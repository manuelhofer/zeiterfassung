# Spezifikation: Terminal-Installation per Skript

*Version:* v1 (2026-08-08)
*Status:* Entwurf, noch nicht umgesetzt
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

## 2. Warum ein Skript und keine Weboberflaeche

Ein frisch aufgesetztes Geraet hat weder Webserver noch PHP – es gibt nichts,
was eine Setup-Seite ausliefern koennte. Das laesst sich nur mit einem Skript
loesen, das auf dem Geraet selbst laeuft.

**Nach** der Installation uebernimmt wieder das Backend: Die Tabelle `terminal`
existiert bereits (Name, Standort, Modus, Auto-Logout, Offline-Rechte). Das
Geraet wird dort eingetragen und bezieht sein Verhalten von dort – das Skript
richtet nur die Maschine ein, nicht die Fachlogik.

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

Eine Datei `terminal.conf` neben dem Skript, sonst interaktive Abfrage:

| Wert | Beispiel | Zweck |
| --- | --- | --- |
| `BACKEND_URL` | `http://server.local/zeiterfassung` | nur fuer den Selbsttest |
| `DB_HOST` | `192.168.178.10` | Hauptdatenbank |
| `DB_NAME` / `DB_USER` / `DB_PASS` | `zeiterfassung` | Zugang zur Hauptdatenbank |
| `TERMINAL_NAME` | `Halle-Nord-1` | Eintrag in der Tabelle `terminal` |
| `RFID_VARIANTE` | `usb` \| `rc522` \| `frage` | siehe Abschnitt 6 |
| `TASTATURLAYOUT` | `de` | entscheidend fuer Scanner, siehe 6.3 |
| `BILDSCHIRM_DREHUNG` | `normal` \| `left` \| `right` \| `inverted` | Touchscreen |
| `GIT_REPO` / `GIT_BRANCH` | `https://github.com/…` / `main` | Codequelle |

## 5. Ablauf in Phasen

Zwei Phasen, weil das Aktivieren von SPI einen **Neustart** erfordert. Phase 2
laeuft nach dem Reboot automatisch weiter (systemd-Einmaldienst, der sich
danach selbst deaktiviert).

### Phase 1 – Grundsystem
1. Distribution und Paketmanager erkennen, Vorbedingungen pruefen (root,
   Netzwerk, Datenbank erreichbar).
2. Pakete installieren: Webserver, PHP mit `pdo_mysql`/`mbstring`/`gd`,
   MariaDB (nur fuer die lokale Ausweichdatenbank), Git, Grafikstack, Browser,
   Python (nur bei RC522).
3. Code aus Git holen, Webserver auf `public/` zeigen lassen.
4. `config/config.local.php` schreiben: `installation_typ = terminal`,
   Hauptdatenbank, lokale Ausweichdatenbank, RFID-Einstellungen.
5. Lokale Ausweichdatenbank anlegen und `sql/offline_db_schema.sql` einspielen.
6. Tastaturlayout systemweit setzen (siehe 6.3).
7. Bei RC522: SPI aktivieren, Phase-2-Dienst einrichten, **Neustart**.

### Phase 2 – Peripherie und Kiosk
8. RFID einrichten (Abschnitt 6).
9. Touchscreen pruefen und drehen (Abschnitt 7).
10. Kiosk einrichten (Abschnitt 8).
11. Selbsttest (Abschnitt 9), Ergebnis auf den Bildschirm und ins Log.

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

## 10. Sicherheitshinweis (wichtig)

Das Terminal traegt die Zugangsdaten zur **Hauptdatenbank** in einer Datei auf
dem Geraet. Wer physischen Zugriff auf ein Terminal in der Halle hat, kommt
damit an die gesamte Datenbank – einschliesslich aller Personendaten.

Empfehlung: **einen eigenen Datenbankbenutzer fuer Terminals** anlegen, der nur
das darf, was das Terminal wirklich braucht (Lesen der Stammdaten, Schreiben
von Buchungen), aber kein `DROP`, kein Zugriff auf Lohn-/Personaldaten. Das ist
kein Teil dieser Spezifikation, sollte aber vor dem ersten Rollout entschieden
werden.

## 11. Umsetzung in Stufen

1. **Grundsystem** – Pakete, Code, Konfiguration, Ausweichdatenbank.
   Vollstaendig in einem Container testbar, ohne Hardware.
2. **Kiosk** – Autologin, Browser im Vollbild. In einer VM testbar.
3. **Peripherie** – RFID, Touchscreen, Tastaturlayout. Braucht echte Hardware.
4. **Selbsttest** – rundet ab und macht das Ergebnis pruefbar.

Stufe 1 und 2 lassen sich also absichern, bevor ein echtes Geraet angefasst
wird.
