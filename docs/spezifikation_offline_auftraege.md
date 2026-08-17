# Spezifikation: Aufträge im Offline-Betrieb (T-138)

Zielbild und Akzeptanzkriterien für den zweiten Schritt aus P-2026-08-16-08.
Der erste war T-125 (lokale Liste der Berechtigten) und ist erledigt.

**Wer das hier abarbeitet, liest vorher:**
[`fachregeln/terminal_und_offline.md`](fachregeln/terminal_und_offline.md),
Abschnitt 5 – dort stehen die Regeln, hier steht der Weg.

---

## 1. Zielbild in drei Sätzen

Fällt die Hauptdatenbank aus, kann heute nur noch gestempelt werden. Aufträge
starten und stoppen ist laut Fachregel ausdrücklich erlaubt, in der Praxis aber
nicht erreichbar. Nach diesem Vorhaben geht beides: Chip scannen, Auftrag
starten, später Chip scannen, Auftrag stoppen – und beim Wiederanlauf landet
alles dort, wo es hingehört.

## 2. Was schon da ist – bitte nicht neu bauen

Der größte Teil ist gebaut. Wer das übersieht, baut ein Paarungsverfahren für
ein Problem, das keins ist (genau das ist am 17.08. beinahe passiert).

| Was | Wo | Zustand |
| --- | --- | --- |
| Offline-Stempeln per RFID, ohne Anmeldung | [`TerminalController.php:1093`](../controller/TerminalController.php) `bucheZeitOfflinePerRfid()` | fertig |
| Auftragsstart in die Queue, inkl. Anlegen des Auftrags | [`AuftragszeitService.php:163`](../services/AuftragszeitService.php) `starteAuftrag()`, Offline-Zweig | fertig |
| Auftragsstopp in die Queue | [`AuftragszeitService.php:346`](../services/AuftragszeitService.php) `stoppeAuftrag()`, Offline-Zweig | fertig |
| Nebenaufträge offline | `TerminalController.php:3038` / `:3216` | fertig |
| Lokale Liste der Berechtigten | [`core/MitarbeiterSpiegel.php`](../core/MitarbeiterSpiegel.php) | fertig (T-125) |
| Letzte Offline-Buchung eines Chips finden | `TerminalController.php:1170` `ermittleOfflineHintFuerRfid()` | fertig, wird bisher nur als Anzeigehinweis benutzt |

**Zwei Dinge daraus, die man kennen muss:**

**Der Auftrag muss nicht existieren.** `starteAuftrag()` schreibt offline vier
Queue-Einträge, darunter:

```sql
INSERT INTO auftrag (auftragsnummer, aktiv) VALUES ('213423421342', 1)
  ON DUPLICATE KEY UPDATE auftragsnummer = auftragsnummer
```

Eine getippte Nummer ist damit ein neuer Auftrag, eine bekannte bleibt die
bekannte. Für den Arbeitsschritt gilt dasselbe über `auftrag_arbeitsschritt`.

**Der Stopp findet seinen Start von allein.** Er ist kein `INSERT`, sondern ein
bedingtes `UPDATE`:

```sql
UPDATE auftragszeit SET endzeit='…', status='abgeschlossen'
 WHERE mitarbeiter_id=… AND typ='haupt' AND status='laufend' AND endzeit IS NULL
```

Die Queue spielt der Reihe nach ab, der Start läuft vorher – es braucht kein
Paarungsverfahren, keine lokale Tabelle offener Aufträge und keine IDs, die über
Gerätegrenzen hinweg eindeutig wären.

## 3. Was fehlt – drei Lücken

**L1 – Offline kommt niemand bis zum Auftragsknopf.** Alle Auftragswege beginnen
mit `holeAngemeldetenTerminalMitarbeiter()`
([`TerminalController.php:2266`](../controller/TerminalController.php), `:2303`,
`:2397` …). Offline gibt das `null` zurück, und der Offline-Zweig darunter
([`:4038`](../controller/TerminalController.php)) kennt nur Kommen und Gehen.
Der fertige Offline-Auftragscode greift deshalb nur in einem schmalen Fenster:
jemand ist online angemeldet, und die Verbindung fällt mitten in der Sitzung
aus. Fällt sie vorher aus, ist der Weg zu.

**L2 – Die Auftrags-SQL schreibt die Mitarbeiter-ID als Zahl.** Das Stempeln
löst sie erst beim Replay auf:

```sql
(SELECT id FROM mitarbeiter WHERE rfid_code = '…' AND aktiv = 1 LIMIT 1)
```

`starteAuftrag()` und `stoppeAuftrag()` setzen dagegen `(int)$mitarbeiterId`
direkt ein. Ohne Anmeldung gibt es diese Zahl offline nicht.

**L3 – Anwesenheit ist offline nicht feststellbar.** `auftragStarten()` verlangt
`istTerminalMitarbeiterHeuteAnwesend()`
([`TerminalController.php:535`](../controller/TerminalController.php)). Offline
fällt die Prüfung auf `$_SESSION['terminal_anwesend']` zurück – ein Merker, den
nur eine Anmeldung setzt. Im RFID-Ablauf wird er nie gesetzt, die Prüfung ist
also immer `false`.

Dazu kommt aus dem Snapshot: **eine lokale Maschinenliste**, falls beim
Auftragsstart eine Maschine gewählt wird.

## 4. Entscheidungen – getroffen, mit Begründung

**E1 – Keine vollständige lokale Datenbank.** Die Frage stand (Manuel,
17.08.). Antwort: nein, und der Grund ist die Richtung. Die Queue **fügt nur
hinzu** – Tatsachen, die allein das Gerät besitzt. Eine volle lokale Datenbank
**ändert auch**, und dann können zwei Stellen dieselbe Zeile anfassen. Das
kostet Konfliktregeln je Tabelle, kollidierende Auto-Increment-IDs gegen die
Fremdschlüssel aus T-129/T-135, Personendaten auf jedem Gerät entgegen der
Entscheidung aus T-125, und Fehlerbilder, die bis zum Gerätetest niemand prüfen
kann. Wer das später erneut erwägt, fängt bei diesem Absatz an.

**E2 – Lesend lokal, schreibend Queue.** Das ist die Regel hinter E1. Was das
Terminal offline **wissen** muss, darf als Spiegel danebenliegen (vier Spalten,
keine Geheimnisse, Bauart `MitarbeiterSpiegel`). Was es **festhalten** will,
geht in die Queue.

**E3 – Keine Anmeldung, ein Scan je Aktion.** Der Chip schaltet die
Auftragsknöpfe frei und wird danach wieder vergessen – wie beim Stempeln. Keine
Sitzung, kein Auto-Logout, niemand bleibt versehentlich angemeldet. Der Preis
ist ein Scan mehr je Aktion; das ist billiger als ein Gerät, an dem der Vorgänger
noch angemeldet ist. Die Fachregel bleibt damit unverändert gültig
(„keine Anmeldung auf einen Mitarbeiter").

**E4 – Anwesenheit ist ein Befund, keine Türsteherei.** Offline gilt jemand als
anwesend, wenn seine letzte Offline-Buchung `kommen` war. Weiß das Gerät nichts
über den Chip – kein Queue-Eintrag, weil er online gestempelt hat –, wird der
Auftrag **trotzdem** angenommen. Dieselbe Haltung wie beim Spiegel in T-125: Eine
verlorene Auftragszeit ist schlimmer als eine, die im Backend auffällt.

## 5. Umsetzung in drei Patches

Reihenfolge einhalten – P2 braucht P1, P3 ist unabhängig, aber ohne P1/P2
nutzlos.

### P1 – Auftrags-SQL löst den Mitarbeiter über die RFID auf

**Ziel:** L2 schließen. Danach kann die Queue einen Auftrag auch dann
einspielen, wenn beim Schreiben niemand angemeldet war.

**Dateien:** `services/AuftragszeitService.php`

**Vorgehen:** `starteAuftrag()` und `stoppeAuftrag()` bekommen einen optionalen
Parameter `?string $rfidCode = null`. Ist er gesetzt **und** ist
`$mitarbeiterId <= 0`, wird im Offline-Zweig überall dort, wo heute
`(int)$mitarbeiterId` im SQL steht, stattdessen eingesetzt:

```php
'(SELECT id FROM mitarbeiter WHERE rfid_code = ' . Helper::sqlLiteral($rfidCode) . ' AND aktiv = 1 LIMIT 1)'
```

Betroffen sind vier Stellen: `sql1` (laufende Hauptaufträge schließen), `sql3`
(neuer Hauptauftrag), der `UPDATE` in `stoppeAuftrag()`, und `meta_mitarbeiter_id`
beim Queue-Eintrag – letzteres bleibt `null`, weil die ID noch nicht bekannt ist.

**Fallstrick:** `Helper::sqlLiteral()` benutzen, nicht `sqlQuote()` von Hand.
`ermittleOfflineHintFuerRfid()` sucht den eigenen Eintrag über
`LIKE "%rfid_code = '…'%"` mit `Helper::sqlEscape()` wieder – eine zweite
Maskierung findet ihn nicht mehr (steht als Warnung schon bei
`bucheZeitOfflinePerRfid()`).

**Akzeptanzkriterium:** Ein Auftragsstart mit `mitarbeiterId = 0` und einer
gültigen RFID erzeugt einen Queue-Eintrag, dessen SQL den Subselect enthält und
der nach dem Einspielen eine `auftragszeit`-Zeile mit der richtigen
Mitarbeiter-ID hinterlässt.

**Prüfung:** Prüfumgebung im Terminal-Modus (`pruefumgebung.sh terminal
--offline`), Auftrag starten, `sql` gegen `zeit_probe_off` – der Eintrag steht
in der Queue. Dann `terminal` (online), ein Seitenaufruf, und die Zeile in
`auftragszeit` gegenprüfen. Gegenprobe mit unbekannter RFID: Eintrag muss auf
`fehler` gehen, nicht auf `mitarbeiter_id = 0` (das sichert der Fremdschlüssel
aus T-129).

### P2 – Der Offline-Scan schaltet die Auftragsknöpfe frei

**Ziel:** L1 und L3 schließen.

**Dateien:** `controller/TerminalController.php`, `views/terminal/start.php`

**Vorgehen:**

1. In `auftragStartenForm()`, `auftragStarten()`, `auftragStoppenForm()`,
   `auftragStoppen()` und `auftragStoppenQuick()` den Zweig
   `$mitarbeiter === null` erweitern: Ist die Hauptdatenbank nicht aktiv und
   liegt `$_SESSION['terminal_offline_rfid_code']` vor, wird mit dieser RFID
   weitergearbeitet statt abgebrochen. Muster steht in `kommen()`
   ([`:4038`](../controller/TerminalController.php)) – **dasselbe** verwenden,
   samt Aufräumen der drei Session-Schlüssel danach.
2. `istTerminalMitarbeiterHeuteAnwesend()` bekommt eine RFID-Variante: Offline
   entscheidet `ermittleOfflineHintFuerRfid()`. `letzte_typ === 'kommen'` heißt
   anwesend, `'gehen'` heißt nicht anwesend, `null` heißt **anwesend** (E4).
3. `views/terminal/start.php`: Im Offline-Block nach dem Scan (ab Zeile 527)
   die Auftragsknöpfe zeigen, nicht nur Kommen/Gehen.

**Fallstrick:** Der Kiosk leert die RFID nach jeder Buchung absichtlich
(„nächster Mitarbeiter"). Für Aufträge gilt dasselbe – nach Start oder Stopp
wieder leeren, sonst bucht der Nächste auf den Vorgänger.

**Akzeptanzkriterium:** Terminal offline, Chip scannen, Auftrag `TEST-138`
starten – die Queue enthält danach `auftrag_ensure` und `auftrag_start`, und der
Bildschirm ist wieder im Ausgangszustand ohne RFID.

**Prüfung:** Wie P1, zusätzlich der Ablauf am Stück: offline Kommen, Auftrag
starten, Auftrag stoppen, online gehen, Wiederanlauf – danach steht in
`auftragszeit` eine Zeile mit Start **und** Ende. Gegenprobe: Chip ohne
vorheriges Kommen (E4 – muss durchgehen).

### P3 – Lokale Maschinenliste

**Ziel:** Maschinenauswahl beim Auftragsstart funktioniert offline.

**Dateien:** `core/MaschinenSpiegel.php` (neu), `sql/offline_db_schema.sql`,
`public/terminal.php`, `views/terminal/*`

**Vorgehen:** `MitarbeiterSpiegel` kopieren und anpassen – dieselbe Bauart,
dieselbe Auffrischung im selben Atemzug wie der Queue-Wiederanlauf, dasselbe
`ensureSchema()`. Tabelle:

```sql
CREATE TABLE IF NOT EXISTS maschine_spiegel (
  maschine_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  aktiv TINYINT(1) NOT NULL DEFAULT 1,
  aktualisiert_am DATETIME NOT NULL,
  PRIMARY KEY (maschine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`name` steht hier, anders als beim Mitarbeiterspiegel, weil eine Maschinenliste
ohne Namen unbedienbar ist – ein Maschinenname ist kein Personendatum.
`beschreibung` und `code_bild_pfad` bleiben draußen.

**Akzeptanzkriterium:** Terminal offline, Auftragsstart mit Maschinenauswahl –
die Liste zeigt dieselben aktiven Maschinen wie online, und die gewählte
`maschine_id` steht im Queue-SQL.

**Prüfung:** Spiegel entsteht online (Zeilenzahl gegen `maschine` prüfen),
offline Auswahl durchspielen, Queue-Eintrag gegenlesen. Gegenprobe ohne
Ausweichdatenbank: Die Auswahl muss leer bleiben und der Start trotzdem
funktionieren (ohne Maschine), nicht abbrechen.

## 6. Was ausdrücklich nicht dazugehört

- **Urlaub, Auswertungen, Übersichten offline** – die Fachregel schließt sie
  aus, daran ändert dieses Vorhaben nichts.
- **Zwei Terminals, dieselbe Person, beide offline.** Beim Einspielen entstehen
  dann zwei laufende Aufträge; der erste Stopp trifft den, den sein `WHERE`
  zuerst findet. Das ist heute schon so und wird hier nicht gelöst – wer es
  lösen will, braucht E1 neu beantwortet.
- **Auftragsliste offline.** Erst nachziehen, wenn der Praxis-Test zeigt, dass
  freie Eingabe nicht reicht. Der Weg dafür ist P3, noch einmal.
