#!/usr/bin/env bash
# Nur im wegwerfbaren Container: echte Benutzergruppen, kein SPI-/GPIO-Zugriff.
set -euo pipefail
[ -f /.dockerenv ] && [ "$(id -u)" -eq 0 ] || exit 1
TEST="$(mktemp -d /tmp/rc522-rechte-XXXXXX)"
trap 'rm -rf "$TEST"' EXIT
chmod 0755 "$TEST"
groupadd --system spi
useradd --system --user-group --no-create-home --groups dialout rfidws
# Null-Geraet statt echter Hardware; dieselben Linux-Dateirechte wie spidev.
mknod "$TEST/leser" c 1 3
chown root:spi "$TEST/leser"
chmod 0660 "$TEST/leser"
if runuser -u rfidws -- bash -c 'exec 3<>"$1"' _ "$TEST/leser" 2>/dev/null; then exit 1; fi
echo 'OK: fehlender SPI-Gruppenzugriff reproduziert'

# Originale Gruppenvergabe aus dem Installer ausfuehren, keine eigene Nachbildung.
sed -n '/# Raspberry Pi OS setzt/,/mkdir -p \/etc\/udev\/rules.d/p' \
    "$(dirname "$0")/../terminal/install_peripherie.sh" | sed '$d' > "$TEST/gruppen.sh"
[ -s "$TEST/gruppen.sh" ]
for lauf in 1 2; do
    RFID_WS_BENUTZER=rfidws bash "$TEST/gruppen.sh"
    runuser -u rfidws -- bash -c 'exec 3<>"$1"' _ "$TEST/leser"
    id -nG rfidws | tr ' ' '\n' | grep -qx dialout
done
echo 'OK: Installer erlaubt SPI-Lesen/Schreiben und erhaelt vorhandene Gruppen bei Wiederholung'

groupdel spi
chown root:zeiterfassung-spi "$TEST/leser"
RFID_WS_BENUTZER=rfidws bash "$TEST/gruppen.sh"
runuser -u rfidws -- bash -c 'exec 3<>"$1"' _ "$TEST/leser"
echo 'OK: System ohne SPI-Gruppe verwendet weiterhin die eigene Anschlussgruppe'
