<?php
declare(strict_types=1);

/**
 * DashboardController
 *
 * Einfache Start-/Übersichtsseite nach erfolgreichem Login.
 */
class DashboardController
{
    private AuthService $authService;

    private const CSRF_KEY = 'csrf_token_dashboard_pausenentscheidung';

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
    }


    private function holeOderErzeugeCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Throwable) {
                $token = bin2hex((string)mt_rand());
            }
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return (string)$token;
    }

    /**
     * Zeigt das Dashboard.
     */
    public function index(): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $mitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();

        $name = 'Unbekannt';
        if ($mitarbeiter !== null) {
            $vorname  = trim((string)($mitarbeiter['vorname'] ?? ''));
            $nachname = trim((string)($mitarbeiter['nachname'] ?? ''));
            $tmp      = trim($vorname . ' ' . $nachname);
            if ($tmp !== '') {
                $name = $tmp;
            }
        }


        $mitarbeiterName = $name;

        // Rechte-/Legacy-Flags für Links im Zeitwarnblock
        $legacyAdmin = (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        );

        $csrfToken = $this->holeOderErzeugeCsrfToken();
        $pausenentscheidungFlash = null;

        // Pausenentscheidung speichern (T-081 Teil 2) – Default ohne Entscheidung: keine Pause
        if (((string)($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST' && isset($_POST['pausenentscheidung_submit'])) {
            $darf = false;
            try {
                $darf = $this->authService->istSuperuser() || $legacyAdmin || $this->authService->hatRecht('DASHBOARD_ZEITWARNUNGEN_SEHEN');
            } catch (Throwable $e) {
                $darf = false;
            }

            if (!$darf) {
                http_response_code(403);
                $pausenentscheidungFlash = [
                    'typ' => 'err',
                    'text' => 'Keine Berechtigung für Pausen-Entscheidungen.',
                ];
            } else {
                $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
                if (!hash_equals($csrfToken, $postToken)) {
                    http_response_code(400);
                    $pausenentscheidungFlash = [
                        'typ' => 'err',
                        'text' => 'Ungültiges Formular-Token (bitte Seite neu laden).',
                    ];
                } else {
                    $mid = isset($_POST['mitarbeiter_id']) ? (int)$_POST['mitarbeiter_id'] : 0;
                    $datum = isset($_POST['datum']) ? trim((string)$_POST['datum']) : '';
                    $entscheidung = isset($_POST['entscheidung']) ? trim((string)$_POST['entscheidung']) : '';

                    $ok = false;
                    try {
                        $model = new PausenentscheidungModel();
                        $ok = $model->setzeEntscheidung(
                            $mid,
                            $datum,
                            $entscheidung,
                            null,
                            $this->authService->holeAngemeldeteMitarbeiterId()
                        );
                    } catch (Throwable $e) {
                        $ok = false;
                    }

                    header('Location: ?seite=dashboard&pausenentscheidung=' . ($ok ? 'ok' : 'err'));
                    return;
                }
            }
        }

        // Flash aus Redirect (nach Speichern)
        $pe = isset($_GET['pausenentscheidung']) ? (string)$_GET['pausenentscheidung'] : '';
        if ($pe === 'ok') {
            $pausenentscheidungFlash = ['typ' => 'ok', 'text' => 'Pausen-Entscheidung gespeichert.'];
        } elseif ($pe === 'err') {
            $pausenentscheidungFlash = ['typ' => 'err', 'text' => 'Pausen-Entscheidung konnte nicht gespeichert werden.'];
        }

        $kannZeitEditAndere = $this->authService->hatRecht('ZEITBUCHUNG_EDIT_ALL') || $legacyAdmin;
        $kannReportAndere = $this->authService->hatRecht('REPORT_MONAT_VIEW_ALL')
            || $this->authService->hatRecht('REPORTS_ANSEHEN_ALLE')
            || $legacyAdmin;

        // ------------------------------------------------------------
        // Dashboard-Hinweis (Admin): Unvollständige Zeitbuchungen
        // Beispiel: nur "Kommen" ohne "Gehen" (oder umgekehrt).
        // Zweck: Chef/Personalbüro/Vorarbeiter sollen sofort sehen, wenn
        //         Zeitbuchungen nicht plausibel sind.
        // Heuristik:
        // - Wir suchen pro Mitarbeiter+Tag nach "ungleicher" Anzahl von
        //   Kommen/Gehen-Buchungen.
        // - Heute nicht warnen (erst ab Folgetag), weil der Tag noch nicht abgeschlossen ist.
        // ------------------------------------------------------------
        $zeitUnstimmigkeiten = null;
        $zeitUnstimmigkeitenFehler = '';
        $darfZeitUnstimmigkeitenSehen = false;
        try {
            // Primär über Recht steuerbar (skalierbar). Fallback auf Rollen (Legacy/Setup).
            $darfZeitUnstimmigkeitenSehen = $this->authService->hatRecht('DASHBOARD_ZEITWARNUNGEN_SEHEN');

            if (!$darfZeitUnstimmigkeitenSehen) {
                $darfZeitUnstimmigkeitenSehen = $legacyAdmin || $this->authService->hatRolle('Vorarbeiter');
            }
        } catch (Throwable $e) {
            $darfZeitUnstimmigkeitenSehen = false;
        }

        // Zeitraum fuer die Dashboard-Pruefung (in Tagen).
        // Default: 31 Tage, damit Monatsfehler nicht "verschwinden", nur weil sie etwas aelter als 14 Tage sind.
        // Optional per Config-Key `dashboard_zeitwarnungen_tage` anpassbar.
        $zeitUnstimmigkeitenTage = 31;
        try {
            $cfg = ConfigService::getInstanz();
            $zeitUnstimmigkeitenTage = $cfg->getInt('dashboard_zeitwarnungen_tage', 31);
        } catch (Throwable $e) {
            $zeitUnstimmigkeitenTage = 31;
        }
        $zeitUnstimmigkeitenTage = (int)$zeitUnstimmigkeitenTage;
        if ($zeitUnstimmigkeitenTage < 1) { $zeitUnstimmigkeitenTage = 1; }
        if ($zeitUnstimmigkeitenTage > 365) { $zeitUnstimmigkeitenTage = 365; }

        if ($darfZeitUnstimmigkeitenSehen && class_exists('Database')) {
            try {
                $db = Database::getInstanz();
                if ($db->istHauptdatenbankVerfuegbar()) {
                    // Wichtig: "Heute" muss in der PHP-Zeitzone (Europe/Berlin) definiert werden.
                    // CURDATE() kann je nach DB-Server/Session-Zeitzone (z. B. UTC) noch "gestern" liefern
                    // und wuerde dann den Vortag faelschlich als "heute" behandeln.
                    $tz = new DateTimeZone('Europe/Berlin');
                    $todayIso = (new DateTimeImmutable('today', $tz))->format('Y-m-d');

                    $startDate = (new DateTimeImmutable('today', $tz))
                        ->modify('-' . $zeitUnstimmigkeitenTage . ' days')
                        ->format('Y-m-d');
                    $startTs = (new DateTimeImmutable($todayIso . ' 00:00:00', $tz))
                        ->modify('-' . $zeitUnstimmigkeitenTage . ' days')
                        ->format('Y-m-d H:i:s');


                // Robust gegen ONLY_FULL_GROUP_BY: Aggregation in Subquery, dann Join auf mitarbeiter
                $sqlZeitwarnungen = "
                    SELECT
                        t.mitarbeiter_id,
                        CONCAT(TRIM(COALESCE(m.vorname, '')), ' ', TRIM(COALESCE(m.nachname, ''))) AS name,
                        t.datum,
                        t.anzahl_buchungen,
                        t.anzahl_kommen,
                        t.anzahl_gehen,
                        t.letzter_stempel
                    FROM (
                        SELECT
                            z.mitarbeiter_id,
                            DATE(z.zeitstempel) AS datum,
                            COUNT(*) AS anzahl_buchungen,
                            SUM(CASE WHEN z.typ = 'kommen' THEN 1 ELSE 0 END) AS anzahl_kommen,
                            SUM(CASE WHEN z.typ = 'gehen' THEN 1 ELSE 0 END) AS anzahl_gehen,
                            MAX(z.zeitstempel) AS letzter_stempel
                        FROM zeitbuchung z
                        WHERE z.zeitstempel >= :start_ts
                          AND z.typ IN ('kommen', 'gehen')
                        GROUP BY z.mitarbeiter_id, DATE(z.zeitstempel)
                        HAVING SUM(CASE WHEN z.typ = 'kommen' THEN 1 ELSE 0 END) <> SUM(CASE WHEN z.typ = 'gehen' THEN 1 ELSE 0 END)
                           AND DATE(z.zeitstempel) < :today
                    ) t
                    JOIN mitarbeiter m ON m.id = t.mitarbeiter_id
                    WHERE m.aktiv = 1
                    ORDER BY t.datum DESC, m.nachname ASC, m.vorname ASC
                    LIMIT 20
                ";

                $rows = $db->fetchAlle($sqlZeitwarnungen, [
                    'start_ts' => $startTs,
                    'today'    => $todayIso,
                ]);

                    $zeitUnstimmigkeiten = [];

                    // Nachtschicht-Grenzfall: Kommen am Abend + Gehen am Folgetag frueh
                    // soll nicht als Unstimmigkeit gelten (paarweise ueber Mitternacht).
                    // Wir filtern nur den sicheren Fall: genau 1 Stempel am Tag (nur Kommen oder nur Gehen).
                    $istNachtschichtGrenzfall = function (int $mid, string $datum, array $r) use ($db): bool {
                        $anz = (int)($r['anzahl_buchungen'] ?? 0);
                        $k = (int)($r['anzahl_kommen'] ?? 0);
                        $g = (int)($r['anzahl_gehen'] ?? 0);
                        if ($anz !== 1) {
                            return false;
                        }
                        if (!(($k === 1 && $g === 0) || ($k === 0 && $g === 1))) {
                            return false;
                        }

                        $d = DateTimeImmutable::createFromFormat('Y-m-d', $datum);
                        if (!$d) {
                            return false;
                        }
                        $prev = $d->modify('-1 day')->format('Y-m-d');
                        $next = $d->modify('+1 day')->format('Y-m-d');

                        $zeit = (string)($r['letzter_stempel'] ?? '');
                        if ($zeit === '' || strlen($zeit) < 19) {
                            return false;
                        }
                        $t = substr($zeit, 11, 8);

                        // Fall A: Tag hat nur Kommen am Abend, Folgetag hat nur Gehen frueh
                        if ($k === 1) {
                            if (!($t >= '18:00:00' && $t <= '23:59:59')) {
                                return false;
                            }
                            $r2 = $db->fetchEine(
                                "SELECT
"
                                . "  SUM(CASE WHEN typ='kommen' THEN 1 ELSE 0 END) AS anzahl_kommen,
"
                                . "  SUM(CASE WHEN typ='gehen' THEN 1 ELSE 0 END) AS anzahl_gehen,
"
                                . "  COUNT(*) AS anzahl_buchungen,
"
                                . "  MIN(zeitstempel) AS erster_stempel,
"
                                . "  MAX(zeitstempel) AS letzter_stempel
"
                                . "FROM zeitbuchung
"
                                . "WHERE mitarbeiter_id = :mid
"
                                . "  AND typ IN ('kommen','gehen')
"
                                . "  AND DATE(zeitstempel) = :d
"
                                . "GROUP BY DATE(zeitstempel)",
                                ['mid' => $mid, 'd' => $next]
                            );
                            if (!is_array($r2)) {
                                return false;
                            }
                            if ((int)($r2['anzahl_buchungen'] ?? 0) !== 1) {
                                return false;
                            }
                            if ((int)($r2['anzahl_gehen'] ?? 0) !== 1 || (int)($r2['anzahl_kommen'] ?? 0) !== 0) {
                                return false;
                            }
                            $z2 = (string)($r2['erster_stempel'] ?? '');
                            if ($z2 === '' || strlen($z2) < 19) {
                                return false;
                            }
                            $t2 = substr($z2, 11, 8);
                            return ($t2 >= '00:00:00' && $t2 <= '06:00:00');
                        }

                        // Fall B: Tag hat nur Gehen frueh, Vortag hat nur Kommen am Abend
                        if (!($t >= '00:00:00' && $t <= '06:00:00')) {
                            return false;
                        }
                        $r1 = $db->fetchEine(
                            "SELECT
"
                            . "  SUM(CASE WHEN typ='kommen' THEN 1 ELSE 0 END) AS anzahl_kommen,
"
                            . "  SUM(CASE WHEN typ='gehen' THEN 1 ELSE 0 END) AS anzahl_gehen,
"
                            . "  COUNT(*) AS anzahl_buchungen,
"
                            . "  MIN(zeitstempel) AS erster_stempel,
"
                            . "  MAX(zeitstempel) AS letzter_stempel
"
                            . "FROM zeitbuchung
"
                            . "WHERE mitarbeiter_id = :mid
"
                            . "  AND typ IN ('kommen','gehen')
"
                            . "  AND DATE(zeitstempel) = :d
"
                            . "GROUP BY DATE(zeitstempel)",
                            ['mid' => $mid, 'd' => $prev]
                        );
                        if (!is_array($r1)) {
                            return false;
                        }
                        if ((int)($r1['anzahl_buchungen'] ?? 0) !== 1) {
                            return false;
                        }
                        if ((int)($r1['anzahl_kommen'] ?? 0) !== 1 || (int)($r1['anzahl_gehen'] ?? 0) !== 0) {
                            return false;
                        }
                        $z1 = (string)($r1['letzter_stempel'] ?? '');
                        if ($z1 === '' || strlen($z1) < 19) {
                            return false;
                        }
                        $t1 = substr($z1, 11, 8);
                        return ($t1 >= '18:00:00' && $t1 <= '23:59:59');
                    };

                    foreach ($rows as $r) {
                        $mid = (int)($r['mitarbeiter_id'] ?? 0);
                        $datum = (string)($r['datum'] ?? '');
                        if ($mid <= 0 || $datum === '') {
                            continue;
                        }

                        // Nachtschicht-Grenzfaelle NICHT mehr unterdruecken:
                        // sonst verschwinden echte Warnungen (z. B. wenn der Monatsreport „FEHLT“ zeigt).
                        $nachtshiftGrenzfall = $istNachtschichtGrenzfall($mid, $datum, $r);

                        $zeitUnstimmigkeiten[] = [
                            'mitarbeiter_id' => $mid,
                            'name' => trim((string)($r['name'] ?? '')),
                            'datum' => $datum,
                            'anzahl_kommen' => (int)($r['anzahl_kommen'] ?? 0),
                            'anzahl_gehen' => (int)($r['anzahl_gehen'] ?? 0),
                            'anzahl_buchungen' => (int)($r['anzahl_buchungen'] ?? 0),
                            'letzter_stempel' => (string)($r['letzter_stempel'] ?? ''),
                            'nachtshift_grenzfall' => $nachtshiftGrenzfall ? 1 : 0,
                        ];
                    }

                    
                    // Zusatz: Tageswerte-Check (roh/korr) – deckt Fälle ab, in denen im Monatsreport bereits
                    // ein „FEHLT“ sichtbar ist, aber in der Roh-Tabelle (zeitbuchung) kein Kommen/Gehen-Ungleichgewicht erkannt wird
                    // (z. B. nach manueller Korrektur/Übernahme in tageswerte_mitarbeiter).
                    try {
                        $rowsTw = $db->fetchAlle(
                            "SELECT\n"
                            . "  m.id AS mitarbeiter_id,\n"
                            . "  CONCAT(TRIM(COALESCE(m.vorname,'')), ' ', TRIM(COALESCE(m.nachname,''))) AS name,\n"
                            . "  tw.datum AS datum,\n"
                            . "  CASE WHEN COALESCE(tw.kommen_korr, tw.kommen_roh) IS NULL THEN 0 ELSE 1 END AS anzahl_kommen,\n"
                            . "  CASE WHEN COALESCE(tw.gehen_korr, tw.gehen_roh) IS NULL THEN 0 ELSE 1 END AS anzahl_gehen,\n"
                            . "  (CASE WHEN COALESCE(tw.kommen_korr, tw.kommen_roh) IS NULL THEN 0 ELSE 1 END\n"
                            . "   + CASE WHEN COALESCE(tw.gehen_korr, tw.gehen_roh) IS NULL THEN 0 ELSE 1 END) AS anzahl_buchungen,\n"
                            . "  COALESCE(COALESCE(tw.gehen_korr, tw.gehen_roh), COALESCE(tw.kommen_korr, tw.kommen_roh)) AS letzter_stempel\n"
                            . "FROM tageswerte_mitarbeiter tw\n"
                            . "JOIN mitarbeiter m ON m.id = tw.mitarbeiter_id\n"
                            . "WHERE m.aktiv = 1\n"
                            . "  AND tw.datum >= :start_date\n"
                            . "  AND tw.datum < :today_date\n"
                            . "  AND (\n"
                            . "        (COALESCE(tw.kommen_korr, tw.kommen_roh) IS NULL AND COALESCE(tw.gehen_korr, tw.gehen_roh) IS NOT NULL)\n"
                            . "     OR (COALESCE(tw.kommen_korr, tw.kommen_roh) IS NOT NULL AND COALESCE(tw.gehen_korr, tw.gehen_roh) IS NULL)\n"
                            . "      )\n"
                            . "ORDER BY datum DESC, m.nachname ASC, m.vorname ASC\n"
                            . "LIMIT 20",
                            ['start_date' => $startDate, 'today_date' => $todayIso]
                        );

                        if (is_array($rowsTw)) {
                            foreach ($rowsTw as $r) {
                                $mid = (int)($r['mitarbeiter_id'] ?? 0);
                                $datum = (string)($r['datum'] ?? '');
                                if ($mid <= 0 || $datum === '') {
                                    continue;
                                }

                                // optional: gleiche Nachtschicht-Heuristik anwenden (nur wenn wir genug Rohdaten haben)
                                $nachtshiftGrenzfall = $istNachtschichtGrenzfall($mid, $datum, $r);

                                $zeitUnstimmigkeiten[] = [
                                    'mitarbeiter_id' => $mid,
                                    'name' => trim((string)($r['name'] ?? '')),
                                    'datum' => $datum,
                                    'anzahl_kommen' => (int)($r['anzahl_kommen'] ?? 0),
                                    'anzahl_gehen' => (int)($r['anzahl_gehen'] ?? 0),
                                    'anzahl_buchungen' => (int)($r['anzahl_buchungen'] ?? 0),
                                    'letzter_stempel' => (string)($r['letzter_stempel'] ?? ''),
                                    'nachtshift_grenzfall' => $nachtshiftGrenzfall ? 1 : 0,
                                ];
                            }
                        }
                    } catch (Throwable $e) {
                        // ignoriere Tageswerte-Check bei fehlender Tabelle/Fehler
                    }

                    // Dedupe (mid+datum), damit keine doppelten Warnungen erscheinen
                    if (is_array($zeitUnstimmigkeiten) && count($zeitUnstimmigkeiten) > 1) {
                        $seen = [];
                        $dedup = [];
                        foreach ($zeitUnstimmigkeiten as $w) {
                            $k = (string)($w['mitarbeiter_id'] ?? '') . '|' . (string)($w['datum'] ?? '');
                            if ($k === '|' || isset($seen[$k])) {
                                continue;
                            }
                            $seen[$k] = true;
                            $dedup[] = $w;
                        }
                        $zeitUnstimmigkeiten = $dedup;
                    }

                    if (count($zeitUnstimmigkeiten) === 0) {
                        $zeitUnstimmigkeiten = null;
                    }
                }
	        } catch (Throwable $e) {
	            $zeitUnstimmigkeiten = null;
	            $zeitUnstimmigkeitenFehler = $e->getMessage();
	            error_log('Dashboard: Zeitwarnungen konnten nicht geladen werden: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
	        }
        }

        

        // ------------------------------------------------------------
        // Pausen-Entscheidung nötig? (T-081)
        // - Grenzfall nahe Pausenschwelle oder "komische" Konstellation
        // - Default (ohne Entscheidung): keine Pause wird abgezogen
        // - Sichtbar für Chef/Personalbüro/Vorarbeiter bzw. über Recht
        // ------------------------------------------------------------
        $pausenEntscheidungenOffen = null;
        try {
            $darfPausenEntscheidungenSehen = $this->authService->hatRecht('DASHBOARD_ZEITWARNUNGEN_SEHEN');
            if (!$darfPausenEntscheidungenSehen) {
                $darfPausenEntscheidungenSehen = $legacyAdmin || $this->authService->istSuperuser();
            }
        } catch (Throwable $e) {
            $darfPausenEntscheidungenSehen = false;
        }

        if ($darfPausenEntscheidungenSehen && class_exists('Database')) {
            try {
                $db = Database::getInstanz();
                if ($db->istHauptdatenbankVerfuegbar()) {
                    $mitarbeiterModel = new MitarbeiterModel();
                    $alleMitarbeiter = $mitarbeiterModel->holeAlleAktiven();

                    $reportService = ReportService::getInstanz();

                    $heute = new DateTimeImmutable('today');
                    $von = $heute->modify('-31 days');

                    $monate = [];
                    $cursor = new DateTimeImmutable($von->format('Y-m-01'));
                    $endeCursor = new DateTimeImmutable($heute->format('Y-m-01'));
                    while ($cursor <= $endeCursor) {
                        $monate[] = [(int)$cursor->format('Y'), (int)$cursor->format('n')];
                        $cursor = $cursor->modify('first day of next month');
                    }

                    $offen = [];
                    $max = 50;
                    $stop = false;

                    foreach ($alleMitarbeiter as $mrow) {
                        $mid = (int)($mrow['id'] ?? 0);
                        if ($mid <= 0) {
                            continue;
                        }

                        $mname = trim((string)($mrow['name'] ?? ''));
                        if ($mname === '') {
                            $mname = trim((string)($mrow['vorname'] ?? '') . ' ' . (string)($mrow['nachname'] ?? ''));
                        }
                        if ($mname === '') {
                            $mname = 'ID ' . $mid;
                        }

                        foreach ($monate as $ym) {
                            $jahr = (int)($ym[0] ?? 0);
                            $monat = (int)($ym[1] ?? 0);

                            $tage = $reportService->holeMonatsdatenFuerMitarbeiter($mid, $jahr, $monat);
                            foreach ($tage as $t) {
                                $datum = (string)($t['datum'] ?? '');
                                if ($datum === '') {
                                    continue;
                                }

                                try {
                                    $dObj = new DateTimeImmutable($datum);
                                } catch (Throwable $e) {
                                    continue;
                                }

                                if ($dObj < $von || $dObj > $heute) {
                                    continue;
                                }

                                if ((int)($t['pause_entscheidung_noetig'] ?? 0) !== 1) {
                                    continue;
                                }

                                $status = $t['pause_entscheidung_status'] ?? null;
                                if ($status !== null && trim((string)$status) !== '') {
                                    continue;
                                }

                                $autoMin = (int)($t['pause_entscheidung_auto_minuten'] ?? 0);
                                if ($autoMin <= 0) {
                                    continue;
                                }

                                $kommen = (string)($t['kommen_korr'] ?? $t['kommen_roh'] ?? '');
                                $gehen = (string)($t['gehen_korr'] ?? $t['gehen_roh'] ?? '');

                                $offen[] = [
                                    'mitarbeiter_id' => $mid,
                                    'name' => $mname,
                                    'datum' => $datum,
                                    'kommen' => $kommen,
                                    'gehen' => $gehen,
                                    'auto_minuten' => $autoMin,
                                ];

                                if (count($offen) >= $max) {
                                    $stop = true;
                                    break;
                                }
                            }

                            if ($stop) {
                                break;
                            }
                        }

                        if ($stop) {
                            break;
                        }
                    }

                    if (count($offen) > 0) {
                        usort($offen, static function (array $a, array $b): int {
                            $c = strcmp((string)($b['datum'] ?? ''), (string)($a['datum'] ?? ''));
                            if ($c !== 0) {
                                return $c;
                            }
                            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                        });

                        $pausenEntscheidungenOffen = $offen;
                    }
                }
            } catch (Throwable $e) {
                $pausenEntscheidungenOffen = null;
            }
        }
// Smoke-Test (manuell über ?seite=dashboard&smoke=1 bzw. &smoke=2)
        // Ziel: schnelle Diagnose für Kern-Subsysteme ohne separate Route.
        // - smoke=1: read-only Checks
        // - smoke=2: zusätzlich "mutierende" Checks in DB-Transaktion (Rollback)
        $smokeTest = null;
        $smokeParam = isset($_GET['smoke']) ? (string)$_GET['smoke'] : '';

        // Systemstatus (optional, primär für Admins)
        // Ziel: schnelle Sicht auf Haupt-DB, Offline-DB (falls aktiv) und Terminal-Konfiguration.
        $systemStatus = null;
        $darfSystemStatusSehen = $this->authService->hatRecht('QUEUE_VERWALTEN')
            || $this->authService->hatRecht('TERMINAL_VERWALTEN')
            || $this->authService->hatRecht('KONFIGURATION_VERWALTEN')
            || $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro');

        if ($darfSystemStatusSehen) {
            $db = Database::getInstanz();

            $hauptDbOk = $db->istHauptdatenbankVerfuegbar();

            // Offline-DB Status: unterscheide "deaktiviert" vs "aktiv, aber nicht erreichbar".
            $offlineStatus = 'deaktiviert';
            $konfigPfad = __DIR__ . '/../config/config.php';
            $offlineEnabled = false;
            if (is_file($konfigPfad)) {
                try {
                    $cfg = require $konfigPfad;
                    if (is_array($cfg) && isset($cfg['offline_db']) && is_array($cfg['offline_db'])) {
                        $offlineEnabled = (($cfg['offline_db']['enabled'] ?? false) === true);
                    }
                } catch (Throwable $e) {
                    $offlineEnabled = false;
                }
            }

            if ($offlineEnabled) {
                $offlinePdo = $db->getOfflineVerbindung();
                $offlineStatus = $offlinePdo instanceof \PDO ? 'ok' : 'nicht_erreichbar';
            }

            // Terminal-Kurzcheck: nur, wenn die Haupt-DB erreichbar ist.
            $terminalSummary = null;
            if ($hauptDbOk) {
                try {
                    $row = $db->fetchEine(
                        "SELECT\n"
                        . "  COUNT(*) AS gesamt,\n"
                        . "  SUM(CASE WHEN aktiv = 1 THEN 1 ELSE 0 END) AS aktiv,\n"
                        . "  SUM(CASE WHEN aktiv = 1 AND modus = 'terminal' THEN 1 ELSE 0 END) AS aktiv_terminal,\n"
                        . "  SUM(CASE WHEN aktiv = 1 AND modus = 'backend' THEN 1 ELSE 0 END) AS aktiv_backend,\n"
                        . "  SUM(CASE WHEN aktiv = 1 AND offline_erlaubt_kommen_gehen = 1 THEN 1 ELSE 0 END) AS aktiv_offline_kg_ja,\n"
                        . "  SUM(CASE WHEN aktiv = 1 AND offline_erlaubt_kommen_gehen = 0 THEN 1 ELSE 0 END) AS aktiv_offline_kg_nein,\n"
                        . "  SUM(CASE WHEN aktiv = 1 AND offline_erlaubt_auftraege = 1 THEN 1 ELSE 0 END) AS aktiv_offline_auf_ja,\n"
                        . "  SUM(CASE WHEN aktiv = 1 AND offline_erlaubt_auftraege = 0 THEN 1 ELSE 0 END) AS aktiv_offline_auf_nein\n"
                        . "FROM terminal"
                    );

                    if (is_array($row)) {
                        $terminalSummary = [
                            'gesamt' => (int)($row['gesamt'] ?? 0),
                            'aktiv' => (int)($row['aktiv'] ?? 0),
                            'aktiv_terminal' => (int)($row['aktiv_terminal'] ?? 0),
                            'aktiv_backend' => (int)($row['aktiv_backend'] ?? 0),
                            'aktiv_offline_kg_ja' => (int)($row['aktiv_offline_kg_ja'] ?? 0),
                            'aktiv_offline_kg_nein' => (int)($row['aktiv_offline_kg_nein'] ?? 0),
                            'aktiv_offline_auf_ja' => (int)($row['aktiv_offline_auf_ja'] ?? 0),
                            'aktiv_offline_auf_nein' => (int)($row['aktiv_offline_auf_nein'] ?? 0),
                        ];
                    }
                } catch (Throwable $e) {
                    $terminalSummary = null;
                }
            }

            $systemStatus = [
                'hauptdb_ok' => $hauptDbOk,
                'offline_db_status' => $offlineStatus,
                'terminals' => $terminalSummary,
            ];

            // Smoke-Test nur wenn explizit angefordert (nicht bei jedem Dashboard-Load)
            if ($smokeParam === '1' || $smokeParam === '2') {
                $smokeTest = [
                    'started_at' => microtime(true),
                    'checks' => [],
                ];

                $add = function (string $name, bool $ok, string $details, float $ms) use (&$smokeTest): void {
                    $smokeTest['checks'][] = [
                        'name' => $name,
                        'ok' => $ok,
                        'details' => $details,
                        'ms' => $ms,
                    ];
                };

                // 1) Haupt-DB
                $t0 = microtime(true);
                $add('Hauptdatenbank erreichbar', $hauptDbOk, $hauptDbOk ? 'OK' : 'Nicht erreichbar', (microtime(true) - $t0) * 1000.0);

                // 2) ZipArchive vorhanden (Sammel-Export)
                $t0 = microtime(true);
                $zipOk = class_exists('ZipArchive');
                $add('PHP ZipArchive', $zipOk, $zipOk ? 'Vorhanden' : 'Fehlt (ZIP-Export funktioniert nicht)', (microtime(true) - $t0) * 1000.0);

                // 3) Tabellen-Existenz (nur wenn DB ok)
                $t0 = microtime(true);
                if ($hauptDbOk) {
                    // DB-Name (hilfreich, falls versehentlich auf eine leere/falsche DB gezeigt wird)
                    $dbName = '?';
                    try {
                        $r = $db->fetchEine('SELECT DATABASE() AS db');
                        if (is_array($r) && isset($r['db']) && (string)($r['db'] ?? '') !== '') {
                            $dbName = (string)$r['db'];
                        }
                    } catch (Throwable $e) {
                        $dbName = '?';
                    }

                    $needTables = [
                        'mitarbeiter',
                        'zeitbuchung',
                        'tageswerte_mitarbeiter',
                        'monatswerte_mitarbeiter',
                        'betriebsferien',
                        'feiertag',
                        'urlaubsantrag',
                        'urlaub_kontingent_jahr',
                        'auftrag',
                        'auftragszeit',
                        'terminal',
                        'db_injektionsqueue',
                        'system_log',
                    ];

                    // IMPORTANT: "SHOW TABLES" ist je nach PDO/Driver nicht zuverlässig mit prepared statements.
                    // Daher prüfen wir über information_schema.
                    $vorhanden = [];
                    try {
                        $rows = $db->fetchAlle('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()');
                        foreach ($rows as $rr) {
                            $tn = (string)($rr['TABLE_NAME'] ?? $rr['table_name'] ?? '');
                            if ($tn !== '') {
                                $vorhanden[$tn] = true;
                            }
                        }
                    } catch (Throwable $e) {
                        // Fallback: direkte SHOW TABLES Query (ohne prepare)
                        try {
                            $pdo = $db->getPdo();
                            $stmt = $pdo->query('SHOW TABLES');
                            if ($stmt !== false) {
                                while (($row = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
                                    $tn = (string)($row[0] ?? '');
                                    if ($tn !== '') {
                                        $vorhanden[$tn] = true;
                                    }
                                }
                            }
                        } catch (Throwable $e2) {
                            $vorhanden = [];
                        }
                    }

                    $missing = [];
                    foreach ($needTables as $tbl) {
                        if (!isset($vorhanden[$tbl])) {
                            $missing[] = $tbl;
                        }
                    }
                    $ok = empty($missing);
                    $detail = $ok ? ('OK (DB: ' . $dbName . ')') : ('DB: ' . $dbName . ' | Fehlt: ' . implode(', ', $missing));
                    if (strlen($detail) > 420) {
                        $detail = substr($detail, 0, 420) . '…';
                    }
                    $add('DB Tabellen (Core)', $ok, $detail, (microtime(true) - $t0) * 1000.0);
                } else {
                    $add('DB Tabellen (Core)', false, 'Übersprungen (Haupt-DB down)', (microtime(true) - $t0) * 1000.0);
                }

                // 3aa) Queue-DB (offline bevorzugt) erreichbar + Tabelle vorhanden
                $t0 = microtime(true);
                $queueDbOk = false;
                $queueDetails = 'Unbekannt';
                try {
                    // gleiche Logik wie OfflineQueueManager::holeQueueVerbindung():
                    $queuePdo = $db->getOfflineVerbindung();
                    $queueQuelle = 'offline';
                    if (!($queuePdo instanceof \PDO)) {
                        if ($hauptDbOk) {
                            $queuePdo = $db->getVerbindung();
                            $queueQuelle = 'haupt';
                        } else {
                            $queuePdo = null;
                        }
                    }

                    if ($queuePdo instanceof \PDO) {
                        $stmt = $queuePdo->query('SELECT 1');
                        $queueDbOk = ($stmt !== false);

                        $hasQueueTable = false;
                        try {
                            $r = $queuePdo->query("SHOW TABLES LIKE 'db_injektionsqueue'");
                            if ($r !== false) {
                                $x = $r->fetch();
                                $hasQueueTable = ($x !== false);
                            }
                        } catch (Throwable $e) {
                            $hasQueueTable = false;
                        }

                        $queueDetails = $queueDbOk
                            ? ($queueQuelle . ' DB OK, table=' . ($hasQueueTable ? 'ok' : 'fehlt'))
                            : ($queueQuelle . ' DB nicht erreichbar');
                    } else {
                        $queueDbOk = false;
                        $queueDetails = 'Keine Queue-DB (offline deaktiviert + Haupt-DB down)';
                    }
                } catch (Throwable $e) {
                    $queueDbOk = false;
                    $queueDetails = 'Fehler: ' . $e->getMessage();
                }
                $add('Queue DB (offline/haupt)', $queueDbOk, $queueDetails, (microtime(true) - $t0) * 1000.0);

                // 3b) PHP-Erweiterungen/Funktionen (für PDF/Export)
                $t0 = microtime(true);
                $missingExt = [];

                $drivers = [];
                try {
                    $drivers = \PDO::getAvailableDrivers();
                } catch (Throwable $e) {
                    $drivers = [];
                }

                if (!in_array('mysql', $drivers, true)) {
                    $missingExt[] = 'pdo_mysql';
                }
                if (!function_exists('mb_strlen')) {
                    $missingExt[] = 'mbstring';
                }
                if (!function_exists('iconv')) {
                    $missingExt[] = 'iconv';
                }

                $ok = empty($missingExt);
                $add(
                    'PHP Extensions (PDF/Export)',
                    $ok,
                    $ok ? 'OK' : ('Fehlt: ' . implode(', ', $missingExt)),
                    (microtime(true) - $t0) * 1000.0
                );

                // 3c) Spalten-Existenz (Schema-Drift erkennen, bevor Controller/Services crashen)
                $t0 = microtime(true);
                if ($hauptDbOk) {
                    $schemaChecks = [
                        'zeitbuchung' => ['id', 'mitarbeiter_id', 'typ', 'zeitstempel', 'quelle', 'manuell_geaendert', 'kommentar', 'terminal_id'],
                        'auftragszeit' => ['id', 'mitarbeiter_id', 'auftrag_id', 'auftragscode', 'maschine_id', 'terminal_id', 'typ', 'startzeit', 'endzeit', 'status', 'kommentar'],
                        // Gemäß Master-Prompt: `auftrag` ist minimal/optional – Kern ist die Auftragsnummer.
                        'auftrag' => ['id', 'auftragsnummer', 'aktiv'],
                        'terminal' => ['id', 'name', 'modus', 'aktiv', 'offline_erlaubt_kommen_gehen', 'offline_erlaubt_auftraege'],
                        'db_injektionsqueue' => ['id', 'status', 'sql_befehl', 'fehlernachricht', 'versuche', 'letzte_ausfuehrung', 'meta_mitarbeiter_id', 'meta_terminal_id', 'meta_aktion', 'erstellt_am'],
                    ];

                    $missingCols = [];
                    foreach ($schemaChecks as $tbl => $cols) {
                        // Spalten über information_schema prüfen (robust, prepared-statement-safe)
                        try {
                            $rows = $db->fetchAlle(
                                'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t',
                                ['t' => $tbl]
                            );

                            if (empty($rows)) {
                                $missingCols[$tbl][] = 'Tabelle fehlt';
                                continue;
                            }

                            $present = [];
                            foreach ($rows as $r) {
                                $f = (string)($r['COLUMN_NAME'] ?? $r['column_name'] ?? '');
                                if ($f !== '') {
                                    $present[$f] = true;
                                }
                            }

                            foreach ($cols as $c) {
                                if (!isset($present[$c])) {
                                    $missingCols[$tbl][] = $c;
                                }
                            }
                        } catch (Throwable $e) {
                            $missingCols[$tbl][] = 'information_schema fehlgeschlagen';
                        }
                    }

                    $ok = empty($missingCols);
                    $details = 'OK';
                    if (!$ok) {
                        $parts = [];
                        foreach ($missingCols as $tbl => $cols) {
                            $parts[] = $tbl . ': ' . implode(', ', $cols);
                        }
                        $details = 'Fehlt: ' . implode(' | ', $parts);
                        if (strlen($details) > 420) {
                            $details = substr($details, 0, 420) . '…';
                        }
                    }

                    $add('DB Spalten (Core)', $ok, $details, (microtime(true) - $t0) * 1000.0);
                } else {
                    $add('DB Spalten (Core)', false, 'Übersprungen (Haupt-DB down)', (microtime(true) - $t0) * 1000.0);
                }

                // 3d) Terminal-Dateien (Deployment-Check)
                $t0 = microtime(true);
                $needFiles = [
                    __DIR__ . '/../public/terminal.php',
                    __DIR__ . '/../public/css/terminal.css',
                    __DIR__ . '/../public/js/terminal-autologout.js',
                    __DIR__ . '/../public/js/terminal-logout.js',
                ];
                $missingFiles = [];
                foreach ($needFiles as $f) {
                    if (!is_file($f)) {
                        $missingFiles[] = basename($f);
                    }
                }
                $ok = empty($missingFiles);
                $add('Terminal Dateien', $ok, $ok ? 'OK' : ('Fehlt: ' . implode(', ', $missingFiles)), (microtime(true) - $t0) * 1000.0);

                // 3e) Terminal-Services (Methoden vorhanden)
                $t0 = microtime(true);
                $missingSvc = [];
                if (!class_exists('ZeitService') || !method_exists('ZeitService', 'getInstanz') || !method_exists('ZeitService', 'bucheKommen') || !method_exists('ZeitService', 'bucheGehen')) {
                    $missingSvc[] = 'ZeitService (bucheKommen/bucheGehen)';
                }
                if (!class_exists('AuftragszeitService') || !method_exists('AuftragszeitService', 'getInstanz') || !method_exists('AuftragszeitService', 'starteAuftrag') || !method_exists('AuftragszeitService', 'stoppeAuftrag')) {
                    $missingSvc[] = 'AuftragszeitService (starte/stoppe)';
                }

                $ok = empty($missingSvc);
                $add('Terminal Services', $ok, $ok ? 'OK' : ('Fehlt: ' . implode(', ', $missingSvc)), (microtime(true) - $t0) * 1000.0);

                // 3f) Offline-Queue Verbindung/Schema (Offline-DB falls aktiv, sonst Haupt-DB)
                $t0 = microtime(true);
                $queuePdo = null;
                $queueQuelle = 'haupt';
                $queueConnOk = false;
                $queueDetails = '';

                try {
                    if ($offlineEnabled) {
                        $queueQuelle = 'offline';
                        $queuePdo = $db->getOfflineVerbindung();
                        if (!($queuePdo instanceof \PDO)) {
                            $queuePdo = null;
                            $queueDetails = 'Offline-DB aktiv, aber nicht erreichbar';
                        }
                    }

                    if (!($queuePdo instanceof \PDO)) {
                        $queueQuelle = 'haupt';
                        if ($hauptDbOk) {
                            $queuePdo = $db->getPdo();
                        } else {
                            $queuePdo = null;
                            if ($queueDetails === '') {
                                $queueDetails = 'Haupt-DB down';
                            }
                        }
                    }

                    if ($queuePdo instanceof \PDO) {
                        $stmt = $queuePdo->query('SELECT 1');
                        $queueConnOk = ($stmt !== false);

                        // Schema check (read-only): Tabelle muss existieren (wird sonst beim ersten Queue-Write angelegt).
                        if ($queueConnOk) {
                            $row = $queuePdo->query("SHOW TABLES LIKE 'db_injektionsqueue'");
                            $hasTable = false;
                            if ($row !== false) {
                                $tmp = $row->fetch(\PDO::FETCH_NUM);
                                $hasTable = ($tmp !== false);
                            }

                            if ($hasTable) {
                                $queueDetails = 'OK (' . $queueQuelle . ')';
                            } else {
                                $queueConnOk = false;
                                $queueDetails = 'Tabelle fehlt (' . $queueQuelle . '); wird beim ersten Queue-Write automatisch angelegt';
                            }
                        } else {
                            if ($queueDetails === '') {
                                $queueDetails = 'DB Query fehlgeschlagen (' . $queueQuelle . ')';
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $queueConnOk = false;
                    $queueDetails = 'Fehler: ' . $e->getMessage();
                }

                $add('Offline-Queue DB/Schema', $queueConnOk, $queueDetails !== '' ? $queueDetails : ($queueConnOk ? 'OK' : 'Fehler'), (microtime(true) - $t0) * 1000.0);

                // 4) ReportService Monatsdaten (aktueller User, aktueller Monat)
                $t0 = microtime(true);
                if ($hauptDbOk && $mitarbeiter !== null) {
                    $mid = (int)($mitarbeiter['id'] ?? 0);
                    $jahr = (int)date('Y');
                    $monat = (int)date('n');
                    $days = (int)cal_days_in_month(CAL_GREGORIAN, $monat, $jahr);
                    try {
                        $daten = ReportService::getInstanz()->holeMonatsdatenFuerMitarbeiter($mid, $jahr, $monat);
                        $tw = (is_array($daten['tageswerte'] ?? null)) ? (array)$daten['tageswerte'] : [];
                        $ok = count($tw) === $days;
                        $details = 'Tage: ' . count($tw) . ' / ' . $days;
                        $add('ReportService Monatsraster', $ok, $details, (microtime(true) - $t0) * 1000.0);
                    } catch (Throwable $e) {
                        $add('ReportService Monatsraster', false, 'Fehler: ' . $e->getMessage(), (microtime(true) - $t0) * 1000.0);
                    }
                } else {
                    $add('ReportService Monatsraster', false, 'Übersprungen (kein User oder DB down)', (microtime(true) - $t0) * 1000.0);
                }

                // 5) PDFService Quick-Test (aktueller User, aktueller Monat)
                $t0 = microtime(true);
                if ($hauptDbOk && $mitarbeiter !== null) {
                    $mid = (int)($mitarbeiter['id'] ?? 0);
                    $jahr = (int)date('Y');
                    $monat = (int)date('n');
                    try {
                        $pdf = PDFService::getInstanz()->erzeugeMonatsPdfFuerMitarbeiter($mid, $jahr, $monat);
                        $ok = (is_string($pdf) && strlen($pdf) > 200 && str_starts_with($pdf, '%PDF'));
                        $details = $ok ? ('OK, ' . number_format(strlen($pdf)) . ' Bytes') : ('Ungültig/leer, ' . number_format(strlen((string)$pdf)) . ' Bytes');
                        $add('PDFService Monats-PDF', $ok, $details, (microtime(true) - $t0) * 1000.0);
                    } catch (Throwable $e) {
                        $add('PDFService Monats-PDF', false, 'Fehler: ' . $e->getMessage(), (microtime(true) - $t0) * 1000.0);
                    }
                } else {
                    $add('PDFService Monats-PDF', false, 'Übersprungen (kein User oder DB down)', (microtime(true) - $t0) * 1000.0);
                }

                // 6) FULL Smoke (mutierende Checks) nur bei smoke=2: alles in Transaktion + Rollback.
                $t0 = microtime(true);
                if ($smokeParam === '2' && $hauptDbOk && $mitarbeiter !== null) {
                    $mid = (int)($mitarbeiter['id'] ?? 0);
                    if ($mid <= 0) {
                        $add('DB Write (Rollback)', false, 'Übersprungen (keine Mitarbeiter-ID)', (microtime(true) - $t0) * 1000.0);
                    } else {
                        $pdo = null;
                        try {
                            $pdo = $db->getPdo();
                        } catch (Throwable $e) {
                            $pdo = null;
                        }

                        if (!($pdo instanceof \PDO)) {
                            $add('DB Write (Rollback)', false, 'Keine PDO-Verbindung', (microtime(true) - $t0) * 1000.0);
                        } else {
                            $ok = false;
                            $details = '';

                            $rand = '';
                            try {
                                $rand = bin2hex(random_bytes(3));
                            } catch (Throwable $e) {
                                $rand = substr(md5((string)mt_rand()), 0, 6);
                            }

                            $comment = 'SMOKETEST ' . date('Y-m-d H:i:s') . ' ' . $rand;
                            $auftragcode = 'SMOKE-' . date('His');

                            try {
                                $pdo->beginTransaction();

                                // Zeitbuchung (Kommen/Gehen) über Service (Quelle=web, damit keine Offline-Queue)
                                $now  = new \DateTimeImmutable('now');
                                $now2 = $now->modify('+1 minute');
                                $kid = ZeitService::getInstanz()->bucheKommen($mid, $now, 'web', null, $comment);
                                $gid = ZeitService::getInstanz()->bucheGehen($mid, $now2, 'web', null, $comment);

                                // Auftrag Start/Stop (Haupt) über Service
                                $aid = AuftragszeitService::getInstanz()->starteAuftrag($mid, $auftragcode, null);
                                $sid = AuftragszeitService::getInstanz()->stoppeAuftrag($mid, null, $auftragcode, 'abgeschlossen');

                                $ok = ($kid !== null && $gid !== null && $aid !== null && $sid !== null);
                                $details = 'K=' . (string)$kid . ', G=' . (string)$gid . ', Astart=' . (string)$aid . ', Astop=' . (string)$sid;
                            } catch (Throwable $e) {
                                $ok = false;
                                $details = 'Fehler: ' . $e->getMessage();
                            } finally {
                                try {
                                    if ($pdo->inTransaction()) {
                                        $pdo->rollBack();
                                    }
                                } catch (Throwable $e) {
                                    // ignore
                                }
                            }

                            $add('DB Write (Rollback)', $ok, $details !== '' ? $details : ($ok ? 'OK' : 'Fehler'), (microtime(true) - $t0) * 1000.0);
                        }
                    }
                } else {
                    // Kein Fehler, nur Info (sonst wirkt smoke=1 "rot" obwohl alles ok ist)
                    $add('DB Write (Rollback)', true, 'Übersprungen (nur bei smoke=2)', (microtime(true) - $t0) * 1000.0);
                }

                $smokeTest['total_ms'] = (microtime(true) - (float)$smokeTest['started_at']) * 1000.0;
            }
        }

        // Optional: Offline-Queue Status-Kachel (nur für Admins)
        $queueKachel = null;
        $darfQueueSehen = $this->authService->hatRecht('QUEUE_VERWALTEN')
            || $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro');

        if ($darfQueueSehen) {
            try {
                $queueKachel = QueueService::getInstanz()->holeStatusSummary();
            } catch (Throwable $e) {
                $queueKachel = [
                    'verfuegbar' => false,
                    'quelle' => 'unbekannt',
                    'offen' => 0,
                    'fehler' => 0,
                    'verarbeitet' => 0,
                    'letzte_erstellung' => null,
                    'letzte_ausfuehrung' => null,
                ];
            }
        }

        
        $pausenEntscheidungenOffenAnzahl = is_array($pausenEntscheidungenOffen) ? count($pausenEntscheidungenOffen) : 0;
require __DIR__ . '/../views/dashboard/index.php';
    }
}