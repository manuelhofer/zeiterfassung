-- Migration: 03 - Rechteverwaltung (recht + rolle_hat_recht) hinzufügen
-- Datum: 2026-01-01
--
-- Zweck:
-- - Führt eine skalierbare, rollenbasierte Rechteverwaltung ein.
-- - Rechte werden in `recht` gepflegt und Rollen über `rolle_hat_recht` zugewiesen.
-- - Mitarbeiter erben Rechte über `mitarbeiter_hat_rolle` (bestehende Tabelle).
--
-- Hinweis:
-- - Skript ist idempotent (CREATE TABLE IF NOT EXISTS + INSERT ... WHERE NOT EXISTS).
-- - Für Neuinstallationen ist `sql/01_initial_schema.sql` weiterhin die Source of Truth.

CREATE TABLE IF NOT EXISTS `recht` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `beschreibung` TEXT NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_recht_code` (`code`),
  KEY `idx_recht_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rolle_hat_recht` (
  `rolle_id` INT UNSIGNED NOT NULL,
  `recht_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`rolle_id`, `recht_id`),
  KEY `idx_rolle_hat_recht_recht` (`recht_id`),
  CONSTRAINT `fk_rolle_hat_recht_rolle`
    FOREIGN KEY (`rolle_id`) REFERENCES `rolle`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_rolle_hat_recht_recht`
    FOREIGN KEY (`recht_id`) REFERENCES `recht`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed: Basis-Rechte
-- --------------------------------------------------------

-- Rollen/Rechte-Verwaltung
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'ROLLEN_RECHTE_VERWALTEN', 'Rollen/Rechte verwalten', 'Darf Rollen anlegen/bearbeiten und Rechte zu Rollen zuweisen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'ROLLEN_RECHTE_VERWALTEN');

-- Urlaub
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'URLAUB_GENEHMIGEN', 'Urlaub genehmigen (Bereich)', 'Darf Urlaubsanträge im zugewiesenen Bereich genehmigen. Bereichslogik folgt später.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'URLAUB_GENEHMIGEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'URLAUB_GENEHMIGEN_SELF', 'Eigenen Urlaub genehmigen', 'Darf den eigenen Urlaubsantrag genehmigen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'URLAUB_GENEHMIGEN_SELF');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'URLAUB_GENEHMIGEN_ALLE', 'Urlaub genehmigen (alle)', 'Darf Urlaubsanträge aller Mitarbeiter genehmigen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'URLAUB_GENEHMIGEN_ALLE');

-- Reports
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'REPORTS_ANSEHEN_ALLE', 'Reports aller Mitarbeiter ansehen', 'Darf Monats-/PDF-Reports für andere Mitarbeiter ansehen/exportieren.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'REPORTS_ANSEHEN_ALLE');

-- Zeitbuchungen bearbeiten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'ZEITBUCHUNG_EDITIEREN_SELF', 'Eigene Zeitbuchungen bearbeiten', 'Darf eigene Zeitbuchungen korrigieren (mit Audit/Begründung).', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'ZEITBUCHUNG_EDITIEREN_SELF');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'ZEITBUCHUNG_EDITIEREN_ALLE', 'Zeitbuchungen aller bearbeiten', 'Darf Zeitbuchungen anderer Mitarbeiter korrigieren (mit Audit/Begründung).', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'ZEITBUCHUNG_EDITIEREN_ALLE');

-- Admin-Module
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'MITARBEITER_VERWALTEN', 'Mitarbeiter verwalten', 'Darf Mitarbeiter anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'MITARBEITER_VERWALTEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'ABTEILUNG_VERWALTEN', 'Abteilungen verwalten', 'Darf Abteilungen anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'ABTEILUNG_VERWALTEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'MASCHINEN_VERWALTEN', 'Maschinen verwalten', 'Darf Maschinen anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'MASCHINEN_VERWALTEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'FEIERTAGE_VERWALTEN', 'Feiertage verwalten', 'Darf Feiertage anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'FEIERTAGE_VERWALTEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'BETRIEBSFERIEN_VERWALTEN', 'Betriebsferien verwalten', 'Darf Betriebsferien anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'BETRIEBSFERIEN_VERWALTEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'QUEUE_VERWALTEN', 'Offline-Queue verwalten', 'Darf Offline-Queue einsehen/clear/retry.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'QUEUE_VERWALTEN');

INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'TERMINAL_VERWALTEN', 'Terminals verwalten', 'Darf Terminals anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'TERMINAL_VERWALTEN');

-- --------------------------------------------------------
-- Seed: Standard-Zuordnung (falls Rollen existieren)
-- --------------------------------------------------------

-- Chef bekommt alle Basis-Rechte
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.id, re.id
FROM `rolle` r
JOIN `recht` re ON re.code IN (
  'ROLLEN_RECHTE_VERWALTEN',
  'URLAUB_GENEHMIGEN', 'URLAUB_GENEHMIGEN_SELF', 'URLAUB_GENEHMIGEN_ALLE',
  'REPORTS_ANSEHEN_ALLE',
  'ZEITBUCHUNG_EDITIEREN_SELF', 'ZEITBUCHUNG_EDITIEREN_ALLE',
  'MITARBEITER_VERWALTEN', 'ABTEILUNG_VERWALTEN', 'MASCHINEN_VERWALTEN',
  'FEIERTAGE_VERWALTEN', 'BETRIEBSFERIEN_VERWALTEN', 'QUEUE_VERWALTEN', 'TERMINAL_VERWALTEN'
)
WHERE r.name = 'Chef'
AND NOT EXISTS (
  SELECT 1 FROM `rolle_hat_recht` x
  WHERE x.rolle_id = r.id AND x.recht_id = re.id
);

-- Personalbüro darf i. d. R. ebenfalls Urlaub genehmigen + Reports
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.id, re.id
FROM `rolle` r
JOIN `recht` re ON re.code IN (
  'URLAUB_GENEHMIGEN', 'URLAUB_GENEHMIGEN_ALLE',
  'REPORTS_ANSEHEN_ALLE'
)
WHERE r.name IN ('Personalbüro','Personalbuero')
AND NOT EXISTS (
  SELECT 1 FROM `rolle_hat_recht` x
  WHERE x.rolle_id = r.id AND x.recht_id = re.id
);
