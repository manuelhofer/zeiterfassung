#!/usr/bin/env bash
# Normale Backendinstallation auf Debian/Raspberry Pi OS, einschließlich Wartung.
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo 'Aufruf: sudo bash scripts/installieren.sh'; exit 1; }
APP="$(cd "$(dirname "$0")/.." && pwd -P)"
[[ "$APP" =~ ^/[A-Za-z0-9_./-]+$ ]] || { echo 'Ungeeigneter Installationspfad.' >&2; exit 1; }
command -v apt-get >/dev/null || { echo 'Dieser Backendinstaller unterstützt Debian und Raspberry Pi OS.' >&2; exit 1; }
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 php-fpm php-cli php-mysql php-mbstring php-gd php-xml php-zip php-curl mariadb-server git curl ca-certificates
source "$APP/scripts/backend_installationspfad.sh"
backend_installationspfad
systemctl enable --now mariadb
# Die normale Terminal-Kopplung verwendet individuelle DB-Zugaenge ueber das LAN.
cat > /etc/mysql/mariadb.conf.d/60-zeiterfassung.cnf <<'EOF'
[mariadbd]
bind-address=0.0.0.0
EOF
systemctl restart mariadb
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
FPM="php$PHP_VERSION-fpm"
systemctl enable --now "$FPM"
php "$APP/scripts/backend_einrichten.php"
cat > /etc/apache2/sites-available/zeiterfassung.conf <<EOF
<VirtualHost *:80>
    DocumentRoot "$APP/public"
    <Directory "$APP/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
    <FilesMatch "\.php\$">
        SetHandler "proxy:unix:/run/php/php$PHP_VERSION-fpm.sock|fcgi://localhost/"
    </FilesMatch>
</VirtualHost>
EOF
a2enmod proxy proxy_fcgi setenvif
a2ensite zeiterfassung
a2dissite 000-default
bash "$APP/scripts/wartung/installieren.sh" www-data apache2 "$FPM"
apache2ctl configtest
systemctl restart apache2 "$FPM"
echo 'Installation abgeschlossen. Im Browser öffnen und die normale Erstanmeldung durchführen.'
