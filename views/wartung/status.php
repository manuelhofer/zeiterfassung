<?php
declare(strict_types=1);
$h = static fn($wert): string => htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$schritt = (int)($status['schritt'] ?? 1);
$fertig = ($status['zustand'] ?? '') === 'erfolgreich';
$fehler = in_array($status['zustand'] ?? '', ['fehlgeschlagen', 'unterbrochen'], true);
?>
<section class="wartung-karte" aria-live="polite">
    <h2><?= $h(WartungAnzeige::titel($status)) ?></h2>
    <p><?= $h(WartungAnzeige::hinweis($status)) ?></p>
    <?php if (($status['aktion'] ?? '') === 'update'): ?>
        <ol class="wartung-schritte" aria-label="Fortschritt">
        <?php foreach ([1 => 'Prüfen', 'Sichern', 'Backend aktualisieren', 'Terminals aktualisieren', 'Abschließen'] as $nummer => $text): ?>
            <li class="<?= $fertig || $nummer < $schritt ? 'erledigt' : ($nummer === $schritt ? 'aktuell' : '') ?>" <?= !$fertig && $nummer === $schritt ? 'aria-current="step"' : '' ?>><?= $fertig || $nummer < $schritt ? '✓ ' : $nummer . '. ' ?><?= $h($text) ?></li>
        <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if (!empty($status['backup'])): ?><p>Eine Sicherung ist vorhanden und bleibt auf dem Server gespeichert.</p><?php endif; ?>
    <details><summary>Technische Details<?= $fehler ? ' zum Fehler' : '' ?></summary>
        <?php if (!empty($status['backup'])): ?><p>Sicherungsordner: <code><?= $h($status['backup']) ?></code></p><?php endif; ?>
        <ol><?php foreach ($status['protokoll'] ?? [] as $zeile): ?>
            <li><?= $h($zeile['zeit']) ?> – <?= $h($zeile['text']) ?></li>
        <?php endforeach; ?></ol>
    </details>
</section>
