<?php
declare(strict_types=1);

/**
 * AuftragszeitModel
 *
 * Datenzugriffe für die Tabelle `auftragszeit`.
 */
class AuftragszeitModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt die zuletzt pausierte Auftragszeit eines Mitarbeiters.
     *
     * @return array<string,mixed>|null
     */
    public function holeLetztePausierteFuerMitarbeiter(int $mitarbeiterId, ?string $typ = null): ?array
    {
        $mitarbeiterId = max(1, $mitarbeiterId);
        $typ = $typ !== null ? trim($typ) : null;
        if ($typ !== null && !in_array($typ, ['haupt', 'neben'], true)) {
            $typ = null;
        }

        try {
            $sql = 'SELECT *
                    FROM auftragszeit
                    WHERE mitarbeiter_id = :mitarbeiter_id
                      AND status = \'pausiert\'';

            $params = ['mitarbeiter_id' => $mitarbeiterId];
            if ($typ !== null) {
                $sql .= ' AND typ = :typ';
                $params['typ'] = $typ;
            }

            $sql .= ' ORDER BY endzeit DESC, id DESC
                      LIMIT 1';

            return $this->datenbank->fetchEine($sql, $params);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der zuletzt pausierten Auftragszeit', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'typ'            => $typ,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftrag');
            }

            return null;
        }
    }

    /**
     * Lädt alle pausierten Auftragszeiten eines Mitarbeiters (neueste zuerst).
     *
     * @return array<int,array<string,mixed>>
     */
    public function holePausierteFuerMitarbeiter(int $mitarbeiterId): array
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        try {
            $sql = 'SELECT *
                    FROM auftragszeit
                    WHERE mitarbeiter_id = :mitarbeiter_id
                      AND status = \'pausiert\'
                    ORDER BY endzeit DESC, id DESC';

            return $this->datenbank->fetchAlle($sql, ['mitarbeiter_id' => $mitarbeiterId]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden pausierter Auftragszeiten', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftrag');
            }

            return [];
        }
    }

    /**
     * Lädt eine Auftragszeit nach ID.
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
                    FROM auftragszeit
                    WHERE id = :id
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden einer Auftragszeit nach ID', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'auftrag');
            }

            return null;
        }
    }

    /**
     * Lädt alle aktuell laufenden Auftragszeiten eines Mitarbeiters.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeLaufendeFuerMitarbeiter(int $mitarbeiterId): array
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        try {
            $sql = 'SELECT *
                    FROM auftragszeit
                    WHERE mitarbeiter_id = :mitarbeiter_id
                      AND status = \'laufend\'
                    ORDER BY startzeit ASC, id ASC';

            return $this->datenbank->fetchAlle($sql, ['mitarbeiter_id' => $mitarbeiterId]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden laufender Auftragszeiten eines Mitarbeiters (Model)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftrag');
            }

            return [];
        }
    }


    /**
     * Beendet alle aktuell laufenden Hauptaufträge eines Mitarbeiters.
     *
     * Setzt endzeit und status = 'abgeschlossen' für alle passenden Zeilen.
     */
    public function beendeLaufendeHauptauftraege(int $mitarbeiterId, \DateTimeImmutable $zeitpunkt): void
    {
        $mitarbeiterId = max(1, $mitarbeiterId);

        try {
            $sql = 'UPDATE auftragszeit
                    SET endzeit = :endzeit,
                        status  = \'abgeschlossen\'
                    WHERE mitarbeiter_id = :mitarbeiter_id
                      AND typ = \'haupt\'
                      AND status = \'laufend\'
                      AND endzeit IS NULL';

            $this->datenbank->ausfuehren($sql, [
                'endzeit'        => $zeitpunkt->format('Y-m-d H:i:s'),
                'mitarbeiter_id' => $mitarbeiterId,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Beenden laufender Hauptaufträge (Model)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftrag');
            }
        }
    }

    /**
     * Erstellt eine neue Auftragszeit.
     *
     * @return int|null ID der neu erzeugten Auftragszeit oder null bei Fehler
     */
    public function erstelleAuftragszeit(
        int $mitarbeiterId,
        ?int $auftragId,
        ?string $auftragscode,
        ?int $arbeitsschrittId,
        ?string $arbeitsschrittCode,
        ?int $maschineId,
        ?int $terminalId,
        string $typ,
        \DateTimeImmutable $startzeit,
        ?string $kommentar = null
    ): ?int {
        $mitarbeiterId = max(1, $mitarbeiterId);
        $typ = $typ === 'neben' ? 'neben' : 'haupt';

        $sql = 'INSERT INTO auftragszeit (
                    mitarbeiter_id,
                    auftrag_id,
                    auftragscode,
                    arbeitsschritt_id,
                    arbeitsschritt_code,
                    maschine_id,
                    terminal_id,
                    typ,
                    startzeit,
                    kommentar
                ) VALUES (
                    :mitarbeiter_id,
                    :auftrag_id,
                    :auftragscode,
                    :arbeitsschritt_id,
                    :arbeitsschritt_code,
                    :maschine_id,
                    :terminal_id,
                    :typ,
                    :startzeit,
                    :kommentar
                )';

        $params = [
            'mitarbeiter_id' => $mitarbeiterId,
            'auftrag_id'     => $auftragId,
            'auftragscode'   => $auftragscode,
            'arbeitsschritt_id' => $arbeitsschrittId,
            'arbeitsschritt_code' => $arbeitsschrittCode,
            'maschine_id'    => $maschineId,
            'terminal_id'    => $terminalId,
            'typ'            => $typ,
            'startzeit'      => $startzeit->format('Y-m-d H:i:s'),
            'kommentar'      => $kommentar,
        ];

        try {
            $this->datenbank->ausfuehren($sql, $params);
            return (int)$this->datenbank->letzteInsertId();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Erstellen einer Auftragszeit (Model)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'auftrag_id'     => $auftragId,
                    'auftragscode'   => $auftragscode,
                    'arbeitsschritt_id' => $arbeitsschrittId,
                    'arbeitsschritt_code' => $arbeitsschrittCode,
                    'maschine_id'    => $maschineId,
                    'terminal_id'    => $terminalId,
                    'typ'            => $typ,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'auftrag');
            }

            return null;
        }
    }

    /**
     * Beendet eine konkrete Auftragszeit.
     *
     * @param int                 $auftragszeitId ID der Auftragszeit
     * @param \DateTimeImmutable $zeitpunkt      Zeitstempel für das Ende
     * @param string              $status         Zielstatus (abgeschlossen/abgebrochen/pausiert)
     *
     * @return bool true bei Erfolg, false bei Fehler
     */
    public function beendeAuftragszeit(int $auftragszeitId, \DateTimeImmutable $zeitpunkt, string $status = 'abgeschlossen'): bool
    {
        $auftragszeitId = max(1, $auftragszeitId);

        if ($auftragszeitId <= 0) {
            return false;
        }

        if (!in_array($status, ['abgeschlossen', 'abgebrochen', 'pausiert'], true)) {
            $status = 'abgeschlossen';
        }

        try {
            $sql = 'UPDATE auftragszeit
                    SET endzeit = :endzeit,
                        status  = :status
                    WHERE id = :id
                      AND status = \'laufend\'
                      AND endzeit IS NULL';

            $this->datenbank->ausfuehren($sql, [
                'endzeit' => $zeitpunkt->format('Y-m-d H:i:s'),
                'status'  => $status,
                'id'      => $auftragszeitId,
            ]);

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Beenden einer Auftragszeit (Model)', [
                    'auftragszeit_id' => $auftragszeitId,
                    'status'          => $status,
                    'exception'       => $e->getMessage(),
                ], null, null, 'auftrag');
            }

            return false;
        }
    }

}
