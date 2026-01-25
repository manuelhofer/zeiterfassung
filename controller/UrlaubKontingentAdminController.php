<?php
declare(strict_types=1);

/**
 * UrlaubKontingentAdminController
 *
 * Admin-UI für das Pflegen von Urlaubskontingenten pro Jahr (T-021 Teil 3).
 *
 * Zweck:
 * - Korrektur pro Mitarbeiter/Jahr pflegen.
 * - Optionaler Anspruch-Override (anstatt `mitarbeiter.urlaub_monatsanspruch * 12`).
 * - Übertrag wird ab v8 automatisch aus dem Resturlaub des Vorjahres berechnet (Master v8, 12.3). Das Feld `uebertrag_tage` ist Legacy und wird hier nicht mehr editiert.
 */
class UrlaubKontingentAdminController
{
    private const CSRF_KEY = 'urlaub_kontingent_admin_csrf_token';

    private AuthService $authService;
    private Database $datenbank;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank   = Database::getInstanz();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Kontingentverwaltung nutzen darf.
     *
     * Aktuell sind hierfür die Rollen "Chef" oder "Personalbüro" ausreichend.
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('URLAUB_KONTINGENT_VERWALTEN')) {
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
        echo '<p>Sie haben keine Berechtigung, das Urlaubskontingent zu verwalten.</p>';
        return false;
    }


    /**
     * Übersicht: Mitarbeiterliste + Werte für ein Jahr.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
        if (!$this->istValidesJahr($jahr)) {
            $jahr = (int)date('Y');
        }

        $flashOk = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_ok'])) {
            $flashOk = (string)$_SESSION['urlaub_kontingent_admin_flash_ok'];
            unset($_SESSION['urlaub_kontingent_admin_flash_ok']);
        }

        $flashFehler = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_error'])) {
            $flashFehler = (string)$_SESSION['urlaub_kontingent_admin_flash_error'];
            unset($_SESSION['urlaub_kontingent_admin_flash_error']);
        }

        $fehlermeldung = null;
        $zeilen = [];

        try {
            $sql = 'SELECT
                        m.id,
                        m.vorname,
                        m.nachname,
                        m.aktiv,
                        m.urlaub_monatsanspruch,
                        ukj.anspruch_override_tage,
                        ukj.korrektur_tage,
                        ukj.notiz
                    FROM mitarbeiter m
                    LEFT JOIN urlaub_kontingent_jahr ukj
                        ON ukj.mitarbeiter_id = m.id
                       AND ukj.jahr = :jahr
                    ORDER BY m.aktiv DESC, m.nachname ASC, m.vorname ASC, m.id ASC';

            $zeilen = $this->datenbank->fetchAlle($sql, ['jahr' => $jahr]);
        } catch (Throwable $e) {
            $fehlermeldung = 'Die Urlaubskontingente konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Urlaubskontingente (Admin)', [
                    'jahr'      => $jahr,
                    'exception' => $e->getMessage(),
                ], null, null, 'urlaub');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Urlaubskontingent pro Jahr</h2>

            <form method="get" style="margin-bottom: 1rem;">
                <input type="hidden" name="seite" value="urlaub_kontingent_admin">
                <label>
                    Jahr:
                    <input type="number" name="jahr" value="<?php echo (int)$jahr; ?>" min="2000" max="2100" style="width: 7rem;">
                </label>
                <button type="submit">Anzeigen</button>
            </form>

            <?php if (!empty($flashOk)): ?>
                <div style="background:#e8f5e9;border:1px solid #c8e6c9;padding:0.6rem 0.8rem;margin-bottom:1rem;">
                    <?php echo htmlspecialchars((string)$flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($zeilen) === 0): ?>
                <p>Es sind noch keine Mitarbeiter angelegt.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Aktiv</th>
                            <th>Anspruch (Standard)</th>
                            <th>Anspruch (Override)</th>
                            <th>Übertrag (auto)</th>
                            <th>Manuell (+/- Tage)</th>
                            <th>Notiz</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zeilen as $row): ?>
                            <?php
                                $id = (int)($row['id'] ?? 0);
                                $vorname  = trim((string)($row['vorname'] ?? ''));
                                $nachname = trim((string)($row['nachname'] ?? ''));
                                $aktiv = (int)($row['aktiv'] ?? 0) === 1;
                                $monats = (string)($row['urlaub_monatsanspruch'] ?? '0.00');
                                $standardAnspruch = $this->formatDecimal(((float)$monats) * 12.0);

                                $override = $row['anspruch_override_tage'];
                                $overrideText = $override === null ? '' : $this->formatDecimal((float)$override);

                                $korrektur = $row['korrektur_tage'] ?? 0;
                                $notiz = trim((string)($row['notiz'] ?? ''));
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo htmlspecialchars(trim($vorname . ' ' . $nachname), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                                <td><?php echo htmlspecialchars($standardAnspruch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($overrideText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><small>auto</small></td>
                                <td><?php echo htmlspecialchars($this->formatDecimal((float)$korrektur), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($notiz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td>
                                    <a href="?seite=urlaub_kontingent_admin_bearbeiten&amp;mitarbeiter_id=<?php echo $id; ?>&amp;jahr=<?php echo (int)$jahr; ?>">Bearbeiten</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 1rem;">
                    <small>
                        Hinweis: Anspruch(Standard) = <code>urlaub_monatsanspruch * 12</code>. Wenn ein Override gesetzt ist, wird er für den Urlaubssaldo verwendet. Übertrag wird automatisch aus dem Resturlaub des Vorjahres berechnet. <br><strong>Manuell (+/- Tage)</strong> ist eine direkte Korrektur (z. B. zusätzliche Urlaubstage gutschreiben oder Urlaubstage abziehen).
                    </small>
                </p>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Formular für einen Mitarbeiter/Jahr.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $mitarbeiterId = isset($_GET['mitarbeiter_id']) ? (int)$_GET['mitarbeiter_id'] : 0;
        $jahr          = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');

        if ($mitarbeiterId <= 0 || !$this->istValidesJahr($jahr)) {
            http_response_code(400);
            echo '<p>Ungültige Parameter.</p>';
            return;
        }

        $flashOk = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_ok'])) {
            $flashOk = (string)$_SESSION['urlaub_kontingent_admin_flash_ok'];
            unset($_SESSION['urlaub_kontingent_admin_flash_ok']);
        }

        $flashFehler = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_error'])) {
            $flashFehler = (string)$_SESSION['urlaub_kontingent_admin_flash_error'];
            unset($_SESSION['urlaub_kontingent_admin_flash_error']);
        }

        $fehlermeldung = null;
        if ($flashFehler !== null && $flashFehler !== '') {
            $fehlermeldung = $flashFehler;
        }
        $mitarbeiter = null;
        $kontingent = null;

        try {
            $mitarbeiter = $this->datenbank->fetchEine(
                'SELECT id, vorname, nachname, aktiv, urlaub_monatsanspruch FROM mitarbeiter WHERE id = :id LIMIT 1',
                ['id' => $mitarbeiterId]
            );
            if ($mitarbeiter === null) {
                $fehlermeldung = 'Mitarbeiter nicht gefunden.';
            }
        } catch (Throwable $e) {
            $fehlermeldung = 'Mitarbeiter konnte nicht geladen werden.';
        }

        if ($fehlermeldung === null) {
            try {
                $kontingent = $this->datenbank->fetchEine(
                    'SELECT * FROM urlaub_kontingent_jahr WHERE mitarbeiter_id = :mid AND jahr = :jahr LIMIT 1',
                    ['mid' => $mitarbeiterId, 'jahr' => $jahr]
                );
            } catch (Throwable $e) {
                $kontingent = null;
            }
        }

        $vorname  = $mitarbeiter !== null ? trim((string)($mitarbeiter['vorname'] ?? '')) : '';
        $nachname = $mitarbeiter !== null ? trim((string)($mitarbeiter['nachname'] ?? '')) : '';
        $aktiv    = $mitarbeiter !== null ? ((int)($mitarbeiter['aktiv'] ?? 0) === 1) : false;
        $monats   = $mitarbeiter !== null ? (float)($mitarbeiter['urlaub_monatsanspruch'] ?? 0.0) : 0.0;
        $standardAnspruch = $this->formatDecimal($monats * 12.0);

        $anspruchOverride = $kontingent['anspruch_override_tage'] ?? null;
        $korrektur = $kontingent['korrektur_tage'] ?? '0.00';
        $notiz     = $kontingent['notiz'] ?? '';

        $csrfToken = $this->holeOderErzeugeCsrfToken(self::CSRF_KEY);

        // Auto-Übertrag (Vorjahr -> Jahr) anzeigen, damit "Manuell" korrekt verstanden wird.
        $autoUebertragTage = null;
        $autoUebertragErmittelt = false;
        try {
            if (class_exists("UrlaubService")) {
                $saldoTmp = UrlaubService::getInstanz()->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr);
                if (is_array($saldoTmp) && array_key_exists("uebertrag", $saldoTmp)) {
                    $autoUebertragTage = (float)str_replace(",", ".", (string)$saldoTmp["uebertrag"]);
                    $autoUebertragErmittelt = true;
                }
            }
        } catch (Throwable $e) {
            $autoUebertragTage = null;
            $autoUebertragErmittelt = false;
        }
        if ($autoUebertragTage === null) {
            $autoUebertragTage = 0.0;
        }
        $autoUebertragText = $this->formatDecimal((float)$autoUebertragTage);
        $autoUebertragHinweis = $autoUebertragErmittelt ? "" : "Auto-Wert nicht ermittelbar";
        $beispielSollTage = 5.0;
        $beispielSollText = $this->formatDecimal($beispielSollTage);
        $beispielDeltaText = $this->formatDecimal($beispielSollTage - (float)$autoUebertragTage);

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Urlaubskontingent bearbeiten</h2>

            <p>
                <a href="?seite=urlaub_kontingent_admin&amp;jahr=<?php echo (int)$jahr; ?>">&laquo; Zurück zur Übersicht</a>
            </p>

            <?php if (!empty($flashOk)): ?>
                <div style="background:#e8f5e9;border:1px solid #c8e6c9;padding:0.6rem 0.8rem;margin-bottom:1rem;">
                    <?php echo htmlspecialchars((string)$flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($mitarbeiter !== null): ?>
                <p>
                    Mitarbeiter: <strong><?php echo htmlspecialchars(trim($vorname . ' ' . $nachname), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    (ID: <?php echo (int)$mitarbeiterId; ?>, <?php echo $aktiv ? 'aktiv' : 'inaktiv'; ?>)
                    <br>
                    Jahr: <strong><?php echo (int)$jahr; ?></strong>
                </p>

                <form method="post" action="?seite=urlaub_kontingent_admin_speichern">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="mitarbeiter_id" value="<?php echo (int)$mitarbeiterId; ?>">
                    <input type="hidden" name="jahr" value="<?php echo (int)$jahr; ?>">

                    <table style="max-width: 48rem;">
                        <tbody>
                            <tr>
                                <th style="width: 18rem;">Anspruch (Standard)</th>
                                <td>
                                    <?php echo htmlspecialchars($standardAnspruch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage
                                    <br>
                                    <small>Berechnung: <code>urlaub_monatsanspruch * 12</code></small>
                                </td>
                            </tr>
                            <tr>
                                <th>Anspruch (Override)</th>
                                <td>
                                    <input type="text" name="anspruch_override_tage" value="<?php echo $anspruchOverride === null ? '' : htmlspecialchars($this->formatDecimal((float)$anspruchOverride), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="leer = Standard" style="width: 10rem;">
                                    <small>Optional. Leer lassen, wenn Standard gelten soll.</small>
                                </td>
                            </tr>
                            <tr>
                                <th>Übertrag (auto)</th>
                                <td>
                                    <strong><?php echo htmlspecialchars($autoUebertragText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage</strong>
                                    <?php if ($autoUebertragHinweis !== ''): ?>
                                        <small style="color:#900;">(<?php echo htmlspecialchars($autoUebertragHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
                                    <?php endif; ?>
                                    <br>
                                    <small>
                                        Resturlaub aus dem Vorjahr (<?php echo (int)($jahr - 1); ?>) wird automatisch übernommen (Master v8, 12.3).<br>
                                        Wenn du den Übertrag abweichend festlegen willst: <code>Manuell = gewünschter Übertrag - Auto-Übertrag</code>.<br>
                                        Beispiel: Auto <?php echo htmlspecialchars($autoUebertragText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> → Soll <?php echo htmlspecialchars($beispielSollText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> ⇒ Manuell <?php echo htmlspecialchars($beispielDeltaText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>.
                                    </small>
                                </td>
                            </tr>

                            <tr>
                                <th>Manuell (+/- Tage)</th>
                                <td>
                                    <input type="text" name="korrektur_tage" value="<?php echo htmlspecialchars($this->formatDecimal((float)$korrektur), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 10rem;"> Tage
                                    <br>
                                    <small>Hier kannst du Urlaubstage manuell gut-/abbuchen (z. B. <code>+2.00</code> oder <code>-1.50</code>). Dieser Wert wird zusätzlich zum Auto-Übertrag addiert. Für Sonderfälle kannst du alternativ den Anspruch-Override setzen.</small>
                                </td>
                            </tr>
                            <tr>
                                <th>Notiz</th>
                                <td>
                                    <input type="text" name="notiz" value="<?php echo htmlspecialchars((string)$notiz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" maxlength="255" style="width: 100%;">
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p style="margin-top: 1rem;">
                        <button type="submit">Speichern</button>
                    </p>

                    <p>
                        <small>Hinweis: Die Werte wirken sich direkt auf den Urlaubssaldo in "Mein Urlaub" aus (Anspruch + Übertrag(auto) + Manuell - genehmigt - offen). Wenn du den Auto-Übertrag reduzieren willst, nutze Manuell mit negativem Wert (Formel oben).</small>
                    </p>
                </form>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Speichern (INSERT/UPDATE via ON DUPLICATE KEY).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '<p>Ungültiger Aufruf.</p>';
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost(self::CSRF_KEY)) {
            http_response_code(400);
            echo '<p>CSRF-Token ungültig. Bitte Seite neu laden.</p>';
            return;
        }

        $mitarbeiterId = isset($_POST['mitarbeiter_id']) ? (int)$_POST['mitarbeiter_id'] : 0;
        $jahr          = isset($_POST['jahr']) ? (int)$_POST['jahr'] : 0;

        $anspruchOverrideRaw = (string)($_POST['anspruch_override_tage'] ?? '');
        $korrekturRaw        = (string)($_POST['korrektur_tage'] ?? '0');
        $notizRaw            = trim((string)($_POST['notiz'] ?? ''));

        if ($mitarbeiterId <= 0 || !$this->istValidesJahr($jahr)) {
            $_SESSION['urlaub_kontingent_admin_flash_ok'] = '';
            header('Location: ?seite=urlaub_kontingent_admin&jahr=' . (int)date('Y'));
            return;
        }

        $fehlermeldung = null;

        $anspruchOverride = $this->parseDecimalNullable($anspruchOverrideRaw, $fehlermeldung);
        $korrektur        = $this->parseDecimalRequired($korrekturRaw, $fehlermeldung);
        $notiz            = $notizRaw !== '' ? $notizRaw : null;

        if ($fehlermeldung !== null) {
            $_SESSION['urlaub_kontingent_admin_flash_ok'] = '';
            // Fehler direkt im Formular zeigen
            $_GET['mitarbeiter_id'] = (string)$mitarbeiterId;
            $_GET['jahr'] = (string)$jahr;
            // "bearbeiten" nochmals rendern, aber mit Fehlerausgabe
            // (wir speichern die Fehlermeldung kurz in Session, um keinen 4. File für Flash zu brauchen)
            $_SESSION['urlaub_kontingent_admin_flash_error'] = $fehlermeldung;
            header('Location: ?seite=urlaub_kontingent_admin_bearbeiten&mitarbeiter_id=' . $mitarbeiterId . '&jahr=' . $jahr);
            return;
        }

        try {
            $sql = 'INSERT INTO urlaub_kontingent_jahr
                        (mitarbeiter_id, jahr, anspruch_override_tage, korrektur_tage, notiz)
                    VALUES
                        (:mid, :jahr, :aot, :kor, :notiz)
                    ON DUPLICATE KEY UPDATE
                        anspruch_override_tage = VALUES(anspruch_override_tage),
                        korrektur_tage = VALUES(korrektur_tage),
                        notiz = VALUES(notiz)';

            $this->datenbank->ausfuehren($sql, [
                'mid'  => $mitarbeiterId,
                'jahr' => $jahr,
                'aot'  => $anspruchOverride,
                'kor'  => $korrektur,
                'notiz'=> $notiz,
            ]);

            if (class_exists('Logger')) {
                Logger::info('Urlaubskontingent gespeichert', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                ], $mitarbeiterId, null, 'urlaub');
            }

            $_SESSION['urlaub_kontingent_admin_flash_ok'] = 'Gespeichert.';
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern des Urlaubskontingents', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaub');
            }
            $_SESSION['urlaub_kontingent_admin_flash_ok'] = 'Speichern fehlgeschlagen (DB-Fehler).';
        }

        header('Location: ?seite=urlaub_kontingent_admin_bearbeiten&mitarbeiter_id=' . $mitarbeiterId . '&jahr=' . $jahr);
        exit;
    }

    private function istValidesJahr(int $jahr): bool
    {
        return $jahr >= 2000 && $jahr <= 2100;
    }

    /**
     * Erzeugt oder holt CSRF-Token.
     */
    private function holeOderErzeugeCsrfToken(string $sessionKey): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_SESSION[$sessionKey] ?? '';
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[$sessionKey] = $token;
        }

        return $token;
    }

    private function istCsrfTokenGueltigAusPost(string $sessionKey): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $post = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $sess = $_SESSION[$sessionKey] ?? '';
        if (!is_string($sess) || $sess === '') {
            return false;
        }

        return hash_equals($sess, $post);
    }

    /**
     * Formatiert für Anzeige (immer 2 Nachkommastellen, Punkt).
     */
    private function formatDecimal(float $wert): string
    {
        return number_format($wert, 2, '.', '');
    }

    /**
     * Parst Dezimal (Pflichtfeld). Akzeptiert Komma oder Punkt.
     *
     * @return string|null
     */
    private function parseDecimalRequired(string $raw, ?string &$fehlermeldung): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '0.00';
        }

        $norm = str_replace(',', '.', $raw);
        if (!is_numeric($norm)) {
            $fehlermeldung = 'Bitte nur Zahlen für Korrektur eingeben (z. B. 2.5 oder 2,5).';
            return null;
        }

        return $this->formatDecimal((float)$norm);
    }

    /**
     * Parst Dezimal (nullable). Leer => NULL.
     *
     * @return string|null
     */
    private function parseDecimalNullable(string $raw, ?string &$fehlermeldung): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $norm = str_replace(',', '.', $raw);
        if (!is_numeric($norm)) {
            $fehlermeldung = 'Bitte nur Zahlen für Anspruch-Override eingeben (z. B. 30 oder 30,0) oder Feld leer lassen.';
            return null;
        }

        return $this->formatDecimal((float)$norm);
    }
}
