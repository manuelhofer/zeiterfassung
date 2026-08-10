# Status-Snapshot

**Die einzige Stelle für den aktuellen Stand:** Projektstatus, nächster Schritt,
offene Bugs und Tasks. Wer wissen will, was ansteht, liest diese Datei und sonst
nichts.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdrücklicher Beauftragung**.

## Nächster Schritt (konkret)

**Der Gerätetest.** Voraussetzung erfüllt seit dem 09.08.2026: Kopplung und
Skripte sind fertig, alle sechs Stufen gebaut und im Container geprüft. Was ein
Container nicht zeigen kann, braucht jetzt einen Bildschirm.

Die sieben Prüfpunkte stehen als Protokoll in
[`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
Abschnitt 12 – dort und bewusst nicht hier ein zweites Mal. Dasselbe gilt für
den Stufenplan (Abschnitt 11).

## Offene Bugs
- **B-093: Abteilungsbezogene Rollenzuweisungen wirken nicht.** Die
  Mitarbeiterverwaltung kann eine Rolle mit `scope_typ = 'abteilung'` und
  `gilt_unterbereiche` speichern, aber `AuthService::ladeRechteCodesAusDb()`
  liest aus `mitarbeiter_hat_rolle_scope` **nur** Zeilen mit
  `scope_typ = 'global'`. Eine abteilungsbezogene Zuweisung gewährt damit gar
  nichts – ohne Fehlermeldung, die Maske sieht aus, als hätte sie gewirkt.
  Gefunden bei der Doku-Prüfung in P-2026-08-10-09. Vorher klären, was gewollt
  ist: Scope-Auswertung nachbauen (dann Abschnitt 3+4 der Fachregel als
  Zielbild nehmen) oder die Scope-Auswahl aus der Oberfläche nehmen.
- **B-080: Der Übertrag bricht die Kette – Resturlaub aus dem Vorvorjahr geht
  verloren.** Am 10.08.2026 reproduziert, damit ist die alte Meldung
  „Urlaubsberechnung stimmt nicht" konkret: Die Maske für 2025 zeigt 25,00 Tage
  Rest, die Maske für 2026 übernimmt daraus **−5,00** – 30 Tage weg. Ursache:
  `berechneUrlaubssaldoFuerJahr()` rechnet das Vorjahr mit
  `autoUebertrag = false`, alle neun Aufrufstellen der Oberfläche aber mit
  `true`. Betrifft jeden, der zwei Jahre hintereinander Resturlaub hatte.
  **Ungelöst, weil es um echte Urlaubstage geht – drei Wege stehen zur Wahl,
  vollständige Analyse in P-2026-08-10-24.**

## Offene Tasks
- **T-102** Buchungen tragen keine `terminal_id`, obwohl sie seit
  P-2026-08-09-01 in `config.local.php` (`terminal.id`) steht.
- **T-104** Neun Controller erzeugen HTML selbst, statt `views/` zu benutzen –
  größter Brocken `SmokeTestController` (283 Zeilen), dann
  `KonfigurationController` (201), `AuftragController` (137).
- **T-105** `SmokeTestController::index()` ist eine Methode mit ~3.700 Zeilen.
  Diagnosewerkzeug, keine Fachlogik – aber praktisch nicht mehr änderbar.
- **T-108** Drei Zugriffswege auf `db_injektionsqueue`: `OfflineQueueManager`,
  `QueueService`, `DbInjektionsqueueModel`. Zusammenführen – berührt den
  Offline-Pfad, deshalb nur mit Offline-Test.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Terminal-Buchungsflows sind unter PHP 8.5 noch
  nicht **im Browser** mit angemeldetem Mitarbeiter durchgeklickt. Die
  Buchungslogik selbst ist seit P-2026-08-10-22 geprüft, online wie offline.
- Auftragsmodul: Scan-Flow/UX nur bei Bedarf verfeinern.
- Terminal (Auftrag): Stop-Detailmaske (Fallback) UX vereinfachen.
