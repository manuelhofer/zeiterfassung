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

Portable Tests und Linux-Integration sind lokal möglich. NTFS, Aufgabenplanung
und echter XAMPP-Neustart müssen zusätzlich auf einem Windows-Testsystem
abgenommen werden; eine Simulation allein bestätigt diese Teile nicht.

Primärquellen: [PHP-Prozessaufrufe](https://www.php.net/manual/en/function.proc-open.php),
[Apache unter Windows](https://httpd.apache.org/docs/2.4/en/platform/windows.html),
[Aufgabenidentitäten](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/new-scheduledtaskprincipal),
[XAMPP Windows](https://www.apachefriends.org/faq_windows.html).
