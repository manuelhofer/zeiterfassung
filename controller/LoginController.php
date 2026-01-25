<?php
declare(strict_types=1);

/**
 * LoginController
 *
 * Verantwortlich für:
 * - Anzeige des Login-Formulars
 * - Verarbeitung des Logins (Benutzername/Passwort)
 * - Logout
 */
class LoginController
{
    private AuthService $authService;
    private MitarbeiterModel $mitarbeiterModel;

    public function __construct()
    {
        $this->authService      = AuthService::getInstanz();
        $this->mitarbeiterModel = new MitarbeiterModel();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }


    /**
     * Prüft, ob bereits mindestens ein login-berechtigter Mitarbeiter existiert.
     *
     * Gibt true zurück, wenn noch KEIN login-berechtigter Mitarbeiter vorhanden ist.
     */
    private function istErstinstallation(): bool
    {
        try {
            return !$this->mitarbeiterModel->existiertLoginberechtigterMitarbeiter();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler bei der Prüfung auf Erstinstallation', [
                    'exception' => $e->getMessage(),
                ], null, null, 'auth');
            }

            // Im Zweifel lieber normales Login anzeigen, nicht den Installer.
            return false;
        }
    }

    /**
     * Einstiegspunkt für ?seite=login
     *
     * GET  → Formular anzeigen
     * POST → Login verarbeiten
     */
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Bei Erstinstallation das Initial-Admin-Formular verarbeiten
            if ($this->istErstinstallation() && isset($_POST['aktion']) && $_POST['aktion'] === 'initial_admin') {
                $this->verarbeiteInitialAdmin();
            } else {
                $this->verarbeiteLogin();
            }
            return;
        }

        // Bereits angemeldete Benutzer direkt zum Dashboard
        if ($this->authService->istAngemeldet()) {
            header('Location: ?seite=dashboard');
            return;
        }

        // Wenn noch kein login-berechtigter Mitarbeiter vorhanden ist,
        // direkt das Erstinstallations-Formular zeigen.
        if ($this->istErstinstallation()) {
            $this->zeigeInitialAdminFormular();
            return;
        }

        $this->zeigeLoginFormular();
    }

    /**
     * Verarbeitet das Login-Formular.
     */
    private function verarbeiteLogin(): void
    {
        $benutzername = isset($_POST['benutzername']) ? trim((string)$_POST['benutzername']) : '';
        $passwort     = isset($_POST['passwort']) ? (string)$_POST['passwort'] : '';

        if ($benutzername === '' || $passwort === '') {
            $this->zeigeLoginFormular('Bitte Benutzername/E-Mail und Passwort eingeben.');
            return;
        }

        $erfolg = $this->authService->loginMitBenutzername($benutzername, $passwort);

        if (!$erfolg) {
            $this->zeigeLoginFormular('Login fehlgeschlagen. Bitte prüfen Sie Ihre Eingaben.');
            return;
        }

        // Erfolgreich → auf Dashboard umleiten
        header('Location: ?seite=dashboard');
    }

    /**
     * Zeigt das Login-Formular an.
     */
    private function zeigeLoginFormular(?string $fehlermeldung = null): void
    {
        $fehlermeldungVariable = $fehlermeldung; // für klare Übergabe
        $fehlermeldung = $fehlermeldungVariable;
        require __DIR__ . '/../views/login/form.php';
    }


    /**
     * Zeigt das Erstinstallations-Formular zum Anlegen des ersten Admin-Benutzers.
     */
    private function zeigeInitialAdminFormular(?string $fehlermeldung = null): void
    {
        $fehlermeldungVariable = $fehlermeldung;
        $fehlermeldung = $fehlermeldungVariable;
        require __DIR__ . '/../views/login/initial_admin.php';
    }

    /**
     * Verarbeitet das Erstinstallations-Formular und legt den ersten Admin-Benutzer an.
     */
    private function verarbeiteInitialAdmin(): void
    {
        $vorname      = isset($_POST['vorname']) ? trim((string)$_POST['vorname']) : '';
        $nachname     = isset($_POST['nachname']) ? trim((string)$_POST['nachname']) : '';
        $benutzername = isset($_POST['benutzername']) ? trim((string)$_POST['benutzername']) : '';
        $email        = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $passwort     = isset($_POST['passwort']) ? (string)$_POST['passwort'] : '';
        $passwort2    = isset($_POST['passwort_bestaetigung']) ? (string)$_POST['passwort_bestaetigung'] : '';

        if ($vorname === '' || $nachname === '' || $benutzername === '' || $passwort === '' || $passwort2 === '') {
            $this->zeigeInitialAdminFormular('Bitte alle Pflichtfelder ausfüllen.');
            return;
        }

        if ($passwort !== $passwort2) {
            $this->zeigeInitialAdminFormular('Die eingegebenen Passwörter stimmen nicht überein.');
            return;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->zeigeInitialAdminFormular('Die eingegebene E-Mail-Adresse ist ungültig.');
            return;
        }

        // Sicherstellen, dass die Erstinstallation wirklich noch aktiv ist.
        if (!$this->istErstinstallation()) {
            // Falls inzwischen ein Benutzer angelegt wurde, zurück zum normalen Login.
            $this->zeigeLoginFormular('Es existiert bereits ein login-berechtigter Benutzer. Bitte normal einloggen.');
            return;
        }

        $hash = password_hash($passwort, PASSWORD_DEFAULT);

        $daten = [
            'vorname'               => $vorname,
            'nachname'              => $nachname,
            'geburtsdatum'          => null,
            'wochenarbeitszeit'     => 40.0,
            'urlaub_monatsanspruch' => 2.5,
            'benutzername'          => $benutzername,
            'email'                 => $email,
            'passwort_hash'         => $hash,
            'rfid_code'             => null,
            'aktiv'                 => 1,
            'ist_login_berechtigt'  => 1,
        ];

        try {
            $mitarbeiterId = $this->mitarbeiterModel->erstelleMitarbeiter($daten);
        } catch (\Throwable $e) {
            $mitarbeiterId = null;
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Anlegen des Initial-Admin-Benutzers', [
                    'daten'     => $daten,
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }
        }

        if ($mitarbeiterId === null) {
            $this->zeigeInitialAdminFormular('Der Benutzer konnte nicht angelegt werden. Bitte prüfen Sie die Eingaben.');
            return;
        }

        // Optional: Basiskonfiguration der Rollen "Chef" und "Personalbüro" und Zuordnung zum neuen Benutzer.
        try {
            $db = Database::getInstanz();

            // Rollen anlegen (falls noch nicht vorhanden)
            $db->ausfuehren(
                'INSERT INTO rolle (name, beschreibung, aktiv)
                 VALUES (:name, :beschreibung, 1)
                 ON DUPLICATE KEY UPDATE beschreibung = VALUES(beschreibung), aktiv = 1',
                [
                    'name'         => 'Chef',
                    'beschreibung' => 'Vollzugriff auf alle Adminfunktionen',
                ]
            );

            $db->ausfuehren(
                'INSERT INTO rolle (name, beschreibung, aktiv)
                 VALUES (:name, :beschreibung, 1)
                 ON DUPLICATE KEY UPDATE beschreibung = VALUES(beschreibung), aktiv = 1',
                [
                    'name'         => 'Personalbüro',
                    'beschreibung' => 'Verwaltung von Mitarbeitern, Urlaub und Stammdaten',
                ]
            );

            // IDs der Rollen holen
            $chef   = $db->fetchEine('SELECT id FROM rolle WHERE name = :name LIMIT 1', ['name' => 'Chef']);
            $pbuero = $db->fetchEine('SELECT id FROM rolle WHERE name = :name LIMIT 1', ['name' => 'Personalbüro']);

            $rollenIds = [];
            if ($chef !== null && isset($chef['id'])) {
                $rollenIds[] = (int)$chef['id'];
            }
            if ($pbuero !== null && isset($pbuero['id'])) {
                $rollenIds[] = (int)$pbuero['id'];
            }

            if (!empty($rollenIds)) {
                $zuordnungModel = new MitarbeiterHatRolleModel();
                $zuordnungModel->speichereRollenFuerMitarbeiter($mitarbeiterId, $rollenIds);
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Initial-Admin wurde angelegt, aber Rollen konnten nicht vollständig zugeordnet werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'rolle');
            }
            // Kein harter Abbruch – der Benutzer existiert, kann sich einloggen und Rollen später nachziehen.
        }

        // Erfolgreich: Hinweis und zurück zum Login.
        $this->zeigeLoginFormular('Der erste Admin-Benutzer wurde angelegt. Bitte melden Sie sich jetzt mit diesen Zugangsdaten an.');
    }

    /**
     * Führt einen Logout durch.
     */
    public function logout(): void
    {
        $this->authService->logout();
        header('Location: ?seite=login');
    }
}
