<?php
declare(strict_types=1);
/**
 * Teil-Template: PDF-Quick-Check (Header/EOF/Seiten, ohne Download).
 *
 * Erwartet **einen** Wert, `$pdfQuickDaten` – das Bündel von
 * `SmokeTestController::pruefePdfQuick()`:
 * - `mitarbeiter_id`, `jahr`, `monat` (int) – die Formularwerte des letzten Laufs
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $pdfQuickDaten */
$mitarbeiterId = (int)($pdfQuickDaten['mitarbeiter_id'] ?? 0);
$jahr          = (int)($pdfQuickDaten['jahr'] ?? 0);
$monat         = (int)($pdfQuickDaten['monat'] ?? 0);
$ergebnis      = $pdfQuickDaten['ergebnis'] ?? null;
$hinweis       = $pdfQuickDaten['hinweis'] ?? null;
?>
<h3>PDF-Quick-Check (Header/EOF/Seiten, ohne Download)</h3>
<p>
    Dieser Check erzeugt das Monats-PDF <strong>im Speicher</strong> und prüft nur, ob das Ergebnis wie ein valides PDF aussieht.
    Es wird <strong>nichts</strong> als PDF ausgeliefert.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <input type="hidden" name="pdf_test_run" value="1">
    <label for="pdf_test_mitarbeiter_id"><strong>Mitarbeiter-ID</strong> (optional):</label>
    <input type="number" id="pdf_test_mitarbeiter_id" name="pdf_test_mitarbeiter_id"
           value="<?php echo $mitarbeiterId; ?>" style="width: 110px;">
    &nbsp;
    <label for="pdf_test_jahr"><strong>Jahr</strong>:</label>
    <input type="number" id="pdf_test_jahr" name="pdf_test_jahr" value="<?php echo $jahr; ?>" style="width: 90px;">
    &nbsp;
    <label for="pdf_test_monat"><strong>Monat</strong>:</label>
    <input type="number" id="pdf_test_monat" name="pdf_test_monat" value="<?php echo $monat; ?>" style="width: 70px;">
    &nbsp;
    <button type="submit">PDF prüfen</button>
</form>

<?php if ($hinweis !== null && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($ergebnis)):
    $ok = (bool)($ergebnis['ok'] ?? false);
    ?>
    <div style="padding:10px; background:<?php echo $ok ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $ok ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;"><strong><?php echo $ok ? 'OK' : 'FAIL'; ?>:</strong> PDF-Check Ergebnis</p>
        <ul style="margin:0;">
            <li>Länge (Bytes): <?php echo (int)($ergebnis['bytes'] ?? 0); ?></li>
            <li>Header "%PDF-" vorhanden: <?php echo !empty($ergebnis['header_ok']) ? 'ja' : 'nein'; ?></li>
            <li>"%%EOF" vorhanden: <?php echo !empty($ergebnis['eof_ok']) ? 'ja' : 'nein'; ?></li>
            <li>Seiten (/Pages /Count): <?php echo ($ergebnis['pages_count_declared'] ?? null) !== null ? (int)$ergebnis['pages_count_declared'] : 'n/a'; ?></li>
            <li>Seiten-Objekte (/Type /Page): <?php echo (int)($ergebnis['pages_count_objects'] ?? 0); ?></li>
            <li>Seitenanzahl konsistent: <?php echo ($ergebnis['pages_count_match'] ?? null) === null ? 'n/a' : (!empty($ergebnis['pages_count_match']) ? 'ja' : 'nein'); ?></li>
            <li>Footer "Seite 1/..." gefunden: <?php echo !empty($ergebnis['footer_seite1']) ? 'ja' : 'nein'; ?></li>
            <?php if ((int)($ergebnis['pages_count_objects'] ?? 0) >= 2): ?>
                <li>Footer "Seite 2/..." gefunden: <?php echo !empty($ergebnis['footer_seite2']) ? 'ja' : 'nein'; ?></li>
            <?php endif; ?>
            <li>Header "Arbeitszeitliste" gefunden: <?php echo !empty($ergebnis['header_arbeitszeitliste']) ? 'ja' : 'nein'; ?></li>
            <li>Header "Tag / KW" gefunden: <?php echo !empty($ergebnis['header_tag_kw']) ? 'ja' : 'nein'; ?></li>
        </ul>

        <?php
        $kCheck = $ergebnis['kommentar_check'] ?? [];
        $kHinweis = $ergebnis['kommentar_hinweis'] ?? null;
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
