<?php
declare(strict_types=1);

/**
 * AuftragController
 *
 * Backend-Auswertung für Auftragszeiten.
 *
 * Scope (Micro-Patch):
 * - Listet Aufträge anhand von `auftragszeit` (auftragscode/auftrag_id).
 * - Detailansicht zeigt alle Buchungen (Mitarbeiter, Maschine, Zeiten, Dauer).
 *
 * Nicht in diesem Patch (kommt getrennt):
 * - Arbeitsschritt-Code wird in der Detailansicht angezeigt (falls vorhanden).
 * - Top-Menü-Link (Nav) – wird als eigener Mini-Patch geliefert (Datei-Budget).
 */
class AuftragController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH_AUFTRAGSZEIT = 'auftragszeit_bearbeiten';
        private const CSRF_BEREICH_STAMM = 'auftrag_stamm';

    /**
     * Zeilen je Seite in der Auftragsliste.
     *
     * 25 passt auf einen Bildschirm, ohne dass gescrollt werden muss – und
     * begrenzt zugleich, wie viel eine Suche über mehrere tausend Aufträge
     * überhaupt zusammensuchen muss.
     */
    private const TREFFER_JE_SEITE = 25;

    /**
     * Ab wie vielen Seiten die Sprungpfeile erscheinen.
     *
     * Bei vier Seiten sind alle Seitenzahlen ohnehin sichtbar; Pfeile wären
     * dann nur ein zweiter Weg zum selben Ziel.
     */
    private const PFEILE_AB_SEITEN = 5;

    /**
     * Auswählbare Auftragsstatus.
     *
     * Bewusst eine feste Liste statt eines Freitextfelds: Frei eingetippte
     * Werte lassen sich nicht auswerten und gehen bei jedem Tippfehler
     * auseinander ("offen", "Offen", "offfen"). Der Status in der Auftragsliste
     * wird ohnehin aus den Buchungen berechnet - dieses Feld ist nur die
     * zusätzliche Einschätzung der Arbeitsvorbereitung.
     *
     * Altbestand mit abweichenden Werten bleibt erhalten und wählbar.
     */
    private const STATUS_AUSWAHL = [
        'offen'         => 'offen',
        'in_arbeit'     => 'in Arbeit',
        'wartet'        => 'wartet (Material/Freigabe)',
        'abgeschlossen' => 'abgeschlossen',
        'storniert'     => 'storniert',
    ];

    private AuthService $authService;
    private Database $db;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->db = Database::getInstanz();
    }

    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Aktuell: jeder eingeloggte Benutzer darf Auswertungen sehen.
        // (Rechte/Scopes können später ergänzt werden, ohne bestehende Funktionen zu entfernen.)
        return true;
    }

    private function hatArbeitsschrittTabellen(): bool
    {
        try {
            $tabellen = $this->db->fetchAlle(
                'SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (:t1, :t2)',
                ['t1' => 'auftrag_arbeitsschritt', 't2' => 'auftragszeit']
            );

            $hat = [];
            foreach ($tabellen as $row) {
                $name = isset($row['TABLE_NAME']) ? (string)$row['TABLE_NAME'] : '';
                if ($name !== '') {
                    $hat[$name] = true;
                }
            }

            if (empty($hat['auftrag_arbeitsschritt']) || empty($hat['auftragszeit'])) {
                return false;
            }

            $spalten = $this->db->fetchAlle(
                'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t',
                ['t' => 'auftragszeit']
            );

            foreach ($spalten as $row) {
                $name = isset($row['COLUMN_NAME']) ? (string)$row['COLUMN_NAME'] : '';
                if ($name === 'arbeitsschritt_id') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Liste / Suche
     * Route: ?seite=auftrag
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $q = trim((string)($_GET['q'] ?? ''));

        // Zwei Ansichten auf dieselbe Liste. Erledigte Aufträge werden auf
        // inaktiv gesetzt und verschwinden damit aus dem Alltag - gelöscht wird
        // dabei nichts, die Buchungen bleiben zuordenbar.
        $nurInaktive = ((string)($_GET['ansicht'] ?? '')) === 'inaktiv';

        // Beim Suchen zählt der Bestand, nicht die Ablage: Wer eine Nummer
        // eintippt, will sie finden - auch wenn der Auftrag längst inaktiv ist.
        // Deshalb ist das Häkchen gesetzt, solange es niemand abwählt. Ohne
        // Suchbegriff bleibt es wirkungslos, sonst wäre die Ablage wieder in
        // der Liste.
        $mitInaktiven = !isset($_GET['mit_inaktiven']) || (string)$_GET['mit_inaktiven'] === '1';

        $like = null;
        if ($q !== '') {
            // LIKE-Pattern defensiv escapen
            $q2 = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $like = '%' . $q2 . '%';
        }

        $seiteNr = (int)($_GET['s'] ?? 1);
        if ($seiteNr < 1) {
            $seiteNr = 1;
        }

        $fehlermeldung = null;
        $ladefehler = false;
        $auftraege = [];
        $anzahlInaktive = $this->zaehleInaktiveAuftraege();
        $treffer = 0;
        $seitenGesamt = 1;

        try {
            $bedingungen = [];
            $params = [];

            // Eine Auftragsnummer ohne Stammdatensatz (`a.aktiv IS NULL`) stammt
            // allein aus Buchungen. Sie gilt als aktiv, sonst wäre sie nirgends
            // zu sehen.
            //
            // Der dritte Fall - Suche mit gesetztem Häkchen - kennt gar keine
            // Bedingung auf `aktiv`: Dann wird der ganze Bestand durchsucht.
            $sucheUeberAlles = ($like !== null && $mitInaktiven && !$nurInaktive);

            if ($nurInaktive) {
                $bedingungen[] = 'a.aktiv = 0';
            } elseif (!$sucheUeberAlles) {
                $bedingungen[] = '(a.aktiv IS NULL OR a.aktiv = 1)';
            }

            if ($like !== null) {
                // Gefiltert wird auf der Grundmenge der Auftragsnummern und den
                // Stammdaten, nicht auf den verbundenen Buchungen. Sonst fände
                // die Suche einen Auftrag ohne Buchung nicht.
                //
                // Ein Suchfeld für alle Spalten statt vier einzelner Felder: Wer
                // "Muster GmbH" im Kopf hat, tippt das - und soll nicht vorher
                // entscheiden müssen, in welcher Spalte es steht.
                //
                // Vier Platzhalter für denselben Wert, weil die Verbindung ohne
                // Emulation präpariert (`ATTR_EMULATE_PREPARES = false`): Ein
                // benannter Platzhalter darf dort nur einmal vorkommen.
                $bedingungen[] = '(nummern.auftragsnummer LIKE :q1 ESCAPE "\\\\"
                               OR a.kunde LIKE :q2 ESCAPE "\\\\"
                               OR a.zeichnungsnummer LIKE :q3 ESCAPE "\\\\"
                               OR a.kurzbeschreibung LIKE :q4 ESCAPE "\\\\")';
                $params['q1'] = $like;
                $params['q2'] = $like;
                $params['q3'] = $like;
                $params['q4'] = $like;
            }

            $where = $bedingungen === [] ? '' : ('WHERE ' . implode(' AND ', $bedingungen));

            // Grundmenge sind alle bekannten Auftragsnummern - aus den Stammdaten
            // (`auftrag`) UND aus den Buchungen (`auftragszeit`).
            //
            // Wichtig: Früher startete diese Abfrage bei `auftragszeit`. Ein im
            // Backend angelegter Auftrag ohne Buchung war damit unsichtbar. Über
            // die UNION-Grundmenge erscheint er sofort - mit 0 Buchungen und dem
            // Status "angelegt".
            $nummern = "
                (
                    SELECT auftragsnummer AS auftragsnummer
                      FROM auftrag
                     WHERE auftragsnummer IS NOT NULL AND auftragsnummer <> ''
                    UNION
                    SELECT DISTINCT az2.auftragscode
                      FROM auftragszeit az2
                     WHERE az2.auftragscode IS NOT NULL AND az2.auftragscode <> ''
                ) AS nummern
                LEFT JOIN auftrag a
                       ON a.auftragsnummer = nummern.auftragsnummer
            ";

            // Erst zählen, dann die eine Seite holen. Der Zähler kommt ohne die
            // Buchungen aus: Gefiltert wird nur über `nummern` und `a`, und je
            // Auftragsnummer gibt es dort genau eine Zeile.
            $anzahlZeile = $this->db->fetchEine("SELECT COUNT(*) AS anzahl FROM {$nummern} {$where}", $params);
            $treffer = is_array($anzahlZeile) ? (int)($anzahlZeile['anzahl'] ?? 0) : 0;

            $seitenGesamt = (int)max(1, (int)ceil($treffer / self::TREFFER_JE_SEITE));
            if ($seiteNr > $seitenGesamt) {
                $seiteNr = $seitenGesamt;
            }

            // LIMIT/OFFSET stehen als Zahl in der Abfrage, nicht als Platzhalter:
            // Ohne Emulation präparierte Statements binden sie als Zeichenkette
            // (`LIMIT '25'`), und das ist ein Syntaxfehler. Beide Werte sind hier
            // nachweislich ganzzahlig.
            $limit  = self::TREFFER_JE_SEITE;
            $offset = ($seiteNr - 1) * self::TREFFER_JE_SEITE;

            // Alle Spalten sind aggregiert (MAX/COUNT/SUM), damit die Abfrage auch
            // unter ONLY_FULL_GROUP_BY läuft (vgl. B-085 / P-2026-01-25-02).
            $sql = "
                SELECT
                    nummern.auftragsnummer AS auftragsnummer,
                    MAX(a.aktiv) AS auftrag_aktiv,
                    MAX(a.kurzbeschreibung) AS kurzbeschreibung,
                    MAX(a.kunde) AS kunde,
                    MAX(a.zeichnungsnummer) AS zeichnungsnummer,
                    COUNT(az.id) AS buchungen,
                    SUM(CASE WHEN az.status = 'laufend' THEN 1 ELSE 0 END) AS laufend,
                    SUM(CASE WHEN az.status = 'pausiert' THEN 1 ELSE 0 END) AS pausiert,
                    CASE
                        WHEN SUM(CASE WHEN az.status = 'laufend' THEN 1 ELSE 0 END) > 0 THEN 'laufend'
                        WHEN SUM(CASE WHEN az.status = 'pausiert' THEN 1 ELSE 0 END) > 0 THEN 'pausiert'
                        WHEN COUNT(az.id) = 0 THEN 'angelegt'
                        ELSE 'abgeschlossen'
                    END AS status,
                    SUM(CASE WHEN az.endzeit IS NOT NULL THEN TIMESTAMPDIFF(SECOND, az.startzeit, az.endzeit) ELSE 0 END) AS sekunden,
                    MIN(az.startzeit) AS erste_startzeit,
                    MAX(COALESCE(az.endzeit, az.startzeit)) AS letzte_zeit,
                    COALESCE(MAX(a.geaendert_am), MAX(COALESCE(az.endzeit, az.startzeit))) AS zuletzt_bearbeitet
                FROM {$nummern}
                LEFT JOIN auftragszeit az
                       ON az.auftragscode = nummern.auftragsnummer
                       OR (a.id IS NOT NULL AND az.auftrag_id = a.id)
                {$where}
                GROUP BY nummern.auftragsnummer
                ORDER BY COALESCE(MAX(COALESCE(az.endzeit, az.startzeit)), MAX(a.geaendert_am)) DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $auftraege = $this->db->fetchAlle($sql, $params);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Aufträge konnten nicht geladen werden.';
            // Eigener Merker statt `$fehlermeldung`: Eine leere Liste heisst
            // "nichts da", ein Ladefehler heisst "nicht nachgesehen" - und die
            // Flash-Meldung einer misslungenen Aktion sagt über den Bestand
            // gar nichts (B-096, P-2026-08-15-09).
            $ladefehler = true;
            Logger::error('Fehler beim Laden der Auftragsliste', [
                'exception' => $e->getMessage(),
                'q' => $q,
            ], null, null, 'auftrag');
        }

        $darfVerwalten = $this->darfAuftraegeVerwalten();

        // Die View baut ihr CSRF-Feld selbst; sie bekommt dafür den
        // Bereichsnamen statt eines fertigen Tokens.
        $csrfBereich = self::CSRF_BEREICH_STAMM;

        $flashOk     = isset($_SESSION['auftrag_flash_ok']) ? (string)$_SESSION['auftrag_flash_ok'] : '';
        $flashFehler = isset($_SESSION['auftrag_flash_fehler']) ? (string)$_SESSION['auftrag_flash_fehler'] : '';
        unset($_SESSION['auftrag_flash_fehler'], $_SESSION['auftrag_flash_ok']);

        $blaetterdaten = $this->baueBlaetterdaten($seiteNr, $seitenGesamt, $treffer, $q, $nurInaktive, $mitInaktiven);

        require __DIR__ . '/../views/auftrag/liste.php';
    }

    /**
     * Rechnet vor, was die Blätternavigation anzeigt.
     *
     * Die Seitenzahlen stehen immer da. Die Sprungpfeile (Anfang, zurück, vor,
     * Ende) kommen erst ab `PFEILE_AB_SEITEN` dazu - bei vier Seiten sind alle
     * Zahlen ohnehin sichtbar.
     *
     * Die URLs entstehen hier und nicht in der View: Sie kommen aus
     * `baueListenUrl()`, das bewusst aus Einzelwerten baut statt aus einem
     * mitgeschickten Ziel.
     *
     * @return array<string,mixed>
     */
    private function baueBlaetterdaten(
        int $seiteNr,
        int $seitenGesamt,
        int $treffer,
        string $q,
        bool $nurInaktive,
        bool $mitInaktiven
    ): array {
        // Wieviele Zahlen um die aktuelle Seite herum stehen. Bei sehr vielen
        // Seiten würde die Zeile sonst umbrechen und unlesbar werden.
        $fenster    = 3;
        $ersteZahl  = max(1, $seiteNr - $fenster);
        $letzteZahl = min($seitenGesamt, $seiteNr + $fenster);

        // Gebraucht werden nur die Ziele, die auch als Link erscheinen: das
        // Fenster um die aktuelle Seite, dazu erste und letzte Seite sowie die
        // Nachbarn für die Pfeile.
        $ziele = array_unique(array_merge(
            range($ersteZahl, $letzteZahl),
            [1, $seitenGesamt, max(1, $seiteNr - 1), min($seitenGesamt, $seiteNr + 1)]
        ));

        $seitenUrls = [];
        foreach ($ziele as $ziel) {
            $seitenUrls[$ziel] = $this->baueListenUrl($q, $nurInaktive, (int)$ziel, $mitInaktiven);
        }

        return [
            'seiteNr'      => $seiteNr,
            'seitenGesamt' => $seitenGesamt,
            'treffer'      => $treffer,
            'von'          => (($seiteNr - 1) * self::TREFFER_JE_SEITE) + 1,
            'bis'          => min($seiteNr * self::TREFFER_JE_SEITE, $treffer),
            'ersteZahl'    => $ersteZahl,
            'letzteZahl'   => $letzteZahl,
            'mitPfeilen'   => $seitenGesamt >= self::PFEILE_AB_SEITEN,
            'seitenUrls'   => $seitenUrls,
        ];
    }

    /**
     * Setzt einen Auftrag aktiv oder inaktiv.
     * Route: ?seite=auftrag_aktiv_setzen (POST)
     *
     * Inaktiv statt gelöscht ist der Regelweg: Der Auftrag verschwindet aus der
     * Liste, seine Buchungen und Stunden bleiben aber zuordenbar - und wenn die
     * Halle den Code doch noch einmal scannt, fällt nichts um.
     */
    public function aktivSetzen(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=auftrag');
            return;
        }

        $zielUrl = $this->baueListenUrl(
            trim((string)($_POST['q'] ?? '')),
            ((string)($_POST['ansicht'] ?? '')) === 'inaktiv',
            max(1, (int)($_POST['s'] ?? 1)),
            !isset($_POST['mit_inaktiven']) || (string)$_POST['mit_inaktiven'] === '1'
        );

        if (!Csrf::istGueltig(self::CSRF_BEREICH_STAMM)) {
            $_SESSION['auftrag_flash_fehler'] = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            header('Location: ' . $zielUrl);
            return;
        }

        $auftragsnummer = trim((string)($_POST['auftragsnummer'] ?? ''));
        $aktiv = ((int)($_POST['aktiv'] ?? 1) === 1) ? 1 : 0;

        if ($auftragsnummer === '') {
            $_SESSION['auftrag_flash_fehler'] = 'Es war kein Auftrag ausgewählt.';
            header('Location: ' . $zielUrl);
            return;
        }

        try {
            // Zu einer Auftragsnummer, die nur aus Buchungen stammt, gibt es
            // keinen Stammdatensatz - und damit nichts, woran "inaktiv" hängen
            // könnte. Der Datensatz wird dann angelegt, sonst liesse sich genau
            // die Zeile nicht ausblenden, die stört.
            $betroffen = $this->db->ausfuehren(
                'UPDATE auftrag SET aktiv = :aktiv WHERE auftragsnummer = :nr',
                ['aktiv' => $aktiv, 'nr' => $auftragsnummer]
            );

            if ($betroffen === 0) {
                $vorhanden = $this->db->fetchEine(
                    'SELECT id FROM auftrag WHERE auftragsnummer = :nr LIMIT 1',
                    ['nr' => $auftragsnummer]
                );

                if (!is_array($vorhanden)) {
                    $this->db->ausfuehren(
                        'INSERT INTO auftrag (auftragsnummer, aktiv) VALUES (:nr, :aktiv)',
                        ['nr' => $auftragsnummer, 'aktiv' => $aktiv]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Auftrag konnte nicht umgestellt werden', [
                'auftragsnummer' => $auftragsnummer,
                'aktiv'          => $aktiv,
                'exception'      => $e->getMessage(),
            ]);
            $_SESSION['auftrag_flash_fehler'] = 'Der Auftrag konnte nicht umgestellt werden.';
            header('Location: ' . $zielUrl);
            return;
        }

        $_SESSION['auftrag_flash_ok'] = $aktiv === 1
            ? 'Auftrag "' . $auftragsnummer . '" ist wieder aktiv.'
            : 'Auftrag "' . $auftragsnummer . '" ist jetzt inaktiv und aus der Liste verschwunden.';

        header('Location: ' . $zielUrl);
    }

    /**
     * Löscht einen Auftrag samt seiner Arbeitsschritte.
     * Route: ?seite=auftrag_loeschen (POST)
     *
     * Nur solange **keine** Buchung daran hängt. Sonst wären gebuchte Stunden
     * plötzlich einer Nummer zugeordnet, zu der es nichts mehr gibt - und genau
     * dafür ist "inaktiv setzen" da.
     */
    public function loeschen(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=auftrag');
            return;
        }

        $auftragsnummer = trim((string)($_POST['auftragsnummer'] ?? ''));
        $detailUrl = '?seite=auftrag_detail&code=' . urlencode($auftragsnummer);

        if (!Csrf::istGueltig(self::CSRF_BEREICH_STAMM)) {
            $_SESSION['auftrag_detail_flash_fehler'] = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            header('Location: ' . ($auftragsnummer !== '' ? $detailUrl : '?seite=auftrag'));
            return;
        }

        if ($auftragsnummer === '') {
            $_SESSION['auftrag_flash_fehler'] = 'Es war kein Auftrag ausgewählt.';
            header('Location: ?seite=auftrag');
            return;
        }

        try {
            $auftrag = $this->db->fetchEine(
                'SELECT id FROM auftrag WHERE auftragsnummer = :nr LIMIT 1',
                ['nr' => $auftragsnummer]
            );

            if (!is_array($auftrag)) {
                $_SESSION['auftrag_flash_fehler'] = 'Der Auftrag wurde nicht gefunden.';
                header('Location: ?seite=auftrag');
                return;
            }

            $auftragId = (int)($auftrag['id'] ?? 0);

            // Die Prüfung gehört hierher und nicht nur ins Formular: Ein
            // zweiter Tab, ein zwischenzeitlicher Scan in der Halle - und die
            // Buchung wäre da, obwohl der Knopf sie nicht kannte.
            $anzahl = $this->zaehleBuchungen($auftragsnummer, $auftragId);

            if ($anzahl > 0) {
                $_SESSION['auftrag_detail_flash_fehler'] = $anzahl === 1
                    ? 'Der Auftrag hat eine Buchung und wird deshalb nicht gelöscht. Setzen Sie ihn auf inaktiv - dann verschwindet er aus der Liste, die gebuchte Zeit bleibt erhalten.'
                    : 'Der Auftrag hat ' . $anzahl . ' Buchungen und wird deshalb nicht gelöscht. Setzen Sie ihn auf inaktiv - dann verschwindet er aus der Liste, die gebuchten Zeiten bleiben erhalten.';
                header('Location: ' . $detailUrl);
                return;
            }

            $this->db->ausfuehren('DELETE FROM auftrag_arbeitsschritt WHERE auftrag_id = :aid', ['aid' => $auftragId]);
            $this->db->ausfuehren('DELETE FROM auftrag WHERE id = :id', ['id' => $auftragId]);
        } catch (\Throwable $e) {
            $this->protokolliere('Auftrag konnte nicht gelöscht werden', [
                'auftragsnummer' => $auftragsnummer,
                'exception'      => $e->getMessage(),
            ]);
            $_SESSION['auftrag_detail_flash_fehler'] = 'Der Auftrag konnte nicht gelöscht werden.';
            header('Location: ' . $detailUrl);
            return;
        }

        // Wer löscht, soll nachvollziehbar sein - hier steht der einzige Weg,
        // auf dem Auftragsstammdaten aus der Datenbank verschwinden.
        Logger::info(
            'Auftrag gelöscht',
            ['auftragsnummer' => $auftragsnummer],
            $this->authService->holeAngemeldeteMitarbeiterId(),
            null,
            'auftrag'
        );

        $_SESSION['auftrag_flash_ok'] = 'Der Auftrag "' . $auftragsnummer . '" wurde gelöscht.';
        header('Location: ?seite=auftrag');
    }

    /**
     * Zählt die Buchungen eines Auftrags - über die Nummer und über die ID.
     *
     * Beide Wege sind nötig: Das Terminal schreibt den gescannten Code nach
     * `auftragscode`, das Backend verknüpft über `auftrag_id`.
     */
    private function zaehleBuchungen(string $auftragsnummer, int $auftragId): int
    {
        $zeile = $this->db->fetchEine(
            'SELECT COUNT(*) AS anzahl
               FROM auftragszeit
              WHERE auftragscode = :code
                 OR (:aid > 0 AND auftrag_id = :aid2)',
            ['code' => $auftragsnummer, 'aid' => $auftragId, 'aid2' => $auftragId]
        );

        return is_array($zeile) ? (int)($zeile['anzahl'] ?? 0) : 0;
    }

    /**
     * Wie viele Aufträge sind auf inaktiv gesetzt?
     *
     * Nur für die Beschriftung des Links. Fällt die Abfrage aus, steht dort
     * eben keine Zahl - die Liste selbst hängt nicht daran.
     */
    private function zaehleInaktiveAuftraege(): int
    {
        try {
            $row = $this->db->fetchEine('SELECT COUNT(*) AS anzahl FROM auftrag WHERE aktiv = 0');

            return is_array($row) ? (int)($row['anzahl'] ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Baut die URL der Auftragsliste aus den bekannten Parametern neu auf.
     *
     * Bewusst aus Einzelwerten statt aus einem mitgeschickten Ziel: Ein
     * Rückleitungsziel, das aus dem Formular kommt, wäre eine offene
     * Weiterleitung.
     */
    private function baueListenUrl(
        string $q,
        bool $nurInaktive,
        int $seiteNr = 1,
        bool $mitInaktiven = true
    ): string {
        $parameter = ['seite' => 'auftrag'];

        if ($nurInaktive) {
            $parameter['ansicht'] = 'inaktiv';
        }

        if ($q !== '') {
            $parameter['q'] = $q;
        }

        // Nur die Abweichung vom Standard steht in der URL - sonst hängt an
        // jedem Link ein Parameter, der nichts ändert.
        if (!$mitInaktiven) {
            $parameter['mit_inaktiven'] = '0';
        }

        if ($seiteNr > 1) {
            $parameter['s'] = $seiteNr;
        }

        return '?' . http_build_query($parameter);
    }

    /**
     * Detailansicht
     * Route: ?seite=auftrag_detail&code=...
     */
    public function detail(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '') {
            header('Location: ?seite=auftrag');
            return;
        }

        $fehlermeldung = null;
        $ladefehler = false;
        $flashOk = isset($_SESSION['auftrag_detail_flash_ok']) ? (string)$_SESSION['auftrag_detail_flash_ok'] : null;
        $flashFehler = isset($_SESSION['auftrag_detail_flash_fehler']) ? (string)$_SESSION['auftrag_detail_flash_fehler'] : null;
        unset($_SESSION['auftrag_detail_flash_ok'], $_SESSION['auftrag_detail_flash_fehler']);

        $buchungen = [];
        $sumSekunden = 0;
        $sumProSchritt = [];
        $countProSchritt = [];

        try {
            $nutztArbeitsschritt = $this->hatArbeitsschrittTabellen();
            if ($nutztArbeitsschritt) {
                $sql = "
                    SELECT
                        az.*,
                        COALESCE(NULLIF(az.arbeitsschritt_code, ''), aas.arbeitsschritt_code) AS arbeitsschritt_code_effektiv,
                        mi.vorname, mi.nachname,
                        ma.name AS maschine_name,
                        a.auftragsnummer AS auftrag_nummer
                    FROM auftragszeit az
                    INNER JOIN mitarbeiter mi ON mi.id = az.mitarbeiter_id
                    LEFT JOIN maschine ma ON ma.id = az.maschine_id
                    LEFT JOIN auftrag a ON a.id = az.auftrag_id
                    LEFT JOIN auftrag_arbeitsschritt aas ON aas.id = az.arbeitsschritt_id
                    WHERE (a.auftragsnummer = :code1 OR az.auftragscode = :code2)
                    ORDER BY az.startzeit DESC
                    LIMIT 1000
                ";
                $buchungen = $this->db->fetchAlle($sql, ['code1' => $code, 'code2' => $code]);
            } else {
                $sql = "
                    SELECT
                        az.*,
                        az.arbeitsschritt_code AS arbeitsschritt_code_effektiv,
                        mi.vorname, mi.nachname,
                        ma.name AS maschine_name,
                        a.auftragsnummer AS auftrag_nummer
                    FROM auftragszeit az
                    INNER JOIN mitarbeiter mi ON mi.id = az.mitarbeiter_id
                    LEFT JOIN maschine ma ON ma.id = az.maschine_id
                    LEFT JOIN auftrag a ON a.id = az.auftrag_id
                    WHERE (a.auftragsnummer = :code1 OR az.auftragscode = :code2)
                    ORDER BY az.startzeit DESC
                    LIMIT 1000
                ";
                $buchungen = $this->db->fetchAlle($sql, ['code1' => $code, 'code2' => $code]);
            }

            foreach ($buchungen as $b) {
                $start = (string)($b['startzeit'] ?? '');
                $end = $b['endzeit'] ?? null;
                if ($end !== null && (string)$end !== '') {
                    $ts1 = strtotime($start);
                    $ts2 = strtotime((string)$end);
                    if ($ts1 !== false && $ts2 !== false && $ts2 >= $ts1) {
                        $dauerSec = (int)($ts2 - $ts1);
                        $sumSekunden += $dauerSec;

                        $schrittTmp = isset($b['arbeitsschritt_code_effektiv']) ? trim((string)$b['arbeitsschritt_code_effektiv']) : '';
                        $key = ($schrittTmp !== '') ? $schrittTmp : '(ohne)';
                        if (!isset($sumProSchritt[$key])) {
                            $sumProSchritt[$key] = 0;
                            $countProSchritt[$key] = 0;
                        }
                        $sumProSchritt[$key] += $dauerSec;
                        $countProSchritt[$key] += 1;
                    }
                }
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Auftragsdetails konnten nicht geladen werden.';
            // Siehe `index()`: "keine Buchungen" und "nicht nachgesehen" sind
            // zwei verschiedene Auskünfte (B-096).
            $ladefehler = true;
            Logger::error('Fehler beim Laden der Auftragsdetails', [
                'exception' => $e->getMessage(),
                'code' => $code,
            ], null, null, 'auftrag');
        }

        // Stammdaten des Auftrags und seine Arbeitsschritte laden.
        //
        // Das ist bewusst unabhängig von den Buchungen: Ein frisch angelegter
        // Auftrag hat noch keine Buchung, soll aber trotzdem Strichcode und
        // Arbeitsschritte zeigen.
        $auftragStamm = null;
        $arbeitsschritte = [];
        $katalogVerfuegbar = [];
        $auftragCodeUrl = '';

        try {
            $auftragStamm = $this->db->fetchEine(
                'SELECT * FROM auftrag WHERE auftragsnummer = :nr LIMIT 1',
                ['nr' => $code]
            );

            if (is_array($auftragStamm)) {
                $auftragId = (int)($auftragStamm['id'] ?? 0);

                $arbeitsschritte = $this->db->fetchAlle(
                    'SELECT * FROM auftrag_arbeitsschritt
                      WHERE auftrag_id = :aid
                      ORDER BY aktiv DESC, id ASC',
                    ['aid' => $auftragId]
                );

                $arbeitsschritte = $this->ergaenzeBezeichnungenAusKatalog($arbeitsschritte);

                // Katalogschritte, die es bei diesem Auftrag noch nicht gibt
                try {
                    $vorhandeneCodes = [];
                    foreach ($arbeitsschritte as $vorhanden) {
                        $vorhandeneCodes[trim((string)($vorhanden['arbeitsschritt_code'] ?? ''))] = true;
                    }

                    foreach ($this->db->fetchAlle(
                        'SELECT id, code, bezeichnung FROM arbeitsschritt_katalog
                          WHERE aktiv = 1 ORDER BY sort_order ASC, code ASC'
                    ) as $katalogEintrag) {
                        $katalogCode = trim((string)($katalogEintrag['code'] ?? ''));
                        if ($katalogCode !== '' && !isset($vorhandeneCodes[$katalogCode])) {
                            $katalogVerfuegbar[] = $katalogEintrag;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ohne Katalog bleibt der Auftrag trotzdem bedienbar.
                    $katalogVerfuegbar = [];
                }

                $codeService = new BarcodeService();

                $auftragCodeUrl = $codeService->baueBildUrl(
                    $codeService->stelleBildBereit(
                        $code,
                        $codeService->dateinameAuftrag($auftragId),
                        isset($auftragStamm['geaendert_am']) ? (string)$auftragStamm['geaendert_am'] : null
                    )
                );

                foreach ($arbeitsschritte as $index => $schritt) {
                    $schrittId = (int)($schritt['id'] ?? 0);
                    $schrittCode = trim((string)($schritt['arbeitsschritt_code'] ?? ''));

                    $arbeitsschritte[$index]['code_url'] = $codeService->baueBildUrl(
                        $codeService->stelleBildBereit(
                            $schrittCode,
                            $codeService->dateinameArbeitsschritt($schrittId),
                            isset($schritt['geaendert_am']) ? (string)$schritt['geaendert_am'] : null
                        )
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Auftragsstammdaten konnten nicht geladen werden', [
                'code'      => $code,
                'exception' => $e->getMessage(),
            ]);
        }

        $sumStunden = $sumSekunden > 0 ? round($sumSekunden / 3600, 2) : 0.0;

        $sumProSchrittSorted = $sumProSchritt;
        if (is_array($sumProSchrittSorted) && count($sumProSchrittSorted) > 1) {
            arsort($sumProSchrittSorted);
        }

        // Rechte und CSRF-Bereich reicht der Controller durch; die View baut
        // ihre drei CSRF-Felder selbst.
        $darfVerwalten = $this->darfAuftraegeVerwalten();
        $csrfBereich   = self::CSRF_BEREICH_STAMM;

        require __DIR__ . '/../views/auftrag/detail.php';
    }

    /**
     * Bearbeiten einer einzelnen Auftragszeit.
     * Route: ?seite=auftragszeit_bearbeiten&id=...
     */
    public function auftragszeitBearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $auftragszeitId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($auftragszeitId <= 0) {
            $_SESSION['auftrag_detail_flash_fehler'] = 'Die Auftragszeit-ID ist ungültig.';
            header('Location: ?seite=auftrag');
            return;
        }

        $auftragszeitModel = new AuftragszeitModel();
        $auftragModel = new AuftragModel();
        $datensatz = $auftragszeitModel->holeNachId($auftragszeitId);
        if (!is_array($datensatz)) {
            $_SESSION['auftrag_detail_flash_fehler'] = 'Die Auftragszeit wurde nicht gefunden.';
            header('Location: ?seite=auftrag');
            return;
        }

        $angemeldeterMitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();
        $angemeldeteId = (int)($angemeldeterMitarbeiter['id'] ?? 0);
        if ($angemeldeteId <= 0) {
            header('Location: ?seite=login');
            return;
        }

        $kannAlleBearbeiten = $this->darfAuftragszeitAlleBearbeiten();
        $kannEigeneBearbeiten = $this->darfAuftragszeitEigeneBearbeiten();
        $zielMitarbeiterId = (int)($datensatz['mitarbeiter_id'] ?? 0);
        $darfBearbeiten = $kannAlleBearbeiten || ($kannEigeneBearbeiten && $zielMitarbeiterId === $angemeldeteId);

        if (!$darfBearbeiten) {
            $_SESSION['auftrag_detail_flash_fehler'] = 'Sie dürfen diese Auftragszeit nicht bearbeiten.';
            header('Location: ?seite=auftrag_detail&code=' . urlencode($this->ermittleAuftragscode($datensatz, $auftragModel)));
            return;
        }

        $csrfToken = Csrf::token(self::CSRF_BEREICH_AUFTRAGSZEIT);
        $fehlermeldung = null;

        $status = (string)($datensatz['status'] ?? '');
        $kommentar = (string)($datensatz['kommentar'] ?? '');
        $startDatum = $this->formatDatumFuerForm((string)($datensatz['startzeit'] ?? ''));
        $startUhrzeit = $this->formatUhrzeitFuerForm((string)($datensatz['startzeit'] ?? ''));
        $endeDatum = $this->formatDatumFuerForm((string)($datensatz['endzeit'] ?? ''));
        $endeUhrzeit = $this->formatUhrzeitFuerForm((string)($datensatz['endzeit'] ?? ''));

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!Csrf::istGueltig(self::CSRF_BEREICH_AUFTRAGSZEIT)) {
                $fehlermeldung = 'CSRF-Check fehlgeschlagen.';
            } else {
                $startDatum = trim((string)($_POST['start_datum'] ?? ''));
                $startUhrzeit = trim((string)($_POST['start_uhrzeit'] ?? ''));
                $endeDatum = trim((string)($_POST['ende_datum'] ?? ''));
                $endeUhrzeit = trim((string)($_POST['ende_uhrzeit'] ?? ''));
                $status = trim((string)($_POST['status'] ?? ''));
                $kommentar = trim((string)($_POST['kommentar'] ?? ''));

                if (strlen($kommentar) > 2000) {
                    $kommentar = substr($kommentar, 0, 2000);
                }

                $startzeit = $this->parseDatumUhrzeit($startDatum, $startUhrzeit);
                if ($startzeit === null) {
                    $fehlermeldung = 'Bitte ein gültiges Start-Datum und eine gültige Start-Uhrzeit angeben.';
                }

                $endzeit = null;
                $hatEndeInput = ($endeDatum !== '' || $endeUhrzeit !== '');
                if ($fehlermeldung === null && $hatEndeInput) {
                    if ($endeDatum === '' || $endeUhrzeit === '') {
                        $fehlermeldung = 'Bitte Ende-Datum und Ende-Uhrzeit gemeinsam ausfüllen oder beide Felder leer lassen.';
                    } else {
                        $endzeit = $this->parseDatumUhrzeit($endeDatum, $endeUhrzeit);
                        if ($endzeit === null) {
                            $fehlermeldung = 'Bitte ein gültiges Ende-Datum und eine gültige Ende-Uhrzeit angeben.';
                        }
                    }
                }

                if ($fehlermeldung === null && $startzeit !== null && $endzeit !== null && $startzeit >= $endzeit) {
                    $fehlermeldung = 'Die Startzeit muss vor der Endzeit liegen.';
                }

                if ($fehlermeldung === null && $endzeit === null && $status !== 'laufend') {
                    $fehlermeldung = 'Ohne Endzeit ist nur der Status "laufend" zulässig.';
                }

                if ($fehlermeldung === null && $endzeit !== null && $status === 'laufend') {
                    $fehlermeldung = 'Bei gesetzter Endzeit darf der Status nicht "laufend" sein.';
                }

                if ($fehlermeldung === null) {
                    if (!in_array($status, ['laufend', 'abgeschlossen', 'abgebrochen', 'pausiert'], true)) {
                        $status = $endzeit === null ? 'laufend' : 'abgeschlossen';
                    }

                    $ok = $auftragszeitModel->aktualisiereAuftragszeitZeitraum(
                        $auftragszeitId,
                        $startzeit,
                        $endzeit,
                        $status,
                        $kommentar !== '' ? $kommentar : null
                    );

                    if ($ok) {
                        $code = $this->ermittleAuftragscode($datensatz, $auftragModel);
                        $_SESSION['auftrag_detail_flash_ok'] = 'Auftragszeit erfolgreich gespeichert.';
                        header('Location: ?seite=auftrag_detail&code=' . urlencode($code));
                        return;
                    }

                    $fehlermeldung = 'Die Auftragszeit konnte nicht gespeichert werden.';
                }
            }
        }

        $auftragscode = $this->ermittleAuftragscode($datensatz, $auftragModel);
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/auftragszeit/bearbeiten.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Formular für einen neuen Auftrag.
     * Route: ?seite=auftrag_neu
     */
    public function neu(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        $this->renderAuftragFormular([
            'id'               => 0,
            'auftragsnummer'   => '',
            'kurzbeschreibung' => '',
            'kunde'            => '',
            'zeichnungsnummer' => '',
            'status'           => '',
            'aktiv'            => 1,
        ], null);
    }

    /**
     * Formular für einen vorhandenen Auftrag.
     * Route: ?seite=auftrag_bearbeiten&id=...
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ?seite=auftrag');
            return;
        }

        $auftrag = null;
        try {
            $auftrag = $this->db->fetchEine('SELECT * FROM auftrag WHERE id = :id LIMIT 1', ['id' => $id]);
        } catch (\Throwable $e) {
            $this->protokolliere('Auftrag konnte nicht geladen werden', ['id' => $id, 'exception' => $e->getMessage()]);
        }

        if (!is_array($auftrag)) {
            $_SESSION['auftrag_flash_fehler'] = 'Der Auftrag wurde nicht gefunden.';
            header('Location: ?seite=auftrag');
            return;
        }

        $this->renderAuftragFormular($auftrag, null);
    }

    /**
     * Speichert einen Auftrag (anlegen oder ändern).
     * Route: ?seite=auftrag_speichern (POST)
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=auftrag');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH_STAMM)) {
            $_SESSION['auftrag_flash_fehler'] = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            header('Location: ?seite=auftrag');
            return;
        }

        $id               = (int)($_POST['id'] ?? 0);
        $auftragsnummer   = trim((string)($_POST['auftragsnummer'] ?? ''));
        $kurzbeschreibung = trim((string)($_POST['kurzbeschreibung'] ?? ''));
        $kunde            = trim((string)($_POST['kunde'] ?? ''));
        $zeichnungsnummer = trim((string)($_POST['zeichnungsnummer'] ?? ''));
        $status           = trim((string)($_POST['status'] ?? ''));
        $aktiv            = isset($_POST['aktiv']) ? 1 : 0;

        $daten = [
            'id'               => $id,
            'auftragsnummer'   => $auftragsnummer,
            'kurzbeschreibung' => $kurzbeschreibung,
            'kunde'            => $kunde,
            'zeichnungsnummer' => $zeichnungsnummer,
            'status'           => $status,
            'aktiv'            => $aktiv,
        ];

        if ($auftragsnummer === '') {
            $this->renderAuftragFormular($daten, 'Bitte eine Auftragsnummer angeben.');
            return;
        }

        if (mb_strlen($auftragsnummer) > 100) {
            $this->renderAuftragFormular($daten, 'Die Auftragsnummer darf höchstens 100 Zeichen lang sein.');
            return;
        }

        if (mb_strlen($zeichnungsnummer) > 100) {
            $this->renderAuftragFormular($daten, 'Die Zeichnungsnummer darf höchstens 100 Zeichen lang sein.');
            return;
        }

        // Das Dropdown im Browser ist keine Sicherung - der Wert wird hier
        // geprüft. Unbekannte Werte sind nur erlaubt, wenn sie schon vorher am
        // Auftrag standen (Altbestand aus der Freitext-Zeit).
        if ($status !== '' && !isset(self::STATUS_AUSWAHL[$status])) {
            $altwert = '';
            if ($id > 0) {
                try {
                    $vorher = $this->db->fetchEine('SELECT status FROM auftrag WHERE id = :id LIMIT 1', ['id' => $id]);
                    $altwert = is_array($vorher) ? trim((string)($vorher['status'] ?? '')) : '';
                } catch (\Throwable $e) {
                    $altwert = '';
                }
            }

            if ($status !== $altwert) {
                $this->renderAuftragFormular($daten, 'Der gewählte Status ist nicht zulässig.');
                return;
            }
        }

        // Doppelte Nummern abfangen, bevor die Datenbank einen Fehler wirft -
        // die Meldung soll verständlich sein, nicht technisch.
        try {
            $vorhanden = $this->db->fetchEine(
                'SELECT id FROM auftrag WHERE auftragsnummer = :nr AND id <> :id LIMIT 1',
                ['nr' => $auftragsnummer, 'id' => $id]
            );

            if (is_array($vorhanden)) {
                $this->renderAuftragFormular(
                    $daten,
                    'Die Auftragsnummer "' . $auftragsnummer . '" gibt es bereits. Bitte eine andere wählen oder den vorhandenen Auftrag öffnen.'
                );
                return;
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Prüfung auf doppelte Auftragsnummer fehlgeschlagen', ['exception' => $e->getMessage()]);
        }

        try {
            if ($id > 0) {
                $this->db->ausfuehren(
                    'UPDATE auftrag
                        SET auftragsnummer = :nr,
                            kurzbeschreibung = :kurz,
                            kunde = :kunde,
                            zeichnungsnummer = :zeichnung,
                            status = :status,
                            aktiv = :aktiv
                      WHERE id = :id',
                    [
                        'nr'        => $auftragsnummer,
                        'kurz'      => $kurzbeschreibung !== '' ? $kurzbeschreibung : null,
                        'kunde'     => $kunde !== '' ? $kunde : null,
                        'zeichnung' => $zeichnungsnummer !== '' ? $zeichnungsnummer : null,
                        'status'    => $status !== '' ? $status : null,
                        'aktiv'     => $aktiv,
                        'id'        => $id,
                    ]
                );
                $_SESSION['auftrag_detail_flash_ok'] = 'Der Auftrag wurde gespeichert.';
            } else {
                $this->db->ausfuehren(
                    'INSERT INTO auftrag (auftragsnummer, kurzbeschreibung, kunde, zeichnungsnummer, status, aktiv)
                     VALUES (:nr, :kurz, :kunde, :zeichnung, :status, :aktiv)',
                    [
                        'nr'        => $auftragsnummer,
                        'kurz'      => $kurzbeschreibung !== '' ? $kurzbeschreibung : null,
                        'kunde'     => $kunde !== '' ? $kunde : null,
                        'zeichnung' => $zeichnungsnummer !== '' ? $zeichnungsnummer : null,
                        'status'    => $status !== '' ? $status : null,
                        'aktiv'     => $aktiv,
                    ]
                );
                $neueAuftragId = (int)$this->db->letzteInsertId();
                $meldung = 'Der Auftrag wurde angelegt.';

                // Im Anlegen-Formular angehakte Standardschritte gleich
                // übernehmen - sonst muss man denselben Auftrag zweimal
                // anfassen, um ihn arbeitsfähig zu machen.
                $katalogIds = $this->leseKatalogAuswahlAusPost();
                if ($katalogIds !== [] && $neueAuftragId > 0) {
                    try {
                        [$uebernommen] = $this->uebernehmeKatalogSchritte($neueAuftragId, $katalogIds);
                        $meldung .= ' ' . ($uebernommen === 1
                            ? 'Ein Arbeitsschritt wurde aus dem Katalog übernommen.'
                            : $uebernommen . ' Arbeitsschritte wurden aus dem Katalog übernommen.');
                    } catch (\Throwable $e) {
                        $this->protokolliere('Katalogschritte beim Anlegen nicht übernommen', [
                            'auftrag_id' => $neueAuftragId,
                            'exception'  => $e->getMessage(),
                        ]);
                        $meldung .= ' Die ausgewählten Arbeitsschritte konnten nicht übernommen werden.';
                    }
                } else {
                    $meldung .= ' Jetzt können Arbeitsschritte ergänzt werden.';
                }

                $_SESSION['auftrag_detail_flash_ok'] = $meldung;
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Auftrag konnte nicht gespeichert werden', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);
            $this->renderAuftragFormular($daten, 'Der Auftrag konnte nicht gespeichert werden.');
            return;
        }

        header('Location: ?seite=auftrag_detail&code=' . urlencode($auftragsnummer));
    }

    /**
     * Rendert das Auftragsformular (anlegen und bearbeiten in einem).
     *
     * @param array<string,mixed> $auftrag
     */
    private function renderAuftragFormular(array $auftrag, ?string $fehlermeldung): void
    {
        $id               = (int)($auftrag['id'] ?? 0);
        $auftragsnummer   = (string)($auftrag['auftragsnummer'] ?? '');
        $kurzbeschreibung = (string)($auftrag['kurzbeschreibung'] ?? '');
        $kunde            = (string)($auftrag['kunde'] ?? '');
        $zeichnungsnummer = (string)($auftrag['zeichnungsnummer'] ?? '');
        $status           = (string)($auftrag['status'] ?? '');
        $aktiv            = (int)($auftrag['aktiv'] ?? 1) === 1;

        // Die View baut ihr CSRF-Feld selbst; sie bekommt dafür den
        // Bereichsnamen statt eines fertigen Tokens.
        $csrfBereich   = self::CSRF_BEREICH_STAMM;
        $statusAuswahl = self::STATUS_AUSWAHL;

        // Nur beim Anlegen: die aktiven Standardschritte zur Auswahl. Beim
        // Bearbeiten steht dieselbe Auswahl in der Auftragsansicht und weiß
        // dort zusätzlich, was der Auftrag schon hat.
        $katalogAuswahl = [];
        $katalogAngehakt = $id > 0 ? [] : $this->leseKatalogAuswahlAusPost();
        if ($id === 0) {
            try {
                $katalogAuswahl = $this->db->fetchAlle(
                    'SELECT id, code, bezeichnung FROM arbeitsschritt_katalog
                      WHERE aktiv = 1
                   ORDER BY sort_order ASC, code ASC'
                );
            } catch (\Throwable $e) {
                $katalogAuswahl = [];
                $this->protokolliere('Arbeitsschritt-Katalog für das Auftragsformular nicht ladbar', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        require __DIR__ . '/../views/auftrag/formular.php';
    }

    /**
     * Laufkarte zum Auftrag als PDF.
     * Route: ?seite=auftrag_laufkarte&code=...
     *
     * Bewusst ohne Verwaltungsrecht: Wer in der Werkstatt eine Laufkarte
     * nachdrucken muss, soll dafür kein Recht zum Ändern brauchen.
     */
    public function laufkarte(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '') {
            header('Location: ?seite=auftrag');
            return;
        }

        $auftrag = null;
        $schritte = [];

        try {
            $auftrag = $this->db->fetchEine(
                'SELECT * FROM auftrag WHERE auftragsnummer = :nr LIMIT 1',
                ['nr' => $code]
            );

            if (is_array($auftrag)) {
                // Nur aktive Schritte - inaktive gehören nicht auf einen Ausdruck.
                $schritte = $this->db->fetchAlle(
                    'SELECT * FROM auftrag_arbeitsschritt
                      WHERE auftrag_id = :aid AND aktiv = 1
                      ORDER BY id ASC',
                    ['aid' => (int)($auftrag['id'] ?? 0)]
                );

                // Fehlende Bezeichnungen aus dem Katalog nachschlagen, damit auf
                // der Laufkarte nicht nur nackte Codes stehen.
                $schritte = $this->ergaenzeBezeichnungenAusKatalog($schritte);
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Laufkarte konnte nicht geladen werden', [
                'code'      => $code,
                'exception' => $e->getMessage(),
            ]);
        }

        if (!is_array($auftrag)) {
            $_SESSION['auftrag_flash_fehler'] = 'Zu dieser Auftragsnummer gibt es keinen Stammdatensatz. Bitte den Auftrag zuerst anlegen.';
            header('Location: ?seite=auftrag_detail&code=' . urlencode($code));
            return;
        }

        $pdf = PDFService::getInstanz()->erzeugeLaufkartePdf($auftrag, $schritte);

        if ($pdf === '') {
            $_SESSION['auftrag_flash_fehler'] = 'Die Laufkarte konnte nicht erzeugt werden.';
            header('Location: ?seite=auftrag_detail&code=' . urlencode($code));
            return;
        }

        // Dateiname aus der Auftragsnummer, auf unbedenkliche Zeichen reduziert.
        $dateiname = preg_replace('~[^A-Za-z0-9_.-]+~', '_', $code);
        $dateiname = trim((string)$dateiname, '_');
        if ($dateiname === '') {
            $dateiname = 'auftrag';
        }

        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: inline; filename="laufkarte_' . $dateiname . '.pdf"');

        echo $pdf;
    }

    /**
     * Formular für einen vorhandenen Arbeitsschritt.
     * Route: ?seite=auftrag_schritt_bearbeiten&id=...
     */
    public function schrittBearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ?seite=auftrag');
            return;
        }

        $schritt = null;
        try {
            $schritt = $this->db->fetchEine(
                'SELECT s.*, a.auftragsnummer
                   FROM auftrag_arbeitsschritt s
                   JOIN auftrag a ON a.id = s.auftrag_id
                  WHERE s.id = :id
                  LIMIT 1',
                ['id' => $id]
            );
        } catch (\Throwable $e) {
            $this->protokolliere('Arbeitsschritt konnte nicht geladen werden', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);
        }

        if (!is_array($schritt)) {
            $_SESSION['auftrag_flash_fehler'] = 'Der Arbeitsschritt wurde nicht gefunden.';
            header('Location: ?seite=auftrag');
            return;
        }

        $this->renderSchrittFormular($schritt, null);
    }

    /**
     * Speichert einen Arbeitsschritt (anlegen oder ändern).
     * Route: ?seite=auftrag_schritt_speichern (POST)
     */
    public function schrittSpeichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=auftrag');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH_STAMM)) {
            $_SESSION['auftrag_flash_fehler'] = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            header('Location: ?seite=auftrag');
            return;
        }

        $schrittId   = (int)($_POST['schritt_id'] ?? 0);
        $auftragId   = (int)($_POST['auftrag_id'] ?? 0);
        $schrittCode = trim((string)($_POST['arbeitsschritt_code'] ?? ''));
        $bezeichnung = trim((string)($_POST['bezeichnung'] ?? ''));
        $aktiv       = isset($_POST['aktiv']) ? 1 : 0;

        // Auftragsnummer für die Rückleitung ermitteln.
        $auftragsnummer = '';
        try {
            $auftrag = $this->db->fetchEine('SELECT auftragsnummer FROM auftrag WHERE id = :id LIMIT 1', ['id' => $auftragId]);
            if (is_array($auftrag)) {
                $auftragsnummer = (string)($auftrag['auftragsnummer'] ?? '');
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Auftrag zum Arbeitsschritt nicht ermittelbar', [
                'auftrag_id' => $auftragId,
                'exception'  => $e->getMessage(),
            ]);
        }

        if ($auftragsnummer === '') {
            $_SESSION['auftrag_flash_fehler'] = 'Der zugehörige Auftrag wurde nicht gefunden.';
            header('Location: ?seite=auftrag');
            return;
        }

        $daten = [
            'id'                  => $schrittId,
            'auftrag_id'          => $auftragId,
            'auftragsnummer'      => $auftragsnummer,
            'arbeitsschritt_code' => $schrittCode,
            'bezeichnung'         => $bezeichnung,
            'aktiv'               => $aktiv,
        ];

        if ($schrittCode === '') {
            $this->renderSchrittFormular($daten, 'Bitte einen Code für den Arbeitsschritt angeben.');
            return;
        }

        if (mb_strlen($schrittCode) > 100) {
            $this->renderSchrittFormular($daten, 'Der Code darf höchstens 100 Zeichen lang sein.');
            return;
        }

        // Codes sind pro Auftrag eindeutig (UNIQUE auftrag_id + code).
        try {
            $vorhanden = $this->db->fetchEine(
                'SELECT id FROM auftrag_arbeitsschritt
                  WHERE auftrag_id = :aid AND arbeitsschritt_code = :code AND id <> :id
                  LIMIT 1',
                ['aid' => $auftragId, 'code' => $schrittCode, 'id' => $schrittId]
            );

            if (is_array($vorhanden)) {
                $this->renderSchrittFormular(
                    $daten,
                    'Der Code "' . $schrittCode . '" ist bei diesem Auftrag schon vergeben.'
                );
                return;
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Prüfung auf doppelten Arbeitsschritt-Code fehlgeschlagen', [
                'exception' => $e->getMessage(),
            ]);
        }

        try {
            if ($schrittId > 0) {
                $this->db->ausfuehren(
                    'UPDATE auftrag_arbeitsschritt
                        SET arbeitsschritt_code = :code,
                            bezeichnung = :bez,
                            aktiv = :aktiv
                      WHERE id = :id',
                    [
                        'code'  => $schrittCode,
                        'bez'   => $bezeichnung !== '' ? $bezeichnung : null,
                        'aktiv' => $aktiv,
                        'id'    => $schrittId,
                    ]
                );
                $_SESSION['auftrag_detail_flash_ok'] = 'Der Arbeitsschritt wurde gespeichert.';
            } else {
                $this->db->ausfuehren(
                    'INSERT INTO auftrag_arbeitsschritt (auftrag_id, arbeitsschritt_code, bezeichnung, aktiv)
                     VALUES (:aid, :code, :bez, :aktiv)',
                    [
                        'aid'   => $auftragId,
                        'code'  => $schrittCode,
                        'bez'   => $bezeichnung !== '' ? $bezeichnung : null,
                        'aktiv' => $aktiv,
                    ]
                );
                $_SESSION['auftrag_detail_flash_ok'] = 'Der Arbeitsschritt wurde angelegt.';
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Arbeitsschritt konnte nicht gespeichert werden', [
                'schritt_id' => $schrittId,
                'exception'  => $e->getMessage(),
            ]);
            $this->renderSchrittFormular($daten, 'Der Arbeitsschritt konnte nicht gespeichert werden.');
            return;
        }

        header('Location: ?seite=auftrag_detail&code=' . urlencode($auftragsnummer));
    }

    /**
     * Rendert das Formular für einen Arbeitsschritt.
     *
     * @param array<string,mixed> $schritt
     */
    private function renderSchrittFormular(array $schritt, ?string $fehlermeldung): void
    {
        $id             = (int)($schritt['id'] ?? 0);
        $auftragId      = (int)($schritt['auftrag_id'] ?? 0);
        $auftragsnummer = (string)($schritt['auftragsnummer'] ?? '');
        $code           = (string)($schritt['arbeitsschritt_code'] ?? '');
        $bezeichnung    = (string)($schritt['bezeichnung'] ?? '');
        $aktiv          = (int)($schritt['aktiv'] ?? 1) === 1;

        // Die View baut ihr CSRF-Feld selbst; sie bekommt dafür den
        // Bereichsnamen statt eines fertigen Tokens.
        $csrfBereich = self::CSRF_BEREICH_STAMM;

        require __DIR__ . '/../views/auftrag/schritt_formular.php';
    }

    /**
     * Hängt Katalogschritte an einen Auftrag.
     *
     * Eine Stelle für zwei Wege: die Übernahme aus der Auftragsansicht
     * (`schritteAusKatalog()`) und die Auswahl direkt im Anlegen-Formular
     * (`speichern()`). Vorhandene Codes bleiben unverändert - eine am Auftrag
     * gepflegte Bezeichnung ist die speziellere und soll gewinnen.
     *
     * @param array<int,int> $katalogIds
     * @return array{0:int,1:int} übernommen, übersprungen
     */
    private function uebernehmeKatalogSchritte(int $auftragId, array $katalogIds): array
    {
        if ($auftragId <= 0 || $katalogIds === []) {
            return [0, 0];
        }

        $uebernommen   = 0;
        $uebersprungen = 0;

        // `aktiv = 1` auch hier, nicht nur beim Anbieten: Angeboten werden
        // ausschliesslich aktive Schritte, aber ein Häkchen im Browser ist
        // keine Zusicherung. Wird ein Schritt zwischen Seitenaufbau und
        // Absenden stillgelegt, soll er nicht doch noch an den Auftrag kommen.
        $platzhalter = implode(',', array_fill(0, count($katalogIds), '?'));
        $eintraege = $this->db->fetchAlle(
            'SELECT code, bezeichnung FROM arbeitsschritt_katalog
              WHERE aktiv = 1 AND id IN (' . $platzhalter . ')',
            array_values($katalogIds)
        );

        foreach ($eintraege as $eintrag) {
            $code = trim((string)($eintrag['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $betroffen = $this->db->ausfuehren(
                'INSERT INTO auftrag_arbeitsschritt (auftrag_id, arbeitsschritt_code, bezeichnung, aktiv)
                 VALUES (:aid, :code, :bez, 1)
                 ON DUPLICATE KEY UPDATE arbeitsschritt_code = arbeitsschritt_code',
                [
                    'aid'  => $auftragId,
                    'code' => $code,
                    'bez'  => ($eintrag['bezeichnung'] ?? null) !== '' ? $eintrag['bezeichnung'] : null,
                ]
            );

            // MySQL liefert 1 für INSERT, 0 wenn der Eintrag schon existierte.
            if ($betroffen > 0) {
                $uebernommen++;
            } else {
                $uebersprungen++;
            }
        }

        return [$uebernommen, $uebersprungen];
    }

    /**
     * Liest die angehakten Katalog-IDs aus dem POST, entdoppelt sie und wirft
     * alles weg, was keine positive Zahl ist.
     *
     * @return array<int,int>
     */
    private function leseKatalogAuswahlAusPost(): array
    {
        $auswahl = $_POST['katalog_ids'] ?? [];
        if (!is_array($auswahl)) {
            return [];
        }

        $ids = [];
        foreach ($auswahl as $wert) {
            $id = (int)$wert;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return $ids;
    }

    /**
     * Übernimmt ausgewählte Katalogschritte in einen vorhandenen Auftrag.
     * Route: ?seite=auftrag_schritte_aus_katalog (POST)
     */
    public function schritteAusKatalog(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfAuftraegeVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=auftrag');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH_STAMM)) {
            $_SESSION['auftrag_flash_fehler'] = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            header('Location: ?seite=auftrag');
            return;
        }

        $auftragId = (int)($_POST['auftrag_id'] ?? 0);
        $ids       = $this->leseKatalogAuswahlAusPost();

        $auftragsnummer = '';
        try {
            $auftrag = $this->db->fetchEine('SELECT auftragsnummer FROM auftrag WHERE id = :id LIMIT 1', ['id' => $auftragId]);
            if (is_array($auftrag)) {
                $auftragsnummer = (string)($auftrag['auftragsnummer'] ?? '');
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Auftrag für Katalog-Übernahme nicht ermittelbar', [
                'auftrag_id' => $auftragId,
                'exception'  => $e->getMessage(),
            ]);
        }

        if ($auftragsnummer === '') {
            $_SESSION['auftrag_flash_fehler'] = 'Der zugehörige Auftrag wurde nicht gefunden.';
            header('Location: ?seite=auftrag');
            return;
        }

        if ($ids === []) {
            $_SESSION['auftrag_detail_flash_fehler'] = 'Es war kein Arbeitsschritt ausgewählt.';
            header('Location: ?seite=auftrag_detail&code=' . urlencode($auftragsnummer));
            return;
        }

        $uebernommen = 0;
        $uebersprungen = 0;
        try {
            [$uebernommen, $uebersprungen] = $this->uebernehmeKatalogSchritte($auftragId, $ids);
        } catch (\Throwable $e) {
            $this->protokolliere('Katalogschritte konnten nicht übernommen werden', [
                'auftrag_id' => $auftragId,
                'exception'  => $e->getMessage(),
            ]);
            $_SESSION['auftrag_detail_flash_fehler'] = 'Die Arbeitsschritte konnten nicht übernommen werden.';
            header('Location: ?seite=auftrag_detail&code=' . urlencode($auftragsnummer));
            return;
        }

        $meldung = $uebernommen === 1
            ? 'Ein Arbeitsschritt wurde übernommen.'
            : $uebernommen . ' Arbeitsschritte wurden übernommen.';

        if ($uebersprungen > 0) {
            $meldung .= ' ' . $uebersprungen . ' waren bereits vorhanden und blieben unverändert.';
        }

        $_SESSION['auftrag_detail_flash_ok'] = $meldung;
        header('Location: ?seite=auftrag_detail&code=' . urlencode($auftragsnummer));
    }

    /**
     * Ergänzt fehlende Bezeichnungen aus dem Arbeitsschritt-Katalog.
     *
     * Legt das Terminal beim Scannen einen Arbeitsschritt automatisch an, hat
     * dieser nur den Code und keine Bezeichnung. Statt in den Buchungspfad
     * einzugreifen, wird die Bezeichnung hier beim Anzeigen nachgeschlagen:
     *
     * - Eine Buchung kann dadurch niemals scheitern – das ist die oberste
     *   Regel in der Halle.
     * - Es wirkt auch für Buchungen, die über die Offline-Queue nachlaufen.
     * - Eine am Auftrag gepflegte Bezeichnung bleibt unberührt; sie ist die
     *   speziellere und gewinnt.
     *
     * Fällt der Katalog aus (z. B. Tabelle fehlt, weil die Migration noch
     * nicht eingespielt ist), bleibt einfach alles wie vorher.
     *
     * @param array<int,array<string,mixed>> $schritte
     * @return array<int,array<string,mixed>>
     */
    private function ergaenzeBezeichnungenAusKatalog(array $schritte): array
    {
        $offeneCodes = [];
        foreach ($schritte as $schritt) {
            $bezeichnung = trim((string)($schritt['bezeichnung'] ?? ''));
            $code = trim((string)($schritt['arbeitsschritt_code'] ?? ''));
            if ($bezeichnung === '' && $code !== '') {
                $offeneCodes[$code] = $code;
            }
        }

        if ($offeneCodes === []) {
            return $schritte;
        }

        $ausKatalog = [];
        try {
            $platzhalter = implode(',', array_fill(0, count($offeneCodes), '?'));
            $treffer = $this->db->fetchAlle(
                'SELECT code, bezeichnung FROM arbeitsschritt_katalog WHERE code IN (' . $platzhalter . ')',
                array_values($offeneCodes)
            );

            foreach ($treffer as $eintrag) {
                $code = trim((string)($eintrag['code'] ?? ''));
                $bez  = trim((string)($eintrag['bezeichnung'] ?? ''));
                if ($code !== '' && $bez !== '') {
                    $ausKatalog[$code] = $bez;
                }
            }
        } catch (\Throwable $e) {
            // Bewusst still: Ohne Katalog bleibt es beim nackten Code.
            return $schritte;
        }

        foreach ($schritte as $index => $schritt) {
            $code = trim((string)($schritt['arbeitsschritt_code'] ?? ''));
            if (trim((string)($schritt['bezeichnung'] ?? '')) === '' && isset($ausKatalog[$code])) {
                $schritte[$index]['bezeichnung'] = $ausKatalog[$code];
                $schritte[$index]['bezeichnung_aus_katalog'] = true;
            }
        }

        return $schritte;
    }

    /**
     * Einheitlicher Hinweis, wenn das Verwaltungsrecht fehlt.
     */
    private function zeigeKeinRecht(): void
    {
        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Keine Berechtigung</h2>
            <p>Zum Anlegen und Bearbeiten von Aufträgen wird das Recht <code>AUFTRAEGE_VERWALTEN</code> benötigt.</p>
            <p><a class="button-link quiet" href="?seite=auftrag">&laquo; Zurück zur Auftragsliste</a></p>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * @param array<string,mixed> $kontext
     */
    private function protokolliere(string $nachricht, array $kontext): void
    {
        Logger::error($nachricht, $kontext, null, null, 'auftrag');
    }

    private function ermittleAuftragscode(array $auftragszeit, AuftragModel $auftragModel): string
    {
        $auftragscode = trim((string)($auftragszeit['auftragscode'] ?? ''));
        if ($auftragscode !== '') {
            return $auftragscode;
        }

        $auftragId = (int)($auftragszeit['auftrag_id'] ?? 0);
        if ($auftragId > 0) {
            $auftrag = $auftragModel->holeNachId($auftragId);
            if (is_array($auftrag)) {
                $nr = trim((string)($auftrag['auftragsnummer'] ?? ''));
                if ($nr !== '') {
                    return $nr;
                }
            }
        }

        return '';
    }

    /**
     * Darf der angemeldete Benutzer Auftragsstammdaten pflegen?
     *
     * Bewusst nur für Anlegen/Bearbeiten. Ansehen der Aufträge und das
     * Laufkarten-PDF bleiben ohne dieses Recht erreichbar - wer in der Werkstatt
     * eine Laufkarte nachdruckt, soll dafür kein Verwaltungsrecht brauchen.
     *
     * Die Legacy-Rollen werden wie an den anderen Stellen dieses Controllers
     * mitgeprüft, damit bestehende Installationen ohne Rechtevergabe
     * weiterarbeiten können.
     */
    private function darfAuftraegeVerwalten(): bool
    {
        $legacyAdmin = (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        );

        return $this->authService->hatRecht('AUFTRAEGE_VERWALTEN') || $legacyAdmin;
    }

    private function darfAuftragszeitAlleBearbeiten(): bool
    {
        $legacyAdmin = (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        );

        return $this->authService->hatRecht('ZEITBUCHUNG_EDIT_ALL') || $legacyAdmin;
    }

    private function darfAuftragszeitEigeneBearbeiten(): bool
    {
        $legacyAdmin = (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        );

        return $this->authService->hatRecht('ZEITBUCHUNG_EDIT_SELF') || $legacyAdmin;
    }

    private function parseDatumUhrzeit(string $datum, string $uhrzeit): ?\DateTimeImmutable
    {
        $datum = trim($datum);
        $uhrzeit = trim($uhrzeit);
        if ($datum === '' || $uhrzeit === '') {
            return null;
        }

        $wert = $datum . ' ' . $uhrzeit;
        $zeit = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $wert);
        if (!($zeit instanceof \DateTimeImmutable)) {
            return null;
        }

        if ($zeit->format('Y-m-d H:i') !== $wert) {
            return null;
        }

        return $zeit;
    }

    private function formatDatumFuerForm(string $datetime): string
    {
        $datetime = trim($datetime);
        if ($datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }

        return date('Y-m-d', $ts);
    }

    private function formatUhrzeitFuerForm(string $datetime): string
    {
        $datetime = trim($datetime);
        if ($datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }

        return date('H:i', $ts);
    }
}
