<?php
declare(strict_types=1);

/**
 * PausenentscheidungModel
 *
 * Datenzugriff für Tabelle `pausenentscheidung`.
 *
 * Wichtig: Alle Methoden sind defensiv – falls die Migration noch nicht importiert wurde,
 * darf die Anwendung nicht crashen (dann wird einfach "keine Entscheidung" angenommen).
 */
class PausenentscheidungModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function holeFuerMitarbeiterUndDatum(int $mitarbeiterId, string $datum): ?array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        $datum = trim($datum);

        if ($mitarbeiterId <= 0 || $datum === '') {
            return null;
        }

        $sql = 'SELECT *
                FROM pausenentscheidung
                WHERE mitarbeiter_id = :mid AND datum = :datum
                LIMIT 1';

        try {
            return $this->db->fetchEine($sql, [
                'mid'   => $mitarbeiterId,
                'datum' => $datum,
            ]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::debug('Pausenentscheidung: Tabelle fehlt oder Query-Fehler (ignoriert)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'datum'          => $datum,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'pause');
            }
            return null;
        }
    }

    /**
     * Upsert einer Entscheidung.
     *
     * @param string $entscheidung 'ABZIEHEN'|'NICHT_ABZIEHEN'
     */
    public function setzeEntscheidung(
        int $mitarbeiterId,
        string $datum,
        string $entscheidung,
        ?string $kommentar,
        ?int $erstelltVonMitarbeiterId
    ): bool {
        $mitarbeiterId = (int)$mitarbeiterId;
        $datum = trim($datum);
        $entscheidung = strtoupper(trim($entscheidung));

        if ($mitarbeiterId <= 0 || $datum === '') {
            return false;
        }

        if (!in_array($entscheidung, ['ABZIEHEN', 'NICHT_ABZIEHEN'], true)) {
            return false;
        }

        $kommentar = $kommentar !== null ? trim($kommentar) : null;
        if ($kommentar === '') {
            $kommentar = null;
        }

        $erstelltVonMitarbeiterId = $erstelltVonMitarbeiterId !== null ? (int)$erstelltVonMitarbeiterId : null;
        if ($erstelltVonMitarbeiterId !== null && $erstelltVonMitarbeiterId <= 0) {
            $erstelltVonMitarbeiterId = null;
        }

        $sql = 'INSERT INTO pausenentscheidung (mitarbeiter_id, datum, entscheidung, kommentar, erstellt_von_mitarbeiter_id)
                VALUES (:mid, :datum, :ent, :kom, :by)
                ON DUPLICATE KEY UPDATE
                    entscheidung = VALUES(entscheidung),
                    kommentar = VALUES(kommentar),
                    erstellt_von_mitarbeiter_id = VALUES(erstellt_von_mitarbeiter_id),
                    erstellt_am = CURRENT_TIMESTAMP';

        try {
            $rc = $this->db->ausfuehren($sql, [
                'mid'   => $mitarbeiterId,
                'datum' => $datum,
                'ent'   => $entscheidung,
                'kom'   => $kommentar,
                'by'    => $erstelltVonMitarbeiterId,
            ]);
            return $rc >= 0;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Pausenentscheidung: Schreiben fehlgeschlagen', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'datum'          => $datum,
                    'entscheidung'   => $entscheidung,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'pause');
            }
            return false;
        }
    }

    public function loescheEntscheidung(int $mitarbeiterId, string $datum): bool
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        $datum = trim($datum);

        if ($mitarbeiterId <= 0 || $datum === '') {
            return false;
        }

        $sql = 'DELETE FROM pausenentscheidung WHERE mitarbeiter_id = :mid AND datum = :datum';

        try {
            $this->db->ausfuehren($sql, [
                'mid'   => $mitarbeiterId,
                'datum' => $datum,
            ]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
