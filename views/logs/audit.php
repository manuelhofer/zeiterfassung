<?php
declare(strict_types=1);
/**
 * Template: Audit-Logs
 *
 * Erwartet:
 * - $eintraege
 * - $mitarbeiterListe
 * - $filterOptionen
 * - $filterTyp
 * - $filterMitarbeiterId
 * - $filterVon
 * - $filterBis
 * - $limit
 * - $fehlermeldung
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $eintraege */
$eintraege = $eintraege ?? [];

/** @var array<int,array<string,mixed>> $mitarbeiterListe */
$mitarbeiterListe = $mitarbeiterListe ?? [];

/** @var array<string,string> $filterOptionen */
$filterOptionen = $filterOptionen ?? [];

$filterTyp = (string)($filterTyp ?? 'alle');
$filterMitarbeiterId = (int)($filterMitarbeiterId ?? 0);
$filterVon = (string)($filterVon ?? '');
$filterBis = (string)($filterBis ?? '');
$limit = (int)($limit ?? 200);
$fehlermeldung = $fehlermeldung ?? null;

$h = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$mitarbeiterName = static function (array $m): string {
    $vorname = trim((string)($m['vorname'] ?? ''));
    $nachname = trim((string)($m['nachname'] ?? ''));
    $name = trim($vorname . ' ' . $nachname);
    if ($name !== '') {
        return $name;
    }

    $benutzername = trim((string)($m['benutzername'] ?? ''));
    return $benutzername !== '' ? $benutzername : 'Mitarbeiter #' . (int)($m['id'] ?? 0);
};
?>

<style>
    .audit-card {
        border: 1px solid #d5dde2;
        border-radius: 8px;
        background: #fff;
        padding: 0.85rem 1rem;
        margin-bottom: 0.9rem;
    }

    .audit-note {
        color: #8a4b00;
        background: #fff3cd;
        border: 1px solid #ffdf7e;
        border-radius: 6px;
        padding: 0.55rem 0.7rem;
        margin: 0.65rem 0 0;
    }

    .audit-filter-row {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .audit-filter-row label {
        display: block;
    }

    .audit-details pre {
        max-width: 42rem;
        max-height: 18rem;
        overflow: auto;
        white-space: pre-wrap;
        background: #f7f9fb;
        border: 1px solid #d5dde2;
        border-radius: 6px;
        padding: 0.6rem;
        margin: 0.45rem 0 0;
        font-size: 0.82rem;
    }

    .audit-level {
        display: inline-block;
        min-width: 3.2rem;
        border-radius: 999px;
        border: 1px solid #d5dde2;
        padding: 0.08rem 0.45rem;
        text-align: center;
        font-size: 0.78rem;
        background: #f7f9fb;
    }

    .audit-level-WARN {
        color: #8a4b00;
        border-color: #ffdf7e;
        background: #fff8e1;
    }

    .audit-level-ERROR {
        color: #a82222;
        border-color: #e2a5a5;
        background: #fff1f1;
    }
</style>

<section>
    <h2>Logs</h2>

    <div class="audit-card">
        <form method="get" action="">
            <input type="hidden" name="seite" value="audit_logs">

            <div class="audit-filter-row">
                <div>
                    <label for="typ"><strong>Bereich</strong></label>
                    <select id="typ" name="typ">
                        <?php foreach ($filterOptionen as $wert => $label): ?>
                            <option value="<?php echo $h($wert); ?>"<?php echo $filterTyp === $wert ? ' selected' : ''; ?>>
                                <?php echo $h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="mitarbeiter_id"><strong>Mitarbeiter</strong></label>
                    <select id="mitarbeiter_id" name="mitarbeiter_id">
                        <option value="0">Alle</option>
                        <?php foreach ($mitarbeiterListe as $m): ?>
                            <?php $mid = (int)($m['id'] ?? 0); ?>
                            <?php if ($mid > 0): ?>
                                <option value="<?php echo (int)$mid; ?>"<?php echo $filterMitarbeiterId === $mid ? ' selected' : ''; ?>>
                                    <?php echo $h($mitarbeiterName($m)); ?>
                                    <?php if ((int)($m['aktiv'] ?? 1) !== 1): ?>
                                        (inaktiv)
                                    <?php endif; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="von"><strong>Von</strong></label>
                    <input id="von" name="von" type="date" value="<?php echo $h($filterVon); ?>">
                </div>

                <div>
                    <label for="bis"><strong>Bis</strong></label>
                    <input id="bis" name="bis" type="date" value="<?php echo $h($filterBis); ?>">
                </div>

                <div>
                    <label for="limit"><strong>Anzahl</strong></label>
                    <select id="limit" name="limit">
                        <?php foreach ([50, 100, 200, 500] as $optLimit): ?>
                            <option value="<?php echo (int)$optLimit; ?>"<?php echo $limit === $optLimit ? ' selected' : ''; ?>>
                                <?php echo (int)$optLimit; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Anzeigen</button>
            </div>
        </form>

        <p class="audit-note">
            Diese Ansicht ist nur lesend. Stealth-Buchungen aus dem Stundenkonto werden hier bewusst nicht angezeigt.
        </p>
    </div>

    <?php if ($fehlermeldung !== null && $fehlermeldung !== ''): ?>
        <p class="error"><?php echo $h($fehlermeldung); ?></p>
    <?php endif; ?>

    <?php if ($eintraege === []): ?>
        <p>Keine Log-Eintr&auml;ge f&uuml;r die aktuelle Auswahl gefunden.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Zeit</th>
                        <th>Level</th>
                        <th>Bereich</th>
                        <th>Aktion</th>
                        <th>Wer</th>
                        <th>Mitarbeiter</th>
                        <th>Datum/Zeitraum</th>
                        <th>Warum</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eintraege as $e): ?>
                        <?php $level = (string)($e['level'] ?? ''); ?>
                        <tr>
                            <td><?php echo $h($e['zeit'] ?? ''); ?></td>
                            <td><span class="audit-level audit-level-<?php echo $h($level); ?>"><?php echo $h($level !== '' ? $level : '-'); ?></span></td>
                            <td><?php echo $h($e['bereich'] ?? ''); ?></td>
                            <td><?php echo $h($e['aktion'] ?? ''); ?></td>
                            <td><?php echo $h($e['wer'] ?? ''); ?></td>
                            <td><?php echo $h($e['ziel'] ?? ''); ?></td>
                            <td><?php echo $h($e['zeitraum'] ?? ''); ?></td>
                            <td style="max-width:28rem;"><?php echo nl2br($h($e['warum'] ?? '')); ?></td>
                            <td class="audit-details">
                                <?php if (trim((string)($e['details'] ?? '')) !== ''): ?>
                                    <details>
                                        <summary>Details</summary>
                                        <pre><?php echo $h($e['details']); ?></pre>
                                    </details>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
