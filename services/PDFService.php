<?php
declare(strict_types=1);

/**
 * PDFService
 *
 * Erzeugt Auswertungs-PDFs (derzeit: Monats-Arbeitszeitliste).
 *
 * Wichtig (Projektregel):
 * - In der DB bleiben weiterhin die Rohzeiten gespeichert.
 * - Rundungen/Korrekturen passieren nur für Anzeige/Berechnung (ReportService).
 */
class PDFService
{
    private static ?PDFService $instanz = null;

    private ReportService $reportService;
    private MitarbeiterModel $mitarbeiterModel;
    private StundenkontoService $stundenkontoService;
    private UrlaubService $urlaubService;

    private function __construct()
    {
        $this->reportService    = ReportService::getInstanz();
        $this->mitarbeiterModel = new MitarbeiterModel();
        $this->stundenkontoService = StundenkontoService::getInstanz();
        $this->urlaubService = UrlaubService::getInstanz();
    }

    public static function getInstanz(): PDFService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Erzeugt ein Monats-PDF (Arbeitszeitliste) für einen Mitarbeiter.
     *
     * Rückgabe:
     * - Binärdaten des PDF-Dokuments (application/pdf)
     * - Bei Fehlern: leerer String
     */
    public function erzeugeMonatsPdfFuerMitarbeiter(int $mitarbeiterId, int $jahr, int $monat, ?bool $showMicro = null): string
    {
        try {
            $daten = $this->reportService->holeMonatsdatenFuerMitarbeiter($mitarbeiterId, $jahr, $monat);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Report-Daten für PDF', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'pdf');
            }
            return '';
        }

        $jahr  = (int)($daten['jahr'] ?? $jahr);
        $monat = (int)($daten['monat'] ?? $monat);

        $mitarbeiterName = 'ID ' . $mitarbeiterId;
        try {
            $m = $this->mitarbeiterModel->holeNachId($mitarbeiterId);
            if (is_array($m)) {
                $vn = trim((string)($m['vorname'] ?? ''));
                $nn = trim((string)($m['nachname'] ?? ''));
                $name = trim($vn . ' ' . $nn);
                if ($name !== '') {
                    $mitarbeiterName = $name;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $tageswerte  = is_array($daten['tageswerte'] ?? null) ? (array)$daten['tageswerte'] : [];
        $monatswerte = is_array($daten['monatswerte'] ?? null) ? (array)$daten['monatswerte'] : null;

        if ($showMicro === null) {
            $showMicro = $this->istShowMicroRequest();
        }

        try {
            return $this->baueArbeitszeitlistePdf($mitarbeiterId, $mitarbeiterName, $jahr, $monat, $tageswerte, $monatswerte, (bool)$showMicro);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Generieren des Arbeitszeitliste-PDF', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'pdf');
            }
            return '';
        }
    }


    /**
     * Erzeugt ein Monats-PDF (Arbeitszeitliste) aus bereits berechneten Report-Daten.
     *
     * Hinweis: Wird primär für Smoke-Tests/Diagnose genutzt, um Randfälle (z. B. Mehrfach-Kommen/Gehen
     * und Mehrseiten-Umbruch) reproduzierbar zu prüfen, ohne DB-Inhalte vorauszusetzen.
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @param array<string,mixed>|null       $monatswerte
     */
    public function erzeugeMonatsPdfAusDaten(int $mitarbeiterId, string $mitarbeiterName, int $jahr, int $monat, array $tageswerte, ?array $monatswerte, ?bool $showMicro = null): string
    {
        if ($showMicro === null) {
            $showMicro = $this->istShowMicroRequest();
        }

        try {
            return $this->baueArbeitszeitlistePdf($mitarbeiterId, $mitarbeiterName, $jahr, $monat, $tageswerte, $monatswerte, (bool)$showMicro);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Generieren des Arbeitszeitliste-PDF (aus Daten)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'pdf');
            }
            return '';
        }
    }

    /**
     * Baut die Arbeitszeitliste optisch ähnlich zur Vorlage (vorlageausgabezeiten.pdf).
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @param array<string,mixed>|null       $monatswerte
     */
    private function baueArbeitszeitlistePdf(int $mitarbeiterId, string $mitarbeiterName, int $jahr, int $monat, array $tageswerte, ?array $monatswerte, bool $showMicro = false): string
    {
        // --- A4 Portrait ---
        $pageW = 595.0;
        $pageH = 842.0;

        $marginL = 40.0;
        $marginR = 40.0;
        $rightX  = $pageW - $marginR;
        $centerX = $pageW / 2.0;

        // Kopf
        $topLineY = 830.0;
        $titleY   = 810.0;
        $infoY    = 785.0;
        $origY    = 765.0;

        // Tabelle
        $tableX     = $marginL;
        $tableTopY  = 750.0;
        // Etwas kompaktere Zeilenhoehe, damit Monate mit wenigen Mehrfach-Bloecken
        // (z.B. 1-2 Tage mit mehreren Kommen/Gehen-Paaren) haeufig noch auf 1 Seite passen.
        // Hinweis: Die Zeilenhoehe wird spaeter ggf. automatisch minimal reduziert, wenn dadurch
        // ein Grenzfall (knapp 2-seitig) wieder auf 1 Seite passt.
        $rowH       = 15.0;

        // Spaltenbreiten (Summe = 515pt) – an Vorlage angelehnt
        $colW = [
            65, // Tag / KW
            31, // leer
            34, // An
            34, // Ab
            34, // An.Korr
            28, // Pause
            34, // Ab.Korr
            28, // Ist
            28, // Arzt
            34, // Krank KK
            36, // Krank LF
            36, // Feiertag
            30, // Kurzar.
            32, // Urlaub
            31, // Sonst
        ];

        $colX = [$tableX];
        $sum = $tableX;
        foreach ($colW as $w) {
            $sum += (float)$w;
            $colX[] = $sum;
        }
        $tableW = $sum - $tableX;
        $tableRightX = $tableX + $tableW;

        // Zeitraum
        $von = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
        $bis = $von->modify('last day of this month');
        $druckDatum = (new \DateTimeImmutable('now'))->format('d.m.Y');

        $zeitraumText = 'von: ' . $von->format('d.m.Y') . '   bis: ' . $bis->format('d.m.Y');

        // Tageswerte nach Datum mappen
        $map = [];
        foreach ($tageswerte as $t) {
            if (!is_array($t)) {
                continue;
            }
            $d = trim((string)($t['datum'] ?? ''));
            if ($d !== '') {
                $map[$d] = $t;
            }
        }

        $daysInMonth = (int)$bis->format('j');

        // Mikro-Buchungs-Grenze (Sekunden) aus Konfiguration (Default 180).
        // Wichtig: Der PDF-Mikro-Filter muss sich wie die Monatsuebersicht primär am Rohstempel orientieren,
        // da Rundungen sonst zu Rueckspruengen (Ende < Start) fuehren koennen.
        $microMaxSeconds = $this->holeMicroBuchungMaxSeconds();

        // Header + Datenzeilen (Mehrfach-Kommen/Gehen: je Arbeitsblock eine Zeile)
        $rows = [];
        $zellMarkierungen = [];
        $rows[] = [
            'Tag / KW',
            'Kürzel',
            'An',
            'Ab',
            'An.Korr',
            'Pause',
            'Ab.Korr',
            'Ist',
            'Arzt',
            'Krank KK',
            'Krank LF',
            'Feiertag',
            'Kurzar.',
            'Urlaub',
            'Sonst',
        ];
        // Kopfzeile nie farblich markieren
        $zellMarkierungen[] = array_fill(0, 15, false);

        // Summen für Block unten
        $sumIst = 0.0;
        $sumArzt = 0.0;
        $sumKrankLfz = 0.0;
        $sumKrankKk = 0.0;
        $sumFeiertag = 0.0;
        $sumKurzarbeit = 0.0;
        $sumUrlaub = 0.0;
        $sumSonst = 0.0;

        $bemerkungen = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $ymd = sprintf('%04d-%02d-%02d', $jahr, $monat, $day);
            $dt = new \DateTimeImmutable($ymd);

            $kw = $dt->format('W');
            $wd = $this->wochentagKurzDe($ymd);

            $tagKw = $day . ' ' . $wd . ' / ' . $kw;

            $t = $map[$ymd] ?? null;

            // Tageswerte (werden nur in der ersten Zeile ausgegeben, um Doppelzählung zu vermeiden)
            $pause = '';
            $ist   = '';

            $arzt = '';
            $krankKk = '';
            $krankLfz = '';
            $feiertag = '';
            $kurz = '';
            $urlaub = '';
            $sonst = '';

            $flagNotiz = '';
            $felderManuell = false;
            $pauseOverrideAktiv = false;

            // Arbeitsblöcke (Mehrfach-Kommen/Gehen): je Block eine Zeile.
            // Wenn keine Blöcke vorhanden sind, fällt der Tag auf eine Zeile aus den Tageswerten zurück.
            $bloecke = [];

            if (is_array($t)) {
                // Rot-Markierung im Monats-PDF soll nur bei manuell geänderten Kommen/Gehen greifen
                // (nicht bei Tageskennzeichen wie Kurzarbeit/Urlaub/Krank etc.)
                $felderManuell = ((int)($t['felder_manuell_geaendert'] ?? 0) === 1);
                $pauseOverrideAktiv = ((int)($t['pause_override_aktiv'] ?? 0) === 1);

                $pauseF = $this->parseFloat((string)($t['pausen_stunden'] ?? '0.00'));
                $istF   = $this->parseFloat((string)($t['arbeitszeit_stunden'] ?? '0.00'));

                if ($pauseF > 0.0001) {
                    $pause = $this->formatDez2($pauseF);
                }
                if ($istF > 0.0001) {
                    $ist = $this->formatDez2($istF);
                }

                $arztF = $this->parseFloat((string)($t['arzt_stunden'] ?? '0.00'));
                $krankLfzF = $this->parseFloat((string)($t['krank_lfz_stunden'] ?? '0.00'));
                $krankKkF  = $this->parseFloat((string)($t['krank_kk_stunden'] ?? '0.00'));
                $feiertagF = $this->parseFloat((string)($t['feiertag_stunden'] ?? '0.00'));
                $kurzF     = $this->parseFloat((string)($t['kurzarbeit_stunden'] ?? '0.00'));
                $urlaubF   = $this->parseFloat((string)($t['urlaub_stunden'] ?? '0.00'));
                $sonstF    = $this->parseFloat((string)($t['sonstige_stunden'] ?? '0.00'));

                if ($arztF > 0.0001) {
                    $arzt = $this->formatDez2($arztF);
                }
                if ($krankKkF > 0.0001) {
                    $krankKk = $this->formatDez2($krankKkF);
                }
                if ($krankLfzF > 0.0001) {
                    $krankLfz = $this->formatDez2($krankLfzF);
                }
                if ($feiertagF > 0.0001) {
                    $feiertag = $this->formatDez2($feiertagF);
                }
                if ($kurzF > 0.0001) {
                    $kurz = $this->formatDez2($kurzF);
                }
                if ($urlaubF > 0.0001) {
                    $urlaub = $this->formatDez2($urlaubF);
                }
                if ($sonstF > 0.0001) {
                    $sonst = $this->formatDez2($sonstF);
                }

                // Summen (nur einmal pro Tag!)
                $sumIst += $istF;
                $sumArzt += $arztF;
                $sumKrankLfz += $krankLfzF;
                $sumKrankKk += $krankKkF;
                $sumFeiertag += $feiertagF;
                $sumKurzarbeit += $kurzF;
                $sumUrlaub += $urlaubF;
                $sumSonst += $sonstF;

                // Kürzel/Kommentar aus dem Tagesdatensatz (z.B. BF, SoU, SoU: Text)
                $kommentar = trim((string)($t['kommentar'] ?? ''));
                if ($kommentar !== '') {
                    $codeTeil = $kommentar;
                    $pos = strpos($kommentar, ':');
                    if ($pos !== false) {
                        $codeTeil = trim(substr($kommentar, 0, $pos));
                    }
                    if ($codeTeil === '') {
                        $codeTeil = $kommentar;
                    }

                    // In der Tabelle nur das Kürzel (Code) anzeigen, Begründungen unten als Bemerkungen ausgeben.
                    $flagNotiz = $this->trimMaxChars($codeTeil, 6);

                    // Bemerkungen sammeln (z. B. "SoU: <Text>")
                    if ($pos !== false || preg_match('/\s/', $kommentar) === 1) {
                        $bemerkungen[] = sprintf('%02d.%02d: %s', $day, $monat, $kommentar);
                    }
                } elseif (!empty($t['ist_betriebsferien'])) {
                    // Fallback: Betriebsferien nur dann als "BF" markieren, wenn sie auch tatsaechlich als Urlaub zaehlen.
                    // Hintergrund: Betriebsferien koennen Feiertage/Wochenenden schneiden oder es kann an einem BF-Tag
                    // gearbeitet worden sein. In diesen Faellen darf im PDF kein "BF" stehen, da es sonst wie "Urlaub"
                    // wirken wuerde.

                    $kennUrlaub = (int)($t['kennzeichen_urlaub'] ?? 0);

                    $urlaubStdF = 0.0;
                    $u = trim((string)($t['urlaub_stunden'] ?? '0'));
                    if ($u !== '') {
                        $u = str_replace(',', '.', $u);
                        $urlaubStdF = is_numeric($u) ? (float)$u : 0.0;
                    }

                    // Nur wenn Betriebsferien hier wirklich als Urlaub bewertet wurden (Kennzeichen oder Stunden), "BF" zeigen.
                    if ($kennUrlaub === 1 || $urlaubStdF > 0.00001) {
                        $flagNotiz = 'BF';
                    }
                }

                // Arbeitsblöcke (vom ReportService bereitgestellt)
                if (isset($t['arbeitsbloecke']) && is_array($t['arbeitsbloecke']) && $t['arbeitsbloecke'] !== []) {
                    $bloecke = $t['arbeitsbloecke'];
                } else {
                    // Fallback: eine Zeile aus den Tageswerten
                    $bloecke = [[
                        'kommen_roh'  => $t['kommen_roh'] ?? null,
                        'gehen_roh'   => $t['gehen_roh'] ?? null,
                        'kommen_korr' => $t['kommen_korr'] ?? null,
                        'gehen_korr'  => $t['gehen_korr'] ?? null,
                    ]];
                }
            } else {
                // Kein Tageswert: leere Zeile
                $bloecke = [[
                    'kommen_roh'  => null,
                    'gehen_roh'   => null,
                    'kommen_korr' => null,
                    'gehen_korr'  => null,
                ]];
            }

            if ($bloecke === []) {
                $bloecke = [[
                    'kommen_roh'  => null,
                    'gehen_roh'   => null,
                    'kommen_korr' => null,
                    'gehen_korr'  => null,
                ]];
            }

            // Mikro-Buchungen (<= 3 Minuten) sollen im PDF standardmäßig nicht als eigene Block-Zeilen auftauchen.
            // Sie bleiben weiterhin nicht gewertet, koennen aber per ?show_micro=1 sichtbar gemacht werden.
            if (!$showMicro) {
                $microIgnoriert = ((int)($t['micro_arbeitszeit_ignoriert'] ?? 0) === 1);

                if ($microIgnoriert) {
                    // Tageswert wurde bereits als Mikro-Arbeitszeit neutralisiert – Blocks aus Rohbuchungen nicht anzeigen.
                    $bloecke = [[
                        'kommen_roh'  => null,
                        'gehen_roh'   => null,
                        'kommen_korr' => null,
                        'gehen_korr'  => null,
                    ]];
                } else {
                    $filtered = [];
                    foreach ($bloecke as $blk) {
                        if (!is_array($blk)) {
                            continue;
                        }

                        if ($this->istMicroArbeitsblock($blk, $microMaxSeconds)) {
                            // Mikro-Block ausblenden
                            continue;
                        }

                        $filtered[] = $blk;
                    }

                    if ($filtered === []) {
                        $filtered = [[
                            'kommen_roh'  => null,
                            'gehen_roh'   => null,
                            'kommen_korr' => null,
                            'gehen_korr'  => null,
                        ]];
                    }

                    $bloecke = $filtered;
                }
            }

            // Standard: ohne show_micro zeigen wir alle NICHT-Mikro-Arbeitsbloecke als eigene Zeilen.
            // (Mehrfach-Kommen/Gehen bleibt sichtbar; Mikro-Bloecke wurden oben bereits gefiltert.)

            // Primaer-Zeile fuer Meta-Felder (Pause/Kurzarbeit/Feiertag/Urlaub):
            // Erste sichtbare Blockzeile mit Dauer >= 60 Minuten, sonst die erste Blockzeile.
            $metaPrimaryMinSeconds = 3600;
            $metaPrimaryIndex = 0;
            foreach ($bloecke as $iMeta => $bMeta) {
                if (!is_array($bMeta)) {
                    continue;
                }
                if ($this->calcBlockSeconds($bMeta) >= $metaPrimaryMinSeconds) {
                    $metaPrimaryIndex = (int)$iMeta;
                    break;
                }
            }

            foreach ($bloecke as $idx => $b) {
                $istErsteZeile = ($idx === 0);
                $istMetaZeile = ($idx === $metaPrimaryIndex);

                $kommenRoh = $this->formatUhrzeit((string)($b['kommen_roh'] ?? ''));
                $gehenRoh  = $this->formatUhrzeit((string)($b['gehen_roh'] ?? ''));
                $kommenKor = $this->formatUhrzeit((string)($b['kommen_korr'] ?? ''));
                $gehenKor  = $this->formatUhrzeit((string)($b['gehen_korr'] ?? ''));

                $istNachtshiftBlock = ((int)($b['nachtshift'] ?? 0) === 1);
                if ($istNachtshiftBlock && $kommenRoh === '' && $gehenRoh !== '') {
                    $kommenRoh = '00:00';
                }
                if ($istNachtshiftBlock && $gehenRoh === '' && $kommenRoh !== '') {
                    $gehenRoh = '00:00';
                }

                $rows[] = [
                    $istErsteZeile ? $tagKw : '',
                    $istErsteZeile ? $flagNotiz : '',
                    $kommenRoh,
                    $gehenRoh,
                    $kommenKor,
                    $istMetaZeile ? $pause : '',
                    $gehenKor,
                    $this->calcBlockIstDez2($b),
                    $istErsteZeile ? $arzt : '',
                    $istErsteZeile ? $krankKk : '',
                    $istErsteZeile ? $krankLfz : '',
                    $istMetaZeile ? $feiertag : '',
                    $istMetaZeile ? $kurz : '',
                    $istMetaZeile ? $urlaub : '',
                    $istErsteZeile ? $sonst : '',
                ];

                $istKommenManuell = ((int)($b['kommen_manuell_geaendert'] ?? 0) === 1);
                $istGehenManuell = ((int)($b['gehen_manuell_geaendert'] ?? 0) === 1);
                $zellenManuell = array_fill(0, 15, false);
                if ($istKommenManuell) {
                    if ($kommenRoh !== '') {
                        $zellenManuell[2] = true; // An
                    }
                    if ($kommenKor !== '') {
                        $zellenManuell[4] = true; // An.Korr
                    }
                }
                if ($istGehenManuell) {
                    if ($gehenRoh !== '') {
                        $zellenManuell[3] = true; // Ab
                    }
                    if ($gehenKor !== '') {
                        $zellenManuell[6] = true; // Ab.Korr
                    }
                }

                $pauseZelle = $istMetaZeile ? $pause : '';
                if ($pauseOverrideAktiv && $pauseZelle !== '') {
                    $zellenManuell[5] = true;
                }

                if ($felderManuell && $istErsteZeile) {
                    if ($arzt !== '') {
                        $zellenManuell[8] = true;
                    }
                    if ($krankKk !== '') {
                        $zellenManuell[9] = true;
                    }
                    if ($krankLfz !== '') {
                        $zellenManuell[10] = true;
                    }
                    if ($sonst !== '') {
                        $zellenManuell[14] = true;
                    }
                }

                if ($felderManuell && $istMetaZeile) {
                    if ($feiertag !== '') {
                        $zellenManuell[11] = true;
                    }
                    if ($kurz !== '') {
                        $zellenManuell[12] = true;
                    }
                    if ($urlaub !== '') {
                        $zellenManuell[13] = true;
                    }
                }

                $zellMarkierungen[] = $zellenManuell;
            }
        }

        // Abschluss-Zeile wie in der Vorlage ("/")
        $rows[] = ['/', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $zellMarkierungen[] = array_fill(0, 15, false);

        // Sollstunden
        $soll = 0.0;
        if (is_array($monatswerte)) {
            $soll = $this->parseFloat((string)($monatswerte['sollstunden'] ?? '0.00'));
        }

        $sumAll = $sumIst + $sumArzt + $sumKrankLfz + $sumKrankKk + $sumUrlaub + $sumKurzarbeit + $sumFeiertag + $sumSonst;
        $diff   = $sumAll - $soll;

        // Stundenkonto (Gut-/Minusstunden) als externer Saldo bis Ende Vormonat.
        // Wichtig: Dieser Wert soll die Stunden des aktuellen Monatsreports NICHT enthalten.
        $saldoBisVormonatMinuten = $this->stundenkontoService->holeSaldoMinutenBisVormonat($mitarbeiterId, $jahr, $monat);
        $saldoBisVormonatStunden = $this->stundenkontoService->formatMinutenAlsStundenString((int)$saldoBisVormonatMinuten, true);

        // Urlaubstage (abzgl. BF) + Betriebsferien (Rest Jahr).
        // Hinweis: UrlaubService berechnet den Jahres-Resturlaub bereits inkl. Betriebsferien (Zwangsurlaub).
        // Daher ist `verbleibend` hier bereits "Urlaubtage (abzgl. BF)".
        $urlaubAbzglBfText = '';
        $bfRestArbeitstageText = '';
        if (is_array($monatswerte)) {
            $urlaubVerbleibendTage = 0.0;
            try {
                $saldo = $this->urlaubService->berechneUrlaubssaldoFuerJahr((int)$mitarbeiterId, (int)$jahr);
                if (is_array($saldo) && isset($saldo['verbleibend'])) {
                    $uv = trim((string)$saldo['verbleibend']);
                    $uv = str_replace(',', '.', $uv);
                    $urlaubVerbleibendTage = is_numeric($uv) ? (float)$uv : 0.0;
                }
            } catch (\Throwable $e) {
                $urlaubVerbleibendTage = 0.0;
            }

            $bfRestArbeitstage = 0;
            try {
                $startRest = $bis->modify('+1 day');
                $endeRest  = new \DateTimeImmutable(sprintf('%04d-12-31', (int)$jahr));

                if ($startRest <= $endeRest) {
                    $bfRestArbeitstage = $this->urlaubService->zaehleBetriebsferienArbeitstageFuerMitarbeiter(
                        (int)$mitarbeiterId,
                        $startRest->format('Y-m-d'),
                        $endeRest->format('Y-m-d')
                    );
                }
            } catch (\Throwable $e) {
                $bfRestArbeitstage = 0;
            }

            // Hinweis:
            // Negative Urlaubstage sind erlaubt (z. B. wenn mehr Urlaub genommen wurde als verfügbar).
            // Der automatische Übertrag ins Folgejahr wird weiterhin defensiv gehandhabt (nur positive
            // Resttage werden übertragen).

            $urlaubAbzglBfText = $this->formatDez2($urlaubVerbleibendTage);
            $bfRestArbeitstageText = (string)$bfRestArbeitstage;
        }

        $sumLines = [
            ['Sollstunden', $this->formatDez2($soll)],
            ['Iststunden', $this->formatDez2($sumIst)],
            ['Arzt (Std):', $this->formatDez2($sumArzt)],
            ['Krankstunden LFZ', $this->formatDez2($sumKrankLfz)],
            ['Krankstunden KK', $this->formatDez2($sumKrankKk)],
            ['Urlaubsstunden', $this->formatDez2($sumUrlaub)],
            ['Kurzarbeitsstunden', $this->formatDez2($sumKurzarbeit)],
            ['Feiertagsstunden', $this->formatDez2($sumFeiertag)],
            ['Sonst.', $this->formatDez2($sumSonst)],
            ['Summen:', $this->formatDez2($sumAll)],
            ['Über- bzw. Minusstunden', $this->formatDez2($diff)],
            ['Stundenkonto (bis Vormonat)', $saldoBisVormonatStunden],
        ];

        // Auto-Compact (T-069): Wenn es durch wenige Mehrfach-Kommen/Gehen-Bloecke knapp auf 2 Seiten rutscht,
        // versuchen wir zuerst die Zeilenhoehe minimal zu reduzieren. Falls das nicht reicht, kann (nur im 2-Seiten-Grenzfall)
        // der Summen-/Bemerkungsbereich minimal nach unten geschoben werden, um eine zusätzliche Tabellenzeile auf 1 Seite zu gewinnen.
        // Lesbarkeit bleibt dabei erhalten (kein aggressives Shrinking fuer echte Mehrseiten-Monate).
        $bottomLimitY = 210.0; // Standard: Reserve fuer Summen/Bemerkungen
        $sumGapY      = 20.0;  // Abstand zwischen Tabellenende und Summenblock-Start (wie in der Vorlage-Annäherung)

        $calcSeiten = function (float $testRowH, float $testBottomLimitY) use ($tableTopY, $rows): int {
            $maxRowsInclHeader = (int)floor(($tableTopY - $testBottomLimitY) / $testRowH);
            if ($maxRowsInclHeader < 6) {
                $maxRowsInclHeader = 6;
            }
            $dataRowsPerPage = $maxRowsInclHeader - 1;
            if ($dataRowsPerPage < 1) {
                $dataRowsPerPage = 1;
            }
            $dataRowsCount = max(count($rows) - 1, 0);
            $pages = (int)ceil($dataRowsCount / $dataRowsPerPage);
            if ($pages < 1) {
                $pages = 1;
            }
            return $pages;
        };

        $seitenDefault = $calcSeiten($rowH, $bottomLimitY);

        // Schritt 1: Zeilenhoehe minimal reduzieren (nur wenn Default 2 Seiten ergibt)
        if ($seitenDefault === 2) {
            foreach ([14.5, 14.0] as $candidateRowH) {
                if ($calcSeiten((float)$candidateRowH, $bottomLimitY) === 1) {
                    $rowH = (float)$candidateRowH;
                    $seitenDefault = 1;
                    break;
                }
            }
        }

        // Schritt 2: Nur falls weiterhin 2 Seiten: Reserve unten minimal verkleinern (Summen/Bemerkungen weiter nach unten),
        // um eine Tabellenzeile mehr auf 1 Seite zu bekommen. Keine Wirkung bei echten Mehrseiten-Monaten.
        if ($seitenDefault === 2) {
            foreach ([195.0, 185.0, 180.0] as $candidateBottomLimitY) {
                if ($calcSeiten($rowH, (float)$candidateBottomLimitY) === 1) {
                    $bottomLimitY = (float)$candidateBottomLimitY;
                    $seitenDefault = 1;
                    break;
                }
            }
        }

        // Summen-/Bemerkungsblock-Start passend zur Reserve berechnen (wird unten im Rendern genutzt)
        // Summenblock leicht nach links verschieben, damit rechts Platz fuer Zusatzblock bleibt.
        // Links bleibt weiterhin Platz fuer Bemerkungen.
        $sumLabelX = 190.0;
        $sumValueX = 385.0;
        $sumStartY = $bottomLimitY - $sumGapY;
        $sumLineH  = 14.0;

        // Zusatzblock rechts (Urlaub/Betriebsferien)
        $rightBlockLabelX = 400.0;
        $rightBlockValueX = $rightX;

        // Unterer Bereich: Summenblock + Zusatzblock visuell mittig platzieren.
        // Wir zentrieren die beiden Bloecke als gemeinsame Gruppe zwischen tableRightX und rightX,
        // allerdings nur, wenn der rechte Zusatzblock auch sichtbar ist.
        if ($urlaubAbzglBfText !== '') {
            $sumGroupLeft = $sumLabelX;
            $sumGroupRight = $sumValueX + 5.0;
            $rightGroupLeft = $rightBlockLabelX;
            $rightGroupRight = $rightBlockValueX + 5.0;

            $groupLeft = min($sumGroupLeft, $rightGroupLeft);
            $groupRight = max($sumGroupRight, $rightGroupRight);
            $groupWidth = $groupRight - $groupLeft;
            $availableWidth = $rightX - $tableRightX;
            $groupOffset = ($availableWidth > $groupWidth) ? (($availableWidth - $groupWidth) / 2.0) : 0.0;

            if ($groupOffset > 0.1) {
                $sumLabelX += $groupOffset;
                $sumValueX += $groupOffset;
                $rightBlockLabelX += $groupOffset;
                $rightBlockValueX += $groupOffset;
            }
        }

        // Seitenaufteilung: Tabelle kann durch Mehrfach-Kommen/Gehen deutlich mehr Zeilen haben.
        // Wir reservieren unten Platz für Summen/Bemerkungen und teilen in mehrere Seiten auf.
        // Platz-Reserve unten: so gewählt, dass ein "normaler" Monat (31 Tage + Abschluss "/")
        // inkl. Summenblock in der Regel auf eine Seite passt.
        $maxRowsInclHeader = (int)floor(($tableTopY - $bottomLimitY) / $rowH);
        if ($maxRowsInclHeader < 6) {
            $maxRowsInclHeader = 6;
        }
        $dataRowsPerPage = $maxRowsInclHeader - 1;

        $dataRows = array_slice($rows, 1);
        $dataManuell = array_slice($zellMarkierungen, 1);

        $chunks = array_chunk($dataRows, $dataRowsPerPage);
        $chunksManuell = array_chunk($dataManuell, $dataRowsPerPage);

        if ($chunks === []) {
            $chunks = [[]];
            $chunksManuell = [[]];
        }

        $seitenGesamt = count($chunks);
        $seitenContent = [];

        for ($p = 0; $p < $seitenGesamt; $p++) {
            $pageRows = array_merge([$rows[0]], $chunks[$p]);
            $pageManuell = array_merge([array_fill(0, 15, false)], $chunksManuell[$p] ?? []);

            $istLetzteSeite = ($p === ($seitenGesamt - 1));

            $rowCount = count($pageRows);
            $tableBottomY = $tableTopY - ($rowCount * $rowH);

            // --- PDF Content Stream ---
            $c = "q\n";
            $c .= "0 0 0 RG\n0 0 0 rg\n0.6 w\n";

            // Hintergrund: Manuell geänderte Zellen rot hinterlegen
            // (muss vor Gitterlinien erfolgen, damit Linien darüber sichtbar bleiben)
            $rects = '';
            for ($r = 1; $r < $rowCount; $r++) {
                $markierungen = $pageManuell[$r] ?? [];
                if (!is_array($markierungen) || $markierungen === []) {
                    continue;
                }
                $yBottom = $tableTopY - (($r + 1) * $rowH);
                for ($ci = 0; $ci < 15; $ci++) {
                    if (!($markierungen[$ci] ?? false)) {
                        continue;
                    }
                    $xLeft = (float)$colX[$ci];
                    $xRight = (float)$colX[$ci + 1];
                    $width = $xRight - $xLeft;
                    $rects .= $this->pdfRectFill($xLeft, $yBottom, $width, $rowH);
                }
            }
            if ($rects !== '') {
                // Helles Rot
                $c .= "1 0.85 0.85 rg\n";
                $c .= $rects;
                // Farben für nachfolgende Linien/Text wieder zurücksetzen
                $c .= "0 0 0 rg\n0 0 0 RG\n";
            }

            // Kopf-Linie
            // Wichtig: In PHP werden Escape-Sequenzen in *einfachen* Quotes nicht interpretiert.
            // PDF-Streams brauchen aber echte Newlines/Whitespace. Daher hier doppelte Quotes.
            $c .= $this->pdfLine($marginL, $topLineY, $rightX, $topLineY);

            // Tabelle: Gitter
            // horizontale Linien
            for ($r = 0; $r <= $rowCount; $r++) {
                $y = $tableTopY - ($r * $rowH);
                $c .= $this->pdfLine($tableX, $y, $tableRightX, $y);
            }
            // vertikale Linien
            $tableTop = $tableTopY;
            $tableBot = $tableBottomY;
            for ($ci = 0; $ci < count($colX); $ci++) {
                $x = (float)$colX[$ci];
                $c .= $this->pdfLine($x, $tableTop, $x, $tableBot);
            }

            // Texte
            $c .= "BT\n";

            // Titel
            $c .= $this->pdfTextCmd('F2', 16.0, $centerX, $titleY, 'Arbeitszeitliste', 'center');

            // Infozeile (links / mitte / rechts)
            $mitarbeiterLine = sprintf('%04d %s', $mitarbeiterId, $mitarbeiterName);
            $c .= $this->pdfTextCmd('F1', 10.0, $marginL, $infoY, $mitarbeiterLine, 'left');
            $c .= $this->pdfTextCmd('F1', 10.0, $centerX, $infoY, $zeitraumText, 'center');
            $c .= $this->pdfTextCmd('F1', 10.0, $rightX, $infoY, $druckDatum, 'right');

            $c .= $this->pdfTextCmd('F2', 10.0, $marginL, $origY, 'Originaldaten !', 'left');
            $c .= $this->pdfTextCmd('F1', 9.0, $rightX, $origY, 'Seite ' . ($p + 1) . '/' . $seitenGesamt, 'right');

            // Tabellen-Header und Daten
            $fontHeader = 7.5;
            $fontData   = 8.0;
            $padX       = 2.0;

            for ($r = 0; $r < $rowCount; $r++) {
                $isHeader = ($r === 0);
                // Text-Baseline innerhalb der Zeile (leicht nach oben versetzt).
                // Dynamisch mit der Zeilenhoehe, damit Auto-Compact (kleinere rowH) sauber zentriert bleibt.
                $yBase = $tableTopY - (($r + 1) * $rowH) + ($rowH * 0.30);

                for ($ci = 0; $ci < 15; $ci++) {
                    $cellText = (string)($pageRows[$r][$ci] ?? '');
                    if ($cellText === '') {
                        continue;
                    }

                    $xLeft  = (float)$colX[$ci];
                    $width  = (float)$colW[$ci];

                    if ($isHeader) {
                        $c .= $this->pdfTextCmd('F1', $fontHeader, $xLeft, $yBase, $cellText, 'center-box', $width);
                    } else {
                        // 1. Spalte linksbündig, Rest zentriert (wie Vorlage)
                        if ($ci === 0) {
                            $c .= $this->pdfTextCmd('F1', $fontData, $xLeft + $padX, $yBase, $cellText, 'left');
                        } elseif ($ci === 1) {
                            $c .= $this->pdfTextCmd('F1', $fontData, $xLeft, $yBase, $cellText, 'center-box', $width);
                        } else {
                            $c .= $this->pdfTextCmd('F1', $fontData, $xLeft, $yBase, $cellText, 'center-box', $width);
                        }
                    }
                }
            }

            // Summenblock unten (Positionen) – Werte werden oberhalb dynamisch ermittelt (Auto-Compact).

            if ($istLetzteSeite) {
                // Bemerkungen (Kürzel/Begründung aus tageswerte_mitarbeiter.kommentar)
                if ($bemerkungen !== []) {
                    $notesX = $marginL;
                    $notesY = $sumStartY + 18.0;

                    $c .= $this->pdfTextCmd('F2', 9.0, $notesX, $notesY, 'Bemerkungen:', 'left');
                    $notesY -= 11.0;

                    $lineH = 9.0;
                    $maxLines = 14;
                    $printed = 0;

                    foreach ($bemerkungen as $bem) {
                        $lines = $this->wrapText($bem, 46);
                        foreach ($lines as $ln) {
                            if ($printed >= $maxLines) {
                                $c .= $this->pdfTextCmd('F1', 7.5, $notesX, $notesY, '...', 'left');
                                $notesY -= $lineH;
                                $printed = $maxLines;
                                break 2;
                            }
                            $c .= $this->pdfTextCmd('F1', 7.5, $notesX, $notesY, $ln, 'left');
                            $notesY -= $lineH;
                            $printed++;
                        }
                    }
                }

                // Summenblock unten
                for ($i = 0; $i < count($sumLines); $i++) {
                    $y = $sumStartY - ($i * $sumLineH);
                    $label = (string)$sumLines[$i][0];
                    $value = (string)$sumLines[$i][1];

                    $font = ($i === 0 || $i === 1 || $i === 9 || $i === 10) ? 'F2' : 'F1';
                    $size = 10.0;

                    $c .= $this->pdfTextCmd($font, $size, $sumLabelX, $y, $label, 'left');
                    $c .= $this->pdfTextCmd($font, $size, $sumValueX, $y, $value, 'right');

                    // Unterstreichung im Wertebereich (optisch wie Vorlage)
                    $lineY = $y - 2.5;
                    $c .= "ET\n"; // Linien außerhalb von BT
                    $c .= $this->pdfLine($sumValueX - 70.0, $lineY, $sumValueX + 5.0, $lineY);
                    $c .= "BT\n";
                }

                // Zusatzblock rechts: Urlaubstage (abzgl. geplante Betriebsferien)
                if ($urlaubAbzglBfText !== '') {
                    $rightLines = [
                        ['Urlaubtage (abzgl. BF)', $urlaubAbzglBfText],
                        ['BF (Rest Jahr)', $bfRestArbeitstageText],
                    ];

                    for ($j = 0; $j < count($rightLines); $j++) {
                        $y = $sumStartY - ($j * $sumLineH);
                        $label = (string)$rightLines[$j][0];
                        $value = (string)$rightLines[$j][1];

                        $c .= $this->pdfTextCmd('F1', 10.0, $rightBlockLabelX, $y, $label, 'left');
                        $c .= $this->pdfTextCmd('F1', 10.0, $rightBlockValueX, $y, $value, 'right');

                        // Unterstreichung im Wertebereich
                        $lineY = $y - 2.5;
                        $c .= "ET\n";
                        $c .= $this->pdfLine($rightBlockValueX - 70.0, $lineY, $rightBlockValueX + 5.0, $lineY);
                        $c .= "BT\n";
                    }
                }
            }

            $c .= "ET\nQ\n";
            $seitenContent[] = $c;
        }

        return $this->baueMinimalPdfMitSeiten($seitenContent);
    }

    /**
     * Minimal-PDF (mehrseitig), mit Standard-Fonts (Helvetica + Bold).
     *
     * @param array<int,string> $seitenContentStreams
     */
    private function baueMinimalPdfMitSeiten(array $seitenContentStreams): string
    {
        $seitenContentStreams = array_values($seitenContentStreams);
        if ($seitenContentStreams === []) {
            $seitenContentStreams = ["BT /F1 12 Tf 50 800 Td (keine Daten) Tj ET\n"]; 
        }

        $anzahlSeiten = count($seitenContentStreams);

        $objects = [];

        // 1: Catalog
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // 3: Font Helvetica
        $objects[3] = "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // 4: Font Helvetica-Bold
        $objects[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $kids = [];

        for ($i = 0; $i < $anzahlSeiten; $i++) {
            $pageObjId    = 5 + ($i * 2);
            $contentObjId = $pageObjId + 1;

            $kids[] = $pageObjId . ' 0 R';

            $content = (string)$seitenContentStreams[$i];
            if ($content !== '' && substr($content, -1) !== "\n") {
                $content .= "\n";
            }
            $len = strlen($content);

            $objects[$contentObjId] = $contentObjId . " 0 obj\n<< /Length " . $len . " >>\nstream\n" . $content . "endstream\nendobj\n";

            $objects[$pageObjId] = $pageObjId . " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /ProcSet [/PDF /Text] /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . $contentObjId . " 0 R >>\nendobj\n";
        }

        // 2: Pages
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . $anzahlSeiten . " >>\nendobj\n";

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        $maxId = (int)max(array_keys($objects));
        for ($id = 1; $id <= $maxId; $id++) {
            if (!isset($objects[$id])) {
                continue;
            }
            $offsets[$id] = strlen($pdf);
            $pdf .= $objects[$id];
        }

        $xrefPos = strlen($pdf);

        $pdf .= "xref\n";
        $pdf .= "0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            if (!isset($objects[$id])) {
                $pdf .= "0000000000 00000 f \n";
                continue;
            }
            $pdf .= sprintf('%010d 00000 n ' . "\n", $offsets[$id]);
        }

        $pdf .= "trailer\n";
        $pdf .= "<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefPos . "\n";
        $pdf .= "%%EOF";

        return $pdf;
    }

    /**
     * Baut einen Text-Command für den Content-Stream (innerhalb eines BT/ET Blocks).
     *
     * Align:
     * - left: x ist Start
     * - right: x ist rechter Rand
     * - center: x ist Mitte
     * - center-box: x ist linke Kante, boxWidth gibt die Boxbreite an
     */
    private function pdfTextCmd(string $fontRef, float $size, float $x, float $y, string $text, string $align = 'left', ?float $boxWidth = null): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $w = $this->schaetzeTextBreitePt($text, $size);

        $xStart = $x;
        if ($align === 'right') {
            $xStart = $x - $w;
        } elseif ($align === 'center') {
            $xStart = $x - ($w / 2.0);
        } elseif ($align === 'center-box' && $boxWidth !== null) {
            $xStart = $x + (($boxWidth - $w) / 2.0);
        }

        if ($xStart < 0) {
            $xStart = 0;
        }

        $escaped = $this->escapePdfText($text);

        // WICHTIG: PDF-Numbers müssen mit Punkt als Dezimaltrenner ausgegeben werden.
        // sprintf/printf kann (selten, je nach Locale) Komma liefern, was PDF-Streams zerstört.
        return '/' . $fontRef . ' ' . $this->pdfNum($size) . " Tf 1 0 0 1 "
            . $this->pdfNum((float)$xStart) . ' '
            . $this->pdfNum((float)$y)
            . ' Tm (' . $escaped . ") Tj\n";
    }

    /**
     * Formatiert eine Zahl für PDF-Content-Streams (immer Dezimalpunkt, kein Tausendertrennzeichen).
     */
    private function pdfNum(float $v): string
    {
        if (!is_finite($v)) {
            return '0';
        }

        // number_format ist locale-unabhängig, wenn Dezimalpunkt explizit gesetzt wird.
        $s = number_format($v, 2, '.', '');
        $s = rtrim($s, '0');
        $s = rtrim($s, '.');
        return $s === '' ? '0' : $s;
    }

    /**
     * Linie zeichnen (Move-To + Line-To + Stroke).
     */
    private function pdfLine(float $x1, float $y1, float $x2, float $y2): string
    {
        return $this->pdfNum($x1) . ' ' . $this->pdfNum($y1) . ' m '
            . $this->pdfNum($x2) . ' ' . $this->pdfNum($y2) . " l S\n";
    }

    /**
     * Gefülltes Rechteck (für Hinterlegung von Tabellenzeilen).
     */
    private function pdfRectFill(float $x, float $y, float $w, float $h): string
    {
        if ($w <= 0 || $h <= 0) {
            return '';
        }
        return $this->pdfNum($x) . ' ' . $this->pdfNum($y) . ' ' . $this->pdfNum($w) . ' ' . $this->pdfNum($h) . " re f\n";
    }

    private function schaetzeTextBreitePt(string $text, float $size): float
    {
        // Sehr grobe Schätzung für Standard-Type1-Fonts.
        // Reicht für optisches Zentrieren/Right-Align (Vorlagen-Annäherung).
        $len = $this->mbLen($text);
        return $len * $size * 0.50;
    }

    private function mbLen(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($text, 'UTF-8');
        }
        return strlen($text);
    }

    private function trimMaxChars(string $text, int $maxChars): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if ($maxChars <= 0) {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($text, 'UTF-8') > $maxChars) {
                return (string)mb_substr($text, 0, $maxChars, 'UTF-8');
            }
            return $text;
        }

        if (strlen($text) > $maxChars) {
            return substr($text, 0, $maxChars);
        }

        return $text;
    }



    /**
     * Sehr einfache Wort-Wrapping-Hilfe (für Bemerkungen unten im PDF).
     *
     * @return array<int,string>
     */
    private function wrapText(string $text, int $maxChars): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if ($maxChars <= 0) {
            return [$text];
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $cur = '';

        foreach ($words as $w) {
            $w = (string)$w;
            if ($w === '') {
                continue;
            }

            if ($cur === '') {
                if ($this->mbLen($w) > $maxChars) {
                    $lines[] = $this->trimMaxChars($w, $maxChars);
                } else {
                    $cur = $w;
                }
                continue;
            }

            $candidate = $cur . ' ' . $w;
            if ($this->mbLen($candidate) > $maxChars) {
                $lines[] = $cur;
                if ($this->mbLen($w) > $maxChars) {
                    $lines[] = $this->trimMaxChars($w, $maxChars);
                    $cur = '';
                } else {
                    $cur = $w;
                }
            } else {
                $cur = $candidate;
            }
        }

        if ($cur !== '') {
            $lines[] = $cur;
        }

        if ($lines === []) {
            $lines = [$text];
        }

        return $lines;
    }

    private function escapePdfText(string $text): string
    {
        // PDF Standard-Fonts arbeiten am zuverlässigsten mit Windows-1252.
        $converted = $text;
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            if ($tmp !== false && $tmp !== '') {
                $converted = $tmp;
            }
        }

        $converted = str_replace("\r", '', $converted);
        $converted = str_replace("\n", '', $converted);
        $converted = str_replace('\\', '\\\\', $converted);
        $converted = str_replace('(', '\\(', $converted);
        $converted = str_replace(')', '\\)', $converted);

        // Steuerzeichen entfernen
        $converted = preg_replace('/[\x00-\x1F\x7F]/', '', $converted) ?? $converted;

        return $converted;
    }

    private function wochentagKurzDe(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($ymd);
            $n  = (int)$dt->format('N');
        } catch (\Throwable $e) {
            return '';
        }

        $map = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        return $map[$n] ?? '';
    }

    private function istShowMicroRequest(): bool
    {
        return (isset($_GET['show_micro']) && ((int)$_GET['show_micro'] === 1));
    }

    /**
     * Liest die Mikro-Buchungs-Grenze (Sekunden) aus der DB-Konfiguration.
     * Default: 180 (3 Minuten). Range-Guard: 30..3600.
     */
    private function holeMicroBuchungMaxSeconds(): int
    {
        $max = 180;

        try {
            if (class_exists('KonfigurationService')) {
                $cfg = KonfigurationService::getInstanz();
                $val = $cfg->getInt('micro_buchung_max_sekunden', 180);
                if ($val !== null) {
                    $max = (int)$val;
                }
            }
        } catch (\Throwable $e) {
            $max = 180;
        }

        if ($max < 30 || $max > 3600) {
            $max = 180;
        }

        return $max;
    }

    /**
     * Mikro-Block-Erkennung analog zur Monatsuebersicht:
     * 1) Roh-Paar diffen (wenn vorhanden) und mit abs() bewerten.
     * 2) Fallback: Main-Paar (korr bevorzugt, sonst roh) und ebenfalls abs().
     */
    private function istMicroArbeitsblock(array $block, int $maxSeconds): bool
    {
        $kRaw = isset($block['kommen_roh']) ? trim((string)$block['kommen_roh']) : '';
        $gRaw = isset($block['gehen_roh']) ? trim((string)$block['gehen_roh']) : '';
        $kK   = isset($block['kommen_korr']) ? trim((string)$block['kommen_korr']) : '';
        $gK   = isset($block['gehen_korr']) ? trim((string)$block['gehen_korr']) : '';

        // Sonderfall: Rundungsregeln koennen kurze Bloecke „umdrehen“/neutralisieren
        // (gehen_korr <= kommen_korr). Diese Bloecke ergeben effektiv 0 Arbeitszeit
        // und sollen im Report standardmaessig nicht als eigene Block-Zeilen auftauchen.
        if ($kK !== '' && $gK !== '' && substr($kK, 0, 10) !== '0000-00-00' && substr($gK, 0, 10) !== '0000-00-00') {
            $kDt = $this->parseDtBerlin($kK);
            $gDt = $this->parseDtBerlin($gK);
            if ($kDt !== null && $gDt !== null && $gDt->getTimestamp() <= $kDt->getTimestamp()) {
                return true;
            }
        }

        if ($maxSeconds <= 0) {
            return false;
        }

        // 1) Roh-Paar vorhanden -> Roh-Diff pruefen
        if ($kRaw !== '' && $gRaw !== '') {
            $kDt = $this->parseDtBerlin($kRaw);
            $gDt = $this->parseDtBerlin($gRaw);
            if ($kDt !== null && $gDt !== null) {
                $diff = abs($gDt->getTimestamp() - $kDt->getTimestamp());
                if ($diff <= $maxSeconds) {
                    return true;
                }
            }
        }

        // 2) Fallback: Main-Paar (korr bevorzugt, sonst roh)
        $kMain = ($kK !== '') ? $kK : $kRaw;
        $gMain = ($gK !== '') ? $gK : $gRaw;

        if ($kMain === '' || $gMain === '') {
            return false;
        }

        $kDt = $this->parseDtBerlin($kMain);
        $gDt = $this->parseDtBerlin($gMain);
        if ($kDt === null || $gDt === null) {
            return false;
        }

        $diff = abs($gDt->getTimestamp() - $kDt->getTimestamp());
        return ($diff <= $maxSeconds);
    }


    
    /**
     * Block-IST (Dezimalstunden) fuer die Anzeige in Folgezeilen.
     *
     * WICHTIG: Nur UI-Anzeige. Summen bleiben unveraendert aus den Tageswerten.
     * Logik analog zur Monatsübersicht:
     * - bevorzugt korrigiertes Paar (kommen_korr/gehen_korr)
     * - sonst Roh-Paar
     * - sonst Main-Paar (korr bevorzugt, sonst roh)
     * - bei Rundung->0 (gehen_korr <= kommen_korr) wird 0.00 geliefert
     * - sonst diff = ende-start (kein abs)
     */
    private function calcBlockIstDez2(array $block): string
    {
        $kRaw = isset($block['kommen_roh']) ? trim((string)($block['kommen_roh'])) : '';
        $gRaw = isset($block['gehen_roh']) ? trim((string)($block['gehen_roh'])) : '';
        $kK   = isset($block['kommen_korr']) ? trim((string)($block['kommen_korr'])) : '';
        $gK   = isset($block['gehen_korr']) ? trim((string)($block['gehen_korr'])) : '';

        // Rundung->0 (korr Paar dreht/egalisiert)
        if ($kK !== '' && $gK !== '' && substr($kK, 0, 10) !== '0000-00-00' && substr($gK, 0, 10) !== '0000-00-00') {
            $kDt = $this->parseDtBerlin($kK);
            $gDt = $this->parseDtBerlin($gK);
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

        $kDt = $this->parseDtBerlin($start);
        $gDt = $this->parseDtBerlin($ende);
        if ($kDt === null || $gDt === null) {
            return '';
        }

        $diff = $gDt->getTimestamp() - $kDt->getTimestamp();
        if ($diff <= 0) {
            return '0.00';
        }

        $hours = $diff / 3600.0;
        return $this->formatDez2((float)$hours);
    }


    /**
     * Block-Dauer in Sekunden (wie calcBlockIstDez2, aber als int).
     * Wird genutzt, um bei Mehrfach-Bloecken eine Primaer-Zeile fuer Meta-Felder zu bestimmen.
     */
    private function calcBlockSeconds(array $block): int
    {
        $kRaw = isset($block['kommen_roh']) ? trim((string)($block['kommen_roh'])) : '';
        $gRaw = isset($block['gehen_roh']) ? trim((string)($block['gehen_roh'])) : '';
        $kK   = isset($block['kommen_korr']) ? trim((string)($block['kommen_korr'])) : '';
        $gK   = isset($block['gehen_korr']) ? trim((string)($block['gehen_korr'])) : '';

        // Rundung->0
        if ($kK !== '' && $gK !== '' && substr($kK, 0, 10) !== '0000-00-00' && substr($gK, 0, 10) !== '0000-00-00') {
            $kDt = $this->parseDtBerlin($kK);
            $gDt = $this->parseDtBerlin($gK);
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

        if ($start === '' || $ende === '') {
            return 0;
        }

        $kDt = $this->parseDtBerlin($start);
        $gDt = $this->parseDtBerlin($ende);
        if ($kDt === null || $gDt === null) {
            return 0;
        }

        $diff = $gDt->getTimestamp() - $kDt->getTimestamp();
        return ($diff > 0) ? (int)$diff : 0;
    }


    private function parseDtBerlin(?string $datetime): ?\DateTimeImmutable
    {
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


    private function blockDauerSekunden(?string $start, ?string $ende): ?int
    {
        $start = trim((string)$start);
        $ende  = trim((string)$ende);
        if ($start === '' || $ende === '') {
            return null;
        }

        try {
            $a = new \DateTimeImmutable($start);
            $b = new \DateTimeImmutable($ende);
        } catch (\Throwable $e) {
            return null;
        }

        $sec = $b->getTimestamp() - $a->getTimestamp();
        if ($sec < 0) {
            return null;
        }
        return (int)$sec;
    }

    private function formatUhrzeit(string $datetime): string
    {
        $s = trim($datetime);
        if ($s === '') {
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($s);
            return $dt->format('H:i');
        } catch (\Throwable $e) {
            if (preg_match('/^\d{2}:\d{2}$/', $s) === 1) {
                return $s;
            }
            return '';
        }
    }

    private function parseFloat(string $s): float
    {
        $s = trim($s);
        if ($s === '') {
            return 0.0;
        }
        $s = str_replace(',', '.', $s);
        return (float)$s;
    }

    private function formatDez2(float $v): string
    {
        return number_format($v, 2, ',', '');
    }
}
