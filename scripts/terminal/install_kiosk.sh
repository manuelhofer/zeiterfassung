#!/usr/bin/env bash
#
# ===========================================================================
#  Zeiterfassung - Hallenterminal, Stufe 4: Kiosk
# ===========================================================================
#
#  Aufruf:
#      sudo ./scripts/terminal/install_kiosk.sh [antwortdatei]
#
#  Ergebnis:
#      Das Geraet startet von selbst in einen Vollbild-Browser auf der
#      Terminal-Oberflaeche. Kein Anmeldebildschirm, keine Adresszeile, kein
#      Bildschirmschoner. Stuerzt der Browser ab, startet ihn systemd neu.
#
#  Voraussetzung:
#      Stufe 3 (scripts/terminal/install_terminal.sh) ist gelaufen - es muss
#      etwas geben, das der Browser anzeigen kann. Fehlt es, warnt dieses
#      Skript, bricht aber nicht ab: Ein Abbild laesst sich auch in der
#      umgekehrten Reihenfolge bauen.
#
#  Was dieses Skript NICHT macht - dafuer gibt es eigene Stufen:
#      - Peripherie: RFID-Leser, Touchscreen-Drehung          (Stufe 5,
#        install_peripherie.sh - haengt sich unten in die X11-Sitzung ein)
#      - Selbsttest mit Scan-Proben                           (Stufe 6,
#        selbsttest.sh)
#
#  Idempotent: Mehrfaches Ausfuehren ist unschaedlich und repariert einen
#  halbfertigen Stand.
#
#  Protokoll: /var/log/zeiterfassung-terminal-setup.log (dasselbe wie Stufe 3 -
#  ein Geraet, ein Protokoll).
#
#  Grundlage: docs/spezifikation_terminal_installation.md, Abschnitt 7.
# ===========================================================================

set -uo pipefail

# ---------------------------------------------------------------------------
# Vorbedingung: root. Es werden Benutzer angelegt und Dienste eingerichtet.
# ---------------------------------------------------------------------------
if [ "$(id -u)" -ne 0 ]; then
    echo "FEHLER: Bitte mit sudo starten:  sudo bash $0"
    exit 1
fi

SKRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

LOGDATEI="/var/log/zeiterfassung-terminal-setup.log"
if ! touch "$LOGDATEI" 2>/dev/null; then
    LOGDATEI="/tmp/zeiterfassung-terminal-setup.log"
    touch "$LOGDATEI"
fi
chmod 0640 "$LOGDATEI" 2>/dev/null
exec > >(tee -a "$LOGDATEI") 2>&1

# ---------------------------------------------------------------------------
# Standardwerte - alle ueber die Antwortdatei ueberschreibbar.
# ---------------------------------------------------------------------------
ZIEL_VERZEICHNIS="/opt/zeiterfassung"
KIOSK_BENUTZER="terminal"
KIOSK_URL="http://localhost/terminal.php"
KIOSK_ANZEIGE="auto"              # auto | wayland | x11
KIOSK_BROWSER=""                  # leer = suchen
KIOSK_ANMELDESCHIRM="abschalten"  # abschalten | belassen

KIOSK_START="/usr/local/bin/zeiterfassung-kiosk"
KIOSK_KONFIG="/etc/zeiterfassung-kiosk.conf"
KIOSK_DIENST="/etc/systemd/system/zeiterfassung-kiosk.service"

WARNUNGEN=()

schritt()  { echo; echo "---- $* ----"; }
warnung()  { echo "WARNUNG: $*"; WARNUNGEN+=("$*"); }

# Fuer die Ergebnisliste am Ende: pruefe "<Text>" <Befehl...>
ERGEBNIS_FEHLT=0
pruefe() {
    local text="$1"; shift
    if "$@" </dev/null >/dev/null 2>&1; then
        printf '  [ OK    ] %s\n' "$text"
    else
        printf '  [ FEHLT ] %s\n' "$text"
        ERGEBNIS_FEHLT=$((ERGEBNIS_FEHLT + 1))
    fi
}

echo "=============================================================="
echo "  Zeiterfassung - Terminal-Kiosk (Stufe 4)"
echo "  Skript:  $SKRIPTDIR"
echo "  Start:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================="

# ---------------------------------------------------------------------------
schritt "1/8  Antwortdatei und Vorbedingungen"
# ---------------------------------------------------------------------------
ANTWORTDATEI="${1:-$SKRIPTDIR/terminal.conf}"
if [ -f "$ANTWORTDATEI" ]; then
    # shellcheck disable=SC1090
    . "$ANTWORTDATEI"
    echo "Antwortdatei gelesen: $ANTWORTDATEI"
else
    echo "Keine Antwortdatei ($ANTWORTDATEI) - es gelten die Standardwerte."
    echo "Vorlage: $SKRIPTDIR/terminal.conf.example"
fi

# Erkennung wie in install_terminal.sh. Bewusst kopiert statt in eine
# gemeinsame Datei gezogen: Das haette das bereits im Container gepruefte
# Skript der Stufe 3 angefasst. Als offener Punkt notiert (T-104).
FAMILIE=""
BETRIEBSSYSTEM="unbekannt"
if [ -r /etc/os-release ]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    BETRIEBSSYSTEM="${PRETTY_NAME:-${NAME:-unbekannt}}"
    case " ${ID:-} ${ID_LIKE:-} " in
        *debian*|*ubuntu*|*raspbian*|*mint*)         FAMILIE="apt" ;;
        *arch*|*cachyos*|*manjaro*|*endeavouros*)    FAMILIE="pacman" ;;
        *fedora*|*rhel*|*centos*|*rocky*|*alma*)     FAMILIE="dnf" ;;
        *suse*|*sles*)                               FAMILIE="zypper" ;;
    esac
fi

echo "System:  $BETRIEBSSYSTEM"
if [ -z "$FAMILIE" ]; then
    echo "FEHLER: Paketmanager nicht erkannt (weder apt, pacman, dnf noch zypper)."
    echo "        Diese Distribution muss von Hand vorbereitet werden;"
    echo "        siehe docs/spezifikation_terminal_installation.md, Abschnitt 7."
    exit 1
fi
echo "Familie: $FAMILIE"

if [ ! -f "$ZIEL_VERZEICHNIS/public/terminal.php" ]; then
    warnung "$ZIEL_VERZEICHNIS/public/terminal.php fehlt - zuerst install_terminal.sh laufen lassen."
fi

# Zuordnungstabelle: alles, was je Familie anders heisst, steht hier an EINER
# Stelle.
#
# PAKETE_BROWSER ist eine Liste von Alternativen - genommen wird die erste, die
# sich installieren laesst. Debian nennt das Paket 'chromium', Raspberry Pi OS
# 'chromium-browser'; wer beides fest verdrahtet, bekommt auf dem jeweils
# anderen System einen Fehlschlag.
case "$FAMILIE" in
    apt)
        PAKETE_WAYLAND="cage"
        PAKETE_X11="xserver-xorg xinit x11-xserver-utils openbox unclutter"
        PAKETE_BROWSER="chromium chromium-browser firefox-esr"
        ;;
    pacman)
        PAKETE_WAYLAND="cage"
        PAKETE_X11="xorg-server xorg-xinit xorg-xset openbox unclutter"
        PAKETE_BROWSER="chromium firefox"
        ;;
    dnf)
        PAKETE_WAYLAND="cage"
        PAKETE_X11="xorg-x11-server-Xorg xorg-x11-xinit xset openbox unclutter"
        PAKETE_BROWSER="chromium firefox"
        ;;
    zypper)
        PAKETE_WAYLAND="cage"
        PAKETE_X11="xorg-x11-server xinit xset openbox unclutter"
        PAKETE_BROWSER="chromium firefox"
        ;;
esac

echo "Zielverzeichnis: $ZIEL_VERZEICHNIS"
echo "Kioskbenutzer:   $KIOSK_BENUTZER"
echo "Adresse:         $KIOSK_URL"
echo "Anzeigeweg:      $KIOSK_ANZEIGE"

# ---------------------------------------------------------------------------
schritt "2/8  Grafikstack und Browser installieren"
# ---------------------------------------------------------------------------
# Stufe 3 hat diese Pakete bewusst ausgelassen: ohne Kiosk waeren sie nur
# Wartezeit gewesen und im Container nicht pruefbar.

paket_installieren() {
    local paket="$1"
    echo "  installiere: $paket"
    case "$FAMILIE" in
        apt)    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$paket" ;;
        pacman) pacman -S --needed --noconfirm "$paket" ;;
        dnf)    dnf install -y "$paket" ;;
        zypper) zypper --non-interactive install "$paket" ;;
    esac
}

# Erste Alternative, die sich installieren laesst - welches Programm dabei
# herauskommt, sucht Schritt 3 ohnehin selbst.
paket_installieren_alternativ() {
    local paket
    for paket in $1; do
        paket_installieren "$paket" && return 0
        echo "  nicht verfuegbar: $paket - naechste Alternative"
    done
    return 1
}

case "$FAMILIE" in
    apt)    DEBIAN_FRONTEND=noninteractive apt-get update || warnung "apt-get update fehlgeschlagen." ;;
    pacman) pacman -Sy --noconfirm || warnung "pacman -Sy fehlgeschlagen." ;;
esac

# Reihenfolge nach Spezifikation Abschnitt 7: Wayland bevorzugt, X11 als
# Rueckfall. Ein einzelnes fehlendes Paket bricht den Lauf nicht ab - welcher
# Weg tatsaechlich gangbar ist, entscheidet Schritt 3 anhand der Programme,
# die danach wirklich da sind.
if [ "$KIOSK_ANZEIGE" = "auto" ] || [ "$KIOSK_ANZEIGE" = "wayland" ]; then
    for paket in $PAKETE_WAYLAND; do
        paket_installieren "$paket" || warnung "Paket '$paket' (Wayland) nicht installierbar."
    done
fi
if [ "$KIOSK_ANZEIGE" = "x11" ] || { [ "$KIOSK_ANZEIGE" = "auto" ] && ! command -v cage >/dev/null 2>&1; }; then
    for paket in $PAKETE_X11; do
        paket_installieren "$paket" || warnung "Paket '$paket' (X11) nicht installierbar."
    done
fi

if [ -n "$KIOSK_BROWSER" ]; then
    echo "Browser aus der Antwortdatei vorgegeben: $KIOSK_BROWSER"
else
    paket_installieren_alternativ "$PAKETE_BROWSER" || \
        warnung "Kein Browser installierbar - Kandidaten waren: $PAKETE_BROWSER"
fi

# ---------------------------------------------------------------------------
schritt "3/8  Anzeigeweg und Browser festlegen"
# ---------------------------------------------------------------------------
# Entschieden wird nach dem, was auf dem Geraet liegt, nicht nach dem, was die
# Paketinstallation gemeldet hat: Auf openSUSE Leap gibt es 'cage' je nach
# Version gar nicht, und ein "installiert" ohne Programm hilft niemandem.
if [ "$KIOSK_ANZEIGE" = "auto" ]; then
    if command -v cage >/dev/null 2>&1; then
        KIOSK_ANZEIGE="wayland"
    elif command -v xinit >/dev/null 2>&1; then
        KIOSK_ANZEIGE="x11"
    else
        echo "FEHLER: Weder 'cage' (Wayland) noch 'xinit' (X11) vorhanden."
        echo "        Ohne eines von beiden gibt es kein Bild. Pakete pruefen:"
        echo "        Wayland: $PAKETE_WAYLAND"
        echo "        X11:     $PAKETE_X11"
        exit 1
    fi
fi

case "$KIOSK_ANZEIGE" in
    wayland)
        command -v cage >/dev/null 2>&1 || { echo "FEHLER: 'cage' fehlt, ist aber vorgegeben."; exit 1; }
        ;;
    x11)
        command -v xinit >/dev/null 2>&1 || { echo "FEHLER: 'xinit' fehlt, ist aber vorgegeben."; exit 1; }
        ;;
    *)
        echo "FEHLER: KIOSK_ANZEIGE='$KIOSK_ANZEIGE' - erlaubt sind auto, wayland, x11."
        exit 1
        ;;
esac
echo "Anzeigeweg: $KIOSK_ANZEIGE"

if [ -z "$KIOSK_BROWSER" ]; then
    for kandidat in chromium chromium-browser google-chrome-stable google-chrome firefox firefox-esr; do
        if command -v "$kandidat" >/dev/null 2>&1; then
            KIOSK_BROWSER="$(command -v "$kandidat")"
            break
        fi
    done
fi
if [ -z "$KIOSK_BROWSER" ]; then
    echo "FEHLER: Kein Browser gefunden. Ohne Browser kein Kiosk."
    exit 1
fi
echo "Browser: $KIOSK_BROWSER"

# Firefox kennt weder die Chromium-Schalter noch eine brauchbare Absperrung
# gegen Wischgesten. Er ist der Rueckfall, nicht die Empfehlung.
case "$KIOSK_BROWSER" in
    *firefox*) warnung "Firefox als Kioskbrowser: Wisch-Navigation und Zoom lassen sich nicht abschalten." ;;
esac

# ---------------------------------------------------------------------------
schritt "4/8  Benutzer '$KIOSK_BENUTZER' anlegen"
# ---------------------------------------------------------------------------
# Eigener Benutzer, nicht root: Ein Browser mit Netzzugang laeuft nicht mit
# allen Rechten des Geraets. Ein Passwort bekommt er nicht - angemeldet wird
# er ausschliesslich vom Dienst, und ohne Passwort ist auch nichts zu erraten.
if id "$KIOSK_BENUTZER" >/dev/null 2>&1; then
    echo "Benutzer '$KIOSK_BENUTZER' ist vorhanden."
else
    KIOSK_SHELL="/bin/bash"
    [ -x "$KIOSK_SHELL" ] || KIOSK_SHELL="/bin/sh"
    if useradd --create-home --shell "$KIOSK_SHELL" --comment "Zeiterfassung Kiosk" "$KIOSK_BENUTZER"; then
        echo "Benutzer '$KIOSK_BENUTZER' angelegt (ohne Passwort)."
    else
        echo "FEHLER: Benutzer '$KIOSK_BENUTZER' liess sich nicht anlegen."
        exit 1
    fi
fi

KIOSK_HOME="$(getent passwd "$KIOSK_BENUTZER" | cut -d: -f6)"
[ -n "$KIOSK_HOME" ] || KIOSK_HOME="/home/$KIOSK_BENUTZER"
mkdir -p "$KIOSK_HOME"
chown "$KIOSK_BENUTZER" "$KIOSK_HOME"

# Gruppen fuer Bildschirm und Eingabegeraete. Mit systemd-logind vergibt die
# Sitzung diese Rechte ohnehin ueber den Seat; die Mitgliedschaft ist der
# Rueckfall fuer Systeme, auf denen das nicht greift.
for gruppe in video input render tty audio; do
    getent group "$gruppe" >/dev/null 2>&1 && usermod -aG "$gruppe" "$KIOSK_BENUTZER"
done
echo "Gruppen: $(id -nG "$KIOSK_BENUTZER")"

# ---------------------------------------------------------------------------
schritt "5/8  Kiosk-Konfiguration schreiben"
# ---------------------------------------------------------------------------
# Adresse, Browser und Anzeigeweg stehen in einer eigenen Datei und nicht im
# Startskript: Wer am Geraet die Adresse aendern will, soll dafuer nicht das
# Installationsskript erneut laufen lassen muessen.
cat > "$KIOSK_KONFIG" <<EOF
# ==========================================================================
# Zeiterfassung - Hallenterminal, Einstellungen des Kioskbrowsers
# Erzeugt von scripts/terminal/install_kiosk.sh am $(date '+%d.%m.%Y %H:%M:%S')
#
# Nach einer Aenderung:  systemctl restart zeiterfassung-kiosk
# ==========================================================================

# Startadresse. Bei fehlender Kopplung zeigt terminal.php die Einrichtungsseite.
KIOSK_URL="$KIOSK_URL"

# Browserprogramm.
KIOSK_BROWSER="$KIOSK_BROWSER"

# wayland (cage) oder x11 (Xorg mit openbox).
KIOSK_ANZEIGE="$KIOSK_ANZEIGE"
EOF
chmod 0644 "$KIOSK_KONFIG"
echo "Geschrieben: $KIOSK_KONFIG"

# ---------------------------------------------------------------------------
schritt "6/8  Startskript schreiben"
# ---------------------------------------------------------------------------
cat > "$KIOSK_START" <<EOF
#!/usr/bin/env bash
# Erzeugt von scripts/terminal/install_kiosk.sh am $(date '+%d.%m.%Y %H:%M:%S').
# Einstellungen stehen in $KIOSK_KONFIG - diese Datei muss dafuer nicht
# angefasst werden.
EOF

# Ab hier bewusst ein abgeschirmtes Heredoc ('EOF' in Anfuehrungszeichen):
# Das Startskript bringt eigene Variablen mit, die beim Erzeugen NICHT
# ersetzt werden duerfen.
cat >> "$KIOSK_START" <<'EOF'

set -u

KIOSK_URL="http://localhost/terminal.php"
KIOSK_BROWSER="chromium"
KIOSK_ANZEIGE="wayland"
# shellcheck disable=SC1091
[ -r /etc/zeiterfassung-kiosk.conf ] && . /etc/zeiterfassung-kiosk.conf

# cage (wlroots) legt seinen Wayland-Socket in XDG_RUNTIME_DIR ab und bricht
# ohne beschreibbares Verzeichnis ab. Ueblicherweise liefert die
# Anmeldesitzung /run/user/<uid>; kommt dort keine Sitzung zustande, bleibt
# das vom Dienst angelegte RuntimeDirectory. Ohne diesen Rueckfall bliebe der
# Bildschirm schwarz, und im Journal staende nur eine Zeile ueber ein nicht
# gesetztes XDG_RUNTIME_DIR.
if [ -z "${XDG_RUNTIME_DIR:-}" ] || [ ! -w "${XDG_RUNTIME_DIR:-/nicht-vorhanden}" ]; then
    export XDG_RUNTIME_DIR="${RUNTIME_DIRECTORY:-/run/zeiterfassung-kiosk}"
fi

# setterm besteht auf einem gesetzten TERM, auch wenn es nur Steuerzeichen
# schreibt.
export TERM="${TERM:-linux}"

PROFIL="$HOME/.config/zeiterfassung-kiosk"
mkdir -p "$PROFIL/Default"

# Nach einem Absturz zeigt Chromium beim naechsten Start eine
# Wiederherstellen-Leiste. Auf einem Terminal ohne Tastatur bleibt sie stehen,
# bis jemand mit einer Maus danebengeht - deshalb wird der Absturzvermerk im
# Profil vor jedem Start zurueckgesetzt.
EINSTELLUNGEN="$PROFIL/Default/Preferences"
if [ -f "$EINSTELLUNGEN" ]; then
    sed -i 's/"exit_type":"[^"]*"/"exit_type":"Normal"/g; s/"exited_cleanly":false/"exited_cleanly":true/g' \
        "$EINSTELLUNGEN" 2>/dev/null
fi

case "$KIOSK_BROWSER" in
    *firefox*)
        BROWSER_ARGUMENTE=(--kiosk "$KIOSK_URL")
        ;;
    *)
        BROWSER_ARGUMENTE=(
            --kiosk
            --user-data-dir="$PROFIL"
            --noerrdialogs                  # keine Dialoge, die niemand wegklickt
            --disable-infobars
            --no-first-run
            --disable-session-crashed-bubble
            --hide-crash-restore-bubble
            --disable-features=TranslateUI
            --disable-pinch                 # kein Zoom durch zwei Finger am Touchscreen
            --overscroll-history-navigation=0   # kein "Zurueck" durch Wischen
            --check-for-update-interval=31536000
            --password-store=basic          # sonst wartet der Browser auf einen Schluesselbund
            "$KIOSK_URL"
        )
        ;;
esac

# Der Kernel dunkelt die Textkonsole nach zehn Minuten ab. Unter X11 faellt das
# nicht auf, unter cage schon - dort haengt der Bildschirm an derselben
# Konsole. /dev/tty1 gehoert waehrend der Sitzung dem Kioskbenutzer.
# Meldungen von setterm gehen bewusst ins Leere: Steht TERM auf etwas anderem
# als einer Konsole (Wartung ueber SSH), meldet es nur, dass es nichts tun
# kann - abdunkeln wuerde dann ohnehin niemand sehen.
if [ -w /dev/tty1 ] && command -v setterm >/dev/null 2>&1; then
    setterm --blank 0 --powerdown 0 > /dev/tty1 2>/dev/null
fi

# --- X11-Sitzung: xinit ruft dieses Skript ein zweites Mal auf --------------
if [ "${1:-}" = "--x11-sitzung" ]; then
    xset s off        2>/dev/null   # Bildschirmschoner
    xset -dpms        2>/dev/null   # Energiesparen des Bildschirms
    xset s noblank    2>/dev/null
    # Peripherie (Stufe 5): dreht Bild und Beruehrung gleich herum. Muss
    # innerhalb der X-Sitzung laufen - ausserhalb fehlt die Anzeige. Fehlt die
    # Datei, ist an diesem Geraet nichts zu drehen.
    [ -x /usr/local/bin/zeiterfassung-peripherie-x11 ] && /usr/local/bin/zeiterfassung-peripherie-x11
    # Mauszeiger sofort ausblenden. Am Touchscreen ohne Maus steht er sonst
    # mitten im Bild.
    command -v unclutter >/dev/null 2>&1 && unclutter -idle 0 -root &
    # Ohne Fenstermanager bekommt der Browser sein Vollbild nicht zuverlaessig.
    command -v openbox >/dev/null 2>&1 && openbox &
    exec "$KIOSK_BROWSER" "${BROWSER_ARGUMENTE[@]}"
fi

case "$KIOSK_ANZEIGE" in
    wayland)
        # -d: keine Fensterrahmen. -s: Umschalten auf eine Textkonsole bleibt
        # moeglich - sonst hilft am Geraet nur noch der Netzstecker.
        case "$KIOSK_BROWSER" in
            *firefox*) export MOZ_ENABLE_WAYLAND=1 ;;
            *)         BROWSER_ARGUMENTE=(--ozone-platform=wayland "${BROWSER_ARGUMENTE[@]}") ;;
        esac
        exec cage -d -s -- "$KIOSK_BROWSER" "${BROWSER_ARGUMENTE[@]}"
        ;;
    x11)
        exec xinit "$0" --x11-sitzung -- :0 vt1 -nolisten tcp
        ;;
    *)
        echo "FEHLER: KIOSK_ANZEIGE='$KIOSK_ANZEIGE' unbekannt." >&2
        exit 1
        ;;
esac
EOF

chmod 0755 "$KIOSK_START"
echo "Geschrieben: $KIOSK_START"

# Debian erlaubt den Start des X-Servers nur von der Konsole aus. Die
# Kiosksitzung ist eine Konsolensitzung - ohne diese Datei entscheidet je nach
# Abbild die Voreinstellung, und der Rueckfall auf X11 scheitert lautlos.
if [ "$FAMILIE" = "apt" ] && [ "$KIOSK_ANZEIGE" = "x11" ]; then
    mkdir -p /etc/X11
    cat > /etc/X11/Xwrapper.config <<'EOF'
# Erzeugt von scripts/terminal/install_kiosk.sh
allowed_users=console
needs_root_rights=auto
EOF
fi

# ---------------------------------------------------------------------------
schritt "7/8  systemd-Dienst einrichten"
# ---------------------------------------------------------------------------
# Bewusst ein Systemdienst mit PAMName=login statt Autologin ueber getty:
#
#   - Restart=always erledigt die Forderung "Browser startet nach einem
#     Absturz neu" ohne Schleife in einer Profildatei.
#   - PAMName=login erzeugt eine echte Anmeldesitzung. Ohne die gibt es keinen
#     Seat, und weder cage noch Xorg bekommen Bildschirm und Eingabegeraete.
#   - Conflicts=getty@tty1: sonst streiten sich Anmeldeaufforderung und Kiosk
#     um dieselbe Konsole, und die Tastatureingaben landen abwechselnd hier
#     und dort.
if ! command -v systemctl >/dev/null 2>&1; then
    warnung "Kein systemd - der Kiosk-Dienst wird geschrieben, aber nicht gestartet."
fi

cat > "$KIOSK_DIENST" <<EOF
# ==========================================================================
# Zeiterfassung - Hallenterminal (Kiosk)
# Erzeugt von scripts/terminal/install_kiosk.sh am $(date '+%Y-%m-%d %H:%M:%S')
# Einstellungen: $KIOSK_KONFIG
# ==========================================================================
[Unit]
Description=Zeiterfassung - Hallenterminal (Kiosk)
Documentation=file://$ZIEL_VERZEICHNIS/docs/spezifikation_terminal_installation.md
After=systemd-user-sessions.service getty@tty1.service network.target
Conflicts=getty@tty1.service

[Service]
Type=simple
User=$KIOSK_BENUTZER
PAMName=login
WorkingDirectory=$KIOSK_HOME
Environment=HOME=$KIOSK_HOME
ExecStart=$KIOSK_START

# Rueckfall fuer XDG_RUNTIME_DIR: legt /run/zeiterfassung-kiosk an, dem
# Kioskbenutzer gehoerend und nur fuer ihn lesbar. Gebraucht wird es nur,
# wenn logind keine Sitzung mit /run/user/<uid> zustande bringt - das
# Startskript entscheidet zur Laufzeit. Ein hier fest eingetragenes
# XDG_RUNTIME_DIR waere falsch: %U loest in einer Systemeinheit auf die
# Kennung des Dienstverwalters auf (0), nicht auf die aus User=.
RuntimeDirectory=zeiterfassung-kiosk
RuntimeDirectoryMode=0700

# Ohne diese Zeile heisst die Kennung im Journal zwar genauso, wird aber
# nirgends zugesichert. 'journalctl -t zeiterfassung-kiosk' ist der Weg zu den
# Meldungen von cage und Browser: Wegen PAMName=login laufen sie in einer
# eigenen Sitzung und tauchen bei 'journalctl -u' nicht auf.
SyslogIdentifier=zeiterfassung-kiosk

TTYPath=/dev/tty1
TTYReset=yes
TTYVHangup=yes
TTYVTDisallocate=yes
StandardInput=tty
StandardOutput=journal
StandardError=journal
UtmpIdentifier=tty1
UtmpMode=user

# Ein Kiosk, der nach einem Absturz dunkel bleibt, ist ein Ausfall der Halle.
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
echo "Geschrieben: $KIOSK_DIENST"

if command -v systemctl >/dev/null 2>&1; then
    systemctl daemon-reload
    systemctl enable zeiterfassung-kiosk.service >/dev/null 2>&1 || \
        warnung "systemctl enable zeiterfassung-kiosk fehlgeschlagen."
fi

# ---------------------------------------------------------------------------
schritt "8/8  Anmeldebildschirm"
# ---------------------------------------------------------------------------
# Ein Anmeldebildschirm auf einem Hallenterminal ist eine Fehlbedienung, die
# auf einen Vollbildbrowser wartet. Deshalb wird er standardmaessig
# abgeschaltet - abschaltbar ueber KIOSK_ANMELDESCHIRM in der Antwortdatei,
# damit niemand seinen Arbeitsplatzrechner damit verliert.
if [ "$KIOSK_ANMELDESCHIRM" = "belassen" ]; then
    echo "Anmeldebildschirm bleibt unveraendert (KIOSK_ANMELDESCHIRM=belassen)."
elif ! command -v systemctl >/dev/null 2>&1; then
    echo "Kein systemd - nichts abzuschalten."
elif systemctl list-unit-files display-manager.service >/dev/null 2>&1 \
     && systemctl is-enabled display-manager.service >/dev/null 2>&1; then
    ANMELDEDIENST="$(basename "$(readlink -f /etc/systemd/system/display-manager.service 2>/dev/null)" 2>/dev/null)"
    systemctl disable display-manager.service >/dev/null 2>&1 && \
        echo "Anmeldebildschirm abgeschaltet: ${ANMELDEDIENST:-display-manager.service}"
    systemctl set-default multi-user.target >/dev/null 2>&1 && \
        echo "Startziel auf multi-user.target gesetzt."
else
    echo "Kein Anmeldebildschirm eingerichtet - nichts abzuschalten."
fi

# ---------------------------------------------------------------------------
schritt "ERGEBNIS"
# ---------------------------------------------------------------------------
pruefe "Benutzer '$KIOSK_BENUTZER' vorhanden" id "$KIOSK_BENUTZER"
pruefe "Startskript ausfuehrbar" test -x "$KIOSK_START"
pruefe "Startskript syntaktisch fehlerfrei" bash -n "$KIOSK_START"
pruefe "Kiosk-Konfiguration vorhanden" test -f "$KIOSK_KONFIG"
pruefe "Browser vorhanden" test -x "$KIOSK_BROWSER"
if [ "$KIOSK_ANZEIGE" = "wayland" ]; then
    pruefe "Wayland-Kiosk 'cage' vorhanden" command -v cage
else
    pruefe "X11 ('xinit') vorhanden" command -v xinit
fi

if command -v systemctl >/dev/null 2>&1; then
    pruefe "Dienst eingerichtet" systemctl cat zeiterfassung-kiosk.service
    pruefe "Dienst startet automatisch" systemctl is-enabled zeiterfassung-kiosk.service
else
    printf '  [ FEHLT ] %s\n' "Dienst eingerichtet - kein systemd auf diesem System"
    ERGEBNIS_FEHLT=$((ERGEBNIS_FEHLT + 1))
fi

# Der Kioskbenutzer darf die Zugangsdaten der Datenbank nicht lesen koennen.
# Stufe 3 legt config/ als root:<Webserver> mit 2770 an; der Kioskbenutzer
# gehoert nicht dazu. Das hier ist die Gegenprobe - sie kostet nichts und
# faellt sonst erst auf, wenn jemand die Rechte von Hand verstellt hat.
KONFIG_DATEI="$ZIEL_VERZEICHNIS/config/config.local.php"
if [ ! -f "$KONFIG_DATEI" ]; then
    printf '  [ OK    ] %s\n' "config.local.php nicht vorhanden - Terminal noch nicht gekoppelt"
elif runuser -u "$KIOSK_BENUTZER" -- test -r "$KONFIG_DATEI" 2>/dev/null; then
    printf '  [ FEHLT ] %s\n' "Kioskbenutzer kann config.local.php lesen - Zugangsdaten offen"
    ERGEBNIS_FEHLT=$((ERGEBNIS_FEHLT + 1))
else
    printf '  [ OK    ] %s\n' "Kioskbenutzer kann config.local.php nicht lesen"
fi

HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$KIOSK_URL" 2>/dev/null)"
if [ "$HTTP_CODE" = "200" ]; then
    printf '  [ OK    ] %s\n' "Startadresse antwortet mit HTTP 200"
else
    printf '  [ FEHLT ] %s\n' "Startadresse antwortet mit HTTP ${HTTP_CODE:-keine Antwort} - $KIOSK_URL"
    ERGEBNIS_FEHLT=$((ERGEBNIS_FEHLT + 1))
fi

echo
if [ "${#WARNUNGEN[@]}" -gt 0 ]; then
    echo "Warnungen aus diesem Lauf:"
    for w in "${WARNUNGEN[@]}"; do echo "  - $w"; done
    echo
fi

echo "=============================================================="
if [ "$ERGEBNIS_FEHLT" -eq 0 ]; then
    echo "  Stufe 4 abgeschlossen."
else
    echo "  Stufe 4 mit $ERGEBNIS_FEHLT offenen Punkten - siehe Liste oben."
fi
echo "  Protokoll: $LOGDATEI"
echo
echo "  Starten:   systemctl start zeiterfassung-kiosk"
echo "  Beenden:   systemctl stop zeiterfassung-kiosk"
echo "  Ansehen:   journalctl -t zeiterfassung-kiosk -f"
echo "             (mit -t, nicht mit -u: Browser und cage laufen in einer"
echo "             eigenen Anmeldesitzung und fehlen in der Sicht der Einheit.)"
echo
echo "  Ob wirklich ein Bild kommt, zeigt sich erst auf echter Hardware oder"
echo "  in einer VM mit Grafik - ein Container hat keinen Bildschirm."
echo "=============================================================="

exit 0
