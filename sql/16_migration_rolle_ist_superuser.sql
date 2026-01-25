-- Migration 16: Rolle – Flag `ist_superuser` (Chef darf immer alles)
-- Datum: 2026-01-07
--
-- Zweck:
-- - Ergänzt `rolle` um `ist_superuser`.
-- - Wenn ein Benutzer mindestens eine Rolle mit `ist_superuser=1` besitzt,
--   gilt jeder Rechte-Check als erlaubt (AuthService::istSuperuser()).
--
-- Idempotent:
-- - Wenn Tabelle/Spalte fehlen -> wird ergänzt.
-- - Update auf Rolle "Chef" wird nur ausgeführt, wenn Spalte existiert.

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rolle'
);

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rolle'
      AND COLUMN_NAME = 'ist_superuser'
);

SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `rolle`\n  ADD COLUMN `ist_superuser` tinyint(1) NOT NULL DEFAULT 0 AFTER `aktiv`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Chef als Superuser markieren (falls vorhanden)
SET @col_exists2 := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rolle'
      AND COLUMN_NAME = 'ist_superuser'
);

SET @sql2 := IF(
    @tbl_exists = 1 AND @col_exists2 = 1,
    'UPDATE `rolle` SET `ist_superuser` = 1 WHERE `name` = \'Chef\'',
    'SELECT 1'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
