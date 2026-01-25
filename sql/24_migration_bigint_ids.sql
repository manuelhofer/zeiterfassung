-- Migration: Alle ID-Spalten auf BIGINT UNSIGNED umstellen
-- Datum: 2026-01-??

SET FOREIGN_KEY_CHECKS = 0;

SET @fk_abteilung_parent = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'abteilung'
    AND COLUMN_NAME = 'parent_id'
    AND REFERENCED_TABLE_NAME = 'abteilung'
  LIMIT 1
);
SET @sql_drop_fk_abteilung_parent = IF(
  @fk_abteilung_parent IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE abteilung DROP FOREIGN KEY ', @fk_abteilung_parent)
);
PREPARE stmt_drop_fk_abteilung_parent FROM @sql_drop_fk_abteilung_parent;
EXECUTE stmt_drop_fk_abteilung_parent;
DEALLOCATE PREPARE stmt_drop_fk_abteilung_parent;

SET @fk_betriebsferien_abteilung = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'betriebsferien'
    AND COLUMN_NAME = 'abteilung_id'
    AND REFERENCED_TABLE_NAME = 'abteilung'
  LIMIT 1
);
SET @sql_drop_fk_betriebsferien_abteilung = IF(
  @fk_betriebsferien_abteilung IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE betriebsferien DROP FOREIGN KEY ', @fk_betriebsferien_abteilung)
);
PREPARE stmt_drop_fk_betriebsferien_abteilung FROM @sql_drop_fk_betriebsferien_abteilung;
EXECUTE stmt_drop_fk_betriebsferien_abteilung;
DEALLOCATE PREPARE stmt_drop_fk_betriebsferien_abteilung;

SET @fk_maschine_abteilung = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'maschine'
    AND COLUMN_NAME = 'abteilung_id'
    AND REFERENCED_TABLE_NAME = 'abteilung'
  LIMIT 1
);
SET @sql_drop_fk_maschine_abteilung = IF(
  @fk_maschine_abteilung IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE maschine DROP FOREIGN KEY ', @fk_maschine_abteilung)
);
PREPARE stmt_drop_fk_maschine_abteilung FROM @sql_drop_fk_maschine_abteilung;
EXECUTE stmt_drop_fk_maschine_abteilung;
DEALLOCATE PREPARE stmt_drop_fk_maschine_abteilung;

SET @fk_mitarbeiter_hat_abteilung_abteilung = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mitarbeiter_hat_abteilung'
    AND COLUMN_NAME = 'abteilung_id'
    AND REFERENCED_TABLE_NAME = 'abteilung'
  LIMIT 1
);
SET @sql_drop_fk_mitarbeiter_hat_abteilung_abteilung = IF(
  @fk_mitarbeiter_hat_abteilung_abteilung IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE mitarbeiter_hat_abteilung DROP FOREIGN KEY ', @fk_mitarbeiter_hat_abteilung_abteilung)
);
PREPARE stmt_drop_fk_mitarbeiter_hat_abteilung_abteilung FROM @sql_drop_fk_mitarbeiter_hat_abteilung_abteilung;
EXECUTE stmt_drop_fk_mitarbeiter_hat_abteilung_abteilung;
DEALLOCATE PREPARE stmt_drop_fk_mitarbeiter_hat_abteilung_abteilung;

SET @fk_terminal_abteilung = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'terminal'
    AND COLUMN_NAME = 'abteilung_id'
    AND REFERENCED_TABLE_NAME = 'abteilung'
  LIMIT 1
);
SET @sql_drop_fk_terminal_abteilung = IF(
  @fk_terminal_abteilung IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE terminal DROP FOREIGN KEY ', @fk_terminal_abteilung)
);
PREPARE stmt_drop_fk_terminal_abteilung FROM @sql_drop_fk_terminal_abteilung;
EXECUTE stmt_drop_fk_terminal_abteilung;
DEALLOCATE PREPARE stmt_drop_fk_terminal_abteilung;

ALTER TABLE abteilung
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY parent_id BIGINT UNSIGNED NULL;

ALTER TABLE abteilung
  ADD CONSTRAINT fk_abteilung_parent
    FOREIGN KEY (parent_id) REFERENCES abteilung(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE mitarbeiter
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE krankzeitraum
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY angelegt_von_mitarbeiter_id BIGINT UNSIGNED NULL;

ALTER TABLE rolle
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE recht
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE rolle_hat_recht
  MODIFY rolle_id BIGINT UNSIGNED NOT NULL,
  MODIFY recht_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE maschine
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY abteilung_id BIGINT UNSIGNED NULL;

ALTER TABLE terminal
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY abteilung_id BIGINT UNSIGNED NULL;

ALTER TABLE auftrag
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE config
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE zeit_rundungsregel
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE pausenfenster
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE pausenentscheidung
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY erstellt_von_mitarbeiter_id BIGINT UNSIGNED NULL;

ALTER TABLE sonstiges_grund
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE betriebsferien
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY abteilung_id BIGINT UNSIGNED NULL;

ALTER TABLE feiertag
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE kurzarbeit_plan
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NULL,
  MODIFY angelegt_von_mitarbeiter_id BIGINT UNSIGNED NULL;

ALTER TABLE system_log
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NULL,
  MODIFY terminal_id BIGINT UNSIGNED NULL;

ALTER TABLE db_injektionsqueue
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY meta_mitarbeiter_id BIGINT UNSIGNED NULL,
  MODIFY meta_terminal_id BIGINT UNSIGNED NULL;

ALTER TABLE mitarbeiter_hat_rolle
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY rolle_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE mitarbeiter_hat_recht
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY recht_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE mitarbeiter_hat_rolle_scope
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY rolle_id BIGINT UNSIGNED NOT NULL,
  MODIFY scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE mitarbeiter_hat_abteilung
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY abteilung_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE mitarbeiter_genehmiger
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY genehmiger_mitarbeiter_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE zeitbuchung
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY terminal_id BIGINT UNSIGNED NULL;

ALTER TABLE tageswerte_mitarbeiter
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE monatswerte_mitarbeiter
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE stundenkonto_batch
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY erstellt_von_mitarbeiter_id BIGINT UNSIGNED NULL;

ALTER TABLE stundenkonto_korrektur
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY batch_id BIGINT UNSIGNED NULL,
  MODIFY erstellt_von_mitarbeiter_id BIGINT UNSIGNED NULL;

ALTER TABLE urlaubsantrag
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY entscheidungs_mitarbeiter_id BIGINT UNSIGNED NULL;

ALTER TABLE urlaub_kontingent_jahr
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE auftragszeit
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  MODIFY auftrag_id BIGINT UNSIGNED NULL,
  MODIFY maschine_id BIGINT UNSIGNED NULL,
  MODIFY terminal_id BIGINT UNSIGNED NULL;

ALTER TABLE abteilung
  ADD CONSTRAINT fk_abteilung_parent
    FOREIGN KEY (parent_id) REFERENCES abteilung(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE betriebsferien
  ADD CONSTRAINT fk_betriebsferien_abteilung
    FOREIGN KEY (abteilung_id) REFERENCES abteilung(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE maschine
  ADD CONSTRAINT fk_maschine_abteilung
    FOREIGN KEY (abteilung_id) REFERENCES abteilung(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE mitarbeiter_hat_abteilung
  ADD CONSTRAINT fk_mitarbeiter_hat_abteilung_abteilung
    FOREIGN KEY (abteilung_id) REFERENCES abteilung(id) ON UPDATE CASCADE;

ALTER TABLE terminal
  ADD CONSTRAINT fk_terminal_abteilung
    FOREIGN KEY (abteilung_id) REFERENCES abteilung(id) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
