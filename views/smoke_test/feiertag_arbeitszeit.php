<?php
declare(strict_types=1);
/**
 * Teil-Template: Feiertag+Arbeitszeit-Check (Monat).
 *
 * Erwartet **einen** Wert, `$feiertagArbeitszeitDaten` – das Bündel von
 * `SmokeTestController::pruefeFeiertagUndArbeitszeit()`:
 * - `mitarbeiter_id`, `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $feiertagArbeitszeitDaten */
$mitarbeiterId = (int)($feiertagArbeitszeitDaten['mitarbeiter_id'] ?? 0);
$jahr          = (int)($feiertagArbeitszeitDaten['jahr'] ?? 0);
$monat         = (int)($feiertagArbeitszeitDaten['monat'] ?? 0);
$ergebnis      = $feiertagArbeitszeitDaten['ergebnis'] ?? null;
$hinweis       = $feiertagArbeitszeitDaten['hinweis'] ?? null;
?>
<h3>Feiertag+Arbeitszeit-Check (Monat)</h3>
<p>
    Dieser Check findet Konflikte, bei denen an einem Feiertag sowohl <em>Arbeitszeit</em> als auch
    <em>Feiertagsstunden</em> gesetzt sind. Das würde im Monatsreport zu einer Doppelzählung führen.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="feiertag_arbeitszeit_test_run" value="1">
    <label for="feiertag_arbeitszeit_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
    <input type="number" id="feiertag_arbeitszeit_test_mitarbeiter_id" name="feiertag_arbeitszeit_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="feiertag_arbeitszeit_test_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="feiertag_arbeitszeit_test_jahr" name="feiertag_arbeitszeit_test_jahr"
           value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="feiertag_arbeitszeit_test_monat"><strong>Monat</strong>:</label>
    <input type="number" id="feiertag_arbeitszeit_test_monat" name="feiertag_arbeitszeit_test_monat"
           value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Konflikte prüfen</button>
</form>

<?php if ($hinweis !== null && $hinweis !== ''): ?>
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
    $issues = $ergebnis['issues'] ?? [];
    ?>
    <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Feiertag+Arbeitszeit-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Mitarbeiter-ID: <?php echo (int)($ergebnis['mitarbeiter_id'] ?? 0); ?></li>
            <li>Monat: <?php echo sprintf('%04d-%02d', (int)($ergebnis['jahr'] ?? 0), (int)($ergebnis['monat'] ?? 0)); ?></li>
            <li>Feiertag-Tage im Monat: <?php echo (int)($ergebnis['feiertag_tage'] ?? 0); ?></li>
            <li>Konflikte: <?php echo (int)($ergebnis['issues_count'] ?? 0); ?></li>
        </ul>

        <?php if (is_array($issues) && $issues !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Beispiele (max. 12):</strong></p>
            <table style="border-collapse:collapse; width:100%;">
                <thead>
                <tr>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">AZ (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Feiertag (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Hinweis</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $it):
                    $d = htmlspecialchars((string)($it['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $az = htmlspecialchars((string)($it['arbeitszeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $ft = htmlspecialchars((string)($it['feiertag'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $h = htmlspecialchars((string)($it['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    ?>
                    <tr>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $d; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $az; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $ft; ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $h; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
