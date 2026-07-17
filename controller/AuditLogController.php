<?php
declare(strict_types=1);

/**
 * AuditLogController
 *
 * Lesbare Audit-Ansicht fuer fachliche system_log-Eintraege.
 * Das technische Warn-/Fehler-Log unter Konfiguration bleibt davon getrennt.
 */
class AuditLogController
{
    private AuthService $authService;
    private Database $datenbank;

    /**
     * @var array<string,string>
     */
    private array $filterOptionen = [
        'alle' => 'Alle sichtbaren Audit-Logs',
        'urlaub' => 'Urlaub: alle',
        'urlaub_storno' => 'Urlaub: Storno/Ruecknahme',
        'urlaub_direkt' => 'Urlaub: direkt eingetragen',
        'urlaub_genehmigung' => 'Urlaub: Genehmigung/Ablehnung',
        'zeitbuchungen' => 'Zeitbuchungen: alle',
        'zeitbuchung_loeschen' => 'Zeitbuchungen: geloescht',
        'tagesfelder' => 'Tagesfelder: Pause/Krank/Kurzarbeit/Sonstiges',
        'stundenkonto' => 'Stundenkonto',
        'offline_queue' => 'Offline-Queue',
    ];

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank = Database::getInstanz();
    }

    private function text(string $wert): string
    {
        return html_entity_decode($wert, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        if (
            $this->authService->hatRecht('KONFIGURATION_VERWALTEN')
            || $this->authService->hatRecht('ROLLEN_RECHTE_VERWALTEN')
            || $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle($this->text('Personalb&uuml;ro'))
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        require __DIR__ . '/../views/layout/header.php';
        echo '<section><h2>Keine Berechtigung</h2><p>Sie haben keine Berechtigung, die Logs anzuzeigen.</p></section>';
        require __DIR__ . '/../views/layout/footer.php';

        return false;
    }

    private function normalisiereFilterTyp(string $typ): string
    {
        $typ = trim($typ);
        return array_key_exists($typ, $this->filterOptionen) ? $typ : 'alle';
    }

    private function normalisiereDatum(string $wert): string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) !== 1) {
            return '';
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $wert);
        if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $wert) {
            return '';
        }

        return $wert;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function holeMitarbeiterListe(): array
    {
        try {
            return $this->datenbank->fetchAlle(
                'SELECT id, vorname, nachname, benutzername, aktiv
                 FROM mitarbeiter
                 ORDER BY aktiv DESC, nachname ASC, vorname ASC, id ASC'
            );
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Audit-Logs: Mitarbeiterliste konnte nicht geladen werden', [
                    'exception' => $e->getMessage(),
                ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'audit_logs');
            }
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function holeLogRohdaten(string $typ, int $mitarbeiterId, string $von, string $bis, int $limit): array
    {
        $params = [
            'stealth_a' => '%"stealth":1%',
            'stealth_b' => '%"stealth": 1%',
        ];

        $where = [
            "l.kategorie IS NOT NULL",
            "LOWER(l.loglevel) IN ('info', 'warn', 'error')",
            "NOT (l.kategorie = 'stundenkonto' AND (l.daten LIKE :stealth_a OR l.daten LIKE :stealth_b))",
        ];

        switch ($typ) {
            case 'urlaub':
                $where[] = "l.kategorie IN ('urlaub', 'urlaub_verwaltung', 'urlaub_genehmigung')";
                break;
            case 'urlaub_storno':
                $where[] = "((l.kategorie = 'urlaub_verwaltung' AND l.nachricht = 'Urlaubsverwaltung: Antrag storniert')
                             OR (l.kategorie = 'urlaub' AND l.nachricht LIKE '%storniert%'))";
                break;
            case 'urlaub_direkt':
                $where[] = "l.kategorie = 'urlaub_verwaltung' AND l.nachricht = 'Urlaubsverwaltung: Urlaub direkt eingetragen'";
                break;
            case 'urlaub_genehmigung':
                $where[] = "l.kategorie = 'urlaub_genehmigung'";
                break;
            case 'zeitbuchungen':
                $where[] = "l.kategorie = 'zeitbuchung_audit'";
                break;
            case 'zeitbuchung_loeschen':
                $where[] = "l.kategorie = 'zeitbuchung_audit'
                             AND (l.daten LIKE '%\"aktion\":\"delete\"%' OR l.daten LIKE '%\"aktion\": \"delete\"%')";
                break;
            case 'tagesfelder':
                $where[] = "l.kategorie = 'tageswerte_audit'";
                break;
            case 'stundenkonto':
                $where[] = "l.kategorie = 'stundenkonto'";
                break;
            case 'offline_queue':
                $where[] = "l.kategorie = 'offline_queue'";
                break;
            case 'alle':
            default:
                $where[] = "l.kategorie IN (
                                'urlaub',
                                'urlaub_verwaltung',
                                'urlaub_genehmigung',
                                'zeitbuchung_audit',
                                'tageswerte_audit',
                                'stundenkonto',
                                'offline_queue'
                            )";
                break;
        }

        if ($mitarbeiterId > 0) {
            $params['mitarbeiter_id'] = $mitarbeiterId;
            $params['mid_a'] = '%"mitarbeiter_id":' . $mitarbeiterId . '%';
            $params['mid_b'] = '%"mitarbeiter_id": ' . $mitarbeiterId . '%';
            $params['mid_c'] = '%"ziel_mitarbeiter_id":' . $mitarbeiterId . '%';
            $params['mid_d'] = '%"ziel_mitarbeiter_id": ' . $mitarbeiterId . '%';
            $params['mid_e'] = '%"erstellt_von":' . $mitarbeiterId . '%';
            $params['mid_f'] = '%"erstellt_von": ' . $mitarbeiterId . '%';

            $where[] = "(l.mitarbeiter_id = :mitarbeiter_id
                         OR l.daten LIKE :mid_a
                         OR l.daten LIKE :mid_b
                         OR l.daten LIKE :mid_c
                         OR l.daten LIKE :mid_d
                         OR l.daten LIKE :mid_e
                         OR l.daten LIKE :mid_f)";
        }

        if ($von !== '') {
            $params['von'] = $von . ' 00:00:00';
            $where[] = 'l.zeitstempel >= :von';
        }

        if ($bis !== '') {
            $bisDt = DateTimeImmutable::createFromFormat('Y-m-d', $bis);
            if ($bisDt instanceof DateTimeImmutable) {
                $params['bis'] = $bisDt->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
                $where[] = 'l.zeitstempel < :bis';
            }
        }

        $sql = 'SELECT l.id, l.zeitstempel, l.loglevel, l.kategorie, l.nachricht, l.daten, l.mitarbeiter_id, l.terminal_id
                FROM system_log l
                WHERE ' . implode("\n                  AND ", $where) . '
                ORDER BY l.zeitstempel DESC, l.id DESC
                LIMIT ' . (int)$limit;

        return $this->datenbank->fetchAlle($sql, $params);
    }

    /**
     * @return array<string,mixed>
     */
    private function dekodiereDaten(?string $json): array
    {
        $json = trim((string)$json);
        if ($json === '') {
            return [];
        }

        $daten = json_decode($json, true);
        return is_array($daten) ? $daten : [];
    }

    /**
     * @param array<string,mixed> $daten
     * @return array<int,int>
     */
    private function sammleMitarbeiterIds(array $daten, int $rowMitarbeiterId): array
    {
        $ids = [];
        if ($rowMitarbeiterId > 0) {
            $ids[] = $rowMitarbeiterId;
        }

        foreach (['mitarbeiter_id', 'ziel_mitarbeiter_id', 'actor_id', 'genehmiger_id', 'erstellt_von', 'erstellt_von_mitarbeiter_id'] as $key) {
            if (isset($daten[$key]) && is_numeric($daten[$key]) && (int)$daten[$key] > 0) {
                $ids[] = (int)$daten[$key];
            }
        }

        return $ids;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,string>
     */
    private function holeMitarbeiterNamen(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $idx => $id) {
            $key = 'id' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        try {
            $rows = $this->datenbank->fetchAlle(
                'SELECT id, vorname, nachname, benutzername
                 FROM mitarbeiter
                 WHERE id IN (' . implode(',', $placeholders) . ')',
                $params
            );
        } catch (Throwable) {
            return [];
        }

        $namen = [];
        foreach ($rows as $row) {
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

            $namen[$id] = $name;
        }

        return $namen;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function bereiteEintraegeFuerAnsicht(array $rows): array
    {
        $vorbereitet = [];
        $ids = [];

        foreach ($rows as $row) {
            $daten = $this->dekodiereDaten(isset($row['daten']) ? (string)$row['daten'] : null);
            $rowMitarbeiterId = (int)($row['mitarbeiter_id'] ?? 0);
            $ids = array_merge($ids, $this->sammleMitarbeiterIds($daten, $rowMitarbeiterId));

            $vorbereitet[] = [
                'row' => $row,
                'daten' => $daten,
            ];
        }

        $namen = $this->holeMitarbeiterNamen($ids);

        $eintraege = [];
        foreach ($vorbereitet as $eintrag) {
            $row = is_array($eintrag['row'] ?? null) ? $eintrag['row'] : [];
            $daten = is_array($eintrag['daten'] ?? null) ? $eintrag['daten'] : [];
            $kategorie = (string)($row['kategorie'] ?? '');
            $nachricht = (string)($row['nachricht'] ?? '');
            $rowMitarbeiterId = (int)($row['mitarbeiter_id'] ?? 0);

            $actorId = $this->ermittleActorId($kategorie, $rowMitarbeiterId, $daten);
            $zielId = $this->ermittleZielMitarbeiterId($kategorie, $rowMitarbeiterId, $daten);

            $details = '';
            if ($daten !== []) {
                $pretty = json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $details = is_string($pretty) ? $pretty : (string)($row['daten'] ?? '');
            } else {
                $details = (string)($row['daten'] ?? '');
            }

            $eintraege[] = [
                'id' => (int)($row['id'] ?? 0),
                'zeit' => $this->formatiereDatumZeit((string)($row['zeitstempel'] ?? '')),
                'level' => strtoupper((string)($row['loglevel'] ?? '')),
                'bereich' => $this->ermittleBereichLabel($kategorie),
                'aktion' => $this->ermittleAktionLabel($kategorie, $nachricht, $daten),
                'wer' => $this->nameFuerId($actorId, $namen),
                'ziel' => $this->nameFuerId($zielId, $namen),
                'zeitraum' => $this->ermittleZeitraumText($daten),
                'warum' => $this->ermittleBegruendungText($daten),
                'details' => $details,
            ];
        }

        return $eintraege;
    }

    /**
     * @param array<string,mixed> $daten
     */
    private function ermittleActorId(string $kategorie, int $rowMitarbeiterId, array $daten): int
    {
        if ($kategorie === 'stundenkonto' && isset($daten['erstellt_von']) && is_numeric($daten['erstellt_von'])) {
            return (int)$daten['erstellt_von'];
        }

        foreach (['actor_id', 'genehmiger_id', 'erstellt_von_mitarbeiter_id'] as $key) {
            if (isset($daten[$key]) && is_numeric($daten[$key]) && (int)$daten[$key] > 0) {
                return (int)$daten[$key];
            }
        }

        return $rowMitarbeiterId;
    }

    /**
     * @param array<string,mixed> $daten
     */
    private function ermittleZielMitarbeiterId(string $kategorie, int $rowMitarbeiterId, array $daten): int
    {
        foreach (['ziel_mitarbeiter_id', 'mitarbeiter_id'] as $key) {
            if (isset($daten[$key]) && is_numeric($daten[$key]) && (int)$daten[$key] > 0) {
                return (int)$daten[$key];
            }
        }

        if ($kategorie === 'stundenkonto') {
            return $rowMitarbeiterId;
        }

        return 0;
    }

    /**
     * @param array<int,string> $namen
     */
    private function nameFuerId(int $id, array $namen): string
    {
        if ($id <= 0) {
            return '-';
        }

        return $namen[$id] ?? ('Mitarbeiter #' . $id);
    }

    private function ermittleBereichLabel(string $kategorie): string
    {
        switch ($kategorie) {
            case 'urlaub_verwaltung':
                return 'Urlaubsverwaltung';
            case 'urlaub_genehmigung':
                return 'Urlaub-Genehmigung';
            case 'urlaub':
                return 'Urlaub';
            case 'zeitbuchung_audit':
                return 'Zeitbuchungen';
            case 'tageswerte_audit':
                return 'Tagesfelder';
            case 'stundenkonto':
                return 'Stundenkonto';
            case 'offline_queue':
                return 'Offline-Queue';
            default:
                return $kategorie !== '' ? $kategorie : '-';
        }
    }

    /**
     * @param array<string,mixed> $daten
     */
    private function ermittleAktionLabel(string $kategorie, string $nachricht, array $daten): string
    {
        if ($kategorie === 'zeitbuchung_audit') {
            $aktion = (string)($daten['aktion'] ?? '');
            if ($aktion === 'delete') {
                return $this->text('Zeitbuchung gel&ouml;scht');
            }
            if ($aktion === 'add') {
                return 'Zeitbuchung hinzugefuegt';
            }
            if ($aktion === 'update') {
                return $this->text('Zeitbuchung ge&auml;ndert');
            }
        }

        if ($nachricht === 'Urlaubsverwaltung: Antrag storniert') {
            return 'Urlaub storniert/rueckgenommen';
        }
        if ($nachricht === 'Urlaubsverwaltung: Urlaub direkt eingetragen') {
            return 'Urlaub direkt eingetragen';
        }
        if ($nachricht === 'Urlaub-Genehmigung: Antrag entschieden') {
            $status = (string)($daten['status_neu'] ?? '');
            if ($status === 'genehmigt') {
                return 'Urlaub genehmigt';
            }
            if ($status === 'abgelehnt') {
                return 'Urlaub abgelehnt';
            }
        }

        return $nachricht !== '' ? $nachricht : '-';
    }

    /**
     * @param array<string,mixed> $daten
     */
    private function ermittleZeitraumText(array $daten): string
    {
        if (isset($daten['quell_daten']) && is_array($daten['quell_daten']) && isset($daten['ziel_datum'])) {
            $quellen = [];
            foreach ($daten['quell_daten'] as $datum) {
                $quellen[] = $this->formatiereDatum((string)$datum);
            }
            return implode(', ', $quellen) . ' -> ' . $this->formatiereDatum((string)$daten['ziel_datum']);
        }

        foreach (['datum', 'wirksam_datum', 'ziel_datum'] as $key) {
            if (!empty($daten[$key])) {
                return $this->formatiereDatum((string)$daten[$key]);
            }
        }

        $von = (string)($daten['von'] ?? ($daten['von_datum'] ?? ''));
        $bis = (string)($daten['bis'] ?? ($daten['bis_datum'] ?? ''));
        if ($von !== '' || $bis !== '') {
            $vonText = $von !== '' ? $this->formatiereDatum($von) : 'offen';
            $bisText = $bis !== '' ? $this->formatiereDatum($bis) : 'offen';
            return $vonText . ' bis ' . $bisText;
        }

        if (isset($daten['jahr'], $daten['monat']) && is_numeric($daten['jahr']) && is_numeric($daten['monat'])) {
            return sprintf('%02d.%04d', (int)$daten['monat'], (int)$daten['jahr']);
        }

        return '-';
    }

    /**
     * @param array<string,mixed> $daten
     */
    private function ermittleBegruendungText(array $daten): string
    {
        foreach (['begruendung', 'grund', 'kommentar_text'] as $key) {
            if (isset($daten[$key]) && trim((string)$daten[$key]) !== '') {
                return trim((string)$daten[$key]);
            }
        }

        if (isset($daten['kommentar']) && (string)$daten['kommentar'] === 'ja') {
            return 'Kommentar vorhanden';
        }

        return '-';
    }

    private function formatiereDatum(string $wert): string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return '';
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1) {
                return (new DateTimeImmutable($wert))->format('d.m.Y');
            }
        } catch (Throwable) {
            return $wert;
        }

        return $wert;
    }

    private function formatiereDatumZeit(string $wert): string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return '';
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $wert) === 1) {
                return (new DateTimeImmutable($wert))->format('d.m.Y H:i:s');
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1) {
                return (new DateTimeImmutable($wert))->format('d.m.Y');
            }
        } catch (Throwable) {
            return $wert;
        }

        return $wert;
    }

    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $filterTyp = $this->normalisiereFilterTyp((string)($_GET['typ'] ?? 'alle'));
        $filterMitarbeiterId = max(0, (int)($_GET['mitarbeiter_id'] ?? 0));
        $filterVon = $this->normalisiereDatum((string)($_GET['von'] ?? ''));
        $filterBis = $this->normalisiereDatum((string)($_GET['bis'] ?? ''));
        $limit = max(25, min(500, (int)($_GET['limit'] ?? 200)));

        $fehlermeldung = null;
        if ($filterVon !== '' && $filterBis !== '' && $filterVon > $filterBis) {
            $fehlermeldung = 'Das Von-Datum muss vor oder am Bis-Datum liegen.';
            $eintraege = [];
        } else {
            try {
                $rows = $this->holeLogRohdaten($filterTyp, $filterMitarbeiterId, $filterVon, $filterBis, $limit);
                $eintraege = $this->bereiteEintraegeFuerAnsicht($rows);
            } catch (Throwable $e) {
                $eintraege = [];
                $fehlermeldung = 'Die Logs konnten nicht geladen werden.';
                if (class_exists('Logger')) {
                    Logger::error('Audit-Logs: Laden fehlgeschlagen', [
                        'typ' => $filterTyp,
                        'exception' => $e->getMessage(),
                    ], $this->authService->holeAngemeldeteMitarbeiterId(), null, 'audit_logs');
                }
            }
        }

        $mitarbeiterListe = $this->holeMitarbeiterListe();
        $filterOptionen = $this->filterOptionen;

        require __DIR__ . '/../views/logs/audit.php';
    }
}
