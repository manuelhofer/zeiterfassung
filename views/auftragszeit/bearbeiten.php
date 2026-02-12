<?php
declare(strict_types=1);

$auftragszeitId = (int)($auftragszeitId ?? 0);
$auftragscode = (string)($auftragscode ?? '');
$startDatum = (string)($startDatum ?? '');
$startUhrzeit = (string)($startUhrzeit ?? '');
$endeDatum = (string)($endeDatum ?? '');
$endeUhrzeit = (string)($endeUhrzeit ?? '');
$status = (string)($status ?? 'laufend');
$kommentar = (string)($kommentar ?? '');
$csrfToken = (string)($csrfToken ?? '');
$fehlermeldung = $fehlermeldung ?? null;

$zielCode = $auftragscode !== '' ? $auftragscode : '';
?>
<section>
    <h2>Auftragszeit bearbeiten</h2>

    <p>
        <a href="?seite=auftrag_detail&amp;code=<?php echo urlencode($zielCode); ?>">&laquo; Zurück zur Auftragsdetailseite</a>
    </p>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <p style="padding:8px;border:1px solid #d29a9a;background:#f7e9e9;">
            <?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="post" action="?seite=auftragszeit_bearbeiten&amp;id=<?php echo $auftragszeitId; ?>" style="max-width: 720px;">
        <input type="hidden" name="id" value="<?php echo $auftragszeitId; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

        <fieldset>
            <legend>Zeitraum</legend>

            <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.75rem;">
                <label>
                    Start-Datum<br>
                    <input type="date" name="start_datum" value="<?php echo htmlspecialchars($startDatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </label>

                <label>
                    Start-Uhrzeit<br>
                    <input type="time" name="start_uhrzeit" value="<?php echo htmlspecialchars($startUhrzeit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </label>
            </div>

            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                <label>
                    Ende-Datum<br>
                    <input type="date" name="ende_datum" value="<?php echo htmlspecialchars($endeDatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>

                <label>
                    Ende-Uhrzeit<br>
                    <input type="time" name="ende_uhrzeit" value="<?php echo htmlspecialchars($endeUhrzeit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
            </div>
        </fieldset>

        <fieldset style="margin-top:1rem;">
            <legend>Optional</legend>

            <label>
                Status<br>
                <select name="status">
                    <?php foreach (['laufend', 'abgeschlossen', 'abgebrochen', 'pausiert'] as $statusOption): ?>
                        <option value="<?php echo $statusOption; ?>" <?php echo $status === $statusOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($statusOption), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <br><br>

            <label>
                Kommentar<br>
                <textarea name="kommentar" rows="4" style="width:100%;"><?php echo htmlspecialchars($kommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
            </label>
        </fieldset>

        <p style="margin-top: 1rem;">
            <button type="submit">Speichern</button>
        </p>
    </form>
</section>
