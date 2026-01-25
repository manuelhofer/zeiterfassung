---
project: zeiterfassung_und_managementsystem
language: de
timezone: Europe/Berlin
status: fertig_praxistest
stack: PHP (plain), MariaDB/MySQL, Apache
source_of_truth_db_schema: sql/01_initial_schema.sql
workflow:
  output: zip_only
  max_files_per_patch: 3
zip_naming:
  pattern: "P-YYYY-MM-DD-XX_kurzbeschreibung.zip"
---

# DEV-PROMPT v12 (kurz)

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Es wird nur noch gearbeitet, wenn **Bugs gefunden** werden oder wenn der Nutzer **ausdrücklich** eine Erweiterung beauftragt.

## Lizenzierung (Open Source)
- Lizenz: **GNU Affero General Public License v3.0 oder spaeter** (SPDX: `AGPL-3.0-or-later`, siehe Datei `LICENSE`).
- Kurzfassung: Jeder darf den Code **kopieren, benutzen, veraendern und weitergeben**. Wenn jemand eine geaenderte Version **verteilt** oder den Dienst **ueber ein Netzwerk bereitstellt** (Web-App/SaaS), muss er den Quellcode dieser Version den Nutzern bereitstellen (AGPL Netzwerkklausel).
- UI-Footer: Hinweis "**Erdacht von Manuel Kleespies**" + "Open Source (GNU AGPLv3)".
## Source of Truth
- Projektstand: **aktuelles Projekt-ZIP** vom Nutzer
- DB-Schema (kanonisch): `sql/01_initial_schema.sql`
- DB-Dump (Referenz): `sql/zeiterfassung_aktuell.sql`
- Rechte: `docs/rechte_prompt.md`
- Voller Verlauf/Archiv: `docs/DEV_PROMPT_HISTORY.md`

## Entry Points
- Backend: `public/index.php` (Routing über `?seite=...`)
- Terminal: `public/terminal.php` (Routing über `?aktion=...`)

## Arbeitsmodus (Bugfix)
1) Bugreport anfordern/nehmen: **Schritte**, **Erwartung**, **Ist**, **Datum/Monat/Mitarbeiter-ID**.
2) Duplicate-Check gegen SNAPSHOT/LOG (B-/T-/D-IDs).
3) Micro-Patch: **max. 3 Dateien** (inkl. `docs/DEV_PROMPT_HISTORY.md`), **ZIP only**.
4) `docs/DEV_PROMPT_HISTORY.md` aktualisieren:
   - SNAPSHOT updaten (Status/Bugs/Next)
   - Neuer Patch-Block mit: **EINGELESEN (SHA256)**, **DUPLICATE-CHECK**, **DATEIEN (max. 3)**, **DONE/NEXT**.

## Aktuell offen
- Praxis-Test + Bugfixes.
- Auftragsmodul (Scan: Auftrag + Arbeitsschritt Pflicht, Maschine optional):
  - Backend: Menü "Aufträge" + Suche + Detail mit Summen.
  - Terminal: Auftrag/Nebenauftrag Start erfasst Arbeitsschritt; Maschine ist optional und scan-tolerant (erste Zifferngruppe).
  - Maschinen: Barcode-Generator (Code 39) pro Maschine (SVG, optional PNG).
- Terminal Kiosk-Flow (Auftrag):
  - "Auftrag starten" / "Auftrag stoppen" sind **Kiosk-Aktionen**: nach Erfolg wird der Mitarbeiter am Terminal abgemeldet und es wird wieder die RFID-Abfrage gezeigt.
  - "Auftrag stoppen" vom Startscreen nutzt einen Quick-Stop (POST+CSRF, `aktion=auftrag_stoppen_quick`) und zeigt im Normalfall **keine** Stop-Detailmaske.
- Offen (optional/Feinschliff):
  - UX-Details/Edgecases nach Praxis-Test.

## Nächster Schritt
- **Warten auf Bug/Bedarf** aus dem Praxis-Test; dann Micro-Patch.
