-- Migration: 22 - Stundenkonto Stealth-Buchungen
-- Datum: 2026-01-25
--
-- Zweck:
-- - Ergaenzt `stundenkonto_batch` und `stundenkonto_korrektur` um ein Stealth-Flag,
--   damit Korrekturen/Verteilungen im Backend bei Bedarf nicht in der UI gelistet werden.

ALTER TABLE `stundenkonto_batch`
  ADD COLUMN `stealth` TINYINT(1) NOT NULL DEFAULT 0 AFTER `erstellt_von_mitarbeiter_id`;

ALTER TABLE `stundenkonto_korrektur`
  ADD COLUMN `stealth` TINYINT(1) NOT NULL DEFAULT 0 AFTER `erstellt_von_mitarbeiter_id`;
