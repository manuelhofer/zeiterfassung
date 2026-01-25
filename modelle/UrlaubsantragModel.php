<?php
declare(strict_types=1);

/**
 * UrlaubsantragModel
 *
 * Datenzugriff für Tabelle `urlaubsantrag`.
 */
class UrlaubsantragModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt alle Urlaubsanträge eines Mitarbeiters, optional gefiltert nach Status.
     *
     * @param int         $mitarbeiterId
     * @param string|null $statusFilter
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeFuerMitarbeiter(int $mitarbeiterId, ?string $statusFilter = null): array
    {
        // Wichtig: Für die Mitarbeiter-Ansicht werden auch Entscheidungsinfos benötigt
        // (Genehmiger + Datum + Kommentar). Dafür joinen wir optional den Entscheider.
        $sql = "SELECT
                    ua.*,
                    COALESCE(
                        NULLIF(
                            CONCAT_WS(' ', NULLIF(TRIM(g.vorname), ''), NULLIF(TRIM(g.nachname), '')),
                            ''
                        ),
                        NULLIF(TRIM(g.benutzername), ''),
                        CASE WHEN g.id IS NULL THEN NULL ELSE CONCAT('Mitarbeiter #', g.id) END
                    ) AS entscheidungs_mitarbeiter_name
                FROM urlaubsantrag ua
                LEFT JOIN mitarbeiter g ON g.id = ua.entscheidungs_mitarbeiter_id
                WHERE ua.mitarbeiter_id = :mid";

        $params = ['mid' => $mitarbeiterId];

        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $statusFilter;
        }

        $sql .= ' ORDER BY ua.von_datum DESC, ua.bis_datum DESC, ua.antrags_datum DESC, ua.id DESC';

        return $this->db->fetchAlle($sql, $params);
    }
}
