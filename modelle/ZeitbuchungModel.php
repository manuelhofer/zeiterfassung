<?php
declare(strict_types=1);

/**
 * ZeitbuchungModel
 *
 * Datenzugriff für Tabelle `zeitbuchung`.
 */
class ZeitbuchungModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt alle Zeitbuchungen eines Mitarbeiters in einem Zeitraum.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeFuerMitarbeiterUndZeitraum(
        int $mitarbeiterId,
        \DateTimeInterface $von,
        \DateTimeInterface $bis
    ): array {
        // Hinweis: In der aktuellen DB-Struktur heißt das Feld in `terminal` nicht `bezeichnung`,
        // sondern `name` (siehe `zeiterfassung_aktuell.sql`).
        $sql = 'SELECT zb.*, t.name AS terminal_bezeichnung
                FROM zeitbuchung zb
                LEFT JOIN terminal t ON t.id = zb.terminal_id
                WHERE zb.mitarbeiter_id = :mid
                  AND zb.zeitstempel >= :von
                  AND zb.zeitstempel < :bis
                ORDER BY zb.zeitstempel ASC';

        $params = [
            'mid' => $mitarbeiterId,
            'von' => $von->format('Y-m-d H:i:s'),
            'bis' => $bis->format('Y-m-d H:i:s'),
        ];

        return $this->db->fetchAlle($sql, $params);
    }

    /**
     * Holt eine einzelne Buchung per ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT zb.*, t.name AS terminal_bezeichnung
                FROM zeitbuchung zb
                LEFT JOIN terminal t ON t.id = zb.terminal_id
                WHERE zb.id = :id
                LIMIT 1';

        try {
            $row = $this->db->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden einer Zeitbuchung per ID', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'zeitbuchung_model');
            }
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Erstellt eine neue Zeitbuchung für einen Mitarbeiter.
     *
     * @param int                $mitarbeiterId ID des Mitarbeiters
     * @param string             $typ           'kommen' oder 'gehen'
     * @param \DateTimeImmutable $zeitpunkt     Zeitstempel der Buchung
     * @param string             $quelle        'terminal', 'web' oder 'import'
     * @param int|null           $terminalId    optional verknüpftes Terminal
     * @param string|null        $kommentar     optionaler Kommentar
     * @param bool               $manuellGeaendert true, wenn die Buchung manuell angelegt wurde
     *
     * @return int|null ID der neuen Zeitbuchung oder null bei Fehler
     */
    public function erstelleBuchung(
        int $mitarbeiterId,
        string $typ,
        \DateTimeImmutable $zeitpunkt,
        string $quelle = 'terminal',
        ?int $terminalId = null,
        ?string $kommentar = null,
        bool $manuellGeaendert = false,
        ?int $nachtshift = null
    ): ?int {
        $mitarbeiterId = max(1, (int)$mitarbeiterId);
        if ($mitarbeiterId <= 0) {
            return null;
        }

        if ($typ !== 'kommen' && $typ !== 'gehen') {
            $typ = 'kommen';
        }

        if (!in_array($quelle, ['terminal', 'web', 'import'], true)) {
            $quelle = 'terminal';
        }

        if ($kommentar !== null) {
            $kommentar = trim($kommentar);
            if ($kommentar === '') {
                $kommentar = null;
            } elseif (strlen($kommentar) > 255) {
                $kommentar = substr($kommentar, 0, 255);
            }
        }

        $nachtshiftVal = ($nachtshift === null) ? 0 : (int)$nachtshift;
        if ($typ !== 'kommen') {
            $nachtshiftVal = 0;
        }

        $sql = 'INSERT INTO zeitbuchung (
                    mitarbeiter_id,
                    typ,
                    zeitstempel,
                    quelle,
                    manuell_geaendert,
                    kommentar,
                    terminal_id,
                    nachtshift
                ) VALUES (
                    :mitarbeiter_id,
                    :typ,
                    :zeitstempel,
                    :quelle,
                    :manuell_geaendert,
                    :kommentar,
                    :terminal_id,
                    :nachtshift
                )';

        $params = [
            'mitarbeiter_id'   => $mitarbeiterId,
            'typ'              => $typ,
            'zeitstempel'      => $zeitpunkt->format('Y-m-d H:i:s'),
            'quelle'           => $quelle,
            'manuell_geaendert'=> $manuellGeaendert ? 1 : 0,
            'kommentar'        => $kommentar,
            'terminal_id'      => $terminalId,
            'nachtshift'       => $nachtshiftVal,
        ];

        try {
            $this->db->ausfuehren($sql, $params);
            return (int)$this->db->letzteInsertId();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Erstellen einer Zeitbuchung', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'typ'            => $typ,
                    'quelle'         => $quelle,
                    'terminal_id'    => $terminalId,
                    'manuell'        => $manuellGeaendert,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, $terminalId, 'zeitbuchung_model');
            }

            return null;
        }
    }

    /**
     * Aktualisiert eine bestehende Zeitbuchung.
     */
    public function aktualisiereBuchung(
        int $id,
        string $typ,
        \DateTimeImmutable $zeitpunkt,
        ?string $kommentar = null,
        bool $manuellGeaendert = true,
        ?int $nachtshift = null
    ): bool {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        if ($typ !== 'kommen' && $typ !== 'gehen') {
            $typ = 'kommen';
        }

        if ($kommentar !== null) {
            $kommentar = trim($kommentar);
            if ($kommentar === '') {
                $kommentar = null;
            } elseif (strlen($kommentar) > 255) {
                $kommentar = substr($kommentar, 0, 255);
            }
        }

        $nachtshiftVal = ($nachtshift === null) ? 0 : (int)$nachtshift;
        if ($typ !== 'kommen') {
            $nachtshiftVal = 0;
        }

        $sql = 'UPDATE zeitbuchung
                SET typ = :typ,
                    zeitstempel = :zeitstempel,
                    kommentar = :kommentar,
                    manuell_geaendert = :manuell,
                    nachtshift = :nachtshift
                WHERE id = :id';

        try {
            $this->db->ausfuehren($sql, [
                'typ'        => $typ,
                'zeitstempel'=> $zeitpunkt->format('Y-m-d H:i:s'),
                'kommentar'  => $kommentar,
                'manuell'    => $manuellGeaendert ? 1 : 0,
                'nachtshift' => $nachtshiftVal,
                'id'         => $id,
            ]);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Aktualisieren einer Zeitbuchung', [
                    'id'        => $id,
                    'typ'       => $typ,
                    'exception' => $e->getMessage(),
                ], null, null, 'zeitbuchung_model');
            }
            return false;
        }
    }

    /**
     * Löscht eine Zeitbuchung.
     */
    public function loescheBuchung(int $id): bool
    {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        try {
            $this->db->ausfuehren('DELETE FROM zeitbuchung WHERE id = :id', ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Löschen einer Zeitbuchung', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'zeitbuchung_model');
            }
            return false;
        }
    }
}
