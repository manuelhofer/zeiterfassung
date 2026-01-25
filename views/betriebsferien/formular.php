<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Betriebsferien Formular
 *
 * Erwartet:
 * - $datensatz (array<string,mixed>)
 * - $abteilungen (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$datensatz     = $datensatz ?? [];
$abteilungen   = $abteilungen ?? [];
$fehlermeldung = $fehlermeldung ?? null;

$id          = (int)($datensatz['id'] ?? 0);
$von         = (string)($datensatz['von_datum'] ?? '');
$bis         = (string)($datensatz['bis_datum'] ?? '');
$beschreibung = (string)($datensatz['beschreibung'] ?? '');
$abteilungId  = $datensatz['abteilung_id'] ?? null;
$aktiv        = (int)($datensatz['aktiv'] ?? 0) === 1;
?>

<section>
    <h2><?php echo $id > 0 ? 'Betriebsferien bearbeiten' : 'Betriebsferien anlegen'; ?></h2>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung" style="background:#ffebee;border:1px solid #ffcdd2;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=betriebsferien_admin_speichern">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;margin-bottom:0.75rem;">
            <div>
                <label for="von_datum"><strong>Von</strong></label><br>
                <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </div>

            <div>
                <label for="bis_datum"><strong>Bis</strong></label><br>
                <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </div>

            <div style="min-width:220px;">
                <label for="abteilung_id"><strong>Abteilung</strong></label><br>
                <select id="abteilung_id" name="abteilung_id">
                    <option value="">Global (für alle)</option>
                    <?php foreach ($abteilungen as $a): ?>
                        <?php
                            $aId = (int)($a['id'] ?? 0);
                            $aName = (string)($a['name'] ?? '');
                            $selected = ($abteilungId !== null && (int)$abteilungId === $aId) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $aId; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($aName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="aktiv"><strong>Aktiv</strong></label><br>
                <input type="checkbox" id="aktiv" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
            </div>
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="beschreibung"><strong>Beschreibung</strong></label><br>
            <input type="text" id="beschreibung" name="beschreibung" value="<?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width:100%;max-width:650px;">
        </div>

        <p>
            <button type="submit">Speichern</button>
            <a href="?seite=betriebsferien_admin" style="margin-left:0.75rem;">Abbrechen</a>
        </p>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
