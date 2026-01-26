<?php
declare(strict_types=1);
/**
 * Template: Monatsübersicht für einen Mitarbeiter.
 *
 * Erwartet u. a.:
 * - $jahr (int)
 * - $monat (int)
 * - $monatswerte (array|null)
 * - $tageswerte (array<int,array<string,mixed>>)
 */
require __DIR__ . '/../layout/header.php';

$jahr        = $jahr ?? (int)date('Y');
$monat       = $monat ?? (int)date('n');
$monatswerte = $monatswerte ?? null;
$tageswerte  = $tageswerte ?? [];

$mitarbeiterId = isset($mitarbeiterId) ? (int)$mitarbeiterId : 0;
$hatReportMonatViewAll = !empty($hatReportMonatViewAll);
$hatReportMonatExportAll = !empty($hatReportMonatExportAll);
$mitarbeiterListe = $mitarbeiterListe ?? [];

$darfZeitBearbeiten = !empty($darfZeitBearbeiten);
$showMicro = !empty($showMicro);

// ------------------------------------------------------------
// Stundenkonto (Gut/Minus) - Saldo bis Ende Vormonat
// (exklusiv der Stunden des hier angezeigten Monats)
// ------------------------------------------------------------
$stundenkontoSaldoMinuten = null;
$stundenkontoSaldoLabel = "";
$stundenkontoSaldoText = "";

if (class_exists("StundenkontoService") && $mitarbeiterId > 0) {
    try {
        $sk = StundenkontoService::getInstanz();
        $stundenkontoSaldoMinuten = $sk->holeSaldoMinutenBisVormonat($mitarbeiterId, (int)$jahr, (int)$monat);

        $stundenkontoSaldoLabel = ($stundenkontoSaldoMinuten < 0)
            ? "Minusstunden (Stand bis Vormonat)"
            : "Gutstunden (Stand bis Vormonat)";

        $stundenkontoSaldoText = $sk->formatMinutenAlsStundenString((int)$stundenkontoSaldoMinuten, true) . " h";
    } catch (\Throwable $e) {
        $stundenkontoSaldoMinuten = null;
        $stundenkontoSaldoLabel = "";
        $stundenkontoSaldoText = "";
    }
}



// ------------------------------------------------------------
// Urlaubstage (abzgl. geplante Betriebsferien) - Jahres-Resturlaub
// Quelle: UrlaubService->berechneUrlaubssaldoFuerJahr(...)[verbleibend].
// Dieser Wert ist bereits inkl. Betriebsferien (Zwangsurlaub) berechnet.
// BF (Rest Jahr) bleibt als separate Info (nach Monatsende bis 31.12.).
// ------------------------------------------------------------
$urlaubAbzglBfText = '';
$bfRestArbeitstageText = '';

if ($monatswerte !== null && $mitarbeiterId > 0 && class_exists('UrlaubService')) {
    try {
        $urlaubVerbleibendTage = 0.0;

        $us = UrlaubService::getInstanz();
        $saldo = $us->berechneUrlaubssaldoFuerJahr((int)$mitarbeiterId, (int)$jahr);
        if (is_array($saldo) && isset($saldo['verbleibend'])) {
            $uv = trim((string)$saldo['verbleibend']);
            $uv = str_replace(',', '.', $uv);
            $urlaubVerbleibendTage = is_numeric($uv) ? (float)$uv : 0.0;
        }

        $tz = new \DateTimeZone('Europe/Berlin');
        $bisMonat = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int)$jahr, (int)$monat), $tz))
            ->modify('last day of this month');

        $startRest = $bisMonat->modify('+1 day');
        $endeRest  = new \DateTimeImmutable(sprintf('%04d-12-31', (int)$jahr), $tz);

        $bfRestArbeitstage = 0;
        if ($startRest <= $endeRest) {
            $bfRestArbeitstage = $us->zaehleBetriebsferienArbeitstageFuerMitarbeiter(
                (int)$mitarbeiterId,
                $startRest->format('Y-m-d'),
                $endeRest->format('Y-m-d')
            );
        }

        // Hinweis:
        // Negative Urlaubstage sind erlaubt (z. B. wenn mehr Urlaub genommen wurde als verfügbar).
        // Der automatische Übertrag ins Folgejahr wird weiterhin defensiv gehandhabt (nur positive
        // Resttage werden übertragen).

        $urlaubAbzglBfText = sprintf('%.2f', (float)$urlaubVerbleibendTage);
        $bfRestArbeitstageText = (string)(int)$bfRestArbeitstage;
    } catch (\Throwable $e) {
        $urlaubAbzglBfText = '';
        $bfRestArbeitstageText = '';
    }
}
// Heuristik: Unvollstaendige Kommen/Gehen-Stempel werden erst nach Tagesende markiert.
// Daher ignorieren wir "heute" und zukuenftige Tage in der Monatsuebersicht (Europe/Berlin).
$heuteIso = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');

// ------------------------------------------------------------
// T-069 (Teil 2a): Unstimmigkeiten in Kommen/Gehen sichtbar machen.
// - Ziel: In der Monatsuebersicht soll sofort erkennbar sein, wenn z. B.
//   nur "Kommen" ohne "Gehen" existiert.
// ------------------------------------------------------------
// (Wir markieren betroffene Tage/Zellen rein visuell; keine Datenmutation.)


// Anzeigename für den ausgewählten Mitarbeiter (nur, wenn wir eine Liste haben).
$mitarbeiterAnzeigeName = '';
if ($hatReportMonatViewAll && is_array($mitarbeiterListe) && $mitarbeiterId > 0) {
    foreach ($mitarbeiterListe as $m) {
        $mid = (int)($m['id'] ?? 0);
        if ($mid === $mitarbeiterId) {
            $vn = trim((string)($m['vorname'] ?? ''));
            $nn = trim((string)($m['nachname'] ?? ''));
            $mitarbeiterAnzeigeName = trim($nn . ', ' . $vn);
            if ($mitarbeiterAnzeigeName === ',') {
                $mitarbeiterAnzeigeName = '';
            }
            break;
        }
    }
}
?>


<?php
if (!function_exists('report_format_datum')) {
    /**
     * Formatiert ein Datum (YYYY-MM-DD) nach deutschem Format (DD.MM.YYYY).
     */
    function report_format_datum(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        if ($dt === false) {
            return $ymd;
        }

        return $dt->format('d.m.Y');
    }
}

if (!function_exists('report_normalize_datum_iso')) {
    /**
     * Normalisiert ein Datum nach ISO (YYYY-MM-DD).
     *
     * Hintergrund: Manche Datenpfade liefern Datum bereits als "DD.MM.YYYY".
     * Die FEHLT/⚠-Logik muss aber mit ISO vergleichen, sonst wird "heute" falsch
     * als vergangener Tag gewertet.
     */
    function report_normalize_datum_iso(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Bereits ISO oder Timestamp-ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        // Deutsches Datumsformat
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value) === 1) {
            $dt = \DateTime::createFromFormat('d.m.Y', $value, new \DateTimeZone('Europe/Berlin'));
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        // Letzter Fallback: strtotime
        $ts = strtotime($value);
        if ($ts !== false) {
            return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d');
        }

        return '';
    }
}

if (!function_exists('report_format_uhrzeit')) {
    /**
     * Formatiert einen DATETIME-String zur Anzeige als Uhrzeit (HH:MM).
     */
    function report_format_uhrzeit(?string $datetime): string
    {
        if ($datetime === null) {
            return '';
        }

        $s = trim($datetime);
        if ($s === '') {
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($s);
            return $dt->format('H:i');
        } catch (\Throwable $e) {
            // Falls Parsing fehlschlägt, zeigen wir den Rohstring.
            return $s;
        }
    }
}

if (!function_exists('report_parse_dt_berlin')) {
    /**
     * Parst einen DATETIME-String robust in Europe/Berlin.
     *
     * Hinweis: Wir brauchen DateTime-Objekte fuer die Nachtschicht-Heuristik
     * (Kommen gestern, Gehen heute).
     */
    function report_parse_dt_berlin(?string $datetime): ?\DateTimeImmutable
    {
        if ($datetime === null) {
            return null;
        }

        $s = trim((string)$datetime);
        if ($s === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($s, new \DateTimeZone('Europe/Berlin'));
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('report_dt_is_close')) {
    /**
     * Vergleicht zwei DateTimes mit Toleranz (Sekunden).
     */
    function report_dt_is_close(?\DateTimeImmutable $a, ?\DateTimeImmutable $b, int $toleranceSeconds = 60): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        return abs($a->getTimestamp() - $b->getTimestamp()) <= $toleranceSeconds;
    }
}



if (!function_exists('report_is_micro_block')) {
    /**
     * Ermittelt, ob ein Arbeitsblock eine Mikro-Buchung ist
     * (Kommen/Gehen innerhalb weniger Sekunden/Minuten).
     *
     * Regeln:
     * 1) Wenn ein korrigiertes Paar existiert und nach Rundung/Regel zu einer
     *    Null-/Negativdauer fuehrt (gehen_korr <= kommen_korr), gilt der Block
     *    als „Rundung->0“ und wird wie Mikro behandelt (standardmaessig ausblenden).
     * 2) Sonst Mikro-Erkennung am Roh-Paar (kommen_roh/gehen_roh), damit Rundung
     *    Mikro-Buchungen nicht aufblaeht.
     * 3) Fallback: Main-Paar (korr bevorzugt, sonst roh).
     */
    function report_is_micro_block($block, int $maxSeconds): bool
    {
        if (!is_array($block)) {
            return false;
        }

        $kRaw = isset($block['kommen_roh']) ? trim((string)$block['kommen_roh']) : '';
        $gRaw = isset($block['gehen_roh']) ? trim((string)$block['gehen_roh']) : '';
        $kK   = isset($block['kommen_korr']) ? trim((string)$block['kommen_korr']) : '';
        $gK   = isset($block['gehen_korr']) ? trim((string)$block['gehen_korr']) : '';

        // Sonderfall: Rundungsregeln koennen kurze Bloecke „umdrehen“ (z. B. 11:01–11:05 -> 11:15–11:00).
        // Diese Bloecke ergeben effektiv 0 Arbeitszeit und sollen standardmaessig nicht angezeigt werden.
        if ($kK !== '' && $gK !== '' && substr($kK, 0, 10) !== '0000-00-00' && substr($gK, 0, 10) !== '0000-00-00') {
            $kDt = report_parse_dt_berlin($kK);
            $gDt = report_parse_dt_berlin($gK);
            if ($kDt !== null && $gDt !== null && $gDt->getTimestamp() <= $kDt->getTimestamp()) {
                return true;
            }
        }

        if ($maxSeconds <= 0) {
            return false;
        }

        // 1) Roh-Paar vorhanden -> Roh-Diff pruefen (Rundung darf Mikro nicht aufblasen)
        if ($kRaw !== '' && $gRaw !== '' && substr($kRaw, 0, 10) !== '0000-00-00' && substr($gRaw, 0, 10) !== '0000-00-00') {
            $kDt = report_parse_dt_berlin($kRaw);
            $gDt = report_parse_dt_berlin($gRaw);
            if ($kDt !== null && $gDt !== null) {
                $diff = abs($gDt->getTimestamp() - $kDt->getTimestamp());
                if ($diff <= $maxSeconds) {
                    return true;
                }
            }
        }

        // 2) Fallback: Main-Paar (korr bevorzugt, sonst roh)
        $kMain = ($kK !== '' && substr($kK, 0, 10) !== '0000-00-00') ? $kK : $kRaw;
        $gMain = ($gK !== '' && substr($gK, 0, 10) !== '0000-00-00') ? $gK : $gRaw;

        if ($kMain === '' || $gMain === '') {
            return false;
        }

        $kDt = report_parse_dt_berlin($kMain);
        $gDt = report_parse_dt_berlin($gMain);
        if ($kDt === null || $gDt === null) {
            return false;
        }

        $diff = abs($gDt->getTimestamp() - $kDt->getTimestamp());
        return ($diff <= $maxSeconds);
    }
}






if (!function_exists('report_calc_block_ist_dez2')) {
    /**
     * Berechnet die Block-IST-Dauer als Dezimalstunden ("%.2f").
     *
     * Wichtig: Diese Anzeige ist nur ein UI-Helper.
     * Die Monats-/Tagessummen bleiben weiterhin aus dem Service (tageswerte).
     *
     * Sonderfall: Wenn korrigierte Zeiten eine Null-/Negativdauer ergeben
     * (gehen_korr <= kommen_korr), wird "0.00" angezeigt.
     */
    function report_calc_block_ist_dez2($block): string
    {
        if (!is_array($block)) {
            return '';
        }

        $kRaw = isset($block['kommen_roh']) ? trim((string)$block['kommen_roh']) : '';
        $gRaw = isset($block['gehen_roh']) ? trim((string)$block['gehen_roh']) : '';
        $kK   = isset($block['kommen_korr']) ? trim((string)$block['kommen_korr']) : '';
        $gK   = isset($block['gehen_korr']) ? trim((string)$block['gehen_korr']) : '';

        // Rundung->0 (korr Paar dreht/egalisiert)
        if ($kK !== '' && $gK !== '' && substr($kK, 0, 10) !== '0000-00-00' && substr($gK, 0, 10) !== '0000-00-00') {
            $kDt = report_parse_dt_berlin($kK);
            $gDt = report_parse_dt_berlin($gK);
            if ($kDt !== null && $gDt !== null && $gDt->getTimestamp() <= $kDt->getTimestamp()) {
                return '0.00';
            }
        }

        $start = '';
        $ende  = '';

        $kCorrValid = ($kK !== '' && substr($kK, 0, 10) !== '0000-00-00');
        $gCorrValid = ($gK !== '' && substr($gK, 0, 10) !== '0000-00-00');
        $kRawValid = ($kRaw !== '' && substr($kRaw, 0, 10) !== '0000-00-00');
        $gRawValid = ($gRaw !== '' && substr($gRaw, 0, 10) !== '0000-00-00');

        // 1) Korrigiertes Paar bevorzugen (soll zur Anzeige passen).
        if ($kCorrValid && $gCorrValid) {
            $start = $kK;
            $ende  = $gK;
        } elseif ($kRawValid && $gRawValid) {
            // 2) Roh-Paar, wenn keine vollstaendige Korrektur vorliegt.
            $start = $kRaw;
            $ende  = $gRaw;
        } else {
            // 3) Fallback: Main-Paar (korr bevorzugt, sonst roh).
            $kMain = $kCorrValid ? $kK : ($kRawValid ? $kRaw : '');
            $gMain = $gCorrValid ? $gK : ($gRawValid ? $gRaw : '');

            if ($kMain !== '' && $gMain !== '') {
                $start = $kMain;
                $ende  = $gMain;
            }
        }

        if ($start === '' || $ende === '') {
            return '';
        }

        $kDt = report_parse_dt_berlin($start);
        $gDt = report_parse_dt_berlin($ende);
        if ($kDt === null || $gDt === null) {
            return '';
        }

        $diff = $gDt->getTimestamp() - $kDt->getTimestamp();
        if ($diff <= 0) {
            return '0.00';
        }

        $hours = $diff / 3600.0;
        return sprintf('%.2f', $hours);
    }
}



if (!function_exists('report_calc_block_seconds')) {
    /**
     * Berechnet die Block-Dauer in Sekunden.
     *
     * Sonderfall: Wenn korrigierte Zeiten eine Null-/Negativdauer ergeben
     * (gehen_korr <= kommen_korr), wird 0 geliefert.
     */
    function report_calc_block_seconds($block): int
    {
        if (!is_array($block)) {
            return 0;
        }

        $kRaw = isset($block['kommen_roh']) ? trim((string)$block['kommen_roh']) : '';
        $gRaw = isset($block['gehen_roh']) ? trim((string)$block['gehen_roh']) : '';
        $kK   = isset($block['kommen_korr']) ? trim((string)$block['kommen_korr']) : '';
        $gK   = isset($block['gehen_korr']) ? trim((string)$block['gehen_korr']) : '';

        // Rundung->0
        if ($kK !== '' && $gK !== '' && substr($kK, 0, 10) !== '0000-00-00' && substr($gK, 0, 10) !== '0000-00-00') {
            $kDt = report_parse_dt_berlin($kK);
            $gDt = report_parse_dt_berlin($gK);
            if ($kDt !== null && $gDt !== null && $gDt->getTimestamp() <= $kDt->getTimestamp()) {
                return 0;
            }
        }

        $start = '';
        $ende  = '';

        $kCorrValid = ($kK !== '' && substr($kK, 0, 10) !== '0000-00-00');
        $gCorrValid = ($gK !== '' && substr($gK, 0, 10) !== '0000-00-00');
        $kRawValid = ($kRaw !== '' && substr($kRaw, 0, 10) !== '0000-00-00');
        $gRawValid = ($gRaw !== '' && substr($gRaw, 0, 10) !== '0000-00-00');

        // 1) Korrigiertes Paar bevorzugen (soll zur Anzeige passen).
        if ($kCorrValid && $gCorrValid) {
            $start = $kK;
            $ende  = $gK;
        } elseif ($kRawValid && $gRawValid) {
            // 2) Roh-Paar, wenn keine vollstaendige Korrektur vorliegt.
            $start = $kRaw;
            $ende  = $gRaw;
        } else {
            // 3) Fallback: Main-Paar (korr bevorzugt, sonst roh).
            $kMain = $kCorrValid ? $kK : ($kRawValid ? $kRaw : '');
            $gMain = $gCorrValid ? $gK : ($gRawValid ? $gRaw : '');

            if ($kMain !== '' && $gMain !== '') {
                $start = $kMain;
                $ende  = $gMain;
            }
        }

        if ($start == '' || $ende === '') {
            return 0;
        }

        $kDt = report_parse_dt_berlin($start);
        $gDt = report_parse_dt_berlin($ende);
        if ($kDt === null || $gDt === null) {
            return 0;
        }

        $diff = $gDt->getTimestamp() - $kDt->getTimestamp();
        return ($diff > 0) ? (int)$diff : 0;
    }
}




if (!function_exists('report_collapse_blocks')) {
    /**
     * Kollabiert mehrere Arbeitsbloecke zu einem Tagesblock (erster Start + letzter Endzeit).
     * Start/Ende werden anhand der Main-Zeiten bestimmt (korr bevorzugt, sonst roh).
     *
     * Rueckgabe ist ein einzelner Block im gleichen Format wie 'arbeitsbloecke'.
     */
    function report_collapse_blocks(array $bloecke): array
    {
        $startBlk = null;
        $startTs = null;
        $endBlk = null;
        $endTs = null;

        foreach ($bloecke as $blk) {
            if (!is_array($blk)) {
                continue;
            }

            $kMain = '';
            if (isset($blk['kommen_korr']) && trim((string)$blk['kommen_korr']) !== '') {
                $kMain = trim((string)$blk['kommen_korr']);
            } else {
                $kMain = trim((string)($blk['kommen_roh'] ?? ''));
            }

            $gMain = '';
            if (isset($blk['gehen_korr']) && trim((string)$blk['gehen_korr']) !== '') {
                $gMain = trim((string)$blk['gehen_korr']);
            } else {
                $gMain = trim((string)($blk['gehen_roh'] ?? ''));
            }

            if ($kMain !== '') {
                $kDt = report_parse_dt_berlin($kMain);
                if ($kDt !== null) {
                    $ts = $kDt->getTimestamp();
                    if ($startTs === null || $ts < $startTs) {
                        $startTs = $ts;
                        $startBlk = $blk;
                    }
                }
            }

            if ($gMain !== '') {
                $gDt = report_parse_dt_berlin($gMain);
                if ($gDt !== null) {
                    $ts = $gDt->getTimestamp();
                    if ($endTs === null || $ts > $endTs) {
                        $endTs = $ts;
                        $endBlk = $blk;
                    }
                }
            }
        }

        return [
            'kommen_roh'  => is_array($startBlk) ? ($startBlk['kommen_roh'] ?? null) : null,
            'gehen_roh'   => is_array($endBlk) ? ($endBlk['gehen_roh'] ?? null) : null,
            'kommen_korr' => is_array($startBlk) ? ($startBlk['kommen_korr'] ?? null) : null,
            'gehen_korr'  => is_array($endBlk) ? ($endBlk['gehen_korr'] ?? null) : null,
        ];
    }
}

$reportMicroMaxSeconds = 180;
if (isset($microBuchungMaxSeconds)) {
    $v = (int)$microBuchungMaxSeconds;
    if ($v >= 30 && $v <= 3600) {
        $reportMicroMaxSeconds = $v;
    }
}
// Nacht-Schicht-Heuristik: Wenn am Folgetag ein fruehes "Gehen" vor dem ersten "Kommen"
// existiert, behandeln wir dieses Gehen als Schichtende des Vortags.
// Dadurch wird der Vortag (innerhalb eines Zeitfensters) nicht als FEHLT markiert.
$reportOvernightMaxSeconds = 12 * 3600; // 12h Fenster (spaeter ggf. DB-config)
$reportOvernightClosingGoByDatum = [];

if (is_array($tageswerte) && $tageswerte !== []) {
    foreach ($tageswerte as $twIdx) {
        if (!is_array($twIdx)) {
            continue;
        }

        $dIsoTmp = report_normalize_datum_iso((string)($twIdx['datum'] ?? ''));
        if ($dIsoTmp === '') {
            continue;
        }

        // Bloecke analog zur Anzeige normalisieren.
        $blocksTmp = [];
        if (isset($twIdx['arbeitsbloecke']) && is_array($twIdx['arbeitsbloecke']) && $twIdx['arbeitsbloecke'] !== []) {
            foreach ($twIdx['arbeitsbloecke'] as $bTmp) {
                if (is_array($bTmp)) {
                    $blocksTmp[] = $bTmp;
                }
            }
        }
        if ($blocksTmp === []) {
            $blocksTmp = [[
                'kommen_roh'  => $twIdx['kommen_roh'] ?? null,
                'gehen_roh'   => $twIdx['gehen_roh'] ?? null,
                'kommen_korr' => $twIdx['kommen_korr'] ?? null,
                'gehen_korr'  => $twIdx['gehen_korr'] ?? null,
            ]];
        }

        $earliestCome = null;
        $earliestGo = null;

        foreach ($blocksTmp as $bTmp) {
            if (!is_array($bTmp)) {
                continue;
            }

            $kStr = (isset($bTmp['kommen_korr']) && trim((string)$bTmp['kommen_korr']) !== '')
                ? (string)$bTmp['kommen_korr']
                : (string)($bTmp['kommen_roh'] ?? '');
            $gStr = (isset($bTmp['gehen_korr']) && trim((string)$bTmp['gehen_korr']) !== '')
                ? (string)$bTmp['gehen_korr']
                : (string)($bTmp['gehen_roh'] ?? '');

            $kDt = report_parse_dt_berlin($kStr);
            $gDt = report_parse_dt_berlin($gStr);

            if ($kDt !== null && ($earliestCome === null || $kDt->getTimestamp() < $earliestCome->getTimestamp())) {
                $earliestCome = $kDt;
            }
            if ($gDt !== null && ($earliestGo === null || $gDt->getTimestamp() < $earliestGo->getTimestamp())) {
                $earliestGo = $gDt;
            }
        }

        // Kandidat: ein Gehen vor dem ersten Kommen des Tages (oder ohne Kommen).
        if ($earliestGo !== null && ($earliestCome === null || $earliestGo->getTimestamp() < $earliestCome->getTimestamp())) {
            $reportOvernightClosingGoByDatum[$dIsoTmp] = $earliestGo;
        }
    }
}
?>



<section>
    <h2>Monatsübersicht <?php echo (int)$jahr; ?> / <?php echo sprintf('%02d', (int)$monat); ?></h2>

    <?php if ($mitarbeiterAnzeigeName !== ''): ?>
        <p><strong>Mitarbeiter:</strong> <?php echo htmlspecialchars($mitarbeiterAnzeigeName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> (ID <?php echo (int)$mitarbeiterId; ?>)</p>
    <?php endif; ?>

    <form method="get" action="" style="margin-bottom: 0.75rem;">
        <input type="hidden" name="seite" value="report_monat">

        <label style="margin-right: 0.75rem;">
            Jahr
            <input type="number" name="jahr" value="<?php echo (int)$jahr; ?>" min="2000" max="2100" style="width: 6.5rem;">
        </label>

        <label style="margin-right: 0.75rem;">
            Monat
            <select name="monat">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo (int)$m; ?>"<?php echo ((int)$monat === (int)$m) ? ' selected' : ''; ?>>
                        <?php echo sprintf('%02d', (int)$m); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </label>

        <?php if ($hatReportMonatViewAll): ?>
            <label style="margin-right: 0.75rem;">
                Mitarbeiter
                <?php if (is_array($mitarbeiterListe) && count($mitarbeiterListe) > 0): ?>
                    <select name="mitarbeiter_id">
                        <?php foreach ($mitarbeiterListe as $m): ?>
                            <?php
                                $mid = (int)($m['id'] ?? 0);
                                $vn  = trim((string)($m['vorname'] ?? ''));
                                $nn  = trim((string)($m['nachname'] ?? ''));
                                $label = trim($nn . ', ' . $vn);
                                if ($label === ',') {
                                    $label = 'Mitarbeiter ' . $mid;
                                }
                            ?>
                            <option value="<?php echo $mid; ?>"<?php echo ($mid === (int)$mitarbeiterId) ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> (ID <?php echo $mid; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="number" name="mitarbeiter_id" value="<?php echo (int)$mitarbeiterId; ?>" min="1" style="width: 6.5rem;">
                <?php endif; ?>
            </label>
        <?php endif; ?>

        <label style="margin-right: 0.75rem;">
            <input type="checkbox" name="show_micro" value="1"<?php echo $showMicro ? " checked" : ""; ?>>
            Mikro-Buchungen anzeigen
        </label>

        <button type="submit">Anzeigen</button>
    </form>

    <p>
        <a href="?seite=report_monat_pdf&amp;jahr=<?php echo (int)$jahr; ?>&amp;monat=<?php echo (int)$monat; ?>&amp;mitarbeiter_id=<?php echo (int)$mitarbeiterId; ?><?php echo $showMicro ? '&amp;show_micro=1' : ''; ?>" target="_blank" rel="noopener">PDF anzeigen</a>
        <?php if ($hatReportMonatExportAll): ?>
            &nbsp;|&nbsp;
            <a href="?seite=report_monat_export_all&amp;jahr=<?php echo (int)$jahr; ?>&amp;monat=<?php echo (int)$monat; ?>">Sammel-Export (ZIP)</a>
        <?php endif; ?>
    </p>

    <?php if ($monatswerte !== null): ?>
        <p>
            Sollstunden: <?php echo htmlspecialchars((string)($monatswerte['sollstunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>,
            Iststunden: <?php echo htmlspecialchars((string)($monatswerte['iststunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>,
            Differenz: <?php echo htmlspecialchars((string)($monatswerte['differenzstunden'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            <?php if (!empty($monatswerte['ist_fallback'])): ?>
                <em>(berechnet)</em>
            <?php endif; ?>

            <?php if ($stundenkontoSaldoText !== ''): ?>
                <br>
                <?php echo htmlspecialchars($stundenkontoSaldoLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>: <?php echo htmlspecialchars($stundenkontoSaldoText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            <?php endif; ?>

            <?php if ($urlaubAbzglBfText !== ''): ?>
                <br>
                Urlaubtage (abzgl. BF): <strong><?php echo htmlspecialchars($urlaubAbzglBfText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                <br>
                BF (Rest Jahr): <?php echo htmlspecialchars($bfRestArbeitstageText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Arbeitstage
            <?php endif; ?>
        </p>
    <?php endif; ?>


    <?php
        // Stundenkonto: Monatsabschluss-Knopf (Differenz Soll/Ist ins Stundenkonto buchen)
        $monatsabschlussMsgText = '';
        $monatsabschlussMsgColor = '#333';
        $msg = isset($stundenkontoMonatsabschlussMsg) ? (string)$stundenkontoMonatsabschlussMsg : '';
        if ($msg !== '') {
            switch ($msg) {
                case 'ok':
                    $monatsabschlussMsgText = 'Monatsabschluss wurde gebucht.';
                    $monatsabschlussMsgColor = 'green';
                    break;
                case 'err':
                    $monatsabschlussMsgText = 'Fehler: Monatsabschluss konnte nicht gebucht werden.';
                    $monatsabschlussMsgColor = 'red';
                    break;
                case 'no_right':
                    $monatsabschlussMsgText = 'Zugriff verweigert: Kein Recht fuer Stundenkonto.';
                    $monatsabschlussMsgColor = 'red';
                    break;
                case 'not_past':
                    $monatsabschlussMsgText = 'Monatsabschluss ist nur fuer vergangene Monate moeglich.';
                    $monatsabschlussMsgColor = 'red';
                    break;
                case 'csrf':
                    $monatsabschlussMsgText = 'Sicherheits-Token ungueltig. Bitte Seite neu laden.';
                    $monatsabschlussMsgColor = 'red';
                    break;
                case 'not_logged':
                    $monatsabschlussMsgText = 'Fehler: Benutzer nicht korrekt angemeldet.';
                    $monatsabschlussMsgColor = 'red';
                    break;
            }
        }

        $monatsabschlussBlockZeigen = false;
        if (!empty($kannStundenkontoVerwalten) && !empty($istMonatVergangen) && $monatswerte !== null) {
            $monatsabschlussBlockZeigen = true;
        }

        $monatsabschlussActionUrl = '?seite=report_monat&jahr=' . (int)$jahr . '&monat=' . (int)$monat;
        if (!empty($hatReportMonatViewAll)) {
            $monatsabschlussActionUrl .= '&mitarbeiter_id=' . (int)$mitarbeiterId;
        }
        if (!empty($showMicro)) {
            $monatsabschlussActionUrl .= '&show_micro=1';
        }

        $skFormat = null;
        try {
            if (class_exists('StundenkontoService')) {
                $skFormat = StundenkontoService::getInstanz();
            }
        } catch (\Throwable $e) {
            $skFormat = null;
        }

        $monatsabschlussBerechnetText = '';
        $monatsabschlussGebuchtText = '';
        if ($skFormat !== null) {
            $monatsabschlussBerechnetText = $skFormat->formatMinutenAlsStundenString((int)($monatsabschlussBerechnetDeltaMinuten ?? 0), true) . ' h';
            $monatsabschlussGebuchtText = $skFormat->formatMinutenAlsStundenString((int)($monatsabschlussGebuchtDeltaMinuten ?? 0), true) . ' h';
        }
    ?>

    <?php if ($monatsabschlussMsgText !== ''): ?>
        <p style="margin:6px 0; color: <?php echo htmlspecialchars($monatsabschlussMsgColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;">
            <?php echo htmlspecialchars($monatsabschlussMsgText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if ($monatsabschlussBlockZeigen): ?>
        <div style="margin: 8px 0; padding: 10px; border: 1px dashed #ccc; background: #fafafa;">
            <strong>Monatsabschluss (Stundenkonto)</strong><br>
            Berechnete Differenz (Soll/Ist): <strong><?php echo htmlspecialchars($monatsabschlussBerechnetText !== '' ? $monatsabschlussBerechnetText : (string)$monatsabschlussBerechnetDeltaMinuten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            <?php if (!empty($monatsabschlussGebucht)): ?>
                <br>Bereits gebucht: <strong><?php echo htmlspecialchars($monatsabschlussGebuchtText !== '' ? $monatsabschlussGebuchtText : (string)$monatsabschlussGebuchtDeltaMinuten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            <?php else: ?>
                <br><em>Noch nicht gebucht.</em>
            <?php endif; ?>

            <?php if (((int)($monatsabschlussBerechnetDeltaMinuten ?? 0) !== 0) || (!empty($monatsabschlussGebucht))): ?>
                <form method="post" action="<?php echo htmlspecialchars($monatsabschlussActionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="margin-top: 8px;">
                    <input type="hidden" name="aktion" value="monatsabschluss_buchen">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($csrfTokenMonatsabschluss ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <button type="submit"><?php echo !empty($monatsabschlussGebucht) ? 'Monatsabschluss aktualisieren' : 'Monatsabschluss buchen'; ?></button>
                </form>
            <?php else: ?>
                <div style="margin-top: 8px;"><em>Differenz ist 0 – keine Buchung noetig.</em></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <?php
        // Vorab-Scan: Monat enthaelt Tage mit unvollstaendigen Kommen/Gehen-Stempeln?
        $monatHatZeitUnstimmigkeit = false;
        $zeitUnstimmigeTage = [];

        if (is_array($tageswerte) && $tageswerte !== []) {
            foreach ($tageswerte as $tScan) {
                $datumIsoScan = report_normalize_datum_iso((string)($tScan['datum'] ?? ''));
                if ($datumIsoScan === '') {
                    continue;
                }

                // Heute (und zukuenftige Tage) sind noch nicht abgeschlossen: hier keine FEHLT-Markierung.
                if ($datumIsoScan >= $heuteIso) {
                    continue;
                }
                // Mikro-Buchungen (<= 3 Minuten) werden nicht als Unstimmigkeit bewertet.
                if (((int)($tScan['micro_arbeitszeit_ignoriert'] ?? 0) === 1)) {
                    continue;
                }


                $bloeckeScan = [];
                if (isset($tScan['arbeitsbloecke']) && is_array($tScan['arbeitsbloecke']) && $tScan['arbeitsbloecke'] !== []) {
                    $bloeckeScan = $tScan['arbeitsbloecke'];
                } else {
                    $bloeckeScan = [[
                        'kommen_roh'  => $tScan['kommen_roh'] ?? null,
                        'gehen_roh'   => $tScan['gehen_roh'] ?? null,
                        'kommen_korr' => $tScan['kommen_korr'] ?? null,
                        'gehen_korr'  => $tScan['gehen_korr'] ?? null,
                    ]];
                }

                // Nachtschicht-Helper (auch im Vorab-Scan berücksichtigen), damit
                // die ⚠-Hinweiszeile und die Tages-⚠-Marker nicht durch "Gehen am Folgetag"
                // (Schichtende) fälschlich ausgelöst werden.
                $overnightThisGoScan = ($datumIsoScan !== '' && isset($reportOvernightClosingGoByDatum[$datumIsoScan]))
                    ? $reportOvernightClosingGoByDatum[$datumIsoScan]
                    : null;

                $overnightNextGoScan = null;
                $nextIsoScan = '';
                if ($datumIsoScan !== '') {
                    $dtTmpScan = \DateTimeImmutable::createFromFormat('Y-m-d', $datumIsoScan, new \DateTimeZone('Europe/Berlin'));
                    if ($dtTmpScan !== false) {
                        $nextIsoScan = $dtTmpScan->modify('+1 day')->format('Y-m-d');
                        $overnightNextGoScan = $reportOvernightClosingGoByDatum[$nextIsoScan] ?? null;
                    }
                }

                $tagUnstimmig = false;
                foreach ($bloeckeScan as $bScan) {
                    $kR = isset($bScan['kommen_roh']) ? trim((string)$bScan['kommen_roh']) : '';
                    $gR = isset($bScan['gehen_roh']) ? trim((string)$bScan['gehen_roh']) : '';
                    $kK = isset($bScan['kommen_korr']) ? trim((string)$bScan['kommen_korr']) : '';
                    $gK = isset($bScan['gehen_korr']) ? trim((string)$bScan['gehen_korr']) : '';

                    $kMain = ($kK !== '') ? $kK : $kR;
                    $gMain = ($gK !== '') ? $gK : $gR;

                    $hatStempel = ($kR !== '' || $gR !== '' || $kK !== '' || $gK !== '');
                    $blockUnvollstaendig = ($hatStempel && ($kMain === '' || $gMain === ''));

                    if ($blockUnvollstaendig) {
                        // Ausnahme: Nachtschicht über Mitternacht (Kommen gestern, Gehen früh am Folgetag).
                        $istUebernachtOkScan = false;

                        if ($kMain !== '' && $gMain === '' && ($overnightNextGoScan instanceof \DateTimeImmutable)) {
                            $kDt = report_parse_dt_berlin($kMain);
                            if ($kDt !== null) {
                                $diff = $overnightNextGoScan->getTimestamp() - $kDt->getTimestamp();
                                if ($diff > 0 && $diff <= $reportOvernightMaxSeconds) {
                                    $istUebernachtOkScan = true;
                                }
                            }
                        } elseif ($kMain === '' && $gMain !== '' && ($overnightThisGoScan instanceof \DateTimeImmutable)) {
                            $gDt = report_parse_dt_berlin($gMain);
                            if (report_dt_is_close($gDt, $overnightThisGoScan, 60)) {
                                $istUebernachtOkScan = true;
                            }
                        }

                        if (!$istUebernachtOkScan) {
                            $tagUnstimmig = true;
                            break;
                        }
                    }
                }

                if ($tagUnstimmig) {
                    $monatHatZeitUnstimmigkeit = true;
                    $zeitUnstimmigeTage[] = $datumIsoScan;
                }
            }
        }
    ?>

    <?php if ($monatHatZeitUnstimmigkeit): ?>
        <p style="margin:0.5rem 0; color:#b71c1c; font-weight:bold;">
            ⚠ Hinweis: Dieser Monat enthält Tage mit <strong>unvollständigen</strong> Kommen/Gehen-Stempeln.
            Betroffene Tage sind in der Tabelle mit <strong>FEHLT</strong> markiert.
        </p>
    <?php endif; ?>

    <?php if ($tageswerte === []): ?>
        <p>Für diesen Monat liegen noch keine Tageswerte vor.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Datum</th>
                <th>An</th>
                <th>Ab</th>
                <th>Ist (Block)</th>
                <th>Pausen</th>
	                <th>Kurzarbeit</th>
                <th>Feiertag</th>
                <th>Urlaub</th>
                <th>Saldo</th>
                <th>Typ</th>
                <th>Kürzel</th>
                <?php if ($darfZeitBearbeiten): ?>
                    <th>Bearbeiten</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tageswerte as $t): ?>
                <?php
                    $datumIso = report_normalize_datum_iso((string)($t['datum'] ?? ''));
                    $istBetriebsferien = !empty($t['ist_betriebsferien']);

                    // Manuelle Zeitbuchungen und Tagesfelder separat behandeln (nur betroffene Zellen markieren).
                    $felderManuell = ((int)($t['felder_manuell_geaendert'] ?? 0) === 1);
                    $pauseOverrideAktiv = ((int)($t['pause_override_aktiv'] ?? 0) === 1);

                    // Arbeitsblöcke (Mehrfach-Kommen/Gehen): falls vorhanden, Tag mehrfach anzeigen.
                    $bloecke = [];

                    // Wichtig: defensive Normalisierung – einzelne Elemente koennen (durch fehlerhafte Daten)
                    // keine Arrays sein. Das wuerde spaeter in der View zu TypeErrors fuehren.
                    if (isset($t['arbeitsbloecke']) && is_array($t['arbeitsbloecke']) && $t['arbeitsbloecke'] !== []) {
                        $tmp = [];
                        foreach ($t['arbeitsbloecke'] as $bTmp) {
                            if (is_array($bTmp)) {
                                $tmp[] = $bTmp;
                            }
                        }
                        if ($tmp !== []) {
                            $bloecke = $tmp;
                        }
                    }

                    if ($bloecke === []) {
                        // Fallback: eine Zeile aus den Tageswerten.
                        $bloecke = [[
                            'kommen_roh'  => $t['kommen_roh'] ?? null,
                            'gehen_roh'   => $t['gehen_roh'] ?? null,
                            'kommen_korr' => $t['kommen_korr'] ?? null,
                            'gehen_korr'  => $t['gehen_korr'] ?? null,
                        ]];
                    }

                    // Mikro-Buchungen (pro Block) optional ausblenden.
                    // Wichtig: Ein Tag kann normale + Mikro-Bloecke enthalten;
                    // ohne show_micro wollen wir Mikro-Bloecke komplett ausblenden.
                    if (!$showMicro && $bloecke !== []) {
                        $tmpBlocks = [];
                        foreach ($bloecke as $bTmp2) {
                            if (!is_array($bTmp2)) {
                                continue;
                            }
                            if (report_is_micro_block($bTmp2, $reportMicroMaxSeconds)) {
                                continue;
                            }
                            $tmpBlocks[] = $bTmp2;
                        }
                        if ($tmpBlocks === []) {
                            $tmpBlocks = [[
                                'kommen_roh'  => null,
                                'gehen_roh'   => null,
                                'kommen_korr' => null,
                                'gehen_korr'  => null,
                            ]];
                        }
                        $bloecke = $tmpBlocks;
                    }
                    // Primaer-Zeile fuer Meta-Felder (Pause/Kurzarbeit/Feiertag/Urlaub):
                    // Erste sichtbare Blockzeile mit Dauer >= 60 Minuten, sonst die erste Blockzeile.
                    $reportMetaPrimaryMinSeconds = 3600;
                    $metaPrimaryIndex = 0;
                    foreach ($bloecke as $iMeta => $bMeta) {
                        if (report_calc_block_seconds($bMeta) >= $reportMetaPrimaryMinSeconds) {
                            $metaPrimaryIndex = (int)$iMeta;
                            break;
                        }
                    }

                    // Tages-Warnflag: mind. ein Block hat nur Kommen oder nur Gehen.
                    // Wichtig: fuer "heute" (und zukuenftige Tage) markieren wir das nicht als Fehler.
                    $tagIstVergangen = ($datumIso !== '' && $datumIso < $heuteIso);

                    // Nachtschicht-Helper: (Kommen gestern, Gehen heute)
                    $overnightThisGo = ($datumIso !== '' && isset($reportOvernightClosingGoByDatum[$datumIso]))
                        ? $reportOvernightClosingGoByDatum[$datumIso]
                        : null;
                    $overnightNextGo = null;
                    $nextIso = '';
                    if ($datumIso !== '') {
                        $dtTmp = \DateTimeImmutable::createFromFormat('Y-m-d', $datumIso, new \DateTimeZone('Europe/Berlin'));
                        if ($dtTmp !== false) {
                            $nextIso = $dtTmp->modify('+1 day')->format('Y-m-d');
                            $overnightNextGo = $reportOvernightClosingGoByDatum[$nextIso] ?? null;
                        }
                    }

                    $tagHatZeitUnstimmigkeit = false;
                    foreach ($bloecke as $bChk) {
                        if (!is_array($bChk)) {
                            continue;
                        }
                        if (report_is_micro_block($bChk, $reportMicroMaxSeconds)) {
                            continue;
                        }
                        $kR = isset($bChk['kommen_roh']) ? trim((string)$bChk['kommen_roh']) : '';
                        $gR = isset($bChk['gehen_roh']) ? trim((string)$bChk['gehen_roh']) : '';
                        $kK = isset($bChk['kommen_korr']) ? trim((string)$bChk['kommen_korr']) : '';
                        $gK = isset($bChk['gehen_korr']) ? trim((string)$bChk['gehen_korr']) : '';

                        $kMain = ($kK !== '') ? $kK : $kR;
                        $gMain = ($gK !== '') ? $gK : $gR;

                        $hatStempel = ($kR !== '' || $gR !== '' || $kK !== '' || $gK !== '');
                        if ($tagIstVergangen && $hatStempel && ($kMain === '' || $gMain === '')) {
                            // Ausnahme: Nachtschicht ueber Mitternacht.
                            // - Vortag: Kommen vorhanden, Gehen fehlt, aber am Folgetag existiert ein fruehes Gehen (vor dem ersten Kommen)
                            //   innerhalb $reportOvernightMaxSeconds.
                            // - Folgetag: Dieses fruehe Gehen ohne Kommen gilt als Abschluss des Vortags (nicht als FEHLT).
                            $istUebernachtOk = false;

                            if ($kMain !== '' && $gMain === '' && ($overnightNextGo instanceof \DateTimeImmutable)) {
                                $kDt = report_parse_dt_berlin($kMain);
                                if ($kDt !== null) {
                                    $diff = $overnightNextGo->getTimestamp() - $kDt->getTimestamp();
                                    if ($diff > 0 && $diff <= $reportOvernightMaxSeconds) {
                                        $istUebernachtOk = true;
                                    }
                                }
                            } elseif ($kMain === '' && $gMain !== '' && ($overnightThisGo instanceof \DateTimeImmutable)) {
                                $gDt = report_parse_dt_berlin($gMain);
                                if (report_dt_is_close($gDt, $overnightThisGo, 60)) {
                                    $istUebernachtOk = true;
                                }
                            }

                            if (!$istUebernachtOk) {
                                $tagHatZeitUnstimmigkeit = true;
                                break;
                            }
                        }
                    }

                    // Betriebsferien = gelb (manuelle Markierung erfolgt pro Zelle).
                    $trStyle = '';
                    if ($istBetriebsferien) {
                        $trStyle = ' style="background:#fffde7;"';
                    }

                    $istMicroIgnoriert = ((int)($t['micro_arbeitszeit_ignoriert'] ?? 0) === 1);
                    // Standard: ohne show_micro zeigen wir alle NICHT-Mikro-Arbeitsbloecke.
                    // (Mehrfach-Kommen/Gehen bleibt sichtbar; Mikro-Bloecke wurden oben bereits gefiltert.)

                ?>

                <?php foreach ($bloecke as $idx => $b): ?>
                    <?php
                        $istErsteZeile = ($idx === 0);
                        $istMetaZeile = ($idx === $metaPrimaryIndex);
                        $istKommenManuell = ((int)($b['kommen_manuell_geaendert'] ?? 0) === 1);
                        $istGehenManuell = ((int)($b['gehen_manuell_geaendert'] ?? 0) === 1);

                        // Anzeige: Rohzeiten bleiben gespeichert, korrigierte Zeiten werden nur berechnet.
                        $kommenRoh  = isset($b['kommen_roh'])  ? (string)$b['kommen_roh']  : '';
                        $gehenRoh   = isset($b['gehen_roh'])   ? (string)$b['gehen_roh']   : '';
                        $kommenKorr = isset($b['kommen_korr']) ? (string)$b['kommen_korr'] : '';
                        $gehenKorr  = isset($b['gehen_korr'])  ? (string)$b['gehen_korr']  : '';

                        $kommenRohAnzeige  = report_format_uhrzeit($kommenRoh !== '' ? $kommenRoh : null);
                        $gehenRohAnzeige   = report_format_uhrzeit($gehenRoh !== '' ? $gehenRoh : null);
                        $kommenKorrAnzeige = report_format_uhrzeit($kommenKorr !== '' ? $kommenKorr : null);
                        $gehenKorrAnzeige  = report_format_uhrzeit($gehenKorr !== '' ? $gehenKorr : null);

                        $kommenMain = $kommenKorrAnzeige !== '' ? $kommenKorrAnzeige : $kommenRohAnzeige;
                        $gehenMain  = $gehenKorrAnzeige !== '' ? $gehenKorrAnzeige : $gehenRohAnzeige;

                        $istNachtshiftBlock = ((int)($b['nachtshift'] ?? 0) === 1);
                        if ($istNachtshiftBlock && $kommenMain === '' && $gehenMain !== '') {
                            $kommenMain = '00:00';
                        }
                        if ($istNachtshiftBlock && $gehenMain === '' && $kommenMain !== '') {
                            $gehenMain = '00:00';
                        }

                        $kommenRohExtra = ($kommenRohAnzeige !== '' && $kommenMain !== $kommenRohAnzeige) ? $kommenRohAnzeige : '';
                        $gehenRohExtra  = ($gehenRohAnzeige !== '' && $gehenMain !== $gehenRohAnzeige) ? $gehenRohAnzeige : '';

                        $tdKommenStyle = ($istKommenManuell && $kommenMain !== '') ? ' style="background:#ffcdd2;"' : '';
                        $tdGehenStyle = ($istGehenManuell && $gehenMain !== '') ? ' style="background:#ffcdd2;"' : '';

                        $hatStempel = ($kommenRoh !== '' || $gehenRoh !== '' || $kommenKorr !== '' || $gehenKorr !== '');

                        if ($istMicroIgnoriert && !$showMicro) {
                            // Mikro-Buchung: in der Monatsübersicht komplett ausblenden (keine Zeiten, keine FEHLT-Markierung).
                            $kommenMain = '';
                            $gehenMain = '';
                            $kommenRohExtra = '';
                            $gehenRohExtra = '';
                            $hatStempel = false;
                        }
                        // Nachtschicht-Exception pro Block (Kommen gestern, Gehen heute).
                        $kMainRaw = trim(($kommenKorr !== '' ? $kommenKorr : $kommenRoh));
                        $gMainRaw = trim(($gehenKorr !== '' ? $gehenKorr : $gehenRoh));
                        $blockIstUebernachtOk = false;
                        if ($tagIstVergangen && $hatStempel && ($kMainRaw === '' || $gMainRaw === '')) {
                            if ($kMainRaw !== '' && $gMainRaw === '' && ($overnightNextGo instanceof \DateTimeImmutable)) {
                                $kDt = report_parse_dt_berlin($kMainRaw);
                                if ($kDt !== null) {
                                    $diff = $overnightNextGo->getTimestamp() - $kDt->getTimestamp();
                                    if ($diff > 0 && $diff <= $reportOvernightMaxSeconds) {
                                        $blockIstUebernachtOk = true;
                                    }
                                }
                            } elseif ($kMainRaw === '' && $gMainRaw !== '' && ($overnightThisGo instanceof \DateTimeImmutable)) {
                                $gDt = report_parse_dt_berlin($gMainRaw);
                                if (report_dt_is_close($gDt, $overnightThisGo, 60)) {
                                    $blockIstUebernachtOk = true;
                                }
                            }
                        }
                        $blockUnvollstaendig = ($tagIstVergangen && $hatStempel && ($kommenMain === '' || $gehenMain === '') && !$blockIstUebernachtOk);
                        $kommenFehlt = ($blockUnvollstaendig && $kommenMain === '');
                        $gehenFehlt  = ($blockUnvollstaendig && $gehenMain === '');
                        if ($istMicroIgnoriert) {
                            // Mikro-Buchungen werden nie als "FEHLT" gewertet (auch wenn eingeblendet).
                            $blockUnvollstaendig = false;
                            $kommenFehlt = false;
                            $gehenFehlt = false;
                        }
                    ?>
                    <tr<?php echo $trStyle; ?>>
                        <td<?php echo $istErsteZeile ? ' title="' . htmlspecialchars($datumIso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : ''; ?>>
                            <?php echo $istErsteZeile ? htmlspecialchars(report_format_datum($datumIso), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : ''; ?>
                            <?php if ($istErsteZeile && $tagHatZeitUnstimmigkeit): ?>
                                <span style="color:#b71c1c; font-weight:bold;">&nbsp;⚠</span>
                            <?php endif; ?>
                        </td>

                        <td<?php echo $tdKommenStyle; ?>>
                            <?php if ($kommenFehlt): ?>
                                <span style="color:#b71c1c; font-weight:bold;">FEHLT</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($kommenMain !== '' ? $kommenMain : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                <?php if ($kommenRohExtra !== ''): ?><br><small>roh: <?php echo htmlspecialchars($kommenRohExtra, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small><?php endif; ?>
                                <?php if ($istMicroIgnoriert && $showMicro): ?><br><small style="color:#777;">micro</small><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td<?php echo $tdGehenStyle; ?>>
                            <?php if ($gehenFehlt): ?>
                                <span style="color:#b71c1c; font-weight:bold;">FEHLT</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($gehenMain !== '' ? $gehenMain : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                <?php if ($gehenRohExtra !== ''): ?><br><small>roh: <?php echo htmlspecialchars($gehenRohExtra, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small><?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php
                                $blockIstShow = report_calc_block_ist_dez2($b);
                            ?>
                            <?php echo htmlspecialchars($blockIstShow !== '' ? $blockIstShow : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </td>
                        <?php
                            $pauseShow = '';
                            $pauseManuell = false;
                            if ($istMetaZeile) {
                                $pauseShow = $istMicroIgnoriert ? '-' : (string)($t['pausen_stunden'] ?? '');
                                $pauseManuell = $pauseOverrideAktiv;
                            }
                            $tdPauseStyle = $pauseManuell ? ' style="background:#ffcdd2;"' : '';
                        ?>
                        <td<?php echo $tdPauseStyle; ?>>
                            <?php
                                if ($istMetaZeile) {
                                    echo htmlspecialchars($pauseShow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                }
                            ?>
                        </td>

                        <?php
                            $kaShow = '-';
                            $kaManuell = false;
                            if ($istMetaZeile) {
                                $ka = isset($t['kurzarbeit_stunden']) ? trim((string)$t['kurzarbeit_stunden']) : '';
                                if ($ka !== '' && $ka !== '0' && $ka !== '0.0' && $ka !== '0.00') {
                                    $kaShow = $ka;
                                }
                                $kaManuell = $felderManuell && $kaShow !== '-';
                            }
                            $tdKurzStyle = $kaManuell ? ' style="background:#ffcdd2;"' : '';
                        ?>
                        <td<?php echo $tdKurzStyle; ?>>
                            <?php if ($istMetaZeile): ?>
                                <?php
                                    echo htmlspecialchars($kaShow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                            <?php endif; ?>
                        </td>
                        <?php
                            $ftShow = '-';
                            $ftManuell = false;
                            if ($istMetaZeile) {
                                $ft = isset($t['feiertag_stunden']) ? trim((string)$t['feiertag_stunden']) : '';
                                $ftF = 0.0;
                                if ($ft !== '') {
                                    $ftF = (float)str_replace(',', '.', $ft);
                                }
                                if ($ftF > 0.01) {
                                    $ftShow = $ft;
                                }
                                $ftManuell = $felderManuell && $ftShow !== '-';
                            }
                            $tdFeiertagStyle = $ftManuell ? ' style="background:#ffcdd2;"' : '';
                        ?>
                        <td<?php echo $tdFeiertagStyle; ?>>
                            <?php if ($istMetaZeile): ?>
                                <?php
                                    echo htmlspecialchars($ftShow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                            <?php endif; ?>
                        </td>
                        <?php
                            $uShow = '-';
                            $uManuell = false;
                            if ($istMetaZeile) {
                                $u = isset($t['urlaub_stunden']) ? trim((string)$t['urlaub_stunden']) : '';
                                $uF = 0.0;
                                if ($u !== '') {
                                    $uF = (float)str_replace(',', '.', $u);
                                }
                                if ($uF > 0.01) {
                                    $uShow = $u;
                                }
                                $uManuell = $felderManuell && $uShow !== '-';
                            }
                            $tdUrlaubStyle = $uManuell ? ' style="background:#ffcdd2;"' : '';
                        ?>
                        <td<?php echo $tdUrlaubStyle; ?>>
                            <?php if ($istMetaZeile): ?>
                                <?php
                                    echo htmlspecialchars($uShow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $istErsteZeile ? htmlspecialchars(($istMicroIgnoriert ? '-' : (string)($t['saldo_stunden'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : ''; ?>
                        </td>
                        <td>
                            <?php echo $istErsteZeile ? htmlspecialchars((string)($t['tagestyp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : ''; ?>
                        </td>
                        <td>
                            <?php if ($istErsteZeile): ?>
                                <?php
                                    $k = isset($t['kommentar']) ? trim((string)$t['kommentar']) : '';
                                    echo htmlspecialchars($k !== '' ? $k : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                            <?php endif; ?>
                        </td>

                        <?php if ($darfZeitBearbeiten): ?>
                            <td>
                                <?php if ($istErsteZeile): ?>
                                    <a href="?seite=zeit_heute&amp;datum=<?php echo urlencode($datumIso); ?>&amp;mitarbeiter_id=<?php echo (int)$mitarbeiterId; ?><?php echo $showMicro ? '&amp;show_micro=1' : ''; ?>">Bearbeiten</a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
