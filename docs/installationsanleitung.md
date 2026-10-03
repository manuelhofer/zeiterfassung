# Installationsanleitung

Diese Anleitung beschreibt eine vollständige, praxistaugliche Installation des
Zeiterfassungs-Backends. Sie enthält die notwendigen Schritte von den
Voraussetzungen bis zur ersten erfolgreichen Anmeldung. Für
Terminal-spezifische Setups (RFID, Offline-DB) gibt es zusätzliche Hinweise.

Nur zum Ausprobieren oder Entwickeln auf dem eigenen Rechner? Dann ist
`docs/lokale_entwicklungsumgebung.md` der schnellere Weg – diese Anleitung hier
beschreibt die Installation auf einem Server.

## 1) Standardinstallation auf Debian oder Raspberry Pi OS

Die automatische Installation ist für einen eigenen Zeiterfassungsserver mit
lokaler MariaDB und Apache/PHP-FPM gedacht (PHP mindestens 8.2).

```bash
sudo git clone https://github.com/manuelhofer/zeiterfassung.git /var/www/zeiterfassung
cd /var/www/zeiterfassung
sudo bash scripts/installieren.sh
```

Das Skript installiert Pakete, richtet Datenbank, zufällige lokale Zugangsdaten,
Dateirechte und Webserver ein und startet den Wartungsdienst. Vorhandene lokale
Anwendungskonfiguration wird erhalten. Es ist kein zusätzlicher Befehl für
Backup oder Updates nötig.

Wurde das Repository beispielsweise unter `/root/zeiterfassung` heruntergeladen,
verschiebt der Installer es automatisch nach `/var/www/zeiterfassung`, weil der
Webserver `/root` nicht betreten kann. Konfiguration und Git-Verzeichnis bleiben
erhalten; `/root` wird nicht freigegeben. Ein bereits belegtes Ziel wird nicht
überschrieben. Danach für weitere Git-Befehle den neuen Projektordner verwenden.

Ist eine ältere Installation mit `runuser: fehlgeschlagen (Exit 1)` unter `/root`
abgebrochen, im bisherigen Projektordner `git pull --ff-only` und nochmals
`bash scripts/installieren.sh` als root ausführen. Die bereits angelegte lokale
Konfiguration und Datenbank werden weiterverwendet; sie müssen nicht gelöscht
werden. Der Installer führt den nötigen Umzug selbst aus.

Die Webserver-Konfiguration bedient das Verzeichnis `public/` auf Port 80 und
ersetzt dabei die Debian-Standardseite. MariaDB nimmt Verbindungen für die
reguläre Terminal-Kopplung über das LAN an. Der Server ist für das interne
Betriebsnetz vorgesehen; bestehende Sonderkonfigurationen, Hosting und externe
Datenbanken sind kein Ziel dieses Installers.

## 2) Erste Anmeldung

Serveradresse im Browser öffnen und im vorhandenen Erstinstallationsformular
den ersten Administrator anlegen. Bei einer bestehenden Installation wie
gewohnt anmelden. Unter **Verwaltung → Backup und Updates** stehen die Knöpfe
nach der automatischen Vorbereitung bereit.

## 3) Backend und Terminals aktuell halten

**Nach Updates suchen**, danach bei einem Angebot **Jetzt aktualisieren**.
Die Sicherung und Verteilung an alle aktiven Terminals laufen automatisch.
Ein eigenständiges Backup ist über **Backup erstellen** möglich.

Es gibt keine eigene Wartungskonfiguration und keine zusätzlichen SSH-Zugänge.
Ablauf, Fehleranzeigen und Wiederherstellung:
[Backup und Updates](wartung_betrieb.md).

## 4) Bestehende Installation übernehmen

Den gemeinsamen neuen Programmstand zunächst auf Backend und Terminals
normal ausliefern. Die jeweiligen Installer bereiten den Dienst selbst vor;
das Terminal verwendet seine bestehende Kopplung weiter. Migrationen 14/15
werden automatisch vorbereitet. Ältere fachliche Bestandsmigrationen bis 13
müssen bereits abgeschlossen sein.

## 5) Abweichende Serverkonfiguration

Bei einer eigenen Apache-/Nginx-, externen DB- oder Hostingkonfiguration muss
die betreuende Person die zugehörigen Systemvoraussetzungen prüfen. Der
vollautomatische Standardinstaller ist dafür nicht ausgelegt. Der Document-Root
muss ausschließlich auf `public/` zeigen; Sicherungen und private Wartungsdaten
liegen außerhalb des Programmordners. PHP-Konfiguration liegt unter
`config/config.local.php` und gehört nicht ins Repository.

## 6) Prüfung

Anmeldung, gewöhnliche Terminal-Kopplung und ein gemeinsames Backup prüfen.
Danach einen Wiederherstellungstest auf separatem Gerät durchführen.
Weitere Prüfungen: [Wartungscheckliste](wartungscheckliste.md).
Die Paketinstallation sowie Apache/FPM und Systemd müssen auf einem echten
Zielgerät abgenommen werden; lokale Anwendungstests ersetzen das nicht.

## 7) Terminal-Installation (optional)

Auf einem **frisch installierten, ausschließlich dafür vorgesehenen Linux-Gerät**
mit systemd (Zielsystem: Debian / Raspberry Pi OS) diese beiden Befehle ausführen:

```bash
curl -fL https://raw.githubusercontent.com/manuelhofer/zeiterfassung/main/scripts/terminal/installieren.sh -o terminal-installieren.sh
sudo bash terminal-installieren.sh
```

Für den Download muss `curl` vorhanden sein (auf Debian bei Bedarf einmal
`sudo apt-get install curl`). Das Terminal braucht während der Erstinstallation
Internet für GitHub, Systempakete und bei seriellen Lesern Python-Bibliotheken.
Linux selbst muss bereits installiert und mit dem Netzwerk verbunden sein.

Der Installer holt `main` nach `/opt/zeiterfassung`, richtet Grundsystem,
lokale Offline-Datenbank, Wartungsdienst, Vollbildbrowser und Peripherie ein,
prüft die Vorbereitung und startet die Einrichtungsseite. Er ersetzt die
grafische Anmeldung durch den Kiosk-Autostart. Gefragt wird nur nach Lesertyp,
Tastaturlayout und Bildschirmdrehung; beim seriellen Leser zusätzlich nach
Anschluss und Baudrate. Keine Konfigurationsdatei von Hand bearbeiten.

Für **USB-Leser im Tastaturmodus, deutsches Layout und einen ungedrehten
Bildschirm** geht es ohne Hardwarefragen:

```bash
sudo bash terminal-installieren.sh --standard
```

USB bedeutet hier ausdrücklich Tastaturmodus; serielle USB-Leser benötigen die
Auswahl „serieller Leser“. Für **RC522 direkt am SPI** Auswahl 4 verwenden.
`bash terminal-installieren.sh --anschluss` zeigt vorab ohne Änderungen den
[Anschlussplan mit Pins und Signalnamen](terminal/rc522_anschluss.md).
Bekannte Pi-Modelle werden erkannt; andere Platinen benötigen ihren Hersteller-
Pinplan und einen vorhandenen Linux-SPI-Anschluss. Wenn der Installer für SPI
einen Neustart meldet, danach denselben Befehl erneut starten; die Auswahl bleibt erhalten.

Bei einem Fehler stoppt der Ablauf. Nach Beheben der Ursache denselben Befehl
wiederholen: gespeicherte Hardwareauswahl und bereits heruntergeladene Dateien
werden weiterverwendet. Eine falsche Hardwareauswahl lässt sich vor der Kopplung
mit `sudo bash terminal-installieren.sh --hardware` korrigieren. Ein eingerichtetes Gerät oder ein fremdes Projekt im
Zielordner wird abgewiesen. Programmupdates später über das Backend starten.
Die Antwortdatei liegt geschützt unter
`/var/lib/zeiterfassung-terminal-installation/terminal.conf`, das Protokoll unter
`/var/log/zeiterfassung-terminal-setup.log`.

Nach der Kopplung **Bildschirm, Touch und einen echten RFID-/Barcode-Scan**
prüfen. Der vollständige Selbsttest bleibt verfügbar:

```bash
sudo bash /opt/zeiterfassung/scripts/terminal/selbsttest.sh /var/lib/zeiterfassung-terminal-installation/terminal.conf
```

Die bisherigen Einzelwerkzeuge `install_terminal.sh`, `install_kiosk.sh` und
`install_peripherie.sh` bleiben für gezielte Einrichtung/Reparatur vorhanden;
sie sind keine Programmupdater. Die technische Vorprüfung des gemeinsamen
Installers erlaubt lediglich die noch ausstehende Kopplung und den erst danach
startenden Kiosk; der normale Selbsttest verlangt weiterhin beides.

Das Grundsystem richtet Pakete, Code, Webserver und die lokale Ausweichdatenbank ein
und legt
`config/config.local.php` bewusst **nicht** an. Das Gerät startet
unkonfiguriert, zeigt seine Einrichtungsseite und holt sich Server-Adresse,
Terminal-ID und Zugangsdaten über einen Kopplungscode aus dem Backend – dabei
setzt es `installation_typ` selbst.

Diese Datei also **nicht von Hand anlegen**: Damit verschwindet die
Einrichtungsseite, das Gerät bekommt keinen eigenen Datenbankbenutzer, und im
Verlustfall lässt es sich nicht einzeln sperren.

Einzelheiten: [Terminal-Installation](spezifikation_terminal_installation.md).

Für RFID/Offline-Setup siehe zusätzlich:

- `docs/rfid_reader_setup.md`
- `docs/terminal/rfid-ws_rollout.md`

## 8) Mitarbeiterportal (optional)

Nur nötig, wenn Mitarbeiter ihren Urlaub über die WERNIG-Homepage beantragen
sollen. Die Kopplung selbst geschieht im Backend unter **Mitarbeiterportal**;
hier steht nur, was auf dem Server einzurichten ist.

**Ein Eintrag im Zeitplan, mehr nicht.** Der Abgleich ist ein
Kommandozeilenskript, keine Adresse – diese Installation ist die **anrufende**
Seite, es gibt niemanden von außen, der etwas aufrufen könnte:

```bash
*/2 * * * *  php /pfad/zur/zeiterfassung/scripts/portal_sync.php >/dev/null
```

Zwei Minuten sind die Vorgabe und keine Vorschrift: Sie entscheiden nur, wie
lange ein Antrag im Briefkasten der Website liegt, bevor er hier ankommt. Das
Skript sperrt sich selbst gegen Überschneidung, ein zu enger Takt kann also
nichts kaputtmachen.

**Was der Server dafür können muss:** ausgehende HTTPS-Verbindungen zur
Website. Eingehend wird nichts gebraucht, kein Port, keine Portweiterleitung,
keine feste Adresse – genau darum ist der Aufbau so herum gebaut
([Mitarbeiterportal](spezifikation_mitarbeiterportal.md), Abschnitt 2).

**Was passiert, wenn man den Eintrag vergisst?** Zwei Auffangnetze fangen den
Anfang ab, aber nicht den Betrieb:

- Die **Kopplung** gleicht sofort einmal ab. Die freigeschalteten Mitarbeiter
  stehen also unmittelbar danach auf der Website.
- **Nebenher** läuft der Abgleich am Ende einer beliebigen Anfrage mit –
  höchstens alle zwei Minuten und nur, wenn nicht ohnehin gerade ein Lauf war.

Beides greift jedoch nur, solange jemand arbeitet. Nachts, am Wochenende und in
den Betriebsferien passiert ohne Zeitplan **gar nichts**: Wer Samstagfrüh
Urlaub beantragt, dessen Antrag liegt bis Montag. Und wer zwischen Freischalten
und erstem Abgleich sein Konto einrichten will, bekommt auf der Website
»Kennung oder Aktivierungscode stimmt nicht« zu lesen, obwohl beides stimmt –
die Website kennt ihn schlicht noch nicht.

Verzögert sich ein Lauf, geht nichts verloren: Anträge sammeln sich auf der
Website, das Portal schreibt seinen Mitarbeitern sichtbar dazu, wie alt seine
Zahlen sind, und der nächste Lauf holt alles nach. In der Maske
**Mitarbeiterportal** steht, wann zuletzt abgeglichen wurde.
