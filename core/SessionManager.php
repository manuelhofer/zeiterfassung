<?php
declare(strict_types=1);

/**
 * SessionManager
 *
 * Kapselt das komplette Session-Handling der Anwendung:
 * - Starten und Konfigurieren der PHP-Session
 * - Speichern und Auslesen von Session-Werten
 * - An- und Abmelden eines Mitarbeiters
 * - Regenerieren der Session-ID nach erfolgreichem Login
 *
 * Ziel:
 * - Nur diese Klasse spricht direkt mit den PHP-Session-Funktionen.
 * - Controller und Services verwenden ausschließlich diese Klasse,
 *   anstatt selbst `session_start()` oder `$_SESSION` zu verwenden.
 */
class SessionManager
{
    private const SESSION_KEY_MITARBEITER_ID = 'mitarbeiter_id';

    /** Singleton-Instanz. */
    private static ?SessionManager $instanz = null;

    /** Kennzeichen, ob bereits eine Session gestartet wurde. */
    private bool $sessionGestartet = false;

    /**
     * Privater Konstruktor.
     * Nutzt `initialisiereSession()`, um bei der ersten Verwendung der Klasse
     * die Session zu konfigurieren und zu starten.
     */
    private function __construct()
    {
        $this->initialisiereSession();
    }

    /**
     * Liefert die Singleton-Instanz des SessionManagers.
     */
    public static function getInstanz(): SessionManager
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Session initialisieren und mit sinnvollen Cookie-Parametern konfigurieren.
     */
    private function initialisiereSession(): void
    {
        if ($this->sessionGestartet) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Optional: Session-Namen anhand des App-Namens aus der config.php ableiten.
            $sessionName = $this->ermittleSessionNameAusKonfiguration();
            if ($sessionName !== '') {
                session_name($sessionName);
            }

            $secure = $this->istHttpsAktiv();

            // Session-Cookie so sicher wie möglich konfigurieren.
            session_set_cookie_params([
                'lifetime' => 0,       // Session-Cookie, lebt bis zum Schließen des Browsers
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure, // nur via HTTPS übertragen, falls verfügbar
                'httponly' => true,    // kein Zugriff über JavaScript (XSS-Schutz)
                'samesite' => 'Lax',   // Standard ausreichend; bei Bedarf später konfigurierbar
            ]);

            session_start();
        }

        $this->sessionGestartet = true;
    }

    /**
     * Ermittelt einen sinnvollen Session-Namen aus der zentralen Konfiguration.
     *
     * Fallback: leerer String → PHP verwendet den Standardnamen (meist PHPSESSID).
     */
    private function ermittleSessionNameAusKonfiguration(): string
    {
        $konfigPfad = __DIR__ . '/../config.php';
        if (!file_exists($konfigPfad)) {
            return '';
        }

        /** @var mixed $konfigRoh */
        $konfigRoh = require $konfigPfad;
        if (!is_array($konfigRoh)) {
            return '';
        }

        $appBereich = $konfigRoh['app'] ?? null;
        if (!is_array($appBereich)) {
            return '';
        }

        $name = (string)($appBereich['name'] ?? '');
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        // Nur Buchstaben und Zahlen zulassen, alles andere entfernen.
        $bereinigt = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        if ($bereinigt === null || $bereinigt === '') {
            return '';
        }

        return strtoupper($bereinigt);
    }

    /**
     * Hilfsfunktion: Prüft, ob die aktuelle Anfrage über HTTPS läuft.
     */
    private function istHttpsAktiv(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /**
     * Stellt sicher, dass eine Session aktiv ist.
     * Kann von öffentlichen Methoden aufgerufen werden, bevor auf `$_SESSION` zugegriffen wird.
     */
    private function stelleSessionSicher(): void
    {
        if (!$this->sessionGestartet || session_status() !== PHP_SESSION_ACTIVE) {
            $this->initialisiereSession();
        }
    }

    /**
     * Beliebigen Wert in der Session speichern.
     *
     * @param string $schluessel
     * @param mixed  $wert
     */
    public function setzeWert(string $schluessel, mixed $wert): void
    {
        $this->stelleSessionSicher();
        $_SESSION[$schluessel] = $wert;
    }

    /**
     * Wert aus der Session lesen.
     *
     * @param string $schluessel
     * @param mixed  $standardWert Wert, der zurückgegeben wird, wenn der Schlüssel nicht existiert.
     *
     * @return mixed
     */
    public function holeWert(string $schluessel, mixed $standardWert = null): mixed
    {
        $this->stelleSessionSicher();

        if (!array_key_exists($schluessel, $_SESSION)) {
            return $standardWert;
        }

        return $_SESSION[$schluessel];
    }

    /**
     * Prüft, ob ein bestimmter Schlüssel in der Session existiert.
     */
    public function hatWert(string $schluessel): bool
    {
        $this->stelleSessionSicher();
        return array_key_exists($schluessel, $_SESSION);
    }

    /**
     * Entfernt einen Wert aus der Session.
     */
    public function entferneWert(string $schluessel): void
    {
        $this->stelleSessionSicher();

        unset($_SESSION[$schluessel]);
    }

    /**
     * Angemeldeten Mitarbeiter in der Session hinterlegen.
     *
     * Wird typischerweise nach erfolgreichem Login aufgerufen.
     */
    public function meldeMitarbeiterAn(int $mitarbeiterId): void
    {
        $this->stelleSessionSicher();

        // Nach Login Session-ID wechseln, um Session-Fixation-Angriffe zu erschweren.
        $this->regeneriereSessionId();

        $_SESSION[self::SESSION_KEY_MITARBEITER_ID] = $mitarbeiterId;
    }

    /**
     * Liefert die aktuell angemeldete Mitarbeiter-ID oder null, wenn niemand angemeldet ist.
     */
    public function holeAngemeldeteMitarbeiterId(): ?int
    {
        $this->stelleSessionSicher();

        if (!isset($_SESSION[self::SESSION_KEY_MITARBEITER_ID])) {
            return null;
        }

        $wert = $_SESSION[self::SESSION_KEY_MITARBEITER_ID];

        if (is_int($wert)) {
            return $wert;
        }

        if (is_numeric($wert)) {
            return (int)$wert;
        }

        return null;
    }

    /**
     * Prüft, ob aktuell ein Mitarbeiter angemeldet ist.
     */
    public function istMitarbeiterAngemeldet(): bool
    {
        return $this->holeAngemeldeteMitarbeiterId() !== null;
    }

    /**
     * Meldet den aktuellen Mitarbeiter ab und zerstört die Session.
     */
    public function meldeAb(): void
    {
        $this->stelleSessionSicher();

        // Session-Inhalt leeren.
        $_SESSION = [];

        // Session-Cookie löschen (falls vorhanden).
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 3600,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
        $this->sessionGestartet = false;
    }

    /**
     * Regeneriert die Session-ID, ohne die Session-Daten zu verlieren.
     *
     * Wird vor allem nach erfolgreichem Login empfohlen.
     */
    public function regeneriereSessionId(): void
    {
        $this->stelleSessionSicher();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
