<?php
declare(strict_types=1);
/**
 * Template: Abteilungsverwaltung – Formular (Neu/Bearbeiten)
 *
 * Erwartet:
 * - $abteilung (array<string,mixed>|null)
 * - $alleAbteilungen (array<int,array<string,mixed>>)
 * - optional: $fehlermeldung (string)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<string,mixed>|null $abteilung */
$abteilung       = $abteilung ?? null;
/** @var array<int,array<string,mixed>> $alleAbteilungen */
$alleAbteilungen = $alleAbteilungen ?? [];
$fehlermeldung   = $fehlermeldung ?? null;

$id           = $abteilung['id'] ?? null;
$id           = $id !== null ? (int)$id : 0;
$name         = isset($abteilung['name']) ? trim((string)$abteilung['name']) : '';
$beschreibung = isset($abteilung['beschreibung']) ? (string)$abteilung['beschreibung'] : '';
$parentId     = $abteilung['parent_id'] ?? null;
$aktiv        = array_key_exists('aktiv', (array)$abteilung) ? ((int)$abteilung['aktiv'] === 1) : true;
?>
<section>
    <h2><?php echo $id > 0 ? 'Abteilung bearbeiten' : 'Neue Abteilung anlegen'; ?></h2>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <p style="color:red;"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="?seite=abteilung_admin_speichern">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

        <div>
            <label for="name">Name der Abteilung *</label><br>
            <input type="text" id="name" name="name" required
                   value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label for="parent_id">Übergeordnete Abteilung</label><br>
            <select id="parent_id" name="parent_id">
                <option value="">(keine)</option>
                <?php foreach ($alleAbteilungen as $abt): ?>
                    <?php
                        $pid   = (int)($abt['id'] ?? 0);
                        $pname = trim((string)($abt['name'] ?? ''));
                        if ($pid <= 0) {
                            continue;
                        }
                        // Sich selbst nicht als Parent anbieten.
                        if ($id > 0 && $pid === $id) {
                            continue;
                        }
                        $selected = ($parentId !== null && (int)$parentId === $pid) ? 'selected' : '';
                    ?>
                    <option value="<?php echo htmlspecialchars((string)$pid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($pname !== '' ? $pname : ('#' . $pid), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Abteilung ist aktiv
            </label>
        </div>

        <div>
            <label for="beschreibung">Beschreibung</label><br>
            <textarea id="beschreibung" name="beschreibung" rows="4" cols="60"><?php
                echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            ?></textarea>
        </div>

        <div style="margin-top:1em;">
            <button type="submit">Speichern</button>
            <a href="?seite=abteilung_admin">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
