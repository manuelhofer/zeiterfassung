<?php
declare(strict_types=1);

/**
 * FeiertagGenerator
 *
 * Verantwortlich für die automatische Generierung gesetzlicher Feiertage
 * in der Tabelle `feiertag`.
 *
 * Aktueller Stand:
 * - Erzeugt die bundeseinheitlichen gesetzlichen Feiertage für ein Jahr.
 * - Optionale Übergabe eines Bundesland-Codes ist bereits vorgesehen, wird
 *   aber aktuell für bundeseinheitliche Feiertage noch nicht ausgewertet.
 * - Vorhandene Einträge werden NICHT überschrieben (ON DUPLICATE KEY → no-op).
 */
class FeiertagGenerator
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Generiert bundeseinheitliche gesetzliche Feiertage für das angegebene Jahr.
     *
     * @param int         $jahr       Zieljahr
     * @param string|null $bundesland Optionaler Bundesland-Code (z. B. 'HE', 'BY').
     *
     * @return int Anzahl der neu eingefügten Datensätze (Schätzung, da ON DUPLICATE KEY UPDATE verwendet wird).
     */
    public function generiereFuerJahr(int $jahr, ?string $bundesland = null): int
    {
        if ($jahr < 1970 || $jahr > 2100) {
            return 0;
        }

        $bundesland = $bundesland !== null ? trim($bundesland) : null;
        if ($bundesland === '') {
            $bundesland = null;
        }

        $ostersonntag = $this->berechneOstersonntag($jahr);

        $feiertage = [];

        // Neujahr
        $feiertage[] = [
            'datum'         => sprintf('%04d-01-01', $jahr),
            'name'          => 'Neujahr',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // Karfreitag (2 Tage vor Ostersonntag)
        $karfreitag = $ostersonntag->modify('-2 days');
        $feiertage[] = [
            'datum'         => $karfreitag->format('Y-m-d'),
            'name'          => 'Karfreitag',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // Ostermontag (1 Tag nach Ostersonntag)
        $ostermontag = $ostersonntag->modify('+1 day');
        $feiertage[] = [
            'datum'         => $ostermontag->format('Y-m-d'),
            'name'          => 'Ostermontag',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // Tag der Arbeit (1. Mai)
        $feiertage[] = [
            'datum'         => sprintf('%04d-05-01', $jahr),
            'name'          => 'Tag der Arbeit',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // Christi Himmelfahrt (39 Tage nach Ostersonntag)
        $christiHimmelfahrt = $ostersonntag->modify('+39 days');
        $feiertage[] = [
            'datum'         => $christiHimmelfahrt->format('Y-m-d'),
            'name'          => 'Christi Himmelfahrt',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // Pfingstmontag (50 Tage nach Ostersonntag)
        $pfingstmontag = $ostersonntag->modify('+50 days');
        $feiertage[] = [
            'datum'         => $pfingstmontag->format('Y-m-d'),
            'name'          => 'Pfingstmontag',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // Tag der Deutschen Einheit (3. Oktober)
        $feiertage[] = [
            'datum'         => sprintf('%04d-10-03', $jahr),
            'name'          => 'Tag der Deutschen Einheit',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // 1. Weihnachtstag
        $feiertage[] = [
            'datum'         => sprintf('%04d-12-25', $jahr),
            'name'          => '1. Weihnachtstag',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        // 2. Weihnachtstag
        $feiertage[] = [
            'datum'         => sprintf('%04d-12-26', $jahr),
            'name'          => '2. Weihnachtstag',
            'bundesland'    => $bundesland,
            'ist_gesetzlich'=> 1,
            'ist_betriebsfrei' => 1,
        ];

        $sql = 'INSERT INTO feiertag (datum, name, bundesland, ist_gesetzlich, ist_betriebsfrei)
                VALUES (:datum, :name, :bundesland, :ist_gesetzlich, :ist_betriebsfrei)
                ON DUPLICATE KEY UPDATE datum = datum';

        $eingefuegt = 0;

        foreach ($feiertage as $ft) {
            try {
                $this->datenbank->ausfuehren($sql, [
                    'datum'          => $ft['datum'],
                    'name'           => $ft['name'],
                    'bundesland'     => $ft['bundesland'],
                    'ist_gesetzlich' => $ft['ist_gesetzlich'],
                    'ist_betriebsfrei' => $ft['ist_betriebsfrei'],
                ]);

                $eingefuegt++;
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error(
                        'Fehler beim Einfügen eines Feiertags',
                        ['feiertag' => $ft, 'exception' => $e->getMessage()],
                        null,
                        null,
                        'feiertag_generator'
                    );
                }
            }
        }

        return $eingefuegt;
    }

    /**
     * Berechnet den Ostersonntag für ein gegebenes Jahr (gregorianischer Kalender).
     *
     * Algorithmus nach Gauß (leicht angepasst auf PHP).
     */
    private function berechneOstersonntag(int $jahr): \DateTimeImmutable
    {
        $a = $jahr % 19;
        $b = intdiv($jahr, 100);
        $c = $jahr % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31); // 3=March, 4=April
        $tag   = (($h + $l - 7 * $m + 114) % 31) + 1;

        $datumString = sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);

        try {
            return new \DateTimeImmutable($datumString);
        } catch (\Exception) {
            // Fallback auf 31.03., falls wider Erwarten etwas schiefgeht.
            return new \DateTimeImmutable(sprintf('%04d-03-31', $jahr));
        }
    }
}
