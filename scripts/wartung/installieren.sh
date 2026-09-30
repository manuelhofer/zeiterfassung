#!/usr/bin/env bash
# Interner Teil der normalen Installation, ohne Fragen oder Wartungsparameter.
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo 'Die Systeminstallation benötigt Administratorrechte.' >&2; exit 1; }
APP="$(cd "$(dirname "$0")/../.." && pwd)"
WEB="${1:-www-data}"
shift || true
[[ "$APP" =~ ^/[A-Za-z0-9_./-]+$ ]] || { echo 'Ungeeigneter Installationspfad.' >&2; exit 1; }
[[ "$WEB" =~ ^[a-zA-Z0-9_-]+$ ]] || exit 1
php -r 'foreach (["pdo_mysql","zip","openssl"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR,"PHP-Erweiterung fehlt: $e\n"); exit(1); } }'
for PROGRAMM in mariadb mariadb-dump git tar runuser systemctl; do command -v "$PROGRAMM" >/dev/null; done
# Root führt nur geschützten Anwendungscode aus. Webschreibbar bleiben
# Kopplungsdaten und Uploads; deren PHP-Konfiguration wird als Webuser gelesen.
find "$APP" -type d -exec chown "root:$WEB" {} + -exec chmod 0750 {} +
find "$APP" -type f -exec chown "root:$WEB" {} + -exec chmod g-w,o-rwx {} +
chmod 2770 "$APP/config"
find "$APP/public/uploads" -type d -exec chmod 2770 {} +
find "$APP/public/uploads" -type f -exec chmod 0660 {} +
php "$APP/scripts/wartung_einrichten.php" "$WEB" "$@"
STATUS="$(php -r 'require $argv[1]."/core/Autoloader.php"; echo WartungSystem::pfad($argv[1]);' "$APP")"
chgrp -R "$WEB" "$STATUS"
chmod 2750 "$STATUS"
chmod 2770 "$STATUS/auftraege"
KENNUNG="$(printf '%s' "$APP" | sha256sum | cut -c1-16)"
DIENST="zeiterfassung-wartung-$KENNUNG"
cat > "/etc/systemd/system/$DIENST.service" <<EOF
[Unit]
Description=Zeiterfassung Backup und Updates
After=network-online.target mariadb.service

[Service]
Type=oneshot
User=root
Group=$WEB
WorkingDirectory=$APP
ExecStart=/usr/bin/php $APP/scripts/wartung.php dienst
TimeoutStartSec=infinity
UMask=0027
EOF
cat > "/etc/systemd/system/$DIENST.timer" <<EOF
[Unit]
Description=Zeiterfassung Wartungsdienst automatisch ausführen
[Timer]
OnBootSec=5s
OnUnitInactiveSec=2s
Unit=$DIENST.service
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now "$DIENST.timer"
systemctl start "$DIENST.service"
echo 'Backup und Updates gehören jetzt zur Installation. Keine zusätzliche Einrichtung erforderlich.'
