#!/usr/bin/env python3
"""RC522-Protokoll und reale lokale WebSockets mit synthetischen Leserdaten."""
import asyncio
from collections import deque
import importlib.util
import os
import subprocess
import tempfile
from pathlib import Path
import socket
import sys
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parents[2]

def module(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'docs/terminal' / f'{name}.py')
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod

rc = module('rc522')
bridge = module('rfid_ws')


class Spi:
    """Register-/FIFO-Gegenstelle, keine GPIO-/SPI-Hardware benoetigt."""
    def __init__(self, replies=(), version=0x92):
        self.reg = {0x37: version}
        self.replies = deque(replies)
        self.fifo = []
        self.sent = []
        self.closed = False

    def open(self, bus, device):
        self.opened = (bus, device)

    def close(self):
        self.closed = True

    def xfer2(self, data):
        address, value = data
        register = (address & 0x7e) >> 1
        if address & 0x80:
            if register == 0x09:
                value = self.fifo.pop(0)
            elif register == 0x0A:
                value = len(self.fifo)
            else:
                value = self.reg.get(register, 0)
            return [0, value]
        if register == 0x0A and value == 0x80:
            self.fifo = []
        elif register == 0x09:
            self.fifo.append(value)
        elif register == 0x04:
            self.reg[register] = 0
        elif register == 0x0D and value & 0x80:
            self.sent.append(list(self.fifo))
            reply = self.replies.popleft() if self.replies else None
            self.fifo = list(reply or [])
            self.reg[0x04] = 0x20 if reply is not None else 1
        else:
            self.reg[register] = value
        return [0, 0]


def block(data):
    return data + [data[0] ^ data[1] ^ data[2] ^ data[3]]


def sak(continuation):
    value = [4 if continuation else 0]
    return value + rc.crc_a(value)


class ReaderTest(unittest.TestCase):
    def reader(self, *args, **kwargs):
        spi = Spi(*args, **kwargs)
        with patch.object(rc.time, 'sleep'):
            reader = rc.RC522('/dev/spidev2.1', spi=spi)
        self.addCleanup(reader.close)
        return reader, spi

    def test_crc_known_halt_vector(self):
        self.assertEqual(rc.crc_a([0x50, 0]), [0x57, 0xCD])

    def test_four_byte_uid_preserves_leading_zero(self):
        reader, spi = self.reader([[4, 0], block([0, 0xA1, 0xB2, 0xC3]), sak(False), None])
        self.assertEqual(reader.read_uid(), '00A1B2C3')
        self.assertEqual(spi.opened, (2, 1))
        self.assertEqual(spi.sent[0], [0x52])
        self.assertEqual(spi.sent[-1], [0x50, 0, 0x57, 0xCD])

    def test_seven_and_ten_byte_uid(self):
        for uid, groups in [('01020304050607', [[0x88, 1, 2, 3], [4, 5, 6, 7]]),
                            ('0102030405060708090A', [[0x88, 1, 2, 3], [0x88, 4, 5, 6], [7, 8, 9, 10]])]:
            replies = [[0x44, 0]]
            for index, group in enumerate(groups):
                replies.extend([block(group), sak(index < len(groups)-1)])
            replies.append(None)
            reader, _ = self.reader(replies)
            self.assertEqual(reader.read_uid(), uid)

    def test_bad_bcc_and_bad_select_crc_are_not_identifiers(self):
        for replies in [[[4, 0], [1, 2, 3, 4, 255]],
                        [[4, 0], block([1, 2, 3, 4]), [0, 0, 0]]]:
            reader, _ = self.reader(replies)
            self.assertIsNone(reader.read_uid())

    def test_incomplete_cascade_never_yields_truncated_uid(self):
        reader, _ = self.reader([[4, 0], block([0x88, 1, 2, 3]), sak(True), None])
        self.assertIsNone(reader.read_uid())

    def test_no_card_is_normal(self):
        reader, _ = self.reader()
        self.assertIsNone(reader.read_uid())

    def test_unplugged_reader_is_error_and_releases_spi(self):
        for version in [0, 255, 0x12]:
            spi = Spi(version=version)
            with self.assertRaises(OSError):
                rc.RC522(spi=spi)
            self.assertTrue(spi.closed)
        reader, spi = self.reader()
        spi.reg[0x37] = 255
        with self.assertRaises(OSError):
            reader.read_uid()

    def test_card_collision_does_not_choose_an_employee(self):
        reader, spi = self.reader([[4, 0]])
        spi.reg[0x06] = 8
        self.assertIsNone(reader.read_uid())

    def test_stuck_chip_has_bounded_timeout(self):
        reader, spi = self.reader()
        with patch.object(reader, 'read', return_value=0), \
             patch.object(rc.time, 'monotonic', side_effect=[0, 1]):
            with self.assertRaises(OSError):
                reader.austausch([0x52], 7)

    def test_held_card_only_once_until_removed(self):
        f = bridge.Entprellung()
        self.assertTrue(f.neu('00112233', 0))
        self.assertFalse(f.neu('00112233', 2))
        self.assertFalse(f.neu(None, 2.2))
        self.assertFalse(f.neu('00112233', 2.3))
        self.assertFalse(f.neu(None, 3.1))
        self.assertTrue(f.neu('00112233', 3.2))
        self.assertTrue(f.neu('00445566', 3.3))

    def test_serial_format_and_split_lines_unchanged(self):
        class Serial:
            def __init__(self, *args, **kwargs):
                self.data = deque([b'0', b'0', b'1', b'2', b'', b'3', b'\r', b'\n'])
            def read(self, size):
                return self.data.popleft() if self.data else b''
            def close(self):
                pass
        import types
        with patch.dict(sys.modules, serial=types.SimpleNamespace(Serial=Serial)):
            reader = bridge.SeriellerLeser('/dev/test', 9600)
        self.assertIsNone(reader.read_uid())
        self.assertEqual(reader.read_uid(), '00123')
        self.assertIsNone(reader.read_uid())


class SpiInstallationTest(unittest.TestCase):
    def test_boot_activation_is_idempotent_and_platform_scoped(self):
        source = (ROOT / 'scripts/terminal/install_peripherie.sh').read_text()
        start = source.index('        if [ "$RFID_VARIANTE" = rc522 ]; then')
        end = source.index('        # --- Python-Umgebung', start)
        fragment = source[start:end]
        with tempfile.TemporaryDirectory(prefix='rc522-boot-test-') as tmp:
            directory = Path(tmp)
            boot = directory / 'config.txt'
            original = '[pi4]\ndtparam=spi=on\n'
            boot.write_text(original)
            fragment = fragment.replace('/boot/firmware/config.txt', str(boot))
            fragment = fragment.replace('/boot/config.txt', str(directory / 'legacy.txt'))
            env = dict(os.environ, RFID_VARIANTE='rc522', RFID_GERAET='/dev/spidev0.0',
                       IST_RASPBERRY='ja', SPI_AKTIVIEREN='ja', NEUSTART_NOETIG='nein')
            for run in range(2):
                result = subprocess.run(['bash', '-eu', '-c', fragment + '\n[ "$NEUSTART_NOETIG" = ja ]'],
                                        env=env, capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
                self.assertTrue(boot.read_text().startswith(original))
                self.assertEqual(boot.read_text().count('# Zeiterfassung RC522 SPI0'), 1)
                self.assertIn('[all]\n# Zeiterfassung RC522 SPI0\ndtparam=spi=on', boot.read_text())
            before = boot.read_text()
            for changes in [{'IST_RASPBERRY': 'nein'}, {'SPI_AKTIVIEREN': 'nein'},
                            {'RFID_GERAET': '/dev/spidev1.0'}]:
                result = subprocess.run(['bash', '-eu', '-c', fragment], env=dict(env, **changes),
                                        capture_output=True, text=True)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(boot.read_text(), before)


class WebSocketTest(unittest.IsolatedAsyncioTestCase):
    async def test_real_socket_uid_and_disconnect_when_hardware_fails(self):
        import websockets
        class Reader:
            uid = None
            failed = False
            def read_uid(self):
                if self.failed:
                    raise OSError('synthetic unplug')
                return self.uid
        reader = Reader()
        with socket.socket() as reservation:
            reservation.bind(('127.0.0.1', 0))
            port = reservation.getsockname()[1]
        task = asyncio.create_task(bridge.serve(reader, rc522=True, port=port))
        try:
            ws = None
            for _ in range(100):
                try:
                    ws = await websockets.connect(f'ws://127.0.0.1:{port}')
                    break
                except OSError:
                    await asyncio.sleep(0.01)
            self.assertIsNotNone(ws)
            async with ws:
                self.assertEqual(await asyncio.wait_for(ws.recv(), 2), 'CONNECTED')
                reader.uid = '00A1B2C3'
                self.assertEqual(await asyncio.wait_for(ws.recv(), 2), '00A1B2C3')
                with self.assertRaises(asyncio.TimeoutError):
                    await asyncio.wait_for(ws.recv(), 0.25)
                reader.failed = True
                with self.assertRaises(websockets.exceptions.ConnectionClosed):
                    await asyncio.wait_for(ws.recv(), 2)
            with self.assertRaises(OSError):
                await task
        finally:
            if not task.done():
                task.cancel()
                try:
                    await task
                except asyncio.CancelledError:
                    pass


if __name__ == '__main__':
    unittest.main(verbosity=2)
