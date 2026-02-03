# Installationsanleitung (Kurzfassung)

Diese Anleitung beschreibt eine **einfache** Standard-Installation für das
Zeiterfassungs-Backend. Für Terminal-spezifische Setups (RFID, Offline-DB)
gibt es zusätzliche Hinweise unten.

## Voraussetzungen

- PHP (empfohlen: 8.1+)
- Webserver (Apache oder Nginx)
- MySQL oder MariaDB
- Git (für den Klon)

## 1) Projekt holen

```bash
git clone <REPO-URL> zeiterfassung
cd zeiterfassung
```

## 2) Datenbank anlegen

1. Lege eine leere Datenbank `zeiterfassung` an.
2. Importiere das Schema:

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

## 4) Webserver konfigurieren

Der Document-Root sollte auf `public/` zeigen:

```
/pfad/zur/zeiterfassung/public
```

Beispiele:

- Apache: `DocumentRoot /pfad/zur/zeiterfassung/public`
- Nginx: `root /pfad/zur/zeiterfassung/public;`

## 5) Start & Test

Rufe im Browser die Basis-URL auf (z. B. `https://example.org/zeiterfassung`).

## Terminal-Installation (optional)

Wenn das System als Terminal läuft, setze in `config/config.local.php`:

```php
'app' => [
    'installation_typ' => 'terminal',
],
```

Für RFID/Offline-Setup siehe zusätzlich:

- `docs/rfid_reader_setup.md`
- `docs/terminal/rfid-ws_rollout.md`

