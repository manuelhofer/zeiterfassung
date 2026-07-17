# Status-Snapshot

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Projektziel (kurz)
Webbasierte Zeiterfassung inkl. Mitarbeiter-/Rollen-/Genehmiger-Verwaltung, Urlaubsverwaltung, Auswertungen sowie Terminal-UI (Kiosk) inkl. Offline-Queue.

## Entry-Points
- Backend: `public/index.php` (Routing ueber `?seite=...`)
- Terminal: `public/terminal.php` (Routing ueber `?aktion=...`)

## Letzte Aenderungen (Auszug)
- **2026-07-17 Stundenkonto-Sammelumbuchung lokal:** Separate Umbuchungsmaske aus dem Stundenkonto heraus; normale Stundenkonto-Seite bleibt ohne Monatsfilter, die Sammelumbuchung zeigt Monats-Tageswerte und verschiebt eingegebene Abzuege gesammelt auf einen Zieltag (netto 0), inkl. Stealth-Unterstuetzung.
- **2026-07-17 Header-Menue lokal:** Top-Navigation in Dropdown-Gruppen `Urlaub`, `Uebersichten`, `Mitarbeiter`, `Rechte` und `Verwaltung` aufgeraeumt; bestehende Zielseiten/Rechtebedingungen bleiben erhalten.
- **2026-07-17 Mitarbeiter/Rollen-Rechte UI lokal:** Rollen, Rechte-Overrides und Genehmiger aus dem normalen Mitarbeiterformular in `?seite=mitarbeiter_rechte` ausgelagert; Stammdaten-Speichern laesst bestehende Rechte-Zuordnungen unangetastet.
- **2026-07-17 Mitarbeiter/Stundenkonto UI lokal:** Stundenkonto aus dem Mitarbeiterformular als eigene Seite `?seite=mitarbeiter_stundenkonto` mit Mitarbeiter-Auswahl ausgelagert; bestehende Buchungslogik bleibt erhalten, Ruecksprung nach Buchung fuehrt optional zur neuen Seite.
- **2026-07-17 Urlaub-Jahresuebersicht V1 lokal:** Read-only Jahresuebersicht unter `?seite=urlaub_jahresuebersicht` mit echten Urlaub-Genehmigungsrechten, U/O/BF/FT-Kalenderzellen, Monatswerten nur bei vorhandenen abgeschlossenen Monatsdaten und O-Link zur Genehmigungsliste.
- **2026-07-17 lokale Doku-Aufraeumung:** Root-`index.php` leitet auf `public/index.php`; `docs/wartungscheckliste.md`, `docs/prompt_uebersicht.md` und `docs/archiv/README.md` ergaenzt/verlinkt; Master-/Rechte-Prompt und aktive Doku an reale Pfade/Schema-Stand angepasst; keine Fachlogik geaendert.
- **P-2026-01-25-02:** Dashboard: Zeitwarnungen-Query als Derived-Table (ONLY_FULL_GROUP_BY/SQLMODE-sicher) + Bind-Parameter (start_ts, today); Debug-Fehlertext nur fuer Legacy-Admin im UI.
- **P-2026-01-24-08:** Dashboard: Zeitwarnungen-Query nutzt keine PDO-Parameter mehr (Inline-ISO-Datum), weil MariaDB/PDO in der Praxis trotz vorhandener Daten leere Resultsets lieferte; Query entspricht dem phpMyAdmin-Test und Zeitwarnungen werden wieder sichtbar.
- **P-2026-01-24-07:** Dashboard: Zeitwarnungen waren trotz vorhandener Daten unsichtbar, weil `DashboardController` versehentlich `fetchEinzel(...)` (nicht existent) aufruft und dadurch in den Catch faellt → Fix auf `fetchEine(...)`.

## Letzter Patch (P-ID)
- P-2026-01-25-02_dashboard-zeitwarnungen-derived-table.zip

## Quelle der DB-Struktur
- `sql/01_initial_schema.sql`
