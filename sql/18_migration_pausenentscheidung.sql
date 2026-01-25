-- Migration: 18 - Pausen-Entscheidungen (manuell) für Grenzfälle
-- Datum: 2026-01-08
--
-- Zweck (T-081 Teil 2):
-- - Wenn Pausenabzug nicht eindeutig automatisch entschieden werden kann, soll eine menschliche Entscheidung
--   gespeichert werden (ABZIEHEN / NICHT_ABZIEHEN).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS).
-- - Für Neuinstallationen bleibt `sql/01_initial_schema.sql` die Source of Truth.

CREATE TABLE IF NOT EXISTS `pausenentscheidung` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `datum` DATE NOT NULL,
  `entscheidung` ENUM('ABZIEHEN','NICHT_ABZIEHEN') NOT NULL,
  `kommentar` VARCHAR(255) NULL,
  `erstellt_von_mitarbeiter_id` INT UNSIGNED NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pausenentscheidung_mid_datum` (`mitarbeiter_id`, `datum`),
  KEY `idx_pausenentscheidung_datum` (`datum`),
  KEY `idx_pausenentscheidung_mid` (`mitarbeiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
