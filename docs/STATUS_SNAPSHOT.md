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

**Der Gerätetest.** Kopplung und Skripte sind fertig und im Container geprüft;
was ein Container nicht zeigen kann, braucht jetzt einen Bildschirm. Die sieben
Prüfpunkte stehen als Protokoll in
[`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
Abschnitt 12, der Stufenplan in Abschnitt 11 – dort und bewusst nicht hier ein
zweites Mal.

## Offene Bugs

Keine bekannten.

## Offene Tasks
- **T-112** „`catch` → `return []`" steckt an 26 Stellen in `modelle/` und
  `services/`. Falsch ist es nur dort, wo es die Fehlermeldung des Aufrufers
  unerreichbar macht (B-097, T-110). Durchsehen, nicht pauschal ändern;
  Suchlauf in P-2026-08-15-10.
- **T-104** Vier Controller erzeugen HTML selbst, statt `views/` zu benutzen –
  **10 Masken**: `KonfigurationController` (3), `AuftragController` (4),
  `TerminalAdminController` (2), `SmokeTestController` (1). Eine Maske je
  Patch; Muster und Prüfweg: P-2026-08-11-09.

  Der `grep` nach dem Header-Require zählt zu hoch – er findet auch „Keine
  Berechtigung"-Blöcke. `UrlaubController` und `AuditLogController` sind
  **fertig**, nachgezählt in P-2026-08-14-06.

  Beim Bauen einer Maske erst in `views/layout/header.php` nachsehen und
  **keine eigenen Grössen auf Knöpfe schreiben** – Begründung steht dort.
- **T-105** `SmokeTestController::index()` ist eine einzige, riesige Methode.
  Diagnosewerkzeug, keine Fachlogik – aber praktisch nicht mehr änderbar. Es ist
  nur *eine* Maske aus T-104 und trotzdem der grösste Brocken darin.
- **Jahreswechsel beobachten:** Die Urlaubs-Übertragskette rechnet über
  laufendes Jahr und Vorjahr und schreibt das Ergebnis in
  `urlaub_kontingent_jahr` fest (B-080). Der erste echte Jahreswechsel steht
  noch aus – dann prüfen, ob die Salden plausibel bleiben.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Terminal-Buchungsflows sind unter PHP 8.5 noch
  nicht **im Browser** durchgeklickt. Die Buchungslogik selbst ist seit
  P-2026-08-10-22 geprüft, online wie offline.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
