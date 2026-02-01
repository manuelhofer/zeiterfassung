<?php
declare(strict_types=1);

/**
 * AuftragArbeitsschrittModel
 *
 * Zugriff auf die Tabelle `auftrag_arbeitsschritt`.
 */
class AuftragArbeitsschrittModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Sucht einen Arbeitsschritt nach Auftrag-ID und Code.
     */
    public function findeNachCode(int $auftragId, string $code): ?array
    {
        $auftragId = max(1, $auftragId);
        $code = trim($code);
        if ($auftragId <= 0 || $code === '') {
            return null;
        }

        $sql = 'SELECT *
                FROM auftrag_arbeitsschritt
                WHERE auftrag_id = :auftrag_id
                  AND arbeitsschritt_code = :code
                LIMIT 1';

        try {
            return $this->datenbank->fetch($sql, [
                'auftrag_id' => $auftragId,
                'code' => $code,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Arbeitsschritts', [
                    'auftrag_id' => $auftragId,
                    'arbeitsschritt_code' => $code,
                    'exception' => $e->getMessage(),
                ], null, null, 'auftrag_arbeitsschritt');
            }
        }

        return null;
    }

    /**
     * Legt einen Arbeitsschritt an, falls noch nicht vorhanden.
     *
     * @return int|null ID des Arbeitsschritts
     */
    public function erstelleWennFehlt(int $auftragId, string $code): ?int
    {
        $auftragId = max(1, $auftragId);
        $code = trim($code);
        if ($auftragId <= 0 || $code === '') {
            return null;
        }

        $sql = 'INSERT INTO auftrag_arbeitsschritt (auftrag_id, arbeitsschritt_code, aktiv)
                VALUES (:auftrag_id, :code, 1)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';

        try {
            $this->datenbank->ausfuehren($sql, [
                'auftrag_id' => $auftragId,
                'code' => $code,
            ]);
            return (int)$this->datenbank->letzteInsertId();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Erstellen eines Arbeitsschritts', [
                    'auftrag_id' => $auftragId,
                    'arbeitsschritt_code' => $code,
                    'exception' => $e->getMessage(),
                ], null, null, 'auftrag_arbeitsschritt');
            }
        }

        return null;
    }
}
