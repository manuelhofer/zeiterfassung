-- Migration: Tabelle `terminal_kopplung` fuer die Terminal-Anmeldung
-- Patch: P-2026-08-08-30
-- Datum: 2026-08-08
--
-- Zweck:
--   Ein Terminal meldet sich mit einem Kopplungscode am Backend an und erhaelt
--   dafuer einen eigenen Datenbankbenutzer. Diese Tabelle haelt die vergebenen
--   Codes (siehe `docs/spezifikation_terminal_installation.md`, Abschnitt 2a).
--
-- Wichtig:
--   Gespeichert wird **nur der Hash** des Codes, nie der Code selbst - genau
--   wie bei einem Passwort. Der Klartext wird einmal angezeigt und ist danach
--   nicht wiederherstellbar; wer ihn verliert, erzeugt einen neuen.
--
-- Idempotent (`CREATE TABLE IF NOT EXISTS`).
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/05_migration_terminal_kopplung.sql

CREATE TABLE IF NOT EXISTS `terminal_kopplung` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `terminal_id` bigint(20) UNSIGNED NOT NULL,
  `code_hash` char(64) NOT NULL COMMENT 'SHA-256 des Kopplungscodes, nie der Code selbst',
  `gueltig_bis` datetime NOT NULL,
  `verbraucht_am` datetime DEFAULT NULL COMMENT 'gesetzt beim ersten erfolgreichen Einloesen',
  `verbraucht_von_host` varchar(190) DEFAULT NULL COMMENT 'Kennung des Geraets, das den Code eingeloest hat',
  `erstellt_von_mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_terminal_kopplung_hash` (`code_hash`),
  KEY `idx_terminal_kopplung_terminal` (`terminal_id`),
  KEY `idx_terminal_kopplung_gueltig` (`gueltig_bis`, `verbraucht_am`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrolle (optional):
-- SELECT id, terminal_id, gueltig_bis, verbraucht_am FROM terminal_kopplung ORDER BY id DESC;
