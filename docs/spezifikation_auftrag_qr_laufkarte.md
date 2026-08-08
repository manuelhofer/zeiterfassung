# Spezifikation: Auftraege mit QR-Codes und Laufkarte

*Version:* v1 (2026-08-08)
*Status:* in Umsetzung
*Grundlage:* `docs/master_prompt_zeiterfassung_v13.md`, Abschnitte 7 (Auftraege)
und 11 (Auftragszeiten)

---

## 1. Zielbild

Heute entstehen Auftraege und Arbeitsschritte **nur nebenbei**: Das Terminal
nimmt beim Scannen jeden beliebigen Text an und legt fehlende Datensaetze
automatisch an (`INSERT ... ON DUPLICATE KEY UPDATE` in
`AuftragszeitService::starteAuftrag`). Das ist bewusst so und bleibt auch so –
es darf nie passieren, dass in der Halle eine Buchung scheitert, weil ein
Stammdatensatz fehlt.

Zusaetzlich soll es moeglich sein, Auftraege **vorher im Backend anzulegen**:

1. Einen Auftrag anlegen (Nummer, Kurzbeschreibung, Kunde, Status).
2. Zu diesem Auftrag Arbeitsschritte pflegen – z. B. `drehen`, `fraesen`,
   `saegen` – jeweils mit Bezeichnung.
3. Fuer Auftrag und jeden Arbeitsschritt gibt es einen **QR-Code**.
4. Alles zusammen laesst sich als **Laufkarte** (PDF) ausdrucken und dem
   Werkstueck beilegen. Der Mitarbeiter scannt am Terminal vom Papier: erst den
   Auftrags-QR, dann den QR des Arbeitsschritts, den er beginnt.

## 2. Was der QR-Code enthaelt

**Nur den nackten Code – kein Praefix, keine URL, kein JSON.**

| Code | Inhalt | Beispiel |
| --- | --- | --- |
| Auftrag | Wert aus `auftrag.auftragsnummer` | `123123123123` |
| Arbeitsschritt | Wert aus `auftrag_arbeitsschritt.arbeitsschritt_code` | `drehen` |

Begruendung: Das Terminal liest die Felder `auftragscode` und
`arbeitsschritt_code` als reinen Text (`TerminalController`, Zeilen 2613/2614)
und reicht sie unveraendert an `AuftragszeitService::starteAuftrag()` weiter.
Ein Scanner im Keyboard-Wedge-Modus tippt den QR-Inhalt also direkt in das
Formularfeld. Damit funktionieren die gedruckten Codes **ohne jede Aenderung am
Terminal** – das ist die wichtigste Randbedingung dieser Erweiterung.

Arbeitsschritt-Codes sind pro Auftrag eindeutig (`UNIQUE (auftrag_id,
arbeitsschritt_code)`), nicht global. Das ist ausreichend, weil am Terminal
immer zuerst der Auftrag gescannt wird und die Laufkarte ohnehin zu genau einem
Auftrag gehoert.

## 3. Datenbank

Die vorhandenen Tabellen reichen aus, es sind **keine Strukturaenderungen**
noetig:

- `auftrag` – `auftragsnummer` (unique), `kurzbeschreibung`, `kunde`, `status`,
  `aktiv`
- `auftrag_arbeitsschritt` – `auftrag_id`, `arbeitsschritt_code`,
  `bezeichnung`, `aktiv`

QR-Bilder werden **nicht** in der Datenbank vermerkt. Sie sind aus dem Code
jederzeit reproduzierbar und werden bei Bedarf erzeugt; ein zusaetzliches
Pfad-Feld waere nur eine weitere Stelle, die aus dem Tritt geraten kann.
(Bei Maschinen gibt es `code_bild_pfad` historisch – das bleibt unangetastet.)

## 4. Funktionsumfang

### 4.1 Auftragsliste (`?seite=auftrag`)

- Neuer Button **„Auftrag hinzufuegen“**.
- **Wichtig:** Die Liste baut heute ausschliesslich auf `auftragszeit` auf. Ein
  frisch angelegter Auftrag ohne Buchung waere unsichtbar. Die Abfrage muss
  daher auch Auftraege aus der Tabelle `auftrag` beruecksichtigen, die noch
  keine Buchung haben (Buchungen = 0, Status „angelegt“).

### 4.2 Auftrag anlegen / bearbeiten (`?seite=auftrag_neu`, `?seite=auftrag_bearbeiten`)

- Felder: Auftragsnummer (Pflicht, eindeutig), Kurzbeschreibung, Kunde, Status,
  Aktiv.
- Doppelte Auftragsnummern werden mit einer verstaendlichen Meldung abgelehnt,
  nicht mit einem SQL-Fehler.
- Speichern per POST mit CSRF-Token, danach Weiterleitung auf die
  Detailansicht.

### 4.3 Auftragsdetail (`?seite=auftrag_detail&code=…`)

Bestehende Bloecke (Buchungen, Summen) bleiben unveraendert. Neu darunter:

- **Auftrags-QR-Code** mit Downloadlink.
- **Arbeitsschritte (Stammdaten)** als Tabelle: Code, Bezeichnung, QR-Code,
  Aktiv, Aktionen. Das ist bewusst getrennt von der bestehenden Auswertung
  „Arbeitsschritte (Summe, abgeschlossen)“, die aus Buchungen kommt.
- Formular **„Arbeitsschritt hinzufuegen“** (Code + Bezeichnung).
- Bearbeiten und Deaktivieren vorhandener Schritte. Geloescht wird nicht,
  solange Buchungen daran haengen koennen – `aktiv = 0` genuegt.

### 4.4 Laufkarte (`?seite=auftrag_laufkarte&code=…`)

PDF im Format A4 hoch:

- Kopf: Auftragsnummer gross, Kunde, Kurzbeschreibung, Druckdatum, daneben der
  Auftrags-QR-Code.
- Danach je Arbeitsschritt ein Block: laufende Nummer, Code, Bezeichnung,
  QR-Code sowie freie Felder zum handschriftlichen Eintragen (Datum, Name,
  Menge) – es ist eine Laufkarte fuer die Werkstatt.
- Mehrere Seiten, wenn die Schritte nicht auf eine Seite passen.

**Technischer Hinweis:** `PDFService` ist ein handgeschriebener PDF-Writer ohne
Bildunterstuetzung. Die QR-Codes werden deshalb **als Vektor gezeichnet**: Die
Bibliothek liefert mit `QRcode::text()` die Modulmatrix, jedes dunkle Modul wird
ein gefuelltes Rechteck (`pdfRectFill`). Das ist beim Drucken schaerfer als ein
eingebettetes Pixelbild, erzeugt kleinere Dateien und erspart eine
XObject-Implementierung im PDF-Writer.

## 5. Rechte

- Neues Recht **`AUFTRAEGE_VERWALTEN`**: Auftraege und Arbeitsschritte anlegen,
  bearbeiten, deaktivieren.
- **Unveraendert:** Auftragsliste, Detailansicht und Laufkarte bleiben fuer alle
  angemeldeten Benutzer sichtbar. Wer in der Werkstatt eine Laufkarte
  nachdrucken muss, soll dafuer kein Verwaltungsrecht brauchen.
- Der Chef ist Superuser und hat das Recht automatisch.
- Dokumentation in `docs/rechte_prompt.md` (Source of Truth).

## 6. Akzeptanzkriterien

1. Ein im Backend angelegter Auftrag ohne jede Buchung erscheint in der
   Auftragsliste und laesst sich oeffnen.
2. Zu einem Auftrag lassen sich Arbeitsschritte `drehen`, `fraesen`, `saegen`
   anlegen; jeder erscheint in der Stammdaten-Tabelle mit eigenem QR-Code.
3. Die Laufkarte enthaelt den Auftrags-QR und je einen QR pro aktivem
   Arbeitsschritt und laesst sich als PDF oeffnen und drucken.
4. Ein aus der Laufkarte gescannter Arbeitsschritt-QR liefert im
   Terminal-Formularfeld exakt den `arbeitsschritt_code` – die Buchung laeuft
   ohne Terminal-Aenderung durch.
5. Ohne das Recht `AUFTRAEGE_VERWALTEN` sind Anlege- und Bearbeitungsfunktionen
   nicht erreichbar, Ansicht und Laufkarte dagegen schon.

## 7. Bewusst nicht Teil dieser Erweiterung

- Das automatische Anlegen beim Scannen bleibt bestehen (siehe Abschnitt 1).
- Keine Mengen-/Stueckzahlverwaltung, keine Terminplanung, keine
  Reihenfolge-Erzwingung der Arbeitsschritte.
- Kein Loeschen von Auftraegen; Deaktivieren reicht.
- Keine Aenderung am Terminal.
