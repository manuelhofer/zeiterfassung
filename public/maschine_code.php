<?php
declare(strict_types=1);

// Barcode/QR-Generator (aktuell: Code 39) fuer Maschinen-IDs.
// Hinweis: bewusst als eigenes Endpoint (ohne Router), damit ein <img>-Tag es direkt laden kann.

require __DIR__ . '/../core/Autoloader.php';

$konfig = require __DIR__ . '/../config/config.php';

// Zeitzone setzen
if (isset($konfig['timezone']) && is_string($konfig['timezone']) && $konfig['timezone'] !== '') {
    date_default_timezone_set($konfig['timezone']);
} else {
    date_default_timezone_set('Europe/Berlin');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var AuthService $auth */
$auth = AuthService::getInstanz();

function maschinenHatZugriff(AuthService $auth): bool
{
    if ($auth->hatRecht('MASCHINEN_VERWALTEN')) {
        return true;
    }

    // Legacy-Fallback
    if ($auth->hatRolle('Chef') || $auth->hatRolle('Personalbüro') || $auth->hatRolle('Personalbuero')) {
        return true;
    }

    return false;
}

if (!$auth->istAngemeldet()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nicht angemeldet.';
    exit;
}

if (!maschinenHatZugriff($auth)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Keine Berechtigung.';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Ungueltige Maschinen-ID.';
    exit;
}

// Aktuell ist der Scan-Code einfach die Maschinen-ID als Ziffernfolge.
$code = (string)$id;

$format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'svg';
if (!in_array($format, ['svg', 'png'], true)) {
    $format = 'svg';
}

// Code-39 Mapping (B/S/B/S/B/S/B/S/B) als n/w-Sequenz.
// Quelle: ISO/IEC 16388 / Uebliche Referenz-Tabellen.
$code39 = [
    '0' => 'nnnwwnwnn',
    '1' => 'wnnwnnnnw',
    '2' => 'nnwwnnnnw',
    '3' => 'wnwwnnnnn',
    '4' => 'nnnwwnnnw',
    '5' => 'wnnwwnnnn',
    '6' => 'nnwwwnnnn',
    '7' => 'nnnwnnwnw',
    '8' => 'wnnwnnwnn',
    '9' => 'nnwwnnwnn',
    'A' => 'wnnnnwnnw',
    'B' => 'nnwnnwnnw',
    'C' => 'wnwnnwnnn',
    'D' => 'nnnnwwnnw',
    'E' => 'wnnnwwnnn',
    'F' => 'nnwnwwnnn',
    'G' => 'nnnnnwwnw',
    'H' => 'wnnnnwwnn',
    'I' => 'nnwnnwwnn',
    'J' => 'nnnnwwwnn',
    'K' => 'wnnnnnnww',
    'L' => 'nnwnnnnww',
    'M' => 'wnwnnnnwn',
    'N' => 'nnnnwnnww',
    'O' => 'wnnnwnnwn',
    'P' => 'nnwnwnnwn',
    'Q' => 'nnnnnnwww',
    'R' => 'wnnnnnwwn',
    'S' => 'nnwnnnwwn',
    'T' => 'nnnnwnwwn',
    'U' => 'wwnnnnnnw',
    'V' => 'nwwnnnnnw',
    'W' => 'wwwnnnnnn',
    'X' => 'nwnnwnnnw',
    'Y' => 'wwnnwnnnn',
    'Z' => 'nwwnwnnnn',
    '-' => 'nwnnnnwnw',
    '.' => 'wwnnnnwnn',
    ' ' => 'nwwnnnwnn',
    '*' => 'nwnnwnwnn',
    '$' => 'nwnwnwnnn',
    '/' => 'nwnwnnnwn',
    '+' => 'nwnnnwnwn',
    '%' => 'nnnwnwnwn',
];

// Daten auf erlaubte Zeichen reduzieren (hier: nur Ziffern; trotzdem defensiv).
$daten = strtoupper($code);
$daten = preg_replace('/[^0-9A-Z \-\.\$\/\+\%]/', '', $daten);
if ($daten === null || $daten === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Kein gueltiger Code.';
    exit;
}

$voll = '*' . $daten . '*';

$narrow = 2;
$wide   = 5;
$quiet  = 10;
$barHeight = 80;
$paddingTop = 10;
$textHeight = 18;
$paddingBottom = 8;

function code39Breite(string $voll, array $map, int $n, int $w, int $quiet): int
{
    $sum = $quiet * 2;
    $len = strlen($voll);

    for ($i = 0; $i < $len; $i++) {
        $ch = $voll[$i];
        if (!isset($map[$ch])) {
            continue;
        }
        $pattern = $map[$ch];
        for ($j = 0; $j < 9; $j++) {
            $sum += ($pattern[$j] === 'w') ? $w : $n;
        }
        // Inter-Character Gap (narrow) – ausser nach dem letzten Zeichen.
        if ($i < $len - 1) {
            $sum += $n;
        }
    }

    return $sum;
}

$breite = code39Breite($voll, $code39, $narrow, $wide, $quiet);
$hoehe = $paddingTop + $barHeight + $textHeight + $paddingBottom;

// Cache defensiv aus (damit der Download immer frisch ist).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($format === 'png') {
    // PNG-Ausgabe via GD (optional). Falls GD fehlt, liefern wir eine klare Fehlermeldung.
    if (!function_exists('imagecreatetruecolor')) {
        http_response_code(501);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'PNG-Ausgabe nicht verfuegbar (PHP-GD fehlt). Bitte SVG verwenden oder php-gd installieren.';
        exit;
    }

    $img = imagecreatetruecolor($breite, $hoehe);
    if ($img === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Bild konnte nicht erzeugt werden.';
        exit;
    }

    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    if ($white === false || $black === false) {
        imagedestroy($img);
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Farben konnten nicht erzeugt werden.';
        exit;
    }

    imagefill($img, 0, 0, $white);

    $x = $quiet;
    $len = strlen($voll);

    for ($i = 0; $i < $len; $i++) {
        $ch = $voll[$i];
        if (!isset($code39[$ch])) {
            continue;
        }

        $pattern = $code39[$ch];
        for ($j = 0; $j < 9; $j++) {
            $wpx = ($pattern[$j] === 'w') ? $wide : $narrow;
            $isBar = ($j % 2 === 0);

            if ($isBar) {
                imagefilledrectangle(
                    $img,
                    $x,
                    $paddingTop,
                    $x + $wpx - 1,
                    $paddingTop + $barHeight,
                    $black
                );
            }
            $x += $wpx;
        }

        if ($i < $len - 1) {
            $x += $narrow;
        }
    }

    // Human-readable Text
    $textY = $paddingTop + $barHeight + 2;
    imagestring($img, 3, (int)max(0, ($breite - (strlen($daten) * 6)) / 2), $textY, $daten, $black);

    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="maschine-' . $id . '.png"');

    imagepng($img);
    imagedestroy($img);
    exit;
}

// Default: SVG (keine PHP-Extensions noetig, gut druck-/skalierbar).
header('Content-Type: image/svg+xml; charset=UTF-8');
header('Content-Disposition: inline; filename="maschine-' . $id . '.svg"');

$rects = [];
$x = $quiet;
$len = strlen($voll);

for ($i = 0; $i < $len; $i++) {
    $ch = $voll[$i];
    if (!isset($code39[$ch])) {
        continue;
    }

    $pattern = $code39[$ch];
    for ($j = 0; $j < 9; $j++) {
        $wpx = ($pattern[$j] === 'w') ? $wide : $narrow;
        $isBar = ($j % 2 === 0);

        if ($isBar) {
            $rects[] = '<rect x="' . $x . '" y="' . $paddingTop . '" width="' . $wpx . '" height="' . $barHeight . '" fill="#000" />';
        }
        $x += $wpx;
    }

    if ($i < $len - 1) {
        $x += $narrow;
    }
}

$textY = $paddingTop + $barHeight + $textHeight;

$svg = [];
$svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
$svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $breite . '" height="' . $hoehe . '" viewBox="0 0 ' . $breite . ' ' . $hoehe . '">';
$svg[] = '<rect x="0" y="0" width="' . $breite . '" height="' . $hoehe . '" fill="#fff" />';
$svg[] = implode('', $rects);
$svg[] = '<text x="' . (int)($breite / 2) . '" y="' . $textY . '" text-anchor="middle" font-family="monospace" font-size="14" fill="#000">' . htmlspecialchars($daten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</text>';
$svg[] = '</svg>';

echo implode("\n", $svg);
