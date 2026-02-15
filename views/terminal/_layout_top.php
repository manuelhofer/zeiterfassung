<?php
declare(strict_types=1);

/**
 * Gemeinsames Terminal-Layout (Kopfbereich).
 *
 * Ziel (T-036):
 * - Duplikate in Terminal-Views reduzieren (Head/Body/Wrapper).
 * - Cache-Busting für `public/css/terminal.css`, damit Kiosk-Browser Updates zuverlässig laden.
 *
 * Verwendung in einer View:
 *   $seitenTitel = '...';            // optional, <title>
 *   $seitenUeberschrift = '...';     // optional, <h1>
 *   $bodyKlasse = 'terminal-wide';   // optional, Body-Klasse(n) für Layout-Varianten
 *   require __DIR__ . '/_layout_top.php';
 *
 * Am Ende der View:
 *   require __DIR__ . '/_layout_bottom.php';
 */

$seitenTitel = (isset($seitenTitel) && is_string($seitenTitel) && trim($seitenTitel) !== '')
    ? trim($seitenTitel)
    : 'Terminal – Zeiterfassung';

$seitenUeberschrift = (isset($seitenUeberschrift) && is_string($seitenUeberschrift) && trim($seitenUeberschrift) !== '')
    ? trim($seitenUeberschrift)
    : $seitenTitel;

// CSS-Hauptdatei fürs Terminal (Cache-Busting wird in `_style.php` zentral gemacht).
$cssRelPfad = 'css/terminal.css';

// Optional: Body-Klasse(n) für Layout-Varianten (z. B. 'terminal-wide', 'terminal-center').
$bodyKlasse = (isset($bodyKlasse) && is_string($bodyKlasse) && trim($bodyKlasse) !== '')
    ? trim($bodyKlasse)
    : '';

$bodyAttr = '';
if ($bodyKlasse !== '') {
    $bodyAttr = ' class="' . htmlspecialchars($bodyKlasse, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
}

// ------------------------------------------------------------
// Topbar-Status (kleines visuelles Signal für Offline/Störung)
// ------------------------------------------------------------
$qs = $_SESSION['terminal_queue_status'] ?? null;

$topbarPillText = 'ONLINE';
$topbarPillClass = 'ok';
$topbarPillLink = 'terminal.php?aktion=offline_info';

if (is_array($qs)) {
    $hauptdb = $qs['hauptdb_verfuegbar'] ?? null;
    $queue = $qs['queue_verfuegbar'] ?? ($qs['offline_queue_verfuegbar'] ?? null);

    if ($hauptdb === false && $queue === true) {
        $topbarPillText = 'OFFLINE';
        $topbarPillClass = 'warn';
    } elseif ($hauptdb === false && $queue === false) {
        $topbarPillText = 'STÖRUNG';
        $topbarPillClass = 'error';
    }
}

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($seitenTitel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php require __DIR__ . '/_style.php'; ?>
</head>
<body<?php echo $bodyAttr; ?>>
<main>
    <div class="terminal-topbar">
        <?php if (is_string($topbarPillLink) && $topbarPillLink !== ''): ?>
            <a href="<?php echo htmlspecialchars($topbarPillLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="terminal-pill terminal-pill-link <?php echo htmlspecialchars($topbarPillClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($topbarPillText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </a>
        <?php else: ?>
            <div class="terminal-pill <?php echo htmlspecialchars($topbarPillClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($topbarPillText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <div class="terminal-uhr" id="terminal-uhr">00:00:00 01-01-1970</div>
    </div>
    <h1><?php echo htmlspecialchars($seitenUeberschrift, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
