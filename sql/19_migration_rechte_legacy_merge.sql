-- Migration: 19 - Legacy-Rechte mergen (Duplikate entfernen)
-- Datum: 2026-01-11
--
-- Zweck:
-- - Führt Legacy-Rechte-Codes auf kanonische Rechte zusammen, damit die Rollen-UI
--   keine doppelten Rechte mehr zeigt.
-- - Migration betrifft:
--   * rolle_hat_recht (Rollenrechte)
--   * mitarbeiter_hat_recht (Overrides Allow/Deny)
-- - Legacy-Rechte werden am Ende deaktiviert (recht.aktiv = 0).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (mehrfach ausführbar).

-- 0) Kanonische Rechte sicherstellen (falls DB-Stand abweicht)
INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT
  'ZEITBUCHUNG_EDIT_SELF',
  'Zeitbuchungen bearbeiten (eigene)',
  'Erlaubt das Korrigieren von eigenen Zeitbuchungen (add/update/delete) im Backend inkl. Audit-Log.',
  1
WHERE NOT EXISTS (SELECT 1 FROM recht WHERE code = 'ZEITBUCHUNG_EDIT_SELF');

INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT
  'ZEITBUCHUNG_EDIT_ALL',
  'Zeitbuchungen bearbeiten (alle Mitarbeiter)',
  'Erlaubt das Korrigieren von Zeitbuchungen aller Mitarbeiter (add/update/delete) im Backend inkl. Audit-Log.',
  1
WHERE NOT EXISTS (SELECT 1 FROM recht WHERE code = 'ZEITBUCHUNG_EDIT_ALL');

INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT
  'REPORT_MONAT_VIEW_ALL',
  'Monatsreport (alle) ansehen',
  'Darf Monatsübersichten/PDFs für beliebige Mitarbeiter anzeigen/erzeugen.',
  1
WHERE NOT EXISTS (SELECT 1 FROM recht WHERE code = 'REPORT_MONAT_VIEW_ALL');

-- 1) Rollenrechte: Legacy -> kanonisch (INSERT nur wenn noch nicht vorhanden)

-- ZEIT_EDIT_SELF -> ZEITBUCHUNG_EDIT_SELF
INSERT INTO rolle_hat_recht (rolle_id, recht_id)
SELECT DISTINCT rhr.rolle_id, r_new.id
FROM rolle_hat_recht rhr
JOIN recht r_old ON r_old.id = rhr.recht_id AND TRIM(r_old.code) = 'ZEIT_EDIT_SELF'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_SELF'
LEFT JOIN rolle_hat_recht rhr2 ON rhr2.rolle_id = rhr.rolle_id AND rhr2.recht_id = r_new.id
WHERE rhr2.rolle_id IS NULL;

-- ZEITBUCHUNG_EDITIEREN_SELF -> ZEITBUCHUNG_EDIT_SELF
INSERT INTO rolle_hat_recht (rolle_id, recht_id)
SELECT DISTINCT rhr.rolle_id, r_new.id
FROM rolle_hat_recht rhr
JOIN recht r_old ON r_old.id = rhr.recht_id AND TRIM(r_old.code) = 'ZEITBUCHUNG_EDITIEREN_SELF'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_SELF'
LEFT JOIN rolle_hat_recht rhr2 ON rhr2.rolle_id = rhr.rolle_id AND rhr2.recht_id = r_new.id
WHERE rhr2.rolle_id IS NULL;

-- ZEIT_EDIT_ALLE -> ZEITBUCHUNG_EDIT_ALL
INSERT INTO rolle_hat_recht (rolle_id, recht_id)
SELECT DISTINCT rhr.rolle_id, r_new.id
FROM rolle_hat_recht rhr
JOIN recht r_old ON r_old.id = rhr.recht_id AND TRIM(r_old.code) = 'ZEIT_EDIT_ALLE'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_ALL'
LEFT JOIN rolle_hat_recht rhr2 ON rhr2.rolle_id = rhr.rolle_id AND rhr2.recht_id = r_new.id
WHERE rhr2.rolle_id IS NULL;

-- ZEITBUCHUNG_EDITIEREN_ALLE -> ZEITBUCHUNG_EDIT_ALL
INSERT INTO rolle_hat_recht (rolle_id, recht_id)
SELECT DISTINCT rhr.rolle_id, r_new.id
FROM rolle_hat_recht rhr
JOIN recht r_old ON r_old.id = rhr.recht_id AND TRIM(r_old.code) = 'ZEITBUCHUNG_EDITIEREN_ALLE'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_ALL'
LEFT JOIN rolle_hat_recht rhr2 ON rhr2.rolle_id = rhr.rolle_id AND rhr2.recht_id = r_new.id
WHERE rhr2.rolle_id IS NULL;

-- REPORT_MONAT_ALLE -> REPORT_MONAT_VIEW_ALL
INSERT INTO rolle_hat_recht (rolle_id, recht_id)
SELECT DISTINCT rhr.rolle_id, r_new.id
FROM rolle_hat_recht rhr
JOIN recht r_old ON r_old.id = rhr.recht_id AND TRIM(r_old.code) = 'REPORT_MONAT_ALLE'
JOIN recht r_new ON TRIM(r_new.code) = 'REPORT_MONAT_VIEW_ALL'
LEFT JOIN rolle_hat_recht rhr2 ON rhr2.rolle_id = rhr.rolle_id AND rhr2.recht_id = r_new.id
WHERE rhr2.rolle_id IS NULL;

-- 2) Mitarbeiter-Overrides: Legacy -> kanonisch (erlaubt-Wert übernehmen, wenn kanonisch noch nicht gesetzt)

-- ZEIT_EDIT_SELF -> ZEITBUCHUNG_EDIT_SELF
INSERT INTO mitarbeiter_hat_recht (mitarbeiter_id, recht_id, erlaubt, notiz)
SELECT mhr.mitarbeiter_id, r_new.id, mhr.erlaubt, CONCAT('migrated from ', TRIM(r_old.code))
FROM mitarbeiter_hat_recht mhr
JOIN recht r_old ON r_old.id = mhr.recht_id AND TRIM(r_old.code) = 'ZEIT_EDIT_SELF'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_SELF'
LEFT JOIN mitarbeiter_hat_recht mhr2 ON mhr2.mitarbeiter_id = mhr.mitarbeiter_id AND mhr2.recht_id = r_new.id
WHERE mhr2.mitarbeiter_id IS NULL;

-- ZEITBUCHUNG_EDITIEREN_SELF -> ZEITBUCHUNG_EDIT_SELF
INSERT INTO mitarbeiter_hat_recht (mitarbeiter_id, recht_id, erlaubt, notiz)
SELECT mhr.mitarbeiter_id, r_new.id, mhr.erlaubt, CONCAT('migrated from ', TRIM(r_old.code))
FROM mitarbeiter_hat_recht mhr
JOIN recht r_old ON r_old.id = mhr.recht_id AND TRIM(r_old.code) = 'ZEITBUCHUNG_EDITIEREN_SELF'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_SELF'
LEFT JOIN mitarbeiter_hat_recht mhr2 ON mhr2.mitarbeiter_id = mhr.mitarbeiter_id AND mhr2.recht_id = r_new.id
WHERE mhr2.mitarbeiter_id IS NULL;

-- ZEIT_EDIT_ALLE -> ZEITBUCHUNG_EDIT_ALL
INSERT INTO mitarbeiter_hat_recht (mitarbeiter_id, recht_id, erlaubt, notiz)
SELECT mhr.mitarbeiter_id, r_new.id, mhr.erlaubt, CONCAT('migrated from ', TRIM(r_old.code))
FROM mitarbeiter_hat_recht mhr
JOIN recht r_old ON r_old.id = mhr.recht_id AND TRIM(r_old.code) = 'ZEIT_EDIT_ALLE'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_ALL'
LEFT JOIN mitarbeiter_hat_recht mhr2 ON mhr2.mitarbeiter_id = mhr.mitarbeiter_id AND mhr2.recht_id = r_new.id
WHERE mhr2.mitarbeiter_id IS NULL;

-- ZEITBUCHUNG_EDITIEREN_ALLE -> ZEITBUCHUNG_EDIT_ALL
INSERT INTO mitarbeiter_hat_recht (mitarbeiter_id, recht_id, erlaubt, notiz)
SELECT mhr.mitarbeiter_id, r_new.id, mhr.erlaubt, CONCAT('migrated from ', TRIM(r_old.code))
FROM mitarbeiter_hat_recht mhr
JOIN recht r_old ON r_old.id = mhr.recht_id AND TRIM(r_old.code) = 'ZEITBUCHUNG_EDITIEREN_ALLE'
JOIN recht r_new ON TRIM(r_new.code) = 'ZEITBUCHUNG_EDIT_ALL'
LEFT JOIN mitarbeiter_hat_recht mhr2 ON mhr2.mitarbeiter_id = mhr.mitarbeiter_id AND mhr2.recht_id = r_new.id
WHERE mhr2.mitarbeiter_id IS NULL;

-- REPORT_MONAT_ALLE -> REPORT_MONAT_VIEW_ALL
INSERT INTO mitarbeiter_hat_recht (mitarbeiter_id, recht_id, erlaubt, notiz)
SELECT mhr.mitarbeiter_id, r_new.id, mhr.erlaubt, CONCAT('migrated from ', TRIM(r_old.code))
FROM mitarbeiter_hat_recht mhr
JOIN recht r_old ON r_old.id = mhr.recht_id AND TRIM(r_old.code) = 'REPORT_MONAT_ALLE'
JOIN recht r_new ON TRIM(r_new.code) = 'REPORT_MONAT_VIEW_ALL'
LEFT JOIN mitarbeiter_hat_recht mhr2 ON mhr2.mitarbeiter_id = mhr.mitarbeiter_id AND mhr2.recht_id = r_new.id
WHERE mhr2.mitarbeiter_id IS NULL;

-- 3) Legacy-Zuweisungen entfernen
DELETE rhr
FROM rolle_hat_recht rhr
JOIN recht r_old ON r_old.id = rhr.recht_id
WHERE TRIM(r_old.code) IN (
  'ZEIT_EDIT_SELF',
  'ZEIT_EDIT_ALLE',
  'REPORT_MONAT_ALLE',
  'ZEITBUCHUNG_EDITIEREN_SELF',
  'ZEITBUCHUNG_EDITIEREN_ALLE'
);

DELETE mhr
FROM mitarbeiter_hat_recht mhr
JOIN recht r_old ON r_old.id = mhr.recht_id
WHERE TRIM(r_old.code) IN (
  'ZEIT_EDIT_SELF',
  'ZEIT_EDIT_ALLE',
  'REPORT_MONAT_ALLE',
  'ZEITBUCHUNG_EDITIEREN_SELF',
  'ZEITBUCHUNG_EDITIEREN_ALLE'
);

-- 4) Legacy-Rechte deaktivieren (Soft-Delete)
UPDATE recht
SET aktiv = 0
WHERE TRIM(code) IN (
  'ZEIT_EDIT_SELF',
  'ZEIT_EDIT_ALLE',
  'REPORT_MONAT_ALLE',
  'ZEITBUCHUNG_EDITIEREN_SELF',
  'ZEITBUCHUNG_EDITIEREN_ALLE'
);
