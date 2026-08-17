<?php
declare(strict_types=1);

/**
 * FachpruefungService
 *
 * Die Fachprüfungen, die nachrechnen statt nur zu zeigen: Monatsraster,
 * Doppelzählung und Feiertag-plus-Arbeitszeit. Sie lagen als `private` in
 * `SmokeTestController` und waren damit nur über Login, Browser und eine
 * handgetippte Mitarbeiter-ID erreichbar (T-140, P-2026-08-17-13).
 *
 * Zwei Aufrufer, eine Logik:
 * - `SmokeTestController` liest weiter `$_POST` und delegiert hierher.
 * - `scripts/dev/pruefe_fachlogik.php` ruft dieselben Methoden auf der
 *   Kommandozeile auf.
 *
 * **Die Rümpfe sind unverändert übernommen.** Was hier fehlt, ist allein das
 * Einlesen von `$_POST` – das ist Sache des Aufrufers, nicht der Prüfung. Das
 * Rückgabebündel (`ergebnis`, `hinweis`, dazu die drei Eingabewerte) ist
 * dasselbe, damit die Views in `views/smoke_test/` unberührt bleiben.
 *
 * Zielbild und Akzeptanzkriterien: `docs/spezifikation_fachlogik_pruefskript.md`.
 */
class FachpruefungService
{
    private static ?FachpruefungService $instanz = null;

    private ?Database $db = null;

    /**
     * Wie im `SmokeTestController`: Eine fehlende Datenbank ist hier kein
     * Abbruch, sondern ein Befund. Die Prüfungen melden ihn als `hinweis`.
     */
    private function __construct()
    {
        try {
            $this->db = Database::getInstanz();
        } catch (Throwable $e) {
            $this->db = null;
        }
    }

    public static function getInstanz(): FachpruefungService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }


    /**
     * Monatsraster-Check: genau ein Tageswert je Kalendertag?
     *
     * @return array{mitarbeiter_id:int, jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeMonatsraster(int $monatsrasterTestMitarbeiterId, int $monatsrasterTestJahr, int $monatsrasterTestMonat): array
    {
        $monatsrasterTestErgebnis = null;
        $monatsrasterTestHinweis = null;

        if ($monatsrasterTestMitarbeiterId <= 0) {
            $monatsrasterTestHinweis = 'Keine gültige Mitarbeiter-ID für den Monatsreport-Raster-Check.';
        } elseif ($monatsrasterTestMonat < 1 || $monatsrasterTestMonat > 12) {
            $monatsrasterTestHinweis = 'Monat muss 1..12 sein.';
        } elseif ($monatsrasterTestJahr < 1970 || $monatsrasterTestJahr > 2100) {
            $monatsrasterTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
        } elseif (!class_exists('ReportService')) {
            $monatsrasterTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $monatsrasterTestJahr, $monatsrasterTestMonat));
                $tageImMonat = (int)$start->format('t');

                $rs = ReportService::getInstanz();
                $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($monatsrasterTestMitarbeiterId, $monatsrasterTestJahr, $monatsrasterTestMonat);
                $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];

                if (!is_array($tageswerte)) {
                    $tageswerte = [];
                }

                $seen = [];
                $invalid = [];
                $dups = [];

                foreach ($tageswerte as $tw) {
                    if (!is_array($tw)) {
                        continue;
                    }
                    $d = (string)($tw['datum'] ?? '');
                    if ($d === '') {
                        $invalid[] = '(leer)';
                        continue;
                    }
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                        $invalid[] = $d;
                        continue;
                    }
                    if (isset($seen[$d])) {
                        $dups[$d] = ($dups[$d] ?? 1) + 1;
                    }
                    $seen[$d] = true;
                }

                $missing = [];
                for ($i = 1; $i <= $tageImMonat; $i++) {
                    $d = sprintf('%04d-%02d-%02d', $monatsrasterTestJahr, $monatsrasterTestMonat, $i);
                    if (!isset($seen[$d])) {
                        $missing[] = $d;
                    }
                }

                $vorhandenCount = count($tageswerte);
                $ok = ($vorhandenCount === $tageImMonat && $missing === [] && $dups === [] && $invalid === []);

                $dupList = [];
                foreach ($dups as $d => $c) {
                    $dupList[] = (string)$d . ' (' . (int)$c . 'x)';
                }

                $monatsrasterTestErgebnis = [
                    'ok' => $ok,
                    'mitarbeiter_id' => $monatsrasterTestMitarbeiterId,
                    'jahr' => $monatsrasterTestJahr,
                    'monat' => $monatsrasterTestMonat,
                    'tage_im_monat' => $tageImMonat,
                    'tageswerte_count' => $vorhandenCount,
                    'missing' => array_slice($missing, 0, 10),
                    'missing_count' => count($missing),
                    'duplicates' => array_slice($dupList, 0, 10),
                    'duplicates_count' => count($dups),
                    'invalid' => array_slice($invalid, 0, 10),
                    'invalid_count' => count($invalid),
                ];
            } catch (Throwable $e) {
                $monatsrasterTestHinweis = 'Monatsreport-Raster-Check Fehler: ' . $e->getMessage();
            }
        }

        return [
            'mitarbeiter_id' => $monatsrasterTestMitarbeiterId,
            'jahr' => $monatsrasterTestJahr,
            'monat' => $monatsrasterTestMonat,
            'ergebnis' => $monatsrasterTestErgebnis,
            'hinweis' => $monatsrasterTestHinweis,
        ];
    }

    /**
     * Doppelzählung-Check: Betriebsferien und Kurzarbeit-Volltag neben Arbeitszeit.
     *
     * @return array{mitarbeiter_id:int, jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeDoppelzaehlung(int $doppelzaehlungTestMitarbeiterId, int $doppelzaehlungTestJahr, int $doppelzaehlungTestMonat): array
    {
        $doppelzaehlungTestErgebnis = null;
        $doppelzaehlungTestHinweis = null;

        if ($doppelzaehlungTestMitarbeiterId <= 0) {
            $doppelzaehlungTestHinweis = 'Keine gültige Mitarbeiter-ID für den Doppelzählung-Check.';
        } elseif ($doppelzaehlungTestMonat < 1 || $doppelzaehlungTestMonat > 12) {
            $doppelzaehlungTestHinweis = 'Monat muss 1..12 sein.';
        } elseif ($doppelzaehlungTestJahr < 1970 || $doppelzaehlungTestJahr > 2100) {
            $doppelzaehlungTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
        } elseif ($this->db === null) {
            $doppelzaehlungTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
        } elseif (!class_exists('ReportService')) {
            $doppelzaehlungTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $parseFloat = static function ($v): float {
                    if ($v === null) {
                        return 0.0;
                    }
                    $s = trim((string)$v);
                    if ($s === '') {
                        return 0.0;
                    }
                    $s = str_replace(',', '.', $s);
                    return (float)$s;
                };

                $rs = ReportService::getInstanz();
                $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($doppelzaehlungTestMitarbeiterId, $doppelzaehlungTestJahr, $doppelzaehlungTestMonat);
                $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];
                if (!is_array($tageswerte)) {
                    $tageswerte = [];
                }

                $volltagSchwelle = 7.99; // 8h-Fallback/Volltag (Toleranz)

                $totalBetriebsferien = 0;
                $totalKurzVolltag = 0;

                $issues = [];

                foreach ($tageswerte as $tw) {
                    if (!is_array($tw)) {
                        continue;
                    }
                    $datum = (string)($tw['datum'] ?? '');
                    if ($datum === '') {
                        continue;
                    }

                    $arb = $parseFloat($tw['arbeitszeit_stunden'] ?? '0');
                    $urlaub = $parseFloat($tw['urlaub_stunden'] ?? '0');
                    $kurz = $parseFloat($tw['kurzarbeit_stunden'] ?? '0');

                    $istBf = ((bool)($tw['ist_betriebsferien'] ?? false) === true);
                    $kennKurz = (int)($tw['kennzeichen_kurzarbeit'] ?? 0);

                    if ($istBf) {
                        $totalBetriebsferien++;
                    }
                    if ($kennKurz === 1 && $kurz >= $volltagSchwelle) {
                        $totalKurzVolltag++;
                    }

                    if ($arb > 0.01) {
                        if ($istBf && $urlaub > 0.01) {
                            $issues[] = [
                                'datum' => $datum,
                                'typ' => 'Betriebsferien',
                                'arbeitszeit' => $arb,
                                'urlaub' => $urlaub,
                                'kurzarbeit' => $kurz,
                                'hinweis' => 'Arbeitszeit > 0, aber Betriebsferien-Urlaub > 0 (Doppelzählung möglich)',
                            ];
                        }

                        if ($kennKurz === 1 && $kurz >= $volltagSchwelle) {
                            $issues[] = [
                                'datum' => $datum,
                                'typ' => 'Kurzarbeit-Volltag',
                                'arbeitszeit' => $arb,
                                'urlaub' => $urlaub,
                                'kurzarbeit' => $kurz,
                                'hinweis' => 'Arbeitszeit > 0, aber Kurzarbeit-Volltag aktiv (Doppelzählung möglich)',
                            ];
                        }
                    }
                }

                $ok = true;
                if ($totalBetriebsferien <= 0 && $totalKurzVolltag <= 0) {
                    $ok = null;
                    $doppelzaehlungTestHinweis = 'Keine Betriebsferien- oder Kurzarbeit-Volltag-Tage im Monatsreport gefunden – Check ist in diesem Monat nicht aussagekräftig.';
                } else {
                    $ok = (count($issues) === 0);
                }

                $doppelzaehlungTestErgebnis = [
                    'ok' => $ok,
                    'mitarbeiter_id' => $doppelzaehlungTestMitarbeiterId,
                    'jahr' => $doppelzaehlungTestJahr,
                    'monat' => $doppelzaehlungTestMonat,
                    'betriebsferien_tage' => $totalBetriebsferien,
                    'kurzarbeit_volltag_tage' => $totalKurzVolltag,
                    'issues_count' => count($issues),
                    'issues' => array_slice($issues, 0, 12),
                ];
            } catch (Throwable $e) {
                $doppelzaehlungTestHinweis = 'Doppelzählung-Check Fehler: ' . $e->getMessage();
            }
        }

        return [
            'mitarbeiter_id' => $doppelzaehlungTestMitarbeiterId,
            'jahr' => $doppelzaehlungTestJahr,
            'monat' => $doppelzaehlungTestMonat,
            'ergebnis' => $doppelzaehlungTestErgebnis,
            'hinweis' => $doppelzaehlungTestHinweis,
        ];
    }

    /**
     * Feiertag+Arbeitszeit-Check: Feiertagsstunden neben Arbeitszeit am selben Tag.
     *
     * @return array{mitarbeiter_id:int, jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeFeiertagUndArbeitszeit(int $feiertagArbeitszeitTestMitarbeiterId, int $feiertagArbeitszeitTestJahr, int $feiertagArbeitszeitTestMonat): array
    {
        $feiertagArbeitszeitTestErgebnis = null;
        $feiertagArbeitszeitTestHinweis = null;

        if ($feiertagArbeitszeitTestMitarbeiterId <= 0) {
            $feiertagArbeitszeitTestHinweis = 'Keine gültige Mitarbeiter-ID für den Feiertag+Arbeitszeit-Check.';
        } elseif ($feiertagArbeitszeitTestMonat < 1 || $feiertagArbeitszeitTestMonat > 12) {
            $feiertagArbeitszeitTestHinweis = 'Monat muss 1..12 sein.';
        } elseif ($feiertagArbeitszeitTestJahr < 1970 || $feiertagArbeitszeitTestJahr > 2100) {
            $feiertagArbeitszeitTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
        } elseif ($this->db === null) {
            $feiertagArbeitszeitTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
        } elseif (!class_exists('ReportService')) {
            $feiertagArbeitszeitTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $parseFloat = static function ($v): float {
                    if ($v === null) {
                        return 0.0;
                    }
                    $s = trim((string)$v);
                    if ($s === '') {
                        return 0.0;
                    }
                    $s = str_replace(',', '.', $s);
                    return (float)$s;
                };

                $rs = ReportService::getInstanz();
                $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($feiertagArbeitszeitTestMitarbeiterId, $feiertagArbeitszeitTestJahr, $feiertagArbeitszeitTestMonat);
                $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];
                if (!is_array($tageswerte)) {
                    $tageswerte = [];
                }

                $totalFeiertage = 0;
                $issues = [];

                foreach ($tageswerte as $tw) {
                    if (!is_array($tw)) {
                        continue;
                    }
                    $datum = (string)($tw['datum'] ?? '');
                    if ($datum === '') {
                        continue;
                    }

                    $kennFeiertag = (int)($tw['kennzeichen_feiertag'] ?? 0);
                    if ($kennFeiertag !== 1) {
                        continue;
                    }
                    $totalFeiertage++;

                    $arb = $parseFloat($tw['arbeitszeit_stunden'] ?? '0');
                    $ft = $parseFloat($tw['feiertag_stunden'] ?? '0');

                    if ($arb > 0.01 && $ft > 0.01) {
                        $issues[] = [
                            'datum' => $datum,
                            'arbeitszeit' => $arb,
                            'feiertag' => $ft,
                            'hinweis' => 'Arbeitszeit > 0, aber Feiertagsstunden > 0 (Doppelzählung möglich)',
                        ];
                    }
                }

                $ok = true;
                if ($totalFeiertage <= 0) {
                    $ok = null;
                    $feiertagArbeitszeitTestHinweis = 'Keine Feiertage im Monatsreport gefunden – Check ist in diesem Monat nicht aussagekräftig.';
                } else {
                    $ok = (count($issues) === 0);
                }

                $feiertagArbeitszeitTestErgebnis = [
                    'ok' => $ok,
                    'mitarbeiter_id' => $feiertagArbeitszeitTestMitarbeiterId,
                    'jahr' => $feiertagArbeitszeitTestJahr,
                    'monat' => $feiertagArbeitszeitTestMonat,
                    'feiertag_tage' => $totalFeiertage,
                    'issues_count' => count($issues),
                    'issues' => array_slice($issues, 0, 12),
                ];
            } catch (Throwable $e) {
                $feiertagArbeitszeitTestHinweis = 'Feiertag+Arbeitszeit-Check Fehler: ' . $e->getMessage();
            }
        }

        return [
            'mitarbeiter_id' => $feiertagArbeitszeitTestMitarbeiterId,
            'jahr' => $feiertagArbeitszeitTestJahr,
            'monat' => $feiertagArbeitszeitTestMonat,
            'ergebnis' => $feiertagArbeitszeitTestErgebnis,
            'hinweis' => $feiertagArbeitszeitTestHinweis,
        ];
    }

    /**
     * Feiertag-Check fuer einen einzelnen Tag: erkennt der Report ihn als
     * Feiertag, und passt die Arbeitszeit dazu?
     *
     * @return array{mitarbeiter_id:int, datum:string, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeFeiertagQuick(int $feiertagTestMitarbeiterId, string $feiertagTestDatum): array
    {
        $feiertagTestErgebnis = null;
        $feiertagTestHinweis = null;

        if ($feiertagTestMitarbeiterId <= 0) {
            $feiertagTestHinweis = 'Keine gültige Mitarbeiter-ID für den Feiertag-Check (auch kein angemeldeter Mitarbeiter gefunden).';
        } else {
            // Datum robust parsen
            try {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $feiertagTestDatum)) {
                    throw new Exception('Datum bitte im Format YYYY-MM-DD angeben.');
                }
                $dt = new DateTimeImmutable($feiertagTestDatum);
            } catch (Throwable $e) {
                $feiertagTestHinweis = 'Ungültiges Datum: ' . $e->getMessage();
                $dt = null;
            }

            if ($feiertagTestHinweis === null && $dt instanceof DateTimeImmutable) {
                if (!class_exists('ReportService')) {
                    $feiertagTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
                } else {
                    $jahr = (int)$dt->format('Y');
                    $monat = (int)$dt->format('n');
                    $wochentag = (int)$dt->format('N');

                    $istFeiertag = null;
                    try {
                        $fs = FeiertagService::getInstanz();
                        $istFeiertag = $fs->istFeiertag($dt, null);
                    } catch (Throwable $e) {
                        $istFeiertag = null;
                    }

                    try {
                        $rs = ReportService::getInstanz();
                        $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($feiertagTestMitarbeiterId, $jahr, $monat);
                        $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];

                        $row = null;
                        if (is_array($tageswerte)) {
                            foreach ($tageswerte as $tw) {
                                if (!is_array($tw)) {
                                    continue;
                                }
                                if ((string)($tw['datum'] ?? '') === $dt->format('Y-m-d')) {
                                    $row = $tw;
                                    break;
                                }
                            }
                        }

                        if (!is_array($row)) {
                            $feiertagTestHinweis = 'Kein Tageswert für das Datum im Monatsreport gefunden.';
                        } else {
                            $arbeits = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
                            $feier = (float)str_replace(',', '.', (string)($row['feiertag_stunden'] ?? '0'));
                            $kennF = (int)($row['kennzeichen_feiertag'] ?? 0);
                            $tagestyp = (string)($row['tagestyp'] ?? '');
                            $kommentar = (string)($row['kommentar'] ?? '');

                            $hatArbeit = ($arbeits > 0.01);
                            $ok = null;
                            $erwartung = '';

                            if ($istFeiertag === true && $wochentag < 6) {
                                if ($hatArbeit) {
                                    // Wenn gearbeitet wurde, erwarten wir zumindest das Kennzeichen.
                                    $erwartung = 'Feiertag erkannt; bei Arbeitszeit > 0 werden Feiertagsstunden ggf. 0 gelassen.';
                                    $ok = ($kennF === 1);
                                } else {
                                    $erwartung = 'Feiertag erkannt und ohne Arbeit: Feiertagsstunden > 0 und Kennzeichen gesetzt.';
                                    $ok = ($kennF === 1 && $feier > 0.01);
                                }
                            } elseif ($istFeiertag === true && $wochentag >= 6) {
                                $erwartung = 'Datum ist Feiertag, aber Wochenende: je nach Regel kann Feiertagsstunden 0 bleiben.';
                                $ok = null;
                            } elseif ($istFeiertag === false) {
                                $erwartung = 'Datum ist laut FeiertagService kein Feiertag.';
                                $ok = null;
                            } else {
                                $erwartung = 'FeiertagService nicht verfügbar oder Fehler.';
                                $ok = null;
                            }

                            $feiertagTestErgebnis = [
                                'ok' => $ok,
                                'datum' => $dt->format('Y-m-d'),
                                'wochentag' => $wochentag,
                                'ist_feiertag' => $istFeiertag,
                                'kennzeichen_feiertag' => $kennF,
                                'arbeitszeit_stunden' => $arbeits,
                                'feiertag_stunden' => $feier,
                                'tagestyp' => $tagestyp,
                                'kommentar' => $kommentar,
                                'erwartung' => $erwartung,
                            ];
                        }
                    } catch (Throwable $e) {
                        $feiertagTestHinweis = 'Feiertag-Check Fehler (Report): ' . $e->getMessage();
                    }
                }
            }
        }

        return [
            'mitarbeiter_id' => $feiertagTestMitarbeiterId,
            'datum' => $feiertagTestDatum,
            'ergebnis' => $feiertagTestErgebnis,
            'hinweis' => $feiertagTestHinweis,
        ];
    }

    /**
     * Fallback-Check: fuellt der Report Tage ohne Tageswerte aus den Buchungen?
     *
     * @return array{mitarbeiter_id:int, jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeMonatsfallback(int $monatsfallbackTestMitarbeiterId, int $monatsfallbackTestJahr, int $monatsfallbackTestMonat): array
    {
        $monatsfallbackTestErgebnis = null;
        $monatsfallbackTestHinweis = null;

        if ($monatsfallbackTestMitarbeiterId <= 0) {
            $monatsfallbackTestHinweis = 'Keine gültige Mitarbeiter-ID für den Monatsreport-Fallback-Check.';
        } elseif ($monatsfallbackTestMonat < 1 || $monatsfallbackTestMonat > 12) {
            $monatsfallbackTestHinweis = 'Monat muss 1..12 sein.';
        } elseif ($monatsfallbackTestJahr < 1970 || $monatsfallbackTestJahr > 2100) {
            $monatsfallbackTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
        } elseif ($this->db === null) {
            $monatsfallbackTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
        } elseif (!class_exists('ReportService')) {
            $monatsfallbackTestHinweis = 'ReportService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $monatsfallbackTestJahr, $monatsfallbackTestMonat));
                $bis = $start->modify('+1 month');

                // 1) Tage mit Buchungen ermitteln (inkl. Buchungsanzahl)
                $bookedMap = []; // datum => count
                $rowsB = $this->db->fetchAlle(
                    'SELECT DATE(zeitstempel) AS datum, COUNT(*) AS c
'
                    . 'FROM zeitbuchung
'
                    . 'WHERE mitarbeiter_id = :mid AND zeitstempel >= :von AND zeitstempel < :bis
'
                    . 'GROUP BY DATE(zeitstempel)
'
                    . 'ORDER BY datum ASC',
                    [
                        'mid' => $monatsfallbackTestMitarbeiterId,
                        'von' => $start->format('Y-m-d H:i:s'),
                        'bis' => $bis->format('Y-m-d H:i:s'),
                    ]
                );
                if (is_array($rowsB)) {
                    foreach ($rowsB as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $d = (string)($r['datum'] ?? '');
                        if ($d === '') {
                            continue;
                        }
                        $bookedMap[$d] = (int)($r['c'] ?? 0);
                    }
                }

                // 2) Tage mit Tageswerten ermitteln
                $twSet = [];
                $rowsTw = $this->db->fetchAlle(
                    'SELECT datum
'
                    . 'FROM tageswerte_mitarbeiter
'
                    . 'WHERE mitarbeiter_id = :mid AND datum >= :von AND datum < :bis
'
                    . 'ORDER BY datum ASC',
                    [
                        'mid' => $monatsfallbackTestMitarbeiterId,
                        'von' => $start->format('Y-m-d'),
                        'bis' => $bis->format('Y-m-d'),
                    ]
                );
                if (is_array($rowsTw)) {
                    foreach ($rowsTw as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $d = (string)($r['datum'] ?? '');
                        if ($d === '') {
                            continue;
                        }
                        $twSet[$d] = true;
                    }
                }

                $bookedDays = array_keys($bookedMap);
                $tageswerteDaysCount = count($twSet);

                $missingDays = [];
                foreach ($bookedDays as $d) {
                    if (!isset($twSet[$d])) {
                        $missingDays[] = $d;
                    }
                }

                // 3) Monatsreport laden und prüfen, ob Missing-Days per Fallback befüllt sind
                $rs = ReportService::getInstanz();
                $monatsdaten = $rs->holeMonatsdatenFuerMitarbeiter($monatsfallbackTestMitarbeiterId, $monatsfallbackTestJahr, $monatsfallbackTestMonat);
                $tageswerte = is_array($monatsdaten) ? ($monatsdaten['tageswerte'] ?? []) : [];
                if (!is_array($tageswerte)) {
                    $tageswerte = [];
                }

                $index = [];
                foreach ($tageswerte as $tw) {
                    if (!is_array($tw)) {
                        continue;
                    }
                    $d = (string)($tw['datum'] ?? '');
                    if ($d === '') {
                        continue;
                    }
                    $index[$d] = $tw;
                }

                $notCovered = [];
                $samples = [];

                foreach ($missingDays as $d) {
                    $row = $index[$d] ?? null;
                    $covered = false;

                    $kommen = '';
                    $gehen = '';
                    $az = 0.0;
                    $pz = 0.0;

                    if (is_array($row)) {
                        $kommen = trim((string)($row['kommen_roh'] ?? ''));
                        $gehen = trim((string)($row['gehen_roh'] ?? ''));
                        $az = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
                        $pz = (float)str_replace(',', '.', (string)($row['pausen_stunden'] ?? '0'));

                        $covered = ($kommen !== '' || $gehen !== '' || $az > 0.01 || $pz > 0.01);
                    }

                    if (!$covered) {
                        $notCovered[] = $d;
                    }

                    if (count($samples) < 8) {
                        $samples[] = [
                            'datum' => $d,
                            'buchungen' => (int)($bookedMap[$d] ?? 0),
                            'kommen_roh' => $kommen,
                            'gehen_roh' => $gehen,
                            'arbeitszeit_stunden' => $az,
                            'pausen_stunden' => $pz,
                            'covered' => $covered ? 1 : 0,
                        ];
                    }
                }

                $missingCount = count($missingDays);
                $notCoveredCount = count($notCovered);

                $ok = true;
                if ($missingCount > 0) {
                    $ok = ($notCoveredCount === 0);
                }

                if ($missingCount === 0) {
                    $monatsfallbackTestHinweis = 'Keine Tage mit Buchungen ohne Tageswerte gefunden – Fallback wird in diesem Monat nicht benötigt.';
                }

                $monatsfallbackTestErgebnis = [
                    'ok' => $ok,
                    'mitarbeiter_id' => $monatsfallbackTestMitarbeiterId,
                    'jahr' => $monatsfallbackTestJahr,
                    'monat' => $monatsfallbackTestMonat,
                    'booked_days_count' => count($bookedDays),
                    'tageswerte_days_count' => $tageswerteDaysCount,
                    'missing_days_count' => $missingCount,
                    'not_covered_count' => $notCoveredCount,
                    'missing_days_sample' => array_slice($missingDays, 0, 10),
                    'not_covered_sample' => array_slice($notCovered, 0, 10),
                    'samples' => $samples,
                ];
            } catch (Throwable $e) {
                $monatsfallbackTestHinweis = 'Monatsreport-Fallback-Check Fehler: ' . $e->getMessage();
            }
        }

        return [
            'mitarbeiter_id' => $monatsfallbackTestMitarbeiterId,
            'jahr' => $monatsfallbackTestJahr,
            'monat' => $monatsfallbackTestMonat,
            'ergebnis' => $monatsfallbackTestErgebnis,
            'hinweis' => $monatsfallbackTestHinweis,
        ];
    }

    /**
     * Buchungssequenz-Check: folgt auf jedes Kommen ein Gehen?
     *
     * @return array{mitarbeiter_id:int, jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeBuchungssequenz(int $buchungssequenzTestMitarbeiterId, int $buchungssequenzTestJahr, int $buchungssequenzTestMonat): array
    {
        $buchungssequenzTestErgebnis = null;
        $buchungssequenzTestHinweis = null;

        if ($buchungssequenzTestMitarbeiterId <= 0) {
            $buchungssequenzTestHinweis = 'Keine gültige Mitarbeiter-ID für den Sequenz-Check.';
        } elseif ($buchungssequenzTestMonat < 1 || $buchungssequenzTestMonat > 12) {
            $buchungssequenzTestHinweis = 'Monat muss 1..12 sein.';
        } elseif ($buchungssequenzTestJahr < 1970 || $buchungssequenzTestJahr > 2100) {
            $buchungssequenzTestHinweis = 'Jahr außerhalb des erwarteten Bereichs (1970..2100).';
        } elseif ($this->db === null) {
            $buchungssequenzTestHinweis = 'Database::getInstanz() ist nicht verfügbar.';
        } else {
            try {
                $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $buchungssequenzTestJahr, $buchungssequenzTestMonat));
                $bis = $start->modify('+1 month');
                $today = (new DateTimeImmutable('today'))->format('Y-m-d');

                $rows = $this->db->fetchAlle(
                    "SELECT id, typ, zeitstempel
                     FROM zeitbuchung
                     WHERE mitarbeiter_id = :mid AND zeitstempel >= :von AND zeitstempel < :bis
                     ORDER BY zeitstempel ASC, id ASC",
                    [
                        'mid' => $buchungssequenzTestMitarbeiterId,
                        'von' => $start->format('Y-m-d H:i:s'),
                        'bis' => $bis->format('Y-m-d H:i:s'),
                    ]
                );

                $byDay = []; // datum => list
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $ts = (string)($r['zeitstempel'] ?? '');
                        $typ = (string)($r['typ'] ?? '');
                        if ($ts === '' || ($typ !== 'kommen' && $typ !== 'gehen')) {
                            continue;
                        }
                        $d = substr($ts, 0, 10);
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                            continue;
                        }
                        $byDay[$d][] = [
                            'typ' => $typ,
                            'zeit' => substr($ts, 11, 5),
                        ];
                    }
                }

                if ($byDay === []) {
                    $buchungssequenzTestHinweis = 'Keine Zeitbuchungen im gewählten Monat gefunden.';
                }

                $auffaellig = [];
                $mehrblock = [];

                foreach ($byDay as $d => $list) {
                    if (!is_array($list) || $list === []) {
                        continue;
                    }

                    $types = [];
                    $times = [];
                    foreach ($list as $it) {
                        if (!is_array($it)) {
                            continue;
                        }
                        $types[] = (string)($it['typ'] ?? '');
                        $times[] = (string)($it['zeit'] ?? '');
                    }

                    $n = count($types);
                    if ($n === 0) {
                        continue;
                    }

                    $flags = [];
                    if ($types[0] !== 'kommen') {
                        $flags[] = 'start!=' . $types[0];
                    }
                    if (($n % 2) === 1) {
                        $flags[] = 'odd';
                    }

                    // Adjacent duplicates
                    for ($i = 1; $i < $n; $i++) {
                        if ($types[$i] === $types[$i - 1]) {
                            $flags[] = 'doppelt:' . $types[$i];
                            break;
                        }
                    }

                    // Pair scan
                    $open = false;
                    $pairs = 0;
                    $scanAnom = [];
                    foreach ($types as $t) {
                        if ($t === 'kommen') {
                            if ($open) {
                                $scanAnom[] = 'kommen_ohne_gehen';
                            }
                            $open = true;
                        } elseif ($t === 'gehen') {
                            if (!$open) {
                                $scanAnom[] = 'gehen_ohne_kommen';
                            } else {
                                $open = false;
                                $pairs++;
                            }
                        }
                    }

                    if ($open) {
                        // Offener Block nur als Fehler, wenn nicht "heute"
                        if ($d !== $today) {
                            $scanAnom[] = 'offen';
                        } else {
                            $flags[] = 'offen(heute)';
                        }
                    }

                    if ($scanAnom !== []) {
                        foreach ($scanAnom as $a) {
                            if (!in_array($a, $flags, true)) {
                                $flags[] = $a;
                            }
                        }
                    }

                    $isAuffaellig = ($scanAnom !== [] || ($types[0] !== 'kommen') || (($n % 2) === 1));

                    $seq = implode(' ', array_map(static fn(string $t): string => ($t === 'kommen' ? 'K' : 'G'), $types));
                    $timeStr = implode(' ', $times);

                    if ($isAuffaellig) {
                        $auffaellig[] = [
                            'datum' => $d,
                            'count' => $n,
                            'pair_count' => $pairs,
                            'anomalien' => implode(', ', $flags),
                            'sequenz' => $seq,
                            'zeiten' => $timeStr,
                        ];
                    } else {
                        if ($pairs >= 2) {
                            $mehrblock[] = [
                                'datum' => $d,
                                'count' => $n,
                                'pair_count' => $pairs,
                                'sequenz' => $seq,
                                'zeiten' => $timeStr,
                            ];
                        }
                    }
                }

                $ok = (count($auffaellig) === 0);

                $buchungssequenzTestErgebnis = [
                    'ok' => $ok,
                    'mitarbeiter_id' => $buchungssequenzTestMitarbeiterId,
                    'jahr' => $buchungssequenzTestJahr,
                    'monat' => $buchungssequenzTestMonat,
                    'tage_mit_buchungen' => count($byDay),
                    'tage_auffaellig' => count($auffaellig),
                    'tage_mehrblock' => count($mehrblock),
                    'auffaellig_sample' => array_slice($auffaellig, 0, 10),
                    'mehrblock_sample' => array_slice($mehrblock, 0, 10),
                ];
            } catch (Throwable $e) {
                $buchungssequenzTestHinweis = 'Sequenz-Check Fehler: ' . $e->getMessage();
            }
        }

        return [
            'mitarbeiter_id' => $buchungssequenzTestMitarbeiterId,
            'jahr' => $buchungssequenzTestJahr,
            'monat' => $buchungssequenzTestMonat,
            'ergebnis' => $buchungssequenzTestErgebnis,
            'hinweis' => $buchungssequenzTestHinweis,
        ];
    }

    /**
     * Feiertag-Seed-Check: sind die bundesweiten Feiertage eines Jahres da?
     *
     * @return array{jahr:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefeFeiertagSeed(int $feiertagSeedJahr): array
    {
        $feiertagSeedErgebnis = null;
        $feiertagSeedHinweis = null;

        if (!class_exists('FeiertagService')) {
            $feiertagSeedHinweis = 'FeiertagService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $fs = FeiertagService::getInstanz();
                if (method_exists($fs, 'diagnoseBundesweiteFeiertage')) {
                    $feiertagSeedErgebnis = $fs->diagnoseBundesweiteFeiertage($feiertagSeedJahr);
                } else {
                    $feiertagSeedHinweis = 'diagnoseBundesweiteFeiertage() ist nicht verfügbar (ältere Version).';
                }
            } catch (Throwable $e) {
                $feiertagSeedHinweis = 'Feiertag-Seed-Check fehlgeschlagen: ' . $e->getMessage();
            }
        }

        return [
            'jahr' => $feiertagSeedJahr,
            'ergebnis' => $feiertagSeedErgebnis,
            'hinweis' => $feiertagSeedHinweis,
        ];
    }
}
