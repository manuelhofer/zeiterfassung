-- Offline-DB Minimal-Schema (Terminal)
-- Datum: 2026-01-04
-- Zweck:
-- - Terminals können im Offline-Fall (Haupt-DB down) Aktionen in eine lokale DB schreiben.
-- - Es wird nur die Queue-Tabelle `db_injektionsqueue` benötigt.
-- - Hinweis: Die Anwendung versucht die Tabelle bei Bedarf automatisch anzulegen.
--            Dieses Skript ist optional (z. B. für vorbereitete Terminals/Images).

CREATE TABLE IF NOT EXISTS db_injektionsqueue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(20) NOT NULL DEFAULT 'offen',
  sql_befehl MEDIUMTEXT NOT NULL,
  fehlernachricht TEXT NULL,
  versuche INT UNSIGNED NOT NULL DEFAULT 0,
  letzte_ausfuehrung DATETIME NULL,
  meta_mitarbeiter_id BIGINT UNSIGNED NULL,
  meta_terminal_id BIGINT UNSIGNED NULL,
  meta_aktion VARCHAR(100) NULL,
  -- Wird nur in der Hauptdatenbank gefüllt (Herkunftsvermerk beim Einspielen,
  -- T-128). Die Spalte steht hier trotzdem, damit beide Queue-Tabellen
  -- dieselbe Form haben: Auf einem Terminal ohne erreichbare
  -- Ausweichdatenbank ist die Queue der Hauptdatenbank dieselbe Tabelle.
  meta_quell_id BIGINT UNSIGNED NULL,
  erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_erstellt (status, erstellt_am, id),
  KEY idx_herkunft (meta_terminal_id, meta_quell_id, erstellt_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
