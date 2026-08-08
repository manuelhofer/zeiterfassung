# Alte Prompts – was archiviert wurde und warum

Diese Datei ist die Begruendungsliste zum Archivordner. Fuer **jede** Datei in
`docs/archiv/` steht hier:

- was sie war,
- wann und warum sie archiviert wurde,
- was davon **heute noch gilt**.

Grundregel im Projekt: **Es wird nichts geloescht.** Alte Prompts bleiben
lesbar, damit nachvollziehbar ist, warum das System so gebaut ist, wie es
gebaut ist. Sie sind aber **keine gueltige Arbeitsanweisung** mehr – gueltig
ist allein der aktive Master-Prompt
[`docs/master_prompt_zeiterfassung_v13.md`](../master_prompt_zeiterfassung_v13.md).

---

## Warum ueberhaupt so viele alte Prompts?

Das Projekt wurde ueber lange Zeit mit ChatGPT entwickelt, **ohne Zugriff auf
das Repository**. Der komplette Projektstand musste deshalb in Prompt-Dateien
mitgefuehrt und bei jedem Schritt neu hochgeladen werden. Daraus sind
mehrere parallele Textsorten entstanden:

- **Master-Prompt** – das vollstaendige Regelwerk (Fachlogik + Meta-Regeln),
- **Dev-Prompt** – eine Kurzfassung davon fuer den taeglichen Gebrauch,
- **Zusatz-/Themen-Prompts** – Spezifikationen fuer einzelne neue Module,
- **History** – der fortlaufende Verlauf als Gedaechtnisersatz.

Seit v13 wird direkt im Git-Workspace gearbeitet. Der Verlauf steckt jetzt
zusaetzlich in der Git-Historie, und die Kurzfassungen werden nicht mehr
gebraucht: Es gibt genau **einen** aktiven Master-Prompt plus den kurzen
`docs/STATUS_SNAPSHOT.md`.

---

## Master-Prompts (abgeloest)

### `master_prompt_zeiterfassung_v12.md`
- **Was:** Bis 2026-08-08 der aktive Master-Prompt.
- **Archiviert am:** 2026-08-08, abgeloest durch v13.
- **Warum:** Die Meta-Regeln waren auf den ZIP-basierten Chat-Workflow
  zugeschnitten (nur ZIP-Ausgabe, max. 3 Dateien pro Patch, SHA256-Nachweis).
  Diese Regeln stammen aus technischen Grenzen der damaligen Arbeitsweise –
  insbesondere aus dem Zeitlimit von etwa fuenf Minuten pro Antwort – und nicht
  aus fachlichen Gruenden.
- **Was noch gilt:** Der komplette fachliche Teil (Abschnitte 2–18 sowie die
  v4-/v7-Ergaenzungen) wurde **wortgleich** nach v13 uebernommen. Die
  Aenderungen im Detail stehen in v13, Abschnitt 1a.

### `master_prompt_zeiterfassung_v11.md`
- **Was:** Vorgaenger von v12.
- **Archiviert am:** frueher (mit Erscheinen von v12).
- **Warum:** Durch v12 vollstaendig ersetzt.
- **Was noch gilt:** Nichts eigenstaendig – reine Referenz, um die Entwicklung
  der Regeln nachzuvollziehen.

## Dev-Prompts (Kurzfassungen, entfallen)

### `dev_prompt_zeiterfassung_v12.md`, `dev_prompt_zeiterfassung_v11.md`
- **Was:** Stark gekuerzte Fassungen des jeweiligen Master-Prompts, gedacht
  zum schnellen Einfuegen in einen neuen Chat.
- **Archiviert am:** frueher; endgueltig ohne Funktion seit v13 (2026-08-08).
- **Warum:** Sie existierten nur, weil Kontext knapp und teuer war. Beim
  Arbeiten im Repository liegt der vollstaendige Prompt ohnehin vor. Zwei
  parallele Regelfassungen sind ausserdem eine Fehlerquelle: Sie liefen
  auseinander.
- **Was noch gilt:** Nichts. Wer eine Kurzfassung braucht, nimmt
  [`docs/STATUS_SNAPSHOT.md`](../STATUS_SNAPSHOT.md).
- **Achtung:** Der YAML-Kopf dieser Dateien (`output: zip_only`,
  `max_files_per_patch: 3`) ist **ueberholt** und darf nicht mehr als Regel
  gelesen werden.

## Themen-/Zusatz-Prompts (Spezifikationen)

### `auftrags_prompt_v1.md`
- **Was:** Spezifikation fuer Auftragszeiterfassung per Scan am Terminal
  inklusive Auswertung im Backend (Stand 2026-01-18).
- **Archiviert am:** frueher, im Zuge der Doku-Aufraeumung.
- **Warum:** Der grosse Teil ist umgesetzt (Auftragszeiten, Scan-Ablauf,
  Maschinen-QR-Codes). Als Arbeitsanweisung ist der Text damit erledigt.
- **Was noch gilt:** Als **Nachschlagewerk zur Fachlogik** weiterhin nuetzlich
  (Zielbild, Scan-Reihenfolge, Sonderfaelle). Die verbindliche Kurzfassung
  steht in v13, Abschnitt 11 („Auftragszeiten“). Bei Widerspruch gilt v13.

### `report_mehrfachbloecke_prompt_v1.md`
- **Was:** Spezifikation zu Mehrfach-Arbeitsbloecken pro Tag, Mikro-Buchungen
  und PDF-Filtern (Stand 2026-01-20).
- **Archiviert am:** frueher.
- **Warum:** Umgesetzt; die Regeln sind in Abschnitt 9.3 und 10 des
  Master-Prompts eingearbeitet.
- **Was noch gilt:** Die Herleitung, **warum** Mikro-Buchungen
  standardmaessig ausgeblendet werden – hilfreich, bevor jemand diese Logik
  „vereinfacht“.
- **Veraltet darin:** Der Hinweis „max. 3 Dateien pro Patch“ im Kopf der Datei.

### `zusatz2promt.md`
- **Was:** Scope-Beschreibung fuer das Stundenkonto (Gut-/Minusstunden) mit
  Audit und rueckwirkender Korrektur (Stand 2026-01-17).
- **Archiviert am:** frueher.
- **Warum:** Umgesetzt (`services/StundenkontoService.php`, Backend-Masken
  inkl. Sammelumbuchung).
- **Was noch gilt:** Die Anforderungen an Nachvollziehbarkeit (Begruendung,
  Audit-Trail) – die sind bindend geblieben.
- **Hinweis:** Der darin erwaehnte Vorgaenger `docs/archiv/zusatzpromt.md`
  wurde schon vor dieser Aufraeumung entfernt, weil er abgearbeitet war.

## Verlauf

### `DEV_PROMPT_HISTORY.md`
- **Was:** Der vollstaendige Projektverlauf mit KI-Snapshot am Anfang.
- **Status:** **Aktiv, nicht archiviert im Sinne von „erledigt“.** Die Datei
  liegt nur physisch im Archivordner.
- **Regel:** Wird bei jedem Patch weiter gepflegt (v13, Abschnitt 20).
- **Hinweis zu alten Eintraegen:** Sie nennen ZIP-Dateinamen, SHA256-Bloecke
  und Pfade wie `docs/DEV_PROMPT_HISTORY.md` oder
  `sql/zeiterfassung_aktuell.sql`. Das ist Historie und wird bewusst **nicht**
  nachtraeglich umgeschrieben.

---

## Wie mit diesen Dateien umzugehen ist

1. **Nicht als Auftrag lesen.** Wenn ein alter Prompt etwas fordert, das im
   aktuellen Master-Prompt nicht steht, gilt der aktuelle Master-Prompt.
2. **Als Begruendung lesen.** Fuer die Frage „warum ist das so gebaut?“ sind
   die alten Spezifikationen oft die beste Quelle.
3. **Bei Widerspruch:** v13 gewinnt. Faellt dabei auf, dass v13 eine wichtige
   Regel gar nicht enthaelt, gehoert sie dort ergaenzt – und der Fund in die
   History.
4. **Neues Archivieren:** Datei nach `docs/archiv/` verschieben und hier einen
   Absatz mit *Was / Archiviert am / Warum / Was noch gilt* ergaenzen.
