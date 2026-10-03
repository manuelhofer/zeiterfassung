# Backup und Updates unter Windows/XAMPP

## Zielbild

Dasselbe Repository unterstützt das Backend unter Debian und Windows/XAMPP
(PHP mindestens 8.2); die vorhandenen Backup-/Updateknöpfe und die Verteilung
an Linux-Terminals bleiben identisch. XAMPP und Git für Windows sind bereits
installiert. Ein einmaliger Installer als Administrator übernimmt Apache,
MariaDB, Dateirechte und Aufgabenplanung ohne Wartungsparameter.

## Abnahme

- Linux behält Systemd, tar-Sicherungen und den vorhandenen Terminalablauf.
- Windows erkennt XAMPP, verwendet dessen PHP-/MySQL-Programme und schützt
  Programmcode, private Schlüssel und Sicherungen durch NTFS-Rechte.
- Der Systemdienst wertet webbeschreibbare PHP-Konfiguration auch unter Windows
  ausschließlich als eingeschränkter Webbenutzer aus; SYSTEM erhält nur JSON.
- Windows startet nach einem Neustart ohne angemeldeten Benutzer, prüft main,
  sichert Dateien/SQL vor Änderungen und startet Apache nach dem Update neu.
- Windows-Dateipfade, Leerzeichen, ZIP-Sicherungen und gesperrte Dateien werden
  berücksichtigt; ein Fehler nach Änderungsbeginn hält die Wartungssperre.
- Installer-Wiederholung erhält DB, Schlüssel und installierten Versionsbeleg;
  gemischte Windows-Backend-/Linux-Terminal-Installationen verwenden unverändert
  den signierten Datenbanktransport.

## Umsetzung und Prüfgrenze

Windows-Aufgaben: Wartungsprozess unter SYSTEM, Konfigurationsauswertung unter
LocalService; Apache läuft ebenfalls unter LocalService. Status und Auftragseingang
sind lesbar beziehungsweise gezielt beschreibbar, private Daten bleiben SYSTEM
und Administratoren vorbehalten. Vor Dateiänderungen werden Windows-Sperren
geprüft; bei einem späteren Konflikt wird kein Erfolg gemeldet.
Windows-Sicherungen verwenden ZIP mit Pfadzuordnung im Manifest; SQL und
Prüfsummen bleiben identisch. Wiederherstellung erfolgt weiterhin kontrolliert
auf separatem Gerät; keine automatische DDL-Rücknahme.

## Prüfergebnis vom 03.10.2026

[Native Windows-Abnahme](https://github.com/manuelhofer/zeiterfassung/actions/runs/37148692919):
Windows Server 2022, Windows PowerShell 5.1 und frisches XAMPP 8.2.12 mit PHP
8.2.12; 17 Ablaufprüfungen und 20 portable Prüfungen erfolgreich. Tatsächlich
ausgeführt: Installer, NTFS-/Aufgaben-/Apache-Konfiguration, Webaufruf,
ZIP-/SQL-Sicherung und getrennte Wiederherstellung, Update mit neuer SQL-
Migration, eigener Skriptaustausch, Apache-Neustart und Installer-Wiederholung
unter Erhalt von Konfiguration, Schlüssel und Version. Eine vorhandene
Dateisperre verhindert SQL und wird ohne dauerhafte Buchungssperre gemeldet;
nach dem Schließen gelingt dasselbe Update.

Linux mit PHP 8.5: 41 Integrationsprüfungen mit privater Testdatenbank und zwei
normal gekoppelten Testterminals sowie 11 portable Prüfungen erfolgreich.
ZIP am Backend und tar an den Terminals verwenden denselben Transport;
Update, Migrationen, offene Offline-Queues und DDL-Fehlerfälle bleiben geprüft.
Testprozesse beendet, keine echten Mitarbeiterdaten verwendet.

Der Windows-Test läuft auf einer isolierten, anschließend verworfenen VM;
auf dem Benutzergerät wurde nichts installiert. Kaltstart des Benutzergeräts,
echte LAN-Verbindung Windows-Backend/Linux-Terminal sowie RFID-/Touch-Hardware
bleiben Geräteabnahme. Windows-Terminalinstallation ist nicht Teil dieser
Backend-Erweiterung.

Primärquellen: [PHP-Prozessaufrufe](https://www.php.net/manual/en/function.proc-open.php),
[Apache unter Windows](https://httpd.apache.org/docs/2.4/en/platform/windows.html),
[Aufgabenidentitäten](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/new-scheduledtaskprincipal),
[Windows-Dateifreigaben](https://learn.microsoft.com/en-us/windows/win32/api/fileapi/nf-fileapi-createfilew),
[XAMPP Windows](https://www.apachefriends.org/faq_windows.html).
