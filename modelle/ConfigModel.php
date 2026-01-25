<?php
declare(strict_types=1);

/**
 * ConfigModel
 *
 * Reiner Datenzugriff für die Tabelle `config`.
 * Für höhere Logik (Typkonvertierung, Caching) ist der KonfigurationService zuständig.
 */
class ConfigModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Lädt einen Konfigurationswert nach Schlüssel.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachSchluessel(string $schluessel): ?array
    {
        $schluessel = trim($schluessel);
        if ($schluessel === '') {
            return null;
        }

        try {
            $sql = 'SELECT *
                    FROM config
                    WHERE schluessel = :schluessel
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['schluessel' => $schluessel]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Config-Eintrags', [
                    'schluessel' => $schluessel,
                    'exception'  => $e->getMessage(),
                ], null, null, 'config');
            }

            return null;
        }
    }

    /**
     * Lädt alle Konfigurationswerte.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlle(): array
    {
        try {
            $sql = 'SELECT *
                    FROM config
                    ORDER BY schluessel ASC';

            return $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden aller Config-Einträge', [
                    'exception' => $e->getMessage(),
                ], null, null, 'config');
            }

            return [];
        }
    }
}
