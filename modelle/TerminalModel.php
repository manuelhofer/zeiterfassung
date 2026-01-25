<?php
declare(strict_types=1);

/**
 * TerminalModel
 *
 * Datenzugriff für Tabelle `terminal`.
 */
class TerminalModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt ein Terminal per ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        $sql = 'SELECT *
                FROM terminal
                WHERE id = :id
                LIMIT 1';

        $daten = $this->db->fetchEine($sql, ['id' => $id]);

        return $daten === null ? null : $daten;
    }

    /**
     * Holt ein aktives Terminal anhand des Namens.
     *
     * @return array<string,mixed>|null
     */
    public function holeAktivesNachName(string $name): ?array
    {
        $sql = 'SELECT *
                FROM terminal
                WHERE name = :name
                  AND aktiv = 1
                LIMIT 1';

        $daten = $this->db->fetchEine($sql, ['name' => $name]);

        return $daten === null ? null : $daten;
    }

    /**
     * Holt alle aktiven Terminals.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleAktiven(): array
    {
        $sql = 'SELECT *
                FROM terminal
                WHERE aktiv = 1
                ORDER BY name ASC';

        return $this->db->fetchAlle($sql);
    }
}
