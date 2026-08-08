# Lokale Entwicklungsumgebung

Diese Anleitung beschreibt, wie das Projekt auf einem Entwicklungsrechner zum
Laufen gebracht wird, so dass die App im Browser unter
`http://localhost/zeiterfassung` erreichbar ist und Aenderungen am Code sofort
sichtbar werden.

**Abgrenzung:** Fuer die Produktivinstallation (Debian/Apache auf dem Server,
Terminal auf dem Raspberry Pi) gilt weiterhin
[`docs/installationsanleitung.md`](installationsanleitung.md). Die hier
beschriebene Umgebung ist bewusst bequemer und dafuer weniger streng
abgesichert – sie gehoert nicht ins Internet.

---

## 1. Zielbild

| Was | URL / Pfad |
| --- | --- |
| Backend (Hauptsystem) | `http://localhost/zeiterfassung` |
| Terminal (Kiosk-UI) | `http://localhost/zeiterfassung/terminal.php` |
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Code / Git-Arbeitskopie | das geklonte Verzeichnis, z. B. `~/zeiterfassunglocal` |

Der Webserver zeigt per `Alias` direkt in die Git-Arbeitskopie. Es wird nichts
kopiert oder deployt: Datei speichern, Seite neu laden, fertig.

## 2. Komponenten

Bewusst **native Installation, kein Docker** – so schreibt es der Master-Prompt
vor, und es entspricht dem Zielsystem (Debian-Server, Raspberry Pi).

- **Apache 2.4** – Webserver, liefert `public/` aus
- **php-fpm** – PHP als eigener Dienst, per FastCGI an Apache angebunden
- **MariaDB (LTS)** – Datenbank; die LTS-Linie entspricht der Version, die
  Debian 13 / Raspberry Pi OS mitbringen
- **phpMyAdmin** – komfortabler DB-Zugriff (Dump ziehen, SQL testen)

Benoetigte PHP-Erweiterungen: `pdo_mysql` (App), `mysqli` (phpMyAdmin),
`mbstring`, `gd` (QR-Codes und Barcodes), `iconv`.

## 3. Schnellstart auf Arch Linux / CachyOS

```bash
sudo bash scripts/dev/setup_lokale_umgebung_arch.sh
```

Das Skript ist idempotent und macht alles aus Abschnitt 4 automatisch. Danach
`http://localhost/zeiterfassung` aufrufen und den Erst-Admin anlegen
(Abschnitt 6).

Fuer andere Distributionen dient das Skript als Vorlage – die Schritte sind
identisch, nur Paketnamen und Pfade unterscheiden sich (siehe Abschnitt 4).

## 4. Was dabei passiert (und wie es auf Debian aussieht)

### 4.1 Pakete

| Zweck | Arch / CachyOS | Debian / Ubuntu |
| --- | --- | --- |
| Webserver | `apache` | `apache2` |
| PHP | `php php-fpm php-gd` | `php php-fpm php-mysql php-gd php-mbstring` |
| Datenbank | `mariadb-lts` | `mariadb-server` |
| DB-Oberflaeche | `phpmyadmin` | `phpmyadmin` |

Unter Arch sind PHP-Erweiterungen zwar mitinstalliert, aber in der `php.ini`
deaktiviert. Statt in der `php.ini` zu editieren, legt das Skript eine eigene
Datei `/etc/php/conf.d/99-zeiterfassung-dev.ini` an (Zeitzone + `extension=`
Zeilen). Debian aktiviert die Module beim Paketinstall selbst.

### 4.2 Apache

Konfiguration liegt in `/etc/httpd/conf/extra/zeiterfassung-dev.conf`
(Debian: `/etc/apache2/sites-available/…`), eingebunden ueber `httpd.conf`:

- `.php` wird per `SetHandler proxy:unix:/run/php-fpm/php-fpm.sock` an php-fpm
  durchgereicht (Module `proxy` und `proxy_fcgi` muessen aktiv sein)
- `Alias /zeiterfassung` zeigt auf `public/` – nicht auf die Projektwurzel
- `Alias /phpmyadmin` zeigt auf die phpMyAdmin-Installation

### 4.3 Datenbank

Angelegt werden zwei Datenbanken und der Benutzer aus dem Master-Prompt:

| Objekt | Wert |
| --- | --- |
| Hauptdatenbank | `zeiterfassung` |
| Offline-Datenbank | `zeiterfassung_offline` |
| Benutzer / Passwort | `zeiterfassung` / `zeiterfassung` |

Anschliessend werden `sql/01_initial_schema.sql` und
`sql/offline_db_schema.sql` importiert (nur wenn die DB noch leer ist).

Der Benutzer wird fuer `localhost` **und** `127.0.0.1` angelegt: MariaDB
unterscheidet die beiden: `localhost` bedeutet Unix-Socket, `127.0.0.1`
bedeutet TCP. Die App verbindet per TCP, phpMyAdmin per Socket.

### 4.4 Konfiguration der App

`config/config.local.php` (nicht versioniert) mit:

- `base_url` = `/zeiterfassung` – passend zum Apache-Alias
- `debug` = `true` – lokal sollen Fehler sichtbar sein
- DB-Zugangsdaten wie oben
- `terminal.rfid_ws.enabled` = `false` – lokal laeuft kein RFID-Bridge-Dienst,
  sonst wartet die Terminal-UI dauerhaft auf einen toten WebSocket

Vorlage: `config/config.php.example`.

### 4.5 Dateirechte

Der Code gehoert dem angemeldeten Benutzer – das muss so bleiben, sonst wird
das Arbeiten mit Git muehsam. Der Webserver bekommt deshalb **nur** per ACL
Schreibrechte auf die Verzeichnisse, in die er wirklich schreibt:

```bash
sudo setfacl -R    -m u:http:rwX -m u:$USER:rwX public/uploads public/img
sudo setfacl -R -d -m u:http:rwX -m u:$USER:rwX public/uploads public/img
```

Die zweite Zeile setzt die Default-ACL, damit auch spaeter erzeugte Dateien
passende Rechte bekommen.

**Beide Benutzer eintragen, nicht nur `http`.** Sonst gehoeren die vom
Webserver erzeugten Dateien dem Benutzer `http`, und der eigene Benutzer faellt
auf `other` zurueck – Lesen ja, Ueberschreiben nein. Das faellt erst auf, wenn
man dieselbe Datei einmal ausserhalb des Browsers erzeugen will:

```
file_put_contents(.../maschine_5_barcode.png): Failed to open stream: Permission denied
```

Die erzeugten Dateien selbst gehoeren nicht ins Repository – sie entstehen im
Betrieb. `.gitignore` schliesst den Inhalt von `public/uploads/` aus und behaelt
nur die `.gitkeep`-Dateien, damit die Verzeichnisse erhalten bleiben.

## 5. Stolpersteine (real aufgetreten)

**HTTP 403, obwohl die Rechte stimmen.**
`httpd.service` und `php-fpm.service` laufen unter Arch mit `ProtectHome=on`.
Damit ist `/home` fuer die Dienste unsichtbar, egal wie die Dateirechte
aussehen. Im Log steht dann:

```
AH00035: access to /zeiterfassung/ denied (filesystem path '/home/manuel')
because search permissions are missing on a component of the path
```

Loesung: systemd-Drop-in mit `ProtectHome=false` fuer beide Dienste
(macht das Setup-Skript). Alternative waere, das Projekt nach `/srv/http` zu
legen – dann liegt die Git-Arbeitskopie aber ausserhalb des Home-Verzeichnisses.

**Paketkonflikt `mariadb-libs` vs. `mariadb-lts-libs`.**
Wenn `mariadb-libs` (Nicht-LTS) schon installiert ist, bricht die Installation
von `mariadb-lts` ab. Ist das Paket ein Orphan (`Benoetigt von: Nichts`), kann
es gefahrlos entfernt werden; sonst stattdessen das Paket `mariadb` verwenden.

**phpMyAdmin meldet ein fehlendes `blowfish_secret`.**
Ohne gesetztes Secret verweigert phpMyAdmin den Cookie-Login. Das Skript
erzeugt eines und traegt es in `/etc/webapps/phpmyadmin/config.inc.php` ein.

## 6. Erste Anmeldung

Das Schema legt Rollen und Rechte an, aber keinen Benutzer. Beim ersten Aufruf
von `http://localhost/zeiterfassung` erscheint deshalb die Maske
**Erstinstallation**, in der der erste Administrator angelegt wird
(`views/login/initial_admin.php`). Danach laeuft der normale Login.

## 6a. Echten Datenbestand einspielen (Server-Dump)

Zum Entwickeln ist ein realistischer Datenbestand oft wertvoller als eine leere
Datenbank – etwa fuer Monatsuebersichten, PDFs und Saldenberechnungen. Ein
phpMyAdmin-Export vom Server laesst sich direkt einspielen:

```bash
# 1. Aktuellen lokalen Stand sichern (dauert Sekunden, erspart Aerger)
mariadb-dump -h 127.0.0.1 -u zeiterfassung -pzeiterfassung --databases zeiterfassung \
  > ~/zeiterfassung_lokal_backup.sql

# 2. Datenbank leeren (phpMyAdmin-Exporte enthalten kein DROP TABLE)
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung -e \
  "DROP DATABASE zeiterfassung; CREATE DATABASE zeiterfassung
   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Dump einspielen
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung zeiterfassung < dump.sql
```

Dasselbe geht in phpMyAdmin unter *Importieren*; bei grossen Dumps ist die
Kommandozeile schneller und laeuft nicht in Upload-Limits.

**Vorher pruefen, ob das Schema auseinanderlaeuft.** Ein Server-Dump zeigt den
echten Produktivstand – Abweichungen zu `sql/01_initial_schema.sql` sind ein
Befund, kein Detail. Vergleich ohne Risiko fuer die Arbeits-Datenbank:

```bash
# Dump und Repo-Schema in zwei Wegwerf-Datenbanken laden und Spalten vergleichen
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung -e \
  "CREATE DATABASE zf_check_prod; CREATE DATABASE zf_check_repo;"
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung zf_check_prod < dump.sql
sed -e '/^CREATE DATABASE/,+2d' -e '/^USE `zeiterfassung`;/d' sql/01_initial_schema.sql \
  | mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung zf_check_repo

mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung -N -B -e "
  SELECT CONCAT(p.table_name,'.',p.column_name,' nur in PROD')
    FROM information_schema.columns p
   WHERE p.table_schema='zf_check_prod'
     AND NOT EXISTS (SELECT 1 FROM information_schema.columns r
                      WHERE r.table_schema='zf_check_repo'
                        AND r.table_name=p.table_name
                        AND r.column_name=p.column_name);"

mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung -e \
  "DROP DATABASE zf_check_prod; DROP DATABASE zf_check_repo;"
```

**Solche Dumps gehoeren niemals ins Repository.** Sie enthalten
personenbezogene Daten – Klarnamen, E-Mail-Adressen, Geburtsdaten,
RFID-Codes und Passwort-Hashes –, und das Repository ist oeffentlich. Der
Datenbestand bleibt lokal; im Repo steht ausschliesslich das Schema mit den
technischen Startwerten (Rollen, Rechte, Pausenfenster).

## 7. Taeglicher Betrieb

```bash
# Dienste
sudo systemctl status  httpd php-fpm mariadb
sudo systemctl restart httpd php-fpm

# Logs
sudo tail -f /var/log/httpd/error_log      # Apache + PHP-Fehler der Seite
sudo journalctl -u php-fpm -f              # php-fpm (loggt nach syslog)

# Datenbank sichern / einspielen
mariadb-dump -h 127.0.0.1 -u zeiterfassung -p zeiterfassung > dump.sql
mariadb      -h 127.0.0.1 -u zeiterfassung -p zeiterfassung < dump.sql
```

Dasselbe geht komfortabel in phpMyAdmin unter *Exportieren* / *Importieren*.

Nach Aenderungen an `config/config.local.php` oder an PHP-Einstellungen:
`sudo systemctl restart php-fpm` (OPcache).

## 8. Bewusste Kompromisse dieser Umgebung

Diese Punkte sind **Absicht** und gehoeren so nicht auf einen Produktivserver:

- Der DB-Benutzer hat globale Rechte (`ON *.*`), damit phpMyAdmin frei
  importieren, exportieren und anlegen kann. Produktiv: Rechte nur auf
  `zeiterfassung` und `zeiterfassung_offline`.
- Passwort = Benutzername (`zeiterfassung`), so wie im Master-Prompt als
  Standard hinterlegt.
- `ProtectHome=false` fuer Apache und php-fpm.
- `debug = true` in der lokalen Konfiguration.
- Kein HTTPS.

## 9. Verwandte Dokumente

- [`docs/installationsanleitung.md`](installationsanleitung.md) – Produktivsetup
- [`docs/master_prompt_zeiterfassung_v13.md`](master_prompt_zeiterfassung_v13.md) – Projektregeln
- [`docs/wartungscheckliste.md`](wartungscheckliste.md) – was vor/nach Aenderungen zu pruefen ist
- [`docs/rfid_reader_setup.md`](rfid_reader_setup.md) – RFID-Leser am Terminal
