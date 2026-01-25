<?php
declare(strict_types=1);

/**
 * PausenService
 *
 * Berechnet Pausenminuten für einen Arbeitsblock.
 *
 * Regeln (Master-Prompt v6 / T-072):
 * - Zwangspausen (betrieblich): Überlappung des Arbeitsblocks mit aktiven Pausenfenstern (Uhrzeitfenster).
 * - Gesetzliche Mindestpause: schwellenbasiert (Konfig-Keys, Default 6h/30m und 9h/45m).
 * - Gesamtabzug pro Block: max(Zwangspause, gesetzliche Mindestpause).
 */
class PausenService
{
    private static ?PausenService $instanz = null;

    private Database $datenbank;
    private KonfigurationService $konfiguration;

    /** @var array<int,array{von_uhrzeit:string,bis_uhrzeit:string}> */
    private array $aktiveFenster = [];

    private bool $fensterGeladen = false;

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
        $this->konfiguration = KonfigurationService::getInstanz();
    }

    public static function getInstanz(): PausenService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }
        return self::$instanz;
    }

    /**
     * Berechnet die Pausenminuten für einen Arbeitsblock (Start/Ende sind bereits gerundet/korrigiert).
     *
     * Hinweis (T-081): In Grenzfällen (z. B. ~6h) ist eine menschliche Entscheidung nötig,
     * ob tatsächlich Pause gemacht wurde. Solange nicht entschieden wurde, wird **keine** Pause abgezogen.
     */
    public function berechnePausenMinutenFuerBlock(DateTimeImmutable $start, DateTimeImmutable $ende): int
    {
        $res = $this->berechnePausenMinutenUndEntscheidungFuerBlock($start, $ende);
        return (int)($res['pause_minuten'] ?? 0);
    }

    /**
     * Liefert die automatische Pausenberechnung inkl. Entscheidungshinweis.
     *
     * Rueckgabe:
     * - pause_minuten: Minuten, die automatisch abgezogen werden (Default 0, wenn Entscheidung noetig)
     * - auto_pause_minuten: Minuten, die abgezogen wuerden, wenn entschieden "Pause abziehen" waere
     * - entscheidung_noetig: true/false
     *
     * @return array{pause_minuten:int,auto_pause_minuten:int,entscheidung_noetig:bool}
     */
    public function berechnePausenMinutenUndEntscheidungFuerBlock(DateTimeImmutable $start, DateTimeImmutable $ende): array
    {
        if ($ende <= $start) {
            return [
                'pause_minuten' => 0,
                'auto_pause_minuten' => 0,
                'entscheidung_noetig' => false,
            ];
        }

        $zwang = $this->berechneZwangspauseMinuten($start, $ende);
        $gesetz = $this->berechneGesetzlichePauseMinuten($start, $ende);

        $auto = max($zwang, $gesetz);
        if ($auto <= 0) {
            return [
                'pause_minuten' => 0,
                'auto_pause_minuten' => 0,
                'entscheidung_noetig' => false,
            ];
        }

        $entscheidungNoetig = $this->istPausenEntscheidungNoetigNaheSchwelle($start, $ende, $auto);

        return [
            'pause_minuten' => $entscheidungNoetig ? 0 : $auto,
            'auto_pause_minuten' => $auto,
            'entscheidung_noetig' => $entscheidungNoetig,
        ];
    }

    /**
     * Grenzfall-Erkennung (T-081): Wenn die Arbeitsdauer "knapp um" der ersten gesetzlichen Schwelle liegt
     * (Default ~6h), ist eine Entscheidung nötig, ob Pause wirklich gemacht wurde.
     *
     * Konfig-Keys:
     * - pause_gesetz_schwelle1_stunden (Default 6)
     * - pause_entscheidung_toleranz_minuten (Default 30)  -> z.B. 5:30h bis 6:30h
     */
    private function istPausenEntscheidungNoetigNaheSchwelle(DateTimeImmutable $start, DateTimeImmutable $ende, int $autoPauseMinuten): bool
    {
        if ($autoPauseMinuten <= 0) {
            return false;
        }

        $schwelle1 = (int)($this->konfiguration->getInt('pause_gesetz_schwelle1_stunden', 6) ?? 6);
        if ($schwelle1 <= 0) {
            $schwelle1 = 6;
        }
        $schwelleMin = $schwelle1 * 60;

        $toleranzMin = (int)($this->konfiguration->getInt('pause_entscheidung_toleranz_minuten', 30) ?? 30);
        if ($toleranzMin < 0) {
            $toleranzMin = 0;
        }

        $dauerSek = $ende->getTimestamp() - $start->getTimestamp();
        if ($dauerSek <= 0) {
            return false;
        }
        $dauerMin = (int)floor($dauerSek / 60);

        // Praxisregel (Manuel): Entscheidung nur, wenn der Block **ab 6h** läuft und knapp darüber liegt.
        // Unter 6h sollen betriebliche Pausenfenster (z. B. Frühstück 09:00–09:15) weiterhin automatisch
        // abgezogen werden, falls sie überlappt werden.
        // Default: 6:00h bis 6:30h (Toleranz nach oben).

        $minGrenze = $schwelleMin;
        $maxGrenze = $schwelleMin + $toleranzMin;

        return ($dauerMin >= $minGrenze && $dauerMin <= $maxGrenze);
    }

    /**
     * Gesetzliche Mindestpause über Konfiguration.
     * Keys:
     * - pause_gesetz_schwelle1_stunden (Default 6)
     * - pause_gesetz_minuten1 (Default 30)
     * - pause_gesetz_schwelle2_stunden (Default 9)
     * - pause_gesetz_minuten2 (Default 45)
     */
    private function berechneGesetzlichePauseMinuten(DateTimeImmutable $start, DateTimeImmutable $ende): int
    {
        $schwelle1 = (int)($this->konfiguration->getInt('pause_gesetz_schwelle1_stunden', 6) ?? 6);
        $minuten1  = (int)($this->konfiguration->getInt('pause_gesetz_minuten1', 30) ?? 30);
        $schwelle2 = (int)($this->konfiguration->getInt('pause_gesetz_schwelle2_stunden', 9) ?? 9);
        $minuten2  = (int)($this->konfiguration->getInt('pause_gesetz_minuten2', 45) ?? 45);

        $dauerSek = $ende->getTimestamp() - $start->getTimestamp();
        if ($dauerSek <= 0) {
            return 0;
        }

        $dauerStunden = $dauerSek / 3600.0;

        // Logik: > Schwelle2 => Minuten2, sonst > Schwelle1 => Minuten1, sonst 0
        if ($dauerStunden > (float)$schwelle2) {
            return max(0, $minuten2);
        }
        if ($dauerStunden > (float)$schwelle1) {
            return max(0, $minuten1);
        }

        return 0;
    }

    /**
     * Zwangspause: Summe der Überlappung des Arbeitsblocks mit allen aktiven Pausenfenstern.
     */
    private function berechneZwangspauseMinuten(DateTimeImmutable $start, DateTimeImmutable $ende): int
    {
        $fenster = $this->holeAktiveFenster();
        if ($fenster === []) {
            return 0;
        }

        $blockStartTs = $start->getTimestamp();
        $blockEndTs   = $ende->getTimestamp();

        if ($blockEndTs <= $blockStartTs) {
            return 0;
        }

        // Falls der Block über Mitternacht geht, berechnen wir tageweise.
        $d = new DateTimeImmutable($start->format('Y-m-d'));
        $dEnd = new DateTimeImmutable($ende->format('Y-m-d'));

        $summe = 0;

        while ($d <= $dEnd) {
            $datum = $d->format('Y-m-d');

            foreach ($fenster as $f) {
                $vonStr = $datum . ' ' . $f['von_uhrzeit'];
                $bisStr = $datum . ' ' . $f['bis_uhrzeit'];

                try {
                    $von = new DateTimeImmutable($vonStr);
                    $bis = new DateTimeImmutable($bisStr);
                } catch (Throwable $e) {
                    continue;
                }

                // Fenster über Mitternacht (selten, aber robust): bis <= von -> bis +1 Tag
                if ($bis <= $von) {
                    $bis = $bis->modify('+1 day');
                }

                // Sonderfall (Praxis): Wenn der Mitarbeiter innerhalb eines Pausenfensters abstempelt,
                // gehen wir davon aus, dass diese Pause NICHT gemacht wurde (kein automatischer Abzug).
                // Beispiel: Pausenfenster 12:30–13:00, Mitarbeiter stempelt um 12:41 ab.
                $vonTs = $von->getTimestamp();
                $bisTs = $bis->getTimestamp();
                if ($blockEndTs >= $vonTs && $blockEndTs <= $bisTs) {
                    continue;
                }

                $summe += $this->berechneUeberlappungMinuten(
                    $blockStartTs,
                    $blockEndTs,
                    $vonTs,
                    $bisTs
                );
            }

            $d = $d->modify('+1 day');
        }

        return $summe > 0 ? $summe : 0;
    }

    /**
     * @return array<int,array{von_uhrzeit:string,bis_uhrzeit:string}>
     */
    private function holeAktiveFenster(): array
    {
        if ($this->fensterGeladen) {
            return $this->aktiveFenster;
        }

        $this->fensterGeladen = true;
        $this->aktiveFenster = [];

        try {
            $sql = 'SELECT von_uhrzeit, bis_uhrzeit
                    FROM pausenfenster
                    WHERE aktiv = 1
                    ORDER BY sort_order ASC, von_uhrzeit ASC';

            $rows = $this->datenbank->fetchAlle($sql);

            foreach ($rows as $r) {
                $von = (string)($r['von_uhrzeit'] ?? '');
                $bis = (string)($r['bis_uhrzeit'] ?? '');
                if ($von === '' || $bis === '') {
                    continue;
                }
                $this->aktiveFenster[] = [
                    'von_uhrzeit' => $von,
                    'bis_uhrzeit' => $bis,
                ];
            }
        } catch (Throwable $e) {
            // Bei Fehlern: keine Pausenfenster anwenden (defensiv).
            $this->aktiveFenster = [];
        }

        return $this->aktiveFenster;
    }

    private function berechneUeberlappungMinuten(int $aStart, int $aEnd, int $bStart, int $bEnd): int
    {
        $start = max($aStart, $bStart);
        $end   = min($aEnd, $bEnd);

        if ($end <= $start) {
            return 0;
        }

        $sek = $end - $start;
        if ($sek <= 0) {
            return 0;
        }

        // Minuten (ganzzahlig, abrunden)
        return (int)floor($sek / 60);
    }
}
