-- Migration: 04 - Monatsreport Detail-Rechte hinzufügen
-- Datum: 2026-01-01
--
-- Zweck:
-- - Ergänzt die Rechteverwaltung um feinere Report-Rechte:
--   - REPORT_MONAT_VIEW_ALL: Monatsreport/PDF für beliebige Mitarbeiter anzeigen/erzeugen.
--   - REPORT_MONAT_EXPORT_ALL: Sammel-Export (ZIP) für Monat/Jahr (pro Mitarbeiter 1 PDF).
--
-- Hinweis:
-- - Skript ist idempotent (INSERT ... WHERE NOT EXISTS).
-- - Erwartet, dass Migration 03 (Rechteverwaltung) bereits ausgeführt wurde.

-- Einzel-View/PDF für beliebigen Mitarbeiter
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'REPORT_MONAT_VIEW_ALL', 'Monatsreport (alle) ansehen', 'Darf Monatsübersichten/PDFs für beliebige Mitarbeiter anzeigen/erzeugen.', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'REPORT_MONAT_VIEW_ALL');

-- Sammel-Export als ZIP (Monat/Jahr)
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'REPORT_MONAT_EXPORT_ALL', 'Monatsreport (alle) exportieren', 'Darf einen Sammel-Export (ZIP) für einen Monat erzeugen (pro Mitarbeiter 1 PDF).', 1
WHERE NOT EXISTS (SELECT 1 FROM `recht` WHERE `code` = 'REPORT_MONAT_EXPORT_ALL');
