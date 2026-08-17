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

**T-140, das nachrechnende Prüfskript.** Der Gerätetest wäre der nächste Schritt,
aber das Gerät fehlt noch rund einen Monat – er steht deshalb unter „Offene
Tasks". Ohne Gerät ist T-140 die lohnendste Arbeit: Die Rechenkerne sind die
Stellen, an denen ein Fehler still bleibt – der Klicktest zeigt eine Zahl, aber
niemand erkennt an 8:36 statt 8:30, dass sie falsch ist, und die Prüfumgebung
vergleicht zwei Stände, nicht Soll und Ist. Erst spezifizieren, dann bauen.

## Offene Bugs

Keine bekannten.

## Offene Tasks

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

- **Gerätetest am Terminal** – Kopplung und Skripte sind fertig und im Container
  geprüft, das Gerät ist frühestens ab ca. Mitte September verfügbar; Protokoll
  und Stufenplan in
  [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
  Abschnitt 12 und 11.
- **T-140** Ein Prüfskript, das nachrechnet: feste Eingaben, erwartetes Ergebnis
  („Kommen 07:03, Gehen 16:12, Rundung auf 15 Minuten → 8:30 und 30 min Pause"),
  ein Aufruf auf der Kommandozeile, ohne Composer. Die Prüfungen in
  `SmokeTestController` sind der Ausgangspunkt. Erst spezifizieren.
- **T-142** Die größten Controller und Services aufteilen – je Datei ein
  eigenes Vorhaben, erst spezifizieren.

**Offline-Betrieb am Terminal** – Befund und Entscheidungen in P-2026-08-16-08,
die Regeln dazu in
[`fachregeln/terminal_und_offline.md`](fachregeln/terminal_und_offline.md),
Abschnitt 5. Die Aufgabenkette daraus ist abgearbeitet; offen bleibt der
zweite Schritt, für den T-125 die Voraussetzung war:

- **T-138** Anmeldung und Aufträge im Offline-Betrieb – braucht zusätzlich eine
  Anwesenheitslogik ohne Hauptdatenbank und, wenn beim Auftragsstart eine
  Maschine gewählt wird, eine lokale Maschinenliste. Eigenes Vorhaben, erst
  spezifizieren.
- **Jahreswechsel beobachten:** Beim ersten echten Jahreswechsel prüfen, ob die
  festgeschriebenen Urlaubssalden plausibel bleiben (B-080).
- **Terminal im Browser:** „Gehen" und „Auftrag starten/stoppen" sind am Gerät
  nie durchgeklickt worden; „Kommen" offline samt Wiederanlauf ist es
  (eingegrenzt in P-2026-08-16-17, offen aus P-2026-08-08-02).
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
