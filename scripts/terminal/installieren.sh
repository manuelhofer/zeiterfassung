#!/usr/bin/env bash
# Einziger Einstieg fuer ein frisch installiertes, dediziertes Hallenterminal.
# Herunterladen, dann sudo bash installieren.sh (nicht als Pipe ausfuehren).
set -Eeuo pipefail

anschluss() {
    MODELL=''
    if [ -r /proc/device-tree/model ]; then
        MODELL="$(tr -d '\0' </proc/device-tree/model)"
    fi
    PI_STANDARD=nein
    case "$MODELL" in
        'Raspberry Pi 1 Model A+'*|'Raspberry Pi 1 Model B+'*|\
        'Raspberry Pi Model A Plus '*|'Raspberry Pi Model B Plus '*|\
        'Raspberry Pi 2 Model '*|'Raspberry Pi 3 Model '*|'Raspberry Pi 4 Model '*|\
        'Raspberry Pi 5 Model '*|'Raspberry Pi Zero '*|'Raspberry Pi Zero W '*|\
        'Raspberry Pi Zero 2 '*|'Raspberry Pi 400 '*) PI_STANDARD=ja ;;
    esac
    echo "RC522-Anschluss - erkannt: ${MODELL:-kein bekanntes Raspberry-Pi-Pinprofil}"
    echo 'Vor dem Verkabeln herunterfahren, Netzteil abziehen! Nur 3,3 V, niemals 5 V.'
    echo 'Auf die Beschriftung am RC522 achten, nicht auf die Reihenfolge der Stifte.'
    if [ "$PI_STANDARD" = ja ]; then
        cat <<'PINS'
RC522-Pin       Raspberry Pi: physischer Pin     Signal
3.3V / VCC      1                               3,3 V
GND             6                               Masse
SDA / SS        24                              GPIO8 / SPI0 CE0
SCK             23                              GPIO11 / SPI0 SCLK
MOSI            19                              GPIO10 / SPI0 MOSI
MISO            21                              GPIO9 / SPI0 MISO
RST             17                              3,3 V (Reset erfolgt per SPI)
IRQ             nicht anschliessen

Das sind Steckleisten-Pinnummern, keine GPIO-Nummern! Pin 1 anhand der
Platinenbeschriftung bestimmen. SPI-Anschluss: /dev/spidev0.0.
SDA bezeichnet hier SPI-Chip-Select, nicht den I2C-SDA-Anschluss.
PINS
    else
        cat <<'PINS'
RC522 -> vorhandener 3,3-V-SPI-Anschluss laut Hersteller-Pinplan:
SDA/SS -> CS, SCK -> SCLK, MOSI -> MOSI, MISO -> MISO,
VCC und RST -> 3,3 V, GND -> Masse, IRQ bleibt frei.
Fuer diese Platine sind keine physischen Pinnummern hinterlegt.
Ohne Hersteller-Pinplan nicht verkabeln. Ein PC ohne SPI braucht einen
geeigneten Adapter; ein gewoehnliches USB-Kabel oder USB-UART reicht nicht.
Alternativ: Mikrocontroller liest den RC522 und sendet UID-Zeilen seriell.
PINS
    fi
}

hilfe() {
    cat <<'TEXT'
Zeiterfassung - Terminal installieren
  sudo bash installieren.sh             Hardware einmal auswaehlen
  sudo bash installieren.sh --standard  USB-Tastaturleser, Deutsch, keine Drehung
  sudo bash installieren.sh --hardware  Hardwareauswahl vor der Kopplung korrigieren
  bash installieren.sh --anschluss      RC522-Verkabelung vorab anzeigen

Nur auf einem dedizierten Terminal ausfuehren: richtet Webserver, Datenbank,
Vollbildbrowser und Autostart ein und ersetzt die grafische Anmeldung.
Internet und ein Linux-System mit systemd sind bei der Installation erforderlich.
Vorhandene konfigurierte Installationen werden nicht angefasst.
Nach einem Fehler denselben Aufruf wiederholen; die Hardwareauswahl bleibt erhalten.
RC522 direkt: Linux-SPI erforderlich. --anschluss zeigt Belegung und Grenzen.
TEXT
}
STANDARD=nein
HARDWARE_NEU=nein
case "${1:-}" in
    --help|-h) hilfe; exit 0 ;;
    --anschluss) anschluss; exit 0 ;;
    --standard) STANDARD=ja ;;
    --hardware) HARDWARE_NEU=ja ;;
    '') ;;
    *) hilfe; exit 1 ;;
esac
[ "$#" -le 1 ] || { hilfe; exit 1; }
fehler() { printf '\nFEHLER: %s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fehler "Bitte mit sudo bash $0 starten."
[ -d /run/systemd/system ] || fehler 'Dieser Installer braucht ein laufendes Linux mit systemd.'

ZIEL_VERZEICHNIS=/opt/zeiterfassung
ZUSTAND=/var/lib/zeiterfassung-terminal-installation
GIT_REPO=https://github.com/manuelhofer/zeiterfassung.git
RAW_URL=https://raw.githubusercontent.com/manuelhofer/zeiterfassung/main

# Nie ein fremdes Projekt, ein Backend oder ein bereits gekoppeltes Terminal
# als vermeintlich unvollstaendige Erstinstallation behandeln.
[ ! -L "$ZIEL_VERZEICHNIS" ] || fehler 'Das Zielverzeichnis ist ein symbolischer Link.'
[ ! -e "$ZIEL_VERZEICHNIS/config/config.local.php" ] &&
    [ ! -L "$ZIEL_VERZEICHNIS/config/config.local.php" ] ||
    fehler 'Dieses Geraet ist bereits eingerichtet. Updates im Backend starten.'
if [ -e "$ZIEL_VERZEICHNIS" ] && [ ! -f "$ZUSTAND/code-bereit" ]; then
    fehler "$ZIEL_VERZEICHNIS ist schon belegt. Es wird nichts ueberschrieben."
fi
[ ! -L "$ZUSTAND" ] || fehler 'Das Installationsverzeichnis ist ein symbolischer Link.'
if [ -e "$ZUSTAND" ]; then
    [ "$(stat -c '%u:%a' "$ZUSTAND")" = '0:700' ] ||
        fehler "Unsichere Rechte an $ZUSTAND; erwartet root mit Modus 700."
else
    install -d -m 700 "$ZUSTAND"
fi
command -v flock >/dev/null || fehler 'flock fehlt (Paket util-linux).'
exec 9>"$ZUSTAND/sperre"
flock -n 9 || fehler 'Eine Terminal-Installation laeuft bereits.'
umask 077
ARBEIT=''
trap '[ -z "$ARBEIT" ] || rm -rf -- "$ARBEIT"' EXIT
trap 'echo "Installation abgebrochen. Nach Beheben der Ursache denselben Befehl erneut starten." >&2; exit 1' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

frage() {
    local name="$1" text="$2" eingabe
    printf '%s [%s]: ' "$text" "${!name}"
    read -r eingabe || fehler 'Eingabe abgebrochen. Fuer die Standardhardware gibt es --standard.'
    [ -z "$eingabe" ] || printf -v "$name" '%s' "$eingabe"
    return 0
}
ANTWORTDATEI="$ZUSTAND/terminal.conf"
if [ -f "$ANTWORTDATEI" ] && [ "$HARDWARE_NEU" != ja ]; then
    # Ausschliesslich unsere eigene Datei im root-exklusiven Zustandsordner.
    . "$ANTWORTDATEI"
    echo 'Gespeicherte Hardwareauswahl wird weiterverwendet.'
else
    RFID_VARIANTE=usb
    RFID_GERAET=/dev/ttyUSB0
    RFID_BAUD=9600
    TASTATURLAYOUT=de
    BILDSCHIRM_DREHUNG=normal
    KIOSK_ANZEIGE=auto
    SPI_AKTIVIEREN=nein
    hilfe
    if [ "$STANDARD" != ja ]; then
        [ -t 0 ] || fehler 'Bitte interaktiv starten oder --standard angeben.'
        AUSWAHL=1
        echo 'RFID: 1 = USB (wie Tastatur), 2 = serieller Leser, 3 = kein Leser, 4 = RC522/SPI'
        frage AUSWAHL 'Lesertyp'
        case "$AUSWAHL" in
            1) RFID_VARIANTE=usb ;;
            2) RFID_VARIANTE=bridge
               frage RFID_GERAET 'Serieller Anschluss (z. B. /dev/ttyUSB0)'
               frage RFID_BAUD 'Baudrate laut Leser-Handbuch' ;;
            3) RFID_VARIANTE=keine ;;
            4) RFID_VARIANTE=rc522
               RFID_GERAET=/dev/spidev0.0
               anschluss
               if [ "$PI_STANDARD" = ja ]; then
                   SPI_AKTIVIEREN=ja
               else
                   frage RFID_GERAET 'Bereits eingerichteter Linux-SPI-Anschluss'
                   [ -c "$RFID_GERAET" ] || fehler 'Kein nutzbarer SPI-Anschluss. Hersteller-Pinprofil/Adapter wird benoetigt; noch keine Systeminstallation ausgefuehrt.'
               fi
               VERKABELT=nein
               frage VERKABELT 'Bereits im stromlosen Zustand exakt so angeschlossen? (ja/nein)'
               [ "$VERKABELT" = ja ] || fehler 'Zuerst herunterfahren und stromlos verkabeln. Danach den Installer erneut starten.' ;;
            *) fehler 'Bitte Lesertyp 1, 2, 3 oder 4 waehlen und erneut starten.' ;;
        esac
        frage TASTATURLAYOUT 'Tastaturlayout des Scanners (de oder us)'
        frage BILDSCHIRM_DREHUNG 'Bildschirmdrehung (normal, links, rechts, kopf)'
    fi
    [[ "$RFID_GERAET" =~ ^/dev/[a-zA-Z0-9_./:-]+$ ]] || fehler 'Ungueltiger serieller Anschluss.'
    if [ "$RFID_VARIANTE" = rc522 ]; then
        [[ "$RFID_GERAET" =~ ^/dev/spidev[0-9]+\.[0-9]+$ ]] || fehler 'Ungueltiger SPI-Anschluss.'
        anschluss > "$ZUSTAND/rc522-anschluss.txt"
    fi
    [[ "$RFID_BAUD" =~ ^[1-9][0-9]{2,6}$ ]] || fehler 'Ungueltige Baudrate.'
    case "$TASTATURLAYOUT" in de|us) ;; *) fehler 'Tastaturlayout muss de oder us sein.' ;; esac
    case "$BILDSCHIRM_DREHUNG" in
        normal) ;;
        links|rechts|kopf) KIOSK_ANZEIGE=x11 ;;
        *) fehler 'Unbekannte Bildschirmdrehung.' ;;
    esac
    # printf %q verhindert, dass Eingaben beim spaeteren Einlesen Shellcode sind.
    for name in ZIEL_VERZEICHNIS GIT_REPO RFID_VARIANTE RFID_GERAET RFID_BAUD \
                TASTATURLAYOUT BILDSCHIRM_DREHUNG KIOSK_ANZEIGE SPI_AKTIVIEREN; do
        printf '%s=%q\n' "$name" "${!name}"
    done > "$ANTWORTDATEI.neu"
    mv "$ANTWORTDATEI.neu" "$ANTWORTDATEI"
fi

# Systemdateien und Dienste brauchen die normalen Leserechte; die Antworten
# bleiben durch ihren root-exklusiven Elternordner geschuetzt.
umask 022

laden() {
    if command -v curl >/dev/null; then
        curl --fail --location --proto '=https' --proto-redir '=https' \
            --connect-timeout 20 --max-time 180 --retry 2 "$1" -o "$2"
    elif command -v wget >/dev/null; then
        wget --https-only --timeout=30 --tries=3 -O "$2" "$1"
    else
        fehler 'Zum Herunterladen fehlt curl oder wget. Bitte eines davon installieren.'
    fi
}

if [ ! -e "$ZIEL_VERZEICHNIS" ]; then
    ARBEIT="$(mktemp -d "$ZUSTAND/download.XXXXXX")"
    # Gemeinsame Paketlogik auch beim Bootstrap benutzen, statt eine zweite
    # Zuordnung der Distributionen/Paketmanager zu pflegen.
    laden "$RAW_URL/scripts/terminal/_paketfamilie.sh" "$ARBEIT/pakete.sh"
    . "$ARBEIT/pakete.sh"
    erkenne_paketfamilie || fehler 'Diese Linux-Distribution wird nicht automatisch unterstuetzt.'
    if ! command -v git >/dev/null; then
        paketquellen_auffrischen
        paket_installieren git ca-certificates
    fi
    echo '1/5 - Programmdateien von GitHub (main) holen'
    GIT_TERMINAL_PROMPT=0 git clone --depth 1 --branch main "$GIT_REPO" "$ARBEIT/code"
    for skript in install_terminal.sh install_kiosk.sh install_peripherie.sh selbsttest.sh; do
        [ -f "$ARBEIT/code/scripts/terminal/$skript" ] || fehler "Download unvollstaendig: $skript fehlt."
        bash -n "$ARBEIT/code/scripts/terminal/$skript"
    done
    # Auch nach einem Ausfall unmittelbar vor/nach mv bleibt Wiederanlauf moeglich.
    touch "$ZUSTAND/code-bereit"
    mv -T "$ARBEIT/code" "$ZIEL_VERZEICHNIS"
    chmod 755 "$ZIEL_VERZEICHNIS"
else
    echo '1/5 - Vorhandenen Stand der begonnenen Erstinstallation weiterverwenden'
fi

STUFEN="$ZIEL_VERZEICHNIS/scripts/terminal"
for skript in install_terminal.sh install_kiosk.sh install_peripherie.sh selbsttest.sh; do
    [ -f "$STUFEN/$skript" ] || fehler "Unvollstaendige Erstinstallation: $skript fehlt."
done
stufe() {
    echo
    echo "$1"
    local status=0
    bash "$STUFEN/$2" "$ANTWORTDATEI" </dev/null || status=$?
    if [ "$status" -eq 20 ]; then
        # Vor der fertigen Leserpruefung noch keine Kopplungsseite anbieten.
        systemctl disable --now zeiterfassung-kiosk.service || fehler 'Kiosk konnte fuer den Zwischenstart nicht angehalten werden.'
        echo 'SPI ist vorbereitet, ein Neustart ist erforderlich.'
        echo 'Jetzt: sudo reboot'
        echo 'Danach denselben Installer erneut starten; die Auswahl bleibt gespeichert.'
        exit 20
    fi
    if [ "$status" -ne 0 ]; then
        fehler "$1 fehlgeschlagen. Details oben und in /var/log/zeiterfassung-terminal-setup.log. Danach denselben Installer erneut starten."
    fi
}
stufe '2/5 - Grundsystem und lokale Offline-Datenbank' install_terminal.sh
stufe '3/5 - Vollbildbrowser und Autostart' install_kiosk.sh
stufe '4/5 - RFID und Bildschirm' install_peripherie.sh

echo '5/5 - Installation vor der Kopplung pruefen'
bash "$STUFEN/selbsttest.sh" "$ANTWORTDATEI" --ohne-scan --vor-kopplung </dev/null ||
    fehler 'Die technische Pruefung ist fehlgeschlagen. Der Kiosk wird noch nicht gestartet; Details siehe Protokoll.'
echo
echo 'Installation vorbereitet. Jetzt startet die Einrichtungsseite.'
echo 'Im Backend unter Verwaltung -> Terminals einen Kopplungscode erzeugen.'
echo 'Am Terminal Server-Adresse und Code eingeben. Danach einen echten Scan testen.'
echo "Vollstaendiger Selbsttest: sudo bash $STUFEN/selbsttest.sh $ANTWORTDATEI"
systemctl start zeiterfassung-kiosk.service || fehler 'Vollbildbrowser konnte nicht gestartet werden.'
systemctl is-active --quiet zeiterfassung-kiosk.service || fehler 'Vollbildbrowser laeuft nicht. Bitte das Dienstprotokoll pruefen.'
echo 'Kiosk-Dienst gestartet. Bildschirm, Touch und Leser jetzt am Geraet pruefen.'
