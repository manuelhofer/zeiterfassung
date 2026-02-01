-- Migration 27: Auftrag-Arbeitsschritt-Tabelle und Verknuepfung in Auftragszeit

CREATE TABLE `auftrag_arbeitsschritt` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `auftrag_id` bigint(20) UNSIGNED NOT NULL,
  `arbeitsschritt_code` varchar(100) NOT NULL,
  `bezeichnung` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_auftrag_arbeitsschritt` (`auftrag_id`,`arbeitsschritt_code`),
  KEY `idx_auftrag_arbeitsschritt_auftrag` (`auftrag_id`),
  CONSTRAINT `fk_auftrag_arbeitsschritt_auftrag`
    FOREIGN KEY (`auftrag_id`) REFERENCES `auftrag`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `auftragszeit`
  ADD COLUMN `arbeitsschritt_id` bigint(20) UNSIGNED NULL AFTER `auftrag_id`,
  ADD KEY `idx_auftragszeit_arbeitsschritt_id` (`arbeitsschritt_id`),
  ADD CONSTRAINT `fk_auftragszeit_arbeitsschritt`
    FOREIGN KEY (`arbeitsschritt_id`) REFERENCES `auftrag_arbeitsschritt`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
