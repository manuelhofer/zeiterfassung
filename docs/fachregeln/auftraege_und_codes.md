# Fachregeln: Auftraege, Auftragszeiten, Strichcodes, Laufkarte

*Gilt für:* `services/AuftragszeitService.php`, `services/BarcodeService.php`,
`controller/AuftragController.php`,
`controller/ArbeitsschrittKatalogController.php`.
*Herkunft:* Master-Prompt v13, Abschnitte 7 und 11.
*Vertiefung:* `docs/spezifikation_auftrag_barcode_laufkarte.md`.

---

## 1. Woher Auftraege kommen

Auftraege werden **nicht** in diesem System geführt, sondern zu 100 % in einem
externen CMS/ERP. In der Zeiterfassung wird nur die **Auftragsnummer**
verwendet.

Seit 2026-08-08 können Auftraege **zusätzlich** im Backend angelegt werden
(`?seite=auftrag_neu`) – als Ergaenzung, nicht als Ersatz. Wer seine Auftraege
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
`kurzbeschreibung`, `kunde`, `status`, `aktiv`, Timestamps. Diese Felder werden
**nicht** am Terminal gepflegt (Touch, keine Tastatur), sondern nur im Backend
oder über Importe.

**`auftragszeit`:** `mitarbeiter_id`, `auftrag_id` bzw. `auftragscode`,
`arbeitsschritt_code`, `maschine_id`, `terminal_id`, `typ` (`haupt`/`neben`),
`startzeit`, `endzeit`, `status`, `kommentar`, Timestamps.

**`arbeitsschritt_katalog`:** betriebsweite Standardschritte (z. B. `fraesen`)
für wiederkehrende Taetigkeiten – einmal pflegen, Codes beliebig oft
ausdrucken.

## 5. Haupt- und Nebenauftraege

Ein Mitarbeiter kann **einen** Hauptauftrag laufen haben und zusätzlich
**mehrere** Nebenauftraege gleichzeitig.

- **Hauptauftrag starten:** Auftragscode scannen, Maschine wählen/scannen; ein
  vorhandener Hauptauftrag wird automatisch geschlossen.
- **Nebenauftrag starten:** wie Hauptauftrag, aber `typ = 'neben'`. Serverseitig
  **geblockt, wenn kein Hauptauftrag läuft** (online wie offline).
- **Auftrag stoppen:** Standard ist der gescannte Auftragscode; Rückfall ist
  die Liste der laufenden Auftraege des Mitarbeiters.

**Start und Stopp eines Hauptauftrags beenden Nebenauftraege *nicht*
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

**Der Code enthaelt nur den nackten Wert**, damit das Terminal ihn unverändert
übernimmt.

Ausdrucke:

- **Laufkarte** je Auftrag (`?seite=auftrag_laufkarte&code=…`): Auftragskopf mit
  Code, je Arbeitsschritt ein Block mit Code und Feldern zum Eintragen,
  mehrseitig.
- **Kartenblatt** je Katalogschritt (`?seite=arbeitsschritt_katalog_blatt`) mit
  Stückzahl – z. B. 20 Karten `fraesen` für 20 Fraesmaschinen. Sechs Karten je
  A4-Seite mit Schnittmarkierung.

**Maschinen-Barcode-URL:** Die Anzeige-URL wird aus der Web-Basis der
Installation plus `maschinen_qr_rel_pfad` abgeleitet. `maschinen_qr_url` ist nur
noch ein Override (leer = automatisch, `/` = Domain-Root, sonst Pfad/URL).

Recht: `AUFTRAEGE_VERWALTEN` nur für das Pflegen. **Ansehen, Detailansicht und
Ausdrucke bleiben bewusst frei** – wer in der Werkstatt eine Laufkarte
nachdrucken muss, soll dafür kein Verwaltungsrecht brauchen.
