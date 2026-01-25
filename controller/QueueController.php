<?php
declare(strict_types=1);

/**
 * QueueController
 *
 * Backend-Controller für die Anzeige der Offline-Queue.
 *
 * Erste Ausbaustufe:
 * - Nur Lesezugriff
 * - Optionaler Filter nach Status (offen/verarbeitet/fehler)
 */
class QueueController
{
    private const CSRF_KEY = 'queue_admin_csrf_token';
    private const FLASH_REPORT_KEY = 'queue_admin_flash_report';

    private AuthService $authService;
    private QueueService $queueService;
    private Database $datenbank;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->queueService = QueueService::getInstanz();
        $this->datenbank     = Database::getInstanz();
    }

    /**
     * Holt oder erzeugt ein CSRF-Token für Queue-Admin-POST-Formulare.
     */
    private function holeOderErzeugeCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (\Throwable) {
                $token = bin2hex((string)mt_rand());
            }
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return (string)$token;
    }

    /**
     * Ermittelt die passende Verbindung für die Queue-Tabelle.
     *
     * Wenn eine Offline-DB konfiguriert und erreichbar ist, wird diese verwendet.
     * Andernfalls wird die Hauptdatenbank genutzt.
     */
    private function holeQueueVerbindung(): \PDO
    {
        $offline = $this->datenbank->getOfflineVerbindung();
        if ($offline instanceof \PDO) {
            return $offline;
        }

        return $this->datenbank->getVerbindung();
    }

    /**
     * Holt Einträge nach Status direkt aus der Queue-DB.
     *
     * Für "offen" und "fehler" existieren Service-Methoden.
     * "verarbeitet" wird hier direkt abgefragt.
     *
     * @return array<int,array<string,mixed>>
     */
    private function holeNachStatusDirekt(string $status, int $limit = 200): array
    {
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 1;
        }

        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = :status
                ORDER BY erstellt_am ASC, id ASC
                LIMIT ' . $limit;

        try {
            $pdo = $this->holeQueueVerbindung();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->execute();

            $daten = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($daten) ? $daten : [];
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden von Queue-Einträgen', [
                    'status'    => $status,
                    'exception' => $e->getMessage(),
                ], null, null, 'offline_queue');
            }
            return [];
        }
    }


    /**
     * Zählt Queue-Einträge für einen bestimmten Status.
     */
    private function zaehleNachStatus(string $status): int
    {
        $sql = 'SELECT COUNT(*) AS anzahl FROM db_injektionsqueue WHERE status = :status';

        try {
            $pdo = $this->holeQueueVerbindung();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (int)($row['anzahl'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Holt den zuletzt aktualisierten Fehler-Eintrag (optional seit einem Timestamp).
     *
     * @return array<string,mixed>|null
     */
    private function holeLetztenFehlerEintrag(?string $seitTs = null): ?array
    {
        $sql = 'SELECT id, fehlernachricht, meta_aktion, letzte_ausfuehrung
                FROM db_injektionsqueue
                WHERE status = \'fehler\'';

        if (is_string($seitTs) && $seitTs !== '') {
            // ISO-Format (Y-m-d H:i:s) ist lexikografisch vergleichbar (MySQL + SQLite).
            $sql .= ' AND letzte_ausfuehrung >= :seitTs';
        }

        $sql .= ' ORDER BY letzte_ausfuehrung DESC, id DESC LIMIT 1';

        try {
            $pdo = $this->holeQueueVerbindung();
            $stmt = $pdo->prepare($sql);

            if (is_string($seitTs) && $seitTs !== '') {
                $stmt->bindValue(':seitTs', $seitTs, \PDO::PARAM_STR);
            }

            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                return null;
            }

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Markiert einen Queue-Eintrag als verarbeitet.
     *
     * Hinweis: Diese Admin-Operation muss sowohl mit MySQL/MariaDB als auch
     * mit SQLite (Terminal-Offlinedb) funktionieren.
     */
    private function markiereAlsVerarbeitet(\PDO $queuePdo, int $id): void
    {
        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'UPDATE db_injektionsqueue
                SET status = \'verarbeitet\',
                    fehlernachricht = NULL,
                    letzte_ausfuehrung = :ts,
                    versuche = versuche + 1
                WHERE id = :id';

        $stmt = $queuePdo->prepare($sql);
        $stmt->bindValue(':ts', $ts, \PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Markiert einen Queue-Eintrag als Fehler und speichert die Fehlermeldung.
     */
    private function markiereAlsFehler(\PDO $queuePdo, int $id, string $fehlerNachricht): void
    {
        $fehlerNachricht = (string)$fehlerNachricht;
        if (strlen($fehlerNachricht) > 1000) {
            $fehlerNachricht = substr($fehlerNachricht, 0, 1000);
        }

        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'UPDATE db_injektionsqueue
                SET status = \'fehler\',
                    fehlernachricht = :fehlernachricht,
                    letzte_ausfuehrung = :ts,
                    versuche = versuche + 1
                WHERE id = :id';

        $stmt = $queuePdo->prepare($sql);
        $stmt->bindValue(':fehlernachricht', $fehlerNachricht, \PDO::PARAM_STR);
        $stmt->bindValue(':ts', $ts, \PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Versucht, einen einzelnen Queue-Eintrag manuell (Retry) gegen die Haupt-DB auszuführen.
     *
     * @return string Meldungs-Code für die UI
     */
    private function retryEintrag(int $id): string
    {
        if ($id < 1) {
            return 'eintrag_nicht_gefunden';
        }

        if (!$this->datenbank->istHauptdatenbankVerfuegbar()) {
            return 'hauptdb_offline';
        }

        try {
            $queuePdo = $this->holeQueueVerbindung();

            $stmt = $queuePdo->prepare('SELECT * FROM db_injektionsqueue WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $eintrag = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($eintrag === false) {
                return 'eintrag_nicht_gefunden';
            }

            $sqlBefehl = trim((string)($eintrag['sql_befehl'] ?? ''));
            if ($sqlBefehl === '') {
                $this->markiereAlsFehler($queuePdo, $id, 'Leerer SQL-Befehl in db_injektionsqueue.');
                return 'retry_fehler';
            }

            $hauptPdo = $this->datenbank->getVerbindung();

            try {
                $hauptPdo->beginTransaction();
                $hauptPdo->exec($sqlBefehl);
                $hauptPdo->commit();

                $this->markiereAlsVerarbeitet($queuePdo, $id);
                return 'retry_ok';
            } catch (\Throwable $e) {
                if ($hauptPdo->inTransaction()) {
                    $hauptPdo->rollBack();
                }

                $this->markiereAlsFehler($queuePdo, $id, $e->getMessage());

                if (class_exists('Logger')) {
                    Logger::error(
                        'Queue Admin: Retry fehlgeschlagen',
                        ['id' => $id, 'exception' => $e->getMessage()],
                        null,
                        null,
                        'offline_queue'
                    );
                }

                return 'retry_fehler';
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Queue Admin: Fehler beim Retry-Flow', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'offline_queue');
            }

            return 'retry_fehler';
        }
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Queue-Ansicht nutzen darf.
     *
     * Primär wird das Recht `QUEUE_VERWALTEN` geprüft.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('QUEUE_VERWALTEN')) {
            return true;
        }

        // Legacy-Fallback: Rollen (für Bestandsinstallationen ohne gepflegte Rechtezuordnung)
        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Offline-Queue anzuzeigen.</p>';

        return false;
    }

    /**
     * Einfache Listenansicht der Queue.
     *
     * Unterstützt einen Filter nach Status (?status=offen|verarbeitet|fehler).
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $status = isset($_GET['status']) ? (string)$_GET['status'] : 'offen';
        $status = in_array($status, ['offen', 'verarbeitet', 'fehler'], true) ? $status : 'offen';

        // CSRF-Token für die View.
        $csrfToken = $this->holeOderErzeugeCsrfToken();

        // Admin-Aktionen (POST + CSRF):
        // - Queue verarbeiten (manuell anstoßen)
        // - fehlerhaften Eintrag erneut ausführen (Retry)
        // - fehlerhaften Eintrag ignorieren/löschen
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $aktion = isset($_POST['aktion']) ? (string)$_POST['aktion'] : '';
            $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;

            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
                header('Location: ?seite=queue_admin&status=' . urlencode($status) . '&meldung=csrf_ungueltig');
                exit;
            }

            if ($aktion === 'queue_verarbeiten') {
                if (!$this->datenbank->istHauptdatenbankVerfuegbar()) {
                    header('Location: ?seite=queue_admin&status=' . urlencode($status) . '&meldung=hauptdb_offline');
                    exit;
                }

                // Vorher/Nachher-Zählung, damit der Admin ein echtes Ergebnis bekommt (T-060).
                $startTs = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
                $startMicro = microtime(true);

                $offenVorher = $this->zaehleNachStatus('offen');
                $fehlerVorher = $this->zaehleNachStatus('fehler');

                $this->queueService->verarbeiteOffeneEintraege();

                $dauerMs = (int)round((microtime(true) - $startMicro) * 1000);

                $offenNachher = $this->zaehleNachStatus('offen');
                $fehlerNachher = $this->zaehleNachStatus('fehler');

                $versucht = max(0, $offenVorher - $offenNachher);
                $neuFehler = max(0, $fehlerNachher - $fehlerVorher);
                $ok = max(0, $versucht - $neuFehler);

                $letzterFehler = null;
                if ($neuFehler > 0) {
                    // Primär: Fehler, die während dieser Verarbeitung entstanden sind.
                    $letzterFehler = $this->holeLetztenFehlerEintrag($startTs);

                    // Fallback: irgendein Fehler (falls Timestamp-Vergleich nicht greift).
                    if ($letzterFehler === null) {
                        $letzterFehler = $this->holeLetztenFehlerEintrag(null);
                    }
                }

                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $_SESSION[self::FLASH_REPORT_KEY] = [
                    'offen_vorher' => $offenVorher,
                    'offen_nachher' => $offenNachher,
                    'versucht' => $versucht,
                    'ok' => $ok,
                    'neu_fehler' => $neuFehler,
                    'dauer_ms' => $dauerMs,
                    'fehler_id' => is_array($letzterFehler) ? (int)($letzterFehler['id'] ?? 0) : 0,
                    'fehler_aktion' => is_array($letzterFehler) ? (string)($letzterFehler['meta_aktion'] ?? '') : '',
                    'fehler_nachricht' => is_array($letzterFehler) ? (string)($letzterFehler['fehlernachricht'] ?? '') : '',
                ];

                // Zurück zur Liste (GET), damit Reload nicht erneut auslöst.
                header('Location: ?seite=queue_admin&status=' . urlencode($status) . '&meldung=queue_verarbeitet');
                exit;
            }

            if ($aktion === 'retry') {
                $meldungCode = $this->retryEintrag($id);
                header('Location: ?seite=queue_admin&status=' . urlencode($status) . '&meldung=' . urlencode($meldungCode));
                exit;
            }

            if ($aktion === 'loeschen' && $id > 0) {
                $this->queueService->loescheEintrag($id);

                // Zurück zur Liste (GET), damit Reload nicht erneut löscht.
                header('Location: ?seite=queue_admin&status=' . urlencode($status) . '&meldung=eintrag_geloescht');
                exit;
            }
        }

        // Liste laden
        $eintraege = [];
        switch ($status) {
            case 'fehler':
                $eintraege = $this->queueService->holeFehlerEintraege(200);
                break;
            case 'verarbeitet':
                $eintraege = $this->holeNachStatusDirekt('verarbeitet', 200);
                break;
            case 'offen':
            default:
                $eintraege = $this->queueService->holeOffeneEintraege(200);
                break;
        }

        $aktuellerStatusFilter = $status;
        $meldung = isset($_GET['meldung']) ? (string)$_GET['meldung'] : '';

        // Flash-Report der letzten Verarbeitung (T-060).
        $queueReport = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION[self::FLASH_REPORT_KEY]) && is_array($_SESSION[self::FLASH_REPORT_KEY])) {
            $queueReport = $_SESSION[self::FLASH_REPORT_KEY];
            unset($_SESSION[self::FLASH_REPORT_KEY]);
        }

        require __DIR__ . '/../views/queue/liste.php';
    }
}

