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
echo json_encode($app, JSON_THROW_ON_ERROR);
