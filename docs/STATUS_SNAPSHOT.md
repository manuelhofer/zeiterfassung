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

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

**Offline-Betrieb am Terminal** – Befund und Entscheidungen in P-2026-08-16-08,
die Regeln dazu in
[`fachregeln/terminal_und_offline.md`](fachregeln/terminal_und_offline.md),
Abschnitt 5. Reihenfolge ist Absicht: T-125 kommt zuletzt.

- **T-125** Lokale Liste der Berechtigten auf dem Terminal (nur ID,
  Personalnummer, RFID, aktiv – keine Namen), damit ein unbekannter Chip sofort
  am Gerät auffällt statt erst beim Einspielen.
- **T-126** Offline zeigt das Menü „Urlaub", „Übersicht" und „RFID-Chip
  zuweisen", obwohl alle drei offline nur in Fehlermeldungen enden; dazu nennt
  die Meldung „RFID-Code erfasst (ID …)" den Chipcode „ID" und der Hinweis
  „Bitte RFID-Chip an das Lesegerät halten" bleibt nach dem Scan stehen.
- **T-127** `TerminalController::start()` ermittelt den Queue-Status zweimal
  hintereinander identisch und stößt den Replay ein zweites Mal an, obwohl
  `public/terminal.php` das bei jedem Request schon tut.
- **T-128** Zwischen dem Commit auf der Hauptdatenbank und dem
  `UPDATE status='verarbeitet'` in der Queue gibt es keinen gemeinsamen
  Abschluss – fällt genau dazwischen der Strom aus, wird der Eintrag beim
  nächsten Start ein zweites Mal eingespielt.
- **T-129** Dass ein unbekannter Chip keine Buchung auf `mitarbeiter_id = 0`
  erzeugt, hängt allein am `sql_mode` des Servers; einen Fremdschlüssel, der
  das auffinge, hat `zeitbuchung` nicht.
- **T-132** Der Selbstcheck führt die Regel „wo liegt die Queue" noch selbst –
  zweimal in `controller/DashboardController.php`, zweimal in
  `controller/SmokeTestController.php` – und meldet auf einem Backend mit
  erreichbarer `offline_db` deshalb „offline DB OK", obwohl die Queue seit
  P-2026-08-16-13 in der Hauptdatenbank liegt.
- **T-131** `views/terminal/_autologout.php` liest die Einstellungen der
  RFID-Bridge aus `$konfig`, das in der View gar nicht existiert – es ist eine
  lokale Variable von `public/terminal.php`. Damit greifen weder
  `rfid_ws.enabled = false` noch eine abweichende `url`; es gilt immer der
  Default `ws://127.0.0.1:8765`. Nachgestellt in P-2026-08-16-12.
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
