# SQL-Skripte

## Source of Truth
- `01_initial_schema.sql`: **vollständiges** Datenbankschema für `zeiterfassung` (Neuinstallation).

## Migrationen (Updates bestehender Installationen)
- Entfernt: Es gibt keine Instanzen, die ueber Migrationen aktualisiert werden muessen.
- Falls spaeter doch Updates noetig sind, werden Migrationen separat bereitgestellt.

## Offline-DB (Terminal, optional)
- `offline_db_schema.sql`: Minimal-Schema für eine **lokale** Terminal-DB `zeiterfassung_offline`.
  - Enthält nur `db_injektionsqueue` (Offline-Queue für Kommen/Gehen + Aufträge, wenn die Haupt-DB down ist).
  - Hinweis: Die Anwendung legt die Tabelle bei Bedarf automatisch an – das Skript ist optional.
