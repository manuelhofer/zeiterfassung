# Arbeitsregeln

Wie in diesem Projekt gearbeitet wird – unabhaengig davon, welches Werkzeug
oder Modell gerade eingesetzt wird. Diese Datei gilt fuer **jede** Aenderung.

*Herkunft:* Master-Prompt v13, Abschnitte 1, 18, 19 und 20. Die Begruendung,
welche Regeln aus v12 entfielen und warum, steht im archivierten Master-Prompt
(`docs/archiv/master_prompt_zeiterfassung_v13.md`, Abschnitt 1a).

---

## 1. Projektstatus bestimmt, wann ueberhaupt gearbeitet wird

Das System ist **fertig** und im **Praxis-Test**. Gearbeitet wird **nur**, wenn

- ein **Bug gefunden** wurde – dann zuerst ein reproduzierbarer Bugreport
  (Schritte / Erwartung / Ist), danach ein kleiner, nachvollziehbarer Patch,
- oder der Nutzer **ausdruecklich** eine Erweiterung beauftragt.

Neue Funktionsbereiche werden **zuerst spezifiziert** (kurzes Zielbild +
Akzeptanzkriterien), bevor implementiert wird.

## 2. Vor der Aenderung: Pre-Flight-Gate (Pflicht)

Ziel: verhindern, dass bereits Erledigtes ein zweites Mal gebaut wird.

1. **Lesen** (siehe `CHATSTART.md` fuer die Lesekarte):
   - `docs/STATUS_SNAPSHOT.md` – immer; dort steht der Stand vollstaendig,
     inklusive „Naechster Schritt (konkret)",
   - die passende Datei aus `docs/fachregeln/` – nur die zum Thema,
   - `docs/rechte_prompt.md` – immer, wenn Rechte beruehrt werden,
   - `sql/01_initial_schema.sql` – immer, wenn die Datenbank beruehrt wird.
2. **Duplicate-Check:** Pruefen, ob das Ziel schon erledigt ist – in der History
   (T-/B-/D-IDs, „DONE") **und** im Git-Verlauf (`git log --oneline`,
   `git log -S"<begriff>"`). Wenn ja: **nicht erneut implementieren**, sondern
   den naechsten offenen Punkt nehmen oder nachfragen.
3. **Task-Disziplin:** Umgesetzt wird nur, was unter „Offene Tasks" steht oder
   ausdruecklich beauftragt wurde.

## 3. Zuschnitt einer Aenderung

- **1 Patch = 1 Thema = 1 sichtbarer Effekt.** Keine Misch-Patches (also nicht
  gleichzeitig UI + DB + PDF „nebenbei").
- Jeder Patch braucht **ein Akzeptanzkriterium in genau einem Satz** – ein
  konkretes Beispiel mit Erwartung – und dokumentiert es im History-Eintrag.
- **Keine Refactors nebenbei.** Geaendert wird nur, was fuer das
  Akzeptanzkriterium noetig ist. Faellt dabei etwas anderes auf, wird es als
  offener Punkt notiert, nicht sofort mitgemacht.
- Es gibt **kein hartes Dateilimit**. Aber: Wenn ein Patch auffaellig viele
  Dateien anfasst, ist das ein Warnsignal – dann pruefen, ob zwei Themen
  vermischt werden, und aufteilen.

## 4. Ergebnis ist ein Commit

- Geaendert wird **direkt in der Arbeitskopie**; das Repository ist die Quelle
  der Wahrheit. Keine ZIP-Pakete, keine Datei-Dumps im Chat. (ZIP nur, wenn der
  Nutzer es ausdruecklich anfordert – z. B. fuer einen Server ohne Git.)
- Was zusammengehoert, kommt in **einen** Commit; was nicht zusammengehoert, in
  getrennte.
- **Patch-ID** `P-YYYY-MM-DD-XX` (Datum Europe/Berlin, `XX` fortlaufend am Tag)
  steht im **Commit-Betreff**, gefolgt von einer kurzen Beschreibung in
  `kebab-case`:

  ```
  P-2026-08-08-01 report-kommen-gehen
  ```

- Zu jeder Patch-ID gehoert ein Eintrag in `docs/archiv/DEV_PROMPT_HISTORY.md`
  – im **selben Commit**.
- **Gepusht wird nur auf ausdrueckliche Ansage.**
- Erklaerungen im Chat sind erwuenscht: kurz, sachlich, deutsch – was geaendert
  wurde, warum, und was bewusst **nicht** gemacht wurde.

## 5. Nach der Aenderung: Pflichtpruefung

- `php -l` ueber **alle** geaenderten PHP-Dateien.
- Die betroffenen Kernablaeufe aus `docs/wartungscheckliste.md` durchklicken.
- Bei DB-Aenderungen: Neuinstallation aus `sql/01_initial_schema.sql` muss
  weiterhin funktionieren, und die Migration muss zweimal hintereinander
  durchlaufen (Idempotenz).
- Auf **Meldungsfreiheit** pruefen: keine Deprecations, keine Warnungen im Log.

## 6. Zwei Dateien, zwei Aufgaben

Bei **jedem** Patch werden beide gepflegt, im selben Commit:

**`docs/STATUS_SNAPSHOT.md` – der Stand.** Die einzige Stelle fuer
Projektstatus, **„Naechster Schritt (konkret)"**, offene Bugs (B-IDs) und
offene Tasks (T-IDs). Wer nur wissen will, was ansteht, liest diese Datei und
sonst nichts. Erledigtes wird hier **entfernt**, nicht abgehakt – die
Begruendung steht im Verlaufseintrag.

**`docs/archiv/DEV_PROMPT_HISTORY.md` – der Verlauf.** Chronologisch
absteigend, ein Eintrag je Patch, **nie** geloescht: Patch-ID, EINGELESEN,
DATEIEN, AKZEPTANZKRITERIUM, DONE, TEST, NEXT. Sie wird nur gelesen, wenn zu
einem bestimmten Patch nachzuschlagen ist – deshalb gehoert **kein**
Statusabbild an ihren Anfang.

Was in den Eintrag gehoert und oft vergessen wird:

- **Gefundene Fehler im eigenen Entwurf** – wer sie verschweigt, laesst den
  Naechsten dieselbe Falle bauen.
- **Was bewusst nicht erreicht wurde**, mit Begruendung.
- **Was tatsaechlich getestet wurde**, nicht was getestet werden koennte.

**Keine handgepflegten Listen fuer Dinge, die Git schon weiss.** Welche Patches
es gab, sagt `git log --oneline` – genauer und immer aktuell.

## 7. Technik und Stil

**Zielsystem produktiv:** Debian, Apache 2.4, MySQL/MariaDB. Terminal und
Backup-Datenbank laufen auch auf einem **Raspberry Pi** – Ressourcenbedarf
bewusst niedrig halten, keine schweren Abhaengigkeiten.

**PHP-Baseline:**

- **Minimum 8.2** (Debian 12 / Raspberry Pi OS Bookworm). Keine Sprachfeatures,
  die neuer sind.
- **Muss auf aktuellem PHP sauber laufen** (entwickelt und getestet gegen
  PHP 8.5): keine Deprecation-Meldungen, keine Warnungen im Log.
- Kollidiert beides, gewinnt die Lauffaehigkeit auf der Minimalversion – der
  neuere Weg wird dann als Kommentar vermerkt.

**Kein grosses Webframework** (kein Laravel, Symfony, Yii). Kleine Bibliotheken
(PDF, Barcode) sind erlaubt, wenn sie lokal im Projekt mitgeliefert werden.

**Keine Container in der Produktion** – installiert wird nativ auf
Debian/Apache. Auch die lokale Entwicklungsumgebung ist nativ aufgesetzt.

**Routing** klassisch per `index.php?seite=…` bzw. `terminal.php?aktion=…` ueber
`$_GET`/`$_POST` und `switch`/`if`.

**Struktur:**

| Ordner | Inhalt |
| --- | --- |
| `public/` | Einstiegspunkte, CSS, JS, Bilder |
| `core/` | DB-Handling, Session, Helper, Logging, Offline-Queue, Feiertagsgenerator |
| `modelle/` | reine Datenzugriffe |
| `services/` | Geschaeftslogik je Domaene |
| `controller/` | Request-Verarbeitung |
| `views/` | Templates |
| `sql/` | Initialschema und Migrationen |
| `docs/` | Dokumentation |

**Stil:**

- Leichtgewichtiges, sinnvolles OOP – modular, testbar, erweiterbar. Kein
  Enterprise-OOP, keine ueberkomplexen Vererbungsbaeume.
- **Deutsch:** Oberflaeche, Variablennamen, Kommentare
  (`$mitarbeiter`, `$zeitbuchung`, `$urlaubService`).
- Kommentare erklaeren vor allem das **Warum**, nicht das Was.
- DB-Zugriff **immer** PDO mit Prepared Statements.
- Sessions fuer Login und Berechtigungen.
- Sauberes Fehler-Handling, besonders fuer DB-Verbindungen und den
  Offline-Modus. Wichtige Aktionen und Fehler nach `system_log`.
- Zeitzone `Europe/Berlin`.

**Kein Spaghetti-PHP:** `index.php` als Einstieg und Router, Controller fuer
Aktionen, Modelle fuer die DB, Services fuer Geschaeftslogik, Views fuer die
Darstellung.

## 8. Worauf bei Code-Aenderungen besonders zu achten ist

- saubere Trennung von **Rohdaten und Rundung**,
- nachvollziehbare **Offline-/Queue-Verarbeitung**,
- klar strukturierte **Rollen- und Genehmigerlogik**,
- uebersichtliche Backend-Oberflaechen,
- Lauffaehigkeit auf der PHP-Baseline – der Code muss auch auf dem
  Raspberry-Pi-Terminal funktionieren.

## 9. SQL im Chat

Zusaetzlich zu den Migrationsdateien duerfen auf Wunsch **reine SQL-Statements**
im Chat ausgegeben werden, die sich 1:1 in phpMyAdmin einfuegen lassen (kleine
Hotfixes, Pruef-Queries). Strukturaenderungen gehoeren trotzdem **immer** nach
`sql/`.
