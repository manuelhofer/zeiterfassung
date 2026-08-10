# Spezifikation: Terminal-Installation per Skript

*Version:* v3 (2026-08-09)
*Status:* alle sechs Stufen gebaut; offen ist der Test auf einem Gerät mit
Bildschirm und Peripherie. Stand und Einschraenkungen je Stufe stehen im
**Stufenplan (Abschnitt 11)**.
*Grundlage:* `docs/fachregeln/terminal_und_offline.md`;
`docs/rfid_reader_setup.md`; `docs/terminal/rfid-ws_rollout.md`

---

## 1. Zielbild

Ein frisch installiertes Linux-Gerät wird mit **vier Befehlen** zum
einsatzfertigen Hallenterminal:

```bash
sudo ./scripts/terminal/install_terminal.sh    # Grundsystem (Abschnitt 5a)
sudo ./scripts/terminal/install_kiosk.sh       # Kiosk       (Abschnitt 7)
sudo ./scripts/terminal/install_peripherie.sh  # Peripherie  (Abschnitt 6)
sudo ./scripts/terminal/selbsttest.sh          # Selbsttest  (Abschnitt 8)
```

Getrennt statt in einem Skript, weil die vier Teile Unterschiedliches
voraussetzen: Das Grundsystem lässt sich im Container prüfen, der Kiosk
braucht einen Bildschirm, die Peripherie braucht angeschlossene Geräte, und
der Selbsttest will einen Menschen, der einmal scannt. Wer nur den Kiosk neu
aufsetzt, faehrt nicht die ganze Installation noch einmal.

Alle vier lesen dieselbe Antwortdatei (`terminal.conf`) und sind idempotent:
Ein zweiter Lauf schadet nicht und repariert einen halbfertigen Stand.

Danach startet das Gerät von selbst in die Terminal-Oberflaeche, der
RFID-Leser funktioniert, der Barcode-Scanner liefert saubere Codes, und der
Touchscreen ist bedienbar. Kein manuelles Nacharbeiten.

## 2. Aufteilung: Skript und Kopplung

Ein frisch aufgesetztes Gerät hat weder Webserver noch PHP – die
Grundinstallation kann deshalb nur ein Skript erledigen. Sobald die Anwendung
aber läuft, kann sie ihre Konfiguration **selbst** holen. Genau dort wird
geteilt:

| Wer | Wofür |
| --- | --- |
| **Skript** (einmal je Gerät) | Betriebssystem, Pakete, Code, Webserver, Kiosk, RFID, Touchscreen, Tastaturlayout |
| **Kopplung im Browser** | Server-Adresse, Anmeldung am Backend, Zugangsdaten, `config.local.php` schreiben |

Der entscheidende Gewinn: **Das Skript kennt keine Zugangsdaten.** Es fragt
nichts Fachliches ab, und dasselbe Abbild lässt sich auf zwanzig Geräte
spielen.

Das Terminal verhält sich dabei genauso wie das Backend beim ersten Start:
Fehlt die Konfiguration, erscheint statt der Terminal-Oberflaeche eine
**Einrichtungsseite** – dieselbe Mechanik wie die vorhandene Maske
„Erstinstallation“ (`views/login/initial_admin.php`).

## 2b. Die Einrichtungsseite (umgesetzt, P-2026-08-09-01)

`public/terminal.php` prüft vor allem Anderen, ob `config/config.local.php`
existiert. Fehlt sie, übernimmt `TerminalEinrichtungController` und zeigt
`views/terminal/einrichtung.php` – unabhängig davon, welche `?aktion=…`
aufgerufen wurde. Einzige Ausnahme ist `?aktion=health`: Eine Überwachung soll
auch ein frisches Gerät abfragen können.

**Woran „nicht eingerichtet“ erkannt wird – und woran ausdrücklich nicht:**
Nur an der **fehlenden Datei**, nicht an einer fehlgeschlagenen
Datenbankverbindung. Ein Terminal ohne Netz ist kein unkonfiguriertes Terminal:
Der Offline-Betrieb mit Queue ist eine gewollte Betriebsart
(`docs/fachregeln/terminal_und_offline.md`, Abschnitt 5).
Würde ein Netzausfall die Einrichtungsseite hervorholen, stuende die Halle bei
jeder Störung vor einer Maske, die nach einem Kopplungscode fragt – und die
Buchungen wären weg.

**Bedienung:** Zwei Felder (Server-Adresse, Kopplungscode) mit eigener
Bildschirmtastatur, weil ein Kiosk keine Tastatur hat. Für den Code enthält
die Tastatur **nur** die Zeichen, die im Code vorkommen können (kein O/0, kein
I/1/L); Kleinschreibung und Bindestriche sind erlaubt und werden serverseitig
normalisiert.

**Adresse:** Es genuegt der Rechnername (`192.168.10.5`,
`server/zeiterfassung`). Fehlt das Schema, wird `http://` ergänzt; probiert
werden `…/index.php` und `…/public/index.php`, weil der Webserver je nach
Installation auf `public/` oder auf das Projektverzeichnis zeigt. Ein
Fehlversuch auf dem falschen Pfad verbraucht den Kopplungscode nicht – der
Endpunkt läuft dort gar nicht. **Weiterleitungen werden nicht verfolgt**,
sondern angezeigt: Eine Umleitung kann auf einen anderen Rechner zeigen, und
dorthin gehen Zugangsdaten.

**Geschrieben wird** `config/config.local.php` mit `installation_typ =
'terminal'`, den Zugangsdaten aus der Antwort und den Terminal-Einstellungen.
Erst vollständig daneben schreiben, gegenlesen, dann umbenennen: Eine halb
geschriebene Konfiguration wäre schlimmer als gar keine – sie könnte das
Gerät dauerhaft lahmlegen, weil dann auch die Einrichtungsseite nicht mehr
erscheint.

**Wenn das Schreiben scheitert** (Verzeichnis nicht beschreibbar), zeigt die
Seite den vollständigen Dateiinhalt zum Übernehmen an. Grund: Der
Kopplungscode ist zu diesem Zeitpunkt verbraucht; ein blosses „Fehler“ würde
den Monteur zwingen, im Backend einen neuen Code zu holen. Fehlende
Schreibrechte werden ausserdem schon **vor** dem Koppeln als Hinweis angezeigt.

**Eine vorhandene Konfiguration wird nie überschrieben.** Sonst liesse sich ein
laufendes Terminal über diese Seite auf einen fremden Server umbiegen. Ein
Gerät neu koppeln heißt deshalb: `config.local.php` löschen.

**Die Warnung aus der Antwort** (Kopplung lief über HTTP) wird nach dem
Speichern gross angezeigt. Sonst merkt niemand, dass die Zugangsdaten im Netz
mitlesbar waren.

### Was das Skript hinterlässt: `config/geraet.local.php`

Zwei Dinge kann die Kopplung nicht liefern, weil sie der **Maschine** gehören
und nicht dem Backend: die Zugangsdaten der lokalen Ausweichdatenbank und die
Einstellung der RFID-Bridge. Dafür gibt es eine optionale Datei, die das
Installationsskript (Stufe 3/5) anlegt und die Einrichtungsseite beim Koppeln
einliest:

```php
<?php
return [
    'offline_db' => [
        'enabled' => true,
        'host'    => 'localhost',
        'dbname'  => 'zeiterfassung_offline',
        'charset' => 'utf8mb4',
        'user'    => '…',
        'pass'    => '…',
    ],
    'terminal' => [
        'rfid_ws' => ['enabled' => false, 'url' => 'ws://127.0.0.1:8765'],
    ],
];
```

Übernommen werden **nur** diese beiden Blöcke. Die Zugangsdaten zur
Hauptdatenbank kommen ausschließlich aus der Kopplung – sonst wäre die
Trennung zwischen Skript und Kopplung wieder aufgeweicht.

Fehlt die Datei, koppelt das Terminal trotzdem; `offline_db.enabled` steht dann
auf `false` und die Seite sagt ausdrücklich, dass dieses Gerät bei einem
Netzausfall **nichts zwischenspeichern** kann. Das ist die ehrlichere Variante,
als eine Ausweichdatenbank zu behaupten, die es nicht gibt.

## 2a. Kopplung (Handshake)

### Ablauf

1. **Backend**, Menue Verwaltung → *Terminal anmelden*: Name und Standort
   eingeben. Das Backend legt den Eintrag in `terminal` an und zeigt einen
   **Kopplungscode** – kurz genug zum Abtippen, zeitlich begrenzt (Vorschlag:
   30 Minuten) und **einmalig** gültig.
2. **Terminal** startet, findet keine Konfiguration → Einrichtungsseite auf dem
   Touchscreen: Server-Adresse und Kopplungscode.
3. Das Terminal ruft den Kopplungs-Endpunkt des Backends auf und schickt Code
   plus eigene Kennung (Hostname, MAC-Adresse).
4. **Das Backend legt einen eigenen Datenbankbenutzer für genau dieses
   Terminal an** (Name z. B. `term_halle_nord_1`, zufälliges Passwort) und
   antwortet mit Zugangsdaten, Terminal-ID und Einstellungen.
5. Das Terminal schreibt `config.local.php`, legt seine lokale
   Ausweichdatenbank an und startet in den Kioskmodus.

### Der Endpunkt (umgesetzt, P-2026-08-08-36)

`POST …/public/index.php?seite=terminal_kopplung`, Antwort als JSON, ohne
Anmeldung erreichbar.

| Feld | Pflicht | Inhalt |
| --- | --- | --- |
| `code` | ja | Kopplungscode aus dem Backend (Bindestriche und Kleinschreibung sind egal) |
| `host` | nein | Kennung des Geräts, z. B. Hostname und MAC-Adresse – nur zur Nachvollziehbarkeit |

Antwort bei Erfolg:

```json
{
  "ok": true,
  "terminal": {
    "id": 7, "name": "Halle 9 links", "standort_beschreibung": "…",
    "abteilung_id": null, "modus": "terminal",
    "auto_logout_timeout_sekunden": 45,
    "offline_erlaubt_kommen_gehen": true,
    "offline_erlaubt_auftraege": false
  },
  "db": { "host": "…", "dbname": "zeiterfassung", "user": "term_halle_9_links_7",
          "pass": "…", "charset": "utf8mb4" },
  "warnung": "… nur, wenn die Kopplung unverschluesselt lief"
}
```

Bei Misserfolg `{"ok": false, "fehler": "…"}` mit passendem HTTP-Status
(400 ohne Code, 403 ungültiger Code oder stillgelegtes Terminal, 429 zu viele
Fehlversuche, 500 Serverproblem).

Festgelegtes Verhalten:

- **Ein Fehlschlag sagt nicht, warum.** Ob der Code unbekannt, abgelaufen oder
  bereits verbraucht war, steht nur im Serverprotokoll – alles andere hilft
  beim Durchprobieren.
- **Fehlversuche werden gebremst** (je Absender-IP, Standard 10 Versuche in 10
  Minuten). Während der Sperre wird auch ein gültiger Code abgewiesen, ohne
  ihn zu verbrauchen.
- **Der Code ist nach dem Aufruf verbraucht**, auch wenn es danach schiefgeht.
  Dann muss im Backend ein neuer erzeugt werden; die Fehlermeldung sagt das.
- **Kein halber Zustand:** Lässt sich die Kopplung nicht speichern, wird der
  eben angelegte Datenbankbenutzer wieder entfernt. Ein Zugang, von dem das
  Backend nichts weiß, wäre später nicht mehr zuzuordnen und bliebe für
  immer gültig.
- **Ein stillgelegtes Terminal koppelt nicht** (`aktiv = 0`).
- **`db.host` ist die Adresse aus Sicht des Terminals**, nicht die des Backends:
  `config: terminal_db_host_extern`, sonst der konfigurierte Datenbank-Host,
  und wenn der lokal ist, die Adresse, unter der das Terminal das Backend
  erreicht hat. Sonst bekaeme das Terminal `localhost` und spraeche sich selbst
  an.
- **Ohne HTTPS** enthält die Antwort ein Feld `warnung`, damit die
  Einrichtungsseite es anzeigen kann. Die Zugangsdaten waren dann im Netz
  mitlesbar.

### Entkoppeln (umgesetzt, P-2026-08-09-13)

Die Gegenrichtung zur Kopplung: In der Terminalverwaltung zeigt die Spalte
**Kopplung** je Gerät den Datenbankbenutzer und seit wann er gilt, daneben
steht **Entkoppeln** (POST, CSRF, Rückfrage).

Der Knopf löscht den Datenbankbenutzer, leert `db_benutzer`,
`db_benutzer_host`, `gekoppelt_am` und `gekoppelt_host` am Terminal-Datensatz
und entwertet offene Kopplungscodes. Danach braucht das Gerät einen neuen
Code.

Warum es das geben muss: **`aktiv = 0` genuegt nicht.** Das verhindert nur eine
neue Kopplung – der bestehende Datenbankbenutzer bleibt gültig. Wer ein
ausgemustertes Gerät mitnimmt, liest die Zugangsdaten aus `config.local.php`
und kommt weiter an alles, was dieses Terminal durfte.

Festgelegtes Verhalten:

- **Erst der Datenbankbenutzer, dann der Vermerk.** Scheitert das Löschen,
  bleibt der Vermerk stehen, das Gerät gilt weiter als gekoppelt und der
  Zugang lässt sich erneut entfernen. Andersherum bliebe ein gültiger
  Benutzer übrig, von dem niemand mehr weiß, wozu er gehört.
- **Offene Codes werden zuerst entwertet**, auch wenn gar kein Zugang besteht.
  Ein noch gültiger Code wäre genau der Weg, sich das eben Abgemeldete
  zurückzuholen.
- **Ein zweiter Aufruf ist harmlos** und meldet „war nicht gekoppelt“.

### Warum ein eigener Benutzer je Terminal

- **Einzeln sperrbar:** Gerät verloren oder ausgetauscht → `DROP USER`, fertig.
  Kein Passwortwechsel auf allen anderen Terminals.
- **Eingeschraenkte Rechte:** Ein Terminal braucht nur Stempeln, Auftragszeiten
  und Urlaubsanträge. Es braucht **kein** `DELETE`, kein `DROP`, keinen Zugriff
  auf Stundenkonto und Lohnkorrekturen.
- **Nachvollziehbar:** In den Datenbank-Protokollen ist erkennbar, welches
  Gerät was getan hat.

### Rechte des Terminal-Benutzers (umgesetzt und geprüft, P-2026-08-08-35)

Die Liste steht in `services/TerminalDbBenutzerService.php` und ist **aus dem
Code hergeleitet** – alles, was `public/terminal.php` und die von dort genutzten
Dienste anfassen. Sie weicht an mehreren Stellen vom ursprünglichen Vorschlag
ab; das war kein Aufweichen, sondern das Ergebnis des Nachsehens.

| Tabelle | Recht |
| --- | --- |
| `zeitbuchung` | SELECT, INSERT |
| `auftrag`, `auftrag_arbeitsschritt`, `auftragszeit` | SELECT, INSERT, UPDATE |
| `urlaubsantrag` | SELECT, INSERT, UPDATE |
| `feiertag` | SELECT, INSERT |
| `system_log` | SELECT, INSERT |
| `db_injektionsqueue` | SELECT, INSERT, UPDATE |
| `mitarbeiter` | SELECT **ohne** `passwort_hash` (spaltenweise) + UPDATE **nur** auf `rfid_code` |
| Rollen/Rechte: `rolle`, `recht`, `rolle_hat_recht`, `mitarbeiter_hat_rolle`, `mitarbeiter_hat_rolle_scope`, `mitarbeiter_hat_recht`, `mitarbeiter_hat_abteilung`, `mitarbeiter_genehmiger` | SELECT |
| Stammdaten: `maschine`, `terminal`, `config`, `abteilung`, `arbeitsschritt_katalog` | SELECT |
| Auswertung: `zeit_rundungsregel`, `pausenfenster`, `pausenentscheidung`, `betriebsferien`, `krankzeitraum`, `kurzarbeit_plan`, `urlaub_kontingent_jahr`, `tageswerte_mitarbeiter`, `monatswerte_mitarbeiter`, `stundenkonto_korrektur` | SELECT |
| alles Übrige, insbesondere `terminal_kopplung` und `stundenkonto_batch` | **kein Zugriff** |

Kein `DELETE`, kein `DROP`, kein `ALTER`, kein `CREATE` – nirgends.

**Warum es vom Vorschlag abweicht:**

- **Rollen und Rechte müssen lesbar sein.** Das Terminal blendet Knoepfe je
  nach Berechtigung ein (z. B. „Urlaubsanträge“ für Genehmiger). Ohne
  Lesezugriff auf die Rechtetabellen wäre jeder Mitarbeiter am Terminal
  rechtlos.
- **`stundenkonto_korrektur` muss lesbar sein.** Das Terminal zeigt seit
  P-2026-01-17-19 Gut- und Minusstunden an. Schreiben darf es dort nichts –
  Buchungen aufs Stundenkonto bleiben Sache des Backends. `stundenkonto_batch`
  bleibt ganz gesperrt.
- **`urlaubsantrag` braucht UPDATE.** Genehmiger können laut
  `docs/fachregeln/urlaub_abwesenheit_feiertage.md` auch am Terminal
  entscheiden.
- **`feiertag` braucht INSERT.** Der `UrlaubService` generiert die Feiertage
  eines Jahres bei Bedarf nach. Ohne dieses Recht rechnet ein Terminal im
  Januar ohne die Feiertage des neuen Jahres – und das fällt niemandem auf.
  Dieses stille Falschrechnen wäre gefaehrlicher als das Recht selbst.
- **`db_injektionsqueue` als Rückfallebene.** Normalerweise liegt die
  Offline-Queue in der lokalen Ausweichdatenbank des Terminals; fehlt die,
  greift der `OfflineQueueManager` auf die Hauptdatenbank zurück. Kein
  `DELETE`: hängengebliebene Einträge raeumt ein Admin im Backend weg.

**`passwort_hash` – gelöst (P-2026-08-09-16).** Früher stand hier, das gehe
nicht ohne Codeaenderung. Das stimmte zur Haelfte: Spaltenrechte verbieten
tatsaechlich `SELECT *`, aber im gesamten Terminalpfad gab es dafür **genau
zwei** Stellen, beide im `ReportService` und beide nur an einem einzigen Wert
interessiert (`wochenarbeitszeit`). Sie holen ihn jetzt über
`MitarbeiterModel::holeWochenarbeitszeit()`.

Damit wird das Leserecht auf `mitarbeiter` **spaltenweise** vergeben: alle
Spalten ausser `passwort_hash`, zur Kopplungszeit aus dem `information_schema`
aufgelöst. Eine später hinzugekommene Spalte ist damit automatisch dabei,
sobald ein Gerät neu koppelt – eine von Hand gepflegte Positivliste wäre beim
nächsten Schema-Zuwachs still unvollständig.

Zwei Dinge sind bewusst so gebaut:

- **Ist die Spaltenliste nicht bestimmbar, entsteht gar kein Zugang.** Auch
  dann nicht, wenn `passwort_hash` gar nicht mehr existierte: Wäre die Spalte
  umbenannt worden, sperrte die Liste nichts mehr, und niemand hätte es
  gemerkt. Die Kopplung schlaegt in dem Fall mit Protokolleintrag fehl.
- **`SELECT *` auf `mitarbeiter` schlaegt am Terminal fehl** – gewollt. Wer den
  Terminalpfad erweitert, merkt das sofort. `MitarbeiterModel` darf sein
  `SELECT *` behalten, denn es läuft nur noch im Backend.

**Bereits gekoppelte Geräte behalten ihr altes, weites Recht**, bis sie neu
gekoppelt werden – der Zugang wird nur beim Koppeln vergeben. Wer sichergehen
will: in der Terminalverwaltung *Entkoppeln*, dann neuen Kopplungscode. Ob ein
Gerät noch das alte Recht hat, zeigt

```sql
SHOW GRANTS FOR 'term_...'@'%';
```

Steht dort `GRANT SELECT ... ON \`zeiterfassung\`.\`mitarbeiter\`` **ohne**
Spaltenliste in Klammern, ist es ein Zugang von vor P-2026-08-09-16.

### Von welchem Rechner darf sich das Terminal verbinden

Der Benutzer wird standardmäßig für `%` angelegt (beliebiger Rechner),
einstellbar über den Konfigurationsschluessel `terminal_db_host_muster`
(z. B. `192.168.10.%`). Grund für den weiten Standard: Terminals bekommen ihre
Adresse per DHCP – eine feste Bindung kappt beim nächsten Neustart still den
Zugang.

**Stolperstein:** Laeuft ein Terminal ausnahmsweise auf demselben Rechner wie
die Datenbank, kann ein `%`-Konto von **anonymen Konten** (`''@'localhost'`)
verdeckt werden – MariaDB wählt den spezifischeren Host-Eintrag. In diesem Fall
entweder die anonymen Konten entfernen (`mariadb-secure-installation`) oder
`terminal_db_host_muster` auf `localhost` setzen.

### Was dafür nötig ist, und was das kostet

**Entschieden:** Das Backend legt die Benutzer selbst an (automatisch).

Damit das Backend Benutzer anlegen kann, braucht **sein** Datenbankbenutzer das
Recht `CREATE USER` sowie `GRANT OPTION` auf das Schema `zeiterfassung`. Die
dafür nötigen Anweisungen stehen in
`sql/06_migration_terminal_db_benutzer.sql` und müssen von einem Administrator
einmal ausgeführt werden – die Anwendung kann sich diese Rechte nicht selbst
geben. Fehlen sie, läuft alles Übrige normal weiter; nur die Kopplung bricht
mit einer verständlichen Meldung ab. Das ist kein Nebeneffekt, sondern eine
bewusste Abwaegung:

- **Vorteil:** Die Kopplung läuft ohne Handarbeit, auch für zwanzig Geräte.
- **Nachteil:** Wer die Weboberflaeche übernimmt, kann Datenbankbenutzer
  anlegen. Begrenzt wird das dadurch, dass `GRANT OPTION` nie mehr vergeben
  kann, als der Vergebende selbst hat – die Rechte des Backends sind also die
  Obergrenze.
- **Ausweichweg, falls das zu weit geht:** Das Backend legt den Benutzer nicht
  selbst an, sondern zeigt dem Administrator die fertige SQL-Anweisung zum
  einmaligen Ausführen. Gleiche Sicherheit für das Terminal, kein erhöhtes
  Recht für die Anwendung, dafür ein manueller Schritt je Gerät.

### Sicherheitsanforderungen an die Kopplung

- Kopplungscode: einmalig, zeitlich begrenzt, nach wenigen Fehlversuchen
  gesperrt (sonst lässt er sich durchprobieren).
- **Verschluesselte Verbindung dringend empfohlen:** Bei der Kopplung gehen
  Zugangsdaten über das Netz. Ohne HTTPS liest sie jeder mit, der im
  Hallennetz mithoert. Ist HTTPS nicht möglich, sollte die Kopplung wenigstens
  nur in einem abgesicherten Netzsegment erfolgen.
- Kopplung protokollieren (`system_log`): wer, wann, welches Gerät.
- Erneute Kopplung eines vorhandenen Terminals ersetzt den alten
  Datenbankbenutzer, statt einen zweiten anzulegen.

## 3. Randbedingungen

- **Distributionsunabhängig.** Nicht auf eine Distribution festgelegt, sondern
  eine kleine Erkennungsschicht über `/etc/os-release` für die vier
  Paketmanager-Familien: `apt` (Debian/Raspberry Pi OS/Ubuntu), `pacman`
  (Arch/CachyOS), `dnf` (Fedora/RHEL), `zypper` (openSUSE).

  Sie steht seit P-2026-08-09-20 in **einer** Datei,
  `scripts/terminal/_paketfamilie.sh`, die alle drei Installationsskripte
  einlesen: `erkenne_paketfamilie`, `paketquellen_auffrischen`,
  `paket_installieren`. Wer eine Distribution ergänzt, fasst genau diese Datei
  an. Vorher stand die Erkennung in jedem Skript noch einmal – die vierte
  Kopie hätte irgendwann jemand vergessen.

  **Welche Pakete** eine Stufe braucht, steht bewusst weiter im jeweiligen
  Skript: Das ist je Stufe verschieden (Webserver, Browser, Python) und gehört
  dorthin, wo es gebraucht wird. Gemeinsam ist nur, **wie** man auf dieser
  Familie installiert.
- **Datenbankzugriff:** Das Terminal verbindet sich **direkt über das Netz**
  auf die MariaDB des Hauptsystems (so ist `config.local.php` heute gebaut:
  `db` = Hauptdatenbank, `offline_db` = lokale Ausweichdatenbank).
- **Beide RFID-Varianten** müssen unterstuetzt werden – USB-Keyboard-Wedge und
  RC522 über SPI mit WebSocket-Bridge.
- **Idempotent:** Mehrfaches Ausführen ist unschaedlich und repariert einen
  halbfertigen Stand.
- **Protokolliert:** Alles nach `/var/log/zeiterfassung-terminal-setup.log`,
  damit bei einem Fehlschlag nachvollziehbar bleibt, was passiert ist.
- **Unbeaufsichtigt wiederholbar:** Wer zwanzig Terminals aufsetzt, will nicht
  zwanzigmal dieselben Fragen beantworten – daher eine Antwortdatei.

## 4. Eingaben

**Das Skript fragt keine Zugangsdaten mehr ab** – die kommen aus der Kopplung.
Es bleibt nur, was die Maschine betrifft:

| Wert | Beispiel | Zweck |
| --- | --- | --- |
| `RFID_VARIANTE` | `usb` \| `rc522` \| `frage` | siehe Abschnitt 6 |
| `TASTATURLAYOUT` | `de` | entscheidend für Scanner, siehe 6.3 |
| `BILDSCHIRM_DREHUNG` | `normal` \| `left` \| `right` \| `inverted` | Touchscreen |
| `GIT_REPO` / `GIT_BRANCH` | `https://github.com/…` / `main` | Codequelle |

Alles per `terminal.conf` neben dem Skript vorgebbar, sonst interaktive
Abfrage. Damit ist dasselbe Abbild auf allen Geräten verwendbar; unterscheiden
tun sie sich erst durch die Kopplung.

## 5. Ablauf in Phasen

Drei Phasen. Die ersten beiden erledigt das Skript; sie sind getrennt, weil das
Aktivieren von SPI einen **Neustart** erfordert – Phase 2 läuft danach
automatisch weiter (systemd-Einmaldienst, der sich anschließend selbst
deaktiviert). Phase 3 ist die Kopplung am Gerät und braucht kein Skript.

### Phase 1 – Grundsystem
1. Distribution und Paketmanager erkennen, Vorbedingungen prüfen (root,
   Netzwerk).
2. Pakete installieren: Webserver, PHP mit `pdo_mysql`/`mbstring`/`gd`,
   MariaDB (nur für die lokale Ausweichdatenbank), Git. **Grafikstack und
   Browser** gehören zum Kiosk und werden dort installiert (Stufe 4) – sie
   ohne den Kiosk mitzunehmen brachte nur Wartezeit und liess sich im Container
   nicht prüfen. Python nur bei RC522 (Stufe 5).
3. Code aus Git holen, Webserver auf `public/` zeigen lassen.
4. **Keine** `config.local.php` schreiben – das Terminal startet bewusst
   unkonfiguriert und zeigt die Einrichtungsseite. Stattdessen: lokale
   Ausweichdatenbank anlegen und ihre Zugangsdaten nach
   `config/geraet.local.php` schreiben (Abschnitt 2b) – das ist alles, was das
   Skript an Konfiguration hinterlässt.
5. Tastaturlayout systemweit setzen (siehe 6.3).
6. Bei RC522: SPI aktivieren, Phase-2-Dienst einrichten, **Neustart**.

### Phase 2 – Peripherie und Kiosk
7. RFID einrichten (Abschnitt 6).
8. Touchscreen prüfen und drehen (Abschnitt 6.4).
9. Kiosk einrichten (Abschnitt 7) – der Browser landet auf der
   Einrichtungsseite, solange keine Konfiguration vorliegt.
10. Selbsttest (Abschnitt 8), Ergebnis auf den Bildschirm und ins Log.

### Phase 3 – Kopplung am Gerät (kein Skript)
11. Am Touchscreen Server-Adresse und Kopplungscode eingeben; das Terminal holt
    sich alles Weitere selbst (Abschnitt 2a).

## 5a. Das Grundsystem-Skript (umgesetzt, P-2026-08-09-04)

`scripts/terminal/install_terminal.sh` setzt Phase 1 um. Aufruf:

```bash
sudo ./scripts/terminal/install_terminal.sh [antwortdatei]
```

Ohne Argument wird `terminal.conf` neben dem Skript gesucht; Vorlage ist
`terminal.conf.example`. Fehlt sie, fragt das Skript nach – aber **nur**, wenn
ein Mensch davorsitzt. Ein unbeaufsichtigter Lauf (Image-Bau) darf nicht an
einer Eingabeaufforderung hängenbleiben.

**Alles Distributionsabhängige steht in einer einzigen Tabelle** im Skript:
Paketliste, Dienstname, Webserver-Benutzer und Ablageort der
Webserver-Konfiguration je Familie (`apt`, `pacman`, `dnf`, `zypper`). Verstreute
Sonderfaelle waren der übliche Grund, warum solche Skripte nach der zweiten
Distribution unwartbar werden.

**PHP hängt überall gleich am Webserver:** `php-fpm` plus `mod_proxy_fcgi`,
statt je Familie ein anderes PHP-Modul. Unterschiedlich ist nur der Socketpfad –
und den sucht das Skript (`/run/php-fpm/*.sock`, `/run/php/*.sock`, …), mit
`127.0.0.1:9000` als Rückfall. Damit ist die erzeugte Apache-Konfiguration für
alle vier Familien dieselbe Datei.

**Das Passwort der Ausweichdatenbank wird bei einem zweiten Lauf
wiederverwendet**, nicht erneuert. Ein bereits gekoppeltes Terminal traegt es in
seiner `config.local.php`; ein frisches Passwort würde ihm stillschweigend die
Offline-Queue kappen – der Ausfall fällt dann erst beim nächsten Netzausfall
auf, also genau dann, wenn er am meisten schadet. Gesucht wird deshalb **zuerst
in `config.local.php`**, erst danach in `geraet.local.php`: Die erste ist die
Datei, aus der ein gekoppeltes Terminal die Queue-Zugangsdaten wirklich liest.
Die zweite kann fehlen oder – nach einem Lauf mit nicht ansprechbarer Datenbank
– ein leeres Passwort tragen (P-2026-08-09-05).

**Der Code gehört root**, der Webserver-Benutzer darf ihn nur lesen.
Schreibrechte bekommt er ausschließlich für `config/` – dort legt die Kopplung
`config.local.php` an – und für `public/uploads/`. Unter SELinux (Fedora/RHEL)
setzt das Skript zusätzlich die Kontexte und den Schalter
`httpd_can_network_connect_db`; ohne ihn erreicht das Terminal die
Hauptdatenbank des Backends nicht, und zwar ohne erkennbare Ursache.

**Das Tastaturlayout wird an drei Stellen gesetzt** (X11, Konsole, bei Debian
zusätzlich `/etc/default/keyboard`), weil je nach Distribution eine andere
davon greift. Dazu die Zeitzone: Die Uhr im Terminal-Header läuft nach der
Systemzeit, ein Gerät in UTC zeigt der Halle stundenversetzte Buchungszeiten.

**Am Ende steht eine Liste mit OK/FEHLT** (Webserver, `pdo_mysql`,
Ausweichdatenbank, `geraet.local.php`, Schreibrecht auf `config/`, HTTP-Antwort
von `terminal.php`) sowie alle Warnungen des Laufs gesammelt. Das ist die kleine
Fassung von Abschnitt 8; der vollständige Selbsttest mit Scan-Proben kommt mit
Stufe 6.

**Bewusst nicht in diesem Skript:** Kiosk (Stufe 4, eigenes Skript
`install_kiosk.sh`, Abschnitt 7), Peripherie (Stufe 5), Selbsttest mit Hardware
(Stufe 6). Ein Lauf ohne systemd (Container) bricht nicht ab, sondern warnt –
sonst wäre die Stufe nicht im Container prüfbar.

## 6. Peripherie (umgesetzt, P-2026-08-09-17)

`scripts/terminal/install_peripherie.sh`, sechs Schritte. Gesteuert wird es
über **`RFID_VARIANTE`** (`usb` | `bridge` | `keine`) und
**`BILDSCHIRM_DREHUNG`** (`normal` | `links` | `rechts` | `kopf`) aus der
Antwortdatei.

Am Ende schreibt es den `rfid_ws`-Block in `config/geraet.local.php` fort –
**nur diesen Block.** Die Zugangsdaten der Ausweichdatenbank werden aus der
vorhandenen Datei übernommen und vor dem Umbenennen gegengelesen. Wäre das
Passwort dabei verloren gegangen, liefe das Terminal weiter, aber seine Queue
wäre tot: derselbe Fehler wie in P-2026-08-09-05, deshalb die Gegenprobe.

**Ein bereits gekoppeltes Gerät übernimmt die Änderung nicht von selbst.**
Die Einrichtungsseite liest `geraet.local.php` nur *beim* Koppeln. Das Skript
sagt das, wenn es eine `config.local.php` vorfindet.

### 6.1 RFID – USB-Leser (Keyboard-Wedge)
Braucht keine Treiber; der Leser tippt wie eine Tastatur. Das Skript setzt
`rfid_ws.enabled = false` in `config/geraet.local.php` (Abschnitt 2b) und bietet
einen Scan-Test an.

### 6.2 RFID – RC522 über SPI
SPI aktivieren (Boot-Konfiguration, danach Neustart), Python-Abhängigkeiten
installieren, `docs/terminal/rfid_ws.py` und `rfid-ws.service` einrichten,
`rfid_ws.enabled = true` in `config/geraet.local.php` setzen. Die Anleitung dazu liegt bereits in
`docs/terminal/rfid-ws_rollout.md` – das Skript automatisiert genau diese
Schritte.

### 6.3 Barcode-Scanner – der unterschaetzte Teil
Der Scanner braucht **keine Treiber**, er tippt wie eine Tastatur. Genau darin
liegt die Falle: Steht das System auf US-Layout und der Code enthält
Sonderzeichen oder `y`/`z`, kommt im Eingabefeld etwas anderes an, als auf dem
Etikett steht. Das Terminal bucht dann klaglos einen falschen Code.

Deshalb ist das Setzen des Tastaturlayouts **Pflichtschritt**, nicht Kosmetik –
und der Selbsttest fordert ausdrücklich zum Scannen eines bekannten Codes auf
und vergleicht das Ergebnis.

### 6.4 Touchscreen
Vorhandensein über `libinput list-devices` erkennen: gesucht wird das erste
Gerät, dessen Faehigkeiten `touch` nennen – ein Touchpad meldet `pointer` und
fällt damit heraus. Drehung und Zuordnung zum richtigen Bildschirm sind
geräteabhängig und werden aus `BILDSCHIRM_DREHUNG` gesetzt; automatisch
erraten lässt sich das nicht zuverlässig.

**Gedreht wird auf zwei ganz verschiedenen Wegen** – das ist der unangenehmste
Teil dieser Stufe:

- **X11:** zur Laufzeit. Das Skript legt `/usr/local/bin/zeiterfassung-peripherie-x11`
  an; der Kioskstart ruft es innerhalb der X-Sitzung auf. Dort dreht `xrandr`
  das Bild und `xinput` die Berührung über die *Coordinate Transformation
  Matrix*. **Beides ist nötig:** Wer nur das Bild dreht, bekommt ein Gerät,
  bei dem der Finger 90 Grad daneben trifft – schlimmer als gar nicht gedreht,
  weil es zunächst richtig aussieht.
- **Wayland (`cage`):** gar nicht. cage hat keinen Schalter zum Drehen. Dort
  dreht der Kernel den Bildschirm über die Startzeile
  (`video=<Ausgang>:rotate=90`), und die Berührung folgt automatisch. Weil das
  einen Neustart braucht und der Ausgangsname geräteabhängig ist, **setzt das
  Skript es nicht**, sondern gibt die einzutragende Zeile aus und meldet eine
  Warnung. Eine halb gedrehte Anzeige stillschweigend zu hinterlassen wäre
  schlechter als eine klare Ansage.

## 7. Kiosk (umgesetzt, P-2026-08-09-09)

Anforderung:

- Autologin für einen eigenen Benutzer `terminal` (nicht root).
- Browser im Vollbild auf `…/public/terminal.php`, ohne Bedienelemente.
- Bildschirmschoner und Energiesparen aus, Mauszeiger ausblenden.
- Neustart des Browsers, falls er abstuerzt.
- Wayland oder X11 je nachdem, was die Distribution mitbringt: bevorzugt ein
  schlanker Wayland-Kiosk (`cage`), sonst X11 mit minimalem Fenstermanager.

Umgesetzt in `scripts/terminal/install_kiosk.sh`, einem **zweiten** Skript:

```bash
sudo ./scripts/terminal/install_kiosk.sh [antwortdatei]
```

Es liest dieselbe `terminal.conf` wie Stufe 3 und legt drei Dinge an:
`/etc/zeiterfassung-kiosk.conf` (Adresse, Browser, Anzeigeweg),
`/usr/local/bin/zeiterfassung-kiosk` (Startskript) und
`/etc/systemd/system/zeiterfassung-kiosk.service`.

**Kein Autologin über getty, sondern ein Systemdienst.** Der übliche Weg
(`agetty --autologin` und ein Aufruf im Anmeldeprofil) hätte den geforderten
Neustart nach einem Absturz in einer Schleife in `~/.bash_profile` nachbauen
müssen. Der Dienst bekommt ihn mit `Restart=always` geschenkt und lässt sich
ausserdem gezielt anhalten, wenn jemand am Gerät arbeiten will.
`PAMName=login` erzeugt dabei eine echte Anmeldesitzung – ohne die gibt es
keinen Seat, und weder `cage` noch Xorg bekommen Bildschirm und
Eingabegeräte. `Conflicts=getty@tty1.service` verhindert, dass sich
Anmeldeaufforderung und Kiosk um dieselbe Konsole streiten.

**Die Meldungen des Browsers stehen nicht unter der Einheit.** Wegen
`PAMName=login` laufen `cage` und Browser in einer eigenen Sitzung; bei
`journalctl -u zeiterfassung-kiosk` erscheinen nur Start und Stopp des
Dienstes. Der Weg zu den Fehlern des Browsers ist
`journalctl -t zeiterfassung-kiosk`. Das Skript sagt das am Ende ausdrücklich
– es einmal zu wissen erspart die Suche nach einem Fehler, der scheinbar keine
Spur hinterlässt.

**Der Anzeigeweg entscheidet sich am Gerät, nicht in der Tabelle:** Zuerst
wird `cage` installiert; liegt danach kein `cage` vor (auf aelteren openSUSE
gibt es das Paket nicht), kommen Xorg, `openbox` und `unclutter` dazu.
`KIOSK_ANZEIGE` in der Antwortdatei erzwingt einen der beiden Wege. Unter X11
ruft sich das Startskript über `xinit` selbst noch einmal auf – so bleibt
alles in **einer** Datei, statt eine zweite `.xinitrc` zu pflegen.

**Bildschirmschoner und Mauszeiger:** Unter X11 übernehmen das `xset` und
`unclutter`. Unter Wayland gibt es beides nicht – `cage` dunkelt von sich aus
nicht ab, und einen Mauszeiger zeigt es nur, wenn tatsaechlich eine Maus
angeschlossen ist. Zusätzlich wird die Abdunkelung der Textkonsole
abgeschaltet (`setterm --blank 0`), die sonst unter `cage` durchschlaegt.

**Der Absturzvermerk von Chromium wird vor jedem Start zurückgesetzt.** Sonst
erscheint nach einem Absturz eine Leiste „Wiederherstellen“, die auf einem
Gerät ohne Tastatur niemand wegbekommt. Dazu Schalter gegen Zoom durch zwei
Finger und gegen „Zurück“ per Wischgeste – beides loest am Touchscreen sonst
laufend versehentliche Navigation aus.

**Ein vorhandener Anmeldebildschirm wird abgeschaltet** (`display-manager`
deaktiviert, Startziel `multi-user.target`), weil er den Kiosk sonst verdeckt.
Wer das nicht will, setzt `KIOSK_ANMELDESCHIRM="belassen"` – gedacht für den
Fall, dass das Skript versehentlich auf einem Arbeitsplatzrechner läuft.

**Der Kioskbenutzer kommt nicht an die Zugangsdaten.** `config/` gehört seit
Stufe 3 `root` und der Webserver-Gruppe (2770); der Benutzer `terminal` ist
nicht darin. Die Ergebnisliste prüft das ausdrücklich mit – ein
Vollbildbrowser mit Netzzugang ist der Teil des Geräts, der am ehesten
übernommen wird.

## 8. Selbsttest zum Abschluss (umgesetzt, P-2026-08-09-18)

1. Webserver liefert die Terminalseite aus (HTTP 200).
2. Hauptdatenbank erreichbar, Anmeldung erfolgreich.
3. Lokale Ausweichdatenbank vorhanden, Tabelle `db_injektionsqueue` da.
4. `?aktion=health` des Terminals antwortet.
5. Bei RC522: Dienst läuft, Port erreichbar.
6. Interaktiv: einmal RFID-Chip scannen, einmal Barcode scannen – das Skript
   zeigt an, was tatsaechlich angekommen ist.

Ergebnis als Liste mit OK/FEHLT, damit man vor dem Verlassen des Geräts weiß,
ob es einsatzbereit ist.

`scripts/terminal/selbsttest.sh`. **Aendert nichts** – es wird nur gelesen und
gefragt. Der Rückgabewert ist 0, wenn nichts fehlt, sonst 1; damit lässt er
sich auch aus einer Überwachung heraus aufrufen.

Drei Zustände statt zwei: neben `OK` und `FEHLT` gibt es `--` für *nicht
geprüft*. Das ist der Unterschied zwischen „der Kiosk läuft nicht“ und „hier
läuft kein systemd, also war nichts zu sehen“ – ein Test, der Unwissen als
Erfolg meldet, ist wertlos.

Woher die Werte kommen: aus `/etc/zeiterfassung-peripherie.conf` und
`/etc/zeiterfassung-kiosk.conf`, **nicht** aus der Antwortdatei. Was auf dem
Gerät eingerichtet wurde, wiegt schwerer als das, was jemand einmal
aufschreiben wollte.

Zwei Punkte über die Liste oben hinaus:

- **Passwort-Hashes.** Der Test verbindet sich mit den Zugangsdaten des Geräts
  und versucht, `passwort_hash` zu lesen. Gelingt es, traegt dieses Terminal
  einen Zugang von vor P-2026-08-09-16 und gehört neu gekoppelt (siehe 2a).
- **Fehlerhafte Queue-Einträge** aus dem Health-Endpunkt. Sie bedeuten, dass
  schon gebucht wurde und etwas davon nicht angekommen ist.

Der **Scan-Test** ist der einzige Teil, der einen Menschen braucht – und der
wichtigste. Er zeigt an, was tatsaechlich angekommen ist, und fragt beim
Barcode ausdrücklich nach, ob es mit dem Etikett übereinstimmt. Grund steht
in 6.3: Ein falsches Tastaturlayout fällt sonst **nirgends** auf. Ohne
Bediener (kein Terminal an der Eingabe) oder mit `--ohne-scan` wird der
Abschnitt übersprungen und als solcher gemeldet, nicht als bestanden.

## 9. Was sich bewusst **nicht** vollautomatisch lösen lässt

Ehrlich vorab, damit niemand es später als Fehler meldet:

- **USB-RFID-Leser sind von einer Tastatur nicht unterscheidbar.** Ob ein Leser
  angeschlossen ist, kann das Skript nicht wissen – nur der Scan-Test zeigt es.
- **Touchscreen-Drehung** ist geräteabhängig und wird abgefragt.
- **SPI braucht einen Neustart.** Daher die zwei Phasen.
- **Paketnamen** unterscheiden sich je Distribution; die Zuordnungstabelle deckt
  die vier grossen Familien ab. Exoten müssen von Hand nacharbeiten.

## 10. Sicherheit – gelöst durch die Kopplung

In der ersten Fassung dieser Spezifikation trug das Terminal die Zugangsdaten
zur Hauptdatenbank auf dem Gerät, und zwar dieselben wie alle anderen. Wer
physisch an ein Hallenterminal kam, kam an die gesamte Datenbank samt aller
Personendaten.

Die Kopplung loest das: Jedes Terminal bekommt **einen eigenen Benutzer mit
eingeschraenkten Rechten**, einzeln sperrbar. Auf dem Gerät liegt damit nur
noch, was dieses eine Terminal ohnehin darf.

### Das Backend läuft auf einem Terminal nicht mit (P-2026-08-09-19)

Der Webserver eines Terminals zeigt auf `public/` – damit lag neben
`terminal.php` auch `index.php` im Zugriff, und ein Hallengerät lieferte die
**Anmeldemaske des Backends** aus. `public/index.php` bricht deshalb sofort ab
und leitet auf `terminal.php` um, wenn eines von beiden zutrifft:

- `installation_typ` steht auf `terminal`, **oder**
- es gibt eine `config/geraet.local.php`, aber noch keine
  `config.local.php`. Das ist ein aufgesetztes, noch nicht gekoppeltes Gerät –
  sonst bliebe zwischen Aufstellen und Koppeln ein Fenster von Tagen offen, in
  dem in der Halle die Anmeldemaske hängt. Steht in einer vorhandenen
  `config.local.php` ausdrücklich `backend`, gewinnt diese Entscheidung.

**Ohne Ausnahme für den Kopplungs-Endpunkt.** Der läuft auf dem Backend; ein
Terminal, das ihn selbst anboete, verteilte Datenbankbenutzer – genau das, was
die Kopplung verhindern soll.

**Was das nicht ist: ein Datenschutz.** Es ist **dieselbe Datenbank** wie die
des Backends – das Terminal bekommt bei der Kopplung nur einen eigenen Benutzer
auf demselben Schema. Was es unterscheidet, sind die **Rechte**, nicht die
Daten. An einem gekoppelten Testgerät nachgemessen:

| Für das Terminal lesbar | Gesperrt |
| --- | --- |
| Namen, Personalnummern, E-Mail, Geburtsdatum | Passwort-Hashes |
| Zeitbuchungen **aller** Mitarbeiter | Kopplungscodes anderer Terminals |
| Urlaubsanträge, Stundenkonto-Korrekturen, Rollen und Rechte | |

Wer das Gerät aufschraubt, liest die Zugangsdaten aus `config.local.php` und
kommt an die linke Spalte – mit oder ohne Backend-Oberflaeche. Diese Sperre
verkleinert die Angriffsflaeche; der Schutz der Daten liegt bei der Rechteliste
weiter oben. Der Selbsttest (Abschnitt 8) prüft die Sperre mit.

Rückweg für die Fernwartung: `installation_typ` in `config.local.php` auf
`backend` setzen. Das braucht Zugriff auf die Datei – die richtige Huerde.

### Was weiterhin gilt

- Die Zugangsdaten liegen trotzdem lesbar auf dem Gerät – der Schaden ist
  begrenzt, aber nicht null. Physischer Schutz der Geräte bleibt sinnvoll.
  Passwort-Hashes gehören seit P-2026-08-09-16 **nicht** mehr dazu (siehe 2a).
- Bei der Kopplung selbst gehen Zugangsdaten über das Netz (siehe 2a).
- Ein ausgemustertes Terminal muss im Backend **entkoppelt** werden (Knopf in
  der Terminalverwaltung, siehe 2a), sonst bleibt sein Datenbankbenutzer
  gültig. Nur stilllegen (`aktiv = 0`) reicht dafür nicht.


## 11. Umsetzung in Stufen

1. **Kopplung im Backend** – Maske „Terminal anmelden“, Kopplungscode,
   Endpunkt, Anlage des Datenbankbenutzers, Entkoppeln. Ohne Hardware testbar.
   **Fertig** (P-2026-08-08-30, -31, -35, -36, P-2026-08-09-13). Am 09.08.2026
   gegen die lokale Datenbank durchgespielt: koppeln, entkoppeln, GET und
   falsches CSRF-Token wirkungslos, zweiter Lauf und unbekannte ID sauber –
   22 von 22 Punkten.
2. **Einrichtungsseite im Terminal** – erscheint bei fehlender Konfiguration,
   nimmt Adresse und Code entgegen, schreibt `config.local.php`. Ohne Hardware
   testbar. **Fertig** (P-2026-08-09-01).
3. **Grundsystem-Skript** – Pakete, Code, Webserver. Im Container testbar.
   **Fertig** (P-2026-08-09-04): `scripts/terminal/install_terminal.sh`, siehe
   Abschnitt 5a. Am 09.08.2026 vollständig auf Debian 12 im Container gelaufen
   (systemd als PID 1): Ergebnisliste sechs von sechs OK, zweiter Lauf ohne
   Warnung, Lauf ohne systemd bricht wie zugesichert nicht ab. Der Lauf brachte
   zwei Fehler ans Licht, beide behoben (P-2026-08-09-05, -06). **Auf echter
   Hardware und auf den anderen drei Paketfamilien ist es weiterhin nicht
   gelaufen** – der Container deckt nur `apt` ab.
4. **Kiosk** – Autologin, Browser im Vollbild, Grafikstack.
   **Fertig** (P-2026-08-09-09): `scripts/terminal/install_kiosk.sh`, siehe
   Abschnitt 7. Am 09.08.2026 im selben Debian-12-Container wie Stufe 3
   gelaufen, zehn von zehn Punkten OK, Wiederholung ohne Warnung, beide
   Anzeigewege (`cage` und Xorg) durchgespielt. **Ein Bild hat dabei niemand
   gesehen** – ein Container hat keinen Bildschirm; `cage` kommt bis zum
   Zugriff auf das Grafikgerät und bricht dort ab, Xorg startet und findet
   keinen Treiber. Beides ist der erwartete Abbruch, aber kein Beleg, dass der
   Kiosk auf einem Gerät erscheint. Das zeigt erst eine VM mit Grafik oder
   echte Hardware. Wie bei Stufe 3 gilt: nur `apt` geprüft.
5. **Peripherie** – RFID, Touchscreen, Drehung.
   **Fertig** (P-2026-08-09-17): `scripts/terminal/install_peripherie.sh`, siehe
   Abschnitt 6. Am 09.08.2026 im selben Debian-12-Container gelaufen, beide
   RFID-Betriebsarten durchgespielt: `usb` fuenf von fuenf, `bridge` neun von
   neun, Wiederholung ohne Abweichung. Das Passwort der Ausweichdatenbank
   überlebt die Fortschreibung von `geraet.local.php` – eigens geprüft, weil
   genau das in P-2026-08-09-05 schon einmal schiefging. Das X11-Drehskript
   wurde mit vorgetaeuschten `xrandr`/`xinput` gegen alle vier Drehungen
   geprüft. **Nicht geprüft, weil dafür Hardware nötig ist:** ob ein
   angeschlossener Leser tatsaechlich Zeichen liefert und ob ein gedrehter
   Touchscreen richtig trifft.
6. **Selbsttest** – rundet ab und macht das Ergebnis prüfbar.
   **Fertig** (P-2026-08-09-18): `scripts/terminal/selbsttest.sh`, siehe
   Abschnitt 8. Am 09.08.2026 gegen zwei Staende geprüft: im Container
   (ungekoppelt) und gegen eine echte gekoppelte Installation auf dem
   Entwicklungsrechner – zehn von zehn, einschließlich der Gegenprobe, dass
   ein Gerät mit altem, weitem Datenbankrecht als Fund gemeldet wird. Die drei
   Wege des Scan-Tests (sauber, vertauscht, übersprungen) wurden über ein
   Pseudoterminal durchgespielt.

**Wo wir stehen** (Stand 09.08.2026, aus der Liste oben ablesbar, damit die
Zahl nicht driftet): **alle sechs Stufen gebaut.** Stufe 1 und 2 sind
funktional geschlossen und gegen die Datenbank durchgespielt; Stufe 3 bis 6
sind gebaut und im Container geprüft, aber nur auf **einer von vier
Paketfamilien** (`apt`).

Nach dem Zusammenlegen der Paketfamilien-Erkennung (P-2026-08-09-20) wurden
alle vier Skripte **gemeinsam in einem frischen Container** durchgespielt und
anschließend wiederholt: kein einziger fehlender Punkt im zweiten Lauf.

Was damit ausdrücklich **noch nicht** belegt ist, weil ein Container es nicht
zeigen kann: dass ein Bild erscheint, dass ein Leser Zeichen liefert und dass
ein gedrehter Touchscreen richtig trifft. Das ist der Gerätetest.

Bemerkenswert: Die ersten beiden Stufen sind der eigentliche Kern und lassen
sich **komplett ohne ein einziges Gerät** bauen und prüfen.

Stufe 1 und 2 lassen sich also absichern, bevor ein echtes Gerät angefasst
wird.

## 12. Der Gerätetest – Prüfprotokoll

Was ein Container prinzipiell nicht zeigen kann, braucht einen Bildschirm.
Ablauf: Debian in einer VM mit Grafik (`qemu`/`virsh` sind auf dem
Entwicklungsrechner vorhanden) oder echte Hardware, dann die vier Skripte der
Reihe nach, danach diese Punkte:

1. Kommt der Browser nach dem Einschalten im Vollbild hoch, ohne Adresszeile?
2. Startet er nach `pkill chromium` von selbst neu (`Restart=always`)?
3. Bleibt der Bildschirm nach zehn Minuten hell?
4. Ist der Mauszeiger weg, wenn eine Maus angeschlossen ist?
5. Greift der X11-Rückfall, wenn man `KIOSK_ANZEIGE="x11"` setzt?
6. Dreht sich mit `BILDSCHIRM_DREHUNG="rechts"` unter X11 **beides** – Bild und
   Berührung? (Nur Bild gedreht ist schlimmer als gar nicht: Es sieht richtig
   aus, aber der Finger trifft daneben.)
7. Liefert ein angeschlossener RFID-Leser Zeichen, und stimmt ein gescannter
   Barcode mit dem Etikett überein? Beides fragt `selbsttest.sh` ab.

Grundlage für die Punkte 1 bis 6 sind die Abschnitte 6 und 7, für Punkt 7
Abschnitt 8.

**Unabhängig davon offen:** Auf `pacman`, `dnf` und `zypper` ist keines der
vier Skripte gelaufen. Der Container deckt nur `apt` ab, und openSUSE bleibt
die unsicherste Familie (versionsgebundene Paketnamen, php-fpm auf TCP statt
Socket, `cage` je nach Version gar nicht vorhanden).
