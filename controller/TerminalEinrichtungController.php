<?php
declare(strict_types=1);

/**
 * TerminalEinrichtungController
 *
 * Die Einrichtungsseite eines frisch installierten Terminals - Stufe 2 der
 * Terminal-Installation (siehe `docs/spezifikation_terminal_installation.md`,
 * Abschnitte 2 und 11).
 *
 * Ausgangslage: Das Installationsskript bringt ein Gerät bis zur laufenden
 * Weboberflaeche, kennt aber bewusst **keine** Zugangsdaten - dasselbe Abbild
 * passt so auf beliebig viele Geräte. Fehlt deshalb `config/config.local.php`,
 * zeigt `public/terminal.php` nicht die Bedienoberflaeche, sondern diese Seite.
 * Das ist dieselbe Mechanik wie die Erstinstallation im Backend
 * (`views/login/initial_admin.php`), nur für den Touchscreen gebaut.
 *
 * Ablauf: Server-Adresse und Kopplungscode eingeben -> das Terminal ruft
 * `?seite=terminal_kopplung` des Backends auf -> aus der Antwort entsteht
 * `config/config.local.php` -> danach läuft das Terminal normal.
 */
class TerminalEinrichtungController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'terminal_einrichtung';

    /** Zeitlimit für den Aufruf des Backends (Sekunden). */
    private const ANFRAGE_TIMEOUT = 20;

    /** Zeitlimit nur für den Verbindungsaufbau (Sekunden). */
    private const VERBINDUNG_TIMEOUT = 8;

    /**
     * Optionale Datei mit den **gerätelokalen** Einstellungen, die das
     * Installationsskript (Stufe 3) hinterlässt: Zugangsdaten der lokalen
     * Ausweichdatenbank und die RFID-Bridge.
     *
     * Warum getrennt: Diese Werte gehören der Maschine und nicht dem Backend -
     * sie stehen beim Koppeln also nicht in der Antwort. Fehlt die Datei
     * (z. B. weil Stufe 3 noch nicht gelaufen ist), koppelt das Terminal
     * trotzdem; es hat dann nur keine Offline-Ausweichdatenbank, und die Seite
     * sagt das ausdrücklich.
     */
    private const GERAETE_DATEI = 'geraet.local.php';

    /**
     * Pfad der Konfiguration, die diese Seite schreibt.
     */
    public static function konfigPfad(): string
    {
        return dirname(__DIR__) . '/config/config.local.php';
    }

    /**
     * Ist dieses Gerät bereits konfiguriert?
     *
     * Bewusst **nur** die Existenz der Datei - nicht "ist die Datenbank
     * erreichbar". Ein Terminal ohne Netz ist kein unkonfiguriertes Terminal:
     * Der Offline-Betrieb mit Queue ist eine gewollte Betriebsart. Würde ein
     * Netzausfall die Einrichtungsseite hervorholen, stuende ein Monteur bei
     * jeder Störung vor einer Maske, die nach einem Kopplungscode fragt - und
     * die Buchungen der Halle wären weg.
     */
    public static function istEingerichtet(): bool
    {
        $pfad = self::konfigPfad();

        // Der realpath-Cache merkt sich auch, dass eine Datei **nicht**
        // existiert. Ohne dieses Leeren könnte ein Arbeitsprozess die eben
        // geschriebene Konfiguration minutenlang übersehen und das frisch
        // gekoppelte Terminal weiter zur Einrichtung schicken.
        clearstatcache(true, $pfad);

        return is_file($pfad);
    }

    /**
     * Einstiegspunkt: GET zeigt das Formular, POST führt die Kopplung durch.
     */
    public function bearbeiten(): void
    {
        // Eine vorhandene Konfiguration wird niemals überschrieben. Sonst
        // liesse sich ein laufendes Terminal über diese Seite auf einen
        // fremden Server umbiegen.
        if (self::istEingerichtet()) {
            header('Location: terminal.php?aktion=start');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verarbeite();
            return;
        }

        $this->zeigeFormular();
    }

    // ------------------------------------------------------------------
    // Ablauf
    // ------------------------------------------------------------------

    private function verarbeite(): void
    {
        $adresse = trim((string)($_POST['serveradresse'] ?? ''));
        $code    = trim((string)($_POST['kopplungscode'] ?? ''));

        $werte = ['serveradresse' => $adresse, 'kopplungscode' => $code];

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            $this->zeigeFormular('Die Sitzung ist abgelaufen. Bitte die Eingaben wiederholen.', $werte);
            return;
        }

        if ($adresse === '') {
            $this->zeigeFormular('Bitte die Adresse des Servers eingeben.', $werte);
            return;
        }

        if ($code === '') {
            $this->zeigeFormular('Bitte den Kopplungscode eingeben.', $werte);
            return;
        }

        $kandidaten = $this->baueKandidaten($adresse);
        if ($kandidaten === []) {
            $this->zeigeFormular(
                'Die Adresse konnte nicht gelesen werden. Beispiel: 192.168.10.5 oder http://server/zeiterfassung',
                $werte
            );
            return;
        }

        $ergebnis = $this->frageBackend($kandidaten, $code);

        if ($ergebnis['antwort'] === null) {
            $this->zeigeFormular($ergebnis['fehler'] ?? 'Der Server hat nicht geantwortet.', $werte);
            return;
        }

        $antwort = $ergebnis['antwort'];

        if (($antwort['ok'] ?? false) !== true) {
            $meldung = trim((string)($antwort['fehler'] ?? ''));
            if ($meldung === '') {
                $meldung = 'Die Kopplung wurde vom Server abgelehnt.';
            }

            $this->zeigeFormular($meldung, $werte);
            return;
        }

        $terminal = is_array($antwort['terminal'] ?? null) ? $antwort['terminal'] : [];
        $db       = is_array($antwort['db'] ?? null) ? $antwort['db'] : [];

        if ((string)($db['user'] ?? '') === '' || (string)($db['dbname'] ?? '') === '') {
            $this->zeigeFormular(
                'Der Server hat geantwortet, aber ohne verwertbare Zugangsdaten. Bitte das Serverprotokoll pruefen.',
                $werte
            );
            return;
        }

        $inhalt = $this->baueKonfigDatei($terminal, $db, $ergebnis['url'] ?? '');

        if (!$this->schreibeKonfig($inhalt)) {
            // Der Kopplungscode ist an dieser Stelle verbraucht - deshalb darf
            // die Seite nicht einfach "Fehler" sagen. Der Inhalt wird
            // angezeigt, damit die Datei notfalls von Hand angelegt werden
            // kann, statt eine erneute Kopplung zu erzwingen.
            $this->zeigeFormular(
                'Die Kopplung hat funktioniert, aber die Datei config/config.local.php konnte nicht '
                . 'geschrieben werden. Bitte den Inhalt unten uebernehmen - der Kopplungscode ist verbraucht.',
                $werte,
                $inhalt
            );
            return;
        }

        Logger::info('Terminal eingerichtet (Kopplung am Geraet)', [
            'terminal_id' => (int)($terminal['id'] ?? 0),
            'endpunkt'    => (string)($ergebnis['url'] ?? ''),
        ], null, isset($terminal['id']) ? (int)$terminal['id'] : null, 'terminal_einrichtung');

        $this->zeigeErfolg($terminal, isset($antwort['warnung']) ? (string)$antwort['warnung'] : null);
    }

    /**
     * Ruft der Reihe nach die möglichen Endpunkt-Adressen auf.
     *
     * Warum mehrere: Je nach Installation zeigt der Webserver direkt auf
     * `public/` (Produktivserver laut Installationsanleitung) oder auf das
     * Projektverzeichnis. Der Monteur soll deshalb nur den Namen des Servers
     * eintippen müssen und nicht wissen, wie dort der Pfad aufgebaut ist.
     *
     * Es wird nur so lange weitergesucht, bis eine Antwort **unseres**
     * Endpunkts kommt (gültiges JSON mit `ok`). Ein Fehlversuch auf einem
     * falschen Pfad verbraucht den Kopplungscode nicht, weil der Endpunkt dort
     * gar nicht läuft.
     *
     * @param array<int,string> $kandidaten
     *
     * @return array{antwort:array<string,mixed>|null,fehler:string|null,url:string}
     */
    private function frageBackend(array $kandidaten, string $code): array
    {
        $letzterFehler = null;
        $geraeteKennung = $this->geraeteKennung();

        foreach ($kandidaten as $url) {
            $roh = $this->sendeAnfrage($url, ['code' => $code, 'host' => $geraeteKennung]);

            if ($roh['fehler'] !== null) {
                $letzterFehler = $roh['fehler'];
                continue;
            }

            if ($roh['weiterleitung'] !== null && $roh['weiterleitung'] !== '') {
                // Bewusst nicht automatisch folgen: Eine Weiterleitung kann auf
                // einen anderen Rechner zeigen, und dorthin gehen Zugangsdaten.
                // Der Monteur soll die richtige Adresse eintragen.
                $letzterFehler = 'Der Server verweist auf eine andere Adresse: '
                    . $roh['weiterleitung'] . ' - bitte diese oben eintragen.';
                continue;
            }

            $daten = json_decode($roh['body'], true);
            if (!is_array($daten) || !array_key_exists('ok', $daten)) {
                $letzterFehler = 'Unter ' . $url . ' antwortet keine Zeiterfassung (HTTP '
                    . (string)$roh['status'] . '). Bitte die Adresse pruefen.';
                continue;
            }

            /** @var array<string,mixed> $daten */
            return ['antwort' => $daten, 'fehler' => null, 'url' => $url];
        }

        return ['antwort' => null, 'fehler' => $letzterFehler, 'url' => ''];
    }

    /**
     * Ein einzelner POST an das Backend.
     *
     * @param array<string,string> $felder
     *
     * @return array{status:int,body:string,fehler:string|null,weiterleitung:string|null}
     */
    private function sendeAnfrage(string $url, array $felder): array
    {
        $inhalt = http_build_query($felder);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $inhalt);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, self::ANFRAGE_TIMEOUT);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::VERBINDUNG_TIMEOUT);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

                $body = curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $ziel = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
                $fehlertext = curl_error($ch);

                // Kein `curl_close()`: seit PHP 8.0 wirkungslos und seit 8.5
                // ausdrücklich veraltet. Die Verbindung raeumt der
                // Speicherverwalter auf, sobald `$ch` aus dem Gültigkeits-
                // bereich fällt.
                unset($ch);

                if (!is_string($body)) {
                    return [
                        'status'        => $status,
                        'body'          => '',
                        'fehler'        => 'Der Server ist nicht erreichbar: ' . $fehlertext,
                        'weiterleitung' => null,
                    ];
                }

                return [
                    'status'        => $status,
                    'body'          => $body,
                    'fehler'        => null,
                    'weiterleitung' => ($status >= 300 && $status < 400 && $ziel !== '') ? $ziel : null,
                ];
            }
        }

        // Rückfallebene ohne cURL (Minimalinstallation eines Terminals).
        $kontext = stream_context_create([
            'http' => [
                'method'           => 'POST',
                'header'           => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content'          => $inhalt,
                'timeout'          => self::ANFRAGE_TIMEOUT,
                'ignore_errors'    => true,
                'follow_location'  => 0,
                'max_redirects'    => 0,
            ],
        ]);

        $http_response_header = [];
        $body = @file_get_contents($url, false, $kontext);

        if (!is_string($body)) {
            return [
                'status'        => 0,
                'body'          => '',
                'fehler'        => 'Der Server ist unter ' . $url . ' nicht erreichbar.',
                'weiterleitung' => null,
            ];
        }

        $status = 0;
        $ziel = null;
        foreach ($http_response_header as $zeile) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $zeile, $treffer) === 1) {
                $status = (int)$treffer[1];
            } elseif (stripos($zeile, 'Location:') === 0) {
                $ziel = trim(substr($zeile, 9));
            }
        }

        return [
            'status'        => $status,
            'body'          => $body,
            'fehler'        => null,
            'weiterleitung' => ($status >= 300 && $status < 400) ? $ziel : null,
        ];
    }

    /**
     * Baut aus der eingegebenen Adresse die möglichen Endpunkt-URLs.
     *
     * @return array<int,string>
     */
    private function baueKandidaten(string $adresse): array
    {
        $adresse = trim($adresse);
        if ($adresse === '') {
            return [];
        }

        // Ohne Schema tippt niemand am Touchscreen "http://".
        if (preg_match('~^https?://~i', $adresse) !== 1) {
            $adresse = 'http://' . $adresse;
        }

        $teile = parse_url($adresse);
        if (!is_array($teile) || !isset($teile['host']) || (string)$teile['host'] === '') {
            return [];
        }

        $schema = strtolower((string)($teile['scheme'] ?? 'http'));
        if ($schema !== 'http' && $schema !== 'https') {
            return [];
        }

        $basis = $schema . '://' . $teile['host'];
        if (isset($teile['port'])) {
            $basis .= ':' . (int)$teile['port'];
        }

        // Query und Fragment werden verworfen: Der Endpunkt bringt seinen
        // eigenen Parameter mit.
        $pfad = rtrim((string)($teile['path'] ?? ''), '/');

        if (preg_match('~/index\.php$~i', $pfad) === 1) {
            return [$basis . $pfad . '?seite=terminal_kopplung'];
        }

        return [
            // Webserver zeigt auf public/ (Standard laut Installationsanleitung)
            $basis . $pfad . '/index.php?seite=terminal_kopplung',
            // Webserver zeigt auf das Projektverzeichnis
            $basis . $pfad . '/public/index.php?seite=terminal_kopplung',
        ];
    }

    /**
     * Kennung dieses Geräts - reine Nachvollziehbarkeit im Backend.
     */
    private function geraeteKennung(): string
    {
        $teile = [];

        $hostname = php_uname('n');
        if (is_string($hostname) && $hostname !== '') {
            $teile[] = $hostname;
        }

        $adresse = (string)($_SERVER['SERVER_ADDR'] ?? '');
        if ($adresse !== '') {
            $teile[] = $adresse;
        }

        return mb_substr(implode(' / ', $teile), 0, 190);
    }

    // ------------------------------------------------------------------
    // Konfiguration schreiben
    // ------------------------------------------------------------------

    /**
     * Erzeugt den Inhalt von `config/config.local.php`.
     *
     * Vorlage ist `config/config.php.example`; ausgefüllt wird alles, was die
     * Kopplung geliefert hat, plus die gerätelokalen Werte aus
     * `config/geraet.local.php` (falls vorhanden).
     *
     * @param array<string,mixed> $terminal
     * @param array<string,mixed> $db
     */
    private function baueKonfigDatei(array $terminal, array $db, string $endpunkt): string
    {
        $geraet = $this->leseGeraeteEinstellungen();

        $zeitzone = 'Europe/Berlin';
        try {
            /** @var array<string,mixed> $aktuell */
            $aktuell = require dirname(__DIR__) . '/config/config.php';
            if (isset($aktuell['timezone']) && is_string($aktuell['timezone']) && $aktuell['timezone'] !== '') {
                $zeitzone = $aktuell['timezone'];
            }
        } catch (\Throwable $e) {
            // Standard behalten.
        }

        $offline = $geraet['offline_db'];
        $rfid    = $geraet['rfid_ws'];

        $w = static function ($wert): string {
            return var_export($wert, true);
        };

        $zeilen = [];
        $zeilen[] = '<?php';
        $zeilen[] = 'declare(strict_types=1);';
        $zeilen[] = '';
        $zeilen[] = '/**';
        $zeilen[] = ' * Konfiguration dieses Terminals.';
        $zeilen[] = ' *';
        $zeilen[] = ' * Automatisch erzeugt bei der Kopplung am ' . date('d.m.Y H:i:s') . '.';
        $zeilen[] = ' * Endpunkt: ' . $this->kommentarText($endpunkt);
        $zeilen[] = ' *';
        $zeilen[] = ' * Diese Datei enthaelt die Zugangsdaten **dieses einen** Terminals.';
        $zeilen[] = ' * Sie gehoert nicht ins Repository und wird bei einer erneuten Kopplung';
        $zeilen[] = ' * ersetzt. Geht das Geraet verloren, reicht es, im Backend den zugehoerigen';
        $zeilen[] = ' * Datenbankbenutzer zu loeschen.';
        $zeilen[] = ' */';
        $zeilen[] = '';
        $zeilen[] = 'return [';
        $zeilen[] = "    'app' => [";
        $zeilen[] = "        'name' => 'Zeiterfassung',";
        $zeilen[] = "        'base_url' => '',";
        $zeilen[] = "        'debug' => false,";
        $zeilen[] = "        'installation_typ' => 'terminal',";
        $zeilen[] = '    ],';
        $zeilen[] = '';
        $zeilen[] = "    'timezone' => " . $w($zeitzone) . ',';
        $zeilen[] = '';
        $zeilen[] = '    // Hauptdatenbank: eigener, eingeschraenkter Benutzer dieses Terminals.';
        $zeilen[] = "    'db' => [";
        $zeilen[] = "        'host'    => " . $w((string)($db['host'] ?? 'localhost')) . ',';
        $zeilen[] = "        'dbname'  => " . $w((string)($db['dbname'] ?? 'zeiterfassung')) . ',';
        $zeilen[] = "        'charset' => " . $w((string)($db['charset'] ?? 'utf8mb4')) . ',';
        $zeilen[] = "        'user'    => " . $w((string)($db['user'] ?? '')) . ',';
        $zeilen[] = "        'pass'    => " . $w((string)($db['pass'] ?? '')) . ',';
        $zeilen[] = '    ],';
        $zeilen[] = '';

        if ($offline === null) {
            $zeilen[] = '    // Lokale Ausweichdatenbank: nicht eingerichtet.';
            $zeilen[] = '    // Das Installationsskript legt sie an und hinterlegt die Zugangsdaten';
            $zeilen[] = '    // in config/geraet.local.php. Ohne sie laeuft das Terminal nur online.';
            $zeilen[] = "    'offline_db' => [";
            $zeilen[] = "        'enabled' => false,";
            $zeilen[] = "        'host'    => 'localhost',";
            $zeilen[] = "        'dbname'  => 'zeiterfassung_offline',";
            $zeilen[] = "        'charset' => 'utf8mb4',";
            $zeilen[] = "        'user'    => '',";
            $zeilen[] = "        'pass'    => '',";
            $zeilen[] = '    ],';
        } else {
            $zeilen[] = '    // Lokale Ausweichdatenbank (aus config/geraet.local.php).';
            $zeilen[] = "    'offline_db' => [";
            $zeilen[] = "        'enabled' => " . $w((bool)$offline['enabled']) . ',';
            $zeilen[] = "        'host'    => " . $w((string)$offline['host']) . ',';
            $zeilen[] = "        'dbname'  => " . $w((string)$offline['dbname']) . ',';
            $zeilen[] = "        'charset' => " . $w((string)$offline['charset']) . ',';
            $zeilen[] = "        'user'    => " . $w((string)$offline['user']) . ',';
            $zeilen[] = "        'pass'    => " . $w((string)$offline['pass']) . ',';
            $zeilen[] = '    ],';
        }

        $zeilen[] = '';
        $zeilen[] = '    // Wer dieses Terminal ist - aus der Kopplung uebernommen.';
        $zeilen[] = "    'terminal' => [";
        $zeilen[] = "        'id'                           => " . $w((int)($terminal['id'] ?? 0)) . ',';
        $zeilen[] = "        'name'                         => " . $w((string)($terminal['name'] ?? '')) . ',';
        $zeilen[] = "        'standort_beschreibung'        => " . $w((string)($terminal['standort_beschreibung'] ?? '')) . ',';
        $zeilen[] = "        'abteilung_id'                 => "
            . $w(isset($terminal['abteilung_id']) && $terminal['abteilung_id'] !== null ? (int)$terminal['abteilung_id'] : null) . ',';
        $zeilen[] = "        'auto_logout_timeout_sekunden' => " . $w((int)($terminal['auto_logout_timeout_sekunden'] ?? 60)) . ',';
        $zeilen[] = "        'offline_erlaubt_kommen_gehen' => " . $w((bool)($terminal['offline_erlaubt_kommen_gehen'] ?? false)) . ',';
        $zeilen[] = "        'offline_erlaubt_auftraege'    => " . $w((bool)($terminal['offline_erlaubt_auftraege'] ?? false)) . ',';
        $zeilen[] = '';
        $zeilen[] = '        // RFID-Bridge (nur bei SPI-Lesern wie RC522; USB-Leser tippen wie eine Tastatur).';
        $zeilen[] = "        'rfid_ws' => [";
        $zeilen[] = "            'enabled' => " . $w((bool)$rfid['enabled']) . ',';
        $zeilen[] = "            'url'     => " . $w((string)$rfid['url']) . ',';
        $zeilen[] = '        ],';
        $zeilen[] = '    ],';
        $zeilen[] = '];';
        $zeilen[] = '';

        return implode("\n", $zeilen);
    }

    /**
     * Liest die gerätelokalen Einstellungen, die das Installationsskript
     * hinterlassen hat.
     *
     * Bewusst mit fester Auswahl: Aus dieser Datei werden **nur**
     * Ausweichdatenbank und RFID-Bridge übernommen. Die Zugangsdaten zur
     * Hauptdatenbank kommen ausschließlich aus der Kopplung - sonst wäre die
     * Trennung zwischen Skript und Kopplung wieder aufgeweicht.
     *
     * @return array{offline_db:array<string,mixed>|null,rfid_ws:array<string,mixed>}
     */
    private function leseGeraeteEinstellungen(): array
    {
        $standardRfid = ['enabled' => false, 'url' => 'ws://127.0.0.1:8765'];
        $pfad = dirname(__DIR__) . '/config/' . self::GERAETE_DATEI;

        clearstatcache(true, $pfad);
        if (!is_file($pfad)) {
            return ['offline_db' => null, 'rfid_ws' => $standardRfid];
        }

        try {
            /** @var mixed $daten */
            $daten = require $pfad;
        } catch (\Throwable $e) {
            Logger::warn('config/geraet.local.php konnte nicht gelesen werden', [
                'exception' => $e->getMessage(),
            ], null, null, 'terminal_einrichtung');

            return ['offline_db' => null, 'rfid_ws' => $standardRfid];
        }

        if (!is_array($daten)) {
            return ['offline_db' => null, 'rfid_ws' => $standardRfid];
        }

        $offline = null;
        if (isset($daten['offline_db']) && is_array($daten['offline_db'])) {
            $roh = $daten['offline_db'];
            $dbname = (string)($roh['dbname'] ?? '');
            $user   = (string)($roh['user'] ?? '');

            // Ohne Datenbankname und Benutzer wäre der Block wertlos; dann
            // lieber ehrlich "nicht eingerichtet" schreiben.
            if ($dbname !== '' && $user !== '') {
                $offline = [
                    'enabled' => ($roh['enabled'] ?? true) ? true : false,
                    'host'    => (string)($roh['host'] ?? 'localhost'),
                    'dbname'  => $dbname,
                    'charset' => (string)($roh['charset'] ?? 'utf8mb4'),
                    'user'    => $user,
                    'pass'    => (string)($roh['pass'] ?? ''),
                ];
            }
        }

        $rfid = $standardRfid;
        if (isset($daten['terminal']['rfid_ws']) && is_array($daten['terminal']['rfid_ws'])) {
            $roh = $daten['terminal']['rfid_ws'];
            $rfid = [
                'enabled' => ($roh['enabled'] ?? false) ? true : false,
                'url'     => (string)($roh['url'] ?? $standardRfid['url']),
            ];
            if ($rfid['url'] === '') {
                $rfid['url'] = $standardRfid['url'];
            }
        }

        return ['offline_db' => $offline, 'rfid_ws' => $rfid];
    }

    /**
     * Schreibt die Konfiguration - erst vollständig daneben, dann umbenennen.
     *
     * Grund für den Umweg: Eine halb geschriebene `config.local.php` wäre
     * schlimmer als gar keine. Sie würde von `config/config.php` eingelesen
     * und könnte das Terminal mit einem Syntaxfehler lahmlegen - und zwar
     * dauerhaft, weil die Einrichtungsseite dann auch nicht mehr erscheint.
     */
    private function schreibeKonfig(string $inhalt): bool
    {
        $ziel = self::konfigPfad();
        $verzeichnis = dirname($ziel);

        if (!is_dir($verzeichnis) || !is_writable($verzeichnis)) {
            return false;
        }

        $temp = $verzeichnis . '/.config.local.php.' . bin2hex(random_bytes(4)) . '.tmp';

        $geschrieben = @file_put_contents($temp, $inhalt, LOCK_EX);
        if ($geschrieben === false || $geschrieben !== strlen($inhalt)) {
            @unlink($temp);
            return false;
        }

        // Gegenlesen statt vertrauen: Eine volle Platte meldet sich sonst erst
        // beim nächsten Start des Terminals.
        if (@file_get_contents($temp) !== $inhalt) {
            @unlink($temp);
            return false;
        }

        // Zugangsdaten - nicht für alle lesbar.
        @chmod($temp, 0640);

        if (!@rename($temp, $ziel)) {
            @unlink($temp);
            return false;
        }

        clearstatcache(true, $ziel);

        return true;
    }

    // ------------------------------------------------------------------
    // Ausgabe
    // ------------------------------------------------------------------

    /**
     * @param array<string,string> $werte
     */
    private function zeigeFormular(?string $fehlermeldung = null, array $werte = [], ?string $abtippInhalt = null): void
    {
        $csrfToken       = Csrf::token(self::CSRF_BEREICH);
        $formularwerte   = $werte;
        $konfigPfad      = self::konfigPfad();
        $verzeichnisOk   = is_dir(dirname($konfigPfad)) && is_writable(dirname($konfigPfad));
        $geraeteDatei    = $this->leseGeraeteEinstellungen();
        $offlineFehlt    = ($geraeteDatei['offline_db'] === null);
        $erfolg          = null;
        $warnung         = null;

        require dirname(__DIR__) . '/views/terminal/einrichtung.php';
    }

    /**
     * @param array<string,mixed> $terminal
     */
    private function zeigeErfolg(array $terminal, ?string $warnung): void
    {
        $csrfToken     = '';
        $fehlermeldung = null;
        $formularwerte = [];
        $abtippInhalt  = null;
        $konfigPfad    = self::konfigPfad();
        $verzeichnisOk = true;
        $offlineFehlt  = ($this->leseGeraeteEinstellungen()['offline_db'] === null);
        $erfolg        = $terminal;

        require dirname(__DIR__) . '/views/terminal/einrichtung.php';
    }

    /**
     * Text für einen PHP-Kommentar entschaerfen (kein Kommentarende einbauen).
     */
    private function kommentarText(string $text): string
    {
        return str_replace(['*/', "\r", "\n"], ['* /', ' ', ' '], $text);
    }

    // ------------------------------------------------------------------
    // CSRF (gleiches Muster wie im TerminalController)
    // ------------------------------------------------------------------

}
