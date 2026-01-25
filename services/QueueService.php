<?php
declare(strict_types=1);

/**
 * QueueService
 *
 * Höherstufiger Service für die Verwaltung der `db_injektionsqueue`.
 *
 * Er nutzt intern den `OfflineQueueManager` und stellt Funktionen bereit für:
 * - Anzeigen offener/fehlerhafter Einträge im Backend,
 * - manuelles Löschen/ignorieren von Einträgen,
 * - Anstoßen der Abarbeitung.
 */
class QueueService
{
    /** Singleton-Instanz. */
    private static ?QueueService $instanz = null;

    private Database $datenbank;
    private OfflineQueueManager $offlineQueueManager;

    private function __construct()
    {
        $this->datenbank          = Database::getInstanz();
        $this->offlineQueueManager = OfflineQueueManager::getInstanz();
    }

    public static function getInstanz(): QueueService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Liefert alle offenen Einträge (Status = 'offen'), optional begrenzt.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeOffeneEintraege(int $limit = 100): array
    {
        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = \'offen\'
                ORDER BY erstellt_am ASC, id ASC
                LIMIT :limit';

        try {
            $pdo = $this->holeQueueVerbindung();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden offener Queue-Einträge', [
                    'exception' => $e->getMessage(),
                ], null, null, 'offline_queue');
            }
            return [];
        }
    }

    /**
     * Liefert fehlerhafte Einträge (Status = 'fehler'), optional begrenzt.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeFehlerEintraege(int $limit = 100): array
    {
        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = \'fehler\'
                ORDER BY letzte_ausfuehrung DESC, id DESC
                LIMIT :limit';

        try {
            $pdo = $this->holeQueueVerbindung();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden fehlerhafter Queue-Einträge', [
                    'exception' => $e->getMessage(),
                ], null, null, 'offline_queue');
            }
            return [];
        }
    }

    /**
     * Liefert einen bestimmten Queue-Eintrag nach ID oder null.
     *
     * @return array<string,mixed>|null
     */
    public function holeEintragNachId(int $id): ?array
    {
        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE id = :id
                LIMIT 1';

        try {
            $pdo = $this->holeQueueVerbindung();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();

            $datensatz = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($datensatz === false) {
                return null;
            }

            return $datensatz;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Queue-Eintrags', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'offline_queue');
            }
            return null;
        }
    }

    /**
     * Löscht einen Eintrag endgültig aus der Queue.
     */
    public function loescheEintrag(int $id): void
    {
        try {
            $this->offlineQueueManager->loescheEintrag($id);

            if (class_exists('Logger')) {
                Logger::info('Queue-Eintrag gelöscht', ['id' => $id], null, null, 'offline_queue');
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Löschen eines Queue-Eintrags', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'offline_queue');
            }
        }
    }

    /**
     * Startet die Abarbeitung aller offenen Einträge.
     */
    public function verarbeiteOffeneEintraege(): void
    {
        $this->offlineQueueManager->verarbeiteOffeneEintraege();
    }


/**
 * Liefert eine Status-Zusammenfassung der Queue (für Dashboard/Admin-Übersicht).
 *
 * @return array<string,mixed>
 *   - verfuegbar (bool)
 *   - quelle (string) 'offline' oder 'haupt'
 *   - offen (int)
 *   - fehler (int)
 *   - verarbeitet (int)
 *   - letzte_erstellung (?string)
 *   - letzte_ausfuehrung (?string)
 */
public function holeStatusSummary(): array
{
    $offline = $this->datenbank->getOfflineVerbindung();
    $pdo = $offline instanceof \PDO ? $offline : $this->datenbank->getVerbindung();

    $out = [
        'verfuegbar'        => false,
        'quelle'            => $offline instanceof \PDO ? 'offline' : 'haupt',
        'offen'             => 0,
        'fehler'            => 0,
        'verarbeitet'       => 0,
        'letzte_erstellung' => null,
        'letzte_ausfuehrung'=> null,
    ];

    $sql = "SELECT
                SUM(CASE WHEN status = 'offen' THEN 1 ELSE 0 END) AS offen,
                SUM(CASE WHEN status = 'fehler' THEN 1 ELSE 0 END) AS fehler,
                SUM(CASE WHEN status = 'verarbeitet' THEN 1 ELSE 0 END) AS verarbeitet,
                MAX(erstellt_am) AS letzte_erstellung,
                MAX(letzte_ausfuehrung) AS letzte_ausfuehrung
            FROM db_injektionsqueue";

    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return $out;
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $out;
        }

        $out['verfuegbar'] = true;
        $out['offen'] = (int)($row['offen'] ?? 0);
        $out['fehler'] = (int)($row['fehler'] ?? 0);
        $out['verarbeitet'] = (int)($row['verarbeitet'] ?? 0);

        $le = $row['letzte_erstellung'] ?? null;
        $la = $row['letzte_ausfuehrung'] ?? null;
        $out['letzte_erstellung'] = is_string($le) && $le !== '' ? $le : null;
        $out['letzte_ausfuehrung'] = is_string($la) && $la !== '' ? $la : null;

        return $out;
    } catch (\Throwable $e) {
        if (class_exists('Logger')) {
            Logger::error('Fehler beim Laden Queue-Status-Summary', [
                'exception' => $e->getMessage(),
            ], null, null, 'offline_queue');
        }

        return $out;
    }
}


    /**
     * Hilfsfunktion: Ermittelt die passende Verbindung für direkte SELECTs
     * auf die `db_injektionsqueue`-Tabelle.
     */
    private function holeQueueVerbindung(): \PDO
    {
        $offline = $this->datenbank->getOfflineVerbindung();
        if ($offline instanceof \PDO) {
            return $offline;
        }

        return $this->datenbank->getVerbindung();
    }
}
