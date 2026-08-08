-- Migration: Tabelle `arbeitsschritt_katalog` nachtragen
-- Patch: P-2026-08-08-12
-- Datum: 2026-08-08
--
-- Zweck:
--   Zentrale, auftragsunabhaengige Standard-Arbeitsschritte (z. B. `fraesen`).
--   Die Arbeitsvorbereitung pflegt sie einmal; der QR-Code kann beliebig oft
--   ausgedruckt und an Maschinen gehaengt werden.
--
--   Der Katalog ist eine **Vorlage**, keine Buchung: Beim Scannen entsteht wie
--   bisher ein Eintrag in `auftrag_arbeitsschritt` fuer den jeweiligen Auftrag.
--   An dieser Mechanik aendert die Migration nichts.
--
-- Eigenschaften:
--   - Idempotent (`CREATE TABLE IF NOT EXISTS`), mehrfaches Ausfuehren ist
--     unschaedlich.
--   - Legt keine Daten an. Der Katalog wird im Backend gefuellt.
--   - Neuinstallationen brauchen dieses Skript nicht; dort steckt die Tabelle
--     bereits in `sql/01_initial_schema.sql`.
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/03_migration_arbeitsschritt_katalog.sql

CREATE TABLE IF NOT EXISTS `arbeitsschritt_katalog` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `bezeichnung` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_arbeitsschritt_katalog_code` (`code`),
  KEY `idx_arbeitsschritt_katalog_aktiv` (`aktiv`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrolle (optional):
-- SELECT COUNT(*) AS katalogeintraege FROM arbeitsschritt_katalog;
