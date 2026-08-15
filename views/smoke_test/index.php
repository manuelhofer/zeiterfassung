<?php
declare(strict_types=1);
/**
 * Template: Smoke-Test (Diagnose)
 *
 * Eine Seite, fünfzehn voneinander unabhängige Checks. Der Controller führt
 * jeden Check aus und legt sein Ergebnis in einem eigenen Satz Variablen ab –
 * hier wird nur angezeigt (siehe `SmokeTestController::index()`).
 *
 * Jeder Check bringt dieselben drei Sorten Werte mit:
 * - `…Ergebnis` (array|null) – `null` heisst „noch nicht gelaufen", die
 *   Ergebnisanzeige bleibt dann weg,
 * - `…Hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses,
 * - die Formularwerte, mit denen der Check zuletzt lief.
 *
 * Erwartet:
 * - $terminalLoginCode, $terminalLoginErgebnis, $terminalLoginHinweis
 * - $pdfTestMitarbeiterId, $pdfTestJahr, $pdfTestMonat,
 *   $pdfTestErgebnis, $pdfTestHinweis
 * - $pdfSynthJahr, $pdfSynthMonat, $pdfSynthErgebnis, $pdfSynthHinweis
 * - $pdfDbMultiWindowMonate, $pdfDbMultiListLimit, $pdfDbMultiErgebnis,
 *   $pdfDbMultiHinweis, $pdfDbMultiListe, $pdfDbMultiListHinweis
 * - $feiertagTestMitarbeiterId, $feiertagTestDatum,
 *   $feiertagTestErgebnis, $feiertagTestHinweis
 * - $monatsrasterTest…, $monatsfallbackTest…, $doppelzaehlungTest…,
 *   $feiertagArbeitszeitTest…, $buchungssequenzTest… – je MitarbeiterId,
 *   Jahr, Monat, Ergebnis und Hinweis
 * - $feiertagSeedJahr, $feiertagSeedErgebnis, $feiertagSeedHinweis
 * - $terminalConfig (array|null), $terminalConfigHinweis (string|null)
 * - $queueUebersicht (array|null), $queueHinweis, $smokeFlash (string|null)
 * - $checks (array) – die Tabelle der Abhängigkeits-Checks
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 *
 * **Warum hier keine `?? null`-Vorbelegung steht wie in den kleinen Masken:**
 * Der Controller setzt alle diese Variablen ausnahmslos, bevor er die View
 * einbindet. Eine Vorbelegung wären knapp sechzig Zeilen, die bei jeder
 * Änderung am Controller mitgepflegt werden müssten – und sie würde einen
 * Controller, der eine davon vergisst, in eine still leere Kachel verwandeln
 * statt in eine Meldung im Log.
 */
require __DIR__ . '/../layout/header.php';
?>
<section>
    <h2>Smoke-Test (Diagnose)</h2>
    <p>
        Diese Seite führt <strong>nur lesende</strong> Checks aus. Sie ändert keine Zeiten und startet keine Queue.
        Optional kann sie PDFs <strong>im Speicher</strong> erzeugen, um die PDF-Erzeugung auf Validität (Header/EOF) zu prüfen.
    </p>

    <p>
        <a href="?seite=dashboard">&laquo; Zurück zum Dashboard</a>
    </p>

    <h3>Terminal-Login-Check (RFID / Personalnummer / ID)</h3>
    <p>
        Dieser Check ist <strong>rein lesend</strong> und emuliert die Login-Reihenfolge des Terminals.
        Damit lässt sich schnell prüfen, ob ein Code in der Datenbank zu einem <strong>aktiven</strong> Mitarbeiter auflöst.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <label for="terminal_login_code"><strong>Code</strong>:</label>
        <input type="text" id="terminal_login_code" name="terminal_login_code"
               value="<?php echo htmlspecialchars($terminalLoginCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
               style="min-width: 260px;">
        <button type="submit">Prüfen</button>
    </form>

    <?php if ($terminalLoginHinweis !== null && $terminalLoginHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($terminalLoginHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <h3>PDF-Quick-Check (Header/EOF/Seiten, ohne Download)</h3>
    <p>
        Dieser Check erzeugt das Monats-PDF <strong>im Speicher</strong> und prüft nur, ob das Ergebnis wie ein valides PDF aussieht.
        Es wird <strong>nichts</strong> als PDF ausgeliefert.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="pdf_test_run" value="1">
        <label for="pdf_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong> (optional):</label>
        <input type="number" id="pdf_test_mitarbeiter_id" name="pdf_test_mitarbeiter_id"
               value="<?php echo (int)$pdfTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="pdf_test_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="pdf_test_jahr" name="pdf_test_jahr" value="<?php echo (int)$pdfTestJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="pdf_test_monat"><strong>Monat</strong>:</label>
        <input type="number" id="pdf_test_monat" name="pdf_test_monat" value="<?php echo (int)$pdfTestMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">PDF prüfen</button>
    </form>

    <?php if ($pdfTestHinweis !== null && $pdfTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($pdfTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($pdfTestErgebnis)):
        $ok = (bool)($pdfTestErgebnis['ok'] ?? false);
        ?>
        <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> PDF-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Länge (Bytes): <?php echo (int)($pdfTestErgebnis['bytes'] ?? 0); ?></li>
                <li>Header "%PDF-" vorhanden: <?php echo !empty($pdfTestErgebnis['header_ok']) ? 'ja' : 'nein'; ?></li>
                <li>"%%EOF" vorhanden: <?php echo !empty($pdfTestErgebnis['eof_ok']) ? 'ja' : 'nein'; ?></li>
                <li>Seiten (/Pages /Count): <?php echo ($pdfTestErgebnis['pages_count_declared'] ?? null) !== null ? (int)$pdfTestErgebnis['pages_count_declared'] : 'n/a'; ?></li>
                <li>Seiten-Objekte (/Type /Page): <?php echo (int)($pdfTestErgebnis['pages_count_objects'] ?? 0); ?></li>
                <li>Seitenanzahl konsistent: <?php echo ($pdfTestErgebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($pdfTestErgebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
                <li>Footer "Seite 1/..." gefunden: <?php echo !empty($pdfTestErgebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
                <?php if ((int)($pdfTestErgebnis['pages_count_objects'] ?? 0) >= 2): ?>
                    <li>Footer "Seite 2/..." gefunden: <?php echo !empty($pdfTestErgebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
                <?php endif; ?>
                <li>Header "Arbeitszeitliste" gefunden: <?php echo !empty($pdfTestErgebnis['header_arbeitszeitliste']) ? 'ja' : 'nein'; ?></li>
                <li>Header "Tag / KW" gefunden: <?php echo !empty($pdfTestErgebnis['header_tag_kw']) ? 'ja' : 'nein'; ?></li>
            </ul>

            <?php
            $kCheck = $pdfTestErgebnis['kommentar_check'] ?? [];
            $kHinweis = $pdfTestErgebnis['kommentar_hinweis'] ?? null;
            ?>

            <?php if (is_array($kCheck) && $kCheck !== []): ?>
                <hr>
                <p style="margin:0 0 6px 0;"><strong>Kommentar-Kürzel Check (optional):</strong></p>
                <ul style="margin:0;">
                    <?php foreach ($kCheck as $kc):
                        $dt = htmlspecialchars((string)($kc['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $kk = htmlspecialchars((string)($kc['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $fi = !empty($kc['found_in_pdf']);
                        ?>
                        <li><?php echo $dt; ?>: "<?php echo $kk; ?>" → <?php echo $fi ? 'im PDF gefunden' : 'nicht gefunden'; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif (is_string($kHinweis) && $kHinweis !== ''): ?>
                <hr>
                <p style="margin:0;"><em><?php echo htmlspecialchars($kHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em></p>
            <?php else: ?>
                <hr>
                <p style="margin:0;"><em>Keine Tageswerte-Kommentare im ausgewählten Monat gefunden (optional).</em></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    
    <h3>PDF-Synth-Check (Multi-Block + Multi-Page, DB-unabhängig)</h3>
    <p>
        Dieser Check erzeugt ein Monats-PDF <strong>aus synthetischen Daten</strong> (3 Arbeitsblöcke pro Tag) und prüft,
        ob der Mehrseiten-Umbruch funktioniert. Er erwartet <strong>mindestens 2 Seiten</strong>.
        Es wird keine DB gelesen/geschrieben. Optional kannst du das Synth-PDF als <strong>PDF im Browser öffnen</strong> (neuer Tab), um Viewer/Rendering zu testen.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="pdf_synth_run" value="1">
        <label for="pdf_synth_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="pdf_synth_jahr" name="pdf_synth_jahr" value="<?php echo (int)$pdfSynthJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="pdf_synth_monat"><strong>Monat</strong>:</label>
        <input type="number" id="pdf_synth_monat" name="pdf_synth_monat" value="<?php echo (int)$pdfSynthMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">Synth-PDF prüfen</button>
    </form>

    <p style="margin:0 0 12px 0;">
        Optional: <a href="?seite=smoke_test&amp;smoke_pdf=synth_multipage&amp;jahr=<?php echo (int)$pdfSynthJahr; ?>&amp;monat=<?php echo (int)$pdfSynthMonat; ?>" target="_blank" rel="noopener">Synth-PDF öffnen</a>
    </p>

    <?php if ($pdfSynthHinweis !== null && $pdfSynthHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($pdfSynthHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($pdfSynthErgebnis)):
        $ok = (bool)($pdfSynthErgebnis['ok'] ?? false);
        ?>
        <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Synth-PDF Ergebnis</p>
            <ul style="margin:0;">
                <li>Länge (Bytes): <?php echo (int)($pdfSynthErgebnis['bytes'] ?? 0); ?></li>
                <li>Tage im Monat: <?php echo (int)($pdfSynthErgebnis['days_in_month'] ?? 0); ?></li>
                <li>Blöcke pro Tag: <?php echo (int)($pdfSynthErgebnis['blocks_per_day'] ?? 0); ?></li>
                <li>Erwartete Zeilen (inkl. Header+"/"): <?php echo (int)($pdfSynthErgebnis['rows_expected'] ?? 0); ?></li>
                <li>Header "%PDF-" vorhanden: <?php echo !empty($pdfSynthErgebnis['header_ok']) ? 'ja' : 'nein'; ?></li>
                <li>"%%EOF" vorhanden: <?php echo !empty($pdfSynthErgebnis['eof_ok']) ? 'ja' : 'nein'; ?></li>
                <li>Seiten (/Pages /Count): <?php echo ($pdfSynthErgebnis['pages_count_declared'] ?? null) !== null ? (int)$pdfSynthErgebnis['pages_count_declared'] : 'n/a'; ?></li>
                <li>Seiten-Objekte (/Type /Page): <?php echo (int)($pdfSynthErgebnis['pages_count_objects'] ?? 0); ?></li>
                <li>Seitenanzahl konsistent: <?php echo ($pdfSynthErgebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($pdfSynthErgebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
                <li>Mind. 2 Seiten erkannt: <?php echo !empty($pdfSynthErgebnis['pages_at_least2']) ? 'ja' : 'nein'; ?></li>
                <li>Footer "Seite 1/" gefunden: <?php echo !empty($pdfSynthErgebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
                <li>Footer "Seite 2/" gefunden: <?php echo !empty($pdfSynthErgebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
                <li>Header "Arbeitszeitliste" gefunden: <?php echo !empty($pdfSynthErgebnis['header_arbeitszeitliste']) ? 'ja' : 'nein'; ?></li>
                <li>Header "Tag / KW" gefunden: <?php echo !empty($pdfSynthErgebnis['header_tag_kw']) ? 'ja' : 'nein'; ?></li>
            </ul>
        </div>
    <?php endif; ?>



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
        <input type="number" id="pdf_db_multipage_window" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>" style="width: 70px;">
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
    <input type="number" id="pdf_db_multipage_window_list" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>" style="width: 70px;">
    &nbsp;
    <label for="pdf_db_multipage_list_limit"><strong>Limit</strong>:</label>
    <input type="number" id="pdf_db_multipage_list_limit" name="pdf_db_multipage_list_limit" value="<?php echo (int)$pdfDbMultiListLimit; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Kandidatenliste anzeigen</button>
</form>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_db_multipage_list_eval" value="1">
    <?php echo Csrf::feld($csrfBereich); ?>
    <label for="pdf_db_multipage_window_list_eval"><strong>Suchfenster (Monate)</strong>:</label>
    <input type="number" id="pdf_db_multipage_window_list_eval" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>" style="width: 70px;">
    &nbsp;
    <label for="pdf_db_multipage_list_limit_eval"><strong>Limit</strong>:</label>
    <input type="number" id="pdf_db_multipage_list_limit_eval" name="pdf_db_multipage_list_limit" value="<?php echo (int)$pdfDbMultiListLimit; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Kandidatenliste automatisch prüfen (PDF)</button>
</form>

<?php if ($pdfDbMultiListHinweis !== null && $pdfDbMultiListHinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
<?php echo htmlspecialchars($pdfDbMultiListHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($pdfDbMultiListe) && $pdfDbMultiListe !== []): ?>
    <div style="padding:10px; background:#e3f2fd; border:1px solid #1565c0; margin-bottom: 12px;">
<p style="margin:0 0 8px 0;"><strong>Kandidatenliste</strong> (Top <?php echo (int)$pdfDbMultiListLimit; ?>, Fenster <?php echo (int)$pdfDbMultiWindowMonate; ?> Monate)</p>

<?php
    $hasEval = false;
    if (isset($pdfDbMultiListe[0]) && is_array($pdfDbMultiListe[0]) && array_key_exists('eval_ok', $pdfDbMultiListe[0])) {
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
    <?php foreach ($pdfDbMultiListe as $row): ?>
        <?php
            $mid = (int)($row['mitarbeiter_id'] ?? 0);
            $jahr = (int)($row['jahr'] ?? 0);
            $monat = (int)($row['monat'] ?? 0);
            $name = (string)($row['name'] ?? '');
            $kg = (int)($row['buchungen_kommen_gehen'] ?? 0);
            $maxc = (int)($row['max_day_buchungen'] ?? 0);
            $maxd = (string)($row['max_day_datum'] ?? '');
            $linkReport = (string)($row['link_report'] ?? '');
            $linkPdf = (string)($row['link_pdf'] ?? '');

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
                    <?php echo (int)$evalPages; ?>
                </td>
                <td style="padding: 6px; border-bottom: 1px solid #e3f2fd; text-align: right;">
                    <?php echo (int)$evalBytes; ?>
                </td>
            <?php endif; ?>

            <td style="padding: 6px; border-bottom: 1px solid #e3f2fd;">
                <form method="post" action="?seite=smoke_test" style="display:inline;">
                    <input type="hidden" name="pdf_db_multipage_run" value="1">
                    <?php echo Csrf::feld($csrfBereich); ?>
                    <input type="hidden" name="pdf_db_multipage_mid" value="<?php echo $mid; ?>">
                    <input type="hidden" name="pdf_db_multipage_year" value="<?php echo $jahr; ?>">
                    <input type="hidden" name="pdf_db_multipage_month" value="<?php echo $monat; ?>">
                    <input type="hidden" name="pdf_db_multipage_window" value="<?php echo (int)$pdfDbMultiWindowMonate; ?>">
                    <button type="submit">Prüfen</button>
                </form>
                <?php if ($linkReport !== ''): ?>
                    &nbsp;|&nbsp;<a href="<?php echo htmlspecialchars($linkReport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Report</a>
                <?php endif; ?>
                <?php if ($linkPdf !== ''): ?>
                    &nbsp;|&nbsp;<a href="<?php echo htmlspecialchars($linkPdf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">PDF</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
    </div>
<?php endif; ?>


    <?php if ($pdfDbMultiHinweis !== null && $pdfDbMultiHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($pdfDbMultiHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($pdfDbMultiErgebnis)):
        $ok = (bool)($pdfDbMultiErgebnis['ok'] ?? false);
        $linkReport = (string)($pdfDbMultiErgebnis['link_report'] ?? '');
        $linkPdf = (string)($pdfDbMultiErgebnis['link_pdf'] ?? '');
        ?>
        <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> PDF DB Auto-Multipage Ergebnis</p>
            <ul style="margin:0;">
                <li>Suchfenster: <?php echo (int)($pdfDbMultiErgebnis['window_monate'] ?? 0); ?> Monate</li>
                <li>Gefunden via: <?php echo htmlspecialchars((string)($pdfDbMultiErgebnis['gefunden_via'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <li>Mitarbeiter: #<?php echo (int)($pdfDbMultiErgebnis['mitarbeiter_id'] ?? 0); ?> (<?php echo htmlspecialchars((string)($pdfDbMultiErgebnis['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</li>
                <li>Monat: <?php echo (int)($pdfDbMultiErgebnis['monat'] ?? 0); ?>/<?php echo (int)($pdfDbMultiErgebnis['jahr'] ?? 0); ?></li>
                <li>Kommen/Gehen-Buchungen: <?php echo (int)($pdfDbMultiErgebnis['buchungen_kommen_gehen'] ?? 0); ?></li>
                <li>Max. Buchungen an einem Tag: <?php echo (int)($pdfDbMultiErgebnis['max_day_buchungen'] ?? 0); ?><?php echo ($pdfDbMultiErgebnis['max_day_datum'] ?? '') !== '' ? ' (' . htmlspecialchars((string)$pdfDbMultiErgebnis['max_day_datum'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : ''; ?></li>
                <li>PDF Bytes: <?php echo (int)($pdfDbMultiErgebnis['pdf_bytes'] ?? 0); ?></li>
                <li>Seiten (/Pages /Count): <?php echo ($pdfDbMultiErgebnis['pages_count_declared'] ?? null) !== null ? (int)$pdfDbMultiErgebnis['pages_count_declared'] : 'n/a'; ?></li>
                <li>Seiten-Objekte (/Type /Page): <?php echo (int)($pdfDbMultiErgebnis['pages_count_objects'] ?? 0); ?></li>
                <li>Mind. 2 Seiten erkannt: <?php echo !empty($pdfDbMultiErgebnis['pages_at_least2']) ? 'ja' : 'nein'; ?></li>
                <li>Seitenanzahl konsistent: <?php echo ($pdfDbMultiErgebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($pdfDbMultiErgebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
                <li>Footer "Seite 1/" gefunden: <?php echo !empty($pdfDbMultiErgebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
                <li>Footer "Seite 2/" gefunden: <?php echo !empty($pdfDbMultiErgebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
                <li>
                    HTML-Render-Check: 
                    <?php
                        $hOk = $pdfDbMultiErgebnis['html_ok'] ?? null;
                        if ($hOk === null) {
                            echo 'SKIP';
                        } else {
                            echo !empty($hOk) ? 'OK' : 'FAIL';
                        }
                    ?>
                    <?php if (!empty($pdfDbMultiErgebnis['html_hinweis'])): ?>
                        &nbsp;<small>(<?php echo htmlspecialchars((string)$pdfDbMultiErgebnis['html_hinweis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
                    <?php endif; ?>
                </li>
                <li>HTML: Heading "Monatsübersicht": <?php echo !empty($pdfDbMultiErgebnis['html_has_heading']) ? 'ja' : 'nein'; ?>, Tabelle: <?php echo !empty($pdfDbMultiErgebnis['html_has_table']) ? 'ja' : 'nein'; ?>, Headerzellen (Datum/An/Ab): <?php echo !empty($pdfDbMultiErgebnis['html_has_header_cells']) ? 'ja' : 'nein'; ?>, PDF-Link: <?php echo !empty($pdfDbMultiErgebnis['html_has_pdf_link']) ? 'ja' : 'nein'; ?></li>
                <li>HTML: &lt;tr&gt; Count: <?php echo (int)($pdfDbMultiErgebnis['html_tr_count'] ?? 0); ?> (Tage im Monat: <?php echo (int)($pdfDbMultiErgebnis['html_days_in_month'] ?? 0); ?>, Mindest-OK: <?php echo ($pdfDbMultiErgebnis['html_rows_min_ok'] ?? null) === null ? 'n/a' : (!empty($pdfDbMultiErgebnis['html_rows_min_ok']) ? 'ja' : 'nein'); ?>)</li>
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

<h3>Feiertag-Quick-Check (Monatsreport)</h3>
    <p>
        Dieser Check prüft, ob ein konkretes Datum im Monatsreport als <strong>Feiertag</strong> erkannt wird
        und (wenn <strong>keine</strong> Arbeitszeit vorhanden ist) die <strong>Sollstunden</strong> im Feld <strong>Feiertag</strong> landen.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="feiertag_test_run" value="1">
        <label for="feiertag_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong> (optional):</label>
        <input type="number" id="feiertag_test_mitarbeiter_id" name="feiertag_test_mitarbeiter_id"
               value="<?php echo (int)$feiertagTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="feiertag_test_datum"><strong>Datum</strong> (YYYY-MM-DD):</label>
        <input type="text" id="feiertag_test_datum" name="feiertag_test_datum"
               value="<?php echo htmlspecialchars($feiertagTestDatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
               style="width: 140px;">
        &nbsp;
        <button type="submit">Feiertag prüfen</button>
    </form>

    <?php if ($feiertagTestHinweis !== null && $feiertagTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($feiertagTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($feiertagTestErgebnis)):
        $ok = $feiertagTestErgebnis['ok'] ?? null;
        $isOk = ($ok === true);
        $isFail = ($ok === false);
        $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
        $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
        $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Feiertag-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Datum: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <li>Wochentag (1=Mo..7=So): <?php echo (int)($feiertagTestErgebnis['wochentag'] ?? 0); ?></li>
                <li>FeiertagService sagt: <?php
                    $v = $feiertagTestErgebnis['ist_feiertag'] ?? null;
                    echo ($v === true) ? 'ja' : (($v === false) ? 'nein' : 'unbekannt');
                ?></li>
                <li>Kennzeichen Feiertag: <?php echo (int)($feiertagTestErgebnis['kennzeichen_feiertag'] ?? 0); ?></li>
                <li>Arbeitszeit (Ist): <?php echo htmlspecialchars((string)($feiertagTestErgebnis['arbeitszeit_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</li>
                <li>Feiertag-Stunden: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['feiertag_stunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</li>
                <li>Tagestyp: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['tagestyp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <li>Kürzel/Kommentar: <?php echo htmlspecialchars((string)($feiertagTestErgebnis['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            </ul>
            <hr>
            <p style="margin:0;"><em><?php echo htmlspecialchars((string)($feiertagTestErgebnis['erwartung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em></p>
        </div>
    <?php endif; ?>

    <h3>Monatsreport-Raster-Check</h3>
    <p>
        Dieser Check prüft, ob der Monatsreport wirklich ein <strong>vollständiges Monatsraster</strong> liefert:
        <strong>genau ein</strong> Tageswert pro Kalendertag (also z. B. 31 Zeilen im Januar).
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="monatsraster_test_run" value="1">
        <label for="monatsraster_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
        <input type="number" id="monatsraster_test_mitarbeiter_id" name="monatsraster_test_mitarbeiter_id"
               value="<?php echo (int)$monatsrasterTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="monatsraster_test_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="monatsraster_test_jahr" name="monatsraster_test_jahr"
               value="<?php echo (int)$monatsrasterTestJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="monatsraster_test_monat"><strong>Monat</strong>:</label>
        <input type="number" id="monatsraster_test_monat" name="monatsraster_test_monat"
               value="<?php echo (int)$monatsrasterTestMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">Raster prüfen</button>
    </form>

    <?php if ($monatsrasterTestHinweis !== null && $monatsrasterTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($monatsrasterTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($monatsrasterTestErgebnis)):
        $ok = (bool)($monatsrasterTestErgebnis['ok'] ?? false);
        $bg = $ok ? '#e8f5e9' : '#ffebee';
        $bd = $ok ? '#2e7d32' : '#c62828';
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Monatsraster-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Mitarbeiter-ID: <?php echo (int)($monatsrasterTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                <li>Monat: <?php echo sprintf('%04d-%02d', (int)($monatsrasterTestErgebnis['jahr'] ?? 0), (int)($monatsrasterTestErgebnis['monat'] ?? 0)); ?></li>
                <li>Tage im Monat: <?php echo (int)($monatsrasterTestErgebnis['tage_im_monat'] ?? 0); ?></li>
                <li>Tageswerte (Report): <?php echo (int)($monatsrasterTestErgebnis['tageswerte_count'] ?? 0); ?></li>
            </ul>

            <?php
            $miss = $monatsrasterTestErgebnis['missing'] ?? [];
            $dup  = $monatsrasterTestErgebnis['duplicates'] ?? [];
            $inv  = $monatsrasterTestErgebnis['invalid'] ?? [];
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
               value="<?php echo (int)$monatsfallbackTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="monatsfallback_test_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="monatsfallback_test_jahr" name="monatsfallback_test_jahr"
               value="<?php echo (int)$monatsfallbackTestJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="monatsfallback_test_monat"><strong>Monat</strong>:</label>
        <input type="number" id="monatsfallback_test_monat" name="monatsfallback_test_monat"
               value="<?php echo (int)$monatsfallbackTestMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">Fallback prüfen</button>
    </form>

    <?php if ($monatsfallbackTestHinweis !== null && $monatsfallbackTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($monatsfallbackTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($monatsfallbackTestErgebnis)):
        $ok = (bool)($monatsfallbackTestErgebnis['ok'] ?? false);
        $bg = $ok ? '#e8f5e9' : '#ffebee';
        $bd = $ok ? '#2e7d32' : '#c62828';
        $missingCount = (int)($monatsfallbackTestErgebnis['missing_days_count'] ?? 0);
        $notCoveredCount = (int)($monatsfallbackTestErgebnis['not_covered_count'] ?? 0);
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Monatsreport-Fallback-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Mitarbeiter-ID: <?php echo (int)($monatsfallbackTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                <li>Monat: <?php echo sprintf('%04d-%02d', (int)($monatsfallbackTestErgebnis['jahr'] ?? 0), (int)($monatsfallbackTestErgebnis['monat'] ?? 0)); ?></li>
                <li>Tage mit Buchungen: <?php echo (int)($monatsfallbackTestErgebnis['booked_days_count'] ?? 0); ?></li>
                <li>Tage mit Tageswerten (DB): <?php echo (int)($monatsfallbackTestErgebnis['tageswerte_days_count'] ?? 0); ?></li>
                <li>Tage mit Buchungen aber ohne Tageswerte: <?php echo $missingCount; ?></li>
                <li>Davon im Report nicht sinnvoll befüllt: <?php echo $notCoveredCount; ?></li>
            </ul>

            <?php
            $miss = $monatsfallbackTestErgebnis['missing_days_sample'] ?? [];
            $nc = $monatsfallbackTestErgebnis['not_covered_sample'] ?? [];
            $samples = $monatsfallbackTestErgebnis['samples'] ?? [];
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
               value="<?php echo (int)$doppelzaehlungTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="doppelzaehlung_test_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="doppelzaehlung_test_jahr" name="doppelzaehlung_test_jahr"
               value="<?php echo (int)$doppelzaehlungTestJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="doppelzaehlung_test_monat"><strong>Monat</strong>:</label>
        <input type="number" id="doppelzaehlung_test_monat" name="doppelzaehlung_test_monat"
               value="<?php echo (int)$doppelzaehlungTestMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">Doppelzählung prüfen</button>
    </form>

    <?php if ($doppelzaehlungTestHinweis !== null && $doppelzaehlungTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($doppelzaehlungTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($doppelzaehlungTestErgebnis)):
        $ok = $doppelzaehlungTestErgebnis['ok'] ?? null;
        $isOk = ($ok === true);
        $isFail = ($ok === false);
        $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
        $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
        $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
        $issues = $doppelzaehlungTestErgebnis['issues'] ?? [];
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Doppelzählung-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Mitarbeiter-ID: <?php echo (int)($doppelzaehlungTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                <li>Monat: <?php echo sprintf('%04d-%02d', (int)($doppelzaehlungTestErgebnis['jahr'] ?? 0), (int)($doppelzaehlungTestErgebnis['monat'] ?? 0)); ?></li>
                <li>Betriebsferien-Tage im Report: <?php echo (int)($doppelzaehlungTestErgebnis['betriebsferien_tage'] ?? 0); ?></li>
                <li>Kurzarbeit-Volltag-Tage im Report: <?php echo (int)($doppelzaehlungTestErgebnis['kurzarbeit_volltag_tage'] ?? 0); ?></li>
                <li>Auffälligkeiten: <?php echo (int)($doppelzaehlungTestErgebnis['issues_count'] ?? 0); ?></li>
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


    <h3>Feiertag+Arbeitszeit-Check (Monat)</h3>
    <p>
        Dieser Check findet Konflikte, bei denen an einem Feiertag sowohl <em>Arbeitszeit</em> als auch
        <em>Feiertagsstunden</em> gesetzt sind. Das würde im Monatsreport zu einer Doppelzählung führen.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="feiertag_arbeitszeit_test_run" value="1">
        <label for="feiertag_arbeitszeit_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
        <input type="number" id="feiertag_arbeitszeit_test_mitarbeiter_id" name="feiertag_arbeitszeit_test_mitarbeiter_id"
               value="<?php echo (int)$feiertagArbeitszeitTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="feiertag_arbeitszeit_test_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="feiertag_arbeitszeit_test_jahr" name="feiertag_arbeitszeit_test_jahr"
               value="<?php echo (int)$feiertagArbeitszeitTestJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="feiertag_arbeitszeit_test_monat"><strong>Monat</strong>:</label>
        <input type="number" id="feiertag_arbeitszeit_test_monat" name="feiertag_arbeitszeit_test_monat"
               value="<?php echo (int)$feiertagArbeitszeitTestMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">Konflikte prüfen</button>
    </form>

    <?php if ($feiertagArbeitszeitTestHinweis !== null && $feiertagArbeitszeitTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($feiertagArbeitszeitTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($feiertagArbeitszeitTestErgebnis)):
        $ok = $feiertagArbeitszeitTestErgebnis['ok'] ?? null;
        $isOk = ($ok === true);
        $isFail = ($ok === false);
        $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
        $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
        $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
        $issues = $feiertagArbeitszeitTestErgebnis['issues'] ?? [];
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> Feiertag+Arbeitszeit-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Mitarbeiter-ID: <?php echo (int)($feiertagArbeitszeitTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                <li>Monat: <?php echo sprintf('%04d-%02d', (int)($feiertagArbeitszeitTestErgebnis['jahr'] ?? 0), (int)($feiertagArbeitszeitTestErgebnis['monat'] ?? 0)); ?></li>
                <li>Feiertag-Tage im Report: <?php echo (int)($feiertagArbeitszeitTestErgebnis['feiertag_tage'] ?? 0); ?></li>
                <li>Konflikte: <?php echo (int)($feiertagArbeitszeitTestErgebnis['issues_count'] ?? 0); ?></li>
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


    <h3>Kommen/Gehen-Sequenz-Check (Monat)</h3>
    <p>
        Dieser Check analysiert die Reihenfolge der Zeitbuchungen (kommen/gehen) pro Tag.
        Er findet auffällige Tage wie z.B. <em>gehen ohne kommen</em>, <em>doppelte Typen</em> oder <em>offene Arbeitsblöcke</em>.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="buchungssequenz_test_run" value="1">
        <label for="buchungssequenz_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong>:</label>
        <input type="number" id="buchungssequenz_test_mitarbeiter_id" name="buchungssequenz_test_mitarbeiter_id"
               value="<?php echo (int)$buchungssequenzTestMitarbeiterId; ?>" style="width: 110px;">
        &nbsp;
        <label for="buchungssequenz_test_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="buchungssequenz_test_jahr" name="buchungssequenz_test_jahr"
               value="<?php echo (int)$buchungssequenzTestJahr; ?>" style="width: 90px;">
        &nbsp;
        <label for="buchungssequenz_test_monat"><strong>Monat</strong>:</label>
        <input type="number" id="buchungssequenz_test_monat" name="buchungssequenz_test_monat"
               value="<?php echo (int)$buchungssequenzTestMonat; ?>" style="width: 70px;">
        &nbsp;
        <button type="submit">Sequenz prüfen</button>
    </form>

    <?php if ($buchungssequenzTestHinweis !== null && $buchungssequenzTestHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($buchungssequenzTestHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($buchungssequenzTestErgebnis)):
        $ok = (bool)($buchungssequenzTestErgebnis['ok'] ?? false);
        $bg = $ok ? '#e8f5e9' : '#ffebee';
        $bd = $ok ? '#2e7d32' : '#c62828';
        $auffaellig = $buchungssequenzTestErgebnis['auffaellig_sample'] ?? [];
        $mehrblock = $buchungssequenzTestErgebnis['mehrblock_sample'] ?? [];
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'HINWEIS'; ?>:</strong> Kommen/Gehen-Sequenz-Check Ergebnis</p>
            <ul style="margin:0;">
                <li>Mitarbeiter-ID: <?php echo (int)($buchungssequenzTestErgebnis['mitarbeiter_id'] ?? 0); ?></li>
                <li>Monat: <?php echo sprintf('%04d-%02d', (int)($buchungssequenzTestErgebnis['jahr'] ?? 0), (int)($buchungssequenzTestErgebnis['monat'] ?? 0)); ?></li>
                <li>Tage mit Buchungen: <?php echo (int)($buchungssequenzTestErgebnis['tage_mit_buchungen'] ?? 0); ?></li>
                <li>Auffällige Tage: <?php echo (int)($buchungssequenzTestErgebnis['tage_auffaellig'] ?? 0); ?></li>
                <li>Tage mit mehreren Arbeitsblöcken: <?php echo (int)($buchungssequenzTestErgebnis['tage_mehrblock'] ?? 0); ?></li>
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

    <?php if (is_array($terminalLoginErgebnis)):
        $typ = $terminalLoginErgebnis['typ'] ?? null;
        $m = $terminalLoginErgebnis['mitarbeiter'] ?? null;
        $warn = $terminalLoginErgebnis['warnungen'] ?? [];
        ?>
        <div style="padding:10px; background:<?php echo $typ ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $typ ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
            <?php if ($typ && is_array($m)): ?>
                <p style="margin:0 0 6px 0;"><strong>OK:</strong> Terminal würde den Mitarbeiter per <strong><?php echo htmlspecialchars((string)$typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> einloggen.</p>
                <ul style="margin:0;">
                    <li>ID: <?php echo (int)($m['id'] ?? 0); ?></li>
                    <li>Name: <?php echo htmlspecialchars(trim((string)($m['vorname'] ?? '') . ' ' . (string)($m['nachname'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                    <li>Personalnummer: <?php echo htmlspecialchars((string)($m['personalnummer'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                    <li>RFID: <?php echo htmlspecialchars((string)($m['rfid_code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                </ul>

                <?php
                $a = $terminalLoginErgebnis['anwesenheit'] ?? null;
                if (is_array($a) && isset($a['kommen']) && isset($a['gehen'])):
                    $isA = (bool)($a['ist_anwesend'] ?? false);
                    $k = (int)($a['kommen'] ?? 0);
                    $g = (int)($a['gehen'] ?? 0);
                    ?>
                    <hr>
                    <p style="margin:0 0 6px 0;"><strong>Anwesenheit heute (<?php echo htmlspecialchars((string)($a['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>):</strong>
                        Kommen <?php echo $k; ?> / Gehen <?php echo $g; ?>
                        &nbsp;→&nbsp;
                        <strong><?php echo $isA ? 'ANWESEND' : 'NICHT anwesend'; ?></strong>
                    </p>
                    <p style="margin:0 0 6px 0;">
                        Terminal-Menü sollte entsprechend
                        <strong><?php echo $isA ? 'Gehen + Aufträge (+ Urlaub)' : 'nur Kommen (+ Urlaub)'; ?></strong>
                        anzeigen.
                    </p>


                    <?php
                    $kommenErlaubt = (bool)($a['kommen_erlaubt'] ?? (!$isA));
                    $gehenErlaubt = (bool)($a['gehen_erlaubt'] ?? $isA);
                    $auftragErlaubt = (bool)($a['auftrag_erlaubt'] ?? $isA);
                    ?>
                    <p style="margin:0 0 6px 0;">
                        <strong>Erlaubt (online-Check):</strong>
                        Kommen <?php echo $kommenErlaubt ? 'JA' : 'NEIN'; ?>,
                        Gehen <?php echo $gehenErlaubt ? 'JA' : 'NEIN'; ?>,
                        Auftrag-Start <?php echo $auftragErlaubt ? 'JA' : 'NEIN'; ?>
                    </p>
                    <?php if (is_array($a['letzte_buchung'] ?? null) && isset($a['letzte_buchung']['typ'])):
                        $lb = $a['letzte_buchung'];
                        ?>
                        <p style="margin:0;">
                            Letzte Buchung: <strong><?php echo htmlspecialchars((string)($lb['typ'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            um <strong><?php echo htmlspecialchars((string)($lb['zeitstempel'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            (Quelle: <?php echo htmlspecialchars((string)($lb['quelle'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                        </p>
                    <?php endif; ?>

                <?php elseif (is_array($a) && isset($a['fehler'])): ?>
                    <hr>
                    <p style="margin:0;"><strong>Anwesenheit-Check:</strong> Fehler: <?php echo htmlspecialchars((string)$a['fehler'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php endif; ?>

            <?php else:
                $fc = (string)($terminalLoginErgebnis['fehler_code'] ?? '');
                if ($fc === 'MEHRDEUTIG'):
                    ?>
                    <p style="margin:0;"><strong>BLOCK:</strong> Mehrdeutiger numerischer Code – Terminal würde den Login abbrechen.</p>
                <?php else: ?>
                    <p style="margin:0;"><strong>FAIL:</strong> Kein aktiver Mitarbeiter für diesen Code gefunden.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            $alts = $terminalLoginErgebnis['alternativen'] ?? [];
            if (is_array($alts) && count($alts) > 0):
                ?>
                <hr>
                <p style="margin:0 0 6px 0;"><strong>Alternative Treffer (Mehrdeutigkeits-Check):</strong></p>
                <ul style="margin:0;">
                    <?php foreach ($alts as $t => $rowAlt):
                        $altId = (int)($rowAlt['id'] ?? 0);
                        $altName = trim((string)($rowAlt['vorname'] ?? '') . ' ' . (string)($rowAlt['nachname'] ?? ''));
                        $altLine = (string)$t . ': ID ' . $altId . ($altName !== '' ? ' (' . $altName . ')' : '');
                        ?>
                        <li><?php echo htmlspecialchars($altLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (is_array($warn) && count($warn) > 0): ?>
                <hr>
                <p style="margin:0 0 6px 0;"><strong>Hinweise:</strong></p>
                <ul style="margin:0;">
                    <?php foreach ($warn as $w): ?>
                        <li><?php echo htmlspecialchars((string)$w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>


    <?php endif; ?>


    
    <h3>Terminal-Konfiguration (Config-Keys)</h3>
    <p>
        Das Terminal liest bestimmte Einstellungen aus der Tabelle <code>config</code>.
        Wenn ein Key fehlt oder ein ungültiger Wert gespeichert ist, wird im Terminal automatisch auf Default-Werte zurückgefallen.
    </p>

    <?php if (is_string($terminalConfigHinweis) && $terminalConfigHinweis !== ''): ?>
        <div style="padding:10px; background:#ffebee; border:1px solid #c62828; margin-bottom: 12px;">
            <?php echo htmlspecialchars($terminalConfigHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($terminalConfig) && count($terminalConfig) > 0): ?>
        <table style="margin-bottom: 16px;">
            <thead>
            <tr>
                <th>Key</th>
                <th>Beschreibung</th>
                <th>DB-Wert</th>
                <th>Effektiv</th>
                <th>Status</th>
                <th>Range</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($terminalConfig as $row):
                if (!is_array($row)) { continue; }
                $st = (string)($row['status'] ?? 'default');
                $statusText = ($st === 'ok') ? 'OK' : (($st === 'invalid') ? 'INVALID → Default' : 'Nicht gesetzt → Default');
                $style = ($st === 'ok') ? 'background:#e8f5e9;' : (($st === 'invalid') ? 'background:#ffebee;' : 'background:#fffde7;');
                $raw = $row['raw'] ?? null;
                $rawText = ($raw === null || trim((string)$raw) === '') ? '—' : (string)$raw;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($row['key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['titel'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><strong><?php echo (int)($row['effective'] ?? 0); ?></strong> s</td>
                    <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo (int)($row['min'] ?? 0); ?>–<?php echo (int)($row['max'] ?? 0); ?> s (Default: <?php echo (int)($row['default'] ?? 0); ?> s)</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:-6px;">
            <a href="?seite=konfiguration_admin">Konfiguration öffnen</a>
        </p>
    <?php else: ?>
        <p><em>Keine Terminal-Konfigdaten verfügbar (DB nicht erreichbar oder Tabelle <code>config</code> fehlt).</em></p>
    <?php endif; ?>

    <h3>Offline-Queue (db_injektionsqueue)</h3>
    <p>
        Zeigt den Status der Offline-Queue (rein lesend). Wenn „offen“ oder „fehler“ vorhanden sind,
        muss die Queue im Backend verarbeitet bzw. geprüft werden.
    </p>

    <?php if (is_string($smokeFlash) && $smokeFlash !== ''): ?>
        <div style="padding:10px; background:#e3f2fd; border:1px solid #1565c0; margin-bottom: 12px;">
            <?php echo htmlspecialchars($smokeFlash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_string($queueHinweis) && $queueHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($queueHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($queueUebersicht) && is_array($queueUebersicht['counts'] ?? null)):
        $qQuelle = (string)($queueUebersicht['quelle'] ?? '');
        $qQuelleLabel = ($qQuelle === 'offline') ? 'Offline-DB' : 'Haupt-DB';
        $qc = (array)$queueUebersicht['counts'];
        $qFehler = (int)($qc['fehler'] ?? 0);
        $qOffen = (int)($qc['offen'] ?? 0);
        $qVerarb = (int)($qc['verarbeitet'] ?? 0);
        $qGes = (int)($qc['gesamt'] ?? ($qFehler + $qOffen + $qVerarb));
        $qOk = ($qFehler === 0);
        ?>
        <div style="padding:10px; background:<?php echo $qOk ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $qOk ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;">
                <strong><?php echo $qOk ? 'OK' : 'HINWEIS'; ?>:</strong>
                Queue-DB: <?php echo htmlspecialchars($qQuelleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> – Queue-Einträge: Gesamt <?php echo $qGes; ?> – Offen <?php echo $qOffen; ?> – Fehler <?php echo $qFehler; ?> – Verarbeitet <?php echo $qVerarb; ?>
            </p>

            <form method="post" action="?seite=smoke_test" style="margin:0 0 8px 0;">
                <?php echo Csrf::feld($csrfBereich); ?>
                <input type="hidden" name="action" value="queue_roundtrip">
                <button type="submit" style="padding:6px 10px; border:1px solid #1565c0; background:#e3f2fd; cursor:pointer;">
                    Queue-Roundtrip testen (DO 1)
                </button>
                <span class="status-small" style="margin-left:8px;">Erzeugt einen harmlosen Testeintrag in der Queue und stößt die Verarbeitung an.</span>
            </form>

            <?php $latest = $queueUebersicht['latest'] ?? []; ?>
            <?php if (is_array($latest) && count($latest) > 0): ?>
                <details>
                    <summary class="status-small"><strong>Letzte 10 Queue-Einträge anzeigen</strong></summary>
                    <table style="margin-top:8px;">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Aktion</th>
                            <th>Mitarbeiter</th>
                            <th>Terminal</th>
                            <th>Erstellt</th>
                            <th>Letzte Ausführung</th>
                            <th>Versuche</th>
                            <th>Fehler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($latest as $r):
                            if (!is_array($r)) { continue; }
                            ?>
                            <tr>
                                <td><?php echo (int)($r['id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['meta_aktion'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['meta_mitarbeiter_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['meta_terminal_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['erstellt_am'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['letzte_ausfuehrung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo (int)($r['versuche'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['fehlernachricht_kurz'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php else: ?>
                <p style="margin:0;"><em>Keine Queue-Einträge vorhanden.</em></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p><em>Keine Queue-Daten verfügbar (DB nicht erreichbar oder Tabelle fehlt).</em></p>
    <?php endif; ?>


    <table>
        <thead>
        <tr>
            <th>Gruppe</th>
            <th>Check</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $c):
            $ok = $c['ok'];
            $status = ($ok === true) ? 'OK' : (($ok === false) ? 'FEHLT/FAIL' : 'n/a');
            $style = ($ok === true) ? 'background:#e8f5e9;' : (($ok === false) ? 'background:#ffebee;' : 'background:#fffde7;');
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$c['gruppe'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$c['titel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$c['details'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Feiertag-Seed-Check (bundesweit)</h3>
    <p>
        Dieser Check prüft, ob die <strong>bundeseinheitliche Grundmenge</strong> für ein Jahr in der Tabelle <code>feiertag</code>
        vorhanden ist. Fehlende Einträge werden dabei (wie im Livebetrieb) <strong>idempotent</strong> nachgezogen.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <input type="hidden" name="feiertag_seed_run" value="1">
        <label for="feiertag_seed_jahr"><strong>Jahr</strong>:</label>
        <input type="number" id="feiertag_seed_jahr" name="feiertag_seed_jahr"
               value="<?php echo (int)$feiertagSeedJahr; ?>" style="width: 110px;">
        <button type="submit">Prüfen</button>
    </form>

    <?php if (is_string($feiertagSeedHinweis) && $feiertagSeedHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($feiertagSeedHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($feiertagSeedErgebnis)):
        $ok = $feiertagSeedErgebnis['ok'] ?? null;
        $isOk = ($ok === true);
        $isFail = ($ok === false);
        $bg = $isOk ? '#e8f5e9' : ($isFail ? '#ffebee' : '#fffde7');
        $bd = $isOk ? '#2e7d32' : ($isFail ? '#c62828' : '#fbc02d');
        $label = $isOk ? 'OK' : ($isFail ? 'FAIL' : 'HINWEIS');
        $missing = $feiertagSeedErgebnis['missing'] ?? [];
        $extra = $feiertagSeedErgebnis['extra'] ?? [];
        ?>
        <div style="padding:10px; background:<?php echo $bg; ?>; border:1px solid <?php echo $bd; ?>; margin-bottom: 12px;">
            <p style="margin:0 0 6px 0;"><strong><?php echo $label; ?>:</strong> <?php echo htmlspecialchars((string)($feiertagSeedErgebnis['hinweis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            <ul style="margin:0 0 6px 0;">
                <li>Jahr: <?php echo (int)($feiertagSeedErgebnis['jahr'] ?? 0); ?></li>
                <li>Erwartet: <?php echo (int)($feiertagSeedErgebnis['erwartet'] ?? 0); ?></li>
                <li>Vorhanden: <?php echo (int)($feiertagSeedErgebnis['vorhanden'] ?? 0); ?></li>
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

    <h3>Manuelle Klick-Checkliste (danach Bugfixes ableiten)</h3>
    <ul>
        <li><strong>Backend Login</strong>: Einloggen, Dashboard öffnen.</li>
        <li><strong>Terminal Health</strong>: <code>terminal.php?aktion=health</code> (JSON) aufrufen.</li>
        <li><strong>Terminal Kommen/Gehen</strong>: einmal stempeln (online), dann DB kurz weg und erneut (Offline-Queue), danach Queue im Backend verarbeiten.</li>
        <li><strong>Auftrag Start/Stop</strong>: Hauptauftrag starten/stoppen (online + offline).</li>
        <li><strong>Monatsreport</strong>: Monatsübersicht öffnen, PDF erzeugen, ggf. Sammel-Export.</li>
        <li><strong>PDF-Quick-Check</strong>: Hier im Smoke-Test Jahr/Monat wählen und prüfen (Header/EOF).</li>
    </ul>
</section>
<?php
require __DIR__ . '/../layout/footer.php';
