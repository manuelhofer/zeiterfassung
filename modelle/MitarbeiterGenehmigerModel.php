<?php
declare(strict_types=1);

/**
 * MitarbeiterGenehmigerModel
 *
 * Datenzugriff für Tabelle `mitarbeiter_genehmiger`.
 */
class MitarbeiterGenehmigerModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt alle Genehmiger eines Mitarbeiters, sortiert nach Priorität.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeGenehmigerFuerMitarbeiter(int $mitarbeiterId): array
    {
        $sql = 'SELECT mg.*,
                       m.vorname AS genehmiger_vorname,
                       m.nachname AS genehmiger_nachname,
                       CONCAT(m.nachname, ", ", m.vorname) AS genehmiger_name
                FROM mitarbeiter_genehmiger AS mg
                LEFT JOIN mitarbeiter AS m
                    ON m.id = mg.genehmiger_mitarbeiter_id
                WHERE mg.mitarbeiter_id = :mid
                ORDER BY mg.prioritaet ASC, mg.id ASC';

        return $this->db->fetchAlle($sql, ['mid' => $mitarbeiterId]);
    }


    /**
     * Holt alle Mitarbeiter, für die eine Person Genehmiger ist.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeMitarbeiterFuerGenehmiger(int $genehmigerId): array
    {
        $sql = 'SELECT *
                FROM mitarbeiter_genehmiger
                WHERE genehmiger_mitarbeiter_id = :gid
                ORDER BY prioritaet ASC, id ASC';

        return $this->db->fetchAlle($sql, ['gid' => $genehmigerId]);
    }


    /**
     * Speichert die Genehmiger-Zuordnung für einen Mitarbeiter.
     *
     * Vorgehen:
     *  - Bestehende Einträge für den Mitarbeiter löschen.
     *  - Neue Einträge je übergebenem Genehmiger-Datensatz anlegen.
     *
     * @param int $mitarbeiterId
     * @param array<int,array<string,mixed>> $eintraege Array von Datensätzen mit
     *        - genehmiger_mitarbeiter_id (int)
     *        - prioritaet (int)
     *        - beschreibung (?string)
     * @return bool true bei Erfolg, false bei Fehler.
     */
    public function speichereGenehmigerFuerMitarbeiter(int $mitarbeiterId, array $eintraege): bool
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        $bereinigt = [];

        $geseheneGenehmigerIds = [];

        foreach ($eintraege as $eintrag) {
            $gid   = isset($eintrag['genehmiger_mitarbeiter_id']) ? (int)$eintrag['genehmiger_mitarbeiter_id'] : 0;
            $prio  = isset($eintrag['prioritaet']) ? (int)$eintrag['prioritaet'] : 0;
            $besch = $eintrag['kommentar'] ?? null;

            // Mitarbeiter kann nicht selbst Genehmiger sein, und ID muss > 0 sein
            if ($gid <= 0 || $gid === $mitarbeiterId) {
                continue;
            }

            // Doppelte Genehmiger-IDs ignorieren (erste Zeile gewinnt)
            if (isset($geseheneGenehmigerIds[$gid])) {
                continue;
            }
            $geseheneGenehmigerIds[$gid] = true;

            if ($besch !== null) {
                $besch = trim((string)$besch);
                if ($besch === '') {
                    $besch = null;
                }
            }

            // Priorität mindestens 1
            if ($prio <= 0) {
                $prio = 1;
            }

            $bereinigt[] = [
                'genehmiger_mitarbeiter_id' => $gid,
                'prioritaet'                => $prio,
                'kommentar'                => $besch,
            ];
        }

        try {
            // Bestehende Einträge entfernen
            $sqlDel = 'DELETE FROM mitarbeiter_genehmiger WHERE mitarbeiter_id = :mid';
            $this->db->ausfuehren($sqlDel, ['mid' => $mitarbeiterId]);

            if (count($bereinigt) === 0) {
                return true;
            }

            // Neue Einträge anlegen
            $sqlIns = 'INSERT INTO mitarbeiter_genehmiger (mitarbeiter_id, genehmiger_mitarbeiter_id, prioritaet, kommentar)
                       VALUES (:mid, :gid, :prio, :besch)';

            foreach ($bereinigt as $row) {
                $this->db->ausfuehren($sqlIns, [
                    'mid'   => $mitarbeiterId,
                    'gid'   => $row['genehmiger_mitarbeiter_id'],
                    'prio'  => $row['prioritaet'],
                    'besch' => $row['kommentar'],
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern der Genehmiger eines Mitarbeiters', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'eintraege'      => $bereinigt,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'genehmiger');
            }

            return false;
        }
    }

}
