<?php
declare(strict_types=1);

/**
 * TerminalDbBenutzerService
 *
 * Legt fuer ein Terminal einen **eigenen Datenbankbenutzer** mit
 * eingeschraenkten Rechten an (siehe
 * `docs/spezifikation_terminal_installation.md`, Abschnitt 2a).
 *
 * Warum ueberhaupt ein eigener Benutzer je Geraet:
 *
 * - **Einzeln sperrbar:** Ein Hallenterminal steht frei zugaenglich herum. Wer
 *   es mitnimmt, hat die Zugangsdaten. Mit einem eigenen Benutzer genuegt ein
 *   `DROP USER` fuer genau dieses Geraet - alle anderen laufen weiter.
 * - **Eingeschraenkt:** Das Terminal stempelt, bucht Auftragszeiten und nimmt
 *   Urlaubsantraege entgegen. Es braucht dafuer nirgends `DELETE`, `DROP` oder
 *   `ALTER` und keinen Schreibzugriff auf Rechte, Rollen oder Stundenkonto.
 * - **Nachvollziehbar:** In den Datenbankprotokollen ist erkennbar, welches
 *   Geraet was getan hat.
 *
 * ---
 *
 * **Bewusste Abweichung von der Regel „immer prepared statements“:**
 * `CREATE USER` und `GRANT` sind DDL. MySQL/MariaDB erlauben dort **keine**
 * Platzhalter - weder fuer Benutzernamen noch fuer Passwoerter oder
 * Tabellennamen. Die Anweisungen muessen also zusammengesetzt werden. Statt zu
 * escapen wird deshalb **eingegrenzt**: Benutzername, Host, Schema- und
 * Tabellennamen werden vor der Verwendung gegen ein enges Muster geprueft, das
 * Passwort besteht ausschliesslich aus Buchstaben und Ziffern. Was dem Muster
 * nicht entspricht, wird nicht ausgefuehrt, sondern abgebrochen.
 */
class TerminalDbBenutzerService
{
    /** Passwort-Alphabet bewusst ohne Sonderzeichen - dann gibt es beim Zusammensetzen der SQL nichts zu escapen. */
    private const PASSWORT_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const PASSWORT_LAENGE = 32;

    /** MySQL erlaubt 32 Zeichen, MariaDB 80. Wir bleiben auf der kleineren Zahl. */
    private const BENUTZER_MAX_LAENGE = 32;

    /** Standard-Host, wenn in der `config` nichts anderes gepflegt ist. */
    private const STANDARD_HOST = '%';

    /**
     * Rechte des Terminal-Benutzers, Tabelle fuer Tabelle.
     *
     * Die Liste ist **aus dem Code hergeleitet** (alles, was `terminal.php` und
     * die von dort genutzten Dienste anfassen), nicht geraten. Fehlt ein Recht,
     * bricht der Betrieb an der Maschine ab - deshalb lieber einmal genau
     * nachgesehen als hinterher gesucht.
     *
     * @var array<string,string>
     */
    private const RECHTE = [
        // --- Lesen ------------------------------------------------------
        // Anmeldung am Terminal, Namen, Wochenarbeitszeit, Urlaubsanspruch.
        'mitarbeiter'                => 'SELECT',
        // Rechtepruefung (welche Knoepfe darf dieser Mitarbeiter sehen).
        'mitarbeiter_hat_rolle'       => 'SELECT',
        'mitarbeiter_hat_rolle_scope' => 'SELECT',
        'mitarbeiter_hat_recht'       => 'SELECT',
        'mitarbeiter_hat_abteilung'   => 'SELECT',
        'mitarbeiter_genehmiger'      => 'SELECT',
        'rolle'                       => 'SELECT',
        'rolle_hat_recht'             => 'SELECT',
        'recht'                       => 'SELECT',
        'abteilung'                   => 'SELECT',
        // Stammdaten fuer Buchung und Anzeige.
        'maschine'                    => 'SELECT',
        'terminal'                    => 'SELECT',
        'config'                      => 'SELECT',
        'arbeitsschritt_katalog'      => 'SELECT',
        // Auswertung/Anzeige am Terminal (Monatsstatus, Urlaubsuebersicht).
        'zeit_rundungsregel'          => 'SELECT',
        'pausenfenster'               => 'SELECT',
        'pausenentscheidung'          => 'SELECT',
        'betriebsferien'              => 'SELECT',
        'krankzeitraum'               => 'SELECT',
        'kurzarbeit_plan'             => 'SELECT',
        'urlaub_kontingent_jahr'      => 'SELECT',
        'tageswerte_mitarbeiter'      => 'SELECT',
        'monatswerte_mitarbeiter'     => 'SELECT',
        // Das Terminal zeigt Gut-/Minusstunden an (P-2026-01-17-19) und muss
        // sie deshalb lesen duerfen. Schreiben nicht - Buchungen aufs
        // Stundenkonto bleiben Sache des Backends.
        'stundenkonto_korrektur'      => 'SELECT',

        // --- Lesen und Schreiben ---------------------------------------
        // Kommen/Gehen. Aendern und Loeschen von Stempeln ist Korrekturarbeit
        // im Backend, nicht Aufgabe des Terminals.
        'zeitbuchung'                 => 'SELECT, INSERT',
        // Das Terminal legt fehlende Stammdaten selbst an - eine Buchung darf
        // nie daran scheitern, dass ein Auftrag noch nicht gepflegt ist
        // (Master-Prompt, Abschnitt 7).
        'auftrag'                     => 'SELECT, INSERT, UPDATE',
        'auftrag_arbeitsschritt'      => 'SELECT, INSERT, UPDATE',
        'auftragszeit'                => 'SELECT, INSERT, UPDATE',
        // Antrag stellen und - fuer Genehmiger am Terminal - entscheiden.
        'urlaubsantrag'               => 'SELECT, INSERT, UPDATE',
        // Feiertage werden bei Bedarf nachgeneriert (UrlaubService). Ohne
        // INSERT rechnet ein Terminal im Januar ohne die Feiertage des neuen
        // Jahres - das faellt niemandem auf und ist deshalb gefaehrlicher als
        // das Recht selbst.
        'feiertag'                    => 'SELECT, INSERT',
        // Protokoll. Lesen, weil die Monatsauswertung Pausen-Overrides aus dem
        // Protokoll zieht.
        'system_log'                  => 'SELECT, INSERT',
        // Rueckfallebene: Normalerweise liegt die Offline-Queue in der lokalen
        // Ausweichdatenbank des Terminals. Fehlt die, greift der
        // OfflineQueueManager auf die Hauptdatenbank zurueck. Kein DELETE -
        // haengengebliebene Eintraege raeumt ein Admin im Backend weg.
        'db_injektionsqueue'          => 'SELECT, INSERT, UPDATE',
    ];

    /**
     * Spaltenweise Rechte.
     *
     * `mitarbeiter` ist absichtlich der einzige Fall: Am Terminal lassen sich
     * RFID-Chips zuweisen, dafuer genuegt genau diese eine Spalte.
     *
     * @var array<string,array<string,array<int,string>>>
     */
    private const SPALTENRECHTE = [
        'mitarbeiter' => ['UPDATE' => ['rfid_code']],
    ];

    private static ?TerminalDbBenutzerService $instanz = null;

    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstanz();
    }

    public static function getInstanz(): TerminalDbBenutzerService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Legt den Datenbankbenutzer fuer ein Terminal an.
     *
     * Ein vorhandener Benutzer desselben Terminals wird dabei **entfernt**, statt
     * einen zweiten anzulegen: Wird ein Geraet neu gekoppelt (Austausch,
     * Neuinstallation), soll danach genau ein gueltiger Zugang existieren.
     *
     * @param string|null $alterBenutzer Benutzer aus der vorherigen Kopplung, falls vorhanden
     * @param string|null $alterHost     zugehoeriger Host der vorherigen Kopplung
     *
     * @return array{benutzer:string,passwort:string,host:string,dbname:string}|null
     *         Zugangsdaten - das Passwort ist hier **einmalig** im Klartext
     *         verfuegbar und wird nirgends gespeichert. Null bei Fehler.
     */
    public function legeAnOderErsetze(
        int $terminalId,
        string $terminalName,
        ?string $alterBenutzer = null,
        ?string $alterHost = null
    ): ?array {
        if ($terminalId <= 0) {
            return null;
        }

        $dbname = $this->holeDatenbankName();
        if ($dbname === null) {
            $this->protokolliere('error', 'Terminal-Datenbankbenutzer: Schemaname nicht ermittelbar', [
                'terminal_id' => $terminalId,
            ]);
            return null;
        }

        $benutzer = $this->benutzernameFuer($terminalId, $terminalName);
        $host     = $this->holeHostMuster();
        $passwort = $this->wuerflePasswort();

        if (!$this->istBenutzernameGueltig($benutzer) || !$this->istHostGueltig($host)) {
            $this->protokolliere('error', 'Terminal-Datenbankbenutzer: Name oder Host unzulaessig', [
                'terminal_id' => $terminalId,
                'benutzer'    => $benutzer,
                'host'        => $host,
            ]);
            return null;
        }

        try {
            $pdo = $this->db->getVerbindung();

            // Alten Zugang der vorherigen Kopplung entfernen, sofern bekannt und
            // nicht identisch mit dem neuen (sonst zieht man sich den Benutzer
            // gleich wieder unter den Fuessen weg).
            if (is_string($alterBenutzer) && $alterBenutzer !== '') {
                $alterHostWert = (is_string($alterHost) && $alterHost !== '') ? $alterHost : $host;
                if ($alterBenutzer !== $benutzer || $alterHostWert !== $host) {
                    $this->entferne($alterBenutzer, $alterHostWert);
                }
            }

            // Bereits vorhandenen gleichnamigen Benutzer verwerfen: Nur so
            // bekommt das Geraet garantiert das Passwort, das ihm gerade
            // geantwortet wird.
            $pdo->exec(sprintf('DROP USER IF EXISTS %s', $this->quoteBenutzer($benutzer, $host)));

            $pdo->exec(sprintf(
                'CREATE USER %s IDENTIFIED BY %s',
                $this->quoteBenutzer($benutzer, $host),
                $this->quoteText($passwort)
            ));

            foreach ($this->baueGrantAnweisungen($benutzer, $host, $dbname) as $anweisung) {
                $pdo->exec($anweisung);
            }
        } catch (\Throwable $e) {
            // Halbfertigen Benutzer nicht stehen lassen - ein Zugang ohne
            // vollstaendige Rechte waere schlimmer als gar keiner, weil das
            // Terminal dann sporadisch scheitert statt sofort.
            try {
                $this->entferne($benutzer, $host);
            } catch (\Throwable $ignore) {
                // bewusst still: der eigentliche Fehler steht unten im Protokoll
            }

            $this->protokolliere('error', 'Terminal-Datenbankbenutzer konnte nicht angelegt werden', [
                'terminal_id' => $terminalId,
                'benutzer'    => $benutzer,
                'host'        => $host,
                'exception'   => $e->getMessage(),
            ]);

            return null;
        }

        $this->protokolliere('info', 'Terminal-Datenbankbenutzer angelegt', [
            'terminal_id' => $terminalId,
            'benutzer'    => $benutzer,
            'host'        => $host,
        ]);

        return [
            'benutzer' => $benutzer,
            'passwort' => $passwort,
            'host'     => $host,
            'dbname'   => $dbname,
        ];
    }

    /**
     * Entfernt einen Terminal-Datenbankbenutzer (z. B. wenn ein Geraet
     * ausgemustert wird oder eine Kopplung fehlschlaegt).
     */
    public function entferne(string $benutzer, string $host): bool
    {
        if (!$this->istBenutzernameGueltig($benutzer) || !$this->istHostGueltig($host)) {
            return false;
        }

        try {
            $this->db->getVerbindung()->exec(
                sprintf('DROP USER IF EXISTS %s', $this->quoteBenutzer($benutzer, $host))
            );
        } catch (\Throwable $e) {
            $this->protokolliere('error', 'Terminal-Datenbankbenutzer konnte nicht entfernt werden', [
                'benutzer'  => $benutzer,
                'host'      => $host,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }

        $this->protokolliere('info', 'Terminal-Datenbankbenutzer entfernt', [
            'benutzer' => $benutzer,
            'host'     => $host,
        ]);

        return true;
    }

    /**
     * Prueft vorab, ob der Datenbankbenutzer des Backends ueberhaupt Benutzer
     * anlegen darf.
     *
     * Das ist bewusst nur eine Vorpruefung fuer eine verstaendliche Meldung -
     * massgeblich ist, was die Datenbank beim Ausfuehren sagt.
     */
    public function istVerfuegbar(): bool
    {
        try {
            $stmt = $this->db->getVerbindung()->query('SHOW GRANTS FOR CURRENT_USER()');
            if ($stmt === false) {
                return false;
            }

            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $zeile) {
                $grant = strtoupper((string)($zeile[0] ?? ''));

                // `CREATE USER` ist ein globales Recht und steht deshalb immer
                // in einer Zeile mit `ON *.*`.
                if (!str_contains($grant, ' ON *.*')) {
                    continue;
                }

                if (str_contains($grant, 'ALL PRIVILEGES') || str_contains($grant, 'CREATE USER')) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Leitet den Benutzernamen aus Terminal-Name und -ID ab.
     *
     * Die ID haengt bewusst hinten dran: Zwei Terminals duerfen gleich heissen,
     * zwei Datenbankbenutzer nicht.
     */
    public function benutzernameFuer(int $terminalId, string $terminalName): string
    {
        $suffix = '_' . $terminalId;
        $rest   = self::BENUTZER_MAX_LAENGE - strlen('term_') - strlen($suffix);

        $slug = $this->slug($terminalName);
        if ($slug === '') {
            $slug = 'terminal';
        }

        if ($rest < 1) {
            // Nur bei absurd hohen IDs; dann bleibt der Name eben kurz.
            return 'term' . $suffix;
        }

        return 'term_' . substr($slug, 0, $rest) . $suffix;
    }

    // ------------------------------------------------------------------
    // Interna
    // ------------------------------------------------------------------

    /**
     * Baut die GRANT-Anweisungen aus der Rechtetabelle.
     *
     * @return array<int,string>
     */
    private function baueGrantAnweisungen(string $benutzer, string $host, string $dbname): array
    {
        $ziel        = $this->quoteBenutzer($benutzer, $host);
        $anweisungen = [];

        foreach (self::RECHTE as $tabelle => $rechte) {
            if (!$this->istBezeichnerGueltig($tabelle)) {
                continue;
            }

            $anweisungen[] = sprintf(
                'GRANT %s ON `%s`.`%s` TO %s',
                $rechte,
                $dbname,
                $tabelle,
                $ziel
            );
        }

        foreach (self::SPALTENRECHTE as $tabelle => $rechteJeSpalte) {
            if (!$this->istBezeichnerGueltig($tabelle)) {
                continue;
            }

            foreach ($rechteJeSpalte as $recht => $spalten) {
                $geprueft = [];
                foreach ($spalten as $spalte) {
                    if ($this->istBezeichnerGueltig($spalte)) {
                        $geprueft[] = '`' . $spalte . '`';
                    }
                }

                if ($geprueft === []) {
                    continue;
                }

                $anweisungen[] = sprintf(
                    'GRANT %s (%s) ON `%s`.`%s` TO %s',
                    $recht,
                    implode(', ', $geprueft),
                    $dbname,
                    $tabelle,
                    $ziel
                );
            }
        }

        return $anweisungen;
    }

    /**
     * Schema, in dem die Anwendung arbeitet.
     *
     * Bewusst aus der Verbindung erfragt und nicht aus der Konfiguration
     * gelesen: So kann der Name nicht auseinanderlaufen.
     */
    private function holeDatenbankName(): ?string
    {
        try {
            $stmt = $this->db->getVerbindung()->query('SELECT DATABASE()');
            if ($stmt === false) {
                return null;
            }

            $name = (string)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return null;
        }

        return $this->istBezeichnerGueltig($name) ? $name : null;
    }

    /**
     * Von welchen Rechnern aus der Terminal-Benutzer sich verbinden darf.
     *
     * Standard ist `%` (beliebiger Rechner). Das ist eine bewusste Abwaegung:
     * Terminals bekommen ihre Adresse per DHCP, eine feste Bindung wuerde beim
     * naechsten Neustart stillschweigend den Zugang kappen. Wer sein Netz kennt,
     * traegt in der `config` ein engeres Muster ein (z. B. `192.168.10.%`).
     */
    private function holeHostMuster(): string
    {
        try {
            if (class_exists('ConfigService')) {
                $wert = ConfigService::getInstanz()->get('terminal_db_host_muster', self::STANDARD_HOST);
                if (is_string($wert) && trim($wert) !== '') {
                    $wert = trim($wert);
                    if ($this->istHostGueltig($wert)) {
                        return $wert;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback unten
        }

        return self::STANDARD_HOST;
    }

    private function wuerflePasswort(): string
    {
        $laenge   = strlen(self::PASSWORT_ALPHABET);
        $passwort = '';

        for ($i = 0; $i < self::PASSWORT_LAENGE; $i++) {
            try {
                $index = random_int(0, $laenge - 1);
            } catch (\Throwable $e) {
                $index = mt_rand(0, $laenge - 1);
            }
            $passwort .= self::PASSWORT_ALPHABET[$index];
        }

        return $passwort;
    }

    /**
     * Macht aus „Halle 1 – Fräserei“ ein `halle_1_fraeserei`.
     */
    private function slug(string $text): string
    {
        $text = strtr($text, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
            'ß' => 'ss',
        ]);

        $text = strtolower($text);
        $text = (string)preg_replace('~[^a-z0-9]+~', '_', $text);

        return trim($text, '_');
    }

    private function istBenutzernameGueltig(string $benutzer): bool
    {
        return (bool)preg_match('~^[a-z0-9_]{1,' . self::BENUTZER_MAX_LAENGE . '}$~', $benutzer);
    }

    /**
     * Host-Muster im MySQL-Sinn: Name, IP oder Platzhalter wie `192.168.10.%`.
     */
    private function istHostGueltig(string $host): bool
    {
        return (bool)preg_match('~^[A-Za-z0-9_.%:-]{1,60}$~', $host);
    }

    /**
     * Schema-, Tabellen- und Spaltennamen.
     */
    private function istBezeichnerGueltig(string $name): bool
    {
        return (bool)preg_match('~^[A-Za-z0-9_]{1,64}$~', $name);
    }

    /**
     * `'benutzer'@'host'` - beide Teile sind vorher geprueft, es kann also
     * nichts aus dem Muster ausbrechen.
     */
    private function quoteBenutzer(string $benutzer, string $host): string
    {
        return "'" . $benutzer . "'@'" . $host . "'";
    }

    /**
     * Textliteral fuer das Passwort. Das Alphabet enthaelt ausschliesslich
     * Buchstaben und Ziffern; trotzdem wird hier geprueft statt vertraut.
     */
    private function quoteText(string $text): string
    {
        if (!preg_match('~^[A-Za-z0-9]+$~', $text)) {
            throw new \RuntimeException('Unerwartetes Zeichen im erzeugten Passwort.');
        }

        return "'" . $text . "'";
    }

    /**
     * @param array<string,mixed> $kontext
     */
    private function protokolliere(string $stufe, string $nachricht, array $kontext): void
    {
        if (!class_exists('Logger')) {
            return;
        }

        // Sicherheitshalber: In diesem Dienst darf niemals ein Passwort ins
        // Protokoll geraten.
        unset($kontext['passwort']);

        $terminalId = isset($kontext['terminal_id']) ? (int)$kontext['terminal_id'] : null;
        if ($terminalId !== null && $terminalId <= 0) {
            $terminalId = null;
        }

        switch ($stufe) {
            case 'error':
                Logger::error($nachricht, $kontext, null, $terminalId, 'terminal_kopplung');
                break;
            case 'warn':
                Logger::warn($nachricht, $kontext, null, $terminalId, 'terminal_kopplung');
                break;
            default:
                Logger::info($nachricht, $kontext, null, $terminalId, 'terminal_kopplung');
        }
    }
}
