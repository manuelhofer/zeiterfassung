<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Abteilungsliste
 *
 * Erwartet:
 * - $abteilungen  (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 */
require __DIR__ . '/../layout/header.php';

$abteilungen  = $abteilungen ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
?>
<section>
    <h2>Abteilungen</h2>
    <p>
        <a href="?seite=abteilung_admin_bearbeiten">Neue Abteilung anlegen</a>
    </p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Abteilungen hinterlegt" wäre
             daneben die falsche Auskunft. */ ?>
    <?php if (count($abteilungen) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine aktiven Abteilungen hinterlegt.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($abteilungen as $abteilung): ?>
                    <?php
                        $id      = (int)($abteilung['id'] ?? 0);
                        $name    = (string)($abteilung['name'] ?? '');
                        $beschr  = (string)($abteilung['beschreibung'] ?? '');
                        $aktiv   = (int)($abteilung['aktiv'] ?? 0) === 1;
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($beschr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <a href="?seite=abteilung_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>