<?php
declare(strict_types=1);

/**
 * KrankzeitraumService
 *
 * Liest Krank-Zeiträume aus `krankzeitraum` (Typ: LFZ/KK) und kann diese
 * für Auswertungen (Monatsübersicht/PDF) auf Tageswerte anwenden.
 *
 * Ziel (T-071, Teil 3):
 * - Krank LFZ/Krank KK wird im PDF in eigenen Spalten ausgewiesen.
 * - Pflege erfolgt pro Mitarbeiter als Zeitraum (Wechsel = zwei Zeiträume).
 *
 * Ableitungs-Regeln (nur Anzeige, keine DB-Änderungen):
 * - Es wird nur gesetzt, wenn der Tag noch keinen expliziten Krank-Override hat.
 * - Nur auf reguläre Arbeitstage (Mo-Fr, nicht Feiertag) anwenden.
 * - Tage mit anderen Kennzeichen (Urlaub, Kurzarbeit, Arzt, Sonstiges, Feiertag)
 *   werden nicht überschrieben.
 * - Tage mit Arbeitszeit > 0 werden nicht überschrieben (Konflikt wird später separat behandelt).
 * - Stunden: Tages-Soll, falls bekannt; sonst defensiver Fallback 8.00.
 */
class KrankzeitraumService
{
    private static ?KrankzeitraumService $instanz = null;

    private Database $db;
    private FeiertagService $feiertagService;

    private function __construct()
    {
        $this->db = Database::getInstanz();
        $this->feiertagService = FeiertagService::getInstanz();
    }

    public static function getInstanz(): KrankzeitraumService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Lädt alle aktiven Krankzeiträume, die den Zeitraum schneiden.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeZeitraeumeFuerZeitraum(int $mitarbeiterId, \DateTimeImmutable $von, \DateTimeImmutable $bis): array
    {
        $sql = "SELECT *
                FROM krankzeitraum
                WHERE aktiv = 1
                  AND mitarbeiter_id = :mid
                  AND von_datum <= :bis
                  AND (bis_datum IS NULL OR bis_datum >= :von)
                ORDER BY von_datum DESC, id DESC";

        try {
            return $this->db->fetchAlle($sql, [
                'mid' => $mitarbeiterId,
                'von' => $von->format('Y-m-d'),
                'bis' => $bis->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden der Krankzeiträume', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von->format('Y-m-d'),
                    'bis'            => $bis->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'krankzeitraum');
            }
            return [];
        }
    }

    /**
     * Wendet Krankzeiträume (falls passend) auf die übergebenen Tageswerte an.
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @return array<int,array<string,mixed>>
     */
    public function wendeZeitraeumeAufTageswerteAn(
        array $tageswerte,
        int $mitarbeiterId,
        \DateTimeImmutable $monatStart,
        float $tagesSoll
    ): array {
        if ($tageswerte === []) {
            return $tageswerte;
        }

        try {
            $monatEnd = $monatStart->modify('last day of this month');
        } catch (\Throwable $e) {
            $monatEnd = $monatStart;
        }

        $zeitr = $this->holeZeitraeumeFuerZeitraum($mitarbeiterId, $monatStart, $monatEnd);
        if ($zeitr === []) {
            return $tageswerte;
        }

        $stundenDefault = ($tagesSoll > 0.0) ? $tagesSoll : 8.0;

        foreach ($tageswerte as $i => $row) {
            $datum = (string)($row['datum'] ?? '');
            if ($datum === '') {
                continue;
            }

            try {
                $tag = new \DateTimeImmutable($datum);
            } catch (\Throwable $e) {
                continue;
            }

            // Nur Mo-Fr
            $wochentag = (int)$tag->format('N');
            if ($wochentag >= 6) {
                continue;
            }

            // Feiertage zählen als arbeitsfrei
            if ($this->feiertagService->istFeiertag($tag, null)) {
                continue;
            }

            // Bereits explizit gesetzter Krank-Override: nicht überschreiben.
            $kennKrankLfz = (int)($row['kennzeichen_krank_lfz'] ?? 0);
            $kennKrankKk  = (int)($row['kennzeichen_krank_kk'] ?? 0);
            $krankLfzStd  = (float)str_replace(',', '.', (string)($row['krank_lfz_stunden'] ?? '0'));
            $krankKkStd   = (float)str_replace(',', '.', (string)($row['krank_kk_stunden'] ?? '0'));
            if ($kennKrankLfz === 1 || $kennKrankKk === 1 || $krankLfzStd > 0.0 || $krankKkStd > 0.0) {
                continue;
            }

            // Andere Kennzeichen schützen (Urlaub/Betriebsferien werden NICHT geschützt:
            // Krank hat Vorrang, Urlaub darf nicht abgezogen werden).
            $hatAndereKennzeichen = (
                ((int)($row['kennzeichen_feiertag'] ?? 0) === 1)
                || ((int)($row['kennzeichen_kurzarbeit'] ?? 0) === 1)
                || ((int)($row['kennzeichen_arzt'] ?? 0) === 1)
                || ((int)($row['kennzeichen_sonstiges'] ?? 0) === 1)
            );
            if ($hatAndereKennzeichen) {
                continue;
            }

            // Wenn Arbeitszeit vorhanden ist, setzen wir nicht automatisch "krank" darüber.
            $istF = (float)str_replace(',', '.', (string)($row['arbeitszeit_stunden'] ?? '0'));
            if ($istF > 0.01) {
                continue;
            }

            $z = $this->findeErstenPassendenZeitraum($zeitr, $tag);
            if ($z === null) {
                continue;
            }


            // Urlaub/Betriebsferien entfernen: Krankzeitraum übersteuert Urlaub (auch Betriebsferien-Urlaub).
            $kennUrlaub = (int)($row['kennzeichen_urlaub'] ?? 0);
            $urlaubStdF = (float)str_replace(',', '.', (string)($row['urlaub_stunden'] ?? '0'));
            $istBetriebsferien = !empty($row['ist_betriebsferien']);
            if ($kennUrlaub === 1 || $urlaubStdF > 0.0 || $istBetriebsferien) {
                $tageswerte[$i]['kennzeichen_urlaub'] = 0;
                $tageswerte[$i]['urlaub_stunden'] = '0.00';

                if ($istBetriebsferien) {
                    // Für Anzeige/PDF kein "BF"-Fallback, wenn eigentlich krank.
                    $tageswerte[$i]['ist_betriebsferien'] = false;
                }

                // Falls das Kürzel explizit als "BF" im Kommentar steckt: entfernen.
                $kommentar = trim((string)($row['kommentar'] ?? ''));
                if ($kommentar !== '') {
                    $codeTeil = $kommentar;
                    $p = strpos($kommentar, ':');
                    if ($p !== false) {
                        $codeTeil = trim(substr($kommentar, 0, $p));
                    }
                    $codeTeil = strtoupper(trim($codeTeil));
                    if ($codeTeil === 'BF') {
                        $tageswerte[$i]['kommentar'] = '';
                    }
                }
            }

            $typ = strtolower((string)($z['typ'] ?? ''));
            if ($typ !== 'lfz' && $typ !== 'kk') {
                continue;
            }

            $stunden = $stundenDefault;
            if ($stunden < 0.0) {
                $stunden = 0.0;
            }
            if ($stunden > 24.0) {
                $stunden = 24.0;
            }
            $stunden = round($stunden, 2);

            if ($typ === 'kk') {
                $tageswerte[$i]['kennzeichen_krank_kk']  = 1;
                $tageswerte[$i]['krank_kk_stunden']      = sprintf('%.2f', $stunden);
                $tageswerte[$i]['kennzeichen_krank_lfz'] = 0;
                $tageswerte[$i]['krank_lfz_stunden']     = '0.00';
            } else {
                $tageswerte[$i]['kennzeichen_krank_lfz'] = 1;
                $tageswerte[$i]['krank_lfz_stunden']     = sprintf('%.2f', $stunden);
                $tageswerte[$i]['kennzeichen_krank_kk']  = 0;
                $tageswerte[$i]['krank_kk_stunden']      = '0.00';
            }

            // Tagestyp für Anzeige (wenn bisher Arbeitstag/leer)
            $tagestyp = strtolower(trim((string)($row['tagestyp'] ?? '')));
            if ($tagestyp === '' || $tagestyp === 'arbeitstag' || $tagestyp === 'urlaub' || $tagestyp === 'betriebsferien') {
                $tageswerte[$i]['tagestyp'] = 'Krank';
            }
        }

        return $tageswerte;
    }

    /**
     * @param array<int,array<string,mixed>> $zeitr
     * @return array<string,mixed>|null
     */
    private function findeErstenPassendenZeitraum(array $zeitr, \DateTimeImmutable $tag): ?array
    {
        $ymd = $tag->format('Y-m-d');

        foreach ($zeitr as $z) {
            $von = (string)($z['von_datum'] ?? '');
            if ($von === '') {
                continue;
            }

            $bis = (string)($z['bis_datum'] ?? '');
            if ($bis === '') {
                // NULL in DB => offen
                $bis = '9999-12-31';
            }

            if ($ymd < $von || $ymd > $bis) {
                continue;
            }

            return $z;
        }

        return null;
    }
}
