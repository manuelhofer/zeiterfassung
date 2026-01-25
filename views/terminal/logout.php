<?php
declare(strict_types=1);

/** @var array|null  $mitarbeiter */
/** @var string|null $csrfToken */
/** @var int|null    $terminalTimeoutSekunden */

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$terminalTimeoutSekunden = isset($terminalTimeoutSekunden) ? (int)$terminalTimeoutSekunden : 60;
if ($terminalTimeoutSekunden < 10) {
    $terminalTimeoutSekunden = 60;
}

$anzeigename = '';
if (is_array($mitarbeiter) && isset($mitarbeiter['vorname'], $mitarbeiter['nachname'])) {
    $anzeigename = trim((string)$mitarbeiter['vorname'] . ' ' . (string)$mitarbeiter['nachname']);
}

$seitenTitel = 'Abmelden – Terminal';
$seitenUeberschrift = 'Abmelden';
$bodyKlasse = 'terminal-center';
require __DIR__ . '/_layout_top.php';
?>

    <?php if ($anzeigename !== ''): ?>
        <?php
            $mitarbeiterId = 0;
            if (is_array($mitarbeiter) && isset($mitarbeiter['id'])) {
                $mitarbeiterId = (int)$mitarbeiter['id'];
            }
        ?>

        <details class="status-box terminal-mitarbeiterpanel">
            <summary class="status-title">
                <a href="terminal.php?aktion=start&amp;view=arbeitszeit" style="display:flex; justify-content:space-between; width:100%; color:inherit; text-decoration:none;">
                    <span>
                        Mitarbeiter:
                        <strong><?php echo htmlspecialchars($anzeigename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        (ID: <?php echo (int)$mitarbeiterId; ?>)
                    </span>
                    <span class="status-small">Arbeitszeit</span>
                </a>
            </summary>
        </details>
    <?php endif; ?>

    <p class="hinweis">Sie werden jetzt abgemeldet…</p>

    <?php
    // Logout-Formular als Partial (T-045) + Noscript-Fallback
    $logoutFormHidden = false;
    $logoutFormShowNoscript = true;
    require __DIR__ . '/_logout_form.php';
    ?>

    <?php
    // Auto-Submit ohne Inline-JS (T-042) – externe Datei.
    // Cache-Busting wird zentral in `_script.php` erledigt.
    $scriptRelPfad = 'js/terminal-logout.js';
    $scriptAttribute = [
        'data-form-id' => 'logout-form',
        'data-delay-ms' => 50,
    ];
    require __DIR__ . '/_script.php';
    ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
