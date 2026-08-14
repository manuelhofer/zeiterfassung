<?php
declare(strict_types=1);

/**
 * TerminalKopplungController
 *
 * Der Endpunkt, an dem sich ein frisch installiertes Terminal anmeldet
 * (`?seite=terminal_kopplung`, nur POST, Antwort als JSON).
 *
 * Ablauf am Gerät: Auf dem Touchscreen werden Server-Adresse und der im
 * Backend erzeugte Kopplungscode eingegeben. Das Terminal ruft damit diesen
 * Endpunkt auf und bekommt alles zurück, was es braucht - Terminal-ID,
 * eigene Zugangsdaten zur Datenbank und seine Einstellungen. Aus der Antwort
 * schreibt es seine `config.local.php`.
 *
 * **Warum ohne Anmeldung:** Ein frisches Gerät hat keinen Benutzer, mit dem es
 * sich anmelden könnte - das ist ja gerade der Zweck der Kopplung. Der
 * Kopplungscode **ist** der Nachweis: einmalig, 30 Minuten gültig, nur als
 * Hash gespeichert (siehe `TerminalKopplungService`).
 *
 * Siehe `docs/spezifikation_terminal_installation.md`, Abschnitt 2a.
 */
class TerminalKopplungController
{
    /** Ab so vielen Fehlversuchen je Absender wird abgewiesen. */
    private const MAX_FEHLVERSUCHE = 10;

    /** Zeitfenster der Fehlversuchszählung in Minuten. */
    private const FEHLVERSUCH_FENSTER_MINUTEN = 10;

    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Nimmt einen Kopplungscode entgegen und richtet das Terminal ein.
     */
    public function koppeln(): void
    {
        // Zugangsdaten in der Antwort: nirgends zwischenspeichern lassen.
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->antworte(405, ['ok' => false, 'fehler' => 'Nur POST.']);
            return;
        }

        $code = trim((string)($_POST['code'] ?? ''));
        $host = trim((string)($_POST['host'] ?? ''));

        if ($code === '') {
            $this->antworte(400, ['ok' => false, 'fehler' => 'Es wurde kein Kopplungscode übergeben.']);
            return;
        }

        if ($this->zuVieleFehlversuche()) {
            $this->antworte(429, [
                'ok'     => false,
                'fehler' => 'Zu viele Fehlversuche. Bitte einige Minuten warten.',
            ]);
            return;
        }

        // Vorprüfung mit klarer Meldung: Ohne `CREATE USER` kann das Backend
        // keinen Datenbankbenutzer anlegen. Das ist ein Einrichtungsfehler des
        // Servers und darf nicht als „Code ungültig“ beim Monteur ankommen -
        // sonst sucht er am falschen Ende.
        $benutzerDienst = TerminalDbBenutzerService::getInstanz();
        if (!$benutzerDienst->istVerfuegbar()) {
            Logger::error('Kopplung nicht möglich: Backend darf keine Datenbankbenutzer anlegen', [
                'hinweis' => 'Siehe sql/06_migration_terminal_db_benutzer.sql (CREATE USER / GRANT OPTION).',
            ], null, null, 'terminal_kopplung');

            $this->antworte(500, [
                'ok'     => false,
                'fehler' => 'Der Server darf keine Datenbankbenutzer anlegen. '
                          . 'Ein Administrator muss die Rechte aus sql/06_migration_terminal_db_benutzer.sql einspielen.',
            ]);
            return;
        }

        // Ab hier ist der Code verbraucht - egal wie es weitergeht. Genau so ist
        // er gemeint: einmalig.
        $terminal = TerminalKopplungService::getInstanz()->loeseCodeEin($code, $host !== '' ? $host : null);

        if (!is_array($terminal)) {
            $this->merkeFehlversuch();

            // Bewusst ohne Angabe, ob der Code unbekannt, abgelaufen oder schon
            // verbraucht war - das hilft nur beim Durchprobieren.
            $this->antworte(403, [
                'ok'     => false,
                'fehler' => 'Der Kopplungscode ist ungültig. Bitte im Backend einen neuen erzeugen.',
            ]);
            return;
        }

        $terminalId = (int)($terminal['id'] ?? 0);

        // Ein stillgelegtes Gerät darf sich nicht zurückholen.
        if ((int)($terminal['aktiv'] ?? 0) !== 1) {
            Logger::warn('Kopplung abgelehnt: Terminal ist nicht aktiv', [
                'terminal_id' => $terminalId,
            ], null, $terminalId > 0 ? $terminalId : null, 'terminal_kopplung');

            $this->antworte(403, [
                'ok'     => false,
                'fehler' => 'Dieses Terminal ist im Backend nicht aktiv.',
            ]);
            return;
        }

        $zugang = $benutzerDienst->legeAnOderErsetze(
            $terminalId,
            (string)($terminal['name'] ?? ''),
            isset($terminal['db_benutzer']) ? (string)$terminal['db_benutzer'] : null,
            isset($terminal['db_benutzer_host']) ? (string)$terminal['db_benutzer_host'] : null
        );

        if ($zugang === null) {
            $this->antworte(500, [
                'ok'     => false,
                'fehler' => 'Der Datenbankbenutzer konnte nicht angelegt werden. '
                          . 'Bitte einen neuen Kopplungscode erzeugen und das Serverprotokoll prüfen.',
            ]);
            return;
        }

        // Merken, welcher Benutzer zu diesem Gerät gehört - sonst lässt sich
        // eine spätere Kopplung nicht sauber ersetzen und ein ausgemustertes
        // Gerät nicht gezielt sperren.
        try {
            $this->datenbank->ausfuehren(
                'UPDATE terminal
                    SET db_benutzer = :benutzer,
                        db_benutzer_host = :dbhost,
                        gekoppelt_am = NOW(),
                        gekoppelt_host = :geraet
                  WHERE id = :id',
                [
                    'benutzer' => $zugang['benutzer'],
                    'dbhost'   => $zugang['host'],
                    'geraet'   => $host !== '' ? mb_substr($host, 0, 190) : null,
                    'id'       => $terminalId,
                ]
            );
        } catch (\Throwable $e) {
            // Kein halber Zustand: Ein Datenbankbenutzer, von dem das Backend
            // nichts weiß, lässt sich später nicht mehr zuordnen und bliebe
            // für immer gültig.
            $benutzerDienst->entferne($zugang['benutzer'], $zugang['host']);

            Logger::error('Kopplung konnte nicht gespeichert werden', [
                'terminal_id' => $terminalId,
                'exception'   => $e->getMessage(),
            ], null, $terminalId > 0 ? $terminalId : null, 'terminal_kopplung');

            $this->antworte(500, [
                'ok'     => false,
                'fehler' => 'Die Kopplung konnte nicht gespeichert werden. Bitte einen neuen Kopplungscode erzeugen.',
            ]);
            return;
        }

        $antwort = [
            'ok'       => true,
            'terminal' => [
                'id'                           => $terminalId,
                'name'                         => (string)($terminal['name'] ?? ''),
                'standort_beschreibung'        => $terminal['standort_beschreibung'] ?? null,
                'abteilung_id'                 => isset($terminal['abteilung_id']) && $terminal['abteilung_id'] !== null
                                                   ? (int)$terminal['abteilung_id'] : null,
                'modus'                        => (string)($terminal['modus'] ?? 'terminal'),
                'auto_logout_timeout_sekunden' => (int)($terminal['auto_logout_timeout_sekunden'] ?? 60),
                'offline_erlaubt_kommen_gehen' => (int)($terminal['offline_erlaubt_kommen_gehen'] ?? 0) === 1,
                'offline_erlaubt_auftraege'    => (int)($terminal['offline_erlaubt_auftraege'] ?? 0) === 1,
            ],
            'db' => [
                'host'    => $this->ermittleDatenbankAdresse(),
                'dbname'  => $zugang['dbname'],
                'user'    => $zugang['benutzer'],
                'pass'    => $zugang['passwort'],
                'charset' => 'utf8mb4',
            ],
        ];

        // Bei der Kopplung gehen Zugangsdaten über das Netz. Ohne HTTPS liest
        // sie jeder mit, der im Hallennetz mithört - das Terminal soll das
        // anzeigen können, statt dass es niemandem auffällt.
        if (!$this->istVerschluesselt()) {
            $antwort['warnung'] = 'Die Kopplung lief unverschlüsselt über HTTP. '
                                . 'Die Zugangsdaten waren im Netz mitlesbar - bitte HTTPS einrichten '
                                . 'oder das Passwort über eine erneute Kopplung wechseln.';

            Logger::warn('Kopplung ohne HTTPS durchgeführt', [
                'terminal_id' => $terminalId,
            ], null, $terminalId > 0 ? $terminalId : null, 'terminal_kopplung');
        }

        $this->antworte(200, $antwort);
    }

    // ------------------------------------------------------------------
    // Interna
    // ------------------------------------------------------------------

    /**
     * Adresse, unter der das Terminal die Datenbank erreicht.
     *
     * Der Haken: In der Konfiguration des Backends steht meist `localhost` -
     * für ein Terminal im Hallennetz ist das wertlos, es würde sich selbst
     * ansprechen. Deshalb:
     *
     * 1. `config: terminal_db_host_extern`, falls gepflegt (letztes Wort),
     * 2. sonst der konfigurierte Datenbank-Host, wenn er nicht lokal ist,
     * 3. sonst die Adresse, unter der das Terminal gerade das Backend erreicht
     *    hat - Backend und Datenbank liegen laut Installationsanleitung auf
     *    demselben Server.
     */
    private function ermittleDatenbankAdresse(): string
    {
        $konfiguriert = '';
        try {
            $konfig = require __DIR__ . '/../config/config.php';
            $konfiguriert = (string)($konfig['db']['host'] ?? '');
        } catch (\Throwable $e) {
            $konfiguriert = '';
        }

        try {
            $override = (string)KonfigurationService::getInstanz()->get('terminal_db_host_extern', '');
            if (is_string($override) && trim($override) !== '') {
                return trim($override);
            }
        } catch (\Throwable $e) {
            // weiter mit der Ableitung unten
        }

        $lokal = ['localhost', '127.0.0.1', '::1', ''];
        if (!in_array(strtolower($konfiguriert), $lokal, true)) {
            return $konfiguriert;
        }

        $httpHost = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($httpHost !== '') {
            // Portangabe entfernen; bei IPv6 in eckigen Klammern das Ende suchen.
            if (str_starts_with($httpHost, '[')) {
                $ende = strpos($httpHost, ']');
                if ($ende !== false) {
                    return substr($httpHost, 1, $ende - 1);
                }
            }

            $teile = explode(':', $httpHost);
            if ($teile[0] !== '') {
                return $teile[0];
            }
        }

        return $konfiguriert !== '' ? $konfiguriert : 'localhost';
    }

    private function istVerschluesselt(): bool
    {
        $https = (string)($_SERVER['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        // Hinter einem Reverse Proxy steht die Information nur im Header.
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return $proto === 'https';
    }

    /**
     * Zählt die Fehlversuche desselben Absenders im aktuellen Zeitfenster.
     *
     * Der Code selbst ist mit 8 Zeichen aus 31 nicht zu erraten; die Bremse
     * sorgt vor allem dafür, dass ein Dauerbeschuss auffällt und den Server
     * nicht beschäftigt.
     */
    private function zuVieleFehlversuche(): bool
    {
        $absender = $this->absender();
        if ($absender === '') {
            return false;
        }

        try {
            $zeile = $this->datenbank->fetchEine(
                'SELECT COUNT(*) AS anzahl
                   FROM system_log
                  WHERE kategorie = :kat
                    AND nachricht = :nachricht
                    AND zeitstempel >= DATE_SUB(NOW(), INTERVAL :minuten MINUTE)
                    AND daten LIKE :muster',
                [
                    'kat'       => 'terminal_kopplung',
                    'nachricht' => 'Kopplungsversuch fehlgeschlagen',
                    'minuten'   => self::FEHLVERSUCH_FENSTER_MINUTEN,
                    'muster'    => '%"absender":"' . $absender . '"%',
                ]
            );
        } catch (\Throwable $e) {
            // Die Bremse darf die Kopplung nicht verhindern, wenn das Protokoll
            // gerade nicht lesbar ist.
            return false;
        }

        return (int)($zeile['anzahl'] ?? 0) >= self::MAX_FEHLVERSUCHE;
    }

    private function merkeFehlversuch(): void
    {
        Logger::warn('Kopplungsversuch fehlgeschlagen', [
            'absender' => $this->absender(),
        ], null, null, 'terminal_kopplung');
    }

    /**
     * IP-Adresse des Anfragenden. Bewusst ohne Auswertung von
     * `X-Forwarded-For`: Diesen Kopf darf jeder frei setzen, er wäre als
     * Grundlage einer Sperre wertlos.
     */
    private function absender(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        return preg_match('~^[0-9a-fA-F:.]{1,45}$~', $ip) ? $ip : '';
    }

    /**
     * @param array<string,mixed> $daten
     */
    private function antworte(int $status, array $daten): void
    {
        http_response_code($status);
        echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
