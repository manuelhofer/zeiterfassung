# Dokumentation – Übersicht

Welche Datei ist wofür zuständig? Diese Seite ist das **Verzeichnis**.

Wer *arbeiten* will, fängt nicht hier an, sondern bei
[`CHATSTART.md`](../CHATSTART.md) in der Projektwurzel: Dort steht die
Lesekarte, welche Datei zu welcher **Aufgabe** gehört – die Auswahl nach
Aufgabe ist beim Arbeiten die nützlichere.

## Immer

| Datei | Wofür |
| --- | --- |
| [`CHATSTART.md`](../CHATSTART.md) | Einstieg für jede KI und jedes Werkzeug: Projekt in Kurzform, Regeln in Kurzform, Lesekarte |
| [`arbeitsregeln.md`](arbeitsregeln.md) | **Verbindlich für jede Änderung:** Patch-Zuschnitt, Patch-ID, Pre-Flight-Gate, Pflichtprüfungen, Code-Stil, PHP-Baseline |
| [`STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md) | **Der ganze aktuelle Stand:** nächster Schritt, offene Bugs, offene Tasks |

Dazu `git log --oneline` – welche Patches es gab, wird bewusst nirgends von
Hand gepflegt.

## Nach Thema

| Datei | Wofür |
| --- | --- |
| [`fachregeln/`](fachregeln/) | Die Fachlogik, nach Thema getrennt. Nur die passende Datei lesen, nicht alle |
| [`rechte_prompt.md`](rechte_prompt.md) | Source of Truth für Rechte-Codes und ihre Prüfpunkte |
| [`spezifikation_auftrag_barcode_laufkarte.md`](spezifikation_auftrag_barcode_laufkarte.md) | Aufträge, Arbeitsschritte, Katalog, Strichcodes, Laufkarte (umgesetzt) |
| [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md) | Terminal per Skript aufsetzen und koppeln. Welche Stufe fertig ist, steht dort im Stufenplan – bewusst nur an dieser einen Stelle |

## Installation und Betrieb

| Datei | Wofür |
| --- | --- |
| [`lokale_entwicklungsumgebung.md`](lokale_entwicklungsumgebung.md) | Lokale Umgebung (Apache + php-fpm + MariaDB + phpMyAdmin), App unter `http://localhost/zeiterfassung` |
| [`installationsanleitung.md`](installationsanleitung.md) | Produktivinstallation auf Debian/Apache |
| [`rfid_reader_setup.md`](rfid_reader_setup.md) | RFID-Leser am Terminal |
| [`wartungscheckliste.md`](wartungscheckliste.md) | Was vor und nach Änderungen zu prüfen ist |
| [`admin_handbuch.md`](admin_handbuch.md) | Bedienung im Backend |
| [`git_wiki_und_beschreibung.md`](git_wiki_und_beschreibung.md) | Repo-Beschreibung und Wiki-Texte |

## Verlauf und Archiv

- [`archiv/DEV_PROMPT_HISTORY.md`](archiv/DEV_PROMPT_HISTORY.md) – der volle
  Projektverlauf, ein Eintrag je Patch, wird bei **jedem** Patch gepflegt.
  **Keine Startlektüre:** Über 12.000 Zeilen, und der Stand steht im
  Status-Snapshot. Gezielt aufschlagen, wenn zu einem Patch die Begründung oder
  der Test gesucht wird.
- [`archiv/ALTE_PROMPTS.md`](archiv/ALTE_PROMPTS.md) – **pro Datei begründet:**
  was sie war, wann und warum sie archiviert wurde, was davon noch gilt. Der
  richtige Einstieg in den Archivordner.
- [`archiv/`](archiv/) – historische Prompts und Spezifikationen. Referenz­material,
  keine gültige Arbeitsanweisung. Der abgelöste Master-Prompt v13 liegt dort;
  sein Inhalt steckt heute in `arbeitsregeln.md` und `fachregeln/`.

## Konfiguration (Zugangsdaten)

`config/config.php` enthält **keine** echten Zugangsdaten. Produktive Werte
gehören nach `config/config.local.php` (nicht versioniert) oder in
Umgebungsvariablen. Vorlage: `config/config.php.example`. Es gibt bewusst nur
**eine** Konfigurationsquelle im Projekt.
