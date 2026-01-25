<?php
declare(strict_types=1);

/**
 * View: Feiertag bearbeiten
 *
 * Erwartet:
 * - array $feiertag
 * - int   $jahr
 */
?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<section>
    <h2>Feiertag bearbeiten</h2>

    <?php
    $id         = (int)($feiertag['id'] ?? 0);
    $datum      = (string)($feiertag['datum'] ?? '');
    $name       = (string)($feiertag['name'] ?? '');
    $bundesland = (string)($feiertag['bundesland'] ?? '');
    $istGesetzlich   = !empty($feiertag['ist_gesetzlich']);
    $istBetriebsfrei = !empty($feiertag['ist_betriebsfrei']);
    ?>

    <form method="post" action="?seite=feiertag_admin_speichern">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="jahr" value="<?php echo (int)$jahr; ?>">

        <div>
            <label>Datum:</label>
            <span><?php echo htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
        </div>

        <div>
            <label for="name">Name:</label>
            <input type="text"
                   name="name"
                   id="name"
                   value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                   required>
        </div>

        <div>
            <label for="bundesland">Bundesland (optional):</label>
            <input type="text"
                   name="bundesland"
                   id="bundesland"
                   value="<?php echo htmlspecialchars($bundesland, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label>
                <input type="checkbox" name="ist_gesetzlich" value="1" <?php echo $istGesetzlich ? 'checked' : ''; ?>>
                Gesetzlicher Feiertag
            </label>
        </div>

        <div>
            <label>
                <input type="checkbox" name="ist_betriebsfrei" value="1" <?php echo $istBetriebsfrei ? 'checked' : ''; ?>>
                Betriebsfrei
            </label>
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit">Speichern</button>
            <a href="?seite=feiertag_admin&jahr=<?php echo (int)$jahr; ?>">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
