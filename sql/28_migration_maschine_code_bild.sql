-- QR-Code-Bildpfad fuer Maschinen
ALTER TABLE `maschine`
  ADD COLUMN `code_bild_pfad` varchar(255) DEFAULT NULL AFTER `beschreibung`;
