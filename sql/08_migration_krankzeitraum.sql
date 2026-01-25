-- Migration: 08 - Tabelle krankzeitraum (Krank LFZ vs Krank KK)
-- Datum: 2026-01-03
--
-- Zweck (T-071):
-- - Abbildung von Krankheits-Phasen pro Mitarbeiter als Zeitraum.
-- - `typ`:
--   - `lfz` = Lohnfortzahlung (i. d. R. die ersten 6 Wochen)
--   - `kk`  = Krankenkasse (ab Übergang zur Krankenkasse)
-- - Wechsel LFZ → KK wird als zweiter Zeitraum gepflegt.
-- - `bis_datum` darf NULL sein (laufender Zeitraum).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS).
-- - Für Neuinstallationen ist `sql/01_initial_schema.sql` weiterhin die Source of Truth.

CREATE TABLE IF NOT EXISTS `krankzeitraum` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `typ` ENUM('lfz','kk') NOT NULL,
  `von_datum` DATE NOT NULL,
  `bis_datum` DATE NULL,
  `kommentar` VARCHAR(255) NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` INT UNSIGNED NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_krankzeitraum_mitarbeiter_dates` (`mitarbeiter_id`,`von_datum`,`bis_datum`,`aktiv`),
  KEY `idx_krankzeitraum_typ` (`typ`),
  KEY `idx_krankzeitraum_angelegt_von` (`angelegt_von_mitarbeiter_id`),
  CONSTRAINT `fk_krankzeitraum_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_krankzeitraum_angelegt_von`
    FOREIGN KEY (`angelegt_von_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
