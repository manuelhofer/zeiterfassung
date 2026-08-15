# Alte Prompts – was archiviert wurde und warum

Diese Datei ist die Begründungsliste zum Archivordner. Für **jede** Datei in
`docs/archiv/` steht hier:

- was sie war,
- wann und warum sie archiviert wurde,
- was davon **heute noch gilt**.

Grundregel im Projekt: **Es wird nichts gelöscht.** Alte Prompts bleiben
lesbar, damit nachvollziehbar ist, warum das System so gebaut ist, wie es
gebaut ist. Sie sind aber **keine gültige Arbeitsanweisung** mehr – gültig
sind [`docs/arbeitsregeln.md`](../arbeitsregeln.md) und die Dateien in
[`docs/fachregeln/`](../fachregeln/); der Einstieg dazu ist
[`CHATSTART.md`](../../CHATSTART.md).

---

## Warum überhaupt so viele alte Prompts?

Das Projekt wurde über lange Zeit mit ChatGPT entwickelt, **ohne Zugriff auf
das Repository**. Der komplette Projektstand musste deshalb in Prompt-Dateien
mitgeführt und bei jedem Schritt neu hochgeladen werden. Daraus sind
mehrere parallele Textsorten entstanden:

- **Master-Prompt** – das vollständige Regelwerk (Fachlogik + Meta-Regeln),
- **Dev-Prompt** – eine Kurzfassung davon für den täglichen Gebrauch,
- **Zusatz-/Themen-Prompts** – Spezifikationen für einzelne neue Module,
- **History** – der fortlaufende Verlauf als Gedächtnisersatz.

Seit v13 wird direkt im Git-Workspace gearbeitet. Der Verlauf steckt jetzt
zusätzlich in der Git-Historie, und die Kurzfassungen werden nicht mehr
gebraucht.

Seit P-2026-08-09-02 gibt es auch **keinen** Master-Prompt mehr: Sein Inhalt
ist nach Lesehäufigkeit aufgeteilt in `docs/arbeitsregeln.md` (gilt immer) und
`docs/fachregeln/*.md` (nach Bedarf), mit `CHATSTART.md` als Einstieg.

---

## Master-Prompts (abgelöst)

### `master_prompt_zeiterfassung_v13.md`
- **Was:** Von 2026-08-08 bis 2026-08-09 der aktive Master-Prompt: Arbeitsweise,
  Architektur und komplette Fachlogik in einem Dokument (~69 KB).
- **Warum archiviert:** Als ein Dokument war er zu groß geworden. Wer einen
  Tippfehler im Terminal beheben wollte, las zwangsläufig auch Pausenregeln,
  PDF-Spalten und Genehmigerlogik – rund 36.000 Token allein für diese eine
  Datei. Aufgeteilt nach Lesehäufigkeit (P-2026-08-09-02).
- **Was davon noch gilt:** Fachlich **alles** – der Inhalt steckt vollständig
  in `docs/arbeitsregeln.md` und `docs/fachregeln/*.md`. Jede Fachregel-Datei
  nennt oben, aus welchen Abschnitten sie stammt.
- **Wofür man ihn noch braucht:** **Abschnitt 1a** – die Begründung, welche
  Regeln aus v12 entfielen und warum (ZIP-Zwang, 3-Dateien-Limit,
  SHA256-Nachweis). Diese Begründung wurde bewusst nicht mitgenommen: Sie
  erklärt eine Arbeitsweise, die es nicht mehr gibt.

### `master_prompt_zeiterfassung_v12.md`
- **Was:** Bis 2026-08-08 der aktive Master-Prompt.
- **Archiviert am:** 2026-08-08, abgelöst durch v13.
- **Warum:** Die Meta-Regeln waren auf den ZIP-basierten Chat-Workflow
  zugeschnitten (nur ZIP-Ausgabe, max. 3 Dateien pro Patch, SHA256-Nachweis).
  Diese Regeln stammen aus technischen Grenzen der damaligen Arbeitsweise –
  insbesondere aus dem Zeitlimit von etwa fünf Minuten pro Antwort – und nicht
  aus fachlichen Gründen.
- **Was noch gilt:** Der komplette fachliche Teil (Abschnitte 2–18 sowie die
  v4-/v7-Ergänzungen) wurde **wortgleich** nach v13 übernommen. Die
  Änderungen im Detail stehen in v13, Abschnitt 1a.

### `master_prompt_zeiterfassung_v11.md`
- **Was:** Vorgänger von v12.
- **Archiviert am:** früher (mit Erscheinen von v12).
- **Warum:** Durch v12 vollständig ersetzt.
- **Was noch gilt:** Nichts eigenständig – reine Referenz, um die Entwicklung
  der Regeln nachzuvollziehen.

## Dev-Prompts (Kurzfassungen, entfallen)

### `dev_prompt_zeiterfassung_v12.md`, `dev_prompt_zeiterfassung_v11.md`
- **Was:** Stark gekürzte Fassungen des jeweiligen Master-Prompts, gedacht
  zum schnellen Einfügen in einen neuen Chat.
- **Archiviert am:** früher; endgültig ohne Funktion seit v13 (2026-08-08).
- **Warum:** Sie existierten nur, weil Kontext knapp und teuer war. Beim
  Arbeiten im Repository liegt der vollständige Prompt ohnehin vor. Zwei
  parallele Regelfassungen sind außerdem eine Fehlerquelle: Sie liefen
  auseinander.
- **Was noch gilt:** Nichts. Wer eine Kurzfassung braucht, nimmt
  [`docs/STATUS_SNAPSHOT.md`](../STATUS_SNAPSHOT.md).
- **Achtung:** Der YAML-Kopf dieser Dateien (`output: zip_only`,
  `max_files_per_patch: 3`) ist **überholt** und darf nicht mehr als Regel
  gelesen werden.

## Themen-/Zusatz-Prompts (Spezifikationen)

### `auftrags_prompt_v1.md`
- **Was:** Spezifikation für Auftragszeiterfassung per Scan am Terminal
  inklusive Auswertung im Backend (Stand 2026-01-18).
- **Archiviert am:** früher, im Zuge der Doku-Aufräumung.
- **Warum:** Der große Teil ist umgesetzt (Auftragszeiten, Scan-Ablauf,
  Maschinen-QR-Codes). Als Arbeitsanweisung ist der Text damit erledigt.
- **Was noch gilt:** Als **Nachschlagewerk zur Fachlogik** weiterhin nützlich
  (Zielbild, Scan-Reihenfolge, Sonderfälle). Die verbindliche Kurzfassung
  steht in v13, Abschnitt 11 („Auftragszeiten“). Bei Widerspruch gilt v13.

### `report_mehrfachbloecke_prompt_v1.md`
- **Was:** Spezifikation zu Mehrfach-Arbeitsblöcken pro Tag, Mikro-Buchungen
  und PDF-Filtern (Stand 2026-01-20).
- **Archiviert am:** früher.
- **Warum:** Umgesetzt; die Regeln sind in Abschnitt 9.3 und 10 des
  Master-Prompts eingearbeitet.
- **Was noch gilt:** Die Herleitung, **warum** Mikro-Buchungen
  standardmäßig ausgeblendet werden – hilfreich, bevor jemand diese Logik
  „vereinfacht“.
- **Veraltet darin:** Der Hinweis „max. 3 Dateien pro Patch“ im Kopf der Datei.

### `zusatz2promt.md`
- **Was:** Scope-Beschreibung für das Stundenkonto (Gut-/Minusstunden) mit
  Audit und rückwirkender Korrektur (Stand 2026-01-17).
- **Archiviert am:** früher.
- **Warum:** Umgesetzt (`services/StundenkontoService.php`, Backend-Masken
  inkl. Sammelumbuchung).
- **Was noch gilt:** Die Anforderungen an Nachvollziehbarkeit (Begründung,
  Audit-Trail) – die sind bindend geblieben.
- **Hinweis:** Der darin erwähnte Vorgänger `docs/archiv/zusatzpromt.md`
  wurde schon vor dieser Aufräumung entfernt, weil er abgearbeitet war.

## Verlauf

### `DEV_PROMPT_HISTORY.md`
- **Was:** Der vollständige Projektverlauf, ein Eintrag je Patch. Der früher
  vorangestellte KI-Snapshot ist seit P-2026-08-09-10 entfallen – er führte
  dieselben Angaben wie `docs/STATUS_SNAPSHOT.md`.
- **Status:** **Aktiv, nicht archiviert im Sinne von „erledigt“.** Die Datei
  liegt nur physisch im Archivordner.
- **Regel:** Wird bei jedem Patch weiter gepflegt (v13, Abschnitt 20).
- **Hinweis zu alten Einträgen:** Sie nennen ZIP-Dateinamen, SHA256-Blöcke
  und Pfade wie `docs/DEV_PROMPT_HISTORY.md` oder
  `sql/zeiterfassung_aktuell.sql`. Das ist Historie und wird bewusst **nicht**
  nachträglich umgeschrieben.

---

## Wie mit diesen Dateien umzugehen ist

1. **Nicht als Auftrag lesen.** Wenn ein alter Prompt etwas fordert, das im
   aktuellen Master-Prompt nicht steht, gilt der aktuelle Master-Prompt.
2. **Als Begründung lesen.** Für die Frage „warum ist das so gebaut?“ sind
   die alten Spezifikationen oft die beste Quelle.
3. **Bei Widerspruch:** v13 gewinnt. Fällt dabei auf, dass v13 eine wichtige
   Regel gar nicht enthält, gehört sie dort ergänzt – und der Fund in die
   History.
4. **Neues Archivieren:** Datei nach `docs/archiv/` verschieben und hier einen
   Absatz mit *Was / Archiviert am / Warum / Was noch gilt* ergänzen.
