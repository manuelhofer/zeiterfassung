<?php
declare(strict_types=1);

/**
 * UrlaubService
 *
 * Zuständig für die Geschäftslogik rund um Urlaubsanträge.
 */
class UrlaubService
{
    private static ?UrlaubService $instanz = null;

    private UrlaubsantragModel $urlaubsantragModel;

    private function __construct()
    {
        $this->urlaubsantragModel = new UrlaubsantragModel();
    }

    public static function getInstanz(): UrlaubService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Gewichtung eines einzelnen Urlaubstages (in Tagen).
     *
     * In der Praxis gelten in vielen Betrieben Heiligabend (24.12) und Silvester (31.12)
     * als "halbe Urlaubstage". Damit Anzeige (z. B. Betriebsferienliste) und Saldo-Berechnung
     * konsistent bleiben, werden diese Tage (falls sie als Urlaub/Betriebsferien zaehlen)
     * mit 0.50 statt 1.00 gewichtet.
     */
    private function urlaubTagesGewicht(string $ymd): float
    {
        // erwartet Y-m-d
        if (strlen($ymd) !== 10) {
            return 1.0;
        }
        $md = substr($ymd, 5, 5); // MM-DD
        if ($md === '12-24' || $md === '12-31') {
            return 0.5;
        }
        return 1.0;
    }

    /**
     * Zaehlt die Anzahl der Betriebsferien-Tage im Zeitraum, die als **Arbeitstage** gelten.
     *
     * Zweck:
     * - Anzeige "Urlaubstage (abzgl. geplante Betriebsferien)" im Monatsreport (PDF/HTML).
     * - Wochenenden und betriebsfreie Feiertage zaehlen nicht als Urlaubstage.
     * - Betriebsferien werden global + abteilungsbezogen beruecksichtigt.
     */
    public function zaehleBetriebsferienArbeitstageFuerMitarbeiter(int $mitarbeiterId, string $vonDatum, string $bisDatum): float
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return 0;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);
        if ($von === null || $bis === null) {
            return 0;
        }

        if ($von > $bis) {
            return 0;
        }

        // Schutz vor extremen Bereichen
        try {
            $tageDiff = (int)$von->diff($bis)->days;
        } catch (\Throwable $e) {
            $tageDiff = 0;
        }
        if ($tageDiff > 3650) { // 10 Jahre
            return 0;
        }

        $bundesland = $this->holeBundeslandAusConfig();
        $feiertageFrei = $this->holeBetriebsfreieFeiertageSet($von->format('Y-m-d'), $bis->format('Y-m-d'), $bundesland);
        $betriebsferienTage = $this->holeBetriebsferienTageSetFuerMitarbeiter($mitarbeiterId, $von, $bis);

        /** @var array<string,bool> $skipBetriebsferienTage */
        $skipBetriebsferienTage = [];

        // WICHTIG (B-080):
        // - Wenn an einem Betriebsferien-Tag gearbeitet wurde, darf der Tag NICHT als Zwangsurlaub zaehlen.
        // - Gleiches gilt, wenn an diesem Tag bereits andere Kennzeichen (z. B. Urlaub/Krank/Arzt/Kurzarbeit/Sonstiges) gesetzt sind.
        // - Krankzeiten werden teils nur als Zeitraum gepflegt (ohne Tages-Override) -> auch dann blockieren.
        try {
            $db = Database::getInstanz();
            $twRows = $db->fetchAlle(
                'SELECT datum,
                        ist_stunden AS arbeitszeit_stunden,
                        kommen_roh, gehen_roh, kommen_korr, gehen_korr,
                        kennzeichen_feiertag,
                        kennzeichen_urlaub,
                        kennzeichen_arzt,
                        kennzeichen_krank_lfz,
                        kennzeichen_krank_kk,
                        kennzeichen_kurzarbeit,
                        kennzeichen_sonstiges
                 FROM tageswerte_mitarbeiter
                 WHERE mitarbeiter_id = :mid
                   AND datum BETWEEN :von AND :bis',
                [
                    'mid' => $mitarbeiterId,
                    'von' => $von->format('Y-m-d'),
                    'bis' => $bis->format('Y-m-d'),
                ]
            );

            foreach ($twRows as $tw) {
                if (!is_array($tw)) {
                    continue;
                }

                $ymd = (string)($tw['datum'] ?? '');
                if ($ymd === '') {
                    continue;
                }

                // "hat gearbeitet" – robust: >0h ODER Kommen/Gehen gesetzt
                $hatArbeitszeit = false;
                $arbStd = (float)str_replace(',', '.', (string)($tw['arbeitszeit_stunden'] ?? '0'));
                if ($arbStd > 0.0) {
                    $hatArbeitszeit = true;
                }

                if (!$hatArbeitszeit) {
                    $k1 = trim((string)($tw['kommen_roh'] ?? ''));
                    $g1 = trim((string)($tw['gehen_roh'] ?? ''));
                    $k2 = trim((string)($tw['kommen_korr'] ?? ''));
                    $g2 = trim((string)($tw['gehen_korr'] ?? ''));
                    if ($k1 !== '' || $g1 !== '' || $k2 !== '' || $g2 !== '') {
                        $hatArbeitszeit = true;
                    }
                }

                // Wenn andere Kennzeichen gesetzt sind (z. B. Urlaub/Krank), soll BF nicht zusaetzlich zaehlen.
                $hatAndereKennzeichen = (
                    ((int)($tw['kennzeichen_feiertag'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_urlaub'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_arzt'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_krank_lfz'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_krank_kk'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_kurzarbeit'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_sonstiges'] ?? 0) === 1)
                );

                if ($hatArbeitszeit || $hatAndereKennzeichen) {
                    $skipBetriebsferienTage[$ymd] = true;
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Betriebsferien-Zaehler: Tageswerte fuer Skip konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von->format('Y-m-d'),
                    'bis'            => $bis->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }

        // Krankzeitraum (LFZ/KK) soll BF-Zwangsurlaub NICHT abziehen.
        // Wenn ein aktiver Krankzeitraum einen BF-Tag schneidet, wird dieser Tag aus der BF-Zaehllogik herausgenommen.
        try {
            $db = Database::getInstanz();
            $krankRows = $db->fetchAlle(
                'SELECT von_datum, bis_datum
                   FROM krankzeitraum
                  WHERE mitarbeiter_id = :mid
                    AND aktiv = 1
                    AND von_datum <= :bis
                    AND (bis_datum IS NULL OR bis_datum >= :von)',
                [
                    'mid' => $mitarbeiterId,
                    'von' => $von->format('Y-m-d'),
                    'bis' => $bis->format('Y-m-d'),
                ]
            );

            foreach ($krankRows as $kr) {
                if (!is_array($kr)) {
                    continue;
                }

                $kVon = $this->parseDatum((string)($kr['von_datum'] ?? ''));
                if ($kVon === null) {
                    continue;
                }

                $kBis = null;
                if (($kr['bis_datum'] ?? null) !== null && (string)$kr['bis_datum'] !== '') {
                    $kBis = $this->parseDatum((string)$kr['bis_datum']);
                }
                if ($kBis === null) {
                    $kBis = $bis;
                }

                if ($kVon < $von) {
                    $kVon = $von;
                }
                if ($kBis > $bis) {
                    $kBis = $bis;
                }

                $d = $kVon;
                while ($d <= $kBis) {
                    $ymd = $d->format('Y-m-d');

                    // Nur BF-Arbeitstage sind relevant
                    if (!isset($betriebsferienTage[$ymd])) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    $wochentag = (int)$d->format('N');
                    if ($wochentag >= 6) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    if (isset($feiertageFrei[$ymd])) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    $skipBetriebsferienTage[$ymd] = true;
                    $d = $d->modify('+1 day');
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Betriebsferien-Zaehler: Krankzeitraeume fuer Skip konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von->format('Y-m-d'),
                    'bis'            => $bis->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }


        $count = 0.0;
        $cursor = $von;
        while ($cursor <= $bis) {
            $ymd = $cursor->format('Y-m-d');

            if (!isset($betriebsferienTage[$ymd])) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            // 1..7 (Mo..So)
            $wochentag = (int)$cursor->format('N');
            if ($wochentag >= 6) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if (isset($feiertageFrei[$ymd])) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if (isset($skipBetriebsferienTage[$ymd])) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $count += $this->urlaubTagesGewicht($ymd);
            $cursor = $cursor->modify('+1 day');
        }

        return $count;
    }

    /**
     * Holt alle Urlaubsanträge eines Mitarbeiters, optional gefiltert nach Status.
     *
     * @param int         $mitarbeiterId
     * @param string|null $statusFilter z. B. 'offen', 'genehmigt', 'abgelehnt', 'storniert' oder null
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAntraegeFuerMitarbeiter(int $mitarbeiterId, ?string $statusFilter = null): array
    {
        try {
            return $this->urlaubsantragModel->holeFuerMitarbeiter($mitarbeiterId, $statusFilter);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Urlaubsanträge', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'status'         => $statusFilter,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }

            return [];
        }
    }

    /**
     * Prüft, ob der gewünschte Zeitraum mit einem bereits **genehmigten** Urlaubsantrag überlappt.
     *
     * Hintergrund:
     * - Mehrere Anträge können grundsätzlich gestellt werden.
     * - Wenn aber bereits genehmigter Urlaub existiert, soll ein weiterer Antrag im selben Zeitraum
     *   **blockiert** werden (Task T-017), damit keine Doppel-Genehmigungen entstehen.
     *
     * @return array<string,mixed>|null 1. überlappender Antrag oder null
     */
    public function findeUeberlappendenGenehmigtenUrlaub(int $mitarbeiterId, string $vonDatum, string $bisDatum): ?array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);
        if ($von === null || $bis === null) {
            return null;
        }

        if ($von > $bis) {
            return null;
        }

        // Schutz: keine extremen Bereiche prüfen
        try {
            $tageDiff = (int)$von->diff($bis)->days;
        } catch (\Throwable $e) {
            $tageDiff = 0;
        }

        if ($tageDiff > 3650) { // 10 Jahre
            return null;
        }

        $db = Database::getInstanz();

        // Überlappung: NICHT (ende < startNeu ODER start > endeNeu)
        $sql = "SELECT id, von_datum, bis_datum\n"
             . "FROM urlaubsantrag\n"
             . "WHERE mitarbeiter_id = :mid\n"
             . "  AND status = 'genehmigt'\n"
             . "  AND NOT (bis_datum < :von OR von_datum > :bis)\n"
             . "ORDER BY von_datum ASC, id ASC\n"
             . "LIMIT 1";

        try {
            $row = $db->fetchEine($sql, [
                'mid' => $mitarbeiterId,
                'von' => $von->format('Y-m-d'),
                'bis' => $bis->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler bei Überlappungsprüfung (genehmigter Urlaub)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $vonDatum,
                    'bis'            => $bisDatum,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
            return null;
        }

        return (is_array($row) && !empty($row['id'])) ? $row : null;
    }

    /**
     * Prüft, ob der gewünschte Zeitraum mit einem bereits **aktiven** Urlaubsantrag überlappt.
     *
     * Aktiv bedeutet standardmäßig: Status IN ('offen', 'genehmigt').
     * Optional kann zusätzlich explizit ausgeschlossen werden, dass abgelehnte/stornierte
     * Datensätze in Sonderfällen versehentlich mitgezogen werden.
     *
     * @return array<string,mixed>|null 1. überlappender Antrag oder null
     */
    public function findeUeberlappendenAktivenUrlaub(
        int $mitarbeiterId,
        string $vonDatum,
        string $bisDatum,
        bool $abgelehntUndStorniertExplizitAusschliessen = false
    ): ?array {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);
        if ($von === null || $bis === null) {
            return null;
        }

        if ($von > $bis) {
            return null;
        }

        // Schutz: keine extremen Bereiche prüfen
        try {
            $tageDiff = (int)$von->diff($bis)->days;
        } catch (\Throwable $e) {
            $tageDiff = 0;
        }

        if ($tageDiff > 3650) { // 10 Jahre
            return null;
        }

        $db = Database::getInstanz();

        // Überlappung: NICHT (ende < startNeu ODER start > endeNeu)
        $sql = "SELECT id, von_datum, bis_datum, status\n"
             . "FROM urlaubsantrag\n"
             . "WHERE mitarbeiter_id = :mid\n"
             . "  AND status IN ('offen', 'genehmigt')\n"
             . ($abgelehntUndStorniertExplizitAusschliessen
                ? "  AND status NOT IN ('abgelehnt', 'storniert')\n"
                : "")
             . "  AND NOT (bis_datum < :von OR von_datum > :bis)\n"
             . "ORDER BY von_datum ASC, id ASC\n"
             . "LIMIT 1";

        try {
            $row = $db->fetchEine($sql, [
                'mid' => $mitarbeiterId,
                'von' => $von->format('Y-m-d'),
                'bis' => $bis->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler bei Überlappungsprüfung (aktiver Urlaub)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $vonDatum,
                    'bis'            => $bisDatum,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
            return null;
        }

        return (is_array($row) && !empty($row['id'])) ? $row : null;
    }

    /**
     * Berechnet die Anzahl der **Arbeitstage** (inkl. Start- und Endtag),
     * wobei Wochenenden, betriebsfreie Feiertage und Betriebsferien **nicht** zählen.
     *
     * Wichtige Annahmen (MVP):
     * - Wochenenden (Sa/So) sind arbeitsfrei.
     * - Feiertage zählen als arbeitsfrei, wenn `feiertag.ist_betriebsfrei = 1`.
     * - Betriebsferien zählen als arbeitsfrei (global + abteilungsbezogen).
     * - Halbtage/Teilzeit/Schichtmodelle sind hier noch nicht berücksichtigt.
     */
    public function berechneArbeitstageFuerMitarbeiter(int $mitarbeiterId, string $vonDatum, string $bisDatum): int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return 0;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);

        if ($von === null || $bis === null) {
            return 0;
        }

        if ($von > $bis) {
            // Controller validiert normalerweise, aber wir bleiben defensiv.
            return 0;
        }

        // Schutz vor extremen Bereichen (z. B. fehlerhafte Eingaben)
        try {
            $tageDiff = (int)$von->diff($bis)->days;
        } catch (\Throwable $e) {
            $tageDiff = 0;
        }

        if ($tageDiff > 3650) { // 10 Jahre
            return 0;
        }

        $bundesland = $this->holeBundeslandAusConfig();

        // Feiertage im Zeitraum einmalig laden (mit Bundesland-Precedence)
        $feiertageFrei = $this->holeBetriebsfreieFeiertageSet($von->format('Y-m-d'), $bis->format('Y-m-d'), $bundesland);

        // Betriebsferien im Zeitraum einmalig laden (global + Abteilung)
        $betriebsferienTage = $this->holeBetriebsferienTageSetFuerMitarbeiter($mitarbeiterId, $von, $bis);

        /** @var array<string,bool> $skipBetriebsferienTage */
        $skipBetriebsferienTage = [];

        // WICHTIG (B-080):
        // - Wenn an einem Betriebsferien-Tag gearbeitet wurde, darf der Tag NICHT als Zwangsurlaub zaehlen.
        // - Gleiches gilt, wenn an diesem Tag bereits andere Kennzeichen (z. B. Urlaub/Krank/Arzt/Kurzarbeit/Sonstiges) gesetzt sind.
        // - Krankzeiten werden teils nur als Zeitraum gepflegt (ohne Tages-Override) -> auch dann blockieren.
        try {
            $db = Database::getInstanz();
            $twRows = $db->fetchAlle(
                'SELECT datum,
                        ist_stunden AS arbeitszeit_stunden,
                        kommen_roh, gehen_roh, kommen_korr, gehen_korr,
                        kennzeichen_feiertag,
                        kennzeichen_urlaub,
                        kennzeichen_arzt,
                        kennzeichen_krank_lfz,
                        kennzeichen_krank_kk,
                        kennzeichen_kurzarbeit,
                        kennzeichen_sonstiges
                 FROM tageswerte_mitarbeiter
                 WHERE mitarbeiter_id = :mid
                   AND datum BETWEEN :von AND :bis',
                [
                    'mid' => $mitarbeiterId,
                    'von' => $von->format('Y-m-d'),
                    'bis' => $bis->format('Y-m-d'),
                ]
            );

            foreach ($twRows as $tw) {
                if (!is_array($tw)) {
                    continue;
                }

                $ymd = (string)($tw['datum'] ?? '');
                if ($ymd === '') {
                    continue;
                }

                // "hat gearbeitet" – robust: >0h ODER Kommen/Gehen gesetzt
                $hatArbeitszeit = false;
                $arbStd = (float)str_replace(',', '.', (string)($tw['arbeitszeit_stunden'] ?? '0'));
                if ($arbStd > 0.0) {
                    $hatArbeitszeit = true;
                }

                if (!$hatArbeitszeit) {
                    $k1 = trim((string)($tw['kommen_roh'] ?? ''));
                    $g1 = trim((string)($tw['gehen_roh'] ?? ''));
                    $k2 = trim((string)($tw['kommen_korr'] ?? ''));
                    $g2 = trim((string)($tw['gehen_korr'] ?? ''));
                    if ($k1 !== '' || $g1 !== '' || $k2 !== '' || $g2 !== '') {
                        $hatArbeitszeit = true;
                    }
                }

                // Wenn andere Kennzeichen gesetzt sind (z. B. Urlaub/Krank), soll BF nicht zusaetzlich zaehlen.
                $hatAndereKennzeichen = (
                    ((int)($tw['kennzeichen_feiertag'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_urlaub'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_arzt'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_krank_lfz'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_krank_kk'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_kurzarbeit'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_sonstiges'] ?? 0) === 1)
                );

                if ($hatArbeitszeit || $hatAndereKennzeichen) {
                    $skipBetriebsferienTage[$ymd] = true;
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Betriebsferien-Zaehler: Tageswerte fuer Skip konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von->format('Y-m-d'),
                    'bis'            => $bis->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }

        // Krankzeitraum (LFZ/KK) soll BF-Zwangsurlaub NICHT abziehen.
        // Wenn ein aktiver Krankzeitraum einen BF-Tag schneidet, wird dieser Tag aus der BF-Zaehllogik herausgenommen.
        try {
            $db = Database::getInstanz();
            $krankRows = $db->fetchAlle(
                'SELECT von_datum, bis_datum
                   FROM krankzeitraum
                  WHERE mitarbeiter_id = :mid
                    AND aktiv = 1
                    AND von_datum <= :bis
                    AND (bis_datum IS NULL OR bis_datum >= :von)',
                [
                    'mid' => $mitarbeiterId,
                    'von' => $von->format('Y-m-d'),
                    'bis' => $bis->format('Y-m-d'),
                ]
            );

            foreach ($krankRows as $kr) {
                if (!is_array($kr)) {
                    continue;
                }

                $kVon = $this->parseDatum((string)($kr['von_datum'] ?? ''));
                if ($kVon === null) {
                    continue;
                }

                $kBis = null;
                if (($kr['bis_datum'] ?? null) !== null && (string)$kr['bis_datum'] !== '') {
                    $kBis = $this->parseDatum((string)$kr['bis_datum']);
                }
                if ($kBis === null) {
                    $kBis = $bis;
                }

                if ($kVon < $von) {
                    $kVon = $von;
                }
                if ($kBis > $bis) {
                    $kBis = $bis;
                }

                $d = $kVon;
                while ($d <= $kBis) {
                    $ymd = $d->format('Y-m-d');

                    // Nur BF-Arbeitstage sind relevant
                    if (!isset($betriebsferienTage[$ymd])) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    $wochentag = (int)$d->format('N');
                    if ($wochentag >= 6) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    if (isset($feiertageFrei[$ymd])) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    $skipBetriebsferienTage[$ymd] = true;
                    $d = $d->modify('+1 day');
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Betriebsferien-Zaehler: Krankzeitraeume fuer Skip konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von->format('Y-m-d'),
                    'bis'            => $bis->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }


        $arbeitstage = 0;
        $cursor = $von;

        while ($cursor <= $bis) {
            $ymd = $cursor->format('Y-m-d');

            // 1..7 (Mo..So)
            $wochentag = (int)$cursor->format('N');
            if ($wochentag >= 6) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if (isset($feiertageFrei[$ymd])) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if (isset($betriebsferienTage[$ymd])) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $arbeitstage++;
            $cursor = $cursor->modify('+1 day');
        }

        return $arbeitstage;
    }

    /**
     * Berechnet die Anzahl der **Arbeitstage** (inkl. Start- und Endtag),
     * wobei Wochenenden und betriebsfreie Feiertage **nicht** zählen.
     *
     * WICHTIG:
     * - Betriebsferien werden hier **nicht** ausgeschlossen.
     * - Zweck ist z. B. die Anzeige „benötigte Urlaubstage“ für Betriebsferien-Zeiträume
     *   in der Terminal-Urlaub-Übersicht.
     */
    public function berechneArbeitstageOhneBetriebsferien(int $mitarbeiterId, string $vonDatum, string $bisDatum): int
    {
        // Signatur behält `mitarbeiterId`, damit Aufrufer konsistent bleiben.
        // Derzeit wird nur das globale Bundesland aus der Konfiguration genutzt.
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return 0;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);

        if ($von === null || $bis === null) {
            return 0;
        }

        if ($von > $bis) {
            return 0;
        }

        // Schutz vor extremen Bereichen
        try {
            $tageDiff = (int)$von->diff($bis)->days;
        } catch (\Throwable $e) {
            $tageDiff = 0;
        }

        if ($tageDiff > 3650) { // 10 Jahre
            return 0;
        }

        $bundesland = $this->holeBundeslandAusConfig();
        $feiertageFrei = $this->holeBetriebsfreieFeiertageSet($von->format('Y-m-d'), $bis->format('Y-m-d'), $bundesland);

        $arbeitstage = 0;
        $cursor = $von;

        while ($cursor <= $bis) {
            $ymd = $cursor->format('Y-m-d');

            // 1..7 (Mo..So)
            $wochentag = (int)$cursor->format('N');
            if ($wochentag >= 6) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if (isset($feiertageFrei[$ymd])) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $arbeitstage++;
            $cursor = $cursor->modify('+1 day');
        }

        return $arbeitstage;
    }

    /**
     * Convenience: liefert `tage_gesamt` als DECIMAL(5,2)-String.
     */
    public function berechneTageGesamtAlsArbeitstageString(int $mitarbeiterId, string $vonDatum, string $bisDatum): string
    {
        $tage = $this->berechneArbeitstageFuerMitarbeiter($mitarbeiterId, $vonDatum, $bisDatum);
        return number_format((float)$tage, 2, '.', '');
    }

    /**
     * Berechnet einen einfachen Urlaubssaldo für ein Kalenderjahr.
     *
     * Ziel (MVP): Anzeige in „Meine Urlaubsanträge“:
     * - Anspruch (aus `mitarbeiter.urlaub_monatsanspruch` * 12)
     *   oder optional als Override pro Jahr aus `urlaub_kontingent_jahr.anspruch_override_tage`.
     * - Genommen (Status = genehmigt) – als Arbeitstage
     * - Beantragt (Status = offen) – als Arbeitstage
     * - Übertrag + Korrektur (optional pro Jahr) werden zusätzlich berücksichtigt.
     * - Verbleibend = Anspruch + Übertrag + Korrektur - Genommen - Beantragt
     *
     * Wichtig:
     * - Wir rechnen bewusst mit **Arbeitstagen** (Mo–Fr, Feiertage/Betriebsferien zählen nicht),
     *   damit es konsistent zu `tage_gesamt` (T-013) bleibt.
     * - Übertrag wird automatisch aus dem Resturlaub des Vorjahres abgeleitet (MasterPrompt v8).
     * - Korrekturen/Anspruch-Override kommen optional aus `urlaub_kontingent_jahr`.
     *
     * @return array<string,string|int>
     */
    public function berechneUrlaubssaldoFuerJahr(int $mitarbeiterId, int $jahr, bool $autoUebertrag = true): array
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return [
                'jahr' => (int)$jahr,
                'anspruch' => '0.00',
                'uebertrag' => '0.00',
                'korrektur' => '0.00',
                'genommen' => '0.00',
                'beantragt' => '0.00',
                'verbleibend' => '0.00',
                'hinweis' => 'Mitarbeiter-ID ungültig.',
            ];
        }

        $jahr = (int)$jahr;
        if ($jahr < 2000 || $jahr > 2100) {
            // defensiv normalisieren
            $jahr = (int)(new \DateTimeImmutable('now'))->format('Y');
        }

        $start = new \DateTimeImmutable(sprintf('%04d-01-01', $jahr));
        $ende  = new \DateTimeImmutable(sprintf('%04d-12-31', $jahr));

        $db = Database::getInstanz();

        // Anspruch (Standard): aus Mitarbeiterstammdaten
        // Hinweis: Wenn der Monatsanspruch bei einem Mitarbeiter nicht gepflegt ist (0.00),
        // darf die Berechnung nicht „still“ auf 0 fallen, weil sonst z. B. Betriebsferien
        // den Resturlaub unplausibel ins Negative drücken. In diesem Fall nutzen wir
        // einen Standardwert aus der Konfiguration (oder als Fallback 2.50 = 30 Tage/Jahr).
        $monatsanspruch = 0.0;
        $hinweis = '';
        $mitarbeiterErstelltAm = null;
        $hatExplizitesEintrittsdatum = false;

        try {
            // Optional: `mitarbeiter.eintrittsdatum` (DATE) hat Vorrang.
            // Fallback: `mitarbeiter.erstellt_am` (Anlagedatum im System).
            // WICHTIG: Viele Alt-Installationen haben die Spalte (noch) nicht → Query-Fallback.
            try {
                $row = $db->fetchEine(
                    'SELECT urlaub_monatsanspruch, erstellt_am, eintrittsdatum FROM mitarbeiter WHERE id = :id LIMIT 1',
                    ['id' => $mitarbeiterId]
                );
            } catch (\Throwable $eInner) {
                $row = $db->fetchEine(
                    'SELECT urlaub_monatsanspruch, erstellt_am FROM mitarbeiter WHERE id = :id LIMIT 1',
                    ['id' => $mitarbeiterId]
                );
            }

            if (is_array($row)) {
                if (isset($row['urlaub_monatsanspruch'])) {
                    $monatsanspruch = (float)$row['urlaub_monatsanspruch'];
                }

                $basis = null;
                if (isset($row['eintrittsdatum']) && $row['eintrittsdatum'] !== null && trim((string)$row['eintrittsdatum']) !== '') {
                    $basis = (string)$row['eintrittsdatum'];
                    $hatExplizitesEintrittsdatum = true;
                } elseif (isset($row['erstellt_am']) && $row['erstellt_am'] !== null && trim((string)$row['erstellt_am']) !== '') {
                    $basis = (string)$row['erstellt_am'];
                }

                if ($basis !== null) {
                    try {
                        $mitarbeiterErstelltAm = new \DateTimeImmutable($basis);
                    } catch (\Throwable $e) {
                        $mitarbeiterErstelltAm = null;
                    }
                }
            }
        } catch (\Throwable $e) {
            $hinweis = 'Anspruch konnte nicht geladen werden.';
        }

        // Fallback: Standard-Anspruch aus Konfiguration, wenn Mitarbeiterwert 0 ist.
        if ($monatsanspruch <= 0.00001) {
            $stdQuelle = '';
            $stdMonat = 0.0;

            try {
                if (class_exists('KonfigurationService')) {
                    $cfg = KonfigurationService::getInstanz();

                    // Primär: urlaub_standard_monatsanspruch (z. B. 2.5)
                    $v1 = $cfg->get('urlaub_standard_monatsanspruch', null);
                    if ($v1 !== null) {
                        $t = trim((string)$v1);
                        $t = str_replace(',', '.', $t);
                        if (is_numeric($t)) {
                            $stdMonat = (float)$t;
                            $stdQuelle = 'config:urlaub_standard_monatsanspruch';
                        }
                    }

                    // Sekundär: urlaub_standard_jahresanspruch (z. B. 30) -> /12
                    if ($stdMonat <= 0.00001) {
                        $v2 = $cfg->get('urlaub_standard_jahresanspruch', null);
                        if ($v2 !== null) {
                            $t = trim((string)$v2);
                            $t = str_replace(',', '.', $t);
                            if (is_numeric($t)) {
                                $jahrStd = (float)$t;
                                if ($jahrStd > 0.0) {
                                    $stdMonat = $jahrStd / 12.0;
                                    $stdQuelle = 'config:urlaub_standard_jahresanspruch';
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignorieren (defensiv)
                $stdQuelle = '';
                $stdMonat = 0.0;
            }

            if ($stdMonat <= 0.00001) {
                $stdMonat = 2.5; // 30 Tage/Jahr
                $stdQuelle = 'fallback:2.50';
            }

            $monatsanspruch = $stdMonat;

            $hinweis = trim(($hinweis !== '' ? $hinweis . ' ' : '')
                . 'Hinweis: Mitarbeiter.urlaub_monatsanspruch ist 0.00 – Standardanspruch verwendet ('
                . $stdQuelle . ').');
        }

        // Übertrag/Korrektur/Override pro Jahr (optional)
        $uebertrag = 0.0;
        $korrektur = 0.0;
        $anspruchOverride = null;

        $kRow = null;
        try {
            $kRow = $db->fetchEine(
                'SELECT anspruch_override_tage, uebertrag_tage, korrektur_tage
                 FROM urlaub_kontingent_jahr
                 WHERE mitarbeiter_id = :mid AND jahr = :jahr
                 LIMIT 1',
                [
                    'mid'  => $mitarbeiterId,
                    'jahr' => $jahr,
                ]
            );
        } catch (\Throwable $e) {
            // Fallback: Manche Installationen haben die Spalte `uebertrag_tage` noch nicht.
            // Dann darf der gesamte Kontingent-Block (inkl. Korrektur!) nicht ausfallen.
            try {
                $kRow = $db->fetchEine(
                    'SELECT anspruch_override_tage, korrektur_tage
                     FROM urlaub_kontingent_jahr
                     WHERE mitarbeiter_id = :mid AND jahr = :jahr
                     LIMIT 1',
                    [
                        'mid'  => $mitarbeiterId,
                        'jahr' => $jahr,
                    ]
                );

                $hinweis = ($hinweis !== '' ? $hinweis . ' ' : '') . 'Hinweis: Urlaubskontingent-Tabelle ist unvollständig (Spalte uebertrag_tage fehlt). Bitte DB aktualisieren.';
            } catch (\Throwable $e2) {
                // Wichtig: Wenn die Tabelle noch nicht eingespielt wurde, darf die Seite nicht crashen.
                // Wir fallen dann auf 0.00 zurück und geben einen Hinweis aus.
                $hinweis = ($hinweis !== '' ? $hinweis . ' ' : '') . 'Hinweis: Urlaubskontingent (Übertrag/Korrektur) ist noch nicht verfügbar (DB-Update fehlt?).';
            }
        }

        if (is_array($kRow) && $kRow !== []) {
            if (array_key_exists('anspruch_override_tage', $kRow) && $kRow['anspruch_override_tage'] !== null) {
                $anspruchOverride = (float)$kRow['anspruch_override_tage'];
            }

            // `uebertrag_tage` wird nur genutzt, wenn Auto-Übertrag bewusst deaktiviert ist (z. B. Vorjahr-Berechnung ohne Rekursion).
            if (!$autoUebertrag && array_key_exists('uebertrag_tage', $kRow) && $kRow['uebertrag_tage'] !== null) {
                $uebertrag = (float)$kRow['uebertrag_tage'];
            }

            if (array_key_exists('korrektur_tage', $kRow) && $kRow['korrektur_tage'] !== null) {
                $korrektur = (float)$kRow['korrektur_tage'];
            }
        }

        // Anspruch: Override hat Vorrang.
        // Sonst: Monatsanspruch * 12, aber im Eintrittsjahr nur ab Eintrittsmonat.
        // Damit erhalten neu angelegte Mitarbeiter (z. B. im Dezember) nur den Monatsanspruch dieses Monats.
        if ($anspruchOverride !== null) {
            $anspruch = (float)$anspruchOverride;
        } else {
            $monateFuerAnspruch = 12;

            if ($mitarbeiterErstelltAm instanceof \DateTimeImmutable) {
                $eintrittJahr = (int)$mitarbeiterErstelltAm->format('Y');

                if ($eintrittJahr > $jahr) {
                    // Mitarbeiter existiert in diesem Jahr noch nicht.
                    $monateFuerAnspruch = 0;

                    $hinweis = trim(($hinweis !== '' ? $hinweis . ' ' : '')
                        . 'Hinweis: Mitarbeiter ist in diesem Jahr noch nicht angelegt (Anspruch 0.00).');
                } elseif ($eintrittJahr === $jahr) {
                    // Eintrittsjahr: Anspruch erst ab Eintrittsmonat.
                    $eintrittMonat = (int)$mitarbeiterErstelltAm->format('n');
                    $monateFuerAnspruch = 12 - $eintrittMonat + 1;

                    $hinweis = trim(($hinweis !== '' ? $hinweis . ' ' : '')
                        . 'Hinweis: Anspruch im Eintrittsjahr anteilig ab Monat '
                        . $mitarbeiterErstelltAm->format('m')
                        . ' (' . $mitarbeiterErstelltAm->format('Y-m-d')
                        . ($hatExplizitesEintrittsdatum ? ', Eintrittsdatum' : ', erstellt_am')
                        . ').');
                }
            }

            if ($monateFuerAnspruch < 0) {
                $monateFuerAnspruch = 0;
            } elseif ($monateFuerAnspruch > 12) {
                $monateFuerAnspruch = 12;
            }

            $anspruch = $monatsanspruch * (float)$monateFuerAnspruch;
        }

        if ($anspruch <= 0.00001) {
            $hinweis = ($hinweis !== '' ? $hinweis . ' ' : '') . 'Urlaubsanspruch ist 0. Bitte im Mitarbeiterstamm pflegen (oder Jahres-Override setzen).';
        }

        // Sets einmalig für das ganze Jahr laden (Performance + konsistente Berechnung)
        $bundesland = $this->holeBundeslandAusConfig();
        $feiertageFrei = $this->holeBetriebsfreieFeiertageSet($start->format('Y-m-d'), $ende->format('Y-m-d'), $bundesland);
        $betriebsferienTage = $this->holeBetriebsferienTageSetFuerMitarbeiter($mitarbeiterId, $start, $ende);

        // Anträge im Jahr laden (genehmigt + offen), auch wenn sie über Jahresgrenzen hinaus laufen.
        $sql = "SELECT id, von_datum, bis_datum, status\n"
             . "FROM urlaubsantrag\n"
             . "WHERE mitarbeiter_id = :mid\n"
             . "  AND status IN ('genehmigt','offen')\n"
             . "  AND NOT (bis_datum < :start OR von_datum > :ende)\n";

        $rows = [];
        try {
            $rows = $db->fetchAlle($sql, [
                'mid'   => $mitarbeiterId,
                'start' => $start->format('Y-m-d'),
                'ende'  => $ende->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            $rows = [];
            if ($hinweis !== '') {
                $hinweis .= ' ';
            }
            $hinweis .= 'Urlaubsanträge konnten nicht geladen werden.';

            if (class_exists('Logger')) {
                Logger::warn('Urlaubssaldo: Fehler beim Laden der Urlaubsanträge', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }

        $genommen = 0.0;
        $beantragt = 0.0;

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }

            $status = (string)($r['status'] ?? '');
            $von = $this->parseDatum((string)($r['von_datum'] ?? ''));
            $bis = $this->parseDatum((string)($r['bis_datum'] ?? ''));
            if ($von === null || $bis === null) {
                continue;
            }

            if ($von > $bis) {
                continue;
            }

            // auf Jahresgrenzen clippen
            $vonClip = ($von < $start) ? $start : $von;
            $bisClip = ($bis > $ende) ? $ende : $bis;
            if ($vonClip > $bisClip) {
                continue;
            }

            $tage = (float)$this->zaehleArbeitstageZwischenMitSets($vonClip, $bisClip, $feiertageFrei, $betriebsferienTage);

            if ($status === 'genehmigt') {
                $genommen += $tage;
            } elseif ($status === 'offen') {
                $beantragt += $tage;
            }
        }

        // Betriebsferien zählen als Urlaub (Zwangsurlaub): nur Arbeitstage, keine Feiertage/Wochenenden.
        // Urlaubsanträge werden bereits ohne Betriebsferien-Tage gezählt (siehe zaehleArbeitstageZwischenMitSets),
        // damit es zu keiner doppelten Zählung bei überlappenden Zeiträumen kommt.
        // WICHTIG:
        // - Wenn an einem Betriebsferien-Tag gearbeitet wurde, darf der Tag NICHT zusätzlich als Urlaub zählen.
        // - Gleiches gilt, wenn an diesem Tag bereits andere Kennzeichen (z. B. krank) gesetzt sind.

        /** @var array<string,bool> $skipBetriebsferienTage */
        $skipBetriebsferienTage = [];
        try {
            $db = Database::getInstanz();
            $twRows = $db->fetchAlle(
                'SELECT datum,
                        ist_stunden AS arbeitszeit_stunden,
                        kommen_roh, gehen_roh, kommen_korr, gehen_korr,
                        kennzeichen_feiertag,
                        kennzeichen_urlaub,
                        kennzeichen_arzt,
                        kennzeichen_krank_lfz,
                        kennzeichen_krank_kk,
                        kennzeichen_kurzarbeit,
                        kennzeichen_sonstiges
                 FROM tageswerte_mitarbeiter
                 WHERE mitarbeiter_id = :mid
                   AND datum BETWEEN :von AND :bis',
                [
                    'mid' => $mitarbeiterId,
                    'von' => $start->format('Y-m-d'),
                    'bis' => $ende->format('Y-m-d'),
                ]
            );

            foreach ($twRows as $tw) {
                if (!is_array($tw)) {
                    continue;
                }

                $ymd = (string)($tw['datum'] ?? '');
                if ($ymd === '') {
                    continue;
                }

                // "hat gearbeitet" – robust: >0h ODER Kommen/Gehen gesetzt
                $hatArbeitszeit = false;
                $arbStd = (float)str_replace(',', '.', (string)($tw['arbeitszeit_stunden'] ?? '0'));
                if ($arbStd > 0.0) {
                    $hatArbeitszeit = true;
                }

                if (!$hatArbeitszeit) {
                    $k1 = trim((string)($tw['kommen_roh'] ?? ''));
                    $g1 = trim((string)($tw['gehen_roh'] ?? ''));
                    $k2 = trim((string)($tw['kommen_korr'] ?? ''));
                    $g2 = trim((string)($tw['gehen_korr'] ?? ''));
                    if ($k1 !== '' || $g1 !== '' || $k2 !== '' || $g2 !== '') {
                        $hatArbeitszeit = true;
                    }
                }

                // Wenn andere Kennzeichen gesetzt sind (z. B. krank), soll Betriebsferien nicht zusätzlich als Urlaub zählen.
                $hatAndereKennzeichen = (
                    ((int)($tw['kennzeichen_feiertag'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_urlaub'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_arzt'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_krank_lfz'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_krank_kk'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_kurzarbeit'] ?? 0) === 1)
                    || ((int)($tw['kennzeichen_sonstiges'] ?? 0) === 1)
                );

                if ($hatArbeitszeit || $hatAndereKennzeichen) {
                    $skipBetriebsferienTage[$ymd] = true;
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Urlaubssaldo: Tageswerte für Betriebsferien-Skip konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }

        // Krankzeitraum (LFZ/KK) soll Betriebsferien-Urlaub NICHT abziehen: wenn ein aktiver Krankzeitraum
        // einen Betriebsferien-Tag schneidet, wird dieser Tag aus dem Zwangsurlaub herausgenommen.
        // Hintergrund: Krankzeiten werden oft nur als Zeitraum gepflegt (ohne Tages-Override in tageswerte_mitarbeiter).
        try {
            $db = Database::getInstanz();
            $krankRows = $db->fetchAlle(
                'SELECT von_datum, bis_datum
                 FROM krankzeitraum
                 WHERE mitarbeiter_id = :mid
                   AND aktiv = 1
                   AND von_datum <= :bis
                   AND (bis_datum IS NULL OR bis_datum >= :von)',
                [
                    'mid' => $mitarbeiterId,
                    'von' => $start->format('Y-m-d'),
                    'bis' => $ende->format('Y-m-d'),
                ]
            );

            foreach ($krankRows as $kr) {
                if (!is_array($kr)) {
                    continue;
                }

                $vonK = $this->parseDatum((string)($kr['von_datum'] ?? ''));
                if ($vonK === null) {
                    continue;
                }

                $bisK = $this->parseDatum((string)($kr['bis_datum'] ?? ''));
                if ($bisK === null) {
                    // Offener Zeitraum: bis Jahresende clippen
                    $bisK = $ende;
                }

                if ($vonK > $bisK) {
                    continue;
                }

                // auf Jahresgrenzen clippen
                $vonClip = ($vonK < $start) ? $start : $vonK;
                $bisClip = ($bisK > $ende) ? $ende : $bisK;
                if ($vonClip > $bisClip) {
                    continue;
                }

                $d = $vonClip;
                while ($d <= $bisClip) {
                    $ymd = $d->format('Y-m-d');

                    // Wir interessieren uns nur für Betriebsferien-Tage, die sonst als Zwangsurlaub zählen würden.
                    if (!isset($betriebsferienTage[$ymd])) {
                        $d = $d->modify('+1 day');
                        continue;
                    }
                    if (isset($feiertageFrei[$ymd])) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    $wochentag = (int)$d->format('N'); // 1..7 (Mo..So)
                    if ($wochentag >= 6) {
                        $d = $d->modify('+1 day');
                        continue;
                    }

                    $skipBetriebsferienTage[$ymd] = true;
                    $d = $d->modify('+1 day');
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Urlaubssaldo: Krankzeitraum für Betriebsferien-Skip konnte nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
        }

        // Wichtig: Saldo-Berechnung muss exakt den gleichen Weg nutzen wie die Anzeige pro Betriebsferien-Block,
        // sonst entstehen Abweichungen (z. B. 9.50 vs 10.00). Daher: zentrale Zaehlung ueber die gleiche Funktion.
        $betriebsferienUrlaubTage = 0.0;
        try {
            $betriebsferienUrlaubTage = (float)$this->zaehleBetriebsferienArbeitstageFuerMitarbeiter(
                $mitarbeiterId,
                $start->format('Y-m-d'),
                $ende->format('Y-m-d')
            );
        } catch (\Throwable $e) {
            if (class_exists('LoggerService')) {
                (new LoggerService())->log('WARN', 'UrlaubService: Betriebsferien-Tage konnten nicht sauber gezaehlt werden (Fallback=0).', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaubservice');
            }
            $betriebsferienUrlaubTage = 0.0;
        }

        if ($betriebsferienUrlaubTage > 0.00001) {
            $genommen += $betriebsferienUrlaubTage;
        }
        // v8: Übertrag automatisch aus dem Resturlaub des Vorjahres ableiten (ohne manuelle Pflege).
        if ($autoUebertrag && $jahr > 2000) {
            try {
                $vorjahrSaldo = $this->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr - 1, false);
                $restVorjahr = 0.0;
                if (is_array($vorjahrSaldo) && isset($vorjahrSaldo['verbleibend'])) {
                    $v = $vorjahrSaldo['verbleibend'];
                    if (is_numeric($v)) {
                        $restVorjahr = (float)$v;
                    } elseif (is_string($v)) {
                        $v = trim($v);
                        $v = str_replace(',', '.', $v);
                        $restVorjahr = is_numeric($v) ? (float)$v : 0.0;
                    }
                }
                // Negativer Resturlaub soll ins Folgejahr mitgenommen werden (z. B. Eintritt im Dez + Betriebsferien).
                $uebertrag = $restVorjahr;
            } catch (\Throwable $e) {
                // defensiv: nie hart crashen
                if ($hinweis !== '') {
                    $hinweis .= ' ';
                }
                $hinweis .= 'Übertrag (Vorjahr) konnte nicht automatisch berechnet werden.';
                if (class_exists('Logger')) {
                    Logger::warn('Urlaubssaldo: Auto-Übertrag (Vorjahr) fehlgeschlagen', [
                        'mitarbeiter_id' => $mitarbeiterId,
                        'jahr'           => $jahr,
                        'exception'      => $e->getMessage(),
                    ], $mitarbeiterId, null, 'urlaubservice');
                }
            }
        }

        $verbleibend = $anspruch + $uebertrag + $korrektur - $genommen - $beantragt;

        return [
            'jahr'       => $jahr,
            'anspruch'   => number_format($anspruch, 2, '.', ''),
            'uebertrag'  => number_format($uebertrag, 2, '.', ''),
            'korrektur'  => number_format($korrektur, 2, '.', ''),
            'genommen'   => number_format($genommen, 2, '.', ''),
            'beantragt'  => number_format($beantragt, 2, '.', ''),
            'verbleibend'=> number_format($verbleibend, 2, '.', ''),
            'hinweis'    => $hinweis,
        ];
    }

    /**
     * Konfigurationsschalter:
     * Wenn aktiv, sollen Urlaubsanträge blockiert werden, sobald der Resturlaub negativ würde.
     */
    public function istNegativerResturlaubGeblockt(): bool
    {
        try {
            if (class_exists('KonfigurationService')) {
                return KonfigurationService::getInstanz()->getBool('urlaub_blocke_negativen_resturlaub', false);
            }
        } catch (Throwable $e) {
            // defensiv: nie hart crashen
        }

        return false;
    }

    /**
     * Prüft vor dem Anlegen eines neuen Antrags, ob der Resturlaub dadurch negativ würde.
     *
     * Rückgabe:
     * - null: ok (oder Prüfung nicht möglich)
     * - string: Fehlermeldung
     */
    public function pruefeNegativenResturlaubBeiNeuemAntrag(int $mitarbeiterId, string $vonDatum, string $bisDatum): ?string
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);
        if ($von === null || $bis === null) {
            return null;
        }

        if ($von > $bis) {
            return null;
        }

        $jahre = $this->ermittleJahreFuerZeitraum($von, $bis);
        if ($jahre === []) {
            return null;
        }

        $fehlerProJahr = [];

        foreach ($jahre as $jahr) {
            $jahrStart = new \DateTimeImmutable(sprintf('%04d-01-01', $jahr));
            $jahrEnde  = new \DateTimeImmutable(sprintf('%04d-12-31', $jahr));

            $clipVon = ($von < $jahrStart) ? $jahrStart : $von;
            $clipBis = ($bis > $jahrEnde) ? $jahrEnde : $bis;

            if ($clipVon > $clipBis) {
                continue;
            }

            $tage = (float)$this->berechneArbeitstageFuerMitarbeiter($mitarbeiterId, $clipVon->format('Y-m-d'), $clipBis->format('Y-m-d'));
            if ($tage <= 0.00001) {
                continue;
            }

            $saldo = $this->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr);
            $verbleibVor = isset($saldo['verbleibend']) ? (float)$saldo['verbleibend'] : 0.0;
            $verbleibNach = $verbleibVor - $tage;

            if ($verbleibNach < -0.00001) {
                $fehlerProJahr[] = sprintf(
                    '%d: verfügbar %s, beantragt %s',
                    $jahr,
                    number_format($verbleibVor, 2, '.', ''),
                    number_format($tage, 2, '.', '')
                );
            }
        }

        if ($fehlerProJahr === []) {
            return null;
        }

        $msg = 'Urlaubsantrag nicht möglich: Der Resturlaub wäre dadurch negativ. (' . implode('; ', $fehlerProJahr) . ')';
        $msg .= ' Bitte Zeitraum anpassen oder Personalbüro kontaktieren.';
        return $msg;
    }

    /**
     * Prüft bei der Genehmigung eines offenen Antrags, ob der Resturlaub bereits negativ ist.
     *
     * Hintergrund:
     * - Saldo berücksichtigt offene Anträge bereits.
     * - Wenn Saldo < 0, soll Genehmigung (optional) blockiert werden.
     */
    public function pruefeNegativenResturlaubBeiGenehmigung(int $mitarbeiterId, string $vonDatum, string $bisDatum): ?string
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return null;
        }

        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);
        if ($von === null || $bis === null) {
            return null;
        }

        if ($von > $bis) {
            return null;
        }

        $jahre = $this->ermittleJahreFuerZeitraum($von, $bis);
        if ($jahre === []) {
            return null;
        }

        $fehlerProJahr = [];

        foreach ($jahre as $jahr) {
            $saldo = $this->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr);
            $verbleib = isset($saldo['verbleibend']) ? (float)$saldo['verbleibend'] : 0.0;

            if ($verbleib < -0.00001) {
                $fehlerProJahr[] = sprintf(
                    '%d: verbleibend %s',
                    $jahr,
                    number_format($verbleib, 2, '.', '')
                );
            }
        }

        if ($fehlerProJahr === []) {
            return null;
        }

        $msg = 'Genehmigung nicht möglich: Resturlaub ist negativ. (' . implode('; ', $fehlerProJahr) . ')';
        $msg .= ' Bitte Urlaubskontingent/Korrektur prüfen.';
        return $msg;
    }

    /**
     * Liefert alle betroffenen Jahre (inkl.) für einen Zeitraum.
     *
     * @return int[]
     */
    private function ermittleJahreFuerZeitraum(\DateTimeImmutable $von, \DateTimeImmutable $bis): array
    {
        $jahrVon = (int)$von->format('Y');
        $jahrBis = (int)$bis->format('Y');

        if ($jahrVon < 2000 || $jahrVon > 2100 || $jahrBis < 2000 || $jahrBis > 2100) {
            return [];
        }

        if ($jahrBis < $jahrVon) {
            return [];
        }

        $jahre = [];
        for ($j = $jahrVon; $j <= $jahrBis; $j++) {
            $jahre[] = $j;
        }

        return $jahre;
    }

    /**
     * Zählt Arbeitstage zwischen zwei Daten (inkl. Start/Ende) auf Basis vorab geladener Sets.
     *
     * @param array<string,bool> $feiertageFrei
     * @param array<string,bool> $betriebsferienTage
     */
    private function zaehleArbeitstageZwischenMitSets(\DateTimeImmutable $von, \DateTimeImmutable $bis, array $feiertageFrei, array $betriebsferienTage): float
    {
        if ($von > $bis) {
            return 0;
        }

        $arbeitstage = 0.0;
        $cursor = $von;

        while ($cursor <= $bis) {
            $ymd = $cursor->format('Y-m-d');
            $wochentag = (int)$cursor->format('N'); // 1..7 (Mo..So)

            if ($wochentag < 6 && !isset($feiertageFrei[$ymd]) && !isset($betriebsferienTage[$ymd])) {
                $arbeitstage += $this->urlaubTagesGewicht($ymd);
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $arbeitstage;
    }

    private function parseDatum(string $datum): ?\DateTimeImmutable
    {
        $datum = trim($datum);
        if ($datum === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $datum);
        if (!$dt || $dt->format('Y-m-d') !== $datum) {
            return null;
        }

        return $dt;
    }

    /**
     * Optional: Bundesland aus der DB-Konfiguration.
     *
     * Erwarteter Key: `bundesland` (z. B. 'HE', 'BY').
     * Wenn nicht gesetzt, werden nur bundeseinheitliche Feiertage (`bundesland IS NULL`) verwendet.
     */
    private function holeBundeslandAusConfig(): ?string
    {
        try {
            $cfg = KonfigurationService::getInstanz();
            $bl = $cfg->get('bundesland', null);
        } catch (\Throwable $e) {
            $bl = null;
        }

        if ($bl === null) {
            return null;
        }

        $bl = trim((string)$bl);
        return $bl !== '' ? $bl : null;
    }

    /**
     * Liefert ein Set (`Y-m-d` => true) aller betriebsfreien Feiertage im Zeitraum.
     *
     * Precedence:
     * - Wenn ein bundeslandspezifischer Feiertag existiert, überschreibt er den bundesweiten Eintrag.
     */
    private function holeBetriebsfreieFeiertageSet(string $vonDatum, string $bisDatum, ?string $bundesland): array
    {
        // Sicherstellen, dass Feiertage für alle betroffenen Jahre generiert sind
        $von = $this->parseDatum($vonDatum);
        $bis = $this->parseDatum($bisDatum);
        if ($von === null || $bis === null) {
            return [];
        }

        $vonJahr = (int)$von->format('Y');
        $bisJahr = (int)$bis->format('Y');

        try {
            $feiertagService = FeiertagService::getInstanz();
            for ($jahr = $vonJahr; $jahr <= $bisJahr; $jahr++) {
                $feiertagService->generiereFeiertageFuerJahrWennNoetig($jahr);
            }
        } catch (\Throwable $e) {
            // Wenn die Generierung fehlschlägt (DB down o. ä.), geben wir lieber ein leeres Set zurück.
            return [];
        }

        $db = Database::getInstanz();

        try {
            if ($bundesland !== null && $bundesland !== '') {
                $sql = 'SELECT datum, bundesland, ist_betriebsfrei
                        FROM feiertag
                        WHERE datum >= :von
                          AND datum <= :bis
                          AND (bundesland = :bundesland OR bundesland IS NULL)
                        ORDER BY datum ASC, bundesland IS NULL ASC';

                $rows = $db->fetchAlle($sql, [
                    'von'        => $vonDatum,
                    'bis'        => $bisDatum,
                    'bundesland' => $bundesland,
                ]);
            } else {
                $sql = 'SELECT datum, bundesland, ist_betriebsfrei
                        FROM feiertag
                        WHERE datum >= :von
                          AND datum <= :bis
                          AND bundesland IS NULL
                        ORDER BY datum ASC';

                $rows = $db->fetchAlle($sql, [
                    'von' => $vonDatum,
                    'bis' => $bisDatum,
                ]);
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden von Feiertagen für Arbeitstage-Berechnung', [
                    'von'       => $vonDatum,
                    'bis'       => $bisDatum,
                    'bundesland'=> $bundesland,
                    'exception' => $e->getMessage(),
                ], null, null, 'urlaubservice');
            }
            return [];
        }

        $gesetzt = [];
        $set = [];

        foreach ($rows as $row) {
            $datum = isset($row['datum']) ? (string)$row['datum'] : '';
            if ($datum === '') {
                continue;
            }

            // First row per date wins (ORDER BY ensures specific before NULL)
            if (isset($gesetzt[$datum])) {
                continue;
            }

            $gesetzt[$datum] = true;

            $istBetriebsfrei = (int)($row['ist_betriebsfrei'] ?? 0) === 1;
            if ($istBetriebsfrei) {
                $set[$datum] = true;
            }
        }

        return $set;
    }

    /**
     * Liefert ein Set (`Y-m-d` => true) aller Betriebsferien-Tage (global + Abteilung) im Zeitraum.
     */
    private function holeBetriebsferienTageSetFuerMitarbeiter(int $mitarbeiterId, \DateTimeImmutable $von, \DateTimeImmutable $bis): array
    {
        $set = [];

        $betriebsferienModel = new BetriebsferienModel();
        $abteilungModel = new MitarbeiterHatAbteilungModel();

        $abteilungsIds = [];
        try {
            $abteilungsIds = $abteilungModel->holeAbteilungsIdsFuerMitarbeiter($mitarbeiterId);
        } catch (\Throwable $e) {
            $abteilungsIds = [];
        }

        // Globale Betriebsferien
        $ranges = [];
        try {
            $ranges = $betriebsferienModel->holeAktive(null);
        } catch (\Throwable $e) {
            $ranges = [];
        }

        foreach ($ranges as $row) {
            $this->fuegeBetriebsferienRangeInSet($set, $row, $von, $bis);
        }

        // Abteilungsspezifische Betriebsferien
        foreach ($abteilungsIds as $abteilungId) {
            $abteilungId = (int)$abteilungId;
            if ($abteilungId <= 0) {
                continue;
            }

            try {
                $ranges = $betriebsferienModel->holeAktive($abteilungId);
            } catch (\Throwable $e) {
                $ranges = [];
            }

            foreach ($ranges as $row) {
                $this->fuegeBetriebsferienRangeInSet($set, $row, $von, $bis);
            }
        }

        return $set;
    }

    /**
     * Expandiert einen Betriebsferien-Datensatz (Range) in das Set, begrenzt auf [von..bis].
     *
     * @param array<string,bool> $set
     * @param array<string,mixed> $row
     */
    private function fuegeBetriebsferienRangeInSet(array &$set, array $row, \DateTimeImmutable $von, \DateTimeImmutable $bis): void
    {
        $vonDatum = isset($row['von_datum']) ? trim((string)$row['von_datum']) : '';
        $bisDatum = isset($row['bis_datum']) ? trim((string)$row['bis_datum']) : '';

        $rangeVon = $this->parseDatum($vonDatum);
        $rangeBis = $this->parseDatum($bisDatum);

        if ($rangeVon === null || $rangeBis === null) {
            return;
        }

        if ($rangeVon > $rangeBis) {
            return;
        }

        // Overlap
        $start = $rangeVon > $von ? $rangeVon : $von;
        $ende  = $rangeBis < $bis ? $rangeBis : $bis;

        if ($start > $ende) {
            return;
        }

        $cursor = $start;
        while ($cursor <= $ende) {
            $set[$cursor->format('Y-m-d')] = true;
            $cursor = $cursor->modify('+1 day');
        }
    }
}
