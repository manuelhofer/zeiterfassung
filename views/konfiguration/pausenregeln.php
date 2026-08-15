<?php
declare(strict_types=1);
/**
 * Template: Konfiguration – Pausenregeln
 *
 * Zwei Formulare auf einer Seite: die gesetzlichen Mindestwerte (aus `config`)
 * und die betrieblichen Pausenfenster (Liste + Formular aus `pausenfenster`).
 *
 * Erwartet:
 * - $fenster (array<int,array<string,mixed>>) – Zeilen aus `pausenfenster`
 * - $form (array<string,mixed>) – Werte des Fenster-Formulars
 * - $editId (int) – >0, wenn ein bestehendes Fenster bearbeitet wird
 * - $cfgSchwelle1, $cfgMinuten1, $cfgSchwelle2, $cfgMinuten2 (int) – gesetzliche Werte
 * - optional: $ok (int) – 1 zeigt „Gespeichert."
 * - optional: $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? '');
/** @var array<int,array<string,mixed>> $fenster */
$fenster       = $fenster ?? [];
/** @var array<string,mixed> $form */
$form          = $form ?? [];
$editId        = (int)($editId ?? 0);
$ok            = (int)($ok ?? 0);
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
$cfgSchwelle1  = (int)($cfgSchwelle1 ?? 6);
$cfgMinuten1   = (int)($cfgMinuten1 ?? 30);
$cfgSchwelle2  = (int)($cfgSchwelle2 ?? 9);
$cfgMinuten2   = (int)($cfgMinuten2 ?? 45);
?>
<section>
    <h2>Pausenregeln</h2>

<?php require __DIR__ . '/_tabzeile.php'; ?>

    <p class="muted" style="max-width:60rem;">
        Hier werden die betrieblichen Pausenfenster (Uhrzeitfenster) und die gesetzlichen Mindestpausenwerte gepflegt.
        Die Abzüge werden später pro Arbeitsblock berechnet (Mehrfach-Kommen/Gehen wird unterstützt).
    </p>

    <?php if ($ok === 1): ?>
        <div class="erfolgsmeldung">Gespeichert.</div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <h3>Gesetzliche Mindestpause (konfigurierbar)</h3>
    <form method="post" action="?seite=konfiguration_admin&amp;tab=pausen">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="pause_action" value="save_rules">

        <p>
            <label>Schwelle 1 (Stunden)</label><br>
            <input type="number" name="gesetz_schwelle1" min="1" step="1" value="<?php echo (int)$cfgSchwelle1; ?>">
        </p>
        <p>
            <label>Pause 1 (Minuten)</label><br>
            <input type="number" name="gesetz_minuten1" min="0" step="1" value="<?php echo (int)$cfgMinuten1; ?>">
        </p>
        <p>
            <label>Schwelle 2 (Stunden)</label><br>
            <input type="number" name="gesetz_schwelle2" min="1" step="1" value="<?php echo (int)$cfgSchwelle2; ?>">
        </p>
        <p>
            <label>Pause 2 (Minuten)</label><br>
            <input type="number" name="gesetz_minuten2" min="0" step="1" value="<?php echo (int)$cfgMinuten2; ?>">
        </p>

        <p>
            <button type="submit">Gesetzliche Werte speichern</button>
        </p>

        <p class="muted" style="max-width:60rem;">
            Empfehlung/Default (Deutschland): &gt;<?php echo (int)$cfgSchwelle1; ?>h → <?php echo (int)$cfgMinuten1; ?>min, &gt;<?php echo (int)$cfgSchwelle2; ?>h → <?php echo (int)$cfgMinuten2; ?>min.
            (Die Schwellen/Minuten sind hier bewusst konfigurierbar.)
        </p>
    </form>

    <hr>

    <h3>Betriebliche Pausenfenster</h3>

    <?php /* Nur beim Bearbeiten: Der Link leert das Formular. Auf der
             leeren Maske zeigt er auf die eigene Seite und tut nichts. */ ?>
    <?php if ($editId > 0): ?>
        <p>
            <a href="?seite=konfiguration_admin&amp;tab=pausen">Neu anlegen</a>
        </p>
    <?php endif; ?>

    <form method="post" action="?seite=konfiguration_admin&amp;tab=pausen">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="pause_action" value="speichern">
        <input type="hidden" name="id" value="<?php echo (int)($form['id'] ?? 0); ?>">

        <p>
            <label for="von_uhrzeit">Von</label><br>
            <input id="von_uhrzeit" type="time" name="von_uhrzeit" value="<?php echo htmlspecialchars((string)($form['von_uhrzeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </p>
        <p>
            <label for="bis_uhrzeit">Bis</label><br>
            <input id="bis_uhrzeit" type="time" name="bis_uhrzeit" value="<?php echo htmlspecialchars((string)($form['bis_uhrzeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </p>
        <p>
            <label for="sort_order">Sortierung</label><br>
            <input id="sort_order" type="number" name="sort_order" min="0" step="1" value="<?php echo (int)($form['sort_order'] ?? 10); ?>">
        </p>
        <p>
            <label for="kommentar">Kommentar</label><br>
            <input id="kommentar" type="text" name="kommentar" maxlength="255" value="<?php echo htmlspecialchars((string)($form['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </p>

        <p>
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($form['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                aktiv
            </label>
        </p>

        <p>
            <button type="submit">Pausenfenster speichern</button>
            <?php if ((int)($form['id'] ?? 0) > 0): ?>
                <a style="margin-left:0.5rem;" href="?seite=konfiguration_admin&amp;tab=pausen">Abbrechen</a>
            <?php endif; ?>
        </p>
    </form>

    <?php /* Nach einem Lesefehler ist die Liste ebenfalls leer – dann
             steht schon die Fehlermeldung da. */ ?>
    <?php if (count($fenster) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Keine Pausenfenster vorhanden.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Sort</th>
                    <th>Kommentar</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fenster as $f): ?>
                    <?php
                        $id = (int)($f['id'] ?? 0);
                        $von = (string)($f['von_uhrzeit'] ?? '');
                        $bis = (string)($f['bis_uhrzeit'] ?? '');
                        $so  = (int)($f['sort_order'] ?? 0);
                        $kom = (string)($f['kommentar'] ?? '');
                        $akt = (int)($f['aktiv'] ?? 1);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo (int)$so; ?></td>
                        <td><?php echo htmlspecialchars($kom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $akt === 1 ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <a href="?seite=konfiguration_admin&amp;tab=pausen&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                            <form method="post" action="?seite=konfiguration_admin&amp;tab=pausen" style="display:inline; margin-left:0.5rem;">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <input type="hidden" name="pause_action" value="toggle">
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
