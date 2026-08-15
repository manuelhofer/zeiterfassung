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

Ein Satz je Bug – die Begründung steht im Verlauf, nicht hier.

- **B-104** `?seite=dashboard&smoke=1` endet mit HTTP 500, weil der Selbsttest
  `cal_days_in_month()` aufruft – die Erweiterung `calendar` steht in keiner
  Installationsanleitung des Projekts; gefunden in P-2026-08-15-39.

## Offene Tasks

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

- **T-105** `SmokeTestController::index()` ist weiterhin eine einzige Methode;
  das Markup ist raus (P-2026-08-15-37), die fünfzehn Check-Blöcke gehören in
  eigene Methoden.
- **T-112** „`catch` → `return []`" an 26 Stellen in `modelle/` und `services/`
  durchsehen – falsch nur dort, wo es die Fehlermeldung des Aufrufers
  unerreichbar macht; Suchlauf in P-2026-08-15-10.
- **Jahreswechsel beobachten:** Beim ersten echten Jahreswechsel prüfen, ob die
  festgeschriebenen Urlaubssalden plausibel bleiben (B-080).
- **Terminal im Browser:** Die Buchungsflows sind unter PHP 8.5 noch nicht
  durchgeklickt (offen aus P-2026-08-08-02).
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
