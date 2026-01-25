<?php
declare(strict_types=1);

/**
 * TageswerteMitarbeiterModel
 *
 * Datenzugriff für Tabelle `tageswerte_mitarbeiter`.
 */
class TageswerteMitarbeiterModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }

    /**
     * Holt einen Tageswert-Datensatz für einen Mitarbeiter und ein Datum.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachMitarbeiterUndDatum(int $mitarbeiterId, string $datum): ?array
    {
        $sql = 'SELECT *
                FROM tageswerte_mitarbeiter
                WHERE mitarbeiter_id = :mid
                  AND datum = :datum
                LIMIT 1';

        $params = [
            'mid'   => $mitarbeiterId,
            'datum' => $datum,
        ];

        $ergebnis = $this->db->fetchEine($sql, $params);

        if ($ergebnis === null) {
            return null;
        }

        return $ergebnis;
    }

    /**
     * Holt alle Tageswerte eines Mitarbeiters für einen Monat.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleFuerMitarbeiterUndMonat(int $mitarbeiterId, int $jahr, int $monat): array
    {
        // Ersten und ersten Tag des Folgemonats bestimmen
        try {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
        } catch (\Throwable $e) {
            $start = new \DateTimeImmutable('first day of this month');
        }

        $naechsterMonat = $start->modify('+1 month');

        $sql = 'SELECT *
                FROM tageswerte_mitarbeiter
                WHERE mitarbeiter_id = :mid
                  AND datum >= :von
                  AND datum < :bis
                ORDER BY datum ASC';

        $params = [
            'mid' => $mitarbeiterId,
            'von' => $start->format('Y-m-d'),
            'bis' => $naechsterMonat->format('Y-m-d'),
        ];

        return $this->db->fetchAlle($sql, $params);
    }

    /**
     * Schreibt Rohzeiten (kommen/gehen) und die berechneten Iststunden in `tageswerte_mitarbeiter`.
     *
     * Wichtig:
     * - Rundung wird nicht gespeichert (kommen_korr/gehen_korr werden auf NULL gesetzt).
     * - Es werden **nur** Rohzeiten + ist_stunden aktualisiert.
     * - Pause/Urlaub/Krank/Flags/Kommentar bleiben unangetastet (MVP-Logik).
     */
    public function upsertRohzeitenUndIststunden(
        int $mitarbeiterId,
        string $datum,
        ?string $kommenRoh,
        ?string $gehenRoh,
        float $istStunden,
        int $pauseMinuten = 0
    ): bool {
        $mitarbeiterId = (int)$mitarbeiterId;
        $datum = trim($datum);

        if ($mitarbeiterId <= 0 || $datum === '') {
            return false;
        }

        // Iststunden immer auf 2 Nachkommastellen normalisieren.
        if ($istStunden < 0) {
            $istStunden = 0.0;
        }
        $istStunden = round($istStunden, 2);

        // Pausenminuten defensiv normalisieren.
        $pauseMinuten = max(0, (int)$pauseMinuten);

        $sql = 'INSERT INTO tageswerte_mitarbeiter
                    (mitarbeiter_id, datum, kommen_roh, gehen_roh, kommen_korr, gehen_korr, pause_korr_minuten, ist_stunden, rohdaten_manuell_geaendert)
                VALUES
                    (:mid, :datum, :kommen_roh, :gehen_roh, NULL, NULL, :pause_min, :ist, 0)
                ON DUPLICATE KEY UPDATE
                    kommen_roh = VALUES(kommen_roh),
                    gehen_roh  = VALUES(gehen_roh),
                    kommen_korr = NULL,
                    gehen_korr  = NULL,
                    -- Pausenminuten werden automatisch abgeleitet, sollen aber manuelle Tagesfeld-Overrides respektieren.
                    -- Wenn ein Admin Tagesfelder manuell gesetzt hat (`felder_manuell_geaendert=1`),
                    -- überschreiben wir die Pause hier nicht mehr (sonst würde eine spätere manuelle Pause "weg-synchronisiert").
                    pause_korr_minuten = CASE
                        WHEN felder_manuell_geaendert = 1 THEN pause_korr_minuten
                        ELSE VALUES(pause_korr_minuten)
                    END,
                    ist_stunden = VALUES(ist_stunden),
                    rohdaten_manuell_geaendert = 0';

        $params = [
            'mid'        => $mitarbeiterId,
            'datum'      => $datum,
            'kommen_roh' => $kommenRoh,
            'gehen_roh'  => $gehenRoh,
            'pause_min'  => $pauseMinuten,
            'ist'        => sprintf('%.2f', $istStunden),
        ];

        try {
            $this->db->ausfuehren($sql, $params);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Tageswerte Upsert fehlgeschlagen', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'datum'          => $datum,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'tageswerte');
            }
            return false;
        }
    }

}
