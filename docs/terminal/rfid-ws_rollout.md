# RFID WebSocket Bridge – Rollout (Terminal)

Diese Anleitung beschreibt die **Standard-Installation** des RFID-WebSocket-Dienstes auf einem Terminal.

## Ziel
- Ein lokaler Dienst liefert RFID-UIDs per WebSocket an den Browser.
- Das Terminal-Frontend verbindet sich zur URL aus `config/config.php` (z. B. `ws://127.0.0.1:8765`).

## Voraussetzungen
- Debian
- Python3 + venv
- Projektdateien des Dienstes liegen z. B. unter `/opt/rfid-ws/`

## 1) Dienstdatei kopieren
1. Datei aus diesem Projekt:
   - `docs/terminal/rfid-ws.service`
2. Nach:
   - `/etc/systemd/system/rfid-ws.service`

```bash
sudo cp /pfad/zum/projekt/docs/terminal/rfid-ws.service /etc/systemd/system/rfid-ws.service
sudo systemctl daemon-reload
```

> Hinweis: Wenn du andere Pfade nutzt, passe `WorkingDirectory` und `ExecStart` in der Service-Datei an.

## 2) Service aktivieren
```bash
sudo systemctl enable --now rfid-ws.service
sudo systemctl status rfid-ws.service --no-pager
```

## 3) Healthcheck / Port-Check
Der Dienst soll auf `127.0.0.1:8765` lauschen (Beispiel).

```bash
ss -lntp | grep 8765
```

Optionaler WS-Quickcheck (sollte zuerst `CONNECTED` senden):
```bash
/opt/rfid-ws/venv/bin/python - <<'PY'
import asyncio, websockets

async def main():
    async with websockets.connect('ws://127.0.0.1:8765') as ws:
        print('connected')
        print('MSG:', await ws.recv())

asyncio.run(main())
PY
```

## 4) Frontend-Konfiguration
In `config/config.php` muss die Bridge aktiviert und die URL korrekt gesetzt sein:
- `terminal.rfid_ws.enabled = true`
- `terminal.rfid_ws.url = ws://127.0.0.1:8765`

## 5) Logs ansehen
```bash
journalctl -u rfid-ws.service -f
```
