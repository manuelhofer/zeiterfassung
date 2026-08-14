# Status-Snapshot

**Die einzige Stelle für den aktuellen Stand:** Projektstatus, nächster Schritt,
offene Bugs und Tasks. Wer wissen will, was ansteht, liest diese Datei und sonst
nichts.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdrücklicher Beauftragung**.
- **Es gibt keinen Produktivbetrieb.** Keine Installation im Einsatz, keine
  Mitarbeiter, die damit stempeln, keine Daten, an denen etwas hängt.
  Produktivbetrieb beginnt **erst**, wenn Manuel ausdrücklich sagt: „Jetzt
  gehen wir in den Produktivbetrieb." Bis dahin gilt jede Formulierung wie
  „im laufenden Betrieb", „im Produktivbestand nachsehen" oder „betrifft
  Anwender" als **falsch** – sie erzeugt Dringlichkeit, die es nicht gibt,
  und Arbeit, die niemand braucht. Ein behobener Fehler betrifft den Test,
  sonst nichts.

## Nächster Schritt (konkret)

**Der Gerätetest.** Voraussetzung erfüllt seit dem 09.08.2026: Kopplung und
Skripte sind fertig, alle sechs Stufen gebaut und im Container geprüft. Was ein
Container nicht zeigen kann, braucht jetzt einen Bildschirm.

Die sieben Prüfpunkte stehen als Protokoll in
[`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
Abschnitt 12 – dort und bewusst nicht hier ein zweites Mal. Dasselbe gilt für
den Stufenplan (Abschnitt 11).

## Offene Bugs
- **B-095:** `abteilung_admin_speichern`, `maschine_admin_speichern`,
  `feiertag_admin_speichern`, `betriebsferien_admin_speichern` und
  `maschine_admin_barcode_neu` schreiben ohne CSRF-Prüfung; geprüft wird nur
  beim Umschalten (P-2026-08-11-09). `maschine_admin_barcode_neu` schreibt
  zusätzlich per **GET** – ein `<img src>` genügt.
  Kein Zählbefehl dazu: Zwei der Controller schreiben über Modelle, ein Muster
  über `INSERT INTO` findet sie nicht. Die fünf Routen liest man nach.

## Offene Tasks
- **T-104** Sechs Controller erzeugen HTML selbst, statt `views/` zu benutzen:
  `AuftragController` und `KonfigurationController` (je 6 Masken),
  `UrlaubController` (3), `TerminalAdminController` (2), `AuditLogController`
  und `SmokeTestController` (je 1) – zusammen 19. Eine Maske je Patch; Muster
  und Prüfweg: P-2026-08-11-09. Nachzählen statt glauben:
  `grep -rn "views/layout/header.php" controller/`
  Beim Bauen einer Maske erst in `views/layout/header.php` nachsehen, was es
  schon gibt, und **keine eigenen Grössen auf Knöpfe schreiben** – warum, steht
  dort als Kommentar.
- **T-105** `SmokeTestController::index()` ist eine Methode mit ~3.700 Zeilen.
  Diagnosewerkzeug, keine Fachlogik – aber praktisch nicht mehr änderbar. Es ist
  nur *eine* Maske aus T-104 und trotzdem der grösste Brocken darin.
- **Jahreswechsel beobachten:** Die Urlaubs-Übertragskette rechnet über
  laufendes Jahr und Vorjahr und schreibt das Ergebnis in
  `urlaub_kontingent_jahr` fest (B-080). Der erste echte Jahreswechsel steht
  noch aus – dann prüfen, ob die Salden plausibel bleiben.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Terminal-Buchungsflows sind unter PHP 8.5 noch
  nicht **im Browser** mit angemeldetem Mitarbeiter durchgeklickt. Die
  Buchungslogik selbst ist seit P-2026-08-10-22 geprüft, online wie offline.
- Auftragsmodul: Scan-Flow/UX nur bei Bedarf verfeinern.
- Terminal (Auftrag): Stop-Detailmaske (Fallback) UX vereinfachen.
