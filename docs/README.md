# Zeiterfassung & Mitarbeiter-/Auftragsmanagement – Projektstruktur (Skeleton)

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
- `sql/` – Datenbankschemata und Migrationen
- `docs/` – Dokumentation, Master-Prompt, Notizen

## Konfiguration (Zugangsdaten)

Die Datei `config/config.php` enthält **keine** echten Zugangsdaten mehr. Lege
deine produktiven Werte stattdessen in `config/config.local.php` (nicht
versioniert) oder per Umgebungsvariablen ab. Eine Vorlage findest du in
`config/config.php.example`.

## Prompt-Archivierung

- Aktueller Kurzstatus: [`docs/STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md)
- Voller Verlauf: [`docs/archiv/DEV_PROMPT_HISTORY.md`](archiv/DEV_PROMPT_HISTORY.md)

Historische Prompts liegen künftig nur in `docs/archiv` und werden dort bewusst
kurz gehalten: Es bleiben pro Prompt nur die letzten ein bis zwei Versionen
(typisch: aktuelle Version im Wurzelverzeichnis `docs/` und die direkt
vorherige Version in `docs/archiv`). Ältere Stände werden entfernt oder in ein
externes Archiv (separates Repo oder Release-Artefakt) ausgelagert.
