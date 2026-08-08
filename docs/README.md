# Dokumentation – Übersicht

Dieses Verzeichnis enthält die Projektdokumentation. Der Einstieg für alle, die
das Repository frisch geklont haben, ist die [`README.md`](../README.md) in der
Projektwurzel.

## Regeln und Projektstand

- **[Master-Prompt v13](master_prompt_zeiterfassung_v13.md)** – das verbindliche
  Regelwerk: Arbeitsweise, Architektur, vollständige Fachlogik. Bei Widerspruch
  zu älteren Texten gilt diese Datei.
- [Status-Snapshot](STATUS_SNAPSHOT.md) – aktueller Stand in Kurzform.
- [Rechte-Prompt](rechte_prompt.md) – Source of Truth für alle Rechte-Codes.
- [Prompt-Übersicht](prompt_uebersicht.md) – welche Prompt-Datei wofür gilt.
- [Spezifikation Aufträge & Laufkarte](spezifikation_auftrag_barcode_laufkarte.md) –
  Aufträge im Backend anlegen, Arbeitsschritte und Katalog mit Strichcodes,
  Laufkarte und Kartenblatt als PDF.
- [Voller Verlauf](archiv/DEV_PROMPT_HISTORY.md) – Projekthistorie, wird bei
  jedem Patch gepflegt.

## Installation und Betrieb

- [Lokale Entwicklungsumgebung](lokale_entwicklungsumgebung.md) – App unter
  `http://localhost/zeiterfassung` (Apache + php-fpm + MariaDB + phpMyAdmin).
- [Installationsanleitung](installationsanleitung.md) – Produktivsetup auf
  Debian/Apache.
- [RFID-Reader-Setup](rfid_reader_setup.md) – Leser am Terminal.
- [Wartungscheckliste](wartungscheckliste.md) – was vor und nach Änderungen zu
  prüfen ist.
- [Admin-Handbuch](admin_handbuch.md) – Bedienung im Backend.
- [Git-Wiki & Repo-Beschreibung](git_wiki_und_beschreibung.md)

## Archiv

Historische Prompts und Spezifikationen liegen in [`archiv/`](archiv/).
Was dort warum liegt und was davon noch gilt, steht in
[`archiv/ALTE_PROMPTS.md`](archiv/ALTE_PROMPTS.md). Archivierte Dateien sind
Referenzmaterial, keine gültige Arbeitsanweisung.

## Konfiguration (Zugangsdaten)

`config/config.php` enthält **keine** echten Zugangsdaten. Produktive Werte
gehören nach `config/config.local.php` (nicht versioniert) oder in
Umgebungsvariablen. Vorlage: `config/config.php.example`. Es gibt bewusst nur
**eine** Konfigurationsquelle im Projekt.
