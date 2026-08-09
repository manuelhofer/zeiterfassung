# Fachregeln: Terminal, Kiosk-UI, Offline-Queue, Kopplung

*Gilt fuer:* `public/terminal.php`, `controller/TerminalController.php`,
`controller/TerminalEinrichtungController.php`, `core/OfflineQueueManager.php`,
`views/terminal/*`.
*Herkunft:* Master-Prompt v13, Abschnitte 2.2, 6.2, 8, 15 und der
Kopplungs-Abschnitt.
*Vertiefung:* `docs/spezifikation_terminal_installation.md` (Installation und
Kopplung im Detail), `docs/rfid_reader_setup.md`,
`docs/terminal/rfid-ws_rollout.md`.

---

## 1. Was ein Terminal ist

Ein separater Linux-PC mit Window-Manager, Browser im **Kioskmodus**,
Touchscreen, keine normale Tastatur fuer den Anwender. Es hat **einen**
Leser/Scanner – entweder einen RFID-Leser (fuer Mitarbeiter-Chips und
Auftrags-/Maschinenchips) oder einen Barcode-Scanner.

Das Terminal spricht **primaer** mit der Hauptdatenbank.

**Der Kontext der UI bestimmt, was ein eingelesener Code bedeutet:**

- Startbildschirm → Mitarbeiter-RFID (Login)
- „Auftrag starten" → Auftragsnummer
- „Maschine auswaehlen" → Maschinen-ID

## 2. Leser-Anbindung

**Standard (empfohlen):** Der Leser verhaelt sich wie eine **USB-Tastatur**
(„Keyboard-Wedge"). Er schreibt den Code in ein fokussiertes Eingabefeld und
sendet am Ende typischerweise ENTER. Die Web-App verarbeitet nur den
empfangenen String.

**Alternative (z. B. Raspberry Pi + RC522/MFRC522 am SPI):** Diese Module
liefern **keine** Tastatureingaben. Auf dem Terminal laeuft dann ein lokaler
Adapterdienst (z. B. Python), der die UID ausliest und entweder als
Tastatureingabe einspeist (uinput/evdev) oder per HTTP/WebSocket ueber
localhost an die Terminalseite uebergibt.

**Wichtig:** Fuer die Web-App bleibt die Schnittstelle gleich – „es kommt ein
Code als String an". Welche Hardware dahintersteckt, ist austauschbar und wird
je Terminal konfiguriert.

## 3. Tabelle `terminal` und Einstellungen

`id`, `name` (z. B. „Halle 1 – Fraeserei Terminal links"),
`standort_beschreibung`, `abteilung_id` (fuer den Maschinenfilter), `modus`
(`terminal` oder `backend`), `aktiv`, Timestamps.

Je Terminal einstellbar: welche Funktionen im Offline-Modus erlaubt sind und
welcher Timeout fuer den Auto-Logout gilt.

## 4. Inbetriebnahme: Kopplung statt Zugangsdaten

Ein neues Terminal bekommt seine Konfiguration **nicht** aus einer von Hand
angelegten Datei, sondern ueber eine **Kopplung** am Backend:

1. In der Terminalverwaltung wird zum Geraet ein **Kopplungscode** erzeugt –
   einmalig gueltig, 30 Minuten, nur als Hash gespeichert.
2. Am Terminal werden Server-Adresse und Code eingegeben (Einrichtungsseite,
   erscheint automatisch bei fehlender `config/config.local.php`).
3. Das Terminal holt sich Terminal-ID, Zugangsdaten und Einstellungen selbst und
   schreibt seine `config.local.php`.

**Jedes Terminal erhaelt einen eigenen Datenbankbenutzer** mit eingeschraenkten
Rechten: kein `DELETE`, kein `DROP`, kein Zugriff auf Stundenkonto und
Lohnkorrekturen. Ein verlorenes Geraet wird durch Loeschen dieses einen
Benutzers unschaedlich gemacht – ohne alle anderen Terminals anzufassen.

Damit kennt das Installationsskript **keine** Zugangsdaten; dasselbe Abbild
passt auf beliebig viele Geraete.

Recht: `TERMINAL_VERWALTEN` (Erzeugen des Codes). Der Kopplungs-Endpunkt selbst
ist bewusst **ohne Anmeldung** erreichbar – ein frisches Geraet hat keinen
Benutzer, der Code **ist** der Nachweis.

Einzelheiten inklusive Rechteliste des Terminal-Benutzers:
`docs/spezifikation_terminal_installation.md`.

## 5. Offline-Fall und Injektions-Queue

Vor jeder Schreiboperation in die Hauptdatenbank wird die Verbindung geprueft.
Ist sie nicht erreichbar:

**Erlaubt bleibt**, was den Betrieb am Laufen haelt: Kommen/Gehen, Auftraege
starten/stoppen.
**Nicht erlaubt** sind komplexe Uebersichten, Urlaubsantraege stellen oder
verwalten, umfangreiche Auswertungen.

### Offline-Stempeln ohne Mitarbeiter-Identifikation (RFID-only)

Im Offline-Modus gibt es **keine Anmeldung auf einen Mitarbeiter** und **keine
Pruefung gegen die Hauptdatenbank**. Der gescannte RFID-Code ist das einzige
Identifikationsmerkmal.

Ablauf: Anzeige „Offline-Modus – Bitte RFID scannen" → nach dem Scan erscheinen
**Kommen** und **Gehen** → beim Klick werden **RFID-Code + Zeitstempel +
Aktion** in die Offline-Queue geschrieben.

Optional darf ein Hinweis erscheinen („letzte Offline-Buchung war
Kommen/Gehen"), basierend auf dem letzten Queue-Eintrag dieser RFID – **aber
ohne harte Sperre**.

Der rohe SQL-Befehl in `db_injektionsqueue` muss die Mitarbeiter-ID **erst beim
Replay** ueber den RFID-Code aufloesen:

```sql
INSERT ... SELECT id FROM mitarbeiter WHERE rfid_code='…' LIMIT 1;
```

Wird beim Replay keine passende Mitarbeiter-ID gefunden (RFID unbekannt), geht
der Eintrag auf `fehler` und die Abarbeitung stoppt – ein Admin muss die RFID
zuweisen oder den Eintrag verwerfen.

### Die Queue

Geschrieben wird in eine **lokale Sekundaerdatenbank** (Tabelle
`db_injektionsqueue`): `id`, `erstellt_am`, `status` (`offen` | `verarbeitet` |
`fehler`), **roher SQL-Befehl** zur spaeteren 1:1-Ausfuehrung gegen die
Hauptdatenbank, sowie Metadaten (Mitarbeiter-ID, Art der Aktion).

Auf allen Terminalseiten wird in diesem Zustand gut sichtbar angezeigt:
**„Hauptdatenbank nicht aktiv – Admin anfordern"**.

### Wiederanlauf und Stoerungsmodus

Sobald die Hauptdatenbank wieder erreichbar ist, wird die Queue in zeitlicher
Reihenfolge abgearbeitet – **bis ein Fehler auftritt**.

Bei einem Fehler:

- Abarbeitung **sofort stoppen**.
- Terminal wechselt in den **Stoerungsmodus**: Meldung, **der konkrete
  SQL-Befehl**, und die Aufforderung, einen Admin zu rufen.
- Ein berechtigter Admin kann im Backend den Eintrag einsehen, den SQL-Befehl
  pruefen und entscheiden: „Ignorieren/Loeschen" (die Injektion wird verworfen,
  die Queue laeuft weiter) oder den Fehler notieren, den Eintrag loeschen und
  den Sachverhalt manuell korrekt nachtragen.
- Erst nach Bearbeitung des Eintrags darf die Queue weiterlaufen.

## 6. Terminal-UI: Layout, Uhr, Texte

- **Bildschirmausnutzung:** ca. **97 %** der verfuegbaren Flaeche (Breite und
  Hoehe), minimale Aussenraender, responsiv ueber den Viewport.
- **Laufende Uhr** im Header, synchron zur Systemzeit: Start-Sync beim Laden,
  dann sekuendlich; optional periodische Resyncs.
- **Datum/Zeitformat ueberall in der UI:** `HH:MM:SS DD-MM-YYYY`
  (Beispiel `12:04:10 05-01-2026`).
- **Keine doppelte Zeitanzeige:** Es bleibt **nur** die Uhr im Header. Weitere
  Zeitangaben in Statusboxen (insbesondere im Format `YYYY-MM-DD HH:MM:SS`)
  werden entfernt.
- **Keine doppelte „Angemeldet als …"-Zeile:** pro Screen nur einmal.
- **Login-Texte:** Das Eingabefeld heisst **„RFID"**, der Erklaertext erwaehnt
  nur RFID – keine Personalnummer, keine Mitarbeiter-ID. Die alternative
  Login-Moeglichkeit darf intern weiter existieren, wird aber nicht beworben.

## 7. Startbildschirm und Hauptmenue

**Startbildschirm:** grosse Aufforderung „Bitte RFID-Chip an das Lesegeraet
halten", Eingabezeile, in die der Leser die Nummer schreibt (kurz sichtbar).
Nach ca. 50–100 ms wird automatisch ENTER ausgeloest. Keine Bearbeitung des
Codes noetig; optional ein „Abbrechen"-Knopf.

**Nach dem Scan** je nach Rolle:

- fuer alle: „Kommen", „Gehen", „Hauptauftrag starten", „Nebenauftrag starten",
  „Auftrag stoppen", „Urlaub beantragen", „Uebersicht"
- zusaetzlich mit passenden Rechten: „RFID-Chip zu Mitarbeiter zuweisen",
  „Urlaubsantraege", weitere Adminfunktionen

### Button-Logik nach Anwesenheitsstatus

**Heute noch nicht anwesend** (kein „Kommen" gebucht):

- sichtbar: **nur** der grosse Knopf **„Kommen"** (doppelte Hoehe), optional
  darunter „Urlaub beantragen"
- **nicht** sichtbar: Gehen, Auftrag starten/stoppen, Nebenauftrag, Uebersicht
- auch **keine** Status-/Urlaubssaldo-Box

**Anwesend** (mindestens ein „Kommen" ohne abschliessendes „Gehen"):

- erste Zeile: grosser Knopf **„Gehen"** (doppelte Hoehe)
- „Kommen" ist nicht sichtbar oder klar deaktiviert, um Fehlbuchungen zu
  verhindern
- danach die restlichen Aktionen, rollenbasiert

„Kommen" und „Gehen" sind die meistgenutzten Aktionen und stehen **immer** als
erste Zeile im Aktionsbereich, mit mindestens doppelter Hoehe.

Die Guards dafuer gelten **serverseitig**, nicht nur in der UI – Stop-Aktionen
waren frueher per Direkt-URL auch ohne Anwesenheit erreichbar (B-033).

### Info-Box nach dem Login

Unterhalb der Status-/Success-Banner steht eine kompakte Urlaubsuebersicht
statt einer zweiten „Angemeldet als …"-Zeile:

```
Uebertrag (YYYY-1): X Tage
Jahr YYYY:          Y Tage
optional Gesamt:    Z Tage
```

Ist kein Kontingent gepflegt, erscheint ein klarer Hinweis („Urlaub: Kontingent
fuer YYYY nicht gepflegt.") – **nicht** „Keine Urlaubsdaten verfuegbar" ohne
Kontext.

## 8. Navigation und Auto-Logout

Fast jede Terminalseite bietet **„Zurueck"** (vorherige Ansicht) und
**„Start"** (zurueck zum RFID-Wartebildschirm). Abläufe wie „Auftrag starten"
oder „Urlaub beantragen" sind linear und moeglichst kurz.

**Auto-Logout:** Passiert nach der Anmeldung eine bestimmte Zeit nichts, wird
die Terminal-Session beendet und das Terminal kehrt zur Startseite zurueck.

- Normalfaelle (Kommen/Gehen, Auftrag, Uebersicht): 30–60 Sekunden
- „Urlaub beantragen": deutlich laenger (2–3 Minuten), weil die Datumsauswahl
  Zeit braucht

Die Dauer ist ueber die `config`-Tabelle einstellbar. Zusaetzlich gibt es einen
**serverseitigen** Fallback (`terminal_session_idle_timeout`, Default 300 s),
falls der Browser oder das JavaScript haengt.

**Doppelbuchungen:** Doppelklick oder Doppel-Scan innerhalb weniger Sekunden
wird per Session-De-Bounce abgefangen (B-028).

## 9. RFID-Chip-Verwaltung

Feld `rfid_code` in `mitarbeiter`. Die Funktion „RFID-Chip zu Mitarbeiter
zuweisen" zeigt die Mitarbeiterliste, erwartet nach der Auswahl den Scan eines
Chips, zeigt den Code kurz an, bestaetigt automatisch und speichert. Optional
mit Chip-Historie.

**Der Terminal-Login haengt nicht an `ist_login_berechtigt`.** Diese Checkbox
steuert nur den Backend-Login ueber Benutzername/Passwort. Waren beide
gekoppelt, konnten sich normale Mitarbeiter nicht am Terminal anmelden (B-031).

**Mehrdeutige numerische Codes** (Personalnummer vs. Mitarbeiter-ID) duerfen
**nicht** still den falschen Mitarbeiter auswaehlen – bei Mehrdeutigkeit wird
abgebrochen (B-036).
