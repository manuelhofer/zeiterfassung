<?php
declare(strict_types=1);

/**
 * KonfigurationController
 *
 * Backend-UI für die `config`-Tabelle (Key/Value).
 */
class KonfigurationController
{
    private const CSRF_KEY = 'konfiguration_admin_csrf_token';
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

        if (method_exists($this->authService, 'hatRecht')) {
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

        if (method_exists($this->authService, 'hatRecht')) {
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
     * Holt oder erzeugt ein CSRF-Token für Konfigurations-POST-Formulare.
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
            } catch (Throwable) {
                // Fallback (sollte nur in sehr eingeschränkten Umgebungen passieren)
                $token = bin2hex((string)mt_rand());
            }
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return (string)$token;
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

        try {
            $eintraege = $this->konfigurationService->getAlle();
        } catch (Throwable $e) {
            $fehlermeldung = 'Die Konfiguration konnte nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Konfiguration', [
                    'exception' => $e->getMessage(),
                ], null, null, 'config');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Konfiguration</h2>

            <p style="margin-top:0.25rem;">
                <a href="?seite=konfiguration_admin">Konfiguration</a>
                | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
                | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
                | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
                | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
            </p>

            <p>
                <a href="?seite=konfiguration_admin_bearbeiten">Neuen Eintrag anlegen</a>
            </p>

            <?php if ($ok === 1): ?>
                <div class="erfolgsmeldung">Gespeichert.</div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($eintraege) === 0): ?>
                <p>Es sind derzeit keine Konfigurationseinträge vorhanden.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Schlüssel</th>
                            <th>Wert</th>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                            <th>Geändert</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eintraege as $e): ?>
                            <?php
                                $schluessel    = (string)($e['schluessel'] ?? '');
                                $wert          = $e['wert'] ?? null;
                                $typ           = (string)($e['typ'] ?? '');
                                $beschreibung  = (string)($e['beschreibung'] ?? '');
                                $geaendertAm   = (string)($e['geaendert_am'] ?? '');

                                $wertText = $wert !== null ? (string)$wert : '';
                                $wertKurz = mb_strlen($wertText) > 80 ? (mb_substr($wertText, 0, 80) . '…') : $wertText;
                                $beschreibungKurz = mb_strlen($beschreibung) > 80 ? (mb_substr($beschreibung, 0, 80) . '…') : $beschreibung;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schluessel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><code><?php echo htmlspecialchars($wertKurz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code></td>
                                <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($beschreibungKurz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($geaendertAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td>
                                    <a href="?seite=konfiguration_admin_bearbeiten&amp;schluessel=<?php echo urlencode($schluessel); ?>">Bearbeiten</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p style="margin-top:0.75rem; color:#555;">
                Hinweis: Änderungen wirken sofort. Defaults werden automatisch über <code>DefaultsSeeder</code> angelegt, falls Einträge fehlen.
            </p>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Admin-UI: System-Log (Warnung/Fehler) anzeigen.
     */
    private function indexSystemlog(): void
    {
        $limit = 200;
        $limit = max(10, min(500, $limit));

        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
        $csrfToken = $this->holeOderErzeugeCsrfToken();
        $fehlermeldung = null;
        $eintraege = [];

        $istPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');
        if ($istPost) {
            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
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
                            if (class_exists('Logger')) {
                                Logger::error('Fehler beim Löschen eines System-Log-Eintrags', [
                                    'id' => $id,
                                    'exception' => $e->getMessage(),
                                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'system_log');
                            }
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
                        if (class_exists('Logger')) {
                            Logger::error('Fehler beim Leeren des System-Logs', [
                                'exception' => $e->getMessage(),
                            ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'system_log');
                        }
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden des System-Logs', [
                    'exception' => $e->getMessage(),
                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'system_log');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>System-Log</h2>

            <p style="margin-top:0.25rem;">
                <a href="?seite=konfiguration_admin">Konfiguration</a>
                | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
                | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
                | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
                | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
            </p>

            <p style="color:#555;max-width:60rem;">
                Angezeigt werden die letzten <?php echo (int)$limit; ?> Einträge (Warnung, Fehler).
            </p>

            <?php if ($ok === 1): ?>
                <div class="erfolgsmeldung">Aktion abgeschlossen.</div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=konfiguration_admin&amp;tab=systemlog" onsubmit="return confirm('System-Log wirklich vollständig leeren?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="log_action" value="leeren">
                <button type="submit" class="button-link danger">System-Log leeren</button>
            </form>

            <?php if (count($eintraege) === 0): ?>
                <p>Es sind derzeit keine Log-Einträge vorhanden.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Zeit</th>
                            <th>Level</th>
                            <th>Kategorie</th>
                            <th>Nachricht</th>
                            <th>Daten</th>
                            <th>Mitarbeiter</th>
                            <th>Terminal</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eintraege as $e): ?>
                            <?php
                                $id = (int)($e['id'] ?? 0);
                                $zeit = (string)($e['zeitstempel'] ?? '');
                                $level = strtolower((string)($e['loglevel'] ?? ''));
                                $kategorie = (string)($e['kategorie'] ?? '');
                                $nachricht = (string)($e['nachricht'] ?? '');
                                $daten = (string)($e['daten'] ?? '');
                                $mitarbeiterId = (int)($e['mitarbeiter_id'] ?? 0);
                                $terminalId = (int)($e['terminal_id'] ?? 0);
                                $mVorname = trim((string)($e['m_vorname'] ?? ''));
                                $mNachname = trim((string)($e['m_nachname'] ?? ''));
                                $mitarbeiterName = trim($mNachname . ', ' . $mVorname);
                                if ($mitarbeiterName === '' && $mitarbeiterId > 0) {
                                    $mitarbeiterName = 'Mitarbeiter #' . $mitarbeiterId;
                                }
                                $datenKurz = $daten !== '' && mb_strlen($daten) > 120 ? (mb_substr($daten, 0, 120) . '…') : $daten;
                                $datenVoll = $daten !== '' ? $daten : 'Keine Daten vorhanden.';
                                $detailId = 'systemlog_detail_' . $id;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($zeit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($level !== '' ? strtoupper($level) : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($kategorie !== '' ? $kategorie : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($nachricht, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($datenKurz !== ''): ?>
                                        <code><?php echo htmlspecialchars($datenKurz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($mitarbeiterName !== '' ? $mitarbeiterName : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $terminalId > 0 ? (int)$terminalId : '-'; ?></td>
                                <td>
                                    <button type="button" class="button-link" data-detail-toggle="<?php echo htmlspecialchars($detailId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Details</button>
                                    <form method="post" action="?seite=konfiguration_admin&amp;tab=systemlog" style="display:inline;" onsubmit="return confirm('Diesen Log-Eintrag wirklich löschen?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <input type="hidden" name="log_action" value="loeschen">
                                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                        <button type="submit" class="button-link danger">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="<?php echo htmlspecialchars($detailId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="display:none;">
                                <td colspan="8">
                                    <div style="padding:0.5rem 0;">
                                        <strong>Details:</strong>
                                        <pre style="white-space:pre-wrap; margin:0.35rem 0 0;"><?php echo htmlspecialchars($datenVoll, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <script>
                (function(){
                    var toggles = document.querySelectorAll('[data-detail-toggle]');
                    toggles.forEach(function(btn){
                        btn.addEventListener('click', function(){
                            var zielId = btn.getAttribute('data-detail-toggle');
                            if (!zielId) return;
                            var zeile = document.getElementById(zielId);
                            if (!zeile) return;
                            var sichtbar = zeile.style.display !== 'none';
                            zeile.style.display = sichtbar ? 'none' : 'table-row';
                        });
                    });
                })();
                </script>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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

        $csrfToken = $this->holeOderErzeugeCsrfToken();
        $fehlermeldung = null;

        $editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $editId = max(0, $editId);

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

        // POST-Aktionen
        if ($istPost) {
            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
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
                            $this->datenbank->ausfuehren(
                                'UPDATE krankzeitraum SET aktiv = :a WHERE id = :id',
                                ['a' => $aktiv, 'id' => $id]
                            );
                            header('Location: ?seite=konfiguration_admin&tab=krankzeitraum&ok=1');
                            return;
                        } catch (Throwable $e) {
                            $fehlermeldung = 'Speichern fehlgeschlagen.';
                            if (class_exists('Logger')) {
                                Logger::error('Fehler beim Toggle krankzeitraum', [
                                    'id' => $id,
                                    'aktiv' => $aktiv,
                                    'exception' => $e->getMessage(),
                                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'krank');
                            }
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

                    $von = isset($_POST['von_datum']) ? trim((string)$_POST['von_datum']) : '';
                    $bis = isset($_POST['bis_datum']) ? trim((string)$_POST['bis_datum']) : '';
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
                    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $von)) {
                        $fehlermeldung = 'Bitte ein gültiges Von-Datum angeben.';
                    } elseif ($bis !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$bis)) {
                        $fehlermeldung = 'Bitte ein gültiges Bis-Datum angeben (oder leer lassen).';
                    } elseif ($bis !== null && (string)$bis < $von) {
                        $fehlermeldung = 'Bis-Datum darf nicht vor Von-Datum liegen.';
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
                                if ($ovBis === '') {
                                    $ovBis = 'offen';
                                }
                                $fehlermeldung = 'Überschneidung mit Zeitraum #' . $ovId . ' (' . $ovVon . ' bis ' . $ovBis . ').';
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
                            if (class_exists('Logger')) {
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden krankzeitraum (Admin)', [
                    'exception' => $e->getMessage(),
                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'krank');
            }
        }

        $mitarbeiterListe = $this->holeMitarbeiterListe((int)($form['mitarbeiter_id'] ?? 0));

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Krankzeiten (LFZ/KK)</h2>

            <p style="margin-top:0.25rem;">
                <a href="?seite=konfiguration_admin">Konfiguration</a>
                | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
                | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
                | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
                | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
            </p>

            <p style="color:#555;max-width:60rem;">
                Hier werden Krankzeiträume gepflegt, damit später im Report/PDF automatisch in die Spalten
                <strong>Krank LF</strong> (Lohnfortzahlung) und <strong>Krank KK</strong> (Krankenkasse) verteilt werden kann.
                Wechsel LFZ → KK wird als zweiter Zeitraum gepflegt.
            </p>

            <p>
                <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Neu anlegen</a>
            </p>

            <?php if ($ok === 1): ?>
                <div class="erfolgsmeldung">Gespeichert.</div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="?seite=konfiguration_admin&amp;tab=krankzeitraum">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="krank_action" value="speichern">
                <input type="hidden" name="id" value="<?php echo (int)($form['id'] ?? 0); ?>">

                <p>
                    <label for="mitarbeiter_id">Mitarbeiter</label><br>
                    <select id="mitarbeiter_id" name="mitarbeiter_id" required>
                        <option value="0">-- bitte wählen --</option>
                        <?php foreach ($mitarbeiterListe as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)($form['mitarbeiter_id'] ?? 0) === (int)$m['id']) ? 'selected' : ''; ?>>
                                <?php
                                    $label = (string)$m['name'];
                                    if ((int)($m['aktiv'] ?? 1) !== 1) {
                                        $label .= ' (inaktiv)';
                                    }
                                    echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="typ">Typ</label><br>
                    <select id="typ" name="typ" required>
                        <option value="lfz" <?php echo ((string)($form['typ'] ?? 'lfz') === 'lfz') ? 'selected' : ''; ?>>Krank LF (Lohnfortzahlung)</option>
                        <option value="kk" <?php echo ((string)($form['typ'] ?? '') === 'kk') ? 'selected' : ''; ?>>Krank KK (Krankenkasse)</option>
                    </select>
                </p>

                <p>
                    <label for="von_datum">Von</label><br>
                    <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars((string)($form['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </p>

                <p>
                    <label for="bis_datum">Bis (optional)</label><br>
                    <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars((string)($form['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <br><small style="color:#666;">Leer lassen = laufender Zeitraum</small>
                </p>

                <?php
                    // Optionaler UX-Helper (T-071): Vorschlag fuer den Wechsel von Krank LF (Lohnfortzahlung) zu Krank KK nach 6 Wochen.
                    // Faustregel: 6 Wochen = 42 Kalendertage ab Start (inkl. Starttag).
                    // -> LFZ bis = Start + 41 Tage, KK ab = Start + 42 Tage.
                    $v6wVon = trim((string)($form['von_datum'] ?? ''));
                    $v6wBis = '';
                    $v6wKkVon = '';
                    if (((string)($form['typ'] ?? '')) === 'lfz' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v6wVon)) {
                        try {
                            $dt = new DateTimeImmutable($v6wVon, new DateTimeZone('Europe/Berlin'));
                            $v6wBis = $dt->modify('+41 days')->format('Y-m-d');
                            $v6wKkVon = $dt->modify('+42 days')->format('Y-m-d');
                        } catch (Throwable) {
                            // defensiv: keine Vorschlaege anzeigen
                        }
                    }
                ?>

                <div id="lfz6w_hinweis" style="margin:-0.5rem 0 1rem 0; padding:0.6rem 0.75rem; background:#f7f7f7; border:1px solid #ddd; border-radius:6px; max-width:48rem;">
                    <strong>Vorschlag „Wechsel nach 6 Wochen“</strong><br>
                    <span style="color:#555;">
                        Start am <span id="lfz6w_von"><?php echo htmlspecialchars($v6wVon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span> →
                        Krank LF bis <span id="lfz6w_bis"><?php echo htmlspecialchars($v6wBis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>,
                        Krank KK ab <span id="lfz6w_kk"><?php echo htmlspecialchars($v6wKkVon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>.
                    </span>
                    <div style="margin-top:0.45rem;">
                        <button type="button" id="lfz6w_apply">Bis automatisch setzen</button>
                        <small style="margin-left:0.5rem; color:#666;">(setzt das Feld „Bis“ auf LFZ-Ende)</small>
                    </div>
                </div>

                <script>
                (function(){
                    var elTyp = document.getElementById('typ');
                    var elVon = document.getElementById('von_datum');
                    var elBis = document.getElementById('bis_datum');
                    var box = document.getElementById('lfz6w_hinweis');
                    var spVon = document.getElementById('lfz6w_von');
                    var spBis = document.getElementById('lfz6w_bis');
                    var spKk = document.getElementById('lfz6w_kk');
                    var btn = document.getElementById('lfz6w_apply');

                    function isValidDateStr(s){
                        return /^\d{4}-\d{2}-\d{2}$/.test(String(s || '').trim());
                    }

                    function addDays(dateStr, days){
                        if (!isValidDateStr(dateStr)) return null;
                        var p = String(dateStr).split('-');
                        var y = parseInt(p[0], 10);
                        var m = parseInt(p[1], 10) - 1;
                        var d = parseInt(p[2], 10);
                        if (!isFinite(y) || !isFinite(m) || !isFinite(d)) return null;
                        var dt = new Date(Date.UTC(y, m, d));
                        dt.setUTCDate(dt.getUTCDate() + days);
                        return dt.toISOString().slice(0, 10);
                    }

                    function update(){
                        if (!box || !elTyp || !elVon) return;
                        var typ = String(elTyp.value || '').toLowerCase();
                        var von = String(elVon.value || '').trim();

                        if (typ !== 'lfz' || !isValidDateStr(von)) {
                            box.style.display = 'none';
                            return;
                        }

                        var bis = addDays(von, 41);
                        var kk = addDays(von, 42);
                        if (!bis || !kk) {
                            box.style.display = 'none';
                            return;
                        }

                        box.style.display = 'block';
                        if (spVon) spVon.textContent = von;
                        if (spBis) spBis.textContent = bis;
                        if (spKk) spKk.textContent = kk;
                        if (btn && btn.dataset) btn.dataset.lfzBis = bis;
                    }

                    if (btn) {
                        btn.addEventListener('click', function(){
                            var v = (btn.dataset && btn.dataset.lfzBis) ? String(btn.dataset.lfzBis) : '';
                            if (elBis && isValidDateStr(v)) {
                                elBis.value = v;
                            }
                        });
                    }

                    if (elTyp) elTyp.addEventListener('change', update);
                    if (elVon) elVon.addEventListener('change', update);

                    update();
                })();
                </script>

                <p>
                    <label for="kommentar">Kommentar (optional)</label><br>
                    <input type="text" id="kommentar" name="kommentar" value="<?php echo htmlspecialchars((string)($form['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" maxlength="255" style="width:100%;max-width:48rem;">
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($form['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        Aktiv
                    </label>
                </p>

                <p>
                    <button type="submit">Speichern</button>
                    <?php if ((int)($form['id'] ?? 0) > 0): ?>
                        <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum" style="margin-left:0.75rem;">Abbrechen</a>
                    <?php endif; ?>
                </p>
            </form>

            <h3 style="margin-top:1.25rem;">Übersicht</h3>

            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Mitarbeiter</th>
                    <th>Typ</th>
                    <th>Zeitraum</th>
                    <th>Kommentar</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($eintraege === []): ?>
                    <tr><td colspan="7">Keine Einträge.</td></tr>
                <?php else: ?>
                    <?php foreach ($eintraege as $k): ?>
                        <?php
                        $id = (int)($k['id'] ?? 0);
                        $mid = (int)($k['mitarbeiter_id'] ?? 0);
                        $vn = trim((string)($k['m_vorname'] ?? ''));
                        $nn = trim((string)($k['m_nachname'] ?? ''));
                        $mName = trim($nn . ', ' . $vn);
                        if ($mName === '') {
                            $mName = 'Mitarbeiter #' . $mid;
                        }
                        $typ = (string)($k['typ'] ?? '');
                        $typText = $typ === 'kk' ? 'Krank KK' : 'Krank LF';
                        $von = (string)($k['von_datum'] ?? '');
                        $bis = (string)($k['bis_datum'] ?? '');
                        $zeitraum = $von . ' bis ' . ($bis !== '' ? $bis : 'offen');
                        $kommentar = (string)($k['kommentar'] ?? '');
                        $aktiv = (int)($k['aktiv'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td><?php echo (int)$id; ?></td>
                            <td><?php echo htmlspecialchars($mName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($typText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($zeitraum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($kommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                            <td>
                                <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                                <form method="post" action="?seite=konfiguration_admin&amp;tab=krankzeitraum" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <input type="hidden" name="krank_action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                    <input type="hidden" name="aktiv" value="<?php echo $aktiv ? '0' : '1'; ?>">
                                    <button type="submit"><?php echo $aktiv ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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

        $csrfToken = $this->holeOderErzeugeCsrfToken();
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
            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
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
                            if (class_exists('Logger')) {
                                Logger::error('Fehler beim Toggle pausenfenster', [
                                    'id' => $id,
                                    'aktiv' => $aktiv,
                                    'exception' => $e->getMessage(),
                                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'pause');
                            }
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

                    if (!preg_match('/^\d{2}:\d{2}$/', $von)) {
                        $fehlermeldung = 'Bitte eine gültige Von-Uhrzeit angeben (HH:MM).';
                    } elseif (!preg_match('/^\d{2}:\d{2}$/', $bis)) {
                        $fehlermeldung = 'Bitte eine gültige Bis-Uhrzeit angeben (HH:MM).';
                    } elseif ($bis <= $von) {
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
                            if (class_exists('Logger')) {
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
                    } catch (Throwable) {
                        $fehlermeldung = 'Speichern fehlgeschlagen.';
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
        try {
            $fenster = $this->datenbank->fetchAlle(
                'SELECT * FROM pausenfenster ORDER BY aktiv DESC, sort_order ASC, von_uhrzeit ASC, id ASC'
            );
        } catch (Throwable $e) {
            if ($fehlermeldung === null) {
                $fehlermeldung = 'Die Pausenfenster konnten nicht geladen werden.';
            }
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden pausenfenster (Admin)', [
                    'exception' => $e->getMessage(),
                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'pause');
            }
        }

        // Gesetzliche Werte erneut laden (falls im POST oben geändert wurde)
        $cfgSchwelle1 = (int)($this->konfigurationService->getInt('pause_gesetz_schwelle1_stunden', 6) ?? 6);
        $cfgMinuten1  = (int)($this->konfigurationService->getInt('pause_gesetz_minuten1', 30) ?? 30);
        $cfgSchwelle2 = (int)($this->konfigurationService->getInt('pause_gesetz_schwelle2_stunden', 9) ?? 9);
        $cfgMinuten2  = (int)($this->konfigurationService->getInt('pause_gesetz_minuten2', 45) ?? 45);

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Pausenregeln</h2>

            <p style="margin-top:0.25rem;">
                <a href="?seite=konfiguration_admin">Konfiguration</a>
                | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
                | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
                | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
                | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
            </p>

            <p style="color:#555;max-width:60rem;">
                Hier werden die betrieblichen Pausenfenster (Uhrzeitfenster) und die gesetzlichen Mindestpausenwerte gepflegt.
                Die Abzüge werden später pro Arbeitsblock berechnet (Mehrfach-Kommen/Gehen wird unterstützt).
            </p>

            <?php if ($ok === 1): ?>
                <div class="erfolgsmeldung">Gespeichert.</div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>

            <h3>Gesetzliche Mindestpause (konfigurierbar)</h3>
            <form method="post" action="?seite=konfiguration_admin&amp;tab=pausen">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="pause_action" value="save_rules">

                <p>
                    <label>Schwelle 1 (Stunden)</label><br>
                    <input type="number" name="gesetz_schwelle1" min="1" step="1" value="<?php echo (int)$cfgSchwelle1; ?>">
                </p>
                <p>
                    <label>Pause 1 (Minuten)</label><br>
                    <input type="number" name="gesetz_minuten1" min="0" step="1" value="<?php echo (int)$cfgMinuten1; ?>">
                </p>
                <p>
                    <label>Schwelle 2 (Stunden)</label><br>
                    <input type="number" name="gesetz_schwelle2" min="1" step="1" value="<?php echo (int)$cfgSchwelle2; ?>">
                </p>
                <p>
                    <label>Pause 2 (Minuten)</label><br>
                    <input type="number" name="gesetz_minuten2" min="0" step="1" value="<?php echo (int)$cfgMinuten2; ?>">
                </p>

                <p>
                    <button type="submit">Gesetzliche Werte speichern</button>
                </p>

                <p style="color:#555;max-width:60rem;">
                    Empfehlung/Default (Deutschland): &gt;<?php echo (int)$cfgSchwelle1; ?>h → <?php echo (int)$cfgMinuten1; ?>min, &gt;<?php echo (int)$cfgSchwelle2; ?>h → <?php echo (int)$cfgMinuten2; ?>min.
                    (Die Schwellen/Minuten sind hier bewusst konfigurierbar.)
                </p>
            </form>

            <hr>

            <h3>Betriebliche Pausenfenster</h3>

            <p>
                <a href="?seite=konfiguration_admin&amp;tab=pausen">Neu anlegen</a>
            </p>

            <form method="post" action="?seite=konfiguration_admin&amp;tab=pausen">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="pause_action" value="speichern">
                <input type="hidden" name="id" value="<?php echo (int)($form['id'] ?? 0); ?>">

                <p>
                    <label for="von_uhrzeit">Von</label><br>
                    <input id="von_uhrzeit" type="time" name="von_uhrzeit" value="<?php echo htmlspecialchars((string)($form['von_uhrzeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </p>
                <p>
                    <label for="bis_uhrzeit">Bis</label><br>
                    <input id="bis_uhrzeit" type="time" name="bis_uhrzeit" value="<?php echo htmlspecialchars((string)($form['bis_uhrzeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </p>
                <p>
                    <label for="sort_order">Sortierung</label><br>
                    <input id="sort_order" type="number" name="sort_order" min="0" step="1" value="<?php echo (int)($form['sort_order'] ?? 10); ?>">
                </p>
                <p>
                    <label for="kommentar">Kommentar</label><br>
                    <input id="kommentar" type="text" name="kommentar" maxlength="255" value="<?php echo htmlspecialchars((string)($form['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($form['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        aktiv
                    </label>
                </p>

                <p>
                    <button type="submit">Pausenfenster speichern</button>
                    <?php if ((int)($form['id'] ?? 0) > 0): ?>
                        <a style="margin-left:0.5rem;" href="?seite=konfiguration_admin&amp;tab=pausen">Abbrechen</a>
                    <?php endif; ?>
                </p>
            </form>

            <?php if (count($fenster) === 0): ?>
                <p>Keine Pausenfenster vorhanden.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Von</th>
                            <th>Bis</th>
                            <th>Sort</th>
                            <th>Kommentar</th>
                            <th>Aktiv</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fenster as $f): ?>
                            <?php
                                $id = (int)($f['id'] ?? 0);
                                $von = (string)($f['von_uhrzeit'] ?? '');
                                $bis = (string)($f['bis_uhrzeit'] ?? '');
                                $so  = (int)($f['sort_order'] ?? 0);
                                $kom = (string)($f['kommentar'] ?? '');
                                $akt = (int)($f['aktiv'] ?? 1);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo (int)$so; ?></td>
                                <td><?php echo htmlspecialchars($kom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $akt === 1 ? 'Ja' : 'Nein'; ?></td>
                                <td>
                                    <a href="?seite=konfiguration_admin&amp;tab=pausen&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                                    <form method="post" action="?seite=konfiguration_admin&amp;tab=pausen" style="display:inline; margin-left:0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <input type="hidden" name="pause_action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                        <input type="hidden" name="aktiv" value="<?php echo $akt === 1 ? 0 : 1; ?>">
                                        <button type="submit"><?php echo $akt === 1 ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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

        $csrfToken = $this->holeOderErzeugeCsrfToken();
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
            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
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
                            if (class_exists('Logger')) {
                                Logger::error('Fehler beim Toggle sonstiges_grund', [
                                    'id' => $id,
                                    'aktiv' => $aktiv,
                                    'exception' => $e->getMessage(),
                                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'sonstiges');
                            }
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

                            if (class_exists('Logger')) {
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
        try {
            $eintraege = $this->datenbank->fetchAlle(
                'SELECT * FROM sonstiges_grund ORDER BY aktiv DESC, sort_order ASC, titel ASC, id ASC'
            );
        } catch (Throwable $e) {
            if ($fehlermeldung === null) {
                $fehlermeldung = 'Die Sonstiges-Gründe konnten nicht geladen werden (Tabelle vorhanden?).';
            }
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden sonstiges_grund (Admin)', [
                    'exception' => $e->getMessage(),
                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'sonstiges');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Sonstiges-Gründe</h2>

            <p style="margin-top:0.25rem;">
                <a href="?seite=konfiguration_admin">Konfiguration</a>
                | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
                | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
                | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
                | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
            </p>

            <p style="color:#555;max-width:60rem;">
                Diese Liste definiert die auswählbaren Gründe für <strong>Sonstiges</strong> (z. B. Sonderurlaub).
                In der Tagesansicht kann später ein Grund gewählt werden, der dann Default-Stunden und ggf. Begründungspflicht vorgibt.
            </p>

            <p>
                <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Neu anlegen</a>
            </p>

            <?php if ($ok === 1): ?>
                <div class="erfolgsmeldung">Gespeichert.</div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="?seite=konfiguration_admin&amp;tab=sonstiges">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="sonstiges_action" value="speichern">
                <input type="hidden" name="id" value="<?php echo (int)($form['id'] ?? 0); ?>">

                <div style="display:grid;grid-template-columns: 1fr 2fr 1fr;gap:0.75rem;align-items:end;max-width:70rem;">
                    <label>
                        Code<br>
                        <input type="text" name="code" maxlength="10" required value="<?php echo htmlspecialchars((string)$form['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>

                    <label>
                        Titel<br>
                        <input type="text" name="titel" maxlength="80" required value="<?php echo htmlspecialchars((string)$form['titel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>

                    <label>
                        Default-Stunden<br>
                        <input type="text" name="default_stunden" inputmode="decimal" value="<?php echo htmlspecialchars((string)$form['default_stunden'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>

                    <label style="grid-column:1 / span 1;">
                        Sortierung<br>
                        <input type="number" name="sort_order" min="0" value="<?php echo (int)($form['sort_order'] ?? 10); ?>">
                    </label>

                    <label style="grid-column:2 / span 1;">
                        Kommentar (optional)<br>
                        <input type="text" name="kommentar" maxlength="255" value="<?php echo htmlspecialchars((string)$form['kommentar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>

                    <div style="grid-column:3 / span 1;">
                        <label style="display:block; margin-bottom:0.25rem;">
                            <input type="checkbox" name="begruendung_pflicht" value="1" <?php echo ((int)($form['begruendung_pflicht'] ?? 0) === 1) ? 'checked' : ''; ?>>
                            Begründung Pflicht
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($form['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                            Aktiv
                        </label>
                    </div>

                    <div style="grid-column: 1 / -1; margin-top:0.25rem;">
                        <button type="submit" style="padding:0.55rem 0.9rem;">Speichern</button>
                        <?php if ($editId > 0): ?>
                            <a style="margin-left:0.5rem;" href="?seite=konfiguration_admin&amp;tab=sonstiges">Abbrechen</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <hr style="margin:1rem 0;">

            <?php if (count($eintraege) === 0): ?>
                <p>Es sind derzeit keine Sonstiges-Gründe vorhanden.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Titel</th>
                            <th>Default</th>
                            <th>Begründung</th>
                            <th>Sort</th>
                            <th>Aktiv</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eintraege as $e): ?>
                            <?php
                                $id = (int)($e['id'] ?? 0);
                                $code = (string)($e['code'] ?? '');
                                $titel = (string)($e['titel'] ?? '');
                                $ds = number_format((float)($e['default_stunden'] ?? 0), 2, '.', '');
                                $bp = (int)($e['begruendung_pflicht'] ?? 0);
                                $so = (int)($e['sort_order'] ?? 10);
                                $akt = (int)($e['aktiv'] ?? 1);
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code></td>
                                <td><?php echo htmlspecialchars($titel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($ds, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $bp === 1 ? 'Ja' : 'Nein'; ?></td>
                                <td><?php echo (int)$so; ?></td>
                                <td><?php echo $akt === 1 ? 'Ja' : 'Nein'; ?></td>
                                <td>
                                    <a href="?seite=konfiguration_admin&amp;tab=sonstiges&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                                    <form method="post" action="?seite=konfiguration_admin&amp;tab=sonstiges" style="display:inline; margin-left:0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <input type="hidden" name="sonstiges_action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                        <input type="hidden" name="aktiv" value="<?php echo $akt === 1 ? 0 : 1; ?>">
                                        <button type="submit"><?php echo $akt === 1 ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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

        $csrfToken = $this->holeOderErzeugeCsrfToken();

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
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Laden eines Config-Eintrags', [
                        'schluessel' => $schluessel,
                        'exception'  => $e->getMessage(),
                    ], null, null, 'config');
                }
            }
        }

        // Speichern
        if ($istPost) {
            $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postToken)) {
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
                    if (class_exists('Logger')) {
                        Logger::error('Fehler beim Speichern eines Config-Eintrags', [
                            'schluessel' => $schluessel,
                            'exception'  => $e->getMessage(),
                        ], null, null, 'config');
                    }
                }
            }
        }

        $istBearbeiten = ($schluesselGet !== '');

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2><?php echo $istBearbeiten ? 'Konfiguration bearbeiten' : 'Konfiguration anlegen'; ?></h2>

            <p>
                <a href="?seite=konfiguration_admin">&laquo; Zurück zur Übersicht</a>
            </p>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=konfiguration_admin_bearbeiten<?php echo $istBearbeiten ? '&amp;schluessel=' . urlencode($schluesselGet) : ''; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <div style="display:flex; flex-direction:column; gap:0.6rem; max-width:900px;">
                    <label>
                        Schlüssel
                        <input
                            type="text"
                            name="schluessel"
                            value="<?php echo htmlspecialchars((string)$datensatz['schluessel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            <?php echo $istBearbeiten ? 'readonly' : ''; ?>
                            style="width:100%; padding:0.45rem;"
                            maxlength="190"
                            required
                        >
                        <?php if ($istBearbeiten): ?>
                            <small>Der Schlüssel ist bei bestehenden Einträgen gesperrt.</small>
                        <?php else: ?>
                            <small>Beispiel: <code>terminal_timeout_standard</code></small>
                        <?php endif; ?>
                    </label>

                    <label>
                        Typ (optional)
                        <input
                            type="text"
                            name="typ"
                            value="<?php echo htmlspecialchars((string)$datensatz['typ'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            style="width:100%; padding:0.45rem;"
                            maxlength="50"
                            placeholder="z.B. int / bool / string"
                        >
                    </label>

                    <label>
                        Wert
                        <textarea
                            name="wert"
                            rows="5"
                            style="width:100%; padding:0.45rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"
                        ><?php echo htmlspecialchars((string)$datensatz['wert'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                    </label>

                    <label>
                        Beschreibung (optional)
                        <textarea
                            name="beschreibung"
                            rows="3"
                            style="width:100%; padding:0.45rem;"
                        ><?php echo htmlspecialchars((string)$datensatz['beschreibung'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                    </label>

                    <?php if (!empty($datensatz['erstellt_am']) || !empty($datensatz['geaendert_am'])): ?>
                        <div style="color:#555; font-size:0.9rem;">
                            <?php if (!empty($datensatz['erstellt_am'])): ?>
                                Erstellt: <?php echo htmlspecialchars((string)$datensatz['erstellt_am'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            <?php endif; ?>
                            <?php if (!empty($datensatz['geaendert_am'])): ?>
                                &nbsp;|&nbsp; Geändert: <?php echo htmlspecialchars((string)$datensatz['geaendert_am'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <button type="submit" style="padding:0.55rem 0.9rem;">Speichern</button>
                    </div>
                </div>
            </form>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }
}
