-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 01. Feb 2026 um 06:12
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `auftragsnummer` varchar(100) NOT NULL,
  `kurzbeschreibung` varchar(255) DEFAULT NULL,
  `kunde` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `auftrag`
--

INSERT INTO `auftrag` (`id`, `auftragsnummer`, `kurzbeschreibung`, `kunde`, `status`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, '12341234', NULL, NULL, NULL, 1, '2026-01-18 16:25:36', '2026-01-18 16:25:36'),
(2, '1234556', NULL, NULL, NULL, 1, '2026-01-19 19:11:24', '2026-01-19 19:11:24'),
(3, '12312', NULL, NULL, NULL, 1, '2026-01-20 04:35:19', '2026-01-20 04:35:19'),
(4, '5486754', NULL, NULL, NULL, 1, '2026-01-20 04:35:31', '2026-01-20 04:35:31');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auftrag_arbeitsschritt`
--

CREATE TABLE `auftrag_arbeitsschritt` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auftrag_id` bigint(20) UNSIGNED NOT NULL,
  `arbeitsschritt_code` varchar(100) NOT NULL,
  `bezeichnung` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `auftrag_arbeitsschritt`
--

INSERT INTO `auftrag_arbeitsschritt` (`id`, `auftrag_id`, `arbeitsschritt_code`, `bezeichnung`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, 1, '12341234', NULL, 1, '2026-01-18 16:25:36', '2026-01-18 16:25:36'),
(2, 2, '123123', NULL, 1, '2026-01-19 19:11:24', '2026-01-19 19:11:24'),
(3, 3, '1213131', NULL, 1, '2026-01-20 04:35:19', '2026-01-20 04:35:19'),
(4, 4, '564687564', NULL, 1, '2026-01-20 04:35:31', '2026-01-20 04:35:31');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auftragszeit`
--

CREATE TABLE `auftragszeit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `auftrag_id` bigint(20) UNSIGNED DEFAULT NULL,
  `arbeitsschritt_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `auftragszeit`
--

INSERT INTO `auftragszeit` (`id`, `mitarbeiter_id`, `auftrag_id`, `arbeitsschritt_id`, `auftragscode`, `arbeitsschritt_code`, `maschine_id`, `terminal_id`, `typ`, `startzeit`, `endzeit`, `status`, `kommentar`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, NULL, NULL, '11', NULL, 1, NULL, 'haupt', '2026-01-02 16:30:59', '2026-01-02 16:31:17', 'abgeschlossen', NULL, '2026-01-02 16:30:59', '2026-01-02 16:31:17'),
(4, 2, NULL, NULL, '123123', NULL, NULL, NULL, 'haupt', '2026-01-18 09:56:16', '2026-01-18 16:25:36', 'abgeschlossen', NULL, '2026-01-18 09:56:16', '2026-01-18 16:25:36'),
(5, 2, 1, 1, '12341234', '12341234', NULL, NULL, 'haupt', '2026-01-18 16:25:36', '2026-01-18 16:26:21', 'abgeschlossen', NULL, '2026-01-18 16:25:36', '2026-01-18 16:26:21'),
(6, 2, 2, 2, '1234556', '123123', NULL, NULL, 'haupt', '2026-01-19 19:11:24', '2026-01-19 19:40:37', 'abgeschlossen', NULL, '2026-01-19 19:11:24', '2026-01-19 19:40:37'),
(7, 2, 3, 3, '12312', '1213131', NULL, NULL, 'haupt', '2026-01-20 04:35:19', '2026-01-20 04:35:45', 'abgeschlossen', NULL, '2026-01-20 04:35:19', '2026-01-20 04:35:45'),
(8, 2, 4, 4, '5486754', '564687564', NULL, NULL, 'neben', '2026-01-20 04:35:31', '2026-01-21 20:32:46', 'abgeschlossen', NULL, '2026-01-20 04:35:31', '2026-01-21 20:32:46');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `betriebsferien`
--

CREATE TABLE `betriebsferien` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date NOT NULL,
  `beschreibung` varchar(255) DEFAULT NULL,
  `abteilung_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
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
(31548, 'micro_buchung_max_sekunden', '180', 'int', 'Zeitbuchungen: Mikro-Buchungen (Kommen/Gehen) bis zu X Sekunden werden standardmäßig ignoriert/ausgeblendet. Default 180 (= 3 Minuten).', '2026-01-14 12:33:45', '2026-01-14 12:33:45'),
(31549, 'maschinen_qr_rel_pfad', 'uploads/maschinen_codes', 'string', 'Maschinen-QR: Relativer Speicherpfad unterhalb von public. Default uploads/maschinen_codes.', '2026-01-14 12:33:45', '2026-01-14 12:33:45'),
(31550, 'maschinen_qr_url', '', 'string', 'Maschinen-QR: URL oder Basispfad für die Ausgabe. Leer = Domain-Root.', '2026-01-14 12:33:45', '2026-01-14 12:33:45');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `db_injektionsqueue`
--

CREATE TABLE `db_injektionsqueue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('offen','verarbeitet','fehler') NOT NULL DEFAULT 'offen',
  `sql_befehl` longtext NOT NULL,
  `fehlernachricht` text DEFAULT NULL,
  `letzte_ausfuehrung` datetime DEFAULT NULL,
  `versuche` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `meta_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meta_terminal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meta_aktion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `feiertag`
--

CREATE TABLE `feiertag` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `typ` enum('lfz','kk') NOT NULL,
  `von_datum` date NOT NULL,
  `bis_datum` date DEFAULT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `angelegt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `krankzeitraum`
--

INSERT INTO `krankzeitraum` (`id`, `mitarbeiter_id`, `typ`, `von_datum`, `bis_datum`, `kommentar`, `aktiv`, `angelegt_von_mitarbeiter_id`, `erstellt_am`, `geaendert_am`) VALUES
(1, 1, 'lfz', '2025-12-29', '2026-02-01', NULL, 1, 2, '2026-01-03 13:04:14', '2026-01-03 13:04:14'),
(2, 9, 'lfz', '2026-01-01', '2026-01-31', NULL, 1, 2, '2026-01-19 19:46:08', '2026-01-19 19:46:08'),
(3, 2, 'lfz', '2025-12-15', '2025-12-30', 'fasdf', 1, 2, '2026-02-01 05:15:08', '2026-02-01 05:15:08');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kurzarbeit_plan`
--

CREATE TABLE `kurzarbeit_plan` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `maschine`
--

CREATE TABLE `maschine` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `abteilung_id` bigint(20) UNSIGNED DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `code_bild_pfad` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `maschine`
--

INSERT INTO `maschine` (`id`, `name`, `abteilung_id`, `beschreibung`, `code_bild_pfad`, `aktiv`, `erstellt_am`, `geaendert_am`) VALUES
(1, 'KaoMing', 1, NULL, NULL, 1, '2026-01-01 20:20:47', '2026-01-11 12:00:25'),
(2, 'CLX 500', 2, 'Dreh-/Fräs- Zentrum', NULL, 1, '2026-01-11 12:00:55', '2026-01-11 12:00:55'),
(3, 'Cyclon1000', 2, NULL, NULL, 1, '2026-01-11 12:01:14', '2026-01-11 12:01:14'),
(4, 'BTF1000', 1, NULL, NULL, 1, '2026-01-11 12:01:26', '2026-01-11 12:01:26');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter`
--

CREATE TABLE `mitarbeiter` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
(9, NULL, 'Jan', 'Goy', NULL, '2026-01-01', 40.00, 2.50, NULL, NULL, NULL, NULL, 1, 0, '2026-01-11 11:57:33', '2026-01-19 19:45:11'),
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `genehmiger_mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `abteilung_id` bigint(20) UNSIGNED NOT NULL,
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
(7, 11, 4, 1, '2026-01-11 16:33:16'),
(8, 11, 5, 0, '2026-01-11 16:33:16'),
(9, 8, 6, 0, '2026-01-11 16:33:29'),
(10, 8, 7, 1, '2026-01-11 16:33:29'),
(11, 10, 1, 1, '2026-01-11 16:33:39'),
(14, 12, 4, 1, '2026-01-11 19:23:05'),
(16, 2, 2, 1, '2026-01-18 07:46:37'),
(17, 9, 2, 1, '2026-01-19 19:45:11');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitarbeiter_hat_recht`
--

CREATE TABLE `mitarbeiter_hat_recht` (
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `recht_id` bigint(20) UNSIGNED NOT NULL,
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
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `rolle_id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `rolle_id` bigint(20) UNSIGNED NOT NULL,
  `scope_typ` enum('global','abteilung') NOT NULL DEFAULT 'global',
  `scope_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `entscheidung` enum('ABZIEHEN','NICHT_ABZIEHEN') NOT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  `erstellt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pausenfenster`
--

CREATE TABLE `pausenfenster` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `rolle_id` bigint(20) UNSIGNED NOT NULL,
  `recht_id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stundenkonto_korrektur`
--

CREATE TABLE `stundenkonto_korrektur` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `wirksam_datum` date NOT NULL,
  `delta_minuten` int(11) NOT NULL,
  `typ` enum('manuell','verteilung') NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `begruendung` varchar(255) NOT NULL,
  `erstellt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stealth` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `stundenkonto_korrektur`
--

INSERT INTO `stundenkonto_korrektur` (`id`, `mitarbeiter_id`, `wirksam_datum`, `delta_minuten`, `typ`, `batch_id`, `begruendung`, `erstellt_von_mitarbeiter_id`, `stealth`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, '2025-12-31', 915, 'manuell', NULL, 'Monatsabschluss 2025-12', 2, 0, '2026-01-18 07:58:51', '2026-02-01 06:16:46');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `system_log`
--

CREATE TABLE `system_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `zeitstempel` datetime NOT NULL DEFAULT current_timestamp(),
  `loglevel` enum('debug','info','warn','error') NOT NULL DEFAULT 'info',
  `kategorie` varchar(100) DEFAULT NULL,
  `nachricht` text NOT NULL,
  `daten` text DEFAULT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `terminal_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `system_log`
--

INSERT INTO `system_log` (`id`, `zeitstempel`, `loglevel`, `kategorie`, `nachricht`, `daten`, `mitarbeiter_id`, `terminal_id`) VALUES
(1194, '2026-02-01 07:06:16', 'info', 'tageswerte_audit', 'Tageswerte gesetzt: Pause-Override', '{\"ziel_mitarbeiter_id\":2,\"datum\":\"2026-02-04\",\"pause_stunden\":0.5,\"pause_minuten\":30,\"begruendung\":\"keine frühstückspause gemacht\"}', 2, NULL),
(1195, '2026-02-01 07:07:31', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":2,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(1196, '2026-02-01 07:07:33', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":2,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL),
(1197, '2026-02-01 07:08:34', 'warn', 'terminal_monatsstatus', 'Terminal: Monatsstatus via ReportService fehlgeschlagen', '{\"mitarbeiter_id\":2,\"jahr\":2026,\"monat\":2,\"exception\":\"Call to private ReportService::__construct() from scope TerminalController\"}', 2, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tageswerte_mitarbeiter`
--

CREATE TABLE `tageswerte_mitarbeiter` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `tageswerte_mitarbeiter`
--

INSERT INTO `tageswerte_mitarbeiter` (`id`, `mitarbeiter_id`, `datum`, `kommen_roh`, `gehen_roh`, `kommen_korr`, `gehen_korr`, `pause_korr_minuten`, `pause_override_aktiv`, `pause_override_begruendung`, `pause_override_gesetzt_von_mitarbeiter_id`, `pause_override_gesetzt_am`, `ist_stunden`, `arzt_stunden`, `krank_lfz_stunden`, `krank_kk_stunden`, `feiertag_stunden`, `kurzarbeit_stunden`, `urlaub_stunden`, `sonstige_stunden`, `kennzeichen_arzt`, `kennzeichen_krank_lfz`, `kennzeichen_krank_kk`, `kennzeichen_feiertag`, `kennzeichen_kurzarbeit`, `kennzeichen_urlaub`, `kennzeichen_sonstiges`, `rohdaten_manuell_geaendert`, `felder_manuell_geaendert`, `kommentar`, `erstellt_am`, `geaendert_am`) VALUES
(1, 1, '2026-01-02', '2026-01-02 05:57:00', '2026-01-02 16:05:08', NULL, NULL, 0, 0, NULL, NULL, NULL, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-02 08:04:22', '2026-01-02 08:05:22'),
(4, 1, '2026-01-23', '2026-01-23 05:00:00', '2026-01-23 16:50:00', NULL, NULL, 0, 0, NULL, NULL, NULL, 11.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-02 11:01:14', '2026-01-02 11:01:27'),
(6, 2, '2026-01-02', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-02 16:32:02', '2026-01-07 08:20:57'),
(9, 1, '2026-01-03', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 8.00, 0.00, 0.00, 0, 0, 0, 0, 1, 0, 0, 0, 1, NULL, '2026-01-03 12:56:33', '2026-01-03 12:56:33'),
(10, 1, '2026-02-02', '2026-02-02 05:00:00', '2026-02-02 16:00:00', NULL, NULL, 45, 0, NULL, NULL, NULL, 10.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-03 13:42:21', '2026-01-03 13:42:34'),
(12, 2, '2026-01-04', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-07 07:41:42', '2026-01-07 15:00:00'),
(15, 2, '2026-01-05', '2026-01-05 05:30:00', '2026-01-05 15:37:00', NULL, NULL, 45, 0, NULL, NULL, NULL, 9.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-07 08:19:46', '2026-01-07 08:20:11'),
(23, 2, '2026-01-03', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-07 08:23:14', '2026-01-07 08:23:14'),
(28, 2, '2026-01-07', '2026-01-07 07:59:00', '2026-01-07 15:00:00', NULL, NULL, 45, 0, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-01-07 15:00:23', '2026-01-09 06:58:12'),
(30, 2, '2026-01-19', '2026-01-19 07:01:00', '2026-01-19 21:22:00', NULL, NULL, 45, 0, NULL, NULL, NULL, 9.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-09 09:55:14', '2026-01-20 18:33:14'),
(32, 1, '2026-01-09', '2026-01-09 12:56:00', '2026-01-09 16:00:00', NULL, NULL, 0, 0, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-10 08:09:09', '2026-01-10 08:09:09'),
(33, 2, '2026-01-12', '2026-01-12 01:01:01', '2026-01-12 04:04:04', NULL, NULL, 0, 0, NULL, NULL, NULL, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-10 10:20:19', '2026-01-10 10:20:38'),
(37, 1, '2026-01-10', '2026-01-10 10:16:56', '2026-01-10 16:00:00', NULL, NULL, 30, 0, NULL, NULL, NULL, 5.01, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 07:02:14', '2026-01-11 07:02:14'),
(38, 2, '2026-01-10', '2026-01-10 08:30:52', '2026-01-10 22:00:00', NULL, NULL, 30, 0, NULL, NULL, NULL, 8.88, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 07:02:32', '2026-01-11 07:15:35'),
(40, 2, '2026-01-11', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 17:04:39', '2026-01-11 17:04:39'),
(41, 5, '2026-01-11', '2026-01-11 06:00:00', '2026-01-11 14:00:00', NULL, NULL, 45, 0, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-11 20:22:18', '2026-01-12 18:07:37'),
(43, 2, '2025-12-20', '2025-12-20 10:10:14', '2025-12-20 22:22:05', NULL, NULL, 45, 0, NULL, NULL, NULL, 11.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-18 07:58:19', '2026-01-18 07:58:36'),
(46, 2, '2026-01-18', '2026-01-18 05:05:05', '2026-01-18 23:00:00', NULL, NULL, 120, 0, NULL, NULL, NULL, 15.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-01-18 08:01:32', '2026-01-19 19:41:32'),
(51, 2, '2026-01-20', '2026-01-20 04:35:00', '2026-01-20 22:00:00', NULL, NULL, 45, 0, NULL, NULL, NULL, 16.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-21 20:29:54', '2026-01-21 20:29:54'),
(52, 2, '2026-01-21', '2026-01-21 20:32:21', '2026-01-21 23:00:00', NULL, NULL, 0, 0, NULL, NULL, NULL, 2.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-22 19:56:18', '2026-01-22 19:56:18'),
(53, 2, '2026-01-22', '2026-01-22 19:55:29', '2026-01-22 22:22:22', NULL, NULL, 0, 0, NULL, NULL, NULL, 2.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-23 19:33:10', '2026-01-23 19:33:10'),
(54, 2, '2026-01-08', '2026-01-08 12:00:00', '2026-01-08 13:33:03', NULL, NULL, 30, 0, NULL, NULL, NULL, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-24 04:47:13', '2026-01-25 14:24:16'),
(55, 3, '2026-01-20', '2026-01-20 02:02:02', '2026-01-20 05:55:05', NULL, NULL, 0, 0, NULL, NULL, NULL, 3.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-24 05:43:02', '2026-01-25 14:23:52'),
(56, 11, '2026-01-24', '2026-01-24 02:02:02', '2026-01-24 12:12:12', NULL, NULL, 45, 0, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-24 05:43:39', '2026-01-25 14:25:30'),
(57, 11, '2026-01-20', '2026-01-20 02:02:02', '2026-01-20 12:12:12', NULL, NULL, 45, 0, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-24 05:44:10', '2026-01-25 14:25:13'),
(60, 2, '2026-01-23', '2026-01-23 19:31:52', '2026-01-23 23:44:23', NULL, NULL, 0, 0, NULL, NULL, NULL, 3.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-25 14:24:35', '2026-01-25 14:24:35'),
(61, 2, '2026-01-24', '2026-01-24 04:36:22', '2026-01-24 12:12:12', NULL, NULL, 30, 0, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-25 14:24:57', '2026-01-25 14:24:57'),
(64, 2, '2026-01-25', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-25 14:26:29', '2026-01-25 15:14:43'),
(66, 2, '2026-01-26', '2026-01-26 23:42:03', '2026-01-26 23:42:05', NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-25 14:27:34', '2026-01-27 20:23:25'),
(67, 2, '2026-01-16', NULL, '2026-01-16 06:22:22', NULL, NULL, 60, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-01-25 14:29:18', '2026-01-31 04:49:03'),
(69, 2, '2026-01-15', '2026-01-15 21:22:23', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-01-25 14:29:57', '2026-01-27 20:27:23'),
(88, 2, '2026-01-27', '2026-01-27 04:04:04', '2026-01-27 22:22:22', NULL, NULL, 45, 0, NULL, NULL, NULL, 17.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:04:25', '2026-02-01 05:04:35'),
(90, 2, '2026-01-28', '2026-01-28 02:02:02', '2026-01-28 22:22:22', NULL, NULL, 45, 0, NULL, NULL, NULL, 19.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:04:52', '2026-02-01 05:04:58'),
(92, 2, '2026-01-29', '2026-01-29 02:02:02', '2026-01-29 22:22:22', NULL, NULL, 45, 0, NULL, NULL, NULL, 19.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:05:11', '2026-02-01 05:05:17'),
(94, 2, '2026-01-30', '2026-01-30 02:02:02', '2026-01-30 22:22:22', NULL, NULL, 30, 0, NULL, NULL, NULL, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:05:29', '2026-02-01 05:05:51'),
(98, 2, '2025-12-01', '2025-12-01 21:22:02', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:08:17', '2026-02-01 05:08:17'),
(99, 2, '2025-12-02', NULL, '2025-12-02 08:00:00', NULL, NULL, 0, 0, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:08:40', '2026-02-01 05:08:40'),
(100, 2, '2025-12-03', '2025-12-03 05:55:05', '2025-12-03 22:22:02', NULL, NULL, 45, 0, NULL, NULL, NULL, 15.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:10:00', '2026-02-01 05:10:08'),
(104, 2, '2025-12-04', '2025-12-04 05:55:05', '2025-12-04 22:22:02', NULL, NULL, 45, 0, NULL, NULL, NULL, 15.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:11:22', '2026-02-01 05:11:36'),
(106, 2, '2025-12-05', '2025-12-05 05:55:05', '2025-12-05 22:22:02', NULL, NULL, 45, 0, NULL, NULL, NULL, 15.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:11:50', '2026-02-01 05:21:14'),
(110, 2, '2025-12-08', '2025-12-08 02:02:02', '2025-12-08 22:22:05', NULL, NULL, 45, 0, NULL, NULL, NULL, 19.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:13:17', '2026-02-01 05:13:35'),
(115, 2, '2025-12-09', '2025-12-09 05:55:05', '2025-12-09 23:23:23', NULL, NULL, 45, 0, NULL, NULL, NULL, 16.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 05:33:52', '2026-02-01 05:55:09'),
(128, 2, '2025-12-10', '2025-12-10 05:55:05', '2025-12-10 05:56:05', NULL, NULL, 0, 0, NULL, NULL, NULL, 0.02, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 06:15:26', '2026-02-01 06:15:51'),
(130, 2, '2026-02-02', '2026-02-02 05:55:05', '2026-02-02 16:00:00', NULL, NULL, 0, 0, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-02-01 06:17:14', '2026-02-01 06:18:25'),
(135, 2, '2026-02-03', '2026-02-03 05:55:05', '2026-02-03 15:45:05', NULL, NULL, 45, 0, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, '2026-02-01 06:52:26', '2026-02-01 06:52:37'),
(137, 2, '2026-02-04', '2026-02-04 05:05:05', '2026-02-04 15:15:05', NULL, NULL, 30, 1, 'keine frühstückspause gemacht', 2, '2026-02-01 07:06:16', 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, '2026-02-01 06:53:24', '2026-02-01 07:06:16');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `terminal`
--

CREATE TABLE `terminal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `standort_beschreibung` varchar(255) DEFAULT NULL,
  `abteilung_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `urlaubsantrag`
--

INSERT INTO `urlaubsantrag` (`id`, `mitarbeiter_id`, `von_datum`, `bis_datum`, `tage_gesamt`, `status`, `antrags_datum`, `entscheidungs_mitarbeiter_id`, `entscheidungs_datum`, `kommentar_mitarbeiter`, `kommentar_genehmiger`, `erstellt_am`, `geaendert_am`) VALUES
(5, 12, '2026-07-02', '2026-07-17', 12.00, 'offen', '2026-01-11 19:21:21', NULL, NULL, 'asdf', NULL, '2026-01-11 19:21:21', '2026-01-11 19:21:21'),
(6, 2, '2026-01-23', '2026-01-31', 6.00, 'storniert', '2026-01-12 06:48:52', NULL, NULL, 'Hahsh', NULL, '2026-01-12 06:48:52', '2026-01-12 06:49:11'),
(7, 2, '2026-01-26', '2026-01-30', 5.00, 'offen', '2026-01-24 04:24:45', NULL, NULL, 'asfd', NULL, '2026-01-24 04:24:45', '2026-01-24 04:24:45');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `urlaub_kontingent_jahr`
--

CREATE TABLE `urlaub_kontingent_jahr` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
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
  `id` bigint(20) UNSIGNED NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED NOT NULL,
  `typ` enum('kommen','gehen') NOT NULL,
  `zeitstempel` datetime NOT NULL,
  `quelle` enum('terminal','web','import') NOT NULL DEFAULT 'terminal',
  `manuell_geaendert` tinyint(1) NOT NULL DEFAULT 0,
  `kommentar` varchar(255) DEFAULT NULL,
  `nachtshift` tinyint(1) NOT NULL DEFAULT 0,
  `terminal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `zeitbuchung`
--

INSERT INTO `zeitbuchung` (`id`, `mitarbeiter_id`, `typ`, `zeitstempel`, `quelle`, `manuell_geaendert`, `kommentar`, `nachtshift`, `terminal_id`, `erstellt_am`, `geaendert_am`) VALUES
(1, 2, 'kommen', '2025-12-20 10:10:14', 'terminal', 0, NULL, 0, NULL, '2025-12-20 10:10:14', '2025-12-20 10:10:14'),
(4, 1, 'kommen', '2026-01-02 05:57:00', 'web', 1, 'test', 0, NULL, '2026-01-02 08:04:22', '2026-01-02 08:04:52'),
(5, 1, 'gehen', '2026-01-02 16:05:08', 'web', 1, 'test', 0, NULL, '2026-01-02 08:05:22', '2026-01-02 08:05:22'),
(6, 1, 'kommen', '2026-01-23 05:00:00', 'web', 1, NULL, 0, NULL, '2026-01-02 11:01:14', '2026-01-02 11:01:14'),
(7, 1, 'gehen', '2026-01-23 16:50:00', 'web', 1, NULL, 0, NULL, '2026-01-02 11:01:27', '2026-01-02 11:01:27'),
(15, 1, 'kommen', '2026-02-02 05:00:00', 'web', 1, NULL, 0, NULL, '2026-01-03 13:42:21', '2026-01-03 13:42:21'),
(16, 1, 'gehen', '2026-02-02 16:00:00', 'web', 1, NULL, 0, NULL, '2026-01-03 13:42:34', '2026-01-03 13:42:34'),
(22, 1, 'kommen', '2026-01-04 06:46:59', 'terminal', 0, NULL, 0, NULL, '2026-01-04 06:46:59', '2026-01-04 06:46:59'),
(25, 1, 'gehen', '2026-01-04 12:41:17', 'terminal', 0, NULL, 0, NULL, '2026-01-04 12:41:17', '2026-01-04 12:41:17'),
(28, 2, 'gehen', '2026-01-05 15:37:00', 'terminal', 1, NULL, 0, NULL, '2026-01-05 15:37:29', '2026-01-07 08:19:46'),
(29, 2, 'kommen', '2026-01-07 07:59:00', 'terminal', 0, NULL, 0, NULL, '2026-01-07 07:59:00', '2026-01-07 07:59:00'),
(30, 2, 'kommen', '2026-01-05 05:30:00', 'web', 1, NULL, 0, NULL, '2026-01-07 08:20:11', '2026-01-07 08:20:11'),
(31, 2, 'gehen', '2026-01-07 15:00:00', 'web', 1, NULL, 0, NULL, '2026-01-07 15:00:23', '2026-01-07 15:00:23'),
(32, 2, 'kommen', '2026-01-19 07:01:00', 'web', 1, NULL, 0, NULL, '2026-01-09 09:55:13', '2026-01-09 09:55:13'),
(33, 2, 'gehen', '2026-01-19 15:59:05', 'web', 1, NULL, 0, NULL, '2026-01-09 09:55:29', '2026-01-09 09:55:29'),
(34, 2, 'kommen', '2026-01-09 10:04:09', 'terminal', 0, NULL, 0, NULL, '2026-01-09 10:04:09', '2026-01-09 10:04:09'),
(35, 2, 'gehen', '2026-01-09 10:34:46', 'terminal', 0, NULL, 0, NULL, '2026-01-09 10:34:46', '2026-01-09 10:34:46'),
(36, 2, 'kommen', '2026-01-09 11:05:15', 'terminal', 0, NULL, 0, NULL, '2026-01-09 11:05:15', '2026-01-09 11:05:15'),
(37, 2, 'gehen', '2026-01-09 11:05:17', 'terminal', 0, NULL, 0, NULL, '2026-01-09 11:05:17', '2026-01-09 11:05:17'),
(38, 1, 'kommen', '2026-01-09 12:56:00', 'terminal', 0, NULL, 0, NULL, '2026-01-09 12:56:00', '2026-01-09 12:56:00'),
(39, 2, 'kommen', '2026-01-09 20:05:21', 'terminal', 0, NULL, 0, NULL, '2026-01-09 20:05:21', '2026-01-09 20:05:21'),
(40, 2, 'gehen', '2026-01-09 20:06:38', 'terminal', 0, NULL, 0, NULL, '2026-01-09 20:06:38', '2026-01-09 20:06:38'),
(41, 1, 'gehen', '2026-01-09 16:00:00', 'web', 1, 'test', 0, NULL, '2026-01-10 08:09:09', '2026-01-10 08:09:09'),
(42, 2, 'kommen', '2026-01-10 08:30:52', 'terminal', 0, NULL, 0, NULL, '2026-01-10 08:30:52', '2026-01-10 08:30:52'),
(43, 2, 'gehen', '2026-01-10 08:45:04', 'terminal', 0, NULL, 0, NULL, '2026-01-10 08:45:04', '2026-01-10 08:45:04'),
(44, 2, 'kommen', '2026-01-10 10:16:36', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:16:36', '2026-01-10 10:16:36'),
(45, 1, 'kommen', '2026-01-10 10:16:56', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:16:56', '2026-01-10 10:16:56'),
(46, 1, 'gehen', '2026-01-10 10:17:31', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:17:31', '2026-01-10 10:17:31'),
(47, 1, 'kommen', '2026-01-10 10:17:36', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:17:36', '2026-01-10 10:17:36'),
(48, 2, 'gehen', '2026-01-10 10:19:18', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:18', '2026-01-10 10:19:18'),
(49, 2, 'kommen', '2026-01-10 10:19:21', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:21', '2026-01-10 10:19:21'),
(50, 2, 'gehen', '2026-01-10 10:19:24', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:24', '2026-01-10 10:19:24'),
(51, 2, 'kommen', '2026-01-10 10:19:26', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:26', '2026-01-10 10:19:26'),
(52, 2, 'gehen', '2026-01-10 10:19:28', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:28', '2026-01-10 10:19:28'),
(53, 2, 'kommen', '2026-01-10 10:19:55', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:55', '2026-01-10 10:19:55'),
(54, 2, 'gehen', '2026-01-10 10:19:58', 'terminal', 0, NULL, 0, NULL, '2026-01-10 10:19:58', '2026-01-10 10:19:58'),
(55, 2, 'kommen', '2026-01-12 01:01:01', 'web', 1, '1', 0, NULL, '2026-01-10 10:20:19', '2026-01-10 10:20:19'),
(56, 2, 'gehen', '2026-01-12 02:02:02', 'web', 1, '2', 0, NULL, '2026-01-10 10:20:24', '2026-01-10 10:20:24'),
(57, 2, 'kommen', '2026-01-12 03:03:03', 'web', 1, '3', 0, NULL, '2026-01-10 10:20:30', '2026-01-10 10:20:30'),
(58, 2, 'gehen', '2026-01-12 04:04:04', 'web', 1, '4', 0, NULL, '2026-01-10 10:20:38', '2026-01-10 10:20:38'),
(59, 2, 'kommen', '2026-01-10 11:01:05', 'terminal', 0, NULL, 0, NULL, '2026-01-10 11:01:23', '2026-01-10 11:01:23'),
(60, 2, 'gehen', '2026-01-10 11:05:49', 'terminal', 0, NULL, 0, NULL, '2026-01-10 11:05:49', '2026-01-10 11:05:49'),
(61, 2, 'kommen', '2026-01-10 11:26:45', 'terminal', 0, NULL, 0, NULL, '2026-01-10 11:26:45', '2026-01-10 11:26:45'),
(62, 2, 'gehen', '2026-01-10 16:53:06', 'terminal', 0, NULL, 0, NULL, '2026-01-10 16:53:06', '2026-01-10 16:53:06'),
(63, 2, 'kommen', '2026-01-10 18:09:12', 'terminal', 0, NULL, 0, NULL, '2026-01-10 18:09:12', '2026-01-10 18:09:12'),
(64, 1, 'gehen', '2026-01-10 16:00:00', 'web', 1, '1', 0, NULL, '2026-01-11 07:02:14', '2026-01-11 07:02:14'),
(65, 2, 'gehen', '2026-01-10 22:00:00', 'web', 1, '1', 0, NULL, '2026-01-11 07:02:32', '2026-01-11 07:15:35'),
(67, 12, 'kommen', '2026-01-11 19:19:12', 'terminal', 0, NULL, 0, NULL, '2026-01-11 19:19:12', '2026-01-11 19:19:12'),
(68, 12, 'gehen', '2026-01-11 19:19:54', 'terminal', 0, NULL, 0, NULL, '2026-01-11 19:19:54', '2026-01-11 19:19:54'),
(69, 5, 'kommen', '2026-01-11 06:00:00', 'web', 1, 'testen', 0, NULL, '2026-01-11 20:22:18', '2026-01-11 20:22:18'),
(70, 2, 'kommen', '2026-01-12 06:46:19', 'terminal', 0, NULL, 0, NULL, '2026-01-12 06:46:19', '2026-01-12 06:46:19'),
(71, 5, 'gehen', '2026-01-11 14:00:00', 'web', 1, 'test', 0, NULL, '2026-01-12 18:07:37', '2026-01-12 18:07:37'),
(72, 2, 'gehen', '2026-01-12 19:06:38', 'terminal', 0, NULL, 0, NULL, '2026-01-12 19:06:38', '2026-01-12 19:06:38'),
(73, 2, 'kommen', '2026-01-14 12:35:42', 'terminal', 0, NULL, 0, NULL, '2026-01-14 12:35:42', '2026-01-14 12:35:42'),
(74, 2, 'gehen', '2026-01-14 12:38:52', 'terminal', 0, NULL, 0, NULL, '2026-01-14 12:38:52', '2026-01-14 12:38:52'),
(77, 2, 'kommen', '2026-01-17 04:50:25', 'terminal', 0, NULL, 0, NULL, '2026-01-17 04:50:25', '2026-01-17 04:50:25'),
(78, 2, 'gehen', '2026-01-17 05:13:59', 'terminal', 0, NULL, 0, NULL, '2026-01-17 05:13:59', '2026-01-17 05:13:59'),
(79, 2, 'gehen', '2025-12-20 22:22:05', 'web', 1, 'ja', 0, NULL, '2026-01-18 07:58:36', '2026-01-18 07:58:36'),
(80, 2, 'kommen', '2026-01-18 05:05:05', 'web', 1, '3', 0, NULL, '2026-01-18 08:01:32', '2026-01-18 08:01:32'),
(81, 2, 'kommen', '2026-01-19 19:11:08', 'terminal', 0, NULL, 0, NULL, '2026-01-19 19:11:08', '2026-01-19 19:11:08'),
(82, 2, 'gehen', '2026-01-18 23:00:00', 'web', 1, 'fhz', 0, NULL, '2026-01-19 19:41:31', '2026-01-19 19:41:31'),
(83, 2, 'kommen', '2026-01-20 04:35:00', 'terminal', 0, NULL, 0, NULL, '2026-01-20 04:35:00', '2026-01-20 04:35:00'),
(84, 2, 'gehen', '2026-01-19 21:22:00', 'web', 1, '115', 0, NULL, '2026-01-20 18:32:28', '2026-01-20 18:33:14'),
(85, 2, 'gehen', '2026-01-20 22:00:00', 'web', 1, '11', 0, NULL, '2026-01-21 20:29:54', '2026-01-21 20:29:54'),
(86, 2, 'kommen', '2026-01-21 20:32:21', 'terminal', 0, NULL, 0, NULL, '2026-01-21 20:32:21', '2026-01-21 20:32:21'),
(87, 2, 'kommen', '2026-01-22 19:55:29', 'terminal', 0, NULL, 0, NULL, '2026-01-22 19:55:29', '2026-01-22 19:55:29'),
(88, 2, 'gehen', '2026-01-21 23:00:00', 'web', 1, '2', 0, NULL, '2026-01-22 19:56:18', '2026-01-22 19:56:18'),
(89, 2, 'kommen', '2026-01-23 19:31:52', 'terminal', 0, NULL, 0, NULL, '2026-01-23 19:31:52', '2026-01-23 19:31:52'),
(90, 2, 'gehen', '2026-01-22 22:22:22', 'web', 1, 'sd', 0, NULL, '2026-01-23 19:33:10', '2026-01-23 19:33:10'),
(91, 2, 'kommen', '2026-01-24 04:36:22', 'terminal', 0, NULL, 0, NULL, '2026-01-24 04:36:22', '2026-01-24 04:36:22'),
(92, 2, 'kommen', '2026-01-08 12:00:00', 'web', 1, '23', 0, NULL, '2026-01-24 04:47:13', '2026-01-24 04:47:13'),
(93, 3, 'kommen', '2026-01-20 02:02:02', 'web', 1, '2', 0, NULL, '2026-01-24 05:43:02', '2026-01-24 05:43:02'),
(94, 11, 'kommen', '2026-01-24 02:02:02', 'web', 1, '2', 0, NULL, '2026-01-24 05:43:39', '2026-01-24 05:43:39'),
(95, 11, 'kommen', '2026-01-20 02:02:02', 'web', 1, '2', 0, NULL, '2026-01-24 05:44:10', '2026-01-24 05:44:10'),
(96, 3, 'gehen', '2026-01-20 05:55:05', 'web', 1, '23', 0, NULL, '2026-01-25 14:23:52', '2026-01-25 14:23:52'),
(97, 2, 'gehen', '2026-01-08 13:33:03', 'web', 1, '3', 0, NULL, '2026-01-25 14:24:15', '2026-01-25 14:24:15'),
(98, 2, 'gehen', '2026-01-23 23:44:23', 'web', 1, '12', 0, NULL, '2026-01-25 14:24:35', '2026-01-25 14:24:35'),
(99, 2, 'gehen', '2026-01-24 12:12:12', 'web', 1, '12', 0, NULL, '2026-01-25 14:24:57', '2026-01-25 14:24:57'),
(100, 11, 'gehen', '2026-01-20 12:12:12', 'web', 1, '12', 0, NULL, '2026-01-25 14:25:13', '2026-01-25 14:25:13'),
(101, 11, 'gehen', '2026-01-24 12:12:12', 'web', 1, '12', 0, NULL, '2026-01-25 14:25:30', '2026-01-25 14:25:30'),
(108, 2, 'kommen', '2026-01-25 16:52:15', 'terminal', 0, NULL, 0, NULL, '2026-01-25 16:52:15', '2026-01-25 16:52:15'),
(109, 2, 'gehen', '2026-01-25 16:52:22', 'terminal', 0, NULL, 0, NULL, '2026-01-25 16:52:22', '2026-01-25 16:52:22'),
(112, 2, 'kommen', '2026-01-25 18:21:43', 'terminal', 0, NULL, 0, NULL, '2026-01-25 18:21:43', '2026-01-25 18:21:43'),
(113, 2, 'gehen', '2026-01-25 19:13:34', 'terminal', 0, NULL, 0, NULL, '2026-01-25 19:13:34', '2026-01-25 19:13:34'),
(114, 2, 'kommen', '2026-01-25 19:28:00', 'terminal', 0, NULL, 1, NULL, '2026-01-25 19:28:00', '2026-01-25 19:28:00'),
(115, 2, 'gehen', '2026-01-26 05:05:05', 'web', 1, '234', 0, NULL, '2026-01-27 19:03:48', '2026-01-27 19:03:48'),
(116, 2, 'kommen', '2026-01-26 23:42:03', 'web', 1, '13', 0, NULL, '2026-01-27 20:23:08', '2026-01-27 20:23:08'),
(117, 2, 'gehen', '2026-01-26 23:42:05', 'web', 1, '345', 0, NULL, '2026-01-27 20:23:25', '2026-01-27 20:23:25'),
(118, 2, 'kommen', '2026-01-15 21:22:23', 'web', 1, '123', 1, NULL, '2026-01-27 20:27:23', '2026-01-27 20:27:23'),
(119, 2, 'gehen', '2026-01-16 06:22:22', 'web', 1, '23', 0, NULL, '2026-01-27 20:27:40', '2026-01-27 20:27:40'),
(120, 2, 'kommen', '2026-01-27 04:04:04', 'web', 1, '4', 0, NULL, '2026-02-01 05:04:25', '2026-02-01 05:04:25'),
(121, 2, 'gehen', '2026-01-27 22:22:22', 'web', 1, '22', 0, NULL, '2026-02-01 05:04:35', '2026-02-01 05:04:35'),
(122, 2, 'kommen', '2026-01-28 02:02:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:04:52', '2026-02-01 05:04:52'),
(123, 2, 'gehen', '2026-01-28 22:22:22', 'web', 1, '2', 0, NULL, '2026-02-01 05:04:58', '2026-02-01 05:04:58'),
(124, 2, 'kommen', '2026-01-29 02:02:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:05:11', '2026-02-01 05:05:11'),
(125, 2, 'gehen', '2026-01-29 22:22:22', 'web', 1, '2', 0, NULL, '2026-02-01 05:05:17', '2026-02-01 05:05:17'),
(126, 2, 'kommen', '2026-01-30 02:02:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:05:29', '2026-02-01 05:05:29'),
(127, 2, 'gehen', '2026-01-30 05:05:05', 'web', 1, '5', 0, NULL, '2026-02-01 05:05:35', '2026-02-01 05:05:35'),
(128, 2, 'kommen', '2026-01-30 14:04:04', 'web', 1, '4', 0, NULL, '2026-02-01 05:05:44', '2026-02-01 05:05:44'),
(129, 2, 'gehen', '2026-01-30 22:22:22', 'web', 1, '2', 0, NULL, '2026-02-01 05:05:51', '2026-02-01 05:05:51'),
(130, 2, 'kommen', '2025-12-01 21:22:02', 'web', 1, '2', 1, NULL, '2026-02-01 05:08:17', '2026-02-01 05:08:17'),
(131, 2, 'gehen', '2025-12-02 08:00:00', 'web', 1, '5', 0, NULL, '2026-02-01 05:08:40', '2026-02-01 05:08:40'),
(132, 2, 'kommen', '2025-12-03 05:55:05', 'web', 1, '5', 0, NULL, '2026-02-01 05:10:00', '2026-02-01 05:10:00'),
(133, 2, 'gehen', '2025-12-03 22:22:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:10:08', '2026-02-01 05:10:08'),
(135, 2, 'kommen', '2025-12-04 05:55:05', 'web', 1, '5', 0, NULL, '2026-02-01 05:11:22', '2026-02-01 05:11:22'),
(136, 2, 'gehen', '2025-12-04 22:22:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:11:36', '2026-02-01 05:11:36'),
(137, 2, 'kommen', '2025-12-05 05:55:05', 'web', 1, '2', 0, NULL, '2026-02-01 05:11:50', '2026-02-01 05:11:50'),
(138, 2, 'gehen', '2025-12-05 05:55:22', 'web', 1, '5', 0, NULL, '2026-02-01 05:11:56', '2026-02-01 05:20:27'),
(139, 2, 'kommen', '2025-12-05 05:55:25', 'web', 1, '5', 0, NULL, '2026-02-01 05:12:18', '2026-02-01 05:21:14'),
(140, 2, 'gehen', '2025-12-05 22:22:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:12:41', '2026-02-01 05:12:41'),
(141, 2, 'kommen', '2025-12-08 02:02:02', 'web', 1, '2', 0, NULL, '2026-02-01 05:13:17', '2026-02-01 05:13:17'),
(142, 2, 'gehen', '2025-12-08 22:22:05', 'web', 1, '2', 0, NULL, '2026-02-01 05:13:35', '2026-02-01 05:13:35'),
(146, 2, 'kommen', '2025-12-09 05:55:05', 'web', 1, '5', 0, NULL, '2026-02-01 05:40:47', '2026-02-01 05:40:47'),
(147, 2, 'gehen', '2025-12-09 05:55:09', 'web', 1, '5', 0, NULL, '2026-02-01 05:41:29', '2026-02-01 06:05:11'),
(149, 2, 'kommen', '2025-12-09 05:55:23', 'web', 1, '5', 0, NULL, '2026-02-01 05:54:59', '2026-02-01 05:54:59'),
(150, 2, 'gehen', '2025-12-09 23:23:23', 'web', 1, '5', 0, NULL, '2026-02-01 05:55:09', '2026-02-01 05:55:09'),
(151, 2, 'kommen', '2025-12-10 05:55:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:15:26', '2026-02-01 06:15:26'),
(152, 2, 'gehen', '2025-12-10 05:56:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:15:51', '2026-02-01 06:15:51'),
(153, 2, 'kommen', '2026-02-02 05:55:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:17:14', '2026-02-01 06:17:14'),
(154, 2, 'gehen', '2026-02-02 10:00:00', 'web', 1, '7', 0, NULL, '2026-02-01 06:17:26', '2026-02-01 06:17:26'),
(155, 2, 'kommen', '2026-02-02 12:00:00', 'web', 1, '4', 0, NULL, '2026-02-01 06:17:49', '2026-02-01 06:17:49'),
(156, 2, 'gehen', '2026-02-02 16:00:00', 'web', 1, '4', 0, NULL, '2026-02-01 06:17:59', '2026-02-01 06:17:59'),
(157, 2, 'kommen', '2026-02-03 05:55:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:52:26', '2026-02-01 06:52:26'),
(158, 2, 'gehen', '2026-02-03 15:45:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:52:37', '2026-02-01 06:52:37'),
(159, 2, 'kommen', '2026-02-04 05:05:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:53:24', '2026-02-01 06:53:24'),
(160, 2, 'gehen', '2026-02-04 15:15:05', 'web', 1, '5', 0, NULL, '2026-02-01 06:53:45', '2026-02-01 06:53:45');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `zeit_rundungsregel`
--

CREATE TABLE `zeit_rundungsregel` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
-- Indizes für die Tabelle `auftrag_arbeitsschritt`
--
ALTER TABLE `auftrag_arbeitsschritt`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_auftrag_arbeitsschritt` (`auftrag_id`,`arbeitsschritt_code`),
  ADD KEY `idx_auftrag_arbeitsschritt_auftrag` (`auftrag_id`);

--
-- Indizes für die Tabelle `auftragszeit`
--
ALTER TABLE `auftragszeit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auftragszeit_mitarbeiter` (`mitarbeiter_id`),
  ADD KEY `idx_auftragszeit_auftrag` (`auftrag_id`),
  ADD KEY `idx_auftragszeit_arbeitsschritt` (`arbeitsschritt_id`),
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
  ADD KEY `idx_tageswerte_datum` (`datum`),
  ADD KEY `idx_pause_override_gesetzt_von` (`pause_override_gesetzt_von_mitarbeiter_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `auftrag`
--
ALTER TABLE `auftrag`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `auftragszeit`
--
ALTER TABLE `auftragszeit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `betriebsferien`
--
ALTER TABLE `betriebsferien`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `config`
--
ALTER TABLE `config`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88080;

--
-- AUTO_INCREMENT für Tabelle `db_injektionsqueue`
--
ALTER TABLE `db_injektionsqueue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `feiertag`
--
ALTER TABLE `feiertag`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT für Tabelle `krankzeitraum`
--
ALTER TABLE `krankzeitraum`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `kurzarbeit_plan`
--
ALTER TABLE `kurzarbeit_plan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `maschine`
--
ALTER TABLE `maschine`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter`
--
ALTER TABLE `mitarbeiter`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter_genehmiger`
--
ALTER TABLE `mitarbeiter_genehmiger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter_hat_abteilung`
--
ALTER TABLE `mitarbeiter_hat_abteilung`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT für Tabelle `mitarbeiter_hat_rolle_scope`
--
ALTER TABLE `mitarbeiter_hat_rolle_scope`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT für Tabelle `monatswerte_mitarbeiter`
--
ALTER TABLE `monatswerte_mitarbeiter`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pausenentscheidung`
--
ALTER TABLE `pausenentscheidung`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pausenfenster`
--
ALTER TABLE `pausenfenster`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `recht`
--
ALTER TABLE `recht`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT für Tabelle `rolle`
--
ALTER TABLE `rolle`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `sonstiges_grund`
--
ALTER TABLE `sonstiges_grund`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `stundenkonto_batch`
--
ALTER TABLE `stundenkonto_batch`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `stundenkonto_korrektur`
--
ALTER TABLE `stundenkonto_korrektur`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `system_log`
--
ALTER TABLE `system_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1198;

--
-- AUTO_INCREMENT für Tabelle `tageswerte_mitarbeiter`
--
ALTER TABLE `tageswerte_mitarbeiter`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT für Tabelle `terminal`
--
ALTER TABLE `terminal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `urlaubsantrag`
--
ALTER TABLE `urlaubsantrag`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `urlaub_kontingent_jahr`
--
ALTER TABLE `urlaub_kontingent_jahr`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `zeitbuchung`
--
ALTER TABLE `zeitbuchung`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT für Tabelle `zeit_rundungsregel`
--
ALTER TABLE `zeit_rundungsregel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `abteilung`
--
ALTER TABLE `abteilung`
  ADD CONSTRAINT `abteilung_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abteilung_parent` FOREIGN KEY (`parent_id`) REFERENCES `abteilung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `tageswerte_mitarbeiter`
--
ALTER TABLE `tageswerte_mitarbeiter`
  ADD CONSTRAINT `fk_pause_override_gesetzt_von` FOREIGN KEY (`pause_override_gesetzt_von_mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
