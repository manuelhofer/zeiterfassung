<?php
declare(strict_types=1);

/**
 * Terminal Partial: Logout-Formular (POST + CSRF)
 *
 * Erwartet:
 * - $csrfToken (string)
 *
 * Optional (vor require setzen):
 * - $logoutFormHidden (bool)        → wenn true: Form ist unsichtbar (für Auto-Logout) und enthält keinen Button
 * - $logoutFormShowNoscript (bool)  → wenn true: <noscript>-Fallback (Button + Abbrechen-Link)
 *   (Alias: $logoutFormIncludeNoscript wird ebenfalls akzeptiert)
 */

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

// Defensive Fallback:
// Wenn eine View vergisst, $csrfToken zu setzen, wird Logout sonst unmöglich.
// Wir versuchen daher zuerst, den Token aus der Session zu lesen.
// Wenn es keinen gibt, erzeugen wir ihn analog zur TerminalController-Logik.
if ($csrfToken === '' || strlen($csrfToken) < 20) {
    $csrfToken = Csrf::token(TerminalController::CSRF_BEREICH);
}

$logoutFormHidden = isset($logoutFormHidden) ? (bool)$logoutFormHidden : false;

// Kompatibilität: älterer Variablenname aus frühen Patches.
if (!isset($logoutFormShowNoscript) && isset($logoutFormIncludeNoscript)) {
    $logoutFormShowNoscript = $logoutFormIncludeNoscript;
}
$logoutFormShowNoscript = isset($logoutFormShowNoscript) ? (bool)$logoutFormShowNoscript : false;

$formKlasse = $logoutFormHidden ? 'is-hidden' : '';
$abbrechenUrl = 'terminal.php?aktion=start';

?>

<form method="post" action="terminal.php?aktion=logout" id="logout-form"<?php echo $formKlasse !== '' ? ' class="' . htmlspecialchars($formKlasse, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : ''; ?>>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

    <?php if ($logoutFormShowNoscript): ?>
        <noscript>
            <div class="button-row">
                <button type="submit">Jetzt abmelden</button>
                <a href="<?php echo htmlspecialchars($abbrechenUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="button-link secondary">Abbrechen</a>
            </div>
        </noscript>
    <?php endif; ?>

    <?php if (!$logoutFormHidden && !$logoutFormShowNoscript): ?>
        <div class="button-row">
            <button type="submit" class="button-danger">Abmelden</button>
        </div>
    <?php endif; ?>
</form>
