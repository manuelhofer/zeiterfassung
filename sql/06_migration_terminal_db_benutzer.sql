-- Migration: Terminal-Kopplung, Teil 2 - eigener Datenbankbenutzer je Terminal
-- Patch: P-2026-08-08-35
-- Datum: 2026-08-08
--
-- Zweck:
--   Bei der Kopplung legt das Backend fuer jedes Terminal einen eigenen,
--   eingeschraenkten Datenbankbenutzer an (siehe
--   `docs/spezifikation_terminal_installation.md`, Abschnitt 2a). Damit ein
--   Geraet spaeter gezielt gesperrt und bei einer erneuten Kopplung nicht ein
--   zweiter Benutzer angelegt wird, muss das Backend wissen, welcher Benutzer
--   zu welchem Terminal gehoert. Genau dafuer sind diese Spalten da.
--
-- Idempotent: MariaDB kennt `ADD COLUMN IF NOT EXISTS`.
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/06_migration_terminal_db_benutzer.sql

ALTER TABLE `terminal`
  ADD COLUMN IF NOT EXISTS `db_benutzer` varchar(80) DEFAULT NULL
      COMMENT 'Datenbankbenutzer dieses Terminals (aus der Kopplung)',
  ADD COLUMN IF NOT EXISTS `db_benutzer_host` varchar(60) DEFAULT NULL
      COMMENT 'Host-Muster des Datenbankbenutzers, z. B. % oder 192.168.10.%',
  ADD COLUMN IF NOT EXISTS `gekoppelt_am` datetime DEFAULT NULL
      COMMENT 'Zeitpunkt der letzten erfolgreichen Kopplung',
  ADD COLUMN IF NOT EXISTS `gekoppelt_host` varchar(190) DEFAULT NULL
      COMMENT 'Kennung des Geraets (Hostname/MAC) aus der letzten Kopplung';

-- --------------------------------------------------------------------------
-- WICHTIG: Rechte des Backend-Datenbankbenutzers
-- --------------------------------------------------------------------------
--
-- Damit die Anwendung Terminal-Benutzer anlegen kann, braucht **ihr eigener**
-- Datenbankbenutzer zwei Rechte, die er bisher nicht hat:
--
--   * `CREATE USER`  - global, anders geht es nicht (MySQL/MariaDB kennt kein
--                      schemabezogenes CREATE USER)
--   * `GRANT OPTION` - auf das Schema `zeiterfassung`, um Rechte weitergeben
--                      zu koennen
--
-- Das ist eine bewusste Abwaegung und in der Spezifikation offen benannt: Wer
-- die Weboberflaeche uebernimmt, koennte damit Datenbankbenutzer anlegen.
-- Begrenzt wird das dadurch, dass `GRANT OPTION` nie mehr weitergeben kann,
-- als der Vergebende selbst besitzt.
--
-- Diese Anweisungen kann die Anwendung **nicht selbst** ausfuehren - sie
-- gehoeren einem Administrator mit Root-Zugang. Einmal ausfuehren, Namen und
-- Host an die eigene Installation anpassen:
--
--   GRANT CREATE USER ON *.* TO 'zeiterfassung'@'localhost';
--   GRANT ALL PRIVILEGES ON `zeiterfassung`.* TO 'zeiterfassung'@'localhost' WITH GRANT OPTION;
--   FLUSH PRIVILEGES;
--
-- Kontrolle:
--   SHOW GRANTS FOR 'zeiterfassung'@'localhost';
--
-- Ohne diese Rechte bleibt alles Uebrige funktionsfaehig; nur die Kopplung
-- eines Terminals bricht mit einer entsprechenden Meldung ab.

-- --------------------------------------------------------------------------
-- Konfigurationsschluessel (wird sonst vom DefaultsSeeder angelegt)
-- --------------------------------------------------------------------------
--
-- Von welchen Rechnern aus sich ein Terminal-Benutzer verbinden darf.
-- Standard `%` = beliebiger Rechner. Wer sein Netz kennt, traegt ein engeres
-- Muster ein (z. B. `192.168.10.%`). Feste Einzel-IPs sind nicht zu empfehlen,
-- weil Terminals ihre Adresse per DHCP bekommen.
INSERT INTO `config` (`schluessel`, `wert`, `typ`, `beschreibung`)
SELECT 'terminal_db_host_muster', '%', 'string',
       'Terminal-Kopplung: Host-Muster fuer den Datenbankbenutzer eines Terminals (z. B. % oder 192.168.10.%).'
WHERE NOT EXISTS (
    SELECT 1 FROM `config` WHERE `schluessel` = 'terminal_db_host_muster'
);

-- Adresse, unter der ein Terminal die Datenbank erreicht. Leer = automatisch:
-- der konfigurierte Datenbank-Host, und wenn der lokal ist (`localhost`), die
-- Adresse, unter der das Terminal das Backend erreicht hat. Nur noetig, wenn
-- Datenbank und Backend auf verschiedenen Rechnern liegen.
INSERT INTO `config` (`schluessel`, `wert`, `typ`, `beschreibung`)
SELECT 'terminal_db_host_extern', '', 'string',
       'Terminal-Kopplung: Adresse der Datenbank aus Sicht des Terminals. Leer = automatisch.'
WHERE NOT EXISTS (
    SELECT 1 FROM `config` WHERE `schluessel` = 'terminal_db_host_extern'
);

-- Kontrolle (optional):
-- SHOW COLUMNS FROM `terminal` LIKE 'db\_benutzer%';
-- SELECT schluessel, wert FROM config WHERE schluessel = 'terminal_db_host_muster';
