<?php
declare(strict_types=1);

/**
 * ZeitController
 *
 * Verantwortlich für:
 * - Anzeige von Tagesübersichten (eigene Zeitbuchungen)
 * - Korrektur von Zeitbuchungen (Self/Alle) mit Rechteprüfung + Audit (system_log)
 */
class ZeitController
{
    private AuthService $authService;
    private ZeitService $zeitService;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->zeitService = ZeitService::getInstanz();
    }

    /**
     * Tagesansicht (standardmäßig: eigener Mitarbeiter).
     *
     * Korrektur-Regeln (T-051):
     * - Eigene Buchungen: nur wenn `ZEITBUCHUNG_EDIT_SELF`.
     * - Andere Mitarbeiter: nur wenn `ZEITBUCHUNG_EDIT_ALL`.
     * - Legacy-Fallback: Chef/Personalbüro werden wie `EDIT_ALL` behandelt.
     * - Jede Änderung verlangt eine Begründung und wird im `system_log` auditiert.
     *
     * @param string|null $datum Y-m-d oder null (=heute)
     */
    public function tagesansicht(?string $datum = null): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $angemeldet = $this->authService->holeAngemeldetenMitarbeiter();
        if ($angemeldet === null || !isset($angemeldet['id'])) {
            echo '<p>Fehler: Mitarbeiterdaten nicht gefunden.</p>';
            return;
        }

        $angemeldeteId = (int)$angemeldet['id'];

        // Legacy-Fallback (bis alle Stellen im Backend konsequent auf Rechte umgestellt sind)
        $legacyAdmin = (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        );

        $kannEditAll  = $this->authService->hatRecht('ZEITBUCHUNG_EDIT_ALL') || $legacyAdmin;
        $kannEditSelf = $this->authService->hatRecht('ZEITBUCHUNG_EDIT_SELF') || $legacyAdmin;

        // Darf andere Mitarbeiter auswählen/bearbeiten?
        $darfAndereMitarbeiter = $kannEditAll;

        // Ziel-Mitarbeiter bestimmen (standard: eigener Mitarbeiter)
        $zielMitarbeiterId = $angemeldeteId;
        $zielMitarbeiterName = $this->formatMitarbeiterName($angemeldet);

        if ($darfAndereMitarbeiter) {
            $midGet = (int)($_GET['mitarbeiter_id'] ?? 0);
            if ($midGet > 0) {
                $tmp = $this->holeMitarbeiterStammdaten($midGet);
                if ($tmp !== null) {
                    $zielMitarbeiterId = $midGet;
                    $zielMitarbeiterName = $this->formatMitarbeiterName($tmp);
                }
            }
        }

        // Darf auf dem Ziel-Mitarbeiter korrigieren?
        $darfKorrigieren = $kannEditAll || ($kannEditSelf && $zielMitarbeiterId === $angemeldeteId);

        // Datum bestimmen (GET überschreibt, danach Parameter, sonst heute)
        $datumInput = (string)($_GET['datum'] ?? ($datum ?? ''));
        $tag = $this->parseYmdOrToday($datumInput);
        $datumYmd = $tag->format('Y-m-d');

        // CSRF Token für Korrektur
        $csrfToken = '';
        if ($darfKorrigieren) {
            if (!isset($_SESSION['zeit_korrektur_csrf']) || !is_string($_SESSION['zeit_korrektur_csrf']) || $_SESSION['zeit_korrektur_csrf'] === '') {
                try {
                    $_SESSION['zeit_korrektur_csrf'] = bin2hex(random_bytes(32));
                } catch (Throwable $e) {
                    $_SESSION['zeit_korrektur_csrf'] = bin2hex((string)mt_rand());
                }
            }
            $csrfToken = (string)$_SESSION['zeit_korrektur_csrf'];
        }

        // Flash
        $flashOk = isset($_SESSION['zeit_korrektur_flash_ok']) ? (string)$_SESSION['zeit_korrektur_flash_ok'] : null;
        $flashFehler = isset($_SESSION['zeit_korrektur_flash_fehler']) ? (string)$_SESSION['zeit_korrektur_flash_fehler'] : null;
        unset($_SESSION['zeit_korrektur_flash_ok'], $_SESSION['zeit_korrektur_flash_fehler']);

        // Optional: Edit-Modus
        $editBuchung = null;
        if ($darfKorrigieren) {
            $editId = (int)($_GET['edit_id'] ?? 0);
            if ($editId > 0) {
                try {
                    $zbModel = new ZeitbuchungModel();
                    $cand = $zbModel->holeNachId($editId);
                    if (is_array($cand) && (int)($cand['mitarbeiter_id'] ?? 0) === $zielMitarbeiterId) {
                        $ts = (string)($cand['zeitstempel'] ?? '');
                        if ($ts !== '' && str_starts_with($ts, $datumYmd)) {
                            $editBuchung = $cand;
                        }
                    }
                } catch (Throwable $e) {
                    $editBuchung = null;
                }
            }
        }

        // POST: Korrekturen nur mit Recht
        if ($darfKorrigieren && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $postToken = (string)($_POST['csrf'] ?? '');
            $aktion = (string)($_POST['aktion'] ?? '');

            if ($csrfToken === '' || !hash_equals($csrfToken, $postToken)) {
                $_SESSION['zeit_korrektur_flash_fehler'] = 'CSRF-Check fehlgeschlagen.';
                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }
            // Begründungspflicht ist normalerweise immer aktiv (Audit/Compliance),
            // aber für bestimmte Aktionen (z. B. Override entfernen) soll der Nutzer nicht
            // gezwungen werden, erneut eine Begründung einzutragen.
            $begruendungPflicht = true;
            if ($aktion === 'set_pause_override') {
                $pauseAktiv = ((string)($_POST['pause_override_aktiv'] ?? '') !== '');
                if (!$pauseAktiv) {
                    $begruendungPflicht = false;
                }
            }

            $begruendung = '';
            if ($begruendungPflicht) {
                $b = $this->parseBegruendung($_POST['begruendung'] ?? null);
                if ($b === null) {
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte eine Begründung angeben (Pflichtfeld).';
                    $editIdForReturn = (int)($_POST['id'] ?? 0);
                    $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, $editIdForReturn > 0 ? $editIdForReturn : null));
                    return;
                }
                $begruendung = $b;
            } else {
                // Optional: Begründung kann leer sein (z. B. beim Entfernen eines Overrides).
                $b = trim((string)($_POST['begruendung'] ?? ''));
                if (strlen($b) > 255) {
                    $b = substr($b, 0, 255);
                }
                $begruendung = $b;
            }

            $zbModel = new ZeitbuchungModel();

            if ($aktion === 'set_kurzarbeit') {
                $aktiv = ((string)($_POST['kurzarbeit_aktiv'] ?? '') !== '');

                $stundenInput = trim((string)($_POST['kurzarbeit_stunden'] ?? ''));
                $stunden = 0.0;
                if ($stundenInput !== '') {
                    $stunden = (float)str_replace(',', '.', $stundenInput);
                }

                // Konfliktcheck: Kurzarbeit nicht gleichzeitig mit anderen Tageskennzeichen (Urlaub/Krank/Feiertag/Arzt/Sonstiges)
                try {
                    $twModel = new TageswerteMitarbeiterModel();
                    $tw = $twModel->holeNachMitarbeiterUndDatum($zielMitarbeiterId, $datumYmd);
                } catch (Throwable $e) {
                    $tw = null;
                }

                if ($aktiv) {
                    // Wenn Stunden nicht angegeben → aus Plan übernehmen (falls vorhanden)
                    if ($stunden <= 0.0) {
                        $stunden = $this->berechnePlanKurzarbeitStundenFuerTag($zielMitarbeiterId, $tag, $datumYmd);
                    }

                    if ($stunden <= 0.0) {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte Kurzarbeit-Stunden angeben (oder es muss ein passender Kurzarbeit-Plan existieren).';
                        $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                        return;
                    }

                    if (is_array($tw)) {
                        $konflikt = (
                            ((int)($tw['kennzeichen_feiertag'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_urlaub'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_krank_lfz'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_krank_kk'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_arzt'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_sonstiges'] ?? 0) === 1)
                        );

                        if ($konflikt) {
                            $_SESSION['zeit_korrektur_flash_fehler'] = 'Kurzarbeit kann nicht gesetzt werden, weil an diesem Tag bereits ein anderes Kennzeichen (Urlaub/Krank/Feiertag/Arzt/Sonstiges) gesetzt ist.';
                            $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                            return;
                        }
                    }
                } else {
                    // Deaktivieren = Override entfernen → Plan greift wieder (reine Anzeige)
                    $stunden = 0.0;
                }

                if ($stunden < 0.0) {
                    $stunden = 0.0;
                }
                $stunden = round($stunden, 2);
                $kenn = ($aktiv && $stunden > 0.0) ? 1 : 0;

                // Persist: Tageswerte upserten (nur Kurzarbeit-Felder); keine Berechnung von Ist/Soll an dieser Stelle.
                $sql = 'INSERT INTO tageswerte_mitarbeiter (mitarbeiter_id, datum, kurzarbeit_stunden, kennzeichen_kurzarbeit, felder_manuell_geaendert)
                        VALUES (:mid, :datum, :std, :kenn, 1)
                        ON DUPLICATE KEY UPDATE
                            kurzarbeit_stunden = VALUES(kurzarbeit_stunden),
                            kennzeichen_kurzarbeit = VALUES(kennzeichen_kurzarbeit),
                            felder_manuell_geaendert = 1';

                try {
                    $db = Database::getInstanz();
                    $db->ausfuehren($sql, [
                        'mid'   => $zielMitarbeiterId,
                        'datum' => $datumYmd,
                        'std'   => sprintf('%.2f', $stunden),
                        'kenn'  => $kenn,
                    ]);

                    if (class_exists('Logger')) {
                        Logger::info('Tageswerte gesetzt: Kurzarbeit', [
                            'ziel_mitarbeiter_id'    => $zielMitarbeiterId,
                            'datum'                  => $datumYmd,
                            'kennzeichen_kurzarbeit' => $kenn,
                            'kurzarbeit_stunden'     => sprintf('%.2f', $stunden),
                            'begruendung'            => $begruendung,
                        ], $angemeldeteId, null, 'tageswerte_audit');
                    }

                    $_SESSION['zeit_korrektur_flash_ok'] = 'Kurzarbeit wurde gespeichert.';
                } catch (Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Speichern Kurzarbeit (Tagesansicht)', [
                            'ziel_mitarbeiter_id'    => $zielMitarbeiterId,
                            'datum'                  => $datumYmd,
                            'kennzeichen_kurzarbeit' => $kenn,
                            'kurzarbeit_stunden'     => sprintf('%.2f', $stunden),
                            'exception'              => $e->getMessage(),
                        ], $angemeldeteId, null, 'tageswerte_audit');
                    }
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Kurzarbeit konnte nicht gespeichert werden.';
                }

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }


            if ($aktion === 'set_krank') {
                $typ = strtolower(trim((string)($_POST['krank_typ'] ?? '')));
                $aktiv = ($typ === 'lfz' || $typ === 'kk');

                $stundenInput = trim((string)($_POST['krank_stunden'] ?? ''));
                $stunden = 0.0;
                if ($stundenInput !== '') {
                    $stundenInput = str_replace(',', '.', $stundenInput);
                    if (is_numeric($stundenInput)) {
                        $stunden = (float)$stundenInput;
                    }
                }

                try {
                    $twModel = new TageswerteMitarbeiterModel();
                    $tw = $twModel->holeNachMitarbeiterUndDatum($zielMitarbeiterId, $datumYmd);
                } catch (Throwable $e) {
                    $tw = null;
                }

                if ($aktiv) {
                    // Default: Sollstunden (Wochenarbeitszeit/5), wenn keine Stunden angegeben.
                    if ($stunden <= 0.0) {
                        $wochenarbeitszeit = 0.0;
                        try {
                            $mModel = new MitarbeiterModel();
                            $m = $mModel->holeNachId($zielMitarbeiterId);
                            if (is_array($m) && isset($m['wochenarbeitszeit'])) {
                                $wochenarbeitszeit = (float)str_replace(',', '.', (string)$m['wochenarbeitszeit']);
                            }
                        } catch (Throwable $e) {
                            $wochenarbeitszeit = 0.0;
                        }

                        if ($wochenarbeitszeit > 0.0) {
                            $stunden = $wochenarbeitszeit / 5.0;
                        }
                    }

                    if ($stunden <= 0.0) {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte Krank-Stunden angeben.';
                        $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                        return;
                    }

                    if (is_array($tw)) {
                        // Konflikte: andere Kennzeichen (und erfasste Arbeitszeit) dürfen nicht parallel gesetzt sein.
                        $konflikt = (
                            ((int)($tw['kennzeichen_feiertag'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_urlaub'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_kurzarbeit'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_arzt'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_sonstiges'] ?? 0) === 1)
                        );

                        $hatArbeitszeit = false;
                        $istStd = (float)str_replace(',', '.', (string)($tw['ist_stunden'] ?? '0'));
                        $kr = (string)($tw['kommen_roh'] ?? '');
                        $gr = (string)($tw['gehen_roh'] ?? '');
                        if ($istStd > 0.01 || $kr !== '' || $gr !== '') {
                            $hatArbeitszeit = true;
                        }

                        if ($konflikt || $hatArbeitszeit) {
                            $_SESSION['zeit_korrektur_flash_fehler'] = 'Krank kann nicht gesetzt werden, wenn bereits Arbeitszeit oder andere Kennzeichen (Urlaub/Kurzarbeit/Feiertag/Arzt/Sonstiges) gesetzt sind.';
                            $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                            return;
                        }
                    }
                } else {
                    // Deaktivieren = Override entfernen → Zeitraum-Ableitung greift wieder (Anzeige)
                    $stunden = 0.0;
                }

                if ($stunden < 0.0) {
                    $stunden = 0.0;
                }
                $stunden = round($stunden, 2);

                $lfzStd = 0.0;
                $kkStd  = 0.0;
                $kennL  = 0;
                $kennK  = 0;

                if ($aktiv && $stunden > 0.0) {
                    if ($typ === 'lfz') {
                        $lfzStd = $stunden;
                        $kennL = 1;
                    } else {
                        $kkStd = $stunden;
                        $kennK = 1;
                    }
                }

                $sql = 'INSERT INTO tageswerte_mitarbeiter (mitarbeiter_id, datum, krank_lfz_stunden, kennzeichen_krank_lfz, krank_kk_stunden, kennzeichen_krank_kk, felder_manuell_geaendert)
                        VALUES (:mid, :datum, :lfz_std, :lfz_kenn, :kk_std, :kk_kenn, 1)
                        ON DUPLICATE KEY UPDATE
                            krank_lfz_stunden = VALUES(krank_lfz_stunden),
                            kennzeichen_krank_lfz = VALUES(kennzeichen_krank_lfz),
                            krank_kk_stunden = VALUES(krank_kk_stunden),
                            kennzeichen_krank_kk = VALUES(kennzeichen_krank_kk),
                            felder_manuell_geaendert = 1';

                try {
                    $db = Database::getInstanz();
                    $db->ausfuehren($sql, [
                        'mid'      => $zielMitarbeiterId,
                        'datum'    => $datumYmd,
                        'lfz_std'  => sprintf('%.2f', $lfzStd),
                        'lfz_kenn' => $kennL,
                        'kk_std'   => sprintf('%.2f', $kkStd),
                        'kk_kenn'  => $kennK,
                    ]);

                    if (class_exists('Logger')) {
                        $msg = $aktiv ? 'Tageswerte gesetzt: Krank' : 'Tageswerte entfernt: Krank';
                        Logger::info($msg, [
                            'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                            'datum'               => $datumYmd,
                            'typ'                 => $typ,
                            'stunden'             => $stunden,
                            'kennzeichen_krank_lfz' => $kennL,
                            'kennzeichen_krank_kk'  => $kennK,
                            'krank_lfz_stunden'     => $lfzStd,
                            'krank_kk_stunden'      => $kkStd,
                            'begruendung'           => $begruendung,
                        ], $angemeldeteId, null, 'tageswerte_audit');
                    }


                    if ($aktiv) {
                        $_SESSION['zeit_korrektur_flash_ok'] = 'Krank (' . strtoupper($typ) . ') gespeichert.';
                    } else {
                        $_SESSION['zeit_korrektur_flash_ok'] = 'Krank-Override entfernt.';
                    }
                } catch (Throwable $e) {
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Fehler beim Speichern der Krank-Tageswerte.';
                }

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }

            if ($aktion === 'set_sonstiges') {
                $grundId = (int)($_POST['sonstiges_grund_id'] ?? 0);

                $stundenInput = trim((string)($_POST['sonstiges_stunden'] ?? ''));
                $stunden = 0.0;
                if ($stundenInput !== '') {
                    $stundenInput = str_replace(',', '.', $stundenInput);
                    if (is_numeric($stundenInput)) {
                        $stunden = (float)$stundenInput;
                    }
                }

                $sonstigesKommentarText = trim((string)($_POST['sonstiges_kommentar'] ?? ''));

                // Tageswerte laden (für Konfliktcheck + ggf. Kommentar-Reset beim Deaktivieren)
                try {
                    $twModel = new TageswerteMitarbeiterModel();
                    $tw = $twModel->holeNachMitarbeiterUndDatum($zielMitarbeiterId, $datumYmd);
                } catch (Throwable $e) {
                    $tw = null;
                }

                // Grund laden (nur wenn aktiv gesetzt werden soll)
                $grund = null;
                if ($grundId > 0) {
                    try {
                        $db = Database::getInstanz();
                        $grund = $db->fetchEine(
                            'SELECT id, code, titel, default_stunden, begruendung_pflicht, aktiv
                             FROM sonstiges_grund
                             WHERE id = :id
                             LIMIT 1',
                            ['id' => $grundId]
                        );
                    } catch (Throwable $e) {
                        $grund = null;
                    }

                    if (!is_array($grund) || (int)($grund['id'] ?? 0) <= 0) {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Ungültiger Sonstiges-Grund.';
                        $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                        return;
                    }

                    if ((int)($grund['aktiv'] ?? 0) !== 1) {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Der ausgewählte Sonstiges-Grund ist inaktiv.';
                        $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                        return;
                    }
                }

                $aktiv = ($grundId > 0);

                if ($aktiv) {
                    // Default-Stunden übernehmen, wenn nicht angegeben.
                    if ($stunden <= 0.0) {
                        $def = (float)str_replace(',', '.', (string)($grund['default_stunden'] ?? '0'));
                        if ($def > 0.0) {
                            $stunden = $def;
                        }
                    }

                    if ($stunden <= 0.0) {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte Sonstiges-Stunden angeben (oder der Grund muss Default-Stunden haben).';
                        $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                        return;
                    }

                    // Kommentar/Begründungspflicht (aus Konfiguration)
                    $pflicht = ((int)($grund['begruendung_pflicht'] ?? 0) === 1);
                    if ($pflicht && $sonstigesKommentarText === '') {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte eine Begründung/Notiz für „Sonstiges“ angeben (Pflicht für diesen Grund).';
                        $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                        return;
                    }

                    // Konfliktcheck: Sonstiges nicht parallel zu Arbeitszeit oder anderen Kennzeichen.
                    if (is_array($tw)) {
                        $konflikt = (
                            ((int)($tw['kennzeichen_feiertag'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_urlaub'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_krank_lfz'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_krank_kk'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_arzt'] ?? 0) === 1)
                            || ((int)($tw['kennzeichen_kurzarbeit'] ?? 0) === 1)
                        );

                        $hatArbeitszeit = false;
                        $istStd = (float)str_replace(',', '.', (string)($tw['ist_stunden'] ?? '0'));
                        $kr = (string)($tw['kommen_roh'] ?? '');
                        $gr = (string)($tw['gehen_roh'] ?? '');
                        if ($istStd > 0.01 || $kr !== '' || $gr !== '') {
                            $hatArbeitszeit = true;
                        }

                        if ($konflikt || $hatArbeitszeit) {
                            $_SESSION['zeit_korrektur_flash_fehler'] = 'Sonstiges kann nicht gesetzt werden, wenn bereits Arbeitszeit oder andere Kennzeichen (Urlaub/Krank/Feiertag/Arzt/Kurzarbeit) gesetzt sind.';
                            $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                            return;
                        }
                    }

                    $stunden = round(max(0.0, $stunden), 2);

                    $code = trim((string)($grund['code'] ?? ''));
                    if ($code === '') {
                        $code = 'SONST';
                    }

                    // Kommentar: immer Code; optional zusätzlich freier Text.
                    $kommentar = $code;
                    if ($sonstigesKommentarText !== '') {
                        $kommentar = $code . ': ' . $sonstigesKommentarText;
                    }

                    $sql = 'INSERT INTO tageswerte_mitarbeiter (mitarbeiter_id, datum, sonstige_stunden, kennzeichen_sonstiges, kommentar, felder_manuell_geaendert)
                            VALUES (:mid, :datum, :std, 1, :kom, 1)
                            ON DUPLICATE KEY UPDATE
                                sonstige_stunden = VALUES(sonstige_stunden),
                                kennzeichen_sonstiges = VALUES(kennzeichen_sonstiges),
                                kommentar = VALUES(kommentar),
                                felder_manuell_geaendert = 1';

                    try {
                        $db = Database::getInstanz();
                        $db->ausfuehren($sql, [
                            'mid'   => $zielMitarbeiterId,
                            'datum' => $datumYmd,
                            'std'   => sprintf('%.2f', $stunden),
                            'kom'   => $kommentar,
                        ]);

                        if (class_exists('Logger')) {
                            Logger::info('Tageswerte gesetzt: Sonstiges', [
                                'ziel_mitarbeiter_id'   => $zielMitarbeiterId,
                                'datum'                 => $datumYmd,
                                'sonstiges_grund_id'    => (int)($grund['id'] ?? 0),
                                'sonstiges_code'        => $code,
                                'sonstige_stunden'      => sprintf('%.2f', $stunden),
                                'begruendung'           => $begruendung,
                            ], $angemeldeteId, null, 'tageswerte_audit');
                        }

                        $_SESSION['zeit_korrektur_flash_ok'] = 'Sonstiges wurde gespeichert.';
                    } catch (Throwable $e) {
                        if (class_exists('Logger')) {
                            Logger::error('Fehler beim Speichern Sonstiges (Tagesansicht)', [
                                'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                                'datum'               => $datumYmd,
                                'sonstiges_grund_id'  => $grundId,
                                'exception'           => $e->getMessage(),
                            ], $angemeldeteId, null, 'tageswerte_audit');
                        }
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Sonstiges konnte nicht gespeichert werden.';
                    }
                } else {
                    // Deaktivieren: Kennzeichen/Std zurücksetzen. Kommentar nur dann entfernen, wenn er wie ein Sonstiges-Code aussieht.
                    $kommentarUpsert = false;
                    $kommentarNeu = null;

                    $curr = '';
                    if (is_array($tw)) {
                        $curr = trim((string)($tw['kommentar'] ?? ''));
                    }

                    if ($curr !== '') {
                        // Nur wenn Kommentar exakt ein Code ist oder mit "CODE:" beginnt und CODE in der Konfiguration existiert.
                        $codeTeil = $curr;
                        $pos = strpos($curr, ':');
                        if ($pos !== false) {
                            $codeTeil = trim(substr($curr, 0, $pos));
                        }

                        if ($codeTeil !== '') {
                            try {
                                $db = Database::getInstanz();
                                $exists = $db->fetchEine('SELECT id FROM sonstiges_grund WHERE code = :c LIMIT 1', ['c' => $codeTeil]);
                                if (is_array($exists) && (int)($exists['id'] ?? 0) > 0) {
                                    $kommentarUpsert = true;
                                    $kommentarNeu = null;
                                }
                            } catch (Throwable $e) {
                                // bei Fehler: Kommentar nicht anfassen
                            }
                        }
                    }

                    try {
                        $db = Database::getInstanz();

                        if ($kommentarUpsert) {
                            $sql = 'INSERT INTO tageswerte_mitarbeiter (mitarbeiter_id, datum, sonstige_stunden, kennzeichen_sonstiges, kommentar, felder_manuell_geaendert)
                                    VALUES (:mid, :datum, 0.00, 0, :kom, 1)
                                    ON DUPLICATE KEY UPDATE
                                        sonstige_stunden = 0.00,
                                        kennzeichen_sonstiges = 0,
                                        kommentar = VALUES(kommentar),
                                        felder_manuell_geaendert = 1';
                            $db->ausfuehren($sql, [
                                'mid'   => $zielMitarbeiterId,
                                'datum' => $datumYmd,
                                'kom'   => $kommentarNeu,
                            ]);
                        } else {
                            $sql = 'INSERT INTO tageswerte_mitarbeiter (mitarbeiter_id, datum, sonstige_stunden, kennzeichen_sonstiges, felder_manuell_geaendert)
                                    VALUES (:mid, :datum, 0.00, 0, 1)
                                    ON DUPLICATE KEY UPDATE
                                        sonstige_stunden = 0.00,
                                        kennzeichen_sonstiges = 0,
                                        felder_manuell_geaendert = 1';
                            $db->ausfuehren($sql, [
                                'mid'   => $zielMitarbeiterId,
                                'datum' => $datumYmd,
                            ]);
                        }

                        if (class_exists('Logger')) {
                            Logger::info('Tageswerte entfernt: Sonstiges', [
                                'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                                'datum'               => $datumYmd,
                                'begruendung'         => $begruendung,
                            ], $angemeldeteId, null, 'tageswerte_audit');
                        }

                        $_SESSION['zeit_korrektur_flash_ok'] = 'Sonstiges wurde entfernt.';
                    } catch (Throwable $e) {
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Sonstiges konnte nicht entfernt werden.';
                    }
                }

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }
            if ($aktion === 'set_pause_override') {
                $aktiv = ((string)($_POST['pause_override_aktiv'] ?? '') !== '');

                // Deaktivieren: Checkbox aus -> Override entfernen, normale Pausenregeln greifen wieder.
                if (!$aktiv) {
                    $warAktiv = false;
                    try {
                        $audit = $this->holeLetztenPauseOverrideAudit($zielMitarbeiterId, $datumYmd);
                        if (is_array($audit) && ($audit['aktiv'] ?? false) === true) {
                            $warAktiv = true;
                        }
                    } catch (Throwable $e) {
                        $warAktiv = false;
                    }

                    try {
                        $db = Database::getInstanz();

                        $twRow = $db->fetchEine(
                            'SELECT arzt_stunden, krank_lfz_stunden, krank_kk_stunden, feiertag_stunden, kurzarbeit_stunden, urlaub_stunden, sonstige_stunden, '
                            . 'kennzeichen_arzt, kennzeichen_krank_lfz, kennzeichen_krank_kk, kennzeichen_feiertag, kennzeichen_kurzarbeit, kennzeichen_urlaub, kennzeichen_sonstiges '
                            . 'FROM tageswerte_mitarbeiter WHERE mitarbeiter_id = :mid AND datum = :datum LIMIT 1',
                            ['mid' => $zielMitarbeiterId, 'datum' => $datumYmd]
                        );

                        if (is_array($twRow)) {
                            $andere = false;
                            $andere = $andere || ((int)($twRow['kennzeichen_arzt'] ?? 0) === 1);
                            $andere = $andere || ((int)($twRow['kennzeichen_krank_lfz'] ?? 0) === 1);
                            $andere = $andere || ((int)($twRow['kennzeichen_krank_kk'] ?? 0) === 1);
                            $andere = $andere || ((int)($twRow['kennzeichen_feiertag'] ?? 0) === 1);
                            $andere = $andere || ((int)($twRow['kennzeichen_kurzarbeit'] ?? 0) === 1);
                            $andere = $andere || ((int)($twRow['kennzeichen_urlaub'] ?? 0) === 1);
                            $andere = $andere || ((int)($twRow['kennzeichen_sonstiges'] ?? 0) === 1);

                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['arzt_stunden'] ?? '0')) > 0.0);
                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['krank_lfz_stunden'] ?? '0')) > 0.0);
                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['krank_kk_stunden'] ?? '0')) > 0.0);
                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['feiertag_stunden'] ?? '0')) > 0.0);
                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['kurzarbeit_stunden'] ?? '0')) > 0.0);
                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['urlaub_stunden'] ?? '0')) > 0.0);
                            $andere = $andere || ((float)str_replace(',', '.', (string)($twRow['sonstige_stunden'] ?? '0')) > 0.0);

                            $felderFlag = $andere ? 1 : 0;

                            $db->ausfuehren(
                                'UPDATE tageswerte_mitarbeiter SET pause_korr_minuten = 0, felder_manuell_geaendert = :flag WHERE mitarbeiter_id = :mid AND datum = :datum',
                                ['flag' => $felderFlag, 'mid' => $zielMitarbeiterId, 'datum' => $datumYmd]
                            );
                        }

                        if ($warAktiv && class_exists('Logger')) {
                            Logger::info('Tageswerte entfernt: Pause-Override', [
                                'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                                'datum'               => $datumYmd,
                                'pause_minuten'       => 0,
                                'begruendung'         => $begruendung,
                            ], $angemeldeteId, null, 'tageswerte_audit');
                        }

                        $_SESSION['zeit_korrektur_flash_ok'] = $warAktiv ? 'Pause-Override wurde entfernt.' : 'Pause-Override war nicht aktiv.';
                    } catch (Throwable $e) {
                        if (class_exists('Logger')) {
                            Logger::error('Fehler beim Entfernen Pause-Override (Tagesansicht)', [
                                'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                                'datum'               => $datumYmd,
                                'exception'           => $e->getMessage(),
                            ], $angemeldeteId, null, 'tageswerte_audit');
                        }
                        $_SESSION['zeit_korrektur_flash_fehler'] = 'Pause-Override konnte nicht entfernt werden.';
                    }

                    $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                    return;
                }

                // Aktivieren/Ändern: Checkbox an -> Stundenwert übernehmen (auch 0,00 möglich).
                $stundenInput = trim((string)($_POST['pause_stunden'] ?? ''));
                if ($stundenInput === '') {
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte Pause in Stunden angeben (z. B. 0,25 oder 0,00).';
                    $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                    return;
                }
                $stundenInput = str_replace(',', '.', $stundenInput);
                if (!is_numeric($stundenInput)) {
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Bitte eine gültige Zahl für die Pause angeben.';
                    $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                    return;
                }

                $stundenFloat = (float)$stundenInput;
                if ($stundenFloat < 0.0 || $stundenFloat > 24.0) {
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Pause muss zwischen 0,00 und 24,00 Stunden liegen.';
                    $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                    return;
                }

                $minuten = (int)round($stundenFloat * 60.0);

                try {
                    $db = Database::getInstanz();

                    $sql = 'INSERT INTO tageswerte_mitarbeiter (mitarbeiter_id, datum, pause_korr_minuten, felder_manuell_geaendert)
                            VALUES (:mid, :datum, :pmin, 1)
                            ON DUPLICATE KEY UPDATE
                                pause_korr_minuten = VALUES(pause_korr_minuten),
                                felder_manuell_geaendert = 1';

                    $db->ausfuehren($sql, [
                        'mid'   => $zielMitarbeiterId,
                        'datum' => $datumYmd,
                        'pmin'  => $minuten,
                    ]);

                    if (class_exists('Logger')) {
                        Logger::info('Tageswerte gesetzt: Pause-Override', [
                            'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                            'datum'               => $datumYmd,
                            'pause_stunden'       => round($stundenFloat, 2),
                            'pause_minuten'       => $minuten,
                            'begruendung'         => $begruendung,
                        ], $angemeldeteId, null, 'tageswerte_audit');
                    }

                    $_SESSION['zeit_korrektur_flash_ok'] = 'Pause-Override wurde gespeichert.';
                } catch (Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Speichern Pause-Override (Tagesansicht)', [
                            'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                            'datum'               => $datumYmd,
                            'pause_minuten'       => $minuten,
                            'exception'           => $e->getMessage(),
                        ], $angemeldeteId, null, 'tageswerte_audit');
                    }
                    $_SESSION['zeit_korrektur_flash_fehler'] = 'Pause-Override konnte nicht gespeichert werden.';
                }

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }


            if ($aktion === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $typ = (string)($_POST['typ'] ?? '');
                $zeit = (string)($_POST['zeit'] ?? '');
                // WICHTIG: Die Begründung ist das Pflichtfeld (Audit/Compliance) und soll im UI
                // auch wieder sichtbar sein. Deshalb wird die Begründung als Kommentar in der
                // Zeitbuchung gespeichert (Spalte "kommentar"), damit sie in Listen/Tabellen
                // direkt angezeigt werden kann.
                $kommentar = $begruendung;

                $nachtshift = ((int)($_POST['nachtshift'] ?? 0) === 1) ? 1 : 0;
                $ok = $this->korrigiereUpdateBuchung($zbModel, $angemeldeteId, $zielMitarbeiterId, $datumYmd, $id, $typ, $zeit, $kommentar, $begruendung, $nachtshift);

                $_SESSION['zeit_korrektur_flash_ok'] = $ok ? 'Buchung wurde aktualisiert.' : null;
                $_SESSION['zeit_korrektur_flash_fehler'] = $ok ? null : 'Buchung konnte nicht aktualisiert werden.';

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, $ok ? null : ($id > 0 ? $id : null)));
                return;
            }

            if ($aktion === 'delete') {
                $id = (int)($_POST['id'] ?? 0);

                $ok = $this->korrigiereDeleteBuchung($zbModel, $angemeldeteId, $zielMitarbeiterId, $datumYmd, $id, $begruendung);

                $_SESSION['zeit_korrektur_flash_ok'] = $ok ? 'Buchung wurde gelöscht.' : null;
                $_SESSION['zeit_korrektur_flash_fehler'] = $ok ? null : 'Buchung konnte nicht gelöscht werden.';

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }

            if ($aktion === 'add') {
                $typ = (string)($_POST['typ'] ?? '');
                $zeit = (string)($_POST['zeit'] ?? '');
                // Siehe oben: Begründung (Pflicht) wird als Kommentar gespeichert.
                $kommentar = $begruendung;

                $nachtshift = ((int)($_POST['nachtshift'] ?? 0) === 1) ? 1 : 0;
                $fehlerAddBuchung = null;
                $ok = $this->korrigiereAddBuchung(
                    $zbModel,
                    $angemeldeteId,
                    $zielMitarbeiterId,
                    $datumYmd,
                    $typ,
                    $zeit,
                    $kommentar,
                    $begruendung,
                    $nachtshift,
                    $fehlerAddBuchung
                );

                $_SESSION['zeit_korrektur_flash_ok'] = $ok ? 'Buchung wurde hinzugefügt.' : null;
                $_SESSION['zeit_korrektur_flash_fehler'] = $ok ? null : ($fehlerAddBuchung ?? 'Buchung konnte nicht hinzugefügt werden.');

                $this->redirect($this->buildTagesansichtUrl($datumYmd, $zielMitarbeiterId, $darfAndereMitarbeiter, null));
                return;
            }
        }

        // Für Admin: Mitarbeiterliste (für Dropdown)
        $mitarbeiterListe = [];
        if ($darfAndereMitarbeiter) {
            try {
                $db = Database::getInstanz();
                $mitarbeiterListe = $db->fetchAlle('SELECT id, vorname, nachname, aktiv FROM mitarbeiter ORDER BY aktiv DESC, nachname ASC, vorname ASC');
            } catch (Throwable $e) {
                $mitarbeiterListe = [];
            }
        }

        $daten = $this->zeitService->holeTagesdaten($zielMitarbeiterId, $tag);

        $datumAnzeige = $daten['datum'] ?? $datumYmd;
        $buchungen    = $daten['buchungen'] ?? [];

        // Mikro-Buchungen (Kommen/Gehen innerhalb weniger Sekunden/Minuten)
        // sollen standardmäßig nicht in der Tagesliste erscheinen.
        // Für Debug/Korrektur kann man sie über ?show_micro=1 einblenden.
        $zeigeMicroBuchungen = ((string)($_GET['show_micro'] ?? '') === '1');
        if (!$zeigeMicroBuchungen && $editBuchung !== null) {
            // Wenn direkt eine Buchung bearbeitet wird, wollen wir sie sichtbar lassen.
            $zeigeMicroBuchungen = true;
        }

        $microBuchungenAusgeblendetAnzahl = 0;
        if (!$zeigeMicroBuchungen && is_array($buchungen) && count($buchungen) >= 2) {
            // Mikro-Buchungen: Grenze ist zentral über `config.micro_buchung_max_sekunden` einstellbar.
            // Default: 180 Sekunden (= 3 Minuten).
            $maxMicroSekunden = 180;
            try {
                if (class_exists('KonfigurationService')) {
                    $cfg = KonfigurationService::getInstanz();
                    $val = $cfg->getInt('micro_buchung_max_sekunden', 180);
                    if ($val !== null) {
                        $maxMicroSekunden = (int)$val;
                    }
                }
            } catch (Throwable $e) {
                // Fallback bleibt Default.
                $maxMicroSekunden = 180;
            }
            if ($maxMicroSekunden < 30 || $maxMicroSekunden > 3600) {
                $maxMicroSekunden = 180;
            }

            $res = $this->filtereMicroZeitbuchungenAusListe($buchungen, $maxMicroSekunden);
            $buchungen = $res['buchungen'];
            $microBuchungenAusgeblendetAnzahl = (int)($res['ausgeblendet'] ?? 0);
        }
        // Tageswerte (für Tagesfelder wie Kurzarbeit etc.)
        $tageswerte = $daten['tageswerte'] ?? null;

        // Kurzarbeit-Plan-Hinweis für diesen Tag (nur Anzeige; DB bleibt unberührt)
        $planKurzarbeitStunden = null;
        $hatKurzarbeitOverride = false;
        if (is_array($tageswerte)) {
            $kenn = (int)($tageswerte['kennzeichen_kurzarbeit'] ?? 0);
            $std  = (float)str_replace(',', '.', (string)($tageswerte['kurzarbeit_stunden'] ?? '0'));
            if ($kenn === 1 || $std > 0.0) {
                $hatKurzarbeitOverride = true;
            }
        }
        if (!$hatKurzarbeitOverride) {
            try {
                $planStd = $this->berechnePlanKurzarbeitStundenFuerTag($zielMitarbeiterId, $tag, $datumYmd);
                if ($planStd > 0.0) {
                    $planKurzarbeitStunden = $planStd;
                }
            } catch (Throwable $e) {
                $planKurzarbeitStunden = null;
            }
        }

        // Pause-Override (aktiv + Begründung) wird über Audit-Log ermittelt,
        // damit 0,00 Stunden als Override möglich sind und trotzdem klar aktiv/inaktiv unterscheidbar bleibt.
        $pauseOverrideAktiv = false;
        $pauseOverrideStunden = '';
        $pauseOverrideBegruendung = '';

        // Auto-Pause nach Regeln (Anzeige-Hilfe):
        // - Wird genutzt, um in der Tagesansicht bei deaktiviertem Override zu zeigen,
        //   welche Pause automatisch abgezogen wird.
        // - Zusätzlich dient der Wert als sinnvoller Default, wenn der Override später
        //   wieder aktiviert wird.
        $pauseAutoStunden = '';
        $pauseAutoEntscheidungNoetig = false;
        $pauseAutoVorschlagMin = 0;

        try {
            $kommenRoh = null;
            $gehenRoh = null;

            if (is_array($buchungen)) {
                foreach ($buchungen as $b) {
                    $typ = (string)($b['typ'] ?? '');
                    $ts = (string)($b['zeitstempel'] ?? '');
                    if ($ts === '') {
                        continue;
                    }
                    try {
                        $dt = new DateTimeImmutable($ts);
                    } catch (Throwable $e) {
                        continue;
                    }

                    if ($typ === 'kommen') {
                        if ($kommenRoh === null || $dt < $kommenRoh) {
                            $kommenRoh = $dt;
                        }
                    } elseif ($typ === 'gehen') {
                        if ($gehenRoh === null || $dt > $gehenRoh) {
                            $gehenRoh = $dt;
                        }
                    }
                }
            }

            if ($kommenRoh instanceof DateTimeImmutable && $gehenRoh instanceof DateTimeImmutable && $gehenRoh > $kommenRoh) {
                $rundungsService = RundungsService::getInstanz();
                $pausenService = PausenService::getInstanz();

                $kKorr = $rundungsService->rundeZeitstempel($kommenRoh, 'kommen');
                $gKorr = $rundungsService->rundeZeitstempel($gehenRoh, 'gehen');

                if ($gKorr <= $kKorr) {
                    $kKorr = $kommenRoh;
                    $gKorr = $gehenRoh;
                }

                $res = $pausenService->berechnePausenMinutenUndEntscheidungFuerBlock($kKorr, $gKorr);
                $pauseMinAuto = (int)($res['pause_minuten'] ?? 0);
                $pauseAutoEntscheidungNoetig = (bool)($res['entscheidung_noetig'] ?? false);
                $pauseAutoVorschlagMin = $pauseAutoEntscheidungNoetig ? (int)($res['auto_pause_minuten'] ?? 0) : 0;

                $pauseAutoStunden = number_format(max(0, $pauseMinAuto) / 60.0, 2, '.', '');
            }
        } catch (Throwable $e) {
            $pauseAutoStunden = '';
            $pauseAutoEntscheidungNoetig = false;
            $pauseAutoVorschlagMin = 0;
        }
        try {
            $audit = $this->holeLetztenPauseOverrideAudit($zielMitarbeiterId, $datumYmd);
            if (is_array($audit)) {
                $pauseOverrideAktiv = (($audit['aktiv'] ?? false) === true);
                $pauseOverrideBegruendung = (string)($audit['begruendung'] ?? '');

                if ($pauseOverrideAktiv) {
                    $min = null;
                    if (isset($audit['pause_minuten']) && is_numeric($audit['pause_minuten'])) {
                        $min = (int)$audit['pause_minuten'];
                    } elseif (is_array($tageswerte) && isset($tageswerte['pause_korr_minuten'])) {
                        $min = (int)$tageswerte['pause_korr_minuten'];
                    }

                    if ($min !== null) {
                        $pauseOverrideStunden = number_format($min / 60.0, 2, '.', '');
                    }
                }
            }
        } catch (Throwable $e) {
            $pauseOverrideAktiv = false;
            $pauseOverrideStunden = '';
            $pauseOverrideBegruendung = '';
        }

        // Wenn Override nicht aktiv ist, zeigen wir die automatische Pause nach Regeln an
        // (und nutzen sie als Default für eine spätere Aktivierung).
        if (!$pauseOverrideAktiv && $pauseAutoStunden !== '') {
            $pauseOverrideStunden = $pauseAutoStunden;
        }

        // Begründungen (Pflicht) für Tagesfelder (Kurzarbeit/Krank/Sonstiges) aus dem Audit-Log,
        // damit Pflichttexte in der Tagesansicht sichtbar bleiben.
        $kurzarbeitOverrideBegruendung = '';
        $krankOverrideBegruendung = '';
        $sonstigesOverrideBegruendung = '';

        try {
            $a = $this->holeLetztenTageswerteAuditEintrag($zielMitarbeiterId, $datumYmd, ['Tageswerte gesetzt: Kurzarbeit']);
            if (is_array($a)) {
                $kurzarbeitOverrideBegruendung = (string)($a['begruendung'] ?? '');
            }
        } catch (Throwable $e) {
            $kurzarbeitOverrideBegruendung = '';
        }

        try {
            $a = $this->holeLetztenTageswerteAuditEintrag($zielMitarbeiterId, $datumYmd, ['Tageswerte gesetzt: Krank', 'Tageswerte entfernt: Krank']);
            if (is_array($a)) {
                $krankOverrideBegruendung = (string)($a['begruendung'] ?? '');
            }
        } catch (Throwable $e) {
            $krankOverrideBegruendung = '';
        }

        try {
            $a = $this->holeLetztenTageswerteAuditEintrag($zielMitarbeiterId, $datumYmd, ['Tageswerte gesetzt: Sonstiges', 'Tageswerte entfernt: Sonstiges']);
            if (is_array($a)) {
                $sonstigesOverrideBegruendung = (string)($a['begruendung'] ?? '');
            }
        } catch (Throwable $e) {
            $sonstigesOverrideBegruendung = '';
        }

        // Sonstiges-Gründe (für Tagesansicht-Auswahl)
        $sonstigesGruende = [];
        if ($darfKorrigieren) {
            try {
                $db = Database::getInstanz();
                $sonstigesGruende = $db->fetchAlle(
                    'SELECT id, code, titel, default_stunden, begruendung_pflicht
                     FROM sonstiges_grund
                     WHERE aktiv = 1
                     ORDER BY sort_order ASC, titel ASC, id ASC'
                );
            } catch (Throwable $e) {
                $sonstigesGruende = [];
            }
        }

        // Variablen für View
        $datum = $datumAnzeige;
        $istAdmin = $darfKorrigieren; // View-Flag (historischer Name)

        require __DIR__ . '/../views/zeit/tagesansicht.php';
    }

    private function redirect(string $ziel): void
    {
        header('Location: ' . $ziel);
    }

    private function buildTagesansichtUrl(string $datumYmd, int $zielMitarbeiterId, bool $darfAndereMitarbeiter, ?int $editId): string
    {
        $url = '?seite=zeit_heute&datum=' . urlencode($datumYmd);

        // Für Konsistenz den Parameter immer setzen (auch wenn er bei Self ignoriert wird).
        if ($zielMitarbeiterId > 0) {
            $url .= '&mitarbeiter_id=' . urlencode((string)$zielMitarbeiterId);
        }

        if ($editId !== null && $editId > 0) {
            $url .= '&edit_id=' . urlencode((string)$editId);
        }

        if ((string)($_GET['show_micro'] ?? $_POST['show_micro'] ?? '') === '1') {
            $url .= '&show_micro=1';
        }

        // Wenn keine Auswahl erlaubt ist, wird mitarbeiter_id serverseitig sowieso auf Self reduziert.
        return $url;
    }

    private function parseYmdOrToday(string $ymd): DateTimeImmutable
    {
        $ymd = trim($ymd);
        if ($ymd !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
            if ($dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $ymd) {
                return $dt;
            }
        }

        return new DateTimeImmutable('today');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function holeMitarbeiterStammdaten(int $id): ?array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        try {
            $db = Database::getInstanz();
            $row = $db->fetchEine('SELECT id, vorname, nachname FROM mitarbeiter WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!is_array($row)) {
                return null;
            }
            return $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $mitarbeiter
     */
    private function formatMitarbeiterName(array $mitarbeiter): string
    {
        $vn = trim((string)($mitarbeiter['vorname'] ?? ''));
        $nn = trim((string)($mitarbeiter['nachname'] ?? ''));
        $name = trim($vn . ' ' . $nn);
        return $name !== '' ? $name : ('Mitarbeiter #' . (int)($mitarbeiter['id'] ?? 0));
    }

    private function parseUhrzeit(string $zeit): ?string
    {
        $zeit = trim($zeit);
        if ($zeit === '') {
            return null;
        }

        // Akzeptiere HH:MM oder HH:MM:SS
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $zeit)) {
            return null;
        }

        // Normalize auf HH:MM:SS
        if (strlen($zeit) === 5) {
            $zeit .= ':00';
        }

        $parts = explode(':', $zeit);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        $s = (int)($parts[2] ?? 0);

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59 || $s < 0 || $s > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private function parseBegruendung($begruendung): ?string
    {
        $b = trim((string)$begruendung);
        if ($b === '') {
            return null;
        }

        if (strlen($b) > 255) {
            $b = substr($b, 0, 255);
        }

        return $b;
    }

    private function markiereTageswerteAlsGeaendert(int $mitarbeiterId, string $datumYmd): void
    {
        try {
            $db = Database::getInstanz();
            $db->ausfuehren(
                'UPDATE tageswerte_mitarbeiter
                 SET rohdaten_manuell_geaendert = 1
                 WHERE mitarbeiter_id = :mid AND datum = :datum',
                ['mid' => $mitarbeiterId, 'datum' => $datumYmd]
            );
        } catch (Throwable $e) {
            // optional
        }
    }

    private function synchronisiereTageswerteNachKorrektur(int $mitarbeiterId, string $datumYmd): void
    {
        try {
            $ok = $this->zeitService->synchronisiereTageswerteAusBuchungen($mitarbeiterId, $datumYmd);
            if ($ok) {
                return;
            }
        } catch (Throwable $e) {
            // ignorieren
        }

        // Fallback: nur markieren, falls Sync aus irgendeinem Grund nicht möglich war.
        $this->markiereTageswerteAlsGeaendert($mitarbeiterId, $datumYmd);
    }

    /**
     * Audit-Log in system_log.
     *
     * @param array<string,mixed>|null $alt
     * @param array<string,mixed>|null $neu
     */
    private function loggeZeitbuchungAudit(
        string $aktion,
        int $actorMitarbeiterId,
        int $zielMitarbeiterId,
        string $datumYmd,
        ?int $buchungId,
        ?array $alt,
        ?array $neu,
        string $begruendung
    ): void {
        if (!class_exists('Logger')) {
            return;
        }

        Logger::info('Zeitbuchung korrigiert', [
            'aktion'              => $aktion,
            'buchung_id'          => $buchungId,
            'ziel_mitarbeiter_id' => $zielMitarbeiterId,
            'datum'               => $datumYmd,
            'alt'                 => $alt,
            'neu'                 => $neu,
            'begruendung'         => $begruendung,
        ], $actorMitarbeiterId, null, 'zeitbuchung_audit');
    }

    private function korrigiereUpdateBuchung(
        ZeitbuchungModel $zbModel,
        int $actorMitarbeiterId,
        int $zielMitarbeiterId,
        string $datumYmd,
        int $buchungId,
        string $typ,
        string $zeit,
        ?string $kommentar,
        string $begruendung,
        ?int $nachtshift = null
    ): bool {
        if ($buchungId <= 0) {
            return false;
        }

        $alt = $zbModel->holeNachId($buchungId);
        if (!is_array($alt) || (int)($alt['mitarbeiter_id'] ?? 0) !== $zielMitarbeiterId) {
            return false;
        }

        $altTs = (string)($alt['zeitstempel'] ?? '');
        if ($altTs === '' || !str_starts_with($altTs, $datumYmd)) {
            return false;
        }

        $zeitNorm = $this->parseUhrzeit($zeit);
        if ($zeitNorm === null) {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumYmd . ' ' . $zeitNorm);
        if (!$dt) {
            return false;
        }

        // Typ normalisieren
        if ($typ !== 'kommen' && $typ !== 'gehen') {
            $typ = 'kommen';
        }

        $nachtshiftVal = ($typ === 'kommen' && (int)$nachtshift === 1) ? 1 : 0;
        $ok = $zbModel->aktualisiereBuchung($buchungId, $typ, $dt, $kommentar, true, $nachtshiftVal);
        if ($ok) {
            $this->synchronisiereTageswerteNachKorrektur($zielMitarbeiterId, $datumYmd);

            $altAudit = [
                'typ'        => (string)($alt['typ'] ?? ''),
                'zeitstempel'=> (string)($alt['zeitstempel'] ?? ''),
                'kommentar'  => (string)($alt['kommentar'] ?? ''),
                'quelle'     => (string)($alt['quelle'] ?? ''),
            ];

            $neuAudit = [
                'typ'        => $typ,
                'zeitstempel'=> $dt->format('Y-m-d H:i:s'),
                'kommentar'  => $kommentar,
                'quelle'     => (string)($alt['quelle'] ?? ''),
                'manuell_geaendert' => 1,
                'nachtshift' => $nachtshiftVal,
            ];

            $this->loggeZeitbuchungAudit('update', $actorMitarbeiterId, $zielMitarbeiterId, $datumYmd, $buchungId, $altAudit, $neuAudit, $begruendung);
        }

        return $ok;
    }

    private function korrigiereDeleteBuchung(
        ZeitbuchungModel $zbModel,
        int $actorMitarbeiterId,
        int $zielMitarbeiterId,
        string $datumYmd,
        int $buchungId,
        string $begruendung
    ): bool {
        if ($buchungId <= 0) {
            return false;
        }

        $alt = $zbModel->holeNachId($buchungId);
        if (!is_array($alt) || (int)($alt['mitarbeiter_id'] ?? 0) !== $zielMitarbeiterId) {
            return false;
        }

        $altTs = (string)($alt['zeitstempel'] ?? '');
        if ($altTs === '' || !str_starts_with($altTs, $datumYmd)) {
            return false;
        }

        $altAudit = [
            'typ'        => (string)($alt['typ'] ?? ''),
            'zeitstempel'=> (string)($alt['zeitstempel'] ?? ''),
            'kommentar'  => (string)($alt['kommentar'] ?? ''),
            'quelle'     => (string)($alt['quelle'] ?? ''),
        ];

        $ok = $zbModel->loescheBuchung($buchungId);
        if ($ok) {
            $this->synchronisiereTageswerteNachKorrektur($zielMitarbeiterId, $datumYmd);

            $this->loggeZeitbuchungAudit('delete', $actorMitarbeiterId, $zielMitarbeiterId, $datumYmd, $buchungId, $altAudit, null, $begruendung);
        }

        return $ok;
    }

    private function korrigiereAddBuchung(
        ZeitbuchungModel $zbModel,
        int $actorMitarbeiterId,
        int $zielMitarbeiterId,
        string $datumYmd,
        string $typ,
        string $zeit,
        ?string $kommentar,
        string $begruendung,
        ?int $nachtshift = null,
        ?string &$fehler = null
    ): bool {
        $zeitNorm = $this->parseUhrzeit($zeit);
        if ($zeitNorm === null) {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumYmd . ' ' . $zeitNorm);
        if (!$dt) {
            return false;
        }

        if ($typ !== 'kommen' && $typ !== 'gehen') {
            $typ = 'kommen';
        }

        $toleranzSekunden = 180;
        try {
            if (class_exists('KonfigurationService')) {
                $cfg = KonfigurationService::getInstanz();
                $val = $cfg->getInt('micro_buchung_max_sekunden', 180);
                if ($val !== null) {
                    $toleranzSekunden = (int)$val;
                }
            }
        } catch (Throwable $e) {
            $toleranzSekunden = 180;
        }
        if ($toleranzSekunden < 0) {
            $toleranzSekunden = 0;
        } elseif ($toleranzSekunden > 3600) {
            $toleranzSekunden = 3600;
        }

        $konflikt = $zbModel->pruefeZeitstempelKonflikt($zielMitarbeiterId, $typ, $dt, $toleranzSekunden);
        if ($konflikt) {
            if ($toleranzSekunden <= 1) {
                $fehler = 'Diese Zeit liegt in derselben Sekunde wie ein vorhandener Stempel und ist deshalb nicht zulässig.';
            } else {
                $fehler = 'Diese Zeit liegt innerhalb von ' . $toleranzSekunden . ' Sekunden zu einem vorhandenen Stempel und ist deshalb nicht zulässig.';
            }
            return false;
        }

        $nachtshiftVal = ($typ === 'kommen' && (int)$nachtshift === 1) ? 1 : 0;
        $id = $zbModel->erstelleBuchung($zielMitarbeiterId, $typ, $dt, 'web', null, $kommentar, true, $nachtshiftVal);
        if ($id === null) {
            return false;
        }

        $this->synchronisiereTageswerteNachKorrektur($zielMitarbeiterId, $datumYmd);

        $neuAudit = [
            'id'         => $id,
            'typ'        => $typ,
            'zeitstempel'=> $dt->format('Y-m-d H:i:s'),
            'kommentar'  => $kommentar,
            'quelle'     => 'web',
            'manuell_geaendert' => 1,
            'nachtshift' => $nachtshiftVal,
        ];

        $this->loggeZeitbuchungAudit('add', $actorMitarbeiterId, $zielMitarbeiterId, $datumYmd, $id, null, $neuAudit, $begruendung);

        return true;
    }

    /**
     * Ermittelt den Kurzarbeit-Plan-Wert (Stunden) für genau einen Tag.
     *
     * - Liefert 0.0 wenn kein passender Plan existiert oder Tages-Soll unbekannt ist.
     * - Es findet kein DB-Write statt (reine Anzeige/Default für Tages-Override).
     */


    /**
     * Liefert den letzten Audit-Status für den Pause-Override eines Tages.
     *
     * Wichtig: Override kann auch 0,00 Stunden sein. Daher wird der Aktiv-Status
     * über Log-Einträge (gesetzt/entfernt) ermittelt.
     *
     * @return array<string,mixed>|null
     */
    private function holeLetztenPauseOverrideAudit(int $zielMitarbeiterId, string $datumYmd): ?array
    {
        try {
            $db = Database::getInstanz();

            $likeMid = '%"ziel_mitarbeiter_id":' . $zielMitarbeiterId . '%';
            $likeDatum = '%"datum":"' . $datumYmd . '"%';

            $row = $db->fetchEine(
                "SELECT id, zeitstempel, nachricht, daten
                 FROM system_log
                 WHERE kategorie = 'tageswerte_audit'
                   AND (nachricht = 'Tageswerte gesetzt: Pause-Override' OR nachricht = 'Tageswerte entfernt: Pause-Override')
                   AND daten LIKE :mid
                   AND daten LIKE :datum
                 ORDER BY zeitstempel DESC, id DESC
                 LIMIT 1",
                ['mid' => $likeMid, 'datum' => $likeDatum]
            );

            if (!is_array($row)) {
                return null;
            }

            $daten = (string)($row['daten'] ?? '');
            $decoded = json_decode($daten, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }

            $aktiv = ((string)($row['nachricht'] ?? '') === 'Tageswerte gesetzt: Pause-Override');
            $begruendung = (string)($decoded['begruendung'] ?? '');

            $pauseMinuten = null;
            if (isset($decoded['pause_minuten']) && is_numeric($decoded['pause_minuten'])) {
                $pauseMinuten = (int)$decoded['pause_minuten'];
            }

            return [
                'aktiv' => $aktiv,
                'begruendung' => $begruendung,
                'pause_minuten' => $pauseMinuten,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private function holeLetztenTageswerteAuditEintrag(int $zielMitarbeiterId, string $datumYmd, array $nachrichten): ?array
    {
        try {
            if (!is_array($nachrichten) || $nachrichten === []) {
                return null;
            }

            $db = Database::getInstanz();

            $likeMid = '%"ziel_mitarbeiter_id":' . $zielMitarbeiterId . '%';
            $likeDatum = '%"datum":"' . $datumYmd . '"%';

            $params = [
                'mid' => $likeMid,
                'datum' => $likeDatum,
            ];

            $conds = [];
            $i = 0;
            foreach ($nachrichten as $n) {
                $key = 'n' . $i;
                $conds[] = 'nachricht = :' . $key;
                $params[$key] = (string)$n;
                $i++;
            }

            $whereNachricht = '(' . implode(' OR ', $conds) . ')';

            $row = $db->fetchEine(
                "SELECT id, zeitstempel, nachricht, daten
                 FROM system_log
                 WHERE kategorie = 'tageswerte_audit'
                   AND $whereNachricht
                   AND daten LIKE :mid
                   AND daten LIKE :datum
                 ORDER BY zeitstempel DESC, id DESC
                 LIMIT 1",
                $params
            );

            if (!is_array($row)) {
                return null;
            }

            $decoded = json_decode((string)($row['daten'] ?? ''), true);
            if (!is_array($decoded)) {
                $decoded = [];
            }

            return [
                'nachricht' => (string)($row['nachricht'] ?? ''),
                'begruendung' => (string)($decoded['begruendung'] ?? ''),
                'daten' => $decoded,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }
    private function berechnePlanKurzarbeitStundenFuerTag(int $mitarbeiterId, DateTimeImmutable $tag, string $datumYmd): float
    {
        $wochenarbeitszeit = 0.0;
        try {
            $mModel = new MitarbeiterModel();
            $m = $mModel->holeNachId($mitarbeiterId);
            if (is_array($m) && isset($m['wochenarbeitszeit'])) {
                $wochenarbeitszeit = (float)str_replace(',', '.', (string)$m['wochenarbeitszeit']);
            }
        } catch (Throwable $e) {
            $wochenarbeitszeit = 0.0;
        }

        $tagesSoll = 0.0;
        if ($wochenarbeitszeit > 0) {
            $tagesSoll = $wochenarbeitszeit / 5.0;
        }

        // Dummy-Tageswert → Plan anwenden (re-use der vorhandenen Logik).
        $dummy = [[
            'datum' => $datumYmd,
            'tagestyp' => '',
            'kennzeichen_arzt' => 0,
            'kennzeichen_krank_lfz' => 0,
            'kennzeichen_krank_kk' => 0,
            'kennzeichen_feiertag' => 0,
            'kennzeichen_urlaub' => 0,
            'kennzeichen_sonstiges' => 0,
            'kennzeichen_kurzarbeit' => 0,
            'kurzarbeit_stunden' => '0.00',
        ]];

        $monatStart = $tag->modify('first day of this month');
        $applied = KurzarbeitService::getInstanz()->wendePlanAufTageswerteAn($dummy, $mitarbeiterId, $monatStart, $tagesSoll);
        $row = $applied[0] ?? null;
        if (!is_array($row)) {
            return 0.0;
        }

        $kenn = (int)($row['kennzeichen_kurzarbeit'] ?? 0);
        $std  = (float)str_replace(',', '.', (string)($row['kurzarbeit_stunden'] ?? '0'));

        if ($kenn !== 1 || $std <= 0.0) {
            return 0.0;
        }

        return round($std, 2);
    }

    /**
     * Filtert Mikro-Stempelpaare (Kommen/Gehen) aus einer Tagesliste.
     * Mikro-Paar = zwei aufeinanderfolgende Stempel (kommen/gehen oder gehen/kommen)
     * mit Zeitdifferenz <= maxSekunden.
     *
     * Die Datensätze bleiben in `zeitbuchung` erhalten (nur Anzeige-Filter).
     *
     * @param array<int,array<string,mixed>> $buchungen
     * @return array{buchungen: array<int,array<string,mixed>>, ausgeblendet: int}
     */
    private function filtereMicroZeitbuchungenAusListe(array $buchungen, int $maxSekunden = 180): array
    {
        $out = [];
        $hidden = 0;

        $n = count($buchungen);
        $i = 0;

        while ($i < $n) {
            $b1 = $buchungen[$i] ?? null;

            if (is_array($b1) && ($i + 1) < $n) {
                $b2 = $buchungen[$i + 1] ?? null;

                if (is_array($b2)) {
                    $t1 = strtolower((string)($b1['typ'] ?? ''));
                    $t2 = strtolower((string)($b2['typ'] ?? ''));

                    if (($t1 === 'kommen' || $t1 === 'gehen')
                        && ($t2 === 'kommen' || $t2 === 'gehen')
                        && $t1 !== $t2) {
                        $dt1 = $this->tryParseZeitbuchungTs((string)($b1['zeitstempel'] ?? ''));
                        $dt2 = $this->tryParseZeitbuchungTs((string)($b2['zeitstempel'] ?? ''));

                        if ($dt1 instanceof DateTimeImmutable && $dt2 instanceof DateTimeImmutable) {
                            $delta = abs($dt2->getTimestamp() - $dt1->getTimestamp());
                            if ($delta <= max(1, $maxSekunden)) {
                                // Mikro-Paar: beide Stempel ausblenden
                                $hidden += 2;
                                $i += 2;
                                continue;
                            }
                        }
                    }
                }
            }

            if (is_array($b1)) {
                $out[] = $b1;
            }
            $i++;
        }

        return [
            'buchungen' => $out,
            'ausgeblendet' => $hidden,
        ];
    }

    private function tryParseZeitbuchungTs(string $ts): ?DateTimeImmutable
    {
        $ts = trim($ts);
        if ($ts === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($ts);
        } catch (Throwable $e) {
            return null;
        }
    }

}
