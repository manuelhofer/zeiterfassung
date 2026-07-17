<?php
declare(strict_types=1);
/**
 * Template: Urlaubsverwaltung
 *
 * Erwartet:
 * - $antraege (array<int,array<string,mixed>>)
 * - $mitarbeiterListe (array<int,array<string,mixed>>)
 * - $csrfToken (string)
 * - $meldung (string|null)
 * - $fehlermeldung (string|null)
 * - $filterMitarbeiterId (int)
 * - $filterStatus (string)
 * - $filterJahr (int)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $antraege */
$antraege = $antraege ?? [];

/** @var array<int,array<string,mixed>> $mitarbeiterListe */
$mitarbeiterListe = $mitarbeiterListe ?? [];

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$meldung = $meldung ?? null;
$fehlermeldung = $fehlermeldung ?? null;
$filterMitarbeiterId = (int)($filterMitarbeiterId ?? 0);
$filterStatus = (string)($filterStatus ?? 'aktiv');
$filterJahr = (int)($filterJahr ?? (int)date('Y'));
$heute = (new DateTimeImmutable('today'))->format('Y-m-d');

$fmtDatum = static function (string $wert): string {
    $wert = trim($wert);
    if ($wert === '') {
        return '';
    }

    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1) {
            return (new DateTimeImmutable($wert))->format('d.m.Y');
        }
    } catch (Throwable $e) {
        return $wert;
    }

    return $wert;
};

$fmtDatumZeit = static function (string $wert): string {
    $wert = trim($wert);
    if ($wert === '') {
        return '';
    }

    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $wert) === 1) {
            return (new DateTimeImmutable($wert))->format('d.m.Y H:i');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1) {
            return (new DateTimeImmutable($wert))->format('d.m.Y');
        }
    } catch (Throwable $e) {
        return $wert;
    }

    return $wert;
};

$fmtTage = static function ($wert): string {
    if (is_numeric($wert)) {
        return number_format((float)$wert, 2, ',', '.');
    }

    return (string)$wert;
};

$mitarbeiterName = static function (array $m): string {
    $vorname = trim((string)($m['vorname'] ?? ''));
    $nachname = trim((string)($m['nachname'] ?? ''));
    $teile = [];
    if ($nachname !== '') {
        $teile[] = $nachname;
    }
    if ($vorname !== '') {
        $teile[] = $vorname;
    }
    if ($teile !== []) {
        return implode(', ', $teile);
    }

    $benutzername = trim((string)($m['benutzername'] ?? ''));
    return $benutzername !== '' ? $benutzername : 'Mitarbeiter #' . (int)($m['id'] ?? 0);
};

$statusLabel = static function (string $status): string {
    switch ($status) {
        case 'offen':
            return 'offen';
        case 'genehmigt':
            return 'genehmigt';
        case 'abgelehnt':
            return 'abgelehnt';
        case 'storniert':
            return 'storniert';
        default:
            return $status;
    }
};
?>

<style>
    .urlaub-admin-card {
        border: 1px solid #d5dde2;
        border-radius: 8px;
        background: #fff;
        padding: 0.85rem 1rem;
        margin-bottom: 0.9rem;
    }

    .urlaub-admin-note {
        color: #8a4b00;
        background: #fff3cd;
        border: 1px solid #ffdf7e;
        border-radius: 6px;
        padding: 0.45rem 0.65rem;
        margin: 0.5rem 0 0;
    }

    .urlaub-status {
        display: inline-block;
        border-radius: 999px;
        padding: 0.1rem 0.5rem;
        border: 1px solid #d5dde2;
        background: #f7f9fb;
        white-space: nowrap;
    }

    .urlaub-status-offen {
        background: #fff3cd;
        border-color: #ffdf7e;
        color: #8a4b00;
    }

    .urlaub-status-genehmigt {
        background: #e8f5e9;
        border-color: #c8e6c9;
        color: #1b5e20;
    }

    .urlaub-status-abgelehnt,
    .urlaub-status-storniert {
        background: #eceff1;
        border-color: #cfd8dc;
        color: #455a64;
    }
</style>

<section>
    <h2>Urlaubsverwaltung</h2>

    <?php if ($meldung !== null && $meldung !== ''): ?>
        <p class="success"><?php echo htmlspecialchars($meldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($fehlermeldung !== null && $fehlermeldung !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <div class="urlaub-admin-card">
        <form method="get" action="">
            <input type="hidden" name="seite" value="urlaub_verwaltung">

            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label for="mitarbeiter_id"><strong>Mitarbeiter</strong></label><br>
                    <select id="mitarbeiter_id" name="mitarbeiter_id">
                        <option value="0">Alle sichtbaren</option>
                        <?php foreach ($mitarbeiterListe as $m): ?>
                            <?php $mid = (int)($m['id'] ?? 0); ?>
                            <?php if ($mid > 0): ?>
                                <option value="<?php echo (int)$mid; ?>"<?php echo $filterMitarbeiterId === $mid ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mitarbeiterName($m), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status"><strong>Status</strong></label><br>
                    <select id="status" name="status">
                        <option value="aktiv"<?php echo $filterStatus === 'aktiv' ? ' selected' : ''; ?>>Offen + genehmigt</option>
                        <option value="alle"<?php echo $filterStatus === 'alle' ? ' selected' : ''; ?>>Alle</option>
                        <option value="offen"<?php echo $filterStatus === 'offen' ? ' selected' : ''; ?>>Offen</option>
                        <option value="genehmigt"<?php echo $filterStatus === 'genehmigt' ? ' selected' : ''; ?>>Genehmigt</option>
                        <option value="abgelehnt"<?php echo $filterStatus === 'abgelehnt' ? ' selected' : ''; ?>>Abgelehnt</option>
                        <option value="storniert"<?php echo $filterStatus === 'storniert' ? ' selected' : ''; ?>>Storniert</option>
                    </select>
                </div>

                <div>
                    <label for="jahr"><strong>Jahr</strong></label><br>
                    <input id="jahr" name="jahr" type="number" min="2000" max="2100" value="<?php echo (int)$filterJahr; ?>" style="width:7rem;">
                </div>

                <button type="submit">Anzeigen</button>
            </div>
        </form>

        <p class="urlaub-admin-note">
            Stornieren/Rücknehmen setzt den Antrag auf <strong>storniert</strong>. Der Eintrag bleibt zur Nachvollziehbarkeit erhalten.
        </p>
    </div>

    <?php if ($antraege === []): ?>
        <p>Keine Urlaubsanträge für die aktuelle Auswahl gefunden.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Tage</th>
                    <th>Status</th>
                    <th>Antrag</th>
                    <th>Entscheidung</th>
                    <th>Kommentar</th>
                    <th>Aktion</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($antraege as $a): ?>
                    <?php
                    $antragId = (int)($a['id'] ?? 0);
                    $status = (string)($a['status'] ?? '');
                    $kannStornieren = in_array($status, ['offen', 'genehmigt'], true) && $antragId > 0;
                    $kommentarMitarbeiter = trim((string)($a['kommentar_mitarbeiter'] ?? ''));
                    $kommentarGenehmiger = trim((string)($a['kommentar_genehmiger'] ?? ''));
                    $entscheidungsDatum = trim((string)($a['entscheidungs_datum'] ?? ''));
                    $entscheidungsName = trim((string)($a['entscheidungs_mitarbeiter_name'] ?? ''));
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)($a['mitarbeiter_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($fmtDatum((string)($a['von_datum'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($fmtDatum((string)($a['bis_datum'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($fmtTage($a['tage_gesamt'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <span class="urlaub-status urlaub-status-<?php echo htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusLabel($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($fmtDatumZeit((string)($a['antrags_datum'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($entscheidungsDatum !== '' || $entscheidungsName !== ''): ?>
                                <?php echo htmlspecialchars($fmtDatumZeit($entscheidungsDatum), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                <?php if ($entscheidungsName !== ''): ?>
                                    <br><small>durch <?php echo htmlspecialchars($entscheidungsName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="max-width:360px;">
                            <?php if ($kommentarMitarbeiter === '' && $kommentarGenehmiger === ''): ?>
                                -
                            <?php else: ?>
                                <?php if ($kommentarMitarbeiter !== ''): ?>
                                    <small><strong>Mitarbeiter:</strong></small><br>
                                    <?php echo nl2br(htmlspecialchars($kommentarMitarbeiter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?>
                                <?php endif; ?>
                                <?php if ($kommentarGenehmiger !== ''): ?>
                                    <?php if ($kommentarMitarbeiter !== ''): ?>
                                        <hr style="border:none;border-top:1px solid #eee;margin:0.45rem 0;">
                                    <?php endif; ?>
                                    <small><strong>Genehmigung/Storno:</strong></small><br>
                                    <?php echo nl2br(htmlspecialchars($kommentarGenehmiger, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($kannStornieren): ?>
                                <form method="post" action="?seite=urlaub_verwaltung" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <input type="hidden" name="aktion" value="stornieren">
                                    <input type="hidden" name="antrag_id" value="<?php echo (int)$antragId; ?>">
                                    <input type="hidden" name="filter_mitarbeiter_id" value="<?php echo (int)$filterMitarbeiterId; ?>">
                                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filterStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <input type="hidden" name="filter_jahr" value="<?php echo (int)$filterJahr; ?>">
                                    <textarea name="storno_begruendung" rows="2" cols="24" required placeholder="Begründung (Pflicht)"></textarea><br>
                                    <button type="submit" onclick="return confirm('Urlaubsantrag wirklich stornieren/rückgängig machen?');">Stornieren</button>
                                </form>
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

    <div class="urlaub-admin-card">
        <h3 style="margin-top:0;">Urlaub direkt eintragen</h3>
        <form method="post" action="?seite=urlaub_verwaltung">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="aktion" value="direkt_eintragen">
            <input type="hidden" name="filter_mitarbeiter_id" value="<?php echo (int)$filterMitarbeiterId; ?>">
            <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filterStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="filter_jahr" value="<?php echo (int)$filterJahr; ?>">

            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label for="urlaub_neu_mitarbeiter_id"><strong>Mitarbeiter</strong></label><br>
                    <select id="urlaub_neu_mitarbeiter_id" name="urlaub_neu_mitarbeiter_id" required>
                        <option value="">-- bitte wählen --</option>
                        <?php foreach ($mitarbeiterListe as $m): ?>
                            <?php $mid = (int)($m['id'] ?? 0); ?>
                            <?php if ($mid > 0): ?>
                                <option value="<?php echo (int)$mid; ?>"<?php echo $filterMitarbeiterId === $mid ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mitarbeiterName($m), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="urlaub_neu_von_datum"><strong>Von</strong></label><br>
                    <input id="urlaub_neu_von_datum" name="urlaub_neu_von_datum" type="date" value="<?php echo htmlspecialchars($heute, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="urlaub_neu_bis_datum"><strong>Bis</strong></label><br>
                    <input id="urlaub_neu_bis_datum" name="urlaub_neu_bis_datum" type="date" value="<?php echo htmlspecialchars($heute, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </div>

                <div style="min-width:260px;flex:1;">
                    <label for="urlaub_neu_begruendung"><strong>Begründung</strong> (Pflicht)</label><br>
                    <input id="urlaub_neu_begruendung" name="urlaub_neu_begruendung" type="text" maxlength="2000" placeholder="z. B. mündlich mitgeteilt" required style="width:100%;">
                </div>

                <button type="submit" onclick="return confirm('Urlaub direkt als genehmigt eintragen?');">Genehmigten Urlaub eintragen</button>
            </div>

            <p style="margin-bottom:0;color:#5d6b73;">
                Der Eintrag wird sofort als genehmigter Urlaub gespeichert und erscheint danach normal in Saldo, Jahresübersicht und Verwaltung.
            </p>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
