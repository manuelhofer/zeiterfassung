# Rechte-Prompt (Berechtigungen) – Source of Truth

Stand: 2026-01-17

## 1. Überblick
- Rechte-Codes liegen in der Haupt-DB in `recht.code`.
- Zuweisungen: `rolle_hat_recht` (Rollenrechte) + `mitarbeiter_rechte_override` (Allow/Deny pro Mitarbeiter).
- Prüfung im Code: `AuthService::hatRecht($code)` (teilweise Legacy-Fallback: Rollen „Chef“/„Personalbüro“ als Admin).
- Ziel dieses Dokuments: **eine** Stelle, an der steht, **welches Recht wofür** gebraucht wird – und welche alten Rechte wir zusammenführen können.



## 1a. Warum sind Rechte in „Rolle bearbeiten“ doppelt?
Kurz: In der DB existieren **Legacy-Rechte** und **kanonische Nachfolger** parallel. Die Rollen-UI listet aktuell alle aktiven Datensätze aus `recht` – deshalb siehst du beides.

- **Legacy (soll weg / mergen):**
  - `ZEIT_EDIT_SELF`, `ZEIT_EDIT_ALLE`
  - `ZEITBUCHUNG_EDITIEREN_SELF`, `ZEITBUCHUNG_EDITIEREN_ALLE`
  - `REPORT_MONAT_ALLE`
- **Kanonisch (im Code aktiv genutzt):**
  - `ZEITBUCHUNG_EDIT_SELF`, `ZEITBUCHUNG_EDIT_ALL`
  - `REPORT_MONAT_VIEW_ALL`
- Roadmap dazu steht unten in **Kapitel 5** (Phase 1: mergen/ausblenden).

## 1b. Inventar aller Rechte (DB) + Status
Quelle: `sql/zeiterfassung_aktuell.sql` (INSERT INTO `recht`).

„Im Code geprüft“ bedeutet: der Code kommt in PHP-Dateien (außerhalb `sql/` und `docs/`) vor und wird aktuell per `AuthService::hatRecht()` / Menü-Checks verwendet.

| Code                          | Name                                        | Im Code geprüft   | Geplanter Merge       |
|:------------------------------|:--------------------------------------------|:------------------|:----------------------|
| URLAUB_GENEHMIGEN             | Urlaub genehmigen (zugewiesene Mitarbeiter) | JA                | —                     |
| URLAUB_GENEHMIGEN_ALLE        | Urlaub genehmigen (alle Mitarbeiter)        | JA                | —                     |
| URLAUB_GENEHMIGEN_SELF        | Urlaub genehmigen (eigene Anträge)          | JA                | —                     |
| ZEIT_EDIT_SELF                | Zeitbuchungen bearbeiten (eigene)           | NEIN              | ZEITBUCHUNG_EDIT_SELF |
| ZEIT_EDIT_ALLE                | Zeitbuchungen bearbeiten (alle)             | NEIN              | ZEITBUCHUNG_EDIT_ALL  |
| REPORT_MONAT_ALLE             | Monatsreports einsehen (alle)               | NEIN              | REPORT_MONAT_VIEW_ALL |
| ROLLEN_RECHTE_VERWALTEN       | Rollen/Rechte verwalten                     | JA                | —                     |
| ZEITBUCHUNG_EDIT_SELF         | Zeitbuchungen bearbeiten (eigene)           | JA                | —                     |
| ZEITBUCHUNG_EDIT_ALL          | Zeitbuchungen bearbeiten (alle Mitarbeiter) | JA                | —                     |
| REPORT_MONAT_VIEW_ALL         | Monatsreport (alle) ansehen                 | JA                | —                     |
| REPORT_MONAT_EXPORT_ALL       | Monatsreport (alle) exportieren             | JA                | —                     |
| REPORTS_ANSEHEN_ALLE          | Reports aller Mitarbeiter ansehen           | JA                | —                     |
| ZEITBUCHUNG_EDITIEREN_SELF    | Eigene Zeitbuchungen bearbeiten             | NEIN              | ZEITBUCHUNG_EDIT_SELF |
| ZEITBUCHUNG_EDITIEREN_ALLE    | Zeitbuchungen aller bearbeiten              | NEIN              | ZEITBUCHUNG_EDIT_ALL  |
| MITARBEITER_VERWALTEN         | Mitarbeiter verwalten                       | JA                | —                     |
| ABTEILUNG_VERWALTEN           | Abteilungen verwalten                       | JA                | —                     |
| MASCHINEN_VERWALTEN           | Maschinen verwalten                         | JA                | —                     |
| FEIERTAGE_VERWALTEN           | Feiertage verwalten                         | JA                | —                     |
| BETRIEBSFERIEN_VERWALTEN      | Betriebsferien verwalten                    | JA                | —                     |
| QUEUE_VERWALTEN               | Offline-Queue verwalten                     | JA                | —                     |
| TERMINAL_VERWALTEN            | Terminals verwalten                         | JA                | —                     |
| ZEIT_RUNDUNGSREGELN_VERWALTEN | Zeit-Rundungsregeln verwalten               | JA                | —                     |
| KONFIGURATION_VERWALTEN       | Konfiguration verwalten                     | JA                | —                     |
| URLAUB_KONTINGENT_VERWALTEN   | Urlaub-Kontingent verwalten                 | JA                | —                     |
| PAUSENREGELN_VERWALTEN        | Pausenregeln verwalten                      | JA                | —                     |
| KRANKZEITRAUM_VERWALTEN       | Krankzeitraum verwalten                     | JA                | —                     |
| KURZARBEIT_VERWALTEN          | Kurzarbeit verwalten                        | JA                | —                     |
| DASHBOARD_ZEITWARNUNGEN_SEHEN | Dashboard: Zeitwarnungen sehen              | JA                | —                     |
| STUNDENKONTO_VERWALTEN       | Stundenkonto verwalten                       | JA                | —                     |

## 2. Kanonische Rechte (im Code aktiv genutzt)
> Diese Codes werden aktuell im PHP-Code geprüft (Controller/Header).

### `ABTEILUNG_VERWALTEN`
- Name (DB): Abteilungen verwalten
- Zweck: Darf Abteilungen anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/AbteilungAdminController.php:38`
  - `views/layout/header.php:66`

### `BETRIEBSFERIEN_VERWALTEN`
- Name (DB): Betriebsferien verwalten
- Zweck: Darf Betriebsferien anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/BetriebsferienAdminController.php:43`
  - `views/layout/header.php:70`

### `DASHBOARD_ZEITWARNUNGEN_SEHEN`
- Name (DB): Dashboard: Zeitwarnungen sehen
- Zweck: Darf den Dashboard-Warnblock für unplausible/unvollständige Kommen/Gehen-Stempel sehen.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:79`
  - `controller/DashboardController.php:151`
  - `controller/DashboardController.php:223`

### `FEIERTAGE_VERWALTEN`
- Name (DB): Feiertage verwalten
- Zweck: Darf Feiertage anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/FeiertagController.php:42`
  - `views/layout/header.php:69`

### `KONFIGURATION_VERWALTEN`
- Name (DB): Konfiguration verwalten
- Zweck: Darf Konfigurationseinträge (Key/Value) anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:360`
  - `controller/KonfigurationController.php:37`
  - `controller/KonfigurationController.php:72`
  - `controller/KonfigurationController.php:111`
  - `controller/KurzarbeitAdminController.php:45`
  - `controller/SmokeTestController.php:49`
  - `views/layout/header.php:76`

### `KRANKZEITRAUM_VERWALTEN`
- Name (DB): Krankzeitraum verwalten
- Zweck: Darf Krank-Zeiträume pro Mitarbeiter pflegen (Lohnfortzahlung/Krankenkasse).
- Prüfpunkte im Code:
  - `controller/KonfigurationController.php:71`
  - `views/layout/header.php:78`

### `KURZARBEIT_VERWALTEN`
- Name (DB): Kurzarbeit verwalten
- Zweck: Darf Kurzarbeit planen und Zeiträume pflegen.
- Prüfpunkte im Code:
  - `controller/KurzarbeitAdminController.php:44`

### `MASCHINEN_VERWALTEN`
- Name (DB): Maschinen verwalten
- Zweck: Darf Maschinen anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/MaschineAdminController.php:38`
  - `views/layout/header.php:67`

### `MITARBEITER_VERWALTEN`
- Name (DB): Mitarbeiter verwalten
- Zweck: Darf Mitarbeiter anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/MitarbeiterAdminController.php:40`
  - `controller/SmokeTestController.php:52`
  - `views/layout/header.php:65`

### `PAUSENREGELN_VERWALTEN`
- Name (DB): Pausenregeln verwalten
- Zweck: Darf betriebliche Pausenfenster (Zwangspausen) anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/KonfigurationController.php:110`

### `QUEUE_VERWALTEN`
- Name (DB): Offline-Queue verwalten
- Zweck: Darf Offline-Queue einsehen/clear/retry.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:358`
  - `controller/DashboardController.php:866`
  - `controller/QueueController.php:301`
  - `controller/SmokeTestController.php:50`
  - `views/layout/header.php:71`

### `REPORTS_ANSEHEN_ALLE`
- Name (DB): Reports aller Mitarbeiter ansehen
- Zweck: Darf Monats-/PDF-Reports für andere Mitarbeiter ansehen/exportieren.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:133`
  - `controller/ReportController.php:43`
  - `controller/ReportController.php:70`
  - `controller/SmokeTestController.php:54`
  - `controller/SmokeTestController.php:1536`

### `REPORT_MONAT_EXPORT_ALL`
- Name (DB): Monatsreport (alle) exportieren
- Zweck: Darf einen Sammel-Export (ZIP) für einen Monat erzeugen (pro Mitarbeiter 1 PDF).
- Prüfpunkte im Code:
  - `controller/ReportController.php:66`

### `REPORT_MONAT_VIEW_ALL`
- Name (DB): Monatsreport (alle) ansehen
- Zweck: Darf Monatsübersichten/PDFs für beliebige Mitarbeiter anzeigen/erzeugen.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:132`
  - `controller/ReportController.php:39`
  - `controller/SmokeTestController.php:1535`

### `ROLLEN_RECHTE_VERWALTEN`
- Name (DB): Rollen/Rechte verwalten
- Zweck: Darf Rollen und deren Rechtezuweisungen administrieren.
- Prüfpunkte im Code:
  - `controller/RollenAdminController.php:39`
  - `controller/SmokeTestController.php:53`
  - `views/layout/header.php:68`

### `TERMINAL_VERWALTEN`
- Name (DB): Terminals verwalten
- Zweck: Darf Terminals anlegen/bearbeiten.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:359`
  - `controller/SmokeTestController.php:51`
  - `controller/TerminalAdminController.php:66`
  - `views/layout/header.php:72`

### `URLAUB_GENEHMIGEN`
- Name (DB): Urlaub genehmigen (zugewiesene Mitarbeiter)
- Zweck: Darf Urlaubsanträge genehmigen/ablehnen (typisch: nur für Mitarbeiter, für die man als Genehmiger eingetragen ist).
- Prüfpunkte im Code:
  - `controller/UrlaubController.php:677`
  - `views/layout/header.php:105`

### `URLAUB_GENEHMIGEN_ALLE`
- Name (DB): Urlaub genehmigen (alle Mitarbeiter)
- Zweck: Darf Urlaubsanträge aller Mitarbeiter genehmigen/ablehnen (Chef/Personalbüro).
- Prüfpunkte im Code:
  - `controller/UrlaubController.php:676`
  - `views/layout/header.php:99`

### `URLAUB_GENEHMIGEN_SELF`
- Name (DB): Urlaub genehmigen (eigene Anträge)
- Zweck: Darf eigene Urlaubsanträge selbst genehmigen/ablehnen (z. B. Chef).
- Prüfpunkte im Code:
  - `controller/UrlaubController.php:678`
  - `views/layout/header.php:100`

### `URLAUB_KONTINGENT_VERWALTEN`
- Name (DB): Urlaub-Kontingent verwalten
- Zweck: Darf Urlaubskontingente/Übertrag/Korrekturen pro Mitarbeiter und Jahr pflegen.
- Prüfpunkte im Code:
  - `controller/UrlaubKontingentAdminController.php:40`
  - `views/layout/header.php:77`

### `ZEITBUCHUNG_EDIT_ALL`
- Name (DB): Zeitbuchungen bearbeiten (alle Mitarbeiter)
- Zweck: Erlaubt das Korrigieren von Zeitbuchungen aller Mitarbeiter (add/update/delete) im Backend inkl. Audit-Log.
- Prüfpunkte im Code:
  - `controller/DashboardController.php:131`
  - `controller/ReportController.php:157`
  - `controller/ZeitController.php:55`

### `ZEITBUCHUNG_EDIT_SELF`
- Name (DB): Zeitbuchungen bearbeiten (eigene)
- Zweck: Erlaubt das Korrigieren von eigenen Zeitbuchungen (add/update/delete) im Backend inkl. Audit-Log.
- Prüfpunkte im Code:
  - `controller/ReportController.php:158`
  - `controller/ZeitController.php:56`

### `ZEIT_RUNDUNGSREGELN_VERWALTEN`
- Name (DB): Zeit-Rundungsregeln verwalten
- Zweck: Darf Zeit-Rundungsregeln anlegen/bearbeiten/aktivieren.
- Prüfpunkte im Code:
  - `controller/ZeitRundungsregelAdminController.php:39`
  - `views/layout/header.php:75`

### `STUNDENKONTO_VERWALTEN`
- Name (DB): Stundenkonto verwalten
- Zweck: Erlaubt Stundenkonto-Korrekturen, Verteilbuchungen und Monatsabschluss-Buchungen im Backend (Audit: Begruendung Pflicht).
- Prüfpunkte im Code:
  - `controller/MitarbeiterAdminController.php:608` (Stundenkonto-Block anzeigen/ausblenden)
  - `controller/MitarbeiterAdminController.php:1551` (Korrektur speichern: Zugriffsschutz)
  - `controller/MitarbeiterAdminController.php:1676` (Verteilbuchung speichern: Zugriffsschutz)
  - `controller/ReportController.php:111` (Monatsabschluss: Zugriffsschutz)
  - `views/mitarbeiter/formular.php:996` (UI-Hinweis wenn Recht fehlt)

## 3. Legacy / doppelte Rechte (aktuell nur DB, nicht im Code genutzt)
Diese Rechte-Codes existieren in `recht`, werden aber aktuell **nirgendwo** per `hatRecht()` geprüft. Das führt in der Rollen-UI zu **doppelten** Einträgen mit gleicher/ähnlicher Bedeutung.

- `REPORT_MONAT_ALLE` – Monatsreports einsehen (alle) – Darf Monatsübersichten/PDFs für alle Mitarbeiter einsehen/erzeugen.
- `ZEITBUCHUNG_EDITIEREN_ALLE` – Zeitbuchungen aller bearbeiten – Darf Zeitbuchungen anderer Mitarbeiter korrigieren (mit Audit/Begründung).
- `ZEITBUCHUNG_EDITIEREN_SELF` – Eigene Zeitbuchungen bearbeiten – Darf eigene Zeitbuchungen korrigieren (mit Audit/Begründung).
- `ZEIT_EDIT_ALLE` – Zeitbuchungen bearbeiten (alle) – Darf Zeitbuchungen aller Mitarbeiter nachträglich bearbeiten (Audit/Markierung erforderlich).
- `ZEIT_EDIT_SELF` – Zeitbuchungen bearbeiten (eigene) – Darf eigene Zeitbuchungen nachträglich bearbeiten (Audit/Markierung erforderlich).

## 4. Feature-Matrix (Backend) – welche Seite braucht welches Recht?
- **Abteilungen**: `ABTEILUNG_VERWALTEN`
- **Mitarbeiter**: `MITARBEITER_VERWALTEN`
- **Maschinen**: `MASCHINEN_VERWALTEN`
- **Rollen / Rechte**: `ROLLEN_RECHTE_VERWALTEN`
- **Feiertage**: `FEIERTAGE_VERWALTEN`
- **Rundungsregeln**: `ZEIT_RUNDUNGSREGELN_VERWALTEN`
- **Konfiguration** (Basis): `KONFIGURATION_VERWALTEN` (inkl. Legacy-Fallback)
  - **Krankzeiträume**: `KRANKZEITRAUM_VERWALTEN` (oder Konfig-Recht)
  - **Pausenregeln**: `PAUSENREGELN_VERWALTEN` (oder Konfig-Recht)
  - **Kurzarbeit-Plan**: `KURZARBEIT_VERWALTEN` (oder Konfig-Recht)
- **Betriebsferien**: `BETRIEBSFERIEN_VERWALTEN`
- **Stundenkonto** (Korrekturen/Verteilungen): `STUNDENKONTO_VERWALTEN`
- **Urlaub-Kontingente**: `URLAUB_KONTINGENT_VERWALTEN`
- **Offline-Queue**: `QUEUE_VERWALTEN`
- **Terminals**: `TERMINAL_VERWALTEN`
- **Urlaub genehmigen**:
  - **alle Mitarbeiter**: `URLAUB_GENEHMIGEN_ALLE`
  - **eigene Anträge**: `URLAUB_GENEHMIGEN_SELF`
  - **zugewiesene Mitarbeiter**: `URLAUB_GENEHMIGEN`
- **Zeitwarnungen (Dashboard-Block)**: `DASHBOARD_ZEITWARNUNGEN_SEHEN`
- **Zeitbuchungen bearbeiten**: `ZEITBUCHUNG_EDIT_SELF` / `ZEITBUCHUNG_EDIT_ALL`
- **Monatsübersicht/Report für andere Mitarbeiter**: `REPORT_MONAT_VIEW_ALL` oder `REPORTS_ANSEHEN_ALLE` (Legacy-Fallback)
- **Sammel-Export (ZIP)**: `REPORT_MONAT_EXPORT_ALL`

## 5. Roadmap: Rechte zusammenführen & UI bereinigen (Status)

### Phase 1 – Legacy-Codes mergen (ohne Funktionsänderung)
- Ziel: Rollen-UI zeigt jeden Zweck **nur einmal**.
- **Phase 1a DONE (SQL):** `sql/19_migration_rechte_legacy_merge.sql`
  - Mapped Legacy → Kanonisch (Rollenrechte + Mitarbeiter-Overrides),
  - entfernt Legacy-Zuweisungen,
  - setzt Legacy-Rechte auf `recht.aktiv=0` (Soft-Delete).
- **Phase 1b DONE (UI):**
  - Rollenverwaltung + Mitarbeiter-Rechte-Overrides laden nur noch **aktive** Rechte (`recht.aktiv=1`).

### Phase 2 – Datenbank-Seite hart machen (Verhindert neue Duplikate)
- **Phase 2 DONE (SQL):** `sql/20_migration_recht_code_unique.sql`
  - Normalisiert `recht.code` (TRIM),
  - konsolidiert Dubletten (mappen in `rolle_hat_recht` und `mitarbeiter_rechte_override`),
  - stellt Unique-Index `uniq_recht_code` sicher,
  - legt Index `idx_recht_aktiv` an (Performance für Filter `aktiv=1`).

### Phase 3 – Optional: Rechte gruppieren/umbenennen (nur UI)
- **Phase 3a DONE (Rollen-UI):** Rechte werden in Gruppen (Details/Summary) angezeigt.
- **Phase 3b DONE (Mitarbeiter-Overrides-UI):** Rechte-Overrides werden analog gruppiert angezeigt.
- Optional (später): Deprecated-Filter, falls wir Soft-Delete über `aktiv=0` dauerhaft nutzen.

## 6. Checkliste wenn ein neues Recht gebraucht wird
1. Code eindeutig wählen (UPPER_SNAKE_CASE, Verb am Ende z. B. `_VERWALTEN`, `_EDIT_*`, `_VIEW_*`).
2. Recht in SQL-Seed ergänzen (ohne Duplikate).
3. **Hier** (`docs/rechte_prompt.md`) dokumentieren (Zweck + wo geprüft + Menü/Controller).
4. Controller-Zugriff **und** Menü-Rendering prüfen (keine reine „UI-Sicherheit“).
