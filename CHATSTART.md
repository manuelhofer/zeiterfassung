# Einstieg für KI-Assistenten

Startpunkt für **jede KI und jedes Werkzeug** an diesem Projekt – Claude Code,
Cursor, Copilot, Aider oder ein beliebiger Chat.

**Lies diese Datei ganz. Danach nur das, was zu deiner Aufgabe passt – nicht
alles.**

---

## 1. Was das Projekt ist

Webbasierte **Zeiterfassung** für einen Handwerks-/Fertigungsbetrieb, dazu eine
**Terminal-Oberfläche (Kiosk)** für die Werkstatt mit RFID-Login und
Offline-Queue. Reines PHP ohne großes Framework, MariaDB/MySQL, Apache.

**Einstiegspunkte:** Backend `public/index.php` (`?seite=…`), Terminal
`public/terminal.php` (`?aktion=…`).

**Zwei Installationstypen** über `app.installation_typ` in
`config/config.local.php`: `backend` und `terminal`. Ein Terminal ohne
Konfiguration zeigt seine Einrichtungsseite und koppelt sich per Code an.

## 2. Die vier Regeln, die immer gelten

**Verbindlich und vollständig ist [docs/arbeitsregeln.md](docs/arbeitsregeln.md)**
– lies die, bevor du etwas änderst; besprechbar ist jede davon (Abschnitt 0).
Hier stehen nur die vier, bei denen ein Verstoß nicht mehr zu reparieren ist:

- **1 Patch = 1 Thema** mit **einem** Akzeptanzkriterium in einem Satz.
- **Patch-ID im Commit-Betreff** (`P-YYYY-MM-DD-XX kurzbeschreibung`), dazu ein
  Eintrag in `docs/archiv/DEV_PROMPT_HISTORY.md` im **selben** Commit.
- **Keine Refactors nebenbei.** Was auffällt, wird notiert, nicht mitgemacht.
- **Gepusht wird nur auf ausdrückliche Ansage.**

## 3. Lesekarte – was liest du wann?

**Immer, vor jeder Änderung – diese drei Zeilen, mehr nicht:**

| Datei | Wofür |
| --- | --- |
| [docs/arbeitsregeln.md](docs/arbeitsregeln.md) | Wie gearbeitet wird |
| [docs/STATUS_SNAPSHOT.md](docs/STATUS_SNAPSHOT.md) | Der ganze Stand: nächster Schritt, offene Bugs, offene Tasks |
| `git log --oneline -20` | Was zuletzt passiert ist |

**Je nach Thema – nur das Passende:**

| Du arbeitest an … | Dann lies |
| --- | --- |
| Kommen/Gehen, Rundung, Pausen, Tageskorrekturen | [docs/fachregeln/zeit_rundung_pausen.md](docs/fachregeln/zeit_rundung_pausen.md) |
| Urlaub, Betriebsferien, Feiertage | [docs/fachregeln/urlaub_abwesenheit_feiertage.md](docs/fachregeln/urlaub_abwesenheit_feiertage.md) |
| Rollen, Rechte, Bereiche, Genehmiger | [docs/fachregeln/rollen_rechte_genehmiger.md](docs/fachregeln/rollen_rechte_genehmiger.md) + [docs/rechte_prompt.md](docs/rechte_prompt.md) |
| Terminal, Kiosk-UI, Offline-Queue, Kopplung | [docs/fachregeln/terminal_und_offline.md](docs/fachregeln/terminal_und_offline.md) |
| Aufträge, Arbeitsschritte, Barcodes, Laufkarte | [docs/fachregeln/aufträge_und_codes.md](docs/fachregeln/auftraege_und_codes.md) |
| Monatsübersicht, Reports, PDF, Stundenkonto | [docs/fachregeln/auswertung_und_pdf.md](docs/fachregeln/auswertung_und_pdf.md) |
| Mitarbeiter, Abteilungen, Maschinen, Schema | [docs/fachregeln/stammdaten_und_datenbank.md](docs/fachregeln/stammdaten_und_datenbank.md) |
| Datenbankstruktur (Spalten, Indizes) | `sql/01_initial_schema.sql` – **Source of Truth** |
| Terminal aufsetzen / installieren | [docs/spezifikation_terminal_installation.md](docs/spezifikation_terminal_installation.md) |
| Lokal ausprobieren | [docs/lokale_entwicklungsumgebung.md](docs/lokale_entwicklungsumgebung.md) |
| Produktivinstallation | [docs/installationsanleitung.md](docs/installationsanleitung.md) |

`docs/archiv/DEV_PROMPT_HISTORY.md` ist **keine Startlektüre** – ein Eintrag je
Patch, seit dem ersten Tag, entsprechend lang. Nur gezielt aufschlagen, wenn zu
einem bestimmten Patch die Begründung oder der Test gesucht wird
(`grep -n "P-2026-08-09-04"`), nie am Stück.

## 4. Was du nicht lesen musst

Diese Dinge stehen im Code und sind dort **immer** aktuell – frag lieber das
Repository als eine Dokumentation:

- **Welche Patches es gab** → `git log --oneline`
- **Welche Tabellen und Spalten es gibt** → `sql/01_initial_schema.sql`
- **Welche Routen es gibt** → `public/index.php`, `public/terminal.php`
- **Wo ein Recht geprüft wird** → `grep -rn "RECHTE_CODE" controller views`
