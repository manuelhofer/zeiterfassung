<?php
declare(strict_types=1);

/**
 * Csrf
 *
 * Ein Token je Bereich, in der Session gehalten – die eine Stelle, an der
 * CSRF-Schutz im Projekt definiert ist.
 *
 * **Warum je Bereich und nicht ein Token für alles:** Die Masken des Backends
 * sind unabhängig voneinander. Ein gemeinsames Token würde bedeuten, dass ein
 * Neuladen der Rollenmaske das Token der Terminalverwaltung mit erneuert – ein
 * offenes Formular in einem zweiten Tab liesse sich dann nicht mehr
 * abschicken. Der Bereichsname entspricht dem, was früher als
 * `CSRF_KEY`-Konstante in jedem Controller stand.
 *
 * **Warum kein Rückfall auf ein schwaches Token:** Die früheren Kopien
 * fingen ein misslungenes `random_bytes()` ab und setzten ersatzweise
 * `bin2hex((string)mt_rand())` – acht Hexzeichen, vorhersagbar, und damit
 * schlechter als gar kein Schutz, weil er einen vortäuscht. Wenn das System
 * keine Zufallszahlen liefern kann, ist das ein echter Fehler und darf
 * auffallen.
 */
class Csrf
{
    /** Name des Formularfelds – im ganzen Projekt einheitlich. */
    public const FELD = 'csrf_token';

    /**
     * Liefert das Token des Bereichs und legt es beim ersten Aufruf an.
     */
    public static function token(string $bereich): string
    {
        self::stelleSessionSicher();

        $schluessel = self::sessionSchluessel($bereich);
        $token      = $_SESSION[$schluessel] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[$schluessel] = $token;
        }

        return $token;
    }

    /**
     * Prüft das aus `$_POST` gesendete Token gegen den Bereich.
     *
     * Der Vergleich läuft über `hash_equals()`, damit die Laufzeit nichts
     * über das erwartete Token verrät.
     */
    public static function istGueltig(string $bereich): bool
    {
        self::stelleSessionSicher();

        $ausSession = $_SESSION[self::sessionSchluessel($bereich)] ?? '';
        $ausPost    = $_POST[self::FELD] ?? '';

        if (!is_string($ausSession) || $ausSession === '') {
            return false;
        }
        if (!is_string($ausPost) || $ausPost === '') {
            return false;
        }

        return hash_equals($ausSession, $ausPost);
    }

    /**
     * Verwirft das Token eines Bereichs.
     *
     * Gebraucht beim Abmelden am Terminal: Der nächste Nutzer soll ein
     * frisches Token bekommen und nicht das des Vorgängers weiterverwenden.
     */
    public static function verwerfe(string $bereich): void
    {
        self::stelleSessionSicher();
        unset($_SESSION[self::sessionSchluessel($bereich)]);
    }

    /**
     * Liefert das fertige versteckte Formularfeld.
     *
     * Spart in jeder Maske dieselbe Zeile aus `htmlspecialchars` und
     * Feldnamen – und verhindert, dass eine davon den Feldnamen verschreibt.
     */
    public static function feld(string $bereich): string
    {
        return '<input type="hidden" name="' . self::FELD . '" value="'
            . htmlspecialchars(self::token($bereich), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }

    private static function sessionSchluessel(string $bereich): string
    {
        return 'csrf_token_' . $bereich;
    }

    /**
     * Die Einstiegspunkte starten die Session bereits. Der Aufruf hier ist die
     * Absicherung für Pfade, die einen Controller direkt einbinden.
     */
    private static function stelleSessionSicher(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
