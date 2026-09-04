<?php
declare(strict_types=1);

/**
 * Die Verbindung zum Mitarbeiterportal auf der WERNIG-Homepage.
 *
 * DIESE SEITE RUFT AN. Das ist keine Geschmacksfrage, sondern die Folge der
 * Lage: Diese Installation steht im Firmennetz und hat keine von aussen
 * erreichbare Adresse. Die Homepage kann hier nichts abfragen und nichts
 * anstossen - sie kann nur antworten, wenn dieser Dienst sie fragt
 * (docs/spezifikation_mitarbeiterportal.md, Abschnitt 2).
 *
 * Der Dienst kuemmert sich um **eine** Sache: dass ein Aufruf ankommt und
 * richtig unterschrieben ist. Was in dem Aufruf steht - Urlaubsantraege,
 * Salden, Monatslisten -, entscheidet der `PortalSyncService`.
 *
 * ZWEI ZAHLEN, DIE HIER WICHTIGER SIND ALS SIE AUSSEHEN: Verbindungs- und
 * Gesamtzeitlimit. Dieselbe Lehre wie beim Terminal (P-2026-08-16-08): »Nicht
 * erreichbar« heisst selten »Verbindung abgelehnt« - meistens kommt gar nichts
 * zurueck. Ohne Zeitlimit wartet PHP dann bis zum Ende der Skriptlaufzeit, und
 * ein Abgleich, der alle zwei Minuten startet, staut sich auf.
 */
class PortalVerbindungService
{
    /** So lange darf der Verbindungsaufbau dauern. */
    private const TIMEOUT_VERBINDUNG = 5;

    /**
     * So lange darf ein ganzer Aufruf dauern.
     *
     * Grosszuegiger als der Verbindungsaufbau, weil in einer Antwort ein
     * fertiges Monats-PDF stecken kann - aber deutlich unter dem Takt, in dem
     * der Abgleich startet.
     */
    private const TIMEOUT_GESAMT = 45;

    /** Der Pfad des Endpunkts auf der Homepage. Bezeichner, deshalb ASCII. */
    public const ENDPUNKT = '/portal-api';

    private static ?PortalVerbindungService $instanz = null;

    private Database $datenbank;

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    public static function getInstanz(): PortalVerbindungService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }
        return self::$instanz;
    }

    // -------------------------------------------------------- Verbindung

    /** Die aktive Verbindung oder null. */
    public function verbindung(): ?array
    {
        return $this->datenbank->fetchEine(
            'SELECT * FROM portal_verbindung WHERE aktiv = 1 ORDER BY id DESC LIMIT 1'
        );
    }

    public function istGekoppelt(): bool
    {
        return $this->verbindung() !== null;
    }

    /**
     * Loest den Handschlag aus: Kopplungscode gegen Schluessel tauschen.
     *
     * @return array{ok:bool, meldung:string}
     */
    public function koppeln(string $basisUrl, string $code): array
    {
        $basis = $this->normalisiereBasis($basisUrl);
        if ($basis === null) {
            return ['ok' => false, 'meldung' =>
                'Die Adresse der Website ist unvollständig. Erwartet wird etwas wie '
                . 'https://wernig.com – mit https:// davor und ohne Pfad dahinter.'];
        }

        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'meldung' => 'Es wurde kein Kopplungscode eingegeben.'];
        }

        $antwort = $this->sendeRoh($basis, ['aktion' => 'koppeln', 'code' => $code], []);

        if (!$antwort['erreichbar']) {
            return ['ok' => false, 'meldung' =>
                'Die Website ist nicht erreichbar: ' . $antwort['fehler']
                . ' Stimmt die Adresse, und hat dieser Server überhaupt einen Weg ins Internet?'];
        }
        if (!is_array($antwort['daten']) || ($antwort['daten']['ok'] ?? false) !== true) {
            return ['ok' => false, 'meldung' =>
                $this->fehlertextAus($antwort, 'Die Website hat die Kopplung abgelehnt.')];
        }

        $portalId   = (string)($antwort['daten']['portal_id'] ?? '');
        $schluessel = (string)($antwort['daten']['schluessel'] ?? '');
        if ($portalId === '' || $schluessel === '') {
            return ['ok' => false, 'meldung' =>
                'Die Website hat zwar zugestimmt, aber keinen Schlüssel mitgeschickt. '
                . 'Das darf nicht vorkommen – bitte die Programmfassung der Website prüfen.'];
        }

        // Eine neue Kopplung loest die alte ab, genau wie drueben.
        $this->datenbank->ausfuehren(
            'UPDATE portal_verbindung SET aktiv = 0, entkoppelt_am = NOW() WHERE aktiv = 1'
        );
        $this->datenbank->ausfuehren(
            'INSERT INTO portal_verbindung (basis_url, portal_id, schluessel, aktiv)
             VALUES (:u, :p, :s, 1)',
            [':u' => $basis, ':p' => $portalId, ':s' => $schluessel]
        );

        Logger::info('Mitarbeiterportal gekoppelt', [
            'basis_url' => $basis,
            'portal_id' => $portalId,
        ], null, null, 'portal');

        // Gleich nachfassen: Eine Kopplung, deren zweite Haelfte erst beim
        // ersten Abgleich scheitert, waere schwer zu deuten.
        $probe = $this->probe();
        if (!$probe['ok']) {
            return ['ok' => true, 'meldung' =>
                'Gekoppelt – aber der erste unterschriebene Aufruf ist gescheitert: '
                . $probe['meldung']];
        }

        return ['ok' => true, 'meldung' => 'Gekoppelt, und der erste unterschriebene Aufruf kam an.'];
    }

    /**
     * Ein Lebenszeichen zur Homepage.
     *
     * @return array{ok:bool, meldung:string}
     */
    public function probe(): array
    {
        $antwort = $this->sende('hallo', []);
        if ($antwort['ok']) {
            return ['ok' => true, 'meldung' => 'Die Website antwortet und die Signatur stimmt.'];
        }
        return ['ok' => false, 'meldung' => $antwort['meldung']];
    }

    /**
     * Trennt die Verbindung auf dieser Seite.
     *
     * Die Homepage erfaehrt davon nichts - sie kann ja nicht angerufen werden,
     * um es ihr zu sagen, und ein »bitte vergiss mich« liesse sich ohnehin
     * faelschen. Sie merkt es daran, dass niemand mehr anruft, und dort wird
     * getrennt, wenn es endgueltig sein soll. **Das ist wichtig zu wissen:**
     * Der Spiegel auf dem oeffentlichen Server bleibt stehen, bis dort jemand
     * »Verbindung trennen« drueckt.
     */
    public function entkoppeln(): void
    {
        $this->datenbank->ausfuehren(
            'UPDATE portal_verbindung SET aktiv = 0, entkoppelt_am = NOW() WHERE aktiv = 1'
        );
        Logger::info('Mitarbeiterportal entkoppelt', [], null, null, 'portal');
    }

    /** Haelt fest, wie der letzte Abgleich ausging - fuer die Maske. */
    public function laufVermerken(bool $ok, ?string $fehler, int $dauerMs): void
    {
        $this->datenbank->ausfuehren(
            'UPDATE portal_verbindung
                SET letzter_lauf_am = NOW(), letzter_lauf_ok = :ok,
                    letzter_fehler = :f, letzte_dauer_ms = :d
              WHERE aktiv = 1',
            [
                ':ok' => $ok ? 1 : 0,
                // Beim Erfolg wird der alte Fehler geloescht, sonst stuende
                // dort auf ewig ein Satz, der nicht mehr gilt.
                ':f'  => $ok ? null : mb_substr((string)$fehler, 0, 2000),
                ':d'  => $dauerMs,
            ]
        );
    }

    // ------------------------------------------------------- Aufrufe

    /**
     * Ein unterschriebener Aufruf an die Homepage.
     *
     * @param array<string,mixed> $daten wird um `aktion` ergaenzt
     * @return array{ok:bool, meldung:string, daten:array|null, status:int}
     */
    public function sende(string $aktion, array $daten): array
    {
        $verbindung = $this->verbindung();
        if ($verbindung === null) {
            return ['ok' => false, 'status' => 0, 'daten' => null,
                    'meldung' => 'Es ist keine Website gekoppelt.'];
        }

        $inhalt = array_merge($daten, ['aktion' => $aktion]);
        $koerper = json_encode($inhalt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($koerper === false) {
            return ['ok' => false, 'status' => 0, 'daten' => null,
                    'meldung' => 'Der Aufruf ließ sich nicht als JSON verpacken.'];
        }

        $portalId = (string)$verbindung['portal_id'];
        $zeit     = time();
        $nonce    = bin2hex(random_bytes(16));

        // Unterschrieben wird mit sha256(schluessel), nicht mit dem
        // Schluessel selbst - so steht drueben nicht derselbe Wert in der
        // Datenbank, den wir hier im Klartext halten (Vertrag, Abschnitt 5).
        $signatur = hash_hmac(
            'sha256',
            $portalId . "\n" . $zeit . "\n" . $nonce . "\n" . $koerper,
            hash('sha256', (string)$verbindung['schluessel'])
        );

        $antwort = $this->sendeRoh((string)$verbindung['basis_url'], $inhalt, [
            'X-Portal-Id: ' . $portalId,
            'X-Portal-Zeit: ' . $zeit,
            'X-Portal-Nonce: ' . $nonce,
            'X-Portal-Signatur: ' . $signatur,
        ], $koerper);

        if (!$antwort['erreichbar']) {
            return ['ok' => false, 'status' => 0, 'daten' => null,
                    'meldung' => 'Die Website ist nicht erreichbar: ' . $antwort['fehler']];
        }
        if (!is_array($antwort['daten']) || ($antwort['daten']['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => $antwort['status'], 'daten' => $antwort['daten'],
                    'meldung' => $this->fehlertextAus($antwort, 'Die Website hat den Aufruf abgelehnt.')];
        }

        return ['ok' => true, 'status' => $antwort['status'], 'daten' => $antwort['daten'], 'meldung' => ''];
    }

    /**
     * Der eigentliche HTTP-Aufruf, ohne jedes Wissen ueber Fachliches.
     *
     * @param array<int,string> $kopfzeilen
     * @return array{erreichbar:bool, fehler:string, status:int, daten:mixed, roh:string}
     */
    private function sendeRoh(string $basis, array $inhalt, array $kopfzeilen, ?string $koerper = null): array
    {
        $koerper = $koerper ?? (string)json_encode($inhalt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init(rtrim($basis, '/') . self::ENDPUNKT);
        if ($ch === false) {
            return ['erreichbar' => false, 'fehler' => 'curl steht nicht zur Verfügung.',
                    'status' => 0, 'daten' => null, 'roh' => ''];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $koerper,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $kopfzeilen),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_VERBINDUNG,
            CURLOPT_TIMEOUT        => self::TIMEOUT_GESAMT,
            // Weiterleitungen ausdruecklich NICHT folgen: Eine Umleitung
            // wuerde die Unterschrift an eine Adresse tragen, die wir nicht
            // gemeint haben. Wer von http auf https umleitet, soll die
            // richtige Adresse eintragen, nicht heimlich umgebogen werden.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'WERNIG-Zeiterfassung Mitarbeiterportal',
        ]);

        $roh    = curl_exec($ch);
        $fehler = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($roh === false) {
            return ['erreichbar' => false, 'fehler' => $fehler !== '' ? $fehler : 'unbekannter Fehler',
                    'status' => 0, 'daten' => null, 'roh' => ''];
        }

        return [
            'erreichbar' => true,
            'fehler'     => '',
            'status'     => $status,
            'daten'      => json_decode((string)$roh, true),
            'roh'        => (string)$roh,
        ];
    }

    /**
     * Macht aus einer abgelehnten Antwort einen Satz, der weiterhilft.
     *
     * Die Homepage schickt ihren Grund im Klartext mit; steht dort nichts,
     * bleibt wenigstens der Statuscode - und bei einer Antwort, die gar kein
     * JSON war, der Anfang des Textes. Genau der ist im Ernstfall die
     * wichtigste Auskunft: Eine HTML-Fehlerseite eines Reverse Proxy sieht
     * sonst aus wie »die Website hat abgelehnt«.
     */
    private function fehlertextAus(array $antwort, string $rueckfall): string
    {
        if (is_array($antwort['daten']) && ($antwort['daten']['fehler'] ?? '') !== '') {
            return (string)$antwort['daten']['fehler'];
        }
        $text = $rueckfall . ' Antwort: HTTP ' . $antwort['status'];
        $roh = trim((string)($antwort['roh'] ?? ''));
        if ($roh !== '' && !is_array($antwort['daten'])) {
            $text .= ', und der Inhalt war kein JSON: ' . mb_substr(strip_tags($roh), 0, 200);
        }
        return $text;
    }

    /**
     * Prueft und normalisiert die eingegebene Adresse.
     *
     * Rueckgabe ohne Pfad und ohne Schrägstrich am Ende - der Endpunkt wird
     * angehaengt. Wer versehentlich `https://wernig.com/portal-api` eintraegt,
     * bekommt sonst `/portal-api/portal-api`.
     */
    private function normalisiereBasis(string $eingabe): ?string
    {
        $eingabe = trim($eingabe);
        if ($eingabe === '') {
            return null;
        }
        // Ohne Schema ist es keine Adresse, sondern eine Vermutung. Wir raten
        // hier nicht: `http` waere unverschluesselt, `https` waere geraten.
        if (!preg_match('#^https?://#i', $eingabe)) {
            return null;
        }
        $teile = parse_url($eingabe);
        if (!is_array($teile) || ($teile['host'] ?? '') === '') {
            return null;
        }
        $basis = strtolower($teile['scheme']) . '://' . $teile['host'];
        if (isset($teile['port'])) {
            $basis .= ':' . (int)$teile['port'];
        }
        return $basis;
    }
}
