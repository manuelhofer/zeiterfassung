<?php
declare(strict_types=1);
/**
 * Template: Arbeitsschritt-Katalog – Liste
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>) – je Eintrag zusätzlich
 *   `code_url` mit der fertigen Strichcode-URL aus dem `BarcodeService`
 * - $darfVerwalten (bool)
 * - optional: $fehlermeldung (string|null), $flashOk (string), $flashFehler (string)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $eintraege */
$eintraege     = $eintraege ?? [];
$darfVerwalten = (bool)($darfVerwalten ?? false);
$fehlermeldung = $fehlermeldung ?? null;
$flashOk       = (string)($flashOk ?? '');
$flashFehler   = (string)($flashFehler ?? '');

$esc = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section>
    <h2>Arbeitsschritt-Katalog</h2>

    <p>
        Hier stehen die immer wiederkehrenden Arbeitsschritte – einmal gepflegt,
        für jeden Auftrag nutzbar. Der Strichcode gehört an die Maschine:
        Wer mehrere Fräsmaschinen hat, druckt <code>fräsen</code> mehrfach aus
        und hängt den Code an jede davon.
    </p>

    <?php if ($flashOk !== ''): ?>
        <p class="success"><?php echo $esc($flashOk); ?></p>
    <?php endif; ?>
    <?php if ($flashFehler !== ''): ?>
        <p class="error"><?php echo $esc($flashFehler); ?></p>
    <?php endif; ?>
    <?php if ($fehlermeldung !== null): ?>
        <div class="fehlermeldung"><?php echo $esc($fehlermeldung); ?></div>
    <?php endif; ?>

    <?php if ($darfVerwalten): ?>
        <div class="table-actions">
            <a class="button-link" href="?seite=arbeitsschritt_katalog_neu">+ Arbeitsschritt hinzufügen</a>
            <?php if (count($eintraege) > 0): ?>
                <a class="button-link quiet" href="?seite=arbeitsschritt_katalog_blatt" target="_blank">Alle Strichcodes als Druckblatt (PDF)</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (count($eintraege) === 0): ?>
        <p>Noch keine Arbeitsschritte im Katalog.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Bezeichnung</th>
                    <th>Strichcode</th>
                    <th>Sortierung</th>
                    <th>Aktiv</th>
                    <th>Drucken</th>
                    <?php if ($darfVerwalten): ?><th>Aktion</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eintraege as $eintrag): ?>
                    <?php
                        $id    = (int)($eintrag['id'] ?? 0);
                        $code  = (string)($eintrag['code'] ?? '');
                        $bez   = trim((string)($eintrag['bezeichnung'] ?? ''));
                        $codeUrl = (string)($eintrag['code_url'] ?? '');
                        $aktiv = (int)($eintrag['aktiv'] ?? 0) === 1;
                    ?>
                    <tr<?php echo $aktiv ? '' : ' class="muted"'; ?>>
                        <td><code><?php echo $esc($code); ?></code></td>
                        <td><?php echo $bez !== '' ? $esc($bez) : '-'; ?></td>
                        <td>
                            <?php if ($codeUrl !== ''): ?>
                                <img src="<?php echo $esc($codeUrl); ?>" alt="Strichcode <?php echo $esc($code); ?>" style="height:44px;width:auto;image-rendering:pixelated;">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)($eintrag['sort_order'] ?? 0); ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <form method="get" action="" target="_blank" style="white-space:nowrap;">
                                <input type="hidden" name="seite" value="arbeitsschritt_katalog_blatt">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <input type="number" name="anzahl" value="1" min="1" max="200" style="width:64px;" title="Wie viele Karten dieses Codes?">
                                <button type="submit">x drucken</button>
                            </form>
                        </td>
                        <?php if ($darfVerwalten): ?>
                            <td><a class="button-link quiet" href="?seite=arbeitsschritt_katalog_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><small>
            Der Katalog schreibt nichts vor: Ein am Terminal gescannter Code, der hier
            nicht steht, wird weiterhin angenommen und gezählt.
        </small></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
