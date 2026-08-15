# Zusatz-Prompt 2 – Stundenkonto (Gutstunden / Minusstunden)

Stand: 2026-01-17

Dieser Prompt beschreibt, wie im Projekt **„Zeiterfassung“** ein **Stundenkonto** (Guthaben/Minus) umgesetzt werden soll: performant, nachvollziehbar (Audit/Begründung) und rückwirkend korrigierbar.

Wichtig: Der vorherige `docs/archiv/zusatzpromt.md` wurde entfernt, da er als **abgearbeitet** gilt. Dieser Prompt ist der neue Scope.

---

## 0) Pflicht vor Start (Checkliste)

Bevor irgendetwas implementiert wird:

1) Master-Regeln lesen und strikt befolgen:
   - `docs/master_prompt_zeiterfassung_v10.md`
   - Ausgabeformat: **nur ZIP**, max. 3 Dateien pro Patch (inkl. `docs/DEV_PROMPT_HISTORY.md`).

2) Entwicklungsstand lesen:
   - `docs/DEV_PROMPT_HISTORY.md` (SNAPSHOT + letzte Patches)

3) Rechte-System verstehen:
   - `docs/rechte_prompt.md`

4) DB-Stand verstehen:
   - `sql/01_initial_schema.sql` (SoT)
   - `sql/zeiterfassung_aktuell.sql` (Dump)

5) Relevante Implementierungen im Code ansehen (nur lesen, noch nichts mischen):
   - Monatslogik: `services/ReportService.php`, `views/report/monatsuebersicht.php`
   - PDF: `services/PDFService.php`
   - Terminal-Arbeitszeit: `views/terminal/start.php` (Sub-View `?aktion=start&view=arbeitszeit`)

---

## 1) Zielbild (was der Nutzer erwartet)

### 1.1 Terminal (Mitarbeiter sieht sein Konto)
- In der **Arbeitszeit-Übersicht** im Terminal steht nicht nur Soll/Ist für den Monat, sondern auch:
  - **Gutstunden / Minusstunden (Saldo gesamt)**
  - und zwar **exklusiv der Stunden des aktuellen Monats** (Saldo bis Ende Vormonat).

### 1.2 Backend (Chef/Personal kann sehen und korrigieren)
- Im Backend kann man beim Mitarbeiter das Stundenkonto:
  - ansehen,
  - manuell korrigieren (mit Begründung),
  - und rückwirkend eine Korrektur **auf mehrere Arbeitstage verteilt** buchen.

### 1.3 Monatsübersicht (HTML + PDF)
- In der Monatsübersicht (Seite + PDF) soll zusätzlich sichtbar sein:
  - Stundenkonto **vor** dem Monat (Saldo bis Ende Vormonat),
  - Monatsdifferenz (Über-/Minusstunden des Monats, gibt es bereits),
  - optional: Saldo **nach** dem Monat (vor + Monatsdifferenz + ggf. Korrekturen im Monat).
- In der PDF soll in der unteren Zusammenfassung zusätzlich Platz für:
  - **Urlaubstage abzüglich geplante Betriebsferien**,
  - **Stundenkonto (Saldo vor Monat)**
  geschaffen werden.

---

## 2) Begriffe & Definitionen

- **Monatsdifferenz / Monatssaldo**
  - Das ist der Wert, der bereits als `monatswerte_mitarbeiter.ueberstunden` existiert.
  - Bedeutet: Differenz aus IST vs. SOLL nach den Regeln im Master-Prompt (inkl. Krank/Kurzarbeit/Feiertage/Betriebsferien/Rundung usw.).

- **Stundenkonto (Saldo gesamt)**
  - Summe aller Monatsdifferenzen (historisch) **plus** manueller/verteilter Korrekturen.

- **Saldo vor Monat**
  - Stundenkonto bis **einschließlich Ende des Vormonats**.
  - Wird in Terminal/Monatsübersicht gebraucht, damit der aktuelle Monat nicht „doppelt“ gerechnet wird.

- **Korrektur**
  - Manuelle Buchung (Delta) oder eine automatisch erzeugte Buchung aus einer Verteilung.

- **Verteilung (Batch)**
  - Eine Rückwirk-Korrektur, die auf mehrere **Arbeitstage** verteilt wird, z. B.
    - „-20 Stunden gleichmäßig auf alle Arbeitstage zwischen 2026-01-01 und 2026-01-31“
    - oder „-15 Minuten pro Arbeitstag für 6 Monate“.

---

## 3) Strategie (Performance & Datenquelle)

Ziel: **Keine Tageswerte über Jahrzehnte bei jeder Anzeige summieren.**

Empfohlene Strategie:

1) **Prim... (1) `monatswerte_mitarbeiter.ueberstunden`** als Aggregatquelle nutzen.
   - Das sind maximal ca. 12 * 25 = 300 Datensätze pro Mitarbeiter -> schnell.

2) **Korrekturen separat als Ledger speichern**.
   - Summe der Korrekturen ist ebenfalls klein.

3) Das Stundenkonto wird bei Anzeige/Report immer als **SQL-Summe** berechnet:
   - `saldo_vor_monat = SUM(monatswerte.ueberstunden fuer Monate < Monat) + SUM(korrekturen < Monatsstart)`

4) Optional (nur falls später nötig): Cache/Snapshot.
   - Erst implementieren, wenn echte Performanceprobleme auftreten. (Vorher: sauberer SQL-Summen-Ansatz.)

---

## 4) Datenbank-Design (minimal, auditierbar)

### 4.1 Neue Rechte
- Neues Recht (Code): `STUNDENKONTO_VERWALTEN`
  - Erlaubt: Korrekturen/Verteilungen im Backend.
  - Terminal-Anzeige für eigenen Saldo braucht kein Spezialrecht.
  - Dokumentation in `docs/rechte_prompt.md` + Eintrag/Seed im SQL.

### 4.2 Neue Tabellen

#### a) `stundenkonto_batch` (Metadaten für Verteilungen)
Zweck: eine Verteilung als „Buchungsvorgang“ mit Begründung speichern.

Vorschlag Spalten:
- `id` (PK)
- `mitarbeiter_id` (FK)
- `modus` ENUM:
  - `gesamt_gleichmaessig` (Total gleichmäßig über Arbeitstage verteilen)
  - `minuten_pro_tag` (fixe Minuten pro Arbeitstag)
- `von_datum`, `bis_datum`
- `gesamt_minuten` (nur bei `gesamt_gleichmaessig`)
- `minuten_pro_tag` (nur bei `minuten_pro_tag`)
- `nur_arbeitstage` TINYINT(1) DEFAULT 1
- `begruendung` VARCHAR(255) NOT NULL
- `erstellt_von_mitarbeiter_id` (FK, optional)
- `erstellt_am`

#### b) `stundenkonto_korrektur` (Ledger – jede Buchung ist ein Datensatz)
Zweck: alle manuellen und verteilten Korrekturen als Delta speichern.

Wichtig: **Minuten als Integer** speichern (keine Rundungsfehler).

Vorschlag Spalten:
- `id` (PK)
- `mitarbeiter_id` (FK)
- `wirksam_datum` DATE NOT NULL
- `delta_minuten` INT NOT NULL  (positiv = Gutstunden, negativ = Minus)
- `typ` ENUM(`manuell`,`verteilung`) NOT NULL
- `batch_id` (FK, NULL)  (nur bei verteilten Buchungen)
- `begruendung` VARCHAR(255) NOT NULL
- `erstellt_von_mitarbeiter_id` (FK, optional)
- `erstellt_am`
- Optional (später): Soft-Delete `geloescht_am`, `geloescht_von_mitarbeiter_id`

Indizes:
- `(mitarbeiter_id, wirksam_datum)`
- `batch_id`

Hinweis:
- Verteilbuchungen werden als **mehrere** `stundenkonto_korrektur`-Zeilen gespeichert (eine pro Tag), alle mit gleicher `batch_id`. So ist die Verteilung „eingefroren“ und später nachvollziehbar.

---

## 5) Berechnung (Service-Logik)

### 5.1 Neue Service-Klasse
- `services/StundenkontoService.php`

Pflicht-Methoden (Beispielsignaturen, an Projektstil anpassen):

1) `getSaldoVorMonatMinuten(int $mitarbeiterId, int $jahr, int $monat): int`
   - Summiert:
     - `monatswerte_mitarbeiter.ueberstunden` für alle Monate < (jahr/monat)
     - `stundenkonto_korrektur.delta_minuten` für `wirksam_datum` < erster Tag des Monats

2) `getSaldoBisDatumExclusiveMinuten(int $mitarbeiterId, string $datumExclusive): int`
   - Allgemeiner Helfer (z. B. für „Saldo bis Ende Vormonat“ oder „Saldo bis Monatsende+1“).

3) `formatMinutenAlsStundenString(int $minuten): string`
   - UI-Ausgabe mit 2 Dezimalstellen, Vorzeichen korrekt.

### 5.2 Umrechnung Monatssaldo (überstunden -> minuten)
- `monatswerte_mitarbeiter.ueberstunden` ist Decimal-Stunden.
- In SQL/Service immer in Minuten umrechnen:
  - `minuten = ROUND(ueberstunden * 60)`
- Ausgabe wieder in Stunden (2 Dezimalstellen).

### 5.3 Wenn sich Vergangenheitsdaten ändern
- Es muss eine Strategie geben, wenn nachträglich Zeiten geändert werden und dadurch `monatswerte_mitarbeiter.ueberstunden` sich ändert.
- Minimalanforderung:
  - Stundenkonto wird immer als SQL-Summe berechnet -> keine „falschen“ gecachten Werte.
- Optional für später:
  - CLI/Tool: „Monatswerte neu berechnen ab Datum“ (nur wenn wirklich gebraucht).

---

## 6) Backend-Funktionalität (Ansehen + Korrigieren)

### 6.1 Anzeige im Mitarbeiter-Formular
- In `views/mitarbeiter/formular.php` (oder eigener Unterseite) eine Sektion „Stundenkonto“:
  - „Saldo gesamt (bis Ende Vormonat)“
  - optional: „Monatssaldo aktuell“ / „Saldo inkl. Monat“

### 6.2 Manuelle Korrektur (Delta)
- Eingabefelder (nur mit Recht `STUNDENKONTO_VERWALTEN`):
  - Datum (wirksam, Default: heute)
  - Delta in Minuten oder Stunden (UI als Stunden, intern Minuten)
  - Begründung (Pflicht)

### 6.3 Saldo auf Wert setzen
- UI optional, aber praktisch:
  - Zielsaldo (Stunden)
  - System berechnet notwendiges Delta = Ziel - Ist und schreibt eine Korrekturzeile.
  - Begründung Pflicht.

### 6.4 Verteilbuchung (Rückwirk-Korrektur)
- UI (nur mit Recht):
  - `von_datum`, `bis_datum`
  - Modus:
    1) `gesamt_gleichmaessig` (z. B. -20h über alle Arbeitstage)
    2) `minuten_pro_tag` (z. B. -15 Minuten pro Arbeitstag)
  - Wert (gesamt Stunden / Minuten pro Tag)
  - Nur Arbeitstage (Default: ja)
  - Begründung (Pflicht)

- Umsetzung:
  - System ermittelt alle betroffenen **Arbeitstage** anhand der vorhandenen Arbeits-/Solllogik.
  - Erzeugt einen `stundenkonto_batch`.
  - Erzeugt pro Tag eine `stundenkonto_korrektur`-Zeile (mit `batch_id`).
  - Gleichmäßig verteilen:
    - auf Minutenbasis teilen, Restminuten fair verteilen (z. B. die ersten N Tage +1 Minute).

---

## 7) Terminal-Anforderungen

Ort: `views/terminal/start.php` Sub-View `?aktion=start&view=arbeitszeit`

Anforderungen:
- Neben Soll/Ist für Monat anzeigen:
  - `Gutstunden/Minusstunden gesamt (bis Ende Vormonat)`
- Anzeige nur, wenn Online/Haupt-DB verfügbar.
- Bei Offline: klarer Hinweis (wie beim Urlaub).

---

## 8) Monatsübersicht (HTML + PDF)

### 8.1 Monatsübersicht (HTML)
- In `views/report/monatsuebersicht.php` zusätzlich darstellen:
  - `Saldo vor Monat` (Stundenkonto bis Ende Vormonat)
  - `Monatssaldo` (bestehend: Über-/Minusstunden des Monats)
  - optional: `Saldo nach Monat`

Wichtig:
- `Saldo vor Monat` ist **exklusiv** der hier dargestellten Monatsstunden.

### 8.2 Monatsübersicht (PDF)
- In `services/PDFService.php` in der unteren Zusammenfassung:
  - Die bestehende Stunden-Zusammenfassung kann nach links geschoben werden.
  - Rechts daneben neuer Block mit mindestens:
    - „Urlaubstage (verfügbar) abzüglich geplante Betriebsferien“
    - „Stundenkonto (Saldo vor Monat)“

---

## 9) Logging / Audit

Jede Änderung am Stundenkonto muss nachvollziehbar sein:
- Bei manueller Korrektur oder Verteilbuchung:
  - Eintrag in `system_log` (oder Logger-Wrapper), inkl.
    - mitarbeiter_id,
    - wer hat gebucht,
    - delta_minuten,
    - wirksam_datum bzw. Bereich,
    - batch_id,
    - Begründung.

---

## 10) Patch-Splitting (verbindlich nach Master-Prompt)

Max. 3 Dateien pro Patch (inkl. `docs/DEV_PROMPT_HISTORY.md`). Deshalb: konsequent in Teilpatches.

Empfohlene Reihenfolge (nur Plan, nicht alles auf einmal):

1) **DB + Recht**
   - SQL-Migration: Tabellen `stundenkonto_batch`, `stundenkonto_korrektur` + Recht `STUNDENKONTO_VERWALTEN`.
   - Doku: `docs/rechte_prompt.md` aktualisieren.

2) **Service-Grundlage**
   - `services/StundenkontoService.php` erstellen.
   - Minimal: Methode `getSaldoVorMonatMinuten`.

3) **Terminal-Anzeige**
   - `views/terminal/start.php` (Arbeitszeit-Übersicht) um Saldo erweitern.
   - Controller-Anbindung nur falls nötig (ansonsten innerhalb bestehender Datenbeschaffung).

4) **Backend: Anzeige + manuelle Delta-Korrektur**
   - Mitarbeiter-Formular um Sektion erweitern.
   - POST-Handling im passenden Controller.
   - Zugriff nur mit Recht.

5) **Backend: Verteilbuchung**
   - Batch speichern, Tage ermitteln, Korrekturzeilen erzeugen.
   - Preview/Validierung (keine 0-Tage-Verteilung).

6) **Monatsübersicht (HTML) / PDF**
   - HTML: `Saldo vor Monat` anzeigen.
   - PDF: Layout erweitern (Urlaub/Betriebsferien + Saldo vor Monat).

Jeder Patch:
- Genau 1 sichtbarer Effekt.
- 1 Satz Akzeptanz.
- Tests als `php -l` für geänderte PHP-Dateien.

---

## 11) Akzeptanz (Gesamtfeature, wenn komplett)

- Terminal zeigt Saldo vor Monat als Gut- oder Minusstunden.
- Backend erlaubt Korrekturen mit Begründung und Rechtsschutz.
- Verteilbuchung erzeugt nachvollziehbare Tagesbuchungen (Minutenbasis) und ist auditierbar.
- Monatsübersicht (HTML + PDF) zeigt „Saldo vor Monat“ zusätzlich zum Monatssaldo.
