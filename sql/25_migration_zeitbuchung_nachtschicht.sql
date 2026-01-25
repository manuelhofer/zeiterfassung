-- Migration: Nachtschicht-Flag in zeitbuchung
-- Datum: 2026-01-??

ALTER TABLE zeitbuchung
  ADD COLUMN nachtshift TINYINT(1) NOT NULL DEFAULT 0 AFTER kommentar;
