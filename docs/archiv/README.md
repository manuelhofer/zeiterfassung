# Archiv

Dieser Ordner enthaelt historische Prompts, Spezifikationen und den langen
Projektverlauf. Dateien hier sind Referenzmaterial und keine neue
Arbeitsanweisung, solange nicht ausdruecklich darauf verwiesen wird.

**Pro Datei begruendet:** [`ALTE_PROMPTS.md`](ALTE_PROMPTS.md) sagt fuer jede
Datei in diesem Ordner, was sie war, wann und warum sie archiviert wurde und
was davon heute noch gilt. Das ist der richtige Einstieg in diesen Ordner.

**Ausnahme:** [`DEV_PROMPT_HISTORY.md`](DEV_PROMPT_HISTORY.md) liegt zwar hier,
ist aber **aktiv** und wird bei jedem Patch weiter gepflegt.

## Aktuelle Orientierung

- `../../README.md`
- `../STATUS_SNAPSHOT.md`
- `../prompt_uebersicht.md`
- `../master_prompt_zeiterfassung_v13.md`
- `../rechte_prompt.md`

## Hinweise zu alten Verweisen

- Alte Pfade wie `docs/DEV_PROMPT_HISTORY.md` wurden nicht massenhaft
  umgeschrieben. Gemeint ist im aktuellen Projektstand
  `docs/archiv/DEV_PROMPT_HISTORY.md`.
- Alte Verweise auf `sql/zeiterfassung_aktuell.sql` bleiben historische
  Dump-/Patch-Verweise. Fuer Neuinstallationen gilt `sql/01_initial_schema.sql`.
- Alte ZIP-/Patch-Regeln (nur ZIP als Ausgabe, max. 3 Dateien pro Patch,
  SHA256-Nachweis) stammen aus dem frueheren Chat-Workflow und gelten seit
  Master-Prompt v13 nicht mehr. Gearbeitet wird direkt im Git-Workspace;
  ein ZIP wird nur auf ausdrueckliche Anforderung erstellt. Begruendung:
  `../master_prompt_zeiterfassung_v13.md`, Abschnitt 1a.
