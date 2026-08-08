# Prompt-Uebersicht

Diese Datei ist die kurze Orientierung fuer die Arbeit am Projekt: Welche
Datei ist wofuer zustaendig, und was ist nur noch Referenz?

## Aktive Orientierung

- `README.md` (Projektwurzel)
  - Einstieg nach dem Klonen: Was ist das Projekt, wie startet man es lokal,
    welche Doku in welcher Reihenfolge.
- `docs/master_prompt_zeiterfassung_v13.md`
  - **Der aktive Master-Prompt.** Projektregeln, Arbeitsweise, Architektur und
    vollstaendige Fachlogik. Bei Widerspruch zu aelteren Texten gilt diese
    Datei.
  - Abschnitt 1a erklaert, welche Regeln aus v12 entfallen sind und warum.
- `docs/STATUS_SNAPSHOT.md`
  - Kurzer aktueller Projektstatus. Erste Datei lesen, wenn nur der Stand
    gebraucht wird.
- `docs/rechte_prompt.md`
  - Source of Truth fuer Rechte-Codes und Berechtigungslogik.
- `docs/wartungscheckliste.md`
  - Praktische Checkliste vor/nach Aenderungen.
- `docs/spezifikation_auftrag_barcode_laufkarte.md`
  - Aktive Spezifikation: Auftraege im Backend anlegen, Arbeitsschritte und
    Arbeitsschritt-Katalog mit Strichcodes (Code 128), Laufkarte und
    Kartenblatt als PDF. Solange in Umsetzung, gilt sie als Auftrag; danach
    wandert sie ins Archiv.
- `docs/lokale_entwicklungsumgebung.md`
  - Lokale Umgebung (Apache + php-fpm + MariaDB + phpMyAdmin), damit die App
    im Browser laeuft.
- `docs/installationsanleitung.md`
  - Produktivinstallation auf Debian/Apache.

## Archiv und Verlauf

- `docs/archiv/ALTE_PROMPTS.md`
  - **Begruendungsliste zum Archiv:** pro Datei steht dort, was sie war, wann
    und warum sie archiviert wurde und was davon noch gilt.
- `docs/archiv/README.md`
  - Kurzer Hinweis, wie der Archivordner zu lesen ist.
- `docs/archiv/DEV_PROMPT_HISTORY.md`
  - Voller Projektverlauf und grosser Snapshot. Wird weiterhin bei jedem Patch
    gepflegt (also nicht "erledigt", sondern nur im Archivordner abgelegt).
  - Hinweis: Aeltere Prompttexte nennen haeufig `docs/DEV_PROMPT_HISTORY.md`.
    In diesem Projektstand liegt die reale History-Datei unter
    `docs/archiv/DEV_PROMPT_HISTORY.md`.
- `docs/archiv/master_prompt_zeiterfassung_v12.md`
  - Vorgaenger des aktiven Master-Prompts (bis 2026-08-08).
- `docs/archiv/master_prompt_zeiterfassung_v11.md`
  - Aeltere Master-Prompt-Version, nur Referenz.
- `docs/archiv/dev_prompt_zeiterfassung_v12.md`,
  `docs/archiv/dev_prompt_zeiterfassung_v11.md`
  - Kurzfassungen aus der Chat-Arbeitsweise. Entfallen ersatzlos; ihr
    YAML-Kopf (`output: zip_only`, `max_files_per_patch: 3`) ist ueberholt.
- `docs/archiv/auftrags_prompt_v1.md`
  - Spezifikation zum Auftragsmodul; groesstenteils umgesetzt.
- `docs/archiv/report_mehrfachbloecke_prompt_v1.md`
  - Historische Report-Spezifikation (Mehrfachbloecke, Mikro-Buchungen).
- `docs/archiv/zusatz2promt.md`
  - Historischer Stundenkonto-Scope; umgesetzt.

## Arbeitsregel fuer neue Aenderungen

1. Zuerst `docs/STATUS_SNAPSHOT.md` lesen.
2. Dann bei Bedarf `docs/archiv/DEV_PROMPT_HISTORY.md` (Snapshot oben plus die
   letzten relevanten Patch-Eintraege) und `git log`.
3. Bei Rechten immer `docs/rechte_prompt.md` pruefen.
4. Bei Codeaenderungen klein bleiben: ein Thema, ein Akzeptanzkriterium,
   danach Syntaxcheck (`php -l`) und die passenden manuellen Kernablaeufe aus
   der Wartungscheckliste.
5. History im selben Commit pflegen, Patch-ID in den Commit-Betreff.

## Was beim Aufraeumen bewusst nicht getan wurde

- Alte Archiv-Prompts wurden nicht geloescht, sondern in
  `docs/archiv/ALTE_PROMPTS.md` eingeordnet und begruendet.
- Grosse History-Dateien wurden nicht gekuerzt.
- Veraltete Pfade und ZIP-Verweise in historischen Eintraegen wurden nicht
  massenhaft ersetzt, damit die Historie nachvollziehbar bleibt.
