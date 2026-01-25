<?php
declare(strict_types=1);

/**
 * Auth
 *
 * Verantwortlich für:
 * - Login/Logout im Backend (Benutzername/Passwort)
 * - Optionales Login am Terminal über RFID-Code
 * - Ermittlung des aktuell angemeldeten Mitarbeiters
 * - Einfache Rollenabfragen
 */
class Auth
{
    /** Singleton-Instanz. */
    private static ?Auth $instanz = null;

    private Database $datenbank;
    private SessionManager $sessionManager;

    /**
     * Privater Konstruktor – holt sich benötigte Abhängigkeiten.
     */
    private function __construct()
    {
        $this->datenbank      = Database::getInstanz();
        $this->sessionManager = SessionManager::getInstanz();
    }

    /**
     * Liefert die Singleton-Instanz von Auth.
     */
    public static function getInstanz(): Auth
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Login über Benutzername und Passwort (Backend-Login).
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function loginMitBenutzername(string $benutzername, string $passwort): bool
    {
        $benutzername = trim($benutzername);

        if ($benutzername === '' || $passwort === '') {
            return false;
        }

        try {
            $sql = 'SELECT id, benutzername, passwort_hash, aktiv, ist_login_berechtigt
                    FROM mitarbeiter
                    WHERE benutzername = :benutzername
                    LIMIT 1';

            $mitarbeiter = $this->datenbank->fetchEine($sql, [
                'benutzername' => $benutzername,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Login (DB-Zugriff)', ['exception' => $e->getMessage()], null, null, 'auth');
            }
            return false;
        }

        if ($mitarbeiter === null) {
            return false;
        }

        if ((int)$mitarbeiter['aktiv'] !== 1 || (int)$mitarbeiter['ist_login_berechtigt'] !== 1) {
            return false;
        }

        $hash = (string)($mitarbeiter['passwort_hash'] ?? '');
        if ($hash === '' || !password_verify($passwort, $hash)) {
            return false;
        }

        // Optional: Hash bei Bedarf aktualisieren (z. B. wenn Algorithmus sich ändert).
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            try {
                $neuerHash = password_hash($passwort, PASSWORD_DEFAULT);
                $this->datenbank->ausfuehren(
                    'UPDATE mitarbeiter SET passwort_hash = :hash WHERE id = :id',
                    [
                        'hash' => $neuerHash,
                        'id'   => (int)$mitarbeiter['id'],
                    ]
                );
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::warn('Konnte Passwort-Hash beim Login nicht aktualisieren', ['exception' => $e->getMessage()], (int)$mitarbeiter['id'], null, 'auth');
                }
            }
        }

        // Erfolgreicher Login → Mitarbeiter in Session hinterlegen.
        $this->sessionManager->meldeMitarbeiterAn((int)$mitarbeiter['id']);

        if (class_exists('Logger')) {
            Logger::info('Mitarbeiter erfolgreich eingeloggt', [], (int)$mitarbeiter['id'], null, 'auth');
        }

        return true;
    }

    /**
     * Login am Terminal über RFID-Code.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function loginMitRfid(string $rfidCode): bool
    {
        $rfidCode = trim($rfidCode);
        if ($rfidCode === '') {
            return false;
        }

        try {
            $sql = 'SELECT id, rfid_code, aktiv, ist_login_berechtigt
                    FROM mitarbeiter
                    WHERE rfid_code = :rfid_code
                    LIMIT 1';

            $mitarbeiter = $this->datenbank->fetchEine($sql, [
                'rfid_code' => $rfidCode,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim RFID-Login (DB-Zugriff)', ['exception' => $e->getMessage()], null, null, 'auth');
            }
            return false;
        }

        if ($mitarbeiter === null) {
            return false;
        }

        if ((int)$mitarbeiter['aktiv'] !== 1 || (int)$mitarbeiter['ist_login_berechtigt'] !== 1) {
            return false;
        }

        $this->sessionManager->meldeMitarbeiterAn((int)$mitarbeiter['id']);

        if (class_exists('Logger')) {
            Logger::info('Mitarbeiter erfolgreich via RFID eingeloggt', [], (int)$mitarbeiter['id'], null, 'auth');
        }

        return true;
    }

    /**
     * Meldet den aktuell angemeldeten Mitarbeiter ab.
     */
    public function logout(): void
    {
        $mitarbeiterId = $this->sessionManager->holeAngemeldeteMitarbeiterId();

        $this->sessionManager->meldeAb();

        if (class_exists('Logger') && $mitarbeiterId !== null) {
            Logger::info('Mitarbeiter ausgeloggt', [], $mitarbeiterId, null, 'auth');
        }
    }

    /**
     * Prüft, ob ein Mitarbeiter angemeldet ist.
     */
    public function istAngemeldet(): bool
    {
        return $this->sessionManager->istMitarbeiterAngemeldet();
    }

    /**
     * Liefert den aktuell angemeldeten Mitarbeiter-Datensatz oder null.
     *
     * @return array<string,mixed>|null
     */
    public function holeAngemeldetenMitarbeiter(): ?array
    {
        $id = $this->sessionManager->holeAngemeldeteMitarbeiterId();
        if ($id === null) {
            return null;
        }

        try {
            $sql = 'SELECT *
                    FROM mitarbeiter
                    WHERE id = :id
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden des angemeldeten Mitarbeiters', ['exception' => $e->getMessage()], $id, null, 'auth');
            }
            return null;
        }
    }

    /**
     * Prüft, ob der aktuell angemeldete Mitarbeiter eine bestimmte Rolle besitzt.
     *
     * @param int|string $rolle Rolle-ID (int) oder Rollenname (string)
     */
    public function hatRolle(int|string $rolle): bool
    {
        $mitarbeiterId = $this->sessionManager->holeAngemeldeteMitarbeiterId();
        if ($mitarbeiterId === null) {
            return false;
        }

        try {
            if (is_int($rolle)) {
                $sql = 'SELECT 1
                        FROM mitarbeiter_hat_rolle mr
                        INNER JOIN rolle r ON r.id = mr.rolle_id
                        WHERE mr.mitarbeiter_id = :mitarbeiter_id
                          AND r.id = :rolle_id
                        LIMIT 1';

                $datensatz = $this->datenbank->fetchEine($sql, [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'rolle_id'       => $rolle,
                ]);
            } else {
                $rollenName = trim($rolle);
                if ($rollenName === '') {
                    return false;
                }

                $sql = 'SELECT 1
                        FROM mitarbeiter_hat_rolle mr
                        INNER JOIN rolle r ON r.id = mr.rolle_id
                        WHERE mr.mitarbeiter_id = :mitarbeiter_id
                          AND r.name = :rollen_name
                        LIMIT 1';

                $datensatz = $this->datenbank->fetchEine($sql, [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'rollen_name'    => $rollenName,
                ]);
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler bei Rollenprüfung', ['exception' => $e->getMessage()], $mitarbeiterId, null, 'auth');
            }
            return false;
        }

        return $datensatz !== null;
    }
}
