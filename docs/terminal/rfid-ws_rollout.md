# RFID WebSocket Bridge – Rollout (Terminal)

> **Der Normalweg ist seit P-2026-08-09-17 das Skript:**
> `sudo ./scripts/terminal/install_peripherie.sh` mit `RFID_VARIANTE="bridge"`
> in der Antwortdatei. Es legt Python-Umgebung, Dienstbenutzer, Dienst und
> Konfiguration an und trägt `rfid_ws.enabled = true` ein.
>
> Diese Anleitung ist der **Weg von Hand** – für Fehlersuche und für Geräte, die
> nicht mit dem Skript aufgesetzt wurden.
>
> **Unterschiede zwischen Skript und dieser Anleitung:** Das Skript legt einen
> eigenen Dienstbenutzer `rfidws` an (in `dialout`/`uucp`/`spi`) statt `www-data`
> zu verwenden, schreibt die Dienstdatei selbst statt die Vorlage unten zu
> kopieren, trägt `SERIAL_PORT` und `BAUD` aus der Antwortdatei ein und startet
> den Dienst nur, wenn der Anschluss existiert. Die Vorlagen in diesem
> Verzeichnis bleiben als Ausgangspunkt bestehen.

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
