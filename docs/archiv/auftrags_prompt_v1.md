# AUFTRAGS-PROMPT v1 – Auftragszeiterfassung per Scan (Terminal) + Auswertung (Backend)

*Version:* v1 (2026-01-18)
*Status:* Spezifikation (noch nicht umgesetzt). Umsetzung nur auf ausdrücklichen Auftrag und ohne Regressionen.

## 0. Zielbild (was der Nutzer will)

Ein Mitarbeiter bearbeitet an einer Maschine einen Auftrag. Auf dem Laufzettel sind mehrere Codes (Barcode/QR), die am Terminal gescannt werden:

1) **Auftragscode / Auftragsnummer** (steht oben auf dem Zettel)
2) **Arbeitsschritt-Code** (z. B. Fräsen/Drehen/Sägen – in der Praxis nur eine Nummer)
3) optional **Maschinen-Code** (Maschinen-ID als Scan-Code)

Die Zeiterfassung erfolgt pro **Arbeitsschritt** innerhalb eines **Auftrags**. Es wird nur mit Nummern/Codes gearbeitet; die semantische Bedeutung ist egal.

Wichtig:
- Ein **Auftrag kann mehrere Arbeitsschritte** haben.
- Ein Mitarbeiter muss für die Zeiterfassung **erst Auftrag, dann Arbeitsschritt** scannen. Maschine ist optional.
- Wenn ein Auftrag noch nicht bekannt ist, wird er beim ersten Start automatisch in der Auftragstabelle angelegt.
- Es dürfen **keine vorhandenen Funktionen entfernt** oder bestätigtes Verhalten geändert werden. Alles Neue muss bestehendes Verhalten unangetastet lassen.

---

## 1. Begriffe

- **Auftrag**: Identifiziert durch `auftragsnummer` / Scan-Code (String).
- **Arbeitsschritt**: Identifiziert durch Scan-Code (String/Nummer). Gehört logisch zu genau einem Auftrag.
- **Auftragszeit**: Zeitintervall (start/end) eines Mitarbeiters auf einem Arbeitsschritt eines Auftrags, optional mit Maschine.
- **Hauptauftrag / Nebenauftrag**: Im Terminal bereits vorhanden. Nebenauftrag nur zulässig, wenn ein Hauptauftrag läuft.

---

## 2. Datenmodell (Ist-Stand im Projekt + Erweiterung)

### 2.1 Bestehende Tabellen (bereits vorhanden)

- `auftrag` (minimal): `id`, `auftragsnummer` (UNIQUE), ...
- `auftragszeit`: `mitarbeiter_id`, `auftrag_id`, `auftragscode`, `maschine_id`, `terminal_id`, `typ` (haupt/neben), `startzeit`, `endzeit`, `status` (laufend/abgeschlossen/abgebrochen), ...

Im Code existieren bereits Skelett/Grundlogik:
- `modelle/AuftragModel.php`
- `modelle/AuftragszeitModel.php`
- `services/AuftragszeitService.php` (inkl. Offline-Queue-Ansatz)
- `controller/AuftragController.php` (der Bereich ist im Backend evtl. noch nicht verlinkt)

### 2.2 Nötige Erweiterung für Arbeitsschritte (vorgeschlagen)

**Ziel:** Arbeitsschritt muss pro Auftragszeit gespeichert und im Backend auswertbar sein.

Vorschlag (robust + normalisiert):

1) Neue Tabelle `auftrag_arbeitsschritt`:
   - `id` INT PK
   - `auftrag_id` INT FK -> `auftrag.id`
   - `arbeitsschritt_code` VARCHAR(100) (Scan-Code)
   - optional `bezeichnung` VARCHAR(255) NULL (später)
   - `aktiv` TINYINT(1) DEFAULT 1
   - `erstellt_am`, `geaendert_am`
   - UNIQUE(`auftrag_id`,`arbeitsschritt_code`)

2) In `auftragszeit` zusätzlich:
   - `arbeitsschritt_id` INT NULL FK -> `auftrag_arbeitsschritt.id`
   - optional zusätzlich (nur wenn gewünscht) `arbeitsschritt_code` VARCHAR(100) NULL (Denormalisierung für Offline/Debug)

**Minimal-Alternative (wenn maximal simpel gewünscht):** Nur Spalte `arbeitsschritt_code` in `auftragszeit` ohne Mapping-Tabelle.

**Empfehlung:** Tabelle + FK (oben), damit Auswertungen konsistent bleiben und Duplikate pro Auftrag vermieden werden.

### 2.3 Verhalten beim Start (Create-on-demand)

Beim Start einer Auftragszeit (nach Scans):

1) **Auftrag finden** über `auftrag.auftragsnummer = <auftragscode>`.
   - falls nicht vorhanden: **anlegen** (nur `auftragsnummer`, Rest NULL) und neue `auftrag_id` verwenden.
2) **Arbeitsschritt finden** über UNIQUE(`auftrag_id`,`arbeitsschritt_code`).
   - falls nicht vorhanden: **anlegen** und `arbeitsschritt_id` verwenden.
3) **Auftragszeit anlegen** mit:
   - `mitarbeiter_id`, `auftrag_id`, `auftragscode`, `arbeitsschritt_id` (und optional `maschine_id`), `typ`, `startzeit`.
4) Bei **Hauptauftrag starten**: alle laufenden Hauptaufträge des Mitarbeiters sauber beenden (bestehende Logik bleibt).

---

## 3. Terminal-Workflow (UI/Bedienlogik)

### 3.1 Button-Sichtbarkeit (unverändert lassen, nur ergänzen)

Regeln:
- Wenn **kein Hauptauftrag läuft**: Button **„Auftrag stoppen“** darf **nicht** angezeigt werden.
- Button **„Nebenauftrag starten“** darf **nur** angezeigt werden, wenn bereits ein Hauptauftrag läuft.
- Bestehende Buttons/Funktionen bleiben bestehen; neue Scan-Schritte dürfen nur zusätzlich eingeführt werden.

### 3.2 Scan-Sequenz beim Start

Beim Klick auf **„Auftrag starten“** (Hauptauftrag):

1) Terminal wechselt in einen **Scan-Dialog**.
2) Erwartete Scans in dieser Reihenfolge:
   - Scan 1: **Auftragscode** (Pflicht)
   - Scan 2: **Arbeitsschritt-Code** (Pflicht)
   - Scan 3: **Maschinen-Code** (Optional)
3) Nach Scan 2 (oder nach Scan 3, falls genutzt) wird der Auftrag gestartet.

Hinweise:
- Scanner ist typischerweise „Keyboard-Wedge“: der Code kommt als Text + Enter. Die UI soll das abfangen.
- Es ist egal, welche Nummern kommen; entscheidend ist die **Reihenfolge** im Dialog.

Beim Klick auf **„Nebenauftrag starten“**:
- identisch, aber `typ='neben'`.
- Nebenauftrag darf Hauptauftrag nicht löschen; er kann parallel laufen oder als „Unterbrechung“ gelten (siehe Offene Punkte).

### 3.3 Stop-Logik

Beim Klick auf **„Auftrag stoppen“**:
- Stoppt den aktuell laufenden Auftrag (typisch: laufender Hauptauftrag; ggf. eigener Button für Nebenauftrag-Stop, falls bereits vorhanden).
- Ein Stop benötigt **keinen erneuten Scan**.
- `endzeit` setzen, `status='abgeschlossen'`.

---

## 4. Backend-Workflow (Auswertung)

### 4.1 Navigation

Im Backend-Menü soll es einen Punkt **„Aufträge“** geben.

### 4.2 Auftrags-Suche

Seite „Aufträge“:
- Suchfeld nach **Auftragsnummer/Code** (Teilstring/LIKE).
- Trefferliste (Auftragsnummer, Status, Aktiv, zuletzt bearbeitet).

### 4.3 Auftrags-Detail

Beim Klick auf einen Auftrag:

- Liste aller erfassten Auftragszeiten zu diesem Auftrag, inkl.:
  - Arbeitsschritt-Code (oder Arbeitsschritt-ID + Code)
  - Mitarbeiter
  - Maschine (optional)
  - Startzeit, Endzeit
  - Dauer (Stunden)
  - Typ (haupt/neben) und Status

- Unten: **Gesamtsumme Stunden** (Summe aller Dauern, getrennt nach Typ optional).

Optional später:
- Filter (Zeitraum/Mitarbeiter/Maschine)
- Export (CSV/PDF)

---

## 5. Maschinen: Barcode/QR-Generator (optional)

Im Maschinen-Admin-Bereich soll ein Barcode/QR für jede Maschine erzeugbar sein.

Ziel:
- Maschinen-Code entspricht in erster Iteration der **Maschinen-ID** oder einer Maschinen-„Nummer“ (später).
- Generator erzeugt ein **PNG/JPG** (besser: PNG) und speichert den Pfad (oder Blob) am Maschinen-Datensatz.

Empfehlung:
- QR-Code (einfach, robust, scannerfreundlich).
- Speicherung z. B. unter `public/uploads/maschinen_codes/maschine_<id>.png`.

---

## 6. Randbedingungen / Safety

- **Keine Regressionen:** vorhandene Terminal- und Backend-Funktionen müssen unverändert bleiben.
- Alles Neue bevorzugt:
  - hinter Feature-Flag oder nur aktiv, wenn Auftrags-Scan explizit genutzt wird.
  - sauber validieren (leere Scans ignorieren; Whitespace trimmen).
- Offline-Queue: Wenn Terminal offline ist, muss Start/Stop weiter funktionieren; Arbeitsschritt muss im Offline-SQL mit gespeichert werden (wenn implementiert).

---

## 7. Offene Punkte (Entscheidungen des Nutzers)

Diese Punkte müssen nicht jetzt geklärt werden; wenn unklar, Default wie unten:

1) **Nebenauftrag-Logik**: Darf Nebenauftrag parallel laufen (Overlap) oder soll er den Hauptauftrag pausieren?
   - Default-Vorschlag: Nebenauftrag läuft parallel, aber Auswertung zeigt beides; optional später „Pausierung“.

2) **Maschinen-Code**: Soll der Scan-Code die Maschinen-ID sein, oder eine frei pflegbare Maschinen-Nummer?
   - Default-Vorschlag: ID in QR, später optional Maschinen-Nummer.

3) **Arbeitsschritt**: Soll Arbeitsschritt pro Auftrag eindeutig sein (UNIQUE), oder dürfen gleiche Codes mehrfach existieren?
   - Default-Vorschlag: UNIQUE pro Auftrag.

4) **Stop per Arbeitsschritt**: Soll „Stop“ den zuletzt gestarteten (laufenden) Arbeitsschritt stoppen, oder muss man einen Arbeitsschritt auswählen?
   - Default-Vorschlag: Stop beendet den (einzigen) laufenden Hauptauftrag; bei mehreren laufenden (neben) wird ggf. Auswahlseite angezeigt.

