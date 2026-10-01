"""Lokale RFID-Bridge: serielle Zeilen oder RC522/SPI -> vorhandenes WebSocket-UI."""
import argparse
import asyncio
import os
import time

SERIAL_PORT = "/dev/ttyUSB0"
BAUD = 9600
WS_HOST = "127.0.0.1"
WS_PORT = 8765


class SeriellerLeser:
    def __init__(self, device, baud):
        import serial
        self.serial = serial.Serial(device, baud, timeout=0.1)
        self.buffer = bytearray()

    def read_uid(self):
        # Genau eine Zeile pro Aufruf, unveraendertes bisheriges Kennungsformat.
        for _ in range(256):
            byte = self.serial.read(1)
            if not byte:
                return None
            if byte in (b'\r', b'\n'):
                line = self.buffer.decode(errors='ignore').strip()
                self.buffer.clear()
                if line:
                    return line
            else:
                self.buffer.extend(byte)
                if len(self.buffer) > 256:
                    raise OSError('Serieller Leser liefert keine begrenzten UID-Zeilen.')
        return None

    def close(self):
        self.serial.close()


class Entprellung:
    def __init__(self):
        self.letzte = None
        self.zuletzt_gesehen = 0

    def neu(self, uid, jetzt):
        if uid is None:
            if jetzt - self.zuletzt_gesehen >= 0.7:
                self.letzte = None
            return False
        senden = uid != self.letzte
        self.letzte, self.zuletzt_gesehen = uid, jetzt
        return senden


async def serve(reader, rc522=False, port=WS_PORT):
    import websockets
    clients = set()
    filter_ = Entprellung()

    async def handler(websocket):
        clients.add(websocket)
        try:
            await websocket.send('CONNECTED')
            await websocket.wait_closed()
        finally:
            clients.discard(websocket)

    # reader ist bereits geoeffnet/initialisiert: ein kaputter Leser darf nicht
    # durch einen trotzdem lauschenden WebSocket als funktionsfaehig erscheinen.
    async with websockets.serve(handler, WS_HOST, port):
        while True:
            uid = await asyncio.to_thread(reader.read_uid)
            if (filter_.neu(uid, time.monotonic()) if rc522 else bool(uid)):
                await asyncio.gather(*(client.send(uid) for client in list(clients)),
                                     return_exceptions=True)
            await asyncio.sleep(0.08 if rc522 else 0.01)


async def bridge_test():
    import websockets
    async with websockets.connect(f'ws://{WS_HOST}:{WS_PORT}', open_timeout=5) as ws:
        if await asyncio.wait_for(ws.recv(), timeout=5) != 'CONNECTED':
            raise OSError('RFID-Bridge liefert keine Bereitschaftsmeldung.')
    print('RFID-Bridge und geoeffneter Leser erreichbar; echten Scan am Terminal pruefen.')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--bridge-test', action='store_true')
    parser.add_argument('--pruefen', action='store_true')
    args = parser.parse_args()
    if args.bridge_test:
        asyncio.run(bridge_test())
        return
    variante = os.environ.get('RFID_VARIANTE', 'bridge')
    device = os.environ.get('RFID_GERAET', SERIAL_PORT)
    if variante == 'rc522':
        from rc522 import RC522
        reader = RC522(device)
    elif variante == 'bridge':
        reader = SeriellerLeser(device, int(os.environ.get('RFID_BAUD', BAUD)))
    else:
        raise ValueError('RFID_VARIANTE muss bridge oder rc522 sein.')
    try:
        if args.pruefen:
            print('Leser erfolgreich geoeffnet' + (' und RC522 erkannt.' if variante == 'rc522' else '.'))
        else:
            asyncio.run(serve(reader, rc522=variante == 'rc522'))
    finally:
        reader.close()


if __name__ == '__main__':
    main()
