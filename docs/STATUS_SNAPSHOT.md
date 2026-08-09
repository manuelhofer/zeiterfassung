# Status-Snapshot

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdruecklicher Beauftragung**.

## Projektziel (kurz)
Webbasierte Zeiterfassung inkl. Mitarbeiter-/Rollen-/Genehmiger-Verwaltung, Urlaubsverwaltung, Auswertungen sowie Terminal-UI (Kiosk) inkl. Offline-Queue.

## Entry-Points
- Backend: `public/index.php` (Routing ueber `?seite=...`)
- Terminal: `public/terminal.php` (Routing ueber `?aktion=...`)

## Arbeitsweise (seit 2026-08-08)
- Verbindliche Regeln: `docs/arbeitsregeln.md`; Fachlogik in `docs/fachregeln/`.
  Einstieg fuer KI-Assistenten: `CHATSTART.md`.
- Gearbeitet wird direkt im Git-Workspace; ein Patch ist ein Commit mit Patch-ID im Betreff. Keine ZIP-Pakete mehr, kein 3-Dateien-Limit.
- Lokale Umgebung zum Testen: `docs/lokale_entwicklungsumgebung.md` (App unter `http://localhost/zeiterfassung`).

## Naechster Schritt
**Stufe 3 der Terminal-Installation: das Grundsystem-Skript.**
`scripts/terminal/install_terminal.sh` bringt ein frisches Linux-Geraet bis zur
laufenden Weboberflaeche: Pakete, Code aus Git, Webserver auf `public/`,
Tastaturlayout und die lokale Ausweichdatenbank samt `config/geraet.local.php`.
Bewusst **ohne** `config.local.php` – die schreibt sich das Terminal bei der
Kopplung selbst.
Grundlage: `docs/spezifikation_terminal_installation.md` (Abschnitte 3 bis 5);
Einzelheiten im „Naechster Schritt“-Block von `docs/archiv/DEV_PROMPT_HISTORY.md`.
**Stufe 1 (Kopplung im Backend) und Stufe 2 (Einrichtungsseite am Geraet) sind
vollstaendig** und durchgaengig geprueft.

## Letzte Aenderungen (Auszug)
- **P-2026-08-09-01 Einrichtungsseite im Terminal:** Fehlt `config/config.local.php`, zeigt `public/terminal.php` statt der Bedienoberflaeche eine Einrichtungsseite: Server-Adresse und Kopplungscode eintippen (Bildschirmtastatur; fuer den Code nur die Zeichen, die darin vorkommen koennen), fertig. Das Terminal ruft den Kopplungs-Endpunkt auf und schreibt seine Konfiguration selbst – erst daneben, dann umbenennen, damit keine halbe Datei entstehen kann. Scheitert das Schreiben, wird der Inhalt zum Abtippen angezeigt, statt den verbrauchten Code wegzuwerfen. Eine vorhandene Konfiguration wird nie ueberschrieben. Geprueft mit zwei getrennten Installationen ueber echte Webserver, inklusive Anmeldung am gekoppelten Terminal. Neu: `config/geraet.local.php` fuer das, was der Maschine gehoert (Ausweichdatenbank, RFID-Bridge). Damit ist Stufe 2 vollstaendig.
- **P-2026-08-08-36 Terminal-Kopplung, Endpunkt:** `?seite=terminal_kopplung` (POST, JSON, ohne Anmeldung – der Code ist der Nachweis) loest den Kopplungscode ein, legt den Datenbankbenutzer an und antwortet mit Terminal-ID, Zugangsdaten und Einstellungen. Fehlversuche werden gebremst, ein stillgelegtes Terminal koppelt nicht, und bei einem Fehler bleibt kein halber Zustand zurueck. Ueber den laufenden Webserver geprueft, inklusive echter Datenbankverbindung mit den gelieferten Zugangsdaten. Damit ist Stufe 1 vollstaendig.
- **P-2026-08-08-35 Terminal-Kopplung, Datenbankbenutzer:** Jedes Terminal bekommt einen eigenen Datenbankbenutzer mit eng gefassten Rechten – stempeln und buchen ja, loeschen/Rechte vergeben/Stundenkonto aendern nein. 35 Proben gegen die laufende Datenbank. Dabei zeigte sich, dass die Rechteliste der Spezifikation zu eng war und das Terminal an vier Stellen lahmgelegt haette; sie ist jetzt aus dem Code hergeleitet. Offen als T-101: `passwort_hash` bleibt fuer das Terminal lesbar.
- **P-2026-08-08-31 Terminal-Kopplung, Stufe 1b:** Knopf „Kopplungscode“ in der Terminalverwaltung; der Code wird genau einmal angezeigt und ist danach nicht mehr abrufbar. Durchgaengig geprueft bis zum Einloesen.
- **P-2026-08-08-30 Terminal-Kopplung, Stufe 1a:** Tabelle `terminal_kopplung` und `TerminalKopplungService` zum Erzeugen und Einloesen von Kopplungscodes (nur Hash gespeichert, einmalig, zeitlich begrenzt). Oberflaeche und DB-Benutzer-Anlage folgen.
- **P-2026-08-08-29 Terminal-Spezifikation v2:** Terminal meldet sich per Kopplungscode am Backend an und erhaelt einen eigenen, eingeschraenkten Datenbankbenutzer. Das Installationsskript kennt damit keine Zugangsdaten mehr. Offen: ob das Backend die DB-Benutzer selbst anlegt (braucht `CREATE USER`/`GRANT OPTION`) oder die SQL nur anzeigt.
- **P-2026-08-08-28 Spezifikation Terminal-Installation:** Zielbild fuer ein Skript, das ein Linux-Geraet in einem Durchlauf zum Hallenterminal macht (Pakete, Kiosk, RFID beide Varianten, Scanner, Touchscreen, Selbsttest). Noch nicht umgesetzt; Grenzen und ein Sicherheitshinweis zu den DB-Zugangsdaten auf dem Geraet sind benannt.
- **P-2026-08-08-26 Personalnummer + Ampelfarben:** Monatsuebersicht zeigt `(Nr. …)` statt der Datenbank-ID (Rueckfall auf die ID, wenn keine Personalnummer gepflegt ist); `appearance: none` sorgt dafuer, dass Firefox die Optionsfarben im aufgeklappten Menue ueberhaupt zeichnet.
- **P-2026-08-08-25 Namen und Doku:** Spezifikation heisst jetzt `spezifikation_auftrag_barcode_laufkarte.md` (v2, Status umgesetzt), Konfigurationsschluessel `auftrag_code_rel_pfad` mit Migration `sql/04_...` und Rueckfall auf den alten Namen. README, Doku-Index und Prompt-Uebersicht aktualisiert.
- **P-2026-08-08-24 Fix Monatsuebersicht (B-091):** Im laufenden Monat brach die Seite mit einem Fatal ab – Regression aus P-19, weil eine Variable nur im Vergangenheits-Zweig gesetzt wurde. Behoben; laufender, vergangener und zukuenftiger Monat geprueft.
- **P-2026-08-08-23 Zeitwarnungen bleiben stehen:** Unvollstaendige Kommen/Gehen-Stempel verschwinden nicht mehr nach Ablauf einer Frist, sondern bleiben sichtbar bis zur Korrektur. Damit ist der gesamte Spiegel-Stand uebernommen.
- **P-2026-08-08-22 Inaktive Mitarbeiter:** `?seite=mitarbeiter_admin&status=inaktiv` zeigt ausgeschiedene Mitarbeiter, die bisher aus dem Backend nicht mehr erreichbar waren.
- **P-2026-08-08-21 Deutsche Datumsformate:** Tagesansicht, Stundenkonto, Feiertagsliste und Krankzeitraum-Pflege zeigen `01.06.2026` statt `2026-06-01`. Gespeichert wird weiterhin ISO.
- **P-2026-08-08-20 Urlaub aus dem Spiegel:** Monatsabschluss-Marker und Urlaubssalden in der Jahresuebersicht, „Sonstiger Grund“ in der Urlaubsverwaltung, farbige Kalenderzellen. Keine Migration noetig.
- **P-2026-08-08-19 Ampel in der Auswahlliste:** Auch die aufgeklappte Mitarbeiterliste ist jetzt rot/gruen; zusaetzlich Textzeichen (`✓` / `● offen`), weil Farbe allein je nach Browser und Systemthema nicht ankommt und nicht barrierefrei ist.
- **P-2026-08-08-18 Monatsabschluss-Ampel + Status-Auswahl:** Mitarbeiter in der Monatsuebersicht rot (Abschluss offen) bzw. gruen (gebucht), inkl. Stepper-Bedienung – aus dem nicht gepushten Spiegelverzeichnis uebernommen. Auftragsstatus ist jetzt eine gepruefte Auswahlliste statt Freitext.
- **P-2026-08-08-16 Katalog im Auftrag:** Standardschritte lassen sich per Mehrfachauswahl in einen Auftrag uebernehmen; fehlende Bezeichnungen werden beim Anzeigen aus dem Katalog ergaenzt (auch fuer am Terminal gescannte Codes). Der Buchungspfad des Terminals bleibt bewusst unberuehrt.
- **P-2026-08-08-15 Strichcodes statt QR:** Alle Auftrags-, Arbeitsschritt- und Katalog-Codes sind jetzt Code 128 – derselbe Typ wie die vorhandenen Maschinen-Codes, passend zu den 1D-Handscannern im Betrieb. `QrCodeService` heisst jetzt `BarcodeService`.
- **P-2026-08-08-14 Katalog-Druckblatt:** `?seite=arbeitsschritt_katalog_blatt` liefert QR-Karten zum Ausschneiden – alle Katalogschritte als Uebersicht oder ein Schritt in frei waehlbarer Stueckzahl (z. B. 20x `fraesen`). Sechs Karten je A4-Seite mit Schnittmarkierung; alle 20 Karten mit `zbarimg` als lesbar bestaetigt.
- **P-2026-08-08-13 Arbeitsschritt-Katalog:** Zentrale Standardschritte (z. B. `fraesen`) unter `?seite=arbeitsschritt_katalog`, erreichbar ueber das neue Aufklappmenue „Auftraege“. Einmal pflegen, QR beliebig oft ausdrucken und an die Maschinen haengen.
- **P-2026-08-08-12 Katalog-Tabelle:** Neue Tabelle `arbeitsschritt_katalog` (Initialschema + Migration `sql/03_...`), Spezifikation um Abschnitt 4a erweitert.
- **P-2026-08-08-11 Laufkarten-PDF:** `?seite=auftrag_laufkarte&code=…` erzeugt die druckbare Laufkarte (Auftragskopf mit QR, je Arbeitsschritt ein Block mit QR und Feldern zum Eintragen, mehrseitig). QR-Codes werden als Vektor gezeichnet; mit `pdftoppm` + `zbarimg` gegengeprueft, dass sie scannbar sind.
- **P-2026-08-08-10 Arbeitsschritte mit QR-Codes:** Auftragsdetail zeigt Stammdaten und Auftrags-QR; Arbeitsschritte lassen sich anlegen und bearbeiten, jeder mit eigenem QR-Code. Mit `zbarimg` gegengeprueft: die Codes liefern exakt den Wert, den das Terminal erwartet.
- **P-2026-08-08-09 Auftrag anlegen/bearbeiten:** Neue Routen `auftrag_neu` / `auftrag_bearbeiten` / `auftrag_speichern` mit CSRF und Recht `AUFTRAEGE_VERWALTEN`; die Auftragsliste zeigt jetzt auch Auftraege ohne Buchung (Status `angelegt`), inkl. Kunde/Kurzbeschreibung.
- **P-2026-08-08-08 Recht AUFTRAEGE_VERWALTEN:** Neues Recht fuer das Anlegen/Bearbeiten von Auftraegen und Arbeitsschritten (Initialschema + idempotente Migration `sql/02_...`), im Rechte-Prompt dokumentiert. Ansehen und Laufkarte bleiben bewusst rechtefrei.
- **P-2026-08-08-07 QrCodeService:** Neuer Dienst fuer QR-Codes beliebiger Nutzdaten (PNG-Datei fuer die Anzeige, Modulmatrix fuer das PDF), Erzeugung nur bei Bedarf. Web-Basis-Ableitung nach `Helper::ermittleWebBasis()` gezogen (keine zweite Kopie, vgl. B-089); `DefaultsSeeder` baut sein INSERT jetzt dynamisch auf.
- **P-2026-08-08-06 Spezifikation Auftrags-QR und Laufkarte:** Zielbild und Akzeptanzkriterien fuer Auftraege im Backend anlegen, Arbeitsschritte mit QR-Codes und Laufkarten-PDF (`docs/spezifikation_auftrag_barcode_laufkarte.md`).
- **P-2026-08-08-05 erzeugte Dateien:** `public/uploads/` ist von der Versionierung ausgenommen (nur `.gitkeep` bleibt); die ACL im Setup-Skript traegt jetzt Webserver **und** Projekteigentuemer ein, damit erzeugte Dateien von beiden Seiten ueberschreibbar bleiben.
- **P-2026-08-08-04 phpqrcode warnungsfrei (B-090):** Vier Ursachen in der mitgelieferten QR-Bibliothek behoben (Pflichtparameter hinter optionalen Parametern, dynamische Eigenschaft `$cmyk`, wirkungsloses `ImageDestroy()`, fehlendes Cache-Verzeichnis). QR-Code- und Barcode-Erzeugung laufen unter PHP 8.5 ohne eine einzige Meldung; betraf auch den Produktivserver mit PHP 8.3.
- **P-2026-08-08-03 Maschinen-Barcode-URL automatisch:** Die Anzeige-URL des Maschinen-Barcodes wird aus der Web-Basis der Installation plus `maschinen_qr_rel_pfad` abgeleitet, statt sie zusaetzlich von Hand pflegen zu muessen. `maschinen_qr_url` ist jetzt nur noch ein Override (leer = automatisch, `/` = Domain-Root, sonst Pfad/URL). Abweichende Zweitlogik im `MaschineAdminController` entfernt. Neuer Bug B-090 dokumentiert (phpqrcode-Deprecations).
- **P-2026-08-08-02 Datenbestand lokal + PHP-8.5-Pruefung:** Serverdump gegen `sql/01_initial_schema.sql` geprueft (strukturgleich, keine Abweichung) und lokal eingespielt; Fachlogik (Report, PDF, Urlaub, Stundenkonto, Feiertage) unter PHP 8.5 ohne Deprecations/Warnings. Produktivserver laeuft auf Debian 11 / MariaDB 11.8.3 / PHP 8.3.26. Dumps bleiben aus dem Repo heraus (personenbezogene Daten).
- **P-2026-08-08-01 lokale Umgebung + Doku-Neuordnung:** Reproduzierbares Setup-Skript fuer die lokale Entwicklungsumgebung (Apache + php-fpm + MariaDB LTS + phpMyAdmin), Root-`README.md` als Einstieg nach dem Klonen, Master-Prompt v13 (ZIP-/Dateilimit-Regeln entfallen samt Begruendung, PHP-Baseline definiert), `docs/archiv/ALTE_PROMPTS.md` als Begruendungsliste zum Archiv. Keine Fachlogik geaendert.
- **2026-07-17 Stundenkonto-Sammelumbuchung lokal:** Separate Umbuchungsmaske aus dem Stundenkonto heraus; normale Stundenkonto-Seite bleibt ohne Monatsfilter, die Sammelumbuchung zeigt Monats-Tageswerte und verschiebt eingegebene Abzuege gesammelt auf einen Zieltag (netto 0), inkl. Stealth-Unterstuetzung.
- **2026-07-17 Header-Menue lokal:** Top-Navigation in Dropdown-Gruppen `Urlaub`, `Uebersichten`, `Mitarbeiter`, `Rechte` und `Verwaltung` aufgeraeumt; bestehende Zielseiten/Rechtebedingungen bleiben erhalten.
- **2026-07-17 Mitarbeiter/Rollen-Rechte UI lokal:** Rollen, Rechte-Overrides und Genehmiger aus dem normalen Mitarbeiterformular in `?seite=mitarbeiter_rechte` ausgelagert; Stammdaten-Speichern laesst bestehende Rechte-Zuordnungen unangetastet.
- **2026-07-17 Mitarbeiter/Stundenkonto UI lokal:** Stundenkonto aus dem Mitarbeiterformular als eigene Seite `?seite=mitarbeiter_stundenkonto` mit Mitarbeiter-Auswahl ausgelagert; bestehende Buchungslogik bleibt erhalten, Ruecksprung nach Buchung fuehrt optional zur neuen Seite.
- **2026-07-17 Urlaub-Jahresuebersicht V1 lokal:** Read-only Jahresuebersicht unter `?seite=urlaub_jahresuebersicht` mit echten Urlaub-Genehmigungsrechten, U/O/BF/FT-Kalenderzellen, Monatswerten nur bei vorhandenen abgeschlossenen Monatsdaten und O-Link zur Genehmigungsliste.
- **2026-07-17 lokale Doku-Aufraeumung:** Root-`index.php` leitet auf `public/index.php`; `docs/wartungscheckliste.md`, `docs/prompt_uebersicht.md` und `docs/archiv/README.md` ergaenzt/verlinkt; Master-/Rechte-Prompt und aktive Doku an reale Pfade/Schema-Stand angepasst; keine Fachlogik geaendert.
- **P-2026-01-25-02:** Dashboard: Zeitwarnungen-Query als Derived-Table (ONLY_FULL_GROUP_BY/SQLMODE-sicher) + Bind-Parameter (start_ts, today); Debug-Fehlertext nur fuer Legacy-Admin im UI.
- **P-2026-01-24-08:** Dashboard: Zeitwarnungen-Query nutzt keine PDO-Parameter mehr (Inline-ISO-Datum), weil MariaDB/PDO in der Praxis trotz vorhandener Daten leere Resultsets lieferte; Query entspricht dem phpMyAdmin-Test und Zeitwarnungen werden wieder sichtbar.
- **P-2026-01-24-07:** Dashboard: Zeitwarnungen waren trotz vorhandener Daten unsichtbar, weil `DashboardController` versehentlich `fetchEinzel(...)` (nicht existent) aufruft und dadurch in den Catch faellt → Fix auf `fetchEine(...)`.

## Letzter Patch (P-ID)
- P-2026-08-09-02 (Commit; Doku nach Lesehaeufigkeit aufgeteilt)
- Davor: P-2026-08-09-01 (Einrichtungsseite im Terminal, Stufe 2 vollstaendig)
- Davor: P-2026-08-08-36 (Kopplungs-Endpunkt, Stufe 1 vollstaendig)
- Davor: P-2026-08-08-35 (eigener Datenbankbenutzer je Terminal)
- Davor: P-2026-08-08-34 (Uebergabepruefung, B-092 dokumentiert)
- Davor: P-2026-08-08-33 (Doku-Luecken vor der Uebergabe geschlossen)
- Davor: P-2026-08-08-32 (naechster Schritt praezisiert)
- Davor: P-2026-08-08-31 (Kopplungscode im Backend erzeugen)
- Davor: P-2026-08-08-30 (Kopplungscodes: Tabelle und Dienst)
- Davor: P-2026-08-08-29 (Terminal-Spezifikation v2: Kopplung)
- Davor: P-2026-08-08-28 (Spezifikation Terminal-Installation)
- Davor: P-2026-08-08-26 (Personalnummer statt ID, Ampelfarben in der Liste)
- Davor: P-2026-08-08-25 (Namen auf Strichcode-Stand, Doku aktualisiert)
- Davor: P-2026-08-08-24 (Fix Monatsuebersicht im laufenden Monat)
- Davor: P-2026-08-08-23 (Zeitwarnungen verschwinden nicht mehr)
- Davor: P-2026-08-08-22 (Inaktive Mitarbeiter einsehbar)
- Davor: P-2026-08-08-21 (Deutsche Datumsformate)
- Davor: P-2026-08-08-20 (Urlaubsbereich aus dem Spiegelverzeichnis)
- Davor: P-2026-08-08-19 (Ampel auch in der aufgeklappten Auswahlliste)
- Davor: P-2026-08-08-18 (Monatsabschluss-Ampel und Status-Auswahl)
- Davor: P-2026-08-08-17 (Doku Auftraege und Katalog)
- Davor: P-2026-08-08-16 (Katalog wirkt in den Auftrag hinein)
- Davor: P-2026-08-08-15 (Strichcodes statt QR-Codes)
- Davor: P-2026-08-08-14 (Druckblatt fuer Katalog-Codes)
- Davor: P-2026-08-08-13 (Katalog-Verwaltung im Menue Auftraege)
- Davor: P-2026-08-08-12 (Arbeitsschritt-Katalog: Tabelle und Spezifikation)
- Davor: P-2026-08-08-11 (Laufkarten-PDF mit QR-Codes)
- Davor: P-2026-08-08-10 (Arbeitsschritte je Auftrag mit QR-Codes)
- Davor: P-2026-08-08-09 (Auftrag im Backend anlegen und bearbeiten)
- Davor: P-2026-08-08-08 (Recht AUFTRAEGE_VERWALTEN)
- Davor: P-2026-08-08-07 (QrCodeService fuer Auftraege und Arbeitsschritte)
- Davor: P-2026-08-08-06 (Spezifikation Auftrags-QR und Laufkarte)
- Davor: P-2026-08-08-05 (Umgang mit zur Laufzeit erzeugten Dateien)
- Davor: P-2026-08-08-04 (phpqrcode laeuft unter aktuellem PHP warnungsfrei)
- Davor: P-2026-08-08-03 (Maschinen-Barcode-URL wird automatisch abgeleitet)
- Davor: P-2026-08-08-02 (Datenbestand lokal eingespielt, PHP-8.5-Pruefung der Fachlogik)
- Davor: P-2026-08-08-01 (lokale Entwicklungsumgebung + Doku/Master-Prompt v13)
- Noch aus dem ZIP-Workflow: P-2026-01-25-02_dashboard-zeitwarnungen-derived-table.zip

## Quelle der DB-Struktur
- `sql/01_initial_schema.sql`
