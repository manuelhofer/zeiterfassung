<?php
declare(strict_types=1);

/**
 * FeiertagModel
 *
 * Datenzugriff für Tabelle `feiertag`.
 */
class FeiertagModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt einen Feiertag für Datum + optionales Bundesland.
     *
     * Wenn kein Datensatz für das konkrete Bundesland gefunden wird,
     * wird zusätzlich nach einem Eintrag mit `bundesland IS NULL` gesucht.
     *
     * @return array<string,mixed>|null
     */
    public function holeFuerDatum(string $datum, ?string $bundesland = null): ?array
    {
        $sql = 'SELECT *
                FROM feiertag
                WHERE datum = :datum';
        $params = ['datum' => $datum];

        if ($bundesland !== null && $bundesland !== '') {
            $sql .= ' AND (bundesland = :bundesland OR bundesland IS NULL)';
            $params['bundesland'] = $bundesland;
        } else {
            $sql .= ' AND bundesland IS NULL';
        }

        $sql .= ' ORDER BY bundesland IS NULL ASC LIMIT 1';

        return $this->db->fetchEine($sql, $params);
    }

    /**
     * Holt alle Feiertage eines Jahres (optional für ein bestimmtes Bundesland).
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeFuerJahr(int $jahr, ?string $bundesland = null): array
    {
        $von = sprintf('%04d-01-01', $jahr);
        $bis = sprintf('%04d-12-31', $jahr);

        $sql = 'SELECT *
                FROM feiertag
                WHERE datum >= :von
                  AND datum <= :bis';

        $params = ['von' => $von, 'bis' => $bis];

        if ($bundesland !== null && $bundesland !== '') {
            $sql .= ' AND (bundesland = :bundesland OR bundesland IS NULL)';
            $params['bundesland'] = $bundesland;
        }

        $sql .= ' ORDER BY datum ASC, bundesland IS NULL ASC';

        return $this->db->fetchAlle($sql, $params);
    }

    /**
     * Fügt einen Feiertag ein, wenn noch nicht vorhanden.
     *
     * @return bool true bei Erfolg oder wenn bereits vorhanden
     */
    public function fuegeEinWennNeu(
        string $datum,
        string $name,
        ?string $bundesland = null,
        int $istGesetzlich = 1,
        int $istBetriebsfrei = 1
    ): bool {
        // Prüfen, ob es bereits einen Eintrag gibt
        $vorhanden = $this->holeFuerDatum($datum, $bundesland);
        if ($vorhanden !== null) {
            return true;
        }

        $sql = 'INSERT INTO feiertag (datum, name, bundesland, ist_gesetzlich, ist_betriebsfrei)
                VALUES (:datum, :name, :bundesland, :ist_gesetzlich, :ist_betriebsfrei)';

        $params = [
            'datum'          => $datum,
            'name'           => $name,
            'bundesland'     => $bundesland,
            'ist_gesetzlich' => $istGesetzlich,
            'ist_betriebsfrei' => $istBetriebsfrei,
        ];

        $this->db->ausfuehren($sql, $params);

        return true;
    }


    /**
     * Holt einen Feiertag anhand der ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT *
                FROM feiertag
                WHERE id = :id';

        $datensatz = $this->db->fetchEine($sql, ['id' => $id]);

        return $datensatz === false ? null : $datensatz;
    }

    /**
     * Aktualisiert ausgewählte Felder eines bestehenden Feiertags.
     */
    public function aktualisiereFeiertag(
        int $id,
        string $name,
        ?string $bundesland,
        bool $istGesetzlich,
        bool $istBetriebsfrei
    ): bool {
        $id          = (int)$id;
        $name        = trim($name);
        $bundesland  = $bundesland !== null ? trim($bundesland) : null;
        $istGesetzlich  = $istGesetzlich ? 1 : 0;
        $istBetriebsfrei = $istBetriebsfrei ? 1 : 0;

        if ($id <= 0 || $name === '') {
            return false;
        }

        $sql = 'UPDATE feiertag
                SET name = :name,
                    bundesland = :bundesland,
                    ist_gesetzlich = :ist_gesetzlich,
                    ist_betriebsfrei = :ist_betriebsfrei
                WHERE id = :id';

        $params = [
            'id'             => $id,
            'name'           => $name,
            'bundesland'     => $bundesland,
            'ist_gesetzlich' => $istGesetzlich,
            'ist_betriebsfrei' => $istBetriebsfrei,
        ];

        $this->db->ausfuehren($sql, $params);

        return true;
    }
}
