-- Offline-DB Minimal-Schema (Terminal)
-- Datum: 2026-01-04
-- Zweck:
-- - Terminals können im Offline-Fall (Haupt-DB down) Aktionen in eine lokale DB schreiben.
-- - Es wird nur die Queue-Tabelle `db_injektionsqueue` benötigt.
-- - Hinweis: Die Anwendung versucht die Tabelle bei Bedarf automatisch anzulegen.
--            Dieses Skript ist optional (z. B. für vorbereitete Terminals/Images).

-- --------------------------------------------------------------------------
-- Lokale Liste der Berechtigten (T-125)
-- --------------------------------------------------------------------------
-- Damit ein unbekannter Chip sofort am Geraet auffaellt und nicht erst beim
-- Einspielen, Stunden spaeter, wenn der Mensch laengst weg ist.
--
-- Bewusst nur vier Felder: keine Namen, keine Passwoerter, keine Kontostaende.
-- Wird das Geraet gestohlen, sind es Nummern ohne Zuordnung.
--
-- Der Spiegel ist KEINE Tuersteherin: Ein Chip, der hier fehlt, wird offline
-- trotzdem angenommen (siehe docs/fachregeln/terminal_und_offline.md,
-- Abschnitt 5). Er kann veraltet sein, und eine verlorene Ankunftszeit waere
-- schlimmer als ein Eintrag, den das Backend spaeter zeigt.
--
-- Hinweis: Die Anwendung legt die Tabelle bei Bedarf selbst an.
CREATE TABLE IF NOT EXISTS mitarbeiter_spiegel (
  mitarbeiter_id BIGINT UNSIGNED NOT NULL,
  personalnummer VARCHAR(50) NULL,
  rfid_code VARCHAR(100) NULL,
  aktiv TINYINT(1) NOT NULL DEFAULT 1,
  aktualisiert_am DATETIME NOT NULL,
  PRIMARY KEY (mitarbeiter_id),
  KEY idx_mitarbeiter_spiegel_rfid (rfid_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
