#!/usr/bin/env bash
#
# ===========================================================================
#  Zeiterfassung - Hallenterminal, Phase 1: Grundsystem
# ===========================================================================
#
#  Aufruf:
#      sudo ./scripts/terminal/install_terminal.sh [antwortdatei]
#
#  Ergebnis:
#      Auf dem Geraet laeuft ein Webserver, der die Terminal-Oberflaeche
#      ausliefert, und daneben eine lokale Ausweichdatenbank fuer den
#      Offline-Betrieb.
#
#      Bewusst NICHT geschrieben wird `config/config.local.php`: Das Terminal
#      startet unkonfiguriert, zeigt seine Einrichtungsseite und holt sich
#      Zugangsdaten und Terminal-ID per Kopplungscode selbst
#      (Phase 3, docs/spezifikation_terminal_installation.md Abschnitt 2a).
#      Genau deshalb kennt dieses Skript keine Zugangsdaten und dasselbe
#      Abbild passt auf beliebig viele Geraete.
#
#  Was dieses Skript NICHT macht - spaetere Stufen der Spezifikation:
#      - Kiosk: Autologin, Browser im Vollbild, Grafikstack   (Stufe 4)
#      - Peripherie: RFID-Leser, Touchscreen-Drehung          (Stufe 5)
#      - Selbsttest mit Scan-Proben                           (Stufe 6)
#
#  Idempotent: Mehrfaches Ausfuehren ist unschaedlich und repariert einen
#  halbfertigen Stand. Ein bereits gekoppeltes Terminal wird nicht angefasst.
#
#  Protokoll: /var/log/zeiterfassung-terminal-setup.log
#
#  Grundlage: docs/spezifikation_terminal_installation.md, Abschnitte 3 bis 5.
# ===========================================================================

set -uo pipefail

# ---------------------------------------------------------------------------
# Vorbedingung: root. Alles Weitere schreibt nach /etc, /opt und /var.
# ---------------------------------------------------------------------------
if [ "$(id -u)" -ne 0 ]; then
    echo "FEHLER: Bitte mit sudo starten:  sudo bash $0"
    exit 1
fi

SKRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJEKT="$(cd "$SKRIPTDIR/../.." && pwd)"

# Ab hier laeuft alles zusaetzlich ins Protokoll. Wer zwanzig Geraete aufsetzt,
# sieht den Fehler von Geraet sieben sonst nie wieder.
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
GIT_REPO=""
GIT_BRANCH="main"
TASTATURLAYOUT="de"
ZEITZONE="Europe/Berlin"
OFFLINE_DB_NAME="zeiterfassung_offline"
OFFLINE_DB_USER="zeiterfassung_offline"

WARNUNGEN=()

schritt()  { echo; echo "---- $* ----"; }
warnung()  { echo "WARNUNG: $*"; WARNUNGEN+=("$*"); }

# Fuer die Ergebnisliste am Ende: pruefe "<Text>" <Befehl...>
# Die Pruefbefehle bekommen bewusst /dev/null als Eingabe: Fragt einer davon
# wider Erwarten nach einem Passwort, soll er scheitern und nicht warten.
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

# Fragt nur nach, wenn ein Mensch davor sitzt. Ein unbeaufsichtigter Lauf
# (Antwortdatei, Image-Bau) darf nicht an einer Eingabeaufforderung haengen.
frage() {
    local name="$1" text="$2" eingabe
    [ -t 0 ] || return 0
    read -r -p "$text [${!name}]: " eingabe
    [ -n "$eingabe" ] && printf -v "$name" '%s' "$eingabe"
    return 0
}

echo "=============================================================="
echo "  Zeiterfassung - Terminal-Grundsystem (Phase 1)"
echo "  Skript:  $SKRIPTDIR"
echo "  Start:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================="

# ---------------------------------------------------------------------------
schritt "1/10  Antwortdatei und Vorbedingungen"
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

# Paketmanager-Familie ueber /etc/os-release. Nur die vier grossen Familien;
# Exoten muessen von Hand nacharbeiten (Spezifikation Abschnitt 9).
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
    echo "        siehe docs/installationsanleitung.md."
    exit 1
fi
echo "Familie: $FAMILIE"

if ! ip route show default 2>/dev/null | grep -q .; then
    warnung "Keine Standardroute - ohne Netz schlagen Paketinstallation und Klon fehl."
fi

# Zuordnungstabelle: alles, was je Familie anders heisst, steht hier an EINER
# Stelle. Verstreute Sonderfaelle im Code waren der haeufigste Grund, warum
# solche Skripte nach der naechsten Distribution unwartbar werden.
case "$FAMILIE" in
    apt)
        PAKETE="apache2 php-fpm php-cli php-mysql php-mbstring php-gd php-xml mariadb-server git curl ca-certificates"
        WEBDIENST="apache2"; WEBBENUTZER="www-data"
        VHOST_DATEI="/etc/apache2/sites-available/zeiterfassung-terminal.conf"
        ;;
    pacman)
        PAKETE="apache php php-fpm php-gd mariadb git curl"
        WEBDIENST="httpd"; WEBBENUTZER="http"
        VHOST_DATEI="/etc/httpd/conf/extra/zeiterfassung-terminal.conf"
        ;;
    dnf)
        PAKETE="httpd php-fpm php-cli php-mysqlnd php-mbstring php-gd php-xml mariadb-server git curl"
        WEBDIENST="httpd"; WEBBENUTZER="apache"
        VHOST_DATEI="/etc/httpd/conf.d/zz-zeiterfassung-terminal.conf"
        ;;
    zypper)
        PAKETE="apache2 php8 php8-fpm php8-cli php8-mysql php8-mbstring php8-gd mariadb git curl"
        WEBDIENST="apache2"; WEBBENUTZER="wwwrun"
        VHOST_DATEI="/etc/apache2/vhosts.d/zeiterfassung-terminal.conf"
        ;;
esac

# Codequelle bestimmen. Reihenfolge: Antwortdatei, sonst das origin der
# Arbeitskopie, aus der dieses Skript gerade laeuft.
if [ -z "$GIT_REPO" ] && [ -d "$PROJEKT/.git" ]; then
    GIT_REPO="$(git -C "$PROJEKT" remote get-url origin 2>/dev/null || true)"
fi
if [ -z "$GIT_REPO" ] && [ ! -d "$ZIEL_VERZEICHNIS/.git" ] && [ "$ZIEL_VERZEICHNIS" != "$PROJEKT" ]; then
    frage GIT_REPO "Git-Repository (URL)"
fi
frage TASTATURLAYOUT "Tastaturlayout (entscheidend fuer den Barcode-Scanner)"

echo "Zielverzeichnis: $ZIEL_VERZEICHNIS"
echo "Zweig:           $GIT_BRANCH"
echo "Tastaturlayout:  $TASTATURLAYOUT"

# Dieses Skript richtet ein Geraet ein, es passt keines an: Es schreibt die
# Webserver-Konfiguration, setzt das Tastaturlayout systemweit und uebereignet
# das Zielverzeichnis an root. Auf einem Arbeitsplatzrechner ist das selten
# gewollt - die lokale Entwicklungsumgebung hat ihr eigenes Skript.
case "$ZIEL_VERZEICHNIS" in
    /home/*)
        warnung "Ziel liegt unter /home - das Verzeichnis wechselt den Eigentuemer zu root."
        warnung "Fuer eine Entwicklungsumgebung ist scripts/dev/setup_lokale_umgebung_arch.sh gedacht."
        ;;
esac

# ---------------------------------------------------------------------------
schritt "2/10  Pakete installieren"
# ---------------------------------------------------------------------------
echo "Pakete: $PAKETE"
paket_fehler=0
case "$FAMILIE" in
    apt)
        DEBIAN_FRONTEND=noninteractive apt-get update || paket_fehler=1
        # shellcheck disable=SC2086
        DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends $PAKETE || paket_fehler=1
        ;;
    pacman)
        # shellcheck disable=SC2086
        pacman -Syu --needed --noconfirm $PAKETE || paket_fehler=1
        ;;
    dnf)
        # shellcheck disable=SC2086
        dnf install -y $PAKETE || paket_fehler=1
        ;;
    zypper)
        # shellcheck disable=SC2086
        zypper --non-interactive install $PAKETE || paket_fehler=1
        ;;
esac

if [ "$paket_fehler" -ne 0 ]; then
    echo "FEHLER: Paketinstallation fehlgeschlagen. Ohne Pakete geht es nicht weiter."
    echo "        Einzelheiten im Protokoll: $LOGDATEI"
    exit 1
fi

# ---------------------------------------------------------------------------
schritt "3/10  PHP-Erweiterungen sicherstellen"
# ---------------------------------------------------------------------------
# Arch liefert die Erweiterungen mit, aktiviert sie in der php.ini aber nicht.
# Statt in der php.ini zu schneiden legen wir eine eigene Datei in conf.d ab.
# Die anderen Familien liefern je Erweiterung ein Paket, das sich selbst
# eintraegt - dort ist nichts zu tun.
if [ "$FAMILIE" = "pacman" ]; then
    MODULDIR="$(php -r 'echo ini_get("extension_dir");' 2>/dev/null)"
    INI="/etc/php/conf.d/99-zeiterfassung-terminal.ini"
    {
        echo "; Erzeugt von scripts/terminal/install_terminal.sh"
        echo "date.timezone = $ZEITZONE"
    } > "$INI"
    for ext in pdo_mysql mbstring gd iconv; do
        if [ -f "$MODULDIR/$ext.so" ]; then
            if grep -Eq "^[[:space:]]*extension[[:space:]]*=[[:space:]]*$ext" /etc/php/php.ini 2>/dev/null; then
                echo "  $ext : bereits in php.ini aktiv"
            else
                echo "extension=$ext" >> "$INI"
                echo "  $ext : aktiviert"
            fi
        else
            echo "  $ext : fest einkompiliert oder nicht vorhanden (uebersprungen)"
        fi
    done
fi

# openSUSE liefert die php-fpm-Poolkonfiguration nur als Vorlage aus; ohne
# www.conf startet der Dienst gar nicht erst.
if [ "$FAMILIE" = "zypper" ]; then
    for vorlage in /etc/php8/fpm/php-fpm.conf.default /etc/php8/fpm/php-fpm.d/www.conf.default; do
        ziel="${vorlage%.default}"
        if [ -f "$vorlage" ] && [ ! -f "$ziel" ]; then
            cp "$vorlage" "$ziel"
            echo "  angelegt: $ziel"
        fi
    done
fi

for modul in pdo_mysql mbstring gd; do
    if php -m 2>/dev/null | grep -qix "$modul"; then
        echo "  PHP-Modul $modul: vorhanden"
    else
        warnung "PHP-Modul $modul fehlt - die Anwendung laeuft damit nicht vollstaendig."
    fi
done

# ---------------------------------------------------------------------------
schritt "4/10  Dienste starten"
# ---------------------------------------------------------------------------
# In einem Container ohne systemd schlaegt systemctl fehl. Das ist beim Testen
# der Fall und kein Grund abzubrechen - der Rest des Skripts bleibt pruefbar.
dienst_an() {
    local dienst="$1"
    if ! command -v systemctl >/dev/null 2>&1; then
        warnung "Kein systemd - Dienst '$dienst' muss von Hand gestartet werden."
        return 0
    fi
    systemctl enable --now "$dienst" >/dev/null 2>&1 || \
        warnung "Dienst '$dienst' liess sich nicht starten (systemctl status $dienst)."
}

# Arch initialisiert das Datenverzeichnis der Datenbank nicht selbst.
if [ ! -d /var/lib/mysql/mysql ] && command -v mariadb-install-db >/dev/null 2>&1; then
    echo "Datenverzeichnis der MariaDB wird initialisiert ..."
    mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >/dev/null
fi

PHP_FPM_DIENST="php-fpm"
[ "$FAMILIE" = "apt" ] && PHP_FPM_DIENST="$(systemctl list-unit-files 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print $1}')"
[ -z "${PHP_FPM_DIENST:-}" ] && PHP_FPM_DIENST="php-fpm"

dienst_an "$PHP_FPM_DIENST"
dienst_an mariadb

# Kurz warten: MariaDB nimmt Verbindungen erst einige Sekunden nach dem Start an.
if command -v mysqladmin >/dev/null 2>&1; then
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        mysqladmin ping >/dev/null 2>&1 && break
        sleep 1
    done
fi

# ---------------------------------------------------------------------------
schritt "5/10  Code holen"
# ---------------------------------------------------------------------------
if [ "$ZIEL_VERZEICHNIS" = "$PROJEKT" ]; then
    echo "Der Code liegt bereits am Zielort ($PROJEKT) - kein Klon noetig."
elif [ -d "$ZIEL_VERZEICHNIS/.git" ]; then
    echo "Arbeitskopie vorhanden - wird aktualisiert."
    git -C "$ZIEL_VERZEICHNIS" fetch --quiet origin || \
        warnung "git fetch fehlgeschlagen - der vorhandene Stand bleibt unveraendert."
    git -C "$ZIEL_VERZEICHNIS" checkout --quiet "$GIT_BRANCH" 2>/dev/null || true
    # Bewusst nur --ff-only: Ein 'reset --hard' wuerde lokale Nacharbeit am
    # Geraet stillschweigend wegwerfen.
    git -C "$ZIEL_VERZEICHNIS" pull --ff-only --quiet || \
        warnung "git pull --ff-only fehlgeschlagen - Stand am Geraet pruefen."
elif [ -n "$GIT_REPO" ]; then
    echo "Klone $GIT_REPO ($GIT_BRANCH) nach $ZIEL_VERZEICHNIS ..."
    mkdir -p "$(dirname "$ZIEL_VERZEICHNIS")"
    if ! git clone --branch "$GIT_BRANCH" "$GIT_REPO" "$ZIEL_VERZEICHNIS"; then
        echo "FEHLER: Klonen fehlgeschlagen. Ohne Code gibt es nichts auszuliefern."
        exit 1
    fi
else
    echo "FEHLER: Kein Code am Zielort und kein GIT_REPO angegeben."
    echo "        GIT_REPO in der Antwortdatei setzen (Vorlage: terminal.conf.example)"
    exit 1
fi

if [ ! -f "$ZIEL_VERZEICHNIS/public/terminal.php" ]; then
    echo "FEHLER: $ZIEL_VERZEICHNIS sieht nicht nach dem Projekt aus"
    echo "        (public/terminal.php fehlt)."
    exit 1
fi

# ---------------------------------------------------------------------------
schritt "6/10  Webserver auf public/ zeigen lassen"
# ---------------------------------------------------------------------------
# Einheitlich ueber php-fpm und mod_proxy_fcgi statt je Familie ein anderes
# PHP-Modul: Der Socketpfad unterscheidet sich zwar, laesst sich aber suchen -
# die Konfiguration bleibt damit fuer alle vier Familien dieselbe.
PHP_HANDLER=""
for kandidat in /run/php-fpm/*.sock /run/php/*.sock /var/run/php-fpm/*.sock /var/run/php/*.sock; do
    if [ -S "$kandidat" ]; then
        PHP_HANDLER="proxy:unix:$kandidat|fcgi://localhost/"
        echo "php-fpm-Socket: $kandidat"
        break
    fi
done
if [ -z "$PHP_HANDLER" ]; then
    PHP_HANDLER="proxy:fcgi://127.0.0.1:9000"
    warnung "Kein php-fpm-Socket gefunden - es wird 127.0.0.1:9000 verwendet."
fi

mkdir -p "$(dirname "$VHOST_DATEI")"
cat > "$VHOST_DATEI" <<EOF
# ==========================================================================
# Zeiterfassung - Hallenterminal
# Erzeugt von scripts/terminal/install_terminal.sh am $(date '+%Y-%m-%d %H:%M:%S')
#
# Der Document-Root zeigt auf public/, genau wie in der Serverinstallation.
# Solange config/config.local.php fehlt, liefert terminal.php die
# Einrichtungsseite aus - das ist der gewollte Zustand nach dieser Phase.
# ==========================================================================

<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "$ZIEL_VERZEICHNIS/public"

    <Directory "$ZIEL_VERZEICHNIS/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    <FilesMatch "\.php\$">
        SetHandler "$PHP_HANDLER"
    </FilesMatch>
</VirtualHost>
EOF
echo "Konfiguration geschrieben: $VHOST_DATEI"

case "$FAMILIE" in
    apt|zypper)
        a2enmod proxy proxy_fcgi setenvif >/dev/null 2>&1 || \
            warnung "a2enmod proxy/proxy_fcgi fehlgeschlagen - PHP wird sonst nicht ausgefuehrt."
        if [ "$FAMILIE" = "apt" ]; then
            a2ensite zeiterfassung-terminal >/dev/null 2>&1 || \
                warnung "a2ensite zeiterfassung-terminal fehlgeschlagen."
            # Sonst bleibt 000-default die Standardseite fuer Port 80 - der
            # Kioskbrowser landet dann auf der Debian-Willkommensseite.
            a2dissite 000-default >/dev/null 2>&1 && echo "Standardseite 000-default abgeschaltet."
        fi
        ;;
    pacman)
        HTTPD_CONF="/etc/httpd/conf/httpd.conf"
        cp -n "$HTTPD_CONF" "$HTTPD_CONF.orig-zeiterfassung" 2>/dev/null && \
            echo "Sicherung angelegt: $HTTPD_CONF.orig-zeiterfassung"
        for modul in proxy_module proxy_fcgi_module rewrite_module; do
            if grep -Eq "^LoadModule ${modul} " "$HTTPD_CONF"; then
                echo "  $modul : bereits aktiv"
            elif grep -Eq "^#LoadModule ${modul} " "$HTTPD_CONF"; then
                sed -i "s|^#LoadModule ${modul} |LoadModule ${modul} |" "$HTTPD_CONF"
                echo "  $modul : aktiviert"
            else
                warnung "LoadModule-Zeile fuer $modul nicht gefunden."
            fi
        done
        if ! grep -q "zeiterfassung-terminal.conf" "$HTTPD_CONF"; then
            {
                echo ""
                echo "# Zeiterfassung - Hallenterminal"
                echo "Include conf/extra/zeiterfassung-terminal.conf"
            } >> "$HTTPD_CONF"
            echo "Include-Zeile in httpd.conf ergaenzt."
        else
            echo "Include-Zeile in httpd.conf bereits vorhanden."
        fi
        ;;
    dnf)
        # Fedora/RHEL laden die Proxy-Module ueber conf.modules.d bereits selbst.
        echo "Konfiguration liegt in conf.d und wird automatisch eingelesen."
        ;;
esac

if command -v apachectl >/dev/null 2>&1; then
    apachectl configtest 2>&1 | tail -3
elif command -v apache2ctl >/dev/null 2>&1; then
    apache2ctl configtest 2>&1 | tail -3
fi
dienst_an "$WEBDIENST"
command -v systemctl >/dev/null 2>&1 && systemctl reload "$WEBDIENST" >/dev/null 2>&1

# ---------------------------------------------------------------------------
schritt "7/10  Lokale Ausweichdatenbank"
# ---------------------------------------------------------------------------
DB_CLIENT="$(command -v mariadb || command -v mysql || true)"
GERAETE_DATEI="$ZIEL_VERZEICHNIS/config/geraet.local.php"

if [ -z "$DB_CLIENT" ]; then
    warnung "Kein Datenbank-Client gefunden - Ausweichdatenbank nicht eingerichtet."
    OFFLINE_DB_PASS=""
else
    # Ein vorhandenes Passwort wird wiederverwendet, nicht erneuert. Ein
    # bereits gekoppeltes Terminal traegt die Zugangsdaten in seiner
    # config.local.php; ein neues Passwort wuerde ihm still die Queue kappen.
    OFFLINE_DB_PASS=""
    if [ -f "$GERAETE_DATEI" ] && command -v php >/dev/null 2>&1; then
        OFFLINE_DB_PASS="$(php -r '$d = @include $argv[1]; echo (is_array($d) && isset($d["offline_db"]["pass"])) ? (string)$d["offline_db"]["pass"] : "";' "$GERAETE_DATEI" 2>/dev/null)"
        [ -n "$OFFLINE_DB_PASS" ] && echo "Vorhandenes Passwort aus geraet.local.php uebernommen."
    fi
    if [ -z "$OFFLINE_DB_PASS" ]; then
        OFFLINE_DB_PASS="$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32)"
    fi

    # Rechte bewusst nur auf dieser einen lokalen Datenbank, dafuer inklusive
    # CREATE: Der OfflineQueueManager legt db_injektionsqueue bei Bedarf selbst
    # an, und DELETE braucht er zum Aufraeumen verarbeiteter Eintraege.
    if "$DB_CLIENT" -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$OFFLINE_DB_NAME\`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$OFFLINE_DB_USER'@'localhost' IDENTIFIED BY '$OFFLINE_DB_PASS';
ALTER USER '$OFFLINE_DB_USER'@'localhost' IDENTIFIED BY '$OFFLINE_DB_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX ON \`$OFFLINE_DB_NAME\`.*
  TO '$OFFLINE_DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    then
        echo "Datenbank '$OFFLINE_DB_NAME' und Benutzer '$OFFLINE_DB_USER' eingerichtet."
        if [ -f "$ZIEL_VERZEICHNIS/sql/offline_db_schema.sql" ]; then
            "$DB_CLIENT" -u root "$OFFLINE_DB_NAME" < "$ZIEL_VERZEICHNIS/sql/offline_db_schema.sql" && \
                echo "Queue-Tabelle angelegt (CREATE TABLE IF NOT EXISTS)."
        fi
    else
        warnung "Ausweichdatenbank liess sich nicht anlegen - das Terminal kann offline nichts zwischenspeichern."
        OFFLINE_DB_PASS=""
    fi
fi

# ---------------------------------------------------------------------------
schritt "8/10  config/geraet.local.php schreiben"
# ---------------------------------------------------------------------------
# Das ist die einzige Konfiguration, die dieses Skript hinterlaesst: die zwei
# Dinge, die der Maschine gehoeren und die die Kopplung nicht liefern kann.
# Die Zugangsdaten der Hauptdatenbank kommen ausschliesslich aus der Kopplung.
if [ -n "$OFFLINE_DB_PASS" ]; then
    OFFLINE_AKTIV="true"
else
    OFFLINE_AKTIV="false"
    warnung "geraet.local.php wird ohne funktionsfaehige Ausweichdatenbank geschrieben."
fi

TEMP_DATEI="$(dirname "$GERAETE_DATEI")/.geraet.local.php.tmp"
cat > "$TEMP_DATEI" <<EOF
<?php
declare(strict_types=1);

/**
 * Geraetelokale Einstellungen dieses Terminals.
 *
 * Erzeugt von scripts/terminal/install_terminal.sh am $(date '+%d.%m.%Y %H:%M:%S').
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
        'enabled' => $OFFLINE_AKTIV,
        'host'    => 'localhost',
        'dbname'  => '$OFFLINE_DB_NAME',
        'charset' => 'utf8mb4',
        'user'    => '$OFFLINE_DB_USER',
        'pass'    => '$OFFLINE_DB_PASS',
    ],

    'terminal' => [
        // USB-Leser tippen wie eine Tastatur und brauchen keine Bridge.
        // Bei einem RC522 am SPI setzt Stufe 5 das hier auf true.
        'rfid_ws' => ['enabled' => false, 'url' => 'ws://127.0.0.1:8765'],
    ],
];
EOF

# Erst danebenschreiben, gegenlesen, dann umbenennen - eine halb geschriebene
# Datei wuerde beim Koppeln einen Syntaxfehler ergeben.
if php -l "$TEMP_DATEI" >/dev/null 2>&1 || ! command -v php >/dev/null 2>&1; then
    mv "$TEMP_DATEI" "$GERAETE_DATEI"
    chmod 0640 "$GERAETE_DATEI"
    chown "root:$WEBBENUTZER" "$GERAETE_DATEI" 2>/dev/null
    echo "Geschrieben: $GERAETE_DATEI"
else
    rm -f "$TEMP_DATEI"
    warnung "geraet.local.php war fehlerhaft und wurde verworfen."
fi

if [ -f "$ZIEL_VERZEICHNIS/config/config.local.php" ]; then
    echo "HINWEIS: config.local.php ist vorhanden - dieses Terminal ist bereits"
    echo "         gekoppelt und wird nicht angefasst. Zum Neukoppeln die Datei"
    echo "         loeschen; dann erscheint wieder die Einrichtungsseite."
fi

# ---------------------------------------------------------------------------
schritt "9/10  Dateirechte"
# ---------------------------------------------------------------------------
# Der Code gehoert root und ist fuer den Webserver nur lesbar. Schreiben darf
# er ausschliesslich in config/ (dort legt die Kopplung config.local.php an)
# und in public/uploads/.
chown -R "root:$WEBBENUTZER" "$ZIEL_VERZEICHNIS"
chmod -R u=rwX,g=rX,o= "$ZIEL_VERZEICHNIS"
chmod 2770 "$ZIEL_VERZEICHNIS/config"
if [ -d "$ZIEL_VERZEICHNIS/public/uploads" ]; then
    # setgid nur auf Verzeichnissen: So behalten spaeter erzeugte Dateien die
    # Gruppe des Webservers. Auf Dateien hat das Bit nichts zu suchen.
    find "$ZIEL_VERZEICHNIS/public/uploads" -type d -exec chmod 2770 {} +
    find "$ZIEL_VERZEICHNIS/public/uploads" -type f -exec chmod 0660 {} +
fi
echo "Eigentuemer: root:$WEBBENUTZER, config/ und public/uploads/ gruppenschreibbar."

# SELinux (Fedora/RHEL) verbietet dem Webserver sonst den Zugriff auf /opt und
# die Verbindung zur Datenbank des Backends - beides faellt erst spaeter auf.
if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce 2>/dev/null)" != "Disabled" ]; then
    echo "SELinux ist aktiv - Kontexte werden gesetzt."
    if command -v semanage >/dev/null 2>&1; then
        semanage fcontext -a -t httpd_sys_content_t "${ZIEL_VERZEICHNIS}(/.*)?" >/dev/null 2>&1
        semanage fcontext -a -t httpd_sys_rw_content_t "${ZIEL_VERZEICHNIS}/config(/.*)?" >/dev/null 2>&1
        semanage fcontext -a -t httpd_sys_rw_content_t "${ZIEL_VERZEICHNIS}/public/uploads(/.*)?" >/dev/null 2>&1
        restorecon -R "$ZIEL_VERZEICHNIS" >/dev/null 2>&1
    else
        chcon -R -t httpd_sys_content_t "$ZIEL_VERZEICHNIS" >/dev/null 2>&1
        chcon -R -t httpd_sys_rw_content_t "$ZIEL_VERZEICHNIS/config" >/dev/null 2>&1
    fi
    # Ohne diesen Schalter erreicht das Terminal die Hauptdatenbank nicht.
    setsebool -P httpd_can_network_connect_db on >/dev/null 2>&1 || \
        warnung "setsebool httpd_can_network_connect_db fehlgeschlagen - Terminal erreicht die Hauptdatenbank evtl. nicht."
fi

# ---------------------------------------------------------------------------
schritt "10/10  Tastaturlayout und Zeitzone"
# ---------------------------------------------------------------------------
# Pflichtschritt, kein Beiwerk: Der Barcode-Scanner tippt wie eine Tastatur.
# Steht das System auf US-Layout, kommt bei 'y', 'z' und Sonderzeichen etwas
# anderes an, als auf dem Etikett steht - und das Terminal bucht klaglos einen
# falschen Code.
mkdir -p /etc/X11/xorg.conf.d
cat > /etc/X11/xorg.conf.d/00-keyboard.conf <<EOF
# Erzeugt von scripts/terminal/install_terminal.sh
# Ohne passendes Layout liefert der Barcode-Scanner falsche Zeichen.
Section "InputClass"
    Identifier "system-keyboard"
    MatchIsKeyboard "on"
    Option "XkbLayout" "$TASTATURLAYOUT"
EndSection
EOF

echo "KEYMAP=$TASTATURLAYOUT" > /etc/vconsole.conf

if [ "$FAMILIE" = "apt" ]; then
    cat > /etc/default/keyboard <<EOF
# Erzeugt von scripts/terminal/install_terminal.sh
XKBMODEL="pc105"
XKBLAYOUT="$TASTATURLAYOUT"
XKBVARIANT=""
XKBOPTIONS=""
BACKSPACE="guess"
EOF
    command -v setupcon >/dev/null 2>&1 && setupcon --save >/dev/null 2>&1
fi

if command -v localectl >/dev/null 2>&1; then
    localectl set-x11-keymap "$TASTATURLAYOUT" >/dev/null 2>&1 || true
    localectl set-keymap "$TASTATURLAYOUT" >/dev/null 2>&1 || true
fi
echo "Tastaturlayout '$TASTATURLAYOUT' gesetzt (Konsole und X11)."

# Die Uhr im Terminal-Header laeuft nach der Systemzeit. Ein Geraet in UTC
# zeigt der Halle sonst stundenversetzte Buchungszeiten an.
if command -v timedatectl >/dev/null 2>&1; then
    timedatectl set-timezone "$ZEITZONE" >/dev/null 2>&1 && echo "Zeitzone: $ZEITZONE"
fi

# ---------------------------------------------------------------------------
schritt "ERGEBNIS"
# ---------------------------------------------------------------------------
pruefe "Webserver laeuft" systemctl is-active --quiet "$WEBDIENST"
pruefe "PHP-Modul pdo_mysql vorhanden" \
    php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'
# Nur mit Passwort pruefen: Ein leeres -p wuerde der Client als "bitte fragen"
# verstehen und der Lauf bliebe an der Eingabeaufforderung stehen.
if [ -n "$OFFLINE_DB_PASS" ]; then
    pruefe "Ausweichdatenbank erreichbar" \
        "${DB_CLIENT:-false}" -h localhost -u "$OFFLINE_DB_USER" -p"$OFFLINE_DB_PASS" \
        -e "SELECT COUNT(*) FROM db_injektionsqueue;" "$OFFLINE_DB_NAME"
else
    printf '  [ FEHLT ] %s\n' "Ausweichdatenbank eingerichtet - Terminal kann offline nichts zwischenspeichern"
    ERGEBNIS_FEHLT=$((ERGEBNIS_FEHLT + 1))
fi
pruefe "config/geraet.local.php vorhanden" test -f "$GERAETE_DATEI"
# runuser statt sudo: sudo ist auf einem frisch aufgesetzten Geraet oft gar
# nicht installiert, runuser gehoert zu util-linux.
pruefe "config/ fuer den Webserver beschreibbar" \
    runuser -u "$WEBBENUTZER" -- test -w "$ZIEL_VERZEICHNIS/config"

HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' http://localhost/terminal.php 2>/dev/null)"
if [ "$HTTP_CODE" = "200" ] && curl -s http://localhost/terminal.php 2>/dev/null | grep -q "Terminal einrichten"; then
    printf '  [ OK    ] %s\n' "terminal.php zeigt die Einrichtungsseite (HTTP 200)"
elif [ "$HTTP_CODE" = "200" ]; then
    printf '  [ OK    ] %s\n' "terminal.php antwortet mit HTTP 200 (bereits gekoppelt?)"
else
    printf '  [ FEHLT ] %s\n' "terminal.php antwortet mit HTTP ${HTTP_CODE:-keine Antwort}"
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
    echo "  Phase 1 abgeschlossen."
else
    echo "  Phase 1 mit $ERGEBNIS_FEHLT offenen Punkten - siehe Liste oben."
fi
echo "  Protokoll: $LOGDATEI"
echo
echo "  Naechster Schritt am Geraet (Phase 3):"
echo "  Im Backend unter Verwaltung -> Terminals einen Kopplungscode erzeugen"
echo "  und ihn zusammen mit der Server-Adresse auf der Einrichtungsseite"
echo "  eingeben:  http://localhost/terminal.php"
echo "=============================================================="

exit 0
