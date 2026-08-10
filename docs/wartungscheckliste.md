# Wartungscheckliste

Diese Checkliste dient als sichere Basis vor und nach Änderungen. Sie ersetzt
keine fachliche Abnahme, hilft aber dabei, bestehende Funktionalitaet bewusst
zu schuetzen.

## Grundsatz

- Keine Fachlogik ändern, wenn nur Dokumentation oder Einstiegspunkte
  verbessert werden sollen.
- Vor größeren Änderungen zuerst den aktuellen Stand sichern.
- Nach jeder Änderung mindestens Syntaxcheck und die passenden manuellen
  Kernablaeufe prüfen.

## Technischer Schnellcheck

Aus dem Projektverzeichnis (siehe `docs/lokale_entwicklungsumgebung.md`):

```bash
git status --short
php -v
```

Alle PHP-Dateien auf Syntaxfehler prüfen:

```bash
find . -name '*.php' -not -path './.git/*' -print0 \
  | xargs -0 -n1 php -l \
  | grep -v '^No syntax errors' \
  || echo 'OK: alle PHP-Dateien syntaktisch sauber'
```

Nur die geänderten Dateien prüfen (schneller, im Alltag meist ausreichend):

```bash
git diff --name-only --diff-filter=ACM | grep '\.php$' | xargs -r -n1 php -l
```

Nach dem Klicktest zusätzlich das Log auf PHP-Meldungen ansehen – hier tauchen
Deprecations auf, die im Browser unsichtbar bleiben:

```bash
sudo tail -50 /var/log/httpd/error_log
```

## Manuelle Kernablaeufe

Nach Änderungen an Backend, Auth, Session, Rechten oder Layout:

- Login als Admin
- Dashboard öffnen
- Mitarbeiterliste öffnen
- Rollen/Rechte öffnen
- Monatsreport HTML öffnen
- Monatsreport PDF erzeugen
- Urlaub beantragen und Liste öffnen
- Urlaub-Genehmigungsliste öffnen, sofern Berechtigung vorhanden

Nach Änderungen am Terminal:

- Terminal-Startseite öffnen
- RFID/Login testen
- Kommen buchen
- Gehen buchen
- Auftrag starten
- Auftrag stoppen
- Auto-Logout prüfen
- Health-Endpunkt `public/terminal.php?aktion=health` prüfen

Nach Änderungen an den Installationsskripten (`scripts/terminal/`):

- Debian-12-Container mit systemd starten, die vier Skripte der Reihe nach
  laufen lassen, jedes ein zweites Mal (Idempotenz)
- `selbsttest.sh --ohne-scan` – der Rückgabewert muss zum Ergebnis passen
- Nach `install_peripherie.sh`: Passwort in `config/geraet.local.php` muss
  dasselbe geblieben sein, sonst ist die Offline-Queue tot

Nach Änderungen an der Terminalverwaltung (Backend):

- Terminalliste öffnen, Spalte *Kopplung* prüfen
- Kopplungscode erzeugen, am Terminal einlösen
- Entkoppeln: Datenbankbenutzer muss verschwinden, das Gerät danach nicht mehr
  buchen können

Nach Änderungen an Offline-Queue oder Datenbankverbindung:

- Queue-Admin öffnen
- Status `offen`, `fehler`, `verarbeitet` ansehen
- Terminal bei erreichbarer Hauptdatenbank testen
- Terminal mit nicht erreichbarer Hauptdatenbank nur kontrolliert testen
- Wiederanlauf der Queue prüfen

## Bereiche mit besonderer Vorsicht

- `public/index.php` und `public/terminal.php`
- `controller/TerminalController.php`
- `core/OfflineQueueManager.php`
- `services/AuthService.php`
- `views/layout/header.php`
- Monatsreport/PDF-Services

Diese Bereiche funktionieren aktuell, sind aber zentral für viele Ablaeufe.
Hier nur kleine, gut prüfbare Schritte machen.
