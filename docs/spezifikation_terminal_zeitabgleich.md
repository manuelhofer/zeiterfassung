# Gemeinsame Zeit für Backend und Terminal

## Ziel

Nach der normalen Kopplung stimmen Terminalanzeige und neue Buchungszeiten
automatisch mit dem Backend überein. Die lokale Browseruhr ist keine
Buchungsquelle. Grundlage ist die UTC-Zeit der zentralen Datenbank; bei den
normalen Linux-/Windows-Installern läuft sie auf dem Backendrechner.
Anzeige und Rohbuchungen verwenden weiterhin die PHP-Anwendungszeitzone,
standardmäßig Europe/Berlin, einschließlich Sommer-/Winterzeit.

## Akzeptanz

- Ein Terminal mit abweichender lokaler Uhr zeigt online die Backendzeit und
  speichert neue Kommen-/Gehen-/Auftragszeiten mit dieser Zeit.
- Die Anzeige läuft sekündlich weiter und gleicht sich alle 15 Sekunden neu ab,
  ohne eine Mitarbeiteraktion, Buchung oder Queue-Verarbeitung auszulösen.
- Bei einer Netzunterbrechung läuft die zuletzt abgeglichene Zeit auf dem
  gleichen Systemstart monoton weiter; die Offline-Queue bleibt nutzbar.
- Ein neuer Systemstart ohne erreichbaren Server verwendet die lokale Uhr;
  ohne Serverkontakt oder verlässliche Hardwareuhr lässt sich die tatsächliche
  Dauer eines ausgeschalteten Geräts nicht bestimmen.
- Das Terminal liest die Zeit direkt über seine bestehende Backend-Verbindung.
  Dafür sind kein Internetzugang, zusätzlicher Dienst oder neue DB-Rechte nötig;
  Betriebssystemuhr und Betriebssystem-Zeitdienste werden nicht verändert.
- Ablaufzeiten signierter Wartungsaufträge werden mit der zentralen Zeit
  erzeugt und geprüft, damit eine falsche Terminaluhr Backup/Update nicht verhindert.
- Bestehende Rohbuchungen und Queue-Zeitstempel bleiben unverändert; ein
  Abgleich repariert eine schon falsch datierte Buchung nicht rückwirkend.

## Grenzen und Prüfung

Eine abweichende Stunde kann nur die Browserzeitzone betreffen. Ein falsches
Datum des Rechners kann eine neue Buchung dagegen außerhalb von „Heutige
Zeiten“ speichern. Beides ist getrennt zu prüfen. Tests verwenden ausschließlich
synthetische Daten und eigene Instanzen. Eine falsche Referenzzeit und lokale
Uhrsprünge werden simuliert; die Uhr des Arbeitsplatzrechners wird nicht verstellt.
Zusätzlich werden Offline-Nachtragung, Datumsauswahl, Zeitumstellung und
unveränderte alte Buchungen geprüft. Die echte Geräteabnahme bleibt erforderlich.

## Wiederholbare Prüfungen

- `php scripts/tests/terminal_zeit.php`: Zeitquelle, Sommer-/Winterzeit und
  Offline-Cache, ohne echte DB oder Konfiguration.
- `node scripts/tests/terminal_uhr.js`: Anzeige, periodischer Abgleich,
  Netzunterbrechung und Auto-Logout bei falscher Browseruhr.
- `python3 scripts/tests/terminal_buchung.py /tmp/neuer-leerer-ordner`:
  privates MariaDB-/HTTP-Labor mit normaler Kopplung; Terminal-PHP hat zwei
  Tage falsche Zeit. Prüft echte Kommen-/Gehen-Buchungen, „Heutige Zeiten“,
  Offline-Nachtragung, Bestandsschutz und signierte Wartungsaufträge.
- Derselbe Laboraufruf mit `--vorher 2bab763` reproduziert den Fehler im alten
  Stand: Erfolg am Terminal, aber falsches Buchungsdatum und kein heutiger Eintrag.
  Benötigt lokale PHP-/MariaDB-/Git-/C-Werkzeuge und Berechtigung für private
  Testsockets; die echte Systemuhr und vorhandene Datenbanken bleiben unberührt.
