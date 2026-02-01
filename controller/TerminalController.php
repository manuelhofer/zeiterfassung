<?php
declare(strict_types=1);

/**
 * TerminalController
 *
 * Steuert die Terminal-UI (Anmeldung, Kommen/Gehen, Auftragsstart/-stopp).
 *
 * Hinweis:
 * - Eine echte RFID-Hardware-Anbindung erfolgt später; aktuell wird der Code
 *   über ein Eingabefeld erfasst (der Reader "tippt" den Code + Enter).
 * - Nach erfolgreicher Anmeldung wird die Mitarbeiter-ID in der Session
 *   (`terminal_mitarbeiter_id`) gehalten.
 */
class TerminalController
{
    private AuftragszeitService $auftragszeitService;
    private ZeitService $zeitService;
    private Database $datenbank;

    /**
     * Sehr einfaches CSRF-Token (nur für Terminal-POSTs).
     *
     * Hinweis:
     * - Das Terminal ist meist ein internes Kiosk-System.
     * - Trotzdem vermeiden wir damit versehentliche/unerwünschte POSTs.
     */
    private function holeOderErzeugeCsrfToken(): string
    {
        $token = $_SESSION['terminal_csrf_token'] ?? '';
        if (is_string($token) && strlen($token) >= 20) {
            return $token;
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            // Fallback, sollte in der Praxis nie nötig sein
            $token = bin2hex(pack('N', time())) . bin2hex(pack('N', random_int(1, PHP_INT_MAX)));
        }

        $_SESSION['terminal_csrf_token'] = $token;
        return $token;
    }

    private function istCsrfTokenGueltigAusPost(): bool
    {
        $post = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $sess = $_SESSION['terminal_csrf_token'] ?? '';

        if (!is_string($sess) || $sess === '' || !is_string($post) || $post === '') {
            return false;
        }

        return hash_equals($sess, $post);
    }

    /**
     * T-069: De-Bounce gegen Doppelbuchungen am Terminal (Doppelklick / Doppel-Scan).
     *
     * Hintergrund:
     * - In der Praxis kommt es vor, dass ein Button doppelt geklickt wird oder der Scanner
     *   den Code zweimal sendet.
     * - Ohne Schutz entstehen dann zwei identische Buchungen (z. B. zwei mal "Kommen").
     *
     * Lösung (bewusst leichtgewichtig):
     * - Wir merken uns die letzte Terminal-Buchung (Typ + Uhrzeit + Timestamp) in der Session
     *   und ignorieren die gleiche Aktion innerhalb eines kurzen Fensters.
     * - Das ist kein Ersatz für saubere Workflow-Validierung, verhindert aber die häufigste
     *   Fehlerquelle im Terminal-Alltag.
     *
     * @return string|null Uhrzeit (H:i:s) der letzten Buchung, wenn Duplikat erkannt wurde
     */
    private function pruefeTerminalDoppelteBuchung(string $typ, int $fensterSekunden = 5, ?int $mitarbeiterId = null, ?string $rfidCode = null): ?string
    {
        $typ = strtolower(trim($typ));
        if ($typ !== 'kommen' && $typ !== 'gehen') {
            return null;
        }

        $fensterSekunden = max(1, min(60, (int)$fensterSekunden));

        $last = $_SESSION['terminal_last_buchung'] ?? null;
        if (!is_array($last)) {
            return null;
        }

        $lastTyp = isset($last['typ']) ? (string)$last['typ'] : '';
        $lastTs  = $last['ts'] ?? null;
        $lastZeit = isset($last['uhrzeit']) ? (string)$last['uhrzeit'] : '';
        $lastDatum = isset($last['datum']) ? (string)$last['datum'] : '';
        $lastMitarbeiterId = null;
        if (array_key_exists('mitarbeiter_id', $last) && $last['mitarbeiter_id'] !== null && $last['mitarbeiter_id'] !== '') {
            $tmp = $last['mitarbeiter_id'];
            if (is_int($tmp) || (is_string($tmp) && ctype_digit($tmp))) {
                $lastMitarbeiterId = (int)$tmp;
            }
        }

        $lastRfidCode = '';
        if (array_key_exists('rfid_code', $last) && $last['rfid_code'] !== null) {
            $lastRfidCode = trim((string)$last['rfid_code']);
        }

        $mitarbeiterId = ($mitarbeiterId !== null && $mitarbeiterId > 0) ? (int)$mitarbeiterId : null;
        $rfidCode = ($rfidCode !== null) ? trim((string)$rfidCode) : null;


        if ($lastTyp !== $typ) {
            return null;
        }

        // De-Bounce nur fuer dieselbe Person (online: Mitarbeiter-ID, offline: RFID-Code),
        // damit mehrere Mitarbeiter kurz hintereinander nicht faelschlich blockiert werden.
        if ($mitarbeiterId !== null) {
            if ($lastMitarbeiterId === null || $lastMitarbeiterId !== $mitarbeiterId) {
                return null;
            }
        }
        if ($rfidCode !== null && $rfidCode !== '') {
            if ($lastRfidCode === '' || $lastRfidCode !== $rfidCode) {
                return null;
            }
        }

        // Nur innerhalb desselben Kalendertags duplizieren (sonst z. B. Nachtschicht-Probleme).
        $heute = (new \DateTimeImmutable('today'))->format('Y-m-d');
        if ($lastDatum !== '' && $lastDatum !== $heute) {
            return null;
        }

        if (is_int($lastTs)) {
            if ((time() - $lastTs) >= 0 && (time() - $lastTs) < $fensterSekunden) {
                return ($lastZeit !== '' ? $lastZeit : null);
            }
        } elseif (is_string($lastTs) && ctype_digit($lastTs)) {
            $lastInt = (int)$lastTs;
            if ((time() - $lastInt) >= 0 && (time() - $lastInt) < $fensterSekunden) {
                return ($lastZeit !== '' ? $lastZeit : null);
            }
        }

        return null;
    }

    /**
     * Maschine-ID aus Scan-String extrahieren.
     *
     * Praxis:
     * - Im Normalfall wird nur die reine Zahl gescannt (z. B. "12").
     * - Zur Robustheit tolerieren wir aber auch Prefix/Suffix (z. B. "M12", "MASCHINE-12").
     */
    private function parseMaschineIdAusScan(?string $raw): ?int
    {
        $raw = $raw === null ? '' : trim((string)$raw);
        if ($raw === '') {
            return null;
        }

        // Reine Zahl
        if (ctype_digit($raw)) {
            $id = (int)$raw;
            return ($id > 0) ? $id : null;
        }

        // Erste Zifferngruppe aus gemischtem String
        if (preg_match('/(\d+)/', $raw, $m)) {
            $id = (int)$m[1];
            return ($id > 0) ? $id : null;
        }

        return null;
    }

    private function merkeTerminalLetzteBuchung(string $typ, \DateTimeImmutable $zeitpunkt, ?int $mitarbeiterId = null, ?string $rfidCode = null): void
    {
        $typ = strtolower(trim($typ));
        if ($typ !== 'kommen' && $typ !== 'gehen') {
            return;
        }

        
        $mitarbeiterId = ($mitarbeiterId !== null && $mitarbeiterId > 0) ? (int)$mitarbeiterId : null;
        $rfidCode = ($rfidCode !== null) ? trim((string)$rfidCode) : null;

        $_SESSION['terminal_last_buchung'] = [
            'typ' => $typ,
            'ts' => time(),
            'uhrzeit' => $zeitpunkt->format('H:i:s'),
            'datum' => $zeitpunkt->format('Y-m-d'),
            'mitarbeiter_id' => $mitarbeiterId,
            'rfid_code' => ($rfidCode !== null ? $rfidCode : ''),
        ];
    }

    /**
     * Liefert die letzten Urlaubsanträge des Mitarbeiters (Terminal-Übersicht).
     *
     * Wichtig:
     * - Nur online verwenden (Hauptdatenbank erreichbar), da die Tabelle nicht
     *   in die Offline-Queue geschrieben wird.
     *
     * @return array<int,array<string,mixed>>
     */
    private function holeEigeneUrlaubsantraege(int $mitarbeiterId, int $limit = 10): array
    {
        if ($mitarbeiterId <= 0) {
            return [];
        }

        $limit = max(1, min(50, (int)$limit));

        try {
            $sql = 'SELECT id, von_datum, bis_datum, tage_gesamt, status, antrags_datum
                    FROM urlaubsantrag
                    WHERE mitarbeiter_id = :mid
                    ORDER BY antrags_datum DESC, id DESC
                    LIMIT ' . $limit;

            return $this->datenbank->fetchAlle($sql, ['mid' => $mitarbeiterId]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Terminal: Urlaubsanträge konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal_urlaub');
            }
            return [];
        }
    }

    /**
     * Liest einen Integer-Wert aus der Tabelle `config`.
     *
     * WICHTIG: Am Terminal darf ein fehlender/defekter Config-Eintrag niemals
     * zum Absturz führen – daher defensiv mit Default/Fallback.
     */
    private function ladeConfigInt(string $schluessel, int $default, int $min = 1, int $max = 86400): int
    {
        $schluessel = trim($schluessel);
        if ($schluessel === '') {
            return $default;
        }

        try {
            $row = $this->datenbank->fetchEine(
                'SELECT wert FROM config WHERE schluessel = :k LIMIT 1',
                ['k' => $schluessel]
            );
        } catch (Throwable $e) {
            return $default;
        }

        if (!is_array($row) || !array_key_exists('wert', $row)) {
            return $default;
        }

        $wert = $row['wert'];
        if ($wert === null) {
            return $default;
        }

        if (is_int($wert)) {
            $intVal = $wert;
        } else {
            $intVal = (int)trim((string)$wert);
        }

        if ($intVal < $min || $intVal > $max) {
            return $default;
        }

        return $intVal;
    }

    /**
     * Terminal-Timeouts laut Master-Prompt (Auto-Logout). Werte kommen aus `config`.
     *
     * Erwartete Keys:
     * - terminal_timeout_standard (Sekunden, Default 60)
     * - terminal_timeout_urlaub (Sekunden, Default 180)
     */
    private function holeTerminalTimeoutSekunden(string $kontext = 'standard'): int
    {
        $kontext = strtolower(trim($kontext));

        if ($kontext === 'urlaub') {
            return $this->ladeConfigInt('terminal_timeout_urlaub', 180, 30, 3600);
        }

        return $this->ladeConfigInt('terminal_timeout_standard', 60, 10, 1800);
    }

    /**
     * Lädt die Zeitbuchungen für "heute" (lokale Zeitzone) für die Terminal-Übersicht.
     *
     * @return array{datum:string,buchungen:array<int,array<string,mixed>>,fehler:?string}
     */
    private function holeHeutigeZeitUebersicht(int $mitarbeiterId, bool $zeigeMicroBuchungen = false): array
    {
        $heute = new DateTimeImmutable('today');

        try {
            $daten = $this->zeitService->holeTagesdaten($mitarbeiterId, $heute);

            $datum = isset($daten['datum']) && is_string($daten['datum']) && $daten['datum'] !== ''
                ? $daten['datum']
                : $heute->format('Y-m-d');

            $buchungen = [];
            if (isset($daten['buchungen']) && is_array($daten['buchungen'])) {
                /** @var array<int,array<string,mixed>> $buchungen */
                $buchungen = $daten['buchungen'];
            }

            // Mikro-Buchungen (Kommen/Gehen innerhalb weniger Sekunden/Minuten)
            // sind am Terminal meistens nur ein Versehen (Doppelklick / Irrtum)
            // und sollen daher standardmäßig nicht in der Übersicht erscheinen.
            // Für Debug kann man sie über ?show_micro=1 einblenden.
            if (!$zeigeMicroBuchungen && is_array($buchungen) && count($buchungen) >= 2) {
                $res = $this->filtereMicroZeitbuchungenAusListe($buchungen, 180);
                $buchungen = $res['buchungen'];
            }

            return [
                'datum'     => $datum,
                'buchungen' => $buchungen,
                'fehler'    => null,
            ];
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Zeitübersicht (heute) konnte am Terminal nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal');
            }

            return [
                'datum'     => $heute->format('Y-m-d'),
                'buchungen' => [],
                'fehler'    => 'Zeitdaten konnten nicht geladen werden.',
            ];
        }
    }


    /**
     * Filtert Mikro-Buchungen (Kommen/Gehen innerhalb weniger Sekunden/Minuten)
     * aus einer Liste.
     *
     * Hintergrund:
     * - Bei Touch-Terminals passieren manchmal Fehleingaben (z. B. erst "Gehen" gedrückt,
     *   dann sofort "Kommen" oder umgekehrt).
     * - Diese Mini-Paare verfälschen Listen/Statistiken und sorgen für Verwirrung.
     */
    private function filtereMicroZeitbuchungenAusListe(array $buchungen, int $maxSekunden = 180): array
    {
        $out = [];
        $hidden = 0;

        $n = count($buchungen);
        $i = 0;

        while ($i < $n) {
            $b1 = $buchungen[$i] ?? null;

            if (is_array($b1) && ($i + 1) < $n) {
                $b2 = $buchungen[$i + 1] ?? null;

                if (is_array($b2)) {
                    $t1 = strtolower((string)($b1['typ'] ?? ''));
                    $t2 = strtolower((string)($b2['typ'] ?? ''));

                    if (($t1 === 'kommen' || $t1 === 'gehen')
                        && ($t2 === 'kommen' || $t2 === 'gehen')
                        && $t1 !== $t2) {
                        $dt1 = $this->tryParseZeitbuchungTs((string)($b1['zeitstempel'] ?? ''));
                        $dt2 = $this->tryParseZeitbuchungTs((string)($b2['zeitstempel'] ?? ''));

                        if ($dt1 instanceof DateTimeImmutable && $dt2 instanceof DateTimeImmutable) {
                            $delta = abs($dt2->getTimestamp() - $dt1->getTimestamp());
                            if ($delta <= max(1, $maxSekunden)) {
                                // Mikro-Paar: beide Stempel ausblenden
                                $hidden += 2;
                                $i += 2;
                                continue;
                            }
                        }
                    }
                }
            }

            if (is_array($b1)) {
                $out[] = $b1;
            }
            $i++;
        }

        return [
            'buchungen' => $out,
            'ausgeblendet' => $hidden,
        ];
    }

    private function tryParseZeitbuchungTs(string $ts): ?DateTimeImmutable
    {
        $ts = trim($ts);
        if ($ts === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($ts);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Urlaub am Terminal darf nur erfolgen, wenn die Hauptdatenbank erreichbar ist.
     */
    private function istHauptdatenbankAktiv(): bool
    {
        $status = $_SESSION['terminal_queue_status'] ?? null;
        if (is_array($status) && array_key_exists('hauptdb_verfuegbar', $status)) {
            if ($status['hauptdb_verfuegbar'] === true) {
                return true;
            }
            if ($status['hauptdb_verfuegbar'] === false) {
                return false;
            }
        }

        // Fallback: direkt prüfen (falls Status unbekannt ist)
        try {
            if (method_exists($this->datenbank, 'istHauptdatenbankVerfuegbar')) {
                return (bool)$this->datenbank->istHauptdatenbankVerfuegbar();
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Terminal-Rechteprüfung (nur für Terminal-Admin-Funktionen).
     *
     * WICHTIG:
     * - Im Offline-Modus wird **immer false** zurückgegeben (Sicherheit & keine DB-Queries).
     * - Es wird das bestehende Rollen/Rechte-System verwendet:
     *   - Superuser-Rollen erlauben alles
     *   - Rechte aus Rollen
     *   - Mitarbeiter-Rechte-Overrides (erlaubt=0/1) überschreiben Rollen
     */
    private function terminalMitarbeiterHatRecht(int $mitarbeiterId, string $rechtCode): bool
    {
        $rechtCode = trim($rechtCode);

        if ($mitarbeiterId <= 0 || $rechtCode === '') {
            return false;
        }

        // Offline: keine Adminfunktionen.
        if (!$this->istHauptdatenbankAktiv()) {
            return false;
        }

        try {
            // Superuser?
            $sqlSuperuser = "
                SELECT 1
                FROM (
                    SELECT rolle_id
                    FROM mitarbeiter_hat_rolle
                    WHERE mitarbeiter_id = :mid

                    UNION

                    SELECT rolle_id
                    FROM mitarbeiter_hat_rolle_scope
                    WHERE mitarbeiter_id = :mid2
                      AND scope_typ = 'global'
                ) x
                JOIN rolle ro ON ro.id = x.rolle_id
                WHERE ro.aktiv = 1
                  AND ro.ist_superuser = 1
                LIMIT 1
            ";

            $su = $this->datenbank->fetchEine($sqlSuperuser, ['mid' => $mitarbeiterId, 'mid2' => $mitarbeiterId]);
            if (is_array($su) && !empty($su)) {
                return true;
            }

            // Mitarbeiter-Override?
            $sqlOverride = "
                SELECT mhr.erlaubt
                FROM mitarbeiter_hat_recht mhr
                JOIN recht r ON r.id = mhr.recht_id
                WHERE mhr.mitarbeiter_id = :mid
                  AND r.code = :code
                  AND r.aktiv = 1
                LIMIT 1
            ";

            $ov = $this->datenbank->fetchEine($sqlOverride, ['mid' => $mitarbeiterId, 'code' => $rechtCode]);
            if (is_array($ov) && array_key_exists('erlaubt', $ov)) {
                return ((int)$ov['erlaubt'] === 1);
            }

            // Rechte aus Rollen.
            $sqlRolleRecht = "
                SELECT 1
                FROM (
                    SELECT rolle_id
                    FROM mitarbeiter_hat_rolle
                    WHERE mitarbeiter_id = :mid

                    UNION

                    SELECT rolle_id
                    FROM mitarbeiter_hat_rolle_scope
                    WHERE mitarbeiter_id = :mid2
                      AND scope_typ = 'global'
                ) x
                JOIN rolle_hat_recht rhr ON rhr.rolle_id = x.rolle_id
                JOIN recht r ON r.id = rhr.recht_id
                WHERE r.code = :code
                  AND r.aktiv = 1
                LIMIT 1
            ";

            $rr = $this->datenbank->fetchEine($sqlRolleRecht, ['mid' => $mitarbeiterId, 'mid2' => $mitarbeiterId, 'code' => $rechtCode]);
            return (is_array($rr) && !empty($rr));
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Terminal-Rechteprüfung fehlgeschlagen', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'recht'          => $rechtCode,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal');
            }
            return false;
        }
    }


    /**
     * Offline-Fallback: Wir merken uns den Anwesenheitsstatus in der Session,
     * damit das Terminal im Offline-Modus Kommen/Gehen korrekt ein-/ausblenden kann.
     */
    private function setzeTerminalAnwesenheitStatus(bool $istAnwesend): void
    {
        $_SESSION['terminal_anwesend'] = $istAnwesend ? 1 : 0;
        $_SESSION['terminal_anwesend_zeit'] = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    /**
     * Ermittelt, ob der Mitarbeiter heute am Terminal als anwesend gilt.
     *
     * Regel:
     * - Online: heute mehr "kommen" als "gehen".
     * - Offline: Session-Fallback `terminal_anwesend`.
     */
    private function istTerminalMitarbeiterHeuteAnwesend(int $mitarbeiterId): bool
    {
        if ($mitarbeiterId <= 0) {
            return false;
        }

        // Offline/Unklar: Session-Fallback.
        if (!$this->istHauptdatenbankAktiv()) {
            $v = $_SESSION['terminal_anwesend'] ?? false;
            if ($v === 1 || $v === '1') {
                return true;
            }
            if ($v === 0 || $v === '0') {
                return false;
            }
            return (bool)$v;
        }

        try {
            $heute = new DateTimeImmutable('today');
            $daten = $this->zeitService->holeTagesdaten($mitarbeiterId, $heute);

            $buchungen = [];
            if (is_array($daten) && isset($daten['buchungen']) && is_array($daten['buchungen'])) {
                $buchungen = $daten['buchungen'];
            }

            $kommen = 0;
            $gehen  = 0;

            foreach ($buchungen as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $typ = isset($b['typ']) ? (string)$b['typ'] : '';
                if ($typ === 'kommen') {
                    $kommen++;
                } elseif ($typ === 'gehen') {
                    $gehen++;
                }
            }

            $anwesend = ($kommen > $gehen);

            // Session-Fallback synchron halten (für mögliche spätere Offline-Phasen).
            $this->setzeTerminalAnwesenheitStatus($anwesend);

            return $anwesend;
        } catch (Throwable $e) {
            $v = $_SESSION['terminal_anwesend'] ?? false;
            return (bool)$v;
        }
    }


    /**
     * Live-Iststunden (heute) bis jetzt aus Rohbuchungen.
     *
     * Hinweis: im offenen Arbeitstag sind Pausen/Rundungen ggf. noch nicht final.
     */
    private function berechneIstStundenHeuteBisJetzt(int $mitarbeiterId, \DateTimeImmutable $now): float
    {
        try {
            $heute = new DateTimeImmutable('today');
            $daten = $this->zeitService->holeTagesdaten($mitarbeiterId, $heute);
            $buchungen = [];
            if (is_array($daten) && isset($daten['buchungen']) && is_array($daten['buchungen'])) {
                $buchungen = $daten['buchungen'];
            }

            // Sortieren nach Zeitstempel (defensiv).
            usort($buchungen, static function ($a, $b): int {
                $ta = is_array($a) ? (string)($a['zeitstempel'] ?? '') : '';
                $tb = is_array($b) ? (string)($b['zeitstempel'] ?? '') : '';
                return strcmp($ta, $tb);
            });

            $blockStart = null; // DateTimeImmutable|null
            $sek = 0;

            foreach ($buchungen as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $typ = isset($b['typ']) ? (string)$b['typ'] : '';
                $ts  = isset($b['zeitstempel']) ? (string)$b['zeitstempel'] : '';
                if ($ts === '') {
                    continue;
                }
                try {
                    $dt = new DateTimeImmutable($ts);
                } catch (Throwable $e) {
                    continue;
                }

                if ($typ === 'kommen') {
                    if ($blockStart === null) {
                        $blockStart = $dt;
                    }
                } elseif ($typ === 'gehen') {
                    if ($blockStart !== null) {
                        $diff = $dt->getTimestamp() - $blockStart->getTimestamp();
                        if ($diff > 0) {
                            $sek += $diff;
                        }
                        $blockStart = null;
                    }
                }
            }

            // Offener Block bis jetzt.
            if ($blockStart !== null) {
                $diff = $now->getTimestamp() - $blockStart->getTimestamp();
                if ($diff > 0) {
                    $sek += $diff;
                }
            }

            $h = $sek / 3600.0;
            if ($h < 0) {
                $h = 0.0;
            }
            return $h;
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Monatsstatus fuer das Mitarbeiterpanel.
     * - Soll Monat gesamt
     * - Soll bis heute
     * - Ist bis heute (inkl. laufendem heutigen Arbeitstag)
     */
    private function berechneMonatsStatusFuerMitarbeiter(int $mitarbeiterId, ?int $jahr = null, ?int $monat = null): ?array
    {
        if ($mitarbeiterId <= 0) {
            return null;
        }
        if (!$this->istHauptdatenbankAktiv()) {
            return null;
        }

        $now = new DateTimeImmutable('now');
        $jahrInput = $jahr ?? (isset($_GET['jahr']) ? (int)$_GET['jahr'] : 0);
        $monatInput = $monat ?? (isset($_GET['monat']) ? (int)$_GET['monat'] : 0);

        $jahr = ($jahrInput >= 2000 && $jahrInput <= 2100) ? $jahrInput : (int)$now->format('Y');
        $monat = ($monatInput >= 1 && $monatInput <= 12) ? $monatInput : (int)$now->format('n');

        $heuteStr = $now->format('Y-m-d');
        $aktuellesJahr = (int)$now->format('Y');
        $aktuellerMonat = (int)$now->format('n');

        if ($jahr < $aktuellesJahr || ($jahr === $aktuellesJahr && $monat < $aktuellerMonat)) {
            $heuteStr = (new DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat)))
                ->modify('last day of this month')
                ->format('Y-m-d');
        }

        try {
            $parseStundenZuMinuten = static function ($wert): int {
                $s = trim((string)$wert);
                if ($s === '') {
                    return 0;
                }
                $s = str_replace(',', '.', $s);
                if (!is_numeric($s)) {
                    return 0;
                }
                $stunden = (float)$s;
                return (int)round($stunden * 60.0);
            };

            $formatStunden = static function (float $stunden, bool $mitVorzeichen = false): string {
                $text = $mitVorzeichen ? sprintf('%+.2f', $stunden) : sprintf('%.2f', $stunden);
                return str_replace('.', ',', $text);
            };

            $formatMinutenAlsStunden = static function (int $minuten, bool $mitVorzeichen = false): string {
                $stunden = $minuten / 60.0;
                $text = $mitVorzeichen ? sprintf('%+.2f', $stunden) : sprintf('%.2f', $stunden);
                return str_replace('.', ',', $text);
            };

            $sumIstMinuten = 0;
            $sumIstMinutenBisHeute = 0;
            $sumArztMinuten = 0;
            $sumKrankLfzMinuten = 0;
            $sumKrankKkMinuten = 0;
            $sumUrlaubMinuten = 0;
            $sumFeiertagMinuten = 0;
            $sumKurzarbeitMinuten = 0;
            $sumSonstMinuten = 0;
            $sollMinuten = 0;
            $stundenkontoSaldoText = '';
            $zusammenfassungAusReport = null;
            $sollBisHeuteMinutenAusTagen = 0;
            $hatSollAusTagen = false;

            if (class_exists('ReportService')) {
                try {
                    $reportService = new ReportService();
                    $monatsdaten = $reportService->holeMonatsdatenFuerMitarbeiter($mitarbeiterId, $jahr, $monat);
                    $tageswerte = $monatsdaten['tageswerte'] ?? [];
                    $zusammenfassungAusReport = $monatsdaten['monatszusammenfassung'] ?? null;

                    if (is_array($tageswerte)) {
                        foreach ($tageswerte as $t) {
                            if (!is_array($t)) {
                                continue;
                            }

                            $sumArztMinuten += $parseStundenZuMinuten($t['arzt_stunden'] ?? '0');
                            $sumKrankLfzMinuten += $parseStundenZuMinuten($t['krank_lfz_stunden'] ?? '0');
                            $sumKrankKkMinuten += $parseStundenZuMinuten($t['krank_kk_stunden'] ?? '0');
                            $sumUrlaubMinuten += $parseStundenZuMinuten($t['urlaub_stunden'] ?? '0');
                            $sumFeiertagMinuten += $parseStundenZuMinuten($t['feiertag_stunden'] ?? '0');
                            $sumKurzarbeitMinuten += $parseStundenZuMinuten($t['kurzarbeit_stunden'] ?? '0');
                            $sumSonstMinuten += $parseStundenZuMinuten($t['sonstige_stunden'] ?? '0');

                            $datum = (string)($t['datum'] ?? '');
                            $istMinutenTagFuerAnzeige = $parseStundenZuMinuten($t['arbeitszeit_stunden'] ?? '0')
                                + $parseStundenZuMinuten($t['arzt_stunden'] ?? '0')
                                + $parseStundenZuMinuten($t['krank_lfz_stunden'] ?? '0')
                                + $parseStundenZuMinuten($t['krank_kk_stunden'] ?? '0')
                                + $parseStundenZuMinuten($t['urlaub_stunden'] ?? '0')
                                + $parseStundenZuMinuten($t['feiertag_stunden'] ?? '0')
                                + $parseStundenZuMinuten($t['sonstige_stunden'] ?? '0');

                            if ($datum !== '' && $datum <= $heuteStr) {
                                $sumIstMinutenBisHeute += $istMinutenTagFuerAnzeige;

                                $sollFeldKandidaten = ['soll_stunden', 'tagessoll', 'soll'];
                                foreach ($sollFeldKandidaten as $feld) {
                                    if (array_key_exists($feld, $t)) {
                                        $hatSollAusTagen = true;
                                        $sollBisHeuteMinutenAusTagen += $parseStundenZuMinuten($t[$feld]);
                                        break;
                                    }
                                }
                            }

                            if (!empty($t['micro_arbeitszeit_ignoriert'])) {
                                continue;
                            }

                            $bloecke = [];
                            if (isset($t['arbeitsbloecke']) && is_array($t['arbeitsbloecke'])) {
                                $bloecke = $t['arbeitsbloecke'];
                            }

                            $istMinutenTag = 0;
                            $hatBlockIst = false;

                            foreach ($bloecke as $b) {
                                if (!is_array($b)) {
                                    continue;
                                }

                                $kStr = (string)($b['kommen_korr'] ?? $b['kommen_roh'] ?? '');
                                $gStr = (string)($b['gehen_korr'] ?? $b['gehen_roh'] ?? '');
                                if ($kStr !== '' && $gStr !== '') {
                                    try {
                                        $k = new DateTimeImmutable($kStr);
                                        $g = new DateTimeImmutable($gStr);
                                        if ($g > $k) {
                                            $durSek = $g->getTimestamp() - $k->getTimestamp();
                                            $durStd = $durSek / 3600.0;
                                            if ($durStd < 0.05) {
                                                continue;
                                            }
                                        }
                                    } catch (Throwable $e) {
                                        // Ignorieren, falls Blockzeiten nicht gelesen werden koennen.
                                    }
                                }

                                $minuten = $parseStundenZuMinuten($b['ist_stunden'] ?? '0');
                                if ($minuten <= 0) {
                                    continue;
                                }

                                $hatBlockIst = true;
                                $istMinutenTag += $minuten;
                            }

                            if (!$hatBlockIst) {
                                $istMinutenTag = $parseStundenZuMinuten($t['arbeitszeit_stunden'] ?? '0');
                            }

                            if ($istMinutenTag > 0) {
                                $sumIstMinuten += $istMinutenTag;
                            }

                        }
                    }

                    if (isset($monatsdaten['monatswerte']) && is_array($monatsdaten['monatswerte'])) {
                        $sollMinuten = $parseStundenZuMinuten($monatsdaten['monatswerte']['sollstunden'] ?? '0');
                    }
                } catch (Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::warn('Terminal: Monatsstatus via ReportService fehlgeschlagen', [
                            'mitarbeiter_id' => $mitarbeiterId,
                            'jahr' => $jahr,
                            'monat' => $monat,
                            'exception' => $e->getMessage(),
                        ], $mitarbeiterId, null, 'terminal_monatsstatus');
                    }
                }
            }

            if (class_exists('StundenkontoService')) {
                try {
                    $stundenkontoService = StundenkontoService::getInstanz();
                    $saldoMinuten = $stundenkontoService->holeSaldoMinutenBisVormonat($mitarbeiterId, $jahr, $monat);
                    $stundenkontoSaldoText = str_replace('.', ',', $stundenkontoService->formatMinutenAlsStundenString((int)$saldoMinuten, true));
                } catch (Throwable $e) {
                    $stundenkontoSaldoText = '';
                }
            }

            $istBisherMinuten = $sumIstMinutenBisHeute;

            // Soll-Werte aus Wochenarbeitszeit ableiten (Mo-Fr).
            $wochenarbeitszeit = 0.0;
            try {
                $r = $this->datenbank->fetchEine(
                    'SELECT wochenarbeitszeit FROM mitarbeiter WHERE id = :id LIMIT 1',
                    ['id' => $mitarbeiterId]
                );
                if (is_array($r) && isset($r['wochenarbeitszeit'])) {
                    $wochenarbeitszeit = (float)str_replace(',', '.', (string)$r['wochenarbeitszeit']);
                }
            } catch (Throwable $e) {
                $wochenarbeitszeit = 0.0;
            }

            $tagesSoll = ($wochenarbeitszeit > 0.0) ? ($wochenarbeitszeit / 5.0) : 0.0;
            $sollBisHeute = 0.0;
            $sollMonatGesamt = 0.0;

            if ($tagesSoll > 0.0) {
                $monatStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat));
                $monatEnd = $monatStart->modify('last day of this month');
                $end = new DateTimeImmutable($heuteStr);

                $d = $monatStart;
                while ($d <= $end) {
                    $wochentag = (int)$d->format('N');
                    if ($wochentag <= 5) {
                        $sollBisHeute += $tagesSoll;
                    }
                    $d = $d->modify('+1 day');
                }

                $d = $monatStart;
                while ($d <= $monatEnd) {
                    $wochentag = (int)$d->format('N');
                    if ($wochentag <= 5) {
                        $sollMonatGesamt += $tagesSoll;
                    }
                    $d = $d->modify('+1 day');
                }
            }

            $sollBisHeuteMinuten = $hatSollAusTagen
                ? $sollBisHeuteMinutenAusTagen
                : (int)round($sollBisHeute * 60.0);
            $sollMonatGesamtMinuten = (int)round($sollMonatGesamt * 60.0);

            // Extras fuer Startscreen-Info.
            $restBisMonatsendeMinuten = $sollMonatGesamtMinuten - $istBisherMinuten;
            $saldoBisHeuteMinuten = $istBisherMinuten - $sollBisHeuteMinuten;
            $saldoLabel = $saldoBisHeuteMinuten >= 0 ? 'im Plan' : 'Rueckstand';
            $saldoAmpel = $saldoBisHeuteMinuten >= 0 ? 'ok' : 'error';

            $sumAllMinuten = $sumIstMinuten
                + $sumArztMinuten
                + $sumKrankLfzMinuten
                + $sumKrankKkMinuten
                + $sumUrlaubMinuten
                + $sumKurzarbeitMinuten
                + $sumFeiertagMinuten
                + $sumSonstMinuten;

            $diffMinuten = $sumAllMinuten - $sollMinuten;

            $formatStundenText = static function (?string $wert): string {
                if ($wert === null) {
                    return '0,00';
                }
                $text = str_replace(',', '.', trim($wert));
                if ($text === '' || !is_numeric($text)) {
                    return '0,00';
                }
                return str_replace('.', ',', sprintf('%.2f', (float)$text));
            };

            $zusammenfassung = [
                'ist' => $formatMinutenAlsStunden($sumIstMinuten),
                'arzt' => $formatMinutenAlsStunden($sumArztMinuten),
                'krank_lfz' => $formatMinutenAlsStunden($sumKrankLfzMinuten),
                'krank_kk' => $formatMinutenAlsStunden($sumKrankKkMinuten),
                'urlaub' => $formatMinutenAlsStunden($sumUrlaubMinuten),
                'feiertag' => $formatMinutenAlsStunden($sumFeiertagMinuten),
                'kurzarbeit' => $formatMinutenAlsStunden($sumKurzarbeitMinuten),
                'sonst' => $formatMinutenAlsStunden($sumSonstMinuten),
                'summen' => $formatMinutenAlsStunden($sumAllMinuten),
                'differenz' => $formatMinutenAlsStunden($diffMinuten),
                'stundenkonto' => $stundenkontoSaldoText,
            ];

            if (is_array($zusammenfassungAusReport)) {
                $stundenkontoAusReport = $zusammenfassungAusReport['stundenkonto_bis_vormonat'] ?? null;
                $stundenkontoText = $stundenkontoSaldoText;
                if ($stundenkontoAusReport !== null && trim((string)$stundenkontoAusReport) !== '') {
                    $stundenkontoText = str_replace('.', ',', (string)$stundenkontoAusReport);
                }
                $zusammenfassung = [
                    'ist' => $formatStundenText((string)($zusammenfassungAusReport['iststunden'] ?? null)),
                    'arzt' => $formatStundenText((string)($zusammenfassungAusReport['arzt'] ?? null)),
                    'krank_lfz' => $formatStundenText((string)($zusammenfassungAusReport['krank_lfz'] ?? null)),
                    'krank_kk' => $formatStundenText((string)($zusammenfassungAusReport['krank_kk'] ?? null)),
                    'urlaub' => $formatStundenText((string)($zusammenfassungAusReport['urlaub'] ?? null)),
                    'feiertag' => $formatStundenText((string)($zusammenfassungAusReport['feiertag'] ?? null)),
                    'kurzarbeit' => $formatStundenText((string)($zusammenfassungAusReport['kurzarbeit'] ?? null)),
                    'sonst' => $formatStundenText((string)($zusammenfassungAusReport['sonst'] ?? null)),
                    'summen' => $formatStundenText((string)($zusammenfassungAusReport['summen'] ?? null)),
                    'differenz' => $formatStundenText((string)($zusammenfassungAusReport['differenz'] ?? null)),
                    'stundenkonto' => $stundenkontoText,
                ];
            }

            return [
                'jahr'               => $jahr,
                'monat'              => $monat,
                'soll_monat_gesamt'  => $formatStunden($sollMonatGesamt),
                'soll_bis_heute'     => $formatMinutenAlsStunden($sollBisHeuteMinuten),
                'ist_bisher'         => $formatMinutenAlsStunden($istBisherMinuten),
                'rest_bis_monatsende'=> $formatMinutenAlsStunden($restBisMonatsendeMinuten),
                'saldo_bis_heute'    => $formatMinutenAlsStunden($saldoBisHeuteMinuten, true),
                'saldo_label'        => $saldoLabel,
                'saldo_ampel'        => $saldoAmpel,
                'zusammenfassung'    => $zusammenfassung,
            ];
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Terminal: Monatsstatus konnte nicht berechnet werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr' => $jahr,
                    'monat' => $monat,
                    'exception' => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal_monatsstatus');
            }

            return [
                'jahr'               => $jahr,
                'monat'              => $monat,
                'soll_monat_gesamt'  => '0,00',
                'soll_bis_heute'     => '0,00',
                'ist_bisher'         => '0,00',
                'rest_bis_monatsende'=> '0,00',
                'saldo_bis_heute'    => '0,00',
                'saldo_label'        => 'unbekannt',
                'saldo_ampel'        => 'error',
                'zusammenfassung'    => [
                    'ist' => '0,00',
                    'arzt' => '0,00',
                    'krank_lfz' => '0,00',
                    'krank_kk' => '0,00',
                    'urlaub' => '0,00',
                    'feiertag' => '0,00',
                    'kurzarbeit' => '0,00',
                    'sonst' => '0,00',
                    'summen' => '0,00',
                    'differenz' => '0,00',
                    'stundenkonto' => '',
                ],
            ];
        }
    }

    /**
     * Offline-Buchung per RFID (ohne vorherige Mitarbeiter-Identifikation).
     *
     * Vorgabe (Master-Prompt v9):
     * - Wenn die Hauptdatenbank offline ist, darf das Terminal weiterhin Kommen/Gehen annehmen.
     * - Es wird **nur** RFID + Zeitpunkt + Aktion gespeichert (in db_injektionsqueue).
     * - Die Auflösung des Mitarbeiters erfolgt erst beim späteren Einspielen in die Hauptdatenbank.
     *
     * @return int|null 0 bei Erfolg (Offline-Queue), null bei Fehler
     */
    private function bucheZeitOfflinePerRfid(string $typ, string $rfidCode, \DateTimeImmutable $zeitpunkt): ?int
    {
        $typ = trim($typ);
        if ($typ !== 'kommen' && $typ !== 'gehen') {
            $typ = 'kommen';
        }

        $rfidCode = trim((string)$rfidCode);
        if ($rfidCode === '') {
            return null;
        }

        // Defensive: sehr lange Codes begrenzen (Reader-/Copy-Paste-Fehler).
        if (strlen($rfidCode) > 128) {
            $rfidCode = substr($rfidCode, 0, 128);
        }

        // Haupt-DB ist offline → wir schreiben NUR in die Queue.
        // Beim Replay wird die Mitarbeiter-ID über die RFID aufgelöst.
        $zeitStr = $zeitpunkt->format('Y-m-d H:i:s');

        // SQL-Literal Escaping (minimal, aber ausreichend für Reader-Input)
        $q = static function (string $s): string {
            return "'" . str_replace("'", "''", $s) . "'";
        };

        // WICHTIG: Wir bauen die Mitarbeiter-Auflösung so, dass ein fehlender RFID
        // zu einem SQL-Fehler führt (damit die Queue im Fehlerfall stoppt und nicht
        // stillschweigend "0 Rows" verarbeitet).
        $sql = 'INSERT INTO zeitbuchung (mitarbeiter_id, typ, zeitstempel, quelle, manuell_geaendert, kommentar, terminal_id) VALUES ('
            . '(SELECT id FROM mitarbeiter WHERE rfid_code = ' . $q($rfidCode) . ' AND aktiv = 1 LIMIT 1), '
            . $q($typ) . ', '
            . $q($zeitStr) . ', '
            . $q('terminal') . ', '
            . '0, '
            . 'NULL, '
            . 'NULL'
            . ')';

        try {
            $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                $sql,
                null,
                null,
                'zeit_' . $typ . '_rfid'
            );

            return $ok ? 0 : null;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Terminal: Offline-Buchung per RFID in Queue fehlgeschlagen', [
                    'typ'       => $typ,
                    'rfid_code' => $rfidCode,
                    'exception' => $e->getMessage(),
                ], null, null, 'terminal_offline_rfid');
            }
            return null;
        }
    }

    /**
     * Ermittelt anhand der lokalen Offline-Queue die letzte bekannte Aktion fuer einen RFID-Code
     * und liefert einen Vorschlag, welcher Button wahrscheinlich korrekt ist.
     *
     * Hinweis:
     * - Wir werten nur Eintraege aus, die dieses Terminal erzeugt (meta_aktion zeit_*_rfid).
     * - Der RFID-Code steckt nur im SQL-Text; daher suchen wir per LIKE nach "rfid_code = '...'."
     *
     * @return array{letzte_typ:string, letzte_zeit:string, vorschlag_typ:string, rfid_code:string}|null
     */
    private function ermittleOfflineHintFuerRfid(string $rfidCode): ?array
    {
        $rfidCode = trim($rfidCode);
        if ($rfidCode === '') {
            return null;
        }

        $pdo = null;
        try {
            if (method_exists($this->datenbank, 'getOfflineVerbindung')) {
                $pdo = $this->datenbank->getOfflineVerbindung();
            }
        } catch (Throwable $e) {
            $pdo = null;
        }

        if (!($pdo instanceof PDO)) {
            return null;
        }

        // Wir suchen nach dem exakten Literal im SQL-Text, das wir in bucheZeitOfflinePerRfid() erzeugen.
        $escaped = str_replace("'", "''", $rfidCode);
        $like = "%rfid_code = '" . $escaped . "'%";

        try {
            $stmt = $pdo->prepare(
                "SELECT meta_aktion, erstellt_am\n"
                . "FROM db_injektionsqueue\n"
                . "WHERE status IN ('offen','verarbeitet','fehler')\n"
                . "  AND meta_aktion IN ('zeit_kommen_rfid','zeit_gehen_rfid')\n"
                . "  AND sql_befehl LIKE :like\n"
                . "ORDER BY id DESC\n"
                . "LIMIT 1"
            );
            $stmt->bindValue(':like', $like, PDO::PARAM_STR);
            if (!$stmt->execute()) {
                return null;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || empty($row['meta_aktion'])) {
                return null;
            }

            $aktion = (string)$row['meta_aktion'];
            $letzteTyp = null;
            if (strpos($aktion, 'kommen') !== false) {
                $letzteTyp = 'kommen';
            } elseif (strpos($aktion, 'gehen') !== false) {
                $letzteTyp = 'gehen';
            } else {
                return null;
            }

            $letzteZeit = '';
            if (isset($row['erstellt_am']) && is_string($row['erstellt_am'])) {
                $letzteZeit = (string)$row['erstellt_am'];
            }

            $vorschlag = ($letzteTyp === 'kommen') ? 'gehen' : 'kommen';

            return [
                'letzte_typ' => $letzteTyp,
                'letzte_zeit' => $letzteZeit,
                'vorschlag_typ' => $vorschlag,
                'rfid_code' => $rfidCode,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->auftragszeitService = AuftragszeitService::getInstanz();
        $this->zeitService         = ZeitService::getInstanz();
        $this->datenbank           = Database::getInstanz();
    }

    /**
     * Störungsmodus (Master-Prompt):
     *
     * Sobald ein Queue-Eintrag auf Status "fehler" steht, müssen alle Aktionen
     * am Terminal blockiert werden. Die Seite zeigt den konkreten SQL-Befehl,
     * der den Fehler ausgelöst hat, damit ein Admin gezielt reagieren kann.
     */
    public function stoerung(): void
    {
        // Terminal ist funktional eingeschraenkt (503), solange ein Queue-Fehler existiert.

        // Wenn ein Mitarbeiter noch eingeloggt ist, zeigen wir unten ein Mitarbeiterpanel.
        // (Nur read-only; Aktionen bleiben weiterhin gesperrt.)
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();

        $queueStatus = $_SESSION['terminal_queue_status'] ?? null;
        $letzterFehler = null;
        $queueOffen = null;
        $queueFehler = null;
        $queueZeit = null;

        if (is_array($queueStatus)) {
            if (isset($queueStatus['zeit']) && is_string($queueStatus['zeit'])) {
                $queueZeit = $queueStatus['zeit'];
            }
            if (array_key_exists('offen', $queueStatus) && $queueStatus['offen'] !== null) {
                $queueOffen = (int)$queueStatus['offen'];
            }
            if (array_key_exists('fehler', $queueStatus) && $queueStatus['fehler'] !== null) {
                $queueFehler = (int)$queueStatus['fehler'];
            }
            if (isset($queueStatus['letzter_fehler']) && is_array($queueStatus['letzter_fehler'])) {
                $letzterFehler = $queueStatus['letzter_fehler'];
            }
        }

        // Fallback: direkt aus dem Queue-Manager laden, falls die Session noch nichts hat.
        if ($letzterFehler === null && class_exists('OfflineQueueManager')) {
            try {
                $letzterFehler = OfflineQueueManager::getInstanz()->holeLetztenFehlerEintrag();
            } catch (Throwable $e) {
                $letzterFehler = null;
            }
        }

        // View-Kompatibilität: `views/terminal/stoerung.php` erwartet `$stoerungEintrag`.
        $stoerungEintrag = is_array($letzterFehler) ? $letzterFehler : null;

        // Wenn keine Queue-Fehler (mehr) vorhanden sind, ist der Stoerungsmodus beendet.
        // Wichtig: Der Benutzer kann auf dieser URL (aktion=stoerung) "festhaengen", wenn ein Admin
        // den Fehler im Backend behebt. Dann soll ein Reload automatisch zur Startseite zurueck.

        $fatalOhneQueue = false;
        if (is_array($queueStatus)) {
            $hauptOk = $queueStatus['hauptdb_verfuegbar'] ?? null;
            $queueOk = null;

            if (array_key_exists('offline_queue_verfuegbar', $queueStatus)) {
                $queueOk = $queueStatus['offline_queue_verfuegbar'];
            } elseif (array_key_exists('queue_verfuegbar', $queueStatus)) {
                $queueOk = $queueStatus['queue_verfuegbar'];
            }

            if ($hauptOk === false && $queueOk === false) {
                $fatalOhneQueue = true;
            }
        }

        if ($stoerungEintrag === null && $fatalOhneQueue === false) {
            $_SESSION['terminal_flash_nachricht'] = 'Stoerung behoben – Terminal ist wieder verfuegbar.';
            header('Location: terminal.php?aktion=start');
            return;
        }

        // Terminal ist funktional eingeschraenkt.
        http_response_code(503);
        // Monatsstatus fuer das Mitarbeiterpanel (Soll Monat / Soll bis heute / IST bis heute) – nur online.
        $monatsStatus = null;
        if (is_array($mitarbeiter) && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }



        require __DIR__ . '/../views/terminal/stoerung.php';
    }

    /**
     * Logout des Terminals.
     *
     * WICHTIG:
     * - Logout ist eine mutierende Aktion → **nur per POST + CSRF**.
     * - GET dient ausschließlich als „Zwischenseite“, um Auto-Logout/Legacy-Links
     *   (z. B. alte `?logout=1` URLs) sauber auf einen POST umzulenken.
     */
    public function logout(): void
    {
        // Kein Caching – wir wollen keine „Zurück“-Effekte beim Kiosk.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $csrfToken = $this->holeOderErzeugeCsrfToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->istCsrfTokenGueltigAusPost()) {
                $_SESSION['terminal_flash_fehler'] = 'Ungültiges CSRF-Token.';
                header('Location: terminal.php?aktion=start');
                exit;
            }

            $this->loescheTerminalMitarbeiterSession();

            $_SESSION['terminal_flash_nachricht'] = 'Sie wurden abgemeldet.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        // GET: zeigt eine kleine Seite, die den POST automatisch auslöst.
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');
        // Monatsstatus (fuer Mitarbeiterpanel im Logout-Screen): Soll Monat / Soll bis heute / Ist bis heute.
        $monatsStatus = null;

        if ($this->istHauptdatenbankAktiv() && is_array($mitarbeiter) && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }



        require __DIR__ . '/../views/terminal/logout.php';
    }


    /**
     * Loescht alle Session-Keys, die eine Terminal-Anmeldung an einen Mitarbeiter binden.
     *
     * Hintergrund (Kiosk):
     * - Der Browser laeuft dauerhaft und teilt sich eine Session ueber viele Mitarbeiter.
     * - Nach Logout oder nach erfolgreichem Kommen/Gehen sollen keine Statuswerte
     *   (z. B. Offline-Fallback "terminal_anwesend") vom Vorgaenger uebrig bleiben.
     */
    private function loescheTerminalMitarbeiterSession(): void
    {
        unset($_SESSION['terminal_mitarbeiter_id']);
        unset($_SESSION['terminal_mitarbeiter_vorname']);
        unset($_SESSION['terminal_mitarbeiter_nachname']);
        unset($_SESSION['terminal_darf_rfid_zuweisen']);

        // Offline-Fallback-Status ist nicht mitarbeiter-spezifisch gespeichert.
        // Daher beim Benutzerwechsel immer loeschen, damit es keine "falschen" Buttons gibt.
        unset($_SESSION['terminal_anwesend']);
        unset($_SESSION['terminal_anwesend_zeit']);

        // Token bewusst verwerfen, damit der naechste Nutzer einen frischen Token bekommt.
        unset($_SESSION['terminal_csrf_token']);

        // Debug-Flag nicht in Produktion "kleben" lassen
        unset($_SESSION['terminal_debug_aktiv']);
    }

    /**
     * Liefert den aktuell am Terminal angemeldeten Mitarbeiter oder null.
     */
    private function holeAngemeldetenTerminalMitarbeiter(): ?array
    {
        $id = $_SESSION['terminal_mitarbeiter_id'] ?? null;

        if ($id === null) {
            return null;
        }

        if (!is_int($id)) {
            if (!is_string($id) || !ctype_digit($id)) {
                return null;
            }
            $id = (int)$id;
        }

        if ($id <= 0) {
            return null;
        }

        // OFFLINE-FALL (Master-Prompt):
        // Wenn die Hauptdatenbank nicht erreichbar ist, dürfen Kommen/Gehen dennoch
        // funktionieren (über Offline-Queue). Dafür verwenden wir die in der Session
        // gecachten Mitarbeiterdaten und vermeiden DB-Queries.
        if (!$this->istHauptdatenbankAktiv()) {
            $vorname = $_SESSION['terminal_mitarbeiter_vorname'] ?? '';
            $nachname = $_SESSION['terminal_mitarbeiter_nachname'] ?? '';

            $darfRfidZuweisen = (bool)($_SESSION['terminal_darf_rfid_zuweisen'] ?? false);

            if (!is_string($vorname)) {
                $vorname = '';
            }
            if (!is_string($nachname)) {
                $nachname = '';
            }

            return [
                'id' => $id,
                'vorname' => $vorname,
                'nachname' => $nachname,
                'darf_rfid_zuweisen' => $darfRfidZuweisen,
            ];
        }

        try {
            // WICHTIG: Terminal-Login muss für alle *aktiven* Mitarbeiter funktionieren.
            // `ist_login_berechtigt` gilt ausschließlich für das Backend (Benutzername/E-Mail + Passwort).
            $sql = 'SELECT id, vorname, nachname
                    FROM mitarbeiter
                    WHERE id = :id
                      AND aktiv = 1
                    LIMIT 1';

            $mitarbeiter = $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden des angemeldeten Terminal-Mitarbeiters', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id, null, 'terminal');
            }
            return null;
        }

        if (!is_array($mitarbeiter) || !isset($mitarbeiter['id'])) {
            return null;
        }

        // Terminal-Adminrechte (nur online zuverlässig): in Session cachen, damit die View keinen DB-Zugriff braucht.
        $darfRfidZuweisen = $this->terminalMitarbeiterHatRecht((int)$mitarbeiter['id'], 'MITARBEITER_VERWALTEN');
        $_SESSION['terminal_darf_rfid_zuweisen'] = $darfRfidZuweisen ? 1 : 0;
        $mitarbeiter['darf_rfid_zuweisen'] = $darfRfidZuweisen;

        return $mitarbeiter;
    }

    /**
     * Startbildschirm des Terminals:
     * - GET: zeigt je nach Session entweder die Login-Maske oder das Hauptmenü.
     * - POST: verarbeitet einen Login-Versuch (RFID-Code oder Mitarbeiter-ID).
     * - Legacy: `?logout=1` ist nur noch eine **nicht-mutierende** Umleitung auf `?aktion=logout`.
     */
    public function start(): void
    {
        $nachricht  = null;
        $fehlerText = null;

        // Offline-Queue Hinweis (End-to-End Feldtest):
        // Wir zeigen bei offenen/fehlerhaften Queue-Eintraegen eine kleine Statusbox.
        // Ziel: Im Feld ist sofort sichtbar, ob lokale Buchungen noch "warten" oder ob die Queue in Stoerung ist.
        $queueStatus = [
            'offen' => 0,
            'fehler' => 0,
            'verarbeitet' => 0,
            'letzter_fehler_kurz' => null,
        ];

        // CSRF-Token für Terminal-POSTs (Login, Kommen/Gehen, Aufträge, ...)
        // Wird in mehreren Formularen des Startscreens genutzt.
        $csrfToken = $this->holeOderErzeugeCsrfToken();

        // Legacy-Logout (GET) darf nicht mutieren: wir leiten sauber auf die Logout-Aktion um.
        if (isset($_GET['logout'])) {
            header('Location: terminal.php?aktion=logout');
            exit;
        }

        // Flash aus vorherigen Aktionen (z. B. Urlaub-Antrag gespeichert)
        if (isset($_SESSION['terminal_flash_nachricht'])) {
            $flash = (string)$_SESSION['terminal_flash_nachricht'];
            unset($_SESSION['terminal_flash_nachricht']);
            if ($flash !== '') {
                $nachricht = $nachricht ? ($nachricht . ' ' . $flash) : $flash;
            }
        }
        if (isset($_SESSION['terminal_flash_fehler'])) {
            $flash = (string)$_SESSION['terminal_flash_fehler'];
            unset($_SESSION['terminal_flash_fehler']);
            if ($flash !== '') {
                $fehlerText = $fehlerText ? ($fehlerText . ' ' . $flash) : $flash;
            }
        }

        // Auto-Logout/Timeout (Master-Prompt) – wird in der View als JS umgesetzt.
        // Default: 60s (Kontext: "standard")
        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');


        // Debug-Mode (T-069 Helper): terminal.php?aktion=start&debug=1
        //
        // v8.1: Debug kann für Feldtests hilfreich sein, soll aber nicht nach jedem Redirect
        // wieder verschwinden. Daher merken wir den Debug-Status in der Session:
        // - Wenn ?debug=... gesetzt ist → Session setzen
        // - Sonst → Session lesen (persistiert über Redirects)
        $debugAktiv = false;
        if (isset($_GET['debug'])) {
            $d = strtolower(trim((string)$_GET['debug']));
            $debugAktiv = ($d === '1' || $d === 'true' || $d === 'yes' || $d === 'on');
            $_SESSION['terminal_debug_aktiv'] = $debugAktiv;
        } elseif (isset($_SESSION['terminal_debug_aktiv'])) {
            $debugAktiv = (bool)$_SESSION['terminal_debug_aktiv'];
        }

        // Offline-Queue Replay-Trigger (End-to-End Feldtest):
        // Sobald die Hauptdatenbank wieder verfuegbar ist, versuchen wir bei jedem
        // Aufruf der Startseite die offenen Queue-Eintraege abzuarbeiten.
        // Rate-Limit ueber Session, damit wir bei schnellen Reloads nicht spammen.
        if (class_exists('OfflineQueueManager') && class_exists('Database')) {
            try {
                $db = Database::getInstanz();
                $hauptOk = false;
                if (method_exists($db, 'istHauptdatenbankVerfuegbar')) {
                    $hauptOk = (bool)$db->istHauptdatenbankVerfuegbar();
                }

                if ($hauptOk) {
                    $now = time();
                    $last = 0;
                    if (isset($_SESSION['terminal_offlinequeue_replay_last'])) {
                        $last = (int)$_SESSION['terminal_offlinequeue_replay_last'];
                    }

                    // 10s Mindestabstand
                    if (($now - $last) >= 10) {
                        $_SESSION['terminal_offlinequeue_replay_last'] = $now;
                        OfflineQueueManager::getInstanz()->verarbeiteOffeneEintraege();
                    }
                }
            } catch (Throwable $e) {
                // bewusst ignorieren: Terminal darf dadurch nicht ausfallen.
            }
        }

        // Debug-Ansicht: letzte Queue-Einträge anzeigen (hilft bei T-069 Teil 2b: Offline-Queue)
        $debugQueueEintraege = null;
        if ($debugAktiv && class_exists('Database')) {
            try {
                $db = Database::getInstanz();
                $pdo = null;

                try {
                    if (method_exists($db, 'getOfflineVerbindung')) {
                        $pdo = $db->getOfflineVerbindung();
                    }
                } catch (Throwable $e) {
                    $pdo = null;
                }

                if (!($pdo instanceof PDO)) {
                    try {
                        if (method_exists($db, 'getVerbindung')) {
                            $pdo = $db->getVerbindung();
                        } elseif (method_exists($db, 'getPdo')) {
                            $pdo = $db->getPdo();
                        }
                    } catch (Throwable $e) {
                        $pdo = null;
                    }
                }

                if ($pdo instanceof PDO) {
                    $stmt = $pdo->query(
                        "SELECT id, erstellt_am, status, versuche, letzte_ausfuehrung, meta_mitarbeiter_id, meta_terminal_id, meta_aktion, "
                        . "LEFT(COALESCE(fehlernachricht,''), 140) AS fehler_kurz "
                        . "FROM db_injektionsqueue ORDER BY id DESC LIMIT 10"
                    );
                    if ($stmt !== false) {
                        $debugQueueEintraege = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!is_array($debugQueueEintraege)) {
                            $debugQueueEintraege = null;
                        }
                    }
                }
            } catch (Throwable $e) {
                $debugQueueEintraege = null;
            }
        }

        // Offline-Queue Status (immer lesen, aber nur anzeigen wenn Auffaelligkeiten da sind).
        // Wir versuchen bevorzugt die Offline-DB (Terminal), sonst Fallback auf Haupt-DB.
        $queueStatus = [
            'offen'       => 0,
            'fehler'      => 0,
            'verarbeitet' => 0,
            'letzter_fehler_kurz' => null,
        ];

        if (class_exists('Database')) {
            try {
                $db = Database::getInstanz();
                $pdo = null;

                try {
                    if (method_exists($db, 'getOfflineVerbindung')) {
                        $pdo = $db->getOfflineVerbindung();
                    }
                } catch (Throwable $e) {
                    $pdo = null;
                }

                if (!($pdo instanceof PDO)) {
                    try {
                        if (method_exists($db, 'getVerbindung')) {
                            $pdo = $db->getVerbindung();
                        } elseif (method_exists($db, 'getPdo')) {
                            $pdo = $db->getPdo();
                        }
                    } catch (Throwable $e) {
                        $pdo = null;
                    }
                }

                if ($pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM db_injektionsqueue GROUP BY status");
                    if ($stmt !== false) {
                        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                            $st = (string)($row['status'] ?? '');
                            $cnt = (int)($row['cnt'] ?? 0);
                            if ($st !== '' && array_key_exists($st, $queueStatus)) {
                                $queueStatus[$st] = $cnt;
                            }
                        }
                    }

                    if ((int)$queueStatus['fehler'] > 0) {
                        $stmt2 = $pdo->query(
                            "SELECT LEFT(COALESCE(fehlernachricht,''), 140) AS fehler_kurz "
                            . "FROM db_injektionsqueue WHERE status='fehler' ORDER BY letzte_ausfuehrung DESC, id DESC LIMIT 1"
                        );
                        if ($stmt2 !== false) {
                            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                            if (is_array($row2) && isset($row2['fehler_kurz'])) {
                                $t = trim((string)$row2['fehler_kurz']);
                                if ($t !== '') {
                                    $queueStatus['letzter_fehler_kurz'] = $t;
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Status bleibt auf Default (0) – Terminal darf nicht blockieren.
            }
        }

        // Queue-Status (auch ohne Debug): nur counts + letzter Fehler (kurz).
        // Die Verbindung kann je nach Setup die Offline-DB oder (Fallback) die Haupt-DB sein.
        if (class_exists('Database')) {
            try {
                $db = Database::getInstanz();
                $pdo = null;

                try {
                    if (method_exists($db, 'getOfflineVerbindung')) {
                        $pdo = $db->getOfflineVerbindung();
                    }
                } catch (Throwable $e) {
                    $pdo = null;
                }

                if (!($pdo instanceof PDO)) {
                    try {
                        if (method_exists($db, 'getVerbindung')) {
                            $pdo = $db->getVerbindung();
                        } elseif (method_exists($db, 'getPdo')) {
                            $pdo = $db->getPdo();
                        }
                    } catch (Throwable $e) {
                        $pdo = null;
                    }
                }

                if ($pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT status, COUNT(*) AS c FROM db_injektionsqueue GROUP BY status");
                    if ($stmt !== false) {
                        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                            $status = (string)($row['status'] ?? '');
                            $cnt = (int)($row['c'] ?? 0);
                            if (isset($queueStatus[$status])) {
                                $queueStatus[$status] = $cnt;
                            }
                        }
                    }

                    if ((int)$queueStatus['fehler'] > 0) {
                        $stmt2 = $pdo->query(
                            "SELECT LEFT(COALESCE(fehlernachricht,''), 140) AS fehler_kurz FROM db_injektionsqueue "
                            . "WHERE status='fehler' ORDER BY letzte_ausfuehrung DESC, id DESC LIMIT 1"
                        );
                        if ($stmt2 !== false) {
                            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                            if (is_array($row2) && isset($row2['fehler_kurz'])) {
                                $t = trim((string)$row2['fehler_kurz']);
                                if ($t !== '') {
                                    $queueStatus['letzter_fehler_kurz'] = $t;
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // leise ignorieren – Terminal darf nie daran scheitern.
            }
        }

        // Login-Versuch per POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Login mutiert die Session → ebenfalls CSRF schützen (Token ist bereits im Formular vorhanden).
            if (!$this->istCsrfTokenGueltigAusPost()) {
                $fehlerText = 'Ungültiges CSRF-Token.';
            } else {
                // Optional: Hinweis „RFID unbekannt“ am Terminal loeschen.
                // Hintergrund: Im Feld kann es vorkommen, dass kurz ein falscher Chip gescannt wurde.
                // Dann soll der rote Banner ohne Admin-Aktion wieder entfernt werden koennen.
                if (isset($_POST['clear_unknown_rfid']) && (string)$_POST['clear_unknown_rfid'] === '1') {
                    unset($_SESSION['terminal_last_unknown_rfid'], $_SESSION['terminal_last_unknown_rfid_ts']);
                    $_SESSION['terminal_flash_nachricht'] = 'Hinweis gelöscht.';

                    $redir = 'terminal.php?aktion=start';
                    if ($debugAktiv) {
                        $redir .= '&debug=1';
                    }
                    header('Location: ' . $redir);
                    exit;
                }


                // T-069 Helper: Offline-Queue manuell verarbeiten (nur Debug + Queue-Admin).
                // Zweck: Feldtest – nach Netz-Rueckkehr gezielt "Replay jetzt" anstossen und Ergebnis sehen.
                if ($debugAktiv && isset($_POST['debug_queue_verarbeiten']) && (string)$_POST['debug_queue_verarbeiten'] === '1') {
                    $mid = null;
                    if (isset($_SESSION['terminal_mitarbeiter_id'])) {
                        $m = $_SESSION['terminal_mitarbeiter_id'];
                        if (is_int($m)) {
                            $mid = $m;
                        } elseif (is_string($m) && ctype_digit($m)) {
                            $mid = (int)$m;
                        }
                    }

                    if ($mid === null || $mid <= 0) {
                        $_SESSION['terminal_flash_fehler'] = 'Queue-Verarbeitung nur moeglich, wenn ein Mitarbeiter am Terminal angemeldet ist.';
                        header('Location: terminal.php?aktion=start&debug=1');
                        exit;
                    }

                    if (!$this->terminalMitarbeiterHatRecht((int)$mid, 'QUEUE_VERWALTEN')) {
                        $_SESSION['terminal_flash_fehler'] = 'Keine Berechtigung fuer Queue-Verarbeitung.';
                        header('Location: terminal.php?aktion=start&debug=1');
                        exit;
                    }

                    if (!$this->istHauptdatenbankAktiv()) {
                        $_SESSION['terminal_flash_fehler'] = 'Hauptdatenbank offline – Queue kann derzeit nicht verarbeitet werden.';
                        header('Location: terminal.php?aktion=start&debug=1');
                        exit;
                    }

                    $beforeOffen = null;
                    $beforeFehler = null;
                    $afterOffen = null;
                    $afterFehler = null;
                    $dauerMs = null;
                    $newFehler = 0;

                    try {
                        $pdo = null;
                        if (class_exists('Database')) {
                            $db = Database::getInstanz();

                            try {
                                if (method_exists($db, 'getOfflineVerbindung')) {
                                    $pdo = $db->getOfflineVerbindung();
                                }
                            } catch (Throwable $e) {
                                $pdo = null;
                            }

                            if (!($pdo instanceof PDO)) {
                                try {
                                    if (method_exists($db, 'getVerbindung')) {
                                        $pdo = $db->getVerbindung();
                                    } elseif (method_exists($db, 'getPdo')) {
                                        $pdo = $db->getPdo();
                                    }
                                } catch (Throwable $e) {
                                    $pdo = null;
                                }
                            }
                        }

                        if ($pdo instanceof PDO) {
                            $beforeOffen = (int)$pdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status='offen'")->fetchColumn();
                            $beforeFehler = (int)$pdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status='fehler'")->fetchColumn();
                        }

                        $t0 = microtime(true);
                        if (class_exists('OfflineQueueManager')) {
                            OfflineQueueManager::getInstanz()->verarbeiteOffeneEintraege();
                        }
                        $t1 = microtime(true);
                        $dauerMs = (int)round(max(($t1 - $t0) * 1000.0, 0));

                        if ($pdo instanceof PDO) {
                            $afterOffen = (int)$pdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status='offen'")->fetchColumn();
                            $afterFehler = (int)$pdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status='fehler'")->fetchColumn();
                            $newFehler = max($afterFehler - ($beforeFehler ?? 0), 0);
                        }
                    } catch (Throwable $e) {
                        $_SESSION['terminal_flash_fehler'] = 'Queue-Verarbeitung fehlgeschlagen: ' . $e->getMessage();
                        header('Location: terminal.php?aktion=start&debug=1');
                        exit;
                    }

                    $_SESSION['terminal_debug_queue_report'] = [
                        'zeit' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                        'before_offen' => $beforeOffen,
                        'before_fehler' => $beforeFehler,
                        'after_offen' => $afterOffen,
                        'after_fehler' => $afterFehler,
                        'dauer_ms' => $dauerMs,
                        'new_fehler' => $newFehler,
                    ];

                    $msg = 'Queue verarbeitet.';
                    if ($beforeOffen !== null && $afterOffen !== null) {
                        $msg .= ' Offen: ' . (int)$beforeOffen . ' → ' . (int)$afterOffen . '.';
                    }
                    if ($newFehler > 0) {
                        $msg .= ' Neue Fehler: ' . (int)$newFehler . '.';
                    }
                    if ($dauerMs !== null) {
                        $msg .= ' Dauer: ' . (int)$dauerMs . 'ms.';
                    }

                    $_SESSION['terminal_flash_nachricht'] = trim($msg);
                    header('Location: terminal.php?aktion=start&debug=1');
                    exit;
                }
                // T-069 Helper: Bug-Notiz direkt ins LOG schreiben (nur im Debug-Modus).
                // Aktivierung: terminal.php?aktion=start&debug=1
                // Hintergrund: Der Feldtest ist manuell (klicken & Auffälligkeiten sammeln).
                // Damit diese Beobachtungen nicht „nur“ in Chats landen, kann man sie hier
                // mit Kontext (Session/DB/Queue-Status) in `system_log` ablegen.

                if ($debugAktiv && isset($_POST['bugreport_submit'])) {
                    $bugId = isset($_POST['bugreport_id']) ? trim((string)$_POST['bugreport_id']) : '';
                    $text  = isset($_POST['bugreport_text']) ? trim((string)$_POST['bugreport_text']) : '';

                    if ($text === '') {
                        $_SESSION['terminal_flash_fehler'] = 'Bug-Notiz ist leer.';
                        header('Location: terminal.php?aktion=start&debug=1');
                        exit;
                    }

                    // Begrenzen, damit das Log nicht aus Versehen riesig wird.
                    $maxLen = 2000;
                    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                        if (mb_strlen($text, 'UTF-8') > $maxLen) {
                            $text = mb_substr($text, 0, $maxLen, 'UTF-8');
                        }
                    } else {
                        if (strlen($text) > $maxLen) {
                            $text = substr($text, 0, $maxLen);
                        }
                    }

                    $mid = null;
                    if (isset($_SESSION['terminal_mitarbeiter_id'])) {
                        $midInt = (int)$_SESSION['terminal_mitarbeiter_id'];
                        if ($midInt > 0) {
                            $mid = $midInt;
                        }
                    }

                    $qs = $_SESSION['terminal_queue_status'] ?? null;
                    $qsKurz = [];
                    if (is_array($qs)) {
                        $qsKurz = [
                            'hauptdb_verfuegbar' => $qs['hauptdb_verfuegbar'] ?? null,
                            'queue_speicherort'  => $qs['queue_speicherort'] ?? null,
                            'offen'              => $qs['offen'] ?? null,
                            'fehler'             => $qs['fehler'] ?? null,
                        ];
                    }

                    if (class_exists('Logger')) {
                        Logger::warn('Terminal Bugreport (T-069)', [
                            'bug_id' => $bugId,
                            'text' => $text,
                            'aktion' => (string)($_GET['aktion'] ?? 'start'),
                            'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
                            'session_anwesend' => $_SESSION['terminal_anwesend'] ?? null,
                            'session_anwesend_zeit' => $_SESSION['terminal_anwesend_zeit'] ?? null,
                            'queue' => $qsKurz,
                        ], $mid, null, 'terminal_bug');
                    }

                    $_SESSION['terminal_flash_nachricht'] = 'Bug-Notiz gespeichert (LOG).';
                    header('Location: terminal.php?aktion=start&debug=1');
                    exit;
                }

                // OFFLINE-FALL (Master-Prompt v9):
                // Wenn die Hauptdatenbank offline ist, darf das Terminal trotzdem Kommen/Gehen
                // annehmen – aber ohne Mitarbeiter-Login. Wir merken uns nur den RFID-Code
                // (Session) und lassen die eigentliche Buchung als Queue-Eintrag erfolgen.
                if (!$this->istHauptdatenbankAktiv()) {
                    $rfidCode = isset($_POST['rfid_code']) ? trim((string)$_POST['rfid_code']) : '';

                    if ($rfidCode === '') {
                        $fehlerText = 'Bitte RFID scannen oder eingeben.';
                    } else {
                        // Für den nächsten Schritt (Kommen/Gehen) merken.
                        $_SESSION['terminal_offline_rfid_code'] = $rfidCode;

                        // UX (Offline): Aus der lokalen Queue die letzte bekannte Aktion für diesen RFID
                        // ermitteln und einen Button-Vorschlag speichern (damit man sich weniger verklickt).
                        $hint = $this->ermittleOfflineHintFuerRfid($rfidCode);
                        if (is_array($hint)) {
                            $_SESSION['terminal_offline_rfid_hint'] = $hint;
                        } else {
                            unset($_SESSION['terminal_offline_rfid_hint']);
                        }

                        $_SESSION['terminal_flash_nachricht'] = 'RFID-Code erfasst. Bitte „Kommen“ oder „Gehen“ wählen.';

                        // Immer redirecten, damit Refresh/Enter nicht mehrfach auslöst.
                        $redir = 'terminal.php?aktion=start';
                        if ($debugAktiv) {
                            $redir .= '&debug=1';
                        }
                        header('Location: ' . $redir);
                        exit;
                    }
                } else {
                    $rfidCode      = isset($_POST['rfid_code']) ? trim((string)$_POST['rfid_code']) : '';
                    $mitarbeiterId = isset($_POST['mitarbeiter_id']) ? (int)$_POST['mitarbeiter_id'] : 0;

                    if ($rfidCode === '' && $mitarbeiterId <= 0) {
                        // UI-Vorgabe (Kiosk): Nur RFID kommunizieren, Demo-Fallbacks bleiben intern moeglich.
                        $fehlerText = 'Bitte RFID scannen oder eingeben.';
                    } else {
                        $mitarbeiter = null;
                        $loginFehler = null;

                        try {
                            // T-076 (Teil 2): Terminal-Login per Personalnummer ermöglichen.
                            // Reihenfolge bewusst:
                            // 1) RFID-Code (exakt)
                            // 2) falls rein numerisch und RFID nicht gefunden: Personalnummer
                            // 3) falls weiterhin nichts gefunden: Mitarbeiter-ID (Fallback)

                            if ($rfidCode !== '') {
                                // B-036: Numerische Codes können mehrdeutig sein (z. B. Personalnummer eines
                                // Mitarbeiters = Mitarbeiter-ID eines anderen Mitarbeiters). In diesem Fall
                                // darf das Terminal nicht „still“ eine Person wählen.
                                $treffer = [];

                                $treffer['rfid'] = $this->datenbank->fetchEine(
                                    'SELECT id, vorname, nachname
                                     FROM mitarbeiter
                                     WHERE rfid_code = :code
                                       AND aktiv = 1
                                     LIMIT 1',
                                    ['code' => $rfidCode]
                                );

                                // RFID ist immer eindeutig (uniq Index) und hat Priorität.
                                if (is_array($treffer['rfid']) && isset($treffer['rfid']['id'])) {
                                    $mitarbeiter = $treffer['rfid'];
                                } elseif (ctype_digit($rfidCode)) {
                                    // Nur wenn RFID nichts ergibt, prüfen wir Personalnummer/ID – hier ist
                                    // die Mehrdeutigkeit realistisch (beide sind kurze numerische Werte).
                                    $treffer['personalnummer'] = $this->datenbank->fetchEine(
                                        'SELECT id, vorname, nachname
                                         FROM mitarbeiter
                                         WHERE personalnummer = :pn
                                           AND aktiv = 1
                                         LIMIT 1',
                                        ['pn' => $rfidCode]
                                    );

                                    $treffer['id'] = $this->datenbank->fetchEine(
                                        'SELECT id, vorname, nachname
                                         FROM mitarbeiter
                                         WHERE id = :id
                                           AND aktiv = 1
                                         LIMIT 1',
                                        ['id' => (int)$rfidCode]
                                    );

                                    $ids = [];
                                    foreach (['personalnummer', 'id'] as $quelle) {
                                        $row = $treffer[$quelle] ?? null;
                                        if (is_array($row) && isset($row['id'])) {
                                            $ids[(int)$row['id']][] = (string)$quelle;
                                        }
                                    }

                                    if (count($ids) > 1) {
                                        // UI-Vorgabe (Kiosk): Keine Hinweise auf Personalnummer/Mitarbeiter-ID anzeigen.
                                        $loginFehler = 'Code ist mehrdeutig (passt zu mehreren Mitarbeitern). Bitte RFID scannen oder Personalbüro informieren.';

                                        if (class_exists('Logger')) {
                                            Logger::warn('Terminal-Login: Mehrdeutiger numerischer Code (Personalnummer vs ID)', [
                                                'code' => $rfidCode,
                                                'treffer' => $ids,
                                            ], null, null, 'terminal_login');
                                        }
                                    } elseif (isset($treffer['personalnummer']) && is_array($treffer['personalnummer']) && isset($treffer['personalnummer']['id'])) {
                                        $mitarbeiter = $treffer['personalnummer'];
                                    } elseif (isset($treffer['id']) && is_array($treffer['id']) && isset($treffer['id']['id'])) {
                                        $mitarbeiter = $treffer['id'];
                                    } else {
                                        $mitarbeiter = null;
                                    }
                                } else {
                                    $mitarbeiter = null;
                                }
                            } else {
                                $mitarbeiter = $this->datenbank->fetchEine(
                                    'SELECT id, vorname, nachname
                                     FROM mitarbeiter
                                     WHERE id = :id
                                       AND aktiv = 1
                                     LIMIT 1',
                                    ['id' => $mitarbeiterId]
                                );
                            }
                        } catch (Throwable $e) {
                            if (class_exists('Logger')) {
                                Logger::error('Fehler beim Login am Terminal', [
                                    'rfid_code'      => $rfidCode,
                                    'mitarbeiter_id' => $mitarbeiterId,
                                    'exception'      => $e->getMessage(),
                                ], null, null, 'terminal_login');
                            }
                            $mitarbeiter = null;
                        }

                        if (!is_array($mitarbeiter) || !isset($mitarbeiter['id'])) {
                            if ($rfidCode !== '' && $loginFehler === null) {
                                $_SESSION['terminal_last_unknown_rfid'] = $rfidCode;
                                $_SESSION['terminal_last_unknown_rfid_ts'] = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
                            }
                            $fehlerText = $loginFehler !== null ? $loginFehler : 'Mitarbeiter nicht gefunden oder inaktiv.';
                        } else {
                            $_SESSION['terminal_mitarbeiter_id'] = (int)$mitarbeiter['id'];
                            $_SESSION['terminal_mitarbeiter_vorname'] = (string)($mitarbeiter['vorname'] ?? '');
                            $_SESSION['terminal_mitarbeiter_nachname'] = (string)($mitarbeiter['nachname'] ?? '');
                            $nachricht = 'Angemeldet als ' . (string)$mitarbeiter['vorname'] . ' ' . (string)$mitarbeiter['nachname'] . '.';
                        }
                    }
                }
            }
        }

        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();

        // Einfache Tagesübersicht (heute) für das Terminal-Hauptmenü.
        $heuteDatum     = null;
        $heuteBuchungen = [];
        $heuteFehler    = null;

        // Laufende Aufträge (für schnelle Kontrolle am Terminal)
        $laufendeAuftraege = [];

        // T-069 (UX): Hinweis für den eingeloggten Mitarbeiter, wenn in den
        // letzten Tagen unvollständige Kommen/Gehen-Stempel existieren.
        // Ziel: „Vergessen zu gehen/kommen“ sofort sichtbar machen.
        $zeitWarnungen = null;

        $hauptdbAktiv = $this->istHauptdatenbankAktiv();

        // Subview: Arbeitszeit-Übersicht (separate Seite über Start-Controller)
        $zeigeArbeitszeitUebersichtSeite = ((string)($_GET['view'] ?? '') === 'arbeitszeit');

        // Mikro-Buchungen am Terminal standardmäßig ausblenden (optional via ?show_micro=1).
        $zeigeMicroBuchungen = ((string)($_GET['show_micro'] ?? '') === '1');

        if (is_array($mitarbeiter) && isset($mitarbeiter['id']) && $hauptdbAktiv) {
            $uebersicht      = $this->holeHeutigeZeitUebersicht((int)$mitarbeiter['id'], $zeigeMicroBuchungen);
            $heuteDatum      = $uebersicht['datum'];
            $heuteBuchungen  = $uebersicht['buchungen'];
            $heuteFehler     = $uebersicht['fehler'];

            try {
                $auftragszeitModel = new AuftragszeitModel();
                $laufendeAuftraege = $auftragszeitModel->holeLaufendeFuerMitarbeiter((int)$mitarbeiter['id']);
            } catch (Throwable $e) {
                $laufendeAuftraege = [];
            }

            // Unvollständige Stempel für den Mitarbeiter (z. B. nur Kommen ohne Gehen).
            // Wichtig: "heute" ist noch nicht abgeschlossen. Wir warnen erst ab Folgetag,
            // damit normale Arbeitstage (laufende Schicht) nicht als Fehler erscheinen.
            try {
                $rows = $this->datenbank->fetchAlle(
                    "SELECT\n"
                    . "  DATE(z.zeitstempel) AS datum,\n"
                    . "  SUM(CASE WHEN z.typ = 'kommen' THEN 1 ELSE 0 END) AS anzahl_kommen,\n"
                    . "  SUM(CASE WHEN z.typ = 'gehen' THEN 1 ELSE 0 END) AS anzahl_gehen,\n"
                    . "  COUNT(*) AS anzahl_buchungen,\n"
                    . "  MAX(z.zeitstempel) AS letzter_stempel\n"
                    . "FROM zeitbuchung z\n"
                    . "WHERE z.mitarbeiter_id = :mid\n"
                    . "  AND z.typ IN ('kommen','gehen')\n"
                    . "  AND z.zeitstempel >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)\n"
                    . "GROUP BY DATE(z.zeitstempel)\n"
                    . "HAVING (COUNT(*) % 2) = 1\n"
                    . "   AND datum < CURDATE()\n"
                    . "ORDER BY datum DESC\n"
                    . "LIMIT 3",
                    ['mid' => (int)$mitarbeiter['id']]
                );

                $tmp = [];

                // Nachtschicht-Grenzfall: Kommen am Abend + Gehen am Folgetag frueh
                // soll nicht als Warnung erscheinen (paarweise ueber Mitternacht).
                // Wir filtern nur den sicheren Fall: genau 1 Stempel am Tag (nur Kommen oder nur Gehen).
                $mid = (int)$mitarbeiter['id'];
                $istNachtschichtGrenzfall = function (string $datum, array $r) use ($mid): bool {
                    $anz = (int)($r['anzahl_buchungen'] ?? 0);
                    $k = (int)($r['anzahl_kommen'] ?? 0);
                    $g = (int)($r['anzahl_gehen'] ?? 0);
                    if ($anz !== 1) {
                        return false;
                    }
                    if (!(($k === 1 && $g === 0) || ($k === 0 && $g === 1))) {
                        return false;
                    }

                    $d = DateTimeImmutable::createFromFormat('Y-m-d', $datum);
                    if (!$d) {
                        return false;
                    }
                    $prev = $d->modify('-1 day')->format('Y-m-d');
                    $next = $d->modify('+1 day')->format('Y-m-d');

                    $zeit = (string)($r['letzter_stempel'] ?? '');
                    if ($zeit === '' || strlen($zeit) < 19) {
                        return false;
                    }
                    $t = substr($zeit, 11, 8);

                    $db = $this->datenbank;

                    // Fall A: Tag hat nur Kommen am Abend, Folgetag hat nur Gehen frueh
                    if ($k === 1) {
                        if (!($t >= '18:00:00' && $t <= '23:59:59')) {
                            return false;
                        }
                        $r2 = $db->fetchEinzel(
                            "SELECT
"
                            . "  SUM(CASE WHEN typ='kommen' THEN 1 ELSE 0 END) AS anzahl_kommen,
"
                            . "  SUM(CASE WHEN typ='gehen' THEN 1 ELSE 0 END) AS anzahl_gehen,
"
                            . "  COUNT(*) AS anzahl_buchungen,
"
                            . "  MIN(zeitstempel) AS erster_stempel,
"
                            . "  MAX(zeitstempel) AS letzter_stempel
"
                            . "FROM zeitbuchung
"
                            . "WHERE mitarbeiter_id = :mid
"
                            . "  AND typ IN ('kommen','gehen')
"
                            . "  AND DATE(zeitstempel) = :d
"
                            . "GROUP BY DATE(zeitstempel)",
                            ['mid' => $mid, 'd' => $next]
                        );
                        if (!is_array($r2)) {
                            return false;
                        }
                        if ((int)($r2['anzahl_buchungen'] ?? 0) !== 1) {
                            return false;
                        }
                        if ((int)($r2['anzahl_gehen'] ?? 0) !== 1 || (int)($r2['anzahl_kommen'] ?? 0) !== 0) {
                            return false;
                        }
                        $z2 = (string)($r2['erster_stempel'] ?? '');
                        if ($z2 === '' || strlen($z2) < 19) {
                            return false;
                        }
                        $t2 = substr($z2, 11, 8);
                        return ($t2 >= '00:00:00' && $t2 <= '06:00:00');
                    }

                    // Fall B: Tag hat nur Gehen frueh, Vortag hat nur Kommen am Abend
                    if (!($t >= '00:00:00' && $t <= '06:00:00')) {
                        return false;
                    }
                    $r1 = $db->fetchEinzel(
                        "SELECT
"
                        . "  SUM(CASE WHEN typ='kommen' THEN 1 ELSE 0 END) AS anzahl_kommen,
"
                        . "  SUM(CASE WHEN typ='gehen' THEN 1 ELSE 0 END) AS anzahl_gehen,
"
                        . "  COUNT(*) AS anzahl_buchungen,
"
                        . "  MIN(zeitstempel) AS erster_stempel,
"
                        . "  MAX(zeitstempel) AS letzter_stempel
"
                        . "FROM zeitbuchung
"
                        . "WHERE mitarbeiter_id = :mid
"
                        . "  AND typ IN ('kommen','gehen')
"
                        . "  AND DATE(zeitstempel) = :d
"
                        . "GROUP BY DATE(zeitstempel)",
                        ['mid' => $mid, 'd' => $prev]
                    );
                    if (!is_array($r1)) {
                        return false;
                    }
                    if ((int)($r1['anzahl_buchungen'] ?? 0) !== 1) {
                        return false;
                    }
                    if ((int)($r1['anzahl_kommen'] ?? 0) !== 1 || (int)($r1['anzahl_gehen'] ?? 0) !== 0) {
                        return false;
                    }
                    $z1 = (string)($r1['letzter_stempel'] ?? '');
                    if ($z1 === '' || strlen($z1) < 19) {
                        return false;
                    }
                    $t1 = substr($z1, 11, 8);
                    return ($t1 >= '18:00:00' && $t1 <= '23:59:59');
                };

                foreach ($rows as $r) {
                    $datum = (string)($r['datum'] ?? '');
                    if ($datum === '') {
                        continue;
                    }

                    if ($istNachtschichtGrenzfall($datum, $r)) {
                        continue;
                    }
                    $tmp[] = [
                        'datum' => $datum,
                        'anzahl_kommen' => (int)($r['anzahl_kommen'] ?? 0),
                        'anzahl_gehen' => (int)($r['anzahl_gehen'] ?? 0),
                        'anzahl_buchungen' => (int)($r['anzahl_buchungen'] ?? 0),
                        'letzter_stempel' => (string)($r['letzter_stempel'] ?? ''),
                    ];
                }

                if (count($tmp) > 0) {
                    $zeitWarnungen = $tmp;
                }
            } catch (Throwable $e) {
                $zeitWarnungen = null;
            }
        } elseif (is_array($mitarbeiter) && isset($mitarbeiter['id']) && !$hauptdbAktiv) {
            // Offline-Fall (Master-Prompt): Komplexe Übersichten sind gesperrt.
            $heuteDatum = (new DateTimeImmutable('today'))->format('Y-m-d');
            $heuteFehler = 'Hauptdatenbank offline – Übersicht ist nur online verfügbar.';
        }
        // Monatsstatus (T-092): SOLL Monat / SOLL bis heute / IST bis heute (+ Live-Heute).
        $monatsStatus = null;
        $monatsStatusFehler = null;

        if (is_array($mitarbeiter) && isset($mitarbeiter['id']) && $hauptdbAktiv) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
            if ($monatsStatus === null) {
                $monatsStatusFehler = 'Monatsstatus konnte nicht geladen werden.';
            }
        }



        // Stundenkonto (Zusatz2): Saldo Stand bis Vormonat (exklusiv aktueller Monat)
        $stundenkontoSaldo = null;
        $stundenkontoSaldoFehler = null;

        if (is_array($mitarbeiter) && isset($mitarbeiter['id']) && $hauptdbAktiv) {
            if (class_exists('StundenkontoService')) {
                try {
                    $stundenkontoService = StundenkontoService::getInstanz();
                    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
                    $jahr = (int)$now->format('Y');
                    $monat = (int)$now->format('n');

                    $saldoMin = $stundenkontoService->holeSaldoMinutenBisVormonat((int)$mitarbeiter['id'], $jahr, $monat);

                    $stundenkontoSaldo = [
                        'saldo_minuten_bis_vormonat' => $saldoMin,
                        'saldo_stunden_bis_vormonat' => $stundenkontoService->formatMinutenAlsStundenString($saldoMin, true),
                        'jahr' => $jahr,
                        'monat' => $monat,
                    ];
                } catch (Throwable $e) {
                    $stundenkontoSaldo = null;
                    $stundenkontoSaldoFehler = 'Stundenkonto konnte nicht geladen werden.';

                    if (class_exists('Logger')) {
                        Logger::warn('Terminal: Stundenkonto konnte nicht geladen werden', [
                            'mitarbeiter_id' => (int)$mitarbeiter['id'],
                            'exception'      => $e->getMessage(),
                        ], (int)$mitarbeiter['id'], null, 'stundenkonto');
                    }
                }
            }
        }

        // Stundenkonto (Zusatz2): Saldo Stand bis Vormonat (exklusiv aktueller Monat)
        $stundenkontoSaldo = null;
        $stundenkontoSaldoFehler = null;

        if (is_array($mitarbeiter) && isset($mitarbeiter['id']) && $hauptdbAktiv) {
            if (class_exists('StundenkontoService')) {
                try {
                    $stundenkontoService = StundenkontoService::getInstanz();
                    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
                    $jahr = (int)$now->format('Y');
                    $monat = (int)$now->format('n');

                    $saldoMin = $stundenkontoService->holeSaldoMinutenBisVormonat((int)$mitarbeiter['id'], $jahr, $monat);

                    $stundenkontoSaldo = [
                        'saldo_minuten_bis_vormonat' => $saldoMin,
                        'saldo_stunden_bis_vormonat' => $stundenkontoService->formatMinutenAlsStundenString($saldoMin, true),
                        'jahr' => $jahr,
                        'monat' => $monat,
                    ];
                } catch (Throwable $e) {
                    $stundenkontoSaldo = null;
                    $stundenkontoSaldoFehler = 'Stundenkonto konnte nicht geladen werden.';

                    if (class_exists('Logger')) {
                        Logger::warn('Terminal: Stundenkonto konnte nicht geladen werden', [
                            'mitarbeiter_id' => (int)$mitarbeiter['id'],
                            'exception'      => $e->getMessage(),
                        ], (int)$mitarbeiter['id'], null, 'stundenkonto');
                    }
                }
            }
        }
        // Urlaubssaldo (aktuelles Jahr) – nur online sinnvoll.
        $urlaubJahr = (int)(new DateTimeImmutable('now'))->format('Y');
        $urlaubSaldo = null;
        $urlaubSaldoFehler = null;
        $urlaubVorschau = null;

        if (is_array($mitarbeiter) && isset($mitarbeiter['id']) && $this->istHauptdatenbankAktiv()) {
            try {
                $urlaubService = UrlaubService::getInstanz();
                $urlaubSaldo = $urlaubService->berechneUrlaubssaldoFuerJahr((int)$mitarbeiter['id'], $urlaubJahr);
            } catch (Throwable $e) {
                $urlaubSaldo = null;
                $urlaubSaldoFehler = 'Urlaubssaldo konnte nicht geladen werden.';

                if (class_exists('Logger')) {
                    Logger::warn('Terminal: Urlaubssaldo konnte nicht geladen werden', [
                        'mitarbeiter_id' => (int)$mitarbeiter['id'],
                        'jahr'           => $urlaubJahr,
                        'exception'      => $e->getMessage(),
                    ], (int)$mitarbeiter['id'], null, 'terminal_urlaub');
                }
            }
        }


        require __DIR__ . '/../views/terminal/start.php';
    }

    /**
     * Formular zum Starten eines Auftrags anzeigen.
     *
     * @param string|null $meldung       Erfolgs-/Infomeldung
     * @param string|null $fehlermeldung Fehlermeldung
     */
    public function auftragStartenForm(?string $meldung = null, ?string $fehlermeldung = null): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }


        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestartet werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = $meldung;
        $fehlerText = $fehlermeldung;
        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');
        $csrfToken = $this->holeOderErzeugeCsrfToken();
        // Monatsstatus (fuer Mitarbeiterpanel in Auftrag-Views): Soll Monat / Soll bis heute / IST bis heute.
        $monatsStatus = null;

        if ($this->istHauptdatenbankAktiv() && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }




        require __DIR__ . '/../views/terminal/auftrag_starten.php';
    }

    /**
     * POST-Handler zum Starten eines Hauptauftrags.
     */
    public function auftragStarten(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }


        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->auftragStartenForm(null, 'Ungültiges Formular-Token (CSRF). Bitte erneut versuchen.');
            return;
        }


        
                if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
                    $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestartet werden kann.';
                    header('Location: terminal.php?aktion=start');
                    exit;
                }
        
        $mitarbeiterId = (int)$mitarbeiter['id'];
        $auftragscode  = isset($_POST['auftragscode']) ? trim((string)$_POST['auftragscode']) : '';
        $arbeitsschrittCode = isset($_POST['arbeitsschritt_code']) ? trim((string)$_POST['arbeitsschritt_code']) : '';
        $maschineRaw   = isset($_POST['maschine_id']) ? trim((string)$_POST['maschine_id']) : '';
        $maschineId    = $this->parseMaschineIdAusScan($maschineRaw);

        if ($auftragscode === '') {
            $this->auftragStartenForm(null, 'Bitte einen Auftragscode eingeben.');
            return;
        }

        // Auftragsmodul v1: Arbeitsschritt ist Pflicht (Zeit wird pro Arbeitsschritt erfasst)
        if ($arbeitsschrittCode === '') {
            $this->auftragStartenForm(null, 'Bitte einen Arbeitsschritt-Code eingeben.');
            return;
        }

        $neueId = $this->auftragszeitService->starteAuftrag($mitarbeiterId, $auftragscode, $maschineId, $arbeitsschrittCode);

        if ($neueId === null) {
            $this->auftragStartenForm(null, 'Auftrag konnte nicht gestartet werden. Bitte erneut versuchen.');
            return;
        }


        // Session: letzten Hauptauftrag lokal merken (für Quick-Actions/Letzter Auftrag).
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['terminal_letzter_auftrag'] = [
                    'auftragscode'     => $auftragscode,
                    'arbeitsschritt_code' => $arbeitsschrittCode,
                    'status'          => 'laufend',
                    'typ'             => 'haupt',
                    'auftragszeit_id' => (int)$neueId,
                    'zeit'            => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ];

                // Wenn ein neuer Hauptauftrag startet, werden laufende Nebenauftraege automatisch abgeschlossen.
                // Daher local/state ebenfalls zuruecksetzen.
                $_SESSION['terminal_nebenauftrag_laufend_count'] = 0;
                $ln = $_SESSION['terminal_letzter_nebenauftrag'] ?? null;
                if (is_array($ln)) {
                    $ln['status'] = 'abgeschlossen';
                    $ln['zeit'] = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
                    $_SESSION['terminal_letzter_nebenauftrag'] = $ln;
                }
            }
        } catch (\Throwable $e) {
            // niemals Terminal-Flow blockieren
        }

        if ($neueId === 0) {
            $meldung = 'Hauptdatenbank offline – Auftrag wurde in der Offline-Queue gespeichert.';
        } else {
            $meldung = 'Auftrag wurde gestartet (Auftragszeit-ID: ' . (int)$neueId . ').';
        }

        // Kiosk-Flow: nach erfolgreichem Start direkt abmelden und wieder zur RFID-Abfrage.
        $_SESSION['terminal_flash_nachricht'] = $meldung;
        $this->loescheTerminalMitarbeiterSession();
        header('Location: terminal.php?aktion=start');
        exit;
    }

    /**
     * Formular zum Stoppen eines Auftrags anzeigen.
     *
     * @param string|null $meldung       Erfolgs-/Infomeldung
     * @param string|null $fehlermeldung Fehlermeldung
     */
    public function auftragStoppenForm(?string $meldung = null, ?string $fehlermeldung = null): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }

        // Regel: Auftrags-Stop ist nur sinnvoll, wenn der Mitarbeiter bereits anwesend ist.
        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestoppt werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = $meldung;
        $fehlerText = $fehlermeldung;
        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');
        $csrfToken = $this->holeOderErzeugeCsrfToken();
        // Monatsstatus (fuer Mitarbeiterpanel in Auftrag-Views): Soll Monat / Soll bis heute / IST bis heute.
        $monatsStatus = null;

        if ($this->istHauptdatenbankAktiv() && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }




        require __DIR__ . '/../views/terminal/auftrag_stoppen.php';
    }

    /**
     * Schnell-Stop (Kiosk-Flow): Stoppt den aktuell laufenden Hauptauftrag direkt vom Startscreen.
     *
     * Ziel:
     * - Kein Zwischenscreen "Hauptauftrag stoppen", wenn eindeutig ein Hauptauftrag läuft.
     * - Mutierende Aktion nur per POST + CSRF (Routing in public/terminal.php).
     */
    public function auftragStoppenQuick(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $_SESSION['terminal_flash_fehler'] = 'Ungültiges Formular-Token (CSRF). Bitte erneut versuchen.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        // Regel: Auftrags-Stop ist nur sinnvoll, wenn der Mitarbeiter bereits anwesend ist.
        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestoppt werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $mitarbeiterId = (int)$mitarbeiter['id'];

        // Default: wir machen nur Zeiterfassung. "Stoppen" setzt endzeit und status=abgeschlossen.
        // (Kein "Abbrechen/Abschließen" im Terminal-Quick-Flow.)
        $status = 'abgeschlossen';

        // Best effort: wenn wir lokal wissen, welcher Auftrag läuft, geben wir den Code mit.
        $auftragscode = null;
        try {
            $last = $_SESSION['terminal_letzter_auftrag'] ?? null;
            if (is_array($last)) {
                $typ = isset($last['typ']) ? (string)$last['typ'] : '';
                $st  = isset($last['status']) ? (string)$last['status'] : '';
                if ($typ === 'haupt' && $st === 'laufend' && isset($last['auftragscode']) && is_string($last['auftragscode'])) {
                    $c = trim((string)$last['auftragscode']);
                    if ($c !== '') {
                        $auftragscode = $c;
                    }
                }
            }
        } catch (Throwable $e) {
            $auftragscode = null;
        }

        $res = $this->auftragszeitService->stoppeAuftrag($mitarbeiterId, null, $auftragscode, $status);

        if ($res === null) {
            // Fallback: falls doch mehrere Aufträge laufen oder keine eindeutige Zuordnung möglich ist.
            $this->auftragStoppenForm(null, 'Es wurde kein eindeutiger laufender Hauptauftrag gefunden.');
            return;
        }

        // Session: Status des letzten Hauptauftrags lokal aktualisieren (nur informativ)
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $code = $auftragscode;
                if ($code === null) {
                    $last = $_SESSION['terminal_letzter_auftrag'] ?? null;
                    if (is_array($last) && isset($last['auftragscode']) && is_string($last['auftragscode'])) {
                        $c = trim((string)$last['auftragscode']);
                        if ($c !== '') {
                            $code = $c;
                        }
                    }
                }

                if ($code !== null) {
                    $lastId = 0;
                    $last = $_SESSION['terminal_letzter_auftrag'] ?? null;
                    if (is_array($last) && isset($last['auftragszeit_id'])) {
                        $lastId = (int)$last['auftragszeit_id'];
                    }

                    $_SESSION['terminal_letzter_auftrag'] = [
                        'auftragscode'     => $code,
                        'status'          => $status,
                        'typ'             => 'haupt',
                        'auftragszeit_id' => $lastId,
                        'zeit'            => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                    ];
                }
            }
        } catch (Throwable $e) {
            // niemals Terminal-Flow blockieren
        }

        $meldung = ($res === 0)
            ? 'Hauptdatenbank offline – Auftrag-Stopp wurde in der Offline-Queue gespeichert.'
            : 'Auftrag wurde gestoppt.';

        // Kiosk-Flow: nach erfolgreichem Stopp direkt abmelden und wieder zur RFID-Abfrage.
        $_SESSION['terminal_flash_nachricht'] = $meldung;
        $this->loescheTerminalMitarbeiterSession();
        header('Location: terminal.php?aktion=start');
        exit;
    }

    /**
     * POST-Handler zum Stoppen eines laufenden Auftrags.
     *
     * Die Auswahllogik (konkrete Auftragszeit-ID vs. Auftragscode vs. einzig laufender Auftrag)
     * wird im AuftragszeitService gekapselt.
     */
    public function auftragStoppen(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }


        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->auftragStoppenForm(null, 'Ungültiges Formular-Token (CSRF). Bitte erneut versuchen.');
            return;
        }

        // Regel: Auftrags-Stop ist nur sinnvoll, wenn der Mitarbeiter bereits anwesend ist.
        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestoppt werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $mitarbeiterId  = (int)$mitarbeiter['id'];
        $auftragszeitId = isset($_POST['auftragszeit_id']) && $_POST['auftragszeit_id'] !== '' ? (int)$_POST['auftragszeit_id'] : null;
        $auftragscode   = isset($_POST['auftragscode']) ? trim((string)$_POST['auftragscode']) : null;
        if ($auftragscode === '') {
            $auftragscode = null;
        }
        $status         = isset($_POST['status']) && $_POST['status'] === 'abgebrochen' ? 'abgebrochen' : 'abgeschlossen';

        // Hauptauftrag stoppen: Der Service stoppt ausschließlich typ='haupt' (kein zusätzlicher Typ-Parameter nötig).
        $res = $this->auftragszeitService->stoppeAuftrag($mitarbeiterId, $auftragszeitId, $auftragscode, $status);

        if ($res === null) {
            $this->auftragStoppenForm(null, 'Es wurde kein passender laufender Auftrag gefunden oder der Stopp ist fehlgeschlagen.');
            return;
        }


        // Session: Status des letzten Hauptauftrags lokal aktualisieren
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $codeFuerSession = $auftragscode;

                if ($codeFuerSession === null && $res === 1 && $auftragszeitId !== null && $auftragszeitId > 0) {
                    try {
                        $azModelTmp = new AuftragszeitModel();
                        $rowTmp = $azModelTmp->holeNachId($auftragszeitId);
                        if (is_array($rowTmp) && isset($rowTmp['auftragscode']) && is_string($rowTmp['auftragscode'])) {
                            $c = trim((string)$rowTmp['auftragscode']);
                            if ($c !== '') {
                                $codeFuerSession = $c;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }

                if ($codeFuerSession === null) {
                    $last = $_SESSION['terminal_letzter_auftrag'] ?? null;
                    if (is_array($last) && isset($last['auftragscode']) && is_string($last['auftragscode'])) {
                        $c = trim((string)$last['auftragscode']);
                        if ($c !== '') {
                            $codeFuerSession = $c;
                        }
                    }
                }

                if ($codeFuerSession !== null) {
                    $last = $_SESSION['terminal_letzter_auftrag'] ?? null;
                    $lastId = null;
                    if (is_array($last) && isset($last['auftragszeit_id'])) {
                        $lastId = (int)$last['auftragszeit_id'];
                    }

                    $_SESSION['terminal_letzter_auftrag'] = [
                        'auftragscode'     => $codeFuerSession,
                        'status'          => $status,
                        'typ'             => 'haupt',
                        'auftragszeit_id' => $auftragszeitId !== null ? (int)$auftragszeitId : (is_int($lastId) ? $lastId : 0),
                        'zeit'            => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // niemals Terminal-Flow blockieren
        }

        if ($res === 0) {
            $meldung = 'Hauptdatenbank offline – Auftrag-Stopp wurde in der Offline-Queue gespeichert.';
        } else {
            $meldung = $status === 'abgebrochen'
                ? 'Auftrag wurde abgebrochen.'
                : 'Auftrag wurde gestoppt.';
        }

        // Kiosk-Flow: nach erfolgreichem Stopp direkt abmelden und wieder zur RFID-Abfrage.
        $_SESSION['terminal_flash_nachricht'] = $meldung;
        $this->loescheTerminalMitarbeiterSession();
        header('Location: terminal.php?aktion=start');
        exit;
    }



    // ---------------------------------------------------------------------
    // Nebenauftrag (Terminal)
    // ---------------------------------------------------------------------

    /**
     * Nebenauftrag darf nur gestartet werden, wenn ein Hauptauftrag laeuft.
     *
     * - Online: prueft `auftragszeit` auf einen laufenden Eintrag mit typ='haupt'.
     * - Offline: Fallback ueber Session-Merker `terminal_letzter_auftrag`.
     */
    private function hatLaufendenHauptauftragFuerMitarbeiter(int $mitarbeiterId): bool
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return false;
        }

        // Offline: nur Session-Fallback moeglich
        if (!$this->istHauptdatenbankAktiv()) {
            try {
                if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['terminal_letzter_auftrag']) && is_array($_SESSION['terminal_letzter_auftrag'])) {
                    $last = $_SESSION['terminal_letzter_auftrag'];
                    $typ = isset($last['typ']) ? (string)$last['typ'] : '';
                    $status = isset($last['status']) ? (string)$last['status'] : '';
                    if ($typ === 'haupt' && $status === 'laufend') {
                        return true;
                    }
                }
            } catch (Throwable $e) {
                return false;
            }
            return false;
        }

        // Online: DB pruefen
        try {
            $m = new AuftragszeitModel();
            $laufende = $m->holeLaufendeFuerMitarbeiter($mitarbeiterId);
            if (!is_array($laufende) || count($laufende) === 0) {
                return false;
            }
            foreach ($laufende as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (($row['typ'] ?? '') === 'haupt' && ($row['status'] ?? '') === 'laufend' && empty($row['endzeit'])) {
                    return true;
                }
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Formular zum Starten eines Nebenauftrags (Terminal).
     *
     * Technische Umsetzung (MVP):
     * - Anzeige erfolgt auf der bestehenden Startseite (views/terminal/start.php),
     *   weil dort bereits alle Status-/Timeout-Mechaniken zentral sind.
     */
    public function nebenauftragStartenForm(?string $meldung = null, ?string $fehlermeldung = null, ?array $formular = null): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }


        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestartet werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        // Regel: Nebenauftrag nur, wenn ein Hauptauftrag laeuft (serverseitig absichern)
        if (!$this->hatLaufendenHauptauftragFuerMitarbeiter((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Nebenauftrag ist nur moeglich, wenn ein Hauptauftrag laeuft.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = $meldung;
        $fehlerText = $fehlermeldung;

        $zeigeNebenauftragStartFormular = true;
        $nebenauftragFormular = is_array($formular) ? $formular : [
            'auftragscode' => '',
            'arbeitsschritt_code' => '',
            'maschine_id'  => '',
        ];

        // Startseite benötigt diese Basisdaten ebenfalls (Übersichten/Listen)
        $mitarbeiterId = (int)$mitarbeiter['id'];
        $zeigeMicroBuchungen = ((string)($_GET['show_micro'] ?? '') === '1');
        $heute = $this->holeHeutigeZeitUebersicht($mitarbeiterId, $zeigeMicroBuchungen);
        $heuteDatum = $heute['datum'] ?? null;
        $heuteBuchungen = $heute['buchungen'] ?? [];
        $heuteFehler = $heute['fehler'] ?? null;

        // Laufende Aufträge sind nur online zuverlässig abrufbar.
        $laufendeAuftraege = [];
        if ($this->istHauptdatenbankAktiv()) {
            try {
                $auftragszeitModel = new AuftragszeitModel();
                $laufendeAuftraege = $auftragszeitModel->holeLaufendeFuerMitarbeiter($mitarbeiterId);
            } catch (Throwable $e) {
                $laufendeAuftraege = [];
            }
        }

        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');
        $csrfToken = $this->holeOderErzeugeCsrfToken();
        // Monatsstatus (fuer Mitarbeiterpanel am Startscreen im Nebenauftrag-Flow).
        $monatsStatus = null;
        if ($this->istHauptdatenbankAktiv() && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }




        require __DIR__ . '/../views/terminal/start.php';
    }

    /**
     * POST-Handler zum Starten eines Nebenauftrags.
     */
    public function nebenauftragStarten(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->nebenauftragStartenForm(null, 'Ungültiges Formular-Token (CSRF). Bitte erneut versuchen.', [
                'auftragscode' => isset($_POST['auftragscode']) ? (string)$_POST['auftragscode'] : '',
                'arbeitsschritt_code' => isset($_POST['arbeitsschritt_code']) ? (string)$_POST['arbeitsschritt_code'] : '',
                'maschine_id'  => isset($_POST['maschine_id']) ? (string)$_POST['maschine_id'] : '',
            ]);
            return;
        }


        
                if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
                    $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Auftrag gestartet werden kann.';
                    header('Location: terminal.php?aktion=start');
                    exit;
                }

                // Regel: Nebenauftrag nur, wenn ein Hauptauftrag laeuft (serverseitig absichern)
                if (!$this->hatLaufendenHauptauftragFuerMitarbeiter((int)$mitarbeiter['id'])) {
                    $_SESSION['terminal_flash_fehler'] = 'Nebenauftrag ist nur moeglich, wenn ein Hauptauftrag laeuft.';
                    header('Location: terminal.php?aktion=start');
                    exit;
                }
        
                $mitarbeiterId = (int)$mitarbeiter['id'];
        $auftragscode  = isset($_POST['auftragscode']) ? trim((string)$_POST['auftragscode']) : '';
        $arbeitsschrittCode = isset($_POST['arbeitsschritt_code']) ? trim((string)$_POST['arbeitsschritt_code']) : '';
        $maschineRaw   = isset($_POST['maschine_id']) ? trim((string)$_POST['maschine_id']) : '';
        $maschineId    = $this->parseMaschineIdAusScan($maschineRaw);

        if ($auftragscode === '') {
            $this->nebenauftragStartenForm(null, 'Bitte einen Auftragscode eingeben.', [
                'auftragscode' => '',
                'arbeitsschritt_code' => isset($_POST['arbeitsschritt_code']) ? (string)$_POST['arbeitsschritt_code'] : '',
                'maschine_id'  => isset($_POST['maschine_id']) ? (string)$_POST['maschine_id'] : '',
            ]);
            return;
        }

        // Auftragsmodul v1: Arbeitsschritt ist Pflicht (Zeit wird pro Arbeitsschritt erfasst)
        if ($arbeitsschrittCode === '') {
            $this->nebenauftragStartenForm(null, 'Bitte einen Arbeitsschritt-Code eingeben.', [
                'auftragscode' => $auftragscode,
                'arbeitsschritt_code' => '',
                'maschine_id'  => isset($_POST['maschine_id']) ? (string)$_POST['maschine_id'] : '',
            ]);
            return;
        }

        $res = $this->starteNebenauftrag($mitarbeiterId, $auftragscode, $maschineId, $arbeitsschrittCode);
        if ($res === null) {
            $this->nebenauftragStartenForm(null, 'Nebenauftrag konnte nicht gestartet werden. Bitte erneut versuchen.', [
                'auftragscode' => $auftragscode,
                'arbeitsschritt_code' => (string)$arbeitsschrittCode,
                'maschine_id'  => $maschineId === null ? '' : (string)$maschineId,
            ]);
            return;
        }


        // Session-Merker: Nebenauftrag (fuer UI-Logik im Offline-Betrieb)
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $cnt = $_SESSION['terminal_nebenauftrag_laufend_count'] ?? 0;
                if (!is_int($cnt)) {
                    $cnt = is_string($cnt) && ctype_digit($cnt) ? (int)$cnt : 0;
                }
                $cnt = max(0, (int)$cnt);
                $cnt = min(10, $cnt + 1);
                $_SESSION['terminal_nebenauftrag_laufend_count'] = $cnt;
                $_SESSION['terminal_letzter_nebenauftrag'] = [
                    'auftragscode'        => $auftragscode,
                    'arbeitsschritt_code' => $arbeitsschrittCode,
                    'status'             => 'laufend',
                    'typ'                => 'neben',
                    'auftragszeit_id'    => (int)$res,
                    'zeit'               => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ];
            }
        } catch (\Throwable $e) {
            // niemals Terminal-Flow blockieren
        }
        $meldung = $res === 0
            ? 'Hauptdatenbank offline – Nebenauftrag wurde in der Offline-Queue gespeichert.'
            : 'Nebenauftrag wurde gestartet (Auftragszeit-ID: ' . (int)$res . ').';

        // Kiosk-Flow: nach erfolgreichem Start direkt abmelden und wieder zur RFID-Abfrage.
        $_SESSION['terminal_flash_nachricht'] = $meldung;
        $this->loescheTerminalMitarbeiterSession();
        header('Location: terminal.php?aktion=start');
        exit;
    }

    /**
     * Formular zum Stoppen eines Nebenauftrags (Terminal).
     */
    public function nebenauftragStoppenForm(?string $meldung = null, ?string $fehlermeldung = null): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }

        // Regel: Nebenauftrag-Stop ist nur sinnvoll, wenn der Mitarbeiter bereits anwesend ist.
        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Nebenauftrag gestoppt werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = $meldung;
        $fehlerText = $fehlermeldung;

        $zeigeNebenauftragStopFormular = true;
        $nebenauftragStopOfflineModus = false;

        $mitarbeiterId = (int)$mitarbeiter['id'];
        $zeigeMicroBuchungen = ((string)($_GET['show_micro'] ?? '') === '1');
        $heute = $this->holeHeutigeZeitUebersicht($mitarbeiterId, $zeigeMicroBuchungen);
        $heuteDatum = $heute['datum'] ?? null;
        $heuteBuchungen = $heute['buchungen'] ?? [];
        $heuteFehler = $heute['fehler'] ?? null;

        $laufendeNebenauftraege = [];
        if ($this->istHauptdatenbankAktiv()) {
            try {
                $auftragszeitModel = new AuftragszeitModel();
                $laufendeAuftraege = $auftragszeitModel->holeLaufendeFuerMitarbeiter($mitarbeiterId);
                $laufendeNebenauftraege = array_values(array_filter($laufendeAuftraege, static function ($row): bool {
                    return is_array($row) && (($row['typ'] ?? '') === 'neben');
                }));
            } catch (Throwable $e) {
                $laufendeNebenauftraege = [];
            }
        } else {
            // Offline: wir können nicht zuverlässig prüfen, ob ein Nebenauftrag läuft.
            // Stattdessen erlauben wir ein manuelles Stoppen per Auftragscode.
            $nebenauftragStopOfflineModus = true;
        }

        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');
        $csrfToken = $this->holeOderErzeugeCsrfToken();
        // Monatsstatus (fuer Mitarbeiterpanel am Startscreen im Nebenauftrag-Stop-Flow).
        $monatsStatus = null;
        if ($this->istHauptdatenbankAktiv() && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }



        require __DIR__ . '/../views/terminal/start.php';
    }

    /**
     * POST-Handler zum Stoppen eines Nebenauftrags.
     */
    public function nebenauftragStoppen(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->nebenauftragStoppenForm(null, 'Ungültiges Formular-Token (CSRF). Bitte erneut versuchen.');
            return;
        }

        // Regel: Nebenauftrag-Stop ist nur sinnvoll, wenn der Mitarbeiter bereits anwesend ist.
        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen, bevor ein Nebenauftrag gestoppt werden kann.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $mitarbeiterId  = (int)$mitarbeiter['id'];
        $auftragszeitId = isset($_POST['auftragszeit_id']) && $_POST['auftragszeit_id'] !== '' ? (int)$_POST['auftragszeit_id'] : null;
        $auftragscode   = isset($_POST['auftragscode']) ? trim((string)$_POST['auftragscode']) : '';

        // Status-Auswahl ist im Terminal-Flow bewusst entfernt.
        // Wir erfassen nur "gestoppt" (Endzeit) und speichern intern als "abgeschlossen".
        $status = 'abgeschlossen';

        // Wenn ein Auftragscode gescannt wurde, hat dieser Vorrang (z.B. bei mehreren Nebenauftraegen).
        // In dem Fall ignorieren wir eine ggf. vorausgewaehlte Auftragszeit-ID aus dem Dropdown.
        if ($auftragscode !== '') {
            $auftragszeitId = null;
        }

        // Offline: Ohne DB können wir nicht listen – daher verlangen wir hier einen Auftragscode,
        // damit nicht "irgendein" Nebenauftrag gestoppt wird.
        if (!$this->istHauptdatenbankAktiv() && ($auftragszeitId === null || $auftragszeitId <= 0) && $auftragscode === '') {
            $this->nebenauftragStoppenForm(null, 'Hauptdatenbank offline: Bitte Auftragscode scannen, um einen Nebenauftrag zu stoppen.');
            return;
        }

        $res = $this->stoppeNebenauftrag($mitarbeiterId, $auftragszeitId, $auftragscode !== '' ? $auftragscode : null, $status);
        if ($res === null) {
            $this->nebenauftragStoppenForm(null, 'Es wurde kein passender laufender Nebenauftrag gefunden oder der Stopp ist fehlgeschlagen.');
            return;
        }


        // Session-Merker: Nebenauftrag ggf. als beendet markieren (Offline-UI)
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $cnt = $_SESSION['terminal_nebenauftrag_laufend_count'] ?? 0;
                if (!is_int($cnt)) {
                    $cnt = is_string($cnt) && ctype_digit($cnt) ? (int)$cnt : 0;
                }
                $cnt = max(0, (int)$cnt);
                if ($cnt > 0) {
                    $cnt--;
                }
                if ($cnt <= 0) {
                    unset($_SESSION['terminal_nebenauftrag_laufend_count']);
                } else {
                    $_SESSION['terminal_nebenauftrag_laufend_count'] = $cnt;
                }
                // Letzten Nebenauftrag als beendet markieren (Info)
                if (isset($_SESSION['terminal_letzter_nebenauftrag']) && is_array($_SESSION['terminal_letzter_nebenauftrag'])) {
                    $_SESSION['terminal_letzter_nebenauftrag']['status'] = $status;
                    $_SESSION['terminal_letzter_nebenauftrag']['endzeit'] = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
                }
            }
        } catch (\Throwable $e) {
            // niemals Terminal-Flow blockieren
        }
        $meldung = $res === 0
            ? 'Hauptdatenbank offline – Nebenauftrag-Stopp wurde in der Offline-Queue gespeichert.'
            : 'Nebenauftrag wurde gestoppt.';

        // Kiosk-Flow: nach erfolgreichem Stopp direkt abmelden und wieder zur RFID-Abfrage.
        $_SESSION['terminal_flash_nachricht'] = $meldung;
        $this->loescheTerminalMitarbeiterSession();
        header('Location: terminal.php?aktion=start');
        exit;
    }

    // ---------------------------------------------------------------------
    // Nebenauftrag – interne Logik (inkl. Offline-Queue)
    // ---------------------------------------------------------------------

    /**
     * Startet einen Nebenauftrag online oder schreibt ihn offline in die Queue.
     *
     * @return int|null
     *  - >0  neue auftragszeit.id (online)
     *  - 0   offline gespeichert (Queue)
     *  - null Fehler
     */
    private function starteNebenauftrag(int $mitarbeiterId, string $auftragscode, ?int $maschineId, ?string $arbeitsschrittCode = null): ?int
    {
        $mitarbeiterId = max(1, $mitarbeiterId);
        $auftragscode = trim($auftragscode);
        $arbeitsschrittCode = $arbeitsschrittCode !== null ? trim((string)$arbeitsschrittCode) : null;
        if ($arbeitsschrittCode === '') {
            $arbeitsschrittCode = null;
        }
        if ($mitarbeiterId <= 0 || $auftragscode === '') {
            return null;
        }

        $jetzt = new DateTimeImmutable('now');

        // Offline: nur in Queue schreiben
        if (!$this->istHauptdatenbankAktiv()) {
            if (!class_exists('OfflineQueueManager')) {
                return null;
            }

            // Auftrag (Minimaldatensatz) sicherstellen, damit die Buchung spaeter aufloesbar ist
            // (analog Hauptauftrag-Start in AuftragszeitService).
            $sqlEnsureAuftrag = 'INSERT INTO auftrag (auftragsnummer, aktiv) VALUES ('
                . $this->sqlString($auftragscode) . ', 1) '
                . 'ON DUPLICATE KEY UPDATE auftragsnummer = auftragsnummer';

            $sql = 'INSERT INTO auftragszeit (mitarbeiter_id, auftrag_id, auftragscode, arbeitsschritt_code, maschine_id, terminal_id, typ, startzeit, kommentar) VALUES ('
                . $this->sqlInt($mitarbeiterId) . ', '
                . 'NULL, '
                . $this->sqlString($auftragscode) . ', '
                . ($arbeitsschrittCode === null ? 'NULL' : $this->sqlString($arbeitsschrittCode)) . ', '
                . $this->sqlNullableInt($maschineId) . ', '
                . 'NULL, '
                . $this->sqlString('neben') . ', '
                . $this->sqlString($jetzt->format('Y-m-d H:i:s')) . ', '
                . 'NULL'
                . ')';

            try {
                OfflineQueueManager::getInstanz()->speichereInQueue($sqlEnsureAuftrag, $mitarbeiterId, null, 'auftrag_ensure');
                OfflineQueueManager::getInstanz()->speichereInQueue($sql, $mitarbeiterId, null, 'nebenauftrag_start');
                return 0;
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Terminal: Nebenauftrag konnte nicht in Offline-Queue geschrieben werden', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'auftragscode'   => $auftragscode,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, null, 'terminal_nebenauftrag');
                }
                return null;
            }
        }

        // Online: Auftrag ggf. in Minimal-Tabelle anlegen/finden
        $auftragId = null;
        try {
            $auftragModel = new AuftragModel();
            $auftrag = $auftragModel->findeNachCode($auftragscode);
            if (is_array($auftrag) && isset($auftrag['id'])) {
                $auftragId = (int)$auftrag['id'];
            }
        } catch (Throwable $e) {
            $auftragId = null;
        }

        // Falls nicht vorhanden: Minimaldatensatz anlegen (idempotent)
        if ($auftragId === null && class_exists('Database')) {
            try {
                $dbEnsure = Database::getInstanz();
                $dbEnsure->ausfuehren(
                    'INSERT INTO auftrag (auftragsnummer, aktiv) VALUES (:nr, 1)
                     ON DUPLICATE KEY UPDATE auftragsnummer = auftragsnummer',
                    ['nr' => $auftragscode]
                );

                $auftragModel = new AuftragModel();
                $auftrag = $auftragModel->findeNachCode($auftragscode);
                if (is_array($auftrag) && isset($auftrag['id'])) {
                    $auftragId = (int)$auftrag['id'];
                }
            } catch (Throwable $e) {
                // Nicht blockieren: dann bleibt auftrag_id NULL, auftragscode ist trotzdem gespeichert.
            }
        }

        try {
            $auftragszeitModel = new AuftragszeitModel();
            $neueId = $auftragszeitModel->erstelleAuftragszeit(
                $mitarbeiterId,
                $auftragId,
                $auftragscode,
                $maschineId,
                null,
                'neben',
                $jetzt,
                null
            );

            if ($neueId !== null && $neueId > 0 && $arbeitsschrittCode !== null && class_exists('Database')) {
                try {
                    $db = Database::getInstanz();
                    $db->ausfuehren('UPDATE auftragszeit SET arbeitsschritt_code = :c WHERE id = :id', [
                        'c' => $arbeitsschrittCode,
                        'id' => (int)$neueId,
                    ]);
                } catch (Throwable $e) {
                    // Soft-fail: Nebenauftrag darf nicht blockieren
                }
            }

            return $neueId;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Terminal: Nebenauftrag konnte nicht gestartet werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'auftragscode'   => $auftragscode,
                    'maschine_id'    => $maschineId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal_nebenauftrag');
            }
            return null;
        }
    }

    /**
     * Stoppt einen Nebenauftrag online oder schreibt den Stopp offline in die Queue.
     *
     * @return int|null
     *  - 1   online gestoppt
     *  - 0   offline gespeichert (Queue)
     *  - null Fehler
     */
    private function stoppeNebenauftrag(int $mitarbeiterId, ?int $auftragszeitId, ?string $auftragscode, string $status): ?int
    {
        $mitarbeiterId = max(1, $mitarbeiterId);
        $auftragszeitId = $auftragszeitId !== null ? max(1, (int)$auftragszeitId) : null;
        $auftragscode = $auftragscode !== null ? trim($auftragscode) : null;
        if ($auftragscode === '') {
            $auftragscode = null;
        }

        $status = in_array($status, ['abgeschlossen', 'abgebrochen'], true) ? $status : 'abgeschlossen';
        $jetzt = new DateTimeImmutable('now');

        // Offline: nur in Queue schreiben
        if (!$this->istHauptdatenbankAktiv()) {
            if (!class_exists('OfflineQueueManager')) {
                return null;
            }

            $sql = 'UPDATE auftragszeit SET endzeit = ' . $this->sqlString($jetzt->format('Y-m-d H:i:s')) . ', status = ' . $this->sqlString($status)
                . ' WHERE mitarbeiter_id = ' . $this->sqlInt($mitarbeiterId)
                . ' AND typ = ' . $this->sqlString('neben')
                . ' AND status = ' . $this->sqlString('laufend')
                . ' AND endzeit IS NULL';

            if ($auftragszeitId !== null && $auftragszeitId > 0) {
                $sql .= ' AND id = ' . $this->sqlInt($auftragszeitId);
            } elseif ($auftragscode !== null) {
                $sql .= ' AND auftragscode = ' . $this->sqlString($auftragscode);
                $sql .= ' ORDER BY startzeit DESC, id DESC LIMIT 1';
            } else {
                // Fallback: letzter laufender Nebenauftrag
                $sql .= ' ORDER BY startzeit DESC, id DESC LIMIT 1';
            }

            try {
                OfflineQueueManager::getInstanz()->speichereInQueue($sql, $mitarbeiterId, null, 'nebenauftrag_stop');
                return 0;
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Terminal: Nebenauftrag-Stopp konnte nicht in Offline-Queue geschrieben werden', [
                        'mitarbeiter_id'  => $mitarbeiterId,
                        'auftragszeit_id' => $auftragszeitId,
                        'auftragscode'    => $auftragscode,
                        'exception'       => $e->getMessage(),
                    ], $mitarbeiterId, null, 'terminal_nebenauftrag');
                }
                return null;
            }
        }

        // Online: passenden Nebenauftrag bestimmen
        $auftragszeitModel = new AuftragszeitModel();

        $zielId = null;
        if ($auftragszeitId !== null && $auftragszeitId > 0) {
            $row = $auftragszeitModel->holeNachId($auftragszeitId);
            if (is_array($row)
                && (int)($row['mitarbeiter_id'] ?? 0) === $mitarbeiterId
                && (($row['typ'] ?? '') === 'neben')
                && (($row['status'] ?? '') === 'laufend')
                && empty($row['endzeit'])) {
                $zielId = $auftragszeitId;
            }
        }

        if ($zielId === null) {
            $laufende = $auftragszeitModel->holeLaufendeFuerMitarbeiter($mitarbeiterId);
            $laufendeNeben = array_values(array_filter($laufende, static function ($r): bool {
                return is_array($r) && (($r['typ'] ?? '') === 'neben');
            }));

            if ($auftragscode !== null) {
                foreach ($laufendeNeben as $r) {
                    $code = isset($r['auftragscode']) ? (string)$r['auftragscode'] : '';
                    if ($code !== '' && strcasecmp($code, $auftragscode) === 0) {
                        $zielId = isset($r['id']) ? (int)$r['id'] : null;
                        break;
                    }
                }
            }

            if ($zielId === null && count($laufendeNeben) === 1) {
                $zielId = isset($laufendeNeben[0]['id']) ? (int)$laufendeNeben[0]['id'] : null;
            }
        }

        if ($zielId === null || $zielId <= 0) {
            return null;
        }

        $ok = $auftragszeitModel->beendeAuftragszeit($zielId, $jetzt, $status);
        return $ok ? 1 : null;
    }

    // ---------------------------------------------------------------------
    // Mini-SQL-Helper für Offline-Queue (keine Prepared-Statements möglich)
    // ---------------------------------------------------------------------

    private function sqlString(string $val): string
    {
        return "'" . str_replace("'", "''", $val) . "'";
    }

    private function sqlInt(int $val): string
    {
        return (string)max(0, (int)$val);
    }

    private function sqlNullableInt(?int $val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        return $this->sqlInt($val);
    }




    // ---------------------------------------------------------------------
    // RFID-Chip zu Mitarbeiter zuweisen (Terminal-Admin)
    // ---------------------------------------------------------------------

    /**
     * Formular: RFID-Chip zu Mitarbeiter zuweisen.
     *
     * Nur für Terminal-Admins (Recht: MITARBEITER_VERWALTEN oder Superuser).
     * Nur online (Hauptdatenbank muss erreichbar sein).
     */
    public function rfidZuweisenForm(?string $fehlermeldung = null, ?array $formular = null): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst am Terminal anmelden (RFID).';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $darf = (bool)($mitarbeiter['darf_rfid_zuweisen'] ?? ($_SESSION['terminal_darf_rfid_zuweisen'] ?? false));
        if (!$darf) {
            $_SESSION['terminal_flash_fehler'] = 'Keine Berechtigung für RFID-Zuweisung.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        if (!$this->istHauptdatenbankAktiv()) {
            $_SESSION['terminal_flash_fehler'] = 'Hauptdatenbank offline – RFID-Zuweisung ist nur online möglich.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = null;
        $fehlerText = null;
        if (isset($_SESSION['terminal_flash_nachricht'])) {
            $nachricht = (string)$_SESSION['terminal_flash_nachricht'];
            unset($_SESSION['terminal_flash_nachricht']);
        }
        if (isset($_SESSION['terminal_flash_fehler'])) {
            $fehlerText = (string)$_SESSION['terminal_flash_fehler'];
            unset($_SESSION['terminal_flash_fehler']);
        }
        if (is_string($fehlermeldung) && trim($fehlermeldung) !== '') {
            $fehlerText = $fehlermeldung;
        }

        $zeigeRfidZuweisenFormular = true;
        $rfidZuweisenFormular = is_array($formular) ? $formular : [
            'ziel_mitarbeiter_id' => '',
            'rfid_code'           => '',
        ];



        if (!isset($rfidZuweisenFormular['rfid_code']) || trim((string)$rfidZuweisenFormular['rfid_code']) === '') {
            $prefill = '';
            if (isset($_GET['prefill_rfid'])) {
                $prefill = trim((string)$_GET['prefill_rfid']);
            }
            if ($prefill === '' && isset($_SESSION['terminal_last_unknown_rfid'])) {
                $prefill = trim((string)$_SESSION['terminal_last_unknown_rfid']);
            }
            if ($prefill !== '') {
                $rfidZuweisenFormular['rfid_code'] = $prefill;
            }
        }

        $rfidZuweisenMitarbeiterListe = [];
        try {
            $rfidZuweisenMitarbeiterListe = $this->datenbank->fetchAlle(
                "SELECT id, vorname, nachname, personalnummer FROM mitarbeiter WHERE aktiv = 1 ORDER BY nachname, vorname"
            );
        } catch (Throwable $e) {
            $rfidZuweisenMitarbeiterListe = [];
        }

        // Startseite benötigt diese Basisdaten ebenfalls (Übersichten/Listen)
        $mitarbeiterId = (int)$mitarbeiter['id'];
        $zeigeMicroBuchungen = ((string)($_GET['show_micro'] ?? '') === '1');
        $heute = $this->holeHeutigeZeitUebersicht($mitarbeiterId, $zeigeMicroBuchungen);
        $heuteDatum = $heute['datum'] ?? null;
        $heuteBuchungen = $heute['buchungen'] ?? [];
        $heuteFehler = $heute['fehler'] ?? null;

        $laufendeAuftraege = [];
        try {
            $auftragszeitModel = new AuftragszeitModel();
            $laufendeAuftraege = $auftragszeitModel->holeLaufendeFuerMitarbeiter($mitarbeiterId);
        } catch (Throwable $e) {
            $laufendeAuftraege = [];
        }

        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('standard');
        $csrfToken = $this->holeOderErzeugeCsrfToken();

        // Monatsstatus (Mitarbeiterpanel): Soll Monat / Soll bis heute / IST bis heute (nur online).
        $monatsStatus = null;
        if ($this->istHauptdatenbankAktiv() && isset($mitarbeiter['id'])) {
            $monatsStatus = $this->berechneMonatsStatusFuerMitarbeiter((int)$mitarbeiter['id']);
        }

        // Urlaub-Box auf der Startseite (nur Anzeige) – laden wir nur online.
        $urlaubSaldo = null;
        $urlaubSaldoFehler = null;
        $urlaubJahr = (int)(new DateTimeImmutable('now'))->format('Y');
        try {
            $urlaubService = UrlaubService::getInstanz();
            $urlaubSaldo = $urlaubService->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $urlaubJahr);
        } catch (Throwable $e) {
            $urlaubSaldo = null;
            $urlaubSaldoFehler = 'Urlaubssaldo konnte nicht geladen werden.';
        }

        require __DIR__ . '/../views/terminal/start.php';
    }

    /**
     * POST-Handler: RFID-Chip zu Mitarbeiter zuweisen.
     */
    public function rfidZuweisen(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst am Terminal anmelden (RFID).';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $darf = (bool)($mitarbeiter['darf_rfid_zuweisen'] ?? ($_SESSION['terminal_darf_rfid_zuweisen'] ?? false));
        if (!$darf) {
            $_SESSION['terminal_flash_fehler'] = 'Keine Berechtigung für RFID-Zuweisung.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        if (!$this->istHauptdatenbankAktiv()) {
            $_SESSION['terminal_flash_fehler'] = 'Hauptdatenbank offline – RFID-Zuweisung ist nur online möglich.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->rfidZuweisenForm('Ungültiges Formular-Token (CSRF). Bitte erneut versuchen.', [
                'ziel_mitarbeiter_id' => isset($_POST['ziel_mitarbeiter_id']) ? (string)$_POST['ziel_mitarbeiter_id'] : '',
                'rfid_code'           => isset($_POST['rfid_code']) ? (string)$_POST['rfid_code'] : '',
            ]);
            return;
        }

        $zielMitarbeiterId = isset($_POST['ziel_mitarbeiter_id']) ? (int)$_POST['ziel_mitarbeiter_id'] : 0;
        $rfidCode = isset($_POST['rfid_code']) ? trim((string)$_POST['rfid_code']) : '';

        if ($zielMitarbeiterId <= 0) {
            $this->rfidZuweisenForm('Bitte einen Mitarbeiter auswählen.', [
                'ziel_mitarbeiter_id' => isset($_POST['ziel_mitarbeiter_id']) ? (string)$_POST['ziel_mitarbeiter_id'] : '',
                'rfid_code'           => $rfidCode,
            ]);
            return;
        }
        if ($rfidCode === '') {
            $this->rfidZuweisenForm('Bitte RFID-Code scannen/eingeben.', [
                'ziel_mitarbeiter_id' => (string)$zielMitarbeiterId,
                'rfid_code'           => '',
            ]);
            return;
        }

        try {
            $ziel = $this->datenbank->fetchEine(
                'SELECT id, vorname, nachname, personalnummer FROM mitarbeiter WHERE id = :id AND aktiv = 1 LIMIT 1',
                ['id' => $zielMitarbeiterId]
            );
            if (!is_array($ziel) || empty($ziel)) {
                $this->rfidZuweisenForm('Der ausgewählte Mitarbeiter existiert nicht (oder ist nicht aktiv).', [
                    'ziel_mitarbeiter_id' => (string)$zielMitarbeiterId,
                    'rfid_code'           => $rfidCode,
                ]);
                return;
            }

            $zielName = trim((string)($ziel['vorname'] ?? '') . ' ' . (string)($ziel['nachname'] ?? ''));
            if ($zielName === '') {
                $zielName = 'ID ' . $zielMitarbeiterId;
            }
            $zielPn = trim((string)($ziel['personalnummer'] ?? ''));
            $zielZusatz = $zielPn !== '' ? ' (PN: ' . $zielPn . ')' : '';

            $bereits = $this->datenbank->fetchEine(
                'SELECT id, vorname, nachname FROM mitarbeiter WHERE rfid_code = :code AND aktiv = 1 LIMIT 1',
                ['code' => $rfidCode]
            );
            if (is_array($bereits) && !empty($bereits)) {
                $bereitsId = (int)($bereits['id'] ?? 0);
                if ($bereitsId > 0 && $bereitsId !== $zielMitarbeiterId) {
                    $name = trim((string)($bereits['vorname'] ?? '') . ' ' . (string)($bereits['nachname'] ?? ''));
                    $this->rfidZuweisenForm('Dieser RFID-Code ist bereits zugewiesen an: ' . ($name !== '' ? $name : ('ID ' . $bereitsId)) . '.', [
                        'ziel_mitarbeiter_id' => (string)$zielMitarbeiterId,
                        'rfid_code'           => $rfidCode,
                    ]);
                    return;
                }
            }

            $this->datenbank->ausfuehren(
                'UPDATE mitarbeiter SET rfid_code = :code WHERE id = :id',
                ['code' => $rfidCode, 'id' => $zielMitarbeiterId]
            );

            if (class_exists('Logger')) {
                Logger::info('Terminal: RFID-Code zugewiesen', [
                    'ziel_mitarbeiter_id'  => $zielMitarbeiterId,
                    'ziel_name'            => $zielName,
                    'ziel_personalnummer'  => $zielPn,
                    'rfid_code'            => $rfidCode,
                ], (int)$mitarbeiter['id'], null, 'terminal_admin');
            }

            $_SESSION['terminal_flash_nachricht'] = 'RFID-Code wurde zugewiesen an: ' . $zielName . $zielZusatz . '.';
            unset($_SESSION['terminal_last_unknown_rfid'], $_SESSION['terminal_last_unknown_rfid_ts']);
            // Nach erfolgreichem Speichern wieder auf den normalen Kommen/Gehen-Startscreen.
            header('Location: terminal.php?aktion=start');
            exit;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Terminal: RFID-Zuweisung fehlgeschlagen', [
                    'ziel_mitarbeiter_id' => $zielMitarbeiterId,
                    'rfid_code'           => $rfidCode,
                    'exception'           => $e->getMessage(),
                ], (int)$mitarbeiter['id'], null, 'terminal_admin');
            }

            $this->rfidZuweisenForm('RFID-Zuweisung fehlgeschlagen. Bitte erneut versuchen.', [
                'ziel_mitarbeiter_id' => (string)$zielMitarbeiterId,
                'rfid_code'           => $rfidCode,
            ]);
        }
    }




    /**
     * Urlaub beantragen – Formular (Terminal).
     *
     * Hinweis:
     * - Nur online (Hauptdatenbank erreichbar).
     * - Auto-Logout Timeout ist länger (Kontext: "urlaub").
     */
    public function urlaubBeantragenForm(?string $fehlermeldung = null, ?array $formular = null): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst am Terminal anmelden (RFID).';
            header('Location: terminal.php?aktion=start');
            return;
        }

        if (!$this->istHauptdatenbankAktiv()) {
            $_SESSION['terminal_flash_fehler'] = 'Hauptdatenbank nicht erreichbar – Urlaub am Terminal nicht möglich.';
            header('Location: terminal.php?aktion=start');
            return;
        }

        // Urlaub kann unabhängig von der heutigen Anwesenheit beantragt werden
        // (z. B. für zukünftige Tage, auch wenn man heute bereits gearbeitet hat).

        $nachricht  = null;
        $fehlerText = $fehlermeldung;

        // Terminal: Urlaub ist jetzt zweistufig (Übersicht -> Antrag).
        // - Standard (GET): Übersicht
        // - Antrag (GET): ?modus=antrag
        // - Bei Validierungsfehlern (POST) soll automatisch das Formular angezeigt werden.
        $modus = isset($_GET['modus']) ? trim((string)$_GET['modus']) : '';
        if ($modus !== 'antrag' && ($fehlermeldung !== null || $formular !== null)) {
            $modus = 'antrag';
        }

        $zeigeUrlaubUebersicht = ($modus !== 'antrag');
        $zeigeUrlaubFormular   = ($modus === 'antrag');
        $urlaubModus           = $modus;

        $urlaubFormular = $formular ?? [
            'von_datum'             => '',
            'bis_datum'             => '',
            'kommentar_mitarbeiter' => '',
        ];


        $csrfToken = $this->holeOderErzeugeCsrfToken();

        // Letzte Anträge anzeigen (T-012)
        $urlaubsantraege = [];
        if (isset($mitarbeiter['id'])) {
            $urlaubsantraege = $this->holeEigeneUrlaubsantraege((int)$mitarbeiter['id'], 12);
        }


        // Urlaubssaldo (aktuelles Jahr) + Vorschau (verfügbar / nach Antrag).
$urlaubJahr = (int)(new DateTimeImmutable('now'))->format('Y');

// Betriebsferien (Anzeige in "Urlaub Übersicht")
$betriebsferienListe = [];
try {
    $jahrStart = sprintf('%04d-01-01', $urlaubJahr);
    $jahrEnd   = sprintf('%04d-12-31', $urlaubJahr);

    $betriebsferienListe = $this->datenbank->fetchAlle(
        'SELECT von_datum, bis_datum, beschreibung
         FROM betriebsferien
         WHERE aktiv = 1
           AND bis_datum >= :start
           AND von_datum <= :ende
         ORDER BY von_datum ASC, bis_datum ASC, id ASC',
        ['start' => $jahrStart, 'ende' => $jahrEnd]
    );
} catch (Throwable $e) {
    $betriebsferienListe = [];
}

$urlaubSaldo = null;

        $urlaubSaldoFehler = null;
        $urlaubVorschau = null;

        try {
            $urlaubService = UrlaubService::getInstanz();
            $urlaubSaldo = $urlaubService->berechneUrlaubssaldoFuerJahr((int)$mitarbeiter['id'], $urlaubJahr);

            // Betriebsferien: "benötigte Urlaubstage" für das aktuell angezeigte Jahr berechnen.
            // Hintergrund: Betriebsferien können über den Jahreswechsel gehen (z. B. Dez→Jan). In der Urlaub-Übersicht
            // soll deshalb die Anzahl der Arbeitstage innerhalb des angezeigten Jahres ausgewiesen werden.
            if (!empty($betriebsferienListe)) {
                $jahrStartDt = DateTimeImmutable::createFromFormat('Y-m-d', $jahrStart) ?: new DateTimeImmutable($jahrStart);
                $jahrEndDt   = DateTimeImmutable::createFromFormat('Y-m-d', $jahrEnd) ?: new DateTimeImmutable($jahrEnd);

                foreach ($betriebsferienListe as $i => $bf) {
                    $bfVon = isset($bf['von_datum']) ? (string)$bf['von_datum'] : '';
                    $bfBis = isset($bf['bis_datum']) ? (string)$bf['bis_datum'] : '';

                    $tage = null;

                    if ($bfVon !== '' && $bfBis !== '') {
                        try {
                            $vonDt = DateTimeImmutable::createFromFormat('Y-m-d', $bfVon) ?: new DateTimeImmutable($bfVon);
                            $bisDt = DateTimeImmutable::createFromFormat('Y-m-d', $bfBis) ?: new DateTimeImmutable($bfBis);

                            // Schnittmenge mit dem angezeigten Jahr bilden (damit Jahreswechsel sauber ist).
                            $calcVon = ($vonDt < $jahrStartDt) ? $jahrStartDt : $vonDt;
                            $calcBis = ($bisDt > $jahrEndDt) ? $jahrEndDt : $bisDt;

                            if ($calcVon->getTimestamp() <= $calcBis->getTimestamp()) {
                                // Für Betriebsferien brauchen wir die Anzahl der Tage, die für diesen Mitarbeiter
                                // tatsächlich als Zwangsurlaub zählen.
                                // Das muss 1:1 zu UrlaubService::berechneUrlaubssaldoFuerJahr passen:
                                // - Wochenenden und betriebsfreie Feiertage zählen nicht.
                                // - Wenn an einem BF-Tag gearbeitet wurde (oder andere Kennzeichen/Krankzeitraum greifen),
                                //   darf der Tag nicht als Urlaub abgezogen werden.
                                $tage = $urlaubService->zaehleBetriebsferienArbeitstageFuerMitarbeiter(
                                    (int)$mitarbeiter['id'],
                                    $calcVon->format('Y-m-d'),
                                    $calcBis->format('Y-m-d')
                                );
                            } else {
                                $tage = 0;
                            }
                        } catch (Throwable $e) {
                            $tage = null;
                        }
                    }

                    $betriebsferienListe[$i]['benoetigte_tage'] = $tage;
                }
            }

            $vonTmp = (string)($urlaubFormular['von_datum'] ?? '');
            $bisTmp = (string)($urlaubFormular['bis_datum'] ?? '');

            if ($vonTmp !== '' && $bisTmp !== '') {
                $vonDt = DateTimeImmutable::createFromFormat('Y-m-d', $vonTmp);
                $bisDt = DateTimeImmutable::createFromFormat('Y-m-d', $bisTmp);

                if ($vonDt && $bisDt && $vonDt->format('Y-m-d') === $vonTmp && $bisDt->format('Y-m-d') === $bisTmp) {
                    $diff = $vonDt->diff($bisDt);
                    if ($diff->invert === 0) {
                        $tageNeu = (float)$urlaubService->berechneTageGesamtAlsArbeitstageString((int)$mitarbeiter['id'], $vonTmp, $bisTmp);
                        $verfuegbar = isset($urlaubSaldo['verbleibend']) ? (float)$urlaubSaldo['verbleibend'] : 0.0;

                        $urlaubVorschau = [
                            'tage_antrag' => number_format($tageNeu, 2, '.', ''),
                            'verfuegbar'  => number_format($verfuegbar, 2, '.', ''),
                            'nach_antrag' => number_format($verfuegbar - $tageNeu, 2, '.', ''),
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            $urlaubSaldo = null;
            $urlaubSaldoFehler = 'Urlaubssaldo konnte nicht geladen werden.';

            if (class_exists('Logger')) {
                Logger::warn('Terminal: Urlaubssaldo/Vorschau konnte nicht geladen werden', [
                    'mitarbeiter_id' => (int)$mitarbeiter['id'],
                    'jahr'           => $urlaubJahr,
                    'exception'      => $e->getMessage(),
                ], (int)$mitarbeiter['id'], null, 'terminal_urlaub');
            }
        }

        // Auto-Logout (Kontext: Urlaub)
        $terminalTimeoutSekunden = $this->holeTerminalTimeoutSekunden('urlaub');

        // Optional: Übersicht wie im Hauptmenü mitladen
        $heuteDatum     = null;
        $heuteBuchungen = [];
        $heuteFehler    = null;
        $laufendeAuftraege = [];

        $zeigeMicroBuchungen = ((string)($_GET['show_micro'] ?? '') === '1');

        if (is_array($mitarbeiter) && isset($mitarbeiter['id'])) {
            $uebersicht      = $this->holeHeutigeZeitUebersicht((int)$mitarbeiter['id'], $zeigeMicroBuchungen);
            $heuteDatum      = $uebersicht['datum'];
            $heuteBuchungen  = $uebersicht['buchungen'];
            $heuteFehler     = $uebersicht['fehler'];

            try {
                $auftragszeitModel = new AuftragszeitModel();
                $laufendeAuftraege = $auftragszeitModel->holeLaufendeFuerMitarbeiter((int)$mitarbeiter['id']);
            } catch (Throwable $e) {
                $laufendeAuftraege = [];
            }
        }

        require __DIR__ . '/../views/terminal/start.php';
    }

    /**
     * Urlaub beantragen – Speichern (Terminal, POST).
     */
    public function urlaubBeantragen(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst am Terminal anmelden (RFID).';
            header('Location: terminal.php?aktion=start');
            return;
        }

        if (!$this->istHauptdatenbankAktiv()) {
            $_SESSION['terminal_flash_fehler'] = 'Hauptdatenbank nicht erreichbar – Urlaub am Terminal nicht möglich.';
            header('Location: terminal.php?aktion=start');
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $this->urlaubBeantragenForm('Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.', [
                'von_datum'             => (string)($_POST['von_datum'] ?? ''),
                'bis_datum'             => (string)($_POST['bis_datum'] ?? ''),
                'kommentar_mitarbeiter' => (string)($_POST['kommentar_mitarbeiter'] ?? ''),
            ]);
            return;
        }

        $mitarbeiterId = (int)$mitarbeiter['id'];

        $von = trim((string)($_POST['von_datum'] ?? ''));
        $bis = trim((string)($_POST['bis_datum'] ?? ''));
        $kommentar = trim((string)($_POST['kommentar_mitarbeiter'] ?? ''));

        $formular = [
            'von_datum'             => $von,
            'bis_datum'             => $bis,
            'kommentar_mitarbeiter' => $kommentar,
        ];

        $fehlermeldung = null;

        if ($von === '' || $bis === '') {
            $fehlermeldung = 'Bitte Von- und Bis-Datum angeben.';
        }

        $vonDt = null;
        $bisDt = null;

        if ($fehlermeldung === null) {
            $vonDt = \DateTimeImmutable::createFromFormat('Y-m-d', $von);
            $bisDt = \DateTimeImmutable::createFromFormat('Y-m-d', $bis);

            if (!$vonDt || $vonDt->format('Y-m-d') !== $von || !$bisDt || $bisDt->format('Y-m-d') !== $bis) {
                $fehlermeldung = 'Bitte gültige Daten wählen.';
            }
        }

        if ($fehlermeldung === null && $vonDt !== null && $bisDt !== null) {
            $diff = $vonDt->diff($bisDt);
            if ($diff->invert === 1) {
                $fehlermeldung = 'Das Bis-Datum muss am oder nach dem Von-Datum liegen.';
            }
        }

        if ($kommentar !== '' && mb_strlen($kommentar, 'UTF-8') > 2000) {
            $kommentar = mb_substr($kommentar, 0, 2000, 'UTF-8');
            $formular['kommentar_mitarbeiter'] = $kommentar;
        }

        if ($fehlermeldung !== null) {
            $this->urlaubBeantragenForm($fehlermeldung, $formular);
            return;
        }

        // Tage gesamt = Arbeitstage (inkl. Start- und Endtag),
        // Wochenenden, betriebsfreie Feiertage und Betriebsferien werden nicht gezählt.
        $urlaubService = UrlaubService::getInstanz();
        $ueberlappung = $urlaubService->findeUeberlappendenGenehmigtenUrlaub($mitarbeiterId, $von, $bis);
        if ($ueberlappung !== null) {
            $ovVon = (string)($ueberlappung['von_datum'] ?? '');
            $ovBis = (string)($ueberlappung['bis_datum'] ?? '');
            $ovId  = (int)($ueberlappung['id'] ?? 0);

            $msg = 'Es existiert bereits genehmigter Urlaub im Zeitraum ';
            $msg .= ($ovVon !== '' ? $ovVon : '?') . ' bis ' . ($ovBis !== '' ? $ovBis : '?');
            if ($ovId > 0) {
                $msg .= ' (Antrag #' . $ovId . ').';
            } else {
                $msg .= '.';
            }
            $msg .= ' Bitte Zeitraum anpassen oder Personalbüro kontaktieren.';

            $this->urlaubBeantragenForm($msg, $formular);
            return;
        }
        $tageGesamt = $urlaubService->berechneTageGesamtAlsArbeitstageString($mitarbeiterId, $von, $bis);

        // Optional (konfigurierbar): Antrag blockieren, wenn Resturlaub dadurch negativ würde.
        if ($urlaubService->istNegativerResturlaubGeblockt()) {
            $msg = $urlaubService->pruefeNegativenResturlaubBeiNeuemAntrag($mitarbeiterId, $von, $bis);
            if ($msg !== null) {
                $this->urlaubBeantragenForm($msg, $formular);
                return;
            }
        }

        try {
            $this->datenbank->ausfuehren(
                'INSERT INTO urlaubsantrag (mitarbeiter_id, von_datum, bis_datum, tage_gesamt, kommentar_mitarbeiter)
                 VALUES (:mid, :von, :bis, :tage, :kommentar)',
                [
                    'mid'       => $mitarbeiterId,
                    'von'       => $von,
                    'bis'       => $bis,
                    'tage'      => $tageGesamt,
                    'kommentar' => ($kommentar !== '' ? $kommentar : null),
                ]
            );
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Terminal: Fehler beim Speichern des Urlaubsantrags', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von,
                    'bis'            => $bis,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal_urlaub');
            }

            $this->urlaubBeantragenForm('Urlaubsantrag konnte nicht gespeichert werden.', $formular);
            return;
        }

        $_SESSION['terminal_flash_nachricht'] = 'Urlaubsantrag gespeichert (Status: offen).';
        header('Location: terminal.php?aktion=urlaub_beantragen');
        return;
    }

    /**
     * Urlaub: eigenen Antrag stornieren (nur solange Status = "offen").
     */
    public function urlaubStornieren(): void
    {
        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst am Terminal anmelden (RFID).';
            header('Location: terminal.php?aktion=start');
            return;
        }

        if (!$this->istHauptdatenbankAktiv()) {
            $_SESSION['terminal_flash_fehler'] = 'Hauptdatenbank nicht erreichbar – Storno am Terminal nicht möglich.';
            header('Location: terminal.php?aktion=start');
            return;
        }

        if (!$this->istCsrfTokenGueltigAusPost()) {
            $_SESSION['terminal_flash_fehler'] = 'Sicherheits-Token ist abgelaufen. Bitte erneut versuchen.';
            header('Location: terminal.php?aktion=urlaub_beantragen');
            return;
        }

        $mitarbeiterId = (int)$mitarbeiter['id'];
        $antragId = isset($_POST['urlaubsantrag_id']) ? (int)$_POST['urlaubsantrag_id'] : 0;

        if ($antragId <= 0) {
            $_SESSION['terminal_flash_fehler'] = 'Ungültige Antrags-ID.';
            header('Location: terminal.php?aktion=urlaub_beantragen');
            return;
        }

        $row = null;
        try {
            $row = $this->datenbank->fetchEine(
                'SELECT id, status, von_datum, bis_datum
                 FROM urlaubsantrag
                 WHERE id = :id AND mitarbeiter_id = :mid
                 LIMIT 1',
                ['id' => $antragId, 'mid' => $mitarbeiterId]
            );
        } catch (Throwable $e) {
            $row = null;
        }

        if (!is_array($row) || empty($row['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Urlaubsantrag nicht gefunden.';
            header('Location: terminal.php?aktion=urlaub_beantragen');
            return;
        }

        $status = isset($row['status']) ? (string)$row['status'] : '';
        if ($status !== 'offen') {
            $_SESSION['terminal_flash_fehler'] = 'Nur offene Anträge können storniert werden.';
            header('Location: terminal.php?aktion=urlaub_beantragen');
            return;
        }

        $betroffen = 0;
        try {
            $betroffen = $this->datenbank->ausfuehren(
                "UPDATE urlaubsantrag SET status = 'storniert' WHERE id = :id AND mitarbeiter_id = :mid AND status = 'offen' LIMIT 1",
                ['id' => $antragId, 'mid' => $mitarbeiterId]
            );
        } catch (Throwable $e) {
            $betroffen = 0;
            if (class_exists('Logger')) {
                Logger::error('Terminal: Fehler beim Stornieren des Urlaubsantrags', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'antrag_id'      => $antragId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'terminal_urlaub');
            }
        }

        if ($betroffen <= 0) {
            $_SESSION['terminal_flash_fehler'] = 'Storno ist fehlgeschlagen. Bitte erneut versuchen.';
            header('Location: terminal.php?aktion=urlaub_beantragen');
            return;
        }

        $_SESSION['terminal_flash_nachricht'] = 'Urlaubsantrag wurde storniert.';
        header('Location: terminal.php?aktion=urlaub_beantragen');
        return;
    }

    /**
     * Bucht ein „Kommen“ für den aktuell angemeldeten Mitarbeiter
     * und kehrt anschließend zum Hauptmenü zurück.
     */
    public function kommen(): void
    {
        // Mutierende Aktion: nur POST + CSRF.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['terminal_flash_fehler'] = 'Ungültige Anfrage (nur POST erlaubt).';
            header('Location: terminal.php?aktion=start');
            exit;
        }
        if (!$this->istCsrfTokenGueltigAusPost()) {
            $_SESSION['terminal_flash_fehler'] = 'Ungültiges CSRF-Token.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            // Offline-Mode (Master-Prompt v9):
            // Wenn die Hauptdatenbank offline ist, buchen wir Kommen/Gehen per RFID in die Queue,
            // ohne Mitarbeiter-Login.
            if (!$this->istHauptdatenbankAktiv()) {
                $rfidCode = isset($_POST['rfid_code']) ? trim((string)$_POST['rfid_code']) : '';
                if ($rfidCode === '' && isset($_SESSION['terminal_offline_rfid_code']) && is_string($_SESSION['terminal_offline_rfid_code'])) {
                    $rfidCode = trim((string)$_SESSION['terminal_offline_rfid_code']);
                }

                if ($rfidCode === '') {
                    $_SESSION['terminal_flash_fehler'] = 'Offline-Modus: Bitte zuerst RFID scannen.';
                    header('Location: terminal.php?aktion=start');
                    exit;
                }

                $zeitpunkt = new DateTimeImmutable('now');

                // T-069: De-Bounce gegen Doppelbuchungen.
                $dupZeit = $this->pruefeTerminalDoppelteBuchung('kommen', 5, null, $rfidCode);
                if ($dupZeit !== null) {
                    $_SESSION['terminal_flash_nachricht'] = 'Kommen wurde bereits um ' . $dupZeit . ' gespeichert (Doppelklick/Scan ignoriert).';
                    unset($_SESSION['terminal_offline_rfid_code']);
                    unset($_SESSION['terminal_offline_rfid_hint']);
                    header('Location: terminal.php?aktion=start');
                    exit;
                }

                $id = $this->bucheZeitOfflinePerRfid('kommen', $rfidCode, $zeitpunkt);
                if ($id === null) {
                    $_SESSION['terminal_flash_fehler'] = 'Offline: Kommen konnte nicht in der Queue gespeichert werden.';
                } else {
                    $_SESSION['terminal_flash_nachricht'] = 'Offline: Kommen gespeichert um ' . $zeitpunkt->format('H:i:s') . '.';
                    $this->merkeTerminalLetzteBuchung('kommen', $zeitpunkt, null, $rfidCode);
                }

                // Kiosk: nach der Buchung RFID wieder leeren (nächster Mitarbeiter).
                unset($_SESSION['terminal_offline_rfid_code']);
                unset($_SESSION['terminal_offline_rfid_hint']);
                header('Location: terminal.php?aktion=start');
                exit;
            }

            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            $csrfToken = $this->holeOderErzeugeCsrfToken();
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }

        // Regel: „Kommen“ darf nicht gebucht werden, wenn der Mitarbeiter bereits anwesend ist.
        if ($this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Sie sind bereits als anwesend registriert. Bitte zuerst „Gehen“ buchen.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = null;
        $fehlerText = null;

        $zeitpunkt = new DateTimeImmutable('now');

        // T-069: De-Bounce gegen Doppelbuchungen (Doppelklick/Scan).
        $dupZeit = $this->pruefeTerminalDoppelteBuchung('kommen', 5, (int)$mitarbeiter['id'], null);

        $id = null;
        $nachtshift = ((int)($_POST['nachtshift'] ?? 0) === 1) ? 1 : 0;
        if ($dupZeit !== null) {
            $nachricht = 'Kommen wurde bereits um ' . $dupZeit . ' gebucht (Doppelklick/Scan ignoriert).';
        } else {
            // WICHTIG (Projektentscheidung): Rohzeit buchen.
            // Rundung erfolgt später nur in Auswertungen/Export/PDF.
            $id = $this->zeitService->bucheKommen((int)$mitarbeiter['id'], $zeitpunkt, 'terminal', null, null, $nachtshift);

            if ($id === null) {
                $fehlerText = 'Kommen konnte nicht gebucht werden. Bitte erneut versuchen oder Vorgesetzten informieren.';
            } elseif ($id === 0) {
                // Offline-Queue: Buchung wird später eingespielt.
                $nachricht = 'Hauptdatenbank offline – Kommen wurde in der Offline-Queue gespeichert (Zeit: ' . $zeitpunkt->format('H:i:s') . ').';
            } else {
                $nachricht = 'Kommen gebucht um ' . $zeitpunkt->format('H:i:s') . '.';
            }

            // Bei Erfolg (online oder offline) merken wir die letzte Buchung für De-Bounce.
            if ($id !== null) {
                $this->merkeTerminalLetzteBuchung('kommen', $zeitpunkt, (int)$mitarbeiter['id'], null);
            }
        }

        // Bei erfolgreichem Kommen (online oder offline) merken wir den Anwesenheitsstatus.
        if ($fehlerText === null) {
            $this->setzeTerminalAnwesenheitStatus(true);
        }


        // Einheitlicher Flow: nach Kommen/Gehen immer auf Startscreen redirecten,
        // damit alle Statusboxen (Urlaub, Warnungen, laufende Aufträge, ...) konsistent berechnet werden.
        if ($fehlerText !== null && $fehlerText !== '') {
            $_SESSION['terminal_flash_fehler'] = $fehlerText;
        } elseif ($nachricht !== null && $nachricht !== '') {
            $_SESSION['terminal_flash_nachricht'] = $nachricht;
        }

        // Kiosk: nach erfolgreicher Buchung direkt abmelden, damit der naechste Mitarbeiter
        // sofort scannen kann (kein Warten auf Timeout/Abbrechen).
        if ($fehlerText === null) {
            $this->loescheTerminalMitarbeiterSession();
        }

        header('Location: terminal.php?aktion=start');
        exit;

    }

    /**
     * Bucht ein „Gehen“ für den aktuell angemeldeten Mitarbeiter
     * und kehrt anschließend zum Hauptmenü zurück.
     */
    public function gehen(): void
    {
        // Mutierende Aktion: nur POST + CSRF.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['terminal_flash_fehler'] = 'Ungültige Anfrage (nur POST erlaubt).';
            header('Location: terminal.php?aktion=start');
            exit;
        }
        if (!$this->istCsrfTokenGueltigAusPost()) {
            $_SESSION['terminal_flash_fehler'] = 'Ungültiges CSRF-Token.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $mitarbeiter = $this->holeAngemeldetenTerminalMitarbeiter();
        if ($mitarbeiter === null) {
            // Offline-Mode (Master-Prompt v9):
            // Wenn die Hauptdatenbank offline ist, buchen wir Kommen/Gehen per RFID in die Queue,
            // ohne Mitarbeiter-Login.
            if (!$this->istHauptdatenbankAktiv()) {
                $rfidCode = isset($_POST['rfid_code']) ? trim((string)$_POST['rfid_code']) : '';
                if ($rfidCode === '' && isset($_SESSION['terminal_offline_rfid_code']) && is_string($_SESSION['terminal_offline_rfid_code'])) {
                    $rfidCode = trim((string)$_SESSION['terminal_offline_rfid_code']);
                }

                if ($rfidCode === '') {
                    $_SESSION['terminal_flash_fehler'] = 'Offline-Modus: Bitte zuerst RFID scannen.';
                    header('Location: terminal.php?aktion=start');
                    exit;
                }

                $zeitpunkt = new DateTimeImmutable('now');

                // T-069: De-Bounce gegen Doppelbuchungen.
                $dupZeit = $this->pruefeTerminalDoppelteBuchung('gehen', 5, null, $rfidCode);
                if ($dupZeit !== null) {
                    $_SESSION['terminal_flash_nachricht'] = 'Gehen wurde bereits um ' . $dupZeit . ' gespeichert (Doppelklick/Scan ignoriert).';
                    unset($_SESSION['terminal_offline_rfid_code']);
                    unset($_SESSION['terminal_offline_rfid_hint']);
                    header('Location: terminal.php?aktion=start');
                    exit;
                }

                $id = $this->bucheZeitOfflinePerRfid('gehen', $rfidCode, $zeitpunkt);
                if ($id === null) {
                    $_SESSION['terminal_flash_fehler'] = 'Offline: Gehen konnte nicht in der Queue gespeichert werden.';
                } else {
                    $_SESSION['terminal_flash_nachricht'] = 'Offline: Gehen gespeichert um ' . $zeitpunkt->format('H:i:s') . '.';
                    $this->merkeTerminalLetzteBuchung('gehen', $zeitpunkt, null, $rfidCode);
                }

                // Kiosk: nach der Buchung RFID wieder leeren (nächster Mitarbeiter).
                unset($_SESSION['terminal_offline_rfid_code']);
                unset($_SESSION['terminal_offline_rfid_hint']);
                header('Location: terminal.php?aktion=start');
                exit;
            }

            $nachricht  = null;
            $fehlerText = 'Bitte zuerst am Terminal anmelden (RFID).';
            $csrfToken = $this->holeOderErzeugeCsrfToken();
            require __DIR__ . '/../views/terminal/start.php';
            return;
        }


        // Regel: Gehen ist nur möglich, wenn der Mitarbeiter aktuell anwesend ist.
        // (Anwesend = heute mehr "kommen" als "gehen"; Offline-Fallback über Session.)
        if (!$this->istTerminalMitarbeiterHeuteAnwesend((int)$mitarbeiter['id'])) {
            $_SESSION['terminal_flash_fehler'] = 'Bitte zuerst „Kommen“ buchen. „Gehen“ ist erst danach möglich.';
            header('Location: terminal.php?aktion=start');
            exit;
        }

        $nachricht  = null;
        $fehlerText = null;

        $zeitpunkt = new DateTimeImmutable('now');

        // T-069: De-Bounce gegen Doppelbuchungen (Doppelklick/Scan).
        $dupZeit = $this->pruefeTerminalDoppelteBuchung('gehen', 5, (int)$mitarbeiter['id'], null);

        $id = null;
        if ($dupZeit !== null) {
            $nachricht = 'Gehen wurde bereits um ' . $dupZeit . ' gebucht (Doppelklick/Scan ignoriert).';
        } else {
            // WICHTIG (Projektentscheidung): Rohzeit buchen.
            // Rundung erfolgt später nur in Auswertungen/Export/PDF.
            $id = $this->zeitService->bucheGehen((int)$mitarbeiter['id'], $zeitpunkt, 'terminal', null, null);

            if ($id === null) {
                $fehlerText = 'Gehen konnte nicht gebucht werden. Bitte erneut versuchen oder Vorgesetzten informieren.';
            } elseif ($id === 0) {
                // Offline-Queue: Buchung wird später eingespielt.
                $nachricht = 'Hauptdatenbank offline – Gehen wurde in der Offline-Queue gespeichert (Zeit: ' . $zeitpunkt->format('H:i:s') . ').';
            } else {
                $nachricht = 'Gehen gebucht um ' . $zeitpunkt->format('H:i:s') . '.';
            }

            // Bei Erfolg (online oder offline) merken wir die letzte Buchung für De-Bounce.
            if ($id !== null) {
                $this->merkeTerminalLetzteBuchung('gehen', $zeitpunkt, (int)$mitarbeiter['id'], null);
            }
        }

        // Bei erfolgreichem Gehen (online oder offline) merken wir den Anwesenheitsstatus.
        if ($fehlerText === null) {
            $this->setzeTerminalAnwesenheitStatus(false);
        }


        // Einheitlicher Flow: nach Kommen/Gehen immer auf Startscreen redirecten,
        // damit alle Statusboxen (Urlaub, Warnungen, laufende Aufträge, ...) konsistent berechnet werden.
        if ($fehlerText !== null && $fehlerText !== '') {
            $_SESSION['terminal_flash_fehler'] = $fehlerText;
        } elseif ($nachricht !== null && $nachricht !== '') {
            $_SESSION['terminal_flash_nachricht'] = $nachricht;
        }

        // Kiosk: nach erfolgreicher Buchung direkt abmelden, damit der naechste Mitarbeiter
        // sofort scannen kann (kein Warten auf Timeout/Abbrechen).
        if ($fehlerText === null) {
            $this->loescheTerminalMitarbeiterSession();
        }

        header('Location: terminal.php?aktion=start');
        exit;

    }

}
