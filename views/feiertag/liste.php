<?php
declare(strict_types=1);

/**
 * View: Liste der Feiertage für ein Jahr
 *
 * Erwartet:
 * - int   $jahr
 * - array $feiertage (Liste aus Feiertag-Datensätzen)
 * - optional: $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 *
 * Diese View wird im Standard-Backend-Layout eingebunden.
 */
?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);

$fmtDatum = static function (string $datum): string {
    $datum = trim($datum);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $m) === 1) {
        return $m[3] . '.' . $m[2] . '.' . $m[1];
    }
    return $datum;
};
?>

<section>
    <h2>Feiertage für das Jahr <?php echo htmlspecialchars((string)$jahr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>

    <form method="get" style="margin-bottom: 1rem;">
        <input type="hidden" name="seite" value="feiertag_admin">
        <label for="jahr">Jahr:</label>
        <input type="number" name="jahr" id="jahr" min="1970" max="2100"
               value="<?php echo htmlspecialchars((string)$jahr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
               style="width: 6rem; margin-left: 0.25rem; margin-right: 0.5rem;">
        <button type="submit">Anzeigen</button>
    </form>

    <p class="muted" style="font-size: 0.9rem;">
        Die gesetzlichen bundeseinheitlichen Feiertage werden automatisch erzeugt, falls für das gewählte Jahr
        noch keine Einträge vorhanden sind.
    </p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Feiertage gefunden" wäre daneben
             die falsche Auskunft. */ ?>
    <?php if (empty($feiertage)): ?>
        <?php if (!$ladefehler): ?>
            <p>Für dieses Jahr wurden keine Feiertage gefunden.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Datum</th>
                <th>Name</th>
                <th>Bundesland</th>
                <th>Gesetzlich</th>
                <th>Betriebsfrei</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($feiertage as $ft): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fmtDatum((string)($ft['datum'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($ft['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($ft['bundesland'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo !empty($ft['ist_gesetzlich']) ? 'Ja' : 'Nein'; ?></td>
                    <td><?php echo !empty($ft['ist_betriebsfrei']) ? 'Ja' : 'Nein'; ?></td>
                    <td>
                        <?php $id = (int)($ft['id'] ?? 0); ?>
                        <?php if ($id > 0): ?>
                            <a href="?seite=feiertag_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
