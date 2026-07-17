# Zeiterfassung & Mitarbeiter-/Auftragsmanagement – Projektstruktur

Dieses Verzeichnis enthält Projektdokumentation, u. a. den Master-Prompt.
Der eigentliche PHP-Code befindet sich in den Verzeichnissen `core`, `controller`,
`modelle`, `services` und `views`.

## Verzeichnisse (Überblick)

- `public/` – Einstiegspunkte (z. B. `index.php`), CSS, JS
- `core/` – Basisfunktionen (Datenbank, Autoloader, Logging, Helper)
- `modelle/` – reine DB-Modelle (Mitarbeiter, Zeitbuchungen, Urlaubsanträge, …)
- `services/` – Geschäftslogik (ZeitService, UrlaubService, RundungsService, …)
- `controller/` – Request-Verarbeitung und Übergabe an Views
- `views/` – PHP-Views/HTML-Templates
- `sql/` – aktuelles Datenbankschema und optionale SQL-Hilfen
- `docs/` – Dokumentation, Master-Prompt, Notizen

## Konfiguration (Zugangsdaten)

Die Datei `config/config.php` enthält **keine** echten Zugangsdaten mehr. Lege
deine produktiven Werte stattdessen in `config/config.local.php` (nicht
versioniert) oder per Umgebungsvariablen ab. Eine Vorlage findest du in
`config/config.php.example`.
Es gibt bewusst **nur eine** Konfigurationsquelle im Projekt: `config/config.php`.

## Prompt-Archivierung

- Aktueller Kurzstatus: [`docs/STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md)
- Prompt-Uebersicht: [`docs/prompt_uebersicht.md`](prompt_uebersicht.md)
- Voller Verlauf: [`docs/archiv/DEV_PROMPT_HISTORY.md`](archiv/DEV_PROMPT_HISTORY.md)

Historische Prompts und der vollstaendige Verlauf liegen in `docs/archiv`.
Welche Prompt-Datei aktuell wofuer relevant ist, steht in der
Prompt-Uebersicht.

Weitere alte Spezifikationen liegen ebenfalls im Archivordner `docs/archiv`.

## Weitere Dokumentation

- Admin-Handbuch: [`docs/admin_handbuch.md`](admin_handbuch.md)
- Installationsanleitung: [`docs/installationsanleitung.md`](installationsanleitung.md)
- Wartungscheckliste: [`docs/wartungscheckliste.md`](wartungscheckliste.md)
- RFID-Reader-Setup: [`docs/rfid_reader_setup.md`](rfid_reader_setup.md)
- Git-Wiki & Repo-Beschreibung: [`docs/git_wiki_und_beschreibung.md`](git_wiki_und_beschreibung.md)
