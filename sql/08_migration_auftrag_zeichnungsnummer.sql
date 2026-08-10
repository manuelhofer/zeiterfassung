-- Migration 08: Zeichnungsnummer am Auftrag
--
-- Warum:
-- In der Fertigung wird ein Auftrag im Gespräch fast immer über die Nummer der
-- zugehörigen Zeichnung gesucht, nicht über die Auftragsnummer. Bisher blieb
-- dafür nur die Kurzbeschreibung – ein Feld, in das dann Zeichnungsnummern
-- hineingeschrieben wurden und in dem sie niemand zuverlässig wiederfindet.
--
-- Wie alle Auftragsfelder ausser der Auftragsnummer ist die Angabe freiwillig
-- (`docs/fachregeln/auftraege_und_codes.md`, Abschnitt 1): Dies ist eine
-- Zeiterfassung, keine Warenwirtschaft.
--
-- Idempotent: Mehrfaches Ausführen ist unschädlich.

SET @spalte_fehlt := (
    SELECT COUNT(*) = 0
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'auftrag'
      AND COLUMN_NAME  = 'zeichnungsnummer'
);

SET @sql := IF(@spalte_fehlt,
    'ALTER TABLE `auftrag`
        ADD COLUMN `zeichnungsnummer` VARCHAR(100) DEFAULT NULL
        COMMENT ''Freiwillig - Nummer der zugehoerigen Zeichnung''
        AFTER `kunde`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Suchbar machen: Die Auftragsliste durchsucht dieses Feld mit, und ein
-- LIKE '%...%' kann den Index zwar nicht nutzen, ein Präfix-Treffer
-- ('Z-1234%') aber sehr wohl.
SET @index_fehlt := (
    SELECT COUNT(*) = 0
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'auftrag'
      AND INDEX_NAME   = 'idx_auftrag_zeichnungsnummer'
);

SET @sql := IF(@index_fehlt,
    'ALTER TABLE `auftrag`
        ADD KEY `idx_auftrag_zeichnungsnummer` (`zeichnungsnummer`)',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
