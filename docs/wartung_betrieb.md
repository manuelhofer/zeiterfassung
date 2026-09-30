# Backup und Updates

## Bedienung

Öffne **Verwaltung → Backup und Updates**.

- **Backup erstellen** sichert das Backend und alle aktiven Terminals.
- **Nach Updates suchen** prüft den aktuellen Stand von `main` und zeigt, ob
  dabei auch die Datenbank aktualisiert wird.
- Ist eine neue Version verfügbar, startet **Jetzt aktualisieren** den gesamten
  Ablauf: prüfen, sichern, Backend aktualisieren, Terminals aktualisieren,
  gemeinsam freigeben.

Es gibt keine zusätzliche Wartungseinrichtung. Der normale Installer bereitet
Dienst, Ordner, Schlüssel und Sicherungszugriff vor. Terminals werden nach der
gewöhnlichen Kopplung automatisch erkannt. Keine SSH-Schlüssel, zusätzliche
Geräteliste oder Wartungskonfigurationsdatei ausfüllen.

Während einer Sicherung oder Aktualisierung sind Buchungen vorübergehend
angehalten. Die Seite zeigt den Fortschritt und lädt sich selbst erneut.
Der Browser darf geschlossen werden; der Dienst arbeitet weiter.

Ein nicht erreichbares Gerät wird mit seinem Namen angezeigt. Bei einem
unterbrochenen Update steht ausdrücklich „Wartung unterbrochen“. Nach bereits
begonnenen Änderungen bleiben Buchungen gesperrt, bis die Betreuung den
Zustand geprüft hat. Ein fehlender Dienst nimmt keinen Auftrag an; ein länger
als eine Minute ungestarteter Auftrag verfällt und läuft nicht später heimlich.

Sicherungen bleiben auf dem Server erhalten. Die technischen Details nennen
den Ordner. Terminal-Sicherungen liegen zusätzlich unter `terminals/<ID>/` im
zugehörigen Backendbackup. Es wird nichts automatisch gelöscht.

## Normale Installation und erster Einsatz

Der unterstützte automatische Weg ist eine native Installation auf einem
Debian-/Raspberry-Pi-OS-Gerät mit lokaler MariaDB, Apache und PHP-FPM ab PHP 8.2:

- Backend: `sudo bash scripts/installieren.sh`
- Terminal: der bisherige `scripts/terminal/install_terminal.sh`, danach die
  normale Kopplung mit Serveradresse und Kopplungscode.

Ein alter Stand ohne diese Fähigkeit benötigt zuerst diese normale
Softwareauslieferung auf jedem Gerät. Ein Webknopf kann den noch fehlenden
Systemdienst nicht selbst installieren. Die bisherigen fachlichen
Bestandsmigrationen bis 13 müssen abgeschlossen sein; die neuen Migrationen
14/15 und Wartungsrechte werden automatisch vorbereitet. Vor der ersten
Übernahme des Backends entsteht bereits eine Sicherung.

Das Backend verwendet das `origin` seiner Installation. Das Projekt ist
öffentlich erreichbar und benötigt zum Lesen keine GitHub-Anmeldung.
Terminals beziehen alle Pakete vom Backend und brauchen keinen GitHub-Zugang.
Für abweichende private Repositories oder externe DB-Server gelten deren
Zugangsanforderungen; das ist nicht der automatisch eingerichtete Standard.

Ein berechtigter Benutzer sieht die Knöpfe direkt. `BACKUP_VERWALTEN` erlaubt
Sicherungen, `UPDATE_VERWALTEN` zusätzlich Prüfung und Update. Die bestehenden
Adminrechte werden um diese Rechte ergänzt; keine neue Benutzerrolle nötig.

## Was automatisch eingerichtet wird

Neben dem Programmordner entstehen `.zeit-wartung-<Kennung>` für Status und
`.zeit-wartung-<Kennung>-sicherungen` für Backups. Sie liegen außerhalb der
Anwendung und des Webroots. Der Pfad ergibt sich aus dem Installationspfad.
Der gleichnamige Systemd-Dienst mit Timer läuft auf Backend und Terminals.

Der Dienst besitzt die Rechte zum Sichern, Ersetzen und Neustarten. Webprozesse
bekommen nur den begrenzten Auftragseingang. Lokale PHP-Konfiguration wird vom
Dienst als Webbenutzer gelesen, nicht mit Systemrechten ausgewertet. Private
Datenbankzugänge und Signaturschlüssel liegen in einem geschützten Unterordner.

Die Terminalverteilung verwendet die schon vorhandene zentrale DB-Verbindung.
Jeder Terminalbenutzer sieht nur seine eigenen Aufträge und Dateiteile. Das
Backend signiert Aufträge; das Terminal prüft Signatur, Geräte-ID, Ablaufzeit
und bereits bearbeitete Aufträge. Die Serveridentität wird bei der ersten
Verbindung über den vorhandenen Kopplungskanal gespeichert. Ein abweichender
Schlüssel stoppt die Wartung und wird nicht still übernommen.

## Sicherungs- und Updateumfang

Gesichert werden alle Anwendungsdateien einschließlich `.git`, lokaler
Konfiguration, Uploads und unversionierter Dateien sowie SQL-Daten, zugehörige
DB-Benutzer/Grants und private Wartungsdaten. Am Terminal kommt die lokale
Offline-Datenbank einschließlich offener Queue und Mitarbeiterspiegel dazu.
Es ist eine Anwendungssicherung, kein vollständiges Betriebssystemabbild.
Externe Symlinkziele werden nicht stillschweigend als mitgesichert ausgegeben.

Zuerst wird das Backend gesichert. Danach verteilt es dasselbe geprüfte Paket
an alle aktiven Terminals und sichert auch deren Daten vor der Installation.
Prüfsummen und lesbare Archive werden kontrolliert. Die Hauptdatenbank wird
zentral einmal migriert, jede Offline-Datenbank an ihrem Terminal. Lokale
Konfigurationen, Uploads und unverarbeitete Buchungen bleiben erhalten.

Installiert wird genau der angebotene Commit, auch wenn `main` inzwischen
weitergelaufen ist. Lokale Codeänderungen oder unterschiedliche Ausgangsstände
verhindern ein Update. `version.json` im externen Statusordner belegt den
installierten Stand; `.git/HEAD` bleibt unverändert. In einer verwalteten
Installation deshalb keine nachträglichen `git pull`/`reset` ausführen.

Nach Syntax-, Datenbank- und Versionsprüfung werden Webserver und PHP-FPM neu
gestartet. Erst nach gemeinsamem Erfolg werden Buchungen freigegeben.
Transportdaten werden anschließend aus der DB entfernt, Sicherungen bleiben.
Unabhängige Fremdskripte mit direktem DB-Schreibzugriff sind vor Wartung
anzuhalten. Die Lesesperre des Dumps kann andere DBs desselben Servers kurz
blockieren.

## Neue Datenbankmigrationen veröffentlichen

`updates/migrationen.json` ist eine geordnete, nur erweiterbare Liste:

```json
{
  "datei": "sql/16_migration_beispiel.sql",
  "ziel": "haupt",
  "pruefung": "SELECT COUNT(*) = 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'beispiel' AND column_name = 'neu'"
}
```

`ziel` ist `haupt` oder `offline`; die Prüfung muss den erwarteten Erfolg mit
1 bestätigen. Alte Migrationen und Einträge bleiben unverändert. Änderungen
am initialen oder Offline-Schema brauchen eine passende neue Migration.
Unbekannte SQL-Änderungen werden abgewiesen. Schemaänderungen in PHP gehören
ebenfalls in diesen Vertrag: beliebige PHP-Logik lässt sich nicht zuverlässig
als DB-Änderung erkennen. Neue Terminalrechte gehören in eine Hauptmigration.

## Fehlerbehebung und Wiederherstellung durch die Betreuung

Vor der ersten Installation werden Sperren bei Fehlern soweit möglich wieder
aufgehoben. Nach Beginn von SQL- oder Dateiänderungen bleiben sie bestehen.
Keine automatische Wiederholung oder Rücknahme von DDL: MariaDB kann einzelne
Änderungen bereits festgeschrieben haben. Ein Stromausfall hinterlässt einen
sichtbaren unterbrochenen Auftrag.

1. Timer und Dienst auf **allen Geräten** stoppen. Auftrags-ID, `status.json`,
   `lauf-<ID>.json`, `installation-<ID>.json` und Sicherungspfad festhalten.
   Private Fehlerdetails liegen unter `privat/fehler-<ID>.json` am Backend.
2. `manifest.json` und SHA-256-Prüfsummen mit `WartungBackup::pruefen()` über PHP
   CLI kontrollieren. Archive zuerst in einen leeren separaten Ordner
   entpacken. Sie enthalten die ursprünglichen Pfade ohne führenden `/`.
3. SQL in neue, leere Wiederherstellungsdatenbanken importieren und prüfen.
   Die `*-benutzer.sql` enthalten Zugangshashes und Grants: nur auf einem
   separaten Server kontrolliert einspielen, keine bestehenden Konten blind
   überschreiben. Jeder Offline-Dump gehört zum Terminal seiner Manifest-ID.
4. Backend und alle Terminals gemeinsam auf die passenden Dateien,
   Versionsbelege und DBs zurücksetzen. Neu hinzugekommene Dateien anhand des
   Plans entfernen; ein Archiv allein über den Teilstand zu entpacken reicht
   nicht. Offene Offline-Queues, lokale Einstellungen und private Schlüssel
   müssen mit zurückkehren.
5. In der wiederhergestellten Hauptdatenbank alte Transportaufträge entfernen:
   `DELETE FROM wartung_befehl; DELETE FROM wartung_dateiteil;` und die
   Heartbeats mit `UPDATE wartung_geraet SET gesehen=NULL;` zurücksetzen.
   Die Agenten bleiben bis zum Abschluss gestoppt, damit keine alten Aufträge
   aus einem Dump wieder abgeholt werden.
6. Webdienste neu starten und auf jedem Gerät
   `php scripts/wartung.php gesundheit` prüfen. Nach fachlicher Kontrolle die
   zugehörigen `pause.json` auf allen Geräten und `aktiv.json` am Backend
   entfernen. Erst jetzt die Timer wieder starten.

Sicherungen enthalten Personendaten und Zugangsdaten. Sie bleiben mit privaten
Dateirechten geschützt und gehören zusätzlich auf einen geschützten separaten
Datenträger. Ein lesbarer Dump ersetzt keinen Wiederherstellungstest.

## Wiederholbare lokale Prüfung

`python3 scripts/tests/wartung_integration.py` startet eine private MariaDB,
eigene Git- und HTTP-Testinstanzen mit synthetischen Daten. Geprüft werden die
automatische Vorbereitung, normale Terminal-Kopplung im Browserformular,
Gerätetrennung/Signaturen, die sichtbaren Knöpfe, vollständiges Update mit zwei
Terminals, Datei-/SQL-Restore, abgelaufene Aufträge sowie Ausfallfälle.
Alle eigenen Prozesse werden beendet; `ergebnis.json` bleibt im Testordner.

Systemd-Takte werden im Labor durch lokale Prozesse, Webserver-Neustarts durch
`true` ersetzt. Native Paketinstallation, Systemd-Dateirechte, Apache/FPM,
echte Hardware und Wiederherstellung auf anderer Hardware bleiben Teil der
Geräteabnahme. Der Labortest allein bestätigt diese Schritte nicht.
