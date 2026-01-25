<?php
declare(strict_types=1);

/**
 * ConfigService
 *
 * Einfacher Key-Value-Konfigurationsdienst auf Basis der Tabelle `config`.
 *
 * Hinweis: Für globale PHP-Konfigurationswerte (z. B. DB-Zugang) wird weiterhin
 *          `config/config.php` verwendet. `ConfigService` ist für dynamische
 *          Einstellungen gedacht, die im laufenden Betrieb geändert werden können.
 */
class ConfigService
{
    private static ?ConfigService $instanz = null;

    private Database $db;

    /** @var array<string,mixed> */
    private array $cache = [];

    private function __construct()
    {
        $this->db = Database::getInstanz();
    }

    public static function getInstanz(): ConfigService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Liefert einen Wert aus der Tabelle `config`.
     *
     * @param string     $schluessel
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function get(string $schluessel, $default = null)
    {
        if (array_key_exists($schluessel, $this->cache)) {
            return $this->cache[$schluessel];
        }

        $sql = 'SELECT wert, typ
                FROM config
                WHERE schluessel = :key
                LIMIT 1';

        try {
            $zeile = $this->db->fetchEine($sql, ['key' => $schluessel]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Config-Wertes', [
                    'schluessel' => $schluessel,
                    'exception'  => $e->getMessage(),
                ], null, null, 'config');
            }
            return $default;
        }

        if ($zeile === null) {
            return $default;
        }

        $wertRoh = $zeile['wert'] ?? null;
        $typ     = $zeile['typ'] ?? null;

        $wert = $this->konvertiereNachTyp($wertRoh, $typ);

        $this->cache[$schluessel] = $wert;

        return $wert;
    }

    /**
     * Einfache Typkonvertierung anhand des Feldes `typ`.
     *
     * Unterstützte Typen (Beispiele):
     * - "string" (Standard)
     * - "int"
     * - "float"
     * - "bool"
     * - "json"
     */
    private function konvertiereNachTyp($wertRoh, ?string $typ)
    {
        if ($wertRoh === null) {
            return null;
        }

        $typ = $typ !== null ? strtolower($typ) : 'string';

        switch ($typ) {
            case 'int':
            case 'integer':
                return (int)$wertRoh;

            case 'float':
            case 'double':
                return (float)$wertRoh;

            case 'bool':
            case 'boolean':
                if (is_bool($wertRoh)) {
                    return $wertRoh;
                }
                $v = strtolower((string)$wertRoh);
                return !($v === '' || $v === '0' || $v === 'false' || $v === 'nein');

            case 'json':
                if (!is_string($wertRoh)) {
                    return null;
                }
                $decoded = json_decode($wertRoh, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return null;
                }
                return $decoded;

            case 'string':
            default:
                return (string)$wertRoh;
        }
    }
}