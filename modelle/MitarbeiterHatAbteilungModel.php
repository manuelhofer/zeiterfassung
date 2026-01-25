<?php
declare(strict_types=1);

/**
 * MitarbeiterHatAbteilungModel
 *
 * Datenzugriff für die Zuordnungstabelle `mitarbeiter_hat_abteilung`.
 */
class MitarbeiterHatAbteilungModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt alle Abteilungs-IDs eines Mitarbeiters.
     *
     * @return array<int,int> Liste von Abteilungs-IDs
     */
    public function holeAbteilungsIdsFuerMitarbeiter(int $mitarbeiterId): array
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        try {
            $sql = 'SELECT abteilung_id
                    FROM mitarbeiter_hat_abteilung
                    WHERE mitarbeiter_id = :mitarbeiter_id';

            $daten = $this->datenbank->fetchAlle($sql, ['mitarbeiter_id' => $mitarbeiterId]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden von Abteilungen eines Mitarbeiters', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'abteilung');
            }

            return [];
        }

        $ids = [];
        foreach ($daten as $row) {
            $ids[] = (int)($row['abteilung_id'] ?? 0);
        }

        return $ids;
    }
}
