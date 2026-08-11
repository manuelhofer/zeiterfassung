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

**Der Gerätetest.** Voraussetzung erfüllt seit dem 09.08.2026: Kopplung und
Skripte sind fertig, alle sechs Stufen gebaut und im Container geprüft. Was ein
Container nicht zeigen kann, braucht jetzt einen Bildschirm.

Die sieben Prüfpunkte stehen als Protokoll in
[`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
Abschnitt 12 – dort und bewusst nicht hier ein zweites Mal. Dasselbe gilt für
den Stufenplan (Abschnitt 11).

## Offene Bugs
- **B-095 offen:** `abteilung_admin_speichern`, `maschine_admin_speichern`,
  `feiertag_admin_speichern` und `betriebsferien_admin_speichern` schreiben
  ohne CSRF-Prüfung; geprüft wird nur beim Umschalten (P-2026-08-11-09).
- **B-093 behoben** (P-2026-08-11-05 bis -07): Eine Rolle mit
  `scope_typ = 'abteilung'` wirkt jetzt – aber **nur** auf die
  Urlaubsgenehmigung, ausgewertet in `UrlaubGenehmigungService`, nicht in
  `hatRecht()`. Zielbild, Grenzen und die sieben Akzeptanzkriterien:
  [`spezifikation_abteilungsrechte.md`](spezifikation_abteilungsrechte.md).
  Zu bedenken **beim späteren Aufsetzen einer echten Installation**: Zeilen mit
  `scope_typ = 'abteilung'` wirken dort ab diesem Stand, sofern die Rolle
  `URLAUB_GENEHMIGEN` enthält. In der Entwicklungsdatenbank steht eine solche
  Zeile.
- **B-080 behoben in P-2026-08-10-28.** Bleibt als Beobachtungspunkt: Die
  Übertragskette rechnet jetzt über **laufendes Jahr und Vorjahr** und schreibt
  das Ergebnis in `urlaub_kontingent_jahr` fest. Im Praxis-Test darauf achten,
  ob die Salden zum Jahreswechsel plausibel bleiben – der erste echte Wechsel
  steht noch aus.

## Offene Tasks
- **T-104** Sieben Controller erzeugen HTML selbst, statt `views/` zu benutzen –
  größter Brocken `SmokeTestController`, dann `KonfigurationController` und
  `AuftragController`. Ein Controller je Patch; Muster und Prüfweg:
  P-2026-08-11-09.
- **T-105** `SmokeTestController::index()` ist eine Methode mit ~3.700 Zeilen.
  Diagnosewerkzeug, keine Fachlogik – aber praktisch nicht mehr änderbar.
- **T-108 erledigt** (P-2026-08-11-03 und -04): `DbInjektionsqueueModel` war
  tot und ist weg; die Regel „wo liegt die Queue" steht nur noch im
  `OfflineQueueManager` (`holeQueueVerbindungOderNull()`,
  `holeQueueSpeicherort()`). Wer neu auf `db_injektionsqueue` zugreift, nimmt
  diese beiden – nicht `Database` direkt.
- **T-109 erledigt** (P-2026-08-10-39 bis -42, Nachbesserung P-2026-08-11-01):
  Alle Backend-Masken gestalten
  sich aus `views/layout/header.php`, keine Farbe mehr per `style="…"`. Einzige
  Ausnahme bleibt `SmokeTestController` (siehe T-105). Beim Bauen neuer Masken
  gilt: erst dort nachsehen, was es schon gibt, und **keine eigenen Grössen auf
  Knöpfe schreiben** – `button` und `.button-link` sind nur deshalb gleich hoch,
  weil sie sich `box-sizing: border-box` teilen. Nachprüfen mit
  `grep -rc 'style="[^"]*\(background:#\|border:.*solid #\|color:#\)' --include='*.php' controller views`
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Terminal-Buchungsflows sind unter PHP 8.5 noch
  nicht **im Browser** mit angemeldetem Mitarbeiter durchgeklickt. Die
  Buchungslogik selbst ist seit P-2026-08-10-22 geprüft, online wie offline.
- Auftragsmodul: Scan-Flow/UX nur bei Bedarf verfeinern.
- Terminal (Auftrag): Stop-Detailmaske (Fallback) UX vereinfachen.
