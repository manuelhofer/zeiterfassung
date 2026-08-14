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

Keine bekannten.

## Offene Tasks
- **T-104** Vier Controller erzeugen HTML selbst, statt `views/` zu benutzen –
  **12 Masken**: `KonfigurationController` (5), `AuftragController` (4),
  `TerminalAdminController` (2), `SmokeTestController` (1). Eine Maske je
  Patch; Muster und Prüfweg: P-2026-08-11-09.

  `grep -rn "require __DIR__ . '/../views/layout/header.php'" controller/`
  liefert mehr Treffer als offene Masken: 5 davon sind dreizeilige „Keine
  Berechtigung"-Blöcke (Urlaub 3, AuditLog 1, Auftrag 1), eine ist eine fertig
  migrierte Maske (`views/auftragszeit/bearbeiten.php`). `UrlaubController` und
  `AuditLogController` sind deshalb **fertig** – zählen, ohne aufzuschlagen,
  hat sie in P-2026-08-14-03 zu Unrecht auf die Liste gebracht.

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
