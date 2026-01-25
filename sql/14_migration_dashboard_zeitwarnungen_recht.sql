-- Migration: 14 - Dashboard Zeitwarnungen Recht
-- Datum: 2026-01-07
--
-- Zweck:
-- - Führt ein eigenes Recht ein, das steuert, wer auf dem Dashboard
--   den roten Warnblock "unvollständige Kommen/Gehen-Buchungen" sieht.
--
-- Hinweis:
-- - Idempotent (INSERT nur, wenn Code noch nicht existiert).
-- - Erwartet, dass `recht` und `rolle_hat_recht` existieren (Migration 03 / Initial-Schema).

-- Neues Recht anlegen
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT
  'DASHBOARD_ZEITWARNUNGEN_SEHEN',
  'Dashboard: Zeitwarnungen sehen',
  'Darf den Dashboard-Warnblock für unplausible/unvollständige Kommen/Gehen-Stempel sehen.',
  1
WHERE NOT EXISTS (
  SELECT 1 FROM `recht` WHERE `code` = 'DASHBOARD_ZEITWARNUNGEN_SEHEN'
);

-- --------------------------------------------------------
-- Seed: Standard-Zuordnung (falls Rollen existieren)
-- --------------------------------------------------------

-- Chef + Personalbüro + Vorarbeiter bekommen dieses Recht (Legacy-Verhalten beibehalten)
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.id, re.id
FROM `rolle` r
JOIN `recht` re ON re.code = 'DASHBOARD_ZEITWARNUNGEN_SEHEN'
WHERE r.name IN ('Chef', 'Personalbüro', 'Personalbuero', 'Vorarbeiter')
AND NOT EXISTS (
  SELECT 1 FROM `rolle_hat_recht` x
  WHERE x.rolle_id = r.id AND x.recht_id = re.id
);
