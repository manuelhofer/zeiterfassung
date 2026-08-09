#!/usr/bin/env bash
#
# ===========================================================================
#  Zeiterfassung - Hallenterminal, Stufe 6: Selbsttest
# ===========================================================================
#
#  Aufruf:
#      sudo ./scripts/terminal/selbsttest.sh [antwortdatei]
#      sudo ./scripts/terminal/selbsttest.sh --ohne-scan   # nichts abfragen
#
#  Zweck:
#      Vor dem Verlassen des Geraets wissen, ob es einsatzbereit ist. Das
#      Ergebnis ist eine Liste mit OK/FEHLT und ein Rueckgabewert: 0, wenn
#      alles steht.
#
#  Warum es das braucht:
#      Die Installationsskripte melden, was SIE getan haben. Ob das Geraet
#      danach wirklich bucht, ist etwas anderes - dazwischen liegen Kopplung,
#      Netz, Datenbank und die Hardware. Genau diese Luecke schliesst dieser
#      Test.
#
#  Aendert nichts. Es wird nur gelesen und abgefragt.
#
#  Protokoll: /var/log/zeiterfassung-terminal-setup.log
#
#  Grundlage: docs/spezifikation_terminal_installation.md, Abschnitt 8.
# ===========================================================================

set -uo pipefail

SKRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

LOGDATEI="/var/log/zeiterfassung-terminal-setup.log"
if ! touch "$LOGDATEI" 2>/dev/null; then
    LOGDATEI="/tmp/zeiterfassung-terminal-setup.log"
    touch "$LOGDATEI" 2>/dev/null
fi
exec > >(tee -a "$LOGDATEI") 2>&1

# ---------------------------------------------------------------------------
# Standardwerte - ueber die Antwortdatei ueberschreibbar.
# ---------------------------------------------------------------------------
ZIEL_VERZEICHNIS="/opt/zeiterfassung"
TERMINAL_URL="http://localhost/terminal.php"
RFID_VARIANTE="usb"
RFID_WS_URL="ws://127.0.0.1:8765"
OFFLINE_DB_NAME="zeiterfassung_offline"

OHNE_SCAN="nein"
ARGUMENTE=()
for arg in "$@"; do
    case "$arg" in
        --ohne-scan) OHNE_SCAN="ja" ;;
        *)           ARGUMENTE+=("$arg") ;;
    esac
done

ANTWORTDATEI="${ARGUMENTE[0]:-$SKRIPTDIR/terminal.conf}"
if [ -f "$ANTWORTDATEI" ]; then
    # shellcheck disable=SC1090
    . "$ANTWORTDATEI"
fi
# Was Stufe 5 tatsaechlich eingerichtet hat, wiegt schwerer als die
# Antwortdatei: Die kann seither geaendert worden sein.
if [ -r /etc/zeiterfassung-peripherie.conf ]; then
    # shellcheck disable=SC1091
    . /etc/zeiterfassung-peripherie.conf
    RFID_VARIANTE="${PERIPHERIE_RFID:-$RFID_VARIANTE}"
fi
if [ -r /etc/zeiterfassung-kiosk.conf ]; then
    # shellcheck disable=SC1091
    . /etc/zeiterfassung-kiosk.conf
    TERMINAL_URL="${KIOSK_URL:-$TERMINAL_URL}"
fi

OK=0
FEHLT=0
UEBERSPRUNGEN=0

melde_ok()   { printf '  [ OK    ] %s\n' "$1"; OK=$((OK + 1)); }
melde_fehlt(){ printf '  [ FEHLT ] %s\n' "$1"; [ -n "${2:-}" ] && printf '            %s\n' "$2"; FEHLT=$((FEHLT + 1)); }
melde_frei() { printf '  [  --   ] %s\n' "$1"; [ -n "${2:-}" ] && printf '            %s\n' "$2"; UEBERSPRUNGEN=$((UEBERSPRUNGEN + 1)); }

schritt() { echo; echo "---- $* ----"; }

echo "=============================================================="
echo "  Zeiterfassung - Terminal-Selbsttest (Stufe 6)"
echo "  Geraet:  $(hostname 2>/dev/null || echo unbekannt)"
echo "  Start:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================="

# ---------------------------------------------------------------------------
schritt "1  Webserver liefert die Terminalseite"
# ---------------------------------------------------------------------------
if command -v curl >/dev/null 2>&1; then
    STATUS="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$TERMINAL_URL" 2>/dev/null)"
    if [ "$STATUS" = "200" ]; then
        melde_ok "$TERMINAL_URL antwortet mit HTTP 200"
    else
        melde_fehlt "$TERMINAL_URL antwortet nicht mit 200" "Erhalten: ${STATUS:-keine Antwort}. Laeuft der Webserver? systemctl status apache2"
    fi
else
    melde_frei "Webserver nicht geprueft" "curl ist nicht installiert."
fi

# Auf einem Terminal darf die Backend-Oberflaeche nicht erreichbar sein
# (T-103). Geprueft wird die Anmeldemaske: Sie muss auf terminal.php
# weiterleiten statt ein Formular auszuliefern.
if command -v curl >/dev/null 2>&1; then
    BACKEND_URL="$(printf '%s' "$TERMINAL_URL" | sed 's|terminal\.php.*|index.php?seite=login|')"
    BACKEND_STATUS="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$BACKEND_URL" 2>/dev/null)"
    BACKEND_ZIEL="$(curl -s -o /dev/null -w '%{redirect_url}' --max-time 10 "$BACKEND_URL" 2>/dev/null)"

    case "$BACKEND_STATUS" in
        30*)
            if printf '%s' "$BACKEND_ZIEL" | grep -q 'terminal\.php'; then
                melde_ok "Backend-Oberflaeche ist gesperrt (leitet auf terminal.php)"
            else
                melde_fehlt "Backend leitet weiter, aber nicht auf terminal.php" "Ziel: $BACKEND_ZIEL"
            fi
            ;;
        200)
            melde_fehlt "Die Backend-Anmeldung ist auf diesem Geraet erreichbar" \
                "installation_typ in config/config.local.php muss 'terminal' sein. Erst ab P-2026-08-09-19 gesperrt." ;;
        000|"")
            melde_frei "Backend-Sperre nicht pruefbar" "$BACKEND_URL nicht erreichbar." ;;
        *)
            melde_ok "Backend-Oberflaeche liefert kein Anmeldeformular (HTTP $BACKEND_STATUS)" ;;
    esac
fi

# ---------------------------------------------------------------------------
schritt "2  Kopplung und Hauptdatenbank"
# ---------------------------------------------------------------------------
KONFIG="$ZIEL_VERZEICHNIS/config/config.local.php"
GEKOPPELT="nein"

if [ -f "$KONFIG" ]; then
    GEKOPPELT="ja"
    melde_ok "Geraet ist gekoppelt (config.local.php vorhanden)"
else
    melde_fehlt "Geraet ist noch nicht gekoppelt" \
        "Das ist kein Fehler, wenn es gerade erst aufgesetzt wurde: Am Bildschirm erscheint die Einrichtungsseite. Kopplungscode im Backend erzeugen."
fi

if [ "$GEKOPPELT" = "ja" ] && command -v php >/dev/null 2>&1; then
    # Verbindung mit den Zugangsdaten des Geraets - und zwar so, wie die
    # Anwendung es tut. Ausgegeben wird nur, ob es ging.
    DB_ERGEBNIS="$(php -r '
        $d = @include $argv[1];
        if (!is_array($d) || !isset($d["db"])) { echo "KEINE_KONFIG"; exit; }
        $c = $d["db"];
        try {
            $pdo = new PDO(
                "mysql:host=" . ($c["host"] ?? "localhost") . ";dbname=" . ($c["dbname"] ?? "") . ";charset=utf8mb4",
                (string)($c["user"] ?? ""), (string)($c["pass"] ?? ""),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->query("SELECT 1 FROM mitarbeiter LIMIT 1");
            echo "OK";
        } catch (Throwable $e) { echo "FEHLER: " . $e->getMessage(); }
    ' "$KONFIG" 2>/dev/null)"

    case "$DB_ERGEBNIS" in
        OK)           melde_ok "Hauptdatenbank erreichbar, Anmeldung erfolgreich" ;;
        KEINE_KONFIG) melde_fehlt "config.local.php enthaelt keinen db-Block" ;;
        *)            melde_fehlt "Hauptdatenbank nicht erreichbar" "$DB_ERGEBNIS" ;;
    esac

    # Gegenprobe zu T-101: Der Terminal-Benutzer darf keine Hashes lesen. Ein
    # Geraet, das es doch kann, traegt einen Zugang von vor P-2026-08-09-16.
    HASH_ERGEBNIS="$(php -r '
        $d = @include $argv[1];
        if (!is_array($d) || !isset($d["db"])) { echo "UNBEKANNT"; exit; }
        $c = $d["db"];
        try {
            $pdo = new PDO(
                "mysql:host=" . ($c["host"] ?? "localhost") . ";dbname=" . ($c["dbname"] ?? "") . ";charset=utf8mb4",
                (string)($c["user"] ?? ""), (string)($c["pass"] ?? ""),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->query("SELECT passwort_hash FROM mitarbeiter LIMIT 1");
            echo "LESBAR";
        } catch (Throwable $e) { echo "GESPERRT"; }
    ' "$KONFIG" 2>/dev/null)"

    case "$HASH_ERGEBNIS" in
        GESPERRT)  melde_ok "Passwort-Hashes sind fuer dieses Geraet gesperrt" ;;
        LESBAR)    melde_fehlt "Dieses Geraet kann Passwort-Hashes lesen" \
                       "Zugang von vor P-2026-08-09-16. Im Backend entkoppeln und neu koppeln." ;;
        *)         melde_frei "Hash-Sperre nicht pruefbar" "Keine Datenbankverbindung." ;;
    esac
elif [ "$GEKOPPELT" = "ja" ]; then
    melde_frei "Datenbank nicht geprueft" "PHP ist nicht installiert."
fi

# ---------------------------------------------------------------------------
schritt "3  Lokale Ausweichdatenbank (Offline-Queue)"
# ---------------------------------------------------------------------------
GERAETE_DATEI="$ZIEL_VERZEICHNIS/config/geraet.local.php"

if [ ! -f "$GERAETE_DATEI" ]; then
    melde_fehlt "geraet.local.php fehlt" "install_terminal.sh (Stufe 3) legt sie an."
elif ! command -v php >/dev/null 2>&1; then
    melde_frei "Ausweichdatenbank nicht geprueft" "PHP ist nicht installiert."
else
    OFFLINE_ERGEBNIS="$(php -r '
        $d = @include $argv[1];
        $o = is_array($d) ? ($d["offline_db"] ?? null) : null;
        if (!is_array($o) || empty($o["enabled"])) { echo "AUS"; exit; }
        try {
            $pdo = new PDO(
                "mysql:host=" . ($o["host"] ?? "localhost") . ";dbname=" . ($o["dbname"] ?? "") . ";charset=utf8mb4",
                (string)($o["user"] ?? ""), (string)($o["pass"] ?? ""),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->query("SELECT 1 FROM db_injektionsqueue LIMIT 1");
            echo "OK";
        } catch (Throwable $e) { echo "FEHLER: " . $e->getMessage(); }
    ' "$GERAETE_DATEI" 2>/dev/null)"

    case "$OFFLINE_ERGEBNIS" in
        OK)  melde_ok "Ausweichdatenbank erreichbar, Tabelle db_injektionsqueue vorhanden" ;;
        AUS) melde_fehlt "Ausweichdatenbank ist abgeschaltet" \
                 "Bei einem Netzausfall speichert dieses Geraet NICHTS zwischen. Gewollt? Sonst install_terminal.sh erneut laufen lassen." ;;
        *)   melde_fehlt "Ausweichdatenbank nicht erreichbar" "$OFFLINE_ERGEBNIS" ;;
    esac
fi

# ---------------------------------------------------------------------------
schritt "4  Health-Endpunkt des Terminals"
# ---------------------------------------------------------------------------
if command -v curl >/dev/null 2>&1; then
    HEALTH_URL="${TERMINAL_URL}?aktion=health"
    HEALTH="$(curl -s --max-time 10 "$HEALTH_URL" 2>/dev/null)"
    if printf '%s' "$HEALTH" | grep -q '"zeit"'; then
        melde_ok "$HEALTH_URL antwortet"
        # Die Queue-Zahlen sind das Interessante: offene Eintraege beim
        # Aufsetzen bedeuten, dass schon gebucht wurde, ohne dass es ankam.
        QUEUE_OFFEN="$(printf '%s' "$HEALTH" | sed -n 's/.*"queue_offen":\([0-9null]*\).*/\1/p')"
        QUEUE_FEHLER="$(printf '%s' "$HEALTH" | sed -n 's/.*"queue_fehler":\([0-9null]*\).*/\1/p')"
        echo "            Queue: offen=${QUEUE_OFFEN:-?}, fehler=${QUEUE_FEHLER:-?}"
        if [ -n "$QUEUE_FEHLER" ] && [ "$QUEUE_FEHLER" != "0" ] && [ "$QUEUE_FEHLER" != "null" ]; then
            melde_fehlt "Die Offline-Queue enthaelt fehlerhafte Eintraege" \
                "Im Backend unter Verwaltung -> Offline-Queue ansehen."
        fi
    else
        melde_fehlt "Health-Endpunkt antwortet nicht wie erwartet" "Erhalten: $(printf '%s' "$HEALTH" | head -c 120)"
    fi
else
    melde_frei "Health-Endpunkt nicht geprueft" "curl ist nicht installiert."
fi

# ---------------------------------------------------------------------------
schritt "5  Kiosk"
# ---------------------------------------------------------------------------
if [ -d /run/systemd/system ]; then
    if systemctl is-enabled zeiterfassung-kiosk.service >/dev/null 2>&1; then
        melde_ok "Kiosk startet automatisch"
        if systemctl is-active zeiterfassung-kiosk.service >/dev/null 2>&1; then
            melde_ok "Kiosk laeuft gerade"
        else
            melde_fehlt "Kiosk laeuft nicht" "journalctl -u zeiterfassung-kiosk -n 50"
        fi
    else
        melde_fehlt "Kiosk ist nicht eingerichtet" "install_kiosk.sh (Stufe 4) laufen lassen."
    fi
else
    melde_frei "Kiosk nicht geprueft" "systemd laeuft hier nicht (Container?)."
fi

# ---------------------------------------------------------------------------
schritt "6  RFID"
# ---------------------------------------------------------------------------
case "$RFID_VARIANTE" in
    keine)
        melde_frei "Kein RFID an diesem Geraet" "Anmeldung ueber Personalnummer."
        ;;
    bridge)
        if [ -d /run/systemd/system ]; then
            if systemctl is-active rfid-ws.service >/dev/null 2>&1; then
                melde_ok "RFID-Bridge laeuft"
            else
                melde_fehlt "RFID-Bridge laeuft nicht" "journalctl -u rfid-ws -n 50"
            fi
        else
            melde_frei "RFID-Bridge nicht geprueft" "systemd laeuft hier nicht."
        fi

        # Port pruefen - ohne ss/netstat notfalls mit Bordmitteln.
        WS_PORT="$(printf '%s' "$RFID_WS_URL" | sed -n 's|.*:\([0-9]\{2,5\}\).*|\1|p')"
        WS_PORT="${WS_PORT:-8765}"
        if command -v ss >/dev/null 2>&1; then
            if ss -lnt 2>/dev/null | grep -q ":$WS_PORT "; then
                melde_ok "Port $WS_PORT wird bedient"
            else
                melde_fehlt "Auf Port $WS_PORT lauscht nichts"
            fi
        elif (exec 3<>"/dev/tcp/127.0.0.1/$WS_PORT") 2>/dev/null; then
            melde_ok "Port $WS_PORT ist erreichbar"
            exec 3<&- 2>/dev/null
        else
            melde_fehlt "Port $WS_PORT ist nicht erreichbar"
        fi
        ;;
    *)
        melde_frei "USB-Leser (Keyboard-Wedge)" \
            "Ob einer angeschlossen ist, laesst sich nicht abfragen - er meldet sich als Tastatur. Das zeigt nur der Scan-Test."
        ;;
esac

# ---------------------------------------------------------------------------
schritt "7  Scan-Test"
# ---------------------------------------------------------------------------
# Der einzige Teil, der einen Menschen braucht - und der wichtigste. Ein
# falsches Tastaturlayout faellt sonst NIRGENDS auf: Das Terminal bucht
# klaglos einen Code, der anders lautet als der auf dem Etikett.
if [ "$OHNE_SCAN" = "ja" ]; then
    melde_frei "Scan-Test uebersprungen" "--ohne-scan wurde angegeben."
elif [ ! -t 0 ]; then
    melde_frei "Scan-Test uebersprungen" "Kein Bediener da (keine Eingabe moeglich)."
else
    echo
    echo "  Jetzt bitte einmal scannen. Danach Eingabetaste."
    echo "  Zum Ueberspringen einfach Eingabetaste druecken."
    echo

    printf '  RFID-Chip an den Leser halten: '
    read -r GESCANNT_RFID
    if [ -z "$GESCANNT_RFID" ]; then
        melde_frei "RFID-Scan uebersprungen"
    else
        echo "            Angekommen: '$GESCANNT_RFID' (${#GESCANNT_RFID} Zeichen)"
        if printf '%s' "$GESCANNT_RFID" | grep -qE '^[0-9A-Za-z:._-]+$'; then
            melde_ok "RFID-Kennung sieht brauchbar aus"
        else
            melde_fehlt "RFID-Kennung enthaelt unerwartete Zeichen" \
                "Sehr wahrscheinlich das Tastaturlayout. Pruefen: localectl status"
        fi
    fi

    echo
    printf '  Barcode-Etikett scannen: '
    read -r GESCANNT_CODE
    if [ -z "$GESCANNT_CODE" ]; then
        melde_frei "Barcode-Scan uebersprungen"
    else
        echo "            Angekommen: '$GESCANNT_CODE' (${#GESCANNT_CODE} Zeichen)"
        echo
        echo "  WICHTIG: Steht dort genau das, was auf dem Etikett steht?"
        echo "  Achten Sie besonders auf y/z und auf Binde- und Unterstriche -"
        echo "  bei falschem Tastaturlayout werden genau die vertauscht."
        printf '  Stimmt es? [j/N]: '
        read -r ANTWORT
        case "$ANTWORT" in
            [jJ]*) melde_ok "Barcode kommt richtig an" ;;
            *)     melde_fehlt "Barcode kommt falsch an" \
                       "Tastaturlayout pruefen: localectl status. In der Antwortdatei steht TASTATURLAYOUT; danach install_terminal.sh erneut laufen lassen." ;;
        esac
    fi
fi

# ---------------------------------------------------------------------------
schritt "ERGEBNIS"
# ---------------------------------------------------------------------------
echo "  OK:            $OK"
echo "  FEHLT:         $FEHLT"
echo "  nicht geprueft: $UEBERSPRUNGEN"
echo

if [ "$FEHLT" -eq 0 ]; then
    echo "  Das Geraet ist einsatzbereit."
    if [ "$UEBERSPRUNGEN" -gt 0 ]; then
        echo "  ($UEBERSPRUNGEN Punkt(e) wurden nicht geprueft - siehe oben.)"
    fi
else
    echo "  $FEHLT Punkt(e) fehlen. Das Geraet ist NICHT einsatzbereit."
fi

echo
echo "  Protokoll: $LOGDATEI"
echo

[ "$FEHLT" -eq 0 ] && exit 0
exit 1
