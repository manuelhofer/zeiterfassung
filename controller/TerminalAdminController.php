<?php
declare(strict_types=1);

/**
 * TerminalAdminController
 *
 * Backend-Controller für das Verwalten von Terminals.
 */
class TerminalAdminController
{
    private const CSRF_KEY = 'terminal_admin_csrf_token';
    private const FLASH_OK_KEY = 'terminal_admin_flash_nachricht';
    private const FLASH_ERR_KEY = 'terminal_admin_flash_error';

    private AuthService $authService;
    private Database $datenbank;
    private TerminalModel $terminalModel;
    private AbteilungModel $abteilungModel;

    public function __construct()
    {
        $this->authService   = AuthService::getInstanz();
        $this->datenbank     = Database::getInstanz();
        $this->terminalModel = new TerminalModel();
        $this->abteilungModel = new AbteilungModel();
    }

    /**
     * Holt oder erzeugt ein CSRF-Token für Terminal-Admin-POST-Formulare.
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
     * Prüft, ob der aktuell angemeldete Benutzer die Terminalverwaltung nutzen darf.
     *
     * Primär (neues System): Recht `TERMINAL_VERWALTEN`.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Recht (neues System)
        if (method_exists($this->authService, 'hatRecht')) {
            try {
                if ($this->authService->hatRecht('TERMINAL_VERWALTEN')) {
                    return true;
                }
            } catch (\Throwable) {
                // Fallback unten
            }
        }

        // Legacy-Fallback: Chef/Personalbüro
        foreach (['Chef', 'Personalbüro', 'Personalbuero'] as $rollenName) {
            if ($this->authService->hatRolle($rollenName)) {
                return true;
            }
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Terminalverwaltung zu nutzen.</p>';

        return false;
    }

    /**
     * Übersicht aller Terminals.
     *
     * Hinweis: (noch) kein CRUD – zunächst nur eine saubere Liste im Backend-Layout.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        // Flash-Meldungen (nach POST-Aktionen wie Quick-Toggles)
        $flashOk = null;
        $flashErr = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION[self::FLASH_OK_KEY])) {
            $flashOk = (string)$_SESSION[self::FLASH_OK_KEY];
            unset($_SESSION[self::FLASH_OK_KEY]);
        }
        if (isset($_SESSION[self::FLASH_ERR_KEY])) {
            $flashErr = (string)$_SESSION[self::FLASH_ERR_KEY];
            unset($_SESSION[self::FLASH_ERR_KEY]);
        }

        $fehlermeldung = null;
        $terminals = [];

        // CSRF-Token wird in der Liste für Quick-Toggle-POSTs benötigt.
        $csrfToken = $this->holeOderErzeugeCsrfToken();

        try {
            $sql = 'SELECT t.*, a.name AS abteilung_name
                    FROM terminal t
                    LEFT JOIN abteilung a ON a.id = t.abteilung_id
                    ORDER BY t.aktiv DESC, t.name ASC';

            $terminals = $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Terminals konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Terminals im Admin-Bereich', [
                    'exception' => $e->getMessage(),
                ], null, null, 'terminal');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Terminalverwaltung</h2>

            <p>
                <a href="?seite=terminal_admin_bearbeiten">Neues Terminal anlegen</a>
            </p>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashOk)): ?>
                <div class="hinweis" style="margin: 0.5rem 0;">
                    <?php echo htmlspecialchars((string)$flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashErr)): ?>
                <div class="fehlermeldung" style="margin: 0.5rem 0;">
                    <?php echo htmlspecialchars((string)$flashErr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($terminals) === 0): ?>
                <p>Es sind derzeit keine Terminals hinterlegt.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Standort</th>
                            <th>Abteilung</th>
                            <th>Modus</th>
                            <th>Offline K/G</th>
                            <th>Offline Aufträge</th>
                            <th>Auto-Logout</th>
                            <th>Aktiv</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terminals as $t): ?>
                            <?php
                                $id = (int)($t['id'] ?? 0);
                                $name = (string)($t['name'] ?? '');
                                $standort = (string)($t['standort_beschreibung'] ?? '');
                                $abteilung = (string)($t['abteilung_name'] ?? '');
                                $modus = (string)($t['modus'] ?? '');
                                $okg = (int)($t['offline_erlaubt_kommen_gehen'] ?? 0) === 1;
                                $oauf = (int)($t['offline_erlaubt_auftraege'] ?? 0) === 1;
                                $timeout = (int)($t['auto_logout_timeout_sekunden'] ?? 0);
                                $aktiv = (int)($t['aktiv'] ?? 0) === 1;
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($standort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($abteilung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($modus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td>
                                    <?php echo $okg ? 'Ja' : 'Nein'; ?>
                                    <form method="post" action="?seite=terminal_admin_toggle" style="display:inline; margin-left:0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="feld" value="offline_erlaubt_kommen_gehen">
                                        <button type="submit" style="padding: 0.15rem 0.5rem;">Umschalten</button>
                                    </form>
                                </td>
                                <td>
                                    <?php echo $oauf ? 'Ja' : 'Nein'; ?>
                                    <form method="post" action="?seite=terminal_admin_toggle" style="display:inline; margin-left:0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="feld" value="offline_erlaubt_auftraege">
                                        <button type="submit" style="padding: 0.15rem 0.5rem;">Umschalten</button>
                                    </form>
                                </td>
                                <td><?php echo $timeout > 0 ? ($timeout . ' s') : '-'; ?></td>
                                <td>
                                    <?php echo $aktiv ? 'Ja' : 'Nein'; ?>
                                    <form method="post" action="?seite=terminal_admin_toggle" style="display:inline; margin-left:0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="feld" value="aktiv">
                                        <button type="submit" style="padding: 0.15rem 0.5rem;">Umschalten</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="?seite=terminal_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
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
     * Quick-Toggle aus der Listenansicht.
     *
     * POST + CSRF: toggelt genau ein erlaubtes Flag.
     * Erlaubte Felder: aktiv, offline_erlaubt_kommen_gehen, offline_erlaubt_auftraege
     */
    public function toggleFlag(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if ((string)($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo '<p>Ungültige Anfrage (nur POST erlaubt).</p>';
            return;
        }

        $csrfToken = $this->holeOderErzeugeCsrfToken();
        $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        if (!hash_equals($csrfToken, $postToken)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION[self::FLASH_ERR_KEY] = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            header('Location: ?seite=terminal_admin');
            return;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $feld = isset($_POST['feld']) ? (string)$_POST['feld'] : '';

        $erlaubt = [
            'aktiv' => 'aktiv',
            'offline_erlaubt_kommen_gehen' => 'offline_erlaubt_kommen_gehen',
            'offline_erlaubt_auftraege' => 'offline_erlaubt_auftraege',
        ];

        if ($id <= 0 || !isset($erlaubt[$feld])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION[self::FLASH_ERR_KEY] = 'Ungültige Anfrage (ID/Feld).';
            header('Location: ?seite=terminal_admin');
            return;
        }

        $spalte = $erlaubt[$feld];

        try {
            // Spaltenname ist aus Whitelist – darf als SQL-Identifikator eingebettet werden.
            $sql = 'UPDATE terminal
                    SET ' . $spalte . ' = CASE WHEN ' . $spalte . ' = 1 THEN 0 ELSE 1 END
                    WHERE id = :id';

            $betroffen = $this->datenbank->ausfuehren($sql, ['id' => $id]);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if ($betroffen > 0) {
                $_SESSION[self::FLASH_OK_KEY] = 'Änderung gespeichert.';
            } else {
                $_SESSION[self::FLASH_ERR_KEY] = 'Änderung konnte nicht gespeichert werden (Terminal nicht gefunden?).';
            }
        } catch (\Throwable $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION[self::FLASH_ERR_KEY] = 'Änderung fehlgeschlagen. Bitte Admin informieren.';

            if (class_exists('Logger')) {
                Logger::error('Fehler beim Quick-Toggle eines Terminal-Flags', [
                    'id'        => $id,
                    'feld'      => $feld,
                    'exception' => $e->getMessage(),
                ], $id, null, 'terminal');
            }
        }

        header('Location: ?seite=terminal_admin');
    }

    /**
     * Formular zum Anlegen/Bearbeiten eines Terminals.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $idRaw = $_GET['id'] ?? '';
        $id    = is_numeric($idRaw) ? (int)$idRaw : 0;

        $terminal = [
            'id'                          => $id,
            'name'                        => '',
            'standort_beschreibung'       => '',
            'abteilung_id'                => null,
            'modus'                       => 'terminal',
            'offline_erlaubt_kommen_gehen'=> 1,
            'offline_erlaubt_auftraege'   => 1,
            'auto_logout_timeout_sekunden'=> 60,
            'aktiv'                       => 1,
        ];

        $fehlermeldung = null;

        try {
            if ($id > 0) {
                $geladen = $this->terminalModel->holeNachId($id);
                if ($geladen === null) {
                    $fehlermeldung = 'Das ausgewählte Terminal wurde nicht gefunden.';
                } else {
                    $terminal = $geladen;
                }
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Das Terminal konnte nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Terminals im Admin-Bereich', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'terminal');
            }
        }

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
        }

        $csrfToken = $this->holeOderErzeugeCsrfToken();
        $this->renderFormular($terminal, $abteilungen, $fehlermeldung, $csrfToken);
    }

    /**
     * Speichert ein Terminal (Neu oder Bearbeiten).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $csrfToken = $this->holeOderErzeugeCsrfToken();
        $postToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        if (!hash_equals($csrfToken, $postToken)) {
            http_response_code(400);
            $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            $this->renderFormular($this->leseTerminalAusPost(), $this->abteilungModel->holeAlleAktiven(), $fehlermeldung, $csrfToken);
            return;
        }

        $terminal = $this->leseTerminalAusPost();
        $id       = (int)($terminal['id'] ?? 0);

        $fehlermeldung = $this->validiereTerminalDaten($terminal);

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
        }

        if ($fehlermeldung !== null) {
            $this->renderFormular($terminal, $abteilungen, $fehlermeldung, $csrfToken);
            return;
        }

        try {
            $parameter = [
                'name'                          => (string)$terminal['name'],
                'standort_beschreibung'         => $terminal['standort_beschreibung'] !== '' ? (string)$terminal['standort_beschreibung'] : null,
                'abteilung_id'                  => $terminal['abteilung_id'] !== null ? (int)$terminal['abteilung_id'] : null,
                'modus'                         => (string)$terminal['modus'],
                'offline_erlaubt_kommen_gehen'  => (int)$terminal['offline_erlaubt_kommen_gehen'],
                'offline_erlaubt_auftraege'     => (int)$terminal['offline_erlaubt_auftraege'],
                'auto_logout_timeout_sekunden'  => (int)$terminal['auto_logout_timeout_sekunden'],
                'aktiv'                         => (int)$terminal['aktiv'],
            ];

            if ($id > 0) {
                $sql = 'UPDATE terminal
                        SET name = :name,
                            standort_beschreibung = :standort_beschreibung,
                            abteilung_id = :abteilung_id,
                            modus = :modus,
                            offline_erlaubt_kommen_gehen = :offline_erlaubt_kommen_gehen,
                            offline_erlaubt_auftraege = :offline_erlaubt_auftraege,
                            auto_logout_timeout_sekunden = :auto_logout_timeout_sekunden,
                            aktiv = :aktiv
                        WHERE id = :id';
                $parameter['id'] = $id;
                $this->datenbank->ausfuehren($sql, $parameter);
            } else {
                $sql = 'INSERT INTO terminal
                        (name, standort_beschreibung, abteilung_id, modus,
                         offline_erlaubt_kommen_gehen, offline_erlaubt_auftraege,
                         auto_logout_timeout_sekunden, aktiv)
                        VALUES
                        (:name, :standort_beschreibung, :abteilung_id, :modus,
                         :offline_erlaubt_kommen_gehen, :offline_erlaubt_auftraege,
                         :auto_logout_timeout_sekunden, :aktiv)';
                $this->datenbank->ausfuehren($sql, $parameter);
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Das Terminal konnte nicht gespeichert werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern eines Terminals im Admin-Bereich', [
                    'id'        => $id,
                    'daten'     => $terminal,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'terminal');
            }
            $this->renderFormular($terminal, $abteilungen, $fehlermeldung, $csrfToken);
            return;
        }

        header('Location: ?seite=terminal_admin');
    }

    /**
     * Liest Terminal-Felder robust aus dem POST.
     *
     * @return array<string,mixed>
     */
    private function leseTerminalAusPost(): array
    {
        $idRaw        = $_POST['id'] ?? '';
        $id           = is_numeric($idRaw) ? (int)$idRaw : 0;
        $name         = trim((string)($_POST['name'] ?? ''));
        $standort     = trim((string)($_POST['standort_beschreibung'] ?? ''));
        $abteilungRaw = (string)($_POST['abteilung_id'] ?? '');
        $modus        = (string)($_POST['modus'] ?? 'terminal');
        $timeoutRaw   = (string)($_POST['auto_logout_timeout_sekunden'] ?? '60');

        $abteilungId = null;
        if ($abteilungRaw !== '') {
            $abteilungId = (int)$abteilungRaw;
            if ($abteilungId <= 0) {
                $abteilungId = null;
            }
        }

        $timeout = (int)$timeoutRaw;
        if ($timeout <= 0) {
            $timeout = 0;
        }

        $offlineKommenGehen = isset($_POST['offline_erlaubt_kommen_gehen']) ? 1 : 0;
        $offlineAuftraege   = isset($_POST['offline_erlaubt_auftraege']) ? 1 : 0;
        $aktiv              = isset($_POST['aktiv']) ? 1 : 0;

        return [
            'id'                           => $id,
            'name'                         => $name,
            'standort_beschreibung'        => $standort,
            'abteilung_id'                 => $abteilungId,
            'modus'                        => $modus,
            'offline_erlaubt_kommen_gehen' => $offlineKommenGehen,
            'offline_erlaubt_auftraege'    => $offlineAuftraege,
            'auto_logout_timeout_sekunden' => $timeout,
            'aktiv'                        => $aktiv,
        ];
    }

    /**
     * Validiert Terminaldaten und gibt eine Fehlermeldung zurück oder null.
     */
    private function validiereTerminalDaten(array $terminal): ?string
    {
        $name = trim((string)($terminal['name'] ?? ''));
        if ($name === '') {
            return 'Bitte geben Sie einen Namen für das Terminal ein.';
        }

        $modus = (string)($terminal['modus'] ?? 'terminal');
        if (!in_array($modus, ['terminal', 'backend'], true)) {
            return 'Ungültiger Modus. Bitte "terminal" oder "backend" wählen.';
        }

        $timeout = (int)($terminal['auto_logout_timeout_sekunden'] ?? 0);
        if ($timeout < 10 || $timeout > 86400) {
            return 'Auto-Logout Timeout muss zwischen 10 und 86400 Sekunden liegen.';
        }

        $abteilungId = $terminal['abteilung_id'] ?? null;
        if ($abteilungId !== null) {
            $abteilungId = (int)$abteilungId;
            if ($abteilungId <= 0) {
                return 'Ungültige Abteilung.';
            }
        }

        return null;
    }

    /**
     * Rendert das Terminal-Formular.
     *
     * @param array<string,mixed> $terminal
     * @param array<int,array<string,mixed>> $abteilungen
     */
    private function renderFormular(array $terminal, array $abteilungen, ?string $fehlermeldung, string $csrfToken): void
    {
        $id = (int)($terminal['id'] ?? 0);

        $name      = (string)($terminal['name'] ?? '');
        $standort  = (string)($terminal['standort_beschreibung'] ?? '');
        $abteilungId = $terminal['abteilung_id'] ?? null;
        $abteilungId = $abteilungId !== null ? (int)$abteilungId : null;

        $modus = (string)($terminal['modus'] ?? 'terminal');
        if (!in_array($modus, ['terminal', 'backend'], true)) {
            $modus = 'terminal';
        }

        $offlineKommenGehen = (int)($terminal['offline_erlaubt_kommen_gehen'] ?? 0) === 1;
        $offlineAuftraege   = (int)($terminal['offline_erlaubt_auftraege'] ?? 0) === 1;
        $timeout            = (int)($terminal['auto_logout_timeout_sekunden'] ?? 60);
        if ($timeout <= 0) {
            $timeout = 60;
        }
        $aktiv = (int)($terminal['aktiv'] ?? 0) === 1;

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2><?php echo $id > 0 ? 'Terminal bearbeiten' : 'Terminal anlegen'; ?></h2>

            <p><a href="?seite=terminal_admin">&laquo; Zurück zur Liste</a></p>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=terminal_admin_speichern">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo $id > 0 ? $id : 0; ?>">

                <div class="formularfeld">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </div>

                <div class="formularfeld">
                    <label for="standort_beschreibung">Standort (optional)</label>
                    <input type="text" id="standort_beschreibung" name="standort_beschreibung" value="<?php echo htmlspecialchars($standort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </div>

                <div class="formularfeld">
                    <label for="abteilung_id">Abteilung (optional)</label>
                    <select id="abteilung_id" name="abteilung_id">
                        <option value="">– keine –</option>
                        <?php foreach ($abteilungen as $a): ?>
                            <?php
                                $aid = (int)($a['id'] ?? 0);
                                $aname = (string)($a['name'] ?? '');
                                $selected = ($abteilungId !== null && $aid === $abteilungId) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $aid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($aname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formularfeld">
                    <label for="modus">Modus</label>
                    <select id="modus" name="modus">
                        <option value="terminal" <?php echo $modus === 'terminal' ? 'selected' : ''; ?>>terminal</option>
                        <option value="backend" <?php echo $modus === 'backend' ? 'selected' : ''; ?>>backend</option>
                    </select>
                </div>

                <div class="formularfeld">
                    <label for="auto_logout_timeout_sekunden">Auto-Logout Timeout (Sekunden)</label>
                    <input type="number" id="auto_logout_timeout_sekunden" name="auto_logout_timeout_sekunden" min="10" max="86400" value="<?php echo $timeout; ?>">
                </div>

                <fieldset>
                    <legend>Offline-Modus</legend>

                    <label>
                        <input type="checkbox" name="offline_erlaubt_kommen_gehen" value="1" <?php echo $offlineKommenGehen ? 'checked' : ''; ?>>
                        Kommen/Gehen offline erlauben
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="offline_erlaubt_auftraege" value="1" <?php echo $offlineAuftraege ? 'checked' : ''; ?>>
                        Aufträge offline erlauben
                    </label>
                </fieldset>

                <div class="formularfeld">
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                        Aktiv
                    </label>
                </div>

                <div class="button-row">
                    <button type="submit">Speichern</button>
                </div>
            </form>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }
}
