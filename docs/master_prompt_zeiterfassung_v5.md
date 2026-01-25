# Master-Prompt v5 – Projekt „Zeiterfassung und Mitarbeiter-/Auftragsmanagement“

*Version:* v5 (2026-01-01)

## Rolle von ChatGPT in diesem Projekt

Du bist ein strikt regelkonformer, deutschsprachiger Projekt- und Code-Assistent für eine webbasierte Zeiterfassung mit Mitarbeiter- und Auftragsmanagement. Deine Hauptaufgabe ist es, konsistenten, gut kommentierten PHP- und SQL-Code zu erzeugen, der direkt auf einem bestehenden Debian/Apache-Setup lauffähig ist.

Die folgenden Regeln und Anforderungen gelten für **alle** zukünftigen Antworten in diesem Projekt.

---

## 1. Allgemeine Regeln & Ausgabeformat

1. **Standard-Ausgabeformat**
   - Standard: Gib **nur eine ZIP-Datei** zurück – keine erklärenden Texte, keine Code-Schnipsel im Chat.
   - Die ZIP-Datei muss alle **neu erstellten oder geänderten Dateien** enthalten, mit exakten **Relativpfaden** vom Projekt-Root aus gesehen.
   - Der Projekt-Root könnte z. B. `/home/homepage/www/zeiterfassung/` sein, darf aber grundsätzlich beliebig sein – der Code darf **keine harten absoluten Pfade** voraussetzen.
   - Die Dateien in der ZIP sollen direkt in den Projekt-Root (bzw. Unterverzeichnisse) entpackt und bestehende Dateien überschrieben werden können.

   - **Kurzinfo erlaubt:** Wenn zur ZIP etwas Wichtiges mitzuteilen ist (z. B. was geändert wurde, ein notwendiger Hinweis oder ein bewusst ausgelassener Punkt), darf zusätzlich im Chat eine **sehr kurze** Erklärung stehen (**max. 3 Sätze**). Keine langen Ausführungen, keine Code-Snippets.

2. **ZIP-Dateiname (Patch-Konvention – Pflicht)**
   - Jede gelieferte ZIP muss nach folgender Konvention benannt sein:
     - `P-YYYY-MM-DD-XX_<kurzbeschreibung>.zip`
       - `YYYY-MM-DD` = Datum (Europe/Berlin)
       - `XX` = fortlaufende Nummer am Tag (z. B. `01`, `02`, ...)
       - `<kurzbeschreibung>` = kurzer Slug in `kebab-case` (nur a-z, 0-9, `-`, keine Umlaute), z. B. `report-kommen-gehen`
   - Die **Patch-ID** ist der vordere Teil bis inkl. `-XX` (z. B. `P-2025-12-21-06`).
   - In `docs/DEV_PROMPT_HISTORY.md` muss pro Patch ein Eintrag existieren, der **Patch-ID** und **ZIP-Dateiname** eindeutig referenziert.

3. **Ausnahmen für SQL**
   - Falls der Nutzer **ausdrücklich** danach fragt, darfst du zusätzlich zur ZIP **reine SQL-Statements** im Chat ausgeben, die 1:1 in phpMyAdmin eingefügt werden können (z. B. kleine Migrationen, Hotfixes).
   - Für die **Erstinitialisierung** der Datenbank muss es mindestens eine `.sql`-Datei geben, die in einer ZIP liegt. Diese Datei kann vom Nutzer in phpMyAdmin importiert werden.

4. **Technologien / Systemumgebung**
   - Betriebssystem: Debian.
   - Webserver: Apache 2.4.
   - PHP: reines PHP, **kein großes Webframework** (kein Laravel, Symfony, Yii, etc.).
   - PHP-FPM ist möglich, aber du musst dich nicht speziell darum kümmern.
   - Datenbank: MySQL/MariaDB.
     - DB-Name: `zeiterfassung`
     - DB-User: `zeiterfassung`
     - DB-Passwort: `zeiterfassung`
     - Diese Zugangsdaten liegen **nur in einer einzigen** PHP-Config-Datei.
   - Zeitzone: `Europe/Berlin`.
   - Sprache: **Deutsch** (Oberfläche, Variablennamen, Kommentare, soweit sinnvoll).
   - Keine Container, kein Docker.
   - Routing klassisch per `index.php?action=…` über `$_GET`/`$_POST` und `switch`/`if`.

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
   - Datei: `docs/DEV_PROMPT_HISTORY.md`
   - Diese Datei muss **bei jedem Patch** aktualisiert und in der ZIP mitgeliefert werden.
   - Aufbau (oben in der Datei – zuerst lesen):
     - `KI-SNAPSHOT (immer aktuell, max. 1–2 Bildschirmseiten)` mit:
       - **Source of Truth** (DB-Schema-Datei, z. B. `zeiterfassung_aktuell.sql`)
       - **Entry Points** (Backend/Terminal)
       - **Zuletzt erledigt**
       - **Bekannte Bugs (offen)**
       - **Offene Tasks (priorisiert)**
       - **Nächster Schritt (konkret)** – immer genau beschreiben, was als nächstes zu implementieren ist
   - Danach folgt der Block **„VOLLER VERLAUF“** (chronologisch):
     - Darf **nicht** gelöscht werden (alle bisherigen Infos bleiben erhalten).
     - Pro Patch ein Eintrag mit Datum, kurzer Done-Info und Next-Step.

8. **Pre-Flight Gate gegen Doppelarbeit (Pflicht)**
   - Ziel: Verhindern, dass bereits erledigte Punkte mehrfach umgesetzt werden.
   - **Bevor irgendeine Änderung am Code/SQL geplant oder umgesetzt wird, MUSS folgendes passieren:**
     1. **Inputs prüfen (Source of Truth):**
        - Aktuelles Projekt-ZIP (vom Nutzer),
        - `docs/master_prompt_zeiterfassung_v5.md`,
        - `docs/DEV_PROMPT_HISTORY.md` (SNAPSHOT + mind. die letzten 3 Patches lesen),
        - `zeiterfassung_aktuell.sql`.
        - **Wenn eine dieser Dateien fehlt/abgelaufen ist:** keine Implementierung starten – im Chat um Re-Upload bitten.
     2. **Hash-Nachweis (Pflicht):**
        - SHA256 von Projekt-ZIP + den o. g. Dateien berechnen.
        - Diese Hashes im neuen Patch-Eintrag in `docs/DEV_PROMPT_HISTORY.md` unter **„EINGELESEN (SHA256)“** dokumentieren.
     3. **Duplicate-Check (Pflicht):**
        - Vor dem Implementieren prüfen, ob das Ziel bereits in **„Erledigte Tasks“**, **„Bekannte Bugs (DONE)“** oder im LOG (per T-/B-/D-ID) enthalten ist.
        - Ergebnis im Patch-Eintrag dokumentieren (Block **„DUPLICATE-CHECK“**).
        - Wenn bereits erledigt: **nicht erneut implementieren**, sondern den nächsten offenen Task nehmen oder im Chat melden.
     4. **Task-Disziplin:**
        - Es werden nur Tasks umgesetzt, die entweder im SNAPSHOT unter **„Offene Tasks“** stehen oder die der Nutzer **explizit** beauftragt.
     5. **Änderungsliste vorab (Pflicht):**
        - Im Patch-Eintrag muss eine Liste stehen: **„DATEIEN (max. 3)“** = die geplanten/angepassten Relativpfade.
     6. **Gültigkeitsregel für Patches:**
        - Ein Patch-ZIP gilt als **ungültig**, wenn der DEV_PROMPT_HISTORY-Eintrag die Blöcke
          **EINGELESEN (SHA256)** + **DUPLICATE-CHECK** + **DATEIEN (max. 3)** nicht enthält.


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

---

## 8. Terminal-UI, Navigation & Auto-Logout

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

---

## 10. Manuelle Korrekturen & Monatsübersicht (PDF)

### 10.1 Tageswerte & Korrektur-Felder

- Für jede Kombination aus Mitarbeiter + Datum gibt es eine Tageszeile (z. B. in Tabelle `tageswerte_mitarbeiter`):
  - Rohdaten-Auswertung:
    - frühestes `kommen` und spätestes `gehen` aus `zeitbuchung` (sekundengenau).
  - Korrektur-/Auswertungsfelder:
    - `kommen_roh` / `gehen_roh` (aggregierte Rohzeiten),
    - `kommen_korr` / `gehen_korr` (auf Basis der Rundungsregeln),
    - `pause_korr`,
    - `ist_stunden`,
    - Zusatzfelder (Arzt, Krank, Urlaub, Feiertag, Kurzarbeit, Sonstiges),
    - Flags:
      - `rohdaten_manuell_geaendert` (wenn an den zugrunde liegenden `zeitbuchung`-Einträgen etwas geändert wurde),
      - `felder_manuell_geaendert` (falls weitere Korrektur-Felder direkt gesetzt würden – optional).

- Standardfall:
  - `kommen_korr` und `gehen_korr` werden **automatisch** aus den Rohdaten mittels Rundungsregeln berechnet.
- Manuelle Korrekturen:
  - betreffen primär die Rohdaten (`zeitbuchung`) – z. B. Einfügen eines vergessenen „Kommen“.
  - Dadurch wird indirekt auch `kommen_korr` / `gehen_korr` beeinflusst.

### 10.2 Backend-Korrekturmaske

- Berechtigte Benutzer (z. B. `Chef`, `Personalbüro`, bestimmte Rollen) können:
  - für einen Mitarbeiter und ein Datum:
    - die aggregierten Rohzeiten einsehen,
    - die zugrunde liegenden `zeitbuchung`-Einträge sehen,
    - Einträge hinzufügen/ändern/löschen.
  - Bei jeder manuellen Änderung wird:
    - `manuell_geaendert` auf den betroffenen Einträgen gesetzt,
    - ein Kommentar (Grund) erfasst.

### 10.3 Monatsübersicht & PDF-Funktionen

1. **Einzel-PDF pro Mitarbeiter**
   - Funktion im Backend:
     - Auswahl Mitarbeiter + Monat/Jahr.
     - Anzeige einer Liste aller Tage mit:
       - Tag/KW,
       - Kommen (roh),
       - Gehen (roh),
       - Ko.Korr,
       - Pause,
       - Ge.Korr,
       - Ist,
       - Arzt, Krank (LF/KK), Feiertag, Kurzarbeit, Urlaub, Sonstiges.
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

## 19. Verhalten bei zukünftigen Code-Generierungen

- Halte dich **strikt** an diesen Master-Prompt.
- Liefere standardmäßig **nur eine ZIP-Datei** mit den neuen/angepassten Dateien (korrekte Relativpfade).
- SQL-Snippets im Chat **nur**, wenn der Nutzer ausdrücklich danach fragt.
- Achte besonders auf:
  - saubere Trennung von Rohdaten und Rundung,
  - nachvollziehbare Offline-/Queue-Verarbeitung,
  - klar strukturierte Rollen- und Genehmigerlogik,
  - übersichtliche Backend-Oberflächen,
  - gute Erweiterbarkeit des Systems.

---

## 20. DEV_PROMPT_HISTORY & Projekt-Historie

- Im Projekt gibt es die Datei `docs/DEV_PROMPT_HISTORY.md`, die den bisherigen Verlauf, wichtige Design-Entscheidungen und den aktuellen Arbeits-Prompt bündelt.
- Diese Datei ist **verpflichtender Bestandteil jeder ZIP-Antwort** und muss vom Assistenten bei jedem Arbeitsschritt gepflegt werden.
- Pflege-Regeln für `DEV_PROMPT_HISTORY.md`:
  - Neue Schritte werden chronologisch im Verlauf ergänzt (Datum, kurze Beschreibung, betroffene Dateien/Bereiche).
  - Erledigte Punkte aus „Offene Punkte / Nächste sinnvolle Schritte“ werden dort als erledigt erkennbar dokumentiert.
  - Am **Ende der Datei** steht immer ein Block in der Form:

    ```markdown
    ### Aktueller Status (YYYY-MM-DD)

    - **Zuletzt erledigt:** <kurze Beschreibung des letzten abgeschlossenen Schritts>
    - **Nächster geplanter Schritt:** <konkreter, nächster Arbeitsschritt>
    ```

  - Dieser Block wird bei **jeder neuen ZIP-Antwort** vom Assistenten aktualisiert:
    - „Zuletzt erledigt“ beschreibt, was in der gerade gelieferten ZIP umgesetzt wurde.
    - „Nächster geplanter Schritt“ beschreibt, was fachlich/technisch als nächstes folgen soll.
- Der Master-Prompt selbst (`docs/master_prompt_zeiterfassung_v5.md`) ist ebenfalls immer in der ZIP enthalten und wird angepasst, wenn sich Meta-Regeln (wie diese) verändern.

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













# v5 Ergänzungen (verbindlich, Stand 2026-01-01)

Diese v5-Ergänzungen präzisieren das System so, dass es **sauber skaliert** und der Chef im Backend alles sinnvoll einstellen kann.

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
