-- Migration: 09 - Tabelle pausenfenster hinzufügen + Pausenregeln-Konfig
-- Datum: 2026-01-03
--
-- Zweck (T-072, Teil 1):
-- - Betriebliche Pausenfenster (Uhrzeitfenster) pflegbar machen.
-- - Gesetzliche Mindestpause wird als Konfig-Keys gepflegt (keine Schema-Änderung nötig).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS).
-- - Für Neuinstallationen ist `sql/01_initial_schema.sql` weiterhin die Source of Truth.

CREATE TABLE IF NOT EXISTS `pausenfenster` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `von_uhrzeit` TIME NOT NULL,
  `bis_uhrzeit` TIME NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 10,
  `kommentar` VARCHAR(255) NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pausenfenster_aktiv_sort` (`aktiv`, `sort_order`, `von_uhrzeit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
