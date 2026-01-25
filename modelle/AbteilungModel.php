<?php
declare(strict_types=1);

/**
 * AbteilungModel
 *
 * Datenzugriff für die Tabelle `abteilung`.
 */
class AbteilungModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt alle aktiven Abteilungen.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleAktiven(): array
    {
        try {
            $sql = 'SELECT *
                    FROM abteilung
                    WHERE aktiv = 1
                    ORDER BY name ASC';

            return $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden aktiver Abteilungen', [
                    'exception' => $e->getMessage(),
                ], null, null, 'abteilung');
            }

            return [];
        }
    }

    /**
     * Lädt eine Abteilung nach ID.
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
                    FROM abteilung
                    WHERE id = :id
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden einer Abteilung nach ID', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'abteilung');
            }

            return null;
        }
    }


    /**
     * Legt eine neue Abteilung an.
     *
     * @param array<string,mixed> $daten
     * @return int|null ID der neu angelegten Abteilung oder null bei Fehler.
     */
    public function erstelleAbteilung(array $daten): ?int
    {
        $name         = trim((string)($daten['name'] ?? ''));
        $beschreibung = $daten['beschreibung'] ?? null;
        $parentId     = $daten['parent_id'] ?? null;
        $aktiv        = !empty($daten['aktiv']) ? 1 : 0;

        if ($name === '') {
            return null;
        }

        if ($beschreibung !== null) {
            $beschreibung = (string)$beschreibung;
        }

        if ($parentId !== null) {
            $parentId = (int)$parentId;
            if ($parentId <= 0) {
                $parentId = null;
            }
        }

        $sql = 'INSERT INTO abteilung (name, beschreibung, parent_id, aktiv)
                VALUES (:name, :beschreibung, :parent_id, :aktiv)';

        $parameter = [
            'name'        => $name,
            'beschreibung'=> $beschreibung,
            'parent_id'   => $parentId,
            'aktiv'       => $aktiv,
        ];

        try {
            $this->datenbank->ausfuehren($sql, $parameter);

            return (int)$this->datenbank->letzteInsertId();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Anlegen einer Abteilung', [
                    'daten'     => $parameter,
                    'exception' => $e->getMessage(),
                ], null, null, 'abteilung');
            }

            return null;
        }
    }

    /**
     * Aktualisiert eine bestehende Abteilung.
     *
     * @param int                  $id
     * @param array<string,mixed>  $daten
     * @return bool true bei Erfolg, false bei Fehler.
     */
    public function aktualisiereAbteilung(int $id, array $daten): bool
    {
        $name         = trim((string)($daten['name'] ?? ''));
        $beschreibung = $daten['beschreibung'] ?? null;
        $parentId     = $daten['parent_id'] ?? null;
        $aktiv        = !empty($daten['aktiv']) ? 1 : 0;

        if ($name === '') {
            return false;
        }

        if ($beschreibung !== null) {
            $beschreibung = (string)$beschreibung;
        }

        if ($parentId !== null) {
            $parentId = (int)$parentId;
            if ($parentId <= 0) {
                $parentId = null;
            }
        }

        $sql = 'UPDATE abteilung
                SET name = :name,
                    beschreibung = :beschreibung,
                    parent_id = :parent_id,
                    aktiv = :aktiv
                WHERE id = :id';

        $parameter = [
            'id'          => $id,
            'name'        => $name,
            'beschreibung'=> $beschreibung,
            'parent_id'   => $parentId,
            'aktiv'       => $aktiv,
        ];

        try {
            $this->datenbank->ausfuehren($sql, $parameter);

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Aktualisieren einer Abteilung', [
                    'id'        => $id,
                    'daten'     => $parameter,
                    'exception' => $e->getMessage(),
                ], $id, null, 'abteilung');
            }

            return false;
        }
    }

}