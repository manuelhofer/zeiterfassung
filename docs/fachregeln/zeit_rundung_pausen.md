# Fachregeln: Kommen/Gehen, Rundung, Pausen, Tageskorrekturen

*Gilt fuer:* `services/ZeitService.php`, `services/RundungsService.php`,
Tageswerte, Korrekturmaske im Backend.
*Herkunft:* Master-Prompt v13, Abschnitte 9 und 10.1/10.2 sowie v4-Abschnitt D.

---

## 1. Rohdaten werden nie gerundet

Alle Kommen/Gehen-Zeitstempel werden **sekundengenau** gespeichert – ohne
Rundung. Tabelle `zeitbuchung`: `mitarbeiter_id`, `typ` (`kommen`/`gehen`),
`zeitstempel`, `quelle` (`terminal`/`web`), `manuell_geaendert`, optional
`kommentar`.

Rohdaten bleiben unveraendert, ausser ein Berechtigter korrigiert sie im
Backend bewusst (z. B. vergessene Buchung nachtragen). Dann wird
`manuell_geaendert` gesetzt und optional ein Kommentar gespeichert.

Die Rundung greift **erst** in der Auswertung: Tages- und Monatsberechnung,
Korrekturwerte `Ko.Korr` und `Ge.Korr`.

> Das ist die wichtigste Regel des ganzen Systems. Wer sie bricht, verliert die
> Nachvollziehbarkeit: Aus einer gerundeten Zahl laesst sich die Rohzeit nicht
> zurueckrechnen.

## 2. Rundungsregeln

Beispiel der betrieblichen Regel:

- Ankunft 05:02 → bezahlte Zeit ab 05:30
- bis 07:00 Uhr auf 30-Minuten-Raster
- ab 07:00 Uhr auf 15-Minuten-Raster
- Gehen 15:14 → 15:00

Umsetzung ueber Tabelle `zeit_rundungsregel`: `von_uhrzeit`, `bis_uhrzeit`,
`einheit_minuten`, `richtung` (`auf` | `ab` | `naechstgelegen`), `gilt_fuer`
(`kommen` | `gehen` | `beide`), `prioritaet`.

Der `RundungsService` berechnet daraus `kommen_korr`, `gehen_korr` und die
resultierenden `ist_stunden`.

## 3. Mehrfach Kommen/Gehen an einem Tag

Ein Mitarbeiter darf an einem Tag mehrfach kommen und gehen (z. B. 05:00–08:00
und 09:00–16:00). Aus den Rohdaten werden **Arbeitsbloecke** gebildet: je ein
`kommen` mit dem naechsten `gehen`.

Paarungslogik (robust, ohne Absturz):

- Buchungen des Tages nach `zeitstempel` aufsteigend sortieren,
- beim ersten `kommen` einen Block oeffnen,
- beim naechsten `gehen` den Block schliessen,
- ein `gehen` ohne offenes `kommen` gilt als **verwaist**: im Backend als
  Fehler anzeigen, **nicht** ins IST einrechnen,
- ein offenes `kommen` ohne `gehen` bleibt **offen**: Fehlerhinweis im Backend,
  nicht als voller Block rechnen.

In Auswertung und PDF wird **jeder Block als eigene Zeile** ausgegeben, auch
bei gleichem Datum. Tages- und Monatssummen sind die Summe aller gueltigen
Bloecke nach Rundung und Pausenabzug.

Beispiel:

- 22.02.2025 05:00–08:00 → 3,00 h
- 22.02.2025 09:00–16:00 → 7,00 h minus Pause (z. B. 45 min) → 6,25 h

## 4. Pausen

Zwei Arten, beide **konfigurierbar**:

1. **Zwangspausen** (betrieblich, Uhrzeitfenster): feste Fenster wie 09:00–09:15
   und 12:30–13:00. Diese Zeit zaehlt nicht als Arbeitszeit, **wenn** ein
   Arbeitsblock sie ueberlappt.
2. **Gesetzliche Mindestpause** (pauschal, schwellenbasiert), Standard nach
   § 4 ArbZG:
   - Arbeitszeit **> 6 h bis 9 h** → mindestens **30 Minuten**
   - Arbeitszeit **> 9 h** → mindestens **45 Minuten**

   Schwellen und Minuten muessen in der Anwendung einstellbar sein.

Berechnet wird **pro Arbeitsblock**, nicht pro Kalendertag – sonst bekaeme ein
zweiter langer Block keine Mindestpause.

Algorithmus je Block (nach Rundung von Kommen/Gehen):

```
pause_zwang_minuten  = Summe der Ueberlappung des Blocks mit aktiven Pausenfenstern
pause_gesetz_minuten = aus Blockdauer und konfigurierten Schwellen
pause_total_minuten  = max(pause_zwang_minuten, pause_gesetz_minuten)
ist_stunden (Block)  = (Ge.Korr − Ko.Korr) − pause_total_minuten
```

Ist die Zwangspause kleiner, wird die Differenz als **pauschale Zusatzpause**
abgezogen (ohne konkrete Uhrzeit).

`tageswerte_mitarbeiter.pause_korr_minuten` ist die **Summe** aller
Block-Pausen – oder bei Override der manuell gesetzte Wert.

Pflegbar im Backend (`konfiguration_admin` oder eigene Admin-Seite):
Pausenfenster (`von_uhrzeit`, `bis_uhrzeit`, optional `abteilung_id`, `aktiv`)
und die gesetzlichen Schwellen (Default 6 h/30 min, 9 h/45 min).

## 5. Tageswerte und Korrekturfelder

Fuer jede Kombination Mitarbeiter + Datum gibt es eine Zeile in
`tageswerte_mitarbeiter`. Ablauf je Tag: Bloecke bilden → Rundung je Block →
Pausenregeln je Block → IST je Block berechnen und aufsummieren.

Aggregierte Rohzeiten (nur fuer die schnelle Uebersicht, **nicht** Grundlage
der IST-Berechnung): `kommen_roh` = erstes Kommen, `gehen_roh` = letztes Gehen.

Korrektur- und Auswertungsfelder:

- `kommen_korr` / `gehen_korr` (aggregiert, aus erstem/letztem Block)
- `pause_korr_minuten` (Summe ueber alle Bloecke, manuell ueberschreibbar)
- `ist_stunden` (Summe IST aller Bloecke nach Rundung und Pausenabzug)
- bezahlte Abwesenheiten je Tag:
  - `arzt_stunden`
  - `krank_lfz_stunden` + `kennzeichen_krank_lfz`
  - `krank_kk_stunden` + `kennzeichen_krank_kk`
  - `feiertag_stunden` + `kennzeichen_feiertag`
  - `urlaub_stunden` + `kennzeichen_urlaub` (Betriebsferien zaehlen als Urlaub,
    i. d. R. 8,00 h/Tag)
  - `kurzarbeit_stunden` + `kennzeichen_kurzarbeit`
  - `sonstige_stunden` + `kennzeichen_sonstiges` (reine Stundenzahl; Kuerzel
    bzw. Begruendung steht im Feld `kommentar`, z. B. `BF`, `SoU`,
    `SoU: <Text>`)
  - **Geplant:** `sonderurlaub_stunden` + `kennzeichen_sonderurlaub` +
    `sonderurlaub_begruendung` (Migration noetig; bis dahin ueber
    `sonstige_stunden` abbilden)

### Fachregeln zu den Abwesenheiten

**Krank LFZ vs. Krank KK**

- LFZ = Lohnfortzahlung (typisch erste 6 Wochen), KK = Krankenkasse (danach).
- Pflegbar **pro Mitarbeiter** als Zeitraum/Krankfall (Backend-Maske) und
  zusaetzlich als Tages-Override.
- Pro Tag darf nur **eine** Variante aktiv sein, nie beide.
- Die Anwendung darf den Wechsel nach 6 Wochen **vorschlagen**; die Umschaltung
  bleibt **manuell**.

**Kurzarbeit**

- Eigene Spalte im PDF (`kurzarbeit_stunden`).
- Pflegbar als Zeitraum-Plan (firmenweit oder mitarbeiterbezogen; einzelne Tage,
  Woche, ganzer Monat) und als Tages-Override in der Korrekturmaske.
- **Kurzarbeit reduziert das Tages-Soll** (keine Minusstunden durch Kurzarbeit);
  die Stunden zaehlen **nicht** als IST.

**Sonderurlaub (SoU)**

- Wird als *Sonstiges* gebucht: `sonstige_stunden` (z. B. 8,00) +
  `kennzeichen_sonstiges = 1`.
- Kuerzel/Grund im Feld `kommentar` (`SoU` bzw. `SoU: <Begruendung>`).
- In der Tagesmaske gibt es einen Schnell-Haken (setzt Default-Stunden auf 8,00
  bzw. Tages-Soll; Begruendung optional oder Pflicht je Konfiguration).

### Flags „manuell geaendert" (fuer die PDF-Markierung)

- `zeitbuchung.manuell_geaendert` – pro Rohbuchung
- `tageswerte_mitarbeiter.rohdaten_manuell_geaendert` – wenn an Rohbuchungen
  des Tages gearbeitet wurde
- `tageswerte_mitarbeiter.felder_manuell_geaendert` – wenn Tagesfelder
  (Pause, Abwesenheiten) manuell gesetzt oder ueberschrieben wurden

## 6. Backend-Korrekturmaske

Berechtigte (z. B. `Chef`, `Personalbuero`, Rollen mit Edit-Recht) koennen je
Mitarbeiter und Datum:

1. die aggregierten Rohzeiten einsehen,
2. die zugrunde liegenden `zeitbuchung`-Eintraege sehen,
3. Eintraege hinzufuegen, aendern, loeschen.

Zusaetzlich gibt es **Tages-Checkboxen mit Stundenfeldern**:

- `Urlaub` (Default 8,00 bzw. Tages-Soll; Kuerzel/Grund optional in `kommentar`)
- `Kurzarbeit` (Default aus dem Kurzarbeit-Plan, falls vorhanden)
- `Krank LFZ` / `Krank KK` – **gegenseitig ausschliessend**, Umschaltung manuell
- `Sonstiges` (Stundenfeld) + Auswahl Grund (konfigurierbar, z. B. `SoU`) +
  Begruendung → `sonstige_stunden` + `kennzeichen_sonstiges = 1`, Kuerzel in
  `kommentar`
- optional Arzt / Feiertag, je nach Berechtigung

**Betriebsferien und Feiertage** werden aus dem Firmenkalender automatisch
gesetzt. Betriebsferien gelten als Urlaub (`BF`) und sind **kein** klickbarer
Tages-Haken; in der Tagesmaske erscheinen sie nur als Info/Badge (optional mit
Admin-Ausnahme).

Aenderungen an Rohbuchungen setzen `zeitbuchung.manuell_geaendert = 1` (im PDF
rot markiert). Aenderungen an Tagesfeldern setzen
`felder_manuell_geaendert = 1` (ebenfalls rot) und **muessen mit Begruendung
geloggt werden**.

### Zeitraum-Assistenten (Routineformulare)

**Kurzarbeit-Plan** (firmenweit oder mitarbeiterbezogen): Zeitraum
`von_datum`–`bis_datum` (optional Wochentage), Modus `stunden` oder `prozent`,
Wert (z. B. 4,00 h oder 50 %), Kommentar. Der Recalc-Service wendet den Plan auf
Tage **ohne** Tages-Override an und befuellt `kurzarbeit_stunden` /
`kennzeichen_kurzarbeit`.

**Krankheits-Zeitraeume (LFZ/KK) pro Mitarbeiter:** Zeitraum, Phase `LFZ` oder
`KK` (bei Wechsel zwei Zeitraeume), Stunden pro Tag (Default 8,00 bzw.
Tages-Soll), optionaler Vorschlag „Wechsel nach 6 Wochen" (endgueltig manuell).
Der Recalc-Service befuellt je Tag entweder `krank_lfz_*` oder `krank_kk_*`.

**Sonstiges-Zeitraeume** (z. B. Sonderurlaub): Zeitraum + Grund-Code aus der
Konfiguration + Default-Stunden → schreibt `sonstige_stunden`,
`kennzeichen_sonstiges` und `kommentar`.

### Konfiguration „Sonstiges-Gruende"

Damit spaeter weitere Faelle ohne Codeaenderung dazukommen, gibt es eine
konfigurierbare Liste (Tabelle z. B. `sonstiges_grund`): `code` (kurz, z. B.
`SoU`), `titel`, `default_stunden`, `requires_begruendung` (0/1), `aktiv` (0/1),
Sortierung. Sie steuert die Schnell-Haken und Dropdowns in der Tagesmaske und
die Default-Befuellung von `sonstige_stunden` + `kommentar`.

## 7. Audit-Trail bei manuellen Korrekturen (Pflicht)

Rohdaten bleiben grundsaetzlich erhalten. Jede manuelle Korrektur muss
**auditierbar** sein:

- wer geaendert hat (Mitarbeiter-ID),
- wann,
- welche Felder (alt → neu),
- **Begruendung als Pflichtfeld**.

UI und PDF muessen Korrekturen markieren (z. B. Sternchen oder Label
„korrigiert") und die Aenderungsinfo abrufbar machen.

Empfohlenes DB-Design: `zeit_korrektur_log` mit `id`, `mitarbeiter_id`
(betroffen), `datum`, `feld`, `wert_alt`, `wert_neu`,
`geaendert_von_mitarbeiter_id`, `begruendung`, `geaendert_am` – alternativ
feldbasiert pro `zeitbuchung_id`.

`Ko.Korr` und `Ge.Korr` werden **nicht direkt** von Hand geaendert, sondern nur
ueber die Rundung und ueber Aenderungen an den Rohdaten beeinflusst.
