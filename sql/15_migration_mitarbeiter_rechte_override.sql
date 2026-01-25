-- Migration: 15 - Mitarbeiter-Rechte Overrides (Allow/Deny pro Mitarbeiter)
-- Datum: 2026-01-07
--
-- Zweck:
-- - Ergänzt die rollenbasierte Rechteverwaltung um optionale Overrides pro Mitarbeiter.
-- - Damit kann man einzelnen Mitarbeitern ein Recht explizit erlauben (auch ohne Rolle)
--   oder explizit entziehen (trotz Rollenrecht).
--
-- Logik (in AuthService):
-- - Start: Rechte aus Rollen (rolle_hat_recht via mitarbeiter_hat_rolle)
-- - Danach: Overrides aus mitarbeiter_hat_recht anwenden:
--     erlaubt=1 => Recht wird hinzugefügt
--     erlaubt=0 => Recht wird entfernt
--
-- Hinweis:
-- - Idempotent (CREATE TABLE IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS `mitarbeiter_hat_recht` (
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `recht_id` INT UNSIGNED NOT NULL,
  `erlaubt` TINYINT(1) NOT NULL DEFAULT 1,
  `notiz` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mitarbeiter_id`, `recht_id`),
  KEY `idx_mhr_recht` (`recht_id`),
  KEY `idx_mhr_erlaubt` (`erlaubt`),
  CONSTRAINT `fk_mitarbeiter_hat_recht_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mitarbeiter_hat_recht_recht`
    FOREIGN KEY (`recht_id`) REFERENCES `recht`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
