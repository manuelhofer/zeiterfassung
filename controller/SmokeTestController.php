<?php
declare(strict_types=1);

/**
 * SmokeTestController
 *
 * Zweck (T-069):
 * - Interne Diagnose-Seite im Backend, um Kern-Abhängigkeiten schnell zu prüfen.
 * - Keine mutierenden Aktionen, kein Queue-Processing, keine Fach-Datenänderungen.
 *
 * Aufruf: ?seite=smoke_test
 */
class SmokeTestController
{
    private AuthService $auth;
    private ?Database $db = null;

    public function __construct()
    {
        $this->auth = AuthService::getInstanz();

        try {
            if (class_exists('Database')) {
                $this->db = Database::getInstanz();
            }
        } catch (Throwable $e) {
            $this->db = null;
        }
    }

    /**
     * Zugriff: Rechte-basiert; Legacy-Fallback Chef/Personalbüro.
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->auth->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        $hatLegacyAdminRolle = (
            $this->auth->hatRolle('Chef')
            || $this->auth->hatRolle('Personalbüro')
            || $this->auth->hatRolle('Personalbuero')
        );

        // Primär: jedes "Admin-Recht" genügt, weil Smoke-Test ein Diagnose-Tool ist.
        if (
            $this->auth->hatRecht('KONFIGURATION_VERWALTEN')
            || $this->auth->hatRecht('QUEUE_VERWALTEN')
            || $this->auth->hatRecht('TERMINAL_VERWALTEN')
            || $this->auth->hatRecht('MITARBEITER_VERWALTEN')
            || $this->auth->hatRecht('ROLLEN_RECHTE_VERWALTEN')
            || $this->auth->hatRecht('REPORTS_ANSEHEN_ALLE')
            || $hatLegacyAdminRolle
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung für den Smoke-Test.</p>';
        return false;
    }

    /**
     * @return array<int,array{gruppe:string,titel:string,ok:bool|null,details:string}>
     */
    private function fuehreChecksAus(): array
    {
        $checks = [];

        $add = static function (array &$checks, string $gruppe, string $titel, ?bool $ok, string $details = ''): void {
            $checks[] = [
                'gruppe' => $gruppe,
                'titel' => $titel,
                'ok' => $ok,
                'details' => $details,
            ];
        };

        // PHP/Runtime
        $add($checks, 'Runtime', 'PHP-Version', true, PHP_VERSION);
        $add($checks, 'Runtime', 'Zeitzone', true, (string)date_default_timezone_get());

        $ext = [
            'pdo',
            'pdo_mysql',
            'mbstring',
            'openssl',
            'json',
        ];
        foreach ($ext as $e) {
            $add($checks, 'Runtime', 'Extension: ' . $e, extension_loaded($e), '');
        }

        // Filesystem
        $root = dirname(__DIR__);
        $add($checks, 'Files', 'public/index.php vorhanden', is_file($root . '/public/index.php'), '');
        $add($checks, 'Files', 'public/terminal.php vorhanden', is_file($root . '/public/terminal.php'), '');
        $add($checks, 'Files', 'sql/01_initial_schema.sql vorhanden', is_file($root . '/sql/01_initial_schema.sql'), '');
        // Offline-DB Minimal-Schema-Datei (T-069): Terminals können so vorbereitet werden.
        // Diese Datei ist optional für den Betrieb (die App legt die Tabelle bei Bedarf an),
        // aber sie muss im Repo vorhanden sein, damit Terminals ohne ersten Offline-Write sauber provisioniert werden können.
        $offlineSchemaPath = $root . '/sql/offline_db_schema.sql';
        $offlineSchemaExists = is_file($offlineSchemaPath);
        $add($checks, 'Files', 'sql/offline_db_schema.sql vorhanden', $offlineSchemaExists, '');

        if ($offlineSchemaExists) {
            if (is_readable($offlineSchemaPath)) {
                $offlineInhalt = @file_get_contents($offlineSchemaPath);
                if (is_string($offlineInhalt) && $offlineInhalt !== '') {
                    $hatCreate = (strpos($offlineInhalt, 'CREATE TABLE') !== false && strpos($offlineInhalt, 'db_injektionsqueue') !== false);
                    $hatSpalte = (strpos($offlineInhalt, 'sql_befehl') !== false && strpos($offlineInhalt, 'status') !== false);

                    $ok = ($hatCreate && $hatSpalte);

                    $add(
                        $checks,
                        'Files',
                        'offline_db_schema.sql: db_injektionsqueue Definition',
                        $ok,
                        $ok ? '' : 'Erwartet: CREATE TABLE db_injektionsqueue inkl. Spalten status + sql_befehl.'
                    );
                } else {
                    $add($checks, 'Files', 'offline_db_schema.sql: db_injektionsqueue Definition', null, 'Datei konnte nicht gelesen werden.');
                }
            } else {
                $add($checks, 'Files', 'offline_db_schema.sql: db_injektionsqueue Definition', null, 'Datei ist nicht lesbar.');
            }
        }

        $add($checks, 'Files', 'views/layout/header.php vorhanden', is_file($root . '/views/layout/header.php'), '');
        $add($checks, 'Files', 'views/layout/footer.php vorhanden', is_file($root . '/views/layout/footer.php'), '');

        // Terminal: sicherstellen, dass das Terminal pro Request versucht, die Offline-Queue abzuarbeiten.
        // Hintergrund (T-069): Wenn Terminals offline puffern, müssen sie bei wieder aktiver Haupt-DB
        // die Queue selbst "in die Haupt-DB injizieren", sonst bleiben Einträge lokal liegen.
        $terminalPfad = $root . '/public/terminal.php';
        if (is_file($terminalPfad) && is_readable($terminalPfad)) {
            $inhalt = @file_get_contents($terminalPfad);
            if (is_string($inhalt) && $inhalt !== '') {
                $hatFlush = (strpos($inhalt, 'verarbeiteOffeneEintraege') !== false);
                $hatManager = (strpos($inhalt, 'OfflineQueueManager') !== false);

                $okFlush = ($hatFlush && $hatManager);

                $add(
                    $checks,
                    'Files',
                    'terminal.php: Queue-Flush pro Request',
                    $okFlush,
                    $okFlush ? '' : 'Hinweis: In public/terminal.php sollte OfflineQueueManager::verarbeiteOffeneEintraege() (best effort) aufgerufen werden, damit lokale Queue-Einträge automatisch in die Haupt-DB laufen, sobald diese wieder erreichbar ist.'
                );
            } else {
                $add($checks, 'Files', 'terminal.php: Queue-Flush pro Request', null, 'Datei konnte nicht gelesen werden.');
            }
        } else {
            $add($checks, 'Files', 'terminal.php: Queue-Flush pro Request', null, 'public/terminal.php nicht lesbar.');
        }



        // T-069 (Fortsetzung): Terminal-Online/Offline-Flow (statische Code-Checks)
        // Ziel: Sicherstellen, dass Kommen/Gehen bei offline Haupt-DB in die Offline-Queue gehen
        // und das Terminal den "Pseudo-Erfolg" (ID=0) sinnvoll behandelt.
        $zeitServicePfad = $root . '/services/ZeitService.php';
        if (is_file($zeitServicePfad) && is_readable($zeitServicePfad)) {
            $inhalt = @file_get_contents($zeitServicePfad);
            if (is_string($inhalt) && $inhalt !== '') {
                $hatQueueCall = (strpos($inhalt, 'OfflineQueueManager::getInstanz()->speichereInQueue') !== false);
                $hatPseudoId  = (strpos($inhalt, 'return $ok ? 0 : null') !== false);
                $hatBedingung = (strpos($inhalt, '$this->istTerminalInstallation()') !== false && strpos($inhalt, '$hauptDbOk === false') !== false);

                $ok = ($hatQueueCall && $hatPseudoId && $hatBedingung);

                $detailsFehlt = [];
                if (!$hatQueueCall) { $detailsFehlt[] = 'Queue-Call fehlt'; }
                if (!$hatPseudoId)  { $detailsFehlt[] = 'Pseudo-ID (return 0) fehlt'; }
                if (!$hatBedingung) { $detailsFehlt[] = 'Offline-Bedingung fehlt'; }

                $add(
                    $checks,
                    'Terminal',
                    'ZeitService: Kommen/Gehen offline → Offline-Queue + Pseudo-ID',
                    $ok,
                    $ok ? '' : ('Fehlt: ' . implode(', ', $detailsFehlt))
                );
            } else {
                $add($checks, 'Terminal', 'ZeitService: Offline-Queue-Branch prüfbar', null, 'Datei konnte nicht gelesen werden.');
            }
        } else {
            $add($checks, 'Terminal', 'ZeitService: Offline-Queue-Branch prüfbar', null, 'services/ZeitService.php nicht lesbar.');
        }

        $terminalCtrlPfad = $root . '/controller/TerminalController.php';
        if (is_file($terminalCtrlPfad) && is_readable($terminalCtrlPfad)) {
            $inhalt = @file_get_contents($terminalCtrlPfad);
            if (is_string($inhalt) && $inhalt !== '') {
                $hatIdNullOrZero = (strpos($inhalt, 'elseif ($id === 0)') !== false || strpos($inhalt, '$id === 0') !== false);
                $hatOfflineMsg   = (strpos($inhalt, 'Offline-Queue') !== false);

                $ok = ($hatIdNullOrZero && $hatOfflineMsg);

                $detailsFehlt = [];
                if (!$hatIdNullOrZero) { $detailsFehlt[] = 'Behandlung ID=0 fehlt'; }
                if (!$hatOfflineMsg)   { $detailsFehlt[] = 'Offline-Queue Hinweis fehlt'; }

                $add(
                    $checks,
                    'Terminal',
                    'TerminalController: ID=0 (Offline-Queue) wird sichtbar behandelt',
                    $ok,
                    $ok ? '' : ('Fehlt: ' . implode(', ', $detailsFehlt))
                );
            } else {
                $add($checks, 'Terminal', 'TerminalController: Offline-Queue Behandlung prüfbar', null, 'Datei konnte nicht gelesen werden.');
            }
        } else {
            $add($checks, 'Terminal', 'TerminalController: Offline-Queue Behandlung prüfbar', null, 'controller/TerminalController.php nicht lesbar.');
        }
        // DB
        if ($this->db === null) {
            $add($checks, 'DB', 'Database-Klasse initialisierbar', false, 'Database::getInstanz() schlug fehl');
            return $checks;
        }

        $add($checks, 'DB', 'Hauptdatenbank erreichbar', $this->db->istHauptdatenbankVerfuegbar(), '');

        $pdo = null;
        try {
            $pdo = $this->db->getVerbindung();
        } catch (Throwable $e) {
            $pdo = null;
            $add($checks, 'DB', 'PDO-Verbindung (Haupt-DB) herstellbar', false, $e->getMessage());
        }

        if ($pdo instanceof PDO) {
            $add($checks, 'DB', 'PDO-Verbindung (Haupt-DB) herstellbar', true, '');

            // Server/DB Version
            try {
                $ver = $pdo->query('SELECT VERSION() AS v');
                $row = $ver ? $ver->fetch() : false;
                $add($checks, 'DB', 'DB-Version', true, (string)($row['v'] ?? '')); 
            } catch (Throwable $e) {
                $add($checks, 'DB', 'DB-Version', null, $e->getMessage());
            }

            $this->checkTabellenUndSpalten($checks, $pdo);

            $this->checkSonstigesKonfiguration($checks, $pdo);
        }

        // Offline-DB / Installation-Typ (T-069: Terminal Online/Offline-Flow)
        $installationTyp = null;
        $offlineEnabled = null;
        $offlineHost = null;
        $offlineDbname = null;
        $mainHost = null;
        $mainDbname = null;

        try {
            /** @var array<string,mixed> $cfg */
            $cfg = require $root . '/config/config.php';

            // Haupt-DB Parameter (wichtig, um Terminal-Offline-DB sauber abzugrenzen)
            if (isset($cfg['db']) && is_array($cfg['db'])) {
                $mainHost = (isset($cfg['db']['host']) && is_string($cfg['db']['host'])) ? (string)$cfg['db']['host'] : null;
                $mainDbname = (isset($cfg['db']['dbname']) && is_string($cfg['db']['dbname'])) ? (string)$cfg['db']['dbname'] : null;

                // Falls ein DSN genutzt wird, versuchen wir host/dbname daraus abzuleiten (best effort).
                if (($mainHost === null || $mainDbname === null) && isset($cfg['db']['dsn']) && is_string($cfg['db']['dsn'])) {
                    $dsn = (string)$cfg['db']['dsn'];
                    if ($mainHost === null && preg_match('/(?:^|;)\s*host=([^;]+)/i', $dsn, $m)) {
                        $mainHost = trim((string)$m[1]);
                    }
                    if ($mainDbname === null && preg_match('/(?:^|;)\s*dbname=([^;]+)/i', $dsn, $m)) {
                        $mainDbname = trim((string)$m[1]);
                    }
                }
            }

            if (isset($cfg['app']) && is_array($cfg['app']) && isset($cfg['app']['installation_typ'])) {
                $installationTyp = is_string($cfg['app']['installation_typ']) ? (string)$cfg['app']['installation_typ'] : null;
            }

            if (isset($cfg['offline_db']) && is_array($cfg['offline_db'])) {
                $offlineEnabled = (($cfg['offline_db']['enabled'] ?? false) === true);
                $offlineHost = (isset($cfg['offline_db']['host']) && is_string($cfg['offline_db']['host'])) ? (string)$cfg['offline_db']['host'] : null;
                $offlineDbname = (isset($cfg['offline_db']['dbname']) && is_string($cfg['offline_db']['dbname'])) ? (string)$cfg['offline_db']['dbname'] : null;
            }
        } catch (Throwable $e) {
            // optional
        }

        if ($installationTyp !== null) {
            $add($checks, 'Offline', 'Installation-Typ', true, (string)$installationTyp);
        } else {
            $add($checks, 'Offline', 'Installation-Typ', null, 'nicht ermittelbar');
        }

        if ($offlineEnabled === true) {
            $add($checks, 'Offline', 'offline_db.enabled', true, 'aktiv (' . ($offlineHost ?? '?') . '/' . ($offlineDbname ?? '?') . ')');
        } elseif ($offlineEnabled === false) {
            $add($checks, 'Offline', 'offline_db.enabled', null, 'deaktiviert');
        } else {
            $add($checks, 'Offline', 'offline_db.enabled', null, 'nicht ermittelbar');
        }

        $offlinePdo = null;
        $offlineErr = null;

        try {
            $offlinePdo = $this->db->getOfflineVerbindung();
        } catch (Throwable $e) {
            $offlinePdo = null;
            $offlineErr = $e->getMessage();
        }

        $erwartetOffline = ($installationTyp === 'terminal');

        if ($erwartetOffline && $offlineEnabled !== true) {
            $add($checks, 'Offline', 'Terminal: Offline-DB muss aktiv sein', false, 'installation_typ=terminal, aber offline_db.enabled ist nicht true');
        } elseif ($erwartetOffline && $offlineEnabled === true) {
            if ($offlinePdo instanceof PDO) {
                $add($checks, 'Offline', 'Terminal: Offline-DB erreichbar', true, '');
            } else {
                $add($checks, 'Offline', 'Terminal: Offline-DB erreichbar', false, $offlineErr !== null ? $offlineErr : 'keine Verbindung (PDO null)');
            }
        } elseif ($installationTyp === 'backend' && $offlineEnabled === true) {
            // Backend braucht typischerweise keine Offline-DB – nur Hinweis.
            if ($offlinePdo instanceof PDO) {
                $add($checks, 'Offline', 'Backend: Offline-DB aktiv (Hinweis)', null, 'Backend nutzt Queue normalerweise auf Haupt-DB; Offline-DB ist hier optional');
            } else {
                $add($checks, 'Offline', 'Backend: Offline-DB aktiv (Hinweis)', null, 'enabled=true, aber keine Verbindung (optional)');
            }
        } else {
            // Optional / keine klare Erwartung
            if ($offlinePdo instanceof PDO) {
                $add($checks, 'Offline', 'Offline-DB verbunden', true, '');
            } else {
                $add($checks, 'Offline', 'Offline-DB aktiviert/verbunden', null, 'Optional (kann deaktiviert sein)');
            }
        }

        // T-069 (Fortsetzung): Terminal-Installationen müssen eine *separate* Offline-DB nutzen.
        // Der Kernpunkt: wenn Offline-DB und Haupt-DB identisch sind, ist bei Netz- oder DB-Ausfällen
        // keine lokale Pufferung möglich (Offline-Queue wäre wirkungslos).
        if ($erwartetOffline && $offlineEnabled === true) {
            $ok = null;
            $details = '';

            $mh = ($mainHost !== null) ? trim((string)$mainHost) : '';
            $md = ($mainDbname !== null) ? trim((string)$mainDbname) : '';
            $oh = ($offlineHost !== null) ? trim((string)$offlineHost) : '';
            $od = ($offlineDbname !== null) ? trim((string)$offlineDbname) : '';

            if ($mh === '' || $md === '' || $oh === '' || $od === '') {
                $ok = null;
                $details = 'Haupt/Offline-DB Parameter nicht vollständig ermittelbar.';
            } else {
                $mhNorm = strtolower($mh);
                $ohNorm = strtolower($oh);

                $hostGleich = ($mhNorm === $ohNorm);
                $dbGleich = ($md === $od);

                if ($hostGleich && $dbGleich) {
                    $ok = false;
                    $details = 'Haupt-DB und Offline-DB sind identisch (' . $mh . '/' . $md . '). Offline-Queue kann so nicht puffern.';
                } elseif ($hostGleich) {
                    // Kann funktionieren (zwei DBs auf einem Server), ist aber für Terminals meist falsch.
                    $ok = null;
                    $details = 'Hinweis: Host ist gleich (' . $mh . '), DBs unterscheiden sich (' . $md . ' vs ' . $od . '). Für Terminals empfohlen: Offline-DB lokal (z. B. localhost), Haupt-DB remote.';
                } else {
                    $ok = true;
                    $details = 'Haupt=' . $mh . '/' . $md . ' · Offline=' . $oh . '/' . $od;
                }
            }

            $add($checks, 'Offline', 'Terminal: Offline-DB getrennt von Haupt-DB', $ok, $details);
        }

        // Offline-Queue Schema Check (nicht mutierend)
        if ($offlinePdo instanceof PDO) {
            $ok = null;
            $details = '';
            try {
                $stmt = $offlinePdo->prepare(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
                );
                $stmt->execute(['t' => 'db_injektionsqueue']);
                $row = $stmt->fetch();
                $ok = ($row !== false);
                if ($ok === false) {
                    $details = 'db_injektionsqueue fehlt (wird automatisch beim ersten Queue-Eintrag erstellt oder via sql/offline_db_schema.sql importieren).';
                    $ok = null;
                }
            } catch (Throwable $e) {
                $ok = null;
                $details = $e->getMessage();
            }

            $add($checks, 'Offline', 'Offline-Queue Schema (db_injektionsqueue)', $ok, $details);
        }

        return $checks;
    }

    /**
     * @param array<int,array{gruppe:string,titel:string,ok:bool|null,details:string}> $checks
     */
    private function checkTabellenUndSpalten(array &$checks, PDO $pdo): void
    {
        $add = static function (array &$checks, string $gruppe, string $titel, ?bool $ok, string $details = ''): void {
            $checks[] = [
                'gruppe' => $gruppe,
                'titel' => $titel,
                'ok' => $ok,
                'details' => $details,
            ];
        };

        $tabellen = [
            'mitarbeiter',
            'zeitbuchung',
            'auftrag',
            'auftragszeit',
            'terminal',
            'db_injektionsqueue',
            'system_log',
            'urlaubsantrag',
            'betriebsferien',
            'feiertag',
            'rolle',
            'recht',
            'rolle_hat_recht',
            'config',
            'sonstiges_grund',
            'tageswerte_mitarbeiter',
        ];

        foreach ($tabellen as $t) {
            $ok = null;
            $details = '';
            try {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
                );
                $stmt->execute(['t' => $t]);
                $row = $stmt->fetch();
                $ok = ($row !== false);
            } catch (Throwable $e) {
                $ok = null;
                $details = $e->getMessage();
            }
            $add($checks, 'DB', 'Tabelle vorhanden: ' . $t, $ok, $details);
        }

        // Kritische Spaltenchecks (damit Kernflows nicht an Schema-Mismatch scheitern)
        $spaltenChecks = [
            ['terminal', 'name'],
            // Neu: Personalnummer wird für Terminal-Login und Admin-Pflege genutzt.
            // Wenn die Spalte fehlt, läuft das Feature zwar evtl. „teilweise“, aber die Datenhaltung ist inkonsistent.
            ['mitarbeiter', 'personalnummer'],
            ['auftrag', 'auftragsnummer'],
            ['zeitbuchung', 'typ'],
            // Schema: `zeitbuchung.zeitstempel` (nicht `zeitpunkt`).
            ['zeitbuchung', 'zeitstempel'],
            ['db_injektionsqueue', 'status'],
            ['tageswerte_mitarbeiter', 'kommentar'],
            ['tageswerte_mitarbeiter', 'kennzeichen_sonstiges'],
            ['tageswerte_mitarbeiter', 'sonstige_stunden'],
            ['sonstiges_grund', 'code'],
            ['sonstiges_grund', 'default_stunden'],
            ['sonstiges_grund', 'begruendung_pflicht'],
            ['sonstiges_grund', 'aktiv'],
        ];

        foreach ($spaltenChecks as $sc) {
            [$t, $c] = $sc;
            $ok = null;
            $details = '';
            try {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1'
                );
                $stmt->execute(['t' => $t, 'c' => $c]);
                $row = $stmt->fetch();
                $ok = ($row !== false);
            } catch (Throwable $e) {
                $ok = null;
                $details = $e->getMessage();
            }
            $add($checks, 'DB', 'Spalte vorhanden: ' . $t . '.' . $c, $ok, $details);
        }

        // Unique/Constraint-Checks (wichtig für eindeutige Terminal-Login-Codes)
        $uniqueSpalten = [
            ['mitarbeiter', 'personalnummer'],
            ['mitarbeiter', 'rfid_code'],
            ['sonstiges_grund', 'code'],
        ];

        foreach ($uniqueSpalten as $uc) {
            [$t, $c] = $uc;
            $ok = null;
            $details = '';
            try {
                $stmt = $pdo->prepare(
                    'SELECT index_name
                     FROM information_schema.statistics
                     WHERE table_schema = DATABASE()
                       AND table_name = :t
                       AND column_name = :c
                       AND non_unique = 0
                     LIMIT 1'
                );
                $stmt->execute(['t' => $t, 'c' => $c]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $ok = ($row !== false);
                if ($ok === true && is_array($row) && isset($row['index_name'])) {
                    $details = 'Index: ' . (string)$row['index_name'];
                }
            } catch (Throwable $e) {
                $ok = null;
                $details = $e->getMessage();
            }
            $add($checks, 'DB', 'Unique Index: ' . $t . '.' . $c, $ok, $details);
        }

    }



    /**
     * T-069 (Fortsetzung): Sonstiges-Flow (konfigurierbare Gründe) – Read-Only Checks.
     *
     * @param array<int,array{gruppe:string,titel:string,ok:bool|null,details:string}> $checks
     */
    private function checkSonstigesKonfiguration(array &$checks, PDO $pdo): void
    {
        $add = static function (array &$checks, string $gruppe, string $titel, ?bool $ok, string $details = ''): void {
            $checks[] = [
                'gruppe' => $gruppe,
                'titel' => $titel,
                'ok' => $ok,
                'details' => $details,
            ];
        };

        // Aktiv-Gründe zählen (Dropdown in Tagesansicht / Sonstiges-Flow)
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM sonstiges_grund WHERE aktiv = 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $c = is_array($row) ? (int)($row['c'] ?? 0) : 0;

            if ($c > 0) {
                $add($checks, 'Sonstiges', 'Sonstiges-Gründe aktiv', true, (string)$c);
            } else {
                $add($checks, 'Sonstiges', 'Sonstiges-Gründe aktiv', false, '0 (Dropdown wäre leer)');
            }
        } catch (Throwable $e) {
            $add($checks, 'Sonstiges', 'Sonstiges-Gründe aktiv', false, $e->getMessage());
        }

        // Default: SoU (Sonderurlaub) sollte im SoT existieren (Alt-DBs: per Migration/Seed nachziehen)
        try {
            $stmt = $pdo->prepare("SELECT id, aktiv, default_stunden, begruendung_pflicht FROM sonstiges_grund WHERE code = :c LIMIT 1");
            $stmt->execute([':c' => 'SoU']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                $aktiv = ((int)($row['aktiv'] ?? 0) === 1);
                $details = 'id=' . (int)$row['id']
                    . ', aktiv=' . ((int)($row['aktiv'] ?? 0))
                    . ', default=' . (string)($row['default_stunden'] ?? '')
                    . ', begr_pflicht=' . (string)($row['begruendung_pflicht'] ?? '');

                $add($checks, 'Sonstiges', 'Default-Grund SoU vorhanden', $aktiv ? true : null, $details . ($aktiv ? '' : ' (inaktiv)'));
            } else {
                $add($checks, 'Sonstiges', 'Default-Grund SoU vorhanden', null, 'nicht gefunden (Alt-DB? Migration/Seed prüfen)');
            }
        } catch (Throwable $e) {
            $add($checks, 'Sonstiges', 'Default-Grund SoU vorhanden', false, $e->getMessage());
        }
    }


    /**
     * Sendet PDF als Inline-Response (sicher gegen Output-BOM/Kompression).
     */
    private function sendePdfInline(string $pdfInhalt, string $filename): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Sicherstellen, dass wirklich nichts vor dem PDF im Output hängt
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Output-Compression deaktivieren (Content-Length/Parsing-Probleme vermeiden)
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('display_errors', '0');

        // Falls möglich: Apache/Proxy-Kompression für dieses Response deaktivieren
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
            @apache_setenv('dont-vary', '1');
        }

        header('Cache-Control: private, no-store, max-age=0, must-revalidate, no-transform');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        echo $pdfInhalt;
        exit;
    }

    /**
     * Erzeugt ein Multi-Page Monats-PDF aus synthetischen Daten (3 Blöcke/Tag).
     *
     * @return array{pdf:string,days_in_month:int,blocks_per_day:int,rows_expected:int}
     */
    private function erzeugePdfSynthMultipage(int $jahr, int $monat): array
    {
        if ($jahr < 1970 || $jahr > 2100) {
            throw new \InvalidArgumentException('Ungültiges Jahr (1970..2100).');
        }
        if ($monat < 1 || $monat > 12) {
            throw new \InvalidArgumentException('Ungültiger Monat (1..12).');
        }
        if (!class_exists('PDFService')) {
            throw new \RuntimeException('PDFService ist nicht verfügbar (Klasse fehlt).');
        }

        $startDt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
        $daysInMonth = (int)$startDt->modify('last day of this month')->format('j');

        $tageswerte = [];
        $blocksPerDay = 3;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $ymd = sprintf('%04d-%02d-%02d', $jahr, $monat, $day);

            $arbeitsbloecke = [
                [
                    'kommen_roh'  => $ymd . ' 05:30:00',
                    'gehen_roh'   => $ymd . ' 09:00:00',
                    'kommen_korr' => $ymd . ' 05:30:00',
                    'gehen_korr'  => $ymd . ' 09:00:00',
                ],
                [
                    'kommen_roh'  => $ymd . ' 09:15:00',
                    'gehen_roh'   => $ymd . ' 12:30:00',
                    'kommen_korr' => $ymd . ' 09:15:00',
                    'gehen_korr'  => $ymd . ' 12:30:00',
                ],
                [
                    'kommen_roh'  => $ymd . ' 13:00:00',
                    'gehen_roh'   => $ymd . ' 16:00:00',
                    'kommen_korr' => $ymd . ' 13:00:00',
                    'gehen_korr'  => $ymd . ' 16:00:00',
                ],
            ];

            $tageswerte[] = [
                'datum' => $ymd,
                'pausen_stunden' => '0.75',
                'arbeitszeit_stunden' => '8.00',
                'arzt_stunden' => '0.00',
                'krank_lfz_stunden' => '0.00',
                'krank_kk_stunden' => '0.00',
                'feiertag_stunden' => '0.00',
                'kurzarbeit_stunden' => '0.00',
                'urlaub_stunden' => '0.00',
                'sonstige_stunden' => '0.00',
                'kommentar' => ($day === 1 ? 'SoU: SmokeTest' : ''),
                'zeit_manuell_geaendert' => (($day % 7) === 0 ? 1 : 0),
                'arbeitsbloecke' => $arbeitsbloecke,
            ];
        }

        $sollstunden = (float)$daysInMonth * 8.0;
        $monatswerte = [
            'sollstunden' => number_format($sollstunden, 2, '.', ''),
        ];

        $pdfService = PDFService::getInstanz();
        if (!method_exists($pdfService, 'erzeugeMonatsPdfAusDaten')) {
            throw new \RuntimeException('PDFService::erzeugeMonatsPdfAusDaten() fehlt (ältere Version).');
        }

        $pdfInhalt = (string)$pdfService->erzeugeMonatsPdfAusDaten(9999, 'SMOKE TEST', $jahr, $monat, $tageswerte, $monatswerte);

        return [
            'pdf' => $pdfInhalt,
            'days_in_month' => $daysInMonth,
            'blocks_per_day' => $blocksPerDay,
            'rows_expected' => ($daysInMonth * $blocksPerDay) + 2, // + Header + Abschluss "/"
        ];
    }


    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        // Smoke-Test: Session/CSRF + Flash (PRG) – nur für Smoke-Test Aktionen
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfKey = 'smoke_test_csrf_token';
        $csrfToken = $_SESSION[$csrfKey] ?? null;
        if (!is_string($csrfToken) || $csrfToken === '') {
            try {
                $csrfToken = bin2hex(random_bytes(32));
            } catch (\Throwable) {
                $csrfToken = bin2hex((string)mt_rand());
            }
            $_SESSION[$csrfKey] = $csrfToken;
        }

        $flashKey = 'smoke_test_flash';
        $smokeFlash = $_SESSION[$flashKey] ?? null;
        if (is_string($smokeFlash) && $smokeFlash !== '') {
            unset($_SESSION[$flashKey]);
        } else {
            $smokeFlash = null;
        }


        // Aktion: Synth-PDF direkt ausliefern (Viewer-Test, DB-unabhängig)
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (string)($_GET['smoke_pdf'] ?? '') === 'synth_multipage') {
            $jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
            $monat = isset($_GET['monat']) ? (int)$_GET['monat'] : (int)date('n');

            try {
                $r = $this->erzeugePdfSynthMultipage($jahr, $monat);
                $pdf = (string)($r['pdf'] ?? '');
                if ($pdf === '') {
                    throw new \RuntimeException('PDF-Inhalt ist leer.');
                }

                $fn = 'smoke_synth_' . $jahr . '_' . sprintf('%02d', $monat) . '.pdf';
                $this->sendePdfInline($pdf, $fn);
            } catch (\Throwable $e) {
                http_response_code(500);
                echo '<p>Synth-PDF konnte nicht erzeugt werden: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
            return;
        }

        // Aktion: Offline-Queue Roundtrip (harmloses SQL `DO 1`)
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'queue_roundtrip') {
            $postToken = (string)($_POST['csrf_token'] ?? '');
            if (!hash_equals((string)$csrfToken, $postToken)) {
                $_SESSION[$flashKey] = 'CSRF-Token ungültig – Aktion abgebrochen.';
            } else {
                $msg = null;

                if ($this->db === null) {
                    $msg = 'Keine Datenbankverbindung verfügbar – Roundtrip nicht möglich.';
                } else {
                    // Marker zur eindeutigen Zuordnung
                    $marker = null;
                    try {
                        $marker = bin2hex(random_bytes(6));
                    } catch (\Throwable) {
                        $marker = (string)mt_rand();
                    }

                    $aktion = 'smoke_roundtrip_' . $marker;

                    try {
                        // In Queue schreiben (Schema wird dabei automatisch sichergestellt)
                        $ok = OfflineQueueManager::getInstanz()->speichereInQueue('DO 1', null, null, $aktion);

                        if (!$ok) {
                            $msg = 'Queue-Roundtrip: Eintrag konnte nicht in die Queue geschrieben werden.';
                        } else {
                            // Verarbeitung anstoßen (nur wenn Haupt-DB verfügbar)
                            try {
                                OfflineQueueManager::getInstanz()->verarbeiteOffeneEintraege();
                            } catch (\Throwable $e) {
                                // Fehler in Verarbeitung wird unten über Status angezeigt
                            }

                            // Status des Test-Eintrags lesen
                            $pdoTmp = null;
                            $quelle = 'unbekannt';

                            try {
                                $pdoOff = $this->db->getOfflineVerbindung();
                                if ($pdoOff instanceof PDO) {
                                    $pdoTmp = $pdoOff;
                                    $quelle = 'offline';
                                }
                            } catch (\Throwable) {
                                $pdoTmp = null;
                            }

                            if (!($pdoTmp instanceof PDO)) {
                                $pdoTmp = $this->db->getVerbindung();
                                $quelle = 'haupt';
                            }

                            $stmt = $pdoTmp->prepare("SELECT id, status, fehlernachricht FROM db_injektionsqueue WHERE meta_aktion = :a ORDER BY id DESC LIMIT 1");
                            $stmt->execute([':a' => $aktion]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);

                            if (is_array($row)) {
                                $id = (int)($row['id'] ?? 0);
                                $status = (string)($row['status'] ?? '');
                                $fehler = (string)($row['fehlernachricht'] ?? '');
                                $quelleLabel = ($quelle === 'offline') ? 'Offline-DB' : 'Haupt-DB';

                                if ($status === 'verarbeitet') {
                                    $msg = 'Queue-Roundtrip OK (Eintrag #' . $id . ', ' . $quelleLabel . ').';
                                } elseif ($status === 'offen') {
                                    $msg = 'Queue-Roundtrip HINWEIS: Eintrag #' . $id . ' ist noch offen (Haupt-DB offline?).';
                                } else {
                                    $msg = 'Queue-Roundtrip FEHLER: Eintrag #' . $id . ' Status=' . $status . ($fehler !== '' ? ' (' . $fehler . ')' : '') . '.';
                                }
                            } else {
                                $msg = 'Queue-Roundtrip: Test-Eintrag wurde nicht wiedergefunden (Queue-DB?).';
                            }
                        }
                    } catch (\Throwable $e) {
                        $msg = 'Queue-Roundtrip FEHLER: ' . $e->getMessage();
                    }
                }

                $_SESSION[$flashKey] = (string)$msg;
            }

            header('Location: ?seite=smoke_test');
            exit;
        }

        $checks = $this->fuehreChecksAus();

        // T-069 (Teil): Offline-Queue Übersicht (rein lesend)
        $queueUebersicht = null;
        $queueHinweis = null;

        if ($this->db !== null) {
            try {
                // Queue-DB Auswahl wie in `QueueController`: Offline-DB bevorzugen (falls konfiguriert).
                $pdoTmp = null;
                $queueQuelle = 'haupt';

                try {
                    $pdoOff = $this->db->getOfflineVerbindung();
                    if ($pdoOff instanceof PDO) {
                        $pdoTmp = $pdoOff;
                        $queueQuelle = 'offline';
                    }
                } catch (Throwable $e) {
                    // Ignore – wir fallen auf Haupt-DB zurück.
                    $pdoTmp = null;
                }

                if (!($pdoTmp instanceof PDO)) {
                    $pdoTmp = $this->db->getVerbindung();
                    $queueQuelle = 'haupt';
                }

                if ($pdoTmp instanceof PDO) {
                    $counts = ['offen' => 0, 'verarbeitet' => 0, 'fehler' => 0];

                    $stmt = $pdoTmp->query("SELECT status, COUNT(*) AS c FROM db_injektionsqueue GROUP BY status");
                    if ($stmt) {
                        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            if (!is_array($r)) {
                                continue;
                            }
                            $st = (string)($r['status'] ?? '');
                            $c = (int)($r['c'] ?? 0);
                            if (array_key_exists($st, $counts)) {
                                $counts[$st] = $c;
                            }
                        }
                    }

                    $counts['gesamt'] = (int)($counts['offen'] + $counts['verarbeitet'] + $counts['fehler']);

                    $latest = [];
                    $stmt2 = $pdoTmp->query(
                        "SELECT id, erstellt_am, status, versuche, letzte_ausfuehrung, meta_mitarbeiter_id, meta_terminal_id, meta_aktion, fehlernachricht
                         FROM db_injektionsqueue
                         ORDER BY id DESC
                         LIMIT 10"
                    );
                    if ($stmt2) {
                        while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                            if (!is_array($r)) {
                                continue;
                            }

                            $fn = (string)($r['fehlernachricht'] ?? '');
                            if ($fn !== '') {
                                if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                                    if (mb_strlen($fn, 'UTF-8') > 160) {
                                        $fn = mb_substr($fn, 0, 160, 'UTF-8') . '…';
                                    }
                                } else {
                                    if (strlen($fn) > 160) {
                                        $fn = substr($fn, 0, 160) . '...';
                                    }
                                }
                            }

                            $r['fehlernachricht_kurz'] = $fn;
                            $latest[] = $r;
                        }
                    }

                    $queueUebersicht = [
                        'quelle' => $queueQuelle,
                        'counts' => $counts,
                        'latest' => $latest,
                    ];

                    if ((int)($counts['fehler'] ?? 0) > 0) {
                        $queueHinweis = 'Es gibt Queue-Einträge im Status "fehler" (' . (($queueQuelle ?? 'haupt') === 'offline' ? 'Offline-DB' : 'Haupt-DB') . '). Bitte Queue im Backend prüfen.';
                    } elseif ((int)($counts['offen'] ?? 0) > 0) {
                        $queueHinweis = 'Es gibt offene Queue-Einträge (' . (($queueQuelle ?? 'haupt') === 'offline' ? 'Offline-DB' : 'Haupt-DB') . '). Falls die Haupt-DB wieder online ist: Queue im Backend verarbeiten.';
                    }
                }
            } catch (Throwable $e) {
                $queueHinweis = 'Queue-Übersicht konnte nicht geladen werden: ' . $e->getMessage();
                $queueUebersicht = null;
            }
        }

        // T-069 (Teil): Terminal-Konfiguration (Timeouts) – rein lesend
        // Hinweis: Das Terminal nutzt `config` Keys, fällt aber auf Defaults zurück, wenn nicht gesetzt/ungültig.
        $terminalConfig = null;
        $terminalConfigHinweis = null;

        if ($this->db !== null) {
            try {
                $pdoCfg = $this->db->getVerbindung();
                if ($pdoCfg instanceof PDO) {
                    $defs = [
                        [
                            'key' => 'terminal_timeout_standard',
                            'titel' => 'Auto-Logout Timeout (Standard)',
                            'default' => 60,
                            'min' => 10,
                            'max' => 1800,
                        ],
                        [
                            'key' => 'terminal_timeout_urlaub',
                            'titel' => 'Auto-Logout Timeout (Urlaub)',
                            'default' => 180,
                            'min' => 30,
                            'max' => 3600,
                        ],
                        [
                            'key' => 'terminal_session_idle_timeout',
                            'titel' => 'Server-Session Idle Timeout (Fallback)',
                            'default' => 300,
                            'min' => 30,
                            'max' => 86400,
                        ],
                    ];

                    $keys = array_map(static fn(array $d): string => (string)$d['key'], $defs);
                    $placeholders = implode(',', array_fill(0, count($keys), '?'));

                    $map = [];
                    $stmtCfg = $pdoCfg->prepare("SELECT schluessel, wert FROM config WHERE schluessel IN ($placeholders)");
                    $stmtCfg->execute($keys);
                    while ($r = $stmtCfg->fetch(PDO::FETCH_ASSOC)) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $k = (string)($r['schluessel'] ?? '');
                        if ($k === '') {
                            continue;
                        }
                        $map[$k] = (string)($r['wert'] ?? '');
                    }

                    $rows = [];
                    foreach ($defs as $d) {
                        $k = (string)$d['key'];
                        $raw = array_key_exists($k, $map) ? (string)$map[$k] : null;

                        $storedInt = null;
                        if ($raw !== null) {
                            $rawTrim = trim($raw);
                            if ($rawTrim !== '' && is_numeric($rawTrim)) {
                                $storedInt = (int)$rawTrim;
                            }
                        }

                        $min = (int)$d['min'];
                        $max = (int)$d['max'];
                        $def = (int)$d['default'];

                        $valid = ($storedInt !== null && $storedInt >= $min && $storedInt <= $max);
                        $effective = $valid ? (int)$storedInt : $def;

                        $status = 'default';
                        if ($raw !== null) {
                            $status = $valid ? 'ok' : 'invalid';
                        }

                        $rows[] = [
                            'key' => $k,
                            'titel' => (string)$d['titel'],
                            'raw' => $raw,
                            'effective' => $effective,
                            'status' => $status,
                            'min' => $min,
                            'max' => $max,
                            'default' => $def,
                        ];
                    }

                    $terminalConfig = $rows;
                }
            } catch (Throwable $e) {
                $terminalConfigHinweis = 'Terminal-Konfiguration konnte nicht geladen werden: ' . $e->getMessage();
                $terminalConfig = null;
            }
        }


        // T-069 (Teil): Terminal-Login-Resolver
        // - Rein lesend.
        // - Emuliert die Terminal-Login-Reihenfolge: RFID → (wenn numerisch) Personalnummer → (wenn numerisch) Mitarbeiter-ID.
        $terminalLoginCode = '';
        $terminalLoginErgebnis = null;
        $terminalLoginHinweis = null;

        // T-069 (Teil): PDF-Quick-Check
        // - Rein lesend.
        // - Erzeugt das PDF im Speicher und prüft nur Header/EOF, ohne es auszugeben.
        $pdfTestJahr = (int)date('Y');
        $pdfTestMonat = (int)date('n');
        $pdfTestMitarbeiterId = 0;
        $pdfTestErgebnis = null;
        $pdfTestHinweis = null;


        // T-069 (Fortsetzung): PDF-Synth-Check (Multi-Block + Multi-Page, DB-unabhängig)
        // - Rein lesend (keine DB-Reads/Mutationen nötig)
        // - Erzeugt ein synthetisches Monats-PDF im Speicher und erwartet mind. 2 Seiten.
        $pdfSynthJahr = (int)date('Y');
        $pdfSynthMonat = (int)date('n');
        $pdfSynthErgebnis = null;
        $pdfSynthHinweis = null;

        // T-069 (Fortsetzung): PDF DB Auto-Multipage-Check (Kandidat-Finder)
        // - Rein lesend.
        // - Sucht in den letzten X Monaten den Mitarbeiter/Monat mit den meisten Kommen/Gehen-Buchungen
        //   und prüft, ob das erzeugte Monats-PDF mindestens 2 Seiten hat.
        $pdfDbMultiWindowMonate = 6;
        $pdfDbMultiErgebnis = null;
        $pdfDbMultiHinweis = null;

        // T-069 (Fortsetzung): PDF DB Kandidaten-Liste (Top-N, rein lesend)
        // - Listet die "besten" Mitarbeiter/Monat-Kombinationen (Kommen/Gehen) im Suchfenster.
        // - Keine PDF-Erzeugung (schnell), nur Links + optionale Detailprüfung via separater Aktion.
        $pdfDbMultiListLimit = 10;
        $pdfDbMultiListe = null;
        $pdfDbMultiListHinweis = null;


        $pdfKommentarSamples = [];
        $pdfKommentarCheck = [];
        $pdfKommentarHinweis = null;

        // Default: angemeldeter Mitarbeiter (wenn vorhanden)
        $angemeldetFuerPdf = $this->auth->holeAngemeldetenMitarbeiter();
        if (is_array($angemeldetFuerPdf) && isset($angemeldetFuerPdf['id'])) {
            $pdfTestMitarbeiterId = (int)$angemeldetFuerPdf['id'];
        }

        // T-069 (Teil): Feiertag-Quick-Check (Monatsreport)
        // - Prüft, ob ein Datum im Monatsreport als Feiertag erkannt wird und (wenn ohne Arbeit) Sollstunden im Feld "Feiertag" landen.
        // - Hinweis: Der Report kann im Hintergrund fehlende Feiertage nachziehen (idempotentes Seeding), weil es auch im Live-Betrieb so funktioniert.
        $feiertagTestDatum = (new DateTimeImmutable('today'))->format('Y-01-01');
        $feiertagTestMitarbeiterId = $pdfTestMitarbeiterId;
        $feiertagTestErgebnis = null;
        $feiertagTestHinweis = null;

        // T-069 (Teil): Feiertag-Seed-Check (bundesweit)
        // - Prüft, ob die bundeseinheitliche Grundmenge für ein Jahr vollständig in `feiertag` vorhanden ist.
        // - Rein lesend (Seeding ist idempotent und entspricht dem Live-Verhalten von istFeiertag()).
        $feiertagSeedJahr = (int)date('Y');
        $feiertagSeedErgebnis = null;
        $feiertagSeedHinweis = null;

        // T-069 (Teil): Monatsreport-Raster-Check
        // - Prüft, ob `ReportService::holeMonatsdatenFuerMitarbeiter()` wirklich ein vollständiges Monatsraster liefert.
        // - Erwartung: Anzahl Tageswerte = Anzahl Kalendertage im Monat UND alle Datumswerte (YYYY-MM-DD) sind vorhanden.
        $monatsrasterTestJahr = (int)date('Y');
        $monatsrasterTestMonat = (int)date('n');
        $monatsrasterTestMitarbeiterId = $pdfTestMitarbeiterId;
        $monatsrasterTestErgebnis = null;
        $monatsrasterTestHinweis = null;

        // T-069 (Fortsetzung): Monatsreport-Fallback-Check (lueckenhafte Tageswerte)
        // - Prueft, ob es Tage mit Zeitbuchungen gibt, die noch keinen Datensatz in `tageswerte_mitarbeiter` haben,
        //   und ob der Monatsreport diese Tage trotzdem sinnvoll fuellt (Fallback aus Zeitbuchungen).
        $monatsfallbackTestJahr = (int)date('Y');
        $monatsfallbackTestMonat = (int)date('n');
        $monatsfallbackTestMitarbeiterId = $pdfTestMitarbeiterId;
        $monatsfallbackTestErgebnis = null;
        $monatsfallbackTestHinweis = null;


        // T-069 (Fortsetzung): Kommen/Gehen-Sequenz-Check (Monat)
        // - Analysiert die Reihenfolge der Zeitbuchungen pro Tag (kommen/gehen).
        // - Findet Auffaelligkeiten: doppelte Typen, gehen ohne kommen, offener Block (kommen ohne gehen), ungerade Anzahl.
        // - Rein lesend.
        $buchungssequenzTestJahr = (int)date('Y');
        $buchungssequenzTestMonat = (int)date('n');
        $buchungssequenzTestMitarbeiterId = $pdfTestMitarbeiterId;
        $buchungssequenzTestErgebnis = null;
        $buchungssequenzTestHinweis = null;


        // T-069 (Fortsetzung): Doppelzählung-Check (Betriebsferien/Kurzarbeit Volltag)
        // - Prüft im Monatsreport, ob Betriebsferien (Urlaub 8h) oder Kurzarbeit-Volltag nicht zusätzlich gezählt werden,
        //   wenn Arbeitszeit vorhanden ist.
        // - Rein lesend.
        $doppelzaehlungTestJahr = (int)date('Y');
        $doppelzaehlungTestMonat = (int)date('n');
        $doppelzaehlungTestMitarbeiterId = $pdfTestMitarbeiterId;
        $doppelzaehlungTestErgebnis = null;
        $doppelzaehlungTestHinweis = null;

        // T-069 (Fortsetzung): Feiertag+Arbeitszeit Doppelzählung-Check (Monat)
        // - Prüft im Monatsreport, ob an Feiertagen mit Arbeitszeit keine Feiertagsstunden zusätzlich gezählt werden.
        // - Rein lesend.
        $feiertagArbeitszeitTestJahr = (int)date('Y');
        $feiertagArbeitszeitTestMonat = (int)date('n');
        $feiertagArbeitszeitTestMitarbeiterId = $pdfTestMitarbeiterId;
        $feiertagArbeitszeitTestErgebnis = null;
        $feiertagArbeitszeitTestHinweis = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('terminal_login_code', $_POST)) {
            $terminalLoginCode = trim((string)($_POST['terminal_login_code'] ?? ''));

            if ($terminalLoginCode === '') {
                $terminalLoginHinweis = 'Bitte einen Code eingeben.';
            } elseif ($this->db === null) {
                $terminalLoginHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } else {
				try {
                    $pdo = $this->db->getVerbindung();

                    $fetchOne = static function (PDO $pdo, string $sql, array $params): ?array {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        return is_array($row) ? $row : null;
                    };

					$match = null;
					$matchTyp = null;
					$warnungen = [];
					$fehlerCode = null;

                    // 1) RFID (aktiv)
                    $match = $fetchOne(
                        $pdo,
                        'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
                         FROM mitarbeiter
                         WHERE rfid_code = :code AND aktiv = 1
                         LIMIT 1',
                        ['code' => $terminalLoginCode]
                    );
                    if (is_array($match) && isset($match['id'])) {
                        $matchTyp = 'RFID';
                    } else {
                        $inactive = $fetchOne(
                            $pdo,
                            'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
                             FROM mitarbeiter
                             WHERE rfid_code = :code
                             LIMIT 1',
                            ['code' => $terminalLoginCode]
                        );
                        if (is_array($inactive) && isset($inactive['id']) && (int)($inactive['aktiv'] ?? 0) !== 1) {
                            $warnungen[] = 'RFID-Code gehört zu einem inaktiven Mitarbeiter (ID ' . (int)$inactive['id'] . ').';
                        }
                    }

                    // 2) Personalnummer (nur numerisch, aktiv)
                    if ($matchTyp === null && ctype_digit($terminalLoginCode)) {
                        $match = $fetchOne(
                            $pdo,
                            'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
                             FROM mitarbeiter
                             WHERE personalnummer = :pn AND aktiv = 1
                             LIMIT 1',
                            ['pn' => $terminalLoginCode]
                        );
                        if (is_array($match) && isset($match['id'])) {
                            $matchTyp = 'Personalnummer';
                        } else {
                            $inactive = $fetchOne(
                                $pdo,
                                'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
                                 FROM mitarbeiter
                                 WHERE personalnummer = :pn
                                 LIMIT 1',
                                ['pn' => $terminalLoginCode]
                            );
                            if (is_array($inactive) && isset($inactive['id']) && (int)($inactive['aktiv'] ?? 0) !== 1) {
                                $warnungen[] = 'Personalnummer gehört zu einem inaktiven Mitarbeiter (ID ' . (int)$inactive['id'] . ').';
                            }
                        }
                    }

                    // 3) ID (nur numerisch, aktiv)
                    if ($matchTyp === null && ctype_digit($terminalLoginCode)) {
                        $match = $fetchOne(
                            $pdo,
                            'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
                             FROM mitarbeiter
                             WHERE id = :id AND aktiv = 1
                             LIMIT 1',
                            ['id' => (int)$terminalLoginCode]
                        );
                        if (is_array($match) && isset($match['id'])) {
                            $matchTyp = 'Mitarbeiter-ID';
                        } else {
                            $inactive = $fetchOne(
                                $pdo,
                                'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
                                 FROM mitarbeiter
                                 WHERE id = :id
                                 LIMIT 1',
                                ['id' => (int)$terminalLoginCode]
                            );
                            if (is_array($inactive) && isset($inactive['id']) && (int)($inactive['aktiv'] ?? 0) !== 1) {
                                $warnungen[] = 'Mitarbeiter-ID existiert, ist aber inaktiv (ID ' . (int)$inactive['id'] . ').';
                            }
                        }
                    }


					// Zusatz (T-069): Mehrdeutigkeits-Check für numerische Codes
					// - In der Praxis können Personalnummern versehentlich mit Mitarbeiter-IDs kollidieren.
					// - Terminal-Logik (B-036): RFID → Personalnummer → ID, ABER:
					//   Wenn RFID nicht passt und sowohl Personalnummer als auch ID auf verschiedene aktive Mitarbeiter zeigen,
					//   wird der Login abgebrochen (kein stilles „falsches Einloggen“).
					$alternativeTreffer = [];
					if (ctype_digit($terminalLoginCode)) {
						$cInt = (int)$terminalLoginCode;

						$byRfid = $fetchOne(
							$pdo,
							'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
							 FROM mitarbeiter
							 WHERE rfid_code = :code AND aktiv = 1
							 LIMIT 1',
							['code' => $terminalLoginCode]
						);

						$byPn = $fetchOne(
							$pdo,
							'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
							 FROM mitarbeiter
							 WHERE personalnummer = :pn AND aktiv = 1
							 LIMIT 1',
							['pn' => $terminalLoginCode]
						);

						$byId = $fetchOne(
							$pdo,
							'SELECT id, vorname, nachname, personalnummer, rfid_code, aktiv
							 FROM mitarbeiter
							 WHERE id = :id AND aktiv = 1
							 LIMIT 1',
							['id' => $cInt]
						);

						// Alternativen sammeln (für Diagnose-Ausgabe)
						$alts = [
							'RFID'          => $byRfid,
							'Personalnummer' => $byPn,
							'Mitarbeiter-ID' => $byId,
						];
						foreach ($alts as $t => $rowAlt) {
							if (!is_array($rowAlt) || !isset($rowAlt['id'])) {
								continue;
							}
							$alternativeTreffer[$t] = $rowAlt;
						}

						// Terminal-Verhalten emulieren: Mehrdeutigkeits-Abbruch nur wenn RFID nicht passt.
						if (!is_array($byRfid) && is_array($byPn) && is_array($byId)) {
							$idPn = (int)($byPn['id'] ?? 0);
							$idId = (int)($byId['id'] ?? 0);
							if ($idPn > 0 && $idId > 0 && $idPn !== $idId) {
								$fehlerCode = 'MEHRDEUTIG';
								$terminalLoginHinweis = 'Mehrdeutiger numerischer Code: Terminal würde den Login abbrechen (Personalnummer vs Mitarbeiter-ID).';
								$match = null;
								$matchTyp = null;
							}
						}

						// Zusatzhinweis: Code kollidiert zwar, Terminal würde aber gemäß Reihenfolge eindeutig wählen.
						if ($fehlerCode === null && $matchTyp !== null && is_array($match) && isset($match['id'])) {
							$primId = (int)$match['id'];
							$teile = [];
							foreach ($alternativeTreffer as $t => $rowAlt) {
								$altId = (int)($rowAlt['id'] ?? 0);
								if ($altId === $primId) {
									continue;
								}
								$name = trim((string)($rowAlt['vorname'] ?? '') . ' ' . (string)($rowAlt['nachname'] ?? ''));
								$teile[] = $t . ' → ID ' . $altId . ($name !== '' ? ' (' . $name . ')' : '');
							}
							if (count($teile) > 0) {
								$warnungen[] = 'Achtung: numerischer Code kollidiert auch mit ' . implode(', ', $teile) . '. Terminal-Reihenfolge: RFID → Personalnummer → ID.';
							}
						}
					}

                    // Zusatz (T-069): Anwesenheit heute (rein lesend)
                    // - hilft beim Debuggen der Terminal-Menülogik (Kommen/Gehen-Buttons).
                    $anwesenheit = null;
                    if ($matchTyp !== null && is_array($match) && isset($match['id'])) {
                        try {
                            $mid = (int)$match['id'];

                            $start = (new DateTimeImmutable('today'))->format('Y-m-d 00:00:00');
                            $ende  = (new DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00');

                            $stmt = $pdo->prepare(
                                "SELECT
                                    SUM(CASE WHEN typ = 'kommen' THEN 1 ELSE 0 END) AS kommen,
                                    SUM(CASE WHEN typ = 'gehen' THEN 1 ELSE 0 END)  AS gehen
                                 FROM zeitbuchung
                                 WHERE mitarbeiter_id = :mid
                                   AND zeitstempel >= :s
                                   AND zeitstempel < :e"
                            );
                            $stmt->execute(['mid' => $mid, 's' => $start, 'e' => $ende]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);

                            $k = is_array($row) && isset($row['kommen']) ? (int)$row['kommen'] : 0;
                            $g = is_array($row) && isset($row['gehen']) ? (int)$row['gehen'] : 0;

                            $letzte = null;
                            $stmt2 = $pdo->prepare(
                                "SELECT typ, zeitstempel, quelle, manuell_geaendert
                                 FROM zeitbuchung
                                 WHERE mitarbeiter_id = :mid
                                 ORDER BY zeitstempel DESC, id DESC
                                 LIMIT 1"
                            );
                            $stmt2->execute(['mid' => $mid]);
                            $r2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                            if (is_array($r2) && isset($r2['typ']) && isset($r2['zeitstempel'])) {
                                $letzte = $r2;
                            }

                            $anwesenheit = [
                                'datum' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                                'kommen' => $k,
                                'gehen' => $g,
                                'ist_anwesend' => ($k > $g),
                                'kommen_erlaubt' => ($k <= $g),
                                'gehen_erlaubt' => ($k > $g),
                                'auftrag_erlaubt' => ($k > $g),
                                'letzte_buchung' => $letzte,
                            ];

                            if ($g > $k) {
                                $warnungen[] = 'Auffälligkeit: Heute mehr "Gehen" als "Kommen" (K=' . $k . ', G=' . $g . ').';
                            }
                        } catch (Throwable $e) {
                            $anwesenheit = [
                                'fehler' => $e->getMessage(),
                            ];
                        }
                    }



					if ($matchTyp === null || !is_array($match) || !isset($match['id'])) {
						if ($fehlerCode !== 'MEHRDEUTIG') {
							$terminalLoginHinweis = 'Kein aktiver Mitarbeiter für diesen Code gefunden.';
						}
                        $terminalLoginErgebnis = [
                            'typ' => null,
                            'mitarbeiter' => null,
                            'warnungen' => $warnungen,
                            'alternativen' => $alternativeTreffer,
                            'anwesenheit' => $anwesenheit,
							'fehler_code' => $fehlerCode,
                        ];
                    } else {
                        $terminalLoginErgebnis = [
                            'typ' => $matchTyp,
                            'mitarbeiter' => $match,
                            'warnungen' => $warnungen,
                            'alternativen' => $alternativeTreffer,
                            'anwesenheit' => $anwesenheit,
							'fehler_code' => $fehlerCode,
                        ];
                    }
                } catch (Throwable $e) {
                    $terminalLoginHinweis = 'DB-Fehler beim Terminal-Login-Check: ' . $e->getMessage();
                }
            }
        }

        // PDF-Quick-Check: PDF wird nur im Speicher erzeugt, ohne Ausgabe/Download.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_test_run'])) {
            $pdfTestJahr = (int)($_POST['pdf_test_jahr'] ?? $pdfTestJahr);
            $pdfTestMonat = (int)($_POST['pdf_test_monat'] ?? $pdfTestMonat);
            $rawMid = trim((string)($_POST['pdf_test_mitarbeiter_id'] ?? ''));
            if ($rawMid !== '') {
                $pdfTestMitarbeiterId = (int)$rawMid;
            }

            if ($pdfTestMitarbeiterId <= 0) {
                $pdfTestHinweis = 'Keine gültige Mitarbeiter-ID für den PDF-Check (auch kein angemeldeter Mitarbeiter gefunden).';
            } elseif ($pdfTestJahr < 2000 || $pdfTestJahr > 2100) {
                $pdfTestHinweis = 'Bitte ein gültiges Jahr (2000–2100) angeben.';
            } elseif ($pdfTestMonat < 1 || $pdfTestMonat > 12) {
                $pdfTestHinweis = 'Bitte einen gültigen Monat (1–12) angeben.';
            } elseif (!class_exists('PDFService')) {
                $pdfTestHinweis = 'PDFService ist nicht verfügbar (Klasse fehlt).';
            } else {
                $pdfInhalt = '';

                $errorHandlerAktiv = false;
                try {
                    set_error_handler(function (int $severity, string $message, string $file, int $line) use ($pdfTestJahr, $pdfTestMonat, $pdfTestMitarbeiterId): bool {
                        if (class_exists('Logger')) {
                            Logger::warn('PHP-Warnung/Notice während SmokeTest-PDF-Quick-Check', [
                                'severity' => $severity,
                                'message'  => $message,
                                'file'     => $file,
                                'line'     => $line,
                                'jahr'     => $pdfTestJahr,
                                'monat'    => $pdfTestMonat,
                                'mitarbeiter_id' => $pdfTestMitarbeiterId,
                            ], null, null, 'smoke_test');
                        }
                        return true; // Ausgabe unterdrücken
                    });
                    $errorHandlerAktiv = true;
                } catch (Throwable $e) {
                    $errorHandlerAktiv = false;
                }

                $obStartLevel = ob_get_level();
                @ob_start();

                try {
                    $pdfService = PDFService::getInstanz();
                    $pdfInhalt = (string)$pdfService->erzeugeMonatsPdfFuerMitarbeiter($pdfTestMitarbeiterId, $pdfTestJahr, $pdfTestMonat);
                } catch (Throwable $e) {
                    $pdfTestHinweis = 'PDF-Fehler beim Erzeugen: ' . $e->getMessage();
                }

                // Buffer leeren (Warnungen/Notices), Handler zurücksetzen
                while (ob_get_level() > $obStartLevel) {
                    @ob_end_clean();
                }
                if ($errorHandlerAktiv) {
                    try {
                        restore_error_handler();
                    } catch (Throwable $e) {
                        // ignore
                    }
                }

                if ($pdfTestHinweis === null) {
                    $bytes = strlen($pdfInhalt);
                    $headerOk = ($bytes >= 5 && substr($pdfInhalt, 0, 5) === '%PDF-');
                    $eofOk = (strpos($pdfInhalt, '%%EOF') !== false);

                    $pageObjCount = substr_count($pdfInhalt, '/Type /Page /Parent');
                    $declaredPages = null;
                    if (preg_match('/\/Type\s*\/Pages\b.*?\/Count\s+(\d+)/s', $pdfInhalt, $m)) {
                        $declaredPages = (int)$m[1];
                    }

                    $pagesMatch = null;
                    if ($declaredPages !== null) {
                        $pagesMatch = ($declaredPages === $pageObjCount);
                    }

                    $footerSeite1 = (strpos($pdfInhalt, '(Seite 1/') !== false);
                    $footerSeite2 = null;
                    if ($pageObjCount >= 2) {
                        $footerSeite2 = (strpos($pdfInhalt, '(Seite 2/') !== false);


                        // T-069 (Fortsetzung): Report-Monatsübersicht HTML-Render-Check (Kandidat)
                        // - Rein lesend.
                        // - Rendert die Monatsübersicht für denselben Kandidaten via ReportController und prüft grob die HTML-Struktur.
                        $reportHtmlOk = null;
                        $reportHtmlHinweis = '';
                        $reportHtmlHasHeading = false;
                        $reportHtmlHasTable = false;
                        $reportHtmlHasHeaderCells = false;
                        $reportHtmlHasPdfLink = false;
                        $reportHtmlTrCount = 0;
                        $reportHtmlDaysInMonth = 0;
                        $reportHtmlRowsMinOk = null;

                        $kannViewAll = false;
                        try {
                            if (method_exists($this->auth, 'hatRecht')) {
                                $kannViewAll = (
                                    $this->auth->hatRecht('REPORT_MONAT_VIEW_ALL')
                                    || $this->auth->hatRecht('REPORTS_ANSEHEN_ALLE')
                                );
                            }
                        } catch (Throwable $e) {
                            $kannViewAll = false;
                        }

                        $angemeldeteIdFuerHtml = (int)($pdfTestMitarbeiterId ?? 0);
                        if (!$kannViewAll && $angemeldeteIdFuerHtml > 0 && $mid !== $angemeldeteIdFuerHtml) {
                            $reportHtmlOk = null;
                            $reportHtmlHinweis = 'SKIP: Kein REPORT_MONAT_VIEW_ALL/REPORTS_ANSEHEN_ALLE Recht für fremde Mitarbeiter.';
                        } else {
                            $backupGet = $_GET;
                            try {
                                if (!class_exists('ReportController')) {
                                    throw new Exception('ReportController fehlt.');
                                }

                                $_GET['mitarbeiter_id'] = (string)$mid;
                                $_GET['seite'] = 'report_monat';

                                $obLevel = ob_get_level();
                                ob_start();
                                $html = '';
                                try {
                                    $rc = new ReportController();
                                    $rc->monatsuebersicht($jahr, $monat);
                                    $html = (string)ob_get_clean();
                                } catch (Throwable $e) {
                                    while (ob_get_level() > $obLevel) {
                                        @ob_end_clean();
                                    }
                                    throw $e;
                                }

                                $reportHtmlDaysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, (int)$monat, (int)$jahr);
                                $reportHtmlHasHeading = (stripos($html, 'Monatsübersicht') !== false);
                                $reportHtmlHasTable = (stripos($html, '<table') !== false);
                                $reportHtmlHasHeaderCells = (
                                    strpos($html, '<th>Datum</th>') !== false
                                    && strpos($html, '<th>An</th>') !== false
                                    && strpos($html, '<th>Ab</th>') !== false
                                );
                                $reportHtmlHasPdfLink = (strpos($html, '?seite=report_monat_pdf') !== false);

                                $mTr = [];
                                $reportHtmlTrCount = (int)preg_match_all('/<tr\b/i', $html, $mTr);

                                // Mindestens: Headerzeile + pro Kalendertag mindestens eine Zeile (Mehrfach-Kommen/Gehen => mehr).
                                $reportHtmlRowsMinOk = ($reportHtmlTrCount >= ($reportHtmlDaysInMonth + 1));

                                $reportHtmlOk = (
                                    $reportHtmlHasHeading
                                    && $reportHtmlHasTable
                                    && $reportHtmlHasHeaderCells
                                    && $reportHtmlHasPdfLink
                                    && $reportHtmlRowsMinOk
                                );

                                if ($reportHtmlOk !== true) {
                                    $reportHtmlHinweis = 'HTML-Struktur unerwartet (Heading/Table/Headers/PDF-Link/Zeilenanzahl prüfen).';
                                }
                            } catch (Throwable $e) {
                                $reportHtmlOk = false;
                                $reportHtmlHinweis = 'HTML-Render-Check fehlgeschlagen: ' . $e->getMessage();
                            } finally {
                                $_GET = $backupGet;
                            }
                        }

                    }

                    $headerArbeitszeitliste = (strpos($pdfInhalt, '(Arbeitszeitliste)') !== false);
                    $headerTagKw = (strpos($pdfInhalt, '(Tag / KW)') !== false);

                    $okErweitert = ($bytes > 0 && $headerOk && $eofOk);
                    if ($pagesMatch === false) {
                        $okErweitert = false;
                    }
                    if (!$footerSeite1) {
                        $okErweitert = false;
                    }
                    if ($pageObjCount >= 2 && $footerSeite2 !== true) {
                        $okErweitert = false;
                    }
                    if (!$headerArbeitszeitliste || !$headerTagKw) {
                        $okErweitert = false;
                    }

                    $pdfTestErgebnis = [
                        'ok' => $okErweitert,
                        'bytes' => $bytes,
                        'header_ok' => $headerOk,
                        'eof_ok' => $eofOk,
                        'pages_count_declared' => $declaredPages,
                        'pages_count_objects' => $pageObjCount,
                        'pages_count_match' => $pagesMatch,
                        'footer_seite1' => $footerSeite1,
                        'footer_seite2' => $footerSeite2,
                        'header_arbeitszeitliste' => $headerArbeitszeitliste,
                        'header_tag_kw' => $headerTagKw,
                    ];

                    // Optional: Tageswerte-Kommentar (Kürzel) im PDF wiederfinden (nur Diagnose)
                    $pdfKommentarSamples = [];
                    $pdfKommentarCheck = [];
                    $pdfKommentarHinweis = null;

                    if ($this->db === null) {
                        $pdfKommentarHinweis = 'Kommentar-Check übersprungen: keine DB-Verbindung im Smoke-Test.';
                    } else {
                        try {
                            $startDt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $pdfTestJahr, $pdfTestMonat));
                            $bisDt   = $startDt->modify('+1 month');

                            $sql = 'SELECT datum, kommentar
                                    FROM tageswerte_mitarbeiter
                                    WHERE mitarbeiter_id = :mid
                                      AND datum >= :von
                                      AND datum < :bis
                                      AND kommentar IS NOT NULL
                                      AND TRIM(kommentar) <> \'\'
                                    ORDER BY datum ASC
                                    LIMIT 10';

                            $rows = $this->db->fetchAlle($sql, [
                                'mid' => $pdfTestMitarbeiterId,
                                'von' => $startDt->format('Y-m-d'),
                                'bis' => $bisDt->format('Y-m-d'),
                            ]);

                            foreach ($rows as $r) {
                                if (!is_array($r)) {
                                    continue;
                                }
                                $d = trim((string)($r['datum'] ?? ''));
                                $k = trim((string)($r['kommentar'] ?? ''));
                                if ($k === '') {
                                    continue;
                                }

                                // Wie im PDF: auf 6 Zeichen kürzen (UTF-8 safe, falls möglich)
                                $short = $k;
                                if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                                    if (mb_strlen($short, 'UTF-8') > 6) {
                                        $short = mb_substr($short, 0, 6, 'UTF-8');
                                    }
                                } else {
                                    if (strlen($short) > 6) {
                                        $short = substr($short, 0, 6);
                                    }
                                }

                                $pdfKommentarSamples[] = [
                                    'datum' => $d,
                                    'kommentar' => $short,
                                ];
                            }
                        } catch (Throwable $e) {
                            $pdfKommentarHinweis = 'Kommentar-Check DB-Fehler: ' . $e->getMessage();
                        }
                    }

                    if ($pdfKommentarSamples !== []) {
                        foreach ($pdfKommentarSamples as $smp) {
                            if (!is_array($smp)) {
                                continue;
                            }
                            $k = (string)($smp['kommentar'] ?? '');
                            $d = (string)($smp['datum'] ?? '');
                            if ($k === '') {
                                continue;
                            }
                            // PDF-Stream enthält Texte als (...). Tj – für Kürzel sollte eine simple Substring-Suche reichen.
                            $found = (strpos($pdfInhalt, '(' . $k . ')') !== false);
                            $pdfKommentarCheck[] = [
                                'datum' => $d,
                                'kommentar' => $k,
                                'found_in_pdf' => $found ? 1 : 0,
                            ];
                        }
                    }

                    $pdfTestErgebnis['kommentar_samples'] = $pdfKommentarSamples;
                    $pdfTestErgebnis['kommentar_check'] = $pdfKommentarCheck;
                    $pdfTestErgebnis['kommentar_hinweis'] = $pdfKommentarHinweis;

                    if ($bytes <= 0) {
                        $pdfTestHinweis = 'PDF-Inhalt ist leer.';
                    } elseif (!$headerOk) {
                        $pdfTestHinweis = 'PDF-Header fehlt (erwartet: %PDF-...).';
                    } elseif (!$eofOk) {
                        $pdfTestHinweis = 'PDF-EOF Marker (%%EOF) fehlt.';
                    } elseif ($pagesMatch === false) {
                        $pdfTestHinweis = 'PDF-Seitenanzahl inkonsistent: /Pages /Count=' . (int)$declaredPages . ' aber Page-Objekte=' . (int)$pageObjCount . '.';
                    } elseif (!$footerSeite1) {
                        $pdfTestHinweis = 'PDF-Footer "Seite 1/..." fehlt im Stream.';
                    } elseif ($pageObjCount >= 2 && $footerSeite2 !== true) {
                        $pdfTestHinweis = 'PDF-Footer "Seite 2/..." fehlt, obwohl mehrere Seiten erkannt wurden.';
                    } elseif (!$headerArbeitszeitliste || !$headerTagKw) {
                        $pdfTestHinweis = 'PDF-Headertexte (Arbeitszeitliste / Tag / KW) wurden im Stream nicht gefunden.';
                    }
                }
            }
        }

        
        // T-069 (Fortsetzung): PDF-Synth-Check (Multi-Block + Multi-Page, DB-unabhängig)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_synth_run'])) {
            $rawJ = trim((string)($_POST['pdf_synth_jahr'] ?? ''));
            $rawM = trim((string)($_POST['pdf_synth_monat'] ?? ''));

            if ($rawJ !== '') {
                $pdfSynthJahr = (int)$rawJ;
            }
            if ($rawM !== '') {
                $pdfSynthMonat = (int)$rawM;
            }

            if ($pdfSynthJahr < 1970 || $pdfSynthJahr > 2100) {
                $pdfSynthHinweis = 'Ungültiges Jahr (1970..2100).';
            } elseif ($pdfSynthMonat < 1 || $pdfSynthMonat > 12) {
                $pdfSynthHinweis = 'Ungültiger Monat (1..12).';
            } elseif (!class_exists('PDFService')) {
                $pdfSynthHinweis = 'PDFService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $startDt = new DateTimeImmutable(sprintf('%04d-%02d-01', $pdfSynthJahr, $pdfSynthMonat));
                    $daysInMonth = (int)$startDt->modify('last day of this month')->format('j');

                    $tageswerte = [];
                    $blocksPerDay = 3;

                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $ymd = sprintf('%04d-%02d-%02d', $pdfSynthJahr, $pdfSynthMonat, $day);

                        $arbeitsbloecke = [
                            [
                                'kommen_roh'  => $ymd . ' 05:30:00',
                                'gehen_roh'   => $ymd . ' 09:00:00',
                                'kommen_korr' => $ymd . ' 05:30:00',
                                'gehen_korr'  => $ymd . ' 09:00:00',
                            ],
                            [
                                'kommen_roh'  => $ymd . ' 09:15:00',
                                'gehen_roh'   => $ymd . ' 12:30:00',
                                'kommen_korr' => $ymd . ' 09:15:00',
                                'gehen_korr'  => $ymd . ' 12:30:00',
                            ],
                            [
                                'kommen_roh'  => $ymd . ' 13:00:00',
                                'gehen_roh'   => $ymd . ' 16:00:00',
                                'kommen_korr' => $ymd . ' 13:00:00',
                                'gehen_korr'  => $ymd . ' 16:00:00',
                            ],
                        ];

                        $tageswerte[] = [
                            'datum' => $ymd,
                            'pausen_stunden' => '0.75',
                            'arbeitszeit_stunden' => '8.00',
                            'arzt_stunden' => '0.00',
                            'krank_lfz_stunden' => '0.00',
                            'krank_kk_stunden' => '0.00',
                            'feiertag_stunden' => '0.00',
                            'kurzarbeit_stunden' => '0.00',
                            'urlaub_stunden' => '0.00',
                            'sonstige_stunden' => '0.00',
                            'kommentar' => ($day === 1 ? 'SoU: SmokeTest' : ''),
                            'zeit_manuell_geaendert' => (($day % 7) === 0 ? 1 : 0),
                            'arbeitsbloecke' => $arbeitsbloecke,
                        ];
                    }

                    $sollstunden = (float)$daysInMonth * 8.0;
                    $monatswerte = [
                        'sollstunden' => number_format($sollstunden, 2, '.', ''),
                    ];

                    $pdfService = PDFService::getInstanz();
                    if (!method_exists($pdfService, 'erzeugeMonatsPdfAusDaten')) {
                        throw new Exception('PDFService::erzeugeMonatsPdfAusDaten() fehlt (ältere Version).');
                    }

                    $pdfInhalt = $pdfService->erzeugeMonatsPdfAusDaten(9999, 'SMOKE TEST', $pdfSynthJahr, $pdfSynthMonat, $tageswerte, $monatswerte);

                    $bytes = (int)strlen($pdfInhalt);
                    $headerOk = ($bytes >= 5 && substr($pdfInhalt, 0, 5) === '%PDF-');
                    $eofOk = (strpos($pdfInhalt, '%%EOF') !== false);

                    $declaredPages = null;
                    if (preg_match('/\/Count\s+(\d+)/', $pdfInhalt, $m) === 1) {
                        $declaredPages = (int)$m[1];
                    }
                    $pageObjCount = 0;
                    if (preg_match_all('/\/Type\s*\/Page\b/', $pdfInhalt, $mm) !== false) {
                        $pageObjCount = is_array($mm[0] ?? null) ? count($mm[0]) : 0;
                    }
                    $pagesMatch = null;
                    if ($declaredPages !== null) {
                        $pagesMatch = ($declaredPages === $pageObjCount);
                    }

                    $footerSeite1 = (strpos($pdfInhalt, '(Seite 1/') !== false);
                    $footerSeite2 = (strpos($pdfInhalt, '(Seite 2/') !== false);

                    $headerArbeitszeitliste = (strpos($pdfInhalt, '(Arbeitszeitliste)') !== false);
                    $headerTagKw = (strpos($pdfInhalt, '(Tag / KW)') !== false);

                    $pagesAtLeast2 = ($pageObjCount >= 2);

                    $okSynth = ($bytes > 0 && $headerOk && $eofOk && $pagesMatch === true && $pagesAtLeast2 && $footerSeite1 && $footerSeite2 && $headerArbeitszeitliste && $headerTagKw);

                    $pdfSynthErgebnis = [
                        'ok' => $okSynth,
                        'bytes' => $bytes,
                        'blocks_per_day' => $blocksPerDay,
                        'days_in_month' => $daysInMonth,
                        'rows_expected' => ($daysInMonth * $blocksPerDay) + 2, // + Header + Abschluss "/"
                        'header_ok' => $headerOk,
                        'eof_ok' => $eofOk,
                        'pages_count_declared' => $declaredPages,
                        'pages_count_objects' => $pageObjCount,
                        'pages_count_match' => $pagesMatch,
                        'pages_at_least2' => $pagesAtLeast2,
                        'footer_seite1' => $footerSeite1,
                        'footer_seite2' => $footerSeite2,
                        'header_arbeitszeitliste' => $headerArbeitszeitliste,
                        'header_tag_kw' => $headerTagKw,
                    ];

                    if ($bytes <= 0) {
                        $pdfSynthHinweis = 'PDF-Inhalt ist leer.';
                    } elseif (!$headerOk) {
                        $pdfSynthHinweis = 'PDF-Header fehlt (erwartet: %PDF-...).';
                    } elseif (!$eofOk) {
                        $pdfSynthHinweis = 'PDF-EOF Marker (%%EOF) fehlt.';
                    } elseif ($pagesAtLeast2 !== true) {
                        $pdfSynthHinweis = 'Erwartet mind. 2 Seiten, erkannt: ' . (int)$pageObjCount . '.';
                    } elseif ($pagesMatch !== true) {
                        $pdfSynthHinweis = 'PDF-Seitenanzahl inkonsistent: /Pages /Count=' . (int)$declaredPages . ' aber Page-Objekte=' . (int)$pageObjCount . '.';
                    } elseif (!$footerSeite1 || !$footerSeite2) {
                        $pdfSynthHinweis = 'PDF-Footer "Seite 1/..." oder "Seite 2/..." fehlt im Stream.';
                    } elseif (!$headerArbeitszeitliste || !$headerTagKw) {
                        $pdfSynthHinweis = 'PDF-Headertexte (Arbeitszeitliste / Tag / KW) wurden im Stream nicht gefunden.';
                    }
                } catch (Throwable $e) {
                    $pdfSynthHinweis = 'PDF-Synth-Check fehlgeschlagen: ' . $e->getMessage();
                }
            }
        }



        // T-069 (Fortsetzung): PDF DB Kandidaten-Liste (Top-N, rein lesend)
        $pdfDbMultiListDoEval = false;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['pdf_db_multipage_list_run']) || isset($_POST['pdf_db_multipage_list_eval']))) {
            $pdfDbMultiListDoEval = isset($_POST['pdf_db_multipage_list_eval']);
            $rawW = trim((string)($_POST['pdf_db_multipage_window'] ?? ''));
            if ($rawW !== '' && ctype_digit($rawW)) {
                $pdfDbMultiWindowMonate = (int)$rawW;
            }

            if ($pdfDbMultiWindowMonate < 1) {
                $pdfDbMultiWindowMonate = 1;
            }
            if ($pdfDbMultiWindowMonate > 24) {
                $pdfDbMultiWindowMonate = 24;
            }

            $rawL = trim((string)($_POST['pdf_db_multipage_list_limit'] ?? ''));
            if ($rawL !== '' && ctype_digit($rawL)) {
                $pdfDbMultiListLimit = (int)$rawL;
            }
            if ($pdfDbMultiListLimit < 1) {
                $pdfDbMultiListLimit = 1;
            }
            if ($pdfDbMultiListLimit > 20) {
                $pdfDbMultiListLimit = 20;
            }

            $postToken = (string)($_POST['csrf_token'] ?? '');
            if ($postToken === '' || !hash_equals((string)$csrfToken, $postToken)) {
                $pdfDbMultiListHinweis = 'CSRF-Token ungültig – Aktion abgebrochen.';
            } elseif ($this->db === null) {
                $pdfDbMultiListHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } else {
                try {
                    $pdo = $this->db->getVerbindung();
                    $window = (int)$pdfDbMultiWindowMonate;
                    $limit = (int)$pdfDbMultiListLimit;

                    $sql = "
                        SELECT mitarbeiter_id, YEAR(zeitstempel) AS jahr, MONTH(zeitstempel) AS monat, COUNT(*) AS c
                        FROM zeitbuchung
                        WHERE typ IN ('kommen','gehen')
                          AND zeitstempel >= DATE_SUB(CURDATE(), INTERVAL {$window} MONTH)
                        GROUP BY mitarbeiter_id, YEAR(zeitstempel), MONTH(zeitstempel)
                        ORDER BY c DESC
                        LIMIT {$limit}
                    ";

                    $stmt = $pdo->query($sql);
                    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

                    if (!is_array($rows) || $rows === []) {
                        $pdfDbMultiListHinweis = 'Keine Kandidaten gefunden (keine Kommen/Gehen-Buchungen in den letzten ' . (int)$window . ' Monaten).';
                    } else {
                        $liste = [];

                        foreach ($rows as $cand) {
                            if (!is_array($cand)) {
                                continue;
                            }

                            $mid = (int)($cand['mitarbeiter_id'] ?? 0);
                            $jahr = (int)($cand['jahr'] ?? 0);
                            $monat = (int)($cand['monat'] ?? 0);
                            $count = (int)($cand['c'] ?? 0);

                            if ($mid <= 0 || $jahr <= 0 || $monat < 1 || $monat > 12) {
                                continue;
                            }

                            $maxDayCount = 0;
                            $maxDayDatum = '';
                            try {
                                $stmt2 = $pdo->prepare(
                                    "SELECT DATE(zeitstempel) AS d, COUNT(*) AS c
                                     FROM zeitbuchung
                                     WHERE mitarbeiter_id = :mid
                                       AND typ IN ('kommen','gehen')
                                       AND YEAR(zeitstempel) = :j
                                       AND MONTH(zeitstempel) = :m
                                     GROUP BY DATE(zeitstempel)
                                     ORDER BY c DESC
                                     LIMIT 1"
                                );
                                $stmt2->execute(['mid' => $mid, 'j' => $jahr, 'm' => $monat]);
                                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                                if (is_array($row2)) {
                                    $maxDayCount = (int)($row2['c'] ?? 0);
                                    $maxDayDatum = (string)($row2['d'] ?? '');
                                }
                            } catch (Throwable $e) {
                                // ignore
                            }

                            $name = 'Mitarbeiter #' . (int)$mid;
                            try {
                                if (class_exists('MitarbeiterModel')) {
                                    $mm = new MitarbeiterModel();
                                    $mrow = $mm->holeNachId($mid);
                                    if (is_array($mrow)) {
                                        $vn = trim((string)($mrow['vorname'] ?? ''));
                                        $nn = trim((string)($mrow['nachname'] ?? ''));
                                        $full = trim($vn . ' ' . $nn);
                                        if ($full !== '') {
                                            $name = $full;
                                        }
                                    }
                                }
                            } catch (Throwable $e) {
                                // ignore
                            }

                            $liste[] = [
                                'mitarbeiter_id' => $mid,
                                'name' => $name,
                                'jahr' => $jahr,
                                'monat' => $monat,
                                'buchungen_kommen_gehen' => $count,
                                'max_day_datum' => $maxDayDatum,
                                'max_day_buchungen' => $maxDayCount,
                                'link_report' => '?seite=report_monat&jahr=' . (int)$jahr . '&monat=' . (int)$monat . '&mitarbeiter_id=' . (int)$mid,
                                'link_pdf' => '?seite=report_monat_pdf&jahr=' . (int)$jahr . '&monat=' . (int)$monat . '&mitarbeiter_id=' . (int)$mid,
                            ];
                        }

                        if ($liste === []) {
                            $pdfDbMultiListHinweis = 'Kandidatenliste ist leer (Filter/Parsing).';
                        } else {
                            if ($pdfDbMultiListDoEval) {
                                if (!class_exists('PDFService')) {
                                    $pdfDbMultiListHinweis = 'Batch-Check nicht möglich: PDFService fehlt.';
                                } else {
                                    $pdfService = PDFService::getInstanz();
                                    $okCount = 0;
                                    $total = is_array($liste) ? count($liste) : 0;

                                    for ($i = 0; $i < $total; $i++) {
                                        $entry = $liste[$i] ?? null;
                                        if (!is_array($entry)) {
                                            continue;
                                        }

                                        $midE = (int)($entry['mitarbeiter_id'] ?? 0);
                                        $jahrE = (int)($entry['jahr'] ?? 0);
                                        $monatE = (int)($entry['monat'] ?? 0);

                                        $evalOk = null;
                                        $evalReason = '';
                                        $pagesObj = 0;
                                        $bytes = 0;

                                        if ($midE > 0 && $jahrE > 0 && $monatE >= 1 && $monatE <= 12) {
                                            try {
                                                $pdf = (string)$pdfService->erzeugeMonatsPdfFuerMitarbeiter($midE, $jahrE, $monatE);
                                                $bytes = strlen($pdf);
                                                $headerOk = ($bytes >= 5 && substr($pdf, 0, 5) === '%PDF-');
                                                $eofOk = (strpos($pdf, '%%EOF') !== false);

                                                $pagesObj = 0;
                                                if (preg_match_all('/\/Type\s*\/Page\b/', $pdf, $mm) !== false) {
                                                    $pagesObj = is_array($mm[0] ?? null) ? count($mm[0]) : 0;
                                                }

                                                $declared = null;
                                                if (preg_match('/\/Type\s*\/Pages\b.*?\/Count\s+(\d+)/s', $pdf, $m2)) {
                                                    $declared = (int)$m2[1];
                                                }

                                                $pagesMatch = null;
                                                if ($declared !== null) {
                                                    $pagesMatch = ($declared === $pagesObj);
                                                }

                                                $footer1 = (strpos($pdf, '(Seite 1/') !== false);
                                                $footer2 = (strpos($pdf, '(Seite 2/') !== false);

                                                $evalOk = ($bytes > 0 && $headerOk && $eofOk && $pagesObj >= 2 && $footer1 && $footer2);
                                                if ($pagesMatch === false) {
                                                    $evalOk = false;
                                                }

                                                if ($evalOk !== true) {
                                                    if ($bytes <= 0) {
                                                        $evalReason = 'leer';
                                                    } elseif (!$headerOk) {
                                                        $evalReason = 'header';
                                                    } elseif (!$eofOk) {
                                                        $evalReason = 'eof';
                                                    } elseif ($pagesObj < 2) {
                                                        $evalReason = 'seiten<' . (int)$pagesObj . '>';
                                                    } elseif ($pagesMatch === false) {
                                                        $evalReason = 'count mismatch';
                                                    } elseif (!$footer1 || !$footer2) {
                                                        $evalReason = 'footer';
                                                    }
                                                }
                                            } catch (Throwable $e) {
                                                $evalOk = false;
                                                $evalReason = 'fehler: ' . $e->getMessage();
                                            }
                                        } else {
                                            $evalOk = false;
                                            $evalReason = 'invalid';
                                        }

                                        $liste[$i]['eval_ok'] = $evalOk;
                                        $liste[$i]['eval_reason'] = $evalReason;
                                        $liste[$i]['eval_pages'] = $pagesObj;
                                        $liste[$i]['eval_bytes'] = $bytes;

                                        if ($evalOk === true) {
                                            $okCount++;
                                        }
                                    }

                                    $prefix = ($pdfDbMultiListHinweis !== null && $pdfDbMultiListHinweis !== '') ? ($pdfDbMultiListHinweis . ' ') : '';
                                    $pdfDbMultiListHinweis = $prefix . 'Batch-Check: ' . (int)$okCount . ' von ' . (int)$total . ' Kandidaten liefern >=2 Seiten.';
                                }
                            }

                            $pdfDbMultiListe = $liste;
                        }
                    }
                } catch (Throwable $e) {
                    $pdfDbMultiListHinweis = 'Kandidatenliste fehlgeschlagen: ' . $e->getMessage();
                }
            }
        }
        // T-069 (Fortsetzung): PDF DB Auto-Multipage-Check (Kandidat-Finder)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_db_multipage_run'])) {
            $rawW = trim((string)($_POST['pdf_db_multipage_window'] ?? ''));
            if ($rawW !== '' && ctype_digit($rawW)) {
                $pdfDbMultiWindowMonate = (int)$rawW;
            }

            if ($pdfDbMultiWindowMonate < 1) {
                $pdfDbMultiWindowMonate = 1;
            }
            if ($pdfDbMultiWindowMonate > 24) {
                $pdfDbMultiWindowMonate = 24;
            }

            $postToken = (string)($_POST['csrf_token'] ?? '');
            if ($postToken === '' || !hash_equals((string)$csrfToken, $postToken)) {
                $pdfDbMultiHinweis = 'CSRF-Token ungültig – Aktion abgebrochen.';
            } elseif ($this->db === null) {
                $pdfDbMultiHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } elseif (!class_exists('PDFService')) {
                $pdfDbMultiHinweis = 'PDFService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $pdo = $this->db->getVerbindung();
                    $window = (int)$pdfDbMultiWindowMonate;

                                        $midSel = (int)($_POST['pdf_db_multipage_mid'] ?? 0);
                    $jahrSel = (int)($_POST['pdf_db_multipage_year'] ?? 0);
                    $monatSel = (int)($_POST['pdf_db_multipage_month'] ?? 0);

                    $mid = 0;
                    $jahr = 0;
                    $monat = 0;
                    $count = 0;
                    $gefundenVia = 'auto';

                    if ($midSel > 0 && $jahrSel > 0 && $monatSel >= 1 && $monatSel <= 12) {
                        $mid = $midSel;
                        $jahr = $jahrSel;
                        $monat = $monatSel;
                        $gefundenVia = 'liste';

                        // Anzahl Kommen/Gehen für die gewählte Kombination
                        try {
                            $stmtC = $pdo->prepare(
                                "SELECT COUNT(*) AS c
                                 FROM zeitbuchung
                                 WHERE mitarbeiter_id = :mid
                                   AND typ IN ('kommen','gehen')
                                   AND YEAR(zeitstempel) = :j
                                   AND MONTH(zeitstempel) = :m"
                            );
                            $stmtC->execute(['mid' => $mid, 'j' => $jahr, 'm' => $monat]);
                            $rowC = $stmtC->fetch(PDO::FETCH_ASSOC);
                            if (is_array($rowC)) {
                                $count = (int)($rowC['c'] ?? 0);
                            }
                        } catch (Throwable $e) {
                            // ignore
                        }
                    } else {
                        $sql = "
                            SELECT mitarbeiter_id, YEAR(zeitstempel) AS jahr, MONTH(zeitstempel) AS monat, COUNT(*) AS c
                            FROM zeitbuchung
                            WHERE typ IN ('kommen','gehen')
                              AND zeitstempel >= DATE_SUB(CURDATE(), INTERVAL {$window} MONTH)
                            GROUP BY mitarbeiter_id, YEAR(zeitstempel), MONTH(zeitstempel)
                            ORDER BY c DESC
                            LIMIT 1
                        ";

                        $stmt = $pdo->query($sql);
                        $cand = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

                        if (!is_array($cand) || (int)($cand['mitarbeiter_id'] ?? 0) <= 0) {
                            $pdfDbMultiHinweis = 'Kein Kandidat gefunden (keine Kommen/Gehen-Buchungen in den letzten ' . (int)$window . ' Monaten).';
                        } else {
                            $mid = (int)($cand['mitarbeiter_id'] ?? 0);
                            $jahr = (int)($cand['jahr'] ?? 0);
                            $monat = (int)($cand['monat'] ?? 0);
                            $count = (int)($cand['c'] ?? 0);
                        }
                    }

                    if ($mid <= 0 || $jahr <= 0 || $monat < 1 || $monat > 12) {
                        // Hinweis wurde bereits gesetzt (Auto) oder wir haben keine valide Auswahl.
                    } else {
// Max. Buchungen an einem Tag (Indikator für Mehrfach-Kommen/Gehen)
                        $maxDayCount = 0;
                        $maxDayDatum = '';
                        try {
                            $stmt2 = $pdo->prepare(
                                "SELECT DATE(zeitstempel) AS d, COUNT(*) AS c\n                                 FROM zeitbuchung\n                                 WHERE mitarbeiter_id = :mid\n                                   AND typ IN ('kommen','gehen')\n                                   AND YEAR(zeitstempel) = :j\n                                   AND MONTH(zeitstempel) = :m\n                                 GROUP BY DATE(zeitstempel)\n                                 ORDER BY c DESC\n                                 LIMIT 1"
                            );
                            $stmt2->execute(['mid' => $mid, 'j' => $jahr, 'm' => $monat]);
                            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                            if (is_array($row2)) {
                                $maxDayCount = (int)($row2['c'] ?? 0);
                                $maxDayDatum = (string)($row2['d'] ?? '');
                            }
                        } catch (Throwable $e) {
                            // ignore
                        }

                        // Name (optional)
                        $name = 'Mitarbeiter #' . (int)$mid;
                        try {
                            if (class_exists('MitarbeiterModel')) {
                                $mm = new MitarbeiterModel();
                                $mrow = $mm->holeNachId($mid);
                                if (is_array($mrow)) {
                                    $vn = trim((string)($mrow['vorname'] ?? ''));
                                    $nn = trim((string)($mrow['nachname'] ?? ''));
                                    $full = trim($vn . ' ' . $nn);
                                    if ($full !== '') {
                                        $name = $full;
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            // ignore
                        }

                        $pdfService = PDFService::getInstanz();
                        $pdfInhalt = (string)$pdfService->erzeugeMonatsPdfFuerMitarbeiter($mid, $jahr, $monat);

                        $bytes = strlen($pdfInhalt);
                        $headerOk = ($bytes >= 5 && substr($pdfInhalt, 0, 5) === '%PDF-');
                        $eofOk = (strpos($pdfInhalt, '%%EOF') !== false);

                        $pageObjCount = substr_count($pdfInhalt, '/Type /Page /Parent');
                        $declaredPages = null;
                        if (preg_match('/\\/Type\\s*\\/Pages\\b.*?\\/Count\\s+(\\d+)/s', $pdfInhalt, $m)) {
                            $declaredPages = (int)$m[1];
                        }

                        $pagesMatch = null;
                        if ($declaredPages !== null) {
                            $pagesMatch = ($declaredPages === $pageObjCount);
                        }

                        $pagesAtLeast2 = ($pageObjCount >= 2);

                        $footerSeite1 = (strpos($pdfInhalt, '(Seite 1/') !== false);
                        $footerSeite2 = (strpos($pdfInhalt, '(Seite 2/') !== false);

                        $ok = ($bytes > 0 && $headerOk && $eofOk && $pagesAtLeast2);
                        if ($pagesMatch === false) {
                            $ok = false;
                        }
                        if (!$footerSeite1 || !$footerSeite2) {
                            $ok = false;
                        }
                        if ($reportHtmlOk === false) {
                            $ok = false;
                        }

                        $pdfDbMultiErgebnis = [
                            'ok' => $ok,
                            'window_monate' => $window,
                            'gefunden_via' => $gefundenVia,
                            'mitarbeiter_id' => $mid,
                            'name' => $name,
                            'jahr' => $jahr,
                            'monat' => $monat,
                            'buchungen_kommen_gehen' => $count,
                            'max_day_datum' => $maxDayDatum,
                            'max_day_buchungen' => $maxDayCount,
                            'pdf_bytes' => $bytes,
                            'header_ok' => $headerOk,
                            'eof_ok' => $eofOk,
                            'pages_count_declared' => $declaredPages,
                            'pages_count_objects' => $pageObjCount,
                            'pages_count_match' => $pagesMatch,
                            'pages_at_least2' => $pagesAtLeast2,
                            'footer_seite1' => $footerSeite1,
                            'footer_seite2' => $footerSeite2,
                            'html_ok' => $reportHtmlOk,
                            'html_hinweis' => $reportHtmlHinweis,
                            'html_has_heading' => $reportHtmlHasHeading,
                            'html_has_table' => $reportHtmlHasTable,
                            'html_has_header_cells' => $reportHtmlHasHeaderCells,
                            'html_has_pdf_link' => $reportHtmlHasPdfLink,
                            'html_tr_count' => $reportHtmlTrCount,
                            'html_days_in_month' => $reportHtmlDaysInMonth,
                            'html_rows_min_ok' => $reportHtmlRowsMinOk,
                            'link_report' => '?seite=report_monat&jahr=' . (int)$jahr . '&monat=' . (int)$monat . '&mitarbeiter_id=' . (int)$mid,
                            'link_pdf' => '?seite=report_monat_pdf&jahr=' . (int)$jahr . '&monat=' . (int)$monat . '&mitarbeiter_id=' . (int)$mid,
                        ];

                        if ($bytes <= 0) {
                            $pdfDbMultiHinweis = 'PDF-Inhalt ist leer.';
                        } elseif (!$headerOk) {
                            $pdfDbMultiHinweis = 'PDF-Header fehlt (erwartet: %PDF-...).';
                        } elseif (!$eofOk) {
                            $pdfDbMultiHinweis = 'PDF-EOF Marker (%%EOF) fehlt.';
                        } elseif ($pagesAtLeast2 !== true) {
                            $pdfDbMultiHinweis = 'Erwartet mind. 2 Seiten, erkannt: ' . (int)$pageObjCount . '.';
                        } elseif ($pagesMatch === false) {
                            $pdfDbMultiHinweis = 'PDF-Seitenanzahl inkonsistent: /Pages /Count=' . (int)$declaredPages . ' aber Page-Objekte=' . (int)$pageObjCount . '.';
                        } elseif (!$footerSeite1 || !$footerSeite2) {
                            $pdfDbMultiHinweis = 'PDF-Footer "Seite 1/..." oder "Seite 2/..." fehlt im Stream.';
                        }

                        if (($pdfDbMultiHinweis === null || $pdfDbMultiHinweis === '') && $reportHtmlOk === false) {
                            $pdfDbMultiHinweis = $reportHtmlHinweis;
                        }
                    }
                } catch (Throwable $e) {
                    $pdfDbMultiHinweis = 'PDF-DB-Multipage-Check fehlgeschlagen: ' . $e->getMessage();
                }
            }
        }

// Feiertag-Quick-Check: Monatsreport-Datum prüfen
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feiertag_test_run'])) {
            $feiertagTestDatum = trim((string)($_POST['feiertag_test_datum'] ?? $feiertagTestDatum));
            $rawMid = trim((string)($_POST['feiertag_test_mitarbeiter_id'] ?? ''));
            if ($rawMid !== '') {
                $feiertagTestMitarbeiterId = (int)$rawMid;
            }

            if ($feiertagTestMitarbeiterId <= 0) {
                $feiertagTestHinweis = 'Keine gültige Mitarbeiter-ID für den Feiertag-Check (auch kein angemeldeter Mitarbeiter gefunden).';
            } else {
                // Datum robust parsen
                try {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $feiertagTestDatum)) {
                        throw new Exception('Datum bitte im Format YYYY-MM-DD angeben.');
                    }
                    $dt = new DateTimeImmutable($feiertagTestDatum);
                } catch (Throwable $e) {
                    $feiertagTestHinweis = 'Ungültiges Datum: ' . $e->getMessage();
                    $dt = null;
                }

                if ($feiertagTestHinweis === null && $dt instanceof DateTimeImmutable) {
                    if (!class_exists('ReportService')) {
                        $feiertagTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
                    } else {
                        $jahr = (int)$dt->format('Y');
                        $monat = (int)$dt->format('n');
                        $wochentag = (int)$dt->format('N');

                        $istFeiertag = null;
                        try {
                            if (class_exists('FeiertagService')) {
                                $fs = FeiertagService::getInstanz();
                                $istFeiertag = $fs->istFeiertag($dt, null);
                            }
                        } catch (Throwable $e) {
                            $istFeiertag = null;
                        }

                        try {
                            $rs = ReportService::getInstanz();
                            $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($feiertagTestMitarbeiterId, $jahr, $monat);
                            $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];

                            $row = null;
                            if (is_array($tageswerte)) {
                                foreach ($tageswerte as $tw) {
                                    if (!is_array($tw)) {
                                        continue;
                                    }
                                    if ((string)($tw['datum'] ?? '') === $dt->format('Y-m-d')) {
                                        $row = $tw;
                                        break;
                                    }
                                }
                            }

                            if (!is_array($row)) {
                                $feiertagTestHinweis = 'Kein Tageswert für das Datum im Monatsreport gefunden.';
                            } else {
                                $arbeits = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
                                $feier = (float)str_replace(',', '.', (string)($row['feiertag_stunden'] ?? '0'));
                                $kennF = (int)($row['kennzeichen_feiertag'] ?? 0);
                                $tagestyp = (string)($row['tagestyp'] ?? '');
                                $kommentar = (string)($row['kommentar'] ?? '');

                                $hatArbeit = ($arbeits > 0.01);
                                $ok = null;
                                $erwartung = '';

                                if ($istFeiertag === true && $wochentag < 6) {
                                    if ($hatArbeit) {
                                        // Wenn gearbeitet wurde, erwarten wir zumindest das Kennzeichen.
                                        $erwartung = 'Feiertag erkannt; bei Arbeitszeit > 0 werden Feiertagsstunden ggf. 0 gelassen.';
                                        $ok = ($kennF === 1);
                                    } else {
                                        $erwartung = 'Feiertag erkannt und ohne Arbeit: Feiertagsstunden > 0 und Kennzeichen gesetzt.';
                                        $ok = ($kennF === 1 && $feier > 0.01);
                                    }
                                } elseif ($istFeiertag === true && $wochentag >= 6) {
                                    $erwartung = 'Datum ist Feiertag, aber Wochenende: je nach Regel kann Feiertagsstunden 0 bleiben.';
                                    $ok = null;
                                } elseif ($istFeiertag === false) {
                                    $erwartung = 'Datum ist laut FeiertagService kein Feiertag.';
                                    $ok = null;
                                } else {
                                    $erwartung = 'FeiertagService nicht verfügbar oder Fehler.';
                                    $ok = null;
                                }

                                $feiertagTestErgebnis = [
                                    'ok' => $ok,
                                    'datum' => $dt->format('Y-m-d'),
                                    'wochentag' => $wochentag,
                                    'ist_feiertag' => $istFeiertag,
                                    'kennzeichen_feiertag' => $kennF,
                                    'arbeitszeit_stunden' => $arbeits,
                                    'feiertag_stunden' => $feier,
                                    'tagestyp' => $tagestyp,
                                    'kommentar' => $kommentar,
                                    'erwartung' => $erwartung,
                                ];
                            }
                        } catch (Throwable $e) {
                            $feiertagTestHinweis = 'Feiertag-Check Fehler (Report): ' . $e->getMessage();
                        }
                    }
                }
            }
        }

        // Monatsreport-Raster-Check: Vollständigkeit (Anzahl Tage + Datumslücken) prüfen
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monatsraster_test_run'])) {
            $rawMid = trim((string)($_POST['monatsraster_test_mitarbeiter_id'] ?? ''));
            $rawJ = trim((string)($_POST['monatsraster_test_jahr'] ?? ''));
            $rawM = trim((string)($_POST['monatsraster_test_monat'] ?? ''));

            if ($rawMid !== '') {
                $monatsrasterTestMitarbeiterId = (int)$rawMid;
            }
            if ($rawJ !== '') {
                $monatsrasterTestJahr = (int)$rawJ;
            }
            if ($rawM !== '') {
                $monatsrasterTestMonat = (int)$rawM;
            }

            if ($monatsrasterTestMitarbeiterId <= 0) {
                $monatsrasterTestHinweis = 'Keine gültige Mitarbeiter-ID für den Monatsreport-Raster-Check.';
            } elseif ($monatsrasterTestMonat < 1 || $monatsrasterTestMonat > 12) {
                $monatsrasterTestHinweis = 'Monat muss 1..12 sein.';
            } elseif ($monatsrasterTestJahr < 1970 || $monatsrasterTestJahr > 2100) {
                $monatsrasterTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
            } elseif (!class_exists('ReportService')) {
                $monatsrasterTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $monatsrasterTestJahr, $monatsrasterTestMonat));
                    $tageImMonat = (int)$start->format('t');

                    $rs = ReportService::getInstanz();
                    $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($monatsrasterTestMitarbeiterId, $monatsrasterTestJahr, $monatsrasterTestMonat);
                    $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];

                    if (!is_array($tageswerte)) {
                        $tageswerte = [];
                    }

                    $seen = [];
                    $invalid = [];
                    $dups = [];

                    foreach ($tageswerte as $tw) {
                        if (!is_array($tw)) {
                            continue;
                        }
                        $d = (string)($tw['datum'] ?? '');
                        if ($d === '') {
                            $invalid[] = '(leer)';
                            continue;
                        }
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                            $invalid[] = $d;
                            continue;
                        }
                        if (isset($seen[$d])) {
                            $dups[$d] = ($dups[$d] ?? 1) + 1;
                        }
                        $seen[$d] = true;
                    }

                    $missing = [];
                    for ($i = 1; $i <= $tageImMonat; $i++) {
                        $d = sprintf('%04d-%02d-%02d', $monatsrasterTestJahr, $monatsrasterTestMonat, $i);
                        if (!isset($seen[$d])) {
                            $missing[] = $d;
                        }
                    }

                    $vorhandenCount = count($tageswerte);
                    $ok = ($vorhandenCount === $tageImMonat && $missing === [] && $dups === [] && $invalid === []);

                    $dupList = [];
                    foreach ($dups as $d => $c) {
                        $dupList[] = (string)$d . ' (' . (int)$c . 'x)';
                    }

                    $monatsrasterTestErgebnis = [
                        'ok' => $ok,
                        'mitarbeiter_id' => $monatsrasterTestMitarbeiterId,
                        'jahr' => $monatsrasterTestJahr,
                        'monat' => $monatsrasterTestMonat,
                        'tage_im_monat' => $tageImMonat,
                        'tageswerte_count' => $vorhandenCount,
                        'missing' => array_slice($missing, 0, 10),
                        'missing_count' => count($missing),
                        'duplicates' => array_slice($dupList, 0, 10),
                        'duplicates_count' => count($dups),
                        'invalid' => array_slice($invalid, 0, 10),
                        'invalid_count' => count($invalid),
                    ];
                } catch (Throwable $e) {
                    $monatsrasterTestHinweis = 'Monatsreport-Raster-Check Fehler: ' . $e->getMessage();
                }
            }
        }



        // Monatsreport-Fallback-Check (lueckenhafte Tageswerte):
        // - Sucht Tage mit Zeitbuchungen, aber ohne passenden Datensatz in `tageswerte_mitarbeiter`.
        // - Prüft anschließend, ob der Monatsreport diese Tage per Fallback (aus Zeitbuchungen) sinnvoll befüllt.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monatsfallback_test_run'])) {
            $rawMid = trim((string)($_POST['monatsfallback_test_mitarbeiter_id'] ?? ''));
            $rawJ = trim((string)($_POST['monatsfallback_test_jahr'] ?? ''));
            $rawM = trim((string)($_POST['monatsfallback_test_monat'] ?? ''));

            if ($rawMid !== '') {
                $monatsfallbackTestMitarbeiterId = (int)$rawMid;
            }
            if ($rawJ !== '') {
                $monatsfallbackTestJahr = (int)$rawJ;
            }
            if ($rawM !== '') {
                $monatsfallbackTestMonat = (int)$rawM;
            }

            if ($monatsfallbackTestMitarbeiterId <= 0) {
                $monatsfallbackTestHinweis = 'Keine gültige Mitarbeiter-ID für den Monatsreport-Fallback-Check.';
            } elseif ($monatsfallbackTestMonat < 1 || $monatsfallbackTestMonat > 12) {
                $monatsfallbackTestHinweis = 'Monat muss 1..12 sein.';
            } elseif ($monatsfallbackTestJahr < 1970 || $monatsfallbackTestJahr > 2100) {
                $monatsfallbackTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
            } elseif ($this->db === null) {
                $monatsfallbackTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } elseif (!class_exists('ReportService')) {
                $monatsfallbackTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $monatsfallbackTestJahr, $monatsfallbackTestMonat));
                    $bis = $start->modify('+1 month');

                    // 1) Tage mit Buchungen ermitteln (inkl. Buchungsanzahl)
                    $bookedMap = []; // datum => count
                    $rowsB = $this->db->fetchAlle(
                        'SELECT DATE(zeitstempel) AS datum, COUNT(*) AS c
'
                        . 'FROM zeitbuchung
'
                        . 'WHERE mitarbeiter_id = :mid AND zeitstempel >= :von AND zeitstempel < :bis
'
                        . 'GROUP BY DATE(zeitstempel)
'
                        . 'ORDER BY datum ASC',
                        [
                            'mid' => $monatsfallbackTestMitarbeiterId,
                            'von' => $start->format('Y-m-d H:i:s'),
                            'bis' => $bis->format('Y-m-d H:i:s'),
                        ]
                    );
                    if (is_array($rowsB)) {
                        foreach ($rowsB as $r) {
                            if (!is_array($r)) {
                                continue;
                            }
                            $d = (string)($r['datum'] ?? '');
                            if ($d === '') {
                                continue;
                            }
                            $bookedMap[$d] = (int)($r['c'] ?? 0);
                        }
                    }

                    // 2) Tage mit Tageswerten ermitteln
                    $twSet = [];
                    $rowsTw = $this->db->fetchAlle(
                        'SELECT datum
'
                        . 'FROM tageswerte_mitarbeiter
'
                        . 'WHERE mitarbeiter_id = :mid AND datum >= :von AND datum < :bis
'
                        . 'ORDER BY datum ASC',
                        [
                            'mid' => $monatsfallbackTestMitarbeiterId,
                            'von' => $start->format('Y-m-d'),
                            'bis' => $bis->format('Y-m-d'),
                        ]
                    );
                    if (is_array($rowsTw)) {
                        foreach ($rowsTw as $r) {
                            if (!is_array($r)) {
                                continue;
                            }
                            $d = (string)($r['datum'] ?? '');
                            if ($d === '') {
                                continue;
                            }
                            $twSet[$d] = true;
                        }
                    }

                    $bookedDays = array_keys($bookedMap);
                    $tageswerteDaysCount = count($twSet);

                    $missingDays = [];
                    foreach ($bookedDays as $d) {
                        if (!isset($twSet[$d])) {
                            $missingDays[] = $d;
                        }
                    }

                    // 3) Monatsreport laden und prüfen, ob Missing-Days per Fallback befüllt sind
                    $rs = ReportService::getInstanz();
                    $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($monatsfallbackTestMitarbeiterId, $monatsfallbackTestJahr, $monatsfallbackTestMonat);
                    $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];
                    if (!is_array($tageswerte)) {
                        $tageswerte = [];
                    }

                    $index = [];
                    foreach ($tageswerte as $tw) {
                        if (!is_array($tw)) {
                            continue;
                        }
                        $d = (string)($tw['datum'] ?? '');
                        if ($d === '') {
                            continue;
                        }
                        $index[$d] = $tw;
                    }

                    $notCovered = [];
                    $samples = [];

                    foreach ($missingDays as $d) {
                        $row = $index[$d] ?? null;
                        $covered = false;

                        $kommen = '';
                        $gehen = '';
                        $az = 0.0;
                        $pz = 0.0;

                        if (is_array($row)) {
                            $kommen = trim((string)($row['kommen_roh'] ?? ''));
                            $gehen = trim((string)($row['gehen_roh'] ?? ''));
                            $az = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
                            $pz = (float)str_replace(',', '.', (string)($row['pausen_stunden'] ?? '0'));

                            $covered = ($kommen !== '' || $gehen !== '' || $az > 0.01 || $pz > 0.01);
                        }

                        if (!$covered) {
                            $notCovered[] = $d;
                        }

                        if (count($samples) < 8) {
                            $samples[] = [
                                'datum' => $d,
                                'buchungen' => (int)($bookedMap[$d] ?? 0),
                                'kommen_roh' => $kommen,
                                'gehen_roh' => $gehen,
                                'arbeitszeit_stunden' => $az,
                                'pausen_stunden' => $pz,
                                'covered' => $covered ? 1 : 0,
                            ];
                        }
                    }

                    $missingCount = count($missingDays);
                    $notCoveredCount = count($notCovered);

                    $ok = true;
                    if ($missingCount > 0) {
                        $ok = ($notCoveredCount === 0);
                    }

                    if ($missingCount === 0) {
                        $monatsfallbackTestHinweis = 'Keine Tage mit Buchungen ohne Tageswerte gefunden – Fallback wird in diesem Monat nicht benötigt.';
                    }

                    $monatsfallbackTestErgebnis = [
                        'ok' => $ok,
                        'mitarbeiter_id' => $monatsfallbackTestMitarbeiterId,
                        'jahr' => $monatsfallbackTestJahr,
                        'monat' => $monatsfallbackTestMonat,
                        'booked_days_count' => count($bookedDays),
                        'tageswerte_days_count' => $tageswerteDaysCount,
                        'missing_days_count' => $missingCount,
                        'not_covered_count' => $notCoveredCount,
                        'missing_days_sample' => array_slice($missingDays, 0, 10),
                        'not_covered_sample' => array_slice($notCovered, 0, 10),
                        'samples' => $samples,
                    ];
                } catch (Throwable $e) {
                    $monatsfallbackTestHinweis = 'Monatsreport-Fallback-Check Fehler: ' . $e->getMessage();
                }
            }
        }

        // Doppelzählung-Check (Betriebsferien/Kurzarbeit Volltag):
        // - Prüft im Monatsreport, ob Betriebsferien-Urlaub (8h) oder Kurzarbeit-Volltag nicht zusätzlich gezählt werden,
        //   wenn an diesem Tag Arbeitszeit vorhanden ist.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doppelzaehlung_test_run'])) {
            $rawMid = trim((string)($_POST['doppelzaehlung_test_mitarbeiter_id'] ?? ''));
            $rawJ = trim((string)($_POST['doppelzaehlung_test_jahr'] ?? ''));
            $rawM = trim((string)($_POST['doppelzaehlung_test_monat'] ?? ''));

            if ($rawMid !== '') {
                $doppelzaehlungTestMitarbeiterId = (int)$rawMid;
            }
            if ($rawJ !== '') {
                $doppelzaehlungTestJahr = (int)$rawJ;
            }
            if ($rawM !== '') {
                $doppelzaehlungTestMonat = (int)$rawM;
            }

            if ($doppelzaehlungTestMitarbeiterId <= 0) {
                $doppelzaehlungTestHinweis = 'Keine gültige Mitarbeiter-ID für den Doppelzählung-Check.';
            } elseif ($doppelzaehlungTestMonat < 1 || $doppelzaehlungTestMonat > 12) {
                $doppelzaehlungTestHinweis = 'Monat muss 1..12 sein.';
            } elseif ($doppelzaehlungTestJahr < 1970 || $doppelzaehlungTestJahr > 2100) {
                $doppelzaehlungTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
            } elseif ($this->db === null) {
                $doppelzaehlungTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } elseif (!class_exists('ReportService')) {
                $doppelzaehlungTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $parseFloat = static function ($v): float {
                        if ($v === null) {
                            return 0.0;
                        }
                        $s = trim((string)$v);
                        if ($s === '') {
                            return 0.0;
                        }
                        $s = str_replace(',', '.', $s);
                        return (float)$s;
                    };

                    $rs = ReportService::getInstanz();
                    $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($doppelzaehlungTestMitarbeiterId, $doppelzaehlungTestJahr, $doppelzaehlungTestMonat);
                    $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];
                    if (!is_array($tageswerte)) {
                        $tageswerte = [];
                    }

                    $volltagSchwelle = 7.99; // 8h-Fallback/Volltag (Toleranz)

                    $totalBetriebsferien = 0;
                    $totalKurzVolltag = 0;

                    $issues = [];

                    foreach ($tageswerte as $tw) {
                        if (!is_array($tw)) {
                            continue;
                        }
                        $datum = (string)($tw['datum'] ?? '');
                        if ($datum === '') {
                            continue;
                        }

                        $arb = $parseFloat($tw['arbeitszeit_stunden'] ?? '0');
                        $urlaub = $parseFloat($tw['urlaub_stunden'] ?? '0');
                        $kurz = $parseFloat($tw['kurzarbeit_stunden'] ?? '0');

                        $istBf = ((bool)($tw['ist_betriebsferien'] ?? false) === true);
                        $kennKurz = (int)($tw['kennzeichen_kurzarbeit'] ?? 0);

                        if ($istBf) {
                            $totalBetriebsferien++;
                        }
                        if ($kennKurz === 1 && $kurz >= $volltagSchwelle) {
                            $totalKurzVolltag++;
                        }

                        if ($arb > 0.01) {
                            if ($istBf && $urlaub > 0.01) {
                                $issues[] = [
                                    'datum' => $datum,
                                    'typ' => 'Betriebsferien',
                                    'arbeitszeit' => $arb,
                                    'urlaub' => $urlaub,
                                    'kurzarbeit' => $kurz,
                                    'hinweis' => 'Arbeitszeit > 0, aber Betriebsferien-Urlaub > 0 (Doppelzählung möglich)',
                                ];
                            }

                            if ($kennKurz === 1 && $kurz >= $volltagSchwelle) {
                                $issues[] = [
                                    'datum' => $datum,
                                    'typ' => 'Kurzarbeit-Volltag',
                                    'arbeitszeit' => $arb,
                                    'urlaub' => $urlaub,
                                    'kurzarbeit' => $kurz,
                                    'hinweis' => 'Arbeitszeit > 0, aber Kurzarbeit-Volltag aktiv (Doppelzählung möglich)',
                                ];
                            }
                        }
                    }

                    $ok = true;
                    if ($totalBetriebsferien <= 0 && $totalKurzVolltag <= 0) {
                        $ok = null;
                        $doppelzaehlungTestHinweis = 'Keine Betriebsferien- oder Kurzarbeit-Volltag-Tage im Monatsreport gefunden – Check ist in diesem Monat nicht aussagekräftig.';
                    } else {
                        $ok = (count($issues) === 0);
                    }

                    $doppelzaehlungTestErgebnis = [
                        'ok' => $ok,
                        'mitarbeiter_id' => $doppelzaehlungTestMitarbeiterId,
                        'jahr' => $doppelzaehlungTestJahr,
                        'monat' => $doppelzaehlungTestMonat,
                        'betriebsferien_tage' => $totalBetriebsferien,
                        'kurzarbeit_volltag_tage' => $totalKurzVolltag,
                        'issues_count' => count($issues),
                        'issues' => array_slice($issues, 0, 12),
                    ];
                } catch (Throwable $e) {
                    $doppelzaehlungTestHinweis = 'Doppelzählung-Check Fehler: ' . $e->getMessage();
                }
            }
        }



        // Feiertag+Arbeitszeit Doppelzählung-Check (Monat): Feiertagsstunden dürfen bei Arbeitszeit nicht zusätzlich zählen.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feiertag_arbeitszeit_test_run'])) {
            $rawMid = trim((string)($_POST['feiertag_arbeitszeit_test_mitarbeiter_id'] ?? ''));
            $rawJ = trim((string)($_POST['feiertag_arbeitszeit_test_jahr'] ?? ''));
            $rawM = trim((string)($_POST['feiertag_arbeitszeit_test_monat'] ?? ''));

            if ($rawMid !== '') {
                $feiertagArbeitszeitTestMitarbeiterId = (int)$rawMid;
            }
            if ($rawJ !== '') {
                $feiertagArbeitszeitTestJahr = (int)$rawJ;
            }
            if ($rawM !== '') {
                $feiertagArbeitszeitTestMonat = (int)$rawM;
            }

            if ($feiertagArbeitszeitTestMitarbeiterId <= 0) {
                $feiertagArbeitszeitTestHinweis = 'Keine gültige Mitarbeiter-ID für den Feiertag+Arbeitszeit-Check.';
            } elseif ($feiertagArbeitszeitTestMonat < 1 || $feiertagArbeitszeitTestMonat > 12) {
                $feiertagArbeitszeitTestHinweis = 'Monat muss 1..12 sein.';
            } elseif ($feiertagArbeitszeitTestJahr < 1970 || $feiertagArbeitszeitTestJahr > 2100) {
                $feiertagArbeitszeitTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
            } elseif ($this->db === null) {
                $feiertagArbeitszeitTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } elseif (!class_exists('ReportService')) {
                $feiertagArbeitszeitTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $parseFloat = static function ($v): float {
                        if ($v === null) {
                            return 0.0;
                        }
                        $s = trim((string)$v);
                        if ($s === '') {
                            return 0.0;
                        }
                        $s = str_replace(',', '.', $s);
                        return (float)$s;
                    };

                    $rs = ReportService::getInstanz();
                    $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($feiertagArbeitszeitTestMitarbeiterId, $feiertagArbeitszeitTestJahr, $feiertagArbeitszeitTestMonat);
                    $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];
                    if (!is_array($tageswerte)) {
                        $tageswerte = [];
                    }

                    $totalFeiertage = 0;
                    $issues = [];

                    foreach ($tageswerte as $tw) {
                        if (!is_array($tw)) {
                            continue;
                        }
                        $datum = (string)($tw['datum'] ?? '');
                        if ($datum === '') {
                            continue;
                        }

                        $kennFeiertag = (int)($tw['kennzeichen_feiertag'] ?? 0);
                        if ($kennFeiertag !== 1) {
                            continue;
                        }
                        $totalFeiertage++;

                        $arb = $parseFloat($tw['arbeitszeit_stunden'] ?? '0');
                        $ft = $parseFloat($tw['feiertag_stunden'] ?? '0');

                        if ($arb > 0.01 && $ft > 0.01) {
                            $issues[] = [
                                'datum' => $datum,
                                'arbeitszeit' => $arb,
                                'feiertag' => $ft,
                                'hinweis' => 'Arbeitszeit > 0, aber Feiertagsstunden > 0 (Doppelzählung möglich)',
                            ];
                        }
                    }

                    $ok = true;
                    if ($totalFeiertage <= 0) {
                        $ok = null;
                        $feiertagArbeitszeitTestHinweis = 'Keine Feiertage im Monatsreport gefunden – Check ist in diesem Monat nicht aussagekräftig.';
                    } else {
                        $ok = (count($issues) === 0);
                    }

                    $feiertagArbeitszeitTestErgebnis = [
                        'ok' => $ok,
                        'mitarbeiter_id' => $feiertagArbeitszeitTestMitarbeiterId,
                        'jahr' => $feiertagArbeitszeitTestJahr,
                        'monat' => $feiertagArbeitszeitTestMonat,
                        'feiertag_tage' => $totalFeiertage,
                        'issues_count' => count($issues),
                        'issues' => array_slice($issues, 0, 12),
                    ];
                } catch (Throwable $e) {
                    $feiertagArbeitszeitTestHinweis = 'Feiertag+Arbeitszeit-Check Fehler: ' . $e->getMessage();
                }
            }
        }
        // Kommen/Gehen-Sequenz-Check (Monat): Zeitbuchung-Reihenfolge pro Tag analysieren
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buchungssequenz_test_run'])) {
            $rawMid = trim((string)($_POST['buchungssequenz_test_mitarbeiter_id'] ?? ''));
            $rawJ = trim((string)($_POST['buchungssequenz_test_jahr'] ?? ''));
            $rawM = trim((string)($_POST['buchungssequenz_test_monat'] ?? ''));

            if ($rawMid !== '') {
                $buchungssequenzTestMitarbeiterId = (int)$rawMid;
            }
            if ($rawJ !== '') {
                $buchungssequenzTestJahr = (int)$rawJ;
            }
            if ($rawM !== '') {
                $buchungssequenzTestMonat = (int)$rawM;
            }

            if ($buchungssequenzTestMitarbeiterId <= 0) {
                $buchungssequenzTestHinweis = 'Keine gültige Mitarbeiter-ID für den Sequenz-Check.';
            } elseif ($buchungssequenzTestMonat < 1 || $buchungssequenzTestMonat > 12) {
                $buchungssequenzTestHinweis = 'Monat muss 1..12 sein.';
            } elseif ($buchungssequenzTestJahr < 1970 || $buchungssequenzTestJahr > 2100) {
                $buchungssequenzTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
            } elseif ($this->db === null) {
                $buchungssequenzTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
            } else {
                try {
                    $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $buchungssequenzTestJahr, $buchungssequenzTestMonat));
                    $bis = $start->modify('+1 month');
                    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

                    $rows = $this->db->fetchAlle(
                        'SELECT id, typ, zeitstempel\n'
                        . 'FROM zeitbuchung\n'
                        . 'WHERE mitarbeiter_id = :mid AND zeitstempel >= :von AND zeitstempel < :bis\n'
                        . 'ORDER BY zeitstempel ASC, id ASC',
                        [
                            'mid' => $buchungssequenzTestMitarbeiterId,
                            'von' => $start->format('Y-m-d H:i:s'),
                            'bis' => $bis->format('Y-m-d H:i:s'),
                        ]
                    );

                    $byDay = []; // datum => list
                    if (is_array($rows)) {
                        foreach ($rows as $r) {
                            if (!is_array($r)) {
                                continue;
                            }
                            $ts = (string)($r['zeitstempel'] ?? '');
                            $typ = (string)($r['typ'] ?? '');
                            if ($ts === '' || ($typ !== 'kommen' && $typ !== 'gehen')) {
                                continue;
                            }
                            $d = substr($ts, 0, 10);
                            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                                continue;
                            }
                            $byDay[$d][] = [
                                'typ' => $typ,
                                'zeit' => substr($ts, 11, 5),
                            ];
                        }
                    }

                    if ($byDay === []) {
                        $buchungssequenzTestHinweis = 'Keine Zeitbuchungen im gewählten Monat gefunden.';
                    }

                    $auffaellig = [];
                    $mehrblock = [];

                    foreach ($byDay as $d => $list) {
                        if (!is_array($list) || $list === []) {
                            continue;
                        }

                        $types = [];
                        $times = [];
                        foreach ($list as $it) {
                            if (!is_array($it)) {
                                continue;
                            }
                            $types[] = (string)($it['typ'] ?? '');
                            $times[] = (string)($it['zeit'] ?? '');
                        }

                        $n = count($types);
                        if ($n === 0) {
                            continue;
                        }

                        $flags = [];
                        if ($types[0] !== 'kommen') {
                            $flags[] = 'start!=' . $types[0];
                        }
                        if (($n % 2) === 1) {
                            $flags[] = 'odd';
                        }

                        // Adjacent duplicates
                        for ($i = 1; $i < $n; $i++) {
                            if ($types[$i] === $types[$i - 1]) {
                                $flags[] = 'doppelt:' . $types[$i];
                                break;
                            }
                        }

                        // Pair scan
                        $open = false;
                        $pairs = 0;
                        $scanAnom = [];
                        foreach ($types as $t) {
                            if ($t === 'kommen') {
                                if ($open) {
                                    $scanAnom[] = 'kommen_ohne_gehen';
                                }
                                $open = true;
                            } elseif ($t === 'gehen') {
                                if (!$open) {
                                    $scanAnom[] = 'gehen_ohne_kommen';
                                } else {
                                    $open = false;
                                    $pairs++;
                                }
                            }
                        }

                        if ($open) {
                            // Offener Block nur als Fehler, wenn nicht "heute"
                            if ($d !== $today) {
                                $scanAnom[] = 'offen';
                            } else {
                                $flags[] = 'offen(heute)';
                            }
                        }

                        if ($scanAnom !== []) {
                            foreach ($scanAnom as $a) {
                                if (!in_array($a, $flags, true)) {
                                    $flags[] = $a;
                                }
                            }
                        }

                        $isAuffaellig = ($scanAnom !== [] || ($types[0] !== 'kommen') || (($n % 2) === 1));

                        $seq = implode(' ', array_map(static fn(string $t): string => ($t === 'kommen' ? 'K' : 'G'), $types));
                        $timeStr = implode(' ', $times);

                        if ($isAuffaellig) {
                            $auffaellig[] = [
                                'datum' => $d,
                                'count' => $n,
                                'pair_count' => $pairs,
                                'anomalien' => implode(', ', $flags),
                                'sequenz' => $seq,
                                'zeiten' => $timeStr,
                            ];
                        } else {
                            if ($pairs >= 2) {
                                $mehrblock[] = [
                                    'datum' => $d,
                                    'count' => $n,
                                    'pair_count' => $pairs,
                                    'sequenz' => $seq,
                                    'zeiten' => $timeStr,
                                ];
                            }
                        }
                    }

                    $ok = (count($auffaellig) === 0);

                    $buchungssequenzTestErgebnis = [
                        'ok' => $ok,
                        'mitarbeiter_id' => $buchungssequenzTestMitarbeiterId,
                        'jahr' => $buchungssequenzTestJahr,
                        'monat' => $buchungssequenzTestMonat,
                        'tage_mit_buchungen' => count($byDay),
                        'tage_auffaellig' => count($auffaellig),
                        'tage_mehrblock' => count($mehrblock),
                        'auffaellig_sample' => array_slice($auffaellig, 0, 10),
                        'mehrblock_sample' => array_slice($mehrblock, 0, 10),
                    ];
                } catch (Throwable $e) {
                    $buchungssequenzTestHinweis = 'Sequenz-Check Fehler: ' . $e->getMessage();
                }
            }
        }


        // Feiertag-Seed-Check: bundeseinheitliche Feiertage pro Jahr vollständig?
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feiertag_seed_run'])) {
            $rawJ = trim((string)($_POST['feiertag_seed_jahr'] ?? ''));
            if ($rawJ !== '') {
                $feiertagSeedJahr = (int)$rawJ;
            }

            if (!class_exists('FeiertagService')) {
                $feiertagSeedHinweis = 'FeiertagService ist nicht verfügbar (Klasse fehlt).';
            } else {
                try {
                    $fs = FeiertagService::getInstanz();
                    if (method_exists($fs, 'diagnoseBundesweiteFeiertage')) {
                        $feiertagSeedErgebnis = $fs->diagnoseBundesweiteFeiertage($feiertagSeedJahr);
                    } else {
                        $feiertagSeedHinweis = 'diagnoseBundesweiteFeiertage() ist nicht verfügbar (ältere Version).';
                    }
                } catch (Throwable $e) {
                    $feiertagSeedHinweis = 'Feiertag-Seed-Check fehlgeschlagen: ' . $e->getMessage();
                }
            }
        }


        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Smoke-Test (Diagnose)</h2>
            <p>
                Diese Seite führt <strong>nur lesende</strong> Checks aus. Sie ändert keine Zeiten und startet keine Queue.
                Optional kann sie PDFs <strong>im Speicher</strong> erzeugen, um die PDF-Erzeugung auf Validität (Header/EOF) zu prüfen.
            </p>

            <p>
                <a href="?seite=dashboard">&laquo; Zurück zum Dashboard</a>
            </p>

            <h3>Terminal-Login-Check (RFID / Personalnummer / ID)</h3>
            <p>
                Dieser Check ist <strong>rein lesend</strong> und emuliert die Login-Reihenfolge des Terminals.
                Damit lässt sich schnell prüfen, ob ein Code in der Datenbank zu einem <strong>aktiven</strong> Mitarbeiter auflöst.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <label for="terminal_login_code"><strong>Code</strong>:</label>
                <input type="text" id="terminal_login_code" name="terminal_login_code"
                       value="<?php echo htmlspecialchars($terminalLoginCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       style="min-width: 260px;">
                <button type="submit">Prüfen</button>
            </form>

            <?php if ($terminalLoginHinweis !== null && $terminalLoginHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($terminalLoginHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <h3>PDF-Quick-Check (Header/EOF/Seiten, ohne Download)</h3>
            <p>
                Dieser Check erzeugt das Monats-PDF <strong>im Speicher</strong> und prüft nur, ob das Ergebnis wie ein valides PDF aussieht.
                Es wird <strong>nichts</strong> als PDF ausgeliefert.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="pdf_test_run" value="1">
                <label for="pdf_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong> (optional):</label>
                <input type="number" id="pdf_test_mitarbeiter_id" name="pdf_test_mitarbeiter_id"
                       value="<?php echo (int)$pdfTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="pdf_test_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="pdf_test_jahr" name="pdf_test_jahr" value="<?php echo (int)$pdfTestJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="pdf_test_monat"><strong>Monat</strong>:</label>
                <input type="number" id="pdf_test_monat" name="pdf_test_monat" value="<?php echo (int)$pdfTestMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">PDF prüfen</button>
            </form>

            <?php if ($pdfTestHinweis !== null && $pdfTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($pdfTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($pdfTestErgebnis)):
                $ok = (bool)($pdfTestErgebnis['ok'] ?? false);
                ?>
                <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> PDF-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Länge (Bytes): <?php echo (int)($pdfTestErgebnis['bytes'] ?? 0); ?></li>
                        <li>Header "%PDF-" vorhanden: <?php echo !empty($pdfTestErgebnis['header_ok']) ? 'ja' : 'nein'; ?></li>
                        <li>"%%EOF" vorhanden: <?php echo !empty($pdfTestErgebnis['eof_ok']) ? 'ja' : 'nein'; ?></li>
                        <li>Seiten (/Pages /Count): <?php echo ($pdfTestErgebnis['pages_count_declared'] ?? null) !== null ? (int)$pdfTestErgebnis['pages_count_declared'] : 'n/a'; ?></li>
                        <li>Seiten-Objekte (/Type /Page): <?php echo (int)($pdfTestErgebnis['pages_count_objects'] ?? 0); ?></li>
                        <li>Seitenanzahl konsistent: <?php echo ($pdfTestErgebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($pdfTestErgebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
                        <li>Footer "Seite 1/..." gefunden: <?php echo !empty($pdfTestErgebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
                        <?php if ((int)($pdfTestErgebnis['pages_count_objects'] ?? 0) >= 2): ?>
                            <li>Footer "Seite 2/..." gefunden: <?php echo !empty($pdfTestErgebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
                        <?php endif; ?>
                        <li>Header "Arbeitszeitliste" gefunden: <?php echo !empty($pdfTestErgebnis['header_arbeitszeitliste']) ? 'ja' : 'nein'; ?></li>
                        <li>Header "Tag / KW" gefunden: <?php echo !empty($pdfTestErgebnis['header_tag_kw']) ? 'ja' : 'nein'; ?></li>
                    </ul>

                    <?php
                    $kCheck = $pdfTestErgebnis['kommentar_check'] ?? [];
                    $kHinweis = $pdfTestErgebnis['kommentar_hinweis'] ?? null;
                    ?>

                    <?php if (is_array($kCheck) && $kCheck !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Kommentar-Kürzel Check (optional):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($kCheck as $kc):
                                $dt = htmlspecialchars((string)($kc['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $kk = htmlspecialchars((string)($kc['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $fi = !empty($kc['found_in_pdf']);
                                ?>
                                <li><?php echo $dt; ?>: "<?php echo $kk; ?>" → <?php echo $fi ? 'im PDF gefunden' : 'nicht gefunden'; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif (is_string($kHinweis) && $kHinweis !== ''): ?>
                        <hr>
                        <p style="margin:0;"><em><?php echo htmlspecialchars($kHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em></p>
                    <?php else: ?>
                        <hr>
                        <p style="margin:0;"><em>Keine Tageswerte-Kommentare im ausgewählten Monat gefunden (optional).</em></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            
            <h3>PDF-Synth-Check (Multi-Block + Multi-Page, DB-unabhängig)</h3>
            <p>
                Dieser Check erzeugt ein Monats-PDF <strong>aus synthetischen Daten</strong> (3 Arbeitsblöcke pro Tag) und prüft,
                ob der Mehrseiten-Umbruch funktioniert. Er erwartet <strong>mindestens 2 Seiten</strong>.
                Es wird keine DB gelesen/geschrieben. Optional kannst du das Synth-PDF als <strong>PDF im Browser öffnen</strong> (neuer Tab), um Viewer/Rendering zu testen.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="pdf_synth_run" value="1">
                <label for="pdf_synth_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="pdf_synth_jahr" name="pdf_synth_jahr" value="<?php echo (int)$pdfSynthJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="pdf_synth_monat"><strong>Monat</strong>:</label>
                <input type="number" id="pdf_synth_monat" name="pdf_synth_monat" value="<?php echo (int)$pdfSynthMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Synth-PDF prüfen</button>
            </form>

            <p style="margin:0 0 12px 0;">
                Optional: <a href="?seite=smoke_test&amp;smoke_pdf=synth_multipage&amp;jahr=<?php echo (int)$pdfSynthJahr; ?>&amp;monat=<?php echo (int)$pdfSynthMonat; ?>" target="_blank" rel="noopener">Synth-PDF öffnen</a>
            </p>

            <?php if ($pdfSynthHinweis !== null && $pdfSynthHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($pdfSynthHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($pdfSynthErgebnis)):
                $ok = (bool)($pdfSynthErgebnis['ok'] ?? false);
                ?>
                <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Synth-PDF Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Länge (Bytes): <?php echo (int)($pdfSynthErgebnis['bytes'] ?? 0); ?></li>
                        <li>Tage im Monat: <?php echo (int)($pdfSynthErgebnis['days_in_month'] ?? 0); ?></li>
                        <li>Blöcke pro Tag: <?php echo (int)($pdfSynthErgebnis['blocks_per_day'] ?? 0); ?></li>
                        <li>Erwartete Zeilen (inkl. Header+"/"): <?php echo (int)($pdfSynthErgebnis['rows_expected'] ?? 0); ?></li>
                        <li>Header "%PDF-" vorhanden: <?php echo !empty($pdfSynthErgebnis['header_ok']) ? 'ja' : 'nein'; ?></li>
                        <li>"%%EOF" vorhanden: <?php echo !empty($pdfSynthErgebnis['eof_ok']) ? 'ja' : 'nein'; ?></li>
                        <li>Seiten (/Pages /Count): <?php echo ($pdfSynthErgebnis['pages_count_declared'] ?? null) !== null ? (int)$pdfSynthErgebnis['pages_count_declared'] : 'n/a'; ?></li>
                        <li>Seiten-Objekte (/Type /Page): <?php echo (int)($pdfSynthErgebnis['pages_count_objects'] ?? 0); ?></li>
                        <li>Seitenanzahl konsistent: <?php echo ($pdfSynthErgebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($pdfSynthErgebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
                        <li>Mind. 2 Seiten erkannt: <?php echo !empty($pdfSynthErgebnis['pages_at_least2']) ? 'ja' : 'nein'; ?></li>
                        <li>Footer "Seite 1/" gefunden: <?php echo !empty($pdfSynthErgebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
                        <li>Footer "Seite 2/" gefunden: <?php echo !empty($pdfSynthErgebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
                        <li>Header "Arbeitszeitliste" gefunden: <?php echo !empty($pdfSynthErgebnis['header_arbeitszeitliste']) ? 'ja' : 'nein'; ?></li>
                        <li>Header "Tag / KW" gefunden: <?php echo !empty($pdfSynthErgebnis['header_tag_kw']) ? 'ja' : 'nein'; ?></li>
                    </ul>
                </div>
            <?php endif; ?>



            <h3>PDF DB Auto-Multipage-Check (Kandidat finden)</h3>
            <p>
                Dieser Check sucht in den letzten <strong>X Monaten</strong> automatisch den Mitarbeiter/Monat mit den meisten
                <strong>Kommen/Gehen</strong>-Buchungen und prüft, ob das erzeugte Monats-PDF <strong>mindestens 2 Seiten</strong> hat.
                Damit findest du schnell einen echten Datensatz für den Browser-Test (Mehrfach-Kommen/Gehen + Mehrseiten).
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="pdf_db_multipage_run" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <label for="pdf_db_multipage_window"><strong>Suchfenster (Monate)</strong>:</label>
                <input type="number" id="pdf_db_multipage_window" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Automatisch finden &amp; prüfen</button>
            </form>

<p style="margin: 0 0 6px 0;">
    <em>Optional:</em> Statt nur den Top-1 Kandidaten zu prüfen, kannst du dir eine <strong>Kandidatenliste</strong> anzeigen lassen
    und gezielt einen Monat testen (nützlich für Browser-Tests).
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_db_multipage_list_run" value="1">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <label for="pdf_db_multipage_window_list"><strong>Suchfenster (Monate)</strong>:</label>
    <input type="number" id="pdf_db_multipage_window_list" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>" style="width: 70px;">
    &nbsp;
    <label for="pdf_db_multipage_list_limit"><strong>Limit</strong>:</label>
    <input type="number" id="pdf_db_multipage_list_limit" name="pdf_db_multipage_list_limit" value="<?php echo (int)$pdfDbMultiListLimit; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Kandidatenliste anzeigen</button>
</form>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_db_multipage_list_eval" value="1">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <label for="pdf_db_multipage_window_list_eval"><strong>Suchfenster (Monate)</strong>:</label>
    <input type="number" id="pdf_db_multipage_window_list_eval" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>" style="width: 70px;">
    &nbsp;
    <label for="pdf_db_multipage_list_limit_eval"><strong>Limit</strong>:</label>
    <input type="number" id="pdf_db_multipage_list_limit_eval" name="pdf_db_multipage_list_limit" value="<?php echo (int)$pdfDbMultiListLimit; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Kandidatenliste automatisch prüfen (PDF)</button>
</form>

<?php if ($pdfDbMultiListHinweis !== null && $pdfDbMultiListHinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($pdfDbMultiListHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($pdfDbMultiListe) && $pdfDbMultiListe !== []): ?>
    <div style="padding:10px; background:#e3f2fd; border:1px solid #1565c0; margin-bottom: 12px;">
        <p style="margin:0 0 8px 0;"><strong>Kandidatenliste</strong> (Top <?php echo (int)$pdfDbMultiListLimit; ?>, Fenster <?php echo (int)$pdfDbMultiWindowMonate; ?> Monate)</p>

        <?php
            $hasEval = false;
            if (isset($pdfDbMultiListe[0]) && is_array($pdfDbMultiListe[0]) && array_key_exists('eval_ok', $pdfDbMultiListe[0])) {
                $hasEval = true;
            }
        ?>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
            <tr>
                <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Mitarbeiter</th>
                <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Monat</th>
                <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">K/G</th>
                <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">Max/Tag</th>
                <?php if (!empty($hasEval)): ?>
                    <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Status</th>
                    <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">Seiten</th>
                    <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">Bytes</th>
                <?php endif; ?>
                <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pdfDbMultiListe as $row): ?>
                <?php
                    $mid = (int)($row['mitarbeiter_id'] ?? 0);
                    $jahr = (int)($row['jahr'] ?? 0);
                    $monat = (int)($row['monat'] ?? 0);
                    $name = (string)($row['name'] ?? '');
                    $kg = (int)($row['buchungen_kommen_gehen'] ?? 0);
                    $maxc = (int)($row['max_day_buchungen'] ?? 0);
                    $maxd = (string)($row['max_day_datum'] ?? '');
                    $linkReport = (string)($row['link_report'] ?? '');
                    $linkPdf = (string)($row['link_pdf'] ?? '');

                    $evalOk = !empty($hasEval) ? ($row['eval_ok'] ?? null) : null;
                    $evalReason = !empty($hasEval) ? (string)($row['eval_reason'] ?? '') : '';
                    $evalPages = !empty($hasEval) ? (int)($row['eval_pages'] ?? 0) : 0;
                    $evalBytes = !empty($hasEval) ? (int)($row['eval_bytes'] ?? 0) : 0;
                ?>
                <tr>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                        #<?php echo $mid; ?> (<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                    </td>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                        <?php echo sprintf('%02d/%04d', $monat, $jahr); ?>
                    </td>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                        <?php echo $kg; ?>
                    </td>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                        <?php echo $maxc; ?><?php echo $maxd !== '' ? ' (' . htmlspecialchars($maxd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : ''; ?>
                    </td>

                    <?php if (!empty($hasEval)): ?>
                        <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                            <?php
                                if ($evalOk === true) {
                                    echo '<strong>OK</strong>';
                                } elseif ($evalOk === false) {
                                    echo '<strong>FAIL</strong>';
                                } else {
                                    echo 'SKIP';
                                }
                            ?>
                            <?php if ($evalOk !== true && $evalReason !== ''): ?>
                                <br><small><?php echo htmlspecialchars($evalReason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                            <?php echo (int)$evalPages; ?>
                        </td>
                        <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                            <?php echo (int)$evalBytes; ?>
                        </td>
                    <?php endif; ?>

                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                        <form method="post" action="?seite=smoke_test" style="display:inline;">
                            <input type="hidden" name="pdf_db_multipage_run" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <input type="hidden" name="pdf_db_multipage_mid" value="<?php echo $mid; ?>">
                            <input type="hidden" name="pdf_db_multipage_year" value="<?php echo $jahr; ?>">
                            <input type="hidden" name="pdf_db_multipage_month" value="<?php echo $monat; ?>">
                            <input type="hidden" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>">
                            <button type="submit">Prüfen</button>
                        </form>
                        <?php if ($linkReport !== ''): ?>
                            &nbsp;|&nbsp;<a href="<?php echo htmlspecialchars($linkReport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Report</a>
                        <?php endif; ?>
                        <?php if ($linkPdf !== ''): ?>
                            &nbsp;|&nbsp;<a href="<?php echo htmlspecialchars($linkPdf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


            <?php if ($pdfDbMultiHinweis !== null && $pdfDbMultiHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($pdfDbMultiHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($pdfDbMultiErgebnis)):
                $ok = (bool)($pdfDbMultiErgebnis['ok'] ?? false);
                $linkReport = (string)($pdfDbMultiErgebnis['link_report'] ?? '');
                $linkPdf = (string)($pdfDbMultiErgebnis['link_pdf'] ?? '');
                ?>
                <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> PDF DB Auto-Multipage Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Suchfenster: <?php echo (int)($pdfDbMultiErgebnis['window_monate'] ?? 0); ?> Monate</li>
                        <li>Gefunden via: <?php echo htmlspecialchars((string)($pdfDbMultiErgebnis['gefunden_via'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                        <li>Mitarbeiter: #<?php echo (int)($pdfDbMultiErgebnis['mitarbeiter_id'] ?? 0); ?> (<?php echo htmlspecialchars((string)($pdfDbMultiErgebnis['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</li>
                        <li>Monat: <?php echo (int)($pdfDbMultiErgebnis['monat'] ?? 0); ?>/<?php echo (int)($pdfDbMultiErgebnis['jahr'] ?? 0); ?></li>
                        <li>Kommen/Gehen-Buchungen: <?php echo (int)($pdfDbMultiErgebnis['buchungen_kommen_gehen'] ?? 0); ?></li>
                        <li>Max. Buchungen an einem Tag: <?php echo (int)($pdfDbMultiErgebnis['max_day_buchungen'] ?? 0); ?><?php echo ($pdfDbMultiErgebnis['max_day_datum'] ?? '') !== '' ? ' (' . htmlspecialchars((string)$pdfDbMultiErgebnis['max_day_datum'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : ''; ?></li>
                        <li>PDF Bytes: <?php echo (int)($pdfDbMultiErgebnis['pdf_bytes'] ?? 0); ?></li>
                        <li>Seiten (/Pages /Count): <?php echo ($pdfDbMultiErgebnis['pages_count_declared'] ?? null) !== null ? (int)$pdfDbMultiErgebnis['pages_count_declared'] : 'n/a'; ?></li>
                        <li>Seiten-Objekte (/Type /Page): <?php echo (int)($pdfDbMultiErgebnis['pages_count_objects'] ?? 0); ?></li>
                        <li>Mind. 2 Seiten erkannt: <?php echo !empty($pdfDbMultiErgebnis['pages_at_least2']) ? 'ja' : 'nein'; ?></li>
                        <li>Seitenanzahl konsistent: <?php echo ($pdfDbMultiErgebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($pdfDbMultiErgebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
                        <li>Footer "Seite 1/" gefunden: <?php echo !empty($pdfDbMultiErgebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
                        <li>Footer "Seite 2/" gefunden: <?php echo !empty($pdfDbMultiErgebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
                        <li>
                            HTML-Render-Check: 
                            <?php
                                $hOk = $pdfDbMultiErgebnis['html_ok'] ?? null;
                                if ($hOk === null) {
                                    echo 'SKIP';
                                } else {
                                    echo !empty($hOk) ? 'OK' : 'FAIL';
                                }
                            ?>
                            <?php if (!empty($pdfDbMultiErgebnis['html_hinweis'])): ?>
                                &nbsp;<small>(<?php echo htmlspecialchars((string)$pdfDbMultiErgebnis['html_hinweis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
                            <?php endif; ?>
                        </li>
                        <li>HTML: Heading "Monatsübersicht": <?php echo !empty($pdfDbMultiErgebnis['html_has_heading']) ? 'ja' : 'nein'; ?>, Tabelle: <?php echo !empty($pdfDbMultiErgebnis['html_has_table']) ? 'ja' : 'nein'; ?>, Headerzellen (Datum/An/Ab): <?php echo !empty($pdfDbMultiErgebnis['html_has_header_cells']) ? 'ja' : 'nein'; ?>, PDF-Link: <?php echo !empty($pdfDbMultiErgebnis['html_has_pdf_link']) ? 'ja' : 'nein'; ?></li>
                        <li>HTML: &lt;tr&gt; Count: <?php echo (int)($pdfDbMultiErgebnis['html_tr_count'] ?? 0); ?> (Tage im Monat: <?php echo (int)($pdfDbMultiErgebnis['html_days_in_month'] ?? 0); ?>, Mindest-OK: <?php echo ($pdfDbMultiErgebnis['html_rows_min_ok'] ?? null) === null ? 'n/a' : (!empty($pdfDbMultiErgebnis['html_rows_min_ok']) ? 'ja' : 'nein'); ?>)</li>
                    </ul>

                    <?php if ($linkReport !== '' || $linkPdf !== ''): ?>
                        <hr>
                        <p style="margin:0;">
                            <?php if ($linkReport !== ''): ?>
                                <a href="<?php echo htmlspecialchars($linkReport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Monatsreport öffnen</a>
                            <?php endif; ?>
                            <?php if ($linkReport !== '' && $linkPdf !== ''): ?>
                                &nbsp;|&nbsp;
                            <?php endif; ?>
                            <?php if ($linkPdf !== ''): ?>
                                <a href="<?php echo htmlspecialchars($linkPdf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Monats-PDF öffnen</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

<h3>Feiertag-Quick-Check (Monatsreport)</h3>
            <p>
                Dieser Check prüft, ob ein konkretes Datum im Monatsreport als <strong>Feiertag</strong> erkannt wird
                und (wenn <strong>keine</strong> Arbeitszeit vorhanden ist) die <strong>Sollstunden</strong> im Feld <strong>Feiertag</strong> landen.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="feiertag_test_run" value="1">
                <label for="feiertag_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong> (optional):</label>
                <input type="number" id="feiertag_test_mitarbeiter_id" name="feiertag_test_mitarbeiter_id"
                       value="<?php echo (int)$feiertagTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="feiertag_test_datum"><strong>Datum</strong> (YYYY-MM-DD):</label>
                <input type="text" id="feiertag_test_datum" name="feiertag_test_datum"
                       value="<?php echo htmlspecialchars($feiertagTestDatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       style="width: 140px;">
                &nbsp;
                <button type="submit">Feiertag prüfen</button>
            </form>

            <?php if ($feiertagTestHinweis !== null && $feiertagTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($feiertagTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($feiertagTestErgebnis)):
                $ok = $feiertagTestErgebnis['ok'] ?? null;
                $isOk = ($ok === true);
                $isFail = ($ok === false);
                $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
                $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
                $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Feiertag-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Datum: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                        <li>Wochentag (1=Mo..7=So): <?php echo (int)($feiertagTestErgebnis['wochentag'] ?? 0); ?></li>
                        <li>FeiertagService sagt: <?php
                            $v = $feiertagTestErgebnis['ist_feiertag'] ?? null;
                            echo ($v === true) ? 'ja' : (($v === false) ? 'nein' : 'unbekannt');
                        ?></li>
                        <li>Kennzeichen Feiertag: <?php echo (int)($feiertagTestErgebnis['kennzeichen_feiertag'] ?? 0); ?></li>
                        <li>Arbeitszeit (Ist): <?php echo htmlspecialchars((string)($feiertagTestErgebnis['arbeitszeit_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</li>
                        <li>Feiertag-Stunden: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['feiertag_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</li>
                        <li>Tagestyp: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['tagestyp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                        <li>Kürzel/Kommentar: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                    </ul>
                    <hr>
                    <p style="margin:0;"><em><?php echo htmlspecialchars((string)($feiertagTestErgebnis['erwartung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em></p>
                </div>
            <?php endif; ?>

            <h3>Monatsreport-Raster-Check</h3>
            <p>
                Dieser Check prüft, ob der Monatsreport wirklich ein <strong>vollständiges Monatsraster</strong> liefert:
                <strong>genau ein</strong> Tageswert pro Kalendertag (also z. B. 31 Zeilen im Januar).
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="monatsraster_test_run" value="1">
                <label for="monatsraster_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
                <input type="number" id="monatsraster_test_mitarbeiter_id" name="monatsraster_test_mitarbeiter_id"
                       value="<?php echo (int)$monatsrasterTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="monatsraster_test_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="monatsraster_test_jahr" name="monatsraster_test_jahr"
                       value="<?php echo (int)$monatsrasterTestJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="monatsraster_test_monat"><strong>Monat</strong>:</label>
                <input type="number" id="monatsraster_test_monat" name="monatsraster_test_monat"
                       value="<?php echo (int)$monatsrasterTestMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Raster prüfen</button>
            </form>

            <?php if ($monatsrasterTestHinweis !== null && $monatsrasterTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($monatsrasterTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($monatsrasterTestErgebnis)):
                $ok = (bool)($monatsrasterTestErgebnis['ok'] ?? false);
                $bg = $ok ? '#e8f5e9' : '#ffebee';
                $bd = $ok ? '#2e7d32' : '#c62828';
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Monatsraster-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Mitarbeiter-ID: <?php echo (int)($monatsrasterTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                        <li>Monat: <?php echo sprintf('%04d-%02d', (int)($monatsrasterTestErgebnis['jahr'] ?? 0), (int)($monatsrasterTestErgebnis['monat'] ?? 0)); ?></li>
                        <li>Tage im Monat: <?php echo (int)($monatsrasterTestErgebnis['tage_im_monat'] ?? 0); ?></li>
                        <li>Tageswerte (Report): <?php echo (int)($monatsrasterTestErgebnis['tageswerte_count'] ?? 0); ?></li>
                    </ul>

                    <?php
                    $miss = $monatsrasterTestErgebnis['missing'] ?? [];
                    $dup  = $monatsrasterTestErgebnis['duplicates'] ?? [];
                    $inv  = $monatsrasterTestErgebnis['invalid'] ?? [];
                    ?>

                    <?php if (is_array($miss) && $miss !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Fehlende Datumswerte (max. 10):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($miss as $d): ?>
                                <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (is_array($dup) && $dup !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Doppelte Datumswerte (max. 10):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($dup as $d): ?>
                                <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (is_array($inv) && $inv !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Ungültige Datumswerte (max. 10):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($inv as $d): ?>
                                <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


            <h3>Monatsreport-Fallback-Check (lueckenhafte Tageswerte)</h3>
            <p>
                Dieser Check sucht im ausgewaehlten Monat nach Tagen, an denen es <strong>Zeitbuchungen</strong> gibt,
                aber <strong>kein</strong> entsprechender Datensatz in <code>tageswerte_mitarbeiter</code> vorhanden ist.
                Anschliessend wird geprueft, ob der Monatsreport diese Tage per <strong>Fallback</strong> (aus Zeitbuchungen) sinnvoll befuellt.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="monatsfallback_test_run" value="1">
                <label for="monatsfallback_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
                <input type="number" id="monatsfallback_test_mitarbeiter_id" name="monatsfallback_test_mitarbeiter_id"
                       value="<?php echo (int)$monatsfallbackTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="monatsfallback_test_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="monatsfallback_test_jahr" name="monatsfallback_test_jahr"
                       value="<?php echo (int)$monatsfallbackTestJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="monatsfallback_test_monat"><strong>Monat</strong>:</label>
                <input type="number" id="monatsfallback_test_monat" name="monatsfallback_test_monat"
                       value="<?php echo (int)$monatsfallbackTestMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Fallback pruefen</button>
            </form>

            <?php if ($monatsfallbackTestHinweis !== null && $monatsfallbackTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($monatsfallbackTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($monatsfallbackTestErgebnis)):
                $ok = (bool)($monatsfallbackTestErgebnis['ok'] ?? false);
                $bg = $ok ? '#e8f5e9' : '#ffebee';
                $bd = $ok ? '#2e7d32' : '#c62828';
                $missingCount = (int)($monatsfallbackTestErgebnis['missing_days_count'] ?? 0);
                $notCoveredCount = (int)($monatsfallbackTestErgebnis['not_covered_count'] ?? 0);
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Monatsreport-Fallback-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Mitarbeiter-ID: <?php echo (int)($monatsfallbackTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                        <li>Monat: <?php echo sprintf('%04d-%02d', (int)($monatsfallbackTestErgebnis['jahr'] ?? 0), (int)($monatsfallbackTestErgebnis['monat'] ?? 0)); ?></li>
                        <li>Tage mit Buchungen: <?php echo (int)($monatsfallbackTestErgebnis['booked_days_count'] ?? 0); ?></li>
                        <li>Tage mit Tageswerten (DB): <?php echo (int)($monatsfallbackTestErgebnis['tageswerte_days_count'] ?? 0); ?></li>
                        <li>Tage mit Buchungen aber ohne Tageswerte: <?php echo $missingCount; ?></li>
                        <li>Davon im Report nicht sinnvoll befuellt: <?php echo $notCoveredCount; ?></li>
                    </ul>

                    <?php
                    $miss = $monatsfallbackTestErgebnis['missing_days_sample'] ?? [];
                    $nc = $monatsfallbackTestErgebnis['not_covered_sample'] ?? [];
                    $samples = $monatsfallbackTestErgebnis['samples'] ?? [];
                    ?>

                    <?php if (is_array($miss) && $miss !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Beispiel: fehlende Tageswerte (max. 10):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($miss as $d): ?>
                                <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (is_array($nc) && $nc !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Nicht befuellt (max. 10):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($nc as $d): ?>
                                <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (is_array($samples) && $samples !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Samples (max. 8):</strong></p>
                        <table style="border-collapse:collapse; width:100%;">
                            <thead>
                            <tr>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Buchungen</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Kommen (roh)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Gehen (roh)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">AZ (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Pause (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:center;">Report gefuellt</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($samples as $s):
                                $d = htmlspecialchars((string)($s['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $b = (int)($s['buchungen'] ?? 0);
                                $k = htmlspecialchars((string)($s['kommen_roh'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $g = htmlspecialchars((string)($s['gehen_roh'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $az = htmlspecialchars((string)($s['arbeitszeit_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $pz = htmlspecialchars((string)($s['pausen_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $cv = !empty($s['covered']);
                                ?>
                                <tr>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $d; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $b; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $k; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $g; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $az; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $pz; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:center;"><?php echo $cv ? 'ja' : 'nein'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h3>Doppelzählung-Check (Betriebsferien / Kurzarbeit-Volltag)</h3>
            <p>
                Dieser Check prüft im Monatsreport, ob <strong>Betriebsferien</strong> (als Urlaub 8h) oder <strong>Kurzarbeit-Volltag</strong>
                <strong>nicht zusätzlich</strong> gezählt werden, wenn an diesem Tag bereits <strong>Arbeitszeit</strong> vorhanden ist.
                Er findet damit typische Randfälle, bei denen Monatsübersicht/PDF sonst zu hohe Ist-Summen zeigen würden.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="doppelzaehlung_test_run" value="1">
                <label for="doppelzaehlung_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
                <input type="number" id="doppelzaehlung_test_mitarbeiter_id" name="doppelzaehlung_test_mitarbeiter_id"
                       value="<?php echo (int)$doppelzaehlungTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="doppelzaehlung_test_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="doppelzaehlung_test_jahr" name="doppelzaehlung_test_jahr"
                       value="<?php echo (int)$doppelzaehlungTestJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="doppelzaehlung_test_monat"><strong>Monat</strong>:</label>
                <input type="number" id="doppelzaehlung_test_monat" name="doppelzaehlung_test_monat"
                       value="<?php echo (int)$doppelzaehlungTestMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Doppelzählung prüfen</button>
            </form>

            <?php if ($doppelzaehlungTestHinweis !== null && $doppelzaehlungTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($doppelzaehlungTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($doppelzaehlungTestErgebnis)):
                $ok = $doppelzaehlungTestErgebnis['ok'] ?? null;
                $isOk = ($ok === true);
                $isFail = ($ok === false);
                $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
                $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
                $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
                $issues = $doppelzaehlungTestErgebnis['issues'] ?? [];
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Doppelzählung-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Mitarbeiter-ID: <?php echo (int)($doppelzaehlungTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                        <li>Monat: <?php echo sprintf('%04d-%02d', (int)($doppelzaehlungTestErgebnis['jahr'] ?? 0), (int)($doppelzaehlungTestErgebnis['monat'] ?? 0)); ?></li>
                        <li>Betriebsferien-Tage im Report: <?php echo (int)($doppelzaehlungTestErgebnis['betriebsferien_tage'] ?? 0); ?></li>
                        <li>Kurzarbeit-Volltag-Tage im Report: <?php echo (int)($doppelzaehlungTestErgebnis['kurzarbeit_volltag_tage'] ?? 0); ?></li>
                        <li>Auffälligkeiten: <?php echo (int)($doppelzaehlungTestErgebnis['issues_count'] ?? 0); ?></li>
                    </ul>

                    <?php if (is_array($issues) && $issues !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Beispiele (max. 12):</strong></p>
                        <table style="border-collapse:collapse; width:100%;">
                            <thead>
                            <tr>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Typ</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">AZ (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Urlaub (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Kurzarbeit (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Hinweis</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($issues as $it):
                                $d = htmlspecialchars((string)($it['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $typ = htmlspecialchars((string)($it['typ'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $az = htmlspecialchars((string)($it['arbeitszeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $u = htmlspecialchars((string)($it['urlaub'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $k = htmlspecialchars((string)($it['kurzarbeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $h = htmlspecialchars((string)($it['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                                <tr>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $d; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $typ; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $az; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $u; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $k; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $h; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


            <h3>Feiertag+Arbeitszeit-Check (Monat)</h3>
            <p>
                Dieser Check findet Konflikte, bei denen an einem Feiertag sowohl <em>Arbeitszeit</em> als auch
                <em>Feiertagsstunden</em> gesetzt sind. Das würde im Monatsreport zu einer Doppelzählung führen.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="feiertag_arbeitszeit_test_run" value="1">
                <label for="feiertag_arbeitszeit_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
                <input type="number" id="feiertag_arbeitszeit_test_mitarbeiter_id" name="feiertag_arbeitszeit_test_mitarbeiter_id"
                       value="<?php echo (int)$feiertagArbeitszeitTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="feiertag_arbeitszeit_test_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="feiertag_arbeitszeit_test_jahr" name="feiertag_arbeitszeit_test_jahr"
                       value="<?php echo (int)$feiertagArbeitszeitTestJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="feiertag_arbeitszeit_test_monat"><strong>Monat</strong>:</label>
                <input type="number" id="feiertag_arbeitszeit_test_monat" name="feiertag_arbeitszeit_test_monat"
                       value="<?php echo (int)$feiertagArbeitszeitTestMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Konflikte prüfen</button>
            </form>

            <?php if ($feiertagArbeitszeitTestHinweis !== null && $feiertagArbeitszeitTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($feiertagArbeitszeitTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($feiertagArbeitszeitTestErgebnis)):
                $ok = $feiertagArbeitszeitTestErgebnis['ok'] ?? null;
                $isOk = ($ok === true);
                $isFail = ($ok === false);
                $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
                $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
                $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
                $issues = $feiertagArbeitszeitTestErgebnis['issues'] ?? [];
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Feiertag+Arbeitszeit-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Mitarbeiter-ID: <?php echo (int)($feiertagArbeitszeitTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                        <li>Monat: <?php echo sprintf('%04d-%02d', (int)($feiertagArbeitszeitTestErgebnis['jahr'] ?? 0), (int)($feiertagArbeitszeitTestErgebnis['monat'] ?? 0)); ?></li>
                        <li>Feiertag-Tage im Report: <?php echo (int)($feiertagArbeitszeitTestErgebnis['feiertag_tage'] ?? 0); ?></li>
                        <li>Konflikte: <?php echo (int)($feiertagArbeitszeitTestErgebnis['issues_count'] ?? 0); ?></li>
                    </ul>

                    <?php if (is_array($issues) && $issues !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Beispiele (max. 12):</strong></p>
                        <table style="border-collapse:collapse; width:100%;">
                            <thead>
                            <tr>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">AZ (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Feiertag (h)</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Hinweis</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($issues as $it):
                                $d = htmlspecialchars((string)($it['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $az = htmlspecialchars((string)($it['arbeitszeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $ft = htmlspecialchars((string)($it['feiertag'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $h = htmlspecialchars((string)($it['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                                <tr>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $d; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $az; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $ft; ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo $h; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


            <h3>Kommen/Gehen-Sequenz-Check (Monat)</h3>
            <p>
                Dieser Check analysiert die Reihenfolge der Zeitbuchungen (kommen/gehen) pro Tag.
                Er findet auffaellige Tage wie z.B. <em>gehen ohne kommen</em>, <em>doppelte Typen</em> oder <em>offene Arbeitsbloecke</em>.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="buchungssequenz_test_run" value="1">
                <label for="buchungssequenz_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
                <input type="number" id="buchungssequenz_test_mitarbeiter_id" name="buchungssequenz_test_mitarbeiter_id"
                       value="<?php echo (int)$buchungssequenzTestMitarbeiterId; ?>" style="width: 110px;">
                &nbsp;
                <label for="buchungssequenz_test_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="buchungssequenz_test_jahr" name="buchungssequenz_test_jahr"
                       value="<?php echo (int)$buchungssequenzTestJahr; ?>" style="width: 90px;">
                &nbsp;
                <label for="buchungssequenz_test_monat"><strong>Monat</strong>:</label>
                <input type="number" id="buchungssequenz_test_monat" name="buchungssequenz_test_monat"
                       value="<?php echo (int)$buchungssequenzTestMonat; ?>" style="width: 70px;">
                &nbsp;
                <button type="submit">Sequenz pruefen</button>
            </form>

            <?php if ($buchungssequenzTestHinweis !== null && $buchungssequenzTestHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($buchungssequenzTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($buchungssequenzTestErgebnis)):
                $ok = (bool)($buchungssequenzTestErgebnis['ok'] ?? false);
                $bg = $ok ? '#e8f5e9' : '#ffebee';
                $bd = $ok ? '#2e7d32' : '#c62828';
                $auffaellig = $buchungssequenzTestErgebnis['auffaellig_sample'] ?? [];
                $mehrblock = $buchungssequenzTestErgebnis['mehrblock_sample'] ?? [];
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'HINWEIS'; ?>:</strong> Kommen/Gehen-Sequenz-Check Ergebnis</p>
                    <ul style="margin:0;">
                        <li>Mitarbeiter-ID: <?php echo (int)($buchungssequenzTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                        <li>Monat: <?php echo sprintf('%04d-%02d', (int)($buchungssequenzTestErgebnis['jahr'] ?? 0), (int)($buchungssequenzTestErgebnis['monat'] ?? 0)); ?></li>
                        <li>Tage mit Buchungen: <?php echo (int)($buchungssequenzTestErgebnis['tage_mit_buchungen'] ?? 0); ?></li>
                        <li>Auffaellige Tage: <?php echo (int)($buchungssequenzTestErgebnis['tage_auffaellig'] ?? 0); ?></li>
                        <li>Tage mit mehreren Arbeitsbloecken: <?php echo (int)($buchungssequenzTestErgebnis['tage_mehrblock'] ?? 0); ?></li>
                    </ul>

                    <?php if (is_array($auffaellig) && $auffaellig !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Auffaellige Tage (max. 10):</strong></p>
                        <table style="border-collapse:collapse; width:100%;">
                            <thead>
                            <tr>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Buchungen</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:right;">Paare</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Anomalien</th>
                                <th style="border:1px solid #ccc; padding:6px; text-align:left;">Sequenz</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($auffaellig as $a): ?>
                                <tr>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo htmlspecialchars((string)($a['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo (int)($a['count'] ?? 0); ?></td>
                                    <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo (int)($a['pair_count'] ?? 0); ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo htmlspecialchars((string)($a['anomalien'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td style="border:1px solid #ccc; padding:6px;"><?php echo htmlspecialchars((string)($a['sequenz'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (is_array($mehrblock) && $mehrblock !== []): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Mehrblock-Tage (max. 10):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($mehrblock as $m): ?>
                                <li>
                                    <?php echo htmlspecialchars((string)($m['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                    (Paare: <?php echo (int)($m['pair_count'] ?? 0); ?>, Buchungen: <?php echo (int)($m['count'] ?? 0); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($terminalLoginErgebnis)):
                $typ = $terminalLoginErgebnis['typ'] ?? null;
                $m = $terminalLoginErgebnis['mitarbeiter'] ?? null;
                $warn = $terminalLoginErgebnis['warnungen'] ?? [];
                ?>
                <div style="padding:10px; background:<?php echo $typ ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $typ ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
                    <?php if ($typ && is_array($m)): ?>
                        <p style="margin:0 0 6px 0;"><strong>OK:</strong> Terminal würde den Mitarbeiter per <strong><?php echo htmlspecialchars((string)$typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> einloggen.</p>
                        <ul style="margin:0;">
                            <li>ID: <?php echo (int)($m['id'] ?? 0); ?></li>
                            <li>Name: <?php echo htmlspecialchars(trim((string)($m['vorname'] ?? '') . ' ' . (string)($m['nachname'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <li>Personalnummer: <?php echo htmlspecialchars((string)($m['personalnummer'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <li>RFID: <?php echo htmlspecialchars((string)($m['rfid_code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                        </ul>

                        <?php
                        $a = $terminalLoginErgebnis['anwesenheit'] ?? null;
                        if (is_array($a) && isset($a['kommen']) && isset($a['gehen'])):
                            $isA = (bool)($a['ist_anwesend'] ?? false);
                            $k = (int)($a['kommen'] ?? 0);
                            $g = (int)($a['gehen'] ?? 0);
                            ?>
                            <hr>
                            <p style="margin:0 0 6px 0;"><strong>Anwesenheit heute (<?php echo htmlspecialchars((string)($a['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>):</strong>
                                Kommen <?php echo $k; ?> / Gehen <?php echo $g; ?>
                                &nbsp;→&nbsp;
                                <strong><?php echo $isA ? 'ANWESEND' : 'NICHT anwesend'; ?></strong>
                            </p>
                            <p style="margin:0 0 6px 0;">
                                Terminal-Menü sollte entsprechend
                                <strong><?php echo $isA ? 'Gehen + Aufträge (+ Urlaub)' : 'nur Kommen (+ Urlaub)'; ?></strong>
                                anzeigen.
                            </p>


                            <?php
                            $kommenErlaubt = (bool)($a['kommen_erlaubt'] ?? (!$isA));
                            $gehenErlaubt = (bool)($a['gehen_erlaubt'] ?? $isA);
                            $auftragErlaubt = (bool)($a['auftrag_erlaubt'] ?? $isA);
                            ?>
                            <p style="margin:0 0 6px 0;">
                                <strong>Erlaubt (online-Check):</strong>
                                Kommen <?php echo $kommenErlaubt ? 'JA' : 'NEIN'; ?>,
                                Gehen <?php echo $gehenErlaubt ? 'JA' : 'NEIN'; ?>,
                                Auftrag-Start <?php echo $auftragErlaubt ? 'JA' : 'NEIN'; ?>
                            </p>
                            <?php if (is_array($a['letzte_buchung'] ?? null) && isset($a['letzte_buchung']['typ'])):
                                $lb = $a['letzte_buchung'];
                                ?>
                                <p style="margin:0;">
                                    Letzte Buchung: <strong><?php echo htmlspecialchars((string)($lb['typ'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                                    um <strong><?php echo htmlspecialchars((string)($lb['zeitstempel'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                                    (Quelle: <?php echo htmlspecialchars((string)($lb['quelle'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                                </p>
                            <?php endif; ?>

                        <?php elseif (is_array($a) && isset($a['fehler'])): ?>
                            <hr>
                            <p style="margin:0;"><strong>Anwesenheit-Check:</strong> Fehler: <?php echo htmlspecialchars((string)$a['fehler'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                        <?php endif; ?>

					<?php else:
						$fc = (string)($terminalLoginErgebnis['fehler_code'] ?? '');
						if ($fc === 'MEHRDEUTIG'):
							?>
							<p style="margin:0;"><strong>BLOCK:</strong> Mehrdeutiger numerischer Code – Terminal würde den Login abbrechen.</p>
						<?php else: ?>
							<p style="margin:0;"><strong>FAIL:</strong> Kein aktiver Mitarbeiter für diesen Code gefunden.</p>
						<?php endif; ?>
					<?php endif; ?>

					<?php
					$alts = $terminalLoginErgebnis['alternativen'] ?? [];
					if (is_array($alts) && count($alts) > 0):
						?>
						<hr>
						<p style="margin:0 0 6px 0;"><strong>Alternative Treffer (Mehrdeutigkeits-Check):</strong></p>
						<ul style="margin:0;">
							<?php foreach ($alts as $t => $rowAlt):
								$altId = (int)($rowAlt['id'] ?? 0);
								$altName = trim((string)($rowAlt['vorname'] ?? '') . ' ' . (string)($rowAlt['nachname'] ?? ''));
								$altLine = (string)$t . ': ID ' . $altId . ($altName !== '' ? ' (' . $altName . ')' : '');
								?>
								<li><?php echo htmlspecialchars($altLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

                    <?php if (is_array($warn) && count($warn) > 0): ?>
                        <hr>
                        <p style="margin:0 0 6px 0;"><strong>Hinweise:</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($warn as $w): ?>
                                <li><?php echo htmlspecialchars((string)$w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>


            <?php endif; ?>


            
            <h3>Terminal-Konfiguration (Config-Keys)</h3>
            <p>
                Das Terminal liest bestimmte Einstellungen aus der Tabelle <code>config</code>.
                Wenn ein Key fehlt oder ein ungültiger Wert gespeichert ist, wird im Terminal automatisch auf Default-Werte zurückgefallen.
            </p>

            <?php if (is_string($terminalConfigHinweis) && $terminalConfigHinweis !== ''): ?>
                <div style="padding:10px; background:#ffebee; border:1px solid #c62828; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($terminalConfigHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($terminalConfig) && count($terminalConfig) > 0): ?>
                <table style="margin-bottom: 16px;">
                    <thead>
                    <tr>
                        <th>Key</th>
                        <th>Beschreibung</th>
                        <th>DB-Wert</th>
                        <th>Effektiv</th>
                        <th>Status</th>
                        <th>Range</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($terminalConfig as $row):
                        if (!is_array($row)) { continue; }
                        $st = (string)($row['status'] ?? 'default');
                        $statusText = ($st === 'ok') ? 'OK' : (($st === 'invalid') ? 'INVALID → Default' : 'Nicht gesetzt → Default');
                        $style = ($st === 'ok') ? 'background:#e8f5e9;' : (($st === 'invalid') ? 'background:#ffebee;' : 'background:#fffde7;');
                        $raw = $row['raw'] ?? null;
                        $rawText = ($raw === null || trim((string)$raw) === '') ? '—' : (string)$raw;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($row['key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['titel'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><strong><?php echo (int)($row['effective'] ?? 0); ?></strong> s</td>
                            <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo (int)($row['min'] ?? 0); ?>–<?php echo (int)($row['max'] ?? 0); ?> s (Default: <?php echo (int)($row['default'] ?? 0); ?> s)</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:-6px;">
                    <a href="?seite=konfiguration_admin">Konfiguration öffnen</a>
                </p>
            <?php else: ?>
                <p><em>Keine Terminal-Konfigdaten verfügbar (DB nicht erreichbar oder Tabelle <code>config</code> fehlt).</em></p>
            <?php endif; ?>

            <h3>Offline-Queue (db_injektionsqueue)</h3>
            <p>
                Zeigt den Status der Offline-Queue (rein lesend). Wenn „offen“ oder „fehler“ vorhanden sind,
                muss die Queue im Backend verarbeitet bzw. geprüft werden.
            </p>

            <?php if (is_string($smokeFlash) && $smokeFlash !== ''): ?>
                <div style="padding:10px; background:#e3f2fd; border:1px solid #1565c0; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($smokeFlash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_string($queueHinweis) && $queueHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($queueHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($queueUebersicht) && is_array($queueUebersicht['counts'] ?? null)):
                $qQuelle = (string)($queueUebersicht['quelle'] ?? '');
                $qQuelleLabel = ($qQuelle === 'offline') ? 'Offline-DB' : 'Haupt-DB';
                $qc = (array)$queueUebersicht['counts'];
                $qFehler = (int)($qc['fehler'] ?? 0);
                $qOffen = (int)($qc['offen'] ?? 0);
                $qVerarb = (int)($qc['verarbeitet'] ?? 0);
                $qGes = (int)($qc['gesamt'] ?? ($qFehler + $qOffen + $qVerarb));
                $qOk = ($qFehler === 0);
                ?>
                <div style="padding:10px; background:<?php echo $qOk ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $qOk ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;">
                        <strong><?php echo $qOk ? 'OK' : 'HINWEIS'; ?>:</strong>
                        Queue-DB: <?php echo htmlspecialchars($qQuelleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> – Queue-Einträge: Gesamt <?php echo $qGes; ?> – Offen <?php echo $qOffen; ?> – Fehler <?php echo $qFehler; ?> – Verarbeitet <?php echo $qVerarb; ?>
                    </p>

                    <form method="post" action="?seite=smoke_test" style="margin:0 0 8px 0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="queue_roundtrip">
                        <button type="submit" style="padding:6px 10px; border:1px solid #1565c0; background:#e3f2fd; cursor:pointer;">
                            Queue-Roundtrip testen (DO 1)
                        </button>
                        <span class="status-small" style="margin-left:8px;">Erzeugt einen harmlosen Testeintrag in der Queue und stößt die Verarbeitung an.</span>
                    </form>

                    <?php $latest = $queueUebersicht['latest'] ?? []; ?>
                    <?php if (is_array($latest) && count($latest) > 0): ?>
                        <details>
                            <summary class="status-small"><strong>Letzte 10 Queue-Einträge anzeigen</strong></summary>
                            <table style="margin-top:8px;">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Aktion</th>
                                    <th>Mitarbeiter</th>
                                    <th>Terminal</th>
                                    <th>Erstellt</th>
                                    <th>Letzte Ausführung</th>
                                    <th>Versuche</th>
                                    <th>Fehler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($latest as $r):
                                    if (!is_array($r)) { continue; }
                                    ?>
                                    <tr>
                                        <td><?php echo (int)($r['id'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['meta_aktion'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['meta_mitarbeiter_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['meta_terminal_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['erstellt_am'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['letzte_ausfuehrung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td><?php echo (int)($r['versuche'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['fehlernachricht_kurz'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php else: ?>
                        <p style="margin:0;"><em>Keine Queue-Einträge vorhanden.</em></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p><em>Keine Queue-Daten verfügbar (DB nicht erreichbar oder Tabelle fehlt).</em></p>
            <?php endif; ?>


            <table>
                <thead>
                <tr>
                    <th>Gruppe</th>
                    <th>Check</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $c):
                    $ok = $c['ok'];
                    $status = ($ok === true) ? 'OK' : (($ok === false) ? 'FEHLT/FAIL' : 'n/a');
                    $style = ($ok === true) ? 'background:#e8f5e9;' : (($ok === false) ? 'background:#ffebee;' : 'background:#fffde7;');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$c['gruppe'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$c['titel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$c['details'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Feiertag-Seed-Check (bundesweit)</h3>
            <p>
                Dieser Check prüft, ob die <strong>bundeseinheitliche Grundmenge</strong> für ein Jahr in der Tabelle <code>feiertag</code>
                vorhanden ist. Fehlende Einträge werden dabei (wie im Livebetrieb) <strong>idempotent</strong> nachgezogen.
            </p>

            <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
                <input type="hidden" name="feiertag_seed_run" value="1">
                <label for="feiertag_seed_jahr"><strong>Jahr</strong>:</label>
                <input type="number" id="feiertag_seed_jahr" name="feiertag_seed_jahr"
                       value="<?php echo (int)$feiertagSeedJahr; ?>" style="width: 110px;">
                <button type="submit">Prüfen</button>
            </form>

            <?php if (is_string($feiertagSeedHinweis) && $feiertagSeedHinweis !== ''): ?>
                <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($feiertagSeedHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($feiertagSeedErgebnis)):
                $ok = $feiertagSeedErgebnis['ok'] ?? null;
                $isOk = ($ok === true);
                $isFail = ($ok === false);
                $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
                $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
                $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
                $missing = $feiertagSeedErgebnis['missing'] ?? [];
                $extra = $feiertagSeedErgebnis['extra'] ?? [];
                ?>
                <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
                    <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> <?php echo htmlspecialchars((string)($feiertagSeedErgebnis['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <ul style="margin:0 0 6px 0;">
                        <li>Jahr: <?php echo (int)($feiertagSeedErgebnis['jahr'] ?? 0); ?></li>
                        <li>Erwartet: <?php echo (int)($feiertagSeedErgebnis['erwartet'] ?? 0); ?></li>
                        <li>Vorhanden: <?php echo (int)($feiertagSeedErgebnis['vorhanden'] ?? 0); ?></li>
                    </ul>

                    <?php if (is_array($missing) && $missing !== []): ?>
                        <p style="margin:6px 0 4px 0;"><strong>Fehlend:</strong></p>
                        <ul style="margin:0 0 6px 0;">
                            <?php foreach ($missing as $m):
                                $md = htmlspecialchars((string)($m['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $mn = htmlspecialchars((string)($m['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                                <li><?php echo $md; ?> – <?php echo $mn; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (is_array($extra) && $extra !== []): ?>
                        <p style="margin:6px 0 4px 0;"><strong>Zusätzlich vorhanden (nicht in der Grundmenge):</strong></p>
                        <ul style="margin:0;">
                            <?php foreach ($extra as $d): ?>
                                <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h3>Manuelle Klick-Checkliste (danach Bugfixes ableiten)</h3>
            <ul>
                <li><strong>Backend Login</strong>: Einloggen, Dashboard öffnen.</li>
                <li><strong>Terminal Health</strong>: <code>terminal.php?aktion=health</code> (JSON) aufrufen.</li>
                <li><strong>Terminal Kommen/Gehen</strong>: einmal stempeln (online), dann DB kurz weg und erneut (Offline-Queue), danach Queue im Backend verarbeiten.</li>
                <li><strong>Auftrag Start/Stop</strong>: Hauptauftrag starten/stoppen (online + offline).</li>
                <li><strong>Monatsreport</strong>: Monatsübersicht öffnen, PDF erzeugen, ggf. Sammel-Export.</li>
                <li><strong>PDF-Quick-Check</strong>: Hier im Smoke-Test Jahr/Monat wählen und prüfen (Header/EOF).</li>
            </ul>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }
}
