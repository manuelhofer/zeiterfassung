<?php
declare(strict_types=1);
/**
 * Template: Maschinen – Liste
 *
 * Erwartet:
 * - $maschinen (array<int,array<string,mixed>>)
 * - optional: $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $maschinen */
$maschinen     = $maschinen ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
?>
<section>
    <h2>Maschinen</h2>
    <p>
        <a href="?seite=maschine_admin_bearbeiten">Neue Maschine anlegen</a>
    </p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Maschinen hinterlegt" wäre daneben
             die falsche Auskunft. */ ?>
    <?php if (count($maschinen) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine Maschinen hinterlegt.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Abteilung</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maschinen as $m): ?>
                    <?php
                        $id          = (int)($m['id'] ?? 0);
                        $name        = (string)($m['name'] ?? '');
                        $abteilung   = (string)($m['abteilung_name'] ?? '');
                        $aktiv       = (int)($m['aktiv'] ?? 0) === 1;
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($abteilung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <a href="?seite=maschine_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
