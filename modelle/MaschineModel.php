<?php
declare(strict_types=1);

/**
 * MaschineModel
 *
 * Datenzugriff für die Tabelle `maschine`.
 */
class MaschineModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt alle aktiven Maschinen.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleAktiven(): array
    {
        try {
            $sql = 'SELECT *
                    FROM maschine
                    WHERE aktiv = 1
                    ORDER BY name ASC';

            return $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden aktiver Maschinen', [
                    'exception' => $e->getMessage(),
                ], null, null, 'maschine');
            }

            return [];
        }
    }

    /**
     * Lädt eine Maschine nach ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $sql = 'SELECT *
                    FROM maschine
                    WHERE id = :id
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden einer Maschine nach ID', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'maschine');
            }

            return null;
        }
    }
}
