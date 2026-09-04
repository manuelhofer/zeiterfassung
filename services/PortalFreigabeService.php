<?php
declare(strict_types=1);

/**
 * Wer darf ins Mitarbeiterportal, unter welcher Kennung, und mit welchem
 * Aktivierungscode.
 *
 * DIE FREISCHALTUNG IST DIE EIGENTLICHE ENTSCHEIDUNG. Sie bestimmt, welche
 * Personendaten das Haus verlassen: Ein freigeschalteter Mitarbeiter steht mit
 * Namen, Urlaubszahlen und Stundensaldo auf einem Server im Internet, ein
 * nicht freigeschalteter mit keinem Byte. Deshalb ist sie ausdruecklich und
 * je Person zu treffen - es gibt bewusst kein »alle freischalten«
 * (docs/spezifikation_mitarbeiterportal.md, Abschnitt 10).
 *
 * DER AKTIVIERUNGSCODE liegt hier wie ueberall nur als Hash. Er wird einmal
 * gedruckt und ist danach nicht mehr abrufbar; wer ihn verliert, bekommt einen
 * neuen. Dass ihn niemand nachschlagen kann - auch der Chef nicht - ist der
 * Punkt: Sonst waere er kein Nachweis, sondern eine Notiz.
 */
class PortalFreigabeService
{
    /**
     * Alphabet des Aktivierungscodes - ohne I, L, O, 0 und 1.
     *
     * Er wird von einem Zettel abgetippt, oft auf einem Handy mit
     * Autokorrektur. Ein O, das eine Null sein koennte, kostet einen Anruf.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Zwoelf Zeichen in drei Vierergruppen.
     *
     * Deutlich laenger als ein Kopplungscode, weil er auch deutlich laenger
     * gilt: 14 Tage statt 30 Minuten. Ueber diese Zeit muss er dem Raten
     * standhalten, und zwar allein - eine Fehlversuchssperre gibt es auf der
     * Homepage zwar, aber sie zaehlt je Absender und nicht je Code.
     */
    private const CODE_LAENGE = 12;

    /**
     * So lange gilt ein Aktivierungscode.
     *
     * Vierzehn Tage, weil ein Zettel im Urlaub liegen bleibt, hinter dem
     * Spind verschwindet oder erst am Montag ankommt. Kuerzer waere sicherer
     * und wuerde vor allem eines erzeugen: Anrufe.
     */
    public const CODE_GUELTIG_TAGE = 14;

    private static ?PortalFreigabeService $instanz = null;

    private Database $datenbank;

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    public static function getInstanz(): PortalFreigabeService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }
        return self::$instanz;
    }

    /**
     * Alle aktiven Mitarbeiter mit ihrem Portal-Zustand.
     *
     * @return array<int,array<string,mixed>>
     */
    public function liste(): array
    {
        return $this->datenbank->fetchAlle(
            'SELECT id, vorname, nachname, personalnummer, benutzername,
                    portal_aktiv, portal_kennung, portal_aktivierung_hash,
                    portal_aktivierung_bis, portal_aktiviert_am, portal_letzte_anmeldung_am
               FROM mitarbeiter
              WHERE aktiv = 1
              ORDER BY nachname, vorname'
        );
    }

    /** @return array<string,mixed>|null */
    public function holeMitarbeiter(int $id): ?array
    {
        return $this->datenbank->fetchEine(
            'SELECT * FROM mitarbeiter WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Schaltet einen Mitarbeiter frei und vergibt dabei die Kennung.
     *
     * @return array{ok:bool, meldung:string}
     */
    public function freischalten(int $id, string $kennungEingabe): array
    {
        $mitarbeiter = $this->holeMitarbeiter($id);
        if ($mitarbeiter === null || (int)$mitarbeiter['aktiv'] !== 1) {
            return ['ok' => false, 'meldung' => 'Diesen Mitarbeiter gibt es nicht oder er ist stillgelegt.'];
        }

        $kennung = $this->normalisiereKennung($kennungEingabe);
        if ($kennung === '') {
            $kennung = $this->vorschlagKennung($mitarbeiter);
        }
        if ($kennung === '') {
            return ['ok' => false, 'meldung' =>
                'Für diesen Mitarbeiter lässt sich keine Kennung bilden. Bitte eine von Hand eintragen.'];
        }
        if (mb_strlen($kennung) < 3) {
            return ['ok' => false, 'meldung' =>
                'Die Kennung ist zu kurz – mindestens drei Zeichen, sonst rät man sie beim Anmelden.'];
        }

        $belegt = $this->datenbank->fetchEine(
            'SELECT id FROM mitarbeiter WHERE portal_kennung = :k AND id <> :id LIMIT 1',
            [':k' => $kennung, ':id' => $id]
        );
        if ($belegt !== null) {
            return ['ok' => false, 'meldung' =>
                'Die Kennung »' . $kennung . '« ist schon vergeben. Zwei Mitarbeiter mit derselben '
                . 'Kennung könnten sich nicht auseinanderhalten – bitte eine andere wählen.'];
        }

        $this->datenbank->ausfuehren(
            'UPDATE mitarbeiter SET portal_aktiv = 1, portal_kennung = :k WHERE id = :id',
            [':k' => $kennung, ':id' => $id]
        );

        Logger::info('Mitarbeiter für das Portal freigeschaltet', [
            'mitarbeiter_id' => $id,
            'kennung'        => $kennung,
        ], null, null, 'portal');

        return ['ok' => true, 'meldung' =>
            'Freigeschaltet unter der Kennung »' . $kennung . '«. Der Mitarbeiter steht nach dem '
            . 'nächsten Abgleich auf der Website – anmelden kann er sich erst mit einem '
            . 'Aktivierungscode.'];
    }

    /**
     * Nimmt die Freischaltung zurueck.
     *
     * Ein halber Entzug waere schlimmer als keiner: Der Aktivierungscode wird
     * mit entwertet. Sonst bliebe ein gedruckter Zettel gueltig, mit dem sich
     * jemand anmelden koennte, sobald die Freischaltung je wieder gesetzt wird.
     */
    public function sperren(int $id): array
    {
        $this->datenbank->ausfuehren(
            'UPDATE mitarbeiter
                SET portal_aktiv = 0,
                    portal_aktivierung_hash = NULL,
                    portal_aktivierung_bis = NULL
              WHERE id = :id',
            [':id' => $id]
        );

        Logger::info('Portal-Freischaltung entzogen', ['mitarbeiter_id' => $id], null, null, 'portal');

        return ['ok' => true, 'meldung' =>
            'Die Freischaltung ist entzogen und ein offener Aktivierungscode entwertet. Beim nächsten '
            . 'Abgleich verschwindet der Mitarbeiter samt Konto, Zahlen und Anträgen von der Website.'];
    }

    /**
     * Erzeugt einen Aktivierungscode und gibt ihn **einmalig** im Klartext zurueck.
     *
     * @return array{ok:bool, meldung:string, code:string}
     */
    public function aktivierungscodeErzeugen(int $id): array
    {
        $mitarbeiter = $this->holeMitarbeiter($id);
        if ($mitarbeiter === null || (int)$mitarbeiter['portal_aktiv'] !== 1) {
            return ['ok' => false, 'code' => '', 'meldung' =>
                'Für diesen Mitarbeiter ist das Portal nicht freigeschaltet.'];
        }
        if ((string)($mitarbeiter['portal_kennung'] ?? '') === '') {
            return ['ok' => false, 'code' => '', 'meldung' =>
                'Dieser Mitarbeiter hat keine Kennung. Ohne sie kann er sich nicht anmelden.'];
        }

        $code = $this->wuerfleCode();

        $this->datenbank->ausfuehren(
            'UPDATE mitarbeiter
                SET portal_aktivierung_hash = :h,
                    portal_aktivierung_bis  = DATE_ADD(NOW(), INTERVAL :tage DAY)
              WHERE id = :id',
            [':h' => hash('sha256', $code), ':tage' => self::CODE_GUELTIG_TAGE, ':id' => $id]
        );

        Logger::info('Aktivierungscode für das Portal erzeugt', [
            'mitarbeiter_id' => $id,
            'gueltig_tage'   => self::CODE_GUELTIG_TAGE,
        ], null, null, 'portal');

        return ['ok' => true, 'code' => $code, 'meldung' =>
            'Der Code gilt ' . self::CODE_GUELTIG_TAGE . ' Tage und lässt sich einmal einlösen. '
            . 'Er ist ab dem nächsten Abgleich auf der Website gültig.'];
    }

    /**
     * Schlaegt eine Kennung vor.
     *
     * Reihenfolge mit Absicht: Personalnummer, wenn es eine gibt - sie ist im
     * Haus die uebliche Kennzeichnung. Sonst `vorname.nachname`, weil das
     * jeder ohne Nachfrage weiss. Als Letztes `m<id>`, damit auch ein
     * Namensvetter oder ein Name aus Zeichen ausserhalb des Alphabets eine
     * Kennung bekommt.
     */
    public function vorschlagKennung(array $mitarbeiter): string
    {
        $personalnummer = $this->normalisiereKennung((string)($mitarbeiter['personalnummer'] ?? ''));
        if ($personalnummer !== '' && mb_strlen($personalnummer) >= 3 && !$this->kennungBelegt($personalnummer, (int)$mitarbeiter['id'])) {
            return $personalnummer;
        }

        $name = $this->normalisiereKennung(
            $this->asciiForm((string)($mitarbeiter['vorname'] ?? '')) . '.'
            . $this->asciiForm((string)($mitarbeiter['nachname'] ?? ''))
        );
        if ($name !== '' && $name !== '.' && mb_strlen($name) >= 3 && !$this->kennungBelegt($name, (int)$mitarbeiter['id'])) {
            return $name;
        }

        return 'm' . (int)($mitarbeiter['id'] ?? 0);
    }

    // --------------------------------------------------------------- intern

    private function kennungBelegt(string $kennung, int $ausserId): bool
    {
        return $this->datenbank->fetchEine(
            'SELECT id FROM mitarbeiter WHERE portal_kennung = :k AND id <> :id LIMIT 1',
            [':k' => $kennung, ':id' => $ausserId]
        ) !== null;
    }

    /**
     * Kleinschreibung, ASCII, nur Buchstaben, Ziffern, Punkt und Bindestrich.
     *
     * Die Kennung wird auf einem Handy getippt, in einem Feld ohne
     * Sonderzeichenhilfe. Ein Umlaut oder ein Leerzeichen darin waere eine
     * Fehlerquelle ohne jeden Gewinn.
     */
    private function normalisiereKennung(string $eingabe): string
    {
        $wert = mb_strtolower(trim($eingabe));
        $wert = $this->asciiForm($wert);
        $wert = preg_replace('/[^a-z0-9.\-]/', '', $wert) ?? '';
        // Fuehrende und schliessende Punkte entstehen aus leeren Namensteilen.
        return trim($wert, '.-');
    }

    /** Umlaute und ß ausschreiben, alles andere ohne Akzent. */
    private function asciiForm(string $wert): string
    {
        $wert = strtr(mb_strtolower($wert), [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y',
        ]);
        // Was danach noch kein ASCII ist, faellt weg - dafuer gibt es `m<id>`.
        return preg_replace('/[^\x20-\x7E]/', '', $wert) ?? '';
    }

    private function wuerfleCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::CODE_LAENGE; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }

    /** Nur fuer die Anzeige: drei Vierergruppen, damit man ihn vorlesen kann. */
    public static function codeLesbar(string $code): string
    {
        return trim(chunk_split($code, 4, ' '));
    }
}
