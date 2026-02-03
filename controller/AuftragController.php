<?php
declare(strict_types=1);

/**
 * AuftragController
 *
 * Backend-Auswertung fuer Auftragszeiten.
 *
 * Scope (Micro-Patch):
 * - Listet Auftraege anhand von `auftragszeit` (auftragscode/auftrag_id).
 * - Detailansicht zeigt alle Buchungen (Mitarbeiter, Maschine, Zeiten, Dauer).
 *
 * Nicht in diesem Patch (kommt getrennt):
 * - Arbeitsschritt-Code wird in der Detailansicht angezeigt (falls vorhanden).
 * - Top-Menue-Link (Nav) – wird als eigener Mini-Patch geliefert (Datei-Budget).
 */
class AuftragController
{
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
        // (Rechte/Scopes koennen spaeter ergaenzt werden, ohne bestehende Funktionen zu entfernen.)
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

        $like = null;
        if ($q !== '') {
            // LIKE-Pattern defensiv escapen
            $q2 = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $like = '%' . $q2 . '%';
        }

        $fehlermeldung = null;
        $auftraege = [];

        try {
            $where = '';
            $params = [];
            if ($like !== null) {
                $where = 'WHERE (a.auftragsnummer LIKE :q ESCAPE "\\\\" OR az.auftragscode LIKE :q ESCAPE "\\\\")';
                $params['q'] = $like;
            }

            // Auswertung ueber existierende Buchungen
            $sql = "
                SELECT
                    COALESCE(a.auftragsnummer, az.auftragscode) AS auftragsnummer,
                    MAX(a.aktiv) AS auftrag_aktiv,
                    COUNT(*) AS buchungen,
                    SUM(CASE WHEN az.status = 'laufend' THEN 1 ELSE 0 END) AS laufend,
                    SUM(CASE WHEN az.status = 'pausiert' THEN 1 ELSE 0 END) AS pausiert,
                    CASE
                        WHEN SUM(CASE WHEN az.status = 'laufend' THEN 1 ELSE 0 END) > 0 THEN 'laufend'
                        WHEN SUM(CASE WHEN az.status = 'pausiert' THEN 1 ELSE 0 END) > 0 THEN 'pausiert'
                        ELSE 'abgeschlossen'
                    END AS status,
                    SUM(CASE WHEN az.endzeit IS NOT NULL THEN TIMESTAMPDIFF(SECOND, az.startzeit, az.endzeit) ELSE 0 END) AS sekunden,
                    MIN(az.startzeit) AS erste_startzeit,
                    MAX(COALESCE(az.endzeit, az.startzeit)) AS letzte_zeit,
                    COALESCE(MAX(a.geaendert_am), MAX(COALESCE(az.endzeit, az.startzeit))) AS zuletzt_bearbeitet
                FROM auftragszeit az
                LEFT JOIN auftrag a ON a.id = az.auftrag_id
                {$where}
                GROUP BY COALESCE(a.auftragsnummer, az.auftragscode)
                ORDER BY letzte_zeit DESC
                LIMIT 200
            ";

            $auftraege = $this->db->fetchAlle($sql, $params);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Auftraege konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Auftragsliste', [
                    'exception' => $e->getMessage(),
                    'q' => $q,
                ], null, null, 'auftrag');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Auftraege</h2>

            <form method="get" action="" style="margin-bottom: 1rem;">
                <input type="hidden" name="seite" value="auftrag">
                <label>
                    Suche (Auftragsnummer):
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="min-width: 240px;">
                </label>
                <button type="submit">Suchen</button>
                <?php if ($q !== ''): ?>
                    <a href="?seite=auftrag" style="margin-left: 0.5rem;">Reset</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($auftraege) === 0): ?>
                <p>Keine Auftraege gefunden (noch keine Auftragsbuchungen vorhanden).</p>
                <p><small>Hinweis: Diese Auswertung basiert auf vorhandenen Buchungen in <code>auftragszeit</code>.</small></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Auftragsnummer</th>
                            <th>Buchungen</th>
                            <th>Laufend</th>
                            <th>Status</th>
                            <th>Stunden (Summe)</th>
                            <th>Erste Buchung</th>
                            <th>Letzte Buchung</th>
                            <th>Aktiv</th>
                            <th>Zuletzt bearbeitet</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auftraege as $row): ?>
                            <?php
                                $nr = (string)($row['auftragsnummer'] ?? '');
                                $aktivRaw = $row['auftrag_aktiv'] ?? null;
                                $buchungen = (int)($row['buchungen'] ?? 0);
                                $laufend = (int)($row['laufend'] ?? 0);
                                $status = (string)($row['status'] ?? '');
                                $sekunden = (int)($row['sekunden'] ?? 0);
                                $stunden = $sekunden > 0 ? round($sekunden / 3600, 2) : 0.0;
                                $erste = (string)($row['erste_startzeit'] ?? '');
                                $letzte = (string)($row['letzte_zeit'] ?? '');
                                $zuletztBearbeitet = (string)($row['zuletzt_bearbeitet'] ?? '');

                                $nrEsc = htmlspecialchars($nr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $aktivText = $aktivRaw === null ? '-' : (((int)$aktivRaw === 1) ? 'Ja' : 'Nein');
                                $statusText = $status !== '' ? $status : '-';
                            ?>
                            <tr>
                                <td><?php echo $nrEsc; ?></td>
                                <td><?php echo $buchungen; ?></td>
                                <td><?php echo $laufend; ?></td>
                                <td><?php echo htmlspecialchars($statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo number_format($stunden, 2, '.', ''); ?></td>
                                <td><?php echo htmlspecialchars($erste, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($letzte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $aktivText; ?></td>
                                <td><?php echo htmlspecialchars($zuletztBearbeitet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($nr !== ''): ?>
                                        <a href="?seite=auftrag_detail&amp;code=<?php echo urlencode($nr); ?>">Details</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 0.75rem;">
                    <small>
                        Arbeitsschritt-Code wird in der Detailansicht angezeigt, sofern beim Auftrag-Start erfasst (Scan/Manuell).
                    </small>
                </p>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Auftragsdetails', [
                    'exception' => $e->getMessage(),
                    'code' => $code,
                ], null, null, 'auftrag');
            }
        }

        $sumStunden = $sumSekunden > 0 ? round($sumSekunden / 3600, 2) : 0.0;

        $sumProSchrittSorted = $sumProSchritt;
        if (is_array($sumProSchrittSorted) && count($sumProSchrittSorted) > 1) {
            arsort($sumProSchrittSorted);
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Auftrag: <?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>

            <p>
                <a href="?seite=auftrag">&laquo; Zurueck zur Liste</a>
            </p>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($buchungen) === 0): ?>
                <p>Keine Buchungen gefunden.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mitarbeiter</th>
                            <th>Maschine</th>
                            <th>Typ</th>
                            <th>Arbeitsschritt</th>
                            <th>Start</th>
                            <th>Ende</th>
                            <th>Dauer (h)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buchungen as $b): ?>
                            <?php
                                $id = (int)($b['id'] ?? 0);
                                $vorname = trim((string)($b['vorname'] ?? ''));
                                $nachname = trim((string)($b['nachname'] ?? ''));
                                $mitarbeiter = trim($vorname . ' ' . $nachname);
                                if ($mitarbeiter === '') {
                                    $mitarbeiter = 'Unbekannt';
                                }
                                $maschine = (string)($b['maschine_name'] ?? '');
                                $typ = (string)($b['typ'] ?? '');
                                $schritt = trim((string)($b['arbeitsschritt_code_effektiv'] ?? ''));
                                $start = (string)($b['startzeit'] ?? '');
                                $end = (string)($b['endzeit'] ?? '');
                                $status = (string)($b['status'] ?? '');

                                $dauerH = '';
                                if ($end !== '') {
                                    $ts1 = strtotime($start);
                                    $ts2 = strtotime($end);
                                    if ($ts1 !== false && $ts2 !== false && $ts2 >= $ts1) {
                                        $dauerH = number_format(round(($ts2 - $ts1) / 3600, 2), 2, '.', '');
                                    }
                                }
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo htmlspecialchars($mitarbeiter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($maschine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $schritt !== '' ? htmlspecialchars($schritt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                                <td><?php echo htmlspecialchars($start, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($end, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $dauerH !== '' ? $dauerH : '-'; ?></td>
                                <td><?php echo htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 0.75rem;">
                    <strong>Gesamtstunden (abgeschlossen):</strong> <?php echo number_format($sumStunden, 2, '.', ''); ?>
                </p>

                <?php if (is_array($sumProSchrittSorted) && count($sumProSchrittSorted) > 0): ?>
                    <h3>Arbeitsschritte (Summe, abgeschlossen)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Arbeitsschritt</th>
                                <th>Buchungen</th>
                                <th>Stunden (Summe)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sumProSchrittSorted as $schrittKey => $sec): ?>
                                <?php
                                    $cnt = isset($countProSchritt[$schrittKey]) ? (int)$countProSchritt[$schrittKey] : 0;
                                    $h = $sec > 0 ? round(((int)$sec) / 3600, 2) : 0.0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$schrittKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td><?php echo $cnt; ?></td>
                                    <td><?php echo number_format($h, 2, '.', ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <p><small>Hinweis: Arbeitsschritt-Code wird in den Details angezeigt, sofern beim Auftrag-Start erfasst (Scan/Manuell).</small></p>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }
}
