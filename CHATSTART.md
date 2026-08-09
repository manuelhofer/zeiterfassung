# Einstieg fuer KI-Assistenten

Diese Datei ist der **Startpunkt fuer jede KI und jedes Werkzeug**, das an
diesem Projekt arbeitet – Claude Code, Cursor, Copilot, Aider oder ein
beliebiger Chat. Sie ist bewusst kurz und werkzeugneutral.

**Lies diese Datei ganz. Danach lies nur das, was zu deiner Aufgabe passt –
nicht alles.**

---

## 1. Was das Projekt ist

Webbasierte **Zeiterfassung** fuer einen Handwerks-/Fertigungsbetrieb:
Mitarbeiter-, Rollen- und Genehmigerverwaltung, Urlaubsverwaltung,
Auftragszeiten per Barcode-Scan, Auswertungen als Monats-PDF – dazu eine
**Terminal-Oberflaeche (Kiosk)** fuer die Werkstatt mit RFID-Login und
Offline-Queue.

Reines PHP ohne grosses Framework, MariaDB/MySQL, Apache. Produktiv auf Debian;
Terminals laufen auch auf einem Raspberry Pi.

**Status: fertig, im Praxis-Test.** Gearbeitet wird nur bei Bugs oder auf
ausdrueckliche Beauftragung.

**Einstiegspunkte:**

- Backend: `public/index.php` (Routing ueber `?seite=…`)
- Terminal: `public/terminal.php` (Routing ueber `?aktion=…`)

**Zwei Installationstypen**, unterschieden ueber `app.installation_typ` in
`config/config.local.php`: `backend` (der Server) und `terminal` (Kiosk in der
Halle). Ein Terminal ohne Konfiguration zeigt automatisch seine
Einrichtungsseite und koppelt sich per Code am Backend an.

## 2. Die wichtigsten Regeln in Kurzform

Vollstaendig in **[docs/arbeitsregeln.md](docs/arbeitsregeln.md)** – lies die,
bevor du etwas aenderst.

- **1 Patch = 1 Thema** mit **einem** Akzeptanzkriterium in einem Satz.
- **Patch-ID im Commit-Betreff:** `P-YYYY-MM-DD-XX kurzbeschreibung-in-kebab-case`
- **`docs/archiv/DEV_PROMPT_HISTORY.md` im selben Commit pflegen.**
- **Vorher pruefen, ob es schon erledigt ist** – History und `git log`.
- **Nachher:** `php -l` ueber alle geaenderten Dateien, betroffene Abläufe aus
  `docs/wartungscheckliste.md` durchklicken.
- **Deutsch** in Oberflaeche, Variablennamen und Kommentaren.
- **PHP-Baseline:** mindestens 8.2, muss auf aktuellem PHP (8.5) warnungsfrei
  laufen.
- **Keine Refactors nebenbei.** Was auffaellt, wird notiert, nicht mitgemacht.
- **Gepusht wird nur auf ausdrueckliche Ansage.**

## 3. Lesekarte – was liest du wann?

**Immer, vor jeder Aenderung:**

| Datei | Wofuer |
| --- | --- |
| [docs/arbeitsregeln.md](docs/arbeitsregeln.md) | Wie gearbeitet wird |
| [docs/STATUS_SNAPSHOT.md](docs/STATUS_SNAPSHOT.md) | Aktueller Stand, offene Bugs, naechster Schritt |
| `git log --oneline -20` | Was zuletzt passiert ist – **statt** einer gepflegten Liste |

**Je nach Thema – nur das Passende:**

| Du arbeitest an … | Dann lies |
| --- | --- |
| Kommen/Gehen, Rundung, Pausen, Tageskorrekturen | [docs/fachregeln/zeit_rundung_pausen.md](docs/fachregeln/zeit_rundung_pausen.md) |
| Urlaub, Betriebsferien, Feiertage | [docs/fachregeln/urlaub_abwesenheit_feiertage.md](docs/fachregeln/urlaub_abwesenheit_feiertage.md) |
| Rollen, Rechte, Bereiche, Genehmiger | [docs/fachregeln/rollen_rechte_genehmiger.md](docs/fachregeln/rollen_rechte_genehmiger.md) + [docs/rechte_prompt.md](docs/rechte_prompt.md) |
| Terminal, Kiosk-UI, Offline-Queue, Kopplung | [docs/fachregeln/terminal_und_offline.md](docs/fachregeln/terminal_und_offline.md) |
| Auftraege, Arbeitsschritte, Barcodes, Laufkarte | [docs/fachregeln/auftraege_und_codes.md](docs/fachregeln/auftraege_und_codes.md) |
| Monatsuebersicht, Reports, PDF, Stundenkonto | [docs/fachregeln/auswertung_und_pdf.md](docs/fachregeln/auswertung_und_pdf.md) |
| Mitarbeiter, Abteilungen, Maschinen, Schema | [docs/fachregeln/stammdaten_und_datenbank.md](docs/fachregeln/stammdaten_und_datenbank.md) |
| Datenbankstruktur (Spalten, Indizes) | `sql/01_initial_schema.sql` – **Source of Truth** |
| Terminal aufsetzen / installieren | [docs/spezifikation_terminal_installation.md](docs/spezifikation_terminal_installation.md) |
| Lokal ausprobieren | [docs/lokale_entwicklungsumgebung.md](docs/lokale_entwicklungsumgebung.md) |
| Produktivinstallation | [docs/installationsanleitung.md](docs/installationsanleitung.md) |

**Nur bei Bedarf:** Der volle Projektverlauf steht in
`docs/archiv/DEV_PROMPT_HISTORY.md` (sehr gross – lies den Snapshot oben und
die letzten Eintraege, nie die ganze Datei). Der abgeloeste Master-Prompt v13
liegt in `docs/archiv/` und wird nur noch fuer historische Fragen gebraucht.

## 4. Was du nicht lesen musst

Diese Dinge stehen im Code und sind dort **immer** aktuell – frag lieber das
Repository als eine Dokumentation:

- **Welche Patches es gab** → `git log --oneline`
- **Welche Tabellen und Spalten es gibt** → `sql/01_initial_schema.sql`
- **Welche Routen es gibt** → `public/index.php`, `public/terminal.php`
- **Wo ein Recht geprueft wird** → `grep -rn "RECHTE_CODE" controller views`

## 5. Warum die Doku so aufgeteilt ist

Frueher stand alles in einem einzigen Master-Prompt (~36.000 Token). Wer nur
einen Tippfehler im Terminal beheben wollte, las zwangslaeufig auch
Pausenregeln, PDF-Spalten und Genehmigerlogik – teuer und unuebersichtlich.

Jetzt ist nach **Lesehaeufigkeit** getrennt: Arbeitsregeln und Status immer,
Fachregeln nach Bedarf, Historie nur auf Nachfrage. Doppelt gepflegte Listen
wurden gestrichen, weil sie auseinanderdriften – und dann weiss niemand mehr,
welche Fassung gilt.

**Wenn du eine Regel aenderst, aendere sie an genau einer Stelle.** Findest du
dieselbe Aussage zweimal, ist das ein Fehler – melde ihn.
