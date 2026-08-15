<?php
declare(strict_types=1);
/**
 * Template: Konfiguration – Sonstiges-Gründe (Liste + Formular)
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>) – Zeilen aus `sonstiges_grund`
 * - $form (array<string,mixed>) – Werte des Formulars (leer oder zum Bearbeiten)
 * - $editId (int) – >0, wenn ein bestehender Eintrag bearbeitet wird
 * - optional: $ok (int) – 1 zeigt „Gespeichert."
 * - optional: $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? '');
/** @var array<int,array<string,mixed>> $eintraege */
$eintraege     = $eintraege ?? [];
/** @var array<string,mixed> $form */
$form          = $form ?? [];
$editId        = (int)($editId ?? 0);
$ok            = (int)($ok ?? 0);
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
?>
<section>
    <h2>Sonstiges-Gründe</h2>

    <p style="margin-top:0.25rem;">
        <a href="?seite=konfiguration_admin">Konfiguration</a>
        | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
        | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
        | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
        | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
    </p>

    <p class="muted" style="max-width:60rem;">
        Diese Liste definiert die auswählbaren Gründe für <strong>Sonstiges</strong> (z. B. Sonderurlaub).
        In der Tagesansicht kann später ein Grund gewählt werden, der dann Default-Stunden und ggf. Begründungspflicht vorgibt.
    </p>

    <p>
        <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Neu anlegen</a>
    </p>

    <?php if ($ok === 1): ?>
        <div class="erfolgsmeldung">Gespeichert.</div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="?seite=konfiguration_admin&amp;tab=sonstiges">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="sonstiges_action" value="speichern">
        <input type="hidden" name="id" value="<?php echo (int)($form['id'] ?? 0); ?>">

        <div style="display:grid;grid-template-columns: 1fr 2fr 1fr;gap:0.75rem;align-items:end;max-width:70rem;">
            <label>
                Code<br>
                <input type="text" name="code" maxlength="10" required value="<?php echo htmlspecialchars((string)$form['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <label>
                Titel<br>
                <input type="text" name="titel" maxlength="80" required value="<?php echo htmlspecialchars((string)$form['titel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <label>
                Default-Stunden<br>
                <input type="text" name="default_stunden" inputmode="decimal" value="<?php echo htmlspecialchars((string)$form['default_stunden'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <label style="grid-column:1 / span 1;">
                Sortierung<br>
                <input type="number" name="sort_order" min="0" value="<?php echo (int)($form['sort_order'] ?? 10); ?>">
            </label>

            <label style="grid-column:2 / span 1;">
                Kommentar (optional)<br>
                <input type="text" name="kommentar" maxlength="255" value="<?php echo htmlspecialchars((string)$form['kommentar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <div style="grid-column:3 / span 1;">
                <label style="display:block; margin-bottom:0.25rem;">
                    <input type="checkbox" name="begruendung_pflicht" value="1" <?php echo ((int)($form['begruendung_pflicht'] ?? 0) === 1) ? 'checked' : ''; ?>>
                    Begründung Pflicht
                </label>
                <label style="display:block;">
                    <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($form['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                    Aktiv
                </label>
            </div>

            <div style="grid-column: 1 / -1; margin-top:0.25rem;">
                <button type="submit">Speichern</button>
                <?php if ($editId > 0): ?>
                    <a style="margin-left:0.5rem;" href="?seite=konfiguration_admin&amp;tab=sonstiges">Abbrechen</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <hr style="margin:1rem 0;">

    <?php /* Nach einem Lesefehler ist die Liste ebenfalls leer – dann
             steht schon die Fehlermeldung da. */ ?>
    <?php if (count($eintraege) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine Sonstiges-Gründe vorhanden.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Titel</th>
                    <th>Default</th>
                    <th>Begründung</th>
                    <th>Sort</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eintraege as $e): ?>
                    <?php
                        $id = (int)($e['id'] ?? 0);
                        $code = (string)($e['code'] ?? '');
                        $titel = (string)($e['titel'] ?? '');
                        $ds = number_format((float)($e['default_stunden'] ?? 0), 2, '.', '');
                        $bp = (int)($e['begruendung_pflicht'] ?? 0);
                        $so = (int)($e['sort_order'] ?? 10);
                        $akt = (int)($e['aktiv'] ?? 1);
                    ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars($titel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($ds, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $bp === 1 ? 'Ja' : 'Nein'; ?></td>
                        <td><?php echo (int)$so; ?></td>
                        <td><?php echo $akt === 1 ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <a href="?seite=konfiguration_admin&amp;tab=sonstiges&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                            <form method="post" action="?seite=konfiguration_admin&amp;tab=sonstiges" style="display:inline; margin-left:0.5rem;">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <input type="hidden" name="sonstiges_action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                <input type="hidden" name="aktiv" value="<?php echo $akt === 1 ? 0 : 1; ?>">
                                <button type="submit"><?php echo $akt === 1 ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
