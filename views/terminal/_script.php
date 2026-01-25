<?php
declare(strict_types=1);

/**
 * Helper-Partial für das Terminal: JS einbinden inkl. Cache-Busting via filemtime().
 *
 * Erwartete Variablen (vom Caller gesetzt):
 * - $scriptRelPfad (string) z. B. 'js/terminal-autologout.js'
 * - $scriptAttribute (array) optionale zusätzliche Attribute, z. B. data-*
 */

if (!isset($scriptRelPfad) || !is_string($scriptRelPfad) || trim($scriptRelPfad) === '') {
    return;
}

$scriptRelPfad = ltrim($scriptRelPfad, '/');

// Cache-Busting über filemtime() (Kiosk/Browser-Caches sind sonst oft aggressiv).
$rootDir = dirname(__DIR__, 2);
$jsAbs = $rootDir . '/public/' . $scriptRelPfad;
$jsVer = is_file($jsAbs) ? (string)filemtime($jsAbs) : '';
$jsSrc = $scriptRelPfad . ($jsVer !== '' ? ('?v=' . rawurlencode($jsVer)) : '');

$scriptAttribute = (isset($scriptAttribute) && is_array($scriptAttribute)) ? $scriptAttribute : [];
?>

<script
    src="<?php echo htmlspecialchars($jsSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
<?php
foreach ($scriptAttribute as $name => $value) {
    if (!is_string($name) || $name === '' || $name === 'src') {
        continue;
    }
    // Sehr simple Whitelist: nur typische HTML-Attribut-Namen zulassen.
    if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $name)) {
        continue;
    }

    // Boolean-Attribute (z. B. defer/async) – nur bei true ausgeben.
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
></script>
