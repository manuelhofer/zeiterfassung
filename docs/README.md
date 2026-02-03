# Zeiterfassung & Mitarbeiter-/Auftragsmanagement – Projektstruktur (Skeleton)

Dieses Verzeichnis enthält Projektdokumentation, u. a. den Master-Prompt.
Der eigentliche PHP-Code befindet sich in den Verzeichnissen `core`, `controller`,
`modelle`, `services` und `views`.

## Prompt-Archivierung

Historische Prompts liegen künftig nur in `docs/archiv` und werden dort bewusst
kurz gehalten: Es bleiben pro Prompt nur die letzten ein bis zwei Versionen
(typisch: aktuelle Version im Wurzelverzeichnis `docs/` und die direkt
vorherige Version in `docs/archiv`). Ältere Stände werden entfernt oder in ein
externes Archiv (separates Repo oder Release-Artefakt) ausgelagert.
