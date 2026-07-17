<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Stundenkonto pro Mitarbeiter.
 *
 * Erwartet:
 * - $mitarbeiterListe (array<int,array<string,mixed>>)
 * - $mitarbeiter (array<string,mixed>|null)
 * - $id (int)
 * - $fehlermeldung (string|null)
 * - $successmeldung (string|null)
 * - $stundenkontoDarfVerwalten (bool)
 * - $stundenkontoSaldoAktuellText (string|null)
 * - $stundenkontoLetzteKorrekturen (array<int,array<string,mixed>>)
 * - $stundenkontoLetzteBatches (array<int,array<string,mixed>>)
 * - $stundenkontoStealthMode (bool)
 * - $stundenkontoAnsicht (string)
 * - $stundenkontoUmbuchungJahr (int)
 * - $stundenkontoUmbuchungMonat (int)
 * - $stundenkontoUmbuchungTageswerte (array<int,array<string,mixed>>)
 */

require __DIR__ . '/../layout/header.php';

$mitarbeiterListe = isset($mitarbeiterListe) && is_array($mitarbeiterListe) ? $mitarbeiterListe : [];
$mitarbeiter = isset($mitarbeiter) && is_array($mitarbeiter) ? $mitarbeiter : null;
$id = isset($id) ? (int)$id : 0;
$fehlermeldung = $fehlermeldung ?? null;
$successmeldung = $successmeldung ?? null;
$stundenkontoDarfVerwalten = !empty($stundenkontoDarfVerwalten);
$stundenkontoSaldoAktuellText = $stundenkontoSaldoAktuellText ?? null;
$stundenkontoLetzteKorrekturen = isset($stundenkontoLetzteKorrekturen) && is_array($stundenkontoLetzteKorrekturen) ? $stundenkontoLetzteKorrekturen : [];
$stundenkontoLetzteBatches = isset($stundenkontoLetzteBatches) && is_array($stundenkontoLetzteBatches) ? $stundenkontoLetzteBatches : [];
$stundenkontoStealthMode = !empty($stundenkontoStealthMode);
$stundenkontoAnsicht = (isset($stundenkontoAnsicht) && (string)$stundenkontoAnsicht === 'sammelumbuchung') ? 'sammelumbuchung' : 'konto';
$stealthStyle = $stundenkontoStealthMode ? ' style="border: 3px solid #c00; padding: 12px;"' : '';
$stundenkontoUmbuchungJahr = isset($stundenkontoUmbuchungJahr) ? (int)$stundenkontoUmbuchungJahr : (int)date('Y');
$stundenkontoUmbuchungMonat = isset($stundenkontoUmbuchungMonat) ? (int)$stundenkontoUmbuchungMonat : (int)date('n');
$stundenkontoUmbuchungPrevJahr = isset($stundenkontoUmbuchungPrevJahr) ? (int)$stundenkontoUmbuchungPrevJahr : $stundenkontoUmbuchungJahr;
$stundenkontoUmbuchungPrevMonat = isset($stundenkontoUmbuchungPrevMonat) ? (int)$stundenkontoUmbuchungPrevMonat : max(1, $stundenkontoUmbuchungMonat - 1);
$stundenkontoUmbuchungNextJahr = isset($stundenkontoUmbuchungNextJahr) ? (int)$stundenkontoUmbuchungNextJahr : $stundenkontoUmbuchungJahr;
$stundenkontoUmbuchungNextMonat = isset($stundenkontoUmbuchungNextMonat) ? (int)$stundenkontoUmbuchungNextMonat : min(12, $stundenkontoUmbuchungMonat + 1);
$stundenkontoUmbuchungTageswerte = isset($stundenkontoUmbuchungTageswerte) && is_array($stundenkontoUmbuchungTageswerte) ? $stundenkontoUmbuchungTageswerte : [];
$stundenkontoUmbuchungMonatswerte = isset($stundenkontoUmbuchungMonatswerte) && is_array($stundenkontoUmbuchungMonatswerte) ? $stundenkontoUmbuchungMonatswerte : null;
$stundenkontoUmbuchungZusammenfassung = isset($stundenkontoUmbuchungZusammenfassung) && is_array($stundenkontoUmbuchungZusammenfassung) ? $stundenkontoUmbuchungZusammenfassung : null;
$stundenkontoUmbuchungFehler = isset($stundenkontoUmbuchungFehler) ? (string)$stundenkontoUmbuchungFehler : '';

$monatsnamen = [
    1 => 'Januar',
    2 => 'Februar',
    3 => 'Maerz',
    4 => 'April',
    5 => 'Mai',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'August',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Dezember',
];

$wochentageKurz = [
    1 => 'Mo',
    2 => 'Di',
    3 => 'Mi',
    4 => 'Do',
    5 => 'Fr',
    6 => 'Sa',
    7 => 'So',
];

$mitarbeiterName = static function (array $row): string {
    $name = trim((string)($row['vorname'] ?? '') . ' ' . (string)($row['nachname'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['benutzername'] ?? ''));
    }
    if ($name === '') {
        $name = 'Mitarbeiter #' . (int)($row['id'] ?? 0);
    }
    return $name;
};

$fmtMin = static function (int $minuten): string {
    $sign = $minuten < 0 ? '-' : '+';
    $abs = abs($minuten);
    $h = intdiv($abs, 60);
    $m = $abs % 60;
    return sprintf('%s%d:%02d', $sign, $h, $m);
};

$fmtStunden = static function (mixed $wert): string {
    $norm = str_replace(',', '.', trim((string)$wert));
    $num = is_numeric($norm) ? (float)$norm : 0.0;
    return sprintf('%.2f', $num);
};

$fmtDatum = static function (string $datum): string {
    $datum = trim($datum);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        return $datum;
    }
    try {
        return (new \DateTimeImmutable($datum))->format('d.m.Y');
    } catch (\Throwable) {
        return $datum;
    }
};

$kontoLink = static function (int $mid, bool $stealth): string {
    $params = [
        'seite' => 'mitarbeiter_stundenkonto',
        'mitarbeiter_id' => $mid,
    ];
    if ($stealth) {
        $params['mode'] = 'stealth';
    }
    return '?' . http_build_query($params);
};

$umbuchungLink = static function (int $mid, int $monat, int $jahr, bool $stealth): string {
    $params = [
        'seite' => 'mitarbeiter_stundenkonto',
        'mitarbeiter_id' => $mid,
        'ansicht' => 'sammelumbuchung',
        'umbuchung_monat' => $monat,
        'umbuchung_jahr' => $jahr,
    ];
    if ($stealth) {
        $params['mode'] = 'stealth';
    }
    return '?' . http_build_query($params);
};
?>

<section<?php echo $stealthStyle; ?>>
    <div class="page-header">
        <div>
    <h2>Stundenkonto</h2>
        </div>
    </div>

    <?php if ($stundenkontoStealthMode): ?>
        <p class="error">
            Stealth-Modus aktiv: Buchungen werden als verdeckte Stundenkonto-Korrekturen gespeichert und in den normalen Listen nicht angezeigt.
        </p>
    <?php endif; ?>

    <?php if ($fehlermeldung !== null && $fehlermeldung !== ''): ?>
        <p class="error"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($successmeldung !== null && $successmeldung !== ''): ?>
        <p class="success"><?php echo htmlspecialchars((string)$successmeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="get" action="" class="toolbar">
        <input type="hidden" name="seite" value="mitarbeiter_stundenkonto">
        <?php if ($stundenkontoAnsicht === 'sammelumbuchung'): ?>
            <input type="hidden" name="ansicht" value="sammelumbuchung">
        <?php endif; ?>
        <?php if ($stundenkontoStealthMode): ?>
            <input type="hidden" name="mode" value="stealth">
        <?php endif; ?>
        <label>
            Mitarbeiter
            <select name="mitarbeiter_id">
                <option value="">-- Mitarbeiter auswaehlen --</option>
                <?php foreach ($mitarbeiterListe as $row): ?>
                    <?php
                        $mid = (int)($row['id'] ?? 0);
                        if ($mid <= 0) {
                            continue;
                        }
                    ?>
                    <option value="<?php echo $mid; ?>"<?php echo $mid === $id ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($mitarbeiterName($row), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($stundenkontoAnsicht === 'sammelumbuchung'): ?>
            <label>
                Monat
                <select name="umbuchung_monat">
                    <?php foreach ($monatsnamen as $monatNummer => $monatName): ?>
                        <option value="<?php echo (int)$monatNummer; ?>"<?php echo (int)$monatNummer === $stundenkontoUmbuchungMonat ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($monatName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Jahr
                <input type="number" name="umbuchung_jahr" min="1900" max="2200" value="<?php echo (int)$stundenkontoUmbuchungJahr; ?>" style="width: 5.5rem;">
            </label>
        <?php endif; ?>
        <button type="submit">Anzeigen</button>
    </form>

    <?php if ($id <= 0 || $mitarbeiter === null): ?>
        <p>Bitte waehle einen Mitarbeiter aus.</p>
    <?php else: ?>
        <h3><?php echo htmlspecialchars($mitarbeiterName($mitarbeiter), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>

        <p>
            <strong>Saldo Stand heute:</strong>
            <?php echo htmlspecialchars($stundenkontoSaldoAktuellText ?? '---', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>

        <?php if ($stundenkontoAnsicht === 'sammelumbuchung'): ?>
            <p>
                <a href="<?php echo htmlspecialchars($kontoLink($id, $stundenkontoStealthMode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">&laquo; Zurueck zum Stundenkonto</a>
            </p>

            <h4>Sammelumbuchung auf Zieltag buchen</h4>

            <p>
                <a href="<?php echo htmlspecialchars($umbuchungLink($id, $stundenkontoUmbuchungPrevMonat, $stundenkontoUmbuchungPrevJahr, $stundenkontoStealthMode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">&laquo; vorheriger Monat</a>
                |
                <strong><?php echo htmlspecialchars(($monatsnamen[$stundenkontoUmbuchungMonat] ?? (string)$stundenkontoUmbuchungMonat) . ' ' . (string)$stundenkontoUmbuchungJahr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                |
                <a href="<?php echo htmlspecialchars($umbuchungLink($id, $stundenkontoUmbuchungNextMonat, $stundenkontoUmbuchungNextJahr, $stundenkontoStealthMode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">naechster Monat &raquo;</a>
            </p>

            <form method="post" action="?seite=mitarbeiter_admin_speichern">
                <input type="hidden" name="stundenkonto_umbuchung_only" value="1">
                <input type="hidden" name="return_to" value="stundenkonto">
                <input type="hidden" name="return_ansicht" value="sammelumbuchung">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="return_umbuchung_monat" value="<?php echo (int)$stundenkontoUmbuchungMonat; ?>">
                <input type="hidden" name="return_umbuchung_jahr" value="<?php echo (int)$stundenkontoUmbuchungJahr; ?>">
                <?php if ($stundenkontoStealthMode): ?>
                    <input type="hidden" name="stundenkonto_stealth" value="1">
                <?php endif; ?>

                <?php if (!$stundenkontoDarfVerwalten): ?>
                    <p><small>Hinweis: Du hast nicht das Recht <code>STUNDENKONTO_VERWALTEN</code>.</small></p>
                <?php elseif ($stundenkontoUmbuchungFehler !== ''): ?>
                    <p class="error"><?php echo htmlspecialchars($stundenkontoUmbuchungFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php elseif ($stundenkontoUmbuchungTageswerte === []): ?>
                    <p><small>Fuer den angezeigten Monat liegen keine Tageswerte vor.</small></p>
                <?php else: ?>
                    <?php
                        $istMonatText = '';
                        if (is_array($stundenkontoUmbuchungZusammenfassung) && isset($stundenkontoUmbuchungZusammenfassung['iststunden'])) {
                            $istMonatText = (string)$stundenkontoUmbuchungZusammenfassung['iststunden'];
                        } elseif (is_array($stundenkontoUmbuchungMonatswerte) && isset($stundenkontoUmbuchungMonatswerte['iststunden'])) {
                            $istMonatText = (string)$stundenkontoUmbuchungMonatswerte['iststunden'];
                        }
                    ?>
                    <?php if ($istMonatText !== ''): ?>
                        <p><small>Ist-Stunden im angezeigten Monat: <?php echo htmlspecialchars($fmtStunden($istMonatText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small></p>
                    <?php endif; ?>

                    <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Tag</th>
                                <th>Ist-Stunden</th>
                                <th>Typ</th>
                                <th>Abziehen (Stunden)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stundenkontoUmbuchungTageswerte as $tag): ?>
                                <?php
                                    if (!is_array($tag)) {
                                        continue;
                                    }
                                    $datumIso = trim((string)($tag['datum'] ?? ''));
                                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datumIso)) {
                                        continue;
                                    }
                                    try {
                                        $tagDatum = new \DateTimeImmutable($datumIso);
                                    } catch (\Throwable) {
                                        continue;
                                    }
                                    $wochentagNummer = (int)$tagDatum->format('N');
                                    $wochentagLabel = $wochentageKurz[$wochentagNummer] ?? '';
                                    $istStunden = $fmtStunden($tag['arbeitszeit_stunden'] ?? '0');
                                    $tagestyp = trim((string)($tag['tagestyp'] ?? ''));
                                    $kommentar = trim((string)($tag['kommentar'] ?? ''));
                                    $typText = $tagestyp !== '' ? $tagestyp : '---';
                                    if ($kommentar !== '') {
                                        $typText .= ' / ' . $kommentar;
                                    }
                                    $rowStyle = $wochentagNummer >= 6 ? ' style="background: #fafafa;"' : '';
                                ?>
                                <tr<?php echo $rowStyle; ?>>
                                    <td><?php echo htmlspecialchars($fmtDatum($datumIso), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($wochentagLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($istStunden, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($typText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.25"
                                            min="0"
                                            name="stundenkonto_umbuchung_quell_stunden[<?php echo htmlspecialchars($datumIso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>]"
                                            placeholder="0.00"
                                            style="width: 7rem;"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div>
                        Zieltag
                        <label>
                            Tag
                            <select name="stundenkonto_umbuchung_ziel_tag" required>
                                <option value="">--</option>
                                <?php for ($tagNummer = 1; $tagNummer <= 31; $tagNummer++): ?>
                                    <option value="<?php echo (int)$tagNummer; ?>"><?php echo sprintf('%02d', $tagNummer); ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label>
                            Monat
                            <select name="stundenkonto_umbuchung_ziel_monat" required>
                                <?php foreach ($monatsnamen as $monatNummer => $monatName): ?>
                                    <option value="<?php echo (int)$monatNummer; ?>"<?php echo (int)$monatNummer === $stundenkontoUmbuchungMonat ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($monatName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Jahr
                            <input type="number" name="stundenkonto_umbuchung_ziel_jahr" min="1900" max="2200" value="<?php echo (int)$stundenkontoUmbuchungJahr; ?>" required style="width: 5.5rem;">
                        </label>
                    </div>

                    <div>
                        <label>
                            Begruendung
                            <input type="text" name="stundenkonto_umbuchung_begruendung" required placeholder="z.B. Migration: Samstag aus Altsystem">
                        </label>
                    </div>

                    <p>
                        <button type="submit">Sammelumbuchung buchen</button>
                    </p>
                <?php endif; ?>
            </form>
        <?php else: ?>

        <h4>Letzte Verteilbuchungen / manuelle Korrekturbuchungen</h4>

        <?php if (count($stundenkontoLetzteKorrekturen) > 0): ?>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Wirksam</th>
                        <th>Delta</th>
                        <th>Typ</th>
                        <th>Begruendung</th>
                        <th>Erstellt</th>
                        <th>Von</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stundenkontoLetzteKorrekturen as $row): ?>
                        <?php
                            $w = (string)($row['wirksam_datum'] ?? '');
                            $dmin = (int)($row['delta_minuten'] ?? 0);
                            $typ = (string)($row['typ'] ?? '');
                            $begr = (string)($row['begruendung'] ?? '');
                            $erst = (string)($row['erstellt_am'] ?? '');
                            $von = trim((string)($row['erstellt_von_vorname'] ?? '') . ' ' . (string)($row['erstellt_von_nachname'] ?? ''));
                            if ($von === '') {
                                $von = '---';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fmtDatum($w), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($fmtMin($dmin), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($begr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($erst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <p><small>Noch keine Stundenkonto-Korrekturen vorhanden.</small></p>
        <?php endif; ?>

        <h4>Letzte Verteilbuchungen (Batch)</h4>

        <?php if (count($stundenkontoLetzteBatches) > 0): ?>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zeitraum</th>
                        <th>Modus</th>
                        <th>Tage</th>
                        <th>Summe</th>
                        <th>Pro Tag</th>
                        <th>Arbeitstage</th>
                        <th>Begruendung</th>
                        <th>Erstellt</th>
                        <th>Von</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stundenkontoLetzteBatches as $b): ?>
                        <?php
                            $bid = (int)($b['id'] ?? 0);
                            $vonDatum = (string)($b['von_datum'] ?? '');
                            $bisDatum = (string)($b['bis_datum'] ?? '');
                            $modus = (string)($b['modus'] ?? '');
                            $anzahlTage = (int)($b['anzahl_tage'] ?? 0);
                            $gesamtMin = isset($b['gesamt_minuten']) ? (int)$b['gesamt_minuten'] : null;
                            $mpt = isset($b['minuten_pro_tag']) ? (int)$b['minuten_pro_tag'] : null;
                            $nur = (int)($b['nur_arbeitstage'] ?? 1) === 1;
                            $begr = (string)($b['begruendung'] ?? '');
                            $erst = (string)($b['erstellt_am'] ?? '');
                            $vonName = trim((string)($b['erstellt_von_vorname'] ?? '') . ' ' . (string)($b['erstellt_von_nachname'] ?? ''));
                            if ($vonName === '') {
                                $vonName = '---';
                            }

                            $modusLabel = $modus;
                            if ($modus === 'gesamt_gleichmaessig') {
                                $modusLabel = 'Gesamt gleichmaessig';
                            } elseif ($modus === 'minuten_pro_tag') {
                                $modusLabel = 'Pro Tag';
                            }
                            if (str_starts_with($begr, '[Umbuchung]')) {
                                $modusLabel = 'Sammelumbuchung';
                            }

                            $sumMin = $gesamtMin;
                            if ($sumMin === null && $mpt !== null && $anzahlTage > 0) {
                                $sumMin = $mpt * $anzahlTage;
                            }

                            $sumTxt = $sumMin !== null ? $fmtMin((int)$sumMin) : '---';
                            $proTagTxt = $mpt !== null ? $fmtMin((int)$mpt) : '---';
                        ?>
                        <tr>
                            <td><?php echo (int)$bid; ?></td>
                            <td><?php echo htmlspecialchars($fmtDatum($vonDatum) . ' bis ' . $fmtDatum($bisDatum), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($modusLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo (int)$anzahlTage; ?></td>
                            <td><?php echo htmlspecialchars($sumTxt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($proTagTxt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo $nur ? 'Ja' : 'Nein'; ?></td>
                            <td><?php echo htmlspecialchars($begr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($erst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($vonName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p><small>Hinweis: Arbeitstage = Montag bis Freitag (Feiertage/Betriebsferien werden aktuell nicht automatisch erkannt).</small></p>
        <?php else: ?>
            <p><small>Noch keine Verteilbuchungen vorhanden.</small></p>
        <?php endif; ?>

        <?php if ($stundenkontoDarfVerwalten): ?>
            <h4>Manuelle Korrektur buchen</h4>
            <form method="post" action="?seite=mitarbeiter_admin_speichern">
                <input type="hidden" name="stundenkonto_only" value="1">
                <input type="hidden" name="return_to" value="stundenkonto">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <?php if ($stundenkontoStealthMode): ?>
                    <input type="hidden" name="stundenkonto_stealth" value="1">
                <?php endif; ?>

                <div>
                    <label>
                        Delta (Stunden) - positiv = Gutschrift, negativ = Abzug
                        <input type="number" step="0.25" name="stundenkonto_delta_stunden" required placeholder="z.B. 1.25 oder -0.25">
                    </label>
                </div>

                <div>
                    <label>
                        Wirksam ab
                        <input type="date" name="stundenkonto_wirksam_datum" value="<?php echo htmlspecialchars((new \DateTimeImmutable('today'))->format('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>
                </div>

                <div>
                    <label>
                        Begruendung
                        <input type="text" name="stundenkonto_begruendung" required placeholder="z.B. Korrektur wegen ...">
                    </label>
                </div>

                <p>
                    <button type="submit">Korrektur buchen</button>
                </p>
            </form>

            <h4>Verteilbuchung buchen</h4>
            <form method="post" action="?seite=mitarbeiter_admin_speichern">
                <input type="hidden" name="stundenkonto_batch_only" value="1">
                <input type="hidden" name="return_to" value="stundenkonto">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <?php if ($stundenkontoStealthMode): ?>
                    <input type="hidden" name="stundenkonto_stealth" value="1">
                <?php endif; ?>

                <div>
                    <label>
                        Modus
                        <select name="stundenkonto_batch_modus" required>
                            <option value="gesamt_gleichmaessig">Gesamtstunden gleichmaessig auf Tage verteilen</option>
                            <option value="minuten_pro_tag">Gutschrift/Abzug pro Tag (fix)</option>
                        </select>
                    </label>
                </div>

                <div>
                    <label>
                        Delta (Stunden)
                        <input type="number" step="0.25" name="stundenkonto_batch_delta_stunden" required placeholder="z.B. 20 oder -20 oder 0.25">
                    </label>
                    <small>Bei "pro Tag" ist das Delta je Tag (z.B. 0.25 = 15 Minuten).</small>
                </div>

                <div>
                    <label>
                        Von
                        <input type="date" name="stundenkonto_batch_von_datum" required>
                    </label>
                    <label>
                        Bis
                        <input type="date" name="stundenkonto_batch_bis_datum" required>
                    </label>
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="stundenkonto_batch_nur_arbeitstage" value="1" checked>
                        Nur Arbeitstage (Mo-Fr)
                    </label>
                </div>

                <div>
                    <label>
                        Begruendung
                        <input type="text" name="stundenkonto_batch_begruendung" required placeholder="z.B. Gesetzaenderung / Korrektur ...">
                    </label>
                </div>

                <p>
                    <button type="submit">Verteilbuchung buchen</button>
                </p>
            </form>

            <form method="get" action="">
                <input type="hidden" name="seite" value="mitarbeiter_stundenkonto">
                <input type="hidden" name="mitarbeiter_id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="ansicht" value="sammelumbuchung">
                <input type="hidden" name="umbuchung_monat" value="<?php echo (int)date('n'); ?>">
                <input type="hidden" name="umbuchung_jahr" value="<?php echo (int)date('Y'); ?>">
                <?php if ($stundenkontoStealthMode): ?>
                    <input type="hidden" name="mode" value="stealth">
                <?php endif; ?>
                <p>
                    <button type="submit">Sammelumbuchung auf Zieltag</button>
                </p>
            </form>
        <?php else: ?>
            <p><small>Hinweis: Du hast nicht das Recht <code>STUNDENKONTO_VERWALTEN</code>.</small></p>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
