# Aufräumplan – abarbeitbare Prompt-Sammlung

*Erstellt am 09.08.2026. Grundlage: vollständiger Durchgang durch Doku, PHP-Code,
Views, SQL und Skripte.*

**Diese Datei ist kein Projektdokument, sondern eine Arbeitsliste.** Sie liegt
bewusst untracked im Wurzelverzeichnis. Wenn alles abgearbeitet ist, wird sie
gelöscht – nicht eingecheckt, nicht gepflegt.

---

## Wie diese Liste zu benutzen ist

Jeder Punkt unten ist **ein Patch nach den Regeln des Projekts**: ein Thema, ein
Akzeptanzkriterium in einem Satz, ein Commit, ein Eintrag in
`docs/archiv/DEV_PROMPT_HISTORY.md`. Die Punkte sind absichtlich einzeln
formuliert, damit man sie in beliebiger Reihenfolge und über mehrere Sitzungen
abarbeiten kann.

**Einstieg für eine neue Sitzung:**

> Lies `CHATSTART.md` und `docs/arbeitsregeln.md`. Arbeite dann Punkt **X-n** aus
> `AUFRAEUMPLAN.md` ab – nur diesen einen. Halte dich an das Pre-Flight-Gate,
> committe lokal mit Patch-ID und pflege den History-Eintrag im selben Commit.
> Pushe nicht.

**Eine Vorbemerkung zur Regel „Keine Refactors nebenbei".** Die gilt weiter. Was
hier steht, ist kein Nebenbei: Es ist ausdrücklich beauftragt und jeder Punkt
ist selbst das Thema seines Patches. Verboten bleibt, *während* Punkt E-1 noch
schnell Punkt F-2 mitzunehmen.

**Reihenfolge:** Phase 1 zuerst (das sind echte Fehler). Phase 2 ist davon
unabhängig und kann jederzeit vorgezogen werden – es ist der Punkt, um den es
ursprünglich ging. Phase 4 muss vor Phase 5 laufen, sonst räumt man Code auf,
den man gleich darauf löscht.

---

## Phase 0 – Entscheidungen, die vorher zu treffen sind

Fünf Punkte lassen sich nicht aus dem Repository ableiten. Ohne Antwort darauf
sollten die betroffenen Patches nicht laufen.

| # | Frage | Empfehlung |
| --- | --- | --- |
| **E-a** | **Umlaute oder `ae/oe/ue`?** Beides ist im Projekt verbreitet, teils in derselben Datei (siehe F-3). | **Umlaute**, in Kommentaren und Oberflächentexten. Der Code führt 1.918 Zeilen mit Umlauten gegen einige hundert mit Ersatzschreibung, alles ist UTF-8, und README, `docs/README.md` und das Admin-Handbuch machen es schon so. Ersatzschreibung bleibt für Bezeichner, Dateinamen, Datenbankwerte und Shell-Skripte. |
| **E-b** | **B-092 Betriebsferien:** Methode nachrüsten oder tote Route entfernen? | **Nachrüsten.** `betriebsferien.aktiv` wird von allen Lesern ausgewertet (`WHERE aktiv = 1`), lässt sich aber über die Oberfläche nie umstellen. Kurzarbeit hat dieselbe Maske vollständig – daran entlangbauen. Details in D-3. |
| **E-c** | **Controller mit Inline-HTML** (9 Stück, bis zu 283 HTML-Zeilen): auflösen oder so lassen? | **So lassen, aber die toten Zwillinge löschen** (C-4). Das Umziehen des HTML in die Views ist ein eigenes Vorhaben mit echtem Regressionsrisiko – als T-ID im Snapshot notieren, nicht in diesem Durchgang. |
| **E-d** | **`?seite=urlaubsplanung`**: Alias auf `urlaub_jahresuebersicht`, nirgends verlinkt. Behalten oder streichen? | **Behalten und als Alt-Link kommentieren.** Kostet eine Zeile, und ein Lesezeichen im Betrieb darf nicht ins Leere laufen. |
| **E-e** | **`services/phpqrcode/`** (13 Dateien) wird nur noch für einen Notfall-Rückfall gebraucht, der ein unlesbares Ergebnis liefert (siehe D-4). Nach D-4 ist die Bibliothek tot. Entfernen? | **Ja, aber in einem eigenen Patch nach D-4.** Erst den Rückfall abschaffen, dann sehen, ob wirklich nichts mehr ruft. |

---

## Phase 1 – Echte Fehler

### D-1 · Die Mini-SQL-Helfer der Offline-Queue entschärfen dürfen keinen Backslash durchlassen

**Das ist der wichtigste Punkt der ganzen Liste.**

An fünf Stellen wird SQL für die Offline-Queue von Hand zusammengesetzt, weil
Prepared Statements dort nicht möglich sind – der fertige SQL-Text wird
gespeichert und später ausgeführt. Das Quoting lautet überall:

```php
return "'" . str_replace("'", "''", $val) . "'";
```

Das verdoppelt einfache Anführungszeichen, lässt den **Backslash** aber
unangetastet. MySQL und MariaDB behandeln `\` in Standardeinstellung als
Fluchtzeichen. Ein Wert, der auf `\` endet, erzeugt damit `'…\'` – das
schließende Anführungszeichen wird geschluckt, der String läuft weiter, und was
danach konkateniert wird, landet als SQL im Queue-Eintrag.

Die Werte kommen aus Terminal-Eingaben: `$auftragscode` und
`$arbeitsschrittCode` stammen direkt aus dem Scan- bzw. Eingabefeld. Der
eingeschleuste Befehl wird nicht am Terminal ausgeführt, sondern **später von
der Warteschlange gegen die Hauptdatenbank**. Ein Angreifer braucht Zugang zum
Hallengerät, das schon – aber genau dafür ist die Queue-Abarbeitung der
denkbar schlechteste Ort für eine Lücke.

**Betroffen:**

- [controller/TerminalController.php:3623](controller/TerminalController.php:3623) (`sqlString`)
- [controller/TerminalController.php:1164](controller/TerminalController.php:1164) (anonyme Quoting-Funktion)
- [controller/TerminalController.php:1232](controller/TerminalController.php:1232) (`$escaped` für RFID-Code)
- [services/ZeitService.php:51](services/ZeitService.php:51)
- [services/AuftragszeitService.php:47](services/AuftragszeitService.php:47)

**Vorgehen:** Eine einzige Stelle schaffen – z. B. `Helper::sqlLiteral()` in
[core/Helper.php](core/Helper.php) – die zuerst Backslashes und dann
Anführungszeichen verdoppelt:

```php
"'" . str_replace(['\\', "'"], ['\\\\', "''"], $wert) . "'"
```

Die fünf Kopien darauf umstellen. `sqlInt()`/`sqlNullableInt()` sind in Ordnung
(harte Typumwandlung) und wandern der Vollständigkeit halber mit.

**Akzeptanzkriterium:** Ein Auftragscode `A1\` erzeugt einen Queue-Eintrag,
dessen SQL sich unverändert und ohne Syntaxfehler ausführen lässt, und der
Auftragscode steht danach mit dem Backslash in `auftragszeit`.

**Prüfung:** Am Terminal einen Nebenauftrag mit Backslash im Code starten,
`db_injektionsqueue` ansehen, Queue abarbeiten lassen, Ergebnis in
`auftragszeit` gegenprüfen. Zusätzlich derselbe Test mit `'` und mit `\'`.

**Risiko:** gering, klar abgegrenzt. Kein Schemawechsel.

---

### D-2 · Ein Fehlerpfad im Urlaubssaldo protokolliert seit jeher nichts

[services/UrlaubService.php:1269](services/UrlaubService.php:1269):

```php
} catch (\Throwable $e) {
    if (class_exists('LoggerService')) {
        (new LoggerService())->log('WARN', 'UrlaubService: Betriebsferien-Tage konnten nicht sauber gezaehlt werden (Fallback=0).', …);
    }
    $betriebsferienUrlaubTage = 0.0;
}
```

**Die Klasse `LoggerService` gibt es nicht** – nirgends im Repository. Die
Bedingung ist immer falsch. Das heißt: Wenn die Betriebsferien-Tage nicht
gezählt werden können, wird stillschweigend mit **0 Tagen** weitergerechnet und
**keine Zeile** landet im Log.

Das ist nicht nur ein toter Zweig, das ist möglicherweise die fehlende Spur zu
**B-080** („Urlaubsberechnung stimmt nicht"). Solange dieser Pfad schweigt, lässt
sich B-080 nicht sauber untersuchen.

**Vorgehen:** Auf `Logger::warn(...)` umstellen – die Signatur passt
(`nachricht, daten, mitarbeiterId, terminalId, kategorie`), das Projekt nutzt sie
überall sonst. `class_exists` fällt weg.

**Akzeptanzkriterium:** Ein erzwungener Fehler in der Betriebsferien-Zählung
hinterlässt einen `warn`-Eintrag in `system_log` mit Mitarbeiter-ID, Jahr und
Ausnahmetext.

**Prüfung:** Testweise `holeAktive()` eine Ausnahme werfen lassen, Urlaubsmaske
öffnen, `system_log` prüfen, Änderung zurücknehmen.

**Danach:** In den Snapshot bei B-080 einen Satz aufnehmen, dass dieser Pfad
jetzt sichtbar ist – das ist der nächste sinnvolle Schritt für B-080.

**Risiko:** sehr gering.

---

### D-3 · B-092: `betriebsferien_admin_toggle` läuft ins Leere

Die Route existiert, steht in der Liste der geschützten Seiten und ruft
`BetriebsferienAdminController::toggleAktiv()` auf – **die Methode gibt es
nicht** ([public/index.php:508](public/index.php:508)). Jeder Aufruf endet im
500er des Front-Controllers.

Dahinter steckt mehr als eine fehlende Methode: `betriebsferien.aktiv` wird von
allen Lesern respektiert ([modelle/BetriebsferienModel.php:37](modelle/BetriebsferienModel.php:37),
[controller/UrlaubJahresuebersichtController.php:537](controller/UrlaubJahresuebersichtController.php:537)),
die Liste zeigt die Spalte aber gar nicht an, und gesetzt wird sie nur beim
Anlegen. Der Schalter ist faktisch eingebaut, aber nirgends erreichbar.

**Vorgehen (bei Entscheidung E-b = „nachrüsten"):**
`KurzarbeitAdminController::toggleAktiv()` ([controller/KurzarbeitAdminController.php:683](controller/KurzarbeitAdminController.php:683))
ist die Vorlage – gleiche Rechteprüfung, gleicher CSRF-Weg, gleicher Redirect.
Dazu in der Liste eine Spalte *Aktiv* mit Schalter, wie bei Kurzarbeit.

**Akzeptanzkriterium:** Ein Betriebsferien-Eintrag lässt sich in der Liste auf
inaktiv schalten und verschwindet danach aus der Urlaubs-Jahresübersicht,
ohne gelöscht zu werden.

**Prüfung:** Eintrag anlegen, in der Jahresübersicht sehen, inaktiv schalten,
Jahresübersicht neu laden, wieder aktiv schalten. Zusätzlich: Aufruf ohne
gültiges CSRF-Token muss abgewiesen werden.

**Danach:** B-092 aus dem Snapshot entfernen.

**Risiko:** gering. Berührt keine Zeitberechnung.

---

### D-4 · Der QR-Rückfall bei Maschinen-Codes erzeugt einen Code, den niemand scannen kann

[public/maschine_code.php:89](public/maschine_code.php:89): Wenn die
Strichcode-Ausgabe leer bleibt, wird ersatzweise ein **QR-Code** ausgegeben.

Das widerspricht der Festlegung, die im Kopf von
[services/BarcodeService.php](services/BarcodeService.php) ausführlich begründet
ist: Im Betrieb sind **1D-Handscanner** im Einsatz, deshalb ist alles Code 128.
Ein QR-Code ist für diese Geräte kein schlechterer Code – er ist gar keiner. Der
Rückfall liefert also im Fehlerfall ein Bild, das aussieht wie ein Code, aber am
Scanner nichts tut. Das ist schlechter als eine Fehlermeldung, weil es erst in
der Halle auffällt.

Dasselbe Muster steckt in
[controller/MaschineAdminController.php:297](controller/MaschineAdminController.php:297)
und `:382`.

**Vorgehen:** Beide Rückfälle durch eine ehrliche Fehlerbehandlung ersetzen –
`Logger::error` (steht schon da) plus HTTP 500 mit Klartext bzw. eine
Fehlermeldung in der Maske. `erzeugeMaschinenQrCode()`, `gebeQrPngAus()`,
`erzeugePng()` und `ladeBibliothek()` in
[services/MaschineQrCodeService.php](services/MaschineQrCodeService.php) verlieren
damit ihre Aufrufer.

**Akzeptanzkriterium:** Schlägt die Strichcode-Erzeugung fehl, liefert
`maschine_code.php` einen HTTP-Fehler mit Hinweistext und keinen QR-Code.

**Prüfung:** `imagecreatetruecolor` versuchsweise über `disable_functions`
lahmlegen oder den Generator kurzzeitig eine Ausnahme werfen lassen, Bild
abrufen, danach zurückbauen.

**Anschlusspatch (nach E-e):** `services/phpqrcode/` samt der QR-Methoden
entfernen und `MaschineQrCodeService` in `MaschineCodeService` umbenennen – der
Name behauptet sonst weiter etwas, das nicht mehr stimmt.

**Risiko:** gering.

---

## Phase 2 – Kaltstart verkleinern

Das ist der eigentliche Auftrag. Ausgangslage, gemessen:

| Datei | heute |
| --- | --- |
| `CLAUDE.md` | 1.018 B |
| `CHATSTART.md` | 5.705 B |
| `docs/arbeitsregeln.md` | 8.338 B |
| `docs/STATUS_SNAPSHOT.md` | 3.757 B |
| **Summe** | **18.818 B** (≈ 5.400 Token) |

**Ziel: unter 12 KB**, ohne dass eine einzige Regel verschwindet.

Der Spielraum kommt nicht aus Kürzen um des Kürzens willen, sondern aus etwas,
das die Doku selbst verbietet: **dieselbe Aussage steht mehrfach.** Belegt:

| Aussage | steht in |
| --- | --- |
| „Statt gepflegter Liste `git log` lesen" | `CLAUDE.md`, `CHATSTART.md` (3×), `arbeitsregeln.md` (2×), `STATUS_SNAPSHOT.md` (2×), `docs/README.md`, `README.md` – **10 Fundstellen** |
| „Fertig, im Praxis-Test, nur bei Bugs" | `CHATSTART.md`, `arbeitsregeln.md`, `STATUS_SNAPSHOT.md`, `README.md` |
| „Gepusht wird nur auf Ansage" | `CLAUDE.md`, `CHATSTART.md`, `arbeitsregeln.md` |
| „Patch-ID `P-YYYY-MM-DD-XX`" | `CHATSTART.md`, `arbeitsregeln.md`, `README.md` |
| „History im selben Commit" | `CHATSTART.md`, `arbeitsregeln.md`, `README.md`, + 4 Erwähnungen |

`CHATSTART.md` sagt selbst: *„Findest du dieselbe Aussage zweimal, ist das ein
Fehler – melde ihn."* Hiermit gemeldet.

**Grundsatz für alle vier Patches:** Eine Regel wird nur dort gestrichen, wo sie
**nicht** zu Hause ist. Ihr Zuhause ist die Datei, in der sie beim Arbeiten
gebraucht wird. `README.md` bleibt unangetastet – die richtet sich an Menschen,
die das Repository frisch klonen, und wird nicht bei jedem Chat gelesen.

**Verlustkontrolle – Pflicht bei jedem Patch dieser Phase:** Vor dem Commit für
jede entfernte Aussage nachweisen, dass sie anderswo noch steht.

```bash
git diff -U0 -- <datei> | grep '^-' | grep -v '^---'
```

Jede so gelistete Zeile einzeln gegen den Rest der Doku prüfen (`grep -rn`).
Bleibt eine ohne Zuhause, gehört sie nicht gelöscht, sondern verschoben.

---

### A-1 · `CLAUDE.md` auf das reduzieren, was nur dort steht

**Ziel:** 1.018 B → rund 500 B.

`CLAUDE.md` wird automatisch geladen, `CHATSTART.md` unmittelbar danach. Von den
vier Punkten unter „Nur für Claude Code" stehen drei schon in Dateien, die im
selben Atemzug gelesen werden:

- „Nicht pushen" → `CHATSTART.md` §2, `arbeitsregeln.md` §4
- „Pre-Flight-Gate durchlaufen" → `arbeitsregeln.md` §2, dort vollständig
- „App läuft unter `http://localhost/zeiterfassung`" → `docs/lokale_entwicklungsumgebung.md`, `README.md`

**Genau ein Punkt ist einzigartig und wichtig:** *Die Datenbank der lokalen
Umgebung enthält echte Personendaten aus einem Serverdump.* Der muss bleiben,
und zwar hier – weil dies die einzige Datei ist, die garantiert gelesen wird,
bevor irgendetwas passiert.

**Vorgehen:** Verweis auf `CHATSTART.md` behalten, den Absatz zur
Werkzeugneutralität auf einen Satz kürzen, den Personendaten-Hinweis behalten,
„Nicht pushen" behalten (harte Nie-Regel, die redundant sein *darf*), die
übrigen zwei Punkte streichen.

**Akzeptanzkriterium:** `CLAUDE.md` ist unter 600 B, und jede gestrichene Zeile
ist per `grep` in `CHATSTART.md`, `arbeitsregeln.md` oder
`lokale_entwicklungsumgebung.md` nachweisbar.

---

### A-2 · `CHATSTART.md` straffen

**Ziel:** 5.705 B → rund 3.600 B. Gemessene Abschnitte:

| Abschnitt | heute | danach | wie |
| --- | --- | --- | --- |
| 1. Was das Projekt ist | 976 B | ~550 B | Die Aufzählung der Funktionsbereiche steht wortgleich in `README.md`. Hier reichen: was es ist, zwei Einstiegspunkte, zwei Installationstypen. Der Status wandert nach A-4. |
| 2. Regeln in Kurzform | 846 B | ~600 B | Bleibt – andere Werkzeuge lesen nur diese Datei. Aber als **Kurzform gekennzeichnet**, mit einem Satz: Verbindlich ist `arbeitsregeln.md`. Die neun Stichpunkte auf sechs eindampfen. |
| 3. Lesekarte | 2.433 B | ~1.700 B | Der wertvollste Abschnitt, die Tabellen bleiben vollständig. Der Absatz „Nur bei Bedarf" über `DEV_PROMPT_HISTORY.md` (≈ 500 B) schrumpft auf zwei Zeilen; die Begründung, warum die Datei nicht gelesen wird, steht schon in `docs/README.md`. |
| 4. Was du nicht lesen musst | 417 B | 417 B | Bleibt unverändert. Billigster Abschnitt der Datei, verhindert die teuersten Fehler. |
| 5. Warum die Doku so aufgeteilt ist | 653 B | 0 B | **Nach `docs/README.md` verschieben.** Das ist Begründung, keine Arbeitsanweisung – einmal interessant, nicht bei jedem Start. Der Merksatz „Eine Regel an genau einer Stelle" wandert in Abschnitt 3 als Einzeiler. |

**Akzeptanzkriterium:** `CHATSTART.md` ist unter 3.800 B, die Lesekarte nennt
weiterhin alle heutigen Ziele, und Abschnitt 5 steht vollständig in
`docs/README.md`.

**Prüfung:** Linkcheck (siehe Phase 7), plus: Alle Dateinamen aus der heutigen
Lesekarte müssen in der neuen wieder vorkommen –
`grep -oP '\]\(\K[^)]+' CHATSTART.md | sort` vorher/nachher vergleichen.

---

### A-3 · `docs/arbeitsregeln.md` straffen

**Ziel:** 8.338 B → rund 5.400 B. Gemessene Abschnitte:

| Abschnitt | heute | Vorschlag |
| --- | --- | --- |
| Kopf *„Herkunft: Master-Prompt v13, Abschnitte 1, 18, 19 und 20…"* | ~330 B | **Streichen.** Dieselbe Herkunft steht in `docs/fachregeln/README.md` und in `docs/archiv/ALTE_PROMPTS.md`, wo sie hingehört. Beim Arbeiten hilft sie nie. |
| 1. Projektstatus | 482 B | **Auf einen Satz.** Der Status hat genau ein Zuhause: den Snapshot. Hier bleibt: „Ob überhaupt gearbeitet wird, entscheidet der Projektstatus im Snapshot" plus die Regel zu neuen Funktionsbereichen (Spezifikation vor Umsetzung) – die steht sonst nirgends. |
| 2. Pre-Flight-Gate | 912 B | Bleibt. Kernstück. |
| 3. Zuschnitt | 683 B | Bleibt. |
| 4. Commit | 869 B | Bleibt. |
| 5. Pflichtprüfung | 429 B | Bleibt. |
| 6. Zwei Dateien | 1.183 B | ~900 B. Der Schlussabsatz „Keine handgepflegten Listen für Dinge, die Git schon weiß" ist die vierte Fassung derselben Aussage – streichen, sie steht in `CHATSTART.md` §4. |
| 7. Technik und Stil | 2.170 B | ~1.600 B. Die **Struktur-Tabelle** (`public/`, `core/`, `modelle/` …, ~450 B) steht wortgleich in `README.md` und ist ohnehin an einem `ls` ablesbar – streichen und auf README verweisen. Der Rest (PHP-Baseline, kein Framework, keine Container, Routing, Stil) bleibt vollständig. |
| 8. Worauf besonders zu achten ist | 361 B | **Streichen.** Vier von fünf Punkten wiederholen §7 („Rohdaten/Rundung", „Offline-Queue", „Rollenlogik", „PHP-Baseline"). Der einzige eigenständige Punkt – Lauffähigkeit auf dem Raspberry Pi – steht schon im ersten Absatz von §7. |
| 9. SQL im Chat | 267 B | Bleibt. |
| 10. Kaltstart klein halten | 516 B | ~300 B, und **die Zahl auf 12 KB korrigieren**, sonst beschreibt die Regel nach diesem Durchgang nicht mehr die Wirklichkeit. |

**Akzeptanzkriterium:** `docs/arbeitsregeln.md` ist unter 5.600 B und enthält
weiterhin alle zehn heutigen Regelbereiche in Nummerierung oder klarer
Nachfolge.

**Achtung:** `CHATSTART.md` §3 verlinkt auf diese Datei, `docs/README.md`
beschreibt ihren Inhalt („Patch-Zuschnitt, Patch-ID, Pre-Flight-Gate,
Pflichtprüfungen, Code-Stil, PHP-Baseline"). Diese Beschreibung im selben Commit
gegenprüfen.

---

### A-4 · `docs/STATUS_SNAPSHOT.md` auf den Stand reduzieren

**Ziel:** 3.757 B → rund 2.000 B.

| Abschnitt | heute | Vorschlag |
| --- | --- | --- |
| Kopf (drei Absätze) | ~700 B | **Auf zwei Zeilen.** Absatz 2 und 3 erklären, was *nicht* hier steht – das sagt `docs/README.md` schon, und wer den Snapshot liest, sucht den Stand, keine Meta-Erklärung. |
| Projektstatus | 140 B | Bleibt. **Hier ist das Zuhause dieser Aussage** – die Kopien in `CHATSTART.md` und `arbeitsregeln.md` fallen in A-2/A-3 weg. |
| Nächster Schritt | 1.681 B | ~500 B. Die sieben Prüfpunkte des Gerätetests sind ein **Testprotokoll**, kein Stand – sie gehören nach `docs/spezifikation_terminal_installation.md` als eigener Abschnitt, wohin der Snapshot bereits verweist („Abschnitte 6, 7, 8"). Im Snapshot bleiben: dass der Gerätetest dran ist, der Verweis auf das Protokoll, und die drei ungetesteten Paketfamilien (`pacman`, `dnf`, `zypper`) – die stehen in der Spezifikation nicht als offener Punkt. |
| Offene Bugs | 542 B | Bleibt. B-092 fällt nach D-3 weg, B-080 bekommt aus D-2 einen Satz. |
| Offene Tasks | 544 B | Bleibt, plus die aus E-c und Phase 6 entstehenden T-IDs. |
| Was zuletzt passiert ist | 213 B | **Streichen.** Dritte Fassung von „lies `git log`" innerhalb desselben Kaltstart-Sets. |

**Akzeptanzkriterium:** Der Snapshot ist unter 2.200 B, und die sieben
Prüfpunkte des Gerätetests stehen vollständig und wortgleich in
`docs/spezifikation_terminal_installation.md`.

**Prüfung:** Die sieben Punkte vor und nach dem Verschieben gegeneinander
diffen. Kein Punkt darf beim Umzug „zusammengefasst" werden.

---

### A-5 · Nach A-1 bis A-4: nachmessen

```bash
cat CLAUDE.md CHATSTART.md docs/arbeitsregeln.md docs/STATUS_SNAPSHOT.md | wc -c
```

Erwartung: unter 12.000. Das Ergebnis in den History-Eintrag von A-4 schreiben –
so ist beim nächsten Durchgang nachvollziehbar, wo man herkam.

---

## Phase 3 – Fehler in der Dokumentation

### B-1 · Ein Tabellenname in der Rechte-Doku existiert nicht

`docs/rechte_prompt.md` (2×) und `docs/fachregeln/rollen_rechte_genehmiger.md`
(1×) sprechen von der Tabelle **`mitarbeiter_rechte_override`**. Die gibt es
nicht – weder im Schema noch im Code. Sie heißt `mitarbeiter_hat_recht`
([sql/01_initial_schema.sql:329](sql/01_initial_schema.sql:329)).

Das wiegt schwerer als ein Tippfehler: `rollen_rechte_genehmiger.md` ist die
Datei, die laut Lesekarte **immer** zu lesen ist, wenn Rechte berührt werden.
Wer danach im Schema sucht, findet nichts und muss raten.

**Akzeptanzkriterium:** `grep -rn mitarbeiter_rechte_override docs/` liefert
außerhalb von `docs/archiv/` keinen Treffer mehr.

---

### B-2 · Drei SQL-Dateien, auf die die Rechte-Doku verweist, gibt es nicht

`docs/rechte_prompt.md` nennt:

- `sql/19_migration_rechte_legacy_merge.sql` (Zeile 319, als „Phase 1a DONE")
- `sql/20_migration_recht_code_unique.sql` (Zeile 327, als „Phase 2 DONE")
- `sql/zeiterfassung_aktuell.sql` (Zeile 26)

In `sql/` liegen `01` bis `06` und `offline_db_schema.sql`. Die drei genannten
Dateien wurden offenbar ins Initialschema eingeschmolzen. Die Doku behauptet
weiter, man könne sie einspielen.

**Vorgehen:** Prüfen, ob die beschriebenen Wirkungen (Unique-Index
`uniq_recht_code`, Soft-Delete über `recht.aktiv`) im Initialschema angekommen
sind – dann umformulieren zu „im Initialschema enthalten, historisch als
Migration 19/20 eingespielt". Sind sie nicht angekommen, ist das ein Bug und
gehört als B-ID in den Snapshot, nicht in diesen Patch.

**Akzeptanzkriterium:** Der Pfad-Check aus Phase 7 meldet für `docs/` keine
fehlenden `sql/`-Dateien mehr.

---

### B-3 · Der XAMPP-Abschnitt in der Wartungscheckliste beschreibt einen Rechner, den es nicht mehr gibt

[docs/wartungscheckliste.md:48-75](docs/wartungscheckliste.md:48) enthält einen
PowerShell-Block mit `D:\xampp1\php\php.exe` unter der Überschrift „Windows /
XAMPP (ältere Arbeitsumgebung)". Die Entwicklungsumgebung ist nativ auf
Arch/CachyOS ([docs/lokale_entwicklungsumgebung.md](docs/lokale_entwicklungsumgebung.md)),
produktiv läuft Debian. Der Abschnitt ist knapp 700 B, die nie jemand braucht,
und die Ausgabe darin ist als einzige Stelle der Datei englisch
(`'OK: all PHP files lint clean'`).

Dazu in derselben Datei, Zeile 133: **„Abläufe"** ist als `Ablaufe` geschrieben.

**Akzeptanzkriterium:** `docs/wartungscheckliste.md` enthält keinen
Windows-/XAMPP-Abschnitt mehr und keine englischsprachige Ausgabe.

---

## Phase 4 – Toter Code

Zusammen rund **1.100 Zeilen**, die nie ausgeführt werden. Das Gefährliche daran
ist nicht der Platz, sondern dass sie echt aussehen: `core/Auth.php` liest sich
wie die Anmeldung des Projekts, ist aber seit Langem durch `AuthService`
ersetzt. Wer dort einen Fehler sucht oder behebt, arbeitet ins Leere – und die
Fachregeln schicken ihn sogar hin.

Alle Angaben unten sind mit `grep` über `controller core modelle services views
public` geprüft; genannt wird jeweils die Trefferzahl außerhalb der eigenen
Datei.

### C-1 · Acht Controller- und sechs View-Dateien ohne Inhalt

Reine Platzhalter, jeweils drei Zeilen aus Kommentar und `?>`:

```
controller/AbteilungController.php          controller/MitarbeiterController.php
controller/AuthController.php               controller/PDFController.php
controller/BackendController.php            controller/RollenController.php
controller/MaschineController.php           controller/TerminalVerwaltungController.php
views/auth/login.php                        views/urlaub/antrag_liste.php
views/config/index.php                      views/zeit/monatsansicht.php
views/urlaub/antrag_formular.php            views/urlaub/antrag_genehmigung.php
```

Sie sind zugleich die **einzigen** acht Projektdateien mit schließendem `?>` –
das ist ein eigener Grund, sie loszuwerden, weil ein `?>` am Dateiende
Leerzeichen ausgeben kann.

**Akzeptanzkriterium:** Nach dem Löschen findet
`grep -rln -e '?>' --include='*.php' controller core modelle services` keine
Datei mehr, und Backend und Terminal starten unverändert.

---

### C-2 · `core/Auth.php` und `core/SessionManager.php`

- `core/Auth.php` (258 Zeilen) – **0 Treffer.** Alles läuft über
  `services/AuthService.php` (26 Aufrufstellen).
- `core/SessionManager.php` (291 Zeilen) – **2 Treffer, beide in `core/Auth.php`.**
  Fällt mit ihm.

**Wichtig, im selben Commit:**
[docs/fachregeln/rollen_rechte_genehmiger.md:3](docs/fachregeln/rollen_rechte_genehmiger.md:3)
nennt `core/Auth.php` als geltende Quelle („*Gilt für:* `services/AuthService.php`,
`core/Auth.php`, …"). Diese Zeile mitziehen – sonst schickt die Doku weiter zu
einer Datei, die es nicht mehr gibt.

Auch `docs/arbeitsregeln.md` §7 und `README.md` führen „Session" bzw. „Feiertage"
in der Beschreibung von `core/` – prüfen, ob die Beschreibung nach C-2 und C-3
noch stimmt.

**Vorher gegenprüfen:** `grep -rn '\bAuth::' --include='*.php' .` und
`grep -rn SessionManager --include='*.php' .` müssen leer bleiben.

**Akzeptanzkriterium:** Anmeldung, Abmeldung, RFID-Anmeldung am Terminal und
Rechteprüfung funktionieren unverändert, und keine Datei im Repository nennt
`core/Auth.php` oder `SessionManager` mehr außerhalb von `docs/archiv/`.

---

### C-3 · Vier weitere ungenutzte Klassen

| Datei | Zeilen | Treffer | Anmerkung |
| --- | --- | --- | --- |
| `core/FeiertagGenerator.php` | 197 | 0 | Genannt in [docs/fachregeln/urlaub_abwesenheit_feiertage.md:4](docs/fachregeln/urlaub_abwesenheit_feiertage.md:4) – Zeile im selben Commit mitziehen. Die Feiertagslogik liegt in `services/FeiertagService.php`. |
| `services/OfflineQueueService.php` | 106 | 0 | Dritte Klasse für dieselbe Tabelle, neben `core/OfflineQueueManager.php` (41 Treffer) und `services/QueueService.php` (7). |
| `modelle/ConfigModel.php` | 73 | 0 | |
| `modelle/SystemLogModel.php` | 70 | 0 | Logging läuft direkt über `core/Logger.php`. |
| `modelle/AuftragArbeitsschrittModel.php` | 88 | 0 | |

Am besten **zwei Patches**: einer für `core/`, einer für `modelle/`+`services/`.
Der `core/`-Teil zieht Doku mit, der andere nicht.

**Akzeptanzkriterium:** Monatsreport, Urlaubsübersicht und Queue-Admin
funktionieren unverändert, und keine der fünf Dateien wird noch referenziert.

---

### C-4 · Drei Views, zu denen es einen zweiten, tatsächlich benutzten Zwilling gibt

- `views/betriebsferien/liste.php` (95 Zeilen)
- `views/betriebsferien/formular.php` (83 Zeilen)
- `views/maschine/liste.php` (68 Zeilen)

Diese Dateien werden **nirgends** eingebunden. Die zugehörigen Controller
erzeugen ihr HTML selbst ([controller/BetriebsferienAdminController.php:100](controller/BetriebsferienAdminController.php:100)
ff., `MaschineAdminController` zwischen `header.php` und `footer.php`).

Das ist die unangenehmste Sorte toter Code: Wer die Betriebsferien-Liste ändern
will, findet `views/betriebsferien/liste.php`, ändert sie – und im Browser
passiert nichts.

Dazu vier weitere, die niemand einbindet:
`views/terminal/hauptmenue.php` (25), `views/terminal/info_uebersicht.php` (25),
`views/terminal/urlaub_beantragen.php` (13), `views/zeit/monatskalender.php` (23).

**Vor dem Löschen:** Bei den vier Terminal-/Zeit-Views einmal gegenlesen, ob dort
Fachwissen steckt, das im Controller fehlt – dann gehört es dorthin, bevor die
Datei geht.

**Akzeptanzkriterium:** Betriebsferien-Liste, Maschinenliste und das
Terminal-Hauptmenü sehen unverändert aus, und `views/` enthält keine Datei mehr,
die nirgends eingebunden wird.

**Danach:** Aus Entscheidung E-c eine T-ID im Snapshot anlegen: „Neun Controller
erzeugen HTML selbst, statt `views/` zu benutzen – bei Gelegenheit auflösen,
größter Brocken `SmokeTestController` (283 HTML-Zeilen)."

---

## Phase 5 – Doppelter Code

### E-1 · CSRF-Behandlung liegt in fünfzehn Controllern als Kopie

`holeOderErzeugeCsrfToken()` existiert in **vierzehn Controllern** (fünfzehnmal –
`AuftragController` hat zwei Fassungen), `istCsrfTokenGueltigAusPost()` mehrfach
dazu:

```
ArbeitsschrittKatalogController   QueueController                  TerminalController
AuftragController (2×)            ReportController                 TerminalEinrichtungController
DashboardController               RollenAdminController            UrlaubController
KonfigurationController           TerminalAdminController          UrlaubKontingentAdminController
KurzarbeitAdminController                                          ZeitRundungsregelAdminController
```

`SmokeTestController` verwaltet sein Token bei
[Zeile 784](controller/SmokeTestController.php:784) noch einmal von Hand, ohne
eigene Methode – gehört mit umgestellt.

Die Kopien sind fast, aber nicht ganz gleich – und genau das ist der Beleg, dass
Kopieren hier schon Schaden angerichtet hat:

- **Tokenlänge:** meist `random_bytes(32)`, in `TerminalController` `random_bytes(16)`.
- **Rückfall bei fehlender Entropie:** meist `bin2hex((string)mt_rand())` – das
  ergibt ein **rateberechenbares Token von 8 Hexzeichen**. In `TerminalController`
  stattdessen `random_int(...)`, was im `catch` von `random_bytes` mit derselben
  Wahrscheinlichkeit ebenfalls wirft.
- **`\Throwable` vs. `Throwable`**, uneinheitlich.
- **Session-Start:** manche Kopien rufen `session_start()` erneut, manche nicht.

**Vorgehen:** `core/Csrf.php` mit zwei statischen Methoden –
`Csrf::token(string $bereich): string` und
`Csrf::istGueltig(string $bereich): bool`. Der Bereich ersetzt die heutigen
`CSRF_KEY`-Konstanten, sodass jede Maske ihr eigenes Token behält (das ist
gewollt, nicht zufällig). Ein misslingendes `random_bytes` wird zur Ausnahme,
nicht zu einem schwachen Token.

Dann Controller für Controller umstellen. **Nicht** in einem Patch – ein Patch
für `core/Csrf.php` plus die ersten zwei Controller, danach je Patch drei bis
vier weitere. Nach jedem Patch die betroffenen Masken tatsächlich abschicken.

**Akzeptanzkriterium (je Patch):** Jede umgestellte Maske speichert weiterhin,
und ein abgeschicktes Formular mit fremdem oder fehlendem Token wird abgewiesen.

**Risiko:** mittel. CSRF-Fehler fallen erst beim Absenden auf, deshalb gehört
jede umgestellte Maske einmal angeklickt. Das ist der aufwendigste Punkt der
Liste – und der mit dem größten Gewinn.

---

### E-2 · Zwei Dienste für dieselbe Konfigurationstabelle

- [services/ConfigService.php](services/ConfigService.php) (130 Zeilen) – liest
  `config`, mit Typumwandlung und Cache. 9 Aufrufstellen.
- [services/KonfigurationService.php](services/KonfigurationService.php) (195 Zeilen)
  – liest **dieselbe** Tabelle `config`, mit `get`/`getInt`/`getBool`/`set`/`getAlle`.
  26 Aufrufstellen.

Beide haben eine Methode `get()` mit unterschiedlicher Rückgabesemantik
(`ConfigService` liefert je nach Spalte `typ` einen `int`/`bool`/`array`,
`KonfigurationService` immer `?string`). Wer den falschen erwischt, bekommt
schweigend einen anderen Typ.

**Vorgehen:** `KonfigurationService` gewinnt – mehr Aufrufer, deutscher Name,
kann auch schreiben. Die Typumwandlung aus `ConfigService::konvertiereNachTyp()`
dort als eigene Methode ergänzen (die kann `KonfigurationService` heute nicht,
und sie ist nützlich). Die neun Aufrufstellen umstellen, `ConfigService`
löschen.

Aufrufstellen: `TerminalKopplungController:235`, `TerminalDbBenutzerService:575`,
`views/terminal/_autologout.php:110-113`, `public/terminal.php:214-217`.

**Akzeptanzkriterium:** `terminal_session_idle_timeout`,
`terminal_healthcheck_interval`, `terminal_db_host_muster` und
`terminal_db_host_extern` liefern nach der Umstellung dieselben Werte wie
vorher, und `services/ConfigService.php` ist gelöscht.

**Prüfung:** Vor und nach der Änderung die vier Werte am Health-Endpunkt bzw. in
der Terminal-Session vergleichen.

---

### E-3 · `Database::getPdo()` ist ein zweiter Name für `getVerbindung()`

[core/Database.php:78](core/Database.php:78) gibt nur
`$this->getVerbindung()` zurück. Beide Namen sind etwa gleich häufig im Umlauf
(32 zu 33 Aufrufe) – es gibt also keinen „eigentlichen" und keinen „alten".

Dazu kommen **64 `method_exists()`-Prüfungen** auf Methoden, die es alle gibt
(`istHauptdatenbankVerfuegbar`, `getOfflineVerbindung`, `getVerbindung`,
`getPdo`, `hatRecht`, …). Jede einzelne ist immer wahr. Muster:

```php
if (method_exists($db, 'getVerbindung')) {
    $queuePdo = $db->getVerbindung();
} elseif (method_exists($db, 'getPdo')) {
    $queuePdo = $db->getPdo();
}
```

Der zweite Zweig ist doppelt tot: erreichbar wäre er nur, wenn `getVerbindung`
fehlte, und dann führte er zur selben Methode.

**Vorgehen:** Auf `getVerbindung()` vereinheitlichen (deutscher Name, passt zum
Rest der Klasse), `getPdo()` entfernen, alle `method_exists`-Prüfungen auf
`Database`-Methoden streichen.

**Akzeptanzkriterium:** `grep -rn 'getPdo\|method_exists' --include='*.php'
controller core modelle services views public` liefert keinen Treffer mehr, und
Backend, Terminal und Health-Endpunkt laufen unverändert.

---

### E-4 · 305 Prüfungen, ob es die Logger-Klasse gibt

`if (class_exists('Logger'))` steht **305-mal** im Projekt, dazu 28-mal
`class_exists('Database')` und rund 40 weitere auf Projektklassen.

Alle sind immer wahr: `core/Autoloader.php` wird von jedem der drei
Einstiegspunkte als Erstes geladen und findet jede Klasse aus `core/`,
`modelle/`, `services/`, `controller/`.

Das ist der reinste Fall von Absicherung, die nichts absichert – aber sie kostet
in jeder Datei Aufmerksamkeit und suggeriert, das Logging sei optional.

**Zwei Ausnahmen, die bleiben müssen:**

- `class_exists('ZipArchive')` in
  [controller/DashboardController.php:669](controller/DashboardController.php:669)
  – eine PHP-Erweiterung, die tatsächlich fehlen kann. Der Selbsttest **prüft**
  sie dort absichtlich.
- `class_exists('Picqer\Barcode\...')` in
  [services/MaschineQrCodeService.php:333](services/MaschineQrCodeService.php:333)
  – wird per `require_once` geladen, nicht über den Autoloader.

`class_exists('LoggerService')` ist der Sonderfall aus **D-2** und wird dort
erledigt.

**Vorgehen:** Mechanisch, aber **nicht per `sed`** – die Bedingung umschließt
jeweils einen Block, dessen Einrückung sich ändert. Verzeichnisweise vorgehen
(`core/`, dann `modelle/`, dann `services/`, dann `controller/`, dann `views/` +
`public/`), je Verzeichnis ein Patch, nach jedem `php -l` über alle geänderten
Dateien.

**Akzeptanzkriterium (je Patch):** Im bearbeiteten Verzeichnis gibt es keine
`class_exists`-Prüfung auf eine Projektklasse mehr, `php -l` ist sauber, und
`system_log` bekommt beim Klicktest weiterhin Einträge.

**Risiko:** gering pro Stelle, spürbar in der Summe. Deshalb kleine Patches.

---

### E-5 · Der Queue-Statusblock steht zweimal in `terminal.php`

[public/terminal.php:83-170](public/terminal.php:83) (Health-Endpunkt) und
[public/terminal.php:298-398](public/terminal.php:298) (normaler Ablauf)
ermitteln **denselben** Zustand mit fast demselben Code: Haupt-DB verfügbar,
Offline-Verbindung, Speicherort `offline`/`haupt`, Zähler `offen`/`fehler`,
letzter Fehler. Zwei Fassungen derselben Wahrheit – wenn eine gepflegt wird und
die andere nicht, meldet der Health-Endpunkt etwas anderes als der Bildschirm.

Der Kommentar „Konsistente Logik mit OfflineQueueManager" steht sogar zweimal
wortgleich da.

**Vorgehen:** Eine Methode, die diesen Zustand liefert – am besten in
`services/QueueService.php`, wo `holeStatusSummary()` schon fast dasselbe tut
([services/QueueService.php:174](services/QueueService.php:174)). Beide Stellen
darauf umstellen.

Dabei mitklären: `$queueStatus['offline_queue_verfuegbar']` wird als
„Legacy-/View-Key" gespiegelt ([public/terminal.php:291](public/terminal.php:291)).
Prüfen, ob den noch eine View liest – wenn nein, ersatzlos streichen.

**Akzeptanzkriterium:** `terminal.php?aktion=health` und die Statusbox im
Terminal melden bei erreichbarer *und* bei abgeschalteter Hauptdatenbank
denselben Zustand, und die Ermittlung steht nur noch an einer Stelle.

**Risiko:** mittel – das ist der Offline-Pfad. Beide Zustände (DB an / DB aus)
gehören getestet, dazu der Störungsmodus.

---

### E-6 · Dreimal derselbe Programmstart

[public/index.php:1-67](public/index.php:1), [public/terminal.php:1-23](public/terminal.php:1)
und [public/maschine_code.php:1-20](public/maschine_code.php:20) laden
identisch Autoloader, Konfiguration, Zeitzone und Session – inklusive desselben
`if/else` für die Zeitzone mit demselben Fallback `Europe/Berlin`.

**Vorgehen:** `core/Start.php` (oder `core/Bootstrap.php`) mit einer Funktion,
die genau das tut und die Konfiguration zurückgibt. Die drei Einstiegspunkte
darauf umstellen. Die Terminal-Weiche in `index.php` (Zeilen 32-56) bleibt, wo
sie ist – die ist inhaltlich und gut kommentiert.

**Akzeptanzkriterium:** Alle drei Einstiegspunkte starten unverändert, die
Zeitzonenlogik steht nur noch an einer Stelle, und ein Terminal ohne
`config.local.php` zeigt weiterhin die Einrichtungsseite.

**Risiko:** gering, aber es sind die drei Türen des Systems – nach dem Patch
alle drei tatsächlich aufrufen.

---

## Phase 6 – Kleinigkeiten

### F-1 · `public/index.php` aufräumen

Drei Dinge in einer Datei, gut in einem Patch:

1. **Verwaister Kommentarblock:** [public/index.php:69-78](public/index.php:69)
   beschreibt `normalize_jahr_monat` („T-069 Teil 2a/2b … Wir clampen defensiv"),
   steht aber vor `verarbeite_jahr_monat_aktion`, das seinen eigenen Block bei
   Zeile 81 hat. `normalize_jahr_monat` ab Zeile 127 steht dann ohne. Den Block
   an die richtige Funktion schieben.
2. **Dreimal derselbe Fünfzeiler:** Die Blöcke bei
   [Zeile 292](public/index.php:292), [303](public/index.php:303) und
   [314](public/index.php:314) sind zeichengleich (Jahr/Monat aus `$_GET`,
   Stepper, Clamping). In eine Funktion `holeJahrMonatAusRequest(): array`.
3. **Zwei Routen ohne Verlinkung:** `betriebsferien_admin_toggle` (nach D-3
   erledigt) und `urlaubsplanung` (nach E-d als Alt-Link kommentieren).

Nebenbei zu sehen, aber **nicht** in diesem Patch: Die Liste
`$geschuetzteSeiten` ([Zeile 161](public/index.php:161)) wiederholt jeden
`case`-Zweig des `switch` von Hand. Geprüft: Beide Listen stimmen heute exakt
überein, bis auf `login`, `logout` und `terminal_kopplung`, die absichtlich offen
sind. Es ist also derzeit kein Fehler – aber eine Stelle, an der beim nächsten
Route-Zusatz einer vergessen wird. Als T-ID notieren.

**Akzeptanzkriterium:** Monatsreport, Monats-PDF und Sammelexport reagieren auf
Jahr/Monat-Stepper genau wie vorher, auch bei `monat=0` und `monat=13`.

---

### F-2 · Einrückung und Schreibweisen vereinheitlichen

Reine Formsache, aber mechanisch prüfbar:

- **Tabs statt Leerzeichen** in zehn Dateien:
  `SmokeTestController` (108 Zeilen), `views/terminal/start.php` (59),
  `views/layout/header.php` (45), `UrlaubController` (25), `AuthService` (15),
  `views/terminal/auftrag_starten.php` (12), `DashboardController` (5),
  `views/mitarbeiter/formular.php` (4), `views/urlaub/genehmigung_liste.php` (1),
  `views/report/monatsuebersicht.php` (1).
- **Zwei Methoden ohne Einrückung:**
  [services/QueueService.php:174](services/QueueService.php:174) und
  [services/ReportService.php:1240](services/ReportService.php:1240) beginnen in
  Spalte 1 statt mit vier Leerzeichen.
- **Ein Ausreißer beim Escaping:** 338 Stellen benutzen
  `htmlspecialchars($x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`, nur
  [views/login/initial_admin.php](views/login/initial_admin.php) benutzt an fünf
  Stellen `ENT_QUOTES` ohne `ENT_SUBSTITUTE`. Ohne `ENT_SUBSTITUTE` liefert
  `htmlspecialchars` bei ungültigem UTF-8 einen **leeren String** – auf der
  Erstinstallationsmaske hieße das: Eingabe verschwindet ohne Meldung.

**Akzeptanzkriterium:** Kein `^\t` mehr in `controller/`, `services/`, `views/`
(ohne Fremdbibliotheken), und `grep -rn 'ENT_QUOTES' views/ | grep -v
ENT_SUBSTITUTE` ist leer.

**Achtung:** Der Whitespace-Teil erzeugt einen großen, inhaltsleeren Diff.
Deshalb **eigener Patch**, nicht mit etwas anderem vermischt, damit `git blame`
später nachvollziehbar bleibt.

---

### F-3 · Umlaut-Konvention festlegen und umsetzen

Nach Entscheidung **E-a**. Der heutige Zustand ist nicht „eine Konvention mit
Ausnahmen", sondern zwei Konventionen nebeneinander, teils in derselben Datei:

| Datei | Zeilen mit Umlaut | Zeilen mit `ae/oe/ue` |
| --- | --- | --- |
| `controller/TerminalController.php` | 150 | 39 |
| `controller/AuftragController.php` | 9 | 31 |
| `services/UrlaubService.php` | 72 | 16 |
| `services/TerminalDbBenutzerService.php` | 3 | 17 |
| `views/terminal/start.php` | 87 | 16 |
| … 25 weitere Dateien | | |

Bei der Doku genauso: `README.md`, `docs/README.md`, `admin_handbuch.md` und
`installationsanleitung.md` schreiben Umlaute, `arbeitsregeln.md`,
`STATUS_SNAPSHOT.md`, alle `fachregeln/` und beide Spezifikationen nicht.
`docs/rechte_prompt.md` und `docs/admin_handbuch.md` mischen innerhalb der Datei.

**Vorgehen:** Die Konvention zuerst in `docs/arbeitsregeln.md` §7 unter „Stil"
festschreiben – **ein** Satz, sonst wächst der Kaltstart wieder. Danach in
getrennten Patches umsetzen, gestaffelt: erst Doku, dann `views/`, dann
`services/`+`controller/`. **`docs/archiv/` bleibt unangetastet** – Archiv wird
nicht umgeschrieben.

**Nicht anfassen:** Bezeichner, Dateinamen, Datenbankfelder und -werte,
Konfigurationsschlüssel, Shell-Skripte, und der Rollenname-Rückfall
`'Personalbuero'` in [public/maschine_code.php:31](public/maschine_code.php:31) –
der steht so in den Daten.

**Akzeptanzkriterium (je Patch):** Im bearbeiteten Bereich kommt keine
Ersatzschreibung mehr in Kommentaren oder Oberflächentexten vor, und die Seiten
zeigen die Texte korrekt an (UTF-8-Prüfung im Browser, nicht nur im Editor).

---

### F-4 · Verweise auf den abgelösten Master-Prompt umbiegen

**21 Kommentare** im Code verweisen auf den Master-Prompt, teils mit
Abschnittsnummern: „Master-Prompt v6 / T-072", „Master-Prompt v9",
„Master-Prompt, Abschnitt 7", „Regeln gemäß Master-Prompt".

Der liegt seit dem Doku-Umbau in `docs/archiv/` und gilt nicht mehr; sein Inhalt
steckt in `docs/arbeitsregeln.md` und `docs/fachregeln/`. Ein Leser, der einem
dieser Verweise folgt, landet entweder nirgends oder in einem Dokument, das
ausdrücklich nicht mehr die Regel ist.

Betroffen unter anderem: `core/OfflineQueueManager.php:117`,
`core/DefaultsSeeder.php:48-49`, `services/PausenService.php:9`,
`services/ZeitService.php:192`, `services/TerminalDbBenutzerService.php:96`,
`controller/TerminalController.php` (8×), `views/terminal/stoerung.php:10`,
`views/terminal/start.php:526`, `public/terminal.php:6` und `:406`.

**Vorgehen:** Jeden Verweis auf die heute gültige Datei umbiegen – meist
`docs/fachregeln/terminal_und_offline.md` oder
`docs/fachregeln/zeit_rundung_pausen.md`. Wo die Regel dort **nicht** steht, ist
das ein Fund: Dann fehlt sie in den Fachregeln und gehört ergänzt, nicht nur der
Verweis geändert.

**Akzeptanzkriterium:** `grep -rn 'Master-Prompt' --include='*.php' controller
core modelle services views public` ist leer, und jede vorher zitierte Regel ist
in einer `docs/fachregeln/`-Datei auffindbar.

---

### F-5 · Zwei englische Methodennamen

[controller/KurzarbeitAdminController.php:137](controller/KurzarbeitAdminController.php:137)
`setFlashOk()` und `:145` `setFlashErr()`. Die Regel lautet Deutsch für
Bezeichner; das Projekt heißt sonst durchgehend `hole…`, `speichere…`,
`pruefe…`. Vorschlag: `setzeHinweis()` / `setzeFehler()`.

`getInstanz()` bleibt, wie es ist – das ist über 20 Klassen hinweg einheitlich
und damit selbst eine Konvention.

**Akzeptanzkriterium:** Kurzarbeit anlegen, ändern und umschalten zeigen
weiterhin dieselben Meldungen.

---

## Phase 7 – Abschluss

Nach dem letzten Patch, in einem Durchgang:

```bash
# 1. Syntax
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l \
  | grep -v '^No syntax errors' || echo 'OK'

# 2. Kaltstart-Größe
cat CLAUDE.md CHATSTART.md docs/arbeitsregeln.md docs/STATUS_SNAPSHOT.md | wc -c

# 3. Tote Markdown-Links
for f in $(find . -name '*.md' -not -path './.git/*'); do
  d=$(dirname "$f")
  grep -oP '\]\(\K[^)#]+' "$f" | grep -v '^http' | while read -r z; do
    [ -e "$d/$z" ] || [ -e "$z" ] || echo "TOT: $f -> $z"
  done
done

# 4. Genannte Repo-Pfade, die es nicht gibt
#    Erwartete Ausnahmen: config/geraet.local.php und
#    scripts/terminal/terminal.conf werden zur Laufzeit erzeugt.
```

Dazu von Hand:

- Die Kernabläufe aus `docs/wartungscheckliste.md` – **beide** Listen, Backend
  und Terminal.
- Terminal einmal mit abgeschalteter Hauptdatenbank: Offline-Buchung, Queue
  füllt sich, DB wieder an, Queue läuft leer.
- `docs/STATUS_SNAPSHOT.md` nachziehen: erledigte B-/T-IDs raus, die neuen
  T-IDs aus E-c und F-1 rein.
- **Diese Datei löschen.**

---

## Was bewusst *nicht* in dieser Liste steht

Damit später niemand sucht, ob es übersehen wurde:

- **`SmokeTestController::index()` mit rund 3.700 Zeilen in einer Methode.**
  Sachlich der schlimmste Befund des Durchgangs. Aber: Die Datei ist ein
  Diagnosewerkzeug, sie berührt keine Fachlogik, und ein Umbau wäre ein
  Vorhaben für sich. Gehört als T-ID in den Snapshot, nicht in diesen Durchgang.
- **Ein Prüfschritt, der Quelltext nach einer Zeichenkette durchsucht:**
  [controller/SmokeTestController.php:230](controller/SmokeTestController.php:230)
  sucht per `strpos` nach `function beendeLetztePassendeLaufendeAuftragszeit…`.
  Beim Umbenennen der Methode schlägt der Selbsttest fehl, obwohl alles
  funktioniert. Ebenfalls T-ID.
- **Der Sammel-PDF-Export** ruft `holeMonatsdatenFuerMitarbeiter()` je
  Mitarbeiter, jeweils mit mehreren Abfragen. Auf einem Raspberry Pi ist das
  spürbar. Es ist aber kein Fehler, sondern der natürliche Zuschnitt – und ohne
  Messung an echten Daten wäre jede Optimierung geraten. Erst messen.
- **99 Verweise auf T-/B-IDs in Code-Kommentaren.** Anders als die
  Master-Prompt-Verweise (F-4) zeigen die auf `DEV_PROMPT_HISTORY.md`, und die
  gibt es weiterhin. Sie sind in Ordnung.
- **`docs/rechte_prompt.md` heißt „Prompt", ist aber eine Spezifikation.** Ein
  Umbenennen berührt sieben Verweise in anderen Dokumenten. Lohnt sich nur,
  falls ohnehin an der Datei gearbeitet wird.
- **`sql/README.md` listet die Migrationen als 02, 03, 05, 04, 06.** Eine Zeile
  tauschen; zu klein für einen eigenen Patch, bei nächster Gelegenheit
  mitnehmen.

---

## Kurzübersicht

| Punkt | Thema | Aufwand | Risiko |
| --- | --- | --- | --- |
| D-1 | SQL-Quoting der Offline-Queue | klein | gering |
| D-2 | Toter Logging-Zweig im Urlaubssaldo | sehr klein | sehr gering |
| D-3 | B-092 Betriebsferien-Schalter | mittel | gering |
| D-4 | QR-Rückfall bei Maschinen-Codes | klein | gering |
| A-1…A-5 | Kaltstart 18,8 KB → unter 12 KB | mittel | gering |
| B-1…B-3 | Falsche Angaben in der Doku | klein | keins |
| C-1…C-4 | ~1.100 Zeilen toter Code | mittel | gering |
| E-1 | CSRF zentralisieren (15 Controller) | **groß** | mittel |
| E-2 | Zwei Konfigurationsdienste zusammenführen | mittel | gering |
| E-3 | `getPdo`/`method_exists` vereinheitlichen | mittel | gering |
| E-4 | 305 × `class_exists('Logger')` | **groß** | gering |
| E-5 | Queue-Status doppelt in `terminal.php` | mittel | mittel |
| E-6 | Dreifacher Programmstart | klein | gering |
| F-1…F-5 | Kleinigkeiten | klein | gering |

**Wenn nur wenig Zeit ist:** D-1, D-2, A-1 bis A-5, C-1, C-2. Das sind die
Punkte mit dem besten Verhältnis von Aufwand zu Nutzen – zwei echte Fehler, der
gewünschte Kaltstart-Gewinn und die gefährlichsten toten Dateien.
