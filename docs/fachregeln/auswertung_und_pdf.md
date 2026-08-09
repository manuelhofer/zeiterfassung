# Fachregeln: Monatsuebersicht, Reports, PDF, Stundenkonto

*Gilt fuer:* `services/ReportService.php`, `services/PDFService.php`,
`services/StundenkontoService.php`, `controller/ReportController.php`.
*Herkunft:* Master-Prompt v13, Abschnitt 10.3 sowie v4-Abschnitt C.

---

## 1. Einzel-PDF pro Mitarbeiter

Auswahl Mitarbeiter + Monat/Jahr, dann eine Liste aller Tage mit:

- Tag/KW
- **An** (roh), **Ab** (roh), **An.Korr**, **Pause**, **Ab.Korr**, **Ist**
- Arzt, Krank (LFZ/KK), Feiertag, Kurzarbeit, Urlaub, Sonstiges

**Layout-Hinweis:** Spaltenueberschriften muessen kurz sein (z. B. **An/Ab**
statt „Kommen/Gehen"), damit die Texte in die Tabellenkoepfe passen.

Summenzeilen: Sollstunden, Iststunden, Arztstunden, Krankstunden (LFZ/KK),
Feiertagsstunden, Urlaubsstunden, Kurzarbeitsstunden, sonstige Stunden,
Ueber-/Minusstunden. Dazu das **Stundenkonto** (Stand bis Vormonat) und der
Urlaubsblock („Urlaubtage (abzgl. BF)", „BF (Rest Jahr)").

### Mehrere Arbeitsbloecke an einem Tag

Hat ein Tag mehrere Bloecke, wird der Tag **mehrfach** ausgegeben – je Block
eine Zeile. Dabei gilt:

- Abwesenheitsfelder (Urlaub, Krank, Kurzarbeit, Sonstiges) werden **nur in der
  ersten Zeile des Tages** gefuellt, die weiteren bleiben leer – keine optische
  Doppelzaehlung.
- **IST wird je Block** angezeigt, auch in den Folgezeilen.
- Pause und Meta-Angaben stehen an der **Primaerzeile** (Block ≥ 60 min), nicht
  automatisch in der ersten Zeile.
- Summen bleiben tages- und monatsweise korrekt.

Das Feld `kommentar` ist die Kuerzel-/Begruendungsspalte (z. B. `BF`, `SoU`,
`SoU: …`).

### Mikro-Buchungen

Standard ist **aggregiert pro Tag**. Detail- bzw. Mikro-Buchungen erscheinen nur
mit `?show_micro=1` (B-078). Erkannt werden sie am **Rohstempel**, damit die
Rundung sie nicht kuenstlich aufblaeht; Schwelle ueber
`config:micro_buchung_max_sekunden`.

Bloecke, die durch die Rundung auf 0 zusammenfallen (`gehen_korr <=
kommen_korr`), werden wie Mikro-Buchungen behandelt: ausgeblendet, Block-IST
0,00.

Ist die Checkbox „Mikro-Buchungen anzeigen" aktiv, uebernimmt der PDF-Link
`?show_micro=1` mit.

## 2. Sammel-PDF („Monatslauf")

Auswahl Monat/Jahr, dann werden fuer **alle aktiven Mitarbeiter** (oder
gefiltert nach Abteilung) die PDFs in einem Lauf erzeugt – entweder als einzelne
Dateien (`Mitarbeitername_Monat.pdf`) in einem Verzeichnis oder als
ZIP-Bundle zum Download.

Der Chef sichtet sie gesammelt, korrigiert gezielt einzelne Mitarbeiter und
erzeugt deren PDF erneut.

Rechte: Einzel-PDF fuer beliebige Mitarbeiter braucht `REPORT_MONAT_VIEW_ALL`,
der Sammel-Export `REPORT_MONAT_EXPORT_ALL`.

## 3. Markierung manuell veraenderter Daten

Im PDF muss sichtbar sein, wo von Hand eingegriffen wurde:

- **Rot markiert werden nur manuell geaenderte Kommen/Gehen-Zeiten.**
  Tageskennzeichen wie Kurzarbeit werden **nicht** rot – das war frueher anders
  und hat verwirrt (B-029).
- Grundlage sind `zeitbuchung.manuell_geaendert` bzw.
  `tageswerte_mitarbeiter.rohdaten_manuell_geaendert`.
- `Ko.Korr` und `Ge.Korr` werden nie direkt von Hand geaendert, sondern nur
  ueber Rundung und Rohdaten beeinflusst.

## 4. Abgrenzungen, die immer wieder falsch laufen

- **Kurzarbeit zaehlt nicht als IST.** Sie reduziert das Soll (B-038).
- **Kurzarbeit-Volltag wirkt wie Betriebsferien** (i. d. R. Tages-Soll bzw.
  8 h), aber **nicht zusaetzlich**, wenn am selben Tag gearbeitet wurde (B-030).
- **Betriebsferien nicht zusaetzlich zur Arbeitszeit zaehlen**, wenn an einem
  BF-Tag tatsaechlich gearbeitet wurde (B-024).
- **Krank hat Vorrang vor Betriebsferien** (B-077).
- **Urlaubsblock nicht doppelt abziehen:** Der Block nutzt den
  Jahressaldo des `UrlaubService` (inkl. BF); „BF (Rest Jahr)" ist reine Info
  (B-079, B-081). „Urlaubtage (abzgl. BF)" darf **negativ** sein – keine
  0-Deckelung.

## 5. Stundenkonto

`StundenkontoService` liefert den Saldo **bis Vormonat** – Basis fuer Terminal,
Monatsuebersicht und PDF.

Tabellen: `stundenkonto_batch` (Verteilbuchungen) und `stundenkonto_korrektur`
(manuelle Korrekturen).

Funktionen im Backend, alle mit Recht `STUNDENKONTO_VERWALTEN` und **Begruendung
als Pflichtfeld**, protokolliert in `system_log`:

- Saldo anzeigen und manuelle Korrektur buchen,
- Verteilbuchung (Batch) ueber einen Zeitraum, optional nur Mo–Fr,
- **Monatsabschluss:** bucht die Differenz Soll/Ist als
  Stundenkonto-Buchung – idempotent bzw. aktualisierbar und nur fuer
  **vergangene** Monate,
- **Sammelumbuchung:** eigene Maske aus dem Stundenkonto heraus, zeigt die
  Tageswerte eines Monats und verschiebt eingegebene Abzuege gesammelt auf einen
  Zieltag (netto 0).

In der Monatsuebersicht markiert eine **Ampel** je Mitarbeiter, ob der
Monatsabschluss offen (rot) oder gebucht (gruen) ist – auch in der aufgeklappten
Auswahlliste, und dort zusaetzlich mit Textzeichen (`✓` / `● offen`), weil Farbe
allein je nach Browser und Systemthema nicht ankommt und nicht barrierefrei ist.

## 6. Dashboard-Zeitwarnungen

Der Warnblock zeigt unvollstaendige Kommen/Gehen-Stempel (Recht
`DASHBOARD_ZEITWARNUNGEN_SEHEN`).

- Erkannt werden **echte Ungleichgewichte** (z. B. zweimal „Kommen" ohne
  „Gehen"), nicht nur eine ungerade Gesamtzahl.
- Zusaetzlich dient `tageswerte_mitarbeiter` als Rueckfall, damit „FEHLT"-Tage
  auch dann auftauchen, wenn keine ungeraden Rohstempel erkennbar sind.
- Standardzeitraum **31 Tage**, einstellbar ueber
  `config:dashboard_zeitwarnungen_tage`.
- Die Warnungen **verschwinden nicht** nach Ablauf einer Frist, sondern bleiben
  sichtbar bis zur Korrektur.

## 7. PDF-Technik

Eine schlanke PHP-Bibliothek liegt im Projekt (nicht als externe Abhaengigkeit).
Erfahrungswerte, die mehrfach Fehler verursacht haben:

- Kein literales `\n` in PDF-Kommandos – `sprintf` mit **doppelten** Quotes
  verwenden (B-013).
- Locale-sichere Zahlenformatierung: Bei `LC_NUMERIC=de_DE` bricht ein
  Dezimalkomma den PDF-Stream (B-015).
- Output-Buffering aufraeumen, Fehler abfangen, `no-gzip`/`no-transform`-Header
  setzen; PDF-Kopf mit Binaermarkierung, WinAnsiEncoding, `/ProcSet` und sauberer
  Stream-Terminierung (B-014, B-016).
- Summenblock-Positionen **vor** dem Bemerkungsblock definieren (B-037).
- `jahr`/`monat` in Report-Routen defensiv begrenzen, damit `monat=0` oder `13`
  keine DateTime-Fehler ausloest.

## 8. Datumsformate

In Tagesansicht, Stundenkonto, Feiertagsliste und Krankzeitraum-Pflege wird
`01.06.2026` angezeigt, nicht `2026-06-01`. **Gespeichert wird weiterhin ISO.**

Im Terminal gilt das eigene Format `HH:MM:SS DD-MM-YYYY` (siehe
[terminal_und_offline.md](terminal_und_offline.md)).
