<?php
declare(strict_types=1);
/**
 * Template: Terminalverwaltung – Formular (Anlegen/Bearbeiten)
 *
 * Erwartet (der Controller rechnet die Werte vor, siehe `renderFormular()`):
 * - $id (int) – 0 beim Anlegen
 * - $name, $standort (string), $abteilungId (int|null), $modus (string)
 * - $offlineKommenGehen, $offlineAuftraege, $aktiv (bool)
 * - $timeout (int) – Sekunden bis zum Auto-Logout
 * - $abteilungen (array<int,array<string,mixed>>) – Auswahlliste
 * - optional: $fehlermeldung (string|null)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich        = (string)($csrfBereich ?? '');
$id                 = (int)($id ?? 0);
$name               = (string)($name ?? '');
$standort           = (string)($standort ?? '');
$abteilungId        = isset($abteilungId) ? (int)$abteilungId : null;
$modus              = (string)($modus ?? 'terminal');
$offlineKommenGehen = (bool)($offlineKommenGehen ?? false);
$offlineAuftraege   = (bool)($offlineAuftraege ?? false);
$timeout            = (int)($timeout ?? 60);
$aktiv              = (bool)($aktiv ?? false);
/** @var array<int,array<string,mixed>> $abteilungen */
$abteilungen        = $abteilungen ?? [];
$fehlermeldung      = $fehlermeldung ?? null;
?>
<section>
    <h2><?php echo $id > 0 ? 'Terminal bearbeiten' : 'Terminal anlegen'; ?></h2>

    <p><a href="?seite=terminal_admin">&laquo; Zurück zur Liste</a></p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=terminal_admin_speichern">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="id" value="<?php echo $id > 0 ? $id : 0; ?>">

        <div class="formularfeld">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </div>

        <div class="formularfeld">
            <label for="standort_beschreibung">Standort (optional)</label>
            <input type="text" id="standort_beschreibung" name="standort_beschreibung" value="<?php echo htmlspecialchars($standort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div class="formularfeld">
            <label for="abteilung_id">Abteilung (optional)</label>
            <select id="abteilung_id" name="abteilung_id">
                <option value="">– keine –</option>
                <?php foreach ($abteilungen as $a): ?>
                    <?php
                        $aid = (int)($a['id'] ?? 0);
                        $aname = (string)($a['name'] ?? '');
                        $selected = ($abteilungId !== null && $aid === $abteilungId) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $aid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($aname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="formularfeld">
            <label for="modus">Modus</label>
            <select id="modus" name="modus">
                <option value="terminal" <?php echo $modus === 'terminal' ? 'selected' : ''; ?>>terminal</option>
                <option value="backend" <?php echo $modus === 'backend' ? 'selected' : ''; ?>>backend</option>
            </select>
        </div>

        <div class="formularfeld">
            <label for="auto_logout_timeout_sekunden">Auto-Logout Timeout (Sekunden)</label>
            <input type="number" id="auto_logout_timeout_sekunden" name="auto_logout_timeout_sekunden" min="10" max="86400" value="<?php echo $timeout; ?>">
        </div>

        <fieldset>
            <legend>Offline-Modus</legend>

            <label>
                <input type="checkbox" name="offline_erlaubt_kommen_gehen" value="1" <?php echo $offlineKommenGehen ? 'checked' : ''; ?>>
                Kommen/Gehen offline erlauben
            </label>
            <br>
            <label>
                <input type="checkbox" name="offline_erlaubt_auftraege" value="1" <?php echo $offlineAuftraege ? 'checked' : ''; ?>>
                Aufträge offline erlauben
            </label>
        </fieldset>

        <div class="formularfeld">
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Aktiv
            </label>
        </div>

        <div class="button-row">
            <button type="submit">Speichern</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
