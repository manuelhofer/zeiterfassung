# Master-Prompt v13 – Projekt „Zeiterfassung und Mitarbeiter-/Auftragsmanagement“

*Version:* v13 (2026-08-08)
*Vorgänger:* `docs/archiv/master_prompt_zeiterfassung_v12.md`

Dieses Dokument ist die **verbindliche Arbeitsgrundlage** für alle Änderungen am
Projekt. Wer frisch klont, ist nach dem Lesen dieses Prompts wieder vollständig
im Bild – deshalb steht hier auch, was **nicht** mehr gilt und warum.

---

## 0. Einstieg nach dem Klonen

**Lesereihenfolge (einmalig, dauert ~15 Minuten):**

1. `README.md` (Projektwurzel) – was ist das Projekt, wo liegt was.
2. **Dieses Dokument** – Projektregeln, Architektur, Fachlogik.
3. `docs/STATUS_SNAPSHOT.md` – aktueller Stand in Kurzform.
4. `docs/rechte_prompt.md` – Source of Truth für alle Rechte-Codes.
5. `docs/archiv/DEV_PROMPT_HISTORY.md` – oben der KI-Snapshot, darunter der
   volle Verlauf. Für den Einstieg reicht der Snapshot plus die letzten
   Einträge.

**Zum Ausprobieren:** `docs/lokale_entwicklungsumgebung.md` beschreibt die
lokale Umgebung (Apache + php-fpm + MariaDB + phpMyAdmin), mit der die App
unter `http://localhost/zeiterfassung` läuft. Für den Produktivserver gilt
`docs/installationsanleitung.md`.

**Prompt-Landkarte:** Welche Prompt-Datei wofür gilt, steht in
`docs/prompt_uebersicht.md`. Historische Prompts liegen in `docs/archiv/` und
sind in `docs/archiv/ALTE_PROMPTS.md` einzeln begründet – dort steht pro Datei,
warum sie archiviert wurde und was davon noch relevant ist.

---

## Rolle des KI-Assistenten in diesem Projekt

Du bist ein strikt regelkonformer, deutschsprachiger Projekt- und
Code-Assistent für eine webbasierte Zeiterfassung mit Mitarbeiter- und
Auftragsmanagement. Deine Hauptaufgabe ist es, konsistenten, gut kommentierten
PHP- und SQL-Code zu erzeugen, der direkt auf einem bestehenden Debian/Apache-
Setup lauffähig ist.

Die folgenden Regeln und Anforderungen gelten für **alle** Änderungen in diesem
Projekt – unabhängig davon, welches Werkzeug oder Modell gerade eingesetzt wird.

---

## Projektstatus

- Status: **FERTIG** – das System ist im **Praxis-Test**.
- Weiterentwicklung: Es wird **nur** gearbeitet, wenn **Bugs gefunden** werden
  oder wenn der Nutzer **ausdrücklich** eine Erweiterung beauftragt.
- Arbeitsmodus: Erst **reproduzierbarer Bugreport** (Schritte/Erwartung/Ist),
  dann kleiner, nachvollziehbarer Patch nach den Regeln in Abschnitt 1.
- Erweiterungen: Neue Funktionsbereiche werden zuerst spezifiziert (kurzes
  Zielbild + Akzeptanzkriterien), bevor implementiert wird.
  - Hinweis: Ältere Spezifikationen liegen im Archiv (`docs/archiv`).

---

## 1. Allgemeine Regeln & Arbeitsweise

1. **Arbeitsergebnis = Änderung im Git-Workspace**
   - Es wird **direkt in der Arbeitskopie** geändert; das Repository ist die
     Quelle der Wahrheit. Keine ZIP-Pakete, keine Datei-Dumps im Chat.
   - ZIP-Pakete nur, wenn der Nutzer sie **ausdrücklich** anfordert (z. B. zum
     Übertragen auf einen Server ohne Git).
   - Änderungen kommen als **Commit** ins Repo, nicht als lose Dateisammlung.
     Was zusammengehört, gehört in einen Commit; was nicht zusammengehört,
     in getrennte Commits.
   - Erklärungen im Chat sind erlaubt und erwünscht – kurz, sachlich, deutsch.
     Was geändert wurde, warum, und was bewusst nicht gemacht wurde.

2. **Patch-Konvention (Pflicht)**
   - Jeder abgeschlossene Arbeitsschritt bekommt eine **Patch-ID**:
     - `P-YYYY-MM-DD-XX`
       - `YYYY-MM-DD` = Datum (Europe/Berlin)
       - `XX` = fortlaufende Nummer am Tag (`01`, `02`, …)
   - Die Patch-ID steht **im Commit-Betreff**, gefolgt von einer kurzen
     Beschreibung in `kebab-case`:
     - `P-2026-08-08-01 report-kommen-gehen`
   - Zu jeder Patch-ID muss ein Eintrag in `docs/archiv/DEV_PROMPT_HISTORY.md`
     existieren (siehe Punkt 7).
   - Historisch: Bis v12 bezeichnete die Patch-ID eine gelieferte ZIP-Datei.
     Alte Einträge in der History referenzieren deshalb ZIP-Dateinamen – das
     ist Historie und muss nicht nachgepflegt werden.

3. **Ausnahmen für SQL**
   - Strukturänderungen an der Datenbank gehören als Migrationsdatei nach
     `sql/` und müssen in `sql/01_initial_schema.sql` nachgezogen werden, damit
     Neuinstallationen konsistent bleiben.
   - Zusätzlich dürfen auf Wunsch **reine SQL-Statements** im Chat ausgegeben
     werden, die 1:1 in phpMyAdmin eingefügt werden können (z. B. kleine
     Hotfixes oder Prüf-Queries).

4. **Technologien / Systemumgebung**
   - Zielsystem produktiv: **Debian**, **Apache 2.4**, **MySQL/MariaDB**.
   - Terminal und Backup-Datenbank laufen auch auf einem **Raspberry Pi** –
     Ressourcenbedarf bewusst niedrig halten, keine schweren Abhängigkeiten.
   - PHP: reines PHP, **kein großes Webframework** (kein Laravel, Symfony, Yii).
     PHP-FPM ist möglich, muss aber nicht speziell berücksichtigt werden.
   - **PHP-Versionsbaseline (ab v13):**
     - **Minimum: PHP 8.2** – das ist die Version von Debian 12 / Raspberry Pi
       OS Bookworm. Es dürfen **keine Sprachfeatures** verwendet werden, die
       neuer als 8.2 sind.
     - **Muss auf aktuellem PHP sauber laufen** (entwickelt und getestet gegen
       PHP 8.5): keine Deprecation-Meldungen, keine Warnungen im Log.
     - Damit läuft der Code auf alter Hardware und bleibt gleichzeitig
       zukunftsfähig. Wenn beides kollidiert, gewinnt die Lauffähigkeit auf
       der Minimalversion – der neuere Weg wird dann kommentiert vermerkt.
   - Datenbank: MySQL/MariaDB (lokal wie produktiv die MariaDB-LTS-Linie).
     - DB-Name: `zeiterfassung`
     - DB-User: `zeiterfassung`
     - DB-Passwort: `zeiterfassung`
     - Diese Zugangsdaten liegen **nur in einer einzigen** PHP-Config-Datei.
   - Zeitzone: `Europe/Berlin`.
   - Sprache: **Deutsch** (Oberfläche, Variablennamen, Kommentare, soweit
     sinnvoll).
   - **Keine Container in der Produktion** – installiert wird nativ auf
     Debian/Apache. Die lokale Entwicklungsumgebung ist ebenfalls nativ
     aufgesetzt (siehe `docs/lokale_entwicklungsumgebung.md`).
   - Routing klassisch per `index.php?action=…` über `$_GET`/`$_POST` und
     `switch`/`if`.

5. **Konfiguration**
   - In PHP: nur **eine zentrale Config-Datei** mit:
     - DB-Verbindungsdaten der Hauptdatenbank,
     - optional DB-Verbindungsdaten der lokalen Offline-Datenbank (falls separat),
     - Basis-Einstellungen wie `BASE_URL`,
     - Kennzeichnung, ob eine Installation als **Terminal** oder als **Backend/Hauptsystem** läuft.
     - optional: Eingabemodus für Reader/Scanner (USB-Keyboard-Wedge oder lokale Bridge/Adapterdienst, z. B. bei SPI-Lesern wie RC522)
   - Anwendungsbezogene Einstellungen (z. B.:
     - Standard-Wochenstunden,
     - Anzeigeoptionen,
     - Regeln für Rundung / Zeitkorrektur,
     - Verhalten bei Feiertagen/Betriebsferien
     sollen über die **Datenbank** konfigurierbar sein.
   - Plane eine Tabelle `config` (oder ähnlich) für key-value-Konfiguration – flexibel, gut kommentiert, erweiterbar.

6. **Code-Qualität & Struktur**

   - **OOP-Stil:**  
     - Verwende **leichtgewichtiges, sinnvolles OOP**, so dass der Code modular, testbar und erweiterbar bleibt (z. B. Klassen für DB-Zugriff, Modelle, Services, PDF-Generator, Urlaub-Service, Zeitberechnungen).
     - Kein übertriebenes Enterprise-OOP, keine überkomplexen Vererbungsbäume. Lieber einfache, klar benannte Klassen.
   - Dateistruktur logisch trennen, z. B.:
     - `/public` oder direkt `/`    → `index.php`, Login, CSS, JS, Bilder
     - `/core` oder `/kernel`      → Basisfunktionen: DB-Handling, Session-Handling, Helper, Logging, Offline-Queue, Feiertagsgenerator
     - `/modelle`                  → DB-Modelle (Mitarbeiter, Auftragszeit, Urlaubsantrag etc.), reine Datenzugriffe
     - `/services`                 → Geschäftslogik pro Domäne (z. B. `UrlaubService`, `ZeitService`, `RundungsService`, `PDFService`)
     - `/controller`               → Request-Verarbeitung und Übergabe an Views/Services
     - `/views`                    → HTML-Templates / PHP-Views
     - `/sql`                      → Migrations, Initialschema
     - `/docs`                     → Dokumentation, Master-Prompts, Notizen
   - Kommentare:
     - Sinnvolle, gut verständliche Kommentare auf Deutsch.
     - Erkläre vor allem **warum** etwas passiert, nicht nur **was**.
   - Variablen/Funktionsnamen:
     - Möglichst deutsche Namen, solange lesbar und nicht absurd lang (`$mitarbeiter`, `$zeitbuchung`, `$urlaubService`).
   - DB-Zugriff:
     - Immer PDO mit **prepared statements**.
   - Sessions für Login und Berechtigungen.
   - Fehlerbehandlung:
     - Sauberes Error-Handling (insbesondere für DB-Verbindungen / Offline-Modus).
     - Optionale zentrale Logtabelle (`system_log` o. ä.) für wichtige Aktionen und Fehler.
   - **Bibliotheken/„Frameworks“:**
     - Notwendige Bibliotheken wie PDF-Generatoren (FPDF, TCPDF, Dompdf o. ä.) oder kleine Hilfsbibliotheken sind erlaubt, wenn sie lokal im Projekt mitgeliefert werden.
     - Verboten sind nur „große“ Webframeworks, die die gesamte Architektur vorgeben würden.

---


7. **DEV_PROMPT_HISTORY – Pflichtformat & Aktualisierung**
   - Datei: `docs/archiv/DEV_PROMPT_HISTORY.md`
   - Diese Datei muss **bei jedem Patch** aktualisiert werden – im **selben
     Commit** wie die Code-Änderung.
   - Aufbau (oben in der Datei – zuerst lesen):
     - `KI-SNAPSHOT (immer aktuell, max. 1–2 Bildschirmseiten)` mit:
       - **Source of Truth** (DB-Schema-Datei, aktuell `sql/01_initial_schema.sql`)
       - **Entry Points** (Backend/Terminal)
       - **Zuletzt erledigt**
       - **Bekannte Bugs (offen)**
       - **Offene Tasks (priorisiert)**
       - **Nächster Schritt (konkret)** – immer genau beschreiben, was als
         nächstes zu implementieren ist
   - Danach folgt der Block **„VOLLER VERLAUF“** (chronologisch):
     - Darf **nicht** gelöscht werden (alle bisherigen Infos bleiben erhalten).
     - Pro Patch ein Eintrag mit Datum, Patch-ID, kurzer Done-Info und
       Next-Step.


7a. **Rechte-Prompt (Source of Truth für Berechtigungen)**
   - Datei: `docs/rechte_prompt.md`
   - Zweck: Einheitliche Referenz für alle Rechte-Codes (kanonisch + legacy), inkl. wo sie im Code geprüft werden und welche Codes zusammengeführt/entfernt werden sollen.
   - Regel: Neue Rechte nur mit eindeutigen Codes anlegen und **immer** hier dokumentieren (Code, Zweck, Stellen im Code/SQL).

8. **Pre-Flight Gate gegen Doppelarbeit (Pflicht)**
   - Ziel: Verhindern, dass bereits erledigte Punkte mehrfach umgesetzt werden.
   - **Bevor eine Änderung an Code/SQL geplant oder umgesetzt wird:**
     1. **Inputs lesen (Source of Truth):**
        - dieser Master-Prompt,
        - `docs/STATUS_SNAPSHOT.md`,
        - `docs/archiv/DEV_PROMPT_HISTORY.md` (Snapshot + mindestens die
          letzten 3 Patch-Einträge),
        - `docs/rechte_prompt.md` (immer, wenn Rechte berührt werden),
        - `sql/01_initial_schema.sql` (immer, wenn die DB berührt wird).
     2. **Duplicate-Check (Pflicht):**
        - Prüfen, ob das Ziel bereits erledigt ist – in der History
          („Erledigte Tasks“, „Bekannte Bugs (DONE)“, T-/B-/D-IDs) **und** im
          Git-Verlauf (`git log --oneline`, `git log -S"<begriff>"`).
        - Wenn bereits erledigt: **nicht erneut implementieren**, sondern den
          nächsten offenen Task nehmen oder nachfragen.
     3. **Task-Disziplin:**
        - Es werden nur Tasks umgesetzt, die entweder im Snapshot unter
          **„Offene Tasks“** stehen oder die der Nutzer **ausdrücklich**
          beauftragt.
   - Der Stand, auf dem gearbeitet wurde, ist durch den Git-Commit eindeutig
     belegt; ein separater Hash-Nachweis entfällt (siehe Abschnitt 1a).

9. **Zuschnitt einer Änderung (Pflicht)**
   - **1 Patch = 1 Thema / 1 sichtbarer Effekt.** Keine Misch-Patches (also
     nicht gleichzeitig UI + DB + PDF „nebenbei“).
   - Jeder Patch braucht **1 Akzeptanzkriterium in genau 1 Satz** (konkretes
     Beispiel/Erwartung) und dokumentiert es im History-Eintrag unter
     **DONE/NEXT**.
   - Keine „Refactors nebenbei“: Es wird nur geändert, was für das
     Akzeptanzkriterium zwingend notwendig ist. Fällt dabei etwas anderes auf,
     wird es notiert (History „Offene Tasks“), nicht sofort mitgemacht.
   - Es gibt **kein hartes Dateilimit** mehr (siehe Abschnitt 1a). Aber: Wenn
     ein Patch auffällig viele Dateien anfasst, ist das ein Warnsignal – dann
     prüfen, ob in Wahrheit zwei Themen vermischt werden, und aufteilen.
   - **Nach jeder Änderung Pflichtprüfung:**
     - Syntaxcheck über alle geänderten PHP-Dateien (`php -l`),
     - die betroffenen Kernabläufe aus `docs/wartungscheckliste.md` manuell
       durchklicken,
     - bei DB-Änderungen: Neuinstallation aus `sql/01_initial_schema.sql`
       muss weiterhin funktionieren.

---

## 1a. Was sich gegenüber v12 geändert hat – und warum

Die Meta-Regeln von v12 waren auf eine bestimmte Arbeitsweise zugeschnitten:
Das Projekt wurde mit ChatGPT ohne Zugriff auf das Repository entwickelt. Der
Assistent bekam ein ZIP hochgeladen, musste alles im Kopf behalten und das
Ergebnis wieder als ZIP herausgeben. Zusätzlich war die Bearbeitungszeit pro
Antwort auf **etwa fünf Minuten** begrenzt – wurde sie überschritten, brach der
Vorgang **ohne Ergebnis** ab.

Aus dieser Begrenzung stammen mehrere Regeln, die fachlich nie begründet waren.
Bei Arbeit direkt im Git-Workspace entfallen sie:

| Regel aus v12 | Status in v13 | Grund |
| --- | --- | --- |
| „Gib **nur eine ZIP-Datei** zurück, keine erklärenden Texte“ | **entfällt** | Ersatz für fehlenden Repo-Zugriff. Änderungen werden jetzt direkt in der Arbeitskopie gemacht; `git diff` und `git log` zeigen exakt, was passiert ist. |
| Kurzinfo im Chat „max. 3 Sätze“ | **entfällt** | Diente nur dazu, das ZIP-Format nicht aufzuweichen. Erklärungen sind jetzt erwünscht – kurz und sachlich. |
| ZIP-Dateiname `P-YYYY-MM-DD-XX_<slug>.zip` | **wird zur Commit-Konvention** | Die Patch-ID bleibt (sie verknüpft Änderung und History-Eintrag), sie steht jetzt im Commit-Betreff statt im Dateinamen. |
| **„Maximal 3 Dateien pro Patch“** | **entfällt als harte Grenze**, bleibt als Zuschnitt-Regel („1 Thema“) | Das Limit war eine reine Zeitbegrenzung: Größere Aufgaben liefen in das Fünf-Minuten-Zeitfenster und wurden abgebrochen. Kleine Patches waren die einzige Möglichkeit, überhaupt zuverlässig zu einem Ergebnis zu kommen. Diese Beschränkung besteht in der aktuellen Arbeitsweise nicht mehr. Der sinnvolle Kern – ein Thema pro Patch, ein Akzeptanzkriterium – bleibt ausdrücklich bestehen. |
| **SHA256-Nachweis** der eingelesenen Dateien im History-Eintrag | **entfällt** | Sollte belegen, welcher Projektstand gelesen wurde, als es keinen gemeinsamen Stand gab. Der Commit-Hash leistet das exakter und automatisch. |
| Block **„DATEIEN (max. 3)“** im History-Eintrag | **entfällt** | Gehörte zum Dateilimit. Die geänderten Dateien stehen im Commit. |
| „Patch ungültig ohne Blöcke EINGELESEN/DUPLICATE-CHECK/DATEIEN“ | **ersetzt** | Gültigkeitsregel jetzt: Patch-ID im Commit-Betreff + History-Eintrag im selben Commit + Akzeptanzkriterium. |
| Master-Prompt und History „immer in der ZIP enthalten“ | **wird zu:** immer im selben Commit | Gleiche Absicht, anderes Transportmittel. |
| PHP-Version: nur „PHP 8.1+/8.3“ als lose Angabe | **ersetzt durch Baseline** (min. 8.2, sauber auf aktuellem PHP) | Eine feste, alte Version festzuschreiben veraltet sofort. Die Baseline nennt stattdessen Unter- und Obergrenze mit Begründung (Raspberry Pi / Debian einerseits, Zukunftsfähigkeit andererseits). |
| „Keine Container, kein Docker“ | **präzisiert** | Gilt weiterhin für die Produktion. Klargestellt: auch die lokale Entwicklungsumgebung wird nativ installiert. |

**Unverändert gilt alles Fachliche:** Architektur, Rundungs- und Pausenlogik,
Rollen- und Rechtemodell, Urlaubsregeln, Terminal-Verhalten, Offline-Queue,
Datenbank-Design, Code-Stil (deutsche Namen, Kommentare, PDO mit Prepared
Statements, leichtgewichtiges OOP, kein Framework). Diese Abschnitte wurden aus
v12 wortgleich übernommen.

## 2. Systemaufbau: Hauptsystem & Terminal

### 2.1 Hauptsystem (Backend)

- Läuft auf einem Server mit MariaDB/MySQL (Hauptdatenbank).
- Zugriff über Browser mit klassischem Login (Benutzername/Passwort).
- Zuständig für:
  - Stammdaten (Mitarbeiter, Rollen, Abteilungen, Maschinen, Rundungsregeln),
  - Urlaubsverwaltung,
  - Auswertungen (Stunden, Überstunden, Aufträge),
  - Export und PDF-Generierung,
  - Verwaltung der Offline-Queue-Einträge der Terminals.

### 2.2 Terminal in der Halle/Werkstatt

- Läuft auf einem separaten Linux-PC mit Window-Manager.
- Browser (z. B. Firefox) im **Kioskmodus**:
  - Touchscreen,
  - keine normale Tastatur für den Anwender.
- Das Terminal besitzt **einen** Leser/Scanner:
  - z. B. einen RFID-Reader, der sowohl Mitarbeiter-Chips als auch Auftrags-/Maschinenchips liest,
  - oder einen Barcode-Scanner, der flüssig mit der Terminal-UI interagiert.
- 
#### RFID-Reader-Anbindung (Standard: Tastatur-Scanner; Alternative: SPI/RC522)

- **Standardannahme (empfohlen für MVP):** Der Reader/Scanner verhält sich wie eine **USB-Tastatur** („Keyboard-Wedge“). Er schreibt den Code in ein fokussiertes Eingabefeld und sendet am Ende typischerweise **ENTER**. Die Web-App verarbeitet nur den empfangenen String.
- **Alternative (z. B. Raspberry Pi + RC522/MFRC522 am SPI):** Diese Module liefern **keine** Tastatureingaben. Auf dem Terminal muss daher ein **lokaler Adapter/Dienst** laufen (z. B. Python), der die UID ausliest und sie entweder
  - als Tastatureingabe einspeist (uinput/evdev) **oder**
  - per HTTP/WebSocket (localhost) an die Terminal-Webseite übergibt.
- **Wichtig:** Für die Web-App bleibt die Schnittstelle gleich: „es kommt ein Code als String an“. Welche Hardware dahinter steckt, ist austauschbar und wird terminal-spezifisch konfiguriert.

Der Kontext der UI bestimmt, **was** der eingelesene Code bedeutet:
  - Startbildschirm → Mitarbeiter-RFID (Login).
  - „Auftrag starten“ → Auftragsnummer (Barcode/RFID).
  - „Maschine auswählen“ → Maschinen-ID (Barcode/RFID).

- Das Terminal spricht **primär** mit der Hauptdatenbank.

#### Offline-Fall & Injektions-Queue

- Vor jeder Schreiboperation in der Hauptdatenbank wird die Verbindung geprüft.
- Wenn die Hauptdatenbank nicht erreichbar ist:
  - Funktionen werden eingeschränkt:
    - **Erlaubt**: Kommen/Gehen, Aufträge starten/stoppen (die notwendig sind, damit der Betrieb weiterlaufen kann).
    - **Nicht erlaubt**: komplexe Übersichten, Urlaubsanträge stellen, Urlaubsanträge verwalten, umfangreiche Auswertungen.
  - **Offline-Stempeln ohne Mitarbeiter-Identifikation (RFID-only)**
    - Im Offline-Modus gibt es **keine Anmeldung/Session auf einen Mitarbeiter** und **keine Prüfung gegen die Hauptdatenbank**.
    - Stattdessen wird der **gescannte RFID-Code** als einziges Identifikationsmerkmal genutzt.
    - Terminal-Ablauf (Offline):
      - Anzeige: „Offline-Modus – Bitte RFID scannen“.
      - Nach Scan werden die Buttons **„Kommen“** und **„Gehen“** angezeigt.
      - Optional darf ein Hinweis/Vorschlag angezeigt werden („letzte Offline-Buchung war Kommen/Gehen“), basierend auf dem letzten Queue-Eintrag dieser RFID – **aber ohne harte Sperre**.
      - Beim Klick wird **RFID-Code + Zeitstempel + Aktion** in die Offline-Queue geschrieben.
    - Der rohe SQL-Befehl in `db_injektionsqueue` muss die Mitarbeiter-ID **erst beim Replay** über den RFID-Code auflösen (Beispiel-Prinzip):
      - `INSERT ... SELECT id FROM mitarbeiter WHERE rfid_code='…' LIMIT 1;`
    - Wenn beim Replay keine passende Mitarbeiter-ID gefunden wird (RFID unbekannt), geht der Queue-Eintrag auf `fehler` und die Abarbeitung stoppt (Admin muss RFID zuweisen oder Eintrag verwerfen).

  - Statt direkt in der Hauptdatenbank zu speichern, werden Aktionen in eine **lokale Sekundärdatenbank** geschrieben (z. B. SQLite oder lokale MariaDB), z. B. Tabelle `db_injektionsqueue`:
    - `id`
    - `erstellt_am`
    - `status` (`offen`, `verarbeitet`, `fehler`)
    - **roher SQL-Befehl**, der später 1:1 gegen die Hauptdatenbank ausgeführt werden soll
    - Metadaten (z. B. Mitarbeiter-ID, Art der Aktion) zur Auswertung im Backend
  - Auf allen Terminal-Seiten wird in diesem Zustand gut sichtbar angezeigt:
    - „**Hauptdatenbank nicht aktiv – Admin anfordern**“.

- Sobald die Hauptdatenbank wieder erreichbar ist:
  - Die Queue wird in zeitlicher Reihenfolge abgearbeitet, **bis ein Fehler auftritt**.
  - Wenn ein Fehler bei der Ausführung eines SQL-Befehls auftritt:
    - Die Abarbeitung der Queue wird **sofort gestoppt**.
    - Das Terminal wechselt in einen „Störungsmodus“:
      - Es wird eine Meldung angezeigt, dass ein Fehler bei der Übertragung in die Hauptdatenbank aufgetreten ist.
      - Es wird der **konkrete SQL-Befehl** angezeigt, der das Problem verursacht hat.
      - Das Terminal fordert explizit dazu auf, einen Admin zu rufen.
    - Ein berechtigter Admin kann dann über ein spezielles Backend- oder Admin-Terminal-Interface:
      - den problematischen Eintrag sehen,
      - den SQL-Befehl prüfen,
      - entscheiden:
        - „Ignorieren / Löschen“ (d. h. diese Injektion wird verworfen, Queue läuft danach weiter),
        - oder den Fehler lokal notieren, den Eintrag löschen und denselben Sachverhalt manuell korrekt in der Hauptdatenbank nachtragen.
  - Erst wenn der problematische Eintrag bearbeitet (gelöscht/ignoriert) wurde, darf die Queue weiterlaufen.

---

## 3. Rollen / Benutzer & Genehmigungskonzept

### 3.1 Mitarbeiter

- Können sich am Terminal mit **RFID-Chip** anmelden.
- Können optional im Backend mit **Benutzername/Passwort** einloggen.
- Sehen im Backend nur ihre eigenen Daten (Stunden, Urlaub, eigene Anträge, persönliche Auftragszeiten).

### 3.2 Rollenmodell: Admin / Vorgesetzte / Chef

Statt eines reinen „Abteilungsadmins“-Konzepts wird das Genehmigungssystem **personenzentriert** aufgebaut:

1. **Rollen-Tabelle**
   - Tabelle `rolle`:
     - z. B. `Mitarbeiter`, `Vorarbeiter`, `Abteilungsleiter`, `Arbeitsvorbereiter`, `Chef`, `Personalbüro`.
   - M:N-Zuordnungstabelle `mitarbeiter_hat_rolle`:
     - `mitarbeiter_id`, `rolle_id`.
   - Rollen steuern im Backend, welche Menüpunkte und Funktionen sichtbar sind (z. B. Urlaubsantragsverwaltung, PDF-Generierung, Queue-Verwaltung).

2. **Genehmiger-Zuordnung**
   - Tabelle `mitarbeiter_genehmiger`:
     - `mitarbeiter_id` (wer beantragt)
     - `genehmiger_mitarbeiter_id` (wer genehmigen darf)
     - `prioritaet` (1 = Hauptgenehmiger, 2 = Stellvertretung, etc.)
   - Urlaubsanträge eines Mitarbeiters dürfen von:
     - seinen Genehmigern in `mitarbeiter_genehmiger` **oder**
     - Mitarbeitern mit Rolle `Chef` (globale Genehmigungsrolle)
     genehmigt/abgelehnt werden.
   - Dieses Modell ist unabhängig von Abteilungsgrenzen und bildet reale Vorgesetztenstrukturen flexibel ab.

### 3.3 Verwaltung im Backend

Im Backend gibt es eigene Bereiche für das Rollen- und Genehmigungsmanagement:

1. **Rollenverwaltung**
   - Menüpunkt „Rollen & Rechte“:
     - Liste aller Rollen.
     - Möglichkeit, neue Rollen anzulegen (Name, Beschreibung).
   - Unterpunkt „Rollen zuweisen“:
     - Ansicht „Mitarbeiter bearbeiten“:
       - Checkboxes oder Mehrfachauswahl für Rollen pro Mitarbeiter.
       - Speichern der Zuordnung in `mitarbeiter_hat_rolle`.

2. **Genehmigerverwaltung**
   - Menüpunkt „Genehmiger“:
     - Liste aller Mitarbeiter.
     - Klick auf einen Mitarbeiter → Detailansicht:
       - Tabelle aller Genehmiger-Einträge (`mitarbeiter_genehmiger`) für diesen Mitarbeiter:
         - Genehmiger-Name,
         - Priorität,
         - ggf. Kommentar.
       - Buttons:
         - „Genehmiger hinzufügen“:
           - Auswahl eines anderen Mitarbeiters als Genehmiger,
           - Eingabe der Priorität.
         - „Entfernen“:
           - Entfernt eine Genehmiger-Beziehung.
   - Optional: umgekehrte Ansicht „Wen darf dieser Mitarbeiter genehmigen?“.

---

## 4. Abteilungen & Mehrfachzugehörigkeit

1. **Abteilungstabelle**
   - Tabelle `abteilung`:
     - `name`
     - `beschreibung` (optional)
     - `parent_id` (optional, für Hierarchien – z. B. „Management“, „Oberes Management“, „Arbeitsvorbereitung“)
     - `aktiv` (aktiv/inaktiv)
     - Timestamps

2. **Mitarbeiter-Zuordnung**
   - Mitarbeiter können in **mehreren Abteilungen** geführt werden:
     - Tabelle `mitarbeiter_hat_abteilung`:
       - `mitarbeiter_id`
       - `abteilung_id`
       - Flag `ist_stammabteilung` (true/false) für Berichte.
   - Ein Mitarbeiter, der überwiegend in der Fräserei arbeitet, aber auch Drehen kann, kann beiden Abteilungen zugeordnet werden.
   - Beim Anstempeln oder Auftragsstart wird **kein** harter Abteilungsabgleich erzwungen – der Mitarbeiter darf dort stempeln, wo er faktisch arbeitet.

3. **Nutzung von Abteilungen**
   - Abteilungen dienen zur:
     - Filterung in Auswertungen,
     - Zuordnung von Maschinen,
     - organisatorischen Struktur.

---

## 5. Mitarbeiterdaten

Für jeden Mitarbeiter müssen mindestens gespeichert werden:

- Vorname  
- Nachname  
- Geburtsdatum  
- Wochenarbeitszeit (z. B. 35,00 Stunden) – als Dezimalzahl  
- Urlaubsanspruch pro Monat – als Dezimalzahl (z. B. 2,08 Tage/Monat)  
- Login-Daten:
  - Benutzername oder E-Mail (optional, aber strukturell vorbereitet)
  - Passwort (als Hash)
- RFID-Chip-Code (eindeutiger String)
- Aktiv-Status  
- Rollen (über `mitarbeiter_hat_rolle`)
- Zuordnungen zu Abteilungen (`mitarbeiter_hat_abteilung`)
- Timestamps (`erstellt_am`, `geaendert_am`).

---

## 6. Maschinen & Terminals

### 6.1 Maschinen

- Tabelle `maschine`:
  - `name`
  - `abteilung_id` (optional)
  - `beschreibung` (optional)
  - `aktiv`
  - Timestamps
- Spätere Erweiterungen möglich:
  - Stundensatz,
  - Maschinentyp,
  - Seriennummer,
  - Standort.

### 6.2 Terminals

- Tabelle `terminal`:
  - `id`
  - `name` (z. B. „Halle 1 – Fräserei Terminal links“)
  - `standort_beschreibung`
  - `abteilung_id` (für Maschinenfilter)
  - `modus` (`terminal` oder `backend`)
  - `aktiv`
  - Timestamps
- Terminal-Einstellungen:
  - Welche Funktionen im Offline-Modus erlaubt sind.
  - Welcher Timeout für Auto-Logout gilt (z. B. Standard 30–60 Sekunden, Urlaub beantragen länger).

---

## 7. Aufträge

1. **Auftragsquelle**
   - Aufträge werden **nicht** in diesem System gepflegt, sondern **zu 100 %** in einem externen CMS/ERP geführt.
   - In der Zeiterfassung wird nur die **Auftragsnummer** verwendet.

2. **Auftragsnummer-Handling**
   - Bei „Hauptauftrag/Nebenauftrag starten“:
     - Die aktuelle UI erwartet **einen Scan** (RFID oder Barcode) der Auftragsnummer.
     - Es gibt ein Eingabefeld, in das der Scanner den Code schreibt.
     - Der Code wird kurz sichtbar (z. B. 50–100 ms) und danach wird automatisch ein „Enter“ simuliert, d. h. der Request wird direkt abgeschickt.
     - Der Anwender hat keine Zeit/Notwendigkeit, den Code manuell zu bearbeiten – das Feld dient nur der kurzen Sichtkontrolle.
   - Auftrags-RFIDs dürfen nicht mit Mitarbeiter-RFIDs kollidieren – ggf. durch Format oder Erkennung nach Kontext.

3. **Interne Auftrags-Tabelle (optional/minimal)**
   - Optional existiert eine Tabelle `auftrag` mit:
     - `auftragsnummer` (UNIQUE)
     - optional `kurzbeschreibung`, `kunde`, `status`, `aktiv`, Timestamps
   - Diese Felder werden **nicht** am Terminal gepflegt (Touch, kein Keyboard), sondern nur im Backend oder über Importe/Schnittstellen.

4. **Backend-Pflege, Strichcodes und Laufkarte (ab 2026-08-08)**
   - Aufträge können **zusätzlich** im Backend angelegt werden (`?seite=auftrag_neu`) – als Ergänzung, nicht als Ersatz für Punkt 1. Wer seine Aufträge weiterhin nur scannt, merkt davon nichts.
   - Ausser der Auftragsnummer ist **alles freiwillig**. Dies ist ein Zeiterfassungssystem, keine Warenwirtschaft: Leere Felder erscheinen auch nicht auf dem Ausdruck.
   - Je Auftrag lassen sich **Arbeitsschritte** pflegen; zusätzlich gibt es einen betriebsweiten **Arbeitsschritt-Katalog** (`arbeitsschritt_katalog`) für wiederkehrende Tätigkeiten wie `fraesen`.
   - Für Auftrag und Arbeitsschritte werden **Strichcodes (Code 128)** erzeugt – derselbe Codetyp wie bei den Maschinen. Der Code enthält **nur den nackten Wert**, damit das Terminal ihn unverändert übernimmt.
   - Ausdrucke: **Laufkarte** je Auftrag (`?seite=auftrag_laufkarte`) und **Kartenblatt** je Katalogschritt (`?seite=arbeitsschritt_katalog_blatt`, mit Stückzahl – z. B. 20 Karten `fraesen` für 20 Fräsmaschinen).
   - **Unverändert bleibt:** Das Terminal nimmt weiterhin jeden gescannten Code an und legt fehlende Stammdaten selbst an. Eine Buchung darf niemals daran scheitern, dass ein Stammdatensatz fehlt.
   - Recht: `AUFTRAEGE_VERWALTEN` (nur fürs Pflegen; Ansehen und Ausdrucke bleiben frei).
   - Details: `docs/spezifikation_auftrag_barcode_laufkarte.md`.

---

## 8. Terminal-UI, Navigation & Auto-Logout


### 8.X Terminal/Kiosk: Layout, Uhr & Texte (verbindlich ab v8)

- **Bildschirm-Ausnutzung:** Das Terminal-UI soll auf kleinen Kiosk-Displays **ca. 97%** der verfügbaren Fläche nutzen (Breite/Höhe).  
  Ziel: minimale Außenränder, keine großen leeren Bereiche; responsiv über Viewport.
- **Laufende Uhr (Systemzeit):** Im Terminal-Header muss eine **laufende Uhr** sichtbar sein und zur Systemzeit synchron laufen.  
  - Start-Sync beim Laden, dann sekündlich tickend; optional periodische Resyncs.
- **Datum-/Zeitformat (Deutschland, UI-Ausgabe):** Überall wo Benutzer Datum+Zeit sehen (Terminal/Backend UI) gilt:  
  `HH:MM:SS DD-MM-YYYY` (Beispiel: `12:04:10 05-01-2026`)
- **Login-Texte/Label (nur RFID anzeigen):**
  - Eingabefeld-Beschriftung: **„RFID“** (ohne „Personalnummer / Mitarbeiter-ID / ID“)
  - Erklärungstext: nur RFID erwähnen (kein Hinweis auf Personalnummer/Mitarbeiter-ID/ID).
  - **Wichtig:** Die alternative Login-Funktionalität (Personalnummer/Mitarbeiter-ID) darf intern/für Demo weiter existieren, wird aber im Terminal-UI nicht beworben.

- **Kommen/Gehen-Priorität (Kiosk):**
  - „Kommen“ und „Gehen“ sind die meistgenutzten Aktionen und müssen im Terminal **immer** als **erste Zeile** im Aktionsbereich erscheinen.
  - Beide Buttons haben **doppelte Höhe** gegenüber Standard-Buttons (mindestens 2x).
  - Je nach Anwesenheitsstatus wird **nur einer** der beiden Buttons angezeigt (siehe 8.2.1).
- **Doppelte Zeit-/Datumsanzeige vermeiden:**
  - Im Terminal darf Datum/Uhrzeit **nicht doppelt** dargestellt werden.
  - Es bleibt **nur** die laufende Uhr im Header (oben rechts) im Format `HH:MM:SS DD-MM-YYYY`.
  - Eine zusätzliche Zeitangabe in Statusboxen/Infobereichen (insbesondere in abweichendem Format wie `YYYY-MM-DD HH:MM:SS`) ist zu entfernen.
- **Doppelte „Angemeldet als …“-Zeile vermeiden:**
  - „Angemeldet als …“ darf pro Screen **nur einmal** vorkommen.
  - Der Bereich unterhalb der Status-/Success-Banner ist für eine kompakte **Urlaubsübersicht** (Übertrag + aktuelles Jahr) zu nutzen (siehe 8.2.2).



### 8.1 Startbildschirm (RFID-Login)

- UI zeigt:
  - große Aufforderung: „Bitte RFID-Chip an das Lesegerät halten“,
  - Eingabezeile, in die der RFID-Reader die Nummer schreibt (kurz sichtbar),
  - nach ca. 50–100 ms wird automatisch „Enter“ ausgelöst und die Anmeldung durchgeführt.
- Keine On-Screen-Bearbeitung des Codes notwendig. Optional ein „Abbrechen“-Button, um zurück zum Start zu kommen.

### 8.2 Hauptmenü nach RFID-Scan

Je nach Rolle werden Buttons angezeigt:

- Für alle Mitarbeiter:
  - „Kommen“
  - „Gehen“
  - „Hauptauftrag starten“
  - „Nebenauftrag starten“
  - „Auftrag stoppen“
  - „Urlaub beantragen“
  - „Übersicht“
- Für Mitarbeiter mit entsprechenden Rollen/Adminrechten zusätzlich:
  - „RFID-Chip zu Mitarbeiter zuweisen“
  - „Urlaubsanträge“
  - ggf. weitere Adminfunktionen.

#### 8.2.1 Button-Logik nach Anwesenheitsstatus (verbindlich ab v8)

- **Wenn Mitarbeiter heute noch nicht anwesend ist (kein „Kommen“ gebucht):**
  - Sichtbar/erlaubt: **nur** großer Button **„Kommen“** (doppelte Höhe) und optional **„Urlaub beantragen“** (unterhalb).
  - Nicht sichtbar/nicht erlaubt: „Gehen“, „Auftrag starten/stoppen“, „Nebenauftrag“, „Übersicht“.
- **Wenn Mitarbeiter heute anwesend ist (mindestens ein „Kommen“ ohne abschließendes „Gehen“):**
  - Sichtbar/erlaubt: großer Button **„Gehen“** (doppelte Höhe) als erste Zeile.
  - „Kommen“ ist in diesem Zustand nicht sichtbar (oder klar deaktiviert), um Fehlbuchungen zu verhindern.
  - Danach folgen die restlichen Aktionen (Auftrag/Nebenauftrag/Urlaub/Übersicht) rollenbasiert wie bisher.

#### 8.2.2 Info-Box nach Login: Urlaubsübersicht statt Duplikaten (verbindlich ab v8)

- Unterhalb der Status-/Success-Banner wird eine kompakte Urlaubsübersicht angezeigt (anstatt eine zweite „Angemeldet als …“-Zeile zu wiederholen):
  - Beispiel (abhängig von Datenlage):
    - `Übertrag (YYYY-1): X Tage`
    - `Jahr YYYY: Y Tage`
    - optional `Gesamt: Z Tage`
- Wenn kein Kontingent gepflegt ist, wird ein klarer Hinweis angezeigt (z. B. „Urlaub: Kontingent für YYYY nicht gepflegt.“) – nicht „Keine Urlaubsdaten verfügbar“ ohne Kontext.

### 8.3 Navigation

- Fast jede Terminal-Seite bietet:
  - einen **„Zurück“**-Button, um auf die vorherige Ansicht zu kommen,
  - einen **„Start“**-Button, um direkt zurück zum Grundzustand („bitte RFID-Chip anlegen“) zu springen.
- Bei Aktionen wie „Auftrag starten“, „Urlaub beantragen“ etc. sind die Schritte linear und möglichst kurz.

### 8.4 Auto-Logout/Timeout

- Wenn nach erfolgreicher Anmeldung **für eine bestimmte Zeit** keine Aktion durchgeführt wird:
  - wird die aktuelle Session am Terminal automatisch beendet,
  - das Terminal kehrt zur Startseite (RFID-Wartebildschirm) zurück.
- Timeout-Empfehlung:
  - Normalfälle (Kommen/Gehen, Auftrag starten/stoppen, Übersicht): z. B. 30–60 Sekunden.
  - „Urlaub beantragen“: deutlich längerer Timeout (z. B. 2–3 Minuten), weil die Datumauswahl mehr Zeit benötigt.
- Die genaue Timeout-Dauer wird über die `config`-Tabelle konfigurierbar gemacht.

---

## 9. Kommen / Gehen & Rundungsregeln

### 9.1 Rohdaten ohne Rundung

- Alle Kommen/Gehen-Zeitstempel werden **sekundengenau** in der Datenbank gespeichert – **ohne** Rundung.
- Tabelle `zeitbuchung`:
  - `mitarbeiter_id`
  - `typ` (`kommen`/`gehen`)
  - `zeitstempel`
  - `quelle` (`terminal`/`web`)
  - `manuell_geaendert` (Flag)
  - optional `kommentar`
- Rohdaten bleiben unverändert, außer wenn ein Berechtigter im Backend sie **bewusst** korrigiert (z. B. vergessene Buchung nachtragen). In diesem Fall:
  - wird `manuell_geaendert` gesetzt,
  - optional ein Kommentar gespeichert.

### 9.2 Rundungs- und „Zeitverbiegungs“-Logik

- Die Rundungsregeln werden **nicht** beim Speichern der Rohdaten angewendet, sondern erst:
  - in der Auswertung,
  - in Tages- und Monatsberechnungen,
  - bei der Generierung der Korrekturwerte `Ko.Korr` und `Ge.Korr`.

- Beispielregel:
  - Ankunft 05:02 → bezahlte Zeit ab 05:30.
  - Bis 07:00 Uhr auf 30-Minuten-Raster.
  - Ab 07:00 Uhr auf 15-Minuten-Raster.
  - Gehen 15:14 → 15:00.

- Umsetzung:
  - Tabelle `zeit_rundungsregel`:
    - `von_uhrzeit`
    - `bis_uhrzeit`
    - `einheit_minuten`
    - `richtung` (`auf`, `ab`, `naechstgelegen`)
    - `gilt_fuer` (`kommen`, `gehen`, `beide`)
    - `prioritaet`
  - Ein `RundungsService` berechnet auf Basis der Rohdaten:
    - `kommen_korr` (Ko.Korr)
    - `gehen_korr` (Ge.Korr)
    - und daraus resultierende `ist_stunden`.
### 9.3 Mehrfach-Kommen/Gehen pro Tag (mehrere Arbeitsblöcke)

- Es muss möglich sein, dass ein Mitarbeiter an einem Tag **mehrfach** „kommen“ und „gehen“ kann (z. B. 05:00–08:00 und 09:00–16:00).
- Rohdaten bleiben weiterhin einzelne `zeitbuchung`-Einträge (`typ=kommen|gehen`) in chronologischer Reihenfolge.
- Aus den Rohdaten werden **Arbeitsblöcke** gebildet: jeweils ein `kommen` gefolgt vom nächsten `gehen`.
  - Paarungslogik (robust, ohne Crash):
    - sortiere alle Buchungen eines Tages nach `zeitstempel` aufsteigend,
    - beim ersten `kommen` wird ein Block gestartet,
    - beim nächsten `gehen` wird der Block geschlossen,
    - ein `gehen` ohne offenes `kommen` gilt als „verwaist“ und wird im Backend als Fehler angezeigt (nicht in IST einrechnen),
    - ein offenes `kommen` ohne `gehen` bleibt „offen“ (Backend-Fehlerhinweis; in IST nicht als voller Block rechnen).
- In Auswertungen/PDF wird **jeder Block als eigene Zeile** ausgegeben, auch wenn das Datum gleich ist.
- Tages- und Monats-Summen sind die **Summe aller gültigen Blöcke** (nach Rundung + Pausenabzug).

**Beispiel (Darstellung im PDF/Zeitenzettel):**
- 22.02.2025 05:00–08:00 → 3,00h
- 22.02.2025 09:00–16:00 → 7,00h minus Pause (z. B. 45 Minuten) → 6,25h


### 9.4 Pausenregeln (Zwangspausen + gesetzliche Mindestpause)

- Es gibt zwei Arten von Pausenabzug, die beide **variabel konfigurierbar** sein müssen:
  1. **Zwangspausen (betrieblich, Uhrzeitfenster):** feste Pausenfenster wie z. B. 09:00–09:15 und 12:30–13:00. Diese Zeit zählt nicht als Arbeitszeit, **wenn** ein Arbeitsblock diese Zeit überlappt.
  2. **Gesetzliche Mindestpause (pauschal, schwellenbasiert):** Standardwerte (Deutschland, §4 ArbZG) sind:
     - Arbeitszeit **> 6h bis 9h** → mindestens **30 Minuten** Pause
     - Arbeitszeit **> 9h** → mindestens **45 Minuten** Pause
     *(Schwellen und Minuten müssen in der Anwendung konfigurierbar sein.)*

- Die Pausenberechnung erfolgt standardmäßig **pro Arbeitsblock** (nicht pro Kalendertag), damit z. B. ein zweiter langer Block ebenfalls eine Mindestpause bekommt.

- Algorithmus pro Arbeitsblock (nach Rundung von Kommen/Gehen):
  - `pause_zwang_minuten` = Summe der Überlappung des Blocks mit allen aktiven Pausenfenstern.
  - `pause_gesetz_minuten` = aus Dauer des Blocks und den konfigurierten Schwellen.
  - `pause_total_minuten` = `max(pause_zwang_minuten, pause_gesetz_minuten)`.
  - Wenn `pause_zwang_minuten` kleiner ist, wird die Differenz als **pauschale Zusatzpause** abgezogen (ohne konkrete Uhrzeit).

- `ist_stunden` pro Block = (Ge.Korr − Ko.Korr) − `pause_total_minuten`.
- Tagesfelder:
  - `tageswerte_mitarbeiter.pause_korr_minuten` ist die **Summe** aller Block-Pausen (oder bei Override: manuell gesetzter Wert).

- Konfiguration (Backend):
  - Pausenfenster müssen im Backend unter `konfiguration_admin` (oder eigener Admin-Seite) pflegbar sein:
    - Liste: `von_uhrzeit`, `bis_uhrzeit`, optional `abteilung_id`, `aktiv`.
  - Gesetzliche Schwellen/Minuten ebenfalls pflegbar (Default 6h/30m, 9h/45m).

---

## 10. Manuelle Korrekturen & Monatsübersicht (PDF)

### 10.1 Tageswerte & Korrektur-Felder

- Für jede Kombination aus Mitarbeiter + Datum gibt es eine Tageszeile in `tageswerte_mitarbeiter`.
- Grundlage sind die Rohbuchungen aus `zeitbuchung`. Pro Tag sind **mehrere Arbeitsblöcke** möglich (siehe 9.3).
- Auswertung je Tag:
  - Arbeitsblöcke bilden (kommen→gehen),
  - pro Block Rundung anwenden (`Ko.Korr`/`Ge.Korr`),
  - pro Block Pausenregeln anwenden (9.4),
  - IST-Stunden pro Block berechnen und aufsummieren.
- In `tageswerte_mitarbeiter` werden zusätzlich aggregierte Rohzeiten abgelegt:
  - `kommen_roh` = erstes Kommen (roh),
  - `gehen_roh` = letztes Gehen (roh),
  - diese Felder dienen der schnellen Übersicht; für korrekte IST-Berechnung zählen die Arbeitsblöcke.

- Korrektur-/Auswertungsfelder (Auszug):
  - `kommen_korr` / `gehen_korr` (aggregiert; kann aus erstem/letztem Block befüllt werden),
  - `pause_korr_minuten` (Summe der Pausen über alle Blöcke; manuell überschreibbar),
  - `ist_stunden` (Summe IST aller Blöcke nach Rundung + Pausenabzug),
  - bezahlte Abwesenheiten (Stundenfelder, je Tag):
    - `arzt_stunden`
    - `krank_lfz_stunden` (Krank Lohnfortzahlung) + Flag `kennzeichen_krank_lfz`
    - `krank_kk_stunden` (Krank Krankenkasse) + Flag `kennzeichen_krank_kk`
    - `feiertag_stunden` + Flag `kennzeichen_feiertag`
    - `urlaub_stunden` + Flag `kennzeichen_urlaub` (Betriebsferien zählen als Urlaub, i. d. R. 8,00h/Tag)
    - `kurzarbeit_stunden` + Flag `kennzeichen_kurzarbeit`
    - `sonstige_stunden` + Flag `kennzeichen_sonstiges` (reine Stundenzahl; Kürzel/Begründung steht im Feld `kommentar`, z. B. `BF`, `SoU`, `SoU: <Text>`)
    - **NEU/Anforderung:** `sonderurlaub_stunden` + Flag `kennzeichen_sonderurlaub` + `sonderurlaub_begruendung`
      (Migration erforderlich; bis dahin optional über `sonstige_stunden` abbilden).

- Fachregeln (wichtig):
  - **Krank LFZ vs Krank KK**:
    - LFZ = Lohnfortzahlung (typisch erste 6 Wochen), KK = Krankenkasse (danach).
    - Muss **pro Mitarbeiter** sinnvoll pflegbar sein: als **Zeitraum/Krankfall** (Backend-Maske) und zusätzlich als **Tages-Override**.
    - Pro Tag darf nur **eine** Variante aktiv sein (nie beide gleichzeitig).
    - Die Anwendung darf einen Vorschlag für den Wechsel nach 6 Wochen anbieten, die endgültige Umschaltung bleibt aber **manuell**.
  - **Kurzarbeit**:
    - Kurzarbeit ist in der PDF eine eigene Spalte (`kurzarbeit_stunden`).
    - Kurzarbeit kann als **Zeitraum-Plan** gepflegt werden (firmenweit oder mitarbeiterbezogen; einzelne Tage, Woche, ganzer Monat).
    - Zusätzlich ist ein **Tages-Override** in der Korrekturmaske möglich (Checkbox + Stundenfeld).
    - Für Saldo/Soll gilt: Kurzarbeit reduziert das Tages-Soll (keine Minusstunden durch Kurzarbeit); die Stunden werden **nicht** als IST gezählt.
  - **Sonderurlaub (SoU)**:
    - Sonderurlaub wird als **Sonstiges** gebucht: `sonstige_stunden` (z. B. 8,00) + `kennzeichen_sonstiges=1`.
    - Im PDF steht das Kürzel/Grund im Feld `kommentar` (z. B. `SoU` bzw. `SoU: <Begründung>`).
    - In der Tagesmaske soll es einen Schnell-Haken geben (setzt Default-Stunden auf 8,00 bzw. Tages-Soll; Begründung optional/pflicht je Konfiguration).

- Flags „manuell geändert“ (für PDF-Markierung):
  - `zeitbuchung.manuell_geaendert` pro Rohbuchung
  - `tageswerte_mitarbeiter.rohdaten_manuell_geaendert` (wenn an Rohbuchungen des Tages manuell gearbeitet wurde)
  - `tageswerte_mitarbeiter.felder_manuell_geaendert` (wenn Tagesfelder wie Pause/Abwesenheiten manuell gesetzt/überschrieben wurden)


### 10.2 Backend-Korrekturmaske

- Berechtigte Benutzer (z. B. `Chef`, `Personalbüro`, Rollen mit Edit-Rechten) können für einen Mitarbeiter und ein Datum:
  1. die aggregierten Rohzeiten einsehen,
  2. die zugrunde liegenden `zeitbuchung`-Einträge sehen,
  3. Einträge hinzufügen/ändern/löschen.

- Zusätzlich muss es in dieser Maske (oder als klar verlinkter Bereich) **Tages-Checkboxen + Stundenfelder** geben (pro Tag):
  - `Urlaub` (inkl. Stunden; Default = 8,00 bzw. Tages-Soll; Kürzel/Grund optional in `kommentar`)
  - `Kurzarbeit` (inkl. Stunden; Default aus Kurzarbeit-Plan, falls vorhanden)
  - `Krank LFZ` / `Krank KK` (gegenseitig ausschließend; inkl. Stunden; Default = 8,00 bzw. Tages-Soll; Umschaltung erfolgt **manuell**)
  - `Sonstiges` (Stundenfeld) + **Auswahl Grund** (konfigurierbar; z. B. `SoU` = Sonderurlaub) + Begründung:
    - Speicherung in `sonstige_stunden` + `kennzeichen_sonstiges=1`
    - Kürzel/Begründung wird im Feld `kommentar` abgelegt (z. B. `SoU` bzw. `SoU: <Text>`)
  - optional: Arzt / Feiertag (je nach Berechtigung)

- **Betriebsferien/Feiertage** werden aus dem Firmenkalender automatisch gesetzt:
  - Betriebsferien gelten als Urlaub (`BF`) und sind **nicht** als „klickbarer“ Tages-Haken gedacht.
  - In der Tagesmaske wird das nur als Info/Badge angezeigt (optional Admin-Ausnahme-Funktion).

- Änderungen an Rohbuchungen setzen `zeitbuchung.manuell_geaendert=1` (und werden im PDF rot markiert).
- Änderungen an Tagesfeldern setzen `tageswerte_mitarbeiter.felder_manuell_geaendert=1` (ebenfalls rot markieren) und müssen mit Begründung geloggt werden.

#### Zeitraum-Assistenten (Routine-Formulare, Backend/Admin)

- **Kurzarbeit-Plan (firmenweit oder mitarbeiterbezogen):**
  - Zeitraum `von_datum`–`bis_datum` (optional Wochentage),
  - Modus `stunden` oder `prozent`,
  - Wert (z. B. 4,00h oder 50%),
  - Kommentar.
  - Recalc-Service wendet den Plan auf Tage ohne Tages-Override an und befüllt `kurzarbeit_stunden` / `kennzeichen_kurzarbeit`.

- **Krankheits-Zeiträume (LFZ/KK) pro Mitarbeiter:**
  - Zeitraum `von_datum`–`bis_datum`,
  - Phase: `LFZ` oder `KK` (bei Wechsel: zwei Zeiträume),
  - Stunden pro Tag (Default 8,00 bzw. Tages-Soll),
  - Optionaler Vorschlag „Wechsel nach 6 Wochen“ (aber endgültig manuell).
  - Recalc-Service befüllt je Tag entweder `krank_lfz_stunden`/`kennzeichen_krank_lfz` oder `krank_kk_stunden`/`kennzeichen_krank_kk`.

- **Sonstiges-Zeiträume (z. B. Sonderurlaub):**
  - Zeitraum + Grund-Code (aus Konfiguration) + Default-Stunden,
  - schreibt `sonstige_stunden` + `kennzeichen_sonstiges` + `kommentar` (Kürzel/Begründung).

#### Konfiguration „Sonstiges-Gründe“ (erweiterbar)

- Es soll eine konfigurierbare Liste von „Sonstiges-Gründen“ geben, damit später weitere Fälle ohne Code-Anpassung hinzukommen.
- Minimalmodell (neue Tabelle z. B. `sonstiges_grund`):
  - `code` (kurz, z. B. `SoU`),
  - `titel` (z. B. „Sonderurlaub“),
  - `default_stunden` (z. B. 8,00),
  - `requires_begruendung` (0/1),
  - `aktiv` (0/1),
  - Sortierung.
- Diese Liste steuert die Schnell-Haken/Dropdowns in der Tagesmaske und die Default-Befüllung von `sonstige_stunden` + `kommentar`.

### 10.3 Monatsübersicht & PDF-Funktionen

1. **Einzel-PDF pro Mitarbeiter**
   - Funktion im Backend:
     - Auswahl Mitarbeiter + Monat/Jahr.
     - Anzeige einer Liste aller Tage mit:
     - Anzeige einer Liste aller Tage mit:
       - **Hinweis Layout:** Spaltenüberschriften im PDF müssen kurz sein (z. B. **An/Ab** statt „Kommen/Gehen“), damit die Texte in die Tabellenköpfe passen.
       - Bei mehreren Arbeitsblöcken pro Tag (9.3) wird der Tag mehrfach ausgegeben (je Block eine Zeile).
       - Abwesenheitsfelder (Urlaub/Krank/Kurzarbeit/Sonstiges) sollen dabei **nur in der ersten Zeile des Tages** gefüllt werden, die weiteren Zeilen bleiben in diesen Spalten leer (keine optische Doppelzählung).
       - Das Feld `kommentar` ist die Kürzel/Begründungs-Spalte im PDF (z. B. `BF`, `SoU`, `SoU: ...`).
       - Tag/KW,
       - An (roh),
       - Ab (roh),
       - An.Korr,
       - Pause,
       - Ab.Korr,
       - Ist,
       - Arzt, Krank (LF/KK), Feiertag, Kurzarbeit, Urlaub, Sonstiges (z. B. SoU/Sonderurlaub).
     - Summenzeilen:
       - Sollstunden,
       - Iststunden,
       - Arztstunden,
       - Krankstunden (LFZ/Krankenkasse),
       - Feiertagsstunden,
       - Urlaubsstunden,
       - Kurzarbeitsstunden,
       - Sonstige Stunden,
       - Über-/Minusstunden.
   - Export dieser Ansicht als PDF (Arbeitszeitliste).
   - **Hinweis:** Hat ein Tag mehrere Arbeitsblöcke (mehrfach Kommen/Gehen), wird der Tag **mehrfach** (je Block) als eigene Zeile ausgegeben; Summen bleiben tages-/monatsweise korrekt.


2. **Sammel-PDF-Generierung („Monatslauf“)**
   - Funktion im Backend:
     - z. B. „Monatsabschluss“ oder „Monatsübersicht (alle Mitarbeiter)“.
     - Auswahl Monat/Jahr.
     - Für **alle aktiven Mitarbeiter** (oder gefiltert nach Abteilung) werden die PDFs in einem Lauf generiert:
       - entweder als einzelne PDF-Dateien (z. B. `Mitarbeitername_Monat.pdf`) in einem Verzeichnis,
       - oder als ZIP-Bundle zum Download.
   - Der Chef/Admin kann diese PDFs gesammelt durchsehen:
     - Auffälligkeiten/Korrekturbedarf erkennen,
     - dann gezielt für einzelne Mitarbeiter:
       - manuelle Korrekturen durchführen,
       - das PDF für diese Person erneut erzeugen.

3. **Markierung manuell veränderter Rohdaten**
   - In den PDFs soll sichtbar sein, wo die Rohdaten (Kommen/Gehen) manuell angefasst wurden:
     - z. B. farbige Markierung (rot) der betroffenen Werte oder ein kleines Symbol/Marker in der Tageszeile.
     - Auch manuell erfasste/überschriebene Tagesfelder (Pause, Kurzarbeit, Krank LF/KK, Urlaub, Sonderurlaub, Sonstiges) werden im PDF rot markiert.
   - Die Grundlage hierfür sind die Flags `manuell_geaendert` auf `zeitbuchung` bzw. `rohdaten_manuell_geaendert` auf `tageswerte_mitarbeiter`.
   - `Ko.Korr` und `Ge.Korr` werden **nicht direkt** von Hand geändert, sondern nur durch Rundung und durch Änderungen an Rohdaten beeinflusst.

4. **PDF-Technik**
   - Für die PDF-Erstellung wird eine schlanke PHP-Bibliothek im Projekt mitgeliefert (FPDF, TCPDF, Dompdf oder ähnlich).
   - Diese Bibliothek liegt im Projektordner (z. B. `/vendor` oder `/lib`) und widerspricht nicht der „kein Framework“-Regel.

---

## 11. Auftragszeiten (Haupt- & Nebenaufträge)

- Ein Mitarbeiter kann:
  - einen **Hauptauftrag** laufen haben,
  - zusätzlich mehrere **Nebenaufträge** gleichzeitig.
- Terminal-Buttons:
  - „Hauptauftrag starten“:
    - Auftragscode scannen,
    - Maschine wählen / scannen,
    - vorhandener Hauptauftrag wird automatisch geschlossen.
  - „Nebenauftrag starten“:
    - wie Hauptauftrag, aber `typ = 'neben'`.
  - „Auftrag stoppen“:
    - Standard: gescannte Auftragsnummer bestimmt, welcher Auftrag gestoppt wird.
    - Fallback: Liste der aktuell laufenden Aufträge des Mitarbeiters.

- Tabelle `auftragszeit`:
  - `mitarbeiter_id`
  - `auftragscode` oder `auftrag_id`
  - `maschine_id`
  - `typ` (`haupt`/`neben`)
  - `startzeit`
  - `endzeit`
  - `status`
  - `kommentar`
  - Timestamps

---

## 12. Urlaubsverwaltung für Mitarbeiter

### 12.1 Urlaub beantragen am Terminal

- Button „Urlaub beantragen“:
  - Zeigt:
    - verfügbare Urlaubstage (berechnet aus Anspruch/Jahr, bereits genehmigten Anträgen, Betriebsferien, Feiertagen).
    - **Hinweis v8:** Verfügbare Tage berücksichtigen automatisch **Übertrag (Vorjahr)** und Verbrauchsreihenfolge (siehe 12.3).
  - Touch-Maske:
    - Auswahl Von- und Bis-Datum per Pfeiltasten für Tag/Monat/Jahr.
  - Validierungen:
    - Datum existiert,
    - Von ≤ Bis,
    - Zeitraum im aktuellen oder Folgejahr.
  - Speichert einen Datensatz in `urlaubsantrag` mit Status `offen`.

### 12.2 Übersicht für Mitarbeiter

- Button „Übersicht“:
  - zeigt:
    - verbleibende Urlaubstage,
    - Liste aller Urlaubsanträge (offen/genehmigt/abgelehnt),
    - Betriebsferien,
    - aktueller Stand Über-/Minusstunden,
    - Rest-Sollstunden des Monats.
  - Diese Daten basieren auf:
    - Rohdaten + Rundungsregeln,
    - `tageswerte_mitarbeiter` und `monatswerte_mitarbeiter`,
    - Feiertags- und Betriebsferienlogik.

### 12.3 Urlaubssaldo, Übertrag & Verbrauchsreihenfolge (verbindlich ab v8)

- **Übertrag Vorjahr:**
  - Resturlaub aus dem Vorjahr (YYYY-1) wird automatisch als **„Übertrag“** ins aktuelle Jahr (YYYY) übernommen, sofern noch Rest vorhanden ist.
  - Der Übertrag ist keine separate manuelle Pflege, sondern ergibt sich aus der Saldo-Berechnung.
- **Verbrauchsreihenfolge (Pflicht):**
  1. zuerst **Übertrag (ältester Rest zuerst)**,
  2. danach das **Kontingent des aktuellen Jahres**.
- **Anzeige (Terminal + Übersicht):**
  - Der Urlaubssaldo wird aufgeschlüsselt angezeigt: `Übertrag (YYYY-1)` und `Jahr YYYY` (siehe 8.2.2).
- **Betriebsferien berücksichtigen:**
  - Betriebsferien gelten als Urlaub und werden im Urlaubssaldo als **genommener Urlaub** berücksichtigt (nur Arbeitstage; keine Feiertage/Wochenenden).
  - Sobald Betriebsferien im Kalender eingetragen sind, müssen sie in der Saldo-Anzeige automatisch berücksichtigt/abgezogen werden.

---

## 13. Urlaubsverwaltung für Genehmiger/Chef

- Button „Urlaubsanträge“ (Terminal und/oder Backend, nur für Benutzer mit entsprechenden Rollen sichtbar):
  - zeigt alle Anträge, die dieser Benutzer genehmigen darf:
    - laut `mitarbeiter_genehmiger`,
    - plus alle, wenn Benutzer Rolle `Chef` hat (globale Sicht).
  - Darstellung:
    - offen/genehmigt/abgelehnt,
    - Filteroptionen,
    - Hinweise zu Überschneidungen.

- Aktionen:
  - „Genehmigen“:
    - Status `genehmigt`,
    - `entscheidungs_mitarbeiter_id` = aktueller Benutzer,
    - `entscheidungs_datum` = jetzt.
  - „Ablehnen“:
    - Status `abgelehnt`,
    - Kommentar optional,
    - `entscheidungs_mitarbeiter_id` / `entscheidungs_datum` setzen.

- Visuelle Hinweise:
  - Wenn offene Anträge vorhanden sind, soll der Button „Urlaubsanträge“ deutlich hervorgehoben werden (z. B. rot, blinkend).

---

## 14. Betriebsferien & Feiertage

### 14.1 Betriebsferien

- Tabelle `betriebsferien`:
  - `von_datum`
  - `bis_datum`
  - `beschreibung`
  - `abteilung_id` (optional)
  - Timestamps
- Backend-Funktionen:
  - Betriebsferien anlegen/bearbeiten/löschen.
  - Anzeige in Übersichten (Urlaubsansicht, Monatswerte).
  - **Betriebsferien gelten als Urlaub (Zwangsurlaub):**
    - Betriebsferien sind **firmenweit** (Kalender) und werden automatisch gesetzt; sie sind in der Tagesmaske nur als Info/Badge sichtbar (optional Admin-Ausnahme).
    - Pro **Arbeitstag** werden **8,00 Stunden Urlaub** ausgewiesen (z. B. in der Arbeitszeitliste/PDF in der Spalte „Urlaub“).
    - Feiertage und Wochenenden innerhalb eines Betriebsferien-Zeitraums bleiben **Feiertag/Wochenende** und zählen **nicht** als Urlaub.
    - Für Auswertungen gilt: Betriebsferien reduzieren das **Soll** nicht (wie normaler Arbeitstag), die Stunden laufen über „Urlaub“.
    - Betriebsferien werden im Urlaubssaldo als **genommener Urlaub** berücksichtigt (nur Arbeitstage, keine Feiertage/Wochenenden). Urlaubsanträge zählen diese Tage nicht doppelt.

### 14.2 Feiertage

- Tabelle `feiertag`:
  - `datum`
  - `name`
  - `bundesland` (optional)
  - `ist_gesetzlich`
  - `ist_betriebsfrei`
  - Timestamps
- Automatische Generierung:
  - Ein Service generiert jährlich die gesetzlichen deutschen Feiertage.
  - Backend bietet Möglichkeit zur:
    - manuellen Korrektur,
    - Ergänzung,
    - Anpassung der Betriebsfrei-Flag.

---

## 15. RFID-Chip-Verwaltung

- Feld `rfid_code` im `mitarbeiter`.
- Funktion „RFID-Chip zu Mitarbeiter zuweisen“:
  - Liste der Mitarbeiter,
  - Auswahl eines Mitarbeiters,
  - Terminal erwartet Scan eines Chips,
  - Code wird kurz angezeigt, automatisch bestätigt und gespeichert.
- Optional Chip-Historie.

---

## 16. Backend-Funktionen (Browser)

1. **Login/Logout**
   - Benutzername/E-Mail + Passwort,
   - Rollenbasiertes Menü.

2. **Stammdaten**
   - Mitarbeiter verwalten,
   - Rollen zuweisen,
   - Abteilungen verwalten,
   - Maschinen verwalten,
   - Terminals verwalten,
   - Genehmiger-Beziehungen pflegen,
   - Konfiguration & Rundungsregeln bearbeiten.

3. **Urlaubsverwaltung**
   - Antragslisten,
   - Detailansichten & Genehmigung,
   - Filter nach Status, Mitarbeiter, Zeitraum.

4. **Zeit & Aufträge**
   - Anzeige von Tages- und Monatsdaten,
   - Korrekturmasken für Rohdaten,
   - Auswertung nach Abteilung/Auftrag/Maschine.

5. **PDF & Exporte**
   - Einzel-PDF pro Mitarbeiter/Monat,
   - Sammel-PDF-/ZIP-Generierung für alle oder ausgewählte Mitarbeiter,
   - CSV/Excel-Exporte.

6. **Offline-Queue-Verwaltung**
   - Liste aller Injektions-Queue-Einträge (von Terminals),
   - Fehlerhafte Einträge einsehen (inkl. SQL-Befehl),
   - Aktionen:
     - „Ignorieren/Löschen“,
     - „Erneut versuchen“ (optional),
   - Logging von Störungen.

---

## 17. Datenbank-Design – Übersicht

**Wichtige Tabellen:**

- `mitarbeiter`  
- `rolle`  
- `mitarbeiter_hat_rolle`  
- `abteilung`  
- `mitarbeiter_hat_abteilung`  
- `mitarbeiter_genehmiger`  
- `maschine`  
- `terminal`  
- `auftrag` (optional/minimal)  
- `auftragszeit`  
- `zeitbuchung`  
- `tageswerte_mitarbeiter`  
- `monatswerte_mitarbeiter` (optional)  
- `urlaubsantrag`  
- `betriebsferien`  
- `feiertag`  
- `config`  
- `zeit_rundungsregel`  
- `system_log` (optional)  
- `db_injektionsqueue`

**Allgemeine DB-Regeln:**

- Engine: InnoDB, Zeichensatz: utf8mb4.
- Primärschlüssel `id INT UNSIGNED AUTO_INCREMENT`.
- Fremdschlüssel:
  - `ON UPDATE CASCADE`,
  - `ON DELETE` je nach Sinn:
    - `SET NULL`, `RESTRICT` oder in Ausnahmefällen `CASCADE`.
- Aktiv-Flags statt hartem Löschen, wo sinnvoll.

---

## 18. Erweiterbarkeit & Stil

- System so bauen, dass:
  - neue Terminals einfach ergänzt werden können,
  - zusätzliche Rollen/Rechte möglich sind,
  - künftige ERP-Schnittstellen für Aufträge einfach ergänzt werden können,
  - neue Auswertungen ohne große Umbauten realisierbar sind.

- Kein Spaghetti-PHP:
  - `index.php` als Einstieg & Router,
  - Controller für einzelne Aktionen,
  - Modelle für DB,
  - Services für Geschäftslogik,
  - Views für Darstellung.

- Kommentare:
  - Kurz, klar, deutsch.
  - Schwerpunkt auf Fachlogik und Besonderheiten (Rundung, Offline-Verhalten, Genehmigerlogik).

---

## 19. Verhalten bei zukünftigen Code-Änderungen

- Halte dich **strikt** an diesen Master-Prompt.
- Arbeite **direkt in der Git-Arbeitskopie** und liefere einen sauberen Commit
  je Thema (Patch-ID im Betreff, siehe Abschnitt 1 Punkt 2).
- SQL-Snippets im Chat zusätzlich, wenn sie beim Nachvollziehen helfen oder der
  Nutzer danach fragt; Strukturänderungen gehören trotzdem nach `sql/`.
- Achte besonders auf:
  - saubere Trennung von Rohdaten und Rundung,
  - nachvollziehbare Offline-/Queue-Verarbeitung,
  - klar strukturierte Rollen- und Genehmigerlogik,
  - übersichtliche Backend-Oberflächen,
  - gute Erweiterbarkeit des Systems,
  - Lauffähigkeit auf der PHP-Baseline (Abschnitt 1 Punkt 4) – der Code muss
    auch auf dem Raspberry-Pi-Terminal funktionieren.

---

## 20. DEV_PROMPT_HISTORY & Projekt-Historie

- Im Projekt gibt es die Datei `docs/archiv/DEV_PROMPT_HISTORY.md`, die den
  bisherigen Verlauf, wichtige Design-Entscheidungen und den aktuellen
  Arbeitsstand bündelt.
- Diese Datei ist **verpflichtender Bestandteil jedes Patch-Commits** und muss
  bei jedem Arbeitsschritt gepflegt werden.
- Pflege-Regeln für `DEV_PROMPT_HISTORY.md`:
  - Neue Schritte werden chronologisch im Verlauf ergänzt (Datum, Patch-ID,
    kurze Beschreibung, betroffene Dateien/Bereiche).
  - Erledigte Punkte aus „Offene Punkte / Nächste sinnvolle Schritte“ werden
    dort als erledigt erkennbar dokumentiert.
  - Der **KI-Snapshot am Dateianfang** wird bei jedem Patch aktualisiert:

    ```markdown
    ### Aktueller Status (YYYY-MM-DD)

    - **Zuletzt erledigt:** <kurze Beschreibung des letzten abgeschlossenen Schritts>
    - **Nächster geplanter Schritt:** <konkreter, nächster Arbeitsschritt>
    ```

    - „Zuletzt erledigt“ beschreibt, was im gerade erstellten Commit umgesetzt
      wurde.
    - „Nächster geplanter Schritt“ beschreibt, was fachlich/technisch als
      nächstes folgen soll.
- `docs/STATUS_SNAPSHOT.md` wird parallel kurz gehalten – er ist die
  Schnellorientierung, die History ist der vollständige Verlauf.
- Der Master-Prompt selbst (`docs/master_prompt_zeiterfassung_v13.md`) wird
  angepasst, wenn sich Meta-Regeln (wie diese) verändern; die abgelöste Version
  wandert nach `docs/archiv/` und wird in `docs/archiv/ALTE_PROMPTS.md`
  begründet.

---

# v4 Ergänzungen (Legacy / bereits umgesetzt, Stand 2026-01-01)

Diese Ergänzungen präzisieren/erweitern v3. Falls v3 an einzelnen Stellen unklar ist, gelten diese v4-Punkte.

## A) Rechteverwaltung (Rolle → Rechte)
- Rollen allein reichen nicht: jede Rolle bekommt **explizite Rechte**.
- DB-Design:
  - Tabelle `recht` (`id`, `code` UNIQUE, `beschreibung`, `aktiv`, timestamps)
  - Tabelle `rolle_hat_recht` (`rolle_id`, `recht_id`, UNIQUE(rolle_id, recht_id))
- Backend-UI: „Rollen & Rechte“
  - Rolle auswählen
  - Checkbox-Liste aller Rechte
  - Speichern (idempotent)
- Enforcing: geschützte Controller-Aktionen prüfen künftig **Rechte** (nicht nur Rollenname-Listen).

### Minimale Rechte-Codes (Start)
- `URLAUB_GENEHMIGEN`
- `URLAUB_GENEHMIGEN_SELF`
- `REPORT_MONAT_VIEW`
- `REPORT_MONAT_VIEW_ALL`
- `REPORT_MONAT_EXPORT_ALL`
- `ZEIT_EDIT_SELF`
- `ZEIT_EDIT_ALL`
- `ROLLEN_RECHTE_VERWALTEN`

## B) Urlaub: Self-Approval (Chef)
- Standard: eigene Urlaubsanträge **nicht** selbst genehmigen.
- Ausnahme: erlaubt, wenn der Benutzer `URLAUB_GENEHMIGEN_SELF` hat (typisch Rolle `Chef` / `Personalbüro`).
- Genehmigungslisten dürfen dann auch eigene offene Anträge enthalten.

## C) Monats-PDF als Chef (alle Mitarbeiter)
- Neben dem „Eigenen Monatsreport“ braucht es im Backend:
  - Einzel-PDF für beliebigen Mitarbeiter (mit Recht `REPORT_MONAT_VIEW_ALL`)
  - Sammel-Export für Monat/Jahr als ZIP (pro Mitarbeiter 1 PDF) (Recht `REPORT_MONAT_EXPORT_ALL`)

## D) Zeiten bearbeiten mit Audit-Trail (Pflicht)
- Rohdaten bleiben grundsätzlich erhalten.
- Manuelle Korrekturen müssen **auditierbar** sein:
  - Wer hat geändert (Mitarbeiter-ID)
  - Wann
  - Welche Felder (alt → neu)
  - Begründung (Pflichtfeld)
- UI/PDF müssen Korrekturen markieren (z. B. Sternchen/Label „korrigiert“) und die Änderungsinfo abrufbar machen.
- Empfohlenes DB-Design (Beispiel):
  - `zeit_korrektur_log`:
    - `id`, `mitarbeiter_id` (betroffen), `datum`, `feld`, `wert_alt`, `wert_neu`,
      `geaendert_von_mitarbeiter_id`, `begruendung`, `geaendert_am`
  - alternativ feldbasiert pro `zeitbuchung_id`.













# v7 Ergänzungen (verbindlich, Stand 2026-01-05)

Diese v7-Ergänzungen präzisieren das System so, dass es **sauber skaliert** und der Chef im Backend alles sinnvoll einstellen kann.

## A) Zielbild
- Rechte müssen **nicht nur global** sein, sondern auch einem **Bereich (Scope)** zugewiesen werden können (typisch: Abteilung inkl. Unterabteilungen).
- Der Chef kann im Backend **Rollen, Rechte, Bereiche und Zuweisungen** verwalten und nachvollziehen.
- Implementierung bleibt bewusst **frameworkfrei** und nachvollziehbar (PDO, einfache Services).

## B) Begriffe
- **Rolle:** Bündel von Rechten (z. B. „Schichtleiter“, „Personalbüro“).
- **Recht:** konkrete Fähigkeit (z. B. „Urlaub genehmigen“, „Zeiten bearbeiten“).
- **Scope/Bereich:** *wofür* ein Recht gilt (z. B. global, Abteilung X, Abteilung X inkl. Unterabteilungen).

## C) Chef darf immer alles (Superuser-Pflicht)
- DB-Erweiterung (Pflicht): `rolle.ist_superuser` (TINYINT(1), Default 0).
- Regel: Hat ein Benutzer mindestens **eine** Rolle mit `ist_superuser=1`, dann gilt **jeder** Rechte-Check als erlaubt.
- Trotzdem (Pflicht): Alle sicherheitsrelevanten Aktionen (Genehmigen, Editieren, Admin-Änderungen) werden in `system_log` protokolliert.

## D) Bereichsmodell (Scope) – Abteilung als skalierbarer Baum
- Der kanonische „Bereich“ ist die vorhandene Tabelle `abteilung` (mit `parent_id` als Hierarchie).
- Mitarbeiter sind bereits M:N zu Abteilungen zugeordnet über `mitarbeiter_hat_abteilung` (inkl. `ist_stammabteilung`).
- **Scope-Check** gegen Abteilungen:
  - `scope=global` passt immer.
  - `scope=abteilung` passt, wenn die Ziel-Abteilung **gleich** ist.
  - Optional (Standard für Team-/Bereichsrechte): „gilt auch für Unterabteilungen“ → Ziel-Abteilung liegt im Unterbaum.
- Performance-Hinweis (optional, wenn nötig): Für sehr große Bäume kann später eine Materialized-Path-Spalte oder eine Closure-Tabelle ergänzt werden. Für MVP reicht rekursives Traversieren.

## E) Rollen-Zuweisung muss scoped sein (damit es skaliert)
> Ohne scoped Rollen müsstest du pro Abteilung eigene Rollen anlegen („Schichtleiter CNC“, „Schichtleiter Montage“, ...). Das skaliert nicht.

- `mitarbeiter_hat_rolle` bleibt als Legacy (global).
- Neue Tabelle (Pflicht, sobald scoped Rechte gebraucht werden): `mitarbeiter_hat_rolle_scope`.
  - Felder (empfohlen):
    - `id` (PK, AUTO_INCREMENT)
    - `mitarbeiter_id` (FK)
    - `rolle_id` (FK)
    - `scope_typ` ENUM('global','abteilung') NOT NULL DEFAULT 'global'
    - `scope_id` INT UNSIGNED NOT NULL DEFAULT 0  (bei global = 0, sonst `abteilung.id`)
    - `gilt_unterbereiche` TINYINT(1) NOT NULL DEFAULT 1
    - `erstellt_am`
  - Regel: Ein Mitarbeiter kann dieselbe Rolle mehrfach haben, aber in unterschiedlichen Scopes.
- Übergangsregel: Ein Eintrag in `mitarbeiter_hat_rolle` wird intern wie `scope_typ='global'` behandelt.

## F) Rechte-Overrides pro Mitarbeiter (optional, aber für Skalierung sehr hilfreich)
- Neue Tabelle (optional): `mitarbeiter_hat_recht_scope` für gezielte Ausnahmen.
  - Felder (empfohlen): `id`, `mitarbeiter_id`, `recht_id`, `scope_typ`, `scope_id`, `gilt_unterbereiche`, `effect` ENUM('allow','deny'), `begruendung`, `erstellt_am`.
- Priorität: `deny` sticht `allow` (bei gleicher oder breiterer Gültigkeit). Overrides sticht Rollen.

## G) Zentrale Rechteprüfung (Pflicht)
- **Nie** in Controllern „Rollenname == ...“ prüfen.
- Es gibt genau eine zentrale Stelle, z. B. `AuthService`:
  - `istSuperuser(): bool`
  - `hatRecht(string $code, ?int $zielMitarbeiterId = null, ?int $zielAbteilungId = null): bool`
- Standard-Ablauf von `hatRecht()`:
  1. Wenn `istSuperuser()` → **true**.
  2. Ziel-Scope bestimmen:
     - wenn `zielAbteilungId` gesetzt → das verwenden.
     - sonst wenn `zielMitarbeiterId` gesetzt → Stammabteilung des Ziel-Mitarbeiters verwenden (Fallback: erste aktive Abteilung).
     - sonst → `global`.
  3. Grants sammeln:
     - Rollen des Users (Legacy + `mitarbeiter_hat_rolle_scope`) inkl. Scope.
     - Rechte je Rolle aus `rolle_hat_recht`.
     - optional Overrides aus `mitarbeiter_hat_recht_scope`.
  4. Matching:
     - `scope_typ='global'` passt immer.
     - `scope_typ='abteilung'` passt, wenn Ziel-Abteilung gleich ist oder (wenn `gilt_unterbereiche=1`) im Unterbaum liegt.
     - Effekt-Regel: `deny` gewinnt, sonst `allow`.
     - Bei mehreren Treffern gilt: der **spezifischste** Treffer (nächster Scope) gewinnt; bei Gleichstand gewinnt `deny`.
  5. Caching:
     - Effektive Grants werden pro Session gecached (z. B. als Array),
     - Cache wird invalidiert, wenn Rollen/Rechte/Scopes administrativ geändert werden.

## H) Admin-UI (Chef kann alles einstellen)
- Menüpunkt „Rollen & Rechte“:
  - Rollen verwalten (inkl. Flag `ist_superuser`).
  - Rechte verwalten (Liste + Beschreibung; Codes stabil halten).
  - Rechte einer Rolle zuweisen (Checkboxen).
- Menüpunkt „Mitarbeiter → Rollen (Bereiche)“:
  - Scoped Rollen zuweisen: Rolle + Bereich (global/Abteilung) + „Unterabteilungen einschließen“.
  - Optional: Overrides (allow/deny) mit Begründung.
- Pflicht: Seite „Effektive Rechte“
  - Auswahl Mitarbeiter → zeigt alle aktiven Rechte + aus welchem Grant (Rolle/Override) + Scope.

## I) Konkrete Regeln/Beispiele (damit es eindeutig wird)
- Urlaub genehmigen:
  - `URLAUB_GENEHMIGEN` gilt scoped (Abteilung des Antragstellers).
  - `URLAUB_GENEHMIGEN_ALLE` ist Legacy-Shortcut für global (kann intern als globaler Grant interpretiert werden).
  - Eigener Antrag: zusätzlich `URLAUB_GENEHMIGEN_SELF`.
- Zeiten bearbeiten:
  - Scoped: `ZEIT_EDIT_ALLE` ist Legacy-Shortcut für global.
  - Bevorzugt ist künftig ein Rechtecode ohne „ALLE“-Suffix (z. B. `ZEIT_EDIT`), der dann über Scope gesteuert wird.
  - Audit/Markierung bleibt Pflicht (v4 Abschnitt D).
- Reports:
  - `REPORT_MONAT_ALLE` ist Legacy-Shortcut für global.
  - Bevorzugt: `REPORT_MONAT_VIEW` / `REPORT_MONAT_EXPORT` scoped.

## J) Kompatibilität & Migrationsregel
- Bestehende Tabellen/Funktionen dürfen nicht „hart“ brechen.
- Legacy-Rechte mit Suffix „_ALLE“ bleiben bestehen und können bis zur vollständigen Umstellung parallel unterstützt werden.
- Neue Features werden **immer** über `hatRecht()` abgesichert und nutzen Scope, statt neue Rollen zu erfinden.
