<?php
declare(strict_types=1);

/**
 * UrlaubJahresuebersichtController
 *
 * Read-only Jahresuebersicht fuer Urlaub.
 */
class UrlaubJahresuebersichtController
{
    private AuthService $authService;
    private Database $db;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->db = Database::getInstanz();
    }

    public function index(): void
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return;
        }

        $mitarbeiter = $this->authService->holeAngemeldetenMitarbeiter();
        $angemeldeterId = is_array($mitarbeiter) ? (int)($mitarbeiter['id'] ?? 0) : 0;
        if ($angemeldeterId <= 0) {
            http_response_code(403);
            $fehlermeldung = 'Fehler: Mitarbeiterdaten nicht gefunden.';
            require __DIR__ . '/../views/urlaub/jahresuebersicht.php';
            return;
        }

        $rechte = $this->ermittleRechte($angemeldeterId);
        if (!$rechte['darf_alle'] && !$rechte['darf_bereich'] && !$rechte['darf_self']) {
            http_response_code(403);
            $fehlermeldung = 'Sie haben keine Berechtigung fuer die Urlaub Jahresuebersicht.';
            require __DIR__ . '/../views/urlaub/jahresuebersicht.php';
            return;
        }

        $jahr = $this->leseJahr();
        $wochenendenAnzeigen = isset($_GET['wochenende']) && (string)$_GET['wochenende'] === '1';

        $mitarbeiterListe = $this->ladeMitarbeiterFuerRechte($angemeldeterId, $rechte);
        $mitarbeiterIds = array_map(
            static fn (array $row): int => (int)($row['id'] ?? 0),
            $mitarbeiterListe
        );
        $mitarbeiterIds = array_values(array_filter($mitarbeiterIds, static fn (int $id): bool => $id > 0));

        $kannAntraegeGenehmigen = $this->ermittleGenehmigungsMap($angemeldeterId, $rechte, $mitarbeiterIds);
        $monatswerte = $this->ladeMonatswerte($mitarbeiterIds, $jahr);
        $stundenSalden = $this->ladeStundenkontoSalden($mitarbeiterIds, $jahr);
        $events = $this->baueEvents($mitarbeiterIds, $jahr);

        $planung = $this->bauePlanung(
            $mitarbeiterListe,
            $jahr,
            $wochenendenAnzeigen,
            $monatswerte,
            $stundenSalden,
            $events,
            $kannAntraegeGenehmigen
        );

        $fehlermeldung = null;
        require __DIR__ . '/../views/urlaub/jahresuebersicht.php';
    }

    /**
     * @return array{darf_alle:bool,darf_bereich:bool,darf_self:bool}
     */
    private function ermittleRechte(int $angemeldeterId): array
    {
        $legacyAdmin = false;
        if (method_exists($this->authService, 'hatRolle')) {
            $legacyAdmin = $this->authService->hatRolle('Chef')
                || $this->authService->hatRolle('Personalbuero')
                || $this->authService->hatRolle('Personalbüro');
        }

        return [
            'darf_alle' => $legacyAdmin || $this->authService->hatRecht('URLAUB_GENEHMIGEN_ALLE'),
            'darf_bereich' => $this->authService->hatRecht('URLAUB_GENEHMIGEN'),
            'darf_self' => $this->authService->hatRecht('URLAUB_GENEHMIGEN_SELF'),
        ];
    }

    private function leseJahr(): int
    {
        $jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
        if ($jahr < 2000 || $jahr > 2100) {
            $jahr = (int)date('Y');
        }
        return $jahr;
    }

    /**
     * @param array{darf_alle:bool,darf_bereich:bool,darf_self:bool} $rechte
     * @return array<int,array<string,mixed>>
     */
    private function ladeMitarbeiterFuerRechte(int $angemeldeterId, array $rechte): array
    {
        $model = new MitarbeiterModel();

        if ($rechte['darf_alle']) {
            return $this->normalisiereMitarbeiterListe($model->holeAlleAktiven());
        }

        $ids = [];

        if ($rechte['darf_bereich']) {
            try {
                $genehmigerModel = new MitarbeiterGenehmigerModel();
                foreach ($genehmigerModel->holeMitarbeiterFuerGenehmiger($angemeldeterId) as $row) {
                    $mid = (int)($row['mitarbeiter_id'] ?? 0);
                    if ($mid > 0) {
                        $ids[$mid] = true;
                    }
                }
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::warn('Urlaub-Jahresuebersicht: Genehmiger-Scope konnte nicht geladen werden', [
                        'genehmiger_id' => $angemeldeterId,
                        'exception' => $e->getMessage(),
                    ], $angemeldeterId, null, 'urlaub_jahresuebersicht');
                }
            }
        }

        if ($rechte['darf_self']) {
            $ids[$angemeldeterId] = true;
        }

        if ($ids === []) {
            return [];
        }

        return $this->normalisiereMitarbeiterListe($model->holeNachIds(array_keys($ids), true));
    }

    /**
     * @param array<int,array<string,mixed>> $liste
     * @return array<int,array<string,mixed>>
     */
    private function normalisiereMitarbeiterListe(array $liste): array
    {
        $out = [];
        foreach ($liste as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string)($row['vorname'] ?? '') . ' ' . (string)($row['nachname'] ?? ''));
            if ($name === '') {
                $name = trim((string)($row['benutzername'] ?? ''));
            }
            if ($name === '') {
                $name = 'Mitarbeiter #' . $id;
            }

            $row['anzeige_name'] = $name;
            $out[] = $row;
        }

        usort($out, static function (array $a, array $b): int {
            return strcasecmp((string)($a['anzeige_name'] ?? ''), (string)($b['anzeige_name'] ?? ''));
        });

        return $out;
    }

    /**
     * @param array{darf_alle:bool,darf_bereich:bool,darf_self:bool} $rechte
     * @param int[] $mitarbeiterIds
     * @return array<int,bool>
     */
    private function ermittleGenehmigungsMap(int $angemeldeterId, array $rechte, array $mitarbeiterIds): array
    {
        $map = [];
        foreach ($mitarbeiterIds as $id) {
            $map[(int)$id] = false;
        }

        if ($rechte['darf_alle']) {
            foreach ($mitarbeiterIds as $id) {
                $map[(int)$id] = true;
            }
            return $map;
        }

        if ($rechte['darf_bereich']) {
            try {
                $genehmigerModel = new MitarbeiterGenehmigerModel();
                foreach ($genehmigerModel->holeMitarbeiterFuerGenehmiger($angemeldeterId) as $row) {
                    $mid = (int)($row['mitarbeiter_id'] ?? 0);
                    if ($mid > 0 && array_key_exists($mid, $map)) {
                        $map[$mid] = true;
                    }
                }
            } catch (\Throwable $e) {
                // Best effort; Sichtbarkeit bleibt, Klicks bleiben gesperrt.
            }
        }

        if ($rechte['darf_self'] && array_key_exists($angemeldeterId, $map)) {
            $map[$angemeldeterId] = true;
        }

        return $map;
    }

    /**
     * @param int[] $mitarbeiterIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function ladeMonatswerte(array $mitarbeiterIds, int $jahr): array
    {
        if ($mitarbeiterIds === []) {
            return [];
        }

        $params = ['jahr' => $jahr];
        $in = $this->baueInPlatzhalter($mitarbeiterIds, 'mid', $params);

        try {
            $rows = $this->db->fetchAlle(
                'SELECT *
                   FROM monatswerte_mitarbeiter
                  WHERE jahr = :jahr
                    AND mitarbeiter_id IN (' . $in . ')',
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $monat = (int)($row['monat'] ?? 0);
            $mid = (int)($row['mitarbeiter_id'] ?? 0);
            if ($monat >= 1 && $monat <= 12 && $mid > 0) {
                $out[$monat][$mid] = $row;
            }
        }

        return $out;
    }

    /**
     * @param int[] $mitarbeiterIds
     * @return array<int,array<int,int>>
     */
    private function ladeStundenkontoSalden(array $mitarbeiterIds, int $jahr): array
    {
        if ($mitarbeiterIds === []) {
            return [];
        }

        $out = [];
        for ($monat = 1; $monat <= 12; $monat++) {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
            $bis = $start->modify('+1 month')->format('Y-m-d');

            $params = ['bis' => $bis];
            $in = $this->baueInPlatzhalter($mitarbeiterIds, 'mid' . $monat . '_', $params);

            try {
                $rows = $this->db->fetchAlle(
                    'SELECT mitarbeiter_id, COALESCE(SUM(delta_minuten), 0) AS sum_min
                       FROM stundenkonto_korrektur
                      WHERE wirksam_datum < :bis
                        AND mitarbeiter_id IN (' . $in . ')
                      GROUP BY mitarbeiter_id',
                    $params
                );
            } catch (\Throwable $e) {
                $rows = [];
            }

            foreach ($mitarbeiterIds as $mid) {
                $out[$monat][(int)$mid] = 0;
            }
            foreach ($rows as $row) {
                $mid = (int)($row['mitarbeiter_id'] ?? 0);
                if ($mid > 0) {
                    $out[$monat][$mid] = (int)($row['sum_min'] ?? 0);
                }
            }
        }

        return $out;
    }

    /**
     * @param int[] $mitarbeiterIds
     * @return array<int,array<int,array<int,array<string,mixed>>>>
     */
    private function baueEvents(array $mitarbeiterIds, int $jahr): array
    {
        $events = [];
        $this->fuegeFeiertageEin($events, $mitarbeiterIds, $jahr);
        $this->fuegeBetriebsferienEin($events, $mitarbeiterIds, $jahr);
        $this->fuegeUrlaubsantraegeEin($events, $mitarbeiterIds, $jahr);
        return $events;
    }

    /**
     * @param array<int,array<int,array<int,array<string,mixed>>>> $events
     * @param int[] $mitarbeiterIds
     */
    private function fuegeUrlaubsantraegeEin(array &$events, array $mitarbeiterIds, int $jahr): void
    {
        if ($mitarbeiterIds === []) {
            return;
        }

        $start = sprintf('%04d-01-01', $jahr);
        $ende = sprintf('%04d-12-31', $jahr);
        $params = ['start' => $start, 'ende' => $ende];
        $in = $this->baueInPlatzhalter($mitarbeiterIds, 'u', $params);

        try {
            $rows = $this->db->fetchAlle(
                "SELECT id, mitarbeiter_id, von_datum, bis_datum, status
                   FROM urlaubsantrag
                  WHERE status IN ('genehmigt', 'offen')
                    AND mitarbeiter_id IN (" . $in . ")
                    AND NOT (bis_datum < :start OR von_datum > :ende)",
                $params
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $mid = (int)($row['mitarbeiter_id'] ?? 0);
            $status = (string)($row['status'] ?? '');
            $id = (int)($row['id'] ?? 0);
            if ($mid <= 0 || ($status !== 'genehmigt' && $status !== 'offen')) {
                continue;
            }

            $code = $status === 'genehmigt' ? 'U' : 'O';
            $class = $status === 'genehmigt' ? 'u' : 'open';
            $label = $status === 'genehmigt'
                ? 'genehmigter Urlaub'
                : 'beantragter Urlaub, noch nicht genehmigt';

            $this->fuegeRangeEventEin($events, $mid, (string)$row['von_datum'], (string)$row['bis_datum'], $jahr, [
                'code' => $code,
                'class' => $class,
                'label' => $label,
                'priority' => $status === 'offen' ? 40 : 35,
                'antrag_id' => $id,
                'status' => $status,
            ]);
        }
    }

    /**
     * @param array<int,array<int,array<int,array<string,mixed>>>> $events
     * @param int[] $mitarbeiterIds
     */
    private function fuegeFeiertageEin(array &$events, array $mitarbeiterIds, int $jahr): void
    {
        if ($mitarbeiterIds === []) {
            return;
        }

        try {
            $rows = $this->db->fetchAlle(
                'SELECT datum, MIN(name) AS name
                   FROM feiertag
                  WHERE ist_betriebsfrei = 1
                    AND datum BETWEEN :start AND :ende
                  GROUP BY datum',
                [
                    'start' => sprintf('%04d-01-01', $jahr),
                    'ende' => sprintf('%04d-12-31', $jahr),
                ]
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $datum = (string)($row['datum'] ?? '');
            if ($datum === '') {
                continue;
            }
            foreach ($mitarbeiterIds as $mid) {
                $this->fuegeRangeEventEin($events, (int)$mid, $datum, $datum, $jahr, [
                    'code' => 'FT',
                    'class' => 'ft',
                    'label' => 'Feiertag' . (!empty($row['name']) ? ': ' . (string)$row['name'] : ''),
                    'priority' => 10,
                ]);
            }
        }
    }

    /**
     * @param array<int,array<int,array<int,array<string,mixed>>>> $events
     * @param int[] $mitarbeiterIds
     */
    private function fuegeBetriebsferienEin(array &$events, array $mitarbeiterIds, int $jahr): void
    {
        if ($mitarbeiterIds === []) {
            return;
        }

        $abteilungen = $this->ladeAbteilungenFuerMitarbeiter($mitarbeiterIds);

        try {
            $rows = $this->db->fetchAlle(
                'SELECT id, von_datum, bis_datum, beschreibung, abteilung_id
                   FROM betriebsferien
                  WHERE aktiv = 1
                    AND NOT (bis_datum < :start OR von_datum > :ende)',
                [
                    'start' => sprintf('%04d-01-01', $jahr),
                    'ende' => sprintf('%04d-12-31', $jahr),
                ]
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $abteilungId = isset($row['abteilung_id']) && $row['abteilung_id'] !== null
                ? (int)$row['abteilung_id']
                : null;

            foreach ($mitarbeiterIds as $mid) {
                $mid = (int)$mid;
                if ($abteilungId !== null && empty($abteilungen[$mid][$abteilungId])) {
                    continue;
                }

                $this->fuegeRangeEventEin($events, $mid, (string)$row['von_datum'], (string)$row['bis_datum'], $jahr, [
                    'code' => 'BF',
                    'class' => 'bf',
                    'label' => 'Betriebsferien' . (!empty($row['beschreibung']) ? ': ' . (string)$row['beschreibung'] : ''),
                    'priority' => 20,
                ]);
            }
        }
    }

    /**
     * @param int[] $mitarbeiterIds
     * @return array<int,array<int,bool>>
     */
    private function ladeAbteilungenFuerMitarbeiter(array $mitarbeiterIds): array
    {
        $out = [];
        foreach ($mitarbeiterIds as $mid) {
            $out[(int)$mid] = [];
        }

        $params = [];
        $in = $this->baueInPlatzhalter($mitarbeiterIds, 'ab', $params);

        try {
            $rows = $this->db->fetchAlle(
                'SELECT mitarbeiter_id, abteilung_id
                   FROM mitarbeiter_hat_abteilung
                  WHERE mitarbeiter_id IN (' . $in . ')',
                $params
            );
        } catch (\Throwable $e) {
            return $out;
        }

        foreach ($rows as $row) {
            $mid = (int)($row['mitarbeiter_id'] ?? 0);
            $aid = (int)($row['abteilung_id'] ?? 0);
            if ($mid > 0 && $aid > 0) {
                $out[$mid][$aid] = true;
            }
        }

        return $out;
    }

    /**
     * @param array<int,array<int,array<int,array<string,mixed>>>> $events
     * @param array<string,mixed> $event
     */
    private function fuegeRangeEventEin(array &$events, int $mitarbeiterId, string $von, string $bis, int $jahr, array $event): void
    {
        try {
            $start = new \DateTimeImmutable($von);
            $ende = new \DateTimeImmutable($bis);
            $jahrStart = new \DateTimeImmutable(sprintf('%04d-01-01', $jahr));
            $jahrEnde = new \DateTimeImmutable(sprintf('%04d-12-31', $jahr));
        } catch (\Throwable $e) {
            return;
        }

        if ($start > $ende) {
            return;
        }

        if ($start < $jahrStart) {
            $start = $jahrStart;
        }
        if ($ende > $jahrEnde) {
            $ende = $jahrEnde;
        }

        for ($tag = $start; $tag <= $ende; $tag = $tag->modify('+1 day')) {
            $monat = (int)$tag->format('n');
            $day = (int)$tag->format('j');
            $aktuell = $events[$monat][$mitarbeiterId][$day] ?? null;
            if (!is_array($aktuell) || (int)($event['priority'] ?? 0) >= (int)($aktuell['priority'] ?? 0)) {
                $events[$monat][$mitarbeiterId][$day] = $event;
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $mitarbeiterListe
     * @param array<int,array<int,array<string,mixed>>> $monatswerte
     * @param array<int,array<int,int>> $stundenSalden
     * @param array<int,array<int,array<int,array<string,mixed>>>> $events
     * @param array<int,bool> $kannAntraegeGenehmigen
     * @return array<int,array<string,mixed>>
     */
    private function bauePlanung(
        array $mitarbeiterListe,
        int $jahr,
        bool $wochenendenAnzeigen,
        array $monatswerte,
        array $stundenSalden,
        array $events,
        array $kannAntraegeGenehmigen
    ): array {
        $monatsnamen = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'Maerz',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ];

        $planung = [];
        for ($monat = 1; $monat <= 12; $monat++) {
            $tage = $this->baueTage($jahr, $monat, $wochenendenAnzeigen);
            $rows = [];
            $abgeschlossenCount = 0;

            foreach ($mitarbeiterListe as $mitarbeiter) {
                $mid = (int)($mitarbeiter['id'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }

                $monatswert = $monatswerte[$monat][$mid] ?? null;
                $istAbgeschlossen = is_array($monatswert);
                if ($istAbgeschlossen) {
                    $abgeschlossenCount++;
                }

                $cells = [];
                foreach ($tage as $tag) {
                    $day = (int)$tag['tag'];
                    $cells[$day] = $events[$monat][$mid][$day] ?? null;
                }

                $rows[] = [
                    'mitarbeiter_id' => $mid,
                    'name' => (string)($mitarbeiter['anzeige_name'] ?? ('Mitarbeiter #' . $mid)),
                    'monatswerte_vorhanden' => $istAbgeschlossen,
                    'ueberstunden' => $istAbgeschlossen
                        ? $this->formatStundenSaldo((int)($stundenSalden[$monat][$mid] ?? 0))
                        : 'offen',
                    'urlaub' => $istAbgeschlossen
                        ? $this->formatTage((float)($monatswert['urlaubstage_verbleibend'] ?? 0))
                        : 'offen',
                    'kann_genehmigen' => !empty($kannAntraegeGenehmigen[$mid]),
                    'cells' => $cells,
                ];
            }

            $planung[$monat] = [
                'monat' => $monat,
                'name' => $monatsnamen[$monat],
                'tage' => $tage,
                'rows' => $rows,
                'abgeschlossen_count' => $abgeschlossenCount,
                'mitarbeiter_count' => count($rows),
            ];
        }

        return $planung;
    }

    /**
     * @return array<int,array{tag:int,wochentag:string,wochenende:bool}>
     */
    private function baueTage(int $jahr, int $monat, bool $wochenendenAnzeigen): array
    {
        $namen = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
        $ende = $start->modify('last day of this month');
        $tage = [];

        for ($tag = $start; $tag <= $ende; $tag = $tag->modify('+1 day')) {
            $w = (int)$tag->format('w');
            $istWochenende = ($w === 0 || $w === 6);
            if (!$wochenendenAnzeigen && $istWochenende) {
                continue;
            }

            $tage[] = [
                'tag' => (int)$tag->format('j'),
                'wochentag' => $namen[$w],
                'wochenende' => $istWochenende,
            ];
        }

        return $tage;
    }

    /**
     * @param int[] $werte
     * @param array<string,mixed> $params
     */
    private function baueInPlatzhalter(array $werte, string $prefix, array &$params): string
    {
        $platzhalter = [];
        $i = 0;
        foreach ($werte as $wert) {
            $key = $prefix . $i;
            $platzhalter[] = ':' . $key;
            $params[$key] = (int)$wert;
            $i++;
        }
        return implode(',', $platzhalter);
    }

    private function formatStundenSaldo(int $minuten): string
    {
        return str_replace('.', ',', sprintf('%+.2f', $minuten / 60));
    }

    private function formatTage(float $tage): string
    {
        return number_format($tage, 2, ',', '.');
    }
}
