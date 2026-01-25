-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 18. Jan 2026 um 10:09
-- Server-Version: 11.8.3-MariaDB-deb11
-- PHP-Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `zeiterfassung`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `abteilung`
--

CREATE TABLE `abteilung` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `abteilung`
--

INSERT INTO `abteilung` (`id`, `name`, `beschreibung`, `parent_id`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'Fräserrei', 'Fräserrei', NULL, 1, '2025-11-30 07:05:44', '2025-11-30 07:05:44'),
(2, 'Dreherei', 'Drehmaschinen', NULL, 1, '2026-01-11 11:58:10', '2026-01-11 11:58:10'),
(3, 'Pumpenbau', '', NULL, 1, '2026-01-11 11:58:28', '2026-01-11 11:58:28'),
(4, 'Schlosserei', 'Schweissen und Blechbearbeitung', NULL, 1, '2026-01-11 11:59:16', '2026-01-11 11:59:16'),
(5, 'Zuschnitt', '', NULL, 1, '2026-01-11 11:59:24', '2026-01-11 11:59:24'),
(6, 'Montage', '', NULL, 1, '2026-01-11 11:59:30', '2026-01-11 11:59:30'),
(7, 'Lackiererei', '', NULL, 1, '2026-01-11 12:00:03', '2026-01-11 12:00:03');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auftrag`
--

CREATE TABLE `auftrag` (
  `id` int(10) UNSIGNED NOT NULL,
  `auftragsnummer` varchar(100) NOT NULL,
  `kurzbeschreibung` varchar(255) DEFAULT NULL,
  `kunde` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auftragszeit`
--

CREATE TABLE `auftragszeit` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `auftrag_id` int(10) UNSIGNED DEFAULT NULL,
  `auftragscode` varchar(100) DEFAULT NULL,
  `arbeitsschritt_code` varchar(100) DEFAULT NULL,
  `maschine_id` int(10) UNSIGNED DEFAULT NULL,
  `terminal_id` int(10) UNSIGNED DEFAULT NULL,
  `typ` enum('haupt','neben') NOT NULL DEFAULT 'haupt',
  `startzeit` datetime NOT NULL,
  `endzeit` datetime DEFAULT NULL,
  `status` enum('laufend','abgeschlossen','abgebrochen') NOT NULL DEFAULT 'laufend',
  `kommentar` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `auftragszeit`
--

INSERT INTO `auftragszeit` (`id`, `mitarbeiter_id`, `auftrag_id`, `auftragscode`, `arbeitsschritt_code`, `maschine_id`, `terminal_id`, `typ`, `startzeit`, `endzeit`, `status`, `kommentar`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, NULL, '11', NULL, 1, NULL, 'haupt', '2026-01-02 16:30:59', '2026-01-02 16:31:17', 'abgeschlossen', NULL, '2026-01-02 16:30:59', '2026-01-02 16:31:17'),
(4, 2, NULL, '123123', NULL, NULL, NULL, 'haupt', '2026-01-18 09:56:16', NULL, 'laufend', NULL, '2026-01-18 09:56:16', '2026-01-18 09:56:16');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `betriebsferien`
--

CREATE TABLE `betriebsferien` (
  `id` int(10) UNSIGNED NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `beschreibung` varchar(255) DEFAULT NULL,
  `abteilung_id` int(10) UNSIGNED DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `betriebsferien`
--

INSERT INTO `betriebsferien` (`id`, `von_datum`, `bis_datum`, `beschreibung`, `abteilung_id`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, '2025-11-15', '2025-12-31', '2025 - jahresurlaub tilgen für alle workarround', NULL, 0, '2025-12-21 17:19:25', '2026-01-18 07:54:13'),
(2, '2026-05-15', '2026-05-15', 'Brückentag', NULL, 1, '2026-01-11 16:58:43', '2026-01-11 16:58:43'),
(3, '2026-12-18', '2026-12-31', 'Weihnachtsurlaub 2026 - ende', NULL, 1, '2026-01-11 17:00:10', '2026-01-11 17:03:45'),
(4, '2026-01-01', '2026-01-04', 'Weihnachtsurlaub 2026 - anfang', NULL, 1, '2026-01-11 17:03:34', '2026-01-11 17:03:34');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `config`
--

CREATE TABLE `config` (
  `id` int(10) UNSIGNED NOT NULL,
  `schluessel` varchar(190) NOT NULL,
  `wert` text DEFAULT NULL,
  `typ` varchar(50) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `config`
--

INSERT INTO `config` (`id`, `schluessel`, `wert`, `typ`, `beschreibung`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'terminal_timeout_standard', '60', 'int', 'Terminal: Auto-Logout Standard (Sekunden). Default 60.', '2025-12-21 10:23:55', '2025-12-21 10:23:55'),
(2, 'terminal_timeout_urlaub', '180', 'int', 'Terminal: Auto-Logout im Urlaub-Kontext (Sekunden). Default 180.', '2025-12-21 10:23:55', '2025-12-21 10:23:55'),
(41, 'zeit_rundung_beim_buchen', '1', 'bool', 'Zeiten: Wenn true, wird der Zeitstempel beim Buchen gerundet (Kommen/Gehen). Wenn false, wird Rohzeit gespeichert; Rundung muss dann in Anzeige/Reports erfolgen.', '2025-12-21 12:25:40', '2025-12-21 12:25:40'),
(227, 'terminal_session_idle_timeout', '300', 'int', 'Terminal: serverseitiges Session-Idle-Timeout (Sekunden). Fallback, falls JS-Auto-Logout nicht greift. Default 300.', '2025-12-31 21:08:33', '2025-12-31 21:08:33'),
(228, 'urlaub_blocke_negativen_resturlaub', '0', 'bool', 'Urlaub: Wenn aktiv (1), werden Urlaubsanträge blockiert, wenn der Resturlaub dadurch negativ würde. Default 0.', '2025-12-31 21:08:33', '2025-12-31 21:08:33'),
(5897, 'terminal_healthcheck_interval', '10', 'int', 'Terminal: Intervall (Sekunden) für wiederkehrende Healthchecks (Hauptdatenbank/Offline-Queue Anzeige). Default 10.', '2026-01-11 06:38:46', '2026-01-11 06:38:46'),
(31548, 'micro_buchung_max_sekunden', '180', 'int', 'Zeitbuchungen: Mikro-Buchungen (Kommen/Gehen) bis zu X Sekunden werden standardmäßig ignoriert/ausgeblendet. Default 180 (= 3 Minuten).', '2026-01-14 12:33:45', '2026-01-14 12:33:45');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `db_injektionsqueue`
--

CREATE TABLE `db_injektionsqueue` (
  `id` int(10) UNSIGNED NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('offen','verarbeitet','fehler') NOT NULL DEFAULT 'offen',
  `sql_befehl` longtext NOT NULL,
  `fehlernachricht` text DEFAULT NULL,
  `letzte_ausfuehrung` datetime DEFAULT NULL,
  `versuche` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `meta_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `meta_terminal_id` int(10) UNSIGNED DEFAULT NULL,
  `meta_aktion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `feiertag`
--

CREATE TABLE `feiertag` (
  `id` int(10) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `bundesland` varchar(50) DEFAULT NULL,
  `ist_gesetzlich` tinyint(1) NOT NULL DEFAULT 1,
  `ist_betriebsfrei` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `feiertag`
--

INSERT INTO `feiertag` (`id`, `datum`, `name`, `bundesland`, `ist_gesetzlich`, `ist_betriebsfrei`, `erstellt_am`, `geaendert_am`) VALUES
(1, '2025-01-01', 'Neujahr', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(2, '2025-05-01', 'Tag der Arbeit', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(3, '2025-10-03', 'Tag der Deutschen Einheit', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(4, '2025-12-25', '1. Weihnachtstag', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(5, '2025-12-26', '2. Weihnachtstag', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(6, '2025-04-18', 'Karfreitag', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(7, '2025-04-21', 'Ostermontag', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(8, '2025-05-29', 'Christi Himmelfahrt', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(9, '2025-06-09', 'Pfingstmontag', NULL, 1, 1, '2025-11-30 07:04:58', '2025-11-30 07:04:58'),
(10, '2026-01-01', 'Neujahr', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(11, '2026-05-01', 'Tag der Arbeit', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(12, '2026-10-03', 'Tag der Deutschen Einheit', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(13, '2026-12-25', '1. Weihnachtstag', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(14, '2026-12-26', '2. Weihnachtstag', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(15, '2026-04-03', 'Karfreitag', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(16, '2026-04-06', 'Ostermontag', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(17, '2026-05-14', 'Christi Himmelfahrt', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(18, '2026-05-25', 'Pfingstmontag', NULL, 1, 1, '2025-12-21 10:25:16', '2025-12-21 10:25:16'),
(19, '2024-01-01', 'Neujahr', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(20, '2024-05-01', 'Tag der Arbeit', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(21, '2024-10-03', 'Tag der Deutschen Einheit', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(22, '2024-12-25', '1. Weihnachtstag', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(23, '2024-12-26', '2. Weihnachtstag', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(24, '2024-03-29', 'Karfreitag', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(25, '2024-04-01', 'Ostermontag', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(26, '2024-05-09', 'Christi Himmelfahrt', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46'),
(27, '2024-05-20', 'Pfingstmontag', NULL, 1, 1, '2026-01-18 07:42:46', '2026-01-18 07:42:46');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `krankzeitraum`
--

CREATE TABLE `krankzeitraum` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `typ` enum('lfz','kk') NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date DEFAULT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `krankzeitraum`
--

INSERT INTO `krankzeitraum` (`id`, `mitarbeiter_id`, `typ`, `von_datum`, `bis_datum`, `kommentar`, `aktiv`, `angelegt_von_mitarbeiter_id`, `erstellt_am`, `geaendert_am`) VALUES
(1, 1, 'lfz', '2025-12-29', '2026-02-01', NULL, 1, 2, '2026-01-03 13:04:14', '2026-01-03 13:04:14');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kurzarbeit_plan`
--

CREATE TABLE `kurzarbeit_plan` (
  `id` int(10) UNSIGNED NOT NULL,
  `scope` enum('firma','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  `mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `wochentage_mask` tinyint(3) UNSIGNED NOT NULL DEFAULT 31,
  `modus` enum('stunden','prozent') NOT NULL DEFAULT 'stunden',
  `wert` decimal(6,2) NOT NULL DEFAULT 0.00,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `maschine`
--

CREATE TABLE `maschine` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `abteilung_id` int(10) UNSIGNED DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `maschine`
--

INSERT INTO `maschine` (`id`, `name`, `abteilung_id`, `beschreibung`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'KaoMing', 1, NULL, 1, '2026-01-01 20:20:47', '2026-01-11 12:00:25'),
(2, 'CLX 500', 2, 'Dreh-/Fräs- Zentrum', 1, '2026-01-11 12:00:55', '2026-01-11 12:00:55'),
(3, 'Cyclon1000', 2, NULL, 1, '2026-01-11 12:01:14', '2026-01-11 12:01:14'),
(4, 'BTF1000', 1, NULL, 1, '2026-01-11 12:01:26', '2026-01-11 12:01:26');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter`
--

CREATE TABLE `mitarbeiter` (
  `id` int(10) UNSIGNED NOT NULL,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitarbeiter`
--

INSERT INTO `mitarbeiter` (`id`, `personalnummer`, `vorname`, `nachname`, `geburtsdatum`, `eintrittsdatum`, `wochenarbeitszeit`, `urlaub_monatsanspruch`, `benutzername`, `email`, `passwort_hash`, `rfid_code`, `aktiv`, `ist_login_berechtigt`, `erstellt_am`, `geaendert_am`) VALUES
(1, '34', 'Hans1', 'Wurst', '1972-10-03', NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 0, 0, '2025-11-30 05:30:47', '2026-01-11 19:24:47'),
(2, '8', 'Manuel', 'Kleespies', '1982-07-19', '2016-10-01', 40.00, 2.50, 'ManuelKleespies', 'zero.c@web.de', '$2y$10$MiGTJ8fEujWjgGo0aXXdv.abGjaXJDsJ0TdMGGcMcgYqOPd44Rbf6', NULL, 1, 1, '2025-11-30 06:41:39', '2026-01-18 07:46:37'),
(3, '20', 'Dietmar', 'Rüppel', '1975-02-02', NULL, 40.00, 2.50, 'Dietmar_Rueppel', 'dietmarrueppel@web.de', NULL, NULL, 1, 0, '2026-01-11 07:46:48', '2026-01-11 07:46:48'),
(4, NULL, 'Marc', 'Crispens', NULL, NULL, 40.00, 2.50, 'MarcCrispens', 'm.crispens@wernig.com', '$2y$10$nzJUOZIULBoOOj6JLVGhHOeCiSc9bPkmTpOVGWgTkMk2RMV2Ait8O', NULL, 1, 1, '2026-01-11 11:44:35', '2026-01-11 11:44:35'),
(5, '9', 'Arno', 'Seitz', NULL, NULL, 40.00, 2.50, 'ArnoSeitz', 'a.seitz@wernig.com', '$2y$10$ob.W736ofFkniF0vPt6GUO3YRXC41v6MSuElVzRzSy18RTmhlu8Si', NULL, 1, 1, '2026-01-11 11:45:29', '2026-01-11 11:54:14'),
(6, '98', 'Marcus', 'Weitzel', NULL, NULL, 40.00, 2.50, 'MarcusWeitzel', 'm.weitzel@wernig.com', '$2y$10$wF9BaGarqug.h4B7zEEUQuNR18A255uCZ8GUTIdh2fJ8Dgy42k6mO', NULL, 1, 1, '2026-01-11 11:46:15', '2026-01-11 11:46:15'),
(7, '568', 'Karin', 'Schmidt', NULL, NULL, 25.00, 2.50, 'KarinSchmidt', 'k.schmidt@wernig.com', '$2y$10$PkpmuGKGa.Rz.I4bad38FupEVvyE5fMXiW4s/8O9lbxWi0GfGQWJy', NULL, 1, 1, '2026-01-11 11:55:14', '2026-01-11 11:55:14'),
(8, NULL, 'Chris', 'Smith', NULL, NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 11:56:56', '2026-01-11 11:56:56'),
(9, NULL, 'Jan', 'Goy', NULL, NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 11:57:33', '2026-01-11 11:57:33'),
(10, NULL, 'Jerzy', 'Solka', '1974-03-16', NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 12:08:53', '2026-01-11 12:08:53'),
(11, NULL, 'Randy', 'Löwer', NULL, NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 12:09:35', '2026-01-11 12:09:35'),
(12, '12', 'Sascha', 'Klein', '2026-01-29', NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 13:32:20', '2026-01-11 19:23:05'),
(13, NULL, 'Thorsten', 'Weitzel', NULL, NULL, 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 13:33:02', '2026-01-11 13:33:02'),
(14, '1', 'Stefan', 'Leber', NULL, NULL, 40.00, 2.50, 'StefanLeber', 's.leber@wernig.com', '$2y$10$8jIjWcpJUe62yPh3zHUZaupw.7qKe/TOZ5TYnJt1yQo0czlmWVFUe', 'asdf', 1, 1, '2026-01-11 13:34:01', '2026-01-18 08:03:12');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter_genehmiger`
--

CREATE TABLE `mitarbeiter_genehmiger` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `genehmiger_mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `prioritaet` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitarbeiter_genehmiger`
--

INSERT INTO `mitarbeiter_genehmiger` (`id`, `mitarbeiter_id`, `genehmiger_mitarbeiter_id`, `prioritaet`, `kommentar`, `erstellt_am`) VALUES
(4, 1, 2, 1, NULL, '2026-01-11 11:56:05');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_abteilung`
--

CREATE TABLE `mitarbeiter_hat_abteilung` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `abteilung_id` int(10) UNSIGNED NOT NULL,
  `ist_stammabteilung` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitarbeiter_hat_abteilung`
--

INSERT INTO `mitarbeiter_hat_abteilung` (`id`, `mitarbeiter_id`, `abteilung_id`, `ist_stammabteilung`, `erstellt_am`) VALUES
(1, 13, 3, 0, '2026-01-11 16:32:29'),
(2, 13, 4, 0, '2026-01-11 16:32:30'),
(3, 13, 5, 1, '2026-01-11 16:32:30'),
(4, 9, 2, 1, '2026-01-11 16:32:41'),
(7, 11, 4, 1, '2026-01-11 16:33:16'),
(8, 11, 5, 0, '2026-01-11 16:33:16'),
(9, 8, 6, 0, '2026-01-11 16:33:29'),
(10, 8, 7, 1, '2026-01-11 16:33:29'),
(11, 10, 1, 1, '2026-01-11 16:33:39'),
(14, 12, 4, 1, '2026-01-11 19:23:05'),
(16, 2, 2, 1, '2026-01-18 07:46:37');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_recht`
--

CREATE TABLE `mitarbeiter_hat_recht` (
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `recht_id` int(10) UNSIGNED NOT NULL,
  `erlaubt` tinyint(1) NOT NULL DEFAULT 1,
  `notiz` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_rolle`
--

CREATE TABLE `mitarbeiter_hat_rolle` (
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `rolle_id` int(10) UNSIGNED NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitarbeiter_hat_rolle`
--

INSERT INTO `mitarbeiter_hat_rolle` (`mitarbeiter_id`, `rolle_id`, `erstellt_am`) VALUES
(2, 1, '2026-01-18 07:46:37'),
(4, 5, '2026-01-11 11:44:35'),
(5, 5, '2026-01-11 11:54:14'),
(6, 5, '2026-01-11 11:46:15'),
(7, 2, '2026-01-11 11:55:14'),
(14, 1, '2026-01-11 13:34:01');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_rolle_scope`
--

CREATE TABLE `mitarbeiter_hat_rolle_scope` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `rolle_id` int(10) UNSIGNED NOT NULL,
  `scope_typ` enum('global','abteilung') NOT NULL DEFAULT 'global',
  `scope_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gilt_unterbereiche` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitarbeiter_hat_rolle_scope`
--

INSERT INTO `mitarbeiter_hat_rolle_scope` (`id`, `mitarbeiter_id`, `rolle_id`, `scope_typ`, `scope_id`, `gilt_unterbereiche`, `erstellt_am`) VALUES
(1, 4, 5, 'global', 0, 1, '2026-01-11 11:44:35'),
(3, 6, 5, 'global', 0, 1, '2026-01-11 11:46:15'),
(4, 5, 5, 'global', 0, 1, '2026-01-11 11:54:14'),
(5, 7, 2, 'global', 0, 1, '2026-01-11 11:55:14'),
(8, 14, 1, 'global', 0, 1, '2026-01-11 13:34:01'),
(13, 2, 1, 'global', 0, 1, '2026-01-18 07:46:37');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `monatswerte_mitarbeiter`
--

CREATE TABLE `monatswerte_mitarbeiter` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `jahr` smallint(5) UNSIGNED NOT NULL,
  `monat` tinyint(3) UNSIGNED NOT NULL,
  `soll_stunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ist_stunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ueberstunden` decimal(6,2) NOT NULL DEFAULT 0.00,
  `urlaubstage_genommen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `urlaubstage_verbleibend` decimal(5,2) NOT NULL DEFAULT 0.00,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pausenentscheidung`
--

CREATE TABLE `pausenentscheidung` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `entscheidung` enum('ABZIEHEN','NICHT_ABZIEHEN') NOT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_von_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pausenfenster`
--

CREATE TABLE `pausenfenster` (
  `id` int(10) UNSIGNED NOT NULL,
  `von_uhrzeit` time NOT NULL,
  `bis_uhrzeit` time NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `pausenfenster`
--

INSERT INTO `pausenfenster` (`id`, `von_uhrzeit`, `bis_uhrzeit`, `sort_order`, `kommentar`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, '09:00:00', '09:15:00', 10, 'Frühstückspause', 1, '2026-01-08 05:51:41', '2026-01-08 05:51:41'),
(2, '12:30:00', '13:00:00', 10, 'Mittag', 1, '2026-01-08 05:51:57', '2026-01-08 05:51:57');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `recht`
--

CREATE TABLE `recht` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `recht`
--

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

--
-- Tabellenstruktur für Tabelle `rolle`
--

CREATE TABLE `rolle` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `ist_superuser` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `rolle`
--

INSERT INTO `rolle` (`id`, `name`, `beschreibung`, `aktiv`, `ist_superuser`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'Chef', 'Vollzugriff auf alle Adminfunktionen', 1, 1, '2025-11-30 05:30:47', '2026-01-07 12:16:34'),
(2, 'Personalbüro', 'Verwaltung von Mitarbeitern, Urlaub und Stammdaten', 1, 0, '2025-11-30 05:30:47', '2025-11-30 05:30:47'),
(5, 'Arbeitsvorbereitung', 'Vorgesetzte', 1, 0, '2026-01-11 11:26:00', '2026-01-11 11:26:25');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rolle_hat_recht`
--

CREATE TABLE `rolle_hat_recht` (
  `rolle_id` int(10) UNSIGNED NOT NULL,
  `recht_id` int(10) UNSIGNED NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `rolle_hat_recht`
--

INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`, `erstellt_am`) VALUES
(1, 1, '2026-01-04 06:40:56'),
(1, 2, '2026-01-04 06:40:56'),
(1, 3, '2026-01-04 06:40:56'),
(1, 7, '2026-01-04 06:40:56'),
(1, 8, '2026-01-04 06:40:56'),
(1, 9, '2026-01-04 06:40:56'),
(1, 10, '2026-01-04 06:40:56'),
(1, 11, '2026-01-04 06:40:56'),
(1, 12, '2026-01-04 06:40:56'),
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
(1, 29, '2026-01-17 16:49:40'),
(2, 1, '2026-01-11 11:27:09'),
(2, 7, '2026-01-11 11:27:09'),
(2, 9, '2026-01-11 11:27:09'),
(2, 10, '2026-01-11 11:27:09'),
(2, 11, '2026-01-11 11:27:09'),
(2, 12, '2026-01-11 11:27:09'),
(2, 18, '2026-01-11 11:27:09'),
(2, 19, '2026-01-11 11:27:09'),
(2, 22, '2026-01-11 11:27:09'),
(2, 23, '2026-01-11 11:27:09'),
(2, 24, '2026-01-11 11:27:09'),
(2, 25, '2026-01-11 11:27:09'),
(2, 26, '2026-01-11 11:27:09'),
(2, 27, '2026-01-11 11:27:09'),
(2, 28, '2026-01-11 11:27:09'),
(5, 1, '2026-01-11 11:26:25'),
(5, 2, '2026-01-11 11:26:25'),
(5, 15, '2026-01-11 11:26:25'),
(5, 16, '2026-01-11 11:26:25'),
(5, 17, '2026-01-11 11:26:25'),
(5, 26, '2026-01-11 11:26:25'),
(5, 27, '2026-01-11 11:26:25'),
(5, 28, '2026-01-11 11:26:25');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sonstiges_grund`
--

CREATE TABLE `sonstiges_grund` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `titel` varchar(80) NOT NULL,
  `default_stunden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `begruendung_pflicht` tinyint(1) NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `sonstiges_grund`
--

INSERT INTO `sonstiges_grund` (`id`, `code`, `titel`, `default_stunden`, `begruendung_pflicht`, `aktiv`, `sort_order`, `kommentar`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'SoU', 'Sonderurlaub', 8.00, 1, 1, 1, NULL, '2026-01-04 10:53:16', '2026-01-04 10:53:16');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stundenkonto_batch`
--

CREATE TABLE `stundenkonto_batch` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `modus` enum('gesamt_gleichmaessig','minuten_pro_tag') NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `gesamt_minuten` int(11) DEFAULT NULL,
  `minuten_pro_tag` int(11) DEFAULT NULL,
  `nur_arbeitstage` tinyint(1) NOT NULL DEFAULT 1,
  `begruendung` varchar(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `stealth` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stundenkonto_korrektur`
--

CREATE TABLE `stundenkonto_korrektur` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `wirksam_datum` date NOT NULL,
  `delta_minuten` int(11) NOT NULL,
  `typ` enum('manuell','verteilung') NOT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `begruendung` varchar(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `stealth` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `stundenkonto_korrektur`
--

INSERT INTO `stundenkonto_korrektur` (`id`, `mitarbeiter_id`, `wirksam_datum`, `delta_minuten`, `typ`, `batch_id`, `begruendung`, `erstellt_von_mitarbeiter_id`, `stealth`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, '2025-12-31', -9405, 'manuell', NULL, 'Monatsabschluss 2025-12', 2, 0, '2026-01-18 07:58:51', '2026-01-18 07:58:51');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `system_log`
--

CREATE TABLE `system_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `zeitstempel` datetime NOT NULL DEFAULT current_timestamp(),
  `loglevel` enum('debug','info','warn','error') NOT NULL DEFAULT 'info',
  `kategorie` varchar(100) DEFAULT NULL,
  `nachricht` text NOT NULL,
  `daten` text DEFAULT NULL,
  `mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `terminal_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `system_log`
--

INSERT INTO `system_log` (`id`, `zeitstempel`, `loglevel`, `kategorie`, `nachricht`, `daten`, `mitarbeiter_id`, `terminal_id`) VALUES
(642, '2026-01-11 19:58:23', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(643, '2026-01-11 19:58:34', 'error', 'frontend', 'Unbehandelter Fehler im Front-Controller', '{\"seite\":\"report_monat\",\"exception\":\"ReportService::holeMonatsdatenFuerMitarbeiter(): Return value must be of type array, none returned\"}', NULL, NULL),
(644, '2026-01-11 20:15:04', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(645, '2026-01-11 20:15:04', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(646, '2026-01-11 20:21:47', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(647, '2026-01-11 20:21:47', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(648, '2026-01-11 20:21:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":12,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 12, NULL),
(649, '2026-01-11 20:21:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":12,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 12, NULL),
(650, '2026-01-11 20:21:53', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(651, '2026-01-11 20:21:53', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(652, '2026-01-11 20:22:18', 'info', 'zeitservice', 'Synchronisierung Tageswerte abgeschlossen', '{\"mitarbeiter_id\":5,\"datum\":\"2026-01-11\",\"kommen_roh\":\"2026-01-11 06:00:00\",\"gehen_roh\":null,\"pause_min\":0,\"pause_quelle\":\"auto\",\"pause_db_min\":0,\"felder_manuell\":0}', 5, NULL),
(653, '2026-01-11 20:22:18', 'info', 'zeitbuchung_audit', 'Zeitbuchung korrigiert', '{\"aktion\":\"add\",\"buchung_id\":69,\"ziel_mitarbeiter_id\":5,\"datum\":\"2026-01-11\",\"alt\":null,\"neu\":{\"id\":69,\"typ\":\"kommen\",\"zeitstempel\":\"2026-01-11 06:00:00\",\"kommentar\":\"testen\",\"quelle\":\"web\",\"manuell_geaendert\":1},\"begruendung\":\"testen\"}', 2, NULL),
(654, '2026-01-11 21:09:32', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(655, '2026-01-12 06:46:11', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(656, '2026-01-12 06:46:11', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(657, '2026-01-12 06:46:24', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(658, '2026-01-12 06:46:24', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(659, '2026-01-12 06:46:28', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(660, '2026-01-12 06:46:28', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(661, '2026-01-12 06:46:34', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(662, '2026-01-12 06:46:34', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(663, '2026-01-12 06:46:45', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(664, '2026-01-12 06:46:45', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(665, '2026-01-12 06:48:14', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(666, '2026-01-12 06:48:14', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(667, '2026-01-12 06:48:16', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(668, '2026-01-12 06:48:16', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(669, '2026-01-12 06:48:37', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(670, '2026-01-12 06:48:37', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(671, '2026-01-12 06:48:52', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(672, '2026-01-12 06:48:52', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(673, '2026-01-12 06:49:12', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(674, '2026-01-12 06:49:12', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(675, '2026-01-12 06:49:33', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(676, '2026-01-12 06:49:33', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(677, '2026-01-12 12:55:07', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(678, '2026-01-12 18:06:51', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(679, '2026-01-12 18:07:37', 'info', 'zeitservice', 'Synchronisierung Tageswerte abgeschlossen', '{\"mitarbeiter_id\":5,\"datum\":\"2026-01-11\",\"kommen_roh\":\"2026-01-11 06:00:00\",\"gehen_roh\":\"2026-01-11 14:00:00\",\"pause_min\":45,\"pause_quelle\":\"auto\",\"pause_db_min\":0,\"felder_manuell\":0}', 5, NULL),
(680, '2026-01-12 18:07:37', 'info', 'zeitbuchung_audit', 'Zeitbuchung korrigiert', '{\"aktion\":\"add\",\"buchung_id\":71,\"ziel_mitarbeiter_id\":5,\"datum\":\"2026-01-11\",\"alt\":null,\"neu\":{\"id\":71,\"typ\":\"gehen\",\"zeitstempel\":\"2026-01-11 14:00:00\",\"kommentar\":\"test\",\"quelle\":\"web\",\"manuell_geaendert\":1},\"begruendung\":\"test\"}', 2, NULL),
(681, '2026-01-12 18:07:42', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(682, '2026-01-12 18:07:42', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(683, '2026-01-12 18:07:43', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":12,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 12, NULL),
(684, '2026-01-12 18:07:43', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":12,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 12, NULL),
(685, '2026-01-12 19:06:34', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(686, '2026-01-12 19:06:34', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(687, '2026-01-12 19:06:40', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(688, '2026-01-12 19:06:40', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(689, '2026-01-13 12:32:59', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"Manuelkleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(690, '2026-01-14 12:33:45', 'info', 'config', 'Default-Config-Werte wurden automatisch angelegt (fehlende Keys).', '{\"keys\":[\"terminal_timeout_standard\",\"terminal_timeout_urlaub\",\"terminal_session_idle_timeout\",\"urlaub_blocke_negativen_resturlaub\",\"terminal_healthcheck_interval\",\"micro_buchung_max_sekunden\"]}', NULL, NULL),
(691, '2026-01-14 12:33:57', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"Manuelkleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(692, '2026-01-14 12:34:43', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(693, '2026-01-14 12:34:43', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(694, '2026-01-14 12:35:34', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(695, '2026-01-14 12:35:34', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(696, '2026-01-14 12:35:36', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(697, '2026-01-14 12:35:36', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(698, '2026-01-14 12:35:44', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(699, '2026-01-14 12:35:44', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(700, '2026-01-14 12:36:03', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":14,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 14, NULL),
(701, '2026-01-14 12:36:03', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":14,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 14, NULL),
(702, '2026-01-14 12:36:29', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(703, '2026-01-14 12:36:29', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(704, '2026-01-14 12:36:32', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(705, '2026-01-14 12:36:32', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(706, '2026-01-14 12:36:47', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(707, '2026-01-14 12:36:47', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(708, '2026-01-14 12:36:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(709, '2026-01-14 12:36:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(710, '2026-01-14 12:36:51', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(711, '2026-01-14 12:36:51', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":13,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 13, NULL),
(712, '2026-01-14 12:38:39', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(713, '2026-01-14 12:38:39', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(714, '2026-01-14 12:38:54', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(715, '2026-01-14 12:38:54', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(716, '2026-01-14 12:41:57', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(717, '2026-01-16 20:20:54', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(718, '2026-01-16 20:20:54', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(719, '2026-01-16 20:21:12', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(720, '2026-01-16 20:21:12', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(721, '2026-01-16 20:21:29', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(722, '2026-01-16 20:21:29', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(723, '2026-01-16 20:21:35', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(724, '2026-01-16 20:21:35', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(725, '2026-01-17 04:49:53', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(726, '2026-01-17 04:49:53', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(727, '2026-01-17 04:50:00', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(728, '2026-01-17 04:50:00', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(729, '2026-01-17 04:50:21', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(730, '2026-01-17 04:50:21', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(731, '2026-01-17 04:50:28', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(732, '2026-01-17 04:50:28', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(733, '2026-01-17 05:13:48', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(734, '2026-01-17 05:13:48', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(735, '2026-01-17 05:14:03', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(736, '2026-01-17 05:14:03', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(737, '2026-01-17 06:17:38', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(738, '2026-01-17 06:17:38', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(739, '2026-01-17 06:17:40', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(740, '2026-01-17 06:17:40', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(741, '2026-01-17 06:17:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(742, '2026-01-17 06:17:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(743, '2026-01-17 06:17:53', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(744, '2026-01-17 06:17:53', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(745, '2026-01-17 06:17:57', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(746, '2026-01-17 06:17:57', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(747, '2026-01-17 06:18:40', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(748, '2026-01-17 06:18:40', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(749, '2026-01-17 06:19:07', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(750, '2026-01-17 06:19:07', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(751, '2026-01-17 06:19:09', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(752, '2026-01-17 06:19:09', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(753, '2026-01-17 06:32:28', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(754, '2026-01-17 06:32:28', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(755, '2026-01-17 06:32:28', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(756, '2026-01-17 06:32:29', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(757, '2026-01-17 06:32:29', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(758, '2026-01-17 06:32:29', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(759, '2026-01-17 06:32:50', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(760, '2026-01-17 06:32:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(761, '2026-01-17 06:32:50', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(762, '2026-01-17 06:33:07', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(763, '2026-01-17 06:36:59', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(764, '2026-01-17 06:36:59', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(765, '2026-01-17 06:36:59', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(766, '2026-01-17 06:37:01', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(767, '2026-01-17 06:37:01', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(768, '2026-01-17 06:37:01', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(769, '2026-01-17 06:38:03', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(770, '2026-01-17 07:16:45', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(771, '2026-01-17 07:16:46', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(772, '2026-01-17 07:16:46', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(773, '2026-01-17 07:16:47', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(774, '2026-01-17 07:16:47', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(775, '2026-01-17 07:16:47', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(776, '2026-01-17 07:17:47', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(777, '2026-01-17 20:04:43', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(778, '2026-01-17 20:04:43', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(779, '2026-01-17 20:04:43', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(780, '2026-01-17 20:04:45', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(781, '2026-01-17 20:04:45', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(782, '2026-01-17 20:04:45', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(783, '2026-01-17 20:05:01', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(784, '2026-01-17 20:05:01', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(785, '2026-01-17 20:05:01', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(786, '2026-01-17 20:05:08', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(787, '2026-01-17 20:05:08', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(788, '2026-01-17 20:05:15', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(789, '2026-01-17 20:05:15', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(790, '2026-01-17 20:05:15', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(791, '2026-01-17 20:05:18', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(792, '2026-01-17 20:05:18', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(793, '2026-01-17 20:05:18', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(794, '2026-01-17 20:05:20', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(795, '2026-01-17 20:05:20', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(796, '2026-01-17 20:05:20', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(797, '2026-01-17 20:21:21', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(798, '2026-01-18 03:32:58', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(799, '2026-01-18 03:34:22', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(800, '2026-01-18 03:34:22', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(801, '2026-01-18 04:02:58', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(802, '2026-01-18 04:02:59', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(803, '2026-01-18 04:02:59', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(804, '2026-01-18 04:03:00', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(805, '2026-01-18 04:03:00', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(806, '2026-01-18 04:03:21', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(807, '2026-01-18 04:03:21', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(808, '2026-01-18 04:03:21', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(809, '2026-01-18 04:04:22', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(810, '2026-01-18 04:14:07', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(811, '2026-01-18 04:14:07', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(812, '2026-01-18 04:14:16', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(813, '2026-01-18 04:14:16', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(814, '2026-01-18 04:14:24', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(815, '2026-01-18 04:14:24', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL);
INSERT INTO `system_log` (`id`, `zeitstempel`, `loglevel`, `kategorie`, `nachricht`, `daten`, `mitarbeiter_id`, `terminal_id`) VALUES
(816, '2026-01-18 04:14:24', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(817, '2026-01-18 04:14:26', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(818, '2026-01-18 04:14:26', 'warn', 'urlaubservice', 'Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', '{\"mitarbeiter_id\":2,\"jahr\":2025,\"exception\":\"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'arbeitszeit_stunden\' in \'SELECT\'\"}', 2, NULL),
(819, '2026-01-18 04:17:27', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(820, '2026-01-18 07:15:43', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(821, '2026-01-18 07:22:29', 'info', 'auth', 'Login erfolgreich (Benutzername/E-Mail)', '{\"kennung\":\"ManuelKleespies\",\"mitarbeiter_id\":2}', 2, NULL),
(822, '2026-01-18 07:55:09', 'info', 'urlaub', 'Urlaubskontingent gespeichert', '{\"mitarbeiter_id\":2,\"jahr\":2026}', 2, NULL),
(823, '2026-01-18 07:58:19', 'info', 'zeitservice', 'Synchronisierung Tageswerte abgeschlossen', '{\"mitarbeiter_id\":2,\"datum\":\"2025-12-20\",\"kommen_roh\":\"2025-12-20 10:10:14\",\"gehen_roh\":null,\"pause_min\":0,\"pause_quelle\":\"auto\",\"pause_db_min\":0,\"felder_manuell\":0}', 2, NULL),
(824, '2026-01-18 07:58:19', 'info', 'zeitbuchung_audit', 'Zeitbuchung korrigiert', '{\"aktion\":\"delete\",\"buchung_id\":3,\"ziel_mitarbeiter_id\":2,\"datum\":\"2025-12-20\",\"alt\":{\"typ\":\"kommen\",\"zeitstempel\":\"2025-12-20 10:10:18\",\"kommentar\":\"\",\"quelle\":\"terminal\"},\"neu\":null,\"begruendung\":\"1\"}', 2, NULL),
(825, '2026-01-18 07:58:22', 'info', 'zeitservice', 'Synchronisierung Tageswerte abgeschlossen', '{\"mitarbeiter_id\":2,\"datum\":\"2025-12-20\",\"kommen_roh\":\"2025-12-20 10:10:14\",\"gehen_roh\":null,\"pause_min\":0,\"pause_quelle\":\"auto\",\"pause_db_min\":0,\"felder_manuell\":0}', 2, NULL),
(826, '2026-01-18 07:58:22', 'info', 'zeitbuchung_audit', 'Zeitbuchung korrigiert', '{\"aktion\":\"delete\",\"buchung_id\":2,\"ziel_mitarbeiter_id\":2,\"datum\":\"2025-12-20\",\"alt\":{\"typ\":\"kommen\",\"zeitstempel\":\"2025-12-20 10:10:17\",\"kommentar\":\"\",\"quelle\":\"terminal\"},\"neu\":null,\"begruendung\":\"1\"}', 2, NULL),
(827, '2026-01-18 07:58:36', 'info', 'zeitservice', 'Synchronisierung Tageswerte abgeschlossen', '{\"mitarbeiter_id\":2,\"datum\":\"2025-12-20\",\"kommen_roh\":\"2025-12-20 10:10:14\",\"gehen_roh\":\"2025-12-20 22:22:05\",\"pause_min\":45,\"pause_quelle\":\"auto\",\"pause_db_min\":0,\"felder_manuell\":0}', 2, NULL),
(828, '2026-01-18 07:58:36', 'info', 'zeitbuchung_audit', 'Zeitbuchung korrigiert', '{\"aktion\":\"add\",\"buchung_id\":79,\"ziel_mitarbeiter_id\":2,\"datum\":\"2025-12-20\",\"alt\":null,\"neu\":{\"id\":79,\"typ\":\"gehen\",\"zeitstempel\":\"2025-12-20 22:22:05\",\"kommentar\":\"ja\",\"quelle\":\"web\",\"manuell_geaendert\":1},\"begruendung\":\"ja\"}', 2, NULL),
(829, '2026-01-18 07:58:51', 'info', 'stundenkonto', 'Stundenkonto-Monatsabschluss gebucht', '{\"korrektur_id\":1,\"mitarbeiter_id\":2,\"jahr\":2025,\"monat\":12,\"wirksam_datum\":\"2025-12-31\",\"delta_minuten\":-9405,\"begruendung\":\"Monatsabschluss 2025-12\",\"erstellt_von\":2}', 2, NULL),
(830, '2026-01-18 08:01:32', 'info', 'zeitservice', 'Synchronisierung Tageswerte abgeschlossen', '{\"mitarbeiter_id\":2,\"datum\":\"2026-01-18\",\"kommen_roh\":\"2026-01-18 05:05:05\",\"gehen_roh\":null,\"pause_min\":0,\"pause_quelle\":\"auto\",\"pause_db_min\":0,\"felder_manuell\":0}', 2, NULL),
(831, '2026-01-18 08:01:32', 'info', 'zeitbuchung_audit', 'Zeitbuchung korrigiert', '{\"aktion\":\"add\",\"buchung_id\":80,\"ziel_mitarbeiter_id\":2,\"datum\":\"2026-01-18\",\"alt\":null,\"neu\":{\"id\":80,\"typ\":\"kommen\",\"zeitstempel\":\"2026-01-18 05:05:05\",\"kommentar\":\"3\",\"quelle\":\"web\",\"manuell_geaendert\":1},\"begruendung\":\"3\"}', 2, NULL),
(832, '2026-01-18 08:02:30', 'info', 'tageswerte_audit', 'Tageswerte gesetzt: Pause-Override', '{\"ziel_mitarbeiter_id\":2,\"datum\":\"2026-01-18\",\"pause_stunden\":2,\"pause_minuten\":120,\"begruendung\":\"2\"}', 2, NULL),
(833, '2026-01-18 08:03:03', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(834, '2026-01-18 08:03:06', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(835, '2026-01-18 08:03:12', 'info', 'terminal_admin', 'Terminal: RFID-Code zugewiesen', '{\"ziel_mitarbeiter_id\":14,\"ziel_name\":\"Stefan Leber\",\"ziel_personalnummer\":\"1\",\"rfid_code\":\"asdf\"}', 2, NULL),
(836, '2026-01-18 08:03:12', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(837, '2026-01-18 08:03:22', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(838, '2026-01-18 08:03:26', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(839, '2026-01-18 09:20:42', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(840, '2026-01-18 09:20:48', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(841, '2026-01-18 09:20:52', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(842, '2026-01-18 09:21:02', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(843, '2026-01-18 09:21:13', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(844, '2026-01-18 09:55:30', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(845, '2026-01-18 09:56:08', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(846, '2026-01-18 09:56:12', 'error', 'auftrag', 'Fehler beim Erstellen einer Auftragszeit (Model)', '{\"mitarbeiter_id\":2,\"auftrag_id\":null,\"auftragscode\":\"123123\",\"maschine_id\":12,\"terminal_id\":null,\"typ\":\"haupt\",\"exception\":\"SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`zeiterfassung`.`auftragszeit`, CONSTRAINT `fk_auftragszeit_maschine` FOREIGN KEY (`maschine_id`) REFERENCES `maschine` (`id`) ON DELETE SET NULL ON UPDATE CASCADE)\"}', 2, NULL),
(847, '2026-01-18 09:56:12', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(848, '2026-01-18 09:56:16', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(849, '2026-01-18 09:56:20', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(850, '2026-01-18 10:00:52', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(851, '2026-01-18 10:00:53', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(852, '2026-01-18 10:08:36', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(853, '2026-01-18 10:08:39', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(854, '2026-01-18 10:10:57', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus (IST) via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":1,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(855, '2026-01-18 10:49:45', 'error', 'auftrag', 'Fehler beim Laden der Auftragsdetails', '{\"exception\":\"SQLSTATE[HY093]: Invalid parameter number\",\"code\":\"11\"}', NULL, NULL),
(856, '2026-01-18 10:49:51', 'error', 'auftrag', 'Fehler beim Laden der Auftragsdetails', '{\"exception\":\"SQLSTATE[HY093]: Invalid parameter number\",\"code\":\"123123\"}', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tageswerte_mitarbeiter`
--

CREATE TABLE `tageswerte_mitarbeiter` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `kommen_roh` datetime DEFAULT NULL,
  `gehen_roh` datetime DEFAULT NULL,
  `kommen_korr` datetime DEFAULT NULL,
  `gehen_korr` datetime DEFAULT NULL,
  `pause_korr_minuten` int(10) UNSIGNED NOT NULL DEFAULT 0,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `tageswerte_mitarbeiter`
--

INSERT INTO `tageswerte_mitarbeiter` (`id`, `mitarbeiter_id`, `datum`, `kommen_roh`, `gehen_roh`, `kommen_korr`, `gehen_korr`, `pause_korr_minuten`, `ist_stunden`, `arzt_stunden`, `krank_lfz_stunden`, `krank_kk_stunden`, `feiertag_stunden`, `kurzarbeit_stunden`, `urlaub_stunden`, `sonstige_stunden`, `kennzeichen_arzt`, `kennzeichen_krank_lfz`, `kennzeichen_krank_kk`, `kennzeichen_feiertag`, `kennzeichen_kurzarbeit`, `kennzeichen_urlaub`, `kennzeichen_sonstiges`, `rohdaten_manuell_geaendert`, `felder_manuell_geaendert`, `kommentar`, `erstellt_am`, `geaendert_am`) VALUES
(1, 1, '2026-01-02', '2026-01-02 05:57:00', '2026-01-02 16:05:08', NULL, NULL, 0, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-02 08:04:22', '2026-01-02 08:05:22'),
(4, 1, '2026-01-23', '2026-01-23 05:00:00', '2026-01-23 16:50:00', NULL, NULL, 0, 11.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-02 11:01:14', '2026-01-02 11:01:27'),
(6, 2, '2026-01-02', NULL, NULL, NULL, NULL, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-02 16:32:02', '2026-01-07 08:20:57'),
(9, 1, '2026-01-03', NULL, NULL, NULL, NULL, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 8.00, 0.00, 0.00, 0, 0, 0, 0, 1, 0, 0, 0, 1, NULL, '2026-01-03 12:56:33', '2026-01-03 12:56:33'),
(10, 1, '2026-02-02', '2026-02-02 05:00:00', '2026-02-02 16:00:00', NULL, NULL, 45, 10.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-03 13:42:21', '2026-01-03 13:42:34'),
(12, 2, '2026-01-04', NULL, NULL, NULL, NULL, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-07 07:41:42', '2026-01-07 15:00:00'),
(15, 2, '2026-01-05', '2026-01-05 05:30:00', '2026-01-05 15:37:00', NULL, NULL, 45, 9.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-07 08:19:46', '2026-01-07 08:20:11'),
(23, 2, '2026-01-03', NULL, NULL, NULL, NULL, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-07 08:23:14', '2026-01-07 08:23:14'),
(28, 2, '2026-01-07', '2026-01-07 07:59:00', '2026-01-07 15:00:00', NULL, NULL, 45, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-01-07 15:00:23', '2026-01-09 06:58:12'),
(30, 2, '2026-01-19', '2026-01-19 07:01:00', '2026-01-19 15:59:05', NULL, NULL, 45, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-09 09:55:14', '2026-01-09 09:55:29'),
(32, 1, '2026-01-09', '2026-01-09 12:56:00', '2026-01-09 16:00:00', NULL, NULL, 0, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-10 08:09:09', '2026-01-10 08:09:09'),
(33, 2, '2026-01-12', '2026-01-12 01:01:01', '2026-01-12 04:04:04', NULL, NULL, 0, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-10 10:20:19', '2026-01-10 10:20:38'),
(37, 1, '2026-01-10', '2026-01-10 10:16:56', '2026-01-10 16:00:00', NULL, NULL, 30, 5.01, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 07:02:14', '2026-01-11 07:02:14'),
(38, 2, '2026-01-10', '2026-01-10 08:30:52', '2026-01-10 22:00:00', NULL, NULL, 30, 8.88, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 07:02:32', '2026-01-11 07:15:35'),
(40, 2, '2026-01-11', NULL, NULL, NULL, NULL, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 17:04:39', '2026-01-11 17:04:39'),
(41, 5, '2026-01-11', '2026-01-11 06:00:00', '2026-01-11 14:00:00', NULL, NULL, 45, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 20:22:18', '2026-01-12 18:07:37'),
(43, 2, '2025-12-20', '2025-12-20 10:10:14', '2025-12-20 22:22:05', NULL, NULL, 45, 11.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-18 07:58:19', '2026-01-18 07:58:36'),
(46, 2, '2026-01-18', '2026-01-18 05:05:05', NULL, NULL, NULL, 120, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-01-18 08:01:32', '2026-01-18 08:02:30');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `terminal`
--

CREATE TABLE `terminal` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `standort_beschreibung` varchar(255) DEFAULT NULL,
  `abteilung_id` int(10) UNSIGNED DEFAULT NULL,
  `modus` enum('terminal','backend') NOT NULL DEFAULT 'terminal',
  `offline_erlaubt_kommen_gehen` tinyint(1) NOT NULL DEFAULT 1,
  `offline_erlaubt_auftraege` tinyint(1) NOT NULL DEFAULT 1,
  `auto_logout_timeout_sekunden` int(10) UNSIGNED NOT NULL DEFAULT 60,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `terminal`
--

INSERT INTO `terminal` (`id`, `name`, `standort_beschreibung`, `abteilung_id`, `modus`, `offline_erlaubt_kommen_gehen`, `offline_erlaubt_auftraege`, `auto_logout_timeout_sekunden`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'Hauptterminal', 'Eingang', NULL, 'terminal', 1, 1, 60, 1, '2026-01-04 06:44:13', '2026-01-04 06:44:13');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `urlaubsantrag`
--

CREATE TABLE `urlaubsantrag` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `tage_gesamt` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` enum('offen','genehmigt','abgelehnt','storniert') NOT NULL DEFAULT 'offen',
  `antrags_datum` datetime NOT NULL DEFAULT current_timestamp(),
  `entscheidungs_mitarbeiter_id` int(10) UNSIGNED DEFAULT NULL,
  `entscheidungs_datum` datetime DEFAULT NULL,
  `kommentar_mitarbeiter` text DEFAULT NULL,
  `kommentar_genehmiger` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `urlaubsantrag`
--

INSERT INTO `urlaubsantrag` (`id`, `mitarbeiter_id`, `von_datum`, `bis_datum`, `tage_gesamt`, `status`, `antrags_datum`, `entscheidungs_mitarbeiter_id`, `entscheidungs_datum`, `kommentar_mitarbeiter`, `kommentar_genehmiger`, `erstellt_am`, `geaendert_am`) VALUES
(5, 12, '2026-07-02', '2026-07-17', 12.00, 'offen', '2026-01-11 19:21:21', NULL, NULL, 'asdf', NULL, '2026-01-11 19:21:21', '2026-01-11 19:21:21'),
(6, 2, '2026-01-23', '2026-01-31', 6.00, 'storniert', '2026-01-12 06:48:52', NULL, NULL, 'Hahsh', NULL, '2026-01-12 06:48:52', '2026-01-12 06:49:11');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `urlaub_kontingent_jahr`
--

CREATE TABLE `urlaub_kontingent_jahr` (
  `id` int(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `jahr` smallint(5) UNSIGNED NOT NULL,
  `anspruch_override_tage` decimal(6,2) DEFAULT NULL,
  `uebertrag_tage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `korrektur_tage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `notiz` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `urlaub_kontingent_jahr`
--

INSERT INTO `urlaub_kontingent_jahr` (`id`, `mitarbeiter_id`, `jahr`, `anspruch_override_tage`, `uebertrag_tage`, `korrektur_tage`, `notiz`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, 2026, NULL, 0.00, -30.00, 'Vorjahres urlaub wird rausgeblockt', '2026-01-05 19:05:01', '2026-01-18 07:55:09');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `zeitbuchung`
--

CREATE TABLE `zeitbuchung` (
  `id` bigint(10) UNSIGNED NOT NULL,
  `mitarbeiter_id` int(10) UNSIGNED NOT NULL,
  `typ` enum('kommen','gehen') NOT NULL,
  `zeitstempel` datetime NOT NULL,
  `quelle` enum('terminal','web','import') NOT NULL DEFAULT 'terminal',
  `manuell_geaendert` tinyint(1) NOT NULL DEFAULT 0,
  `kommentar` varchar(255) DEFAULT NULL,
  `terminal_id` int(10) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `zeitbuchung`
--

INSERT INTO `zeitbuchung` (`id`, `mitarbeiter_id`, `typ`, `zeitstempel`, `quelle`, `manuell_geaendert`, `kommentar`, `terminal_id`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, 'kommen', '2025-12-20 10:10:14', 'terminal', 0, NULL, NULL, '2025-12-20 10:10:14', '2025-12-20 10:10:14'),
(4, 1, 'kommen', '2026-01-02 05:57:00', 'web', 1, 'test', NULL, '2026-01-02 08:04:22', '2026-01-02 08:04:52'),
(5, 1, 'gehen', '2026-01-02 16:05:08', 'web', 1, 'test', NULL, '2026-01-02 08:05:22', '2026-01-02 08:05:22'),
(6, 1, 'kommen', '2026-01-23 05:00:00', 'web', 1, NULL, NULL, '2026-01-02 11:01:14', '2026-01-02 11:01:14'),
(7, 1, 'gehen', '2026-01-23 16:50:00', 'web', 1, NULL, NULL, '2026-01-02 11:01:27', '2026-01-02 11:01:27'),
(15, 1, 'kommen', '2026-02-02 05:00:00', 'web', 1, NULL, NULL, '2026-01-03 13:42:21', '2026-01-03 13:42:21'),
(16, 1, 'gehen', '2026-02-02 16:00:00', 'web', 1, NULL, NULL, '2026-01-03 13:42:34', '2026-01-03 13:42:34'),
(22, 1, 'kommen', '2026-01-04 06:46:59', 'terminal', 0, NULL, NULL, '2026-01-04 06:46:59', '2026-01-04 06:46:59'),
(25, 1, 'gehen', '2026-01-04 12:41:17', 'terminal', 0, NULL, NULL, '2026-01-04 12:41:17', '2026-01-04 12:41:17'),
(28, 2, 'gehen', '2026-01-05 15:37:00', 'terminal', 1, NULL, NULL, '2026-01-05 15:37:29', '2026-01-07 08:19:46'),
(29, 2, 'kommen', '2026-01-07 07:59:00', 'terminal', 0, NULL, NULL, '2026-01-07 07:59:00', '2026-01-07 07:59:00'),
(30, 2, 'kommen', '2026-01-05 05:30:00', 'web', 1, NULL, NULL, '2026-01-07 08:20:11', '2026-01-07 08:20:11'),
(31, 2, 'gehen', '2026-01-07 15:00:00', 'web', 1, NULL, NULL, '2026-01-07 15:00:23', '2026-01-07 15:00:23'),
(32, 2, 'kommen', '2026-01-19 07:01:00', 'web', 1, NULL, NULL, '2026-01-09 09:55:13', '2026-01-09 09:55:13'),
(33, 2, 'gehen', '2026-01-19 15:59:05', 'web', 1, NULL, NULL, '2026-01-09 09:55:29', '2026-01-09 09:55:29'),
(34, 2, 'kommen', '2026-01-09 10:04:09', 'terminal', 0, NULL, NULL, '2026-01-09 10:04:09', '2026-01-09 10:04:09'),
(35, 2, 'gehen', '2026-01-09 10:34:46', 'terminal', 0, NULL, NULL, '2026-01-09 10:34:46', '2026-01-09 10:34:46'),
(36, 2, 'kommen', '2026-01-09 11:05:15', 'terminal', 0, NULL, NULL, '2026-01-09 11:05:15', '2026-01-09 11:05:15'),
(37, 2, 'gehen', '2026-01-09 11:05:17', 'terminal', 0, NULL, NULL, '2026-01-09 11:05:17', '2026-01-09 11:05:17'),
(38, 1, 'kommen', '2026-01-09 12:56:00', 'terminal', 0, NULL, NULL, '2026-01-09 12:56:00', '2026-01-09 12:56:00'),
(39, 2, 'kommen', '2026-01-09 20:05:21', 'terminal', 0, NULL, NULL, '2026-01-09 20:05:21', '2026-01-09 20:05:21'),
(40, 2, 'gehen', '2026-01-09 20:06:38', 'terminal', 0, NULL, NULL, '2026-01-09 20:06:38', '2026-01-09 20:06:38'),
(41, 1, 'gehen', '2026-01-09 16:00:00', 'web', 1, 'test', NULL, '2026-01-10 08:09:09', '2026-01-10 08:09:09'),
(42, 2, 'kommen', '2026-01-10 08:30:52', 'terminal', 0, NULL, NULL, '2026-01-10 08:30:52', '2026-01-10 08:30:52'),
(43, 2, 'gehen', '2026-01-10 08:45:04', 'terminal', 0, NULL, NULL, '2026-01-10 08:45:04', '2026-01-10 08:45:04'),
(44, 2, 'kommen', '2026-01-10 10:16:36', 'terminal', 0, NULL, NULL, '2026-01-10 10:16:36', '2026-01-10 10:16:36'),
(45, 1, 'kommen', '2026-01-10 10:16:56', 'terminal', 0, NULL, NULL, '2026-01-10 10:16:56', '2026-01-10 10:16:56'),
(46, 1, 'gehen', '2026-01-10 10:17:31', 'terminal', 0, NULL, NULL, '2026-01-10 10:17:31', '2026-01-10 10:17:31'),
(47, 1, 'kommen', '2026-01-10 10:17:36', 'terminal', 0, NULL, NULL, '2026-01-10 10:17:36', '2026-01-10 10:17:36'),
(48, 2, 'gehen', '2026-01-10 10:19:18', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:18', '2026-01-10 10:19:18'),
(49, 2, 'kommen', '2026-01-10 10:19:21', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:21', '2026-01-10 10:19:21'),
(50, 2, 'gehen', '2026-01-10 10:19:24', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:24', '2026-01-10 10:19:24'),
(51, 2, 'kommen', '2026-01-10 10:19:26', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:26', '2026-01-10 10:19:26'),
(52, 2, 'gehen', '2026-01-10 10:19:28', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:28', '2026-01-10 10:19:28'),
(53, 2, 'kommen', '2026-01-10 10:19:55', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:55', '2026-01-10 10:19:55'),
(54, 2, 'gehen', '2026-01-10 10:19:58', 'terminal', 0, NULL, NULL, '2026-01-10 10:19:58', '2026-01-10 10:19:58'),
(55, 2, 'kommen', '2026-01-12 01:01:01', 'web', 1, '1', NULL, '2026-01-10 10:20:19', '2026-01-10 10:20:19'),
(56, 2, 'gehen', '2026-01-12 02:02:02', 'web', 1, '2', NULL, '2026-01-10 10:20:24', '2026-01-10 10:20:24'),
(57, 2, 'kommen', '2026-01-12 03:03:03', 'web', 1, '3', NULL, '2026-01-10 10:20:30', '2026-01-10 10:20:30'),
(58, 2, 'gehen', '2026-01-12 04:04:04', 'web', 1, '4', NULL, '2026-01-10 10:20:38', '2026-01-10 10:20:38'),
(59, 2, 'kommen', '2026-01-10 11:01:05', 'terminal', 0, NULL, NULL, '2026-01-10 11:01:23', '2026-01-10 11:01:23'),
(60, 2, 'gehen', '2026-01-10 11:05:49', 'terminal', 0, NULL, NULL, '2026-01-10 11:05:49', '2026-01-10 11:05:49'),
(61, 2, 'kommen', '2026-01-10 11:26:45', 'terminal', 0, NULL, NULL, '2026-01-10 11:26:45', '2026-01-10 11:26:45'),
(62, 2, 'gehen', '2026-01-10 16:53:06', 'terminal', 0, NULL, NULL, '2026-01-10 16:53:06', '2026-01-10 16:53:06'),
(63, 2, 'kommen', '2026-01-10 18:09:12', 'terminal', 0, NULL, NULL, '2026-01-10 18:09:12', '2026-01-10 18:09:12'),
(64, 1, 'gehen', '2026-01-10 16:00:00', 'web', 1, '1', NULL, '2026-01-11 07:02:14', '2026-01-11 07:02:14'),
(65, 2, 'gehen', '2026-01-10 22:00:00', 'web', 1, '1', NULL, '2026-01-11 07:02:32', '2026-01-11 07:15:35'),
(67, 12, 'kommen', '2026-01-11 19:19:12', 'terminal', 0, NULL, NULL, '2026-01-11 19:19:12', '2026-01-11 19:19:12'),
(68, 12, 'gehen', '2026-01-11 19:19:54', 'terminal', 0, NULL, NULL, '2026-01-11 19:19:54', '2026-01-11 19:19:54'),
(69, 5, 'kommen', '2026-01-11 06:00:00', 'web', 1, 'testen', NULL, '2026-01-11 20:22:18', '2026-01-11 20:22:18'),
(70, 2, 'kommen', '2026-01-12 06:46:19', 'terminal', 0, NULL, NULL, '2026-01-12 06:46:19', '2026-01-12 06:46:19'),
(71, 5, 'gehen', '2026-01-11 14:00:00', 'web', 1, 'test', NULL, '2026-01-12 18:07:37', '2026-01-12 18:07:37'),
(72, 2, 'gehen', '2026-01-12 19:06:38', 'terminal', 0, NULL, NULL, '2026-01-12 19:06:38', '2026-01-12 19:06:38'),
(73, 2, 'kommen', '2026-01-14 12:35:42', 'terminal', 0, NULL, NULL, '2026-01-14 12:35:42', '2026-01-14 12:35:42'),
(74, 2, 'gehen', '2026-01-14 12:38:52', 'terminal', 0, NULL, NULL, '2026-01-14 12:38:52', '2026-01-14 12:38:52'),
(75, 2, 'kommen', '2026-01-16 20:21:08', 'terminal', 0, NULL, NULL, '2026-01-16 20:21:08', '2026-01-16 20:21:08'),
(76, 2, 'gehen', '2026-01-16 20:21:32', 'terminal', 0, NULL, NULL, '2026-01-16 20:21:32', '2026-01-16 20:21:32'),
(77, 2, 'kommen', '2026-01-17 04:50:25', 'terminal', 0, NULL, NULL, '2026-01-17 04:50:25', '2026-01-17 04:50:25'),
(78, 2, 'gehen', '2026-01-17 05:13:59', 'terminal', 0, NULL, NULL, '2026-01-17 05:13:59', '2026-01-17 05:13:59'),
(79, 2, 'gehen', '2025-12-20 22:22:05', 'web', 1, 'ja', NULL, '2026-01-18 07:58:36', '2026-01-18 07:58:36'),
(80, 2, 'kommen', '2026-01-18 05:05:05', 'web', 1, '3', NULL, '2026-01-18 08:01:32', '2026-01-18 08:01:32');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `zeit_rundungsregel`
--

CREATE TABLE `zeit_rundungsregel` (
  `id` int(10) UNSIGNED NOT NULL,
  `von_uhrzeit` time NOT NULL,
  `bis_uhrzeit` time NOT NULL,
  `einheit_minuten` int(10) UNSIGNED NOT NULL,
  `richtung` enum('auf','ab','naechstgelegen') NOT NULL,
  `gilt_fuer` enum('kommen','gehen','beide') NOT NULL DEFAULT 'beide',
  `prioritaet` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `beschreibung` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `zeit_rundungsregel`
--

INSERT INTO `zeit_rundungsregel` (`id`, `von_uhrzeit`, `bis_uhrzeit`, `einheit_minuten`, `richtung`, `gilt_fuer`, `prioritaet`, `aktiv`, `beschreibung`, `erstellt_am`, `geaendert_am`) VALUES
(1, '00:00:00', '07:00:00', 30, 'auf', 'kommen', 1, 1, 'Standard: 00:00–07:00 auf 30 Minuten aufrunden', '2025-12-21 10:24:25', '2026-01-09 10:00:02'),
(2, '07:00:00', '23:59:00', 15, 'auf', 'kommen', 2, 1, 'Standard: 07:00–24:00 auf 15 Minuten aufrunden', '2025-12-21 10:24:25', '2026-01-09 10:00:13'),
(3, '00:00:00', '23:59:00', 15, 'ab', 'gehen', 1, 1, 'Abrunden auf 15 Minuten wenn gehen!', '2026-01-09 09:57:09', '2026-01-09 09:57:50');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `abteilung`
--
ALTER TABLE `abteilung`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_abteilung_parent` (`parent_id`),
  ADD KEY `idx_abteilung_name` (`name`);

--
-- Indizes für die Tabelle `auftrag`
--
ALTER TABLE `auftrag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_auftrag_auftragsnummer` (`auftragsnummer`),
  ADD KEY `idx_auftrag_status` (`status`);

--
-- Indizes für die Tabelle `auftragszeit`
--
ALTER TABLE `auftragszeit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auftragszeit_mitarbeiter` (`mitarbeiter_id`),
  ADD KEY `idx_auftragszeit_auftrag` (`auftrag_id`),
  ADD KEY `idx_auftragszeit_maschine` (`maschine_id`),
  ADD KEY `idx_auftragszeit_startzeit` (`startzeit`),
  ADD KEY `idx_auftragszeit_status` (`status`),
  ADD KEY `fk_auftragszeit_terminal` (`terminal_id`),
  ADD KEY `idx_auftragszeit_auftrag_arbeitsschritt` (`auftrag_id`,`arbeitsschritt_code`),
  ADD KEY `idx_auftragszeit_code_arbeitsschritt` (`auftragscode`,`arbeitsschritt_code`);

--
-- Indizes für die Tabelle `betriebsferien`
--
ALTER TABLE `betriebsferien`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_betriebsferien_abteilung` (`abteilung_id`);

--
-- Indizes für die Tabelle `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_config_schluessel` (`schluessel`);

--
-- Indizes für die Tabelle `db_injektionsqueue`
--
ALTER TABLE `db_injektionsqueue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_db_injektionsqueue_status` (`status`),
  ADD KEY `idx_db_injektionsqueue_meta_mitarbeiter` (`meta_mitarbeiter_id`),
  ADD KEY `idx_db_injektionsqueue_meta_terminal` (`meta_terminal_id`);

--
-- Indizes für die Tabelle `feiertag`
--
ALTER TABLE `feiertag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_feiertag_datum_bundesland` (`datum`,`bundesland`);

--
-- Indizes für die Tabelle `krankzeitraum`
--
ALTER TABLE `krankzeitraum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_krankzeitraum_mitarbeiter_dates` (`mitarbeiter_id`,`von_datum`,`bis_datum`,`aktiv`),
  ADD KEY `idx_krankzeitraum_typ` (`typ`),
  ADD KEY `idx_krankzeitraum_angelegt_von` (`angelegt_von_mitarbeiter_id`);

--
-- Indizes für die Tabelle `kurzarbeit_plan`
--
ALTER TABLE `kurzarbeit_plan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kurzarbeit_plan_scope_dates` (`scope`,`von_datum`,`bis_datum`,`aktiv`),
  ADD KEY `idx_kurzarbeit_plan_mitarbeiter` (`mitarbeiter_id`),
  ADD KEY `idx_kurzarbeit_plan_angelegt_von` (`angelegt_von_mitarbeiter_id`);

--
-- Indizes für die Tabelle `maschine`
--
ALTER TABLE `maschine`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_maschine_abteilung` (`abteilung_id`),
  ADD KEY `idx_maschine_aktiv` (`aktiv`);

--
-- Indizes für die Tabelle `mitarbeiter`
--
ALTER TABLE `mitarbeiter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mitarbeiter_benutzername` (`benutzername`),
  ADD UNIQUE KEY `uniq_mitarbeiter_email` (`email`),
  ADD UNIQUE KEY `uniq_mitarbeiter_rfid` (`rfid_code`),
  ADD UNIQUE KEY `uniq_mitarbeiter_personalnummer` (`personalnummer`),
  ADD KEY `idx_mitarbeiter_name` (`nachname`,`vorname`),
  ADD KEY `idx_mitarbeiter_aktiv` (`aktiv`);

--
-- Indizes für die Tabelle `mitarbeiter_genehmiger`
--
ALTER TABLE `mitarbeiter_genehmiger`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mitarbeiter_genehmiger` (`mitarbeiter_id`,`genehmiger_mitarbeiter_id`),
  ADD KEY `idx_mitarbeiter_genehmiger_genehmiger` (`genehmiger_mitarbeiter_id`);

--
-- Indizes für die Tabelle `mitarbeiter_hat_abteilung`
--
ALTER TABLE `mitarbeiter_hat_abteilung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mitarbeiter_abteilung` (`mitarbeiter_id`,`abteilung_id`),
  ADD KEY `idx_mitarbeiter_hat_abteilung_abteilung` (`abteilung_id`);

--
-- Indizes für die Tabelle `mitarbeiter_hat_recht`
--
ALTER TABLE `mitarbeiter_hat_recht`
  ADD PRIMARY KEY (`mitarbeiter_id`,`recht_id`),
  ADD KEY `idx_mhr_recht` (`recht_id`),
  ADD KEY `idx_mhr_erlaubt` (`erlaubt`);

--
-- Indizes für die Tabelle `mitarbeiter_hat_rolle`
--
ALTER TABLE `mitarbeiter_hat_rolle`
  ADD PRIMARY KEY (`mitarbeiter_id`,`rolle_id`),
  ADD KEY `idx_mitarbeiter_hat_rolle_rolle` (`rolle_id`);

--
-- Indizes für die Tabelle `mitarbeiter_hat_rolle_scope`
--
ALTER TABLE `mitarbeiter_hat_rolle_scope`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mhrs` (`mitarbeiter_id`,`rolle_id`,`scope_typ`,`scope_id`),
  ADD KEY `idx_mhrs_rolle` (`rolle_id`),
  ADD KEY `idx_mhrs_scope` (`scope_typ`,`scope_id`);

--
-- Indizes für die Tabelle `monatswerte_mitarbeiter`
--
ALTER TABLE `monatswerte_mitarbeiter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_monatswerte_mitarbeiter_monat` (`mitarbeiter_id`,`jahr`,`monat`),
  ADD KEY `idx_monatswerte_jahr_monat` (`jahr`,`monat`);

--
-- Indizes für die Tabelle `pausenentscheidung`
--
ALTER TABLE `pausenentscheidung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pausenentscheidung_mid_datum` (`mitarbeiter_id`,`datum`),
  ADD KEY `idx_pausenentscheidung_datum` (`datum`),
  ADD KEY `idx_pausenentscheidung_mid` (`mitarbeiter_id`);

--
-- Indizes für die Tabelle `pausenfenster`
--
ALTER TABLE `pausenfenster`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pausenfenster_aktiv_sort` (`aktiv`,`sort_order`,`von_uhrzeit`);

--
-- Indizes für die Tabelle `recht`
--
ALTER TABLE `recht`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_recht_code` (`code`),
  ADD KEY `idx_recht_aktiv` (`aktiv`);

--
-- Indizes für die Tabelle `rolle`
--
ALTER TABLE `rolle`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rolle_name` (`name`);

--
-- Indizes für die Tabelle `rolle_hat_recht`
--
ALTER TABLE `rolle_hat_recht`
  ADD PRIMARY KEY (`rolle_id`,`recht_id`),
  ADD KEY `idx_rolle_hat_recht_recht` (`recht_id`);

--
-- Indizes für die Tabelle `sonstiges_grund`
--
ALTER TABLE `sonstiges_grund`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sonstiges_grund_code` (`code`),
  ADD KEY `idx_sonstiges_grund_aktiv_sort` (`aktiv`,`sort_order`,`titel`);

--
-- Indizes für die Tabelle `stundenkonto_batch`
--
ALTER TABLE `stundenkonto_batch`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stundenkonto_batch_mitarbeiter_datum` (`mitarbeiter_id`,`von_datum`,`bis_datum`),
  ADD KEY `idx_stundenkonto_batch_erstellt_von` (`erstellt_von_mitarbeiter_id`);

--
-- Indizes für die Tabelle `stundenkonto_korrektur`
--
ALTER TABLE `stundenkonto_korrektur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stundenkonto_korr_mitarbeiter_datum` (`mitarbeiter_id`,`wirksam_datum`),
  ADD KEY `idx_stundenkonto_korr_batch` (`batch_id`),
  ADD KEY `idx_stundenkonto_korr_typ` (`typ`),
  ADD KEY `fk_stundenkonto_korr_erstellt_von` (`erstellt_von_mitarbeiter_id`);

--
-- Indizes für die Tabelle `system_log`
--
ALTER TABLE `system_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_system_log_mitarbeiter` (`mitarbeiter_id`),
  ADD KEY `idx_system_log_terminal` (`terminal_id`);

--
-- Indizes für die Tabelle `tageswerte_mitarbeiter`
--
ALTER TABLE `tageswerte_mitarbeiter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tageswerte_mitarbeiter_datum` (`mitarbeiter_id`,`datum`),
  ADD KEY `idx_tageswerte_datum` (`datum`);

--
-- Indizes für die Tabelle `terminal`
--
ALTER TABLE `terminal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_terminal_abteilung` (`abteilung_id`),
  ADD KEY `idx_terminal_aktiv` (`aktiv`);

--
-- Indizes für die Tabelle `urlaubsantrag`
--
ALTER TABLE `urlaubsantrag`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_urlaubsantrag_mitarbeiter` (`mitarbeiter_id`),
  ADD KEY `idx_urlaubsantrag_status` (`status`),
  ADD KEY `idx_urlaubsantrag_zeitraum` (`von_datum`,`bis_datum`),
  ADD KEY `idx_urlaubsantrag_entscheider` (`entscheidungs_mitarbeiter_id`);

--
-- Indizes für die Tabelle `urlaub_kontingent_jahr`
--
ALTER TABLE `urlaub_kontingent_jahr`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_urlaub_kontingent_jahr` (`mitarbeiter_id`,`jahr`),
  ADD KEY `idx_urlaub_kontingent_jahr_jahr` (`jahr`);

--
-- Indizes für die Tabelle `zeitbuchung`
--
ALTER TABLE `zeitbuchung`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_zeitbuchung_mitarbeiter` (`mitarbeiter_id`),
  ADD KEY `idx_zeitbuchung_zeitstempel` (`zeitstempel`),
  ADD KEY `idx_zeitbuchung_terminal` (`terminal_id`);

--
-- Indizes für die Tabelle `zeit_rundungsregel`
--
ALTER TABLE `zeit_rundungsregel`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `abteilung`
--
ALTER TABLE `abteilung`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `auftrag`
--
ALTER TABLE `auftrag`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auftragszeit`
--
ALTER TABLE `auftragszeit`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `betriebsferien`
--
ALTER TABLE `betriebsferien`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `config`
--
ALTER TABLE `config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49140;

--
-- AUTO_INCREMENT für Tabelle `db_injektionsqueue`
--
ALTER TABLE `db_injektionsqueue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `feiertag`
--
ALTER TABLE `feiertag`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT für Tabelle `krankzeitraum`
--
ALTER TABLE `krankzeitraum`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `kurzarbeit_plan`
--
ALTER TABLE `kurzarbeit_plan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `maschine`
--
ALTER TABLE `maschine`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter`
--
ALTER TABLE `mitarbeiter`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter_genehmiger`
--
ALTER TABLE `mitarbeiter_genehmiger`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter_hat_abteilung`
--
ALTER TABLE `mitarbeiter_hat_abteilung`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter_hat_rolle_scope`
--
ALTER TABLE `mitarbeiter_hat_rolle_scope`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT für Tabelle `monatswerte_mitarbeiter`
--
ALTER TABLE `monatswerte_mitarbeiter`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pausenentscheidung`
--
ALTER TABLE `pausenentscheidung`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pausenfenster`
--
ALTER TABLE `pausenfenster`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `recht`
--
ALTER TABLE `recht`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT für Tabelle `rolle`
--
ALTER TABLE `rolle`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `sonstiges_grund`
--
ALTER TABLE `sonstiges_grund`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `stundenkonto_batch`
--
ALTER TABLE `stundenkonto_batch`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `stundenkonto_korrektur`
--
ALTER TABLE `stundenkonto_korrektur`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `system_log`
--
ALTER TABLE `system_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=857;

--
-- AUTO_INCREMENT für Tabelle `tageswerte_mitarbeiter`
--
ALTER TABLE `tageswerte_mitarbeiter`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT für Tabelle `terminal`
--
ALTER TABLE `terminal`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `urlaubsantrag`
--
ALTER TABLE `urlaubsantrag`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `urlaub_kontingent_jahr`
--
ALTER TABLE `urlaub_kontingent_jahr`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `zeitbuchung`
--
ALTER TABLE `zeitbuchung`
  MODIFY `id` bigint(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT für Tabelle `zeit_rundungsregel`
--
ALTER TABLE `zeit_rundungsregel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `abteilung`
--
ALTER TABLE `abteilung`
  ADD CONSTRAINT `fk_abteilung_parent` FOREIGN KEY (`parent_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `auftragszeit`
--
ALTER TABLE `auftragszeit`
  ADD CONSTRAINT `fk_auftragszeit_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftrag` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auftragszeit_maschine` FOREIGN KEY (`maschine_id`) REFERENCES `maschine` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auftragszeit_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auftragszeit_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `betriebsferien`
--
ALTER TABLE `betriebsferien`
  ADD CONSTRAINT `fk_betriebsferien_abteilung` FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `db_injektionsqueue`
--
ALTER TABLE `db_injektionsqueue`
  ADD CONSTRAINT `fk_db_injektionsqueue_mitarbeiter` FOREIGN KEY (`meta_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_db_injektionsqueue_terminal` FOREIGN KEY (`meta_terminal_id`) REFERENCES `terminal` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `krankzeitraum`
--
ALTER TABLE `krankzeitraum`
  ADD CONSTRAINT `fk_krankzeitraum_angelegt_von` FOREIGN KEY (`angelegt_von_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_krankzeitraum_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kurzarbeit_plan`
--
ALTER TABLE `kurzarbeit_plan`
  ADD CONSTRAINT `fk_kurzarbeit_plan_angelegt_von` FOREIGN KEY (`angelegt_von_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kurzarbeit_plan_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `maschine`
--
ALTER TABLE `maschine`
  ADD CONSTRAINT `fk_maschine_abteilung` FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `mitarbeiter_genehmiger`
--
ALTER TABLE `mitarbeiter_genehmiger`
  ADD CONSTRAINT `fk_mitarbeiter_genehmiger_genehmiger` FOREIGN KEY (`genehmiger_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mitarbeiter_genehmiger_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `mitarbeiter_hat_abteilung`
--
ALTER TABLE `mitarbeiter_hat_abteilung`
  ADD CONSTRAINT `fk_mitarbeiter_hat_abteilung_abteilung` FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mitarbeiter_hat_abteilung_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `mitarbeiter_hat_recht`
--
ALTER TABLE `mitarbeiter_hat_recht`
  ADD CONSTRAINT `fk_mitarbeiter_hat_recht_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mitarbeiter_hat_recht_recht` FOREIGN KEY (`recht_id`) REFERENCES `recht` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `mitarbeiter_hat_rolle`
--
ALTER TABLE `mitarbeiter_hat_rolle`
  ADD CONSTRAINT `fk_mitarbeiter_hat_rolle_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mitarbeiter_hat_rolle_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rolle` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `mitarbeiter_hat_rolle_scope`
--
ALTER TABLE `mitarbeiter_hat_rolle_scope`
  ADD CONSTRAINT `fk_mhrs_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mhrs_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rolle` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `monatswerte_mitarbeiter`
--
ALTER TABLE `monatswerte_mitarbeiter`
  ADD CONSTRAINT `fk_monatswerte_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `rolle_hat_recht`
--
ALTER TABLE `rolle_hat_recht`
  ADD CONSTRAINT `fk_rolle_hat_recht_recht` FOREIGN KEY (`recht_id`) REFERENCES `recht` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rolle_hat_recht_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rolle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `stundenkonto_batch`
--
ALTER TABLE `stundenkonto_batch`
  ADD CONSTRAINT `fk_stundenkonto_batch_erstellt_von` FOREIGN KEY (`erstellt_von_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stundenkonto_batch_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `stundenkonto_korrektur`
--
ALTER TABLE `stundenkonto_korrektur`
  ADD CONSTRAINT `fk_stundenkonto_korr_batch` FOREIGN KEY (`batch_id`) REFERENCES `stundenkonto_batch` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stundenkonto_korr_erstellt_von` FOREIGN KEY (`erstellt_von_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stundenkonto_korr_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `system_log`
--
ALTER TABLE `system_log`
  ADD CONSTRAINT `fk_system_log_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_system_log_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `tageswerte_mitarbeiter`
--
ALTER TABLE `tageswerte_mitarbeiter`
  ADD CONSTRAINT `fk_tageswerte_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `terminal`
--
ALTER TABLE `terminal`
  ADD CONSTRAINT `fk_terminal_abteilung` FOREIGN KEY (`abteilung_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `urlaubsantrag`
--
ALTER TABLE `urlaubsantrag`
  ADD CONSTRAINT `fk_urlaubsantrag_entscheider` FOREIGN KEY (`entscheidungs_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_urlaubsantrag_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `urlaub_kontingent_jahr`
--
ALTER TABLE `urlaub_kontingent_jahr`
  ADD CONSTRAINT `fk_urlaub_kontingent_jahr_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `zeitbuchung`
--
ALTER TABLE `zeitbuchung`
  ADD CONSTRAINT `fk_zeitbuchung_mitarbeiter` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_zeitbuchung_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
