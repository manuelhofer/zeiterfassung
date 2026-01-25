<?php
declare(strict_types=1);

/**
 * Logger
 *
 * Einfache Logging-Klasse, die – sofern möglich – in die Tabelle `system_log`
 * schreibt und ansonsten auf `error_log()` zurückfällt.
 */
class Logger
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warn';
    public const LEVEL_ERROR = 'error';

    /**
     * Zentrale Log-Methode.
     *
     * @param array<string,mixed> $daten
     */
    public static function log(
        string $level,
        string $nachricht,
        array $daten = [],
        ?int $mitarbeiterId = null,
        ?int $terminalId = null,
        ?string $kategorie = null
    ): void {
        $level = strtolower($level);

        if ($level !== self::LEVEL_DEBUG
            && $level !== self::LEVEL_INFO
            && $level !== self::LEVEL_WARN
            && $level !== self::LEVEL_ERROR
        ) {
            $level = self::LEVEL_INFO;
        }

        $jsonDaten = null;
        if (!empty($daten)) {
            try {
                $jsonDaten = json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (\Throwable $e) {
                $jsonDaten = null;
            }
        }

        try {
            $db = Database::getInstanz();

            $sql = 'INSERT INTO system_log
                        (zeitstempel, loglevel, kategorie, nachricht, daten, mitarbeiter_id, terminal_id)
                    VALUES (NOW(), :loglevel, :kategorie, :nachricht, :daten, :mitarbeiter_id, :terminal_id)';

            $params = [
                'loglevel'      => $level,
                'kategorie'     => $kategorie,
                'nachricht'     => $nachricht,
                'daten'         => $jsonDaten,
                'mitarbeiter_id'=> $mitarbeiterId,
                'terminal_id'   => $terminalId,
            ];

            $db->ausfuehren($sql, $params);
        } catch (\Throwable $e) {
            // Fallback: PHP error_log
            $fallback = [
                'zeit'          => date('c'),
                'level'         => $level,
                'kategorie'     => $kategorie,
                'nachricht'     => $nachricht,
                'daten'         => $daten,
                'mitarbeiter_id'=> $mitarbeiterId,
                'terminal_id'   => $terminalId,
                'log_fehler'    => $e->getMessage(),
            ];

            // Fehler bei json_encode hier ignorieren und ggf. var_export nutzen
            $text = '[Logger-Fallback] ' . var_export($fallback, true);

            error_log($text);
        }
    }

    /**
     * Convenience-Methoden für die einzelnen Log-Level.
     *
     * @param array<string,mixed> $daten
     */
    public static function debug(string $nachricht, array $daten = [], ?int $mitarbeiterId = null, ?int $terminalId = null, ?string $kategorie = null): void
    {
        self::log(self::LEVEL_DEBUG, $nachricht, $daten, $mitarbeiterId, $terminalId, $kategorie);
    }

    /**
     * @param array<string,mixed> $daten
     */
    public static function info(string $nachricht, array $daten = [], ?int $mitarbeiterId = null, ?int $terminalId = null, ?string $kategorie = null): void
    {
        self::log(self::LEVEL_INFO, $nachricht, $daten, $mitarbeiterId, $terminalId, $kategorie);
    }

    /**
     * @param array<string,mixed> $daten
     */
    public static function warn(string $nachricht, array $daten = [], ?int $mitarbeiterId = null, ?int $terminalId = null, ?string $kategorie = null): void
    {
        self::log(self::LEVEL_WARN, $nachricht, $daten, $mitarbeiterId, $terminalId, $kategorie);
    }

    /**
     * @param array<string,mixed> $daten
     */
    public static function error(string $nachricht, array $daten = [], ?int $mitarbeiterId = null, ?int $terminalId = null, ?string $kategorie = null): void
    {
        self::log(self::LEVEL_ERROR, $nachricht, $daten, $mitarbeiterId, $terminalId, $kategorie);
    }
}
