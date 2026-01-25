<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Maschinenliste
 *
 * Erwartet:
 * - $maschinen (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$maschinen     = $maschinen ?? [];
$fehlermeldung = $fehlermeldung ?? null;
?>
<section>
    <h2>Maschinen</h2>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (count($maschinen) === 0): ?>
        <p>Es sind derzeit keine aktiven Maschinen hinterlegt.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Abteilung-ID</th>
                    <th>Beschreibung</th>
                    <th>Aktiv</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maschinen as $maschine): ?>
                    <?php
                        $id           = (int)($maschine['id'] ?? 0);
                        $name         = (string)($maschine['name'] ?? '');
                        $abteilungId  = $maschine['abteilung_id'] ?? null;
                        $beschreibung = (string)($maschine['beschreibung'] ?? '');
                        $aktiv        = (int)($maschine['aktiv'] ?? 0) === 1;

                        $abteilungIdAnzeige = '';
                        if ($abteilungId !== null && $abteilungId !== '') {
                            $abteilungIdAnzeige = (string)(int)$abteilungId;
                        }
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($abteilungIdAnzeige, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top:1rem;">
        <small>Hinweis: Anlegen/Bearbeiten (CRUD) folgt im nächsten Schritt.</small>
    </p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
