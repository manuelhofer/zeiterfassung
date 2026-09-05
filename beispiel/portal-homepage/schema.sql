-- Mitarbeiterportal – Tabellen für die Website-Seite
--
-- Sechs Tabellen, mehr braucht es nicht. Einspielen mit:
--     mariadb IHRE_DATENBANK < schema.sql
--
-- Mehrfach ausführbar: Alles steht unter IF NOT EXISTS.

-- --------------------------------------------------------------------------
-- 1) Die Verbindung zur Zeiterfassung
-- --------------------------------------------------------------------------
-- Gespeichert wird nur der **Hash** des Schlüssels, nie der Schlüssel selbst.
-- Damit ist ein gestohlener Datenbankabzug wertlos für den, der ihn hat:
-- Unterschrieben wird mit genau diesem Hash, und wer ihn hätte, könnte zwar
-- unterschreiben – aber der Schlüssel im Klartext existiert nur einmal, drüben.
CREATE TABLE IF NOT EXISTS portal_kopplung (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    portal_id       VARCHAR(64)  NOT NULL,
    schluessel_hash CHAR(64)     NOT NULL,
    aktiv           TINYINT(1)   NOT NULL DEFAULT 1,
    gekoppelt_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    letzte_ip       VARCHAR(45)      NULL,
    UNIQUE KEY uq_portal_id (portal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 2) Kopplungscodes
-- --------------------------------------------------------------------------
-- Acht Zeichen, 30 Minuten gültig, genau einmal einlösbar. Auch hier nur der
-- Hash: Wer die Datenbank liest, soll keinen gültigen Code herausholen können.
CREATE TABLE IF NOT EXISTS portal_kopplungscode (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    code_hash    CHAR(64) NOT NULL,
    gueltig_bis  DATETIME NOT NULL,
    verbraucht_am DATETIME    NULL,
    UNIQUE KEY uq_code_hash (code_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 3) Wiederholungsschutz
-- --------------------------------------------------------------------------
-- Jede Anfrage trägt eine Nonce. Der eindeutige Index entscheidet, nicht ein
-- vorheriges SELECT – zwei gleichzeitige Anfragen mit derselben Nonce kämen
-- sonst beide durch.
CREATE TABLE IF NOT EXISTS portal_nonce (
    nonce     CHAR(32) NOT NULL PRIMARY KEY,
    zeitpunkt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_zeitpunkt (zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 4) Die gespiegelten Mitarbeiter
-- --------------------------------------------------------------------------
-- `fremd_id` ist die ID **in der Zeiterfassung**. Sie ist der Anker für alles
-- Weitere; eine eigene ID hier wäre nur eine zweite Wahrheit.
--
-- `passwort_hash`, `aktiviert_am` und `letzte_anmeldung_am` entstehen **hier**
-- und werden nie überschrieben – die Zeiterfassung sieht keine Anmeldung.
--
-- `aktivierung_hash_verbraucht` merkt sich einen eingelösten Code. Ohne das
-- Feld schickt der nächste Abgleich denselben Code wieder mit, und ein längst
-- verbrauchter Zettel wäre wieder gültig.
CREATE TABLE IF NOT EXISTS portal_mitarbeiter (
    fremd_id                     INT          NOT NULL PRIMARY KEY,
    kennung                      VARCHAR(60)  NOT NULL,
    anzeigename                  VARCHAR(120) NOT NULL DEFAULT '',
    aktiv                        TINYINT(1)   NOT NULL DEFAULT 1,
    aktivierung_hash             CHAR(64)         NULL,
    aktivierung_bis              DATETIME         NULL,
    aktivierung_hash_verbraucht  CHAR(64)         NULL,
    passwort_hash                VARCHAR(255)     NULL,
    aktiviert_am                 DATETIME         NULL,
    letzte_anmeldung_am          DATETIME         NULL,
    UNIQUE KEY uq_kennung (kennung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 5) Das Schaufenster
-- --------------------------------------------------------------------------
-- Bewusst **eine** Tabelle mit JSON statt sieben mit Spalten. Der Spiegel darf
-- wachsen, ohne dass hier eine Migration nötig wird, und die Website zeigt die
-- Zahlen ohnehin nur an – sie rechnet mit keiner davon.
--
-- Wer lieber echte Spalten möchte: Die Feldnamen je Teil stehen in der README.
--
-- `mitarbeiter` ist NULL bei firmenweiten Teilen (Feiertage, Betriebsferien).
CREATE TABLE IF NOT EXISTS portal_spiegel (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teil         VARCHAR(32) NOT NULL,
    mitarbeiter  INT             NULL,
    schluessel   VARCHAR(64) NOT NULL DEFAULT '',
    inhalt       LONGTEXT    NOT NULL,
    stand        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teil (teil, mitarbeiter, schluessel),
    KEY ix_mitarbeiter (mitarbeiter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 6) Der Briefkasten
-- --------------------------------------------------------------------------
-- Was ein Mitarbeiter hier eingibt, wartet, bis die Zeiterfassung anruft.
-- Sie entscheidet, nicht die Website.
--
-- `art`: urlaub_antrag | urlaub_storno | monat_pdf
CREATE TABLE IF NOT EXISTS portal_auftrag (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    mitarbeiter  INT          NOT NULL,
    art          VARCHAR(32)  NOT NULL,
    daten        TEXT         NOT NULL,
    status       VARCHAR(16)  NOT NULL DEFAULT 'offen',
    hinweis      VARCHAR(255) NOT NULL DEFAULT '',
    erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erledigt_am  DATETIME         NULL,
    KEY ix_offen (status, id),
    KEY ix_mitarbeiter (mitarbeiter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
