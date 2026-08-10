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
- **B-080:** Urlaubssaldo wirkt teils verwirrend (Nutzerrückmeldung
  „Urlaubsberechnung stimmt nicht") – BF/Feiertage/Arbeitszeit-Abgrenzung
  nochmals prüfen. Teilfix in P-2026-01-18-07. Seit P-2026-08-10-02 meldet sich
  der Rückfall „Betriebsferien-Tage nicht zählbar" in `system_log` (Kategorie
  `urlaubservice`) – vorher schwieg er. **Nächster Schritt: dort nachsehen,
  bevor weiter gesucht wird.**

## Offene Tasks
- **T-102 Buchungen tragen keine `terminal_id`.** Seit P-2026-08-09-01 steht die
  ID in `config.local.php` (`terminal.id`) und ließe sich durchreichen.
- **T-104 Neun Controller erzeugen HTML selbst**, statt `views/` zu benutzen –
  größter Brocken `SmokeTestController` (283 HTML-Zeilen), dazu
  `KonfigurationController` (201) und `AuftragController` (137). Auflösen, wenn
  ohnehin an der jeweiligen Maske gearbeitet wird.
- **T-105 `SmokeTestController::index()` ist eine Methode mit ~3.700 Zeilen.**
  Berührt keine Fachlogik (Diagnosewerkzeug), aber praktisch nicht mehr
  änderbar. Zerlegen, wenn der Selbsttest ohnehin angefasst wird.
- **T-106 Ein Prüfschritt des Selbsttests durchsucht Quelltext nach einer
  Zeichenkette** (`SmokeTestController`, Suche nach
  `function beendeLetztePassendeLaufendeAuftragszeit…`). Beim Umbenennen der
  Methode schlägt der Test fehl, obwohl alles funktioniert.
- **T-107 Die Liste `$geschuetzteSeiten` in `public/index.php` wiederholt jeden
  `case`-Zweig des Routers von Hand.** Heute stimmen beide überein; beim
  nächsten Route-Zusatz wird eine davon vergessen.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Strichcode-Erzeugung und Terminal-Buchungsflows
  sind unter PHP 8.5 noch nicht im Browser geprüft (brauchen einen
  angemeldeten Durchlauf).
- Auftragsmodul: Scan-Flow/UX nur bei Bedarf weiter verfeinern.
- Terminal (Auftrag): Stop-Detailmaske (Fallback) UX vereinfachen.
