-- Migration: Alle ID-Spalten auf BIGINT UNSIGNED umstellen
-- Datum: 2026-01-??

SET FOREIGN_KEY_CHECKS = 0;

CREATE TEMPORARY TABLE tmp_fk_def (
  table_name VARCHAR(64) NOT NULL,
  constraint_name VARCHAR(64) NOT NULL,
  columns_list TEXT NOT NULL,
  referenced_table_name VARCHAR(64) NOT NULL,
  referenced_columns_list TEXT NOT NULL,
  update_rule VARCHAR(32) NOT NULL,
  delete_rule VARCHAR(32) NOT NULL
);

INSERT INTO tmp_fk_def (
  table_name,
  constraint_name,
  columns_list,
  referenced_table_name,
  referenced_columns_list,
  update_rule,
  delete_rule
)
SELECT
  kcu.TABLE_NAME,
  kcu.CONSTRAINT_NAME,
  GROUP_CONCAT(CONCAT('`', kcu.COLUMN_NAME, '`') ORDER BY kcu.ORDINAL_POSITION SEPARATOR ', '),
  kcu.REFERENCED_TABLE_NAME,
  GROUP_CONCAT(CONCAT('`', kcu.REFERENCED_COLUMN_NAME, '`') ORDER BY kcu.ORDINAL_POSITION SEPARATOR ', '),
  rc.UPDATE_RULE,
  rc.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
  AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
GROUP BY
  kcu.TABLE_NAME,
  kcu.CONSTRAINT_NAME,
  kcu.REFERENCED_TABLE_NAME,
  rc.UPDATE_RULE,
  rc.DELETE_RULE;

DELIMITER $$
CREATE PROCEDURE drop_all_foreign_keys()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE fk_table VARCHAR(64);
  DECLARE fk_name VARCHAR(64);
  DECLARE fk_cursor CURSOR FOR
    SELECT table_name, constraint_name FROM tmp_fk_def;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN fk_cursor;
  fk_loop: LOOP
    FETCH fk_cursor INTO fk_table, fk_name;
    IF done = 1 THEN
      LEAVE fk_loop;
    END IF;
    SET @sql_drop_fk = CONCAT('ALTER TABLE `', fk_table, '` DROP FOREIGN KEY `', fk_name, '`');
    PREPARE stmt_drop_fk FROM @sql_drop_fk;
    EXECUTE stmt_drop_fk;
    DEALLOCATE PREPARE stmt_drop_fk;
  END LOOP;
  CLOSE fk_cursor;
END$$
DELIMITER ;

CALL drop_all_foreign_keys();
DROP PROCEDURE drop_all_foreign_keys;

ALTER TABLE abteilung
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY parent_id BIGINT UNSIGNED NULL;

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

DELIMITER $$
CREATE PROCEDURE restore_all_foreign_keys()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE fk_table VARCHAR(64);
  DECLARE fk_name VARCHAR(64);
  DECLARE fk_columns TEXT;
  DECLARE fk_ref_table VARCHAR(64);
  DECLARE fk_ref_columns TEXT;
  DECLARE fk_update_rule VARCHAR(32);
  DECLARE fk_delete_rule VARCHAR(32);
  DECLARE fk_cursor CURSOR FOR
    SELECT
      table_name,
      constraint_name,
      columns_list,
      referenced_table_name,
      referenced_columns_list,
      update_rule,
      delete_rule
    FROM tmp_fk_def;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN fk_cursor;
  fk_loop: LOOP
    FETCH fk_cursor INTO fk_table, fk_name, fk_columns, fk_ref_table, fk_ref_columns, fk_update_rule, fk_delete_rule;
    IF done = 1 THEN
      LEAVE fk_loop;
    END IF;
    SET @sql_add_fk = CONCAT(
      'ALTER TABLE `', fk_table, '` ADD CONSTRAINT `', fk_name, '` FOREIGN KEY (',
      fk_columns, ') REFERENCES `', fk_ref_table, '` (', fk_ref_columns, ') ',
      'ON UPDATE ', fk_update_rule, ' ON DELETE ', fk_delete_rule
    );
    PREPARE stmt_add_fk FROM @sql_add_fk;
    EXECUTE stmt_add_fk;
    DEALLOCATE PREPARE stmt_add_fk;
  END LOOP;
  CLOSE fk_cursor;
END$$
DELIMITER ;

CALL restore_all_foreign_keys();
DROP PROCEDURE restore_all_foreign_keys;

DROP TEMPORARY TABLE tmp_fk_def;

SET FOREIGN_KEY_CHECKS = 1;
