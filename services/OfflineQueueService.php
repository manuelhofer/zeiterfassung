<?php
declare(strict_types=1);

/**
 * OfflineQueueService
 *
 * Verwaltet die Injektions-Queue (`db_injektionsqueue`) für Offline-Terminals.
 *
 * Wichtige Punkte:
 * - Erzeugt neue Queue-Einträge mit rohem SQL-Befehl.
 * - Kann offene Einträge in korrekter Reihenfolge über einen Callback ausführen lassen.
 * - Stoppt beim ersten Fehler, markiert diesen Eintrag als `fehler` und bricht ab
 *   (damit ein Admin ihn explizit behandeln muss).
 */
class OfflineQueueService
{
    private static ?OfflineQueueService $instanz = null;

    private DbInjektionsqueueModel $queueModel;

    private function __construct()
    {
        $this->queueModel = new DbInjektionsqueueModel();
    }

    public static function getInstanz(): OfflineQueueService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Fügt einen SQL-Befehl der Queue hinzu.
     *
     * @param string      $sqlBefehl
     * @param int|null    $metaMitarbeiterId
     * @param int|null    $metaTerminalId
     * @param string|null $metaAktion       z. B. 'zeitbuchung_kommen', 'zeitbuchung_gehen', 'auftrag_start'
     *
     * @return int ID des Queue-Eintrags
     */
    public function enqueueSql(
        string $sqlBefehl,
        ?int $metaMitarbeiterId = null,
        ?int $metaTerminalId = null,
        ?string $metaAktion = null
    ): int {
        return $this->queueModel->fuegeEin(
            $sqlBefehl,
            $metaMitarbeiterId,
            $metaTerminalId,
            $metaAktion
        );
    }

    /**
     * Verarbeitet alle offenen Einträge in zeitlicher Reihenfolge.
     *
     * Die eigentliche Ausführung des SQL-Befehls übernimmt ein Callback, das
     * typischerweise die Hauptdatenbank verwendet.
     *
     * @param callable $executor  Funktion mit Signatur `function (string $sqlBefehl): void`
     * @param int      $limit     Maximale Anzahl Einträge pro Lauf
     */
    public function verarbeiteOffeneMitExecutor(callable $executor, int $limit = 100): void
    {
        $eintraege = $this->queueModel->holeOffene($limit);

        foreach ($eintraege as $eintrag) {
            $id        = (int)$eintrag['id'];
            $sqlBefehl = (string)$eintrag['sql_befehl'];

            try {
                // SQL-Befehl übergeben – der Callback entscheidet,
                // wie und gegen welche DB er ausgeführt wird.
                $executor($sqlBefehl);

                $this->queueModel->markiereAlsVerarbeitet($id);

                if (class_exists('Logger')) {
                    Logger::info('Offline-Queue-Eintrag verarbeitet', [
                        'queue_id' => $id,
                    ], (int)($eintrag['meta_mitarbeiter_id'] ?? 0), (int)($eintrag['meta_terminal_id'] ?? 0), 'offline_queue');
                }
            } catch (\Throwable $e) {
                $fehlermeldung = $e->getMessage();

                $this->queueModel->markiereMitFehler($id, $fehlermeldung);

                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Verarbeiten eines Offline-Queue-Eintrags', [
                        'queue_id'  => $id,
                        'exception' => $fehlermeldung,
                        'sql'       => $sqlBefehl,
                    ], (int)($eintrag['meta_mitarbeiter_id'] ?? 0), (int)($eintrag['meta_terminal_id'] ?? 0), 'offline_queue');
                }

                // Beim ersten Fehler abbrechen – Admin muss eingreifen.
                break;
            }
        }
    }
}
