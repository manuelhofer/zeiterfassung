# Bedienbarkeitsprüfung – 30.09.2026

Dokumentation abgeschlossen am 01.10.2026.

Geprüfter Programmstand: `83290ab` auf `codex/wartung-updates`.
Auftrag: das gesamte Projekt auf Bedienbarkeit und Einfachheit durchsehen.

## Ergebnis

**Die täglichen Grundabläufe sind brauchbar und teilweise sehr einfach; die
Verwaltung ist noch nicht durchgehend für Menschen ohne technische Kenntnisse
geeignet.** Am meisten bringen eine verständliche Verwaltungsstartseite,
einfachere Urlaubs- und Zeitansichten sowie verlässliche Handyansichten.

Backup und Updates sind bereits vergleichsweise gut gelöst: klare Aktionen,
sichtbarer Ablauf, technische Details eingeklappt, keine zusätzliche
Wartungseinrichtung nach der normalen Installation und Terminalkopplung.
Das bedeutet jedoch nicht, dass das gesamte Projekt ohne Einrichtung oder
technische Betreuung auskommt: Erstinstallation, Portal-Zeitplan und
Wiederherstellung benötigen weiterhin entsprechende Schritte.

Diese Prüfung dokumentiert Befunde und Vorschläge. Sie ändert keine
Programmabläufe, Berechnungen, Rechte oder Datenbankschemata.

## Prüfgrundlage und Grenzen

- Private MariaDB und vier getrennte Testinstallationen für Backend, Terminal,
  Erststart und ungekoppeltes Terminal; ausschließlich künstliche Mitarbeiter
  und Buchungen. Der vorhandene Entwicklungsdatenbestand wurde nicht verwendet.
- Browserprüfung der Hauptbereiche als Chef und als normaler Mitarbeiter,
  ergänzt durch Prüfung von Navigation, Formularen, Controllern, Installern
  und Handbüchern. Über 30 unterschiedliche Backendansichten sowie Terminal-
  und Einrichtungsseiten wurden geöffnet.
- Backend im Desktopformat und ausgewählte Ansichten bei 390 Pixeln Breite;
  Terminal im Browser bei 1280 × 720. Kein Test auf einem physischen Handy,
  Touchscreen oder RFID-Leser und keine vollständige Barrierefreiheitsprüfung.
- Neu durchgespielt: RFID-Anmeldung mit Testcode, Kommen, Auftrag starten,
  Auftrag stoppen, Gehen; dazu Urlaubsantrag mit falschem und richtigem
  Zeitraum sowie die drei Schritte des Terminal-Urlaubsformulars.
- Der richtige Urlaubsantrag erschien mit fünf Tagen als offen in der
  Genehmigungsansicht. Der abschließende Genehmigungsklick konnte wegen einer
  Störung der Browsersteuerung am Bestätigungsdialog nicht abgeschlossen
  werden; er wird nicht als bestandener Ende-zu-Ende-Test gewertet.
- PDF-Verknüpfungen und Druckwege wurden angesehen, PDF-Ausgaben nicht erneut
  visuell abgenommen. Das externe Mitarbeiterportal wurde nicht verbunden.
  Offline-Ausfall und Wiederanlauf wurden in dieser Bedienprüfung nicht neu
  simuliert; dafür wurden die bestehenden Regeln und Prüfungen herangezogen.
- Die isolierte Oberfläche hat absichtlich keinen installierten Wartungsdienst.
  Ihre deaktivierten Wartungsknöpfe sind daher kein neuer Produktfehler.
  Die 39 bestandenen Wartungsprüfungen stammen aus dem vorherigen Patch;
  native Systemd-/Apache-/FPM- und Wiederherstellungstests auf Geräten bleiben offen.

## Was bereits gut funktioniert

1. Normale Mitarbeiter sehen weder Verwaltungsmenüs noch die umfangreichen
   Korrekturformulare. Ihre Startseite bietet drei direkte Wege zu Zeiten,
   Urlaub und Monatsübersicht.
2. Das Terminal bietet große Schaltflächen und abhängig von der Anwesenheit
   passende Aktionen. Nach Kommen, Gehen und Auftragsaktionen erscheint eine
   Bestätigung, dann ist es wieder für den nächsten Chip bereit.
3. Die Terminalkopplung verlangt nur Serveradresse und Einmalcode. Eine
   Bildschirmtastatur ist vorhanden; Datenbankzugänge muss der Benutzer
   nicht übertragen. Der erste Backendbenutzer wird über ein kurzes Formular
   angelegt.
4. Ein Urlaubsantrag braucht nur Beginn und Ende; der Kommentar ist optional.
   Bei gültigem Zeitraum zeigt die Vorschau die benötigten Tage und den
   verbleibenden Urlaub. Ein umgekehrter Zeitraum wird beim Speichern
   verständlich abgewiesen und die Eingaben bleiben erhalten.
5. Abteilungen und Maschinen haben kurze Formulare. Neue Mitarbeiter müssen
   nicht gleichzeitig alle Rechte verstehen: Stammdaten und Rechte sind
   bereits getrennt.
6. Aufträge besitzen eine Suche, wiederverwendbare Arbeitsschritte und eine
   Laufkarte mit Codes. Ein gebuchter Auftrag lässt sich nicht versehentlich
   samt Zeiten löschen; die Oberfläche erklärt das und nennt „Inaktiv setzen“.
7. Die Wartungsseite folgt dem gewünschten Muster: „Nach Updates suchen“,
   „Jetzt aktualisieren“, „Backup erstellen“ und ein verständlicher Fortschritt.

## Befunde mit Priorität

„Hoch“ bedeutet: vor breiterer Nutzung vereinfachen, weil der Befund eine
häufige Aufgabe erschwert oder eine falsche Erwartung erzeugt. Es ist keine
Aussage über einen aktuellen Produktionsausfall; das Projekt ist im Praxistest.

### UX-01 · Hoch · Verwaltung beginnt mit technischen Schlüsseln

Ein Klick auf **Verwaltung** öffnet bei einem Chef die Konfigurationstabelle.
Dort stehen beispielsweise `terminal_db_host_extern`, `micro_buchung_max_sekunden`,
Datentypen und Dateipfade. Das ist ein schlechter Einstieg für jemanden, der
einen Mitarbeiter, eine Pause oder ein Terminal verwalten möchte. Auch die
Startseite mischt elf Verwaltungsverweise mit „Smoke-Test“, „Rollback“ und
Queue-Angaben.

**Vorschlag:** Verwaltung als übersichtliche Auswahl nach Aufgaben aufbauen;
technische Schlüssel und Diagnosen hinter „Erweiterte Einstellungen“ legen.
Auf der Startseite offene Aufgaben zuerst zeigen, zum Beispiel zu bearbeitende
Urlaubsanträge. Vorhandene Rechte weiterhin berücksichtigen.

**Abnahme:** Ein neuer Administrator findet Mitarbeiter, Arbeitszeitregeln,
Terminals und Backup direkt, ohne Konfigurationsschlüssel lesen zu müssen.

Beleg: `views/layout/header.php:171`, `views/konfiguration/liste.php:47`,
`views/dashboard/index.php` und tatsächlich geöffnete Verwaltungsstartseite.

### UX-02 · Hoch · Einige Handyansichten schieben die ganze Seite seitlich

Bei 390 Pixeln Fensterbreite war die Monatsübersicht 831 Pixel breit, die
Konfiguration 905 Pixel. Das betrifft die ganze Seite; die Monatstabelle
verschwindet rechts aus dem sichtbaren Bereich. Mitarbeiterformular,
Terminalverwaltung und Jahresübersicht zeigen bereits das bessere Muster:
Formulare passen sich an, breite Tabellen scrollen in ihrem eigenen Bereich.

**Vorschlag:** Fehlende Tabellencontainer ergänzen und die Monatsübersicht
auf schmalen Geräten auf Datum, Kommen, Gehen und Arbeitszeit konzentrieren;
weitere Tageswerte über Details zugänglich machen.

**Abnahme:** Bei 390 Pixeln bleiben Navigation, Filter und Hauptaktionen
vollständig erreichbar, ohne dass die gesamte Seite horizontal scrollt.

Beleg: `views/report/monatsuebersicht.php:1153`,
`views/konfiguration/liste.php:47`; reproduziert im Browser. **B-107**.

### UX-03 · Hoch · Urlaubsübersicht verlangt zu viel Rechenverständnis

Auch normale Mitarbeiter sehen „War (Auto)“, „Korrektur (Manuell)“, „Effektiv“,
„Verbraucht“, „Übrig“ und „BF“. Die eigentliche Frage „Wie viele Tage kann ich
noch beantragen?“ geht zwischen zahlreichen Zahlen unter. Nach einem offenen
Testantrag stieg zudem eine Anzeige „Verbraucht“ bereits an, obwohl der Urlaub
noch nicht genehmigt war. Vorschau und Tabellen nutzen Dezimalpunkte, andere
Angaben Dezimalkommas. Hier geht es um verständliche Darstellung, nicht um
eine in dieser Prüfung nachgewiesene falsche Urlaubsberechnung.

**Vorschlag:** Zuerst „Noch verfügbar“, „Beantragt“ und „Genehmigt“ zeigen.
Anspruch, Übertrag und Korrekturen in ausklappbare Berechnungsdetails legen;
Betriebsferien ausschreiben. Zahlen und Datumsformate vereinheitlichen.

**Abnahme:** Ein Mitarbeiter erkennt verfügbaren und noch unentschiedenen
Urlaub ohne Abkürzungen oder eigene Rechnung.

Beleg: `views/urlaub/meine_antraege.php`, `views/urlaub/kontingent_liste.php`;
Ansicht vor und nach dem Testantrag vom 12.–16.10.2026.

### UX-04 · Hoch · Terminal „Aktiv“ sagt nicht klar, was es bewirkt

Die Terminaltabelle bietet dreimal den Knopf **Umschalten**. „Aktiv = Nein“
klingt nach einem vollständig stillgelegten Gerät. Laut vorhandenem
Zugangsmodell und Handbuch bleiben die bereits vergebenen Datenbankzugänge
jedoch gültig; für deren Widerruf gibt es „Entkoppeln“. Zusätzlich berücksichtigt
der neue Wartungsablauf nur aktive Terminals. Diese Folgen gehören unmittelbar
an die Aktion und dürfen nicht erst im technischen Handbuch auffallen.

**Vorschlag:** Aktionen nach ihrer Wirkung benennen, etwa „Offlinebuchungen
erlauben“ oder „Gerät entkoppeln“. Bei der Aktiv-Einstellung verständlich
anzeigen, dass sie keinen bestehenden Zugang entzieht und das Gerät aus der
gemeinsamen Wartung herausnimmt. Eine echte vorübergehende Gerätesperre wäre
eine eigene fachliche Entscheidung.

**Abnahme:** Vor dem Stilllegen ist sichtbar, ob Buchungen weiterhin möglich
sind und ob das Gerät am nächsten Backup/Update teilnimmt.

Beleg: `views/terminal_admin/liste.php:125`,
`services/WartungDienst.php:229`, `docs/admin_handbuch.md:72` und
`docs/fachregeln/terminal_und_offline.md`, Abschnitt 4; keine neue
Zugriffssperrenprüfung am physischen Gerät.

### UX-05 · Hoch · Auftragsstatus hat zwei unterschiedliche Bedeutungen

Der Testauftrag stand nach dem Stoppen seiner einzigen Buchung in der Liste
auf **abgeschlossen**, in den Auftragsstammdaten aber auf **offen**.
Die Liste berechnet ihren Status aus Zeitbuchungen; das Formular speichert
einen separaten Auftragsstatus. Beide heißen in der Oberfläche „Status“.
Ein gestoppter Arbeitsschritt bedeutet für den Benutzer nicht zwangsläufig,
dass der Fertigungsauftrag abgeschlossen ist.

**Vorschlag:** „Auftragsstatus“ und „Zeiterfassung“ eindeutig trennen und in
der Liste denselben Auftragsstatus zeigen wie im Formular. Wie ein Auftrag
fachlich abgeschlossen wird, vor einer Änderung festlegen.

**Abnahme:** Ein noch offener Auftrag wird durch das bloße Stoppen einer
Zeitbuchung nicht als abgeschlossener Fertigungsauftrag dargestellt.

Beleg: `controller/AuftragController.php:252`,
`views/auftrag/detail.php:197`, Testauftrag A-1001. Kein Nachweis beschädigter Daten.

### UX-06 · Mittel · Zeitkorrekturen und Stundenkonto sind zu technisch

Die administrative Tagesansicht zeigt viele Bearbeitungsmöglichkeiten auf
einmal: neue Buchung, Pause, Kurzarbeit, Krankheit und Sonstiges, mit mehreren
„Speichern“-Knöpfen. Begriffe wie „Override“, „system_log auditiert“, „Delta“
und „Batch“ erklären eine Umsetzung statt der Aufgabe. Dezimalstunden
erfordern Umrechnung, etwa 0,25 Stunden für 15 Minuten.

**Vorschlag:** Zuerst Aufgabe wählen: „Buchung korrigieren“, „Pause ändern“,
„Abwesenheit eintragen“ oder „Stunden gutschreiben/abziehen“. Passendes Formular
öffnen; Dauer in Stunden und Minuten eingeben oder die Umrechnung unmittelbar
anzeigen. Begründung und Änderungsnachweis beibehalten.

**Abnahme:** Eine Pause von 15 Minuten lässt sich ohne Dezimalrechnung und
ohne Kenntnisse von „Overrides“ eintragen.

Beleg: `views/zeit/tagesansicht.php:394`, `views/mitarbeiter/stundenkonto.php:509`.
Die normale Mitarbeiteransicht hat diese Komplexität bereits nicht.

### UX-07 · Mittel · Kurzarbeitspläne haben keinen sichtbaren Einstieg

Liste und Formular sind vorhanden und auf direktem Weg erreichbar.
Die Navigation und die übrigen Ansichten verlinken die Liste jedoch nicht;
Verweise auf `kurzarbeit_admin` stehen nur innerhalb des Moduls selbst.
Tageskorrekturen für Kurzarbeit ersetzen den fehlenden Zugang zum Zeitraumplan nicht.

**Vorschlag:** „Kurzarbeit planen“ bei den Arbeitszeit-/Abwesenheitsaufgaben
verlinken, passend zum vorhandenen Recht.

**Abnahme:** Ein berechtigter Benutzer öffnet einen Kurzarbeitsplan über die
Navigation, ohne eine URL kennen zu müssen.

Beleg: `views/kurzarbeit/liste.php`, `views/kurzarbeit/formular.php`,
Suche nach `kurzarbeit_admin` unter `views/`. **B-108**.

### UX-08 · Mittel · Terminaltexte und Auftragsauswahl vereinfachen

Der Urlaubsassistent zeigt **„Schritt 1 von 3: ab_wann“**, **„bis_wann“** und
**„Exit“**. Beim Auftragsstart erscheint danach eine interne Auftragszeit-ID.
Ohne gescannten Code muss der Mitarbeiter Auftrag und Arbeitsschritt kennen;
bei fehlender Maschinenliste bleibt eine technische Maschinen-ID-Eingabe.
Mit Scanner ist der Codeweg sinnvoll, ohne Scanner ist er wenig selbsterklärend.

**Vorschlag:** „Beginn“, „Ende“, „Abbrechen“ und konkrete Bestätigungen mit
Auftragsnummer verwenden. Als Ergänzung zum Scan eine einfache Auswahl
bekannter Aufträge, Arbeitsschritte und Maschinen anbieten. Den dreistufigen
Urlaubsassistenten und die großen Schaltflächen beibehalten.

**Abnahme:** Urlaub enthält keine internen Schrittnamen und ein Auftrag kann
wahlweise per Scan oder verständlicher Auswahl gestartet werden.

Beleg: `views/terminal/start.php:994`, `views/terminal/start.php:1064`,
`views/terminal/auftrag_starten.php`, `controller/TerminalController.php:2511`.

### UX-09 · Mittel · Mitarbeiter und Rechte benötigen bessere Orientierung

Die Mitarbeiterliste kündigt „Mitarbeiter suchen“ an, besitzt aber kein
Suchfeld. Das Formular verlangt Urlaubstage **pro Monat** statt eines direkt
verständlichen Jahresanspruchs. Die Abteilungszuordnung verwendet „Stamm“
und mehrfach „ja“. Die Rechte sind zwar gruppiert, zeigen aber zusätzlich
technische Codes und Begriffe wie `add/update/delete`.

**Vorschlag:** Namens-/Personalnummernsuche ergänzen; Jahresanspruch mit
sichtbarer Umrechnung anbieten; „Hauptabteilung“ verwenden. Häufige
Rechtekombinationen als verständliche Vorlagen anbieten und Codes einklappen.
Beim Anlegen unterscheiden, ob jemand nur am Terminal arbeitet oder einen
Webzugang braucht; vorhandene Trennung von Stammdaten und Rechten beibehalten.

**Abnahme:** Ein neuer Werkstattmitarbeiter lässt sich mit Jahresurlaub und
Hauptabteilung anlegen, ohne Rechtecodes oder Monatsumrechnung zu verstehen.

Beleg: `views/mitarbeiter/liste.php:25`, `views/mitarbeiter/formular.php:172`,
`views/rolle/formular.php` sowie geöffnete Formulare und Rechteansichten.

### UX-10 · Mittel · Portal benötigt weiterhin einen manuellen Zeitplan

Das Koppeln selbst braucht nur Homepageadresse und Code. Für regelmäßige
Abgleiche verlangt die Installationsanleitung zusätzlich einen Cron-Eintrag
für `scripts/portal_sync.php`. Der normale neue Installer richtet die Wartung
ein, aber diesen Portal-Zeitplan nicht. Ein erfolgreicher Kopplungsvorgang
allein bedeutet damit nicht, dass die Verbindung künftig automatisch abgleicht.

**Vorschlag:** Den Portal-Zeitplan bei der normalen Installation vorbereiten
und nach Kopplung ohne weitere technische Eingabe nutzbar machen. In der
Oberfläche „Automatischer Abgleich aktiv“, letzter Erfolg und ein konkreter
Fehlerhinweis. Architekturerklärungen in Details verschieben.

**Abnahme:** Nach der normalen Kopplung erscheinen neue Anträge automatisch,
ohne dass ein Administrator Cron kennen oder einrichten muss.

Beleg: `docs/installationsanleitung.md:121`, `scripts/installieren.sh`,
`views/portal_admin/index.php`; Quelltext-/Dokumentationsbefund, kein neuer
Live-Test mit einer externen Homepage.

### UX-11 · Mittel · Handbuch beschreibt für einfache Aufgaben Codeänderungen

Das Administratorhandbuch empfiehlt, Rundungsregeln in Services zu ändern,
obwohl dafür eine Verwaltungsmaske vorhanden ist. Auch Datenbank-, Bridge-
und Dateikonfiguration stehen weit vorn. Das unterstützt keinen normalen
Administrator bei wiederkehrenden Aufgaben.

**Vorschlag:** Kurze Anleitungen nach Aufgaben: Mitarbeiter anlegen, Chip
zuweisen, vergessene Buchung korrigieren, Urlaub genehmigen, Gerät koppeln,
Backup erstellen. Installations- und Entwicklerwissen gesondert halten.

**Abnahme:** Die Anleitung „Rundung ändern“ führt zur vorhandenen Oberfläche
und verlangt keine Bearbeitung einer PHP-Datei.

Beleg: `docs/admin_handbuch.md:54`.

### UX-12 · Niedrig · Rückmeldungen und Formate vereinheitlichen

Bei einem Enddatum vor dem Beginn lautet die Vorschau zunächst nur
„Zeitraum wählen für Vorschau“; erst Speichern erklärt den Fehler.
Die Vorschau lädt beim Ändern eines Datums die ganze Seite neu, wodurch der
Fokus wechselt. Verschiedene Bereiche nutzen „Logout“, „Exit“, „Editieren“,
„Bearbeiten“, `YYYY-MM-DD` und `DD.MM.YYYY` sowie Punkt und Komma für Zahlen.
Rundungsregeln zeigen intern benannte Werte wie `naechstgelegen` und bieten
keinen unmittelbaren Beispielvergleich einer Buchungszeit.

**Vorschlag:** Fehler direkt beim Feld anzeigen; Vorschau ohne vollständige
Navigation aktualisieren; deutsche Begriffe und Formate einheitlich verwenden.
Bei Rundung eine unverbindliche Vorschau wie „07:04 → 07:00“ anbieten.

**Abnahme:** Fehlerhafte Zeiträume sind vor dem Absenden klar erkennbar und
eine Änderung der Vorschau unterbricht die Eingabe nicht.

Beleg: `views/urlaub/meine_antraege.php:348`,
`views/zeit_rundungsregel/liste.php`, geöffnete Backend- und Terminalansichten.

## Abdeckung nach Bereich

| Bereich | Prüfung | Einordnung |
| --- | --- | --- |
| Erststart und Anmeldung | Backend-Erststart, zwei bestehende Testzugänge | Kurze Formulare; kein geführter nächster Schritt für die weitere Einrichtung |
| Navigation und Startseite | Administrator und Mitarbeiter, Desktop und schmal | Mitarbeiter einfach, Verwaltung zu technisch |
| Mitarbeiter, Abteilungen, Maschinen | Listen, neue/bestehende Mitarbeiterformulare, Stammdatenformulare | Basis gut; Suche, Hauptabteilung, Urlaubsanspruch vereinfachen |
| Rollen und Rechte | Rollenformular und Mitarbeiterrechte | Gruppiert, aber technische Bezeichnungen |
| Tageszeiten und Stundenkonto | Admin-/Mitarbeiteransicht, Korrektur- und Verteilformulare | Viele gleichzeitige Aktionen und Fachbegriffe |
| Urlaub | Antrag, Vorschau, Validierung, Verwaltung, Genehmigungsansicht, Kontingent | Antrag kurz, Saldenanzeige zu komplex; Genehmigungsabschluss nicht bestätigt |
| Kalender und Abwesenheiten | Jahresübersicht, Feiertage, Betriebsferien, Krankzeitraum, Kurzarbeit | Viele Funktionen vorhanden; Kurzarbeitsplan schlecht auffindbar |
| Arbeitszeitregeln | Pausen- und Rundungsformulare | Verständliche Beispiele und klarere Begriffe fehlen |
| Monatsübersicht und PDF-Zugänge | Zwei Rollen, Desktop/390 Pixel, sichtbare Drucklinks | Monatsübersicht mobil mangelhaft; kein neuer PDF-Layouttest |
| Aufträge und Schritte | Liste/Suche, Neuformular, Details, Katalog, Laufkartenverweise | Codes hilfreich; Statusbegriffe widersprechen einander |
| Terminal | Anmeldung, Kommen/Gehen, Start/Stopp, Urlaubsassistent | Hauptaktionen einfach; Codes und einzelne Texte verbessern |
| Kopplung und Offline | Einrichtungsmaske, Adminansichten; Regeln/Quelltext | Kopplungsformular einfach; Zugang/Wartung bei „inaktiv“ klarstellen; Hardware-/Ausfalltests offen |
| Backup und Updates | Oberfläche, Statusdarstellung, Installations- und Betriebsweg | Gutes Vorbild; erste Geräteauslieferung und native Abnahme noch offen |
| Mitarbeiterportal | Ungekoppelte Oberfläche, Installer und Anleitung | Zusätzlicher Portal-Zeitplan verhindert vollständig einfache Einrichtung |
| Logs und Offline-Queue | Listen, Filter und Bedienbeschriftungen | Für technische Betreuung; aus täglicher Aufgabenansicht heraushalten |

## Empfohlene Reihenfolge für weitere Patches

Die Reihenfolge ist ein Vorschlag, keine bereits umgesetzte oder freigegebene
Änderung von Fachregeln.

1. **Konkrete Hindernisse beheben:** mobile Tabellen (B-107), Menüweg zur
   Kurzarbeit (B-108), irreführende Terminal- und Auftragsstatusangaben klären.
   Jeweils ein eigener, kleiner Patch.
2. **Häufige Aufgaben vereinfachen:** Verwaltungsstartseite, Urlaubssaldo,
   Zeitkorrekturen und Mitarbeitersuche. Berechnungen und Rechte beibehalten.
3. **Terminal sprachlich aufräumen:** interne Namen entfernen und die
   Bedienung ohne Scanner ergänzen, soweit der Praxistest diesen Weg benötigt.
4. **Einrichtung und Hilfe vervollständigen:** Portal-Abgleich automatisch
   vorbereiten; kurze Aufgabenanleitungen statt Quelltextanweisungen.
5. **Mit tatsächlichen Anwendern am Gerät abnehmen:** Ein neuer Mitarbeiter
   soll ohne Erklärung stempeln, Urlaub beantragen und einen Auftrag buchen;
   ein neuer Administrator soll Mitarbeiter, Zeitkorrektur und Backup finden.
   Erst dieser Test belegt die Alltagstauglichkeit mit realem Touchscreen und Leser.

Der vorhandene gemeinsame Quellcode von Backend und Terminal ist dafür eine
gute Grundlage. Eine vollständige Neuentwicklung ist für die gefundenen
Bedienprobleme nicht erforderlich.
