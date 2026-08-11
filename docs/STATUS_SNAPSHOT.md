# Status-Snapshot

**Die einzige Stelle für den aktuellen Stand:** Projektstatus, nächster Schritt,
offene Bugs und Tasks. Wer wissen will, was ansteht, liest diese Datei und sonst
nichts.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdrücklicher Beauftragung**.

## Nächster Schritt (konkret)

**Der Gerätetest.** Voraussetzung erfüllt seit dem 09.08.2026: Kopplung und
Skripte sind fertig, alle sechs Stufen gebaut und im Container geprüft. Was ein
Container nicht zeigen kann, braucht jetzt einen Bildschirm.

Die sieben Prüfpunkte stehen als Protokoll in
[`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
Abschnitt 12 – dort und bewusst nicht hier ein zweites Mal. Dasselbe gilt für
den Stufenplan (Abschnitt 11).

## Offene Bugs
- **B-093: Abteilungsbezogene Rollenzuweisungen wirken nicht.**
  `AuthService::ladeRechteCodesAusDb()` liest aus
  `mitarbeiter_hat_rolle_scope` nur `scope_typ = 'global'`. Seit
  P-2026-08-10-25 ist die Auswahl in der Mitarbeiterverwaltung **gesperrt** und
  als „noch nicht wirksam" beschriftet – der gefährliche Zustand (Rechte
  vergeben, die nicht greifen) ist damit weg. Der Fehler selbst bleibt offen:
  Die Scope-Auswertung ist nicht gebaut. **Entschieden am 11.08.2026:** Gebaut
  wird nur die Urlaubsgenehmigung je Abteilung, nicht das allgemeine
  Bereichsmodell – Zielbild und Akzeptanzkriterien in
  [`spezifikation_abteilungsrechte.md`](spezifikation_abteilungsrechte.md).
  **In der lokalen Datenbank existiert eine solche wirkungslose Zuweisung** –
  im Produktivbestand nachsehen.
- **B-080 behoben in P-2026-08-10-28.** Bleibt als Beobachtungspunkt: Die
  Übertragskette rechnet jetzt über **laufendes Jahr und Vorjahr** und schreibt
  das Ergebnis in `urlaub_kontingent_jahr` fest. Im Praxis-Test darauf achten,
  ob die Salden zum Jahreswechsel plausibel bleiben – der erste echte Wechsel
  steht noch aus.

## Offene Tasks
- **T-104** Neun Controller erzeugen HTML selbst, statt `views/` zu benutzen –
  größter Brocken `SmokeTestController` (283 Zeilen), dann
  `KonfigurationController` (201), `AuftragController` (137).
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
