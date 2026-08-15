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

## Nach flächigen Textänderungen (Pflicht)

Gemeint ist jede Änderung, die viele Dateien gleichzeitig auf eine
Schreibweise bringt – Umlaute, Begriffe, Rechtschreibung. Sie ist der einzige
Änderungstyp in diesem Projekt, der **stumm** kaputtgeht: `php -l` bleibt
sauber, die Seite lädt, und trotzdem tut ein Knopf nichts mehr, weil sein Wert
nicht mehr zu der `if`-Bedingung passt, die ihn liest.

Das ist zweimal passiert (P-2026-08-14-08 → P-2026-08-15-01/-02/-03), obwohl
der Fall in P-2026-08-10-19 vollständig beschrieben stand. Deshalb hier drei
Suchläufe statt eines weiteren Absatzes. **Erwartete Ausgabe: jeweils keine.**

Ein Formularwert ist ein Bezeichner, kein Text – kein `value` bekommt einen
Umlaut:

```bash
grep -rInE '<(input|button|option)[^>]*value="[^"<]*[äöüßÄÖÜ]' --include='*.php' controller views public
```

Namen mit Unterstrich sind Spalten, Array-Schlüssel oder Felder – auch dort,
wo ein Kommentar sie nur zitiert:

```bash
grep -rInoE '[A-Za-zäöüßÄÖÜ]*[äöüß][A-Za-zäöüß]*_[A-Za-z_äöüß]+|[A-Za-z]+_[A-Za-z_]*[äöüß][A-Za-zäöüß_]*' --include='*.php' --include='*.js' --include='*.sql' controller views services core modelle public sql
```

Funktions- und Variablennamen bleiben ASCII, in PHP wie in JavaScript:

```bash
grep -rInE '(function|const|let|var)\s+[A-Za-z_$]*[äöüßÄÖÜ]|\$[a-zA-Z_]*[äöüßÄÖÜ][a-zA-Z_]*\s*=' --include='*.php' --include='*.js' controller views services core modelle public
```

Wird **Dokumentation** flächig umgeschrieben, kommt ein vierter Suchlauf dazu.
Im Fließtext stehen Bezeichner oft ohne Backticks – Commit-Betreffe in
Überschriften, Routennamen in einer Aufzählung. Sie sehen aus wie Text und
sind keiner. Der Vergleich gegen den Git-Verlauf findet es:

```bash
git log --format='%s' | sed -E 's/^P-[0-9-]{13} //' \
  | grep -E '^[a-z0-9]+(-[a-z0-9]+)+$' | sort -u \
  | while read -r s; do grep -qF "$s" docs/archiv/DEV_PROMPT_HISTORY.md \
      || echo "Betreff nicht mehr wörtlich im Verlauf: $s"; done
```

Vor der Änderung einmal laufen lassen und die Ausgabe aufheben – ein paar
Betreffe fehlen seit jeher. Danach darf **kein neuer** dazukommen.

**Was diese Suchläufe nicht finden:** Attribute und Werte, die erst zur
Laufzeit entstehen. Die Bildschirmtastatur des Terminals baut ihre Tasten in
JavaScript zusammen – `data-taste-wert="löschen"` stand in keinem Suchlauf und
war trotzdem da. Wer Terminal-Dateien angefasst hat, öffnet die Seite und
prüft die Tasten von Hand (siehe *Manuelle Kernablaeufe*).

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
- Bildschirmtastatur: ein Zeichen tippen, *Löschen* drücken, auf der
  Einrichtungsseite zusätzlich die Umschalttaste – diese Tasten hängen an
  Werten, die kein Suchlauf im Quelltext sieht
- Urlaub-Wizard: Schritt 1 → 2 → *Zurück*, und einmal ein Enddatum vor dem
  Startdatum (die Fehlermeldung muss erscheinen und *Weiter* blockieren)

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
