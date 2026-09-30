<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/Autoloader.php';
$wurzel = dirname(__DIR__);
if (is_file($wurzel . '/config/config.local.php')) { echo "Vorhandene Backendkonfiguration bleibt erhalten.\n"; exit; }
$admin = ['dsn' => 'mysql:unix_socket=' . ini_get('pdo_mysql.default_socket') . ';charset=utf8mb4', 'user' => 'root', 'pass' => ''];
$pdo = WartungDateien::pdo($admin);
$name = 'zeit_' . substr(hash('sha256', $wurzel), 0, 12);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
$stmt->execute([$name]);
if ((int)$stmt->fetchColumn() !== 0) { throw new RuntimeException('Vorhandene Datenbank ohne zugehörige Konfiguration. Installation wird nicht überschrieben.'); }
$pass = bin2hex(random_bytes(24));
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("CREATE USER '$name'@'localhost' IDENTIFIED BY " . $pdo->quote($pass));
$pdo->exec("GRANT ALL ON `$name`.* TO '$name'@'localhost' WITH GRANT OPTION");
$pdo->exec("GRANT CREATE USER ON *.* TO '$name'@'localhost'");
$admin['dsn'] .= ';dbname=' . $name;
WartungDateien::mysql($admin, static function ($option, $db) use ($wurzel): void {
    WartungDateien::prozess(['mariadb', $option, $db], null, $wurzel . '/sql/01_initial_schema.sql');
});
$konfig = ['app' => ['installation_typ' => 'backend', 'name' => 'Zeiterfassung', 'base_url' => ''], 'timezone' => 'Europe/Berlin',
    'db' => ['host' => 'localhost', 'dbname' => $name, 'user' => $name, 'pass' => $pass, 'charset' => 'utf8mb4'], 'offline_db' => ['enabled' => false]];
$pfad = $wurzel . '/config/config.local.php';
if (file_put_contents($pfad, "<?php\nreturn " . var_export($konfig, true) . ";\n", LOCK_EX) === false) { throw new RuntimeException('Backendkonfiguration konnte nicht gespeichert werden.'); }
chmod($pfad, 0640);
if (!chgrp($pfad, $argv[1] ?? 'www-data')) { throw new RuntimeException('Webgruppe für die Backendkonfiguration konnte nicht gesetzt werden.'); }
echo "Backenddatenbank und lokale Konfiguration angelegt.\n";
