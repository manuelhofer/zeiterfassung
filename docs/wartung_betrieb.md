# Backup und Updates betreiben

Die Oberfläche liegt unter **Verwaltung → Backup und Updates**. Sie prüft `main`,
zeigt den angebotenen Commit und neue Datenbankmigrationen und stellt einen
Auftrag an den lokalen Dienst. Das Schließen des Browsers beendet den Auftrag
nicht. Das Recht `BACKUP_VERWALTEN` erlaubt eigenständige Sicherungen;
`UPDATE_VERWALTEN` erlaubt Prüfung und Installation einschließlich Sicherung.

## Einmalige Einrichtung

Dieses erste Wartungsupdate muss einmal auf Backend **und allen Terminals**
aus demselben zusammengeführten Git-Commit installiert werden. Ein alter
Terminalstand kennt den neuen Dienst noch nicht. Vorher vorhandene Dateien,
Konfigurationen und Datenbanken manuell sichern. Die Migration
`sql/14_migration_wartungsrechte.sql` einmal auf der Hauptdatenbank ausführen;
sie ist wiederholbar. Bei Neuinstallation sind die Rechte im initialen Schema.
Die bisherigen Migrationen bis 13 müssen bereits abgeschlossen sein.

Benötigt: PHP CLI ab 8.2 mit PDO-MySQL und ZIP, Git (Backend), OpenSSH-Client
(Backend), SSH-Server (Terminals), GNU tar, `mariadb` und `mariadb-dump`.
Der Wartungsrechner benötigt lesenden GitHub-Zugang; die Terminals benötigen
keinen GitHub-Zugang. Git-Anmeldung interaktiv vorher einrichten. Keine Tokens
in Repository-URLs eintragen.

1. Auf jedem Gerät ein Deploymentkonto einrichten, beispielsweise `zeit-deploy`.
   Es muss Anwendungscode ersetzen und den Webserver neu starten dürfen.
   Das Webkonto darf Code und Wartungskonfiguration ausschließlich lesen.
   Der normale Webzugriff braucht weiterhin die bisherigen Schreibrechte für
   lokale Einrichtung und Uploads. Insbesondere darf das Webkonto die Datei
   `config/wartung.local.php` weder ändern noch über einen schreibbaren
   Elternordner ersetzen: nach der Kopplung `config/` entsprechend absichern.
2. Externe Ordner anlegen, beispielsweise `/var/lib/zeiterfassung-wartung`
   (Deploymentkonto, Gruppe `www-data`, Modus 0750) und
   `/var/backups/zeiterfassung` (Deploymentkonto, Modus 0700). Keine Ordner
   unter dem Webroot und keine Symlinks verwenden. Sicherungsordner nie
   durch Apache ausliefern. Es gibt keine automatische Backup-Löschung.
3. `config/wartung.local.php.example` nach `config/wartung.local.php` kopieren
   und Pfade, Datenbank-Sicherungszugänge und Neustartbefehl anpassen.
   Die Rolle und Terminal-ID kommen weiterhin aus `config.local.php`.
   Auf Terminals nur den lokalen Offline-DB-Adminzugang hinterlegen;
   der Haupt-DB-Adminzugang bleibt ausschließlich am Backend.
4. Das Backupkonto braucht vollständigen Schema-/Datenzugriff, Trigger,
   Routinen, Events, eine globale Lesesperre für den Dump sowie Leserechte
   für die Sicherung der zugehörigen MariaDB-Benutzer und Grants.
   Der Dienst bricht bei fehlenden Rechten ab. Die eingeschränkten
   Terminalzugänge bleiben bestehen und werden weder erweitert noch rotiert.
5. Im Backend `terminals` für **jede aktive Terminal-ID** ausfüllen.
   SSH-Schlüssel und `known_hosts` außerhalb der Anwendung ablegen.
   Hostfingerprints auf den Geräten verifizieren und explizit eintragen;
   `StrictHostKeyChecking` bleibt eingeschaltet. `ssh` und `scp` müssen als
   Deploymentkonto ohne Passwortabfrage funktionieren. Alle Terminalpfade
   müssen zu deren tatsächlicher Wartungskonfiguration passen.
6. Betriebssystemdateien, die zur Wiederherstellung gehören, in
   `zusatz_pfade` aufnehmen: etwa die Apache-Konfiguration, Systemd-Units,
   Kiosk-Konfiguration und SSH-Schlüssel/Hostliste. Die Sicherung enthält
   die gesamte Anwendung einschließlich `.git`, unversionierter Dateien,
   Konfiguration und Uploads, aber kein Betriebssystemabbild. Nicht erfasste
   externe Symlinkziele führen zum Abbruch.
7. Auf jedem Gerät als Deploymentkonto initialisieren:

   ```sh
   php /var/www/zeiterfassung/scripts/wartung.php initialisieren
   ```

   Dabei müssen Arbeitskopie und Git-Commit übereinstimmen. Lokale
   Konfigurationen und Uploads sind von der Codeprüfung ausgenommen.
   Der Dienst prüft die DB-Basis und übernimmt den Ausgangsstand. Er führt
   dabei keine Bestandsmigrationen und keinen Git-Reset aus.
8. Dateigruppen nach der Initialisierung prüfen: Statusordner und die Dateien
   `version.json`/`anfragen.lock` müssen für das Webkonto lesbar sein;
   `auftraege/` und `auftraege/eingang.lock` müssen gruppenschreibbar sein
   (0770/0660). Der Webbenutzer darf keine anderen Statusdateien ändern.
   Neue Statusdateien übernimmt der Dienst mit seiner Gruppe `www-data`.
9. `neustart_befehl` auf jedem Gerät vorab testen: bei einem Deploymentkonto
   beispielsweise `sudo -n /usr/bin/systemctl restart apache2.service` mit
   einer ausschließlich für diesen Dienst erlaubten sudo-Regel. Bei PHP-FPM
   muss auch der passende FPM-Dienst neu gestartet werden. Damit werden
   alte PHP-Opcodes nach dem Dateiaustausch verworfen. Keine interaktive
   Passwortabfrage zulassen und keine beliebigen sudo-Befehle freigeben.
10. Auf dem Backend die Vorlagen in `scripts/wartung/` nach `/etc/systemd/system/`
    übernehmen, Benutzer/Gruppe/Pfade anpassen, `daemon-reload` ausführen und
    den Timer aktivieren. Der Timer läuft nur am Backend. Das identische
    CLI-Skript auf den Terminals wird über SSH gestartet.

Vor dem ersten Softwareupdate den eigenständigen Backup-Knopf benutzen und
Wiederherstellung auf einem separaten Rechner prüfen. Zur Diagnose lässt sich
`php scripts/wartung.php verarbeiten` einmal direkt als Deploymentkonto starten.

## Was beim Update passiert

- Git `main` abrufen, Nachfolgerbeziehung zum installierten Commit prüfen,
  Paket und DB-Migrationsvertrag prüfen. Kein Zurücksetzen der Historie.
- Installiert wird exakt der angebotene Commit. Ein inzwischen neuerer Commit
  auf `main` wird erst bei einer erneuten Prüfung angeboten.
- Aktive Terminals, Identitäten, Ausgangsversionen, lokale Änderungen und
  freien Speicher prüfen. Ein fehlendes Gerät verhindert den Gesamtauftrag.
- Anfragen an allen Geräten sperren und laufende Anfragen einschließlich
  Portal-Synchronisierung auslaufen lassen. Neue Buchungen erhalten HTTP 503.
- Backenddateien und SQL samt DB-Konten sichern und Prüfsummen kontrollieren.
- Dasselbe Paket an Terminals übertragen; deren Dateien und lokale
  Offline-Datenbanken sichern. Terminalbackups zum Backend zurückholen und
  erneut prüfen. Kein Hauptdatenbankdump wird auf ein Terminal eingespielt.
- PHP-Syntax und Paketdateien vorab prüfen. Neue Hauptmigrationen einmal
  zentral, neue Offline-Migrationen je lokaler DB ausführen. SQL-Erfolg wird
  zusätzlich mit dem Migrations-Prüfausdruck kontrolliert.
- Programmdateien ersetzen; gerätelokale Konfigurationen, Uploads und
  unverarbeitete Queueeinträge bleiben erhalten. Entfernte verwaltete
  Programmdateien werden entfernt; fremde Dateien nicht überschrieben.
- Neuen Code in einem frischen PHP-Prozess prüfen, Webdienst neu starten,
  Versionen und Datenbankzugriffe aller Geräte kontrollieren, dann freigeben.

`version.json` im externen Statusordner ist nach dem ersten Update der
maßgebliche installierte Stand. `.git/HEAD` wird nicht verändert; in dieser
verwalteten Installation anschließend kein `git pull`/`reset` ausführen.
Vorhandene lokale Änderungen werden vor dem Update erkannt und verhindern es.

Alle Schreiber müssen über die Anwendung oder den CLI-Start laufen.
Direkte Fremdskripte, die unabhängig in dieselbe Datenbank schreiben, vor einer
Wartung anhalten. Der SQL-Dump hält zusätzlich eine MariaDB-Lesesperre; das
kann andere Datenbanken auf demselben MariaDB-Server kurz blockieren.

## Neue DB-Migrationen veröffentlichen

`updates/migrationen.json` ist eine geordnete, nur erweiterbare Liste. Beispiel:

```json
{
  "datei": "sql/15_migration_beispiel.sql",
  "ziel": "haupt",
  "pruefung": "SELECT COUNT(*) = 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'beispiel' AND column_name = 'neu'"
}
```

`ziel` ist `haupt` oder `offline`; `pruefung` muss genau den erwarteten Erfolg
mit dem Wert 1 bestätigen. Alte Migrationen und ihre Einträge bleiben
unverändert. Änderungen am initialen oder Offline-Schema benötigen eine neue
passende Migration. Andere unbekannte SQL-Änderungen werden abgewiesen.
Schemaänderungen in PHP gehören ebenfalls in diesen Vertrag. Der Dienst kann
keine beliebigen fachlichen Änderungen im PHP-Code automatisch als DB-Änderung
erkennen. Neue Terminal-Leserechte müssen in einer geprüften Hauptmigration
explizit ergänzt werden; ein fehlender Grant darf nicht durch neue Passwörter
oder umfassendere Standardrechte verdeckt werden.

## Fehler und Wiederherstellung

Vor Beginn der Installation werden gesetzte Sperren bei Fehlern nach Möglichkeit
wieder aufgehoben. Ein nicht mehr erreichbares Gerät bleibt als ungeklärt
sichtbar. Nach Beginn einer Migration oder Dateiinstallation bleiben die
Sperren bestehen. Keine automatische SQL-Wiederholung und kein automatisches
Rollback nach DDL: MariaDB kann Schemaänderungen bereits festgeschrieben haben.
Ein Stromausfall wird über `aktiv.json` beim nächsten Dienstlauf erkannt.

1. Timer stoppen. Auftrags-ID, `status.json`, `lauf-<id>.json`,
   `installation-<id>.json` und Sicherungspfad festhalten. Auch alle
   Terminalzustände prüfen. Nicht nur das Backend freigeben.
2. `manifest.json` und alle SHA-256-Prüfsummen kontrollieren, beispielsweise
   mit `WartungBackup::pruefen()` über PHP CLI. Die Dateisicherung lässt sich
   zunächst gefahrlos in einen **leeren separaten Zielordner** entpacken:

   ```sh
   tar -xzf /var/backups/zeiterfassung/AUFTRAG/dateien.tar.gz -C /pfad/zum/leeren/restore
   ```

   Das Archiv enthält die ursprünglichen Pfade ohne führenden Schrägstrich.
   Enthalten sind auch lokale Konfigurationen und der vorherige Versionsbeleg.
3. Haupt-SQL in eine neue, leere Wiederherstellungsdatenbank importieren und
   prüfen. Die `*-benutzer.sql` enthalten Zugangshashes und Grants; nur
   kontrolliert auf einem separaten Server einspielen. Bestehende Accounts
   niemals blind überschreiben. Offline-Dumps gehören zu genau dem Terminal,
   dessen `manifest.json` die ID nennt.
4. Bei einer tatsächlichen Rückkehr zum alten Stand Backend **und alle
   Terminals** auf die zusammengehörigen Dateien, Versionsbelege und Datenbanken
   zurücksetzen. Dateien, die erst das Update angelegt hat, anhand des Plans
   entfernen. Nicht einfach nur das Archiv über den Teilstand entpacken.
   Die unverarbeiteten Offline-Queues müssen mit zurückkehren; keine leeren
   Schemas über diese Daten legen. Gerätewerte und DB-Verbindungen prüfen.
5. Webdienste neu starten und auf jedem Gerät
   `php scripts/wartung.php gesundheit` ausführen. Erst nach zusätzlicher
   fachlicher Kontrolle der wiederhergestellten Daten die zur Auftrags-ID
   gehörenden `pause.json` auf **allen** Geräten sowie `aktiv.json` am Backend
   entfernen und den Timer wieder starten. Keine automatische Freigabe eines
   unvollständig wiederhergestellten Verbunds.

Sicherungen enthalten Personendaten und Zugangsdaten. Sie bleiben unter
Dateirechten 0700/0600 und gehören zusätzlich auf einen geschützten separaten
Datenträger. Dateiprüfsummen und ein lesbarer Dump ersetzen keinen regelmäßigen
Wiederherstellungstest.

## Wiederholbare lokale Prüfung

`python3 scripts/tests/wartung_integration.py` erstellt ausschließlich eigene
Testdaten in einem neuen temporären Verzeichnis. Es prüft unter anderem einen
kompletten Ablauf über echte lokale SSH-Verbindungen mit zwei Terminals,
Datei-/SQL-Wiederherstellung, HTTP-Formularschutz und Migrationsfehler. Eigene
DB-/SSH-/HTTP-Testprozesse werden am Ende beendet. Das Testverzeichnis mit
`ergebnis.json` bleibt zur Diagnose bestehen. Der Webserver-Neustart wird im
Test durch `true` ersetzt; echter Apache/FPM-Neustart, reale Geräte und
Wiederherstellung auf anderer Hardware gehören zur Geräteabnahme.
