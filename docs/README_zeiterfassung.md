# Zeiterfassung und Mitarbeiter-/Auftragsmanagement

Dieses Projekt enthält eine PHP-basierte Webanwendung zur Zeiterfassung,
Urlaubsverwaltung und optionalen Auftragszeiterfassung.

Verzeichnisse (Überblick):

- `public/`   – Einstiegspunkte (z. B. `index.php`, später evtl. Terminal-UI), CSS, JS
- `core/`     – Basisfunktionen (Datenbank, Autoloader, Logging, Helper)
- `modelle/`  – reine DB-Modelle (Mitarbeiter, Zeitbuchungen, Urlaubsanträge, …)
- `services/` – Geschäftslogik (ZeitService, UrlaubService, RundungsService, …)
- `controller/` – Request-Verarbeitung und Übergabe an Views
- `views/`    – PHP-Views/HTML-Templates
- `sql/`      – Datenbankschemata und Migrationen
- `docs/`     – Dokumentation, Master-Prompt, Notizen

Diese Datei dient nur als kurze Orientierung und kann bei Bedarf erweitert werden.
