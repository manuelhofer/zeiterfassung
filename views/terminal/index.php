<?php
declare(strict_types=1);

/**
 * Legacy/Platzhalter-View (nicht aktiv geroutet).
 *
 * Hinweis:
 * - Das echte Terminal läuft über `public/terminal.php`.
 * - Dieser View existiert nur noch, um versehentliche Direktaufrufe
 *   (z. B. durch falsche Links/Bookmarks) abzufangen.
 */

$seitenTitel = 'Terminal – Hinweis';
$seitenUeberschrift = 'Terminal';
$bodyKlasse = 'terminal-center';
require __DIR__ . '/_layout_top.php';
?>

<p class="hinweis">
    Diese Seite ist ein veralteter Platzhalter und wird vom Terminal nicht genutzt.
</p>

<div class="button-row">
    <a href="../../public/terminal.php?aktion=start" class="button-link">Zum Terminal-Start</a>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
