<?php
declare(strict_types=1);
/**
 * Template: Urlaub Jahresuebersicht
 *
 * Erwartet:
 * - $jahr (int)
 * - $wochenendenAnzeigen (bool)
 * - $planung (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$jahr = isset($jahr) ? (int)$jahr : (int)date('Y');
$wochenendenAnzeigen = !empty($wochenendenAnzeigen);
$planung = isset($planung) && is_array($planung) ? $planung : [];
$fehlermeldung = $fehlermeldung ?? null;
?>

<style>
    .urlaub-jahresuebersicht-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: flex-end;
        margin: 0 0 1rem 0;
    }

    .urlaub-jahresuebersicht-toolbar label {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .urlaub-jahresuebersicht-toolbar .check {
        flex-direction: row;
        align-items: center;
        padding-bottom: 0.35rem;
    }

    .urlaub-jahresuebersicht-legende {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin: 0.4rem 0 1rem 0;
        font-size: 0.9rem;
    }

    .urlaub-jahresuebersicht-legende span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .urlaub-jahresuebersicht-swatch {
        width: 0.9rem;
        height: 0.9rem;
        border: 1px solid #b0bec5;
        display: inline-block;
    }

    .urlaub-jahresuebersicht-scroll {
        overflow-x: auto;
        max-width: 100%;
    }

    table.urlaub-jahresuebersicht-table {
        width: auto;
        min-width: 980px;
        table-layout: fixed;
        margin-bottom: 1.2rem;
        font-size: 0.78rem;
    }

    table.urlaub-jahresuebersicht-table th,
    table.urlaub-jahresuebersicht-table td {
        padding: 0.18rem 0.22rem;
        text-align: center;
        vertical-align: middle;
        min-width: 1.65rem;
        height: 1.65rem;
    }

    table.urlaub-jahresuebersicht-table th.plan-name,
    table.urlaub-jahresuebersicht-table td.plan-name {
        min-width: 10.5rem;
        text-align: left;
        font-weight: 600;
        white-space: nowrap;
    }

    table.urlaub-jahresuebersicht-table th.plan-metric,
    table.urlaub-jahresuebersicht-table td.plan-metric {
        min-width: 4.7rem;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .plan-day-head {
        font-weight: 600;
        line-height: 1.05;
    }

    .plan-weekend {
        background: #eeeeee;
        color: #607d8b;
    }

    .plan-empty {
        color: #90a4ae;
    }

    .plan-u {
        background: #bbdefb;
        font-weight: 700;
    }

    .plan-open {
        background: #ffe0b2;
        font-weight: 700;
    }

    .plan-bf {
        background: #fff9c4;
        font-weight: 700;
    }

    .plan-ft {
        background: #dcedc8;
        font-weight: 700;
    }

    .plan-krank-lf {
        background: #ffcdd2;
        font-weight: 700;
    }

    .plan-krank-kk {
        background: #ef9a9a;
        font-weight: 700;
    }

    .plan-arzt {
        background: #d1c4e9;
        font-weight: 700;
    }

    .plan-kurzarbeit {
        background: #b2dfdb;
        font-weight: 700;
    }

    .plan-sonstiges {
        background: #cfd8dc;
        font-weight: 700;
    }

    .plan-open a {
        display: block;
        color: inherit;
        text-decoration: underline;
    }

    .planung-monat-kopf {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.5rem;
        align-items: baseline;
        margin: 1rem 0 0.35rem 0;
    }

    .planung-monat-kopf small {
        color: #607d8b;
    }
</style>

<section>
    <h2>Jahres&uuml;bersicht <?php echo (int)$jahr; ?></h2>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php else: ?>
        <form class="urlaub-jahresuebersicht-toolbar" method="get" action="">
            <input type="hidden" name="seite" value="urlaub_jahresuebersicht">
            <label>
                Jahr
                <input type="number" name="jahr" min="2000" max="2100" value="<?php echo (int)$jahr; ?>">
            </label>
            <label class="check">
                <input type="checkbox" name="wochenende" value="1"<?php echo $wochenendenAnzeigen ? ' checked' : ''; ?>>
                Sa/So anzeigen
            </label>
            <button type="submit">Anzeigen</button>
        </form>

        <div class="urlaub-jahresuebersicht-legende" aria-label="Legende">
            <span><i class="urlaub-jahresuebersicht-swatch plan-u"></i> U = genehmigter Urlaub</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-open"></i> O = beantragter Urlaub, noch nicht genehmigt</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-bf"></i> BF = Betriebsferien</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-ft"></i> FT = Feiertag</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-krank-lf"></i> LF = Krank LF (Lohnfortzahlung)</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-krank-kk"></i> KK = Krank KK (Krankenkasse)</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-arzt"></i> A = Arzt</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-kurzarbeit"></i> KA = Kurzarbeit</span>
            <span><i class="urlaub-jahresuebersicht-swatch plan-sonstiges"></i> S = Sonstiges</span>
            <span>UeStd/Url stehen auf "offen", solange fuer den Mitarbeiter kein Monatsabschluss vorhanden ist.</span>
        </div>

        <?php if ($planung === []): ?>
            <p>Keine Mitarbeiter fuer diese Jahresuebersicht gefunden.</p>
        <?php else: ?>
            <?php foreach ($planung as $monat): ?>
                <?php
                    $monatName = (string)($monat['name'] ?? '');
                    $tage = isset($monat['tage']) && is_array($monat['tage']) ? $monat['tage'] : [];
                    $rows = isset($monat['rows']) && is_array($monat['rows']) ? $monat['rows'] : [];
                    $abgeschlossen = (int)($monat['abgeschlossen_count'] ?? 0);
                    $gesamt = (int)($monat['mitarbeiter_count'] ?? 0);
                ?>
                <div class="planung-monat-kopf">
                    <h3><?php echo htmlspecialchars($monatName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>
                    <small>Monatswerte: <?php echo $abgeschlossen; ?> / <?php echo $gesamt; ?> Mitarbeiter</small>
                </div>

                <div class="urlaub-jahresuebersicht-scroll">
                    <table class="urlaub-jahresuebersicht-table">
                        <thead>
                        <tr>
                            <th class="plan-name">Mitarbeiter</th>
                            <th class="plan-metric">UeStd</th>
                            <th class="plan-metric">Url</th>
                            <?php foreach ($tage as $tag): ?>
                                <?php
                                    $tagNr = (int)($tag['tag'] ?? 0);
                                    $wt = (string)($tag['wochentag'] ?? '');
                                    $istWochenende = !empty($tag['wochenende']);
                                ?>
                                <th class="plan-day-head<?php echo $istWochenende ? ' plan-weekend' : ''; ?>">
                                    <?php echo htmlspecialchars($wt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
                                    <?php echo $tagNr; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $name = (string)($row['name'] ?? '');
                                $ueberstunden = (string)($row['ueberstunden'] ?? '-');
                                $urlaub = (string)($row['urlaub'] ?? '-');
                                $cells = isset($row['cells']) && is_array($row['cells']) ? $row['cells'] : [];
                                $kannGenehmigen = !empty($row['kann_genehmigen']);
                            ?>
                            <tr>
                                <td class="plan-name"><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td class="plan-metric<?php echo $ueberstunden === 'offen' ? ' plan-empty' : ''; ?>"><?php echo htmlspecialchars($ueberstunden, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td class="plan-metric<?php echo $urlaub === 'offen' ? ' plan-empty' : ''; ?>"><?php echo htmlspecialchars($urlaub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>

                                <?php foreach ($tage as $tag): ?>
                                    <?php
                                        $tagNr = (int)($tag['tag'] ?? 0);
                                        $event = $cells[$tagNr] ?? null;
                                        $code = is_array($event) ? (string)($event['code'] ?? '') : '';
                                        $class = is_array($event) ? (string)($event['class'] ?? '') : '';
                                        $label = is_array($event) ? (string)($event['label'] ?? '') : '';
                                        $antragId = is_array($event) ? (int)($event['antrag_id'] ?? 0) : 0;
                                        $istWochenende = !empty($tag['wochenende']);
                                        $tdClass = trim(($istWochenende ? 'plan-weekend ' : '') . ($class !== '' ? 'plan-' . $class : ''));
                                    ?>
                                    <td class="<?php echo htmlspecialchars($tdClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <?php if ($code === 'O' && $antragId > 0 && $kannGenehmigen): ?>
                                            <a href="?seite=urlaub_genehmigung&amp;antrag_id=<?php echo (int)$antragId; ?>#antrag-<?php echo (int)$antragId; ?>">
                                                <?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
