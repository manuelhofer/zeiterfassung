-- Migration 09: Fremdschlüssel zeitbuchung.mitarbeiter_id -> mitarbeiter.id (T-129)
--
-- Warum:
-- Der Offline-Befehl, den das Terminal in `db_injektionsqueue` legt, kennt den
-- Mitarbeiter nicht - er löst ihn erst beim Einspielen über den RFID-Code auf:
--
--   INSERT INTO zeitbuchung (mitarbeiter_id, ...) VALUES (
--     (SELECT id FROM mitarbeiter WHERE rfid_code='...' AND aktiv=1 LIMIT 1), ...)
--
-- Gehört der Chip niemandem, liefert der Unterausdruck `NULL`. Was dann
-- passiert, entschied bisher der Server, nicht die Anwendung. Nachgemessen auf
-- MariaDB 11.8 (P-2026-08-16-24):
--
--   * einzeiliges INSERT ... VALUES  -> Fehler 1048, in **jedem** `sql_mode`
--   * mehrzeiliges INSERT ... VALUES -> ohne strikten Modus: `mitarbeiter_id = 0`
--   * INSERT ... SELECT              -> ohne strikten Modus: `mitarbeiter_id = 0`
--
-- Heute schreibt die Anwendung die erste Form, also hält sie. Aber die Form des
-- Befehls ist eine Zeile Code, und die beiden anderen sind genau das, wozu man
-- ihn umbaut, wenn man ihn "aufräumt". Dann entsteht auf einem Server ohne
-- strikten `sql_mode` eine Zeitbuchung auf einen Mitarbeiter, den es nicht
-- gibt - der Queue-Eintrag gilt als verarbeitet, und niemand erfährt davon.
--
-- Der Fremdschlüssel nimmt diese Entscheidung vom Server zurück: `0` steht
-- nicht in `mitarbeiter`, also scheitert der INSERT (Fehler 1452), der
-- Queue-Eintrag geht auf `fehler` und erscheint in der Queue-Verwaltung des
-- Backends. Unabhängig von `sql_mode` und von der Form des Befehls.
--
-- `ON DELETE RESTRICT`: Eine Zeitbuchung darf ihren Mitarbeiter nicht
-- verlieren. Die Anwendung löscht ohnehin keinen Mitarbeiter (sie setzt
-- `aktiv = 0`); von Hand in phpMyAdmin geht es ab jetzt auch nicht mehr,
-- solange Buchungen daran hängen. Genau das ist gewollt.
--
-- Idempotent: Mehrfaches Ausführen ist unschädlich.

-- --------------------------------------------------------------------------
-- Schritt 1: Verwaiste Zeitbuchungen suchen
-- --------------------------------------------------------------------------
-- Ein Fremdschlüssel lässt sich nicht auf Daten legen, die ihn schon verletzen.
-- Statt mit Fehler 1452 abzubrechen, sagt die Migration, was im Weg steht, und
-- lässt die Tabelle in Ruhe. Nach dem Aufräumen ein zweites Mal ausführen.

SELECT z.`id`, z.`mitarbeiter_id`, z.`typ`, z.`zeitstempel`, z.`quelle`
  FROM `zeitbuchung` z
  LEFT JOIN `mitarbeiter` m ON m.`id` = z.`mitarbeiter_id`
 WHERE m.`id` IS NULL
 ORDER BY z.`zeitstempel`
 LIMIT 50;

SET @waisen := (
    SELECT COUNT(*)
      FROM `zeitbuchung` z
      LEFT JOIN `mitarbeiter` m ON m.`id` = z.`mitarbeiter_id`
     WHERE m.`id` IS NULL
);

SELECT IF(@waisen = 0,
    'OK - keine verwaisten Zeitbuchungen.',
    CONCAT('ACHTUNG - ', @waisen, ' Zeitbuchung(en) ohne Mitarbeiter (Liste oben). ',
           'Der Fremdschluessel wird NICHT gesetzt. Zeilen zuordnen oder entfernen, ',
           'dann diese Migration erneut ausfuehren.')
) AS `befund`;

-- --------------------------------------------------------------------------
-- Schritt 2: Fremdschlüssel anlegen, falls er fehlt und nichts im Weg steht
-- --------------------------------------------------------------------------
SET @fk_fehlt := (
    SELECT COUNT(*) = 0
      FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'zeitbuchung'
       AND CONSTRAINT_NAME = 'fk_zeitbuchung_mitarbeiter'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

-- Der Index auf `mitarbeiter_id` existiert bereits (`idx_zeitbuchung_mitarbeiter`);
-- InnoDB benutzt ihn und legt keinen zweiten an.
SET @sql := IF(@fk_fehlt AND @waisen = 0,
    'ALTER TABLE `zeitbuchung`
        ADD CONSTRAINT `fk_zeitbuchung_mitarbeiter`
        FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
