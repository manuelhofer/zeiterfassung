# Fachregeln: Kommen/Gehen, Rundung, Pausen, Tageskorrekturen

*Gilt für:* `services/ZeitService.php`, `services/RundungsService.php`,
Tageswerte, Korrekturmaske im Backend.
*Herkunft:* Master-Prompt v13, Abschnitte 9 und 10.1/10.2 sowie v4-Abschnitt D.

---

## 1. Rohdaten werden nie gerundet

Alle Kommen/Gehen-Zeitstempel werden **sekundengenau** gespeichert – ohne
Rundung. Tabelle `zeitbuchung`: `mitarbeiter_id`, `typ` (`kommen`/`gehen`),
`zeitstempel`, `quelle` (`terminal`/`web`), `manuell_geaendert`, optional
`kommentar`.

Rohdaten bleiben unverändert, außer ein Berechtigter korrigiert sie im
Backend bewusst (z. B. vergessene Buchung nachtragen). Dann wird
`manuell_geaendert` gesetzt und optional ein Kommentar gespeichert.

Die Rundung greift **erst** in der Auswertung: Tages- und Monatsberechnung,
Korrekturwerte `Ko.Korr` und `Ge.Korr`.

> Das ist die wichtigste Regel des ganzen Systems. Wer sie bricht, verliert die
> Nachvollziehbarkeit: Aus einer gerundeten Zahl lässt sich die Rohzeit nicht
> zurückrechnen.

## 2. Rundungsregeln

Beispiel der betrieblichen Regel:

- Ankunft 05:02 → bezahlte Zeit ab 05:30
- bis 07:00 Uhr auf 30-Minuten-Raster
- ab 07:00 Uhr auf 15-Minuten-Raster
- Gehen 15:14 → 15:00

Umsetzung über Tabelle `zeit_rundungsregel`: `von_uhrzeit`, `bis_uhrzeit`,
`einheit_minuten`, `richtung` (`auf` | `ab` | `naechstgelegen`), `gilt_fuer`
(`kommen` | `gehen` | `beide`), `prioritaet`.

Der `RundungsService` berechnet daraus `kommen_korr`, `gehen_korr` und die
resultierenden `ist_stunden`.

## 3. Mehrfach Kommen/Gehen an einem Tag

Ein Mitarbeiter darf an einem Tag mehrfach kommen und gehen (z. B. 05:00–08:00
und 09:00–16:00). Aus den Rohdaten werden **Arbeitsblöcke** gebildet: je ein
`kommen` mit dem nächsten `gehen`.

Paarungslogik (robust, ohne Absturz):

- Buchungen des Tages nach `zeitstempel` aufsteigend sortieren,
- beim ersten `kommen` einen Block öffnen,
- beim nächsten `gehen` den Block schließen,
- ein `gehen` ohne offenes `kommen` gilt als **verwaist**: im Backend als
  Fehler anzeigen, **nicht** ins IST einrechnen,
- ein offenes `kommen` ohne `gehen` bleibt **offen**: Fehlerhinweis im Backend,
  nicht als voller Block rechnen.

In Auswertung und PDF wird **jeder Block als eigene Zeile** ausgegeben, auch
bei gleichem Datum. Tages- und Monatssummen sind die Summe aller gültigen
Blöcke nach Rundung und Pausenabzug.

Beispiel:

- 22.02.2025 05:00–08:00 → 3,00 h
- 22.02.2025 09:00–16:00 → 7,00 h minus Pause (z. B. 45 min) → 6,25 h

## 4. Pausen

Zwei Arten, beide **konfigurierbar**:

1. **Zwangspausen** (betrieblich, Uhrzeitfenster): feste Fenster wie 09:00–09:15
   und 12:30–13:00. Diese Zeit zählt nicht als Arbeitszeit, **wenn** ein
   Arbeitsblock sie überlappt.
2. **Gesetzliche Mindestpause** (pauschal, schwellenbasiert), Standard nach
   § 4 ArbZG:
   - Arbeitszeit **> 6 h bis 9 h** → mindestens **30 Minuten**
   - Arbeitszeit **> 9 h** → mindestens **45 Minuten**

   Schwellen und Minuten müssen in der Anwendung einstellbar sein.

Berechnet wird **pro Arbeitsblock**, nicht pro Kalendertag – sonst bekäme ein
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

Für jede Kombination Mitarbeiter + Datum gibt es eine Zeile in
`tageswerte_mitarbeiter`. Ablauf je Tag: Blöcke bilden → Rundung je Block →
Pausenregeln je Block → IST je Block berechnen und aufsummieren.

Aggregierte Rohzeiten (nur für die schnelle Übersicht, **nicht** Grundlage
der IST-Berechnung): `kommen_roh` = erstes Kommen, `gehen_roh` = letztes Gehen.

Korrektur- und Auswertungsfelder:

- `kommen_korr` / `gehen_korr` (aggregiert, aus erstem/letztem Block)
- `pause_korr_minuten` (Summe über alle Blöcke, manuell überschreibbar)
- `ist_stunden` (Summe IST aller Blöcke nach Rundung und Pausenabzug)
- bezahlte Abwesenheiten je Tag:
  - `arzt_stunden`
  - `krank_lfz_stunden` + `kennzeichen_krank_lfz`
  - `krank_kk_stunden` + `kennzeichen_krank_kk`
  - `feiertag_stunden` + `kennzeichen_feiertag` – **nicht gespeichert:** keine
    Stelle der Anwendung schreibt diese zwei Spalten. Der Feiertag entsteht in
    `ReportService` je Anzeige aus dem Kalender (`feiertag`, bundesweit), mit
    Tagessoll als Stundenwert; hat der Tag Arbeitszeit, bleiben die
    Feiertagsstunden auf 0,00. Andere Kennzeichen (Arzt, Krank, Sonstiges)
    behalten Vorrang. Gefüllte Spalten im Bestand sind also Import- oder
    Handkorrekturdaten – genau darauf zielt die Feiertag+Arbeitszeit-Prüfung
    (P-2026-08-17-24).
  - `urlaub_stunden` + `kennzeichen_urlaub` (Betriebsferien zählen als Urlaub,
    i. d. R. 8,00 h/Tag)
  - `kurzarbeit_stunden` + `kennzeichen_kurzarbeit`
  - `sonstige_stunden` + `kennzeichen_sonstiges` (reine Stundenzahl; Kürzel
    bzw. Begründung steht im Feld `kommentar`, z. B. `BF`, `SoU`,
    `SoU: <Text>`)
  - **Geplant:** `sonderurlaub_stunden` + `kennzeichen_sonderurlaub` +
    `sonderurlaub_begruendung` (Migration nötig; bis dahin über
    `sonstige_stunden` abbilden)

### Fachregeln zu den Abwesenheiten

**Krank LFZ vs. Krank KK**

- LFZ = Lohnfortzahlung (typisch erste 6 Wochen), KK = Krankenkasse (danach).
- Pflegbar **pro Mitarbeiter** als Zeitraum/Krankfall (Backend-Maske) und
  zusätzlich als Tages-Override.
- Pro Tag darf nur **eine** Variante aktiv sein, nie beide.
- Die Anwendung darf den Wechsel nach 6 Wochen **vorschlagen**; die Umschaltung
  bleibt **manuell**.

**Kurzarbeit**

- Eigene Spalte im PDF (`kurzarbeit_stunden`).
- Pflegbar als Zeitraum-Plan (firmenweit oder mitarbeiterbezogen; einzelne Tage,
  Woche, ganzer Monat) und als Tages-Override in der Korrekturmaske.
- **Kurzarbeit reduziert das Tages-Soll** (keine Minusstunden durch Kurzarbeit);
  die Stunden zählen **nicht** als IST.

**Sonderurlaub (SoU)**

- Wird als *Sonstiges* gebucht: `sonstige_stunden` (z. B. 8,00) +
  `kennzeichen_sonstiges = 1`.
- Kürzel/Grund im Feld `kommentar` (`SoU` bzw. `SoU: <Begruendung>`).
- In der Tagesmaske gibt es einen Schnell-Haken (setzt Default-Stunden auf 8,00
  bzw. Tages-Soll; Begründung optional oder Pflicht je Konfiguration).

### Flags „manuell geändert" (für die PDF-Markierung)

- `zeitbuchung.manuell_geaendert` – pro Rohbuchung
- `tageswerte_mitarbeiter.rohdaten_manuell_geaendert` – wenn an Rohbuchungen
  des Tages gearbeitet wurde
- `tageswerte_mitarbeiter.felder_manuell_geaendert` – wenn Tagesfelder
  (Pause, Abwesenheiten) manuell gesetzt oder überschrieben wurden

## 6. Backend-Korrekturmaske

Berechtigte (z. B. `Chef`, `Personalbuero`, Rollen mit Edit-Recht) können je
Mitarbeiter und Datum:

1. die aggregierten Rohzeiten einsehen,
2. die zugrunde liegenden `zeitbuchung`-Einträge sehen,
3. Einträge hinzufügen, ändern, löschen.

Zusätzlich gibt es **Tages-Checkboxen mit Stundenfeldern**:

- `Urlaub` (Default 8,00 bzw. Tages-Soll; Kürzel/Grund optional in `kommentar`)
- `Kurzarbeit` (Default aus dem Kurzarbeit-Plan, falls vorhanden)
- `Krank LFZ` / `Krank KK` – **gegenseitig ausschließend**, Umschaltung manuell
- `Sonstiges` (Stundenfeld) + Auswahl Grund (konfigurierbar, z. B. `SoU`) +
  Begründung → `sonstige_stunden` + `kennzeichen_sonstiges = 1`, Kürzel in
  `kommentar`
- optional Arzt / Feiertag, je nach Berechtigung

**Betriebsferien und Feiertage** werden aus dem Firmenkalender automatisch
gesetzt. Betriebsferien gelten als Urlaub (`BF`) und sind **kein** klickbarer
Tages-Haken; in der Tagesmaske erscheinen sie nur als Info/Badge (optional mit
Admin-Ausnahme).

Änderungen an Rohbuchungen setzen `zeitbuchung.manuell_geaendert = 1` (im PDF
rot markiert). Änderungen an Tagesfeldern setzen
`felder_manuell_geaendert = 1` (ebenfalls rot) und **müssen mit Begründung
geloggt werden**.

### Zeitraum-Assistenten (Routineformulare)

**Kurzarbeit-Plan** (firmenweit oder mitarbeiterbezogen): Zeitraum
`von_datum`–`bis_datum` (optional Wochentage), Modus `stunden` oder `prozent`,
Wert (z. B. 4,00 h oder 50 %), Kommentar. Der Recalc-Service wendet den Plan auf
Tage **ohne** Tages-Override an und befüllt `kurzarbeit_stunden` /
`kennzeichen_kurzarbeit`.

**Krankheits-Zeiträume (LFZ/KK) pro Mitarbeiter:** Zeitraum, Phase `LFZ` oder
`KK` (bei Wechsel zwei Zeiträume), Stunden pro Tag (Default 8,00 bzw.
Tages-Soll), optionaler Vorschlag „Wechsel nach 6 Wochen" (endgültig manuell).
Der Recalc-Service befüllt je Tag entweder `krank_lfz_*` oder `krank_kk_*`.

**Sonstiges-Zeiträume** (z. B. Sonderurlaub): Zeitraum + Grund-Code aus der
Konfiguration + Default-Stunden → schreibt `sonstige_stunden`,
`kennzeichen_sonstiges` und `kommentar`.

### Konfiguration „Sonstiges-Gründe"

Damit später weitere Fälle ohne Codeänderung dazukommen, gibt es eine
konfigurierbare Liste (Tabelle z. B. `sonstiges_grund`): `code` (kurz, z. B.
`SoU`), `titel`, `default_stunden`, `requires_begruendung` (0/1), `aktiv` (0/1),
Sortierung. Sie steuert die Schnell-Haken und Dropdowns in der Tagesmaske und
die Default-Befüllung von `sonstige_stunden` + `kommentar`.

## 7. Audit-Trail bei manuellen Korrekturen (Pflicht)

Rohdaten bleiben grundsätzlich erhalten. Jede manuelle Korrektur muss
**auditierbar** sein:

- wer geändert hat (Mitarbeiter-ID),
- wann,
- welche Felder (alt → neu),
- **Begründung als Pflichtfeld**.

UI und PDF müssen Korrekturen markieren (z. B. Sternchen oder Label
„korrigiert") und die Änderungsinfo abrufbar machen.

Empfohlenes DB-Design: `zeit_korrektur_log` mit `id`, `mitarbeiter_id`
(betroffen), `datum`, `feld`, `wert_alt`, `wert_neu`,
`geaendert_von_mitarbeiter_id`, `begruendung`, `geaendert_am` – alternativ
feldbasiert pro `zeitbuchung_id`.

`Ko.Korr` und `Ge.Korr` werden **nicht direkt** von Hand geändert, sondern nur
über die Rundung und über Änderungen an den Rohdaten beeinflusst.
