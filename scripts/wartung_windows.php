<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') { exit(1); }
require dirname(__DIR__) . '/core/Autoloader.php';
// Jeder Takt startet frischen Anwendungscode; der Browser muss nicht offen sein.
$sperre = fopen(WartungSystem::pfad(dirname(__DIR__)) . '/takt.lock', 'c');
if (!$sperre || !flock($sperre, LOCK_EX | LOCK_NB)) { exit(1); }
while (true) {
    try { WartungDateien::prozess(WartungPlattform::phpSkript(__DIR__ . '/wartung.php', ['dienst']), frist: 86400, jsonFehler: true); }
    catch (Throwable $e) { /* Der Kindprozess setzt das sichtbare Dienst-Lebenszeichen. */ }
    sleep(2);
}
