<?php
declare(strict_types=1);

/**
 * Gemeinsames Terminal-Layout (Footer).
 *
 * Siehe auch: `_layout_top.php`.
 */
?>
</main>

<?php
// Auto-Logout zentral über das Layout laden (T-047).
// Seit T-048 werden Terminal-Views nicht mehr direkt mit `_autologout.php` verdrahtet.
// Der Include-Guard in `_autologout.php` bleibt als defensive Absicherung, falls zukünftig
// wieder irgendwo ein Direkt-Include auftaucht.
require __DIR__ . '/_autologout.php';
?>
</body>
</html>
