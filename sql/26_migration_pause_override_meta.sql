-- Migration: Metadaten für Pause-Override in tageswerte_mitarbeiter
-- Datum: 2026-01-??

ALTER TABLE tageswerte_mitarbeiter
  ADD COLUMN pause_override_aktiv TINYINT(1) NOT NULL DEFAULT 0 AFTER pause_korr_minuten,
  ADD COLUMN pause_override_begruendung VARCHAR(255) NULL AFTER pause_override_aktiv,
  ADD COLUMN pause_override_gesetzt_von_mitarbeiter_id INT UNSIGNED NULL AFTER pause_override_begruendung,
  ADD COLUMN pause_override_gesetzt_am DATETIME NULL AFTER pause_override_gesetzt_von_mitarbeiter_id,
  ADD KEY idx_pause_override_gesetzt_von (pause_override_gesetzt_von_mitarbeiter_id),
  ADD CONSTRAINT fk_pause_override_gesetzt_von
    FOREIGN KEY (pause_override_gesetzt_von_mitarbeiter_id) REFERENCES mitarbeiter(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
