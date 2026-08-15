<?php
declare(strict_types=1);
/**
 * Template: Betriebsferien – Liste
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller,
 *   damit der Name an genau einer Stelle steht.
 * - optional: $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $eintraege */
$eintraege     = $eintraege ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
$csrfBereich   = (string)($csrfBereich ?? '');
?>
<section>
    <h2>Betriebsferien</h2>
    <p>
        <a href="?seite=betriebsferien_admin_bearbeiten">Neue Betriebsferien anlegen</a>
    </p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Betriebsferien hinterlegt" wäre
             daneben die falsche Auskunft. */ ?>
    <?php if (count($eintraege) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine Betriebsferien hinterlegt.</p>
        <?php endif; ?>
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
                <?php foreach ($eintraege as $bf): ?>
                    <?php
                        $id = (int)($bf['id'] ?? 0);
                        $von = (string)($bf['von_datum'] ?? '');
                        $bis = (string)($bf['bis_datum'] ?? '');
                        $abteilung = (string)($bf['abteilung_name'] ?? '');
                        $beschreibung = (string)($bf['beschreibung'] ?? '');
                        $aktiv = (int)($bf['aktiv'] ?? 0) === 1;
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $abteilung !== '' ? htmlspecialchars($abteilung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '(global)'; ?></td>
                        <td><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <a href="?seite=betriebsferien_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                            <form method="post" action="?seite=betriebsferien_admin_toggle" style="display:inline;">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <input type="hidden" name="aktiv" value="<?php echo $aktiv ? '0' : '1'; ?>">
                                <button type="submit"><?php echo $aktiv ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top: 1rem;">
        <small>
            Hinweis: Global bedeutet <code>abteilung_id = NULL</code> (gilt für alle).
        </small>
    </p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
