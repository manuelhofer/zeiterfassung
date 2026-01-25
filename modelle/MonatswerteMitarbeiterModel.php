<?php
declare(strict_types=1);

/**
 * MonatswerteMitarbeiterModel
 *
 * Datenzugriff für Tabelle `monatswerte_mitarbeiter`.
 */
class MonatswerteMitarbeiterModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt den Monatswert-Datensatz für einen Mitarbeiter und einen Monat.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachMitarbeiterUndMonat(int $mitarbeiterId, int $jahr, int $monat): ?array
    {
        $sql = 'SELECT *
                FROM monatswerte_mitarbeiter
                WHERE mitarbeiter_id = :mid
                  AND jahr = :jahr
                  AND monat = :monat
                LIMIT 1';

        $params = [
            'mid'   => $mitarbeiterId,
            'jahr'  => $jahr,
            'monat' => $monat,
        ];

        $ergebnis = $this->db->fetchEine($sql, $params);

        if ($ergebnis === null) {
            return null;
        }

        return $ergebnis;
    }
}
