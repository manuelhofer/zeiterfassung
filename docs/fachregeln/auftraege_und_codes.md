# Fachregeln: Aufträge, Auftragszeiten, Strichcodes, Laufkarte

*Gilt für:* `services/AuftragszeitService.php`, `services/BarcodeService.php`,
`controller/AuftragController.php`,
`controller/ArbeitsschrittKatalogController.php`.
*Herkunft:* Master-Prompt v13, Abschnitte 7 und 11.
*Vertiefung:* `docs/spezifikation_auftrag_barcode_laufkarte.md`.

---

## 1. Woher Aufträge kommen

Aufträge werden **nicht** in diesem System geführt, sondern zu 100 % in einem
externen CMS/ERP. In der Zeiterfassung wird nur die **Auftragsnummer**
verwendet.

Seit 2026-08-08 können Aufträge **zusätzlich** im Backend angelegt werden
(`?seite=auftrag_neu`) – als Ergänzung, nicht als Ersatz. Wer seine Aufträge
weiterhin nur scannt, merkt davon nichts.

Ausser der Auftragsnummer ist **alles freiwillig**. Dies ist ein
Zeiterfassungssystem, keine Warenwirtschaft: Leere Felder erscheinen auch nicht
auf dem Ausdruck.

## 2. Die eiserne Regel des Terminals

> **Eine Buchung darf niemals daran scheitern, dass ein Stammdatensatz fehlt.**

Das Terminal nimmt jeden gescannten Code an und legt fehlende Stammdaten selbst
an (`AuftragszeitService::starteAuftrag`). Dort darf auch nie ein fehlendes
**Recht** im Weg stehen – `AUFTRAEGE_VERWALTEN` gilt ausschließlich für die
Pflege im Backend.

## 3. Scan-Verhalten

Bei „Hauptauftrag/Nebenauftrag starten" erwartet die UI **einen Scan** (RFID
oder Barcode) der Auftragsnummer:

- Es gibt ein Eingabefeld, in das der Scanner den Code schreibt.
- Der Code wird kurz sichtbar (ca. 50–100 ms), danach wird automatisch ENTER
  ausgelöst und der Request abgeschickt.
- Der Anwender hat keine Gelegenheit, den Code zu bearbeiten – das Feld dient
  nur der Sichtkontrolle.

Auftrags-RFIDs dürfen **nicht** mit Mitarbeiter-RFIDs kollidieren – notfalls
über das Format oder über die Erkennung nach Kontext.

Der Maschinen-Scan ist tolerant (Ziffern werden aus dem Scan-Text gezogen), das
Maschinenfeld ist ein Textfeld und die Maschine ist optional.

## 4. Tabellen

**`auftrag`** (optional/minimal): `auftragsnummer` (UNIQUE), optional
`kurzbeschreibung`, `kunde`, `zeichnungsnummer`, `status`, `aktiv`, Timestamps.
Diese Felder werden **nicht** am Terminal gepflegt (Touch, keine Tastatur),
sondern nur im Backend oder über Importe.

Die **Zeichnungsnummer** ist wie alles ausser der Auftragsnummer freiwillig. Sie
steht in der Auftragsliste, wird von der Suche mitgefunden und erscheint auf der
Laufkarte – leer bleibt sie auch dort unsichtbar.

Die **Suche** in der Auftragsliste ist ein einziges Feld für vier Spalten:
Auftragsnummer, Kunde, Zeichnungsnummer und Kurzbeschreibung. Wer „Muster GmbH"
im Kopf hat, tippt das und soll nicht vorher entscheiden müssen, in welcher
Spalte es steht. `%` und `_` werden maskiert und suchen sich selbst.

**`auftragszeit`:** `mitarbeiter_id`, `auftrag_id` bzw. `auftragscode`,
`arbeitsschritt_code`, `maschine_id`, `terminal_id`, `typ` (`haupt`/`neben`),
`startzeit`, `endzeit`, `status`, `kommentar`, Timestamps.

**`arbeitsschritt_katalog`:** betriebsweite Standardschritte (z. B. `fraesen`)
für wiederkehrende Tätigkeiten – einmal pflegen, Codes beliebig oft
ausdrucken.

## 5. Haupt- und Nebenaufträge

Ein Mitarbeiter kann **einen** Hauptauftrag laufen haben und zusätzlich
**mehrere** Nebenaufträge gleichzeitig.

- **Hauptauftrag starten:** Auftragscode scannen, Maschine wählen/scannen; ein
  vorhandener Hauptauftrag wird automatisch geschlossen.
- **Nebenauftrag starten:** wie Hauptauftrag, aber `typ = 'neben'`. Serverseitig
  **geblockt, wenn kein Hauptauftrag läuft** (online wie offline).
- **Auftrag stoppen:** Standard ist der gescannte Auftragscode; Rückfall ist
  die Liste der laufenden Aufträge des Mitarbeiters.

**Start und Stopp eines Hauptauftrags beenden Nebenaufträge *nicht*
automatisch** (online wie offline). Das war zwischenzeitlich anders und hat sich
im Praxis-Test als unerwünscht erwiesen (P-2026-01-18-35 → -36).

Der Arbeitsschritt-Code ist beim Start eines Hauptauftrags **Pflicht**
(Server-Validierung, nicht nur im Formular).

Im Startbildschirm sind die Auftrags-Knoepfe kontextabhängig: Laeuft ein
Hauptauftrag, wird „Auftrag starten" ausgeblendet und „Auftrag stoppen" als
Primaeraktion gezeigt – das verhindert Doppelstarts.

## 6. Strichcodes und Ausdrucke

Für Auftrag und Arbeitsschritte werden **Strichcodes (Code 128)** erzeugt –
derselbe Codetyp wie bei den Maschinen, passend zu den 1D-Handscannern im
Betrieb.

**Der Code enthält nur den nackten Wert**, damit das Terminal ihn unverändert
übernimmt.

Ausdrucke:

- **Laufkarte** je Auftrag (`?seite=auftrag_laufkarte&code=…`): Auftragskopf mit
  Code, je Arbeitsschritt ein Block mit Code und Feldern zum Eintragen,
  mehrseitig.
- **Kartenblatt** je Katalogschritt (`?seite=arbeitsschritt_katalog_blatt`) mit
  Stückzahl – z. B. 20 Karten `fraesen` für 20 Fräsmaschinen. Sechs Karten je
  A4-Seite mit Schnittmarkierung.

**Maschinen-Barcode-URL:** Die Anzeige-URL wird aus der Web-Basis der
Installation plus `maschinen_qr_rel_pfad` abgeleitet. `maschinen_qr_url` ist nur
noch ein Override (leer = automatisch, `/` = Domain-Root, sonst Pfad/URL).

Recht: `AUFTRAEGE_VERWALTEN` nur für das Pflegen. **Ansehen, Detailansicht und
Ausdrucke bleiben bewusst frei** – wer in der Werkstatt eine Laufkarte
nachdrucken muss, soll dafür kein Verwaltungsrecht brauchen.
