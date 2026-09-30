-- Die vorhandene Terminal-DB-Anmeldung ist zugleich der Wartungskanal.
-- USER() ist der angemeldete Client, auch in SQL SECURITY DEFINER Views.
CREATE TABLE IF NOT EXISTS wartung_system (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    oeffentlicher_schluessel VARCHAR(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wartung_geraet (
    terminal_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    db_benutzer VARCHAR(80) NOT NULL UNIQUE,
    gesehen DATETIME NULL,
    version VARCHAR(40) NOT NULL DEFAULT '',
    meldung VARCHAR(190) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wartung_befehl (
    id CHAR(32) NOT NULL PRIMARY KEY,
    db_benutzer VARCHAR(80) NOT NULL,
    anfrage LONGTEXT NOT NULL,
    signatur VARCHAR(600) NOT NULL,
    zustand ENUM('wartend','laeuft','erledigt') NOT NULL DEFAULT 'wartend',
    antwort LONGTEXT NULL,
    erstellt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY wartung_befehl_geraet (db_benutzer, zustand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wartung_dateiteil (
    db_benutzer VARCHAR(80) NOT NULL,
    auftrag CHAR(24) NOT NULL,
    richtung ENUM('hin','zurueck') NOT NULL,
    datei VARCHAR(100) NOT NULL,
    nummer INT UNSIGNED NOT NULL,
    inhalt MEDIUMBLOB NOT NULL,
    PRIMARY KEY (db_benutzer, auftrag, richtung, datei, nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW wartung_mein_geraet AS
SELECT * FROM wartung_geraet WHERE db_benutzer = SUBSTRING_INDEX(USER(), '@', 1)
WITH CASCADED CHECK OPTION;
CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW wartung_mein_befehl AS
SELECT * FROM wartung_befehl WHERE db_benutzer = SUBSTRING_INDEX(USER(), '@', 1)
WITH CASCADED CHECK OPTION;
CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW wartung_mein_download AS
SELECT * FROM wartung_dateiteil WHERE db_benutzer = SUBSTRING_INDEX(USER(), '@', 1) AND richtung = 'hin'
WITH CASCADED CHECK OPTION;
CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW wartung_mein_upload AS
SELECT * FROM wartung_dateiteil WHERE db_benutzer = SUBSTRING_INDEX(USER(), '@', 1) AND richtung = 'zurueck'
WITH CASCADED CHECK OPTION;
