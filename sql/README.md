# SQL-Skripte

## Source of Truth
- `01_initial_schema.sql`: **vollständiges** Datenbankschema für `zeiterfassung` (Neuinstallation).

## Migrationen (Updates bestehender Installationen)
- `02_...` bis `28_...`: Schrittweise Migrationen fuer bestehende Datenbanken.
- Hinweis: Fuer eine **Neuinstallation** werden diese Dateien **nicht** benoetigt.

## Offline-DB (Terminal, optional)
- `offline_db_schema.sql`: Minimal-Schema für eine **lokale** Terminal-DB `zeiterfassung_offline`.
  - Enthält nur `db_injektionsqueue` (Offline-Queue für Kommen/Gehen + Aufträge, wenn die Haupt-DB down ist).
  - Hinweis: Die Anwendung legt die Tabelle bei Bedarf automatisch an – das Skript ist optional.
