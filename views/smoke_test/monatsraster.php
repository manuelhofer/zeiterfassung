<?php
declare(strict_types=1);
/**
 * Teil-Template: Monatsreport-Raster-Check.
 *
 * Erwartet **einen** Wert, `$monatsrasterDaten` – das Bündel von
 * `SmokeTestController::pruefeMonatsraster()`:
 * - `mitarbeiter_id`, `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $monatsrasterDaten */
$mitarbeiterId = (int)($monatsrasterDaten['mitarbeiter_id'] ?? 0);
$jahr          = (int)($monatsrasterDaten['jahr'] ?? 0);
$monat         = (int)($monatsrasterDaten['monat'] ?? 0);
$ergebnis      = $monatsrasterDaten['ergebnis'] ?? null;
$hinweis       = $monatsrasterDaten['hinweis'] ?? null;
?>
<h3>Monatsreport-Raster-Check</h3>
<p>
    Dieser Check prüft, ob der Monatsreport wirklich ein <strong>vollständiges Monatsraster</strong> liefert:
    <strong>genau ein</strong> Tageswert pro Kalendertag (also z. B. 31 Zeilen im Januar).
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="monatsraster_test_run" value="1">
    <label for="monatsraster_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
    <input type="number" id="monatsraster_test_mitarbeiter_id" name="monatsraster_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="monatsraster_test_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="monatsraster_test_jahr" name="monatsraster_test_jahr"
           value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="monatsraster_test_monat"><strong>Monat</strong>:</label>
    <input type="number" id="monatsraster_test_monat" name="monatsraster_test_monat"
           value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Raster prüfen</button>
</form>

<?php if ($hinweis !== null && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($ergebnis)):
    $ok = (bool)($ergebnis['ok'] ?? false);
    $bg = $ok ? '#e8f5e9' : '#ffebee';
    $bd = $ok ? '#2e7d32' : '#c62828';
    ?>
    <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Monatsraster-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Mitarbeiter-ID: <?php echo (int)($ergebnis['mitarbeiter_id'] ?? 0); ?></li>
            <li>Monat: <?php echo sprintf('%04d-%02d', (int)($ergebnis['jahr'] ?? 0), (int)($ergebnis['monat'] ?? 0)); ?></li>
            <li>Tage im Monat: <?php echo (int)($ergebnis['tage_im_monat'] ?? 0); ?></li>
            <li>Tageswerte (Report): <?php echo (int)($ergebnis['tageswerte_count'] ?? 0); ?></li>
        </ul>

        <?php
        $miss = $ergebnis['missing'] ?? [];
        $dup  = $ergebnis['duplicates'] ?? [];
        $inv  = $ergebnis['invalid'] ?? [];
        ?>

        <?php if (is_array($miss) && $miss !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Fehlende Datumswerte (max. 10):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($miss as $d): ?>
                    <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_array($dup) && $dup !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Doppelte Datumswerte (max. 10):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($dup as $d): ?>
                    <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_array($inv) && $inv !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Ungültige Datumswerte (max. 10):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($inv as $d): ?>
                    <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
