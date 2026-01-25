<?php
declare(strict_types=1);

/**
 * Helper-Partial für das Terminal: CSS einbinden inkl. Cache-Busting via filemtime().
 *
 * Erwartete Variablen (vom Caller gesetzt):
 * - $cssRelPfad (string) z. B. 'css/terminal.css'
 * - $cssAttribute (array) optionale zusätzliche Attribute, z. B. media, integrity, crossorigin
 */

if (!isset($cssRelPfad) || !is_string($cssRelPfad) || trim($cssRelPfad) === '') {
    return;
}

$cssRelPfad = ltrim($cssRelPfad, '/');

// Cache-Busting über filemtime() (Kiosk/Browser-Caches sind sonst oft aggressiv).
$rootDir = dirname(__DIR__, 2);
$cssAbs = $rootDir . '/public/' . $cssRelPfad;
$cssVer = is_file($cssAbs) ? (string)filemtime($cssAbs) : '';
$cssHref = $cssRelPfad . ($cssVer !== '' ? ('?v=' . rawurlencode($cssVer)) : '');

$cssAttribute = (isset($cssAttribute) && is_array($cssAttribute)) ? $cssAttribute : [];
?>

<link
    rel="stylesheet"
    href="<?php echo htmlspecialchars($cssHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
<?php
foreach ($cssAttribute as $name => $value) {
    if (!is_string($name) || $name === '' || $name === 'href' || $name === 'rel') {
        continue;
    }
    // Sehr simple Whitelist: nur typische HTML-Attribut-Namen zulassen.
    if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $name)) {
        continue;
    }

    // Boolean-Attribute – nur bei true ausgeben.
    if (is_bool($value)) {
        if ($value === true) {
            echo '    ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
        }
        continue;
    }

    if ($value === null) {
        continue;
    }

    if (is_scalar($value)) {
        echo '    ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '="' .
            htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"\n";
    }
}
?>
>
