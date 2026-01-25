<?php
declare(strict_types=1);

/**
 * Zentrale Konfigurationsdatei (einzige Quelle für Zugangsdaten).
 *
 * WICHTIG:
 * - Passe die Werte an deine Umgebung an.
 * - Diese Datei wird vom Backend (`public/index.php`) und von `core/Database.php`
 *   direkt eingelesen.
 * - Die Datei `/config.php` ist nur noch ein Kompatibilitäts-Wrapper und
 *   enthält **keine** Zugangsdaten mehr.
 */

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
        'host'    => 'localhost',
        'dbname'  => 'zeiterfassung',
        'charset' => 'utf8mb4',

        // Zugangsdaten
        'user' => 'zeiterfassung',
        'pass' => 'zeiterfassung',

        // Optionale PDO-Optionen (Standardwerte werden in Database.php ergänzt)
        // 'options' => [
        //     \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        //     \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        //     \PDO::ATTR_EMULATE_PREPARES   => false,
        // ],
    ],

    // Optionale Offline-Datenbank (für Terminals, falls separat genutzt)
    'offline_db' => [
        'enabled' => true,
        'host'    => 'localhost',
        'dbname'  => 'zeiterfassung_offline',
        'charset' => 'utf8mb4',
        'user'    => 'zeiterfassung',
        'pass'    => 'zeiterfassung',
    ],

    // Terminal-spezifische Einstellungen (nur relevant, wenn installation_typ = 'terminal')
    'terminal' => [
        // RFID-Bridge per WebSocket (z. B. RC522 → lokaler Python-Dienst → Browser)
        'rfid_ws' => [
            // Bridge aktivieren/deaktivieren (z. B. wenn ein Keyboard-Wedge Reader genutzt wird)
            'enabled' => true,

            // Default: lokaler Dienst auf dem Terminal
            // Hinweis: bei HTTPS-Terminal-UI muss i. d. R. auch WSS genutzt werden.
            'url' => 'ws://127.0.0.1:8765',
        ],
    ],
];
