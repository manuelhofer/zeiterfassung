# Fachregeln: Rollen, Rechte, Bereiche, Genehmiger

*Gilt für:* `services/AuthService.php`, Rollen- und
Rechteverwaltung im Backend.
*Source of Truth für die einzelnen Rechte-Codes:* `docs/rechte_prompt.md` –
dort steht, welches Recht wofür gebraucht wird und wo es im Code geprüft wird.
*Herkunft:* Master-Prompt v13, Abschnitt 3 sowie v4-Abschnitte A/B und
v7 komplett.

---

## 1. Grundmodell

- **Rolle:** Bündel von Rechten (z. B. `Schichtleiter`, `Personalbuero`).
- **Recht:** konkrete Fähigkeit (z. B. „Urlaub genehmigen").
- **Scope/Bereich:** *wofür* ein Recht gilt (global oder Abteilung, optional
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
  `erlaubt` entscheidet: `1` gewährt zusätzlich, `0` entzieht trotz Rolle.
- `mitarbeiter_genehmiger` – siehe 5.

## 2. Chef darf immer alles (Superuser-Pflicht)

`rolle.ist_superuser` (TINYINT(1), Default 0). Hat ein Benutzer mindestens
**eine** Rolle mit `ist_superuser = 1`, gilt **jeder** Rechte-Check als erlaubt.

Trotzdem Pflicht: Alle sicherheitsrelevanten Aktionen (Genehmigen, Editieren,
Admin-Änderungen) werden in `system_log` protokolliert.

## 3. Warum Rollen scoped zugewiesen werden

Ohne Bereiche müsste man je Abteilung eigene Rollen anlegen
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
mit `parent_id` als Hierarchie. Mitarbeiter sind über
`mitarbeiter_hat_abteilung` M:N zugeordnet (inkl. `ist_stammabteilung`).

Scope-Prüfung – **umgesetzt seit P-2026-08-11-07, aber nur für ein einziges
Recht** (`URLAUB_GENEHMIGEN`, siehe
[`spezifikation_abteilungsrechte.md`](../spezifikation_abteilungsrechte.md)):

- `scope = global` passt immer, für **jedes** Recht.
- `scope = abteilung` wird ausschließlich für `URLAUB_GENEHMIGEN`
  ausgewertet, und zwar in `UrlaubGenehmigungService` – nicht in
  `hatRecht()`. Alle übrigen Rechte einer so zugewiesenen Rolle greifen
  **nicht**.
- `gilt_unterbereiche = 1` schließt den Unterbaum von `abteilung.parent_id`
  ein – ebenfalls nur für dieses eine Recht.

Für ein allgemeines Bereichsmodell (jedes Recht, jede Maske) müsste
`hatRecht()` ein Ziel bekommen und jede der rund 84 Prüfstellen entscheiden,
worauf sie sich bezieht. Das ist bewusst **nicht** gebaut.

Für sehr große Bäume könnte später eine Materialized-Path-Spalte oder eine
Closure-Tabelle ergänzt werden; für den aktuellen Umfang reicht rekursives
Traversieren.

## 4. Zentrale Rechteprüfung (Pflicht)

**Nie** in Controllern „Rollenname == …" prüfen. Es gibt genau eine zentrale
Stelle, den `AuthService`:

```php
istSuperuser(): bool
hatRecht(string $rechtCode): bool
```

**Das ist der Ist-Zustand, und er ist bewusst kleiner als das Bereichsmodell
aus Abschnitt 3.** Bis P-2026-08-10-09 stand hier eine Signatur mit
`$zielMitarbeiterId`/`$zielAbteilungId` und eine Scope-Auflösung, die es im
Code nie gab – wer sich darauf verließ, übergab Argumente, die stillschweigend
ignoriert wurden. Was der Code wirklich tut:

Ablauf von `hatRecht()`:

1. Nicht angemeldet oder leerer Code → **false**.
2. Wenn `istSuperuser()` → **true**.
3. Sonst: die effektiven Rechte-Codes des Benutzers holen und
   case-insensitiv vergleichen.

Die effektiven Codes (`ladeRechteCodesAusDb()`) entstehen so:

1. Rollen aus `mitarbeiter_hat_rolle` **und** aus `mitarbeiter_hat_rolle_scope`
   – dort aber **nur Zeilen mit `scope_typ = 'global'`**.
2. Rechte je Rolle aus `rolle_hat_recht`, nur `recht.aktiv = 1`.
3. Overrides aus `mitarbeiter_hat_recht`: `erlaubt = 1` gewährt zusätzlich
   (auch ohne Rollenrecht), `erlaubt = 0` entzieht. **Entzug gewinnt**, und
   Overrides stechen Rollen.
4. Caching pro Session; der Cache wird verworfen, wenn sich die Mitarbeiter-ID
   ändert.

**Was daraus folgt:** Eine Rollenzuweisung mit `scope_typ = 'abteilung'`
gewährt derzeit **gar nichts** – `hatRecht()` sieht sie nie an. Seit
P-2026-08-10-25 ist die Auswahl in der Mitarbeiterverwaltung deshalb gesperrt
(`MitarbeiterAdminController::SCOPE_ABTEILUNG_AKTIV`), damit niemand Rechte
vergibt, die nicht greifen. Bestehende Zeilen bleiben lesbar und löschbar.
Siehe B-093 im Status-Snapshot.

## 5. Genehmiger sind personenzentriert, nicht abteilungsgebunden

Tabelle `mitarbeiter_genehmiger`:

- `mitarbeiter_id` – wer beantragt
- `genehmiger_mitarbeiter_id` – wer genehmigen darf
- `prioritaet` – 1 = Hauptgenehmiger, 2 = Stellvertretung, …

Urlaubsanträge dürfen genehmigt/abgelehnt werden von den eingetragenen
Genehmigern **oder** von Mitarbeitern mit der Rolle `Chef` (globale
Genehmigungsrolle). Das Modell ist unabhängig von Abteilungsgrenzen und bildet
reale Vorgesetztenstrukturen ab.

## 6. Verwaltung im Backend

**Rollen & Rechte**

- Rollen verwalten (inkl. Flag `ist_superuser`).
- Rechte verwalten (Liste + Beschreibung; **Codes stabil halten**).
- Rechte einer Rolle zuweisen (Checkboxen, idempotentes Speichern).

**Mitarbeiter → Rollen (Bereiche)**

- Scoped Rollen zuweisen: Rolle + Bereich (global/Abteilung) +
  „Unterabteilungen einschließen".
- Optional Overrides (allow/deny) mit Begründung.

**Pflichtseite „Effektive Rechte"**

- Mitarbeiter auswählen → zeigt alle aktiven Rechte, aus welchem Grant
  (Rolle oder Override) sie kommen und mit welchem Scope.

**Genehmiger**

- Liste aller Mitarbeiter, Klick auf einen zeigt seine Genehmiger-Einträge
  (Name, Priorität, ggf. Kommentar), mit „Genehmiger hinzufügen" und
  „Entfernen".
- Optional die umgekehrte Ansicht „Wen darf dieser Mitarbeiter genehmigen?".

## 7. Regeln und Beispiele

**Urlaub genehmigen**

- `URLAUB_GENEHMIGEN` gilt scoped (Abteilung des Antragstellers).
- `URLAUB_GENEHMIGEN_ALLE` ist ein Legacy-Kürzel für global und darf intern
  als globaler Grant interpretiert werden.
- Eigener Antrag: zusätzlich `URLAUB_GENEHMIGEN_SELF`.

**Zeiten bearbeiten**

- `ZEIT_EDIT_ALLE` ist ein Legacy-Kürzel für global.
- Bevorzugt ist ein Code ohne „ALLE"-Suffix (z. B. `ZEIT_EDIT`), der über den
  Scope gesteuert wird.
- Audit und Markierung bleiben Pflicht (siehe
  [zeit_rundung_pausen.md](zeit_rundung_pausen.md), Abschnitt 7).

**Reports**

- `REPORT_MONAT_ALLE` ist ein Legacy-Kürzel für global.
- Bevorzugt `REPORT_MONAT_VIEW` / `REPORT_MONAT_EXPORT`, scoped.

## 8. Kompatibilität und Migration

- Bestehende Tabellen und Funktionen dürfen nicht hart brechen.
- Legacy-Rechte mit Suffix `_ALLE` bleiben bestehen und werden bis zur
  vollständigen Umstellung parallel unterstützt.
- **Neue Features werden immer über `hatRecht()` abgesichert** und nutzen
  Scope, statt neue Rollen zu erfinden.
- Ein neues Recht wird **immer** in `docs/rechte_prompt.md` dokumentiert
  (Code, Zweck, Prüfpunkte im Code und in der SQL).
