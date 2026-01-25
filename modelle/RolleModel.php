<?php
declare(strict_types=1);

/**
 * RolleModel
 *
 * Datenzugriff für Tabelle `rolle` und die M:N-Zuordnung `mitarbeiter_hat_rolle`.
 */
class RolleModel
{
    private Database $db;

    /**
     * Interner Cache: Existieren die Rechte-Tabellen?
     */
    private static ?bool $rechteTabellenVerfuegbar = null;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt alle aktiven Rollen.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleAktiven(): array
    {
        $sql = 'SELECT *
                FROM rolle
                WHERE aktiv = 1
                ORDER BY name ASC';

        return $this->db->fetchAlle($sql);
    }

    /**
     * Holt alle Rollen eines Mitarbeiters.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeRollenFuerMitarbeiter(int $mitarbeiterId): array
    {
        $sql = 'SELECT r.*
                FROM rolle r
                INNER JOIN mitarbeiter_hat_rolle mhr
                  ON mhr.rolle_id = r.id
                WHERE mhr.mitarbeiter_id = :mid
                ORDER BY r.name ASC';

        return $this->db->fetchAlle($sql, ['mid' => $mitarbeiterId]);
    }


    /**
     * Legt eine neue Rolle an.
     *
     * @param array<string,mixed> $daten
     * @return int|null ID der neu angelegten Rolle oder null bei Fehler.
     */
    public function erstelleRolle(array $daten): ?int
    {
        $name         = trim((string)($daten['name'] ?? ''));
        $beschreibung = $daten['beschreibung'] ?? null;
        $aktiv        = !empty($daten['aktiv']) ? 1 : 0;

        if ($name === '') {
            return null;
        }

        if ($beschreibung !== null) {
            $beschreibung = (string)$beschreibung;
        }

        $sql = 'INSERT INTO rolle (name, beschreibung, aktiv)
                VALUES (:name, :beschreibung, :aktiv)';

        $parameter = [
            'name'         => $name,
            'beschreibung' => $beschreibung,
            'aktiv'        => $aktiv,
        ];

        try {
            $this->db->ausfuehren($sql, $parameter);

            return (int)$this->db->letzteInsertId();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Anlegen einer Rolle', [
                    'daten'     => $parameter,
                    'exception' => $e->getMessage(),
                ], null, null, 'rolle');
            }

            return null;
        }
    }

    /**
     * Aktualisiert eine bestehende Rolle.
     *
     * @param int                  $id
     * @param array<string,mixed>  $daten
     * @return bool true bei Erfolg, false bei Fehler.
     */
    public function aktualisiereRolle(int $id, array $daten): bool
    {
        $name         = trim((string)($daten['name'] ?? ''));
        $beschreibung = $daten['beschreibung'] ?? null;
        $aktiv        = !empty($daten['aktiv']) ? 1 : 0;

        if ($name === '') {
            return false;
        }

        if ($beschreibung !== null) {
            $beschreibung = (string)$beschreibung;
        }

        $sql = 'UPDATE rolle
                SET name = :name,
                    beschreibung = :beschreibung,
                    aktiv = :aktiv
                WHERE id = :id';

        $parameter = [
            'id'           => $id,
            'name'         => $name,
            'beschreibung' => $beschreibung,
            'aktiv'        => $aktiv,
        ];

        try {
            $this->db->ausfuehren($sql, $parameter);

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Aktualisieren einer Rolle', [
                    'id'        => $id,
                    'daten'     => $parameter,
                    'exception' => $e->getMessage(),
                ], $id, null, 'rolle');
            }

            return false;
        }
    }


    /**
     * Holt alle Rechte (aus Tabelle `recht`).
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleRechte(bool $nurAktive = false): array
    {
        try {
            $pdo = $this->db->getPdo();
            if (!$this->sindRechteTabellenVerfuegbar($pdo)) {
                return [];
            }

            $sql = 'SELECT id, code, name, beschreibung, aktiv
                    FROM recht';

            $params = [];
            if ($nurAktive) {
                $sql .= ' WHERE aktiv = 1';
            }

            $sql .= ' ORDER BY aktiv DESC, name ASC, code ASC';

            return $this->db->fetchAlle($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Liefert die IDs der Rechte, die einer Rolle zugeordnet sind.
     *
     * @return int[]
     */
    public function holeRechtIdsFuerRolle(int $rolleId): array
    {
        if ($rolleId <= 0) {
            return [];
        }

        try {
            $pdo = $this->db->getPdo();
            if (!$this->sindRechteTabellenVerfuegbar($pdo)) {
                return [];
            }

            $rows = $this->db->fetchAlle(
                'SELECT recht_id
                 FROM rolle_hat_recht
                 WHERE rolle_id = :rid',
                ['rid' => $rolleId]
            );

            $ids = [];
            foreach ($rows as $row) {
                $id = (int)($row['recht_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }

            return array_keys($ids);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Erstellt oder aktualisiert eine Rolle und speichert anschließend die Rechte-Zuordnung.
     *
     * @param int|null                 $rolleId null/0 = neu anlegen
     * @param array<string,mixed>      $daten
     * @param int[]                    $rechtIds
     *
     * @return int|null Rolle-ID bei Erfolg, sonst null
     */
    public function speichereRolleMitRechten(?int $rolleId, array $daten, array $rechtIds): ?int
    {
        $rolleId = $rolleId !== null ? (int)$rolleId : 0;

        // Input normalisieren
        $name = trim((string)($daten['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $beschreibung = $daten['beschreibung'] ?? null;
        if ($beschreibung !== null) {
            $beschreibung = (string)$beschreibung;
        }
        $aktiv = !empty($daten['aktiv']) ? 1 : 0;

        // Rechte normalisieren/unique
        $tmp = [];
        foreach ($rechtIds as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) {
                $tmp[$rid] = true;
            }
        }
        $rechtIds = array_keys($tmp);

        $pdo = $this->db->getPdo();

        try {
            $pdo->beginTransaction();

            if ($rolleId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE rolle
                     SET name = :name,
                         beschreibung = :beschreibung,
                         aktiv = :aktiv
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id'           => $rolleId,
                    'name'         => $name,
                    'beschreibung' => $beschreibung,
                    'aktiv'        => $aktiv,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO rolle (name, beschreibung, aktiv)
                     VALUES (:name, :beschreibung, :aktiv)'
                );
                $stmt->execute([
                    'name'         => $name,
                    'beschreibung' => $beschreibung,
                    'aktiv'        => $aktiv,
                ]);

                $rolleId = (int)$pdo->lastInsertId();
                if ($rolleId <= 0) {
                    throw new \RuntimeException('Konnte Rolle nicht anlegen (keine Insert-ID).');
                }
            }

            // Rechte speichern (wenn Tabellen verfügbar). Falls nicht, nur Rolle speichern.
            if ($this->sindRechteTabellenVerfuegbar($pdo)) {
                $del = $pdo->prepare('DELETE FROM rolle_hat_recht WHERE rolle_id = :rid');
                $del->execute(['rid' => $rolleId]);

                if (count($rechtIds) > 0) {
                    $ins = $pdo->prepare('INSERT INTO rolle_hat_recht (rolle_id, recht_id) VALUES (:rolle_id, :recht_id)');
                    foreach ($rechtIds as $rechtId) {
                        $ins->execute([
                            'rolle_id' => $rolleId,
                            'recht_id' => (int)$rechtId,
                        ]);
                    }
                }
            }

            $pdo->commit();
            return $rolleId;
        } catch (\Throwable $e) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Throwable) {
                // ignore
            }

            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern einer Rolle inkl. Rechte', [
                    'rolle_id'   => $rolleId > 0 ? $rolleId : null,
                    'name'       => $name,
                    'recht_ids'  => $rechtIds,
                    'exception'  => $e->getMessage(),
                ], $rolleId > 0 ? $rolleId : null, null, 'rolle');
            }

            return null;
        }
    }

    /**
     * Prüft (gecached), ob `recht` und `rolle_hat_recht` existieren.
     */
    private function sindRechteTabellenVerfuegbar(\PDO $pdo): bool
    {
        if (self::$rechteTabellenVerfuegbar !== null) {
            return self::$rechteTabellenVerfuegbar;
        }

        try {
            $pdo->query('SELECT 1 FROM recht LIMIT 1');
            $pdo->query('SELECT 1 FROM rolle_hat_recht LIMIT 1');
            self::$rechteTabellenVerfuegbar = true;
        } catch (\Throwable $e) {
            self::$rechteTabellenVerfuegbar = false;
        }

        return self::$rechteTabellenVerfuegbar;
    }

}