<?php
declare(strict_types=1);

/**
 * KurzarbeitService
 *
 * Liest Kurzarbeit-Planungen aus `kurzarbeit_plan` und kann diese
 * für Auswertungen (Monatsübersicht/PDF) auf Tageswerte anwenden.
 *
 * Ziel (T-070, Teil 1):
 * - Kurzarbeit wird im PDF in einer eigenen Spalte ausgewiesen.
 * - Für Saldo/Soll-Berechnung reduziert Kurzarbeit das Soll.
 * - Planungen sind firmenweit oder mitarbeiterbezogen möglich.
 * - Mitarbeiter-Plan hat Vorrang vor Firmen-Plan.
 *
 * Wochentage-Maske:
 * - Bit 0 = Montag, Bit 1 = Dienstag, ..., Bit 6 = Sonntag
 */
class KurzarbeitService
{
    private static ?KurzarbeitService $instanz = null;

    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstanz();
    }

    public static function getInstanz(): KurzarbeitService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Lädt alle aktiven Kurzarbeit-Pläne, die den Zeitraum schneiden.
     *
     * Priorität: mitarbeiterbezogen vor firmenweit; innerhalb der Kategorie neuere IDs zuerst.
     *
     * @return array<int,array<string,mixed>>
     */
    public function holePlaeneFuerZeitraum(int $mitarbeiterId, \DateTimeImmutable $von, \DateTimeImmutable $bis): array
    {
        $sql = "SELECT *
                FROM kurzarbeit_plan
                WHERE aktiv = 1
                  AND von_datum <= :bis
                  AND bis_datum >= :von
                  AND (
                        scope = 'firma'
                        OR (scope = 'mitarbeiter' AND mitarbeiter_id = :mid)
                  )
                ORDER BY
                  CASE WHEN scope = 'mitarbeiter' THEN 2 ELSE 1 END DESC,
                  id DESC";

        try {
            return $this->db->fetchAlle($sql, [
                'von' => $von->format('Y-m-d'),
                'bis' => $bis->format('Y-m-d'),
                'mid' => $mitarbeiterId,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Laden der Kurzarbeit-Pläne', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'von'            => $von->format('Y-m-d'),
                    'bis'            => $bis->format('Y-m-d'),
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'kurzarbeit');
            }
            return [];
        }
    }

    /**
     * Wendet Kurzarbeit-Pläne (falls passend) auf die übergebenen Tageswerte an.
     *
     * Regeln:
     * - Nur wenn der Tag noch keine explizite Kurzarbeit enthält.
     * - Nur auf reguläre Arbeitstage (tagestyp = 'Arbeitstag' oder leer).
     * - Mitarbeiter-Pläne haben Vorrang vor Firmen-Plänen.
     * - Modus:
     *   - stunden: `wert` ist Stunden
     *   - prozent: `wert` ist Prozent vom Tages-Soll
     *
     * Hinweis:
     * - Diese Methode schreibt **nicht** in die DB.
     * - Tages-Overrides (in `tageswerte_mitarbeiter`) werden später als eigene Aufgabe umgesetzt.
     *
     * @param array<int,array<string,mixed>> $tageswerte
     * @return array<int,array<string,mixed>>
     */
    public function wendePlanAufTageswerteAn(
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

        $plaene = $this->holePlaeneFuerZeitraum($mitarbeiterId, $monatStart, $monatEnd);
        if ($plaene === []) {
            return $tageswerte;
        }

        foreach ($tageswerte as $i => $row) {
            $datum = (string)($row['datum'] ?? '');
            if ($datum === '') {
                continue;
            }

            // Wenn bereits explizite Kurzarbeit gesetzt ist: nicht überschreiben.
            $kennKurzarbeit = (int)($row['kennzeichen_kurzarbeit'] ?? 0);
            $kurzarbeitStd  = (float)str_replace(',', '.', (string)($row['kurzarbeit_stunden'] ?? '0'));
            if ($kennKurzarbeit === 1 || $kurzarbeitStd > 0.0) {
                continue;
            }

            // Nur auf reguläre Arbeitstage anwenden.
            $tagestyp = strtolower(trim((string)($row['tagestyp'] ?? '')));
            if ($tagestyp !== '' && $tagestyp !== 'arbeitstag') {
                continue;
            }

            // Andere Kennzeichen schützen (Urlaub/Krank/Feiertag/Arzt/Sonstiges).
            $hatAndereKennzeichen = (
                ((int)($row['kennzeichen_feiertag'] ?? 0) === 1)
                || ((int)($row['kennzeichen_urlaub'] ?? 0) === 1)
                || ((int)($row['kennzeichen_krank_lfz'] ?? 0) === 1)
                || ((int)($row['kennzeichen_krank_kk'] ?? 0) === 1)
                || ((int)($row['kennzeichen_arzt'] ?? 0) === 1)
                || ((int)($row['kennzeichen_sonstiges'] ?? 0) === 1)
            );
            if ($hatAndereKennzeichen) {
                continue;
            }

            try {
                $tag = new \DateTimeImmutable($datum);
            } catch (\Throwable $e) {
                continue;
            }

            $plan = $this->findeErstenPassendenPlan($plaene, $tag);
            if ($plan === null) {
                continue;
            }

            $stunden = $this->berechneKurzarbeitStundenAusPlan($plan, $tagesSoll);
            if ($stunden <= 0.0) {
                continue;
            }

            $tageswerte[$i]['kennzeichen_kurzarbeit'] = 1;
            $tageswerte[$i]['kurzarbeit_stunden']     = sprintf('%.2f', $stunden);

            // Tagestyp für Anzeige (wenn bisher Arbeitstag)
            if ($tagestyp === '' || $tagestyp === 'arbeitstag') {
                $tageswerte[$i]['tagestyp'] = 'Kurzarbeit';
            }
        }

        return $tageswerte;
    }

    /**
     * @param array<int,array<string,mixed>> $plaene
     * @return array<string,mixed>|null
     */
    private function findeErstenPassendenPlan(array $plaene, \DateTimeImmutable $tag): ?array
    {
        $ymd = $tag->format('Y-m-d');
        $bit = $this->wochentagBit($tag);

        foreach ($plaene as $p) {
            $von = (string)($p['von_datum'] ?? '');
            $bis = (string)($p['bis_datum'] ?? '');
            if ($von === '' || $bis === '') {
                continue;
            }

            // Datumsbereich
            if ($ymd < $von || $ymd > $bis) {
                continue;
            }

            $mask = (int)($p['wochentage_mask'] ?? 31);
            if (($mask & $bit) === 0) {
                continue;
            }

            return $p;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function berechneKurzarbeitStundenAusPlan(array $plan, float $tagesSoll): float
    {
        $modus = strtolower((string)($plan['modus'] ?? 'stunden'));
        $wert  = (float)str_replace(',', '.', (string)($plan['wert'] ?? '0'));

        $stunden = 0.0;
        if ($modus === 'prozent') {
            if ($tagesSoll <= 0) {
                return 0.0;
            }
            $stunden = $tagesSoll * ($wert / 100.0);
        } else {
            // Default: stunden
            $stunden = $wert;
        }

        if ($stunden < 0.0) {
            $stunden = 0.0;
        }

        // Kurzarbeit kann das Tages-Soll nicht übersteigen (wenn bekannt).
        if ($tagesSoll > 0 && $stunden > $tagesSoll) {
            $stunden = $tagesSoll;
        }

        // Wenn Tages-Soll unbekannt ist, begrenzen wir defensiv auf 24h.
        if ($tagesSoll <= 0 && $stunden > 24.0) {
            $stunden = 24.0;
        }

        return round($stunden, 2);
    }

    /**
     * Liefert das Bit für den Wochentag (Mo=Bit0 ... So=Bit6).
     */
    private function wochentagBit(\DateTimeImmutable $tag): int
    {
        $n = (int)$tag->format('N'); // 1..7
        if ($n < 1 || $n > 7) {
            return 0;
        }
        return 1 << ($n - 1);
    }
}
