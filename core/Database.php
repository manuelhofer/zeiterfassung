<?php
declare(strict_types=1);

/**
 * Database
 *
 * Einfache PDO-Wrapper-Klasse mit Singleton-Zugriff.
 * Liest ihre Konfiguration direkt aus `config/config.php`.
 */
class Database
{
    private static ?Database $instanz = null;

    /** @var array<string,mixed> */
    private array $config;

    /** @var array<string,mixed> */
    private array $dbKonfig;

    /** @var array<string,mixed> */
    private array $offlineDbKonfig;

    private ?\PDO $hauptPdo = null;

    private ?\PDO $offlinePdo = null;

    /**
     * Privater Konstruktor – nutze `getInstanz()`.
     */
    private function __construct()
    {
        $konfigPfad = __DIR__ . '/../config/config.php';

        if (!is_file($konfigPfad)) {
            throw new \RuntimeException('Konfigurationsdatei config/config.php wurde nicht gefunden.');
        }

        /** @var array<string,mixed> $config */
        $config = require $konfigPfad;

        $this->config = $config;

        if (!isset($this->config['db']) || !is_array($this->config['db'])) {
            throw new \RuntimeException('DB-Konfiguration in config/config.php fehlt oder ist ungültig.');
        }

        /** @var array<string,mixed> $dbKonfig */
        $dbKonfig = $this->config['db'];
        $this->dbKonfig = $dbKonfig;

        // Offline-DB ist optional; Default: disabled
        $offline = $this->config['offline_db'] ?? [];
        if (!is_array($offline)) {
            $offline = [];
        }
        /** @var array<string,mixed> $offline */
        $this->offlineDbKonfig = $offline;

        // WICHTIG: keine sofortige Verbindung – lazy connect.
        // Dadurch kann ein Terminal auch starten, wenn die Haupt-DB gerade down ist.
    }

    /**
     * Liefert die Singleton-Instanz.
     */
    public static function getInstanz(): Database
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Gibt das interne PDO-Objekt zurück (falls direkt benötigt).
     */
    public function getPdo(): \PDO
    {
        return $this->getVerbindung();
    }

    /**
     * Liefert die PDO-Verbindung zur Hauptdatenbank.
     *
     * Hinweis: Kann eine Exception werfen, falls die Hauptdatenbank nicht erreichbar ist.
     */
    public function getVerbindung(): \PDO
    {
        if ($this->hauptPdo instanceof \PDO) {
            return $this->hauptPdo;
        }

        $this->hauptPdo = $this->erstellePdoAusKonfig($this->dbKonfig);
        return $this->hauptPdo;
    }

    /**
     * Liefert die PDO-Verbindung zur Offline-Datenbank (falls aktiv & erreichbar) oder null.
     */
    public function getOfflineVerbindung(): ?\PDO
    {
        $enabled = $this->offlineDbKonfig['enabled'] ?? false;
        if ($enabled !== true) {
            return null;
        }

        if ($this->offlinePdo instanceof \PDO) {
            return $this->offlinePdo;
        }

        try {
            $this->offlinePdo = $this->erstellePdoAusKonfig($this->offlineDbKonfig);
            return $this->offlinePdo;
        } catch (\Throwable $e) {
            // Offline-DB ist optional – bei Fehlern einfach null zurückgeben.
            return null;
        }
    }

    /**
     * Prüft, ob die Hauptdatenbank erreichbar ist (ohne die App hart zu crashen).
     */
    public function istHauptdatenbankVerfuegbar(): bool
    {
        try {
            $pdo = $this->getVerbindung();
            $stmt = $pdo->query('SELECT 1');
            return $stmt !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Führt ein SELECT aus und gibt genau einen Datensatz oder null zurück.
     *
     * @param array<string,mixed> $parameter
     *
     * @return array<string,mixed>|null
     */
    public function fetchEine(string $sql, array $parameter = []): ?array
    {
        $stmt = $this->getVerbindung()->prepare($sql);
        $stmt->execute($parameter);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * Führt ein SELECT aus und gibt alle Datensätze als Array zurück.
     *
     * @param array<string,mixed> $parameter
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchAlle(string $sql, array $parameter = []): array
    {
        $stmt = $this->getVerbindung()->prepare($sql);
        $stmt->execute($parameter);

        /** @var array<int,array<string,mixed>> $daten */
        $daten = $stmt->fetchAll();

        return $daten;
    }

    /**
     * Führt ein INSERT/UPDATE/DELETE aus und gibt die Anzahl betroffener Zeilen zurück.
     *
     * @param array<string,mixed> $parameter
     */
    public function ausfuehren(string $sql, array $parameter = []): int
    {
        $stmt = $this->getVerbindung()->prepare($sql);
        $stmt->execute($parameter);

        return $stmt->rowCount();
    }

    /**
     * Gibt die ID des letzten INSERTs zurück.
     */
    public function letzteInsertId(): string
    {
        return $this->getVerbindung()->lastInsertId();
    }

    /**
     * Erstellt eine PDO-Verbindung aus einer DB-Konfiguration.
     *
     * Erwartet Keys: dsn ODER host/dbname/charset, sowie user/pass.
     * Optional: options (PDO options array).
     *
     * @param array<string,mixed> $dbKonfig
     */
    private function erstellePdoAusKonfig(array $dbKonfig): \PDO
    {
        $dsn = '';
        if (!empty($dbKonfig['dsn']) && is_string($dbKonfig['dsn'])) {
            $dsn = $dbKonfig['dsn'];
        } else {
            $host    = (string)($dbKonfig['host']    ?? 'localhost');
            $dbname  = (string)($dbKonfig['dbname']  ?? '');
            $charset = (string)($dbKonfig['charset'] ?? 'utf8mb4');

            if ($dbname === '') {
                throw new \RuntimeException('DB-Name (dbname) ist in config/config.php nicht gesetzt.');
            }

            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);
        }

        $user    = (string)($dbKonfig['user'] ?? '');
        $pass    = (string)($dbKonfig['pass'] ?? '');
        $options = $dbKonfig['options'] ?? [];

        if (!is_array($options)) {
            $options = [];
        }

        // Standard-PDO-Optionen ergänzen, falls nicht gesetzt
        $defaults = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $options)) {
                $options[$key] = $value;
            }
        }

        return new \PDO($dsn, $user, $pass, $options);
    }
}
