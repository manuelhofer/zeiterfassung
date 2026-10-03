<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/core/Autoloader.php';
if (($argv[1] ?? '') === 'status-pfad') {
    echo WartungSystem::pfad($argv[2]);
    exit;
}
if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP mindestens 8.2 erforderlich.\n"); exit(1); }
foreach (['pdo_mysql','zip','openssl','mbstring','gd','curl'] as $erweiterung) {
    if (!extension_loaded($erweiterung)) { fwrite(STDERR, "PHP-Erweiterung fehlt: $erweiterung\n"); exit(1); }
}
