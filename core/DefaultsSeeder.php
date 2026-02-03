<?php
declare(strict_types=1);

/**
 * DefaultsSeeder
 *
 * Legt notwendige Standardwerte in der Datenbank an, falls sie fehlen.
 *
 * WICHTIG:
 * - Idempotent: Es werden nur fehlende Einträge erzeugt (keine Überschreibung).
 * - Defensiv: Darf Backend/Terminal niemals hart crashen lassen.
 */
class DefaultsSeeder
{
    /**
     * Führt alle Default-Checks/Seeds aus.
     */
    public static function ensureDefaults(): void
    {
        // Database::getInstanz() darf nie zu einem Hard-Crash führen.
        try {
            $db = Database::getInstanz();
        } catch (Throwable $e) {
            return;
        }

        // Wenn die Haupt-DB nicht verfügbar ist, keine Seeds versuchen.
        try {
            if (method_exists($db, 'istHauptdatenbankVerfuegbar') && $db->istHauptdatenbankVerfuegbar() !== true) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }

        self::ensureConfigDefaults($db);
    }

    /**
     * Legt fehlende Default-Config-Keys an.
     */
    private static function ensureConfigDefaults(Database $db): void
    {
        if (!self::tableExists($db, 'config')) {
            return;
        }

        // Defaults laut Master-Prompt / TerminalController
        // Hinweis: Rundung der Rohzeiten beim Buchen ist laut Master-Prompt deaktiviert (Rohdaten immer sekundengenau).
        // Daher wird hier kein Config-Key wie 'zeit_rundung_beim_buchen' mehr geseedet.
        $defaults = [
            [
                'schluessel'    => 'terminal_timeout_standard',
                'wert'          => '60',
                'typ'           => 'int',
                'beschreibung'  => 'Terminal: Auto-Logout Standard (Sekunden). Default 60.',
            ],
            [
                'schluessel'    => 'terminal_timeout_urlaub',
                'wert'          => '180',
                'typ'           => 'int',
                'beschreibung'  => 'Terminal: Auto-Logout im Urlaub-Kontext (Sekunden). Default 180.',
            ],
            [
                'schluessel'    => 'terminal_session_idle_timeout',
                'wert'          => '300',
                'typ'           => 'int',
                'beschreibung'  => 'Terminal: serverseitiges Session-Idle-Timeout (Sekunden). Fallback, falls JS-Auto-Logout nicht greift. Default 300.',
            ],
            [
                'schluessel'    => 'urlaub_blocke_negativen_resturlaub',
                'wert'          => '0',
                'typ'           => 'bool',
                'beschreibung'  => 'Urlaub: Wenn aktiv (1), werden Urlaubsanträge blockiert, wenn der Resturlaub dadurch negativ würde. Default 0.',
            ],
            [
                'schluessel'    => 'terminal_healthcheck_interval',
                'wert'          => '10',
                'typ'           => 'int',
                'beschreibung'  => 'Terminal: Intervall (Sekunden) für wiederkehrende Healthchecks (Hauptdatenbank/Offline-Queue Anzeige). Default 10.',
            ],
            [
                'schluessel'    => 'micro_buchung_max_sekunden',
                'wert'          => '180',
                'typ'           => 'int',
                'beschreibung'  => 'Zeitbuchungen: Mikro-Buchungen (Kommen/Gehen) bis zu X Sekunden werden standardmäßig ignoriert/ausgeblendet. Default 180 (= 3 Minuten).',
            ],
            [
                'schluessel'    => 'maschinen_qr_rel_pfad',
                'wert'          => 'uploads/maschinen_codes',
                'typ'           => 'string',
                'beschreibung'  => 'Maschinen-QR: Relativer Speicherpfad unterhalb von public. Default uploads/maschinen_codes.',
            ],
            [
                'schluessel'    => 'maschinen_qr_url',
                'wert'          => '',
                'typ'           => 'string',
                'beschreibung'  => 'Maschinen-QR: URL oder Basispfad für die Ausgabe. Leer = Domain-Root.',
            ],
        ];

        // INSERT IGNORE ist sicher: es werden nur fehlende Keys eingefügt.
        $sql = 'INSERT IGNORE INTO config (schluessel, wert, typ, beschreibung)
                VALUES
                    (:k1, :w1, :t1, :b1),
                    (:k2, :w2, :t2, :b2),
                    (:k3, :w3, :t3, :b3),
                    (:k4, :w4, :t4, :b4),
                    (:k5, :w5, :t5, :b5),
                    (:k6, :w6, :t6, :b6),
                    (:k7, :w7, :t7, :b7),
                    (:k8, :w8, :t8, :b8)';

        try {
            $betroffen = $db->ausfuehren($sql, [
                'k1' => $defaults[0]['schluessel'],
                'w1' => $defaults[0]['wert'],
                't1' => $defaults[0]['typ'],
                'b1' => $defaults[0]['beschreibung'],

                'k2' => $defaults[1]['schluessel'],
                'w2' => $defaults[1]['wert'],
                't2' => $defaults[1]['typ'],
                'b2' => $defaults[1]['beschreibung'],

                'k3' => $defaults[2]['schluessel'],
                'w3' => $defaults[2]['wert'],
                't3' => $defaults[2]['typ'],
                'b3' => $defaults[2]['beschreibung'],

                'k4' => $defaults[3]['schluessel'],
                'w4' => $defaults[3]['wert'],
                't4' => $defaults[3]['typ'],
                'b4' => $defaults[3]['beschreibung'],

                'k5' => $defaults[4]['schluessel'],
                'w5' => $defaults[4]['wert'],
                't5' => $defaults[4]['typ'],
                'b5' => $defaults[4]['beschreibung'],

                'k6' => $defaults[5]['schluessel'],
                'w6' => $defaults[5]['wert'],
                't6' => $defaults[5]['typ'],
                'b6' => $defaults[5]['beschreibung'],

                'k7' => $defaults[6]['schluessel'],
                'w7' => $defaults[6]['wert'],
                't7' => $defaults[6]['typ'],
                'b7' => $defaults[6]['beschreibung'],

                'k8' => $defaults[7]['schluessel'],
                'w8' => $defaults[7]['wert'],
                't8' => $defaults[7]['typ'],
                'b8' => $defaults[7]['beschreibung'],
            ]);

            if ($betroffen > 0 && class_exists('Logger')) {
                Logger::info('Default-Config-Werte wurden automatisch angelegt (fehlende Keys).', [
                    'keys' => array_column($defaults, 'schluessel'),
                ], null, null, 'config');
            }
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Default-Config-Werte konnten nicht automatisch angelegt werden.', [
                    'exception' => $e->getMessage(),
                ], null, null, 'config');
            }
        }
    }

    /**
     * Prüft, ob eine Tabelle existiert.
     */
    private static function tableExists(Database $db, string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        try {
            $row = $db->fetchEine(
                'SELECT 1 AS ok
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :t
                 LIMIT 1',
                ['t' => $table]
            );

            return $row !== null;
        } catch (Throwable $e) {
            return false;
        }
    }
}
