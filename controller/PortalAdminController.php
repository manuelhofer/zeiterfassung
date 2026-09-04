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

    private AuthService $authService;
    private Database $datenbank;
    private PortalVerbindungService $portal;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank   = Database::getInstanz();
        $this->portal      = PortalVerbindungService::getInstanz();
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
        unset($_SESSION[self::FLASH_OK_KEY], $_SESSION[self::FLASH_ERR_KEY]);

        $csrfBereich = self::CSRF_BEREICH;
        $freigegeben = $this->zaehleFreigegebene();
        $eingang     = $this->letzteEingaenge();

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
            'koppeln'    => $this->koppeln(),
            'probe'      => $this->probe(),
            'entkoppeln' => $this->entkoppeln(),
            default      => $this->flashErr('Unbekannte Aktion.'),
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
