# Installationsanleitung

Diese Anleitung beschreibt eine vollständige, praxistaugliche Installation des
Zeiterfassungs-Backends. Sie enthält die notwendigen Schritte von den
Voraussetzungen bis zur ersten erfolgreichen Anmeldung. Für
Terminal-spezifische Setups (RFID, Offline-DB) gibt es zusätzliche Hinweise.

Nur zum Ausprobieren oder Entwickeln auf dem eigenen Rechner? Dann ist
`docs/lokale_entwicklungsumgebung.md` der schnellere Weg – diese Anleitung hier
beschreibt die Installation auf einem Server.

## Voraussetzungen

- PHP mit Erweiterungen: `pdo`, `pdo_mysql`, `mbstring`, `json`, `gd`
  (`gd` wird für QR-Codes und Barcodes gebraucht)
  - **Mindestversion: PHP 8.2** (Debian 12 / Raspberry Pi OS Bookworm)
  - Neuere PHP-Versionen werden unterstützt; entwickelt und getestet wird
    gegen die jeweils aktuelle Version. Details zur Versionsbaseline:
    `docs/arbeitsregeln.md`, Abschnitt 7.
- Webserver (Apache oder Nginx)
- MySQL oder MariaDB (empfohlen: die LTS-Linie von MariaDB)
- Git (für den Klon)

## 1) Projekt holen

```bash
git clone <REPO-URL> zeiterfassung
cd zeiterfassung
```

## 2) Datenbank anlegen

1. Lege eine leere Datenbank `zeiterfassung` an.
2. Lege einen Datenbank-Benutzer an (oder verwende einen vorhandenen) und
   erteile die nötigen Rechte.
3. Importiere das Schema:

```bash
mysql -u <USER> -p zeiterfassung < sql/01_initial_schema.sql
```

Weitere Details zum Schema findest du in `sql/README.md`.

## 3) Konfiguration setzen

Lege die produktiven Zugangsdaten in `config/config.local.php` ab (nicht
versioniert). Starte mit der Vorlage:

```bash
cp config/config.php.example config/config.local.php
```

Passe anschließend mindestens die Datenbank-Zugangsdaten sowie `base_url` an.
Alternativ kannst du die Werte über Umgebungsvariablen setzen (siehe
`config/config.php`).

## 4) Dateirechte prüfen

Stelle sicher, dass der Webserver auf das Projektverzeichnis zugreifen darf.
In der Regel reicht Leserechte für den Code und Schreibrechte für
`public/uploads/`, falls Uploads genutzt werden.

Beispiel (Besitzer/Gruppe anpassen):

```bash
sudo chown -R www-data:www-data /pfad/zur/zeiterfassung
sudo chmod -R u=rwX,g=rX,o= /pfad/zur/zeiterfassung
sudo chmod -R u=rwX,g=rwX,o= /pfad/zur/zeiterfassung/public/uploads
```

## 5) Webserver konfigurieren

Der Document-Root muss auf `public/` zeigen:

```
/pfad/zur/zeiterfassung/public
```

### Apache (vHost-Beispiel)

```apache
<VirtualHost *:80>
    ServerName example.org
    DocumentRoot /pfad/zur/zeiterfassung/public

    <Directory /pfad/zur/zeiterfassung/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx (Server-Block-Beispiel)

```nginx
server {
    listen 80;
    server_name example.org;
    root /pfad/zur/zeiterfassung/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

## 6) Start & Test

Rufe im Browser die Basis-URL auf (z. B. `https://example.org/zeiterfassung`).
Wenn die Seite leer bleibt oder Fehler zeigt, prüfe:

- PHP-Fehler-Logs des Webservers
- Konfiguration in `config/config.local.php`
- Datenbank-Zugangsdaten

Für wiederholbare technische und manuelle Prüfungen siehe
`docs/wartungscheckliste.md`.

## 7) Terminal-Installation (optional)

**Ein Hallenterminal wird nicht nach dieser Anleitung aufgesetzt**, sondern mit
zwei eigenen Skripten:

```bash
sudo ./scripts/terminal/install_terminal.sh    # Grundsystem
sudo ./scripts/terminal/install_kiosk.sh       # Vollbildbrowser beim Start
sudo ./scripts/terminal/install_peripherie.sh  # RFID-Leser, Touchscreen
sudo ./scripts/terminal/selbsttest.sh          # ist das Gerät einsatzbereit?
```

Alle vier lesen dieselbe Antwortdatei (`scripts/terminal/terminal.conf`,
Vorlage daneben) und dürfen mehrfach laufen. Der Selbsttest ändert nichts und
liefert am Ende eine Liste mit OK/FEHLT – vor dem Verlassen des Geräts.

Das erste richtet Pakete, Code, Webserver und die lokale Ausweichdatenbank ein
und legt
`config/config.local.php` bewusst **nicht** an. Das Gerät startet
unkonfiguriert, zeigt seine Einrichtungsseite und holt sich Server-Adresse,
Terminal-ID und Zugangsdaten über einen Kopplungscode aus dem Backend – dabei
setzt es `installation_typ` selbst.

Diese Datei also **nicht von Hand anlegen**: Damit verschwindet die
Einrichtungsseite, das Gerät bekommt keinen eigenen Datenbankbenutzer, und im
Verlustfall lässt es sich nicht einzeln sperren.

Einzelheiten: [Terminal-Installation](spezifikation_terminal_installation.md).

Für RFID/Offline-Setup siehe zusätzlich:

- `docs/rfid_reader_setup.md`
- `docs/terminal/rfid-ws_rollout.md`
