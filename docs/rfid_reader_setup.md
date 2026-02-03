# RFID-Reader einrichten

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
   - In `config/config.php` bzw. besser in `config/config.local.php`:
     - `terminal.rfid_ws.enabled = false`
   - Alternativ per Umgebungsvariable:
     - `ZEIT_RFID_WS_ENABLED=0`

3) **Terminal testen**
   - Terminal-Seite öffnen (`terminal.php?aktion=start`).
   - RFID-Scan durchführen und prüfen, dass die UID ins Feld geschrieben wird.

### Variante 2: SPI/RC522 mit WebSocket-Bridge

1) **Bridge-Dienst installieren**
   - Folge der Anleitung in `docs/terminal/rfid-ws_rollout.md`.
   - Typische Pfade:
     - Service-Datei: `/etc/systemd/system/rfid-ws.service`
     - Projektpfad des Dienstes: z. B. `/opt/rfid-ws/`

2) **Bridge-Dienst aktivieren**
   - `systemctl enable --now rfid-ws.service`
   - `systemctl status rfid-ws.service --no-pager`

3) **Terminal-Konfiguration setzen**
   - In `config/config.php` bzw. `config/config.local.php`:
     - `terminal.rfid_ws.enabled = true`
     - `terminal.rfid_ws.url = ws://127.0.0.1:8765`
   - Alternativ per Umgebungsvariablen:
     - `ZEIT_RFID_WS_ENABLED=1`
     - `ZEIT_RFID_WS_URL=ws://127.0.0.1:8765`

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
