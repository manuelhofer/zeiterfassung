<?php
declare(strict_types=1);
/**
 * Template: Maschinen – Formular (Neu/Bearbeiten)
 *
 * Erwartet:
 * - $maschine (array<string,mixed>)
 * - $abteilungen (array<int,array<string,mixed>>)
 * - $codeBildUrl (string) – fertige URL des Barcode-Bildes; sie kommt aus
 *   `MaschineQrCodeService`, damit Erzeugung und Anzeige dieselbe Logik nutzen.
 * - optional: $fehlermeldung (string|null), $erfolgsmeldung (string|null)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich    = (string)($csrfBereich ?? '');
/** @var array<string,mixed> $maschine */
$maschine       = $maschine ?? [];
/** @var array<int,array<string,mixed>> $abteilungen */
$abteilungen    = $abteilungen ?? [];
$codeBildUrl    = (string)($codeBildUrl ?? '');
$fehlermeldung  = $fehlermeldung ?? null;
$erfolgsmeldung = $erfolgsmeldung ?? null;

$id          = (int)($maschine['id'] ?? 0);
$name        = (string)($maschine['name'] ?? '');
$abteilungId = $maschine['abteilung_id'] ?? null;
$beschreibung = (string)($maschine['beschreibung'] ?? '');
$aktiv       = (int)($maschine['aktiv'] ?? 0) === 1;
$scanDaten = $id > 0 ? $id . '_' . $name : '';

if ($abteilungId !== null) {
    $abteilungId = (int)$abteilungId;
    if ($abteilungId <= 0) {
        $abteilungId = null;
    }
}
?>
<section>
    <h2><?php echo $id > 0 ? 'Maschine bearbeiten' : 'Maschine anlegen'; ?></h2>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($erfolgsmeldung)): ?>
        <div class="erfolgsmeldung">
            <?php echo htmlspecialchars((string)$erfolgsmeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=maschine_admin_speichern">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div style="margin-bottom: 0.75rem;">
            <label for="name"><strong>Name</strong></label><br>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 100%; max-width: 520px;">
        </div>

        <div style="margin-bottom: 0.75rem;">
            <label for="abteilung_id"><strong>Abteilung</strong></label><br>
            <select id="abteilung_id" name="abteilung_id" style="width: 100%; max-width: 520px;">
                <option value="">(keine)</option>
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
            <textarea id="beschreibung" name="beschreibung" rows="4" style="width: 100%; max-width: 820px;"><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
        </div>

        <div style="margin-bottom: 0.75rem;">
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Aktiv
            </label>
        </div>

        <?php if ($id > 0): ?>
            <div class="admin-card" style="margin: 1rem 0; border-radius: 6px; max-width: 520px;">
                <div><strong>Maschinen-Barcode</strong></div>
                <?php if ($codeBildUrl !== ''): ?>
                    <div style="margin-top: 0.5rem;">
                        <img src="<?php echo htmlspecialchars($codeBildUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Barcode Maschine <?php echo $id; ?>" style="max-width: 100%; height: auto;">
                    </div>
                    <div style="margin-top: 0.5rem; display:flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="<?php echo htmlspecialchars($codeBildUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" target="_blank">Download PNG</a>
                    </div>
                <?php else: ?>
                    <div class="muted" style="margin-top: 0.5rem;">
                        Noch kein Barcode gespeichert. Bitte die Maschine speichern.
                    </div>
                <?php endif; ?>
                <div style="margin-top: 0.5rem;">
                    <form method="post" action="?seite=maschine_admin_barcode_neu&amp;id=<?php echo $id; ?>">
                        <?php echo Csrf::feld($csrfBereich); ?>
                        <button type="submit">Barcode neu generieren</button>
                    </form>
                </div>
                <div class="muted" style="margin-top: 0.5rem; font-size: 0.9rem;">
                    Scan-Code: <code><?php echo htmlspecialchars($scanDaten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code>
                </div>
            </div>
        <?php endif; ?>

        <div style="display:flex; gap: 1rem; align-items:center;">
            <button type="submit">Speichern</button>
            <a href="?seite=maschine_admin">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
