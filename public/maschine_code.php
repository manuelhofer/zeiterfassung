<?php
declare(strict_types=1);

// Barcode-Generator für Maschinen-IDs.
// Hinweis: bewusst als eigenes Endpoint (ohne Router), damit ein <img>-Tag es direkt laden kann.

require __DIR__ . '/../core/Autoloader.php';

Start::los();

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

$codeService = new MaschineQrCodeService();
$name = trim((string)($_GET['name'] ?? ''));
$barcodeDaten = $id . '_' . $name;

// Erst erzeugen, dann Bild-Header senden: Sonst steht bei einem Fehlschlag
// bereits `Content-Type: image/png` fest und die Fehlermeldung kaeme als
// kaputtes Bild an.
ob_start();
$codeService->gebeBarcodePngAus($barcodeDaten);
$ausgabe = ob_get_clean();

if ($ausgabe === '' || $ausgabe === false) {
    // Früher wurde hier ersatzweise ein QR-Code ausgegeben. Das war schlechter
    // als eine Fehlermeldung: In der Halle sind 1D-Handscanner im Einsatz, für
    // die ein QR-Code kein schlechterer Code ist, sondern gar keiner (siehe
    // Kopf von services/BarcodeService.php). Das Etikett sah brauchbar aus und
    // fiel erst an der Maschine auf.
    Logger::error('Barcode-Ausgabe fuer Maschine fehlgeschlagen', [
        'id'   => $id,
        'name' => $name,
    ], $id, null, 'maschine');

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Der Barcode konnte nicht erzeugt werden. Bitte im Systemlog nachsehen.';
    exit;
}

// Cache defensiv aus (damit der Download immer frisch ist).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: image/png');
header('Content-Disposition: inline; filename="maschine-' . $id . '-barcode.png"');

echo $ausgabe;
