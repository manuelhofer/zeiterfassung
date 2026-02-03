# REPORT/MEHRFACH-BLOECKE PROMPT v1 – Mikro-Buchungen, PDF-Filter, IST/Pause Anzeige

*Version:* v1 (2026-01-20)
*Status:* Spezifikation + Fix-Plan (Micro-Patches). Umsetzung nur in kleinen, isolierten Patches (max. 3 Dateien) ohne Regressionen.

## 0. Zielbild

- Monatsuebersicht + PDF zeigen **Mehrfach-Arbeitsbloecke pro Tag** korrekt.
- **Mikro-Buchungen** (sehr kurze Arbeitsbloecke) werden **standardmaessig NICHT angezeigt** (und nicht gewertet).
- Aktiviert man „Mikro-Buchungen anzeigen“ (Backend) bzw. `?show_micro=1` (PDF), werden sie sichtbar.
- **IST-Zeit** wird als **Summe der echten Bloecke** berechnet (nicht Min/Max-Klammerung).
- Anzeige von **Pause / IST** wirkt logisch auch wenn der erste Block sehr kurz ist.

WICHTIG:
- Bestehende Funktionen duerfen nicht entfernt oder still veraendert werden.
- Jede Aenderung muss bestehende Tests/Flows weiter funktionieren lassen.

---

## 1. Begriffe + Konfiguration

### 1.1 Mikro-Buchung

Ein Arbeitsblock (Kommen->Gehen) gilt als Mikro-Buchung, wenn seine Dauer <= Grenzwert ist.

- Konfiguration: `micro_buchung_max_sekunden` (Default 180 Sekunden = 3 Minuten)
- Konsequenz: z. B. **10:16–10:19** (= 3:00) ist Mikro, **11:01–11:05** (= 4:00) ist **keine** Mikro-Buchung.

### 1.2 Arbeitsblock / Mehrfachblöcke

Ein Tag kann mehrere Kommen/Gehen-Paare haben. Im Report werden diese als Zeilen unter dem gleichen Datum angezeigt.

---

## 2. Ist-Probleme (Praxis)

1) **PDF zeigt Mikro-Buchungen trotz „Mikro-Buchungen anzeigen“ = AUS**
   - In der Monatsuebersicht sind Mikro-Bloecke korrekt ausgeblendet.
   - In der PDF tauchen sie dennoch auf.

2) **Unterschied „10:16–10:19“ vs „11:01–11:05“**
   - Im Backend wird 10:16–10:19 als Mikro erkannt, 11:01–11:05 nicht.
   - Ursache: Grenzwert = 180s. 3 Minuten (<=180) ist Mikro, 4 Minuten ist nicht Mikro.

3) **IST / Pause nur in der ersten Zeile**
   - Bei mehreren Bloecken steht in den Folgezeilen kein IST.
   - Pause wird immer in der ersten Zeile angezeigt – auch wenn der erste Block nur ein kurzer „Randblock“ ist.

---

## 3. Technische Ursache (warum die PDF falsch filtert)

- In `ReportService` werden pro Block sowohl Roh- als auch Rundungszeiten (`*_roh`, `*_korr`) erzeugt.
- Bei kurzen Bloecken kann Rundung dazu fuehren, dass:
  - Kommen_korr spaeter gerundet wird (z. B. 10:16 -> 10:30)
  - Gehen_korr frueher gerundet wird (z. B. 10:19 -> 10:15)
  - Ergebnis: **Ende < Start**
- Die PDF-Mikro-Erkennung prueft aktuell bevorzugt `*_korr` und verwirft bei „Ende < Start“ den Block-Diff (NULL) -> Block wird **nicht** als Mikro erkannt und bleibt sichtbar.
- Die Monatsuebersicht nutzt hingegen eine Mikro-Erkennung, die **primaer auf Rohstempel** basiert (und deshalb korrekt ist).

---

## 4. Fix-Strategie (kleine Micro-Patches)

### Patch-P1: PDF Mikro-Filter an Monatsuebersicht angleichen

**Ziel:** Ohne `show_micro` duerfen Mikro-Bloecke in der PDF nicht erscheinen.

**Aenderung:**
- Mikro-Erkennung in `PDFService` an die Logik aus `views/report/monatsuebersicht.php::report_is_micro_block()` angleichen:
  - Zuerst Rohstempel diffen (wenn vorhanden)
  - Diff mit `abs()` (tolerant gegen Rundungs-Ruecksprung)
  - Nur wenn Roh nicht vorhanden: Fallback auf Main (korr/roh)

**Dateien (max 3):**
- `services/PDFService.php`
- `docs/DEV_PROMPT_HISTORY.md`

**Akzeptanz:**
- Wenn `show_micro` **nicht** gesetzt ist, sind in der PDF keine Bloecke <= Grenzwert sichtbar.
- Wenn `show_micro=1`, sind sie sichtbar.
- Monatsuebersicht und PDF verhalten sich bzgl. Mikro identisch.

---

### Patch-P2: IST je Block anzeigen (ohne Summen kaputt zu machen)

**Ziel:** In Mehrfach-Ansicht sollen Folgezeilen nicht „leer“ wirken.

**Vorschlag (Default):**
- In **jeder Block-Zeile** wird im IST-Feld die **Block-Dauer** angezeigt.
- In der **ersten Zeile** bleibt (zusaetzlich) die **Tages-Gesamtsumme** sichtbar, ohne neue Spalten:
  - Variante A (minimal): Erste Zeile zeigt Tages-IST wie bisher, Folgezeilen zeigen Block-IST.
  - Variante B (klarer): Erste Zeile zeigt Block-IST; Tages-IST kommt zusaetzlich als kleine Zeile/Footnote im Tageskopf (nur falls Platz).

**Dateien (max 3):**
- `views/report/monatsuebersicht.php` (Backend)
- `services/PDFService.php` (PDF)
- `docs/DEV_PROMPT_HISTORY.md`

**Akzeptanz:**
- Pro sichtbarem Block steht eine IST-Zeit.
- Tages-Summe bleibt korrekt (Monatssummen unveraendert korrekt).

---

### Patch-P3: Pause/Meta logisch platzieren (optional, wenn nach P1/P2 noch stoert)

**Problem:** Pause erscheint aktuell immer in der ersten Block-Zeile.

**Vorschlag:**
- Definiere eine „Primaer-Zeile“ pro Tag fuer Meta-Felder (Pause, Kurzarbeit, Feiertag/Urlaub-Felder):
  - primaer = **erste sichtbare Block-Zeile mit Dauer >= X Minuten** (Default X=60), sonst erste sichtbare.
- Meta-Felder nur dort anzeigen.

**Dateien (max 3):**
- `views/report/monatsuebersicht.php`
- `services/PDFService.php`
- `docs/DEV_PROMPT_HISTORY.md`

**Akzeptanz:**
- Pause steht nicht an einem 15-Minuten-Randblock, sondern beim „Hauptblock“.

---

## 5. Smoke-Tests (nach jedem Patch)

1) Monatsuebersicht: `?seite=report_monat&jahr=YYYY&monat=MM&mitarbeiter_id=...`
   - Checkbox Mikro aus/an -> Verhalten korrekt.
2) PDF Export aus Monatsuebersicht
   - Ohne Mikro: keine Mikro-Zeilen sichtbar
   - Mit Mikro: Mikro-Zeilen sichtbar
3) Ein normaler Tag mit nur einem Block bleibt unveraendert.

