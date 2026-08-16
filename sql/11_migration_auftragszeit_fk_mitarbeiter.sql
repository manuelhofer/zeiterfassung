-- Migration 11: Fremdschlüssel auftragszeit.mitarbeiter_id -> mitarbeiter.id (T-135)
--
-- Warum:
-- `zeitbuchung` hat den Fremdschlüssel seit Migration 09 (T-129). `auftragszeit`
-- führt dieselbe Spalte unter denselben Bedingungen - `NOT NULL`, gefüllt aus
-- Befehlen, die die Offline-Queue später gegen die Hauptdatenbank ausführt -,
-- hatte den Schutz aber nicht.
--
-- Heute ist dort keine Lücke: Offline entsteht eine Auftragszeit nur mit der ID
-- der angemeldeten Sitzung, und die Fortsetzung eines pausierten Auftrags
-- kopiert die ID einer vorhandenen Zeile. Ein Chip wird nirgends aufgelöst.
-- Das ändert sich mit dem zweiten Schritt nach T-125 (Anmeldung und Aufträge
-- im Offline-Betrieb) - dann wird auch hier eine Mitarbeiter-ID erst beim
-- Einspielen aus einem RFID-Code, und dann gilt Wort für Wort, was in
-- `09_migration_zeitbuchung_fk_mitarbeiter.sql` steht.
--
-- Der Fremdschlüssel jetzt zu setzen ist billig: Die Tabelle ist klein, und
-- eine Zusicherung, die vor der Lücke da ist, muss niemand nachrüsten, wenn es
-- eilig wird.
--
-- `ON DELETE RESTRICT` wie bei `zeitbuchung`: Eine gebuchte Arbeitszeit darf
-- ihren Mitarbeiter nicht verlieren.
--
-- Idempotent: Mehrfaches Ausführen ist unschädlich.

-- --------------------------------------------------------------------------
-- Schritt 1: Verwaiste Auftragszeiten suchen
-- --------------------------------------------------------------------------
-- Wie in Migration 09: Steht etwas im Weg, sagt die Migration das und lässt die
-- Tabelle in Ruhe, statt mit Fehler 1452 mittendrin abzubrechen.

SELECT a.`id`, a.`mitarbeiter_id`, a.`auftragscode`, a.`startzeit`, a.`status`
  FROM `auftragszeit` a
  LEFT JOIN `mitarbeiter` m ON m.`id` = a.`mitarbeiter_id`
 WHERE m.`id` IS NULL
 ORDER BY a.`startzeit`
 LIMIT 50;

SET @waisen := (
    SELECT COUNT(*)
      FROM `auftragszeit` a
      LEFT JOIN `mitarbeiter` m ON m.`id` = a.`mitarbeiter_id`
     WHERE m.`id` IS NULL
);

SELECT IF(@waisen = 0,
    'OK - keine verwaisten Auftragszeiten.',
    CONCAT('ACHTUNG - ', @waisen, ' Auftragszeit(en) ohne Mitarbeiter (Liste oben). ',
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
       AND TABLE_NAME      = 'auftragszeit'
       AND CONSTRAINT_NAME = 'fk_auftragszeit_mitarbeiter'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

-- Der Index auf `mitarbeiter_id` existiert bereits (`idx_auftragszeit_mitarbeiter`);
-- InnoDB benutzt ihn und legt keinen zweiten an.
SET @sql := IF(@fk_fehlt AND @waisen = 0,
    'ALTER TABLE `auftragszeit`
        ADD CONSTRAINT `fk_auftragszeit_mitarbeiter`
        FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
