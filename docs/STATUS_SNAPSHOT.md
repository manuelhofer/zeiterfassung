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

**Die Patches vom 17.08. örtlich nachprüfen.** Sie sind in einem Container ohne
MariaDB entstanden: `php -l` und statische Kontrollen sind gelaufen, **Klicktest
und Prüfumgebung nicht**. Was zu prüfen ist, steht je Patch im Verlauf unter
TEST, mit den Aufrufen zum Kopieren. Zwei Dinge zuerst:

1. `scripts/dev/pruefumgebung.sh aufbauen`, dann
   `scripts/dev/pruefumgebung.sh pruefen` – der erste Lauf überhaupt. Erwartet:
   46 Fälle grün. Was dabei abweicht, ist erst ein Befund über die Erwartung,
   dann einer über den Code.
2. Der Klicktest für P-2026-08-17-11 (Legacy-Admin-Fallback, 19 Dateien): Fällt
   eine Admin-Maske für einen Chef zu, liegt die Ursache dort.

Erst danach gehört dieser Stand nach `main`.

## Offene Bugs

Keine bekannten.

## Offene Tasks

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

- **Gerätetest am Terminal** – Kopplung und Skripte sind fertig und im Container
  geprüft, das Gerät ist frühestens ab ca. Mitte September verfügbar; Protokoll
  und Stufenplan in
  [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
  Abschnitt 12 und 11.
- **T-140** Das nachrechnende Prüfskript ist gebaut
  ([`spezifikation_fachlogik_pruefskript.md`](spezifikation_fachlogik_pruefskript.md));
  offen ist allein der erste grüne Lauf gegen `zeit_probe` – siehe „Nächster
  Schritt".
- **T-142** Aus dem `SmokeTestController` sind die fachlichen Prüfungen heraus;
  offen bleiben die drei PDF-Prüfungen und `pruefeTerminalLogin` als je eigenes
  Vorhaben, letzteres erst nach dem Gerätetest.

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
