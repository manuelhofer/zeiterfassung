-- Migration: 22 - Mitarbeiter Eintrittsdatum
-- Datum: 2026-01-18
--
-- Zweck:
-- - Optionales `eintrittsdatum` (DATE) im Mitarbeiterstamm.
-- - UrlaubService nutzt (wenn gepflegt) Eintrittsdatum statt `erstellt_am`.
--
-- Hinweis:
-- - Dieses Skript ist idempotent (prüft Spalte via information_schema).

SET @db := DATABASE();

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mitarbeiter'
    AND COLUMN_NAME = 'eintrittsdatum'
);

SET @sql_add_col := IF(
  @col_exists = 0,
  'ALTER TABLE `mitarbeiter` ADD COLUMN `eintrittsdatum` DATE NULL AFTER `geburtsdatum`',
  'SELECT 1'
);

PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;
