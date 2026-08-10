<?php
declare(strict_types=1);
/**
 * Template: Liste offener Urlaubsanträge zur Genehmigung
 *
 * Erwartet:
 * - $antraege (array<int,array<string,mixed>>)
 * - $csrfToken (string)
 * - $meldung (string|null)
 * - $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $antraege */
$antraege = $antraege ?? [];

/** @var string $csrfToken */
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$meldung      = $meldung ?? null;
$fehlermeldung = $fehlermeldung ?? null;
$markierterAntragId = isset($_GET['antrag_id']) ? (int)$_GET['antrag_id'] : 0;
?>

<section>
    <div class="page-header">
        <div>
    <h2>Offene Urlaubsanträge</h2>

        </div>
    </div>

    <?php if ($meldung !== null && $meldung !== ''): ?>
        <p class="success"><?php echo htmlspecialchars($meldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($fehlermeldung !== null && $fehlermeldung !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($antraege === []): ?>
        <p>Es liegen keine offenen Urlaubsanträge vor.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Tage gesamt</th>
                    <th>Resturlaub</th>
                <th>Kommentar Mitarbeiter</th>
                <th>Aktion</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($antraege as $a): ?>
                <?php
                    $antragId = (int)($a['id'] ?? 0);
                    $mitarbeiterName = (string)($a['mitarbeiter_name'] ?? '');
                    $von = (string)($a['von_datum'] ?? '');
                    $bis = (string)($a['bis_datum'] ?? '');
                    $tage = (string)($a['tage_gesamt'] ?? '');
                    $saldoVorschau = $a['saldo_vorschau'] ?? [];
                    $saldoWarnungAktiv = (int)($a['saldo_warnung_aktiv'] ?? 0) === 1;
                    $kommentarMitarbeiter = (string)($a['kommentar_mitarbeiter'] ?? '');
                ?>
                <tr id="antrag-<?php echo (int)$antragId; ?>"<?php echo ($markierterAntragId > 0 && $markierterAntragId === $antragId) ? ' class="zeile-markiert"' : ''; ?>>
                    <td><?php echo htmlspecialchars($mitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($tage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td style="min-width: 260px;">
                        <?php if (is_array($saldoVorschau) && $saldoVorschau !== []): ?>
                            <?php
                                // Vorname allein: „Marc hat noch 32 Tage" liest sich
                                // schneller als eine Zeile aus Pfeilen und Klammern.
                                $vorname = trim(explode(' ', trim($mitarbeiterName))[0] ?? '');
                                if ($vorname === '') { $vorname = $mitarbeiterName; }
                                $kurz = static function (string $wert): string {
                                    if ($wert === '') { return $wert; }
                                    $zahl = (float)str_replace(',', '.', $wert);
                                    return rtrim(rtrim(number_format($zahl, 2, ',', '.'), '0'), ',');
                                };
                            ?>
                            <?php foreach ($saldoVorschau as $sv): ?>
                                <?php
                                    $sjahr = (int)($sv['jahr'] ?? 0);
                                    $svor = (string)($sv['verfuegbar_vor'] ?? '');
                                    $snach = (string)($sv['nach_genehmigung'] ?? '');
                                    $stage = (string)($sv['tage_antrag'] ?? '');
                                    $swarn = !empty($sv['warnung']);
                                ?>
                                <div style="margin-bottom:0.35rem;">
                                    <?php echo htmlspecialchars($vorname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                    hat <?php echo (int)$sjahr; ?> noch
                                    <strong><?php echo htmlspecialchars($kurz($svor), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage</strong>
                                    übrig.
                                    <br>
                                    <small>
                                        Nach dieser Genehmigung:
                                        <strong<?php echo ($saldoWarnungAktiv && $swarn) ? ' class="error"' : ''; ?>><?php echo htmlspecialchars($kurz($snach), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage</strong>
                                        (−<?php echo htmlspecialchars($kurz($stage), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                                        <?php if ($saldoWarnungAktiv && $swarn): ?>
                                            <br><span class="error">Achtung: damit im Minus</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                            <small style="opacity:0.75;">Betriebsferien sind bereits abgezogen.</small>
                        <?php else: ?>
                            <span style="opacity:0.75;">–</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width: 360px; white-space: pre-wrap; text-align:center; vertical-align:middle;">
                        <?php echo htmlspecialchars($kommentarMitarbeiter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </td>
                    <td>
                        <form method="post" action="?seite=urlaub_genehmigung" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <input type="hidden" name="antrag_id" value="<?php echo (int)$antragId; ?>">

                            <textarea name="kommentar_genehmiger" rows="2" cols="22" placeholder="Kommentar (optional)"></textarea>

                            <button type="submit" name="aktion" value="genehmigen" onclick="return confirm('Urlaubsantrag genehmigen?');">Genehmigen</button>
                            <button type="submit" name="aktion" value="ablehnen" onclick="return confirm('Urlaubsantrag ablehnen?');">Ablehnen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
