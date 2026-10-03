#!/usr/bin/env bash
# Ausschliesslich im wegwerfbaren Container, niemals auf dem Entwicklungsrechner.
set -euo pipefail
[ -f /.dockerenv ] && [ "$(id -u)" -eq 0 ] || exit 1
source "$(dirname "$0")/../backend_installationspfad.sh"
TEST="$(mktemp -d /tmp/backend-pfad-XXXXXX)"
chmod 0755 "$TEST"
mkdir -p "$TEST/normal" /root/backend-pfad-test/config
chmod 0700 /root
APP="$TEST/normal"
backend_installationspfad "$TEST/ziel"
[ "$APP" = "$TEST/normal" ] && [ ! -e "$TEST/ziel" ]
echo 'OK: erreichbarer Pfad bleibt erhalten'
printf 'synthetische Konfiguration\n' > /root/backend-pfad-test/config/config.local.php
chmod 0640 /root/backend-pfad-test/config/config.local.php
APP=/root/backend-pfad-test
if runuser -u www-data -- test -x "$(dirname "$APP")"; then exit 1; fi
backend_installationspfad "$TEST/ziel"
[ "$APP" = "$TEST/ziel" ] && [ ! -e /root/backend-pfad-test ]
[ "$(cat "$APP/config/config.local.php")" = 'synthetische Konfiguration' ]
[ "$(stat -c %a "$APP/config/config.local.php")" = 640 ]
echo 'OK: gesperrter Elternpfad reproduziert, Umzug erhaelt Konfiguration und Rechte'
backend_installationspfad "$TEST/zweites-ziel"
[ ! -e "$TEST/zweites-ziel" ]
echo 'OK: Wiederanlauf verschiebt nicht erneut'
mkdir /root/backend-pfad-konflikt
APP=/root/backend-pfad-konflikt
if backend_installationspfad "$TEST/ziel"; then exit 1; fi
[ -d "$APP" ] && [ -f "$TEST/ziel/config/config.local.php" ]
echo 'OK: belegtes Ziel bleibt unveraendert'
