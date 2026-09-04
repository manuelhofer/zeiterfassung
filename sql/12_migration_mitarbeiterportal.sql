-- Migration 12: Mitarbeiterportal - die Seite, die anruft (T-170)
-- Patch: P-2026-09-04-04
-- Datum: 2026-09-04
--
-- Zweck:
--   Mitarbeiter sollen ihren Urlaub von zu Hause oder vom Handy beantragen -
--   ueber einen Bereich `/mitarbeiter` auf der WERNIG-Homepage. Diese
--   Installation steht im Firmennetz und ist von aussen nicht erreichbar;
--   deshalb ruft **sie** dort an und nicht umgekehrt.
--   Vertrag: `docs/spezifikation_mitarbeiterportal.md`.
--
-- Die drei Dinge, die diese Migration anlegt:
--
--   1. `portal_verbindung` - wohin angerufen wird und womit unterschrieben.
--      **Hier steht ein Geheimnis im Klartext**, und das ist Absicht: Wer
--      unterschreiben will, muss den Schluessel haben. Er liegt damit genauso
--      sicher wie die Datenbank selbst - und die enthaelt ohnehin alle
--      Personendaten. Ihn zusaetzlich zu verschluesseln hiesse, den
--      Schluessel des Schluessels danebenzulegen.
--
--   2. `portal_eingang` - der Doppelschutz. Jeder Auftrag, den die Homepage
--      herausgibt, traegt dort eine fortlaufende Nummer. Diese Tabelle haelt
--      fest, welche schon verarbeitet ist, mit `(portal_id, fremd_id)` als
--      eindeutigem Schluessel. Geschrieben wird sie **vor** dem
--      Urlaubsantrag, in derselben Transaktion: Bricht es davor ab, kommt der
--      Auftrag wieder; bricht es danach ab, kommt er auch wieder - und wird
--      als Doppel erkannt. Ohne diese Tabelle wuerde aus einem abgebrochenen
--      Anruf ein zweiter Urlaubsantrag.
--
--   3. Sechs Spalten an `mitarbeiter`. Freischaltung, Kennung,
--      Aktivierungscode mit Frist und zwei Zeitstempel, die von der Homepage
--      zurueckkommen (Vertrag, Abschnitt 5.2).
--
-- Warum `portal_kennung` und nicht `personalnummer`: Die hat im Bestand ein
-- Mitarbeiter von dreizehn. Die Kennung wird bei der Freischaltung
-- vorgeschlagen und ist aenderbar (P-2026-09-04-02).
--
-- Warum der Aktivierungscode nur als Hash dasteht: Derselbe Grund wie beim
-- Kopplungscode eines Terminals. Er wird einmal gedruckt und ist danach nicht
-- wiederherstellbar; wer ihn verliert, bekommt einen neuen.
--
-- Idempotent: Mehrfaches Ausfuehren ist unschaedlich.
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/12_migration_mitarbeiterportal.sql

-- --------------------------------------------------------------------------
-- 1. Die Verbindung zur Homepage
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portal_verbindung` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `basis_url` varchar(255) NOT NULL COMMENT 'z. B. https://wernig.com - ohne Pfad',
  `portal_id` varchar(64) NOT NULL COMMENT 'von der Homepage bei der Kopplung vergeben',
  `schluessel` varchar(128) NOT NULL COMMENT 'Klartext; wird zum Unterschreiben gebraucht',
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `gekoppelt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `entkoppelt_am` datetime DEFAULT NULL,
  `letzter_lauf_am` datetime DEFAULT NULL,
  `letzter_lauf_ok` tinyint(1) DEFAULT NULL COMMENT 'NULL = noch kein Lauf',
  `letzter_fehler` text DEFAULT NULL COMMENT 'Klartext des letzten Fehlschlags, fuer die Maske',
  `letzte_dauer_ms` int(10) UNSIGNED DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_portal_verbindung_portal` (`portal_id`),
  KEY `idx_portal_verbindung_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 2. Der Doppelschutz: was schon verarbeitet wurde
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portal_eingang` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `portal_id` varchar(64) NOT NULL,
  `fremd_id` bigint(20) UNSIGNED NOT NULL COMMENT 'portal_ausgang.id auf der Homepage',
  `art` varchar(30) NOT NULL,
  `mitarbeiter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ergebnis` varchar(20) NOT NULL DEFAULT 'angenommen' COMMENT 'angenommen | abgelehnt',
  `hinweis` text DEFAULT NULL COMMENT 'Klartext fuer den Mitarbeiter, wenn abgelehnt',
  `urlaubsantrag_id` bigint(20) UNSIGNED DEFAULT NULL,
  `verarbeitet_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_portal_eingang` (`portal_id`, `fremd_id`),
  KEY `idx_portal_eingang_zeit` (`verarbeitet_am`),
  KEY `idx_portal_eingang_mitarbeiter` (`mitarbeiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3. Die Portal-Spalten an `mitarbeiter`
-- --------------------------------------------------------------------------
-- `IF NOT EXISTS` an ALTER TABLE gibt es in MariaDB seit 10.0 und es tut hier
-- genau das Richtige: Die Spalte entsteht beim ersten Lauf und der zweite
-- Lauf schweigt. Das ist lesbarer als sechs PREPARE-Bloecke und laeuft
-- ausserdem ueber jeden Weg - Kommandozeile wie PDO.
ALTER TABLE `mitarbeiter`
  ADD COLUMN IF NOT EXISTS `portal_aktiv` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'Darf das Mitarbeiterportal benutzen. 0 = steht dort gar nicht.',
  ADD COLUMN IF NOT EXISTS `portal_kennung` VARCHAR(60) DEFAULT NULL
      COMMENT 'Anmeldename im Portal. NULL = noch keine vergeben.',
  ADD COLUMN IF NOT EXISTS `portal_aktivierung_hash` CHAR(64) DEFAULT NULL
      COMMENT 'SHA-256 des Aktivierungscodes, nie der Code selbst',
  ADD COLUMN IF NOT EXISTS `portal_aktivierung_bis` DATETIME DEFAULT NULL
      COMMENT 'Frist des Aktivierungscodes',
  ADD COLUMN IF NOT EXISTS `portal_aktiviert_am` DATETIME DEFAULT NULL
      COMMENT 'Von der Homepage zurueckgemeldet: wann das Portal-Passwort gesetzt wurde',
  ADD COLUMN IF NOT EXISTS `portal_letzte_anmeldung_am` DATETIME DEFAULT NULL
      COMMENT 'Von der Homepage zurueckgemeldet: letzte Anmeldung im Portal';

-- Eindeutig, aber NULL-faehig: Beliebig viele Mitarbeiter ohne Kennung sind
-- erlaubt (die meisten haben keine), eine vergebene aber nur einmal. In
-- MariaDB sind NULL-Werte in einem eindeutigen Index verschieden - dieselbe
-- Eigenschaft, die schon `personalnummer` nutzt.
ALTER TABLE `mitarbeiter`
  ADD UNIQUE KEY IF NOT EXISTS `uniq_mitarbeiter_portal_kennung` (`portal_kennung`);

-- --------------------------------------------------------------------------
-- 4. Das Recht
-- --------------------------------------------------------------------------
-- Eigenes Recht neben TERMINAL_VERWALTEN: Wer ein Terminal koppelt, stellt ein
-- Geraet in die Halle. Wer das Portal verwaltet, entscheidet, welche
-- Personendaten das Haus verlassen und auf einem oeffentlichen Server liegen.
-- Das ist eine andere Frage und ein kleinerer Kreis.
INSERT INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
SELECT 'PORTAL_VERWALTEN',
       'Mitarbeiterportal verwalten',
       'Darf die Homepage als Mitarbeiterportal koppeln, Mitarbeiter dafür freischalten und Aktivierungscodes erzeugen.',
       1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT code FROM `recht`) r WHERE r.code = 'PORTAL_VERWALTEN');

-- Die Rolle `Chef` ist Superuser (`ist_superuser = 1`) und braucht keine
-- ausdrueckliche Zuordnung. Sie steht hier trotzdem: Wird der Superuser-Haken
-- eines Tages entfernt - und genau das passiert, wenn jemand Rechte
-- feiner schneiden will -, faellt sonst still die Portalverwaltung weg.
INSERT INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT ro.id, re.id
  FROM `rolle` ro
  JOIN `recht` re ON re.code = 'PORTAL_VERWALTEN'
 WHERE ro.name = 'Chef'
   AND NOT EXISTS (
       SELECT 1 FROM (SELECT rolle_id, recht_id FROM `rolle_hat_recht`) x
        WHERE x.rolle_id = ro.id AND x.recht_id = re.id
   );

-- Kontrolle (optional):
-- SELECT portal_aktiv, portal_kennung FROM mitarbeiter LIMIT 5;
-- SELECT code FROM recht WHERE code = 'PORTAL_VERWALTEN';
