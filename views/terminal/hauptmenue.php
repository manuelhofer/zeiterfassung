<?php
declare(strict_types=1);

/**
 * Legacy/Platzhalter-View (nicht aktiv geroutet).
 *
 * Historisch gab es die Idee, nach der Anmeldung ein eigenes "Hauptmenü" zu zeigen.
 * Inzwischen läuft die Terminal-UI zentral über `views/terminal/start.php`.
 */

$seitenTitel = 'Terminal – Hinweis';
$seitenUeberschrift = 'Terminal';
$bodyKlasse = 'terminal-center';
require __DIR__ . '/_layout_top.php';
?>

<p class="hinweis">
    Diese Seite wird aktuell nicht verwendet. Bitte das Terminal über die Startseite nutzen.
</p>

<div class="button-row">
    <a href="../../public/terminal.php?aktion=start" class="button-link">Zum Terminal-Start</a>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
