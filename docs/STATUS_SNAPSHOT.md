# Status-Snapshot

**Die einzige Stelle fuer den aktuellen Stand:** Projektstatus, naechster
Schritt, offene Bugs und Tasks. Wer weiss, was gerade ansteht, hat diese Datei
gelesen und sonst nichts.

Alles, was der Code oder Git schon weiss, steht bewusst **nicht** hier. Der
volle Verlauf je Patch liegt in `docs/archiv/DEV_PROMPT_HISTORY.md` – die wird
nur gelesen, wenn ein bestimmter Patch nachzuschlagen ist.

**Was das Projekt ist, wo die Einstiegspunkte liegen und wie gearbeitet wird,
steht in [`CHATSTART.md`](../CHATSTART.md) und
[`arbeitsregeln.md`](arbeitsregeln.md)** – hier steht nur, was sich aendert.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Naechster Schritt (konkret)

**Der Geraetetest.** Die Voraussetzung dafuer ist seit dem 09.08.2026 erfuellt:
Kopplung und Skripte sind fertig, alle sechs Stufen gebaut und im Container
geprueft. Was ein Container prinzipiell nicht zeigen kann, ist jetzt der ganze
Rest – und der braucht einen Bildschirm.

Ablauf: Debian in einer VM mit Grafik (`qemu`/`virsh` sind auf dem
Entwicklungsrechner vorhanden) oder echte Hardware, dann die vier Skripte der
Reihe nach, danach diese Punkte:

1. Kommt der Browser nach dem Einschalten im Vollbild hoch, ohne Adresszeile?
2. Startet er nach `pkill chromium` von selbst neu (`Restart=always`)?
3. Bleibt der Bildschirm nach zehn Minuten hell?
4. Ist der Mauszeiger weg, wenn eine Maus angeschlossen ist?
5. Greift der X11-Rueckfall, wenn man `KIOSK_ANZEIGE="x11"` setzt?
6. Dreht sich mit `BILDSCHIRM_DREHUNG="rechts"` unter X11 **beides** – Bild und
   Beruehrung? (Nur Bild gedreht ist schlimmer als gar nicht: Es sieht richtig
   aus, aber der Finger trifft daneben.)
7. Liefert ein angeschlossener RFID-Leser Zeichen, und stimmt ein gescannter
   Barcode mit dem Etikett ueberein? Beides fragt `selbsttest.sh` ab.

Weiterhin offen aus allen vier Skripten: **auf `pacman`, `dnf` und `zypper` ist
keines gelaufen.** Der Container deckt nur `apt` ab, und openSUSE bleibt die
unsicherste Familie (versionsgebundene Paketnamen, php-fpm auf TCP statt
Socket, `cage` je nach Version gar nicht vorhanden).


Grundlage: `docs/spezifikation_terminal_installation.md` (Abschnitte 6, 7, 8).

**Welche Stufen fertig sind und wie weit sie geprueft sind, steht im Stufenplan
der Spezifikation** (Abschnitt 11) – hier bewusst nicht ein zweites Mal.

## Offene Bugs
- **B-080:** Urlaubssaldo wirkt teils verwirrend (Nutzerrueckmeldung
  „Urlaubsberechnung stimmt nicht") – BF/Feiertage/Arbeitszeit-Abgrenzung
  nochmals pruefen. Teilfix in P-2026-01-18-07. Seit P-2026-08-10-02 meldet
  sich der Rueckfall „Betriebsferien-Tage nicht zaehlbar" in `system_log`
  (Kategorie `urlaubservice`) – vorher schwieg er. **Naechster Schritt: dort
  nachsehen, bevor weiter gesucht wird.**

## Offene Tasks
- **T-102 Buchungen tragen keine `terminal_id`.** Seit P-2026-08-09-01 steht die
  ID in `config.local.php` (`terminal.id`) und liesse sich durchreichen.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Offen aus P-2026-08-08-02: Strichcode-Erzeugung und Terminal-Buchungsflows
  sind unter PHP 8.5 noch nicht im Browser geprueft (brauchen einen
  angemeldeten Durchlauf).
- Auftragsmodul: Scan-Flow/UX nur bei Bedarf weiter verfeinern.
- Terminal (Auftrag): Stop-Detailmaske (Fallback) UX vereinfachen.

## Was zuletzt passiert ist
`git log --oneline` – genauer und immer aktuell als jede von Hand gepflegte
Zusammenfassung. Ausfuehrlich je Patch (mit Tests und Begruendungen) in
`docs/archiv/DEV_PROMPT_HISTORY.md`.
