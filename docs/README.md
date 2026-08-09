# Dokumentation – Übersicht

Dieses Verzeichnis enthält die Projektdokumentation. Der Einstieg für alle, die
das Repository frisch geklont haben, ist [`CHATSTART.md`](../CHATSTART.md) in
der Projektwurzel – dort steht auch die Lesekarte, welche Datei zu welcher
Aufgabe gehört.

## Regeln und Projektstand

- **[Arbeitsregeln](arbeitsregeln.md)** – wie gearbeitet wird: Patch-Zuschnitt,
  Patch-ID, Pre-Flight-Gate, Pflichtprüfungen, Code-Stil, PHP-Baseline.
- **[Fachregeln](fachregeln/)** – die Fachlogik, nach Thema getrennt. Nur die
  passende Datei lesen, nicht alle.
- [Status-Snapshot](STATUS_SNAPSHOT.md) – aktueller Stand und nächster Schritt.
- [Rechte-Prompt](rechte_prompt.md) – Source of Truth für alle Rechte-Codes.
- [Prompt-Übersicht](prompt_uebersicht.md) – welche Datei wofür gilt.
- [Spezifikation Aufträge & Laufkarte](spezifikation_auftrag_barcode_laufkarte.md) –
  Aufträge im Backend anlegen, Arbeitsschritte und Katalog mit Strichcodes,
  Laufkarte und Kartenblatt als PDF.
- [Spezifikation Terminal-Installation](spezifikation_terminal_installation.md) –
  Entwurf: Terminal per Skript aufsetzen (noch nicht umgesetzt).
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
