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

# DEV-PROMPT v11 (kurz)

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Es wird nur noch gearbeitet, wenn **Bugs gefunden** werden oder wenn der Nutzer **ausdrücklich** eine Erweiterung beauftragt.

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
- Nur Praxis-Test + Bugfixes.
- Offene Bugs nur, wenn sie **reproduzierbar** sind (sonst erst Daten sammeln).

## Nächster Schritt
- **Warten auf Bug/Bedarf** aus dem Praxis-Test; dann Micro-Patch.
