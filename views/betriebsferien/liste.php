<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Betriebsferienliste
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 * - $meldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$eintraege     = $eintraege ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$meldung       = $meldung ?? null;

/**
 * Formatiert ein Datum (YYYY-MM-DD) nach deutschem Format (DD.MM.YYYY).
 */
function betriebsferien_format_datum(?string $ymd): string
{
    if ($ymd === null || trim($ymd) === '') {
        return '';
    }

    $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
    if ($dt === false) {
        return htmlspecialchars((string)$ymd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return $dt->format('d.m.Y');
}
?>

<section>
    <h2>Betriebsferien</h2>
    <p>
        <a href="?seite=betriebsferien_admin_bearbeiten">Neue Betriebsferien anlegen</a>
    </p>

    <?php if (!empty($meldung)): ?>
        <div style="background:#e8f5e9;border:1px solid #c8e6c9;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$meldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung" style="background:#ffebee;border:1px solid #ffcdd2;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (count($eintraege) === 0): ?>
        <p>Es sind derzeit keine Betriebsferien hinterlegt.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Abteilung</th>
                <th>Beschreibung</th>
                <th>Aktiv</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($eintraege as $e): ?>
                <?php
                    $id            = (int)($e['id'] ?? 0);
                    $von           = (string)($e['von_datum'] ?? '');
                    $bis           = (string)($e['bis_datum'] ?? '');
                    $abteilungName = (string)($e['abteilung_name'] ?? '');
                    $beschreibung  = (string)($e['beschreibung'] ?? '');
                    $aktiv         = (int)($e['aktiv'] ?? 0) === 1;
                ?>
                <tr>
                    <td><?php echo $id; ?></td>
                    <td><?php echo betriebsferien_format_datum($von); ?></td>
                    <td><?php echo betriebsferien_format_datum($bis); ?></td>
                    <td><?php echo htmlspecialchars($abteilungName !== '' ? $abteilungName : 'Global', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                    <td>
                        <a href="?seite=betriebsferien_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
