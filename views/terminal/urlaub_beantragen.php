<?php
declare(strict_types=1);

/**
 * Legacy-Datei ohne eigenes Markup.
 *
 * Maßgeblich für Terminal-Urlaub ist `views/terminal/start.php`
 * (Modus-basiert: Übersicht/Formular via `?modus=antrag`).
 *
 * Dieser Fallback verhindert zukünftige Fehländerungen an doppeltem Markup,
 * falls diese Datei noch irgendwo direkt eingebunden wird.
 */
require __DIR__ . '/start.php';
