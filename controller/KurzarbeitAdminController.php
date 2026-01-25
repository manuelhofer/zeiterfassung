<?php
declare(strict_types=1);

/**
 * KurzarbeitAdminController
 *
 * Backend-UI für die Kurzarbeit-Planung (`kurzarbeit_plan`).
 *
 * Ziel (T-070, Teil 2a):
 * - Plan-CRUD im Backend (Zeitraum/Wochentage/Modus/Wert/Scope).
 * - Noch kein Tages-Override (folgt im nächsten Patch).
 */
class KurzarbeitAdminController
{
    private const CSRF_KEY = 'kurzarbeit_admin_csrf_token';
    private const FLASH_OK_KEY = 'kurzarbeit_admin_flash_ok';
    private const FLASH_ERR_KEY = 'kurzarbeit_admin_flash_err';

    private AuthService $authService;
    private Database $datenbank;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank   = Database::getInstanz();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Kurzarbeit-Planung verwalten darf.
     *
     * Primär (falls vorhanden): Recht `KURZARBEIT_VERWALTEN` oder `KONFIGURATION_VERWALTEN`.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        if (method_exists($this->authService, 'hatRecht')) {
            try {
                if (
                    $this->authService->hatRecht('KURZARBEIT_VERWALTEN')
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
        echo '<p>Sie haben keine Berechtigung, Kurzarbeit zu verwalten.</p>';
        return false;
    }

    /**
     * Holt oder erzeugt ein CSRF-Token für Kurzarbeit-Admin-POST-Formulare.
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
                $token = bin2hex((string)mt_rand());
            }
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return (string)$token;
    }

    /**
     * Prüft das CSRF-Token aus POST.
     */
    private function istCsrfTokenGueltigAusPost(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $tokenSession = $_SESSION[self::CSRF_KEY] ?? '';
        $tokenPost    = $_POST['csrf_token'] ?? '';

        if (!is_string($tokenSession) || $tokenSession === '') {
            return false;
        }
        if (!is_string($tokenPost) || $tokenPost === '') {
            return false;
        }

        return hash_equals($tokenSession, $tokenPost);
    }

    /**
     * Liest Flash-Meldungen aus der Session (und entfernt sie).
     *
     * @return array{ok:?string,err:?string}
     */
    private function holeFlash(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ok = null;
        $err = null;

        if (isset($_SESSION[self::FLASH_OK_KEY])) {
            $ok = (string)$_SESSION[self::FLASH_OK_KEY];
            unset($_SESSION[self::FLASH_OK_KEY]);
        }
        if (isset($_SESSION[self::FLASH_ERR_KEY])) {
            $err = (string)$_SESSION[self::FLASH_ERR_KEY];
            unset($_SESSION[self::FLASH_ERR_KEY]);
        }

        return ['ok' => $ok, 'err' => $err];
    }

    private function setFlashOk(string $msg): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::FLASH_OK_KEY] = $msg;
    }

    private function setFlashErr(string $msg): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::FLASH_ERR_KEY] = $msg;
    }

    private function formatWochentageMask(int $mask): string
    {
        $mask = max(0, min(127, $mask));
        if ($mask === 31) {
            return 'Mo-Fr';
        }
        if ($mask === 127) {
            return 'Mo-So';
        }
        if ($mask === 0) {
            return '-';
        }

        $tage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $bit = 1 << $i;
            if (($mask & $bit) !== 0) {
                $out[] = $tage[$i];
            }
        }
        return implode(',', $out);
    }

    private function parseWochentageMaskAusPost(): int
    {
        $arr = $_POST['wochentage'] ?? [];
        if (!is_array($arr)) {
            $arr = [];
        }

        $mask = 0;
        foreach ($arr as $n) {
            $nn = (int)$n;
            if ($nn < 1 || $nn > 7) {
                continue;
            }
            $mask |= 1 << ($nn - 1);
        }

        if ($mask <= 0) {
            $mask = 31; // Default Mo-Fr
        }

        return max(0, min(127, $mask));
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    private function holeMitarbeiterListe(): array
    {
        try {
            $rows = $this->datenbank->fetchAlle(
                "SELECT id, vorname, nachname FROM mitarbeiter WHERE aktiv = 1 ORDER BY nachname, vorname"
            );
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
            $out[] = ['id' => $id, 'name' => $name];
        }
        return $out;
    }

    /**
     * Übersicht aller Kurzarbeit-Pläne.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $flash = $this->holeFlash();
        $csrfToken = $this->holeOderErzeugeCsrfToken();

        $plaene = [];
        $fehlermeldung = null;

        try {
            $plaene = $this->datenbank->fetchAlle(
                "SELECT p.*, m.vorname AS m_vorname, m.nachname AS m_nachname
                 FROM kurzarbeit_plan p
                 LEFT JOIN mitarbeiter m ON m.id = p.mitarbeiter_id
                 ORDER BY p.aktiv DESC, p.von_datum DESC, p.id DESC"
            );
        } catch (Throwable $e) {
            $fehlermeldung = 'Die Kurzarbeit-Pläne konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Kurzarbeit-Pläne (Admin)', [
                    'exception' => $e->getMessage(),
                ], null, null, 'kurzarbeit');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Kurzarbeit (Planung)</h2>

            <p>
                <a href="?seite=kurzarbeit_admin_bearbeiten">Neuen Plan anlegen</a>
            </p>

            <?php if (!empty($flash['ok'])): ?>
                <div class="erfolgsmeldung"><?php echo htmlspecialchars((string)$flash['ok'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($flash['err'])): ?>
                <div class="fehlermeldung"><?php echo htmlspecialchars((string)$flash['err'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>

            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Scope</th>
                    <th>Mitarbeiter</th>
                    <th>Zeitraum</th>
                    <th>Wochentage</th>
                    <th>Modus</th>
                    <th>Wert</th>
                    <th>Kommentar</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($plaene === []): ?>
                    <tr><td colspan="10">Keine Einträge.</td></tr>
                <?php else: ?>
                    <?php foreach ($plaene as $p): ?>
                        <?php
                        $id = (int)($p['id'] ?? 0);
                        $scope = (string)($p['scope'] ?? '');
                        $aktiv = (int)($p['aktiv'] ?? 0) === 1;
                        $von = (string)($p['von_datum'] ?? '');
                        $bis = (string)($p['bis_datum'] ?? '');
                        $mask = (int)($p['wochentage_mask'] ?? 31);
                        $modus = (string)($p['modus'] ?? 'stunden');
                        $wert = (string)($p['wert'] ?? '0');
                        $kommentar = (string)($p['kommentar'] ?? '');
                        $mid = (int)($p['mitarbeiter_id'] ?? 0);
                        $mName = '';
                        if ($mid > 0) {
                            $vn = trim((string)($p['m_vorname'] ?? ''));
                            $nn = trim((string)($p['m_nachname'] ?? ''));
                            $mName = trim($nn . ', ' . $vn);
                        }
                        ?>
                        <tr>
                            <td><?php echo (int)$id; ?></td>
                            <td><?php echo htmlspecialchars($scope, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo $mName !== '' ? htmlspecialchars($mName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                            <td><?php echo htmlspecialchars($von . ' bis ' . $bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($this->formatWochentageMask($mask), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($modus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($kommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                            <td>
                                <a href="?seite=kurzarbeit_admin_bearbeiten&id=<?php echo (int)$id; ?>">Bearbeiten</a>
                                <form method="post" action="?seite=kurzarbeit_admin_toggle" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
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

            <p style="margin-top:0.75rem;color:#555;">
                Hinweis: Tages-Overrides (Kurzarbeit pro Tag in der Korrekturmaske) folgen im nächsten Patch.
            </p>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Formular: Anlegen/Bearbeiten.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $flash = $this->holeFlash();
        $csrfToken = $this->holeOderErzeugeCsrfToken();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $plan = [
            'id' => 0,
            'scope' => 'mitarbeiter',
            'mitarbeiter_id' => null,
            'von_datum' => '',
            'bis_datum' => '',
            'wochentage_mask' => 31,
            'modus' => 'stunden',
            'wert' => '0.00',
            'kommentar' => '',
            'aktiv' => 1,
        ];

        if ($id > 0) {
            try {
                $row = $this->datenbank->fetchEine('SELECT * FROM kurzarbeit_plan WHERE id = :id', ['id' => $id]);
                if (is_array($row)) {
                    $plan = array_merge($plan, $row);
                } else {
                    $this->setFlashErr('Eintrag nicht gefunden.');
                    header('Location: ?seite=kurzarbeit_admin');
                    return;
                }
            } catch (Throwable $e) {
                $this->setFlashErr('Eintrag konnte nicht geladen werden.');
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Laden kurzarbeit_plan (Admin bearbeiten)', [
                        'id' => $id,
                        'exception' => $e->getMessage(),
                    ], null, null, 'kurzarbeit');
                }
                header('Location: ?seite=kurzarbeit_admin');
                return;
            }
        }

        $mitarbeiterListe = $this->holeMitarbeiterListe();

        $mask = (int)($plan['wochentage_mask'] ?? 31);
        $mask = max(0, min(127, $mask));

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2><?php echo $id > 0 ? 'Kurzarbeit-Plan bearbeiten' : 'Kurzarbeit-Plan anlegen'; ?></h2>

            <?php if (!empty($flash['ok'])): ?>
                <div class="erfolgsmeldung"><?php echo htmlspecialchars((string)$flash['ok'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($flash['err'])): ?>
                <div class="fehlermeldung"><?php echo htmlspecialchars((string)$flash['err'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="?seite=kurzarbeit_admin_speichern">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo (int)($plan['id'] ?? 0); ?>">

                <p>
                    <label for="scope">Gültigkeit (Scope)</label><br>
                    <select id="scope" name="scope">
                        <option value="firma" <?php echo ((string)($plan['scope'] ?? '') === 'firma') ? 'selected' : ''; ?>>Firma (alle)</option>
                        <option value="mitarbeiter" <?php echo ((string)($plan['scope'] ?? '') === 'mitarbeiter') ? 'selected' : ''; ?>>Mitarbeiter</option>
                    </select>
                </p>

                <p id="mitarbeiter_row">
                    <label for="mitarbeiter_id">Mitarbeiter</label><br>
                    <select id="mitarbeiter_id" name="mitarbeiter_id">
                        <option value="0">-- bitte wählen --</option>
                        <?php foreach ($mitarbeiterListe as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)($plan['mitarbeiter_id'] ?? 0) === (int)$m['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="von_datum">Von</label><br>
                    <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars((string)($plan['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </p>

                <p>
                    <label for="bis_datum">Bis</label><br>
                    <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars((string)($plan['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </p>

                <fieldset style="border:1px solid #ddd;padding:0.5rem 0.75rem;max-width:32rem;">
                    <legend>Wochentage</legend>
                    <?php
                    $tage = [
                        1 => 'Mo',
                        2 => 'Di',
                        3 => 'Mi',
                        4 => 'Do',
                        5 => 'Fr',
                        6 => 'Sa',
                        7 => 'So',
                    ];
                    foreach ($tage as $n => $label):
                        $bit = 1 << ($n - 1);
                        $checked = (($mask & $bit) !== 0);
                        ?>
                        <label style="display:inline-block;margin-right:0.6rem;">
                            <input type="checkbox" name="wochentage[]" value="<?php echo (int)$n; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                    <div style="margin-top:0.35rem;color:#666;font-size:0.9em;">Default: Mo-Fr</div>
                </fieldset>

                <p>
                    <label for="modus">Modus</label><br>
                    <select id="modus" name="modus">
                        <option value="stunden" <?php echo ((string)($plan['modus'] ?? '') === 'stunden') ? 'selected' : ''; ?>>Stunden</option>
                        <option value="prozent" <?php echo ((string)($plan['modus'] ?? '') === 'prozent') ? 'selected' : ''; ?>>Prozent vom Tages-Soll</option>
                    </select>
                </p>

                <p>
                    <label for="wert">Wert</label><br>
                    <input type="number" step="0.01" min="0" id="wert" name="wert" value="<?php echo htmlspecialchars((string)($plan['wert'] ?? '0.00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </p>

                <p>
                    <label for="kommentar">Kommentar (optional)</label><br>
                    <input type="text" id="kommentar" name="kommentar" value="<?php echo htmlspecialchars((string)($plan['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" maxlength="255" style="width:100%;max-width:48rem;">
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($plan['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        Aktiv
                    </label>
                </p>

                <p>
                    <button type="submit">Speichern</button>
                    <a href="?seite=kurzarbeit_admin" style="margin-left:0.75rem;">Zurück zur Liste</a>
                </p>
            </form>

            <script>
                (function () {
                    var scope = document.getElementById('scope');
                    var row = document.getElementById('mitarbeiter_row');
                    function sync() {
                        var isFirma = scope && scope.value === 'firma';
                        if (row) {
                            row.style.display = isFirma ? 'none' : '';
                        }
                    }
                    if (scope) {
                        scope.addEventListener('change', sync);
                    }
                    sync();
                })();
            </script>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * POST: Speichert einen Kurzarbeit-Plan (Insert/Update).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->setFlashErr('CSRF-Check fehlgeschlagen. Bitte Seite neu laden.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);

        $scope = strtolower(trim((string)($_POST['scope'] ?? 'mitarbeiter')));
        if ($scope !== 'firma' && $scope !== 'mitarbeiter') {
            $scope = 'mitarbeiter';
        }

        $mitarbeiterId = (int)($_POST['mitarbeiter_id'] ?? 0);
        if ($scope === 'firma') {
            $mitarbeiterId = 0;
        }

        $von = trim((string)($_POST['von_datum'] ?? ''));
        $bis = trim((string)($_POST['bis_datum'] ?? ''));

        if ($von === '' || $bis === '') {
            $this->setFlashErr('Bitte Von/Bis-Datum angeben.');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
        if ($von > $bis) {
            $this->setFlashErr('Das Von-Datum darf nicht nach dem Bis-Datum liegen.');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
        if ($scope === 'mitarbeiter' && $mitarbeiterId <= 0) {
            $this->setFlashErr('Bitte einen Mitarbeiter auswählen (Scope = Mitarbeiter).');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }

        $mask = $this->parseWochentageMaskAusPost();

        $modus = strtolower(trim((string)($_POST['modus'] ?? 'stunden')));
        if ($modus !== 'stunden' && $modus !== 'prozent') {
            $modus = 'stunden';
        }

        $wertRaw = trim((string)($_POST['wert'] ?? '0'));
        $wert = (float)str_replace(',', '.', $wertRaw);
        if ($wert < 0) {
            $wert = 0.0;
        }

        // defensives Clamping für UI (Logik im Service clamped nochmal)
        if ($modus === 'prozent' && $wert > 100.0) {
            $wert = 100.0;
        }
        if ($modus === 'stunden' && $wert > 24.0) {
            $wert = 24.0;
        }

        $kommentar = trim((string)($_POST['kommentar'] ?? ''));
        if ($kommentar === '') {
            $kommentar = null;
        } elseif (strlen($kommentar) > 255) {
            $kommentar = substr($kommentar, 0, 255);
        }

        $aktiv = isset($_POST['aktiv']) ? 1 : 0;

        $angelegtVon = null;
        $am = $this->authService->holeAngemeldetenMitarbeiter();
        if (is_array($am)) {
            $angelegtVon = (int)($am['id'] ?? 0);
            if ($angelegtVon <= 0) {
                $angelegtVon = null;
            }
        }

        try {
            if ($id > 0) {
                $this->datenbank->ausfuehren(
                    "UPDATE kurzarbeit_plan
                     SET scope = :scope,
                         mitarbeiter_id = :mid,
                         von_datum = :von,
                         bis_datum = :bis,
                         wochentage_mask = :mask,
                         modus = :modus,
                         wert = :wert,
                         kommentar = :kommentar,
                         aktiv = :aktiv
                     WHERE id = :id",
                    [
                        'scope' => $scope,
                        'mid' => ($mitarbeiterId > 0 ? $mitarbeiterId : null),
                        'von' => $von,
                        'bis' => $bis,
                        'mask' => $mask,
                        'modus' => $modus,
                        'wert' => sprintf('%.2f', $wert),
                        'kommentar' => $kommentar,
                        'aktiv' => $aktiv,
                        'id' => $id,
                    ]
                );
            } else {
                $this->datenbank->ausfuehren(
                    "INSERT INTO kurzarbeit_plan
                        (scope, mitarbeiter_id, von_datum, bis_datum, wochentage_mask, modus, wert, kommentar, aktiv, angelegt_von_mitarbeiter_id)
                     VALUES
                        (:scope, :mid, :von, :bis, :mask, :modus, :wert, :kommentar, :aktiv, :angelegt_von)",
                    [
                        'scope' => $scope,
                        'mid' => ($mitarbeiterId > 0 ? $mitarbeiterId : null),
                        'von' => $von,
                        'bis' => $bis,
                        'mask' => $mask,
                        'modus' => $modus,
                        'wert' => sprintf('%.2f', $wert),
                        'kommentar' => $kommentar,
                        'aktiv' => $aktiv,
                        'angelegt_von' => $angelegtVon,
                    ]
                );
                $id = (int)$this->datenbank->letzteInsertId();
            }

            $this->setFlashOk('Gespeichert.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        } catch (Throwable $e) {
            $this->setFlashErr('Speichern fehlgeschlagen.');
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern kurzarbeit_plan (Admin)', [
                    'id' => $id,
                    'exception' => $e->getMessage(),
                ], $angelegtVon, null, 'kurzarbeit');
            }
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
    }

    /**
     * POST + CSRF: toggelt Aktiv-Flag.
     */
    public function toggleAktiv(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->setFlashErr('CSRF-Check fehlgeschlagen. Bitte Seite neu laden.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $aktiv = (int)($_POST['aktiv'] ?? 0) === 1 ? 1 : 0;

        if ($id <= 0) {
            $this->setFlashErr('Ungültige ID.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        try {
            $this->datenbank->ausfuehren(
                'UPDATE kurzarbeit_plan SET aktiv = :aktiv WHERE id = :id',
                ['aktiv' => $aktiv, 'id' => $id]
            );
            $this->setFlashOk('Aktualisiert.');
        } catch (Throwable $e) {
            $this->setFlashErr('Aktualisieren fehlgeschlagen.');
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Toggle kurzarbeit_plan (Admin)', [
                    'id' => $id,
                    'aktiv' => $aktiv,
                    'exception' => $e->getMessage(),
                ], null, null, 'kurzarbeit');
            }
        }

        header('Location: ?seite=kurzarbeit_admin');
    }
}
