# SQL-Skripte

## Source of Truth
- `01_initial_schema.sql`: **vollständiges** Datenbankschema für `zeiterfassung` (Neuinstallation).

## Migrationen (Updates bestehender Installationen)
Migrationen sind fortlaufend nummeriert und **idempotent**: Mehrfaches
Ausführen ist unschaedlich. Sie werden nur für bestehende Installationen
gebraucht - Neuinstallationen bekommen alles über `01_initial_schema.sql`.

- `02_migration_recht_auftraege_verwalten.sql`: legt das Recht
  `AUFTRAEGE_VERWALTEN` an und ordnet es den Superuser-Rollen zu
  (Patch P-2026-08-08-08).
- `03_migration_arbeitsschritt_katalog.sql`: legt die Tabelle
  `arbeitsschritt_katalog` für zentrale Standard-Arbeitsschritte an
  (Patch P-2026-08-08-12).
- `05_migration_terminal_kopplung.sql`: legt die Tabelle `terminal_kopplung`
  für die Terminal-Anmeldung per Kopplungscode an (Patch P-2026-08-08-30).
- `04_migration_auftrag_code_rel_pfad.sql`: benennt den Konfigurationsschluessel
  `auftrag_qr_rel_pfad` in `auftrag_code_rel_pfad` um, nachdem die Codes von QR
  auf Strichcode umgestellt wurden (Patch P-2026-08-08-24). Rein kosmetisch -
  ohne die Migration liest die Anwendung ersatzweise den alten Schlüssel.
- `07_migration_urlaub_uebertrag_festschreiben.sql`: ergänzt
  `urlaub_kontingent_jahr` um `uebertrag_festgeschrieben_am` und markiert
  vorhandene, von Hand gepflegte Überträge als festgeschrieben (B-080,
  Patch P-2026-08-10-27).
- `08_migration_auftrag_zeichnungsnummer.sql`: ergänzt `auftrag` um die Spalte
  `zeichnungsnummer` samt Index (Patch P-2026-08-10-33).
- `06_migration_terminal_db_benutzer.sql`: ergänzt `terminal` um die Spalten zum
  Datenbankbenutzer der Kopplung (Patch P-2026-08-08-35). **Enthält zusätzlich
  die GRANT-Anweisungen, die ein Administrator einmal von Hand ausführen muss**,
  damit das Backend Terminal-Benutzer anlegen darf (`CREATE USER`,
  `GRANT OPTION`) - die Anwendung kann sich diese Rechte nicht selbst geben.

Einspielen:

```bash
mysql -u <USER> -p zeiterfassung < sql/02_migration_recht_auftraege_verwalten.sql
```

## Offline-DB (Terminal, optional)
- `offline_db_schema.sql`: Minimal-Schema für eine **lokale** Terminal-DB `zeiterfassung_offline`.
  - Enthält nur `db_injektionsqueue` (Offline-Queue für Kommen/Gehen + Aufträge, wenn die Haupt-DB down ist).
  - Hinweis: Die Anwendung legt die Tabelle bei Bedarf automatisch an – das Skript ist optional.
