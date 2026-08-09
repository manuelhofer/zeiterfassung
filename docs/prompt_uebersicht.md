# Prompt-Uebersicht

Kurze Orientierung: Welche Datei ist wofuer zustaendig, und was ist nur noch
Referenz?

Der eigentliche Einstieg – auch fuer KI-Assistenten – ist
[`CHATSTART.md`](../CHATSTART.md) in der Projektwurzel. Dort steht die
Lesekarte, welche Datei zu welcher Aufgabe gehoert.

## Aktiv

| Datei | Wofuer |
| --- | --- |
| [`CHATSTART.md`](../CHATSTART.md) | Einstieg fuer jede KI und jedes Werkzeug: Projekt in Kurzform, Regeln in Kurzform, Lesekarte |
| [`CLAUDE.md`](../CLAUDE.md) | Nur ein Verweis auf `CHATSTART.md` plus wenige Claude-Code-Besonderheiten. Bewusst ohne Projektinhalte |
| [`README.md`](../README.md) | Einstieg fuer Menschen nach dem Klonen: was das Projekt ist, wie es lokal laeuft |
| [`docs/arbeitsregeln.md`](arbeitsregeln.md) | **Verbindlich fuer jede Aenderung:** Patch-Zuschnitt, Patch-ID, Pre-Flight-Gate, Pflichtpruefungen, Code-Stil, PHP-Baseline |
| [`docs/fachregeln/`](fachregeln/) | Die Fachlogik, nach Thema getrennt. Nur das Passende lesen |
| [`docs/STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md) | Aktueller Stand, offene Bugs, naechster Schritt |
| [`docs/rechte_prompt.md`](rechte_prompt.md) | Source of Truth fuer Rechte-Codes und ihre Pruefpunkte |
| [`docs/wartungscheckliste.md`](wartungscheckliste.md) | Praktische Checkliste vor und nach Aenderungen |
| [`docs/spezifikation_auftrag_barcode_laufkarte.md`](spezifikation_auftrag_barcode_laufkarte.md) | Auftraege im Backend, Arbeitsschritte, Katalog, Strichcodes, Laufkarte (umgesetzt) |
| [`docs/spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md) | Terminal per Skript aufsetzen und koppeln. Stufe 1 und 2 umgesetzt, Stufe 3–6 offen |
| [`docs/lokale_entwicklungsumgebung.md`](lokale_entwicklungsumgebung.md) | Lokale Umgebung (Apache + php-fpm + MariaDB + phpMyAdmin) |
| [`docs/installationsanleitung.md`](installationsanleitung.md) | Produktivinstallation auf Debian/Apache |

## Archiv und Verlauf

- [`docs/archiv/ALTE_PROMPTS.md`](archiv/ALTE_PROMPTS.md) – **Begruendungsliste
  zum Archiv:** pro Datei steht dort, was sie war, wann und warum sie
  archiviert wurde und was davon noch gilt.
- [`docs/archiv/README.md`](archiv/README.md) – wie der Archivordner zu lesen
  ist.
- [`docs/archiv/DEV_PROMPT_HISTORY.md`](archiv/DEV_PROMPT_HISTORY.md) – voller
  Projektverlauf mit Snapshot oben. Wird weiterhin **bei jedem Patch**
  gepflegt; sie liegt nur deshalb im Archivordner, weil dort der Verlauf
  gesammelt ist.
  Hinweis: Aeltere Texte nennen haeufig `docs/DEV_PROMPT_HISTORY.md`.
- [`docs/archiv/master_prompt_zeiterfassung_v13.md`](archiv/master_prompt_zeiterfassung_v13.md)
  – der frueher aktive Master-Prompt. Sein Inhalt steckt jetzt in
  `docs/arbeitsregeln.md` und `docs/fachregeln/`; er wird nur noch fuer
  historische Fragen gebraucht (z. B. Abschnitt 1a: warum die v12-Regeln
  entfielen).
- Aeltere Master- und Dev-Prompts (v11, v12), `auftrags_prompt_v1.md`,
  `report_mehrfachbloecke_prompt_v1.md`, `zusatz2promt.md` – reines
  Referenzmaterial, einzeln begruendet in `ALTE_PROMPTS.md`.

## Arbeitsregel fuer neue Aenderungen

Vollstaendig in [`docs/arbeitsregeln.md`](arbeitsregeln.md). In Kurzform:

1. `docs/STATUS_SNAPSHOT.md` lesen, dann den „Naechster Schritt"-Block der
   History und `git log --oneline`.
2. Nur die passende Datei aus `docs/fachregeln/` lesen.
3. Bei Rechten immer `docs/rechte_prompt.md` pruefen.
4. Klein bleiben: ein Thema, ein Akzeptanzkriterium. Danach `php -l` und die
   passenden Kernablaeufe aus der Wartungscheckliste.
5. History im selben Commit pflegen, Patch-ID in den Commit-Betreff.

## Was beim Aufraeumen bewusst nicht getan wurde

- Alte Archiv-Prompts wurden **nicht geloescht**, sondern in `ALTE_PROMPTS.md`
  eingeordnet und begruendet.
- Der volle Verlauf in der History wurde **nicht gekuerzt**.
- Veraltete Pfade und ZIP-Verweise in historischen Eintraegen wurden **nicht**
  massenhaft ersetzt, damit die Historie nachvollziehbar bleibt.
