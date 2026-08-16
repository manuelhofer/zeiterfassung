# Lokale Entwicklungsumgebung

Diese Anleitung beschreibt, wie das Projekt auf einem Entwicklungsrechner zum
Laufen gebracht wird, so dass die App im Browser unter
`http://localhost/zeiterfassung` erreichbar ist und Änderungen am Code sofort
sichtbar werden.

**Abgrenzung:** Für die Produktivinstallation (Debian/Apache auf dem Server,
Terminal auf dem Raspberry Pi) gilt weiterhin
[`docs/installationsanleitung.md`](installationsanleitung.md). Die hier
beschriebene Umgebung ist bewusst bequemer und dafür weniger streng
abgesichert – sie gehört nicht ins Internet.

---

## 1. Zielbild

| Was | URL / Pfad |
| --- | --- |
| Backend (Hauptsystem) | `http://localhost/zeiterfassung` |
| Terminal (Kiosk-UI) | `http://localhost/zeiterfassung/terminal.php` |
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Code / Git-Arbeitskopie | das geklonte Verzeichnis, z. B. `~/zeiterfassunglocal` |

Der Webserver zeigt per `Alias` direkt in die Git-Arbeitskopie. Es wird nichts
kopiert oder deployt: Datei speichern, Seite neu laden, fertig – mit einer
Einschränkung, die der OPcache macht (Abschnitt 5).

## 2. Komponenten

Bewusst **native Installation, kein Docker** – so schreibt es der Master-Prompt
vor, und es entspricht dem Zielsystem (Debian-Server, Raspberry Pi).

- **Apache 2.4** – Webserver, liefert `public/` aus
- **php-fpm** – PHP als eigener Dienst, per FastCGI an Apache angebunden
- **MariaDB (LTS)** – Datenbank; die LTS-Linie entspricht der Version, die
  Debian 13 / Raspberry Pi OS mitbringen
- **phpMyAdmin** – komfortabler DB-Zugriff (Dump ziehen, SQL testen)

Benötigte PHP-Erweiterungen: `pdo_mysql` (App), `mysqli` (phpMyAdmin),
`mbstring`, `gd` (QR-Codes und Barcodes), `iconv`.

## 3. Schnellstart auf Arch Linux / CachyOS

```bash
sudo bash scripts/dev/setup_lokale_umgebung_arch.sh
```

Das Skript ist idempotent und macht alles aus Abschnitt 4 automatisch. Danach
`http://localhost/zeiterfassung` aufrufen und den Erst-Admin anlegen
(Abschnitt 6).

Für andere Distributionen dient das Skript als Vorlage – die Schritte sind
identisch, nur Paketnamen und Pfade unterscheiden sich (siehe Abschnitt 4).

## 4. Was dabei passiert (und wie es auf Debian aussieht)

### 4.1 Pakete

| Zweck | Arch / CachyOS | Debian / Ubuntu |
| --- | --- | --- |
| Webserver | `apache` | `apache2` |
| PHP | `php php-fpm php-gd` | `php php-fpm php-mysql php-gd php-mbstring` |
| Datenbank | `mariadb-lts` | `mariadb-server` |
| DB-Oberfläche | `phpmyadmin` | `phpmyadmin` |

Unter Arch sind PHP-Erweiterungen zwar mitinstalliert, aber in der `php.ini`
deaktiviert. Statt in der `php.ini` zu editieren, legt das Skript eine eigene
Datei `/etc/php/conf.d/99-zeiterfassung-dev.ini` an (Zeitzone + `extension=`
Zeilen). Debian aktiviert die Module beim Paketinstall selbst.

### 4.2 Apache

Konfiguration liegt in `/etc/httpd/conf/extra/zeiterfassung-dev.conf`
(Debian: `/etc/apache2/sites-available/…`), eingebunden über `httpd.conf`:

- `.php` wird per `SetHandler proxy:unix:/run/php-fpm/php-fpm.sock` an php-fpm
  durchgereicht (Module `proxy` und `proxy_fcgi` müssen aktiv sein)
- `Alias /zeiterfassung` zeigt auf `public/` – nicht auf die Projektwurzel
- `Alias /phpmyadmin` zeigt auf die phpMyAdmin-Installation

### 4.3 Datenbank

Angelegt werden zwei Datenbanken und der Benutzer aus dem Master-Prompt:

| Objekt | Wert |
| --- | --- |
| Hauptdatenbank | `zeiterfassung` |
| Offline-Datenbank | `zeiterfassung_offline` |
| Benutzer / Passwort | `zeiterfassung` / `zeiterfassung` |

Anschließend werden `sql/01_initial_schema.sql` und
`sql/offline_db_schema.sql` importiert (nur wenn die DB noch leer ist).

Der Benutzer wird für `localhost` **und** `127.0.0.1` angelegt: MariaDB
unterscheidet die beiden: `localhost` bedeutet Unix-Socket, `127.0.0.1`
bedeutet TCP. Die App verbindet per TCP, phpMyAdmin per Socket.

**Stolperstein beim Kopplungstest auf demselben Rechner.** Die Kopplung legt
den Datenbankbenutzer eines Terminals mit Host `%` an – ein Hallengerät ist ja
eine andere Maschine. Eine frische MariaDB bringt aber einen **anonymen
Benutzer** `''@localhost` mit, und der ist spezifischer als `%`. Eine
Verbindung von genau diesem Rechner läuft deshalb gegen den anonymen Eintrag
und scheitert mit „Access denied" – obwohl Benutzer, Passwort und Rechte
stimmen. Prüfen mit:

```bash
mariadb -u root -N -B -e "SELECT User, Host FROM mysql.user WHERE User = ''"
```

Wer lokal wirklich als Terminal-Benutzer verbinden will, entfernt den anonymen
Eintrag (`DROP USER ''@'localhost';`) oder prüft die Rechte stattdessen über
`SHOW GRANTS` – das beantwortet dieselbe Frage ohne Anmeldung.

### 4.4 Konfiguration der App

`config/config.local.php` (nicht versioniert) mit:

- `base_url` = `/zeiterfassung` – passend zum Apache-Alias
- `debug` = `true` – lokal sollen Fehler sichtbar sein
- DB-Zugangsdaten wie oben
- `terminal.rfid_ws.enabled` = `false` – lokal läuft kein RFID-Bridge-Dienst,
  sonst wartet die Terminal-UI dauerhaft auf einen toten WebSocket

Vorlage: `config/config.php.example`.

### 4.5 Dateirechte

Der Code gehört dem angemeldeten Benutzer – das muss so bleiben, sonst wird
das Arbeiten mit Git mühsam. Der Webserver bekommt deshalb **nur** per ACL
Schreibrechte auf die Verzeichnisse, in die er wirklich schreibt:

```bash
sudo setfacl -R    -m u:http:rwX -m u:$USER:rwX public/uploads public/img
sudo setfacl -R -d -m u:http:rwX -m u:$USER:rwX public/uploads public/img
```

Die zweite Zeile setzt die Default-ACL, damit auch später erzeugte Dateien
passende Rechte bekommen.

**Beide Benutzer eintragen, nicht nur `http`.** Sonst gehören die vom
Webserver erzeugten Dateien dem Benutzer `http`, und der eigene Benutzer fällt
auf `other` zurück – Lesen ja, Überschreiben nein. Das fällt erst auf, wenn
man dieselbe Datei einmal außerhalb des Browsers erzeugen will:

```
file_put_contents(.../maschine_5_barcode.png): Failed to open stream: Permission denied
```

Die erzeugten Dateien selbst gehören nicht ins Repository – sie entstehen im
Betrieb. `.gitignore` schließt den Inhalt von `public/uploads/` aus und behält
nur die `.gitkeep`-Dateien, damit die Verzeichnisse erhalten bleiben.

## 5. Stolpersteine (real aufgetreten)

**HTTP 403, obwohl die Rechte stimmen.**
`httpd.service` und `php-fpm.service` laufen unter Arch mit `ProtectHome=on`.
Damit ist `/home` für die Dienste unsichtbar, egal wie die Dateirechte
aussehen. Im Log steht dann:

```
AH00035: access to /zeiterfassung/ denied (filesystem path '/home/manuel')
because search permissions are missing on a component of the path
```

Lösung: systemd-Drop-in mit `ProtectHome=false` für beide Dienste
(macht das Setup-Skript). Alternative wäre, das Projekt nach `/srv/http` zu
legen – dann liegt die Git-Arbeitskopie aber außerhalb des Home-Verzeichnisses.

**Paketkonflikt `mariadb-libs` vs. `mariadb-lts-libs`.**
Wenn `mariadb-libs` (Nicht-LTS) schon installiert ist, bricht die Installation
von `mariadb-lts` ab. Ist das Paket ein Orphan (`Benoetigt von: Nichts`), kann
es gefahrlos entfernt werden; sonst stattdessen das Paket `mariadb` verwenden.

**phpMyAdmin meldet ein fehlendes `blowfish_secret`.**
Ohne gesetztes Secret verweigert phpMyAdmin den Cookie-Login. Das Skript
erzeugt eines und trägt es in `/etc/webapps/phpmyadmin/config.inc.php` ein.

**Eine geänderte PHP-Datei wirkt bis zu drei Minuten lang nicht.**
Der OPcache prüft den Zeitstempel einer bereits übersetzten Datei nur alle
`opcache.revalidate_freq` Sekunden – in `/etc/php/php.ini` steht dort **180**.
Bis dahin liefern Apache/php-fpm und auch `php -S` den alten Stand, obwohl die
Datei längst gespeichert ist. Das Tückische daran ist nicht die Verzögerung,
sondern dass sie nicht immer auftritt: Dateien, die jünger als
`opcache.file_update_protection` (**2** Sekunden) sind, landen gar nicht erst
im Cache. Wer speichert und sofort neu lädt, sieht seine Änderung – wer
speichert, kurz nachdenkt und dann neu lädt, sieht sie nicht. Nachstellen:

```bash
printf '<?php echo "STAND-A";\n' > public/probe.php
sleep 4 && curl -s http://localhost/zeiterfassung/probe.php   # STAND-A
printf '<?php echo "STAND-B";\n' > public/probe.php
sleep 2 && curl -s http://localhost/zeiterfassung/probe.php   # weiterhin STAND-A
rm public/probe.php
```

Abhilfe im Browser: `sudo systemctl reload php-fpm`. Auf der Kommandozeile –
etwa wenn zwei Stände gegeneinander gerendert werden – gehört
`-d opcache.enable=0 -d opcache.enable_cli=0` an den Aufruf, sonst misst man
den Stand von vorhin. Gekostet hat das in P-2026-08-14-12 zwei komplette
Messreihen.

## 6. Erste Anmeldung

Das Schema legt Rollen und Rechte an, aber keinen Benutzer. Beim ersten Aufruf
von `http://localhost/zeiterfassung` erscheint deshalb die Maske
**Erstinstallation**, in der der erste Administrator angelegt wird
(`views/login/initial_admin.php`). Danach läuft der normale Login.

## 6a. Echten Datenbestand einspielen (Server-Dump)

Zum Entwickeln ist ein realistischer Datenbestand oft wertvoller als eine leere
Datenbank – etwa für Monatsübersichten, PDFs und Saldenberechnungen. Ein
phpMyAdmin-Export vom Server lässt sich direkt einspielen:

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

Dasselbe geht in phpMyAdmin unter *Importieren*; bei großen Dumps ist die
Kommandozeile schneller und läuft nicht in Upload-Limits.

**Vorher prüfen, ob das Schema auseinanderläuft.** Ein Server-Dump zeigt den
echten Produktivstand – Abweichungen zu `sql/01_initial_schema.sql` sind ein
Befund, kein Detail. Vergleich ohne Risiko für die Arbeits-Datenbank:

```bash
# Dump und Repo-Schema in zwei Wegwerf-Datenbanken laden und Spalten vergleichen
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung -e \
  "CREATE DATABASE zf_check_prod; CREATE DATABASE zf_check_repo;"
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung zf_check_prod < dump.sql
mariadb -h 127.0.0.1 -u zeiterfassung -pzeiterfassung zf_check_repo < sql/01_initial_schema.sql

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

**Solche Dumps gehören niemals ins Repository.** Sie enthalten
personenbezogene Daten – Klarnamen, E-Mail-Adressen, Geburtsdaten,
RFID-Codes und Passwort-Hashes –, und das Repository ist öffentlich. Der
Datenbestand bleibt lokal; im Repo steht ausschließlich das Schema mit den
technischen Startwerten (Rollen, Rechte, Pausenfenster).

## 6b. Was in dieser Datenbank künstlich ist (Stand 2026-08-08)

Damit sich niemand über Daten wundert, die nicht aus dem Betrieb stammen:

- **Zeitbuchungen:** 10.032, davon **360 künstlich ergänzt** (Kommentar
  `Import Altzeiten 2026 (lokal ergaenzt bis 08.08.2026)`), um den Bestand bis
  zum 07.08.2026 zu füllen. Mit einem `DELETE` über diesen Kommentar wieder
  entfernbar.
- **Aufträge:** 3, davon 2 Testaufträge (`A-2026-0815`, `A-2026-0999`) mit
  Arbeitsschritten – reine Anschauungsdaten.
- **Arbeitsschritt-Katalog:** 8 Beispieleinträge – die Codes `saegen`,
  `drehen`, `fraesen`, `bohren`, `schleifen`, `entgraten`, `montage`,
  `pruefen`. Als Startbestand brauchbar.
- **Terminals:** keine; keine offenen Kopplungen (Testdaten entfernt).
- Der eigentliche Datenbestand stammt aus dem Serverdump vom 2026-08-08 und
  enthält echte Personendaten – deshalb liegt er **nicht** im Repository.

## 6c. Zwei Stände gegeneinander rendern (Prüfumgebung)

Wer Markup verschiebt – Controller in ein Teil-Template –, muss belegen, dass
dieselbe Seite danach dasselbe HTML liefert. Dafür gibt es
`scripts/dev/pruefumgebung.sh`: zwei Kopien des Projekts (ein Commit und der
Arbeitsstand), **ein** Paar Probe-Datenbanken, an denen beide hängen, ein
erfundener Prüfbenutzer und zwei `php -S` mit abgeschaltetem OPcache.

```bash
scripts/dev/pruefumgebung.sh aufbauen HEAD
scripts/dev/pruefumgebung.sh vergleichen '?seite=smoke_test'   # 0 = gleich
scripts/dev/pruefumgebung.sh spiegeln                          # nach jeder Änderung
scripts/dev/pruefumgebung.sh abraeumen                         # Pflicht, prüft sich nach
```

Die Entwicklungsdatenbank wird dabei nicht angefasst: Beide Kopien bekommen
eine eigene `config.local.php` auf `zeit_probe`/`zeit_probe_off`, und jeder
Datenbankname muss mit `zeit_probe` beginnen, sonst bricht das Skript ab.
Fachliche Probe-Daten bringt jeder Patch selbst mit (`daten <datei.sql>`) –
welche Kanten eine Maske hat, weiß nur, wer sie gerade anfasst. Alle Befehle
und Optionen stehen im Kopf des Skripts.

## 7. Täglicher Betrieb

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

Nach Änderungen an `config/config.local.php` oder an PHP-Einstellungen:
`sudo systemctl restart php-fpm` (OPcache).

## 8. Bewusste Kompromisse dieser Umgebung

Diese Punkte sind **Absicht** und gehören so nicht auf einen Produktivserver:

- Der DB-Benutzer hat globale Rechte (`ON *.*`), damit phpMyAdmin frei
  importieren, exportieren und anlegen kann. Produktiv: Rechte nur auf
  `zeiterfassung` und `zeiterfassung_offline`.
- Passwort = Benutzername (`zeiterfassung`), so wie im Master-Prompt als
  Standard hinterlegt.
- `ProtectHome=false` für Apache und php-fpm.
- `debug = true` in der lokalen Konfiguration.
- Kein HTTPS.

## 9. Verwandte Dokumente

- [`docs/installationsanleitung.md`](installationsanleitung.md) – Produktivsetup
- [`docs/arbeitsregeln.md`](arbeitsregeln.md) – Projektregeln (der Master-Prompt
  v13 liegt abgelöst in `docs/archiv/`)
- [`docs/wartungscheckliste.md`](wartungscheckliste.md) – was vor/nach Änderungen zu prüfen ist
- [`docs/rfid_reader_setup.md`](rfid_reader_setup.md) – RFID-Leser am Terminal
