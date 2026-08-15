<?php
declare(strict_types=1);
/**
 * Template: Kurzarbeit – Liste der Pläne
 *
 * Erwartet:
 * - $plaene (array<int,array<string,mixed>>) – je Zeile zusätzlich
 *   `wochentage_text`, weil das Ausschreiben der Bitmaske im Controller sitzt
 * - $csrfToken (string)
 * - optional: $flash (array{ok?:string,err?:string}), $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $plaene */
$plaene        = $plaene ?? [];
$csrfToken     = (string)($csrfToken ?? '');
$flash         = $flash ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
?>
<section>
    <h2>Kurzarbeit (Planung)</h2>

    <p>
        <a href="?seite=kurzarbeit_admin_bearbeiten">Neuen Plan anlegen</a>
    </p>

    <?php if (!empty($flash['ok'])): ?>
        <div class="erfolgsmeldung"><?php echo htmlspecialchars((string)$flash['ok'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['err'])): ?>
        <div class="fehlermeldung"><?php echo htmlspecialchars((string)$flash['err'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Scope</th>
            <th>Mitarbeiter</th>
            <th>Zeitraum</th>
            <th>Wochentage</th>
            <th>Modus</th>
            <th>Wert</th>
            <th>Kommentar</th>
            <th>Aktiv</th>
            <th>Aktionen</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($plaene === []): ?>
            <?php /* Nach einem Lesefehler steht die Fehlermeldung schon oben –
                     „Keine Einträge." wäre daneben die falsche Auskunft. */ ?>
            <?php if (!$ladefehler): ?>
                <tr><td colspan="10">Keine Einträge.</td></tr>
            <?php endif; ?>
        <?php else: ?>
            <?php foreach ($plaene as $p): ?>
                <?php
                $id = (int)($p['id'] ?? 0);
                $scope = (string)($p['scope'] ?? '');
                $aktiv = (int)($p['aktiv'] ?? 0) === 1;
                $von = (string)($p['von_datum'] ?? '');
                $bis = (string)($p['bis_datum'] ?? '');
                $wochentageText = (string)($p['wochentage_text'] ?? '');
                $modus = (string)($p['modus'] ?? 'stunden');
                $wert = (string)($p['wert'] ?? '0');
                $kommentar = (string)($p['kommentar'] ?? '');
                $mid = (int)($p['mitarbeiter_id'] ?? 0);
                $mName = '';
                if ($mid > 0) {
                    $vn = trim((string)($p['m_vorname'] ?? ''));
                    $nn = trim((string)($p['m_nachname'] ?? ''));
                    $mName = trim($nn . ', ' . $vn);
                }
                ?>
                <tr>
                    <td><?php echo (int)$id; ?></td>
                    <td><?php echo htmlspecialchars($scope, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo $mName !== '' ? htmlspecialchars($mName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                    <td><?php echo htmlspecialchars($von . ' bis ' . $bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($wochentageText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($modus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($kommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                    <td>
                        <a href="?seite=kurzarbeit_admin_bearbeiten&id=<?php echo (int)$id; ?>">Bearbeiten</a>
                        <form method="post" action="?seite=kurzarbeit_admin_toggle" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                            <input type="hidden" name="aktiv" value="<?php echo $aktiv ? '0' : '1'; ?>">
                            <button type="submit"><?php echo $aktiv ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="muted" style="margin-top:0.75rem;">
        Hinweis: Tages-Overrides (Kurzarbeit pro Tag in der Korrekturmaske) folgen im nächsten Patch.
    </p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
