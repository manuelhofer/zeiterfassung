<?php
declare(strict_types=1);

/**
 * OfflineQueueManager
 *
 * Verwaltet die Tabelle `db_injektionsqueue` für den Offline-Betrieb.
 *
 * Aufgaben:
 * - SQL-Befehle in die Queue schreiben, wenn die Hauptdatenbank nicht erreichbar ist.
 * - Abarbeitung der Queue, sobald die Hauptdatenbank wieder verfügbar ist.
 * - Abbruch der Abarbeitung beim ersten Fehler („Störungsmodus“).
 * - Bereitstellung von Hilfsfunktionen für das Backend/Terminal-UI (z. B. aktueller Fehler-Eintrag).
 */
class OfflineQueueManager
{
    /** Singleton-Instanz. */
    private static ?OfflineQueueManager $instanz = null;

    private Database $datenbank;

    /**
     * Wird genutzt, um die Queue-Schema-Initialisierung nur einmal pro Prozess
     * auszuführen (idempotent).
     */
    private bool $schemaInitialisiert = false;

    /**
     * Privater Konstruktor.
     */
    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Liefert die Singleton-Instanz.
     */
    public static function getInstanz(): OfflineQueueManager
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Legt einen neuen Eintrag in der Offline-Queue an.
     *
     * Wird typischerweise vom Terminal aufgerufen, wenn die Hauptdatenbank
     * nicht erreichbar ist und eine Aktion später nachgeholt werden soll.
     *
     * @param string      $sqlBefehl    Vollständiger SQL-Befehl, der später 1:1 gegen die Haupt-DB ausgeführt wird.
     * @param int|null    $mitarbeiterId Optionale Mitarbeiter-ID für Metadaten.
     * @param int|null    $terminalId    Optionale Terminal-ID für Metadaten.
     * @param string|null $aktion        Optionale Beschreibung der Aktion (z. B. 'zeit_stempeln', 'auftrag_start').
     */
    public function speichereInQueue(
        string $sqlBefehl,
        ?int $mitarbeiterId = null,
        ?int $terminalId = null,
        ?string $aktion = null
    ): bool {
        $sqlBefehl = trim($sqlBefehl);
        if ($sqlBefehl === '') {
            return false;
        }

        try {
            $pdo = $this->holeQueueVerbindung();
            $this->ensureQueueSchema($pdo);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error(
                    'OfflineQueueManager: Keine DB-Verbindung zum Speichern in db_injektionsqueue',
                    ['exception' => $e->getMessage()],
                    $mitarbeiterId,
                    $terminalId,
                    'offline_queue'
                );
            }

            return false;
        }

        $sql = 'INSERT INTO db_injektionsqueue (status, sql_befehl, meta_mitarbeiter_id, meta_terminal_id, meta_aktion)
                VALUES (\'offen\', :sql_befehl, :meta_mitarbeiter_id, :meta_terminal_id, :meta_aktion)';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':sql_befehl', $sqlBefehl, \PDO::PARAM_STR);

        if ($mitarbeiterId !== null) {
            $statement->bindValue(':meta_mitarbeiter_id', $mitarbeiterId, \PDO::PARAM_INT);
        } else {
            $statement->bindValue(':meta_mitarbeiter_id', null, \PDO::PARAM_NULL);
        }

        if ($terminalId !== null) {
            $statement->bindValue(':meta_terminal_id', $terminalId, \PDO::PARAM_INT);
        } else {
            $statement->bindValue(':meta_terminal_id', null, \PDO::PARAM_NULL);
        }

        if ($aktion !== null && $aktion !== '') {
            $statement->bindValue(':meta_aktion', $aktion, \PDO::PARAM_STR);
        } else {
            $statement->bindValue(':meta_aktion', null, \PDO::PARAM_NULL);
        }

        return $statement->execute();
    }

    /**
     * Abarbeitung aller offenen Queue-Einträge in zeitlicher Reihenfolge.
     *
     * Regeln gemäß Master-Prompt:
     * - Es werden nur Einträge mit Status 'offen' verarbeitet.
     * - Abarbeitung in aufsteigender Reihenfolge von `erstellt_am`, dann `id`.
     * - Beim ersten Fehler:
     *   - wird der Eintrag auf Status 'fehler' gesetzt,
     *   - es wird die Fehlermeldung gespeichert,
     *   - die Abarbeitung wird abgebrochen.
     */
    public function verarbeiteOffeneEintraege(): void
    {
        // Ohne Hauptdatenbank macht das Abarbeiten keinen Sinn.
        if (!$this->datenbank->istHauptdatenbankVerfuegbar()) {
            return;
        }

        $queuePdo = $this->holeQueueVerbindung();
        $this->ensureQueueSchema($queuePdo);
        $hauptPdo = $this->datenbank->getVerbindung();

        $sqlSelect = 'SELECT *
                      FROM db_injektionsqueue
                      WHERE status = \'offen\'
                      ORDER BY erstellt_am ASC, id ASC';

        $statement = $queuePdo->query($sqlSelect);
        if ($statement === false) {
            return;
        }

        while (($eintrag = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $id        = (int)$eintrag['id'];
            $sqlBefehl = (string)$eintrag['sql_befehl'];

            // Sicherstellen, dass der SQL-Befehl nicht leer ist.
            if (trim($sqlBefehl) === '') {
                $this->markiereAlsFehler($queuePdo, $id, 'Leerer SQL-Befehl in db_injektionsqueue.');
                break;
            }

            try {
                // Ausführung auf der Hauptdatenbank.
                $hauptPdo->beginTransaction();
                $hauptPdo->exec($sqlBefehl);
                $hauptPdo->commit();

                $this->markiereAlsVerarbeitet($queuePdo, $id);
            } catch (\Throwable $e) {
                if ($hauptPdo->inTransaction()) {
                    $hauptPdo->rollBack();
                }

                $this->markiereAlsFehler($queuePdo, $id, $e->getMessage());

                if (class_exists('Logger')) {
                    Logger::error(
                        'Fehler bei Abarbeitung von db_injektionsqueue',
                        ['id' => $id, 'sql_befehl' => $sqlBefehl, 'exception' => $e->getMessage()],
                        null,
                        null,
                        'offline_queue'
                    );
                }

                // Beim ersten Fehler abbrechen.
                break;
            }
        }
    }

    /**
     * Gibt den aktuell letzten Fehler-Eintrag aus der Queue zurück (oder null).
     *
     * @return array<string,mixed>|null
     */
    public function holeLetztenFehlerEintrag(): ?array
    {
        try {
            $pdo = $this->holeQueueVerbindung();
            $this->ensureQueueSchema($pdo);
        } catch (\Throwable $e) {
            return null;
        }

        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = \'fehler\'
                ORDER BY letzte_ausfuehrung DESC, id DESC
                LIMIT 1';

        $statement = $pdo->query($sql);
        if ($statement === false) {
            return null;
        }

        $datensatz = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($datensatz === false) {
            return null;
        }

        return $datensatz;
    }

    /**
     * Löscht einen Eintrag dauerhaft aus der Queue.
     *
     * Kann vom Backend/Terminal-Admin verwendet werden, um einen
     * problematischen Eintrag nach manueller Nachpflege zu entfernen
     * und die Queue danach weiterlaufen zu lassen.
     */
    public function loescheEintrag(int $id): void
    {
        try {
            $pdo = $this->holeQueueVerbindung();
            $this->ensureQueueSchema($pdo);
        } catch (\Throwable $e) {
            return;
        }

        $sql = 'DELETE FROM db_injektionsqueue WHERE id = :id';
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Markiert einen Eintrag als verarbeitet.
     */
    private function markiereAlsVerarbeitet(\PDO $pdo, int $id): void
    {
        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'UPDATE db_injektionsqueue
                SET status = \'verarbeitet\',
                    fehlernachricht = NULL,
                    letzte_ausfuehrung = :ts,
                    versuche = versuche + 1
                WHERE id = :id';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':ts', $ts, \PDO::PARAM_STR);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Markiert einen Eintrag als Fehler und speichert die Fehlermeldung.
     */
    private function markiereAlsFehler(\PDO $pdo, int $id, string $fehlerNachricht): void
    {
        // Fehlermeldung auf eine sinnvolle Länge begrenzen.
        if (strlen($fehlerNachricht) > 1000) {
            $fehlerNachricht = substr($fehlerNachricht, 0, 1000);
        }

        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'UPDATE db_injektionsqueue
                SET status = \'fehler\',
                    fehlernachricht = :fehlernachricht,
                    letzte_ausfuehrung = :ts,
                    versuche = versuche + 1
                WHERE id = :id';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':fehlernachricht', $fehlerNachricht, \PDO::PARAM_STR);
        $statement->bindValue(':ts', $ts, \PDO::PARAM_STR);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Stellt sicher, dass die Queue-Tabelle in der verwendeten Queue-DB existiert.
     *
     * Hintergrund:
     * - Für Terminals wird optional eine separate Offline-DB genutzt.
     * - Diese kann leer sein (z. B. SQLite-Datei frisch angelegt).
     * - Dann müssen wir das Minimal-Schema für `db_injektionsqueue` automatisch erstellen.
     */
    private function ensureQueueSchema(\PDO $pdo): void
    {
        if ($this->schemaInitialisiert) {
            return;
        }

        try {
            $probe = $pdo->query('SELECT 1 FROM db_injektionsqueue LIMIT 1');
            if ($probe !== false) {
                $this->schemaInitialisiert = true;
                return;
            }
        } catch (\Throwable $e) {
            // Tabelle existiert vermutlich nicht → wir erstellen sie.
        }

        $driver = '';
        try {
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = '';
        }

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS db_injektionsqueue (\n" .
                "  id INTEGER PRIMARY KEY AUTOINCREMENT,\n" .
                "  status TEXT NOT NULL DEFAULT 'offen',\n" .
                "  sql_befehl TEXT NOT NULL,\n" .
                "  fehlernachricht TEXT NULL,\n" .
                "  versuche INTEGER NOT NULL DEFAULT 0,\n" .
                "  letzte_ausfuehrung TEXT NULL,\n" .
                "  meta_mitarbeiter_id INTEGER NULL,\n" .
                "  meta_terminal_id INTEGER NULL,\n" .
                "  meta_aktion TEXT NULL,\n" .
                "  erstellt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP\n" .
                ");"
            );
        } else {
            // Default: MySQL/MariaDB
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS db_injektionsqueue (\n" .
                "  id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
                "  status VARCHAR(20) NOT NULL DEFAULT 'offen',\n" .
                "  sql_befehl MEDIUMTEXT NOT NULL,\n" .
                "  fehlernachricht TEXT NULL,\n" .
                "  versuche INT UNSIGNED NOT NULL DEFAULT 0,\n" .
                "  letzte_ausfuehrung DATETIME NULL,\n" .
                "  meta_mitarbeiter_id INT UNSIGNED NULL,\n" .
                "  meta_terminal_id INT UNSIGNED NULL,\n" .
                "  meta_aktion VARCHAR(100) NULL,\n" .
                "  erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
                "  PRIMARY KEY (id)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );
        }

        $this->schemaInitialisiert = true;
    }

    /**
     * Ermittelt die passende Verbindung für die Queue-Tabelle.
     *
     * Logik:
     * - Wenn eine Offline-Datenbank konfiguriert und erreichbar ist, wird diese verwendet.
     * - Ansonsten wird die Hauptdatenbank verwendet.
     */
    private function holeQueueVerbindung(): \PDO
    {
        $offline = $this->datenbank->getOfflineVerbindung();
        if ($offline instanceof \PDO) {
            return $offline;
        }

        // Fallback auf Haupt-DB nur dann, wenn sie erreichbar ist.
        if ($this->datenbank->istHauptdatenbankVerfuegbar()) {
            return $this->datenbank->getVerbindung();
        }

        throw new \RuntimeException('Keine Queue-DB verfügbar (Offline-DB nicht aktiv/erreichbar und Haupt-DB offline).');
    }
}
