-- Neue Tabelle für Arbeitsschritte je Auftrag
CREATE TABLE IF NOT EXISTS `auftrag_arbeitsschritt` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `auftrag_id` bigint(20) UNSIGNED NOT NULL,
  `arbeitsschritt_code` varchar(100) NOT NULL,
  `bezeichnung` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_auftrag_arbeitsschritt` (`auftrag_id`,`arbeitsschritt_code`),
  KEY `idx_auftrag_arbeitsschritt_auftrag` (`auftrag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auftragszeit um Arbeitsschritt-ID ergaenzen
ALTER TABLE `auftragszeit`
  ADD COLUMN `arbeitsschritt_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `auftrag_id`,
  ADD KEY `idx_auftragszeit_arbeitsschritt` (`arbeitsschritt_id`);
