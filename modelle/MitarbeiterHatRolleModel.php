<?php
declare(strict_types=1);

/**
 * MitarbeiterHatRolleModel
 *
 * Datenzugriff für die Zuordnungstabelle `mitarbeiter_hat_rolle`.
 */
class MitarbeiterHatRolleModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt alle Rollen-IDs eines Mitarbeiters.
     *
     * @return array<int,int> Liste von Rollen-IDs
     */
    public function holeRollenIdsFuerMitarbeiter(int $mitarbeiterId): array
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        try {
            $sql = 'SELECT rolle_id
                    FROM mitarbeiter_hat_rolle
                    WHERE mitarbeiter_id = :mitarbeiter_id';

            $daten = $this->datenbank->fetchAlle($sql, ['mitarbeiter_id' => $mitarbeiterId]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden von Rollen eines Mitarbeiters', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'rolle');
            }

            return [];
        }

        $ids = [];
        foreach ($daten as $row) {
            $ids[] = (int)($row['rolle_id'] ?? 0);
        }

        return $ids;
    }

    /**
     * Speichert die Rollen-Zuordnung für einen Mitarbeiter.
     *
     * Vorgehen:
     *  - Bestehende Einträge für den Mitarbeiter löschen.
     *  - Neue Einträge je übergebener Rollen-ID anlegen.
     *
     * @param int   $mitarbeiterId
     * @param array<int,int|string> $rollenIds
     * @return bool true bei Erfolg, false bei Fehler.
     */
    public function speichereRollenFuerMitarbeiter(int $mitarbeiterId, array $rollenIds): bool
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        // Rollen-IDs bereinigen (int, > 0, eindeutig)
        $bereinigt = [];
        foreach ($rollenIds as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) {
                $bereinigt[$rid] = $rid;
            }
        }
        $rollenIdsBereinigt = array_values($bereinigt);

        try {
            // Bestehende Einträge entfernen
            $sqlDel = 'DELETE FROM mitarbeiter_hat_rolle WHERE mitarbeiter_id = :mid';
            $this->datenbank->ausfuehren($sqlDel, ['mid' => $mitarbeiterId]);

            if (count($rollenIdsBereinigt) === 0) {
                return true;
            }

            // Neue Einträge anlegen
            $sqlIns = 'INSERT INTO mitarbeiter_hat_rolle (mitarbeiter_id, rolle_id)
                       VALUES (:mid, :rid)';

            foreach ($rollenIdsBereinigt as $rid) {
                $this->datenbank->ausfuehren($sqlIns, [
                    'mid' => $mitarbeiterId,
                    'rid' => $rid,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern der Rollen eines Mitarbeiters', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'rollen_ids'     => $rollenIdsBereinigt,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'rolle');
            }

            return false;
        }
    }

}
