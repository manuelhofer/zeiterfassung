<?php
declare(strict_types=1);

$bodyKlasse = 'terminal-login';
require __DIR__ . '/_layout_top.php';
?>

<div class="button-row" style="margin-top:0.25rem; margin-bottom:0.75rem;">
    <a href="terminal.php?aktion=start" class="button-link secondary">Zurück</a>
</div>

<?php require __DIR__ . '/_statusbox.php'; ?>

<div class="status-box <?php echo ($qsFehler > 0) ? 'error' : 'warn'; ?>" style="margin-top:1rem;">
    <div class="status-title">
        <span>Offline-Queue: <?php echo ($qsFehler > 0) ? 'Fehler' : (($qsOffen > 0) ? 'Offen' : 'Leer'); ?></span>
    </div>
    <div class="status-small">
        Offen: <strong><?php echo (int)$qsOffen; ?></strong>
        · Fehler: <strong><?php echo (int)$qsFehler; ?></strong>
        · Speicherort: <strong><?php echo htmlspecialchars($qsSpeicherort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
    </div>
    <div class="status-small mt-025">
        <?php if ($qsHauptdb === true): ?>
            Hauptdatenbank ist online. Offene Queue-Einträge werden automatisch nachgezogen.
        <?php elseif ($qsHauptdb === false): ?>
            Hauptdatenbank ist offline. Buchungen bleiben lokal in der Queue, bis die Verbindung wieder verfügbar ist.
        <?php else: ?>
            Hauptdatenbank-Status aktuell unbekannt.
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
