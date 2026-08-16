<?php
declare(strict_types=1);
/**
 * Teil-Template: PDF-Synth-Check (Multi-Block + Multi-Page, DB-unabhängig).
 *
 * Erwartet **einen** Wert, `$pdfSynthDaten` – das Bündel von
 * `SmokeTestController::pruefePdfSynth()`:
 * - `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $pdfSynthDaten */
$jahr     = (int)($pdfSynthDaten['jahr'] ?? 0);
$monat    = (int)($pdfSynthDaten['monat'] ?? 0);
$ergebnis = $pdfSynthDaten['ergebnis'] ?? null;
$hinweis  = $pdfSynthDaten['hinweis'] ?? null;
?>
<h3>PDF-Synth-Check (Multi-Block + Multi-Page, DB-unabhängig)</h3>
<p>
    Dieser Check erzeugt ein Monats-PDF <strong>aus synthetischen Daten</strong> (3 Arbeitsblöcke pro Tag) und prüft,
    ob der Mehrseiten-Umbruch funktioniert. Er erwartet <strong>mindestens 2 Seiten</strong>.
    Es wird keine DB gelesen/geschrieben. Optional kannst du das Synth-PDF als <strong>PDF im Browser öffnen</strong> (neuer Tab), um Viewer/Rendering zu testen.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_synth_run" value="1">
    <label for="pdf_synth_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="pdf_synth_jahr" name="pdf_synth_jahr" value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="pdf_synth_monat"><strong>Monat</strong>:</label>
    <input type="number" id="pdf_synth_monat" name="pdf_synth_monat" value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">Synth-PDF prüfen</button>
</form>

<p style="margin:0 0 12px 0;">
    Optional: <a href="?seite=smoke_test&amp;smoke_pdf=synth_multipage&amp;jahr=<?php echo $jahr; ?>&amp;monat=<?php echo $monat; ?>" target="_blank" rel="noopener">Synth-PDF öffnen</a>
</p>

<?php if ($hinweis !== null && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($ergebnis)):
    $ok = (bool)($ergebnis['ok'] ?? false);
    ?>
    <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> Synth-PDF Ergebnis</p>
        <ul style="margin:0;">
            <li>Länge (Bytes): <?php echo (int)($ergebnis['bytes'] ?? 0); ?></li>
            <li>Tage im Monat: <?php echo (int)($ergebnis['days_in_month'] ?? 0); ?></li>
            <li>Blöcke pro Tag: <?php echo (int)($ergebnis['blocks_per_day'] ?? 0); ?></li>
            <li>Erwartete Zeilen (inkl. Header+"/"): <?php echo (int)($ergebnis['rows_expected'] ?? 0); ?></li>
            <li>Header "%PDF-" vorhanden: <?php echo !empty($ergebnis['header_ok']) ? 'ja' : 'nein'; ?></li>
            <li>"%%EOF" vorhanden: <?php echo !empty($ergebnis['eof_ok']) ? 'ja' : 'nein'; ?></li>
            <li>Seiten (/Pages /Count): <?php echo ($ergebnis['pages_count_declared'] ?? null) !== null ? (int)$ergebnis['pages_count_declared'] : 'n/a'; ?></li>
            <li>Seiten-Objekte (/Type /Page): <?php echo (int)($ergebnis['pages_count_objects'] ?? 0); ?></li>
            <li>Seitenanzahl konsistent: <?php echo ($ergebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($ergebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
            <li>Mind. 2 Seiten erkannt: <?php echo !empty($ergebnis['pages_at_least2']) ? 'ja' : 'nein'; ?></li>
            <li>Footer "Seite 1/" gefunden: <?php echo !empty($ergebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
            <li>Footer "Seite 2/" gefunden: <?php echo !empty($ergebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
            <li>Header "Arbeitszeitliste" gefunden: <?php echo !empty($ergebnis['header_arbeitszeitliste']) ? 'ja' : 'nein'; ?></li>
            <li>Header "Tag / KW" gefunden: <?php echo !empty($ergebnis['header_tag_kw']) ? 'ja' : 'nein'; ?></li>
        </ul>
    </div>
<?php endif; ?>
