<?php
declare(strict_types=1);

/**
 * AuthService
 *
 * Kapselt die komplette Login-/Logout-Logik für Backend- und Terminal-Logins.
 *
 * Wichtige Punkte:
 * - Backend-Login erfolgt über Benutzername oder E-Mail plus Passwort.
 * - Terminal-Login erfolgt über RFID ohne Passwort.
 * - In der Session wird nur die `mitarbeiter_id` gespeichert.
 */
class AuthService
{
    private static ?AuthService $instanz = null;

    private MitarbeiterService $mitarbeiterService;

    private const SESSION_KEY_MITARBEITER_ID = 'auth_mitarbeiter_id';

    // Rechte-Cache in der Session (rollenbasierte Rechte)
    private const SESSION_KEY_RECHTE_CODES = 'auth_rechte_codes';
    private const SESSION_KEY_RECHTE_FOR_MITARBEITER = 'auth_rechte_mitarbeiter_id';

    // Superuser-Cache in Session (rolle.ist_superuser)
    private const SESSION_KEY_IST_SUPERUSER = 'auth_ist_superuser';
    private const SESSION_KEY_IST_SUPERUSER_FOR_MITARBEITER = 'auth_ist_superuser_mitarbeiter_id';

    private function __construct()
    {
        $this->mitarbeiterService = MitarbeiterService::getInstanz();
    }

    public static function getInstanz(): AuthService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Führt einen Login über Benutzername/E-Mail + Passwort durch.
     *
     * @param string $benutzername Benutzername oder E-Mail
     * @param string $passwort     Klartext-Passwort
     */
    public function loginMitBenutzername(string $benutzername, string $passwort): bool
    {
        $kennung = trim($benutzername);
        if ($kennung === '' || $passwort === '') {
            return false;
        }

        $mitarbeiter = $this->mitarbeiterService->holeLoginFaehigenMitarbeiterNachKennung($kennung);
        if ($mitarbeiter === null) {
            $this->logLoginFehler($kennung, 'Mitarbeiter nicht gefunden oder nicht login-berechtigt');
            return false;
        }

        $hash = (string)($mitarbeiter['passwort_hash'] ?? '');
        if ($hash === '' || !password_verify($passwort, $hash)) {
            $this->logLoginFehler($kennung, 'Passwort falsch');
            return false;
        }

        $this->setzeSessionFuerMitarbeiter((int)$mitarbeiter['id']);

        if (class_exists('Logger')) {
            Logger::info('Login erfolgreich (Benutzername/E-Mail)', [
                'kennung'       => $kennung,
                'mitarbeiter_id'=> (int)$mitarbeiter['id'],
            ], (int)$mitarbeiter['id'], null, 'auth');
        }

        return true;
    }

    /**
     * Führt einen Login über RFID-Code durch (z. B. am Terminal).
     *
     * @param string $rfidCode
     */
    public function loginMitRfid(string $rfidCode): bool
    {
        $code = trim($rfidCode);
        if ($code === '') {
            return false;
        }

        $mitarbeiter = $this->mitarbeiterService->holeAktivenMitarbeiterNachRfid($code);
        if ($mitarbeiter === null || (int)($mitarbeiter['ist_login_berechtigt'] ?? 0) !== 1) {
            $this->logLoginFehler('RFID:' . $code, 'Mitarbeiter nicht gefunden oder nicht login-berechtigt');
            return false;
        }

        $this->setzeSessionFuerMitarbeiter((int)$mitarbeiter['id']);

        if (class_exists('Logger')) {
            Logger::info('Login erfolgreich (RFID)', [
                'rfid'          => $code,
                'mitarbeiter_id'=> (int)$mitarbeiter['id'],
            ], (int)$mitarbeiter['id'], null, 'auth');
        }

        return true;
    }

    /**
     * Prüft, ob aktuell ein Mitarbeiter angemeldet ist.
     */
    public function istAngemeldet(): bool
    {
        return isset($_SESSION[self::SESSION_KEY_MITARBEITER_ID])
            && is_int($_SESSION[self::SESSION_KEY_MITARBEITER_ID])
            && $_SESSION[self::SESSION_KEY_MITARBEITER_ID] > 0;
    }

    /**
     * Gibt die ID des angemeldeten Mitarbeiters zurück oder null.
     */
    public function holeAngemeldeteMitarbeiterId(): ?int
    {
        if (!$this->istAngemeldet()) {
            return null;
        }

        return (int)$_SESSION[self::SESSION_KEY_MITARBEITER_ID];
    }

    /**
     * Holt die Stammdaten des aktuell angemeldeten Mitarbeiters.
     *
     * @return array<string,mixed>|null
     */
    public function holeAngemeldetenMitarbeiter(): ?array
    {
        $id = $this->holeAngemeldeteMitarbeiterId();
        if ($id === null) {
            return null;
        }

        $daten = $this->mitarbeiterService->holeMitarbeiterMitRollenUndGenehmigern($id);

        if (!is_array($daten)) {
            return null;
        }

        return $daten['mitarbeiter'] ?? null;
    }

    /**
     * Rollenprüfung.
     *
     * Prüft, ob der aktuell angemeldete Benutzer eine bestimmte Rolle hat.
     * Vergleich erfolgt case-insensitiv auf Basis des Rollennamens (`rolle.name`).
     */
    public function hatRolle(string $rollenName): bool
    {
        $rollenName = trim($rollenName);
        if ($rollenName === '') {
            return false;
        }

        if (!$this->istAngemeldet()) {
            return false;
        }

        $id = $this->holeAngemeldeteMitarbeiterId();
        if ($id === null) {
            return false;
        }

        $daten = $this->mitarbeiterService->holeMitarbeiterMitRollenUndGenehmigern($id);
        if (!is_array($daten)) {
            return false;
        }

        $rollen = $daten['rollen'] ?? [];
        if (!is_array($rollen) || count($rollen) === 0) {
            return false;
        }

        $norm = static function (string $wert): string {
            if (function_exists('mb_strtolower')) {
                return mb_strtolower($wert, 'UTF-8');
            }

            return strtolower($wert);
        };

        $gesuchterName = $norm($rollenName);

        foreach ($rollen as $rolle) {
            $name = isset($rolle['name']) ? (string)$rolle['name'] : '';
            if ($name === '') {
                continue;
            }

            if ($norm($name) === $gesuchterName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt die Namen aller Rollen des aktuell angemeldeten Benutzers zurück.
     *
     * @return array<int,string>
     */
    public function holeAngemeldeteRollenNamen(): array
    {
        if (!$this->istAngemeldet()) {
            return [];
        }

        $id = $this->holeAngemeldeteMitarbeiterId();
        if ($id === null) {
            return [];
        }

        $daten = $this->mitarbeiterService->holeMitarbeiterMitRollenUndGenehmigern($id);
        if (!is_array($daten)) {
            return [];
        }

        $rollen = $daten['rollen'] ?? [];
        if (!is_array($rollen) || count($rollen) === 0) {
            return [];
        }

        $namen = [];
        foreach ($rollen as $rolle) {
            $name = trim((string)($rolle['name'] ?? ''));
            if ($name !== '') {
                $namen[] = $name;
            }
        }

        return $namen;
    }


    /**
     * Superuser-Check.
     *
     * Wenn ein Benutzer mindestens eine Rolle mit `rolle.ist_superuser=1` besitzt,
     * ist jeder Rechte-Check automatisch erlaubt.
     *
     * Defensive: Falls die Spalte (noch) nicht existiert, wird kein Bypass aktiviert.
     */
    public function istSuperuser(): bool
    {
        if (!$this->istAngemeldet()) {
            return false;
        }

        $mid = $this->holeAngemeldeteMitarbeiterId();
        if ($mid === null || $mid <= 0) {
            return false;
        }

        $cachedFor = $_SESSION[self::SESSION_KEY_IST_SUPERUSER_FOR_MITARBEITER] ?? null;
        $cached = $_SESSION[self::SESSION_KEY_IST_SUPERUSER] ?? null;

        if (is_int($cachedFor) && $cachedFor === $mid && is_bool($cached)) {
            return $cached;
        }

        // Migration noch nicht vorhanden? -> kein Superuser-Bypass.
        if (!$this->rolleSuperuserSpalteVerfuegbar()) {
            $_SESSION[self::SESSION_KEY_IST_SUPERUSER_FOR_MITARBEITER] = $mid;
            $_SESSION[self::SESSION_KEY_IST_SUPERUSER] = false;
            return false;
        }

        $ist = false;
        try {
            $pdo = Database::getInstanz()->getPdo();

            $hatScope = $this->mitarbeiterHatRolleScopeTabelleVerfuegbar();

            if ($hatScope) {
                // scoped Rollen (vorerst nur scope_typ='global' wird ausgewertet)
                $sql = "SELECT 1\n"
                    . "FROM (\n"
                    . "  SELECT rolle_id FROM mitarbeiter_hat_rolle WHERE mitarbeiter_id = :mid1\n"
                    . "  UNION\n"
                    . "  SELECT rolle_id FROM mitarbeiter_hat_rolle_scope\n"
                    . "   WHERE mitarbeiter_id = :mid2 AND scope_typ = 'global'\n"
                    . ") x\n"
                    . "JOIN rolle r ON r.id = x.rolle_id\n"
                    . "WHERE r.aktiv = 1\n"
                    . "  AND r.ist_superuser = 1\n"
                    . "LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':mid1' => $mid, ':mid2' => $mid]);
            } else {
                // Legacy: nur mitarbeiter_hat_rolle
                $sql = "SELECT 1\n"
                    . "FROM mitarbeiter_hat_rolle mhr\n"
                    . "JOIN rolle r ON r.id = mhr.rolle_id\n"
                    . "WHERE mhr.mitarbeiter_id = :mid\n"
                    . "  AND r.aktiv = 1\n"
                    . "  AND r.ist_superuser = 1\n"
                    . "LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':mid' => $mid]);
            }

            $row = $stmt->fetch(PDO::FETCH_NUM);
            $ist = is_array($row) && count($row) > 0;
        } catch (Throwable $e) {
            // Defensive: Bei DB/Schema-Problemen kein Superuser (statt Fatal).
            $ist = false;
        }

        $_SESSION[self::SESSION_KEY_IST_SUPERUSER_FOR_MITARBEITER] = $mid;
        $_SESSION[self::SESSION_KEY_IST_SUPERUSER] = $ist;

        return $ist;
    }


    private function rolleSuperuserSpalteVerfuegbar(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return (bool)$cache;
        }

        try {
            $pdo = Database::getInstanz()->getPdo();
            // löst Fehler aus, falls Spalte fehlt
            $pdo->query('SELECT ist_superuser FROM rolle LIMIT 1');
            $cache = true;
            return true;
        } catch (Throwable $e) {
            $cache = false;
            return false;
        }
    }

    /**
     * Rechteprüfung (rollenbasiert).
     *
     * Prüft, ob der aktuell angemeldete Benutzer ein bestimmtes Recht besitzt.
     * Rechte werden über Rollen zugewiesen (M:N: rolle_hat_recht).
     *
     * Hinweis:
     * - Cache in Session, um wiederholte DB-Queries zu vermeiden.
     * - Wenn die Tabellen (noch) nicht existieren (Legacy), wird false zurückgegeben.
     */
    public function hatRecht(string $rechtCode): bool
    {
        $rechtCode = trim($rechtCode);
        if ($rechtCode === '' || !$this->istAngemeldet()) {
            return false;
        }

        if ($this->istSuperuser()) {
            return true;
        }

        $codes = $this->holeAngemeldeteRechteCodes();
        if (empty($codes)) {
            return false;
        }

        $norm = static function (string $v): string {
            if (function_exists('mb_strtolower')) {
                return mb_strtolower($v, 'UTF-8');
            }

            return strtolower($v);
        };

        $gesucht = $norm($rechtCode);
        foreach ($codes as $c) {
            if ($norm($c) === $gesucht) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt alle Rechte-Codes des aktuell angemeldeten Benutzers zurück.
     *
     * @return array<int,string>
     */
    public function holeAngemeldeteRechteCodes(): array
    {
        if (!$this->istAngemeldet()) {
            return [];
        }

        $mid = $this->holeAngemeldeteMitarbeiterId();
        if ($mid === null || $mid <= 0) {
            return [];
        }

        $cachedFor = $_SESSION[self::SESSION_KEY_RECHTE_FOR_MITARBEITER] ?? null;
        $cached = $_SESSION[self::SESSION_KEY_RECHTE_CODES] ?? null;

        if (is_int($cachedFor) && $cachedFor === $mid && is_array($cached)) {
            // Sanity: strings only
            $out = [];
            foreach ($cached as $v) {
                if (is_string($v) && $v !== '') {
                    $out[] = $v;
                }
            }

			// Hinweis:
			// Mitarbeiter-Overrides (Allow/Deny) werden bereits beim ersten Laden aus der DB
			// in `ladeRechteCodesAusDb()` eingerechnet. Im Cache-Zweig wird daher nur noch
			// defensiv normalisiert (Strings) und case-insensitiv dedupliziert.
			$norm = static function (string $v): string {
				if (function_exists('mb_strtolower')) {
					return mb_strtolower($v, 'UTF-8');
				}

				return strtolower($v);
			};

			$set = [];
			foreach ($out as $c) {
				$set[$norm($c)] = $c;
			}

			return array_values($set);
        }

        $codes = $this->ladeRechteCodesAusDb($mid);

        $_SESSION[self::SESSION_KEY_RECHTE_FOR_MITARBEITER] = $mid;
        $_SESSION[self::SESSION_KEY_RECHTE_CODES] = $codes;

        return $codes;
    }

    /**
     * Führt einen Logout durch.
     */
    public function logout(): void
    {
        $mitarbeiterId = $this->holeAngemeldeteMitarbeiterId();

        if (class_exists('Logger') && $mitarbeiterId !== null) {
            Logger::info('Logout', [
                'mitarbeiter_id' => $mitarbeiterId,
            ], $mitarbeiterId, null, 'auth');
        }

        if (isset($_SESSION[self::SESSION_KEY_MITARBEITER_ID])) {
            unset($_SESSION[self::SESSION_KEY_MITARBEITER_ID]);
        }

        // Rechte-Cache ebenfalls leeren
        $this->resetRechteCache();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Setzt die Session für einen Mitarbeiter.
     */
    private function setzeSessionFuerMitarbeiter(int $mitarbeiterId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY_MITARBEITER_ID] = $mitarbeiterId;

        // Rechte-Cache für neuen Login zurücksetzen
        $this->resetRechteCache();

        // Session-Fixation verhindern
        session_regenerate_id(true);
    }

    private function resetRechteCache(): void
    {
        if (isset($_SESSION[self::SESSION_KEY_RECHTE_FOR_MITARBEITER])) {
            unset($_SESSION[self::SESSION_KEY_RECHTE_FOR_MITARBEITER]);
        }
        if (isset($_SESSION[self::SESSION_KEY_RECHTE_CODES])) {
            unset($_SESSION[self::SESSION_KEY_RECHTE_CODES]);
        }

        if (isset($_SESSION[self::SESSION_KEY_IST_SUPERUSER_FOR_MITARBEITER])) {
            unset($_SESSION[self::SESSION_KEY_IST_SUPERUSER_FOR_MITARBEITER]);
        }
        if (isset($_SESSION[self::SESSION_KEY_IST_SUPERUSER])) {
            unset($_SESSION[self::SESSION_KEY_IST_SUPERUSER]);
        }
    }

    /**
     * @return array<int,string>
     */
    private function ladeRechteCodesAusDb(int $mitarbeiterId): array
    {
        // Legacy/Setup: Wenn Rechte-Tabellen noch nicht existieren, darf das nicht fatal sein.
        if (!$this->rechteTabellenVerfuegbar()) {
            return [];
        }

        try {
            $pdo = Database::getInstanz()->getPdo();

            $hatScope = $this->mitarbeiterHatRolleScopeTabelleVerfuegbar();

            if ($hatScope) {
                // scoped Rollen (vorerst nur scope_typ='global' wird ausgewertet)
                $sql = "SELECT DISTINCT r.code\n"
                    . "FROM (\n"
                    . "  SELECT rolle_id FROM mitarbeiter_hat_rolle WHERE mitarbeiter_id = :mid1\n"
                    . "  UNION\n"
                    . "  SELECT rolle_id FROM mitarbeiter_hat_rolle_scope\n"
                    . "   WHERE mitarbeiter_id = :mid2 AND scope_typ = 'global'\n"
                    . ") x\n"
                    . "JOIN rolle_hat_recht rhr ON rhr.rolle_id = x.rolle_id\n"
                    . "JOIN recht r ON r.id = rhr.recht_id\n"
                    . "WHERE r.aktiv = 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':mid1' => $mitarbeiterId, ':mid2' => $mitarbeiterId]);
            } else {
                $sql = "SELECT DISTINCT r.code\n"
                    . "FROM mitarbeiter_hat_rolle mhr\n"
                    . "JOIN rolle_hat_recht rhr ON rhr.rolle_id = mhr.rolle_id\n"
                    . "JOIN recht r ON r.id = rhr.recht_id\n"
                    . "WHERE mhr.mitarbeiter_id = :mid AND r.aktiv = 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':mid' => $mitarbeiterId]);
            }
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $code) {
                    $c = trim((string)$code);
                    if ($c !== '') {
                        $out[] = $c;
                    }
                }
            }

            // Mitarbeiter-Overrides (Allow/Deny) anwenden, falls vorhanden.
            $norm = static function (string $v): string {
                if (function_exists('mb_strtolower')) {
                    return mb_strtolower($v, 'UTF-8');
                }

                return strtolower($v);
            };

            // Set (case-insensitiv), damit Overwrites/Entzüge stabil funktionieren
            $set = [];
            foreach ($out as $c) {
                $set[$norm($c)] = $c;
            }

            if ($this->mitarbeiterRechteOverrideTabelleVerfuegbar()) {
                try {
                    $sql2 = "SELECT r.code, mhr.erlaubt\n"
                        . "FROM mitarbeiter_hat_recht mhr\n"
                        . "JOIN recht r ON r.id = mhr.recht_id\n"
                        . "WHERE mhr.mitarbeiter_id = :mid AND r.aktiv = 1";

                    $stmt2 = $pdo->prepare($sql2);
                    $stmt2->execute([':mid' => $mitarbeiterId]);
                    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                    if (is_array($rows2)) {
                        foreach ($rows2 as $row) {
                            $code = trim((string)($row['code'] ?? ''));
                            if ($code === '') {
                                continue;
                            }

                            $key = $norm($code);
                            $erlaubt = (int)($row['erlaubt'] ?? 0);

                            if ($erlaubt === 1) {
                                // explizit erlauben (auch ohne Rollenrecht)
                                $set[$key] = $code;
                            } else {
                                // expliziter Entzug
                                if (isset($set[$key])) {
                                    unset($set[$key]);
                                }
                            }
                        }
                    }
                } catch (Throwable $e2) {
                    // Ignorieren: Overrides sind optional.
                }
            }

            return array_values($set);
        } catch (Throwable $e) {
            // Defensive: In Fehlerfällen keine Rechte (statt Fatal).
            return [];
        }
    }

    private function rechteTabellenVerfuegbar(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return (bool)$cache;
        }

        try {
            $pdo = Database::getInstanz()->getPdo();
            // Minimaler Existenzcheck
            $pdo->query('SELECT 1 FROM recht LIMIT 1');
            $pdo->query('SELECT 1 FROM rolle_hat_recht LIMIT 1');
            $cache = true;
            return true;
        } catch (Throwable $e) {
            $cache = false;
            return false;
        }
    }

    private function mitarbeiterRechteOverrideTabelleVerfuegbar(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return (bool)$cache;
        }

        try {
            $pdo = Database::getInstanz()->getPdo();
            $pdo->query('SELECT 1 FROM mitarbeiter_hat_recht LIMIT 1');
            $cache = true;
            return true;
        } catch (Throwable $e) {
            $cache = false;
            return false;
        }
    }


    private function mitarbeiterHatRolleScopeTabelleVerfuegbar(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return (bool)$cache;
        }

        try {
            $pdo = Database::getInstanz()->getPdo();
            $pdo->query('SELECT 1 FROM mitarbeiter_hat_rolle_scope LIMIT 1');
            $cache = true;
            return true;
        } catch (Throwable $e) {
            $cache = false;
            return false;
        }
    }


    /**
     * Interne Logging-Hilfsfunktion für fehlgeschlagene Logins.
     */
    private function logLoginFehler(string $kennung, string $grund): void
    {
        if (!class_exists('Logger')) {
            return;
        }

        Logger::warn('Login fehlgeschlagen', [
            'kennung'   => $kennung,
            'grund'     => $grund,
        ], null, null, 'auth');
    }
}
