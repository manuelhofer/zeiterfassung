-- Migration 10: Herkunftsvermerk in der Queue (T-128)
--
-- Warum:
-- Beim Wiederanlauf läuft eine Buchung über **zwei** Datenbanken: Der Befehl
-- wird auf der Hauptdatenbank ausgeführt und committet, danach wird der
-- Eintrag in der Queue des Terminals auf `verarbeitet` gesetzt. Zwei
-- Datenbanken, zwei Abschlüsse - fällt der Strom genau dazwischen aus, ist die
-- Buchung drin, der Eintrag steht aber weiter auf `offen`. Beim nächsten Start
-- wird er ein zweites Mal eingespielt, und der Mitarbeiter hat zweimal
-- gestempelt.
--
-- Ein gemeinsamer Abschluss über beide Datenbanken ginge nur mit verteilten
-- Transaktionen (XA). Der Umweg dahin ist kürzer: Der Vermerk "Eintrag N von
-- Terminal X ist eingespielt" wird in **derselben** Transaktion geschrieben
-- wie die Buchung selbst, und zwar in die `db_injektionsqueue` der
-- Hauptdatenbank. Damit gibt es genau einen Abschluss, der zählt. Der Eintrag
-- auf dem Terminal ist danach nur noch eine Notiz: Geht sie verloren, findet
-- der nächste Lauf den Vermerk, holt die Notiz nach und spielt **nicht**
-- erneut ein.
--
-- Diese Spalte ist der Vermerk. `NULL` heißt "hier entstanden" - also ein
-- Eintrag, den das Backend selbst angelegt hat, und kein eingespielter.
--
-- Warum kein UNIQUE über (meta_terminal_id, meta_quell_id): Wird die lokale
-- Ausweichdatenbank eines Terminals neu angelegt, fangen die IDs wieder bei 1
-- an, während die Terminal-ID dieselbe bleibt. Ein UNIQUE würde dann beim
-- Einspielen scheitern und die Buchung mit in den Abgrund ziehen. Deshalb ein
-- gewöhnlicher Index, und die Suche vergleicht zusätzlich `erstellt_am`.
--
-- Idempotent: Mehrfaches Ausführen ist unschädlich.

-- --------------------------------------------------------------------------
-- Spalte anlegen, falls sie fehlt
-- --------------------------------------------------------------------------
SET @spalte_fehlt := (
    SELECT COUNT(*) = 0
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'db_injektionsqueue'
      AND COLUMN_NAME  = 'meta_quell_id'
);

SET @sql := IF(@spalte_fehlt,
    'ALTER TABLE `db_injektionsqueue`
        ADD COLUMN `meta_quell_id` BIGINT UNSIGNED DEFAULT NULL
        COMMENT ''ID dieses Eintrags in der Queue des Terminals; NULL = hier entstanden''
        AFTER `meta_aktion`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- Index für die Suche nach dem Vermerk
-- --------------------------------------------------------------------------
-- Gesucht wird immer nach allen drei Feldern zusammen: Terminal, lokale ID und
-- Zeitpunkt des Eintrags. Der Zeitpunkt ist dabei die Absicherung gegen eine
-- neu angelegte Ausweichdatenbank, deren IDs wieder bei 1 beginnen.
SET @index_fehlt := (
    SELECT COUNT(*) = 0
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'db_injektionsqueue'
      AND INDEX_NAME   = 'idx_db_injektionsqueue_herkunft'
);

SET @sql := IF(@index_fehlt,
    'ALTER TABLE `db_injektionsqueue`
        ADD KEY `idx_db_injektionsqueue_herkunft` (`meta_terminal_id`, `meta_quell_id`, `erstellt_am`)',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
