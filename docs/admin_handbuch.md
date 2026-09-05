# Admin-Handbuch

## Zweck und Zielgruppe
Dieses Handbuch richtet sich an Administrator:innen und beschreibt die
Konfigurationsorte sowie typische Einstellungen im Projekt.

## Konfigurationsorte und Zuständigkeiten

### `config/`
- **`config/config.php`**: Zentrale Konfigurationsdatei. Enthält die Struktur
  der Einstellungen und Default-Werte.
- **`config/config.php.example`**: Vorlage für eigene Konfigurationen.
- **`config/config.local.php`** (nicht versioniert): Lokale/produktive Werte,
  z. B. Zugangsdaten. Wird bevorzugt geladen.

### `services/`
- **Geschäftslogik** (z. B. Zeit- und Urlaubsregeln). Hier wird festgelegt, wie
  Buchungen, Rundungen oder Berechnungen durchgeführt werden.
- Typische Anlaufpunkte: `ZeitService`, `UrlaubService`, `RundungsService`.

### `controller/`
- **Request-Verarbeitung**: Nimmt Parameter an, orchestriert Services und
  übergibt Daten an Views. Geeignet für Anpassungen an Abläufen und
  Admin-Oberflächen.

### `modelle/`
- **Datenbank-Modelle**: Reine DB-Zugriffe und Datenobjekte für Mitarbeiter,
  Zeitbuchungen, Urlaubsanträge usw.

### `sql/`
- **Datenbankschema** und optionale SQL-Hilfen, z. B. `sql/01_initial_schema.sql`
  als Referenz der DB-Struktur.

## Schritt-für-Schritt: Typische Einstellungen

### 1) Benutzer anlegen und pflegen
1. Öffne die Admin-Oberfläche (Web).
2. Lege einen neuen Mitarbeiter an (Name, Personalnummer, Status).
3. Weisen die Benutzerrolle(n) zu (siehe Abschnitt Rollen/Rechte).
4. Speichere und prüfe, ob der Benutzer in der Übersicht erscheint.

**Hinweis:** Die zugrundeliegenden DB-Tabellen und Felder findest du im
Schema unter `sql/01_initial_schema.sql`.

### 2) Rollen und Rechte
1. Öffne die Rollen-/Rechteverwaltung in der Admin-Oberfläche.
2. Erstelle eine neue Rolle (z. B. „Teamleitung“).
3. Aktiviere die benötigten Rechte (z. B. Freigaben, Auswertungen, Admin).
4. Weisen die Rolle einzelnen Benutzer:innen zu.

**Technischer Bezug:** Rollenlogik wird typischerweise in `services/` umgesetzt
und von `controller/` verarbeitet.

### 3) Zeitregeln und Rundungen
1. Prüfe vorhandene Rundungs- oder Zeitregeln in den Services.
2. Passe die Regeln in den jeweiligen Services an (z. B. Rundungsintervalle).
3. Teste die Änderung mit Beispielbuchungen (z. B. Start/Ende).

**Tipp:** Halte die Regeln konsistent und dokumentiere Änderungen im
Status-Snapshot.

### 4) Datenbank/SQL (z. B. Initialisierung)
1. Richte die Datenbank ein und importiere das Schema aus `sql/01_initial_schema.sql`.
2. Pflege Zugangsdaten in `config/config.local.php` oder per Umgebungsvariablen.
3. Prüfe die Verbindung über die Admin-Oberfläche.

### 5) RFID-Reader (Terminal)
1. Wähle die passende Reader-Variante (Tastatur-Scanner oder WebSocket-Bridge).
2. Konfiguriere die Bridge-Einstellungen in `config/config.php` bzw. `config/config.local.php`.
3. Teste den Scan am Terminal und prüfe die Logs bei Problemen.

### 6) Ein Terminal ausmustern (Menü *Verwaltung → Terminals*)
Jedes gekoppelte Gerät hat einen **eigenen Datenbankbenutzer**. Wird ein
Terminal ersetzt, verschrottet oder vermisst, in der Spalte *Kopplung* auf
**Entkoppeln** klicken – das löscht diesen Benutzer.

**Nur „Aktiv“ auf Nein zu stellen genügt nicht.** Das verhindert lediglich eine
neue Kopplung; die Zugangsdaten auf dem Gerät funktionieren weiter. Zum
vorübergehenden Stilllegen (Umbau, Störung) ist „Aktiv = Nein“ richtig, zum
Ausmustern *Entkoppeln*.

Ein entkoppeltes Gerät braucht zum Wiederinbetriebnehmen einen neuen
Kopplungscode. Einzelheiten:
[Terminal-Installation](spezifikation_terminal_installation.md), Abschnitt 2a.

## Aufträge, Arbeitsschritte und Laufkarte

Dieser Ablauf ist optional. Wer Aufträge wie bisher nur am Terminal scannt,
muss hier nichts tun – das Terminal legt fehlende Datensätze weiterhin selbst
an.

**1. Arbeitsschritt-Katalog einmalig anlegen** (Menü *Aufträge →
Arbeitsschritt-Katalog*)

Hier stehen die wiederkehrenden Tätigkeiten: `saegen`, `drehen`, `fraesen`,
`entgraten` … Einmal gepflegt, für jeden Auftrag nutzbar.

Über *x drucken* lässt sich ein Schritt in beliebiger Stückzahl als
Kartenblatt ausgeben. Bei mehreren Fräsmaschinen also z. B. 20 Karten
`fraesen` – ausschneiden und an jede Maschine hängen.

**2. Auftrag anlegen** (Menü *Aufträge → Auftrag anlegen*)

Pflicht ist nur die Auftragsnummer. Kunde, Kurzbeschreibung und Status sind
freiwillig; was leer bleibt, erscheint auch nicht auf dem Ausdruck.

Unter den Feldern stehen die Katalogschritte zum **Anhaken**. Was hier
angehakt ist, hängt sofort nach dem Speichern am Auftrag – der übliche Fall
ist damit in einem Schritt erledigt.

**3. Arbeitsschritte ergänzen** (in der Auftrags-Detailansicht)

Für alles, was beim Anlegen noch nicht feststand: frei eintippen (für
werkstückspezifische Schritte wie „Außendurchmesser auf 40 mm drehen“) oder
nachträglich aus dem Katalog übernehmen. Angeboten werden dort nur die
Schritte, die dem Auftrag noch fehlen.

**4. Laufkarte drucken** (Detailansicht, *Laufkarte als PDF drucken*)

Enthält den Auftrags-Strichcode und je Arbeitsschritt einen Strichcode plus
Felder für Datum, Name, Menge und i. O. Die Karte begleitet das Werkstück.

**5. Am Terminal**

Erst den Auftrag scannen (von der Laufkarte), dann den Arbeitsschritt –
entweder von der Laufkarte oder von der Karte an der Maschine.

**Wichtig beim Ändern:** Wird ein Code umbenannt, entsteht ein neuer
Strichcode. Bereits gedruckte Laufkarten und Maschinenkarten werden dadurch
ungültig und müssen neu gedruckt werden.

## Mitarbeiterportal: Urlaub von zu Hause

Optional. Wer es nicht einrichtet, merkt vom ganzen Bereich nichts – in der
Zeiterfassung ändert sich ohne Kopplung keine Zeile.

**Was es tut:** Mitarbeiter melden sich auf der WERNIG-Homepage unter
`/mitarbeiter` an, sehen ihren Resturlaub, ihr Stundenkonto und ihre
Monatsübersicht und beantragen Urlaub. **Genehmigt wird hier**, im Backend oder
am Terminal – auf der Website kann das niemand.

**Die Richtung, die man kennen muss:** Diese Installation ruft dort an, nicht
umgekehrt. Die Homepage kennt die Adresse dieses Servers nicht und kann hier
nichts abfragen. Deshalb braucht es keine Portfreigabe und keine feste Adresse
– nur einen ausgehenden Weg ins Internet und einen Eintrag im Zeitplan
([Installationsanleitung](installationsanleitung.md), Abschnitt 8).

### 1. Einmalig koppeln

1. **Im Backend der Homepage** unter *Mitarbeiterportal* einen Kopplungscode
   erzeugen. Er gilt 30 Minuten und lässt sich genau einmal einlösen.
2. **Hier** unter *Verwaltung → Mitarbeiterportal* die Adresse der Homepage
   (`https://wernig.com`) und den Code eintragen, *Koppeln*.

Steht danach »Gekoppelt, und der erste unterschriebene Aufruf kam an«, ist der
Handschlag fertig. Der Knopf *Verbindung prüfen* wiederholt ihn jederzeit.

### 2. Mitarbeiter freischalten

In derselben Maske, Liste *Freischaltung*. **Das ist die eigentliche
Entscheidung:** Ein freigeschalteter Mitarbeiter steht mit Namen,
Urlaubszahlen und Stundensaldo auf einem Server im Internet, ein nicht
freigeschalteter mit keinem Byte. Deshalb gibt es bewusst kein »alle
freischalten«.

Die **Kennung** – der Anmeldename – wird vorgeschlagen (Personalnummer, sonst
`vorname.nachname`) und ist im Feld änderbar, bevor man freischaltet.

### 3. Aktivierungscode drucken

Knopf *Code erzeugen* in derselben Zeile. Es erscheint ein Zettel mit Adresse,
Kennung, Code und Frist – **einmal**. Er ist nirgends gespeichert, wo man ihn
nachlesen könnte; wer ihn verliert, erzeugt einen neuen, und der alte gilt dann
nicht mehr. Genau das macht ihn zum Nachweis statt zur Notiz.

Der Code gilt **14 Tage** und wird einmal eingelöst. Der Mitarbeiter gibt auf
der Website Kennung und Code ein und vergibt dabei sein eigenes Passwort.

**»Passwort vergessen« gibt es dort nicht per Mail** – im Mitarbeiterbereich
liegt keine E-Mail-Adresse. Wer sein Passwort vergisst, bekommt hier einen
neuen Aktivierungscode; damit richtet er den Zugang neu ein, das alte Passwort
gilt nicht mehr.

### 4. Zugang wieder nehmen

Knopf *Entziehen*. Beim nächsten Abgleich verschwindet der Mitarbeiter von der
Website – Konto, Zahlen, Anträge und wartende Einträge. Ein offener
Aktivierungscode wird dabei mit entwertet, sonst bliebe ein gedruckter Zettel
gültig.

### Was schiefgehen kann, und woran man es sieht

| In der Maske steht … | Dann |
| --- | --- |
| »Letzter Abgleich: noch keiner« | Der Zeitplan ist nicht eingerichtet. Ohne ihn kommt auf der Website kein einziger Mitarbeiter an. |
| »Der letzte Lauf ist gescheitert« mit Uhrzeit-Hinweis | Die Uhren beider Server laufen mehr als 300 Sekunden auseinander. Zeitdienst prüfen. |
| »Diese Datenbank stammt aus einer anderen Installation« | Diese Datenbank ist eine **Kopie** – etwa ein Server-Dump auf einem Testrechner. Es wird nichts abgeglichen, und das ist richtig so. Auf einer Kopie: *Verbindung trennen*. |
| »Freigeschaltet: niemand« | Die Kopplung steht, aber es ist noch niemand freigeschaltet. Auf der Website steht dann nichts. |

Der Knopf *Jetzt abgleichen* stößt einen Lauf von Hand an und zeigt im
Gegensatz zum Zeitplan seine Zeilen – der schnellste Weg zur Ursache.

**Wenn der Firmenserver aus ist**, passiert nichts Schlimmes: Anträge sammeln
sich auf der Website, die Mitarbeiter sehen dort einen sichtbaren Hinweis, wie
alt ihre Zahlen sind, und der nächste Lauf holt alles nach.

Einzelheiten und die vollständige Abnahmekette:
[Mitarbeiterportal](spezifikation_mitarbeiterportal.md).

## Verlinkte Referenzen
- Verzeichnis aller Dokumente: [`docs/README.md`](README.md)
- Status/Letzte Änderungen: [`docs/STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md)
- Wartungs- und Prüfcheckliste: [`docs/wartungscheckliste.md`](wartungscheckliste.md)
- RFID-Reader-Setup: [`docs/rfid_reader_setup.md`](rfid_reader_setup.md)
- Mitarbeiterportal (Vertrag mit der Homepage):
  [`docs/spezifikation_mitarbeiterportal.md`](spezifikation_mitarbeiterportal.md)
