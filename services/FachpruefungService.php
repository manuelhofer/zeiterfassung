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
}
