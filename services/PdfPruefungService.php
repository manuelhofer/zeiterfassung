<?php
declare(strict_types=1);

/**
 * PdfPruefungService
 *
 * Die drei Prüfungen, die das Monats-PDF erzeugen und den fertigen Stream
 * nachlesen: Quick-Check aus der Datenbank, Synth-Check ohne Datenbank und der
 * Multipage-Check am besten Kandidaten aus dem Bestand. Sie lagen als
 * `private` in `SmokeTestController` und waren damit nur über Login, Browser
 * und ein abgeschicktes Formular erreichbar (T-142, P-2026-08-17-18).
 *
 * Warum ein eigener Service neben `FachpruefungService`: Der prüft die
 * Rechenkerne – Raster, Doppelzählung, Salden. Hier geht es um die Ausgabe,
 * also um `PDFService` und, beim Multipage-Check, um das gerenderte HTML der
 * Monatsübersicht. Zwei Themen, zwei Dateien.
 *
 * **Die Rümpfe sind unverändert übernommen.** Was hier fehlt, ist allein das
 * Einlesen von `$_POST`, die CSRF-Prüfung und der Griff auf `AuthService` –
 * alles drei ist Sache des Aufrufers, nicht der Prüfung. Was der Rumpf davon
 * braucht, kommt als Parameter herein und trägt dort weiter denselben Namen
 * wie vorher die lokale Variable. Das Rückgabebündel ist dasselbe, damit die
 * Views in `views/smoke_test/` unberührt bleiben.
 */
class PdfPruefungService
{
    private static ?PdfPruefungService $instanz = null;

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

    public static function getInstanz(): PdfPruefungService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }


    /**
     * PDF-Quick-Check: Monats-PDF im Speicher erzeugen und Header/EOF/Seiten prüfen.
     *
     * @return array{mitarbeiter_id:int, jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefePdfQuick(int $pdfTestMitarbeiterId, int $pdfTestJahr, int $pdfTestMonat): array
    {
        $pdfTestErgebnis = null;
        $pdfTestHinweis = null;
        $pdfKommentarSamples = [];
        $pdfKommentarCheck = [];
        $pdfKommentarHinweis = null;

        if ($pdfTestMitarbeiterId <= 0) {
            $pdfTestHinweis = 'Keine gültige Mitarbeiter-ID für den PDF-Check (auch kein angemeldeter Mitarbeiter gefunden).';
        } elseif ($pdfTestJahr < 2000 || $pdfTestJahr > 2100) {
            $pdfTestHinweis = 'Bitte ein gültiges Jahr (2000–2100) angeben.';
        } elseif ($pdfTestMonat < 1 || $pdfTestMonat > 12) {
            $pdfTestHinweis = 'Bitte einen gültigen Monat (1–12) angeben.';
        } elseif (!class_exists('PDFService')) {
            $pdfTestHinweis = 'PDFService ist nicht verfügbar (Klasse fehlt).';
        } else {
            $pdfInhalt = '';

            $errorHandlerAktiv = false;
            try {
                set_error_handler(function (int $severity, string $message, string $file, int $line) use ($pdfTestJahr, $pdfTestMonat, $pdfTestMitarbeiterId): bool {
                    Logger::warn('PHP-Warnung/Notice während SmokeTest-PDF-Quick-Check', [
                        'severity' => $severity,
                        'message'  => $message,
                        'file'     => $file,
                        'line'     => $line,
                        'jahr'     => $pdfTestJahr,
                        'monat'    => $pdfTestMonat,
                        'mitarbeiter_id' => $pdfTestMitarbeiterId,
                    ], null, null, 'smoke_test');
                    return true; // Ausgabe unterdrücken
                });
                $errorHandlerAktiv = true;
            } catch (Throwable $e) {
                $errorHandlerAktiv = false;
            }

            $obStartLevel = ob_get_level();
            @ob_start();

            try {
                $pdfService = PDFService::getInstanz();
                $pdfInhalt = (string)$pdfService->erzeugeMonatsPdfFuerMitarbeiter($pdfTestMitarbeiterId, $pdfTestJahr, $pdfTestMonat);
            } catch (Throwable $e) {
                $pdfTestHinweis = 'PDF-Fehler beim Erzeugen: ' . $e->getMessage();
            }

            // Buffer leeren (Warnungen/Notices), Handler zurücksetzen
            while (ob_get_level() > $obStartLevel) {
                @ob_end_clean();
            }
            if ($errorHandlerAktiv) {
                try {
                    restore_error_handler();
                } catch (Throwable $e) {
                    // ignore
                }
            }

            if ($pdfTestHinweis === null) {
                $bytes = strlen($pdfInhalt);
                $headerOk = ($bytes >= 5 && substr($pdfInhalt, 0, 5) === '%PDF-');
                $eofOk = (strpos($pdfInhalt, '%%EOF') !== false);

                $pageObjCount = substr_count($pdfInhalt, '/Type /Page /Parent');
                $declaredPages = null;
                if (preg_match('/\/Type\s*\/Pages\b.*?\/Count\s+(\d+)/s', $pdfInhalt, $m)) {
                    $declaredPages = (int)$m[1];
                }

                $pagesMatch = null;
                if ($declaredPages !== null) {
                    $pagesMatch = ($declaredPages === $pageObjCount);
                }

                $footerSeite1 = (strpos($pdfInhalt, '(Seite 1/') !== false);
                $footerSeite2 = null;
                if ($pageObjCount >= 2) {
                    $footerSeite2 = (strpos($pdfInhalt, '(Seite 2/') !== false);
                }

                $headerArbeitszeitliste = (strpos($pdfInhalt, '(Arbeitszeitliste)') !== false);
                $headerTagKw = (strpos($pdfInhalt, '(Tag / KW)') !== false);

                $okErweitert = ($bytes > 0 && $headerOk && $eofOk);
                if ($pagesMatch === false) {
                    $okErweitert = false;
                }
                if (!$footerSeite1) {
                    $okErweitert = false;
                }
                if ($pageObjCount >= 2 && $footerSeite2 !== true) {
                    $okErweitert = false;
                }
                if (!$headerArbeitszeitliste || !$headerTagKw) {
                    $okErweitert = false;
                }

                $pdfTestErgebnis = [
                    'ok' => $okErweitert,
                    'bytes' => $bytes,
                    'header_ok' => $headerOk,
                    'eof_ok' => $eofOk,
                    'pages_count_declared' => $declaredPages,
                    'pages_count_objects' => $pageObjCount,
                    'pages_count_match' => $pagesMatch,
                    'footer_seite1' => $footerSeite1,
                    'footer_seite2' => $footerSeite2,
                    'header_arbeitszeitliste' => $headerArbeitszeitliste,
                    'header_tag_kw' => $headerTagKw,
                ];

                // Optional: Tageswerte-Kommentar (Kürzel) im PDF wiederfinden (nur Diagnose)
                $pdfKommentarSamples = [];
                $pdfKommentarCheck = [];
                $pdfKommentarHinweis = null;

                if ($this->db === null) {
                    $pdfKommentarHinweis = 'Kommentar-Check übersprungen: keine DB-Verbindung im Smoke-Test.';
                } else {
                    try {
                        $startDt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $pdfTestJahr, $pdfTestMonat));
                        $bisDt   = $startDt->modify('+1 month');

                        $sql = 'SELECT datum, kommentar
                                FROM tageswerte_mitarbeiter
                                WHERE mitarbeiter_id = :mid
                                  AND datum >= :von
                                  AND datum < :bis
                                  AND kommentar IS NOT NULL
                                  AND TRIM(kommentar) <> \'\'
                                ORDER BY datum ASC
                                LIMIT 10';

                        $rows = $this->db->fetchAlle($sql, [
                            'mid' => $pdfTestMitarbeiterId,
                            'von' => $startDt->format('Y-m-d'),
                            'bis' => $bisDt->format('Y-m-d'),
                        ]);

                        foreach ($rows as $r) {
                            if (!is_array($r)) {
                                continue;
                            }
                            $d = trim((string)($r['datum'] ?? ''));
                            $k = trim((string)($r['kommentar'] ?? ''));
                            if ($k === '') {
                                continue;
                            }

                            // Wie im PDF: auf 6 Zeichen kürzen (UTF-8 safe, falls möglich)
                            $short = $k;
                            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                                if (mb_strlen($short, 'UTF-8') > 6) {
                                    $short = mb_substr($short, 0, 6, 'UTF-8');
                                }
                            } else {
                                if (strlen($short) > 6) {
                                    $short = substr($short, 0, 6);
                                }
                            }

                            $pdfKommentarSamples[] = [
                                'datum' => $d,
                                'kommentar' => $short,
                            ];
                        }
                    } catch (Throwable $e) {
                        $pdfKommentarHinweis = 'Kommentar-Check DB-Fehler: ' . $e->getMessage();
                    }
                }

                if ($pdfKommentarSamples !== []) {
                    foreach ($pdfKommentarSamples as $smp) {
                        if (!is_array($smp)) {
                            continue;
                        }
                        $k = (string)($smp['kommentar'] ?? '');
                        $d = (string)($smp['datum'] ?? '');
                        if ($k === '') {
                            continue;
                        }
                        // PDF-Stream enthält Texte als (...). Tj – für Kürzel sollte eine simple Substring-Suche reichen.
                        $found = (strpos($pdfInhalt, '(' . $k . ')') !== false);
                        $pdfKommentarCheck[] = [
                            'datum' => $d,
                            'kommentar' => $k,
                            'found_in_pdf' => $found ? 1 : 0,
                        ];
                    }
                }

                $pdfTestErgebnis['kommentar_samples'] = $pdfKommentarSamples;
                $pdfTestErgebnis['kommentar_check'] = $pdfKommentarCheck;
                $pdfTestErgebnis['kommentar_hinweis'] = $pdfKommentarHinweis;

                if ($bytes <= 0) {
                    $pdfTestHinweis = 'PDF-Inhalt ist leer.';
                } elseif (!$headerOk) {
                    $pdfTestHinweis = 'PDF-Header fehlt (erwartet: %PDF-...).';
                } elseif (!$eofOk) {
                    $pdfTestHinweis = 'PDF-EOF Marker (%%EOF) fehlt.';
                } elseif ($pagesMatch === false) {
                    $pdfTestHinweis = 'PDF-Seitenanzahl inkonsistent: /Pages /Count=' . (int)$declaredPages . ' aber Page-Objekte=' . (int)$pageObjCount . '.';
                } elseif (!$footerSeite1) {
                    $pdfTestHinweis = 'PDF-Footer "Seite 1/..." fehlt im Stream.';
                } elseif ($pageObjCount >= 2 && $footerSeite2 !== true) {
                    $pdfTestHinweis = 'PDF-Footer "Seite 2/..." fehlt, obwohl mehrere Seiten erkannt wurden.';
                } elseif (!$headerArbeitszeitliste || !$headerTagKw) {
                    $pdfTestHinweis = 'PDF-Headertexte (Arbeitszeitliste / Tag / KW) wurden im Stream nicht gefunden.';
                }
            }
        }

        return [
            'mitarbeiter_id' => $pdfTestMitarbeiterId,
            'jahr' => $pdfTestJahr,
            'monat' => $pdfTestMonat,
            'ergebnis' => $pdfTestErgebnis,
            'hinweis' => $pdfTestHinweis,
        ];
    }

    /**
     * PDF-Synth-Check: Mehrseiten-Umbruch aus synthetischen Daten, ohne Datenbank.
     *
     * @return array{jahr:int, monat:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefePdfSynth(int $pdfSynthJahr, int $pdfSynthMonat): array
    {
        $pdfSynthErgebnis = null;
        $pdfSynthHinweis = null;

        if ($pdfSynthJahr < 1970 || $pdfSynthJahr > 2100) {
            $pdfSynthHinweis = 'Ungültiges Jahr (1970..2100).';
        } elseif ($pdfSynthMonat < 1 || $pdfSynthMonat > 12) {
            $pdfSynthHinweis = 'Ungültiger Monat (1..12).';
        } elseif (!class_exists('PDFService')) {
            $pdfSynthHinweis = 'PDFService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $startDt = new DateTimeImmutable(sprintf('%04d-%02d-01', $pdfSynthJahr, $pdfSynthMonat));
                $daysInMonth = (int)$startDt->modify('last day of this month')->format('j');

                $tageswerte = [];
                $blocksPerDay = 3;

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $ymd = sprintf('%04d-%02d-%02d', $pdfSynthJahr, $pdfSynthMonat, $day);

                    $arbeitsbloecke = [
                        [
                            'kommen_roh'  => $ymd . ' 05:30:00',
                            'gehen_roh'   => $ymd . ' 09:00:00',
                            'kommen_korr' => $ymd . ' 05:30:00',
                            'gehen_korr'  => $ymd . ' 09:00:00',
                        ],
                        [
                            'kommen_roh'  => $ymd . ' 09:15:00',
                            'gehen_roh'   => $ymd . ' 12:30:00',
                            'kommen_korr' => $ymd . ' 09:15:00',
                            'gehen_korr'  => $ymd . ' 12:30:00',
                        ],
                        [
                            'kommen_roh'  => $ymd . ' 13:00:00',
                            'gehen_roh'   => $ymd . ' 16:00:00',
                            'kommen_korr' => $ymd . ' 13:00:00',
                            'gehen_korr'  => $ymd . ' 16:00:00',
                        ],
                    ];

                    $tageswerte[] = [
                        'datum' => $ymd,
                        'pausen_stunden' => '0.75',
                        'arbeitszeit_stunden' => '8.00',
                        'arzt_stunden' => '0.00',
                        'krank_lfz_stunden' => '0.00',
                        'krank_kk_stunden' => '0.00',
                        'feiertag_stunden' => '0.00',
                        'kurzarbeit_stunden' => '0.00',
                        'urlaub_stunden' => '0.00',
                        'sonstige_stunden' => '0.00',
                        'kommentar' => ($day === 1 ? 'SoU: SmokeTest' : ''),
                        'zeit_manuell_geaendert' => (($day % 7) === 0 ? 1 : 0),
                        'arbeitsbloecke' => $arbeitsbloecke,
                    ];
                }

                $sollstunden = (float)$daysInMonth * 8.0;
                $monatswerte = [
                    'sollstunden' => number_format($sollstunden, 2, '.', ''),
                ];

                $pdfService = PDFService::getInstanz();
                if (!method_exists($pdfService, 'erzeugeMonatsPdfAusDaten')) {
                    throw new Exception('PDFService::erzeugeMonatsPdfAusDaten() fehlt (ältere Version).');
                }

                $pdfInhalt = $pdfService->erzeugeMonatsPdfAusDaten(9999, 'SMOKE TEST', $pdfSynthJahr, $pdfSynthMonat, $tageswerte, $monatswerte);

                $bytes = (int)strlen($pdfInhalt);
                $headerOk = ($bytes >= 5 && substr($pdfInhalt, 0, 5) === '%PDF-');
                $eofOk = (strpos($pdfInhalt, '%%EOF') !== false);

                $declaredPages = null;
                if (preg_match('/\/Count\s+(\d+)/', $pdfInhalt, $m) === 1) {
                    $declaredPages = (int)$m[1];
                }
                $pageObjCount = 0;
                if (preg_match_all('/\/Type\s*\/Page\b/', $pdfInhalt, $mm) !== false) {
                    $pageObjCount = is_array($mm[0] ?? null) ? count($mm[0]) : 0;
                }
                $pagesMatch = null;
                if ($declaredPages !== null) {
                    $pagesMatch = ($declaredPages === $pageObjCount);
                }

                $footerSeite1 = (strpos($pdfInhalt, '(Seite 1/') !== false);
                $footerSeite2 = (strpos($pdfInhalt, '(Seite 2/') !== false);

                $headerArbeitszeitliste = (strpos($pdfInhalt, '(Arbeitszeitliste)') !== false);
                $headerTagKw = (strpos($pdfInhalt, '(Tag / KW)') !== false);

                $pagesAtLeast2 = ($pageObjCount >= 2);

                $okSynth = ($bytes > 0 && $headerOk && $eofOk && $pagesMatch === true && $pagesAtLeast2 && $footerSeite1 && $footerSeite2 && $headerArbeitszeitliste && $headerTagKw);

                $pdfSynthErgebnis = [
                    'ok' => $okSynth,
                    'bytes' => $bytes,
                    'blocks_per_day' => $blocksPerDay,
                    'days_in_month' => $daysInMonth,
                    'rows_expected' => ($daysInMonth * $blocksPerDay) + 2, // + Header + Abschluss "/"
                    'header_ok' => $headerOk,
                    'eof_ok' => $eofOk,
                    'pages_count_declared' => $declaredPages,
                    'pages_count_objects' => $pageObjCount,
                    'pages_count_match' => $pagesMatch,
                    'pages_at_least2' => $pagesAtLeast2,
                    'footer_seite1' => $footerSeite1,
                    'footer_seite2' => $footerSeite2,
                    'header_arbeitszeitliste' => $headerArbeitszeitliste,
                    'header_tag_kw' => $headerTagKw,
                ];

                if ($bytes <= 0) {
                    $pdfSynthHinweis = 'PDF-Inhalt ist leer.';
                } elseif (!$headerOk) {
                    $pdfSynthHinweis = 'PDF-Header fehlt (erwartet: %PDF-...).';
                } elseif (!$eofOk) {
                    $pdfSynthHinweis = 'PDF-EOF Marker (%%EOF) fehlt.';
                } elseif ($pagesAtLeast2 !== true) {
                    $pdfSynthHinweis = 'Erwartet mind. 2 Seiten, erkannt: ' . (int)$pageObjCount . '.';
                } elseif ($pagesMatch !== true) {
                    $pdfSynthHinweis = 'PDF-Seitenanzahl inkonsistent: /Pages /Count=' . (int)$declaredPages . ' aber Page-Objekte=' . (int)$pageObjCount . '.';
                } elseif (!$footerSeite1 || !$footerSeite2) {
                    $pdfSynthHinweis = 'PDF-Footer "Seite 1/..." oder "Seite 2/..." fehlt im Stream.';
                } elseif (!$headerArbeitszeitliste || !$headerTagKw) {
                    $pdfSynthHinweis = 'PDF-Headertexte (Arbeitszeitliste / Tag / KW) wurden im Stream nicht gefunden.';
                }
            } catch (Throwable $e) {
                $pdfSynthHinweis = 'PDF-Synth-Check fehlgeschlagen: ' . $e->getMessage();
            }
        }

        return [
            'jahr' => $pdfSynthJahr,
            'monat' => $pdfSynthMonat,
            'ergebnis' => $pdfSynthErgebnis,
            'hinweis' => $pdfSynthHinweis,
        ];
    }

    /**
     * PDF-DB-Auto-Multipage-Check: besten Kandidaten suchen und sein PDF prüfen.
     *
     * Die drei Werte `$midSel`/`$jahrSel`/`$monatSel` wählen einen Kandidaten
     * von Hand; sind sie 0, sucht die Prüfung selbst. `$csrfGueltig`,
     * `$kannViewAll` und `$angemeldeteIdFuerHtml` sind der Zustand des
     * Aufrufers: Ein CLI-Aufruf hat keine Sitzung und übergibt `true`, `true`
     * und die geprüfte Mitarbeiter-ID.
     *
     * @return array{window_monate:int, ergebnis:?array<string,mixed>, hinweis:?string}
     */
    public function pruefePdfDbMultipage(
        int $pdfDbMultiWindowMonate,
        int $midSel,
        int $jahrSel,
        int $monatSel,
        bool $csrfGueltig,
        bool $kannViewAll,
        int $angemeldeteIdFuerHtml
    ): array {
        $pdfDbMultiErgebnis = null;
        $pdfDbMultiHinweis = null;

        if ($pdfDbMultiWindowMonate < 1) {
            $pdfDbMultiWindowMonate = 1;
        }
        if ($pdfDbMultiWindowMonate > 24) {
            $pdfDbMultiWindowMonate = 24;
        }

        if (!$csrfGueltig) {
            $pdfDbMultiHinweis = 'CSRF-Token ungültig – Aktion abgebrochen.';
        } elseif ($this->db === null) {
            $pdfDbMultiHinweis = 'Database::getInstanz() ist nicht verfügbar.';
        } elseif (!class_exists('PDFService')) {
            $pdfDbMultiHinweis = 'PDFService ist nicht verfügbar (Klasse fehlt).';
        } else {
            try {
                $pdo = $this->db->getVerbindung();
                $window = (int)$pdfDbMultiWindowMonate;

                $mid = 0;
                $jahr = 0;
                $monat = 0;
                $count = 0;
                $gefundenVia = 'auto';

                if ($midSel > 0 && $jahrSel > 0 && $monatSel >= 1 && $monatSel <= 12) {
                    $mid = $midSel;
                    $jahr = $jahrSel;
                    $monat = $monatSel;
                    $gefundenVia = 'liste';

                    // Anzahl Kommen/Gehen für die gewählte Kombination
                    try {
                        $stmtC = $pdo->prepare(
                            "SELECT COUNT(*) AS c
                             FROM zeitbuchung
                             WHERE mitarbeiter_id = :mid
                               AND typ IN ('kommen','gehen')
                               AND YEAR(zeitstempel) = :j
                               AND MONTH(zeitstempel) = :m"
                        );
                        $stmtC->execute(['mid' => $mid, 'j' => $jahr, 'm' => $monat]);
                        $rowC = $stmtC->fetch(PDO::FETCH_ASSOC);
                        if (is_array($rowC)) {
                            $count = (int)($rowC['c'] ?? 0);
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }
                } else {
                    $sql = "
                        SELECT mitarbeiter_id, YEAR(zeitstempel) AS jahr, MONTH(zeitstempel) AS monat, COUNT(*) AS c
                        FROM zeitbuchung
                        WHERE typ IN ('kommen','gehen')
                          AND zeitstempel >= DATE_SUB(CURDATE(), INTERVAL {$window} MONTH)
                        GROUP BY mitarbeiter_id, YEAR(zeitstempel), MONTH(zeitstempel)
                        ORDER BY c DESC
                        LIMIT 1
                    ";

                    $stmt = $pdo->query($sql);
                    $cand = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

                    if (!is_array($cand) || (int)($cand['mitarbeiter_id'] ?? 0) <= 0) {
                        $pdfDbMultiHinweis = 'Kein Kandidat gefunden (keine Kommen/Gehen-Buchungen in den letzten ' . (int)$window . ' Monaten).';
                    } else {
                        $mid = (int)($cand['mitarbeiter_id'] ?? 0);
                        $jahr = (int)($cand['jahr'] ?? 0);
                        $monat = (int)($cand['monat'] ?? 0);
                        $count = (int)($cand['c'] ?? 0);
                    }
                }

                if ($mid <= 0 || $jahr <= 0 || $monat < 1 || $monat > 12) {
                    // Hinweis wurde bereits gesetzt (Auto) oder wir haben keine valide Auswahl.
                } else {
// Max. Buchungen an einem Tag (Indikator für Mehrfach-Kommen/Gehen)
                    $maxDayCount = 0;
                    $maxDayDatum = '';
                    try {
                        $stmt2 = $pdo->prepare(
                            "SELECT DATE(zeitstempel) AS d, COUNT(*) AS c\n                                 FROM zeitbuchung\n                                 WHERE mitarbeiter_id = :mid\n                                   AND typ IN ('kommen','gehen')\n                                   AND YEAR(zeitstempel) = :j\n                                   AND MONTH(zeitstempel) = :m\n                                 GROUP BY DATE(zeitstempel)\n                                 ORDER BY c DESC\n                                 LIMIT 1"
                        );
                        $stmt2->execute(['mid' => $mid, 'j' => $jahr, 'm' => $monat]);
                        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                        if (is_array($row2)) {
                            $maxDayCount = (int)($row2['c'] ?? 0);
                            $maxDayDatum = (string)($row2['d'] ?? '');
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }

                    // Name (optional)
                    $name = 'Mitarbeiter #' . (int)$mid;
                    try {
                        $mm = new MitarbeiterModel();
                        $mrow = $mm->holeNachId($mid);
                        if (is_array($mrow)) {
                            $vn = trim((string)($mrow['vorname'] ?? ''));
                            $nn = trim((string)($mrow['nachname'] ?? ''));
                            $full = trim($vn . ' ' . $nn);
                            if ($full !== '') {
                                $name = $full;
                            }
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }

                    $pdfService = PDFService::getInstanz();
                    $pdfInhalt = (string)$pdfService->erzeugeMonatsPdfFuerMitarbeiter($mid, $jahr, $monat);

                    $bytes = strlen($pdfInhalt);
                    $headerOk = ($bytes >= 5 && substr($pdfInhalt, 0, 5) === '%PDF-');
                    $eofOk = (strpos($pdfInhalt, '%%EOF') !== false);

                    $pageObjCount = substr_count($pdfInhalt, '/Type /Page /Parent');
                    $declaredPages = null;
                    if (preg_match('/\\/Type\\s*\\/Pages\\b.*?\\/Count\\s+(\\d+)/s', $pdfInhalt, $m)) {
                        $declaredPages = (int)$m[1];
                    }

                    $pagesMatch = null;
                    if ($declaredPages !== null) {
                        $pagesMatch = ($declaredPages === $pageObjCount);
                    }

                    $pagesAtLeast2 = ($pageObjCount >= 2);

                    $footerSeite1 = (strpos($pdfInhalt, '(Seite 1/') !== false);
                    $footerSeite2 = (strpos($pdfInhalt, '(Seite 2/') !== false);

                    // T-069 (Fortsetzung): Report-Monatsübersicht HTML-Render-Check (Kandidat)
                    // - Rein lesend.
                    // - Rendert die Monatsübersicht für denselben Kandidaten via ReportController und prüft grob die HTML-Struktur.
                    $reportHtmlOk = null;
                    $reportHtmlHinweis = '';
                    $reportHtmlHasHeading = false;
                    $reportHtmlHasTable = false;
                    $reportHtmlHasHeaderCells = false;
                    $reportHtmlHasPdfLink = false;
                    $reportHtmlTrCount = 0;
                    $reportHtmlDaysInMonth = 0;
                    $reportHtmlRowsMinOk = null;

                    if (!$kannViewAll && $angemeldeteIdFuerHtml > 0 && $mid !== $angemeldeteIdFuerHtml) {
                        $reportHtmlOk = null;
                        $reportHtmlHinweis = 'SKIP: Kein REPORT_MONAT_VIEW_ALL/REPORTS_ANSEHEN_ALLE Recht für fremde Mitarbeiter.';
                    } else {
                        $backupGet = $_GET;
                        try {
                            if (!class_exists('ReportController')) {
                                throw new Exception('ReportController fehlt.');
                            }

                            $_GET['mitarbeiter_id'] = (string)$mid;
                            $_GET['seite'] = 'report_monat';

                            $obLevel = ob_get_level();
                            ob_start();
                            $html = '';
                            try {
                                $rc = new ReportController();
                                $rc->monatsuebersicht($jahr, $monat);
                                $html = (string)ob_get_clean();
                            } catch (Throwable $e) {
                                while (ob_get_level() > $obLevel) {
                                    @ob_end_clean();
                                }
                                throw $e;
                            }

                            // Nicht cal_days_in_month(): das braucht die Erweiterung
                            // `calendar`, die in keiner Installationsanleitung des
                            // Projekts steht. `format('t')` kann PHP von Haus aus.
                            $reportHtmlDaysInMonth = (int)(new DateTimeImmutable(
                                sprintf('%04d-%02d-01', (int)$jahr, (int)$monat)
                            ))->format('t');
                            $reportHtmlHasHeading = (stripos($html, 'Monatsübersicht') !== false);
                            $reportHtmlHasTable = (stripos($html, '<table') !== false);
                            $reportHtmlHasHeaderCells = (
                                strpos($html, '<th>Datum</th>') !== false
                                && strpos($html, '<th>An</th>') !== false
                                && strpos($html, '<th>Ab</th>') !== false
                            );
                            $reportHtmlHasPdfLink = (strpos($html, '?seite=report_monat_pdf') !== false);

                            $mTr = [];
                            $reportHtmlTrCount = (int)preg_match_all('/<tr\b/i', $html, $mTr);

                            // Mindestens: Headerzeile + pro Kalendertag mindestens eine Zeile (Mehrfach-Kommen/Gehen => mehr).
                            $reportHtmlRowsMinOk = ($reportHtmlTrCount >= ($reportHtmlDaysInMonth + 1));

                            $reportHtmlOk = (
                                $reportHtmlHasHeading
                                && $reportHtmlHasTable
                                && $reportHtmlHasHeaderCells
                                && $reportHtmlHasPdfLink
                                && $reportHtmlRowsMinOk
                            );

                            if ($reportHtmlOk !== true) {
                                $reportHtmlHinweis = 'HTML-Struktur unerwartet (Heading/Table/Headers/PDF-Link/Zeilenanzahl prüfen).';
                            }
                        } catch (Throwable $e) {
                            $reportHtmlOk = false;
                            $reportHtmlHinweis = 'HTML-Render-Check fehlgeschlagen: ' . $e->getMessage();
                        } finally {
                            $_GET = $backupGet;
                        }
                    }

                    $ok = ($bytes > 0 && $headerOk && $eofOk && $pagesAtLeast2);
                    if ($pagesMatch === false) {
                        $ok = false;
                    }
                    if (!$footerSeite1 || !$footerSeite2) {
                        $ok = false;
                    }
                    if ($reportHtmlOk === false) {
                        $ok = false;
                    }

                    $pdfDbMultiErgebnis = [
                        'ok' => $ok,
                        'window_monate' => $window,
                        'gefunden_via' => $gefundenVia,
                        'mitarbeiter_id' => $mid,
                        'name' => $name,
                        'jahr' => $jahr,
                        'monat' => $monat,
                        'buchungen_kommen_gehen' => $count,
                        'max_day_datum' => $maxDayDatum,
                        'max_day_buchungen' => $maxDayCount,
                        'pdf_bytes' => $bytes,
                        'header_ok' => $headerOk,
                        'eof_ok' => $eofOk,
                        'pages_count_declared' => $declaredPages,
                        'pages_count_objects' => $pageObjCount,
                        'pages_count_match' => $pagesMatch,
                        'pages_at_least2' => $pagesAtLeast2,
                        'footer_seite1' => $footerSeite1,
                        'footer_seite2' => $footerSeite2,
                        'html_ok' => $reportHtmlOk,
                        'html_hinweis' => $reportHtmlHinweis,
                        'html_has_heading' => $reportHtmlHasHeading,
                        'html_has_table' => $reportHtmlHasTable,
                        'html_has_header_cells' => $reportHtmlHasHeaderCells,
                        'html_has_pdf_link' => $reportHtmlHasPdfLink,
                        'html_tr_count' => $reportHtmlTrCount,
                        'html_days_in_month' => $reportHtmlDaysInMonth,
                        'html_rows_min_ok' => $reportHtmlRowsMinOk,
                        'link_report' => '?seite=report_monat&jahr=' . (int)$jahr . '&monat=' . (int)$monat . '&mitarbeiter_id=' . (int)$mid,
                        'link_pdf' => '?seite=report_monat_pdf&jahr=' . (int)$jahr . '&monat=' . (int)$monat . '&mitarbeiter_id=' . (int)$mid,
                    ];

                    if ($bytes <= 0) {
                        $pdfDbMultiHinweis = 'PDF-Inhalt ist leer.';
                    } elseif (!$headerOk) {
                        $pdfDbMultiHinweis = 'PDF-Header fehlt (erwartet: %PDF-...).';
                    } elseif (!$eofOk) {
                        $pdfDbMultiHinweis = 'PDF-EOF Marker (%%EOF) fehlt.';
                    } elseif ($pagesAtLeast2 !== true) {
                        $pdfDbMultiHinweis = 'Erwartet mind. 2 Seiten, erkannt: ' . (int)$pageObjCount . '.';
                    } elseif ($pagesMatch === false) {
                        $pdfDbMultiHinweis = 'PDF-Seitenanzahl inkonsistent: /Pages /Count=' . (int)$declaredPages . ' aber Page-Objekte=' . (int)$pageObjCount . '.';
                    } elseif (!$footerSeite1 || !$footerSeite2) {
                        $pdfDbMultiHinweis = 'PDF-Footer "Seite 1/..." oder "Seite 2/..." fehlt im Stream.';
                    }

                    if (($pdfDbMultiHinweis === null || $pdfDbMultiHinweis === '') && $reportHtmlOk === false) {
                        $pdfDbMultiHinweis = $reportHtmlHinweis;
                    }
                }
            } catch (Throwable $e) {
                $pdfDbMultiHinweis = 'PDF-DB-Multipage-Check fehlgeschlagen: ' . $e->getMessage();
            }
        }

        return [
            'window_monate' => $pdfDbMultiWindowMonate,
            'ergebnis' => $pdfDbMultiErgebnis,
            'hinweis' => $pdfDbMultiHinweis,
        ];
    }
}
