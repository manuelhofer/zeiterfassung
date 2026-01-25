-- Migration: 12 - Tabelle sonstiges_grund (konfigurierbare Sonstiges-Gründe)
-- Datum: 2026-01-04
--
-- Zweck (T-075 Teil 1):
-- - Gründe für Kennzeichen "Sonstiges" konfigurierbar machen (Code/Titel/Default-Stunden/Begründungspflicht).
-- - Basis-Datensatz "Sonderurlaub" (Code `SoU`) wird initial angelegt.
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
-- - Für Neuinstallationen ist `sql/01_initial_schema.sql` weiterhin die Source of Truth.

CREATE TABLE IF NOT EXISTS `sonstiges_grund` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(10) NOT NULL,
  `titel` VARCHAR(80) NOT NULL,
  `default_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `begruendung_pflicht` TINYINT(1) NOT NULL DEFAULT 0,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 10,
  `kommentar` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sonstiges_grund_code` (`code`),
  KEY `idx_sonstiges_grund_aktiv_sort` (`aktiv`,`sort_order`,`titel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Basis-Eintrag: Sonderurlaub
INSERT IGNORE INTO `sonstiges_grund`
  (`code`, `titel`, `default_stunden`, `begruendung_pflicht`, `aktiv`, `sort_order`, `kommentar`)
VALUES
  ('SoU', 'Sonderurlaub', 8.00, 1, 1, 1, NULL);
