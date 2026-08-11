<?php
declare(strict_types=1);
/**
 * Template: Arbeitsschritt-Katalog – fehlendes Recht
 *
 * Erwartet nichts; die Seite ist der Hinweis selbst.
 */
require __DIR__ . '/../layout/header.php';
?>
<section>
    <h2>Keine Berechtigung</h2>
    <p>Zum Pflegen des Arbeitsschritt-Katalogs wird das Recht <code>AUFTRAEGE_VERWALTEN</code> benoetigt.</p>
    <p><a class="button-link quiet" href="?seite=arbeitsschritt_katalog">&laquo; Zurueck zum Katalog</a></p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
