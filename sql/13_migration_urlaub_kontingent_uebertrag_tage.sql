-- Migration 13: Urlaub-Kontingent – Spalte `uebertrag_tage` sicherstellen
--
-- Hintergrund:
-- - Die Urlaubslogik selektiert `urlaub_kontingent_jahr.uebertrag_tage`.
-- - In älteren Installationen kann die Spalte fehlen; dann bricht das SELECT ab und
--   damit werden auch `korrektur_tage`/`anspruch_override_tage` nicht berücksichtigt.
--
-- Diese Migration ist idempotent:
-- - Wenn die Tabelle existiert und die Spalte fehlt → Spalte wird ergänzt.
-- - In allen anderen Fällen passiert nichts.

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'urlaub_kontingent_jahr'
);

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'urlaub_kontingent_jahr'
      AND COLUMN_NAME = 'uebertrag_tage'
);

SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `urlaub_kontingent_jahr`\n  ADD COLUMN `uebertrag_tage` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `anspruch_override_tage`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
