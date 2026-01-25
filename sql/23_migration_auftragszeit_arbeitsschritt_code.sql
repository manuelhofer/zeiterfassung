-- Migration 23: Auftragszeit um Arbeitsschritt-Code erweitern
-- Ziel: Grundlage fuer Auftragsmodul v1 (Scan: Auftrag + Arbeitsschritt + optional Maschine)
-- Hinweis: Diese Migration ist fuer bestehende Datenbanken gedacht.

ALTER TABLE `auftragszeit`
  ADD COLUMN `arbeitsschritt_code` VARCHAR(100) NULL AFTER `auftragscode`,
  ADD KEY `idx_auftragszeit_auftrag_arbeitsschritt` (`auftrag_id`, `arbeitsschritt_code`),
  ADD KEY `idx_auftragszeit_code_arbeitsschritt` (`auftragscode`, `arbeitsschritt_code`);
