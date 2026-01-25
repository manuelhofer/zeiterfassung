<?php
declare(strict_types=1);

/**
 * SystemLogModel
 *
 * Datenzugriff für die Tabelle `system_log`.
 * In der Regel wird das Logging über die Logger-Klasse gekapselt.
 * Dieses Model ist vor allem für Auswertungen im Backend gedacht.
 */
class SystemLogModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt die letzten Logeinträge, optional gefiltert nach Kanal.
     *
     * @param string|null $kanalFilter z. B. 'auth', 'zeit', 'urlaub'
     * @param int         $limit       maximale Anzahl Datensätze
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeLetzte(?string $kanalFilter = null, int $limit = 200): array
    {
        $limit = max(1, $limit);

        try {
            if ($kanalFilter !== null && $kanalFilter !== '') {
                $sql = 'SELECT *
                        FROM system_log
                        WHERE kanal = :kanal
                        ORDER BY erstellt_am DESC, id DESC
                        LIMIT :limit';

                $pdo = $this->datenbank->getVerbindung();
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':kanal', $kanalFilter, \PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }

            $sql = 'SELECT *
                    FROM system_log
                    ORDER BY erstellt_am DESC, id DESC
                    LIMIT :limit';

            $pdo = $this->datenbank->getVerbindung();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden von Logeinträgen (Model)', [
                    'exception' => $e->getMessage(),
                ], null, null, 'system_log');
            }

            return [];
        }
    }
}
