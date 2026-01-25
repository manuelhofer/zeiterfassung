-- Migration: 06 - Backend Admin-Rechte (Konfiguration/Rundung/Urlaub-Kontingent)
-- Datum: 2026-01-01
--
-- Zweck:
-- - Ergänzt fehlende Rechte-Codes für weitere Backend-Admin-Module.
-- - Rechte werden in `recht` gepflegt und optional Rollen zugewiesen (rolle_hat_recht).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (INSERT nur, wenn Code noch nicht existiert).
-- - Erwartet, dass `recht` und `rolle_hat_recht` (Migration 03 / Initial-Schema) existieren.

-- Zeit-Rundungsregeln verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'ZEIT_RUNDUNGSREGELN_VERWALTEN', 'Zeit-Rundungsregeln verwalten', 'Darf Zeit-Rundungsregeln anlegen/bearbeiten/aktivieren.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'ZEIT_RUNDUNGSREGELN_VERWALTEN');

-- Konfiguration (config) verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'KONFIGURATION_VERWALTEN', 'Konfiguration verwalten', 'Darf Konfigurationseinträge (Key/Value) anlegen/bearbeiten.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'KONFIGURATION_VERWALTEN');

-- Urlaub-Kontingent/Override verwalten
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'URLAUB_KONTINGENT_VERWALTEN', 'Urlaub-Kontingent verwalten', 'Darf Urlaubskontingente/Übertrag/Korrekturen pro Mitarbeiter und Jahr pflegen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'URLAUB_KONTINGENT_VERWALTEN');

-- --------------------------------------------------------
-- Seed: Standard-Zuordnung (falls Rollen existieren)
-- --------------------------------------------------------

-- Chef + Personalbüro bekommen die neuen Admin-Rechte (Legacy-Verhalten beibehalten)
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.id, re.id
FROM `rolle` r
JOIN `recht` re ON re.code IN (
  'ZEIT_RUNDUNGSREGELN_VERWALTEN',
  'KONFIGURATION_VERWALTEN',
  'URLAUB_KONTINGENT_VERWALTEN'
)
WHERE r.name IN ('Chef', 'Personalbüro', 'Personalbuero')
AND NOT EXISTS (
  SELECT 1 FROM `rolle_hat_recht` x
  WHERE x.rolle_id = r.id AND x.recht_id = re.id
);
