-- Offline-DB Minimal-Schema (Terminal)
-- Datum: 2026-01-04
-- Zweck:
-- - Terminals können im Offline-Fall (Haupt-DB down) Aktionen in eine lokale DB schreiben.
-- - Es wird nur die Queue-Tabelle `db_injektionsqueue` benötigt.
-- - Hinweis: Die Anwendung versucht die Tabelle bei Bedarf automatisch anzulegen.
--            Dieses Skript ist optional (z. B. für vorbereitete Terminals/Images).

CREATE TABLE IF NOT EXISTS db_injektionsqueue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(20) NOT NULL DEFAULT 'offen',
  sql_befehl MEDIUMTEXT NOT NULL,
  fehlernachricht TEXT NULL,
  versuche INT UNSIGNED NOT NULL DEFAULT 0,
  letzte_ausfuehrung DATETIME NULL,
  meta_mitarbeiter_id INT UNSIGNED NULL,
  meta_terminal_id INT UNSIGNED NULL,
  meta_aktion VARCHAR(100) NULL,
  erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_erstellt (status, erstellt_am, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
