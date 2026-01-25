<?php
declare(strict_types=1);
/**
 * Template für die Tagesansicht der Zeitbuchungen.
 *
 * Erwartet u. a.:
 * - $datum (string, z. B. '2025-01-01')
 * - $buchungen (array)
 *
 * Optional (Korrektur mit Rechten):
 * - $istAdmin (bool)                // historischer Name: bedeutet hier "darf korrigieren"
 * - $darfAndereMitarbeiter (bool)   // nur bei EDIT_ALL
 * - $mitarbeiterListe (array)
 * - $zielMitarbeiterId (int)
 * - $zielMitarbeiterName (string)
 * - $csrfToken (string)
 * - $flashOk / $flashFehler (string|null)
 * - $editBuchung (array|null)
 */
require __DIR__ . '/../layout/header.php';

$datum     = $datum ?? date('Y-m-d');
$buchungen = $buchungen ?? [];

$istAdmin            = $istAdmin ?? false;
$darfAndereMitarbeiter = $darfAndereMitarbeiter ?? false;
$mitarbeiterListe    = $mitarbeiterListe ?? [];
$zielMitarbeiterId   = isset($zielMitarbeiterId) ? (int)$zielMitarbeiterId : 0;
$zielMitarbeiterName = $zielMitarbeiterName ?? '';
$csrfToken           = $csrfToken ?? '';
$flashOk             = $flashOk ?? null;
$flashFehler         = $flashFehler ?? null;
$editBuchung         = $editBuchung ?? null;

$zeigeMicroBuchungen = !empty($zeigeMicroBuchungen);
$microBuchungenAusgeblendetAnzahl = isset($microBuchungenAusgeblendetAnzahl) ? (int)$microBuchungenAusgeblendetAnzahl : 0;

if (!function_exists('zeit_format_uhrzeit')) {
    /**
     * Formatiert DATETIME-Strings als Uhrzeit (HH:MM:SS).
     */
    function zeit_format_uhrzeit(string $ts): string
    {
        $ts = trim($ts);
        if ($ts === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($ts))->format('H:i:s');
        } catch (Throwable $e) {
            return $ts;
        }
    }
}

$hatRohUndGerundet = false;
foreach ($buchungen as $b) {
    if (is_array($b) && isset($b['zeitstempel_gerundet'])) {
        $hatRohUndGerundet = true;
        break;
    }
}
?>

<section>
    <h2>
        Tagesansicht <?php echo htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        <?php if ($darfAndereMitarbeiter && $zielMitarbeiterName !== ''): ?>
            <small>(<?php echo htmlspecialchars($zielMitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
        <?php endif; ?>
    </h2>

    <?php if ($darfAndereMitarbeiter): ?>
        <form method="get" style="margin: 0 0 12px 0;">
            <input type="hidden" name="seite" value="zeit_heute">
            <?php if ($zeigeMicroBuchungen): ?>
                <input type="hidden" name="show_micro" value="1">
            <?php endif; ?>
            <label>
                Mitarbeiter:
                <select name="mitarbeiter_id">
                    <?php foreach ($mitarbeiterListe as $m): ?>
                        <?php
                            $mid = (int)($m['id'] ?? 0);
                            $vn  = trim((string)($m['vorname'] ?? ''));
                            $nn  = trim((string)($m['nachname'] ?? ''));
                            $an  = trim($vn . ' ' . $nn);
                            if ($an === '') { $an = 'Mitarbeiter #' . $mid; }
                            $aktiv = (int)($m['aktiv'] ?? 1);
                            $label = $an . ($aktiv === 1 ? '' : ' (inaktiv)');
                        ?>
                        <option value="<?php echo $mid; ?>" <?php echo ($mid === $zielMitarbeiterId ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="margin-left: 8px;">
                Datum:
                <input type="date" name="datum" value="<?php echo htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>
            <button type="submit" style="margin-left: 8px;">Anzeigen</button>
        </form>
    <?php elseif ($istAdmin): ?>
        <form method="get" style="margin: 0 0 12px 0;">
            <input type="hidden" name="seite" value="zeit_heute">
            <?php if ($zeigeMicroBuchungen): ?>
                <input type="hidden" name="show_micro" value="1">
            <?php endif; ?>
            <label>
                Datum:
                <input type="date" name="datum" value="<?php echo htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>
            <button type="submit" style="margin-left: 8px;">Anzeigen</button>
        </form>
    <?php endif; ?>

    <?php if (is_string($flashOk) && trim($flashOk) !== ''): ?>
        <p style="padding:8px;border:1px solid #9ad29a;background:#e9f7e9;"><?php echo htmlspecialchars($flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (is_string($flashFehler) && trim($flashFehler) !== ''): ?>
        <p style="padding:8px;border:1px solid #d29a9a;background:#f7e9e9;"><?php echo htmlspecialchars($flashFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($buchungen === []): ?>
        <p>Für dieses Datum sind keine Buchungen vorhanden.</p>
    <?php else: ?>

        <?php
            // Hinweis: Mikro-Buchungen werden standardmäßig ausgeblendet (siehe Controller).
            $baseUrl = '?seite=zeit_heute&datum=' . urlencode($datum);
            if ($zielMitarbeiterId > 0) {
                $baseUrl .= '&mitarbeiter_id=' . urlencode((string)$zielMitarbeiterId);
            }
            $toggleUrl = $zeigeMicroBuchungen ? $baseUrl : ($baseUrl . '&show_micro=1');
        ?>

        <?php if (!$zeigeMicroBuchungen && $microBuchungenAusgeblendetAnzahl > 0): ?>
            <p style="padding:8px;border:1px solid #d0d0d0;background:#f7f7f7;">
                Hinweis: <?php echo (int)$microBuchungenAusgeblendetAnzahl; ?> Mikro-Buchung(en) (≤ 3 Minuten) wurden ausgeblendet.
                <a href="<?php echo htmlspecialchars($toggleUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="margin-left:8px;">Mikro-Buchungen anzeigen</a>
            </p>
        <?php elseif ($zeigeMicroBuchungen): ?>
            <p style="padding:8px;border:1px solid #d0d0d0;background:#f7f7f7;">
                Mikro-Buchungen werden angezeigt.
                <a href="<?php echo htmlspecialchars($toggleUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="margin-left:8px;">Mikro-Buchungen ausblenden</a>
            </p>
        <?php endif; ?>

        <table>
            <thead>
            <tr>
                <?php if ($hatRohUndGerundet): ?>
                    <th>Zeit (gerundet)</th>
                    <th>Zeit (roh)</th>
                <?php else: ?>
                    <th>Zeit</th>
                <?php endif; ?>
                <th>Typ</th>
                <th>Quelle</th>
                <th>Begründung</th>
                <?php if ($istAdmin): ?>
                    <th>Manuell</th>
                    <th>Aktionen</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($buchungen as $b): ?>
                <?php
                    $id = (int)($b['id'] ?? 0);
                    $tsRoh = (string)($b['zeitstempel_roh'] ?? ($b['zeitstempel'] ?? ''));
                    $tsGerundet = (string)($b['zeitstempel_gerundet'] ?? ($b['zeitstempel'] ?? ''));
                    $typ = (string)($b['typ'] ?? '');
                    $quelle = (string)($b['quelle'] ?? '');
                    $kommentar = (string)($b['kommentar'] ?? '');
                    $manuell = (int)($b['manuell_geaendert'] ?? 0);

                    $editUrl = '?seite=zeit_heute&datum=' . urlencode($datum)
                        . '&mitarbeiter_id=' . urlencode((string)$zielMitarbeiterId)
                        . '&edit_id=' . urlencode((string)$id);

                    if ($zeigeMicroBuchungen) {
                        $editUrl .= '&show_micro=1';
                    }
                ?>
                <tr>
                    <?php if ($hatRohUndGerundet): ?>
                        <td><?php echo htmlspecialchars(zeit_format_uhrzeit($tsGerundet), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(zeit_format_uhrzeit($tsRoh), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <?php else: ?>
                        <td><?php echo htmlspecialchars(zeit_format_uhrzeit((string)($b['zeitstempel'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($quelle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($kommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>

                    <?php if ($istAdmin): ?>
                        <td><?php echo ($manuell === 1 ? 'ja' : 'nein'); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Bearbeiten</a>
                            &nbsp;|
                            <form method="post" style="display:inline;" onsubmit="return confirm('Buchung wirklich löschen?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <input type="hidden" name="aktion" value="delete">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <?php if ($zeigeMicroBuchungen): ?>
                                    <input type="hidden" name="show_micro" value="1">
                                <?php endif; ?>
                                <input type="text" name="begruendung" maxlength="255" required placeholder="Begründung" style="width: 180px;">
                                <button type="submit">Löschen</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

<?php if ($istAdmin && is_array($editBuchung)):
    $editId = (int)($editBuchung['id'] ?? 0);
    $editTyp = (string)($editBuchung['typ'] ?? 'kommen');
    $editZeit = zeit_format_uhrzeit((string)($editBuchung['zeitstempel'] ?? ''));
    $editKommentar = (string)($editBuchung['kommentar'] ?? '');
    // HTML time input erwartet i.d.R. HH:MM (Sekunden optional je nach Browser)
    $editZeitInput = substr($editZeit, 0, 5);
?>
    <div id="buchung-bearbeiten" style="border:1px solid #b00020; border-radius: 8px; padding: 10px 12px; background:#fff5f5; margin: 12px 0;">
        <h3 style="margin: 0 0 8px 0; color:#b00020;">Buchung bearbeiten (ID <?php echo $editId; ?>)</h3>
        <form method="post" style="margin: 0;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="aktion" value="update">
            <input type="hidden" name="id" value="<?php echo $editId; ?>">

            <label>
                Typ:
                <select name="typ">
                    <option value="kommen" <?php echo ($editTyp === 'kommen' ? 'selected' : ''); ?>>kommen</option>
                    <option value="gehen" <?php echo ($editTyp === 'gehen' ? 'selected' : ''); ?>>gehen</option>
                </select>
            </label>

            <label style="margin-left: 8px;">
                Zeit:
                <input type="time" name="zeit" step="1" value="<?php echo htmlspecialchars($editZeitInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </label>

            <label style="margin-left: 8px;">
                Begründung (Pflicht):
                <input type="text" name="begruendung" maxlength="255" required style="width: 320px;" value="<?php echo htmlspecialchars($editKommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <button type="submit" style="margin-left: 8px;">Speichern</button>
            <a href="?seite=zeit_heute&amp;datum=<?php echo urlencode($datum); ?>&amp;mitarbeiter_id=<?php echo urlencode((string)$zielMitarbeiterId); ?><?php echo $zeigeMicroBuchungen ? '&amp;show_micro=1' : ''; ?>" style="margin-left:8px;">Abbrechen</a>
        </form>
    </div>
<?php endif; ?>


    <?php endif; ?>

    <?php if ($istAdmin): ?>
        <hr>

        <h3>Abwesenheiten / Tagesfelder</h3>

        <?php
            $tageswerte = $tageswerte ?? null;
            $planKurzarbeitStunden = $planKurzarbeitStunden ?? null;

            $kurzarbeitAktiv = false;
            $kurzarbeitStunden = '';
            if (is_array($tageswerte)) {
                $kenn = (int)($tageswerte['kennzeichen_kurzarbeit'] ?? 0);
                $std  = (float)str_replace(',', '.', (string)($tageswerte['kurzarbeit_stunden'] ?? '0'));
                if ($kenn === 1 || $std > 0.0) {
                    $kurzarbeitAktiv = true;
                    $kurzarbeitStunden = number_format($std, 2, '.', '');
                }
            }

            // Pause-Override (pro Tag) – aktiv/inaktiv über Checkbox.
            // Aktiv-Status und Begründung kommen aus dem Controller (Audit-Log), damit 0,00 als Override möglich ist.
            $pauseOverrideAktiv = isset($pauseOverrideAktiv) ? (bool)$pauseOverrideAktiv : false;
            $pauseOverrideStunden = isset($pauseOverrideStunden) ? (string)$pauseOverrideStunden : '';
            $pauseOverrideBegruendung = isset($pauseOverrideBegruendung) ? (string)$pauseOverrideBegruendung : '';

            // Pflicht-Begründungen (aus Audit-Log) für Tagesfelder.
            $kurzarbeitOverrideBegruendung = isset($kurzarbeitOverrideBegruendung) ? (string)$kurzarbeitOverrideBegruendung : '';
            $krankOverrideBegruendung = isset($krankOverrideBegruendung) ? (string)$krankOverrideBegruendung : '';
            $sonstigesOverrideBegruendung = isset($sonstigesOverrideBegruendung) ? (string)$sonstigesOverrideBegruendung : '';

            // Auto-Pause (Anzeigehilfe), falls vom Controller geliefert.
            $pauseAutoStunden = isset($pauseAutoStunden) ? (string)$pauseAutoStunden : '';
            $pauseAutoEntscheidungNoetig = isset($pauseAutoEntscheidungNoetig) ? (bool)$pauseAutoEntscheidungNoetig : false;
            $pauseAutoVorschlagMin = isset($pauseAutoVorschlagMin) ? (int)$pauseAutoVorschlagMin : 0;
        ?>

        <h4>Pause (Override)</h4>

        <?php if ($pauseOverrideAktiv): ?>
            <div style="border: 2px solid #b00020; border-radius: 10px; padding: 0.6rem 0.8rem; background: #fff5f5; margin: 8px 0 10px 0;">
                <strong style="color:#b00020;">Pause Override aktiv:</strong>
                <span><?php echo htmlspecialchars(str_replace('.', ',', (string)$pauseOverrideStunden), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Stunden</span>
                <span style="color:#666; font-size: 0.95rem;">(überschreibt automatische Pausenberechnung)</span>
                <?php if (trim((string)$pauseOverrideBegruendung) !== ''): ?>
                    <span style="margin-left: 10px; color:#666;">Begründung: <em><?php echo htmlspecialchars($pauseOverrideBegruendung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em></span>
                <?php endif; ?>
                <a href="?seite=dashboard" style="margin-left: 10px;">Zurück zum Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (!$pauseOverrideAktiv && $pauseAutoStunden !== ''): ?>
            <div style="margin: 0 0 10px 0; color:#666;">
                Automatische Pause nach Regeln: <strong><?php echo htmlspecialchars(str_replace('.', ',', (string)$pauseAutoStunden), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Stunden</strong>
                <?php if ($pauseAutoEntscheidungNoetig && $pauseAutoVorschlagMin > 0): ?>
                    <span style="margin-left: 8px;">(Pausenentscheidung nötig; Auto-Vorschlag: <?php echo htmlspecialchars(str_replace('.', ',', number_format($pauseAutoVorschlagMin / 60.0, 2, '.', '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Stunden)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" style="margin: 8px 0 18px 0;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="aktion" value="set_pause_override">

            <label style="margin-right: 10px;">
                <input type="checkbox" id="pause_override_aktiv" name="pause_override_aktiv" value="1" <?php echo $pauseOverrideAktiv ? 'checked' : ''; ?>>
                Pause Override
            </label>

            <label>
                Pause in Stunden:
                <input type="number" id="pause_stunden" name="pause_stunden" step="0.25" min="0" max="24" style="width: 90px;" value="<?php echo htmlspecialchars($pauseOverrideStunden, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <div style="margin-top: 8px;">
                <label>
                    Begründung (Pflicht):
                    <input type="text" id="pause_begruendung" name="begruendung" maxlength="255" required style="width: 360px;" value="<?php echo htmlspecialchars($pauseOverrideBegruendung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
                <button type="submit" style="margin-left: 8px;">Speichern</button>
            </div>

            <p style="margin: 8px 0 0 0; color: #666;">
                Hinweis: Haken aus = Override löschen (normale Pausenregeln aktiv). Beispiel: <code>0,00</code> = keine Pause; <code>0,25</code> = 15 Minuten.
            </p>
        </form>

        <script>
            (function () {
                var cb = document.getElementById('pause_override_aktiv');
                var inp = document.getElementById('pause_stunden');
                var begr = document.getElementById('pause_begruendung');
                if (!cb || !inp) return;

                function sync() {
                    var on = !!cb.checked;
                    inp.disabled = !on;
                    if (begr) {
                        begr.disabled = !on;
                        begr.required = on;
                    }
                }

                cb.addEventListener('change', sync);
                sync();
            })();
        </script>

        <form method="post" style="margin: 8px 0 18px 0;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="aktion" value="set_kurzarbeit">

            <label style="margin-right: 10px;">
                <input type="checkbox" name="kurzarbeit_aktiv" value="1" <?php echo $kurzarbeitAktiv ? 'checked' : ''; ?>>
                Kurzarbeit
            </label>

            <label>
                Stunden:
                <input type="number" name="kurzarbeit_stunden" step="0.25" min="0" style="width: 90px;" value="<?php echo htmlspecialchars($kurzarbeitStunden, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <?php if ($planKurzarbeitStunden !== null): ?>
                <span style="margin-left: 10px; color: #666;">
                    (Plan: <?php echo htmlspecialchars(number_format((float)$planKurzarbeitStunden, 2, ',', '.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h)
                </span>
            <?php endif; ?>

            <div style="margin-top: 8px;">
                <label>
                    Begründung (Pflicht):
                    <input type="text" name="begruendung" maxlength="255" required style="width: 360px;" value="<?php echo htmlspecialchars($kurzarbeitOverrideBegruendung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
                <button type="submit" style="margin-left: 8px;">Speichern</button>
            </div>

            <p style="margin: 8px 0 0 0; color: #666;">
                Hinweis: Tages-Override überschreibt den Kurzarbeit-Plan (für Report/PDF). Deaktivieren = Override entfernen, Plan greift wieder.
            </p>
        </form>


        <?php
            // Krank-Override (LFZ/KK) – Tageswerte
            $krankTyp = '';
            $krankStunden = '';
            if (is_array($tageswerte)) {
                $kennL = (int)($tageswerte['kennzeichen_krank_lfz'] ?? 0);
                $kennK = (int)($tageswerte['kennzeichen_krank_kk'] ?? 0);

                $stdL = (float)str_replace(',', '.', (string)($tageswerte['krank_lfz_stunden'] ?? '0'));
                $stdK = (float)str_replace(',', '.', (string)($tageswerte['krank_kk_stunden'] ?? '0'));

                if ($kennL === 1 || $stdL > 0.0) {
                    $krankTyp = 'lfz';
                    $krankStunden = number_format(max(0.0, $stdL), 2, '.', '');
                } elseif ($kennK === 1 || $stdK > 0.0) {
                    $krankTyp = 'kk';
                    $krankStunden = number_format(max(0.0, $stdK), 2, '.', '');
                }
            }

            // Sonstiges – konfigurierbare Gründe (T-075)
            $sonstigesGruende = $sonstigesGruende ?? [];
            $sonstigesGrundId = 0;
            $sonstigesStunden = '';
            $sonstigesKommentarText = '';

            if (is_array($tageswerte)) {
                $kennS = (int)($tageswerte['kennzeichen_sonstiges'] ?? 0);
                $stdS  = (float)str_replace(',', '.', (string)($tageswerte['sonstige_stunden'] ?? '0'));
                if ($kennS === 1 || $stdS > 0.0) {
                    $sonstigesStunden = number_format(max(0.0, $stdS), 2, '.', '');

                    $kom = trim((string)($tageswerte['kommentar'] ?? ''));
                    $code = $kom;
                    $rest = '';
                    $pos = strpos($kom, ':');
                    if ($pos !== false) {
                        $code = trim(substr($kom, 0, $pos));
                        $rest = trim(substr($kom, $pos + 1));
                    }
                    $sonstigesKommentarText = $rest;

                    if ($code !== '' && is_array($sonstigesGruende)) {
                        foreach ($sonstigesGruende as $g) {
                            $gid = (int)($g['id'] ?? 0);
                            $gcode = trim((string)($g['code'] ?? ''));
                            if ($gid > 0 && $gcode !== '' && strcasecmp($gcode, $code) === 0) {
                                $sonstigesGrundId = $gid;
                                break;
                            }
                        }
                    }
                }
            }
        ?>

        <h4>Krank (LFZ / KK)</h4>

        <form method="post" style="margin-bottom: 16px;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="aktion" value="set_krank">

            <label style="margin-right: 10px;">
                <input type="radio" name="krank_typ" value="" <?php echo $krankTyp === '' ? 'checked' : ''; ?>>
                Kein Krank-Override
            </label>

            <label style="margin-right: 10px;">
                <input type="radio" name="krank_typ" value="lfz" <?php echo $krankTyp === 'lfz' ? 'checked' : ''; ?>>
                Krank LFZ
            </label>

            <label style="margin-right: 10px;">
                <input type="radio" name="krank_typ" value="kk" <?php echo $krankTyp === 'kk' ? 'checked' : ''; ?>>
                Krank KK
            </label>

            <label>
                Stunden:
                <input type="number" name="krank_stunden" step="0.25" min="0" max="24" style="width: 90px;" value="<?php echo htmlspecialchars($krankStunden, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>

            <div style="margin-top: 8px;">
                <label>
                    Begründung (Pflicht):
                    <input type="text" name="begruendung" maxlength="255" required style="width: 360px;" value="<?php echo htmlspecialchars($krankOverrideBegruendung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
                <button type="submit" style="margin-left: 8px;">Speichern</button>
            </div>

            <p style="margin: 8px 0 0 0; color: #666;">
                Hinweis: Tages-Override hat Vorrang vor der Zeitraum-Ableitung (Report/PDF). Deaktivieren = Override entfernen, Zeitraum greift wieder.
            </p>
        </form>

        <h4>Sonstiges</h4>

        <?php if (!is_array($sonstigesGruende) || $sonstigesGruende === []): ?>
            <p style="margin: 0 0 16px 0; color: #666;">
                Keine aktiven Sonstiges-Gründe konfiguriert. (Konfiguration → Sonstiges-Gründe)
            </p>
        <?php else: ?>
            <form method="post" style="margin-bottom: 16px;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="aktion" value="set_sonstiges">

                <label>
                    Grund:
                    <select name="sonstiges_grund_id">
                        <option value="0" <?php echo ($sonstigesGrundId === 0 ? 'selected' : ''); ?>>Kein Sonstiges</option>
                        <?php foreach ($sonstigesGruende as $g): ?>
                            <?php
                                $gid = (int)($g['id'] ?? 0);
                                $gcode = trim((string)($g['code'] ?? ''));
                                $gtitel = trim((string)($g['titel'] ?? ''));
                                $gdef = (float)str_replace(',', '.', (string)($g['default_stunden'] ?? '0'));
                                $gpflicht = ((int)($g['begruendung_pflicht'] ?? 0) === 1);
                                $label = $gcode;
                                if ($gtitel !== '') {
                                    $label .= ' – ' . $gtitel;
                                }
                                if ($gdef > 0.0) {
                                    $label .= ' (' . number_format($gdef, 2, ',', '.') . ' h)';
                                }
                                if ($gpflicht) {
                                    $label .= ' *';
                                }
                            ?>
                            <option value="<?php echo $gid; ?>" <?php echo ($gid === $sonstigesGrundId ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label style="margin-left: 10px;">
                    Stunden:
                    <input type="number" name="sonstiges_stunden" step="0.25" min="0" max="24" style="width: 90px;" value="<?php echo htmlspecialchars($sonstigesStunden, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>

                <label style="margin-left: 10px;">
                    Kommentar/Notiz:
                    <input type="text" name="sonstiges_kommentar" maxlength="255" style="width: 320px;" value="<?php echo htmlspecialchars($sonstigesKommentarText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>

                <div style="margin-top: 8px;">
                    <label>
                        Begründung (Pflicht):
                        <input type="text" name="begruendung" maxlength="255" required style="width: 360px;" value="<?php echo htmlspecialchars($sonstigesOverrideBegruendung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>
                    <button type="submit" style="margin-left: 8px;">Speichern</button>
                </div>

                <p style="margin: 8px 0 0 0; color: #666;">
                    Hinweis: Wenn keine Stunden angegeben sind, werden die Default-Stunden des Grundes übernommen. Gründe mit <strong>*</strong> verlangen zusätzlich eine Notiz.
                </p>
            </form>
        <?php endif; ?>

        <h3>Neue Buchung hinzufügen</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="aktion" value="add">

            <label>
                Typ:
                <select name="typ">
                    <option value="kommen">kommen</option>
                    <option value="gehen">gehen</option>
                </select>
            </label>

            <label style="margin-left: 8px;">
                Zeit:
                <input type="time" name="zeit" step="1" required>
            </label>

            <label style="margin-left: 8px;">
                Begründung (Pflicht):
                <input type="text" name="begruendung" maxlength="255" required style="width: 320px;">
            </label>

            <button type="submit" style="margin-left: 8px;">Hinzufügen</button>
        </form>

        <p style="margin-top:10px;color:#666;">
            Hinweis: Änderungen an Zeitbuchungen werden als <strong>manuell geändert</strong> markiert.
            Zusätzlich wird jede Änderung mit Begründung im <code>system_log</code> auditiert.
            Wenn für diesen Tag bereits Tageswerte gespeichert sind, werden diese als „Rohdaten geändert“ markiert.
            Die Begründung wird zusätzlich als Kommentar in der Zeitbuchung gespeichert und oben in der Tabelle angezeigt.
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
