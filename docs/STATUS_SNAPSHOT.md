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
- **T-128** Zwischen dem Commit auf der Hauptdatenbank und dem
  `UPDATE status='verarbeitet'` in der Queue gibt es keinen gemeinsamen
  Abschluss – fällt genau dazwischen der Strom aus, wird der Eintrag beim
  nächsten Start ein zweites Mal eingespielt.
- **T-129** Dass ein unbekannter Chip keine Buchung auf `mitarbeiter_id = 0`
  erzeugt, hängt allein am `sql_mode` des Servers; einen Fremdschlüssel, der
  das auffinge, hat `zeitbuchung` nicht.
- **T-133** Aufräumen ohne sichtbaren Effekt: Drei Stellen in
  `controller/TerminalController.php` wählen die Queue-Datenbank selbst statt
  über den `OfflineQueueManager`, `ZeitService` und `AuftragszeitService` haben
  je eine private `istTerminalInstallation()` neben der in `Helper`, und drei
  `Helper`-Methoden lesen `config/config.php` selbst statt über
  `Start::konfig()`. Heute liefert alles dasselbe – der Unterschied entsteht,
  wenn jemand eine der Regeln wieder ändert (so geschehen in P-2026-08-16-14).
- **T-112** „`catch` → `return []`" an 26 Stellen in `modelle/` und `services/`
  durchsehen – falsch nur dort, wo es die Fehlermeldung des Aufrufers
  unerreichbar macht; Suchlauf in P-2026-08-15-10.
- **Jahreswechsel beobachten:** Beim ersten echten Jahreswechsel prüfen, ob die
  festgeschriebenen Urlaubssalden plausibel bleiben (B-080).
- **Terminal im Browser:** „Gehen" und „Auftrag starten/stoppen" sind am Gerät
  nie durchgeklickt worden; „Kommen" offline samt Wiederanlauf ist es
  (eingegrenzt in P-2026-08-16-17, offen aus P-2026-08-08-02).
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
