# Fachregeln: Rollen, Rechte, Bereiche, Genehmiger

*Gilt fuer:* `services/AuthService.php`, `core/Auth.php`, Rollen- und
Rechteverwaltung im Backend.
*Source of Truth fuer die einzelnen Rechte-Codes:* `docs/rechte_prompt.md` –
dort steht, welches Recht wofuer gebraucht wird und wo es im Code geprueft wird.
*Herkunft:* Master-Prompt v13, Abschnitt 3 sowie v4-Abschnitte A/B und
v7 komplett.

---

## 1. Grundmodell

- **Rolle:** Buendel von Rechten (z. B. `Schichtleiter`, `Personalbuero`).
- **Recht:** konkrete Faehigkeit (z. B. „Urlaub genehmigen").
- **Scope/Bereich:** *wofuer* ein Recht gilt (global oder Abteilung, optional
  inklusive Unterabteilungen).

Tabellen:

- `rolle` – z. B. `Mitarbeiter`, `Vorarbeiter`, `Abteilungsleiter`,
  `Arbeitsvorbereiter`, `Chef`, `Personalbuero`; Flag `ist_superuser`.
- `recht` – `id`, `code` (UNIQUE), `beschreibung`, `aktiv`, Timestamps.
- `rolle_hat_recht` – UNIQUE(`rolle_id`, `recht_id`).
- `mitarbeiter_hat_rolle` – Legacy, wird intern wie `scope_typ = 'global'`
  behandelt.
- `mitarbeiter_hat_rolle_scope` – scoped Rollenzuweisung (siehe 3).
- `mitarbeiter_hat_recht` – gezielte Ausnahmen je Mitarbeiter. Die Spalte
  `erlaubt` entscheidet: `1` gewaehrt zusaetzlich, `0` entzieht trotz Rolle.
- `mitarbeiter_genehmiger` – siehe 5.

## 2. Chef darf immer alles (Superuser-Pflicht)

`rolle.ist_superuser` (TINYINT(1), Default 0). Hat ein Benutzer mindestens
**eine** Rolle mit `ist_superuser = 1`, gilt **jeder** Rechte-Check als erlaubt.

Trotzdem Pflicht: Alle sicherheitsrelevanten Aktionen (Genehmigen, Editieren,
Admin-Aenderungen) werden in `system_log` protokolliert.

## 3. Warum Rollen scoped zugewiesen werden

Ohne Bereiche muesste man je Abteilung eigene Rollen anlegen
(„Schichtleiter CNC", „Schichtleiter Montage", …). Das skaliert nicht.

`mitarbeiter_hat_rolle_scope`:

| Feld | Inhalt |
| --- | --- |
| `id` | PK |
| `mitarbeiter_id` | FK |
| `rolle_id` | FK |
| `scope_typ` | ENUM('global','abteilung'), Default `global` |
| `scope_id` | bei `global` = 0, sonst `abteilung.id` |
| `gilt_unterbereiche` | TINYINT(1), Default 1 |
| `erstellt_am` | Timestamp |

Ein Mitarbeiter kann dieselbe Rolle **mehrfach** haben – in unterschiedlichen
Bereichen.

**Bereichsmodell:** Kanonischer Bereich ist die vorhandene Tabelle `abteilung`
mit `parent_id` als Hierarchie. Mitarbeiter sind ueber
`mitarbeiter_hat_abteilung` M:N zugeordnet (inkl. `ist_stammabteilung`).

Scope-Pruefung:

- `scope = global` passt immer.
- `scope = abteilung` passt, wenn die Ziel-Abteilung **gleich** ist.
- Mit `gilt_unterbereiche = 1` passt auch alles im Unterbaum.

Fuer sehr grosse Baeume koennte spaeter eine Materialized-Path-Spalte oder eine
Closure-Tabelle ergaenzt werden; fuer den aktuellen Umfang reicht rekursives
Traversieren.

## 4. Zentrale Rechtepruefung (Pflicht)

**Nie** in Controllern „Rollenname == …" pruefen. Es gibt genau eine zentrale
Stelle, den `AuthService`:

```php
istSuperuser(): bool
hatRecht(string $code, ?int $zielMitarbeiterId = null, ?int $zielAbteilungId = null): bool
```

Ablauf von `hatRecht()`:

1. Wenn `istSuperuser()` → **true**.
2. Ziel-Scope bestimmen:
   - `zielAbteilungId` gesetzt → diese verwenden,
   - sonst `zielMitarbeiterId` gesetzt → Stammabteilung des Ziel-Mitarbeiters
     (Rueckfall: erste aktive Abteilung),
   - sonst → `global`.
3. Grants sammeln: Rollen des Benutzers (Legacy + Scope-Tabelle) inklusive
   Scope, Rechte je Rolle aus `rolle_hat_recht`, optional Overrides.
4. Matching:
   - `global` passt immer,
   - `abteilung` passt bei Gleichheit oder (mit `gilt_unterbereiche = 1`) im
     Unterbaum,
   - **`deny` gewinnt** gegen `allow`,
   - bei mehreren Treffern gewinnt der **spezifischste** (naechste Scope); bei
     Gleichstand gewinnt `deny`.
5. Caching: effektive Grants pro Session cachen; Cache invalidieren, wenn
   Rollen, Rechte oder Scopes administrativ geaendert werden.

**Overrides:** `mitarbeiter_hat_recht_scope` mit `effect` ENUM('allow','deny')
und `begruendung`. Priorisierung: `deny` sticht `allow`, Overrides stechen
Rollen.

## 5. Genehmiger sind personenzentriert, nicht abteilungsgebunden

Tabelle `mitarbeiter_genehmiger`:

- `mitarbeiter_id` – wer beantragt
- `genehmiger_mitarbeiter_id` – wer genehmigen darf
- `prioritaet` – 1 = Hauptgenehmiger, 2 = Stellvertretung, …

Urlaubsantraege duerfen genehmigt/abgelehnt werden von den eingetragenen
Genehmigern **oder** von Mitarbeitern mit der Rolle `Chef` (globale
Genehmigungsrolle). Das Modell ist unabhaengig von Abteilungsgrenzen und bildet
reale Vorgesetztenstrukturen ab.

## 6. Verwaltung im Backend

**Rollen & Rechte**

- Rollen verwalten (inkl. Flag `ist_superuser`).
- Rechte verwalten (Liste + Beschreibung; **Codes stabil halten**).
- Rechte einer Rolle zuweisen (Checkboxen, idempotentes Speichern).

**Mitarbeiter → Rollen (Bereiche)**

- Scoped Rollen zuweisen: Rolle + Bereich (global/Abteilung) +
  „Unterabteilungen einschliessen".
- Optional Overrides (allow/deny) mit Begruendung.

**Pflichtseite „Effektive Rechte"**

- Mitarbeiter auswaehlen → zeigt alle aktiven Rechte, aus welchem Grant
  (Rolle oder Override) sie kommen und mit welchem Scope.

**Genehmiger**

- Liste aller Mitarbeiter, Klick auf einen zeigt seine Genehmiger-Eintraege
  (Name, Prioritaet, ggf. Kommentar), mit „Genehmiger hinzufuegen" und
  „Entfernen".
- Optional die umgekehrte Ansicht „Wen darf dieser Mitarbeiter genehmigen?".

## 7. Regeln und Beispiele

**Urlaub genehmigen**

- `URLAUB_GENEHMIGEN` gilt scoped (Abteilung des Antragstellers).
- `URLAUB_GENEHMIGEN_ALLE` ist ein Legacy-Kuerzel fuer global und darf intern
  als globaler Grant interpretiert werden.
- Eigener Antrag: zusaetzlich `URLAUB_GENEHMIGEN_SELF`.

**Zeiten bearbeiten**

- `ZEIT_EDIT_ALLE` ist ein Legacy-Kuerzel fuer global.
- Bevorzugt ist ein Code ohne „ALLE"-Suffix (z. B. `ZEIT_EDIT`), der ueber den
  Scope gesteuert wird.
- Audit und Markierung bleiben Pflicht (siehe
  [zeit_rundung_pausen.md](zeit_rundung_pausen.md), Abschnitt 7).

**Reports**

- `REPORT_MONAT_ALLE` ist ein Legacy-Kuerzel fuer global.
- Bevorzugt `REPORT_MONAT_VIEW` / `REPORT_MONAT_EXPORT`, scoped.

## 8. Kompatibilitaet und Migration

- Bestehende Tabellen und Funktionen duerfen nicht hart brechen.
- Legacy-Rechte mit Suffix `_ALLE` bleiben bestehen und werden bis zur
  vollstaendigen Umstellung parallel unterstuetzt.
- **Neue Features werden immer ueber `hatRecht()` abgesichert** und nutzen
  Scope, statt neue Rollen zu erfinden.
- Ein neues Recht wird **immer** in `docs/rechte_prompt.md` dokumentiert
  (Code, Zweck, Pruefpunkte im Code und in der SQL).
