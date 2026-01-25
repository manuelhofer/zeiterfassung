<?php
declare(strict_types=1);

/**
 * ZeitRundungsregelModel
 *
 * Datenzugriff für Tabelle `zeit_rundungsregel`.
 */
class ZeitRundungsregelModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt alle aktiven Rundungsregeln, sortiert nach Priorität und Zeit.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAktiveRegeln(): array
    {
        $sql = 'SELECT *
                FROM zeit_rundungsregel
                WHERE aktiv = 1
                ORDER BY prioritaet ASC, von_uhrzeit ASC, bis_uhrzeit ASC';

        return $this->db->fetchAlle($sql);
    }
}
