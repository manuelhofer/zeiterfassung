<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
$h = static fn($wert): string => htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section>
    <h1>Backup und Updates</h1>
    <p>Updatequelle: <strong>main</strong>. Das Backend verteilt denselben Programmstand an alle aktiven Terminals.</p>
    <?php if ($flash !== ''): ?><p role="status"><?= $h($flash) ?></p><?php endif; ?>
    <?php if ($version === null): ?>
        <p>Der Wartungsdienst muss einmal auf dem Backend und den Terminals eingerichtet werden. Die Anleitung steht in <code>docs/wartung_betrieb.md</code>.</p>
    <?php else: ?>
        <p>Installiert: <code><?= $h($version['commit']) ?></code></p>
    <?php endif; ?>
    <?php if ($wartet): ?><p role="status">Auftrag wartet auf den Wartungsdienst. Diese Seite aktualisiert sich automatisch. Bleibt der Auftrag liegen, bitte den Dienst prüfen.</p><?php endif; ?>
    <?php if ($darfUpdate): ?>
        <form method="post" action="?seite=wartung">
            <?= Csrf::feld('wartung') ?><input type="hidden" name="aktion" value="pruefen">
            <button type="submit" <?= $bereit ? '' : 'disabled' ?>>1. Nach Updates suchen</button>
        </form>
    <?php endif; ?>
    <?php if ($angebot !== null): ?>
        <h2>Prüfergebnis</h2>
        <p><?= ($angebot['verfuegbar'] ?? false) ? 'Update verfügbar:' : 'Stand bei der letzten Prüfung:' ?> <code><?= $h($angebot['commit']) ?></code></p>
        <?php if (empty($angebot['migrationen'])): ?><p>Keine neuen Datenbankmigrationen im geprüften Paket.</p>
        <?php else: ?>
            <p>Diese Datenbankänderungen werden nach erfolgreicher Sicherung ausgeführt:</p>
            <ul><?php foreach ($angebot['migrationen'] as $migration): ?>
                <li><?= $h($migration['datei']) ?> — <?= $migration['ziel'] === 'haupt' ? 'Hauptdatenbank, einmal zentral' : 'Lokale Offline-Datenbank je Installation' ?></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($darfUpdate && ($angebot['verfuegbar'] ?? false) && ($version['commit'] ?? '') === ($angebot['installiert'] ?? '')): ?>
            <form method="post" action="?seite=wartung">
                <?= Csrf::feld('wartung') ?><input type="hidden" name="aktion" value="update">
                <input type="hidden" name="commit" value="<?= $h($angebot['commit']) ?>">
                <p>Während Backup und Update sind Buchungen angehalten. Alle aktiven Terminals müssen erreichbar sein.</p>
                <button type="submit" <?= $bereit ? '' : 'disabled' ?>>2. Sichern und Update durchführen</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($darfBackup): ?>
        <h2>Unabhängige Sicherung</h2>
        <p>Sichert die Anwendung, Konfiguration, Uploads und Datenbanken des Backends sowie Dateien und Offline-Daten der Terminals in einem separaten Ordner.</p>
        <form method="post" action="?seite=wartung">
            <?= Csrf::feld('wartung') ?><input type="hidden" name="aktion" value="backup">
            <button type="submit" <?= $bereit ? '' : 'disabled' ?>>Backup erstellen</button>
        </form>
    <?php endif; ?>
    <?php if ($status !== null): ?>
        <h2>Letzter Auftrag: <?= $h($status['zustand'] ?? '') ?></h2>
        <?php if (isset($status['backup'])): ?><p>Sicherung: <code><?= $h($status['backup']) ?></code></p><?php endif; ?>
        <ol><?php foreach ($status['protokoll'] ?? [] as $zeile): ?>
            <li><time><?= $h($zeile['zeit']) ?></time> — <?= $h($zeile['text']) ?></li>
        <?php endforeach; ?></ol>
    <?php endif; ?>
    <p><a href="?seite=wartung">Status aktualisieren</a></p>
</section>
<?php if ($belegt): ?><script>window.setTimeout(function () { window.location.reload(); }, 5000);</script><?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
