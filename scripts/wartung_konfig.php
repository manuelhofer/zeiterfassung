<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Dieser Prozess läuft auch bei privilegiertem Systemdienst ausschließlich als Webbenutzer.
$wurzel = dirname(__DIR__);
$app = require $wurzel . '/config/config.php';
if (!is_file($wurzel . '/config/config.local.php') && is_file($wurzel . '/config/geraet.local.php')) {
    $geraet = require $wurzel . '/config/geraet.local.php';
    $app['app']['installation_typ'] = 'terminal';
    $app['offline_db'] = $geraet['offline_db'] ?? ['enabled' => false];
}
if (($argv[1] ?? '') === '--windows-json' && PHP_OS_FAMILY === 'Windows') {
    require $wurzel . '/core/Autoloader.php';
    // Fester externer Pfad, keine vom Webauftrag vorgegebenen Ausgabepfade.
    WartungDateien::schreiben(WartungSystem::pfad($wurzel) . '/anwendung/config.json', $app, 0600);
} else {
    echo json_encode($app, JSON_THROW_ON_ERROR);
}
