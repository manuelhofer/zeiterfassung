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
- **Wenn du nur schnell den Projektstand brauchst:** lies nur diesen SNAPSHOT.
- **Alles darunter** ist **LOG/ARCHIV** mit vollständiger Historie und Details.

## Projektziel
Webbasierte Zeiterfassung inkl. Mitarbeiter-/Rollen-/Genehmiger-Verwaltung, Urlaubsverwaltung, Auswertungen sowie Terminal-UI (Kiosk) inkl. Offline-Queue.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Entry Points
- Backend: `public/index.php` (Routing über `?seite=...`)
- Terminal: `public/terminal.php` (Routing über `?aktion=...`)

## Zuletzt erledigt
- **P-2026-01-25-02:** Dashboard: Zeitwarnungen-Query als Derived-Table (ONLY_FULL_GROUP_BY/SQLMODE-sicher) + Bind-Parameter (start_ts, today); Debug-Fehlertext nur für Legacy-Admin im UI.
- **P-2026-01-24-08:** Dashboard: Zeitwarnungen-Query nutzt keine PDO-Parameter mehr (Inline-ISO-Datum), weil MariaDB/PDO in der Praxis trotz vorhandener Daten leere Resultsets lieferte; Query entspricht dem phpMyAdmin-Test und Zeitwarnungen werden wieder sichtbar.
- **P-2026-01-25-01:** Dashboard: Zeitwarnungen-Query GROUP BY erweitert (MariaDB/ONLY_FULL_GROUP_BY kompatibel) + Sortierung ueber m.nachname/m.vorname; bei Fehlern wird jetzt ins error_log geschrieben und im Dashboard ein kurzer Hinweis angezeigt.
- **P-2026-01-24-07:** Dashboard: Zeitwarnungen waren trotz vorhandener Daten unsichtbar, weil `DashboardController` versehentlich `fetchEinzel(...)` (nicht existent) aufruft und dadurch in den Catch faellt → Fix auf `fetchEine(...)`.
- **P-2026-01-24-05:** Dashboard: Zeitwarnungen werden nicht mehr durch die Nachtschicht-Grenzfall-Heuristik komplett ausgefiltert; Eintraege bleiben sichtbar (zus. Flag), damit der Warnhinweis im Dashboard wieder erscheint.
- **P-2026-01-24-04:** Dashboard: Zeitwarnungen erkennen jetzt echte Kommen/Gehen-Ungleichgewichte (z. B. 2x "Kommen" ohne "Gehen"), nicht nur ungerade Gesamtanzahl; UI-Text angepasst.
- **P-2026-01-24-03:** Dashboard: Zeitwarnungen nutzen jetzt zusaetzlich `tageswerte_mitarbeiter` (roh/korr) als Fallback, damit "FEHLT"-Tage auch dann im Dashboard auftauchen, wenn keine ungeraden Rohstempel erkannt werden; Dedupe mid+datum.
- **P-2026-01-24-01:** Dashboard: Zeitwarnung (unvollstaendige Kommen/Gehen) prueft standardmaessig **31 Tage** (statt 14); Zeitraum optional via Config `dashboard_zeitwarnungen_tage`.
- **P-2026-01-23-02:** Urlaubssaldo: Betriebsferien-Abzug ueber zentrale BF-Zaehllogik (wie Anzeige) berechnet → kein Drift mehr (z. B. 9.50 vs 10.00).
- **P-2026-01-23-01:** Urlaub (Betriebsferien + Urlaubsantraege): Heiligabend (24.12) und Silvester (31.12) werden als **0.5 Urlaubstage** gewertet → Betriebsferien-Liste passt zum Urlaubssaldo.
- **P-2026-01-22-02:** Terminal Urlaub-Uebersicht: Betriebsferien-"benoetigte Urlaubstage" nutzt Mitarbeiter-spezifische BF-Logik (Skip bei Arbeit/Kennzeichen/Krank) + Reihenfolge: Jahresanspruch | Uebertrag | Korrektur | Effektiv.
- **P-2026-01-22-01:** Monatsuebersicht/PDF: Rundung->0-Bloecke (gehen_korr<=kommen_korr) wie Mikro behandeln (ausblenden) + Block-IST=0.00.
- **P-2026-01-21-03:** Monatsuebersicht/PDF: Pause/Meta in Mehrfach-Bloecken an Primaer-Zeile (>=60min), nicht immer in erster Zeile.
- **P-2026-01-21-02:** Monatsuebersicht/PDF: IST je Block anzeigen (Folgezeilen bei Mehrfach-Bloecken, Summen unveraendert).
- **P-2026-01-21-01:** Monatsreport-PDF: Mikro-Filter an Monatsuebersicht angeglichen (Rohdiff + abs, config `micro_buchung_max_sekunden`).
- **P-2026-01-20-07:** Monatsuebersicht/PDF: Mehrfach-Kommen/Gehen wird als **eigene Block-Zeilen** angezeigt (keine Min/Max-Kollaps). Mikro-Buchungen bleiben standardmaessig ausgeblendet.
- **P-2026-01-20-06:** Monatsuebersicht/PDF: IST-Stunden pro Tag = Summe echter Arbeitsbloecke (Mehrfach-Kommen/Gehen), nicht Min/Max-Spanne.
- **P-2026-01-20-04:** Open Source: Projekt unter **GNU AGPLv3 (copyleft)** gestellt (`LICENSE`) + UI-Footer-Hinweis "Erdacht von Manuel Kleespies".
- **P-2026-01-20-02:** Open Source: (alt) MIT License – durch P-2026-01-20-04 ersetzt.
- **P-2026-01-20-01:** Terminal: Nebenauftrag stoppen – Dropdown waehlt automatisch den ersten laufenden Nebenauftrag (kein "--- auswaehlen ---"). Status-Auswahl entfernt (einfach "stoppen"); Auftragscode-Scan hat Vorrang vor Dropdown.
- **P-2026-01-18-36:** Auftrag: Hauptauftrag Start/Stop beendet Nebenauftraege **nicht** automatisch (Online+Offline). Terminal-Text: „gestoppt“ statt „beendet“.
- **P-2026-01-18-35:** Auftrag: Auto-Stop Nebenauftrag bei Hauptauftrag-Stop (Praxis-Test zeigte: Verhalten unerwuenscht → in P-2026-01-18-36 rueckgaengig).
- **P-2026-01-18-34:** Terminal: Nebenauftrag-Start ist serverseitig geblockt, wenn kein Hauptauftrag laeuft (Online-DB + Offline-Session-Fallback).
- **P-2026-01-18-33:** Doku: Auftrags-Prompt v1 hinzugefuegt + DevPrompt/History aktualisiert (Patch 31/32 nachgetragen).
- **P-2026-01-18-32:** Terminal: Scan-Flow – Enter im Maschinenfeld = Submit (Maschine optional).
- **P-2026-01-18-31:** Terminal: Maschine-Scan tolerant (Ziffern aus Scan-Text) + Maschinenfeld als Text.
- **P-2026-01-18-30:** Maschinen-Backend: Barcode-Generator (Code 39) pro Maschine (SVG, optional PNG) + Anzeige/Download im Maschinenformular.

- **P-2026-01-18-29:** Terminal: Hauptauftrag starten – Arbeitsschritt-Code ist Pflicht (Server-Validierung + Formular).
- **P-2026-01-18-28:** Terminal: Nebenauftrag – Offline-UI-Status via Session-Merker (Stop-Button sichtbar nach Start).
- **P-2026-01-18-27:** Terminal: Startscreen – wenn ein Hauptauftrag laeuft, wird „Auftrag starten“ ausgeblendet; stattdessen wird „Auftrag stoppen“ als Primaeraktion gezeigt (Doppelstarts vermeiden).
- **P-2026-01-18-26:** Terminal: Nebenauftrag-Start (Offline-Queue) legt jetzt auch einen Minimaldatensatz in `auftrag` an (Queue-Aktion `auftrag_ensure`), damit spaetere Auswertungen/Zuordnung stabil bleiben.
- **P-2026-01-18-25:** Terminal: Nebenauftrag starten – Arbeitsschritt-Code (Scan) wird erfasst und in `auftragszeit.arbeitsschritt_code` gespeichert.
- **P-2026-01-18-24:** Backend: Auftrag-Detail – Arbeitsschritt-Code sichtbar + Summen pro Arbeitsschritt + Gesamtstunden (abgeschlossen).
- **P-2026-01-18-23:** Terminal: Startscreen – Auftrag-/Nebenauftrag-Buttons sind kontextabhaengig (Stop/Nebenauftrag nur wenn ein Auftrag laeuft).
- **P-2026-01-18-22:** Terminal: Auftrag starten – Arbeitsschritt-Code (Scan) als Pflichtfeld erfasst und gespeichert (Controller+Service+View).
- **P-2026-01-18-21:** DB/Schema: `auftragszeit` um `arbeitsschritt_code` (Scan-Grundlage) erweitert.
- **P-2026-01-18-20:** Backend: Top-Menue – Link "Auftraege" ist jetzt im Header klickbar (active fuer `auftrag`/`auftrag_detail`).
- **P-2026-01-18-19:** Backend: Auftraege – neue Routen `?seite=auftrag` + `?seite=auftrag_detail`. 
- **P-2026-01-18-17:** Doku: Master+Dev Prompt v12 (Auftragsmodul als eigener Prompt geplant).
- **P-2026-01-18-16:** Doku: Projekt auf **FERTIG/Praxis-Test** gesetzt; neue kurze Prompt-Dateien v11 (Master+Dev).
- **P-2026-01-18-15:** Backend: Mein Urlaub – `BF (Rest Jahr)` Anzeige nutzt AuthService (korrekte Session-ID).
- **P-2026-01-18-13:** Backend: Report-Routen clampen `jahr/monat` defensiv (monat=0/13 verursacht keine DateTime-Fehler mehr).
- **P-2026-01-18-12:** SoT: `sql/01_initial_schema.sql` enthaelt `mitarbeiter.eintrittsdatum` (Neuinstallation konsistent).
- **P-2026-01-18-11:** Mitarbeiter-Admin: Eintrittsdatum im Formular pflegbar (lesen/speichern).
- **P-2026-01-18-12:** SoT: sql/01_initial_schema.sql enthaelt mitarbeiter.eintrittsdatum (Neuinstallation konsistent).
- **P-2026-01-18-08:** UrlaubService: Wenn `mitarbeiter.urlaub_monatsanspruch` = 0.00, wird ein Standardanspruch genutzt (`config:urlaub_standard_monatsanspruch` oder `config:urlaub_standard_jahresanspruch`, sonst Fallback 2.50=30 Tage/Jahr) + Hinweistext. Fix fuer „Urlaubtage (abzgl. BF)“ wird sonst durch Betriebsferien unplausibel negativ.
- **P-2026-01-18-07:** UrlaubService: Betriebsferien-Abzug/Restjahr robust (tageswerte nutzt `ist_stunden`, BF Restjahr zaehlt keine Tage mit Arbeit/Kennzeichen/Krankzeitraum) – Teilfix fuer **B-080**.
- **P-2026-01-18-06:** Monatsreport (HTML+PDF): Urlaubtage (abzgl. BF) darf wieder **negativ** sein (keine 0-Deckelung). TODO: Urlaub-Saldo-Drift zwischen „Meine Urlaubsantraege“ und Monatsreport-PDF (Screenshot: -10 trotz 20 uebrig) nachziehen.
- **P-2026-01-18-03:** Monatsreport-HTML: Urlaubsblock nutzt UrlaubService-Jahressaldo (inkl. BF); BF (Rest Jahr) bleibt Info; kein Doppelabzug.
- **P-2026-01-18-02:** Monatsreport-PDF: Urlaubsblock nutzt UrlaubService-Jahressaldo (inkl. BF) und zeigt "Urlaubtage (abzgl. BF)"; kein Doppel-Abzug.
- **P-2026-01-18-01:** Monatsuebersicht: PDF-Link uebernimmt `?show_micro=1`, wenn die Checkbox „Mikro-Buchungen anzeigen“ aktiv ist.
- **P-2026-01-17-29:** Bugfix B-078: Monatsuebersicht + Monatsreport-PDF wieder standardmaessig **aggregiert pro Tag** (1 Zeile/Tag: erster Start + letzter Endzeit); Detail-/Mikro-Buchungen nur mit `?show_micro=1`.
- **P-2026-01-17-28:** Stabilitaet: Regression „Mikro-Buchungen“ in Monatsuebersicht/PDF als Bug **B-078** dokumentiert (Fix folgt als Micro-Patch).
- **P-2026-01-17-27:** Monatsreport-HTML: Zusatzblock zeigt "Urlaubtage (abzgl. BF)" + "BF (Rest Jahr)" neben dem Stundenkonto (Rest des Jahres).
- **P-2026-01-17-26:** Monatsreport-PDF: Zusatzblock rechts zeigt "Urlaubtage (abzgl. BF)" + "BF (Rest Jahr)" (Rest des Jahres), Basis: UrlaubService-Arbeitstage.
- **P-2026-01-17-25:** Doku Rechte: `STUNDENKONTO_VERWALTEN` ist aktiv (Inventar=JA, reale Pruefpunkte).
- **P-2026-01-17-24:** Stundenkonto: Audit-Logs in `system_log` fuer Korrektur/Verteilung/Monatsabschluss.
- **P-2026-01-17-23:** Monatsuebersicht: Monatsabschluss-Knopf (Differenz Soll/Ist als Stundenkonto-Buchung, idempotent/aktualisierbar, nur fuer vergangene Monate).
- **P-2026-01-17-22:** Backend-Admin: Stundenkonto-Verteilbuchung (Batch) buchen + letzte Batchs anzeigen (Mo-Fr optional).
- **P-2026-01-17-21:** Backend-Admin: Stundenkonto-Saldo anzeigen + manuelle Korrektur buchen (Recht `STUNDENKONTO_VERWALTEN`).
- **P-2026-01-17-20:** Monatsreport-PDF: Summenblock zeigt Stundenkonto (Stand bis Vormonat).
- **P-2026-01-17-19:** Terminal: Arbeitszeit-Uebersicht zeigt Gutstunden/Minusstunden (Stand bis Vormonat).
- **P-2026-01-17-18:** Backend: Monatsuebersicht zeigt Gutstunden/Minusstunden (Stand bis Vormonat).
- **P-2026-01-17-17:** Stundenkonto: Neuer `StundenkontoService` (read-only) liefert Saldo bis Vormonat (Basis fuer Terminal/Report/PDF).
- **P-2026-01-17-16:** Stundenkonto: `sql/01_initial_schema.sql` (SoT) um Tabellen `stundenkonto_batch` + `stundenkonto_korrektur` ergaenzt (Neuinstallation konsistent).
- **P-2026-01-17-15:** Stundenkonto: SQL-Migration sauber einsortiert (kanonisch als `21_migration_stundenkonto.sql`); `13_migration_stundenkonto.sql` ist als Legacy-Alias markiert.
- **P-2026-01-17-13:** Zusatz-Prompt 2: Stundenkonto (Gutstunden/Minusstunden) Scope/Plan dokumentiert.
- **P-2026-01-17-12:** Monatsuebersicht: Mikro-Buchungen werden am Rohstempel erkannt (Rundung blaeht Mikro nicht mehr auf) und bei deaktivierter Checkbox wirklich ausgeblendet.
- **P-2026-01-17-10:** Terminal: Arbeitszeit-Übersicht-Seite – Monatsstatus robust (ReportService-Fehler blockiert nicht), Labels Soll/Ist, Zurück-Button wie alle anderen.
- **P-2026-01-17-09:** Terminal: Logout – Mitarbeiterpanel ist klickbarer Link zur Arbeitszeit-Übersicht-Seite.
- **P-2026-01-17-08:** Terminal: Urlaub beantragen + Stoerungsmodus – Mitarbeiterpanel ist klickbarer Link zur Arbeitszeit-Übersicht-Seite.
- **P-2026-01-17-07:** Terminal: Auftrag stoppen – Mitarbeiterpanel Link zur Arbeitszeit-Übersicht-Seite.
- **P-2026-01-17-06:** Terminal: Auftrag starten – Mitarbeiterpanel Link zur Arbeitszeit-Übersicht-Seite.
- **P-2026-01-17-05:** Terminal: Mitarbeitername unten ist klickbar → Arbeitszeit-Übersicht als eigene Seite (mit Zurück).
- **P-2026-01-17-04:** Terminal: Monatsstatus – live heute + Helper (konsistente Berechnung in allen Flows).
- **P-2026-01-17-03:** Terminal: Monatsstatus – „Geleistete Arbeitsstunden bis heute“ zaehlt nur Arbeitszeit (keine Urlaub/Krank/Feiertag etc.).
- **P-2026-01-17-02:** Terminal: Startscreen – Labels im Block "Monatsstatus" (Uebersicht) vereinheitlicht.
- **P-2026-01-17-01:** Terminal: Startscreen – Monatsstatus Duplikat-Fix.
- **P-2026-01-16-07:** Terminal: Stoerungsmodus – wenn Queue wieder ok, automatisch zurueck zum Start (inkl. Button "Neu pruefen").
- **P-2026-01-16-06:** Terminal: Logout – Mitarbeiterpanel zeigt Arbeitszeit-Uebersicht (Soll Monat/Soll bis heute/Ist bis heute).
- **P-2026-01-16-05:** Terminal: Auftrag Start/Stop – Labels im Mitarbeiterpanel vereinheitlicht (ohne "(Soll)"/"(Ist)").
- **P-2026-01-16-04:** Terminal: Stoerungsmodus – Mitarbeiterpanel (Arbeitszeit-Uebersicht) + Fehlerdetails-Fix (`$stoerungEintrag`).
- **P-2026-01-16-03:** Terminal: Nebenauftrag stoppen – Monatsstatus/Arbeitszeit-Uebersicht im Mitarbeiterpanel (Startscreen) verfuegbar.
- **P-2026-01-16-02:** Terminal: Nebenauftrag starten – Monatsstatus/Arbeitszeit-Uebersicht im Mitarbeiterpanel (Startscreen) verfuegbar.
- **P-2026-01-16-01:** Terminal: Urlaub beantragen – Mitarbeiterpanel zeigt Arbeitszeit-Uebersicht (Soll Monat/Soll bis heute/Ist bis heute).


## Routing (Backend, Stand aus Code)
- Öffentlich: `login`, `logout`
- Mitarbeiter: `dashboard`, `zeit_heute`, `urlaub_meine`, `urlaub_genehmigung`, `report_monat`, `report_monat_pdf`
- Rechte-basiert: `report_monat_export_all`, `smoke_test`
- Admin: `mitarbeiter_admin*`, `abteilung_admin*`, `rollen_admin*`, `feiertag_admin*`, `maschine_admin*`, `betriebsferien_admin*`, `kurzarbeit_admin*`, `urlaub_kontingent_admin*`, `konfiguration_admin*`, `queue_admin`, `zeit_rundungsregel_admin*`, `terminal_admin*`

## Datenbank
- **Source of Truth**: `sql/01_initial_schema.sql` (aktuelle DB-Struktur)
- Wichtige Tabellen: `mitarbeiter`, `zeitbuchung`, `terminal`, `urlaubsantrag`, `mitarbeiter_genehmiger`, `db_injektionsqueue`, `system_log`

## Entscheidungen (D-IDs)
- **D-001:** Das Repo nutzt `sql/01_initial_schema.sql` als Schema-Referenz (SQL-Datei in ZIP).
- **D-002:** Pro Patch-Iteration werden **maximal 3 Dateien** geändert und als ZIP geliefert.
- **D-003:** `DEV_PROMPT_HISTORY.md` bleibt vollständig, bekommt aber diesen SNAPSHOT oben zur schnellen Übergabe in neue Chats.
- **D-004:** Zeitbuchungen (Kommen/Gehen) werden immer als **Rohzeit** gespeichert. Rundung erfolgt ausschließlich bei **Auswertungen/Export/PDF**.
- **D-005:** **Pre-Flight Gate** ist Pflicht: Vor jeder Implementierung werden Inputs (ZIP/Prompts/SQL) gelesen, SHA256 dokumentiert und ein Duplicate-Check gegen SNAPSHOT/LOG durchgeführt.
- **D-006:** Micro-Patches sind Pflicht: 1 Patch = 1 Thema/1 Effekt; wegen `DEV_PROMPT_HISTORY.md` bleiben praktisch nur 2 weitere Dateien → Tasks müssen vor Umsetzung gesplittet werden.

## Bekannte Probleme / Bugs (B-IDs)
- **B-079:** Monatsreport-PDF: Urlaubsblock nutzte `urlaub_verbleibend` aus Monatswerten und zog BF-Restjahr erneut ab (Doppelabzug/negative Werte). **DONE in P-2026-01-18-02**.
- **B-081:** Monatsreport-HTML: Urlaubsblock zog BF-Restjahr zusaetzlich ab und konnte so inkonsistent/negativ werden. **DONE in P-2026-01-18-03**.
- **B-082:** Urlaub: Eintrittsjahr/Anlage im laufenden Jahr wurde bisher nicht anteilig gerechnet (voller Jahresanspruch). Zudem wurde negativer Resturlaub beim Auto-Übertrag auf 0 gekappt → Minusurlaub gleicht sich im Folgejahr nicht aus. **DONE in P-2026-01-18-09**.
- **B-080:** Urlaubssaldo wirkt teils verwirrend (User-Feedback: "Urlaubsberechnung stimmt nicht") – BF/Feiertage/Arbeitszeit-Abgrenzung nochmals pruefen. **OPEN** (Teilfix: P-2026-01-18-07).
- **B-078:** Monatsuebersicht + Monatsreport-PDF: „Mikro-Buchungen“ (mehrere Rohstempel-Reihen pro Tag) wurden als Detail-Zeilen wieder angezeigt (Regression gegen **P-2026-01-17-12**). Standard ist wieder **aggregiert pro Tag**; Detail-/Mikro-Buchungen nur optional via `?show_micro=1`. **DONE in P-2026-01-17-29**.
- **B-076:** Urlaubssaldo: Betriebsferien (Zwangsurlaub) werden nicht abgezogen, wenn ein aktiver Krankzeitraum (LFZ/KK) den Tag umfasst (ohne Tages-Override in `tageswerte_mitarbeiter`). **DONE in P-2026-01-07-23**.
- **B-077:** Monatsreport/PDF: Krankzeitraum muss Betriebsferien im Tagesraster übersteuern (kein BF-Kürzel, Urlaub 0, Krank 8.00) – Krank hat Vorrang vor Betriebsferien. **DONE in P-2026-01-08-01**.
- **B-074:** Urlaub: Kontingent wurde in "Mein Urlaub" ignoriert, weil SQL-Queries im UrlaubService literales `\n` enthielten (Syntaxfehler) → Hinweis "DB-Update fehlt?". **DONE in P-2026-01-07-03**.
- **B-075:** Urlaub: Urlaubsantrag konnte gespeichert werden, obwohl der Zeitraum **0.00 verrechenbare Urlaubstage** ergibt (z. B. komplett Wochenende/Feiertag/Betriebsferien) → verwirrende Einträge in "Meine Urlaubsanträge". **DONE in P-2026-01-07-04**.
- **B-037:** Monatsreport-PDF: Bemerkungen-Block nutzte `$sumStartY` vor Definition (PHP-Notice, Positionierung inkonsistent) → Summenblock-Positionen vor Bemerkungen definiert. **DONE in P-2026-01-04-47**.
- **B-038:** Monatsübersicht: "Ist (gesamt)" zählte Kurzarbeit fälschlich als IST (MasterPrompt: Kurzarbeit reduziert Soll, nicht IST). **DONE in P-2026-01-05-13**.
- **B-012:** Backend: Reiter/Seite `urlaub_kontingent_admin` erzeugte einen „Internal Server Error“ (500). Ursache: Parse-Error (doppelter Block nach `pruefeZugriff()` im Controller). **DONE in P-2026-01-01-72**.
- **B-013:** Monatsreport-PDF zeigte in manchen Umgebungen nur eine leere Seite („oben ein Strich“). Ursache: PDF-Content-Stream enthielt literales `\\n` (z. B. `S\\n40.00`) durch `sprintf(...\n...)` in **einfachen** Quotes → Parser bricht ab. **DONE in P-2026-01-02-05**.
- **B-014:** Monatsreport-PDF konnte durch Warnungen/Output-Buffering/Kompression beschädigt oder abgebrochen werden. Mit Error-Handler + Buffer-Cleanup + defensiven ini_set-Absicherungen behoben. **DONE in P-2026-01-02-06**.
- **B-015:** Monatsreport-PDF konnte bei `LC_NUMERIC=de_DE` durch Dezimalkomma in PDF-Kommandos abbrechen. Locale-sichere PDF-Number-Helper + Caching in FeiertagService. **DONE in P-2026-01-02-07**.
- **B-016:** Monatsreport-PDF Rendering weiterhin in einzelnen Viewer-Setups instabil: PDF-Kopf/Binary-Markierung + WinAnsiEncoding + /ProcSet + Stream-Termination + no-gzip/no-transform Headers ergänzt. **DONE in P-2026-01-02-08**.
- **B-018:** Dashboard Smoke-Test: Tabellen-Check nutzte falschen Tabellenname `feiertage` statt `feiertag` (falsches FAIL). **DONE in P-2026-01-02-11**.
- **B-019:** AuftragModel suchte in Tabelle `auftrag` fälschlich nach Spalte `auftragscode` (existiert nicht); korrekt ist `auftragsnummer`. **DONE in P-2026-01-02-17**.
- **B-020:** `sql/01_initial_schema.sql`: Nach `kurzarbeit_plan` stand eine doppelte `) ENGINE=...`-Zeile → Neuanlage/Import bricht ab. **DONE in P-2026-01-03-04**.
- **B-021:** `sql/01_initial_schema.sql`: Tabelle `feiertag` fehlte `) ENGINE=...` → Neuanlage/Import bricht ab. **DONE in P-2026-01-03-08**.
- **B-022:** Pausenregeln: `KonfigurationController` speicherte neue `pausenfenster` mit Spalte `angelegt_von_mitarbeiter_id`, die im Schema nicht existiert → Speichern schlug fehl. **DONE in P-2026-01-03-12**.
- **B-023:** Terminal: `TerminalController::auftragStoppen()` übergab einen zusätzlichen Typ-Parameter an `AuftragszeitService::stoppeAuftrag()` → `ArgumentCountError` beim Stoppen von Hauptaufträgen. **DONE in P-2026-01-03-26**.
- **B-028:** Terminal: Kommen/Gehen konnte durch Doppelklick oder Doppel-Scan innerhalb weniger Sekunden doppelt gebucht werden → Doppelbuchung wird jetzt per Session-De-Bounce verhindert. **DONE in P-2026-01-03-33**.
- **B-024:** Monatsübersicht/PDF: Betriebsferien (8h Urlaub) wurden fälschlich **zusätzlich** zur Arbeitszeit gezählt, wenn an einem Betriebsferien-Tag tatsächlich gearbeitet wurde → Ist-Summe war zu hoch. **DONE in P-2026-01-03-27**.
- **B-025:** Urlaubssaldo: Betriebsferien-Tage wurden als genommener Urlaub gezählt, obwohl an diesen Tagen gearbeitet wurde (oder bereits andere Kennzeichen wie krank gesetzt waren) → Resturlaub zu niedrig. **DONE in P-2026-01-03-28**.
- **B-026:** Monatsübersicht: Manuell geänderte Zeiten/Tage waren nicht rot markiert („rot hinterlegt“ fehlte). **DONE in P-2026-01-03-29**.
- **B-027:** Monatsreport-PDF: Manuell geänderte Zeiten/Tage waren nicht rot hinterlegt („rot hinterlegt“ fehlte). **DONE in P-2026-01-03-30**.
- **B-029:** Monatsübersicht/PDF: Rot-Markierung basierte auf `felder_manuell_geaendert` (Tageskennzeichen wie Kurzarbeit wurden rot). Gewünscht: Rot nur bei manuell geänderten Kommen/Gehen. Monatsübersicht **DONE in P-2026-01-03-35**, PDF **DONE in P-2026-01-03-36**.
- **B-030:** Monatsübersicht/PDF: Kurzarbeit-Volltag soll wie Betriebsferien wirken (i. d. R. Tages-Soll/8h), aber nicht zusätzlich, wenn am selben Tag gearbeitet wurde. **DONE in P-2026-01-03-37**.
- **B-031:** Terminal: Login per RFID/Mitarbeiter-ID war fälschlich an `ist_login_berechtigt` gekoppelt (Checkbox „Login über Benutzername/E-Mail erlaubt“) → normale Mitarbeiter konnten sich nicht am Terminal anmelden. **DONE in P-2026-01-03-40**.
- **B-032:** Terminal: Nach Login wurden Auftrag-/Nebenauftrag-Buttons auch ohne Anwesenheit angezeigt; zudem fehlten Helper-Methoden (`setzeTerminalAnwesenheitStatus`, `istTerminalMitarbeiterHeuteAnwesend`) → UI zeigt bei Nicht-Anwesenheit nur „Kommen“ (+ optional Urlaub) und Online/Offline-Anwesenheit ist konsistent. **DONE in P-2026-01-03-42**.
- **B-033:** Terminal: Einige Stop-Aktionen (Haupt-/Nebenauftrag stoppen) waren per Direkt-URL auch ohne Anwesenheit erreichbar; außerdem konnte „Kommen“ trotz bestehender Anwesenheit manuell ausgelöst werden → serverseitige Anwesenheits-Guards ergänzt. **DONE in P-2026-01-03-43**.
- **B-034:** Terminal: Wenn Mitarbeiter nach Login **nicht anwesend** ist, soll das Menü wirklich nur „Kommen“ (+ optional „Urlaub beantragen“) zeigen – keine Übersicht/Details. **DONE in P-2026-01-03-44**.
- **B-035:** Terminal: Wenn Mitarbeiter nach Login **nicht anwesend** ist, soll das Terminal-Hauptmenü auch **keinen Urlaubssaldo/Status-Box** anzeigen (nur „Kommen“ + optional „Urlaub beantragen“). **DONE in P-2026-01-04-08**.
- **B-036:** Terminal: Numerische Codes (Personalnummer vs Mitarbeiter-ID) können mehrdeutig sein → Login darf nicht „still“ den falschen Mitarbeiter wählen; bei Mehrdeutigkeit abbrechen. **DONE in P-2026-01-04-10**.
- **B-070:** Monatsreport/PDF: Kalender-Feiertage werden in der Tagesliste als **Feiertag** geführt und bei **keiner Arbeitszeit** mit Tagesstunden (Fallback 8.00 / Tages-Soll) befüllt; Abgrenzung gegen Urlaub/Betriebsferien. **DONE in P-2026-01-04-17**.
- **B-071:** FeiertagService: Jahres-Init wurde fälschlich als „fertig“ behandelt, sobald irgendein Feiertag für das Jahr existierte → fehlende bundeseinheitliche Feiertage (z. B. 01.01.) konnten unbemerkt fehlen. Jetzt werden fehlende Basis-Feiertage **idempotent nachgeseedet**. **DONE in P-2026-01-04-18**.
- **B-072:** Smoke-Test: Queue-Übersicht nutzte bisher immer die Haupt-DB; bei konfigurierter Offline-DB waren Zähler/„Letzte 10“ inkonsistent zu `queue_admin` (dort Offline-DB bevorzugt). **DONE in P-2026-01-04-26**.
- **B-073:** Urlaub: `UrlaubService` selektierte `urlaub_kontingent_jahr.uebertrag_tage`; wenn die Spalte fehlte, fiel der komplette Kontingent-Block aus (Korrektur/Override ignoriert, Hinweis „DB-Update fehlt?“). **DONE in P-2026-01-07-01**.
- **B-017:** Monatsübersicht zeigte bei Mitarbeiterwechsel teils nur „Tage mit Eintrag“ statt kompletter Monatstabelle. **DONE in P-2026-01-02-09**.
- **B-001:** `ZeitbuchungModel::holeFuerMitarbeiterUndZeitraum()` joint `terminal` und selektiert `t.bezeichnung` – laut DB ist die Spalte `terminal.name`. **DONE in P-2025-12-20-02**.
- **B-002:** `core/OfflineQueueManager.php` referenzierte `Database`-Methoden, die nicht existierten (potenzieller Fatal Error, sobald genutzt). **DONE in P-2025-12-20-04**.
- **B-003:** Konfiguration lag doppelt (`/config.php` und `/config/config.php`) – Master-Prompt fordert **genau eine** zentrale Config. **DONE in P-2025-12-20-03**.
- **B-004:** Genehmiger-Auswahl beim Mitarbeiter (Dropdown) speichert/lädt nun robuster (Fehlerfeedback + Nachladen inaktiver Genehmiger im Dropdown). **DONE in P-2025-12-20-08**.
- **B-010:** Terminal: `_autologout.php` Fallback ging auf `aktion=start` (Logout nicht garantiert) + Fokus-Ziel-Auswahl ohne ID-Priorität. **DONE in P-2025-12-31-17**.
- **B-011:** Terminal: Startscreen bekam in manchen Pfaden keinen `$csrfToken` gesetzt (Formulare sendeten leeren Token). **DONE in P-2025-12-31-35**.

## Offene Tasks (T-IDs, priorisiert)
- Praxis-Test: Bugs/Anomalien sammeln und als Micro-Patches beheben.
- Auftragsmodul: Scan-Flow/UX nur bei Bedarf weiter verfeinern (Praxis-Feedback).
- Terminal (Auftrag): Stop-Detailmaske (Fallback) UX vereinfachen (keine Status-Auswahl "Abschliessen/Abbrechen" im Terminal).

## Nächster Schritt (konkret)
- Praxis-Test: naechster Bug/Anomalie-Report (Micro-Patch).

## Letzter Patch (P-ID)
P-2026-01-25-02_dashboard-zeitwarnungen-derived-table.zip





## P-2026-01-25-02_dashboard-zeitwarnungen-derived-table.zip

### EINGELESEN (SHA256)
- 120428_gesammt.zip: 9d22b48346afced2758bf065f42fdecedb55a832ef6ab50132a7d9129a50939e
- docs/master_prompt_zeiterfassung_v12.md: 7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf
- docs/dev_prompt_zeiterfassung_v12.md: 8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e
- docs/rechte_prompt.md: 446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122
- docs/report_mehrfachbloecke_prompt_v1.md: 86e8c376bdbe962aa838cb113bd004624bf4440835e259c82e8bbe5de8c3655c
- sql/zeiterfassung_aktuell.sql: 9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee
- sql/offline_db_schema.sql: 165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9
- docs/DEV_PROMPT_HISTORY.md: 1f155d3df824a8f937cadeb890fa5131715d3fe50a99b798a08ec59ce732bc16

### DUPLIKAT-CHECK
- Kein Duplikat: neuer Derived-Table Query-Ansatz für Zeitwarnungen (robust gegen ONLY_FULL_GROUP_BY).

### DATEIEN
- controller/DashboardController.php
- views/dashboard/index.php
- docs/DEV_PROMPT_HISTORY.md

### DONE
- Zeitwarnungen-Query als Derived-Table umgesetzt (SQLMODE/ONLY_FULL_GROUP_BY robust) + Bind-Params (start_ts, today).
- Bei Fehlern wird die PDO-Fehlermeldung im UI nur für Legacy-Admin zusätzlich angezeigt.

### AKZEPTANZKRITERIEN
- Dashboard lädt Zeitwarnungen ohne Fehlermeldung und zeigt die Tabelle (oder leer, wenn nichts offen).
- Wenn DB-Fehler auftreten: roter Hinweis zeigt Debug-Fehlertext nur für Legacy-Admin.

### TEST
1. Dashboard als Chef/LegacyAdmin öffnen → Zeitwarnungen-Tabelle sichtbar.
2. Optional: SQLMODE ONLY_FULL_GROUP_BY aktivieren → Dashboard lädt weiterhin.

## P-2026-01-23-01
- ZIP: `P-2026-01-23-01_urlaub-halbtage-heiligabend-silvester.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193358_gesammt.zip` = `fde3ca1893ee855a6f678a9fc397a02c117dc59c0f9ddbf447c4b93b3bafd12b`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - SQL Snapshot: `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Offline Schema: `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Report-SoT: `docs/report_mehrfachbloecke_prompt_v1.md` = `86e8c376bdbe962aa838cb113bd004624bf4440835e259c82e8bbe5de8c3655c`
  - DEV_PROMPT_HISTORY (vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `453407e64e092d0d58520839214b41a98c5930f58196b4b8b478ec1b572db450`
- **DUPLICATE-CHECK:**
  - Kein Duplicate: P-2026-01-22-02 hat die Betriebsferien-Anzeige an die Mitarbeiter-spezifische BF-Logik gekoppelt; Abweichungen bleiben bestehen, wenn einzelne Tage als "halbe Urlaubstage" gewertet werden.
- **DATEIEN (max. 3):**
  - `services/UrlaubService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Urlaubstage-Gewichtung eingefuehrt: 24.12 und 31.12 zaehlen als 0.5 Urlaubstage.
  - Gilt konsistent fuer Betriebsferien-Zaehler (Terminal/PDF) und Urlaubsantraege (Arbeitstage-Zaehlung).
- **AKZEPTANZ:**
  - Terminal -> Urlaub-Uebersicht: Betriebsferien 18.12-31.12 zeigt bei gesetzlichem Feiertag 25.12 und Halbtagen (24.12/31.12) eine reduzierte Urlaubstage-Zahl (z. B. 8.00 statt 9.00), sodass Summe zur "Genehmigt"-Anzeige passt.
- **TEST (manuell):**
  1) Terminal -> Urlaub-Uebersicht oeffnen: Betriebsferien-Block (18.12-31.12) pruefen.
  2) Optional: Urlaubsantrag ueber 24.12 oder 31.12 anlegen und "Tage"-Berechnung kontrollieren.




## P-2026-01-22-02
- ZIP: `P-2026-01-22-02_urlaub-betriebsferien-saldo-order.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `195122_gesammt.zip` = `da86e295cd7e23ba18697ae5d224816d93f99b12e91b59472036de14e6e79243`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - SQL Snapshot: `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Offline Schema: `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Report-SoT: `docs/report_mehrfachbloecke_prompt_v1.md` = `86e8c376bdbe962aa838cb113bd004624bf4440835e259c82e8bbe5de8c3655c`
  - DEV_PROMPT_HISTORY (vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1bd126f741c55b32f305173ad5322d23af1b48f3fc9e339fe8ff69bcf298c434`
- **DUPLICATE-CHECK:**
  - Kein Duplicate: Betriebsferien-Anzeige rechnete bisher ohne Skip-Logik; Urlaubssaldo zieht Betriebsferien bereits mit Skip bei Arbeit/Kennzeichen/Krankzeitraum ab.
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Urlaub-Uebersicht (Betriebsferien): "benoetigte Urlaubstage" wird pro Eintrag ueber `UrlaubService::zaehleBetriebsferienArbeitstageFuerMitarbeiter()` berechnet (inkl. Skip bei Arbeit/Kennzeichen/Krankzeitraum) + Jahr-Schnittmenge.
  - Terminal Urlaub-Uebersicht (Saldo-Zeile): Reihenfolge umgestellt: Jahresanspruch | Uebertrag | Korrektur | Effektiv.
- **AKZEPTANZ:**
  - In der Terminal Urlaub-Uebersicht entspricht die Summe der angezeigten Betriebsferien-"benoetigte Urlaubstage" dem Urlaubsabzug, der im Urlaubssaldo unter "Genehmigt" sichtbar ist (wenn sonst kein genehmigter Urlaub existiert).
- **TEST (manuell):**
  1) Terminal -> Urlaub-Uebersicht oeffnen.
  2) "Genehmigt" mit Summe der Betriebsferien-Tage vergleichen.
- **NEXT:**
  - Optional: Gleiche Reihenfolge auch in `views/terminal/urlaub_beantragen.php` angleichen (separater Micro-Patch).


## P-2026-01-22-01
- ZIP: `P-2026-01-22-01_rundung-nullbloecke-ausblenden.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `043156_gesammt.zip` = `0dd6f40b2780f89af495dd92174ff54204833f13af74945accf0dfbd691b6d04`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - SQL Snapshot: `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Offline Schema: `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Report Mehrfachbloecke Prompt v1: `docs/report_mehrfachbloecke_prompt_v1.md` = `86e8c376bdbe962aa838cb113bd004624bf4440835e259c82e8bbe5de8c3655c`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `86f324cc8fb139462594c0f372625a1bd33fbf7e5ccb47144a220e7d1b48db55`
- **DUPLICATE-CHECK:**
  - Mikro-Filter war bereits am Rohstempel ausgerichtet (P-2026-01-17-12 / P-2026-01-21-01).
  - Neu war aber der Sonderfall **Rundung->0** (gehen_korr <= kommen_korr), der sonst in der UI als „11:15 gekommen, 11:00 gegangen“ verwirrt → daher **kein Duplicate**.
- **DATEIEN (max. 3):**
  - `views/report/monatsuebersicht.php`
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: Arbeitsbloecke mit **gehen_korr <= kommen_korr** werden als **Rundung->0** erkannt und wie Mikro behandelt (bei deaktivierter Mikro-Checkbox ausgeblendet).
  - Monatsuebersicht: Block-IST/Block-Sekunden liefern bei Rundung->0 **0** (kein `abs()` mehr).
  - Monatsreport-PDF: gleiche Logik (Mikro-Filter + Block-IST + Primaerzeilen-Dauer).
- **AKZEPTANZ:**
  - Bei aktivierten Rundungsregeln (Kommen=auf, Gehen=ab) werden kurze Randbloecke, die nach Rundung zu 0 werden, **nicht mehr** angezeigt (wenn Mikro aus).
  - Keine „korrigiert unmoegliche“ Zeit-Kombinationen mehr im Report bei deaktivierten Mikro-Buchungen.
- **TEST (manuell):**
  1) Rundungsregeln aktiv (wie in `zeit_rundungsregel`): Kommen aufrunden, Gehen abrunden.
  2) Tag mit Roh-Block 11:01–11:05 (korr 11:15–11:00) → Monatsuebersicht: Block **nicht sichtbar**, wenn Mikro aus.
  3) PDF exportieren → Block ebenfalls nicht sichtbar, wenn Mikro aus.
  4) Optional `?show_micro=1` → Block sichtbar, Block-IST = 0.00.
- **NEXT:**
  - Optional: In Monatsuebersicht bei `?show_micro=1` Rundung->0 optisch markieren (Label), um Debugging zu erleichtern.
## P-2026-01-21-03
- ZIP: `P-2026-01-21-03_pause-meta-primaerzeile.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `195646_gesammt.zip` = `a399d5e3eeb509c98beb60c23dfc18e3925e84cbcc5387a2d357b9cafbf22a4f`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - SQL Snapshot: `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Offline Schema: `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Report Mehrfachbloecke Prompt v1: `docs/report_mehrfachbloecke_prompt_v1.md` = `86e8c376bdbe962aa838cb113bd004624bf4440835e259c82e8bbe5de8c3655c`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `2836d26841240d4a60a7e5a06c45cf4e71cdf98d4a2a93982dd7e4f9437c4919`
- **DUPLICATE-CHECK:**
  - P-2026-01-21-02 hat IST je Block sichtbar gemacht. Meta-Felder (Pause/Kurzarbeit/Feiertag/Urlaub) standen aber weiterhin immer in der ersten Zeile → daher **kein Duplicate**, sondern Anzeige-Logik-Fix.
- **DATEIEN (max. 3):**
  - `views/report/monatsuebersicht.php`
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: pro Tag wird eine Primaer-Zeile ermittelt (erste sichtbare Blockzeile mit Dauer >= 60 Minuten, sonst die erste sichtbare Blockzeile).
  - Monatsuebersicht: Pause/Kurzarbeit/Feiertag/Urlaub werden nur in dieser Primaer-Zeile angezeigt.
  - Monatsreport-PDF: identische Primaer-Zeilen-Logik; Pause/Kurzar./Feiertag/Urlaub werden nur dort angezeigt.
- **AKZEPTANZ:**
  - Bei Mehrfach-Bloecken mit kurzem Randblock steht Pause nicht mehr im Randblock, sondern beim Hauptblock.
- **TEST (manuell):**
  1) Monatsuebersicht Tag mit 2+ Bloecken (z.B. 08:30-08:45 und 11:30-16:45) → Pause/Meta in der Hauptzeile.
  2) PDF exportieren → Pause/Meta ebenfalls in der Hauptzeile.
  3) Tag mit nur einem Block bleibt unveraendert.
- **NEXT:**
  - Praxis: Wenn Tages-IST (Summe) ebenfalls auf die Primaer-Zeile wandern soll, als separaten Micro-Patch spezifizieren.






## P-2026-01-21-02
- ZIP: `P-2026-01-21-02_ist-je-block-zeigen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193643_gesammt.zip` = `fc2df57c531ab06a288ad9cb1b2663e347d255b9c00b046b4ea61b268f15070d`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - SQL Snapshot: `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Offline Schema: `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `b274a604bb1de5ae66447561d92121eb964c1df2f4e0785ab428f203bc404b1f`
- **DUPLICATE-CHECK:**
  - Bereits vorhanden: Tages-IST korrekt als Summe der Bloecke (P-2026-01-20-06) + Block-Zeilen-Ansicht (P-2026-01-20-07).
  - Fehlte aber: In Folgezeilen der Mehrfach-Bloecke war die Spalte **Ist** leer → daher **kein Duplicate**, sondern reine Anzeige-Ergaenzung.
- **DATEIEN (max. 3):**
  - `views/report/monatsuebersicht.php`
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: Neue Helper-Funktion `report_calc_block_ist_dez2()` berechnet pro Block die Dauer (Roh-Paar bevorzugt, sonst Main-Paar; `abs()` Diff).
  - Monatsuebersicht: In **Folgezeilen** wird jetzt im Feld **Ist (gesamt)** die Blockdauer angezeigt (nicht mehr leer).
  - Monatsreport-PDF: In **Folgezeilen** wird in der Spalte **Ist** die Blockdauer angezeigt (Summenblock unten bleibt unveraendert).
- **AKZEPTANZ:**
  - Monatsuebersicht/PDF: Pro sichtbarem Block ist die IST-Spalte befuellt (Folgezeilen nicht leer).
  - Tages-/Monatssummen bleiben unveraendert (kommen weiter aus `tageswerte`).
- **TEST (manuell):**
  1) Monatsuebersicht mit Mehrfach-Bloecken oeffnen → Folgezeilen zeigen IST-Dauer je Block.
  2) PDF exportieren → Folgezeilen zeigen IST-Dauer je Block; Summen unten unveraendert.
- **NEXT:**
  - Patch-P3: Pause pro Block/Gap sinnvoll anzeigen (Mehrfach-Bloecke) in Monatsuebersicht + PDF.

## P-2026-01-21-01
- ZIP: `P-2026-01-21-01_pdf-mikrofilter-angleichen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `191359_gesammt.zip` = `6aca3d90c5ded61a100b34b13d2942c32ae5df85e7589cb1f433f929301393b5`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - SQL Snapshot: `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Offline Schema: `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ba1ee9cec9fde66db0bda2699b59712de1c1880009f751be908b462a0c92d42a`
- **DUPLICATE-CHECK:**
  - Es gibt `P-2026-01-13-05` (PDF Mikro-Buchungen ausblenden). Problem blieb: Mikro-Erkennung nutzte bevorzugt `*_korr` und scheiterte bei Rundungs-Ruecksprung (Ende < Start) → Block wurde nicht als Mikro erkannt und blieb sichtbar. Daher **kein Duplicate**, sondern Bugfix/Angleichen an Monatsuebersicht.
- **DATEIEN (max. 3):**
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - PDF-Mikro-Filter nutzt jetzt die gleiche Mikro-Erkennung wie `views/report/monatsuebersicht.php::report_is_micro_block()`:
    - Zuerst Rohzeiten (`kommen_roh/gehen_roh`) diffen und mit `abs()` bewerten.
    - Fallback auf Main-Paar (korr bevorzugt, sonst roh), ebenfalls `abs()`.
    - Grenzwert kommt aus `config.micro_buchung_max_sekunden` (Default 180s, Range-Guard 30..3600).
  - Ergebnis: Mikro-Bloecke werden im PDF **wirklich** ausgeblendet, auch wenn Rundung `Ende < Start` erzeugt.
- **AKZEPTANZ:**
  - Monats-PDF ohne `show_micro`: keine Mikro-Block-Zeilen (<= Grenzwert).
  - Monats-PDF mit `show_micro=1`: Mikro-Block-Zeilen sind sichtbar.
  - Verhalten ist bzgl. Mikro identisch zur Monatsuebersicht.
- **TEST (manuell):**
  1) Monatsuebersicht oeffnen (Micro aus) → PDF exportieren → Mikro-Zeilen sind weg.
  2) Monatsuebersicht oeffnen (Micro an / `show_micro=1`) → PDF exportieren → Mikro-Zeilen sichtbar.
- **NEXT:**
  - Patch-P2: IST je Block anzeigen (Folgezeilen nicht leer) in Monatsuebersicht + PDF.




## P-2026-01-20-08
- ZIP: `P-2026-01-20-08_prompt-report-mehrfachbloecke-v1.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `203417_gesammt.zip` = `e8ec9103494d19bfbd73281e1ac0bd0c1e4eccd172cabc9f5076ad580d26dc25`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `72ae2c93a49dfac584be33668cbf9efc8c6c46998f9e8af648738ed59ad8a00f`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-20-08` vorhanden → OK
- **DATEIEN (max. 3):**
  - `docs/report_mehrfachbloecke_prompt_v1.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neuer Prompt `report_mehrfachbloecke_prompt_v1.md` dokumentiert die Praxis-Probleme mit Mehrfach-Bloecken:
    - PDF zeigt Mikro-Bloecke obwohl `show_micro` aus ist.
    - IST/Pause wirken in Folgezeilen leer bzw. unlogisch platziert.
  - Erklaert das beobachtete Verhalten: Mikro-Grenze = `micro_buchung_max_sekunden` (Default 180s) → 3 Min = Mikro, 4 Min = nicht Mikro.
  - Root-Cause festgehalten: PDF-Mikro-Erkennung bevorzugt `*_korr` und scheitert bei Rundungs-Ruecksprung (Ende < Start) → Block wird nicht als Mikro erkannt.
  - Fix-Plan als Micro-Patches (P1-P3) beschrieben.
- **NEXT:**
  - Patch-P1: PDF Mikro-Filter an Monatsuebersicht angleichen (Rohdiff + abs, Fallback), damit Mikro-Bloecke ohne `show_micro` nie im PDF landen.


## P-2026-01-20-07
- ZIP: `P-2026-01-20-07_report-zeige-mehrfachbloecke.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `200902_gesammt.zip` = `fa1f6a7b056738154afca9282a56d6def3ae4f2bf115263b349a41e1f0790af2`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `6625e09970d520d71588896031b64e71c4ecec641fb029a3c3c69a0b7fab7a57`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-20-07` vorhanden → OK
- **DATEIEN (max. 3):**
  - `views/report/monatsuebersicht.php`
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht (HTML): Mehrfach-Kommen/Gehen wird standardmaessig als **mehrere Zeilen pro Tag** angezeigt.
  - Monatsreport-PDF: Mehrfach-Kommen/Gehen wird ebenfalls als **mehrere Block-Zeilen** ausgegeben.
  - Mikro-Bloecke (<= 3 Minuten) bleiben standardmaessig ausgeblendet (wie zuvor).
- **Akzeptanz:**
  - Ein Tag mit 2 Arbeitsbloecken zeigt 2 Zeilen (An/Ab) im HTML-Raster und im PDF.
  - Tages-IST bleibt die **Summe** der Bloecke (Fix aus P-2026-01-20-06).
  - Mikro-Buchungen erscheinen nur bei `?show_micro=1`.
- **NEXT:**
  - Warten auf Praxis-Test Feedback; weitere Micro-Patches nur bei Bugs/Wuenschen.



## P-2026-01-20-06
- ZIP: `P-2026-01-20-06_report-multi-block-ist-sum.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `190549_gesammt.zip` = `fa6db6a7d604793255b9f3f5622a524cc17db2cf07695247b9db6df1efea9d72`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - Rechte-Prompt (SoT): `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - DB-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `75468c0ca2681fd64be2330d360f7573bfbadf20947f3afaebe683a89bafa849`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-20-06` vorhanden → OK
- **DATEIEN (max. 3):**
  - `services/ReportService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht/PDF: Tages-IST wird nicht mehr als **Min/Max-Spanne** berechnet.
  - Stattdessen wird die Arbeitszeit als **Summe echter Arbeitsbloecke** (Kommen→Gehen Paare) gewertet.
  - Mikro-Bloecke (< `MICRO_ARBEITSZEIT_GRENZE_STUNDEN`) werden dabei weiterhin ignoriert.
  - Ergebnis: Mehrfach-Kommen/Gehen (z. B. 2 Schichten am selben Tag) wird korrekt summiert und Unterbrechungen zaehlen nicht als Arbeitszeit.
- **Akzeptanz:**
  - Ein Tag mit 2 Arbeitsbloecken (z. B. 07:00-15:00 und 19:00-21:00) ergibt IST = Summe beider Bloecke minus Pause (keine Anrechnung der Luecke dazwischen).
  - Mikro-Buchungen bleiben im Report als "nicht gearbeitet" gewertet.
  - Monats-Istsumme passt zur Summierung der Tageswerte.
- **NEXT:**
  - Monatsuebersicht + PDF: Anzeige der Mehrfach-Bloecke im An/Ab-Feld (z. B. als Zeilenumbruch/Liste), ohne das Raster zu sprengen. **DONE in P-2026-01-20-07**.


## P-2026-01-20-05
- ZIP: `P-2026-01-20-05_docs-devhistory-agpl.zip`
- **DATEIEN (max. 3):**
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - DEV_PROMPT_HISTORY nachgezogen: Snapshot + Log fuer den Lizenzwechsel auf GNU AGPLv3 (P-2026-01-20-04).
- **NEXT:**
  - Optional: README/NOTICE + UI-Hinweis "Quellcode erhalten" falls extern ausgerollt.


## P-2026-01-20-04
- ZIP: `P-2026-01-20-04_oss-agpl-license-footer.zip`
- **DATEIEN (max. 3):**
  - `LICENSE`
  - `views/layout/footer.php`
  - `docs/dev_prompt_zeiterfassung_v12.md`
- **DONE:**
  - Lizenzwechsel von MIT auf **GNU AGPLv3** (SPDX: `AGPL-3.0-or-later`) – Copyleft inkl. Netzwerkklausel (SaaS/Web).
  - Footer-Hinweis aktualisiert: "Erdacht von Manuel Kleespies · Open Source (GNU AGPLv3)".
  - Dev-Prompt Lizenzkapitel aktualisiert.
- **NEXT:**
  - Optional: README/NOTICE + UI-Hinweis "Quellcode erhalten" falls extern ausgerollt.


## P-2026-01-20-02
- ZIP: `P-2026-01-20-02_oss-mit-license-footer.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `171908_gesammt.zip` = `c95b23187c0a7610db14610f04eeade80b7cfacbe7e61bad1a5e792c20a77ca8`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `2929407f042f95ab15650247a35b9fe5e4cfe0662d4478310d87246c94e2b6c7`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `24c5aabb2d185ebd9c143c306c31f606687024b3d8ea8ddbb96cfb0ef2ee8fd9`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-20-02` vorhanden → OK
- **DATEIEN (max. 3):**
  - `LICENSE`
  - `views/layout/footer.php`
  - `docs/dev_prompt_zeiterfassung_v12.md`
- **DONE:**
  - Open Source: MIT-Lizenz hinzugefuegt (Datei `LICENSE`).
  - UI-Footer: Hinweis "Erdacht von Manuel Kleespies" + "Open Source (MIT License)".
  - Dev-Prompt v12: Lizenz-Hinweis dokumentiert.
- **NEXT (optional):**
  - README kurz ergaenzen + ggf. SPDX-Header in Kern-Dateien.


## P-2026-01-20-01
- ZIP: `P-2026-01-20-01_terminal-nebenauftrag-stop-clean.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `043636_gesammt.zip` = `d9ccb6cceb364d09ac6eb56fbdf658ec6d1d07a3be1de402410f932eb91c7c5d`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `2929407f042f95ab15650247a35b9fe5e4cfe0662d4478310d87246c94e2b6c7`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `e30ec6c7abd8c4fe14084a3695674d8c32e8ad8d66fa8a15b1e5680bf7d97a08`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-20-01` vorhanden → OK
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Nebenauftrag stoppen – Dropdown hat keinen leeren Platzhalter mehr (erster laufender Nebenauftrag ist vorausgewaehlt).
  - Terminal: Status-Auswahl entfernt (einfach stoppen; intern wird als `abgeschlossen` gespeichert).
  - Terminal: Auftragscode-Scan hat Vorrang vor Dropdown-ID (wichtig bei mehreren Nebenauftraegen).
- **Akzeptanz:**
  - In `terminal.php?aktion=nebenauftrag_stoppen` ist im Online-Modus der erste laufende Nebenauftrag sofort ausgewaehlt.
  - Es gibt keinen Status-Dropdown mehr.
  - Scan eines Auftragscodes stoppt den passenden Nebenauftrag auch dann, wenn im Dropdown ein Eintrag vorausgewaehlt ist.





## P-2026-01-19-03
- ZIP: `P-2026-01-19-03_docs-devprompt-history-backfill.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193739_gesammt.zip` = `4e2ccb19eee9ffb131b5c0d5da1ba17b4427bfb55667ba035401046743c0ee33`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `67db39c5d74b03fad6fec66ec78be13fad316732ce774a6aec75bf249582d83c`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `5d2f928afa7283bc7ad75d142eacbe73fdaadd460a97d8cbd80a92fe9a364e61`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-19-03` vorhanden → OK
- **DATEIEN (max. 3):**
  - `docs/dev_prompt_zeiterfassung_v12.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - DEV-Prompt v12: Kiosk-Flow fuer Auftrag (Auto-Logout) + Quick-Stop dokumentiert.
  - DEV_PROMPT_HISTORY: SNAPSHOT aktualisiert und P-2026-01-19-01/02 nachgetragen.
- **NEXT:**
  - Optional: Terminal Stop-Detailmaske (Fallback) verschlanken/entfernen, wenn sie noch stoert.




## P-2026-01-19-02
- ZIP: `P-2026-01-19-02_terminal-hauptauftrag-stop-quick.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193739_gesammt.zip` = `4e2ccb19eee9ffb131b5c0d5da1ba17b4427bfb55667ba035401046743c0ee33`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `67db39c5d74b03fad6fec66ec78be13fad316732ce774a6aec75bf249582d83c`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `5d2f928afa7283bc7ad75d142eacbe73fdaadd460a97d8cbd80a92fe9a364e61`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-19-02` vorhanden → OK
- **DATEIEN (max. 3):**
  - `views/terminal/start.php`
  - `public/terminal.php`
  - `controller/TerminalController.php`
- **DONE:**
  - Terminal: Wenn ein Hauptauftrag laeuft, stoppt der Startscreen-Button jetzt **direkt** (POST+CSRF) ueber `aktion=auftrag_stoppen_quick`.
  - Kein Zwischenscreen mehr fuer "Hauptauftrag stoppen" im Normalfall (Stop-Detailmaske bleibt nur Fallback).
- **NEXT:**
  - Optional: Stop-Detailmaske (Fallback) UX verschlanken (keine Status-Auswahl, keine "Letzten ... abschliessen"-Buttons).


## P-2026-01-19-01
- ZIP: `P-2026-01-19-01_terminal-auftrag-kiosk-flow.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193739_gesammt.zip` = `4e2ccb19eee9ffb131b5c0d5da1ba17b4427bfb55667ba035401046743c0ee33`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `67db39c5d74b03fad6fec66ec78be13fad316732ce774a6aec75bf249582d83c`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `5d2f928afa7283bc7ad75d142eacbe73fdaadd460a97d8cbd80a92fe9a364e61`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Eintrag `P-2026-01-19-01` fehlte (Patch wurde integriert, aber ohne Log) → jetzt nachgetragen.
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
- **DONE:**
  - Terminal (Auftrag): Nach **Auftrag starten/stoppen** sowie **Nebenauftrag starten/stoppen** wird der Mitarbeiter am Terminal **abgemeldet** und es wird wieder die RFID-Abfrage gezeigt.
  - Text/Benennung: "beenden" wurde im Kontext Auftrag auf "stoppen" umgestellt (Zeiterfassung, kein Finalisieren erforderlich).
- **NEXT:**
  - Quick-Stop vom Startscreen (ohne Stop-Detailmaske) umsetzen (DONE in P-2026-01-19-02).


## P-2026-01-18-23
- ZIP: `P-2026-01-18-23_terminal-start-auftrag-buttons.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `144717_gesammt.zip` = `5b8e3e0297a4c1fb796244b0adcb1efafb42f2d0d7dc10360ee44d5d2df216e0`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8746c351fa67e429595d7e6ba0f2cdc50068c6929c4328f6a8c238843572056c`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-23` vorhanden → OK
- **DATEIEN (max. 3):**
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: `Auftrag stoppen` wird nur angezeigt, wenn ein Hauptauftrag laeuft (online via DB, offline via Session-Fallback `terminal_letzter_auftrag`).
  - Nebenauftrag-Buttons: Werden nur angezeigt, wenn ein Auftrag laeuft (Haupt oder Neben). `Nebenauftrag starten` nur, wenn ein Hauptauftrag laeuft.
- **NEXT:**
  - Backend Auftrag-Detail: `arbeitsschritt_code` anzeigen und optional nach Arbeitsschritt gruppieren (mit Summen je Schritt).





## P-2026-01-18-22
- ZIP: `P-2026-01-18-22_terminal-auftrag-arbeitsschritt-input.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `111001_gesammt.zip` = `ea50164f9a3e469fa51c3aae0f67fcb6098160d29a62e8bdfcaf5f3cb0449c48`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8746c351fa67e429595d7e6ba0f2cdc50068c6929c4328f6a8c238843572056c`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-22` vorhanden → OK
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `services/AuftragszeitService.php`
  - `views/terminal/auftrag_starten.php`
- **DONE:**
  - Terminal Auftrag starten: Arbeitsschritt-Code (Scan) ist Pflicht und wird in `auftragszeit.arbeitsschritt_code` gespeichert.
  - Service/Controller: Start-Insert uebernimmt `arbeitsschritt_code` (ohne bestehende Auftragslogik zu entfernen).
- **NEXT:**
  - Startscreen: Buttons kontextabhaengig (Stop nur bei laufendem Auftrag, Nebenauftrag erst nach Hauptauftrag).
  - Hinweis: DEV_PROMPT_HISTORY war in diesem Patch nicht enthalten und wird mit P-2026-01-18-23 nachgezogen.





## P-2026-01-18-21
- ZIP: `P-2026-01-18-21_db-auftragszeit-arbeitsschritt-schema.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `105558_gesammt.zip` = `0b3aaaf08260d51525e68d69fd558db2108b9f1fe0b21e3cbf09f3316dbeb655`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `cba7acf016131918db114a51142f443cb3236ff0ff2689f8b85f6ac4148b4461`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `6feea16246999086a0591a62fc5a7b2775d45a368f59e859bc42f4be472c44d8`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-21` vorhanden → OK
- **DATEIEN (max. 3):**
  - `sql/01_initial_schema.sql`
  - `sql/23_migration_auftragszeit_arbeitsschritt_code.sql`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - DB/Schema: `auftragszeit` ergaenzt um `arbeitsschritt_code` (nullable) als Grundlage fuer Arbeitsschritt-Scan.
  - Indizes fuer schnellere Auswertung: (`auftrag_id`,`arbeitsschritt_code`) und (`auftragscode`,`arbeitsschritt_code`).
  - Migration 23 fuer Bestands-DB hinzugefuegt.
- **NEXT:**
  - Terminal: Arbeitsschritt-Scan im Auftrag-Start (Auftrag + Arbeitsschritt + optional Maschine), ohne bestehende Buchungslogik zu entfernen.
  - Service: Unbekannten Auftragscode in Tabelle `auftrag` automatisch anlegen (idempotent).
  - Backend: Auftrag-Detail nach Arbeitsschritt gruppieren + Gesamtsummen.
## P-2026-01-18-20
- ZIP: `P-2026-01-18-20_backend-auftrag-menue-link.zip`
- **DATEIEN (max. 3):**
  - `views/layout/header.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Neuer Menuepunkt "Auftraege" im Header (klickt auf `?seite=auftrag`).
  - Active-State wird fuer `auftrag` und `auftrag_detail` gesetzt.
- **NEXT:**
  - Auftragsmodul v1: Arbeitsschritt-Scan + DB-Design (Auftragcode + Arbeitsschrittcode + optional Maschine) und danach Terminal-Flow in Micro-Schritten.








## P-2026-01-18-19
- ZIP: `P-2026-01-18-19_backend-auftrag-routes.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `102952_gesammt.zip` = `07fbf4b70ad2d7eb85e9c6d20d5bc46bfa0b8ba6c5b660848ccbe8dcb6adfa5a`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `cf2c9effa4f43a46a8509b3d39b44ff8e231262e4b242b86ee6df41adb2c5604`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-19` vorhanden → OK
- **DATEIEN (max. 3):**
  - `public/index.php`
  - `controller/AuftragController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Neue Routen `?seite=auftrag` + `?seite=auftrag_detail&code=...` (Login required) zeigen eine erste Auftragsauswertung aus `auftragszeit` (Mitarbeiter/Maschine/Zeiten + Gesamtstunden).
  - Akzeptanzkriterium: Wenn Buchungen in `auftragszeit` mit `auftragscode` existieren, zeigt `?seite=auftrag` diese Auftraege und `?seite=auftrag_detail&code=...` listet die Einzelbuchungen inkl. Summenstunden.
- **NEXT:**
  - Micro-Patch: Menue-Link "Auftraege" in `views/layout/header.php` ergaenzen.
  - Danach: Arbeitsschritt-Nummern + Scan-Flow (Auftrag + Arbeitsschritt + optional Maschine) inkl. DB-Erweiterung und Terminal-UI.
## P-2026-01-18-17
- ZIP: `P-2026-01-18-17_prompts-v12-auftrag-scope.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `095514_gesammt.zip` = `f779bfdf4048eb728d5d07727a0fc09d76844fa054157589c888aa26a356f2f6`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v11.md` = `07f4ecf92815a34dca61d9cfe2fc85c189e3e9a481c629b4d5b6943fe4dc3057`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `23316f3e9628656bb6d62672619069e5a4ea2309e367beb7841e526f26dac8b0`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `6feea16246999086a0591a62fc5a7b2775d45a368f59e859bc42f4be472c44d8`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Keine v12-Prompts vorhanden; Auftrags-Scan/Arbeitsschritt-Spezifikation war bisher nicht als eigener Prompt dokumentiert.
- **DATEIEN (max. 3):**
  - `docs/master_prompt_zeiterfassung_v12.md`
  - `docs/dev_prompt_zeiterfassung_v12.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Master-Prompt v12: Doku-Regel ergaenzt, dass neue Funktionsbereiche zuerst als separate Prompt-Datei spezifiziert werden (Auftragsmodul geplant).
  - Dev-Prompt v12: Status beibehalten, Auftragsmodul als Doku-Scope referenziert.
  - DEV_PROMPT_HISTORY: SNAPSHOT aktualisiert + Patch dokumentiert.
  - Akzeptanzkriterium: In Master- und Dev-Prompt steht, dass das Auftragsmodul zuerst als eigener Prompt spezifiziert wird und die Implementierung nur auf ausdruecklichen Auftrag erfolgt.
- **NEXT:**
  - Doku-Patch: `docs/archiv/auftrags_prompt_v1.md` erstellen (Auftrag + Arbeitsschritte + optional Maschine/Barcode) ohne bestehende Funktionen anzutasten.


## P-2026-01-18-16
- ZIP: `P-2026-01-18-16_prompts-v11-praxistest-fertig.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `091622_gesammt.zip` = `6852e7661ed102ac811051909aa09de3c25ddd8f2e631f40d6ee5e7682f46457`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ddbcf3393822d2504a79c03e17b0701732a4f35df14cb9c070b039aef7a3045f`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `6feea16246999086a0591a62fc5a7b2775d45a368f59e859bc42f4be472c44d8`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag, der den Projektstatus explizit auf "FERTIG/Praxis-Test (Bugfix only)" setzt; v11-Prompts fehlten.
- **DATEIEN (max. 3):**
  - `docs/master_prompt_zeiterfassung_v11.md`
  - `docs/dev_prompt_zeiterfassung_v11.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Master-Prompt v11 erstellt: Projektstatus "FERTIG/Praxis-Test" + Arbeitsmodus (Bugfix/Micro-Patches).
  - Dev-Prompt v11 (kurz) erstellt: kompakte Uebergabe-Datei; Historie bleibt in `DEV_PROMPT_HISTORY.md`.
  - DEV_PROMPT_HISTORY: SNAPSHOT aktualisiert (Status/Tasks/Next) + dieser Patch dokumentiert.
  - Akzeptanzkriterium: In beiden Prompts steht klar, dass nur noch Bugfix/Bedarf im Praxis-Test gemacht wird.
- **NEXT:**
  - Keine Roadmap mehr: Weiter nur bei konkretem Bug/Bedarf.


## P-2026-01-18-15
- ZIP: `P-2026-01-18-15_mein-urlaub-bf-restjahr-authfix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `081724_gesammt.zip` = `299149501db87a37b37a5c367fda10bb8f439d1b5d9d29c3059a5ae2970b5b83`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `f34c4c61e00b2677c9710bc0bc0f9c8547293c3cea4e25dc5b1da4f97c780835`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `6feea16246999086a0591a62fc5a7b2775d45a368f59e859bc42f4be472c44d8`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Backend "Mein Urlaub" sollte `BF (Rest Jahr)` anzeigen; in `views/urlaub/meine_antraege.php` wurde dafuer noch `Auth::getInstanz()` genutzt (Session-Key mismatch) → Micro-Patch erforderlich.
- **DATEIEN (max. 3):**
  - `views/urlaub/meine_antraege.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `BF (Rest Jahr)`-Anzeige nutzt `AuthService->holeAngemeldeteMitarbeiterId()` (korrekte Session-ID) statt Legacy-Auth.
  - Akzeptanzkriterium: Backend → Mein Urlaub → Meine Urlaubsantraege zeigt `BF (Rest Jahr)` fuer eingeloggten Benutzer (auch `0,00` moeglich).
- **NEXT:**
  - Stabilitaet: Backend weiter klicken (T-069 Teil 2a/2b/2c) und naechste Bugs als Micro-Patches abarbeiten.
## P-2026-01-18-13
- ZIP: `P-2026-01-18-13_backend-report-year-month-clamp.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `070303_gesammt.zip` = `2a04f3ce3902dc6d3292d42b5d6a5bdc9303cd9e0d46c334b05f62f88628d586`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a0631b8239d7214d14deb21ec5d6c08ba72ab17c0a6960b03b9e33a7309d45aa`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `6feea16246999086a0591a62fc5a7b2775d45a368f59e859bc42f4be472c44d8`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Clamp/Guard fuer ungueltige `jahr/monat` Werte in `public/index.php` vorhanden → Micro-Patch erforderlich.
- **DATEIEN (max. 3):**
  - `public/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Router: `normalize_jahr_monat()` eingefuegt und fuer `report_monat`, `report_monat_pdf`, `report_monat_export_all` verwendet (monat 1..12, jahr 2000..2100).
  - Akzeptanzkriterium: `?seite=report_monat&jahr=2026&monat=13` zeigt 12/2026 ohne Fehler; `monat=0` zeigt 01/2026.
- **NEXT:**
  - Stabilitaet: Backend – Monatsuebersicht/PDF weiter klicken (T-069 Teil 2a/2b) und naechste Bugs als Micro-Patches abarbeiten.


## P-2026-01-18-10
- ZIP: `P-2026-01-18-10_mitarbeiter-eintrittsdatum-db-urlaubservice.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `061616_gesammt.zip` = `e483382b2b93cf2dee240778ee1d4a1a28e2db74a919dfcf4536ea8c3156b890`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `e68ec7f20f84800844ce612b6405940f09cd736056d68a11a18d0ba99f66645c`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Ein explizites Mitarbeiter-`eintrittsdatum` (Schema + Nutzung im UrlaubService) war noch nicht vorhanden.
- **DATEIEN (max. 3):**
  - `sql/22_migration_mitarbeiter_eintrittsdatum.sql`
  - `services/UrlaubService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - DB: Migration 22 fuegt optional `mitarbeiter.eintrittsdatum` (DATE, NULL) hinzu (idempotent).
  - UrlaubService: Anspruch/Eintrittsjahr-Berechnung nutzt `eintrittsdatum`, falls gepflegt; sonst Fallback auf `erstellt_am`.
- **NEXT:**
  - Backend: Mitarbeiter anlegen/bearbeiten um Feld **Eintrittsdatum** erweitern (lesen/speichern) + danach `sql/01_initial_schema.sql` (SoT) nachziehen.


## P-2026-01-18-09
- ZIP: `P-2026-01-18-09_urlaub-eintritt-uebertrag-negativ.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `055749_gesammt.zip` = `bee852bf86850fd412259b4a89b3a81066494b84607e4e2e87f895c6baf8c274`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `2537de48c7334e3a89e3a666da886b94439d65efc79148c64abd0c4c300647ae`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Eintrittsjahr-Pro-Rata & negativer Auto-Uebertrag waren noch nicht umgesetzt → Micro-Patch erforderlich.
- **DATEIEN (max. 3):**
  - `services/UrlaubService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - UrlaubService: Standard-Anspruch (Monatsanspruch) wird im Eintrittsjahr anteilig ab Eintrittsmonat berechnet (Eintritt = `mitarbeiter.erstellt_am`).
  - UrlaubService: Auto-Übertrag (Vorjahr) uebernimmt auch negative Restwerte, sodass Minusurlaub ins Folgejahr mitgenommen und dort verrechnet wird.
- **NEXT:**
  - **B-080** weiter pruefen: Urlaub-Saldo-Darstellung/Drift ("Meine Urlaubsantraege" vs Monatsreport/PDF) bei Grenzfaellen.


## P-2026-01-18-01
- ZIP: `P-2026-01-18-01_report-pdf-link-show-micro.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `033007_gesammt.zip` = `d205089a183af0a83ee18e94468ed7852f4e98116100933e148911fc71f97a0c`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `268a799524715a9aeff8f86baace4373c8cd349045688df1a3ba009694665e19`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: `show_micro` war in Monatsuebersicht (Checkbox) und im PDF-Service bereits umgesetzt, aber der "PDF anzeigen"-Link hat den Parameter noch nicht weitergereicht → Micro-Patch erforderlich.
- **DATEIEN (max. 3):**
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: Wenn "Mikro-Buchungen anzeigen" aktiv ist, oeffnet "PDF anzeigen" das PDF mit `&show_micro=1`.
- **NEXT:**
  - Stabilitaet: Monatsuebersicht/PDF weiter klicken (T-069 Teil 2a/2b) und naechste Bugs als Micro-Patches abarbeiten.


## P-2026-01-17-29
- ZIP: `P-2026-01-17-29_bugfix-b078-microbuchungen-aggregiert.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `203513_gesammt.zip` = `9cd4365c6f1b0f836ca8305b902c0d2e40a15f20dd85202d02fcb3cecc69326b`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `c83bcaea8c5e84344721ddac0f84ed67e3e6a79b00edc72fe493192d96769697`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Bug **B-078** war als OFFEN dokumentiert (Regression gegen P-2026-01-17-12) → Fix erforderlich.
- **DATEIEN (max. 3):**
  - `views/report/monatsuebersicht.php`
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: Standard wieder aggregiert (1 Zeile/Tag: erster Start + letzter Endzeit). Detail-/Mikro-Buchungen werden nur mit `?show_micro=1` angezeigt.
  - Monatsreport-PDF: Standard wieder aggregiert (1 Zeile/Tag). Detail-/Mikro-Buchungen nur mit `?show_micro=1`.
- **NEXT:**
  - Stabilitaet: Monatsuebersicht/PDF weiter klicken (T-069 Teil 2a/2b) und naechste Bugs als Micro-Patches abarbeiten.


## P-2026-01-09-20
- ZIP: `P-2026-01-09-20_terminal-ws-config.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `200050_gesammt.zip` = `a4fa9bd0d1222ad42f0fd46500b001aea250c43c4d097d82ed06e2d9cf5b0c6a`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8535d4bef8337ab02309fb531084105e989b9d5a6fc25f2ecc9e8f28626da61a`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - Zusatz (Offline-Schema): `offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Sidequest "RFID-Bridge konfigurierbar" war als **Next Step** offen und noch nicht als DONE markiert → Implementierung erforderlich.
- **DATEIEN (max. 3):**
  - `config/config.php`
  - `views/terminal/_autologout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: RFID-Bridge (WebSocket) ist jetzt zentral konfigurierbar (enable + WS-URL) in `config/config.php`; bei `enabled=false` wird das WS-Script nicht eingebunden.
  - Akzeptanzkriterium: Wenn `terminal.rfid_ws.enabled=false` gesetzt ist, wird im Terminal keine WS-Verbindung aufgebaut; wenn `url` geaendert wird, nutzt der Browser diese URL.
- **NEXT:**
  - Terminal: WS-Bridge Status im Debug-Panel anzeigen (connected/disconnected, letzte UID + Zeit, Reconnect-Counter).


## P-2026-01-09-22
- ZIP: `P-2026-01-09-22_terminal-ws-reconnect-backoff.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `203002_gesammt.zip` = `2179a65f6691dace058917bf618fbb94150635b876f58af19b2365a78c5154b0`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8535d4bef8337ab02309fb531084105e989b9d5a6fc25f2ecc9e8f28626da61a`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Sidequest „WS-Bridge stabilisieren/Backoff“ war nicht als DONE markiert.
  - Code-Check: Es gab bereits einen einfachen Reconnect (Backoff-Grundstruktur), aber ohne klare Statusanzeige (Delay/Versuch) und ohne defensive Error-Kante → Implementierung erforderlich.
- **DATEIEN (max. 3):**
  - `public/js/terminal-rfid-ws.js`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: WS-Bridge Reconnect ist stabiler: Backoff 1/2/5/10s (gedeckelt) mit Status „Reconnect in Xs – Versuch N“, Reset des Counters bei erfolgreichem Connect sowie defensive Behandlung fuer Browser-Kanten (Error ohne Close) und leichter DOM-Update-Guard gegen Status-Spam.
  - Akzeptanzkriterium: Wenn der WS-Dienst neu startet oder die Verbindung kurz weg ist, zeigt das Terminal einen nachvollziehbaren Reconnect-Countdown mit Versuch-Zähler und verbindet sich automatisch wieder.
- **NEXT:**
  - Rollout-Standard fuer Terminals: `rfid-ws.service` (Systemd) + Kurz-Checkliste in `docs/`, damit weitere Probe-Terminals reproduzierbar aufgesetzt werden koennen.


## P-2026-01-09-23
- ZIP: `P-2026-01-09-23_terminal-ws-service-rollout.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `203616_gesammt.zip` = `__WIRD_IM_BUILD-SCHRITT_BERECHNET__`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `__WIRD_IM_BUILD-SCHRITT_BERECHNET__`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Sidequest „Rollout-Standard fuer Terminals“ war als NEXT markiert und noch nicht als DONE umgesetzt.
- **DATEIEN (max. 3):**
  - `docs/terminal/rfid-ws.service`
  - `docs/terminal/rfid-ws_rollout.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Rollout: Systemd-Service-Vorlage (`rfid-ws.service`) + Install/Healthcheck-Checkliste in `docs/terminal/` hinzugefuegt.
  - Akzeptanzkriterium: Ein neues Terminal kann den Dienst reproduzierbar per `systemctl enable --now rfid-ws.service` starten; Port/Healthcheck und Logs sind dokumentiert.
- **NEXT:**
  - Offline-Queue End-to-End Feldtest auf dem Probe-Terminal (online → offline → online) und danach ggf. Bugfixes aus realen Kantenfaellen.


## P-2026-01-09-24
- ZIP: `P-2026-01-09-24_terminal-offlinequeue-statusbox.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `204135_gesammt.zip` = `af18906ccdb136a61620c076e121ae209c3ecc991c1979f1e106354d798fb199`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `954e31d3855240f496a0a3ccce9a6e117cc3c040cc2969ea246f2707110e6007`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Vorher wurde die Offline-Queue nur im Debug-Details-Bereich (Top-10 Liste) sichtbar; es fehlte ein sofortiger Hinweis im Normal-Screen bei offenen/fehlerhaften Queue-Eintraegen.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Offline-Queue Status wird beim Start gelesen (Counts offen/fehler/verarbeitet; letzter Fehler kurz).
  - UI: Wenn `fehler>0` oder `offen>0`, erscheint automatisch eine Statusbox (warn/error) mit klarer Handlungsinfo.
- **AKZEPTANZ:**
  - Wenn im Terminal lokale Queue-Eintraege offen sind oder Fehler haben, sieht man das sofort als Warn-/Fehler-Box ohne Debug-Menue.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Feldtest: Haupt-DB offline → mehrere Kommen/Gehen buchen → Haupt-DB online → Queue-Replay beobachten; falls Replay nicht automatisch triggert, einen kleinen Trigger im Terminal-Flow ergaenzen.


# ARCHIV (unverändert übernommen)

# Zeiterfassung – Dev-Verlauf & Arbeits-Prompt

## 1. Projektziel (Kurzbeschreibung)

PHP-basierte Zeiterfassung und Verwaltungsanwendung mit:

- Kommen/Gehen-Zeiterfassung über Terminals (RFID / Barcode / Touch).
- Urlaubs- und Abwesenheitsverwaltung mit Genehmiger-Modell.
- Optionale Auftragszeiterfassung (Maschinen- / Auftragszeiten).
- Offline-fähigen Terminals, die SQL-Injektionen puffern und später an die Hauptdatenbank senden.
- Klarer Trennung von Schichten:
  - **core/**: Basisfunktionen (Datenbank, Autoloader, Logging).
  - **modelle/**: Reine DB-Modelle (CRUD, keine Businesslogik).
  - **services/**: Geschäftslogik.
  - **controller/**: HTTP-Request-Verarbeitung.
  - **views/**: PHP-Templates/HTML.
  - **public/**: Front-Controller, Terminal-Entry, Assets.
  - **sql/**: Datenbankschemata.
  - **docs/**: Dokumentation, Master-Prompt, Dev-Verlauf.

Keine externen PHP-Frameworks, nur Plain-PHP nach den Regeln aus dem Master-Prompt.

---

## 2. Aktueller Stand (2025-11-29)

**Core**

- `core/Database.php`
  - Singleton-PDO-Wrapper für Hauptdatenbank und optionale Offline-Datenbank.
  - Hilfsfunktionen: `fetchAlle()`, `fetchEine()`, `ausfuehren()`, `letzteInsertId()`.
- `core/Logger.php`
  - Schreibt Logs in Tabelle `system_log` (Fallback: `error_log()`).
  - Loglevel: `debug`, `info`, `warn`, `error`.
- `core/Autoloader.php`
  - Namenskonvention:
    - `*Model` → `modelle/`,
    - `*Service` → `services/`,
    - `*Controller` → `controller/`,
    - sonst → `core/` bzw. Projektroot.

**Konfiguration & Doku**

- `config/config.php`
  - DB-Konfiguration (`db`), optionale `offline_db`, `modus` (`backend`/`terminal`), `base_url`, `timezone`.
- `sql/01_initial_schema.sql`
  - Initiales Datenbankschema (aus Upload übernommen).
- `docs/master_prompt_zeiterfassung_v2.md`
  - Master-Prompt mit allen Regeln für dieses Projekt.
- `docs/README_zeiterfassung.md`
  - Überblick über Verzeichnisstruktur und Zweck.
- `docs/DEV_PROMPT_HISTORY.md`
  - (Diese Datei) – beschreibt Verlauf und aktuellen Arbeits-Prompt.

**Modelle (Auszug der wichtigsten)**

- `MitarbeiterModel` – Zugriff auf Mitarbeiterdaten (inkl. Login-relevanter Felder).
- `UrlaubsantragModel` – Urlaubsanträge.
- `ZeitbuchungModel`, `TageswerteMitarbeiterModel`, `MonatswerteMitarbeiterModel` – Zeitdaten.
- `AuftragszeitModel`, `AuftragModel` (optional) – Auftragszeiten / Auftragsstammdaten (lokaler Cache).
- `TerminalModel` – Terminals (Stationen).
- `MaschineModel` – Maschinenstammdaten.
- `AbteilungModel` – Abteilungen.
- `RolleModel`, `MitarbeiterHatRolleModel`, `MitarbeiterHatAbteilungModel`, `MitarbeiterGenehmigerModel` – Rollen- und Genehmiger-Modell.
- `FeiertagModel`, `BetriebsferienModel` – Feiertage und Betriebsferien.
- `ZeitRundungsregelModel` – Konfigurierbare Rundungsregeln.
- `DbInjektionsqueueModel` – Offline-Queue für SQL-Injektionen.
- `SystemLogModel` – Zugriff auf Logtabelle.

**Services**

- `AuthService`
  - Session-basiertes Login/Logout mit `mitarbeiter_id` in `$_SESSION`.
  - `loginMitBenutzername($benutzername, $passwort)`:
    - sucht Mitarbeiter per Benutzername,
    - prüft `aktiv`, `ist_login_berechtigt`,
    - prüft Passwort mit `password_verify()` über `passwort_hash`,
    - setzt Session und merkt angemeldeten Mitarbeiter.
  - `loginMitRfid($rfidCode)`:
    - sucht Mitarbeiter per RFID (ohne Passwort),
    - prüft `aktiv`, `ist_login_berechtigt`,
    - setzt Session.
  - `logout()`:
    - entfernt `mitarbeiter_id` aus Session,
    - regeneriert Session-ID,
    - loggt Aktion.
  - `istAngemeldet()`, `holeAngemeldetenMitarbeiter()`.
  - Rollenprüfung `hatRolle()` ist aktuell noch Platzhalter.
- `ZeitService`
  - Skelett, stellt aktuell nur `holeTagesdaten()` bereit (Platzhalter).
- `UrlaubService`
  - Lädt Urlaubsanträge eines Mitarbeiters aus `urlaubsantrag` (einfache Liste).
- `ReportService`
  - Skelett für Monatsberichte (Monats- + Tageswerte).
- `PDFService`
  - Skelett für spätere PDF-Erstellung (aktuell Rückgabe: leerer String).
- `RundungsService`
  - Skelett für Zeitrundung auf Basis `zeit_rundungsregel`.
- `ConfigService`
  - Key-Value-Zugriff auf `config` mit einfachem Cache.
- `FeiertagService`
  - Prüft, ob Datum (optional mit Bundesland) Feiertag ist.
  - Erzeugung von Feiertagen pro Jahr ist noch TODO.
- `OfflineQueueService`
  - Liest offene Einträge aus `db_injektionsqueue`.
- `AuftragszeitService`
  - Skelett: Starten von Aufträgen (aktuell TODO).

**Controller**

- `LoginController`
  - `index()` – derzeit nur Platzhalter; später Anzeige/Verarbeitung Loginformular.
  - `logout()` – nutzt `AuthService::logout()`.
- `DashboardController`
  - `index()` – zeigt einfache Dashboard-Ausgabe (nur Text), prüft `istAngemeldet()` und zeigt Namen aus `AuthService`.
- `ZeitController`
  - `tagesansicht($datum)` – Platzhalter: zeigt Datum + Mitarbeiter-ID, wenn angemeldet.
- `UrlaubController`
  - `meineAntraege()` – lädt Anträge via `UrlaubService` und gibt einfache Liste aus (aktuell noch ohne View-Kopplung).
- `ReportController`
  - `monatsuebersicht($jahr, $monat)` – holt Monatsdaten via `ReportService` und gibt sie rudimentär aus.
  - `monatsPdf($jahr, $monat)` – würde ein PDF ausgeben, sobald `PDFService` implementiert ist.

**Views & Layout**

- `views/layout/header.php` / `views/layout/footer.php`
  - Einfaches HTML-Gerüst, bindet `public/css/app.css` ein.
- `views/login/form.php`
  - Einfaches Loginformular (Benutzername/Passwort).
- `views/dashboard/index.php`
  - Begrüßungsseite mit einfachem Menü.
- `views/zeit/tagesansicht.php`
  - Tabelle zur Anzeige von Buchungen eines Tages.
- `views/zeit/monatskalender.php`
  - Platzhalter für Monatskalender.
- `views/urlaub/meine_antraege.php`
  - Listet Urlaubsanträge des angemeldeten Mitarbeiters.
- `views/urlaub/genehmigung_liste.php`
  - Listet Anträge zur Genehmigung (Input via Controller/Service).
- `views/report/monatsuebersicht.php`
  - Anzeige der Monatswerte (Soll/Ist/Differenz) und Tageswerte je Datum.
- `views/terminal/index.php`
  - Platzhalter-View für Terminaloberfläche.

**Public & Assets**

- `public/index.php`
  - Front-Controller Backend:
    - lädt Autoloader,
    - lädt `config.php`,
    - setzt Zeitzone,
    - startet Session,
    - gibt derzeit eine statische Startseite („Zeiterfassung – Startseite“) aus.
  - Routing-Logik ist noch nicht wirklich implementiert (nur `$seite = $_GET['seite'] ?? 'start';` ohne Verzweigung).
- `public/terminal.php`
  - Einstiegspunkt für Terminal-Frontend, aktuell nur statische Platzhalterseite.
- `public/css/app.css`
  - Basis-CSS für Layout, Tabellen und Sections.

---

## 3. Wichtige Design-Entscheidungen

- **Plain-PHP, kein Framework**  
  Alles basiert auf selbst geschriebenem Core (Datenbank, Logger, Autoloader).

- **Namenskonvention und Ordnerstruktur**
  - Klassenendungen bestimmen Ordner (Model/Service/Controller).
  - Views leben in `views/<bereich>/` und werden direkt mit `require` eingebunden.

- **Logging über Datenbank**
  - Fehler und wichtige Aktionen werden primär in `system_log` protokolliert.
  - Fallback zum PHP-Error-Log, wenn DB nicht erreichbar ist.

- **Sessionbasierte Authentifizierung**
  - Aktuell eine einfache Mitarbeiter-Session (`mitarbeiter_id`).
  - Rollenmodell (Rollen + Genehmiger) ist auf DB-Seite vorbereitet, aber noch nicht in `AuthService` eingebunden.

- **Offline-Design**
  - Optional zweite DB-Verbindung für Offline-Terminals (`offline_db` in `config.php`).
  - Tabelle `db_injektionsqueue` + `OfflineQueueService` für gepufferte SQL-Befehle.
  - Detail-Logik zur Abarbeitung und Fehlerbehandlung folgt später.

---

## 4. Offene Punkte / Nächste sinnvolle Schritte

Kurzfristige nächste Schritte (Backend):

1. **Routing in `public/index.php`** implementieren:
   - `?seite=login` → `LoginController::index()`,
   - `?seite=logout` → `LoginController::logout()`,
   - `?seite=dashboard` → `DashboardController::index()`,
   - `?seite=zeit_heute` → `ZeitController::tagesansicht(heute)`,
   - `?seite=urlaub_meine` → `UrlaubController::meineAntraege()`,
   - `?seite=report_monat` → `ReportController::monatsuebersicht(jahr,monat)` (vorerst fixe Beispielwerte oder GET-Parameter).

2. **Login-Flow verdrahten**:
   - `LoginController::index()` so umbauen, dass:
     - bei `GET` das View `views/login/form.php` gerendert wird,
     - bei `POST` `AuthService::loginMitBenutzername()` aufgerufen wird,
     - bei Erfolg ein Redirect zum Dashboard erfolgt,
     - bei Fehlern eine Meldung im Formular angezeigt wird.

3. **Dashboard-View an Controller anbinden**:
   - `DashboardController::index()` soll `views/dashboard/index.php` laden
     und den angezeigten Namen aus `AuthService` an das View übergeben.

4. **Basis-Rollenprüfungen vorbereiten** (später):
   - `AuthService::hatRolle()` mit `MitarbeiterHatRolleModel` verdrahten.

Terminal & Offline-Teil werden in späteren, eigenen Schritten behandelt, wenn das Backend stabil steht.

---

## 5. Aktueller Arbeits-Prompt für ChatGPT (Stand 2025-11-29)

Diesen Block 1:1 in einen neuen Chat mit ChatGPT einfügen, um an genau diesem Projektstand weiterzuarbeiten:

> Du bekommst ein bestehendes PHP-Projekt für eine Zeiterfassung/Verwaltung mit folgender Struktur:  
> - core/: Database, Logger, Autoloader  
> - modelle/: diverse DB-Modelle (Mitarbeiter, Zeitbuchung, Urlaubsantrag, Rollen, Rundungsregeln, Offline-Queue usw.)  
> - services/: AuthService (Session-Login mit Passwort-Hash + RFID), UrlaubService, ReportService, ZeitService (Skelett), RundungsService, ConfigService, FeiertagService, OfflineQueueService, AuftragszeitService (Skelett), PDFService (Skelett)  
> - controller/: LoginController, DashboardController, ZeitController, UrlaubController, ReportController (jeweils einfache Platzhalter-Logik)  
> - views/: Layout (header/footer), Login-Formular, Dashboard, einfache Zeit-/Urlaub-/Report-Views, Terminal-Platzhalter  
> - public/: index.php (Backend-Front-Controller – aktuell nur statische Startseite), terminal.php (Terminal-Platzhalter), css/app.css  
> - sql/: 01_initial_schema.sql mit der Datenbankstruktur  
> - docs/: master_prompt_zeiterfassung_v2.md, README_zeiterfassung.md, DEV_PROMPT_HISTORY.md  
>  
> WICHTIG:  
> - Halte Dich an die Regeln aus `master_prompt_zeiterfassung_v2.md`.  
> - Gib mir **immer nur eine ZIP-Datei** mit den **geänderten/neu erstellten Dateien** (Relative Pfade ab Projekt-Root), keine langen Erklärungen im Chat.  
> - Arbeite in kleinen, lauffähigen Schritten.  
>  
> Aktueller Stand (Kurzfassung):  
> - AuthService ist bereits sessionbasiert implementiert (Login per Benutzername+Passwort oder RFID), aber LoginController, DashboardController und public/index.php sind noch nicht sauber über Routing und Views verdrahtet.  
>  
> Als Nächstes sollst Du:  
> 1. In `public/index.php` ein simples Routing implementieren (`?seite=...`) und die bestehenden Controller/Views daran anbinden.  
> 2. Den Login-Flow so umbauen, dass das Formular `views/login/form.php` verwendet wird, Fehlermeldungen anzeigen kann und bei erfolgreichem Login zum Dashboard weiterleitet.  
> 3. Das Dashboard so anbinden, dass der angemeldete Mitarbeitername im View `views/dashboard/index.php` angezeigt wird.  
>  
> Bitte liefere mir nur eine ZIP-Datei mit den geänderten Dateien (z. B. `public/index.php`, `controller/LoginController.php`, `controller/DashboardController.php`, evtl. angepasste Views), ohne weitere Erklärtexte.

---

## 6. Verlauf der bisherigen Schritte

- **2025-11-27 bis 2025-11-28**  
  - Grundgerüst (core, config, public/index.php, Autoloader) angelegt.  
  - Erste Modelle und Services (Skelette) erstellt.  
  - Basis-Views und CSS angelegt (Layout, Login, Dashboard, Zeit/Urlaub/Report).  
  - SQL-Schema und Master-Prompt als Dateien im Projekt hinterlegt.

- **2025-11-29**  
  - AuthService von Dummy auf echte Session-Logik mit Passwort-Hash + RFID-Login erweitert.  
  - Diese Verlauf-/Prompt-Datei `docs/DEV_PROMPT_HISTORY.md` eingeführt, um den Projektzustand für zukünftige Arbeiten und Übergaben festzuhalten.

- **2025-11-29 (späterer Schritt)**  
  - `public/index.php` um einfache Routing-Logik erweitert (`?seite=login`, `?seite=logout`, `?seite=dashboard`, `?seite=zeit_heute`, `?seite=urlaub_meine`, `?seite=report_monat`).  
  - `LoginController::index()` so angepasst, dass er das Formular `views/login/form.php` nutzt, POST-Logins verarbeitet, bei Erfolg zum Dashboard weiterleitet und bei Fehlern eine Meldung an das View übergibt.  
  - `DashboardController::index()` an das View `views/dashboard/index.php` angebunden, so dass der angemeldete Mitarbeitername angezeigt wird.  
  - `views/login/form.php` erweitert, um optionale Fehlermeldungen anzuzeigen.

- **2025-11-29 (weiterer Schritt)**  
  - `ZeitController::tagesansicht()` an `ZeitService::holeTagesdaten()` und das View `views/zeit/tagesansicht.php` angebunden.  
  - `UrlaubController::meineAntraege()` so angepasst, dass es das View `views/urlaub/meine_antraege.php` verwendet.  
  - `ReportController::monatsuebersicht()` umgebaut, um Daten über `ReportService` zu holen und im View `views/report/monatsuebersicht.php` darzustellen.  
  - Views für Tagesansicht, Urlaubsanträge und Monatsübersicht erstellt/aktualisiert.

- **2025-11-29 (weiterer sinnvoller Schritt)**  
  - `ZeitService::holeTagesdaten()` implementiert: lädt rohe Zeitbuchungen (`zeitbuchung`) und – falls vorhanden – den aggregierten Tagesdatensatz (`tageswerte_mitarbeiter`) für den jeweiligen Tag.  
  - `ZeitbuchungModel::holeFuerMitarbeiterUndZeitraum()` hinzugefügt, inkl. optionaler Terminalbezeichnung über LEFT JOIN auf `terminal`.  
  - `TageswerteMitarbeiterModel::holeNachMitarbeiterUndDatum()` hinzugefügt, um einen Tageswert-Datensatz pro Mitarbeiter/Datum zu laden.

- **2025-11-29 (weiterer sinnvoller Schritt – Monatsberichte)**  
  - `ReportService::holeMonatsdatenFuerMitarbeiter()` implementiert: lädt Monatswerte (`monatswerte_mitarbeiter`) und alle Tageswerte (`tageswerte_mitarbeiter`) für den gewünschten Monat und bereitet sie für das View `views/report/monatsuebersicht.php` auf.  
  - `MonatswerteMitarbeiterModel::holeNachMitarbeiterUndMonat()` hinzugefügt.  
  - `TageswerteMitarbeiterModel` um `holeAlleFuerMitarbeiterUndMonat()` erweitert, um alle Tage eines Monats in einem Rutsch laden zu können.

- **2025-11-29 (Urlaubslogik Basis)**  
  - `UrlaubService` eingeführt und an `UrlaubController` angebunden; liefert Urlaubsanträge eines Mitarbeiters (optional nach Status gefiltert).  
  - `UrlaubsantragModel::holeFuerMitarbeiter()` implementiert, inkl. Sortierung nach Zeitraum/Antragsdatum.  
  - View `views/urlaub/meine_antraege.php` bereinigt und an die echten Feldnamen aus `urlaubsantrag` angepasst.

- **2025-11-29 (Feiertage – automatische Berechnung)**  
  - `FeiertagModel` implementiert (Lesen und Schreiben der Tabelle `feiertag`).  
  - `FeiertagService` hinzugefügt: berechnet bundesweit gültige Feiertage (inkl. beweglicher Feiertage über Ostersonntag) für ein Jahr und legt sie in der Datenbank ab; stellt außerdem `istFeiertag()` bereit, das bei Bedarf automatisch die Feiertage eines Jahres seeden kann.

- **2025-11-29 (Rundungsregeln – Basis)**  
  - `ZeitRundungsregelModel` implementiert, das alle aktiven Rundungsregeln aus `zeit_rundungsregel` in sortierter Reihenfolge liefert.  
  - `RundungsService::rundeZeitstempel()` eingeführt: ermittelt anhand der DB-Regeln (Zeitfenster, Kommen/Gehen, Richtung `auf`/`ab`/`naechstgelegen`, Schrittweite in Minuten) einen gerundeten Zeitstempel, ohne feste Logik im Code zu verankern.

- **2025-11-29 (Offline-Queue – Basisimplementierung)**  
  - `DbInjektionsqueueModel` implementiert: Einfügen neuer Einträge, Laden offener Einträge, Statuswechsel auf `verarbeitet` bzw. `fehler` und Zurücksetzen auf `offen`.  
  - `OfflineQueueService` hinzugefügt: Stellt `enqueueSql()` zum Hinzufügen von Offline-SQL-Befehlen bereit und `verarbeiteOffeneMitExecutor()`, das offene Einträge in Reihenfolge über einen Callback gegen die Hauptdatenbank ausführt und beim ersten Fehler stoppt.

- **2025-11-29 (Stammdaten – Mitarbeiter & Rollen)**  
  - `MitarbeiterModel` implementiert: Basiszugriffe auf Tabelle `mitarbeiter` (inkl. Login- und RFID-Suche).  
  - `RolleModel` hinzugefügt, inkl. Laden aller Rollen eines Mitarbeiters über `mitarbeiter_hat_rolle`.  
  - `MitarbeiterGenehmigerModel` implementiert für das personenzentrierte Genehmiger-Konzept.  
  - `TerminalModel` erstellt, um Terminal-Stammdaten (inkl. Modus/Offline-Flags) per ID/Name laden zu können.  
  - `MitarbeiterService` eingeführt, der Mitarbeiter, Rollen und Genehmiger zusammenfasst und Hilfsfunktionen für Login und RFID-Login bereitstellt.

- **2025-11-29 (Auth & Konfiguration & PDF-Skelett)**  
  - `AuthService` implementiert: Session-basiertes Login/Logout, Login per Benutzername/E-Mail + Passwort sowie Login per RFID über den `MitarbeiterService`; Rollenprüfung `hatRolle()` vorerst als einfacher Platzhalter (nur "angemeldet ja/nein").  
  - `ConfigService` hinzugefügt, der Werte aus der Tabelle `config` mit einfachem Typing (string/int/float/bool/json) lädt und cached.  
  - `PDFService` als Skelett-Service erstellt; `erzeugeMonatsPdfFuerMitarbeiter()` existiert, liefert aktuell aber bewusst nur einen leeren String zurück.

- **2025-11-29 (Core-Infrastruktur – Autoloader, DB, Logger, Konfiguration)**  
  - `core/Autoloader.php` implementiert: einfacher SPL-Autoloader für `core/`, `modelle/`, `services/` und `controller/`.  
  - `core/Database.php` erstellt: Singleton-PDO-Wrapper mit `fetchEine()`, `fetchAlle()`, `ausfuehren()` und `letzteInsertId()`, der seine Einstellungen aus `config/config.php` liest.  
  - `core/Logger.php` hinzugefügt: schreibt Logs bevorzugt in die Tabelle `system_log` und fällt bei DB-Problemen auf `error_log()` zurück; Convenience-Methoden `debug()`, `info()`, `warn()`, `error()`.  
  - `config/config.php` als zentrale Konfigurationsdatei angelegt (DB-Zugang + Zeitzone, mit Kommentaren zum Anpassen).

- **2025-11-29 (Basis-Layout & Dashboard)**  
  - Gemeinsames Layout mit `views/layout/header.php` und `views/layout/footer.php` erstellt (Header inkl. Benutzeranzeige, Navigation, simples CSS).  
  - `views/dashboard/index.php` umgesetzt, sodass nach dem Login ein funktionsfähiges Dashboard mit Links zu Tageszeiten, Urlaub und Monatsbericht angezeigt wird.

- **2025-11-29 (Core – DB/Logger/Autoloader konkretisiert)**  
  - `core/Database.php` jetzt mit vollständiger PDO-Initialisierung auf Basis von `config/config.php` und Hilfsmethoden `fetchEine()`, `fetchAlle()`, `ausfuehren()`, `letzteInsertId()`.  
  - `core/Logger.php` überarbeitet: schreibt Logs in `system_log` (mit JSON-kodierten Zusatzdaten) und fällt bei Fehlern sauber auf `error_log()` zurück.  
  - `core/Autoloader.php` finalisiert: SPL-Autoloader, der Klassen aus `core/`, `modelle/`, `services/` und `controller/` lädt.

- **2025-11-29 (Front-Controller & Login-Flow)**  
  - `public/index.php` von Platzhalter auf vollständigen Front-Controller umgestellt (Routing nach `seite`, Auth-Check für geschützte Seiten, Fallback auf Login/Dashboard).  
  - `LoginController` fertig implementiert: verarbeitet GET/POST für `?seite=login`, nutzt `AuthService` und leitet nach erfolgreichem Login aufs Dashboard um.  
  - Neues View `views/login/form.php` erstellt (einfache, eigenständige Login-Seite ohne Backend-Navigation).

- **2025-11-29 (Genehmiger-Model – Speichern der Zuordnung)**  
  - `MitarbeiterGenehmigerModel` um Methode `speichereGenehmigerFuerMitarbeiter()` erweitert, die für einen Mitarbeiter alle vorhandenen Einträge in `mitarbeiter_genehmiger` löscht und die übergebenen Genehmiger (Mitarbeiter-ID, Priorität, optionale Beschreibung) bereinigt neu einträgt.

- **2025-11-29 (Mitarbeiter-Admin – Liste & Navigation)**  
  - Front-Controller (`public/index.php`) um die Seite `mitarbeiter_admin` erweitert und als geschützte Backend-Seite markiert.  
  - Navigation im Basis-Layout (`views/layout/header.php`) um einen Menüpunkt „Mitarbeiter“ ergänzt.  
  - `MitarbeiterAdminController::index()` so umgebaut, dass die Mitarbeiterliste per Model geladen und an eine eigene View übergeben wird.  
  - Neue Backend-View `views/mitarbeiter/liste.php` erstellt, die alle aktiven Mitarbeiter tabellarisch mit ID, Name, Benutzername, E-Mail, RFID und Login-/Aktiv-Status darstellt.  
- **2025-11-29 (Mitarbeiter-Admin – Bearbeiten & Speichern von Stammdaten)**  
  - Front-Controller (`public/index.php`) um die Routen `mitarbeiter_admin_bearbeiten` und `mitarbeiter_admin_speichern` erweitert und beide als geschützte Backend-Seiten markiert.  
  - `MitarbeiterAdminController` um die Methoden `bearbeiten()` und `speichern()` ergänzt: Stammdaten-Formular lädt vorhandene Mitarbeiterdaten bzw. bereitet einen neuen Datensatz vor und speichert Änderungen über das `MitarbeiterModel`.  
  - Neue View `views/mitarbeiter/formular.php` implementiert (Stammdaten, Login-/Zugangs-Felder, Passwort-Änderung) sowie „Neuen Mitarbeiter anlegen“-Link und „Bearbeiten“-Aktion in `views/mitarbeiter/liste.php` ergänzt.  

- **2025-11-29 (Mitarbeiter-Admin – Genehmiger-Verwaltung im Formular)**  
  - `MitarbeiterAdminController::bearbeiten()` lädt für bestehende Mitarbeiter jetzt die Genehmiger über das `MitarbeiterGenehmigerModel` und übergibt sie an die Formular-View.  
  - `MitarbeiterAdminController::speichern()` wertet die Felder `genehmiger_id[]`, `genehmiger_prio[]` und `genehmiger_beschreibung[]` aus und speichert die Zuordnung mit `MitarbeiterGenehmigerModel::speichereGenehmigerFuerMitarbeiter()`.  
  - `views/mitarbeiter/formular.php` um einen neuen Abschnitt „Genehmiger“ erweitert: mehrere Zeilen mit Mitarbeiter-ID, Priorität und Beschreibung; leere Zeilen werden ignoriert, vorhandene Genehmiger werden mit Namen angezeigt.  

- **2025-11-29 (Mitarbeiter-Admin – Rollen-Auswahl im Formular)**  
  - `MitarbeiterHatRolleModel` um die Methode `speichereRollenFuerMitarbeiter()` erweitert (löscht alte Zuordnungen und legt neue an).  
  - `MitarbeiterAdminController::bearbeiten()` lädt nun alle aktiven Rollen (`RolleModel::holeAlleAktiven()`) und die bereits zugeordneten Rollen-IDs (`MitarbeiterHatRolleModel::holeRollenIdsFuerMitarbeiter()`), die an das Formular übergeben werden.  
  - `MitarbeiterAdminController::speichern()` liest die Checkbox-Auswahl `rollen_ids[]` aus, übergibt sie an `speichereRollenFuerMitarbeiter()` und sorgt in allen Fehlerfällen dafür, dass die Rollenliste und Auswahl im Formular erhalten bleibt.  
  - `views/mitarbeiter/formular.php` um ein neues Feldset „Rollen (Berechtigungen)“ ergänzt: alle aktiven Rollen werden als Checkboxen angezeigt; aktive Rollen eines Mitarbeiters sind vorausgewählt.  

- **2025-11-29 (Auth / Rollen – Anzeige & Prüfung)**  
  - `AuthService::hatRolle()` prüft jetzt anhand der in `rolle` hinterlegten Namen, ob der angemeldete Benutzer eine bestimmte Rolle besitzt, und `holeAngemeldeteRollenNamen()` liefert die Rollen-Namen des aktuellen Benutzers.  
  - Im Header wird neben dem Namen des angemeldeten Benutzers eine Liste seiner Rollen angezeigt (falls vorhanden).  

- **2025-11-29 (Abteilungs-Admin – erste Backend-Ansicht)**  
  - `AbteilungAdminController::index()` nutzt jetzt eine eigene View und das Standard-Layout, um alle aktiven Abteilungen tabellarisch anzuzeigen.  
  - Neue View `views/abteilung/liste.php` erstellt und im Front-Controller (`?seite=abteilung_admin`) sowie in der Hauptnavigation verlinkt.  

- **2025-11-29 (Abteilungs-Admin – Bearbeiten/Anlegen)**  
  - `AbteilungAdminController` um die Methoden `bearbeiten()` und `speichern()` erweitert; damit können Abteilungen im Backend neu angelegt und bestehende Einträge bearbeitet werden.  
  - `views/abteilung/liste.php` um einen Link „Neue Abteilung anlegen“ sowie eine „Bearbeiten“-Aktion je Zeile ergänzt.  
  - `public/index.php` um die geschützten Routen `abteilung_admin_bearbeiten` und `abteilung_admin_speichern` erweitert, sodass diese nur für angemeldete Benutzer verfügbar sind.  

- **2025-11-30 (Erstinstallation & Initial-Admin)**  
  - `LoginController` erkennt nun, ob noch kein login-berechtigter Mitarbeiter existiert, und zeigt in diesem Fall ein Erstinstallations-Formular zum Anlegen des ersten Admin-Benutzers (inkl. automatischer Erstellung der Rollen „Chef“ und „Personalbüro“ und deren Zuordnung).  
  - `MitarbeiterModel` wurde um `existiertLoginberechtigterMitarbeiter()` ergänzt; neue View `views/login/initial_admin.php` sorgt für einen einfachen Setup-Flow ohne manuelle SQL-INSERTs.  

- **2025-11-30 (Login-Fix & erster Live-Login)**  
  - `MitarbeiterModel::ladeLoginfaehigenMitarbeiter()` wurde korrigiert (zwei unterschiedliche Platzhalter statt doppeltem `:kennung`), damit der Login ohne `SQLSTATE[HY093] Invalid parameter number` läuft.  
  - Auf der produktiven Instanz `project-ronin.de/zeiterfassung` konnte der erste Admin-Benutzer erfolgreich angelegt und angemeldet werden; Dashboard, Menü (inkl. Feiertage und Offline-Queue) sind damit im Live-System erreichbar.  

### Aktueller Status (2025-11-30)

- **Zuletzt erledigt:** Erstinstallation (Initial-Admin) und Login funktionieren nun durchgehend bis ins Dashboard; das System läuft bereits auf einer produktiven Instanz zu Testzwecken.  
- **Nächster geplanter Schritt:** Installations-Assistent für Datenbank-Setup (config.php aus Template erzeugen, Schema automatisch einspielen) und erste Aufräumrunde bei Fehlerseiten (403/404 als eigene Views).
### 2025-11-30 – Bugfix: Mitarbeiter-Formular überschreibt Datensatz mit eingeloggtem Benutzer

- Problem: Beim Aufruf der Mitarbeiter-Bearbeitungsmaske wurden in den Stammdaten immer die Daten des aktuell eingeloggten Mitarbeiters angezeigt, unabhängig davon, welcher Mitarbeiter in der Liste angeklickt wurde. Dadurch konnte ein Admin versehentlich die eigenen Daten überschreiben, wenn er eigentlich einen anderen Mitarbeiter bearbeiten wollte.
- Ursache: Im global eingebundenen Header (`views/layout/header.php`) wurde die Variable `$mitarbeiter` für den angemeldeten Benutzer verwendet. Diese Variable hat in Views wie `views/mitarbeiter/formular.php` den vom Controller übergebenen Mitarbeiter-Datensatz überschrieben.
- Fix: Im Header wurde die Variable für den angemeldeten Benutzer in `$angemeldeterMitarbeiter` umbenannt und alle Zugriffe entsprechend angepasst. Damit bleibt `$mitarbeiter` in den Views für den jeweils vom Controller übergebenen Datensatz reserviert und das Bearbeiten anderer Mitarbeiter funktioniert korrekt.


### 2025-11-30 – Genehmiger-Auswahl: Dropdown in Mitarbeiter-Formular (Teil 1)

- Kontext: Im Mitarbeiter-Formular wurden Genehmiger bisher über drei numerische Felder `Genehmiger-Mitarbeiter-ID` gepflegt. Die Eingabe über reine IDs ist unpraktisch und fehleranfällig.
- Änderung (Teil 1): Die Genehmiger-Sektion im Formular wurde so vorbereitet, dass statt freier ID-Eingabe eine Auswahl der Mitarbeiter über Dropdowns möglich ist (Anzeige nach Name, intern weiterhin Speicherung der Mitarbeiter-ID).
- Technik: Der Mitarbeiter-Controller stellt der View eine sortierte Liste aller aktiven Mitarbeiter zur Verfügung, so dass die Dropdowns die vorhandenen Genehmiger vorausgewählt anzeigen können.
- Geplanter nächster Schritt (Teil 2): Aufräumen und Vereinheitlichen der Speicherroutine für Genehmiger, zusätzliche Validierungen (z. B. Mitarbeiter kann sich nicht selbst als Genehmiger wählen) und Vorbereitung für eine eigenständige Genehmiger-Verwaltung im Backend.

### 2025-11-30 – Zeit: einfache Buchungs-API für Kommen/Gehen (Teil 6)

- Kontext: Die Tagesansicht (`ZeitService::holeTagesdaten()`) liest bereits Buchungen aus der Tabelle `zeitbuchung`. Für das Terminal (Kommen/Gehen-Funktionen) wird nun eine einfache Schreib-API benötigt.
- Änderungen:
  - `ZeitbuchungModel`:
    - Neue Methode `erstelleBuchung(...)`:
      - Legt eine neue Zeile in der Tabelle `zeitbuchung` an (mitarbeiter_id, typ, zeitstempel, quelle, kommentar, terminal_id).
      - Validiert `typ` (nur 'kommen'/'gehen') und `quelle` ('terminal', 'web', 'import').
      - Liefert die neue Buchungs-ID oder `null` bei Fehler und loggt Ausnahmen über `Logger`.
  - `ZeitService`:
    - Interne Methode `bucheZeit(...)` kapselt die Erstellung einer Zeitbuchung inklusive Standardwerten (aktueller Zeitpunkt, Standardquelle 'terminal').
    - Öffentliche Methoden:
      - `bucheKommen(...)` → bucht ein „Kommen“.
      - `bucheGehen(...)`  → bucht ein „Gehen“.
- Effekt: Es existiert jetzt eine saubere Service-Schicht zum Anlegen von Zeitbuchungen, die später direkt vom Terminal (RFID-Kommen/Gehen) und ggf. von Web-UIs genutzt werden kann.
- Nächster geplanter Schritt: Anbindung der Terminal-Logik (Kommen/Gehen-Buttons) an `ZeitService::bucheKommen()` / `bucheGehen()` und Erweiterung der Terminal-Ansichten um einfache Status-/Fehlermeldungen.

### 2025-11-30 – Terminal: Kommen/Gehen am Terminal (Teil 7)

- Kontext: Nach Aufbau der Auftragszeit-Logik und der Buchungs-API für Zeitbuchungen (`ZeitService::bucheKommen()/bucheGehen()`) soll das Terminal jetzt auch Kommen/Gehen direkt buchen können.
- Änderungen:
  - `controller/TerminalController.php`:
    - Erweitert um eine Abhängigkeit zu `ZeitService`.
    - Neue Methoden `kommen()` und `gehen()`:
      - prüfen zunächst, ob ein Mitarbeiter am Terminal angemeldet ist,
      - buchen dann über `ZeitService::bucheKommen()` bzw. `bucheGehen()` eine Zeitbuchung mit Quelle `terminal` und aktuellem Zeitstempel,
      - zeigen anschließend wieder das Hauptmenü (`views/terminal/start.php`) mit Erfolgs- oder Fehlermeldung an.
  - `views/terminal/start.php`:
    - Logged-in-Ansicht erweitert:
      - zeigt weiterhin den angemeldeten Mitarbeiter an,
      - bietet jetzt zwei große Buttons „Kommen“ und „Gehen“ sowie darunter „Auftrag starten“ und „Auftrag stoppen“.
    - Nicht angemeldete Benutzer sehen unverändert die Login-Maske (RFID-Code oder Mitarbeiter-ID).
  - `public/terminal.php`:
    - Routing um die Aktionen `kommen` und `gehen` ergänzt und an die neuen Controller-Methoden angebunden.
- Effekt: Ein angemeldeter Mitarbeiter kann über das Terminal nun Kommen/Gehen buchen; die Rohdaten landen über den `ZeitService` in der Tabelle `zeitbuchung` und sind damit für spätere Tages-/Monatsauswertungen verfügbar.
- Nächster geplanter Schritt: Nutzung dieser Kommen/Gehen-Funktionen in einer einfachen Tagesübersicht am Terminal und Vorbereitung von Terminal-Funktionen für Urlaub/Abwesenheiten.

- **2025-11-30 – Bugfix Genehmiger-Zuordnung im Mitarbeiter-Backend**  
  - `controller/MitarbeiterAdminController.php`  
    - Speichern der Genehmiger verwendet jetzt das Feld `kommentar` für die Übergabe an das Modell, passend zur Spalte `kommentar` in der Tabelle `mitarbeiter_genehmiger`.  
  - `modelle/MitarbeiterGenehmigerModel.php`  
    - Spaltenzuordnung beim Speichern der Genehmiger korrigiert (`kommentar` statt `beschreibung`), INSERT-Statement angepasst.  
  - `views/mitarbeiter/formular.php`  
    - Genehmiger-Formular liest jetzt die Spalte `kommentar` und füllt das Kommentar-Feld bei vorhandenen Genehmigern korrekt vor.  

### 2025-12-20 – Terminal: Störungsmodus bei Queue-Fehler (Teil 3)

- Kontext: Laut Master-Prompt muss bei einem Fehler in `db_injektionsqueue` die Abarbeitung sofort stoppen und das Terminal in einen Störungsmodus wechseln, damit keine weiteren Buchungen ausgeführt werden, bevor ein Admin den fehlerhaften Eintrag geprüft/entfernt hat.
- Änderungen:
  - `public/terminal.php`
    - Erkennt Queue-Fehler (`status='fehler'` oder vorhandener letzter Fehler) und biegt jede Aktion auf `aktion=stoerung` um (Blockade von Kommen/Gehen/Auftragsfunktionen).
    - Routing um die neue Aktion `stoerung` ergänzt.
  - `controller/TerminalController.php`
    - Neue Methode `stoerung()` zum Rendern der Störungsseite inkl. Fallback-Laden des letzten Fehler-Eintrags.
  - `views/terminal/stoerung.php` (neu)
    - Eigene Terminal-Seite für den Störungsmodus: Anzeige von Queue-Status, Fehlermeldung und konkretem SQL-Befehl (mit Scroll/Umbruch).
- Effekt: Sobald ein Queue-Fehler existiert, wird das Terminal zuverlässig in den Störungsmodus versetzt und blockiert alle produktiven Terminal-Aktionen, bis ein Admin die Queue bereinigt.
- Nächster geplanter Schritt: Backend-Queue-Admin erweitern, damit ein Admin fehlerhafte Queue-Einträge „Ignorieren/Löschen“ kann (danach läuft die Queue weiter).

### 2025-12-20 – Queue-Admin: Fehler-Einträge ignorieren/löschen (Teil 4)

- Kontext: Wenn ein Eintrag in `db_injektionsqueue` mit Status `fehler` existiert, geht das Terminal in den Störungsmodus. Damit der Betrieb wieder anlaufen kann, muss ein Admin den fehlerhaften Queue-Eintrag nach Prüfung entfernen („Ignorieren/Löschen“).
- Änderungen:
  - `controller/QueueController.php`
    - Umgestellt auf `QueueService`/Offline-Queue-Verbindung (zeigt die Queue aus der Offline-DB, falls vorhanden).
    - POST-Aktion `loeschen` ergänzt: Ein Fehler-Eintrag kann per Button aus der Liste gelöscht werden; anschließend Redirect auf die Listenansicht mit Meldung.
    - Laden der Listen: `offen`/`fehler` über `QueueService`, `verarbeitet` via direkter Abfrage über die Queue-Verbindung.
  - `views/queue/liste.php`
    - Hinweistext angepasst und für Status `fehler` eine Aktionsspalte ergänzt.
    - Pro Fehler-Eintrag Button „Ignorieren/Löschen“ (mit Confirm), plus einfache Erfolgsmeldung.
- Effekt: Ein Admin kann einen fehlerhaften Queue-Eintrag direkt über die Weboberfläche entfernen, wodurch das Terminal nach dem nächsten Request wieder aus dem Störungsmodus herauskommt und die Queue weiterlaufen kann.
- Nächster geplanter Schritt: Genehmiger-Dropdowns im Mitarbeiter-Formular final fixen (Speichern + korrektes Reload beim Bearbeiten), inklusive Validierung (kein Selbst-Genehmiger, Duplikate vermeiden).

### 2025-12-20 – Terminal: Auto-Logout per Config + Startseite zeigt „Übersicht (heute)“ (Teil 5)

- Kontext: Laut Master-Prompt soll das Terminal nach Inaktivität automatisch abmelden (konfigurierbar über `config`). Außerdem soll am Terminal eine schnelle Übersicht verfügbar sein.
- Änderungen:
  - `controller/TerminalController.php`
    - Neue Helper `ladeConfigInt()` + `holeTerminalTimeoutSekunden()`.
    - Erwartete Config-Keys (Sekunden): `terminal_timeout_standard` (Default 60), `terminal_timeout_urlaub` (Default 180).
    - Start/kommen/gehen setzen jetzt `$terminalTimeoutSekunden` und laden zusätzlich laufende Aufträge für die Übersicht.
  - `views/terminal/start.php`
    - Defensives Initialisieren der Variablen (`$heuteDatum`, `$heuteBuchungen`, `$laufendeAuftraege`, `$terminalTimeoutSekunden`).
    - Auto-Logout-JS nutzt nun den vom Controller gelieferten Timeout.
- Effekt: Terminal meldet nach Inaktivität automatisch ab (konfigurierbar) und zeigt am Hauptmenü eine kompakte Übersicht (heutige Buchungen + laufende Aufträge).
- Nächster geplanter Schritt: Terminal-Menü um „Urlaub beantragen“ ergänzen (Form + Insert in `urlaubsantrag`) und Timeout-Kontext „urlaub“ dort anwenden.
### 2025-12-21 – Backend: Betriebsferien-Admin (T-006) – Teil 1

- Kontext: Die Tabelle `betriebsferien` ist im Schema vorhanden, es fehlte jedoch eine Backend-Verwaltung (CRUD) inkl. Navigation/Routing.
- Änderungen:
  - `public/index.php`
    - Routing um `betriebsferien_admin`, `betriebsferien_admin_bearbeiten`, `betriebsferien_admin_speichern` ergänzt (inkl. geschützter Seiten).
  - `views/layout/header.php`
    - Neuer Menüpunkt „Betriebsferien“ für Rollen Chef/Personalbüro.
  - `controller/BetriebsferienAdminController.php` (neu)
    - Liste aller Betriebsferien (inkl. inaktiver) mit Bearbeiten-Link.
    - Formular zum Anlegen/Bearbeiten: Von/Bis-Datum, Abteilung (optional/global), Beschreibung, Aktiv.
    - Speichern per INSERT/UPDATE (Validierung: gültige Datumswerte, Von <= Bis).
- Effekt: Betriebsferien können jetzt im Backend angelegt und gepflegt werden.
- Nächster geplanter Schritt: Betriebsferien in Auswertungen/Sollstunden-Logik berücksichtigen (Tages-/Monatswerte) und danach Konfiguration/Rundungsregeln als Admin-UI.

### 2025-12-21 – Bugfix: Monatsübersicht Betriebsferien-Markierung stabilisiert

- Kontext: In `ReportService` wurde die Betriebsferien-Tage-Map versehentlich nur im Exception-Pfad initialisiert, wodurch bei normalem Lauf eine undefinierte Variable entstehen konnte.
- Änderungen:
  - `services/ReportService.php`
    - `$tageswerteRoh` wird defensiv initialisiert.
    - `$betriebsferienTage` wird nach dem Laden der Tageswerte (unabhängig vom Try/Catch) immer ermittelt.
- Effekt: Die Monatsübersicht kann Betriebsferien-Tage zuverlässig markieren, ohne PHP-Notices/Undefined-Variable.
- Nächster geplanter Schritt: Betriebsferien in der Sollstunden-/Tageswerte-Berechnung berücksichtigen (Sollstunden reduzieren bzw. Tag als Betriebsferien behandeln), danach Admin-UI für `config`/Rundungsregeln.


### 2025-12-21 – Rundungsregeln: Default-Seeding + Tagesende korrekt abgedeckt (T-007) – Teil 3

- Kontext: Es gab noch keine Standard-Rundungsregeln in der DB; außerdem war ein „Tagesende“-Zeitbereich (23:59) mit exklusivem Ende in Minutenauflösung schwierig abzubilden.
- Änderungen:
  - `services/RundungsService.php`
    - Legt beim Laden der Regeln automatisch **Default-Rundungsregeln** an, **wenn** die Tabelle `zeit_rundungsregel` leer ist (idempotent).
      - 00:00–07:00 → 30 Minuten, nächstgelegen, gilt für beide
      - 07:00–23:59 → 15 Minuten, nächstgelegen, gilt für beide (DB-Wert `bis_uhrzeit = 23:59:59`)
    - `zeitstringZuMinuten()` berücksichtigt jetzt Sekunden: Zeiten wie `23:59:59` werden für die Bereichsprüfung als **nächste Minute** interpretiert, damit der letzte Tag-Minutenbereich korrekt enthalten ist.
  - `controller/ZeitRundungsregelAdminController.php`
    - Initialisiert im Admin-Listing den `RundungsService`, damit das Default-Seeding auch dann greift, wenn ein Admin zuerst die Rundungsregeln aufruft.

- Effekt: Nach dem ersten Aufruf (Terminal/Report/Admin) sind Standard-Rundungsregeln vorhanden und decken den gesamten Tag ab, ohne dass im Formular ungültige Uhrzeiten wie 24:00 eingetragen werden müssen.
- Nächster geplanter Schritt: Allgemeines Seeding/„Ensure Defaults“ für weitere Pflicht-Configs (z.B. `terminal_timeout_standard`, `terminal_timeout_urlaub`) und ggf. eine kleine Admin-Config-Übersicht.

### 2025-12-21 – Ensure Defaults: Config-Seeding für Terminal-Timeouts

- Kontext: Die Tabelle `config` hat (im aktuellen SQL) keine Seed-Daten. Das Terminal erwartet aber konfigurierbare Timeout-Keys (`terminal_timeout_standard`, `terminal_timeout_urlaub`). Fehlende Werte sollen automatisch und sicher angelegt werden.
- Änderungen:
  - `core/DefaultsSeeder.php` (neu)
    - Defensiver, idempotenter Seeder:
      - Prüft, ob die Haupt-DB erreichbar ist.
      - Prüft, ob die Tabelle `config` existiert.
      - Legt fehlende Default-Keys per `INSERT IGNORE` an (überschreibt keine bestehenden Werte).
      - Loggt nur bei Erfolg/Fehler über `Logger`.
  - `public/index.php`
    - Ruft `DefaultsSeeder::ensureDefaults()` früh im Request auf (mit Try/Catch), damit Backend/Reports/Logins automatisch die Defaults sicherstellen.
  - `public/terminal.php`
    - Ruft `DefaultsSeeder::ensureDefaults()` ebenfalls früh auf (mit Try/Catch), damit Terminal-Requests die Defaults auch ohne Backend-Besuch sicherstellen.

- Effekt: Wenn `config` leer ist oder die Timeout-Keys fehlen, werden sie automatisch angelegt (ohne bestehende Werte zu verändern). Terminal kann dadurch die Timeouts künftig direkt aus der DB beziehen.
- Nächster geplanter Schritt: Admin-UI „Konfiguration“ (Liste + Bearbeiten) für `config` (Key/Typ/Beschreibung/Wert), damit Werte bequem gepflegt werden können.

### 2025-12-21 – Admin-UI: Konfiguration (config-Tabelle) – Teil 1

- Kontext: Defaults werden über `DefaultsSeeder` automatisch angelegt, aber es fehlte eine Backend-Oberfläche, um Config-Werte bequem zu sehen und zu ändern.
- Änderungen:
  - `public/index.php`
    - Neue geschützte Seiten + Routing:
      - `konfiguration_admin` (Liste)
      - `konfiguration_admin_bearbeiten` (Formular / Speichern per POST)
  - `views/layout/header.php`
    - Neuer Menüpunkt „Konfiguration“ (Rollen Chef/Personalbüro).
  - `controller/KonfigurationController.php` (ersetzt Platzhalter)
    - Liste aller Einträge aus Tabelle `config` inkl. kurzer Vorschau von Wert/Beschreibung.
    - Formular für Neu/Bearbeiten; Speichern via `KonfigurationService::set()` (idempotent).
    - Validierung: Schlüssel Pflicht, max. 190 Zeichen, keine Leerzeichen.

- Effekt: Admin kann Config-Keys/Werte/Typ/Beschreibung direkt im Backend verwalten.
- Nächster geplanter Schritt: Weitere Defaults systematisch seeden/prüfen (z. B. zentrale System-Configs) und optional eine „Reset to Default“-Funktion (später).
## P-2025-12-21-08
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `770aada54768797dc488d54f810f95e6c9f7be537aceb0c05a22e4030fdb8050`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v2.md` = `48cf65e3f9bdbff647e3dd209943330c9059728293d06b44b6bac9efce35bbb3`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `da14a19e985a016748bc38eb55b11c5c5069b3f4233c115ad3f3b89f7ef39fad`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: T-007 war als „Rundung nur in Reports/PDF“ offen; Web-Monatsreport hat bisher Rohzeiten/roh-basierte Berechnung genutzt → **nicht DONE**, Implementierung notwendig.
- **DATEIEN (max. 3):**
  - `services/ReportService.php`
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport berechnet Arbeitszeit jetzt aus **korrigierten** Kommen/Gehen-Zeiten gemäß `zeit_rundungsregel` (Rohzeit bleibt gespeichert/unverändert).
  - Monatsübersicht zeigt standardmäßig korrigierte Zeiten; wenn abweichend wird die Rohzeit darunter als „roh:“ angezeigt.
- **NEXT:**
  - PDFService implementieren (echte PDF-Ausgabe) und dabei dieselbe Rundungslogik zentral verwenden.

## P-2025-12-21-09
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `770aada54768797dc488d54f810f95e6c9f7be537aceb0c05a22e4030fdb8050`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `48cf65e3f9bdbff647e3dd209943330c9059728293d06b44b6bac9efce35bbb3`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `da14a19e985a016748bc38eb55b11c5c5069b3f4233c115ad3f3b89f7ef39fad`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
- **DATEIEN (max. 3 + DEV):**
  - `services/PDFService.php`
  - `public/index.php`
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **PDFService (V2)** erzeugt jetzt ein echtes **Minimal-PDF** (ohne externe Libraries) aus den Monatsdaten.
    - Rohzeiten bleiben gespeichert/unverändert.
    - Korrigierte Zeiten und Istzeit werden wie im Web-Report nur abgeleitet und im PDF angezeigt.
  - Routing ergänzt: neue Seite `?seite=report_monat_pdf&jahr=YYYY&monat=MM`.
  - Monatsübersicht hat einen Link „PDF anzeigen“ (öffnet in neuem Tab).
- **NEXT:**
  - PDF optisch verbessern (mehrspaltig/sauberere Tabelle, ggf. automatische Seitenumbrüche, optional Firmenkopf).
  - Danach: Terminal „Urlaub beantragen“ Flow (T-008) umsetzen.

## P-2025-12-21-10
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `770aada54768797dc488d54f810f95e6c9f7be537aceb0c05a22e4030fdb8050`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `48cf65e3f9bdbff647e3dd209943330c9059728293d06b44b6bac9efce35bbb3`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a6495a4bd2dc7bd0a2cc8f7783d0550350388d5ab209b8e71aabf116a90b36c2`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Zusatz: angewendete Patch-ZIPs: `P-2025-12-21-08_report-rundung-web.zip` = `902080333f80ad085ee9814c3659a202c6ad23596674126d17d87a8db099ee5b`, `P-2025-12-21-09_pdf-report.zip` = `4469533989af62a706eee3e4e84b5b0b59cb8d8b5c8b03c708062af753b255ad`
- **DUPLICATE-CHECK:**
  - LOG/SNAPSHOT geprüft: Terminal hatte bislang keine Aktion/UX für Urlaub (nur Kommen/Gehen + Auftrag Start/Stop). → **nicht DONE**, Implementierung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `controller/TerminalController.php`
  - `public/terminal.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal unterstützt jetzt `?aktion=urlaub_beantragen` (GET/POST).
    - Validierung: Datum von/bis (bis >= von), Kommentar optional.
    - Speicherung in `urlaubsantrag` mit `status='offen'` und `tage_gesamt` (inkl. Wochenenden, da rein kalendarisch).
  - Master-Prompt erfüllt: Urlaub am Terminal ist **nur erlaubt**, wenn die **Hauptdatenbank erreichbar** ist (Offline-Modus blockiert).
  - Terminal-UI: Button „Urlaub beantragen“ im Hauptmenü (bei Offline/DB down als disabled dargestellt).
  - Flash-Meldungen: Nach erfolgreichem Speichern Redirect zurück auf Start mit Info-Text.
- **NEXT:**
  - **T-011:** PDF optisch verbessern (Tabellenlayout/Seitenumbrüche, optional Firmenkopf).


## P-2025-12-23-01
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (aus Patch-ZIP): `docs/DEV_PROMPT_HISTORY.md` = `19736ca64a6e6931f3370147ee56ecfce0f146194f9ab512b5e15c28ea473700`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Patch-ZIP: `P-2025-12-23-01_urlaub-genehmigung-post.zip` = `ecb7c665a3e8c11083cf792e38bb542e2759033fd33572d701cad3a455c9b3fd`
- **DUPLICATE-CHECK:**
  - Backend-Genehmigung hatte bisher keine saubere POST-Verarbeitung/Action-Handling → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/UrlaubController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Genehmigen/Ablehnen als POST-Action im `UrlaubController` verdrahtet (inkl. Flash-Meldungen/Redirect).
- **NEXT:**
  - Entscheidung/Kommentar in „Meine Urlaubsanträge“ anzeigen (Mitarbeiter sieht Ergebnis).

## P-2025-12-23-02
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (aus Patch-ZIP): `docs/DEV_PROMPT_HISTORY.md` = `d0ffa4e206b39ed7cf2d1fdf7c391db717d4cf6f0e14da43e10bdccc74909da7`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Patch-ZIP: `P-2025-12-23-02_urlaub-meine-entscheidung.zip` = `fbdd89ed8a5f87895e525c3e5e093fd83126125eec21d8bad057e5cbf7008664`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: „Mitarbeiter sieht Entscheidung/Kommentar“ war noch nicht umgesetzt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `modelle/UrlaubsantragModel.php`
  - `views/urlaub/meine_antraege.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - „Meine Urlaubsanträge“ zeigt jetzt Entscheidung (Status) + Genehmiger-Kommentar/Name (falls vorhanden).
- **NEXT:**
  - Terminal-Flow härten: CSRF + Überlappung mit genehmigtem Urlaub blocken.

## P-2025-12-23-03
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (aus Patch-ZIP): `docs/DEV_PROMPT_HISTORY.md` = `2f750a9fae3333855c181f3136b6f2ab273c0fd19591dfdf702a57a879ac67f1`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Patch-ZIP: `P-2025-12-23-03_urlaub-csrf-ueberlappung-terminal.zip` = `b7f7ca95ff58c57bc45b786beb1291aaaae2767203ef4cfa86a4fb2b48ee1fb1`
- **DUPLICATE-CHECK:**
  - Terminal-Urlaub hatte noch kein CSRF + keine harte Überlappungsprüfung → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/TerminalController.php`
  - `views/urlaub/meine_antraege.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: CSRF-Token eingeführt.
  - Terminal: Urlaub beantragen blockiert, wenn der Zeitraum mit bereits genehmigtem Urlaub überlappt.
- **NEXT:**
  - Backend: Storno (Mitarbeiter kann eigenen offenen Antrag zurückziehen).

## P-2025-12-23-04
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (aus Patch-ZIP): `docs/DEV_PROMPT_HISTORY.md` = `7cf3be69170e3593d563d23a410399d9290ec4c33012e1ac5da93343e3ef4f3a`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Patch-ZIP: `P-2025-12-23-04_urlaub-storno-backend.zip` = `6de4f32bc9ef219687be8f5676222f52770fe0055c72529af39ad34bc76ec510`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Backend-Storno war noch nicht vorhanden → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/UrlaubController.php`
  - `views/urlaub/meine_antraege.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Mitarbeiter kann eigenen offenen Antrag stornieren (CSRF + Berechtigungscheck).
- **NEXT:**
  - Backend: Antragstellen blockiert Überlappung mit genehmigtem Urlaub.

## P-2025-12-23-05
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (aus Patch-ZIP): `docs/DEV_PROMPT_HISTORY.md` = `ce023eb0f256057b6010b78c8ea215c4838535ad5f6515522e492a15a8e287e7`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Patch-ZIP: `P-2025-12-23-05_urlaub-ueberlappung-backend.zip` = `a2c5ae66db1dcaa91cebe78e5ba2730671a07b1364405c5f75196c04d4f510e5`
- **DUPLICATE-CHECK:**
  - Backend-Urlaubsantrag (Mitarbeiter) hatte noch keine Überlappungsprüfung → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/UrlaubController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Antragstellen blockiert jetzt Überlappung mit bereits genehmigtem Urlaub (Legacy-Schutz, analog Terminal).
- **NEXT:**
  - Backend: Beim Genehmigen ebenfalls Überlappung prüfen und Genehmigung blocken.

## P-2025-12-23-06
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `6b741b5cfd0678857cf0c243634b7a58b8578f18b8dc25845fdb765a19958067`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Patch-ZIP: `P-2025-12-23-06_urlaub-genehmigung-ueberlappung-block.zip` = `822f090e06f758628ab79d049d77c227a61e56075f38153e0c77368ac9ca86ba`
- **DUPLICATE-CHECK:**
  - Genehmigung-Flow im Backend musste Überlappung beim Genehmigen hart blocken → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/UrlaubController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Genehmigen blockiert nun, wenn der Antrag mit bereits genehmigtem Urlaub überlappt.
- **NEXT:**
  - T-021 (Teil 1): Urlaubssaldo in „Meine Urlaubsanträge“ anzeigen.

## P-2025-12-23-07
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `a88fc868777482a04debae4b961ba0a49da47484bf038f3e217a882d5d0e352d`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce37b23345db70b80402e1fe0b25b65ed2169`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `6b741b5cfd0678857cf0c243634b7a58b8578f18b8dc25845fdb765a19958067`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Zusatz (Upload): `zeiterfassung_aktuell.sql` = `21c9d66cf329c0aa9de0ba2918299fedc9f8852ac265ae80a111f4a77cc5c158`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: T-021 war bisher nur als Vorschlag erwähnt, keine Umsetzung vorhanden → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `services/UrlaubService.php`
  - `controller/UrlaubController.php`
  - `views/urlaub/meine_antraege.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: In „Meine Urlaubsanträge“ wird jetzt ein Urlaubssaldo für das aktuelle Jahr angezeigt:
    - Anspruch (aus `mitarbeiter.urlaub_monatsanspruch * 12`)
    - Genehmigt/Offen als Arbeitstage (Feiertage/Betriebsferien werden abgezogen)
    - Verbleibend = Anspruch - Genehmigt - Offen
  - Hinweis: „Übertrag“ wird aktuell als 0.00 ausgewiesen (Teil 2 folgt).
- **NEXT:**
  - **T-021 (Teil 2):** Übertrag/Korrekturen pro Jahr modellieren (z. B. Tabelle `urlaub_kontingent_jahr`) und optional Validierung beim Antrag/Genehmigen.

## P-2025-12-24-11
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `55f200e59198c031371101505769244f727915748ba4e7397cfa38806b99f1ac`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce2ae3af59cf2416e2f55412220c6454b5615`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a4fff572ccd3b21a604f198cb3c4df29e11b3a7215a889e102e68cc374895b0b`
  - SQL-Schema: `sql/01_initial_schema.sql` = `7ed38d58e7e3e4d603c21e5beb13f50e1452def56685ac648c04cca309678450`
  - Zusatz (Upload): `zeiterfassung_aktuell.sql` = `17922d8bc8b265193a9bc93f7a2cbe7905fe95d17b29b924dc6a0feb660bf8e6`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Terminal-Offline-Queue war zwar vorhanden (T-003), aber **Kommen/Gehen** wurde bei DB-Ausfall noch nicht in die Queue geschrieben → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `core/OfflineQueueManager.php`
  - `services/ZeitService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal (Zeitbuchung): Wenn Hauptdatenbank nicht erreichbar ist, werden Kommen/Gehen als SQL-INSERT in `db_injektionsqueue` geschrieben (Rohzeit bleibt unverändert). Das Terminal bekommt eine Pseudo-ID (0) zurück.
  - OfflineQueueManager: Queue-Schema wird auf der Queue-DB automatisch angelegt (z. B. leere Offline-DB/SQLite) und Timestamp-Updates laufen ohne MySQL-spezifisches `NOW()`.
- **NEXT:**
  - **T-023:** Auftragsstart/-stopp offline in `db_injektionsqueue` spiegeln und Terminal-UI-Feedback „Offline gespeichert“ ergänzen.

## P-2025-12-24-25
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Basis): `gesammtesprojektaktuellzip.zip` = `1160f25dfd5afc74336f95dccb0c48baed95a4e7f17514c98bfed9028c290bf1`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce2ae3af59cf2416e2f55412220c6454b5615`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `86472844d7f4e9f1deb11057b092f41c6ecf9f82bfb0d8f79af7719a33e5803b`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Patch-ZIP: `P-2025-12-24-25_terminal-auftrag-offline-queue.zip`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-023 war noch offen → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `services/AuftragszeitService.php`
  - `controller/TerminalController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Auftragsstart bei Ausfall der Hauptdatenbank schreibt die nötigen SQL-Befehle in `db_injektionsqueue` (Alte Hauptaufträge schließen + neuen Hauptauftrag anlegen).
  - Terminal: Auftrag-Stopp bei Ausfall der Hauptdatenbank schreibt ein passendes UPDATE (per ID/Code/Fallback) in `db_injektionsqueue`.
  - Terminal-UI: Start/Stop zeigen konsistente Meldung „Hauptdatenbank offline – ... in Offline-Queue gespeichert“.
- **NEXT:**
  - **T-024:** Terminal-UI konsolidieren: Offline-Banner auf Startseite + Störungsmodus wenn weder Haupt-DB noch Offline-Queue verfügbar.

## P-2025-12-25-08
- **EINGELESEN (SHA256):**
  - `gesammtesprojektaktuellzip.zip`: `cdd54a4e73061407a1225d3ba3ff4ef6f6244b9fba8367e2d2e19645ac6fe390`
  - `master_prompt_zeiterfassung_v3.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `DEV_PROMPT_HISTORY.md` (Upload, Stand vor Patch): `f75901ccc94b224a36d32d7ea096dcfea5b558cd5468520afa1def2441a38b02`
  - SQL-Schema: `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - `zeiterfassung_aktuell.sql`: `17922d8bc8b265193a9bc93f7a2cbe7905fe95d17b29b924dc6a0feb660bf8e6`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-024** war offen. In `TerminalController::kommen()` wurde bei Offline-Queue (Pseudo-ID `0`) fälschlich „Kommen gebucht“ angezeigt.
  - `TerminalController::gehen()` hatte die Offline-Queue-Meldung bereits, `kommen()` nicht → **nicht DONE**, Patch erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `controller/TerminalController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: **Kommen** zeigt nun bei Offline-Queue (Pseudo-ID `0`) eine eindeutige Meldung „Hauptdatenbank offline – ... in Offline-Queue gespeichert“.
- **NEXT:**
  - **T-024:** Status/Offline-Banner als Partial auch in `views/terminal/auftrag_starten.php`, `views/terminal/auftrag_stoppen.php`, `views/terminal/urlaub_beantragen.php` einbinden.

## P-2025-12-25-10
- **EINGELESEN (SHA256):**
  - `gesammtesprojektaktuellzip.zip`: `ac43c24d65943b8fca64350966d12888d00a7497f09fad2de63e8c665716c3fb`
  - `master_prompt_zeiterfassung_v3.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `DEV_PROMPT_HISTORY.md` (Upload, Stand vor Patch): `ce2fa06668ae75842d499331ff151298dafedb5db20b06fd2e223457f66fdd52`
  - SQL-Schema: `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - `zeiterfassung_aktuell.sql`: `28580462914ba97a3216d9a75ce3ed7cfdc1702ab5fc5d99c30b728bb3b5a416`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: „Nebenauftrag starten/stoppen“ war in den Terminal-Anforderungen (Master-Prompt) vorgesehen, in Code/Routes aber nicht implementiert (Suche nach `nebenauftrag` ergab 0 Treffer) → **nicht DONE**.
  - DB-Schema unterstützt `typ` = `neben` bereits (kein SQL-Change nötig).
- **DATEIEN (max. 3 + DEV):**
  - `public/terminal.php`
  - `controller/TerminalController.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Neue Aktionen `nebenauftrag_starten` und `nebenauftrag_stoppen` (Routing in `public/terminal.php`).
  - Terminal-UI: Buttons „Nebenauftrag starten/stoppen“ auf der Startseite; Formulare sind in `views/terminal/start.php` integriert.
  - Online: Nebenauftrag-Start legt `auftragszeit` mit `typ='neben'` an (ohne Hauptauftrag automatisch zu schließen); Stop validiert, dass der Eintrag wirklich ein laufender Nebenauftrag des Mitarbeiters ist.
  - Offline: Start/Stop erzeugen passende SQL-Statements in `db_injektionsqueue` (INSERT/UPDATE mit Filter `typ='neben'`) und zeigen die bekannte „Offline gespeichert“-Meldung.
- **NEXT:**
  - **T-026:** Hauptauftrag-Stop sauber auf `typ='haupt'` begrenzen (UI/Service/Offline-Queue-Update), damit Nebenaufträge nicht versehentlich mitgestoppt werden.


## P-2025-12-31-04_terminal-kommen-gehen-post-csrf
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `gesammtesprojektaktuellzip.zip`: `56d215e2d4f1b2e8987099820eeb15ba162c34aeca19c0dfe50c14bc2d0d0848`
  - Master-Prompt `docs/master_prompt_zeiterfassung_v5.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch): `ddc0a6fcfb6d092ebd3be79f0d6df78fa2cab2701652ebf4f06af430bc3df5ce`
  - SQL-Schema `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - `sql/zeiterfassung_aktuell.sql`: `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Es gab keinen Patch, der **Kommen/Gehen** als mutierende Aktion auf **POST+CSRF** umstellt. → **nicht DONE**.
  - Legacy-Logout lief bisher über `?logout=1` (GET) – bleibt vorerst als Kompatibilität bestehen.
- **DATEIEN (max. 3 + DEV):**
  - `public/terminal.php`
  - `controller/TerminalController.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: **Kommen/Gehen** laufen jetzt nur noch über **POST + CSRF** und nutzen ein PRG-Muster (Flash + Redirect), damit Reload keine Doppelbuchung triggert.
  - Terminal: Neue Aktion `?aktion=logout` (POST + CSRF) + Startseite nutzt sie für Abmelden und Auto-Logout.
- **NEXT:**
  - **T-026:** `AuftragszeitService::stoppeAuftrag()` (online + offline) auf `typ='haupt'` begrenzen (sonst kann ein Nebenauftrag versehentlich als „Hauptauftrag stoppen“ beendet werden).


## P-2025-12-31-09_terminal-hauptauftrag-csrf-logout-bridge
- ZIP: `P-2025-12-31-09_terminal-hauptauftrag-csrf-logout-bridge.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `gesammtesprojektaktuellzip.zip`: `2d41db700d209314b92ca549dca595fe035be0545e7dd727ad7d56a5e895d4e5`
  - Master-Prompt `docs/master_prompt_zeiterfassung_v5.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch): `2817bef49eacdd4a234d7e0266088e804f8030f624d20b7a6799eb8408f41a2a`
  - SQL-Schema `sql/zeiterfassung_aktuell.sql`: `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - T-026 war im SNAPSHOT noch offen, ist im Code aber bereits umgesetzt (`AuftragszeitService::stoppeAuftrag()` filtert online + offline auf `typ='haupt'`). → **nicht erneut implementiert**.
  - T-027 war nur teilweise umgesetzt: Hauptauftrag-Start/Stop hatte keine CSRF-Validierung und Logout konnte in `TerminalController::start()` noch per GET (`?logout=1`) mutieren. → **Patch nötig**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/TerminalController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Hauptauftrag **Start/Stop** validiert jetzt **CSRF** (analog Nebenauftrag/Kommen/Gehen).
  - Terminal: `auftragStartenForm()`/`auftragStoppenForm()` liefern `$csrfToken` + `$terminalTimeoutSekunden` an die Views.
  - Terminal: Legacy-Logout per GET wurde entfernt und durch eine **POST-Bridge** ersetzt (Kompatibilität für alte Links/Auto-Logout ohne GET-Mutation).
- **NEXT:**
  - **T-027 (Teil 2):** Terminal-Views konsequent umstellen: Logout/Auto-Logout **nicht** mehr über `?logout=1`, sondern per POST-Form (`?aktion=logout`) mit CSRF; anschließend die Legacy-Bridge in `TerminalController::start()` entfernen.


## P-2025-12-31-11_terminal-logout-post-teil1
- ZIP: `P-2025-12-31-11_terminal-logout-post-teil1.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `gesammtesprojektaktuellzip.zip`: `56d215e2d4f1b2e8987099820eeb15ba162c34aeca19c0dfe50c14bc2d0d0848`
  - Master-Prompt `docs/master_prompt_zeiterfassung_v5.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch): `3575ff9d94e1ab7eb3c13c285ba1797c110197b327f7aed7270cad31ae72aa43`
  - SQL-Schema `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: T-027 ist offen (Teil 2: View-Umstellung + Bridge entfernen) und im Code noch nicht vollständig umgesetzt (mehrere Terminal-Views verwenden weiterhin `?logout=1`). → **Patch nötig**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/start.php`
  - `views/terminal/auftrag_starten.php`
  - `views/terminal/auftrag_stoppen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Auto-Logout in `start.php`, `auftrag_starten.php`, `auftrag_stoppen.php` löst jetzt **POST-Logout** (`?aktion=logout`) per Form-Submit aus (kein GET-Mutation-Fallback mehr).
  - Terminal: In Auftrag-Views wurde ein defensives Hidden-Logout-Formular ergänzt.
- **NEXT:**
  - **T-027 (Teil 2b):** `views/terminal/urlaub_beantragen.php` auf POST-Logout umstellen (Link + Auto-Logout), danach die Legacy-Bridge in `TerminalController::start()` entfernen.


## P-2025-12-31-12_terminal-logout-post-teil2
- ZIP: `P-2025-12-31-12_terminal-logout-post-teil2.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `gesammtesprojektaktuellzip.zip`: `56d215e2d4f1b2e8987099820eeb15ba162c34aeca19c0dfe50c14bc2d0d0848`
  - Master-Prompt `docs/master_prompt_zeiterfassung_v5.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch): `e3bdf89a6f67499ce798d2289f373ee7545a9866e6d2f4ce122c0a53924b0cd3`
  - SQL-Schema `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - `sql/zeiterfassung_aktuell.sql`: `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: T-027 war noch offen (Teil 2b: Urlaub-View + Bridge entfernen). In `views/terminal/urlaub_beantragen.php` gab es noch `?logout=1` und `TerminalController::start()` enthielt noch eine POST-Bridge für GET-Logout. → **Patch nötig**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/urlaub_beantragen.php`
  - `controller/TerminalController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: `urlaub_beantragen.php` nutzt für „Abmelden“ und Auto-Logout jetzt **POST-Logout** (`?aktion=logout`) mit CSRF (kein `?logout=1` mehr).
  - Terminal: Legacy-GET-Logout-Bridge in `TerminalController::start()` entfernt; `?logout=1` wird nun nur bereinigt und **mutiert nicht**.
  - Terminal: Urlaub-View Default-Fallback für Timeout auf **180s** angeglichen.
- **NEXT:**
  - **T-028 (optional):** Auto-Logout/Countdown-JS zentralisieren (Duplikate reduzieren).

## P-2025-12-31-13
- **EINGELESEN (SHA256):**
  - `gesammtesprojektaktuellzip.zip`: `def7a857c332d4d55a1d55c79f3f485f88348ca6a7067a9da742021b9198b551`
  - `master_prompt_zeiterfassung_v3.md`: `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `DEV_PROMPT_HISTORY.md` (Upload, Stand vor Patch): `801e0a7353e4aa32f581bdc344e3a99cf944ad327e93c2d0ff3912ae29b6389b`
  - SQL-Schema: `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - `sql/zeiterfassung_aktuell.sql`: `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-027 ist als DONE markiert (Logout/Auto-Logout vollständig auf POST+CSRF).
  - Code-Prüfung im Projekt-ZIP: `TerminalController::start()` reagierte noch auf `?logout=1` und **mutierte** per GET (Session wurde direkt gelöscht). Außerdem existierte noch keine eigene `?aktion=logout` Route im Terminal-Router.
  - Ergebnis: Patch ist **nicht** doppelt – Sicherheitslücke/Legacy-Mutation per GET musste geschlossen werden.
- **DATEIEN (max. 3 + DEV):**
  - `public/terminal.php`
  - `controller/TerminalController.php`
  - `views/terminal/logout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: neue Action `?aktion=logout` eingeführt.
  - Terminal: Logout ist nun **ausschließlich** mutierend per **POST + CSRF**.
  - Terminal: GET auf `?aktion=logout` zeigt eine kleine Zwischen-Seite, die den POST automatisch auslöst (kiosk-tauglich, funktioniert auch mit bestehendem Auto-Logout).
  - Terminal: Legacy `?logout=1` wird in `start()` nur noch **nicht-mutierend** auf `?aktion=logout` umgeleitet.
- **NEXT:**
  - **T-028 (optional):** Auto-Logout/Countdown-JS zentralisieren (Duplikate reduzieren) oder als Cleanup die Terminal-Views sukzessive von `?logout=1` auf `?aktion=logout` umstellen (ohne Funktionsänderung).

## P-2025-12-31-14_terminal-autologout-partial-teil1
- ZIP: `P-2025-12-31-14_terminal-autologout-partial-teil1.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `gesammtesprojektaktuellzip.zip`: `91083a7b313d1cf31271756c34f99c1f8c078540d3c00f30c807b09251453c8e`
  - Master-Prompt `master_prompt_zeiterfassung_v3.md` (Upload): `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `DEV_PROMPT_HISTORY.md` (Upload, Stand vor Patch): `801e0a7353e4aa32f581bdc344e3a99cf944ad327e93c2d0ff3912ae29b6389b`
  - `zeiterfassung_aktuell.sql` (Upload): `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
  - SQL-Schema `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `P-2025-12-31-13_terminal-logout-action.zip`: `8cb70699d7de9e594eb40bc543807806b7d8e33f6030ee7bd0d24f219e25a568`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: T-028 ist offen. In mehreren Terminal-Views existieren weiterhin identische Auto-Logout/Countdown-Skriptblöcke. → **Patch nötig**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_autologout.php`
  - `views/terminal/start.php`
  - `views/terminal/auftrag_starten.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Auto-Logout/Countdown in `start.php` und `auftrag_starten.php` auf gemeinsame Partial `views/terminal/_autologout.php` umgestellt.
  - Partial ist defensiv: räumt alte Timer auf (Cache/Back-Button) und erzeugt das Countdown-Badge nur einmal.
- **NEXT:**
  - **T-028 (optional):** `auftrag_stoppen.php` und `urlaub_beantragen.php` ebenfalls auf `_autologout.php` umstellen (Duplikate entfernen).


## P-2025-12-31-15
- ZIP: `P-2025-12-31-15_terminal-autologout-partial-teil2.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `def7a857c332d4d55a1d55c79f3f485f88348ca6a7067a9da742021b9198b551`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v3.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Basis): `docs/DEV_PROMPT_HISTORY.md` (Stand aus P-2025-12-31-14) = `6f38e97ec5cd0171bd1d5230f31fae4c0b3c11ec8def30abe742e31e310f7eae`
  - SQL (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
  - Zusatz (letzter Patch): `P-2025-12-31-14_terminal-autologout-partial-teil1.zip` = `80b5e35c864d5d00705df0d2878290d1774930aa091128ed39429ab9cb482c6b`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-028** war offen (Teil 2: restliche Terminal-Views auf Auto-Logout-Partial umstellen).
  - Code-Scan im Projekt: `views/terminal/auftrag_stoppen.php` und `views/terminal/urlaub_beantragen.php` enthielten noch das duplizierte Countdown-Script → **nicht DONE**, Patch nötig.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/auftrag_stoppen.php`
  - `views/terminal/urlaub_beantragen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: `auftrag_stoppen.php` nutzt jetzt die gemeinsame Auto-Logout-Partial `views/terminal/_autologout.php` (Duplikat-Script entfernt).
  - Terminal: `urlaub_beantragen.php` nutzt jetzt ebenfalls die Auto-Logout-Partial; Default-Timeout bleibt **180s**, wenn kein Wert aus dem Controller geliefert wird.
- **NEXT:**
  - Terminal: manueller Smoke-Test (Start, Auftrag starten/stoppen, Urlaub beantragen) – Auto-Logout sichtbar + Logout läuft per POST+CSRF.
  - Danach nächstes Feature priorisieren (kein weiteres offenes T-ID im SNAPSHOT).

## P-2025-12-31-16
- ZIP: `P-2025-12-31-16_terminal-focus-keeper.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `def7a857c332d4d55a1d55c79f3f485f88348ca6a7067a9da742021b9198b551`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v3.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Basis): `docs/DEV_PROMPT_HISTORY.md` = `1018ebadb05c812f27a8c2af76564825ba416a063e6c32721e7e46cdef3fef6f`
  - SQL (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: keine offenen T-IDs. Letzter Cleanup war T-028 (Auto-Logout zentralisiert) → bereits DONE.
  - Code-Prüfung: In `views/terminal/_autologout.php` existierte noch **keine** Fokus-/Scanner-UX-Logik (nur Timer/Countdown). → Patch ist **nicht** doppelt.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_autologout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: `_autologout.php` erweitert um einen **Fokus-Keeper** für Keyboard-Wedge-Scanner/Reader:
    - Klick/Touch setzt den Auto-Logout zurück und fokussiert danach wieder ein sinnvolles Eingabefeld.
    - Bei Keydown ohne fokussiertes Input wird das Ziel-Input fokussiert; erstes Zeichen wird best-effort nicht verloren.
    - Optional kann ein Input explizit markiert werden: `data-terminal-scan="1"`.
- **NEXT:**
  - Terminal: Smoke-Test im Kiosk (Klick → Scan) + Auto-Logout-Auslösung prüfen; danach nächstes Terminal-UX/CSS-Refactoring als neue T-ID priorisieren.

## P-2025-12-31-17
- ZIP: `P-2025-12-31-17_terminal-autologout-hardening.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `def7a857c332d4d55a1d55c79f3f485f88348ca6a7067a9da742021b9198b551`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v3.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Basis): `docs/DEV_PROMPT_HISTORY.md` = `cca13742268b2f28dfc1bc17dd56c0bd856dbb4101e2cd4b9d0d74c5a9b4f21e`
  - SQL (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: keine offenen T-IDs; letzter Terminal-UX Patch war **P-2025-12-31-16**.
  - Code-Prüfung: In `views/terminal/_autologout.php` war der Fallback-Redirect noch auf `terminal.php?aktion=start` (kein Logout) und die Fokus-Ziel-Auswahl hatte keine Priorität für typische Scan-Inputs. → **nicht DONE**, Patch notwendig.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_autologout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Auto-Logout-Fallback geht jetzt auf `terminal.php?aktion=logout` (statt `aktion=start`).
  - Terminal: Fokus-Ziel-Auswahl priorisiert typische Scan-Inputs (`rfid_code`, `auftragscode`, `neben_auftragscode`, `auftragszeit_id`) sowie `placeholder="Scan"` (sichtbar), bevor die generischen Heuristiken greifen.
- **NEXT:**
  - Terminal: Smoke-Test im Kiosk (Klick → Scan, Auto-Logout-Auslösung, Logout-Flow).
  - Danach: optional **T-030** definieren (Terminal-CSS in gemeinsame Datei auslagern) und in kleinen Patches umstellen.

## P-2025-12-31-18
- ZIP: `P-2025-12-31-18_terminal-css-extract-start.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `c0b09a8386a23e7bb1c99c23ddf0f66eaeb253a2c35a35e0ac4375be2c79b748`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Basis): `docs/DEV_PROMPT_HISTORY.md` = `d14165a1f6acffdb3b04a416580baaddb4374fd91385892bc16e4a336a88eb1a`
  - SQL: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: letzter Hinweis war „optional T-030 definieren“.
  - Code-Prüfung: Terminal-Views enthalten weiterhin duplizierte Inline-`<style>`-Blöcke. → **nicht DONE**, Extraktion sinnvoll.
- **DATEIEN (max. 3 + DEV):**
  - `public/css/terminal.css`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: CSS aus `views/terminal/start.php` nach `public/css/terminal.css` ausgelagert.
  - Terminal: `start.php` nutzt jetzt `<link rel="stylesheet" href="css/terminal.css">` und enthält keinen Inline-Style-Block mehr.
- **NEXT:**
  - Terminal: nächste View (z. B. `auftrag_starten.php`) ebenfalls umstellen und fehlende Styles ggf. in `terminal.css` ergänzen.

## P-2025-12-31-19
- ZIP: `P-2025-12-31-19_terminal-css-auftrag-starten.zip`
- **EINGELESEN (SHA256):**
  - `gesammtesprojektaktuellzip.zip`: `400aee90640d804a9447a12da37b2dd1ab6a70fab79519cbc4b92ef5b4130c8b`
  - `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP): `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - `docs/DEV_PROMPT_HISTORY.md` (aus ZIP): `133457b2ac9721bc8cee5707a499deef5a48c2029ee7e649beb8bb0a849ef189`
  - `sql/01_initial_schema.sql`: `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Hinweis: `sql/zeiterfassung_aktuell.sql` ist vorhanden (`1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`), Source of Truth bleibt aber `sql/01_initial_schema.sql`.
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-030 (Teil 2)** ist offen. `start.php` ist bereits umgestellt (**P-2025-12-31-18**).
  - `views/terminal/auftrag_starten.php` hatte noch Inline-CSS → **nicht DONE**, Umsetzung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/auftrag_starten.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `auftrag_starten.php` nutzt jetzt `public/css/terminal.css` (Inline-`<style>` entfernt).
  - Form auf `class="login-form"` umgestellt + Buttons nutzen `button-row` für konsistentes Terminal-Layout.
- **NEXT:**
  - `views/terminal/auftrag_stoppen.php` als nächstes auf `public/css/terminal.css` umstellen.
  - Danach: `views/terminal/urlaub_beantragen.php` umstellen und fehlende Styles ggf. in `terminal.css` ergänzen.

## P-2025-12-31-20
- ZIP: `P-2025-12-31-20_terminal-css-auftrag-stoppen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `f765634975b9e987f453dffa7f566a81a7c995274b5d9abbe4468f71a834a1fa`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `645f8ee2777b3ea6c90146cad154dd2450636a89e128249d3c353bc92203bdbb`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Hinweis: `sql/zeiterfassung_aktuell.sql` ist vorhanden (`1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`), Source of Truth bleibt aber `sql/01_initial_schema.sql`.
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-030 (Teil 2)** ist offen (Rest: `urlaub_beantragen.php`).
  - `views/terminal/auftrag_stoppen.php` hatte noch Inline-`<style>` → **nicht DONE**, Umstellung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/auftrag_stoppen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `auftrag_stoppen.php` nutzt jetzt `public/css/terminal.css` (Inline-`<style>` entfernt).
  - Form-Layout an Terminal-Standard angepasst (`class="login-form"` + `button-row`; Danger-Button nutzt `button-danger`).
- **NEXT:**
  - `views/terminal/urlaub_beantragen.php` auf `public/css/terminal.css` umstellen (Inline-`<style>` entfernen) und ggf. fehlende Styles in `terminal.css` ergänzen.



## P-2025-12-31-21
- ZIP: `P-2025-12-31-21_terminal-css-urlaub-beantragen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `ce3cec2220939a5b1a957e5ebdf6088f1ed19e637682248b67be8d18d5a65b52`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `e1b300d9ea4d8f3d9f2fe14df1b10f5c11165cb959daafbeb519f5bed9b4192b`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Hinweis: `zeiterfassung_aktuell.sql` (Upload) = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT + LOG geprüft: **T-030 (Teil 3)** (Urlaub-View auf `terminal.css`) war laut SNAPSHOT als nächster Schritt offen.
  - `views/terminal/urlaub_beantragen.php` enthielt noch Inline-`<style>` und keine `terminal.css`-Einbindung → **nicht DONE**, Umsetzung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/urlaub_beantragen.php`
  - `public/css/terminal.css`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `urlaub_beantragen.php` nutzt jetzt `public/css/terminal.css` (Inline-`<style>` entfernt).
  - `terminal.css` um `.top-actions` ergänzt (kompakte Aktionsleiste oben).
  - Logout-Link-Button vereinheitlicht (`logout-button`).
- **NEXT:**
  - **T-031 (Teil 1):** `views/terminal/stoerung.php` + `views/terminal/logout.php` auf `public/css/terminal.css` umstellen (Inline-CSS entfernen) und Styles bei Bedarf ergänzen.
  - Danach: `_statusbox.php` CSS aus dem Inline-`<style>` herausziehen (wenn alle relevanten Terminal-Views `terminal.css` nutzen).

## P-2025-12-31-22
- ZIP: `P-2025-12-31-22_terminal-css-stoerung-logout.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `abcf237958d5814fea4af9f7dfe9c3fd74fd56e69772fd877ff3cbd00514ecfd`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `1bf366379c1bdaecbab51b7320d8357955912706f69e815af992adcdcded731c`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Hinweis: `sql/zeiterfassung_aktuell.sql` ist vorhanden (`1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`), Source of Truth bleibt aber `sql/01_initial_schema.sql`.
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-031 (Teil 1)** war offen.
  - `views/terminal/stoerung.php` und `views/terminal/logout.php` enthielten Inline-`<style>` → **nicht DONE**, Umstellung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/stoerung.php`
  - `views/terminal/logout.php`
  - `public/css/terminal.css`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `stoerung.php` + `logout.php` binden jetzt `public/css/terminal.css` ein (Inline-`<style>` entfernt).
  - Kleine Layout-Varianten ergänzt (`body.terminal-wide` / `body.terminal-center`) + Hilfsklassen (`status-small.mt`, `p.hinweis.center`).
- **NEXT:**
  - **T-032:** `_statusbox.php` CSS in `public/css/terminal.css` integrieren und Inline-`<style>` entfernen.

## P-2025-12-31-23
- ZIP: `P-2025-12-31-23_terminal-css-statusbox.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `d6d352a75b324deacd590050a93637df3bc6470bed2942cff61519431d8fe0f9`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a586d2441d0aa3e413c175d28688b0080b825ecae6f9fd6aafaf96569a555b70`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT + LOG geprüft: **T-032** war offen (NEXT aus P-2025-12-31-22).
  - `views/terminal/_statusbox.php` enthielt ein Inline-`<style>` (überschrieb `terminal.css`) → **nicht DONE**, Umsetzung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_statusbox.php`
  - `public/css/terminal.css`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Inline-`<style>` aus `_statusbox.php` entfernt; Statusbox nutzt nun ausschließlich `public/css/terminal.css`.
  - Kleine Statusbox-Hilfsklassen ergänzt (`.status-hint`, `.status-details-label`, Summary-Cursor).
- **NEXT:**
  - **T-033 (optional):** `public/css/terminal.css` aufräumen (Einrückung normalisieren, doppelte Selektoren entfernen). Keine Design-Änderungen.

## P-2025-12-31-24
- ZIP: `P-2025-12-31-24_terminal-css-cleanup.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `bf9e260fd304b6692d05911f77e84d2dc971b7d43fc3005e7dccf291faf4f9e3`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v2.md` = `d5ae8ed4f97cf25923fc33b08dcce2ae3af59cf2416e2f55412220c6454b5615`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `0a6c35449b68aabc9e889dd8b5d4188afdef0a83486a0c19a7609ca289057fab`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT + LOG geprüft: **T-033** war offen (NEXT aus P-2025-12-31-23) und nicht als DONE markiert.
- **DATEIEN (max. 3 + DEV):**
  - `public/css/terminal.css`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `public/css/terminal.css` konsolidiert: saubere Einrückung, doppelte Selektoren zusammengeführt (Layout unverändert).
  - Fehlende Alias-Selektoren ergänzt für bereits genutzte Klassen (`.actions`, `.quick-actions`, `.btn-danger`).
- **NEXT:**
  - **T-034 (optional):** `views/terminal/auftrag_stoppen.php` Klassen vereinheitlichen (`.actions`/`.quick-actions` → `.button-row`, `.btn-danger` → `.button-danger`) und danach Alias-Selektoren in `public/css/terminal.css` entfernen.

## P-2025-12-31-25
- ZIP: `P-2025-12-31-25_terminal-css-unify-classes.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `b5354d75c99bcf79ae58f5cf8aacd4950e8c9ac895c0bdf82aa608dba68605d5`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `db7afdfab17d6d6f45f12dc44cc1d6309dcb147b829c4c66f84bfce6c81b1e8f`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-034** war offen und nicht als DONE markiert.
  - Code-Check: `views/terminal/auftrag_stoppen.php` nutzte noch `.actions`/`.quick-actions`/`.btn-danger` → Umsetzung erforderlich.
- **DATEIEN (max. 3):**
  - `views/terminal/auftrag_stoppen.php`
  - `public/css/terminal.css`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `auftrag_stoppen.php`: Klassen vereinheitlicht (`.button-row`, `.button-danger`).
  - `terminal.css`: Alias-Selektoren (`.actions`, `.quick-actions`, `button.btn-danger`) entfernt.
- **NEXT:**
  - Terminal: kurzer Smoke-Test im Kiosk (Start, Auftrag starten/stoppen, Auto-Logout, Logout POST+CSRF) und danach neuen Task priorisieren.

## P-2025-12-31-26
- ZIP: `P-2025-12-31-26_terminal-autologout-external-js.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `8527272c393be68a677e0592db2dc518cf715faf5bec69dfb029c08167f599dd`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ea84aa0b4cb369aa629e461cebe6d4dc905ee9ac2f6cf2b8a058c3b5fa0cb375`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Hinweis: `sql/zeiterfassung_aktuell.sql` ist vorhanden (`1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`), Source of Truth bleibt aber `sql/01_initial_schema.sql`.
- **DUPLICATE-CHECK:**
  - SNAPSHOT + LOG geprüft: Es gab **keinen** Patch, der Inline-JS aus `views/terminal/_autologout.php` vollständig entfernt.
  - Code-Check: `_autologout.php` enthielt noch umfangreiche Inline-`<script>`-Logik → Auslagerung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `public/js/terminal-autologout.js`
  - `views/terminal/_autologout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Auto-Logout/Countdown/Fokus-Keeper aus `_autologout.php` nach `public/js/terminal-autologout.js` ausgelagert.
  - `_autologout.php` lädt nur noch das externe Script (mit `data-timeout-sekunden` + `data-logout-url`) und hat keine Inline-JS mehr.
  - Cache-Busting (`?v=filemtime`) integriert, damit Kiosk/Browser Updates sicher ziehen.
- **NEXT:**
  - Optional: Countdown-Badge Styling in `public/css/terminal.css` umstellen (statt Inline-Styles im JS).

## P-2025-12-31-27
- ZIP: `P-2025-12-31-27_terminal-countdown-css.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `bf7013b81373c0e680f82c43b3f916674de70dbeb59772289fe57e5a49f44703`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `34251ccb0a5e14a3991beba936237e957bde2c79f1fff9d8b9d79eb692fc3eb5`
  - SQL-Schema: `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Hinweis: `sql/zeiterfassung_aktuell.sql` ist vorhanden (`1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`), Source of Truth bleibt aber `sql/01_initial_schema.sql`.
- **DUPLICATE-CHECK:**
  - NEXT aus P-2025-12-31-26: „Countdown-Badge Styling in `public/css/terminal.css` statt Inline-Styles im JS“ war offen.
  - Code-Check: `public/js/terminal-autologout.js` setzte das Countdown-Badge per Inline-Styles → Umsetzung erforderlich.
- **DATEIEN (max. 3 + DEV):**
  - `public/js/terminal-autologout.js`
  - `public/css/terminal.css`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Countdown-Badge Styling nach `public/css/terminal.css` verschoben (`.terminal-countdown`).
  - Inline-Styling in `public/js/terminal-autologout.js` entfernt; Badge erhält nur noch die Klasse.
- **NEXT:**
  - Terminal Smoke-Test (Start/Scan, Auftrag starten/stoppen, Urlaub, Auto-Logout, Logout POST+CSRF) und danach nächsten funktionalen Terminal-Task priorisieren.

## P-2025-12-31-28
- ZIP: `P-2025-12-31-28_terminal-layout-partials.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `94935a61a7dbd0b00a8928a67054f095a6612b88b1b6478cc6e59379db3f256e`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `1eb6218a2c5a489528bb0971ac826e8a7c14ce158a3ab219d5be13516571023f`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: kein Eintrag für Layout-Partials in Terminal-Views → **nicht DONE**.
  - Hinweis: Input-ZIP enthielt bereits CSS/JS-Anpassungen (z. B. `.terminal-countdown`), die im SNAPSHOT noch nicht als eigener Patch geloggt waren. Wir haben darauf aufgesetzt und nichts doppelt implementiert.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_layout_top.php`
  - `views/terminal/_layout_bottom.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Gemeinsame Layout-Partials eingeführt; `terminal.css` wird per `?v=filemtime` geladen (Cache-Busting).
  - `start.php` nutzt Layout-Partials (Head/Body/Wrapper nicht mehr dupliziert).
- **NEXT:**
  - T-036 (Teil 2): `views/terminal/auftrag_starten.php` auf Layout-Partials umstellen.

## P-2025-12-31-29
- ZIP: `P-2025-12-31-29_terminal-layout-auftrag-starten.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `def7a857c332d4d55a1d55c79f3f485f88348ca6a7067a9da742021b9198b551`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `6182e8a4d8a5eff38f4630c86ccdfedfcb2bc779144eff19c0f6f26a1f6793f4`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-036 (Teil 2) war offen; `views/terminal/auftrag_starten.php` war noch nicht auf Layout-Partials umgestellt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/auftrag_starten.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `auftrag_starten.php` nutzt `_layout_top.php`/`_layout_bottom.php` (inkl. CSS Cache-Busting über Layout).
- **NEXT:**
  - T-036 (Teil 2): `views/terminal/auftrag_stoppen.php` auf Layout-Partials umstellen.

## P-2025-12-31-30
- ZIP: `P-2025-12-31-30_terminal-layout-auftrag-stoppen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `18557b349907892c193d9e74ab3eae6180ceea33202d6f0928e4d7ad8f3725f4`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `035d85295a13b0b67be3a4e3ab438b63dac14df96494241b047dcbba2685d8da`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-036 (Teil 2) war offen; `views/terminal/auftrag_stoppen.php` war noch nicht auf Layout-Partials umgestellt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/auftrag_stoppen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `auftrag_stoppen.php` nutzt `_layout_top.php`/`_layout_bottom.php` (inkl. CSS Cache-Busting über Layout) und folgt dem Standard (hidden `logout-form` + `_autologout.php`).
- **NEXT:**
  - T-036 (Teil 2): `views/terminal/urlaub_beantragen.php` auf Layout-Partials umstellen.

## P-2025-12-31-31
- ZIP: `P-2025-12-31-31_terminal-layout-urlaub-beantragen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `def7a857c332d4d55a1d55c79f3f485f88348ca6a7067a9da742021b9198b551`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `d193780a40c484b94e2a477c6f30a8736ff7d2169878022535e5675713528f64`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-036 (Teil 2) war offen; `views/terminal/urlaub_beantragen.php` war noch nicht auf Layout-Partials umgestellt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/urlaub_beantragen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `urlaub_beantragen.php` nutzt `_layout_top.php`/`_layout_bottom.php` (inkl. CSS Cache-Busting über Layout).
- **NEXT:**
  - T-036 (Teil 2): `views/terminal/stoerung.php` + `views/terminal/logout.php` auf Layout-Partials umstellen.

## P-2025-12-31-32
- ZIP: `P-2025-12-31-32_terminal-layout-stoerung-logout.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `ff9f3b0cd3f7f0634c30ec182b987d25901bcabd8117cb13fed90902a09ed770`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `6dc0c60db8deeb3be0e8661538b4dd5040de484ca721ac7c773984367f3cac3a`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-036 (Teil 2) war offen; `views/terminal/stoerung.php` + `views/terminal/logout.php` waren noch nicht auf Layout-Partials umgestellt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_layout_top.php`
  - `views/terminal/stoerung.php`
  - `views/terminal/logout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `_layout_top.php` unterstützt jetzt optional `$bodyKlasse` (z. B. `terminal-wide`, `terminal-center`) – rückwärtskompatibel.
  - `stoerung.php` und `logout.php` nutzen jetzt `_layout_top.php`/`_layout_bottom.php` (inkl. CSS Cache-Busting), kein dupliziertes HTML-Gerüst mehr.
- **NEXT:**
  - T-036 (Teil 2) ist damit vollständig erledigt. Als nächstes: restliche Terminal-Views prüfen (z. B. `index.php`, `info_uebersicht.php`, `hauptmenue.php`) und bei Bedarf ebenfalls auf Layout-Partials umstellen oder entfernen, falls ungenutzt.

## P-2025-12-31-33
- ZIP: `P-2025-12-31-33_terminal-legacy-views-layout.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `4194d63a11ab6eb822d06eca952d025fb3ddd60be15bbf197f90829cbd75267a`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `cd5350c4fa02a67fbf60820cb789b1f2fbe36fb87fa1b74e7bcf0703b2fbbc8c`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Legacy-Platzhalter-Views (`views/terminal/index.php`, `views/terminal/hauptmenue.php`, `views/terminal/info_uebersicht.php`) waren noch nicht auf Terminal-Layout umgestellt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/index.php`
  - `views/terminal/hauptmenue.php`
  - `views/terminal/info_uebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Die drei Legacy-Views nutzen jetzt das Terminal-Layout (`_layout_top.php`/`_layout_bottom.php`) und zeigen einen klaren Hinweis inkl. Link auf den echten Terminal-Einstieg.
- **NEXT:**
  - T-038: Inline-Styles in Terminal-Views reduzieren (Utility-Klassen in `public/css/terminal.css`, anschließend `start.php` + `urlaub_beantragen.php` auf Klassen umstellen).

## P-2025-12-31-34
- ZIP: `P-2025-12-31-34_terminal-inline-styles-start.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `f08d217b273f1fec667c0de8e8bd351656ad568297a06f5d7e2753c263153114`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `462bf5f86e2464089ba43c81a62a8e93501d417c9c80858672e1125de28a82b8`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-038 war offen; `views/terminal/start.php` hatte noch mehrere `style="..."`-Attribute → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `public/css/terminal.css`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Utility-Klassen für häufige Abstände (`mt-*`, `mb-*`) + `.is-hidden` ergänzt.
  - In `start.php` alle Inline-Styles entfernt und durch Utility-Klassen ersetzt.
- **NEXT:**
  - T-038 weiter: `views/terminal/urlaub_beantragen.php` Inline-Styles entfernen (Utility-Klassen verwenden), danach optional die versteckten Logout-Forms (`style="display:none"`) auf `.is-hidden` umstellen.

## P-2025-12-31-35
- ZIP: `P-2025-12-31-35_terminal-csrf-token-startscreen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `e29e47936038a3f317341878e9893932e61587b0b8b40fa0ca46ced40a20c860`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `810d7e52f37b12bba21f0b18eb8e9a5037df867c789334742c7dac8de1d3b7fa`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - Zusatz: `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Kein Eintrag, der „CSRF-Token am Startscreen ist immer gesetzt“ abdeckt → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `controller/TerminalController.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `TerminalController::start()` setzt `$csrfToken` immer vor dem Rendern von `start.php`.
  - `views/terminal/start.php` nutzt bei leerem `$csrfToken` defensiv den Session-Token (`$_SESSION['terminal_csrf_token']`).
- **NEXT:**
  - T-040: serverseitiges Inaktivitäts-Timeout (Session idle) als Fallback zum JS Auto-Logout.

## P-2025-12-31-36
- ZIP: `P-2025-12-31-36_terminal-server-idle-timeout.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `bf91124bb0be2b8c41f412707bd33969773a2842e7e4ab0a5c95c95062e3f036`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `09e589c13b3a9d226c21ce7f66d180fce196314e68f7aa1aad3dfcb40c9b1f06`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-040 war als „offen“ geführt, kein bestehender serverseitiger Idle-Guard in `public/terminal.php` → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `public/terminal.php`
  - `core/DefaultsSeeder.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Pro Request wird `$_SESSION['terminal_last_activity_ts']` aktualisiert.
  - Wenn ein Mitarbeiter angemeldet ist und die Inaktivität größer als das Timeout ist, wird serverseitig auf `aktion=logout` umgeleitet.
  - Neuer Config-Key (idempotent geseedet): `terminal_session_idle_timeout` (int, Default 300 Sekunden).
- **NEXT:**
  - T-038 (Teil 2): Inline-Styles in `views/terminal/urlaub_beantragen.php` weiter reduzieren (Utility-Klassen in `public/css/terminal.css`).

## P-2025-12-31-37
- ZIP: `P-2025-12-31-37_terminal-urlaub-inline-styles-2.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `c0057fc55b52a54f59c181acc5f5091005e46d8cc35f08f2e78db28991c7a720`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `a657d536fb5c22cb92db4cf7b3f246abb06a3f0c3a3e17161df7f5244c9615d1`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - SQL-Export (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: **T-038 (Teil 2)** war als „offen“ geführt, in `views/terminal/urlaub_beantragen.php` existierte noch Inline-Style am Logout-Formular → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/urlaub_beantragen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: In `views/terminal/urlaub_beantragen.php` den letzten Inline-Style entfernt (`style="display:none"` → `class="is-hidden"`).
  - Datei enthält nun **keine** Inline-Styles mehr.
- **NEXT:**
  - T-041: Inline-Styles in `views/terminal/auftrag_starten.php` und `views/terminal/auftrag_stoppen.php` entfernen (analog).

## P-2025-12-31-38
- ZIP: `P-2025-12-31-38_terminal-auftrag-inline-styles.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `f82b8b1d271affdecb7b74a36084969b8dab411c6a02bb6f6bfe09d72859a40b`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `157dbc883de31fa5d5ce3b7432eb85548b054e512278ac6fa4fba2d1fbeeefe5`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - SQL-Export (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: **T-041** war als „offen“ geführt. In `views/terminal/auftrag_starten.php` und `views/terminal/auftrag_stoppen.php` existierte noch `style="display:none"` am Logout-Formular → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/auftrag_starten.php`
  - `views/terminal/auftrag_stoppen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: In `views/terminal/auftrag_starten.php` und `views/terminal/auftrag_stoppen.php` den letzten Inline-Style entfernt (`style="display:none"` → `class="is-hidden"`).
  - Damit enthalten die Terminal-Views jetzt **keine** Inline-Styles mehr (Utility-Klasse `.is-hidden` existiert bereits in `public/css/terminal.css`).
- **NEXT:**
  - **T-042:** Inline-JS in `views/terminal/logout.php` entfernen (Auto-Submit) und als externe JS-Datei auslagern.

## P-2025-12-31-39
- ZIP: `P-2025-12-31-39_terminal-logout-inline-js.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `72e43afe93a7d9a5b863a0c5ddc6ed3223f4273a45d92d890aa06b8566c3c63d`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `6a56728858d966447e6279d0ca6d4c5f9dc3c22fdff8f0bf57f2faa1ada1ab51`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - SQL-Export (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: **T-042** war als „offen“ geführt. In `views/terminal/logout.php` existierte noch Inline-JS (Auto-Submit) → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/logout.php`
  - `public/js/terminal-logout.js`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Inline-JS aus `views/terminal/logout.php` entfernt.
  - Auto-Submit ausgelagert nach `public/js/terminal-logout.js` inkl. Cache-Busting (filemtime wie bei `terminal-autologout.js`).
- **NEXT:**
  - **T-043:** JS-Cache-Busting in eine gemeinsame Helper-Partial auslagern (DRY) und in `_autologout.php` + `logout.php` verwenden.

## P-2025-12-31-40
- ZIP: `P-2025-12-31-40_terminal-js-cachebust-helper.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `b6a78f6e6694e007dbd908bb9db9090e4c529ab93fa64945c1586680dc19d5f2`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `c611048b859ef3fde6c3e6fe663073e3bc8152dba998468a6691ac6315d3700b`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - SQL-Export (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: **T-043** war als „offen“ geführt.
  - In `views/terminal/_autologout.php` und `views/terminal/logout.php` existierte jeweils eigener `filemtime()`-Cache-Busting-Code → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_script.php`
  - `views/terminal/_autologout.php`
  - `views/terminal/logout.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: neue Helper-Partial `views/terminal/_script.php` für `<script src="...">` inkl. `filemtime()`-Cache-Busting und optionale Attribute (`data-*`, `defer`, ...).
  - `_autologout.php` und `logout.php` auf die Helper-Partial umgestellt (DRY).
- **NEXT:**
  - **T-044:** CSS-Cache-Busting in eine gemeinsame Helper-Partial auslagern (DRY) und in `views/terminal/_layout_top.php` verwenden.


## P-2025-12-31-41
- ZIP: `P-2025-12-31-41_terminal-css-cachebust-helper.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `71051284ba7016b5270cfb5202bbc686c00418d516c5e0a06bb435936ec21a86`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `fc1be71253f18406c13df25da402df5656b3084ff3f6b0e01af113db4469ba52`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - SQL-Export (Upload): `zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: **T-044** war als „offen“ geführt.
  - In `views/terminal/_layout_top.php` existierte eigener `filemtime()`-Cache-Busting-Code für `terminal.css` → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_style.php`
  - `views/terminal/_layout_top.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: neue Helper-Partial `views/terminal/_style.php` für `<link rel="stylesheet" ...>` inkl. `filemtime()`-Cache-Busting und optionale Attribute.
  - `views/terminal/_layout_top.php` auf die Helper-Partial umgestellt (DRY).
- **NEXT:**
  - **T-045:** Logout-Formular als Partial auslagern (DRY) – schrittweise 1–2 Views pro Patch (max. 3 Dateien).

## P-2025-12-31-46
- ZIP: `P-2025-12-31-46_terminal-logout-form-partial-1.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `gesammtesprojektaktuellzip.zip` = `c0057fc55b52a54f59c181acc5f5091005e46d8cc35f08f2e78db28991c7a720`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` (aus ZIP) = `b9925b7601f084e6cc8c937e1e66c78cba664c3f070a13b680d5f8df1fba115b`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (aus ZIP) = `c40ef3c11727b1aefb320d12e00f54e045aa1480706bfc0e71e9ab3bce63d7e4`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `5edc2dfda046186a5f25470ecb0b9e45b7c4acb4e1061c6ef6c86a3bf7b6b048`
  - SQL-Export (im Repo): `sql/zeiterfassung_aktuell.sql` = `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: **T-045** war als „offen“ geführt.
  - In `auftrag_starten.php` und `auftrag_stoppen.php` war das Logout-Formular (id=`logout-form`) noch dupliziert vorhanden → **nicht DONE**.
- **DATEIEN (max. 3 + DEV):**
  - `views/terminal/_logout_form.php`
  - `views/terminal/auftrag_starten.php`
  - `views/terminal/auftrag_stoppen.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: neue Partial `views/terminal/_logout_form.php` für das Logout-POST-Formular inkl. CSRF.
  - `auftrag_starten.php` und `auftrag_stoppen.php` nutzen jetzt die Partial (DRY), weiterhin als verstecktes Form (`is-hidden`) für Auto-Logout.
- **NEXT:**
  - **T-045 (Teil 2):** `views/terminal/urlaub_beantragen.php` und `views/terminal/logout.php` auf die Partial umstellen (inkl. `<noscript>`-Fallback in `logout.php`).

## P-2026-01-01-47
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (`gesammtesprojektaktuellzip.zip`): `c9a19cec8cad4d475245ca642fb0f30357d019bc4bbd5a53eeae9f07060997e6`
  - Master-Prompt (`master_prompt_zeiterfassung_v5.md` Upload): `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (`DEV_PROMPT_HISTORY.md` Upload): `72248cf79158bb8cb3b547d7aa60a5947e7fce5a9e8a410dbbbcb48070041036`
  - SQL-Dump (`zeiterfassung_aktuell.sql` Upload): `1d7952ad8394583028a902ee6d543a07b9db9d4098d6b52d14e051e03469dd3a`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Rechteverwaltung war noch nicht umgesetzt → keine Doppelarbeit.
- **DATEIEN (max. 3):**
  - `services/AuthService.php`
  - `sql/01_initial_schema.sql`
  - `sql/03_migration_rechteverwaltung.sql`
- **DONE:**
  - Rollenbasierte Rechteverwaltung eingeführt:
    - Neue Tabellen `recht` + `rolle_hat_recht` (inkl. FK/Index).
    - `AuthService::hatRecht($code)` + Session-Cache (Logout/Login invalidiert Cache).
    - Migration 03 seeded Basis-Rechte + weist (falls Rollen existieren) Standardrechte zu (`Chef`, `Personalbüro`).
- **NEXT:**
  - **T-048:** Backend-UI/Flow um Rechte zu Rollen zuweisen (Checkboxen im Rollenformular + Save nach `rolle_hat_recht`).

## P-2026-01-01-48
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (`gesammtesprojektaktuellzip.zip`): `0baaa24f866ba06a66493254edf14174cb072ac7cb6b4ede18477df8f61bc7c6`
  - Master-Prompt (`master_prompt_zeiterfassung_v5.md` Upload): `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (`DEV_PROMPT_HISTORY.md` Upload): `a11868a31de8fa07d51845c3e8eba26c2eaab3220b56b9ef253f41650aa2d190`
  - SQL-Dump (`zeiterfassung_aktuell.sql` Upload): `cc0f007bca69cc09c3536f5792b19922298225537d3c1d6efda74beb5af455cc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-048 war offen und noch nicht erledigt → keine Doppelarbeit.
- **DATEIEN (max. 3):**
  - `controller/RollenAdminController.php`
  - `modelle/RolleModel.php`
  - `views/rolle/formular.php`
- **DONE:**
  - Rollen-Formular erweitert: Rechte aus `recht` laden, bestehende Zuweisungen aus `rolle_hat_recht` vorselektieren.
  - Speichern-Flow: Rolle + Rechte werden atomar gespeichert (Transaktion), inkl. CSRF-Check.
  - Rechte-Cache in Session nach Save invalidiert; Zugriff bevorzugt `ROLLEN_RECHTE_VERWALTEN` (Legacy-Fallback: Chef/Personalbüro).
- **NEXT:**
  - **T-050:** Monatsreport Rechte prüfen (Chef/Personal für andere Mitarbeiter).

## P-2026-01-01-49
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (`gesammtesprojektaktuellzip.zip`): `0baaa24f866ba06a66493254edf14174cb072ac7cb6b4ede18477df8f61bc7c6`
  - Master-Prompt (`docs/master_prompt_zeiterfassung_v5.md` im Projekt-ZIP): `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (`docs/DEV_PROMPT_HISTORY.md` im Projekt-ZIP): `9f7c910b6379dba9306d7b0ddcd0b43f08925d0c122434efddbaaa7d43b2f192`
  - SQL-Schema (`sql/01_initial_schema.sql` im Projekt-ZIP): `8b2842bf739d566d30ee5de3fc5544e3b7191f95581ad7a2c8d0c61dabf9d11e`
  - SQL-Dump (`zeiterfassung_aktuell.sql` Upload): `cc0f007bca69cc09c3536f5792b19922298225537d3c1d6efda74beb5af455cc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-049 war offen und noch nicht erledigt → keine Doppelarbeit.
- **DATEIEN (max. 3):**
  - `controller/UrlaubController.php`
  - `views/layout/header.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Urlaub-Genehmigung konsequent Rechte-basiert umgesetzt:
    - `URLAUB_GENEHMIGEN_ALLE`: sieht/entscheidet alle offenen Anträge.
    - `URLAUB_GENEHMIGEN`: sieht/entscheidet nur Mitarbeiter im eigenen Genehmiger-Bereich (`mitarbeiter_genehmiger`).
    - `URLAUB_GENEHMIGEN_SELF`: eigene Anträge werden in der Liste angezeigt und dürfen entschieden werden.
  - POST-Flow serverseitig hart geprüft (Self/Alle/Bereich) + Update-Statement blockiert Self-Approval nur, wenn SELF-Recht fehlt.
  - Menüpunkt „Urlaub genehmigen“ im Header auf Rechte umgestellt (Bereich-Recht zusätzlich nur bei vorhandener Genehmiger-Zuordnung sichtbar).
- **NEXT:**
  - **T-050:** Monatsreport Rechte prüfen (Chef/Personal für andere Mitarbeiter).


## P-2026-01-01-50
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (`150511_gesammt.zip`): `6fde697fba905a75dcdd67df15e211578e102cac108c7076cd26038f2f6c30a1`
  - Master-Prompt (`docs/master_prompt_zeiterfassung_v5.md` im Projekt-ZIP): `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (`docs/DEV_PROMPT_HISTORY.md` im Projekt-ZIP): `a2cdde3c552b10a32256fa5bdda8b1e5ca4df98d0ded8deff3c2b19dde67f5fc`
  - SQL-Schema (`sql/01_initial_schema.sql` im Projekt-ZIP): `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL-Dump (`sql/zeiterfassung_aktuell.sql` im Projekt-ZIP): `cc0f007bca69cc09c3536f5792b19922298225537d3c1d6efda74beb5af455cc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: T-050 war offen und noch nicht erledigt → keine Doppelarbeit.
- **DATEIEN (max. 3):**
  - `controller/ReportController.php`
  - `public/index.php`
  - `sql/04_migration_report_monat_rechte.sql`
- **DONE:**
  - Monatsreport-Rechte umgesetzt (fein + Legacy-Fallback):
    - `REPORT_MONAT_VIEW_ALL` (Fallback `REPORTS_ANSEHEN_ALLE`): Monatsübersicht + PDF für beliebige Mitarbeiter via `?mitarbeiter_id=...` / `?mid=...`.
    - `REPORT_MONAT_EXPORT_ALL` (Fallback `REPORTS_ANSEHEN_ALLE`): Sammel-Export als ZIP via `?seite=report_monat_export_all&jahr=YYYY&monat=MM`.
  - Router erweitert um Backend-Route `report_monat_export_all` (geschützt).
  - Neue Migration `sql/04_migration_report_monat_rechte.sql` seeded die beiden neuen Rechte-Codes idempotent.
- **NEXT:**
  - **T-051:** Monatsreport-UI erweitern (Mitarbeiter-Auswahl + Export-Link/Button), nur bei passenden Rechten sichtbar.

## P-2026-01-01-51
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (`151631_gesammt.zip`): `876b6dcb4edd2540a16db8f5f330104af494204226a85fe35adc6921f7c1a408`
  - Master-Prompt (`docs/master_prompt_zeiterfassung_v5.md` im Projekt-ZIP): `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (`docs/DEV_PROMPT_HISTORY.md` im Projekt-ZIP): `5c03efba08eec118d3923934af0cbd4ecdf25dc09549df188a52ecd76f628de3`
  - SQL-Schema (`sql/01_initial_schema.sql` im Projekt-ZIP): `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Patch P-2026-01-01-50 hat Rechte + Routen geliefert; in `views/report/monatsuebersicht.php` fehlten noch Mitarbeiter-Auswahl + Sammel-Export-Link → nicht DONE, keine Doppelarbeit.
- **DATEIEN (max. 3):**
  - `controller/ReportController.php`
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport-UI erweitert:
    - Filter-Formular für Jahr/Monat.
    - (Nur mit `REPORT_MONAT_VIEW_ALL` / Legacy `REPORTS_ANSEHEN_ALLE`) Mitarbeiter-Auswahl per Dropdown (aktive Mitarbeiter; ausgewählter inaktiver wird nachgeladen).
    - PDF-Link trägt `mitarbeiter_id`, damit Chef/Personal direkt das passende PDF bekommt.
    - Sammel-Export-Link (ZIP) wird nur gezeigt, wenn `REPORT_MONAT_EXPORT_ALL` (oder Legacy) vorhanden ist.
- **NEXT:**
  - **T-051:** Zeitbuchungen editieren (self/alle) inkl. Audit: wer, wann, alte/neue Werte, Begründung.
  - Danach: **T-045 (Teil 2)** Terminal Logout-Form-Partial in `urlaub_beantragen.php`/`logout.php`.


## P-2026-01-01-52
**EINGELESEN (SHA256):**
- Projekt-ZIP: `152252_gesammt.zip` → `1e1da02fb12151cb71b5638eb835fff1616472d739f3ede0c937e30ec6e914aa`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` → `fb1f5586c3da9ec227ccee3203c06f9aaffa01ab676ddfdcad2c91b6fc7a5004`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-051 war offen, in „Erledigte Tasks“ nicht vorhanden → Implementierung ist nicht doppelt.
- LOG stichprobenartig geprüft: keine frühere P-ID setzt T-051 vollständig um.

**DATEIEN (max. 3):**
- `controller/ZeitController.php`
- `views/zeit/tagesansicht.php`
- `sql/05_migration_zeitbuchung_edit_rechte.sql`

**DONE:**
- Zeitbuchungs-Korrektur Rechte-basiert umgesetzt:
  - `ZEITBUCHUNG_EDIT_SELF`: eigene Buchungen bearbeiten.
  - `ZEITBUCHUNG_EDIT_ALL`: alle Mitarbeiter bearbeiten (inkl. Mitarbeiter-Dropdown).
  - Legacy-Fallback: Chef/Personalbüro werden wie `EDIT_ALL` behandelt.
- Pflichtfeld „Begründung“ für Add/Update/Delete.
- Audit-Log je Änderung in `system_log` (Kategorie: `zeitbuchung_audit`) inkl. wer/wann + alt/neu + Begründung.

**NEXT:**
- **T-045 (Teil 2):** Terminal: Logout-Formular-Partial in `views/terminal/urlaub_beantragen.php` und `views/terminal/logout.php` nutzen.


## P-2026-01-01-53
**EINGELESEN (SHA256):**
- Projekt-ZIP: `153836_gesammt.zip` → `be54376a7e57a39d95bf9d33305cf10df324eb0362c7fa7afdaf6002d428ccd7`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` → `667495060edc5fde800bd1558018d7b14c1574a55ff8a9fed4a38e5fec4e96ef`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`

**DUPLICATE-CHECK:**
- SNAPSHOT/LOG geprüft: T-045 (Teil 2) war als offen gelistet.
- Code-Check: `views/terminal/urlaub_beantragen.php` und `views/terminal/logout.php` nutzen das Logout-Form-Partial bereits → keine Doppel-Umstellung.
- Stattdessen: Partial defensiv gehärtet (CSRF-Fallback), und SNAPSHOT/Erledigte Tasks aktualisiert.

**DATEIEN (max. 3):**
- `views/terminal/_logout_form.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- **T-045 (Teil 2)** als erledigt dokumentiert (beide Views nutzen das Partial).
- `_logout_form.php` bekommt CSRF-Fallback (Session/Auto-Generate), damit Logout auch bei fehlendem `$csrfToken` nicht „kaputt“ ist.

**NEXT:**
- **T-052:** Terminal: Inline-`confirm()` beim Urlaub-Storno entfernen (kein Inline-JS) → `data-confirm` + Bindung in `public/js/terminal-autologout.js`.


## P-2026-01-01-54
- ZIP: `P-2026-01-01-54_terminal-confirm-storno.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `154559_gesammt.zip` → `5404355d08f231b9a744376be84744c10b42118c22aad5b1d0a478de01203457`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` → `168c419d35c73df84216d27b9c12fc831ed910f72242ee0b8a572990bafee1c8`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-052 war offen und nicht als DONE markiert.
- Code-Check: In `views/terminal/start.php` und `views/terminal/urlaub_beantragen.php` gab es noch Inline-`confirm()` im `onsubmit` (2 Stellen) → nicht umgesetzt.

**DATEIEN (max. 3):**
- `public/js/terminal-autologout.js`
- `views/terminal/start.php`
- `views/terminal/urlaub_beantragen.php`

**DONE:**
- Inline-`confirm()` beim Urlaub-Storno entfernt (kein Inline-JS mehr in den Terminal-Views).
- Stattdessen `data-confirm="..."` am Storno-Formular gesetzt.
- Zentrale Confirm-Logik in `public/js/terminal-autologout.js`: `submit`-Listener prüft `data-confirm` am Form oder am Submit-Button und fragt per `window.confirm()` nach.

**NEXT:**
- **T-053:** Terminal: Urlaubsanträge-Liste als Partial extrahieren (Duplikate Start/Urlaub reduzieren).


## P-2026-01-01-55
- ZIP: `P-2026-01-01-55_backend-admin-rechte-teil1.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `155148_gesammt.zip` → `8dd7edae2d6cb142251a0a8991906e3505f2397b9996e15c86c4e53ebaeb9507`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` → `daf438347fb9b3cc7e9013bb6d7655a1101db9429ea967e1232033f40dbbd7c9`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: Admin-Controller waren noch Rollen-basiert (kein Rechte-Check), Header-Menü ebenfalls → nicht umgesetzt.
- Code-Check: `MitarbeiterAdminController`/`AbteilungAdminController` hatten nur Rollenprüfung → Umstellung auf Rechte möglich.

**DATEIEN (max. 3):**
- `views/layout/header.php`
- `controller/MitarbeiterAdminController.php`
- `controller/AbteilungAdminController.php`

**DONE:**
- Backend-Menü (Header): Admin-Menüpunkte bevorzugen nun Rechte-Codes (z. B. `MITARBEITER_VERWALTEN`, `ABTEILUNG_VERWALTEN`, …) mit Legacy-Fallback auf Rollen (`Chef`/`Personalbüro`).
- Admin-Controller: Zugriff prüft primär Rechte (`MITARBEITER_VERWALTEN`, `ABTEILUNG_VERWALTEN`), fallback Rollen.

**NEXT:**
- **T-054 (Teil 2):** Restliche Admin-Controller auf Rechte umstellen (Maschinen/Feiertage/Betriebsferien/Queue/Rundungsregeln/Konfiguration/Urlaub-Kontingent) + fehlende Rechte-Codes als SQL-Migration seeden.


## P-2026-01-01-56
- ZIP: `P-2026-01-01-56_backend-admin-rechte-teil2a.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `160452_gesammt.zip` → `c368af23fcf3da6d312ba1ad4a8725b31374ac2c523ead517d2fb7f4d0777594`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` → `229c700ffe951ed1e7cc3cf85795ae68cba255d59ae59d5332b9f98e59144fb5`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: Backend-Admin-Rechte noch nicht komplett umgesetzt (T-054 Teil 2 offen).
- Code-Check: `MaschineAdminController` und `QueueController` waren noch rein Rollen-basiert; `FeiertagController` war bereits Rechte-basiert → keine Doppelarbeit am Feiertag.
- SQL-Check: Rechte-Codes `ZEIT_RUNDUNGSREGELN_VERWALTEN` / `KONFIGURATION_VERWALTEN` / `URLAUB_KONTINGENT_VERWALTEN` fehlten in den Migrationen.

**DATEIEN (max. 3):**
- `controller/MaschineAdminController.php`
- `controller/QueueController.php`
- `sql/06_migration_backend_admin_rechte.sql`

**DONE:**
- `MaschineAdminController`: Zugriff prüft primär `MASCHINEN_VERWALTEN`, Legacy-Fallback Rollen (Chef/Personalbüro).
- `QueueController`: Zugriff prüft primär `QUEUE_VERWALTEN`, Legacy-Fallback Rollen (Chef/Personalbüro).
- Migration 06: fehlende Admin-Rechte für Rundungsregeln/Konfiguration/Urlaub-Kontingent ergänzt und standardmäßig an Chef/Personalbüro zugewiesen (idempotent).

**NEXT:**
- **T-054 (Teil 2b):** Restliche Admin-Controller auf Rechte umstellen: `BetriebsferienAdminController`, `ZeitRundungsregelAdminController`, `KonfigurationController`, `UrlaubKontingentAdminController` (jeweils primär Recht, Legacy-Fallback Rollen).



## P-2026-01-01-57
- ZIP: `P-2026-01-01-57_backend-admin-rechte-teil2b.zip`
- **EINGELESEN (SHA256):**
  - `161612_gesammt.zip`: `12fa8e3e0ed55729f1f054ff2b21285fc40a523bf29fd7020c41080290822283`
  - `docs/master_prompt_zeiterfassung_v5.md`: `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - `docs/DEV_PROMPT_HISTORY.md`: `b119bd6d42471429162418c35a2bc709719dfab32ba7ade77e506607048a9419`
  - `sql/01_initial_schema.sql`: `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - T-054 ist im SNAPSHOT als **offen** markiert; im LOG zuletzt **Teil 2a** DONE. Teil 2b.1 (Konfiguration + Rundungsregeln) war noch nicht umgesetzt → keine Doppelarbeit.
- **DATEIEN (max. 3):**
  - `controller/ZeitRundungsregelAdminController.php`
  - `controller/KonfigurationController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- DONE: Zugriffsschutz in den Admin-Controllern **Konfiguration** und **Zeit-Rundungsregeln** auf Rechteprüfung umgestellt (`KONFIGURATION_VERWALTEN`, `ZEIT_RUNDUNGSREGELN_VERWALTEN`) inkl. Legacy-Fallback Chef/Personalbüro.
- NEXT: T-054 (Teil 2b – Fortsetzung): `BetriebsferienAdminController` (`BETRIEBSFERIEN_VERWALTEN`) + `UrlaubKontingentAdminController` (`URLAUB_KONTINGENT_VERWALTEN`) auf Rechteprüfung umstellen.
## P-2026-01-01-58
- ZIP: `P-2026-01-01-58_backend-admin-rechte-teil2c.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `162318_gesammt.zip` → `bd5a8830148a866911c9fad42764964157ef47f56d3c2793680dabe0920a4595`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `3a81f50fe047559998f338e02ac55445db5807ff8302025409a4109d7aa12116`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`
- `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`

**DUPLICATE-CHECK:**
- Im SNAPSHOT war T-054 Teil 2b (Betriebsferien + Urlaub-Kontingent) als offen markiert → Umsetzung ist keine Doppelarbeit.

**DATEIEN (max. 3):**
- `controller/BetriebsferienAdminController.php`
- `controller/UrlaubKontingentAdminController.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Zugriffsschutz in `BetriebsferienAdminController` ist jetzt primär Rechte-basiert (`BETRIEBSFERIEN_VERWALTEN`) mit Legacy-Fallback auf Rollen (Chef/Personalbüro).
- Zugriffsschutz in `UrlaubKontingentAdminController` ist jetzt primär Rechte-basiert (`URLAUB_KONTINGENT_VERWALTEN`) mit Legacy-Fallback auf Rollen (Chef/Personalbüro).

**NEXT:**
- T-054 (Teil 2b – Fortsetzung): `FeiertagController` (`FEIERTAGE_VERWALTEN`) + `TerminalAdminController` (`TERMINAL_VERWALTEN`) auf Rechteprüfung umstellen.


## P-2026-01-01-59
- ZIP: `P-2026-01-01-59_backend-feiertage-terminal-rechte.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `163130_gesammt.zip` → `dc52b5bbef75304fb6efc6187112dff76d10750d50ef2c82b4bec38dc300e387`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `b0fa060d119f8a8ca18b989f5335350a32ea3302a1a139766e738dcf2d1897e3`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-054 (Teil 2b – Fortsetzung) war offen (Feiertag + Terminalverwaltung Rechte).
- Code-Check:
  - `FeiertagController` war noch **rein rollenbasiert** (keine Prüfung auf `FEIERTAGE_VERWALTEN`).
  - `TerminalAdminController` prüfte nur „angemeldet“ und hatte **keine** Rechteprüfung.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `controller/FeiertagController.php`
- `controller/TerminalAdminController.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- `FeiertagController`: Zugriff jetzt primär Rechte-basiert (`FEIERTAGE_VERWALTEN`) mit Legacy-Fallback Chef/Personalbüro.
- `TerminalAdminController`: Zugriff jetzt primär Rechte-basiert (`TERMINAL_VERWALTEN`) mit Legacy-Fallback Chef/Personalbüro.
- SNAPSHOT aktualisiert: Routing-Liste ergänzt (Konfiguration/Urlaub-Kontingent), T-054 als vollständig DONE markiert; neues Backend-Next (T-055) ergänzt.

**NEXT:**
- **T-055:** Terminalverwaltung im Backend sauber anbinden (Route + Menüpunkt + erste View statt Platzhalter).


## P-2026-01-01-60
- ZIP: `P-2026-01-01-60_backend-terminalverwaltung-route-view.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `163821_gesammt.zip` → `8ee69ba304706dbb6ece704da6bfcc5b8caf33194c28263740e6f756c7b0998e`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `151837bf0e527eab171e81429eddb88a3081f00289c2c402ab17bf70e269fd08`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-055 war offen (Terminalverwaltung im Backend: Route + Menüpunkt + View).
- Code-Check:
  - `public/index.php` hatte **keine** Route `terminal_admin`.
  - `TerminalAdminController::index()` gab nur ein kleines `echo`-Listing aus (Platzhalter, ohne Backend-Layout).
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `public/index.php`
- `controller/TerminalAdminController.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Backend-Route `?seite=terminal_admin` ergänzt (geschützt wie andere Admin-Seiten).
- `TerminalAdminController::index()` nutzt jetzt das Backend-Layout (Header/Footer) und zeigt eine saubere Liste der Terminals (inkl. Modus/Offline-Flags/Timeout/Aktiv).
- SNAPSHOT aktualisiert (Routing ergänzt; T-055 auf „Menüpunkt fehlt noch“ konkretisiert).

**NEXT:**
- **T-055 abschließen:** Menüpunkt „Terminals“ in `views/layout/header.php` ergänzen (sichtbar nur mit `TERMINAL_VERWALTEN` bzw. Legacy-Chef/Personalbüro).
## P-2026-01-01-61
- ZIP: `P-2026-01-01-61_backend-terminal-menuepunkt.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `164541_gesammt.zip` → `c8c56ca2711e2879970a88c0eba5eebde530edf6b422bad26608efb388ac5e4c`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `fc7ce80a16b3910f7bcf11237cc9909858d816bc76f3de7ec4626b9cdfe9e671`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-055 war offen (Menüpunkt „Terminals“ im Backend-Header fehlt).
- Code-Check:
  - `views/layout/header.php` hatte **keinen** Menüpunkt für `?seite=terminal_admin`.
  - Es existierte **kein** `$hatTerminalAdminRecht` Flag im Header (nur Controller/Route waren bereits vorhanden).
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `views/layout/header.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Backend-Header: Menüpunkt **„Terminals“** ergänzt (sichtbar nur mit Recht `TERMINAL_VERWALTEN` bzw. Legacy-Fallback Chef/Personalbüro).
- Header-Flags erweitert: `$hatTerminalAdminRecht` wird analog zu anderen Admin-Menüpunkten gesetzt.
- SNAPSHOT aktualisiert: T-055 als DONE, Next auf T-053 gesetzt.

**NEXT:**
- **T-053:** Terminal: Urlaubsanträge-Liste (Start + Urlaub) als gemeinsames Partial extrahieren (Duplikate reduzieren).

## P-2026-01-01-62
- ZIP: `P-2026-01-01-62_terminal-urlaubsantraege-partial-teil1.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `165017_gesammt.zip` → `be8c8c69383199d8494b5ce7c980437815ff6384d008f57ab97aafdcccca1df1`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `0d2b5d4dc1076d576ebe2a7faff14bf2d47e72e7d3956760bb2ee6ac670cf760`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-053 war offen (Urlaubsanträge-Liste aus Terminal-Views als Partial extrahieren).
- Code-Check:
  - In `views/terminal/start.php` und `views/terminal/urlaub_beantragen.php` existierte derselbe `<details class="urlaub-liste">` Block.
  - Es gab noch **kein** gemeinsames Partial für diese Liste.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `views/terminal/_urlaub_antraege_liste.php`
- `views/terminal/urlaub_beantragen.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Neues Partial `views/terminal/_urlaub_antraege_liste.php` eingeführt (Liste der letzten Urlaubsanträge inkl. Storno-Button).
- `views/terminal/urlaub_beantragen.php` nutzt jetzt das Partial statt dupliziertem Block.
- SNAPSHOT aktualisiert: T-053 in Teil 1/2 gesplittet (wegen max. 3 Dateien pro Patch).

**NEXT:**
- **T-053 (Teil 2):** `views/terminal/start.php` auf `views/terminal/_urlaub_antraege_liste.php` umstellen und den duplizierten Block entfernen.


## P-2026-01-01-63
- ZIP: `P-2026-01-01-63_terminal-urlaubsantraege-partial-teil2.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `165928_gesammt.zip` → `3bff5947fbd1580bf700d5c469d33de13fbbf4243dbfd52ba3515ef113f10183`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `38cdf58359707ad59936ec8f5a75b5a0d1fc60893577394de8a5bec935eeb2a4`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-053 (Teil 2) war offen.
- Code-Check:
  - `views/terminal/start.php` enthielt weiterhin den duplizierten `<details class="urlaub-liste">` Block.
  - Das Partial `views/terminal/_urlaub_antraege_liste.php` existierte bereits (Teil 1) und wird nun wiederverwendet.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `views/terminal/start.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- `views/terminal/start.php` nutzt jetzt `views/terminal/_urlaub_antraege_liste.php` (Duplikat entfernt).
- T-053 ist damit komplett abgeschlossen.

**NEXT:**
- **T-056:** Backend: Terminalverwaltung (CRUD) ausbauen – Start mit Bearbeiten/Anlegen + Save-Route.


## P-2026-01-01-64
- ZIP: `P-2026-01-01-64_backend-terminalverwaltung-crud-form.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `170335_gesammt.zip` → `56de4a119589046346781c1cf4367e68760c9694b18650ef28dfb4264a166f62`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `4c2c0edd095bcb2d944a4f510778c263b238305f1f03c1a36ab9d7c5b73449b2`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-056 war offen.
- Code-Check:
  - `public/index.php` hatte **keine** Routes `terminal_admin_bearbeiten` / `terminal_admin_speichern`.
  - `controller/TerminalAdminController.php` hatte nur die Liste, **kein** Bearbeiten/Anlegen-Formular und **keine** Save-Verarbeitung.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `public/index.php`
- `controller/TerminalAdminController.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Backend-Terminalverwaltung zu echtem CRUD ausgebaut: **Anlegen/Bearbeiten** + **Speichern** (POST).
- Formularfelder: Name, Standort, Abteilung, Modus, Auto-Logout Timeout, Offline-Flags, Aktiv.
- Save-Flow mit **CSRF** abgesichert, Fehlerfälle rendern Formular mit Meldung.
- Liste erweitert: „Neues Terminal anlegen“ + „Bearbeiten“-Link pro Terminal.
- Routing ergänzt: `terminal_admin_bearbeiten` / `terminal_admin_speichern`.
- SNAPSHOT aktualisiert: T-056 DONE, Next auf T-057 gesetzt.

**NEXT:**
- **T-057 (optional):** In der Terminal-Liste Quick-Toggles (Aktiv + Offline-Flags) als POST+CSRF ergänzen.


## P-2026-01-01-65
- ZIP: `P-2026-01-01-65_backend-terminalverwaltung-quicktoggles.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `171425_gesammt.zip` → `92cce59ac598accd9d2305b88f39b7485149b6c2f39b15689f652d85013a7f24`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `9b508bde6cd6673663ebd8a541e71e7ed0dc053979e6c6ae04014b89ece3dc0f`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-057 war offen (Quick-Toggles in Terminal-Liste).
- Code-Check:
  - `controller/TerminalAdminController.php` listete Flags nur als Text (Ja/Nein), ohne Toggle-POSTs.
  - `public/index.php` hatte **keine** Route `terminal_admin_toggle`.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `public/index.php`
- `controller/TerminalAdminController.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Backend-Terminalliste: Quick-Toggles für **Aktiv**, **Offline K/G** und **Offline Aufträge** als **POST+CSRF** ergänzt.
- Neue Route `terminal_admin_toggle` + Controller-Methode `toggleFlag()` (Whitelist der erlaubten Spalten).
- Flash-Meldungen (OK/Fehler) für Toggle-Aktionen in der Listenansicht.
- SNAPSHOT aktualisiert: T-057 DONE, Next auf T-058.

**NEXT:**
- **T-058:** Offline-Queue Admin: Aktionen CSRF-sichern + Eintrags-Details (SQL/Meta) in der Liste.


## P-2026-01-01-66
- ZIP: `P-2026-01-01-66_backend-queueadmin-csrf-details.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `172440_gesammt.zip` → `df46ddfa899cf6af46f490139ce86c865062ec6c4774716b1fbd23bcf9735244`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `dd7d0e1507397beca862798fc8286774c0d551bb2403d60557c4e7c476d14473`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-058 war offen (nicht als DONE markiert).
- Code-Check:
  - `controller/QueueController.php` akzeptierte Admin-POST (Löschen) ohne CSRF-Prüfung.
  - `views/queue/liste.php` zeigte nur gekürzten SQL/Fehlertext, keine Detailansicht.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `controller/QueueController.php`
- `views/queue/liste.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Offline-Queue Admin: Lösch-/Ignorier-Aktion ist jetzt **POST + CSRF** (Mismatch → Meldung).
- Offline-Queue Admin: Pro Eintrag sind **Details** (Meta-Felder + voller SQL-Befehl + volle Fehlermeldung) in der Liste aufklappbar.
- SNAPSHOT aktualisiert: T-058 DONE, Next auf T-059.

**NEXT:**
- **T-059:** Offline-Queue Admin: Button „Queue verarbeiten“ (manuell anstoßen) + optional Retry eines einzelnen Eintrags.


## P-2026-01-01-67
- ZIP: `P-2026-01-01-67_queue-verarbeiten-retry.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `173541_gesammt.zip` → `15ce162a1d142f0dc4e4309451c641de27616af6d0e6d368b8a21bbf9a52e79d`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `79361353b0135e4f9bba2ecadfd7052375c575a883cfc8a4d29e03d31e078dbb`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-059 war offen (nicht als DONE markiert).
- Code-Check:
  - `views/queue/liste.php`: Kein „Queue verarbeiten“-Button; bei Status `fehler` nur „Ignorieren/Löschen“.
  - `controller/QueueController.php`: Keine POST-Aktion zum Anstoßen der Verarbeitung; kein Retry-Flow.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `controller/QueueController.php`
- `views/queue/liste.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Offline-Queue Admin: Button „Queue verarbeiten“ als **POST + CSRF** (arbeitet alle offenen Einträge ab).
- Offline-Queue Admin: Optionaler **Retry** für einzelne Fehler-Einträge (führt SQL gegen Haupt-DB aus; markiert als verarbeitet oder aktualisiert Fehler).
- User-Feedback per `meldung=`: `queue_verarbeitet`, `retry_ok`, `retry_fehler`, `hauptdb_offline`, `eintrag_nicht_gefunden`, `csrf_ungueltig`.
- SNAPSHOT aktualisiert: T-059 DONE, Next auf T-060.

**NEXT:**
- **T-060:** Offline-Queue Admin: Beim „Queue verarbeiten“ ein aussagekräftiges Ergebnis anzeigen (z. B. Anzahl verarbeitet + erster Fehler/ID), damit Admin direkt Feedback bekommt.


## P-2026-01-01-68
- ZIP: `P-2026-01-01-68_queue-verarbeiten-report.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `174407_gesammt.zip` → `7499fe26dfce8b7ec8bd820411e5138ab110b5b0b7513ce422f945e052ed0e3e`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `b99649e13c5db1feca61917fee801f0369811327584018b172b2947d0e9a5fb6`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-060 war offen (nicht als DONE markiert).
- Code-Check:
  - `controller/QueueController.php`: `queue_verarbeitet` war nur ein generisches „angestoßen“ ohne Zahlen/Fehler-ID.
  - `views/queue/liste.php`: Meldungsbox hatte keine Detail-Ausgabe nach der Verarbeitung.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `controller/QueueController.php`
- `views/queue/liste.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Offline-Queue Admin: Beim „Queue verarbeiten“ wird jetzt ein **Ergebnisreport** angezeigt (versucht/OK/neu Fehler/offen verbleibend + Dauer).
- Bei neuem Fehler: Meldung zeigt zusätzlich eine **Fehler-ID** (inkl. Meta-Aktion + gekürzte Fehlermeldung) als direktes Debug-Entry.
- Report wird als **Flash** in Session gespeichert, damit Reload nicht erneut verarbeitet und trotzdem Zahlen angezeigt werden.
- SNAPSHOT aktualisiert: T-060 DONE, Next auf T-061.

**NEXT:**
- **T-061:** Dashboard/Admin-Übersicht: Offline-Queue Status-Kachel (Anzahl offen + Anzahl fehler) + Link auf `queue_admin`.

## P-2026-01-01-69
- ZIP: `P-2026-01-01-69_dashboard-queue-kachel.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `184001_gesammt.zip` → `50ebf8b2c6f06ea397ae5508210732d78bc1bfaf4f263987ee66f7f44bb64f22`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `2a133fd6598409680cee0bfa5f61ae74e25a203bdfee4a20e26cad3086375999`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-061 war offen (nicht als DONE markiert).
- Code-Check:
  - `views/dashboard/index.php`: keine Systemstatus-Kachel, nur Links.
  - `controller/DashboardController.php`: kein Laden von Queue-Statusdaten.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `services/QueueService.php`
- `controller/DashboardController.php`
- `views/dashboard/index.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Dashboard: Offline-Queue Status-Kachel für Admins (Recht `QUEUE_VERWALTEN` oder Legacy-Rolle Chef/Personalbüro).
- Anzeige: Anzahl `offen` + `fehler` und Link auf `?seite=queue_admin` (inkl. Quelle Offline-/Haupt-DB + letzte Ausführung, wenn verfügbar).
- SNAPSHOT aktualisiert: T-061 DONE, Next auf T-062.

**NEXT:**
- **T-062:** Dashboard/Admin-Übersicht – weitere Systemstatus-Kacheln (optional): Hauptdatenbank erreichbar + Terminal/Queue Kurzchecks.

## P-2026-01-01-70
- ZIP: `P-2026-01-01-70_dashboard-status-kacheln.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `185446_gesammt.zip` → `13fa14ffca8b4565088cd2b1220fccea732984d990855a1d5d203f3b71945081`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `871f4a6d69c5122f31b6f868abd2f37f73cea2411ca4b6d66e1ba75ef2e2dde0`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: T-062 war offen (nicht als DONE markiert).
- Code-Check:
  - `controller/DashboardController.php`: kein DB/Terminal-Status.
  - `views/dashboard/index.php`: nur Queue-Kachel (bisher), keine DB/Terminal-Kacheln.
  → Umsetzung ist **keine Doppelarbeit**.

**DATEIEN (max. 3):**
- `controller/DashboardController.php`
- `views/dashboard/index.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Dashboard: zusätzliche Systemstatus-Kacheln für Admins (Hauptdatenbank erreichbar + Offline-DB Status + Terminal-Kurzcheck).
- Queue-Kachel bleibt im Systemstatus-Block; Darstellung ist jetzt als flexible Kartenansicht umgesetzt.
- SNAPSHOT aktualisiert: T-062 DONE, Next auf T-063 (optional).

**NEXT:**
- **T-063 (optional):** Dashboard/Admin-Übersicht – Admin-Schnelllinks/Kacheln (Mitarbeiter, Rollen, Terminals, Queue) für schnellere Navigation.
## P-2026-01-01-71
- ZIP: `P-2026-01-01-71_dashboard-admin-schnellzugriff.zip`

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `205316_gesammt.zip` = `7547b27a20c56ee129f549a455ffd669df352f8d52fb78cbeaa0a74d5dd3c7cb`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `f4292d234f6c5735793cbe20c82987c53efc7476fc0b1faf8cbd4902e18c0f8d`
  - SQL-Schema: `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: T-063 war **OFFEN** und nicht in „Erledigte Tasks“.
  - LOG geprüft: kein Eintrag, der T-063 bereits als DONE markiert.
- **DATEIEN (max. 3):**
  - `views/dashboard/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** T-063 umgesetzt: Dashboard zeigt für sichtbare Admin-Rechte eine Schnellzugriff-Kachel-Leiste (Mitarbeiter/Rollen/Terminals/Queue usw.).
- **NEXT:** T-064 Bugfix: `urlaub_kontingent_admin` Internal Server Error debuggen und beheben.

## P-2026-01-01-72
- ZIP: `P-2026-01-01-72_backend-parseerror-fix-urlaub-betriebsferien.zip`

**EINGELESEN (SHA256):**
- Projekt-ZIP: `210127_gesammt.zip` → `2db7c49093ab8694d134289b24064dd1b7552ae8754d4f47c67ede14a877010c`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v5.md` → `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
- DEV-Prompt: `docs/DEV_PROMPT_HISTORY.md` → `13ecf7b7b871cd7de3ab59c949cdf9a1637a7fb1d10781c1fdff7c0c63cbff2f`
- SQL-Schema: `sql/01_initial_schema.sql` → `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- SQL-Dump: `sql/zeiterfassung_aktuell.sql` → `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`

**DUPLICATE-CHECK:**
- SNAPSHOT geprüft: B-012/T-064 war **OFFEN**.
- Code-Check: In `controller/UrlaubKontingentAdminController.php` lag nach `pruefeZugriff()` ein doppelter Block (`foreach ...`) außerhalb einer Funktion → **PHP Parse Error** → 500.
- Preflight `php -l controller/*.php` zeigte zusätzlich dasselbe Muster in `controller/BetriebsferienAdminController.php` → ebenfalls potentieller 500.
→ Umsetzung ist **keine Doppelarbeit** und erhöht Backend-Stabilität.

**DATEIEN (max. 3):**
- `controller/UrlaubKontingentAdminController.php`
- `controller/BetriebsferienAdminController.php`
- `docs/DEV_PROMPT_HISTORY.md`

**DONE:**
- Bugfix: Parse-Error in `urlaub_kontingent_admin` behoben → Seite lädt wieder.
- Zusätzlich: identisches Parse-Error-Muster im Betriebsferien-Admin entfernt.
- SNAPSHOT aktualisiert: B-012/T-064 DONE, Next auf T-065.

**NEXT:**
- **T-065:** Backend: CSRF für Admin-POSTs nachziehen (Konfiguration + Rundungsregeln speichern aktuell ohne CSRF).



## P-2026-01-02-01
- ZIP: `P-2026-01-02-01_csrf-konfig-rundungsregeln.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `211019_gesammt.zip` = `e2cc2a82fa00b71489532c232221746ae6e3f753ed4ebd5e3861f11912c885c7`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Upload, Stand vor Patch): `DEV_PROMPT_HISTORY.md` = `b563037242a8f4e0b1dc963d93866c7bc88089e6263e93287c817834a07bf0cf`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL (Upload): `zeiterfassung_aktuell.sql` = `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-065** war offen und ist weder in „Erledigte Tasks“ noch im LOG als DONE vorhanden.
- **DATEIEN (max. 3):**
  - `controller/KonfigurationController.php`
  - `controller/ZeitRundungsregelAdminController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- DONE: **T-065** umgesetzt: Admin-POSTs in Konfiguration + Rundungsregeln sind CSRF-geschützt (Token in Session, Validierung serverseitig). Rundungsregel-Formular bekommt das Token über Output-Injection (ohne View-Datei zu ändern), um das Patch-Limit einzuhalten.
- NEXT: **T-066** (PDF-Ausgabe wie Vorlage `vorlageausgabezeiten.pdf`).


## P-2026-01-02-02
- ZIP: `P-2026-01-02-02_pdf-arbeitszeitliste-vorlage.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `070429_gesammt.zip` = `3a939d5d0f326b3a3fbd6587e47e01e741621846ccb8b388fdb88e6a95626eb8`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0e46ef751d7415d91ba11bf9f0ec9c4daf806109e22e8ba32bf2fe27857b12e5`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL (im ZIP): `sql/zeiterfassung_aktuell.sql` = `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-066** war offen und ist weder in „Erledigte Tasks“ noch im LOG als DONE vorhanden.
- **DATEIEN (max. 3):**
  - `services/PDFService.php`
  - `services/ReportService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- DONE: **T-066** umgesetzt: Monatsreport-PDF als **Arbeitszeitliste** mit Tabellenraster, Kopfzeile (Mitarbeiter, Zeitraum, Druckdatum) und Summenblock – optisch an die Vorlage `vorlageausgabezeiten.pdf` angelehnt. Dafür liefert `ReportService` zusätzlich Abwesenheits-/Sonderstunden (Arzt/Krank/Feiertag/Kurzarbeit/Urlaub/Sonst) + Kennzeichen an die PDF-Ausgabe.
- NEXT: **T-067** (`zeit_rundungsregel_admin` Aktion `seed_defaults` von GET auf POST+CSRF umstellen).


## P-2026-01-02-03
- ZIP: `P-2026-01-02-03_rundungsregeln-seed-post-csrf.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `074709_gesammt.zip` = `0e4c8fb8e2ef69ef5a0ea393eb43d6e05e05435b6d2eabdb3a1246abc492f3a0`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7b92f95653e3252e084fceb6685fbf8dcf95bc2b0777898529d3a24d91743b8b`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL (aktuell, im ZIP): `sql/zeiterfassung_aktuell.sql` = `32c382e66d4ec5ed9152a678888d10e7d731d70688dcfd0d1eea09a6badc8037`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: **T-067** war **OFFEN** und ist weder in „Erledigte Tasks“ noch im LOG als DONE vorhanden.
- **DATEIEN (max. 3):**
  - `controller/ZeitRundungsregelAdminController.php`
  - `views/zeit_rundungsregel/liste.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** **T-067** umgesetzt: Aktion `seed_defaults` („Standardregeln anlegen“) ist jetzt **POST + CSRF** (GET wird abgefangen und auf einen Hinweis umgeleitet).
- **NEXT:** **T-068 (optional):** Rundungsregeln-Liste – Quick-Toggle „Aktiv“ direkt in der Tabelle (POST+CSRF).


## P-2026-01-02-04
- **EINGELESEN (SHA256):**
  - 075419_gesammt.zip: 1e441cd83b57b67d607b64ac6779dd7ccb0a815f7a8c57b9417a37c049f52fee
  - docs/master_prompt_zeiterfassung_v5.md: e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6
  - docs/DEV_PROMPT_HISTORY.md: 0c97b93db524dd3a3cb1ae0be44b3f52060a068d486339e0d2458206d9465525
  - sql/01_initial_schema.sql: 328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3
- **DUPLICATE-CHECK:**
  - SNAPSHOT: T-068 war offen (optional) → noch nicht DONE.
  - LOG: kein früherer DONE-Eintrag zu T-068 gefunden.
- **DATEIEN (max. 3):**
  - controller/ZeitRundungsregelAdminController.php
  - views/zeit_rundungsregel/liste.php
  - docs/DEV_PROMPT_HISTORY.md
- **DONE:**
  - T-068: Quick-Toggle „Aktiv“ direkt in der Rundungsregeln-Liste (POST+CSRF, ohne Formularwechsel).
- **NEXT:**
  - T-069: Smoke-Test der Kernflows und Bugfixes basierend auf Testergebnissen.


## P-2026-01-02-05
- ZIP: `P-2026-01-02-05_pdf-stream-newlines.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `080600_gesammt.zip` = `5091645794a9a428992e1c5f0287b74665d3cdc149a932b83535edea03b58802`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `6a439b9827789bea3fcdaf05bc5ca8a155dbaf0e35b49ba927778fe63fdf2bed`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: T-069 ist offen; Bug „PDF leer/Strich“ war nicht als DONE vermerkt.
  - LOG: kein früherer Eintrag zu **B-013** gefunden.
- **DATEIEN (max. 3):**
  - `services/PDFService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-013**: Monatsreport-PDF war leer (Parserabbruch), weil in mehreren `sprintf('...\n')`-Strings der Inhalt `\\n` **literal** (einfache Quotes) in den Content-Stream geschrieben wurde → Formatstrings auf **doppelte Quotes** umgestellt (echte Newlines), zusätzlich undefinierte Variablen in `baueMinimalPdfMitSeiten()` entfernt.
- **NEXT:**
  - **T-069:** Smoke-Test weiterführen (Terminal, Offline-Queue, Monatsreport PDF + Sammel-Export ZIP) und restliche Bugs fixen.


## P-2026-01-02-06
- ZIP: `P-2026-01-02-06_pdf-output-safety.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `084653_gesammt.zip` = `42393f15edc104c07786d3176efd255af7965244aa9ea91b6968147c50cffa47`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `b5ea713eb309520880474d5148a7af8feb79688ed3a29a6d29285f1ddd235b32`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: B-013 (Newlines im PDF-Stream) war gefixt, aber PDF blieb im Browser teils **leer/abgebrochen**.
  - LOG: kein früherer DONE-Eintrag zu „PDF-Output-Safety/Warning-Leak“ gefunden.
- **DATEIEN (max. 3):**
  - `controller/ReportController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-014:** PDF/ZIP-Ausgaben gegen „Warning/Notice-Leak“ und Output-Corruption gehärtet:
    - `report_monat_pdf` & `report_monat_export_all` nutzen nun **Error-Handler** (unterdrückt Ausgabe, loggt optional) + **Output-Buffering**.
    - Vor dem Senden werden Buffer konsequent geleert; außerdem wird `display_errors`/`zlib.output_compression` defensiv deaktiviert.
    - **Kein Content-Length** mehr gesetzt (verhindert Truncation bei (unerwarteter) Kompression/Proxy).
- **NEXT:**
  - **T-069:** Smoke-Test: Monatsreport-PDF im Browser prüfen (muss wieder vollständig rendern) und bei Bedarf als nächster Split die tatsächliche Vorlage-Optik (vorlageausgabezeiten.pdf) weiter angleichen.


## P-2026-01-02-07
- ZIP: `P-2026-01-02-07_pdf-locale-cache.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `090318_gesammt.zip` = `a37860bb838e4d752c9589a34c4880e68ff72d06bf7c340adbb1232858335616`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ba569144c08de862daa4f5899ddf5af5fe7dbf088aba7ebdf9d752e67ee4173b`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: B-013/B-014 waren erledigt, aber PDF konnte je nach Umgebung weiterhin **leer/abgebrochen** sein.
  - LOG: kein früherer DONE-Eintrag zu „Locale-Number-Bug im PDF-Stream“ gefunden.
- **DATEIEN (max. 3):**
  - `services/PDFService.php`
  - `services/FeiertagService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-015:** PDF-Content-Streams robuster gemacht:
    - Zahlen in PDF-Kommandos werden jetzt **locale-sicher** formatiert (immer Dezimalpunkt) → verhindert Parser-Abbrüche auf Systemen mit `LC_NUMERIC=de_DE`.
    - Helpers `pdfNum()`/`pdfLine()` ergänzt und konsequent genutzt.
  - Performance-Absicherung:
    - `FeiertagService` cached pro Request Jahr-Init + `istFeiertag`-Ergebnisse, um unnötige DB-Queries in Monats-Fallbacks zu reduzieren (weniger Timeout-Risiko beim PDF/ZIP).
- **NEXT:**
  - **T-069:** Monatsreport-PDF optisch weiter an `vorlageausgabezeiten.pdf` angleichen (Feinpositionierung/Schriftgrößen/Abstände) und Sammel-Export (ZIP) mit mehreren Mitarbeitern smoke-testen.


## P-2026-01-02-08
- ZIP: `P-2026-01-02-08_pdf-compat-no-gzip.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `091250_gesammt.zip` = `4575f7c020922c1f82d853cbb2b13b5b4fea98de0e6eb2bb45061861cd70db7a`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `146042041bac4ba4f2f8cf00c5703686304eb01c96b8cad1ca8c699bb2d862ef`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: B-013/B-014/B-015 waren bereits adressiert, aber der Effekt „oben ein Strich / sonst leer“ trat weiterhin sporadisch auf.
  - LOG: kein früherer DONE-Eintrag zu „PDF-Binary-Header/WinAnsiEncoding + no-gzip/no-transform Headers“ gefunden.
- **DATEIEN (max. 3):**
  - `services/PDFService.php`
  - `controller/ReportController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-016:** PDF-Generator/Response robuster gemacht:
    - Minimal-PDF bekommt jetzt Binary-Markierung im Header (`%\xE2\xE3\xCF\xD3`) + Fonts mit `/WinAnsiEncoding` + `/ProcSet` in Resources.
    - Content-Streams werden defensiv mit finalem Newline terminiert (Viewer-Kompatibilität).
    - `report_monat_pdf`: `apache_setenv('no-gzip', '1')` + `Cache-Control: no-transform` (verhindert Proxy/Kompressions-Manipulationen).
- **NEXT:**
  - **T-069:** Monatsreport-PDF im Browser erneut smoke-testen; wenn vollständig, nächster Split: Feinpositionierung/Schriftgrößen/Abstände zur 1:1 Vorlage (`vorlageausgabezeiten.pdf`).


## P-2026-01-02-09
- ZIP: `P-2026-01-02-09_monatsuebersicht-vollraster-bearbeitenlink.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `104502_gesammt.zip` = `8de2c8e79a9128e3a4d3fea7ece3c82331f5fc8a7eec1147246c5384aa283eee`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8e654e332aa3f34cbf13fc578b9d32682dafbdaae6ba8754c699fcea106f442c`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: Monatsübersicht zeigte bei Mitarbeiterwechsel teils nur „Tage mit Eintrag“ statt kompletter Monatstabelle.
  - LOG: kein früherer DONE-Eintrag zu „Monatsraster für tageswerte_mitarbeiter (sparse) in UI erzwingen“ gefunden.
- **DATEIEN (max. 4):**
  - `services/ReportService.php`
  - `controller/ReportController.php`
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-017:** Monatsübersicht zeigt jetzt **immer alle Tage** des ausgewählten Monats (auch wenn `tageswerte_mitarbeiter` nur einzelne Tage enthält):
    - In `ReportService` wird ein vollständiges Monatsraster aufgebaut und echte Tageswerte werden darauf gemerged (Defaults für fehlende Keys).
  - In der Monatsübersicht gibt es optional eine Spalte **„Bearbeiten“**:
    - Link führt zur bestehenden Tagesansicht/Korrektur (`?seite=zeit_heute&datum=...&mitarbeiter_id=...`).
    - Anzeige nur, wenn der User die Rechte `ZEITBUCHUNG_EDIT_ALL` oder (bei eigenen Daten) `ZEITBUCHUNG_EDIT_SELF` besitzt (inkl. Legacy Chef/Personalbüro).
- **NEXT:**
  - **T-070:** Monatsübersicht: optional pro Tag „Bearbeiten“ als Button stylen + evtl. Wochenenden/Feiertage farblich markieren (UI-Feinschliff).


## P-2026-01-02-10
- ZIP: `P-2026-01-02-10_dashboard-smoketest.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `105840_gesammt.zip` = `5bb516e93bcf736f2eb5baf3d8a81c9a3f232714704e2bf24fc520e5cd02976b`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `f93965c70ceaac896974190527a404979afebeadd34dae5d65df549fdc82bb8a`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: **T-069** ist offen (Smoke-Test Kernflows). Es gab bisher keine zentrale Diagnose-Seite im Backend.
  - LOG: kein früherer DONE-Eintrag zu „Dashboard Smoke-Test (DB/ZIP/Report/PDF)“ gefunden.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `views/dashboard/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **T-069 (Teil 1):** Dashboard bekommt einen **manuellen Smoke-Test** via `?seite=dashboard&smoke=1`:
    - Checks: Haupt-DB, `ZipArchive`, Core-Tabellen, ReportService Monatsraster (aktueller User/Monat), PDFService Quick-Test (Header `%PDF` + Größe).
    - Ausgabe im Dashboard inkl. Details + Laufzeit (ms). Nur auf Klick (nicht bei jedem Laden).
- **NEXT:**
  - **T-069:** Kernflows praktisch testen (Terminal Kommen/Gehen, Auftrag Start/Stop, Offline-Queue) und konkrete Bugfixes nach Testergebnissen.


## P-2026-01-02-11
- ZIP: `P-2026-01-02-11_dashboard-smoketest-tablefix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `112049_gesammt.zip` = `eeb03434e7df4ab64c498d2f7c88682eedb3b68cb660119317ae31c7adf0101a`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a13f730c37f65975016a30c4546342aa2e5a1e5bcf0b3d2d00a4cf473252dfde`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: **T-069** nutzt Dashboard-Smoke-Test zur Diagnose; Tabellen-Check muss korrekte Namen prüfen.
  - LOG: Kein früherer DONE-Eintrag zu „Smoke-Test prüft falsche Tabelle `feiertage`“ gefunden.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-018:** Dashboard-Smoke-Test prüft jetzt die korrekte Feiertags-Tabelle `feiertag` (statt `feiertage`).
  - Smoke-Test „DB Tabellen (Core)“ erweitert um: `auftrag`, `auftragszeit`, `urlaubsantrag`, `urlaub_kontingent_jahr` – passend zu **T-069** Kernflows.
- **NEXT:**
  - **T-069:** Kernflows praktisch testen (Terminal Kommen/Gehen, Auftrag Start/Stop, Offline-Queue) und konkrete Bugfixes nach Testergebnissen.


## P-2026-01-02-12
- ZIP: `P-2026-01-02-12_dashboard-smoketest-schema-terminalchecks.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `153508_gesammt.zip` = `6abd69f045c458119ae4b5c94baa2e7390861f72c31194abacffc3d565051439`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1199ed410709fd66cef92b99ee130590ef9a2fe83bb3d6306bc3d0da2c65c004`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: **T-069** ist offen; Smoke-Test soll typische „500 wegen Schema/Deployment“ schneller sichtbar machen.
  - LOG: Kein früherer DONE-Eintrag zu „Smoke-Test: Core-Spalten/Terminal-Services/Extensions prüfen“ gefunden.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **T-069 (Teil 2):** Dashboard-Smoke-Test erweitert um zusätzliche Diagnose-Checks:
    - **PHP Extensions (PDF/Export):** prüft `pdo_mysql`-Driver, `mbstring` (`mb_strlen`) und `iconv`.
    - **DB Spalten (Core):** prüft wichtige Spalten-Sets in `zeitbuchung`, `auftragszeit`, `auftrag`, `terminal`, `db_injektionsqueue` (Schema-Drift früh erkennen).
    - **Terminal Dateien:** prüft, ob `public/terminal.php` + relevante Terminal-Assets vorhanden sind.
    - **Terminal Services:** prüft, ob `ZeitService` (bucheKommen/bucheGehen) und `AuftragszeitService` (starte/stoppe) samt Singleton verfügbar sind.
- **NEXT:**
  - **T-069:** Kernflows praktisch testen (Terminal Kommen/Gehen, Auftrag Start/Stop, Offline-Queue) und konkrete Bugfixes nach Testergebnissen.


## P-2026-01-02-13
- ZIP: `P-2026-01-02-13_betriebsferien-als-urlaub-report-pdf.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `154750_gesammt.zip` = `ed31e48c70713bf10f11e071a3e4df4ce8f2a64ecd84402c1dad53fe9861dd43`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `e7b23ac3bdbbcba994245884b5ebfe55d67cce832d65fe27059e50bcf9672ab6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `e86825f959a1b22b8ce2d3275acd01c7b4bd3c3a6c6d7b53931fd8075c785551`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG: Betriebsferien wurden bisher nur als Marker behandelt; es fehlte eine saubere Verrechnung als Urlaub in Report/PDF.
- **DATEIEN (max. 3):**
  - `services/ReportService.php`
  - `docs/master_prompt_zeiterfassung_v5.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Betriebsferien zählen in Auswertungen wie **Urlaub**: pro Arbeitstag werden **8,00h Urlaub** in den Tageswerten belegt.
  - Monats-Fallback-Berechnung summiert jetzt Arbeitszeit **+** bezahlte Abwesenheiten (Urlaub/Krank/Feiertag/...).
  - Sollstunden-Fallback: Betriebsferien reduzieren das Soll **nicht** (wie normaler Arbeitstag; Ausgleich über Urlaubstunden).
  - Ergebnis: PDF/Arbeitszeitliste weist Betriebsferien korrekt in der Spalte „Urlaub“ (8,00) aus.
- **NEXT:**
  - Urlaubskontingent-/Urlaubsgenommene-Berechnung um Betriebsferien erweitern (Zwangsurlaub), sofern noch nicht berücksichtigt.


## P-2026-01-02-14
- ZIP: `P-2026-01-02-14_betriebsferien-urlaubssaldo.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `160438_gesammt.zip` = `dd709cd03e754e252c85dd9c6775b2e6a589ef601a5e12e61d50ec72e57ea5ec`
  - Master-Prompt (im ZIP): `docs/master_prompt_zeiterfassung_v5.md` = `3621eabe160ec2cf2559c63b153089cd0afbbbae555878ca3a596418dbf02d5f`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `21c229f8eac0cf5d41860b061cdcf230783c0337bd9dbb8cc49b8688e08f6a38`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG: Report/PDF hatte Betriebsferien bereits als Urlaub (8,00h) ausgewiesen; es fehlte noch die **Kontingent-/Saldo-Verrechnung** für Betriebsferien.
- **DATEIEN (max. 3):**
  - `services/UrlaubService.php`
  - `docs/master_prompt_zeiterfassung_v5.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Betriebsferien werden im Urlaubssaldo als **genommener Urlaub** gezählt (nur Arbeitstage; keine Feiertage/Wochenenden).
  - Urlaubsanträge zählen Betriebsferien-Tage weiterhin **nicht doppelt** (Betriebsferien bleiben im Arbeitstage-Count ausgeschlossen).
- **NEXT:**
  - **T-069:** Smoke-Test Urlaubssaldo/Urlaub-Kontingent-UI (inkl. Betriebsferien) + Monatsreport/PDF mit Betriebsferien-Range prüfen; ggf. Edgecases (Teilzeit/abweichende Arbeitstage) als nächstes sauber spezifizieren.


## P-2026-01-02-15
- ZIP: `P-2026-01-02-15_dashboard-smoketest-queuechecks-fullbutton.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `161317_gesammt.zip` = `100b8a2c9d75c9e51da494e399f481d2a8f985258d677dba309cf3a1f8a49e2e`
  - Master-Prompt (im Projekt): `docs/master_prompt_zeiterfassung_v5.md` = `9f74ef538bca5a7fdf95b32aecde82e8390993ee310df6f0d2aa53be4c36cbe2`
  - DEV_PROMPT_HISTORY (nach Patch): `docs/DEV_PROMPT_HISTORY.md` = `ab7abf58333934a62690b0ce430cacac835f2eecf2f0fc63e083c93b7f6c2bc1`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: T-069 ist offen; Ziel ist schnellere Diagnose typischer Setup/Queue-Probleme.
  - VIEW: Es existierten zwei Smoke-Test-Boxen; die Duplikation wurde entfernt.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `views/dashboard/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard-Smoke-Test erweitert um **Offline-Queue DB/Schema** Check (Offline-DB falls aktiv, sonst Haupt-DB):
    - Connection + `db_injektionsqueue` Tabellen-Existenz (read-only Hinweis: wird beim ersten Queue-Write angelegt).
  - Dashboard-UI: zusätzlicher Button für **FULL Smoke** (`smoke=2`, Schreibtests in Transaktion + Rollback).
  - Dashboard-UI: doppelte Smoke-Test-Box entfernt.
- **NEXT:**
  - **T-069:** Kernflows praktisch testen (Terminal Kommen/Gehen, Auftrag Start/Stop, Offline-Queue mit DB-Ausfall) und konkrete Bugfixes nach Testergebnissen.


## P-2026-01-02-16
- ZIP: `P-2026-01-02-16_dashboard-smoketest-dbchecks-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `161317_gesammt.zip` = `100b8a2c9d75c9e51da494e399f481d2a8f985258d677dba309cf3a1f8a49e2e`
  - Master-Prompt (im Projekt): `docs/master_prompt_zeiterfassung_v5.md` = `9f74ef538bca5a7fdf95b32aecde82e8390993ee310df6f0d2aa53be4c36cbe2`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `56637b1cb09bfdb7dcdbc0619dda0f99b22dc04cb27831e25631463901b8a60f`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT: Smoke-Test meldete „fehlende Tabellen“ obwohl DB/Queue-Checks OK wirkten.
  - Ursache: `SHOW TABLES` über PDO prepared statements ist nicht zuverlässig → false positives.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Smoke-Test **DB Tabellen (Core)** auf `information_schema.tables` umgestellt (prepared-statement-safe) + Fallback `SHOW TABLES` ohne prepare.
  - Smoke-Test **DB Spalten (Core)** auf `information_schema.columns` umgestellt.
  - Schema-Check `auftrag` korrigiert (gemäß Master/SQL: `auftragsnummer` statt `code/bezeichnung`).
  - Smoke=1 zeigt **DB Write (Rollback)** als **Info/OK** (nicht rot), da nur bei `smoke=2` aktiv.
  - Lange Detailtexte werden im Tabellen-Check auf 420 Zeichen gekürzt.
- **NEXT:**
  - **T-069:** Smoke=1/2 erneut laufen lassen und die echten Terminal-/Queue-Flows testen; danach echte Bugfixes aus Logs ableiten.


## P-2026-01-02-17
- ZIP: `P-2026-01-02-17_auftragmodel-auftragsnummer.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `161317_gesammt.zip` = `100b8a2c9d75c9e51da494e399f481d2a8f985258d677dba309cf3a1f8a49e2e`
  - Master-Prompt (im Projekt): `docs/master_prompt_zeiterfassung_v5.md` = `9f74ef538bca5a7fdf95b32aecde82e8390993ee310df6f0d2aa53be4c36cbe2`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7376bd1334861506ea9530755b930a158db78f80120636db9de7f998ad9f87e8`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG: Schema-Check für `auftrag` ist bereits auf `auftragsnummer` korrigiert (P-2026-01-02-16), aber `AuftragModel::findeNachCode()` fragte weiterhin `auftrag.auftragscode` ab.
  - Ergebnis: potenzieller Fehler/Log-Spam beim Starten von Aufträgen (AuftragszeitService), da Spalte in `auftrag` nicht existiert.
- **DATEIEN (max. 3):**
  - `modelle/AuftragModel.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `AuftragModel::findeNachCode()` nutzt jetzt `auftrag.auftragsnummer` statt `auftrag.auftragscode`.
  - Logger-Kontext-Key auf `auftragsnummer` umgestellt.
- **NEXT:**
  - **T-069:** Echte Terminal-Flows testen (Kommen/Gehen + Auftrag Start/Stop + Offline-Queue) und bei Auffälligkeiten Bugfixes ableiten.

## P-2026-01-03-01
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `1600180b7ae970f16b728b207dd4326bb8a81fb5837b480bc55421741f67e862`
  - Master-Prompt: `3952e29757b3e8d1ac33fc2bdd64da404a25ba293a4509de25a7d2d2baa09b00`
  - DEV_PROMPT_HISTORY: `ed59fcdb16084774f0deb1d1e3774157cadb45ef95e4b33d3f13842ac8be093c`
  - SQL-Schema: `eb53bf163e40cd5bd4087ac0781ec1735fe042fced5f0bf3e87a34d61b507e2d`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT (T-069..T-075) + LOG; keine doppelte Implementierung, nur Spec-Update.
- **DATEIEN (max. 3):**
  - `docs/master_prompt_zeiterfassung_v6.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Spec/Prompt erweitert: BF als globaler Kalender (nicht klickbar), Kurzarbeit-Plan+Tages-Override, Krank LFZ/KK als Zeitraum/Krankfall (manuell, optional 6-Wochen-Vorschlag), Sonstiges-Gründe konfigurierbar (SoU über `kommentar`), PDF-Hinweise (An/Ab + Mehrfachblöcke ohne optische Doppelzählung).
- **NEXT:** T-069 (Smoke-Test & Bugfixes).

## P-2026-01-03-02
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `062317_gesammt.zip` = `1a9c8407b88fc6608b2aeb50b648a95889eb002439829a0b31d270007705d3f6`
  - Master-Prompt (im Projekt): `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `78d865a90c860228589c02d9cac92a01f852aa2d48755987ceed0fc197bbbb5d`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL-Dump (aktuell): `zeiterfassung_aktuell.sql` = `eb53bf163e40cd5bd4087ac0781ec1735fe042fced5f0bf3e87a34d61b507e2d`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG: Keine vorhandene Backend-Route `smoke_test` und kein `SmokeTestController`; Terminal-Health existiert separat (`terminal.php?aktion=health`).
  - Ergebnis: Implementierung ist neu (keine Doppelung).
- **DATEIEN (max. 3):**
  - `public/index.php`
  - `controller/SmokeTestController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend-Route `?seite=smoke_test` ergänzt (geschützt) und `SmokeTestController` implementiert.
  - Read-only Checks: PHP/Extensions, wichtige Dateien, DB-Connectivity, Tabellen/Spalten, optional Offline-DB.
  - Manuelle Klick-Checkliste direkt auf der Seite hinterlegt.
- **NEXT:**
  - **T-069:** Smoke-Test jetzt praktisch durchführen (Kernflows) und konkrete Bugfixes ableiten.


## P-2026-01-03-03
- ZIP: `P-2026-01-03-03_kurzarbeit-plan-schema.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `063506_gesammt.zip` = `06d56e36b7202e48e0c6f115dc3dedec834b5dd7bf52bdbd6bb48a56b1d8566e`
  - Master-Prompt (im Projekt): `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `4c62bda15ea86ebe2b7c9d93d449459c6550769ea2f907b6d5e30bc84ac871b2`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `328983ebce85fca36940382f4cac9d5657fb9af054d0c31c4091d674a3fa30a3`
  - SQL-Dump (aktuell): `sql/zeiterfassung_aktuell.sql` = `eb53bf163e40cd5bd4087ac0781ec1735fe042fced5f0bf3e87a34d61b507e2d`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG: Es existierte bisher keine Tabelle zur Kurzarbeit-Planung (nur Tagesfelder in `tageswerte_mitarbeiter`).
  - Ergebnis: Schema-Erweiterung ist neu; keine Doppelung.
- **DATEIEN (max. 3):**
  - `sql/01_initial_schema.sql`
  - `sql/07_migration_kurzarbeit_plan.sql`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neue Tabelle `kurzarbeit_plan` (Scope firma/mitarbeiter, Zeitraum, Wochentage-Maske, Modus Stunden/Prozent, Wert, Kommentar, Audit) in **Source-of-Truth Schema** ergänzt.
  - Migration `07_migration_kurzarbeit_plan.sql` hinzugefügt (idempotent) für Bestandsdatenbanken.
- **NEXT:**
  - **T-070 (Teil 2):** KurzarbeitService + Admin-UI (Plan CRUD) und Anwendung in Report/Tageswerte (Soll-Reduktion + Tages-Override in Korrekturmaske).

## P-2026-01-03-04
- ZIP: `P-2026-01-03-04_fix-initial-schema-kurzarbeit-plan.zip`

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `064031_gesammt.zip` = `360a4ee9192316e274b9a7adc1b73248bdbc67980d128d39e0cd2ee7faf90c28`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `827612215a0235d330de1adb41aa7a6083a687ad4de53f2b516bbb22183f5373`
  - SQL-Schema: `sql/01_initial_schema.sql` = `8f8a92286ca504c98718d6421474bc4e424f2326a3fb79ac87255b2876d3e113`

- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Kein bestehender Patch behebt die doppelte `ENGINE`-Zeile nach `kurzarbeit_plan` (neu eingeführt in P-2026-01-03-03).

- **DATEIEN (max. 3):**
  - `sql/01_initial_schema.sql`
  - `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - Source-of-Truth Schema repariert: Die stray/doppelte `) ENGINE=InnoDB ...;` Zeile nach `kurzarbeit_plan` entfernt (Neuanlage/Import wieder lauffähig).

- **NEXT:**
  - **T-070** Kurzarbeit (Teil 1): Service + Admin-UI (Plan anlegen/bearbeiten) + Report-Logik (Soll-Reduktion + Tages-Override).

## P-2026-01-03-05
- ZIP: `P-2026-01-03-05_kurzarbeit-report-plan-anwenden.zip`

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `064544_gesammt.zip` = `ae65b37c90cbe72d227446efb1867805df6981b967e908cbf030c11f6e179395`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8f1227d0146e55e3b8cf59355c9dc167f2bd3b60762d364f6f63d415bb34c86f`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `92b6a9fdc34621a62627b54a8d1c17d7d2c2b5b444cd1683a41cf75d2f61b841`
  - SQL-Dump (aktuell): `sql/zeiterfassung_aktuell.sql` = `02388604b2ad7bc0f393f632ddc1bca6e18c5917153cc117f44fb5b254b9870c`

- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Kein vorhandener `KurzarbeitService` und keine Report-Logik, die Kurzarbeit als Soll-Reduktion behandelt; bisher nur Schema vorhanden.
  - Ergebnis: Implementierung ist neu; keine Doppelung.

- **DATEIEN (max. 3):**
  - `services/KurzarbeitService.php`
  - `services/ReportService.php`
  - `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - `KurzarbeitService` neu: liest `kurzarbeit_plan` (firma/mitarbeiter, Zeitraum, Wochentage, Modus Stunden/Prozent) und wendet Plan **nur für Anzeige** auf Tageswerte an (kein DB-Write).
  - `ReportService`: Monatsraster bleibt vollständig; Kurzarbeit-Plan wird nach Raster-Erzeugung angewendet (wirkt auch auf PDF).
  - Fallback-Monatswerte: Kurzarbeit zählt **nicht** mehr zu "Ist", sondern reduziert das Soll (Mo-Fr, nicht Feiertag) → Saldo/Saldo-Logik passt zur Spec.

- **NEXT:**
  - **T-070 (Teil 2):** Admin-UI für Plan-CRUD + Tages-Override in der Korrekturmaske + (optional) Persist/Recalc, damit `monatswerte_mitarbeiter` die gleiche Logik nutzt.


## P-2026-01-03-06
- ZIP: `P-2026-01-03-06_kurzarbeit-admin-crud.zip`

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `065724_gesammt.zip` = `3760a152a9168574c8cb54f9ca3671e5336ff26c56d0ed58291d2bfd5b5e2095`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `345fc9164fde4f57541deb18ed4fd04caebb0eea2cc672f889d90c9bb8ccc0c0`
  - SQL-Schema (Source of Truth): `sql/01_initial_schema.sql` = `8f8a92286ca504c98718d6421474bc4e424f2326a3fb79ac87255b2876d3e113`
  - SQL-Dump (aktuell): `sql/zeiterfassung_aktuell.sql` = `e6e7a9a443133c6fb8cdb9e0eead7f631ee0b8c6a9c9597fb1c97ead20920ccb`

- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Kein vorhandenes Backend-Routing `kurzarbeit_admin*` und kein `KurzarbeitAdminController`.
  - Ergebnis: Implementierung ist neu; keine Doppelung.

- **DATEIEN (max. 3):**
  - `public/index.php`
  - `controller/KurzarbeitAdminController.php`
  - `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - Backend-Routing: `kurzarbeit_admin` (Liste), `kurzarbeit_admin_bearbeiten` (Form), `kurzarbeit_admin_speichern` (POST), `kurzarbeit_admin_toggle` (POST).
  - `KurzarbeitAdminController`: CRUD für `kurzarbeit_plan` (Scope Firma/Mitarbeiter, Zeitraum, Wochentage-Maske, Modus Stunden/Prozent, Wert, Kommentar, Aktiv) inkl. CSRF + Flash-Meldungen.
  - Aktiv-Toggle direkt in der Liste (POST+CSRF), ohne erst ins Formular zu gehen.

- **NEXT:**
  - **T-070 (Teil 2b):** Tages-Override in der Korrekturmaske (Checkbox/Inputs) → Schreiben nach `tageswerte_mitarbeiter` + Anzeige/Verrechnung konsistent in Monatsübersicht und PDF.

---

## P-2026-01-03-07_kurzarbeit-tagesoverride.zip

### EINGELESEN (SHA256)
- Projekt-ZIP: `071104_gesammt.zip` = `bbd1c1badb7a1af714d1c6f7d98570be6c0273f26300ccd66082c15da86479d6`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `9287a9a90b3ed3ec0bbfeb814b739581641ba37035585fdf5944dce73f55a370`
- SQL (SoT): `sql/01_initial_schema.sql` = `8f8a92286ca504c98718d6421474bc4e424f2326a3fb79ac87255b2876d3e113`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e6e7a9a443133c6fb8cdb9e0eead7f631ee0b8c6a9c9597fb1c97ead20920ccb`

### DUPLICATE-CHECK
- Gesucht: Kurzarbeit Tages-Override (Korrekturmaske/Tagesansicht)
- Ergebnis: **noch nicht vorhanden** im Stand `P-2026-01-03-06` → Umsetzung ok.

### ZIEL
- In der Tagesansicht (Korrekturmaske) soll Kurzarbeit pro Tag schnell setzbar sein (Checkbox + Stunden).
- Speicherung in `tageswerte_mitarbeiter` (Upsert) → Report/PDF übernimmt Werte, Plan wird überschrieben.
- Optional: Hinweis auf Planwert (nur Anzeige), damit Admin schnell übernehmen kann.

### UMSETZUNG
- Tagesansicht zeigt neuen Block „Abwesenheiten / Tagesfelder“ (nur wenn Admin-Korrektur erlaubt).
- Checkbox „Kurzarbeit“ + Stundenfeld + Begründung (Pflicht) → POST `aktion=set_kurzarbeit`.
- Controller upsertet `kennzeichen_kurzarbeit` + `kurzarbeit_stunden` und setzt `felder_manuell_geaendert=1`.
- Konflikt-Check: Kurzarbeit wird verweigert, wenn Urlaub/Krank/Feiertag/Arzt/Sonstiges bereits gesetzt ist.
- Plan-Hinweis: wenn kein Override gesetzt ist, wird Planwert für den Tag berechnet und angezeigt; bei leerem Stundenfeld wird Planwert als Default übernommen.

### DATEIEN (max 3)
1) `controller/ZeitController.php`
2) `views/zeit/tagesansicht.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### NÄCHSTER SCHRITT
---

## P-2026-01-03-08_krankzeitraum-schema.zip

### EINGELESEN (SHA256)
- Projekt-ZIP: `072456_gesammt.zip` = `145a7aeb83258fa049cf7ae851a9c73859ae8a743f0bd11eb713bb9870f62810`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `b9ba0481e74eb92cf524b2ccbf3c50c517874b7a9673793d04c596f655b66ad6`
- SQL (SoT): `sql/01_initial_schema.sql` = `8f8a92286ca504c98718d6421474bc4e424f2326a3fb79ac87255b2876d3e113`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e6e7a9a443133c6fb8cdb9e0eead7f631ee0b8c6a9c9597fb1c97ead20920ccb`

### DUPLICATE-CHECK
- Gesucht: Krank (LFZ/KK) als Zeitraum-Tabelle (Schema)
- Ergebnis: **noch nicht vorhanden** → Umsetzung ok.

### ZIEL
- Tabelle `krankzeitraum` als Source-of-Truth + Migration, damit Krank-LFZ/KK später automatisch auf Tageswerte/Report/PDF abgeleitet werden kann.

### UMSETZUNG
- `sql/01_initial_schema.sql`: `feiertag`-Tabelle repariert (fehlendes `) ENGINE=...`), sonst bricht Neuanlage ab.
- `sql/01_initial_schema.sql`: neue Tabelle `krankzeitraum` (LFZ/KK) nach `mitarbeiter` eingefügt.
- Neue Migration `sql/08_migration_krankzeitraum.sql` (idempotent).

### DATEIEN (max 3)
1) `sql/01_initial_schema.sql`
2) `sql/08_migration_krankzeitraum.sql`
3) `docs/DEV_PROMPT_HISTORY.md`

### NÄCHSTER SCHRITT

## P-2026-01-03-09_krankzeitraum-admin-crud.zip

### EINGELESEN (SHA256)
- Projekt-ZIP: `073308_gesammt.zip` = `51d9566e9955b9dcf658f26b8ad76e4e4f336f68d7c90bf22aa245a6238f4b78`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `d4ab6e24a7dad2f47397ad08d94a4b011e83291b66987b4deda25f4aeddbd0fd`
- SQL (SoT): `sql/01_initial_schema.sql` = `9e8ac3ae5bf172a902079b050d62af4561e8eede8ee37f87ad09e362e916847f`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e65c8e4d7312b30e911ecab5f2dea8e2dff2854ac6cbaeb05e1aaebafc3d26d6`

### DUPLICATE-CHECK
- Gesucht: Admin-UI für `krankzeitraum` (LFZ/KK) inkl. Menüpunkt
- Ergebnis: **noch nicht vorhanden** → Umsetzung ok.

### ZIEL
- Krankzeiten pro Mitarbeiter als Zeiträume pflegbar machen (LFZ/KK), damit Report/PDF später automatisch die Spalten **Krank LF** und **Krank KK** befüllen kann.

### UMSETZUNG
- `controller/KonfigurationController.php`: neuer Tab `tab=krankzeitraum` mit Admin-CRUD (Liste + Formular, Speichern, Aktivieren/Deaktivieren) inkl. CSRF; Overlap-Check pro Mitarbeiter für aktive Zeiträume.
- `views/layout/header.php`: Menüpunkt **Krank (LF/KK)** (sichtbar bei Recht `KRANKZEITRAUM_VERWALTEN` oder Konfig-Recht, Legacy-Fallback Chef/Personalbüro).

### DATEIEN (max 3)
1) `controller/KonfigurationController.php`
2) `views/layout/header.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### NÄCHSTER SCHRITT
## P-2026-01-03-10_krankzeitraum-report-ableitung.zip

### EINGELESEN (SHA256)
- Projekt-ZIP: `074437_gesammt.zip` = `df9abd801879cf488f5adfd7c316044536f8872fe8c829afab6a09c0d120d6aa`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `fa21c7add8619066cdfdd822d0eb7e99fa12ab6b21aa2adf3ed17d2d68923846`
- SQL (SoT): `sql/01_initial_schema.sql` = `9e8ac3ae5bf172a902079b050d62af4561e8eede8ee37f87ad09e362e916847f`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e65c8e4d7312b30e911ecab5f2dea8e2dff2854ac6cbaeb05e1aaebafc3d26d6`

### DUPLICATE-CHECK
- Gesucht: **T-071 (Teil 3)** – Ableitung von `krankzeitraum` (LFZ/KK) in Monatsreport/PDF.
- Ergebnis: **noch nicht vorhanden** (im SNAPSHOT als nächster Schritt) → Umsetzung ok.

### ZIEL
- Monatsreport/PDF sollen die Spalten **Krank LF** und **Krank KK** automatisch aus den gepflegten Krank-Zeiträumen befüllen.

### UMSETZUNG
- Neue Serviceklasse `KrankzeitraumService`: Lädt aktive Zeiträume (LFZ/KK) und wendet sie als **Anzeige-Logik** auf Tageswerte an (nur Mo-Fr, keine Feiertage; keine Überschreibung von Urlaub/Kurzarbeit/Arzt/Sonstiges; keine Überschreibung wenn Arbeitszeit vorhanden).
- `ReportService`: Wendet Krankzeitraum **vor** Kurzarbeit an, sodass Kurzarbeit den Krank-Tag nicht überschreibt.

### DATEIEN (max 3)
1) `services/KrankzeitraumService.php`
2) `services/ReportService.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### NÄCHSTER SCHRITT
- **T-071 (Teil 4):** Tages-Override für Krank (LFZ/KK) in der Tagesansicht (Checkbox/Typ-Auswahl + Stunden) + Persist in `tageswerte_mitarbeiter`; Report/PDF: Override hat Vorrang.

## P-2026-01-03-11_krank-tagesoverride.zip

### EINGELESEN (SHA256)
- Projekt-ZIP: `075553_gesammt.zip` = `5a13e3072130811939944ee8789ee883932f62e0ecaa05fc64ad5fd0301bcc1b`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `915f15ea0964d069c807e13fc441392dda5b7bb26047de0d72237cd467e6564a`
- SQL (SoT): `sql/01_initial_schema.sql` = `9e8ac3ae5bf172a902079b050d62af4561e8eede8ee37f87ad09e362e916847f`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e65c8e4d7312b30e911ecab5f2dea8e2dff2854ac6cbaeb05e1aaebafc3d26d6`

### DUPLICATE-CHECK
- Gesucht: **T-071 (Teil 4)** – Tages-Override Krank (LFZ/KK) in Tagesansicht + Persist.
- Ergebnis: **noch nicht vorhanden** (im SNAPSHOT als nächster Schritt) → Umsetzung ok.

### ZIEL
- Krank LFZ/Krank KK soll pro Tag als Override setzbar/entfernbar sein (Admin), unabhängig vom Zeitraum-Plan.
- Report/PDF: Zeitraum-Ableitung soll auch funktionieren, wenn im Monat nur einzelne `tageswerte_mitarbeiter`-Datensätze existieren.

### UMSETZUNG
- `views/zeit/tagesansicht.php`: Neuer Abschnitt **Krank (LFZ/KK)** (Radio + Stunden + Pflicht-Begründung).
- `controller/ZeitController.php`: Neue POST-Aktion `set_krank`:
  - Validierung (Konflikte mit Urlaub/Kurzarbeit/Feiertag/Arzt/Sonstiges; keine Arbeitszeit parallel).
  - Persist via UPSERT in `tageswerte_mitarbeiter` (LFZ/KK gegenseitig exklusiv) + `felder_manuell_geaendert=1`.
- `services/ReportService.php`: Wendet `KrankzeitraumService` auf das komplette Monatsraster an (vor Kurzarbeit), damit Krankzeiträume auch bei teilweisen Tageswerten korrekt im Report/PDF erscheinen.

### DATEIEN (max 3 + DEV)
1) `views/zeit/tagesansicht.php`
2) `controller/ZeitController.php`
3) `services/ReportService.php`
4) `docs/DEV_PROMPT_HISTORY.md`

### NÄCHSTER SCHRITT
- **T-072:** Pausenregeln konfigurierbar: betriebliche Pausenfenster (Uhrzeit) + gesetzliche Mindestpause (Schwellen/Minuten) + Abzug pro Arbeitsblock.


## P-2026-01-03-12
- ZIP: `P-2026-01-03-12_pausenfenster-insert-fix.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `082157_gesammt.zip` = `b268475e17b3a2bcc70dc19c064abc197aab9cc076fd8988c01fe2f92f5b23fe`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `abef2b952363c4262d16b71b54fd4c17e47af583a585910f6cee5d55a7015310`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `f88a6d22fd14be285addba82c1263d27fe9f2698003231176d787275e574e483`

### DUPLICATE-CHECK
- Gesucht: Fix für **B-022** (T-072 Teil 1: Admin-CRUD `pausenfenster` – Speichern neuer Einträge).
- Ergebnis: **nicht vorhanden** in SNAPSHOT/LOG (T-072 weiterhin offen) → Umsetzung ok.

### DATEIEN (max. 3)
1) `controller/KonfigurationController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- **B-022** gefixt: `pausenfenster`-INSERT nutzt nur Spalten, die im Schema existieren → Admin „Pausenfenster anlegen“ speichert wieder.

### NEXT
- **T-072 (Teil 1b):** Pausenregeln: fehlende Rechte-Codes (z. B. `PAUSENREGELN_VERWALTEN`) als Migration ergänzen + Default-Zuordnung (Chef/Personalbüro).


## P-2026-01-03-13
- ZIP: `P-2026-01-03-13_rechte-admin-pausen-krank-kurzarbeit.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `091055_gesammt.zip` = `dcb09f059e5e6377c1dc4a1137f144abe0af93f21c16200a35de845187fe7f70`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `2f43006f7a194ce001a5398c9be8b4b96d0a4c344d910f2ee34d1b0002b792d4`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `f88a6d22fd14be285addba82c1263d27fe9f2698003231176d787275e574e483`

### DUPLICATE-CHECK
- Gesucht: **T-072 (Teil 1b)** (fehlende Rechte-Codes z. B. `PAUSENREGELN_VERWALTEN`).
- Ergebnis: Im SNAPSHOT als NEXT geführt; in `sql/03`–`sql/09` nicht vorhanden → Umsetzung ok.

### DATEIEN (max. 3)
1) `sql/10_migration_admin_rechte_pausen_kurzarbeit_krank.sql`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Neue Rechte-Codes eingeführt: `PAUSENREGELN_VERWALTEN`, `KRANKZEITRAUM_VERWALTEN`, `KURZARBEIT_VERWALTEN` (idempotent).
- Default-Zuordnung an Rollen **Chef** und **Personalbüro** (falls Rollen existieren).

### NEXT
- **T-072 (Teil 2):** Pausenregeln: automatische Pausen-Berechnung (Fenster + gesetzliche Mindestpause) in `ZeitService`/`ReportService` anwenden.

## P-2026-01-03-14
- ZIP: `P-2026-01-03-14_masterprompt-v6-task-splitting.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `091751_gesammt.zip` = `5e310682a21fec296d12f7379016072e088b0bc36848ce0d7878a73f54d79491`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `466823f9a93ae0a6cad5eb4dfe6e9a9c25240d7c81b7424323e799fb644eafc0`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `24e73473f9bf74caff3d2e275f541a8b4b6774673ee3d36d81697934722b8a1d`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Meta-Fix „Master-Prompt v6 enthält v5-Referenzen / Task-Splitting-Regeln fehlen“.
- Ergebnis: Im SNAPSHOT/LOG nicht als DONE vorhanden → Umsetzung ok.

### DATEIEN (max. 3)
1) `docs/master_prompt_zeiterfassung_v6.md`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Master-Prompt v6 konsolidiert: v6 Header/Version, keine v5-Verweise mehr, neue verbindliche Regeln für Task-Splitting/Patch-Größe.
- DEV_PROMPT_HISTORY: SNAPSHOT/Checkliste auf v6 aktualisiert + neue Decision/Gate für Micro-Patches.

### NEXT
- **T-072 (Teil 2):** Pausenregeln: automatische Pausen-Berechnung (Fenster + gesetzliche Mindestpause) in `ZeitService`/`ReportService` anwenden.

## P-2026-01-03-15
- ZIP: `P-2026-01-03-15_pausenberechnung-reportservice.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `095934_gesammt.zip` = `6a34ae23346d2c0cfce11a55c11337e588ed9a9434fa88b54ce1fd7f84b0afa3`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ed5bb23ed500ababb0478584da1a23c79fa533a85e99fa5e58de1228f9cc99cf`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: **T-072 (Teil 2)** (Pausenregeln automatisch im Report/PDF anwenden).
- Ergebnis: Im SNAPSHOT als NEXT geführt; in LOG nicht als DONE vorhanden → Umsetzung ok.

### DATEIEN (max. 3)
1) `services/PausenService.php` (neu)
2) `services/ReportService.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monatsreport/Monatsübersicht/PDF zieht Pausen jetzt automatisch nach Regeln: `pause = max(Überlappung mit aktiven Pausenfenstern, gesetzliche Mindestpause)`; DB-Wert `pause_korr_minuten` wird nur genutzt, wenn > 0.

### AKZEPTANZ
- Bei Arbeitsblock **05:30–13:00** und aktiven Pausenfenstern **09:00–09:15** + **12:30–13:00** zeigt der Monatsreport **Pause=0,75h** und reduziert die IST-Zeit entsprechend.

### NEXT
- **T-072 (Teil 3):** Pausenberechnung im `ZeitService` (Tageswerte-Sync) anwenden und in `tageswerte_mitarbeiter.pause_korr_minuten` persistieren.

## P-2026-01-03-16
- ZIP: `P-2026-01-03-16_pausenberechnung-zeitservice.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `101847_gesammt.zip` = `a211452bdbcc5516570814b94e52a95450c1caec0b0edd273eae250344b3f1f3`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ead321fc9941d097cebc2e357c35d8f9a067f6c3091c3dc62b8375d0d9575864`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: **T-072 (Teil 3)** (Pausenregeln im Tageswerte-Sync anwenden + persistieren).
- Ergebnis: Im SNAPSHOT als NEXT geführt; in LOG nicht als DONE vorhanden → Umsetzung ok.

### DATEIEN (max. 3)
1) `services/ZeitService.php`
2) `modelle/TageswerteMitarbeiterModel.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ZeitService::synchronisiereTageswerteAusBuchungen()` berechnet jetzt Pausenminuten automatisch via `PausenService` (auf korrigierten Zeiten, Fallback roh) und verwendet sie für `ist_stunden`.
- `TageswerteMitarbeiterModel::upsertRohzeitenUndIststunden()` persistiert `pause_korr_minuten` im Upsert (überschreibt **nicht**, wenn `felder_manuell_geaendert=1`).

### AKZEPTANZ
- Bei Buchungen **05:30–13:00** und aktiven Pausenfenstern **09:00–09:15** + **12:30–13:00** wird beim Tageswerte-Sync `pause_korr_minuten=45` gespeichert und `ist_stunden` entsprechend reduziert (Rundung erfolgt weiterhin nur abgeleitet über `RundungsService`).

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln inkl. Offline-Queue, Monatsübersicht, PDF) + Fixes.

## P-2026-01-03-17
- ZIP: `P-2026-01-03-17_reportservice-fixes-manual-pause-krank-dedupe.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `103221_gesammt.zip` = `e949b7124101fc5dd594fcf93cfbc8f1dba63111ae212e78412f2245d3a11494`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ebf4821093279c301b8e3ebda626c6d0866ecdd9f4daf66a1ff6fb99f9b7a958`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: ReportService respektiert `felder_manuell_geaendert` bei `pause_korr_minuten=0` / doppelter Aufruf `krankzeitraumService->wendeZeitraeumeAufTageswerteAn()`.
- Ergebnis: Auto-Pause überschreibt manuelle Pause=0; Krankzeitraum wird doppelt angewendet → Fix erforderlich.

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Report/Monatsübersicht/PDF: automatische Pausenberechnung wird nur angewendet, wenn `felder_manuell_geaendert != 1` (manuelle Pause=0 bleibt 0).
- Doppelten Aufruf von `KrankzeitraumService::wendeZeitraeumeAufTageswerteAn()` entfernt (wird nur einmal angewendet).

### AKZEPTANZ
- Wenn Tageswerte manuell gesetzt sind (`felder_manuell_geaendert=1`) und `pause_korr_minuten=0`, zeigt der Report Pause=0 und überschreibt nicht automatisch.
- Krank-/LFZ/KK-Anzeige wird nur einmal angewendet (keine doppelten Seiteneffekte).

### NEXT
- **T-069:** Fix in `views/zeit/tagesansicht.php` (fehlendes `</form>` / verschachtelte Formulare bei Tagesfeldern) + kurzer Terminal-Flow-Check.

## P-2026-01-03-18
- ZIP: `P-2026-01-03-18_tagesansicht-form-fix.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `103739_gesammt.zip` = `c3a389cf6c4a71b692cd0769e72c26931f8474d3ec7d6a17269c017a19ee4f6c`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `b75f71c2fb46afb700ad6e1123bab4fd1621aa768c5fc77fd09d41293385a8d2`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: **T-069** Fix in `views/zeit/tagesansicht.php` (fehlendes `</form>` / verschachtelte Formulare bei Tagesfeldern Kurzarbeit/Krank).
- Ergebnis: In LOG nicht als DONE vorhanden; HTML enthält verschachtelte Formulare → Fix erforderlich.

### DATEIEN (max. 3)
1) `views/zeit/tagesansicht.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `Kurzarbeit`-Formular korrekt geschlossen, bevor das `Krank (LFZ/KK)`-Formular startet.
- Überflüssiges `</form>` entfernt → keine verschachtelten Formulare mehr.

### AKZEPTANZ
- Browser rendert die Tagesfelder ohne kaputte Form-Layouts; `set_kurzarbeit` und `set_krank` lassen sich unabhängig speichern.

### NEXT
- **T-069:** kurzer Terminal-Flow-Check (Stempeln inkl. Offline-Queue) + Fixes.

## P-2026-01-03-19
- ZIP: `P-2026-01-03-19_smoketest-zeitstempel-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `112207_gesammt.zip` = `d157de44aeeb2b10b4f6960d09c63f3b4c6de7061d9ee3e37487bf6ab859e167`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `bea531d788077e5499538a3f6cc010f0459d4df7005909853dd82b4f12905b1a`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Smoke-Test Spaltencheck nutzt falsche Spalte `zeitbuchung.zeitpunkt` (Schema hat `zeitstempel`).
- Ergebnis: In LOG nicht als DONE vorhanden; aktueller Smoke-Test zeigt ein falsches FAIL → Fix erforderlich.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test Spaltencheck korrigiert: `zeitbuchung.zeitstempel` statt `zeitpunkt`.

### AKZEPTANZ
- `?seite=smoke_test` zeigt den Spaltencheck für `zeitbuchung.zeitstempel` ohne falsches FAIL (sofern Tabelle existiert).

### NEXT
- **T-069:** Terminal-Flow-Check (Kommen/Gehen online + offline Queue) und daraus abgeleitete Micro-Fixes.

## P-2026-01-03-20
- ZIP: `P-2026-01-03-20_terminal-flow-id-guard.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `113120_gesammt.zip` = `ac28a01aa4c78d7fa70ef7df322a5ba57a2834a035c28fc977aa4f0997d01d1b`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `80856f0eddf54a543bb0c86a6a632573080c63dafa586f994d7db47a6cccdf0d`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: `max(1, $mitarbeiterId)` in `ZeitService`/`AuftragszeitService` (kann `mitarbeiterId=0` still zu `1` machen).
- Ergebnis: Vorkommen gefunden; in LOG nicht als DONE vorhanden → Fix erforderlich.

### DATEIEN (max. 3)
1) `services/ZeitService.php`
2) `services/AuftragszeitService.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ZeitService`/`AuftragszeitService`: `mitarbeiterId` wird jetzt strikt validiert (`<=0` → Abbruch), kein stilles „0 → 1“ mehr.
- `AuftragszeitService` Offline-Queue: redundantes doppeltes `typ='haupt'` im Update-SQL entfernt.

### AKZEPTANZ
- Aufruf mit `mitarbeiterId=0` erzeugt keine Buchung (liefert `null`) statt fälschlich für Mitarbeiter **1** zu buchen.
- Offline-Queue-Update für Hauptaufträge enthält nur einmal `typ='haupt'`.

### NEXT
- **T-069:** Terminal-Status/Queue-Speicherort konsistent machen (wenn Offline-DB aktiv ist) + kurzer Offline-UI-Check (erlaubte Funktionen).

### Aktueller Status (2026-01-03)

- **Zuletzt erledigt:** Guard gegen falsche Mitarbeiter-ID bei Terminal-Funktionen (kein „0 → 1“ mehr in Zeit-/Auftragszeiten) + redundantes Offline-Queue-SQL bereinigt.
- **Nächster geplanter Schritt:** T-069 Terminal-Status/Queue-Speicherort konsistent machen + kurzer Offline-UI-Check.
## P-2026-01-03-21
- ZIP: `P-2026-01-03-21_terminal-queue-speicherort-konsistent.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `113841_gesammt.zip` = `a555a12c45a01964c0364814b30c858a27f05e457d50c180b719a3157ef568ae`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `81776a3bf98267813aaf59b1fbc6467edc729aa2b093a8a0e34894df9818640f`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal-Status/Health zeigt `queue_speicherort` inkonsistent zur tatsächlichen Queue-DB (OfflineQueueManager priorisiert Offline-DB, Terminal zeigte bei erreichbarer Haupt-DB dennoch `haupt`).
- Ergebnis: In LOG nicht als DONE vorhanden; Inkonsistenz vorhanden → Fix erforderlich.

### DATEIEN (max. 3)
1) `public/terminal.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `public/terminal.php`: Queue-Speicherort/Status an OfflineQueueManager-Logik angeglichen:
  - Wenn `offline_db` verfügbar ist → `queue_speicherort='offline'` (auch wenn Haupt-DB online ist).
  - Health (`aktion=health`) und Session-Queue-Status verwenden die gleiche Priorität.
- Dadurch passen `queue_speicherort` und Queue-Zähler/Queue-Verarbeitung auf dieselbe DB.

### AKZEPTANZ
- Bei `offline_db.enabled=true` und erreichbarer Offline-DB liefert `terminal.php?aktion=health` `queue_speicherort="offline"` (unabhängig von `hauptdb_verfuegbar`).
- Terminal-Statusbox zeigt denselben Speicherort wie die Queue-Zähler.

### NEXT
- **T-069:** Offline-UI-Check (wenn Haupt-DB down): erlaubte/gesperrte Aktionen sauber führen (Kommen/Gehen/Logout vs. Urlaub/Aufträge) + weitere Micro-Fixes.

## P-2026-01-03-22
- ZIP: `P-2026-01-03-22_terminal-nebenauftrag-stop-offline-ui.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `114639_gesammt.zip` = `43f195cb6ba9146cbf974816bac8a373d8bbadcc6197cf8bd5084e223919dfb6`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `3d348c7bcc41df9c4275ef23b868478c3655d0e5f9bf5dd74b3b0238654135e4`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal **Nebenauftrag stoppen** muss bei **Haupt-DB offline** nutzbar bleiben (kein Fatal durch DB-Query; UI darf nicht fälschlich "kein Nebenauftrag" behaupten).
- Ergebnis: In LOG nicht als DONE vorhanden; `nebenauftragStoppenForm()` lädt laufende Nebenaufträge ohne Try/Catch → Fatal bei DB down; UI blockt Stop bei leerer Liste.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `views/terminal/start.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `TerminalController::nebenauftragStoppenForm()` lädt laufende Nebenaufträge **nur online**; offline wird auf manuelles Stoppen per Auftragscode umgeschaltet.
- `TerminalController::nebenauftragStoppen()` verlangt bei Offline-Modus einen Auftragscode (oder eine ID), damit nicht "irgendein" Nebenauftrag gestoppt wird.
- `views/terminal/start.php`: Nebenauftrag-Stop zeigt im Offline-Modus ein manuelles Formular (Auftragscode + Status) statt "Es läuft aktuell kein Nebenauftrag".

### AKZEPTANZ
- Wenn `hauptdb_verfuegbar=false`, rendert `terminal.php?aktion=nebenauftrag_stoppen` ohne Fehler und zeigt einen Hinweis "Hauptdatenbank offline" sowie ein Stop-Formular mit Pflichtfeld Auftragscode.
- Offline-POST ohne Auftragscode (und ohne ID) liefert eine saubere Fehlermeldung und bleibt auf der Stop-Seite.

### NEXT
- **T-069:** weiterer Offline-UI-Check: Nebenauftrag-Stop/Start, Auftrag-Stop/Start, Kommen/Gehen (Queue), Logout – und daraus weitere Micro-Fixes.

## P-2026-01-03-23
- ZIP: `P-2026-01-03-23_terminal-nebenauftrag-start-heuteuebersicht-fix.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `115837_gesammt.zip` = `bfe205ced79662c9c8f0a3c93daf5b32b7e2d03dad5a64f3488a50fc96285d1b`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `e695cf64877f1f26e788dd3a2f051e3ffb72ef274b6d8e7a835536459e8e5d72`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: `TerminalController` ruft nicht existierende Methode `ZeitService::holeHeutigeZeitUebersicht()` auf (Fatal bei `aktion=nebenauftrag_starten` / `aktion=nebenauftrag_stoppen`).
- Ergebnis: Vorkommen gefunden; in LOG nicht als DONE vorhanden → Fix erforderlich.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `TerminalController`: Nebenauftrag-Formulare nutzen jetzt die interne Helper-Methode `holeHeutigeZeitUebersicht()` (statt nicht existierender ZeitService-Methode).
- `nebenauftragStartenForm()`: Laufende Aufträge werden **nur online** geladen (offline → leere Liste), um unnötige DB-Fehler zu vermeiden.

### AKZEPTANZ
- `terminal.php?aktion=nebenauftrag_starten` rendert ohne Fatal-Error.
- Bei Haupt-DB offline rendert `nebenauftrag_starten` weiterhin (laufende Aufträge bleiben leer; Übersicht bleibt verfügbar).

### NEXT
- **T-069:** Terminal-Flow-Check Kommen/Gehen (online + offline Queue) inkl. Statusbox/Fehlermeldungen und daraus Micro-Fixes.

## P-2026-01-03-24
- ZIP: `P-2026-01-03-24_terminal-uebersicht-offline-gate.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `121711_gesammt.zip` = `9b2d90b8ea898f5ecc8213be51a706df0a156152976e8e811349710c02156640`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `3072c4e8095769abfda71ef1f0138f98d401c8fd835c24a885827d64d1f49f93`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Startscreen/Übersicht (heute) lädt Zeitbuchungen + laufende Aufträge auch dann, wenn die Haupt-DB offline ist (führt zu DB-Errors; Offline-Modus soll Übersichten sperren).
- Ergebnis: In TerminalController (`start/kommen/gehen`) wird die Übersicht immer geladen; View zeigt offline fälschlich „Keine Buchungen“.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `views/terminal/start.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `TerminalController` (`start/kommen/gehen`): Übersicht + laufende Aufträge werden **nur online** geladen; offline wird ein klarer Hinweis gesetzt und das Datum auf „today“ gefüllt.
- `views/terminal/start.php`: Übersicht zeigt bei `hauptdbOk!==true` einen Offline-Hinweis (und optional „Letzter Auftrag (lokal)“) statt leere Listen/„Keine Buchungen“.

### AKZEPTANZ
- Wenn `hauptdb_verfuegbar=false`, rendert `terminal.php?aktion=start` ohne DB-Fehler; die Übersicht zeigt „Hauptdatenbank offline – Übersicht ist nur online verfügbar.“
- Nach `aktion=kommen`/`aktion=gehen` im Offline-Fall bleibt der Startscreen stabil (kein DB-Fatal), Kommen/Gehen werden weiterhin akzeptiert (Queue).

### NEXT
- **T-069:** Auftrag starten/stoppen: Offline-Regeln prüfen (erlaubt, aber UI/Queries dürfen nicht crashen) + ggf. gleiche Online-Gates / Offline-Formulare ergänzen.

## P-2026-01-03-25
- ZIP: `P-2026-01-03-25_terminal-offline-login-hide-logout-cache-clear.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `124154_gesammt.zip` = `52ec68a5020ee71ea96dac45a12eed351dd4bac777b469a1d838b3e5eee8bb7b`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `2b3f6dcbc29d85c153b2c4f6e66da1373793f6696c985d6d7be7073204d4f1b0`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal-Startscreen bietet bei Haupt-DB offline weiterhin Login-Formular an (führt zu unnötigen Login-Versuchen); außerdem bleiben gecachte Mitarbeiter-Namen in der Session nach Logout stehen.
- Ergebnis: kein DONE-Eintrag vorhanden; Anpassung nötig.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `views/terminal/start.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `TerminalController::logout()` entfernt zusätzlich die gecachten Mitarbeiter-Namen aus der Session.
- `views/terminal/start.php`: Wenn **Hauptdatenbank offline** und **kein Mitarbeiter angemeldet**, wird das Login-Formular ausgeblendet und stattdessen ein klarer Hinweis angezeigt.

### AKZEPTANZ
- Bei `hauptdb_verfuegbar=false` zeigt `terminal.php?aktion=start` ohne Login den Hinweis „Hauptdatenbank offline – Anmeldung ist aktuell nicht möglich…“ und **kein** Login-Formular.
- Logout entfernt `terminal_mitarbeiter_id` **und** `terminal_mitarbeiter_vorname/nachname`.

### NEXT
- **T-069:** Auftrag starten/stoppen + Nebenauftrag Start/Stop (online + offline Queue) praktisch testen; daraus weitere Micro-Fixes ableiten.

## P-2026-01-03-26
- ZIP: `P-2026-01-03-26_terminal-auftrag-stop-arg-fix.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `125133_gesammt.zip` = `64587de48346f6e733f592a2ce45a5a737833a1f6f4647368d8b8ef5b2ad16d3`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `c5460b86ae921b082006234bb284ed84a648a5733adabc29054c35f85182b28b`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal **Hauptauftrag stoppen** wirft `ArgumentCountError` (zu viele Parameter bei `AuftragszeitService::stoppeAuftrag()`).
- Ergebnis: Vorkommen im `TerminalController` gefunden; in LOG nicht als DONE markiert → Fix nötig.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `TerminalController::auftragStoppen()` ruft `AuftragszeitService::stoppeAuftrag()` wieder mit korrekter Parameteranzahl auf (kein zusätzlicher Typ-Parameter).
- Bug als **B-023** im SNAPSHOT dokumentiert und als DONE markiert.

### AKZEPTANZ
- `terminal.php?aktion=auftrag_stoppen` (POST) beendet einen laufenden Hauptauftrag ohne `ArgumentCountError` und zeigt eine Erfolgsmeldung.

### NEXT
- **T-069:** Terminal-Flow praktisch testen: Kommen/Gehen (online + offline Queue) + Auftrag Start/Stop (online + offline Queue) und daraus weitere Micro-Fixes ableiten.

## P-2026-01-03-27
- ZIP: `P-2026-01-03-27_betriebsferien-kein-doppelt-arbeiten.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `125836_gesammt.zip` = `05e1f1dd7471f9bb8c545be39b777927eea97823332a5dcede21ab0bb3f15362`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a56ec79127bd3dd2be4521bd3d870bd064c0709e186467b189ca08094d83498e`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Monatsübersicht/PDF zählt an Betriebsferien-Wochentagen 8h Urlaub **zusätzlich** zur Arbeitszeit, wenn gearbeitet wurde.
- Ergebnis: Logik in `ReportService::komplettiereMonatsraster()` setzt Betriebsferien-Urlaub unabhängig von Arbeitszeit → Fix nötig.

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ReportService::komplettiereMonatsraster()` erkennt „hat gearbeitet“ (Arbeitszeit > 0 oder Kommen/Gehen gesetzt) und setzt in diesem Fall `kennzeichen_urlaub=0` sowie `urlaub_stunden=0.00` für Betriebsferien-Tage.
- Tagestyp bleibt als Marker `Betriebsferien` erhalten (keine zusätzliche Urlaubszeit).
- Bug als **B-024** im SNAPSHOT dokumentiert und als DONE markiert.

### AKZEPTANZ
- Betriebsferien (Mo-Fr), **ohne** Arbeitszeit: `urlaub_stunden=8.00` und `kennzeichen_urlaub=1` (wie Urlaub).
- Betriebsferien (Mo-Fr), **mit** Arbeitszeit (Kommen/Gehen oder >0h): `urlaub_stunden=0.00` und `kennzeichen_urlaub=0` → Ist-Summe zählt nur Arbeitszeit.

### NEXT
- **T-069:** Terminal-Flow praktisch testen: Kommen/Gehen (online + offline Queue) + Auftrag Start/Stop (online + offline Queue) und daraus weitere Micro-Fixes ableiten.

## P-2026-01-03-28
- ZIP: `P-2026-01-03-28_betriebsferien-urlaubssaldo-skip-workdays.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `130703_gesammt.zip` = `476488ec2cc21ab5f7c3e5e322f5f6a2819e5bcf647cfd38a3bf1c5ef0d3f30f`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `33804f3220a59342f5777f3e5567bb19b4166e770e7dfc6fa2eda8f2e4235d78`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Urlaubssaldo zählt Betriebsferien-Tage als „genommenen Urlaub“, obwohl an diesen Tagen gearbeitet wurde (oder bereits andere Kennzeichen wie krank gesetzt waren).
- Ergebnis: `UrlaubService::berechneUrlaubssaldoFuerJahr()` addiert alle Betriebsferien-Arbeitstage pauschal auf `genommen` → Fix nötig.

### DATEIEN (max. 3)
1) `services/UrlaubService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `UrlaubService::berechneUrlaubssaldoFuerJahr()` lädt die Jahres-Tageswerte und baut ein Skip-Set: Wenn an einem Betriebsferien-Tag gearbeitet wurde (Arbeitszeit > 0 oder Kommen/Gehen gesetzt) **oder** andere Kennzeichen (z. B. krank) gesetzt sind, wird dieser Tag **nicht** zusätzlich als Betriebsferien-Urlaub gezählt.
- Bug als **B-025** im SNAPSHOT dokumentiert und als DONE markiert.

### AKZEPTANZ
- Betriebsferien (Mo-Fr), **ohne** Arbeitszeit und **ohne** andere Kennzeichen: Urlaubssaldo `genommen` enthält diesen Tag (Zwangsurlaub).
- Betriebsferien (Mo-Fr), **mit** Arbeitszeit (Kommen/Gehen oder >0h): Urlaubssaldo zählt diesen Tag **nicht** zusätzlich als Urlaub.

### NEXT
- **T-069:** Terminal-Flow praktisch testen: Kommen/Gehen (online + offline Queue) + Auftrag Start/Stop (online + offline Queue) und daraus weitere Micro-Fixes ableiten.

## P-2026-01-03-29
- ZIP: `P-2026-01-03-29_monatsuebersicht-manuell-rot.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `131426_gesammt.zip` = `393ea4990dcf4e2899777c48a2df998d28d57709ba20f46073940149e8bda896`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `5ed577604c263c3f3e68afb47cd25ce3fa8cff0d078845fa861da3e4b1a1d208`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Monatsübersicht markiert manuell geänderte Zeiten/Zeilen nicht sichtbar ("rot hinterlegt" fehlt).
- Ergebnis: keine DONE-Markierung / kein Styling in `views/report/monatsuebersicht.php` vorhanden → Fix nötig.

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `views/report/monatsuebersicht.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ReportService::holeMonatsdatenFuerMitarbeiter()` liefert `felder_manuell_geaendert` je Tag mit aus.
- Monatsübersicht: Zeilen mit `felder_manuell_geaendert=1` werden rot hinterlegt (Betriebsferien bleiben gelb, manuell hat Vorrang).
- Bug als **B-026** im SNAPSHOT dokumentiert und als DONE markiert.

### AKZEPTANZ
- Tage mit manuell geänderten Feldern (`tageswerte_mitarbeiter.felder_manuell_geaendert=1`) erscheinen in der Monatsübersicht deutlich rot hinterlegt.
- Normale Tage bleiben unverändert; Betriebsferien bleiben gelb, sofern nicht manuell.

### NEXT
- **T-070:** Gleiche Markierung "manuell" auch im Monats-PDF umsetzen (rote Hinterlegung im PDF für manuell geänderte Zeiten).

## P-2026-01-03-30
- ZIP: `P-2026-01-03-30_monatspdf-manuell-rot.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `132303_gesammt.zip` = `da92edaa006db9daeb445fd0560a42048e7fb24807ba84f8d5978394818ddd9d`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `5e9e7a9f0562c76894d838e6e60974a7bb707149c92f98f97d0e66a5481fe864`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Monatsreport-PDF markiert manuell geänderte Zeiten/Tage nicht sichtbar (rot hinterlegt fehlt).
- Ergebnis: In `services/PDFService.php` wurde bisher keine Zeilen-Hinterlegung basierend auf `felder_manuell_geaendert` vorgenommen → Fix nötig.

### DATEIEN (max. 3)
1) `services/PDFService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monats-PDF: Zeilen mit `felder_manuell_geaendert=1` werden im Tabellenbereich **hellrot hinterlegt** (Hinterlegung wird **vor** den Gitterlinien gezeichnet, damit Linien sichtbar bleiben).
- Bug als **B-027** im SNAPSHOT dokumentiert und als DONE markiert.

### AKZEPTANZ
- Tage mit `tageswerte_mitarbeiter.felder_manuell_geaendert=1` erscheinen im Monats-PDF deutlich rot hinterlegt.
- Nicht-manuelle Tage bleiben unverändert.

### NEXT
- **T-069:** Terminal-Kernflow praktisch testen: Kommen/Gehen (online + offline Queue) sowie Auftrag Start/Stop (online + offline Queue) – und daraus konkrete Bugfixes ableiten.

## P-2026-01-03-31
- ZIP: `P-2026-01-03-31_monatspdf-an-ab-headings.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `133148_gesammt.zip` = `1b329427857e99f18b8a106fc7c9245af4a8ad7e4969eba75fcf110e995e1dab`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `90b86cabc0e2fb1177618d52052063bf7e879ea745d990e5375c2c877644fda8`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Monats-PDF: Spaltenüberschriften kurz wie in Vorlage (An/Ab statt Kommen/Gehen).
- Ergebnis: In `services/PDFService.php` standen in der Kopfzeile noch `Kommen/Gehen/Ko.Korr/Ge.Korr` → Anpassung nötig.

### DATEIEN (max. 3)
1) `services/PDFService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monats-PDF: Spaltenüberschriften auf `An/Ab/An.Korr/Ab.Korr` umgestellt (Layout/Spaltenbreiten unverändert).
- SNAPSHOT aktualisiert (Letzter Patch, T-074 Teil 1 als DONE dokumentiert).

### AKZEPTANZ
- Monats-PDF zeigt in der Tabelle die Überschriften `An`, `Ab`, `An.Korr`, `Ab.Korr`.

### NEXT
- **T-069:** Terminal-Kernflow praktisch testen: Kommen/Gehen (online + offline Queue) sowie Auftrag Start/Stop (online + offline Queue) – und daraus konkrete Bugfixes ableiten.


## P-2026-01-03-32
- ZIP: `P-2026-01-03-32_terminal-health-letzter-fehler.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `133855_gesammt.zip` = `f4c478ee99cb63812878fa8cc234a65fcee0c3e6bf8c2e8bad58fa7712ba5b2b`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `0579cf1a5e4066a339719c461e8a84d58f34b2a1c9736b5b9bed25ce46f5fe56`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: `terminal.php?aktion=health` soll Metadaten zum **letzten Queue-Fehler** liefern (für Smoke-Tests/Monitoring), ohne SQL-Inhalte zu leaken.
- Ergebnis: Health-JSON enthielt bisher nur Zähler (`queue_offen/queue_fehler`) → Ergänzung nötig.

### DATEIEN (max. 3)
1) `public/terminal.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Health-Endpoint liefert zusätzlich:
  - `queue_letzter_fehler_id` (int|null)
  - `queue_letzter_fehler_zeit` (string|null)
- Es werden **keine** SQL-Befehle im Health-JSON ausgegeben.

### AKZEPTANZ
- `GET terminal.php?aktion=health` gibt JSON mit `queue_letzter_fehler_id` und `queue_letzter_fehler_zeit` zurück.
- Wenn kein Fehler-Eintrag existiert, bleiben beide Werte `null`.

### NEXT
- **T-069:** Terminal-Kernflow praktisch testen: Kommen/Gehen (online + offline Queue) sowie Auftrag Start/Stop (online + offline Queue) – und daraus konkrete Bugfixes ableiten.


## P-2026-01-03-33
- ZIP: `P-2026-01-03-33_terminal-debounce-kommen-gehen.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `135732_gesammt.zip` = `d36ae6a91b6cc7bf52506753e4d04550d558742f5486ee8ff2351487a6e8e0eb`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `3cb3685c6cc0f03cc9005fb4cc52c4b8f016a2a3babfd71c792b67967c324937`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Schutz gegen Doppelbuchungen bei `Kommen/Gehen` (Doppelklick oder Doppel-Scan innerhalb weniger Sekunden).
- Ergebnis: Kein De-Bounce im `TerminalController` vorhanden → Doppelbuchungen möglich.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal: Session-De-Bounce für `Kommen/Gehen` ergänzt:
  - merkt letzte Buchung (Typ + Uhrzeit + Timestamp)
  - ignoriert die gleiche Aktion innerhalb von **5 Sekunden** (mit Hinweistext)

### AKZEPTANZ
- Doppelklick/Doppel-Scan auf `Kommen` oder `Gehen` innerhalb von 5 Sekunden erzeugt **keine** zweite Buchung.
- Die zweite Auslösung zeigt eine Meldung „bereits gebucht … (ignoriert)“.

### NEXT
- **T-069:** Terminal-Kernflow praktisch testen: Auftrag Start/Stop (online + offline Queue) und daraus konkrete Bugfixes ableiten.


## P-2026-01-03-34
- ZIP: `P-2026-01-03-34_monatsuebersicht-kuerzel-kommentar.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `140638_gesammt.zip` = `b11f67ec3ab7a08bc004cf65a0fde0b1b5a339b0d8ee40119c32e4b230789a3e`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `938b89822062b22d04b4b91ae2a9bb080a8b93074e60cc7eecfbc98b6dfb5d8c`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Monatsübersicht: Kürzel/Begründung aus `tageswerte_mitarbeiter.kommentar` anzeigen.
- Ergebnis: Feld wurde im Report-Service nicht an die View durchgereicht, und in der Monatsübersicht fehlte eine Spalte dafür.

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `views/report/monatsuebersicht.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ReportService`: reicht `kommentar` (trim/leer → `null`) pro Tag an die View weiter (auch Default/Fallback-Raster).
- Monatsübersicht: neue Spalte **Kürzel** (zeigt Kommentar oder `-`).

### AKZEPTANZ
- In der Monatsübersicht gibt es eine Spalte **Kürzel**.
- Wenn `tageswerte_mitarbeiter.kommentar` für den Tag gesetzt ist, wird der Wert angezeigt, sonst `-`.

### NEXT
- **T-069:** Terminal-Kernflow praktisch testen: Auftrag Start/Stop (online + offline Queue) und daraus konkrete Bugfixes ableiten.


## P-2026-01-03-35
- ZIP: `P-2026-01-03-35_monatsuebersicht-rot-nur-zeitkorrektur.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `141448_gesammt.zip` = `be0a229692d5bcf326973ce942198699721ece77708f04a8e112a5fdf7b36817`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `4fc00129c7fc2b8b350ac8451263d9728d3be4b2de5ba3929d3f93f965d8f913`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Rot-Markierung in Monatsübersicht soll **nicht** durch Kurzarbeit/Urlaub etc ausgelöst werden, sondern nur durch manuelle Zeitkorrekturen (Kommen/Gehen).
- Ergebnis: Monatsübersicht nutzte `felder_manuell_geaendert` (Tageskennzeichen) statt Zeitbuchungs-Änderungen.

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `views/report/monatsuebersicht.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ReportService`: berechnet pro Tag `zeit_manuell_geaendert` anhand `zeitbuchung.manuell_geaendert` (Monat) und reicht das Feld an Monatsübersicht durch.
- Monatsübersicht: Rot-Markierung basiert jetzt auf `zeit_manuell_geaendert` (manuelle Kommen/Gehen-Korrektur), nicht mehr auf `felder_manuell_geaendert`.

### AKZEPTANZ
- Wenn ein Tag nur manuell als Kurzarbeit/Urlaub/Krank etc markiert wurde, bleibt die Monatsübersicht **nicht rot**.
- Wenn an einem Tag mindestens eine Zeitbuchung manuell geändert wurde, werden die betroffenen Zellen wie bisher **rot** hinterlegt.

### NEXT
- **B-029 (Teil 2):** Monatsreport-PDF: dieselbe Umstellung (`zeit_manuell_geaendert` statt `felder_manuell_geaendert`).
- Danach: Kurzarbeit in der Monatsübersicht wie Betriebsferien als 8h behandeln (ohne Doppelzählung bei Arbeit).


## P-2026-01-03-36
- ZIP: `P-2026-01-03-36_monatspdf-rot-nur-zeitkorrektur.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `143532_gesammt.zip` = `06ef5bfadb7c8ee0928a05d98e0b95ef62646e2988b736597dcd51bc9b5c05b1`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `bc684f136b4ac0b91ced0515a82ada45492c5cf37c3f02fdfded40cace3d0160`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Rot-Markierung im Monats-PDF soll **nicht** durch Kurzarbeit/Urlaub/Krank etc ausgelöst werden, sondern nur durch manuelle Zeitkorrekturen (Kommen/Gehen).
- Ergebnis: `PDFService` nutzte bisher `felder_manuell_geaendert` (Tageskennzeichen) als Rot-Trigger.

### DATEIEN (max. 3)
1) `services/PDFService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monats-PDF: Rot-Hinterlegung basiert jetzt auf `zeit_manuell_geaendert` (manuelle Kommen/Gehen-Korrektur) statt `felder_manuell_geaendert`.

### AKZEPTANZ
- Wenn ein Tag nur manuell als Kurzarbeit/Urlaub/Krank etc markiert wurde, bleibt die PDF-Zeile **nicht rot**.
- Wenn an einem Tag mindestens eine Zeitbuchung manuell geändert wurde, ist die Zeile wie gewohnt **rot** hinterlegt.

### NEXT
- Kurzarbeit in der Monatsübersicht wie Betriebsferien als 8h behandeln (ohne Doppelzählung bei Arbeit).


## P-2026-01-03-37
- ZIP: `P-2026-01-03-37_kurzarbeit-8h-monatsuebersicht.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `143947_gesammt.zip` = `d0b389042b3801956e7203f2bdda97b22acbbdb3d2cc1e498c16b63d03ed1034`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `e34674481ea43fb53c2c9409731f0daeb3355f8c2970e18ec2d76bbb0f572fd2`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Kurzarbeit-Volltag soll in Monatsübersicht/PDF wie Betriebsferien wirken (Default = Tages-Soll/8h), aber nicht zusätzlich, wenn am gleichen Tag Arbeitszeit gebucht wurde.
- Ergebnis: Kurzarbeit reduzierte das Soll, auch wenn am Tag gearbeitet wurde; außerdem fehlte ein Default für „Volltag“, wenn nur Kennzeichen gesetzt war.

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ReportService`: neue Normalisierung „Kurzarbeit-Volltag wie Betriebsferien“ nach Plan/Override-Anwendung:
  - Default-Volltag = Tages-Soll (Fallback 8h), wenn Kennzeichen gesetzt ist, aber keine Stunden vorliegen.
  - Wenn an einem Volltag tatsächlich gearbeitet wurde, wird Kurzarbeit für diesen Tag auf 0 gesetzt (keine Soll-Reduktion, kein Tagestyp „Kurzarbeit“).

### AKZEPTANZ
- Kurzarbeit-Volltag ohne Arbeitszeit reduziert das Soll (Saldo neutral) und ist im Tagestyp sichtbar.
- Wenn an einem Kurzarbeit-Volltag gearbeitet wurde, wirkt Kurzarbeit nicht zusätzlich (keine doppelte Berücksichtigung).

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) + Bugfixes basierend auf den Ergebnissen.


## P-2026-01-03-38
- ZIP: `P-2026-01-03-38_monatsuebersicht-spalte-kurzarbeit.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `145058_gesammt.zip` = `2e94801433d7d15b562bdb5c010d446ce61da6ed6361c7f2c3ec1f5875f9604e`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `faa1cd287386022c70daa8eb96882f31a927c9c0cfc099f6227f835ef21a0e61`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Kurzarbeit (Volltag = Tages-Soll/8h) soll in der Monatsübersicht sichtbar sein, ohne die IST-Berechnung zu verfälschen.
- Ergebnis: `kurzarbeit_stunden` wird im Backend gesetzt/normalisiert, aber in `views/report/monatsuebersicht.php` bisher nicht angezeigt.

### DATEIEN (max. 3)
1) `views/report/monatsuebersicht.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monatsübersicht: neue Spalte **Kurzarbeit** ergänzt (zeigt `kurzarbeit_stunden`, bei 0/leer als `-`).

### AKZEPTANZ
- Kurzarbeit-Volltag ohne Arbeitszeit zeigt in der Monatsübersicht in der Spalte **Kurzarbeit** den Wert `8.00` (bzw. Tages-Soll, falls vorhanden).
- Wenn an einem Kurzarbeit-Volltag gearbeitet wurde, bleibt die Spalte **Kurzarbeit** leer/`-` (keine Doppelzählung).

### NEXT
- **T-069:** weiterer Smoke-Test (Terminal + Report) und Bugfixes basierend auf realen Klickpfaden.


## P-2026-01-03-39
- ZIP: `P-2026-01-03-39_monatspdf-kuerzel-spalte.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `152204_gesammt.zip` = `b832930269152362cd226885b02fb96fb4232bfaba68e63d8d9c0ce2bf54b5e8`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `356636e50d1336e499cc56177454912a20b6be1500086768838036d4df8ee943`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Monats-PDF soll (analog Monatsübersicht) ein Kürzel/Kommentar pro Tag anzeigen.
- Ergebnis: 2. Spalte war bisher leer und wurde nur indirekt für „BF“ genutzt.

### DATEIEN (max. 3)
1) `services/PDFService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monats-PDF: 2. Spalte heißt jetzt **Kürzel**.
- Befüllung: `kommentar` (max. 6 Zeichen). Fallback: wenn leer und `ist_betriebsferien=1`, wird **BF** gesetzt.

### AKZEPTANZ
- Wenn im Tagesdatensatz ein Kürzel/Kommentar vorhanden ist, wird es im Monats-PDF angezeigt.
- Wenn kein Kürzel vorhanden ist, aber der Tag Betriebsferien ist, steht im Monats-PDF **BF**.

### NEXT
- **T-069:** weiterer Smoke-Test (Terminal + Report) und Bugfixes basierend auf realen Klickpfaden.

## P-2026-01-03-40
- ZIP: `P-2026-01-03-40_terminal-login-fuer-alle-aktiven.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `172636_gesammt.zip` = `5f0970ad81d81988496b04b88e5557e34e3fa7c7f0e7e7803b4c27f80df28032`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `d07cb5fc3c6e9a10c1df4c5f47d792b53f18bbd5aab76023ca0fb55e15a8527b`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal-Login soll für normale Mitarbeiter funktionieren, auch wenn `ist_login_berechtigt` (Backend-Login) nicht gesetzt ist.
- Ergebnis: `TerminalController` prüfte bei Login und beim Laden des angemeldeten Terminal-Mitarbeiters `ist_login_berechtigt = 1` → blockiert Terminal-Login für normale Mitarbeiter.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal-Login (RFID/Mitarbeiter-ID) und Session-Reload prüfen nur noch `aktiv = 1` (kein `ist_login_berechtigt` mehr).
- Fehlermeldung angepasst: „Mitarbeiter nicht gefunden oder inaktiv.“

### AKZEPTANZ
- Ein aktiver Mitarbeiter kann sich am Terminal per RFID oder Mitarbeiter-ID anmelden, auch wenn „Login über Benutzername/E-Mail erlaubt“ im Mitarbeiterprofil nicht gesetzt ist.

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) + Bugfixes basierend auf den Ergebnissen.

## P-2026-01-03-41
- ZIP: `P-2026-01-03-41_terminal-kommen-gehen-status.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `175833_gesammt.zip` = `c385906f2a51b74527c50c14f5d93c12dc9da9ef159670a819a22e6945f63e77`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a542d4c4c268c533079b119e5134ec88e7f5ac5d3f56d928283fea2cb6a05e62`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal soll nach Login prüfen, ob Mitarbeiter bereits „Kommen“ gebucht hat; entsprechend Buttons ein-/ausblenden. „Auftrag starten“ nur nach „Kommen“.
- Ergebnis: Startscreen zeigte bisher immer beide Buttons (Kommen/Gehen) und erlaubte Auftrag-Start unabhängig von Anwesenheit; Server-seitig fehlten Guards.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `views/terminal/start.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal-Startscreen: „Kommen“ nur wenn nicht anwesend; „Gehen“ nur wenn anwesend.
- Auftrag-Start (Hauptauftrag + Nebenauftrag) ist server-seitig geblockt, solange Mitarbeiter nicht anwesend ist.
- Session-Fallback `terminal_anwesend` für den Offline-Fall (Status wird bei Kommen/Gehen gesetzt).

### NEXT
- Personalnummer/Mitarbeiter-ID-Strategie fürs Terminal (z. B. Personalnummer zusätzlich zu DB-ID) + UI/DB-Feld ergänzen.

## P-2026-01-03-42
- ZIP: `P-2026-01-03-42_terminal-hauptmenue-anwesenheit.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `182001_gesammt.zip` = `2af8da2b788e3de1a953caf223c3ad973ee6b6be8270f2a6d0d64cd0414c7cd0`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `8a6e74f4de649312d4c5e5fc4f9330eeabf8563840398bd28fc13976c62bf534`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal soll nach Login zuerst prüfen, ob Mitarbeiter heute bereits „Kommen“ gebucht hat. Wenn **nicht anwesend**, sollen im Hauptmenü **nur** „Kommen“ (+ optional „Urlaub beantragen“) sichtbar sein – kein „Gehen“, kein Auftrag-Start.
- Ergebnis: In **P-2026-01-03-41** wurden Kommen/Gehen bereits abhängig vom Anwesenheitsstatus ein-/ausgeblendet und Auftrag-Start serverseitig geblockt; jedoch waren im Menü weiterhin Auftrag-/Nebenauftrag-Aktionen sichtbar (teilweise disabled) und im Controller fehlten die dafür referenzierten Helper-Methoden → Ziel noch nicht vollständig erreicht.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `views/terminal/start.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal-Startscreen: Wenn Mitarbeiter **nicht anwesend** ist, werden im Hauptmenü nur „Kommen“ (+ optional „Urlaub beantragen“ und „Übersicht“) angeboten; Auftrag-/Nebenauftrag-Buttons werden vollständig ausgeblendet.
- TerminalController: fehlende Helper ergänzt (`setzeTerminalAnwesenheitStatus`, `istTerminalMitarbeiterHeuteAnwesend`), inkl. Online-Prüfung (Kommen>Gehen) und Session-Fallback für Offline.

### AKZEPTANZ
- Nach Terminal-Login sieht ein Mitarbeiter, der heute noch nicht „Kommen“ gebucht hat, **nur** „Kommen“ (und optional „Urlaub beantragen“). Nach „Kommen“ erscheinen „Gehen“ sowie die Auftragsfunktionen.

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) + Bugfixes basierend auf den Ergebnissen.

## P-2026-01-03-43
- ZIP: `P-2026-01-03-43_terminal-anwesenheits-guards.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `183841_gesammt.zip` = `b45b0a750ba8313da6ac1488e1eb33290972078e25c935122cac51a062bd992e`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `90347665e177c86014d9d1e8e0764250d2f85ea44a540fe1e5a4bd6065160eb0`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Terminal soll mutierende Stop-Aktionen (Haupt-/Nebenauftrag stoppen) sowie „Kommen“ serverseitig an den Anwesenheitsstatus koppeln (auch bei Direkt-URL).
- Ergebnis: Stop-Handler hatten keine Anwesenheitsprüfung; „Kommen“ konnte auch bei bestehender Anwesenheit ausgelöst werden.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `auftragStoppenForm()` + `auftragStoppen()` prüfen nun Anwesenheit (sonst Redirect + Flash-Fehler).
- `nebenauftragStoppenForm()` + `nebenauftragStoppen()` prüfen nun Anwesenheit (sonst Redirect + Flash-Fehler).
- `kommen()` blockt, wenn der Mitarbeiter bereits anwesend ist.

### AKZEPTANZ
- Ein Mitarbeiter, der heute noch nicht „Kommen“ gebucht hat, kann weder Haupt- noch Nebenaufträge stoppen (auch nicht per Direkt-URL).
- „Kommen“ kann nicht doppelt ausgelöst werden, solange der Mitarbeiter anwesend ist.

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) + Bugfixes basierend auf den Ergebnissen.

## P-2026-01-03-44
- ZIP: `P-2026-01-03-44_terminal-nicht-anwesend-nur-kommen-urlaub.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `185358_gesammt.zip` = `4953e338d92c37743a1965a54c8de32b25bfe86544d305cafab38f6e17f1dfae`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `51c91f7e12cf87175aaa69d764add9d3493fb524cdb2bbdfd76eb0a4530f2f67`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Nach Terminal-Login soll bei **Nicht-Anwesenheit** wirklich nur „Kommen“ (+ optional „Urlaub beantragen“) sichtbar sein – keine weiteren Menüpunkte/Übersicht.
- Ergebnis: In **P-2026-01-03-42** wurden Auftrag-/Nebenauftrag-Buttons bereits ausgeblendet; jedoch war weiterhin „Übersicht“ (inkl. Details-Block) sichtbar/aufrufbar → Ziel noch nicht vollständig erfüllt.

### DATEIEN (max. 3)
1) `views/terminal/start.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal-Startscreen zeigt bei **Nicht-Anwesenheit** ausschließlich „Kommen“ (+ optional „Urlaub beantragen“). „Übersicht“/Details werden erst bei Anwesenheit gerendert.

### AKZEPTANZ
- Nach Terminal-Login sieht ein Mitarbeiter ohne heutiges „Kommen“ **nur** „Kommen“ (und ggf. „Urlaub beantragen“) – keine Übersicht/Details und keine Auftragsfunktionen.

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) + Bugfixes basierend auf den Ergebnissen.


## P-2026-01-04-01
- ZIP: `P-2026-01-04-01_mitarbeiter-personalnummer-schema.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `055003_gesammt.zip` = `fc46de4cde162952031782df716a34003f349a33c8d2103d71253c86a0e98469`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `b0bf3565df202d746b6c7ff7a5cdd7c578a03b6c6b160d292f2fcc34fdb7a479`
- SQL (SoT): `sql/01_initial_schema.sql` = `b0d75145e039f3647cd0c9488d775f51b105c3e4654d9d00c5f96a0749c0ddce`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `e62ff69ed0f420ffa4f5a709e89e82f8753f3ac3ac379ce2b2a6752c3c2a8144`

### DUPLICATE-CHECK
- Gesucht: Schema-Änderung „mitarbeiter.personalnummer“ (inkl. Unique-Key) + passende Migration.
- Ergebnis: Kein vorhandenes Migration-Skript/kein Schema-Eintrag für `personalnummer` gefunden → Umsetzung notwendig.

### DATEIEN (max. 3)
1) `sql/01_initial_schema.sql`
2) `sql/11_migration_mitarbeiter_personalnummer.sql`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `mitarbeiter.personalnummer` (VARCHAR(32), NULL) + Unique-Key `uniq_mitarbeiter_personalnummer` im Initial-Schema ergänzt.
- Migration `11_migration_mitarbeiter_personalnummer.sql` hinzugefügt (idempotent via information_schema).

### AKZEPTANZ
- Neue Installationen haben `mitarbeiter.personalnummer` im Schema.
- Bestands-DBs können Migration 11 mehrfach ausführen, ohne Fehler (Spalte/Index wird nur bei Bedarf angelegt).

### NEXT
- **T-076 (Teil 2):** Terminal-Login zusätzlich per Personalnummer ermöglichen (DB-ID bleibt als Fallback) + UI-Label anpassen.


## P-2026-01-04-02
- ZIP: `P-2026-01-04-02_terminal-login-personalnummer.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `055915_gesammt.zip` = `cfd6298afa918b664f896a0ef6aaf52008f7e5a193418cedbdc50beb8e021976`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `efd1bb6819e1c16be2ca54c814bf40943661ca4ffe61ebc895329028b559b4db`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gesucht: **T-076 (Teil 2)** – Terminal-Login zusätzlich per **Personalnummer** ermöglichen (Fallback bleibt Mitarbeiter-ID).
- Ergebnis: Im **SNAPSHOT** als nächster Schritt offen; im **LOG** kein DONE-Eintrag zu „Terminal-Login Personalnummer“ gefunden → Umsetzung notwendig.

### DATEIEN (max. 3)
1) `controller/TerminalController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal-Login akzeptiert jetzt zusätzlich **Personalnummer** (wenn der eingegebene Code rein numerisch ist): Reihenfolge **RFID → Personalnummer → Mitarbeiter-ID**.

### AKZEPTANZ
- Ein Mitarbeiter kann sich am Terminal per **Personalnummer** einloggen (Scan/Eingabe im RFID-Feld); falls keine Personalnummer existiert, funktioniert (bei numerischem Code) weiterhin der Login per **Mitarbeiter-ID**.

### NEXT
- **T-076 (Teil 3a):** Terminal-Startscreen-Text/Label anpassen: „RFID / Personalnummer / ID“.


## P-2026-01-04-03
- ZIP: `P-2026-01-04-03_terminal-startscreen-label-rfid-personalnummer-id.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `060413_gesammt.zip` = `5dbaa4dd35da1d89363e10c46626b7541b3c09d2cb69ccf84b28a9b5b3436999`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ab5c2232ea255ecd35475664b84bdf9844b057511fd357261163dc45171c740e`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gesucht: **T-076 (Teil 3a)** – Terminal-Startscreen-Text/Label anpassen: „RFID / Personalnummer / ID“.
- Ergebnis: Im **SNAPSHOT** als nächster Schritt offen; im **LOG** kein DONE-Eintrag für das Startscreen-Label gefunden → Umsetzung notwendig.

### DATEIEN (max. 3)
1) `views/terminal/start.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Terminal-Login-Maske zeigt jetzt ein einziges Eingabefeld mit Label „RFID / Personalnummer / ID“; Hinweistext angepasst.

### AKZEPTANZ
- Auf dem Terminal-Login-Screen steht „RFID / Personalnummer / ID“, und ein Scan/Eingabe in dieses Feld reicht für Login per RFID, Personalnummer oder ID.

### NEXT
- **T-076 (Teil 3b):** Backend-UI: Personalnummer im Mitarbeiter-Admin pflegbar machen (Formular + Save-Flow).


## P-2026-01-04-04
- ZIP: `P-2026-01-04-04_mitarbeiter-admin-personalnummer-ui.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `060813_gesammt.zip` = `bbd4f89874dba518a4498d9697c1f864310f022952c2c51355ce34ee97adbc64`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `0520a2c9c2e4d4a9cdbb593f05aa4475ff68a54795b14dcf90dc2bb1e3b7be66`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gesucht: **T-076 (Teil 3b)** – Backend-UI: Personalnummer im Mitarbeiter-Admin pflegbar (Formular + Save-Flow).
- Ergebnis: Im **SNAPSHOT** als nächster Schritt offen; im **LOG** kein DONE-Eintrag zur UI/Save-Logik gefunden → Umsetzung notwendig.

### DATEIEN (max. 3)
1) `controller/MitarbeiterAdminController.php`
2) `views/mitarbeiter/formular.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Mitarbeiter-Admin-Formular um Feld **Personalnummer** ergänzt.
- Save-Flow speichert Personalnummer; Duplikate werden mit freundlicher Fehlermeldung abgefangen.

### AKZEPTANZ
- Im Backend (Mitarbeiterverwaltung) kann pro Mitarbeiter eine **Personalnummer** gepflegt werden.
- Bei doppelter Personalnummer erscheint eine Fehlermeldung und es wird nichts gespeichert.

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) + Bugfixes basierend auf den Ergebnissen.


## P-2026-01-04-05
- ZIP: `P-2026-01-04-05_smoke-test-personalnummer-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `061935_gesammt.zip` = `21c062a0176e871780e85dd15ae8218a01dd7f288ebb192fc643ffe2c70d4ad6`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `8fa12b5029e9d1f85224e88a2d2956ee0f5bdcf436dd7fd5c91613632ecc9c99`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gesucht: **T-069 (Teil 1)** – Smoke-Test soll Schema-Mismatches früh erkennen (u. a. neue Spalte `mitarbeiter.personalnummer`).
- Ergebnis: Im **LOG** kein DONE-Eintrag für einen Smoke-Test-Spaltencheck `mitarbeiter.personalnummer` gefunden → Umsetzung notwendig.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test prüft jetzt zusätzlich die Existenz der DB-Spalte `mitarbeiter.personalnummer`.

### AKZEPTANZ
- Auf `?seite=smoke_test` erscheint ein Eintrag „Spalte vorhanden: mitarbeiter.personalnummer“ (OK wenn Schema aktuell ist).

### NEXT
- **T-069:** Smoke-Test der Kernflows (Login, Terminal-Stempeln, Monatsübersicht, PDF) durchführen und die ersten echten Bugfixes aus den Testergebnissen ableiten.


## P-2026-01-04-06
- ZIP: `P-2026-01-04-06_smoke-test-terminal-login-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `062400_gesammt.zip` = `a02d86f216e74850864ab49b40d8e6835232059944fb19851767b6b0ee67d8b1`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `5da748916981e4bf88ec6b60dae673e4d3c6fba530812b090cf487518489937f`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gesucht: **T-069 (Teil 2)** – Smoke-Test: Terminal-Login (RFID/Personalnummer/ID) als lesender Check für schnelle Diagnose.
- Ergebnis: Im **SNAPSHOT/LOG** kein DONE-Eintrag für einen Terminal-Login-Resolver im Smoke-Test gefunden → Umsetzung notwendig.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Auf `?seite=smoke_test` gibt es jetzt einen Abschnitt **„Terminal-Login-Check (RFID / Personalnummer / ID)”**.
- Eingabe eines Codes zeigt, ob (und über welchen Pfad) das Terminal einen **aktiven** Mitarbeiter finden würde; bei inaktiven Treffern werden Hinweise angezeigt.

### AKZEPTANZ
- Smoke-Test zeigt ein Formular „Code“.
- Bei gültigem RFID/Personalnummer/ID eines **aktiven** Mitarbeiters wird „OK“ inkl. Mitarbeiterdaten angezeigt.
- Bei ungültigem Code wird „FAIL“ angezeigt; wenn der Code zu einem **inaktiven** Mitarbeiter gehört, erscheint ein Hinweis.

### NEXT
- **T-069:** Kernflows manuell durchklicken (Login, Terminal-Stempeln online/offline, Monatsübersicht, PDF) und Bugfixes daraus ableiten.

## P-2026-01-04-07
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `063029_gesammt.zip` = 361c01c845d88bdad9cd8a2ce385bb459f8140a7d0d4a907eb61fb689077f27f
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = 2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = 395f6db931e6401996ab1c5798af893c3e7cc6e0d18ebc6edb225f325bedab77
  - SQL (Projekt): `sql/zeiterfassung_aktuell.sql` = c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd
  - SQL (Schema): `sql/01_initial_schema.sql` = 3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920
- **DUPLICATE-CHECK:**
  - Gegen SNAPSHOT/LOG geprüft: **T-069** ist offen. „Smoke-Test: Terminal-Anwesenheit heute“ war noch nicht umgesetzt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  - `controller/SmokeTestController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Smoke-Test erweitert: Bei Terminal-Login-Check wird zusätzlich **Anwesenheit heute** (Kommen/Gehen-Zählung) inkl. erwarteter Menü-Logik + letzte Buchung angezeigt.
- **NEXT:**
  - T-069 weiter: Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF prüfen und daraus Bugfixes ableiten.

## P-2026-01-04-08
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `063752_gesammt.zip` = 4ef14e2feb636bcf4243b88341e741b0cc552d65a53cf7ff4588995f9e43bf05
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = 2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = 836c7671473f06dd1512f0cd5c99f6c6eb415fcb7f83b2d110b157bed6485f5f
  - SQL (Projekt): `sql/zeiterfassung_aktuell.sql` = c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd
  - SQL (Schema): `sql/01_initial_schema.sql` = 3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920
- **DUPLICATE-CHECK:**
  - Gegen SNAPSHOT/LOG geprüft: **B-034** ist als DONE markiert, aber Startscreen zeigte bei Nicht-Anwesenheit weiterhin die Urlaubssaldo-Box → **B-035 neu**, kein Duplicate.
- **DATEIEN (max. 3):**
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-035:** Terminal-Startscreen zeigt bei **Nicht-Anwesenheit** (und ohne geöffnete Urlaub-Form) **keinen** Urlaubssaldo/Status-Block mehr.
- **AKZEPTANZ:**
  - Nach Terminal-Login ohne heutiges „Kommen“: Startscreen zeigt nur Hinweis + Buttons „Kommen“ und (online) „Urlaub beantragen“.
  - Urlaubssaldo/Status-Box erscheint wieder, sobald „Urlaub beantragen“ geöffnet ist oder der Mitarbeiter anwesend ist.
- **NEXT:**
  - T-069 weiter: Kernflows manuell testen und daraus Bugfixes ableiten.

## P-2026-01-04-09
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `064336_gesammt.zip` = 9166bccb60b3a12eefddde7b46e5dba4b6fd516d96c8ad163eb7479a5d750be4
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = 2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = 516c13dfaac47f672222e64f72425183268df559eb78706ac39b739f7385d0c2
  - SQL (Projekt): `sql/zeiterfassung_aktuell.sql` = c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd
  - SQL (Schema): `sql/01_initial_schema.sql` = 3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920
- **DUPLICATE-CHECK:**
  - Gegen SNAPSHOT/LOG geprüft: **T-069** ist offen. Smoke-Test-Mehrdeutigkeitswarnungen für numerische Codes waren noch nicht umgesetzt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  - `controller/SmokeTestController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Smoke-Test erweitert: Terminal-Login-Check zeigt bei **numerischen Codes** eine **Warnung**, wenn der Code auch als RFID/Personalnummer/Mitarbeiter-ID eines **anderen aktiven Mitarbeiters** passen würde, und listet die alternativen Treffer.
- **AKZEPTANZ:**
  - Wenn ein numerischer Code gleichzeitig als Personalnummer von Mitarbeiter A und als Mitarbeiter-ID von Mitarbeiter B existiert, zeigt der Smoke-Test eine Warnung inkl. alternativer Treffer und Hinweis auf die Reihenfolge „RFID → Personalnummer → ID“.
- **NEXT:**
  - T-069 weiter: Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF prüfen und daraus Bugfixes ableiten.

## P-2026-01-04-10
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `065057_gesammt.zip` = f3823065c2e0328d86496398bd54db2ccb6afcf1e4d4379731a6c5aea447f473
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = 2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = 45d27485d74383906df741f41e45ec06e019853b128f93654b72a817d0361fbe
  - SQL (Projekt): `sql/zeiterfassung_aktuell.sql` = c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd
  - SQL (Schema): `sql/01_initial_schema.sql` = 3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920
- **DUPLICATE-CHECK:**
  - Gegen SNAPSHOT/LOG geprüft: Mehrdeutigkeit im Terminal-Login war noch nicht serverseitig geblockt → **B-036 neu**, kein Duplicate.
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - **B-036:** Terminal-Login erkennt **Mehrdeutigkeit** bei numerischen Codes (Personalnummer vs Mitarbeiter-ID) und bricht mit Fehlermeldung ab (kein stilles Einloggen falscher Person).
- **AKZEPTANZ:**
  - Wenn `rfid_code` rein numerisch ist und als **Personalnummer** von Mitarbeiter A sowie als **ID** von Mitarbeiter B existiert, wird der Login **abgebrochen** und der Nutzer bekommt einen Hinweis.
  - Wenn RFID direkt passt, wird wie gewohnt eingeloggt.
- **NEXT:**
  - T-069 weiter: Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF prüfen und daraus Bugfixes ableiten.


## P-2026-01-04-11
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `065638_gesammt.zip` = 9f40d5e732f6c87a7119a8e67378709d36704a90765148cbd7f4db710c53929d
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = 2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = 5606d521ac394c75e1c38d4a99f0130e564ead12547dcf09455d991c8a31c21d
  - SQL (Projekt): `sql/zeiterfassung_aktuell.sql` = c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd
  - SQL (Schema): `sql/01_initial_schema.sql` = 3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920
- **DUPLICATE-CHECK:**
  - Gegen SNAPSHOT/LOG geprüft: Terminal-Login blockt Mehrdeutigkeit seit **P-2026-01-04-10**; Smoke-Test zeigte bis jetzt nur Warnungen → Anpassung ist **neu**, kein Duplicate.
- **DATEIEN (max. 3):**
  - `controller/SmokeTestController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Smoke-Test Terminal-Login emuliert jetzt den **Mehrdeutigkeits-Abbruch** wie das Terminal selbst (B-036):
    - Wenn **kein RFID-Treffer** vorhanden ist und der numerische Code gleichzeitig als **Personalnummer** (aktiv) und als **Mitarbeiter-ID** (aktiv) auf **verschiedene** Personen zeigt, wird das Ergebnis als **BLOCK** angezeigt.
    - Alternative Treffer werden auch im BLOCK/FAIL-Fall gelistet.
- **AKZEPTANZ:**
  - Bei Code-Kollision (PN=A, ID=B, beide aktiv, RFID passt nicht) zeigt der Smoke-Test **BLOCK** + beide Alternativen; kein „OK“-Einloggen.
- **NEXT:**
  - T-069 weiter: Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF prüfen und daraus Bugfixes ableiten.


## P-2026-01-04-12
- ZIP: `P-2026-01-04-12_smoke-test-pdf-quick-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `070555_gesammt.zip` = `dbc6c3e328dfcf7f7b975a596102b832c20870eb40ed819fcbdae2786ba87086`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `8608a34eab6f83b7d09413eba1319164dd5c5d1e64a1d1b3937ae0597f02f70d`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: **T-069** ist offen. Ein Smoke-Test **PDF-Quick-Check (Header/EOF)** war noch nicht umgesetzt → **kein Duplicate**.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test erweitert: Abschnitt **„PDF-Quick-Check (Header/EOF, ohne Download)”** ergänzt.
  - Erzeugt Monats-PDF **im Speicher** (keine Ausgabe als PDF).
  - Prüft `%PDF-` Header und `%%EOF` Marker.
  - Zeigt OK/FAIL + Bytes + Header/EOF Flags.

### AKZEPTANZ
- Auf `?seite=smoke_test` ist die PDF-Quick-Check Sektion sichtbar.
- Klick auf „PDF prüfen“ zeigt OK oder FAIL inkl. Bytes, Header/EOF.

### NEXT
- **T-069:** Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF/Export prüfen und daraus Bugfixes ableiten.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Smoke-Test um PDF-Quick-Check (Header/EOF, ohne Download) ergänzt.
- **Nächster geplanter Schritt:** T-069 – Kernflows manuell testen und erste Bugfixes aus den Testergebnissen ableiten.


## P-2026-01-04-13
- ZIP: `P-2026-01-04-13_smoke-test-pdf-kommentar-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `071509_gesammt.zip` = `62d123fc402284bf4a369f7a94b79848bb9b576e902b7a29d7c4f21cd22c123a`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `29cf76fc5167ec77a00b20bcb4e51d6717f35882f00fbb1c85369f0f1a2b32a7`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: PDF-Quick-Check (Header/EOF) existiert seit **P-2026-01-04-12**. Ein zusätzlicher Diagnose-Check für **Kommentar-Kürzel im PDF-Stream** war noch nicht vorhanden → **kein Duplicate**.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test PDF-Quick-Check erweitert:
  - Liest bis zu 10 `tageswerte_mitarbeiter.kommentar`-Einträge im ausgewählten Monat (nicht leer).
  - Kürzt wie im PDF auf max. 6 Zeichen (UTF-8 safe, falls möglich).
  - Prüft per einfacher Substring-Suche, ob das Kürzel im generierten PDF-Stream vorkommt (Diagnose/Hinweis).
  - Ergebnis wird im Smoke-Test als Liste je Datum angezeigt (gefunden/nicht gefunden).

### AKZEPTANZ
- Auf `?seite=smoke_test` zeigt der PDF-Quick-Check nach „PDF prüfen“ zusätzlich den Block **„Kommentar-Kürzel Check (optional)”**:
  - Wenn Kommentare im Monat vorhanden sind: Liste mit „im PDF gefunden / nicht gefunden”.
  - Wenn keine Kommentare vorhanden sind: Hinweis „keine Tageswerte-Kommentare …”.

### NEXT
- **T-069:** Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF/Export prüfen und daraus Bugfixes ableiten.


## P-2026-01-04-14
- ZIP: `P-2026-01-04-14_smoke-test-offline-queue-uebersicht.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `072419_gesammt.zip` = `2414484c4242c84b9f042ae32a96210e17027e906918da4b2aff21b0f0512a5b`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `fc9ea9c24f4f3805cd39cb8faba5b495d7625a9bdf6f631597eaa7803e2a7aa6`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Ein Smoke-Test Abschnitt für **Offline-Queue (db_injektionsqueue) Zähler + letzte Einträge** war noch nicht vorhanden → **kein Duplicate**.
- Gegen SNAPSHOT/LOG geprüft: Smoke-Test prüfte bisher Spalten, aber keine **Unique-Index** Diagnose für Terminal-Login-Codes → **kein Duplicate**.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test erweitert (rein lesend):
  - Neuer Abschnitt **„Offline-Queue (db_injektionsqueue)”**: Status-Zähler (offen/verarbeitet/fehler) + letzte 10 Einträge (inkl. meta_aktion, Versuche, Fehler-Kurztext).
  - DB-Diagnose ergänzt: **Unique Index** Checks für `mitarbeiter.personalnummer` und `mitarbeiter.rfid_code`.

### AKZEPTANZ
- Auf `?seite=smoke_test` ist der Abschnitt **„Offline-Queue (db_injektionsqueue)”** sichtbar und zeigt Zähler + (optional) die letzten 10 Einträge.
- In der Smoke-Test Check-Tabelle erscheinen zusätzlich zwei Einträge **„Unique Index: mitarbeiter.personalnummer”** und **„Unique Index: mitarbeiter.rfid_code”**.

### NEXT
- **T-069:** Kernflows manuell testen (Terminal online/offline stempeln, Queue, Aufträge), Monatsübersicht/PDF/Export prüfen und daraus Bugfixes ableiten.


## P-2026-01-04-15
- ZIP: `P-2026-01-04-15_smoke-test-terminal-config-timeouts.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `073107_gesammt.zip` = `4857377d5662d6cdf262f142adbc5a0505db17c17272f55fb72e2ba57ff274f0`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a198a68049c0465335a5daf04fad4d3c735e599fd2e9fe226199db6d61b85f86`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Es gab bisher **keine** Smoke-Test Übersicht der Terminal-Timeout-Config-Keys (nur Notizen über fehlende Seeds) → **kein Duplicate**.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test erweitert: Abschnitt **„Terminal-Konfiguration (Config-Keys)”** ergänzt (rein lesend):
  - Zeigt `terminal_timeout_standard` und `terminal_timeout_urlaub` inkl. DB-Wert, effektivem Wert (Fallback auf Default), Status (OK/Default/Invalid) und gültigem Range.
  - Link zu `?seite=konfiguration_admin` ergänzt.

### AKZEPTANZ
- Auf `?seite=smoke_test` ist der Abschnitt **„Terminal-Konfiguration (Config-Keys)”** sichtbar (unabhängig davon, ob der Terminal-Login-Check ausgeführt wurde).
- Wenn Keys fehlen: Status **„Nicht gesetzt → Default”**.
- Wenn Key-Wert außerhalb Range: Status **„INVALID → Default”**.

### NEXT
- **T-069:** Kernflows manuell testen (Terminal online/offline stempeln, Queue), Monatsübersicht/PDF/Export prüfen und daraus Bugfixes ableiten.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Smoke-Test um Terminal-Konfiguration (Timeout-Config-Keys) ergänzt.
- **Nächster geplanter Schritt:** T-069 – Kernflows manuell testen und erste Bugfixes aus den Testergebnissen ableiten.


## P-2026-01-04-16
- ZIP: `P-2026-01-04-16_smoke-test-terminal-idle-timeout-config.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `074221_gesammt.zip` = `6c2da2fe456b26dd60c32fb065b76491a5098b44f4e397a8baed212cdb0e4d8a`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `de096e83251e738d77dcf9cac21ad872ae2a89d5c9b04d356f9d194474e1e74a`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Smoke-Test „Terminal-Konfiguration“ existiert bereits (P-2026-01-04-15), aber der Key **`terminal_session_idle_timeout`** wurde dort noch nicht angezeigt → **kein Duplicate**.
- Task-Kontext: **T-069** (Smoke-Test/Diagnose erweitern, Bugfixes aus Tests ableiten).

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test erweitert: Im Abschnitt **„Terminal-Konfiguration (Config-Keys)”** wird zusätzlich der Key `terminal_session_idle_timeout` (serverseitiger Session-Idle-Fallback) angezeigt.

### AKZEPTANZ
- Auf `?seite=smoke_test` erscheint im Abschnitt **„Terminal-Konfiguration (Config-Keys)”** eine zusätzliche Zeile für `terminal_session_idle_timeout` (mit DB-Wert/Effective/Range/Status).

### NEXT
- **T-069:** Kernflows manuell testen (Terminal online/offline stempeln, Queue, Monatsübersicht/PDF) und die ersten gefundenen Bugs in **Einzel-Patches** fixen.





## P-2026-01-04-17
- ZIP: `P-2026-01-04-17_report-feiertag-stunden.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `074846_gesammt.zip` = `350ec4bc01a1c8e38ca67a3108015dda10d5db6d024522fd737cf9f5cff68db1`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `13783d0dd4e4a96d21bad7e2847c8dc65669c835f3536ff37ff5e4f09b885d76`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Es gab bisher **keinen** Patch, der Feiertage im Monatsreport/PDF als bezahlte Abwesenheit (8h) ausweist → **kein Duplicate**.
- Task-Kontext: **T-069** (Kernflows testen → Bugfixes aus PDF/Monatsübersicht ableiten).

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `docs/DEV_PROMPT_HISTORY.md`
3) `docs/master_prompt_zeiterfassung_v6.md`

### DONE
- **B-070:** Monatsreport/PDF: Kalender-Feiertage werden nun in der Tagesliste als **Feiertag** geführt und bei **keiner Arbeitszeit** mit Tagesstunden (Fallback 8.00 / Tages-Soll) befüllt.
- Anzeige-Logik: Kalender-Feiertag überschreibt `Urlaub/Betriebsferien` (Urlaub-Stunden werden an Feiertagen auf 0 gesetzt), schützt aber explizite Kennzeichen (Krank/Arzt/Sonstiges).
- Soll-Fallback angepasst: Feiertage reduzieren das Soll **nicht** (damit Feiertagsstunden nicht als „+8 Überstunden“ erscheinen).

### AKZEPTANZ
- Im Monatsreport/PDF für **01.01.2026** wird in der Spalte **Feiertag** `8,00` angezeigt (wenn keine Arbeitszeit gebucht ist).
- Der Tag wird nicht als Urlaub (Betriebsferien) gezählt.
- Monatsdifferenz (Ist-Soll) bleibt dadurch neutral (kein künstliches +8 durch Feiertag).

### NEXT
- **T-069:** Weitere PDF/Monatsreport-Bugs aus den Tests aufnehmen und in Einzel-Patches fixen (z. B. Korrektur-Markierung/Rot, Rundung, Feiertag/BF/Kurzarbeit-Kantenfälle).



## P-2026-01-04-18
- ZIP: `P-2026-01-04-18_feiertagservice-seed-missing.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `080417_gesammt.zip` = `da672ffeeefa46b211d6237298fb367bed036db751488e9e41151fc9a5695fa0`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `9025c47af649f1c061df343214b66c293c97074e645124dcc346c365600e8852`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Es gab bisher keinen Patch, der das Feiertage-Seeding gegen **Teilbestände pro Jahr** absichert (Seed lief nur bei „Jahr leer“). → **kein Duplicate**.
- Task-Kontext: **T-069** (Kernflows testen → PDF/Monatsreport-Feiertagsfälle stabilisieren).

### DATEIEN (max. 3)
1) `services/FeiertagService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- **B-071:** FeiertagService seedet bundeseinheitliche Feiertage jetzt auch dann (idempotent), wenn für ein Jahr bereits **einzelne** Feiertage vorhanden sind (z. B. manuell/teilweise gepflegt) – fehlende Basis-Feiertage wie **01.01.** werden ergänzt.
- `FeiertagService::generiereFeiertageFuerJahrWennNoetig()` prüft nun die erwarteten Basis-Daten pro Jahr und seeded bei Bedarf fehlende Einträge; dabei werden **nur** `bundesland IS NULL` als Basis gewertet.

### AKZEPTANZ
- Wenn in `feiertag` für ein Jahr nur **teilweise** Einträge existieren, wird beim ersten `istFeiertag()`-Call dieses Jahr automatisch (idempotent) mit den fehlenden bundeseinheitlichen Feiertagen ergänzt.
- Monatsreport/PDF erkennt danach 01.01. (Neujahr) zuverlässig als Feiertag.

### NEXT
- **T-069:** Weitere Kernflow-Tests (Terminal/Queue/PDF) durchführen und gefundene Bugs in Einzel-Patches fixen.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Feiertage-Seeding: Basis-Feiertage werden pro Jahr auch bei Teilbestand idempotent ergänzt (Feiertag-Detection stabil).
- **Nächster geplanter Schritt:** T-069 – weitere Kernflow-Tests (Terminal/Queue/PDF) und daraus folgende Bugfixes.



## P-2026-01-04-19
- ZIP: `P-2026-01-04-19_smoke-test-feiertag-quick-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `081626_gesammt.zip` = `c8392bd74b4a92db49ddcd26ebd5f7efc96b7d7bb3172d43751db3bc6269afc3`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `d07cb5fc3c6e9a10c1df4c5f47d792b53f18bbd5aab76023ca0fb55e15a8527b`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Kein bestehender Patch ergänzt einen Feiertag-Quick-Check im Smoke-Test (Monatsreport: Kennzeichen/Feiertagsstunden). → **kein Duplicate**.
- Task-Kontext: **T-069** (Kernflows testen/stabilisieren).

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test-Seite um **Feiertag-Quick-Check (Monatsreport)** ergänzt: für ein Datum wird geprüft, ob `kennzeichen_feiertag=1` gesetzt ist und (bei 0 Ist-Arbeitszeit) `feiertag_stunden` mit Tages-Soll (z. B. 8,00) gefüllt wird.
- Ausgabe zeigt die relevanten Felder (Wochentag, Tagestyp, Arbeitszeit, Feiertag-Stunden, Kommentar) direkt im Browser.

### AKZEPTANZ
- `?seite=smoke_test` → Abschnitt „Feiertag-Quick-Check“: Datum **2026-01-01** prüfen → Ergebnis **OK** (kennzeichen_feiertag=1, feiertag_stunden=8,00) sofern keine Arbeitszeit gebucht ist.

### NEXT
- **T-069:** Weitere Kernflow-Tests (Terminal/Queue/PDF) durchführen und gefundene Bugs in Einzel-Patches fixen.


## P-2026-01-04-20
- ZIP: `P-2026-01-04-20_smoke-test-feiertag-seed-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `083551_gesammt.zip` = `0568570d577b3b17e2813b11940da617b3ffa5f2282a81f42b2da7354e854525`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `7aa850592f22cb48bb5543e157b40974ac5f87b0b8e9c003cb634fce98e5fa60`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Kein bestehender Patch ergänzt einen Feiertag-Seed-Check im Smoke-Test (Vollständigkeit Grundmenge pro Jahr). → **kein Duplicate**.
- Task-Kontext: **T-069** (Kernflows testen/stabilisieren).

### DATEIEN (max. 3)
1) `services/FeiertagService.php`
2) `controller/SmokeTestController.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `FeiertagService`: Diagnose-Methode `diagnoseBundesweiteFeiertage(jahr)` ergänzt (rein lesend, seeding bleibt idempotent wie im Livebetrieb).
- `SmokeTestController`: Neuer Abschnitt „Feiertag-Seed-Check (bundesweit)“ + POST-Handling (Jahr wählen, Ergebnis: erwartet/vorhanden/missing/extra).

### AKZEPTANZ
- `?seite=smoke_test` → Abschnitt „Feiertag-Seed-Check (bundesweit)“: Jahr **2026** prüfen → Ergebnis **OK** (Erwartet: 9, Vorhanden: 9) und Liste „Fehlend“ ist leer.

### NEXT
- **T-069:** Weitere Kernflow-Tests (Terminal/Queue/PDF) durchführen und gefundene Bugs in Einzel-Patches fixen.


## P-2026-01-04-21
- ZIP: `P-2026-01-04-21_smoke-test-terminal-stempel-guard-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `090351_gesammt.zip` = `bbf21fc4f2c31f78270b0843fbdf52273a281615fe4b24a68d29ab505fe32311`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `1c71f9c7ed0766dcf96e3d4c8afbf63274148e7f2befaed3a22369a57f343a94`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Kein Patch ergänzt im Smoke-Test eine explizite Anzeige „Kommen/Gehen/Auftrag erlaubt“ basierend auf heutiger Anwesenheit. → **kein Duplicate**.
- Task-Kontext: **T-069** (Kernflows testen/stabilisieren).

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test „Terminal-Login-Check“ zeigt jetzt zusätzlich eine klare **Erlaubt**-Anzeige (Kommen/Gehen/Auftrag-Start) basierend auf der heutigen Kommen/Gehen-Zählung (online/DB-Check).

### AKZEPTANZ
- `?seite=smoke_test` → Abschnitt „Terminal-Login-Check“: bei gültigem Code erscheint unter der Anwesenheit die Zeile „Erlaubt (online-Check): Kommen JA/NEIN, Gehen JA/NEIN, Auftrag-Start JA/NEIN“ passend zum Status.

### NEXT
- **T-069:** Weitere Kernflow-Tests (Terminal online/offline stempeln + Queue, Monatsübersicht/PDF) durchführen und gefundene Bugs in Einzel-Patches fixen.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Smoke-Test erweitert: Terminal-Login-Check zeigt Erlaubt-Ampel (Kommen/Gehen/Auftrag-Start) basierend auf Anwesenheit heute.
- **Nächster geplanter Schritt:** T-069 weiter: Terminal online/offline stempeln + Queue testen; Monatsübersicht/PDF prüfen und Bugfixes in Einzel-Patches umsetzen.


## P-2026-01-04-22
- ZIP: `P-2026-01-04-22_smoke-test-monatsreport-raster-check.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `091229_gesammt.zip` = `5310190e5083500a1a644a04047153bff549fa9f1e5cdb9fc0786cdf62a99c82`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `c939af90965d1755cf80aad43a063d80fc2cf804ccfeca3bcee6296cf5b31dba`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Kein Smoke-Test-Abschnitt prüft bisher explizit die Vollständigkeit des Monatsrasters (Tage im Monat vs. Tageswerte + fehlende/doppelte Datumswerte). → **kein Duplicate**.
- Task-Kontext: **T-069** (Kernflows testen/stabilisieren).

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test um **„Monatsreport-Raster-Check“** ergänzt: prüft, ob `ReportService::holeMonatsdatenFuerMitarbeiter()` ein vollständiges Monatsraster liefert (1 Tageswert pro Kalendertag).

### AKZEPTANZ
- `?seite=smoke_test` → Abschnitt „Monatsreport-Raster-Check“: Mitarbeiter/Jahr/Monat eingeben → **OK**, wenn Tageswerte_count = Tage im Monat und keine fehlenden/doppelten/invaliden Datumswerte; sonst **FAIL** mit Listen.

### NEXT
- **T-069:** Weitere Kernflow-Tests (Terminal online/offline stempeln + Queue, Monatsübersicht/PDF) durchführen und gefundene Bugs in Einzel-Patches fixen.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Smoke-Test erweitert: Monatsreport-Raster-Check ergänzt (Vollraster = 1 Tageswert/Tag).
- **Nächster geplanter Schritt:** T-069 weiter: Terminal online/offline stempeln + Queue testen; Monatsübersicht/PDF prüfen und Bugfixes in Einzel-Patches umsetzen.


## P-2026-01-04-23
- ZIP: `P-2026-01-04-23_fix-feiertag-8h-im-raster.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `092143_gesammt.zip` = `156016efa690effe576717369dfe9639e3aa2dc9e046cddcd108d285039eb684`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `9e870fb2309b3bf4e29c2d4cf9900abd0ea2ff59620b271cbc45827c176903b0`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Feiertag-Quick-Check existiert bereits (Smoke-Test), aber **kein Fix** für fehlende Feiertagsstunden im Default-Monatsraster. → **kein Duplicate**.
- Task-Kontext: **Bugfix** (Feiertag 01.01.2026: in Liste/PDF fehlen 8h).

### DATEIEN (max. 3)
1) `services/ReportService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- `ReportService::baueDefaultTageswert()` setzt im Monatsraster für **Feiertage an Arbeitstagen (Mo–Fr)** standardmäßig `feiertag_stunden = 8.00` (nicht am Wochenende). Dadurch ist der 01.01.2026 in der Liste/PDF korrekt mit 8h sichtbar, auch wenn kein Tageswerte-Datensatz vorhanden ist.

### AKZEPTANZ
- `?seite=smoke_test` → Abschnitt „Feiertag-Quick-Check (Monatsreport)“ (Default-Datum `2026-01-01`):
  - `Ist Feiertag` = true
  - `Kennzeichen Feiertag` = 1
  - `Feiertag-Stunden` > 0 (soll 8.00 anzeigen)

### NEXT
- **T-069:** Terminal online/offline stempeln + Queue testen; anschließend Monatsübersicht/PDF auf weitere Randfälle prüfen (Urlaub/Krank/Kurzarbeit-Kollisionen).

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Bugfix: Feiertag im Default-Monatsraster wird mit 8.00h ausgewiesen.
- **Nächster geplanter Schritt:** T-069 weiter: Terminal online/offline stempeln + Queue testen; Monatsübersicht/PDF weiter prüfen.


## P-2026-01-04-24
- ZIP: `P-2026-01-04-24_ui-monatsuebersicht-an-ab.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `093049_gesammt.zip` = `862b1105b6da717acc4159c4fe02772e2ad66c5d2319cbb5d7583d209bc8544c`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6c775ce5978f3742667b4e2aa55727b7f2ec13fa0fbed6d9edb89cf21ad4be99`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Monatsübersicht nutzt in der Tabelle noch die Überschriften **„Kommen/Gehen“**; kurze Überschriften sind bisher nur in Teilen (z. B. PDF) umgesetzt. → **kein Duplicate**.
- Task-Kontext: **T-074** (PDF/UX – UI-Spalten kurz).

### DATEIEN (max. 3)
1) `views/report/monatsuebersicht.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monatsübersicht: Tabellenüberschriften **„Kommen/Gehen“ → „An/Ab“** gekürzt.

### AKZEPTANZ
- Backend: `?seite=report_monat` → Monatsübersicht-Tabelle zeigt die Spalten **„An“** und **„Ab“** (statt „Kommen/Gehen“).

### NEXT
- **T-069:** Terminal online/offline stempeln + Queue testen; anschließend Monatsübersicht/PDF auf weitere Randfälle prüfen (Urlaub/Krank/Kurzarbeit-Kollisionen).

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Monatsübersicht: Spaltenüberschriften kurz (An/Ab).
- **Nächster geplanter Schritt:** T-069 weiter: Terminal online/offline stempeln + Queue testen; Monatsübersicht/PDF weiter prüfen.



## P-2026-01-04-25
- ZIP: `P-2026-01-04-25_ui-monatsuebersicht-feiertag-spalte.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `100201_gesammt.zip` = `fed94f5c8c640d1a624ca9f8d8929fb9f64e7034584106b78ef694c384f43174`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `8ad1aa4e28030c4ddf86190d69b7c6bad328f5eda92c0a7e26fbfad868780069`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Monatsübersicht hatte bisher **keine** separate Spalte für `feiertag_stunden` (nur `Tagestyp`). → **kein Duplicate**.
- Task-Kontext: **UX/Bugfix** – Feiertagsstunden (z. B. 8.00) sollen in der Liste sichtbar sein.

### DATEIEN (max. 3)
1) `views/report/monatsuebersicht.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Monatsübersicht: neue Spalte **„Feiertag“** ergänzt, die `feiertag_stunden` anzeigt (bei 0 → `-`).

### AKZEPTANZ
- Backend: `?seite=report_monat&amp;jahr=2026&amp;monat=1` → Tabelle enthält Spalte **„Feiertag“**.
- Am **2026-01-01** (Feiertag, Mo–Fr) wird **8.00** in der Feiertag-Spalte angezeigt (wenn keine Arbeit erfasst).

### NEXT
- **T-069:** Smoke-Test der Kernflows weiterführen (Terminal online/offline stempeln + Queue) und Bugfixes basierend auf Ergebnissen.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Monatsübersicht: Feiertag-Stunden-Spalte ergänzt.
- **Nächster geplanter Schritt:** T-069 weiter: Terminal online/offline stempeln + Queue testen; Monatsübersicht/PDF weiter prüfen.

## P-2026-01-04-26
- ZIP: `P-2026-01-04-26_smoke-test-queue-offline-db-source.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `101155_gesammt.zip` = `39e419926cec8aab8c0f050c4cf8b4c4213d7e27e7efb5344e9072861071b0af`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `fa60cc506d59197d9aeea977de8fd6ba8ae81285622653bc82e3834551104da9`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Smoke-Test-Queue-Übersicht verwendete bisher ausschließlich `getVerbindung()` (Haupt-DB). Ein Fix auf die gleiche Queue-DB-Auswahl wie `queue_admin` ist bisher **nicht** dokumentiert. → **kein Duplicate**.
- Task-Kontext: **T-069 (Teil)** – Smoke-Test Kernflows/Diagnostik.

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test: Queue-Übersicht nutzt jetzt wie `queue_admin` die Offline-DB, wenn konfiguriert (sonst Haupt-DB) und zeigt die verwendete Quelle in der UI an.

### AKZEPTANZ
- Backend: `?seite=smoke_test` → Abschnitt „Offline-Queue“ zeigt „Queue-DB: Offline-DB/Haupt-DB“ an und die Zähler stimmen mit `?seite=queue_admin` überein (bei konfigurierter Offline-DB).

### NEXT
- **T-069:** Terminal online/offline stempeln + Queue verarbeiten; anschließend Monatsübersicht/PDF auf weitere Randfälle prüfen (Urlaub/Krank/Kurzarbeit-Kollisionen).

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Smoke-Test: Queue-Übersicht nutzt Offline-DB wie Queue-Admin.
- **Nächster geplanter Schritt:** T-069 weiter: Terminal online/offline stempeln + Queue testen; Monatsübersicht/PDF weiter prüfen.


## P-2026-01-04-26
- ZIP: `P-2026-01-04-26_smoke-test-queue-db-auswahl.zip`

### EINGELESEN (SHA256)
- Projekt-ZIP: `101155_gesammt.zip` = `39e419926cec8aab8c0f050c4cf8b4c4213d7e27e7efb5344e9072861071b0af`
- Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
- DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `fa60cc506d59197d9aeea977de8fd6ba8ae81285622653bc82e3834551104da9`
- SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
- SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`

### DUPLICATE-CHECK
- Gegen SNAPSHOT/LOG geprüft: Smoke-Test-Queue-Übersicht dokumentiert bisher **keine** Queue-DB-Auswahl (Offline-DB vs Haupt-DB) und erwähnt keine Inkonsistenz zu `queue_admin`. → **kein Duplicate**.
- Task-Kontext: **T-069** (Smoke-Test / Stabilität).

### DATEIEN (max. 3)
1) `controller/SmokeTestController.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### DONE
- Smoke-Test: Queue-Übersicht nutzt jetzt wie `queue_admin` die **Offline-DB**, wenn konfiguriert (sonst Haupt-DB) und zeigt die verwendete **Quelle** in der UI an.

### AKZEPTANZ
- Backend: `?seite=smoke_test` → Abschnitt „Offline-Queue“ zeigt **Queue-DB: Offline-DB/Haupt-DB** und die Zähler/„Letzte 10“ sind konsistent zu `?seite=queue_admin` (gleiche DB-Auswahl).

### NEXT
- **T-069:** End-to-End Offline-Queue Roundtrip testen (Terminal offline stempeln → Queue-Admin verarbeiten → Monatsübersicht/PDF prüfen) und gefundene Bugs als Micro-Patches beheben.

### Aktueller Status (2026-01-04)

- **Zuletzt erledigt:** Smoke-Test: Queue-Übersicht nutzt Offline-DB wie Queue-Admin.
- **Nächster geplanter Schritt:** T-069 weiter: End-to-End Offline-Queue Roundtrip testen; Monatsübersicht/PDF weiter prüfen.
## P-2026-01-04-28
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `103625_gesammt.zip`: `b0a9974399400c02f452c7f395fb6cc26927ae61ae200144fa34dd2fc39deeb2`
  - `docs/master_prompt_zeiterfassung_v6.md`: `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - `docs/DEV_PROMPT_HISTORY.md`: `3e26b132c3abfe05c7cbd8c9ee003ea72981255225e62c185ca7eecf39daeef6`
  - `sql/01_initial_schema.sql`: `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
  - `sql/zeiterfassung_aktuell.sql`: `c2fdaa368de1ab85c43b7e19b9e3fb86571d902fecaf014284d4d2ef2b6daffd`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: T-075 war offen, keine bestehende Tabelle `sonstiges_grund`, keine Migration 12 vorhanden.
- **DATEIEN (max. 3):**
  1) `sql/12_migration_sonstiges_gruende.sql`
  2) `sql/zeiterfassung_aktuell.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - T-075 (Teil 1): neue Konfig-Tabelle `sonstiges_grund` (Code/Titel/Default-Stunden/Begründungspflicht/Sort/Aktiv).
  - Default-Datensatz `SoU` (Sonderurlaub) mit 8.00h als erster Eintrag (Migration + SQL-Dump).
- **NEXT:**
  - T-075 (Teil 2): Admin-Liste in `konfiguration_admin` (CRUD/Sort/Aktiv) + Auswahl in Tagesansicht (Schnell-Haken/Dropdown).

## P-2026-01-04-29
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `105327_gesammt.zip`: `6876336bcbec167f1c3d9d7f546d509d3cb0de18857cd00300eadeab6224dc11`
  - `docs/master_prompt_zeiterfassung_v6.md`: `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - `docs/DEV_PROMPT_HISTORY.md`: `1ff296141f86bb0b9593a03d45c5702a9e9de00e8ef3e7af13465721130b22c4`
  - `sql/01_initial_schema.sql`: `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
  - `sql/zeiterfassung_aktuell.sql`: `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: T-075 Teil 2a war offen, kein bestehender Admin-Tab für `sonstiges_grund`.
- **DATEIEN (max. 3):**
  1) `controller/KonfigurationController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - T-075 (Teil 2a): Admin-Tab „Sonstiges-Gründe“ in `konfiguration_admin` (Liste + Formular, Speichern + Aktiv-Toggle, CSRF).
- **NEXT:**
  - T-075 (Teil 2b): Tagesansicht: Grund auswählen (Dropdown/Schnellwahl) → setzt `kennzeichen_sonstiges=1`, füllt `sonstige_stunden` (Default) und erzwingt Kommentar, wenn `begruendung_pflicht=1`.

## P-2026-01-04-30
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `110348_gesammt.zip`: `55d00b0f8c5ef258b30dc0302def6f099d8b13d5190d7c8e2cb255f2e1d2c8c0`
  - `docs/master_prompt_zeiterfassung_v6.md`: `0d2dc2a4117c286a2e8aa841c12a9cbd1dc254d91f4e5e9fd3b046f8006e7520`
  - `docs/DEV_PROMPT_HISTORY.md`: `7236be1ce4e8344e915688e9ae0d6bcff0dfd1dc3700205cf8ce03a0a41b51f6`
  - `sql/01_initial_schema.sql`: `0c29e9c5a60cf0d9c7088dd863fb452f6c03b45dd2f9f5fc6159fc0c1af8c7be`
  - `sql/zeiterfassung_aktuell.sql`: `a5111e0df8b7d2d7c039599b3cf55ee2e646b6601060a5bbdd2c4da0f59bcd6a`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: keine vorhandene Doku/SQL-Hilfe zur Initialisierung einer Terminal-Offline-DB.
- **DATEIEN (max. 3):**
  1) `sql/offline_db_schema.sql` (neu)
  2) `sql/README.md`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Offline-DB (Terminal): Minimal-Schema als optionales SQL-Skript ergänzt (`db_injektionsqueue`) + Doku in `sql/README.md`.
- **NEXT:**
  - T-075 (Teil 2b): Tagesansicht: Grund auswählen (Dropdown/Schnellwahl) → setzt `kennzeichen_sonstiges=1`, füllt `sonstige_stunden` (Default) und erzwingt Kommentar, wenn `begruendung_pflicht=1`.

## P-2026-01-04-31
- **EINGELESEN (SHA256):**
  - Projekt-ZIP `112614_gesammt.zip`: `9d17a617b77f3fa283f9ad1b56a990e4031c7ce7d1bd86c757048870f914fc38`
  - `docs/master_prompt_zeiterfassung_v6.md`: `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - `docs/DEV_PROMPT_HISTORY.md`: `b3843e79f03648678f2a6295af6667dc36347d4fc2b6c94df1afdbc866bea820`
  - `sql/01_initial_schema.sql`: `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
  - `sql/zeiterfassung_aktuell.sql`: `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: T-075 (Teil 2b) war offen; keine bestehende `set_sonstiges`-Action in Tagesansicht.
- **DATEIEN (max. 3):**
  1) `controller/ZeitController.php`
  2) `views/zeit/tagesansicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - T-075 (Teil 2b): Tagesansicht: Sonstiges-Grund-Auswahl (Dropdown) inkl. Default-Stunden-Übernahme, Konfliktcheck gegen Arbeitszeit/andere Kennzeichen und Notizpflicht pro Grund.
- **NEXT:**
  - T-074 (Teil 2): Monatsreport-PDF: Kürzel/Begründung aus `tageswerte_mitarbeiter.kommentar` auch im PDF ausweisen.

## P-2026-01-04-32
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `3ba424324d150131adf306fd78c384ca89347bb37ed7ecb9767c5dd9fe056966` (114105_gesammt.zip)
  - Master-Prompt: `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595` (docs/master_prompt_zeiterfassung_v6.md)
  - DEV_PROMPT_HISTORY: `54752596e978d2ef2c1b3d705988b24f1463670d15f57a748e530035756933f7` (docs/DEV_PROMPT_HISTORY.md)
  - SQL-Schema: `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920` (sql/01_initial_schema.sql)
- **DUPLICATE-CHECK:** T-074 (Teil 2) war im SNAPSHOT offen; kein gleichlautender DONE-Eintrag im LOG gefunden.
- **DATEIEN (max. 3):** `services/PDFService.php`, `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsreport-PDF: Spalte „Kürzel“ zeigt Code-Teil (bis ':'); Begründungen/Notizen werden unten links als „Bemerkungen“ gelistet.
- **TESTS:** `php -l services/PDFService.php`
- **NEXT:** T-069 Smoke-Test Kernflows fortsetzen; Tagesansicht-Sonstiges Notizpflicht/Begründung prüfen und ggf. korrigieren.

## P-2026-01-04-33
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `120301_gesammt.zip` = `af32afa235638ef5023b599823d5c15330e1ea7c8b01ae4350080185ffc09f9e`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `3468ca19e43f55d620be6bc03a3b470c32c7c3f9c04a9008db1fae3d137ae7aa`
  - SQL (SoT): `sql/01_initial_schema.sql` = `3b943bae27f373d1c20565bb3f969c334a6e362a9c61888348c39fc579af0920`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: T-075 (Schema+Admin+Tagesansicht) ist zwar DONE, aber es gab **keinen** DONE-Eintrag, der `sql/01_initial_schema.sql` um `sonstiges_grund` ergänzt. → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `sql/01_initial_schema.sql`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `sql/01_initial_schema.sql` ergänzt um Tabelle `sonstiges_grund` (inkl. Unique/Index) + Default-Eintrag `SoU` (Sonderurlaub, 8.00h).
- **NEXT:** **T-069** Smoke-Test Kernflows fortsetzen; Terminal online/offline stempeln + Queue verarbeiten; Monatsübersicht/PDF auf Randfälle prüfen.

## P-2026-01-04-34
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `121437_gesammt.zip` = `1c241ac2a8fb4c0fabc78eec8e9ef9e982b44688ef8ff1f8ccb6d555df07e8f9`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `42fa118e3e64a22a48c9cfff1024bcfd6e10de6b8ea6e303bd40fd95d6814959`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein bestehender Smoke-Test-Check für `sonstiges_grund`/SoU/Active-Count vorhanden → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert um Sonstiges-Checks: Tabelle/Spalten/Unique-Index (`sonstiges_grund.code`) + Active-Count + Default `SoU`-Presence (read-only).
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069** Smoke-Test Kernflows weiterführen (Monatsübersicht/PDF Randfälle + Terminal-Online/Offline-Flow) und Bugfixes nach Testergebnis.


## P-2026-01-04-35
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `122359_gesammt.zip` = `fcb9478eb1804ea0a25ad05cb1d700c171b3aa796d2ba60dd56cb7c16804756b`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `031ddec4740fc06ea32212f3c35a975333d843cfc2c181636ab603703ac40619`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein bestehender Smoke-Test-Check für `installation_typ`↔`offline_db.enabled` Erwartung + Offline-Queue-Schema-Check vorhanden → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: liest `config/config.php` und prüft Terminal-Installation auf aktive/erreichbare Offline-DB; Backend bekommt einen Hinweis bei aktivierter Offline-DB; zusätzlich read-only Schema-Check auf `db_injektionsqueue` (warnend, falls Tabelle noch fehlt).
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069** Terminal-Online/Offline-Flow manuell smoketesten: Haupt-DB kurz offline → Kommen/Gehen schreibt in Offline-Queue; danach DB online → Queue wird automatisch abgearbeitet; prüfen, dass keine Störungsseite erscheint.


## P-2026-01-04-36
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `123556_gesammt.zip` = `1b5cf8fbaec9f635ac7c51d3a560999cb41841cd084cf94f37a1d4fba3a0e2f6`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `739e240bfd805b7085cad5fc2f47d890e8b32912ae0403a91d6f9940c8e0b519`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Smoke-Test-Check vorhanden, der die Terminal-Entry-Datei `public/terminal.php` auf Queue-Flush (Aufruf `verarbeiteOffeneEintraege`) prüft → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: zusätzlicher read-only Check, ob `public/terminal.php` die Offline-Queue pro Request abarbeitet (wichtig für Terminal-Offline→Online Rücksync).
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069** Terminal-Online/Offline-Flow manuell smoketesten: Haupt-DB kurz offline → Kommen/Gehen schreibt in Offline-Queue; danach DB online → Queue wird automatisch abgearbeitet; prüfen, dass keine Störungsseite erscheint.

## P-2026-01-04-37
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `124402_gesammt.zip` = `4f9c2cecffcc8a7bca98ba15af217642676d4e76e9156f52ad14503176e37794`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `67ec3e2771b0e459dbdfc5e080cf151769956fc95c3cc5a4b37bd1aab5243706`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Smoke-Test-Check vorhanden, der bei `installation_typ=terminal` die Trennung von Haupt-DB vs Offline-DB (Host/DBname) prüft → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: Terminal-Config-Check „Offline-DB getrennt von Haupt-DB“ (Fail wenn identisch; Warn-Hinweis wenn nur Host gleich), damit Offline-Pufferung pro Terminal sauber bleibt.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069** Terminal-Online/Offline-Flow manuell smoketesten: Haupt-DB kurz offline → Kommen/Gehen schreibt in Offline-Queue; danach DB online → Queue wird automatisch abgearbeitet; prüfen, dass keine Störungsseite erscheint.

## P-2026-01-04-38
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `125313_gesammt.zip` = `151edadf0e9e52f5c2c338d575a378916fe2b86c42decae30f659d5648682e56`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `37b6d4acae88d0aa9ccc9e90bf67700b4d14d828baae68558381dd64c33c3cb9`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch für T-073 (Arbeitsblöcke/IST-Summe bei Mehrfach-Kommen/Gehen) vorhanden → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `services/ZeitService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Tageswerte-Sync: IST-Stunden werden bei mehreren Kommen/Gehen-Paaren als Summe aus Arbeitsblöcken berechnet (inkl. Pausenregeln pro Block).
- **TESTS:** `php -l services/ZeitService.php`
- **NEXT:** **T-069 (Fortsetzung):** Smoke-Test Kernflows weiterführen (Monatsübersicht/PDF + Terminal-Online/Offline-Flow) und Bugfixes nach Testergebnis.

## P-2026-01-04-39
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `131648_gesammt.zip` = `e1bec569b6d7e3acd76567b19eb065136719dfa3b6fc3e9595e667c9e248d170`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6904729f9c2fc7b5e212e273893fda858e1e40bcda09a31bbc1edfe58dfa17f6`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der Monatsreport-Fallback bei lückenhaften Tageswerten + Mehrfachblock-Berechnung im Fallback abdeckt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsreport: Fallback aus `zeitbuchung` wird immer berechnet und durch vorhandene `tageswerte_mitarbeiter` überschrieben (DB hat Vorrang). Fallback berechnet IST-Stunden bei Mehrfach-Kommen/Gehen als Summe aus Arbeitsblöcken inkl. Pausenregeln; Tagestyp „Betriebsferien“ nur an Arbeitstagen.
- **TESTS:** `php -l services/ReportService.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).





## P-2026-01-04-45
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `172318_gesammt.zip` = `0e79a7a4a3c5ad4b6a92521268c170f6d20f2ecfa8dfc3f65870bd9fad52dbc0`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `0ec67e5eb346302a35efc6a5a0aa2ff4aaa39e75c23539b52b8098bd8d2773d7`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der im Monatsreport Feiertagsstunden bei vorhandener Arbeitszeit auf 0 setzt **und** einen Smoke-Test-Check dafür anbietet → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `controller/SmokeTestController.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Bugfix Monatsreport: Bei Arbeitszeit am Feiertag werden `feiertag_stunden` auf 0 gesetzt (keine Doppelzählung). Smoke-Test erweitert: neuer Check „Feiertag+Arbeitszeit“ findet Konflikte (Feiertagsstunden>0 bei Arbeitszeit>0).
- **TESTS:** `php -l services/ReportService.php`, `php -l controller/SmokeTestController.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).

## P-2026-01-04-44
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `171116_gesammt.zip` = `4f9b5456e7482241e3f4242eadf55b9a29d3d9058845ad6644555855524449e6`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `124cbabbfbef67beec0da38beba6755417eb74c19193e29d37ccea5370ddbe1e`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Smoke-Test-Check vorhanden, der das Vorhandensein + Mindestinhalt von `sql/offline_db_schema.sql` absichert → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: neue Files-Checks prüfen, dass `sql/offline_db_schema.sql` im Projekt vorhanden ist und eine `db_injektionsqueue`-Definition (CREATE TABLE inkl. Spalten `status` + `sql_befehl`) enthält.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).

## P-2026-01-04-43
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `161312_gesammt.zip` = `fb4a6a5c447d896b8ab97991544148f35113aec66c9653758b948ce7c6f68082`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `1dfd38d195e35b46be11fc463d6353edef5f89e9c8bf0d85e6f39a49126ecbe0`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Smoke-Test-Check vorhanden, der im Monatsreport Doppelzählung für Betriebsferien-Urlaub bzw. Kurzarbeit-Volltag bei vorhandener Arbeitszeit erkennt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: neuer „Doppelzählung-Check (Betriebsferien/Kurzarbeit-Volltag)“ über `ReportService::holeMonatsdatenFuerMitarbeiter()`; FAIL bei Arbeitszeit>0 und gleichzeitig Betriebsferien-Urlaub>0 oder Kurzarbeit-Volltag (>=7.99h). Teil-Kurzarbeit wird nicht als Konflikt gewertet. Ausgabe mit Beispielen.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).

## P-2026-01-04-42
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `155515_gesammt.zip` = `ad63e2c4043c2a37c90868e7ffca1795ede5a3a303715c1750e8983c7966a7b2`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a4b232f96e78460de4af5b9a57d22a061b4b5d92c80d95c179c9e0b023674a0d`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der den Terminal-Online/Offline-Flow im Smoke-Test als statischen Code-Check absichert → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: neue Checks prüfen, dass `ZeitService` bei Terminal+Offline-Haupt-DB in die Offline-Queue schreibt (Pseudo-ID=0) und dass `TerminalController` ID=0 (Offline-Queue) sichtbar behandelt (Hinweistext).
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).

## P-2026-01-04-41
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `140026_gesammt.zip` = `653c41cb2e0238a6829dc24dd7fdfe2161a1134a6c332d4540fcb273f146ea88`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a922b08900be60a81966c178c2efa6d10d96ac225a35cc1dd4aaf838fbbf0b35`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der einen Kommen/Gehen-Sequenz-Check im Smoke-Test abdeckt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: neuer „Kommen/Gehen-Sequenz-Check (Monat)“ analysiert Zeitbuchungen pro Tag (K/G) und listet auffällige Tage sowie Mehrblock-Tage. Zusätzlich Cleanups: doppelte Init-Blöcke im SmokeTestController entfernt.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).


## P-2026-01-04-40
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `133609_gesammt.zip` = `a3bfa2a5aa341121049e728d3fbe41addc0f903b3e9c219b44bc704df8728189`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `d8ece142e052435954b57a474c3cd9ad7a7601a5769e08df66dc85ad36e2eacd`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der einen Smoke-Test fuer Monatsreport-Fallback (Buchungen ohne Tageswerte) abdeckt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Smoke-Test erweitert: neuer Monatsreport-Fallback-Check findet Tage mit Buchungen aber ohne `tageswerte_mitarbeiter` und prueft, ob der Monatsreport diese Tage via Fallback sinnvoll befuellt. Zusaetzlich wurde ein duplizierter `monatsraster_test_run`-POST-Block entfernt.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069 (Fortsetzung):** Smoke-Test Kernflows weiterfuehren (Monatsuebersicht/PDF Randfaelle + Terminal-Online/Offline-Flow) und Bugfixes nach Testergebnis.



## P-2026-01-04-47
- ZIP: `P-2026-01-04-47_pdf-bemerkungen-sumstarty-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `181204_gesammt.zip` = `8f02d293efed3358a97c6a3e337541a74d5a9de58a4f973546f532b433ac74de`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `0a8a71ca5a77341b3fb7bd782ce54c248c45839e8b6b763773e4085de829765a`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kein Patch vorhanden, der das `$sumStartY`-Pre-Definition-Problem im PDF-Bemerkungen-Block behebt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `PDFService`: Summenblock-Positionsvariablen (`$sumStartY` usw.) werden vor der Verwendung im Bemerkungen-Block definiert → keine PHP-Notice/Logspam, Bemerkungen stabil positioniert. (Keine Änderung an Berechnung/Business-Logik.)
- **TESTS:** `php -l services/PDFService.php`
- **NEXT:** **T-073 (Teil 2b):** Monatsreport-PDF analog zur Monatsübersicht mehrzeilig rendern (je Arbeitsblock), ohne optische Doppelzählung (Abwesenheiten nur in erster Zeile).

## P-2026-01-04-46
- ZIP: `P-2026-01-04-46_monatsuebersicht-arbeitsbloecke-mehrzeilig.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `b7ddf0d2b46e8ea023f4177b2a24218fbddf468c31d856b4aa5d5c77084bcba1`
  - Master-Prompt: `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `443b13b9762a59cd09d19600cac9b3cd8432131214b450b5a8bccee95a3246c7`
  - SQL-Schema (SoT): `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL-Dump: `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:**
  - Gegen SNAPSHOT/LOG geprüft: **T-073 Anzeige (Monatsübersicht mehrzeilig je Arbeitsblock)** ist noch **nicht** als DONE umgesetzt → **kein Duplicate**.
- **DATEIEN (max. 3):**
  - `services/ReportService.php`
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `ReportService`: liefert pro Tag zusätzlich `arbeitsbloecke` (Kommen/Gehen-Paare aus `zeitbuchung`, inkl. korrigierter Anzeigezeiten; offene Blöcke möglich).
  - Monatsübersicht: zeigt bei mehreren Arbeitsblöcken den Tag **mehrzeilig** (je Block), ohne optische Doppelzählung (Tages-Summen/Typ/Kürzel/Bearbeiten nur in der ersten Zeile).
- **NEXT:**
  - **T-073 (Teil 2b):** Monatsreport-PDF analog mehrzeilig rendern (je Arbeitsblock), Abwesenheitsfelder nur in erster Zeile.

## P-2026-01-04-48
- ZIP: `P-2026-01-04-48_monatsreport-pdf-arbeitsbloecke-mehrzeilig.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `182138_gesammt.zip` = `edbda57ad5cacc7d38cac9b4664f0f037365e7a14dc0cc96bb954150628b8863`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `512ff12664d9d0224c10fa64277b5dbd1459c542cc77d48915005deaf949d19c`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kein Patch vorhanden, der Monatsreport-PDF mehrzeilig je Arbeitsblock rendert (T-073 Teil 2b) → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `PDFService`: Monatsreport-PDF rendert pro Kalendertag mehrere Zeilen (je Arbeitsblock aus `tageswerte.arbeitsbloecke`), ohne optische Doppelzählung (Kürzel/Abwesenheiten/Pause/Ist nur in erster Zeile). Tabelle wird bei vielen Zeilen automatisch auf mehrere Seiten umgebrochen; Summenblock/Bemerkungen nur auf letzter Seite.
- **TESTS:** `php -l services/PDFService.php`
- **NEXT:** **T-069 (Fortsetzung):** Smoke-Test Monatsreport-PDF (Mehrfach-Kommen/Gehen + Mehrseiten) und Bugfixes nach Testergebnis.

## P-2026-01-04-49
- ZIP: `P-2026-01-04-49_smoketest-pdf-seitencheck.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `194732_gesammt.zip` = `edeb5d8dc425d1865e223cf7e415d1162cc1c11a2fbf0203067420eae7fa0fe9`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a314947f0200f38f046689d3078f64328a6fa852ce4dc5fbdec19bd38e56d330`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: PDF-Quick-Check existiert bereits, aber ohne Seitenanzahl-/Footer-Validierung → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `SmokeTestController`: PDF-Quick-Check erweitert um Seitenanzahl-Auswertung (`/Pages /Count` vs Page-Objekte), Footer-Checks ("Seite 1/..." + bei Mehrseiten "Seite 2/..."), sowie Header-String-Prüfungen ("Arbeitszeitliste", "Tag / KW").
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069 (Fortsetzung):** Monatsreport/PDF im Browser testen (Monat mit Mehrfachblöcken + >1 Seite) und ggf. Bugfixes.
## P-2026-01-05-01
- ZIP: `P-2026-01-05-01_smoketest-pdf-synth-multipage.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `061720_gesammt.zip` = `d6cf6aaa291e476497df51315cd603086fd54f3cf9a29ecbd87e8a4e26bf86e9`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `9c6077630a3761320a5bf4b287d7c499a6350bc92ddc56ad8872c7f0e11f3102`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: PDF-Quick-Check existiert, aber nicht als DB-unabhängiger Synth-Multi-Page-Test → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `controller/SmokeTestController.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `PDFService`: neue Diagnose-API `erzeugeMonatsPdfAusDaten()` (Wrapper um interne PDF-Erzeugung). `SmokeTestController`: neuer PDF-Synth-Check erzeugt Multi-Block-Testdaten und prüft mind. 2 Seiten inkl. Header/EOF, Seitenzählung und Footer.
- **TESTS:** `php -l services/PDFService.php && php -l controller/SmokeTestController.php`
- **NEXT:** **T-069 (Fortsetzung):** Monatsreport/PDF im Browser testen (echte Daten: Mehrfachblöcke + Mehrseiten) und ggf. Bugfixes; danach **T-071**.
- **AKZEPTANZKRITERIUM:** Smoke-Test zeigt beim „PDF-Synth-Check“ ein **OK** und erkennt mindestens **2 Seiten**.

## P-2026-01-05-02
- ZIP: `P-2026-01-05-02_smoketest-pdf-db-multipage-candidate.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `063120_gesammt.zip` = `0cf865fbbbcc504e3223d42ddccdf0553cf765da14abda22ccbb80b7d690b409`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `540169e9ed956cde836acb9c2900d004f2600423735a3e824cb2affbd4324ae9`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein bestehender Smoke-Test, der automatisch einen echten Multi-Page-Datensatz findet → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `SmokeTestController`: neuer „PDF DB Auto-Multipage-Check“ findet automatisch den Mitarbeiter/Monat mit den meisten Kommen/Gehen-Buchungen (Suchfenster X Monate) und prüft PDF auf **>=2 Seiten**; liefert Links zu `report_monat` + `report_monat_pdf` für den Browser-Test.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069 (Fortsetzung):** Mit dem gefundenen Kandidaten im Browser `report_monat` + `report_monat_pdf` testen (Mehrfach-Kommen/Gehen + Mehrseiten); falls Viewer/Rendering-Probleme: Bugfix + Smoke-Test-Erweiterung.

## P-2026-01-05-03
- ZIP: `P-2026-01-05-03_smoketest-report-html-check.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `064237_gesammt.zip` = `c836885f5d068ed2f18176c7b1a59fc42c68b0651c635651a54fc84f7dc732b2`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `80d6326eeb262cb25b150011343c46e039ebc5833643919b9bf2219cd5a7682e`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Smoke-Test, der beim DB Auto-Multipage-Kandidaten zusätzlich die HTML-Monatsübersicht rendert → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `SmokeTestController`: Beim „PDF DB Auto-Multipage-Check“ wird nun zusätzlich die Monatsübersicht (`ReportController::monatsuebersicht`) für denselben Kandidaten gerendert und grob validiert (Heading/Tabelle/Headers/PDF-Link/Zeilenanzahl). Bei fehlendem View-All-Recht: **SKIP** statt Fehlalarm.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069:** Browser-Test mit dem Kandidaten: `report_monat` + `report_monat_pdf`. Falls HTML/PDF im Viewer zickt: Bugfix + Smoke-Test erweitern (gezielter auf das Problem).



## P-2026-01-05-04
- ZIP: `P-2026-01-05-04_smoketest-candidate-list.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `065224_gesammt.zip` = `90b77143a212d56a48d205ac0ccb80578751b6cd6bc7a9b8b634ac1462a4f2df`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a9bd896131e78224dfbe822c8946ff5cb6e8c52d10365e1a88a32b3cfda7391b`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kein Smoke-Test vorhanden, der eine Top-N Kandidatenliste (DB) inkl. Direktlinks + gezielter Kandidaten-Prüfung anbietet → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `SmokeTestController`: Neue „Kandidatenliste“ (Top-N) im DB-Fenster (Kommen/Gehen + Max/Tag) inkl. Direktlinks (`report_monat`, `report_monat_pdf`) und gezielter Kandidaten-Prüfung (PDF+HTML) per Auswahl aus der Liste.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069 (Fortsetzung):** Browser-Test mit dem besten Kandidaten (Mehrseiten) und Bugfixes nach Ergebnis.


## P-2026-01-05-05
- ZIP: `P-2026-01-05-05_smoketest-candidate-list-batch-eval.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `070341_gesammt.zip` = `5f422cfef5dfceb9a95574e328edfe2d5da5a6d48442ac5ec0c09e55c64d420d`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6974312fb56a1b9557f80cba583e576bc3d91793c950bfceb824dc01a9f639a9`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kandidatenliste existiert, aber ohne Batch-PDF-Auswertung (OK/FAIL + Seiten/Bytes) → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/SmokeTestController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** `SmokeTestController`: Kandidatenliste kann nun per Button als Batch geprüft werden: pro Kandidat PDF im Speicher erzeugen, Mehrseiten erkennen und in der Tabelle Status/Seiten/Bytes anzeigen.
- **TESTS:** `php -l controller/SmokeTestController.php`
- **NEXT:** **T-069 (Fortsetzung):** Mit einem **OK**-Kandidaten im Browser `report_monat` + `report_monat_pdf` testen; falls Viewer/Rendering-Probleme: Bugfix + Smoke-Test gezielt erweitern.


---

## P-2026-01-05-06_smoketest-synth-pdf-inline

### Ziel
- Den bereits vorhandenen **Synth-PDF Multi-Block+Multi-Page** Smoke-Check so erweitern, dass man das erzeugte PDF **direkt im Browser** (inline) öffnen kann.
- Damit kann man **Viewer/Rendering-Probleme** unabhängig von echten DB-Daten schnell prüfen.

### Änderungen (max. 3 Dateien)
1) `controller/SmokeTestController.php`
- Neu: `sendePdfInline()` (Buffer-Cleanup + no-gzip/no-transform Headers) für saubere PDF-Ausgabe.
- Neu: `erzeugePdfSynthMultipage()` (DB-unabhängige Monatsdaten, 3 Blöcke/Tag) → liefert PDF-Bytes.
- Neu: GET-Aktion `?seite=smoke_test&smoke_pdf=synth_multipage&jahr=YYYY&monat=MM` liefert Synth-PDF als `Content-Type: application/pdf` (inline).
- UI: Text angepasst + Link „Synth-PDF öffnen“ im Abschnitt **PDF-Synth-Check**.

2) `docs/DEV_PROMPT_HISTORY.md`
- SNAPSHOT + LOG aktualisiert.

### Smoke-Test
- Backend öffnen: `?seite=smoke_test`
- Abschnitt **PDF-Synth-Check** → Link „Synth-PDF öffnen“ (neuer Tab)
- Alternativ direkt: `?seite=smoke_test&smoke_pdf=synth_multipage&jahr=2026&monat=1`
- Erwartung: PDF lädt/öffnet und hat **>= 2 Seiten**.

### Pre-Flight Gate (D-005)
- Inputs gelesen + SHA256:
  - Projekt ZIP: `/mnt/data/071741_gesammt.zip` → `c726b2723135f0d820e3c137486d3f8fad4c200e66bf7b40a00c60d77c066d31`
  - Master Prompt: `docs/master_prompt_zeiterfassung_v6.md` → `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - Dev Prompt: `docs/DEV_PROMPT_HISTORY.md` → `21dea738c460327aa15e4fb6a818bf21cefb8f64859007505fd003c6f1cc6ee7`
  - SQL SoT: `sql/01_initial_schema.sql` → `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
- Duplicate-Check: Kein Eintrag für **P-2026-01-05-06** in SNAPSHOT/LOG vorhanden.

### Nächster Schritt
- Im Browser zuerst **Synth-PDF öffnen** (Viewer-Test ohne DB).
- Danach in der Smoke-Test Kandidatenliste einen **DB-MultiPage Kandidaten** auswählen und `report_monat_pdf` im selben Browser/Viewer öffnen. Falls der Viewer bei DB-PDFs zickt → gezielt bugfixen.


## P-2026-01-05-07
- ZIP: `P-2026-01-05-07_report-monat-ist-gesamt.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `080345_gesammt.zip` = `edc3cc5010e2970cb3003b999da2b2a169dfa6b037df4cfddebd1bd876fc345e`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `7ec61e84cc8d548fabc141134eaf52346ba18b5b435de3ddf37928e6a4d667e7`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Monatsübersicht zeigte bei Urlaub/Betriebsferien/Krank/Feiertag/Kurzarbeit in der bisherigen „Arbeitszeit“-Spalte weiterhin **0.00** → kein Patch vorhanden, der eine konsolidierte „Ist (gesamt)“-Anzeige ergänzt.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsübersicht: Spalte „Arbeitszeit“ in „Ist (gesamt)“ umbenannt und pro Tag als Summe aus Arbeitszeit + Arzt + Krank LFZ/KK + Feiertag + Kurzarbeit + Urlaub + Sonstiges angezeigt; optionaler Hinweis „Arbeit: x.xx“ nur wenn zusätzlich zur Arbeitszeit weitere Stunden anfallen.
- **TESTS:** `php -l views/report/monatsuebersicht.php`
- **NEXT:** Browser-Check `report_monat` (Jahr/Monat): Urlaub/Betriebsferien/Krank/Feiertag/Kurzarbeit-Tage zeigen nun in „Ist (gesamt)“ die Stunden; danach T-069 (Feldtest/Klicktest der Kernflows).


## P-2026-01-05-08
- ZIP: `P-2026-01-05-08_pdf-onepage-rowheight.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `081928_gesammt.zip` = `20845574af3c775afca377b1f0bcc579af84e0aeccd6df5c4477e1e8f8ba61c2`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ae361e15d98f0c146765d15678eb62317657e1fb0ac1d71d680a1cc47ea7da4f`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der die Tabellen-Zeilenhoehe im Monatsreport-PDF reduziert (um 1-Seiten-Faelle bei wenigen Mehrfach-Bloecken zu erhoehen).
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsreport-PDF: Tabellen-Zeilenhoehe von 16.0 auf 15.0 reduziert + Text-Baseline leicht angepasst, damit Monate mit wenigen Mehrfach-Kommen/Gehen-Zeilen typischerweise wieder auf **1 Seite** passen (Summen/Bemerkungen bleiben unten).
- **TESTS:** `php -l services/PDFService.php`
- **NEXT:** Browser-Check `report_monat_pdf` bei einem Monat, der bisher wegen 1-2 Mehrfach-Bloecken auf 2 Seiten gesprungen ist: soll jetzt haeufig wieder **1 Seite** sein. Falls weiterhin 2 Seiten (viele Bloecke) → Seitenaufteilung optional optimieren.

## P-2026-01-05-09
- ZIP: `P-2026-01-05-09_krankzeitraum-6wochen-vorschlag.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `094954_gesammt.zip` = `e675bd043bbec003e17419f72766cf738657633b5c47ba2eb51d18dd26f1f79f`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `11c4854d8be8cb25af481b71328129b7d036a9cf8141afe1cf5f5aedbde34624`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der in der Krankzeitraum-Adminmaske einen 6-Wochen-Vorschlag (LFZ→KK) anbietet.
- **DATEIEN (max. 3):**
  1) `controller/KonfigurationController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Konfiguration → Krankzeiten (Krankzeitraum): Anzeige eines optionalen „Wechsel nach 6 Wochen“-Hinweises (LFZ bis/KK ab) basierend auf Startdatum; Button setzt das Feld „Bis“ auf das LFZ-Ende; dynamisch aktualisiert beim Aendern von Typ/Startdatum.
- **TESTS:** `php -l controller/KonfigurationController.php`
- **NEXT:** T-069 Browser-/Feldtest der Kernflows (insb. Monatsübersicht + PDF) und Bugfixes nach echten Testergebnissen.


## P-2026-01-05-10
- ZIP: `P-2026-01-05-10_pdf-auto-compact-onepage.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `100922_gesammt.zip` = `4e9ba6be181a3fdbcce610b525e4e2ee5f1b0e2148a780da67634bc96d0ad03c`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `57926506b472215edc4a806076f2d9a2d2e0fa17ea387f2cb1c55bba669274e6`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der im Monatsreport-PDF bei Grenzfaellen automatisch die Tabellen-Zeilenhoehe minimal reduziert, um 1-Seiten-Ausgabe zu ermoeglichen (wenn Standard sonst knapp 2-seitig waere).
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsreport-PDF: Auto-Compact eingebaut – wenn die Standard-Zeilenhoehe (15.0) zu genau 2 Seiten fuehrt, wird testweise minimal auf 14.5/14.0 reduziert, falls dadurch wieder **1 Seite** moeglich ist; Text-Baseline nutzt nun die aktuelle Zeilenhoehe (sauber zentriert).
- **TESTS:** `php -l services/PDFService.php`
- **NEXT:** Browser-Check `report_monat_pdf` bei einem Monat, der bisher *knapp* wegen 1-2 Mehrfach-Bloecken auf 2 Seiten gesprungen ist: soll jetzt haeufig wieder **1 Seite** sein.


## P-2026-01-05-11
- ZIP: `P-2026-01-05-11_monatswerte-aus-tageswerten.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `102044_gesammt.zip` = `dde45061d977f2b04c3a69c53a875fbcef00c377f52126b6a983586bd106ff0e`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `2e8dda2c9f2ef59188da5f2f3efc13fffd17429cb75fc24e44003106f1e44558`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der die Monatskopf-Werte (Soll/Ist/Differenz) in der Monatsübersicht zuverlässig aus den Tageswerten ableitet, wenn `monatswerte_mitarbeiter` existiert und Abwesenheiten (Urlaub/Betriebsferien/Krank/Feiertag) sonst nicht sauber in IST einfliessen.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
  3) `docs/master_prompt_zeiterfassung_v6.md`
- **DONE:** Monatsübersicht: Monatskopf (Soll/Ist/Differenz) wird fuer die Anzeige aus den berechneten Tageswerten abgeleitet und bei Abweichungen gegenueber `monatswerte_mitarbeiter` korrigiert (Betriebsferien→Urlaub/Krank/Feiertag zaehlen in IST; Kurzarbeit reduziert Soll). Dadurch passen die Werte oben besser zur Tagesliste.
- **TESTS:** `php -l services/ReportService.php`
- **NEXT:** Browser-Check Monatsübersicht (Monatskopf vs Tagesliste) in einem Monat mit Betriebsferien/Krank/Feiertag und ggf. Kurzarbeit; danach entscheiden, ob `Ist (gesamt)`/PDF-Summen die Kurzarbeit-Stunden ebenfalls strikt nach MasterPrompt behandeln sollen (Kurzarbeit = Soll-Reduktion, nicht IST).


## P-2026-01-05-12
- ZIP: `P-2026-01-05-12_pdf-bottom-reserve-auto-compact.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `103423_gesammt.zip` = `e9806bc653e5eb17042695c9ea88a16655429b1f86594239138f0e98c5d95305`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `35298538053110324d06ef223cd38de130918b210ff0dd38bf5e08a2c050cc82`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: P-2026-01-05-10 hat Auto-Compact nur ueber Zeilenhoehe (rowH) – kein Patch vorhanden, der im Grenzfall zusaetzlich die Bottom-Reserve (Summen/Bemerkungen) dynamisch nach unten verschiebt, um **1 weitere Tabellenzeile** auf Seite 1 zu gewinnen.
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsreport-PDF: Auto-Compact erweitert – falls trotz minimaler Zeilenhoehen-Reduktion weiterhin genau 2 Seiten entstehen, wird im Grenzfall die Bottom-Reserve stufenweise reduziert (210→195/185/180) und Summen/Bemerkungen entsprechend nach unten geschoben, sofern dadurch wieder **1 Seite** moeglich ist (echte Mehrseiten-Monate bleiben unveraendert).
- **TESTS:** `php -l services/PDFService.php`
- **NEXT:** Browser-Check `report_monat_pdf` bei einem Monat, der bisher *knapp* auf 2 Seiten war: sollte jetzt haeufig wieder **1 Seite** sein. Falls weiterhin 2 Seiten (viele Bloecke) → echte Mehrseiten-Ausgabe akzeptieren oder spaeter weitere Kompakt-Optionen (z. B. Notes maxLines/Spacing) pruefen.

## P-2026-01-05-13
- ZIP: `P-2026-01-05-13_ist-gesamt-ohne-kurzarbeit.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `104444_gesammt.zip` = `be6473c850ec81ffb5290ea2758f1ecbe6467d3546b217d998dbd1f45c7b498c`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `2b2243b0bf88c878493ca79bc371a38820dcc202e9f4619761f166622dee3d4b`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: P-2026-01-05-07 fuehrte "Ist (gesamt)" ein, zaehlte aber Kurzarbeit mit; kein spaeterer Patch vorhanden, der die IST-Definition in `views/report/monatsuebersicht.php` an MasterPrompt (Kurzarbeit = Soll-Reduktion, nicht IST) angleicht.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsübersicht: "Ist (gesamt)" pro Tag summiert jetzt nur Arbeitszeit + bezahlte Abwesenheiten (Arzt/Krank/Feiertag/Urlaub/Sonstiges) und **nicht** mehr Kurzarbeit; Kurzarbeit bleibt als eigene Spalte sichtbar.
- **AKZEPTANZ:** Ein Tag mit 8.00h Kurzarbeit und 0.00h Arbeitszeit zeigt in "Ist (gesamt)" **0.00**, waehrend die Kurzarbeit-Spalte **8.00** zeigt.
- **TESTS:** `php -l views/report/monatsuebersicht.php`
- **NEXT:** Browser-Check `report_monat` mit einem Kurzarbeit-Tag (ohne Arbeitszeit) und einem Kurzarbeit-Tag (mit Arbeitszeit) – "Ist (gesamt)" darf Kurzarbeit nicht zaehlen, Monatskopf (Soll/Ist/Differenz) muss dazu passen; danach `report_monat_pdf` (1 Seite vs echte Mehrseite) pruefen.


## P-2026-01-05-14
- ZIP: `P-2026-01-05-14_pdf-header-pagecount.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `114733_gesammt.zip` = `aac6cb68ca2934a8c6578720192e41eb72ab0fcc0e6dab3ed0be10dbe9a472b4`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v6.md` = `2cb60b998d891d776e3b62fde7cbe4a2c6fd4cf7a1ffbbcd97afaeb4c34f6595`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `f7e1075355a094c0d04ceb0da126ed3804e8d6d09accef95b9ed918551b974bc`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der bei `report_monat_pdf` eine Seitenanzahl als Response-Header ausgibt (Feldtest-Hilfe fuer Grenzfall-Checks 1 Seite vs 2 Seiten).
- **DATEIEN (max. 3):**
  1) `controller/ReportController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Monatsreport-PDF (`report_monat_pdf`): Response sendet optional `X-Zeiterfassung-PDF-Pages: N` (aus `/Type /Pages ... /Count N` extrahiert, Fallback: Page-Objekte zaehlen), um im Browser schnell zu sehen, ob ein Monat durch Auto-Compact/Bottom-Shift wieder 1-seitig wurde.
- **AKZEPTANZ:** Beim Aufruf von `?seite=report_monat_pdf&jahr=YYYY&monat=MM` ist im HTTP-Response-Header `X-Zeiterfassung-PDF-Pages` gesetzt (N>0).
- **TESTS:** `php -l controller/ReportController.php`
- **NEXT:** T-069 Browser-Check (Monatsübersicht/PDF) – Seitenanzahl kann nun direkt ueber den Response-Header verifiziert werden.



## P-2026-01-05-15
- ZIP: `P-2026-01-05-15_terminal-layout-97-percent.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `135331_gesammt.zip` = `28780d2a919a821e24ff9c7d64bc30a4cdcaf4627d7b5b3b0dccc25e0a9a6285`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v7.md` = `b0c5f2797cca43dd72b404754d9900961583954fa8b708cefa00ba09a1b8439e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `5d3e4de60bf80aa3a4bba6c3d4f08b3d1296a3d1acfd50c019af7183a2d311c7`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der das Terminal-Layout auf ~97% Screen skaliert und im Kopfbereich eine sichtbare Uhr (Platzhalter) ausgibt.
- **DATEIEN (max. 3):**
  1) `public/css/terminal.css`
  2) `views/terminal/_layout_top.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Terminal-UI: `main` fuellt nun im Kiosk typischerweise ~97% (97vw/97vh) und zeigt oben rechts einen Uhr-Platzhalter (`#terminal-uhr`) als Vorbereitung fuer die laufende Uhr.
- **AKZEPTANZ:** Beim Öffnen des Terminals ist der Inhalt nahezu vollflaechig (~97%) und oben rechts ist eine Uhr (Platzhalter) sichtbar.
- **TESTS:** `php -l views/terminal/_layout_top.php`
- **NEXT:** T-077 (Teil 2): laufende Uhr per JS (Systemzeit, Format `HH:MM:SS DD-MM-YYYY`) implementieren.


## P-2026-01-05-16
- ZIP: `P-2026-01-05-16_terminal-clock-live.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `150357_gesammt.zip` = `ac55c2b90d83ded477e03d78acd91497540ad2a152843e7f731aa31ec3c64699`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v7.md` = `b0c5f2797cca43dd72b404754d9900961583954fa8b708cefa00ba09a1b8439e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `e944db9575ddde0a4c47218b75e438f39c0d6905906ade744e56fd722f27e079`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: P-2026-01-05-15 setzte nur den Uhr-Platzhalter (`#terminal-uhr`); kein Patch vorhanden, der die Uhr live (Sekunden-Takt, Systemzeit) aktualisiert.
- **DATEIEN (max. 3):**
  1) `views/terminal/_autologout.php`
  2) `public/js/terminal-autologout.js`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Terminal-UI: Uhr im Kopfbereich (`#terminal-uhr`) wird jetzt per JS jede Sekunde aktualisiert (Format `HH:MM:SS DD-MM-YYYY`); Script wird auch auf dem Login-Screen geladen, Auto-Logout/Countdown bleibt nur bei eingeloggtem Mitarbeiter aktiv.
- **AKZEPTANZ:** Auf dem Terminal (auch auf `terminal.php?aktion=start`) tickt die Uhr oben rechts sichtbar jede Sekunde im Format `HH:MM:SS DD-MM-YYYY`.
- **TESTS:** `php -l views/terminal/_autologout.php`
- **NEXT:** T-077 (Teil 3): Terminal-Startscreen-Text/Label auf **nur RFID** reduzieren (keine Hinweise mehr auf Personalnummer/Mitarbeiter-ID; Funktionalitaet bleibt intern fuer Demo).


## P-2026-01-05-17
- ZIP: `P-2026-01-05-17_terminal-login-text-nur-rfid.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `152135_gesammt.zip` = `ea6059df89a0b6ded130344ead3163891a3665d175d63c66ea6eaf881b234392`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v7.md` = `b0c5f2797cca43dd72b404754d9900961583954fa8b708cefa00ba09a1b8439e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `f97e87821f44d1ca7c5ec67cdaef7be33f7369aaf484e5825befc3f64b8513a6`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der auf `terminal.php?aktion=start` die Login-Texte/Labels auf **nur RFID** reduziert (ohne Hinweise auf Personalnummer/Mitarbeiter-ID/ID).
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Terminal: Startscreen-Login-Hinweis und Feld-Label zeigen nur noch **RFID**; keine Hinweise mehr auf Personalnummer/Mitarbeiter-ID/ID im Login-Text.
- **AKZEPTANZ:** Auf `terminal.php?aktion=start` steht als Login-Hinweis/Label nur noch **RFID** (kein Personalnummer/Mitarbeiter-ID/ID-Text).
- **TESTS:** `php -l views/terminal/start.php`
- **NEXT:** T-069 Feldtest/Klicktest der Kernflows (Terminal + Monatsübersicht + Monats-PDF) und danach nur Bugfix-Patches.


## P-2026-01-05-18
- ZIP: `P-2026-01-05-18_masterprompt-v8-terminal-urlaub-ui.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `153135_gesammt.zip` = `612a2f00d0c829cf091bd1435e4605bbd3944dee835e18202151350e0e81e346`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v7.md` = `b0c5f2797cca43dd72b404754d9900961583954fa8b708cefa00ba09a1b8439e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `b5747cf8f55c7fba00b61f10690a6c5e63d839918b037736b28c2141043f67fc`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der den MasterPrompt auf v8 erweitert (Terminal-Buttons Kommen/Gehen priorisieren + Urlaubssaldo/Übertrag anzeigen + doppelte Zeit entfernen).
- **DATEIEN (max. 3):**
  1) `docs/master_prompt_zeiterfassung_v8.md`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** MasterPrompt v8 ergänzt um Terminal-UI-Regeln (Kommen/Gehen doppelte Höhe/oben; doppelte Zeit-/Datumsanzeige entfernen; Urlaubsübersicht im Terminal) und Urlaub-Übertrag-Logik (Vorjahr → aktuelles Jahr).
- **AKZEPTANZ:** MasterPrompt v8 enthält die neuen, verbindlichen Regeln und widerspricht den bisherigen Regeln nicht.
- **TESTS:** (keine, reine Doku-Änderung)
- **NEXT:** T-078 (Teil 1) als Code-Patch umsetzen (Terminal-Hauptmenü: Button-Reihenfolge/Höhe + doppelte Zeit entfernen).


## P-2026-01-05-19
- ZIP: `P-2026-01-05-19_terminal-ui-kommen-gehen-urlaub-compact.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `155453_gesammt.zip` = `7fe459c8e60149b9373a4dd16339d307a8da5a6c5802ff3ce41eb7e53694df11`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6188724e98451eafa63a93636e0957d63c722cfb38d2f50cbc206c47cb424b02`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: kein Patch vorhanden, der am Terminal die Kommen/Gehen-Buttons doppelt hoch/immer erste Zeile rendert, die doppelte Zeitanzeige entfernt und die Urlaubsübersicht (Übertrag + Jahr) direkt nach Login sichtbar macht.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `public/css/terminal.css`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:** Terminal-UI nach MasterPrompt v8:
  - Kommen/Gehen ist jetzt die **Primäraktion**: immer **erste Zeile**, **doppelte Höhe**.
  - Urlaub-Button ist darunter (nicht in derselben Zeile wie Kommen/Gehen).
  - "Angemeldet als ..." wird nicht doppelt angezeigt: Login-Nachricht wird unterdrückt, Mitarbeiterbox bleibt.
  - Mitarbeiterbox zeigt eine kompakte **Urlaubsübersicht** (Übertrag Vorjahr + aktuelles Jahr), auch wenn noch kein Kommen gebucht wurde.
  - Doppelte Zeit/Datum in der Statusbox wird entfernt (Statusbox-Titel-Zeit ausgeblendet, Header-Uhr ist die einzige Zeitquelle).
- **AKZEPTANZ:**
  - Nach Login (noch nicht anwesend): oben ein großer "Kommen"-Button; "Urlaub beantragen" darunter.
  - Nach Kommen: oben ein großer "Gehen"-Button.
  - Im Screen steht die Uhrzeit nur einmal (oben rechts im Header) im Format `HH:MM:SS DD-MM-YYYY`.
  - Es gibt keine doppelte "Angemeldet als ..." Anzeige.
  - Urlaub: wenn Kontingent fehlt, steht sichtbar "Kontingent für YYYY nicht gepflegt" statt "Keine Daten".
- **TESTS:** `php -l views/terminal/start.php`
- **NEXT:** T-079 (Urlaub-Logik): automatischer Übertrag (Vorjahr → aktuelles Jahr), Verbrauchsreihenfolge Übertrag→Jahr, Betriebsferien-Abzug in der Berechnung.

## P-2026-01-05-21
- ZIP: `P-2026-01-05-21_urlaub-uebertrag-auto.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `172929_gesammt.zip` = `f1aef037feb110355e44c0da05f766e6613d2ff1ffe2acb8f612a61d1fa7038b`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6782f1fc00d1203abe2d5a4b3b513861f25264427d55e8d697a6b9d8714acb9d`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: es gab bisher keinen Patch, der den Urlaub-Übertrag automatisch aus dem Vorjahres-Resturlaub ableitet.
- **DATEIEN (max. 3):**
  1) `services/UrlaubService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `UrlaubService::berechneUrlaubssaldoFuerJahr()` setzt `uebertrag` für Jahr YYYY automatisch als `max(verbleibend(YYYY-1), 0)`.
  - Die Vorjahres-Berechnung nutzt dafür den Saldo von (YYYY-1) mit deaktiviertem Auto-Übertrag (legacy), um Rekursion zu vermeiden.
  - Fehlerfall: kein Hard-Crash; Hinweistext + optional Logger::warn.
- **TESTS:**
  - `php -l services/UrlaubService.php`
- **NEXT:**
  - T-079 (Teil 2): Backend „Meine Urlaubsanträge“ – gleiche Aufschlüsselung wie im Terminal anzeigen.


## P-2026-01-05-22
- ZIP: `P-2026-01-05-22_urlaub-meine-saldo-split.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `174813_gesammt.zip` = `a4f6142d5ae604eee1006861f1a95c9f04c8b9d66603f77044a85ddb93c4ab22`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `cb71d92e8566596c581ec2d1fa0e04e889d8931aab47d1ad3c196883afe79e58`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: T-079 (Teil 2) war noch offen; Backend-View zeigte Urlaubssaldo bisher ohne Aufschlüsselung nach Verbrauchsreihenfolge.
- **DATEIEN (max. 3):**
  1) `views/urlaub/meine_antraege.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend „Meine Urlaubsanträge“: Saldo-Box zeigt jetzt explizit `Übertrag (YYYY-1)`, `YYYY`, `Gesamt verfügbar`, `Genehmigt`, `Offen` und berechnet Übertrag/Jahr nach Verbrauchsreihenfolge (Übertrag → Jahr).
  - **AKZEPTANZ:** Auf `?seite=urlaub_meine` zeigt die Saldo-Box die Zeilen „Übertrag (YYYY-1)“, „YYYY“, „Gesamt verfügbar“, „Genehmigt“ und „Offen“ mit Werten aus dem UrlaubService.
- **TESTS:** `php -l views/urlaub/meine_antraege.php`
- **NEXT:** T-069 (Teil 1) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.
## P-2026-01-05-23
- ZIP: `P-2026-01-05-23_urlaub-admin-uebertrag-auto.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `175659_gesammt.zip` = `5c4fbe25d3ccae0d318bc1b3fe0836b752528b0081904ab29c548344736c4457`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `98e9e10bf041325b43ea9a5cfe4a15a86c4fac956304b128093a857736e3d5f6`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Admin-Kontingent erlaubte bisher manuelles Pflegen von `uebertrag_tage`, obwohl Übertrag ab v8 automatisch aus Vorjahr-Rest berechnet wird.
- **DATEIEN (max. 3):**
  1) `controller/UrlaubKontingentAdminController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Admin-UI: Übertrag wird als „auto“ dargestellt (kein Eingabefeld), Hinweistext ergänzt; Tabellen-Spalte als „legacy“ gekennzeichnet.
  - Speichern: POST ignoriert `uebertrag_tage`; INSERT/UPDATE schreibt nur Anspruch-Override, Korrektur, Notiz.
- **TESTS:**
  - `php -l controller/UrlaubKontingentAdminController.php`
- **NEXT:**
  - T-069 (Teil 1): Terminal-Kernflow klicktesten und Auffälligkeiten als B-IDs im LOG festhalten.

## P-2026-01-05-24
- ZIP: `P-2026-01-05-24_urlaub-kontingent-manuell-klarstellen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `181950_gesammt.zip` = `7708530176b988f79f7d5345198518fd93953542c5e90c9e208611d55e33725a`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6c508946d4fd2af8517c835308c2d820e2d06ac1b9350031db6af4503cb6bf61`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kontingent-Admin hatte das Korrektur-Feld bereits, aber „Urlaubstage einfach so eintragen“ war in Übersicht/Formular nicht eindeutig; die frühere Anzeige konnte als manuell pflegbarer Übertrag missverstanden werden.
- **DATEIEN (max. 3):**
  1) `controller/UrlaubKontingentAdminController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Admin-Übersicht: Übertrag ist sichtbar als „auto“ (kein Legacy-Zahlenwert), Spalte „Manuell (+/- Tage)“ klar benannt.
  - Bearbeiten-Formular: Feld „Manuell (+/- Tage)“ erklärt klar, dass hier Urlaubstage direkt gut-/abbuchbar sind (auch negative Werte); Hinweistext angepasst.
- **TESTS:**
  - `php -l controller/UrlaubKontingentAdminController.php`
- **NEXT:** T-069 (Teil 1a): Terminal – Login via RFID → Startscreen-Status/Buttons (nicht anwesend) klicken und Auffälligkeiten als B-IDs im LOG festhalten.

## P-2026-01-05-25
- ZIP: `P-2026-01-05-25_terminal-start-debugpanel.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `183805_gesammt.zip` = `123c2ddd1f6efe54656731ef94a6cd2deda596897afef5b60ae7bf087aa7a0d3`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a5a6b6cc30927dd38e3e7bcdcd607184f9ae5c9445dac05bf01ea5837ba37bb4`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: es gab noch kein Debug-Panel am Terminal-Startscreen; T-069 (Teil 1a) ist explizit „klicken & Bugs sammeln“ → Helper ist neu, ohne Fachlogik zu ändern.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Startscreen: optionales Debug-Panel (nur bei `&debug=1`), das DB/Queue-Status, Session-Status (`terminal_anwesend`), berechnete Anwesenheit und Kommen/Gehen-Zähler anzeigt.
  - **AKZEPTANZ:** Auf `public/terminal.php?aktion=start&debug=1` ist ein „Debug (T-069)“-Block sichtbar; ohne `debug` bleibt der Screen unverändert.
- **TESTS:** `php -l views/terminal/start.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.


## P-2026-01-05-26
- ZIP: `P-2026-01-05-26_terminal-bugreport-log.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `185257_gesammt.zip` = `4597dc15f15565a7abc9bfecbdec3aacebea548d52da795591e442e57169a85d`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `4907dcebf214691149702a1497b4c2ffd848c5fd56c664224e6a703181ffaf8d`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: es gab bisher keinen Patch, der im Terminal-Startscreen (Debug) ein Bugreport-Formular anbietet und Notizen in `system_log` persistiert.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal (nur bei `&debug=1`): Debug-Block enthält ein Bugreport-Formular (Bug-ID optional + Text).
  - POST auf `terminal.php?aktion=start&debug=1` mit `bugreport_submit` schreibt via `Logger::warn` in `system_log` (Kategorie `terminal_bug`) inkl. Kontext (Session-Anwesenheit + Queue-Kurzstatus).
  - Danach Redirect zurück auf Startscreen mit Flash „Bug-Notiz gespeichert (LOG).“.
- **AKZEPTANZ:**
  - Auf `public/terminal.php?aktion=start&debug=1` ist im Debug-Block ein Formular „Bug-Notiz speichern“ sichtbar.
  - Nach Absenden steht im `system_log` ein Eintrag mit Kategorie `terminal_bug` und der eingegebenen Notiz.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.

## P-2026-01-07-01
- ZIP: `P-2026-01-07-01_urlaub-kontingent-fallback-select.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `062918_gesammt.zip` = `7b553262fb7e63653f29870198514832cabfb761da82b8e9dfcfaec972e4bb84`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6ac2dea0327e9a10b3ca9e57b3ae032a907a337d967a5fd37bcbdc1ab7c56b1a`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `6669b868b48b8a438809ded5395a3c93a807e3fafae76d9644592d79e4d507f3`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kontingent-Admin war bereits vorhanden (P-2026-01-05-23/24), aber „Mein Urlaub“ zeigte weiterhin den Hinweis „DB-Update fehlt?“ und ignorierte Korrekturen, wenn `uebertrag_tage` in der DB fehlte.
- **DATEIEN (max. 3):**
  1) `services/UrlaubService.php`
  2) `sql/13_migration_urlaub_kontingent_uebertrag_tage.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - UrlaubService: SELECT auf `urlaub_kontingent_jahr` ist jetzt robust – wenn `uebertrag_tage` fehlt, wird per Fallback trotzdem `anspruch_override_tage`/`korrektur_tage` geladen (kein „alles 0“ mehr).
  - Migration 13: ergänzt `uebertrag_tage` idempotent, falls die Spalte in einer bestehenden DB noch fehlt.
- **TESTS:**
  - `php -l services/UrlaubService.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.

## P-2026-01-07-02
- ZIP: `P-2026-01-07-02_urlaub-kontingent-auto-uebertrag-hinweis.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `064423_gesammt.zip` = `fe1b1a902d7db22a516645ebda2b9c89e55ae4be164e31084bd076af88b731be`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `b85c8bd35ac23c5feef7a6d23d49b7fa3e5dc67043c0801fce27a3a05a4b0c28`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `79d311b22995ae4ca1afbea557b52c8da5838d5b88a87ca1109605df1a045281`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Kontingent-Admin erklärt „Manuell (+/- Tage)“ zwar als Korrektur, aber es ist in der Praxis nicht klar, wie man den Auto-Übertrag (Rest Vorjahr) gezielt reduziert/erhöht (führt zu Fehlbedienung wie „+5 statt 5 Übertrag“).
- **DATEIEN (max. 3):**
  1) `controller/UrlaubKontingentAdminController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Bearbeiten-Formular zeigt jetzt den aktuellen Auto-Übertrag als Zahl (Resturlaub Vorjahr) und eine klare Formel/Beispiel, wie „Manuell“ den Auto-Übertrag korrigiert.
  - Hinweistext ergänzt: „Manuell“ addiert sich zum Auto-Übertrag.
- **TESTS:**
  - `php -l controller/UrlaubKontingentAdminController.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.


## P-2026-01-07-03
- ZIP: `P-2026-01-07-03_urlaub-mein-urlaub-kontingent-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `070658_gesammt.zip` = `8207b2e726c7bcdf2fb3551ff8597a9cc05b6da067ba6ba70e3e2fdfe69a453b`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `adc9c9e61856ed9dc5677f2c8b2d722c4ed244b055d606684c5a689390063f7c`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `79d311b22995ae4ca1afbea557b52c8da5838d5b88a87ca1109605df1a045281`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Symptom war identisch zu B-013 (literales `\\n`), aber diesmal im UrlaubService → führte zu "DB-Update fehlt?" und ignorierter Manuell-Korrektur in "Mein Urlaub".
- **DATEIEN (max. 3):**
  1) `services/UrlaubService.php`
  2) `views/urlaub/meine_antraege.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - UrlaubService: Kontingent-SELECTs verwenden keine literalen `\\n` mehr → kein SQL-Syntaxfehler, Korrektur/Override greifen wieder, Hinweis verschwindet.
  - Mein Urlaub: "Manuell (+/- Tage)" wirkt jetzt zuerst auf den Auto-Übertrag; falls dadurch < 0, wird der Rest negativ vom Jahresanspruch abgezogen (Summe bleibt exakt).
- **TESTS:**
  - `php -l services/UrlaubService.php`
  - `php -l views/urlaub/meine_antraege.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.

## P-2026-01-07-04
- ZIP: `P-2026-01-07-04_urlaub-antrag-0tage-block.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `072121_gesammt.zip` = `c19b931c59198f8613f9c7445e26ca965895bd90448be43ceedbe2e51aecb195`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `de0f4e708ad2da6a32c52ce3d6d8d7e7f3c082c93b8bc405552c3d2d724e7eac`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `79d311b22995ae4ca1afbea557b52c8da5838d5b88a87ca1109605df1a045281`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: es gab bisher keinen Patch, der beim Anlegen eines Urlaubsantrags im Backend explizit verhindert, dass ein Antrag mit **0.00 Tagen** (komplett Wochenende/Feiertag/Betriebsferien) gespeichert wird.
- **DATEIEN (max. 3):**
  1) `controller/UrlaubController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Urlaubsantrag anlegen wird geblockt, wenn der Zeitraum **keine verrechenbaren Urlaubstage** ergibt (tage_gesamt <= 0.00).
  - Nutzer bekommt eine klare Fehlermeldung; Formular bleibt geöffnet.
- **TESTS:**
  - `php -l controller/UrlaubController.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und danach micro-Bugfix-Patches.


## P-2026-01-07-05
- ZIP: `P-2026-01-07-05_mein-urlaub-jahrfilter-betriebsferien-hinweis.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `073959_gesammt.zip` = `ad9221db430a94c85b720a75ac54fce6de04d3292983d308ac16adde161bd820`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `52dd9eb1892ab4288f594e0a0818d3c5cf77af59ef8478c9e6cf27337451bae4`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `79d311b22995ae4ca1afbea557b52c8da5838d5b88a87ca1109605df1a045281`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: „Mein Urlaub“ hatte keinen klaren Kontext, ob die Liste für das aktuelle Jahr oder alle Jahre gilt; zusätzlich war unklar, ob Betriebsferien vom Urlaubssaldo abgezogen werden.
- **DATEIEN (max. 3):**
  1) `views/urlaub/meine_antraege.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - „Mein Urlaub“ zeigt standardmäßig die Anträge für das aktuelle Saldo-Jahr; optional `?alle=1` zeigt Anträge aus allen Jahren (Link wird nur angezeigt, wenn tatsächlich Anträge außerhalb des Jahres existieren).
  - Hinweistext ergänzt: Betriebsferien werden automatisch als Urlaub berücksichtigt (und sind in „Genehmigt“ enthalten). Wochenenden/Feiertage zählen nicht als Urlaubstage.
- **TESTS:**
  - `php -l views/urlaub/meine_antraege.php`
- **NEXT:** Dashboard-Zeitwarnung per Recht steuerbar machen (statt hart über Rollen).


## P-2026-01-07-06
- ZIP: `P-2026-01-07-06_dashboard-zeitwarnung-unpaired-kommen-gehen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `080118_gesammt.zip` = `acc8dc946215d1b2aecff3b1ad3ded47f75dec3259e40c90e872b510eccfc58c`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `52dd9eb1892ab4288f594e0a0818d3c5cf77af59ef8478c9e6cf27337451bae4`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `79d311b22995ae4ca1afbea557b52c8da5838d5b88a87ca1109605df1a045281`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: es gab bisher keinen Dashboard-Hinweis, der für Chef/Personalbüro/Vorarbeiter unplausible Kommen/Gehen-Stempel (ungerade Anzahl pro Tag) sichtbar macht.
- **DATEIEN (max. 3):**
  1) `controller/DashboardController.php`
  2) `views/dashboard/index.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard: Für Chef/Personalbüro/Vorarbeiter wird ein roter Warnblock angezeigt, wenn in den letzten 14 Tagen pro Mitarbeiter+Tag eine **ungerade** Anzahl Kommen/Gehen-Buchungen gefunden wird.
  - Heuristik für „heute“: Warnung nur, wenn der letzte Stempel älter als 10 Stunden ist (sonst kann die Schicht noch laufen).
  - Warnblock listet Mitarbeiter + Datum + Kommen/Gehen-Zähler und bietet „Monat öffnen“-Link auf den passenden Monatsreport.
- **AKZEPTANZ:**
  - Wenn ein Mitarbeiter z. B. am 05.01.2026 nur „Kommen“ ohne „Gehen“ gestempelt hat, sieht ein Chef nach dem Login auf dem Dashboard einen roten Hinweis inklusive Mitarbeiter+Datum.
- **TESTS:**
  - `php -l controller/DashboardController.php`
  - `php -l views/dashboard/index.php`
- **NEXT:** Optional: Detail-Ansicht/Link direkt auf Tagesansicht (Tag vorselektiert) und Warnung für „Gehen ohne Kommen“ explizit ausformulieren.


## P-2026-01-07-07
- ZIP: `P-2026-01-07-07_dashboard-zeitwarnung-recht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `081428_gesammt.zip` = `8b612648d990caaaf1fbc0685aa1059f1a9dc9dc192ce275072ffeb9b71f9858`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `a6ebc5d25faa11bc4b53aae6a66dedf1306b606b9e62939cba45f690d46edadc`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `79d311b22995ae4ca1afbea557b52c8da5838d5b88a87ca1109605df1a045281`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: Dashboard-Zeitwarnung war bisher hart auf Rollen (Chef/Personalbüro/Vorarbeiter) codiert und konnte nicht sauber über Rollenrechte gesteuert werden.
- **DATEIEN (max. 3):**
  1) `controller/DashboardController.php`
  2) `sql/14_migration_dashboard_zeitwarnungen_recht.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard-Zeitwarnung ist jetzt über das Recht `DASHBOARD_ZEITWARNUNGEN_SEHEN` steuerbar (Fallback auf Rollen für Legacy/Setup).
  - Migration 14 legt das Recht an und weist es standardmäßig Chef/Personalbüro/Vorarbeiter zu (falls die Rollen existieren).
- **AKZEPTANZ:**
  - Ein Benutzer ohne Recht `DASHBOARD_ZEITWARNUNGEN_SEHEN` sieht den roten Warnblock nicht; mit Recht ist er sichtbar (bei vorhandenen Unstimmigkeiten).
- **TESTS:**
  - `php -l controller/DashboardController.php`
- **NEXT:** Optional: Mitarbeiter-spezifische Rechte (Override/Entzug pro Mitarbeiter) ergänzen, ohne die Rollenrechte zu ändern.


## P-2026-01-07-08
- ZIP: `P-2026-01-07-08_mitarbeiter-rechte-override-core.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `083628_gesammt.zip` = `07eec26c5c20728834e50b051cd1e591204af1b3bf04ddb277f9ad1f260e1bac`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `36cca7430b9a3976293ed22f76d16bfd0de26fca1266797d717dd109b752fbf2`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `7c8777200e6191add24ebac85d7d5f4176f30767dbd398b8286580cf43847b39`
- **DUPLICATE-CHECK:** geprüft gegen SNAPSHOT/LOG: es gab bisher keine Möglichkeit, Rechte **pro Mitarbeiter** zu erlauben/entziehen, ohne Rollenrechte zu verändern.
- **DATEIEN (max. 3):**
  1) `services/AuthService.php`
  2) `sql/15_migration_mitarbeiter_rechte_override.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neue Tabelle `mitarbeiter_hat_recht` (Allow/Deny pro Mitarbeiter) via Migration 15.
  - `AuthService` berücksichtigt jetzt die Overrides:
    - `erlaubt=1` => Recht wird hinzugefügt (auch ohne Rollenrecht)
    - `erlaubt=0` => Recht wird entzogen (trotz Rollenrecht)
  - Fail-safe: Wenn die Override-Tabelle (noch) nicht existiert, läuft alles wie bisher.
- **AKZEPTANZ:**
  - Ein Mitarbeiter ohne passende Rolle kann z. B. trotzdem `DASHBOARD_ZEITWARNUNGEN_SEHEN` bekommen (Override allow).
  - Ein Mitarbeiter mit Rolle kann ein Recht explizit entzogen bekommen (Override deny).
- **TESTS:**
  - `php -l services/AuthService.php`
- **NEXT:** UI/Admin: Overrides pro Mitarbeiter editierbar machen (Mitarbeiter bearbeiten → Rechte-Allow/Deny-Liste), inkl. Anzeige „effektiv“.


## P-2026-01-07-09
- ZIP: `P-2026-01-07-09_auth-rechte-cache-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `084507_gesammt.zip` = `525bef8c8ace3976015cbe5e8f8bc2f347aa3d5cf0e902283077dd81174ff630`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `86f794d4941bd48c838c37744991680071638bec82e33a911369bce319606d17`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** geprüft gegen P-2026-01-07-08: im Session-Cache-Zweig von `holeAngemeldeteRechteCodes()` wurden Overrides ein zweites Mal geladen, aber mit undefinierten Variablen; dadurch konnten Overrides nach dem Caching effektiv ignoriert werden.
- **DATEIEN (max. 3):**
  1) `services/AuthService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `AuthService`: Cache-Zweig (`$_SESSION` Rechte) wird jetzt nur noch defensiv normalisiert + case-insensitiv dedupliziert.
  - Hinweis: Overrides werden weiterhin beim initialen DB-Laden in `ladeRechteCodesAusDb()` eingerechnet und bleiben damit stabil.
- **AKZEPTANZ:**
  - Ein Allow/Deny-Override wirkt konsistent auch nach mehrfachen Requests/Checks innerhalb derselben Session (kein „Verschwinden“ durch Cache-Zweig).
- **TESTS:**
  - `php -l services/AuthService.php`
- **NEXT:** T-080 UI/Admin: Overrides pro Mitarbeiter editierbar machen (Mitarbeiter bearbeiten → Rechte-Allow/Deny-Liste), inkl. Cache-Reset.


## P-2026-01-07-10
- ZIP: `P-2026-01-07-10_mitarbeiter-rechte-override-ui.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `085650_gesammt.zip` = `1068d4b348b9fd6db7a03a6ff7d14c7982b57b96ef94cbe56c52923206c15887`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `64632395fe995afa182c51c2dd1b7a429a19ec1176599e91571a68f0b6333cc1`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** T-080 UI war in diesem Code-Stand noch nicht umgesetzt (kein Override-UI im Mitarbeiter-Formular; keine Übergabe/Speicherung der Overrides im Bearbeiten-Flow).
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter bearbeiten: neue Sektion „Rechte-Overrides“ (vererbt/erlauben/entziehen) pro Recht.
  - Controller: lädt Rechte + aktuelle Overrides; speichert Overrides beim Speichern; leert Session-Rechte-Cache, wenn der Admin seinen eigenen Datensatz editiert.
- **AKZEPTANZ:**
  - Ein Admin kann einem Mitarbeiter ein Recht explizit erlauben/entziehen und sieht die Auswahl nach dem Speichern wieder im Formular.
- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
  - `php -l views/mitarbeiter/formular.php`
- **NEXT:** T-080 (Teil 2): Anzeige „effektiv“ (Ergebnis aus Rollen+Override) in der Liste.


## P-2026-01-07-11
- ZIP: `P-2026-01-07-11_mitarbeiter-rechte-effektiv-anzeige.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `091153_gesammt.zip` = `43903b4b6e514a3b0875d89703756d490aa5eec3173cfd17901febd7b8f7d5d9`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ad882d78183a486a24d4b5444ccac59093ed658fce75eddf881e043e9353f0a2`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** T-080 (Teil 2) war im SNAPSHOT als OFFEN gelistet (kein „Effektiv“ in der Rechte-Override-Liste). In diesem Patch umgesetzt.
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter bearbeiten: In der Rechte-Overrides-Tabelle wird pro Recht eine Spalte **„Effektiv“** angezeigt (Rolle + Override).
  - Effektive Rechte werden analog AuthService berechnet (nur aktive Rechte; Allow/Deny-Override hat Vorrang).
- **AKZEPTANZ:**
  - In „Mitarbeiter bearbeiten“ wird für ein Rollenrecht ohne Override **„JA (Rolle)“** angezeigt; bei Override „entziehen“ **„NEIN (Override)“**.
- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
  - `php -l views/mitarbeiter/formular.php`
- **NEXT:** T-069 (Teil 1a): Terminal – Login via RFID → Startscreen-Status/Buttons (nicht anwesend) prüfen und Bugs/Anomalien sammeln.


## P-2026-01-07-12
- ZIP: `P-2026-01-07-12_terminal-warnung-eigene-unstimmigkeit.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `092914_gesammt.zip` = `10f76942a67ededaf3759b28553acdcedb114198ffbd09a5840e660e5e87f942`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `d75356e5e9c07fe517480c105b5ed8c10d5e8dc38ed4b6f1222e64a8fd1463f2`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** Terminal-Startscreen hatte bisher keinen Hinweis für den Mitarbeiter selbst bei unvollständigen Kommen/Gehen-Stempeln (nur Backend-Dashboard-Warnblock existierte).
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Wenn der eingeloggte Mitarbeiter in den letzten 14 Tagen Tage mit ungerader Anzahl Kommen/Gehen-Stempeln hat, wird eine rote Warnbox angezeigt.
  - Heuristik wie im Dashboard: Für „heute“ wird nur gewarnt, wenn der letzte Stempel älter als 10 Stunden ist.
- **AKZEPTANZ:**
  - Mitarbeiter mit offenen/unklaren Stempeln sieht direkt nach Login eine Warnung mit betroffenen Tagen.
  - Ohne Unstimmigkeiten erscheint keine Warnbox.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:** T-069 (Teil 1a): Feldtest weiterführen (Login/Buttons/Offline) und Auffälligkeiten via Debug-Notizen (`&debug=1`) sammeln.


## P-2026-01-07-13
- ZIP: `P-2026-01-07-13_mitarbeiter-rechte-override-spaltenname-erlaubt.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `094229_gesammt.zip` = `96cc6d206b6d32800ea84dbd652bdc66b6d0d63917d39018c122abf72c9cd41b`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6974aa76584d14d5e033b5f34a9483e7f8877f95e16d20b129692201a9de2f01`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** In `MitarbeiterAdminController` wurde `mitarbeiter_hat_recht.ist_erlaubt` genutzt, obwohl Schema/Migration/AuthService `erlaubt` verwenden.
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter bearbeiten: Rechte-Overrides werden aus `mitarbeiter_hat_recht.erlaubt` geladen und korrekt als Allow/Deny interpretiert.
  - Speichern: INSERT nutzt `erlaubt` (konsistent zur Migration 15 und AuthService).
- **AKZEPTANZ:**
  - In „Mitarbeiter bearbeiten“ funktioniert Laden & Speichern der Overrides ohne SQL-Fehler (Spalte `erlaubt`).
  - Overrides wirken weiterhin in der effektiven Rechteanzeige (Allow hinzufuegen, Deny entziehen).
- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
- **NEXT:** T-069 (Teil 1a): Feldtest Terminal (Login/Buttons/Offline) weiterführen und Auffälligkeiten via Debug-Notizen (`&debug=1`) sammeln.


## P-2026-01-07-14
- ZIP: `P-2026-01-07-14_report-monat-unstimmigkeit-markierung.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `095417_gesammt.zip` = `6de78356f4ae6094ad4d8aa24e8d097c96212ea9a5c214bc0978dbd042e80bcf`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `5fd3708f6087b663cfd29f9c1ba55efcef398232d44eb2de470d8521c98666c1`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** Monatsübersicht zeigte unvollständige Kommen/Gehen-Stempel bisher nur als "-" ohne klare Warn-Markierung.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `?seite=report_monat`: Tage mit unvollständigen Kommen/Gehen-Stempeln werden am Datum mit ⚠ markiert.
  - Fehlende Werte in der Kommen/Gehen-Spalte werden als **FEHLT** (rot) angezeigt.
  - Wenn der Monat mindestens einen solchen Tag enthält, erscheint oben ein roter Hinweis.
- **AKZEPTANZ:**
  - In der Monatsübersicht sieht man sofort, wenn ein Tag nur Kommen oder nur Gehen enthält.
  - Tage ohne Zeitstempel (z. B. reiner Urlaub/Feiertag) werden nicht fälschlich als FEHLT markiert.
- **TESTS:**
  - `php -l views/report/monatsuebersicht.php`
- **NEXT:** T-069 (Teil 2a/1a): Backend/Terminal im Feld weiter klicken und Auffälligkeiten via Debug-Notizen (`&debug=1`) sammeln.


## P-2026-01-07-15
- ZIP: `P-2026-01-07-15_dashboard-zeitwarnung-links.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `101429_gesammt.zip` = `6e5bf041e7d99e93e247fabb6cd56655f7e7cb917158299dc8e0144380dd59ba`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `6cdc92d4310adc45f98212ed234624a0fe020358c17cdbd902ddb2e7f0267ce2`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** Der Dashboard-Zeitwarnungsblock hatte zuvor keine direkten Navigationslinks zu Monatsreport oder Tageskorrektur.
- **DATEIEN (max. 3):**
  1) `controller/DashboardController.php`
  2) `views/dashboard/index.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard: Im Zeitwarnungsblock werden pro Zeile Links angezeigt:
    - „Monat öffnen“ (nur wenn Report-Recht vorhanden)
    - „Tag öffnen“ (nur wenn `ZEITBUCHUNG_EDIT_ALL` vorhanden)
- **AKZEPTANZ:**
  - Nutzer mit `DASHBOARD_ZEITWARNUNGEN_SEHEN` sieht Warnungen; Links erscheinen nur, wenn die jeweiligen Rechte vorliegen.
- **TESTS:**
  - `php -l controller/DashboardController.php`
  - `php -l views/dashboard/index.php`
- **NEXT:** T-069 Feldtest weiterführen (Dashboard/Monat/Tag) und Auffälligkeiten sammeln.


## P-2026-01-07-16
- ZIP: `P-2026-01-07-16_terminal-debug-queue-liste.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `102606_gesammt.zip` = `a034893f1167fdb9cd7d7ee4e05fa9ac93691a2925b4cad39758915c1345daec`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `7b5c5723386ff3a0cac334fec57e864b3e337570362fd961eeaf6e16841139f7`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** Debug-Panel (T-069) enthielt bereits Bugreport-Notizen, aber keine direkte Sicht auf die letzten Offline-Queue-Einträge.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Startscreen: Im Debug-Panel (`&debug=1`) wird eine Tabelle mit den letzten 10 `db_injektionsqueue`-Einträgen angezeigt (inkl. Status/Versuche/Meta/Fehler kurz).
  - Quelle: bevorzugt Offline-DB (falls verfügbar), sonst Haupt-DB.
- **AKZEPTANZ:**
  - Auf `terminal.php?aktion=start&debug=1` sieht man die letzten Queue-Einträge; ohne `debug=1` bleibt alles unverändert.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:** T-069 Feldtest Terminal weiterführen und Auffälligkeiten via Debug-Notiz sammeln.


## P-2026-01-07-17
- ZIP: `P-2026-01-07-17_terminal-urlaub-form-anwesenheit.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `110751_gesammt.zip` = `312e5f82f5e279d3a6477038ab14fcb9495560734dc9270a197bd30442a1dec9`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `df7f0654bd9f2f045ac4be479ae4636782aed631b8375354372256be96eb5b77`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** `urlaubBeantragenForm()` blockierte bisher fälschlich, wenn der Mitarbeiter heute bereits als „anwesend“ erkannt wurde (Kommen ohne Gehen / bereits gekommen). Das war ein Copy/Paste-Fehler und verhindert Urlaubsanträge während eines Arbeitstags.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Urlaub-Formular ist unabhängig von der heutigen Anwesenheit nutzbar (nur Login + Haupt-DB online erforderlich).
- **AKZEPTANZ:**
  - Ein Mitarbeiter kann Urlaub am Terminal beantragen, auch wenn er heute bereits „Kommen“ gestempelt hat.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:** T-069 (Teil 1a): Terminal-Feldtest (Login/Buttons/Offline) weiterführen und Auffälligkeiten sammeln.
## P-2026-01-07-18
- ZIP: `P-2026-01-07-18_terminal-start-ohne-uebersicht-wenn-nicht-anwesend.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `113057_gesammt.zip` = `6919fb9c80c9d18fcf7e6d3be7773ea267107613383761feb48e77fc6e2291b2`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `c40430fd4ed831f481c2babec971f7520bbee2a96977cb2170df69afba45d6de`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** Nicht-Anwesend-Screen soll nur Kommen + optional Urlaub anzeigen (keine Übersicht/Details).
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Wenn Mitarbeiter nicht anwesend ist, wird kein „Übersicht“-Link mehr angeboten.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:** T-069 (Teil 1a) Terminal-Kernflow klicktesten und Auffälligkeiten als B-IDs im LOG festhalten.

## P-2026-01-07-19
- ZIP: `P-2026-01-07-19_rolle-ist_superuser.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `114530_gesammt.zip` = `23949fe0f9703ccff7771973e62abe91dc694b9398c0d04d18cd57fefa9a2b52`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `ad48e94b9bc27ad780a907f7389a4695dfe21f1a34a6ed01e6d99a6a6aabee92`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `4095b5b1f69b0db72f02e7c0981769b7acc256fb319b8b16c2c7fa6d49b56bac`
- **DUPLICATE-CHECK:** Master-Prompt verlangt: Chef/Superuser darf immer alles (unabhängig von granularen Rollenrechten). Bisher nur über vollständige Rechte-Zuweisung lösbar.
- **DATEIEN (max. 3):**
  1) `services/AuthService.php`
  2) `sql/16_migration_rolle_ist_superuser.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Rechte-System: `AuthService::hatRecht()` erlaubt automatisch, wenn `istSuperuser()` true ist.
  - Superuser-Erkennung über neue Spalte `rolle.ist_superuser` (Session-Cache pro Mitarbeiter).
  - SQL Migration 16 ergänzt `rolle.ist_superuser` (default 0) und markiert Rolle "Chef" automatisch als Superuser.
- **AKZEPTANZ:**
  - Benutzer mit Rolle "Chef" (oder einer anderen Superuser-Rolle) kann jede Seite/Funktion nutzen, auch wenn einzelne Rechte nicht gesetzt sind.
  - Wenn Migration noch nicht eingespielt ist, bleibt Verhalten unverändert (kein Bypass).
- **TESTS:**
  - `php -l services/AuthService.php`
- **NEXT:** T-069 Feldtest weiterführen (Terminal/Dashboard/Monat/Tag) und auffällige Fälle sammeln.


## P-2026-01-07-20
- ZIP: `P-2026-01-07-20_mitarbeiter-rollen-scope-vorbereitung.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `121659_gesammt.zip` = `ed69e72ecc6cce0a8ce3682f0d176695e089795a8e730f37ba7935411c5ab890`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `2a0e6b675f2d2235ecb8354e5e16638a67856d71bae6fa8ff90cb8a140e4fa5b`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `57f2d08ff89814bb8ea9f11bda84e3ddfad686c4d131a1b334eec2e08ed2919f`
- **DUPLICATE-CHECK:** Es gab bisher keine Tabelle für scoped Rollen-Zuweisung (`mitarbeiter_hat_rolle_scope`).
- **DATEIEN (max. 3):**
  1) `services/AuthService.php`
  2) `sql/17_migration_mitarbeiter_rolle_scope.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - DB: Neue Tabelle `mitarbeiter_hat_rolle_scope` (Scope: global/abteilung, inkl. Flag „Unterbereiche“).
  - Auth: Rechte- und Superuser-Check lesen zusätzlich aus `mitarbeiter_hat_rolle_scope`.
    - **Wichtig:** Aktuell wird nur `scope_typ='global'` ausgewertet (Abteilungslogik kommt später), damit nichts bricht.
- **AKZEPTANZ:**
  - Wenn ein Mitarbeiter eine Rolle per `mitarbeiter_hat_rolle_scope` mit `scope_typ='global'` bekommt, wirken die Rechte wie bei `mitarbeiter_hat_rolle`.
  - Eine scoped zugewiesene Superuser-Rolle funktioniert ebenfalls.
- **TESTS:**
  - `php -l services/AuthService.php`
- **NEXT:** T-069 Feldtest weiterführen; danach scoped Rollen-UI + Abteilungs-Matching (Unterbaum) schrittweise ergänzen.

## P-2026-01-07-21
- ZIP: `P-2026-01-07-21_mitarbeiter-rollen-scope-ui-global.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `122634_gesammt.zip` = `f2afa929fe1271d41b070960fda7d8a90857cd00599a0ebb5984bd0ab4514356`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `4736ac1c836ef70f91a20913533fd9d3a55912e1d1bce4aad02f3ba9160792c8`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** Rollen-Scope-DB war vorbereitet (Migration 17), aber Mitarbeiter-UI/Speichern war noch Legacy-only.
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter-Formular lädt Rollen bevorzugt aus `mitarbeiter_hat_rolle_scope` (global), fallback auf `mitarbeiter_hat_rolle`.
  - Beim Speichern werden ausgewählte Rollen zusätzlich als `scope_typ='global'` in `mitarbeiter_hat_rolle_scope` gespiegelt (fail-safe falls Tabelle fehlt).
  - Wenn der Admin seine eigene Rollen-Zuordnung ändert: Session-Cache (Rechte + Superuser) wird geleert, damit Änderungen sofort greifen.
  - UI: Hinweis ergänzt, dass Rollen derzeit als global gespeichert werden.
- **AKZEPTANZ:**
  - Nach Migration 17: Rollen-Checkboxen setzen/löschen Einträge in `mitarbeiter_hat_rolle_scope` (global). UI zeigt diese Rollen korrekt an.
  - Ohne Migration 17: Verhalten bleibt wie vorher (Legacy-Tabelle).
- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
  - `php -l views/mitarbeiter/formular.php`
- **NEXT:** Scoped Rollen (Phase 1) für `scope_typ='abteilung'` in der Mitarbeiter-UI ergänzen (Auswahl Abteilung + gilt_unterbereiche), danach Auth-Abteilungs-Matching schrittweise.


## P-2026-01-07-22
- ZIP: `P-2026-01-07-22_mitarbeiter-rollen-scope-ui-abteilung.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `132459_gesammt.zip` = `ba1daa177e60c020f8be532c778f285345a501c6aa0c8d03c6a00aa22b0bf535`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `e05a7411a5e2e67f2c7e423c6653b40cd6b3a40ca1e29971a28a7d547eeb66b8`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
- **DUPLICATE-CHECK:** OK (kein Eintrag mit gleicher ZIP gefunden)
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter-Formular: neuer Abschnitt „Abteilungs-Rollen (Phase 1)“ (anzeigen, hinzufügen, löschen, Unterbereiche-Flag).
  - Controller lädt/speichert `scope_typ='abteilung'` Einträge in `mitarbeiter_hat_rolle_scope` (Migration 17).
  - Fail-safe: ohne Migration 17 bleibt alles wie vorher (keine harten Fehler).
- **AKZEPTANZ:**
  - Nach Migration 17: Abteilungs-Rollen lassen sich hinzufügen/löschen und Unterbereiche kann pro Eintrag gesetzt werden; nach Speichern werden Einträge korrekt wieder angezeigt.
  - Ohne Migration 17: Speichern bricht nicht; Feature wird still ignoriert.
- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
  - `php -l views/mitarbeiter/formular.php`
- **NEXT:** Auth-Abteilungs-Matching (Unterbaum) schrittweise: erste Nutzstelle „Dashboard Zeitwarnungen“ so filtern, dass nur Mitarbeiter aus erlaubten Abteilungen angezeigt werden (Superuser unverändert).

## P-2026-01-07-23
- ZIP: `P-2026-01-07-23_betriebsferien-nicht-abziehen-wenn-krankzeitraum.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `150106_gesammt.zip` = `85de888bf3d50fc843496eee11a82c17164a56e9573d312d704c62f75142a40e`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `b401cc38c7cd6540b01b2ed02e40143ec47b3fba8467fd50f40d1d20a2b61355`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** B-025 deckte nur Fälle ab, bei denen Krank/andere Kennzeichen als Tages-Override in `tageswerte_mitarbeiter` vorhanden sind. Krankzeitraum (Zeiträume) ohne Tages-Override führte weiterhin zu Betriebsferien-Abzug.
- **DATEIEN (max. 3):**
  1) `services/UrlaubService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Urlaubssaldo: Betriebsferien-Tage werden **nicht** als genommener Urlaub gezählt, wenn ein aktiver Krankzeitraum (LFZ/KK) den Tag umfasst (auch ohne Tages-Override).
  - Skip-Set für Betriebsferien berücksichtigt nun zusätzlich Krankzeitraum-Intervalle (jahr-geclippt), nur für Mo–Fr und nicht an betriebsfreien Feiertagen.
- **AKZEPTANZ:**
  - Beispiel: Mitarbeiter hat Krankzeitraum 2025-12-29 bis 2026-02-01 und es gibt Betriebsferien im Januar 2026 → diese Betriebsferien-Tage reduzieren den Urlaubssaldo **nicht**.
  - Bestehendes Verhalten bleibt: Wenn an Betriebsferien gearbeitet wurde oder andere Kennzeichen im Tageswert gesetzt sind, wird ebenfalls nicht abgezogen.
- **TESTS:**
  - `php -l services/UrlaubService.php`
- **NEXT:** **B-077** Monatsreport/PDF: Krankzeitraum muss Betriebsferien im Tagesraster übersteuern (Anzeige: kein BF-Kürzel, Urlaub 0, Krank 8.00).

## P-2026-01-08-01
- ZIP: `P-2026-01-08-01_krankzeit-uebersteuert-betriebsferien-report.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `044139_gesammt.zip` = `95d5d0ad6c3045421fc4c0f0714ccd1c8560e563cc81401f07e464310742c289`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `32f70ab156225e916dc1f6a3e943f5e3919c824598701860addbbb63d1a0f239`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** B-077 war offen; im LOG gab es noch keinen Fix, der Krankzeitraum-Intervalle im Tagesraster/Report **vor** Betriebsferien/Urlaub setzt.
- **DATEIEN (max. 3):**
  1) `services/KrankzeitraumService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport/PDF Tagesraster: Wenn ein Krankzeitraum (LFZ/KK) einen Tag abdeckt (und keine Arbeitszeit/anderen Schutz-Kennzeichen gesetzt sind), wird **Urlaub/Betriebsferien** für die Anzeige entfernt (Urlaub=0.00, kein BF-Kürzel/Fallback) und stattdessen **Krank 8.00** (LFZ oder KK) gesetzt.
  - BF-Kürzel im Kommentar (nur wenn Kommentar exakt „BF“ ist) wird für den Tag geleert, damit im PDF kein BF angezeigt wird.
- **AKZEPTANZ:**
  - Krankzeitraum übersteuert Betriebsferien/Urlaub im Tagesraster: In der Monatsliste/PDF steht kein „BF“, kein Urlaub, sondern Krank LFZ/KK mit 8.00 Stunden (Mo–Fr, kein Feiertag, keine Arbeitszeit).
- **TESTS:**
  - `php -l services/KrankzeitraumService.php`
- **NEXT:** T-069 Feldtest weiterführen (Terminal/Dashboard/Monat/Tag) und auffällige Fälle sammeln.


## P-2026-01-08-02
- ZIP: `P-2026-01-08-02_initial-schema-rechte-sync.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `045627_gesammt.zip` = `4ed5ad7349e06a711da7c24291345c64ef1ae526a3f824c0c1241a98e56dcbfd`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `bee6f6f3bb69261f0f4c094c3b6d9c8c31114b91562df89bbf4aeabb8e8c0858`
  - SQL (SoT): `sql/01_initial_schema.sql` = `a57617c9e51913f32f8d642467a6a1a2218f9199b3a4be1050ee816e051d7888`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** In SNAPSHOT/LOG existieren bereits Migrationen/Features (Superuser, Mitarbeiter-Rechte-Overrides, Rollen-Scopes), aber `sql/01_initial_schema.sql` als Source-of-Truth enthielt diese Strukturen noch nicht → frische Installationen wären inkonsistent.
- **DATEIEN (max. 3):**
  1) `sql/01_initial_schema.sql`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Source-of-Truth Schema (`sql/01_initial_schema.sql`) erweitert/synchronisiert:
    - `rolle.ist_superuser` (Chef/Superuser-Bypass)
    - `rolle_hat_recht.erstellt_am`
    - neue Tabellen `mitarbeiter_hat_recht` (Allow/Deny Overrides) und `mitarbeiter_hat_rolle_scope` (scoped Rollen)
- **AKZEPTANZ:**
  - Frische Installation nur mit `sql/01_initial_schema.sql` enthält alle aktuellen Rechte-Strukturen (Superuser/Overrides/Scope) konsistent zu `sql/zeiterfassung_aktuell.sql` + Migrationen 15–17.
- **TESTS:**
  - (SQL) Datei syntaktisch geprüft (manuell/import).
- **NEXT:** T-069 Feldtest weiterführen; danach Abteilungs-Matching/Filterung schrittweise (scoped Rollen) ausbauen.


## P-2026-01-08-03
- ZIP: `P-2026-01-08-03_pdf-bf-kuerzel-nur-wenn-urlaub.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `050656_gesammt.zip` = `ec7b4a0d69b5ed7fbeddd539de194d722b36571cccf4bdd91d8d26797a0ac3d1`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `41723779a48b76c23483de23ff06bcdef5021f4261b2eb220617f028c36a74dc`
  - SQL (SoT): `sql/01_initial_schema.sql` = `1aedeea4b65f1edaeb6324aa0315fc6df9a9d77a5a27917e045e3565fa925a33`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** Im PDF existiert ein BF-Fallback (wenn `kommentar` leer und `ist_betriebsferien=1`). Da `ist_betriebsferien` auch auf Feiertag/Wochenende und bei Arbeitszeit true sein kann, erschien "BF" im PDF an Tagen, die nicht als Betriebsferien-Urlaub zaehlen sollen.
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Arbeitszeitliste/PDF: BF-Fallback zeigt "BF" nur, wenn Betriebsferien an dem Tag tatsaechlich als Urlaub bewertet wurden (`kennzeichen_urlaub=1` oder `urlaub_stunden>0`).
  - Dadurch kein "BF" auf Feiertagen/Wochenenden innerhalb Betriebsferien und kein "BF" an BF-Tagen mit echter Arbeitszeit.
- **AKZEPTANZ:**
  - Monats-PDF "Arbeitszeitliste": Ein Feiertag innerhalb Betriebsferien zeigt kein "BF" im Kuerzel-Feld; ein echter Betriebsferien-Urlaubstag (Mo-Fr, nicht Feiertag, keine Arbeitszeit) zeigt "BF".
- **TESTS:**
  - `php -l services/PDFService.php`
- **NEXT:** T-069 Feldtest weiterführen (weitere PDF/Monatsreport-Kantenfaelle sammeln).

## P-2026-01-08-04
- ZIP: `P-2026-01-08-04_b077-status-doku.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `051704_gesammt.zip` = `d410b4220be5c9087d7a14b0a653ac574026213bb13c56142c7411661e4a730c`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `371a8931c1c245c7c45b71f92aeee8adb6c3dd21fb9e39aaec94d3307c65e47b`
  - SQL (SoT): `sql/01_initial_schema.sql` = `1aedeea4b65f1edaeb6324aa0315fc6df9a9d77a5a27917e045e3565fa925a33`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** Fachlogik-Änderung (B-077) ist bereits in den Patches P-2026-01-08-01 / P-2026-01-08-03 umgesetzt; im SNAPSHOT/Bug-Block stand B-077 noch auf **OPEN** → reine Doku-Korrektur.
- **DATEIEN (max. 3):**
  1) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - SNAPSHOT/Bugliste konsistent gemacht: **B-077** ist jetzt als **DONE** markiert (Krank übersteuert Betriebsferien im Monatsreport/PDF).
- **AKZEPTANZ:**
  - B-077 steht im SNAPSHOT unter „Bekannte Probleme / Bugs“ nicht mehr als OPEN.
- **TESTS:**
  - (keine)
- **NEXT:** T-069 Kernflows manuell klicken (Terminal/Queue/Monatsübersicht/PDF) und gefundene Bugs als Micro-Patches fixen.



## P-2026-01-08-05
- ZIP: `P-2026-01-08-05_terminal-debug-session-idleinfo.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `053212_gesammt.zip` = `d0ab7e4f27bb67fc2ed9e24b0e8eaed095b8aeff82206746ea46cefdeea3774c`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `1d0e757889b3ead14c25aeb12e9b6e0132e578c69fe55cc98a89fa0eccc7181e`
  - SQL (SoT): `sql/01_initial_schema.sql` = `1aedeea4b65f1edaeb6324aa0315fc6df9a9d77a5a27917e045e3565fa925a33`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** SNAPSHOT/LOG geprüft: **T-069** ist offen (Feldtest/Stabilität). Debug-Panel existierte bereits, aber es gab keinen DONE-Patch für „Debug bleibt über Redirects aktiv“ und keine Anzeige des serverseitigen Idle-Fallbacks im Debug-Block.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Debug-Mode: `&debug=1` aktiviert Debug dauerhaft per Session (bleibt über Redirects aktiv). `&debug=0` deaktiviert Debug wieder.
  - Debug-Block zeigt zusätzlich serverseitiges Idle-Timeout (Fallback) + letzte Aktivität + Restzeit.
  - Logout entfernt das Debug-Flag aus der Session.
- **AKZEPTANZ:**
  - `terminal.php?aktion=start&debug=1` zeigt den Debug-Block; nach „Kommen“/Redirect bleibt der Debug-Block sichtbar auch ohne `debug=1` in der URL.
  - `terminal.php?aktion=start&debug=0` deaktiviert Debug (Debug-Block verschwindet).
  - Nach Logout ist Debug ebenfalls deaktiviert.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:** T-069 Teil 1a/1b/1c manuell testen; gefundene Kantenfälle als Micro-Patches fixen.
## P-2026-01-08-06
- ZIP: `P-2026-01-08-06_terminal-debug-copydump.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `054042_gesammt.zip` = `0af442e803155a4eee5146d1bd490c78d0ebfac8ecbe886d176ce9d0623131c5`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `1f6b7a22094c99c844352b64c09cd743cb560486eb9b475dfea3756e0f4e7d07`
  - SQL (SoT): `sql/01_initial_schema.sql` = `1aedeea4b65f1edaeb6324aa0315fc6df9a9d77a5a27917e045e3565fa925a33`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** SNAPSHOT/LOG geprüft: Es gibt noch keinen Patch, der im Debug-Panel einen Copy-Button für den Debug-Dump bereitstellt; T-069 Feldtest ist weiterhin offen.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `public/js/terminal-autologout.js`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Debug (T-069): Debug-Dump hat jetzt einen „Debug-Info kopieren“-Button (Clipboard API + Fallbacks).
  - Debug-Panel zeigt zusätzlich eine Mini-Checkliste für die T-069 Kernflows (1a/1b/1c).
- **AKZEPTANZ:**
  - Wenn Debug aktiv ist (`&debug=1`), gibt es im Debug-Panel einen Button „Debug-Info kopieren“ und danach erscheint ein kurzes Status-Feedback (Kopiert / Fallback-Hinweis).
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:** T-069 Teil 1a/1b/1c manuell testen; gefundene Kantenfälle als Micro-Patches fixen.

## P-2026-01-08-07
- ZIP: `P-2026-01-08-07_pause-ab-in-fenster-kein-abzug.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `054733_gesammt.zip` = `ca90d18c328077e7b17ab74a754647ef438b00dcaed767a55b4076d80cef1dcb`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `87411e87c06d1a58f17d251a39736dbe3b7aa378be453a0f568cb2840956ead6`
  - SQL (SoT): `sql/01_initial_schema.sql` = `1aedeea4b65f1edaeb6324aa0315fc6df9a9d77a5a27917e045e3565fa925a33`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** SNAPSHOT/LOG geprüft: Es gab bisher keinen Patch, der ein Pausenfenster explizit ignoriert, wenn der Mitarbeiter innerhalb des Fensters abstempelt (Ab/Ab.Korr im Fenster).
- **DATEIEN (max. 3):**
  1) `services/PausenService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Zwangspause (Pausenfenster): Wenn `Ab/Ab.Korr` innerhalb eines Pausenfensters liegt, wird dieses Fenster **nicht** abgezogen (Annahme: Pause nicht gemacht).
- **AKZEPTANZ:**
  - Beispiel: Arbeitsblock (korr.) 07:00–12:45 und Pausenfenster 12:30–13:00 → dieses Fenster wird nicht abgezogen; nur weitere Fenster (z. B. 09:00–09:15) werden abgezogen.
- **TESTS:**
  - `php -l services/PausenService.php`
- **NEXT:** T-081 (Teil 1) – „Entscheidung noetig“ Faelle erkennen (knapp um ~6h) und Default ohne Entscheidung: keine Pause abziehen; danach UI/Dashboard Hinweis (Teil 2).


## P-2026-01-08-08
- ZIP: `P-2026-01-08-08_pause-entscheidung-default-kein-abzug.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `060932_gesammt.zip` = `796847807a66464f45caec25a65c411fc1c5be197c35722de7757b7041113a96`
  - Master-Prompt: `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY: `docs/DEV_PROMPT_HISTORY.md` = `d1d8fdd975c97bae498eb1939b243df463e38fcdfeac36d54daf36b13ffa4809`
  - SQL (SoT): `sql/01_initial_schema.sql` = `1aedeea4b65f1edaeb6324aa0315fc6df9a9d77a5a27917e045e3565fa925a33`
  - SQL (aktuell): `sql/zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:** Es gab bisher keinen Patch, der Grenzfaelle (~6h) als „Entscheidung noetig“ markiert und bis zur Entscheidung standardmaessig **keine** Pause abzieht.
- **DATEIEN (max. 3):**
  1) `services/PausenService.php`
  2) `services/ReportService.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - PausenService (T-081 Teil 1): Grenzfaelle um die 1. gesetzliche Schwelle (Default 6h +/- `pause_entscheidung_toleranz_minuten`, Default 30) werden als `entscheidung_noetig` markiert und ziehen bis zur Entscheidung **keine** Pause ab.
  - ReportService: gibt pro Tag zusaetzlich `pause_entscheidung_noetig` und `pause_entscheidung_auto_minuten` aus (nur Datenfeld, UI folgt in Teil 2).
- **AKZEPTANZ:**
  - Arbeitsblock 07:00–13:15 (nahe 6h) mit Pausenfenstern 09:00–09:15 und 12:30–13:00 → `pausen_stunden` bleibt 0.00 (Default), aber `pause_entscheidung_noetig=1` und `pause_entscheidung_auto_minuten=45`.
  - Arbeitsblock 07:00–15:00 (deutlich ueber 6h) zieht Pause wie gehabt automatisch ab und `pause_entscheidung_noetig=0`.
- **TESTS:**
  - `php -l services/PausenService.php`
  - `php -l services/ReportService.php`
- **NEXT:** T-081 Teil 2 – Dashboard/Listenansicht: offene Pausen-Entscheidungen anzeigen und eine Entscheidung (Abziehen/Nein) speicherbar machen.

## P-2026-01-08-09
- ZIP: `P-2026-01-08-09_pausenentscheidung-tabelle-model.zip`
- Change: Migration `sql/18_migration_pausenentscheidung.sql` + neues `PausenentscheidungModel` (defensiv, falls Migration noch nicht importiert ist).
- Next: Report/PDF/Monatswerte sollen gespeicherte Pausen-Entscheidungen berücksichtigen (ABZIEHEN => Auto-Pause abziehen, NICHT_ABZIEHEN => 0) + Dashboard/Übersicht für offene Entscheidungen.


## P-2026-01-08-11
- ZIP: `P-2026-01-08-11_pausenentscheidung-report-anwenden.zip`
- Change: `ReportService` wendet eine gespeicherte Entscheidung aus `pausenentscheidung` an, falls `pause_entscheidung_noetig=1` (Default bleibt: ohne Entscheidung kein Abzug).
- Smoke:
  - `php -l services/ReportService.php`
- NEXT: T-081 Teil 2 – Dashboard/Listenansicht: offene Pausen-Entscheidungen anzeigen + Entscheidung (Abziehen/Nein) speichern (Controller + View + Route).

## P-2026-01-08-13
- ZIP: `P-2026-01-08-13_reportservice-parsefix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `071419_gesammt.zip` = `2696c7e3b362782087f8d16bd67721dde5ed71795d0ca25b63e36cbada5207e1`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8731038e7a4f2320c67a6965cc4d604a374bacb4d5bac70c6ed69bfae6ec62f6`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: Kein Patch dokumentiert, der den aktuellen Parse-Error/Fragment in `services/ReportService.php` entfernt. Fix ist notwendig, sonst brechen Monatsübersicht/PDF je nach Autoload mit Fatal Error.
- **DATEIEN (max. 3):**
  - `services/ReportService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Parse-Error/Rest-Fragment in `ReportService.php` (im Tageswerte-Loop, Pausenentscheidung) entfernt.
  - Monatsreport/PDF/Monatsübersicht sind wieder lauffaehig (keine PHP-Syntaxfehler).
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilität testen (RFID Login „nicht anwesend“ → nur „Kommen“ + optional „Urlaub“; Warnboxen/Debug-Dump bei Auffälligkeiten).


## P-2026-01-09-04
- ZIP: `P-2026-01-09-04_monatsreport-pause-override-auditlog.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `081742_gesammt.zip` = `a16f260fbde1249065d6c9db545047b02320ba3e230ee3ad1b34bd0feb925403`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a068e3961311cf518c65c753ff2557751b2c02f79f4e8ec2e2a44380a53ef2f3`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Pause-Override kann (inkl. 0,00) aktiviert/deaktiviert werden. Aber Monatsreport/PDF berechnete Auto-Pause bisher nur, wenn `felder_manuell_geaendert != 1`. Bei deaktiviertem Pause-Override und gleichzeitig anderen manuellen Tagesfeldern konnte Pause dadurch fälschlich 0 bleiben. → Fix erforderlich.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport/PDF: Auto-Pause wird wieder nach Regeln berechnet, wenn Pause-Override deaktiviert ist – auch wenn andere Tagesfelder manuell gesetzt sind.
  - Pause-Override-Status pro Datum wird aus dem Audit-Log (`system_log`, Kategorie `tageswerte_audit`, Nachrichten „gesetzt/entfernt: Pause-Override“) abgeleitet, damit Override=0,00 eindeutig bleibt.
- **AKZEPTANZ:**
  - Wenn Pause-Override deaktiviert ist, zeigt Monatsübersicht/PDF wieder Pause nach Regeln (bzw. 0 bei „Entscheidung nötig“), unabhängig von anderen Tagesfeld-Overrides.
  - Wenn Pause-Override aktiv ist (auch 0,00), wird keine Auto-Pause berechnet.
- **TESTS:**
  - `php -l services/ReportService.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilität testen (RFID Login „nicht anwesend“ → nur „Kommen“ + optional „Urlaub“; Warnboxen/Debug-Dump bei Auffälligkeiten).

## P-2026-01-08-18
- ZIP: `P-2026-01-08-18_tagesansicht-pauseoverride-hinweis-zeitcontroller-restore.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `094946_gesammt.zip` = `82eee3105ae502c5f7fedaa9b239dbbf4a725124fbf49010d14531960c81bdc1`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1aeab38d9d825a68a68d774d1b4c134bb17ccb83572dc19b0c5fc06f41cbe1e0`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `ee363d47a756ed640cb9f077fd9b9cf3a5ade101a17334e4788e44276895e15e`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: Kein Patch dokumentiert, der eine versehentlich beschädigte `controller/ZeitController.php` (Master-Prompt-Text statt PHP) wiederherstellt oder in der Tagesansicht einen sichtbaren Override-Hinweis ergänzt.
- **DATEIEN (max. 3):**
  1) `controller/ZeitController.php`
  2) `views/zeit/tagesansicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `controller/ZeitController.php` wiederhergestellt (gültige PHP-Datei), damit `?seite=zeit_heute` und Tagesansicht stabil laden.
  - Tagesansicht zeigt einen roten Hinweisblock, wenn „Pause (Override)“ gesetzt ist (inkl. Link zurück zum Dashboard).
- **AKZEPTANZ:**
  - Tagesansicht lädt ohne PHP-Fatal-Error und zeigt bei gesetztem Pause-Override den Hinweis „Pause Override aktiv …“.
- **TESTS:**
  - `php -l controller/ZeitController.php`
  - `php -l views/zeit/tagesansicht.php`
- **NEXT:**
  - Tagesansicht: Button „Override löschen“ (setzt `pause_korr_minuten` auf NULL) + optional Anzeige/Link, wenn für den Tag eine Pausenentscheidung offen/gesetzt ist.


## P-2026-01-09-01
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `072706_gesammt.zip` = `5c1a107b91d9697887ca59fa62cedc6948ad67d33f5076f07d65fcc3267acc6a`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `bb676ebfc12ff67aad0c93e23fc21b9620d9b14eaab3ae0b8e86559e2ac5fdad`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Pause-Override existiert (P-2026-01-08-16), aber ohne sichtbare/auffüllbare Begründung und ohne Aktiv-Checkbox zum sicheren De-/Aktivieren.
- **DATEIEN (max. 3):**
  1) `controller/ZeitController.php`
  2) `views/zeit/tagesansicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Tagesansicht: Pause-Override zeigt die zuletzt gespeicherte Begründung im roten Hinweisblock an und füllt das Feld „Begründung“ im Formular vor.
  - Tagesansicht: Pause-Override hat jetzt eine Checkbox „Pause Override“ (aktiv/deaktiv). Wenn der Haken entfernt wird, wird der Override entfernt (DB: `pause_korr_minuten=0`, Audit-Log „entfernt“) und die normalen Pausenregeln greifen wieder.
  - Aktiv/Inaktiv-Status für Pause-Override wird aus dem Audit-Log abgeleitet (damit bleibt 0,00 Stunden als aktiver Override eindeutig erkennbar).
- **AKZEPTANZ:**
  - In der Tagesansicht ist die Pause-Override-Begründung sichtbar/vorgefüllt und der Override lässt sich per Checkbox sicher aktivieren/deaktivieren.
- **TESTS:**
  - `php -l controller/ZeitController.php`
  - `php -l views/zeit/tagesansicht.php`
- **NEXT:**
  - Weiter mit T-069 Klickpfaden (Terminal/Backend) testen und daraus Bugfixes ableiten.


## P-2026-01-09-02
- ZIP: `P-2026-01-09-02_tagesansicht-auto-pauseanzeige.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `074650_gesammt.zip` = `474b7f5f83c2c42a57f4549d75ca483e8128f0d54736b7caa1402a2ec6ddfe66`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `2185761c9eb7aa7761ad9eb82726a20b93dc8678bffc9ac556db7ad17ac6e751`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - P-2026-01-09-01 liefert Checkbox + Begründung, zeigte bei deaktiviertem Override aber im Formular oft leer/0,00 und wirkte dadurch widersprüchlich zu „normale Pausenregeln aktiv“.
- **DATEIEN (max. 3):**
  1) `controller/ZeitController.php`
  2) `views/zeit/tagesansicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Tagesansicht berechnet zusätzlich die automatische Pause nach Regeln (auf Basis frühestes Kommen / spätestes Gehen, inkl. Rundung + Pausenregeln) und zeigt diese an, wenn kein Pause-Override aktiv ist.
  - Das Feld „Pause in Stunden“ wird bei deaktiviertem Override mit der Auto-Pause vorgefüllt (disabled), sodass beim späteren Aktivieren ein sinnvoller Default vorhanden ist.
  - Optionaler Hinweis, wenn eine Pausenentscheidung nötig ist (Auto-Vorschlag wird angezeigt).
- **AKZEPTANZ:**
  - Wenn Pause-Override deaktiviert ist, sieht man in der Tagesansicht die automatische Pause nach Regeln und der Wert wirkt nicht mehr wie „0,00 obwohl Auto-Pause greifen soll“.
- **TESTS:**
  - `php -l controller/ZeitController.php`
  - `php -l views/zeit/tagesansicht.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilität testen (RFID Login „nicht anwesend“ → nur „Kommen“ + optional „Urlaub“; Warnboxen/Debug-Dump bei Auffälligkeiten).

## P-2026-01-09-03
- ZIP: `P-2026-01-09-03_pause-override-deaktivieren-ohne-begruendung.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `080912_gesammt.zip` = `c89d0402ef496b8b35ed2c9a7d72818c706ea49e738191c53bb3d87e0d9fa0a0`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7d8bea5c8c1006f1bda74ace91fe3a65a662ba3ebccd22e08856034aff2e7c34`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Pause-Override-Checkbox existiert (P-2026-01-09-01), aber **Deaktivieren ohne erneute Begründung** war nicht möglich (globales Pflichtfeld im Controller + `required` im Formular). → **nicht DONE**, Fix erforderlich.
- **DATEIEN (max. 3):**
  1) `controller/ZeitController.php`
  2) `views/zeit/tagesansicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Tagesansicht: Pause-Override kann jetzt deaktiviert werden (Checkbox aus) **ohne** dass eine Begründung erneut eingegeben werden muss (Controller erlaubt leere Begründung nur für diesen Fall).
  - Tagesansicht: Begründungsfeld wird bei deaktiviertem Override automatisch deaktiviert und ist nur bei aktivem Override `required`.
- **AKZEPTANZ:**
  - Wenn der Pause-Override-Haken entfernt wird, lässt sich „Speichern“ ohne Begründung ausführen und der Override ist danach entfernt.
- **TESTS:**
  - `php -l controller/ZeitController.php`
  - `php -l views/zeit/tagesansicht.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilität testen (RFID Login „nicht anwesend“ → nur „Kommen“ + optional „Urlaub“; Warnboxen/Debug-Dump bei Auffälligkeiten).

## P-2026-01-09-05
- ZIP: `P-2026-01-09-05_terminal-wide-97percent.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `084328_gesammt.zip` = `dad9b4070b950d39d3d7c89f6af5c8e4871e14292a17b76b9ce45eba4743ca64`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `43d159bd0b3437d3929a889cfe69f9eb15c26aa352d73f373b906b3839aa11fc`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Terminal-Layout: `body.terminal-wide main` war noch auf `max-width: 820px` begrenzt → widerspricht „~97% Display“ (Kiosk), besonders sichtbar in `stoerung.php`.
- **DATEIEN (max. 3):**
  1) `public/css/terminal.css`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-CSS: Begrenzung `max-width: 820px` für `body.terminal-wide main` entfernt (`max-width: none`), damit Kiosk-Displays wieder ~97% genutzt werden.
- **AKZEPTANZ:**
  - Terminal-Seiten mit `terminal-wide` nutzen wieder die volle Terminal-Fläche (97vw/97vh) ohne künstliche 820px-Begrenzung.
- **TESTS:**
  - (keine)
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilität manuell klicken (RFID Login „nicht anwesend“, Warnboxen/Debug-Dump) und Bugs als Micro-Patches fixen.


## P-2026-01-09-06
- ZIP: `P-2026-01-09-06_terminal-login-meldungen-nur-rfid.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `093035_gesammt.zip` = `ec7de4abec09c380d9cc60606383ca3c75d3ec08a3e219152965091e09e54052`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `3786ece3b637f13a07c99f51bbf7677aa78ecbea65e034c72bca93983dac021d`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - P-2026-01-05-17 reduzierte Login-Text/Label in `views/terminal/start.php` auf „nur RFID“, aber Controller-Meldungen enthielten weiterhin „Personalnummer/Mitarbeiter-ID/ID“. Kein spaeterer Patch dokumentiert, der diese Terminal-Fehltexte bereinigt.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: User-Fehltexte rund um Login/Session nennen jetzt nur noch **RFID** (kein „Personalnummer“, kein „Mitarbeiter-ID“, kein „RFID oder ID“):
    - fehlender Code → „Bitte RFID scannen oder eingeben.“
    - mehrdeutiger numerischer Code → „Bitte RFID scannen oder Personalbüro informieren.“
    - nicht eingeloggt → „Bitte zuerst am Terminal anmelden (RFID).“
  - Demo-Fallbacks (Personalnummer/ID) bleiben technisch aktiv, werden aber nicht mehr im UI beworben.
- **AKZEPTANZ:**
  - Auf dem Terminal erscheinen in Login/Fehlermeldungen keine Hinweise auf Personalnummer/Mitarbeiter-ID/ID mehr, sondern ausschließlich RFID.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilitaet weiter manuell klicken und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-07
- ZIP: `P-2026-01-09-07_begruendung-statt-kommentar.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `094058_gesammt.zip` = `9842020026baf4fbee0bc9cac0dadef946d8a6f24f7368b1e8b39a6024704e3f`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `42ff159e2125c0531a1cfe2dd64d46fbaa7750ca25fd46de155a98c654d9b722`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der die Pflicht-Begründung bei Zeitbuchungen (Add/Update) auch als sichtbaren Text in der Tagesansicht speichert/anzeigt. Bisher war die Begründung zwar Pflicht (Audit), aber im UI nicht als Spaltenwert auslesbar.
- **DATEIEN (max. 3):**
  1) `controller/ZeitController.php`
  2) `views/zeit/tagesansicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Tagesansicht: Bei Zeitbuchung **Add** und **Update** wird das Pflichtfeld `begruendung` zusaetzlich in `zeitbuchung.kommentar` gespeichert, damit es direkt in der Tabelle sichtbar ist.
  - UI: Spaltenheader heisst nun **Begruendung** (statt Kommentar) und es gibt kein separates optionales Kommentar-Feld mehr bei Add/Update.
- **AKZEPTANZ:**
  - Nach Hinzufuegen oder Bearbeiten einer Kommen/Gehen-Buchung ist die eingegebene Begruendung sofort in der Tagesansicht in der Spalte **Begruendung** sichtbar.
- **TESTS:**
  - `php -l controller/ZeitController.php`
  - `php -l views/zeit/tagesansicht.php`
- **NEXT:**
  - Pruefen, ob es weitere UI-Stellen gibt, wo Pflicht-Begruendung existiert, aber im UI nicht direkt sichtbar ist (z. B. andere Korrektur-/Admin-Formulare) und dort konsistent ausgeben.

## Patch P-2026-01-09-08_begruendung-tagesfelder-auslesbar

### EINGELESEN (Pre-Flight)
- Projekt-ZIP: `100132_gesammt.zip` (SHA256: `d84ee6bce0e9ff60b1104554c2bb73d86c00ac5b6459c162e68adcdee41d3f22`)
- Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` (SHA256: `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`)
- DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` (SHA256: `8cbedd6f66a0f8271da9a59834ae409da4d5278642c01632363f18eae7adeff1`)
- SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` (SHA256: `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`)

### ZIEL
Pflicht-Begründungen für Tagesfelder (Kurzarbeit/Krank/Sonstiges) sollen in der Tagesansicht **auslesbar** sein (wie Pause-Override). Umsetzung ohne DB-Schema-Änderung über das bestehende Audit-Log.

### UMSETZUNG
- `ZeitController`: liest die letzte Begründung aus `system_log` (Kategorie `tageswerte_audit`) für Kurzarbeit/Krank/Sonstiges pro Mitarbeiter/Tag.
- `set_krank`: schreibt jetzt konsistent ins Audit-Log (Kategorie `tageswerte_audit`, inkl. Begründung) und unterscheidet `gesetzt`/`entfernt`.
- `tagesansicht.php`: Begründungsfelder werden mit der zuletzt geloggten Begründung vorgefüllt (sichtbar/editierbar).

### DATEIEN (max 3)
- `controller/ZeitController.php`
- `views/zeit/tagesansicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### HINWEIS (manueller Kurzcheck)
- In der Tagesansicht Kurzarbeit/Krank/Sonstiges setzen → Seite neu laden → Begründung bleibt sichtbar (vorgefüllt).
- Historische Krank-Einträge (vor diesem Patch) haben keine Begründung im `tageswerte_audit`; ab diesem Patch ist es konsistent.

### NEXT
- Optional: Kurzarbeit „Override entfernt“ als eigenes Audit-Event (wie Pause) + ggf. Deaktivieren ohne Begründung (analog Pause), falls gewünscht.

## P-2026-01-09-09
- ZIP: `P-2026-01-09-09_terminal-login-button-fillheight.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `102420_gesammt.zip` = `fdb3a2325c045643a0ac0106e08029cb533bac4da9d92b580e9d89d7a41b769e`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `280230cd838ba0d5e99e390fced56c41f2b7fa60502464713f398831d9477c06`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der den **Anmelde-Button** im Terminal-Login vertikal bis zum unteren Rand streckt.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `public/css/terminal.css`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Login: Body-Klasse `terminal-login` gesetzt, wenn kein Mitarbeiter eingeloggt ist.
  - CSS: Login-Formular als Flex-Layout, sodass die Button-Zeile den restlichen Platz nach unten ausfuellt.
- **AKZEPTANZ:**
  - Auf dem Terminal-Login-Screen fuellt der Button **„Anmelden“** den verbleibenden Bildschirmbereich nach unten (kein "kleiner" Button mehr).
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilitaet weiter manuell klicken und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-10
- ZIP: `P-2026-01-09-10_terminal-login-button-fullsize.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `103345_gesammt.zip` = `9e83a3dead0d972891bc336791255c9711435a28a9fcb7daf4f64a9d734806a6`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `346c7a915bba54b1e58e8de67be1442e702df504e5c9d08a54ff2107d0fc445d`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Patch **P-2026-01-09-09** streckt den Login-Button vertikal, aber durch die generische Button-Regel `flex: 1 1 45%` kann der Button je nach Layout weiterhin **nicht sicher volle Breite** einnehmen. → Anpassung ist **nicht** bereits DONE.
- **DATEIEN (max. 3):**
  1) `public/css/terminal.css`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Login: `.terminal-login-buttonrow` wird explizit als Spalte (`flex-direction: column`) gerendert und streckt Inhalte.
  - Terminal-Login: Button "Anmelden" bekommt `width: 100%` + eigenes Flex-Verhalten, damit er **sicher Fullsize** ist (Breite + verbleibende Hoehe).
- **AKZEPTANZ:**
  - Login-Screen: Button "Anmelden" fuellt den verbleibenden Bildschirmbereich **nach unten** und ist **vollbreit** (keine 45%-Breite).
- **TESTS:**
  - n/a (nur CSS/Markdown)
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal-Stabilitaet weiter manuell klicken und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-11
- ZIP: `P-2026-01-09-11_terminal-kommen-gehen-redirect-start.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `104353_gesammt.zip` = `b37ba7b07758e6d6e2da3e0bbda7dd64961cba26baf6fc19f0eed657f6e099eb`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ede614b522ad8b16f8ff6dd2b0ffd0593606851dca7e4347ca29f1bd488f9222`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der nach **Kommen/Gehen** wieder auf `aktion=start` redirectet, damit **Urlaub/Warnungen/Statusboxen** konsistent berechnet werden.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Aktionen **Kommen** und **Gehen** setzen jetzt nur noch Flash (Nachricht/Fehler) und redirecten dann auf `terminal.php?aktion=start`.
  - Dadurch wird der Startsreen immer ueber den zentralen `start()`-Flow gerendert (inkl. Urlaubssaldo-Box), statt die View direkt ohne die benoetigten Variablen zu laden.
- **AKZEPTANZ:**
  - Nach Klick auf **Kommen** oder **Gehen** bleibt die **Urlaub verfuegbar**-Box korrekt (kein "Kontingent fuer 2026 nicht gepflegt" mehr).
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal weiter manuell klicken (Kommen/Gehen/Auftrag/Urlaub) und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-12
- ZIP: `P-2026-01-09-12_terminal-urlaub-meldung-daten-nicht-verfuegbar.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `105225_gesammt.zip` = `80548cd9c59e3d9de6031cd5d5c49e1bb70ddf9b7216222161d27ca60dca8a21`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `5e0a27e2d5d880e2ee48d4ee32c06f66f09bdf7e01a66af1187c028a2d5a2f43`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der die irrefuehrende Terminal-Urlaubsmeldung **"Kontingent ... nicht gepflegt"** als Fallback ersetzt, wenn `$urlaubSaldo` nicht geladen wurde.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Start: Fallback-Text bei fehlenden Urlaubsdaten ist jetzt neutral/korrekt ("Daten aktuell nicht verfuegbar" statt "Kontingent nicht gepflegt").
- **AKZEPTANZ:**
  - Falls im Terminal-Startscreen die Urlaubsdaten aus irgendeinem Grund nicht geladen sind, erscheint **keine** irrefuehrende Kontingent-Meldung mehr.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal weiter manuell klicken und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-13
- ZIP: `P-2026-01-09-13_terminal-rfid-zuweisen-zurueck-zum-start.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `113450_gesammt.zip` = `938142c975881cde32d4cc5a8626075632e751560b10fbd72bbfbbfd32bbd9d2`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `f850a2f4c59fc6e4361b96b5ba11012664e39d2221a2978d3e2c7b6d2a8e398e`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der nach **RFID-Chip zu Mitarbeiter zuweisen → Speichern** automatisch wieder auf den **Kommen/Gehen-Startscreen** zurueckleitet.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Nach erfolgreicher RFID-Zuweisung wird per Redirect wieder `terminal.php?aktion=start` geladen (statt auf dem Zuweisungs-Formular zu bleiben).
  - Flash-Nachricht bleibt erhalten und wird auf dem Startscreen angezeigt.
- **AKZEPTANZ:**
  - Nach Klick auf **Speichern** in der RFID-Zuweisung landet man automatisch wieder im Terminal-Hauptmenue (Kommen/Gehen), nicht im Zuweisungsformular.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal weiter manuell klicken und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-14
- ZIP: `P-2026-01-09-14_terminal-rfid-zuweisung-flash-zielinfo.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `124035_gesammt.zip` = `0988e9fbf7f1572dbe610fc37ccc5711e6ed03a0e7e658bf76b832c9eb94a297`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v8.md` = `e3f96b8331325365011fd8aac0aaf0563e54dfa819fbb92a4196e80762ec489e`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0105f1863754039b5a2b8f790b3214e56add9c659994d3bff850beb5c2b661e1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der nach erfolgreicher RFID-Zuweisung die Flash-Nachricht um Ziel-Infos (Name/PN) erweitert, um Fehlzuweisungen sofort zu erkennen.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Erfolgs-Flash nach RFID-Zuweisung nennt jetzt den Ziel-Mitarbeiter (Name + optional Personalnummer).
  - Logger schreibt optional Personalnummer mit, wenn vorhanden.
- **AKZEPTANZ:**
  - Nach **Speichern** in der RFID-Zuweisung erscheint auf dem Startscreen eine eindeutige Bestätigung, **wem** der RFID-Code zugewiesen wurde.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - **T-069 (Teil 1a):** Terminal weiter manuell klicken und naechste Auffaelligkeit als Micro-Patch fixen.


## P-2026-01-09-16
- ZIP: `P-2026-01-09-16_terminal-offline-rfid-kommen-gehen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `190156_gesammt.zip` = `efc4083e3c5e18e7329aa2073c24dcbc359e8e00a31e1ac91e233807c1396d6a`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0bb7e9c60eba5146ec16a07784ed8a475974b8c28db845c88896202ad77889cf`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der im **Offline-Modus** (Haupt-DB offline) **Kommen/Gehen per RFID** ohne Mitarbeiter-Login ermoeglicht.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Wenn Hauptdatenbank offline ist, wird **kein Login geblockt**, sondern ein **Offline-Modus** angezeigt.
  - Offline-Flow: RFID scannen → Button **Kommen**/**Gehen**.
  - Offline-Buchung: Es wird ein Queue-Eintrag erstellt, der beim Replay die `mitarbeiter_id` ueber RFID aufloest.
  - Kiosk-Sicherheit: Nach Kommen/Gehen wird der gemerkte RFID-Code wieder geloescht.
- **AKZEPTANZ:**
  - Bei Hauptdatenbank-Offlinemode kann man weiter Kommen/Gehen erfassen (RFID + Zeit + Aktion), ohne dass der Login blockiert.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Terminal-Installation: RC522/USB-Reader Einbindung + Offline-Queue-Replay testen (Haupt-DB wieder online).


## P-2026-01-09-17
- ZIP: `P-2026-01-09-17_terminal-offline-vorschlag-letzte-aktion.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `191702_gesammt.zip` = `3995908f133d097a85d77559922407262ca9a37f3fad7e7c0af7e85465bd9472`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ed0942da7a40b492db91bca92e910934107426cede5692ab2017a7532402fd9c`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der im Offline-Modus nach RFID-Scan die **letzte lokale Offline-Aktion** anzeigt und einen **Button-Vorschlag** macht.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Offline-Flow: Nach RFID-Scan wird aus der lokalen Offline-Queue die **letzte Aktion fuer diesen RFID** ermittelt.
  - Anzeige auf dem Offline-Startscreen: **Letzte Offline-Buchung** (Typ + Zeit) + **Vorschlag** (Kommen/Gehen).
  - UX: Der *nicht* vorgeschlagene Button wird als **secondary** (grau) dargestellt, um Fehlklicks zu reduzieren.
  - Kiosk: Beim Zuruecksetzen des gemerkten RFID-Codes wird auch der Hint geloescht.
- **AKZEPTANZ:**
  - Im Offline-Modus sieht man nach dem Scan sofort, was die letzte lokale Aktion war, und bekommt einen klaren Vorschlag, ob **Kommen** oder **Gehen** sinnvoll ist.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Terminal-Feldtest: Offline mehrfach buchen (selber RFID) und pruefen, ob der Vorschlag korrekt wechselt.
  - Danach: Haupt-DB wieder online → Queue-Replay beobachten (Statusbox offen/fehler).
## P-2026-01-09-18
- ZIP: `P-2026-01-09-18_terminal-debounce-pro-rfid.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `192722_gesammt.zip` = `6f5bd028bf382369b24e24b58e7a11c7944b0fcdd85976209c1a353e7f1256ed`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `094b9b5f36c13e48cb43b960debc302b23ec386e03596af02b712c481bb0645e`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - De-Bounce (T-069) war bisher **global** (nur Typ + Zeit). Dadurch konnten zwei verschiedene Mitarbeiter, die kurz hintereinander scannen, faelschlich als „Doppelklick/Scan“ erkannt und ignoriert werden.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - De-Bounce wurde auf **Person-Kontext** erweitert:
    - Online: Vergleich ueber `mitarbeiter_id`
    - Offline: Vergleich ueber `rfid_code`
  - Letzte Buchung in der Session speichert jetzt optional `mitarbeiter_id`/`rfid_code`.
- **AKZEPTANZ:**
  - Zwei verschiedene Mitarbeiter koennen innerhalb weniger Sekunden hintereinander buchen, ohne dass die zweite Buchung als „Doppelklick“ verworfen wird.
  - Derselbe Mitarbeiter/RFID bleibt weiterhin gegen Doppelbuchungen geschuetzt.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Terminal-Feldtest: 2 verschiedene RFID nacheinander schnell buchen (Kommen/Gehen) → beide muessen angenommen werden.


## P-2026-01-09-21
- ZIP: `P-2026-01-09-21_terminal-ws-debug-status.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `202603_gesammt.zip` = `ae961e93abce848a4a7a53926b55238e9156188a5d6d84a11fa59ff1ecd1e484`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `c9279b1ef111503c7ed33429202302966b8713a7108520f7017a6dcd363d3de9`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Es gab bisher keine sichtbare Statusanzeige am Terminal, ob die RFID-WebSocket-Bridge wirklich verbunden ist bzw. ob zuletzt eine UID empfangen wurde.
- **DATEIEN (max. 3):**
  1) `public/js/terminal-rfid-ws.js`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-Debug (T-069): Neue Zeile **"RFID WebSocket"** mit Live-Status.
  - WS-Script aktualisiert den Status bei: verbinden, verbunden, getrennt/reconnect, error sowie bei **UID empfangen** (mit Zeitformat `HH:MM:SS TT-MM-JJJJ`).
- **AKZEPTANZ:**
  - Auf dem Terminal ist im Debug-Details-Feld sofort erkennbar, ob der Browser eine WS-Verbindung aufgebaut hat und wann zuletzt eine UID angekommen ist.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - WebSocket-Bridge stabilisieren: Reconnect-Backoff (1s→2s→5s→10s) + Spam-Protection fuer Statusmeldungen.

## P-2026-01-09-26
- **EINGELESEN (SHA256):**
  - `205025_gesammt.zip` = `909cc80249b287a72e55ab77a042cad46972c24a7208bf710f9f1ce0dd7dd1e4`
  - `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - `DEV_PROMPT_HISTORY.md` = `8535d4bef8337ab02309fb531084105e989b9d5a6fc25f2ecc9e8f28626da61a`
  - `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - `offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:** Kein bestehender roter Banner/Prefill-Flow fuer „unbekannte RFID“ in Terminal-Statusbox gefunden.
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `views/terminal/_statusbox.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal merkt die letzte unbekannte RFID (Session) bei fehlgeschlagenem Login (RFID vorhanden, keine Mehrdeutigkeits-Meldung).
  - Terminal-Statusbox zeigt roten Banner „RFID unbekannt“ (inkl. Zeitstempel).
  - Wenn Adminrecht + Haupt-DB online: Button „RFID jetzt Mitarbeiter zuweisen“ fuehrt in das Zuweisen-Formular (Prefill via GET/Session).
  - Nach erfolgreicher RFID-Zuweisung wird der gespeicherte Hinweis geloescht.
- **NEXT:**
  - Optional: „Hinweis loeschen“ Button am Terminal + kleine Audit-Anzeige (wer/ wann zugewiesen) direkt im Zuweisen-Formular.
## P-2026-01-09-27
- ZIP: `P-2026-01-09-27_terminal-unknown-rfid-clear.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `210229_gesammt.zip` = `5827575a37d8fced5ad06afee9228dd9fdc21ba53a49bbce204d6661e0517e55`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0b4aea0e7c381d15cf8c3301174ea6975593399aa332da41c01d07002a1f95da`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der den roten Banner „RFID unbekannt“ am Terminal manuell (per Button) löschen kann.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/_statusbox.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Im Banner „RFID unbekannt“ gibt es jetzt einen Button **„Hinweis löschen“** (POST+CSRF).
  - Terminal: `start()` verarbeitet `clear_unknown_rfid=1`, löscht die Session-Hinweise und redirectet zurück auf den Startscreen.
- **AKZEPTANZ:**
  - Wenn am Terminal eine falsche/unbekannte RFID gescannt wurde, kann der Hinweis ohne Admin-Aktion wieder entfernt werden.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/_statusbox.php`
- **NEXT:**
  - Sidequest Feldtest: online → offline → online (Offline-Queue füllen, Replay beobachten; ggf. Queue-Fehler „RFID unbekannt“ prüfen und dann per Zuweisung fixen).


## P-2026-01-09-28
- ZIP: `P-2026-01-09-28_terminal-debug-queue-run.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `210909_gesammt.zip` = `9335daf650471ba41ceb32412aee850a72f44f186794e370fdec02f7211d982e`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `2f47c1c003cfd7e8ff4779a5fae37ecf66232cf20034c81edc6495921e899ef5`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Kein bestehender Terminal-Debug-Button zum manuellen Verarbeiten der Offline-Queue gefunden (nur Auto-Replay vorhanden).
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal (Debug): Button „Queue jetzt verarbeiten“ (POST+CSRF) löst sofort `OfflineQueueManager::verarbeiteOffeneEintraege()` aus.
  - Terminal (Debug): Ergebnis-Report wird in der Session gespeichert und im Debug-Panel angezeigt (Offen/Fehler vorher/nachher, Dauer, neue Fehler).
  - Server-seitig abgesichert: nur mit Recht `QUEUE_VERWALTEN`, nur wenn Haupt-DB online und ein Mitarbeiter angemeldet ist.
- **AKZEPTANZ:**
  - Im Debug-Modus kann ein Queue-Admin die Offline-Queue manuell anstoßen und sieht danach einen kurzen Replay-Report im Debug-Panel.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Sidequest Feldtest: online → offline → offline Stempel erzeugen → online → Debug-Button nutzen und prüfen ob alles sauber nachgezogen wird.

## P-2026-01-10-01
- ZIP: `P-2026-01-10-01_terminal-auto-logout-nach-stempeln.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `081233_gesammt.zip` = `b917f037904dba7b9c78f67b1723d5c1c4404dd559dcdc6c0e983663043947c4`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8b39c8f41bd317c9266e988b90762d85656b55e68b55eff5621c6c2a0a519711`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Kein bestehender Auto-Logout nach erfolgreichem Kommen/Gehen gefunden (bisher nur Timeout/Abbrechen).
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Nach erfolgreichem „Kommen“ oder „Gehen“ wird der Mitarbeiter automatisch abgemeldet (Kiosk-Flow).
  - Logout-Session-Clear zentralisiert; `terminal_anwesend` wird beim Logout mit gelöscht, damit Offline-Fallback nicht auf den nächsten Mitarbeiter durchschlägt.
- **AKZEPTANZ:**
  - Nach Klick auf „Kommen“ oder „Gehen“ landet man direkt wieder im Login-Screen; kein „Abbrechen“ und kein Warten auf Timeout nötig.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Terminal: Mehrfach hintereinander scannen → Kommen/Gehen → prüfen dass immer sofort wieder der Login erscheint (danach: Offline-Queue Feldtest fortsetzen).

## P-2026-01-10-02
- ZIP: `P-2026-01-10-02_terminal-abbrechen-grosser-button.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `083013_gesammt.zip` = `03debd750eea9316e52046943344d9ac7086de39db9e4f5216e7ab15bc319a67`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `dd469d87d6a34018cf5ad2297ff154c8e9f000a56bf6ce4253f90e8b64be9e60`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der den Logout/„Abbrechen“ am Terminal-Startscreen als großen Button rendert (Touch-Problem bisher offen).
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Logout/„Abbrechen“ am Startscreen ist jetzt ein großer Button im Kommen/Gehen-Stil (Touch-optimiert).
- **AKZEPTANZ:**
  - Nach RFID-Login ist „Abbrechen“ als großer Button leicht zu treffen und loggt sofort aus.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Terminal: Buttons „Urlaub …“ und „RFID …“ (wenn anwesend) nebeneinander anordnen, um Scrollen zu vermeiden.

## P-2026-01-10-04
- ZIP: `P-2026-01-10-04_terminal-urlaub-verfuegbar-box-entfernt.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `091951_gesammt.zip` = `0c385135b763d0757dd3830b8cdaa971d192e2729f06e765b55521e8648b7e3f`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `c645523f915c024c42b71398236bdca1e4ea0fcd37be503cf98ec9cc099a7546`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Es gibt am Terminal-Startscreen eine eigene Status-Box „Urlaub verfügbar“; kein Patch dokumentiert, der diese entfernt/ausblendet (Platzproblem auf kleinen Displays).
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Die Status-Box „Urlaub verfügbar“ wird am Startscreen nach RFID-Login nicht mehr angezeigt (spart Platz; weniger Scrollen).
- **AKZEPTANZ:**
  - Nach RFID-Login sind die Haupt-Buttons ohne Scrollen besser erreichbar; „Urlaub verfügbar“ wird nicht mehr direkt im Startscreen angezeigt.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Terminal: „Urlaub beantragen“ → „Urlaub Übersicht“ umbauen (Übersicht-Seite + separater, cleaner Antrag-Screen).

## P-2026-01-10-08
- ZIP: `P-2026-01-10-08_terminal-touch-abbrechen-in-forms.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `100113_gesammt.zip` = `3cca7757f51ba2d5641b16c643458281d29e49e59c110f1290b8002e349dbfbf`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `2b43c416a7269c6aa260b8b8ee37cb9ff9ddbce14c88c92d4ccc7cc9dd327a4d`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Kein Patch dokumentiert, der „Abbrechen/Zurück“ in den Terminal-Formularen **RFID-Zuweisen** und **Nebenauftrag (Start/Stop)** als große Touch-Buttons rendert.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: In den Formularen „RFID-Chip zu Mitarbeiter zuweisen“ sowie „Nebenauftrag starten/stoppen“ sind die Buttons „Abbrechen/Zurück“ (und die Primär-Aktionen) jetzt im gleichen Touch-Format wie Kommen/Gehen (`terminal-primary-action`).
- **AKZEPTANZ:**
  - Auf dem Touchscreen lassen sich in RFID-Zuweisen und Nebenauftrag-Formularen „Abbrechen/Zurück“ sicher treffen (gleiche Button-Größe wie Kommen/Gehen).
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Sidequest Feldtest: offline Stempel erzeugen → online → Debug-Button (P-2026-01-09-28) nutzen; Replay-Report beobachten (Fehlerliste/Count).

## P-2026-01-10-09
- ZIP: `P-2026-01-10-09_terminal-systemstatus-bottom-toggle.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `113057_gesammt.zip` = `fc823559522a384dedf7dc3bacca33f8bc169716e469fc296c1591c9699b1ec0`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `935360018e41cf4325c71dd21f6c17c139d6e99e074ed576498ea6b90fe301e8`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Systemstatus war oben fix eingeblendet und hat auf kleinen Displays unnötig Platz verbraucht.
- **DATEIEN (max. 3):**
  1) `views/terminal/_statusbox.php`
  2) `public/css/terminal.css`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Systemstatus ist jetzt als aufklappbares `<details>` umgesetzt und unten angedockt (Touch-freundlich, spart Platz).
  - Terminal: Die obere H1-Überschrift wird im Terminal per CSS ausgeblendet (mehr Platz für Buttons/Inhalte).
- **AKZEPTANZ:**
  - Systemstatus steht nicht mehr oben im Weg; einzeilige Zusammenfassung unten, Details per Klick.
  - Oben wird kein "Terminal – Zeiterfassung" mehr angezeigt.
- **TESTS:**
  - `php -l views/terminal/_statusbox.php`
- **NEXT:**
  - Terminal: „Mitarbeiter: …“ ebenfalls als aufklappbares Panel ganz unten (wie „Übersicht heute“), damit oben weiter Platz gewonnen wird.


## P-2026-01-10-10
- ZIP: `P-2026-01-10-10_terminal-betriebsferien-urlaubstage-anzeige.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `163428_gesammt.zip` = `84d2aad83b7776fcd38c6c69f9c4c9b52e843e94ca6ac3187efb7ca48fab251f`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1c05a9dce5cc98ae09d0348df7f26b618d4c18235c7e87f0b4b77536855c3281`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Urlaub Übersicht zeigt Betriebsferien-Datum, aber keine Angabe, wie viele Urlaubstage dafür benötigt werden.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Urlaub Übersicht – Betriebsferien-Einträge enthalten jetzt zusätzlich „benötigte Urlaubstage: …“ (berechnet als Arbeitstage innerhalb des angezeigten Jahres).
- **AKZEPTANZ:**
  - In der Urlaub Übersicht steht bei jedem Betriebsferien-Eintrag (wenn >0) der Text „benötigte Urlaubstage: X,XX“.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Sidequest Feldtest: online → offline → offline Stempel erzeugen → online → Debug-Button (P-2026-01-09-28) nutzen; Replay-Report beobachten (Fehlerliste/Count).


## P-2026-01-10-11
- ZIP: `P-2026-01-10-11_terminal-urlaubliste-kompakt-genehmigt.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `16571911_gesammt.zip` = `3735501c18bf895e9ef0f1a95ac80bbf3662ef9e0442e16e926d747e71b99b3c`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `48a1c70c6da82d4be32c05b2d32b3283833e3190b20987585401daa8a862efbc`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Keine bestehende Umsetzung gefunden, die genehmigte Vorjahres-Anträge im Terminal ausblendet oder genehmigte Einträge standardmäßig einzeilig (Details per Klick) rendert.
- **DATEIEN (max. 3):**
  1) `views/terminal/_urlaub_antraege_liste.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Urlaubsanträge-Liste blendet genehmigte Anträge aus, deren Enddatum vor dem 01.01. des laufenden Jahres liegt.
  - Terminal: Einträge mit Status „genehmigt“ sind standardmäßig kompakt und zeigen Details (Antrag+ID) erst nach Klick (details/summary).
- **AKZEPTANZ:**
  - Genehmigte Vorjahres-Anträge erscheinen nicht mehr, und genehmigte Anträge sind ohne Zusatzzeile sichtbar (Details per Klick).
- **TESTS:**
  - `php -l views/terminal/_urlaub_antraege_liste.php`
- **NEXT:**
  - Terminal: Urlaub Übersicht – Betriebsferien-Zeile „benötigte Urlaubstage: …“ muss im Terminal sichtbar sein (Berechnung prüfen; nicht 0 durch Betriebsferien-Filter).


## P-2026-01-10-12
- ZIP: `P-2026-01-10-12_terminal-betriebsferien-urlaubstage-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `175930_gesammt.zip` = `05b0ee361a42ea293ba2ac64cf8076d02935c6cd4d0145dac8d423a2424fe75e`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0f50bd1bdf32396035163f5734cb2628384ef53387f114a733134262d8b3ce43`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - P-2026-01-10-10 hat die Anzeige bereits ergänzt, aber die Berechnung nutzte die normale Urlaubs-Arbeitstage-Logik (die Betriebsferien selbst herausfiltert). Dadurch kam bei reinen Betriebsferien-Zeiträumen meist `0` heraus und der Text wurde nicht angezeigt.
- **DATEIEN (max. 3):**
  1) `services/UrlaubService.php`
  2) `controller/TerminalController.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Betriebsferien „benötigte Urlaubstage“ wird jetzt als Arbeitstage im Zeitraum berechnet, wobei Wochenenden und betriebsfreie Feiertage nicht zählen, Betriebsferien-Tage aber **nicht** herausgefiltert werden.
- **AKZEPTANZ:**
  - In der Terminal-Urlaub-Übersicht wird bei einem Betriebsferien-Zeitraum mit Arbeitstagen (z. B. 18-12-2025 bis 16-01-2026) „benötigte Urlaubstage: X,XX“ mit einem Wert > 0 angezeigt.
- **TESTS:**
  - `php -l services/UrlaubService.php`
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Sidequest Feldtest: online → offline → offline Stempel erzeugen → online → Debug-Button (P-2026-01-09-28) nutzen; Replay-Report beobachten (Fehlerliste/Count).


## P-2026-01-10-13
- ZIP: `P-2026-01-10-13_terminal-mitarbeiterpanel-unten.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `180641_gesammt.zip` = `beb42b3746ca4e7391b53350acac2bb15ebc4e7d85f31d7b6b3e0aa64b90e89f`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1683a0a431314a8da44593fc7bfad6257cd14f448d1ebe51c1ca4119bb8a5ff3`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Kein bereits vorhandenes, unten angedocktes (aufklappbares) Mitarbeiter-Panel gefunden; Mitarbeiter-Info war als große Box oben im Content.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `public/css/terminal.css`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Mitarbeiter-Info wurde in ein platzsparendes <details>-Panel umgebaut.
  - Terminal: Panel ist unten angedockt (über Systemstatus) und nimmt oben keinen Platz mehr weg.
- **AKZEPTANZ:**
  - Im Terminal ist die Mitarbeiter-Info standardmäßig eingeklappt und unten sichtbar; oben entsteht mehr Platz.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Terminal: gleiche Umstellung der Mitarbeiter-Box in `views/terminal/auftrag_starten.php`, `views/terminal/auftrag_stoppen.php`, `views/terminal/urlaub_beantragen.php` (damit alle Screens konsistent sind).


## P-2026-01-10-14
- ZIP: `P-2026-01-10-14_terminal-urlaubssaldo-anspruch-korrektur.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `182343_gesammt.zip` = `1f56725090ffa39b94250c0679d031e5aae7a5f6b528faaf5a3540ac2a13faee`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ce6c8ab2f7d3e39c7cfa7a7d218069ff30647d780678b13108bae9cf5fdfe04d`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - In `views/terminal/start.php` wurde im Urlaubssaldo „Anspruch (YYYY)“ als `anspruch + korrektur` angezeigt → keine bestehende Klartext-Aufschlüsselung gefunden.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Urlaubssaldo zeigt jetzt **Jahresanspruch** (Basis), optional **Korrektur** und **Effektiv** getrennt; „Übertrag“ bleibt separat.
- **AKZEPTANZ:**
  - Beispiel: Jahresanspruch 30,00 + Korrektur -18,00 wird als „Jahresanspruch: 30,00 | Korrektur: -18,00 | Effektiv: 12,00“ angezeigt.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Terminal: gleiche Aufschlüsselung auch in `views/terminal/urlaub_beantragen.php` (Anspruch/Übertrag/Korrektur/Effektiv konsistent).
  - Optional: Backend „Mein Urlaub“: gleiche Benennung/Erklärung übernehmen.

## P-2026-01-10-15
- ZIP: `P-2026-01-10-15_terminal-urlaubbeantragen-urlaubssaldo-klar.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `183357_gesammt.zip` = `227356716a4f57535d0e4e386fec83c27b2eb36ca4ca5809f9de12c55f70d720`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `3bf2e3d99e9d246b13c74b06f5e4c55fe6f55fea20aee1e11c51e110462e58aa`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - In `views/terminal/urlaub_beantragen.php` war der Urlaubssaldo noch im alten Format (Anspruch/Übertrag/Korrektur in 1 Zeile) → keine Klartext-Aufschlüsselung wie in „Urlaub Übersicht“ vorhanden.
- **DATEIEN (max. 3):**
  1) `views/terminal/urlaub_beantragen.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Urlaub beantragen – Urlaubssaldo zeigt jetzt **Verfügbar/Genehmigt/Offen** und darunter **Jahresanspruch (Basis)**, optional **Korrektur** + **Effektiv** sowie **Übertrag** (Vorjahr) konsistent zur „Urlaub Übersicht“.
- **AKZEPTANZ:**
  - Im Terminal unter „Urlaub beantragen“ wird z. B. „Jahresanspruch: 30,00 | Korrektur: -18,00 | Effektiv: 12,00“ angezeigt (wie in „Urlaub Übersicht“).
- **TESTS:**
  - `php -l views/terminal/urlaub_beantragen.php`
- **NEXT:**
  - Terminal: Mitarbeiter-Info auch in `views/terminal/urlaub_beantragen.php` als Bottom-Toggle (<details>) umbauen (mehr Platz oben, konsistent zu Startscreen).
  - Sidequest Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Debug-Replay-Report prüfen).
## P-2026-01-10-16
- ZIP: `P-2026-01-10-16_terminal-urlaubbeantragen-mitarbeiterpanel-unten.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `184205_gesammt.zip` = `ae9cbd6797636a5fcdb270f638a8a162bb6d9c76c3eab95e3963385b79aec7cb`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1e323571e3ee72f197e21b95e3e3560717a1f8ee100e34bfd1e3caf6cc02ee75`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - In `views/terminal/urlaub_beantragen.php` war die Mitarbeiter-Info als große `.mitarbeiter-box` oben im Content → kein unten angedocktes Panel genutzt.
- **DATEIEN (max. 3):**
  1) `views/terminal/urlaub_beantragen.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: „Urlaub beantragen“ – Mitarbeiter-Info als unten angedocktes, aufklappbares Panel (<details>) umgesetzt (mehr Platz oben).
- **AKZEPTANZ:**
  - Auf der Seite „Urlaub beantragen“ ist die Mitarbeiter-Info unten eingeklappt sichtbar; oben erscheint keine große Mitarbeiter-Box mehr.
- **TESTS:**
  - `php -l views/terminal/urlaub_beantragen.php`
- **NEXT:**
  - Terminal: gleiche Umstellung in `views/terminal/auftrag_starten.php` und `views/terminal/auftrag_stoppen.php`.
  - Sidequest Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Debug-Replay-Report prüfen).

## P-2026-01-10-17
- ZIP: `P-2026-01-10-17_terminal-auftrag-starten-mitarbeiterpanel-unten.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `190841_gesammt.zip` = `7426b2f64470bb187bbf938c11b6a18c086205ff2bf68cfc0adef45c19f56c6a`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `fee108848869aa78c26a744ac2a4082ae07e8da9b9dfce499037babb87ee5bb0`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Im SNAPSHOT/LOG war die Umstellung der Mitarbeiter-Box in `views/terminal/auftrag_starten.php` noch als NEXT offen; die View nutzte noch die große `.mitarbeiter-box` → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/terminal/auftrag_starten.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: „Auftrag starten“ – Mitarbeiter-Info als unten angedocktes, aufklappbares Panel (<details>) umgesetzt (mehr Platz oben).
- **AKZEPTANZ:**
  - Auf der Seite „Auftrag starten“ ist die Mitarbeiter-Info unten eingeklappt sichtbar; oben erscheint keine große Mitarbeiter-Box mehr.
- **TESTS:**
  - `php -l views/terminal/auftrag_starten.php`
- **NEXT:**
  - Terminal: gleiche Umstellung in `views/terminal/auftrag_stoppen.php` (für Konsistenz).


## P-2026-01-10-18
- ZIP: `P-2026-01-10-18_terminal-auftrag-stoppen-mitarbeiterpanel-unten.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `192858_gesammt.zip` = `c7c0bdf4418b84cbb8ac73d1891c5ee3b41092edcb2465caedd132b5db1d4185`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `42c1f23e23cfed48bc2ba6207a9722352e179619ef7a9399dda9b277abcb0217`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - In `views/terminal/auftrag_stoppen.php` war die Mitarbeiter-Info als große `.mitarbeiter-box` oben im Content → kein unten angedocktes Panel genutzt.
- **DATEIEN (max. 3):**
  1) `views/terminal/auftrag_stoppen.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: „Hauptauftrag stoppen“ – Mitarbeiter-Info als unten angedocktes, aufklappbares Panel (<details>) umgesetzt (mehr Platz oben).
- **AKZEPTANZ:**
  - Auf der Seite „Hauptauftrag stoppen“ ist die Mitarbeiter-Info unten eingeklappt sichtbar; oben erscheint keine große Mitarbeiter-Box mehr.
- **TESTS:**
  - `php -l views/terminal/auftrag_stoppen.php`
- **NEXT:**
  - Sidequest Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Debug-Replay-Report prüfen).




## P-2026-01-10-19
- ZIP: `P-2026-01-10-19_terminal-debug-queue-feldtest-helper.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193154_gesammt.zip` = `ff108a56661b03cd4d5fb8f0cf4b7ee987e020a26bdb8acb7e9017d14c0fb3c8`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `d8f387630951e2f9b63e8b6ba0e45bb65a60f2ffd03a7dc5ada90bb80bd1ce74`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - In `views/terminal/start.php` existierte bereits der Debug-Block „Offline-Queue (manuell)“; wir ergänzen nur platzsparende Feldtest-Hilfen.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal (Debug): Offline-Queue Kurzstatuszeile (Haupt-DB/Queue/Offen/Fehler + Speicherort) ergänzt.
  - Terminal (Debug): Feldtest-Checkliste eingeklappt (Details) + Hinweis auf Health-JSON und Backend-Queue.
  - Terminal (Debug): „Letzte Queue-Einträge (Top 10)“ eingeklappt (mehr Platz auf kleinen Displays).
- **AKZEPTANZ:**
  - `terminal.php?aktion=start&debug=1` zeigt im Offline-Queue-Block eine Kurzstatuszeile.
  - Feldtest-Checkliste und Queue-Einträge sind per Klick aufklappbar; Standardansicht bleibt kompakt.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Sidequest Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Debug-Replay-Report prüfen).


## P-2026-01-10-20
- ZIP: `P-2026-01-10-20_terminal-css-button-align.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `200709_gesammt.zip` = `4230fa041bd86c25b5db0c5a31240250aacd9f15efe500716e1ef6eaf8443db0`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a7d13a3aba7b60c3d904d404f478e02b5c1d6290f1c5eca2a20549c15247e30c`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Buttons/Links im Terminal hatten je nach Browser minimale vertikale Abweichungen (z. B. „Abbrechen“ neben „Antrag speichern“).
- **DATEIEN (max. 3):**
  1) `public/css/terminal.css`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal-CSS: Buttons/Links werden als Flex-Container gerendert und zentrieren den Text (einheitliche Ausrichtung, kein „Abbrechen“-Versatz).
- **AKZEPTANZ:**
  - Auf Terminal-Seiten mit 2 Buttons in einer Zeile (z. B. „Urlaub beantragen“) sind beide Buttons optisch gleich ausgerichtet.
- **TESTS:**
  - (manuell) Terminal-Seite „Urlaub beantragen“ prüfen.
- **NEXT:**
  - Sidequest Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Debug-Replay-Report prüfen).

## P-2026-01-10-21
- ZIP: `P-2026-01-10-21_terminal-topbar-offline-pill.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `203223_gesammt.zip` = `1674888fb9494e7f30651a5e76c68fd8af0fa08fe7421f8ed4c09f3caa462a6e`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `e594d64da0a01f057dc24f5338a7af90ad10e98c86e5205e7d9138bae9e3dffc`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - In der Terminal-Topbar wird bisher nur die Uhr angezeigt; ein visuelles Signal für Offline/Störung fehlte.
- **DATEIEN (max. 3):**
  1) `views/terminal/_layout_top.php`
  2) `public/css/terminal.css`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: In der Topbar wird bei Offline-Modus bzw. Störung ein kleines Badge angezeigt (OFFLINE / STÖRUNG).
- **AKZEPTANZ:**
  - Wenn `Systemstatus: Offline-Modus`, erscheint oben neben der Uhr ein Badge „OFFLINE“.
  - Wenn `Systemstatus: Störung`, erscheint oben neben der Uhr ein Badge „STÖRUNG“.
  - Im Normalfall (Online) wird kein Badge angezeigt.
- **TESTS:**
  - `php -l views/terminal/_layout_top.php`
- **NEXT:**
  - Sidequest Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Debug-Replay-Report prüfen).

## P-2026-01-10-22
- ZIP: `P-2026-01-10-22_terminal-healthcheck-interval-config.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `204147_gesammt.zip` = `b328c24a752b2a40dbf16261acdf7d2efb778ca5c69eea8d4d5aec415904858d`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `f6aa263fd52c0eb66a104b68a4274417fac24f6f0a3920ecfd8f5409835c1c15`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - Hauptdatenbank/Queue-Status wird bisher nur pro Seitenrequest geprüft; für Kiosk-Terminals soll die Anzeige regelmäßig aktualisiert werden.
- **DATEIEN (max. 3):**
  1) `core/DefaultsSeeder.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Config-Seed ergänzt: `terminal_healthcheck_interval` (int, Sekunden) – Intervall für wiederkehrende Healthchecks/Statusanzeige am Terminal (Default 10).
- **AKZEPTANZ:**
  - In der Tabelle `config` existiert der Key `terminal_healthcheck_interval` (wird automatisch angelegt, falls fehlend).
  - Wert kann per Konfigurations-UI/SQL angepasst werden (z. B. 5, 10, 30 Sekunden; keine Überschreibung bestehender Werte).
- **TESTS:**
  - `php -l core/DefaultsSeeder.php`
- **NEXT:**
  - Terminal-JS pollt `terminal.php?aktion=health` alle X Sekunden (aus `terminal_healthcheck_interval`) und aktualisiert Topbar-Badge + Systemstatus-Box ohne Reload.

## P-2026-01-10-23
- ZIP: `P-2026-01-10-23_terminal-healthcheck-polling.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `205227_gesammt.zip` = `4c5cfe7808cca584ad2bfe5e14bb750ac6cce209947c2c91ca334c889fb4e473`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `a931958cee744aee1a1e39eeda3d91592f80f32b963fbcec223674c74175b922`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - SQL-Schema Offline (aus ZIP): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: P-2026-01-10-22 hatte als NEXT „JS Polling“ offen; kein Eintrag, der das Polling bereits umgesetzt hat → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/terminal/_autologout.php`
  2) `public/js/terminal-health-poll.js`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal lädt ein kleines JS, das `terminal.php?aktion=health` **alle X Sekunden** abfragt (X = `terminal_healthcheck_interval`).
  - Topbar-Badge (OFFLINE/STÖRUNG) und Systemstatus-Box werden ohne Seitenreload aktualisiert.
- **AKZEPTANZ:**
  - Wenn die Hauptdatenbank „online → offline“ wechselt, erscheint nach spätestens X Sekunden das Badge „OFFLINE“.
  - Wenn „offline → online“, verschwindet das Badge ohne Reload.
  - Systemstatus-Box (Details) zeigt laufend aktualisierte Werte (Hauptdatenbank/Queue/Offen/Fehler).
- **TESTS:**
  - `php -l views/terminal/_autologout.php`
- **NEXT:**
  - Optional: Polling nur aktivieren, wenn `terminal_healthcheck_interval > 0` (0 = deaktiviert) + UI-Hinweis in Konfiguration.


## P-2026-01-10-24
- ZIP: `P-2026-01-10-24_terminal-healthcheck-disable-zero.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `210311_gesammt.zip` = `0b60e8f8de82d8c4cae0d369efc44f816cf754af862e63064088066f9f2ef643`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `4f0af7b21bf5f3eb464b576ac3c85ee09ca2096534d4a65b76ea127d79f87d6f`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DATEIEN (max. 3):**
  - `views/terminal/_autologout.php`
  - `public/js/terminal-health-poll.js`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: `terminal_healthcheck_interval = 0` deaktiviert Health-Polling (keine zyklischen Health-Requests).
- **AKZEPTANZ:**
  - Wenn `terminal_healthcheck_interval` auf `0` steht, werden keine zyklischen Requests an `terminal.php?aktion=health` abgesetzt.
  - Bei Werten `>= 2` pollt das Terminal weiterhin im eingestellten Intervall (max. 300s).
- **TESTS:**
  - `php -l views/terminal/_autologout.php`
- **NEXT:**
  - Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Replay/Report prüfen).

## P-2026-01-11-02
- ZIP: `P-2026-01-11-02_dashboard-zeitwarnungen-aktionen-spalte.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `211359_gesammt.zip` = `f5e5ef372f981b9e3d732b12213e39963ca31896dcd2e88ca3f00581e9cb70d3`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload, Stand vor Patch): `DEV_PROMPT_HISTORY.md` = `846fda55f2813e2288222a02d1f315bb251c88c54740ea57b4d84c943821f046`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: P-2026-01-07-15 hat die Links „Monat öffnen“/„Tag öffnen“ eingeführt, aber kein Eintrag, der die Spaltenzuordnung in der Dashboard-Tabelle korrigiert → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/dashboard/index.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard: In der Warn-Tabelle „Unvollständige Zeitbuchungen“ werden jetzt
    - „Öffnen“ = Gesamtanzahl Buchungen,
    - „Kommen“/„Gehen“ = jeweilige Anzahl,
    - „Monat öffnen | Tag öffnen“ korrekt in der Spalte „Aktion“ angezeigt.
- **AKZEPTANZ:**
  - In der Tabelle „Unvollständige Zeitbuchungen“ steht „Monat öffnen | Tag öffnen“ unter „Aktion“ und nicht unter „Gehen“.
- **TESTS:**
  - `php -l views/dashboard/index.php`
- **NEXT:**
  - Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Replay/Report prüfen).


## P-2026-01-11-03 (P-2026-01-11-03_monatsuebersicht-mehrfach-kommen.zip)
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `070339_gesammt.zip` = `8b723565e4254038d1af6e20338c637902a179b761f23bf0bde003e51aa77d97`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload, Stand vor Patch): `DEV_PROMPT_HISTORY.md` = `846fda55f2813e2288222a02d1f315bb251c88c54740ea57b4d84c943821f046`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
  - Offline-Schema (Upload): `offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Kein bestehender Patch, der „Mehrfach-kommen hintereinander“ in der Monatsübersicht sichtbar macht → **nicht DONE**, Implementierung erforderlich.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsübersicht: In der Arbeitsblöcke-Berechnung werden mehrere „Kommen“ hintereinander nicht mehr ignoriert; jeder zusätzliche „Kommen“-Stempel schließt den vorherigen offenen Block als „unvollständig“ ab und startet einen neuen Block.
- **AKZEPTANZ:**
  - Wenn an einem Tag zwei „Kommen“-Stempel ohne dazwischenliegendes „Gehen“ existieren, zeigt die Monatsübersicht beide Stempel als separate Blöcke; der fehlende Gegenstempel wird als „FEHLT“ angezeigt.
- **TESTS:**
  - `php -l services/ReportService.php`
- **NEXT:**
  - Monatsübersicht: Analog „Gehen ohne vorheriges Kommen“ als eigener Block anzeigen, damit auch fehlende „Kommen“-Stempel sichtbar werden.
## P-2026-01-11-04
- ZIP: `P-2026-01-11-04_tagesansicht-buchung-bearbeiten-box.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `071442_gesammt.zip` = `c369645aa2fc7423c73ced84160c84aa2daefff89781778dfefce358a2b232f3`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload, Stand vor Patch): `DEV_PROMPT_HISTORY.md` = `846fda55f2813e2288222a02d1f315bb251c88c54740ea57b4d84c943821f046`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Kein bestehender Patch, der die „Buchung bearbeiten“-Maske in der Tagesansicht direkt unter die Zeit-Tabelle zieht → **nicht DONE**, Fix erforderlich.
- **DATEIEN (max. 3):**
  1) `views/zeit/tagesansicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Tagesansicht: Wenn eine Zeitbuchung über „Bearbeiten“ geöffnet wird, erscheint die Edit-Maske direkt unter der Zeitbuchungs-Tabelle und ist durch einen dünnen roten Rahmen klar hervorgehoben.
- **AKZEPTANZ:**
  - In der Tagesansicht erscheint „Buchung bearbeiten“ nach Klick auf „Bearbeiten“ direkt unter der Tabelle mit den Zeitbuchungen (sichtbar ohne weit nach unten zu scrollen) und ist rot umrandet.
- **TESTS:**
  - `php -l views/zeit/tagesansicht.php`
- **NEXT:**
  - Monatsübersicht: Analog „Gehen ohne vorheriges Kommen“ als eigener Block anzeigen, damit auch fehlende „Kommen“-Stempel sichtbar werden.

## P-2026-01-11-05
- ZIP: `P-2026-01-11-05_urlaub-saldo-box-klarer.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `073020_gesammt.zip` = `9fc43bf4f33e6c0c76beeb9e2a6f80165c467dcc5f3fd9d5f7a8842de626424a`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload, Stand vor Patch): `DEV_PROMPT_HISTORY.md` = `846fda55f2813e2288222a02d1f315bb251c88c54740ea57b4d84c943821f046`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Es existiert bereits die Urlaubs-Übertrag-Logik (T-079), aber kein Patch, der die Anzeige in „Mein Urlaub / Meine Urlaubsanträge“ klar als War/Korrektur/Verbraucht/Übrig aufschlüsselt (statt Klammer-Notation). → **nicht DONE**, UX-Fix erforderlich.
- **DATEIEN (max. 3):**
  1) `views/urlaub/meine_antraege.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: In „Mein Urlaub / Meine Urlaubsanträge“ wird der Urlaubssaldo jetzt klar getrennt dargestellt:
    - **Übertrag (Vorjahr):** War (Auto), Korrektur (Manuell), Effektiv, Verbraucht, Übrig
    - **Jahr (YYYY):** Anspruch (Auto), Rest-Korrektur (falls vorhanden), Effektiv, Verbraucht, Übrig
    - „Gesamt übrig | Genehmigt | Offen“ bleibt als kompakte Zusammenfassung bestehen.
- **AKZEPTANZ:**
  - In „Mein Urlaub → Meine Urlaubsanträge“ ist die Box „Urlaub verfügbar“ verständlich: Übertrag zeigt „War (Auto) … / Korrektur (Manuell) … / Übrig …“ (analog für das Jahr), ohne die frühere Klammer-Zusammenfassung.
- **TESTS:**
  - `php -l views/urlaub/meine_antraege.php`
- **NEXT:**
  - Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Replay/Report prüfen).


## P-2026-01-11-06
- ZIP: `P-2026-01-11-06_urlaub-genehmigen-kommentar-zentriert.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `074416_gesammt.zip` = `dd1518e73751b59ed5c5c3371680acaa0f4c01b9f2fe539197623260853e1000`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload, Stand vor Patch): `DEV_PROMPT_HISTORY.md` = `21fff11d6e4edd90505e87a747242ed450ffdd622c7c5fb6e0af17536d3ec5c7`
  - SQL-Schema (Upload): `zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Es existiert kein Patch, der in „Urlaub genehmigen“ den Text in der Spalte „Kommentar Mitarbeiter“ mittig ausrichtet. → **nicht DONE**, UX-Fix erforderlich.
- **DATEIEN (max. 3):**
  1) `views/urlaub/genehmigung_liste.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: „Urlaub genehmigen“ – Spalte „Kommentar Mitarbeiter“ ist jetzt horizontal und vertikal zentriert, damit kurze Kommentare (z. B. „sdf“) sauber mittig stehen.
- **AKZEPTANZ:**
  - In „Urlaub genehmigen → Offene Urlaubsanträge“ ist der Text in der Spalte „Kommentar Mitarbeiter“ mittig ausgerichtet (nicht oben/links).
- **TESTS:**
  - `php -l views/urlaub/genehmigung_liste.php`
- **NEXT:**
  - Feldtest Offline-Queue End-to-End (online → offline stempeln → online → Replay/Report prüfen).


## P-2026-01-11-07
- ZIP: `P-2026-01-11-07_rechte-prompt-doku.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `074932_gesammt.zip` = `47f090f44cef2a143dfac71b92b2f7efffa39bbb099538c77edc946099f95102`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `f74c8e24c730521864259f1eb942c70cb8128d252140f3c48250858407beb711`
  - SQL-Schema (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - Projekt durchsucht: Kein bestehendes Dokument `docs/rechte_prompt.md` vorhanden; doppelte Rechte waren nicht dokumentiert → **nicht DONE**, Dokumentation erforderlich.
- **DATEIEN (max. 3):**
  1) `docs/rechte_prompt.md`
  2) `docs/master_prompt_zeiterfassung_v10.md`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neue zentrale Rechte-Dokumentation `docs/rechte_prompt.md` erstellt (kanonische Rechte, Prüfpunkte im Code, Legacy/Duplikate, Feature-Matrix, Roadmap für Zusammenführungen).
  - MasterPrompt v10 ergänzt: Rechte-Prompt als Source of Truth + Pre-Flight Gate referenziert `docs/rechte_prompt.md`.
- **AKZEPTANZ:**
  - Es gibt eine einzelne, klare Referenzdatei, welche Rechte wofür benötigt werden und wo sie im Code geprüft werden.
  - Doppelte/Legacy-Rechte sind explizit aufgelistet inkl. Mapping/Plan, wie sie zusammengeführt werden.
- **TESTS:**
  - n/a (nur Doku)
- **NEXT:**
  - Phase 1 umsetzen: SQL-Migration „Legacy-Rechte mergen“ + Rollen-UI bereinigen (keine doppelten Einträge; optional Soft-Delete via `aktiv=0`).

## P-2026-01-11-08
- ZIP: `P-2026-01-11-08_rechte-prompt-inventar.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `082046_gesammt.zip` = `e99af3f885e1335f867304b150ef0153d587aa9c48d6567b84c8b77c799fe604`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `98db8ce136ac5de9d22c457992670410b03f17bc30c4bdac016d853925fd82a3`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `16b5da2d24a5f583743139d00569bc4cf43ea75b3e25bdecf17fc3d171ddf566`
  - SQL-Schema (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - `docs/rechte_prompt.md` existiert (P-2026-01-11-07), aber es fehlte noch die **kompakte Erklärung**, warum Rechte in „Rolle bearbeiten“ doppelt erscheinen, sowie ein **vollständiges Inventar** aller DB-Rechte inkl. „im Code geprüft“/Merge-Ziel → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `docs/rechte_prompt.md`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `docs/rechte_prompt.md` erweitert um:
    - klare Erklärung der Doppelungen (Legacy vs kanonisch),
    - Inventar-Tabelle aller Rechte aus `sql/zeiterfassung_aktuell.sql`,
    - Status „im Code geprüft“ + Merge-Ziel pro Legacy-Recht.
- **AKZEPTANZ:**
  - Beim Blick auf „Rolle bearbeiten“ ist sofort klar, warum es doppelte Rechte gibt und welche davon Legacy sind.
  - Es gibt eine vollständige Liste aller Rechte-Codes aus der DB inkl. Hinweis, ob der Code im PHP-Code tatsächlich geprüft wird.
- **TESTS:**
  - n/a (nur Doku)
- **NEXT:**
  - Phase 1 umsetzen: SQL-Migration „Legacy-Rechte mergen/ausblenden“ + Rollen-UI bereinigen (keine doppelten Einträge; optional Soft-Delete via `aktiv=0`).

## P-2026-01-11-09
- ZIP: `P-2026-01-11-09_rechte-phase1a-legacy-merge.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `082922_gesammt.zip` = `768f19596efc6b9c3431e7faa55dfd31b3cec87d088608938bef60aabc5c9e98`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `da69dff80532bce26ac3f1008483521caef03cb2e7bb0306cf58506d57e366f2`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `4b58d3ffc4c7a961a2b51425df9375d64fb58a8cb497749dc09e4586c324f302`
  - SQL-Schema (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `b4ea330490da945616103371dd71fbb9d64c03e557d3dfbae834213af3c11714`
- **DUPLICATE-CHECK:**
  - `docs/rechte_prompt.md` listet die Legacy→Kanonisch-Mappings; Phase 1a setzt diese Mappings nun technisch in SQL um (Merge + Deaktivierung) → **DONE**.
- **DATEIEN (max. 3):**
  1) `sql/19_migration_rechte_legacy_merge.sql`
  2) `sql/zeiterfassung_aktuell.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - SQL-Migration 19 ergänzt: Legacy-Rechte werden auf kanonische Rechte gemappt (Rollenrechte + Mitarbeiter-Overrides), Legacy-Zuweisungen entfernt, Legacy-Rechte deaktiviert (`recht.aktiv=0`).
  - SQL-Snapshot (`sql/zeiterfassung_aktuell.sql`) bereinigt: Legacy-Rechte als inaktiv markiert, Rollen-Zuweisungen konsolidiert (kein Doppelbestand in `rolle_hat_recht`).
- **AKZEPTANZ:**
  - Nach Ausführen der Migration 19 sind Legacy-Rechte nicht mehr aktiv und Rollen/Overrides nutzen nur noch die kanonischen Codes.
  - Der SQL-Snapshot bildet den Zielzustand ab.
- **TESTS:**
  - n/a (SQL-Migration). Manuell: Migration 19 ausführen und „Rolle bearbeiten“ öffnen – Legacy-Rechte müssen (je nach UI-Filter) mindestens deaktiviert sein.
- **NEXT:**
  - Phase 1b: UI so anpassen, dass nur aktive Rechte (`recht.aktiv=1`) angezeigt werden (Legacy nicht mehr sichtbar).

## P-2026-01-11-10
- ZIP: `P-2026-01-11-10_rechte-phase1b-ui-nur-aktive.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `090301_gesammt.zip` = `e593607aa925318ffaf15a9f09d3719a0557a28285a06350ada9ebebec063c81`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `f25f753b0d9699325a94216be7f4abccda9158735d6c43f93f2e1f5d5b1ee26f`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `4b58d3ffc4c7a961a2b51425df9375d64fb58a8cb497749dc09e4586c324f302`
  - SQL-Snapshot (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `836fb7881663cd7157be357233c535a130d2ed7bb0974d6fd90101ee74fa68ea`
- **DUPLICATE-CHECK:**
  - SNAPSHOT geprüft: „Sidequest Rechte Phase 1b (UI nur aktive Rechte)“ war als **Nächster Schritt** offen und im LOG noch **nicht DONE**.
  - Keine zweite Implementierung: Es wird nur die UI-Anzeige auf `aktiv=1` umgestellt (keine neuen Migrations/Refactors).
- **DATEIEN (max. 3):**
  1) `controller/RollenAdminController.php`
  2) `controller/MitarbeiterAdminController.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Rollenverwaltung („Rolle bearbeiten“) und Mitarbeiterverwaltung (Rechte-Overrides) laden die Rechte-Liste jetzt nur noch **aktiv** (`recht.aktiv=1`).
  - Damit sind die in Phase 1a deaktivierten Legacy-Rechte nicht mehr sichtbar und erzeugen keine doppelten UI-Einträge.
- **AKZEPTANZ:**
  - In „Rolle bearbeiten“ und „Mitarbeiter bearbeiten“ werden keine inaktiven/Legacy-Rechte mehr angezeigt (keine doppelten Rechte-Zeilen).
- **TESTS:**
  - Manuell:
    1) Backend → Rollen → Rolle bearbeiten: Rechte-Liste darf keine „(inaktiv)“-Einträge enthalten.
    2) Backend → Mitarbeiter → Mitarbeiter bearbeiten: Tabelle „Rechte (Override)“ darf keine „(inaktiv)“-Einträge enthalten.
- **NEXT:**
  - Phase 1c (Doku): `docs/rechte_prompt.md` ergänzen, dass Phase 1b (UI-Filter aktiv=1) umgesetzt ist.
  - Phase 2 (DB harden): Unique-Constraint auf `recht.code` + defensive Seeds/Migrationen (keine neuen Duplikate mehr möglich).

## P-2026-01-11-11
- ZIP: `P-2026-01-11-11_rechte-phase1c-doku-status.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `092723_gesammt.zip` = `3a988a556577ff6aab03a3bbaa3b2fc58554cbe3d8e725c64591c83b68ae332b`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `d20d6decd03758d52244e8f7b2b4dd6413ef41931fbe09791bf179b3023a9633`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `4b58d3ffc4c7a961a2b51425df9375d64fb58a8cb497749dc09e4586c324f302`
  - SQL-Snapshot (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `836fb7881663cd7157be357233c535a130d2ed7bb0974d6fd90101ee74fa68ea`
- **DUPLICATE-CHECK:**
  - In P-2026-01-11-10 war „Phase 1c (Doku)“ als NEXT offen → `docs/rechte_prompt.md` war noch nicht aktualisiert → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `docs/rechte_prompt.md`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `docs/rechte_prompt.md` Roadmap aktualisiert: Phase 1a (SQL Merge) + Phase 1b (UI Filter aktiv=1) als **DONE** dokumentiert.
  - Hinweis ergänzt: Unique-Constraint auf `recht.code` ist im Schema bereits vorhanden; optional später Snapshot-SQL index/constraint-sichtbar machen.
- **AKZEPTANZ:**
  - In `docs/rechte_prompt.md` ist klar ersichtlich, dass Phase 1 abgeschlossen ist (SQL + UI) und was optional als nächstes kommt.
- **TESTS:**
  - n/a (nur Doku)
- **NEXT:**
  - Phase 3 (UI): Rechte in der Rollen-UI gruppiert darstellen (Stammdaten/Reports/Urlaub/Terminal/System) für bessere Übersicht.

## P-2026-01-11-12
- ZIP: `P-2026-01-11-12_rechte-ui-gruppierung-rollen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `093313_gesammt.zip` = `02d435d12c3645b1bc6a6877542a1cb734a8a6f3e6e5ff51ef7d1729f639a2c6`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `65fa6aa2c04a1522ed452807d0ea009dc693efceac8d2970284562cab06b63f2`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `d2ecc6f15bb87fd2cbe47836fcdb16ba0c5cccf48fe55017c0f4923314a9578d`
  - SQL-Snapshot (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `836fb7881663cd7157be357233c535a130d2ed7bb0974d6fd90101ee74fa68ea`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprüft: Phase 3 war als **NEXT** offen (P-2026-01-11-11). In `views/rolle/formular.php` gab es noch keine UI-Gruppierung der Rechte → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/rolle/formular.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Rollenverwaltung („Rolle bearbeiten“): Rechte-Liste wird jetzt in UI-Gruppen dargestellt (Details/Summary), damit man schnell Stammdaten/Zeit/Urlaub/Reports/Terminal/System auseinanderhalten kann.
- **AKZEPTANZ:**
  - In „Rolle bearbeiten“ sind Rechte nicht mehr als eine endlose Liste, sondern klar nach Themen gruppiert (mit aufklappbaren Blöcken).
- **TESTS:**
  - Manuell: Backend → Rollen → „Rolle bearbeiten“ öffnen → Rechte erscheinen in Gruppen (Details-Blöcke) und lassen sich weiterhin normal anhaken/speichern.
- **NEXT:**
  - Phase 3b: gleiche Gruppierung auch im Mitarbeiter-Formular bei „Rechte-Overrides“ umsetzen (damit dort ebenfalls Übersicht entsteht).

## P-2026-01-11-13
- ZIP: `P-2026-01-11-13_rechte-phase2-db-harden.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `095224_gesammt.zip` = `5256df60ec854f671155e4c88901b6a094176868e048b362594dd246cdb47985`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `032157054658dc57e589193b50def1089f76d11114cb8848cca42c1fc4cc7fbb`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `d2ecc6f15bb87fd2cbe47836fcdb16ba0c5cccf48fe55017c0f4923314a9578d`
  - SQL-Snapshot (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `836fb7881663cd7157be357233c535a130d2ed7bb0974d6fd90101ee74fa68ea`
- **DUPLICATE-CHECK:**
  - `docs/rechte_prompt.md` sagt: Unique-Constraint auf `recht.code` ist grundsätzlich im Schema vorhanden. In Alt-Installationen kann dieser Index aber fehlen oder beim Nachziehen scheitern (wenn historische Dubletten vorhanden sind) → **nicht DONE**, defensiver Reparatur-Schritt sinnvoll.
  - Snapshot geprüft: `sql/zeiterfassung_aktuell.sql` hatte den Unique-Index, aber keinen expliziten Index auf `recht.aktiv` im Index-Block → Optimierung/Angleichung sinnvoll.
- **DATEIEN (max. 3):**
  1) `sql/20_migration_recht_code_unique.sql`
  2) `sql/zeiterfassung_aktuell.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Migration 20 ergänzt: bereinigt eventuelle Dubletten in `recht` anhand von `code` (Referenzen in `rolle_hat_recht` / `mitarbeiter_hat_recht` werden konsolidiert) und stellt Unique-Index `uniq_recht_code` sicher.
  - Zusätzlich: Index `idx_recht_aktiv` wird (falls fehlend) angelegt, damit Listen/Filter (`aktiv=1`) performant bleiben.
  - SQL-Snapshot (`sql/zeiterfassung_aktuell.sql`) angepasst: `idx_recht_aktiv` ist nun im Index-Block sichtbar.
- **AKZEPTANZ:**
  - Nach Ausführen der Migration 20 kann es in `recht` keine doppelten `code`-Werte mehr geben und es existiert ein Index auf `aktiv`.
- **TESTS:**
  - Manuell:
    1) Migration 20 in phpMyAdmin ausführen.
    2) Prüfen: `SHOW INDEX FROM recht;` → `uniq_recht_code` + `idx_recht_aktiv` vorhanden.
- **NEXT:**
  - Phase 3b: Rechte-Gruppierung im Mitarbeiter-Formular bei „Rechte-Overrides“ umsetzen (analog Rollen-UI), damit dort ebenfalls Übersicht entsteht.


## P-2026-01-11-14
- ZIP: `P-2026-01-11-14_rechte-ui-gruppierung-mitarbeiter-overrides.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `104535_gesammt.zip` = `2dd566e53d8a06a69f6f9fb0fa726c4d78b3b43df4d3809d9302da1c0b1b4e3a`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `9e21bf211e838f0ff8fdbca636f0d21cfede88bbe735964afc035c477168b528`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `d2ecc6f15bb87fd2cbe47836fcdb16ba0c5cccf48fe55017c0f4923314a9578d`
- **DUPLICATE-CHECK:**
  - In P-2026-01-11-13 war „Phase 3b: Rechte-Gruppierung im Mitarbeiter-Formular (Overrides)“ als **NEXT** offen → Overrides-Ansicht war noch eine ungruppierte Tabelle → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/mitarbeiter/formular.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiterverwaltung („Mitarbeiter bearbeiten“): Rechte-Overrides werden jetzt analog zur Rollen-UI in UI-Gruppen (Details/Summary) dargestellt.
  - Keine Logikänderung: Override-Speichern/Vererbung/Effektiv-Status bleibt unverändert, nur Darstellung gruppiert.
- **AKZEPTANZ:**
  - Backend → Mitarbeiter → Mitarbeiter bearbeiten: Rechte-Overrides erscheinen in Gruppen (Details-Blöcke) und Overrides lassen sich weiterhin setzen/speichern.
- **TESTS:**
  - Manuell:
    1) Backend → Mitarbeiter → Mitarbeiter bearbeiten öffnen.
    2) In einer Gruppe ein Override setzen (erlauben/entziehen) und Speichern.
    3) Seite neu laden: Override bleibt gesetzt; Effektiv-Text bleibt korrekt.
- **NEXT:**
  - Optional: `docs/rechte_prompt.md` Roadmap Phase 3b als DONE markieren.
  - Danach zurück zur Haupt-Roadmap: Offline-Queue Feldtest (online → offline stempeln → online → Replay/Report prüfen).



## P-2026-01-11-15
- ZIP: `P-2026-01-11-15_rechte-roadmap-finalisieren.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `105212_gesammt.zip` = `6b83100160875902e83baaa934fdc79ccbaba5e2c3a2c66993b9920dd7d1ece5`
  - Master-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus Projekt-ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `4682fd6cf719458f5da40d535a07571e9df1dc8eb58dccff44162124b0ff5cef`
  - Rechte-Prompt (aus Projekt-ZIP, Stand vor Patch): `docs/rechte_prompt.md` = `d2ecc6f15bb87fdcbe47836fcdb16ba0c5cccf48fe55017c0f4923314a9578d`
  - SQL-Snapshot (aus Projekt-ZIP): `sql/zeiterfassung_aktuell.sql` = `f440d97ef28909c214a906587453612deee15e53a94e31aeebcc69b6e227ed01`
- **DUPLICATE-CHECK:**
  - In P-2026-01-11-14 war als NEXT offen: „Optional: `docs/rechte_prompt.md` Roadmap Phase 3b als DONE markieren.“ → in `docs/rechte_prompt.md` war Phase 3 noch **nicht** als DONE dokumentiert → **nicht DONE**, jetzt nachgezogen.
- **DATEIEN (max. 3):**
  1) `docs/rechte_prompt.md`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `docs/rechte_prompt.md` Roadmap aktualisiert:
    - Phase 2 als DONE dokumentiert (Migration 20: `recht.code` unique + Index `idx_recht_aktiv`).
    - Phase 3a/3b als DONE dokumentiert (Rechte-Gruppierung Rollen-UI + Mitarbeiter-Overrides-UI).
  - `docs/DEV_PROMPT_HISTORY.md` SNAPSHOT „Nächster Schritt“ auf Haupt-Roadmap zurückgesetzt: Offline-Queue Feldtest End-to-End.
- **AKZEPTANZ:**
  - In `docs/rechte_prompt.md` → Kapitel 5 sind Phase 2 sowie Phase 3a/3b klar als DONE markiert.
- **TESTS:**
  - Manuell:
    1) `docs/rechte_prompt.md` öffnen → Kapitel 5 prüfen.
    2) `docs/DEV_PROMPT_HISTORY.md` öffnen → SNAPSHOT „Nächster Schritt“ = Offline-Queue Feldtest.
- **NEXT:**
  - Offline-Queue Feldtest End-to-End (online → offline stempeln → online → Replay-Report prüfen) und ggf. gezielter Bugfix.
### Aktueller Status (2026-01-11)

- **Zuletzt erledigt:** P-2026-01-11-15 – Rechte-Doku finalisiert (`docs/rechte_prompt.md` Roadmap Phase 2 + Phase 3a/3b als DONE).
- **Nächster geplanter Schritt:** Offline-Queue Feldtest End-to-End (online → offline stempeln → online → Replay-Report prüfen) und ggf. gezielter Bugfix.

## P-2026-01-11-16
- ZIP: `P-2026-01-11-16_mitarbeiter-passwort-position.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `133702_gesammt.zip` = `bf324ce03e366f3d68c16046e4088f80bde06d8b0b694db01c82b35a2eaef76c`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload): `DEV_PROMPT_HISTORY.md` = `c6d097f30d1b5422444f82d578907f6dae9ce9320f5955949e299f4c41798b76`
  - SQL-Snapshot (Upload): `zeiterfassung_aktuell.sql` = `1273069981d1c5fbcb5978d0105dd37385874a5b52fba445d9951c37e5277552`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: „Passwort-Feldset zwischen Login/Rollen“ war noch nicht als DONE dokumentiert → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/mitarbeiter/formular.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Mitarbeiter anlegen/bearbeiten – Passwort-Feldset im Formular nach oben verschoben (zwischen „Login / Zugang“ und „Rollen (Berechtigungen)“).
  - Analyse (noch kein Patch): Mitarbeiter→Abteilung-Zuweisung ist aktuell nicht implementiert; im Formular existiert nur „Abteilungs-Rollen (Phase 1)“ (Role-Scope). Echte Zuweisung waere ueber `mitarbeiter_hat_abteilung` (M:N).
- **AKZEPTANZ:**
  - „Mitarbeiter anlegen“ und „Mitarbeiter bearbeiten“ zeigen den Passwort-Block direkt nach „Login / Zugang“.
- **TESTS:**
  - Manuell:
    1) Backend → Mitarbeiter → Neuen Mitarbeiter anlegen: Passwort-Block-Position pruefen.
    2) Backend → Mitarbeiter → Mitarbeiter bearbeiten: Passwort-Block-Position pruefen.
- **NEXT:**
  - T-090: Mitarbeiter-Abteilungszuweisung UI/Save implementieren (M:N `mitarbeiter_hat_abteilung`, optional „Stammabteilung“).
  - Haupt-Roadmap: Offline-Queue Feldtest End-to-End (online → offline stempeln → online → Replay-Report pruefen) und ggf. gezielter Bugfix.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-16 – Mitarbeiter-Formular: Passwort-Block nach oben (unter Login) verschoben.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.


## P-2026-01-11-17
- ZIP: `P-2026-01-11-17_mitarbeiter-stammabteilung.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `135840_gesammt.zip` = `d87730141f2c11194f4604cc7f30960e53fe095af6b15d273f66dbf01487fbb2`
  - Master-Prompt (Upload): `master_prompt_zeiterfassung_v9.md` = `e267f38670338dcffaeac907922f403abb443ce087d0db60bddb8e31d1ce3781`
  - DEV_PROMPT_HISTORY (Upload): `DEV_PROMPT_HISTORY.md` = `c6d097f30d1b5422444f82d578907f6dae9ce9320f5955949e299f4c41798b76`
  - SQL-Snapshot (Upload): `zeiterfassung_aktuell.sql` = `1273069981d1c5fbcb5978d0105dd37385874a5b52fba445d9951c37e5277552`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: T-090 (Mitarbeiter-Abteilungszuweisung) war noch offen → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Mitarbeiter anlegen/bearbeiten – neue Auswahl **Stammabteilung** (Dropdown aus aktiven Abteilungen) inkl. Save/Load ueber `mitarbeiter_hat_abteilung.ist_stammabteilung`.
  - Fix: Abteilungs-Rollen (Phase 1) – Abteilungen-Liste + gespeicherte Scopes werden im Formular wieder korrekt geladen (Controller: `alleAbteilungen` + `rollenScopesAbteilung`), zudem `renderFormMitFehler` nutzt jetzt sauber die Mitarbeiter-ID.
- **AKZEPTANZ:**
  - In Mitarbeiter anlegen/bearbeiten ist das Dropdown „Stammabteilung“ sichtbar und nach Speichern bei erneutem Oeffnen vorbelegt.
  - In „Abteilungs-Rollen (Phase 1)“ ist das Abteilungs-Dropdown befuellt (nicht leer) und vorhandene Eintraege werden gelistet.
- **TESTS:**
  - Manuell:
    1) Backend → Mitarbeiter → Bearbeiten: Stammabteilung auswaehlen → speichern → Seite neu oeffnen → Auswahl bleibt.
    2) DB-Check: `SELECT * FROM mitarbeiter_hat_abteilung WHERE mitarbeiter_id = X;` → eine Zeile mit `ist_stammabteilung=1` (bei Auswahl).
    3) Backend → Mitarbeiter → Bearbeiten: Bereich „Abteilungs-Rollen (Phase 1)“ → Abteilung-Dropdown hat Eintraege.
- **NEXT:**
  - Optional: T-091 (Mehrfach-Abteilungszuweisung im UI) oder Report-Filter/Default-Abteilung auf Basis der Stammabteilung.
  - Haupt-Roadmap: Offline-Queue Feldtest End-to-End (online → offline stempeln → online → Replay-Report pruefen) und ggf. gezielter Bugfix.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-17 – Mitarbeiter: Stammabteilung Dropdown + Save/Load; Abteilungs-Rollen Dropdowns wieder befuellt.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.


## P-2026-01-11-18
- ZIP: `P-2026-01-11-18_mitarbeiter-abteilungen-multi.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `142947_gesammt.zip` = `24f01afc597e7f3aef635adbcdb1b8308600865ff0990bc093a17d26edc3e735`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `ea2b6391a7b4dcb475cb84c6ea7531b4391fd38b63b79791a657a34fbcf14c7b`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `1273069981d1c5fbcb5978d0105dd37385874a5b52fba445d9951c37e5277552`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: T-091 (Mehrfach-Abteilungszuweisung im UI) war offen → wird hier umgesetzt.
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter anlegen/bearbeiten: neue Checkbox-Liste „Abteilungen (Mitgliedschaft)“ (0..n, optional) im Stammdaten-Block.
  - Save/Load: Mitgliedschaften werden in `mitarbeiter_hat_abteilung` gespeichert; optional kann eine Stammabteilung gesetzt werden (`ist_stammabteilung=1`), Stammabteilung wird beim Speichern automatisch als Mitgliedschaft uebernommen.
- **AKZEPTANZ:**
  - In Mitarbeiter anlegen/bearbeiten koennen 0..n Abteilungen angehakt werden; nach Speichern sind die Haken beim erneuten Oeffnen identisch.
  - Wenn eine Stammabteilung gesetzt ist, ist diese Abteilung auch als Mitgliedschaft gespeichert.
- **TESTS:**
  - Manuell:
    1) Mitarbeiter bearbeiten → 2 Abteilungen anhaken → speichern → erneut oeffnen → beide Haken gesetzt.
    2) Stammabteilung setzen (eine der Abteilungen) → speichern → DB: genau eine Zeile hat `ist_stammabteilung=1`.
    3) Alle Haken entfernen + Stammabteilung „keine“ → speichern → DB: keine Zeilen in `mitarbeiter_hat_abteilung` fuer den Mitarbeiter.
- **NEXT:**
  - UI-Feinschliff: Stammabteilung ggf. als Radio in der Abteilungs-Liste (statt separatem Dropdown) oder Auto-Sync (Dropdown-Auswahl setzt Checkbox).
  - Haupt-Roadmap: Offline-Queue Feldtest End-to-End und ggf. gezielter Bugfix.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-18 – Mitarbeiter: Abteilungen (Mitgliedschaften) als Multi-Select (0..n) inkl. Save/Load.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.


## P-2026-01-11-19
- ZIP: `P-2026-01-11-19_mitarbeiter-stammabteilung-radio.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `145040_gesammt.zip` = `b7684b360c7c58c38ffaa46c9a44bcecdbc5266281e325580924df90d1bc9fba`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `aaba814aee67bedb87fc9bc7dfb7852b44ae4256a553bd80ffd60ce929baada4`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `1273069981d1c5fbcb5978d0105dd37385874a5b52fba445d9951c37e5277552`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: UI-Feinschliff „Stammabteilung als Radio/Auto-Sync“ war als NEXT offen → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/mitarbeiter/formular.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter anlegen/bearbeiten: Stammabteilung wird jetzt als Radio „Stamm“ direkt in der Abteilungs-Liste gesetzt (statt separatem Dropdown).
  - Auto-Sync: Stammwahl setzt die Mitgliedschaft automatisch; Entfernen der Mitgliedschaft setzt die Stammabteilung automatisch auf „keine“.
- **AKZEPTANZ:**
  - Beim Setzen von „Stamm“ ist die Abteilung als Mitgliedschaft aktiv (Checkbox wird automatisch gesetzt).
  - Wenn eine Abteilungs-Mitgliedschaft entfernt wird, die Stammabteilung war, wird automatisch „keine Stammabteilung“ gesetzt.
- **TESTS:**
  - Manuell:
    1) Mitarbeiter bearbeiten → Abteilung A an/aus → „Stamm“ wird korrekt enabled/disabled.
    2) „Stamm“ bei Abteilung B setzen → Checkbox B wird automatisch gesetzt.
    3) Checkbox der Stammabteilung entfernen → „keine Stammabteilung“ ist selektiert.
- **NEXT:**
  - Haupt-Roadmap Feldtest Offline-Queue (End-to-End) und ggf. gezielter Bugfix (1 Thema/1 Patch).

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-19 – Mitarbeiter: Stammabteilung als Radio in Abteilungs-Liste (Auto-Sync).
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.


## P-2026-01-11-20
- ZIP: `P-2026-01-11-20_mitarbeiter-abteilungen-table-filter.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `150505_gesammt.zip` = `050c72763ef2500e46f220cc3e24cb39eace3b3f2ca73f7f589043fb1ebe2af5`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `6bfe3c417d330e46d8c4154df793b91bf213e133147317673419a8a0cfd0eee4`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `1273069981d1c5fbcb5978d0105dd37385874a5b52fba445d9951c37e5277552`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Abteilungen-UI war als „unschön bei vielen Abteilungen“ neu gemeldet → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/mitarbeiter/formular.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mitarbeiter anlegen/bearbeiten: Abteilungen werden jetzt als **scrollbare Tabelle** dargestellt (kein Zeilen-Wrap mit Checkbox/Radio pro Abteilung).
  - Suchfilter: Abteilungen koennen per Suchfeld gefiltert werden (praktisch bei 100+ Abteilungen).
  - Stammabteilung bleibt optional („keine“), Auto-Sync (Stamm setzt Mitgliedschaft; Mitgliedschaft aus → Stamm wird auf „keine“ gesetzt) bleibt erhalten.
- **AKZEPTANZ:**
  - Bei vielen Abteilungen bleibt das Formular nutzbar (scrollbarer Block, keine unleserliche Mehrzeilen-Checkbox-Wolke).
  - Filter reduziert die sichtbaren Abteilungen sofort beim Tippen.
  - Auto-Sync-Regeln funktionieren weiterhin wie in P-2026-01-11-19.
- **TESTS:**
  - Manuell:
    1) Mitarbeiter bearbeiten → viele Abteilungen: Liste bleibt innerhalb des Scroll-Blocks.
    2) Filter tippen → nur passende Abteilungen sichtbar.
    3) Stamm setzen → Checkbox wird automatisch gesetzt.
    4) Checkbox der Stammabteilung entfernen → „keine Stammabteilung“ ist selektiert.
- **NEXT:**
  - Haupt-Roadmap Feldtest Offline-Queue (End-to-End) und ggf. gezielter Bugfix (1 Thema/1 Patch).

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-20 – Mitarbeiter: Abteilungen-UI skaliert (Tabelle + Suchfilter).
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.



## P-2026-01-11-21
- ZIP: `P-2026-01-11-21_terminal-uebersicht-unter-abbrechen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `165217_gesammt.zip` = `78705aa8ed76369350c1e91d6628d7ac10e34880a14ce242d57ac87b650689c9`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `9ba8e55a86a68963c48120412a87ee9095e7f6b835aaaef8426fbc56b1ca3832`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Startscreen „Übersicht (heute)“ unter „Abbrechen“ + Statusbox-Optik war noch nicht umgesetzt → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `public/css/terminal.css`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: „Übersicht (heute)“ wurde **unter** den großen „Abbrechen“-Button verschoben.
  - Der Übersichts-Block nutzt jetzt das **Statusbox-Layout** (volle Breite, wie Systemstatus auf-/zuklappbar).
- **AKZEPTANZ:**
  - Auf `terminal.php?aktion=start` ist „Übersicht (heute)“ direkt unter dem „Abbrechen“-Button und lässt sich über die ganze Breite auf-/zuklappen.
- **TESTS:**
  - Manuell:
    1) Terminal → als anwesender Mitarbeiter einloggen.
    2) Prüfen: „Abbrechen“ bleibt groß; „Übersicht (heute)“ liegt direkt darunter und ist klickbar.
- **NEXT:**
  - T-092: Terminal Startscreen – Monatsstunden-Status in „Übersicht“ ergänzen (Micro-Patches: Ist → Soll bis jetzt → Rest + Ampel).

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-21 – Terminal: Übersicht (heute) unter Abbrechen + Statusbox.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-11-22
- ZIP: `P-2026-01-11-22_terminal-monatsstatus-iststunden.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `170705_gesammt.zip` = `2e3c17a93aa3abcc71580129cbde5d55cb15b30fc465d459a5b18a50017f00a6`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `2567bd50e33aa1ab42916ecec89032f140e46d916c2c8397abf25fb8f0cbc7d3`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: T-092 (Monatsstunden-Status im Terminal) war offen, Monatsstatus-Block existierte noch nicht → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: In „Übersicht (heute)“ wird ein „Monatsstatus“-Block angezeigt.
  - Der Block zeigt aktuell die **Arbeitszeit diesen Monat (bisher)** (IST) als Stundenwert (Summe aus ReportService-Tageswerten bis heute).
- **AKZEPTANZ:**
  - Nach Terminal-Login (Haupt-DB online) steht in „Übersicht (heute)“ im Monatsstatus: „Arbeitszeit diesen Monat (bisher): X,XX h“.
- **TESTS:**
  - Manuell:
    1) Terminal → als anwesender Mitarbeiter einloggen.
    2) „Übersicht (heute)“ aufklappen → Monatsstatus muss sichtbar sein.
- **NEXT:**
  - T-092 (Micro-Patch 2): „Soll bis jetzt (Monat)“ ergänzen.
  - T-092 (Micro-Patch 3): „Rest-Soll (Monat)“ + Delta/Ampel (grün/rot) ergänzen.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-22 – Terminal: Monatsstatus zeigt IST (bisher).
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-11-23
- ZIP: `P-2026-01-11-23_terminal-monatsstatus-soll-bis-heute.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `172712_gesammt.zip` = `43f15e41df4122f528288c97a78114e2873979cde5843a884282fd7c3ab92d0c`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `5670f8b430efad4667aa523f88ad3b3605a8f1146d6d8ea6bf697b74e0f6265e`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: T-092 Micro-Patch 2 „Soll bis jetzt“ war offen (P-2026-01-11-22 hatte nur IST) → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: Monatsstatus zeigt zusätzlich **Soll-Stunden bis heute (Monat)**.
  - Berechnung: Wochenarbeitszeit / 5, summiert über Mo–Fr vom 1. bis heute (Feiertage/Urlaub reduzieren das Soll nicht – analog ReportService-Fallback).
- **AKZEPTANZ:**
  - Nach Terminal-Login (Haupt-DB online) steht in „Übersicht (heute)“ im Monatsstatus:
    - „Soll-Stunden bis heute (Monat): X,XX h“
    - „Ist-Stunden bis heute (Monat): Y,YY h“
- **TESTS:**
  - Manuell:
    1) Terminal → als anwesender Mitarbeiter einloggen.
    2) „Übersicht (heute)“ aufklappen → beide Zeilen sind sichtbar und Werte plausibel.
- **NEXT:**
  - T-092 (Micro-Patch 3): „Rest-Soll (Monat)“ ergänzen (Soll Monat minus Ist) + Delta/Ampel (grün/rot).

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-23 – Terminal: Monatsstatus zeigt Soll bis heute + IST.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-11-24
- ZIP: `P-2026-01-11-24_terminal-monatsstatus-rest-soll.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `174736_gesammt.zip` = `173783e25c3eda69e3e2040870305184eb94d5132593f6ade59b036c720c3b99`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `6211d83ec5ae89b1b547d843c6abcb515958986fb24b53a992b4f2d89581ebe9`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: T-092 Micro-Patch 3 „Rest-Soll“ war offen → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: Monatsstatus zeigt zusätzlich **Rest-Soll bis Monatsende**.
  - Berechnung: Soll Monat (Mo–Fr * Tages-Soll) minus IST bis heute, negative Werte werden als 0.00 dargestellt ("noch zu arbeiten").
- **AKZEPTANZ:**
  - Nach Terminal-Login (Haupt-DB online) steht in „Übersicht (heute)“ im Monatsstatus zusätzlich:
    - „Noch zu arbeiten bis Monatsende: X,XX h“
- **TESTS:**
  - Manuell:
    1) Terminal → als anwesender Mitarbeiter einloggen.
    2) „Übersicht (heute)“ aufklappen → Rest-Soll-Zeile ist sichtbar und plausibel.
- **NEXT:**
  - T-092 (Micro-Patch 4): Delta (IST minus SOLL bis heute) + Ampel (grün/rot) + Text „Überstunden / Minus“ ergänzen.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-24 – Terminal: Monatsstatus zeigt Rest-Soll bis Monatsende.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-11-25
- ZIP: `P-2026-01-11-25_terminal-monatsstatus-saldo-ampel.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `180139_gesammt.zip` = `24695857852b899d33817a482912ff84af9adf3be0c44775af142b7459db9e70`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `d8e630ea7484041a371e92d35c77e4953d6be182cd79c87cfa4affcd9f304991`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: T-092 Micro-Patch 4 „Delta/Ampel“ war offen → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: Monatsstatus zeigt **Saldo (IST minus SOLL bis heute)** mit Plus/Minus und Ampel-Farbe (grün/rot) inkl. Text „Überstunden/Minusstunden“.
- **AKZEPTANZ:**
  - Nach Terminal-Login (Haupt-DB online) steht in „Übersicht (heute)“ im Monatsstatus zusätzlich:
    - „Saldo (bis heute): +X,XX h (Überstunden)“ **grün** oder „Saldo (bis heute): -X,XX h (Minusstunden)“ **rot**.
- **TESTS:**
  - Manuell:
    1) Terminal → als anwesender Mitarbeiter einloggen.
    2) „Übersicht (heute)“ aufklappen → Saldo-Zeile sichtbar; Farbe + Vorzeichen passen.
- **NEXT:**
  - T-069 (Teil 1a): Feldtest Terminal-Login/Startscreen (anwesend/nicht anwesend) und Auffälligkeiten sammeln.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-25 – Terminal: Monatsstatus zeigt Saldo/Ampel.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-11-26
- ZIP: `P-2026-01-11-26_monatsuebersicht-mitarbeiterwechsel-internal-error.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `181754_gesammt.zip` = `4515c54f3a257ff1eb6f101924a18a1b52d3b34a683858a0d71184743015ef89`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `2af97a7ae14cd3b1845a222783b9d6d59659f2a2ae04872ce28ad98ee684bf3c`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Bug „Monatsübersicht → Mitarbeiter wechseln (Chef) → interner Fehler“ war **nicht** als DONE dokumentiert.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsübersicht (Backend): View normalisiert `arbeitsbloecke` defensiv (nur Array-Elemente), um TypeErrors/„Interner Fehler“ beim Mitarbeiter-Wechsel zu verhindern.
- **AKZEPTANZ:**
  - Als Chef/Personalbüro kann man in der Monatsübersicht einen anderen Mitarbeiter auswählen, ohne dass ein „Interner Fehler“ erscheint.
- **TESTS:**
  - Manuell:
    1) Backend-Login als Chef.
    2) Monatsübersicht öffnen.
    3) Anderen Mitarbeiter auswählen → Seite lädt ohne Fehler.
- **NEXT:**
  - Falls der Fehler weiterhin auftritt: konkreten PHP-Fehler aus `system_log`/Server-Log posten → dann zielgenau an der Datenquelle (ReportService/Zeitbuchungen) nachziehen.

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-26 – Monatsübersicht: Mitarbeiterwechsel crasht nicht mehr.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-11-27
- ZIP: `P-2026-01-11-27_monatsreport-reportservice-return-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `195907_gesammt.zip` = `9a42b5d5e6941c178c7014e4268f0ea13e31ab1ae4f6429ea857ed9484f523d9`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `281c5a54cb3a6eafca5ec4d56fb5f1f757614c731333fe97c3ef802db5f28b7a`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `abc41004bdfbca6744fa86f8e106a0ca4775d3f870f772ff041c7161a6ea6fcc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Patch 26 fixte nur die View (Arbeitsblock-Normalisierung). Der TypeError "Return value must be of type array, none returned" war **nicht** als DONE dokumentiert.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport/Backend: `ReportService::holeMonatsdatenFuerMitarbeiter()` liefert jetzt **immer** ein Array zurueck, auch wenn `tageswerte_mitarbeiter` fuer den Monat leer ist (fehlende Klammer geschlossen).
- **AKZEPTANZ:**
  - Als Chef kann man im Monatsreport einen neu angelegten Mitarbeiter auswaehlen (ohne Tageswerte-Datensaetze) ohne „Interner Fehler“.
- **TESTS:**
  - Manuell:
    1) Backend-Login als Chef.
    2) Monatsübersicht öffnen.
    3) Neuen Mitarbeiter (ohne Tageswerte im Monat) auswählen → Seite lädt.
- **NEXT:**
  - Feldtest: Monatsreport fuer mehrere neue Mitarbeiter (mit/ohne Zeitbuchungen) + `system_log` kontrollieren.

## P-2026-01-11-28
- ZIP: `P-2026-01-11-28_monatsreport-mikrobuchungen-ignorieren.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `201634_gesammt.zip` = `ae03f77df20dbe7057a5175310986c3871d4a279a46acd74f5438aad7f6c02c7`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `bf615d80d08c4b7c4ec095e3915950d1e161676d80f58c0733a2c6e8428a4c3a`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `abc41004bdfbca6744fa86f8e106a0ca4775d3f870f772ff041c7161a6ea6fcc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Es gibt noch keinen Patch, der Mikro-Arbeitszeiten im Monatsreport bewusst ausfiltert (0,01h durch Doppel-Stempel).
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport: Mikro-Arbeitszeiten < 0,05h (3 Minuten) werden vor dem Monatsraster neutralisiert (Arbeitszeit=0,00; Kommen/Gehen leer), damit sie weder in Summen noch in Sonderlogiken (Betriebsferien/Feiertage) als „gearbeitet“ zaehlen.
- **AKZEPTANZ:**
  - Eine versehentliche Mini-Buchung (Kommen/Gehen fast gleich) erzeugt im Monatsreport keine 0,01h mehr und beeinflusst keine Monats-Summen.
- **TESTS:**
  - Manuell:
    1) Mitarbeiter mit Mini-Buchung (z. B. 20–60 Sekunden) im Monat öffnen.
    2) Monatsreport (PDF/HTML) zeigt an dem Tag 0,00 bei IST und keine Kommen/Gehen-Zeiten.
- **NEXT:**
  - Optional: Grenzwert konfigurierbar machen (Config-Key) + Hinweis/Markierung im Tages-Detail (nur wenn gewuenscht).

### Aktueller Status (2026-01-11)
- **Zuletzt erledigt:** P-2026-01-11-28 – Monatsreport: Mikro-Buchungen werden im Report ignoriert.
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## P-2026-01-12-01
- ZIP: `P-2026-01-12-01_monatsuebersicht-mikrobuchungen-als-strich.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `541564_gesammt.zip` = `bdb634008a71c58bab3d94fd0220da0fe0f4bfa0c325913643775bb29bbb7f72`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `c0f347c1b6c47f85b52d4f14f30e051aa1431e81082459f7e71603ff5b1e8438`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Mikro-Buchungen wurden zwar im Monatsreport neutralisiert (P-2026-01-11-28), aber in der HTML-Monatsuebersicht tauchten sie noch als „0,00“ auf. Keine bestehende Loesung dokumentiert.
- **DATEIEN (max. 3):**
  1) `services/ReportService.php`
  2) `views/report/monatsuebersicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - ReportService markiert neutralisierte Mikro-Buchungen mit `micro_arbeitszeit_ignoriert=1`.
  - Monatsuebersicht (HTML): Wenn Flag gesetzt ist, werden „Ist (gesamt)“ und „Pausen“ als „-” angezeigt (statt 0,00).
- **AKZEPTANZ:**
  - In der HTML-Monatsuebersicht erzeugt eine versehentliche Mini-Buchung keinen sichtbaren 0,00-Eintrag mehr (Ist/Pausen = „-”).
- **TESTS:**
  - Manuell:
    1) Backend-Login als Chef.
    2) Monatsuebersicht oeffnen und Mitarbeiter mit Mikro-Buchung waehlen.
    3) Der betroffene Tag zeigt Ist/Pausen als „-” und keine Zeiten.
- **NEXT:**
  - Feldtest: Monatsreport (HTML+PDF) fuer mehrere Mitarbeiter, speziell Randfaelle mit Mini-Buchungen.



## P-2026-01-12-02
- ZIP: `P-2026-01-12-02_monatsuebersicht-heute-ignorieren-fehlt.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `180809_gesammt.zip` = `f790cb426183e22001c8a96f69c8fc0f85fc42b36e287e5648f341d22c2cdcae`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `81ad08002ff6384d00e45ab69e146adf2a61bc0d08d791dfe9b011417272e5f2`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Monatsuebersicht markiert unvollstaendige Stempel generell als ⚠/FEHLT; es gibt noch keinen Patch, der "heute" explizit ausnimmt. Dashboard/Terminal nutzen bereits eine "heute"-Heuristik (nicht identisch) → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: ⚠/FEHLT-Markierung nur fuer vergangene Tage (`datum < heute`, Europe/Berlin). "Heute" und zukuenftige Tage zeigen bei fehlendem Gegenstempel "-" statt "FEHLT".
- **AKZEPTANZ:**
  - Am aktuellen Tag wird ein fehlender Gehen-/Kommen-Stempel **nicht** als ⚠/FEHLT markiert; ab dem Folgetag erscheint die Markierung.
- **TESTS:**
  - Manuell:
    1) Monatsuebersicht im aktuellen Monat oeffnen.
    2) Heute: nur "Kommen" gestempelt → kein ⚠ und kein "FEHLT" (stattdessen "-" in Ab).
    3) Gestern: nur "Kommen" gestempelt → ⚠ + "FEHLT" sichtbar.
- **NEXT:**
  - Optional: Alternative Heuristik wie Dashboard (z. B. erst warnen, wenn letzter Stempel > X Stunden alt ist), falls gewuenscht.



## P-2026-01-12-03
- ZIP: `P-2026-01-12-03_dashboard-zeitwarnungen-heute-ignorieren.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `183727_gesammt.zip` = `fa3236eb32cf2ce8fedf551d63cbbbecff8730660d3b4e4a0380df1b8231ff30`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `1fd0e443676a194d45e24a67dc9b588b4de0febe853a362493a833308b008be9`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Dashboard-Zeitwarnungen hatten eine 10h-Heuristik fuer „heute“. Gewuenscht ist jetzt: Warnungen erst ab Folgetag (heute komplett ignorieren) → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/DashboardController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard-Zeitwarnungen listen nur noch vergangene Tage (`datum < CURDATE()`); „heute“ wird nicht mehr gewarnt.
- **AKZEPTANZ:**
  - Auf dem Dashboard wird ein offener „Kommen“-Stempel am heutigen Tag nicht als Zeitwarnung angezeigt; am Folgetag erscheint er als Warnung.
- **TESTS:**
  - Manuell:
    1) Heute nur „Kommen“ stempeln.
    2) Dashboard oeffnen: keine Zeitwarnung fuer heute.
    3) Datum auf morgen (oder Testdaten von gestern) → Zeitwarnung erscheint.
- **NEXT:**
  - Feldtest (T-069 Teil 2a): Monatsuebersicht + Dashboard-Zeitwarnungen mit mehreren Mitarbeitern durchklicken (Randfaelle sammeln).


## P-2026-01-12-04
- ZIP: `P-2026-01-12-04_terminal-zeitwarnungen-heute-ignorieren.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `185012_gesammt.zip` = `f425a9e50d2e3cd047659738a5107efb397f1d400cc0da1b5204d019afdce281`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `5c608c83c7f33f00683a00c41ff3cc5def9d872b7c557217e7840784c3cc3fc1`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Terminal warnte "heute" bisher per 10h-Heuristik. Gewuenscht ist jetzt: Warnungen erst ab Folgetag (heute komplett ignorieren) → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Zeitwarnungen listen nur noch vergangene Tage (`datum < CURDATE()`); "heute" wird nicht mehr gewarnt.
- **AKZEPTANZ:**
  - Auf dem Terminal-Startscreen wird ein offener "Kommen"-Stempel am heutigen Tag nicht als Warnung angezeigt; am Folgetag erscheint er als Warnung.
- **TESTS:**
  - Manuell:
    1) Terminal-Login als Mitarbeiter.
    2) Heute nur „Kommen“ stempeln.
    3) Terminal-Startscreen: keine Zeitwarnung fuer heute.
    4) Testdaten von gestern (oder Datum umstellen) → Zeitwarnung erscheint.
- **NEXT:**
  - Feldtest (T-069 Teil 2a): Monatsuebersicht + Dashboard + Terminal Zeitwarnungen mit mehreren Mitarbeitern durchklicken (Randfaelle sammeln).


## P-2026-01-12-05
- ZIP: `P-2026-01-12-05_monatsuebersicht-datum-normalisieren-heute-regel.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP (Upload): `190808_gesammt.zip` = `7cb0a75422133e73586c6cc7bc4457fcedae4364f7f68aa5f9ea978ff7fbb964`
  - Master-Prompt (Projekt): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Projekt): `docs/DEV_PROMPT_HISTORY.md` = `d47cbdf9a35d9d0307285a0316ebdb03d79a888348beacc964e10950918c4016`
  - Rechte-Prompt (Projekt): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Snapshot (Projekt): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Monatsuebersicht ignoriert "heute" bereits, aber Dateivergleich kann fehlschlagen, wenn `datum` als `DD.MM.YYYY` geliefert wird → **kein Duplicate**.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: `datum` wird vor allen Vergleichen nach ISO (`YYYY-MM-DD`) normalisiert.
  - Dadurch gilt "heute" sicher als nicht vergangen, auch wenn `datum` als `DD.MM.YYYY` geliefert wird.
- **AKZEPTANZ:**
  - Ein offener Kommen/Gehen-Block am heutigen Datum wird nicht als ⚠/FEHLT markiert; ab dem Folgetag erscheint die Markierung.
- **TESTS:**
  - Manuell:
    1) Sicherstellen, dass in den gelieferten Tageswerten `datum` als `DD.MM.YYYY` vorkommt.
    2) Monatsuebersicht oeffnen: heutiger Tag darf nicht ⚠/FEHLT anzeigen.
    3) Testdaten von gestern: zeigt weiterhin ⚠/FEHLT.
- **NEXT:**
  - OPTIONAL Micro-Patch: Nacht-Schicht ueber Mitternacht (Kommen gestern, Gehen heute) fuer definierbares Zeitfenster nicht als FEHLT markieren.

### Aktueller Status (2026-01-12)
- **Zuletzt erledigt:** P-2026-01-12-07 – Dashboard+Terminal: Zeitwarnungen Nachtschicht-Grenzfall gefiltert (kein Fehlalarm).
- **Naechster geplanter Schritt:** Feldtest: Nachtschicht + echte Fehlfaelle (vergessenes Gehen) unterscheiden und Beispiele sammeln.

## Patch P-2026-01-12-06: Monatsuebersicht Nachtschicht ueber Mitternacht

### Ziel
- Wenn eine Schicht ueber Mitternacht geht (Kommen am Vortag, Gehen am Folgetag frueh), soll der Vortag nicht als FEHLT/⚠ erscheinen.
- Ebenso soll das fruehe Gehen am Folgetag nicht als FEHLT (Kommen fehlt) markiert werden.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 191902_gesammt.zip — `670234f9c9a735f59cf012d6ac0dd82d16b671f429ecbf6dfd96a9e7a990a7f5`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `d265fc805a43371df5b717159cf7ba8bfd514d657f3ad3f88da794101894efac`

### DUPLICATE-CHECK
- Suche (History/Code): "Nachtschicht", "ueber Mitternacht", "overnight" → keine bestehende Ausnahme in der Monatsuebersicht gefunden.

### DATEIEN (max. 3)
- `views/report/monatsuebersicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) Monatsuebersicht: Precompute `reportOvernightClosingGoByDatum` (fruehes Gehen vor erstem Kommen am Tag) + Zeitfenster `12h`.
2) Tageswarnflag (⚠): Wenn Vortag Kommen aber kein Gehen und Folgetag hat fruehes Gehen innerhalb 12h → kein ⚠.
3) Block-FEHLT: Gleiches gilt pro Block (Kommen/Gehen) → Anzeige zeigt dann `-` statt FEHLT.

### TEST (manuell)
- Fall: 22:00 Kommen (Tag D), 06:00 Gehen (Tag D+1) → Tag D nicht ⚠/FEHLT, Tag D+1 zeigt im ersten Block Kommen `-` und Gehen `06:00` ohne ⚠.
- Normalfall: Kommen 08:00 (D), Gehen fehlt, kein fruehes Gehen (D+1) → weiterhin ⚠/FEHLT.

---

## Patch P-2026-01-12-07: Dashboard+Terminal Zeitwarnungen Nachtschicht-Grenzfall

- **ZIP:** P-2026-01-12-07_dashboard-terminal-zeitwarnungen-nachtschicht-grenzfall.zip

### Ziel
- Zeitwarnungen (Dashboard + Terminal) sollen bei echter Nachtschicht ueber Mitternacht keinen Fehlalarm erzeugen.
- Wir behandeln nur den sicheren Fall: **genau 1 Stempel pro Tag** (nur Kommen am Abend / nur Gehen frueh am Folgetag).

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 194211_gesammt.zip — `a7a4d566a202b6191c5d3376c0fd8ca4d0b1e1375a9cdd2596656fab85a80c9c`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `83b80db42f2a1bc3e8d0714b0496a514e53df044e4ba7e747c2ef6c57cbbbf2a`

### DUPLICATE-CHECK
- Suche (History/Code): "zeitwarnungen", "Nachtschicht", "ueber Mitternacht" → Ausnahme existiert bereits in der Monatsuebersicht, aber **nicht** in Dashboard/Terminal-Warnungen → **kein Duplicate**.

### DATEIEN (max. 3)
- `controller/DashboardController.php`
- `controller/TerminalController.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) Dashboard: Zeitwarnungen filtern Nachtschicht-Paare (Kommen 18:00–23:59 am Tag D, Gehen 00:00–06:00 am Tag D+1; jeweils genau 1 Stempel pro Tag).
2) Terminal: Gleiches Filter-Verhalten in der Warnbox (letzte 14 Tage, max. 3 Zeilen).

### AKZEPTANZ
- Nachtschicht-Beispiel (Kommen spaet am Tag D, Gehen frueh am Tag D+1) erscheint **weder** im Dashboard-Warnblock **noch** in der Terminal-Warnbox als Fehler.

### TEST (manuell)
1) Mitarbeiter: Kommen 23:00 (Tag D), Gehen 05:00 (Tag D+1) → keine Zeitwarnung.
2) Echter Fehler: Kommen 08:00 (Tag D), kein Gehen bis Folgetag → Warnung ab Folgetag weiterhin vorhanden.

### NEXT
- OPTIONAL Micro-Patch: Zeitfenster (18–06) als DB-Config-Key machen oder Heuristik erweitern (z. B. 2–3 Stempel-Faelle), **ohne** echte Fehler zu maskieren.


## Patch P-2026-01-12-08: Monatsuebersicht Mikro-Buchungen komplett ausblenden

- **ZIP:** P-2026-01-12-08_monatsuebersicht-mikrobuchungen-ausblenden.zip

### Ziel
- Mikro-Buchungen sollen in der Monatsuebersicht vollstaendig wie "ignoriert" wirken: keine An/Ab-Uhrzeiten, kein FEHLT/⚠, Anzeige als "-".

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 195740_gesammt.zip — `40895d11027cb5f7287155111b1b974ef2979c1e7f620131033ad55bf7098253`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `19dd12f3fb4a7e630f7b5cce2a5f737160f12b6ed5f2f4759b926dba56b2a810`

### DUPLICATE-CHECK
- Suche (History/Code): "Mikro", "0.01", "Strich" → Patch P-2026-01-12-01 zeigt Mikro-IST als Strich, aber An/Ab/FEHLT konnten noch sichtbar sein → Erweiterung noetig → **kein Duplicate**.

### DATEIEN (max. 3)
- `views/report/monatsuebersicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) Monatsuebersicht: Wenn ein Tag als `micro_ignoriert` markiert ist, werden An/Ab-Stempel pro Block bewusst als leer behandelt (`hatStempel=false`) → Anzeige '-' statt Uhrzeiten und keine FEHLT/⚠-Markierung.

### AKZEPTANZ
- Ein Tag, der nur aus einer Mikro-Buchung besteht (z. B. 20 Sekunden), erscheint in der Monatsuebersicht ohne Uhrzeiten ('-') und ohne FEHLT/⚠.

### TEST (manuell)
1) Fall: Kommen 08:00, Gehen 08:00:20 → Monatsuebersicht: An/Ab '-' und keine FEHLT/⚠.
2) Normaler Tag bleibt unveraendert.

### NEXT
- OPTIONAL: gleiche Mikro-Ausblendung auch in Tagesansicht/Exportlisten, falls dort noch Mikro-Zeiten auftauchen.

## Patch P-2026-01-13-01: Tagesansicht Mikro-Buchungen ausblenden

- **ZIP:** P-2026-01-13-01_tagesansicht-mikrobuchungen-ausblenden.zip

### Ziel
- In der Tagesansicht sollen Mikro-Stempelpaare (Kommen/Gehen innerhalb von <= 3 Minuten) standardmäßig nicht in der Buchungs-Tabelle erscheinen, um 0,01h/Fehlinterpretationen zu vermeiden; per Toggle sollen sie bei Bedarf einblendbar sein.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 232342_gesammt.zip — `304c7b1c0947cc77bf53511d14623df7a6a37c086b10734dc2bc005d63a0693f`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `71af33241aee6e5e7cf19b7a5c470fd954f05b287caa80f7deeef44b016f53ab`

### DUPLICATE-CHECK
- Suche (History/Code): "tagesansicht" + "Mikro" + "show_micro" → bisher nur Monatsreport/Monatsuebersicht behandelt; NEXT von P-2026-01-12-08 nennt Tagesansicht explizit als optionalen naechsten Schritt → **kein Duplicate**.

### DATEIEN (max. 3)
- `controller/ZeitController.php`
- `views/zeit/tagesansicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) ZeitController: filtert in der Tagesansicht standardmäßig aufeinanderfolgende Kommen/Gehen-Stempelpaare mit Delta <= 180 Sekunden aus der Anzeige-Liste (DB bleibt unveraendert).
2) Tagesansicht-View: zeigt einen Hinweis + Link zum Einblenden (Parameter `show_micro=1`) und traegt den Toggle in GET-Formulare weiter.

### AKZEPTANZ
- Ein Tag mit Kommen 08:00:00 und Gehen 08:00:20 zeigt in der Tagesansicht standardmäßig keine Buchungen (aber einen Hinweis mit "Mikro-Buchungen anzeigen"); mit `&show_micro=1` werden die Stempel sichtbar.

### TEST (manuell)
1) Zwei Stempel setzen: Kommen 08:00:00, Gehen 08:00:20.
2) `?seite=zeit_heute&datum=...&mitarbeiter_id=...` → Hinweis + keine Tabelleintraege fuer diese Mikro-Paare.
3) Klick "Mikro-Buchungen anzeigen" (oder `&show_micro=1`) → beide Stempel erscheinen wieder in der Tabelle.

### NEXT
- OPTIONAL Micro-Patch: gleiche Mikro-Ausblendung auch in weiteren Listen/Exports (z. B. Tages-/Wochen-Export), ohne Korrektur-Moeglichkeiten fuer Admin zu verlieren.


## Patch P-2026-01-13-02: Monatsuebersicht Mikro-Buchungen einblendbar

- **ZIP:** P-2026-01-13-02_monatsuebersicht-mikrobuchungen-toggle.zip

### Ziel
- In der Monatsuebersicht sollen Mikro-Stempelpaare (Kommen/Gehen innerhalb von <= 3 Minuten) standardmaessig weiterhin **nicht** in der Liste auftauchen, aber bei Bedarf per Checkbox/GET-Parameter sichtbar gemacht werden koennen, ohne dass sie in IST/SOLL oder Warnungen einlaufen.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 173015_gesammt.zip — `905ee34303cf0c6837636fa7d90215fb92acb8898ac1a9908ad2d27978304453`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `aef9a09a7001642012bfe8073ee74aabf3fd97964518aac7c0a78aa7ebc4abaa`

### DUPLICATE-CHECK
- Suche (History/Code): "Monatsuebersicht" + "show_micro" → bisher nur Tagesansicht hatte Toggle; Monatsuebersicht blendet Mikro-Buchungen ohne Toggle komplett aus → **kein Duplicate**.

### DATEIEN (max. 3)
- `controller/ReportController.php`
- `views/report/monatsuebersicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) ReportController: nimmt `show_micro=1` an und stellt `$showMicro` im View bereit.
2) Monatsuebersicht-View: Checkbox "Mikro-Buchungen anzeigen"; bei aktivem Toggle werden Zeitstempel angezeigt (mit "micro"-Hinweis), aber Mikro-Buchungen bleiben aus IST/Pause/Markierungen ausgeschlossen (kein FEHLT/⚠ durch Mikro-Buchungen).

### AKZEPTANZ
- Ohne Checkbox erscheinen Mikro-Buchungen wie bisher als "-"/ausgeblendet.
- Mit Checkbox/`&show_micro=1` werden die Mikro-Zeiten sichtbar, aber **IST/Pause bleiben "-"** und es gibt **keine** FEHLT/⚠-Markierung nur wegen dieser Mikro-Buchung.

### TEST (manuell)
1) Tag mit Mikro-Paar (z. B. Kommen 08:00:00, Gehen 08:00:20) erzeugen.
2) `?seite=report_monat&jahr=YYYY&monat=MM` → keine sichtbaren Zeiten fuer dieses Paar.
3) Checkbox aktivieren oder `&show_micro=1` → Zeiten sichtbar, als Mikro markiert; Summen/Warnungen unveraendert.

### NEXT
- DONE in P-2026-01-13-03: Toggle-Status beim Klick "Bearbeiten" (Tagesansicht) als GET weiterreichen, damit Mikro-Analyse ohne erneutes Umschalten moeglich ist.


## Patch P-2026-01-13-03: Monatsuebersicht show_micro an Tagesansicht weiterreichen

- **ZIP:** P-2026-01-13-03_monatsuebersicht-show-micro-weiterreichen.zip

### Ziel
- Wenn in der Monatsuebersicht Mikro-Buchungen eingeblendet sind (`show_micro=1`), soll der Klick auf „Bearbeiten“ (Tagesansicht) den Toggle automatisch mitnehmen, damit die Mikro-Analyse ohne erneutes Umschalten moeglich ist.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 182841_gesammt.zip — `db03863dfe49eb4f4a047f9d9237b115fb3bba65c30281b37890a112d8ba3744`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `b9761cdb9769f574151bc9dfa928da43a55b895876841f4d636a3804b907207d`

### DUPLICATE-CHECK
- Suche (Code): `monatsuebersicht.php` + "Bearbeiten" + `show_micro` → Link hatte den Parameter bisher nicht; Tagesansicht besitzt Toggle bereits → **kein Duplicate**.

### DATEIEN (max. 3)
- `views/report/monatsuebersicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) Monatsuebersicht-View: haengt bei aktivem `$showMicro` an den „Bearbeiten“-Link `&show_micro=1` an.

### AKZEPTANZ
- Wenn Monatsuebersicht mit `&show_micro=1` geoeffnet ist, enthaelt der „Bearbeiten“-Link ebenfalls `show_micro=1` und die Tagesansicht zeigt Mikro-Buchungen direkt an.

### TEST (manuell)
1) Monatsuebersicht oeffnen: `?seite=report_monat&jahr=YYYY&monat=MM&mitarbeiter_id=...&show_micro=1`.
2) In einem Tag auf „Bearbeiten“ klicken.
3) URL enthaelt `show_micro=1` und die Tagesansicht zeigt Mikro-Buchungen (Hinweis "Mikro-Buchungen werden angezeigt.").

### NEXT
- Zurueck zum Feldtest (T-069): Terminal/Backend weiter klicken, naechster Bugfix wieder als Micro-Patch.

## Patch P-2026-01-13-04: Tagesansicht show_micro Toggle bei Bearbeiten/Abbrechen behalten

- **ZIP:** P-2026-01-13-04_tagesansicht-show-micro-links.zip

### Ziel
- Wenn in der Tagesansicht Mikro-Buchungen angezeigt werden (`show_micro=1`), soll der Toggle beim Klick auf „Bearbeiten“ (Einzelbuchung) sowie „Abbrechen“ erhalten bleiben, damit die Mikro-Analyse ohne erneutes Umschalten moeglich ist.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 184055_gesammt.zip — `7f6e67f224d5bc9fcb8b04467193be37266d78bbe60cc794142d04db0c8167b3`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `8ecfc0a3c56456d8b32244f6f5ba64bca15f51a279675aea0a50a5bedc7d547f`

### DUPLICATE-CHECK
- Suche (Code): `ZeitController::buildTagesansichtUrl` + `show_micro` sowie `views/zeit/tagesansicht.php` + `edit_id`/`Abbrechen` → bisher wurde `show_micro` nicht konsistent in Redirects/Links erhalten → **kein Duplicate**.

### DATEIEN (max. 3)
- `controller/ZeitController.php`
- `views/zeit/tagesansicht.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) ZeitController: `buildTagesansichtUrl()` haengt bei vorhandenem `show_micro=1` (GET/POST) den Parameter an Redirect-URLs an.
2) Tagesansicht-View: Haengt bei aktivem `$zeigeMicroBuchungen` an Bearbeiten-Links `&show_micro=1` an.
3) Tagesansicht-View: „Abbrechen“-Link im Bearbeiten-Block behaelt `show_micro=1`.
4) Tagesansicht-View: Delete-Formular traegt `show_micro=1` als Hidden mit, damit Redirects den Toggle behalten.

### AKZEPTANZ
- Tagesansicht mit `&show_micro=1`: Klick „Bearbeiten“ → URL enthaelt weiterhin `show_micro=1`.
- Im Bearbeiten-Block „Abbrechen“ → Zurueck zur Tagesansicht mit `show_micro=1`.
- Nach Speichern/Loeschen (POST) bleibt der Redirect in der Tagesansicht bei aktivem Toggle auf `show_micro=1`.

### TEST (manuell)
1) Tagesansicht oeffnen: `?seite=zeit_heute&datum=YYYY-MM-DD&mitarbeiter_id=X&show_micro=1`.
2) Auf „Bearbeiten“ bei einer Buchung klicken → URL enthaelt `show_micro=1`.
3) „Abbrechen“ klicken → URL enthaelt `show_micro=1`.

### NEXT
- Zurueck zum Feldtest (T-069): Backend/Terminal weiter klicken, naechster Bugfix wieder als Micro-Patch.

## Patch P-2026-01-13-05: Monats-PDF Mikro-Buchungen ausblenden

- **ZIP:** P-2026-01-13-05_monats-pdf-mikrobuchungen-ausblenden.zip

### Ziel
- Mikro-Buchungen (Kommen/Gehen innerhalb von <= 3 Minuten) sollen im Monats-PDF standardmäßig nicht als zusätzliche Block-Zeilen erscheinen. Per `&show_micro=1` sollen sie sichtbar gemacht werden können.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 190401_gesammt.zip — `71234d0ae9d607f9ce885226e74b62698ae78f88ec93bf24121516fd510605ee`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `cdb143b6341fe0f49fa771a4710f8f1620cd574fb486b5c041ac77c8a59a9770`

### DUPLICATE-CHECK
- Suche (Code): `PDFService` + `show_micro` / "Mikro" → bisher nur Monats-/Tagesansicht, nicht PDF → **kein Duplicate**.

### DATEIEN (max. 3)
- `services/PDFService.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) PDFService: liest `show_micro` (GET) und blendet Mikro-Blocks standardmäßig aus:
   - Wenn `micro_arbeitszeit_ignoriert=1` im Tageswert gesetzt ist, werden keine Roh-Blocks angezeigt.
   - Zusätzlich werden Blocks mit Dauer <= 180 Sekunden herausgefiltert.
2) Mit `?show_micro=1` werden die Blocks wieder angezeigt.

### AKZEPTANZ
- Monats-PDF ohne `show_micro`: Mikro-Blocks tauchen nicht als eigene Zeilen auf.
- Monats-PDF mit `show_micro=1`: Mikro-Blocks sind sichtbar.

### TEST (manuell)
1) Monats-PDF öffnen: `?seite=report_monat_pdf&jahr=YYYY&monat=MM&mitarbeiter_id=X`
2) Prüfen: Tag mit Mikro-Buchung zeigt keine Block-Zeilen.
3) Mit `&show_micro=1` erneut öffnen → Mikro-Zeilen sichtbar.

### NEXT
- Zurueck zum Feldtest (T-069 Teil 2b): Monats-PDF weiter klicken (Mehrseiten/Grenzfaelle) und naechsten Bugfix als Micro-Patch.



## Patch P-2026-01-13-06: Terminal-Übersicht Mikro-Buchungen ausblenden

- **ZIP:** P-2026-01-13-06_terminal-uebersicht-mikrobuchungen-ausblenden.zip

### Ziel
- In der Terminal-Startseite („Übersicht (heute)“) sollen Mikro-Buchungen (Kommen/Gehen innerhalb von <= 3 Minuten) standardmäßig nicht in der Liste erscheinen, damit Fehleingaben am Terminal nicht verwirren. Per `?show_micro=1` sollen sie zu Debug-Zwecken sichtbar sein.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 192215_gesammt.zip — `70befb9577a2338e6a8553c7562c1773394b89915211208bd68d881bdfa076bd`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `575233f4fc804eaf1468cffb2a9da189eb25b64ef160d8920724b8d5671509a7`

### DUPLICATE-CHECK
- Suche (Code): `TerminalController::holeHeutigeZeitUebersicht` / `heuteBuchungen` → bisher keine Mikro-Filterung am Terminal vorhanden (nur Monats-/Tagesansicht/PDF) → **kein Duplicate**.

### DATEIEN (max. 3)
- `controller/TerminalController.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) TerminalController: `holeHeutigeZeitUebersicht(..., $zeigeMicroBuchungen)` filtert Mikro-Paare (Kommen/Gehen innerhalb 180s) aus der Buchungsliste, wenn `show_micro` nicht aktiv ist.
2) TerminalController: `show_micro` wird in allen Startscreen-Renders (Start + Auftrag/Nebenauftrag-Form + Urlaub-Form) berücksichtigt.

### AKZEPTANZ
- Terminal Start ohne `show_micro`: Mikro-Paare tauchen nicht in „Heutige Buchungen“ auf.
- Terminal Start mit `&show_micro=1`: Mikro-Paare sind sichtbar.

### TEST (manuell)
1) Terminal Start öffnen: `terminal.php?aktion=start` → Liste prüfen.
2) Mit `&show_micro=1` öffnen → Liste enthält Mikro-Paare.
3) Auftrag/Nebenauftrag/Urlaub-Form öffnen → Verhalten identisch.

### NEXT
- Feldtest T-069 weiter: Terminal stempeln (kommen/gehen + evtl. Doppelklick) und prüfen, ob die Übersicht ruhig bleibt.



## Patch P-2026-01-13-07: Config-Default für Mikro-Buchungen

- **ZIP:** P-2026-01-13-07_config-micro-schwelle-default.zip

### Ziel
- Der Config-Key `micro_buchung_max_sekunden` (Sekunden) soll automatisch in `config` angelegt werden, falls er fehlt. Damit ist die Mikro-Buchungs-Grenze zentral einstellbar (Default 180s = 3 Minuten).

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 193839_gesammt.zip — `e203afcdeb377792e7c1e24ca78c3700c4cfea42de585443efdbcbac054d9b42`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `586e046c7d18cba8a40021f1c8810d33d1c33347a5236edf49da6221fc52a18b`

### DUPLICATE-CHECK
- Suche: `micro_buchung_max_sekunden` in `core/DefaultsSeeder.php` → Key wurde bisher nicht automatisch geseedet → **kein Duplicate**.

### DATEIEN (max. 3)
- `core/DefaultsSeeder.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) `DefaultsSeeder`: Neuer Default-Config-Key `micro_buchung_max_sekunden` (int, Default 180) wird per `INSERT IGNORE` mit angelegt.

### AKZEPTANZ
- Fehlt der Key in `config`, wird er beim ersten Request automatisch angelegt (Default 180).
- Ist der Key vorhanden, wird er nicht überschrieben (idempotent).

### TEST (manuell)
1) In DB `config` ggf. Key löschen: `DELETE FROM config WHERE schluessel='micro_buchung_max_sekunden';`
2) Backend/Terminal einmal aufrufen → Key muss wieder vorhanden sein.
3) Optional Wert ändern und Monatsübersicht/PDF prüfen (Mikro-Grenze passt sich an).

### NEXT
- Feldtest (T-069) weiter: Monatsübersicht/PDF/Terminal klicken und nächste Auffälligkeit als Micro-Patch fixen.



## Patch P-2026-01-14-01: Tagesansicht Mikro-Buchungen über Config

- **ZIP:** P-2026-01-14-01_tagesansicht-micro-config.zip

### Ziel
- In der Tagesansicht soll die Mikro-Buchungs-Grenze nicht hart 180s sein, sondern den Config-Key `micro_buchung_max_sekunden` verwenden.

### EINGELESEN (SHA256)
- **Projekt-ZIP:** 5841854_gesammt.zip — `6a1c5d6cc197314b7f60e98f2af30c9e0aab89f0fcbf42617e42cdfcf45bfcf2`
- **Master:** docs/master_prompt_zeiterfassung_v10.md — `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
- **Rechte:** docs/rechte_prompt.md — `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
- **SQL Snapshot:** sql/zeiterfassung_aktuell.sql — `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **Offline Schema:** sql/offline_db_schema.sql — `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
- **DEV_HISTORY vorher:** docs/DEV_PROMPT_HISTORY.md — `955244a98f34e8bb769035aec04baf6cf101f6e27e2fdbd656c0933a7d0634e6`

### DATEIEN (max. 3)
- `controller/ZeitController.php`
- `docs/DEV_PROMPT_HISTORY.md`

### TECHNISCHE AENDERUNGEN
1) `ZeitController`: liest `micro_buchung_max_sekunden` (Default 180, Range 30..3600) über `KonfigurationService` und nutzt den Wert beim Filtern der Buchungsliste.

### AKZEPTANZ
- Ohne `show_micro`: Mikro-Paare (Kommen/Gehen mit Delta <= Config-Wert) werden in der Tagesliste ausgeblendet.
- Mit `show_micro=1`: Mikro-Paare bleiben sichtbar.
- Bei fehlendem/ungültigem Config-Wert wird auf 180 Sekunden gefallbackt.

### TEST (manuell)
1) Tagesansicht öffnen (ohne `show_micro`) → Mikro-Paare ausgeblendet.
2) Tagesansicht mit `&show_micro=1` → Mikro-Paare sichtbar.
3) `UPDATE config SET wert='60' WHERE schluessel='micro_buchung_max_sekunden';` → Tagesansicht neu laden → Filter reagiert.

### NEXT
- Restliche harte 180s/0,05h (Terminal/PDF/Report) auf den Config-Key umstellen.

## P-2026-01-14-02
- ZIP: `P-2026-01-14-02_terminal-mitarbeiterpanel-arbeitszeit-uebersicht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193932_gesammt.zip` = `a93f2ac950692c823bc9d238b261109e410791bb96216fa41e212b0108a2ac73`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `5b96df4f179b88d4bd9c84eaf66355e0371b26a0cc1463bf34195a92fe466a86`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Monatsstatus (T-092) existiert in `views/terminal/start.php` nur im Block „Übersicht (heute)“; im Mitarbeiterpanel war keine Arbeitszeit-Übersicht vorhanden → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen: Im Mitarbeiterpanel (Bottom-Details) werden die drei Stundenwerte aus dem bestehenden Monatsstatus angezeigt (Soll Monat / Soll bis heute / Ist bis heute).
- **AKZEPTANZ:**
  - Nach Terminal-Login lässt sich das Mitarbeiterpanel unten öffnen und zeigt die drei Werte; bei Offline-Modus steht „nur online verfügbar“ statt Werte.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Optional: gleiche Arbeitszeit-Übersicht auch in `auftrag_starten.php`, `auftrag_stoppen.php`, `urlaub_beantragen.php` im Mitarbeiterpanel anzeigen.

## P-2026-01-15-01
- ZIP: `P-2026-01-15-01_terminal-auftrag-starten-arbeitszeit-uebersicht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `193501_gesammt.zip` = `254a1625bb49e4b2b5ac5799f9eff36cd74fd5aa62b1facfda76611aefd697e4`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0a3f484f67a47055895801bec88cbd71290f2b25b89506f56deba27544d2f5ad`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Arbeitszeit-Übersicht existiert im Startscreen-Mitarbeiterpanel (`views/terminal/start.php`), aber nicht in `views/terminal/auftrag_starten.php` → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/auftrag_starten.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Auftrag starten: Mitarbeiterpanel unten zeigt jetzt ebenfalls Soll Monat / Soll bis heute / Ist bis heute (nur online).
- **AKZEPTANZ:**
  - In `terminal.php?aktion=auftrag_starten` lässt sich das Mitarbeiterpanel öffnen und zeigt die drei Werte; im Offline-Modus steht „nur online verfügbar“.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/auftrag_starten.php`
- **NEXT:**
  - Gleiche Arbeitszeit-Übersicht auch in `views/terminal/auftrag_stoppen.php` im Mitarbeiterpanel anzeigen.


## P-2026-01-15-02
- ZIP: `P-2026-01-15-02_terminal-auftrag-stoppen-arbeitszeit-uebersicht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `200633_gesammt.zip` = `745514d265a35bdc961f1bc10c9f60bab4086a6134b907cf01f100e5872a7750`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `adfb34d51662ec928b789ed79f118ef8d4ed0a34409e5cd90d422dede6495b49`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Arbeitszeit-Übersicht existiert im Startscreen-Mitarbeiterpanel (`views/terminal/start.php`) und in `views/terminal/auftrag_starten.php`, aber nicht in `views/terminal/auftrag_stoppen.php` → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/auftrag_stoppen.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Auftrag stoppen: Mitarbeiterpanel unten zeigt jetzt ebenfalls Soll Monat / Soll bis heute / Ist bis heute (nur online).
- **AKZEPTANZ:**
  - In `terminal.php?aktion=auftrag_stoppen` lässt sich das Mitarbeiterpanel öffnen und zeigt die drei Werte; im Offline-Modus steht „nur online verfügbar“.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/auftrag_stoppen.php`
- **NEXT:**
  - Gleiche Arbeitszeit-Übersicht auch in `views/terminal/urlaub_beantragen.php` im Mitarbeiterpanel anzeigen.


## P-2026-01-16-01
- ZIP: `P-2026-01-16-01_terminal-urlaub-beantragen-arbeitszeit-uebersicht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `132542_gesammt.zip` = `48aeed1039168f75f451ef36b9a452e2601e740046317721f8fc1b1ca7fc6135`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `757222d04911e40b9fc8e5d53ef269ea561d8bdc78dc493d01b44852e9c044ee`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - `views/terminal/urlaub_beantragen.php`: Mitarbeiterpanel war nur bei `$nachricht` sichtbar; `$mitarbeiterName/$mitarbeiterId` wurden dort vor Definition benutzt; Arbeitszeit-Übersicht fehlte → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `views/terminal/urlaub_beantragen.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Urlaub beantragen: Mitarbeiterpanel unten ist bei eingeloggtem Mitarbeiter immer sichtbar.
  - Panel zeigt Soll Monat / Soll bis heute / Ist bis heute (nur online; nutzt `$monatsStatus`, falls vorhanden).
  - `$mitarbeiterName/$mitarbeiterId` werden defensiv initialisiert (keine undefined vars/warnings).
- **AKZEPTANZ:**
  - In `terminal.php?aktion=urlaub_beantragen` lässt sich das Mitarbeiterpanel öffnen und zeigt die drei Werte; im Offline-Modus steht „nur online verfügbar“.
- **TESTS:**
  - `php -l views/terminal/urlaub_beantragen.php`
- **NEXT:**
  - Keine weiteren Terminal-Views mit eigenem Mitarbeiterpanel offen (Start + Auftrag starten/stoppen + Urlaub beantragen sind abgedeckt).

## P-2026-01-16-02
- ZIP: `P-2026-01-16-02_terminal-nebenauftrag-starten-monatsstatus.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `142127_gesammt.zip` = `5ce06143e8ee12220c68e2322f8a2d47eabcd4a22bf59513bef9beba2cbfd27c`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1b4a4b52a456092d9713444fc266e172bcc62660eb4c97ea44a4bf57a260bad6`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - `views/terminal/start.php` zeigt die Arbeitszeit-Übersicht im Mitarbeiterpanel nur, wenn `$monatsStatus` gesetzt ist.
  - In `TerminalController::nebenauftragStartenForm()` wurde `$monatsStatus` bisher nicht berechnet → Panel zeigte „aktuell nicht verfügbar“ → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `TerminalController::nebenauftragStartenForm()` berechnet jetzt `$monatsStatus` (Soll Monat / Soll bis heute / Ist bis heute), analog zu Auftrag starten/stoppen.
  - Dadurch zeigt das Mitarbeiterpanel (Startscreen-View) in `terminal.php?aktion=nebenauftrag_starten` die Arbeitszeit-Übersicht (nur online).
- **AKZEPTANZ:**
  - In `terminal.php?aktion=nebenauftrag_starten` lässt sich das Mitarbeiterpanel öffnen und zeigt die drei Werte; im Offline-Modus steht „nur online verfügbar“.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Gleiche `$monatsStatus`-Berechnung auch in `TerminalController::nebenauftragStoppenForm()` ergänzen, damit `terminal.php?aktion=nebenauftrag_stoppen` die Arbeitszeit-Übersicht ebenfalls zeigt.


## P-2026-01-16-03
- ZIP: `P-2026-01-16-03_terminal-nebenauftrag-stoppen-monatsstatus.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `151937_gesammt.zip` = `048f9d01ed486e91aa0a002ee639dee5b3a581c8c1a536003071214f20d6c6a7`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `91e7db907b4ed04655424b2ce219a387932a014e90176b312bbc1950dcba81a5`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - `views/terminal/start.php` zeigt die Arbeitszeit-Übersicht im Mitarbeiterpanel nur, wenn `$monatsStatus` gesetzt ist.
  - In `TerminalController::nebenauftragStoppenForm()` wurde `$monatsStatus` bisher nicht berechnet → Panel zeigte „aktuell nicht verfügbar“ → **nicht DONE**.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `TerminalController::nebenauftragStoppenForm()` berechnet jetzt `$monatsStatus` (Soll Monat / Soll bis heute / Ist bis heute), analog zu Nebenauftrag starten.
  - Dadurch zeigt das Mitarbeiterpanel (Startscreen-View) in `terminal.php?aktion=nebenauftrag_stoppen` die Arbeitszeit-Übersicht (nur online).
- **AKZEPTANZ:**
  - In `terminal.php?aktion=nebenauftrag_stoppen` lässt sich das Mitarbeiterpanel öffnen und zeigt die drei Werte; im Offline-Modus steht „nur online verfügbar“.
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Fertig (Nebenauftrag starten + stoppen sind abgedeckt).


## P-2026-01-16-04
- ZIP: `P-2026-01-16-04_terminal-stoerung-mitarbeiterpanel-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `175736_gesammt.zip` = `6765ea53498685976c97b4cc1aedcbf68429873db786e1ba9ed67ecb60be8fec`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `30b1861d0481c5fca15a98923b135ca2dfc96c0ab087686aad91c2a2b9a9f332`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - `views/terminal/stoerung.php` erwartete `$stoerungEintrag`, aber `TerminalController::stoerung()` lieferte nur `$letzterFehler` → Fehlerdetails konnten leer bleiben.
  - Störungs-View hatte kein Mitarbeiterpanel → Arbeitszeit-Übersicht war dort nicht erreichbar.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/stoerung.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `TerminalController::stoerung()` setzt jetzt `$stoerungEintrag` (View-kompatibel) und berechnet optional `$monatsStatus` (nur online).
  - `views/terminal/stoerung.php` zeigt (wenn ein Mitarbeiter eingeloggt ist) ein klickbares Mitarbeiterpanel mit Arbeitszeit-Übersicht (Soll Monat / Soll bis heute / Ist bis heute).
- **AKZEPTANZ:**
  - Im Störungsmodus werden Fehlerdetails (SQL + Fehlermeldung) angezeigt, sofern vorhanden.
  - Wenn im Störungsmodus noch ein Mitarbeiter eingeloggt ist, lässt sich das Mitarbeiterpanel öffnen und zeigt die drei Werte (online) bzw. „nur online verfügbar“ (offline).
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/stoerung.php`
- **NEXT:**
  - Optional: `views/terminal/logout.php` ebenfalls mit Mitarbeiterpanel/Arbeitszeit-Übersicht ausstatten (wenn gewünscht).


## P-2026-01-16-05
- ZIP: `P-2026-01-16-05_terminal-arbeitszeit-labels-vereinheitlichen.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `182059_gesammt.zip` = `2641038d9ff86cfea74edd5caadaf2f5daf74ee3ad56175e47d94cd34318f9f5`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `43fe7a67104e53a2ca409f77b95a5d15073b24949fcf934f631a899e469cb7b5`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - In `views/terminal/auftrag_starten.php` und `views/terminal/auftrag_stoppen.php` wichen die Labels von Start/Urlaub/Störung ab (enthielten "(Soll)"/"(Ist)").
- **DATEIEN (max. 3):**
  1) `views/terminal/auftrag_starten.php`
  2) `views/terminal/auftrag_stoppen.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Labels im Mitarbeiterpanel vereinheitlicht (ohne "(Soll)"/"(Ist)"):
    - Arbeitsstunden in diesem Monat
    - Arbeitsstunden in diesem Monat bis heute
    - Geleistete Arbeitsstunden bis heute
- **AKZEPTANZ:**
  - In `terminal.php?aktion=auftrag_starten` und `terminal.php?aktion=auftrag_stoppen` sind die drei Zeilen im Mitarbeiterpanel identisch benannt wie im Startscreen.
- **TESTS:**
  - `php -l views/terminal/auftrag_starten.php`
  - `php -l views/terminal/auftrag_stoppen.php`
- **NEXT:**
  - Optional: bei zukünftigen Terminal-Views die gleichen Label-Strings wiederverwenden.

## P-2026-01-16-06
- ZIP: `P-2026-01-16-06_terminal-logout-mitarbeiterpanel-arbeitszeit-uebersicht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `183548_gesammt.zip` = `aa9afd979b260317061de7597fb296dbcf3c2b6a0053daa733da1de7a8f9ddd1`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `81c04f554a669bbbb29a7101e588b10963a500e14c238c945ebd9f0ddbb99532`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - `views/terminal/logout.php` zeigte bisher nur „Angemeldet als …“ – keine klickbare Arbeitszeit-Übersicht.
  - Optionaler NEXT aus P-2026-01-16-04 („Logout-View ebenfalls mit Mitarbeiterpanel“) war noch offen.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/logout.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `TerminalController::logout()` berechnet jetzt (wenn online) `$monatsStatus` (Soll Monat / Soll bis heute / Ist bis heute).
  - `views/terminal/logout.php` zeigt ein klickbares Mitarbeiterpanel mit Arbeitszeit-Übersicht (online) bzw. Hinweis „nur online verfügbar“.
- **AKZEPTANZ:**
  - `terminal.php?aktion=logout` zeigt bei eingeloggtem Mitarbeiter das Panel mit den drei Werten (online).
  - Bei inaktiver Haupt-DB/Offline-Modus erscheint „Arbeitszeit-Übersicht nur online verfügbar“.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/logout.php`
- **NEXT:**
  - Zurück zu **T-069 Feldtest** (Terminal/Login/Kommen/Gehen/Auto-Logout) und Bugs sammeln.


## P-2026-01-16-07
- ZIP: `P-2026-01-16-07_terminal-stoerung-auto-redirect-start.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `202248_gesammt.zip` = `80c6f0a31b5a271216c261d7faaefff8a65725b99f42539726cce87e520f0b14`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ef1232287c3f96ba2e2eb4ae886682443a52ac8601cbe76ae633c5b364099e93`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Stoerungsmodus existierte bereits, aber auf `aktion=stoerung` konnte man nach Admin-Fix haengen bleiben.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/stoerung.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `TerminalController::stoerung()` leitet jetzt auf `aktion=start` um, wenn kein Queue-Fehler-Eintrag mehr existiert (ausser fatal: Haupt-DB & Offline-Queue beide offline).
  - `views/terminal/stoerung.php` zeigt einen Button „Neu pruefen / Start“.
- **AKZEPTANZ:**
  - Wenn der letzte Fehler im Backend geloescht/behoben wurde, fuehrt Reload von `terminal.php?aktion=stoerung` zurueck zum Startscreen.
  - Im fatalen Fall (Haupt-DB und Offline-Queue beide offline) bleibt die Stoerungsseite bestehen.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/stoerung.php`
- **NEXT:**
  - Zurueck zu **T-069 Feldtest** und diese neue „Neu pruefen“-Logik in echten Stoerungsfaellen testen.


## P-2026-01-17-01
- ZIP: `P-2026-01-17-01_terminal-start-monatsstatus-dup-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `042649_gesammt.zip` = `e7f4daff8c843c494c540c47b5df50c67dea73cfb65ab50ff21a7dabf240af37`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `67bf3233ea235f9d6d561b9870edc0fdec82c8f22c0d2ca5c68de805a3ed5afd`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - `TerminalController::start()` enthielt direkt nach `$terminalTimeoutSekunden` einen ueberzaehligen Monatsstatus-Block (Copy/Paste aus Logout), der `$mitarbeiter` zu diesem Zeitpunkt noch nicht gesetzt hatte.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Entfernt: der ueberzaehlige Monatsstatus-Block am Anfang von `TerminalController::start()`.
- **AKZEPTANZ:**
  - `terminal.php?aktion=start` wirft keine PHP-Warnung mehr wegen undefiniertem `$mitarbeiter`.
  - Monatsstatus im Mitarbeiterpanel bleibt unveraendert (wird weiterhin spaeter im Start-Flow berechnet).
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Optional: Monatsstatus-Berechnung in einen Helper auslagern und Duplikate im Controller reduzieren.


## P-2026-01-17-02
- ZIP: `P-2026-01-17-02_terminal-start-uebersicht-labels-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `044026_gesammt.zip` = `72017c9af44024c9e59e54ad4336e84c04f9300de22d21651edc71225f9e12d0`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `68a47684e3d48927f9e6d4d52b92b3856e5e6e16c95ae194047044d3704eff41`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - In `views/terminal/start.php` waren im Block "Monatsstatus" ("Übersicht (heute)") noch alte Label (Soll/Ist-Formulierung), waehrend das Mitarbeiterpanel bereits die Wunsch-Labels nutzt.
- **DATEIEN (max. 3):**
  1) `views/terminal/start.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Startscreen: Labels im Block "Monatsstatus" vereinheitlicht:
    - "Arbeitsstunden in diesem Monat bis heute"
    - "Geleistete Arbeitsstunden bis heute"
- **AKZEPTANZ:**
  - Startscreen "Übersicht (heute)" nutzt jetzt dieselben Begriffe wie das Mitarbeiterpanel.
- **TESTS:**
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Optional: Monatsstatus-Berechnung im Controller als Helper auslagern (Duplikate reduzieren).


## P-2026-01-17-03
- ZIP: `P-2026-01-17-03_terminal-monatsstatus-ist-nur-arbeitszeit.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `044705_gesammt.zip` = `ec0b53b53fa535ea2c58e62f1fefaaf3cdf31d46a7a1cec1acc09d60de0b3b6f`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `58bbd3b9bc44dd1e0c367f71b6f6949a23596257563060b94ceebb60ecf94ea9`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Monatsstatus/Arbeitszeit-Übersicht war bereits integriert, aber `ist_bisher` zaehlte bislang auch Urlaub/Krank/Feiertag/sonstiges mit.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: In allen Monatsstatus-Berechnungen im `TerminalController` zaehlt `ist_bisher` jetzt **nur** `arbeitszeit_stunden` (echte geleistete Arbeitszeit).
- **AKZEPTANZ:**
  - Label "Geleistete Arbeitsstunden bis heute" entspricht der Summe der **Arbeitszeit** im Monatsreport (ohne Urlaub/Krank/Feiertag).
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Feldtest: Werte im Terminal mit Monatsreport gegenpruefen (ein Monat mit Urlaub/Krank/Feiertag, damit der Unterschied sichtbar ist).

## P-2026-01-17-04
- ZIP: `P-2026-01-17-04_terminal-monatsstatus-live-heute-helper.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `045645_gesammt.zip` = `617f3458a3a2ca3d0bf188a6214ae41c23f4042bc1de8840452ed0f30eac123c`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `b74effb26559d5ee0524004a9bf99b55efa9cf76b24d65b91d447943b16075d5`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Monatsstatus (`ist_bisher`) beruecksichtigt jetzt den **laufenden heutigen Arbeitstag** (wenn "Kommen" ohne "Gehen"), damit die Anzeige dem aktuellen Moment entspricht.
  - Terminal: Monatsstatus-Berechnung als Helper zentralisiert (`berechneMonatsStatusFuerMitarbeiter`) und in allen Terminal-Flows konsistent genutzt.
- **AKZEPTANZ:**
  - Wenn ein Mitarbeiter gerade anwesend ist, steigt "Geleistete Arbeitsstunden bis heute" live an, ohne dass erst "Gehen" gebucht werden muss.
  - Anzeige bleibt im Offline-Modus unveraendert (Monatsstatus bleibt leer/ohne Werte).
- **TESTS:**
  - `php -l controller/TerminalController.php`
- **NEXT:**
  - Optional: Live-Heute noch mit Rundung/Pausenlogik angleichen (derzeit bewusst als Live-Schaetzung).

## P-2026-01-17-05
- ZIP: `P-2026-01-17-05_terminal-arbeitszeit-uebersicht-seite.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `051151_gesammt.zip` = `1752db4b2d2ec43bce87a22c7f9eb2a17dfe5ba6a9a9641ba7d5d8fa2ef9a202`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `b9306ecd095e6e0be6803b378d17701a75b0f97898d5c96ab56389a9624d4a31`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Arbeitszeit-Übersicht im Mitarbeiterpanel existierte bereits, aber UX war zu platzintensiv/unklar.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Klick auf Mitarbeitername unten oeffnet eine **separate Arbeitszeit-Übersicht-Seite** (Start-Subview `?aktion=start&view=arbeitszeit`) mit den drei Kennzahlen.
  - Terminal: Mitarbeiterpanel zeigt nur noch den Namen + Hinweis "Arbeitszeit" (kein ausklappender Textblock mehr).
- **AKZEPTANZ:**
  - Startscreen bleibt uebersichtlich; Arbeitszeitdaten werden auf eigener Seite angezeigt; Rueckkehr per "Zurück" zum Hauptmenue.
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Gleiche Name-Click/Arbeitszeit-Seite auch in den anderen Terminal-Seiten (auftrag_starten/stoppen, nebenauftrag, urlaub, logout, stoerung) angleichen (micro-patches).


## P-2026-01-17-06
- ZIP: `P-2026-01-17-06_terminal-auftrag-starten-link-arbeitszeitseite.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `053718_gesammt.zip` = `2458ab86f94eef18ba40d984db8b923d3c049b83e48eb2b288d6042177e84be5`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `e65ec54fd52dd9b1fc46e55a92790fa8b47d65c7376b58e36f95b744ded73035`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Geprueft: Arbeitszeit-Übersicht existiert als Start-Subview; hier nur UX-Angleichung auf einer weiteren Terminal-Seite.
- **DATEIEN (max. 3):**
  1) `views/terminal/auftrag_starten.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Seite "Auftrag starten" zeigt im unteren Mitarbeiterpanel nur noch den Namen + Link "Arbeitszeit" (kein Textblock mehr). Klick oeffnet `?aktion=start&view=arbeitszeit`.
- **AKZEPTANZ:**
  - Auf "Auftrag starten" ist der Startscreen wieder uebersichtlich; Arbeitszeitdaten sind ueber den Link erreichbar.
- **TESTS:**
  - `php -l views/terminal/auftrag_starten.php`
- **NEXT:**
  - Gleiche Angleichung auf `views/terminal/auftrag_stoppen.php`.



## P-2026-01-17-07
- ZIP: `P-2026-01-17-07_terminal-auftrag-stoppen-link-arbeitszeitseite.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `054432_gesammt.zip` = `756df59ce725918c539db4464e8125ee7a996469d2842fd23cf89392a68e7520`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `6ecdd49de891446b8bff454fd4421e46efc527c62f02e71ae920b244da1280e0`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Geprueft: Arbeitszeit-Übersicht existiert als Start-Subview; hier nur UX-Angleichung auf einer weiteren Terminal-Seite.
- **DATEIEN (max. 3):**
  1) `views/terminal/auftrag_stoppen.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Seite "Hauptauftrag stoppen" zeigt im unteren Mitarbeiterpanel nur noch den Namen + Link "Arbeitszeit" (kein Textblock mehr). Klick oeffnet `?aktion=start&view=arbeitszeit`.
- **AKZEPTANZ:**
  - Auf "Hauptauftrag stoppen" bleibt der Screen uebersichtlich; Arbeitszeitdaten sind ueber den Link erreichbar.
- **TESTS:**
  - `php -l views/terminal/auftrag_stoppen.php`
- **NEXT:**
  - Gleiche Angleichung auf `views/terminal/urlaub_beantragen.php`.


## P-2026-01-17-08
- ZIP: `P-2026-01-17-08_terminal-urlaub-stoerung-link-arbeitszeitseite.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `055528_gesammt.zip` = `96c7169048a940229b2493b90c15d90846aa0fef037553f617a7467c727515fb`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `d3e8986382b437edd5f6ece810b338e5b72708071bd2c0492a20e54960d14060`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Geprueft: Arbeitszeit-Übersicht existiert als Start-Subview; hier nur UX-Angleichung auf weiteren Terminal-Seiten.
- **DATEIEN (max. 3):**
  1) `views/terminal/urlaub_beantragen.php`
  2) `views/terminal/stoerung.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Seiten "Urlaub beantragen" und "Stoerung" zeigen im unteren Mitarbeiterpanel nur noch den Namen + Link "Arbeitszeit" (kein Textblock mehr). Klick oeffnet `?aktion=start&view=arbeitszeit`.
- **AKZEPTANZ:**
  - Screens bleiben uebersichtlich; Arbeitszeitdaten sind konsistent ueber den Link erreichbar.
- **TESTS:**
  - `php -l views/terminal/urlaub_beantragen.php`
  - `php -l views/terminal/stoerung.php`
- **NEXT:**
  - Gleiche Angleichung auf `views/terminal/logout.php`.


## P-2026-01-17-09
- ZIP: `P-2026-01-17-09_terminal-logout-link-arbeitszeitseite.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `060925_gesammt.zip` = `553e81ab200b05ec3799d08ecd8362b7246b0787dcaf30de74246ccb593dbebb`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `e5c81447ea60fc4b3588b7489e518acc31199f437336d2a441fb1042832a4b53`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Geprueft: Arbeitszeit-Übersicht existiert als Start-Subview; hier nur UX-Angleichung auf einer weiteren Terminal-Seite.
- **DATEIEN (max. 3):**
  1) `views/terminal/logout.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Seite "Abmelden" zeigt im unteren Mitarbeiterpanel nur noch den Namen + Link "Arbeitszeit" (kein Textblock mehr). Klick oeffnet `?aktion=start&view=arbeitszeit`.
- **AKZEPTANZ:**
  - Logout-Screen bleibt uebersichtlich; Arbeitszeitdaten sind ueber den Link erreichbar.
- **TESTS:**
  - `php -l views/terminal/logout.php`
- **NEXT:**
  - Fertig: alle Terminal-Screens nutzen jetzt den Link auf die Arbeitszeit-Übersicht-Seite.


## P-2026-01-17-11
- ZIP: `P-2026-01-17-11_report_monat_microbloecke_filter.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `063438_gesammt.zip` = `11bee4335cd9104de93b46f61655a94b35925d33d4fcc241bbe24ae3eb1e72eb`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `655bac2d650fb8ab0a3de807debe30ad7fae37e9135c98677593c1f5a8727733`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **BUG:** Monatsuebersicht zeigt Mikro-Buchungen auch dann in den An/Ab-Spalten, wenn "Mikro-Buchungen anzeigen" **nicht** aktiviert ist (weil Mikro-Flag bisher nur auf Tagesebene greift).
- **DATEIEN (max. 3):**
  1) `controller/ReportController.php`
  2) `views/report/monatsuebersicht.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsuebersicht: Mikro-Bloecke werden jetzt **pro Block** erkannt (Kommen/Gehen innerhalb `config.micro_buchung_max_sekunden`, Default 180s).
  - Wenn `show_micro` aus ist, werden diese Mikro-Bloecke komplett aus der Darstellung entfernt (statt als extra Zeilen/Zeitschnipsel zu erscheinen).
  - Warn-/FEHLT-Pruefung ignoriert Mikro-Bloecke (auch wenn `show_micro` aktiv ist), damit keine falschen ⚠-Marker entstehen.
- **AKZEPTANZ:**
  - Checkbox aus: Keine Mikro-Buchungen in der Monatsuebersicht sichtbar (auch nicht als "roh:"-Zeilen).
  - Checkbox an: Mikro-Buchungen koennen sichtbar sein, beeinflussen aber keine FEHLT/⚠-Logik.
- **TESTS:**
  - `php -l controller/ReportController.php`
  - `php -l views/report/monatsuebersicht.php`
- **NEXT:**
  - keine.


## P-2026-01-17-12
- ZIP: `P-2026-01-17-12_report_monat_micro_roh_fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `065525_gesammt.zip` = `44626a4b31cf59c21a151d87b937eb6cff578eaf3c86de71e80ec7770c7b7637`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `764c125678bfaf6d004148a67694916633d3f654e87c0d27b0dc58b56acffa8a`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **BUG:** Monatsuebersicht zeigt weiterhin "Mikro-Buchungen" trotz deaktivierter Checkbox, wenn Rundung aus einer Roh-Mikro-Buchung scheinbar laengere korrigierte Zeiten macht.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Mikro-Erkennung basiert jetzt zuerst auf **Rohzeiten** (`kommen_roh/gehen_roh`). Wenn Roh-Diff <= `micro_buchung_max_sekunden`, wird der Block als Mikro erkannt.
  - Rundung kann Mikro-Buchungen damit nicht mehr "aufblaehen" – bei deaktivierter Checkbox werden diese Bloecke wirklich ausgeblendet.
- **AKZEPTANZ:**
  - Checkbox aus: Keine Roh-Mikro-Buchungen sichtbar (auch nicht als aufgeblasene korrigierte Zeiten/Extra-Zeilen).
  - Checkbox an: Mikro-Buchungen koennen sichtbar sein.
- **TESTS:**
  - `php -l views/report/monatsuebersicht.php`
- **NEXT:**
  - keine.


## P-2026-01-17-13
- ZIP: `P-2026-01-17-13_zusatz2-stundenkonto-prompt.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `070845_gesammt.zip` = `c315aa5653a822ac1d5b797582496e2d3de1adec407a6fd4dafcc7531747a90b`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `1915161d42170a4aa47e97c732b5160a64ab62d5faf576cb8af0838d9dde2008`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
  - Zusatz 1 (Scope alt, aus ZIP): `docs/archiv/zusatzpromt.md` = `1d80dc1a3718e7b495fbae5615840448ada0ad7a14281bb57618f11dc8bf4f58`
- **DUPLICATE-CHECK:**
  - Geprueft: `docs/archiv/zusatz2promt.md` existierte noch nicht.
- **DATEIEN (max. 3):**
  1) `docs/archiv/zusatz2promt.md`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neuer Zusatz-Prompt 2 erstellt: Stundenkonto (Gutstunden/Minusstunden) inkl. Strategie (Monatsaggregation), Datenmodell (Korrektur-Ledger + Batch), Backend/Terminal/PDF-Anforderungen und Micro-Patch-Plan.
- **AKZEPTANZ:**
  - `docs/archiv/zusatz2promt.md` ist im Projekt vorhanden und beschreibt die Stundenkonto-Umsetzung inkl. rueckwirkender Verteilungen so, dass eine LLM direkt danach implementieren kann.
- **TESTS:**
  - `sha256sum docs/archiv/zusatz2promt.md`
- **NEXT:**
  - Naechster Patch: DB-Migration fuer `stundenkonto_korrektur` + `stundenkonto_batch` und neues Recht `STUNDENKONTO_VERWALTEN` (danach Service + Terminal-Anzeige).



## P-2026-01-17-14
- ZIP: `P-2026-01-17-14_stundenkonto-migration-recht.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `155222_gesammt.zip` = `716366457bcaa8d169ea2c74e5f81bfc04364c6ab7aeae8995dc16a6dd465ff5`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7ed609a30314dbc83a5850800b0f176b5a33d04fd2a8432918b4acc1e92ed082`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `5d8a5925bc2cf0dff364e500996c42fdeefe5f0b953cead7807ae642b70e7fc1`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Geprueft: Keine vorhandenen Tabellen/SQL-Migrationen fuer `stundenkonto_batch`/`stundenkonto_korrektur`.
  - Geprueft: Recht `STUNDENKONTO_VERWALTEN` existierte in `docs/rechte_prompt.md` noch nicht.
- **DATEIEN (max. 3):**
  1) `sql/13_migration_stundenkonto.sql`
  2) `docs/rechte_prompt.md`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neue DB-Migration `sql/13_migration_stundenkonto.sql` erstellt:
    - legt Tabellen `stundenkonto_batch` und `stundenkonto_korrektur` an (idempotent),
    - seeded Recht `STUNDENKONTO_VERWALTEN` in `recht`,
    - weist das Recht der Rolle `Chef` zu (idempotent).
  - `docs/rechte_prompt.md` erweitert: neues Recht dokumentiert + Feature-Matrix um Stundenkonto ergaenzt.
- **AKZEPTANZ:**
  - Migration laeuft ohne Fehler durch und erzeugt die beiden Tabellen inkl. FKs/Indizes.
  - Recht `STUNDENKONTO_VERWALTEN` ist nach Migration vorhanden und bei Rolle `Chef` zugewiesen.
- **TESTS:**
  - `grep -R "stundenkonto_" -n sql/13_migration_stundenkonto.sql docs/rechte_prompt.md`
- **NEXT:**
  - Micro-Patch: `sql/01_initial_schema.sql` (SoT) um die beiden neuen Tabellen ergaenzen (damit Neuinstallationen konsistent sind).



## P-2026-01-17-15
- ZIP: `P-2026-01-17-15_stundenkonto-migration-renumber.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `164958_gesammt.zip` = `473abb05901d87052a4a6d49269d815249436cd70752d11bec2b6d86ab3f1dd3`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8f56b49675b2c24b81ce05ebf764dc2a8e94a03262077a991eb3f4688f29c0e7`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `64bba2e060402795cebddbf8c63a6bf8833aa10e71675c9ddac49a8f414c7e4f`
- **DUPLICATE-CHECK:**
  - Geprueft: `sql/21_migration_stundenkonto.sql` existierte noch nicht.
  - Geprueft: `sql/13_migration_stundenkonto.sql` kollidiert im Nummernschema (es gibt bereits Migrationen bis 20) → daher Kanonisch-File nachgezogen.
- **DATEIEN (max. 3):**
  1) `sql/21_migration_stundenkonto.sql`
  2) `sql/13_migration_stundenkonto.sql`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Stundenkonto-Migration zusaetzlich als `sql/21_migration_stundenkonto.sql` abgelegt (korrekte Reihenfolge/Nummerierung).
  - `sql/13_migration_stundenkonto.sql` als Legacy-Alias markiert (Inhalt bleibt identisch), damit alte Rollouts nicht brechen.
- **AKZEPTANZ:**
  - Im `sql/`-Ordner existiert die kanonische Migration `21_migration_stundenkonto.sql` und `13_migration_stundenkonto.sql` ist sichtbar als Legacy-Alias gekennzeichnet.
- **TESTS:**
  - `ls -1 sql/*stundenkonto*.sql`
  - `grep -n "Migration: 21" -n sql/21_migration_stundenkonto.sql`
- **NEXT:**
  - Micro-Patch: `sql/01_initial_schema.sql` (SoT) um `stundenkonto_batch` + `stundenkonto_korrektur` ergaenzen (Neuinstallation konsistent).



## P-2026-01-17-16
- ZIP: `P-2026-01-17-16_stundenkonto-sot-schema.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `171401_gesammt.zip` = `2d5de38dbe502370a1d75e4da9ac9ba6d09057bdafd25abbd0fd275f365de0d6`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `302560ac34ad4ec3c8317c7ee25f68c2444ab30c9602509cb784070c4c6582d8`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `b0088149d6f39bd7c0dc6e41dff197fea496ddcb7a3afca838140f6b50fd33de`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - Geprueft: `sql/01_initial_schema.sql` enthielt vor dem Patch keine Tabellen `stundenkonto_batch`/`stundenkonto_korrektur`.
- **DATEIEN (max. 3):**
  1) `sql/01_initial_schema.sql`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `sql/01_initial_schema.sql` (SoT) um `stundenkonto_batch` + `stundenkonto_korrektur` erweitert (FKs/Indizes analog zur Migration `sql/21_migration_stundenkonto.sql`).
- **AKZEPTANZ:**
  - Neuinstallation (Import von `sql/01_initial_schema.sql`) enthaelt die beiden Stundenkonto-Tabellen ohne weitere Migration.
- **TESTS:**
  - `grep -n "stundenkonto_" -n sql/01_initial_schema.sql`
- **NEXT:**
  - Micro-Patch: `services/StundenkontoService.php` (read-only) zum Berechnen von "Saldo bis Ende Vormonat" (Monatswerte + Korrekturen), damit Terminal/Report/PDF darauf aufbauen koennen.



## P-2026-01-17-17
- ZIP: `P-2026-01-17-17_stundenkonto-service-saldo.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `172942_gesammt.zip` = `242876d32667a72c0043095a595a9363d0ded5b7bd29d474aa375f54722a6f01`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `d582f6435b3193c98cbfe0f81ef73db8aa14a60a18f29e1227850dd6e84125f2`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - Geprueft: `services/StundenkontoService.php` existierte vor dem Patch nicht.
- **DATEIEN (max. 3):**
  1) `services/StundenkontoService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Neues Service `StundenkontoService` (read-only) erstellt:
    - `holeSaldoMinutenBisDatumExklusiv(mitarbeiterId, bisDatumIso)` -> Summe `delta_minuten` aus `stundenkonto_korrektur`.
    - `holeSaldoMinutenBisVormonat(mitarbeiterId, jahr, monat)` -> Stichtag = 1. Tag des Monats (exklusiv).
    - `formatMinutenAlsStundenString(minuten, mitVorzeichen)` -> Ausgabe als `+12.50` / `-3.25`.
  - Defensive Fehlerbehandlung: Wenn die Tabelle (Migration) noch fehlt, liefert das Service **0** und loggt eine Warnung (kein Fatal Error).
- **AKZEPTANZ:**
  - Controller/Views koennen das Service verwenden, ohne dass fehlende Stundenkonto-Tabellen einen 500er verursachen.
- **TESTS:**
  - `php -l services/StundenkontoService.php`
- **NEXT:**
  - Micro-Patch: `views/report/monatsuebersicht.php` nutzt `StundenkontoService` und zeigt "Gutstunden/Minusstunden (Stand bis Vormonat)" im Monatsreport an.


## P-2026-01-17-18
- ZIP: `P-2026-01-17-18_stundenkonto-report-html.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `174754_gesammt.zip` = `960cde04b9770a9498542ae597e4045a43033f3aa7c78368b1abbc0d12afc50f`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `aff0af925ec9211728ec24c55db6ece05d6a1c3967ca2db9a5099c56e376828d`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - Geprueft: `views/report/monatsuebersicht.php` enthielt vor dem Patch keine Stundenkonto-Anzeige und keinen Zugriff auf `StundenkontoService`.
- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - `views/report/monatsuebersicht.php`: Stundenkonto-Saldo bis Ende Vormonat wird ueber `StundenkontoService::holeSaldoMinutenBisVormonat()` berechnet.
  - Anzeige im Kopfbereich unter Soll/Ist/Differenz:
    - Label: `Gutstunden (Stand bis Vormonat)` oder `Minusstunden (Stand bis Vormonat)`
    - Wert: formatierte Stunden (z. B. `+12.50 h` / `-3.25 h`)
- **AKZEPTANZ:**
  - Auf `?seite=report_monat&jahr=YYYY&monat=MM` erscheint bei vorhandener Stundenkonto-Tabelle eine Zeile mit Gut-/Minusstunden (Stand bis Vormonat).
- **TESTS:**
  - `php -l views/report/monatsuebersicht.php`
- **NEXT:**
  - Micro-Patch: Terminal – Arbeitszeit-Uebersicht (`views/terminal/info_uebersicht.php`) zeigt Stundenkonto-Saldo (Stand bis Vormonat) an.
  - Danach: Monatsreport-PDF (`report_monat_pdf`) Summenblock um Stundenkonto erweitern.



## P-2026-01-17-19
- ZIP: `P-2026-01-17-19_stundenkonto-terminal-arbeitszeit.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `180611_gesammt.zip` = `dec11add749dd14efa24289a30a05fd8d7b5a7cad729d208d539b2312b0e4cf9`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `2c202d12a8316946c2471ee2c55c24c7ac0d2e4778bd53b5ae11377639b8aaeb`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - Geprueft: Terminal-Arbeitszeit-Uebersicht (`views/terminal/start.php` View `?aktion=start&view=arbeitszeit`) hatte vor dem Patch keine Stundenkonto-Zeile.
  - Hinweis: `views/terminal/info_uebersicht.php` ist laut Kommentar nicht aktiv genutzt; Anzeige erfolgt im Start-View.
- **DATEIEN (max. 3):**
  1) `controller/TerminalController.php`
  2) `views/terminal/start.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - TerminalController: berechnet Stundenkonto-Saldo *Stand bis Vormonat* via `StundenkontoService->holeSaldoMinutenBisVormonat()` und uebergibt an View.
  - Terminal Arbeitszeit-Übersicht-Seite: zeigt neue Zeile `Gutstunden/Minusstunden (Stand bis Vormonat)` inkl. Fehleranzeige.
- **AKZEPTANZ:**
  - In Terminal-UI (online) erscheint in der Arbeitszeit-Übersicht zusaetzlich zur Soll/Ist-Anzeige eine Zeile mit Gut-/Minusstunden (Stand bis Vormonat).
- **TESTS:**
  - `php -l controller/TerminalController.php`
  - `php -l views/terminal/start.php`
- **NEXT:**
  - Micro-Patch: Monatsreport-PDF (`report_monat_pdf`) Summenblock um Stundenkonto erweitern (Stand bis Vormonat, exklusiv aktueller Monat).


## P-2026-01-17-20
- ZIP: `P-2026-01-17-20_stundenkonto-pdf-saldo.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `182538_gesammt.zip` = `4a4cd74f6d726bc27eb7cbbdedb69cc76a2d7888df9378b7e8a7429978db1bee`
  - Master-Prompt (aus ZIP): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (aus ZIP, Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `0de669e7de9813df323743abe393ff99df02e00a633d24e4e65957a750380bc8`
  - Rechte-Prompt (aus ZIP): `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT, aus ZIP): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump, aus ZIP): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
- **DUPLICATE-CHECK:**
  - Geprueft: `services/PDFService.php` enthielt vor dem Patch keinen Zugriff auf `StundenkontoService` und keine Stundenkonto-Zeile im Summenblock.
- **DATEIEN (max. 3):**
  1) `services/PDFService.php`
  2) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Monatsreport-PDF: Summenblock zeigt zusaetzliche Zeile `Stundenkonto (bis Vormonat)`.
  - Wert wird ueber `StundenkontoService->holeSaldoMinutenBisVormonat(mitarbeiterId, jahr, monat)` berechnet und als `+/-XX.XX` Stunden ausgegeben.
- **AKZEPTANZ:**
  - Im PDF `report_monat_pdf` erscheint unten im Summenblock eine Zeile `Stundenkonto (bis Vormonat)` mit formatiertem Saldo.
- **TESTS:**
  - `php -l services/PDFService.php`
- **NEXT:**
  - Backend-Admin UI: Stundenkonto verwalten (minimal) – manuelle Korrektur (+/- Minuten) mit Begruendung erfassen (Recht `STUNDENKONTO_VERWALTEN`).

## P-2026-01-17-21
- ZIP: `P-2026-01-17-21_stundenkonto-backend-korrektur.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `183638_gesammt.zip` = `a2dfb3d5e2c8082b9602ffcb429687ee016fa00a3698ccd6ebaae9f035a218f4`
  - Master-Prompt (extern): `master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `167af7b4f646c91806324e258bc3f5cccd8c4ec4332ae7b012ab01f65a0b35db`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump): `zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`
- **DUPLICATE-CHECK:**
  - Geprueft: `MitarbeiterAdminController` + `views/mitarbeiter/formular.php` enthielten vor dem Patch keine Stundenkonto-Anzeige und kein separates Mini-Formular fuer Korrekturen.
- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `views/mitarbeiter/formular.php`
  3) `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend Mitarbeiter-Formular: Neuer Abschnitt **Stundenkonto** (nur bei bestehendem Mitarbeiter):
    - Anzeige `Saldo Stand heute` (Summe aller `stundenkonto_korrektur` bis < morgen).
    - Tabelle der letzten 10 Korrekturen (wirksam, delta, typ, begruendung, erstellt, von).
  - Manuelle Korrektur buchen (separates Mini-Formular):
    - POST auf `?seite=mitarbeiter_admin_speichern` mit Flag `stundenkonto_only=1`.
    - Zugriff nur mit Recht `STUNDENKONTO_VERWALTEN`.
    - Persistiert in `stundenkonto_korrektur` mit `typ='manuell'`, `wirksam_datum`, `delta_minuten`, `begruendung`, `erstellt_von_mitarbeiter_id`.
    - Flash-Feedback: Erfolg/Fehler im Formular.
- **AKZEPTANZ:**
  - In `mitarbeiter_admin_bearbeiten` ist der Abschnitt **Stundenkonto** sichtbar und zeigt Saldo + Korrektur-Historie.
  - Mit Recht `STUNDENKONTO_VERWALTEN` kann eine Korrektur gebucht werden; danach erscheint sie in der Liste und der Saldo aendert sich.
- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
  - `php -l views/mitarbeiter/formular.php`
- **NEXT:**
  - Batch-/Verteilbuchung (Stunden auf X Arbeitstage verteilen): DB-Logik ueber `stundenkonto_batch` + auto-generierte `stundenkonto_korrektur`-Zeilen (UI + Service) – als eigener Micro-Patch.



## P-2026-01-17-23_stundenkonto-monatsabschluss-buchen

- Monatsuebersicht: Button "Monatsabschluss buchen" (nur fuer vergangene Monate, Recht `STUNDENKONTO_VERWALTEN`).
- Bucht/aktualisiert die Monats-Differenz (Soll/Ist) als Eintrag in `stundenkonto_korrektur` (typ `manuell`, Begruendung `Monatsabschluss YYYY-MM`, Wirksamkeitsdatum = letzter Tag des Monats).
- Anzeige: berechnete Differenz, ggf. bereits gebuchte Differenz + Statusmeldungen (CSRF).

- **EINGELESEN (SHA256):**
  - `/mnt/data/191048_gesammt.zip`: `4d0033da4fd0e7813ddb7736f45cff5f79fdc775912db599c4cfaf9bbca1e767`
  - `docs/master_prompt_zeiterfassung_v10.md`: `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - `docs/DEV_PROMPT_HISTORY.md`: `fc8c20e2d5747f98f3bfe5bb396c4f877e31eb27414bee528c3f694169608bb6`
  - `docs/rechte_prompt.md`: `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - `sql/01_initial_schema.sql`: `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - `sql/zeiterfassung_aktuell.sql`: `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`

- **DUPLICATE-CHECK:**
  - Suche `Monatsabschluss` im Projekt: kein Treffer vor Implementierung (neuer Scope).

- **DATEIEN:**
  - `controller/ReportController.php`
  - `views/report/monatsuebersicht.php`
  - `docs/DEV_PROMPT_HISTORY.md`

- **TESTS:**
  - `php -l controller/ReportController.php`
  - `php -l views/report/monatsuebersicht.php`
- **NEXT:**
  - Optional: Quick-Flow fuer Vormonat (Dashboard/Report) – Hinweis, wenn Monatsabschluss fuer einen vergangenen Monat noch nicht gebucht ist (Button direkt ausloesen).


## P-2026-01-17-24_stundenkonto-audit-logs

- Stundenkonto: Erfolgs- und Fehler-Aktionen schreiben nachvollziehbar nach `system_log` (Kategorie `stundenkonto`).
- Logging erfolgt **zusätzlich** zu Flash/Redirect, ohne UI-Aenderung.

- **EINGELESEN (SHA256):**
  - `/mnt/data/193233_gesammt.zip`: `85b1daa9208f4bb5e1c6ebea75415177c66c845c0ee9e60f364ba1b491e9f897`
  - `docs/master_prompt_zeiterfassung_v10.md`: `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - `docs/DEV_PROMPT_HISTORY.md`: `06ffdc0bb4093d6a0ffc1f4b7c597b1eab2c7fed22990be4849dee98690de222`
  - `docs/rechte_prompt.md`: `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - `sql/01_initial_schema.sql`: `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - `sql/zeiterfassung_aktuell.sql`: `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`

- **DUPLICATE-CHECK:**
  - Geprueft: In den Stundenkonto-POST-Flows gab es bislang nur Error-Logs; Success-Logs fehlten.

- **DATEIEN (max. 3):**
  1) `controller/MitarbeiterAdminController.php`
  2) `controller/ReportController.php`
  3) `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - Backend-Admin: Manuelle Stundenkonto-Korrektur schreibt bei Erfolg `Logger::info(...)` inkl. Korrektur-ID, Delta, Datum, Begruendung, Actor.
  - Backend-Admin: Verteilbuchung (Batch) schreibt bei Erfolg `Logger::info(...)` inkl. Batch-ID, Zeitraum, Modus, Summe/Anzahl Tage, Actor.
  - Monatsuebersicht: Monatsabschluss buchen schreibt bei Erfolg `Logger::info(...)` (Insert + Update) und bei Fehler `Logger::error(...)`.

- **AKZEPTANZ:**
  - Nach erfolgreicher Korrektur/Batch/Monatsabschluss existiert ein Eintrag in `system_log` (Kategorie `stundenkonto`) mit den Meta-Daten.
  - Bei Fehlern im Monatsabschluss existiert ein Error-Log (Kategorie `stundenkonto`).

- **TESTS:**
  - `php -l controller/MitarbeiterAdminController.php`
  - `php -l controller/ReportController.php`

- **NEXT:**
  - Doku-Patch: `docs/rechte_prompt.md` – `STUNDENKONTO_VERWALTEN` von "geplant" auf aktiv (Pruefpunkte aktualisieren).



## P-2026-01-17-25_rechteprompt-stundenkonto-aktiv

- Doku: Recht `STUNDENKONTO_VERWALTEN` ist jetzt als **aktiv** dokumentiert (Inventar: "Im Code geprueft = JA") inkl. realer Pruefpunkte in Controller/Views.

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `194757_gesammt.zip` = `8f57e2dcb0ba3976a50a3c229ce56e584efff65a15bbff3b787258eb68ca42dd`
  - `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch) = `c5f4efb9d6c5db8dc13260534618be23bc6fa30c544a49e957911978ace50d60`
  - `docs/rechte_prompt.md` (Stand vor Patch) = `aa9b5331a35aef97a8d20c02183f98055b13b24287092c5fa8f1635d572fac37`
  - `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`
  - Zusatz (DB-Dump): `zeiterfassung_aktuell.sql` = `6ffbe22edafbe20bf6d8c95deaf442cc4e35ceec497531ed4ee8a3fbb561c138`

- **DUPLICATE-CHECK:**
  - Geprueft: In `docs/rechte_prompt.md` war `STUNDENKONTO_VERWALTEN` noch als "NEIN" markiert und die Sektion als "(geplant)" gefuehrt → Doku-Patch erforderlich.

- **DATEIEN (max. 3):**
  1) `docs/rechte_prompt.md`
  2) `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - `docs/rechte_prompt.md`: `STUNDENKONTO_VERWALTEN` ist als kanonisches Recht dokumentiert (Inventar: JA) inkl. realer Pruefpunkte (MitarbeiterAdminController/ReportController/View).

- **AKZEPTANZ:**
  - Rechte-Prompt zeigt `STUNDENKONTO_VERWALTEN` im Inventar als "Im Code geprueft: JA" und enthaelt reale Pruefpunkte/Dateien.

- **TESTS:**
  - (Docs-only)

- **NEXT:**
  - Monatsreport HTML/PDF: Block "Urlaubstage abzueglich geplante Betriebsferien" neben dem Stundenkonto-Block ergaenzen.



## P-2026-01-17-26_pdf-urlaub-betriebsferien-block

- Monatsreport-PDF: Summenbereich zeigt rechts Zusatzwerte fuer Urlaubstage (abzgl. geplante Betriebsferien) im Rest des Jahres.

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `200035_gesammt.zip` = `6d302230d6483e85d443d76bb32480c3e63b1e9396e34c8759235550d8cdad6a`
  - `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch) = `5fc66d786f66b759f299300fbdf6293c52e14421208a2f4228a56c327ea5de6b`
  - `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`

- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Der Urlaub-Betriebsferien-Block war als NEXT offen (Patch 25) und noch nicht DONE → Implementierung erforderlich.

- **DATEIEN (max. 3):**
  1) `services/UrlaubService.php`
  2) `services/PDFService.php`
  3) `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - `UrlaubService`: Neue Methode `zaehleBetriebsferienArbeitstageFuerMitarbeiter()` zaehlt Betriebsferien als Arbeitstage (Mo-Fr, ohne betriebsfreie Feiertage) fuer einen Zeitraum.
  - `PDFService`: Summenblock unten wurde leicht nach links verschoben; rechts daneben neuer Zusatzblock:
    - "Urlaubtage (abzgl. BF)" = `urlaub_verbleibend` minus Betriebsferien-Arbeitstage im Rest des Jahres (nach Monatsende bis 31.12.).
    - "BF (Rest Jahr)" = Anzahl der Betriebsferien-Arbeitstage im Rest des Jahres.

- **AKZEPTANZ:**
  - Monatsreport-PDF zeigt rechts neben den Summen die beiden Werte; Anzeige ist leer, wenn keine Monatswerte vorliegen.
  - Betriebsferien-Zaehler ignoriert Wochenenden und betriebsfreie Feiertage.

- **TESTS:**
  - `php -l services/UrlaubService.php`
  - `php -l services/PDFService.php`

- **NEXT:**
  - Monatsreport (HTML): Block "Urlaubstage (abzgl. geplante Betriebsferien)" neben dem Stundenkonto-Block ergänzen (Rest-Jahr). 



## P-2026-01-17-27_report-html-urlaub-abzgl-bf

- Monatsreport (HTML): Zusatzblock zeigt „Urlaubtage (abzgl. BF)“ + „BF (Rest Jahr)“ neben dem Stundenkonto (Rest des Jahres).

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `201551_gesammt.zip` = `1800d6fdd264ae582b247f23b6eb859b64a8d10e301d7537df787411499c0994`
  - `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch) = `643594f3db845efbbfff0152b4d73c806de005961cc576a93c52fe43e49f56d2`
  - `docs/rechte_prompt.md` (Stand vor Patch) = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`

- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Monatsreport (HTML) Urlaub/BF-Block war als **NEXT** (Patch 26) offen und noch nicht DONE → Implementierung erforderlich.

- **DATEIEN (max. 3):**
  1) `views/report/monatsuebersicht.php`
  2) `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - `views/report/monatsuebersicht.php`: Berechnet und zeigt (nur wenn Monatswerte vorliegen):
    - „Urlaubtage (abzgl. BF)“ = `urlaub_verbleibend` minus Betriebsferien-Arbeitstage im Rest des Jahres (nach Monatsende bis 31.12.).
    - „BF (Rest Jahr)“ = Anzahl der Betriebsferien-Arbeitstage im Rest des Jahres.
  - Berechnung defensiv (try/catch), damit es keine 500er gibt, wenn die Berechnung fehlschlaegt.

- **AKZEPTANZ:**
  - Monatsreport (HTML) zeigt die Werte im Summenbereich unter Soll/Ist/Differenz + Stundenkonto.
  - Keine Ausgabe, wenn keine Monatswerte vorhanden sind.

- **TESTS:**
  - `php -l views/report/monatsuebersicht.php`

- **NEXT:**
  - Stabilitaet: Backend – Monatsuebersicht + Monats-PDF klicken (T-069 Teil 2a/2b) und Bugs/Anomalien sammeln (danach Micro-Bugfix-Patches).


## P-2026-01-17-28_stabilitaet-microbuchungen-regression-note

- Stabilitaet: Regression „Mikro-Buchungen“ in Monatsuebersicht/PDF als Bug (B-078) dokumentiert, Fix folgt als Micro-Patch.

- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `202608_gesammt.zip` = `af5f7b232bdb71de30cd61723d0cc875914cd551d54c0e83ba7913ce7f62adb3`
  - `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - `docs/DEV_PROMPT_HISTORY.md` (Stand vor Patch) = `d4d2b2b0ced4de34ddad863255e5d324658ca311312a32dc75f2e0c28ab0e26b`
  - `docs/rechte_prompt.md` (Stand vor Patch) = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec`

- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Mikro-Buchungen wurden bereits in **P-2026-01-17-12** als ausgeblendet dokumentiert, sind aktuell aber wieder sichtbar (User-Screenshot). Es gab noch keinen B-ID-Eintrag fuer diese Regression → Dokumentation erforderlich.

- **DATEIEN (max. 3):**
  1) `docs/DEV_PROMPT_HISTORY.md`

- **DONE:**
  - SNAPSHOT: Neuer Bug **B-078** (Regression Mikro-Buchungen in Monatsuebersicht/PDF) aufgenommen.
  - NEXT konkretisiert: Nach Stabilitaets-Clickthrough ist **B-078** als erster Micro-Bugfix einzuplanen.

- **AKZEPTANZ:**
  - Im SNAPSHOT ist B-078 als OFFEN gelistet und der naechste Micro-Bugfix-Schritt referenziert diesen Bug.

- **TESTS:**
  - (Docs-only)

- **NEXT:**
  - Micro-Patch: Ursache finden (wo das Ausblenden wieder verloren ging) und Mikro-Buchungen in Monatsuebersicht + PDF wieder wie vorgesehen ausblenden.

---

## P-2026-01-18-02_report-pdf-urlaub-abzgl-bf-fix

### Pre-Flight Gate (Pflicht)
**EINGELESEN (SHA256):**
- INPUT ZIP: /mnt/data/033835_gesammt.zip = e9a1dab82629f9772d362590c32661a8a96e7c893b0927d32d13cf3b4b133ba9
- docs/master_prompt_zeiterfassung_v10.md = 343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6
- docs/DEV_PROMPT_HISTORY.md = 13e6ae3d4f0f769914330a5aa650ab75872c8b65dd4ef8b3c4bf136a472d9c97
- docs/rechte_prompt.md = 446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122
- sql/01_initial_schema.sql = 9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787

**Duplicate-Check:**
- SNAPSHOT/LOG enthaelt keine `P-2026-01-18-02` → OK

### Dateien (max 3)
1) `services/PDFService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### Ziel
Monatsreport-PDF: Urlaubsblock konsistent zum UrlaubService (Jahres-Resturlaub inkl. Betriebsferien), keine Doppel-Abzuege/negativen Werte.

### Umsetzung
- `PDFService.php`: `Urlaubtage (abzgl. BF)` = `UrlaubService->berechneUrlaubssaldoFuerJahr(...)[verbleibend]` (inkl. BF). `BF (Rest Jahr)` bleibt separat als Info.

### Test
- Backend → Monatsuebersicht → PDF erzeugen: Urlaubsblock rechts muss plausible positive Werte zeigen.

### Next
- T-069 Teil 2b: Backend-PDF weiter klicken, naechsten Bug als Micro-Patch.

---

## P-2026-01-18-03_report-html-urlaub-abzgl-bf-saldo

### Pre-Flight Gate (Pflicht)
**EINGELESEN (SHA256):**
- INPUT ZIP: /mnt/data/035700_gesammt.zip = ea732b4a257c98d41af6800e57331c4bdc55728d401dec9d1d9159dd0806a1ea
- docs/master_prompt_zeiterfassung_v10.md = 343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6
- docs/DEV_PROMPT_HISTORY.md = 4eb3677cd01154435d1e815514a1846d81adf123bd3ae2e76bae2c5553a961fd
- docs/rechte_prompt.md = 446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122
- sql/zeiterfassung_aktuell.sql = 742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec

**Duplicate-Check:**
- SNAPSHOT/LOG enthaelt keine `P-2026-01-18-03` → OK

### Dateien (max 3)
1) `views/report/monatsuebersicht.php`
2) `docs/DEV_PROMPT_HISTORY.md`
3) `docs/master_prompt_zeiterfassung_v10.md`

### Ziel
Monatsreport-HTML: Urlaubsblock konsistent zum PDF (UrlaubService-Jahres-Resturlaub inkl. BF), BF-Restjahr nur als Info; keine negativen Werte/Doppelabzug.

### Umsetzung
- `views/report/monatsuebersicht.php`: `Urlaubtage (abzgl. BF)` = `UrlaubService->berechneUrlaubssaldoFuerJahr(...)[verbleibend]` (inkl. BF). `BF (Rest Jahr)` bleibt separat via `zaehleBetriebsferienArbeitstageFuerMitarbeiter(...)` nach Monatsende bis 31.12.

### Test
- Backend → Monatsuebersicht: Urlaubsblock muss plausibel sein (kein negativer Urlaub) und zu PDF passen.

### Next
- T-069 Teil 2a/2b weiter: Monatsuebersicht + Monats-PDF klicken und naechste Bugs als Micro-Patches.
- Bei Gelegenheit: B-080 Urlaubsberechnung/Anzeige „Mein Urlaub“ nochmal pruefen (BF/Feiertag/Workday).


---

## P-2026-01-18-04_urlaub-meine-bf-restjahr-label

### Pre-Flight Gate (Pflicht)
**EINGELESEN (SHA256):**
- INPUT ZIP: /mnt/data/041314_gesammt.zip = ae7cbe0af3bcae3502dae27f823fd5cee2bf9d38819e5cc7bd4286cdaf246032
- docs/master_prompt_zeiterfassung_v10.md = 343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6
- docs/DEV_PROMPT_HISTORY.md = b812f6ba6b7b52c113e86955f27f6e1132e30491139e7fcd9d5ec617bd5feae4
- docs/rechte_prompt.md = 446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122
- sql/zeiterfassung_aktuell.sql = 742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec

**Duplicate-Check:**
- SNAPSHOT/LOG enthaelt keine `P-2026-01-18-04_urlaub-meine-bf-restjahr-label` → OK

### Dateien (max 3)
1) `views/urlaub/meine_antraege.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### Ziel
Urlaub-UI ("Mein Urlaub"): Anzeige transparent machen, weil Betriebsferien automatisch als Urlaub in "Genehmigt" einfließen.

### Umsetzung
- `views/urlaub/meine_antraege.php`:
  - Label angepasst: "Gesamt übrig (abzgl. BF)" und "Genehmigt (inkl. BF)".
  - Zusatzinfo: `BF (Rest Jahr)` wird aus `UrlaubService->zaehleBetriebsferienArbeitstageFuerMitarbeiter(...)` (ab heute bis 31.12.) berechnet und angezeigt.

### Test
- Backend → Mein Urlaub: Kopfbox muss `BF (Rest Jahr)` anzeigen und die Labels muessen klar sein.

### Next
- T-069 weiter: Monatsuebersicht + Monats-PDF weiter klicken und naechste Bugs als Micro-Patches.

---

## P-2026-01-18-05_report-urlaub-abzgl-bf-clamp0

### Pre-Flight Gate (Pflicht)
**EINGELESEN (SHA256):**
- INPUT ZIP: /mnt/data/042611_gesammt.zip = 4874c7c3583366cce074996d90e8b66143c13cbefeab39ca5dd1c4b039aa4339
- docs/master_prompt_zeiterfassung_v10.md = 343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6
- docs/DEV_PROMPT_HISTORY.md = 27fcbb1eda70418263432652aadd816ffd5b9bea7883f19701e3e795c5eeaff9
- docs/rechte_prompt.md = 446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122
- sql/zeiterfassung_aktuell.sql = 742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec

**Duplicate-Check:**
- SNAPSHOT/LOG enthaelt keine `P-2026-01-18-05` → OK

### Dateien (max 3)
1) `services/PDFService.php`
2) `views/report/monatsuebersicht.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### Ziel
Monatsreport (PDF + HTML): Im Urlaubsblock darf "Urlaubtage (abzgl. BF)" nie negativ angezeigt werden (Minimum 0.00).

### Umsetzung
- `PDFService.php`: Anzeige clamped auf Minimum 0.00 (nur Darstellung, keine Datenmutation).
- `views/report/monatsuebersicht.php`: Anzeige clamped auf Minimum 0.00.

### Test
- Backend → Monatsuebersicht (HTML) + Monatsreport-PDF: "Urlaubtage (abzgl. BF)" darf nicht negativ erscheinen.

### Next
- Bei Gelegenheit: Ursache fuer negative Salden pruefen (haeufig: Urlaubsanspruch=0 bei neuem Mitarbeiter) und ggf. Hinweis/Link zur Stammdatenpflege ergaenzen.
- Urlaubsberechnung/Anzeige weiter beobachten (User-Hinweis: wirkt teils unplausibel) und als eigenen Micro-Bug aufnehmen, sobald reproduzierbar.


---

## P-2026-01-18-07_urlaub-bf-skip-iststunden

### Pre-Flight Gate (Pflicht)
**EINGELESEN (SHA256):**
- INPUT ZIP: /mnt/data/053239_gesammt.zip = d146902a147410fde64fd9f870604f203fa9c02ee1e2048e8a9977a016508429
- docs/master_prompt_zeiterfassung_v10.md = 343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6
- docs/DEV_PROMPT_HISTORY.md = e86bd8f596d6cece85e9e8a00b6f8eff1021432cc2dc4f5b4888884483e71f91
- docs/rechte_prompt.md = 446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122
- sql/zeiterfassung_aktuell.sql = 742b2d0d00bd86acc8240450e618defbd15d9acd5b788e4da058527af34a0cec

**Duplicate-Check:**
- SNAPSHOT/LOG enthaelt keine `P-2026-01-18-07` → OK

### Dateien (max 3)
1) `services/UrlaubService.php`
2) `docs/DEV_PROMPT_HISTORY.md`

### Ziel
B-080 (Teilfix): Urlaubs-/Betriebsferien-Saldo war unplausibel, weil die Skip-Logik fuer Betriebsferien in `UrlaubService` teilweise nicht griff.

Hauptursache:
- Query auf `tageswerte_mitarbeiter` nutzte die Spalte `arbeitszeit_stunden`, die im Schema/SoT nicht existiert (`ist_stunden` ist korrekt) → Skip-Set blieb leer, BF wurde zu oft als Urlaub gezählt.

Zusaetzlich:
- Der Zaehler `zaehleBetriebsferienArbeitstageFuerMitarbeiter()` (BF Rest Jahr) soll **dieselbe** Skip-Logik verwenden (Arbeit/Kennzeichen/Krankzeitraum).

### Umsetzung
- `UrlaubService.php`:
  - `tageswerte_mitarbeiter`: nutzt `ist_stunden AS arbeitszeit_stunden` (kompatibel zur bestehenden Logik) und erweitert die Kennzeichen um `kennzeichen_urlaub`.
  - `zaehleBetriebsferienArbeitstageFuerMitarbeiter()` bekommt ein Skip-Set aus:
    - Tageswerten (hat gearbeitet oder andere Kennzeichen)
    - aktiven Krankzeitraeumen (LFZ/KK) fuer BF-Arbeitstage

### Test
- Backend → Mein Urlaub:
  - Keine SQL-Fehler (Logger) rund um `tageswerte_mitarbeiter`.
  - `BF (Rest Jahr)` wirkt plausibel (BF-Tage mit Arbeit/Kennzeichen/Krankzeitraum werden nicht abgezogen).
- Monatsuebersicht/PDF:
  - Urlaubssaldo (abzgl. BF) konsistenter zu „Mein Urlaub“ (kein systematischer Drift durch leeres Skip-Set).

### Next
- B-080 weiter: Wenn noch Abweichungen auftreten, konkret mit Screenshot + DB-Daten (Urlaubsantraege, Tageswerte, Krankzeitraum) reproduzieren und als Micro-Bug fixen.

---

## Patch P-2026-01-18-11_mitarbeiter-eintrittsdatum-ui

### Dateien (max 3)
1) `controller/MitarbeiterAdminController.php`
2) `views/mitarbeiter/formular.php`
3) `docs/DEV_PROMPT_HISTORY.md`

### Ziel
Eintrittsdatum im Mitarbeiter-Admin pflegbar machen, damit die Urlaubslogik (Eintrittsjahr/Anspruch) sauber auf einem expliziten Datum basiert (statt auf `erstellt_am`).

### Umsetzung
- UI: Feld `Eintrittsdatum` in `views/mitarbeiter/formular.php` (Stammdaten).
- Save: `MitarbeiterAdminController::speichern()` liest `eintrittsdatum` aus POST und speichert es separat per `UPDATE mitarbeiter SET eintrittsdatum = :dt` (soft-fail, falls Spalte noch nicht existiert).

### Test
- Mitarbeiter bearbeiten/anlegen: Datum setzen → speichern → erneut oeffnen → Datum bleibt erhalten.

### Next
- SoT: `sql/01_initial_schema.sql` um `mitarbeiter.eintrittsdatum` ergaenzen.

---

## P-2026-01-18-12
- ZIP: `P-2026-01-18-12_sot-initialschema-eintrittsdatum.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `065434_gesammt.zip` = `a22363d1cfd21d1851ef5e5ad53e3dcda1f29f5badf60a07a7095eb4b957bb48`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v10.md` = `343ad4dc429aade2ca8d56b966fe52228e18050b129004f6212fc4b4a83122f6`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7971936a0abf0988ea084c0f0a26d8981f3ac7a7879fdaa7d9bf13ccd4c36e5a`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `9c43bd846fe5ab0aa58c543809db3683f4a4bed8b73879f437c1163d53757787`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `d1013e2d5a1a37514b0b9ce14c0a291b0f0b1dfda4125f93952d8054c862049c`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Migration 22 existiert, aber SoT `sql/01_initial_schema.sql` enthaelt `mitarbeiter.eintrittsdatum` noch nicht.
- **DATEIEN (max. 3):**
  - `sql/01_initial_schema.sql`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - SoT: `mitarbeiter.eintrittsdatum` (DATE NULL) im Initialschema aufgenommen (nach `geburtsdatum`).
  - Akzeptanz: Eine Neuinstallation per `sql/01_initial_schema.sql` enthaelt die Spalte `mitarbeiter.eintrittsdatum`.
- **NEXT:**
  - Stabilitaet: Backend – Monatsuebersicht + Monats-PDF klicken und naechste Bugs als Micro-Patches.

---

## P-2026-01-18-24
- ZIP: `P-2026-01-18-24_backend-auftrag-detail-arbeitsschritt.zip`
- **DATEIEN (max. 3):**
  - `controller/AuftragController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Backend: Auftrag-Detail zeigt `arbeitsschritt_code` je Buchung (falls vorhanden).
  - Backend: Zusatz-Tabelle "Arbeitsschritte (Summe, abgeschlossen)" inkl. Buchungsanzahl + Stunden-Summe pro Arbeitsschritt.
  - Hinweistext angepasst (Arbeitsschritt-Code wird bereits angezeigt, wenn beim Auftrag-Start erfasst).
- **NEXT:**
  - Terminal-UX: "Auftrag starten" ausblenden, wenn bereits ein Hauptauftrag laeuft (nur "Auftrag stoppen" anzeigen), um Doppelstarts zu vermeiden.
  - Maschine: Barcode/QR-Generator (JPG/PNG) pro Maschine + Anzeige/Download im Backend.

## P-2026-01-18-25
- ZIP: `P-2026-01-18-25_terminal-nebenauftrag-arbeitsschritt.zip`
- Terminal: **Nebenauftrag starten** unterstuetzt jetzt optional `arbeitsschritt_code`.
  - Neues Feld in `views/terminal/start.php` (Nebenauftrag-Startformular).
  - Speicherung in `auftragszeit.arbeitsschritt_code` (online via UPDATE nach Insert, offline direkt im INSERT).
- Akzeptanz:
  - Nebenauftrag starten speichert Arbeitsschritt-Code (wenn angegeben).
  - Keine Regressionen am Startscreen / vorhandenen Buttons.

## P-2026-01-18-26
- ZIP: `P-2026-01-18-26_terminal-nebenauftrag-offline-auftrag-ensure.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `155149_gesammt.zip` = `251e2378368f64489c8310514dc2e78f78b24fbdafbfba5ea4152e9b01d163cc`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `484b55b284c0d3e49a4e2e8c30c7d95287fb34d21e3553ccc9105fc2353d40be`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Zusatz (Auftrags-Scope): `docs/docs/archiv/auftrags_prompt_v1.md` = `35142adf8d89ca7df88472aa50186da5c659a29928754ffc3354259ce54467cc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-26` vorhanden → OK
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
- **DONE:**
  - Terminal (Nebenauftrag starten): Im Offline-Queue-Pfad wird jetzt **vor** dem `nebenauftrag_start` ein idempotentes `auftrag_ensure` in die Queue geschrieben, das `auftrag(auftragsnummer)` anlegt (oder bestaetigt).
  - Terminal (Nebenauftrag starten, online): Wenn `auftrag` noch nicht existiert, wird ein Minimaldatensatz idempotent angelegt und `auftrag_id` nachgeladen.
- **NEXT:**
  - Terminal-UX: Hauptauftrag-Start-Button ausblenden, wenn bereits ein Hauptauftrag laeuft (nur Stop zeigen) – Doppelstarts vermeiden.






## P-2026-01-18-36
- ZIP: `P-2026-01-18-36_hauptauftrag-stop-ohne-autostop-nebenauftrag.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `194342_gesammt.zip` = `2d775f5cc275585d383d007e31352bef8133f23a1127de2e80df191db186bf50`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (Stand vor Patch): `docs/dev_prompt_zeiterfassung_v12.md` = `67db39c5d74b03fad6fec66ec78be13fad316732ce774a6aec75bf249582d83c`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `50658e26e033c4f96991f69892014e7e4d95b8e4f1b1cb1b195d8aada8d571fe`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-36` vorhanden → OK
- **DATEIEN (max. 3):**
  - `services/AuftragszeitService.php`
  - `controller/TerminalController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Praxis-Fix: **Hauptauftrag Start/Stop** beendet **keine Nebenauftraege automatisch** mehr.
    - Online: Auto-Close Calls entfernt.
    - Offline: Queue-Updates fuer Nebenauftraege entfernt.
    - Terminal: Session-State fuer Nebenauftrag wird beim Hauptauftrag-Start/Stop nicht mehr zurueckgesetzt.
  - Text/UX: „Auftrag wurde gestoppt.“ (statt „beendet“).
- **NEXT:**
  - Praxis-Test: Wenn Start/Stop-Logik bei gleichzeitigen Buchungen unklar wird → UX-Regel festziehen (nur nach Bedarf).


## P-2026-01-18-35
- ZIP: `P-2026-01-18-35_auto-stop-nebenauftrag.zip`
- **DONE (kurz):**
  - Auto-Stop Nebenauftrag beim Hauptauftrag-Stop/Wechsel.
  - Praxis-Test zeigte: unerwuenscht → Rueckgaengig in **P-2026-01-18-36**.


## P-2026-01-18-34
- ZIP: `P-2026-01-18-34_terminal-nebenauftrag-requires-hauptauftrag.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `192434_gesammt.zip` = `bffa6fed6a78370d3228cd402e81cff9bfa23c7816f43177662017761c97de7c`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (Stand vor Patch): `docs/dev_prompt_zeiterfassung_v12.md` = `1782b82ef69728b8a50acd5d1c74820e2678c2c44dbed4ddb17ff646b2b604dc`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `88f821a6d625786b590ee4f588b77aa9590844698fdb5ed3fe3ae32cd8120534`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-34` vorhanden → OK
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `docs/dev_prompt_zeiterfassung_v12.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: **Nebenauftrag starten** ist jetzt **serverseitig** geblockt, wenn **kein Hauptauftrag** laeuft.
    - Online: Pruefung ueber laufende `auftragszeit` (typ='haupt').
    - Offline: Fallback ueber Session-Merker `terminal_letzter_auftrag`.
- **NEXT:**
  - Praxis-Test: Edgecases/UX nur bei Bedarf/Bug.


## P-2026-01-18-33
- ZIP: `P-2026-01-18-33_docs-auftrags-prompt-devhistory.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `191528_gesammt.zip` = `823a7f056207c951ade5d46722c9acae1184321e84810987921ffe021362c533`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7ac1db1943b5701671f32296484e75e7fa1fbc98b6ab832fdd937485c7341a81`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Zusatz (Auftrags-Prompt): `docs/archiv/auftrags_prompt_v1.md` = `35142adf8d89ca7df88472aa50186da5c659a29928754ffc3354259ce54467cc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-33` vorhanden → OK
- **DATEIEN (max. 3):**
  - `docs/archiv/auftrags_prompt_v1.md`
  - `docs/dev_prompt_zeiterfassung_v12.md`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Doku: `docs/archiv/auftrags_prompt_v1.md` im Projektstand abgelegt.
  - Dev-Prompt: Auftragsmodul-Stand kurz aktualisiert.
  - History: P-2026-01-18-31/32 nachgetragen + SNAPSHOT aktualisiert.
- **NEXT:**
  - Weiter am Auftragsmodul nur nach Bedarf/Bug (Praxis-Test).


## P-2026-01-18-32
- ZIP: `P-2026-01-18-32_terminal-scanflow-submit.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `190631_gesammt.zip` = `b64b9dd95bb9d93e870f4e1042f356bbc6c06a4e6f06c4cd94e980f2e362ea03`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7ac1db1943b5701671f32296484e75e7fa1fbc98b6ab832fdd937485c7341a81`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-32` vorhanden → OK (nachgetragen; Patch wurde ohne History ausgeliefert)
- **DATEIEN (max. 3):**
  - `views/terminal/auftrag_starten.php`
  - `views/terminal/start.php`
- **DONE:**
  - Terminal: Scan-Flow verbessert – **Enter im Maschinenfeld** sendet jetzt das Formular ab (Maschine bleibt optional).
  - Ziel: Auftrag → Arbeitsschritt → Maschine (optional) → Enter = Start.
- **NEXT:**
  - Optional: serverseitige Guardrails/Edgecases nach Praxis-Test.


## P-2026-01-18-31
- ZIP: `P-2026-01-18-31_terminal-maschine-scan-parse.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `184722_gesammt.zip` = `38bfa953ef5b2fdc6cb0bde27f97506c119c90baec528e09a6141a891fbe97a4`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `7ac1db1943b5701671f32296484e75e7fa1fbc98b6ab832fdd937485c7341a81`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-31` vorhanden → OK (nachgetragen; Patch wurde ohne History ausgeliefert)
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `views/terminal/auftrag_starten.php`
  - `views/terminal/start.php`
- **DONE:**
  - Terminal: Maschinenfeld ist scanfreundlich (Textfeld). Es wird tolerant die **erste Zifferngruppe** aus dem Scan als `maschine_id` uebernommen.
  - Maschine bleibt optional; reine ID reicht, Scan mit Prefix funktioniert trotzdem.
- **NEXT:**
  - Scan-Flow: Enter im Maschinenfeld = Submit (P-2026-01-18-32).
## P-2026-01-18-30
- ZIP: `P-2026-01-18-30_maschine-barcode-generator.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `165119_gesammt.zip` = `38eea1ed64ba1af41c261ed9b079819d022c97b7cb2e019b40050f779b586007`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `62c40ee567bdad196fd6712d727315370d3be3bbc760f5551631f3f1af210070`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-30` vorhanden → OK
- **DATEIEN (max. 3):**
  - `public/maschine_code.php`
  - `controller/MaschineAdminController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Maschinen-Backend: Pro Maschine wird ein druck-/scanbarer **Code-39 Barcode** fuer die Maschinen-ID generiert (SVG, optional PNG via GD).
  - Maschinen-Formular: Anzeige + Downloadlinks (SVG/PNG) fuer die Maschinen-ID.
- **Akzeptanz:**
  - Im Maschinen-Edit-Formular (nur wenn ID vorhanden) erscheint ein Barcode-Bereich mit Bild + Downloadlinks.
- **NEXT:**
  - Terminal (optional): Maschine scannen/auswaehlen beim Auftragstart (nur wenn wirklich benoetigt).


## P-2026-01-18-29
- ZIP: `P-2026-01-18-29_terminal-arbeitsschritt-required.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `164056_gesammt.zip` = `c2a3679853cc9026d22b26a7c21eaa7ba1297110fa8e5ce726c0212741b829a4`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `62c40ee567bdad196fd6712d727315370d3be3bbc760f5551631f3f1af210070`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-29` vorhanden → OK (nachgetragen; Patch wurde bereits integriert, aber ohne Log-Eintrag)
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `views/terminal/auftrag_starten.php`
  - `views/terminal/start.php`
- **DONE:**
  - Terminal: Hauptauftrag starten – Arbeitsschritt-Code ist Pflicht (Server-Validierung + Formular).
- **NEXT:**
  - Maschinen-Backend: Barcode/QR-Generator (PNG/JPG) pro Maschine + Anzeige/Download (separater Micro-Patch).



## P-2026-01-24-01
- ZIP: `P-2026-01-24-01_dashboard-zeitwarnungen-zeitraum.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `042602_gesammt.zip` = `caf88a4633deb81e84549b9b37ab3e6e1ec316b36f1d3d4acfa1ee2dcac19de0`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `78933f600a4a1762163adf00cccf2978691f123dedc9bbd3fe235fb220b3c90a`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-24-01` vorhanden → OK
- **ZIEL:**
  - Dashboard: Hinweis „Unvollstaendige Zeitbuchungen“ soll nicht einfach verschwinden, nur weil die betroffenen Tage etwas aelter als 14 Tage sind.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `views/dashboard/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Dashboard-Pruefzeitraum ist jetzt **konfigurierbar** (Config-Key `dashboard_zeitwarnungen_tage`) und hat Default **31 Tage**.
  - SQL nutzt Startdatum-Param (statt fixe `DATE_SUB(..., 14 DAY)`).
  - UI-Label zeigt dynamisch „letzte X Tage“.
- **TEST:**
  - Backend: `?seite=dashboard` → Hinweis erscheint wieder, wenn Unstimmigkeiten im Zeitraum existieren.
- **NEXT:**
  - Optional: Config-Key in `Konfiguration` UI als Default vorbefuellen/anzeigen (separater Micro-Patch, falls gewuenscht).

## P-2026-01-24-02
- ZIP: `P-2026-01-24-02_dashboard-zeitwarnungen-db-tzfix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `043612_gesammt.zip` = `9b9f4b7cabede63b0fc2104d5fb9378fbc18808d38f12912b4de54715676a3ed`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `655297cd25fed82f79365dbc583bc31ff02ac529bab4baa22bc689dc2c685095`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-24-02` vorhanden → OK.
- **ZIEL:**
  - Dashboard-Zeitwarnung muss "gestern" sicher finden, auch wenn die DB-Session/Server-Zeitzone (z. B. UTC) von PHP abweicht.
- **DONE:**
  - `controller/DashboardController.php`: `CURDATE()` im HAVING entfernt und durch `:today_date` ersetzt (aus PHP/Europe/Berlin).
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **TEST:**
  - Backend: `?seite=dashboard` → Unstimmigkeit von "gestern" erscheint auch dann, wenn MySQL `CURDATE()` noch "gestern" liefern wuerde.
- **NEXT:**
  - Optional: Aehnliche CURDATE()/NOW()-Abhaengigkeiten in weiteren Admin-Checks vermeiden (nur wenn nochmal auffaellig).


## P-2026-01-24-04
- ZIP: `P-2026-01-24-04_dashboard-zeitwarnungen-kommen-gehen-diff.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `045508_gesammt.zip` = `39d254df9ac93cba5620468b343c5934578fdebe8f08d0b213a8657fe12c71ca`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `148cf16dfd671738ffc0e03adac29dc07b0624f7745dbc3b7227b861e07f0f03`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-24-04` vorhanden → OK.
- **ZIEL:**
  - Dashboard-Zeitwarnungen sollen auch dann anschlagen, wenn die Anzahl **Kommen != Gehen** ist, obwohl die Gesamtanzahl der Stempel **gerade** ist (z. B. doppelt „Kommen“ ohne „Gehen“).
- **DONE:**
  - `controller/DashboardController.php`: SQL-HAVING prueft jetzt `anzahl_kommen <> anzahl_gehen` (statt nur `COUNT(*) % 2 = 1`).
  - `views/dashboard/index.php`: Hinweistext von „ungerade Anzahl“ auf „ungleiche Anzahl“ angepasst.
  - Nacht-/Schicht-Heuristik bleibt aktiv (Grenzfaelle um Mitternacht werden weiter gefiltert).
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `views/dashboard/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **TEST:**
  - Beispiel: 2x „Kommen“ und 0x „Gehen“ am gleichen Tag → Dashboard zeigt den Hinweis.
- **NEXT:**
  - Optional: Im Dashboard-Dropdown (Details) die Roh-Stempelanzahlen mit ausgeben (nur falls zur Diagnose gewuenscht).
## P-2026-01-24-08
- ZIP: `P-2026-01-24-08_dashboard-zeitwarnungen-sql-no-placeholders.zip`
- Input: `gesammt.zip` (sha256: `cc9976fedce101d4a8f0248f9a8354a42f9f6c8e9c2d19b481bdc2b8c8232109`)
- Referenzen:
  - `docs/master_prompt_zeiterfassung_v12.md`
  - `docs/dev_prompt_zeiterfassung_v12.md`

### Problem
- Zeitwarnungen wurden auf dem Dashboard nicht gerendert, obwohl die Query in phpMyAdmin Treffer liefert.

### Ursache
- In der Praxis lieferte die per PDO vorbereitete Query (mit `:start_date`/`:today_date`) auf MariaDB teils leere Resultsets, obwohl dieselbe Query ohne Placeholder Daten liefert.

### Fix
- Dashboard: Zeitwarnungen-Query wird nun ohne PDO-Placeholder ausgefuehrt (Inline ISO-Datum-Strings aus PHP), orientiert am funktionierenden phpMyAdmin-Test.

### Geaenderte Dateien
- `controller/DashboardController.php`
- `docs/DEV_PROMPT_HISTORY.md`

### Tests
- Dashboard aufrufen als Chef/Admin: Zeitwarnungen-Box muss erscheinen, sobald ein Tag in der Vergangenheit unvollstaendige Kommen/Gehen-Stempel hat.

---

## P-2026-01-25-01
- ZIP: `P-2026-01-25-01_dashboard-zeitwarnungen-groupby-sqlmode-log.zip`
- Problem: Dashboard zeigt keine Zeitwarnungen, obwohl die Abfrage in phpMyAdmin Treffer liefert.
- Fix:
  - Zeitwarnungen-Query: `GROUP BY` erweitert (`m.vorname`, `m.nachname`) und `ORDER BY` auf Nachname/Vorname umgestellt, damit sie auch unter `ONLY_FULL_GROUP_BY`/strikten SQL-Modes laeuft.
  - Wenn dennoch ein SQL-Fehler auftritt, wird er ins `error_log` geschrieben und im Dashboard als roter Hinweis angezeigt.
- Geaenderte Dateien:
  - `controller/DashboardController.php`
  - `views/dashboard/index.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- Tests:
  - `?seite=dashboard` (Chef/Admin): Zeitwarnungen muessen erscheinen, sobald es einen Tag < heute mit ungleichen Kommen/Gehen-Stempeln gibt.
  - Bei SQL-Fehler: roter Hinweis im Dashboard + `error_log` Eintrag.

---

## P-2026-01-24-07
- ZIP: `P-2026-01-24-07_dashboard-zeitwarnungen-fetchEine-fix.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `052443_gesammt.zip` = `a1f04ed0d88e3d293cf844371931c791cf6adb51d8732a0b9e59cd99819198f1`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `ac206376944d77b8c370589678d2eee4908750115c5bb60f654b942bbf045748`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-24-07` vorhanden → OK.
- **URSACHE:**
  - Dashboard-Zeitwarnungen wurden nicht angezeigt, weil in `DashboardController` versehentlich `Database::fetchEinzel(...)` aufgerufen wurde (Methode existiert nicht). Der Fehler wurde im `try/catch` geschluckt → Ergebnis: `$zeitUnstimmigkeiten` blieb leer.
- **DONE:**
  - `controller/DashboardController.php`: `fetchEinzel(...)` → `fetchEine(...)` (DB-Wrapper-Methode existiert) in der Nachtschicht-Grenzfall-Pruefung.
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **TEST:**
  - `php -l controller/DashboardController.php`
  - `?seite=dashboard` → Bei einem Tag mit FEHLT (Kommen/Gehen unausgeglichen, nicht heute) muss der Warnblock wieder sichtbar sein.
- **NEXT:**
  - Optional: Im Catch der Zeitwarnungs-Query ein `error_log(...)` schreiben (nur Diagnose), falls es nochmal „still“ ausfaellt.

## P-2026-01-24-05
- ZIP: `P-2026-01-24-05_dashboard-zeitwarnungen-nachtschicht-nicht-ausfiltern.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `050955_gesammt.zip` = `73422a7e1cd6170e447d3e47c9b29ad11f8fc2ef7c9add7d5f5e3c4567d127fa`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `8d424e3bc18d9db2bc6402cfe8bc54e1fc8bbf937d169343b6f52904e499497e`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `6d3e4cacc59f9de75226f00945314ff338c3ed4d29bfc0f18e7e052ee56ad9cc`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-24-05` vorhanden → OK.
- **URSACHE:**
  - Zeitwarnungen wurden im Dashboard als moeglicher Nachtschicht-Grenzfall erkannt und dadurch komplett unterdrueckt → dadurch kein Hinweis, obwohl im Monatsreport „FEHLT“ sichtbar ist.
- **DONE:**
  - `controller/DashboardController.php`: Nachtschicht-Grenzfaelle werden nicht mehr per `continue` ausgefiltert; Eintraege bleiben im Warn-Block und bekommen ein Flag `nachtshift_grenzfall` (optional fuer spaetere UI-Label).
- **DATEIEN (max. 3):**
  - `controller/DashboardController.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **TEST:**
  - `php -l controller/DashboardController.php`
  - `?seite=dashboard` → Warnhinweis erscheint auch bei Kommen spaet / Gehen frueh Grenzfall.
- **NEXT:**
  - Optional: Im Dashboard ein kleines Label „(Nachtschicht?)“ anzeigen, wenn `nachtshift_grenzfall=1`.

## P-2026-01-23-02
- ZIP: `P-2026-01-23-02_urlaub-bf-saldo-sync.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `195930_gesammt.zip` = `e4afcb87fc42a9bdb5892cf4c218cb774c4dd4dc24fc280018ba1d5f8c12417c`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `99d6ddd4c08b9b71994a9aacad3de5308a0a9d57f8fbba9c52ca3f5075b14eff`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `fbd76757a86b9a74ad19a34b5b6d93285d8a8a41e9b2b660135c15ce65c40c63`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8cd0bdff5e6aa29217d66f3252b8184d8a54265e7f4cafb7a33a75e7cf63ff0b`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-23-02` vorhanden → OK.
  - Hinweis: Einige bereits erstellte Urlaubs-/Report-Patches (u. a. `P-2026-01-23-01`) sind im Projektstand enthalten, aber in diesem History-File noch nicht nachgetragen.
- **ZIEL:**
  - Urlaubssaldo: **Genehmigt/Verfuegbar** darf nicht von den **Betriebsferien-„benoetigte Urlaubstage“** abweichen (z. B. 9.50 vs 10.00).
- **DONE:**
  - `services/UrlaubService.php`: Betriebsferien-Abzug im Urlaubs-Saldo wird jetzt ueber die zentrale Funktion `zaehleBetriebsferienArbeitstageFuerMitarbeiter(...)` berechnet (gleiche Logik wie die Anzeige pro Betriebsferien-Block).
- **DATEIEN (max. 3):**
  - `services/UrlaubService.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **TEST:**
  - `php -l services/UrlaubService.php`
- **NEXT:**
  - Optional: Unbenutzte/duplizierte Betriebsferien-Skip-Berechnung in `berechneUrlaubssaldoFuerJahr()` aufraeumen (Performance, keine Logikaenderung).


## P-2026-01-18-28
- ZIP: `P-2026-01-18-28_terminal-nebenauftrag-offline-state.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `162454_gesammt.zip` = `beb39cac25ee8bd3c725f0be65397d0e9302084b24c5d372cd02c032b4cb9002`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `8dc41ce64909e09523ae0e12b2250638a4eddcc1238298d925a3e6f85bb49dd1`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Kein Eintrag `P-2026-01-18-28` vorhanden → OK
- **DATEIEN (max. 3):**
  - `controller/TerminalController.php`
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal: Nebenauftrag Start/Stop setzt einen Session-Zaehler `terminal_nebenauftrag_laufend_count` + `terminal_letzter_nebenauftrag` (fuer Offline-UI).
  - Terminal: Startscreen nutzt im Offline-Betrieb den Session-Zaehler, um „Nebenauftrag stoppen“ korrekt anzuzeigen.
- **NEXT:**
  - Maschinen-Backend: Barcode/QR-Generator (PNG) pro Maschine + Anzeige/Download im Maschinenformular.


## P-2026-01-18-27
- ZIP: `P-2026-01-18-27_terminal-auftrag-start-guard.zip`
- **EINGELESEN (SHA256):**
  - Projekt-ZIP: `160733_gesammt.zip` = `500b5251dbf9ffefc3481a3c747a4d8c0cddfa099f0d724fda1dfb577198e980`
  - Master-Prompt (SoT): `docs/master_prompt_zeiterfassung_v12.md` = `7327e0896cc71d2aabd55d8d8ec3882428f83bc72fec7ebd2cbd97f78dea4bdf`
  - Dev-Prompt (SoT): `docs/dev_prompt_zeiterfassung_v12.md` = `174ff61fea61d90690bdccfa105e1a1a0c95698f4d6912626fbf0f0f1c3c902a`
  - DEV_PROMPT_HISTORY (Stand vor Patch): `docs/DEV_PROMPT_HISTORY.md` = `38cd7a8c409c95293b77a2fac0edca19d6cf75bd532b4d919e6c1fb17fb15175`
  - Rechte-Prompt: `docs/rechte_prompt.md` = `446da183245ed18087648d9f03e3a2ce4d08db9927d588455b2eeb2e396e4122`
  - SQL-Schema (SoT): `sql/01_initial_schema.sql` = `70114c586e4f366bdc339efa58bfd4ef7dc85a7a22ac960e21995f604faf985e`
  - Zusatz (Offline-Schema): `sql/offline_db_schema.sql` = `165bd68e62f4a776d2425d108fbf0775497ade28f5a1e8242069c1cf084177c9`
  - Zusatz (DB-Dump): `sql/zeiterfassung_aktuell.sql` = `9c62b1709c4729cca8a3b59e7a700c25ef23907592b9cc5256e5d78649e29cee`
  - Zusatz (Auftrags-Scope): `docs/docs/archiv/auftrags_prompt_v1.md` = `35142adf8d89ca7df88472aa50186da5c659a29928754ffc3354259ce54467cc`
- **DUPLICATE-CHECK:**
  - SNAPSHOT/LOG geprueft: Task „Auftrag starten ausblenden wenn Hauptauftrag laeuft“ war nur als NEXT in P-2026-01-18-24 dokumentiert → noch nicht umgesetzt → OK.
- **DATEIEN (max. 3):**
  - `views/terminal/start.php`
  - `docs/DEV_PROMPT_HISTORY.md`
- **DONE:**
  - Terminal Startscreen (anwesend): Der Hauptauftrag-Bereich ist jetzt eine **Primaeraktion** – entweder **„Auftrag starten“** (wenn kein Hauptauftrag laeuft) **oder** **„Auftrag stoppen“** (wenn ein Hauptauftrag laeuft).
  - Ziel: Doppelstarts vermeiden; der Operator hat pro Zustand genau eine klare Hauptaktion.
- **Akzeptanz:**
  - Wenn ein Hauptauftrag laeuft, ist auf dem Startscreen kein „Auftrag starten“-Button sichtbar, sondern nur „Auftrag stoppen“ als grosse Primaeraktion.
- **NEXT:**
  - Maschinen-Backend: Barcode/QR-Generator (PNG/JPG) pro Maschine + Anzeige/Download (separater Micro-Patch).