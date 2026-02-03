<?php
declare(strict_types=1);

// Barcode-Generator fuer Maschinen-IDs.
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

if (!function_exists('imagecreatetruecolor') && !extension_loaded('imagick')) {
    http_response_code(501);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Barcode-Ausgabe nicht verfuegbar (PHP-GD fehlt). Bitte php-gd installieren.';
    exit;
}

$qrService = new MaschineQrCodeService();
$name = trim((string)($_GET['name'] ?? ''));
$barcodeDaten = $id . '_' . $name;

// Cache defensiv aus (damit der Download immer frisch ist).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: image/png');
header('Content-Disposition: inline; filename="maschine-' . $id . '-barcode.png"');

ob_start();
$qrService->gebeBarcodePngAus($barcodeDaten);
$ausgabe = ob_get_clean();

if ($ausgabe === '' || $ausgabe === false) {
    if (class_exists('Logger')) {
        Logger::error('Barcode-Ausgabe fehlgeschlagen, Fallback auf QR', [
            'id' => $id,
            'name' => $name,
        ], $id, null, 'maschine');
    }
    $qrService->gebeQrPngAus((string)$id);
    exit;
}

echo $ausgabe;
