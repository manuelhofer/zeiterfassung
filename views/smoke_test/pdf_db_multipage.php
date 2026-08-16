<?php
declare(strict_types=1);
/**
 * Teil-Template: PDF DB Auto-Multipage-Check samt Kandidatenliste.
 *
 * Der Abschnitt zeigt **zwei** Checks, die sich ein Suchfenster teilen; deshalb
 * bekommt er auch zwei Bündel:
 * - `$pdfDbMultiDaten` von `SmokeTestController::pruefePdfDbMultipage()`
 *   – `window_monate` (int), `ergebnis` (array|null), `hinweis` (string|null)
 * - `$pdfDbListeDaten` von `SmokeTestController::sucheMultipageKandidaten()`
 *   – `window_monate`, `limit` (int), `ergebnis` (array|null), `hinweis` (string|null)
 *
 * Dazu `$csrfBereich` (string) – der Bereichsname für `Csrf::feld()`. Vier
 * Formulare hier schreiben in die Datenbank nichts, stoßen aber die
 * PDF-Erzeugung an und sind deshalb geschützt.
 */
/** @var array<string,mixed> $pdfDbMultiDaten */
/** @var array<string,mixed> $pdfDbListeDaten */
/** @var string $csrfBereich */
$fensterMonate = (int)($pdfDbMultiDaten['window_monate'] ?? 0);
$ergebnis      = $pdfDbMultiDaten['ergebnis'] ?? null;
$hinweis       = $pdfDbMultiDaten['hinweis'] ?? null;

$listeFenster  = (int)($pdfDbListeDaten['window_monate'] ?? 0);
$listeLimit    = (int)($pdfDbListeDaten['limit'] ?? 0);
$liste         = $pdfDbListeDaten['ergebnis'] ?? null;
$listeHinweis  = $pdfDbListeDaten['hinweis'] ?? null;
?>
<h3>PDF DB Auto-Multipage-Check (Kandidat finden)</h3>
<p>
    Dieser Check sucht in den letzten <strong>X Monaten</strong> automatisch den Mitarbeiter/Monat mit den meisten
    <strong>Kommen/Gehen</strong>-Buchungen und prüft, ob das erzeugte Monats-PDF <strong>mindestens 2 Seiten</strong> hat.
    Damit findest du schnell einen echten Datensatz für den Browser-Test (Mehrfach-Kommen/Gehen + Mehrseiten).
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_db_multipage_run" value="1">
    <?php echo Csrf::feld($csrfBereich); ?>
    <label for="pdf_db_multipage_window"><strong>Suchfenster (Monate)</strong>:</label>
    <input type="number" id="pdf_db_multipage_window" name="pdf_db_multipage_window" value="<?php echo $fensterMonate; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Automatisch finden &amp; prüfen</button>
</form>

<p style="margin: 0 0 6px 0;">
    <em>Optional:</em> Statt nur den Top-1 Kandidaten zu prüfen, kannst du dir eine <strong>Kandidatenliste</strong> anzeigen lassen
    und gezielt einen Monat testen (nützlich für Browser-Tests).
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_db_multipage_list_run" value="1">
    <?php echo Csrf::feld($csrfBereich); ?>
    <label for="pdf_db_multipage_window_list"><strong>Suchfenster (Monate)</strong>:</label>
    <input type="number" id="pdf_db_multipage_window_list" name="pdf_db_multipage_window" value="<?php echo $listeFenster; ?>" style="width: 70px;">
    &nbsp;
    <label for="pdf_db_multipage_list_limit"><strong>Limit</strong>:</label>
    <input type="number" id="pdf_db_multipage_list_limit" name="pdf_db_multipage_list_limit" value="<?php echo $listeLimit; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Kandidatenliste anzeigen</button>
</form>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_db_multipage_list_eval" value="1">
    <?php echo Csrf::feld($csrfBereich); ?>
    <label for="pdf_db_multipage_window_list_eval"><strong>Suchfenster (Monate)</strong>:</label>
    <input type="number" id="pdf_db_multipage_window_list_eval" name="pdf_db_multipage_window" value="<?php echo $listeFenster; ?>" style="width: 70px;">
    &nbsp;
    <label for="pdf_db_multipage_list_limit_eval"><strong>Limit</strong>:</label>
    <input type="number" id="pdf_db_multipage_list_limit_eval" name="pdf_db_multipage_list_limit" value="<?php echo $listeLimit; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Kandidatenliste automatisch prüfen (PDF)</button>
</form>

<?php if ($listeHinweis !== null && $listeHinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($listeHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($liste) && $liste !== []): ?>
    <div style="padding:10px; background:#e3f2fd; border:1px solid #1565c0; margin-bottom: 12px;">
        <p style="margin:0 0 8px 0;"><strong>Kandidatenliste</strong> (Top <?php echo $listeLimit; ?>, Fenster <?php echo $listeFenster; ?> Monate)</p>

        <?php
        $hasEval = false;
        if (isset($liste[0]) && is_array($liste[0]) && array_key_exists('eval_ok', $liste[0])) {
            $hasEval = true;
        }
        ?>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
            <tr>
                <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Mitarbeiter</th>
                <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Monat</th>
                <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">K/G</th>
                <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">Max/Tag</th>
                <?php if (!empty($hasEval)): ?>
                    <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Status</th>
                    <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">Seiten</th>
                    <th style="text-align:right; border-bottom: 1px solid #90caf9; padding: 6px;">Bytes</th>
                <?php endif; ?>
                <th style="text-align:left; border-bottom: 1px solid #90caf9; padding: 6px;">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($liste as $row): ?>
                <?php
                    $mid = (int)($row['mitarbeiter_id'] ?? 0);
                    $jahr = (int)($row['jahr'] ?? 0);
                    $monat = (int)($row['monat'] ?? 0);
                    $name = (string)($row['name'] ?? '');
                    $kg = (int)($row['buchungen_kommen_gehen'] ?? 0);
                    $maxc = (int)($row['max_day_buchungen'] ?? 0);
                    $maxd = (string)($row['max_day_datum'] ?? '');
                    $zeileReport = (string)($row['link_report'] ?? '');
                    $zeilePdf = (string)($row['link_pdf'] ?? '');

                    $evalOk = !empty($hasEval) ? ($row['eval_ok'] ?? null) : null;
                    $evalReason = !empty($hasEval) ? (string)($row['eval_reason'] ?? '') : '';
                    $evalPages = !empty($hasEval) ? (int)($row['eval_pages'] ?? 0) : 0;
                    $evalBytes = !empty($hasEval) ? (int)($row['eval_bytes'] ?? 0) : 0;
                ?>
                <tr>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                        #<?php echo $mid; ?> (<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                    </td>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                        <?php echo sprintf('%02d/%04d', $monat, $jahr); ?>
                    </td>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                        <?php echo $kg; ?>
                    </td>
                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                        <?php echo $maxc; ?><?php echo $maxd !== '' ? ' (' . htmlspecialchars($maxd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : ''; ?>
                    </td>

                    <?php if (!empty($hasEval)): ?>
                        <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                            <?php
                                if ($evalOk === true) {
                                    echo '<strong>OK</strong>';
                                } elseif ($evalOk === false) {
                                    echo '<strong>FAIL</strong>';
                                } else {
                                    echo 'SKIP';
                                }
                            ?>
                            <?php if ($evalOk !== true && $evalReason !== ''): ?>
                                <br><small><?php echo htmlspecialchars($evalReason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                            <?php echo $evalPages; ?>
                        </td>
                        <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                            <?php echo $evalBytes; ?>
                        </td>
                    <?php endif; ?>

                    <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                        <form method="post" action="?seite=smoke_test" style="display:inline;">
                            <input type="hidden" name="pdf_db_multipage_run" value="1">
                            <?php echo Csrf::feld($csrfBereich); ?>
                            <input type="hidden" name="pdf_db_multipage_mid" value="<?php echo $mid; ?>">
                            <input type="hidden" name="pdf_db_multipage_year" value="<?php echo $jahr; ?>">
                            <input type="hidden" name="pdf_db_multipage_month" value="<?php echo $monat; ?>">
                            <input type="hidden" name="pdf_db_multipage_window" value="<?php echo $listeFenster; ?>">
                            <button type="submit">Prüfen</button>
                        </form>
                        <?php if ($zeileReport !== ''): ?>
                            &nbsp;|&nbsp;<a href="<?php echo htmlspecialchars($zeileReport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Report</a>
                        <?php endif; ?>
                        <?php if ($zeilePdf !== ''): ?>
                            &nbsp;|&nbsp;<a href="<?php echo htmlspecialchars($zeilePdf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($hinweis !== null && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($ergebnis)):
    $ok = (bool)($ergebnis['ok'] ?? false);
    $linkReport = (string)($ergebnis['link_report'] ?? '');
    $linkPdf = (string)($ergebnis['link_pdf'] ?? '');
    ?>
    <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> PDF DB Auto-Multipage Ergebnis</p>
        <ul style="margin:0;">
            <li>Suchfenster: <?php echo (int)($ergebnis['window_monate'] ?? 0); ?> Monate</li>
            <li>Gefunden via: <?php echo htmlspecialchars((string)($ergebnis['gefunden_via'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            <li>Mitarbeiter: #<?php echo (int)($ergebnis['mitarbeiter_id'] ?? 0); ?> (<?php echo htmlspecialchars((string)($ergebnis['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</li>
            <li>Monat: <?php echo (int)($ergebnis['monat'] ?? 0); ?>/<?php echo (int)($ergebnis['jahr'] ?? 0); ?></li>
            <li>Kommen/Gehen-Buchungen: <?php echo (int)($ergebnis['buchungen_kommen_gehen'] ?? 0); ?></li>
            <li>Max. Buchungen an einem Tag: <?php echo (int)($ergebnis['max_day_buchungen'] ?? 0); ?><?php echo ($ergebnis['max_day_datum'] ?? '') !== '' ? ' (' . htmlspecialchars((string)$ergebnis['max_day_datum'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : ''; ?></li>
            <li>PDF Bytes: <?php echo (int)($ergebnis['pdf_bytes'] ?? 0); ?></li>
            <li>Seiten (/Pages /Count): <?php echo ($ergebnis['pages_count_declared'] ?? null) !== null ? (int)$ergebnis['pages_count_declared'] : 'n/a'; ?></li>
            <li>Seiten-Objekte (/Type /Page): <?php echo (int)($ergebnis['pages_count_objects'] ?? 0); ?></li>
            <li>Mind. 2 Seiten erkannt: <?php echo !empty($ergebnis['pages_at_least2']) ? 'ja' : 'nein'; ?></li>
            <li>Seitenanzahl konsistent: <?php echo ($ergebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($ergebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
            <li>Footer "Seite 1/" gefunden: <?php echo !empty($ergebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
            <li>Footer "Seite 2/" gefunden: <?php echo !empty($ergebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
            <li>
                HTML-Render-Check:
                <?php
                    $hOk = $ergebnis['html_ok'] ?? null;
                    if ($hOk === null) {
                        echo 'SKIP';
                    } else {
                        echo !empty($hOk) ? 'OK' : 'FAIL';
                    }
                ?>
                <?php if (!empty($ergebnis['html_hinweis'])): ?>
                    &nbsp;<small>(<?php echo htmlspecialchars((string)$ergebnis['html_hinweis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
                <?php endif; ?>
            </li>
            <li>HTML: Heading "Monatsübersicht": <?php echo !empty($ergebnis['html_has_heading']) ? 'ja' : 'nein'; ?>, Tabelle: <?php echo !empty($ergebnis['html_has_table']) ? 'ja' : 'nein'; ?>, Headerzellen (Datum/An/Ab): <?php echo !empty($ergebnis['html_has_header_cells']) ? 'ja' : 'nein'; ?>, PDF-Link: <?php echo !empty($ergebnis['html_has_pdf_link']) ? 'ja' : 'nein'; ?></li>
            <li>HTML: &lt;tr&gt; Count: <?php echo (int)($ergebnis['html_tr_count'] ?? 0); ?> (Tage im Monat: <?php echo (int)($ergebnis['html_days_in_month'] ?? 0); ?>, Mindest-OK: <?php echo ($ergebnis['html_rows_min_ok'] ?? null) === null ? 'n/a' : (!empty($ergebnis['html_rows_min_ok']) ? 'ja' : 'nein'); ?>)</li>
        </ul>

        <?php if ($linkReport !== '' || $linkPdf !== ''): ?>
            <hr>
            <p style="margin:0;">
                <?php if ($linkReport !== ''): ?>
                    <a href="<?php echo htmlspecialchars($linkReport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Monatsreport öffnen</a>
                <?php endif; ?>
                <?php if ($linkReport !== '' && $linkPdf !== ''): ?>
                    &nbsp;|&nbsp;
                <?php endif; ?>
                <?php if ($linkPdf !== ''): ?>
                    <a href="<?php echo htmlspecialchars($linkPdf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Monats-PDF öffnen</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
