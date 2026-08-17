<?php
declare(strict_types=1);

/**
 * ReportController
 *
 * Verantwortlich für:
 * - Anzeigen von Monatsberichten
 * - Download von Monats-PDFs
 * - Sammel-Export (ZIP) von Monats-PDFs (für berechtigte Rollen)
 */
class ReportController
{
    private AuthService $authService;
    private ReportService $reportService;
    private PDFService $pdfService;

    public function __construct()
    {
        $this->authService   = AuthService::getInstanz();
        $this->reportService = ReportService::getInstanz();
        $this->pdfService    = PDFService::getInstanz();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer Monatsreports für andere Mitarbeiter ansehen darf.
     *
     * Backward-Compatibility:
     * - Wenn das neue, feinere Recht (REPORT_MONAT_VIEW_ALL) nicht genutzt wird,
     *   akzeptieren wir zusätzlich das ältere Sammel-Recht REPORTS_ANSEHEN_ALLE.
     */
    private function hatReportMonatViewAllRecht(): bool
    {
        try {
            if ($this->authService->hatRecht('REPORT_MONAT_VIEW_ALL')) {
                return true;
            }
            // Legacy / gröberes Recht
            if ($this->authService->hatRecht('REPORTS_ANSEHEN_ALLE')) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer Monatsreports als Sammel-ZIP exportieren darf.
     *
     * Backward-Compatibility:
     * - Wenn REPORT_MONAT_EXPORT_ALL nicht verwendet wird, akzeptieren wir REPORTS_ANSEHEN_ALLE.
     */
    private function hatReportMonatExportAllRecht(): bool
    {
        try {
            if ($this->authService->hatRecht('REPORT_MONAT_EXPORT_ALL')) {
                return true;
            }
            // Legacy / gröberes Recht
            if ($this->authService->hatRecht('REPORTS_ANSEHEN_ALLE')) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }


    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH_MONATSABSCHLUSS = 'report_monatsabschluss';

    private function hatStundenkontoVerwaltenRecht(): bool
    {
        $legacyAdmin = $this->authService->istLegacyAdmin();

        try {
            return $this->authService->hatRecht('STUNDENKONTO_VERWALTEN') || $legacyAdmin;
        } catch (\Throwable $e) {
            return $legacyAdmin;
        }

        return $legacyAdmin;
    }

    private function istMonatVergangen(int $jahr, int $monat): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
        $cur = ((int)$now->format('Y')) * 100 + ((int)$now->format('n'));
        $tgt = ((int)$jahr) * 100 + ((int)$monat);
        return $tgt < $cur;
    }

    private function berechneDifferenzMinutenAusMonatswerten(?array $monatswerte): int
    {
        if (!is_array($monatswerte)) {
            return 0;
        }

        $istS  = (string)($monatswerte['iststunden'] ?? '0');
        $sollS = (string)($monatswerte['sollstunden'] ?? '0');

        $ist  = (float)str_replace(',', '.', $istS);
        $soll = (float)str_replace(',', '.', $sollS);

        $istMin  = (int)round($ist * 60.0);
        $sollMin = (int)round($soll * 60.0);

        return $istMin - $sollMin;
    }


    private function holeMonatsabschlussKorrektur(int $mitarbeiterId, int $jahr, int $monat): ?array
    {
        try {
            $dt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat), new \DateTimeZone('Europe/Berlin'));
            $wirksam = $dt->modify('last day of this month')->format('Y-m-d');
            $begruendung = sprintf('Monatsabschluss %04d-%02d', $jahr, $monat);

            $db = Database::getInstanz();
            $row = $db->fetchEine(
                "SELECT id, delta_minuten FROM stundenkonto_korrektur
                 WHERE mitarbeiter_id = :mid
                   AND wirksam_datum = :wd
                   AND typ = 'manuell'
                   AND begruendung = :b
                 LIMIT 1",
                [
                    ':mid' => $mitarbeiterId,
                    ':wd'  => $wirksam,
                    ':b'   => $begruendung,
                ]
            );

            if (is_array($row) && isset($row['id'])) {
                return [
                    'id' => (int)$row['id'],
                    'delta_minuten' => (int)($row['delta_minuten'] ?? 0),
                    'wirksam_datum' => $wirksam,
                    'begruendung' => $begruendung,
                ];
            }
        } catch (\Throwable $e) {
            // Tabelle kann in alten Ständen fehlen -> dann einfach als nicht gebucht behandeln
            return null;
        }

        return null;
    }

    /**
     * @param int[] $mitarbeiterIds
     * @return array<int,bool>
     */
    private function ladeMonatsabschlussStatusMap(array $mitarbeiterIds, int $jahr, int $monat): array
    {
        $ids = [];
        foreach ($mitarbeiterIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        try {
            $dt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat), new \DateTimeZone('Europe/Berlin'));
            $wirksam = $dt->modify('last day of this month')->format('Y-m-d');
            $begruendung = sprintf('Monatsabschluss %04d-%02d', $jahr, $monat);

            $params = [
                ':wd' => $wirksam,
                ':b' => $begruendung,
            ];
            $platzhalter = [];
            $i = 0;
            foreach (array_keys($ids) as $id) {
                $key = ':mid' . $i;
                $platzhalter[] = $key;
                $params[$key] = $id;
                $i++;
            }

            $rows = Database::getInstanz()->fetchAlle(
                "SELECT mitarbeiter_id
                 FROM stundenkonto_korrektur
                 WHERE mitarbeiter_id IN (" . implode(',', $platzhalter) . ")
                   AND wirksam_datum = :wd
                   AND typ = 'manuell'
                   AND begruendung = :b",
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $mid = (int)($row['mitarbeiter_id'] ?? 0);
            if ($mid > 0) {
                $map[$mid] = true;
            }
        }

        return $map;
    }

    private function bucheOderAktualisiereMonatsabschluss(int $mitarbeiterId, int $jahr, int $monat, int $deltaMinuten, int $erstelltVonMitarbeiterId): bool
    {
        try {
            $dt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat), new \DateTimeZone('Europe/Berlin'));
            $wirksam = $dt->modify('last day of this month')->format('Y-m-d');
            $begruendung = sprintf('Monatsabschluss %04d-%02d', $jahr, $monat);

            $db = Database::getInstanz();

            $existing = $this->holeMonatsabschlussKorrektur($mitarbeiterId, $jahr, $monat);
            if (is_array($existing) && isset($existing['id'])) {
                $db->ausfuehren(
                    "UPDATE stundenkonto_korrektur
                     SET delta_minuten = :delta
                     WHERE id = :id",
                    [
                        ':delta' => $deltaMinuten,
                        ':id'    => (int)$existing['id'],
                    ]
                );

                Logger::info('Stundenkonto-Monatsabschluss aktualisiert', [
                    'korrektur_id'   => (int)($existing['id'] ?? 0),
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'monat'          => $monat,
                    'wirksam_datum'  => $wirksam,
                    'delta_alt'      => (int)($existing['delta_minuten'] ?? 0),
                    'delta_neu'      => $deltaMinuten,
                    'begruendung'    => $begruendung,
                    'erstellt_von'   => $erstelltVonMitarbeiterId,
                ], $mitarbeiterId, null, 'stundenkonto');

                return true;
            }

            $db->ausfuehren(
                "INSERT INTO stundenkonto_korrektur
                    (mitarbeiter_id, wirksam_datum, delta_minuten, typ, batch_id, begruendung, erstellt_von_mitarbeiter_id)
                 VALUES
                    (:mid, :wd, :delta, 'manuell', NULL, :b, :eid)",
                [
                    ':mid'   => $mitarbeiterId,
                    ':wd'    => $wirksam,
                    ':delta' => $deltaMinuten,
                    ':b'     => $begruendung,
                    ':eid'   => $erstelltVonMitarbeiterId,
                ]
            );

            $korrekturId = 0;
            try {
                $korrekturId = (int)$db->letzteInsertId();
            } catch (\Throwable) {
                $korrekturId = 0;
            }

            Logger::info('Stundenkonto-Monatsabschluss gebucht', [
                'korrektur_id'   => $korrekturId,
                'mitarbeiter_id' => $mitarbeiterId,
                'jahr'           => $jahr,
                'monat'          => $monat,
                'wirksam_datum'  => $wirksam,
                'delta_minuten'  => $deltaMinuten,
                'begruendung'    => $begruendung,
                'erstellt_von'   => $erstelltVonMitarbeiterId,
            ], $mitarbeiterId, null, 'stundenkonto');

            return true;
        } catch (\Throwable $e) {
            Logger::error('Fehler beim Buchen des Stundenkonto-Monatsabschlusses', [
                'mitarbeiter_id' => $mitarbeiterId,
                'jahr'           => $jahr,
                'monat'          => $monat,
                'wirksam_datum'  => $wirksam ?? null,
                'delta_minuten'  => $deltaMinuten,
                'begruendung'    => $begruendung ?? null,
                'erstellt_von'   => $erstelltVonMitarbeiterId,
                'exception'      => $e->getMessage(),
            ], $mitarbeiterId, null, 'stundenkonto');
            return false;
        }
    }

    /**
     * Ermittelt die Ziel-Mitarbeiter-ID aus Request + Rechten.
     * - Standard: eigener Mitarbeiter.
     * - Wenn mitarbeiter_id angegeben und != eigener:
     *   nur erlaubt, wenn View-All-Recht vorhanden.
     */
    private function ermittleZielMitarbeiterId(): int
    {
        $mitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();
        if ($mitarbeiter === null || !isset($mitarbeiter['id'])) {
            return 0;
        }

        $eigeneId = (int)$mitarbeiter['id'];

        $reqId = null;
        if (isset($_GET['mitarbeiter_id'])) {
            $reqId = (int)$_GET['mitarbeiter_id'];
        } elseif (isset($_GET['mid'])) {
            $reqId = (int)$_GET['mid'];
        }

        if ($reqId === null || $reqId <= 0 || $reqId === $eigeneId) {
            return $eigeneId;
        }

        if ($this->hatReportMonatViewAllRecht()) {
            return $reqId;
        }

        // Harte Ablehnung, statt stillschweigend auf "eigen" zu fallen.
        return -1;
    }

    public function monatsuebersicht(int $jahr, int $monat): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $zielMitarbeiterId = $this->ermittleZielMitarbeiterId();
        if ($zielMitarbeiterId <= 0) {
            echo '<p>Fehler: Mitarbeiterdaten nicht gefunden.</p>';
            return;
        }
        if ($zielMitarbeiterId === -1) {
            echo '<p>Zugriff verweigert: Sie dürfen Monatsreports anderer Mitarbeiter nicht ansehen.</p>';
            return;
        }

        $daten = $this->reportService->holeMonatsdatenFuerMitarbeiter($zielMitarbeiterId, $jahr, $monat);

        $jahrWert        = $daten['jahr'] ?? $jahr;
        $monatWert       = $daten['monat'] ?? $monat;
        $monatswerte     = $daten['monatswerte'] ?? null;
        $tageswerte      = $daten['tageswerte'] ?? [];
        $monatszusammenfassung = $daten['monatszusammenfassung'] ?? null;

        $jahr  = (int)$jahrWert;
        $monat = (int)$monatWert;

        // Für Views/Erweiterungen (Dropdown etc.)
        $hatReportMonatViewAll   = $this->hatReportMonatViewAllRecht();
        $hatReportMonatExportAll = $this->hatReportMonatExportAllRecht();
        $mitarbeiterId           = $zielMitarbeiterId;

        // UI-Option: Mikro-Buchungen anzeigen (werden weiterhin nicht gewertet, aber sichtbar).
        $showMicro = (isset($_GET['show_micro']) && (string)$_GET['show_micro'] === '1');


        // Stundenkonto: Monatsabschluss (Differenz Soll/Ist) als Buchung ins Stundenkonto schreiben (nur für vergangene Monate).
        $csrfTokenMonatsabschluss = Csrf::token(self::CSRF_BEREICH_MONATSABSCHLUSS);
        $kannStundenkontoVerwalten = $this->hatStundenkontoVerwaltenRecht();
        $istMonatVergangen = $this->istMonatVergangen($jahr, $monat);

        $monatsabschlussBerechnetDeltaMinuten = $this->berechneDifferenzMinutenAusMonatswerten(is_array($monatswerte) ? $monatswerte : null);

        $monatsabschlussGebucht = false;
        $monatsabschlussGebuchtDeltaMinuten = 0;
        $monatsabschlussRow = $this->holeMonatsabschlussKorrektur($mitarbeiterId, $jahr, $monat);
        if (is_array($monatsabschlussRow) && isset($monatsabschlussRow['id'])) {
            $monatsabschlussGebucht = true;
            $monatsabschlussGebuchtDeltaMinuten = (int)($monatsabschlussRow['delta_minuten'] ?? 0);
        }

        $stundenkontoMonatsabschlussMsg = isset($_GET['sk_msg']) ? (string)$_GET['sk_msg'] : '';

        if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['aktion'] ?? '') === 'monatsabschluss_buchen') {
            if (!Csrf::istGueltig(self::CSRF_BEREICH_MONATSABSCHLUSS)) {
                $url = '?seite=report_monat&jahr=' . $jahr . '&monat=' . $monat;
                if ($hatReportMonatViewAll) {
                    $url .= '&mitarbeiter_id=' . $mitarbeiterId;
                }
                if ($showMicro) {
                    $url .= '&show_micro=1';
                }
                $url .= '&sk_msg=csrf';
                header('Location: ' . $url);
                return;
            }

            if (!$kannStundenkontoVerwalten) {
                $url = '?seite=report_monat&jahr=' . $jahr . '&monat=' . $monat;
                if ($hatReportMonatViewAll) {
                    $url .= '&mitarbeiter_id=' . $mitarbeiterId;
                }
                if ($showMicro) {
                    $url .= '&show_micro=1';
                }
                $url .= '&sk_msg=no_right';
                header('Location: ' . $url);
                return;
            }

            if (!$istMonatVergangen) {
                $url = '?seite=report_monat&jahr=' . $jahr . '&monat=' . $monat;
                if ($hatReportMonatViewAll) {
                    $url .= '&mitarbeiter_id=' . $mitarbeiterId;
                }
                if ($showMicro) {
                    $url .= '&show_micro=1';
                }
                $url .= '&sk_msg=not_past';
                header('Location: ' . $url);
                return;
            }

            $ang = $this->authService->holeAngemeldetenMitarbeiter();
            $vonId = (int)($ang['id'] ?? 0);
            if ($vonId <= 0) {
                $url = '?seite=report_monat&jahr=' . $jahr . '&monat=' . $monat;
                if ($hatReportMonatViewAll) {
                    $url .= '&mitarbeiter_id=' . $mitarbeiterId;
                }
                if ($showMicro) {
                    $url .= '&show_micro=1';
                }
                $url .= '&sk_msg=not_logged';
                header('Location: ' . $url);
                return;
            }

            $ok = $this->bucheOderAktualisiereMonatsabschluss($mitarbeiterId, $jahr, $monat, $monatsabschlussBerechnetDeltaMinuten, $vonId);

            $url = '?seite=report_monat&jahr=' . $jahr . '&monat=' . $monat;
            if ($hatReportMonatViewAll) {
                $url .= '&mitarbeiter_id=' . $mitarbeiterId;
            }
            if ($showMicro) {
                $url .= '&show_micro=1';
            }
            $url .= $ok ? '&sk_msg=ok' : '&sk_msg=err';
            header('Location: ' . $url);
            return;
        }

        // Mikro-Buchungs-Grenze (Sekunden) aus Konfiguration (Default 180).
        // Wichtig: Im Monatsreport kann ein Tag normale + Mikro-Blöcke enthalten.
        // Ohne show_micro wollen wir diese Mikro-Blöcke komplett ausblenden.
        $microBuchungMaxSeconds = 180;
        try {
            $cfg = KonfigurationService::getInstanz();
            $val = $cfg->getInt('micro_buchung_max_sekunden', 180);
            if ($val !== null) {
                $microBuchungMaxSeconds = (int)$val;
            }
        } catch (\Throwable $e) {
            $microBuchungMaxSeconds = 180;
        }
        if ($microBuchungMaxSeconds < 30 || $microBuchungMaxSeconds > 3600) {
            $microBuchungMaxSeconds = 180;
        }



        // Bearbeiten-Link in der Monatsübersicht (führt zur Tagesansicht/Korrektur)
        // Rechte wie in ZeitController (T-051): ZEITBUCHUNG_EDIT_SELF / ZEITBUCHUNG_EDIT_ALL
        $angemeldet = $this->authService->holeAngemeldetenMitarbeiter();
        $angemeldeteId = (int)($angemeldet['id'] ?? 0);

        $legacyAdmin = $this->authService->istLegacyAdmin();

        $kannEditAll  = ($this->authService->hatRecht('ZEITBUCHUNG_EDIT_ALL')) || $legacyAdmin;
        $kannEditSelf = ($this->authService->hatRecht('ZEITBUCHUNG_EDIT_SELF')) || $legacyAdmin;

        $darfZeitBearbeiten = ($kannEditAll || ($kannEditSelf && $angemeldeteId > 0 && $mitarbeiterId === $angemeldeteId));

        // Mitarbeiterliste für Auswahl (nur wenn View-All-Recht vorhanden).
        // Hinweis: Wir listen primär aktive Mitarbeiter. Falls der aktuell ausgewählte Mitarbeiter
        // inaktiv ist, laden wir ihn zusätzlich nach, damit die Auswahl stabil bleibt.
        $mitarbeiterListe = [];
        if ($hatReportMonatViewAll) {
            $mitarbeiterModel = new MitarbeiterModel();

            try {
                $mitarbeiterListe = $mitarbeiterModel->holeAlleAktiven();
            } catch (\Throwable $e) {
                $mitarbeiterListe = [];
            }

            $hatAktuellen = false;
            foreach ($mitarbeiterListe as $m) {
                $mid = (int)($m['id'] ?? 0);
                if ($mid === $mitarbeiterId) {
                    $hatAktuellen = true;
                    break;
                }
            }

            if (!$hatAktuellen && $mitarbeiterId > 0) {
                try {
                    $extra = $mitarbeiterModel->holeNachId($mitarbeiterId);
                    if ($extra !== null) {
                        $mitarbeiterListe[] = $extra;
                    }
                } catch (\Throwable $e) {
                    // Ignorieren
                }
            }

            // Sortierung (Nachname, Vorname), damit das Dropdown stabil bleibt.
            if (is_array($mitarbeiterListe) && count($mitarbeiterListe) > 1) {
                usort($mitarbeiterListe, function ($a, $b) {
                    $an = mb_strtolower(trim((string)($a['nachname'] ?? '')));
                    $av = mb_strtolower(trim((string)($a['vorname'] ?? '')));
                    $bn = mb_strtolower(trim((string)($b['nachname'] ?? '')));
                    $bv = mb_strtolower(trim((string)($b['vorname'] ?? '')));

                    $c = strcmp($an, $bn);
                    if ($c !== 0) {
                        return $c;
                    }
                    return strcmp($av, $bv);
                });
            }
        }

        $monatsabschlussStatusMap = [];
        if (!empty($istMonatVergangen)) {
            $statusIds = [];
            foreach ($mitarbeiterListe as $m) {
                $mid = (int)($m['id'] ?? 0);
                if ($mid > 0) {
                    $statusIds[$mid] = true;
                }
            }
            if ($mitarbeiterId > 0) {
                $statusIds[$mitarbeiterId] = true;
            }

            $monatsabschlussStatusMap = $this->ladeMonatsabschlussStatusMap(array_keys($statusIds), $jahr, $monat);
            if ($mitarbeiterId > 0) {
                $monatsabschlussStatusMap[$mitarbeiterId] = !empty($monatsabschlussGebucht);
            }
        }

        require __DIR__ . '/../views/report/monatsuebersicht.php';
    }

    public function monatsPdf(int $jahr, int $monat): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        // PDF-Response muss absolut "clean" sein:
        // - keine Warnungen/Notices im Output (sonst ist das PDF kaputt)
        // - möglichst keine Timeouts beim Generieren
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // WICHTIG: Bei aktivem display_errors können Warnungen/Notices in die PDF-Ausgabe geraten.
        // Daher Error-Handler + Output-Buffering einsetzen und vor dem Senden leeren.
        $errorHandlerAktiv = false;
        try {
            set_error_handler(function (int $severity, string $message, string $file, int $line) use ($jahr, $monat): bool {
                Logger::warn('PHP-Warnung/Notice während PDF-Generierung', [
                    'severity' => $severity,
                    'message'  => $message,
                    'file'     => $file,
                    'line'     => $line,
                    'jahr'     => $jahr,
                    'monat'    => $monat,
                ], null, null, 'pdf');
                // Ausgabe unterdrücken
                return true;
            });
            $errorHandlerAktiv = true;
        } catch (\Throwable $e) {
            $errorHandlerAktiv = false;
        }

        $obStartLevel = ob_get_level();
        @ob_start();

        $cleanup = function () use ($obStartLevel, $errorHandlerAktiv): void {
            while (ob_get_level() > $obStartLevel) {
                @ob_end_clean();
            }
            if ($errorHandlerAktiv) {
                try {
                    restore_error_handler();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        };

        $zielMitarbeiterId = $this->ermittleZielMitarbeiterId();
        if ($zielMitarbeiterId <= 0) {
            $cleanup();
            echo '<p>Fehler: Mitarbeiterdaten nicht gefunden.</p>';
            return;
        }
        if ($zielMitarbeiterId === -1) {
            $cleanup();
            echo '<p>Zugriff verweigert: Sie dürfen Monatsreports anderer Mitarbeiter nicht als PDF erzeugen.</p>';
            return;
        }

        $pdfInhalt = $this->pdfService->erzeugeMonatsPdfFuerMitarbeiter($zielMitarbeiterId, $jahr, $monat);

        // Buffer (Warnungen/Notices) verwerfen + Handler zurücksetzen
        $cleanup();

        if ($pdfInhalt === '') {
            echo '<p>PDF konnte nicht erzeugt werden.</p>';
            return;
        }

        // Feldtest-Hilfe (T-069): Für Browser-/Viewer-Checks ist es praktisch, die
        // Seitenanzahl ohne eigenes Parsing zu sehen. Wir senden daher einen Debug-Header.
        // Dieser ist harmlos (PDF bleibt unverändert) und erleichtert das schnelle Prüfen,
        // ob Auto-Compact/Bottom-Shift den Grenzfall wieder auf 1 Seite bringt.
        $pdfSeiten = $this->extrahierePdfSeitenanzahl($pdfInhalt);

        // Sicherstellen, dass wirklich nichts vor dem PDF im Output hängt
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Falls möglich: Output-Compression deaktivieren (Content-Length/Parsing-Probleme vermeiden)
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('display_errors', '0');

        // Falls möglich: Apache/Proxy-Kompression für dieses Response deaktivieren (sonst PDF-Parsing/Truncation möglich)
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
            @apache_setenv('dont-vary', '1');
        }

        header('Cache-Control: private, no-store, max-age=0, must-revalidate, no-transform');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        header('Content-Type: application/pdf');
        if ($pdfSeiten > 0) {
            header('X-Zeiterfassung-PDF-Pages: ' . $pdfSeiten);
        }
        header('Content-Disposition: inline; filename="monatsnachweis_' . $jahr . '_' . sprintf('%02d', $monat) . '_mitarbeiter_' . $zielMitarbeiterId . '.pdf"');
        echo $pdfInhalt;
        exit;
    }

    /**
     * Extrahiert die Seitenanzahl aus einem von unserem Generator erzeugten PDF.
     *
     * Hintergrund:
     * - Wir bauen das PDF bewusst minimalistisch.
     * - In Objekt 2 (Pages) steht ein eindeutiges "/Count N".
     *
     * Fallback:
     * - Falls das Muster nicht gefunden wird, zählen wir Vorkommen von "/Type /Page".
     */
    private function extrahierePdfSeitenanzahl(string $pdf): int
    {
        // Primär: Pages-Objekt mit /Type /Pages ... /Count N
        if (preg_match('~/Type\s*/Pages\b.*?/Count\s+(\d+)~s', $pdf, $m)) {
            $n = (int)($m[1] ?? 0);
            if ($n > 0) {
                return $n;
            }
        }

        // Fallback: Anzahl Page-Objekte zählen
        if (preg_match_all('~/Type\s*/Page\b~', $pdf, $m2)) {
            $c = is_array($m2[0] ?? null) ? count($m2[0]) : 0;
            if ($c > 0) {
                return $c;
            }
        }

        return 0;
    }

    /**
     * Exportiert alle Monats-PDFs als ZIP (pro Mitarbeiter 1 PDF).
     *
     * Route: ?seite=report_monat_export_all&jahr=YYYY&monat=MM
     */
    public function monatsPdfExportAll(int $jahr, int $monat): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Auch hier: Warnungen/Notices dürfen den ZIP-Output nicht zerstören.
        $errorHandlerAktiv = false;
        try {
            set_error_handler(function (int $severity, string $message, string $file, int $line) use ($jahr, $monat): bool {
                Logger::warn('PHP-Warnung/Notice während ZIP-Export', [
                    'severity' => $severity,
                    'message'  => $message,
                    'file'     => $file,
                    'line'     => $line,
                    'jahr'     => $jahr,
                    'monat'    => $monat,
                ], null, null, 'pdf');
                return true;
            });
            $errorHandlerAktiv = true;
        } catch (\Throwable $e) {
            $errorHandlerAktiv = false;
        }

        $obStartLevel = ob_get_level();
        @ob_start();

        $cleanup = function () use ($obStartLevel, $errorHandlerAktiv): void {
            while (ob_get_level() > $obStartLevel) {
                @ob_end_clean();
            }
            if ($errorHandlerAktiv) {
                try {
                    restore_error_handler();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        };

        if (!$this->hatReportMonatExportAllRecht()) {
            $cleanup();
            echo '<p>Zugriff verweigert: Sie dürfen keinen Sammel-Export erzeugen.</p>';
            return;
        }

        $mitarbeiterModel = new MitarbeiterModel();
        try {
            $alle = $mitarbeiterModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $alle = [];
        }

        if (!is_array($alle) || empty($alle)) {
            $cleanup();
            echo '<p>Keine aktiven Mitarbeiter gefunden.</p>';
            return;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'monatszip_');
        if ($tmpZip === false) {
            $cleanup();
            echo '<p>ZIP konnte nicht vorbereitet werden.</p>';
            return;
        }

        $zip = new \ZipArchive();
        $ok = $zip->open($tmpZip, \ZipArchive::OVERWRITE);
        if ($ok !== true) {
            @unlink($tmpZip);
            $cleanup();
            echo '<p>ZIP konnte nicht geöffnet werden.</p>';
            return;
        }

        $anzahl = 0;

        foreach ($alle as $m) {
            $mid = (int)($m['id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }

            $pdf = $this->pdfService->erzeugeMonatsPdfFuerMitarbeiter($mid, $jahr, $monat);
            if ($pdf === '') {
                continue;
            }

            $vn = trim((string)($m['vorname'] ?? ''));
            $nn = trim((string)($m['nachname'] ?? ''));
            $name = trim($nn . '_' . $vn);
            if ($name === '') {
                $name = 'mitarbeiter_' . $mid;
            }

            // Dateiname: nur sichere Zeichen
            $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name);
            $fileName = 'monatsnachweis_' . sprintf('%04d_%02d', $jahr, $monat) . '_' . $name . '_ID' . $mid . '.pdf';

            $zip->addFromString($fileName, $pdf);
            $anzahl++;
        }

        $zip->close();

        // Buffer (Warnungen/Notices) verwerfen + Handler zurücksetzen
        $cleanup();

        if ($anzahl <= 0) {
            @unlink($tmpZip);
            echo '<p>Keine PDFs konnten erzeugt werden.</p>';
            return;
        }

        $downloadName = 'monatsreports_' . sprintf('%04d_%02d', $jahr, $monat) . '.zip';

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('display_errors', '0');

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');

        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }
}
