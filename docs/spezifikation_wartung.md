# Backup und Updates

Beauftragt am 29.09.2026: gemeinsamer Quellcode für Backend und Terminals,
Updatequelle ist `main`. Die Oberfläche bietet „Nach Updates suchen“,
„Backup erstellen“ und „Update durchführen“ mit Protokoll und Geräteergebnis.

## Ablauf

1. Genau einen Git-Commit prüfen und dessen Datenbankänderungen anzeigen.
2. Installationszustand und alle aktiven Terminals prüfen; unbekannte oder
   nicht erreichbare Geräte verhindern eine als vollständig bezeichnete Wartung.
3. Schreibzugriffe an allen beteiligten Installationen pausieren und laufende
   Anfragen auslaufen lassen. Backend samt Dateien, lokalen Einstellungen,
   Uploads, Datenbank und zugehörigen Datenbankzugängen sichern und prüfen.
4. Erst danach dasselbe Programmpaket vom Backend an die Terminals übertragen;
   dort vor der Installation Dateien und lokale Offline-Datenbank sichern.
5. Hauptdatenbankmigrationen zentral einmal ausführen, Backend aktualisieren,
   danach denselben Programmstand auf den Terminals aktivieren. Gerätelokale
   Konfigurationen und unverarbeitete Offline-Buchungen erhalten.
6. Ergebnisse prüfen und Schreibzugriffe erst nach gemeinsamem Erfolg freigeben.

Der Backup-Knopf benutzt dieselbe Sicherung ohne anschließende Installation.
SQL für Neuinstallationen wird niemals als Migration ausgeführt. Unbekannte
oder geänderte bereits verwendete Migrationen verhindern ein automatisches
Update. Ein fehlgeschlagenes Update bleibt sichtbar und erhält seine Sicherung.

## Ausführung

Ein PHP-CLI-Wartungsdienst verarbeitet lokale Aufträge außerhalb des
HTTP-Aufrufs. Es läuft höchstens ein Auftrag zugleich. Konfiguration und
Sicherungen liegen außerhalb des Webroots; Sicherungen auch außerhalb des
Programmverzeichnisses. Keine automatische Löschung alter Sicherungen.

Die normale Installation richtet Dienst und Dateirechte automatisch ein.
Die Terminalverteilung verwendet den bereits gekoppelten Datenbankzugang.
Ein lokaler Agent holt ausschließlich die für sein Gerät bestimmten Aufträge
und Dateiteile ab; das Backend stellt sie bereit. Getrennte, auf den
angemeldeten DB-Benutzer begrenzte Views schützen Geräteaufträge und Backups.
Zusätzliche SSH-Zugänge, Gerätelisten oder GitHub-Zugänge am Terminal entfallen.
Der identische CLI-Code führt lokal die Rolle aus der Konfiguration aus.

Die Sicherung umfasst die Anwendung, ihre Datenbanken und ausdrücklich
konfigurierte zusätzliche Pfade (etwa Apache-/Kiosk-Konfiguration), kein
vollständiges Betriebssystemabbild. Externe Dateiverweise werden nicht still
als mitgesichert ausgegeben. Ein Wiederherstellungstest ist Teil der Abnahme.

## Akzeptanz

- Ein berechtigter Benutzer kann unabhängig von Updates eine geprüfte Sicherung
  erstellen; ein fehlgeschlagener Dump erzeugt keine Erfolgsmeldung.
- Eine Git-Prüfung benennt angebotenen Commit und DB-Auswirkung ohne Installation.
- Ein Update wird nur nach erfolgreicher Sicherung installiert und verteilt.
- Bei zwei Terminals erhalten beide denselben Programmstand; ihre lokalen
  Konfigurationen und Queueeinträge bleiben erhalten.
- Bei Speicher-, Netzwerk-, Migrations- oder Installationsfehlern bleibt der
  konkrete Fehler samt Sicherung sichtbar und es erscheint kein Gesamterfolg.
- CSRF und eigene Wartungsrechte schützen die Oberfläche; kein URL-Parameter
  bestimmt Shellbefehle, Repositoryadressen oder Dateipfade des Dienstes.

Geräteabnahme und Wiederherstellung auf einem anderen Rechner bleiben eigene
Prüfschritte; lokale Tests dürfen diese nicht als erledigt ausgeben.

## Umsetzung

Backendmaske, CLI-Dienst, Paketvertrag und wiederholbare Integrationstests sind
umgesetzt. Einrichtung und Wiederherstellung: [wartung_betrieb.md](wartung_betrieb.md).
Die Hardwareabnahme einschließlich Apache/FPM und Systemd bleibt nach der
normalen Installation erforderlich.

## Bedienung ohne zusätzliche Einrichtung (beauftragt 30.09.2026)

Die normale Installation richtet lokale Ordner, Dienst, Sicherungszugriff,
Versionsbeleg und nötige Migrationen automatisch ein. Auf Terminals läuft der
Agent schon vor der normalen Kopplung und übernimmt anschließend deren
vorhandenen Zugang; keine zweite Kopplung und keine Wartungskonfigurationsmaske.
Bestehende Installationen ohne Agent brauchen einmal die normale Auslieferung
dieses neuen Installationsstands; die Webanwendung erhält dafür keine freien
Systemrechte.

Der Benutzer sieht „Backup erstellen“, „Nach Updates suchen“ und bei einem
Angebot „Jetzt aktualisieren“. Ein gemeinsamer Fortschritt nennt Prüfung,
Sicherung, Backend und Terminals. Datenbankänderungen werden in Alltagssprache
angekündigt; Protokolldetails sind eingeklappt. Ein fehlender Dienst wird vor
Auftragsannahme erkannt; keine unendlich wartenden Aufträge. Die Statusseite
bleibt während der Wartung ohne DB-Schreibzugriffe verständlich sichtbar.

Abnahme: Eine normale Neuinstallation plus gewöhnliche Terminal-Kopplung
muss ohne bearbeitete Wartungsdatei, SSH-Einrichtung, zusätzliche Rechtevergabe
oder eigenen CLI-Initialisierungsschritt ein Backup und ein gemeinsames Update
aus der Oberfläche ermöglichen. Auch Ausfallmeldungen und der Erststart werden
getestet, nicht nur ein vorbereiteter laufender Dienst.
