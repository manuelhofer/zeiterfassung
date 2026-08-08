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
sudo setfacl -R    -m u:http:rwX public/uploads public/img
sudo setfacl -R -d -m u:http:rwX public/uploads public/img
```

Die zweite Zeile setzt die Default-ACL, damit auch spaeter erzeugte Dateien
(z. B. generierte QR-Code-Bilder) fuer beide Seiten nutzbar bleiben.

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
