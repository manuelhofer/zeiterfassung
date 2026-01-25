-- Migration: 05 - Rechte für Zeitbuchungs-Korrektur (Self/All)
-- Datum: 2026-01-01
--
-- Zweck:
-- - Ergänzt die Rechte-Codes für das Bearbeiten/Korrigieren von Zeitbuchungen.
-- - Rechte werden Rollen zugewiesen (rolle_hat_recht).
--
-- Hinweis:
-- - Dieses Skript ist idempotent (INSERT nur, wenn Code noch nicht existiert).

INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT
  'ZEITBUCHUNG_EDIT_SELF',
  'Zeitbuchungen bearbeiten (eigene)',
  'Erlaubt das Korrigieren von eigenen Zeitbuchungen (add/update/delete) im Backend inkl. Audit-Log.',
  1
WHERE NOT EXISTS (
  SELECT 1 FROM recht WHERE code = 'ZEITBUCHUNG_EDIT_SELF'
);

INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT
  'ZEITBUCHUNG_EDIT_ALL',
  'Zeitbuchungen bearbeiten (alle Mitarbeiter)',
  'Erlaubt das Korrigieren von Zeitbuchungen aller Mitarbeiter (add/update/delete) im Backend inkl. Audit-Log.',
  1
WHERE NOT EXISTS (
  SELECT 1 FROM recht WHERE code = 'ZEITBUCHUNG_EDIT_ALL'
);
