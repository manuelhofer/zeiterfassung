-- Migration: 13 - Stundenkonto (Batch + Korrektur) + Recht STUNDENKONTO_VERWALTEN
-- Datum: 2026-01-17
--
-- WICHTIG (Legacy-Alias):
-- - Diese Datei wurde in einem fruehen Patch fälschlich als "13" angelegt.
-- - Kanonisch ist nun `sql/21_migration_stundenkonto.sql` (gleicher Inhalt, korrekte Reihenfolge).
-- - Diese Datei bleibt als Alias erhalten, damit bereits ausgerollte Umgebungen nicht "ins Leere" laufen.
--
-- Zweck:
-- - Fuehrt Tabellen fuer das Stundenkonto ein:
--   - `stundenkonto_batch` (Metadaten fuer Verteilungen)
--   - `stundenkonto_korrektur` (Ledger, Delta-Minuten pro Tag)
-- - Legt das neue Recht `STUNDENKONTO_VERWALTEN` an (Backend-Korrekturen/Verteilungen).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (CREATE TABLE IF NOT EXISTS + INSERT ... WHERE NOT EXISTS).
-- - Fuer Neuinstallationen wird `sql/01_initial_schema.sql` in einem separaten Micro-Patch nachgezogen (SoT).

CREATE TABLE IF NOT EXISTS `stundenkonto_batch` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `modus` ENUM('gesamt_gleichmaessig','minuten_pro_tag') NOT NULL,
  `von_datum` DATE NOT NULL,
  `bis_datum` DATE NOT NULL,
  `gesamt_minuten` INT NULL,
  `minuten_pro_tag` INT NULL,
  `nur_arbeitstage` TINYINT(1) NOT NULL DEFAULT 1,
  `begruendung` VARCHAR(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` INT UNSIGNED NULL,
  `stealth` TINYINT(1) NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stundenkonto_batch_mitarbeiter_datum` (`mitarbeiter_id`,`von_datum`,`bis_datum`),
  KEY `idx_stundenkonto_batch_erstellt_von` (`erstellt_von_mitarbeiter_id`),
  CONSTRAINT `fk_stundenkonto_batch_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stundenkonto_batch_erstellt_von`
    FOREIGN KEY (`erstellt_von_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stundenkonto_korrektur` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `wirksam_datum` DATE NOT NULL,
  `delta_minuten` INT NOT NULL,
  `typ` ENUM('manuell','verteilung') NOT NULL,
  `batch_id` INT UNSIGNED NULL,
  `begruendung` VARCHAR(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` INT UNSIGNED NULL,
  `stealth` TINYINT(1) NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stundenkonto_korr_mitarbeiter_datum` (`mitarbeiter_id`,`wirksam_datum`),
  KEY `idx_stundenkonto_korr_batch` (`batch_id`),
  KEY `idx_stundenkonto_korr_typ` (`typ`),
  CONSTRAINT `fk_stundenkonto_korr_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stundenkonto_korr_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `stundenkonto_batch`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_stundenkonto_korr_erstellt_von`
    FOREIGN KEY (`erstellt_von_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Neues Recht: Stundenkonto verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'STUNDENKONTO_VERWALTEN', 'Stundenkonto verwalten', 'Darf Stundenkonto-Korrekturen und Verteilungen im Backend erfassen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'STUNDENKONTO_VERWALTEN');

-- Standard-Zuordnung: Chef bekommt das neue Recht
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.id, re.id
FROM `rolle` r
JOIN `recht` re ON re.code = 'STUNDENKONTO_VERWALTEN'
WHERE r.name = 'Chef'
AND NOT EXISTS (
  SELECT 1 FROM `rolle_hat_recht` x
  WHERE x.rolle_id = r.id AND x.recht_id = re.id
);
