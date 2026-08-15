<?php
declare(strict_types=1);
/**
 * Teil-Template: Feiertag-Quick-Check (Monatsreport).
 *
 * Erwartet **einen** Wert, `$feiertagQuickDaten` – das Bündel von
 * `SmokeTestController::pruefeFeiertagQuick()`:
 * - `mitarbeiter_id` (int), `datum` (string) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 *
 * Kein `?? []` auf das Bündel selbst: Wer es vergisst, soll eine Meldung im Log
 * bekommen und keine still leere Kachel (Begründung wie in `index.php`).
 */
/** @var array<string,mixed> $feiertagQuickDaten */
$mitarbeiterId = (int)($feiertagQuickDaten['mitarbeiter_id'] ?? 0);
$datum         = (string)($feiertagQuickDaten['datum'] ?? '');
$ergebnis      = $feiertagQuickDaten['ergebnis'] ?? null;
$hinweis       = $feiertagQuickDaten['hinweis'] ?? null;
?>
<h3>Feiertag-Quick-Check (Monatsreport)</h3>
<p>
    Dieser Check prüft, ob ein konkretes Datum im Monatsreport als <strong>Feiertag</strong> erkannt wird
    und (wenn <strong>keine</strong> Arbeitszeit vorhanden ist) die <strong>Sollstunden</strong> im Feld <strong>Feiertag</strong> landen.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="feiertag_test_run" value="1">
    <label for="feiertag_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong> (optional):</label>
    <input type="number" id="feiertag_test_mitarbeiter_id" name="feiertag_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="feiertag_test_datum"><strong>Datum</strong> (YYYY-MM-DD):</label>
    <input type="text" id="feiertag_test_datum" name="feiertag_test_datum"
           value="<?php echo htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
           style="width: 140px;">
    &nbsp;
    <button type="submit">Feiertag prüfen</button>
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
    ?>
    <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Feiertag-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Datum: <?php echo htmlspecialchars((string)($ergebnis['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            <li>Wochentag (1=Mo..7=So): <?php echo (int)($ergebnis['wochentag'] ?? 0); ?></li>
            <li>FeiertagService sagt: <?php
                $v = $ergebnis['ist_feiertag'] ?? null;
                echo ($v === true) ? 'ja' : (($v === false) ? 'nein' : 'unbekannt');
            ?></li>
            <li>Kennzeichen Feiertag: <?php echo (int)($ergebnis['kennzeichen_feiertag'] ?? 0); ?></li>
            <li>Arbeitszeit (Ist): <?php echo htmlspecialchars((string)($ergebnis['arbeitszeit_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</li>
            <li>Feiertag-Stunden: <?php echo htmlspecialchars((string)($ergebnis['feiertag_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</li>
            <li>Tagestyp: <?php echo htmlspecialchars((string)($ergebnis['tagestyp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            <li>Kürzel/Kommentar: <?php echo htmlspecialchars((string)($ergebnis['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
        </ul>
        <hr>
        <p style="margin:0;"><em><?php echo htmlspecialchars((string)($ergebnis['erwartung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em></p>
    </div>
<?php endif; ?>
