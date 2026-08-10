-- Migration 07: Urlaubsübertrag festschreiben (B-080)
--
-- Warum:
-- Der Übertrag ins Jahr N wurde bisher bei jeder Anzeige aus dem Vorjahr neu
-- gerechnet – und zwar aus einem Vorjahr **ohne** eigenen Übertrag. Dadurch
-- zeigte die Maske für 2025 einen Rest von 25,00 Tagen, während die Maske für
-- 2026 daraus -5,00 übernahm: Der Übertrag aus 2024 fiel beim Jahreswechsel
-- weg. Vollständige Analyse in `docs/archiv/DEV_PROMPT_HISTORY.md`,
-- P-2026-08-10-24.
--
-- Die Lösung ist, den Übertrag einmal zu **berechnen und festzuhalten**, statt
-- ihn immer wieder herzuleiten. Danach steht in der Datenbank, welche Zahl
-- gilt; sie ist im Backend sichtbar und von Hand korrigierbar.
--
-- Warum eine eigene Spalte und nicht einfach `uebertrag_tage`:
-- `uebertrag_tage` ist `NOT NULL DEFAULT 0.00`. Damit lässt sich „noch nicht
-- festgeschrieben" nicht von „festgeschrieben auf 0,00 Tage" unterscheiden –
-- und beides kommt vor. Der Zeitstempel beantwortet die Frage eindeutig und
-- sagt nebenbei, wann es passiert ist.
--
-- Idempotent: Mehrfaches Ausführen ist unschädlich.

-- --------------------------------------------------------------------------
-- Spalte anlegen, falls sie fehlt
-- --------------------------------------------------------------------------
SET @spalte_fehlt := (
    SELECT COUNT(*) = 0
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'urlaub_kontingent_jahr'
      AND COLUMN_NAME  = 'uebertrag_festgeschrieben_am'
);

SET @sql := IF(@spalte_fehlt,
    'ALTER TABLE `urlaub_kontingent_jahr`
        ADD COLUMN `uebertrag_festgeschrieben_am` DATETIME NULL DEFAULT NULL
        COMMENT ''Wann der Uebertrag festgeschrieben wurde; NULL = noch nicht''
        AFTER `uebertrag_tage`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- Bestehende, von Hand gepflegte Überträge als festgeschrieben markieren
-- --------------------------------------------------------------------------
-- Wer bisher einen Übertrag ungleich 0,00 eingetragen hat, hat das bewusst
-- getan. Dieser Wert soll weiter gelten und nicht beim nächsten Seitenaufruf
-- überschrieben werden.
--
-- Einträge mit 0,00 bleiben bewusst offen: Bei ihnen ist nicht erkennbar, ob
-- jemand „null Tage" gemeint hat oder das Feld einfach nie angefasst wurde.
-- Sie werden beim nächsten Aufruf aus der Kette berechnet und dann
-- festgeschrieben.

UPDATE `urlaub_kontingent_jahr`
   SET `uebertrag_festgeschrieben_am` = NOW()
 WHERE `uebertrag_festgeschrieben_am` IS NULL
   AND `uebertrag_tage` <> 0.00;
