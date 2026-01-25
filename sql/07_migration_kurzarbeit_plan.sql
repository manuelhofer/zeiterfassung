-- Migration: 07 - Tabelle kurzarbeit_plan hinzufügen
-- Datum: 2026-01-03
--
-- Zweck:
-- - Ermöglicht die Planung von Kurzarbeit als Zeitraum (firmenweit oder mitarbeiterbezogen),
--   inkl. Wochentage-Maske und Modus (Stunden/Prozent).
-- - Wird später von ReportService/Tageswerte-Berechnung genutzt, um Soll/Saldo zu reduzieren.
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS).
-- - Für Neuinstallationen ist `sql/01_initial_schema.sql` weiterhin die Source of Truth.

CREATE TABLE IF NOT EXISTS `kurzarbeit_plan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` ENUM('firma','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  `mitarbeiter_id` INT UNSIGNED NULL,
  `von_datum` DATE NOT NULL,
  `bis_datum` DATE NOT NULL,
  `wochentage_mask` TINYINT UNSIGNED NOT NULL DEFAULT 31,
  `modus` ENUM('stunden','prozent') NOT NULL DEFAULT 'stunden',
  `wert` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `kommentar` VARCHAR(255) NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` INT UNSIGNED NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kurzarbeit_plan_scope_dates` (`scope`,`von_datum`,`bis_datum`,`aktiv`),
  KEY `idx_kurzarbeit_plan_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_kurzarbeit_plan_angelegt_von` (`angelegt_von_mitarbeiter_id`),
  CONSTRAINT `fk_kurzarbeit_plan_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_kurzarbeit_plan_angelegt_von`
    FOREIGN KEY (`angelegt_von_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
