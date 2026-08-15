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

- **B-101** Die Auftragsliste zeigt bei einem Ladefehler beides: „Die Aufträge
  konnten nicht geladen werden." und „Keine Aufträge vorhanden." – derselbe
  Fehler, den P-2026-08-15-09 (B-096) für sechs andere Listen behoben hat.

## Offene Tasks
- **T-112** „`catch` → `return []`" steckt an 26 Stellen in `modelle/` und
  `services/`. Falsch ist es nur dort, wo es die Fehlermeldung des Aufrufers
  unerreichbar macht (B-097, T-110). Durchsehen, nicht pauschal ändern;
  Suchlauf in P-2026-08-15-10.
- **T-104** Noch **eine** Maske erzeugt ihr HTML selbst statt in `views/`:
  `SmokeTestController` (siehe T-105). Muster und Prüfweg: P-2026-08-11-09,
  zuletzt P-2026-08-15-33; keine eigenen Grössen auf Knöpfe (Begründung in
  `views/layout/header.php`).
- **T-120** Die Katalog-Auswahl steht zweimal fast gleich in `views/auftrag/`
  (`formular.php`, `detail.php`) – erst nach der letzten T-104-Maske
  entscheiden, ob ein gemeinsames Teil-Template lohnt.
- **T-105** `SmokeTestController::index()` ist eine einzige, riesige Methode:
  Diagnosewerkzeug ohne Fachlogik, praktisch nicht mehr änderbar – nur *eine*
  Maske aus T-104 und trotzdem der grösste Brocken darin.
- **Jahreswechsel beobachten:** Die Urlaubs-Übertragskette schreibt ihr Ergebnis
  in `urlaub_kontingent_jahr` fest (B-080). Beim ersten echten Jahreswechsel
  prüfen, ob die Salden plausibel bleiben.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Terminal-Buchungsflows sind unter PHP 8.5 noch
  nicht **im Browser** durchgeklickt. Die Buchungslogik selbst ist seit
  P-2026-08-10-22 geprüft, online wie offline.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
