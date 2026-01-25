-- Migration 17: Scoped Rollen-Zuweisung (Vorbereitung Bereichsrechte)
-- Datum: 2026-01-07
--
-- Zweck:
-- - Ergänzt das Rollenmodell um eine skalierbare, scoped Rollen-Zuweisung je Mitarbeiter.
-- - Diese Migration ist zunächst "vorbereitend":
--   - AuthService liest zusätzlich Rollen aus `mitarbeiter_hat_rolle_scope`,
--     wertet aktuell aber nur `scope_typ='global'` aus (keine Abteilungs-/Unterbaumlogik).
--
-- Hinweis:
-- - Idempotent (CREATE TABLE IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS `mitarbeiter_hat_rolle_scope` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `rolle_id` INT UNSIGNED NOT NULL,
  `scope_typ` ENUM('global','abteilung') NOT NULL DEFAULT 'global',
  `scope_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `gilt_unterbereiche` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mhrs` (`mitarbeiter_id`,`rolle_id`,`scope_typ`,`scope_id`),
  KEY `idx_mhrs_rolle` (`rolle_id`),
  KEY `idx_mhrs_scope` (`scope_typ`,`scope_id`),
  CONSTRAINT `fk_mhrs_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mhrs_rolle`
    FOREIGN KEY (`rolle_id`) REFERENCES `rolle`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
