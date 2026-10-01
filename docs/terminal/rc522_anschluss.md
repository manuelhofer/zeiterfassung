# RC522 anschließen und installieren

Der vorhandene WebSocket-Weg bleibt bestehen. Zusätzlich zur seriellen Bridge
liest `rc522.py` den Chip jetzt direkt über Linux `spidev`. GPIO-Bibliotheken
wie `RPi.GPIO` werden dafür nicht benötigt. Der native Installer unterstützt
weiterhin Linux mit systemd und den vorhandenen Paketmanager-Familien;
Windows/macOS und beliebige USB-SPI-Adapter sind dadurch nicht automatisch unterstützt.

## Am Gerät anzeigen

```bash
bash terminal-installieren.sh --anschluss
```

Das funktioniert ohne root und ändert nichts. Es zeigt das erkannte Modell,
Steckleisten-Pinnummern und Signalnamen. Im Installer Auswahl **4: RC522/SPI**
verwenden. Der Anschlussplan erscheint nochmals und wird unter
`/var/lib/zeiterfassung-terminal-installation/rc522-anschluss.txt` gespeichert.
Eine falsche Auswahl lässt sich vor der Kopplung mit `--hardware` korrigieren.

## Raspberry Pi mit bekanntem 40-Pin-Anschluss

Automatisch erkannt werden Pi 1 A+/B+, Pi 2/3/4/5, Zero/Zero W/Zero 2 W und Pi 400.
Compute Modules und unbekannte Platinen werden nicht diesem Pinplan zugeordnet.

**Vor dem Anschließen herunterfahren und das Netzteil abziehen. Nur 3,3 V –
niemals 5 V.** Die Beschriftung des RC522-Moduls ist maßgeblich, nicht die
Reihenfolge seiner Stifte. Die Zahlen unten sind **physische Steckleisten-Pins**;
GPIO-Nummern stehen gesondert daneben. Pin 1 an der Platinenmarkierung bestimmen.

| Beschriftung am RC522 | Physischer Pin am Pi | Signal |
| --- | --- | --- |
| 3.3V / VCC | 1 | 3,3 V |
| GND | 6 | Masse |
| SDA / SS | 24 | GPIO8 / SPI0 CE0 |
| SCK | 23 | GPIO11 / SPI0 SCLK |
| MOSI | 19 | GPIO10 / SPI0 MOSI |
| MISO | 21 | GPIO9 / SPI0 MISO |
| RST | 17 | 3,3 V, dauerhaft HIGH |
| IRQ | nicht anschließen | nicht verwendet |

**SDA bedeutet hier SPI-Chip-Select**, nicht I²C-SDA. RST liegt in diesem
Anschlussplan fest an 3,3 V; der Treiber setzt den Chip per SPI zurück.
Eine ältere Anleitung mit RST an GPIO25 passt deshalb nicht unverändert.
Der Anschlussplan setzt ein übliches, auf SPI beschaltetes RC522-Modul voraus.

Grundlage: [Raspberry-Pi GPIO/SPI-Dokumentation](https://www.raspberrypi.com/documentation/computers/raspberry-pi.html#gpio-and-the-40-pin-header)
und [NXP MFRC522-Datenblatt](https://www.nxp.com/docs/en/data-sheet/MFRC522.pdf),
Reset-Eingang und SoftReset. Keine Zuordnung durch eine GPIO-Bibliothek nötig.

## Andere Rechner und Platinen

Der Leseweg akzeptiert `/dev/spidevBUS.CS`. Voraussetzung ist eine vom Linux-Kernel
bereitgestellte SPI-Schnittstelle mit passenden 3,3-V-Signalen. Das ist unabhängig
von CPU und Paketmanager, aber nicht von vorhandener Hardware und deren Treibern.

Der Installer zeigt dort die Signalzuordnung: SS→CS, SCK→SCLK, MOSI→MOSI,
MISO→MISO, VCC/RST→3,3 V, GND→Masse, IRQ frei. **Die physischen Pinnummern müssen
aus dem Herstellerplan stammen.** Für unbekannte Platinen gibt es keine geratenen
Nummern und keine automatische Bootkonfiguration. Ohne vorhandenen SPI-Anschluss
stoppt die Installation vor den Systemstufen.

Ein normaler PC ohne SPI braucht einen passenden Adapter oder einen
Mikrocontroller, der die Kartenkennung als serielle Textzeile ausgibt. Für Letzteres
bleibt im Installer die Auswahl „serieller Leser“ erhalten. Ein gewöhnliches
USB-UART-Kabel wandelt USB nicht in SPI um. Eine Firmware für einen bestimmten
Mikrocontroller ist nicht Bestandteil dieses Patches.

## Neustart und Prüfung

Fehlt SPI0 auf einem erkannten Pi, richtet der Installer den Boot-Eintrag ein
und meldet einen nötigen Neustart (Status 20). Der Kiosk bleibt für diesen
Zwischenstart deaktiviert, damit die Kopplung nicht vor der Leserprüfung beginnt:

```bash
sudo reboot
# Nach dem Neustart:
sudo bash terminal-installieren.sh
```

Die Auswahl bleibt gespeichert. Der Dienstbenutzer erhält Zugriff auf genau den
gewählten SPI-Anschluss. Vor dem Dienststart wird der Leser unter diesem Benutzer
geöffnet und sein Chipregister geprüft. Ein abgezogener oder falsch verdrahteter
Leser darf nicht bloß einen lauschenden Port hinterlassen; die Bridge beendet sich
bei einem Leseausfall und systemd versucht einen Neustart.

Der abschließende Selbsttest prüft zusätzlich die Bereitschaftsmeldung der Bridge.
**Ein echter Kartenscan bleibt nötig:** Chipregister und Port beweisen noch keine
funktionierende Antenne. Anschließend UID am Terminal zuordnen und Anmelden testen.
Der neue Leseweg liefert 4-, 7- oder 10-Byte-UIDs als Großbuchstaben-Hex ohne
Trennzeichen, inklusive führender Nullen (z. B. `00A1B2C3`). Bestehende serielle
Kennungen werden nicht umformatiert. Beim Wechsel von einem Leser mit anderem
Kennungsformat muss die Zuordnung überprüft werden.

Eine gehaltene Karte wird einmal gemeldet; erst nach Entfernen und erneutem
Vorhalten wieder. Mehrere gleichzeitig aufgelegte Karten werden nicht aufgelöst.
Es werden nur Kennungen gelesen, keine Kartendaten geschrieben.
Protokollgrundlage: [NXP AN10927, UID-Kaskaden](https://www.nxp.com/docs/en/application-note/AN10927.pdf).

## Prüfstand

Register-/FIFO-Simulation, fehlerhafte Kartenantworten, vollständige UID-Kaskaden,
Entprellung, serielle Kompatibilität und die tatsächliche lokale WebSocket-Übertragung
sind automatisiert geprüft. Ein RC522 war hier nicht angeschlossen: native
Paketinstallation, elektrische Verdrahtung und reale Kartenscans stehen noch aus.
