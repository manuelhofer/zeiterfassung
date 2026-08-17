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

**Diesen Stand nach `main` bringen.** Die Prüfungen, die der Sitzung vom 17.08.
gefehlt haben, sind nachgeholt – örtlich, gegen MariaDB (P-2026-08-17-22):
Prüfskript 46 von 46, alle Admin-Masken für einen Chef offen, die umgebauten
Smoke-Test-Masken byteweise gleich, Serverlogs ohne PHP-Meldung.

Zwei Befunde daraus sind als eigene Patches erledigt: der Aufruf des Prüfskripts
konnte nie funktionieren (P-2026-08-17-20), und die Feiertag+Arbeitszeit-Prüfung
konnte nie anschlagen (P-2026-08-17-21).

## Offene Bugs

Keine bekannten.

## Offene Tasks

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

- **Gerätetest am Terminal** – Kopplung und Skripte sind fertig und im Container
  geprüft, das Gerät ist frühestens ab ca. Mitte September verfügbar; Protokoll
  und Stufenplan in
  [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
  Abschnitt 12 und 11.
- **T-142** Aus dem `SmokeTestController` sind die fachlichen Prüfungen heraus;
  offen bleiben die drei PDF-Prüfungen und `pruefeTerminalLogin` als je eigenes
  Vorhaben, letzteres erst nach dem Gerätetest.
- **Feiertag+Arbeitszeit im Bestand nachsehen** – die Prüfung kann seit
  P-2026-08-17-21 anschlagen; ob in der Entwicklungsdatenbank aus dem Serverdump
  Tage mit Ist- **und** Feiertagsstunden stehen, hat noch niemand gefragt.
- **Hilfeausgabe von `pruefumgebung.sh`** schneidet den Kopfkommentar mit einer
  fest verdrahteten Zeilennummer heraus; sie driftet bei jeder Kopfänderung
  (aufgefallen in P-2026-08-17-20).

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
