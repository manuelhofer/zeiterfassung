<?php
declare(strict_types=1);

/**
 * Einfacher Autoloader für Core-, Modell-, Service- und Controller-Klassen.
 *
 * Klassen werden ohne Namespace verwendet und direkt aus den Standardordnern geladen:
 * - core/
 * - modelle/
 * - services/
 * - controller/
 */
spl_autoload_register(
    static function (string $class): void {
        $class = ltrim($class, '\\');

        if ($class === '') {
            return;
        }

        $baseDir = __DIR__ . '/..';

        $verzeichnisse = [
            'core',
            'modelle',
            'services',
            'controller',
        ];

        foreach ($verzeichnisse as $verz) {
            $pfad = $baseDir . '/' . $verz . '/' . $class . '.php';

            if (is_file($pfad)) {
                require_once $pfad;
                return;
            }
        }

        // Falls nichts gefunden wurde, nicht weiter stören –
        // andere Autoloader (falls vorhanden) dürfen weiter suchen.
    }
);
