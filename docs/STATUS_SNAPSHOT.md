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

**T-138, Patch 3 von 3:** Lokale Maschinenliste (`maschine_spiegel`) für die
Maschinenauswahl beim Auftragsstart. Vorgehen und Prüfung stehen in
[`spezifikation_offline_auftraege.md`](spezifikation_offline_auftraege.md),
Abschnitt 5. Patch 1 und 2 sind erledigt (P-2026-08-17-29, -30) – Aufträge
lassen sich offline starten und stoppen.

## Offene Bugs

- **B-106** Ein Auftragscode, der beim Buchen scheitert, hinterlässt trotzdem
  einen leeren Auftrag – `auftrag_ensure` legt ihn an, bevor die Buchung
  abbricht. Betrifft online wie offline.

## Offene Tasks

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

- **Gerätetest am Terminal** – Kopplung und Skripte sind fertig und im Container
  geprüft, das Gerät ist frühestens ab ca. Mitte September verfügbar; Protokoll
  und Stufenplan in
  [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
  Abschnitt 12 und 11.
- **T-142** Aus dem `SmokeTestController` sind die fachlichen und die
  PDF-Prüfungen heraus; offen bleibt `pruefeTerminalLogin`, und das erst nach
  dem Gerätetest.

**Offline-Betrieb am Terminal** – Befund und Entscheidungen in P-2026-08-16-08,
die Regeln dazu in
[`fachregeln/terminal_und_offline.md`](fachregeln/terminal_und_offline.md),
Abschnitt 5. Die Aufgabenkette daraus ist abgearbeitet; offen bleibt der
zweite Schritt, für den T-125 die Voraussetzung war:

- **T-138** Aufträge im Offline-Betrieb – spezifiziert in
  [`spezifikation_offline_auftraege.md`](spezifikation_offline_auftraege.md),
  Abschnitt 5: drei Patches in fester Reihenfolge, alle ohne Gerät prüfbar.
- **Jahreswechsel beobachten:** Beim ersten echten Jahreswechsel prüfen, ob die
  festgeschriebenen Urlaubssalden plausibel bleiben (B-080).
- **Terminal im Browser:** „Gehen" und „Auftrag starten/stoppen" sind am Gerät
  nie durchgeklickt worden; „Kommen" offline samt Wiederanlauf ist es
  (eingegrenzt in P-2026-08-16-17, offen aus P-2026-08-08-02).
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
