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

Früher stand hier „mit Window-Manager". Das trifft es seit dem Kiosk-Skript
nicht mehr: Auf dem Standardweg (Wayland, `cage`) gibt es keinen
Fenstermanager im herkömmlichen Sinn – `cage` **ist** der Compositor und zeigt
genau ein Fenster. Nur der X11-Rückfall startet zusätzlich `openbox`, und
zwar aus genau diesem Grund: Ohne Fenstermanager bekommt der Browser dort sein
Vollbild nicht zuverlässig. Wie das Gerät aufgesetzt wird, steht in
`docs/spezifikation_terminal_installation.md`, Abschnitt 7.

Das Terminal spricht **primär** mit der Hauptdatenbank.

**Der Kontext der UI bestimmt, was ein eingelesener Code bedeutet:**

- Startbildschirm → Mitarbeiter-RFID (Login)
- „Auftrag starten" → Auftragsnummer
- „Maschine auswählen" → Maschinen-ID

## 2. Leser-Anbindung

**Standard (empfohlen):** Der Leser verhält sich wie eine **USB-Tastatur**
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

`id`, `name` (z. B. „Halle 1 – Fräserei Terminal links"),
`standort_beschreibung`, `abteilung_id` (für den Maschinenfilter), `modus`
(`terminal` oder `backend`), `aktiv`, Timestamps.

Je Terminal einstellbar: welche Funktionen im Offline-Modus erlaubt sind und
welcher Timeout für den Auto-Logout gilt.

### Jede Buchung weiß, an welchem Gerät sie entstanden ist

`zeitbuchung.terminal_id` und `auftragszeit.terminal_id` tragen die ID des
Terminals, an dem gestempelt wurde – auch dann, wenn die Buchung erst über die
Offline-Queue in die Hauptdatenbank kommt. Auskunft gibt **eine** Stelle:
`Helper::terminalId()`. Sie liest `terminal.id` aus `config/config.local.php`
und liefert `null`, sobald `app.installation_typ` nicht `terminal` ist.

Diese Bindung an den Installationstyp ist Absicht: Eine im Büro nachgetragene
Buchung (`quelle = 'web'`) bleibt ohne Terminal-ID, selbst wenn in der
Konfiguration des Backends noch ein `terminal`-Block aus einer früheren
Kopplung steht. `NULL` heißt also „nicht an einem Terminal entstanden" – nicht
„unbekannt".

Beim Pausieren und Fortsetzen eines Auftrags erbt die neue Zeile die ID der
pausierten – eine Auftragszeit sagt damit, wo sie **begonnen** hat.

## 4. Inbetriebnahme: Kopplung statt Zugangsdaten

Ein neues Terminal bekommt seine Konfiguration **nicht** aus einer von Hand
angelegten Datei, sondern über eine **Kopplung** am Backend:

1. In der Terminalverwaltung wird zum Gerät ein **Kopplungscode** erzeugt –
   einmalig gültig, 30 Minuten, nur als Hash gespeichert.
2. Am Terminal werden Server-Adresse und Code eingegeben (Einrichtungsseite,
   erscheint automatisch bei fehlender `config/config.local.php`).
3. Das Terminal holt sich Terminal-ID, Zugangsdaten und Einstellungen selbst und
   schreibt seine `config.local.php`.

**Jedes Terminal erhält einen eigenen Datenbankbenutzer** mit eingeschränkten
Rechten: kein `DELETE`, kein `DROP`, kein Zugriff auf Stundenkonto und
Lohnkorrekturen. Ein verlorenes Gerät wird durch Löschen dieses einen
Benutzers unschädlich gemacht – ohne alle anderen Terminals anzufassen; in der
Terminalverwaltung ist das der Knopf **Entkoppeln**. Ein Gerät nur stillzulegen
(`aktiv = 0`) reicht dafür **nicht**: Das verhindert nur eine neue Kopplung,
der vorhandene Zugang bleibt gültig.

Damit kennt das Installationsskript **keine** Zugangsdaten; dasselbe Abbild
passt auf beliebig viele Geräte.

Eine Ausnahme gibt es: die **lokale Ausweichdatenbank** (Abschnitt 5). Sie
gehört der Maschine und nicht dem Backend, also kann die Kopplung sie nicht
liefern. `scripts/terminal/install_terminal.sh` legt sie samt eigenem Benutzer
an und schreibt ihre Zugangsdaten nach `config/geraet.local.php`; beim Koppeln
werden sie von dort in die `config.local.php` übernommen. Deshalb tragen beide
Dateien dasselbe Passwort – und deshalb sucht das Skript ein vorhandenes
Passwort zuerst in `config.local.php`: Würde es stattdessen ein neues erzeugen,
liefe das Terminal weiter, aber seine Queue wäre tot (P-2026-08-09-05).

Recht: `TERMINAL_VERWALTEN` (Erzeugen des Codes). Der Kopplungs-Endpunkt selbst
ist bewusst **ohne Anmeldung** erreichbar – ein frisches Gerät hat keinen
Benutzer, der Code **ist** der Nachweis.

Einzelheiten inklusive Rechteliste des Terminal-Benutzers:
`docs/spezifikation_terminal_installation.md`.

## 5. Offline-Fall und Injektions-Queue

Vor jeder Schreiboperation in die Hauptdatenbank wird die Verbindung geprüft.

**Diese Prüfung muss schnell scheitern.** „Nicht erreichbar" heißt in der Halle
selten „Verbindung abgelehnt" (das antwortet in Millisekunden), sondern meistens
„es kommt gar nichts zurück" – Switch tot, Server hängt, Firewall verwirft. Ohne
gesetzten Verbindungs-Timeout wartet PHP dann 30 Sekunden je Versuch, und ein
einziger Seitenaufruf braucht mehrere davon. Gemessen an einem Host, der
schweigt: **270 Sekunden für die Startseite** (P-2026-08-16-08). Das Terminal
geht dann nicht offline, es steht. Deshalb gilt: Verbindungs-Timeout wenige
Sekunden, und wer in einem Aufruf einmal „nicht erreichbar" festgestellt hat,
fragt in **demselben** Aufruf nicht noch einmal nach.

Ist die Hauptdatenbank nicht erreichbar:

**Erlaubt bleibt**, was den Betrieb am Laufen hält: Kommen/Gehen, Aufträge
starten/stoppen.
**Nicht erlaubt** sind komplexe Übersichten, Urlaubsanträge stellen oder
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

Geschrieben wird in eine **lokale Sekundärdatenbank** (Tabelle
`db_injektionsqueue`): `id`, `erstellt_am`, `status` (`offen` | `verarbeitet` |
`fehler`), **roher SQL-Befehl** zur späteren 1:1-Ausführung gegen die
Hauptdatenbank, sowie Metadaten (Mitarbeiter-ID, Art der Aktion).

**Was der Mitarbeiter davon sieht: die Zustandspille oben rechts, sonst
nichts.** Sie steht auf jeder Terminalseite und heißt online grün `ONLINE`,
offline rot `OFFLINE`. Kein zusätzlicher Text, keine Aufforderung, einen Admin
zu rufen – der Mitarbeiter am Gerät kann daran nichts ändern, und ein
Alarmbanner über dem Stempelknopf erzeugt nur Anrufe. Die Farbe genügt, damit
niemand wochenlang übersieht, dass die Verbindung fehlt.

Früher stand hier die Vorgabe, auf allen Terminalseiten „Hauptdatenbank nicht
aktiv – Admin anfordern" anzuzeigen. Umgesetzt war sie nie, und sie ist auch
nicht gewollt (Entscheidung Manuel, P-2026-08-16-08).

**Wo die Queue liegt, weiß genau eine Stelle:** der `OfflineQueueManager` mit
`holeQueueVerbindungOderNull()` und `holeQueueSpeicherort()`. Wer neu auf
`db_injektionsqueue` zugreift, nimmt diese beiden – **nicht** `Database`
direkt, sonst entsteht eine zweite Meinung darüber, welche Datenbank gemeint
ist.

### Wiederanlauf: ein kaputter Eintrag hält niemanden auf

Sobald die Hauptdatenbank wieder erreichbar ist, wird die Queue in zeitlicher
Reihenfolge abgearbeitet. Scheitert ein Eintrag:

- Er wird auf `fehler` gesetzt, mit Fehlermeldung – und **übersprungen**. Die
  folgenden Einträge werden weiter abgearbeitet.
- Das Terminal bleibt **bedienbar**. Kommen und Gehen müssen immer gehen.
- Am Terminal steht darüber **nichts**. Der Mitarbeiter kann einen kaputten
  Eintrag nicht reparieren; die Meldung gehört dorthin, wo jemand entscheiden
  darf.
- Gemeldet wird im **Backend**: Wenn das Einspielen scheitert, ist die
  Hauptdatenbank ja gerade erreichbar – sonst hätte es keinen Versuch gegeben.
  Das Terminal legt den gescheiterten Eintrag deshalb in `db_injektionsqueue`
  **der Hauptdatenbank** ab. Damit zeigt ihn die vorhandene Queue-Verwaltung
  (`QUEUE_VERWALTEN`) ohne neue Maske: Chef, Personalbüro oder
  Abteilungsleiter sehen Zeitpunkt, Chipnummer und SQL-Befehl und tragen die
  Zeit von Hand nach oder verwerfen den Eintrag.

**Warum das geändert wurde** (Entscheidung Manuel, P-2026-08-16-08): Vorher
stoppte die Abarbeitung beim ersten Fehler und das Terminal ging in einen
sperrenden Störungsmodus. Nachgestellt hieß das: Ein einziger unbekannter Chip
– ein neuer Mitarbeiter, ein Besucher, ein Chip auf `aktiv = 0` – legte das
Gerät für **alle** lahm, auch wenn die Hauptdatenbank längst wieder lief. Der
dokumentierte Ausweg („Admin löscht den Eintrag im Backend") funktionierte
dabei nicht: Die Queue liegt in der lokalen Datenbank des Terminals, und das
Backend liest über dieselbe Regel seine eigene. Es blieb nur, mit Tastatur oder
SSH an das Gerät zu gehen.

### Störungsmodus: nur noch ein Fall

Der sperrende Bildschirm bleibt für die eine Lage, in der das Terminal
tatsächlich nichts mehr tun kann: **weder Hauptdatenbank noch lokale Queue
erreichbar**. Dann ist keine Buchung speicherbar, und das muss dastehen –
inklusive HTTP-Status `503`, damit eine Überwachung es sieht.

### Lokale Liste der Berechtigten (geplant, T-125)

Heute merkt das Terminal erst beim Einspielen – Stunden später –, dass ein Chip
niemandem gehört. Der Mensch, der ihn drangehalten hat, ist längst weg.
Deshalb soll das Gerät eine **eigene, kleine Liste** bekommen, die es im
Online-Betrieb aus der Hauptdatenbank auffrischt:

- **Nur** `mitarbeiter_id`, `personalnummer`, `rfid_code`, `aktiv`.
- **Keine Namen, keine Passwörter, keine Kontostände.** Wird das Gerät
  gestohlen, sind es Nummern ohne Zuordnung. (Dass die Zugangsdaten zur
  Hauptdatenbank ohnehin auf dem Gerät liegen, bleibt davon unberührt – der
  Spiegel macht es nicht schlimmer, aber auch nicht besser.)
- Zweck ist **Erkennen und Auflösen**, nicht Türsteherei: Ein Chip, der nicht
  in der Liste steht, wird offline **trotzdem angenommen**. Sonst verliert
  jemand mit frisch ausgegebenem Chip seine Ankunftszeit, weil der Spiegel zwei
  Stunden alt ist. Der Eintrag geht dann wie gehabt mit RFID-Auflösung in die
  Queue und taucht im Zweifel im Backend auf.
- Der Spiegel ersetzt die Regel oben **nicht**: Er kann veraltet sein,
  unauflösbare Einträge bleiben möglich.

Erst danach ist der zweite Schritt sinnvoll – **Anmeldung und Aufträge im
Offline-Betrieb**. Der braucht zusätzlich eine Anwesenheitslogik ohne
Hauptdatenbank und, wenn beim Auftragsstart eine Maschine gewählt wird, auch
eine lokale Maschinenliste. Das ist ein eigenes Vorhaben, kein Anhängsel.

## 6. Terminal-UI: Layout, Uhr, Texte

- **Bildschirmausnutzung:** ca. **97 %** der verfügbaren Fläche (Breite und
  Höhe), minimale Außenränder, responsiv über den Viewport.
- **Laufende Uhr** im Header, synchron zur Systemzeit: Start-Sync beim Laden,
  dann sekündlich; optional periodische Resyncs.
- **Datum/Zeitformat überall in der UI:** `HH:MM:SS DD-MM-YYYY`
  (Beispiel `12:04:10 05-01-2026`).
- **Keine doppelte Zeitanzeige:** Es bleibt **nur** die Uhr im Header. Weitere
  Zeitangaben in Statusboxen (insbesondere im Format `YYYY-MM-DD HH:MM:SS`)
  werden entfernt.
- **Keine doppelte „Angemeldet als …"-Zeile:** pro Screen nur einmal.
- **Login-Texte:** Das Eingabefeld heißt **„RFID"**, der Erklärtext erwähnt
  nur RFID – keine Personalnummer, keine Mitarbeiter-ID. Die alternative
  Login-Möglichkeit darf intern weiter existieren, wird aber nicht beworben.

## 7. Startbildschirm und Hauptmenü

**Startbildschirm:** große Aufforderung „Bitte RFID-Chip an das Lesegerät
halten", Eingabezeile, in die der Leser die Nummer schreibt (kurz sichtbar).
Nach ca. 50–100 ms wird automatisch ENTER ausgelöst. Keine Bearbeitung des
Codes nötig; optional ein „Abbrechen"-Knopf.

**Nach dem Scan** je nach Rolle:

- für alle: „Kommen", „Gehen", „Hauptauftrag starten", „Nebenauftrag starten",
  „Auftrag stoppen", „Urlaub beantragen", „Übersicht"
- zusätzlich mit passenden Rechten: „RFID-Chip zu Mitarbeiter zuweisen",
  „Urlaubsanträge", weitere Adminfunktionen

### Button-Logik nach Anwesenheitsstatus

**Heute noch nicht anwesend** (kein „Kommen" gebucht):

- sichtbar: **nur** der große Knopf **„Kommen"** (doppelte Höhe), optional
  darunter „Urlaub beantragen"
- **nicht** sichtbar: Gehen, Auftrag starten/stoppen, Nebenauftrag, Übersicht
- auch **keine** Status-/Urlaubssaldo-Box

**Anwesend** (mindestens ein „Kommen" ohne abschließendes „Gehen"):

- erste Zeile: großer Knopf **„Gehen"** (doppelte Höhe)
- „Kommen" ist nicht sichtbar oder klar deaktiviert, um Fehlbuchungen zu
  verhindern
- danach die restlichen Aktionen, rollenbasiert

„Kommen" und „Gehen" sind die meistgenutzten Aktionen und stehen **immer** als
erste Zeile im Aktionsbereich, mit mindestens doppelter Höhe.

Die Guards dafür gelten **serverseitig**, nicht nur in der UI – Stop-Aktionen
waren früher per Direkt-URL auch ohne Anwesenheit erreichbar (B-033).

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

- Normalfälle (Kommen/Gehen, Auftrag, Übersicht): 30–60 Sekunden
- „Urlaub beantragen": deutlich länger (2–3 Minuten), weil die Datumsauswahl
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
