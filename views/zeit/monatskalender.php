<?php
declare(strict_types=1);
/**
 * Template: Einfacher Monatskalender (Platzhalter).
 *
 * Erwartet u. a.:
 * - $jahr (int)
 * - $monat (int)
 * - $tageswerte (array<int,array<string,mixed>>)
 */
require __DIR__ . '/../layout/header.php';

$jahr       = $jahr ?? (int)date('Y');
$monat      = $monat ?? (int)date('n');
$tageswerte = $tageswerte ?? [];
?>

<section>
    <h2>Kalenderansicht <?php echo $jahr; ?> / <?php echo sprintf('%02d', $monat); ?></h2>
    <p>Diese Ansicht ist aktuell ein Platzhalter und wird später mit einem echten Kalenderlayout gefüllt.</p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
