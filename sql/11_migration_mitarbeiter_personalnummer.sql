-- Migration: 11 - Mitarbeiter Personalnummer
-- Datum: 2026-01-04
--
-- Zweck:
-- - Ergänzt das Schema um eine optionale `personalnummer` pro Mitarbeiter.
-- - Personalnummer kann später z. B. für Terminal-Login als Alternative zur DB-ID genutzt werden.
--
-- Hinweis:
-- - Dieses Skript ist idempotent (prüft Spalte/Index via information_schema).

SET @db := DATABASE();

-- --------------------------------------------------------
-- Spalte `personalnummer` ergänzen (falls noch nicht vorhanden)
-- --------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mitarbeiter'
    AND COLUMN_NAME = 'personalnummer'
);

SET @sql_add_col := IF(
  @col_exists = 0,
  'ALTER TABLE `mitarbeiter` ADD COLUMN `personalnummer` VARCHAR(32) NULL AFTER `id`',
  'SELECT 1'
);

PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- --------------------------------------------------------
-- Unique Index `uniq_mitarbeiter_personalnummer` ergänzen
-- --------------------------------------------------------
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mitarbeiter'
    AND INDEX_NAME = 'uniq_mitarbeiter_personalnummer'
);

SET @sql_add_idx := IF(
  @idx_exists = 0,
  'ALTER TABLE `mitarbeiter` ADD UNIQUE KEY `uniq_mitarbeiter_personalnummer` (`personalnummer`)',
  'SELECT 1'
);

PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;
