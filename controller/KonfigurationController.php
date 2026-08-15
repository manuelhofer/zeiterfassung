<?php
declare(strict_types=1);

/**
 * KonfigurationController
 *
 * Backend-UI für die `config`-Tabelle (Key/Value).
 */
class KonfigurationController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'konfiguration_admin';
    private AuthService $authService;
    private Database $datenbank;
    private KonfigurationService $konfigurationService;

    public function __construct()
    {
        $this->authService           = AuthService::getInstanz();
        $this->datenbank             = Database::getInstanz();
        $this->konfigurationService  = KonfigurationService::getInstanz();
    }

        /**
     * Prüft, ob der aktuell angemeldete Benutzer die Konfiguration verwalten darf.
     *
     * Primär wird das Recht `KONFIGURATION_VERWALTEN` geprüft.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('KONFIGURATION_VERWALTEN')) {
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
        echo '<p>Sie haben keine Berechtigung, die Konfiguration zu verwalten.</p>';
        return false;
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer Krankzeiten (LFZ/KK) verwalten darf.
     *
     * Primär (falls vorhanden): Recht `KRANKZEITRAUM_VERWALTEN` oder `KONFIGURATION_VERWALTEN`.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriffKrankzeitraum(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        try {
            if (
                $this->authService->hatRecht('KRANKZEITRAUM_VERWALTEN')
                || $this->authService->hatRecht('KONFIGURATION_VERWALTEN')
            ) {
                return true;
            }
        } catch (Throwable) {
            // Fallback unten
        }

        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, Krankzeiten zu verwalten.</p>';
        return false;
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer Pausenregeln verwalten darf.
     *
     * Primär (falls vorhanden): Recht `PAUSENREGELN_VERWALTEN` oder `KONFIGURATION_VERWALTEN`.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriffPausenregeln(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        try {
            if (
                $this->authService->hatRecht('PAUSENREGELN_VERWALTEN')
                || $this->authService->hatRecht('KONFIGURATION_VERWALTEN')
            ) {
                return true;
            }
        } catch (Throwable) {
            // Fallback unten
        }

        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, Pausenregeln zu verwalten.</p>';
        return false;
    }

    /**
     * Prüft eine Uhrzeit aus einem Formular auf `HH:MM` **im gültigen Bereich**
     * (00:00 bis 23:59).
     *
     * Der Browser liefert über `<input type="time">` nur gültige Werte; ein
     * POST von Hand nicht. Ohne Bereichsprüfung kommt `25:99` durch und wird
     * beim Vergleich zweier Uhrzeiten als späte Zeit gelesen (B-099).
     */
    private static function istUhrzeit(string $wert): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $wert) === 1;
    }

    /**
     * Prüft ein Datum auf `YYYY-MM-DD` **und darauf, dass es den Tag gibt**.
     *
     * `checkdate()` statt eines längeren Ausdrucks: Schaltjahre und die Länge
     * der Monate stehen damit nicht noch einmal im Code. Ohne diese Prüfung
     * kommt `2026-02-30` bis zur Datenbank und wird dort abgewiesen – der
     * Benutzer liest dann „Speichern fehlgeschlagen." statt eines Hinweises
     * auf sein Datum (B-100).
     */
    private static function istDatum(string $wert): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $wert, $treffer) !== 1) {
            return false;
        }

        return checkdate((int)$treffer[2], (int)$treffer[3], (int)$treffer[1]);
    }

    /**
     * @return array<int,array{id:int,name:string,aktiv:int}>
     */
    private function holeMitarbeiterListe(int $includeId = 0): array
    {
        $includeId = max(0, (int)$includeId);

        $sql = "SELECT id, vorname, nachname, aktiv FROM mitarbeiter WHERE aktiv = 1";
        $params = [];
        if ($includeId > 0) {
            $sql .= " OR id = :id";
            $params['id'] = $includeId;
        }
        $sql .= " ORDER BY nachname, vorname";

        try {
            $rows = $this->datenbank->fetchAlle($sql, $params);
        } catch (Throwable) {
            $rows = [];
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $vn = trim((string)($r['vorname'] ?? ''));
            $nn = trim((string)($r['nachname'] ?? ''));
            $name = trim($nn . ', ' . $vn);
            if ($name === '') {
                $name = 'Mitarbeiter #' . $id;
            }
            $out[] = ['id' => $id, 'name' => $name, 'aktiv' => (int)($r['aktiv'] ?? 0)];
        }

        return $out;
    }




    /**
     * Übersicht aller Config-Einträge.
     */
    public function index(): void
    {
        $tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : '';
        if ($tab === 'krankzeitraum') {
            if (!$this->pruefeZugriffKrankzeitraum()) {
                return;
            }

            $this->indexKrankzeitraum();
            return;
        }

        if ($tab === 'pausen') {
            if (!$this->pruefeZugriffPausenregeln()) {
                return;
            }

            $this->indexPausenregeln();
            return;
        }

        
        if ($tab === 'sonstiges') {
            if (!$this->pruefeZugriff()) {
                return;
            }

            $this->indexSonstigesGruende();
            return;
        }

        if ($tab === 'systemlog') {
            if (!$this->pruefeZugriff()) {
                return;
            }

            $this->indexSystemlog();
            return;
        }

        if (!$this->pruefeZugriff()) {
            return;
        }

        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

        $eintraege = [];
        $fehlermeldung = null;
        // Getrennt von $fehlermeldung, weil nur dieser Fall die leere Liste
        // erklärt: „nicht ladbar" und „nichts vorhanden" sind zwei Aussagen.
        $ladefehler = false;

        try {
            $eintraege = $this->konfigurationService->getAlle();
        } catch (Throwable $e) {
            $fehlermeldung = 'Die Konfiguration konnte nicht geladen werden.';
            $ladefehler = true;
            Logger::error('Fehler beim Laden der Konfiguration', [
                'exception' => $e->getMessage(),
            ], null, null, 'config');
        }

        require __DIR__ . '/../views/konfiguration/liste.php';
    }

    /**
     * Admin-UI: System-Log (Warnung/Fehler) anzeigen.
     */
    private function indexSystemlog(): void
    {
        $limit = 200;
        $limit = max(10, min(500, $limit));

        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
        $csrfBereich = self::CSRF_BEREICH;
        $fehlermeldung = null;
        // Siehe index(): nur ein Lesefehler erklärt die leere Liste. Hier zählt
        // der eigene Merker doppelt, weil auch eine misslungene POST-Aktion
        // $fehlermeldung setzt – die sagt über den Bestand nichts aus.
        $ladefehler = false;
        $eintraege = [];

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');
        if ($istPost) {
            if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            } else {
                $aktion = isset($_POST['log_action']) ? trim((string)$_POST['log_action']) : '';

                if ($aktion === 'loeschen') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $id = max(0, $id);
                    if ($id <= 0) {
                        $fehlermeldung = 'Ungültige Log-ID.';
                    } else {
                        try {
                            $this->datenbank->ausfuehren('DELETE FROM system_log WHERE id = :id', ['id' => $id]);
                            header('Location: ?seite=konfiguration_admin&tab=systemlog&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Löschen fehlgeschlagen.';
                            Logger::error('Fehler beim Löschen eines System-Log-Eintrags', [
                                'id' => $id,
                                'exception' => $e->getMessage(),
                            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'system_log');
                        }
                    }
                }

                if ($aktion === 'leeren') {
                    try {
                        $this->datenbank->ausfuehren('DELETE FROM system_log');
                        header('Location: ?seite=konfiguration_admin&tab=systemlog&ok=1');
                        return;
                    } catch (Throwable $e) {
                        $fehlermeldung = 'Das System-Log konnte nicht geleert werden.';
                        Logger::error('Fehler beim Leeren des System-Logs', [
                            'exception' => $e->getMessage(),
                        ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'system_log');
                    }
                }
            }
        }

        try {
            $sql = "SELECT l.id, l.zeitstempel, l.loglevel, l.kategorie, l.nachricht, l.daten, l.mitarbeiter_id, l.terminal_id,
                           m.vorname AS m_vorname, m.nachname AS m_nachname
                    FROM system_log l
                    LEFT JOIN mitarbeiter m ON m.id = l.mitarbeiter_id
                    WHERE LOWER(l.loglevel) IN ('warn', 'error')
                    ORDER BY l.zeitstempel DESC
                    LIMIT " . (int)$limit;
            $eintraege = $this->datenbank->fetchAlle($sql);
        } catch (Throwable $e) {
            $fehlermeldung = 'Das System-Log konnte nicht geladen werden.';
            $ladefehler = true;
            Logger::error('Fehler beim Laden des System-Logs', [
                'exception' => $e->getMessage(),
            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'system_log');
        }

        require __DIR__ . '/../views/konfiguration/systemlog.php';
    }



    /**
     * Admin-UI: Krankzeiten (LFZ/KK) über `krankzeitraum` verwalten.
     *
     * Umsetzung (T-071, Teil 2):
     * - Liste + Formular (Neu/Bearbeiten) auf einer Seite.
     * - Speichern + Aktivieren/Deaktivieren (Toggle).
     * - Overlap-Check pro Mitarbeiter für aktive Zeiträume.
     */
    private function indexKrankzeitraum(): void
    {
        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

        $csrfBereich = self::CSRF_BEREICH;
        $fehlermeldung = null;
        $hinweismeldung = null;

        $editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $editId = max(0, $editId);
        $wechselKkVonId = isset($_GET['wechsel_kk_von']) ? (int)$_GET['wechsel_kk_von'] : 0;
        $wechselKkVonId = max(0, $wechselKkVonId);

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');

        // Default-Form
        $form = [
            'id' => 0,
            'mitarbeiter_id' => 0,
            'typ' => 'lfz',
            'von_datum' => '',
            'bis_datum' => '',
            'kommentar' => '',
            'aktiv' => 1,
        ];

        $formatKrankDatumAnzeige = static function (?string $datum): string {
            $datum = trim((string)$datum);
            if ($datum === '') {
                return '';
            }

            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $m) === 1) {
                return $m[3] . '.' . $m[2] . '.' . $m[1];
            }

            if (preg_match('/^(\d{2})[-.](\d{2})[-.](\d{4})$/', $datum, $m) === 1) {
                return $m[1] . '.' . $m[2] . '.' . $m[3];
            }

            return $datum;
        };

        $normalisiereKrankDatumEingabe = static function (string $datum): string {
            $datum = trim($datum);
            if ($datum === '') {
                return '';
            }

            $tz = new DateTimeZone('Europe/Berlin');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) === 1) {
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $datum, $tz);
                $errors = DateTimeImmutable::getLastErrors();
                $ok = $dt instanceof DateTimeImmutable
                    && ($errors === false || ((int)($errors['warning_count'] ?? 0) === 0 && (int)($errors['error_count'] ?? 0) === 0));
                return $ok ? $dt->format('Y-m-d') : $datum;
            }

            if (preg_match('/^\d{2}[-.]\d{2}[-.]\d{4}$/', $datum) === 1) {
                $dt = DateTimeImmutable::createFromFormat('!d-m-Y', str_replace('.', '-', $datum), $tz);
                $errors = DateTimeImmutable::getLastErrors();
                $ok = $dt instanceof DateTimeImmutable
                    && ($errors === false || ((int)($errors['warning_count'] ?? 0) === 0 && (int)($errors['error_count'] ?? 0) === 0));
                return $ok ? $dt->format('Y-m-d') : $datum;
            }

            return $datum;
        };

        $pruefeKrankzeitraumRegeln = function (int $mitarbeiterId, string $typ, string $von, ?string $bis, int $id) use ($formatKrankDatumAnzeige): ?string {
            $newBis = $bis ?? '9999-12-31';

            if ($typ === 'kk') {
                $lfOverlap = $this->datenbank->fetchEine(
                    "SELECT id, von_datum, bis_datum FROM krankzeitraum
                     WHERE aktiv = 1 AND mitarbeiter_id = :mid AND id <> :id AND typ = 'lfz'
                       AND von_datum <= :newBis
                       AND (bis_datum IS NULL OR bis_datum >= :newVon)
                     ORDER BY von_datum DESC, id DESC
                     LIMIT 1",
                    [
                        'mid' => $mitarbeiterId,
                        'id' => $id,
                        'newBis' => $newBis,
                        'newVon' => $von,
                    ]
                );

                if ($lfOverlap !== null) {
                    $lfVon = (string)($lfOverlap['von_datum'] ?? '');
                    $lfBis = (string)($lfOverlap['bis_datum'] ?? '');
                    $lfVonAnzeige = $formatKrankDatumAnzeige($lfVon);
                    $lfBisAnzeige = $lfBis !== '' ? $formatKrankDatumAnzeige($lfBis) : 'offen';
                    return 'Der Zeitraum ist gesperrt: Mitarbeiter ist vom ' . $lfVonAnzeige . ' bis ' . $lfBisAnzeige . ' bereits Krank LF. Krank KK darf erst danach beginnen.';
                }

                $vorherigeLf = $this->datenbank->fetchEine(
                    "SELECT id, von_datum, bis_datum FROM krankzeitraum
                     WHERE aktiv = 1 AND mitarbeiter_id = :mid AND id <> :id AND typ = 'lfz'
                       AND bis_datum IS NOT NULL
                       AND bis_datum < :newVon
                     ORDER BY bis_datum DESC, id DESC
                     LIMIT 1",
                    [
                        'mid' => $mitarbeiterId,
                        'id' => $id,
                        'newVon' => $von,
                    ]
                );

                if ($vorherigeLf === null) {
                    return 'Krank KK kann erst angelegt werden, wenn vorher ein beendeter Krank-LF-Zeitraum vorhanden ist.';
                }
            }

            $row = $this->datenbank->fetchEine(
                "SELECT id, typ, von_datum, bis_datum FROM krankzeitraum
                 WHERE aktiv = 1 AND mitarbeiter_id = :mid AND id <> :id
                   AND von_datum <= :newBis
                   AND (bis_datum IS NULL OR bis_datum >= :newVon)
                 LIMIT 1",
                [
                    'mid' => $mitarbeiterId,
                    'id' => $id,
                    'newBis' => $newBis,
                    'newVon' => $von,
                ]
            );

            if ($row !== null) {
                $ovId = (int)($row['id'] ?? 0);
                $ovVon = (string)($row['von_datum'] ?? '');
                $ovBis = (string)($row['bis_datum'] ?? '');
                $ovVonAnzeige = $formatKrankDatumAnzeige($ovVon);
                $ovBisAnzeige = $ovBis !== '' ? $formatKrankDatumAnzeige($ovBis) : 'offen';
                return 'Überschneidung mit Zeitraum #' . $ovId . ' (' . $ovVonAnzeige . ' bis ' . $ovBisAnzeige . ').';
            }

            return null;
        };

        // POST-Aktionen
        if ($istPost) {
            if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            } else {
                $aktion = isset($_POST['krank_action']) ? trim((string)$_POST['krank_action']) : '';

                if ($aktion === 'toggle') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $aktiv = isset($_POST['aktiv']) ? (int)$_POST['aktiv'] : 0;
                    $id = max(0, $id);
                    $aktiv = $aktiv === 1 ? 1 : 0;

                    if ($id <= 0) {
                        $fehlermeldung = 'Ungültige ID.';
                    } else {
                        try {
                            if ($aktiv === 1) {
                                $row = $this->datenbank->fetchEine(
                                    'SELECT id, mitarbeiter_id, typ, von_datum, bis_datum FROM krankzeitraum WHERE id = :id',
                                    ['id' => $id]
                                );

                                if ($row === null) {
                                    $fehlermeldung = 'Eintrag nicht gefunden.';
                                } else {
                                    $regelFehler = $pruefeKrankzeitraumRegeln(
                                        (int)($row['mitarbeiter_id'] ?? 0),
                                        (string)($row['typ'] ?? ''),
                                        (string)($row['von_datum'] ?? ''),
                                        ((string)($row['bis_datum'] ?? '') !== '') ? (string)$row['bis_datum'] : null,
                                        $id
                                    );
                                    if ($regelFehler !== null) {
                                        $fehlermeldung = $regelFehler;
                                    }
                                }
                            }

                            if ($fehlermeldung === null) {
                                $this->datenbank->ausfuehren(
                                    'UPDATE krankzeitraum SET aktiv = :a WHERE id = :id',
                                    ['a' => $aktiv, 'id' => $id]
                                );
                                header('Location: ?seite=konfiguration_admin&tab=krankzeitraum&ok=1');
                                return;
                            }
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Speichern fehlgeschlagen.';
                            Logger::error('Fehler beim Toggle krankzeitraum', [
                                'id' => $id,
                                'aktiv' => $aktiv,
                                'exception' => $e->getMessage(),
                            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'krank');
                        }
                    }
                }

                if ($aktion === 'speichern') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $id = max(0, $id);

                    $mitarbeiterId = isset($_POST['mitarbeiter_id']) ? (int)$_POST['mitarbeiter_id'] : 0;
                    $mitarbeiterId = max(0, $mitarbeiterId);

                    $typ = isset($_POST['typ']) ? trim((string)$_POST['typ']) : '';
                    if (!in_array($typ, ['lfz','kk'], true)) {
                        $typ = '';
                    }

                    $von = isset($_POST['von_datum']) ? $normalisiereKrankDatumEingabe((string)$_POST['von_datum']) : '';
                    $bis = isset($_POST['bis_datum']) ? $normalisiereKrankDatumEingabe((string)$_POST['bis_datum']) : '';
                    if ($bis === '') {
                        $bis = null;
                    }

                    $kommentar = isset($_POST['kommentar']) ? trim((string)$_POST['kommentar']) : '';
                    if ($kommentar === '') {
                        $kommentar = null;
                    }

                    $aktiv = isset($_POST['aktiv']) ? 1 : 0;

                    $form = [
                        'id' => $id,
                        'mitarbeiter_id' => $mitarbeiterId,
                        'typ' => $typ !== '' ? $typ : 'lfz',
                        'von_datum' => $von,
                        'bis_datum' => $bis ?? '',
                        'kommentar' => $kommentar ?? '',
                        'aktiv' => $aktiv,
                    ];

                    // Validierung
                    if ($mitarbeiterId <= 0) {
                        $fehlermeldung = 'Bitte einen Mitarbeiter auswählen.';
                    } elseif ($typ === '') {
                        $fehlermeldung = 'Bitte einen Typ wählen (LFZ/KK).';
                    } elseif (!self::istDatum($von)) {
                        $fehlermeldung = 'Bitte ein gültiges Von-Datum angeben.';
                    } elseif ($bis !== null && !self::istDatum((string)$bis)) {
                        $fehlermeldung = 'Bitte ein gültiges Bis-Datum angeben (oder leer lassen).';
                    } elseif ($bis !== null && (string)$bis < $von) {
                        // Zeichenvergleich reicht: Beide Werte sind hier geprüft
                        // und normalisiert (`YYYY-MM-DD`).
                        $fehlermeldung = 'Bis-Datum darf nicht vor Von-Datum liegen.';
                    }

                    if ($fehlermeldung === null && $aktiv === 1 && $typ === 'kk') {
                        try {
                            $regelFehler = $pruefeKrankzeitraumRegeln($mitarbeiterId, $typ, $von, $bis, $id);
                            if ($regelFehler !== null) {
                                $fehlermeldung = $regelFehler;
                            }
                        } catch (Throwable) {
                            // defensiv: der bestehende Overlap-Check bleibt als Rückfall aktiv
                        }
                    }

                    // Overlap-Check nur für aktive Zeiträume
                    if ($fehlermeldung === null && $aktiv === 1) {
                        $newVon = $von;
                        $newBis = $bis ?? '9999-12-31';

                        try {
                            $row = $this->datenbank->fetchEine(
                                "SELECT id, typ, von_datum, bis_datum FROM krankzeitraum
                                 WHERE aktiv = 1 AND mitarbeiter_id = :mid AND id <> :id
                                   AND von_datum <= :newBis
                                   AND (bis_datum IS NULL OR bis_datum >= :newVon)
                                 LIMIT 1",
                                [
                                    'mid' => $mitarbeiterId,
                                    'id' => $id,
                                    'newBis' => $newBis,
                                    'newVon' => $newVon,
                                ]
                            );
                            if ($row !== null) {
                                $ovId = (int)($row['id'] ?? 0);
                                $ovVon = (string)($row['von_datum'] ?? '');
                                $ovBis = (string)($row['bis_datum'] ?? '');
                                $ovVonAnzeige = $formatKrankDatumAnzeige($ovVon);
                                $ovBisAnzeige = $ovBis !== '' ? $formatKrankDatumAnzeige($ovBis) : 'offen';
                                $fehlermeldung = 'Überschneidung mit Zeitraum #' . $ovId . ' (' . $ovVonAnzeige . ' bis ' . $ovBisAnzeige . ').';
                            }
                        } catch (Throwable) {
                            // defensiv: bei Check-Fehler nicht blockieren, aber warnen
                        }
                    }

                    if ($fehlermeldung === null) {
                        $angemeldeterId = $this->authService->holeAngemeldeteMitarbeiterId();
                        $angemeldeterId = $angemeldeterId !== null ? (int)$angemeldeterId : null;

                        try {
                            if ($id > 0) {
                                $this->datenbank->ausfuehren(
                                    'UPDATE krankzeitraum
                                     SET mitarbeiter_id = :mid, typ = :typ, von_datum = :von, bis_datum = :bis, kommentar = :kommentar, aktiv = :aktiv
                                     WHERE id = :id',
                                    [
                                        'mid' => $mitarbeiterId,
                                        'typ' => $typ,
                                        'von' => $von,
                                        'bis' => $bis,
                                        'kommentar' => $kommentar,
                                        'aktiv' => $aktiv,
                                        'id' => $id,
                                    ]
                                );
                            } else {
                                $this->datenbank->ausfuehren(
                                    'INSERT INTO krankzeitraum (mitarbeiter_id, typ, von_datum, bis_datum, kommentar, aktiv, angelegt_von_mitarbeiter_id)
                                     VALUES (:mid, :typ, :von, :bis, :kommentar, :aktiv, :angelegt_von)',
                                    [
                                        'mid' => $mitarbeiterId,
                                        'typ' => $typ,
                                        'von' => $von,
                                        'bis' => $bis,
                                        'kommentar' => $kommentar,
                                        'aktiv' => $aktiv,
                                        'angelegt_von' => $angemeldeterId,
                                    ]
                                );
                            }

                            header('Location: ?seite=konfiguration_admin&tab=krankzeitraum&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Speichern fehlgeschlagen.';
                            Logger::error('Fehler beim Speichern krankzeitraum', [
                                'id' => $id,
                                'mid' => $mitarbeiterId,
                                'typ' => $typ,
                                'von' => $von,
                                'bis' => $bis,
                                'aktiv' => $aktiv,
                                'exception' => $e->getMessage(),
                            ], $angemeldeterId, null, 'krank');
                        }
                    }
                }
            }
        }

        // Krank KK aus einem abgeschlossenen Krank-LF-Zeitraum vorbereiten.
        if ($wechselKkVonId > 0 && !$istPost) {
            try {
                $row = $this->datenbank->fetchEine(
                    "SELECT id, mitarbeiter_id, von_datum, bis_datum
                     FROM krankzeitraum
                     WHERE id = :id AND typ = 'lfz' AND aktiv = 1",
                    ['id' => $wechselKkVonId]
                );

                if ($row === null) {
                    $fehlermeldung = 'Krank-LF-Zeitraum nicht gefunden oder nicht aktiv.';
                } else {
                    $lfBis = (string)($row['bis_datum'] ?? '');
                    if ($lfBis === '') {
                        $fehlermeldung = 'Krank KK kann erst vorbereitet werden, wenn der Krank-LF-Zeitraum ein Bis-Datum hat.';
                    } else {
                        $dt = new DateTimeImmutable($lfBis, new DateTimeZone('Europe/Berlin'));
                        $kkVon = $dt->modify('+1 day')->format('Y-m-d');
                        $form = [
                            'id' => 0,
                            'mitarbeiter_id' => (int)($row['mitarbeiter_id'] ?? 0),
                            'typ' => 'kk',
                            'von_datum' => $kkVon,
                            'bis_datum' => '',
                            'kommentar' => '',
                            'aktiv' => 1,
                        ];
                        $hinweismeldung = 'Krank KK wurde mit dem Folgetag nach Krank LF vorbereitet.';
                    }
                }

                $editId = 0;
            } catch (Throwable) {
                $fehlermeldung = 'Krank-KK-Wechsel konnte nicht vorbereitet werden.';
                $editId = 0;
            }
        }

        // Laden für Bearbeiten
        if ($editId > 0 && !$istPost) {
            try {
                $row = $this->datenbank->fetchEine('SELECT * FROM krankzeitraum WHERE id = :id', ['id' => $editId]);
                if ($row !== null) {
                    $form = [
                        'id' => (int)($row['id'] ?? 0),
                        'mitarbeiter_id' => (int)($row['mitarbeiter_id'] ?? 0),
                        'typ' => (string)($row['typ'] ?? 'lfz'),
                        'von_datum' => (string)($row['von_datum'] ?? ''),
                        'bis_datum' => (string)($row['bis_datum'] ?? ''),
                        'kommentar' => (string)($row['kommentar'] ?? ''),
                        'aktiv' => (int)($row['aktiv'] ?? 1),
                    ];
                } else {
                    $fehlermeldung = 'Eintrag nicht gefunden.';
                    $editId = 0;
                }
            } catch (Throwable) {
                $fehlermeldung = 'Eintrag konnte nicht geladen werden.';
                $editId = 0;
            }
        }

        // Liste laden
        $eintraege = [];
        $ladefehler = false;
        try {
            $eintraege = $this->datenbank->fetchAlle(
                "SELECT k.*, m.vorname AS m_vorname, m.nachname AS m_nachname
                 FROM krankzeitraum k
                 LEFT JOIN mitarbeiter m ON m.id = k.mitarbeiter_id
                 ORDER BY k.aktiv DESC, k.von_datum DESC, k.id DESC"
            );
        } catch (Throwable $e) {
            if ($fehlermeldung === null) {
                $fehlermeldung = 'Die Krankzeiten konnten nicht geladen werden.';
            }
            $ladefehler = true;
            Logger::error('Fehler beim Laden krankzeitraum (Admin)', [
                'exception' => $e->getMessage(),
            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'krank');
        }

        $mitarbeiterListe = $this->holeMitarbeiterListe((int)($form['mitarbeiter_id'] ?? 0));

        require __DIR__ . '/../views/konfiguration/krankzeitraum.php';
    }



    /**
     * Admin-UI: Pausenregeln verwalten.
     *
     * Umsetzung (T-072, Teil 1):
     * - Betriebliche Pausenfenster (Uhrzeitfenster) pflegbar.
     * - Gesetzliche Mindestpause (Schwellen/Minuten) als Konfigurationswerte pflegbar.
     *
     * Hinweis:
     * - Die eigentliche Berechnung/Abzüge passieren später in Zeit-/Report-Logik.
     */
    private function indexPausenregeln(): void
    {
        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

        $csrfBereich = self::CSRF_BEREICH;
        $fehlermeldung = null;

        $editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $editId = max(0, $editId);

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');

        // Default-Form (Pausenfenster)
        $form = [
            'id' => 0,
            'von_uhrzeit' => '',
            'bis_uhrzeit' => '',
            'sort_order' => 10,
            'kommentar' => '',
            'aktiv' => 1,
        ];

        // Gesetzliche Defaults (ArbZG §4, konfigurierbar)
        $cfgSchwelle1 = (int)($this->konfigurationService->getInt('pause_gesetz_schwelle1_stunden', 6) ?? 6);
        $cfgMinuten1  = (int)($this->konfigurationService->getInt('pause_gesetz_minuten1', 30) ?? 30);
        $cfgSchwelle2 = (int)($this->konfigurationService->getInt('pause_gesetz_schwelle2_stunden', 9) ?? 9);
        $cfgMinuten2  = (int)($this->konfigurationService->getInt('pause_gesetz_minuten2', 45) ?? 45);

        // POST-Aktionen
        if ($istPost) {
            if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            } else {
                $aktion = isset($_POST['pause_action']) ? trim((string)$_POST['pause_action']) : '';

                if ($aktion === 'toggle') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $aktiv = isset($_POST['aktiv']) ? (int)$_POST['aktiv'] : 0;
                    $id = max(0, $id);
                    $aktiv = $aktiv === 1 ? 1 : 0;

                    if ($id <= 0) {
                        $fehlermeldung = 'Ungültige ID.';
                    } else {
                        try {
                            $this->datenbank->ausfuehren(
                                'UPDATE pausenfenster SET aktiv = :a WHERE id = :id',
                                ['a' => $aktiv, 'id' => $id]
                            );
                            header('Location: ?seite=konfiguration_admin&tab=pausen&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Speichern fehlgeschlagen.';
                            Logger::error('Fehler beim Toggle pausenfenster', [
                                'id' => $id,
                                'aktiv' => $aktiv,
                                'exception' => $e->getMessage(),
                            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'pause');
                        }
                    }
                }

                if ($aktion === 'speichern') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $id = max(0, $id);

                    $von = isset($_POST['von_uhrzeit']) ? trim((string)$_POST['von_uhrzeit']) : '';
                    $bis = isset($_POST['bis_uhrzeit']) ? trim((string)$_POST['bis_uhrzeit']) : '';

                    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 10;
                    $sortOrder = max(0, $sortOrder);

                    $kommentar = isset($_POST['kommentar']) ? trim((string)$_POST['kommentar']) : '';
                    if ($kommentar === '') {
                        $kommentar = null;
                    }

                    $aktiv = isset($_POST['aktiv']) ? 1 : 0;

                    $form = [
                        'id' => $id,
                        'von_uhrzeit' => $von,
                        'bis_uhrzeit' => $bis,
                        'sort_order' => $sortOrder,
                        'kommentar' => $kommentar ?? '',
                        'aktiv' => $aktiv,
                    ];

                    // Bereich mitprüfen, nicht nur die Form: `\d{2}:\d{2}` lässt
                    // `25:99` durch. Der Vergleich unten hielte das für eine
                    // späte Uhrzeit, und die Meldung zeigte auf das falsche Feld.
                    if (!self::istUhrzeit($von)) {
                        $fehlermeldung = 'Bitte eine gültige Von-Uhrzeit angeben (HH:MM).';
                    } elseif (!self::istUhrzeit($bis)) {
                        $fehlermeldung = 'Bitte eine gültige Bis-Uhrzeit angeben (HH:MM).';
                    } elseif ($bis <= $von) {
                        // Zeichenvergleich reicht, weil beide Werte hier
                        // zweistellig und geprüft sind.
                        $fehlermeldung = 'Bis-Uhrzeit muss nach der Von-Uhrzeit liegen.';
                    }

                    if ($fehlermeldung === null) {
                        $angemeldeterId = $this->authService->holeAngemeldeteMitarbeiterId();
                        $angemeldeterId = $angemeldeterId !== null ? (int)$angemeldeterId : null;

                        try {
                            if ($id > 0) {
                                $this->datenbank->ausfuehren(
                                    'UPDATE pausenfenster
                                     SET von_uhrzeit = :von, bis_uhrzeit = :bis, sort_order = :so, kommentar = :k, aktiv = :a
                                     WHERE id = :id',
                                    [
                                        'von' => $von,
                                        'bis' => $bis,
                                        'so' => $sortOrder,
                                        'k' => $kommentar,
                                        'a' => $aktiv,
                                        'id' => $id,
                                    ]
                                );
                            } else {
                                $this->datenbank->ausfuehren(
                                    'INSERT INTO pausenfenster (von_uhrzeit, bis_uhrzeit, sort_order, kommentar, aktiv)
                                     VALUES (:von, :bis, :so, :k, :a)',
                                    [
                                        'von' => $von,
                                        'bis' => $bis,
                                        'so' => $sortOrder,
                                        'k' => $kommentar,
                                        'a' => $aktiv,
                                    ]
                                );
                            }

                            header('Location: ?seite=konfiguration_admin&tab=pausen&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Speichern fehlgeschlagen.';
                            Logger::error('Fehler beim Speichern pausenfenster', [
                                'id' => $id,
                                'von' => $von,
                                'bis' => $bis,
                                'sort_order' => $sortOrder,
                                'aktiv' => $aktiv,
                                'exception' => $e->getMessage(),
                            ], $angemeldeterId, null, 'pause');
                        }
                    }
                }

                if ($aktion === 'save_rules') {
                    $s1 = isset($_POST['gesetz_schwelle1']) ? (int)$_POST['gesetz_schwelle1'] : $cfgSchwelle1;
                    $m1 = isset($_POST['gesetz_minuten1']) ? (int)$_POST['gesetz_minuten1'] : $cfgMinuten1;
                    $s2 = isset($_POST['gesetz_schwelle2']) ? (int)$_POST['gesetz_schwelle2'] : $cfgSchwelle2;
                    $m2 = isset($_POST['gesetz_minuten2']) ? (int)$_POST['gesetz_minuten2'] : $cfgMinuten2;

                    $s1 = max(1, $s1);
                    $m1 = max(0, $m1);
                    $s2 = max($s1, $s2);
                    $m2 = max($m1, $m2);

                    // Speichern als Config-Keys (int)
                    try {
                        $this->konfigurationService->set('pause_gesetz_schwelle1_stunden', (string)$s1, 'int', 'Pause: Gesetzliche Schwelle 1 (Stunden). Default 6.');
                        $this->konfigurationService->set('pause_gesetz_minuten1', (string)$m1, 'int', 'Pause: Gesetzliche Mindestpause 1 (Minuten). Default 30.');
                        $this->konfigurationService->set('pause_gesetz_schwelle2_stunden', (string)$s2, 'int', 'Pause: Gesetzliche Schwelle 2 (Stunden). Default 9.');
                        $this->konfigurationService->set('pause_gesetz_minuten2', (string)$m2, 'int', 'Pause: Gesetzliche Mindestpause 2 (Minuten). Default 45.');

                        header('Location: ?seite=konfiguration_admin&tab=pausen&ok=1');
                        return;
                    } catch (Throwable $e) {
                        $fehlermeldung = 'Speichern fehlgeschlagen.';
                        Logger::error('Fehler beim Speichern der gesetzlichen Pausenregeln', [
                            'exception' => $e->getMessage(),
                        ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'config');
                    }
                }
            }
        }

        // Laden für Bearbeiten (Pausenfenster)
        if ($editId > 0 && !$istPost) {
            try {
                $row = $this->datenbank->fetchEine('SELECT * FROM pausenfenster WHERE id = :id', ['id' => $editId]);
                if ($row !== null) {
                    $form = [
                        'id' => (int)($row['id'] ?? 0),
                        'von_uhrzeit' => (string)($row['von_uhrzeit'] ?? ''),
                        'bis_uhrzeit' => (string)($row['bis_uhrzeit'] ?? ''),
                        'sort_order' => (int)($row['sort_order'] ?? 10),
                        'kommentar' => (string)($row['kommentar'] ?? ''),
                        'aktiv' => (int)($row['aktiv'] ?? 1),
                    ];
                } else {
                    $fehlermeldung = 'Eintrag nicht gefunden.';
                    $editId = 0;
                }
            } catch (Throwable) {
                $fehlermeldung = 'Eintrag konnte nicht geladen werden.';
                $editId = 0;
            }
        }

        // Liste laden
        $fenster = [];
        $ladefehler = false;
        try {
            $fenster = $this->datenbank->fetchAlle(
                'SELECT * FROM pausenfenster ORDER BY aktiv DESC, sort_order ASC, von_uhrzeit ASC, id ASC'
            );
        } catch (Throwable $e) {
            if ($fehlermeldung === null) {
                $fehlermeldung = 'Die Pausenfenster konnten nicht geladen werden.';
            }
            $ladefehler = true;
            Logger::error('Fehler beim Laden pausenfenster (Admin)', [
                'exception' => $e->getMessage(),
            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'pause');
        }

        // Gesetzliche Werte erneut laden (falls im POST oben geändert wurde)
        $cfgSchwelle1 = (int)($this->konfigurationService->getInt('pause_gesetz_schwelle1_stunden', 6) ?? 6);
        $cfgMinuten1  = (int)($this->konfigurationService->getInt('pause_gesetz_minuten1', 30) ?? 30);
        $cfgSchwelle2 = (int)($this->konfigurationService->getInt('pause_gesetz_schwelle2_stunden', 9) ?? 9);
        $cfgMinuten2  = (int)($this->konfigurationService->getInt('pause_gesetz_minuten2', 45) ?? 45);

        require __DIR__ . '/../views/konfiguration/pausenregeln.php';
    }




    /**
     * Admin-UI: Sonstiges-Gründe (T-075, Teil 2a)
     *
     * Zweck:
     * - Verwaltung der konfigurierbaren Gründe für Tageskennzeichen "Sonstiges"
     *   (Code/Titel/Default-Stunden/Begründungspflicht/Sort/Aktiv).
     *
     * Hinweis:
     * - Die Auswahl/Übernahme in der Tagesansicht folgt im nächsten Teil (T-075, Teil 2b).
     */
    private function indexSonstigesGruende(): void
    {
        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

        $csrfBereich = self::CSRF_BEREICH;
        $fehlermeldung = null;

        $editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $editId = max(0, $editId);

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');

        // Default-Form
        $form = [
            'id' => 0,
            'code' => '',
            'titel' => '',
            'default_stunden' => '0.00',
            'begruendung_pflicht' => 0,
            'sort_order' => 10,
            'kommentar' => '',
            'aktiv' => 1,
        ];

        // POST-Aktionen
        if ($istPost) {
            if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            } else {
                $aktion = isset($_POST['sonstiges_action']) ? trim((string)$_POST['sonstiges_action']) : '';

                if ($aktion === 'toggle') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $aktiv = isset($_POST['aktiv']) ? (int)$_POST['aktiv'] : 0;
                    $id = max(0, $id);
                    $aktiv = $aktiv === 1 ? 1 : 0;

                    if ($id <= 0) {
                        $fehlermeldung = 'Ungültige ID.';
                    } else {
                        try {
                            $this->datenbank->ausfuehren(
                                'UPDATE sonstiges_grund SET aktiv = :a WHERE id = :id',
                                ['a' => $aktiv, 'id' => $id]
                            );
                            header('Location: ?seite=konfiguration_admin&tab=sonstiges&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Speichern fehlgeschlagen.';
                            Logger::error('Fehler beim Toggle sonstiges_grund', [
                                'id' => $id,
                                'aktiv' => $aktiv,
                                'exception' => $e->getMessage(),
                            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'sonstiges');
                        }
                    }
                }

                if ($aktion === 'speichern') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $id = max(0, $id);

                    $code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
                    $code = preg_replace('/\s+/', '', (string)$code);
                    $code = (string)$code;

                    $titel = isset($_POST['titel']) ? trim((string)$_POST['titel']) : '';

                    $dsRaw = isset($_POST['default_stunden']) ? trim((string)$_POST['default_stunden']) : '';
                    $dsRaw = str_replace(',', '.', $dsRaw);
                    if ($dsRaw === '') {
                        $dsRaw = '0';
                    }

                    $begruendungPflicht = isset($_POST['begruendung_pflicht']) ? 1 : 0;

                    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 10;
                    $sortOrder = max(0, $sortOrder);

                    $kommentar = isset($_POST['kommentar']) ? trim((string)$_POST['kommentar']) : '';
                    if ($kommentar === '') {
                        $kommentar = null;
                    }

                    $aktiv = isset($_POST['aktiv']) ? 1 : 0;

                    // Normalisieren/Formatieren Default-Stunden (Punkt als Dezimaltrenner)
                    $defaultStunden = null;
                    if (is_numeric($dsRaw)) {
                        $defaultStunden = (float)$dsRaw;
                        if ($defaultStunden < 0) {
                            $defaultStunden = 0.0;
                        }
                        if ($defaultStunden > 24) {
                            $defaultStunden = 24.0;
                        }
                        $dsDb = number_format($defaultStunden, 2, '.', '');
                    } else {
                        $dsDb = '0.00';
                    }

                    $form = [
                        'id' => $id,
                        'code' => $code,
                        'titel' => $titel,
                        'default_stunden' => $dsDb,
                        'begruendung_pflicht' => $begruendungPflicht,
                        'sort_order' => $sortOrder,
                        'kommentar' => $kommentar ?? '',
                        'aktiv' => $aktiv,
                    ];

                    // Validierung
                    if ($code === '' || strlen($code) > 10 || !preg_match('/^[A-Za-z0-9]{1,10}$/', $code)) {
                        $fehlermeldung = 'Bitte einen gültigen Code (1–10 Zeichen, A-Z/0-9) angeben.';
                    } elseif ($titel === '' || mb_strlen($titel) > 80) {
                        $fehlermeldung = 'Bitte einen gültigen Titel (1–80 Zeichen) angeben.';
                    } elseif (!is_numeric($dsRaw)) {
                        $fehlermeldung = 'Bitte gültige Default-Stunden angeben (z. B. 8 oder 8.00).';
                    }

                    if ($fehlermeldung === null) {
                        try {
                            if ($id > 0) {
                                $this->datenbank->ausfuehren(
                                    'UPDATE sonstiges_grund
                                     SET code = :c, titel = :t, default_stunden = :ds, begruendung_pflicht = :bp,
                                         aktiv = :a, sort_order = :so, kommentar = :k
                                     WHERE id = :id',
                                    [
                                        'c' => $code,
                                        't' => $titel,
                                        'ds' => $dsDb,
                                        'bp' => $begruendungPflicht,
                                        'a' => $aktiv,
                                        'so' => $sortOrder,
                                        'k' => $kommentar,
                                        'id' => $id,
                                    ]
                                );
                            } else {
                                $this->datenbank->ausfuehren(
                                    'INSERT INTO sonstiges_grund (code, titel, default_stunden, begruendung_pflicht, aktiv, sort_order, kommentar)
                                     VALUES (:c, :t, :ds, :bp, :a, :so, :k)',
                                    [
                                        'c' => $code,
                                        't' => $titel,
                                        'ds' => $dsDb,
                                        'bp' => $begruendungPflicht,
                                        'a' => $aktiv,
                                        'so' => $sortOrder,
                                        'k' => $kommentar,
                                    ]
                                );
                            }

                            header('Location: ?seite=konfiguration_admin&tab=sonstiges&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $msg = $e->getMessage();
                            if (is_string($msg) && (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uniq_sonstiges_grund_code') !== false)) {
                                $fehlermeldung = 'Code ist bereits vorhanden. Bitte einen anderen Code wählen.';
                            } else {
                                $fehlermeldung = 'Speichern fehlgeschlagen.';
                            }

                            Logger::error('Fehler beim Speichern sonstiges_grund', [
                                'id' => $id,
                                'code' => $code,
                                'titel' => $titel,
                                'default_stunden' => $dsDb,
                                'begruendung_pflicht' => $begruendungPflicht,
                                'aktiv' => $aktiv,
                                'exception' => $e->getMessage(),
                            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'sonstiges');
                        }
                    }
                }
            }
        }

        // Laden für Bearbeiten
        if ($editId > 0 && !$istPost) {
            try {
                $row = $this->datenbank->fetchEine('SELECT * FROM sonstiges_grund WHERE id = :id', ['id' => $editId]);
                if ($row !== null) {
                    $form = [
                        'id' => (int)($row['id'] ?? 0),
                        'code' => (string)($row['code'] ?? ''),
                        'titel' => (string)($row['titel'] ?? ''),
                        'default_stunden' => number_format((float)($row['default_stunden'] ?? 0), 2, '.', ''),
                        'begruendung_pflicht' => (int)($row['begruendung_pflicht'] ?? 0),
                        'sort_order' => (int)($row['sort_order'] ?? 10),
                        'kommentar' => (string)($row['kommentar'] ?? ''),
                        'aktiv' => (int)($row['aktiv'] ?? 1),
                    ];
                } else {
                    $fehlermeldung = 'Eintrag nicht gefunden.';
                    $editId = 0;
                }
            } catch (Throwable) {
                $fehlermeldung = 'Eintrag konnte nicht geladen werden.';
                $editId = 0;
            }
        }

        // Liste laden
        $eintraege = [];
        $ladefehler = false;
        try {
            $eintraege = $this->datenbank->fetchAlle(
                'SELECT * FROM sonstiges_grund ORDER BY aktiv DESC, sort_order ASC, titel ASC, id ASC'
            );
        } catch (Throwable $e) {
            if ($fehlermeldung === null) {
                $fehlermeldung = 'Die Sonstiges-Gründe konnten nicht geladen werden (Tabelle vorhanden?).';
            }
            $ladefehler = true;
            Logger::error('Fehler beim Laden sonstiges_grund (Admin)', [
                'exception' => $e->getMessage(),
            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'sonstiges');
        }

        require __DIR__ . '/../views/konfiguration/sonstiges_gruende.php';
    }



/**
     * Formular (Neu/Bearbeiten) – speichert bei POST.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');

        $schluesselGet = isset($_GET['schluessel']) ? trim((string)$_GET['schluessel']) : '';
        $schluesselPost = isset($_POST['schluessel']) ? trim((string)$_POST['schluessel']) : '';

        $schluessel = $istPost ? $schluesselPost : $schluesselGet;

        $datensatz = [
            'schluessel'    => $schluessel,
            'wert'          => '',
            'typ'           => '',
            'beschreibung'  => '',
            'erstellt_am'   => '',
            'geaendert_am'  => '',
        ];

        $fehlermeldung = null;

        // Laden (bei GET oder bei POST-Fehlern)
        if ($schluessel !== '' && !$istPost) {
            try {
                $row = $this->datenbank->fetchEine(
                    'SELECT schluessel, wert, typ, beschreibung, erstellt_am, geaendert_am
                     FROM config
                     WHERE schluessel = :k
                     LIMIT 1',
                    ['k' => $schluessel]
                );
                if ($row !== null) {
                    $datensatz = [
                        'schluessel'    => (string)($row['schluessel'] ?? $schluessel),
                        'wert'          => (string)($row['wert'] ?? ''),
                        'typ'           => (string)($row['typ'] ?? ''),
                        'beschreibung'  => (string)($row['beschreibung'] ?? ''),
                        'erstellt_am'   => (string)($row['erstellt_am'] ?? ''),
                        'geaendert_am'  => (string)($row['geaendert_am'] ?? ''),
                    ];
                }
            } catch (Throwable $e) {
                $fehlermeldung = 'Der Eintrag konnte nicht geladen werden.';
                Logger::error('Fehler beim Laden eines Config-Eintrags', [
                    'schluessel' => $schluessel,
                    'exception'  => $e->getMessage(),
                ], null, null, 'config');
            }
        }

        // Speichern
        if ($istPost) {
            if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            }

            $schluessel = trim((string)($_POST['schluessel'] ?? ''));
            $wert = (string)($_POST['wert'] ?? '');
            $typ = trim((string)($_POST['typ'] ?? ''));
            $beschreibung = trim((string)($_POST['beschreibung'] ?? ''));

            // Validierung
            if ($fehlermeldung === null) {
                if ($schluessel === '') {
                    $fehlermeldung = 'Bitte geben Sie einen Schlüssel an.';
                } elseif (preg_match('/\s/', $schluessel) === 1) {
                    $fehlermeldung = 'Der Schlüssel darf keine Leerzeichen enthalten.';
                } elseif (mb_strlen($schluessel) > 190) {
                    $fehlermeldung = 'Der Schlüssel ist zu lang (max. 190 Zeichen).';
                }
            }

            $datensatz = [
                'schluessel'    => $schluessel,
                'wert'          => $wert,
                'typ'           => $typ,
                'beschreibung'  => $beschreibung,
                'erstellt_am'   => $datensatz['erstellt_am'],
                'geaendert_am'  => $datensatz['geaendert_am'],
            ];

            if ($fehlermeldung === null) {
                try {
                    $this->konfigurationService->set(
                        $schluessel,
                        $wert !== '' ? $wert : null,
                        $typ !== '' ? $typ : null,
                        $beschreibung !== '' ? $beschreibung : null
                    );

                    header('Location: ?seite=konfiguration_admin&ok=1');
                    exit;
                } catch (Throwable $e) {
                    $fehlermeldung = 'Der Eintrag konnte nicht gespeichert werden.';
                    Logger::error('Fehler beim Speichern eines Config-Eintrags', [
                        'schluessel' => $schluessel,
                        'exception'  => $e->getMessage(),
                    ], null, null, 'config');
                }
            }
        }

        $istBearbeiten = ($schluesselGet !== '');
        $csrfBereich   = self::CSRF_BEREICH;

        require __DIR__ . '/../views/konfiguration/bearbeiten.php';
    }
}
