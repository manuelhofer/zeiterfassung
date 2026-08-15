<?php
declare(strict_types=1);
/**
 * Teil-Template: Monatsreport-Fallback-Check (lückenhafte Tageswerte).
 *
 * Erwartet **einen** Wert, `$monatsfallbackDaten` – das Bündel von
 * `SmokeTestController::pruefeMonatsfallback()`:
 * - `mitarbeiter_id`, `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $monatsfallbackDaten */
$mitarbeiterId = (int)($monatsfallbackDaten['mitarbeiter_id'] ?? 0);
$jahr          = (int)($monatsfallbackDaten['jahr'] ?? 0);
$monat         = (int)($monatsfallbackDaten['monat'] ?? 0);
$ergebnis      = $monatsfallbackDaten['ergebnis'] ?? null;
$hinweis       = $monatsfallbackDaten['hinweis'] ?? null;
?>
<h3>Monatsreport-Fallback-Check (lückenhafte Tageswerte)</h3>
<p>
    Dieser Check sucht im ausgewählten Monat nach Tagen, an denen es <strong>Zeitbuchungen</strong> gibt,
    aber <strong>kein</strong> entsprechender Datensatz in <code>tageswerte_mitarbeiter</code> vorhanden ist.
    Anschliessend wird geprüft, ob der Monatsreport diese Tage per <strong>Fallback</strong> (aus Zeitbuchungen) sinnvoll befüllt.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="monatsfallback_test_run" value="1">
    <label for="monatsfallback_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
    <input type="number" id="monatsfallback_test_mitarbeiter_id" name="monatsfallback_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="monatsfallback_test_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="monatsfallback_test_jahr" name="monatsfallback_test_jahr"
           value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="monatsfallback_test_monat"><strong>Monat</strong>:</label>
    <input type="number" id="monatsfallback_test_monat" name="monatsfallback_test_monat"
           value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Fallback prüfen</button>
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
    $missingCount = (int)($ergebnis['missing_days_count'] ?? 0);
    $notCoveredCount = (int)($ergebnis['not_covered_count'] ?? 0);
    ?>
    <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Monatsreport-Fallback-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Mitarbeiter-ID: <?php echo (int)($ergebnis['mitarbeiter_id'] ?? 0); ?></li>
            <li>Monat: <?php echo sprintf('%04d-%02d', (int)($ergebnis['jahr'] ?? 0), (int)($ergebnis['monat'] ?? 0)); ?></li>
            <li>Tage mit Buchungen: <?php echo (int)($ergebnis['booked_days_count'] ?? 0); ?></li>
            <li>Tage mit Tageswerten (DB): <?php echo (int)($ergebnis['tageswerte_days_count'] ?? 0); ?></li>
            <li>Tage mit Buchungen aber ohne Tageswerte: <?php echo $missingCount; ?></li>
            <li>Davon im Report nicht sinnvoll befüllt: <?php echo $notCoveredCount; ?></li>
        </ul>

        <?php
        $miss = $ergebnis['missing_days_sample'] ?? [];
        $nc = $ergebnis['not_covered_sample'] ?? [];
        $samples = $ergebnis['samples'] ?? [];
        ?>

        <?php if (is_array($miss) && $miss !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Beispiel: fehlende Tageswerte (max. 10):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($miss as $d): ?>
                    <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_array($nc) && $nc !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Nicht befüllt (max. 10):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($nc as $d): ?>
                    <li><?php echo htmlspecialchars((string)$d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_array($samples) && $samples !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Samples (max. 8):</strong></p>
            <table style="border-collapse:collapse; width:100%;">
                <thead>
                <tr>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Buchungen</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Kommen (roh)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Gehen (roh)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">AZ (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Pause (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:center;">Report gefüllt</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($samples as $s):
                    $d = htmlspecialchars((string)($s['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $b = (int)($s['buchungen'] ?? 0);
                    $k = htmlspecialchars((string)($s['kommen_roh'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $g = htmlspecialchars((string)($s['gehen_roh'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $az = htmlspecialchars((string)($s['arbeitszeit_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $pz = htmlspecialchars((string)($s['pausen_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $cv = !empty($s['covered']);
                    ?>
                    <tr>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $d; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $b; ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $k; ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $g; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $az; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $pz; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center;"><?php echo $cv ? 'ja' : 'nein'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
