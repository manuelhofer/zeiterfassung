<?php
declare(strict_types=1);

/**
 * BetriebsferienModel
 *
 * Datenzugriff für die Tabelle `betriebsferien`.
 */
class BetriebsferienModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt alle aktiven Betriebsferien (optional gefiltert nach Abteilung).
     *
     * Hinweis: Für die Sollstunden-Logik werden i. d. R. sowohl globale (abteilung_id IS NULL)
     * als auch abteilungsspezifische Einträge benötigt. Diese Methode bildet bewusst nur
     * *eine* der beiden Varianten ab:
     * - null => globale Betriebsferien
     * - ID   => nur diese Abteilung
     *
     * @param int|null $abteilungId null = global gültige Betriebsferien
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAktive(?int $abteilungId = null): array
    {
        try {
            if ($abteilungId === null) {
                $sql = 'SELECT *
                        FROM betriebsferien
                        WHERE aktiv = 1
                          AND abteilung_id IS NULL
                        ORDER BY von_datum ASC, bis_datum ASC';

                return $this->datenbank->fetchAlle($sql);
            }

            $sql = 'SELECT *
                    FROM betriebsferien
                    WHERE aktiv = 1
                      AND abteilung_id = :abteilung_id
                    ORDER BY von_datum ASC, bis_datum ASC';

            return $this->datenbank->fetchAlle($sql, ['abteilung_id' => (int)$abteilungId]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden aktiver Betriebsferien', [
                    'abteilung_id' => $abteilungId,
                    'exception'    => $e->getMessage(),
                ], null, null, 'betriebsferien');
            }

            return [];
        }
    }

    /**
     * Admin: Lädt alle Betriebsferien (inkl. inaktiver) inkl. Abteilungsname.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleMitAbteilung(): array
    {
        try {
            $sql = 'SELECT b.*, a.name AS abteilung_name
                    FROM betriebsferien b
                    LEFT JOIN abteilung a ON a.id = b.abteilung_id
                    ORDER BY b.von_datum DESC, b.bis_datum DESC, b.id DESC';

            return $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Betriebsferien (Admin)', [
                    'exception' => $e->getMessage(),
                ], null, null, 'betriebsferien');
            }

            return [];
        }
    }

    /**
     * Lädt einen Betriebsferien-Eintrag anhand der ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        try {
            $sql = 'SELECT *
                    FROM betriebsferien
                    WHERE id = :id
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden von Betriebsferien nach ID', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id, null, 'betriebsferien');
            }

            return null;
        }
    }

    /**
     * Speichert (INSERT/UPDATE) einen Betriebsferien-Datensatz.
     */
    public function speichere(
        int $id,
        string $vonDatum,
        string $bisDatum,
        ?string $beschreibung,
        ?int $abteilungId,
        int $aktiv
    ): bool {
        $id          = (int)$id;
        $vonDatum    = trim($vonDatum);
        $bisDatum    = trim($bisDatum);
        $beschreibung = $beschreibung !== null ? trim($beschreibung) : null;
        $aktiv       = $aktiv === 1 ? 1 : 0;

        if ($vonDatum === '' || $bisDatum === '') {
            return false;
        }

        if ($beschreibung === '') {
            $beschreibung = null;
        }

        if ($abteilungId !== null) {
            $abteilungId = (int)$abteilungId;
            if ($abteilungId <= 0) {
                $abteilungId = null;
            }
        }

        $params = [
            'von_datum'    => $vonDatum,
            'bis_datum'    => $bisDatum,
            'beschreibung' => $beschreibung,
            'abteilung_id' => $abteilungId,
            'aktiv'        => $aktiv,
        ];

        try {
            if ($id > 0) {
                $sql = 'UPDATE betriebsferien
                        SET von_datum = :von_datum,
                            bis_datum = :bis_datum,
                            beschreibung = :beschreibung,
                            abteilung_id = :abteilung_id,
                            aktiv = :aktiv
                        WHERE id = :id';

                $params['id'] = $id;
                $this->datenbank->ausfuehren($sql, $params);
                return true;
            }

            $sql = 'INSERT INTO betriebsferien (von_datum, bis_datum, beschreibung, abteilung_id, aktiv)
                    VALUES (:von_datum, :bis_datum, :beschreibung, :abteilung_id, :aktiv)';

            $this->datenbank->ausfuehren($sql, $params);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern von Betriebsferien', [
                    'id'        => $id,
                    'params'    => $params,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'betriebsferien');
            }

            return false;
        }
    }

    /**
     * Setzt den Aktiv-Status eines Eintrags.
     */
    public function setzeAktiv(int $id, int $aktiv): bool
    {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        $aktiv = $aktiv === 1 ? 1 : 0;

        try {
            $sql = 'UPDATE betriebsferien
                    SET aktiv = :aktiv
                    WHERE id = :id';

            $this->datenbank->ausfuehren($sql, [
                'id'    => $id,
                'aktiv' => $aktiv,
            ]);

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Setzen des Betriebsferien-Status', [
                    'id'        => $id,
                    'aktiv'     => $aktiv,
                    'exception' => $e->getMessage(),
                ], $id, null, 'betriebsferien');
            }

            return false;
        }
    }
}
