-- Initiales Datenbankschema für Zeiterfassung / Mitarbeiter- und Auftragsmanagement
-- Quelle: sql/zeiterfassung_aktuell.sql

SET NAMES utf8mb4;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `zeiterfassung`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `zeiterfassung`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `abteilung`
-- --------------------------------------------------------
CREATE TABLE `abteilung` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_abteilung_parent` (`parent_id`),
  KEY `idx_abteilung_name` (`name`),
  CONSTRAINT `abteilung_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_abteilung_parent` FOREIGN KEY (`parent_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `auftrag`
-- --------------------------------------------------------
CREATE TABLE `auftrag` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `auftragsnummer` varchar(100) NOT NULL,
  `kurzbeschreibung` varchar(255) DEFAULT NULL,
  `kunde` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_auftrag_auftragsnummer` (`auftragsnummer`),
  KEY `idx_auftrag_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `auftragszeit`
-- --------------------------------------------------------
CREATE TABLE `auftragszeit` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `auftrag_id` bigint(20) UNSIGNED DEFAULT NULL,
  `auftragscode` varchar(100) DEFAULT NULL,
  `arbeitsschritt_code` varchar(100) DEFAULT NULL,
  `maschine_id` bigint(20) UNSIGNED DEFAULT NULL,
  `terminal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `typ` enum('haupt','neben') NOT NULL DEFAULT 'haupt',
  `startzeit` datetime NOT NULL,
  `endzeit` datetime DEFAULT NULL,
  `status` enum('laufend','abgeschlossen','abgebrochen') NOT NULL DEFAULT 'laufend',
  `kommentar` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auftragszeit_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_auftragszeit_auftrag` (`auftrag_id`),
  KEY `idx_auftragszeit_maschine` (`maschine_id`),
  KEY `idx_auftragszeit_startzeit` (`startzeit`),
  KEY `idx_auftragszeit_status` (`status`),
  KEY `fk_auftragszeit_terminal` (`terminal_id`),
  KEY `idx_auftragszeit_auftrag_arbeitsschritt` (`auftrag_id`,`arbeitsschritt_code`),
  KEY `idx_auftragszeit_code_arbeitsschritt` (`auftragscode`,`arbeitsschritt_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `betriebsferien`
-- --------------------------------------------------------
CREATE TABLE `betriebsferien` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `beschreibung` varchar(255) DEFAULT NULL,
  `abteilung_id` bigint(20) UNSIGNED DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_betriebsferien_abteilung` (`abteilung_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `config`
-- --------------------------------------------------------
CREATE TABLE `config` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schluessel` varchar(190) NOT NULL,
  `wert` text DEFAULT NULL,
  `typ` varchar(50) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_config_schluessel` (`schluessel`)
) ENGINE=InnoDB AUTO_INCREMENT=88080 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `db_injektionsqueue`
-- --------------------------------------------------------
CREATE TABLE `db_injektionsqueue` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('offen','verarbeitet','fehler') NOT NULL DEFAULT 'offen',
  `sql_befehl` longtext NOT NULL,
  `fehlernachricht` text DEFAULT NULL,
  `letzte_ausfuehrung` datetime DEFAULT NULL,
  `versuche` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `meta_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meta_terminal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meta_aktion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_db_injektionsqueue_status` (`status`),
  KEY `idx_db_injektionsqueue_meta_mitarbeiter` (`meta_mitarbeiter_id`),
  KEY `idx_db_injektionsqueue_meta_terminal` (`meta_terminal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `feiertag`
-- --------------------------------------------------------
CREATE TABLE `feiertag` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `bundesland` varchar(50) DEFAULT NULL,
  `ist_gesetzlich` tinyint(1) NOT NULL DEFAULT 1,
  `ist_betriebsfrei` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_feiertag_datum_bundesland` (`datum`,`bundesland`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `krankzeitraum`
-- --------------------------------------------------------
CREATE TABLE `krankzeitraum` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `typ` enum('lfz','kk') NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date DEFAULT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_krankzeitraum_mitarbeiter_dates` (`mitarbeiter_id`,`von_datum`,`bis_datum`,`aktiv`),
  KEY `idx_krankzeitraum_typ` (`typ`),
  KEY `idx_krankzeitraum_angelegt_von` (`angelegt_von_mitarbeiter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `kurzarbeit_plan`
-- --------------------------------------------------------
CREATE TABLE `kurzarbeit_plan` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` enum('firma','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  `mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `wochentage_mask` tinyint(3) UNSIGNED NOT NULL DEFAULT 31,
  `modus` enum('stunden','prozent') NOT NULL DEFAULT 'stunden',
  `wert` decimal(6,2) NOT NULL DEFAULT 0.00,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kurzarbeit_plan_scope_dates` (`scope`,`von_datum`,`bis_datum`,`aktiv`),
  KEY `idx_kurzarbeit_plan_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_kurzarbeit_plan_angelegt_von` (`angelegt_von_mitarbeiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `maschine`
-- --------------------------------------------------------
CREATE TABLE `maschine` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `abteilung_id` bigint(20) UNSIGNED DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maschine_abteilung` (`abteilung_id`),
  KEY `idx_maschine_aktiv` (`aktiv`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `mitarbeiter`
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personalnummer` varchar(32) DEFAULT NULL,
  `vorname` varchar(100) NOT NULL,
  `nachname` varchar(100) NOT NULL,
  `geburtsdatum` date DEFAULT NULL,
  `eintrittsdatum` date DEFAULT NULL,
  `wochenarbeitszeit` decimal(5,2) NOT NULL DEFAULT 0.00,
  `urlaub_monatsanspruch` decimal(5,2) NOT NULL DEFAULT 0.00,
  `benutzername` varchar(150) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `passwort_hash` varchar(255) DEFAULT NULL,
  `rfid_code` varchar(64) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `ist_login_berechtigt` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mitarbeiter_benutzername` (`benutzername`),
  UNIQUE KEY `uniq_mitarbeiter_email` (`email`),
  UNIQUE KEY `uniq_mitarbeiter_rfid` (`rfid_code`),
  UNIQUE KEY `uniq_mitarbeiter_personalnummer` (`personalnummer`),
  KEY `idx_mitarbeiter_name` (`nachname`,`vorname`),
  KEY `idx_mitarbeiter_aktiv` (`aktiv`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `mitarbeiter_genehmiger`
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_genehmiger` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `genehmiger_mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `prioritaet` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mitarbeiter_genehmiger` (`mitarbeiter_id`,`genehmiger_mitarbeiter_id`),
  KEY `idx_mitarbeiter_genehmiger_genehmiger` (`genehmiger_mitarbeiter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_abteilung`
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_abteilung` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `abteilung_id` bigint(20) UNSIGNED NOT NULL,
  `ist_stammabteilung` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mitarbeiter_abteilung` (`mitarbeiter_id`,`abteilung_id`),
  KEY `idx_mitarbeiter_hat_abteilung_abteilung` (`abteilung_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_recht`
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_recht` (
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `recht_id` bigint(20) UNSIGNED NOT NULL,
  `erlaubt` tinyint(1) NOT NULL DEFAULT 1,
  `notiz` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`mitarbeiter_id`,`recht_id`),
  KEY `idx_mhr_recht` (`recht_id`),
  KEY `idx_mhr_erlaubt` (`erlaubt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_rolle`
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_rolle` (
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `rolle_id` bigint(20) UNSIGNED NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mitarbeiter_id`,`rolle_id`),
  KEY `idx_mitarbeiter_hat_rolle_rolle` (`rolle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_rolle_scope`
-- --------------------------------------------------------
CREATE TABLE `mitarbeiter_hat_rolle_scope` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `rolle_id` bigint(20) UNSIGNED NOT NULL,
  `scope_typ` enum('global','abteilung') NOT NULL DEFAULT 'global',
  `scope_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `gilt_unterbereiche` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mhrs` (`mitarbeiter_id`,`rolle_id`,`scope_typ`,`scope_id`),
  KEY `idx_mhrs_rolle` (`rolle_id`),
  KEY `idx_mhrs_scope` (`scope_typ`,`scope_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `monatswerte_mitarbeiter`
-- --------------------------------------------------------
CREATE TABLE `monatswerte_mitarbeiter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `jahr` smallint(5) UNSIGNED NOT NULL,
  `monat` tinyint(3) UNSIGNED NOT NULL,
  `soll_stunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ist_stunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ueberstunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `urlaubstage_genommen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `urlaubstage_verbleibend` decimal(5,2) NOT NULL DEFAULT 0.00,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_monatswerte_mitarbeiter_monat` (`mitarbeiter_id`,`jahr`,`monat`),
  KEY `idx_monatswerte_jahr_monat` (`jahr`,`monat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `pausenentscheidung`
-- --------------------------------------------------------
CREATE TABLE `pausenentscheidung` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `entscheidung` enum('ABZIEHEN','NICHT_ABZIEHEN') NOT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pausenentscheidung_mid_datum` (`mitarbeiter_id`,`datum`),
  KEY `idx_pausenentscheidung_datum` (`datum`),
  KEY `idx_pausenentscheidung_mid` (`mitarbeiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `pausenfenster`
-- --------------------------------------------------------
CREATE TABLE `pausenfenster` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `von_uhrzeit` time NOT NULL,
  `bis_uhrzeit` time NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pausenfenster_aktiv_sort` (`aktiv`,`sort_order`,`von_uhrzeit`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daten für Tabelle `pausenfenster`
-- --------------------------------------------------------
INSERT INTO `pausenfenster` (`id`, `von_uhrzeit`, `bis_uhrzeit`, `sort_order`, `kommentar`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, '09:00:00', '09:15:00', 10, 'Frühstückspause', 1, '2026-01-08 05:51:41', '2026-01-08 05:51:41'),
(2, '12:30:00', '13:00:00', 10, 'Mittag', 1, '2026-01-08 05:51:57', '2026-01-08 05:51:57');

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `recht`
-- --------------------------------------------------------
CREATE TABLE `recht` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_recht_code` (`code`),
  KEY `idx_recht_aktiv` (`aktiv`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daten für Tabelle `recht`
-- --------------------------------------------------------
INSERT INTO `recht` (`id`, `code`, `name`, `beschreibung`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'URLAUB_GENEHMIGEN', 'Urlaub genehmigen (zugewiesene Mitarbeiter)', 'Darf Urlaubsanträge genehmigen/ablehnen (typisch: nur für Mitarbeiter, für die man als Genehmiger eingetragen ist).', 1, '2026-01-01 12:33:18', '2026-01-01 12:33:18'),
(2, 'URLAUB_GENEHMIGEN_ALLE', 'Urlaub genehmigen (alle Mitarbeiter)', 'Darf Urlaubsanträge aller Mitarbeiter genehmigen/ablehnen (Chef/Personalbüro).', 1, '2026-01-01 12:33:18', '2026-01-01 12:33:18'),
(3, 'URLAUB_GENEHMIGEN_SELF', 'Urlaub genehmigen (eigene Anträge)', 'Darf eigene Urlaubsanträge selbst genehmigen/ablehnen (z. B. Chef).', 1, '2026-01-01 12:33:18', '2026-01-01 12:33:18'),
(4, 'ZEIT_EDIT_SELF', 'Zeitbuchungen bearbeiten (eigene)', 'Darf eigene Zeitbuchungen nachträglich bearbeiten (Audit/Markierung erforderlich).', 0, '2026-01-01 12:33:18', '2026-01-11 11:01:15'),
(5, 'ZEIT_EDIT_ALLE', 'Zeitbuchungen bearbeiten (alle)', 'Darf Zeitbuchungen aller Mitarbeiter nachträglich bearbeiten (Audit/Markierung erforderlich).', 0, '2026-01-01 12:33:18', '2026-01-11 11:01:15'),
(6, 'REPORT_MONAT_ALLE', 'Monatsreports einsehen (alle)', 'Darf Monatsübersichten/PDFs für alle Mitarbeiter einsehen/erzeugen.', 0, '2026-01-01 12:33:18', '2026-01-11 11:01:15'),
(7, 'ROLLEN_RECHTE_VERWALTEN', 'Rollen/Rechte verwalten', 'Darf Rollen und deren Rechtezuweisungen administrieren.', 1, '2026-01-01 12:33:18', '2026-01-01 12:33:18'),
(8, 'ZEITBUCHUNG_EDIT_SELF', 'Zeitbuchungen bearbeiten (eigene)', 'Erlaubt das Korrigieren von eigenen Zeitbuchungen (add/update/delete) im Backend inkl. Audit-Log.', 1, '2026-01-01 16:15:18', '2026-01-01 16:15:18'),
(9, 'ZEITBUCHUNG_EDIT_ALL', 'Zeitbuchungen bearbeiten (alle Mitarbeiter)', 'Erlaubt das Korrigieren von Zeitbuchungen aller Mitarbeiter (add/update/delete) im Backend inkl. Audit-Log.', 1, '2026-01-01 16:15:18', '2026-01-01 16:15:18'),
(10, 'REPORT_MONAT_VIEW_ALL', 'Monatsreport (alle) ansehen', 'Darf Monatsübersichten/PDFs für beliebige Mitarbeiter anzeigen/erzeugen.', 1, '2026-01-01 16:15:29', '2026-01-01 16:15:29'),
(11, 'REPORT_MONAT_EXPORT_ALL', 'Monatsreport (alle) exportieren', 'Darf einen Sammel-Export (ZIP) für einen Monat erzeugen (pro Mitarbeiter 1 PDF).', 1, '2026-01-01 16:15:29', '2026-01-01 16:15:29'),
(12, 'REPORTS_ANSEHEN_ALLE', 'Reports aller Mitarbeiter ansehen', 'Darf Monats-/PDF-Reports für andere Mitarbeiter ansehen/exportieren.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(13, 'ZEITBUCHUNG_EDITIEREN_SELF', 'Eigene Zeitbuchungen bearbeiten', 'Darf eigene Zeitbuchungen korrigieren (mit Audit/Begründung).', 0, '2026-01-01 16:15:38', '2026-01-11 11:01:15'),
(14, 'ZEITBUCHUNG_EDITIEREN_ALLE', 'Zeitbuchungen aller bearbeiten', 'Darf Zeitbuchungen anderer Mitarbeiter korrigieren (mit Audit/Begründung).', 0, '2026-01-01 16:15:38', '2026-01-11 11:01:15'),
(15, 'MITARBEITER_VERWALTEN', 'Mitarbeiter verwalten', 'Darf Mitarbeiter anlegen/bearbeiten.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(16, 'ABTEILUNG_VERWALTEN', 'Abteilungen verwalten', 'Darf Abteilungen anlegen/bearbeiten.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(17, 'MASCHINEN_VERWALTEN', 'Maschinen verwalten', 'Darf Maschinen anlegen/bearbeiten.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(18, 'FEIERTAGE_VERWALTEN', 'Feiertage verwalten', 'Darf Feiertage anlegen/bearbeiten.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(19, 'BETRIEBSFERIEN_VERWALTEN', 'Betriebsferien verwalten', 'Darf Betriebsferien anlegen/bearbeiten.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(20, 'QUEUE_VERWALTEN', 'Offline-Queue verwalten', 'Darf Offline-Queue einsehen/clear/retry.', 1, '2026-01-01 16:15:38', '2026-01-01 16:15:38'),
(21, 'TERMINAL_VERWALTEN', 'Terminals verwalten', 'Darf Terminals anlegen/bearbeiten.', 1, '2026-01-01 16:15:39', '2026-01-01 16:15:39'),
(22, 'ZEIT_RUNDUNGSREGELN_VERWALTEN', 'Zeit-Rundungsregeln verwalten', 'Darf Zeit-Rundungsregeln anlegen/bearbeiten/aktivieren.', 1, '2026-01-01 16:20:52', '2026-01-01 16:20:52'),
(23, 'KONFIGURATION_VERWALTEN', 'Konfiguration verwalten', 'Darf Konfigurationseinträge (Key/Value) anlegen/bearbeiten.', 1, '2026-01-01 16:20:52', '2026-01-01 16:20:52'),
(24, 'URLAUB_KONTINGENT_VERWALTEN', 'Urlaub-Kontingent verwalten', 'Darf Urlaubskontingente/Übertrag/Korrekturen pro Mitarbeiter und Jahr pflegen.', 1, '2026-01-01 16:20:52', '2026-01-01 16:20:52'),
(25, 'PAUSENREGELN_VERWALTEN', 'Pausenregeln verwalten', 'Darf betriebliche Pausenfenster (Zwangspausen) anlegen/bearbeiten.', 1, '2026-01-03 09:17:30', '2026-01-03 09:17:30'),
(26, 'KRANKZEITRAUM_VERWALTEN', 'Krankzeitraum verwalten', 'Darf Krank-Zeiträume pro Mitarbeiter pflegen (Lohnfortzahlung/Krankenkasse).', 1, '2026-01-03 09:17:30', '2026-01-03 09:17:30'),
(27, 'KURZARBEIT_VERWALTEN', 'Kurzarbeit verwalten', 'Darf Kurzarbeit planen und Zeiträume pflegen.', 1, '2026-01-03 09:17:30', '2026-01-03 09:17:30'),
(28, 'DASHBOARD_ZEITWARNUNGEN_SEHEN', 'Dashboard: Zeitwarnungen sehen', 'Darf den Dashboard-Warnblock für unplausible/unvollständige Kommen/Gehen-Stempel sehen.', 1, '2026-01-07 08:36:06', '2026-01-07 08:36:06'),
(29, 'STUNDENKONTO_VERWALTEN', 'Stundenkonto verwalten', 'Darf Stundenkonto-Korrekturen und Verteilungen im Backend erfassen.', 1, '2026-01-17 16:49:40', '2026-01-17 16:49:40');

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `rolle`
-- --------------------------------------------------------
CREATE TABLE `rolle` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `ist_superuser` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rolle_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daten für Tabelle `rolle`
-- --------------------------------------------------------
INSERT INTO `rolle` (`id`, `name`, `beschreibung`, `aktiv`, `ist_superuser`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'Chef', 'Vollzugriff auf alle Adminfunktionen', 1, 1, '2025-11-30 05:30:47', '2026-01-07 12:16:34');

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `rolle_hat_recht`
-- --------------------------------------------------------
CREATE TABLE `rolle_hat_recht` (
  `rolle_id` bigint(20) UNSIGNED NOT NULL,
  `recht_id` bigint(20) UNSIGNED NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rolle_id`,`recht_id`),
  KEY `idx_rolle_hat_recht_recht` (`recht_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daten für Tabelle `rolle_hat_recht`
-- --------------------------------------------------------
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`, `erstellt_am`) VALUES
(1, 1, '2026-01-04 06:40:56'),
(1, 2, '2026-01-04 06:40:56'),
(1, 3, '2026-01-04 06:40:56'),
(1, 4, '2026-01-04 06:40:56'),
(1, 5, '2026-01-04 06:40:56'),
(1, 6, '2026-01-04 06:40:56'),
(1, 7, '2026-01-04 06:40:56'),
(1, 8, '2026-01-04 06:40:56'),
(1, 9, '2026-01-04 06:40:56'),
(1, 10, '2026-01-04 06:40:56'),
(1, 11, '2026-01-04 06:40:56'),
(1, 12, '2026-01-04 06:40:56'),
(1, 13, '2026-01-04 06:40:56'),
(1, 14, '2026-01-04 06:40:56'),
(1, 15, '2026-01-04 06:40:56'),
(1, 16, '2026-01-04 06:40:56'),
(1, 17, '2026-01-04 06:40:56'),
(1, 18, '2026-01-04 06:40:56'),
(1, 19, '2026-01-04 06:40:56'),
(1, 20, '2026-01-04 06:40:56'),
(1, 21, '2026-01-04 06:40:56'),
(1, 22, '2026-01-04 06:40:56'),
(1, 23, '2026-01-04 06:40:56'),
(1, 24, '2026-01-04 06:40:56'),
(1, 25, '2026-01-04 06:40:56'),
(1, 26, '2026-01-04 06:40:56'),
(1, 27, '2026-01-04 06:40:56'),
(1, 28, '2026-01-07 08:36:06'),
(1, 29, '2026-01-17 16:49:40');

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `sonstiges_grund`
-- --------------------------------------------------------
CREATE TABLE `sonstiges_grund` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `titel` varchar(80) NOT NULL,
  `default_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `begruendung_pflicht` tinyint(1) NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sonstiges_grund_code` (`code`),
  KEY `idx_sonstiges_grund_aktiv_sort` (`aktiv`,`sort_order`,`titel`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daten für Tabelle `sonstiges_grund`
-- --------------------------------------------------------
INSERT INTO `sonstiges_grund` (`id`, `code`, `titel`, `default_stunden`, `begruendung_pflicht`, `aktiv`, `sort_order`, `kommentar`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'SoU', 'Sonderurlaub', 8.00, 1, 1, 1, NULL, '2026-01-04 10:53:16', '2026-01-04 10:53:16');

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `stundenkonto_batch`
-- --------------------------------------------------------
CREATE TABLE `stundenkonto_batch` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `modus` enum('gesamt_gleichmaessig','minuten_pro_tag') NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `gesamt_minuten` int(11) DEFAULT NULL,
  `minuten_pro_tag` int(11) DEFAULT NULL,
  `nur_arbeitstage` tinyint(1) NOT NULL DEFAULT 1,
  `begruendung` varchar(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stealth` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stundenkonto_batch_mitarbeiter_datum` (`mitarbeiter_id`,`von_datum`,`bis_datum`),
  KEY `idx_stundenkonto_batch_erstellt_von` (`erstellt_von_mitarbeiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `stundenkonto_korrektur`
-- --------------------------------------------------------
CREATE TABLE `stundenkonto_korrektur` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `wirksam_datum` date NOT NULL,
  `delta_minuten` int(11) NOT NULL,
  `typ` enum('manuell','verteilung') NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `begruendung` varchar(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stealth` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stundenkonto_korr_mitarbeiter_datum` (`mitarbeiter_id`,`wirksam_datum`),
  KEY `idx_stundenkonto_korr_batch` (`batch_id`),
  KEY `idx_stundenkonto_korr_typ` (`typ`),
  KEY `fk_stundenkonto_korr_erstellt_von` (`erstellt_von_mitarbeiter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `system_log`
-- --------------------------------------------------------
CREATE TABLE `system_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `zeitstempel` datetime NOT NULL DEFAULT current_timestamp(),
  `loglevel` enum('debug','info','warn','error') NOT NULL DEFAULT 'info',
  `kategorie` varchar(100) DEFAULT NULL,
  `nachricht` text NOT NULL,
  `daten` text DEFAULT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `terminal_id` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_system_log_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_system_log_terminal` (`terminal_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `tageswerte_mitarbeiter`
-- --------------------------------------------------------
CREATE TABLE `tageswerte_mitarbeiter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `kommen_roh` datetime DEFAULT NULL,
  `gehen_roh` datetime DEFAULT NULL,
  `kommen_korr` datetime DEFAULT NULL,
  `gehen_korr` datetime DEFAULT NULL,
  `pause_korr_minuten` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pause_override_aktiv` tinyint(1) NOT NULL DEFAULT 0,
  `pause_override_begruendung` varchar(255) DEFAULT NULL,
  `pause_override_gesetzt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pause_override_gesetzt_am` datetime DEFAULT NULL,
  `ist_stunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `arzt_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `krank_lfz_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `krank_kk_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `feiertag_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `kurzarbeit_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `urlaub_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sonstige_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `kennzeichen_arzt` tinyint(1) NOT NULL DEFAULT 0,
  `kennzeichen_krank_lfz` tinyint(1) NOT NULL DEFAULT 0,
  `kennzeichen_krank_kk` tinyint(1) NOT NULL DEFAULT 0,
  `kennzeichen_feiertag` tinyint(1) NOT NULL DEFAULT 0,
  `kennzeichen_kurzarbeit` tinyint(1) NOT NULL DEFAULT 0,
  `kennzeichen_urlaub` tinyint(1) NOT NULL DEFAULT 0,
  `kennzeichen_sonstiges` tinyint(1) NOT NULL DEFAULT 0,
  `rohdaten_manuell_geaendert` tinyint(1) NOT NULL DEFAULT 0,
  `felder_manuell_geaendert` tinyint(1) NOT NULL DEFAULT 0,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tageswerte_mitarbeiter_datum` (`mitarbeiter_id`,`datum`),
  KEY `idx_tageswerte_datum` (`datum`),
  KEY `idx_pause_override_gesetzt_von` (`pause_override_gesetzt_von_mitarbeiter_id`),
  CONSTRAINT `fk_pause_override_gesetzt_von` FOREIGN KEY (`pause_override_gesetzt_von_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `terminal`
-- --------------------------------------------------------
CREATE TABLE `terminal` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `standort_beschreibung` varchar(255) DEFAULT NULL,
  `abteilung_id` bigint(20) UNSIGNED DEFAULT NULL,
  `modus` enum('terminal','backend') NOT NULL DEFAULT 'terminal',
  `offline_erlaubt_kommen_gehen` tinyint(1) NOT NULL DEFAULT 1,
  `offline_erlaubt_auftraege` tinyint(1) NOT NULL DEFAULT 1,
  `auto_logout_timeout_sekunden` int(10) UNSIGNED NOT NULL DEFAULT 60,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_terminal_abteilung` (`abteilung_id`),
  KEY `idx_terminal_aktiv` (`aktiv`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `urlaubsantrag`
-- --------------------------------------------------------
CREATE TABLE `urlaubsantrag` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `tage_gesamt` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` enum('offen','genehmigt','abgelehnt','storniert') NOT NULL DEFAULT 'offen',
  `antrags_datum` datetime NOT NULL DEFAULT current_timestamp(),
  `entscheidungs_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entscheidungs_datum` datetime DEFAULT NULL,
  `kommentar_mitarbeiter` text DEFAULT NULL,
  `kommentar_genehmiger` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_urlaubsantrag_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_urlaubsantrag_status` (`status`),
  KEY `idx_urlaubsantrag_zeitraum` (`von_datum`,`bis_datum`),
  KEY `idx_urlaubsantrag_entscheider` (`entscheidungs_mitarbeiter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `urlaub_kontingent_jahr`
-- --------------------------------------------------------
CREATE TABLE `urlaub_kontingent_jahr` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `jahr` smallint(5) UNSIGNED NOT NULL,
  `anspruch_override_tage` decimal(6,2) DEFAULT NULL,
  `uebertrag_tage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `korrektur_tage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `notiz` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_urlaub_kontingent_jahr` (`mitarbeiter_id`,`jahr`),
  KEY `idx_urlaub_kontingent_jahr_jahr` (`jahr`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `zeitbuchung`
-- --------------------------------------------------------
CREATE TABLE `zeitbuchung` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `typ` enum('kommen','gehen') NOT NULL,
  `zeitstempel` datetime NOT NULL,
  `quelle` enum('terminal','web','import') NOT NULL DEFAULT 'terminal',
  `manuell_geaendert` tinyint(1) NOT NULL DEFAULT 0,
  `kommentar` varchar(255) DEFAULT NULL,
  `nachtshift` tinyint(1) NOT NULL DEFAULT 0,
  `terminal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_zeitbuchung_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_zeitbuchung_zeitstempel` (`zeitstempel`),
  KEY `idx_zeitbuchung_terminal` (`terminal_id`)
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabellenstruktur für Tabelle `zeit_rundungsregel`
-- --------------------------------------------------------
CREATE TABLE `zeit_rundungsregel` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `von_uhrzeit` time NOT NULL,
  `bis_uhrzeit` time NOT NULL,
  `einheit_minuten` int(10) UNSIGNED NOT NULL,
  `richtung` enum('auf','ab','naechstgelegen') NOT NULL,
  `gilt_fuer` enum('kommen','gehen','beide') NOT NULL DEFAULT 'beide',
  `prioritaet` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `beschreibung` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
