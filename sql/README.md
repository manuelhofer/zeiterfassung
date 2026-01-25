# SQL-Skripte

## Source of Truth
- `01_initial_schema.sql`: **vollständiges** Datenbankschema für `zeiterfassung` (Neuinstallation).

## Migrationen (Bestands-DB)
- `02_migration_urlaub_kontingent_jahr.sql`: Ergänzt die Tabelle `urlaub_kontingent_jahr` (Übertrag/Korrektur/Anspruch-Override pro Mitarbeiter/Jahr).

## Dev/Test Dump (optional)
- `zeiterfassung_aktuell.sql`: phpMyAdmin-Dump mit Beispieldaten (für lokale Tests).  
  **Wichtig:** Nicht als „Schema-Quelle“ betrachten – für Produktion/Updates immer `01_*` + ggf. Migrationen verwenden.

## Offline-DB (Terminal, optional)
- `offline_db_schema.sql`: Minimal-Schema für eine **lokale** Terminal-DB `zeiterfassung_offline`.
  - Enthält nur `db_injektionsqueue` (Offline-Queue für Kommen/Gehen + Aufträge, wenn die Haupt-DB down ist).
  - Hinweis: Die Anwendung legt die Tabelle bei Bedarf automatisch an – das Skript ist optional.
