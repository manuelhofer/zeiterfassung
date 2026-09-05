<?php
declare(strict_types=1);

/**
 * PortalAdminController
 *
 * Backend-Maske »Mitarbeiterportal« (`?seite=portal_admin`): Die Homepage als
 * Portal koppeln, den Stand der Verbindung sehen, sie wieder trennen.
 *
 * Hier faengt der Handschlag an. Auf der Homepage wird ein Kopplungscode
 * erzeugt, hier werden Adresse und Code eingetragen - und ab dann ruft diese
 * Installation dort an. Umgekehrt geht nichts: Die Homepage kennt weder die
 * Adresse dieses Servers noch seine Datenbank
 * (docs/spezifikation_mitarbeiterportal.md, Abschnitt 2).
 *
 * Recht: `PORTAL_VERWALTEN`. Kein Rueckfall auf Rollennamen wie bei der
 * Terminalverwaltung - das Recht ist neu, es gibt keine Altinstallation, die
 * es noch nicht kennt.
 */
class PortalAdminController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'portal_admin';

    private const FLASH_OK_KEY  = 'portal_admin_flash_ok';
    private const FLASH_ERR_KEY = 'portal_admin_flash_err';
    /** Der frische Aktivierungscode - steht genau einmal in der Sitzung. */
    private const FLASH_ZETTEL_KEY = 'portal_admin_flash_zettel';

    private AuthService $authService;
    private Database $datenbank;
    private PortalVerbindungService $portal;
    private PortalFreigabeService $freigabe;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank   = Database::getInstanz();
        $this->portal      = PortalVerbindungService::getInstanz();
        $this->freigabe    = PortalFreigabeService::getInstanz();
    }

    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (Helper::istPost()) {
            $this->verarbeitePost();
            return;
        }

        $verbindung = $this->portal->verbindung();

        $flashOk  = $_SESSION[self::FLASH_OK_KEY]  ?? null;
        $flashErr = $_SESSION[self::FLASH_ERR_KEY] ?? null;
        // Der Zettel wird beim Anzeigen verbraucht. Er steht in der Sitzung
        // und nicht in der Adresse: Ein Code in der URL landet im
        // Browserverlauf und im Zugriffsprotokoll des Servers.
        $zettel   = $_SESSION[self::FLASH_ZETTEL_KEY] ?? null;
        unset($_SESSION[self::FLASH_OK_KEY], $_SESSION[self::FLASH_ERR_KEY],
              $_SESSION[self::FLASH_ZETTEL_KEY]);

        $csrfBereich = self::CSRF_BEREICH;
        // Stammt diese Datenbank aus einer anderen Installation? Dann laeuft
        // nichts, und die Maske muss sagen warum - sonst sucht jemand den
        // Fehler im Netz, obwohl er in einem eingespielten Dump liegt.
        $herkunft = $verbindung !== null
            ? $this->portal->installationPasst($verbindung)
            : ['ok' => true, 'meldung' => ''];
        $kennung = $this->portal->installationsKennung();
        $freigegeben = $this->zaehleFreigegebene();
        $eingang     = $this->letzteEingaenge();
        $belegschaft = $this->portal->istGekoppelt() ? $this->freigabe->liste() : [];
        $vorschlaege = [];
        foreach ($belegschaft as $m) {
            $vorschlaege[(int)$m['id']] = (string)($m['portal_kennung'] ?? '') !== ''
                ? (string)$m['portal_kennung']
                : $this->freigabe->vorschlagKennung($m);
        }

        require __DIR__ . '/../views/portal_admin/index.php';
    }

    // ------------------------------------------------------------ Aktionen

    private function verarbeitePost(): void
    {
        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            $this->flashErr('Die Formularsitzung ist abgelaufen. Bitte die Seite neu laden.');
            $this->zurueck();
            return;
        }

        $aktion = Helper::leseString($_POST, 'aktion');

        match ($aktion) {
            'koppeln'      => $this->koppeln(),
            'probe'        => $this->probe(),
            'entkoppeln'   => $this->entkoppeln(),
            'abgleich'     => $this->abgleich(),
            'freischalten' => $this->freischalten(),
            'sperren'      => $this->sperren(),
            'code'         => $this->aktivierungscode(),
            default        => $this->flashErr('Unbekannte Aktion.'),
        };

        $this->zurueck();
    }

    private function koppeln(): void
    {
        $adresse = Helper::leseString($_POST, 'basis_url');
        $code    = Helper::leseString($_POST, 'code');

        $ergebnis = $this->portal->koppeln($adresse, $code);

        if ($ergebnis['ok']) {
            $this->flashOk($ergebnis['meldung']);
            return;
        }
        $this->flashErr($ergebnis['meldung']);
    }

    private function probe(): void
    {
        $ergebnis = $this->portal->probe();
        if ($ergebnis['ok']) {
            $this->flashOk($ergebnis['meldung']);
            return;
        }
        $this->flashErr($ergebnis['meldung']);
    }

    private function entkoppeln(): void
    {
        $this->portal->entkoppeln();
        $this->flashOk(
            'Die Verbindung ist auf dieser Seite getrennt – es wird nicht mehr angerufen. '
            . 'Auf der Website bleiben die gespiegelten Daten stehen, bis dort jemand '
            . '»Verbindung trennen« drückt. Solange können sich Mitarbeiter dort weiter '
            . 'anmelden und sehen alte Zahlen mit einem Hinweis, wie alt sie sind.'
        );
    }

    /**
     * Abgleich von Hand anstossen.
     *
     * Im Normalfall macht das der Zeitplandienst alle zwei Minuten. Dieser
     * Knopf ist fuer zwei Faelle da: gleich nach dem Freischalten, damit
     * jemand nicht zwei Minuten auf seinen Zugang wartet - und zur
     * Fehlersuche, weil er im Gegensatz zum Zeitplan seine Zeilen anzeigt.
     */
    private function abgleich(): void
    {
        $ergebnis = (new PortalSyncService())->laufen('hand');

        if ($ergebnis['ok']) {
            $this->flashOk('Abgleich durchgelaufen in ' . $ergebnis['dauer_ms'] . ' ms: '
                . implode(' | ', $ergebnis['zeilen']));
            return;
        }
        $this->flashErr('Der Abgleich ist gescheitert: ' . $ergebnis['fehler']);
    }

    private function freischalten(): void
    {
        $id = (int)(Helper::leseInt($_POST, 'mitarbeiter_id') ?? 0);
        $ergebnis = $this->freigabe->freischalten($id, Helper::leseString($_POST, 'kennung'));
        $ergebnis['ok'] ? $this->flashOk($ergebnis['meldung']) : $this->flashErr($ergebnis['meldung']);
    }

    private function sperren(): void
    {
        $id = (int)(Helper::leseInt($_POST, 'mitarbeiter_id') ?? 0);
        $ergebnis = $this->freigabe->sperren($id);
        $ergebnis['ok'] ? $this->flashOk($ergebnis['meldung']) : $this->flashErr($ergebnis['meldung']);
    }

    private function aktivierungscode(): void
    {
        $id = (int)(Helper::leseInt($_POST, 'mitarbeiter_id') ?? 0);
        $ergebnis = $this->freigabe->aktivierungscodeErzeugen($id);

        if (!$ergebnis['ok']) {
            $this->flashErr($ergebnis['meldung']);
            return;
        }

        $mitarbeiter = $this->freigabe->holeMitarbeiter($id);
        $verbindung  = $this->portal->verbindung();

        $_SESSION[self::FLASH_ZETTEL_KEY] = [
            'name'    => trim((string)($mitarbeiter['vorname'] ?? '') . ' ' . (string)($mitarbeiter['nachname'] ?? '')),
            'kennung' => (string)($mitarbeiter['portal_kennung'] ?? ''),
            'code'    => $ergebnis['code'],
            'bis'     => (string)($mitarbeiter['portal_aktivierung_bis'] ?? ''),
            'adresse' => rtrim((string)($verbindung['basis_url'] ?? ''), '/') . '/mitarbeiter',
        ];
        $this->flashOk($ergebnis['meldung']);
    }

    // -------------------------------------------------------------- Anzeige

    /** Wie viele Mitarbeiter für das Portal freigeschaltet sind. */
    private function zaehleFreigegebene(): int
    {
        $zeile = $this->datenbank->fetchEine(
            'SELECT COUNT(*) AS anzahl FROM mitarbeiter WHERE portal_aktiv = 1 AND aktiv = 1'
        );
        return (int)($zeile['anzahl'] ?? 0);
    }

    /**
     * Die letzten verarbeiteten Auftraege - der Beleg dafür, dass der Abgleich
     * wirklich etwas tut. Ohne diese Liste sieht man nur »zuletzt gelaufen«
     * und weiss nicht, ob dabei jemals etwas ankam.
     *
     * @return array<int,array<string,mixed>>
     */
    private function letzteEingaenge(): array
    {
        return $this->datenbank->fetchAlle(
            'SELECT e.fremd_id, e.art, e.ergebnis, e.hinweis, e.verarbeitet_am,
                    e.urlaubsantrag_id,
                    CONCAT(m.vorname, " ", m.nachname) AS name
               FROM portal_eingang e
          LEFT JOIN mitarbeiter m ON m.id = e.mitarbeiter_id
              ORDER BY e.id DESC
              LIMIT 15'
        );
    }

    // --------------------------------------------------------------- intern

    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }
        if ($this->authService->hatRecht('PORTAL_VERWALTEN')) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, das Mitarbeiterportal zu verwalten.</p>';
        return false;
    }

    private function flashOk(string $text): void
    {
        $_SESSION[self::FLASH_OK_KEY] = $text;
    }

    private function flashErr(string $text): void
    {
        $_SESSION[self::FLASH_ERR_KEY] = $text;
    }

    private function zurueck(): void
    {
        header('Location: ?seite=portal_admin');
    }
}
