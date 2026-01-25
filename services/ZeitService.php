<?php
declare(strict_types=1);

/**
 * ZeitService
 *
 * Stellt Funktionen rund um Zeitbuchungen und Tageswerte bereit.
 *
 * Diese erste Implementierung liefert vor allem die Daten für die
 * Tagesansicht eines Mitarbeiters.
 */
class ZeitService
{
    private static ?ZeitService $instanz = null;

    private ZeitbuchungModel $zeitbuchungModel;
    private TageswerteMitarbeiterModel $tageswerteModel;
    private RundungsService $rundungsService;

    private function __construct()
    {
        $this->zeitbuchungModel      = new ZeitbuchungModel();
        $this->tageswerteModel       = new TageswerteMitarbeiterModel();
        $this->rundungsService       = RundungsService::getInstanz();
    }

    /**
     * Terminal-Installationserkennung (für Offline-Queue-Logik).
     */
    private function istTerminalInstallation(): bool
    {
        $pfad = __DIR__ . '/../config/config.php';
        if (!is_file($pfad)) {
            return false;
        }

        try {
            /** @var array<string,mixed> $cfg */
            $cfg = require $pfad;
        } catch (\Throwable $e) {
            return false;
        }

        $typ = $cfg['app']['installation_typ'] ?? null;
        return is_string($typ) && strtolower(trim($typ)) === 'terminal';
    }

    private function sqlQuote(string $value): string
    {
        // Standard-SQL-Quoting (''), MySQL-kompatibel
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function sqlNullableString(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = trim($value);
        if ($value === '') {
            return 'NULL';
        }

        // Kommentar-Spalte ist VARCHAR(255)
        if (strlen($value) > 255) {
            $value = substr($value, 0, 255);
        }

        return $this->sqlQuote($value);
    }

    public static function getInstanz(): ZeitService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Liefert die Tagesdaten für einen Mitarbeiter.
     *
     * Rückgabeformat (Beispiel):
     *
     * [
     *   'mitarbeiter_id' => 5,
     *   'datum'          => '2025-01-01',
     *   'buchungen'      => [...], // rohe Zeitbuchungen (SELECT aus zeitbuchung)
     *   'tageswerte'     => [...], // ggf. Eintrag aus tageswerte_mitarbeiter oder null
     * ]
     *
     * @return array<string,mixed>
     */
    public function holeTagesdaten(int $mitarbeiterId, \DateTimeInterface $datum): array
    {
        // Start/Ende des Tages bestimmen (lokale Zeit)
        $tagStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datum->format('Y-m-d') . ' 00:00:00');
        if ($tagStart === false) {
            $tagStart = new \DateTimeImmutable($datum->format('Y-m-d'));
        }

        $tagEnd = $tagStart->modify('+1 day');

        // Rohe Zeitbuchungen holen
        try {
            $buchungen = $this->zeitbuchungModel->holeFuerMitarbeiterUndZeitraum(
                $mitarbeiterId,
                $tagStart,
                $tagEnd
            );
        } catch (\Throwable $e) {
            $buchungen = [];
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Zeitbuchungen für Tagesansicht', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'datum'          => $datum->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'zeitservice');
            }
        }

        // Aggregierte Tageswerte (falls vorhanden) laden
        $tageswerte = null;
        try {
            $tageswerte = $this->tageswerteModel->holeNachMitarbeiterUndDatum(
                $mitarbeiterId,
                $tagStart->format('Y-m-d')
            );
        } catch (\Throwable $e) {
            $tageswerte = null;
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden der Tageswerte für Tagesansicht', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'datum'          => $datum->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'zeitservice');
            }
        }

        return [
            'mitarbeiter_id' => $mitarbeiterId,
            'datum'          => $tagStart->format('Y-m-d'),
            'buchungen'      => $buchungen,
            'tageswerte'     => $tageswerte,
        ];
    }


    /**
     * Interne Hilfsmethode zum Buchen einer Zeit (Kommen/Gehen).
     *
     * @return int|null ID der erzeugten Zeitbuchung oder null bei Fehler
     */
    private function bucheZeit(
        int $mitarbeiterId,
        string $typ,
        ?\DateTimeImmutable $zeitpunkt = null,
        string $quelle = 'terminal',
        ?int $terminalId = null,
        ?string $kommentar = null,
        ?int $nachtshift = null
    ): ?int {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        if ($typ !== 'kommen' && $typ !== 'gehen') {
            $typ = 'kommen';
        }

        if ($zeitpunkt === null) {
            $zeitpunkt = new \DateTimeImmutable('now');
        }

        // WICHTIG (Projektentscheidung):
        // - Beim Buchen (Kommen/Gehen) speichern wir IMMER die Rohzeit.
        // - Rundung erfolgt ausschließlich bei Auswertungen/Export/PDF.
        // Dadurch bleibt die Datenbasis unverfälscht und Rundungsregeln können
        // später angepasst werden, ohne historische Buchungen zu "verbiegen".

        if (!in_array($quelle, ['terminal', 'web', 'import'], true)) {
            $quelle = 'terminal';
        }

        // ------------------------------------------------------------
        // Offline-Queue (Terminal):
        // Wenn die Hauptdatenbank nicht erreichbar ist, speichern wir den
        // INSERT-Befehl in `db_injektionsqueue`, damit das Terminal weiter
        // arbeiten kann (Master-Prompt).
        // ------------------------------------------------------------

        $db = null;
        if (class_exists('Database')) {
            $db = Database::getInstanz();
        }

        $hauptDbOk = null;
        if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptDbOk = (bool)$db->istHauptdatenbankVerfuegbar();
            } catch (\Throwable $e) {
                $hauptDbOk = false;
            }
        }

        if ($quelle === 'terminal' && $this->istTerminalInstallation() && $hauptDbOk === false) {
            $zeitStr = $zeitpunkt->format('Y-m-d H:i:s');
            $terminalSql = ($terminalId !== null) ? (string)(int)$terminalId : 'NULL';

            $nachtshiftVal = ($typ === 'kommen' && (int)$nachtshift === 1) ? 1 : 0;

            $sql = 'INSERT INTO zeitbuchung (mitarbeiter_id, typ, zeitstempel, quelle, manuell_geaendert, kommentar, terminal_id, nachtshift) VALUES ('
                . (int)$mitarbeiterId . ', '
                . $this->sqlQuote($typ) . ', '
                . $this->sqlQuote($zeitStr) . ', '
                . $this->sqlQuote($quelle) . ', '
                . '0, '
                . $this->sqlNullableString($kommentar) . ', '
                . $terminalSql . ', '
                . $nachtshiftVal
                . ')';

            try {
                $ok = OfflineQueueManager::getInstanz()->speichereInQueue(
                    $sql,
                    $mitarbeiterId,
                    $terminalId,
                    'zeit_' . $typ
                );

                // Wir liefern eine "Pseudo-ID" zurück, damit das Terminal die Buchung als Erfolg behandelt.
                // Die echte ID entsteht erst beim späteren Einspielen in die Hauptdatenbank.
                return $ok ? 0 : null;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('ZeitService: Offline-Queue-Buchung fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'typ'            => $typ,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, $terminalId, 'zeitservice_offline');
                }
                return null;
            }
        }

        try {
            return $this->zeitbuchungModel->erstelleBuchung(
                $mitarbeiterId,
                $typ,
                $zeitpunkt,
                $quelle,
                $terminalId,
                $kommentar,
                false,
                $nachtshift
            );
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Buchen einer Zeit', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'typ'            => $typ,
                    'quelle'         => $quelle,
                    'terminal_id'    => $terminalId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'zeitservice');
            }

            return null;
        }
    }

    /**
     * Bucht ein „Kommen“ für einen Mitarbeiter.
     *
     * @return int|null ID der neuen Zeitbuchung oder null bei Fehler
     */
    public function bucheKommen(
        int $mitarbeiterId,
        ?\DateTimeImmutable $zeitpunkt = null,
        string $quelle = 'terminal',
        ?int $terminalId = null,
        ?string $kommentar = null,
        ?int $nachtshift = null
    ): ?int {
        return $this->bucheZeit($mitarbeiterId, 'kommen', $zeitpunkt, $quelle, $terminalId, $kommentar, $nachtshift);
    }

    /**
     * Bucht ein „Gehen“ für einen Mitarbeiter.
     *
     * @return int|null ID der neuen Zeitbuchung oder null bei Fehler
     */
    public function bucheGehen(
        int $mitarbeiterId,
        ?\DateTimeImmutable $zeitpunkt = null,
        string $quelle = 'terminal',
        ?int $terminalId = null,
        ?string $kommentar = null
    ): ?int {
        return $this->bucheZeit($mitarbeiterId, 'gehen', $zeitpunkt, $quelle, $terminalId, $kommentar, null);
    }

    /**
     * Synchronisiert (MVP) `tageswerte_mitarbeiter` für einen einzelnen Tag
     * anhand der Rohbuchungen in `zeitbuchung`.
     *
     * Ziel:
     * - Nach Admin-Korrekturen sollen Reports nicht "stale" werden.
     * - Wir upserten Rohzeiten (kommen_roh/gehen_roh) und ist_stunden.
     *
     * Wichtige Projektentscheidung:
     * - Rundung wird NICHT gespeichert, sondern für Berechnung/Anzeige abgeleitet.
     *   Deshalb werden kommen_korr/gehen_korr im Upsert auf NULL gesetzt.
     */
    public function synchronisiereTageswerteAusBuchungen(int $mitarbeiterId, string $datumYmd): bool
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        $datumYmd = trim($datumYmd);

        if ($mitarbeiterId <= 0 || $datumYmd === '') {
            return false;
        }

        $tag = \DateTimeImmutable::createFromFormat('Y-m-d', $datumYmd);
        if (!$tag instanceof \DateTimeImmutable || $tag->format('Y-m-d') !== $datumYmd) {
            return false;
        }

        $start = $tag->setTime(0, 0, 0);
        $ende  = $start->modify('+1 day');

        try {
            $buchungen = $this->zeitbuchungModel->holeFuerMitarbeiterUndZeitraum($mitarbeiterId, $start, $ende);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Synchronisierung Tageswerte: Zeitbuchungen konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'datum'          => $datumYmd,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'zeitservice');
            }
            return false;
        }

        $kommenRoh = null; // DateTimeImmutable|null
        $gehenRoh  = null; // DateTimeImmutable|null

        // Arbeitsblöcke: Kommen->Gehen Paare (für Mehrfach-Kommen/Gehen pro Tag).
        // Buchungen sind im Model zeitlich aufsteigend sortiert.
        $arbeitsBloecke = [];
        $blockStart = null; // DateTimeImmutable|null

        foreach ($buchungen as $b) {
            $typ = (string)($b['typ'] ?? '');
            $ts  = (string)($b['zeitstempel'] ?? '');
            if ($ts === '') {
                continue;
            }

            try {
                $dt = new \DateTimeImmutable($ts);
            } catch (\Throwable $e) {
                continue;
            }

            if ($typ === 'kommen') {
                if ($kommenRoh === null || $dt < $kommenRoh) {
                    $kommenRoh = $dt;
                }

                if ($blockStart === null) {
                    $blockStart = $dt;
                }
            } elseif ($typ === 'gehen') {
                if ($gehenRoh === null || $dt > $gehenRoh) {
                    $gehenRoh = $dt;
                }

                if ($blockStart !== null && $dt > $blockStart) {
                    $arbeitsBloecke[] = [$blockStart, $dt];
                    $blockStart = null;
                }
            }
        }

        // Bestehende Tageswerte laden (für manuelle Tagesfeld-Overrides).
        $pauseMinDb = 0;
        $felderManuell = 0;
        try {
            $tw = $this->tageswerteModel->holeNachMitarbeiterUndDatum($mitarbeiterId, $datumYmd);
            if (is_array($tw)) {
                $pauseMinDb = (int)($tw['pause_korr_minuten'] ?? 0);
                $felderManuell = (int)($tw['felder_manuell_geaendert'] ?? 0);
            }
        } catch (\Throwable $e) {
            $pauseMinDb = 0;
            $felderManuell = 0;
        }

        $pauseMin = 0;
        $pauseQuelle = 'auto';

        // Iststunden berechnen:
        // - Bei manuell gesetzten Tagesfeldern: wie bisher anhand kommen/gehen (korr/roh) - manuelle Pause.
        // - Sonst: Summe aus Arbeitsblöcken (Kommen->Gehen), inkl. Pausenregeln pro Block.
        $istStd = 0.0;

        // Wenn Tagesfelder manuell gesetzt wurden, respektieren wir den DB-Wert.
        if ($felderManuell === 1) {
            $pauseMin = max(0, $pauseMinDb);
            $pauseQuelle = 'manuell';

            $pauseStd = $pauseMin / 60.0;

            if ($kommenRoh !== null && $gehenRoh !== null && $gehenRoh > $kommenRoh) {
                try {
                    $kKorr = $this->rundungsService->rundeZeitstempel($kommenRoh, 'kommen');
                    $gKorr = $this->rundungsService->rundeZeitstempel($gehenRoh, 'gehen');

                    if ($gKorr > $kKorr) {
                        $istStd = (($gKorr->getTimestamp() - $kKorr->getTimestamp()) / 3600.0) - $pauseStd;
                    } else {
                        $istStd = (($gehenRoh->getTimestamp() - $kommenRoh->getTimestamp()) / 3600.0) - $pauseStd;
                    }
                } catch (\Throwable $e) {
                    $istStd = (($gehenRoh->getTimestamp() - $kommenRoh->getTimestamp()) / 3600.0) - $pauseStd;
                }
            }
        } elseif (!empty($arbeitsBloecke)) {
            try {
                $pausenService = PausenService::getInstanz();

                foreach ($arbeitsBloecke as $block) {
                    $startRoh = $block[0] ?? null;
                    $endeRoh  = $block[1] ?? null;

                    if (!($startRoh instanceof \DateTimeImmutable) || !($endeRoh instanceof \DateTimeImmutable) || $endeRoh <= $startRoh) {
                        continue;
                    }

                    $kKorr = $this->rundungsService->rundeZeitstempel($startRoh, 'kommen');
                    $gKorr = $this->rundungsService->rundeZeitstempel($endeRoh, 'gehen');

                    if ($gKorr <= $kKorr) {
                        // Fallback roh
                        $blockStd = (($endeRoh->getTimestamp() - $startRoh->getTimestamp()) / 3600.0);
                        $istStd += max(0.0, $blockStd);
                        continue;
                    }

                    $blockPauseMin = (int)$pausenService->berechnePausenMinutenFuerBlock($kKorr, $gKorr);
                    $pauseMin += max(0, $blockPauseMin);

                    $blockStd = (($gKorr->getTimestamp() - $kKorr->getTimestamp()) / 3600.0) - ($blockPauseMin / 60.0);
                    $istStd += max(0.0, $blockStd);
                }
            } catch (\Throwable $e) {
                // Kein Hard-Crash; dann rechnen wir ohne Pausenregeln.
                foreach ($arbeitsBloecke as $block) {
                    $startRoh = $block[0] ?? null;
                    $endeRoh  = $block[1] ?? null;

                    if (!($startRoh instanceof \DateTimeImmutable) || !($endeRoh instanceof \DateTimeImmutable) || $endeRoh <= $startRoh) {
                        continue;
                    }

                    $istStd += max(0.0, (($endeRoh->getTimestamp() - $startRoh->getTimestamp()) / 3600.0));
                }
            }
        }

        $pauseMin = max(0, (int)$pauseMin);

        if ($istStd < 0) {
            $istStd = 0.0;
        }

        $kommenStr = $kommenRoh !== null ? $kommenRoh->format('Y-m-d H:i:s') : null;
        $gehenStr  = $gehenRoh  !== null ? $gehenRoh->format('Y-m-d H:i:s')  : null;

        $ok = $this->tageswerteModel->upsertRohzeitenUndIststunden(
            $mitarbeiterId,
            $datumYmd,
            $kommenStr,
            $gehenStr,
            $istStd,
            $pauseMin
        );

        if ($ok && class_exists('Logger')) {
            Logger::info('Synchronisierung Tageswerte abgeschlossen', [
                'mitarbeiter_id' => $mitarbeiterId,
                'datum'          => $datumYmd,
                'kommen_roh'     => $kommenStr,
                'gehen_roh'      => $gehenStr,
                'pause_min'      => $pauseMin,
                'pause_quelle'   => $pauseQuelle,
                'pause_db_min'   => $pauseMinDb,
                'felder_manuell' => $felderManuell,
            ], $mitarbeiterId, null, 'zeitservice');
        }

        return $ok;
    }

}
