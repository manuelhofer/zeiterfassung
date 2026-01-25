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

        if (!in_array($status, ['abgeschlossen', 'abgebrochen'], true)) {
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

            // 3) neuen Hauptauftrag anlegen
            $sql3 = 'INSERT INTO auftragszeit (mitarbeiter_id, auftrag_id, auftragscode, arbeitsschritt_code, maschine_id, terminal_id, typ, startzeit, kommentar) VALUES ('
                . (int)$mitarbeiterId . ', '
                . $this->sqlNullableInt($auftragId) . ', '
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

        try {
            // Vor dem Start alle laufenden Hauptaufträge des Mitarbeiters beenden
            $this->auftragszeitModel->beendeLaufendeHauptauftraege($mitarbeiterId, $startzeit);

            // Neue Auftragszeit als Hauptauftrag anlegen
            $neueId = $this->auftragszeitModel->erstelleAuftragszeit(
                $mitarbeiterId,
                $auftragId,
                $auftragscode,
                $maschineId,
                null,          // terminal_id – wird später vom Terminal-Subsystem gesetzt
                'haupt',
                $startzeit,
                null           // Kommentar
            );

            // Optional: Arbeitsschritt-Code nachtragen (Schema: auftragszeit.arbeitsschritt_code)
            if ($neueId !== null && $neueId > 0 && $arbeitsschrittCode !== null && class_exists('Database')) {
                try {
                    $db3 = Database::getInstanz();
                    $db3->ausfuehren(
                        'UPDATE auftragszeit SET arbeitsschritt_code = :c WHERE id = :id',
                        ['c' => $arbeitsschrittCode, 'id' => (int)$neueId]
                    );
                } catch (\Throwable $e) {
                    // Soft-fail: Start darf nicht blockieren
                }
            }

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
     * @param string      $status         Zielstatus (`abgeschlossen` oder `abgebrochen`)
     *
     * @return int|null 1=online erfolgreich, 0=offline in Queue gespeichert, null=Fehler/kein passender Auftrag
     */
    public function stoppeAuftrag(int $mitarbeiterId, ?int $auftragszeitId = null, ?string $auftragscode = null, string $status = 'abgeschlossen'): ?int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        if (!in_array($status, ['abgeschlossen', 'abgebrochen'], true)) {
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
}
