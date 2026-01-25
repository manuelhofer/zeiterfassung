<?php
declare(strict_types=1);

/**
 * ZeitRundungsregelAdminController
 *
 * Backend-Controller für das Verwalten der Zeit-Rundungsregeln (`zeit_rundungsregel`).
 *
 * Hinweis zur Architektur:
 * - In dieser Iteration ist die Admin-Liste bereits nutzbar.
 * - Formular (Anlegen/Bearbeiten) wird über `bearbeiten()` bereitgestellt.
 */
class ZeitRundungsregelAdminController
{
    private const CSRF_KEY = 'zeit_rundungsregel_admin_csrf_token';
    private AuthService $authService;
    private Database $db;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->db          = Database::getInstanz();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Rundungsregeln verwalten darf.
     *
     * Primär wird das Recht `ZEIT_RUNDUNGSREGELN_VERWALTEN` geprüft.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('ZEIT_RUNDUNGSREGELN_VERWALTEN')) {
            return true;
        }

        // Legacy-Fallback: Rollen (für Bestandsinstallationen ohne gepflegte Rechtezuordnung)
        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, Rundungsregeln zu verwalten.</p>';
        return false;
    }

    /**
     * Holt oder erzeugt ein CSRF-Token für Rundungsregel-POST-Formulare.
     */
    private function holeOderErzeugeCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (\Throwable) {
                $token = bin2hex((string)mt_rand());
            }
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return (string)$token;
    }

    /**
     * Ersetzt das erste Vorkommen von $search in $subject.
     */
    private function replaceFirst(string $search, string $replace, string $subject): string
    {
        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }
        return substr($subject, 0, $pos) . $replace . substr($subject, $pos + strlen($search));
    }


    /**
     * Übersicht aller Rundungsregeln (inkl. inaktiver).
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $csrfToken = $this->holeOderErzeugeCsrfToken();

        // Explizites Anlegen der Standardregeln (UI-Button).
        // Hinweis: RundungsService seedet bereits automatisch, wenn die Tabelle leer ist.
        // Dieser Pfad ist für Admins gedacht, um die Standardregeln nachvollziehbar
        // „per Klick“ (wieder) anzulegen.
        // Ab jetzt nur noch POST + CSRF (Security/UX). GET-Links werden ignoriert.
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $aktionPost = isset($_POST['aktion']) ? trim((string)$_POST['aktion']) : '';
            if ($aktionPost === 'seed_defaults') {
                $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
                if (!hash_equals($csrfToken, $postToken)) {
                    header('Location: ?seite=zeit_rundungsregel_admin&seed_csrf=1');
                    return;
                }

                try {
                    $result = $this->seedStandardRegelnWennLeer();
                    if ($result === 'seeded') {
                        header('Location: ?seite=zeit_rundungsregel_admin&seeded=1');
                        return;
                    }
                    if ($result === 'already') {
                        header('Location: ?seite=zeit_rundungsregel_admin&already=1');
                        return;
                    }

                    header('Location: ?seite=zeit_rundungsregel_admin&seed_err=1');
                    return;
                } catch (\Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Seeden der Standard-Rundungsregeln (Admin-Button)', [
                            'exception' => $e->getMessage(),
                        ], null, null, 'rundungsregeln');
                    }
                    header('Location: ?seite=zeit_rundungsregel_admin&seed_err=1');
                    return;
                }
            }

            if ($aktionPost === 'toggle_aktiv') {
                $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
                if (!hash_equals($csrfToken, $postToken)) {
                    header('Location: ?seite=zeit_rundungsregel_admin&toggle_csrf=1');
                    return;
                }

                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($id <= 0) {
                    header('Location: ?seite=zeit_rundungsregel_admin&toggle_err=1');
                    return;
                }

                try {
                    $sqlToggle = 'UPDATE zeit_rundungsregel
                                  SET aktiv = CASE WHEN aktiv = 1 THEN 0 ELSE 1 END
                                  WHERE id = :id';
                    $rc = $this->db->ausfuehren($sqlToggle, ['id' => $id]);

                    if ($rc > 0) {
                        header('Location: ?seite=zeit_rundungsregel_admin&toggle_ok=1');
                        return;
                    }

                    header('Location: ?seite=zeit_rundungsregel_admin&toggle_nf=1');
                    return;
                } catch (\Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Quick-Toggle einer Rundungsregel (aktiv)', [
                            'id'        => $id,
                            'exception' => $e->getMessage(),
                        ], $id, null, 'rundungsregeln');
                    }
                    header('Location: ?seite=zeit_rundungsregel_admin&toggle_err=1');
                    return;
                }
            }
        }

        $aktion = isset($_GET['aktion']) ? trim((string)$_GET['aktion']) : '';
        if ($aktion === 'seed_defaults') {
            header('Location: ?seite=zeit_rundungsregel_admin&seed_need_post=1');
            return;
        }
        // (absichtlich keine zweite Seed-Logik – doppelte Verarbeitung vermeiden)

        // Sicherstellen, dass Default-Rundungsregeln vorhanden sind (falls Tabelle leer ist).
        // Die Logik ist idempotent und seedet nur dann, wenn wirklich keine Regeln existieren.
        try {
            RundungsService::getInstanz();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('RundungsService konnte nicht initialisiert werden (Default-Seeding ggf. übersprungen).', [
                    'exception' => $e->getMessage(),
                ], null, null, 'rundungsregeln');
            }
        }

        $fehlermeldung = null;
        $meldung       = null;

        if (!empty($_GET['ok'])) {
            $meldung = 'Die Rundungsregeln wurden aktualisiert.';
        }

        if (!empty($_GET['seeded'])) {
            $meldung = 'Standard-Rundungsregeln wurden angelegt.';
        }

        if (!empty($_GET['already'])) {
            $meldung = 'Es sind bereits Rundungsregeln vorhanden – Standard-Regeln wurden nicht überschrieben.';
        }

        if (!empty($_GET['seed_err'])) {
            $fehlermeldung = 'Standard-Rundungsregeln konnten nicht angelegt werden.';
        }

        if (!empty($_GET['seed_need_post'])) {
            $fehlermeldung = 'Bitte verwenden Sie den Button „Standardregeln anlegen“ (POST).';
        }

        if (!empty($_GET['seed_csrf'])) {
            $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
        }

        if (!empty($_GET['err'])) {
            $fehlermeldung = 'Die Rundungsregeln konnten nicht geladen oder gespeichert werden.';
        }

        if (!empty($_GET['toggle_ok'])) {
            $meldung = 'Aktiv-Status wurde umgeschaltet.';
        }

        if (!empty($_GET['toggle_nf'])) {
            $fehlermeldung = 'Die ausgewählte Rundungsregel wurde nicht gefunden.';
        }

        if (!empty($_GET['toggle_csrf'])) {
            $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
        }

        if (!empty($_GET['toggle_err'])) {
            $fehlermeldung = 'Der Aktiv-Status konnte nicht umgeschaltet werden.';
        }


        try {
            $sql = 'SELECT *
                    FROM zeit_rundungsregel
                    ORDER BY prioritaet ASC, von_uhrzeit ASC, bis_uhrzeit ASC, id ASC';
            $regeln = $this->db->fetchAlle($sql);
        } catch (\Throwable $e) {
            $regeln = [];
            $fehlermeldung = 'Fehler beim Laden der Rundungsregeln.';

            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Rundungsregeln (Admin)', [
                    'exception' => $e->getMessage(),
                ], null, null, 'rundungsregeln');
            }
        }

        require __DIR__ . '/../views/zeit_rundungsregel/liste.php';
    }

    /**
     * Legt die Standard-Rundungsregeln an, wenn die Tabelle `zeit_rundungsregel` leer ist.
     *
     * Rückgabe:
     * - 'seeded'  = Regeln wurden angelegt
     * - 'already' = Tabelle enthält bereits mind. 1 Regel
     * - 'error'   = Fehler beim Insert
     */
    private function seedStandardRegelnWennLeer(): string
    {
        try {
            $row = $this->db->fetchEine('SELECT COUNT(*) AS cnt FROM zeit_rundungsregel');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($cnt > 0) {
                return 'already';
            }

            $sql = 'INSERT INTO zeit_rundungsregel
                        (von_uhrzeit, bis_uhrzeit, einheit_minuten, richtung, gilt_fuer, prioritaet, aktiv, beschreibung)
                    VALUES
                        (:von1, :bis1, :einheit1, :richtung1, :gilt1, :prio1, 1, :besch1),
                        (:von2, :bis2, :einheit2, :richtung2, :gilt2, :prio2, 1, :besch2)';

            $this->db->ausfuehren($sql, [
                'von1'      => '00:00:00',
                'bis1'      => '07:00:00',
                'einheit1'  => 30,
                'richtung1' => 'naechstgelegen',
                'gilt1'     => 'beide',
                'prio1'     => 1,
                'besch1'    => 'Standard: 00:00–07:00 auf 30 Minuten runden (nächstgelegen)',

                'von2'      => '07:00:00',
                'bis2'      => '23:59:59',
                'einheit2'  => 15,
                'richtung2' => 'naechstgelegen',
                'gilt2'     => 'beide',
                'prio2'     => 2,
                'besch2'    => 'Standard: 07:00–24:00 auf 15 Minuten runden (nächstgelegen)',
            ]);

            if (class_exists('Logger')) {
                Logger::info('Standard-Rundungsregeln wurden per Admin-UI angelegt (Tabelle war leer).', [], null, null, 'rundungsregeln');
            }

            return 'seeded';
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Anlegen der Standard-Rundungsregeln (Admin-UI).', [
                    'exception' => $e->getMessage(),
                ], null, null, 'rundungsregeln');
            }
            return 'error';
        }
    }

    /**
     * Formular zum Anlegen/Bearbeiten einer Rundungsregel.
     *
     * Hinweis:
     * - Es gibt (noch) keine separate "speichern"-Route – das Formular postet zurück
     *   auf diese Methode.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $fehlermeldung = null;

        $csrfToken = $this->holeOderErzeugeCsrfToken();

        $idRaw = $_GET['id'] ?? ($_POST['id'] ?? '');
        $id    = is_numeric($idRaw) ? (int)$idRaw : 0;

        $regel = null;

        // POST = speichern
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $von         = trim((string)($_POST['von_uhrzeit'] ?? ''));
            $bis         = trim((string)($_POST['bis_uhrzeit'] ?? ''));
            $einheitRaw  = $_POST['einheit_minuten'] ?? '';
            $richtung    = trim((string)($_POST['richtung'] ?? 'naechstgelegen'));
            $giltFuer    = trim((string)($_POST['gilt_fuer'] ?? 'beide'));
            $prioRaw     = $_POST['prioritaet'] ?? '1';
            $aktiv       = isset($_POST['aktiv']) ? 1 : 0;
            $beschreibung= trim((string)($_POST['beschreibung'] ?? ''));

            $einheit = is_numeric($einheitRaw) ? (int)$einheitRaw : 0;
            $prio    = is_numeric($prioRaw) ? (int)$prioRaw : 1;

            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            }

            // Grundvalidierung
            if ($fehlermeldung === null && ($von === '' || $bis === '')) {
                $fehlermeldung = 'Bitte geben Sie einen Zeitbereich (von/bis) an.';
            }

            // Zeitformat prüfen (HH:MM oder HH:MM:SS)
            if ($fehlermeldung === null) {
                $timeRegex = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';
                if (!preg_match($timeRegex, $von) || !preg_match($timeRegex, $bis)) {
                    $fehlermeldung = 'Bitte geben Sie die Uhrzeiten im Format HH:MM an.';
                }
            }

            // Bis muss nach Von liegen (Service unterstützt kein "über Mitternacht")
            if ($fehlermeldung === null) {
                $vonMin = $this->zeitZuMinuten($von);
                $bisMin = $this->zeitZuMinuten($bis);

                if ($vonMin === null || $bisMin === null) {
                    $fehlermeldung = 'Ungültiger Zeitbereich.';
                } elseif ($bisMin <= $vonMin) {
                    $fehlermeldung = '"Bis" muss später als "Von" sein (keine Bereiche über Mitternacht).';
                }
            }

            if ($fehlermeldung === null && ($einheit <= 0 || $einheit > 24 * 60)) {
                $fehlermeldung = 'Die Einheit (Minuten) muss zwischen 1 und 1440 liegen.';
            }

            $erlaubteRichtungen = ['auf', 'ab', 'naechstgelegen'];
            if ($fehlermeldung === null && !in_array($richtung, $erlaubteRichtungen, true)) {
                $fehlermeldung = 'Ungültige Rundungsrichtung.';
            }

            $erlaubteGiltFuer = ['kommen', 'gehen', 'beide'];
            if ($fehlermeldung === null && !in_array($giltFuer, $erlaubteGiltFuer, true)) {
                $fehlermeldung = 'Ungültiger Typ (Gilt für).';
            }

            if ($prio <= 0) {
                $prio = 1;
            }

            if ($beschreibung !== '' && mb_strlen($beschreibung, 'UTF-8') > 255) {
                $beschreibung = mb_substr($beschreibung, 0, 255, 'UTF-8');
            }

            // Fürs Formular wieder füllen
            $regel = [
                'id'             => $id,
                'von_uhrzeit'     => $von,
                'bis_uhrzeit'     => $bis,
                'einheit_minuten' => $einheit,
                'richtung'        => $richtung,
                'gilt_fuer'       => $giltFuer,
                'prioritaet'      => $prio,
                'aktiv'           => $aktiv,
                'beschreibung'    => $beschreibung,
            ];

            if ($fehlermeldung === null) {
                try {
                    if ($id > 0) {
                        $sql = 'UPDATE zeit_rundungsregel
                                SET von_uhrzeit = :von,
                                    bis_uhrzeit = :bis,
                                    einheit_minuten = :einheit,
                                    richtung = :richtung,
                                    gilt_fuer = :gilt_fuer,
                                    prioritaet = :prio,
                                    aktiv = :aktiv,
                                    beschreibung = :beschreibung
                                WHERE id = :id';

                        $rc = $this->db->ausfuehren($sql, [
                            'von'          => $von,
                            'bis'          => $bis,
                            'einheit'      => $einheit,
                            'richtung'     => $richtung,
                            'gilt_fuer'    => $giltFuer,
                            'prio'         => $prio,
                            'aktiv'        => $aktiv,
                            'beschreibung' => ($beschreibung !== '' ? $beschreibung : null),
                            'id'           => $id,
                        ]);

                        if ($rc <= 0) {
                            // Auch "keine Änderung" ist okay – wir behandeln nur echte Fehler als Fehler.
                        }
                    } else {
                        $sql = 'INSERT INTO zeit_rundungsregel
                                (von_uhrzeit, bis_uhrzeit, einheit_minuten, richtung, gilt_fuer, prioritaet, aktiv, beschreibung)
                                VALUES
                                (:von, :bis, :einheit, :richtung, :gilt_fuer, :prio, :aktiv, :beschreibung)';

                        $this->db->ausfuehren($sql, [
                            'von'          => $von,
                            'bis'          => $bis,
                            'einheit'      => $einheit,
                            'richtung'     => $richtung,
                            'gilt_fuer'    => $giltFuer,
                            'prio'         => $prio,
                            'aktiv'        => $aktiv,
                            'beschreibung' => ($beschreibung !== '' ? $beschreibung : null),
                        ]);
                    }

                    header('Location: ?seite=zeit_rundungsregel_admin&ok=1');
                    return;
                } catch (\Throwable $e) {
                    $fehlermeldung = 'Die Rundungsregel konnte nicht gespeichert werden.';
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Speichern einer Rundungsregel', [
                            'id'        => $id,
                            'regel'     => $regel,
                            'exception' => $e->getMessage(),
                        ], $id > 0 ? $id : null, null, 'rundungsregeln');
                    }
                }
            }
        }

        // GET = laden
        if ($regel === null) {
            if ($id > 0) {
                try {
                    $regel = $this->db->fetchEine('SELECT * FROM zeit_rundungsregel WHERE id = :id', ['id' => $id]);
                    if ($regel === null) {
                        $fehlermeldung = 'Die ausgewählte Rundungsregel wurde nicht gefunden.';
                        $regel = null;
                    }
                } catch (\Throwable $e) {
                    $fehlermeldung = 'Die Rundungsregel konnte nicht geladen werden.';
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Laden einer Rundungsregel (Admin)', [
                            'id'        => $id,
                            'exception' => $e->getMessage(),
                        ], $id, null, 'rundungsregeln');
                    }
                }
            }

            if ($regel === null) {
                // Defaults für "neu"
                $regel = [
                    'id'             => 0,
                    'von_uhrzeit'     => '00:00',
                    'bis_uhrzeit'     => '23:59',
                    'einheit_minuten' => 15,
                    'richtung'        => 'naechstgelegen',
                    'gilt_fuer'       => 'beide',
                    'prioritaet'      => 1,
                    'aktiv'           => 1,
                    'beschreibung'    => '',
                ];
            }
        }

        
        // View rendern, aber CSRF-Feld ohne zusätzliche View-Datei-Änderung injizieren (Patch-Limit).
        ob_start();
        require __DIR__ . '/../views/zeit_rundungsregel/formular.php';
        $html = ob_get_clean();

        if (is_string($html)) {
            if (strpos($html, 'name="csrf_token"') === false) {
                $tokenHtml = '<input type="hidden" name="csrf_token" value="'
                    . htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '">';

                if (strpos($html, '<input type="hidden" name="id"') !== false) {
                    $html = $this->replaceFirst('<input type="hidden" name="id"', $tokenHtml . '<input type="hidden" name="id"', $html);
                }
            }

            echo $html;
            return;
        }

        // Fallback (sollte nicht passieren)
        require __DIR__ . '/../views/zeit_rundungsregel/formular.php';
    }

    /**
     * Konvertiert "HH:MM" oder "HH:MM:SS" nach Minuten seit Mitternacht.
     */
    private function zeitZuMinuten(string $time): ?int
    {
        $teile = explode(':', $time);
        if (count($teile) < 2) {
            return null;
        }

        $h = (int)$teile[0];
        $m = (int)$teile[1];

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return $h * 60 + $m;
    }
}
