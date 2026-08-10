# Fachregeln: Terminal, Kiosk-UI, Offline-Queue, Kopplung

*Gilt für:* `public/terminal.php`, `controller/TerminalController.php`,
`controller/TerminalEinrichtungController.php`, `core/OfflineQueueManager.php`,
`views/terminal/*`.
*Herkunft:* Master-Prompt v13, Abschnitte 2.2, 6.2, 8, 15 und der
Kopplungs-Abschnitt.
*Vertiefung:* `docs/spezifikation_terminal_installation.md` (Installation und
Kopplung im Detail), `docs/rfid_reader_setup.md`,
`docs/terminal/rfid-ws_rollout.md`.

---

## 1. Was ein Terminal ist

Ein separater Linux-PC, der von selbst in einen Browser im **Kioskmodus**
startet: Touchscreen, keine normale Tastatur für den Anwender. Es hat **einen**
Leser/Scanner – entweder einen RFID-Leser (für Mitarbeiter-Chips und
Auftrags-/Maschinenchips) oder einen Barcode-Scanner.

Frueher stand hier „mit Window-Manager". Das trifft es seit dem Kiosk-Skript
nicht mehr: Auf dem Standardweg (Wayland, `cage`) gibt es keinen
Fenstermanager im herkoemmlichen Sinn – `cage` **ist** der Compositor und zeigt
genau ein Fenster. Nur der X11-Rückfall startet zusätzlich `openbox`, und
zwar aus genau diesem Grund: Ohne Fenstermanager bekommt der Browser dort sein
Vollbild nicht zuverlaessig. Wie das Geraet aufgesetzt wird, steht in
`docs/spezifikation_terminal_installation.md`, Abschnitt 7.

Das Terminal spricht **primaer** mit der Hauptdatenbank.

**Der Kontext der UI bestimmt, was ein eingelesener Code bedeutet:**

- Startbildschirm → Mitarbeiter-RFID (Login)
- „Auftrag starten" → Auftragsnummer
- „Maschine auswählen" → Maschinen-ID

## 2. Leser-Anbindung

**Standard (empfohlen):** Der Leser verhaelt sich wie eine **USB-Tastatur**
(„Keyboard-Wedge"). Er schreibt den Code in ein fokussiertes Eingabefeld und
sendet am Ende typischerweise ENTER. Die Web-App verarbeitet nur den
empfangenen String.

**Alternative (z. B. Raspberry Pi + RC522/MFRC522 am SPI):** Diese Module
liefern **keine** Tastatureingaben. Auf dem Terminal läuft dann ein lokaler
Adapterdienst (z. B. Python), der die UID ausliest und entweder als
Tastatureingabe einspeist (uinput/evdev) oder per HTTP/WebSocket über
localhost an die Terminalseite übergibt.

**Wichtig:** Für die Web-App bleibt die Schnittstelle gleich – „es kommt ein
Code als String an". Welche Hardware dahintersteckt, ist austauschbar und wird
je Terminal konfiguriert.

## 3. Tabelle `terminal` und Einstellungen

`id`, `name` (z. B. „Halle 1 – Fraeserei Terminal links"),
`standort_beschreibung`, `abteilung_id` (für den Maschinenfilter), `modus`
(`terminal` oder `backend`), `aktiv`, Timestamps.

Je Terminal einstellbar: welche Funktionen im Offline-Modus erlaubt sind und
welcher Timeout für den Auto-Logout gilt.

## 4. Inbetriebnahme: Kopplung statt Zugangsdaten

Ein neues Terminal bekommt seine Konfiguration **nicht** aus einer von Hand
angelegten Datei, sondern über eine **Kopplung** am Backend:

1. In der Terminalverwaltung wird zum Geraet ein **Kopplungscode** erzeugt –
   einmalig gültig, 30 Minuten, nur als Hash gespeichert.
2. Am Terminal werden Server-Adresse und Code eingegeben (Einrichtungsseite,
   erscheint automatisch bei fehlender `config/config.local.php`).
3. Das Terminal holt sich Terminal-ID, Zugangsdaten und Einstellungen selbst und
   schreibt seine `config.local.php`.

**Jedes Terminal erhaelt einen eigenen Datenbankbenutzer** mit eingeschraenkten
Rechten: kein `DELETE`, kein `DROP`, kein Zugriff auf Stundenkonto und
Lohnkorrekturen. Ein verlorenes Geraet wird durch Löschen dieses einen
Benutzers unschaedlich gemacht – ohne alle anderen Terminals anzufassen; in der
Terminalverwaltung ist das der Knopf **Entkoppeln**. Ein Geraet nur stillzulegen
(`aktiv = 0`) reicht dafür **nicht**: Das verhindert nur eine neue Kopplung,
der vorhandene Zugang bleibt gültig.

Damit kennt das Installationsskript **keine** Zugangsdaten; dasselbe Abbild
passt auf beliebig viele Geraete.

Eine Ausnahme gibt es: die **lokale Ausweichdatenbank** (Abschnitt 5). Sie
gehört der Maschine und nicht dem Backend, also kann die Kopplung sie nicht
liefern. `scripts/terminal/install_terminal.sh` legt sie samt eigenem Benutzer
an und schreibt ihre Zugangsdaten nach `config/geraet.local.php`; beim Koppeln
werden sie von dort in die `config.local.php` übernommen. Deshalb tragen beide
Dateien dasselbe Passwort – und deshalb sucht das Skript ein vorhandenes
Passwort zuerst in `config.local.php`: Wuerde es stattdessen ein neues erzeugen,
liefe das Terminal weiter, aber seine Queue wäre tot (P-2026-08-09-05).

Recht: `TERMINAL_VERWALTEN` (Erzeugen des Codes). Der Kopplungs-Endpunkt selbst
ist bewusst **ohne Anmeldung** erreichbar – ein frisches Geraet hat keinen
Benutzer, der Code **ist** der Nachweis.

Einzelheiten inklusive Rechteliste des Terminal-Benutzers:
`docs/spezifikation_terminal_installation.md`.

## 5. Offline-Fall und Injektions-Queue

Vor jeder Schreiboperation in die Hauptdatenbank wird die Verbindung geprüft.
Ist sie nicht erreichbar:

**Erlaubt bleibt**, was den Betrieb am Laufen haelt: Kommen/Gehen, Auftraege
starten/stoppen.
**Nicht erlaubt** sind komplexe Übersichten, Urlaubsantraege stellen oder
verwalten, umfangreiche Auswertungen.

### Offline-Stempeln ohne Mitarbeiter-Identifikation (RFID-only)

Im Offline-Modus gibt es **keine Anmeldung auf einen Mitarbeiter** und **keine
Prüfung gegen die Hauptdatenbank**. Der gescannte RFID-Code ist das einzige
Identifikationsmerkmal.

Ablauf: Anzeige „Offline-Modus – Bitte RFID scannen" → nach dem Scan erscheinen
**Kommen** und **Gehen** → beim Klick werden **RFID-Code + Zeitstempel +
Aktion** in die Offline-Queue geschrieben.

Optional darf ein Hinweis erscheinen („letzte Offline-Buchung war
Kommen/Gehen"), basierend auf dem letzten Queue-Eintrag dieser RFID – **aber
ohne harte Sperre**.

Der rohe SQL-Befehl in `db_injektionsqueue` muss die Mitarbeiter-ID **erst beim
Replay** über den RFID-Code auflösen:

```sql
INSERT ... SELECT id FROM mitarbeiter WHERE rfid_code='…' LIMIT 1;
```

Wird beim Replay keine passende Mitarbeiter-ID gefunden (RFID unbekannt), geht
der Eintrag auf `fehler` und die Abarbeitung stoppt – ein Admin muss die RFID
zuweisen oder den Eintrag verwerfen.

### Die Queue

Geschrieben wird in eine **lokale Sekundaerdatenbank** (Tabelle
`db_injektionsqueue`): `id`, `erstellt_am`, `status` (`offen` | `verarbeitet` |
`fehler`), **roher SQL-Befehl** zur späteren 1:1-Ausführung gegen die
Hauptdatenbank, sowie Metadaten (Mitarbeiter-ID, Art der Aktion).

Auf allen Terminalseiten wird in diesem Zustand gut sichtbar angezeigt:
**„Hauptdatenbank nicht aktiv – Admin anfordern"**.

### Wiederanlauf und Störungsmodus

Sobald die Hauptdatenbank wieder erreichbar ist, wird die Queue in zeitlicher
Reihenfolge abgearbeitet – **bis ein Fehler auftritt**.

Bei einem Fehler:

- Abarbeitung **sofort stoppen**.
- Terminal wechselt in den **Störungsmodus**: Meldung, **der konkrete
  SQL-Befehl**, und die Aufforderung, einen Admin zu rufen.
- Ein berechtigter Admin kann im Backend den Eintrag einsehen, den SQL-Befehl
  prüfen und entscheiden: „Ignorieren/Löschen" (die Injektion wird verworfen,
  die Queue läuft weiter) oder den Fehler notieren, den Eintrag löschen und
  den Sachverhalt manuell korrekt nachtragen.
- Erst nach Bearbeitung des Eintrags darf die Queue weiterlaufen.

## 6. Terminal-UI: Layout, Uhr, Texte

- **Bildschirmausnutzung:** ca. **97 %** der verfügbaren Flaeche (Breite und
  Hoehe), minimale Aussenraender, responsiv über den Viewport.
- **Laufende Uhr** im Header, synchron zur Systemzeit: Start-Sync beim Laden,
  dann sekuendlich; optional periodische Resyncs.
- **Datum/Zeitformat überall in der UI:** `HH:MM:SS DD-MM-YYYY`
  (Beispiel `12:04:10 05-01-2026`).
- **Keine doppelte Zeitanzeige:** Es bleibt **nur** die Uhr im Header. Weitere
  Zeitangaben in Statusboxen (insbesondere im Format `YYYY-MM-DD HH:MM:SS`)
  werden entfernt.
- **Keine doppelte „Angemeldet als …"-Zeile:** pro Screen nur einmal.
- **Login-Texte:** Das Eingabefeld heißt **„RFID"**, der Erklaertext erwaehnt
  nur RFID – keine Personalnummer, keine Mitarbeiter-ID. Die alternative
  Login-Möglichkeit darf intern weiter existieren, wird aber nicht beworben.

## 7. Startbildschirm und Hauptmenue

**Startbildschirm:** grosse Aufforderung „Bitte RFID-Chip an das Lesegeraet
halten", Eingabezeile, in die der Leser die Nummer schreibt (kurz sichtbar).
Nach ca. 50–100 ms wird automatisch ENTER ausgelöst. Keine Bearbeitung des
Codes noetig; optional ein „Abbrechen"-Knopf.

**Nach dem Scan** je nach Rolle:

- für alle: „Kommen", „Gehen", „Hauptauftrag starten", „Nebenauftrag starten",
  „Auftrag stoppen", „Urlaub beantragen", „Übersicht"
- zusätzlich mit passenden Rechten: „RFID-Chip zu Mitarbeiter zuweisen",
  „Urlaubsantraege", weitere Adminfunktionen

### Button-Logik nach Anwesenheitsstatus

**Heute noch nicht anwesend** (kein „Kommen" gebucht):

- sichtbar: **nur** der grosse Knopf **„Kommen"** (doppelte Hoehe), optional
  darunter „Urlaub beantragen"
- **nicht** sichtbar: Gehen, Auftrag starten/stoppen, Nebenauftrag, Übersicht
- auch **keine** Status-/Urlaubssaldo-Box

**Anwesend** (mindestens ein „Kommen" ohne abschließendes „Gehen"):

- erste Zeile: grosser Knopf **„Gehen"** (doppelte Hoehe)
- „Kommen" ist nicht sichtbar oder klar deaktiviert, um Fehlbuchungen zu
  verhindern
- danach die restlichen Aktionen, rollenbasiert

„Kommen" und „Gehen" sind die meistgenutzten Aktionen und stehen **immer** als
erste Zeile im Aktionsbereich, mit mindestens doppelter Hoehe.

Die Guards dafür gelten **serverseitig**, nicht nur in der UI – Stop-Aktionen
waren frueher per Direkt-URL auch ohne Anwesenheit erreichbar (B-033).

### Info-Box nach dem Login

Unterhalb der Status-/Success-Banner steht eine kompakte Urlaubsübersicht
statt einer zweiten „Angemeldet als …"-Zeile:

```
Uebertrag (YYYY-1): X Tage
Jahr YYYY:          Y Tage
optional Gesamt:    Z Tage
```

Ist kein Kontingent gepflegt, erscheint ein klarer Hinweis („Urlaub: Kontingent
für YYYY nicht gepflegt.") – **nicht** „Keine Urlaubsdaten verfügbar" ohne
Kontext.

## 8. Navigation und Auto-Logout

Fast jede Terminalseite bietet **„Zurück"** (vorherige Ansicht) und
**„Start"** (zurück zum RFID-Wartebildschirm). Abläufe wie „Auftrag starten"
oder „Urlaub beantragen" sind linear und möglichst kurz.

**Auto-Logout:** Passiert nach der Anmeldung eine bestimmte Zeit nichts, wird
die Terminal-Session beendet und das Terminal kehrt zur Startseite zurück.

- Normalfaelle (Kommen/Gehen, Auftrag, Übersicht): 30–60 Sekunden
- „Urlaub beantragen": deutlich laenger (2–3 Minuten), weil die Datumsauswahl
  Zeit braucht

Die Dauer ist über die `config`-Tabelle einstellbar. Zusätzlich gibt es einen
**serverseitigen** Fallback (`terminal_session_idle_timeout`, Default 300 s),
falls der Browser oder das JavaScript hängt.

**Doppelbuchungen:** Doppelklick oder Doppel-Scan innerhalb weniger Sekunden
wird per Session-De-Bounce abgefangen (B-028).

## 9. RFID-Chip-Verwaltung

Feld `rfid_code` in `mitarbeiter`. Die Funktion „RFID-Chip zu Mitarbeiter
zuweisen" zeigt die Mitarbeiterliste, erwartet nach der Auswahl den Scan eines
Chips, zeigt den Code kurz an, bestätigt automatisch und speichert. Optional
mit Chip-Historie.

**Der Terminal-Login hängt nicht an `ist_login_berechtigt`.** Diese Checkbox
steuert nur den Backend-Login über Benutzername/Passwort. Waren beide
gekoppelt, konnten sich normale Mitarbeiter nicht am Terminal anmelden (B-031).

**Mehrdeutige numerische Codes** (Personalnummer vs. Mitarbeiter-ID) dürfen
**nicht** still den falschen Mitarbeiter auswählen – bei Mehrdeutigkeit wird
abgebrochen (B-036).

## 10. Rückgabewerte der Buchungsdienste

`ZeitService::bucheKommen()`/`bucheGehen()` und
`AuftragszeitService::starteAuftrag()`/`stoppeAuftrag()` haben **drei**
Ergebnisse, nicht zwei:

| Rückgabe | Bedeutung |
| --- | --- |
| `> 0` | Hauptdatenbank erreichbar, Datensatz angelegt, Wert ist die ID |
| `0` | Hauptdatenbank offline, Befehl liegt in der Queue – für den Benutzer ein **Erfolg** |
| `null` | fehlgeschlagen |

Wer nur auf `null` prüft, behandelt den Offline-Fall richtig. Wer auf `> 0`
prüft, meldet ihn als Fehler – und ein Mitarbeiter, der gestempelt hat, bekommt
„Buchung fehlgeschlagen" zu sehen, obwohl seine Zeit sicher in der Queue liegt.
