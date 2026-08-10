# Fachregeln: Urlaub, Betriebsferien, Feiertage

*Gilt für:* `services/UrlaubService.php`, `services/FeiertagService.php`,
Urlaubsmasken in Backend und Terminal.
*Herkunft:* Master-Prompt v13, Abschnitte 12, 13, 14 sowie v4-Abschnitt B.

---

## 1. Urlaub beantragen (Terminal)

Der Knopf „Urlaub beantragen" zeigt die verfügbaren Urlaubstage, berechnet aus
Jahresanspruch, bereits genehmigten Anträgen, Betriebsferien und Feiertagen –
inklusive **Übertrag aus dem Vorjahr** und Verbrauchsreihenfolge (siehe 3).

Touch-Maske: Von- und Bis-Datum über Pfeiltasten für Tag/Monat/Jahr.

Validierungen: Datum existiert, Von ≤ Bis, Zeitraum im aktuellen oder
Folgejahr. Gespeichert wird ein Datensatz in `urlaubsantrag` mit Status
`offen`.

Ein Antrag, der **0,00 verrechenbare Urlaubstage** ergibt (z. B. komplett
Wochenende, Feiertag oder Betriebsferien), wird abgelehnt – sonst entstehen
verwirrende Einträge in „Meine Urlaubsanträge" (B-075).

## 2. Übersicht für Mitarbeiter

Der Knopf „Übersicht" zeigt:

- verbleibende Urlaubstage,
- alle Urlaubsanträge (offen / genehmigt / abgelehnt),
- Betriebsferien,
- aktuellen Stand Über-/Minusstunden,
- Rest-Sollstunden des Monats.

Grundlage: Rohdaten + Rundungsregeln, `tageswerte_mitarbeiter`,
`monatswerte_mitarbeiter`, Feiertags- und Betriebsferienlogik.

## 3. Urlaubssaldo, Übertrag und Verbrauchsreihenfolge

**Übertrag Vorjahr:** Der Rest aus dem Vorjahr (YYYY-1) wird als „Übertrag" ins
aktuelle Jahr übernommen – auch ein **negativer** Rest, siehe unten.

**Gerechnet wird nur über zwei Jahre: laufendes Jahr und Vorjahr.** Weiter
zurück nicht. Resturlaub verfällt, und vor Einführung dieses Systems wurde
Urlaub anderswo geführt; eine unbegrenzte Kette summierte für jedes Jahr ohne
erfassten Urlaub den vollen Jahresanspruch auf.

**Der Übertrag wird festgeschrieben.** Sobald er für ein Jahr berechnet ist,
steht er in `urlaub_kontingent_jahr.uebertrag_tage`, und
`uebertrag_festgeschrieben_am` hält fest, wann. Ein festgeschriebener Wert
gewinnt danach immer – auch gegen die Neuberechnung. Zwei Gründe:

1. **Die Zahl steht fest.** Ein Übertrag, der bei jeder Anzeige neu hergeleitet
   wird, ändert sich, sobald sich irgendetwas im Vorjahr ändert – und niemand
   kann später sagen, welche Zahl damals galt.
2. **Das Fenster darf wandern.** Fällt ein Jahr aus den zwei gerechneten
   Jahren heraus, gilt sein festgeschriebener Wert weiter. Das Fenster bestimmt
   nur, was **neu gerechnet** wird.

Verschieben lässt sich der Schnitt über `urlaub_uebertrag_ab_jahr` in der
Tabelle `config` – etwa um beim Einführen einmalig weiter zurückzurechnen. Ohne
Eintrag gilt das Vorjahr. Zusätzlicher Boden ist immer das Eintrittsjahr.

**Wo der Resturlaub sichtbar ist** (überall dieselbe Zahl aus
`berechneUrlaubssaldoFuerJahr()`):

| Maske | was sie zeigt |
| --- | --- |
| Meine Urlaubsanträge | eigener Saldo, aufgeschlüsselt nach Übertrag und Jahr |
| Urlaub-Genehmigungsliste | je Antrag `verfügbar vor → nach Genehmigung`, mit Warnung bei negativ |
| Urlaubsverwaltung | Spalte *Übrig* je Zeile, und der Resturlaub hinter jedem Namen in der Auswahlliste für „Urlaub direkt eintragen" |
| Urlaubskontingent pro Jahr | Übertrag, Verbraucht und Übrig für alle Mitarbeiter auf einen Blick |

In **Verbraucht** stecken die Betriebsferien bereits drin – „Übrig" ist also
das, was der Mitarbeiter tatsächlich noch nehmen kann.

**Korrigieren:** In der Kontingentverwaltung (Backend) steht der Übertrag als
eigenes Feld, zusammen mit dem Zeitpunkt der Festschreibung. Wer den Wert
ändert und speichert, schreibt ihn damit fest; das Kästchen „neu berechnen
lassen" gibt ihn wieder frei, und beim nächsten Aufruf entsteht er neu aus dem
Vorjahr.

Dass Anzeige und Übernahme früher **verschiedene** Regeln benutzten, war
B-080: Die Maske für 2025 zeigte 25,00 Tage Rest, die Maske für 2026 übernahm
daraus −5,00. Behoben in P-2026-08-10-28.

**Verbrauchsreihenfolge (Pflicht):**

1. zuerst der **Übertrag** (aeltester Rest zuerst),
2. danach das **Kontingent des aktuellen Jahres**.

**Anzeige** (Terminal und Übersicht): aufgeschluesselt nach
`Uebertrag (YYYY-1)` und `Jahr YYYY`.

**Negativer Rest wird nicht gekappt.** Minusurlaub muss sich im Folgejahr
ausgleichen können (B-082).

**Anteiliger Anspruch:** Bei Eintritt oder Anlage im laufenden Jahr wird
anteilig gerechnet, nicht der volle Jahresanspruch (B-082).

**Fehlendes Kontingent:** Ist `mitarbeiter.urlaub_monatsanspruch` = 0,00, greift
ein Standardanspruch (`config:urlaub_standard_monatsanspruch`, sonst
`config:urlaub_standard_jahresanspruch`, sonst Fallback 2,50 = 30 Tage/Jahr)
plus Hinweistext. Ohne diesen Rückfall werden die Werte durch den
Betriebsferien-Abzug unplausibel negativ.

**Halbe Tage:** Heiligabend (24.12.) und Silvester (31.12.) zählen als
**0,5 Urlaubstage** – sowohl bei Betriebsferien als auch bei Urlaubsanträgen.

## 4. Genehmigung

Der Knopf „Urlaubsanträge" (Terminal und/oder Backend, nur mit passendem
Recht sichtbar) zeigt alle Anträge, die dieser Benutzer genehmigen darf:

- laut `mitarbeiter_genehmiger`,
- plus **alle**, wenn der Benutzer die Rolle `Chef` hat (globale Sicht).

Darstellung: offen / genehmigt / abgelehnt, Filter, Hinweise zu
Überschneidungen.

Aktionen:

- **Genehmigen** → Status `genehmigt`, `entscheidungs_mitarbeiter_id` = aktueller
  Benutzer, `entscheidungs_datum` = jetzt.
- **Ablehnen** → Status `abgelehnt`, Kommentar optional, dieselben
  Entscheidungsfelder setzen.

**Eigene Anträge:** Standardmäßig darf niemand seine eigenen Anträge
genehmigen. Ausnahme: Benutzer mit `URLAUB_GENEHMIGEN_SELF` (typisch `Chef`,
`Personalbuero`). Dann dürfen Genehmigungslisten auch eigene offene Anträge
enthalten.

**Sichtbarer Hinweis:** Gibt es offene Anträge, wird der Knopf
„Urlaubsanträge" deutlich hervorgehoben (z. B. rot, blinkend).

## 5. Betriebsferien

Tabelle `betriebsferien`: `von_datum`, `bis_datum`, `beschreibung`, optional
`abteilung_id`, Timestamps. Im Backend anlegen, bearbeiten, löschen; Anzeige in
Urlaubsansicht und Monatswerten.

**Betriebsferien gelten als Urlaub (Zwangsurlaub):**

- Sie sind **firmenweit** und werden automatisch gesetzt; in der Tagesmaske nur
  als Info/Badge sichtbar (optional Admin-Ausnahme).
- Pro **Arbeitstag** werden **8,00 Stunden Urlaub** ausgewiesen (Spalte
  „Urlaub" in Arbeitszeitliste und PDF).
- Feiertage und Wochenenden innerhalb eines Betriebsferien-Zeitraums bleiben
  Feiertag bzw. Wochenende und zählen **nicht** als Urlaub.
- Betriebsferien reduzieren das **Soll nicht** (wie ein normaler Arbeitstag);
  die Stunden laufen über „Urlaub".
- Im Urlaubssaldo werden sie als **genommener Urlaub** beruecksichtigt (nur
  Arbeitstage). Urlaubsanträge zählen diese Tage **nicht doppelt**.

**Abgrenzung – wann Betriebsferien *nicht* als Urlaub zählen:**

- Wenn an dem Tag tatsaechlich gearbeitet wurde (B-024, B-025),
- wenn bereits ein anderes Kennzeichen gesetzt ist (z. B. krank),
- wenn ein aktiver Krankzeitraum (LFZ/KK) den Tag umfasst – **Krank hat Vorrang
  vor Betriebsferien** (B-076, B-077): kein BF-Kürzel, Urlaub 0, Krank 8,00.

Der Abzug im Saldo nutzt dieselbe zentrale BF-Zähllogik wie die Anzeige, sonst
driften beide auseinander (B-080, P-2026-01-23-02).

## 6. Feiertage

Tabelle `feiertag`: `datum`, `name`, optional `bundesland`, `ist_gesetzlich`,
`ist_betriebsfrei`, Timestamps.

Ein Service generiert die gesetzlichen deutschen Feiertage jaehrlich. Das
Backend erlaubt manuelle Korrektur, Ergänzung und Anpassung des
Betriebsfrei-Flags.

**Idempotentes Nachseeden:** Ein Jahr gilt **nicht** schon dann als fertig, wenn
irgendein Feiertag dafür existiert. Fehlende bundeseinheitliche Feiertage
werden nachtraeglich ergänzt – sonst fehlt z. B. der 01.01. unbemerkt (B-071).

**Im Monatsreport:** Kalender-Feiertage werden in der Tagesliste als Feiertag
geführt und bei **keiner** Arbeitszeit mit Tagesstunden befüllt (Fallback
8,00 bzw. Tages-Soll) – abgegrenzt gegen Urlaub und Betriebsferien (B-070).

> Warum das Terminal Feiertage schreiben darf: Der `UrlaubService` generiert die
> Feiertage eines Jahres bei Bedarf nach. Ohne `INSERT`-Recht rechnet ein
> Terminal im Januar still ohne die Feiertage des neuen Jahres – und das fällt
> niemandem auf. Siehe [terminal_und_offline.md](terminal_und_offline.md).
