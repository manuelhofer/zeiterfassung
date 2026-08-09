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

**Reihenfolge, festgelegt am 09.08.2026:** Erst Kopplung und Skripte
fertigstellen, **dann** der Test auf einem echten Bildschirm (VM oder
Hardware). Vorher steht dort ein Geraet, an dem noch gebaut wird – der Test
waere zweimal zu machen.

Offen bis dahin, in dieser Reihenfolge:

1. **T-101** (`passwort_hash` vor dem Terminal verbergen) – der letzte offene
   Punkt mit Sicherheitsbezug an der Kopplung.
2. **T-103** entscheiden (Backend-Anmeldung auf dem Terminal erreichbar) –
   braucht eine Ansage, keine Bauarbeit.
3. **Stufe 6, Selbsttest** – laesst sich ohne Hardware bauen und macht den
   spaeteren Geraetetest ueberhaupt erst pruefbar. Vorziehen vor Stufe 5.
4. **Stufe 5, Peripherie** – RFID (USB-Keyboard-Wedge und RC522 ueber SPI),
   Touchscreen und Drehung. Der blind schreibbare Teil (udev-Regeln,
   Drehungs-Konfiguration) geht vorher, der Scan-Test braucht Hardware.

**Danach erst** der Geraetetest. Fuenf Punkte, die im Container prinzipiell
nicht zu belegen sind: Vollbild ohne Adresszeile, Neustart nach `pkill
chromium` (`Restart=always`), Bildschirm nach zehn Minuten noch hell,
Mauszeiger weg, X11-Rueckfall ueber `KIOSK_ANZEIGE="x11"`. `qemu`/`virsh` sind
auf dem Entwicklungsrechner vorhanden.

Weiterhin offen aus Stufe 3 und 4: **auf `pacman`, `dnf` und `zypper` ist
keines der beiden Skripte gelaufen.** Der Container deckt nur `apt` ab, und
openSUSE bleibt die unsicherste Familie (versionsgebundene Paketnamen, php-fpm
auf TCP statt Socket, `cage` je nach Version gar nicht vorhanden).

Grundlage: `docs/spezifikation_terminal_installation.md` (Abschnitte 6, 7, 8).

**Welche Stufen fertig sind und wie weit sie geprueft sind, steht im Stufenplan
der Spezifikation** (Abschnitt 11) – hier bewusst nicht ein zweites Mal.

## Offene Bugs
- **B-092:** Die Route `?seite=betriebsferien_admin_toggle` ruft
  `BetriebsferienAdminController::toggleAktiv()` auf – **diese Methode existiert
  nicht**. Ein Aufruf endet in einem Fatal. Kein Menuepunkt verlinkt darauf, der
  Fehler ist also latent. Vorher klaeren, was gewollt ist: Methode nachruesten
  oder tote Route entfernen.
- **B-080:** Urlaubssaldo wirkt teils verwirrend (Nutzerrueckmeldung
  „Urlaubsberechnung stimmt nicht") – BF/Feiertage/Arbeitszeit-Abgrenzung
  nochmals pruefen. Teilfix in P-2026-01-18-07.

## Offene Tasks
- **T-101 `passwort_hash` vor dem Terminal verbergen.** Der Terminal-Benutzer
  darf `mitarbeiter` komplett lesen, also auch die Passwort-Hashes.
  Spaltenrechte scheitern an `SELECT *` im `MitarbeiterModel`. Loesung: Sicht
  ohne diese Spalte oder feste Spaltenlisten.
- **T-102 Buchungen tragen keine `terminal_id`.** Seit P-2026-08-09-01 steht die
  ID in `config.local.php` (`terminal.id`) und liesse sich durchreichen.
- **T-103 Auf dem Terminal ist die Backend-Anmeldung erreichbar.** Der
  Document-Root zeigt auf `public/`, also liefert `http://<terminal>/` das
  Anmeldeformular des Backends – im Container geprueft. Erst klaeren, ob das
  gewollt ist (Fernwartung) oder ob ein Terminal nur `terminal.php` ausliefern
  soll.
- **T-104 Distributionserkennung steht zweimal.** `install_terminal.sh` und
  `install_kiosk.sh` bringen denselben Block ueber `/etc/os-release` mit. Wer
  eine Paketfamilie ergaenzt, muss an zwei Stellen denken. Zusammenlegen erst,
  wenn eine dritte Stelle dazukommt – dafuer muesste das im Container
  geprueft Skript der Stufe 3 angefasst und erneut geprueft werden.
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
