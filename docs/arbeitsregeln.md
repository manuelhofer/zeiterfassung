# Arbeitsregeln

Wie in diesem Projekt gearbeitet wird – unabhängig davon, welches Werkzeug oder
Modell gerade eingesetzt wird. Diese Datei gilt für **jede** Änderung.

---

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
  Akzeptanzkriterium nötig ist. Fällt dabei etwas anderes auf, wird es als
  offener Punkt notiert, nicht sofort mitgemacht.
- Es gibt **kein hartes Dateilimit**. Aber: Wenn ein Patch auffällig viele
  Dateien anfasst, ist das ein Warnsignal – dann prüfen, ob zwei Themen
  vermischt werden, und aufteilen.

## 4. Ergebnis ist ein Commit

- Geändert wird **direkt in der Arbeitskopie**; das Repository ist die Quelle
  der Wahrheit. Keine ZIP-Pakete, keine Datei-Dumps im Chat. (ZIP nur, wenn der
  Nutzer es ausdrücklich anfordert – z. B. für einen Server ohne Git.)
- Was zusammengehört, kommt in **einen** Commit; was nicht zusammengehört, in
  getrennte.
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
Projektstatus, **„Nächster Schritt (konkret)"**, offene Bugs (B-IDs) und
offene Tasks (T-IDs). Wer nur wissen will, was ansteht, liest diese Datei und
sonst nichts. Erledigtes wird hier **entfernt**, nicht abgehakt – die
Begründung steht im Verlaufseintrag.

**`docs/archiv/DEV_PROMPT_HISTORY.md` – der Verlauf.** Chronologisch
absteigend, ein Eintrag je Patch, **nie** gelöscht: Patch-ID, EINGELESEN,
DATEIEN, AKZEPTANZKRITERIUM, DONE, TEST, NEXT. Sie wird nur gelesen, wenn zu
einem bestimmten Patch nachzuschlagen ist – deshalb gehört **kein**
Statusabbild an ihren Anfang.

Was in den Eintrag gehört und oft vergessen wird:

- **Gefundene Fehler im eigenen Entwurf** – wer sie verschweigt, lässt den
  Nächsten dieselbe Falle bauen.
- **Was bewusst nicht erreicht wurde**, mit Begründung.
- **Was tatsächlich getestet wurde**, nicht was getestet werden könnte.

## 7. Technik und Stil

**Zielsystem produktiv:** Debian, Apache 2.4, MySQL/MariaDB. Terminal und
Backup-Datenbank laufen auch auf einem **Raspberry Pi** – Ressourcenbedarf
bewusst niedrig halten, keine schweren Abhängigkeiten. Der Code muss auf diesem
Gerät genauso laufen wie auf dem Server.

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
- **Umlaute schreiben, nicht umschreiben:** `ä ö ü ß` in Kommentaren und
  Oberflächentexten, nicht `ae oe ue ss`. Alles ist UTF-8. Ausgenommen bleiben
  Bezeichner, Dateinamen, Datenbankfelder und -werte, Konfigurationsschlüssel
  und Shell-Skripte – dort gilt weiterhin ASCII.
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
bevor er etwas tun kann – zusammen unter **15 KB** halten. Neue Erklärungen
gehören deshalb in die Dateien, die nur bei Bedarf gelesen werden (Fachregeln,
Spezifikation, Handbuch). Nachmessen:

```bash
cat CLAUDE.md CHATSTART.md docs/arbeitsregeln.md docs/STATUS_SNAPSHOT.md | wc -c
```

Zum Abschluss einer Sitzung: Erledigtes aus dem Snapshot **entfernen**, keine
ableitbare Zahl pflegen (Prozente, Patch-Listen, Verlaufsabrisse – sie driften),
Links gegenprüfen, wenn Dateien verschoben wurden.
