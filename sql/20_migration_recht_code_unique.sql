-- Migration: 20 - Rechte: Duplikate defensiv bereinigen + Unique/Indizes sicherstellen
-- Datum: 2026-01-11
--
-- Zweck:
-- - Stellt sicher, dass `recht.code` eindeutig bleibt (Unique-Index vorhanden).
-- - Falls in Alt-Installationen Duplikate entstanden sind (z. B. durch alte Seeds/Imports),
--   werden diese vor dem Anlegen des Unique-Index zusammengeführt:
--     - pro `code` bleibt die kleinste `id` als Master
--     - Referenzen in `rolle_hat_recht` und `mitarbeiter_hat_recht` werden auf den Master umgebogen
--     - Dubletten werden danach gelöscht
-- - Stellt zusätzlich sicher, dass ein Index auf `recht.aktiv` existiert (UI filtert aktiv=1).
--
-- Hinweise:
-- - Idempotent: Wenn keine Duplikate vorhanden sind und Indizes bereits existieren, passiert nichts.
-- - Der Unique-Constraint auf `recht.code` ist im Schema grundsätzlich vorgesehen; diese Migration
--   ist ein defensiver Reparaturschritt für Altinstallationen.

-- --------------------------------------------------------
-- A) Duplikate in `recht` nach `code` zusammenführen
-- --------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_recht_keep;
DROP TEMPORARY TABLE IF EXISTS tmp_recht_dedup;

CREATE TEMPORARY TABLE tmp_recht_keep
ENGINE=MEMORY
AS
SELECT `code`, MIN(`id`) AS keep_id
FROM `recht`
GROUP BY `code`;

CREATE TEMPORARY TABLE tmp_recht_dedup
ENGINE=MEMORY
AS
SELECT r.`id` AS dup_id, k.keep_id
FROM `recht` r
JOIN tmp_recht_keep k ON k.`code` = r.`code`
WHERE r.`id` <> k.keep_id;

-- 1) rolle_hat_recht: Konflikte vermeiden, dann umbiegen
DELETE rh
FROM `rolle_hat_recht` rh
JOIN tmp_recht_dedup m ON m.dup_id = rh.recht_id
JOIN `rolle_hat_recht` rh2 ON rh2.rolle_id = rh.rolle_id AND rh2.recht_id = m.keep_id;

UPDATE `rolle_hat_recht` rh
JOIN tmp_recht_dedup m ON m.dup_id = rh.recht_id
SET rh.recht_id = m.keep_id;

-- 2) mitarbeiter_hat_recht: Konflikte vermeiden, dann umbiegen
DELETE mhr
FROM `mitarbeiter_hat_recht` mhr
JOIN tmp_recht_dedup m ON m.dup_id = mhr.recht_id
JOIN `mitarbeiter_hat_recht` mhr2 ON mhr2.mitarbeiter_id = mhr.mitarbeiter_id AND mhr2.recht_id = m.keep_id;

UPDATE `mitarbeiter_hat_recht` mhr
JOIN tmp_recht_dedup m ON m.dup_id = mhr.recht_id
SET mhr.recht_id = m.keep_id;

-- 3) Dubletten in `recht` löschen
DELETE r
FROM `recht` r
JOIN tmp_recht_dedup m ON m.dup_id = r.id;

DROP TEMPORARY TABLE IF EXISTS tmp_recht_dedup;
DROP TEMPORARY TABLE IF EXISTS tmp_recht_keep;

-- --------------------------------------------------------
-- B) Indizes/Constraints sicherstellen
-- --------------------------------------------------------

-- Unique Index auf `code`
SET @has_uniq := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'recht'
    AND index_name = 'uniq_recht_code'
);

SET @sql_uniq := IF(@has_uniq = 0,
  'ALTER TABLE `recht` ADD UNIQUE KEY `uniq_recht_code` (`code`);',
  'SELECT 1;'
);

PREPARE stmt_uniq FROM @sql_uniq;
EXECUTE stmt_uniq;
DEALLOCATE PREPARE stmt_uniq;

-- Index auf `aktiv` (UI-Filter/Listen)
SET @has_aktiv := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'recht'
    AND index_name = 'idx_recht_aktiv'
);

SET @sql_aktiv := IF(@has_aktiv = 0,
  'ALTER TABLE `recht` ADD KEY `idx_recht_aktiv` (`aktiv`);',
  'SELECT 1;'
);

PREPARE stmt_aktiv FROM @sql_aktiv;
EXECUTE stmt_aktiv;
DEALLOCATE PREPARE stmt_aktiv;
