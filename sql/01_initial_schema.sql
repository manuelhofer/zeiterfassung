-- Initiales Datenbankschema für Zeiterfassung / Mitarbeiter- und Auftragsmanagement
-- Generiert am 2025-11-27
-- ACHTUNG:
--  - Script legt Tabellen nur an und löscht nichts.
--  - Wenn Tabellen schon existieren, gibt es Fehlermeldungen bei CREATE TABLE.

SET NAMES utf8mb4;
SET time_zone = '+01:00';

CREATE DATABASE IF NOT EXISTS `zeiterfassung`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `zeiterfassung`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Tabelle: abteilung
-- --------------------------------------------------------
CREATE TABLE `abteilung` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `beschreibung` TEXT NULL,
  `parent_id` INT UNSIGNED NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_abteilung_parent` (`parent_id`),
  KEY `idx_abteilung_name` (`name`),
  CONSTRAINT `fk_abteilung_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `abteilung`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- --------------------------------------------------------
-- Tabelle: mitarbeiter
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personalnummer` VARCHAR(32) NULL,
  `vorname` VARCHAR(100) NOT NULL,
  `nachname` VARCHAR(100) NOT NULL,
  `geburtsdatum` DATE NULL,
  `eintrittsdatum` DATE NULL,
  `wochenarbeitszeit` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `urlaub_monatsanspruch` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `benutzername` VARCHAR(150) NULL,
  `email` VARCHAR(190) NULL,
  `passwort_hash` VARCHAR(255) NULL,
  `rfid_code` VARCHAR(64) NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `ist_login_berechtigt` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mitarbeiter_benutzername` (`benutzername`),
  UNIQUE KEY `uniq_mitarbeiter_email` (`email`),
  UNIQUE KEY `uniq_mitarbeiter_rfid` (`rfid_code`),
  UNIQUE KEY `uniq_mitarbeiter_personalnummer` (`personalnummer`),
  KEY `idx_mitarbeiter_name` (`nachname`,`vorname`),
  KEY `idx_mitarbeiter_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Tabelle: krankzeitraum (Krank LFZ vs Krank KK)
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
-- - Die automatische Ableitung auf Tageswerte/PDF (Spalten „Krank LF“/„Krank KK“) passiert in der
--   Report-/Tageswerte-Logik. Manuelle Tages-Overrides bleiben möglich.
-- --------------------------------------------------------
CREATE TABLE `krankzeitraum` (
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

-- --------------------------------------------------------
-- Tabelle: rolle
-- --------------------------------------------------------
CREATE TABLE `rolle` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `beschreibung` TEXT NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `ist_superuser` TINYINT(1) NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rolle_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: recht
--
-- Zweck:
-- - Granulare Rechteverwaltung (rollenbasiert).
-- - Rechte werden Rollen zugewiesen (rolle_hat_recht).
-- - Ein Mitarbeiter erbt Rechte über seine Rollen (mitarbeiter_hat_rolle).
--
-- Hinweis:
-- - Rechte werden primär über den Code (z. B. "URLAUB_GENEHMIGEN") im Code abgefragt.
-- --------------------------------------------------------
CREATE TABLE `recht` (
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

-- --------------------------------------------------------
-- Tabelle: rolle_hat_recht (M:N)
-- --------------------------------------------------------
CREATE TABLE `rolle_hat_recht` (
  `rolle_id` INT UNSIGNED NOT NULL,
  `recht_id` INT UNSIGNED NOT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
-- Tabelle: maschine
-- --------------------------------------------------------
CREATE TABLE `maschine` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `abteilung_id` INT UNSIGNED NULL,
  `beschreibung` TEXT NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_maschine_abteilung` (`abteilung_id`),
  KEY `idx_maschine_aktiv` (`aktiv`),
  CONSTRAINT `fk_maschine_abteilung`
    FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: terminal
-- --------------------------------------------------------
CREATE TABLE `terminal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `standort_beschreibung` VARCHAR(255) NULL,
  `abteilung_id` INT UNSIGNED NULL,
  `modus` ENUM('terminal','backend') NOT NULL DEFAULT 'terminal',
  `offline_erlaubt_kommen_gehen` TINYINT(1) NOT NULL DEFAULT 1,
  `offline_erlaubt_auftraege` TINYINT(1) NOT NULL DEFAULT 1,
  `auto_logout_timeout_sekunden` INT UNSIGNED NOT NULL DEFAULT 60,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_terminal_abteilung` (`abteilung_id`),
  KEY `idx_terminal_aktiv` (`aktiv`),
  CONSTRAINT `fk_terminal_abteilung`
    FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: auftrag (optional/minimal)
-- --------------------------------------------------------
CREATE TABLE `auftrag` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `auftragsnummer` VARCHAR(100) NOT NULL,
  `kurzbeschreibung` VARCHAR(255) NULL,
  `kunde` VARCHAR(255) NULL,
  `status` VARCHAR(50) NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_auftrag_auftragsnummer` (`auftragsnummer`),
  KEY `idx_auftrag_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: config (Key-Value-Konfiguration)
-- --------------------------------------------------------
CREATE TABLE `config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schluessel` VARCHAR(190) NOT NULL,
  `wert` TEXT NULL,
  `typ` VARCHAR(50) NULL,
  `beschreibung` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_config_schluessel` (`schluessel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: zeit_rundungsregel
-- --------------------------------------------------------
CREATE TABLE `zeit_rundungsregel` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `von_uhrzeit` TIME NOT NULL,
  `bis_uhrzeit` TIME NOT NULL,
  `einheit_minuten` INT UNSIGNED NOT NULL,
  `richtung` ENUM('auf','ab','naechstgelegen') NOT NULL,
  `gilt_fuer` ENUM('kommen','gehen','beide') NOT NULL DEFAULT 'beide',
  `prioritaet` INT UNSIGNED NOT NULL DEFAULT 1,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `beschreibung` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: pausenfenster (betriebliche Pausenfenster)
--
-- Zweck (T-072, Teil 1):
-- - Pflege von festen Pausenfenstern (Uhrzeitfenster), die später bei der Tages-/Report-Berechnung
--   abgezogen werden können.
-- - Gesetzliche Mindestpause wird als Konfig-Keys gepflegt (siehe Backend „Pausenregeln“).
-- --------------------------------------------------------
CREATE TABLE `pausenfenster` (
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

CREATE TABLE `pausenentscheidung` (
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


-- --------------------------------------------------------
-- Tabelle: sonstiges_grund (konfigurierbare Sonstiges-Gründe)
-- --------------------------------------------------------
CREATE TABLE `sonstiges_grund` (
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
INSERT INTO `sonstiges_grund`
  (`code`, `titel`, `default_stunden`, `begruendung_pflicht`, `aktiv`, `sort_order`, `kommentar`)
VALUES
  ('SoU', 'Sonderurlaub', 8.00, 1, 1, 1, NULL);

-- --------------------------------------------------------
-- Tabelle: betriebsferien
-- --------------------------------------------------------
CREATE TABLE `betriebsferien` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `von_datum` DATE NOT NULL,
  `bis_datum` DATE NOT NULL,
  `beschreibung` VARCHAR(255) NULL,
  `abteilung_id` INT UNSIGNED NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_betriebsferien_abteilung` (`abteilung_id`),
  CONSTRAINT `fk_betriebsferien_abteilung`
    FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: feiertag
-- --------------------------------------------------------
CREATE TABLE `feiertag` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `datum` DATE NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `bundesland` VARCHAR(50) NULL,
  `ist_gesetzlich` TINYINT(1) NOT NULL DEFAULT 1,
  `ist_betriebsfrei` TINYINT(1) NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_feiertag_datum_bundesland` (`datum`,`bundesland`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: kurzarbeit_plan
-- --------------------------------------------------------
-- Kurzarbeit kann firmenweit oder mitarbeiterbezogen als Zeitraum geplant werden.
-- Wochentage-Maske: Bit 0=Mo, 1=Di, 2=Mi, 3=Do, 4=Fr, 5=Sa, 6=So
-- Default: 31 (= Mo-Fr)
CREATE TABLE `kurzarbeit_plan` (
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


-- --------------------------------------------------------
-- Tabelle: system_log (optional)
-- --------------------------------------------------------
CREATE TABLE `system_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zeitstempel` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `loglevel` ENUM('debug','info','warn','error') NOT NULL DEFAULT 'info',
  `kategorie` VARCHAR(100) NULL,
  `nachricht` TEXT NOT NULL,
  `daten` TEXT NULL,
  `mitarbeiter_id` INT UNSIGNED NULL,
  `terminal_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_system_log_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_system_log_terminal` (`terminal_id`),
  CONSTRAINT `fk_system_log_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_system_log_terminal`
    FOREIGN KEY (`terminal_id`) REFERENCES `terminal`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: db_injektionsqueue (Offline-Queue)
-- --------------------------------------------------------
CREATE TABLE `db_injektionsqueue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('offen','verarbeitet','fehler') NOT NULL DEFAULT 'offen',
  `sql_befehl` LONGTEXT NOT NULL,
  `fehlernachricht` TEXT NULL,
  `letzte_ausfuehrung` DATETIME NULL,
  `versuche` INT UNSIGNED NOT NULL DEFAULT 0,
  `meta_mitarbeiter_id` INT UNSIGNED NULL,
  `meta_terminal_id` INT UNSIGNED NULL,
  `meta_aktion` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_db_injektionsqueue_status` (`status`),
  KEY `idx_db_injektionsqueue_meta_mitarbeiter` (`meta_mitarbeiter_id`),
  KEY `idx_db_injektionsqueue_meta_terminal` (`meta_terminal_id`),
  CONSTRAINT `fk_db_injektionsqueue_mitarbeiter`
    FOREIGN KEY (`meta_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_db_injektionsqueue_terminal`
    FOREIGN KEY (`meta_terminal_id`) REFERENCES `terminal`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: mitarbeiter_hat_rolle (M:N)
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_rolle` (
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `rolle_id` INT UNSIGNED NOT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`mitarbeiter_id`,`rolle_id`),
  KEY `idx_mitarbeiter_hat_rolle_rolle` (`rolle_id`),
  CONSTRAINT `fk_mitarbeiter_hat_rolle_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mitarbeiter_hat_rolle_rolle`
    FOREIGN KEY (`rolle_id`) REFERENCES `rolle`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: mitarbeiter_hat_recht (Overrides: Allow/Deny pro Mitarbeiter)
--
-- Zweck:
-- - Ergänzt die Rollen-Rechte um optionale Overrides je Mitarbeiter.
-- - `erlaubt=1` => Recht explizit erlauben
-- - `erlaubt=0` => Recht explizit entziehen
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_recht` (
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

-- --------------------------------------------------------
-- Tabelle: mitarbeiter_hat_rolle_scope (scoped Rollen-Zuweisung)
--
-- Zweck:
-- - Vorbereitung für skalierbare Bereichsrechte (z. B. Abteilung/Unterbereiche).
-- - Aktuell wird `scope_typ='global'` verwendet; weitere Scopes können später ausgewertet werden.
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_rolle_scope` (
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


-- --------------------------------------------------------
-- Tabelle: mitarbeiter_hat_abteilung (M:N)
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_abteilung` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `abteilung_id` INT UNSIGNED NOT NULL,
  `ist_stammabteilung` TINYINT(1) NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mitarbeiter_abteilung` (`mitarbeiter_id`,`abteilung_id`),
  KEY `idx_mitarbeiter_hat_abteilung_abteilung` (`abteilung_id`),
  CONSTRAINT `fk_mitarbeiter_hat_abteilung_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mitarbeiter_hat_abteilung_abteilung`
    FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: mitarbeiter_genehmiger
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_genehmiger` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `genehmiger_mitarbeiter_id` INT UNSIGNED NOT NULL,
  `prioritaet` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `kommentar` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mitarbeiter_genehmiger` (`mitarbeiter_id`,`genehmiger_mitarbeiter_id`),
  KEY `idx_mitarbeiter_genehmiger_genehmiger` (`genehmiger_mitarbeiter_id`),
  CONSTRAINT `fk_mitarbeiter_genehmiger_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mitarbeiter_genehmiger_genehmiger`
    FOREIGN KEY (`genehmiger_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: zeitbuchung (Rohdaten Kommen/Gehen)
-- --------------------------------------------------------
CREATE TABLE `zeitbuchung` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `typ` ENUM('kommen','gehen') NOT NULL,
  `zeitstempel` DATETIME NOT NULL,
  `quelle` ENUM('terminal','web','import') NOT NULL DEFAULT 'terminal',
  `manuell_geaendert` TINYINT(1) NOT NULL DEFAULT 0,
  `kommentar` VARCHAR(255) NULL,
  `terminal_id` INT UNSIGNED NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_zeitbuchung_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_zeitbuchung_zeitstempel` (`zeitstempel`),
  KEY `idx_zeitbuchung_terminal` (`terminal_id`),
  CONSTRAINT `fk_zeitbuchung_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_zeitbuchung_terminal`
    FOREIGN KEY (`terminal_id`) REFERENCES `terminal`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: tageswerte_mitarbeiter
-- --------------------------------------------------------
CREATE TABLE `tageswerte_mitarbeiter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `datum` DATE NOT NULL,
  `kommen_roh` DATETIME NULL,
  `gehen_roh` DATETIME NULL,
  `kommen_korr` DATETIME NULL,
  `gehen_korr` DATETIME NULL,
  `pause_korr_minuten` INT UNSIGNED NOT NULL DEFAULT 0,
  `ist_stunden` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `arzt_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `krank_lfz_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `krank_kk_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `feiertag_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `kurzarbeit_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `urlaub_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `sonstige_stunden` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `kennzeichen_arzt` TINYINT(1) NOT NULL DEFAULT 0,
  `kennzeichen_krank_lfz` TINYINT(1) NOT NULL DEFAULT 0,
  `kennzeichen_krank_kk` TINYINT(1) NOT NULL DEFAULT 0,
  `kennzeichen_feiertag` TINYINT(1) NOT NULL DEFAULT 0,
  `kennzeichen_kurzarbeit` TINYINT(1) NOT NULL DEFAULT 0,
  `kennzeichen_urlaub` TINYINT(1) NOT NULL DEFAULT 0,
  `kennzeichen_sonstiges` TINYINT(1) NOT NULL DEFAULT 0,
  `rohdaten_manuell_geaendert` TINYINT(1) NOT NULL DEFAULT 0,
  `felder_manuell_geaendert` TINYINT(1) NOT NULL DEFAULT 0,
  `kommentar` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tageswerte_mitarbeiter_datum` (`mitarbeiter_id`,`datum`),
  KEY `idx_tageswerte_datum` (`datum`),
  CONSTRAINT `fk_tageswerte_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: monatswerte_mitarbeiter (optional)
-- --------------------------------------------------------
CREATE TABLE `monatswerte_mitarbeiter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `jahr` SMALLINT UNSIGNED NOT NULL,
  `monat` TINYINT UNSIGNED NOT NULL,
  `soll_stunden` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `ist_stunden` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `ueberstunden` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `urlaubstage_genommen` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `urlaubstage_verbleibend` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_monatswerte_mitarbeiter_monat` (`mitarbeiter_id`,`jahr`,`monat`),
  KEY `idx_monatswerte_jahr_monat` (`jahr`,`monat`),
  CONSTRAINT `fk_monatswerte_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: stundenkonto_batch
--
-- Zweck:
-- - Rueckwirk-Korrekturen auf mehrere Tage ("Verteilungen") als Batch speichern.
-- --------------------------------------------------------
CREATE TABLE `stundenkonto_batch` (
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

-- --------------------------------------------------------
-- Tabelle: stundenkonto_korrektur
--
-- Zweck:
-- - Ledger fuer Stundenkonto: Delta-Minuten pro Tag (positiv/negativ).
-- - `typ` unterscheidet manuelle Korrektur vs. Verteilung (Batch).
-- --------------------------------------------------------
CREATE TABLE `stundenkonto_korrektur` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `wirksam_datum` DATE NOT NULL,
  `delta_minuten` INT NOT NULL,
  `typ` ENUM('manuell','verteilung') NOT NULL,
  `batch_id` INT UNSIGNED NULL,
  `begruendung` VARCHAR(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` INT UNSIGNED NULL,
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

-- --------------------------------------------------------
-- Tabelle: urlaubsantrag
-- --------------------------------------------------------
CREATE TABLE `urlaubsantrag` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `von_datum` DATE NOT NULL,
  `bis_datum` DATE NOT NULL,
  `tage_gesamt` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('offen','genehmigt','abgelehnt','storniert') NOT NULL DEFAULT 'offen',
  `antrags_datum` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entscheidungs_mitarbeiter_id` INT UNSIGNED NULL,
  `entscheidungs_datum` DATETIME NULL,
  `kommentar_mitarbeiter` TEXT NULL,
  `kommentar_genehmiger` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_urlaubsantrag_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_urlaubsantrag_status` (`status`),
  KEY `idx_urlaubsantrag_zeitraum` (`von_datum`,`bis_datum`),
  KEY `idx_urlaubsantrag_entscheider` (`entscheidungs_mitarbeiter_id`),
  CONSTRAINT `fk_urlaubsantrag_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_urlaubsantrag_entscheider`
    FOREIGN KEY (`entscheidungs_mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: urlaub_kontingent_jahr
--
-- Zweck (T-021 Teil 2):
-- - Pro Mitarbeiter und Kalenderjahr können hier Werte gepflegt werden, die den Urlaubssaldo beeinflussen.
-- - `anspruch_override_tage` ist optional. Wenn gesetzt, überschreibt dieser Wert den Standard-
--   Anspruch aus `mitarbeiter.urlaub_monatsanspruch * 12`.
-- - `uebertrag_tage` ist der Übertrag aus dem Vorjahr.
-- - `korrektur_tage` dient für manuelle Korrekturen (z. B. Sonderurlaub, Nachbuchungen, Korrektur +/-).
--
-- Hinweis:
-- - In der MVP-Logik werden `uebertrag_tage` + `korrektur_tage` immer zusätzlich zum Anspruch berücksichtigt.
-- --------------------------------------------------------
CREATE TABLE `urlaub_kontingent_jahr` (
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

-- --------------------------------------------------------
-- Tabelle: auftragszeit (Haupt- & Nebenaufträge)
-- --------------------------------------------------------
CREATE TABLE `auftragszeit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` INT UNSIGNED NOT NULL,
  `auftrag_id` INT UNSIGNED NULL,
  `auftragscode` VARCHAR(100) NULL,
  `arbeitsschritt_code` VARCHAR(100) NULL,
  `maschine_id` INT UNSIGNED NULL,
  `terminal_id` INT UNSIGNED NULL,
  `typ` ENUM('haupt','neben') NOT NULL DEFAULT 'haupt',
  `startzeit` DATETIME NOT NULL,
  `endzeit` DATETIME NULL,
  `status` ENUM('laufend','abgeschlossen','abgebrochen') NOT NULL DEFAULT 'laufend',
  `kommentar` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auftragszeit_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_auftragszeit_auftrag` (`auftrag_id`),
  KEY `idx_auftragszeit_auftrag_arbeitsschritt` (`auftrag_id`,`arbeitsschritt_code`),
  KEY `idx_auftragszeit_code_arbeitsschritt` (`auftragscode`,`arbeitsschritt_code`),
  KEY `idx_auftragszeit_maschine` (`maschine_id`),
  KEY `idx_auftragszeit_startzeit` (`startzeit`),
  KEY `idx_auftragszeit_status` (`status`),
  CONSTRAINT `fk_auftragszeit_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_auftragszeit_auftrag`
    FOREIGN KEY (`auftrag_id`) REFERENCES `auftrag`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_auftragszeit_maschine`
    FOREIGN KEY (`maschine_id`) REFERENCES `maschine`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_auftragszeit_terminal`
    FOREIGN KEY (`terminal_id`) REFERENCES `terminal`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
