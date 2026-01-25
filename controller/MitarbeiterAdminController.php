<?php
declare(strict_types=1);

/**
 * MitarbeiterAdminController
 *
 * Backend-Controller für das Verwalten von Mitarbeitern:
 * - Liste
 * - Detailansicht
 * - Anlegen/Bearbeiten (Platzhalter)
 */
class MitarbeiterAdminController
{
    private AuthService $authService;
    private MitarbeiterModel $mitarbeiterModel;

    public function __construct()
    {
        $this->authService      = AuthService::getInstanz();
        $this->mitarbeiterModel = new MitarbeiterModel();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Mitarbeiterverwaltung nutzen darf.
     *
     * Erlaubt sind derzeit die Rollen "Chef" oder "Personalbüro".
     * Bei fehlender Anmeldung wird eine Hinweisnachricht ausgegeben,
     * bei fehlender Berechtigung ein HTTP-Status 403 gesetzt.
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            echo '<p>Bitte melden Sie sich zuerst im Backend an.</p>';
            return false;
        }

        // Primär: Recht (neues System)
        if (method_exists($this->authService, 'hatRecht')) {
            try {
                if ($this->authService->hatRecht('MITARBEITER_VERWALTEN')) {
                    return true;
                }
            } catch (\Throwable) {
                // Fallback unten
            }
        }

        $erlaubteRollen = ['Chef', 'Personalbüro', 'Personalbuero'];

        foreach ($erlaubteRollen as $rollenName) {
            if ($this->authService->hatRolle($rollenName)) {
                return true;
            }
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Mitarbeiterverwaltung zu nutzen.</p>';

        return false;
    }

    /**
     * Rendert das Mitarbeiter-Formular inkl. rekonstruierter POST-Daten.
     *
     * Hintergrund: Bei Validierungsfehlern sollen Eingaben nicht verloren gehen.
     * Diese Hilfsroutine vermeidet Code-Duplikate im Save-Flow.
     *
     * @param array<string,mixed> $mitarbeiter
     * @param array<int,int> $rollenIdsAusgewaehlt
     */
    private function renderFormMitFehler(array $mitarbeiter, string $fehlermeldung, array $rollenIdsAusgewaehlt): void
    {
        $rollen = [];
        $rollenIdsAusgewaehlt = array_map('intval', $rollenIdsAusgewaehlt);

        $id = (int)($mitarbeiter['id'] ?? 0);

        // Genehmiger aus POST rekonstruieren, damit Eingaben bei Validierungsfehler nicht verloren gehen
        $genehmigerIds        = $_POST['genehmiger_id'] ?? [];
        $genehmigerPrioritaet = $_POST['genehmiger_prio'] ?? [];
        $genehmigerBeschr     = $_POST['genehmiger_beschreibung'] ?? [];

        if (!is_array($genehmigerIds)) {
            $genehmigerIds = [];
        }
        if (!is_array($genehmigerPrioritaet)) {
            $genehmigerPrioritaet = [];
        }
        if (!is_array($genehmigerBeschr)) {
            $genehmigerBeschr = [];
        }

        $genehmiger = [];
        foreach ($genehmigerIds as $index => $gidRoh) {
            $gid = (int)$gidRoh;
            $prioRoh = $genehmigerPrioritaet[$index] ?? null;
            $prio    = $prioRoh !== null && $prioRoh !== '' ? (int)$prioRoh : 0;
            $besch   = $genehmigerBeschr[$index] ?? null;

            $genehmiger[] = [
                'genehmiger_mitarbeiter_id' => $gid,
                'prioritaet'                => $prio,
                'kommentar'                => $besch,
            ];
        }

        // Rollen-Liste (alle aktiven Rollen) laden
        $alleRollen = [];
        try {
            $rolleModel = new RolleModel();
            $alleRollen = $rolleModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $alleRollen = [];
            if (class_exists('Logger')) {
                Logger::error('Rollen-Liste konnte nicht geladen werden (Validierungsfehler)', [
                    'exception' => $e->getMessage(),
                ], isset($mitarbeiter['id']) ? (int)$mitarbeiter['id'] : null, null, 'rolle');
            }
        }

        // Alle aktiven Mitarbeiter für die Genehmiger-Auswahlliste laden (auch im Validierungsfehler-Fall)
        $alleMitarbeiterGenehmiger = [];
        try {
            $alleMitarbeiterGenehmiger = $this->mitarbeiterModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $alleMitarbeiterGenehmiger = [];
            if (class_exists('Logger')) {
                Logger::error('Mitarbeiterliste für Genehmiger-Auswahl konnte nicht geladen werden (Validierungsfehler)', [
                    'mitarbeiter_id' => isset($mitarbeiter['id']) ? (int)$mitarbeiter['id'] : null,
                    'exception'      => $e->getMessage(),
                ], isset($mitarbeiter['id']) ? (int)$mitarbeiter['id'] : null, null, 'genehmiger');
            }
        }

        // Rechte-Overrides (Allow/Deny pro Mitarbeiter) aus POST rekonstruieren, damit Eingaben bei Validierungsfehler nicht verloren gehen.
        $alleRechte = [];
        try {
            $rolleModel = new RolleModel();
            // Phase 1b (Rechte-Roadmap): Inaktive (Legacy-)Rechte ausblenden,
            // damit die Mitarbeiter-Override-UI keine doppelten/alten Codes mehr anzeigt.
            $alleRechte = $rolleModel->holeAlleRechte(true);
        } catch (\Throwable $e) {
            $alleRechte = [];
            if (class_exists('Logger')) {
                Logger::error('Rechte-Liste konnte nicht geladen werden (Validierungsfehler)', [
                    'exception' => $e->getMessage(),
                ], isset($mitarbeiter['id']) ? (int)$mitarbeiter['id'] : null, null, 'recht');
            }
        }

        $rechteOverrides = [];
        $overridePost = $_POST['recht_override'] ?? [];
        if (is_array($overridePost)) {
            foreach ($overridePost as $rechtIdRoh => $wert) {
                $rid = (int)$rechtIdRoh;
                if ($rid <= 0) {
                    continue;
                }

                $v = is_string($wert) ? trim($wert) : (is_int($wert) ? (string)$wert : '');
                if ($v === '') {
                    continue; // vererbt
                }
                if ($v === '1') {
                    $rechteOverrides[$rid] = 1; // erlauben
                } elseif ($v === '0') {
                    $rechteOverrides[$rid] = 0; // entziehen
                }
            }
        }

        // Effektive Rechte für Anzeige berechnen (Rollen + Overrides)
        $rechteVererbt  = [];
        $rechteEffektiv = [];
        try {
            $sets = $this->berechneRechteVererbtUndEffektiv($rollenIdsAusgewaehlt, $rechteOverrides, $alleRechte);
            $rechteVererbt  = $sets['vererbt'] ?? [];
            $rechteEffektiv = $sets['effektiv'] ?? [];
        } catch (\Throwable) {
            $rechteVererbt  = [];
            $rechteEffektiv = [];
        }

        
        // Abteilungen für scoped Rollen laden (Phase 1)
        $alleAbteilungen = [];
        try {
            $abteilungModel  = new AbteilungModel();
            $alleAbteilungen = $abteilungModel->holeAlleAktiven();
        } catch (\Throwable) {
            $alleAbteilungen = [];
        }

        // Scoped Rollen (abteilung) laden (Phase 1)
        $rollenScopesAbteilung = [];
        if ($id > 0 && $mitarbeiter !== null) {
            try {
                $pdo = Database::getInstanz()->getPdo();
                $pdo->query('SELECT 1 FROM mitarbeiter_hat_rolle_scope LIMIT 1');

                $stmt = $pdo->prepare(
                    "SELECT id, rolle_id, scope_id, gilt_unterbereiche
                     FROM mitarbeiter_hat_rolle_scope
                     WHERE mitarbeiter_id = :mid
                       AND scope_typ = 'abteilung'
                     ORDER BY scope_id ASC, rolle_id ASC"
                );
                $stmt->execute([':mid' => $id]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (is_array($rows)) {
                    $rollenScopesAbteilung = $rows;
                }
            } catch (\Throwable) {
                $rollenScopesAbteilung = [];
            }
        }



        // Abteilungs-Mitgliedschaften aus POST rekonstruieren (optional, Mehrfach moeglich)
        $abteilungenIdsPost = $_POST['abteilungen_ids'] ?? [];
        if (!is_array($abteilungenIdsPost)) {
            $abteilungenIdsPost = [];
        }
        $tmpAbt = [];
        foreach ($abteilungenIdsPost as $aidRoh) {
            $aid = (int)$aidRoh;
            if ($aid > 0) {
                $tmpAbt[$aid] = true;
            }
        }
        $stammIdPost = (int)($_POST['stammabteilung_id'] ?? 0);
        if ($stammIdPost > 0) {
            $tmpAbt[$stammIdPost] = true;
        }
        $mitarbeiter['abteilungen_ids'] = array_keys($tmpAbt);

        require __DIR__ . '/../views/mitarbeiter/formular.php';
    }

    /**
     * Platzhalter: Liste aller aktiven Mitarbeiter.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $fehlermeldung    = null;
        $mitarbeiterListe = [];

        try {
            $mitarbeiterListe = $this->mitarbeiterModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Mitarbeiterliste konnte nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Mitarbeiterliste im Admin', [
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }
        }

        require __DIR__ . '/../views/mitarbeiter/liste.php';
    }


    /**
     * Formular zum Anlegen/Bearbeiten eines Mitarbeiters anzeigen.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $mitarbeiter   = null;
        $fehlermeldung = null;

        $successmeldung = null;

        $flashFehler = null;
        if (isset($_SESSION['mitarbeiter_admin_flash_error'])) {
            $flashFehler = (string)$_SESSION['mitarbeiter_admin_flash_error'];
            unset($_SESSION['mitarbeiter_admin_flash_error']);
        }

        $flashOk = null;
        if (isset($_SESSION['mitarbeiter_admin_flash_success'])) {
            $flashOk = (string)$_SESSION['mitarbeiter_admin_flash_success'];
            unset($_SESSION['mitarbeiter_admin_flash_success']);
        }

        if ($flashOk !== null && $flashOk !== '') {
            $successmeldung = $flashOk;
        }

        $alleMitarbeiterGenehmiger = [];

        if ($id > 0) {
            $mitarbeiter = $this->mitarbeiterModel->holeNachId($id);

            // Sicherheitscheck: Wenn ein anderer Datensatz als angefordert geliefert wird,
            // brechen wir ab, statt einen falschen Mitarbeiter zu bearbeiten.
            if ($mitarbeiter !== null && (int)($mitarbeiter['id'] ?? 0) !== $id) {
                if (class_exists('Logger')) {
                    Logger::error('Inkonsistente Mitarbeiter-ID beim Bearbeiten', [
                        'angeforderte_id' => $id,
                        'gefunden_id'     => isset($mitarbeiter['id']) ? (int)$mitarbeiter['id'] : null,
                    ], null, null, 'mitarbeiter');
                }
                $mitarbeiter   = null;
                $fehlermeldung = 'Der ausgewählte Mitarbeiter konnte nicht eindeutig geladen werden.';
            } elseif ($mitarbeiter === null) {
                $fehlermeldung = 'Der ausgewählte Mitarbeiter wurde nicht gefunden.';
            }
        }

        if ($fehlermeldung === null && $flashFehler !== null && $flashFehler !== '') {
            $fehlermeldung = $flashFehler;
        }

        // Rollen & Genehmiger für das Formular vorbereiten
        $rollen                = [];
        $alleRollen            = [];
        $rollenIdsAusgewaehlt  = [];
        $genehmiger            = [];

        // Rechte-Overrides: Liste aller Rechte + aktuelle Overrides dieses Mitarbeiters
        $alleRechte       = [];
        $rechteOverrides  = [];
        $rechteVererbt    = [];
        $rechteEffektiv   = [];

        // Rollen-Liste (alle aktiven Rollen) + Rechte-Liste (alle Rechte) laden
        try {
            $rolleModel = new RolleModel();
            $alleRollen = $rolleModel->holeAlleAktiven();
            // Phase 1b (Rechte-Roadmap): Inaktive (Legacy-)Rechte ausblenden,
            // damit die Mitarbeiter-Override-UI keine doppelten/alten Codes mehr anzeigt.
            $alleRechte = $rolleModel->holeAlleRechte(true);
        } catch (\Throwable $e) {
            $alleRollen = [];
            $alleRechte = [];
            if (class_exists('Logger')) {
                Logger::error('Rollen-Liste konnte nicht geladen werden', [
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'rolle');
            }
        }

        // Ausgewählte Rollen eines bestehenden Mitarbeiters laden
        if ($id > 0 && $mitarbeiter !== null) {
            $rollenIdsAusgewaehlt = [];

            // Primär: scoped Rollen (global) aus `mitarbeiter_hat_rolle_scope` lesen.
            // Fail-safe: wenn Tabelle noch nicht existiert, wird unten auf Legacy zurückgefallen.
            try {
                $pdo = Database::getInstanz()->getPdo();
                $pdo->query('SELECT 1 FROM mitarbeiter_hat_rolle_scope LIMIT 1');

                $stmt = $pdo->prepare(
                    "SELECT rolle_id FROM mitarbeiter_hat_rolle_scope
                     WHERE mitarbeiter_id = :mid
                       AND scope_typ = 'global'
                       AND scope_id = 0"
                );
                $stmt->execute([':mid' => $id]);
                $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                if (is_array($rows) && count($rows) > 0) {
                    $tmp = [];
                    foreach ($rows as $rid) {
                        $rid = (int)$rid;
                        if ($rid > 0) {
                            $tmp[$rid] = true;
                        }
                    }
                    $rollenIdsAusgewaehlt = array_keys($tmp);
                }
            } catch (\Throwable) {
                // ignorieren
            }

            // Legacy-Fallback: alte Zuordnungstabelle `mitarbeiter_hat_rolle`
            if (count($rollenIdsAusgewaehlt) === 0) {
                try {
                    $rollenZuordnungModel = new MitarbeiterHatRolleModel();
                    $rollenIdsAusgewaehlt = $rollenZuordnungModel->holeRollenIdsFuerMitarbeiter($id);
                } catch (\Throwable $e) {
                    $rollenIdsAusgewaehlt = [];
                    if (class_exists('Logger')) {
                        Logger::error('Rollen für Mitarbeiter konnten nicht geladen werden', [
                            'mitarbeiter_id' => $id,
                            'exception'      => $e->getMessage(),
                        ], $id, null, 'rolle');
                    }
                }
            }
        }


        // Genehmiger für bestehenden Mitarbeiter laden
        if ($id > 0 && $mitarbeiter !== null) {
            try {
                $genehmigerModel = new MitarbeiterGenehmigerModel();
                $genehmiger      = $genehmigerModel->holeGenehmigerFuerMitarbeiter($id);
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Genehmiger für Mitarbeiter konnten nicht geladen werden', [
                        'mitarbeiter_id' => $id,
                        'exception'      => $e->getMessage(),
                    ], $id, null, 'genehmiger');
                }
                $genehmiger = [];
            }
        }

        // Rechte-Overrides für bestehenden Mitarbeiter laden
        if ($id > 0 && $mitarbeiter !== null) {
            try {
                $db = Database::getInstanz();
                // Schema-SoT: Spalte heisst `erlaubt` (Migration 15).
                $rows = $db->fetchAlle(
                    'SELECT recht_id, erlaubt FROM mitarbeiter_hat_recht WHERE mitarbeiter_id = :mid',
                    ['mid' => $id]
                );

                foreach ($rows as $row) {
                    $rid = (int)($row['recht_id'] ?? 0);
                    if ($rid <= 0) {
                        continue;
                    }
                    $rechteOverrides[$rid] = (int)($row['erlaubt'] ?? 1) === 1 ? 1 : 0;
                }
            } catch (\Throwable $e) {
                $rechteOverrides = [];
                if (class_exists('Logger')) {
                    Logger::error('Rechte-Overrides für Mitarbeiter konnten nicht geladen werden', [
                        'mitarbeiter_id' => $id,
                        'exception'      => $e->getMessage(),
                    ], $id, null, 'recht');
                }
            }
        }

        // Effektive Rechte für Anzeige berechnen (Rollen + Overrides)
        try {
            $sets = $this->berechneRechteVererbtUndEffektiv($rollenIdsAusgewaehlt, $rechteOverrides, $alleRechte);
            $rechteVererbt  = $sets['vererbt'] ?? [];
            $rechteEffektiv = $sets['effektiv'] ?? [];
        } catch (\Throwable) {
            $rechteVererbt  = [];
            $rechteEffektiv = [];
        }


        // Alle aktiven Mitarbeiter für die Genehmiger-Auswahlliste laden
        try {
            $alleMitarbeiterGenehmiger = $this->mitarbeiterModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $alleMitarbeiterGenehmiger = [];
            if (class_exists('Logger')) {
                Logger::error('Mitarbeiterliste für Genehmiger-Auswahl konnte nicht geladen werden', [
                    'mitarbeiter_id' => $id > 0 ? $id : null,
                    'exception'      => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'genehmiger');
            }
        }

        // Sicherstellen, dass vorhandene Genehmiger auch dann im Dropdown sichtbar sind,
        // wenn sie (z. B. später) deaktiviert wurden.
        if (is_array($genehmiger) && count($genehmiger) > 0) {
            $idsImDropdown = [];
            foreach ($alleMitarbeiterGenehmiger as $m) {
                $mid = (int)($m['id'] ?? 0);
                if ($mid > 0) {
                    $idsImDropdown[$mid] = true;
                }
            }

            $genehmigerIds = [];
            foreach ($genehmiger as $g) {
                $gid = (int)($g['genehmiger_mitarbeiter_id'] ?? 0);
                if ($gid > 0 && $gid !== $id) {
                    $genehmigerIds[$gid] = true;
                }
            }

            $fehlende = [];
            foreach (array_keys($genehmigerIds) as $gid) {
                if (!isset($idsImDropdown[$gid])) {
                    $fehlende[] = $gid;
                }
            }

            if (count($fehlende) > 0) {
                try {
                    $nachgeladene = $this->mitarbeiterModel->holeNachIds($fehlende, false);
                    foreach ($nachgeladene as $m) {
                        $mid = (int)($m['id'] ?? 0);
                        if ($mid > 0 && $mid !== $id && !isset($idsImDropdown[$mid])) {
                            $alleMitarbeiterGenehmiger[] = $m;
                            $idsImDropdown[$mid] = true;
                        }
                    }

                    // Sortierung konsistent halten
                    usort($alleMitarbeiterGenehmiger, function (array $a, array $b): int {
                        $na = strtolower((string)($a['nachname'] ?? ''));
                        $nb = strtolower((string)($b['nachname'] ?? ''));
                        if ($na === $nb) {
                            $va = strtolower((string)($a['vorname'] ?? ''));
                            $vb = strtolower((string)($b['vorname'] ?? ''));
                            return $va <=> $vb;
                        }
                        return $na <=> $nb;
                    });
                } catch (\Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::error('Genehmiger: Nachladen inaktiver Mitarbeiter für Dropdown fehlgeschlagen', [
                            'mitarbeiter_id' => $id > 0 ? $id : null,
                            'fehlende_ids'   => $fehlende,
                            'exception'      => $e->getMessage(),
                        ], $id > 0 ? $id : null, null, 'genehmiger');
                    }
                }
            }
        }


        // Abteilungen: Liste aller aktiven Abteilungen (für Mitarbeiter-Zuordnung + scoped Rollen)
        $alleAbteilungen = [];
        try {
            $abteilungModel  = new AbteilungModel();
            $alleAbteilungen = $abteilungModel->holeAlleAktiven();
        } catch (\Throwable) {
            $alleAbteilungen = [];
        }

        // Scoped Rollen (abteilung) laden (Phase 1)
        $rollenScopesAbteilung = [];
        if ($id > 0 && $mitarbeiter !== null) {
            try {
                $pdo = Database::getInstanz()->getPdo();
                $pdo->query('SELECT 1 FROM mitarbeiter_hat_rolle_scope LIMIT 1');

                $stmt = $pdo->prepare(
                    "SELECT id, rolle_id, scope_id, gilt_unterbereiche
                     FROM mitarbeiter_hat_rolle_scope
                     WHERE mitarbeiter_id = :mid
                       AND scope_typ = 'abteilung'
                     ORDER BY scope_id ASC, rolle_id ASC"
                );
                $stmt->execute([':mid' => $id]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (is_array($rows)) {
                    $rollenScopesAbteilung = $rows;
                }
            } catch (\Throwable) {
                $rollenScopesAbteilung = [];
            }
        }



        // Abteilungs-Mitgliedschaften (optional, Mehrfach moeglich) laden
        if ($id > 0 && $mitarbeiter !== null) {
            $mitarbeiter['abteilungen_ids'] = [];
            try {
                $db = Database::getInstanz();
                $rows = $db->fetchAlle(
                    'SELECT abteilung_id FROM mitarbeiter_hat_abteilung WHERE mitarbeiter_id = :mid ORDER BY abteilung_id ASC',
                    ['mid' => $id]
                );

                $tmp = [];
                foreach ($rows as $r) {
                    $aid = (int)($r['abteilung_id'] ?? 0);
                    if ($aid > 0) {
                        $tmp[$aid] = true;
                    }
                }
                $mitarbeiter['abteilungen_ids'] = array_keys($tmp);
            } catch (\Throwable) {
                $mitarbeiter['abteilungen_ids'] = [];
            }
        }
        // Stammabteilung (optional) laden
        if ($id > 0 && $mitarbeiter !== null) {
            try {
                $db = Database::getInstanz();
                $row = $db->fetchEine(
                    'SELECT abteilung_id FROM mitarbeiter_hat_abteilung WHERE mitarbeiter_id = :mid AND ist_stammabteilung = 1 LIMIT 1',
                    ['mid' => $id]
                );
                $mitarbeiter['stammabteilung_id'] = $row !== null ? (int)($row['abteilung_id'] ?? 0) : 0;
            } catch (\Throwable) {
                $mitarbeiter['stammabteilung_id'] = 0;
            }
        }

        // Stundenkonto (nur Anzeige/Verwaltung im Admin)
        $stundenkontoDarfVerwalten = $this->authService->hatRecht('STUNDENKONTO_VERWALTEN');
        $stundenkontoSaldoAktuellMinuten = null;
        $stundenkontoSaldoAktuellText = null;
        $stundenkontoLetzteKorrekturen = [];
        $stundenkontoLetzteBatches = [];

        if ($id > 0 && $mitarbeiter !== null) {
            $formatMinuten = static function (?int $minuten): string {
                if ($minuten === null) {
                    return '—';
                }

                $sign = $minuten < 0 ? '-' : '+';
                $abs = abs($minuten);
                $h = intdiv($abs, 60);
                $m = $abs % 60;

                return sprintf('%s%d:%02d', $sign, $h, $m);
            };

            try {
                // "Stand heute" = alle Korrekturen mit wirksam_datum < morgen
                $morgen = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
                $svc = StundenkontoService::getInstanz();
                $stundenkontoSaldoAktuellMinuten = $svc->holeSaldoMinutenBisDatumExklusiv($id, $morgen);
                $stundenkontoSaldoAktuellText = $formatMinuten($stundenkontoSaldoAktuellMinuten);

                $db = Database::getInstanz();
                $stundenkontoLetzteKorrekturen = $db->fetchAlle(
                    "SELECT k.wirksam_datum, k.delta_minuten, k.typ, k.begruendung, k.erstellt_am,
                            m.vorname AS erstellt_von_vorname, m.nachname AS erstellt_von_nachname
                     FROM stundenkonto_korrektur k
                     LEFT JOIN mitarbeiter m ON m.id = k.erstellt_von_mitarbeiter_id
                     WHERE k.mitarbeiter_id = :mid
                     ORDER BY k.wirksam_datum DESC, k.id DESC
                     LIMIT 10",
                    ['mid' => $id]
                );

                $stundenkontoLetzteBatches = $db->fetchAlle(
                    "SELECT b.id, b.modus, b.von_datum, b.bis_datum, b.gesamt_minuten, b.minuten_pro_tag, b.nur_arbeitstage, b.begruendung, b.erstellt_am,
                            m.vorname AS erstellt_von_vorname, m.nachname AS erstellt_von_nachname,
                            (SELECT COUNT(1) FROM stundenkonto_korrektur k2 WHERE k2.batch_id = b.id) AS anzahl_tage
                     FROM stundenkonto_batch b
                     LEFT JOIN mitarbeiter m ON m.id = b.erstellt_von_mitarbeiter_id
                     WHERE b.mitarbeiter_id = :mid
                     ORDER BY b.erstellt_am DESC, b.id DESC
                     LIMIT 10",
                    ['mid' => $id]
                );
            } catch (\Throwable) {
                // Tabellen koennen in Legacy/Setup fehlen; dann einfach nichts anzeigen.
                $stundenkontoSaldoAktuellMinuten = null;
                $stundenkontoSaldoAktuellText = null;
                $stundenkontoLetzteKorrekturen = [];
                $stundenkontoLetzteBatches = [];
            }
        }

        require __DIR__ . '/../views/mitarbeiter/formular.php';
    }

    /**
     * Ermittelt Rechte, die über Rollen "vererbt" werden, und das "effektive" Ergebnis
     * nach Anwendung von Mitarbeiter-Overrides (Allow/Deny).
     *
     * @param array<int,int>              $rollenIds
     * @param array<int,int>              $rechteOverrides recht_id => 1 (allow) | 0 (deny)
     * @param array<int,array<string,mixed>> $alleRechte      Liste aus RolleModel::holeAlleRechte()
     *
     * @return array{vererbt: array<int,bool>, effektiv: array<int,bool>}
     */
    private function berechneRechteVererbtUndEffektiv(array $rollenIds, array $rechteOverrides, array $alleRechte): array
    {
        // Recht-ID -> aktiv
        $aktivMap = [];
        foreach ($alleRechte as $r) {
            $rid = (int)($r['id'] ?? 0);
            if ($rid > 0) {
                $aktivMap[$rid] = (int)($r['aktiv'] ?? 1) === 1;
            }
        }

        // Rollen-IDs normalisieren
        $tmp = [];
        foreach ($rollenIds as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) {
                $tmp[$rid] = true;
            }
        }
        $rollenIds = array_keys($tmp);

        // Vererbte Rechte (nur aktive Rechte, analog AuthService)
        $vererbt = [];
        if (count($rollenIds) > 0) {
            try {
                $pdo = Database::getInstanz()->getPdo();

                // Fail-safe: wenn Tabellen nicht existieren (Setup), nicht fatal sein.
                $pdo->query('SELECT 1 FROM rolle_hat_recht LIMIT 1');
                $pdo->query('SELECT 1 FROM recht LIMIT 1');

                $ph = [];
                $params = [];
                foreach ($rollenIds as $i => $rolleId) {
                    $k = ':r' . (int)$i;
                    $ph[] = $k;
                    $params[$k] = (int)$rolleId;
                }

                $sql = 'SELECT DISTINCT r.id AS recht_id
                        FROM rolle_hat_recht rhr
                        JOIN recht r ON r.id = rhr.recht_id
                        WHERE rhr.rolle_id IN (' . implode(',', $ph) . ')
                          AND r.aktiv = 1';

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                if (is_array($rows)) {
                    foreach ($rows as $rid) {
                        $rid = (int)$rid;
                        if ($rid > 0) {
                            $vererbt[$rid] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                $vererbt = [];
            }
        }

        // Effektiv = vererbt + Overrides
        $effektiv = $vererbt;

        foreach ($rechteOverrides as $rechtId => $allow) {
            $rechtId = (int)$rechtId;
            if ($rechtId <= 0) {
                continue;
            }

            // Inaktive Rechte sind nie effektiv (AuthService filtert r.aktiv=1)
            if (isset($aktivMap[$rechtId]) && $aktivMap[$rechtId] === false) {
                unset($effektiv[$rechtId]);
                continue;
            }

            if ((int)$allow === 1) {
                $effektiv[$rechtId] = true;
            } else {
                if (isset($effektiv[$rechtId])) {
                    unset($effektiv[$rechtId]);
                }
            }
        }

        // Safety: inaktive Rechte entfernen
        foreach ($effektiv as $rid => $_) {
            if (isset($aktivMap[$rid]) && $aktivMap[$rid] === false) {
                unset($effektiv[$rid]);
            }
        }

        return [
            'vererbt'  => $vererbt,
            'effektiv' => $effektiv,
        ];
    }

    /**
     * Speichert einen neuen oder bestehenden Mitarbeiter.
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?seite=mitarbeiter_admin');
            return;
        }

        // Stundenkonto-Korrektur (separates Mini-Formular im Mitarbeiter-Admin)
        if (isset($_POST['stundenkonto_only']) && (string)$_POST['stundenkonto_only'] === '1') {
            $this->speichereStundenkontoKorrekturNur();
            return;
        }

        // Stundenkonto-Verteilbuchung (Batch) (separates Mini-Formular im Mitarbeiter-Admin)
        if (isset($_POST['stundenkonto_batch_only']) && (string)$_POST['stundenkonto_batch_only'] === '1') {
            $this->speichereStundenkontoBatchNur();
            return;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $mitarbeiterIdNachSave = null;

        $vorname  = trim((string)($_POST['vorname'] ?? ''));
        $nachname = trim((string)($_POST['nachname'] ?? ''));

        $geburtsdatumRaw = trim((string)($_POST['geburtsdatum'] ?? ''));
        $geburtsdatum    = $geburtsdatumRaw !== '' ? $geburtsdatumRaw : null;

        $eintrittsdatumRaw = trim((string)($_POST['eintrittsdatum'] ?? ''));
        $eintrittsdatum    = $eintrittsdatumRaw !== '' ? $eintrittsdatumRaw : null;

        $wochenRaw = trim((string)($_POST['wochenarbeitszeit'] ?? ''));
        $wochenarbeitszeit = $wochenRaw === '' ? '0.00' : str_replace(',', '.', $wochenRaw);

        $urlaubRaw = trim((string)($_POST['urlaub_monatsanspruch'] ?? ''));
        $urlaubMonatsanspruch = $urlaubRaw === '' ? '0.00' : str_replace(',', '.', $urlaubRaw);

        $benutzername = trim((string)($_POST['benutzername'] ?? ''));
        $email        = trim((string)($_POST['email'] ?? ''));
        $personalnummer = trim((string)($_POST['personalnummer'] ?? ''));
        $rfidCode     = trim((string)($_POST['rfid_code'] ?? ''));


        $stammabteilungIdPost = (int)($_POST['stammabteilung_id'] ?? 0);


        // Abteilungen (Mitgliedschaften) – optional, Mehrfach moeglich
        $abteilungenIdsPost = $_POST['abteilungen_ids'] ?? [];
        if (!is_array($abteilungenIdsPost)) {
            $abteilungenIdsPost = [];
        }
        $tmpAbt = [];
        foreach ($abteilungenIdsPost as $aidRoh) {
            $aid = (int)$aidRoh;
            if ($aid > 0) {
                $tmpAbt[$aid] = true;
            }
        }
        // Stammabteilung ist immer auch eine Mitgliedschaft
        if ($stammabteilungIdPost > 0) {
            $tmpAbt[$stammabteilungIdPost] = true;
        }
        $abteilungenIdsPost = array_keys($tmpAbt);
        sort($abteilungenIdsPost);

        $aktiv = isset($_POST['aktiv']) && (string)$_POST['aktiv'] === '1' ? 1 : 0;
        $loginErlaubt = isset($_POST['ist_login_berechtigt']) && (string)$_POST['ist_login_berechtigt'] === '1' ? 1 : 0;

        $rollenIdsPost = $_POST['rollen_ids'] ?? [];
        if (!is_array($rollenIdsPost)) {
            $rollenIdsPost = [];
        }

        // Scoped Rollen (abteilung) – POST (Phase 1)
        $scopeAbtAddRolleId     = (int)($_POST['scope_abteilung_add_rolle_id'] ?? 0);
        $scopeAbtAddAbteilungId = (int)($_POST['scope_abteilung_add_abteilung_id'] ?? 0);
        $scopeAbtAddUnter       = isset($_POST['scope_abteilung_add_unterbereiche']) ? 1 : 0;

        $scopeAbtRowIds = $_POST['scope_abteilung_row_ids'] ?? [];
        if (!is_array($scopeAbtRowIds)) {
            $scopeAbtRowIds = [];
        }
        $scopeAbtRowIds = array_map('intval', $scopeAbtRowIds);

        $scopeAbtUnter = $_POST['scope_abteilung_unterbereiche'] ?? [];
        if (!is_array($scopeAbtUnter)) {
            $scopeAbtUnter = [];
        }

        $scopeAbtDel = $_POST['scope_abteilung_delete'] ?? [];
        if (!is_array($scopeAbtDel)) {
            $scopeAbtDel = [];
        }
        $scopeAbtDelIdsTmp = [];
        foreach ($scopeAbtDel as $delIdRoh) {
            $did = (int)$delIdRoh;
            if ($did > 0) {
                $scopeAbtDelIdsTmp[$did] = true;
            }
        }
        $scopeAbtDelIds = array_keys($scopeAbtDelIdsTmp);

        $passwortNeu = trim((string)($_POST['passwort_neu'] ?? ''));

        // Rechte-Overrides aus POST parsen ("" = vererbt)
        $rechtOverridesToSave = [];
        $overridePost = $_POST['recht_override'] ?? [];
        if (is_array($overridePost)) {
            foreach ($overridePost as $rechtIdRoh => $wert) {
                $rid = (int)$rechtIdRoh;
                if ($rid <= 0) {
                    continue;
                }
                $v = is_string($wert) ? trim($wert) : (is_int($wert) ? (string)$wert : '');
                if ($v === '') {
                    continue;
                }
                if ($v === '1') {
                    $rechtOverridesToSave[$rid] = 1;
                } elseif ($v === '0') {
                    $rechtOverridesToSave[$rid] = 0;
                }
            }
        }

        // Minimale Validierung
        if ($vorname === '' || $nachname === '') {
            $fehlermeldung = 'Vorname und Nachname sind Pflichtfelder.';

            $mitarbeiter = [
                'id'                    => $id > 0 ? $id : null,
                'vorname'               => $vorname,
                'nachname'              => $nachname,
                'geburtsdatum'          => $geburtsdatum,
                'eintrittsdatum'        => $eintrittsdatum,
                'wochenarbeitszeit'     => $wochenarbeitszeit,
                'urlaub_monatsanspruch' => $urlaubMonatsanspruch,
                'benutzername'          => $benutzername,
                'email'                 => $email,
                'personalnummer'        => $personalnummer,
                'rfid_code'             => $rfidCode,
                'stammabteilung_id'    => $stammabteilungIdPost > 0 ? $stammabteilungIdPost : null,
                'aktiv'                 => $aktiv,
                'ist_login_berechtigt'  => $loginErlaubt,
            ];

            $this->renderFormMitFehler($mitarbeiter, $fehlermeldung, $rollenIdsPost);
            return;
        }

        // Optionalfelder: leere Strings als NULL speichern
        $benutzernameDb = $benutzername !== '' ? $benutzername : null;
        $emailDb        = $email !== '' ? $email : null;
        $personalnummerDb = $personalnummer !== '' ? $personalnummer : null;
        $rfidDb         = $rfidCode !== '' ? $rfidCode : null;

        if ($personalnummerDb !== null && strlen($personalnummerDb) > 32) {
            $fehlermeldung = 'Personalnummer darf maximal 32 Zeichen haben.';

            $mitarbeiter = [
                'id'                    => $id > 0 ? $id : null,
                'vorname'               => $vorname,
                'nachname'              => $nachname,
                'geburtsdatum'          => $geburtsdatum,
                'eintrittsdatum'        => $eintrittsdatum,
                'wochenarbeitszeit'     => $wochenarbeitszeit,
                'urlaub_monatsanspruch' => $urlaubMonatsanspruch,
                'benutzername'          => $benutzername,
                'email'                 => $email,
                'personalnummer'        => $personalnummer,
                'rfid_code'             => $rfidCode,
                'stammabteilung_id'    => $stammabteilungIdPost > 0 ? $stammabteilungIdPost : null,
                'aktiv'                 => $aktiv,
                'ist_login_berechtigt'  => $loginErlaubt,
            ];

            $this->renderFormMitFehler($mitarbeiter, $fehlermeldung, $rollenIdsPost);
            return;
        }

        // Personalnummer: Duplikat-Check (freundliche Fehlermeldung statt DB-Fehler)
        if ($personalnummerDb !== null) {
            try {
                $db = Database::getInstanz();
                $row = $db->fetchEine(
                    'SELECT id FROM mitarbeiter WHERE personalnummer = :pn AND id <> :id LIMIT 1',
                    ['pn' => $personalnummerDb, 'id' => $id]
                );
            } catch (\Throwable $e) {
                $row = null;
                if (class_exists('Logger')) {
                    Logger::error('Personalnummer-Duplikatcheck fehlgeschlagen', [
                        'mitarbeiter_id'  => $id > 0 ? $id : null,
                        'personalnummer'  => $personalnummerDb,
                        'exception'       => $e->getMessage(),
                    ], $id > 0 ? $id : null, null, 'mitarbeiter');
                }
            }

            if ($row !== null) {
                $fehlermeldung = 'Personalnummer ist bereits vergeben.';

                $mitarbeiter = [
                    'id'                    => $id > 0 ? $id : null,
                    'vorname'               => $vorname,
                    'nachname'              => $nachname,
                    'geburtsdatum'          => $geburtsdatum,
                    'eintrittsdatum'        => $eintrittsdatum,
                    'wochenarbeitszeit'     => $wochenarbeitszeit,
                    'urlaub_monatsanspruch' => $urlaubMonatsanspruch,
                    'benutzername'          => $benutzername,
                    'email'                 => $email,
                    'personalnummer'        => $personalnummer,
                    'rfid_code'             => $rfidCode,
                    'stammabteilung_id'    => $stammabteilungIdPost > 0 ? $stammabteilungIdPost : null,
                    'aktiv'                 => $aktiv,
                    'ist_login_berechtigt'  => $loginErlaubt,
                ];

                $this->renderFormMitFehler($mitarbeiter, $fehlermeldung, $rollenIdsPost);
                return;
            }
        }

        // Passwort-Hash bestimmen
        $passwortHash = null;
        if ($id > 0) {
            $bestehend = $this->mitarbeiterModel->holeNachId($id);
            $passwortHashAlt = $bestehend['passwort_hash'] ?? null;

            if ($passwortNeu !== '') {
                $passwortHash = password_hash($passwortNeu, PASSWORD_DEFAULT);
            } else {
                $passwortHash = $passwortHashAlt;
            }
        } else {
            if ($passwortNeu !== '') {
                $passwortHash = password_hash($passwortNeu, PASSWORD_DEFAULT);
            } else {
                $passwortHash = null;
            }
        }

        $datenMitarbeiter = [
            'vorname'               => $vorname,
            'nachname'              => $nachname,
            'geburtsdatum'          => $geburtsdatum,
            'eintrittsdatum'        => $eintrittsdatum,
            'wochenarbeitszeit'     => $wochenarbeitszeit,
            'urlaub_monatsanspruch' => $urlaubMonatsanspruch,
            'benutzername'          => $benutzernameDb,
            'email'                 => $emailDb,
            'personalnummer'        => $personalnummerDb,
            'passwort_hash'         => $passwortHash,
            'rfid_code'             => $rfidDb,
            'aktiv'                 => $aktiv,
            'ist_login_berechtigt'  => $loginErlaubt,
        ];

        if ($id > 0) {
            $ok = $this->mitarbeiterModel->aktualisiereMitarbeiter($id, $datenMitarbeiter);
            if (!$ok) {
                $fehlermeldung = 'Der Mitarbeiter konnte nicht gespeichert werden.';

                $mitarbeiter               = $datenMitarbeiter;
                $mitarbeiter['id']         = $id;
                $mitarbeiter['stammabteilung_id'] = $stammabteilungIdPost;
                $mitarbeiter['abteilungen_ids'] = $abteilungenIdsPost;

                // Abteilungen-Liste laden (für Dropdowns)
                $alleAbteilungen = [];
                try {
                    $abteilungModel  = new AbteilungModel();
                    $alleAbteilungen = $abteilungModel->holeAlleAktiven();
                } catch (\Throwable) {
                    $alleAbteilungen = [];
                }

                $rollenScopesAbteilung = [];

                $rollen                    = [];
                $genehmiger                = [];
                $alleRollen                = [];
                $rollenIdsAusgewaehlt      = $rollenIdsPost;

                try {
                    $rolleModel  = new RolleModel();
                    $alleRollen  = $rolleModel->holeAlleAktiven();
                } catch (\Throwable $e) {
                    $alleRollen = [];
                    if (class_exists('Logger')) {
                        Logger::error('Rollen-Liste konnte nicht geladen werden (Fehler beim Aktualisieren)', [
                            'mitarbeiter_id' => $id,
                            'exception'      => $e->getMessage(),
                        ], $id, null, 'rolle');
                    }
                }

                require __DIR__ . '/../views/mitarbeiter/formular.php';
                return;
            }

            $mitarbeiterIdNachSave = $id;
        } else {
            $neueId = $this->mitarbeiterModel->erstelleMitarbeiter($datenMitarbeiter);
            if ($neueId === null) {
                $fehlermeldung = 'Der Mitarbeiter konnte nicht angelegt werden (evtl. Konflikt bei Benutzername/E-Mail/RFID).';

                $mitarbeiter               = $datenMitarbeiter;
                $mitarbeiter['id']         = null;
                $mitarbeiter['stammabteilung_id'] = $stammabteilungIdPost;
                $mitarbeiter['abteilungen_ids'] = $abteilungenIdsPost;

                // Abteilungen-Liste laden (für Dropdowns)
                $alleAbteilungen = [];
                try {
                    $abteilungModel  = new AbteilungModel();
                    $alleAbteilungen = $abteilungModel->holeAlleAktiven();
                } catch (\Throwable) {
                    $alleAbteilungen = [];
                }

                $rollenScopesAbteilung = [];

                $rollen                    = [];
                $genehmiger                = [];
                $alleRollen                = [];
                $rollenIdsAusgewaehlt      = $rollenIdsPost;

                try {
                    $rolleModel  = new RolleModel();
                    $alleRollen  = $rolleModel->holeAlleAktiven();
                } catch (\Throwable $e) {
                    $alleRollen = [];
                    if (class_exists('Logger')) {
                        Logger::error('Rollen-Liste konnte nicht geladen werden (Fehler beim Anlegen)', [
                            'exception' => $e->getMessage(),
                        ], null, null, 'rolle');
                    }
                }

                require __DIR__ . '/../views/mitarbeiter/formular.php';
                return;
            }

            $mitarbeiterIdNachSave = (int)$neueId;
        }

        // Rollen- und Genehmiger-Zuordnung speichern (falls eine Mitarbeiter-ID vorhanden ist)
        if ($mitarbeiterIdNachSave !== null) {
            // Personalnummer speichern (separat, da das alte Model sie noch nicht schreibt)
            try {
                $db = Database::getInstanz();
                $db->ausfuehren(
                    'UPDATE mitarbeiter SET personalnummer = :pn WHERE id = :id',
                    ['pn' => $personalnummerDb, 'id' => $mitarbeiterIdNachSave]
                );
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Speichern der Personalnummer eines Mitarbeiters (Controller)', [
                        'mitarbeiter_id' => $mitarbeiterIdNachSave,
                        'personalnummer' => $personalnummerDb,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'mitarbeiter');
                }

                $_SESSION['mitarbeiter_admin_flash_error'] = 'Personalnummer konnte nicht gespeichert werden. Bitte Admin informieren.';
                header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . (int)$mitarbeiterIdNachSave);
                exit;
            }

            // Eintrittsdatum speichern (optional; darf auch NULL sein)
            // (Separat, da das alte Model den Wert (noch) nicht schreibt)
            try {
                $db = Database::getInstanz();
                $db->ausfuehren(
                    'UPDATE mitarbeiter SET eintrittsdatum = :dt WHERE id = :id',
                    ['dt' => $eintrittsdatum, 'id' => $mitarbeiterIdNachSave]
                );
            } catch (\Throwable $e) {
                // Soft-Fail: Wenn Migration noch nicht gelaufen ist (Spalte fehlt), soll der Save nicht abbrechen.
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Speichern des Eintrittsdatums eines Mitarbeiters (Controller)', [
                        'mitarbeiter_id' => $mitarbeiterIdNachSave,
                        'eintrittsdatum' => $eintrittsdatum,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'mitarbeiter');
                }
            }

            // Abteilungen speichern (Mitgliedschaften + optionale Stammabteilung)
            try {
                $pdo = Database::getInstanz()->getPdo();
                $pdo->query('SELECT 1 FROM mitarbeiter_hat_abteilung LIMIT 1');

                $pdo->beginTransaction();

                // Alle bisherigen Mitgliedschaften ersetzen (optional kann auch leer sein)
                $stmtDel = $pdo->prepare('DELETE FROM mitarbeiter_hat_abteilung WHERE mitarbeiter_id = :mid');
                $stmtDel->execute([':mid' => $mitarbeiterIdNachSave]);

                if (count($abteilungenIdsPost) > 0) {
                    $stmtIns = $pdo->prepare(
                        'INSERT INTO mitarbeiter_hat_abteilung (mitarbeiter_id, abteilung_id, ist_stammabteilung) VALUES (:mid, :aid, :stamm)'
                    );

                    foreach ($abteilungenIdsPost as $aid) {
                        $aid = (int)$aid;
                        if ($aid <= 0) {
                            continue;
                        }

                        $istStamm = ($stammabteilungIdPost > 0 && $aid === (int)$stammabteilungIdPost) ? 1 : 0;
                        $stmtIns->execute([
                            ':mid'   => $mitarbeiterIdNachSave,
                            ':aid'   => $aid,
                            ':stamm' => $istStamm,
                        ]);
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                try {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (\Throwable) {
                    // ignore
                }

                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Speichern der Abteilungs-Mitgliedschaften eines Mitarbeiters (Controller)', [
                        'mitarbeiter_id'       => $mitarbeiterIdNachSave,
                        'stammabteilung_id'    => $stammabteilungIdPost,
                        'abteilungen_ids'      => $abteilungenIdsPost,
                        'exception'            => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'abteilung');
                }
                $_SESSION['mitarbeiter_admin_flash_error'] = 'Abteilungen konnten nicht gespeichert werden. Bitte Admin informieren.';
            }

            // Rollen speichern
            try {
                $rollenZuordnungModel = new MitarbeiterHatRolleModel();
                $rollenZuordnungModel->speichereRollenFuerMitarbeiter($mitarbeiterIdNachSave, $rollenIdsPost);
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Speichern der Rollen eines Mitarbeiters (Controller)', [
                        'mitarbeiter_id' => $mitarbeiterIdNachSave,
                        'rollen_ids'     => $rollenIdsPost,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'rolle');
                }
            }



            // Wenn der Admin seinen eigenen Datensatz editiert hat: Session-Rechte/Superuser-Cache leeren,
            // damit Rollen-Änderungen sofort greifen (kein Logout nötig).
            try {
                $angemeldetId = $this->authService->holeAngemeldeteMitarbeiterId();
                if ($angemeldetId !== null && (int)$angemeldetId === (int)$mitarbeiterIdNachSave) {
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }
                    unset(
                        $_SESSION['auth_rechte_codes'],
                        $_SESSION['auth_rechte_mitarbeiter_id'],
                        $_SESSION['auth_ist_superuser'],
                        $_SESSION['auth_ist_superuser_mitarbeiter_id']
                    );
                }
            } catch (\Throwable) {
                // ignorieren
            }

            // Scoped Rollen (global) spiegeln (neues Modell, Migration 17).
            // Fail-safe: wenn Tabelle nicht existiert, bleibt Legacy bestehen.
            try {
                $pdo = Database::getInstanz()->getPdo();
                $pdo->query('SELECT 1 FROM mitarbeiter_hat_rolle_scope LIMIT 1');

                $pdo->beginTransaction();

                $stmtDelScope = $pdo->prepare(
                    "DELETE FROM mitarbeiter_hat_rolle_scope
                     WHERE mitarbeiter_id = :mid
                       AND scope_typ = 'global'
                       AND scope_id = 0"
                );
                $stmtDelScope->execute([':mid' => $mitarbeiterIdNachSave]);

                $rollenIdsUniq = [];
                foreach ($rollenIdsPost as $ridRoh) {
                    $rid = (int)$ridRoh;
                    if ($rid > 0) {
                        $rollenIdsUniq[$rid] = true;
                    }
                }

                if (count($rollenIdsUniq) > 0) {
                    $stmtInsScope = $pdo->prepare(
                        "INSERT INTO mitarbeiter_hat_rolle_scope (mitarbeiter_id, rolle_id, scope_typ, scope_id, gilt_unterbereiche)
                         VALUES (:mid, :rid, 'global', 0, 1)"
                    );

                    foreach (array_keys($rollenIdsUniq) as $rid) {
                        $stmtInsScope->execute([
                            ':mid' => $mitarbeiterIdNachSave,
                            ':rid' => (int)$rid,
                        ]);
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                // Transaktion sauber beenden (falls gestartet)
                try {
                    $pdo = Database::getInstanz()->getPdo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (\Throwable) {
                    // ignorieren
                }

                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Spiegeln der globalen Rollen in mitarbeiter_hat_rolle_scope', [
                        'mitarbeiter_id' => $mitarbeiterIdNachSave,
                        'rollen_ids'     => $rollenIdsPost,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'rolle');
                }
            }
            
            // Scoped Rollen (abteilung) speichern (Phase 1)
            // Fail-safe: wenn Tabelle noch nicht existiert (Migration 17 nicht eingespielt), bleibt alles wie vorher.
            if (count($scopeAbtRowIds) > 0 || count($scopeAbtDelIds) > 0 || ($scopeAbtAddRolleId > 0 && $scopeAbtAddAbteilungId > 0)) {
                $pdoScope = null;
                try {
                    $pdoScope = Database::getInstanz()->getPdo();
                    $pdoScope->query('SELECT 1 FROM mitarbeiter_hat_rolle_scope LIMIT 1');

                    $pdoScope->beginTransaction();

                    // Löschen
                    if (count($scopeAbtDelIds) > 0) {
                        $stmtDelAbt = $pdoScope->prepare(
                            "DELETE FROM mitarbeiter_hat_rolle_scope
                             WHERE id = :id
                               AND mitarbeiter_id = :mid
                               AND scope_typ = 'abteilung'"
                        );
                        foreach ($scopeAbtDelIds as $delId) {
                            $delId = (int)$delId;
                            if ($delId <= 0) {
                                continue;
                            }
                            $stmtDelAbt->execute([
                                ':id'  => $delId,
                                ':mid' => $mitarbeiterIdNachSave,
                            ]);
                        }
                    }

                    // Unterbereiche-Flag aktualisieren (für verbleibende Einträge)
                    if (count($scopeAbtRowIds) > 0) {
                        $delSet = [];
                        foreach ($scopeAbtDelIds as $d) {
                            $d = (int)$d;
                            if ($d > 0) {
                                $delSet[$d] = true;
                            }
                        }

                        $stmtUpdAbt = $pdoScope->prepare(
                            "UPDATE mitarbeiter_hat_rolle_scope
                             SET gilt_unterbereiche = :gilt
                             WHERE id = :id
                               AND mitarbeiter_id = :mid
                               AND scope_typ = 'abteilung'"
                        );

                        foreach ($scopeAbtRowIds as $rowId) {
                            $rowId = (int)$rowId;
                            if ($rowId <= 0 || isset($delSet[$rowId])) {
                                continue;
                            }

                            $gilt = (isset($scopeAbtUnter[(string)$rowId]) || isset($scopeAbtUnter[$rowId])) ? 1 : 0;

                            $stmtUpdAbt->execute([
                                ':gilt' => $gilt,
                                ':id'   => $rowId,
                                ':mid'  => $mitarbeiterIdNachSave,
                            ]);
                        }
                    }

                    // Hinzufügen (ein Eintrag pro Speichern)
                    if ($scopeAbtAddRolleId > 0 && $scopeAbtAddAbteilungId > 0) {
                        $stmtInsAbt = $pdoScope->prepare(
                            "INSERT INTO mitarbeiter_hat_rolle_scope
                                (mitarbeiter_id, rolle_id, scope_typ, scope_id, gilt_unterbereiche)
                             VALUES
                                (:mid, :rid, 'abteilung', :sid, :gilt)
                             ON DUPLICATE KEY UPDATE gilt_unterbereiche = VALUES(gilt_unterbereiche)"
                        );
                        $stmtInsAbt->execute([
                            ':mid'  => $mitarbeiterIdNachSave,
                            ':rid'  => $scopeAbtAddRolleId,
                            ':sid'  => $scopeAbtAddAbteilungId,
                            ':gilt' => $scopeAbtAddUnter,
                        ]);
                    }

                    $pdoScope->commit();
                } catch (\Throwable $e) {
                    // Wenn Tabelle fehlt: stillschweigend (Setup/Legacy). Sonst Fehler sichtbar machen.
                    $msg = $e->getMessage();
                    $tableMissing =
                        (stripos($msg, 'mitarbeiter_hat_rolle_scope') !== false)
                        && (stripos($msg, 'doesn\'t exist') !== false || stripos($msg, 'unknown table') !== false || stripos($msg, '42s02') !== false);

                    try {
                        if ($pdoScope instanceof \PDO && $pdoScope->inTransaction()) {
                            $pdoScope->rollBack();
                        }
                    } catch (\Throwable) {
                        // ignorieren
                    }

                    if (!$tableMissing) {
                        if (class_exists('Logger')) {
                            Logger::error('Fehler beim Speichern der Abteilungs-Rollen eines Mitarbeiters (Controller)', [
                                'mitarbeiter_id' => $mitarbeiterIdNachSave,
                                'add_rolle_id'   => $scopeAbtAddRolleId,
                                'add_scope_id'   => $scopeAbtAddAbteilungId,
                                'delete_ids'     => $scopeAbtDelIds,
                                'exception'      => $e->getMessage(),
                            ], $mitarbeiterIdNachSave, null, 'rolle');
                        }

                        if (session_status() !== PHP_SESSION_ACTIVE) {
                            session_start();
                        }
                        $_SESSION['mitarbeiter_admin_flash_error'] = 'Abteilungs-Rollen konnten nicht gespeichert werden. Bitte Admin informieren.';
                        header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . (int)$mitarbeiterIdNachSave);
                        exit;
                    }
                }
            }

// Genehmiger speichern
            $genehmigerIds        = $_POST['genehmiger_id'] ?? [];
            $genehmigerPrioritaet = $_POST['genehmiger_prio'] ?? [];
            $genehmigerBeschr     = $_POST['genehmiger_beschreibung'] ?? [];

            if (!is_array($genehmigerIds)) {
                $genehmigerIds = [];
            }
            if (!is_array($genehmigerPrioritaet)) {
                $genehmigerPrioritaet = [];
            }
            if (!is_array($genehmigerBeschr)) {
                $genehmigerBeschr = [];
            }

            $genehmigerDaten = [];

            foreach ($genehmigerIds as $index => $gidRoh) {
                $gid = (int)$gidRoh;
                if ($gid <= 0) {
                    continue;
                }

                $prioRoh = $genehmigerPrioritaet[$index] ?? null;
                $prio    = $prioRoh !== null && $prioRoh !== '' ? (int)$prioRoh : 0;
                $besch   = $genehmigerBeschr[$index] ?? null;

                $genehmigerDaten[] = [
                    'genehmiger_mitarbeiter_id' => $gid,
                    'prioritaet'                => $prio,
                    'kommentar'                => $besch,
                ];
            }

            try {
                $genehmigerModel = new MitarbeiterGenehmigerModel();
                $okGenehmiger = $genehmigerModel->speichereGenehmigerFuerMitarbeiter($mitarbeiterIdNachSave, $genehmigerDaten);

                if ($okGenehmiger !== true) {
                    $_SESSION['mitarbeiter_admin_flash_error'] = 'Genehmiger konnten nicht gespeichert werden. Bitte Admin informieren.';
                    header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . (int)$mitarbeiterIdNachSave);
                    exit;
                }
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Speichern der Genehmiger eines Mitarbeiters (Controller)', [
                        'mitarbeiter_id' => $mitarbeiterIdNachSave,
                        'genehmiger'     => $genehmigerDaten,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'genehmiger');
                }

                $_SESSION['mitarbeiter_admin_flash_error'] = 'Genehmiger konnten nicht gespeichert werden. Bitte Admin informieren.';
                header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . (int)$mitarbeiterIdNachSave);
                exit;
            }

            // Rechte-Overrides speichern (Allow/Deny)
            try {
                $pdo = Database::getInstanz()->getPdo();

                // Fail-safe: wenn Tabelle nicht existiert (Setup), nichts kaputt machen.
                $pdo->query('SELECT 1 FROM mitarbeiter_hat_recht LIMIT 1');

                $pdo->beginTransaction();

                $stmtDel = $pdo->prepare('DELETE FROM mitarbeiter_hat_recht WHERE mitarbeiter_id = :mid');
                $stmtDel->execute([':mid' => $mitarbeiterIdNachSave]);

                if (count($rechtOverridesToSave) > 0) {
                    $stmtIns = $pdo->prepare(
                        'INSERT INTO mitarbeiter_hat_recht (mitarbeiter_id, recht_id, erlaubt) VALUES (:mid, :rid, :allow)'
                    );

                    foreach ($rechtOverridesToSave as $rid => $allow) {
                        $stmtIns->execute([
                            ':mid'   => $mitarbeiterIdNachSave,
                            ':rid'   => (int)$rid,
                            ':allow' => (int)$allow,
                        ]);
                    }
                }

                $pdo->commit();

                // Wenn der Admin seinen eigenen Datensatz editiert hat: Session-Rechte-Cache leeren,
                // damit Änderungen sofort greifen (kein Logout nötig).
                $angemeldetId = $this->authService->holeAngemeldeteMitarbeiterId();
                if ($angemeldetId !== null && (int)$angemeldetId === (int)$mitarbeiterIdNachSave) {
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }
                    unset(
                        $_SESSION['auth_rechte_codes'],
                        $_SESSION['auth_rechte_mitarbeiter_id'],
                        $_SESSION['auth_ist_superuser'],
                        $_SESSION['auth_ist_superuser_mitarbeiter_id']
                    );
                }
            } catch (\Throwable $e) {
                // Transaktion sauber beenden
                try {
                    $pdo = Database::getInstanz()->getPdo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (\Throwable) {
                    // ignorieren
                }

                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Speichern der Rechte-Overrides eines Mitarbeiters (Controller)', [
                        'mitarbeiter_id' => $mitarbeiterIdNachSave,
                        'overrides'      => $rechtOverridesToSave,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterIdNachSave, null, 'recht');
                }

                $_SESSION['mitarbeiter_admin_flash_error'] = 'Rechte-Overrides konnten nicht gespeichert werden. Bitte Admin informieren.';
                header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . (int)$mitarbeiterIdNachSave);
                exit;
            }
        }

        header('Location: ?seite=mitarbeiter_admin');
    }

    private function speichereStundenkontoKorrekturNur(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!$this->authService->hatRecht('STUNDENKONTO_VERWALTEN')) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Kein Zugriff: Stundenkonto verwalten.';
            header('Location: ?seite=mitarbeiter_admin');
            exit;
        }

        $mid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($mid <= 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Ungültiger Mitarbeiter (ID fehlt).';
            header('Location: ?seite=mitarbeiter_admin');
            exit;
        }

        $deltaRaw = trim((string)($_POST['stundenkonto_delta_stunden'] ?? ''));
        $begruendung = trim((string)($_POST['stundenkonto_begruendung'] ?? ''));
        $wirksam = trim((string)($_POST['stundenkonto_wirksam_datum'] ?? ''));

        if ($wirksam === '') {
            $wirksam = (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wirksam)) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Wirksam-Datum ist ungültig.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        if ($deltaRaw === '') {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Delta (Stunden) ist Pflicht.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        $deltaNorm = str_replace(',', '.', $deltaRaw);
        if (!is_numeric($deltaNorm)) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Delta (Stunden) muss eine Zahl sein.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        $deltaStunden = (float)$deltaNorm;
        $deltaMinuten = (int)round($deltaStunden * 60);

        if ($deltaMinuten === 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Delta darf nicht 0 sein.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        if ($begruendung === '') {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Begründung ist Pflicht.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        $erstelltVon = $this->authService->holeAngemeldeteMitarbeiterId();
        if ($erstelltVon === null || $erstelltVon <= 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Nicht angemeldet.';
            header('Location: ?seite=login');
            exit;
        }

        try {
            $db = Database::getInstanz();
            $db->ausfuehren(
                'INSERT INTO stundenkonto_korrektur (mitarbeiter_id, wirksam_datum, delta_minuten, typ, batch_id, begruendung, erstellt_von_mitarbeiter_id)
                 VALUES (:mid, :wirksam, :delta, \'manuell\', NULL, :begr, :von)',
                [
                    'mid'     => $mid,
                    'wirksam' => $wirksam,
                    'delta'   => $deltaMinuten,
                    'begr'    => $begruendung,
                    'von'     => $erstelltVon,
                ]
            );

            if (class_exists('Logger')) {
                $korrekturId = 0;
                try {
                    $korrekturId = (int)$db->letzteInsertId();
                } catch (\Throwable) {
                    $korrekturId = 0;
                }

                Logger::info('Stundenkonto-Korrektur gebucht', [
                    'korrektur_id'   => $korrekturId,
                    'mitarbeiter_id' => $mid,
                    'wirksam_datum'  => $wirksam,
                    'delta_minuten'  => $deltaMinuten,
                    'begruendung'    => $begruendung,
                    'erstellt_von'   => $erstelltVon,
                    'typ'            => 'manuell',
                ], $mid, null, 'stundenkonto');
            }

            $_SESSION['mitarbeiter_admin_flash_success'] = 'Stundenkonto-Korrektur gespeichert.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern der Stundenkonto-Korrektur', [
                    'mitarbeiter_id' => $mid,
                    'wirksam'        => $wirksam,
                    'delta_minuten'  => $deltaMinuten,
                    'begruendung'    => $begruendung,
                    'exception'      => $e->getMessage(),
                ], $mid, null, 'stundenkonto');
            }

            $_SESSION['mitarbeiter_admin_flash_error'] = 'Stundenkonto-Korrektur konnte nicht gespeichert werden. Bitte Admin informieren.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }
    }

    private function speichereStundenkontoBatchNur(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!$this->authService->hatRecht('STUNDENKONTO_VERWALTEN')) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Kein Zugriff: Stundenkonto verwalten.';
            header('Location: ?seite=mitarbeiter_admin');
            exit;
        }

        $mid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($mid <= 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Ungültiger Mitarbeiter (ID fehlt).';
            header('Location: ?seite=mitarbeiter_admin');
            exit;
        }

        $modus = trim((string)($_POST['stundenkonto_batch_modus'] ?? ''));
        $deltaRaw = trim((string)($_POST['stundenkonto_batch_delta_stunden'] ?? ''));
        $von = trim((string)($_POST['stundenkonto_batch_von_datum'] ?? ''));
        $bis = trim((string)($_POST['stundenkonto_batch_bis_datum'] ?? ''));
        $nurArbeitstage = isset($_POST['stundenkonto_batch_nur_arbeitstage']) && (string)$_POST['stundenkonto_batch_nur_arbeitstage'] === '1';
        $begruendung = trim((string)($_POST['stundenkonto_batch_begruendung'] ?? ''));

        $gueltigeModi = ['gesamt_gleichmaessig', 'minuten_pro_tag'];
        if (!in_array($modus, $gueltigeModi, true)) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Modus ist ungültig.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $von) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bis)) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Von/Bis-Datum ist ungültig.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        try {
            $dtVon = new \DateTimeImmutable($von);
            $dtBis = new \DateTimeImmutable($bis);
        } catch (\Throwable) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Von/Bis-Datum ist ungültig.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        if ($dtVon > $dtBis) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Von-Datum darf nicht nach dem Bis-Datum liegen.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        if ($deltaRaw === '') {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Delta (Stunden) ist Pflicht.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        $deltaNorm = str_replace(',', '.', $deltaRaw);
        if (!is_numeric($deltaNorm)) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Delta (Stunden) muss eine Zahl sein.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        $deltaStunden = (float)$deltaNorm;
        $deltaMinuten = (int)round($deltaStunden * 60);

        if ($deltaMinuten === 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Delta darf nicht 0 sein.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        if ($begruendung === '') {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Begründung ist Pflicht.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        $erstelltVon = $this->authService->holeAngemeldeteMitarbeiterId();
        if ($erstelltVon === null || $erstelltVon <= 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Nicht angemeldet.';
            header('Location: ?seite=login');
            exit;
        }

        // Ziel-Tage ermitteln
        $tage = [];
        $period = new \DatePeriod(
            $dtVon,
            new \DateInterval('P1D'),
            $dtBis->modify('+1 day')
        );

        foreach ($period as $dt) {
            if (!($dt instanceof \DateTimeImmutable)) {
                continue;
            }

            if ($nurArbeitstage) {
                $dow = (int)$dt->format('N');
                if ($dow > 5) {
                    continue;
                }
            }

            $tage[] = $dt;
        }

        if (count($tage) === 0) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Es wurden keine passenden Tage im Zeitraum gefunden.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        // Guard: zu große Batchs vermeiden (Performance/Bedienbarkeit)
        if (count($tage) > 10000) {
            $_SESSION['mitarbeiter_admin_flash_error'] = 'Zu viele Tage im Zeitraum (>' . 10000 . '). Bitte Zeitraum splitten.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }

        // Deltas pro Tag berechnen
        $deltasProTag = [];
        $gesamtMinuten = null;
        $minutenProTag = null;

        if ($modus === 'minuten_pro_tag') {
            $minutenProTag = $deltaMinuten;
            $gesamtMinuten = $minutenProTag * count($tage);

            foreach ($tage as $dt) {
                $deltasProTag[] = $minutenProTag;
            }
        } else {
            // gesamt_gleichmaessig
            $gesamtMinuten = $deltaMinuten;

            $n = count($tage);
            $basis = intdiv($gesamtMinuten, $n);
            $rest = $gesamtMinuten - ($basis * $n);

            $restAbs = abs($rest);
            $restSign = $rest < 0 ? -1 : 1;

            for ($i = 0; $i < $n; $i++) {
                $d = $basis;
                if ($restAbs > 0 && $i < $restAbs) {
                    $d += $restSign;
                }
                $deltasProTag[] = $d;
            }
        }

        // Persistieren (Batch + auto-Korrekturen)
        try {
            $db = Database::getInstanz();
            $pdo = $db->getPdo();
            $pdo->beginTransaction();

            $db->ausfuehren(
                'INSERT INTO stundenkonto_batch (mitarbeiter_id, modus, von_datum, bis_datum, gesamt_minuten, minuten_pro_tag, nur_arbeitstage, begruendung, erstellt_von_mitarbeiter_id)
                 VALUES (:mid, :modus, :von, :bis, :gesamt, :mpt, :nur, :begr, :vonmid)',
                [
                    'mid'   => $mid,
                    'modus' => $modus,
                    'von'   => $von,
                    'bis'   => $bis,
                    'gesamt'=> $gesamtMinuten,
                    'mpt'   => $minutenProTag,
                    'nur'   => $nurArbeitstage ? 1 : 0,
                    'begr'  => $begruendung,
                    'vonmid'=> $erstelltVon,
                ]
            );

            $batchId = (int)$db->letzteInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO stundenkonto_korrektur (mitarbeiter_id, wirksam_datum, delta_minuten, typ, batch_id, begruendung, erstellt_von_mitarbeiter_id)
                 VALUES (:mid, :datum, :delta, \'verteilung\', :bid, :begr, :von)'
            );

            foreach ($tage as $idx => $dt) {
                $stmt->execute([
                    ':mid'   => $mid,
                    ':datum' => $dt->format('Y-m-d'),
                    ':delta' => (int)$deltasProTag[$idx],
                    ':bid'   => $batchId,
                    ':begr'  => $begruendung,
                    ':von'   => $erstelltVon,
                ]);
            }

            $pdo->commit();

            $sign = $gesamtMinuten < 0 ? '-' : '+';
            $abs = abs((int)$gesamtMinuten);
            $h = intdiv($abs, 60);
            $m = $abs % 60;
            $sumTxt = sprintf('%s%d:%02d', $sign, $h, $m);

            if (class_exists('Logger')) {
                Logger::info('Stundenkonto-Verteilbuchung gebucht', [
                    'batch_id'        => $batchId,
                    'mitarbeiter_id'  => $mid,
                    'modus'           => $modus,
                    'von_datum'       => $von,
                    'bis_datum'       => $bis,
                    'nur_arbeitstage' => $nurArbeitstage,
                    'tage_anzahl'     => count($tage),
                    'gesamt_minuten'  => (int)$gesamtMinuten,
                    'minuten_pro_tag' => $minutenProTag,
                    'summe_text'      => $sumTxt,
                    'begruendung'     => $begruendung,
                    'erstellt_von'    => $erstelltVon,
                    'typ'             => 'verteilung',
                ], $mid, null, 'stundenkonto');
            }

            $_SESSION['mitarbeiter_admin_flash_success'] = 'Verteilbuchung gespeichert (Batch #' . $batchId . '): ' . count($tage) . ' Tage, Summe ' . $sumTxt . '.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        } catch (\Throwable $e) {
            try {
                $db = Database::getInstanz();
                $pdo = $db->getPdo();
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Throwable) {
                // ignore
            }

            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern der Stundenkonto-Verteilbuchung (Batch)', [
                    'mitarbeiter_id' => $mid,
                    'modus'          => $modus,
                    'von_datum'      => $von,
                    'bis_datum'      => $bis,
                    'delta_minuten'  => $deltaMinuten,
                    'nur_arbeitstage'=> $nurArbeitstage,
                    'begruendung'    => $begruendung,
                    'exception'      => $e->getMessage(),
                ], $mid, null, 'stundenkonto');
            }

            $_SESSION['mitarbeiter_admin_flash_error'] = 'Verteilbuchung konnte nicht gespeichert werden. Bitte Admin informieren.';
            header('Location: ?seite=mitarbeiter_admin_bearbeiten&id=' . $mid);
            exit;
        }
    }

}
