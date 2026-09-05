<?php
declare(strict_types=1);

/**
 * Die einzige Datei, die Sie anfassen müssen.
 *
 * Tragen Sie Ihren Datenbankzugang ein. Wenn Ihre Website schon eine
 * PDO-Verbindung hat, ist der bessere Weg: diese Datei wegwerfen und in
 * `portal-api.php` und `mitarbeiter.php` Ihre vorhandene Verbindung
 * einsetzen – die beiden brauchen nur `datenbank()`.
 *
 * DIESE DATEI GEHÖRT NICHT INS NETZ. Sie liegt zwar hinter PHP und gibt von
 * sich aus nichts aus, aber falls PHP für das Verzeichnis einmal ausfällt,
 * läge das Passwort im Klartext im Browser. Zwei Zeilen in der `.htaccess`
 * daneben verhindern das:
 *
 *     <Files "portal-konfig.php">
 *         Require all denied
 *     </Files>
 */

return (static function (): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host     = 'localhost';
    $name     = 'IHRE_DATENBANK';
    $benutzer = 'IHR_BENUTZER';
    $passwort = 'IHR_PASSWORT';

    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $benutzer,
        $passwort,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    return $pdo;
})();
