# Status-Snapshot

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Projektziel (kurz)
Webbasierte Zeiterfassung inkl. Mitarbeiter-/Rollen-/Genehmiger-Verwaltung, Urlaubsverwaltung, Auswertungen sowie Terminal-UI (Kiosk) inkl. Offline-Queue.

## Entry-Points
- Backend: `public/index.php` (Routing ueber `?seite=...`)
- Terminal: `public/terminal.php` (Routing ueber `?aktion=...`)

## Arbeitsweise (seit 2026-08-08)
- Aktiver Master-Prompt: `docs/master_prompt_zeiterfassung_v13.md` (v12 liegt im Archiv).
- Gearbeitet wird direkt im Git-Workspace; ein Patch ist ein Commit mit Patch-ID im Betreff. Keine ZIP-Pakete mehr, kein 3-Dateien-Limit.
- Lokale Umgebung zum Testen: `docs/lokale_entwicklungsumgebung.md` (App unter `http://localhost/zeiterfassung`).

## Letzte Aenderungen (Auszug)
- **P-2026-08-08-05 erzeugte Dateien:** `public/uploads/` ist von der Versionierung ausgenommen (nur `.gitkeep` bleibt); die ACL im Setup-Skript traegt jetzt Webserver **und** Projekteigentuemer ein, damit erzeugte Dateien von beiden Seiten ueberschreibbar bleiben.
- **P-2026-08-08-04 phpqrcode warnungsfrei (B-090):** Vier Ursachen in der mitgelieferten QR-Bibliothek behoben (Pflichtparameter hinter optionalen Parametern, dynamische Eigenschaft `$cmyk`, wirkungsloses `ImageDestroy()`, fehlendes Cache-Verzeichnis). QR-Code- und Barcode-Erzeugung laufen unter PHP 8.5 ohne eine einzige Meldung; betraf auch den Produktivserver mit PHP 8.3.
- **P-2026-08-08-03 Maschinen-Barcode-URL automatisch:** Die Anzeige-URL des Maschinen-Barcodes wird aus der Web-Basis der Installation plus `maschinen_qr_rel_pfad` abgeleitet, statt sie zusaetzlich von Hand pflegen zu muessen. `maschinen_qr_url` ist jetzt nur noch ein Override (leer = automatisch, `/` = Domain-Root, sonst Pfad/URL). Abweichende Zweitlogik im `MaschineAdminController` entfernt. Neuer Bug B-090 dokumentiert (phpqrcode-Deprecations).
- **P-2026-08-08-02 Datenbestand lokal + PHP-8.5-Pruefung:** Serverdump gegen `sql/01_initial_schema.sql` geprueft (strukturgleich, keine Abweichung) und lokal eingespielt; Fachlogik (Report, PDF, Urlaub, Stundenkonto, Feiertage) unter PHP 8.5 ohne Deprecations/Warnings. Produktivserver laeuft auf Debian 11 / MariaDB 11.8.3 / PHP 8.3.26. Dumps bleiben aus dem Repo heraus (personenbezogene Daten).
- **P-2026-08-08-01 lokale Umgebung + Doku-Neuordnung:** Reproduzierbares Setup-Skript fuer die lokale Entwicklungsumgebung (Apache + php-fpm + MariaDB LTS + phpMyAdmin), Root-`README.md` als Einstieg nach dem Klonen, Master-Prompt v13 (ZIP-/Dateilimit-Regeln entfallen samt Begruendung, PHP-Baseline definiert), `docs/archiv/ALTE_PROMPTS.md` als Begruendungsliste zum Archiv. Keine Fachlogik geaendert.
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
- P-2026-08-08-05 (Commit; Umgang mit zur Laufzeit erzeugten Dateien)
- Davor: P-2026-08-08-04 (phpqrcode laeuft unter aktuellem PHP warnungsfrei)
- Davor: P-2026-08-08-03 (Maschinen-Barcode-URL wird automatisch abgeleitet)
- Davor: P-2026-08-08-02 (Datenbestand lokal eingespielt, PHP-8.5-Pruefung der Fachlogik)
- Davor: P-2026-08-08-01 (lokale Entwicklungsumgebung + Doku/Master-Prompt v13)
- Noch aus dem ZIP-Workflow: P-2026-01-25-02_dashboard-zeitwarnungen-derived-table.zip

## Quelle der DB-Struktur
- `sql/01_initial_schema.sql`
