# Spezifikation: Terminal-Installation per Skript

*Version:* v2 (2026-08-08)
*Status:* in Umsetzung – Stufe 1a und 1b fertig (P-2026-08-08-30, -31),
Stufe 1c als Naechstes (Endpunkt + Datenbankbenutzer)
*Grundlage:* `docs/master_prompt_zeiterfassung_v13.md`, Abschnitte 2.2 (Terminal),
6.2 (Terminals), 8 (Terminal-UI); `docs/rfid_reader_setup.md`;
`docs/terminal/rfid-ws_rollout.md`

---

## 1. Zielbild

Ein frisch installiertes Linux-Geraet wird mit **einem Befehl** zum
einsatzfertigen Hallenterminal:

```bash
sudo ./scripts/terminal/install_terminal.sh
```

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

### Warum ein eigener Benutzer je Terminal

- **Einzeln sperrbar:** Geraet verloren oder ausgetauscht → `DROP USER`, fertig.
  Kein Passwortwechsel auf allen anderen Terminals.
- **Eingeschraenkte Rechte:** Ein Terminal braucht nur Stempeln, Auftragszeiten
  und Urlaubsantraege. Es braucht **kein** `DELETE`, kein `DROP`, keinen Zugriff
  auf Stundenkonto und Lohnkorrekturen.
- **Nachvollziehbar:** In den Datenbank-Protokollen ist erkennbar, welches
  Geraet was getan hat.

### Rechte des Terminal-Benutzers (Vorschlag)

| Tabelle | Recht |
| --- | --- |
| `mitarbeiter` | SELECT (moeglichst ohne Spalte `passwort_hash` – MariaDB kann Rechte je Spalte) |
| `zeitbuchung` | SELECT, INSERT |
| `auftrag`, `auftrag_arbeitsschritt`, `auftragszeit` | SELECT, INSERT, UPDATE |
| `urlaubsantrag` | SELECT, INSERT |
| `maschine`, `terminal`, `config`, `pausenfenster`, `zeit_rundungsregel`, `feiertag`, `betriebsferien` | SELECT |
| `system_log` | INSERT |
| alles Uebrige, besonders `stundenkonto_*`, `recht`, `rolle` | **kein Zugriff** |

Kein `DELETE`, kein `DROP`, kein `ALTER` – nirgends.

### Was dafuer noetig ist, und was das kostet

Damit das Backend Benutzer anlegen kann, braucht **sein** Datenbankbenutzer das
Recht `CREATE USER` sowie `GRANT OPTION` auf das Schema `zeiterfassung`. Das ist
kein Nebeneffekt, sondern eine bewusste Abwaegung:

- **Vorteil:** Die Kopplung laeuft ohne Handarbeit, auch fuer zwanzig Geraete.
- **Nachteil:** Wer die Weboberflaeche uebernimmt, kann Datenbankbenutzer
  anlegen. Begrenzt wird das dadurch, dass `GRANT OPTION` nie mehr vergeben
  kann, als der Vergebende selbst hat – die Rechte des Backends sind also die
  Obergrenze.
- **Ausweichweg, falls das zu weit geht:** Das Backend legt den Benutzer nicht
  selbst an, sondern zeigt dem Administrator die fertige SQL-Anweisung zum
  einmaligen Ausfuehren. Gleiche Sicherheit fuer das Terminal, kein erhoehtes
  Recht fuer die Anwendung, dafuer ein manueller Schritt je Geraet.

Diese Entscheidung sollte **vor** der Umsetzung getroffen werden.

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
   MariaDB (nur fuer die lokale Ausweichdatenbank), Git, Grafikstack, Browser,
   Python (nur bei RC522).
3. Code aus Git holen, Webserver auf `public/` zeigen lassen.
4. **Keine** `config.local.php` schreiben – das Terminal startet bewusst
   unkonfiguriert und zeigt die Einrichtungsseite.
5. Tastaturlayout systemweit setzen (siehe 6.3).
6. Bei RC522: SPI aktivieren, Phase-2-Dienst einrichten, **Neustart**.

### Phase 2 – Peripherie und Kiosk
7. RFID einrichten (Abschnitt 6).
8. Touchscreen pruefen und drehen (Abschnitt 7).
9. Kiosk einrichten (Abschnitt 8) – der Browser landet auf der
   Einrichtungsseite, solange keine Konfiguration vorliegt.
10. Selbsttest (Abschnitt 9), Ergebnis auf den Bildschirm und ins Log.

### Phase 3 – Kopplung am Geraet (kein Skript)
11. Am Touchscreen Server-Adresse und Kopplungscode eingeben; das Terminal holt
    sich alles Weitere selbst (Abschnitt 2a).

## 6. Peripherie

### 6.1 RFID – USB-Leser (Keyboard-Wedge)
Braucht keine Treiber; der Leser tippt wie eine Tastatur. Das Skript setzt
`terminal.rfid_ws.enabled = false` und bietet einen Scan-Test an.

### 6.2 RFID – RC522 ueber SPI
SPI aktivieren (Boot-Konfiguration, danach Neustart), Python-Abhaengigkeiten
installieren, `docs/terminal/rfid_ws.py` und `rfid-ws.service` einrichten,
`terminal.rfid_ws.enabled = true` setzen. Die Anleitung dazu liegt bereits in
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

## 7. Kiosk

- Autologin fuer einen eigenen Benutzer `terminal` (nicht root).
- Browser im Vollbild auf `…/public/terminal.php`, ohne Bedienelemente.
- Bildschirmschoner und Energiesparen aus, Mauszeiger ausblenden.
- Neustart des Browsers, falls er abstuerzt.
- Wayland oder X11 je nachdem, was die Distribution mitbringt: bevorzugt ein
  schlanker Wayland-Kiosk (`cage`), sonst X11 mit minimalem Fenstermanager.

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
- Ein ausgemustertes Terminal muss im Backend abgemeldet werden, sonst bleibt
  sein Datenbankbenutzer gueltig.

## 11. Umsetzung in Stufen

1. **Kopplung im Backend** – Maske „Terminal anmelden“, Kopplungscode,
   Endpunkt, Anlage des Datenbankbenutzers. Ohne Hardware testbar.
2. **Einrichtungsseite im Terminal** – erscheint bei fehlender Konfiguration,
   nimmt Adresse und Code entgegen, schreibt `config.local.php`. Ohne Hardware
   testbar.
3. **Grundsystem-Skript** – Pakete, Code, Webserver. Im Container testbar.
4. **Kiosk** – Autologin, Browser im Vollbild. In einer VM testbar.
5. **Peripherie** – RFID, Touchscreen, Tastaturlayout. Braucht echte Hardware.
6. **Selbsttest** – rundet ab und macht das Ergebnis pruefbar.

Bemerkenswert: Die ersten beiden Stufen sind der eigentliche Kern und lassen
sich **komplett ohne ein einziges Geraet** bauen und pruefen.

Stufe 1 und 2 lassen sich also absichern, bevor ein echtes Geraet angefasst
wird.
