<?php
declare(strict_types=1);

/**
 * Zentrale Konfigurationsdatei (einzige Quelle für Zugangsdaten).
 *
 * WICHTIG:
 * - Lege **echte** Zugangsdaten ausschließlich in `/config/config.local.php`
 *   (nicht versioniert) oder per Umgebungsvariablen ab.
 * - Diese Datei enthält nur Default-/Beispielwerte.
 * - Die Datei `/config.php` ist nur noch ein Kompatibilitäts-Wrapper und
 *   enthält **keine** Zugangsdaten mehr.
 */

$lokaleConfig = __DIR__ . '/config.local.php';
if (is_file($lokaleConfig)) {
    /** @var array<string,mixed> $cfg */
    $cfg = require $lokaleConfig;
    return $cfg;
}

if (!function_exists('config_env')) {
    /**
     * Liest Umgebungsvariablen mit Fallback.
     */
    function config_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return (string)$value;
    }
}

return [
    // App-/Installations-Einstellungen (Master-Prompt konform)
    'app' => [
        // Anzeigename der Anwendung (optional, u. a. für Session-Name)
        'name' => 'Zeiterfassung',

        // Basis-URL der Installation (z. B. '/zeiterfassung' oder 'https://example.org/zeiterfassung')
        'base_url' => '',

        // Debug-Modus (für Entwickler)
        'debug' => false,

        // Kennzeichnung, welcher Installationstyp vorliegt:
        // - 'backend'  → Zentrales Hauptsystem
        // - 'terminal' → Hallen-/Werkstattterminal im Kioskmodus
        'installation_typ' => 'backend',
    ],

    // Standard-Zeitzone der Anwendung (wird im Front-Controller gesetzt)
    // Hinweis: Dieser Key bleibt bewusst auf Root-Ebene bestehen, weil der
    //          bestehende Code ihn bereits verwendet.
    'timezone' => 'Europe/Berlin',

    // Hauptdatenbank (MySQL/MariaDB)
    'db' => [
        // Entweder direkt ein kompletter DSN-String ...
        // 'dsn'  => 'mysql:host=localhost;dbname=zeiterfassung;charset=utf8mb4',

        // ... oder Host/DB-Name/Charset angeben (daraus wird ein DSN gebaut):
        'host'    => config_env('ZEIT_DB_HOST', 'localhost'),
        'dbname'  => config_env('ZEIT_DB_NAME', 'zeiterfassung'),
        'charset' => config_env('ZEIT_DB_CHARSET', 'utf8mb4'),

        // Zugangsdaten (Default bewusst generisch)
        'user' => config_env('ZEIT_DB_USER', 'zeiterfassung'),
        'pass' => config_env('ZEIT_DB_PASS', ''),

        // Optionale PDO-Optionen (Standardwerte werden in Database.php ergänzt)
        // 'options' => [
        //     \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        //     \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        //     \PDO::ATTR_EMULATE_PREPARES   => false,
        // ],
    ],

    // Optionale Offline-Datenbank (für Terminals, falls separat genutzt)
    'offline_db' => [
        'enabled' => (config_env('ZEIT_OFFLINE_DB_ENABLED', '1') === '1'),
        'host'    => config_env('ZEIT_OFFLINE_DB_HOST', 'localhost'),
        'dbname'  => config_env('ZEIT_OFFLINE_DB_NAME', 'zeiterfassung_offline'),
        'charset' => config_env('ZEIT_OFFLINE_DB_CHARSET', 'utf8mb4'),
        'user'    => config_env('ZEIT_OFFLINE_DB_USER', 'zeiterfassung'),
        'pass'    => config_env('ZEIT_OFFLINE_DB_PASS', ''),
    ],

    // Terminal-spezifische Einstellungen (nur relevant, wenn installation_typ = 'terminal')
    'terminal' => [
        // RFID-Bridge per WebSocket (z. B. RC522 → lokaler Python-Dienst → Browser)
        'rfid_ws' => [
            // Bridge aktivieren/deaktivieren (z. B. wenn ein Keyboard-Wedge Reader genutzt wird)
            'enabled' => (config_env('ZEIT_RFID_WS_ENABLED', '1') === '1'),

            // Default: lokaler Dienst auf dem Terminal
            // Hinweis: bei HTTPS-Terminal-UI muss i. d. R. auch WSS genutzt werden.
            'url' => config_env('ZEIT_RFID_WS_URL', 'ws://127.0.0.1:8765'),
        ],
    ],

    // QR-Code-Pfade für Maschinen
    // Basis-URL für die Ausgabe (kann absolute URL oder Pfad relativ zur Domain sein)
    'maschinen_qr_base_url' => '',
    // Optionaler relativer Pfad unterhalb des public-Verzeichnisses
    'maschinen_qr_rel_pfad' => 'uploads/maschinen_codes',
    // Kompatibilitätsschlüssel (alt)
    'qr_maschinen_rel_pfad' => 'uploads/maschinen_codes',
];
