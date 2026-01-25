import asyncio
import serial
import websockets
import threading

SERIAL_PORT = "/dev/ttyUSB0"
BAUD = 9600          # falls nötig: 115200
WS_HOST = "127.0.0.1"
WS_PORT = 8765

clients: set[websockets.WebSocketServerProtocol] = set()

async def ws_handler(websocket):
    clients.add(websocket)
    try:
        await websocket.send("CONNECTED")
        await websocket.wait_closed()
    finally:
        clients.discard(websocket)

def serial_thread(loop: asyncio.AbstractEventLoop, queue: asyncio.Queue):
    ser = serial.Serial(SERIAL_PORT, BAUD, timeout=1)
    buf = b""
    while True:
        b = ser.read(1)
        if not b:
            continue
        if b in b"\r\n":
            line = buf.decode(errors="ignore").strip()
            buf = b""
            if line:
                # thread-safe in asyncio queue
                loop.call_soon_threadsafe(queue.put_nowait, line)
        else:
            buf += b

async def broadcaster(queue: asyncio.Queue):
    while True:
        line = await queue.get()
        if clients:
            await asyncio.gather(*[c.send(line) for c in list(clients)], return_exceptions=True)

async def main():
    loop = asyncio.get_running_loop()
    queue: asyncio.Queue[str] = asyncio.Queue()

    t = threading.Thread(target=serial_thread, args=(loop, queue), daemon=True)
    t.start()

    async with websockets.serve(ws_handler, WS_HOST, WS_PORT):
        await broadcaster(queue)

if __name__ == "__main__":
    asyncio.run(main())
