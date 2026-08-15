<?php
declare(strict_types=1);
/**
 * Teil-Template: Doppelzählung-Check (Betriebsferien / Kurzarbeit-Volltag).
 *
 * Erwartet **einen** Wert, `$doppelzaehlungDaten` – das Bündel von
 * `SmokeTestController::pruefeDoppelzaehlung()`:
 * - `mitarbeiter_id`, `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $doppelzaehlungDaten */
$mitarbeiterId = (int)($doppelzaehlungDaten['mitarbeiter_id'] ?? 0);
$jahr          = (int)($doppelzaehlungDaten['jahr'] ?? 0);
$monat         = (int)($doppelzaehlungDaten['monat'] ?? 0);
$ergebnis      = $doppelzaehlungDaten['ergebnis'] ?? null;
$hinweis       = $doppelzaehlungDaten['hinweis'] ?? null;
?>
<h3>Doppelzählung-Check (Betriebsferien / Kurzarbeit-Volltag)</h3>
<p>
    Dieser Check prüft im Monatsreport, ob <strong>Betriebsferien</strong> (als Urlaub 8h) oder <strong>Kurzarbeit-Volltag</strong>
    <strong>nicht zusätzlich</strong> gezählt werden, wenn an diesem Tag bereits <strong>Arbeitszeit</strong> vorhanden ist.
    Er findet damit typische Randfälle, bei denen Monatsübersicht/PDF sonst zu hohe Ist-Summen zeigen würden.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="doppelzaehlung_test_run" value="1">
    <label for="doppelzaehlung_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
    <input type="number" id="doppelzaehlung_test_mitarbeiter_id" name="doppelzaehlung_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="doppelzaehlung_test_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="doppelzaehlung_test_jahr" name="doppelzaehlung_test_jahr"
           value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="doppelzaehlung_test_monat"><strong>Monat</strong>:</label>
    <input type="number" id="doppelzaehlung_test_monat" name="doppelzaehlung_test_monat"
           value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Doppelzählung prüfen</button>
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
        <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Doppelzählung-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Mitarbeiter-ID: <?php echo (int)($ergebnis['mitarbeiter_id'] ?? 0); ?></li>
            <li>Monat: <?php echo sprintf('%04d-%02d', (int)($ergebnis['jahr'] ?? 0), (int)($ergebnis['monat'] ?? 0)); ?></li>
            <li>Betriebsferien-Tage im Report: <?php echo (int)($ergebnis['betriebsferien_tage'] ?? 0); ?></li>
            <li>Kurzarbeit-Volltag-Tage im Report: <?php echo (int)($ergebnis['kurzarbeit_volltag_tage'] ?? 0); ?></li>
            <li>Auffälligkeiten: <?php echo (int)($ergebnis['issues_count'] ?? 0); ?></li>
        </ul>

        <?php if (is_array($issues) && $issues !== []): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Beispiele (max. 12):</strong></p>
            <table style="border-collapse:collapse; width:100%;">
                <thead>
                <tr>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Datum</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Typ</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">AZ (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Urlaub (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:right;">Kurzarbeit (h)</th>
                    <th style="border:1px solid #ccc; padding:6px; text-align:left;">Hinweis</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $it):
                    $d = htmlspecialchars((string)($it['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $typ = htmlspecialchars((string)($it['typ'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $az = htmlspecialchars((string)($it['arbeitszeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $u = htmlspecialchars((string)($it['urlaub'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $k = htmlspecialchars((string)($it['kurzarbeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $h = htmlspecialchars((string)($it['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    ?>
                    <tr>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $d; ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $typ; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $az; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $u; ?></td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:right;"><?php echo $k; ?></td>
                        <td style="border:1px solid #ccc; padding:6px;"><?php echo $h; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
