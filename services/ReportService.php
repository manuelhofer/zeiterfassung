<?php
declare(strict_types=1);

/**
 * ReportService
 *
 * Stellt Auswertungsfunktionen für Monatsberichte bereit.
 *
 * Diese erste Version holt:
 * - den Monats-Gesamtdatensatz aus `monatswerte_mitarbeiter`
 * - alle Tagesdatensätze aus `tageswerte_mitarbeiter` für den Monat
 * und bereitet sie für die Anzeige im View auf.
 */
class ReportService
{
    private static ?ReportService $instanz = null;

    private MonatswerteMitarbeiterModel $monatswerteModel;
    private TageswerteMitarbeiterModel $tageswerteModel;

    private ZeitbuchungModel $zeitbuchungModel;
    private RundungsService $rundungsService;


    private PausenService $pausenService;


    private BetriebsferienModel $betriebsferienModel;
    private MitarbeiterHatAbteilungModel $mitarbeiterHatAbteilungModel;

    private MitarbeiterModel $mitarbeiterModel;
    private FeiertagService $feiertagService;


    private KrankzeitraumService $krankzeitraumService;
    private KurzarbeitService $kurzarbeitService;

    /** Betriebsferien werden wie Urlaub bewertet (8 Stunden pro Arbeitstag). */
    private const BETRIEBSFERIEN_URLAUB_STUNDEN = 8.0;

    /** Default für Kurzarbeit-Volltag (Fallback, wenn Tages-Soll unbekannt ist). */
    private const KURZARBEIT_DEFAULT_VOLLTAG_STUNDEN = 8.0;

    /** Default-Stunden für Feiertage (Fallback, wenn Tages-Soll unbekannt ist). */
    private const FEIERTAG_DEFAULT_STUNDEN = 8.0;

    /**
     * Mikro-Arbeitszeiten (z. B. 0,01h = ~36 Sekunden) entstehen meist durch versehentliches
     * Doppel-Stempeln (gehen/kommen) und sollen NICHT in Reports/Summen einfließen.
     *
     * 0,05h = 3 Minuten.
     */
    private const MICRO_ARBEITSZEIT_GRENZE_STUNDEN = 0.05;

    /**
     * Entfernt Mikro-Arbeitszeiten aus einer (noch unvollständigen) Tageswertliste,
     * damit Betriebsferien/Feiertage/Urlaub nicht durch versehentliche Mini-Buchungen
     * fälschlich als "gearbeitet" erkannt werden.
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @return array<int,array<string,mixed>>
     */
    private function filtereMicroArbeitszeitenVorReport(array $tageswerte): array
    {
        if ($tageswerte === []) {
            return $tageswerte;
        }

        foreach ($tageswerte as $i => $row) {
            $ist = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
            if ($ist <= 0.0) {
                continue;
            }
            if ($ist >= self::MICRO_ARBEITSZEIT_GRENZE_STUNDEN) {
                continue;
            }

            // Nur dann ausblenden, wenn wirklich Zeitstempel vorhanden sind.
            // Dadurch bleiben rein manuelle Mini-Werte (falls gewünscht) sichtbar.
            $hatZeitstempel = false;
            foreach (['kommen_roh', 'gehen_roh', 'kommen_korr', 'gehen_korr'] as $k) {
                $v = trim((string)($row[$k] ?? ''));
                if ($v !== '') {
                    $hatZeitstempel = true;
                    break;
                }
            }
            if (!$hatZeitstempel) {
                continue;
            }

            // Report/Summen neutralisieren – Rohdaten bleiben in `zeitbuchung` natürlich erhalten.
            $row['micro_arbeitszeit_ignoriert'] = 1;
            $row['arbeitszeit_stunden'] = sprintf('%.2f', 0.0);
            $row['pausen_stunden'] = sprintf('%.2f', 0.0);
            $row['pause_entscheidung_noetig'] = 0;
            $row['pause_entscheidung_auto_minuten'] = 0;

            $row['kommen_roh'] = null;
            $row['gehen_roh'] = null;
            $row['kommen_korr'] = null;
            $row['gehen_korr'] = null;

            $tageswerte[$i] = $row;
        }

        return $tageswerte;
    }

    /**
     * Ermittelt alle Tage (YYYY-MM-DD) in einem Monat, an denen mindestens eine Zeitbuchung
     * manuell geändert wurde (`zeitbuchung.manuell_geaendert=1`).
     *
     * @return array<string,bool> Set: ['2026-01-03' => true, ...]
     */
    private function holeManuellGeaenderteZeitbuchungTage(int $mitarbeiterId, \DateTimeImmutable $monatStart): array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return [];
        }

        try {
            $von = $monatStart->setTime(0, 0, 0);
            $bis = $von->modify('first day of next month');
        } catch (\Throwable $e) {
            return [];
        }

        $sql = 'SELECT DATE(zeitstempel) AS datum
                FROM zeitbuchung
                WHERE mitarbeiter_id = :mid
                  AND zeitstempel >= :von
                  AND zeitstempel < :bis
                  AND manuell_geaendert = 1
                GROUP BY DATE(zeitstempel)';

        try {
            $db = Database::getInstanz();
            $rows = $db->fetchAlle($sql, [
                'mid' => $mitarbeiterId,
                'von' => $von->format('Y-m-d H:i:s'),
                'bis' => $bis->format('Y-m-d H:i:s'),
            ]);

            $set = [];
            foreach ($rows as $r) {
                $d = trim((string)($r['datum'] ?? ''));
                if ($d !== '') {
                    $set[$d] = true;
                }
            }
            return $set;
        } catch (\Throwable $e) {
            return [];
        }
    }


    /**
     * Ermittelt den Pause-Override-Status pro Datum aus dem Audit-Log (`system_log`).
     *
     * Warum so?
     * - Pause-Override darf auch mit 0,00h gespeichert werden.
     * - `tageswerte_mitarbeiter.pause_korr_minuten` ist bei 0 nicht unterscheidbar (Default/Override).
     * - `felder_manuell_geaendert` wird auch von anderen Tagesfeldern gesetzt und kann daher
     *   nicht als alleiniger Indikator fuer Pause-Override dienen.
     *
     * Wir lesen pro Monat die Logeintraege:
     * - "Tageswerte gesetzt: Pause-Override"
     * - "Tageswerte entfernt: Pause-Override"
     * und bilden daraus den letzten Status je Datum.
     *
     * @return array<string,bool> Map: 'YYYY-MM-DD' => true (aktiv) / false (inaktiv)
     */
    private function holePauseOverrideStatusProTagFuerMonat(int $mitarbeiterId, \DateTimeImmutable $monatStart): array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return [];
        }

        try {
            $von = $monatStart->setTime(0, 0, 0);
            $bis = $von->modify('first day of next month');
        } catch (\Throwable $e) {
            return [];
        }

        $sql = 'SELECT zeitstempel, nachricht, daten
                FROM system_log
                WHERE kategorie = :kat
                  AND zeitstempel >= :von
                  AND zeitstempel < :bis
                  AND nachricht IN (:setMsg, :rmMsg)
                ORDER BY zeitstempel ASC';

        try {
            $db = Database::getInstanz();
            $rows = $db->fetchAlle($sql, [
                'kat'    => 'tageswerte_audit',
                'von'    => $von->format('Y-m-d H:i:s'),
                'bis'    => $bis->format('Y-m-d H:i:s'),
                'setMsg' => 'Tageswerte gesetzt: Pause-Override',
                'rmMsg'  => 'Tageswerte entfernt: Pause-Override',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $r) {
            $msg = (string)($r['nachricht'] ?? '');
            $json = (string)($r['daten'] ?? '');

            $data = null;
            if ($json !== '') {
                try {
                    $tmp = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($tmp)) {
                        $data = $tmp;
                    }
                } catch (\Throwable $e) {
                    $data = null;
                }
            }

            if (!is_array($data)) {
                continue;
            }

            $ziel = (int)($data['ziel_mitarbeiter_id'] ?? 0);
            if ($ziel !== $mitarbeiterId) {
                continue;
            }

            $datum = trim((string)($data['datum'] ?? ''));
            if ($datum === '') {
                continue;
            }

            if ($msg === 'Tageswerte gesetzt: Pause-Override') {
                $map[$datum] = true;
            } elseif ($msg === 'Tageswerte entfernt: Pause-Override') {
                $map[$datum] = false;
            }
        }

        return $map;
    }


    /**
     * Liefert Arbeitsblöcke (Kommen->Gehen) pro Tag für einen Monat, basierend auf `zeitbuchung`.
     * Für offene Blöcke (Kommen ohne Gehen) wird `gehen_*` als null gesetzt.
     *
     * @return array<string,array<int,array<string,mixed>>> Map: 'Y-m-d' => [ ['kommen_roh'=>..., 'gehen_roh'=>..., 'kommen_korr'=>..., 'gehen_korr'=>..., 'zeit_manuell_geaendert'=>0|1], ...]
     */
    private function holeArbeitsbloeckeProTagFuerMonat(int $mitarbeiterId, \DateTimeImmutable $monatStart): array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return [];
        }

        $tzName = (string)date_default_timezone_get();
        if ($tzName === '') {
            $tzName = 'Europe/Berlin';
        }

        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Throwable $e) {
            $tz = new \DateTimeZone('Europe/Berlin');
        }

        try {
            $von = $monatStart->setTime(0, 0, 0);
            $bis = $von->modify('first day of next month');
        } catch (\Throwable $e) {
            return [];
        }

        // Rohbuchungen für den Monat laden (einmalig).
        try {
            $buchungen = $this->zeitbuchungModel->holeFuerMitarbeiterUndZeitraum($mitarbeiterId, $von, $bis);
        } catch (\Throwable $e) {
            return [];
        }

        /** @var array<string,array<int,array<string,mixed>>> $proTag */
        $proTag = [];
        foreach ($buchungen as $b) {
            $ts = (string)($b['zeitstempel'] ?? '');
            if ($ts === '') {
                continue;
            }

            $ymd = substr($ts, 0, 10);
            if ($ymd === '') {
                continue;
            }

            if (!isset($proTag[$ymd])) {
                $proTag[$ymd] = [];
            }
            $proTag[$ymd][] = $b;
        }

        $result = [];
        /** @var array<string,array<int,array{0:(\DateTimeImmutable|null),1:(\DateTimeImmutable|null),2:int,3:int,4:int}>> $extraBlocks */
        $extraBlocks = [];

        $tage = array_keys($proTag);
        sort($tage);
        $overnightMaxSeconds = 12 * 3600;

        foreach ($tage as $ymd) {
            $dayBookings = $proTag[$ymd] ?? [];
            // Defensiv sortieren
            usort($dayBookings, static function (array $a, array $b): int {
                return strcmp((string)($a['zeitstempel'] ?? ''), (string)($b['zeitstempel'] ?? ''));
            });

            /** @var array<int,array{0:(\DateTimeImmutable|null),1:(\DateTimeImmutable|null),2:int,3:int,4:int}> $bloecke */
            $bloecke = [];
            $blockStart = null; // \DateTimeImmutable|null
            $blockStartManuell = 0;
            $blockStartNachtshift = 0;

            $nextYmd = '';
            try {
                $dtDay = new \DateTimeImmutable($ymd, $tz);
                $nextYmd = $dtDay->modify('+1 day')->format('Y-m-d');
            } catch (\Throwable $e) {
                $nextYmd = '';
            }

            foreach ($dayBookings as $b) {
                $typ = (string)($b['typ'] ?? '');
                $ts  = (string)($b['zeitstempel'] ?? '');
                if ($ts === '') {
                    continue;
                }

                $istManuell = ((int)($b['manuell_geaendert'] ?? 0) === 1) ? 1 : 0;
                $istNachtshift = ((int)($b['nachtshift'] ?? 0) === 1) ? 1 : 0;

                try {
                    $dt = new \DateTimeImmutable($ts, $tz);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($typ === 'kommen') {
                    if ($blockStart === null) {
                        $blockStart = $dt;
                        $blockStartManuell = $istManuell;
                        $blockStartNachtshift = $istNachtshift;
                    } else {
                        // Wenn mehrfach "kommen" hintereinander gestempelt wird (z. B. doppelter Scan
                        // oder manuell nachgetragen), darf der spätere Stempel nicht unsichtbar werden.
                        // Wir schließen den bisherigen offenen Block als "unvollständig" ab (ohne Gehen)
                        // und starten einen neuen Block.
                        $bloecke[] = [$blockStart, null, $blockStartManuell, 0, $blockStartNachtshift];
                        $blockStart = $dt;
                        $blockStartManuell = $istManuell;
                        $blockStartNachtshift = $istNachtshift;
                    }
                } elseif ($typ === 'gehen') {
                    if ($blockStart !== null && $dt > $blockStart) {
                        $bloecke[] = [$blockStart, $dt, $blockStartManuell, $istManuell, $blockStartNachtshift];
                        $blockStart = null;
                        $blockStartManuell = 0;
                        $blockStartNachtshift = 0;
                    } elseif ($blockStart === null) {
                        // Gehen ohne Kommen: als unvollständigen Block ablegen
                        $bloecke[] = [null, $dt, 0, $istManuell, 0];
                    }
                }
            }

            // Offener Block (Kommen ohne Gehen)
            if ($blockStart !== null) {
                $overnightClosed = false;
                if ($blockStartNachtshift === 1 && $nextYmd !== '' && isset($proTag[$nextYmd])) {
                    $nextDayBookings = $proTag[$nextYmd];
                    if (is_array($nextDayBookings) && $nextDayBookings !== []) {
                        usort($nextDayBookings, static function (array $a, array $b): int {
                            return strcmp((string)($a['zeitstempel'] ?? ''), (string)($b['zeitstempel'] ?? ''));
                        });

                        $firstGoIndex = null;
                        $firstGoDt = null;
                        $firstGoManuell = 0;
                        foreach ($nextDayBookings as $idx => $nb) {
                            $nTyp = (string)($nb['typ'] ?? '');
                            $nTs = (string)($nb['zeitstempel'] ?? '');
                            if ($nTs === '') {
                                continue;
                            }
                            try {
                                $nDt = new \DateTimeImmutable($nTs, $tz);
                            } catch (\Throwable $e) {
                                continue;
                            }

                            if ($nTyp === 'gehen') {
                                $firstGoIndex = (int)$idx;
                                $firstGoDt = $nDt;
                                $firstGoManuell = ((int)($nb['manuell_geaendert'] ?? 0) === 1) ? 1 : 0;
                                break;
                            }
                            if ($nTyp === 'kommen') {
                                break;
                            }
                        }

                        if ($firstGoDt instanceof \DateTimeImmutable) {
                            $diff = $firstGoDt->getTimestamp() - $blockStart->getTimestamp();
                            if ($diff > 0 && $diff <= $overnightMaxSeconds) {
                                $midnight = $blockStart->setTime(0, 0, 0)->modify('+1 day');
                                if ($midnight < $firstGoDt) {
                                    $bloecke[] = [$blockStart, $midnight, $blockStartManuell, 0, $blockStartNachtshift];
                                    $extraBlocks[$nextYmd][] = [$midnight, $firstGoDt, $blockStartManuell, $firstGoManuell, $blockStartNachtshift];
                                } else {
                                    $bloecke[] = [$blockStart, $firstGoDt, $blockStartManuell, $firstGoManuell, $blockStartNachtshift];
                                }
                                unset($nextDayBookings[$firstGoIndex]);
                                $proTag[$nextYmd] = array_values($nextDayBookings);
                                $overnightClosed = true;
                            }
                        }
                    }
                }

                if (!$overnightClosed) {
                    $bloecke[] = [$blockStart, null, $blockStartManuell, 0, $blockStartNachtshift];
                }
            }

            if (isset($extraBlocks[$ymd]) && $extraBlocks[$ymd] !== []) {
                $bloecke = array_merge($bloecke, $extraBlocks[$ymd]);
            }

            if (count($bloecke) > 1) {
                usort($bloecke, static function (array $a, array $b): int {
                    $aStart = $a[0] instanceof \DateTimeImmutable ? $a[0] : $a[1];
                    $bStart = $b[0] instanceof \DateTimeImmutable ? $b[0] : $b[1];
                    if ($aStart instanceof \DateTimeImmutable && $bStart instanceof \DateTimeImmutable) {
                        return $aStart <=> $bStart;
                    }
                    if ($aStart instanceof \DateTimeImmutable) {
                        return -1;
                    }
                    if ($bStart instanceof \DateTimeImmutable) {
                        return 1;
                    }
                    return 0;
                });
            }

            $out = [];
            foreach ($bloecke as $blk) {
                $kRoh = $blk[0];
                $gRoh = $blk[1];
                $manStart = (int)($blk[2] ?? 0);
                $manEnd   = (int)($blk[3] ?? 0);
                $nachtshift = (int)($blk[4] ?? 0);
                $zeitManuell = (($manStart === 1) || ($manEnd === 1)) ? 1 : 0;

                $kommenRohStr = ($kRoh instanceof \DateTimeImmutable) ? $kRoh->format('Y-m-d H:i:s') : null;
                $gehenRohStr  = ($gRoh instanceof \DateTimeImmutable) ? $gRoh->format('Y-m-d H:i:s') : null;

                $kommenKorrStr = null;
                $gehenKorrStr  = null;

                if ($kRoh instanceof \DateTimeImmutable) {
                    try {
                        $kKorr = $this->rundungsService->rundeZeitstempel($kRoh, 'kommen');
                        if ($kKorr instanceof \DateTimeImmutable) {
                            $kommenKorrStr = $kKorr->format('Y-m-d H:i:s');
                        }
                    } catch (\Throwable $e) {
                        $kommenKorrStr = null;
                    }
                }

                if ($gRoh instanceof \DateTimeImmutable) {
                    try {
                        $gKorr = $this->rundungsService->rundeZeitstempel($gRoh, 'gehen');
                        if ($gKorr instanceof \DateTimeImmutable) {
                            $gehenKorrStr = $gKorr->format('Y-m-d H:i:s');
                        }
                    } catch (\Throwable $e) {
                        $gehenKorrStr = null;
                    }
                }

                $out[] = [
                    'kommen_roh'             => $kommenRohStr,
                    'gehen_roh'              => $gehenRohStr,
                    'kommen_korr'            => $kommenKorrStr,
                    'gehen_korr'             => $gehenKorrStr,
                    'zeit_manuell_geaendert' => $zeitManuell,
                    'nachtshift'             => $nachtshift,
                ];
            }

            if ($out !== []) {
                $result[$ymd] = $out;
            }
        }

        return $result;
    }

    /**
     * Erstellt einen Default-Tagesdatensatz für die Monatsübersicht.
     *
     * Hintergrund:
     * - `tageswerte_mitarbeiter` kann (je nach Cron/Batch-Stand) nur einzelne Tage enthalten.
     * - Für die UI soll die Tabelle trotzdem immer den kompletten Monat anzeigen.
     *
     * @return array<string,mixed>
     */
    private function baueDefaultTageswert(
        string $ymd,
        \DateTimeImmutable $tag,
        array $betriebsferienTage
    ): array {
        $istBetriebsferien = isset($betriebsferienTage[$ymd]);
        $wochentag = (int)$tag->format('N');
        $istWochenende = ($wochentag >= 6);
        $istFeiertag = $this->feiertagService->istFeiertag($tag, null);

        // Tagestyp (Anzeige)
        $tagestyp = 'Arbeitstag';
        if ($istWochenende) {
            $tagestyp = 'Wochenende';
        }
        if ($istFeiertag) {
            $tagestyp = 'Feiertag';
        }
        // Betriebsferien werden wie Urlaub behandelt (nur an Arbeitstagen). Feiertage/Wochenenden bleiben Feiertag/Wochenende.
        if ($istBetriebsferien && !$istWochenende && !$istFeiertag) {
            $tagestyp = 'Betriebsferien';
        }

        $kennFeiertag = ($tagestyp === 'Feiertag') ? 1 : 0;
        $kennUrlaub   = 0;
        $urlaubStunden = '0.00';
        if ($istBetriebsferien && !$istWochenende && !$istFeiertag) {
            $kennUrlaub = 1;
            $urlaubStunden = sprintf('%.2f', self::BETRIEBSFERIEN_URLAUB_STUNDEN);
        }

        // Feiertage sollen im Monatsraster (ohne vorhandene Tageswerte) als bezahlte Abwesenheit sichtbar sein.
        // Nur an Arbeitstagen (Mo–Fr) – ein Feiertag am Wochenende zählt nicht als zusätzliche Stunden.
        $feiertagStunden = '0.00';
        if ($istFeiertag && !$istWochenende) {
            $feiertagStunden = sprintf('%.2f', self::FEIERTAG_DEFAULT_STUNDEN);
        }

        return [
            'datum'               => $ymd,

            // Kürzel/Begründung (z.B. "BF", "SoU") – stammt aus `tageswerte_mitarbeiter.kommentar`.
            'kommentar'           => null,

            // Manuelle Korrekturen (für UI-Markierung)
            'felder_manuell_geaendert' => 0,

            // Manuell geänderte Zeitbuchungen (Kommen/Gehen) – getrennt von Tageskennzeichen.
            'zeit_manuell_geaendert'   => 0,

            // Arbeitszeit / Kernwerte
            'arbeitszeit_stunden' => sprintf('%.2f', 0.0),
            'pausen_stunden'      => sprintf('%.2f', 0.0),
            // Pausen-Grenzfaelle (T-081): Entscheidung noetig? (Default ohne Entscheidung: keine Pause)
            'pause_entscheidung_noetig' => 0,
            'pause_entscheidung_auto_minuten' => 0,
            'saldo_stunden'       => '',
            'tagestyp'            => $tagestyp,
            'ist_betriebsferien'  => $istBetriebsferien,

            // Abwesenheits-/Sonderzeiten (für Arbeitszeitliste/PDF)
            'arzt_stunden'        => '0.00',
            'krank_lfz_stunden'   => '0.00',
            'krank_kk_stunden'    => '0.00',
            'feiertag_stunden'    => $feiertagStunden,
            'kurzarbeit_stunden'  => '0.00',
            'urlaub_stunden'      => $urlaubStunden,
            'sonstige_stunden'    => '0.00',

            // Kennzeichen
            'kennzeichen_arzt'        => 0,
            'kennzeichen_krank_lfz'   => 0,
            'kennzeichen_krank_kk'    => 0,
            'kennzeichen_feiertag'    => $kennFeiertag,
            'kennzeichen_kurzarbeit'  => 0,
            'kennzeichen_urlaub'      => $kennUrlaub,
            'kennzeichen_sonstiges'   => 0,

            // Kommen/Gehen (leer)
            'kommen_roh'          => null,
            'gehen_roh'           => null,
            'kommen_korr'         => null,
            'gehen_korr'          => null,
        ];
    }

    /**
     * Normalisiert die Monats-Tageswerte zu einem vollständigen Monatsraster.
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @return array<int,array<string,mixed>>
     */
    private function komplettiereMonatsraster(array $tageswerte, \DateTimeImmutable $monatStart, array $betriebsferienTage): array
    {
        /** @var array<string,array<string,mixed>> $byDate */
        $byDate = [];
        foreach ($tageswerte as $t) {
            $d = (string)($t['datum'] ?? '');
            if ($d !== '') {
                $byDate[$d] = $t;
            }
        }

        try {
            $monatEnd = $monatStart->modify('last day of this month');
        } catch (\Throwable $e) {
            $monatEnd = $monatStart;
        }

        $out = [];
        $d = $monatStart->setTime(0, 0, 0);
        while ($d <= $monatEnd) {
            $ymd = $d->format('Y-m-d');
            $base = $this->baueDefaultTageswert($ymd, $d, $betriebsferienTage);

            if (isset($byDate[$ymd]) && is_array($byDate[$ymd])) {
                // Base liefert Defaults (damit Views/PDF keine Undefined-Keys sehen)
                // und wird von den echten Daten überschrieben.
                $row = array_merge($base, $byDate[$ymd]);
            } else {
                $row = $base;
            }

            // Betriebsferien müssen in Reports wie Urlaub zählen (8h pro Arbeitstag),
            // auch wenn `tageswerte_mitarbeiter` hierfür noch keine Stunden/Kennzeichen liefert.
            if (isset($betriebsferienTage[$ymd])) {
                $wochentag = (int)$d->format('N');
                $istWochenende = ($wochentag >= 6);
                $istFeiertag = $this->feiertagService->istFeiertag($d, null);

                $row['ist_betriebsferien'] = true;

                if (!$istWochenende && !$istFeiertag) {
                    // WICHTIG:
                    // - Betriebsferien werden an Arbeitstagen wie Urlaub bewertet (8h),
                    //   aber NICHT zusätzlich, wenn an diesem Tag tatsächlich gearbeitet wurde.
                    // - Sonst würde in der Monatsübersicht/PDF die Arbeitszeit doppelt zählen
                    //   (Arbeitszeit + 8h Betriebsferien-Urlaub).
                    $hatArbeitszeit = false;
                    $arbStd = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
                    if ($arbStd > 0.0) {
                        $hatArbeitszeit = true;
                    }
                    if (!$hatArbeitszeit) {
                        $k1 = trim((string)($row['kommen_roh'] ?? ''));
                        $g1 = trim((string)($row['gehen_roh'] ?? ''));
                        $k2 = trim((string)($row['kommen_korr'] ?? ''));
                        $g2 = trim((string)($row['gehen_korr'] ?? ''));
                        if ($k1 !== '' || $g1 !== '' || $k2 !== '' || $g2 !== '') {
                            $hatArbeitszeit = true;
                        }
                    }

                    $kennUrlaub = (int)($row['kennzeichen_urlaub'] ?? 0);

                    $hatAndereKennzeichen = (
                        ((int)($row['kennzeichen_feiertag'] ?? 0) === 1)
                        || ((int)($row['kennzeichen_arzt'] ?? 0) === 1)
                        || ((int)($row['kennzeichen_krank_lfz'] ?? 0) === 1)
                        || ((int)($row['kennzeichen_krank_kk'] ?? 0) === 1)
                        || ((int)($row['kennzeichen_kurzarbeit'] ?? 0) === 1)
                        || ((int)($row['kennzeichen_sonstiges'] ?? 0) === 1)
                    );

                    if ($hatArbeitszeit) {
                        // Wenn gearbeitet wurde: keine zusätzliche Betriebsferien-Urlaubszeit.
                        $row['kennzeichen_urlaub'] = 0;
                        $row['urlaub_stunden'] = sprintf('%.2f', 0.0);
                    } elseif ($kennUrlaub !== 1 && !$hatAndereKennzeichen) {
                        $row['kennzeichen_urlaub'] = 1;
                        $row['urlaub_stunden'] = sprintf('%.2f', self::BETRIEBSFERIEN_URLAUB_STUNDEN);
                    }

                    $tagestyp = (string)($row['tagestyp'] ?? '');
                    if ($tagestyp === '' || $tagestyp === 'Arbeitstag' || ($hatArbeitszeit && $tagestyp === 'Urlaub')) {
                        $row['tagestyp'] = 'Betriebsferien';
                    }
                }
            }

            $out[] = $row;

            $d = $d->modify('+1 day');
        }

        return $out;
    }

    /**
     * Kurzarbeit-Volltage sollen in Monatsübersicht/PDF wie Betriebsferien funktionieren:
     * - an Arbeitstagen als Kurzarbeit-Stunden sichtbar (Default = Tages-Soll, fallback = 8h),
     * - aber NICHT zusätzlich, wenn an diesem Tag tatsächlich gearbeitet wurde.
     *
     * Hinweis:
     * - Für Saldo/Soll wird Kurzarbeit weiterhin als Soll-Reduktion betrachtet (MasterPrompt).
     * - Teil-Kurzarbeit (z. B. 4h) bleibt unangetastet.
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @return array<int,array<string,mixed>>
     */
    private function wendeKurzarbeitVolltagWieBetriebsferienAn(array $tageswerte, float $volltagStunden): array
    {
        if ($tageswerte === []) {
            return $tageswerte;
        }

        if ($volltagStunden <= 0.0) {
            $volltagStunden = self::KURZARBEIT_DEFAULT_VOLLTAG_STUNDEN;
        }

        foreach ($tageswerte as $i => $row) {
            $datum = (string)($row['datum'] ?? '');
            if ($datum === '') {
                continue;
            }

            $kennKurz = (int)($row['kennzeichen_kurzarbeit'] ?? 0);
            $kurzStd  = (float)str_replace(',', '.', (string)($row['kurzarbeit_stunden'] ?? '0'));

            if ($kennKurz !== 1 && $kurzStd <= 0.0) {
                continue;
            }

            // Schutz: keine Kurzarbeit auf Wochenenden/Feiertagen.
            try {
                $dt = new \DateTimeImmutable($datum);
            } catch (\Throwable $e) {
                continue;
            }

            $wochentag = (int)$dt->format('N');
            if ($wochentag >= 6) {
                continue;
            }
            if ($this->feiertagService->istFeiertag($dt, null)) {
                continue;
            }

            // Konflikte: wenn andere Kennzeichen aktiv sind (Urlaub/Krank/Arzt/Sonstiges/Betriebsferien), nichts anfassen.
            $hatKonflikt = (
                ((int)($row['kennzeichen_urlaub'] ?? 0) === 1)
                || ((int)($row['kennzeichen_krank_lfz'] ?? 0) === 1)
                || ((int)($row['kennzeichen_krank_kk'] ?? 0) === 1)
                || ((int)($row['kennzeichen_arzt'] ?? 0) === 1)
                || ((int)($row['kennzeichen_sonstiges'] ?? 0) === 1)
                || ((int)($row['kennzeichen_feiertag'] ?? 0) === 1)
                || ((bool)($row['ist_betriebsferien'] ?? false) === true)
            );
            if ($hatKonflikt) {
                continue;
            }

            // Wenn Stunden fehlen, als Volltag behandeln.
            if ($kurzStd <= 0.0) {
                $kurzStd = $volltagStunden;
                $tageswerte[$i]['kennzeichen_kurzarbeit'] = 1;
                $tageswerte[$i]['kurzarbeit_stunden']     = sprintf('%.2f', $kurzStd);
            }

            // Nur Volltag-Logik anwenden (Teil-Kurzarbeit bleibt unangetastet).
            $istVolltag = ($kurzStd >= ($volltagStunden - 0.01));
            if (!$istVolltag) {
                continue;
            }

            // Wenn gearbeitet wurde: Kurzarbeit darf nicht zusätzlich wirken.
            $hatArbeitszeit = false;
            $arbStd = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
            if ($arbStd > 0.0) {
                $hatArbeitszeit = true;
            }
            if (!$hatArbeitszeit) {
                $k1 = trim((string)($row['kommen_roh'] ?? ''));
                $g1 = trim((string)($row['gehen_roh'] ?? ''));
                $k2 = trim((string)($row['kommen_korr'] ?? ''));
                $g2 = trim((string)($row['gehen_korr'] ?? ''));
                if ($k1 !== '' || $g1 !== '' || $k2 !== '' || $g2 !== '') {
                    $hatArbeitszeit = true;
                }
            }

            if ($hatArbeitszeit) {
                $tageswerte[$i]['kennzeichen_kurzarbeit'] = 0;
                $tageswerte[$i]['kurzarbeit_stunden']     = sprintf('%.2f', 0.0);

                $tagestyp = strtolower(trim((string)($row['tagestyp'] ?? '')));
                if ($tagestyp === 'kurzarbeit') {
                    $tageswerte[$i]['tagestyp'] = 'Arbeitstag';
                }
            } else {
                // Anzeige-Tagestyp setzen (falls noch Arbeitstag)
                $tagestyp = strtolower(trim((string)($row['tagestyp'] ?? '')));
                if ($tagestyp === '' || $tagestyp === 'arbeitstag') {
                    $tageswerte[$i]['tagestyp'] = 'Kurzarbeit';
                }
            }
        }

        return $tageswerte;
    }

    /**
     * Normalisiert einen DATETIME-String auf das Format 'Y-m-d H:i:s'.
     *
     * Hintergrund:
     * - In PDO-Fetches kommen DATETIME-Spalten typischerweise als String.
     * - Je nach DB-/Treiber-Setup kann ein Wert auch bereits im gewünschten Format vorliegen.
     * - Für die Views wollen wir konsistente, erwartbare Strings oder NULL.
     */
    private function normalisiereDateTimeString($wert): ?string
    {
        if ($wert === null) {
            return null;
        }

        $s = trim((string)$wert);
        if ($s === '') {
            return null;
        }

        try {
            $dt = new \DateTimeImmutable($s);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            // Wenn das Parsing fehlschlägt, geben wir den Rohstring zurück,
            // damit zumindest eine Anzeige möglich bleibt.
            return $s;
        }
    }

    /**
     * Leitet (falls nötig) aus einem Rohzeitstempel den gerundeten Zeitstempel ab.
     *
     * Wichtig:
     * - Das ist eine reine Anzeige-/Fallback-Hilfe.
     * - Es werden **keine** Daten in der DB verändert.
     */
    private function leiteKorrigiertenZeitstempelAb(?string $roh, $korr, string $typ): ?string
    {
        // WICHTIGE Projektentscheidung:
        // - Zeitbuchungen werden immer als Rohzeit gespeichert (sekundengenau).
        // - Rundung wird **niemals** beim Buchen gespeichert/überschrieben,
        //   sondern nur für Berechnungen in Auswertungen/Export/PDF abgeleitet.
        //
        // Daher: Wenn eine Rohzeit vorhanden ist, leiten wir die korrigierte Zeit
        // immer daraus ab (konfigurierbar über `zeit_rundungsregel`).

        $rohNorm = $this->normalisiereDateTimeString($roh);
        if ($rohNorm !== null) {
            try {
                $dt = new \DateTimeImmutable($rohNorm);
                $gerundet = $this->rundungsService->rundeZeitstempel($dt, $typ);

                return $gerundet->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // Fallback: im Zweifel wenigstens die Rohzeit anzeigen
                return $rohNorm;
            }
        }

        // Legacy-Fallback (sollte perspektivisch nicht mehr nötig sein):
        // Wenn keine Rohzeit vorhanden ist, geben wir den gespeicherten Korr-Wert zurück.
        return $this->normalisiereDateTimeString($korr);
    }
    private function __construct()
    {
        $this->monatswerteModel = new MonatswerteMitarbeiterModel();
        $this->tageswerteModel  = new TageswerteMitarbeiterModel();

        $this->zeitbuchungModel = new ZeitbuchungModel();
        $this->rundungsService   = RundungsService::getInstanz();

        $this->pausenService = PausenService::getInstanz();

        $this->betriebsferienModel = new BetriebsferienModel();
        $this->mitarbeiterHatAbteilungModel = new MitarbeiterHatAbteilungModel();

        $this->mitarbeiterModel = new MitarbeiterModel();
        $this->feiertagService  = FeiertagService::getInstanz();

        $this->krankzeitraumService = KrankzeitraumService::getInstanz();
        $this->kurzarbeitService = KurzarbeitService::getInstanz();
    }

    /**
     * Fallback-Berechnung der Sollstunden, wenn es keinen Datensatz in `monatswerte_mitarbeiter` gibt.
     *
     * Logik (Basisversion):
     * - Wochenarbeitszeit des Mitarbeiters wird auf 5 Arbeitstage (Mo-Fr) verteilt.
     * - Wochenenden zählen als arbeitsfrei (kein Soll).
     * - Feiertage gelten als bezahlte Abwesenheit und reduzieren das Soll **nicht**.
     * - Betriebsferien werden wie Urlaub behandelt und reduzieren das Soll **nicht**.
     *
     * Hinweis:
     * - Diese Logik ist bewusst einfach gehalten und kann später über Konfiguration erweitert werden
     *   (z. B. abweichende Arbeitstage/Woche, Schichtmodelle, Teilzeitverteilungen).
     */
    private function berechneSollstundenFallback(int $mitarbeiterId, \DateTimeImmutable $monatStart, array $betriebsferienTage): float
    {
        $wochenarbeitszeit = 0.0;

        try {
            $m = $this->mitarbeiterModel->holeNachId($mitarbeiterId);
            if (is_array($m) && isset($m['wochenarbeitszeit'])) {
                $wochenarbeitszeit = (float)str_replace(',', '.', (string)$m['wochenarbeitszeit']);
            }
        } catch (\Throwable $e) {
            $wochenarbeitszeit = 0.0;
        }

        if ($wochenarbeitszeit <= 0) {
            return 0.0;
        }

        $tagesSoll = $wochenarbeitszeit / 5.0;

        try {
            $monatEnd = $monatStart->modify('last day of this month');
        } catch (\Throwable $e) {
            $monatEnd = $monatStart;
        }

        $summe = 0.0;
        $d = $monatStart;
        while ($d <= $monatEnd) {
            $wochentag = (int)$d->format('N'); // 1=Mo .. 7=So
            if ($wochentag >= 6) {
                $d = $d->modify('+1 day');
                continue;
            }

            $summe += $tagesSoll;
            $d = $d->modify('+1 day');
        }

        return round($summe, 2);
    }

    public static function getInstanz(): ReportService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

/**
 * Ermittelt alle Tage im gegebenen Monat, die für den Mitarbeiter als Betriebsferien gelten.
 *
 * Es werden sowohl globale Betriebsferien (abteilung_id IS NULL) als auch betriebsabhängige
 * Einträge für alle Abteilungen des Mitarbeiters berücksichtigt.
 *
 * @return array<string,bool> Map: 'Y-m-d' => true
 */
private function holeBetriebsferienTageFuerMitarbeiterUndMonat(int $mitarbeiterId, \DateTimeImmutable $monatStart): array
{
    $tage = [];

    try {
        $monatEnd = $monatStart->modify('last day of this month');
    } catch (\Throwable $e) {
        $monatEnd = $monatStart;
    }

    // Abteilungen des Mitarbeiters laden
    try {
        $abteilungIds = $this->mitarbeiterHatAbteilungModel->holeAbteilungsIdsFuerMitarbeiter($mitarbeiterId);
    } catch (\Throwable $e) {
        $abteilungIds = [];
    }

    $abteilungIds = array_values(array_unique(array_filter(array_map('intval', $abteilungIds), static fn($v) => $v > 0)));

    // Betriebsferien (global + abteilungsspezifisch) laden
    $eintraege = [];
    try {
        $eintraege = array_merge($eintraege, $this->betriebsferienModel->holeAktive(null));
        foreach ($abteilungIds as $aid) {
            $eintraege = array_merge($eintraege, $this->betriebsferienModel->holeAktive($aid));
        }
    } catch (\Throwable $e) {
        if (class_exists('Logger')) {
            Logger::warn('Fehler beim Laden der Betriebsferien für Monatsreport', [
                'mitarbeiter_id' => $mitarbeiterId,
                'monat'          => $monatStart->format('Y-m'),
                'exception'      => $e->getMessage(),
            ], $mitarbeiterId, null, 'reportservice');
        }
        return [];
    }

    if ($eintraege === []) {
        return [];
    }

    // Nur Einträge berücksichtigen, die den Monat schneiden
    foreach ($eintraege as $e) {
        $von = (string)($e['von_datum'] ?? '');
        $bis = (string)($e['bis_datum'] ?? '');

        if ($von === '' || $bis === '') {
            continue;
        }

        try {
            $vonDt = new \DateTimeImmutable($von);
            $bisDt = new \DateTimeImmutable($bis);
        } catch (\Throwable $ex) {
            continue;
        }

        // Overlap-Check
        if ($bisDt < $monatStart || $vonDt > $monatEnd) {
            continue;
        }

        $start = $vonDt > $monatStart ? $vonDt : $monatStart;
        $ende  = $bisDt < $monatEnd ? $bisDt : $monatEnd;

        // Tage inklusiv addieren
        $d = $start;
        while ($d <= $ende) {
            $tage[$d->format('Y-m-d')] = true;
            $d = $d->modify('+1 day');
        }
    }

    return $tage;
}


    /**
     * Fallback: Berechnet Tageswerte direkt aus den Roh-Zeitbuchungen (`zeitbuchung`),
     * falls noch keine Datensätze in `tageswerte_mitarbeiter` vorhanden sind.
     *
     * Dabei gilt:
     * - Rohdaten bleiben unverändert (sekundengenau in `zeitbuchung`)
     * - `kommen_korr` / `gehen_korr` werden aus den Rohdaten mittels `RundungsService` abgeleitet
     * - Pausen-/Saldo-Logik ist in dieser Basisversion noch minimal (Pause = 0)
     *
     * @param array<string,bool> $betriebsferienTage Map: 'Y-m-d' => true
     *
     * @return array<int,array<string,mixed>>
     */
    private function berechneTageswerteFallbackAusZeitbuchung(
        int $mitarbeiterId,
        int $jahr,
        int $monat,
        \DateTimeImmutable $monatStart,
        array $betriebsferienTage
    ): array {
        $tageswerte = [];

        $tzName = (string)date_default_timezone_get();
        if ($tzName === '') {
            $tzName = 'Europe/Berlin';
        }

        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Throwable $e) {
            $tz = new \DateTimeZone('Europe/Berlin');
        }


        // Pausenentscheidung (optional, defensiv: wenn Migration/Tabelle fehlt -> ignorieren)
        $pausenentscheidungModel = null;
        if (class_exists('PausenentscheidungModel')) {
            try {
                $pausenentscheidungModel = new PausenentscheidungModel();
            } catch (\Throwable $e) {
                $pausenentscheidungModel = null;
            }
        }

        $start = $monatStart->setTime(0, 0, 0);
        $endeExclusive = $start->modify('first day of next month');

        // Rohbuchungen für den Monat laden
        try {
            $buchungen = $this->zeitbuchungModel->holeFuerMitarbeiterUndZeitraum($mitarbeiterId, $start, $endeExclusive);
        } catch (\Throwable $e) {
            $buchungen = [];
            if (class_exists('Logger')) {
                Logger::warn('Fallback: Zeitbuchungen für Monatsbericht konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'reportservice');
            }
        }

        /** @var array<string,array<int,array<string,mixed>>> $buchungenProTag */
        $buchungenProTag = [];
        foreach ($buchungen as $b) {
            $ts = (string)($b['zeitstempel'] ?? '');
            if ($ts === '') {
                continue;
            }

            // DATETIME-Format: YYYY-MM-DD HH:MM:SS → YYYY-MM-DD
            $ymd = substr($ts, 0, 10);
            if ($ymd === '') {
                continue;
            }

            if (!isset($buchungenProTag[$ymd])) {
                $buchungenProTag[$ymd] = [];
            }
            $buchungenProTag[$ymd][] = $b;
        }

        // Tageslauf über den gesamten Monat (inkl. Wochenenden)
        $monatEnd = $start->modify('last day of this month');
        $d = $start;

        while ($d <= $monatEnd) {
            $ymd = $d->format('Y-m-d');

            $dayBookings = $buchungenProTag[$ymd] ?? [];

            $zeitManuell = false;
            $kommenRoh = null; // \DateTimeImmutable|null
            $gehenRoh  = null; // \DateTimeImmutable|null

            /** @var array<int,array{0:\DateTimeImmutable,1:\DateTimeImmutable}> $arbeitsBloecke */
            $arbeitsBloecke = [];
            $blockStart = null; // \DateTimeImmutable|null

            // Buchungen sind in der Regel bereits ASC sortiert, dennoch defensiv.
            usort($dayBookings, static function (array $a, array $b): int {
                return strcmp((string)($a['zeitstempel'] ?? ''), (string)($b['zeitstempel'] ?? ''));
            });

            foreach ($dayBookings as $b) {
                if ((int)($b['manuell_geaendert'] ?? 0) === 1) {
                    $zeitManuell = true;
                }

                $typ = (string)($b['typ'] ?? '');
                $ts  = (string)($b['zeitstempel'] ?? '');
                if ($ts === '') {
                    continue;
                }

                try {
                    $dt = new \DateTimeImmutable($ts, $tz);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($typ === 'kommen') {
                    if ($kommenRoh === null || $dt < $kommenRoh) {
                        $kommenRoh = $dt;
                    }
                    if ($blockStart === null) {
                        $blockStart = $dt;
                    }
                } elseif ($typ === 'gehen') {
                    if ($gehenRoh === null || $dt > $gehenRoh) {
                        $gehenRoh = $dt;
                    }
                    if ($blockStart !== null && $dt > $blockStart) {
                        $arbeitsBloecke[] = [$blockStart, $dt];
                        $blockStart = null;
                    }
                }
            }
            // Korrigierte Zeiten (nur berechnet, nicht gespeichert!)
            // Arbeitszeit wird aus den korrigierten Zeiten berechnet (Projektvorgabe),
            // Rohzeiten bleiben unverändert in `zeitbuchung`.
            $kommenKorr = null; // \\DateTimeImmutable|null
            $gehenKorr  = null; // \\DateTimeImmutable|null

            if ($kommenRoh !== null) {
                try {
                    $kommenKorr = $this->rundungsService->rundeZeitstempel($kommenRoh, 'kommen');
                } catch (\Throwable $e) {
                    $kommenKorr = $kommenRoh;
                }
            }

            if ($gehenRoh !== null) {
                try {
                    $gehenKorr = $this->rundungsService->rundeZeitstempel($gehenRoh, 'gehen');
                } catch (\Throwable $e) {
                    $gehenKorr = $gehenRoh;
                }
            }

            // WICHTIG: Mehrfach-Kommen/Gehen wird als Summe aus Arbeitsblöcken gewertet.
            $bruttoStd = 0.0;
            $istStd = 0.0;
            $pauseMin = 0;
            $pauseEntscheidungNoetig = false;
            $pauseAutoMin = 0;
            $pausenentscheidungStatus = null;

            if ($arbeitsBloecke !== []) {
                foreach ($arbeitsBloecke as $block) {
                    [$kRoh, $gRoh] = $block;

                    $k = $this->rundungsService->rundeZeitstempel($kRoh, 'kommen');
                    $g = $this->rundungsService->rundeZeitstempel($gRoh, 'gehen');

                    if ($k === null || $g === null || $g <= $k) {
                        $k = $kRoh;
                        $g = $gRoh;
                    }

                    $diffSek = $g->getTimestamp() - $k->getTimestamp();
                    if ($diffSek <= 0) {
                        continue;
                    }

                    $bruttoStd += ($diffSek / 3600.0);

                    $res = $this->pausenService->berechnePausenMinutenUndEntscheidungFuerBlock($k, $g);
                    $pMin = (int)($res['pause_minuten'] ?? 0);
                    $autoMin = (int)($res['auto_pause_minuten'] ?? $pMin);
                    $entscheidungNoetig = (bool)($res['entscheidung_noetig'] ?? false);

                    if ($pMin < 0) {
                        $pMin = 0;
                    }
                    if ($entscheidungNoetig) {
                        $pauseEntscheidungNoetig = true;
                        $pauseAutoMin += max(0, $autoMin);
                    }

                    $pauseMin += $pMin;
                }
            } elseif ($kommenKorr !== null && $gehenKorr !== null && $gehenKorr > $kommenKorr) {
                // Kein Block-Paar erkennbar → klassisch (frühestes Kommen / spätestes Gehen)
                $diffSek = $gehenKorr->getTimestamp() - $kommenKorr->getTimestamp();
                $bruttoStd = ($diffSek / 3600.0);

                $res = $this->pausenService->berechnePausenMinutenUndEntscheidungFuerBlock($kommenKorr, $gehenKorr);
                $pauseMin = (int)($res['pause_minuten'] ?? 0);
                $pauseAutoMin = (int)($res['auto_pause_minuten'] ?? $pauseMin);
                $pauseEntscheidungNoetig = (bool)($res['entscheidung_noetig'] ?? false);
            } elseif ($kommenRoh !== null && $gehenRoh !== null && $gehenRoh > $kommenRoh) {
                // Fallback, falls Rundung nicht möglich war
                $diffSek = $gehenRoh->getTimestamp() - $kommenRoh->getTimestamp();
                $bruttoStd = ($diffSek / 3600.0);

                $res = $this->pausenService->berechnePausenMinutenUndEntscheidungFuerBlock($kommenRoh, $gehenRoh);
                $pauseMin = (int)($res['pause_minuten'] ?? 0);
                $pauseAutoMin = (int)($res['auto_pause_minuten'] ?? $pauseMin);
                $pauseEntscheidungNoetig = (bool)($res['entscheidung_noetig'] ?? false);
            }

            // Gespeicherte Pausenentscheidung anwenden (Default bleibt: keine Pause, wenn Entscheidung nötig und offen)
            if ($pauseEntscheidungNoetig && $pausenentscheidungModel !== null) {
                $row = $pausenentscheidungModel->holeFuerMitarbeiterUndDatum($mitarbeiterId, $ymd);
                if (is_array($row) && isset($row['entscheidung'])) {
                    $pausenentscheidungStatus = strtoupper(trim((string)$row['entscheidung']));
                    if ($pausenentscheidungStatus === 'NICHT_ABZIEHEN') {
                        $pauseMin = 0;
                        $pauseEntscheidungNoetig = false;
                        $pauseAutoMin = 0;
                    } elseif ($pausenentscheidungStatus === 'ABZIEHEN') {
                        $pauseMin = max(0, (int)$pauseAutoMin);
                        $pauseEntscheidungNoetig = false;
                        $pauseAutoMin = 0;
                    }
                }
            }

            $istStd = $bruttoStd - ($pauseMin / 60.0);

            if ($istStd < 0) {
                $istStd = 0.0;
            }

            $pauseStd = $pauseMin / 60.0;

            $istBetriebsferien = isset($betriebsferienTage[$ymd]);

            $wochentag = (int)$d->format('N');
            $istWochenende = ($wochentag >= 6);
            $istFeiertag = $this->feiertagService->istFeiertag($d, null);

            $tagestyp = 'Arbeitstag';
            if ($istWochenende) {
                $tagestyp = 'Wochenende';
            }
            if ($istFeiertag) {
                $tagestyp = 'Feiertag';
            }
            // Betriebsferien nur an Arbeitstagen sichtbar machen (Feiertag/Wochenende bleibt so).
            if ($istBetriebsferien && !$istWochenende && !$istFeiertag) {
                $tagestyp = 'Betriebsferien';
            }

            $tageswerte[] = [
                'datum'               => $ymd,
                'kommentar'           => null,
                'felder_manuell_geaendert' => 0,
                'zeit_manuell_geaendert'   => $zeitManuell ? 1 : 0,
                'arbeitszeit_stunden' => sprintf('%.2f', $istStd),
                'pausen_stunden'      => sprintf('%.2f', $pauseStd),
                'pause_entscheidung_noetig' => $pauseEntscheidungNoetig ? 1 : 0,
                'pause_entscheidung_auto_minuten' => $pauseEntscheidungNoetig ? (int)$pauseAutoMin : 0,
                'pause_entscheidung_status' => $pausenentscheidungStatus,
                'saldo_stunden'       => '',
                'tagestyp'            => $tagestyp,
                'ist_betriebsferien'  => $istBetriebsferien,

                // Zusatzinfos (derzeit noch nicht im View angezeigt, aber nützlich für spätere Ausbaustufen)
                'kommen_roh'          => $kommenRoh  !== null ? $kommenRoh->format('Y-m-d H:i:s') : null,
                'gehen_roh'           => $gehenRoh   !== null ? $gehenRoh->format('Y-m-d H:i:s') : null,
                // Korr-Werte werden nur berechnet (nicht gespeichert) und für Auswertungen/PDF genutzt.
                'kommen_korr'         => $kommenKorr !== null ? $kommenKorr->format('Y-m-d H:i:s') : null,
                'gehen_korr'          => $gehenKorr  !== null ? $gehenKorr->format('Y-m-d H:i:s') : null,
            ];

            $d = $d->modify('+1 day');
        }

        return $tageswerte;
    }

    /**
     * Liefert Monatsdaten (Monatswerte + Tageswerte) für einen Mitarbeiter.
     *
     * Rückgabeformat:
     * [
     *   'mitarbeiter_id' => 5,
     *   'jahr'           => 2025,
     *   'monat'          => 1,
     *   'monatswerte'    => [
     *       'sollstunden'       => '168.00',
     *       'iststunden'        => '172.50',
     *       'differenzstunden'  => '4.50',
     *       'urlaub_genommen'   => '2.0',
     *       'urlaub_verbleibend'=> '18.0',
     *   ] | null,
     *   'tageswerte'     => [
     *       [
     *         'datum'              => '2025-01-01',
     *         'arbeitszeit_stunden'=> '8.00',
     *         'pausen_stunden'     => '0.50',
     *         'saldo_stunden'      => '',
     *         'tagestyp'           => 'Arbeitstag',
     *       ],
     *       ...
     *   ],
     * ]
     *
     * @return array<string,mixed>
     */
    public function holeMonatsdatenFuerMitarbeiter(int $mitarbeiterId, int $jahr, int $monat): array
    {
        // Monat plausibilisieren (1–12)
        if ($monat < 1) {
            $monat = 1;
        } elseif ($monat > 12) {
            $monat = 12;
        }

        // Ersten Tag des Monats bestimmen
        try {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
        } catch (\Throwable $e) {
            $start = new \DateTimeImmutable('first day of this month');
        }

        // Jahr/Monat aus normalisiertem Datum übernehmen
        $jahr  = (int)$start->format('Y');
        $monat = (int)$start->format('n');

        // Monats-Gesamtdatensatz laden
        $monatswerteRoh = null;
        try {
            $monatswerteRoh = $this->monatswerteModel->holeNachMitarbeiterUndMonat($mitarbeiterId, $jahr, $monat);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden der Monatswerte', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'reportservice');
            }
        }

        $monatswerte = null;
        if (is_array($monatswerteRoh)) {
            $monatswerte = [
                'sollstunden'        => (string)($monatswerteRoh['soll_stunden'] ?? '0.00'),
                'iststunden'         => (string)($monatswerteRoh['ist_stunden'] ?? '0.00'),
                // `ueberstunden` wird als Differenz interpretiert
                'differenzstunden'   => (string)($monatswerteRoh['ueberstunden'] ?? '0.00'),
                'urlaub_genommen'    => (string)($monatswerteRoh['urlaubstage_genommen'] ?? '0.00'),
                'urlaub_verbleibend' => (string)($monatswerteRoh['urlaubstage_verbleibend'] ?? '0.00'),
            ];
        }

        // Tageswerte laden und für View aufbereiten
        $tageswerte    = [];
        $tageswerteRoh = [];
        try {
            $tageswerteRoh = $this->tageswerteModel->holeAlleFuerMitarbeiterUndMonat($mitarbeiterId, $jahr, $monat);
        } catch (\Throwable $e) {
            $tageswerteRoh = [];
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden der Tageswerte für Monatsbericht', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'reportservice');
            }
        }

        // Betriebsferien-Tage für den Monat ermitteln (für Anzeige im Report)
        $betriebsferienTage = $this->holeBetriebsferienTageFuerMitarbeiterUndMonat($mitarbeiterId, $start);

        // Manuelle Zeitkorrekturen (Kommen/Gehen) für Markierung in UI/PDF.
        $manuellZeitbuchungTage = $this->holeManuellGeaenderteZeitbuchungTage($mitarbeiterId, $start);

        // Pause-Override Status (Audit-Log), damit "Haken raus" wieder die normalen Pausenregeln nutzt
        // (auch wenn andere Tagesfelder manuell gesetzt sind) und Override=0,00 weiterhin moeglich ist.
        $pauseOverrideStatusProTag = $this->holePauseOverrideStatusProTagFuerMonat($mitarbeiterId, $start);

        // Fallback: Zeitbuchungen direkt auswerten.
        // Hintergrund:
        // - `tageswerte_mitarbeiter` kann (je nach Cron/Batch-Stand) lückenhaft sein.
        // - Für Monatsübersicht/PDF sollen auch Tage mit Buchungen sichtbar bleiben.
        // - DB-Werte (inkl. Overrides) haben Vorrang und überschreiben den Fallback.
        $tageswerteFallback = $this->berechneTageswerteFallbackAusZeitbuchung($mitarbeiterId, $jahr, $monat, $start, $betriebsferienTage);
        $tageswerte = $tageswerteFallback;

        if ($tageswerteRoh !== []) {
            foreach ($tageswerteRoh as $tw) {
            $datum      = (string)($tw['datum'] ?? '');
            $pauseOverrideAktiv = isset($pauseOverrideStatusProTag[$datum]) ? (bool)$pauseOverrideStatusProTag[$datum] : false;
            $istStunden = (string)($tw['ist_stunden'] ?? '0.00');
            $pauseMinDb = (int)($tw['pause_korr_minuten'] ?? 0);
            $felderManuell = (int)($tw['felder_manuell_geaendert'] ?? 0);

            $rohManuell = (int)($tw['rohdaten_manuell_geaendert'] ?? 0);
            $zeitManuell = ($rohManuell === 1) || isset($manuellZeitbuchungTage[$datum]);
            $pauseMin   = $pauseMinDb;
            $pauseStd   = $pauseMin / 60.0;
            $pauseEntscheidungNoetig = false;
            $pauseAutoMin = 0;

            // Kommen/Gehen: Rohzeiten bleiben unverändert gespeichert.
            $kommenRoh  = $this->normalisiereDateTimeString($tw['kommen_roh'] ?? null);
            $gehenRoh   = $this->normalisiereDateTimeString($tw['gehen_roh'] ?? null);

            // Korrigierte Zeiten werden für Berechnungen abgeleitet (nicht gespeichert).
            $kommenKorr = $this->leiteKorrigiertenZeitstempelAb($kommenRoh, $tw['kommen_korr'] ?? null, 'kommen');
            $gehenKorr  = $this->leiteKorrigiertenZeitstempelAb($gehenRoh,  $tw['gehen_korr']  ?? null, 'gehen');

            // Iststunden: werden aus den korrigierten Zeiten berechnet (minus Pause).
            // Fallback auf Rohzeiten, falls keine Korr-Zeit berechnet werden konnte.
            $istStdFloat = null;

            if ($kommenKorr !== null && $gehenKorr !== null) {
                try {
                    $k = new \DateTimeImmutable($kommenKorr);
                    $g = new \DateTimeImmutable($gehenKorr);
                    if ($g > $k) {
                        if (!$pauseOverrideAktiv && $pauseMinDb <= 0) {
                            $res = $this->pausenService->berechnePausenMinutenUndEntscheidungFuerBlock($k, $g);
                            $pauseMin = (int)($res['pause_minuten'] ?? 0);
                            $pauseStd = $pauseMin / 60.0;
                            $pauseEntscheidungNoetig = (bool)($res['entscheidung_noetig'] ?? false);
                            $pauseAutoMin = $pauseEntscheidungNoetig ? (int)($res['auto_pause_minuten'] ?? 0) : 0;
                        }
                        $istStdFloat = (($g->getTimestamp() - $k->getTimestamp()) / 3600.0) - $pauseStd;
                    }
                } catch (\Throwable $e) {
                    $istStdFloat = null;
                }
            }

            if ($istStdFloat === null && $kommenRoh !== null && $gehenRoh !== null) {
                try {
                    $k = new \DateTimeImmutable($kommenRoh);
                    $g = new \DateTimeImmutable($gehenRoh);
                    if ($g > $k) {
                        if (!$pauseOverrideAktiv && $pauseMinDb <= 0) {
                            $res = $this->pausenService->berechnePausenMinutenUndEntscheidungFuerBlock($k, $g);
                            $pauseMin = (int)($res['pause_minuten'] ?? 0);
                            $pauseStd = $pauseMin / 60.0;
                            $pauseEntscheidungNoetig = (bool)($res['entscheidung_noetig'] ?? false);
                            $pauseAutoMin = $pauseEntscheidungNoetig ? (int)($res['auto_pause_minuten'] ?? 0) : 0;
                        }
                        $istStdFloat = (($g->getTimestamp() - $k->getTimestamp()) / 3600.0) - $pauseStd;
                    }
                } catch (\Throwable $e) {
                    $istStdFloat = null;
                }
            }

            if ($istStdFloat !== null) {
                if ($istStdFloat < 0) {
                    $istStdFloat = 0.0;
                }
                $istStunden = sprintf('%.2f', $istStdFloat);
            }

            // Stunden-Spalten (für Arbeitszeitliste/PDF)
            $arztStunden       = (string)($tw['arzt_stunden'] ?? '0.00');
            $krankLfzStunden   = (string)($tw['krank_lfz_stunden'] ?? '0.00');
            $krankKkStunden    = (string)($tw['krank_kk_stunden'] ?? '0.00');
            $feiertagStunden   = (string)($tw['feiertag_stunden'] ?? '0.00');
            $kurzarbeitStunden = (string)($tw['kurzarbeit_stunden'] ?? '0.00');
            $urlaubStunden     = (string)($tw['urlaub_stunden'] ?? '0.00');
            $sonstigeStunden   = (string)($tw['sonstige_stunden'] ?? '0.00');

            $kennArzt       = (int)($tw['kennzeichen_arzt'] ?? 0);
            $kennFeiertag   = (int)($tw['kennzeichen_feiertag'] ?? 0);
            $kennUrlaub     = (int)($tw['kennzeichen_urlaub'] ?? 0);
            $kennKrankLfz   = (int)($tw['kennzeichen_krank_lfz'] ?? 0);
            $kennKrankKk    = (int)($tw['kennzeichen_krank_kk'] ?? 0);
            $kennKurzarbeit = (int)($tw['kennzeichen_kurzarbeit'] ?? 0);
            $kennSonstiges  = (int)($tw['kennzeichen_sonstiges'] ?? 0);

            // Kürzel/Begründung (z.B. BF, SoU)
            $kommentar = null;
            if (array_key_exists('kommentar', $tw)) {
                $k = trim((string)$tw['kommentar']);
                if ($k !== '') {
                    $kommentar = $k;
                }
            }

            $tagestyp = 'Arbeitstag';
            if ($kennFeiertag === 1) {
                $tagestyp = 'Feiertag';
            } elseif ($kennUrlaub === 1) {
                $tagestyp = 'Urlaub';
            } elseif ($kennArzt === 1) {
                $tagestyp = 'Arzt';
            } elseif ($kennKrankLfz === 1 || $kennKrankKk === 1) {
                $tagestyp = 'Krank';
            } elseif ($kennKurzarbeit === 1) {
                $tagestyp = 'Kurzarbeit';
            } elseif ($kennSonstiges === 1) {
                $tagestyp = 'Sonstiges';
            }


            $istBetriebsferien = isset($betriebsferienTage[$datum]);
            if ($tagestyp === 'Arbeitstag' && $istBetriebsferien) {
                $tagestyp = 'Betriebsferien';
            }

            $tageswerte[] = [
                'datum'               => $datum,

                // Kürzel/Begründung (z.B. BF, SoU)
                'kommentar'           => $kommentar,

                // Manuelle Korrekturen (für UI-Markierung)
                'felder_manuell_geaendert' => $felderManuell,

                // Manuell geänderte Zeitbuchungen (Kommen/Gehen)
                'zeit_manuell_geaendert'   => $zeitManuell ? 1 : 0,

                // Arbeitszeit / Kernwerte (Ist/Pause)
                // - Ist wird ggf. aus gerundeten Zeiten abgeleitet (Kommen/Gehen korrigiert)
                'arbeitszeit_stunden' => $istStunden,
                'pausen_stunden'      => sprintf('%.2f', $pauseStd),
                'pause_entscheidung_noetig' => $pauseEntscheidungNoetig ? 1 : 0,
                'pause_entscheidung_auto_minuten' => $pauseEntscheidungNoetig ? max(0, (int)$pauseAutoMin) : 0,

                // Abwesenheits-/Sonderzeiten (für Arbeitszeitliste/PDF)
                'arzt_stunden'        => $arztStunden,
                'krank_lfz_stunden'   => $krankLfzStunden,
                'krank_kk_stunden'    => $krankKkStunden,
                'feiertag_stunden'    => $feiertagStunden,
                'kurzarbeit_stunden'  => $kurzarbeitStunden,
                'urlaub_stunden'      => $urlaubStunden,
                'sonstige_stunden'    => $sonstigeStunden,

                // Kennzeichen (UI/Export/PDF)
                'kennzeichen_arzt'        => $kennArzt,
                'kennzeichen_krank_lfz'   => $kennKrankLfz,
                'kennzeichen_krank_kk'    => $kennKrankKk,
                'kennzeichen_feiertag'    => $kennFeiertag,
                'kennzeichen_kurzarbeit'  => $kennKurzarbeit,
                'kennzeichen_urlaub'      => $kennUrlaub,
                'kennzeichen_sonstiges'   => $kennSonstiges,

                // Tages-Saldo wird derzeit noch nicht berechnet
                'saldo_stunden'       => '',
                'tagestyp'            => $tagestyp,
                'ist_betriebsferien'  => ($istBetriebsferien ?? false),

                // Kommen/Gehen Zusatzinfos für Anzeige
                'kommen_roh'          => $kommenRoh,
                'gehen_roh'           => $gehenRoh,
                // Korr-Werte: berechnet (nicht gespeichert) – für Auswertung/Anzeige
                'kommen_korr'         => $kommenKorr,
                'gehen_korr'          => $gehenKorr,
            ];
        }
        }

        // Mikro-Buchungen (z. B. 0,01h durch versehentliches Doppel-Stempeln) sollen im Report
        // nicht als "gearbeitet" gewertet werden, da sie sonst Betriebsferien/Feiertage/Urlaub
        // unabsichtlich blockieren.
        $tageswerte = $this->filtereMicroArbeitszeitenVorReport($tageswerte);

        // UI-Erwartung: Monatsübersicht immer als vollständiges Monatsraster anzeigen.
        // Auch wenn `tageswerte_mitarbeiter` nur einzelne Tage enthält.
        $tageswerte = $this->komplettiereMonatsraster($tageswerte, $start, $betriebsferienTage);

        // Arbeitsbloecke (Rohbuchungen) je Tag: Basis fuer Mehrfach-Kommen/Gehen und echte Stunden-Summe.
        // Wichtig: Wir berechnen hier **Summe der Bloecke** (nicht Min/Max ueber den ganzen Tag),
        // damit Pausen/Unterbrechungen nicht als Arbeitszeit zaehlen.
        $arbeitsBloeckeProTag = $this->holeArbeitsbloeckeProTagFuerMonat($mitarbeiterId, $start);

        foreach ($tageswerte as $i => $row) {
            $datum = (string)($row['datum'] ?? '');
            $bloecke = ($datum !== '' && isset($arbeitsBloeckeProTag[$datum])) ? $arbeitsBloeckeProTag[$datum] : [];
            $tageswerte[$i]['arbeitsbloecke'] = $bloecke;

            // Wenn der Tag zuvor als "Mikro-Arbeitszeit" komplett ignoriert wurde, ueberschreiben wir nichts.
            if (!empty($row['micro_arbeitszeit_ignoriert'])) {
                continue;
            }

            // IST-Stunden aus Arbeitsbloecken summieren (Mikro-Bloecke werden nicht als Arbeit gewertet).
            $sumSek = 0;
            foreach ($bloecke as $b) {
                $kStr = (string)($b['kommen_korr'] ?? $b['kommen_roh'] ?? '');
                $gStr = (string)($b['gehen_korr'] ?? $b['gehen_roh'] ?? '');
                if ($kStr === '' || $gStr === '') {
                    continue;
                }

                try {
                    $k = new DateTimeImmutable($kStr);
                    $g = new DateTimeImmutable($gStr);
                } catch (Throwable $e) {
                    continue;
                }

                if ($g <= $k) {
                    continue;
                }

                $durSek = $g->getTimestamp() - $k->getTimestamp();
                $durStd = $durSek / 3600.0;

                if ($durStd < self::MICRO_ARBEITSZEIT_GRENZE_STUNDEN) {
                    continue;
                }

                $sumSek += $durSek;
            }

            if ($sumSek <= 0) {
                continue;
            }

            $pauseStd = (float)str_replace(',', '.', (string)($row['pausen_stunden'] ?? '0'));
            if ($pauseStd < 0) {
                $pauseStd = 0.0;
            }

            $istStd = $sumSek / 3600.0;
            $istNet = $istStd - $pauseStd;
            if ($istNet < 0) {
                $istNet = 0.0;
            }

            $tageswerte[$i]['arbeitszeit_stunden'] = sprintf('%.2f', round($istNet, 2));
            $tageswerte[$i]['ist_aus_bloecken'] = 1;
        }


        // Kurzarbeit-Plan (firmenweit/mitarbeiterbezogen) für die Anzeige anwenden.
        // Wichtig: Das ist reine Anzeige-Logik – es wird nichts in der DB gespeichert.
        $wochenarbeitszeit = 0.0;
        try {
            $m = $this->mitarbeiterModel->holeNachId($mitarbeiterId);
            if (is_array($m) && isset($m['wochenarbeitszeit'])) {
                $wochenarbeitszeit = (float)str_replace(',', '.', (string)$m['wochenarbeitszeit']);
            }
        } catch (\Throwable $e) {
            $wochenarbeitszeit = 0.0;
        }

        $tagesSoll = 0.0;
        if ($wochenarbeitszeit > 0) {
            $tagesSoll = $wochenarbeitszeit / 5.0;
        }

        // Kalender-Feiertage für die Anzeige anwenden (reine Anzeige-Logik, keine DB-Änderung).
        // Wichtig: Feiertage sollen in der Arbeitszeitliste/PDF als bezahlte Abwesenheit mit Stunden erscheinen.
        $feiertagStdDefault = ($tagesSoll > 0.0) ? $tagesSoll : self::FEIERTAG_DEFAULT_STUNDEN;

        foreach ($tageswerte as $i => $row) {
            $datum = (string)($row['datum'] ?? '');
            if ($datum === '') {
                continue;
            }

            try {
                $dt = new \DateTimeImmutable($datum);
            } catch (\Throwable $e) {
                continue;
            }

            $wochentag = (int)$dt->format('N');
            if ($wochentag >= 6) {
                continue;
            }

            if (!$this->feiertagService->istFeiertag($dt, null)) {
                continue;
            }

            // Andere Kennzeichen (Krank/Arzt/Sonstiges) schützen – Feiertag überschreibt diese nicht automatisch.
            $kennArzt     = (int)($row['kennzeichen_arzt'] ?? 0);
            $kennKrankLfz = (int)($row['kennzeichen_krank_lfz'] ?? 0);
            $kennKrankKk  = (int)($row['kennzeichen_krank_kk'] ?? 0);
            $kennSonst    = (int)($row['kennzeichen_sonstiges'] ?? 0);
            if ($kennArzt === 1 || $kennKrankLfz === 1 || $kennKrankKk === 1 || $kennSonst === 1) {
                continue;
            }

            // Kalender-Feiertag erzwingt Tagestyp/Kennzeichen.
            $row['kennzeichen_feiertag'] = 1;
            $row['tagestyp'] = 'Feiertag';

            // Urlaub (auch Betriebsferien/Antrag) soll an Feiertagen nicht zählen.
            $row['kennzeichen_urlaub'] = 0;
            $row['urlaub_stunden'] = '0.00';

            // Nur wenn keine Arbeitszeit vorhanden ist, setzen wir Feiertagsstunden als bezahlte Abwesenheit.
            $istF = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
            if ($istF <= 0.01) {
                $ftF = (float)str_replace(',', '.', (string)($row['feiertag_stunden'] ?? '0'));
                if ($ftF <= 0.01) {
                    $row['feiertag_stunden'] = sprintf('%.2f', $feiertagStdDefault);
                }
            } else {
                // Bei Arbeitszeit am Feiertag sollen keine zusaetzlichen Feiertagsstunden gezaehlt werden.
                $row['feiertag_stunden'] = sprintf('%.2f', 0.0);
            }

            $tageswerte[$i] = $row;
        }


        // Krankzeitraum (LFZ/KK) für die Anzeige anwenden (reine Anzeige-Logik, keine DB-Änderung).
        // Wichtig: Krank hat Vorrang vor Kurzarbeit (Kurzarbeit wird später übersprungen, wenn krank gesetzt ist).
        $tageswerte = $this->krankzeitraumService->wendeZeitraeumeAufTageswerteAn(
            $tageswerte,
            $mitarbeiterId,
            $start,
            $tagesSoll
        );

        $tageswerte = $this->kurzarbeitService->wendePlanAufTageswerteAn(
            $tageswerte,
            $mitarbeiterId,
            $start,
            $tagesSoll
        );

        // Kurzarbeit-Volltage (i. d. R. 8h bzw. Tages-Soll) sollen sich wie Betriebsferien verhalten:
        // - an Arbeitstagen als Kurzarbeit-Stunden vorhanden,
        // - aber NICHT zusätzlich, wenn an dem Tag gearbeitet wurde.
        $volltagKurzarbeitStd = ($tagesSoll > 0.0) ? $tagesSoll : self::KURZARBEIT_DEFAULT_VOLLTAG_STUNDEN;
        $tageswerte = $this->wendeKurzarbeitVolltagWieBetriebsferienAn($tageswerte, $volltagKurzarbeitStd);

        // Monatswerte fuer Anzeige verlässlich aus Tageswerten ableiten:
        // - IST: Arbeitszeit + bezahlte Abwesenheiten (Arzt/Krank/Feiertag/Urlaub/Sonstiges)
        // - Kurzarbeit reduziert das Soll, zaehlt aber **nicht** als IST (MasterPrompt).
        $felderIst = [
            'arbeitszeit_stunden',
            'arzt_stunden',
            'krank_lfz_stunden',
            'krank_kk_stunden',
            'feiertag_stunden',
            'urlaub_stunden',
            'sonstige_stunden',
        ];

        $istSumme = 0.0;
        foreach ($tageswerte as $t) {
            foreach ($felderIst as $f) {
                $istSumme += (float)str_replace(',', '.', (string)($t[$f] ?? '0'));
            }
        }
        $istSumme = round($istSumme, 2);

        // Basis-Soll (Wochentage * Tages-Soll) minus Kurzarbeit-Reduktion (nur Mo-Fr, nicht Feiertag).
        $baseSollFallback = $this->berechneSollstundenFallback($mitarbeiterId, $start, $betriebsferienTage);

        $kurzarbeitReduktion = 0.0;
        foreach ($tageswerte as $t) {
            $datum = (string)($t['datum'] ?? '');
            if ($datum === '') {
                continue;
            }

            try {
                $dt = new \DateTimeImmutable($datum);
            } catch (\Throwable $e) {
                continue;
            }

            $wochentag = (int)$dt->format('N');
            if ($wochentag >= 6) {
                continue;
            }
            if ($this->feiertagService->istFeiertag($dt, null)) {
                continue;
            }

            $kurzarbeitReduktion += (float)str_replace(',', '.', (string)($t['kurzarbeit_stunden'] ?? '0'));
        }

        $kurzarbeitReduktion = round($kurzarbeitReduktion, 2);
        if ($kurzarbeitReduktion > $baseSollFallback) {
            $kurzarbeitReduktion = $baseSollFallback;
        }

        $sollCalc = round($baseSollFallback - $kurzarbeitReduktion, 2);
        if ($sollCalc < 0) {
            $sollCalc = 0.0;
        }

        // Wenn keine Monatsaggregation vorhanden ist, liefern wir eine einfache Fallback-Berechnung.
        // Dadurch bleibt die Monatsübersicht im Backend nutzbar, auch wenn Cron/Batch noch fehlt.
        if ($monatswerte === null) {
            $diffCalc = round($istSumme - $sollCalc, 2);

            $monatswerte = [
                'sollstunden'        => sprintf('%.2f', $sollCalc),
                'iststunden'         => sprintf('%.2f', $istSumme),
                'differenzstunden'   => sprintf('%.2f', $diffCalc),
                'urlaub_genommen'    => '0.00',
                'urlaub_verbleibend' => '0.00',
                'ist_fallback'       => true,
            ];
        } else {
            // Falls Monatswerte vorhanden sind, korrigieren wir die Anzeige-Werte
            // (IST/Differenz und ggf. Soll bei Kurzarbeit), damit Monatszeile und Tagesliste konsistent sind.
            $sollShow = (float)str_replace(',', '.', (string)($monatswerte['sollstunden'] ?? '0'));
            $istDb    = (float)str_replace(',', '.', (string)($monatswerte['iststunden'] ?? '0'));

            $override = false;

            // Wenn Soll aus der DB fehlt/0 ist, verwenden wir die berechnete Variante.
            if ($sollShow <= 0.0) {
                $sollShow = $sollCalc;
                $override = true;
            } else {
                // Bei Kurzarbeit: Wenn DB-Soll offensichtlich noch nicht reduziert ist, reduzieren wir fuer die Anzeige.
                if ($kurzarbeitReduktion > 0.01) {
                    $baseSoll = $baseSollFallback;
                    if (abs($sollShow - $baseSoll) <= 0.02) {
                        $sollShow = round($sollShow - $kurzarbeitReduktion, 2);
                        if ($sollShow < 0) {
                            $sollShow = 0.0;
                        }
                        $override = true;
                    }
                }
            }

            $diffShow = round($istSumme - $sollShow, 2);

            if (abs($istDb - $istSumme) > 0.02) {
                $override = true;
            }

            $diffDb = (float)str_replace(',', '.', (string)($monatswerte['differenzstunden'] ?? '0'));
            if (abs($diffDb - $diffShow) > 0.02) {
                $override = true;
            }

            if ($override) {
                $monatswerte['sollstunden']      = sprintf('%.2f', $sollShow);
                $monatswerte['iststunden']       = sprintf('%.2f', $istSumme);
                $monatswerte['differenzstunden'] = sprintf('%.2f', $diffShow);
                $monatswerte['ist_fallback']     = true;
            }
        }

        return [
            'mitarbeiter_id' => $mitarbeiterId,
            'jahr'           => $jahr,
            'monat'          => $monat,
            'monatswerte'    => $monatswerte,
            'tageswerte'     => $tageswerte,
        ];
}
}
