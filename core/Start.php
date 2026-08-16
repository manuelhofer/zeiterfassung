<?php
declare(strict_types=1);

/**
 * Start
 *
 * Der gemeinsame Programmstart der drei Einstiegspunkte
 * (`public/index.php`, `public/terminal.php`, `public/maschine_code.php`).
 *
 * Warum als eigene Datei: Die drei Einstiegspunkte hatten denselben Vorspann
 * dreimal – Konfiguration laden, Zeitzone setzen, Session starten, jeweils mit
 * demselben `if/else` und demselben Rückfall `Europe/Berlin`. Drei Kopien
 * heißen: Eine Änderung an der Zeitzonenlogik wird an zwei Stellen gemacht
 * und an der dritten vergessen.
 *
 * Diese Datei wird per `require` geladen, **nicht** über den Autoloader – sie
 * läuft, bevor es einen gibt.
 */
final class Start
{
    /** Einmal geladene Konfiguration – innerhalb einer Anfrage unveränderlich. */
    private static ?array $konfig = null;

    /**
     * Lädt die Konfiguration, setzt die Zeitzone und startet die Session.
     *
     * @return array<string,mixed> die geladene Konfiguration
     */
    public static function los(): array
    {
        $konfig = self::konfig();

        $zeitzone = $konfig['timezone'] ?? null;
        if (!is_string($zeitzone) || $zeitzone === '') {
            $zeitzone = 'Europe/Berlin';
        }
        date_default_timezone_set($zeitzone);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $konfig;
    }

    /**
     * Die geladene Konfiguration – für Code, der sie braucht, aber nicht im
     * Gültigkeitsbereich des Einstiegspunkts läuft.
     *
     * Warum es das gibt (T-131): `public/terminal.php` schreibt das Ergebnis
     * von `los()` in eine **lokale** Variable `$konfig`. Views werden aus
     * Methoden der Controller heraus eingebunden, also aus einem anderen
     * Gültigkeitsbereich – dort ist `$konfig` nicht sichtbar, und ein
     * `isset($konfig)` ist immer falsch. Wer die Konfiguration braucht, fragt
     * sie hier ab, statt sich auf eine Variable zu verlassen, die zufällig da
     * sein könnte.
     *
     * @return array<string,mixed>
     */
    public static function konfig(): array
    {
        if (self::$konfig === null) {
            /** @var array<string,mixed> $konfig */
            $konfig = require __DIR__ . '/../config/config.php';
            self::$konfig = $konfig;
        }

        return self::$konfig;
    }
}
