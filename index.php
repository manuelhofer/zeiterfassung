<?php
declare(strict_types=1);

/**
 * Einstieg ueber die Projektwurzel.
 *
 * Der eigentliche Web-Einstieg liegt in `public/index.php`. Wenn die
 * Projektwurzel direkt aufgerufen wird (z. B. in einer XAMPP-Installation),
 * leiten wir auf den echten Front-Controller weiter und behalten vorhandene
 * Query-Parameter bei.
 */

if (PHP_SAPI === 'cli') {
    echo 'Web entry point: public/index.php' . PHP_EOL;
    exit(0);
}

$ziel = 'public/index.php';
$queryString = $_SERVER['QUERY_STRING'] ?? '';

if (is_string($queryString) && $queryString !== '') {
    $ziel .= '?' . $queryString;
}

header('Location: ' . $ziel, true, 302);
exit;
