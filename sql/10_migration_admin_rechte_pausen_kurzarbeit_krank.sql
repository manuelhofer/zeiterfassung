-- Migration: 10 - Backend Admin-Rechte (Pausenregeln / Krankzeitraum / Kurzarbeit)
-- Datum: 2026-01-03
--
-- Zweck:
-- - Ergänzt fehlende Rechte-Codes für neue/erweiterte Module:
--   - Pausenregeln (betriebliche Pausenfenster)
--   - Krankzeitraum (LFZ/KK Zeiträume)
--   - Kurzarbeit (Plan/Zeiträume)
-- - Optional: Default-Zuordnung an Chef + Personalbüro (Legacy-Verhalten beibehalten).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (INSERT nur, wenn Code noch nicht existiert).
-- - Erwartet, dass `recht` und `rolle_hat_recht` (Migration 03 / Initial-Schema) existieren.

-- Pausenregeln verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'PAUSENREGELN_VERWALTEN', 'Pausenregeln verwalten', 'Darf betriebliche Pausenfenster (Zwangspausen) anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'PAUSENREGELN_VERWALTEN');

-- Krankzeitraum (LFZ/KK) verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'KRANKZEITRAUM_VERWALTEN', 'Krankzeitraum verwalten', 'Darf Krank-Zeiträume pro Mitarbeiter pflegen (Lohnfortzahlung/Krankenkasse).', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'KRANKZEITRAUM_VERWALTEN');

-- Kurzarbeit verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'KURZARBEIT_VERWALTEN', 'Kurzarbeit verwalten', 'Darf Kurzarbeit planen und Zeiträume pflegen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'KURZARBEIT_VERWALTEN');

-- --------------------------------------------------------
-- Seed: Standard-Zuordnung (falls Rollen existieren)
-- --------------------------------------------------------

-- Chef + Personalbüro bekommen die neuen Admin-Rechte (Legacy-Verhalten beibehalten)
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.id, re.id
FROM `rolle` r
JOIN `recht` re ON re.code IN (
  'PAUSENREGELN_VERWALTEN',
  'KRANKZEITRAUM_VERWALTEN',
  'KURZARBEIT_VERWALTEN'
)
WHERE r.name IN ('Chef', 'Personalbüro', 'Personalbuero')
AND NOT EXISTS (
  SELECT 1 FROM `rolle_hat_recht` x
  WHERE x.rolle_id = r.id AND x.recht_id = re.id
);
