<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/Autoloader.php';
umask(0027);
try {
    $wurzel = dirname(__DIR__);
    $konfig = WartungSystem::einrichten($wurzel, $argv[1] ?? 'www-data', array_slice($argv, 2));
    $app = WartungSystem::anwendung($wurzel, $konfig);
    WartungSystem::vorbereiten($wurzel, $konfig, $app);
    echo "Backup und Updates sind automatisch vorbereitet.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Die Installation konnte Backup und Updates nicht vorbereiten: " . ($e instanceof PDOException ? 'Datenbankzugriff prüfen.' : $e->getMessage()) . "\n");
    exit(1);
}
