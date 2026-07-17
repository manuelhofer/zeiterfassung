<?php
declare(strict_types=1);

/**
 * Backend-Ansicht: Mitarbeiter fuer Rollen & Rechte auswaehlen.
 *
 * Erwartet:
 * - $mitarbeiterListe (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 * - $successmeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$mitarbeiterListe = $mitarbeiterListe ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$successmeldung = $successmeldung ?? null;
?>

<section>
    <div class="page-header">
        <div>
            <h2>Rollen &amp; Rechte</h2>
            <p>Mitarbeiter auswaehlen und die Rollenverwaltung oeffnen.</p>
        </div>
    </div>

    <?php if ($fehlermeldung !== null): ?>
        <p class="error"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($successmeldung !== null): ?>
        <p class="success"><?php echo htmlspecialchars($successmeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="get" action="" class="toolbar">
        <input type="hidden" name="seite" value="mitarbeiter_rechte">
        <label for="rechte_mitarbeiter_id">
            Mitarbeiter
            <select name="id" id="rechte_mitarbeiter_id" required>
                <option value="">-- bitte waehlen --</option>
                <?php foreach ($mitarbeiterListe as $mit): ?>
                    <?php
                        $id = (int)($mit['id'] ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        $vorname = trim((string)($mit['vorname'] ?? ''));
                        $nachname = trim((string)($mit['nachname'] ?? ''));
                        $name = trim($nachname . ', ' . $vorname);
                        if ($name === ',' || $name === '') {
                            $name = trim((string)($mit['benutzername'] ?? ''));
                        }
                        if ($name === '') {
                            $name = 'ID ' . $id;
                        }
                    ?>
                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Oeffnen</button>
    </form>

    <?php if (count($mitarbeiterListe) === 0): ?>
        <p>Es sind derzeit keine aktiven Mitarbeiter erfasst.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Benutzername</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mitarbeiterListe as $mit): ?>
                    <?php
                        $id = (int)($mit['id'] ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        $vorname = trim((string)($mit['vorname'] ?? ''));
                        $nachname = trim((string)($mit['nachname'] ?? ''));
                        $name = trim($vorname . ' ' . $nachname);
                        $benutzer = trim((string)($mit['benutzername'] ?? ''));
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name !== '' ? $name : ('ID ' . $id), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($benutzer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <a href="?seite=mitarbeiter_rechte&amp;id=<?php echo $id; ?>">Rollen &amp; Rechte bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
