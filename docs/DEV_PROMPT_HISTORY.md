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
  example: "P-2025-12-21-04_rohzeit-buchen.zip"
---

# SNAPSHOT (immer aktuell)

## Wie dieses Dokument gelesen wird
- Dieses Dokument enthaelt **nur den SNAPSHOT** fuer den aktuellen Projektstand.
- Ein **vollstaendiger Verlauf/Archiv** ist **nicht mehr Teil der Veroeffentlichung**.

## Projektziel
Webbasierte Zeiterfassung inkl. Mitarbeiter-/Rollen-/Genehmiger-Verwaltung, Urlaubsverwaltung, Auswertungen sowie Terminal-UI (Kiosk) inkl. Offline-Queue.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Entry Points
- Backend: `public/index.php` (Routing ueber `?seite=...`)
- Terminal: `public/terminal.php` (Routing ueber `?aktion=...`)

## Zuletzt erledigt
- **P-2026-01-25-02:** Dashboard: Zeitwarnungen-Query als Derived-Table (ONLY_FULL_GROUP_BY/SQLMODE-sicher) + Bind-Parameter (start_ts, today); Debug-Fehlertext nur fuer Legacy-Admin im UI.
- **P-2026-01-25-01:** Dashboard: Zeitwarnungen-Query GROUP BY erweitert (MariaDB/ONLY_FULL_GROUP_BY kompatibel) + Sortierung ueber m.nachname/m.vorname; bei Fehlern wird jetzt ins error_log geschrieben und im Dashboard ein kurzer Hinweis angezeigt.
- **P-2026-01-24-08:** Dashboard: Zeitwarnungen-Query nutzt keine PDO-Parameter mehr (Inline-ISO-Datum), weil MariaDB/PDO in der Praxis trotz vorhandener Daten leere Resultsets lieferte; Query entspricht dem phpMyAdmin-Test und Zeitwarnungen werden wieder sichtbar.
- **P-2026-01-24-07:** Dashboard: Zeitwarnungen waren trotz vorhandener Daten unsichtbar, weil `DashboardController` versehentlich `fetchEinzel(...)` (nicht existent) aufruft und dadurch in den Catch faellt → Fix auf `fetchEine(...)`.
- **P-2026-01-24-05:** Dashboard: Zeitwarnungen werden nicht mehr durch die Nachtschicht-Grenzfall-Heuristik komplett ausgefiltert; Eintraege bleiben sichtbar (zus. Flag), damit der Warnhinweis im Dashboard wieder erscheint.

## Routing (Backend, Stand aus Code)
- Oeffentlich: `login`, `logout`
- Mitarbeiter: `dashboard`, `zeit_heute`, `urlaub_meine`, `urlaub_genehmigung`, `report_monat`, `report_monat_pdf`
- Rechte-basiert: `report_monat_export_all`, `smoke_test`
- Admin: `mitarbeiter_admin*`, `abteilung_admin*`, `rollen_admin*`, `feiertag_admin*`, `maschine_admin*`, `betriebsferien_admin*`, `kurzarbeit_admin*`, `urlaub_kontingent_admin*`, `konfiguration_admin*`, `queue_admin`, `zeit_rundungsregel_admin*`, `terminal_admin*`

## Datenbank
- **Source of Truth**: `sql/01_initial_schema.sql` (aktuelle DB-Struktur)
- Wichtige Tabellen: `mitarbeiter`, `zeitbuchung`, `terminal`, `urlaubsantrag`, `mitarbeiter_genehmiger`, `db_injektionsqueue`, `system_log`

## Entscheidungen (D-IDs)
- **D-001:** Das Repo nutzt `sql/01_initial_schema.sql` als Schema-Referenz (SQL-Datei in ZIP).
- **D-002:** Pro Patch-Iteration werden **maximal 3 Dateien** geaendert und als ZIP geliefert.
- **D-003:** `DEV_PROMPT_HISTORY.md` enthaelt nur den **SNAPSHOT** fuer den aktuellen Stand; ein Vollverlauf ist **nicht mehr Teil der Veroeffentlichung**.
- **D-004:** Zeitbuchungen (Kommen/Gehen) werden immer als **Rohzeit** gespeichert. Rundung erfolgt ausschliesslich bei **Auswertungen/Export/PDF**.

## Bekannte Probleme / Bugs (B-IDs)
- **B-080:** Urlaubssaldo wirkt teils verwirrend (User-Feedback: "Urlaubsberechnung stimmt nicht") – BF/Feiertage/Arbeitszeit-Abgrenzung nochmals pruefen. **OPEN** (Teilfix: P-2026-01-18-07).
