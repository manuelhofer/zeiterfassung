<?php
declare(strict_types=1);
/**
 * Template: Urlaubskontingent pro Jahr – Übersicht
 *
 * Erwartet:
 * - $zeilen (array<int,array<string,mixed>>) – je Zeile zusätzlich die drei
 *   bereits formatierten Werte `standard_anspruch_text`, `override_text` und
 *   `korrektur_text`; das Formatieren sitzt im Controller.
 * - $jahr, $vorjahr, $aktuellesJahr (int)
 * - optional: $flashOk (string|null), $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $zeilen */
$zeilen        = $zeilen ?? [];
$jahr          = (int)($jahr ?? date('Y'));
$vorjahr       = (int)($vorjahr ?? ($jahr - 1));
$aktuellesJahr = (int)($aktuellesJahr ?? date('Y'));
$flashOk       = $flashOk ?? null;
$fehlermeldung = $fehlermeldung ?? null;
?>
<section>
    <h2>Urlaubskontingent pro Jahr</h2>

    <form method="get" style="margin-bottom: 1rem;">
        <input type="hidden" name="seite" value="urlaub_kontingent_admin">
        <label>
            Jahr:
            <input type="number" name="jahr" value="<?php echo (int)$jahr; ?>" min="2000" max="2100" style="width: 7rem;">
        </label>
        <button type="submit">Anzeigen</button>
        <a href="?seite=urlaub_kontingent_admin&amp;jahr=<?php echo (int)$vorjahr; ?>" style="margin-left:0.75rem;">Vorjahr <?php echo (int)$vorjahr; ?></a>
        |
        <a href="?seite=urlaub_kontingent_admin&amp;jahr=<?php echo (int)$aktuellesJahr; ?>">Aktuelles Jahr <?php echo (int)$aktuellesJahr; ?></a>
    </form>

    <?php if (!empty($flashOk)): ?>
        <div class="success">
            <?php echo htmlspecialchars((string)$flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (count($zeilen) === 0): ?>
        <p>Es sind noch keine Mitarbeiter angelegt.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Aktiv</th>
                    <th>Anspruch (Standard)</th>
                    <th>Anspruch (Override)</th>
                    <th>Übertrag</th>
                    <th>Manuell (+/- Tage)</th>
                    <th title="Genommen inklusive Betriebsferien">Verbraucht</th>
                    <th title="Anspruch + Übertrag + Manuell − Verbraucht − Offen">Übrig</th>
                    <th>Notiz</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zeilen as $row): ?>
                    <?php
                        $id = (int)($row['id'] ?? 0);
                        $vorname  = trim((string)($row['vorname'] ?? ''));
                        $nachname = trim((string)($row['nachname'] ?? ''));
                        $aktiv = (int)($row['aktiv'] ?? 0) === 1;
                        $standardAnspruch = (string)($row['standard_anspruch_text'] ?? '');
                        $overrideText = (string)($row['override_text'] ?? '');
                        $korrekturText = (string)($row['korrektur_text'] ?? '');
                        $notiz = trim((string)($row['notiz'] ?? ''));

                        $saldo = is_array($row['saldo'] ?? null) ? $row['saldo'] : null;
                        $uebertragText  = $saldo !== null ? (string)($saldo['uebertrag'] ?? '') : '';
                        $verbrauchtText = $saldo !== null ? (string)($saldo['genommen'] ?? '') : '';
                        $offenText      = $saldo !== null ? (string)($saldo['beantragt'] ?? '0.00') : '';
                        $uebrigText     = $saldo !== null ? (string)($saldo['verbleibend'] ?? '') : '';
                        $uebrigNegativ  = $uebrigText !== '' && (float)$uebrigText < 0;
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars(trim($vorname . ' ' . $nachname), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                        <td><?php echo htmlspecialchars($standardAnspruch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($overrideText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $uebertragText !== '' ? htmlspecialchars($uebertragText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '<small>–</small>'; ?></td>
                        <td><?php echo htmlspecialchars($korrekturText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <?php echo $verbrauchtText !== '' ? htmlspecialchars($verbrauchtText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '<small>–</small>'; ?>
                            <?php if ($offenText !== '' && (float)$offenText > 0): ?>
                                <br><small>+ <?php echo htmlspecialchars($offenText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> offen</small>
                            <?php endif; ?>
                        </td>
                        <td<?php echo $uebrigNegativ ? ' class="fehlermeldung"' : ''; ?>>
                            <strong><?php echo $uebrigText !== '' ? htmlspecialchars($uebrigText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '–'; ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($notiz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <a href="?seite=urlaub_kontingent_admin_bearbeiten&amp;mitarbeiter_id=<?php echo $id; ?>&amp;jahr=<?php echo (int)$jahr; ?>">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 1rem;">
            <small>
                Anspruch(Standard) = <code>urlaub_monatsanspruch * 12</code>; ein gesetzter Override ersetzt ihn.
                Der <strong>Übertrag</strong> kommt aus dem Resturlaub des Vorjahres und wird festgeschrieben, sobald er einmal berechnet ist.
                <strong>Manuell (+/- Tage)</strong> ist eine direkte Korrektur (z. B. Tage gutschreiben oder abziehen).
                <br><strong>Verbraucht</strong> enthält bereits die <strong>Betriebsferien</strong> dieses Jahres; „offen" sind beantragte, noch nicht entschiedene Tage.
                <strong>Übrig</strong> ist das, was der Mitarbeiter noch nehmen kann: Anspruch + Übertrag + Manuell − Verbraucht − Offen.
            </small>
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
