#!/usr/bin/env bash
#
# ===========================================================================
#  Zeiterfassung - Hallenterminal, Stufe 5: Peripherie
# ===========================================================================
#
#  Aufruf:
#      sudo ./scripts/terminal/install_peripherie.sh [antwortdatei]
#
#  Ergebnis:
#      RFID-Leser und Touchscreen sind eingerichtet: die passende
#      RFID-Betriebsart steht in config/geraet.local.php, eine serielle Bridge
#      laeuft als Dienst, und Bildschirm und Touch sind gleich herum gedreht.
#
#  Voraussetzung:
#      Stufe 3 (install_terminal.sh) ist gelaufen - dort entsteht
#      config/geraet.local.php, das dieses Skript fortschreibt. Stufe 4
#      (install_kiosk.sh) sollte gelaufen sein, wenn gedreht werden soll:
#      Die Drehung haengt sich in den Kioskstart ein.
#
#  Was dieses Skript NICHT kann - und zwar grundsaetzlich nicht:
#      - Erkennen, ob ein USB-RFID-Leser angeschlossen ist. Er meldet sich als
#        Tastatur und ist von einer solchen nicht zu unterscheiden. Nur der
#        Scan-Test in Stufe 6 zeigt es.
#      - Die richtige Drehung erraten. Sie steht in BILDSCHIRM_DREHUNG.
#      - Einen Barcode-Scanner einrichten. Der braucht keine Treiber; was er
#        braucht, ist das richtige Tastaturlayout - das setzt Stufe 3.
#
#  Idempotent: Mehrfaches Ausfuehren ist unschaedlich.
#
#  Protokoll: /var/log/zeiterfassung-terminal-setup.log (dasselbe wie Stufe 3
#  und 4 - ein Geraet, ein Protokoll).
#
#  Grundlage: docs/spezifikation_terminal_installation.md, Abschnitt 6.
# ===========================================================================

set -uo pipefail

# ---------------------------------------------------------------------------
# Vorbedingung: root. Es werden Pakete installiert und Dienste eingerichtet.
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

# usb    = Keyboard-Wedge (tippt wie eine Tastatur), keine Bridge
# bridge = serieller Leser oder RC522 ueber einen lokalen WebSocket-Dienst
# keine  = kein RFID an diesem Geraet (Anmeldung ueber Personalnummer)
RFID_VARIANTE="usb"

RFID_GERAET="/dev/ttyUSB0"
RFID_BAUD="9600"
RFID_WS_URL="ws://127.0.0.1:8765"
RFID_WS_VERZEICHNIS="/opt/rfid-ws"
RFID_WS_BENUTZER="rfidws"

# normal | links | rechts | kopf
BILDSCHIRM_DREHUNG="normal"
# Leer = das erste erkannte Touchgeraet.
TOUCH_GERAET=""
# Leer = der erste verbundene Bildschirm.
BILDSCHIRM_AUSGANG=""

# SPI wird nur auf einem Raspberry Pi gebraucht (RC522). Auf allen anderen
# Geraeten ist der Schalter wirkungslos.
SPI_AKTIVIEREN="auto"   # auto | ja | nein

PERIPHERIE_KONFIG="/etc/zeiterfassung-peripherie.conf"
PERIPHERIE_X11="/usr/local/bin/zeiterfassung-peripherie-x11"
RFID_DIENST="/etc/systemd/system/rfid-ws.service"

WARNUNGEN=()

schritt()  { echo; echo "---- $* ----"; }
warnung()  { echo "WARNUNG: $*"; WARNUNGEN+=("$*"); }

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
echo "  Zeiterfassung - Terminal-Peripherie (Stufe 5)"
echo "  Skript:  $SKRIPTDIR"
echo "  Start:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================="

# ---------------------------------------------------------------------------
schritt "1/6  Antwortdatei und Vorbedingungen"
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

# Erkennung wie in install_terminal.sh und install_kiosk.sh (T-104).
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
    exit 1
fi
echo "Familie: $FAMILIE"

GERAETE_DATEI="$ZIEL_VERZEICHNIS/config/geraet.local.php"
if [ ! -f "$GERAETE_DATEI" ]; then
    warnung "$GERAETE_DATEI fehlt - zuerst install_terminal.sh laufen lassen."
fi

case "$RFID_VARIANTE" in
    usb|bridge|keine) ;;
    *)
        warnung "RFID_VARIANTE='$RFID_VARIANTE' unbekannt - es gilt 'usb'."
        RFID_VARIANTE="usb"
        ;;
esac

case "$BILDSCHIRM_DREHUNG" in
    normal|links|rechts|kopf) ;;
    *)
        warnung "BILDSCHIRM_DREHUNG='$BILDSCHIRM_DREHUNG' unbekannt - es gilt 'normal'."
        BILDSCHIRM_DREHUNG="normal"
        ;;
esac

# Ein Raspberry Pi wird nicht am Paketmanager erkannt, sondern am Geraetebaum.
IST_RASPBERRY="nein"
if [ -r /proc/device-tree/model ] && grep -qi raspberry /proc/device-tree/model 2>/dev/null; then
    IST_RASPBERRY="ja"
fi

echo "RFID-Variante:   $RFID_VARIANTE"
echo "Drehung:         $BILDSCHIRM_DREHUNG"
echo "Raspberry Pi:    $IST_RASPBERRY"

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

# ---------------------------------------------------------------------------
schritt "2/6  Werkzeuge fuer Erkennung und Drehung"
# ---------------------------------------------------------------------------
# libinput zeigt Eingabegeraete an - damit laesst sich ein Touchscreen finden.
# xinput/xrandr braucht nur der X11-Weg; sie fehlen auf einem reinen
# Wayland-Geraet und werden dann auch nicht gebraucht.
case "$FAMILIE" in
    apt)    WERKZEUG_LIBINPUT="libinput-tools" ;;
    pacman) WERKZEUG_LIBINPUT="libinput" ;;
    dnf)    WERKZEUG_LIBINPUT="libinput-utils" ;;
    zypper) WERKZEUG_LIBINPUT="libinput-tools" ;;
esac

if ! command -v libinput >/dev/null 2>&1; then
    paket_installieren "$WERKZEUG_LIBINPUT" || \
        warnung "'$WERKZEUG_LIBINPUT' nicht installierbar - Touchscreen wird nicht erkannt."
fi

# Nur nachziehen, wenn der Kiosk tatsaechlich auf X11 laeuft.
KIOSK_ANZEIGE_IST=""
if [ -r /etc/zeiterfassung-kiosk.conf ]; then
    KIOSK_ANZEIGE_IST="$(sed -n 's/^KIOSK_ANZEIGE="\{0,1\}\([a-z0-9]*\).*/\1/p' /etc/zeiterfassung-kiosk.conf | head -n1)"
fi
echo "Kiosk-Anzeigeweg laut Stufe 4: ${KIOSK_ANZEIGE_IST:-unbekannt}"

if [ "$KIOSK_ANZEIGE_IST" = "x11" ] && [ "$BILDSCHIRM_DREHUNG" != "normal" ]; then
    case "$FAMILIE" in
        apt)    PAKETE_XTOOLS="x11-xserver-utils xinput" ;;
        pacman) PAKETE_XTOOLS="xorg-xrandr xorg-xinput" ;;
        dnf)    PAKETE_XTOOLS="xrandr xinput" ;;
        zypper) PAKETE_XTOOLS="xrandr xinput" ;;
    esac
    for paket in $PAKETE_XTOOLS; do
        command -v "${paket##*-}" >/dev/null 2>&1 || paket_installieren "$paket" || \
            warnung "Paket '$paket' nicht installierbar - Drehung unter X11 unvollstaendig."
    done
fi

# ---------------------------------------------------------------------------
schritt "3/6  RFID einrichten"
# ---------------------------------------------------------------------------
RFID_WS_AKTIV="false"

case "$RFID_VARIANTE" in
    usb)
        echo "USB-Leser (Keyboard-Wedge): keine Treiber, kein Dienst."
        echo "Der Leser tippt die Kennung wie eine Tastatur in das Eingabefeld."
        echo
        echo "Was hier NICHT geprueft werden kann: ob ueberhaupt einer"
        echo "angeschlossen ist. Ein solcher Leser meldet sich als Tastatur und"
        echo "ist von einer Tastatur nicht zu unterscheiden. Das zeigt erst der"
        echo "Scan-Test:  sudo ./scripts/terminal/selbsttest.sh"
        RFID_WS_AKTIV="false"
        ;;

    keine)
        echo "Kein RFID an diesem Geraet - Anmeldung ueber Personalnummer."
        RFID_WS_AKTIV="false"
        ;;

    bridge)
        echo "Serielle Bridge: lokaler Dienst liest den Leser und reicht die"
        echo "Kennung per WebSocket an den Browser weiter."

        # --- SPI (nur Raspberry Pi, nur fuer RC522) ------------------------
        if [ "$SPI_AKTIVIEREN" = "ja" ] || { [ "$SPI_AKTIVIEREN" = "auto" ] && [ "$IST_RASPBERRY" = "ja" ]; }; then
            BOOTKONFIG=""
            for kandidat in /boot/firmware/config.txt /boot/config.txt; do
                [ -f "$kandidat" ] && { BOOTKONFIG="$kandidat"; break; }
            done

            if [ -n "$BOOTKONFIG" ]; then
                if grep -qE '^\s*dtparam=spi=on' "$BOOTKONFIG"; then
                    echo "SPI ist in $BOOTKONFIG bereits eingeschaltet."
                else
                    printf '\n# Zeiterfassung Stufe 5: SPI fuer den RC522-Leser\ndtparam=spi=on\n' >> "$BOOTKONFIG"
                    echo "SPI in $BOOTKONFIG eingeschaltet."
                    warnung "SPI wirkt erst nach einem Neustart. Danach Stufe 5 erneut laufen lassen."
                fi
            else
                warnung "Keine Boot-Konfiguration gefunden - SPI muss von Hand eingeschaltet werden."
            fi
        fi

        # --- Python-Umgebung ----------------------------------------------
        case "$FAMILIE" in
            apt)    PAKETE_PYTHON="python3 python3-venv" ;;
            pacman) PAKETE_PYTHON="python python-virtualenv" ;;
            dnf)    PAKETE_PYTHON="python3 python3-virtualenv" ;;
            zypper) PAKETE_PYTHON="python3 python3-virtualenv" ;;
        esac
        for paket in $PAKETE_PYTHON; do
            paket_installieren "$paket" || warnung "Paket '$paket' nicht installierbar."
        done

        mkdir -p "$RFID_WS_VERZEICHNIS"

        # Dienstbenutzer ohne Anmeldung. Der Dienst braucht nur den seriellen
        # Anschluss - er hat mit dem Webserver nichts zu tun und laeuft
        # deshalb bewusst nicht als dessen Benutzer.
        if ! id "$RFID_WS_BENUTZER" >/dev/null 2>&1; then
            useradd --system --no-create-home --shell /usr/sbin/nologin "$RFID_WS_BENUTZER" 2>/dev/null || \
                useradd --system --no-create-home --shell /sbin/nologin "$RFID_WS_BENUTZER" 2>/dev/null || \
                warnung "Benutzer '$RFID_WS_BENUTZER' konnte nicht angelegt werden."
        fi
        # Serielle Anschluesse gehoeren der Gruppe 'dialout' (Debian) bzw.
        # 'uucp' (Arch/SUSE). Ohne Mitgliedschaft liest der Dienst nichts.
        for gruppe in dialout uucp; do
            getent group "$gruppe" >/dev/null 2>&1 && usermod -aG "$gruppe" "$RFID_WS_BENUTZER" 2>/dev/null
        done
        # RC522 haengt am SPI, nicht am seriellen Anschluss.
        getent group spi >/dev/null 2>&1 && usermod -aG spi "$RFID_WS_BENUTZER" 2>/dev/null

        # --- Dienstprogramm ------------------------------------------------
        # Die Vorlage liegt im Repository; Anschluss und Baudrate kommen aus
        # der Antwortdatei, damit niemand im Python-Quelltext editieren muss.
        QUELLE_PY="$ZIEL_VERZEICHNIS/docs/terminal/rfid_ws.py"
        if [ ! -f "$QUELLE_PY" ] && [ -f "$SKRIPTDIR/../../docs/terminal/rfid_ws.py" ]; then
            QUELLE_PY="$SKRIPTDIR/../../docs/terminal/rfid_ws.py"
        fi

        if [ -f "$QUELLE_PY" ]; then
            cp "$QUELLE_PY" "$RFID_WS_VERZEICHNIS/rfid_ws.py"
            # Anschluss, Baudrate und Port eintragen.
            sed -i \
                -e "s|^SERIAL_PORT *=.*|SERIAL_PORT = \"$RFID_GERAET\"|" \
                -e "s|^BAUD *=.*|BAUD = $RFID_BAUD|" \
                "$RFID_WS_VERZEICHNIS/rfid_ws.py"
            echo "Dienstprogramm nach $RFID_WS_VERZEICHNIS/rfid_ws.py kopiert."
        else
            warnung "rfid_ws.py nicht gefunden - die Bridge bleibt unvollstaendig."
        fi

        # --- Abhaengigkeiten in einer eigenen Umgebung ----------------------
        # venv statt Systempakete: Debian 12 verweigert pip auf Systemebene
        # (PEP 668), und die beiden Bibliotheken haben im Rest des Systems
        # nichts zu suchen.
        if [ ! -x "$RFID_WS_VERZEICHNIS/venv/bin/python" ]; then
            python3 -m venv "$RFID_WS_VERZEICHNIS/venv" || \
                warnung "Python-Umgebung konnte nicht angelegt werden."
        fi
        if [ -x "$RFID_WS_VERZEICHNIS/venv/bin/pip" ]; then
            "$RFID_WS_VERZEICHNIS/venv/bin/pip" install --quiet --upgrade pip 2>/dev/null
            "$RFID_WS_VERZEICHNIS/venv/bin/pip" install --quiet pyserial websockets || \
                warnung "pyserial/websockets nicht installierbar - ohne Netz geht das nicht."
        fi

        chown -R "$RFID_WS_BENUTZER":"$RFID_WS_BENUTZER" "$RFID_WS_VERZEICHNIS" 2>/dev/null

        # --- Dienst ---------------------------------------------------------
        # Bewusst neu geschrieben statt die Vorlage zu kopieren: Die Vorlage in
        # docs/terminal/ nennt feste Pfade und www-data als Benutzer. Was hier
        # entsteht, passt zu dem, was dieses Skript tatsaechlich angelegt hat.
        cat > "$RFID_DIENST" <<EOF
[Unit]
Description=Zeiterfassung Terminal - RFID WebSocket Bridge
Documentation=file://$ZIEL_VERZEICHNIS/docs/terminal/rfid-ws_rollout.md
After=network.target

[Service]
Type=simple
User=$RFID_WS_BENUTZER
Group=$RFID_WS_BENUTZER
WorkingDirectory=$RFID_WS_VERZEICHNIS
ExecStart=$RFID_WS_VERZEICHNIS/venv/bin/python $RFID_WS_VERZEICHNIS/rfid_ws.py

# Ein Leser, der beim Einschalten noch nicht da ist, darf das Geraet nicht
# dauerhaft ohne Anmeldung lassen.
Restart=always
RestartSec=2

StandardOutput=journal
StandardError=journal

# Der Dienst liest einen Anschluss und schreibt auf einen lokalen Port -
# mehr braucht er nicht.
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true

[Install]
WantedBy=multi-user.target
EOF
        echo "Geschrieben: $RFID_DIENST"

        if [ -d /run/systemd/system ]; then
            systemctl daemon-reload
            systemctl enable rfid-ws.service 2>/dev/null || warnung "rfid-ws.service liess sich nicht aktivieren."
            # Starten nur versuchen, wenn der Anschluss existiert - sonst
            # laeuft der Dienst in eine Neustartschleife, und im Journal steht
            # hundertmal derselbe Fehler.
            if [ -e "$RFID_GERAET" ]; then
                systemctl restart rfid-ws.service 2>/dev/null || warnung "rfid-ws.service startete nicht."
            else
                echo "HINWEIS: $RFID_GERAET ist nicht vorhanden - der Dienst ist"
                echo "         aktiviert, wird aber erst mit angeschlossenem Leser"
                echo "         gestartet. Danach:  systemctl start rfid-ws"
            fi
        else
            echo "HINWEIS: systemd laeuft hier nicht (Container?) - der Dienst ist"
            echo "         geschrieben, aber nicht aktiviert."
        fi

        RFID_WS_AKTIV="true"
        ;;
esac

# ---------------------------------------------------------------------------
schritt "4/6  Touchscreen erkennen"
# ---------------------------------------------------------------------------
TOUCH_GEFUNDEN="nein"
TOUCH_NAME=""

if command -v libinput >/dev/null 2>&1; then
    # 'libinput list-devices' braucht Lesezugriff auf /dev/input.
    LIBINPUT_AUSGABE="$(libinput list-devices 2>/dev/null)"
    if [ -n "$LIBINPUT_AUSGABE" ]; then
        # Ein Touchscreen meldet die Faehigkeit 'touch'; ein Touchpad meldet
        # 'pointer'. Wir suchen den ersten mit Touch.
        TOUCH_NAME="$(printf '%s\n' "$LIBINPUT_AUSGABE" | awk '
            /^Device:/        { name = substr($0, index($0, ":") + 2) }
            /^Capabilities:/  { if ($0 ~ /touch/ && name != "") { print name; exit } }
        ')"
        [ -n "$TOUCH_NAME" ] && TOUCH_GEFUNDEN="ja"
    else
        echo "libinput lieferte nichts - im Container ohne /dev/input der Normalfall."
    fi
else
    echo "libinput nicht vorhanden - Touchscreen kann nicht erkannt werden."
fi

if [ -n "$TOUCH_GERAET" ]; then
    echo "Touchgeraet aus der Antwortdatei vorgegeben: $TOUCH_GERAET"
    TOUCH_NAME="$TOUCH_GERAET"
    TOUCH_GEFUNDEN="ja"
elif [ "$TOUCH_GEFUNDEN" = "ja" ]; then
    echo "Touchscreen erkannt: $TOUCH_NAME"
else
    echo "Kein Touchscreen erkannt."
    if [ "$BILDSCHIRM_DREHUNG" != "normal" ]; then
        warnung "Drehung gewuenscht, aber kein Touchgeraet gefunden - die Bedienung koennte verdreht bleiben."
    fi
fi

# ---------------------------------------------------------------------------
schritt "5/6  Drehung einrichten"
# ---------------------------------------------------------------------------
# Zwei Wege, weil sich Wayland und X11 hier grundsaetzlich unterscheiden:
#
#   X11    - zur Laufzeit ueber xrandr (Bild) und xinput (Beruehrung). Der
#            Kioskstart ruft dafuer ein Hilfsskript auf.
#   cage   - kennt keinen Schalter zum Drehen. Dort dreht der Kernel den
#            Bildschirm ueber die Startzeile (video=...,rotate=), und die
#            Beruehrung folgt der Drehung des Ausgangs automatisch. Das wirkt
#            erst nach einem Neustart, deshalb wird es nur vorbereitet und
#            ausdruecklich gemeldet.
case "$BILDSCHIRM_DREHUNG" in
    normal) XRANDR_DREHUNG="normal";   KERNEL_DREHUNG="0"   ;;
    links)  XRANDR_DREHUNG="left";     KERNEL_DREHUNG="270" ;;
    rechts) XRANDR_DREHUNG="right";    KERNEL_DREHUNG="90"  ;;
    kopf)   XRANDR_DREHUNG="inverted"; KERNEL_DREHUNG="180" ;;
esac

cat > "$PERIPHERIE_KONFIG" <<EOF
# ==========================================================================
# Zeiterfassung - Hallenterminal, Peripherie
# Erzeugt von scripts/terminal/install_peripherie.sh am $(date '+%d.%m.%Y %H:%M:%S')
#
# Diese Datei liest das Kiosk-Startskript. Nach einer Aenderung:
#   systemctl restart zeiterfassung-kiosk
# ==========================================================================

# normal | links | rechts | kopf
PERIPHERIE_DREHUNG="$BILDSCHIRM_DREHUNG"

# Fuer xrandr/xinput unter X11.
PERIPHERIE_DREHUNG_XRANDR="$XRANDR_DREHUNG"
PERIPHERIE_TOUCH_NAME="$TOUCH_NAME"
PERIPHERIE_BILDSCHIRM="$BILDSCHIRM_AUSGANG"

# Betriebsart des RFID-Lesers: usb | bridge | keine
PERIPHERIE_RFID="$RFID_VARIANTE"
EOF
chmod 0644 "$PERIPHERIE_KONFIG"
echo "Geschrieben: $PERIPHERIE_KONFIG"

# --- X11: Hilfsskript, das der Kioskstart aufruft ---------------------------
cat > "$PERIPHERIE_X11" <<'EOF'
#!/usr/bin/env bash
# Erzeugt von scripts/terminal/install_peripherie.sh (Stufe 5).
#
# Dreht unter X11 Bild und Beruehrung gleich herum. Wird vom Kiosk-Startskript
# innerhalb der X-Sitzung aufgerufen - ausserhalb hat es keine Wirkung.
set -u

PERIPHERIE_DREHUNG="normal"
PERIPHERIE_DREHUNG_XRANDR="normal"
PERIPHERIE_TOUCH_NAME=""
PERIPHERIE_BILDSCHIRM=""
# shellcheck disable=SC1091
[ -r /etc/zeiterfassung-peripherie.conf ] && . /etc/zeiterfassung-peripherie.conf

[ "$PERIPHERIE_DREHUNG" = "normal" ] && exit 0
command -v xrandr >/dev/null 2>&1 || exit 0

# Ohne Vorgabe der erste verbundene Ausgang.
AUSGANG="$PERIPHERIE_BILDSCHIRM"
if [ -z "$AUSGANG" ]; then
    AUSGANG="$(xrandr --query 2>/dev/null | awk '/ connected/ {print $1; exit}')"
fi
[ -z "$AUSGANG" ] && exit 0

xrandr --output "$AUSGANG" --rotate "$PERIPHERIE_DREHUNG_XRANDR" 2>/dev/null || exit 0

# Das Bild ist gedreht, die Beruehrung noch nicht: Ein Fingertipp landet sonst
# an der um 90 Grad versetzten Stelle. Die Matrix bildet die Beruehrung auf das
# gedrehte Bild ab.
command -v xinput >/dev/null 2>&1 || exit 0
[ -z "$PERIPHERIE_TOUCH_NAME" ] && exit 0

case "$PERIPHERIE_DREHUNG" in
    links)  MATRIX="0 -1 1 1 0 0 0 0 1" ;;
    rechts) MATRIX="0 1 0 -1 0 1 0 0 1" ;;
    kopf)   MATRIX="-1 0 1 0 -1 1 0 0 1" ;;
    *)      exit 0 ;;
esac

# shellcheck disable=SC2086
xinput set-prop "$PERIPHERIE_TOUCH_NAME" "Coordinate Transformation Matrix" $MATRIX 2>/dev/null
EOF
chmod 0755 "$PERIPHERIE_X11"
echo "Geschrieben: $PERIPHERIE_X11"

# --- Wayland/cage: Drehung ueber die Startzeile des Kernels ------------------
if [ "$BILDSCHIRM_DREHUNG" != "normal" ] && [ "$KIOSK_ANZEIGE_IST" = "wayland" ]; then
    echo
    echo "Anzeigeweg ist 'wayland' (cage). cage hat keinen Schalter zum Drehen -"
    echo "dort dreht der Kernel den Bildschirm. Einzutragen in die Startzeile:"
    echo
    echo "    video=<Ausgang>:rotate=$KERNEL_DREHUNG        (z. B. video=HDMI-A-1:rotate=$KERNEL_DREHUNG)"
    echo
    if [ "$IST_RASPBERRY" = "ja" ]; then
        echo "Auf dem Raspberry Pi: in /boot/firmware/cmdline.txt an das Ende der"
        echo "einen Zeile anhaengen (die Datei hat genau eine Zeile)."
    else
        echo "Ueblicherweise in /etc/default/grub (GRUB_CMDLINE_LINUX_DEFAULT),"
        echo "danach update-grub bzw. grub2-mkconfig."
    fi
    echo
    warnung "Drehung unter cage wurde NICHT gesetzt - sie braucht die Startzeile des Kernels und einen Neustart."
elif [ "$BILDSCHIRM_DREHUNG" != "normal" ]; then
    echo "Drehung '$BILDSCHIRM_DREHUNG' vorbereitet (X11-Weg)."
else
    echo "Keine Drehung gewuenscht - Bild und Beruehrung bleiben, wie sie sind."
fi

# ---------------------------------------------------------------------------
schritt "6/6  config/geraet.local.php fortschreiben"
# ---------------------------------------------------------------------------
# Nur der rfid_ws-Block wird angefasst. Die Zugangsdaten der Ausweichdatenbank
# stehen in derselben Datei und werden dabei aus der vorhandenen Datei
# uebernommen - sie neu zu erzeugen wuerde die Queue des Geraets abhaengen
# (derselbe Fehler wie in P-2026-08-09-05).
if [ -f "$GERAETE_DATEI" ] && command -v php >/dev/null 2>&1; then
    ALT_ENABLED="$(php -r '$d = @include $argv[1]; echo (is_array($d) && !empty($d["offline_db"]["enabled"])) ? "true" : "false";' "$GERAETE_DATEI" 2>/dev/null)"
    ALT_DBNAME="$(php -r '$d = @include $argv[1]; echo (is_array($d) && isset($d["offline_db"]["dbname"])) ? (string)$d["offline_db"]["dbname"] : "";' "$GERAETE_DATEI" 2>/dev/null)"
    ALT_USER="$(php -r '$d = @include $argv[1]; echo (is_array($d) && isset($d["offline_db"]["user"])) ? (string)$d["offline_db"]["user"] : "";' "$GERAETE_DATEI" 2>/dev/null)"
    ALT_PASS="$(php -r '$d = @include $argv[1]; echo (is_array($d) && isset($d["offline_db"]["pass"])) ? (string)$d["offline_db"]["pass"] : "";' "$GERAETE_DATEI" 2>/dev/null)"
    ALT_HOST="$(php -r '$d = @include $argv[1]; echo (is_array($d) && isset($d["offline_db"]["host"])) ? (string)$d["offline_db"]["host"] : "localhost";' "$GERAETE_DATEI" 2>/dev/null)"

    TEMP_DATEI="$(dirname "$GERAETE_DATEI")/.geraet.local.php.tmp"
    cat > "$TEMP_DATEI" <<EOF
<?php
declare(strict_types=1);

/**
 * Geraetelokale Einstellungen dieses Terminals.
 *
 * Angelegt von scripts/terminal/install_terminal.sh,
 * fortgeschrieben von scripts/terminal/install_peripherie.sh
 * am $(date '+%d.%m.%Y %H:%M:%S').
 *
 * Hier steht nur, was der Maschine gehoert: die lokale Ausweichdatenbank und
 * die RFID-Bridge. Die Einrichtungsseite liest diese beiden Bloecke beim
 * Koppeln aus und uebernimmt sie in config/config.local.php.
 *
 * Zugangsdaten zur Hauptdatenbank stehen bewusst NICHT hier - die kommen
 * ausschliesslich aus der Kopplung am Backend.
 */

return [
    'offline_db' => [
        'enabled' => $ALT_ENABLED,
        'host'    => '$ALT_HOST',
        'dbname'  => '$ALT_DBNAME',
        'charset' => 'utf8mb4',
        'user'    => '$ALT_USER',
        'pass'    => '$ALT_PASS',
    ],

    'terminal' => [
        // Betriebsart: $RFID_VARIANTE
        'rfid_ws' => ['enabled' => $RFID_WS_AKTIV, 'url' => '$RFID_WS_URL'],
    ],
];
EOF

    if php -l "$TEMP_DATEI" >/dev/null 2>&1; then
        # Gegenprobe vor dem Umbenennen: Das Passwort der Ausweichdatenbank
        # muss noch dasselbe sein. Ginge es hier verloren, liefe das Terminal
        # weiter - aber seine Queue waere tot.
        NEU_PASS="$(php -r '$d = @include $argv[1]; echo (is_array($d) && isset($d["offline_db"]["pass"])) ? (string)$d["offline_db"]["pass"] : "";' "$TEMP_DATEI" 2>/dev/null)"
        if [ "$NEU_PASS" = "$ALT_PASS" ]; then
            BESITZER="$(stat -c '%U:%G' "$GERAETE_DATEI" 2>/dev/null)"
            mv "$TEMP_DATEI" "$GERAETE_DATEI"
            chmod 0640 "$GERAETE_DATEI"
            [ -n "$BESITZER" ] && chown "$BESITZER" "$GERAETE_DATEI" 2>/dev/null
            echo "Fortgeschrieben: $GERAETE_DATEI  (rfid_ws.enabled = $RFID_WS_AKTIV)"
        else
            rm -f "$TEMP_DATEI"
            warnung "Passwort der Ausweichdatenbank waere verloren gegangen - Datei nicht angefasst."
        fi
    else
        rm -f "$TEMP_DATEI"
        warnung "Neue geraet.local.php war fehlerhaft und wurde verworfen."
    fi

    if [ -f "$ZIEL_VERZEICHNIS/config/config.local.php" ]; then
        echo
        echo "HINWEIS: Dieses Geraet ist bereits gekoppelt. Die Einrichtungsseite"
        echo "         liest geraet.local.php nur BEIM Koppeln - die Aenderung an"
        echo "         rfid_ws wirkt also erst nach einer erneuten Kopplung."
        echo "         Kurzer Weg: den rfid_ws-Block in config.local.php von Hand"
        echo "         angleichen."
    fi
elif [ ! -f "$GERAETE_DATEI" ]; then
    warnung "$GERAETE_DATEI fehlt - rfid_ws konnte nicht gesetzt werden."
else
    warnung "PHP nicht vorhanden - geraet.local.php wurde nicht angefasst."
fi

# ---------------------------------------------------------------------------
schritt "ERGEBNIS"
# ---------------------------------------------------------------------------
pruefe "Peripherie-Konfiguration vorhanden" test -f "$PERIPHERIE_KONFIG"
pruefe "X11-Drehskript ausfuehrbar"         test -x "$PERIPHERIE_X11"
pruefe "X11-Drehskript fehlerfrei"          bash -n "$PERIPHERIE_X11"

if [ "$RFID_VARIANTE" = "bridge" ]; then
    pruefe "Dienstprogramm vorhanden"    test -f "$RFID_WS_VERZEICHNIS/rfid_ws.py"
    pruefe "Python-Umgebung vorhanden"   test -x "$RFID_WS_VERZEICHNIS/venv/bin/python"
    pruefe "Dienstdatei geschrieben"     test -f "$RFID_DIENST"
    if [ -d /run/systemd/system ]; then
        pruefe "Dienst startet automatisch" systemctl is-enabled rfid-ws.service
    fi
fi

if [ -f "$GERAETE_DATEI" ] && command -v php >/dev/null 2>&1; then
    pruefe "geraet.local.php syntaktisch fehlerfrei" php -l "$GERAETE_DATEI"
    pruefe "rfid_ws steht auf $RFID_WS_AKTIV" \
        sh -c "php -r '\$d = @include \$argv[1]; exit((!empty(\$d[\"terminal\"][\"rfid_ws\"][\"enabled\"]) ? \"true\" : \"false\") === \"$RFID_WS_AKTIV\" ? 0 : 1);' '$GERAETE_DATEI'"
fi

echo
if [ ${#WARNUNGEN[@]} -gt 0 ]; then
    echo "Warnungen ($(( ${#WARNUNGEN[@]} ))):"
    for w in "${WARNUNGEN[@]}"; do echo "  - $w"; done
    echo
fi

if [ "$ERGEBNIS_FEHLT" -eq 0 ]; then
    echo "Stufe 5 abgeschlossen."
else
    echo "Stufe 5 abgeschlossen, aber $ERGEBNIS_FEHLT Punkt(e) fehlen - siehe oben."
fi

echo
echo "Naechster Schritt:  sudo $SKRIPTDIR/selbsttest.sh"
echo "Protokoll:          $LOGDATEI"
echo

exit 0
