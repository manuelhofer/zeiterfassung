<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Rundungsregeln (Liste)
 *
 * Erwartet:
 * - $regeln (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 * - $meldung (string|null)
 * - $csrfToken (string)
 */
require __DIR__ . '/../layout/header.php';

$regeln        = $regeln ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$meldung       = $meldung ?? null;
$csrfToken     = (string)($csrfToken ?? '');

function rr_fmt_time(?string $time): string
{
    if ($time === null) {
        return '';
    }
    $t = trim($time);
    if ($t === '') {
        return '';
    }
    // DB: TIME meist "HH:MM:SS" – anzeigen als "HH:MM"
    if (strlen($t) >= 5) {
        return htmlspecialchars(substr($t, 0, 5), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<section>
    <h2>Rundungsregeln</h2>

    <p style="margin-top:0.25rem;color:#555;">
        Reihenfolge: niedrigste <strong>Priorität</strong> zuerst. Die erste passende Regel wird angewendet.
    </p>

    <p>
        <a href="?seite=zeit_rundungsregel_admin_bearbeiten">Neue Regel anlegen</a>
        &nbsp;|&nbsp;
        <form method="post" action="?seite=zeit_rundungsregel_admin" style="display:inline;" onsubmit="return confirm('Standard-Rundungsregeln anlegen? Dies passiert nur, wenn noch keine Regeln existieren.');">
            <input type="hidden" name="aktion" value="seed_defaults">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <button type="submit" style="background:none;border:none;padding:0;margin:0;color:#06c;text-decoration:underline;cursor:pointer;font:inherit;">Standardregeln anlegen</button>
        </form>
    </p>

    <?php if (!empty($meldung)): ?>
        <div style="background:#e8f5e9;border:1px solid #c8e6c9;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$meldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div style="background:#ffebee;border:1px solid #ffcdd2;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (count($regeln) === 0): ?>
        <p>Es sind derzeit keine Rundungsregeln hinterlegt.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Zeitbereich</th>
                <th>Einheit</th>
                <th>Richtung</th>
                <th>Gilt für</th>
                <th>Priorität</th>
                <th>Aktiv</th>
                <th>Beschreibung</th>
                <th>Aktion</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($regeln as $r): ?>
                <?php
                    $id          = (int)($r['id'] ?? 0);
                    $von         = (string)($r['von_uhrzeit'] ?? '');
                    $bis         = (string)($r['bis_uhrzeit'] ?? '');
                    $einheit     = (int)($r['einheit_minuten'] ?? 0);
                    $richtung    = (string)($r['richtung'] ?? '');
                    $giltFuer    = (string)($r['gilt_fuer'] ?? '');
                    $prio        = (int)($r['prioritaet'] ?? 0);
                    $aktiv       = (int)($r['aktiv'] ?? 0) === 1;
                    $beschreibung= (string)($r['beschreibung'] ?? '');
                ?>
                <tr<?php echo $aktiv ? '' : ' style="opacity:0.65;"'; ?>>
                    <td><?php echo $id; ?></td>
                    <td><?php echo rr_fmt_time($von); ?> – <?php echo rr_fmt_time($bis); ?></td>
                    <td><?php echo $einheit; ?> min</td>
                    <td><?php echo htmlspecialchars($richtung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($giltFuer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo $prio; ?></td>
                    <td>
                        <?php echo $aktiv ? 'Ja' : 'Nein'; ?>
                        <form method="post" action="?seite=zeit_rundungsregel_admin" style="display:inline; margin-left:0.5rem;">
                            <input type="hidden" name="aktion" value="toggle_aktiv">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button type="submit" style="padding: 0.15rem 0.5rem;"><?php echo $aktiv ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                        </form>
                    </td>
                    <td><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td>
                        <a href="?seite=zeit_rundungsregel_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
