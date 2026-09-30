<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
$h = static fn($wert): string => htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="css/wartung.css">
<main class="wartung">
    <h1>Backup und Updates</h1>
    <p>Sichere deine Daten oder aktualisiere das Backend und alle gekoppelten Terminals gemeinsam.</p>
    <?php if ($flash !== ''): ?><p class="wartung-hinweis" role="status"><?= $h($flash) ?></p><?php endif; ?>
    <?php if (!$bereitschaft['bereit'] && !$belegt): ?><p class="wartung-hinweis" role="status"><?= $h($bereitschaft['text']) ?></p><?php endif; ?>
    <?php if ($abgelaufen): ?><p class="wartung-hinweis">Der letzte Auftrag wurde nicht rechtzeitig gestartet und ist abgelaufen. Er wird nicht nachträglich ausgeführt. Sobald der Dienst bereit ist, kannst du es erneut versuchen.</p><?php endif; ?>
    <?php if ($wartet): ?><p role="status">Der Vorgang startet gleich. Du kannst diese Seite schließen; er läuft automatisch weiter.</p><?php endif; ?>
    <?php if ($darfUpdate): ?>
    <section class="wartung-karte">
        <h2>Software aktualisieren</h2>
        <?php if ($angebot !== null && ($angebot['verfuegbar'] ?? false) && ($version['commit'] ?? '') === ($angebot['installiert'] ?? '')): ?>
            <p><strong>Eine neue Version ist verfügbar.</strong></p>
            <p><?= empty($angebot['migrationen']) ? 'Die Datenbankstruktur bleibt unverändert.' : 'Die Datenbank wird ebenfalls aktualisiert. Das geschieht automatisch nach der Sicherung.' ?></p>
            <p>Vorher werden alle Daten gesichert. Buchungen sind währenddessen kurz angehalten.</p>
            <form method="post" action="?seite=wartung">
                <?= Csrf::feld('wartung') ?><input type="hidden" name="aktion" value="update">
                <input type="hidden" name="commit" value="<?= $h($angebot['commit']) ?>">
                <button type="submit" <?= $bereit ? '' : 'disabled' ?>>Jetzt aktualisieren</button>
            </form>
        <?php elseif ($angebot !== null): ?><p>Deine Installation ist auf dem zuletzt geprüften Stand.</p><?php else: ?><p>Prüfe, ob eine neue Version bereitsteht. Dabei wird noch nichts installiert.</p><?php endif; ?>
        <form method="post" action="?seite=wartung">
            <?= Csrf::feld('wartung') ?><input type="hidden" name="aktion" value="pruefen">
            <button type="submit" <?= $bereit ? '' : 'disabled' ?>>Nach Updates suchen</button>
        </form>
        <details><summary>Versionsdetails</summary>
            <p>Quelle: main · Installiert: <code><?= $h($version['commit'] ?? 'wird vorbereitet') ?></code></p>
            <?php if ($angebot !== null): ?><p>Angebot: <code><?= $h($angebot['commit']) ?></code></p><?php endif; ?>
            <ul><?php foreach ($angebot['migrationen'] ?? [] as $migration): ?><li><?= $h($migration['datei']) ?> (<?= $migration['ziel'] === 'haupt' ? 'Hauptdatenbank' : 'Offline-Datenbank' ?>)</li><?php endforeach; ?></ul>
        </details>
    </section>
    <?php endif; ?>
    <?php if ($darfBackup): ?>
    <section class="wartung-karte">
        <h2>Daten sichern</h2>
        <p>Sichert Programmdateien, Einstellungen und Datenbanken einschließlich der offenen Buchungen auf den Terminals.</p>
        <form method="post" action="?seite=wartung">
            <?= Csrf::feld('wartung') ?><input type="hidden" name="aktion" value="backup">
            <button type="submit" <?= $bereit ? '' : 'disabled' ?>>Backup erstellen</button>
        </form>
    </section>
    <?php endif; ?>
    <?php if ($geraete !== []): ?>
    <section class="wartung-karte"><h2>Terminals</h2><ul>
        <?php foreach ($geraete as $geraet): ?><li><?= $h($geraet['name']) ?> – <?= (int)$geraet['bereit'] ? 'bereit' : 'noch nicht erreichbar' ?></li><?php endforeach; ?>
    </ul><p>Gekoppelte Terminals werden automatisch berücksichtigt.</p></section>
    <?php endif; ?>
    <?php if ($status !== null): require __DIR__ . '/status.php'; endif; ?>
    <p><a href="?seite=wartung">Status aktualisieren</a></p>
</main>
<?php if ($belegt || (!$bereitschaft['bereit'] && !$abgelaufen)): ?><script>window.setTimeout(function () { window.location.reload(); }, 5000);</script><?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
