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

Die erste Ausführung benötigt eine einmalige Einrichtung des Dienstes und
seiner Dateirechte. Die Terminalverteilung verwendet administrativ konfigurierte
SSH-Verbindungen im Betriebsnetz mit Schlüsselanmeldung und Hostprüfung.
Das Backend überträgt die Pakete; die Terminals benötigen keinen GitHub-Zugang.
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
Die Hardwareabnahme einschließlich Apache/FPM bleibt nach der einmaligen
Einrichtung erforderlich.
