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
 * demselben `if/else` und demselben Rueckfall `Europe/Berlin`. Drei Kopien
 * heissen: Eine Aenderung an der Zeitzonenlogik wird an zwei Stellen gemacht
 * und an der dritten vergessen.
 *
 * Diese Datei wird per `require` geladen, **nicht** ueber den Autoloader – sie
 * laeuft, bevor es einen gibt.
 */
final class Start
{
    /**
     * Laedt die Konfiguration, setzt die Zeitzone und startet die Session.
     *
     * @return array<string,mixed> die geladene Konfiguration
     */
    public static function los(): array
    {
        /** @var array<string,mixed> $konfig */
        $konfig = require __DIR__ . '/../config/config.php';

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
}
