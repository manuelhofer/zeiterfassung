# Spezifikation: Terminal-Installation per Skript

*Version:* v2 (2026-08-08)
*Status:* in Umsetzung – Stand und Einschraenkungen je Stufe stehen im
**Stufenplan (Abschnitt 11)**; gebaut sind die Stufen 1 bis 4, als Naechstes
Stufe 5 (Peripherie)
*Grundlage:* `docs/fachregeln/terminal_und_offline.md`;
`docs/rfid_reader_setup.md`; `docs/terminal/rfid-ws_rollout.md`

---

## 1. Zielbild

Ein frisch installiertes Linux-Geraet wird mit **zwei Befehlen** zum
einsatzfertigen Hallenterminal:

```bash
sudo ./scripts/terminal/install_terminal.sh   # Grundsystem (Abschnitt 5a)
sudo ./scripts/terminal/install_kiosk.sh      # Kiosk        (Abschnitt 7)
```

Getrennt, weil ein Grundsystem sich im Container pruefen laesst und ein Kiosk
einen Bildschirm braucht. Wer nur den Kiosk neu aufsetzt, faehrt nicht die
ganze Installation noch einmal.

Danach startet das Geraet von selbst in die Terminal-Oberflaeche, der
RFID-Leser funktioniert, der Barcode-Scanner liefert saubere Codes, und der
Touchscreen ist bedienbar. Kein manuelles Nacharbeiten.

## 2. Aufteilung: Skript und Kopplung

Ein frisch aufgesetztes Geraet hat weder Webserver noch PHP – die
Grundinstallation kann deshalb nur ein Skript erledigen. Sobald die Anwendung
aber laeuft, kann sie ihre Konfiguration **selbst** holen. Genau dort wird
geteilt:

| Wer | Wofuer |
| --- | --- |
| **Skript** (einmal je Geraet) | Betriebssystem, Pakete, Code, Webserver, Kiosk, RFID, Touchscreen, Tastaturlayout |
| **Kopplung im Browser** | Server-Adresse, Anmeldung am Backend, Zugangsdaten, `config.local.php` schreiben |

Der entscheidende Gewinn: **Das Skript kennt keine Zugangsdaten.** Es fragt
nichts Fachliches ab, und dasselbe Abbild laesst sich auf zwanzig Geraete
spielen.

Das Terminal verhaelt sich dabei genauso wie das Backend beim ersten Start:
Fehlt die Konfiguration, erscheint statt der Terminal-Oberflaeche eine
**Einrichtungsseite** – dieselbe Mechanik wie die vorhandene Maske
„Erstinstallation“ (`views/login/initial_admin.php`).

## 2b. Die Einrichtungsseite (umgesetzt, P-2026-08-09-01)

`public/terminal.php` prueft vor allem Anderen, ob `config/config.local.php`
existiert. Fehlt sie, uebernimmt `TerminalEinrichtungController` und zeigt
`views/terminal/einrichtung.php` – unabhaengig davon, welche `?aktion=…`
aufgerufen wurde. Einzige Ausnahme ist `?aktion=health`: Eine Ueberwachung soll
auch ein frisches Geraet abfragen koennen.

**Woran „nicht eingerichtet“ erkannt wird – und woran ausdruecklich nicht:**
Nur an der **fehlenden Datei**, nicht an einer fehlgeschlagenen
Datenbankverbindung. Ein Terminal ohne Netz ist kein unkonfiguriertes Terminal:
Der Offline-Betrieb mit Queue ist eine gewollte Betriebsart
(`docs/fachregeln/terminal_und_offline.md`, Abschnitt 5).
Wuerde ein Netzausfall die Einrichtungsseite hervorholen, stuende die Halle bei
jeder Stoerung vor einer Maske, die nach einem Kopplungscode fragt – und die
Buchungen waeren weg.

**Bedienung:** Zwei Felder (Server-Adresse, Kopplungscode) mit eigener
Bildschirmtastatur, weil ein Kiosk keine Tastatur hat. Fuer den Code enthaelt
die Tastatur **nur** die Zeichen, die im Code vorkommen koennen (kein O/0, kein
I/1/L); Kleinschreibung und Bindestriche sind erlaubt und werden serverseitig
normalisiert.

**Adresse:** Es genuegt der Rechnername (`192.168.10.5`,
`server/zeiterfassung`). Fehlt das Schema, wird `http://` ergaenzt; probiert
werden `…/index.php` und `…/public/index.php`, weil der Webserver je nach
Installation auf `public/` oder auf das Projektverzeichnis zeigt. Ein
Fehlversuch auf dem falschen Pfad verbraucht den Kopplungscode nicht – der
Endpunkt laeuft dort gar nicht. **Weiterleitungen werden nicht verfolgt**,
sondern angezeigt: Eine Umleitung kann auf einen anderen Rechner zeigen, und
dorthin gehen Zugangsdaten.

**Geschrieben wird** `config/config.local.php` mit `installation_typ =
'terminal'`, den Zugangsdaten aus der Antwort und den Terminal-Einstellungen.
Erst vollstaendig daneben schreiben, gegenlesen, dann umbenennen: Eine halb
geschriebene Konfiguration waere schlimmer als gar keine – sie koennte das
Geraet dauerhaft lahmlegen, weil dann auch die Einrichtungsseite nicht mehr
erscheint.

**Wenn das Schreiben scheitert** (Verzeichnis nicht beschreibbar), zeigt die
Seite den vollstaendigen Dateiinhalt zum Uebernehmen an. Grund: Der
Kopplungscode ist zu diesem Zeitpunkt verbraucht; ein blosses „Fehler“ wuerde
den Monteur zwingen, im Backend einen neuen Code zu holen. Fehlende
Schreibrechte werden ausserdem schon **vor** dem Koppeln als Hinweis angezeigt.

**Eine vorhandene Konfiguration wird nie ueberschrieben.** Sonst liesse sich ein
laufendes Terminal ueber diese Seite auf einen fremden Server umbiegen. Ein
Geraet neu koppeln heisst deshalb: `config.local.php` loeschen.

**Die Warnung aus der Antwort** (Kopplung lief ueber HTTP) wird nach dem
Speichern gross angezeigt. Sonst merkt niemand, dass die Zugangsdaten im Netz
mitlesbar waren.

### Was das Skript hinterlaesst: `config/geraet.local.php`

Zwei Dinge kann die Kopplung nicht liefern, weil sie der **Maschine** gehoeren
und nicht dem Backend: die Zugangsdaten der lokalen Ausweichdatenbank und die
Einstellung der RFID-Bridge. Dafuer gibt es eine optionale Datei, die das
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

Uebernommen werden **nur** diese beiden Bloecke. Die Zugangsdaten zur
Hauptdatenbank kommen ausschliesslich aus der Kopplung – sonst waere die
Trennung zwischen Skript und Kopplung wieder aufgeweicht.

Fehlt die Datei, koppelt das Terminal trotzdem; `offline_db.enabled` steht dann
auf `false` und die Seite sagt ausdruecklich, dass dieses Geraet bei einem
Netzausfall **nichts zwischenspeichern** kann. Das ist die ehrlichere Variante,
als eine Ausweichdatenbank zu behaupten, die es nicht gibt.

## 2a. Kopplung (Handshake)

### Ablauf

1. **Backend**, Menue Verwaltung → *Terminal anmelden*: Name und Standort
   eingeben. Das Backend legt den Eintrag in `terminal` an und zeigt einen
   **Kopplungscode** – kurz genug zum Abtippen, zeitlich begrenzt (Vorschlag:
   30 Minuten) und **einmalig** gueltig.
2. **Terminal** startet, findet keine Konfiguration → Einrichtungsseite auf dem
   Touchscreen: Server-Adresse und Kopplungscode.
3. Das Terminal ruft den Kopplungs-Endpunkt des Backends auf und schickt Code
   plus eigene Kennung (Hostname, MAC-Adresse).
4. **Das Backend legt einen eigenen Datenbankbenutzer fuer genau dieses
   Terminal an** (Name z. B. `term_halle_nord_1`, zufaelliges Passwort) und
   antwortet mit Zugangsdaten, Terminal-ID und Einstellungen.
5. Das Terminal schreibt `config.local.php`, legt seine lokale
   Ausweichdatenbank an und startet in den Kioskmodus.

### Der Endpunkt (umgesetzt, P-2026-08-08-36)

`POST …/public/index.php?seite=terminal_kopplung`, Antwort als JSON, ohne
Anmeldung erreichbar.

| Feld | Pflicht | Inhalt |
| --- | --- | --- |
| `code` | ja | Kopplungscode aus dem Backend (Bindestriche und Kleinschreibung sind egal) |
| `host` | nein | Kennung des Geraets, z. B. Hostname und MAC-Adresse – nur zur Nachvollziehbarkeit |

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
(400 ohne Code, 403 ungueltiger Code oder stillgelegtes Terminal, 429 zu viele
Fehlversuche, 500 Serverproblem).

Festgelegtes Verhalten:

- **Ein Fehlschlag sagt nicht, warum.** Ob der Code unbekannt, abgelaufen oder
  bereits verbraucht war, steht nur im Serverprotokoll – alles andere hilft
  beim Durchprobieren.
- **Fehlversuche werden gebremst** (je Absender-IP, Standard 10 Versuche in 10
  Minuten). Waehrend der Sperre wird auch ein gueltiger Code abgewiesen, ohne
  ihn zu verbrauchen.
- **Der Code ist nach dem Aufruf verbraucht**, auch wenn es danach schiefgeht.
  Dann muss im Backend ein neuer erzeugt werden; die Fehlermeldung sagt das.
- **Kein halber Zustand:** Laesst sich die Kopplung nicht speichern, wird der
  eben angelegte Datenbankbenutzer wieder entfernt. Ein Zugang, von dem das
  Backend nichts weiss, waere spaeter nicht mehr zuzuordnen und bliebe fuer
  immer gueltig.
- **Ein stillgelegtes Terminal koppelt nicht** (`aktiv = 0`).
- **`db.host` ist die Adresse aus Sicht des Terminals**, nicht die des Backends:
  `config: terminal_db_host_extern`, sonst der konfigurierte Datenbank-Host,
  und wenn der lokal ist, die Adresse, unter der das Terminal das Backend
  erreicht hat. Sonst bekaeme das Terminal `localhost` und spraeche sich selbst
  an.
- **Ohne HTTPS** enthaelt die Antwort ein Feld `warnung`, damit die
  Einrichtungsseite es anzeigen kann. Die Zugangsdaten waren dann im Netz
  mitlesbar.

### Entkoppeln (umgesetzt, P-2026-08-09-13)

Die Gegenrichtung zur Kopplung: In der Terminalverwaltung zeigt die Spalte
**Kopplung** je Geraet den Datenbankbenutzer und seit wann er gilt, daneben
steht **Entkoppeln** (POST, CSRF, Rueckfrage).

Der Knopf loescht den Datenbankbenutzer, leert `db_benutzer`,
`db_benutzer_host`, `gekoppelt_am` und `gekoppelt_host` am Terminal-Datensatz
und entwertet offene Kopplungscodes. Danach braucht das Geraet einen neuen
Code.

Warum es das geben muss: **`aktiv = 0` genuegt nicht.** Das verhindert nur eine
neue Kopplung – der bestehende Datenbankbenutzer bleibt gueltig. Wer ein
ausgemustertes Geraet mitnimmt, liest die Zugangsdaten aus `config.local.php`
und kommt weiter an alles, was dieses Terminal durfte.

Festgelegtes Verhalten:

- **Erst der Datenbankbenutzer, dann der Vermerk.** Scheitert das Loeschen,
  bleibt der Vermerk stehen, das Geraet gilt weiter als gekoppelt und der
  Zugang laesst sich erneut entfernen. Andersherum bliebe ein gueltiger
  Benutzer uebrig, von dem niemand mehr weiss, wozu er gehoert.
- **Offene Codes werden zuerst entwertet**, auch wenn gar kein Zugang besteht.
  Ein noch gueltiger Code waere genau der Weg, sich das eben Abgemeldete
  zurueckzuholen.
- **Ein zweiter Aufruf ist harmlos** und meldet „war nicht gekoppelt“.

### Warum ein eigener Benutzer je Terminal

- **Einzeln sperrbar:** Geraet verloren oder ausgetauscht → `DROP USER`, fertig.
  Kein Passwortwechsel auf allen anderen Terminals.
- **Eingeschraenkte Rechte:** Ein Terminal braucht nur Stempeln, Auftragszeiten
  und Urlaubsantraege. Es braucht **kein** `DELETE`, kein `DROP`, keinen Zugriff
  auf Stundenkonto und Lohnkorrekturen.
- **Nachvollziehbar:** In den Datenbank-Protokollen ist erkennbar, welches
  Geraet was getan hat.

### Rechte des Terminal-Benutzers (umgesetzt und geprueft, P-2026-08-08-35)

Die Liste steht in `services/TerminalDbBenutzerService.php` und ist **aus dem
Code hergeleitet** – alles, was `public/terminal.php` und die von dort genutzten
Dienste anfassen. Sie weicht an mehreren Stellen vom urspruenglichen Vorschlag
ab; das war kein Aufweichen, sondern das Ergebnis des Nachsehens.

| Tabelle | Recht |
| --- | --- |
| `zeitbuchung` | SELECT, INSERT |
| `auftrag`, `auftrag_arbeitsschritt`, `auftragszeit` | SELECT, INSERT, UPDATE |
| `urlaubsantrag` | SELECT, INSERT, UPDATE |
| `feiertag` | SELECT, INSERT |
| `system_log` | SELECT, INSERT |
| `db_injektionsqueue` | SELECT, INSERT, UPDATE |
| `mitarbeiter` | SELECT + UPDATE **nur** auf Spalte `rfid_code` |
| Rollen/Rechte: `rolle`, `recht`, `rolle_hat_recht`, `mitarbeiter_hat_rolle`, `mitarbeiter_hat_rolle_scope`, `mitarbeiter_hat_recht`, `mitarbeiter_hat_abteilung`, `mitarbeiter_genehmiger` | SELECT |
| Stammdaten: `maschine`, `terminal`, `config`, `abteilung`, `arbeitsschritt_katalog` | SELECT |
| Auswertung: `zeit_rundungsregel`, `pausenfenster`, `pausenentscheidung`, `betriebsferien`, `krankzeitraum`, `kurzarbeit_plan`, `urlaub_kontingent_jahr`, `tageswerte_mitarbeiter`, `monatswerte_mitarbeiter`, `stundenkonto_korrektur` | SELECT |
| alles Uebrige, insbesondere `terminal_kopplung` und `stundenkonto_batch` | **kein Zugriff** |

Kein `DELETE`, kein `DROP`, kein `ALTER`, kein `CREATE` – nirgends.

**Warum es vom Vorschlag abweicht:**

- **Rollen und Rechte muessen lesbar sein.** Das Terminal blendet Knoepfe je
  nach Berechtigung ein (z. B. „Urlaubsantraege“ fuer Genehmiger). Ohne
  Lesezugriff auf die Rechtetabellen waere jeder Mitarbeiter am Terminal
  rechtlos.
- **`stundenkonto_korrektur` muss lesbar sein.** Das Terminal zeigt seit
  P-2026-01-17-19 Gut- und Minusstunden an. Schreiben darf es dort nichts –
  Buchungen aufs Stundenkonto bleiben Sache des Backends. `stundenkonto_batch`
  bleibt ganz gesperrt.
- **`urlaubsantrag` braucht UPDATE.** Genehmiger koennen laut
  `docs/fachregeln/urlaub_abwesenheit_feiertage.md` auch am Terminal
  entscheiden.
- **`feiertag` braucht INSERT.** Der `UrlaubService` generiert die Feiertage
  eines Jahres bei Bedarf nach. Ohne dieses Recht rechnet ein Terminal im
  Januar ohne die Feiertage des neuen Jahres – und das faellt niemandem auf.
  Dieses stille Falschrechnen waere gefaehrlicher als das Recht selbst.
- **`db_injektionsqueue` als Rueckfallebene.** Normalerweise liegt die
  Offline-Queue in der lokalen Ausweichdatenbank des Terminals; fehlt die,
  greift der `OfflineQueueManager` auf die Hauptdatenbank zurueck. Kein
  `DELETE`: haengengebliebene Eintraege raeumt ein Admin im Backend weg.

**Offen geblieben – `passwort_hash`:** Der Vorschlag sah vor, die Spalte per
spaltenweisem Recht auszunehmen. Das geht nicht, ohne vorher Code zu aendern:
Spaltenrechte in MySQL/MariaDB verbieten `SELECT *`, und `MitarbeiterModel`
arbeitet genau so – ueber den `ReportService` auch im Terminalpfad. Das Terminal
kann die Hashes also lesen. Wer ein Geraet stiehlt, bekommt damit
Passwort-Hashes zum Offline-Knacken. Das ist der letzte verbliebene Punkt aus
Abschnitt 10 und als Aufgabe festgehalten; die Loesung ist entweder eine
Sicht ohne diese Spalte oder feste Spaltenlisten statt `SELECT *`.

### Von welchem Rechner darf sich das Terminal verbinden

Der Benutzer wird standardmaessig fuer `%` angelegt (beliebiger Rechner),
einstellbar ueber den Konfigurationsschluessel `terminal_db_host_muster`
(z. B. `192.168.10.%`). Grund fuer den weiten Standard: Terminals bekommen ihre
Adresse per DHCP – eine feste Bindung kappt beim naechsten Neustart still den
Zugang.

**Stolperstein:** Laeuft ein Terminal ausnahmsweise auf demselben Rechner wie
die Datenbank, kann ein `%`-Konto von **anonymen Konten** (`''@'localhost'`)
verdeckt werden – MariaDB waehlt den spezifischeren Host-Eintrag. In diesem Fall
entweder die anonymen Konten entfernen (`mariadb-secure-installation`) oder
`terminal_db_host_muster` auf `localhost` setzen.

### Was dafuer noetig ist, und was das kostet

**Entschieden:** Das Backend legt die Benutzer selbst an (automatisch).

Damit das Backend Benutzer anlegen kann, braucht **sein** Datenbankbenutzer das
Recht `CREATE USER` sowie `GRANT OPTION` auf das Schema `zeiterfassung`. Die
dafuer noetigen Anweisungen stehen in
`sql/06_migration_terminal_db_benutzer.sql` und muessen von einem Administrator
einmal ausgefuehrt werden – die Anwendung kann sich diese Rechte nicht selbst
geben. Fehlen sie, laeuft alles Uebrige normal weiter; nur die Kopplung bricht
mit einer verstaendlichen Meldung ab. Das ist kein Nebeneffekt, sondern eine
bewusste Abwaegung:

- **Vorteil:** Die Kopplung laeuft ohne Handarbeit, auch fuer zwanzig Geraete.
- **Nachteil:** Wer die Weboberflaeche uebernimmt, kann Datenbankbenutzer
  anlegen. Begrenzt wird das dadurch, dass `GRANT OPTION` nie mehr vergeben
  kann, als der Vergebende selbst hat – die Rechte des Backends sind also die
  Obergrenze.
- **Ausweichweg, falls das zu weit geht:** Das Backend legt den Benutzer nicht
  selbst an, sondern zeigt dem Administrator die fertige SQL-Anweisung zum
  einmaligen Ausfuehren. Gleiche Sicherheit fuer das Terminal, kein erhoehtes
  Recht fuer die Anwendung, dafuer ein manueller Schritt je Geraet.

### Sicherheitsanforderungen an die Kopplung

- Kopplungscode: einmalig, zeitlich begrenzt, nach wenigen Fehlversuchen
  gesperrt (sonst laesst er sich durchprobieren).
- **Verschluesselte Verbindung dringend empfohlen:** Bei der Kopplung gehen
  Zugangsdaten ueber das Netz. Ohne HTTPS liest sie jeder mit, der im
  Hallennetz mithoert. Ist HTTPS nicht moeglich, sollte die Kopplung wenigstens
  nur in einem abgesicherten Netzsegment erfolgen.
- Kopplung protokollieren (`system_log`): wer, wann, welches Geraet.
- Erneute Kopplung eines vorhandenen Terminals ersetzt den alten
  Datenbankbenutzer, statt einen zweiten anzulegen.

## 3. Randbedingungen

- **Distributionsunabhaengig.** Nicht auf eine Distribution festgelegt, sondern
  eine kleine Erkennungsschicht ueber `/etc/os-release` fuer die vier
  Paketmanager-Familien: `apt` (Debian/Raspberry Pi OS/Ubuntu), `pacman`
  (Arch/CachyOS), `dnf` (Fedora/RHEL), `zypper` (openSUSE). Paketnamen
  unterscheiden sich je Familie und werden in einer Zuordnungstabelle gepflegt.
- **Datenbankzugriff:** Das Terminal verbindet sich **direkt ueber das Netz**
  auf die MariaDB des Hauptsystems (so ist `config.local.php` heute gebaut:
  `db` = Hauptdatenbank, `offline_db` = lokale Ausweichdatenbank).
- **Beide RFID-Varianten** muessen unterstuetzt werden – USB-Keyboard-Wedge und
  RC522 ueber SPI mit WebSocket-Bridge.
- **Idempotent:** Mehrfaches Ausfuehren ist unschaedlich und repariert einen
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
| `TASTATURLAYOUT` | `de` | entscheidend fuer Scanner, siehe 6.3 |
| `BILDSCHIRM_DREHUNG` | `normal` \| `left` \| `right` \| `inverted` | Touchscreen |
| `GIT_REPO` / `GIT_BRANCH` | `https://github.com/…` / `main` | Codequelle |

Alles per `terminal.conf` neben dem Skript vorgebbar, sonst interaktive
Abfrage. Damit ist dasselbe Abbild auf allen Geraeten verwendbar; unterscheiden
tun sie sich erst durch die Kopplung.

## 5. Ablauf in Phasen

Drei Phasen. Die ersten beiden erledigt das Skript; sie sind getrennt, weil das
Aktivieren von SPI einen **Neustart** erfordert – Phase 2 laeuft danach
automatisch weiter (systemd-Einmaldienst, der sich anschliessend selbst
deaktiviert). Phase 3 ist die Kopplung am Geraet und braucht kein Skript.

### Phase 1 – Grundsystem
1. Distribution und Paketmanager erkennen, Vorbedingungen pruefen (root,
   Netzwerk).
2. Pakete installieren: Webserver, PHP mit `pdo_mysql`/`mbstring`/`gd`,
   MariaDB (nur fuer die lokale Ausweichdatenbank), Git. **Grafikstack und
   Browser** gehoeren zum Kiosk und werden dort installiert (Stufe 4) – sie
   ohne den Kiosk mitzunehmen brachte nur Wartezeit und liess sich im Container
   nicht pruefen. Python nur bei RC522 (Stufe 5).
3. Code aus Git holen, Webserver auf `public/` zeigen lassen.
4. **Keine** `config.local.php` schreiben – das Terminal startet bewusst
   unkonfiguriert und zeigt die Einrichtungsseite. Stattdessen: lokale
   Ausweichdatenbank anlegen und ihre Zugangsdaten nach
   `config/geraet.local.php` schreiben (Abschnitt 2b) – das ist alles, was das
   Skript an Konfiguration hinterlaesst.
5. Tastaturlayout systemweit setzen (siehe 6.3).
6. Bei RC522: SPI aktivieren, Phase-2-Dienst einrichten, **Neustart**.

### Phase 2 – Peripherie und Kiosk
7. RFID einrichten (Abschnitt 6).
8. Touchscreen pruefen und drehen (Abschnitt 6.4).
9. Kiosk einrichten (Abschnitt 7) – der Browser landet auf der
   Einrichtungsseite, solange keine Konfiguration vorliegt.
10. Selbsttest (Abschnitt 8), Ergebnis auf den Bildschirm und ins Log.

### Phase 3 – Kopplung am Geraet (kein Skript)
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
einer Eingabeaufforderung haengenbleiben.

**Alles Distributionsabhaengige steht in einer einzigen Tabelle** im Skript:
Paketliste, Dienstname, Webserver-Benutzer und Ablageort der
Webserver-Konfiguration je Familie (`apt`, `pacman`, `dnf`, `zypper`). Verstreute
Sonderfaelle waren der uebliche Grund, warum solche Skripte nach der zweiten
Distribution unwartbar werden.

**PHP haengt ueberall gleich am Webserver:** `php-fpm` plus `mod_proxy_fcgi`,
statt je Familie ein anderes PHP-Modul. Unterschiedlich ist nur der Socketpfad –
und den sucht das Skript (`/run/php-fpm/*.sock`, `/run/php/*.sock`, …), mit
`127.0.0.1:9000` als Rueckfall. Damit ist die erzeugte Apache-Konfiguration fuer
alle vier Familien dieselbe Datei.

**Das Passwort der Ausweichdatenbank wird bei einem zweiten Lauf
wiederverwendet**, nicht erneuert. Ein bereits gekoppeltes Terminal traegt es in
seiner `config.local.php`; ein frisches Passwort wuerde ihm stillschweigend die
Offline-Queue kappen – der Ausfall faellt dann erst beim naechsten Netzausfall
auf, also genau dann, wenn er am meisten schadet. Gesucht wird deshalb **zuerst
in `config.local.php`**, erst danach in `geraet.local.php`: Die erste ist die
Datei, aus der ein gekoppeltes Terminal die Queue-Zugangsdaten wirklich liest.
Die zweite kann fehlen oder – nach einem Lauf mit nicht ansprechbarer Datenbank
– ein leeres Passwort tragen (P-2026-08-09-05).

**Der Code gehoert root**, der Webserver-Benutzer darf ihn nur lesen.
Schreibrechte bekommt er ausschliesslich fuer `config/` – dort legt die Kopplung
`config.local.php` an – und fuer `public/uploads/`. Unter SELinux (Fedora/RHEL)
setzt das Skript zusaetzlich die Kontexte und den Schalter
`httpd_can_network_connect_db`; ohne ihn erreicht das Terminal die
Hauptdatenbank des Backends nicht, und zwar ohne erkennbare Ursache.

**Das Tastaturlayout wird an drei Stellen gesetzt** (X11, Konsole, bei Debian
zusaetzlich `/etc/default/keyboard`), weil je nach Distribution eine andere
davon greift. Dazu die Zeitzone: Die Uhr im Terminal-Header laeuft nach der
Systemzeit, ein Geraet in UTC zeigt der Halle stundenversetzte Buchungszeiten.

**Am Ende steht eine Liste mit OK/FEHLT** (Webserver, `pdo_mysql`,
Ausweichdatenbank, `geraet.local.php`, Schreibrecht auf `config/`, HTTP-Antwort
von `terminal.php`) sowie alle Warnungen des Laufs gesammelt. Das ist die kleine
Fassung von Abschnitt 8; der vollstaendige Selbsttest mit Scan-Proben kommt mit
Stufe 6.

**Bewusst nicht in diesem Skript:** Kiosk (Stufe 4, eigenes Skript
`install_kiosk.sh`, Abschnitt 7), Peripherie (Stufe 5), Selbsttest mit Hardware
(Stufe 6). Ein Lauf ohne systemd (Container) bricht nicht ab, sondern warnt –
sonst waere die Stufe nicht im Container pruefbar.

## 6. Peripherie

### 6.1 RFID – USB-Leser (Keyboard-Wedge)
Braucht keine Treiber; der Leser tippt wie eine Tastatur. Das Skript setzt
`rfid_ws.enabled = false` in `config/geraet.local.php` (Abschnitt 2b) und bietet
einen Scan-Test an.

### 6.2 RFID – RC522 ueber SPI
SPI aktivieren (Boot-Konfiguration, danach Neustart), Python-Abhaengigkeiten
installieren, `docs/terminal/rfid_ws.py` und `rfid-ws.service` einrichten,
`rfid_ws.enabled = true` in `config/geraet.local.php` setzen. Die Anleitung dazu liegt bereits in
`docs/terminal/rfid-ws_rollout.md` – das Skript automatisiert genau diese
Schritte.

### 6.3 Barcode-Scanner – der unterschaetzte Teil
Der Scanner braucht **keine Treiber**, er tippt wie eine Tastatur. Genau darin
liegt die Falle: Steht das System auf US-Layout und der Code enthaelt
Sonderzeichen oder `y`/`z`, kommt im Eingabefeld etwas anderes an, als auf dem
Etikett steht. Das Terminal bucht dann klaglos einen falschen Code.

Deshalb ist das Setzen des Tastaturlayouts **Pflichtschritt**, nicht Kosmetik –
und der Selbsttest fordert ausdruecklich zum Scannen eines bekannten Codes auf
und vergleicht das Ergebnis.

### 6.4 Touchscreen
Vorhandensein ueber `libinput list-devices` erkennen. Drehung und
Zuordnung zum richtigen Bildschirm sind geraeteabhaengig und werden aus
`BILDSCHIRM_DREHUNG` gesetzt; automatisch erraten laesst sich das nicht
zuverlaessig.

## 7. Kiosk (umgesetzt, P-2026-08-09-09)

Anforderung:

- Autologin fuer einen eigenen Benutzer `terminal` (nicht root).
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

**Kein Autologin ueber getty, sondern ein Systemdienst.** Der uebliche Weg
(`agetty --autologin` und ein Aufruf im Anmeldeprofil) haette den geforderten
Neustart nach einem Absturz in einer Schleife in `~/.bash_profile` nachbauen
muessen. Der Dienst bekommt ihn mit `Restart=always` geschenkt und laesst sich
ausserdem gezielt anhalten, wenn jemand am Geraet arbeiten will.
`PAMName=login` erzeugt dabei eine echte Anmeldesitzung – ohne die gibt es
keinen Seat, und weder `cage` noch Xorg bekommen Bildschirm und
Eingabegeraete. `Conflicts=getty@tty1.service` verhindert, dass sich
Anmeldeaufforderung und Kiosk um dieselbe Konsole streiten.

**Die Meldungen des Browsers stehen nicht unter der Einheit.** Wegen
`PAMName=login` laufen `cage` und Browser in einer eigenen Sitzung; bei
`journalctl -u zeiterfassung-kiosk` erscheinen nur Start und Stopp des
Dienstes. Der Weg zu den Fehlern des Browsers ist
`journalctl -t zeiterfassung-kiosk`. Das Skript sagt das am Ende ausdruecklich
– es einmal zu wissen erspart die Suche nach einem Fehler, der scheinbar keine
Spur hinterlaesst.

**Der Anzeigeweg entscheidet sich am Geraet, nicht in der Tabelle:** Zuerst
wird `cage` installiert; liegt danach kein `cage` vor (auf aelteren openSUSE
gibt es das Paket nicht), kommen Xorg, `openbox` und `unclutter` dazu.
`KIOSK_ANZEIGE` in der Antwortdatei erzwingt einen der beiden Wege. Unter X11
ruft sich das Startskript ueber `xinit` selbst noch einmal auf – so bleibt
alles in **einer** Datei, statt eine zweite `.xinitrc` zu pflegen.

**Bildschirmschoner und Mauszeiger:** Unter X11 uebernehmen das `xset` und
`unclutter`. Unter Wayland gibt es beides nicht – `cage` dunkelt von sich aus
nicht ab, und einen Mauszeiger zeigt es nur, wenn tatsaechlich eine Maus
angeschlossen ist. Zusaetzlich wird die Abdunkelung der Textkonsole
abgeschaltet (`setterm --blank 0`), die sonst unter `cage` durchschlaegt.

**Der Absturzvermerk von Chromium wird vor jedem Start zurueckgesetzt.** Sonst
erscheint nach einem Absturz eine Leiste „Wiederherstellen“, die auf einem
Geraet ohne Tastatur niemand wegbekommt. Dazu Schalter gegen Zoom durch zwei
Finger und gegen „Zurueck“ per Wischgeste – beides loest am Touchscreen sonst
laufend versehentliche Navigation aus.

**Ein vorhandener Anmeldebildschirm wird abgeschaltet** (`display-manager`
deaktiviert, Startziel `multi-user.target`), weil er den Kiosk sonst verdeckt.
Wer das nicht will, setzt `KIOSK_ANMELDESCHIRM="belassen"` – gedacht fuer den
Fall, dass das Skript versehentlich auf einem Arbeitsplatzrechner laeuft.

**Der Kioskbenutzer kommt nicht an die Zugangsdaten.** `config/` gehoert seit
Stufe 3 `root` und der Webserver-Gruppe (2770); der Benutzer `terminal` ist
nicht darin. Die Ergebnisliste prueft das ausdruecklich mit – ein
Vollbildbrowser mit Netzzugang ist der Teil des Geraets, der am ehesten
uebernommen wird.

## 8. Selbsttest zum Abschluss

1. Webserver liefert die Terminalseite aus (HTTP 200).
2. Hauptdatenbank erreichbar, Anmeldung erfolgreich.
3. Lokale Ausweichdatenbank vorhanden, Tabelle `db_injektionsqueue` da.
4. `?aktion=health` des Terminals antwortet.
5. Bei RC522: Dienst laeuft, Port erreichbar.
6. Interaktiv: einmal RFID-Chip scannen, einmal Barcode scannen – das Skript
   zeigt an, was tatsaechlich angekommen ist.

Ergebnis als Liste mit OK/FEHLT, damit man vor dem Verlassen des Geraets weiss,
ob es einsatzbereit ist.

## 9. Was sich bewusst **nicht** vollautomatisch loesen laesst

Ehrlich vorab, damit niemand es spaeter als Fehler meldet:

- **USB-RFID-Leser sind von einer Tastatur nicht unterscheidbar.** Ob ein Leser
  angeschlossen ist, kann das Skript nicht wissen – nur der Scan-Test zeigt es.
- **Touchscreen-Drehung** ist geraeteabhaengig und wird abgefragt.
- **SPI braucht einen Neustart.** Daher die zwei Phasen.
- **Paketnamen** unterscheiden sich je Distribution; die Zuordnungstabelle deckt
  die vier grossen Familien ab. Exoten muessen von Hand nacharbeiten.

## 10. Sicherheit – geloest durch die Kopplung

In der ersten Fassung dieser Spezifikation trug das Terminal die Zugangsdaten
zur Hauptdatenbank auf dem Geraet, und zwar dieselben wie alle anderen. Wer
physisch an ein Hallenterminal kam, kam an die gesamte Datenbank samt aller
Personendaten.

Die Kopplung loest das: Jedes Terminal bekommt **einen eigenen Benutzer mit
eingeschraenkten Rechten**, einzeln sperrbar. Auf dem Geraet liegt damit nur
noch, was dieses eine Terminal ohnehin darf.

Was weiterhin gilt:

- Die Zugangsdaten liegen trotzdem lesbar auf dem Geraet – der Schaden ist
  begrenzt, aber nicht null. Physischer Schutz der Geraete bleibt sinnvoll.
- Bei der Kopplung selbst gehen Zugangsdaten ueber das Netz (siehe 2a).
- Ein ausgemustertes Terminal muss im Backend **entkoppelt** werden (Knopf in
  der Terminalverwaltung, siehe 2a), sonst bleibt sein Datenbankbenutzer
  gueltig. Nur stilllegen (`aktiv = 0`) reicht dafuer nicht.

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
   Abschnitt 5a. Am 09.08.2026 vollstaendig auf Debian 12 im Container gelaufen
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
   Zugriff auf das Grafikgeraet und bricht dort ab, Xorg startet und findet
   keinen Treiber. Beides ist der erwartete Abbruch, aber kein Beleg, dass der
   Kiosk auf einem Geraet erscheint. Das zeigt erst eine VM mit Grafik oder
   echte Hardware. Wie bei Stufe 3 gilt: nur `apt` geprueft.
5. **Peripherie** – RFID, Touchscreen, Tastaturlayout. Braucht echte Hardware.
   **Als Naechstes.**
6. **Selbsttest** – rundet ab und macht das Ergebnis pruefbar.

**Wo wir stehen** (Stand 09.08.2026, aus der Liste oben ablesbar, damit die
Zahl nicht driftet): **vier von sechs Stufen gebaut.** Stufe 1 und 2 sind
funktional geschlossen und gegen die Datenbank durchgespielt; Stufe 3 und 4
sind gebaut und im Container geprueft, aber nur auf **einer von vier
Paketfamilien** (`apt`) und ohne dass je ein Bild zu sehen war. Stufe 5 und 6
existieren noch nicht.

Bemerkenswert: Die ersten beiden Stufen sind der eigentliche Kern und lassen
sich **komplett ohne ein einziges Geraet** bauen und pruefen.

Stufe 1 und 2 lassen sich also absichern, bevor ein echtes Geraet angefasst
wird.
