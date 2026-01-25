<?php
declare(strict_types=1);
/**
 * Template: Rollenverwaltung – Liste
 *
 * Erwartet:
 * - $rollen (array<int,array<string,mixed>>)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $rollen */
$rollen = $rollen ?? [];
?>
<section>
    <h2>Rollenverwaltung</h2>

    <p><a href="?seite=rollen_admin_bearbeiten">Neue Rolle anlegen</a></p>

    <?php if (count($rollen) === 0): ?>
        <p>Es sind noch keine Rollen hinterlegt.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Aktiv</th>
                    <th>Beschreibung</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rollen as $rolle): ?>
                <?php
                    $id           = (int)($rolle['id'] ?? 0);
                    $name         = trim((string)($rolle['name'] ?? ''));
                    $beschreibung = (string)($rolle['beschreibung'] ?? '');
                    $aktiv        = (int)($rolle['aktiv'] ?? 0) === 1;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                    <td><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td>
                        <a href="?seite=rollen_admin_bearbeiten&amp;id=<?php echo urlencode((string)$id); ?>">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
