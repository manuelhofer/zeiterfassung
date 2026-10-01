"""UID-Leser fuer MFRC522 an Linux spidev (SPI Mode 0, 1 MHz).

RST fest an 3,3 V: SoftReset ersetzt plattformspezifisches GPIO.
Register/Frames: NXP MFRC522 Datenblatt Rev. 3.9 und AN10927.
Nur UID-Erkennung; keine Kartendaten schreiben, keine Authentifizierung.
"""
import re
import time


def crc_a(data):
    crc = 0x6363
    for byte in data:
        crc ^= byte
        for _ in range(8):
            crc = (crc >> 1) ^ (0x8408 if crc & 1 else 0)
    return [crc & 255, crc >> 8]


class RC522:
    def __init__(self, device='/dev/spidev0.0', spi=None):
        match = re.fullmatch(r'/dev/spidev([0-9]+)\.([0-9]+)', device)
        if not match:
            raise ValueError('SPI-Anschluss muss /dev/spidevBUS.CS sein.')
        if spi is None:
            import spidev
            spi = spidev.SpiDev()
        self.spi = spi
        try:
            spi.open(int(match[1]), int(match[2]))
            spi.max_speed_hz = 1_000_000
            spi.mode = 0
            spi.bits_per_word = 8
            self.pruefen()
            self.write(0x01, 0x0F)  # SoftReset
            time.sleep(0.05)
            deadline = time.monotonic() + 0.2
            while self.read(0x01) & 0x10:
                if time.monotonic() >= deadline:
                    raise OSError('RC522 bleibt im Reset; Stromversorgung/RST pruefen.')
                time.sleep(0.001)
            # Automatischer Timer: 13,56 MHz / (2*169 + 1), 1000 Takte ~25 ms.
            for register, value in [(0x2A, 0x80), (0x2B, 0xA9), (0x2C, 3),
                                    (0x2D, 0xE8), (0x12, 0), (0x13, 0),
                                    (0x15, 0x40), (0x11, 0x3D)]:
                self.write(register, value)
            self.write(0x14, self.read(0x14) | 3)  # Antenne einschalten
        except BaseException:
            spi.close()
            raise

    def read(self, register):
        return self.spi.xfer2([0x80 | ((register << 1) & 0x7E), 0])[1]

    def write(self, register, value):
        self.spi.xfer2([(register << 1) & 0x7E, value])

    def pruefen(self):
        version = self.read(0x37)
        # 00/FF sind typische offene/kurzgeschlossene SPI-Leitungen. Andere
        # unbekannte Chips ebenfalls nicht als einen funktionierenden RC522 melden.
        if version not in (0x91, 0x92, 0x88):
            raise OSError(f'RC522 antwortet nicht passend (Version 0x{version:02X}); '
                          '3,3 V, RST und SPI-Verkabelung pruefen.')
        return version

    def austausch(self, data, restbits=0):
        self.write(0x01, 0)  # Idle
        self.write(0x04, 0x7F)  # IRQs quittieren
        self.write(0x0A, 0x80)  # FIFO leeren
        self.write(0x0D, restbits)
        for byte in data:
            self.write(0x09, byte)
        self.write(0x01, 0x0C)  # Transceive
        self.write(0x0D, 0x80 | restbits)
        deadline = time.monotonic() + 0.1
        try:
            while True:
                irq = self.read(0x04)
                if irq & 0x30:
                    break
                if irq & 1:  # Karten-Timeout, kein Hardwareausfall
                    return None
                if time.monotonic() >= deadline:
                    raise OSError('RC522 reagiert nicht mehr; Anschluss pruefen.')
                time.sleep(0.001)
            # Keine Kennung bei Kollision, Paritaets-/Protokoll-/FIFO-Fehlern.
            if self.read(0x06) & 0xDF:
                return None
            count = self.read(0x0A)
            if not 0 < count <= 64:
                return None
            result = [self.read(0x09) for _ in range(count)]
            if self.read(0x0C) & 7:
                return None  # UID-Antworten muessen ganze Bytes enthalten
            return result
        finally:
            self.write(0x0D, restbits)
            self.write(0x01, 0)

    def read_uid(self):
        self.pruefen()  # Ein laufender Port alleine beweist keinen Leser.
        self.write(0x08, self.read(0x08) & ~0x08)  # Crypto1 aus
        atqa = self.austausch([0x52], 7)  # WUPA: auch gehaltene Karten wieder sehen
        if atqa is None or len(atqa) != 2:
            return None
        uid = []
        for level in (0x93, 0x95, 0x97):
            block = self.austausch([level, 0x20])
            if block is None or len(block) != 5:
                return None
            if block[0] ^ block[1] ^ block[2] ^ block[3] != block[4]:
                return None
            select = [level, 0x70] + block
            sak = self.austausch(select + crc_a(select))
            if sak is None or len(sak) != 3 or crc_a(sak[:1]) != sak[1:]:
                return None
            continuation = bool(sak[0] & 4)
            if continuation:
                if block[0] != 0x88 or level == 0x97:
                    return None
                uid.extend(block[1:4])
            else:
                if block[0] == 0x88:
                    return None
                uid.extend(block[:4])
                halt = [0x50, 0]
                self.austausch(halt + crc_a(halt))
                return ''.join(f'{byte:02X}' for byte in uid)
        return None

    def close(self):
        self.spi.close()
