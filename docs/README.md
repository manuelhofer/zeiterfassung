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
| [`spezifikation_abteilungsrechte.md`](spezifikation_abteilungsrechte.md) | Urlaubsgenehmigung je Abteilung: Zielbild, Grenzen, was beim Aufsetzen einer Installation zu bedenken ist (umgesetzt) |
| [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md) | Terminal per Skript aufsetzen und koppeln. Welche Stufe fertig ist, steht dort im Stufenplan – bewusst nur an dieser einen Stelle |
| [`spezifikation_fachlogik_pruefskript.md`](spezifikation_fachlogik_pruefskript.md) | Das nachrechnende Prüfskript für Rundung, Pausen, Salden und die Fachprüfungen aus dem Smoke-Test (T-140, noch nicht gebaut) |

## Installation und Betrieb

| Datei | Wofür |
| --- | --- |
| [`lokale_entwicklungsumgebung.md`](lokale_entwicklungsumgebung.md) | Lokale Umgebung (Apache + php-fpm + MariaDB + phpMyAdmin), App unter `http://localhost/zeiterfassung` |
| [`installationsanleitung.md`](installationsanleitung.md) | Produktivinstallation auf Debian/Apache |
| [`rfid_reader_setup.md`](rfid_reader_setup.md) | RFID-Leser am Terminal – **von Hand**; der Normalweg ist `scripts/terminal/install_peripherie.sh` |
| [`terminal/rfid-ws_rollout.md`](terminal/rfid-ws_rollout.md) | WebSocket-Bridge von Hand aufsetzen, samt Vorlagen (`rfid_ws.py`, `rfid-ws.service`) |
| [`wartungscheckliste.md`](wartungscheckliste.md) | Was vor und nach Änderungen zu prüfen ist |
| [`admin_handbuch.md`](admin_handbuch.md) | Bedienung im Backend |
| [`git_wiki_und_beschreibung.md`](git_wiki_und_beschreibung.md) | Repo-Beschreibung und Wiki-Texte |

## Verlauf und Archiv

- [`archiv/DEV_PROMPT_HISTORY.md`](archiv/DEV_PROMPT_HISTORY.md) – der volle
  Projektverlauf, ein Eintrag je Patch, wird bei **jedem** Patch gepflegt.
  **Keine Startlektüre:** Sie wächst mit jedem Patch, und der Stand steht im
  Status-Snapshot. Gezielt aufschlagen, wenn zu einem Patch die Begründung oder
  der Test gesucht wird.
- [`archiv/ALTE_PROMPTS.md`](archiv/ALTE_PROMPTS.md) – **pro Datei begründet:**
  was sie war, wann und warum sie archiviert wurde, was davon noch gilt. Der
  richtige Einstieg in den Archivordner.
- [`archiv/`](archiv/) – historische Prompts und Spezifikationen. Referenz­material,
  keine gültige Arbeitsanweisung. Der abgelöste Master-Prompt v13 liegt dort;
  sein Inhalt steckt heute in `arbeitsregeln.md` und `fachregeln/`.

## Warum die Doku so aufgeteilt ist

Früher stand alles in einem einzigen Master-Prompt (~36.000 Token). Wer nur
einen Tippfehler im Terminal beheben wollte, las zwangsläufig auch Pausenregeln,
PDF-Spalten und Genehmigerlogik – teuer und unübersichtlich.

Jetzt ist nach **Lesehäufigkeit** getrennt: Arbeitsregeln und Status immer,
Fachregeln nach Bedarf, Historie nur auf Nachfrage. Doppelt gepflegte Listen
wurden gestrichen, weil sie auseinanderdriften – und dann weiß niemand mehr,
welche Fassung gilt.

Deshalb gilt: **Wenn du eine Regel änderst, ändere sie an genau einer Stelle.**
Findest du dieselbe Aussage zweimal, ist das ein Fehler – melde ihn.

## Konfiguration (Zugangsdaten)

`config/config.php` enthält **keine** echten Zugangsdaten. Produktive Werte
gehören nach `config/config.local.php` (nicht versioniert) oder in
Umgebungsvariablen. Vorlage: `config/config.php.example`. Es gibt bewusst nur
**eine** Konfigurationsquelle im Projekt.
