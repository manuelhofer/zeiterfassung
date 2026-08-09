# Spezifikation: Auftraege mit Strichcodes, Laufkarte und Arbeitsschritt-Katalog

*Version:* v2 (2026-08-08)
*Status:* umgesetzt (P-2026-08-08-06 bis -16, -24)
*Hinweis:* Die erste Fassung sah QR-Codes vor; umgestellt auf Code 128, weil im
Betrieb 1D-Handscanner im Einsatz sind. Der Dateiname hiess bis P-2026-08-08-24
`spezifikation_auftrag_qr_laufkarte.md`.
*Grundlage:* `docs/fachregeln/auftraege_und_codes.md`

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

## 2. Codetyp und Inhalt

### Warum Strichcode (Code 128) und nicht QR

Im Betrieb sind **1D-Handscanner** im Einsatz, und die Maschinen-Codes des
Projekts sind bereits Code 128. Ein einziger Codetyp bedeutet: ein Scannertyp,
keine Sonderfaelle, keine Schulung. Code 128 kann Buchstaben, Ziffern und
Sonderzeichen – deshalb steht im Strichcode weiterhin **der Code selbst**
(z. B. `fraesen`); eine kuenstliche Nummer waere unnoetig und wuerde die
Auswertungen unleserlich machen.

(Die erste Fassung dieser Spezifikation sah QR-Codes vor. Umgestellt am
2026-08-08 auf Wunsch aus der Praxis – siehe P-2026-08-08-15.)

### Was der Code enthaelt

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

Code-Bilder werden **nicht** in der Datenbank vermerkt. Sie sind aus dem Code
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
- **Alles ausser der Auftragsnummer ist freiwillig.** Das hier ist ein
  Zeiterfassungssystem, keine Warenwirtschaft: Wer Kunde oder Beschreibung
  pflegen will, kann es; wer nicht, dem fehlt nichts. Leere Felder erscheinen
  auch nicht auf der Laufkarte.
- Doppelte Auftragsnummern werden mit einer verstaendlichen Meldung abgelehnt,
  nicht mit einem SQL-Fehler.
- Speichern per POST mit CSRF-Token, danach Weiterleitung auf die
  Detailansicht.

### 4.3 Auftragsdetail (`?seite=auftrag_detail&code=…`)

Bestehende Bloecke (Buchungen, Summen) bleiben unveraendert. Neu darunter:

- **Auftrags-Strichcode** mit Downloadlink.
- **Arbeitsschritte (Stammdaten)** als Tabelle: Code, Bezeichnung, QR-Code,
  Aktiv, Aktionen. Das ist bewusst getrennt von der bestehenden Auswertung
  „Arbeitsschritte (Summe, abgeschlossen)“, die aus Buchungen kommt.
- Formular **„Arbeitsschritt hinzufuegen“** (Code + Bezeichnung).
- Bearbeiten und Deaktivieren vorhandener Schritte. Geloescht wird nicht,
  solange Buchungen daran haengen koennen – `aktiv = 0` genuegt.

### 4.4 Laufkarte (`?seite=auftrag_laufkarte&code=…`)

PDF im Format A4 hoch:

- Kopf: Auftragsnummer gross, Kunde, Kurzbeschreibung, Druckdatum, daneben der
  Auftrags-Strichcode.
- Danach je Arbeitsschritt ein Block: laufende Nummer, Code, Bezeichnung,
  Strichcode sowie freie Felder zum handschriftlichen Eintragen (Datum, Name,
  Menge) – es ist eine Laufkarte fuer die Werkstatt.
- Mehrere Seiten, wenn die Schritte nicht auf eine Seite passen.

**Technischer Hinweis:** `PDFService` ist ein handgeschriebener PDF-Writer ohne
Bildunterstuetzung. Die Strichcodes werden deshalb **als Vektor gezeichnet**: Die
Bibliothek liefert die Balkenfolge, jeder Balken wird ein gefuelltes Rechteck
(`pdfRectFill`). Das ist beim Drucken schaerfer als ein
eingebettetes Pixelbild, erzeugt kleinere Dateien und erspart eine
XObject-Implementierung im PDF-Writer.

## 4a. Arbeitsschritt-Katalog (Erweiterung v2, 2026-08-08)

### Das Problem

Arbeitsschritte je Auftrag zu pflegen ist richtig, wenn der Schritt zum
Werkstueck gehoert („Aussendurchmesser auf 40 mm drehen“). Fuer die
**wiederkehrenden Taetigkeiten** ist es aber unnoetige Arbeit: `fraesen` ist
bei jedem Auftrag dasselbe `fraesen`. Es waere absurd, das fuer jeden neuen
Auftrag erneut anzulegen.

### Zielbild

Die Arbeitsvorbereitung pflegt einmal einen **Katalog** von Standardschritten:
`saegen`, `drehen`, `fraesen`, `entgraten`, `pruefen`, …

Der Strichcode eines Katalogschritts haengt **an der Maschine**, nicht auf dem
Papier. Wer 20 Fraesmaschinen hat, druckt den Code `fraesen` 20-mal aus und
haengt ihn an jede Maschine. Der Mitarbeiter scannt am Terminal:

1. den Auftrags-Strichcode von der Laufkarte (welches Werkstueck),
2. den Arbeitsschritt-Strichcode von der Maschine (welche Taetigkeit).

Damit passt derselbe gedruckte Code zu **jedem** Auftrag.

### Warum das ohne Terminal-Aenderung funktioniert

`AuftragszeitService::starteAuftrag()` legt einen fehlenden Arbeitsschritt zum
Auftrag automatisch an (`INSERT … ON DUPLICATE KEY UPDATE`). Wird `fraesen`
fuer Auftrag `A-2026-0999` gescannt, entsteht dort genau ein Eintrag
`auftrag_arbeitsschritt(auftrag_id=…, arbeitsschritt_code='fraesen')`. Der
Katalog ist also eine **Vorlage**, keine zweite Buchungsquelle – gezaehlt wird
weiterhin ueber `auftragszeit`.

### Datenbank

Neue Tabelle `arbeitsschritt_katalog`: `code` (global eindeutig),
`bezeichnung`, `sort_order`, `aktiv`.

Global eindeutig ist hier richtig – anders als bei `auftrag_arbeitsschritt`,
wo der Code nur pro Auftrag eindeutig sein muss. Ein an der Maschine haengender
Code muss betriebsweit dasselbe bedeuten.

### Funktionen

- **Katalogverwaltung** unter `?seite=arbeitsschritt_katalog` (Menue
  „Auftraege“): Liste mit Strichcode-Vorschau, Anlegen, Bearbeiten, Deaktivieren.
- **Druckblatt** `?seite=arbeitsschritt_katalog_blatt`:
  - ohne Parameter: alle aktiven Katalogschritte als Uebersicht (eine Karte je
    Schritt),
  - mit `id` und `anzahl`: derselbe Schritt in frei waehlbarer Stueckzahl –
    genau der Fall „20-mal `fraesen`“.
  - Karten mit Schnittmarkierung, damit sie sich ausschneiden und an die
    Maschine haengen lassen.
- **Uebernahme in einen Auftrag:** Im Auftragsdetail lassen sich
  Katalogschritte per Mehrfachauswahl uebernehmen. Sie erscheinen dann auf der
  Laufkarte. Bereits vorhandene Codes werden uebersprungen, nicht doppelt
  angelegt.
- **Bezeichnung beim Scannen:** Legt das Terminal einen Arbeitsschritt
  automatisch an und der Code steht im Katalog, wird die Bezeichnung von dort
  uebernommen. Sonst stuenden in der Auswertung nur nackte Codes. Streng
  defensiv: nie eine vorhandene Bezeichnung ueberschreiben, und ein Fehler
  dabei darf eine Buchung niemals verhindern.

### Abgrenzung

- Der Katalog **erzwingt nichts**. Ein am Terminal gescannter Code, der nicht
  im Katalog steht, wird weiterhin angenommen und gezaehlt – wie bisher.
- Keine Reihenfolge, keine Pflichtschritte, keine Vorgabezeiten.

## 5. Rechte

- Neues Recht **`AUFTRAEGE_VERWALTEN`**: Auftraege, deren Arbeitsschritte und
  den Arbeitsschritt-Katalog anlegen, bearbeiten, deaktivieren.
  Fuer den Katalog wird bewusst **kein eigenes Recht** eingefuehrt: Wer
  Auftraege pflegen darf, pflegt auch die Vorlagen dafuer. Ein weiteres Recht
  waere zusaetzliche Verwaltung ohne erkennbaren Nutzen.
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
3. Die Laufkarte enthaelt den Auftrags-Strichcode und je einen Strichcode pro aktivem
   Arbeitsschritt und laesst sich als PDF oeffnen und drucken.
4. Ein aus der Laufkarte gescannter Arbeitsschritt-Strichcode liefert im
   Terminal-Formularfeld exakt den `arbeitsschritt_code` – die Buchung laeuft
   ohne Terminal-Aenderung durch.
5. Ohne das Recht `AUFTRAEGE_VERWALTEN` sind Anlege- und Bearbeitungsfunktionen
   nicht erreichbar, Ansicht und Laufkarte dagegen schon.
6. Ein Katalogschritt `fraesen` laesst sich einmal anlegen, 20-mal auf ein
   Druckblatt bringen und in beliebige Auftraege uebernehmen.
7. Wird ein Katalog-Code am Terminal fuer einen Auftrag gescannt, bei dem er
   noch nicht hinterlegt ist, entsteht der Arbeitsschritt automatisch – mit der
   Bezeichnung aus dem Katalog.

## 7. Bewusst nicht Teil dieser Erweiterung

- Das automatische Anlegen beim Scannen bleibt bestehen (siehe Abschnitt 1).
- Keine Mengen-/Stueckzahlverwaltung, keine Terminplanung, keine
  Reihenfolge-Erzwingung der Arbeitsschritte.
- Kein Loeschen von Auftraegen; Deaktivieren reicht.
- Keine Aenderung am Terminal.
