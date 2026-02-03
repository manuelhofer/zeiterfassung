# Zeiterfassung & Mitarbeiter-/Auftragsmanagement – Projektstruktur (Skeleton)

Dieses Verzeichnis enthält Projektdokumentation, u. a. den Master-Prompt.
Der eigentliche PHP-Code befindet sich in den Verzeichnissen `core`, `controller`,
`modelle`, `services` und `views`.

## Prompt-Archivierung

- Aktueller Kurzstatus: [`docs/STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md)
- Voller Verlauf: [`docs/archiv/DEV_PROMPT_HISTORY.md`](archiv/DEV_PROMPT_HISTORY.md)

Historische Prompts liegen künftig nur in `docs/archiv` und werden dort bewusst
kurz gehalten: Es bleiben pro Prompt nur die letzten ein bis zwei Versionen
(typisch: aktuelle Version im Wurzelverzeichnis `docs/` und die direkt
vorherige Version in `docs/archiv`). Ältere Stände werden entfernt oder in ein
externes Archiv (separates Repo oder Release-Artefakt) ausgelagert.
