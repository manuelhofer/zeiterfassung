# Archiv

Dieser Ordner enthält historische Prompts, Spezifikationen und den langen
Projektverlauf. Dateien hier sind Referenzmaterial und keine neue
Arbeitsanweisung, solange nicht ausdrücklich darauf verwiesen wird.

**Pro Datei begründet:** [`ALTE_PROMPTS.md`](ALTE_PROMPTS.md) sagt für jede
Datei in diesem Ordner, was sie war, wann und warum sie archiviert wurde und
was davon heute noch gilt. Das ist der richtige Einstieg in diesen Ordner.

**Ausnahme:** [`DEV_PROMPT_HISTORY.md`](DEV_PROMPT_HISTORY.md) liegt zwar hier,
ist aber **aktiv** und wird bei jedem Patch weiter gepflegt.

## Aktuelle Orientierung

- `../../CHATSTART.md` – der Einstieg
- `../arbeitsregeln.md` – wie gearbeitet wird
- `../fachregeln/` – die Fachlogik, nach Thema getrennt
- `../STATUS_SNAPSHOT.md` – aktueller Stand
- `../rechte_prompt.md` – Rechte-Codes
- `../README.md` – Verzeichnis aller Dokumente

## Hinweise zu alten Verweisen

- **Gelöscht wird hier nichts.** Überholte Prompts werden in
  `ALTE_PROMPTS.md` eingeordnet und begründet, nicht entfernt; der Verlauf in
  `DEV_PROMPT_HISTORY.md` wird nie gekürzt. Wer nachlesen will, warum etwas
  früher anders galt, soll es finden.
- Alte Pfade wie `docs/DEV_PROMPT_HISTORY.md` wurden nicht massenhaft
  umgeschrieben. Gemeint ist im aktuellen Projektstand
  `docs/archiv/DEV_PROMPT_HISTORY.md`.
- Alte Verweise auf `sql/zeiterfassung_aktuell.sql` bleiben historische
  Dump-/Patch-Verweise. Für Neuinstallationen gilt `sql/01_initial_schema.sql`.
- Alte ZIP-/Patch-Regeln (nur ZIP als Ausgabe, max. 3 Dateien pro Patch,
  SHA256-Nachweis) stammen aus dem früheren Chat-Workflow und gelten seit
  Master-Prompt v13 nicht mehr. Gearbeitet wird direkt im Git-Workspace;
  ein ZIP wird nur auf ausdrückliche Anforderung erstellt. Begründung:
  `master_prompt_zeiterfassung_v13.md`, Abschnitt 1a.
