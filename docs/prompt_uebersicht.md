# Prompt-Uebersicht

Diese Datei ist die kurze Orientierung fuer kuenftige Arbeit am Projekt. Sie
ersetzt keine bestehenden Prompts, sondern klaert, welche Datei wofuer gedacht
ist.

## Aktive Orientierung

- `docs/STATUS_SNAPSHOT.md`
  - Kurzer aktueller Projektstatus.
  - Erste Datei lesen, wenn nur der Stand gebraucht wird.
- `docs/master_prompt_zeiterfassung_v12.md`
  - Aktiver Master-Prompt mit Projektregeln und Architekturvorgaben.
  - Enthaelt oben eine lokale Klarstellung fuer den aktuellen Workspace
    (History-Pfad, DB-Source-of-Truth, direkte Workspace-Aenderungen).
- `docs/rechte_prompt.md`
  - Source of Truth fuer Rechte-Codes und Berechtigungslogik.
- `docs/wartungscheckliste.md`
  - Praktische Checkliste vor/nach Aenderungen.

## Archiv und Verlauf

- `docs/archiv/README.md`
  - Kurzer Hinweis, wie der Archivordner zu lesen ist.
- `docs/archiv/DEV_PROMPT_HISTORY.md`
  - Voller Projektverlauf und grosser Snapshot.
  - Hinweis: Aeltere Prompttexte nennen haeufig `docs/DEV_PROMPT_HISTORY.md`.
    In diesem Projektstand liegt die reale History-Datei unter
    `docs/archiv/DEV_PROMPT_HISTORY.md`.
- `docs/archiv/dev_prompt_zeiterfassung_v12.md`
  - Kurzer Dev-Prompt v12, historisch/archiviert.
- `docs/archiv/dev_prompt_zeiterfassung_v11.md`
  - Kurzer Dev-Prompt v11, historisch/archiviert.
- `docs/archiv/master_prompt_zeiterfassung_v11.md`
  - Alte Master-Prompt-Version, nur Referenz.
- `docs/archiv/auftrags_prompt_v1.md`
  - Spezifikation zum Auftragsmodul; viele Punkte sind inzwischen umgesetzt.
- `docs/archiv/report_mehrfachbloecke_prompt_v1.md`
  - Historische Report-Spezifikation.
- `docs/archiv/zusatz2promt.md`
  - Historischer Stundenkonto-Scope.

## Arbeitsregel fuer neue Aenderungen

1. Zuerst `docs/STATUS_SNAPSHOT.md` lesen.
2. Dann bei Bedarf `docs/archiv/DEV_PROMPT_HISTORY.md` oben im Snapshot und die
   letzten relevanten Patch-Eintraege lesen.
3. Bei Rechten immer `docs/rechte_prompt.md` pruefen.
4. Bei Codeaenderungen klein bleiben: ein Thema, wenige Dateien, danach
   Syntaxcheck und passende manuelle Kernablaeufe aus der Wartungscheckliste.

## Was beim Aufraeumen bewusst nicht getan wurde

- Alte Archiv-Prompts wurden nicht geloescht.
- Grosse History-Dateien wurden nicht gekuerzt.
- Veraltete Pfade in historischen Eintraegen wurden nicht massenhaft ersetzt,
  damit die Historie nachvollziehbar bleibt.
