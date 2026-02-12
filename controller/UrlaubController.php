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
                        $msg .= ($ovVon !== '' ? $ovVon : '?') . ' bis ' . ($ovBis !== '' ? $ovBis : '?');
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

                        $warnung = null;
                        if ($nach < 0 && $this->urlaubService->istNegativerResturlaubGeblockt()) {
                            $warnung = 'Achtung: Nach diesem Antrag wäre der Resturlaub negativ – der Antrag wird beim Speichern blockiert.';
                        }

                        $urlaubVorschau = [
                            'tage_antrag' => number_format($tageNeu, 2, '.', ''),
                            'verfuegbar'  => number_format($verfuegbar, 2, '.', ''),
                            'nach_antrag' => number_format($nach, 2, '.', ''),
                            'warnung'     => $warnung,
                        ];
                    }
                }
            }
        }

        require __DIR__ . '/../views/urlaub/meine_antraege.php';
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
