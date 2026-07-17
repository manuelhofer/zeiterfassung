<?php
declare(strict_types=1);

/**
 * UrlaubController
 *
 * Verantwortlich für:
 * - Anzeigen der eigenen Urlaubsanträge
 * - (neu) einfachen Urlaubsantrag stellen (Minimal-Workflow über dieselbe Seite)
 * - Urlaub-Genehmigung (Backend): Liste + Genehmigen/Ablehnen
 */
class UrlaubController
{
    private AuthService $authService;
    private UrlaubService $urlaubService;

    /** Session-Key für CSRF im Bereich "urlaub_meine" (Antrag stellen + Storno). */
    private const CSRF_KEY_MEINE = 'urlaub_meine_csrf_token';
    private const CSRF_KEY_VERWALTUNG = 'urlaub_verwaltung_csrf_token';

    public function __construct()
    {
        $this->authService   = AuthService::getInstanz();
        $this->urlaubService = UrlaubService::getInstanz();
    }

    /**
     * CSRF-Token (Backend) für einfache Formular-POSTs.
     *
     * Hinweis:
     * - Backend ist öffentlich erreichbar → CSRF-Schutz ist Pflicht.
     * - Wir verwenden bewusst einen eigenen Session-Key pro Funktionsbereich.
     */
    private function holeOderErzeugeCsrfToken(string $sessionKey): string
    {
        $token = $_SESSION[$sessionKey] ?? '';
        if (is_string($token) && strlen($token) >= 20) {
            return $token;
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            // Fallback (sollte praktisch nie nötig sein)
            $token = bin2hex(pack('N', time())) . bin2hex(pack('N', random_int(1, PHP_INT_MAX)));
        }

        $_SESSION[$sessionKey] = $token;
        return $token;
    }

    private function istCsrfTokenGueltigAusPost(string $sessionKey): bool
    {
        $post = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $sess = $_SESSION[$sessionKey] ?? '';

        if (!is_string($sess) || $sess === '' || $post === '') {
            return false;
        }

        return hash_equals($sess, $post);
    }

    private function redirectZurGenehmigungListe(): void
    {
        header('Location: ?seite=urlaub_genehmigung');
    }

    private function redirectZurMeineAntraege(): void
    {
        header('Location: ?seite=urlaub_meine');
    }

    private function redirectZurUrlaubsverwaltung(string $queryString = ''): void
    {
        $ziel = '?seite=urlaub_verwaltung';
        if ($queryString !== '') {
            $ziel .= '&' . ltrim($queryString, '&');
        }

        header('Location: ' . $ziel);
    }

    /**
     * Verarbeitet Genehmigen/Ablehnen in der Genehmigungs-Liste (POST).
     *
     * Regeln:
     * - Nur Statuswechsel von 'offen' → ('genehmigt'|'abgelehnt').
     * - Eigene Anträge dürfen nur mit Recht `URLAUB_GENEHMIGEN_SELF` entschieden werden.
     * - Mit Recht `URLAUB_GENEHMIGEN_ALLE`: alle Urlaubsanträge entscheiden.
     * - Mit Recht `URLAUB_GENEHMIGEN`: nur Mitarbeiter entscheiden, für die man als Genehmiger eingetragen ist.
     */
    private function verarbeiteUrlaubGenehmigungPost(Database $db, int $genehmigerId, bool $darfAlle, bool $darfBereich, bool $darfSelf): void
    {
        $csrfKey = 'urlaub_genehmigung_csrf_token';

        if (!$this->istCsrfTokenGueltigAusPost($csrfKey)) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        $aktion = trim((string)($_POST['aktion'] ?? ''));
        $antragId = (int)($_POST['antrag_id'] ?? 0);
        $kommentar = trim((string)($_POST['kommentar_genehmiger'] ?? ''));

        if ($antragId <= 0) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Ungültige Antrags-ID.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        if ($aktion !== 'genehmigen' && $aktion !== 'ablehnen') {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Ungültige Aktion.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        if ($kommentar !== '' && mb_strlen($kommentar, 'UTF-8') > 2000) {
            $kommentar = mb_substr($kommentar, 0, 2000, 'UTF-8');
        }

        $statusNeu = ($aktion === 'genehmigen') ? 'genehmigt' : 'abgelehnt';

        try {
            $antrag = $db->fetchEine(
                'SELECT id, mitarbeiter_id, status, von_datum, bis_datum
                 FROM urlaubsantrag
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $antragId]
            );
        } catch (Throwable $e) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Urlaubsantrag konnte nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Urlaub-Genehmigung: Antrag konnte nicht geladen werden', [
                    'genehmiger_id' => $genehmigerId,
                    'antrag_id'     => $antragId,
                    'exception'     => $e->getMessage(),
                ], $genehmigerId, null, 'urlaub_genehmigung');
            }
            $this->redirectZurGenehmigungListe();
            return;
        }

        if ($antrag === null) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Urlaubsantrag nicht gefunden.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        $mitarbeiterIdAntrag = (int)($antrag['mitarbeiter_id'] ?? 0);
        $statusAlt = (string)($antrag['status'] ?? '');

        if ($mitarbeiterIdAntrag <= 0) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Urlaubsantrag ist ungültig (Mitarbeiter fehlt).';
            $this->redirectZurGenehmigungListe();
            return;
        }

        if ($mitarbeiterIdAntrag === $genehmigerId && !$darfSelf) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Eigene Urlaubsanträge können nicht genehmigt/abgelehnt werden.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        if ($statusAlt !== 'offen') {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Nur offene Anträge können genehmigt/abgelehnt werden.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        // Rechte für genau diesen Antrag nochmals serverseitig prüfen
        $hatRechtFuerAntrag = false;

        if ($darfAlle) {
            $hatRechtFuerAntrag = true;
        } elseif ($mitarbeiterIdAntrag === $genehmigerId) {
            $hatRechtFuerAntrag = $darfSelf;
        } elseif ($darfBereich) {
            try {
                $ok = $db->fetchEine(
                    'SELECT 1 AS ok
                     FROM mitarbeiter_genehmiger
                     WHERE mitarbeiter_id = :mid
                       AND genehmiger_mitarbeiter_id = :gid
                     LIMIT 1',
                    ['mid' => $mitarbeiterIdAntrag, 'gid' => $genehmigerId]
                );
            } catch (Throwable $e) {
                $ok = null;
            }

            if ($ok !== null) {
                $hatRechtFuerAntrag = true;
            }
        }

        if (!$hatRechtFuerAntrag) {
            $_SESSION['urlaub_genehmigung_flash_error'] = 'Keine Berechtigung für diesen Urlaubsantrag.';
            $this->redirectZurGenehmigungListe();
            return;
        }

        // Für T-020 (Legacy-Schutz): Beim Genehmigen zusätzlich Überlappung prüfen.
        $vonDatumAntrag = (string)($antrag['von_datum'] ?? '');
        $bisDatumAntrag = (string)($antrag['bis_datum'] ?? '');

        if ($statusNeu === 'genehmigt') {
            if ($vonDatumAntrag === '' || $bisDatumAntrag === '') {
                $_SESSION['urlaub_genehmigung_flash_error'] = 'Urlaubsantrag ist ungültig (Zeitraum fehlt).';
                $this->redirectZurGenehmigungListe();
                return;
            }

            // Optional (konfigurierbar): Genehmigung blockieren, wenn Resturlaub negativ ist.
            if ($this->urlaubService->istNegativerResturlaubGeblockt()) {
                $msg = $this->urlaubService->pruefeNegativenResturlaubBeiGenehmigung(
                    $mitarbeiterIdAntrag,
                    $vonDatumAntrag,
                    $bisDatumAntrag
                );

                if ($msg !== null) {
                    $_SESSION['urlaub_genehmigung_flash_error'] = $msg;
                    $this->redirectZurGenehmigungListe();
                    return;
                }
            }

            // Wenn bereits genehmigter Urlaub überlappt, darf dieser Antrag nicht genehmigt werden.
            $ueberlappung = $this->urlaubService->findeUeberlappendenGenehmigtenUrlaub($mitarbeiterIdAntrag, $vonDatumAntrag, $bisDatumAntrag);
            if ($ueberlappung !== null) {
                $ovVon = (string)($ueberlappung['von_datum'] ?? '');
                $ovBis = (string)($ueberlappung['bis_datum'] ?? '');
                $ovId  = (int)($ueberlappung['id'] ?? 0);

                $msg = 'Genehmigung nicht möglich: Es existiert bereits genehmigter Urlaub im Zeitraum ';
                $msg .= ($ovVon !== '' ? $ovVon : '?') . ' bis ' . ($ovBis !== '' ? $ovBis : '?');
                if ($ovId > 0) {
                    $msg .= ' (Antrag #' . $ovId . ').';
                } else {
                    $msg .= '.';
                }
                $msg .= ' Bitte Zeitraum prüfen oder Personalbüro kontaktieren.';

                $_SESSION['urlaub_genehmigung_flash_error'] = $msg;

                if (class_exists('Logger')) {
                    Logger::warn('Urlaub-Genehmigung: Genehmigung wegen Überlappung blockiert', [
                        'antrag_id'           => $antragId,
                        'mitarbeiter_id'      => $mitarbeiterIdAntrag,
                        'von'                 => $vonDatumAntrag,
                        'bis'                 => $bisDatumAntrag,
                        'overlap_antrag_id'   => $ovId,
                        'overlap_von'         => $ovVon,
	                        'overlap_bis'         => $ovBis,
	                        'darf_alle'           => $darfAlle ? 1 : 0,
	                        'darf_bereich'        => $darfBereich ? 1 : 0,
	                        'darf_self'           => $darfSelf ? 1 : 0,
                    ], $genehmigerId, null, 'urlaub_genehmigung');
                }

                $this->redirectZurGenehmigungListe();
                return;
            }
	        }

	        try {
	            // Atomar: nur dann updaten, wenn der Antrag noch offen ist.
	            // Zusätzlich (T-020): Beim Genehmigen per NOT EXISTS sicherstellen, dass kein anderer genehmigter Urlaub überlappt.
	            $sqlUpdate =
	                "UPDATE urlaubsantrag ua\n"
	                . "SET ua.status = :status,\n"
	                . "    ua.entscheidungs_mitarbeiter_id = :gid,\n"
	                . "    ua.entscheidungs_datum = NOW(),\n"
	                . "    ua.kommentar_genehmiger = :kommentar\n"
	                . "WHERE ua.id = :id\n"
	                . "  AND ua.status = 'offen'\n"
	                . "  AND ua.mitarbeiter_id = :mid";

	            // Self-Approval nur, wenn das entsprechende Recht vorhanden ist.
	            if (!$darfSelf) {
	                $sqlUpdate .= "\n  AND ua.mitarbeiter_id <> :gid";
	            }

	            if ($statusNeu === 'genehmigt') {
                $sqlUpdate .=
                    "\n  AND NOT EXISTS (\n"
                    . "      SELECT 1\n"
                    . "      FROM urlaubsantrag u2\n"
                    . "      WHERE u2.mitarbeiter_id = ua.mitarbeiter_id\n"
                    . "        AND u2.status = 'genehmigt'\n"
                    . "        AND u2.id <> ua.id\n"
                    . "        AND NOT (u2.bis_datum < ua.von_datum OR u2.von_datum > ua.bis_datum)\n"
                    . "  )";
            }

            $rows = $db->ausfuehren($sqlUpdate, [
                'status'    => $statusNeu,
                'gid'       => $genehmigerId,
                'kommentar' => ($kommentar !== '' ? $kommentar : null),
                'id'        => $antragId,
                'mid'       => $mitarbeiterIdAntrag,
            ]);
        } catch (Throwable $e) {
            $rows = 0;
            if (class_exists('Logger')) {
                Logger::error('Urlaub-Genehmigung: Update fehlgeschlagen', [
                    'genehmiger_id' => $genehmigerId,
                    'antrag_id'     => $antragId,
                    'status_neu'    => $statusNeu,
                    'exception'     => $e->getMessage(),
                ], $genehmigerId, null, 'urlaub_genehmigung');
            }
        }

        if ($rows !== 1) {
            // Wenn die Genehmigung wegen Überlappung (Race/Legacy) blockiert wurde, liefern wir eine klare Meldung.
            if ($statusNeu === 'genehmigt' && $vonDatumAntrag !== '' && $bisDatumAntrag !== '') {
                $ueber = $this->urlaubService->findeUeberlappendenGenehmigtenUrlaub($mitarbeiterIdAntrag, $vonDatumAntrag, $bisDatumAntrag);
                $ueberId = (is_array($ueber) ? (int)($ueber['id'] ?? 0) : 0);
                if ($ueber !== null && $ueberId > 0 && $ueberId !== $antragId) {
                    $ovVon = (string)($ueber['von_datum'] ?? '');
                    $ovBis = (string)($ueber['bis_datum'] ?? '');
                    $msg = 'Genehmigung nicht möglich: Es existiert bereits genehmigter Urlaub im Zeitraum ';
                    $msg .= ($ovVon !== '' ? $ovVon : '?') . ' bis ' . ($ovBis !== '' ? $ovBis : '?');
                    $msg .= ' (Antrag #' . $ueberId . '). Bitte Zeitraum prüfen oder Personalbüro kontaktieren.';
                    $_SESSION['urlaub_genehmigung_flash_error'] = $msg;
                } else {
                    $_SESSION['urlaub_genehmigung_flash_error'] = 'Urlaubsantrag konnte nicht aktualisiert werden (evtl. bereits entschieden).';
                }
            } else {
                $_SESSION['urlaub_genehmigung_flash_error'] = 'Urlaubsantrag konnte nicht aktualisiert werden (evtl. bereits entschieden).';
            }
            $this->redirectZurGenehmigungListe();
            return;
        }

        $_SESSION['urlaub_genehmigung_flash_ok'] = ($statusNeu === 'genehmigt')
            ? 'Urlaubsantrag wurde genehmigt.'
            : 'Urlaubsantrag wurde abgelehnt.';

        if (class_exists('Logger')) {
            Logger::info('Urlaub-Genehmigung: Antrag entschieden', [
                'antrag_id'      => $antragId,
                'mitarbeiter_id' => $mitarbeiterIdAntrag,
                'status_neu'     => $statusNeu,
                'kommentar'      => ($kommentar !== '' ? 'ja' : 'nein'),
	                'darf_alle'      => $darfAlle ? 1 : 0,
	                'darf_bereich'   => $darfBereich ? 1 : 0,
	                'darf_self'      => $darfSelf ? 1 : 0,
            ], $genehmigerId, null, 'urlaub_genehmigung');
        }

        $this->redirectZurGenehmigungListe();
    }

    /**
     * Mitarbeiter: eigenen Antrag stornieren (nur solange Status = "offen").
     */
    private function verarbeiteUrlaubMeineStornoPost(Database $db, int $mitarbeiterId): void
    {
        if (!$this->istCsrfTokenGueltigAusPost(self::CSRF_KEY_MEINE)) {
            $_SESSION['urlaub_flash_error'] = 'Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.';
            $this->redirectZurMeineAntraege();
            return;
        }

        $antragId = (int)($_POST['antrag_id'] ?? 0);
        if ($antragId <= 0) {
            $_SESSION['urlaub_flash_error'] = 'Ungültige Antrags-ID.';
            $this->redirectZurMeineAntraege();
            return;
        }

        // Besitz + Status prüfen
        try {
            $row = $db->fetchEine(
                'SELECT id, status, von_datum, bis_datum
                 FROM urlaubsantrag
                 WHERE id = :id AND mitarbeiter_id = :mid
                 LIMIT 1',
                ['id' => $antragId, 'mid' => $mitarbeiterId]
            );
        } catch (Throwable $e) {
            $row = null;
        }

        if (!is_array($row) || empty($row['id'])) {
            $_SESSION['urlaub_flash_error'] = 'Urlaubsantrag nicht gefunden.';
            $this->redirectZurMeineAntraege();
            return;
        }

        $status = (string)($row['status'] ?? '');
        if ($status !== 'offen') {
            $_SESSION['urlaub_flash_error'] = 'Nur offene Anträge können storniert werden.';
            $this->redirectZurMeineAntraege();
            return;
        }

        $betroffen = 0;
        try {
            $betroffen = $db->ausfuehren(
                "UPDATE urlaubsantrag SET status = 'storniert' WHERE id = :id AND mitarbeiter_id = :mid AND status = 'offen' LIMIT 1",
                ['id' => $antragId, 'mid' => $mitarbeiterId]
            );
        } catch (Throwable $e) {
            $betroffen = 0;
            if (class_exists('Logger')) {
                Logger::error('Urlaub: Fehler beim Stornieren des Urlaubsantrags (Backend)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'antrag_id'      => $antragId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaub');
            }
        }

        if ($betroffen !== 1) {
            $_SESSION['urlaub_flash_error'] = 'Storno ist fehlgeschlagen. Bitte erneut versuchen.';
            $this->redirectZurMeineAntraege();
            return;
        }

        $_SESSION['urlaub_flash_ok'] = 'Urlaubsantrag wurde storniert.';

        if (class_exists('Logger')) {
            Logger::info('Urlaub: Urlaubsantrag storniert (Backend)', [
                'antrag_id'      => $antragId,
                'mitarbeiter_id' => $mitarbeiterId,
            ], $mitarbeiterId, null, 'urlaub');
        }

        $this->redirectZurMeineAntraege();
    }

    /**
     * Übersicht der eigenen Urlaubsanträge.
     */
    public function meineAntraege(): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $mitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();
        if ($mitarbeiter === null || !isset($mitarbeiter['id'])) {
            echo '<p>Fehler: Mitarbeiterdaten nicht gefunden.</p>';
            return;
        }

        $mitarbeiterId = (int)$mitarbeiter['id'];

        $csrfToken = $this->holeOderErzeugeCsrfToken(self::CSRF_KEY_MEINE);

        // Flash-Meldungen (einfach, ohne separates Framework)
        $meldung = null;
        if (isset($_SESSION['urlaub_flash_ok'])) {
            $meldung = (string)$_SESSION['urlaub_flash_ok'];
            unset($_SESSION['urlaub_flash_ok']);
        }

        $fehlermeldung = null;
        if (isset($_SESSION['urlaub_flash_error'])) {
            $fehlermeldung = (string)$_SESSION['urlaub_flash_error'];
            unset($_SESSION['urlaub_flash_error']);
        }

        // Minimal: Urlaubsantrag stellen über dieselbe Seite (ohne neue Route)
        $aktion = isset($_GET['aktion']) ? trim((string)$_GET['aktion']) : '';
        $zeigeFormular = ($aktion === 'neu');

        $formular = [
            'von_datum'             => '',
            'bis_datum'             => '',
            'kommentar_mitarbeiter' => '',
        ];

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');

        if (!$istPost && $zeigeFormular) {
            $formular = [
                'von_datum'             => trim((string)($_GET['von_datum'] ?? '')),
                'bis_datum'             => trim((string)($_GET['bis_datum'] ?? '')),
                'kommentar_mitarbeiter' => trim((string)($_GET['kommentar_mitarbeiter'] ?? '')),
            ];
        }

        // POST: Storno (unabhängig vom Formular-Modus)
        if ($istPost) {
            $postAktion = trim((string)($_POST['aktion'] ?? ''));
            if ($postAktion === 'stornieren') {
                $db = Database::getInstanz();
                $this->verarbeiteUrlaubMeineStornoPost($db, $mitarbeiterId);
                return;
            }
        }

        // POST: Antrag anlegen
        if ($istPost && $zeigeFormular) {
            if (!$this->istCsrfTokenGueltigAusPost(self::CSRF_KEY_MEINE)) {
                $fehlermeldung = 'Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.';
            } else {
                $von = trim((string)($_POST['von_datum'] ?? ''));
                $bis = trim((string)($_POST['bis_datum'] ?? ''));
                $kommentar = trim((string)($_POST['kommentar_mitarbeiter'] ?? ''));

                $formular = [
                    'von_datum'             => $von,
                    'bis_datum'             => $bis,
                    'kommentar_mitarbeiter' => $kommentar,
                ];

                if ($von === '' || $bis === '') {
                    $fehlermeldung = 'Bitte geben Sie ein Von- und Bis-Datum an.';
                }

                $vonDt = null;
                $bisDt = null;

                if ($fehlermeldung === null) {
                    $vonDt = \DateTimeImmutable::createFromFormat('Y-m-d', $von);
                    $bisDt = \DateTimeImmutable::createFromFormat('Y-m-d', $bis);

                    if (!$vonDt || $vonDt->format('Y-m-d') !== $von || !$bisDt || $bisDt->format('Y-m-d') !== $bis) {
                        $fehlermeldung = 'Bitte geben Sie die Daten im Format YYYY-MM-DD an.';
                    }
                }

                if ($fehlermeldung === null && $vonDt !== null && $bisDt !== null) {
                    $diff = $vonDt->diff($bisDt);
                    if ($diff->invert === 1) {
                        $fehlermeldung = 'Das Bis-Datum muss am oder nach dem Von-Datum liegen.';
                    }
                }

                if ($fehlermeldung === null && $kommentar !== '' && mb_strlen($kommentar, 'UTF-8') > 2000) {
                    // Schutz: nicht unendlich große Texte speichern
                    $kommentar = mb_substr($kommentar, 0, 2000, 'UTF-8');
                    $formular['kommentar_mitarbeiter'] = $kommentar;
                }

                if ($fehlermeldung === null && $vonDt !== null && $bisDt !== null) {
                    // Blockiere, wenn der Zeitraum mit bereits offenem/genehmigtem Urlaub überlappt.
                    $ueberlappung = $this->urlaubService->findeUeberlappendenAktivenUrlaub($mitarbeiterId, $von, $bis);
                    if ($ueberlappung !== null) {
                        $ovVon = (string)($ueberlappung['von_datum'] ?? '');
                        $ovBis = (string)($ueberlappung['bis_datum'] ?? '');
                        $ovId  = (int)($ueberlappung['id'] ?? 0);

                        $msg = 'Es existiert bereits ein offener oder genehmigter Antrag im Zeitraum ';
                        $msg .= ($ovVon !== '' ? $this->formatiereDatumDeutsch($ovVon) : '?')
                            . ' bis '
                            . ($ovBis !== '' ? $this->formatiereDatumDeutsch($ovBis) : '?');
                        if ($ovId > 0) {
                            $msg .= ' (Antrag #' . $ovId . ').';
                        } else {
                            $msg .= '.';
                        }
                        $msg .= ' Bitte Zeitraum anpassen oder Personalbüro kontaktieren.';

                        $fehlermeldung = $msg;
                    }
                }

                if ($fehlermeldung === null && $vonDt !== null && $bisDt !== null) {
                    // Tage gesamt = Arbeitstage (inkl. Start- und Endtag),
                    // Wochenenden, betriebsfreie Feiertage und Betriebsferien werden nicht gezählt.
                    $tageGesamt = $this->urlaubService->berechneTageGesamtAlsArbeitstageString($mitarbeiterId, $von, $bis);

                    // UX/Schutz: Wenn im Zeitraum keine verrechenbaren Urlaubstage liegen (z. B. komplett
                    // Wochenende/Feiertag/Betriebsferien), macht ein Antrag keinen Sinn und führt zu
                    // verwirrenden Einträgen wie "0.00 Tage".
                    if (round((float)$tageGesamt, 2) <= 0.0) {
                        $fehlermeldung = 'Der Zeitraum enthält keine verrechenbaren Urlaubstage (Wochenende/Feiertag/Betriebsferien). Bitte Zeitraum anpassen.';
                    }

                    // Optional (konfigurierbar): Antrag blockieren, wenn Resturlaub dadurch negativ würde.
                    if ($fehlermeldung === null && $this->urlaubService->istNegativerResturlaubGeblockt()) {
                        $msg = $this->urlaubService->pruefeNegativenResturlaubBeiNeuemAntrag($mitarbeiterId, $von, $bis);
                        if ($msg !== null) {
                            $fehlermeldung = $msg;
                        }
                    }

                    if ($fehlermeldung === null) {
                        try {
                            $db = Database::getInstanz();
                            $db->ausfuehren(
                                'INSERT INTO urlaubsantrag (mitarbeiter_id, von_datum, bis_datum, tage_gesamt, kommentar_mitarbeiter)
                                 VALUES (:mid, :von, :bis, :tage, :kommentar)',
                                [
                                    'mid'       => $mitarbeiterId,
                                    'von'       => $von,
                                    'bis'       => $bis,
                                    'tage'      => $tageGesamt,
                                    'kommentar' => ($kommentar !== '' ? $kommentar : null),
                                ]
                            );

                            $_SESSION['urlaub_flash_ok'] = 'Urlaubsantrag wurde gespeichert (Status: offen).';
                            $this->redirectZurMeineAntraege();
                            return;
                        } catch (\Throwable $e) {
                            $fehlermeldung = 'Der Urlaubsantrag konnte nicht gespeichert werden.';
                            if (class_exists('Logger')) {
                                Logger::error('Fehler beim Anlegen eines Urlaubsantrags', [
                                    'mitarbeiter_id' => $mitarbeiterId,
                                    'von'            => $von,
                                    'bis'            => $bis,
                                    'exception'      => $e->getMessage(),
                                ], $mitarbeiterId, null, 'urlaub');
                            }
                        }
                    }
                }
            }

            // Bei Fehlern Formular sichtbar lassen
            $zeigeFormular = true;
        }

        $antraege = $this->urlaubService->holeAntraegeFuerMitarbeiter($mitarbeiterId);

        // T-021 (Teil 1, MVP): Urlaubssaldo für das aktuelle Jahr (Anspruch/Genehmigt/Offen/Verbleibend)
        // Hinweis: Übertrag ist in diesem MVP noch nicht implementiert.
        $jahrAktuell = (int)(new \DateTimeImmutable('now'))->format('Y');
        $urlaubSaldo = $this->urlaubService->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahrAktuell);

        // T-023: Vorschau im Formular (Verfügbar / Dieser Antrag / Nach diesem Antrag) analog Terminal.
        $urlaubVorschau = null;
        if ($zeigeFormular && is_array($urlaubSaldo)) {
            $vonTmp = (string)($formular['von_datum'] ?? '');
            $bisTmp = (string)($formular['bis_datum'] ?? '');

            if ($vonTmp !== '' && $bisTmp !== '') {
                $vonDt = \DateTimeImmutable::createFromFormat('Y-m-d', $vonTmp);
                $bisDt = \DateTimeImmutable::createFromFormat('Y-m-d', $bisTmp);

                if ($vonDt && $bisDt && $vonDt->format('Y-m-d') === $vonTmp && $bisDt->format('Y-m-d') === $bisTmp) {
                    $diff = $vonDt->diff($bisDt);
                    if ($diff->invert === 0) {
                        $tageNeu = (float)$this->urlaubService->berechneTageGesamtAlsArbeitstageString($mitarbeiterId, $vonTmp, $bisTmp);
                        $verfuegbar = isset($urlaubSaldo['verbleibend']) ? (float)$urlaubSaldo['verbleibend'] : 0.0;
                        $nach = $verfuegbar - $tageNeu;
                        $kalendertage = ((int)$diff->days) + 1;
                        $wochenendtage = 0;
                        for ($d = $vonDt; $d <= $bisDt; $d = $d->modify('+1 day')) {
                            $wochentag = (int)$d->format('N');
                            if ($wochentag >= 6) {
                                $wochenendtage++;
                            }
                        }

                        $hinweise = [];
                        if ($wochenendtage > 0) {
                            $hinweise[] = $wochenendtage === 1
                                ? '1 Wochenendtag wird nicht als Urlaubstag gezählt.'
                                : $wochenendtage . ' Wochenendtage werden nicht als Urlaubstage gezählt.';
                        }

                        $nichtGezaehlt = max(0.0, (float)$kalendertage - $tageNeu);
                        if ($nichtGezaehlt > (float)$wochenendtage + 0.01) {
                            $hinweise[] = 'Betriebsfreie Feiertage oder Betriebsferien im Zeitraum werden ebenfalls nicht als Urlaubstage gezählt.';
                        }

                        $warnung = null;
                        $ueberlappung = $this->urlaubService->findeUeberlappendenAktivenUrlaub($mitarbeiterId, $vonTmp, $bisTmp);
                        if ($ueberlappung !== null) {
                            $ovVon = (string)($ueberlappung['von_datum'] ?? '');
                            $ovBis = (string)($ueberlappung['bis_datum'] ?? '');
                            $ovStatus = (string)($ueberlappung['status'] ?? '');
                            $statusText = ($ovStatus === 'genehmigt')
                                ? 'genehmigten'
                                : (($ovStatus === 'offen') ? 'offenen' : 'aktiven');

                            $warnung = 'Das geht so nicht: Der Zeitraum überschneidet sich mit einem bereits '
                                . $statusText
                                . ' Urlaubsantrag vom '
                                . ($ovVon !== '' ? $this->formatiereDatumDeutsch($ovVon) : '?')
                                . ' bis '
                                . ($ovBis !== '' ? $this->formatiereDatumDeutsch($ovBis) : '?')
                                . '. Bitte Zeitraum anpassen oder Personalbüro kontaktieren.';
                        }

                        if ($warnung === null && $nach < 0 && $this->urlaubService->istNegativerResturlaubGeblockt()) {
                            $warnung = 'Achtung: Nach diesem Antrag wäre der Resturlaub negativ – der Antrag wird beim Speichern blockiert.';
                        }

                        $urlaubVorschau = [
                            'tage_antrag' => number_format($tageNeu, 2, '.', ''),
                            'verfuegbar'  => number_format($verfuegbar, 2, '.', ''),
                            'nach_antrag' => number_format($nach, 2, '.', ''),
                            'warnung'     => $warnung,
                            'hinweise'    => $hinweise,
                        ];
                    }
                }
            }
        }

        require __DIR__ . '/../views/urlaub/meine_antraege.php';
    }

    private function formatiereDatumDeutsch(string $datum): string
    {
        $datum = trim($datum);
        if ($datum === '') {
            return '';
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) === 1) {
                return (new \DateTimeImmutable($datum))->format('d.m.Y');
            }
        } catch (\Throwable $e) {
            return $datum;
        }

        return $datum;
    }

    /**
     * @return array{darf_alle:bool,darf_bereich:bool,darf_self:bool}
     */
    private function ermittleUrlaubGenehmigungsrechte(): array
    {
        $legacyAdmin = false;
        if (method_exists($this->authService, 'hatRolle')) {
            $legacyAdmin = $this->authService->hatRolle('Chef')
                || $this->authService->hatRolle('Personalbüro')
                || $this->authService->hatRolle('Personalbuero');
        }

        return [
            'darf_alle' => $legacyAdmin || $this->authService->hatRecht('URLAUB_GENEHMIGEN_ALLE'),
            'darf_bereich' => $this->authService->hatRecht('URLAUB_GENEHMIGEN'),
            'darf_self' => $this->authService->hatRecht('URLAUB_GENEHMIGEN_SELF'),
        ];
    }

    private function darfUrlaubsantragBearbeiten(
        Database $db,
        int $actorId,
        int $mitarbeiterIdAntrag,
        bool $darfAlle,
        bool $darfBereich,
        bool $darfSelf
    ): bool {
        if ($actorId <= 0 || $mitarbeiterIdAntrag <= 0) {
            return false;
        }

        if ($mitarbeiterIdAntrag === $actorId) {
            return $darfSelf || $darfAlle;
        }

        if ($darfAlle) {
            return true;
        }

        if (!$darfBereich) {
            return false;
        }

        try {
            $row = $db->fetchEine(
                'SELECT 1 AS ok
                 FROM mitarbeiter_genehmiger
                 WHERE mitarbeiter_id = :mid
                   AND genehmiger_mitarbeiter_id = :gid
                 LIMIT 1',
                ['mid' => $mitarbeiterIdAntrag, 'gid' => $actorId]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return $row !== null;
    }

    private function baueUrlaubVerwaltungQueryAusPost(): string
    {
        $params = [];

        $mitarbeiterId = (int)($_POST['filter_mitarbeiter_id'] ?? 0);
        if ($mitarbeiterId > 0) {
            $params['mitarbeiter_id'] = (string)$mitarbeiterId;
        }

        $status = trim((string)($_POST['filter_status'] ?? 'aktiv'));
        if ($status !== '') {
            $params['status'] = $status;
        }

        $jahr = (int)($_POST['filter_jahr'] ?? (int)date('Y'));
        if ($jahr >= 2000 && $jahr <= 2100) {
            $params['jahr'] = (string)$jahr;
        }

        return http_build_query($params);
    }

    private function verarbeiteUrlaubVerwaltungDirektEintragenPost(
        Database $db,
        int $actorId,
        bool $darfAlle,
        bool $darfBereich,
        bool $darfSelf
    ): void {
        $returnQuery = $this->baueUrlaubVerwaltungQueryAusPost();

        if (!$this->istCsrfTokenGueltigAusPost(self::CSRF_KEY_VERWALTUNG)) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $mitarbeiterId = (int)($_POST['urlaub_neu_mitarbeiter_id'] ?? 0);
        $von = trim((string)($_POST['urlaub_neu_von_datum'] ?? ''));
        $bis = trim((string)($_POST['urlaub_neu_bis_datum'] ?? ''));
        $begruendung = trim((string)($_POST['urlaub_neu_begruendung'] ?? ''));

        if ($mitarbeiterId <= 0) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Bitte einen Mitarbeiter auswählen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if (!$this->darfUrlaubsantragBearbeiten($db, $actorId, $mitarbeiterId, $darfAlle, $darfBereich, $darfSelf)) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Keine Berechtigung für diesen Mitarbeiter.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if ($von === '' || $bis === '') {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Bitte Von- und Bis-Datum angeben.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $vonDt = \DateTimeImmutable::createFromFormat('Y-m-d', $von);
        $bisDt = \DateTimeImmutable::createFromFormat('Y-m-d', $bis);
        if (!$vonDt || $vonDt->format('Y-m-d') !== $von || !$bisDt || $bisDt->format('Y-m-d') !== $bis) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Bitte gültige Datumswerte auswählen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if ($vonDt > $bisDt) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Das Bis-Datum muss am oder nach dem Von-Datum liegen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if ($begruendung === '') {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Bitte eine Begründung für den direkten Urlaubseintrag angeben.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if (mb_strlen($begruendung, 'UTF-8') > 2000) {
            $begruendung = mb_substr($begruendung, 0, 2000, 'UTF-8');
        }

        $ueberlappung = $this->urlaubService->findeUeberlappendenAktivenUrlaub($mitarbeiterId, $von, $bis);
        if ($ueberlappung !== null) {
            $ovVon = (string)($ueberlappung['von_datum'] ?? '');
            $ovBis = (string)($ueberlappung['bis_datum'] ?? '');
            $ovStatus = (string)($ueberlappung['status'] ?? 'aktiv');
            $_SESSION['urlaub_verwaltung_flash_error'] =
                'Direkteintrag nicht möglich: Der Zeitraum überschneidet sich mit einem bereits '
                . ($ovStatus === 'genehmigt' ? 'genehmigten' : 'offenen')
                . ' Urlaubsantrag vom '
                . ($ovVon !== '' ? $this->formatiereDatumDeutsch($ovVon) : '?')
                . ' bis '
                . ($ovBis !== '' ? $this->formatiereDatumDeutsch($ovBis) : '?')
                . '.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $tageGesamt = $this->urlaubService->berechneTageGesamtAlsArbeitstageString($mitarbeiterId, $von, $bis);
        if (round((float)$tageGesamt, 2) <= 0.0) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Der Zeitraum enthält keine verrechenbaren Urlaubstage (Wochenende/Feiertag/Betriebsferien). Bitte Zeitraum anpassen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if ($this->urlaubService->istNegativerResturlaubGeblockt()) {
            $msg = $this->urlaubService->pruefeNegativenResturlaubBeiNeuemAntrag($mitarbeiterId, $von, $bis);
            if ($msg !== null) {
                $_SESSION['urlaub_verwaltung_flash_error'] = $msg;
                $this->redirectZurUrlaubsverwaltung($returnQuery);
                return;
            }
        }

        $kommentarGenehmiger = 'Direkteintrag durch Urlaubsverwaltung: ' . $begruendung;

        try {
            $db->ausfuehren(
                "INSERT INTO urlaubsantrag
                    (mitarbeiter_id, von_datum, bis_datum, tage_gesamt, status, entscheidungs_mitarbeiter_id, entscheidungs_datum, kommentar_mitarbeiter, kommentar_genehmiger)
                 VALUES
                    (:mid, :von, :bis, :tage, 'genehmigt', :actor_id, NOW(), :kommentar_mitarbeiter, :kommentar_genehmiger)",
                [
                    'mid' => $mitarbeiterId,
                    'von' => $von,
                    'bis' => $bis,
                    'tage' => $tageGesamt,
                    'actor_id' => $actorId,
                    'kommentar_mitarbeiter' => 'Mündlich mitgeteilt / durch Verwaltung eingetragen.',
                    'kommentar_genehmiger' => $kommentarGenehmiger,
                ]
            );
        } catch (\Throwable $e) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Urlaub konnte nicht eingetragen werden.';
            if (class_exists('Logger')) {
                Logger::error('Urlaubsverwaltung: Direkteintrag fehlgeschlagen', [
                    'actor_id' => $actorId,
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von' => $von,
                    'bis' => $bis,
                    'exception' => $e->getMessage(),
                ], $actorId, null, 'urlaub_verwaltung');
            }
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $_SESSION['urlaub_verwaltung_flash_ok'] = 'Urlaub wurde direkt als genehmigt eingetragen.';

        if (class_exists('Logger')) {
            Logger::info('Urlaubsverwaltung: Urlaub direkt eingetragen', [
                'mitarbeiter_id' => $mitarbeiterId,
                'von' => $von,
                'bis' => $bis,
                'tage' => $tageGesamt,
                'begruendung' => $begruendung,
            ], $actorId, null, 'urlaub_verwaltung');
        }

        $this->redirectZurUrlaubsverwaltung($returnQuery);
    }

    private function verarbeiteUrlaubVerwaltungStornoPost(
        Database $db,
        int $actorId,
        bool $darfAlle,
        bool $darfBereich,
        bool $darfSelf
    ): void {
        $returnQuery = $this->baueUrlaubVerwaltungQueryAusPost();

        if (!$this->istCsrfTokenGueltigAusPost(self::CSRF_KEY_VERWALTUNG)) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $aktion = trim((string)($_POST['aktion'] ?? ''));
        $antragId = (int)($_POST['antrag_id'] ?? 0);
        $begruendung = trim((string)($_POST['storno_begruendung'] ?? ''));

        if ($aktion !== 'stornieren') {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Ungültige Aktion.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if ($antragId <= 0) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Ungültige Antrags-ID.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if ($begruendung === '') {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Bitte eine Begründung für die Stornierung angeben.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if (mb_strlen($begruendung, 'UTF-8') > 2000) {
            $begruendung = mb_substr($begruendung, 0, 2000, 'UTF-8');
        }

        try {
            $antrag = $db->fetchEine(
                'SELECT id, mitarbeiter_id, status, von_datum, bis_datum, kommentar_genehmiger
                 FROM urlaubsantrag
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $antragId]
            );
        } catch (\Throwable $e) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Urlaubsantrag konnte nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Urlaubsverwaltung: Antrag konnte nicht geladen werden', [
                    'actor_id' => $actorId,
                    'antrag_id' => $antragId,
                    'exception' => $e->getMessage(),
                ], $actorId, null, 'urlaub_verwaltung');
            }
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if (!is_array($antrag) || empty($antrag['id'])) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Urlaubsantrag nicht gefunden.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $mitarbeiterIdAntrag = (int)($antrag['mitarbeiter_id'] ?? 0);
        $statusAlt = (string)($antrag['status'] ?? '');

        if (!in_array($statusAlt, ['offen', 'genehmigt'], true)) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Nur offene oder genehmigte Urlaubsanträge können storniert werden.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        if (!$this->darfUrlaubsantragBearbeiten($db, $actorId, $mitarbeiterIdAntrag, $darfAlle, $darfBereich, $darfSelf)) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Keine Berechtigung für diesen Urlaubsantrag.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $alterKommentar = trim((string)($antrag['kommentar_genehmiger'] ?? ''));
        $stornoZeile = 'Storno/Rücknahme: ' . $begruendung;
        $neuerKommentar = ($alterKommentar === '') ? $stornoZeile : $alterKommentar . "\n\n" . $stornoZeile;

        try {
            $betroffen = $db->ausfuehren(
                "UPDATE urlaubsantrag
                 SET status = 'storniert',
                     entscheidungs_mitarbeiter_id = :actor_id,
                     entscheidungs_datum = NOW(),
                     kommentar_genehmiger = :kommentar
                 WHERE id = :id
                   AND status IN ('offen', 'genehmigt')
                 LIMIT 1",
                [
                    'actor_id' => $actorId,
                    'kommentar' => $neuerKommentar,
                    'id' => $antragId,
                ]
            );
        } catch (\Throwable $e) {
            $betroffen = 0;
            if (class_exists('Logger')) {
                Logger::error('Urlaubsverwaltung: Storno fehlgeschlagen', [
                    'actor_id' => $actorId,
                    'antrag_id' => $antragId,
                    'status_alt' => $statusAlt,
                    'exception' => $e->getMessage(),
                ], $actorId, null, 'urlaub_verwaltung');
            }
        }

        if ($betroffen !== 1) {
            $_SESSION['urlaub_verwaltung_flash_error'] = 'Storno ist fehlgeschlagen. Bitte erneut versuchen.';
            $this->redirectZurUrlaubsverwaltung($returnQuery);
            return;
        }

        $_SESSION['urlaub_verwaltung_flash_ok'] = 'Urlaubsantrag wurde storniert/rückgängig gemacht.';

        if (class_exists('Logger')) {
            Logger::info('Urlaubsverwaltung: Antrag storniert', [
                'antrag_id' => $antragId,
                'mitarbeiter_id' => $mitarbeiterIdAntrag,
                'status_alt' => $statusAlt,
                'status_neu' => 'storniert',
                'von' => (string)($antrag['von_datum'] ?? ''),
                'bis' => (string)($antrag['bis_datum'] ?? ''),
                'begruendung' => $begruendung,
            ], $actorId, null, 'urlaub_verwaltung');
        }

        $this->redirectZurUrlaubsverwaltung($returnQuery);
    }

    /**
     * Verwaltung aller sichtbaren Urlaubsanträge inkl. Storno/Rücknahme.
     */
    public function verwaltung(): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $mitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();
        if ($mitarbeiter === null || !isset($mitarbeiter['id'])) {
            http_response_code(403);
            echo '<p>Fehler: Mitarbeiterdaten nicht gefunden.</p>';
            return;
        }

        $actorId = (int)$mitarbeiter['id'];
        if ($actorId <= 0) {
            http_response_code(403);
            echo '<p>Fehler: Ungültige Mitarbeiter-ID.</p>';
            return;
        }

        $rechte = $this->ermittleUrlaubGenehmigungsrechte();
        $darfAlle = $rechte['darf_alle'];
        $darfBereich = $rechte['darf_bereich'];
        $darfSelf = $rechte['darf_self'];

        $db = Database::getInstanz();

        if (!$darfAlle && !$darfBereich && !$darfSelf) {
            http_response_code(403);
            require __DIR__ . '/../views/layout/header.php';
            echo '<section><h2>Keine Berechtigung</h2><p>Sie haben keine Berechtigung zur Urlaubsverwaltung.</p></section>';
            require __DIR__ . '/../views/layout/footer.php';
            return;
        }

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');
        if ($istPost) {
            $aktion = trim((string)($_POST['aktion'] ?? ''));
            if ($aktion === 'direkt_eintragen') {
                $this->verarbeiteUrlaubVerwaltungDirektEintragenPost($db, $actorId, $darfAlle, $darfBereich, $darfSelf);
            } else {
                $this->verarbeiteUrlaubVerwaltungStornoPost($db, $actorId, $darfAlle, $darfBereich, $darfSelf);
            }
            return;
        }

        $meldung = null;
        if (isset($_SESSION['urlaub_verwaltung_flash_ok'])) {
            $meldung = (string)$_SESSION['urlaub_verwaltung_flash_ok'];
            unset($_SESSION['urlaub_verwaltung_flash_ok']);
        }

        $fehlermeldung = null;
        if (isset($_SESSION['urlaub_verwaltung_flash_error'])) {
            $fehlermeldung = (string)$_SESSION['urlaub_verwaltung_flash_error'];
            unset($_SESSION['urlaub_verwaltung_flash_error']);
        }

        $filterMitarbeiterId = (int)($_GET['mitarbeiter_id'] ?? 0);
        $filterStatus = trim((string)($_GET['status'] ?? 'aktiv'));
        $filterJahr = (int)($_GET['jahr'] ?? (int)date('Y'));
        if ($filterJahr < 2000 || $filterJahr > 2100) {
            $filterJahr = (int)date('Y');
        }

        $erlaubteStatus = ['aktiv', 'alle', 'offen', 'genehmigt', 'abgelehnt', 'storniert'];
        if (!in_array($filterStatus, $erlaubteStatus, true)) {
            $filterStatus = 'aktiv';
        }

        try {
            $mitarbeiterListe = (new MitarbeiterModel())->holeAlleAktiven();
        } catch (\Throwable $e) {
            $mitarbeiterListe = [];
            if (class_exists('Logger')) {
                Logger::error('Urlaubsverwaltung: Mitarbeiterliste konnte nicht geladen werden', [
                    'exception' => $e->getMessage(),
                ], $actorId, null, 'urlaub_verwaltung');
            }
        }

        $params = [];
        $sql =
            "SELECT
                ua.id,
                ua.mitarbeiter_id,
                ua.von_datum,
                ua.bis_datum,
                ua.tage_gesamt,
                ua.status,
                ua.antrags_datum,
                ua.entscheidungs_datum,
                ua.kommentar_mitarbeiter,
                ua.kommentar_genehmiger,
                COALESCE(
                    NULLIF(CONCAT_WS(' ', NULLIF(TRIM(m.vorname), ''), NULLIF(TRIM(m.nachname), '')), ''),
                    NULLIF(TRIM(m.benutzername), ''),
                    CONCAT('Mitarbeiter #', m.id)
                ) AS mitarbeiter_name,
                COALESCE(
                    NULLIF(CONCAT_WS(' ', NULLIF(TRIM(g.vorname), ''), NULLIF(TRIM(g.nachname), '')), ''),
                    NULLIF(TRIM(g.benutzername), ''),
                    CASE WHEN g.id IS NULL THEN NULL ELSE CONCAT('Mitarbeiter #', g.id) END
                ) AS entscheidungs_mitarbeiter_name
             FROM urlaubsantrag ua
             INNER JOIN mitarbeiter m ON m.id = ua.mitarbeiter_id
             LEFT JOIN mitarbeiter g ON g.id = ua.entscheidungs_mitarbeiter_id";

        $where = [];

        if ($darfAlle) {
            // Vollzugriff: kein zusätzlicher Scope.
        } elseif ($darfBereich) {
            $sql .=
                ' LEFT JOIN mitarbeiter_genehmiger mg
                    ON mg.mitarbeiter_id = ua.mitarbeiter_id
                   AND mg.genehmiger_mitarbeiter_id = :gid_scope';
            $params['gid_scope'] = $actorId;

            if ($darfSelf) {
                $where[] = '(ua.mitarbeiter_id = :gid_self OR mg.genehmiger_mitarbeiter_id IS NOT NULL)';
                $params['gid_self'] = $actorId;
            } else {
                $where[] = '(ua.mitarbeiter_id <> :gid_not_self AND mg.genehmiger_mitarbeiter_id IS NOT NULL)';
                $params['gid_not_self'] = $actorId;
            }
        } else {
            $where[] = 'ua.mitarbeiter_id = :gid_only_self';
            $params['gid_only_self'] = $actorId;
        }

        if ($filterMitarbeiterId > 0) {
            $where[] = 'ua.mitarbeiter_id = :filter_mid';
            $params['filter_mid'] = $filterMitarbeiterId;
        }

        if ($filterStatus === 'aktiv') {
            $where[] = "ua.status IN ('offen', 'genehmigt')";
        } elseif ($filterStatus !== 'alle') {
            $where[] = 'ua.status = :filter_status';
            $params['filter_status'] = $filterStatus;
        }

        $jahrStart = sprintf('%04d-01-01', $filterJahr);
        $jahrEnde = sprintf('%04d-12-31', $filterJahr);
        $where[] = 'NOT (ua.bis_datum < :jahr_start OR ua.von_datum > :jahr_ende)';
        $params['jahr_start'] = $jahrStart;
        $params['jahr_ende'] = $jahrEnde;

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ua.von_datum DESC, ua.id DESC LIMIT 500';

        try {
            $antraege = $db->fetchAlle($sql, $params);
        } catch (\Throwable $e) {
            $antraege = [];
            if (class_exists('Logger')) {
                Logger::error('Urlaubsverwaltung: Anträge konnten nicht geladen werden', [
                    'actor_id' => $actorId,
                    'exception' => $e->getMessage(),
                ], $actorId, null, 'urlaub_verwaltung');
            }
            $fehlermeldung = 'Urlaubsanträge konnten nicht geladen werden.';
        }

        $csrfToken = $this->holeOderErzeugeCsrfToken(self::CSRF_KEY_VERWALTUNG);

        require __DIR__ . '/../views/urlaub/verwaltung.php';
    }

    /**
     * Liste offener Urlaubsanträge zur Genehmigung.
     *
     * Rechte:
     * - URLAUB_GENEHMIGEN_ALLE: sieht alle offenen Anträge.
     * - URLAUB_GENEHMIGEN: sieht offene Anträge der Mitarbeiter, für die er als Genehmiger eingetragen ist.
     * - URLAUB_GENEHMIGEN_SELF: darf eigene Anträge entscheiden (und sieht sie in der Liste).
     */
    public function genehmigungListe(): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $mitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();
        if ($mitarbeiter === null || !isset($mitarbeiter['id'])) {
            http_response_code(403);
            echo '<p>Fehler: Mitarbeiterdaten nicht gefunden.</p>';
            return;
        }

        $genehmigerId = (int)$mitarbeiter['id'];
        if ($genehmigerId <= 0) {
            http_response_code(403);
            echo '<p>Fehler: Ungültige Mitarbeiter-ID.</p>';
            return;
        }

        $darfAlle    = $this->authService->hatRecht('URLAUB_GENEHMIGEN_ALLE');
        $darfBereich = $this->authService->hatRecht('URLAUB_GENEHMIGEN');
        $darfSelf    = $this->authService->hatRecht('URLAUB_GENEHMIGEN_SELF');

        $db = Database::getInstanz();

        // Zugriff schützen: mindestens eines der Genehmigungsrechte muss vorhanden sein.
        if (!$darfAlle && !$darfBereich && !$darfSelf) {
            http_response_code(403);
            require __DIR__ . '/../views/layout/header.php';
            echo '<section><h2>Keine Berechtigung</h2><p>Sie haben keine Berechtigung zur Urlaubsgenehmigung.</p></section>';
            require __DIR__ . '/../views/layout/footer.php';
            return;
        }

        // Wenn nur "Bereich" (ohne SELF/ALLE) vergeben ist, muss der Benutzer auch als Genehmiger eingetragen sein.
        if ($darfBereich && !$darfAlle && !$darfSelf) {
            try {
                $row = $db->fetchEine(
                    'SELECT 1 AS ok
                     FROM mitarbeiter_genehmiger
                     WHERE genehmiger_mitarbeiter_id = :gid
                     LIMIT 1',
                    ['gid' => $genehmigerId]
                );

                if ($row === null) {
                    http_response_code(403);
                    require __DIR__ . '/../views/layout/header.php';
                    echo '<section><h2>Keine Berechtigung</h2><p>Sie sind nicht als Genehmiger eingetragen.</p></section>';
                    require __DIR__ . '/../views/layout/footer.php';
                    return;
                }
            } catch (Throwable $e) {
                http_response_code(500);
                echo '<p>Fehler: Berechtigungsprüfung fehlgeschlagen.</p>';
                return;
            }
        }

        // POST: Genehmigen/Ablehnen
        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');
        if ($istPost) {
            $this->verarbeiteUrlaubGenehmigungPost($db, $genehmigerId, $darfAlle, $darfBereich, $darfSelf);
            return;
        }

        // Flash-Meldungen für die Liste
        $meldung = null;
        if (isset($_SESSION['urlaub_genehmigung_flash_ok'])) {
            $meldung = (string)$_SESSION['urlaub_genehmigung_flash_ok'];
            unset($_SESSION['urlaub_genehmigung_flash_ok']);
        }

        $fehlermeldung = null;
        if (isset($_SESSION['urlaub_genehmigung_flash_error'])) {
            $fehlermeldung = (string)$_SESSION['urlaub_genehmigung_flash_error'];
            unset($_SESSION['urlaub_genehmigung_flash_error']);
        }

        $csrfToken = $this->holeOderErzeugeCsrfToken('urlaub_genehmigung_csrf_token');

        $params = [];
        $sql =
            "SELECT
                ua.id,
                ua.mitarbeiter_id,
                ua.von_datum,
                ua.bis_datum,
                ua.tage_gesamt,
                ua.status,
                ua.antrags_datum,
                ua.kommentar_mitarbeiter,
                COALESCE(
                    NULLIF(
                        CONCAT_WS(' ', NULLIF(TRIM(m.vorname), ''), NULLIF(TRIM(m.nachname), '')),
                        ''
                    ),
                    NULLIF(TRIM(m.benutzername), ''),
                    CONCAT('Mitarbeiter #', m.id)
                ) AS mitarbeiter_name
             FROM urlaubsantrag ua
             INNER JOIN mitarbeiter m ON m.id = ua.mitarbeiter_id";

        if ($darfAlle) {
            $sql .= " WHERE ua.status = 'offen'";

            // Eigene Anträge nur anzeigen, wenn SELF-Recht vorhanden ist.
            if (!$darfSelf) {
                $sql .= " AND ua.mitarbeiter_id <> :gid";
                $params['gid'] = $genehmigerId;
            }
        } elseif ($darfBereich) {
            $params['gid'] = $genehmigerId;

            if ($darfSelf) {
                // Bereich + SELF: Mitarbeiter aus dem eigenen Genehmiger-Bereich + eigene Anträge
                $sql .=
                    ' LEFT JOIN mitarbeiter_genehmiger mg
                        ON mg.mitarbeiter_id = ua.mitarbeiter_id
                       AND mg.genehmiger_mitarbeiter_id = :gid';
                $sql .= " WHERE ua.status = 'offen' AND (ua.mitarbeiter_id = :gid OR mg.genehmiger_mitarbeiter_id IS NOT NULL)";
            } else {
                // Nur Bereich: ausschließlich Mitarbeiter aus dem eigenen Genehmiger-Bereich (ohne eigene)
                $sql .=
                    ' INNER JOIN mitarbeiter_genehmiger mg
                        ON mg.mitarbeiter_id = ua.mitarbeiter_id
                       AND mg.genehmiger_mitarbeiter_id = :gid';
                $sql .= " WHERE ua.status = 'offen' AND ua.mitarbeiter_id <> :gid";
            }
        } else {
            // Nur SELF
            $params['gid'] = $genehmigerId;
            $sql .= " WHERE ua.status = 'offen' AND ua.mitarbeiter_id = :gid";
        }

        $sql .= "
             ORDER BY ua.antrags_datum DESC, ua.id DESC";

        try {
            $antraege = $db->fetchAlle($sql, $params);
        } catch (Throwable $e) {
            $antraege = [];
            if (class_exists('Logger')) {
                Logger::error('Urlaub-Genehmigung: Anträge konnten nicht geladen werden', [
                    'genehmiger_id' => $genehmigerId,
                    'darf_alle'     => $darfAlle ? 1 : 0,
                    'darf_bereich'  => $darfBereich ? 1 : 0,
                    'darf_self'     => $darfSelf ? 1 : 0,
                    'exception'     => $e->getMessage(),
                ], $genehmigerId, null, 'urlaub_genehmigung');
            }
        }

        // T-024: In der Genehmigungsliste pro Antrag den aktuellen Urlaubssaldo anzeigen
        // + (optional) Warnung, wenn die Genehmigung den Saldo negativ machen würde.
        if (!empty($antraege)) {
            $urlaubService = UrlaubService::getInstanz();
            $blockNegativ = $urlaubService->istNegativerResturlaubGeblockt();

            $saldoCache = []; // Key: "{mitarbeiterId}:{jahr}" => saldo-array
            $tz = new \DateTimeZone('Europe/Berlin');
            $fallbackJahr = (int)(new \DateTimeImmutable('now', $tz))->format('Y');

            foreach ($antraege as &$a) {
                if (!is_array($a)) {
                    continue;
                }

                $mitarbeiterId = (int)($a['mitarbeiter_id'] ?? 0);
                if ($mitarbeiterId <= 0) {
                    $a['saldo_vorschau'] = [];
                    continue;
                }

                $vonStr = (string)($a['von_datum'] ?? '');
                $bisStr = (string)($a['bis_datum'] ?? '');

                $vonDt = \DateTimeImmutable::createFromFormat('Y-m-d', $vonStr, $tz);
                $bisDt = \DateTimeImmutable::createFromFormat('Y-m-d', $bisStr, $tz);
                if (!$vonDt || $vonDt->format('Y-m-d') !== $vonStr || !$bisDt || $bisDt->format('Y-m-d') !== $bisStr || $vonDt > $bisDt) {
                    $jahre = [$fallbackJahr];
                    $vonDt = new \DateTimeImmutable(sprintf('%04d-01-01', $fallbackJahr), $tz);
                    $bisDt = new \DateTimeImmutable(sprintf('%04d-12-31', $fallbackJahr), $tz);
                } else {
                    $jahrVon = (int)$vonDt->format('Y');
                    $jahrBis = (int)$bisDt->format('Y');

                    if ($jahrVon < 2000 || $jahrVon > 2100 || $jahrBis < 2000 || $jahrBis > 2100 || $jahrBis < $jahrVon) {
                        $jahre = [$fallbackJahr];
                    } else {
                        $jahre = [];
                        for ($j = $jahrVon; $j <= $jahrBis; $j++) {
                            $jahre[] = $j;
                        }
                    }
                }

                $vorschau = [];
                foreach ($jahre as $jahr) {
                    $jahr = (int)$jahr;
                    if ($jahr < 2000 || $jahr > 2100) {
                        continue;
                    }

                    $jahrStart = new \DateTimeImmutable(sprintf('%04d-01-01', $jahr), $tz);
                    $jahrEnde  = new \DateTimeImmutable(sprintf('%04d-12-31', $jahr), $tz);

                    $clipVon = ($vonDt < $jahrStart) ? $jahrStart : $vonDt;
                    $clipBis = ($bisDt > $jahrEnde) ? $jahrEnde : $bisDt;
                    if ($clipVon > $clipBis) {
                        continue;
                    }

                    $tageAntrag = (float)$urlaubService->berechneArbeitstageFuerMitarbeiter($mitarbeiterId, $clipVon->format('Y-m-d'), $clipBis->format('Y-m-d'));

                    $cacheKey = $mitarbeiterId . ':' . $jahr;
                    if (!isset($saldoCache[$cacheKey])) {
                        $saldoCache[$cacheKey] = $urlaubService->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr);
                    }

                    $saldo = $saldoCache[$cacheKey];
                    $verbleibNach = isset($saldo['verbleibend']) ? (float)$saldo['verbleibend'] : 0.0;
                    $verfuegbarVor = $verbleibNach + $tageAntrag; // "ohne diesen Antrag" (Saldo enthält diesen Antrag als 'offen' bereits)

                    $vorschau[] = [
                        'jahr' => $jahr,
                        'verfuegbar_vor' => number_format($verfuegbarVor, 2, '.', ''),
                        'tage_antrag' => number_format($tageAntrag, 2, '.', ''),
                        'nach_genehmigung' => number_format($verbleibNach, 2, '.', ''),
                        'warnung' => ($blockNegativ && $verbleibNach < -0.00001),
                    ];
                }

                $a['saldo_vorschau'] = $vorschau;
                $a['saldo_warnung_aktiv'] = $blockNegativ ? 1 : 0;
            }
            unset($a);
        }

        require __DIR__ . '/../views/urlaub/genehmigung_liste.php';

    }
}
