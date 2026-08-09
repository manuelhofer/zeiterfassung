# RFID-Reader einrichten

> **Auf einem Hallenterminal macht das seit P-2026-08-09-17 ein Skript:**
> `scripts/terminal/install_peripherie.sh` (Stufe 5). Es setzt `RFID_VARIANTE`
> aus der Antwortdatei um – `usb`, `bridge` oder `keine` – und schreibt das
> Ergebnis nach `config/geraet.local.php`. Siehe
> [Terminal-Installation](spezifikation_terminal_installation.md), Abschnitt 6.
>
> Diese Datei beschreibt, **was dabei passiert** und wie man es von Hand macht:
> für Fehlersuche, für Geräte, die nicht mit dem Skript aufgesetzt wurden, und
> um zu verstehen, woran es liegt, wenn nichts ankommt.

## Übersicht: unterstützte Reader-Varianten

1) **Tastatur-Scanner (USB-HID/Keyboard-Wedge)**
   - RFID-Reader verhält sich wie eine Tastatur und tippt die UID ins aktive Eingabefeld.
   - Kein zusätzlicher Dienst notwendig.

2) **SPI/RC522 mit WebSocket-Bridge**
   - RFID-Reader ist per SPI angebunden (z. B. RC522) und liefert die UID über einen lokalen WebSocket-Dienst.
   - Das Terminal-Frontend verbindet sich auf die Bridge-URL aus der Konfiguration.

## Schritt-für-Schritt-Konfiguration

### Variante 1: Tastatur-Scanner (USB-HID/Keyboard-Wedge)

1) **Hardware anschließen**
   - Reader per USB anschließen.
   - Prüfen, dass Scans als Tastatureingabe ankommen (z. B. in einem Textfeld).

2) **RFID-Bridge deaktivieren**
   - Auf einem Terminal: `RFID_VARIANTE="usb"` in der Antwortdatei, dann
     `install_peripherie.sh`. Das Skript trägt `rfid_ws.enabled = false` in
     `config/geraet.local.php` ein.
   - **Wichtig:** Beim Koppeln wandert der Wert von dort nach
     `config/config.local.php`. Bei einem **bereits gekoppelten** Gerät wirkt
     eine Änderung an `geraet.local.php` deshalb erst nach erneuter Kopplung –
     oder man gleicht den `rfid_ws`-Block in `config.local.php` von Hand an.
   - Ohne Skript: `terminal.rfid_ws.enabled = false` in
     `config/config.local.php`; alternativ `ZEIT_RFID_WS_ENABLED=0` (greift nur,
     solange es keine `config.local.php` gibt – die hat Vorrang).

3) **Terminal testen**
   - Terminal-Seite öffnen (`terminal.php?aktion=start`).
   - RFID-Scan durchführen und prüfen, dass die UID ins Feld geschrieben wird.

### Variante 2: Bridge mit WebSocket (serieller Leser, RC522)

**Zur Bezeichnung:** Das mitgelieferte `docs/terminal/rfid_ws.py` liest einen
**seriellen Anschluss** (`/dev/ttyUSB0`), nicht direkt den SPI-Bus. Für viele
RC522-Aufbauten steht ein kleiner Mikrocontroller davor, der die UID seriell
ausgibt – dafür passt es unverändert. Ein RC522, der **direkt** am SPI des
Raspberry Pi hängt, braucht ein anderes Leseprogramm; die Bridge, der Dienst
und die Terminal-Seite bleiben dieselben. `install_peripherie.sh` schaltet SPI
auf einem Raspberry Pi ein (`dtparam=spi=on`), liefert aber ebenfalls nur das
serielle Leseprogramm.

1) **Bridge-Dienst installieren**
   - Folge der Anleitung in `docs/terminal/rfid-ws_rollout.md`.
   - Typische Pfade:
     - Service-Datei: `/etc/systemd/system/rfid-ws.service`
     - Projektpfad des Dienstes: z. B. `/opt/rfid-ws/`

2) **Bridge-Dienst aktivieren**
   - `systemctl enable --now rfid-ws.service`
   - `systemctl status rfid-ws.service --no-pager`

3) **Terminal-Konfiguration setzen**
   - Mit Skript: `RFID_VARIANTE="bridge"`, dazu `RFID_GERAET` und `RFID_BAUD`
     in der Antwortdatei. Den Rest erledigt `install_peripherie.sh`.
   - Ohne Skript, in `config/config.local.php`:
     - `terminal.rfid_ws.enabled = true`
     - `terminal.rfid_ws.url = ws://127.0.0.1:8765`
   - Zum Vorrang von `config.local.php` siehe den Hinweis bei Variante 1.

4) **Verbindung testen**
   - Port-Check: `ss -lntp | grep 8765`
   - WebSocket-Quickcheck siehe `docs/terminal/rfid-ws_rollout.md`.

## Fehlersuche

### Typische Fehlerbilder
- **Es kommt keine Eingabe an (Keyboard-Wedge):**
  - Prüfen, ob der Scanner im richtigen Modus ist (Tastatur/USB-HID).
  - Fokus im Browser auf ein Eingabefeld setzen.

- **WebSocket-Status bleibt „getrennt“:**
  - Bridge-Dienst läuft nicht oder falsche URL/Port.
  - Bei HTTPS-Terminal ggf. `wss://` statt `ws://` nutzen.

- **RFID unbekannt / kein Login möglich:**
  - RFID-Code ist nicht dem Mitarbeiter zugeordnet.
  - RFID-Zuweisung im Terminal prüfen (Admin-Rechte erforderlich).

### Log-Stellen und Dateien
- **System-Log (Datenbank):** Tabelle `system_log` (Kategorie z. B. `terminal_offline_rfid`).
- **PHP-Fehlerlog:** Server- bzw. PHP-Error-Log (Fallback über `error_log()`).
- **Bridge-Logs:** `journalctl -u rfid-ws.service -f`.

### Testschritte zur Diagnose
1. Terminal-Startseite öffnen und RFID scannen.
2. Prüfen, ob ein Login/Fehlerhinweis erscheint.
3. Falls offline getestet wird: Kommen/Gehen im Offline-Modus auslösen und danach Queue/Replay prüfen.
4. Logs mit Zeitstempel der Scan-Aktion vergleichen.
