<?php
declare(strict_types=1);

/**
 * DbInjektionsqueueModel
 *
 * Datenzugriff für Tabelle `db_injektionsqueue`.
 */
class DbInjektionsqueueModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Fügt einen neuen Queue-Eintrag ein.
     *
     * @return int ID des neu erzeugten Eintrags
     */
    public function fuegeEin(
        string $sqlBefehl,
        ?int $metaMitarbeiterId = null,
        ?int $metaTerminalId = null,
        ?string $metaAktion = null
    ): int {
        $sql = 'INSERT INTO db_injektionsqueue
                    (sql_befehl, meta_mitarbeiter_id, meta_terminal_id, meta_aktion)
                VALUES (:sql_befehl, :meta_mitarbeiter_id, :meta_terminal_id, :meta_aktion)';

        $params = [
            'sql_befehl'        => $sqlBefehl,
            'meta_mitarbeiter_id' => $metaMitarbeiterId,
            'meta_terminal_id'  => $metaTerminalId,
            'meta_aktion'       => $metaAktion,
        ];

        $this->db->ausfuehren($sql, $params);

        return (int)$this->db->letzteInsertId();
    }

    /**
     * Holt alle offenen Einträge (status = 'offen') in zeitlicher Reihenfolge.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeOffene(int $limit = 100): array
    {
        if ($limit < 1) {
            $limit = 1;
        }

        $limit = (int)$limit;

        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = \'offen\'
                ORDER BY erstellt_am ASC, id ASC
                LIMIT ' . $limit;

        return $this->db->fetchAlle($sql);
    }

    /**
     * Holt Einträge für einen bestimmten Status in zeitlicher Reihenfolge.
     *
     * @param string $status Erwartete Werte: 'offen', 'verarbeitet', 'fehler'
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeNachStatus(string $status, int $limit = 200): array
    {
        $status = (string)$status;
        if ($limit < 1) {
            $limit = 1;
        }

        $limit = (int)$limit;

        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = :status
                ORDER BY erstellt_am ASC, id ASC
                LIMIT ' . $limit;

        return $this->db->fetchAlle($sql, ['status' => $status]);
    }


    /**
     * Markiert einen Eintrag als verarbeitet.
     */
    public function markiereAlsVerarbeitet(int $id): void
    {
        $sql = 'UPDATE db_injektionsqueue
                SET status = \'verarbeitet\',
                    letzte_ausfuehrung = NOW(),
                    versuche = versuche + 1,
                    fehlernachricht = NULL
                WHERE id = :id';

        $this->db->ausfuehren($sql, ['id' => $id]);
    }

    /**
     * Markiert einen Eintrag mit Fehler.
     */
    public function markiereMitFehler(int $id, string $fehlermeldung): void
    {
        $sql = 'UPDATE db_injektionsqueue
                SET status = \'fehler\',
                    letzte_ausfuehrung = NOW(),
                    versuche = versuche + 1,
                    fehlernachricht = :fehlermeldung
                WHERE id = :id';

        $params = [
            'id'             => $id,
            'fehlermeldung'  => $fehlermeldung,
        ];

        $this->db->ausfuehren($sql, $params);
    }

    /**
     * Setzt einen Fehler-Eintrag wieder auf 'offen' (für Admin-Korrekturen).
     */
    public function setzeStatusAufOffen(int $id): void
    {
        $sql = 'UPDATE db_injektionsqueue
                SET status = \'offen\',
                    fehlernachricht = NULL
                WHERE id = :id';

        $this->db->ausfuehren($sql, ['id' => $id]);
    }
}
