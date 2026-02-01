-- QR-Code-Bildpfad fuer Maschinen (idempotent)
ALTER TABLE `maschine`
  ADD COLUMN IF NOT EXISTS `code_bild_pfad` varchar(255) DEFAULT NULL AFTER `beschreibung`;
