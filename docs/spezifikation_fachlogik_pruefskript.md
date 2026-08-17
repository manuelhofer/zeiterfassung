# Spezifikation: Fachlogik-Prüfskript (T-140)

Zielbild und Akzeptanzkriterien für das Skript, das die Rechenkerne nachrechnet.
Noch nicht gebaut – **diese Datei ist die Abnahme vor dem Bauen** (Arbeitsregeln,
Abschnitt 1).

---

## 1. Warum

Die Pflichtprüfung nach einer Änderung prüft heute, dass der Code **läuft**,
nicht dass er **richtig rechnet**:

- `php -l` ist Syntax.
- Der Klicktest zeigt eine Zahl im Browser. Bei 8:36 statt 8:30 erkennt sie
  niemand.
- Die Prüfumgebung vergleicht zwei Stände gegeneinander. Rechnen beide falsch,
  ist sie zufrieden – sie kennt kein Soll.

Betroffen sind die Stellen, an denen ein Fehler still bleibt und am Monatsende
Stunden verschiebt: Rundung, Pausenabzug, Urlaubssalden, Monatsraster. Die
TEST-Abschnitte im Verlauf sind sorgfältig, aber Einmalläufe; sie laufen nie
wieder.

Abschnitt 5 der Arbeitsregeln verlangt seit P-2026-08-17-08, dass eine Änderung
an Rundung, Pausen oder Salden einen nachrechnenden Fall als wiederholbare
Prüfung hinterlässt. Dieses Skript ist der Ort dafür.

## 2. Zielbild

Ein Aufruf, alle Fälle, eine Zeile Ergebnis:

```
$ scripts/dev/pruefumgebung.sh pruefen
Rundung ................ 9 von 9 OK
Pausen ................. 5 von 5 OK
Monatsraster ........... 2 von 2 OK
Doppelzaehlung ......... 2 von 2 OK
Feiertag+Arbeitszeit ... 2 von 2 OK

20 von 20 OK
```

Und im Fehlerfall genau das, was fehlt:

```
Rundung ................ 8 von 9 OK
  Fall 4 (Kommen 07:03, Regel 15min naechstgelegen):
    erwartet 07:00, bekommen 07:15

19 von 20 OK
```

Rückgabewert 0 bei Erfolg, 1 bei mindestens einem Fehlschlag – damit der Lauf
später ohne Änderung in einen Hook oder eine Pipeline passt.

## 3. Bauart

**Gegen die Probe-Datenbank, nicht gegen Attrappen.** Das Skript hängt sich an
`zeit_probe` aus [`lokale_entwicklungsumgebung.md`](lokale_entwicklungsumgebung.md)
bzw. an die Prüfumgebung (`scripts/dev/pruefumgebung.sh aufbauen`) und ruft die
**öffentliche API** der Services auf – denselben Weg, den das Backend nimmt.
Grund: Ein Test gegen nachgebaute Rundungsregeln prüft den Nachbau.

Daraus folgt, was das Skript ist und was nicht:

- **Es braucht MariaDB.** Es ist ein Entwicklungswerkzeug und läuft dort, wo die
  Entwicklungsumgebung steht. Auf dem Produktivsystem hat es nichts zu tun.
- **Es fasst `zeiterfassung` und `zeiterfassung_offline` nicht an.** Wie
  `pruefumgebung.sh` bricht es ab, wenn der Datenbankname nicht mit `zeit_probe`
  beginnt. Diese Sperre ist Teil der Abnahme, nicht Beiwerk.
- **Es seedet seine Fälle selbst** und räumt sie hinterher weg: Rundungsregeln,
  einen Probe-Mitarbeiter, Buchungen, Feiertage, Betriebsferien. Zweimal
  hintereinander laufen muss dasselbe Ergebnis liefern (Idempotenz).
- **Kein Composer, kein Framework.** Eine PHP-Datei plus die Fälle. PHP-Baseline
  8.2, wie der Rest.

## 4. Umfang der ersten Fassung

### 4a. Rundung (`RundungsService::rundeZeitstempel`)
Regeln werden vor dem Lauf gesetzt, nicht vorausgesetzt. Fälle mindestens:
Grenze 07:00 (davor 30 min, danach 15 min), je Richtung `auf`, `ab`,
`naechstgelegen`, ein Wert genau auf der Rundungsgrenze, Mitternacht und
23:59 als Ränder.

### 4b. Pausen (`PausenService::berechnePausenMinutenFuerBlock`)
Block ohne Pausenfenster, Block genau an der Schwelle, Block über zwei Fenster,
Block innerhalb eines Fensters, Block mit Ende vor Beginn (Fehlerfall).

### 4c. Urlaubssalden (`UrlaubService::berechneUrlaubssaldoFuerJahr`)
Kontingent mit und ohne Übertrag, ein genehmigter Antrag, ein Betriebsferientag,
ein Feiertag im Antragszeitraum. Deckt den Beobachtungspunkt aus B-080
(Jahreswechsel) mit ab, sobald ein zweites Jahr dazukommt.

### 4d. Die drei Fachprüfungen aus dem Smoke-Test
`Monatsraster`, `Doppelzaehlung` und `Feiertag+Arbeitszeit` prüfen bereits
Fachlogik – nur an einem Ort, der Login, Browser und eine handgetippte
Mitarbeiter-ID braucht. Sie kommen mit.

**Dafür ist ein Umbau nötig, und er ist Teil dieses Vorhabens:** Die Methoden
`pruefeMonatsraster()`, `pruefeDoppelzaehlung()` und
`pruefeFeiertagUndArbeitszeit()` sind `private` in
`controller/SmokeTestController.php` und lesen `$_POST` in ihren ersten Zeilen.

Zielbild des Umbaus:

- Ein neuer `services/FachpruefungService.php` bekommt die drei Prüfungen als
  **öffentliche** Methoden mit der Signatur
  `(int $mitarbeiterId, int $jahr, int $monat): array` und dem heutigen
  Rückgabebündel (`ok`, Zähler, Listen) – **Rumpf unverändert**.
- `SmokeTestController` liest weiter `$_POST` und delegiert. Die Views in
  `views/smoke_test/` bleiben unangetastet, weil sich das Bündel nicht ändert.
- Das Prüfskript ruft denselben Service auf.

Ein Nebeneffekt, der zu T-142 gehört und hier gratis anfällt:
`SmokeTestController` wird um die drei Rümpfe kleiner.

## 5. Akzeptanzkriterien

Je eigener Patch, je ein Kriterium:

1. **Umbau:** `views/smoke_test/monatsraster.php`, `doppelzaehlung.php` und
   `feiertag_arbeitszeit.php` liefern über die Prüfumgebung byteweise dasselbe
   HTML wie vor dem Umbau, bei gleichen Probe-Daten und gleichem Formularwert.
2. **Skript, Grundgerüst:** `php scripts/dev/pruefe_fachlogik.php` gegen eine
   Datenbank, deren Name nicht mit `zeit_probe` beginnt, bricht ab, ohne eine
   Tabelle zu lesen oder zu schreiben, und liefert Rückgabewert 1.
3. **Rundung und Pausen:** Ein Lauf meldet für jeden Fall aus 4a und 4b „OK",
   und wenn `einheit_minuten` einer Regel von 15 auf 30 geändert wird, meldet
   genau der betroffene Fall den erwarteten und den bekommenen Wert.
4. **Salden und Fachprüfungen:** Ein Lauf deckt 4c und 4d ab; zweimal
   hintereinander gestartet liefert er beide Male dasselbe Ergebnis.

## 6. Was bewusst nicht dazugehört

- **Keine Prüfung von Oberflächen.** Wer HTML vergleichen will, nimmt
  `pruefumgebung.sh vergleichen` – dafür ist sie da.
- **Kein Test-Framework, keine CI.** Beides wäre eine eigene Entscheidung. Das
  Skript ist so gebaut, dass es später ohne Änderung in einen Hook passt (siehe
  Rückgabewert), aber es bringt keinen mit.
- **Keine Attrappen für Services.** Wenn ein Rechenkern nur mit Datenbank
  aufrufbar ist, ist das ein Befund über den Code, kein Grund für einen Nachbau
  im Test. Das Herauslösen der reinen Arithmetik aus `RundungsService` und
  `PausenService` stand zur Wahl und ist **verworfen** worden, weil es genau den
  Pfad ändert, für den es noch kein Netz gibt. Sobald das Netz steht, darf die
  Frage neu gestellt werden.
