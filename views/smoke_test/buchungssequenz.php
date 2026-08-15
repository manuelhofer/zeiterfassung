<?php
declare(strict_types=1);
/**
 * Teil-Template: Kommen/Gehen-Sequenz-Check (Monat).
 *
 * Erwartet **einen** Wert, `$buchungssequenzDaten` – das Bündel von
 * `SmokeTestController::pruefeBuchungssequenz()`:
 * - `mitarbeiter_id`, `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $buchungssequenzDaten */
$mitarbeiterId = (int)($buchungssequenzDaten['mitarbeiter_id'] ?? 0);
$jahr          = (int)($buchungssequenzDaten['jahr'] ?? 0);
$monat         = (int)($buchungssequenzDaten['monat'] ?? 0);
$ergebnis      = $buchungssequenzDaten['ergebnis'] ?? null;
$hinweis       = $buchungssequenzDaten['hinweis'] ?? null;
?>
<h3>Kommen/Gehen-Sequenz-Check (Monat)</h3>
<p>
    Dieser Check analysiert die Reihenfolge der Zeitbuchungen (kommen/gehen) pro Tag.
    Er findet auffällige Tage wie z.B. <em>gehen ohne kommen</em>, <em>doppelte Typen</em> oder <em>offene Arbeitsblöcke</em>.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="buchungssequenz_test_run" value="1">
    <label for="buchungssequenz_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
    <input type="number" id="buchungssequenz_test_mitarbeiter_id" name="buchungssequenz_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="buchungssequenz_test_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="buchungssequenz_test_jahr" name="buchungssequenz_test_jahr"
           value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="buchungssequenz_test_monat"><strong>Monat</strong>:</label>
    <input type="number" id="buchungssequenz_test_monat" name="buchungssequenz_test_monat"
           value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Sequenz prüfen</button>
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
    $auffaellig = $ergebnis['auffaellig_sample'] ?? [];
    $mehrblock = $ergebnis['mehrblock_sample'] ?? [];
    ?>
    <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'HINWEIS'; ?>:</strong> Kommen/Gehen-Sequenz-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Mitarbeiter-ID: <?php echo (int)($ergebnis['mitarbeiter_id'] ?? 0); ?></li>
            <li>Monat: <?php echo sprintf('%04d-%02d', (int)($ergebnis['jahr'] ?? 0), (int)($ergebnis['monat'] ?? 0)); ?></li>
            <li>Tage mit Buchungen: <?php echo (int)($ergebnis['tage_mit_buchungen'] ?? 0); ?></li>
            <li>Auffällige Tage: <?php echo (int)($ergebnis['tage_auffaellig'] ?? 0); ?></li>
            <li>Tage mit mehreren Arbeitsblöcken: <?php echo (int)($ergebnis['tage_mehrblock'] ?? 0); ?></li>
        </ul>

        <?php if (is_array($auffaellig) && $auffaellig !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Auffällige Tage (max. 10):</strong></p>
            <table style="border-collapse:collapse; width:100%;">
                <thead>
                <tr>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Buchungen</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Paare</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Anomalien</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Sequenz</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($auffaellig as $a): ?>
                    <tr>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo htmlspecialchars((string)($a['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo (int)($a['count'] ?? 0); ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo (int)($a['pair_count'] ?? 0); ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo htmlspecialchars((string)($a['anomalien'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo htmlspecialchars((string)($a['sequenz'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (is_array($mehrblock) && $mehrblock !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Mehrblock-Tage (max. 10):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($mehrblock as $m): ?>
                    <li>
                        <?php echo htmlspecialchars((string)($m['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        (Paare: <?php echo (int)($m['pair_count'] ?? 0); ?>, Buchungen: <?php echo (int)($m['count'] ?? 0); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
