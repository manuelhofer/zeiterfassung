<?php
declare(strict_types=1);
/**
 * Template: Betriebsferien – Formular (Neu/Bearbeiten)
 *
 * Erwartet:
 * - $eintrag (array<string,mixed>)
 * - $abteilungen (array<int,array<string,mixed>>)
 * - optional: $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<string,mixed> $eintrag */
$eintrag       = $eintrag ?? [];
/** @var array<int,array<string,mixed>> $abteilungen */
$abteilungen   = $abteilungen ?? [];
$fehlermeldung = $fehlermeldung ?? null;

$id          = (int)($eintrag['id'] ?? 0);
$von         = (string)($eintrag['von_datum'] ?? '');
$bis         = (string)($eintrag['bis_datum'] ?? '');
$beschreibung = (string)($eintrag['beschreibung'] ?? '');
$aktiv       = (int)($eintrag['aktiv'] ?? 0) === 1;

$abteilungId = $eintrag['abteilung_id'] ?? null;
if ($abteilungId !== null) {
    $abteilungId = (int)$abteilungId;
    if ($abteilungId <= 0) {
        $abteilungId = null;
    }
}
?>
<section>
    <h2><?php echo $id > 0 ? 'Betriebsferien bearbeiten' : 'Betriebsferien anlegen'; ?></h2>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=betriebsferien_admin_speichern">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div style="margin-bottom: 0.75rem;">
            <label for="von_datum"><strong>Von</strong></label><br>
            <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div style="margin-bottom: 0.75rem;">
            <label for="bis_datum"><strong>Bis</strong></label><br>
            <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div style="margin-bottom: 0.75rem;">
            <label for="abteilung_id"><strong>Abteilung</strong></label><br>
            <select id="abteilung_id" name="abteilung_id" style="width: 100%; max-width: 520px;">
                <option value="">(global)</option>
                <?php foreach ($abteilungen as $abt): ?>
                    <?php
                        $aid = (int)($abt['id'] ?? 0);
                        $aname = (string)($abt['name'] ?? '');
                        $selected = ($abteilungId !== null && $aid === (int)$abteilungId) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $aid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($aname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 0.75rem;">
            <label for="beschreibung"><strong>Beschreibung</strong></label><br>
            <input type="text" id="beschreibung" name="beschreibung" value="<?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 100%; max-width: 820px;">
        </div>

        <div style="margin-bottom: 0.75rem;">
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Aktiv
            </label>
        </div>

        <div style="display:flex; gap: 1rem; align-items:center;">
            <button type="submit">Speichern</button>
            <a href="?seite=betriebsferien_admin">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
