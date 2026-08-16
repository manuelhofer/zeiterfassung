<?php
declare(strict_types=1);
/**
 * Teil-Template: Feiertag-Seed-Check (bundesweit).
 *
 * Erwartet **einen** Wert, `$feiertagSeedDaten` – das Bündel von
 * `SmokeTestController::pruefeFeiertagSeed()`:
 * - `jahr` (int) – der Formularwert des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $feiertagSeedDaten */
$jahr     = (int)($feiertagSeedDaten['jahr'] ?? 0);
$ergebnis = $feiertagSeedDaten['ergebnis'] ?? null;
$hinweis  = $feiertagSeedDaten['hinweis'] ?? null;
?>
<h3>Feiertag-Seed-Check (bundesweit)</h3>
<p>
    Dieser Check prüft, ob die <strong>bundeseinheitliche Grundmenge</strong> für ein Jahr in der Tabelle <code>feiertag</code>
    vorhanden ist. Fehlende Einträge werden dabei (wie im Livebetrieb) <strong>idempotent</strong> nachgezogen.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="feiertag_seed_run" value="1">
    <label for="feiertag_seed_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="feiertag_seed_jahr" name="feiertag_seed_jahr"
           value="<?php echo $jahr; ?>" style="width: 110px;">
    <button type="submit">Prüfen</button>
</form>

<?php if (is_string($hinweis) && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($ergebnis)):
    $ok = $ergebnis['ok'] ?? null;
    $isOk = ($ok === true);
    $isFail = ($ok === false);
    $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
    $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
    $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
    $missing = $ergebnis['missing'] ?? [];
    $extra = $ergebnis['extra'] ?? [];
    ?>
    <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> <?php echo htmlspecialchars((string)($ergebnis['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <ul style="margin:0 0 6px 0;">
            <li>Jahr: <?php echo (int)($ergebnis['jahr'] ?? 0); ?></li>
            <li>Erwartet: <?php echo (int)($ergebnis['erwartet'] ?? 0); ?></li>
            <li>Vorhanden: <?php echo (int)($ergebnis['vorhanden'] ?? 0); ?></li>
        </ul>

        <?php if (is_array($missing) && $missing !== []): ?>
            <p style="margin:6px 0 4px 0;"><strong>Fehlend:</strong></p>
            <ul style="margin:0 0 6px 0;">
                <?php foreach ($missing as $m):
                    $md = htmlspecialchars((string)($m['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $mn = htmlspecialchars((string)($m['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    ?>
                    <li><?php echo $md; ?> – <?php echo $mn; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_array($extra) && $extra !== []): ?>
            <p style="margin:6px 0 4px 0;"><strong>Zusätzlich vorhanden (nicht in der Grundmenge):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($extra as $d): ?>
                    <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
