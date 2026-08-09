# Status-Snapshot

**Die einzige Stelle fuer den aktuellen Stand:** Projektstatus, naechster
Schritt, offene Bugs und Tasks. Wer weiss, was gerade ansteht, hat diese Datei
gelesen und sonst nichts.

Alles, was der Code oder Git schon weiss, steht bewusst **nicht** hier. Der
volle Verlauf je Patch liegt in `docs/archiv/DEV_PROMPT_HISTORY.md` – die wird
nur gelesen, wenn ein bestimmter Patch nachzuschlagen ist.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Projektziel (kurz)
Webbasierte Zeiterfassung inkl. Mitarbeiter-/Rollen-/Genehmiger-Verwaltung,
Urlaubsverwaltung, Auswertungen sowie Terminal-UI (Kiosk) inkl. Offline-Queue.

## Entry-Points
- Backend: `public/index.php` (Routing ueber `?seite=...`)
- Terminal: `public/terminal.php` (Routing ueber `?aktion=...`)
- Source of Truth der DB-Struktur: `sql/01_initial_schema.sql`

## Arbeitsweise
- Einstieg fuer KI-Assistenten: `CHATSTART.md` (mit Lesekarte).
- Verbindliche Regeln: `docs/arbeitsregeln.md`. Fachlogik nach Thema:
  `docs/fachregeln/`.
- Gearbeitet wird direkt im Git-Workspace; ein Patch ist ein Commit mit
  Patch-ID im Betreff. Keine ZIP-Pakete, kein Dateilimit.
- Lokale Umgebung zum Testen: `docs/lokale_entwicklungsumgebung.md`
  (App unter `http://localhost/zeiterfassung`).

## Naechster Schritt (konkret)

**Der Kiosk auf einem echten Bildschirm.** Stufe 4 ist gebaut
(P-2026-08-09-09) und lief im Container zehn von zehn Punkten durch – aber ein
Bild hat dabei niemand gesehen. `cage` kommt bis zum Zugriff auf das
Grafikgeraet und bricht dort ab, Xorg startet und findet keinen Treiber; beides
ist im Container der erwartete Abbruch und kein Beleg. Zu pruefen ist in einer
VM mit Grafik (`qemu`/`virsh` sind auf dem Entwicklungsrechner vorhanden) oder
auf echter Hardware:

1. Kommt der Browser nach dem Einschalten im Vollbild hoch, ohne Adresszeile?
2. Startet er nach `pkill chromium` von selbst neu (`Restart=always`)?
3. Bleibt der Bildschirm nach zehn Minuten hell?
4. Ist der Mauszeiger weg, wenn eine Maus angeschlossen ist?
5. Greift der X11-Rueckfall, wenn man `KIOSK_ANZEIGE="x11"` setzt?

Erst danach **Stufe 5 (Peripherie)**: RFID-Leser (USB-Keyboard-Wedge und RC522
ueber SPI), Touchscreen und Drehung, Scan-Test. Braucht echte Hardware.
Danach Stufe 6 (Selbsttest mit Scan-Proben).

Weiterhin offen aus Stufe 3 und 4: **auf `pacman`, `dnf` und `zypper` ist
keines der beiden Skripte gelaufen.** Der Container deckt nur `apt` ab, und
openSUSE bleibt die unsicherste Familie (versionsgebundene Paketnamen, php-fpm
auf TCP statt Socket, `cage` je nach Version gar nicht vorhanden).

Grundlage: `docs/spezifikation_terminal_installation.md` (Abschnitte 6 und 7).

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
Steht in `git log --oneline` – genauer und immer aktuell als eine gepflegte
Liste. Ausfuehrlich je Patch (mit Tests und Begruendungen) in
`docs/archiv/DEV_PROMPT_HISTORY.md`.

Grober Verlauf: Januar 2026 Reports, Urlaub und Stundenkonto stabilisiert;
August 2026 lokale Entwicklungsumgebung, Auftraege mit Strichcodes und
Laufkarte, danach die Terminal-Installation (Kopplung und Einrichtungsseite).
