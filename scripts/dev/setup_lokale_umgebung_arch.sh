#!/usr/bin/env bash
#
# ===========================================================================
#  Lokale Entwicklungsumgebung fuer das Projekt "Zeiterfassung"
#  Zielsystem: Arch Linux / CachyOS  (Apache 2.4 + php-fpm + MariaDB + phpMyAdmin)
# ===========================================================================
#
#  Aufruf:
#      sudo bash scripts/dev/setup_lokale_umgebung_arch.sh
#
#  Ergebnis:
#      App        -> http://localhost/zeiterfassung
#      Terminal   -> http://localhost/zeiterfassung/terminal.php
#      phpMyAdmin -> http://localhost/phpmyadmin
#
#  Das Skript ist idempotent, mehrfaches Ausfuehren ist unschaedlich.
#
#  WICHTIG - das hier ist eine ENTWICKLUNGS-Umgebung:
#      - Der DB-Benutzer bekommt bewusst weitreichende Rechte (Import/Export
#        in phpMyAdmin soll ohne Reibung funktionieren).
#      - `ProtectHome` wird fuer httpd/php-fpm abgeschaltet, weil der Code in
#        der Git-Arbeitskopie unter /home liegt.
#      Fuer die Produktivinstallation gilt stattdessen
#      `docs/installationsanleitung.md` (Debian/Apache, eingeschraenkte Rechte).
#
#  Hintergrund und Erlaeuterungen: docs/lokale_entwicklungsumgebung.md
# ===========================================================================

set -uo pipefail

# Projektwurzel aus dem Skriptpfad ableiten (scripts/dev/ -> zwei Ebenen hoch),
# damit das Skript unabhaengig vom Klonpfad funktioniert.
SKRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJEKT="$(cd "$SKRIPTDIR/../.." && pwd)"

WEBUSER="http"          # Apache/php-fpm laufen unter Arch als Benutzer 'http'
DB_NAME="zeiterfassung"
DB_OFFLINE="zeiterfassung_offline"
DB_USER="zeiterfassung"
DB_PASS="zeiterfassung"

echo "=============================================================="
echo "  Zeiterfassung - lokale Entwicklungsumgebung"
echo "  Projekt: $PROJEKT"
echo "  Start:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================="

if [ "$(id -u)" -ne 0 ]; then
    echo "FEHLER: Bitte mit sudo starten:  sudo bash $0"
    exit 1
fi

if [ ! -f "$PROJEKT/sql/01_initial_schema.sql" ]; then
    echo "FEHLER: $PROJEKT sieht nicht nach dem Projekt aus (Schema fehlt)."
    exit 1
fi

schritt() { echo; echo "---- $* ----"; }

# --------------------------------------------------------------------------
schritt "1/9  Pakete installieren"
# --------------------------------------------------------------------------
# mariadb-lts = 11.8 und damit die Version, die auch Debian 13 / Raspberry Pi OS
# ausliefert. Lokal die gleiche DB-Version zu fahren erspart Ueberraschungen.
#
# Stolperstein: Ein bereits installiertes `mariadb-libs` (Nicht-LTS) kollidiert
# mit `mariadb-lts-libs`. Ist es ein Orphan, wird es vorher entfernt.
if pacman -Qq mariadb-libs >/dev/null 2>&1 && ! pacman -Qq mariadb-lts-libs >/dev/null 2>&1; then
    BENOETIGT_VON="$(pacman -Qi mariadb-libs | sed -n 's/^Required By *: //p;s/^Benötigt von *: //p')"
    echo "mariadb-libs ist installiert, benoetigt von: ${BENOETIGT_VON:-unbekannt}"

    case "$BENOETIGT_VON" in
        "None"|"Nichts"|"")
            echo "-> Orphan, wird entfernt um Platz fuer mariadb-lts-libs zu machen."
            pacman -Rdd --noconfirm mariadb-libs
            ;;
        *)
            echo "FEHLER: mariadb-libs wird von anderen Paketen gebraucht."
            echo "        Dann statt 'mariadb-lts' das Paket 'mariadb' verwenden."
            exit 1
            ;;
    esac
fi

pacman -S --needed --noconfirm apache php php-fpm php-gd mariadb-lts phpmyadmin || {
    echo "FEHLER: Paketinstallation fehlgeschlagen."
    exit 1
}

pacman -Q apache php php-fpm php-gd mariadb-lts phpmyadmin

# --------------------------------------------------------------------------
schritt "2/9  PHP-Erweiterungen aktivieren"
# --------------------------------------------------------------------------
# Arch liefert die Erweiterungen mit, aktiviert sie in der php.ini aber nicht.
# Statt in der php.ini herumzuschneiden legen wir eine eigene Datei in conf.d ab.
MODULDIR="$(php -r 'echo ini_get("extension_dir");' 2>/dev/null)"
INI="/etc/php/conf.d/99-zeiterfassung-dev.ini"

{
    echo "; Erzeugt von scripts/dev/setup_lokale_umgebung_arch.sh"
    echo "; Lokale Entwicklungsumgebung - siehe docs/lokale_entwicklungsumgebung.md"
    echo "date.timezone = Europe/Berlin"
} > "$INI"

# Benoetigt: pdo_mysql (App), mysqli (phpMyAdmin), gd (QR-/Barcode-Bilder).
# mbstring/iconv sind bei Arch fest einkompiliert - deshalb wird nur
# eingetragen, was auch wirklich als Modul-Datei vorliegt.
for ext in pdo_mysql mysqli mbstring gd iconv bz2 zip; do
    if [ -f "$MODULDIR/$ext.so" ]; then
        if grep -Eq "^[[:space:]]*extension[[:space:]]*=[[:space:]]*$ext" /etc/php/php.ini; then
            echo "  $ext : bereits in php.ini aktiv"
        else
            echo "extension=$ext" >> "$INI"
            echo "  $ext : aktiviert"
        fi
    else
        echo "  $ext : fest einkompiliert oder nicht vorhanden (uebersprungen)"
    fi
done

# --------------------------------------------------------------------------
schritt "3/9  Zugriff auf das Projekt im Home-Verzeichnis erlauben"
# --------------------------------------------------------------------------
# httpd.service und php-fpm.service laufen unter Arch mit ProtectHome=on:
# /home ist fuer die Dienste dann unsichtbar, unabhaengig von Dateirechten
# (Symptom: HTTP 403 "search permissions are missing on a component").
# Da die Git-Arbeitskopie im Home liegt, wird das lokal abgeschaltet.
for dienst in php-fpm httpd; do
    mkdir -p "/etc/systemd/system/$dienst.service.d"
    cat > "/etc/systemd/system/$dienst.service.d/zeiterfassung-dev.conf" <<EOF
# Lokale Entwicklungsumgebung Zeiterfassung:
# Der Projektcode liegt unter $PROJEKT, deshalb darf $dienst /home sehen.
[Service]
ProtectHome=false
ReadWritePaths=$PROJEKT/public/uploads
EOF
    echo "Drop-in: /etc/systemd/system/$dienst.service.d/zeiterfassung-dev.conf"
done
systemctl daemon-reload

# --------------------------------------------------------------------------
schritt "4/9  Apache konfigurieren"
# --------------------------------------------------------------------------
HTTPD_CONF="/etc/httpd/conf/httpd.conf"
cp -n "$HTTPD_CONF" "$HTTPD_CONF.orig-zeiterfassung" 2>/dev/null && \
    echo "Backup angelegt: $HTTPD_CONF.orig-zeiterfassung"

for modul in proxy_module proxy_fcgi_module rewrite_module; do
    if grep -Eq "^LoadModule ${modul} " "$HTTPD_CONF"; then
        echo "  $modul : bereits aktiv"
    elif grep -Eq "^#LoadModule ${modul} " "$HTTPD_CONF"; then
        sed -i "s|^#LoadModule ${modul} |LoadModule ${modul} |" "$HTTPD_CONF"
        echo "  $modul : aktiviert"
    else
        echo "  $modul : ACHTUNG - Zeile nicht gefunden"
    fi
done

cat > /etc/httpd/conf/extra/zeiterfassung-dev.conf <<EOF
# ==========================================================================
# Lokale Entwicklungsumgebung "Zeiterfassung"
# Erzeugt von scripts/dev/setup_lokale_umgebung_arch.sh
#
#   App        : http://localhost/zeiterfassung
#   Terminal   : http://localhost/zeiterfassung/terminal.php
#   phpMyAdmin : http://localhost/phpmyadmin
# ==========================================================================

ServerName localhost

# PHP-Dateien an php-fpm durchreichen (FastCGI ueber Unix-Socket).
<FilesMatch "\.php\$">
    SetHandler "proxy:unix:/run/php-fpm/php-fpm.sock|fcgi://localhost/"
</FilesMatch>

# --- Projekt -------------------------------------------------------------
# Einstiegspunkt ist bewusst public/, genau wie in der Produktivinstallation.
Alias /zeiterfassung "$PROJEKT/public"

<Directory "$PROJEKT/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>

# --- phpMyAdmin ----------------------------------------------------------
Alias /phpmyadmin "/usr/share/webapps/phpMyAdmin"

<Directory "/usr/share/webapps/phpMyAdmin">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
EOF

if ! grep -q "zeiterfassung-dev.conf" "$HTTPD_CONF"; then
    {
        echo ""
        echo "# Lokale Entwicklungsumgebung Zeiterfassung"
        echo "Include conf/extra/zeiterfassung-dev.conf"
    } >> "$HTTPD_CONF"
    echo "Include-Zeile in httpd.conf ergaenzt."
else
    echo "Include-Zeile in httpd.conf bereits vorhanden."
fi

apachectl configtest

# --------------------------------------------------------------------------
schritt "5/9  MariaDB initialisieren und starten"
# --------------------------------------------------------------------------
if [ ! -d /var/lib/mysql/mysql ]; then
    echo "Datenverzeichnis wird initialisiert ..."
    mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
else
    echo "Datenverzeichnis existiert bereits - kein Init noetig."
fi

systemctl enable --now mariadb
sleep 3
systemctl is-active mariadb

# --------------------------------------------------------------------------
schritt "6/9  Datenbanken, Benutzer und Schema"
# --------------------------------------------------------------------------
# Zugangsdaten wie im Master-Prompt festgelegt: zeiterfassung/zeiterfassung.
# Die weitreichenden Rechte sind eine bewusste Entscheidung fuer die lokale
# Entwicklung (Import/Export/Anlegen in phpMyAdmin). Produktiv gehoeren hier
# Rechte nur auf die beiden Datenbanken hin.
mariadb -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`$DB_OFFLINE\`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';

GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

TABELLEN=$(mariadb -u root -N -B -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
echo "Tabellen in '$DB_NAME': $TABELLEN"

if [ "$TABELLEN" = "0" ]; then
    echo "Importiere sql/01_initial_schema.sql ..."
    mariadb -u root < "$PROJEKT/sql/01_initial_schema.sql"
    echo "Import fertig. Tabellen jetzt: $(mariadb -u root -N -B -e \
      "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"
else
    echo "Schema bereits vorhanden - Import uebersprungen."
fi

OFFLINE_TABELLEN=$(mariadb -u root -N -B -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_OFFLINE';")
if [ "$OFFLINE_TABELLEN" = "0" ]; then
    echo "Importiere sql/offline_db_schema.sql ..."
    mariadb -u root "$DB_OFFLINE" < "$PROJEKT/sql/offline_db_schema.sql"
else
    echo "Offline-Schema bereits vorhanden."
fi

# --------------------------------------------------------------------------
schritt "7/9  phpMyAdmin konfigurieren"
# --------------------------------------------------------------------------
PMA_CONF="/etc/webapps/phpmyadmin/config.inc.php"
PMA_TMP="/var/lib/phpmyadmin/tmp"

mkdir -p "$PMA_TMP"
chown -R "$WEBUSER:$WEBUSER" /var/lib/phpmyadmin
chmod 700 "$PMA_TMP"

GEHEIM="$(head -c 24 /dev/urandom | base32 | tr -d '=' | head -c 32)"

if [ ! -f "$PMA_CONF" ]; then
    mkdir -p /etc/webapps/phpmyadmin
    cat > "$PMA_CONF" <<EOF
<?php
declare(strict_types=1);
// Lokale Entwicklungsumgebung Zeiterfassung.
\$cfg['blowfish_secret'] = '$GEHEIM';
\$i = 0;
\$i++;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = 'localhost';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;
\$cfg['TempDir'] = '$PMA_TMP';
\$cfg['UploadDir'] = '';
\$cfg['SaveDir'] = '';
EOF
    echo "$PMA_CONF neu angelegt."
else
    cp -n "$PMA_CONF" "$PMA_CONF.orig-zeiterfassung" 2>/dev/null

    # Ohne gesetztes blowfish_secret verweigert phpMyAdmin den Cookie-Login.
    if grep -Eq "blowfish_secret'\][[:space:]]*=[[:space:]]*''" "$PMA_CONF"; then
        sed -i "s|blowfish_secret'\][[:space:]]*=[[:space:]]*''|blowfish_secret'] = '$GEHEIM'|" "$PMA_CONF"
        echo "  blowfish_secret gesetzt."
    elif ! grep -q "blowfish_secret" "$PMA_CONF"; then
        sed -i "1a \$cfg['blowfish_secret'] = '$GEHEIM';" "$PMA_CONF"
        echo "  blowfish_secret ergaenzt."
    else
        echo "  blowfish_secret bereits gesetzt."
    fi

    if ! grep -q "TempDir" "$PMA_CONF"; then
        echo "\$cfg['TempDir'] = '$PMA_TMP';" >> "$PMA_CONF"
        echo "  TempDir ergaenzt."
    fi
fi

# --------------------------------------------------------------------------
schritt "8/9  Dateirechte (Schreibzugriff fuer den Webserver)"
# --------------------------------------------------------------------------
# Der Code bleibt dem angemeldeten Benutzer gehoeren (Git-Arbeitskopie!).
# Der Webserver bekommt per ACL nur dort Schreibrechte, wo er sie braucht -
# inklusive Default-ACL, damit auch neu erzeugte Dateien passen.
#
# Wichtig: Die ACL gilt fuer *beide* Seiten. Sonst gehoeren vom Webserver
# erzeugte Dateien dem Benutzer 'http' und lassen sich weder per CLI-Skript noch
# beim Aufraeumen ueberschreiben (Symptom: "Permission denied" beim Erzeugen
# eines Barcodes ausserhalb des Browsers).
ENTWICKLER="$(stat -c %U "$PROJEKT")"
echo "Entwicklerkonto (Eigentuemer des Projekts): $ENTWICKLER"

for verzeichnis in "$PROJEKT/public/uploads" "$PROJEKT/public/img"; do
    if [ -d "$verzeichnis" ]; then
        setfacl -R    -m "u:$WEBUSER:rwX" -m "u:$ENTWICKLER:rwX" "$verzeichnis"
        setfacl -R -d -m "u:$WEBUSER:rwX" -m "u:$ENTWICKLER:rwX" "$verzeichnis"
        echo "ACL gesetzt: $verzeichnis"
    fi
done

# --------------------------------------------------------------------------
schritt "9/9  Dienste starten"
# --------------------------------------------------------------------------
systemctl enable --now php-fpm
systemctl restart php-fpm
systemctl enable --now httpd
systemctl restart httpd
sleep 2

for dienst in mariadb php-fpm httpd; do
    printf '%-10s %s\n' "$dienst" "$(systemctl is-active $dienst)"
done

# --------------------------------------------------------------------------
schritt "DIAGNOSE"
# --------------------------------------------------------------------------
php -v | head -1
echo "PHP-Module: $(php -m | tr '\n' ' ')"
echo "MariaDB:    $(mariadb -u root -N -B -e 'SELECT VERSION();')"

echo "DB-Login-Test (TCP):"
mariadb -h 127.0.0.1 -u "$DB_USER" -p"$DB_PASS" -N -B \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>&1 | tail -1

echo "HTTP-Test:"
curl -s -o /dev/null -w "  /zeiterfassung/             -> %{http_code}\n" "http://localhost/zeiterfassung/"
curl -s -o /dev/null -w "  /zeiterfassung/terminal.php -> %{http_code}\n" "http://localhost/zeiterfassung/terminal.php"
curl -s -o /dev/null -w "  /phpmyadmin/                -> %{http_code}\n" "http://localhost/phpmyadmin/"

echo
echo "=============================================================="
echo "  Fertig."
echo "  App:        http://localhost/zeiterfassung"
echo "  phpMyAdmin: http://localhost/phpmyadmin  (Login: $DB_USER / $DB_PASS)"
echo "=============================================================="
