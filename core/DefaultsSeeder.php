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
            if ($db->istHauptdatenbankVerfuegbar() !== true) {
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
                // Von welchen Rechnern aus sich der Datenbankbenutzer eines
                // Terminals verbinden darf. Default '%' (beliebig), weil
                // Terminals ihre Adresse per DHCP bekommen und eine feste
                // Bindung beim nächsten Neustart still den Zugang kappt.
                'schluessel'    => 'terminal_db_host_muster',
                'wert'          => '%',
                'typ'           => 'string',
                'beschreibung'  => 'Terminal-Kopplung: Host-Muster für den Datenbankbenutzer eines Terminals (z. B. % oder 192.168.10.%). Default %.',
            ],
            [
                // Adresse, unter der ein Terminal die Datenbank erreicht. Leer
                // = automatisch ableiten (siehe TerminalKopplungController).
                // Nötig, weil in der Backend-Konfiguration meist 'localhost'
                // steht – für ein Terminal im Hallennetz wertlos.
                'schluessel'    => 'terminal_db_host_extern',
                'wert'          => '',
                'typ'           => 'string',
                'beschreibung'  => 'Terminal-Kopplung: Adresse der Datenbank aus Sicht des Terminals. Leer = automatisch (konfigurierter DB-Host, sonst die Adresse, unter der das Terminal das Backend erreicht hat).',
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
                'schluessel'    => 'auftrag_code_rel_pfad',
                'wert'          => 'uploads/auftrag_codes',
                'typ'           => 'string',
                'beschreibung'  => 'Auftrags-Strichcodes: Relativer Speicherpfad unterhalb von public für die Code-Bilder von Aufträgen, Arbeitsschritten und Katalogeinträgen. Default uploads/auftrag_codes.',
            ],
            [
                'schluessel'    => 'maschinen_qr_url',
                'wert'          => '',
                'typ'           => 'string',
                'beschreibung'  => 'Maschinen-QR: Basis-URL oder Basispfad für die Ausgabe. Leer = automatisch aus der Installation ableiten (empfohlen). "/" = ausdrücklich Domain-Root. Sonst Pfad ("/zeiterfassung") oder volle URL ("https://host/pfad"); der relative Speicherpfad wird jeweils angehängt.',
            ],
        ];

        // INSERT IGNORE ist sicher: es werden nur fehlende Keys eingefügt.
        //
        // Hinweis: Platzhalter und Parameter werden aus $defaults aufgebaut.
        // Vorher waren beide fest verdrahtet, so dass ein neuer Default-Wert an
        // drei Stellen nachgezogen werden musste – eine unnötige Fehlerquelle.
        $platzhalter = [];
        $parameter   = [];

        foreach (array_values($defaults) as $index => $eintrag) {
            $nr = $index + 1;
            $platzhalter[] = '(:k' . $nr . ', :w' . $nr . ', :t' . $nr . ', :b' . $nr . ')';

            $parameter['k' . $nr] = $eintrag['schluessel'];
            $parameter['w' . $nr] = $eintrag['wert'];
            $parameter['t' . $nr] = $eintrag['typ'];
            $parameter['b' . $nr] = $eintrag['beschreibung'];
        }

        if ($platzhalter === []) {
            return;
        }

        $sql = 'INSERT IGNORE INTO config (schluessel, wert, typ, beschreibung)
                VALUES ' . implode(",\n                       ", $platzhalter);

        try {
            $betroffen = $db->ausfuehren($sql, $parameter);

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
