# SQL-Skripte

## Source of Truth
- `01_initial_schema.sql`: **vollständiges** Datenbankschema für `zeiterfassung` (Neuinstallation).

## Migrationen (Updates bestehender Installationen)
Migrationen sind fortlaufend nummeriert und **idempotent**: Mehrfaches
Ausfuehren ist unschaedlich. Sie werden nur fuer bestehende Installationen
gebraucht - Neuinstallationen bekommen alles ueber `01_initial_schema.sql`.

- `02_migration_recht_auftraege_verwalten.sql`: legt das Recht
  `AUFTRAEGE_VERWALTEN` an und ordnet es den Superuser-Rollen zu
  (Patch P-2026-08-08-08).

Einspielen:

```bash
mysql -u <USER> -p zeiterfassung < sql/02_migration_recht_auftraege_verwalten.sql
```

## Offline-DB (Terminal, optional)
- `offline_db_schema.sql`: Minimal-Schema für eine **lokale** Terminal-DB `zeiterfassung_offline`.
  - Enthält nur `db_injektionsqueue` (Offline-Queue für Kommen/Gehen + Aufträge, wenn die Haupt-DB down ist).
  - Hinweis: Die Anwendung legt die Tabelle bei Bedarf automatisch an – das Skript ist optional.
