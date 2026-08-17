# Rechte-Prompt (Berechtigungen) – Source of Truth

Diese Datei sagt, **welches Recht wofür** da ist und **was bewusst ohne Recht
erreichbar bleibt**. Beides steht nirgendwo sonst.

**Wo ein Recht geprüft wird, sagt das Repository und nicht diese Datei:**

```bash
grep -rn "RECHTE_CODE" controller views public
```

Findet der Aufruf nur eine Methode wie `darfAuftraegeVerwalten()` oder
`darfVerwalten()`, ist das der Kapselungspunkt – die eigentlichen Aufrufer holt
der zweite Lauf:

```bash
grep -rn "darfAuftraegeVerwalten" controller views
```

Früher standen hier 66 Verweise auf `datei.php:zeile`. Sie waren nach wenigen
Patches falsch und behaupteten trotzdem Genauigkeit (entfernt in
P-2026-08-17-10). Ein Datum „Stand: …" gibt es aus demselben Grund nicht mehr –
wann diese Datei zuletzt stimmte, sagt `git log -- docs/rechte_prompt.md`.

## 1. Überblick
- Rechte-Codes liegen in der Haupt-DB in `recht.code`.
- Zuweisungen: `rolle_hat_recht` (Rollenrechte) + `mitarbeiter_hat_recht` (Allow/Deny pro Mitarbeiter über die Spalte `erlaubt`).
- Prüfung im Code: `AuthService::hatRecht($code)` (teilweise Legacy-Fallback: Rollen „Chef“/„Personalbüro“ als Admin – zusammenzuführen unter T-139).
- Welche Codes es gibt, steht in `sql/01_initial_schema.sql` (`INSERT INTO recht`)
  – das ist die Quelle, diese Datei ist die Erklärung dazu.

## 1a. Warum stehen Legacy-Rechte noch in der Datenbank?
Weil sie per Soft-Delete abgeschaltet sind, nicht gelöscht: Sie stehen mit
`recht.aktiv = 0` in `recht`, damit alte Zuweisungen nachvollziehbar bleiben.

**In der Rollen-UI sind sie nicht mehr sichtbar.** Rollenverwaltung und
Mitarbeiter-Overrides laden nur aktive Rechte
(`RolleModel::holeAlleRechte(true)`) – jeder Zweck erscheint einmal. Das war
Phase 1b in Kapitel 5 und ist erledigt.

Abgeschaltet sind fünf Codes, jeder mit kanonischem Nachfolger:

| Legacy (`aktiv = 0`) | Kanonisch |
| --- | --- |
| `ZEIT_EDIT_SELF`, `ZEITBUCHUNG_EDITIEREN_SELF` | `ZEITBUCHUNG_EDIT_SELF` |
| `ZEIT_EDIT_ALLE`, `ZEITBUCHUNG_EDITIEREN_ALLE` | `ZEITBUCHUNG_EDIT_ALL` |
| `REPORT_MONAT_ALLE` | `REPORT_MONAT_VIEW_ALL` |

Wer eine Zuweisung zu einem inaktiven Recht in der Datenbank findet: Sie
überlebt das Speichern der Masken, weil deren `DELETE` auf die angezeigte
Auswahl eingegrenzt ist (T-137, P-2026-08-16-29).

## 1b. Inventar aller Rechte (DB) + Status

„Im Code geprüft" heißt: Der Code kommt in PHP-Dateien außerhalb `sql/` und
`docs/` vor. Nachprüfbar mit dem `grep` von oben – die Spalte ist eine
Bestandsaufnahme, kein gepflegter Wert.

| Code                          | Name                                        | Im Code geprüft   | Nachfolger            |
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
| STUNDENKONTO_VERWALTEN        | Stundenkonto verwalten                      | JA                | —                     |
| AUFTRAEGE_VERWALTEN           | Aufträge verwalten                          | JA                | —                     |

## 2. Kanonische Rechte – Zweck und Grenzen

Die Rechte, deren Zweck sich nicht aus dem Namen ergibt, oder bei denen eine
Entscheidung dahintersteht. Alle übrigen (`ABTEILUNG_VERWALTEN`,
`MASCHINEN_VERWALTEN`, `FEIERTAGE_VERWALTEN`, `BETRIEBSFERIEN_VERWALTEN`,
`MITARBEITER_VERWALTEN`, `ROLLEN_RECHTE_VERWALTEN`,
`ZEIT_RUNDUNGSREGELN_VERWALTEN`, `URLAUB_KONTINGENT_VERWALTEN`,
`PAUSENREGELN_VERWALTEN`, `KRANKZEITRAUM_VERWALTEN`, `KURZARBEIT_VERWALTEN`,
`QUEUE_VERWALTEN`, `DASHBOARD_ZEITWARNUNGEN_SEHEN`) schützen genau die Maske,
die sie benennen – anlegen, bearbeiten, deaktivieren.

### `AUFTRAEGE_VERWALTEN`
- Aufträge und deren Arbeitsschritte im Backend anlegen, bearbeiten,
  deaktivieren, inklusive der zugehörigen QR-Codes.
- **Bewusst nicht geschützt:** Auftragsliste, Detailansicht und das
  Laufkarten-PDF bleiben für alle angemeldeten Benutzer erreichbar. Wer in der
  Werkstatt eine Laufkarte nachdrucken muss, soll dafür kein Verwaltungsrecht
  brauchen.
- **Unberührt** bleibt das automatische Anlegen beim Scannen am Terminal
  (`AuftragszeitService::starteAuftrag`) – dort darf eine Buchung nie an einem
  fehlenden Recht scheitern.
- Gekapselt in `AuftragController::darfAuftraegeVerwalten()` und
  `ArbeitsschrittKatalogController::darfVerwalten()`; im Menü steuert es das
  Aufklappmenü „Aufträge“.
- Eingeführt mit P-2026-08-08-08. Bestandsinstallationen ziehen das Recht mit
  `sql/02_migration_recht_auftraege_verwalten.sql` nach.

### `TERMINAL_VERWALTEN`
- Terminals anlegen und bearbeiten sowie **Kopplungscodes erzeugen**, mit denen
  ein Gerät sich am Backend anmeldet (ab P-2026-08-08-31,
  `TerminalAdminController::kopplung()`).
- **Bewusst ohne Recht:** Der Kopplungs-Endpunkt, den das Terminal selbst
  aufruft (`?seite=terminal_kopplung`, `TerminalKopplungController`, nur POST,
  ab P-2026-08-08-36), ist ohne Anmeldung erreichbar – ein frisch installiertes
  Gerät hat noch keinen Benutzer. Der Kopplungscode ist dort der Nachweis.
  Abgesichert ist er dadurch, dass der Code einmalig und zeitlich begrenzt gilt,
  dass Fehlversuche je Absender gebremst werden und dass ein inaktives Terminal
  abgewiesen wird.

### `STUNDENKONTO_VERWALTEN`
- Stundenkonto-Korrekturen, Verteilbuchungen und Monatsabschluss-Buchungen im
  Backend. **Begründung ist Pflicht**, jede Buchung wird auditiert.
- Fehlt das Recht, blendet das Mitarbeiter-Formular den Stundenkonto-Block aus
  und sagt, warum.

### `ZEITBUCHUNG_EDIT_SELF` / `ZEITBUCHUNG_EDIT_ALL`
- Zeitbuchungen korrigieren (anlegen, ändern, löschen) im Backend, mit
  Audit-Log. `SELF` nur die eigenen, `ALL` die aller Mitarbeiter.
- Jede Korrektur verlangt eine Begründung und landet im `system_log`.

### `REPORT_MONAT_VIEW_ALL`, `REPORT_MONAT_EXPORT_ALL`, `REPORTS_ANSEHEN_ALLE`
- `REPORT_MONAT_VIEW_ALL`: Monatsübersichten und PDFs für beliebige Mitarbeiter
  anzeigen und erzeugen.
- `REPORT_MONAT_EXPORT_ALL`: Sammel-Export als ZIP für einen Monat, ein PDF je
  Mitarbeiter.
- `REPORTS_ANSEHEN_ALLE`: älterer Code mit gleicher Wirkung wie
  `REPORT_MONAT_VIEW_ALL`; er wird als Fallback weiter akzeptiert, damit
  bestehende Rollen nicht über Nacht den Zugriff verlieren.

### `URLAUB_GENEHMIGEN`, `_ALLE`, `_SELF`
- `URLAUB_GENEHMIGEN`: nur für Mitarbeiter, für die man als Genehmiger
  eingetragen ist – die Abteilungsregeln dazu stehen in
  [`spezifikation_abteilungsrechte.md`](spezifikation_abteilungsrechte.md).
- `URLAUB_GENEHMIGEN_ALLE`: alle Mitarbeiter, unabhängig von der Zuordnung.
- `URLAUB_GENEHMIGEN_SELF`: eigene Anträge selbst genehmigen (z. B. der Chef).

### `KONFIGURATION_VERWALTEN`
- Konfigurationseinträge (Key/Value) anlegen und bearbeiten.
- Gilt zusätzlich als Ersatzrecht für die drei Unterseiten
  `KRANKZEITRAUM_VERWALTEN`, `PAUSENREGELN_VERWALTEN` und
  `KURZARBEIT_VERWALTEN`: Wer die Konfiguration verwalten darf, kommt auch dort
  hinein.

## 3. Legacy-Rechte (nur in der Datenbank, nicht im Code)

Diese fünf Codes stehen in `recht` mit `aktiv = 0` und werden **nirgendwo** per
`hatRecht()` geprüft. Sie sind in der Rollen-UI ausgeblendet (Kapitel 1a) und
verursachen dort keine Doppeleinträge mehr.

- `REPORT_MONAT_ALLE` – Monatsreports einsehen (alle)
- `ZEITBUCHUNG_EDITIEREN_ALLE` – Zeitbuchungen aller bearbeiten
- `ZEITBUCHUNG_EDITIEREN_SELF` – Eigene Zeitbuchungen bearbeiten
- `ZEIT_EDIT_ALLE` – Zeitbuchungen bearbeiten (alle)
- `ZEIT_EDIT_SELF` – Zeitbuchungen bearbeiten (eigene)

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
- **Aufträge** (anlegen/bearbeiten, Katalog): `AUFTRAEGE_VERWALTEN`
- **Urlaub genehmigen**:
  - **alle Mitarbeiter**: `URLAUB_GENEHMIGEN_ALLE`
  - **eigene Anträge**: `URLAUB_GENEHMIGEN_SELF`
  - **zugewiesene Mitarbeiter**: `URLAUB_GENEHMIGEN`
- **Zeitwarnungen (Dashboard-Block)**: `DASHBOARD_ZEITWARNUNGEN_SEHEN`
- **Zeitbuchungen bearbeiten**: `ZEITBUCHUNG_EDIT_SELF` / `ZEITBUCHUNG_EDIT_ALL`
- **Monatsübersicht/Report für andere Mitarbeiter**: `REPORT_MONAT_VIEW_ALL` oder `REPORTS_ANSEHEN_ALLE` (Legacy-Fallback)
- **Sammel-Export (ZIP)**: `REPORT_MONAT_EXPORT_ALL`

## 5. Rechte zusammenführen und UI bereinigen – abgeschlossen

Alle Phasen sind erledigt. Der Abschnitt bleibt stehen, weil die Entscheidungen
darin erklären, warum die Datenbank heute so aussieht.

### Phase 1 – Legacy-Codes mergen (ohne Funktionsänderung)
- Ziel war: Die Rollen-UI zeigt jeden Zweck **nur einmal**. Erreicht.
- **Phase 1a (SQL):** damals `sql/19_migration_rechte_legacy_merge.sql` – mappte
  Legacy → Kanonisch (Rollenrechte + Mitarbeiter-Overrides), entfernte
  Legacy-Zuweisungen und setzte die Legacy-Rechte auf `recht.aktiv = 0`
  (Soft-Delete).
  **Die Datei gibt es nicht mehr:** Ihr Ergebnis steckt seither in
  `sql/01_initial_schema.sql`. Neuinstallationen brauchen sie nicht, bestehende
  Installationen haben sie längst.
- **Phase 1b (UI):** Rollenverwaltung und Mitarbeiter-Overrides laden nur noch
  aktive Rechte.

### Phase 2 – Datenbank-Seite hart machen (verhindert neue Duplikate)
- damals `sql/20_migration_recht_code_unique.sql` – normalisierte `recht.code`
  (TRIM), konsolidierte Dubletten, stellte den Unique-Index `uniq_recht_code`
  sicher und legte `idx_recht_aktiv` an.
  **Die Datei gibt es nicht mehr:** Beide Indizes stehen heute direkt in
  `sql/01_initial_schema.sql` an der Tabelle `recht`.

### Phase 3 – Rechte gruppieren (nur UI)
- Rollen-UI und Mitarbeiter-Overrides zeigen die Rechte gruppiert
  (Details/Summary).
- Offen und **nur bei Bedarf**: ein Filter, der inaktive Rechte auf Wunsch
  wieder einblendet, falls der Soft-Delete dauerhaft bleibt.

## 6. Checkliste wenn ein neues Recht gebraucht wird
1. Code eindeutig wählen (UPPER_SNAKE_CASE, Verb am Ende z. B. `_VERWALTEN`,
   `_EDIT_*`, `_VIEW_*`).
2. Recht in `sql/01_initial_schema.sql` ergänzen (ohne Duplikate) und für
   Bestandsinstallationen eine Migration nach `sql/` legen.
3. **Hier** eintragen: der **Zweck**, und vor allem, was bewusst **ohne** das
   Recht erreichbar bleibt. Keine Fundstellen – die findet der `grep` von oben,
   und von Hand gepflegt wären sie nach dem nächsten Patch falsch.
4. Controller-Zugriff **und** Menü-Rendering prüfen (keine reine
   „UI-Sicherheit“).
