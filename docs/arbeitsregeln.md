# Arbeitsregeln

Wie in diesem Projekt gearbeitet wird – unabhängig davon, welches Werkzeug oder
Modell gerade eingesetzt wird. Diese Datei gilt für **jede** Änderung.

---

## 0. Verhandelbar ist alles davon

Jede Regel hier ist besprechbar – sie steht nicht da, weil sie unantastbar
wäre, sondern weil sie sich bewährt hat. Steht eine im Weg oder ist sie falsch:
**sagen**, mit Begründung. Was nicht geht, ist Stillschweigen in beide
Richtungen: eine Regel kommentarlos umgehen, oder ihr sehenden Auges in ein
schlechtes Ergebnis folgen. Geändert wird sie als eigener Patch mit Begründung
im Verlauf.

Das gilt für den ganzen Arbeitsweg, nicht nur für diese Liste: Was am Ablauf
stört – ein Schritt, der jedes Mal Zeit kostet, eine Datei am falschen Ort,
eine Doku, die niemand mehr liest, eine Vorgabe, die keiner gestellt hat –,
wird **angesprochen**, nicht ausgehalten.

## 1. Wann überhaupt gearbeitet wird

Ob gerade gearbeitet wird, entscheidet der **Projektstatus** in
[STATUS_SNAPSHOT.md](STATUS_SNAPSHOT.md).

Neue Funktionsbereiche werden **zuerst spezifiziert** (kurzes Zielbild +
Akzeptanzkriterien), bevor implementiert wird.

## 2. Vor der Änderung: Pre-Flight-Gate (Pflicht)

Ziel: verhindern, dass bereits Erledigtes ein zweites Mal gebaut wird.

1. **Lesen:** die drei Immer-Zeilen und die passende Themenzeile aus der
   Lesekarte in `CHATSTART.md` – nicht mehr.
2. **Duplicate-Check:** Prüfen, ob das Ziel schon erledigt ist – in der History
   (T-/B-/D-IDs, „DONE") **und** im Git-Verlauf (`git log --oneline`,
   `git log -S"<begriff>"`). Wenn ja: **nicht erneut implementieren**, sondern
   den nächsten offenen Punkt nehmen oder nachfragen.
3. **Task-Disziplin:** Umgesetzt wird nur, was unter „Offene Tasks" steht oder
   ausdrücklich beauftragt wurde.

## 3. Zuschnitt einer Änderung

- **1 Patch = 1 Thema = 1 sichtbarer Effekt.** Keine Misch-Patches (also nicht
  gleichzeitig UI + DB + PDF „nebenbei").
- Jeder Patch braucht **ein Akzeptanzkriterium in genau einem Satz** – ein
  konkretes Beispiel mit Erwartung – und dokumentiert es im History-Eintrag.
- **Keine Refactors nebenbei.** Geändert wird nur, was für das
  Akzeptanzkriterium nötig ist. Fällt dabei etwas anderes auf, wird es notiert
  statt mitgemacht – und als eigener Patch **abgearbeitet**, möglichst gleich
  danach. Die Notiz ist ein Aufschub, keine Ablage: Wer den Fehler gefunden
  hat, weiß am meisten über ihn.
- Es gibt **kein hartes Dateilimit**. Aber: Wenn ein Patch auffällig viele
  Dateien anfasst, ist das ein Warnsignal – dann prüfen, ob zwei Themen
  vermischt werden, und aufteilen.

## 4. Ergebnis ist ein Commit

- Geändert wird **direkt in der Arbeitskopie**; das Repository ist die Quelle
  der Wahrheit. Keine ZIP-Pakete, keine Datei-Dumps im Chat – ZIP nur auf
  ausdrücklichen Wunsch (z. B. für einen Server ohne Git).
- Was zusammengehört, kommt in **einen** Commit; was nicht, in getrennte.
- **Patch-ID** `P-YYYY-MM-DD-XX` (Datum Europe/Berlin, `XX` fortlaufend am Tag)
  steht im **Commit-Betreff**, gefolgt von einer kurzen Beschreibung in
  `kebab-case`:

  ```
  P-2026-08-08-01 report-kommen-gehen
  ```

- Zu jeder Patch-ID gehört ein Eintrag in `docs/archiv/DEV_PROMPT_HISTORY.md`
  – im **selben Commit**.
- **Gepusht wird nur auf ausdrückliche Ansage.**
- Erklärungen im Chat sind erwünscht: kurz, sachlich, deutsch – was geändert
  wurde, warum, und was bewusst **nicht** gemacht wurde.

## 5. Nach der Änderung: Pflichtprüfung

- `php -l` über **alle** geänderten PHP-Dateien.
- Die betroffenen Kernabläufe aus `docs/wartungscheckliste.md` durchklicken.
- Bei DB-Änderungen: Neuinstallation aus `sql/01_initial_schema.sql` muss
  weiterhin funktionieren, und die Migration muss zweimal hintereinander
  durchlaufen (Idempotenz).
- Auf **Meldungsfreiheit** prüfen: keine Deprecations, keine Warnungen im Log.

## 6. Zwei Dateien, zwei Aufgaben

Bei **jedem** Patch werden beide gepflegt, im selben Commit:

**`docs/STATUS_SNAPSHOT.md` – der Stand.** Die einzige Stelle für
Projektstatus, nächsten Schritt, offene Bugs (B-IDs) und Tasks (T-IDs).
Erledigtes wird hier **entfernt**, nicht abgehakt – die Begründung steht im
Verlaufseintrag.

**`docs/archiv/DEV_PROMPT_HISTORY.md` – der Verlauf.** Chronologisch
absteigend, ein Eintrag je Patch, **nie** gelöscht: Patch-ID, EINGELESEN,
DATEIEN, AKZEPTANZKRITERIUM, DONE, TEST, NEXT. Kein Statusabbild an ihrem
Anfang – dafür gibt es den Snapshot.

Was in den Eintrag gehört und oft vergessen wird:

- **Gefundene Fehler im eigenen Entwurf** – wer sie verschweigt, lässt den
  Nächsten dieselbe Falle bauen.
- **Was bewusst nicht erreicht wurde**, mit Begründung.
- **Was tatsächlich getestet wurde**, nicht was getestet werden könnte.

## 7. Technik und Stil

**Zielsystem produktiv:** Debian, Apache 2.4, MySQL/MariaDB. Terminal und
Backup-Datenbank laufen auch auf einem **Raspberry Pi** – der Code muss dort
genauso laufen, also Ressourcenbedarf niedrig halten und keine schweren
Abhängigkeiten.

**PHP-Baseline:**

- **Minimum 8.2** (Debian 12 / Raspberry Pi OS Bookworm). Keine Sprachfeatures,
  die neuer sind.
- **Muss auf aktuellem PHP sauber laufen** (entwickelt und getestet gegen
  PHP 8.5): keine Deprecation-Meldungen, keine Warnungen im Log.
- Kollidiert beides, gewinnt die Lauffähigkeit auf der Minimalversion – der
  neuere Weg wird dann als Kommentar vermerkt.

**Kein großes Webframework** (kein Laravel, Symfony, Yii). Kleine Bibliotheken
(PDF, Barcode) sind erlaubt, wenn sie lokal im Projekt mitgeliefert werden.

**Keine Container in der Produktion** – installiert wird nativ auf
Debian/Apache. Auch die lokale Entwicklungsumgebung ist nativ aufgesetzt.

**Routing** klassisch per `index.php?seite=…` bzw. `terminal.php?aktion=…` über
`$_GET`/`$_POST` und `switch`/`if`. Die Verzeichnisstruktur steht in
[`README.md`](../README.md).

**Stil:**

- Leichtgewichtiges, sinnvolles OOP – modular, testbar, erweiterbar. Kein
  Enterprise-OOP, keine überkomplexen Vererbungsbäume.
- **Deutsch:** Oberfläche, Variablennamen, Kommentare
  (`$mitarbeiter`, `$zeitbuchung`, `$urlaubService`).
- **Umlaute schreiben, nicht umschreiben:** `ä ö ü ß` überall, wo Text für
  Menschen steht – Oberfläche, Kommentare, Dokumentation **und Verlauf**. Nie
  `ae oe ue ss`. Alles ist UTF-8. Ausgenommen bleiben Bezeichner, Dateinamen,
  Datenbankfelder und -werte, Konfigurationsschlüssel, Routen- und
  Commit-Namen und Shell-Skripte – dort gilt weiterhin ASCII. **Auch dann,
  wenn sie in einem Kommentar, einer Tabelle oder mitten im Fließtext zitiert
  werden:** Ein Name behält seine Schreibweise, egal wo er auftaucht. Im
  Commit heißt das: **Betreff ASCII** (er ist ein Name), **Text darunter mit
  Umlauten** (er ist für Menschen).
- Kommentare erklären vor allem das **Warum**, nicht das Was.
- DB-Zugriff **immer** PDO mit Prepared Statements. Wo das nicht geht (die
  Offline-Queue speichert fertiges SQL), maskieren über `Helper::sqlLiteral()`
  – nirgends von Hand.
- Sessions für Login und Berechtigungen, CSRF-Schutz über `core/Csrf.php`.
- Sauberes Fehler-Handling, besonders für DB-Verbindungen und den
  Offline-Modus. Wichtige Aktionen und Fehler nach `system_log` über `Logger`.
- Saubere Trennung von **Rohdaten und Rundung**.
- Zeitzone `Europe/Berlin`.

**Kein Spaghetti-PHP:** `index.php` als Einstieg und Router, Controller für
Aktionen, Modelle für die DB, Services für Geschäftslogik, Views für die
Darstellung.

## 8. SQL im Chat

Zusätzlich zu den Migrationsdateien dürfen auf Wunsch **reine SQL-Statements**
im Chat ausgegeben werden, die sich 1:1 in phpMyAdmin einfügen lassen (kleine
Hotfixes, Prüf-Queries). Strukturänderungen gehören trotzdem **immer** nach
`sql/`.

## 9. Am Ende: Kaltstart klein halten

Jeder neue Chat liest `CLAUDE.md`, `CHATSTART.md`, diese Datei und den Snapshot,
**bevor** er irgendetwas tun kann – auch bei der kleinsten Frage. Was hier
steht, kostet jedes Mal: **so kurz wie möglich, aber nicht kürzer.** Braucht
eine Sache ein paar Zeilen mehr, bekommt sie die. Eine Byte-Grenze gibt es
**nicht** (warum nicht: P-2026-08-15-25). Neue Erklärungen gehören in die
Dateien, die nur bei Bedarf gelesen werden (Fachregeln, Spezifikation,
Handbuch); was von Natur aus wächst, ist der Snapshot – **ein Satz je Bug und
Task**, die Begründung steht im Verlauf.

Zum Abschluss einer Sitzung: Erledigtes aus dem Snapshot **entfernen**, keine
ableitbare Zahl pflegen (Prozente, Patch-Listen, Verlaufsabrisse, Zeilenzahlen
von Dateien – sie driften alle), Links gegenprüfen, wenn Dateien verschoben
wurden.
