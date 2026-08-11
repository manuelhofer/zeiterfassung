# Spezifikation: Urlaubsgenehmigung je Abteilung (B-093)

Zielbild und Akzeptanzkriterien für den ersten – und vorerst einzigen –
Anwendungsfall abteilungsbezogener Rollen. Verlangt von
[`arbeitsregeln.md`](arbeitsregeln.md) §1: Neue Funktionsbereiche werden
spezifiziert, bevor sie gebaut werden.

**Fachlicher Hintergrund:** [`fachregeln/rollen_rechte_genehmiger.md`](fachregeln/rollen_rechte_genehmiger.md),
Abschnitte 3 und 4. **Ausgangslage:** B-093 im
[Status-Snapshot](STATUS_SNAPSHOT.md).

---

## 1. Warum nur die Urlaubsgenehmigung

Die Fachregel beschreibt ein allgemeines Bereichsmodell: Jede Rolle soll auf
eine Abteilung eingegrenzt werden können. Gebaut wird davon jetzt **ein
Ausschnitt**, und zwar bewusst.

Ein allgemeines Modell hiesse: `hatRecht()` bekommt ein Ziel, und jede der 84
Prüfstellen im Projekt muss entscheiden, worauf sie sich bezieht – dazu
Datenfilterung in Listen, Auswertungen und PDF. Das ist ein Umbau am
Rechtesystem eines Systems, das im Praxis-Test läuft.

Der Bedarf dahinter ist dagegen schmal und konkret: **Ein Schichtleiter soll
den Urlaub seiner Abteilung genehmigen, ohne dass jeder Mitarbeiter einzeln als
sein „Genehmigter" eingetragen werden muss.** Genau das wird gebaut. Alle
anderen Rechte bleiben global – auch dann, wenn die Rolle mit Abteilungsbezug
zugewiesen ist.

## 2. Zielbild

Eine Rolle, die `URLAUB_GENEHMIGEN` enthält, kann einem Mitarbeiter **mit
Abteilungsbezug** zugewiesen werden (`mitarbeiter_hat_rolle_scope`,
`scope_typ = 'abteilung'`). Dann gilt:

- Er darf Urlaubsanträge der Mitarbeiter **dieser Abteilung** entscheiden.
- Mit `gilt_unterbereiche = 1` zusätzlich die des gesamten Unterbaums
  (`abteilung.parent_id`).
- Er sieht in der Genehmigungsliste und in der Jahresübersicht genau diese
  Mitarbeiter – nicht mehr.

Die Zugehörigkeit eines Mitarbeiters zu einer Abteilung kommt aus
`mitarbeiter_hat_abteilung` (M:N). `ist_stammabteilung` spielt **keine** Rolle:
Wer einer Abteilung zugeordnet ist, gehört für die Genehmigung dazu.

## 3. Was sich nicht ändert

- **`hatRecht($code)` behält seine Signatur.** Kein Ziel, kein zweiter
  Parameter. Wer die Rolle mit Abteilungsbezug hat, **hat** das Recht
  `URLAUB_GENEHMIGEN` – begrenzt wird nicht das Recht, sondern die Menge der
  Mitarbeiter, auf die es sich anwenden lässt.
- **Alle übrigen Rechte einer abteilungsbezogen zugewiesenen Rolle gelten
  global.** Das ist eine bewusste Vereinfachung und der Grund, warum Abschnitt 5
  eine Warnung in der Maske verlangt.
- **`mitarbeiter_genehmiger` bleibt.** Die namentliche Zuordnung ist der
  Sonderfall (Vertretung, abteilungsübergreifend), der Abteilungsbezug der
  Regelfall. Beide Wege gelten **additiv**: Wer über einen von beiden
  zuständig ist, darf entscheiden.
- **`URLAUB_GENEHMIGEN_ALLE` und `_SELF` bleiben unberührt.** `ALLE` sticht
  jede Eingrenzung.

## 4. Eine Stelle, nicht vier

Heute beantworten vier Stellen die Frage „für wen bin ich zuständig", jede mit
eigener SQL:

| Stelle | heute |
| --- | --- |
| `UrlaubController::darfUrlaubsantragBearbeiten()` | `SELECT 1 FROM mitarbeiter_genehmiger` |
| `UrlaubController::genehmigungListe()`, Zugangsprüfung | dieselbe Abfrage ohne Zielmitarbeiter |
| `UrlaubController::genehmigungListe()`, Listenabfrage | `JOIN mitarbeiter_genehmiger`, zwei Varianten |
| `UrlaubJahresuebersichtController::ladeMitarbeiterFuerRechte()` | `MitarbeiterGenehmigerModel` |

Diese vier bekommen **eine** gemeinsame Auskunft:

```php
UrlaubGenehmigungService::holeZustaendigeMitarbeiterIds(int $genehmigerId): array
UrlaubGenehmigungService::istZustaendigFuer(int $genehmigerId, int $mitarbeiterId): bool
```

Die Menge ist die Vereinigung aus:

1. `mitarbeiter_genehmiger` (namentlich eingetragen), und
2. allen Mitarbeitern der Abteilungen, in denen der Genehmiger eine Rolle mit
   `URLAUB_GENEHMIGEN` und `scope_typ = 'abteilung'` hat – bei
   `gilt_unterbereiche = 1` einschliesslich Unterbaum.

Der Genehmiger selbst ist **nicht** enthalten; eigene Anträge hängen
ausschliesslich an `URLAUB_GENEHMIGEN_SELF`.

## 5. Maske

Die Sperre aus P-2026-08-10-25 (`MitarbeiterAdminController::SCOPE_ABTEILUNG_AKTIV`)
fällt. Der Abschnitt „Rollen in Abteilungen" erklärt dann klar, was er bewirkt –
und was nicht:

> Wirkt heute nur auf die **Urlaubsgenehmigung**: Der Mitarbeiter darf Urlaub
> für diese Abteilung entscheiden. Alle anderen Rechte der Rolle gelten
> weiterhin **betriebsweit**.

Ohne diesen Satz entsteht wieder der Zustand, den P-2026-08-10-25 beseitigt hat:
eine Maske, die mehr verspricht, als sie hält.

## 6. Akzeptanzkriterien

Prüfbar an einer Wegwerf-Datenbank mit erfundenen Mitarbeitern; Aufbau wie in
P-2026-08-11-02 beschrieben.

1. **Zuständig über die Abteilung.** Genehmiger G hat die Rolle
   „Schichtleiter" (enthält `URLAUB_GENEHMIGEN`) mit
   `scope_typ = 'abteilung'`, `scope_id = CNC`, `gilt_unterbereiche = 0`.
   Mitarbeiter A ist in CNC. G sieht A's offenen Antrag in der Liste und kann
   ihn genehmigen – **ohne** Eintrag in `mitarbeiter_genehmiger`.
2. **Nicht zuständig ausserhalb.** Mitarbeiter B ist in Montage. G sieht B's
   Antrag nicht, und ein von Hand gebauter POST auf B's Antrag wird abgewiesen.
3. **Unterbereiche.** Mit `gilt_unterbereiche = 1` und CNC als Elternabteilung
   von „CNC Nachtschicht" sieht G auch deren Mitarbeiter C. Mit `0` nicht.
4. **Additiv.** Ein zusätzlicher Eintrag in `mitarbeiter_genehmiger` für B
   macht B zusätzlich sichtbar, ohne A oder C zu verlieren.
5. **Kein Selbsteintritt.** G sieht seinen eigenen Antrag nur mit
   `URLAUB_GENEHMIGEN_SELF`.
6. **Global sticht.** `URLAUB_GENEHMIGEN_ALLE` zeigt weiterhin alle Anträge,
   unabhängig von jeder Abteilung.
7. **Kein Rechtezuwachs anderswo.** Eine Rolle mit Abteilungsbezug, die
   ausserdem `MITARBEITER_VERWALTEN` enthält, gewährt dieses Recht wie bisher
   betriebsweit – die Änderung fügt kein Recht hinzu und nimmt keines weg.

## 7. Was ausdrücklich nicht dazugehört

- **Kein Zyklenschutz „von Hand".** `abteilung.parent_id` hat einen
  Fremdschlüssel auf dieselbe Tabelle; eine Schleife ist trotzdem eintragbar.
  Das Traversieren begrenzt sich deshalb über eine Besuchsliste, aber es wird
  keine Prüfung beim Speichern einer Abteilung ergänzt – eigenes Thema.
- **Keine Auswertungen, kein PDF.** Monatsübersicht, Stundenkonto und Reports
  bleiben unverändert; wer sie sehen darf, sieht sie ganz.
- **Keine Terminal-Auswirkung.**
- **Keine Migration.** Die Tabelle und ihre Spalten existieren seit
  `sql/01_initial_schema.sql`; es entstehen keine neuen Felder.
