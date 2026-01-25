-- Migration: 02 - Tabelle urlaub_kontingent_jahr hinzufügen
-- Datum: 2025-12-25
--
-- Zweck:
-- - Ergänzt das Schema um `urlaub_kontingent_jahr`, das für Übertrag/Korrektur/Anspruch-Override pro Mitarbeiter/Jahr genutzt wird.
-- - Für Bestandsdatenbanken, die vor Einführung dieser Tabelle erstellt wurden.
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS).
-- - Für Neuinstallationen ist `sql/01_initial_schema.sql` weiterhin die Source of Truth.

CREATE TABLE IF NOT EXISTS `urlaub_kontingent_jahr` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `jahr` SMALLINT UNSIGNED NOT NULL,
  `anspruch_override_tage` DECIMAL(6,2) NULL,
  `uebertrag_tage` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `korrektur_tage` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `notiz` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_urlaub_kontingent_jahr` (`mitarbeiter_id`,`jahr`),
  KEY `idx_urlaub_kontingent_jahr_jahr` (`jahr`),
  CONSTRAINT `fk_urlaub_kontingent_jahr_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
