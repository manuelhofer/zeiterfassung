<?php
declare(strict_types=1);

/**
 * AuftragszeitService
 *
 * Geschäftslogik für Start/Stop von Auftragszeiten (`auftragszeit`).
 *
 * Aktuell nur als Skelett angelegt – die eigentliche Anwendungslogik
 * (gleichzeitige Nebenaufträge, Validierungen, automatische Stopps usw.)
 * wird später ergänzt.
 */
class AuftragszeitService
{
    private static ?AuftragszeitService $instanz = null;

    private AuftragszeitModel $auftragszeitModel;

    private function __construct()
    {
        $this->auftragszeitModel = new AuftragszeitModel();
    }

    /**
     * Terminal-Installationserkennung (für Offline-Queue-Logik).
     */
    private function istTerminalInstallation(): bool
    {
        $pfad = __DIR__ . '/../config/config.php';
        if (!is_file($pfad)) {
            return false;
        }

        try {
            /** @var array<string,mixed> $cfg */
            $cfg = require $pfad;
        } catch (\Throwable $e) {
            return false;
        }

        $typ = $cfg['app']['installation_typ'] ?? null;
        return is_string($typ) && strtolower(trim($typ)) === 'terminal';
    }

    private function sqlQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function sqlNullableString(?string $value, int $maxLen = 255): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = trim($value);
        if ($value === '') {
            return 'NULL';
        }

        if ($maxLen > 0 && strlen($value) > $maxLen) {
            $value = substr($value, 0, $maxLen);
        }

        return $this->sqlQuote($value);
    }

    private function sqlNullableInt(?int $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return (string)max(0, (int)$value);
    }

    private function findeOderErstelleArbeitsschritt(?int $auftragId, ?string $arbeitsschrittCode): ?int
    {
        $auftragId = $auftragId !== null ? (int)$auftragId : 0;
        $arbeitsschrittCode = $arbeitsschrittCode !== null ? trim($arbeitsschrittCode) : '';
        if ($auftragId <= 0 || $arbeitsschrittCode === '' || !class_exists('Database')) {
            return null;
        }

        try {
            $db = Database::getInstanz();
            $row = $db->fetchEine(
                'SELECT id FROM auftrag_arbeitsschritt WHERE auftrag_id = :auftrag_id AND arbeitsschritt_code = :code LIMIT 1',
                ['auftrag_id' => $auftragId, 'code' => $arbeitsschrittCode]
            );

            if (is_array($row) && isset($row['id'])) {
                return (int)$row['id'];
            }

            $db->ausfuehren(
                'INSERT INTO auftrag_arbeitsschritt (auftrag_id, arbeitsschritt_code, aktiv)
                 VALUES (:auftrag_id, :code, 1)
                 ON DUPLICATE KEY UPDATE arbeitsschritt_code = arbeitsschritt_code',
                ['auftrag_id' => $auftragId, 'code' => $arbeitsschrittCode]
            );

            $row = $db->fetchEine(
                'SELECT id FROM auftrag_arbeitsschritt WHERE auftrag_id = :auftrag_id AND arbeitsschritt_code = :code LIMIT 1',
                ['auftrag_id' => $auftragId, 'code' => $arbeitsschrittCode]
            );

            if (is_array($row) && isset($row['id'])) {
                return (int)$row['id'];
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('AuftragszeitService: Arbeitsschritt konnte nicht angelegt werden', [
                    'auftrag_id' => $auftragId,
                    'arbeitsschritt_code' => $arbeitsschrittCode,
                    'exception' => $e->getMessage(),
                ], null, null, 'auftragszeit_service');
            }
        }

        return null;
    }

    /**
     * OPTIONAL (aktuell nicht genutzt): Nebenauftraege eines Mitarbeiters per Update beenden.
     *
     * Hinweis Praxis-Test:
     * - Nebenauftraege werden **nicht automatisch** gestoppt, wenn ein Hauptauftrag gestartet/gestoppt wird.
     * - Mitarbeiter stoppen Nebenauftraege bewusst manuell.
     *
     * Soft-fail: darf keinen Ablauf blockieren.
     */
    private function beendeLaufendeNebenauftraegeOnline(int $mitarbeiterId, \DateTimeImmutable $zeitpunkt, string $status): void
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return;
        }

        if (!in_array($status, ['abgeschlossen', 'abgebrochen', 'pausiert'], true)) {
            $status = 'abgeschlossen';
        }

        if (!class_exists('Database')) {
            return;
        }

        try {
            $db = Database::getInstanz();
            $db->ausfuehren(
                "UPDATE auftragszeit\n                 SET endzeit = :endzeit, status = :status\n                 WHERE mitarbeiter_id = :mid\n                   AND typ = 'neben'\n                   AND status = 'laufend'\n                   AND endzeit IS NULL",
                [
                    'endzeit' => $zeitpunkt->format('Y-m-d H:i:s'),
                    'status'  => $status,
                    'mid'     => $mitarbeiterId,
                ]
            );
        } catch (\Throwable $e) {
            // Soft-fail
        }
    }

    public static function getInstanz(): AuftragszeitService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Startet eine neue Auftragszeit als Hauptauftrag.
     *
     * - Schließt automatisch alle aktuell laufenden Hauptaufträge des Mitarbeiters.
     * - Versucht optional, zu einem bekannten Auftragscode die `auftrag_id` zu laden.
     *
     * @return int|null ID der neuen Auftragszeit oder null bei Fehler/ungültigen Parametern
     */
    public function starteAuftrag(int $mitarbeiterId, string $auftragscode, ?int $maschineId = null, ?string $arbeitsschrittCode = null): ?int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        $auftragscode  = trim($auftragscode);
        $arbeitsschrittCode = $arbeitsschrittCode !== null ? trim($arbeitsschrittCode) : null;
        if ($arbeitsschrittCode === '') {
            $arbeitsschrittCode = null;
        }

        if ($mitarbeiterId <= 0 || $auftragscode === '') {
            return null;
        }

        $startzeit = new \DateTimeImmutable('now');
        $auftragId = null;

        // ------------------------------------------------------------
        // Offline-Queue (Terminal):
        // Wenn die Hauptdatenbank nicht erreichbar ist, speichern wir die
        // notwendigen SQL-Befehle in `db_injektionsqueue`, damit das Terminal
        // weiter arbeiten kann (analog ZeitService).
        // ------------------------------------------------------------

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        if ($this->istTerminalInstallation() && $hauptDbOk === false) {
            $zeitStr = $startzeit->format('Y-m-d H:i:s');

            // 1) laufende Hauptaufträge des Mitarbeiters schließen
            $sql1 = 'UPDATE auftragszeit SET '
                . 'endzeit=' . $this->sqlQuote($zeitStr) . ', '
                . "status='abgeschlossen' "
                . 'WHERE mitarbeiter_id=' . (int)$mitarbeiterId
                . " AND typ='haupt' AND status='laufend' AND endzeit IS NULL";


            // 2) Auftrag (Minimaldatensatz) sicherstellen, damit die Buchung spaeter aufloesbar ist
            $sql2 = 'INSERT INTO auftrag (auftragsnummer, aktiv) VALUES ('
                . $this->sqlNullableString($auftragscode, 100) . ', 1)
                ON DUPLICATE KEY UPDATE auftragsnummer = auftragsnummer';

            $sqlSchritt = null;
            $sqlSchrittId = 'NULL';
            if ($arbeitsschrittCode !== null) {
                $auftragIdSql = '(SELECT id FROM auftrag WHERE auftragsnummer = ' . $this->sqlNullableString($auftragscode, 100) . ' LIMIT 1)';
                $sqlSchritt = 'INSERT INTO auftrag_arbeitsschritt (auftrag_id, arbeitsschritt_code, aktiv) VALUES ('
                    . $auftragIdSql . ', '
                    . $this->sqlNullableString($arbeitsschrittCode, 100) . ', 1)
                    ON DUPLICATE KEY UPDATE arbeitsschritt_code = arbeitsschritt_code';
                $sqlSchrittId = '(SELECT id FROM auftrag_arbeitsschritt WHERE auftrag_id = ' . $auftragIdSql
                    . ' AND arbeitsschritt_code = ' . $this->sqlNullableString($arbeitsschrittCode, 100) . ' LIMIT 1)';
            }

            // 3) neuen Hauptauftrag anlegen
            $sql3 = 'INSERT INTO auftragszeit (mitarbeiter_id, auftrag_id, arbeitsschritt_id, auftragscode, arbeitsschritt_code, maschine_id, terminal_id, typ, startzeit, kommentar) VALUES ('
                . (int)$mitarbeiterId . ', '
                . $this->sqlNullableInt($auftragId) . ', '
                . $sqlSchrittId . ', '
                . $this->sqlNullableString($auftragscode, 100) . ', '
                . $this->sqlNullableString($arbeitsschrittCode, 100) . ', '
                . $this->sqlNullableInt($maschineId) . ', '
                . 'NULL, '
                . "'haupt', "
                . $this->sqlQuote($zeitStr) . ', '
                . 'NULL'
                . ')';

            try {
                $ok1 = OfflineQueueManager::getInstanz()->speichereInQueue($sql1, $mitarbeiterId, null, 'auftrag_start_close');
                $ok2 = OfflineQueueManager::getInstanz()->speichereInQueue($sql2, $mitarbeiterId, null, 'auftrag_ensure');
                if ($sqlSchritt !== null) {
                    OfflineQueueManager::getInstanz()->speichereInQueue($sqlSchritt, $mitarbeiterId, null, 'auftrag_schritt_ensure');
                }
                $ok3 = OfflineQueueManager::getInstanz()->speichereInQueue($sql3, $mitarbeiterId, null, 'auftrag_start');

                return ($ok1 && $ok2 && $ok3) ? 0 : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('AuftragszeitService: Offline-Queue Auftrag starten fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'auftragscode'   => $auftragscode,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service_offline');
                }
                return null;
            }
        }

        // Auftrag zu diesem Code aus der Auftragstabelle laden
        try {
            $auftragModel = new AuftragModel();
            $auftrag      = $auftragModel->findeNachCode($auftragscode);

            if (is_array($auftrag) && isset($auftrag['id'])) {
                $auftragId = (int)$auftrag['id'];
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Auftrags im AuftragszeitService (starteAuftrag)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'auftragscode'   => $auftragscode,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftragszeit_service');
            }
        }

        // Falls nicht vorhanden: Minimaldatensatz anlegen (idempotent)
        if ($auftragId === null) {
            try {
                $db2 = Database::getInstanz();
                $sql = 'INSERT INTO auftrag (auftragsnummer, aktiv) VALUES (:nr, 1)
                        ON DUPLICATE KEY UPDATE auftragsnummer = auftragsnummer';
                $db2->ausfuehren($sql, ['nr' => $auftragscode]);

                $auftragModel = new AuftragModel();
                $auftrag      = $auftragModel->findeNachCode($auftragscode);
                if (is_array($auftrag) && isset($auftrag['id'])) {
                    $auftragId = (int)$auftrag['id'];
                }
            } catch (\Throwable $e) {
                // Nicht blockieren: dann bleibt auftrag_id NULL, auftragscode ist trotzdem gespeichert.
                if (class_exists('Logger')) {
                    Logger::warn('AuftragszeitService: Auftrag konnte nicht automatisch angelegt werden', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'auftragscode'   => $auftragscode,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service');
                }
            }
        }

        $arbeitsschrittId = $this->findeOderErstelleArbeitsschritt($auftragId, $arbeitsschrittCode);

        try {
            // Vor dem Start alle laufenden Hauptaufträge des Mitarbeiters beenden
            $this->auftragszeitModel->beendeLaufendeHauptauftraege($mitarbeiterId, $startzeit);

            // Neue Auftragszeit als Hauptauftrag anlegen
            $neueId = $this->auftragszeitModel->erstelleAuftragszeit(
                $mitarbeiterId,
                $auftragId,
                $auftragscode,
                $arbeitsschrittId,
                $arbeitsschrittCode,
                $maschineId,
                null,          // terminal_id – wird später vom Terminal-Subsystem gesetzt
                'haupt',
                $startzeit,
                null           // Kommentar
            );

            return $neueId;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Starten einer Auftragszeit (Service)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'auftragscode'   => $auftragscode,
                    'maschine_id'    => $maschineId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftragszeit_service');
            }

            return null;
        }
    }

    /**
     * Stoppt eine laufende Auftragszeit.
     *
     * Standardfall: Eine konkrete Auftragszeit-ID wird übergeben.
     * Alternativ: Es wird nach laufenden Aufträgen des Mitarbeiters gesucht und
     *             anhand des Auftragscodes oder – falls nur ein Auftrag läuft –
     *             der einzig laufende Auftrag gestoppt.
     *
     * @param int         $mitarbeiterId  Mitarbeiter, für den gestoppt werden soll
     * @param int|null    $auftragszeitId Konkrete ID der Auftragszeit (optional)
     * @param string|null $auftragscode   Auftragscode zur Auswahl (optional)
     * @param string      $status         Zielstatus (`abgeschlossen`, `abgebrochen` oder `pausiert`)
     *
     * @return int|null 1=online erfolgreich, 0=offline in Queue gespeichert, null=Fehler/kein passender Auftrag
     */
    public function stoppeAuftrag(int $mitarbeiterId, ?int $auftragszeitId = null, ?string $auftragscode = null, string $status = 'abgeschlossen'): ?int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        if (!in_array($status, ['abgeschlossen', 'abgebrochen', 'pausiert'], true)) {
            $status = 'abgeschlossen';
        }

        $zeitpunkt = new \DateTimeImmutable('now');

        // ------------------------------------------------------------
        // Offline-Queue (Terminal):
        // Wenn die Hauptdatenbank nicht erreichbar ist, legen wir ein
        // UPDATE in der Queue ab. Auswahl erfolgt über ID oder Code
        // bzw. Fallback: zuletzt laufender Auftrag.
        // ------------------------------------------------------------

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        $auftragscodeTrim = $auftragscode !== null ? trim($auftragscode) : '';

        if ($this->istTerminalInstallation() && $hauptDbOk === false) {
            $endStr = $zeitpunkt->format('Y-m-d H:i:s');

            $sql = 'UPDATE auftragszeit SET '
                . 'endzeit=' . $this->sqlQuote($endStr) . ', '
                . 'status=' . $this->sqlQuote($status) . ' '
                . 'WHERE mitarbeiter_id=' . (int)$mitarbeiterId
                . " AND typ='haupt' AND status='laufend' AND endzeit IS NULL";

            if ($auftragszeitId !== null && $auftragszeitId > 0) {
                $sql .= ' AND id=' . (int)$auftragszeitId;
            } elseif ($auftragscodeTrim !== '') {
                $sql .= ' AND auftragscode=' . $this->sqlQuote($auftragscodeTrim);
            }

            $sql .= ' ORDER BY startzeit DESC, id DESC LIMIT 1';

            try {
                $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                    $sql,
                    $mitarbeiterId,
                    null,
                    $status === 'abgebrochen' ? 'auftrag_stop_abgebrochen' : 'auftrag_stop'
                );

                return $ok ? 0 : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('AuftragszeitService: Offline-Queue Auftrag stoppen fehlgeschlagen', [
                        'mitarbeiter_id'  => $mitarbeiterId,
                        'auftragszeit_id' => $auftragszeitId,
                        'auftragscode'    => $auftragscodeTrim,
                        'status'          => $status,
                        'exception'       => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service_offline');
                }

                return null;
            }
        }
        // 1. Fall: Es wurde explizit eine Auftragszeit-ID übergeben
        // Schutz: Im Kontext "Hauptauftrag stoppen" darf nur typ='haupt' beendet werden.
        if ($auftragszeitId !== null && $auftragszeitId > 0) {
            $row = $this->auftragszeitModel->holeNachId($auftragszeitId);
            if (!is_array($row)) {
                return null;
            }
            $rowMit = isset($row['mitarbeiter_id']) ? (int)$row['mitarbeiter_id'] : 0;
            $rowTyp = isset($row['typ']) ? (string)$row['typ'] : '';
            $rowStatus = isset($row['status']) ? (string)$row['status'] : '';
            $rowEnd = $row['endzeit'] ?? null;

            if ($rowMit !== $mitarbeiterId || $rowTyp !== 'haupt' || $rowStatus !== 'laufend' || $rowEnd !== null) {
                return null;
            }

            $ok = $this->auftragszeitModel->beendeAuftragszeit($auftragszeitId, $zeitpunkt, $status);
            if ($ok) {
                return 1;
            }
            return null;
        }

        $auftragscode = $auftragscodeTrim;

        try {
            $laufende = $this->auftragszeitModel->holeLaufendeFuerMitarbeiter($mitarbeiterId);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden laufender Aufträge im AuftragszeitService (stoppeAuftrag)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftragszeit_service');
            }

            return null;
        }

        if (!is_array($laufende) || count($laufende) === 0) {
            return null;
        }

        // Nur Hauptaufträge berücksichtigen (Nebenaufträge werden separat gestoppt)
        $laufendeHaupt = [];
        foreach ($laufende as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['typ'] ?? '') === 'haupt') {
                $laufendeHaupt[] = $row;
            }
        }

        if (count($laufendeHaupt) === 0) {
            return null;
        }

        $laufende = $laufendeHaupt;

        // 2. Fall: Auftragscode vorhanden – passenden laufenden Auftrag suchen
        $ziel = null;
        if ($auftragscode !== '') {
            foreach ($laufende as $row) {
                $codeRow = isset($row['auftragscode']) ? (string)$row['auftragscode'] : '';
                if ($codeRow !== '' && strcasecmp($codeRow, $auftragscode) === 0) {
                    $ziel = $row;
                    break;
                }
            }
        }

        // 3. Fallback: Wenn kein passender Code gefunden wurde, aber genau ein Auftrag läuft
        if ($ziel === null && count($laufende) === 1) {
            $ziel = $laufende[0];
        }

        if ($ziel === null || !isset($ziel['id'])) {
            return null;
        }

        $zielId = (int)$ziel['id'];
        if ($zielId <= 0) {
            return null;
        }

        $ok = $this->auftragszeitModel->beendeAuftragszeit($zielId, $zeitpunkt, $status);
        if ($ok) {
            return 1;
        }
        return null;
    }

    /**
     * Stoppt alle laufenden Aufträge (mindestens Hauptaufträge) eines Mitarbeiters.
     *
     * @param int                $mitarbeiterId Mitarbeiter-ID
     * @param \DateTimeImmutable $zeitpunkt      Endzeitpunkt
     * @param string             $status         Zielstatus (abgeschlossen/abgebrochen/pausiert)
     *
     * @return int|null 1=online erfolgreich, 0=offline in Queue gespeichert, null=Fehler
     */
    public function stoppeAlleLaufendenAuftraegeFuerMitarbeiter(int $mitarbeiterId, \DateTimeImmutable $zeitpunkt, string $status = 'abgeschlossen'): ?int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        if (!in_array($status, ['abgeschlossen', 'abgebrochen', 'pausiert'], true)) {
            $status = 'abgeschlossen';
        }

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        if ($this->istTerminalInstallation() && $hauptDbOk === false) {
            $endStr = $zeitpunkt->format('Y-m-d H:i:s');
            $sql = 'UPDATE auftragszeit SET '
                . 'endzeit=' . $this->sqlQuote($endStr) . ', '
                . 'status=' . $this->sqlQuote($status) . ' '
                . 'WHERE mitarbeiter_id=' . (int)$mitarbeiterId
                . " AND typ IN ('haupt','neben') AND status='laufend' AND endzeit IS NULL";

            try {
                $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                    $sql,
                    $mitarbeiterId,
                    null,
                    'auftrag_stop_alle'
                );

                return $ok ? 0 : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('AuftragszeitService: Offline-Queue Aufträge stoppen fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'status'         => $status,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service_offline');
                }

                return null;
            }
        }

        try {
            $dbOnline = Database::getInstanz();
            $sql = 'UPDATE auftragszeit
                    SET endzeit = :endzeit,
                        status  = :status
                    WHERE mitarbeiter_id = :mitarbeiter_id
                      AND typ IN (\'haupt\', \'neben\')
                      AND status = \'laufend\'
                      AND endzeit IS NULL';

            $dbOnline->ausfuehren($sql, [
                'endzeit'        => $zeitpunkt->format('Y-m-d H:i:s'),
                'status'         => $status,
                'mitarbeiter_id' => $mitarbeiterId,
            ]);

            return 1;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Beenden laufender Aufträge (Service)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'status'         => $status,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftragszeit_service');
            }

            return null;
        }
    }

    /**
     * Stoppt alle laufenden Hauptaufträge eines Mitarbeiters.
     *
     * @param int                $mitarbeiterId Mitarbeiter-ID
     * @param \DateTimeImmutable $zeitpunkt      Endzeitpunkt
     * @param string             $status         Zielstatus (abgeschlossen/abgebrochen/pausiert)
     *
     * @return int|null 1=online erfolgreich, 0=offline in Queue gespeichert, null=Fehler
     */
    public function stoppeLaufendeHauptauftraegeFuerMitarbeiter(int $mitarbeiterId, \DateTimeImmutable $zeitpunkt, string $status = 'abgeschlossen'): ?int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        if (!in_array($status, ['abgeschlossen', 'abgebrochen', 'pausiert'], true)) {
            $status = 'abgeschlossen';
        }

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        if ($this->istTerminalInstallation() && $hauptDbOk === false) {
            $endStr = $zeitpunkt->format('Y-m-d H:i:s');
            $sql = 'UPDATE auftragszeit SET '
                . 'endzeit=' . $this->sqlQuote($endStr) . ', '
                . 'status=' . $this->sqlQuote($status) . ' '
                . 'WHERE mitarbeiter_id=' . (int)$mitarbeiterId
                . " AND typ='haupt' AND status='laufend' AND endzeit IS NULL";

            try {
                $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                    $sql,
                    $mitarbeiterId,
                    null,
                    'auftrag_stop_haupt'
                );

                return $ok ? 0 : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('AuftragszeitService: Offline-Queue Hauptaufträge stoppen fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'status'         => $status,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service_offline');
                }

                return null;
            }
        }

        try {
            $dbOnline = Database::getInstanz();
            $sql = 'UPDATE auftragszeit
                    SET endzeit = :endzeit,
                        status  = :status
                    WHERE mitarbeiter_id = :mitarbeiter_id
                      AND typ = \'haupt\'
                      AND status = \'laufend\'
                      AND endzeit IS NULL';

            $dbOnline->ausfuehren($sql, [
                'endzeit'        => $zeitpunkt->format('Y-m-d H:i:s'),
                'status'         => $status,
                'mitarbeiter_id' => $mitarbeiterId,
            ]);

            return 1;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Beenden laufender Hauptaufträge (Service)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'status'         => $status,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftragszeit_service');
            }

            return null;
        }
    }

    /**
     * Startet den zuletzt pausierten Hauptauftrag eines Mitarbeiters erneut.
     *
     * @return array<string,mixed>|null Metadaten der neuen Auftragszeit oder null, wenn nichts fortgesetzt wurde
     */
    public function starteLetztenPausiertenHauptauftrag(int $mitarbeiterId, \DateTimeImmutable $zeitpunkt, string $kommentar = 'automatisch fortgesetzt'): ?array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        if ($this->istTerminalInstallation() && $hauptDbOk === false) {
            $zeitStr = $zeitpunkt->format('Y-m-d H:i:s');
            $sql = 'INSERT INTO auftragszeit (mitarbeiter_id, auftrag_id, arbeitsschritt_id, auftragscode, arbeitsschritt_code, maschine_id, terminal_id, typ, startzeit, kommentar) '
                . 'SELECT az.mitarbeiter_id, az.auftrag_id, az.arbeitsschritt_id, az.auftragscode, az.arbeitsschritt_code, az.maschine_id, az.terminal_id, '
                . "'haupt', "
                . $this->sqlQuote($zeitStr) . ', '
                . $this->sqlNullableString($kommentar, 255)
                . ' FROM auftragszeit az '
                . 'WHERE az.mitarbeiter_id=' . (int)$mitarbeiterId
                . " AND az.status='pausiert' AND az.typ='haupt'"
                . ' AND NOT EXISTS (SELECT 1 FROM auftragszeit laufend'
                . ' WHERE laufend.mitarbeiter_id=az.mitarbeiter_id AND laufend.status=\'laufend\' AND laufend.endzeit IS NULL)'
                . ' ORDER BY az.endzeit DESC, az.id DESC'
                . ' LIMIT 1';

            try {
                $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                    $sql,
                    $mitarbeiterId,
                    null,
                    'auftrag_fortsetzen'
                );

                return $ok ? ['id' => 0, 'typ' => 'haupt', 'queued' => true] : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('AuftragszeitService: Offline-Queue Auftrag fortsetzen fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'exception' => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service_offline');
                }

                return null;
            }
        }

        $laufende = $this->auftragszeitModel->holeLaufendeFuerMitarbeiter($mitarbeiterId);
        if (is_array($laufende) && count($laufende) > 0) {
            return null;
        }

        $pause = $this->auftragszeitModel->holeLetztePausierteFuerMitarbeiter($mitarbeiterId, 'haupt');
        if (!is_array($pause)) {
            return null;
        }

        $auftragscode = isset($pause['auftragscode']) ? trim((string)$pause['auftragscode']) : '';
        if ($auftragscode === '') {
            return null;
        }

        $typ = 'haupt';
        $auftragId = isset($pause['auftrag_id']) ? (int)$pause['auftrag_id'] : null;
        $arbeitsschrittId = isset($pause['arbeitsschritt_id']) ? (int)$pause['arbeitsschritt_id'] : null;
        $arbeitsschrittCode = isset($pause['arbeitsschritt_code']) ? trim((string)$pause['arbeitsschritt_code']) : null;
        if ($arbeitsschrittCode === '') {
            $arbeitsschrittCode = null;
        }
        $maschineId = isset($pause['maschine_id']) ? (int)$pause['maschine_id'] : null;
        $terminalId = isset($pause['terminal_id']) ? (int)$pause['terminal_id'] : null;

        $neueId = $this->auftragszeitModel->erstelleAuftragszeit(
            $mitarbeiterId,
            $auftragId,
            $auftragscode,
            $arbeitsschrittId,
            $arbeitsschrittCode,
            $maschineId,
            $terminalId,
            $typ,
            $zeitpunkt,
            $kommentar
        );

        if ($neueId === null) {
            return null;
        }

        return [
            'id' => (int)$neueId,
            'auftragscode' => $auftragscode,
            'arbeitsschritt_code' => $arbeitsschrittCode,
            'typ' => $typ,
            'queued' => false,
        ];
    }

    /**
     * Startet pausierte Aufträge eines Mitarbeiters erneut.
     *
     * Regel: Alle pausierten Aufträge (Haupt + Neben) werden fortgesetzt.
     *
     * @return array<string,mixed>|null Metadaten der Fortsetzungen oder null, wenn nichts fortgesetzt wurde
     */
    public function startePausierteAuftraegeFuerMitarbeiter(
        int $mitarbeiterId,
        \DateTimeImmutable $zeitpunkt,
        string $kommentar = 'automatisch fortgesetzt'
    ): ?array {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        if ($this->istTerminalInstallation() && $hauptDbOk === false) {
            $zeitStr = $zeitpunkt->format('Y-m-d H:i:s');
            $sql = 'INSERT INTO auftragszeit (mitarbeiter_id, auftrag_id, arbeitsschritt_id, auftragscode, arbeitsschritt_code, maschine_id, terminal_id, typ, startzeit, kommentar) '
                . 'SELECT az.mitarbeiter_id, az.auftrag_id, az.arbeitsschritt_id, az.auftragscode, az.arbeitsschritt_code, az.maschine_id, az.terminal_id, '
                . 'az.typ, '
                . $this->sqlQuote($zeitStr) . ', '
                . $this->sqlNullableString($kommentar, 255)
                . ' FROM auftragszeit az '
                . 'WHERE az.mitarbeiter_id=' . (int)$mitarbeiterId
                . " AND az.status='pausiert'"
                . ' ORDER BY az.endzeit DESC, az.id DESC';

            try {
                $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                    $sql,
                    $mitarbeiterId,
                    null,
                    'auftrag_fortsetzen'
                );

                return $ok ? ['queued' => true, 'anzahl' => 0, 'auftraege' => []] : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('AuftragszeitService: Offline-Queue Auftragsfortsetzung fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'exception' => $e->getMessage(),
                    ], $mitarbeiterId, null, 'auftragszeit_service_offline');
                }

                return null;
            }
        }

        $pausen = $this->auftragszeitModel->holePausierteFuerMitarbeiter($mitarbeiterId);
        if ($pausen === []) {
            return null;
        }

        $fortgesetzt = [];
        $anzahl = 0;

        foreach ($pausen as $pause) {
            $auftragscode = isset($pause['auftragscode']) ? trim((string)$pause['auftragscode']) : '';
            if ($auftragscode === '') {
                continue;
            }

            $typ = isset($pause['typ']) ? trim((string)$pause['typ']) : 'haupt';
            if (!in_array($typ, ['haupt', 'neben'], true)) {
                $typ = 'haupt';
            }

            $auftragId = isset($pause['auftrag_id']) ? (int)$pause['auftrag_id'] : null;
            $arbeitsschrittId = isset($pause['arbeitsschritt_id']) ? (int)$pause['arbeitsschritt_id'] : null;
            $arbeitsschrittCode = isset($pause['arbeitsschritt_code']) ? trim((string)$pause['arbeitsschritt_code']) : null;
            if ($arbeitsschrittCode === '') {
                $arbeitsschrittCode = null;
            }
            $maschineId = isset($pause['maschine_id']) ? (int)$pause['maschine_id'] : null;
            $terminalId = isset($pause['terminal_id']) ? (int)$pause['terminal_id'] : null;

            $neueId = $this->auftragszeitModel->erstelleAuftragszeit(
                $mitarbeiterId,
                $auftragId,
                $auftragscode,
                $arbeitsschrittId,
                $arbeitsschrittCode,
                $maschineId,
                $terminalId,
                $typ,
                $zeitpunkt,
                $kommentar
            );

            if ($neueId === null) {
                continue;
            }

            $anzahl++;
            $fortgesetzt[] = [
                'id' => (int)$neueId,
                'auftragscode' => $auftragscode,
                'arbeitsschritt_code' => $arbeitsschrittCode,
                'typ' => $typ,
            ];
        }

        if ($anzahl === 0) {
            return null;
        }

        return [
            'queued' => false,
            'anzahl' => $anzahl,
            'auftraege' => $fortgesetzt,
        ];
    }
}
