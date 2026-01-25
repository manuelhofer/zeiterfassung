<?php
declare(strict_types=1);

/**
 * FeiertagService
 *
 * Berechnet und verwaltet Feiertage.
 *
 * Wichtige Punkte:
 * - Feiertage werden bei Bedarf für ein Jahr berechnet und in der Tabelle `feiertag` gespeichert.
 * - Bundeseinheitliche Feiertage werden mit `bundesland = NULL` gespeichert.
 * - Später können bundeslandspezifische Feiertage ergänzt werden.
 */
class FeiertagService
{
    private static ?FeiertagService $instanz = null;

    private FeiertagModel $feiertagModel;

    /** @var array<int,bool> */
    private array $cacheJahrInit = [];

    /** @var array<string,bool> */
    private array $cacheIstFeiertag = [];

    private function __construct()
    {
        $this->feiertagModel = new FeiertagModel();
    }

    public static function getInstanz(): FeiertagService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Prüft, ob ein Datum ein Feiertag ist.
     *
     * @param \DateTimeInterface $datum
     * @param string|null         $bundesland Optionales Bundesland-Kürzel/-Name
     */
    public function istFeiertag(\DateTimeInterface $datum, ?string $bundesland = null): bool
    {
        $jahr = (int)$datum->format('Y');

        $key = $datum->format('Y-m-d') . '|' . (string)($bundesland ?? '');
        if (isset($this->cacheIstFeiertag[$key])) {
            return $this->cacheIstFeiertag[$key];
        }

        // Sicherstellen, dass wir Feiertage für das Jahr kennen
        $this->generiereFeiertageFuerJahrWennNoetig($jahr);

        $treffer = $this->feiertagModel->holeFuerDatum($datum->format('Y-m-d'), $bundesland);
        $ist = ($treffer !== null);

        $this->cacheIstFeiertag[$key] = $ist;
        return $ist;
    }

    /**
     * Stellt sicher, dass für das übergebene Jahr bundeseinheitliche Feiertage vorhanden sind.
     *
     * Diese Basisversion erzeugt nur die in ganz Deutschland üblichen gesetzlichen Feiertage.
     *
     * @param int $jahr
     */
    public function generiereFeiertageFuerJahrWennNoetig(int $jahr): void
    {
        if (isset($this->cacheJahrInit[$jahr])) {
            return;
        }

        try {
            $vorhanden = $this->feiertagModel->holeFuerJahr($jahr, null);
        } catch (\Throwable $e) {
            $vorhanden = [];
            if (class_exists('Logger')) {
                Logger::warn('Fehler beim Prüfen vorhandener Feiertage', [
                    'jahr'      => $jahr,
                    'exception' => $e->getMessage(),
                ], null, null, 'feiertagservice');
            }
        }

        // Jahres-Init darf nicht fälschlich als "fertig" gelten, nur weil irgendein Feiertag existiert.
        // In realen Datenbeständen können (manuell/teilweise) nur einzelne Feiertage pro Jahr vorhanden sein.
        // Dann müssen die bundeseinheitlichen Feiertage trotzdem (idempotent) ergänzt werden.
        $mussSeeden = ($vorhanden === []);
        $erwartet = $this->berechneBundesweiteFeiertage($jahr);

        if (!$mussSeeden) {
            $vorhandeneDaten = [];
            foreach ($vorhanden as $ft) {
                // Nur bundeseinheitliche Einträge (bundesland IS NULL) zählen für das Basis-Seed.
                $bl = $ft['bundesland'] ?? null;
                if ($bl !== null && (string)$bl !== '') {
                    continue;
                }
                $d = (string)($ft['datum'] ?? '');
                if ($d !== '') {
                    $vorhandeneDaten[$d] = true;
                }
            }

            foreach ($erwartet as $e) {
                $d = (string)($e['datum'] ?? '');
                if ($d !== '' && !isset($vorhandeneDaten[$d])) {
                    $mussSeeden = true;
                    break;
                }
            }
        }

        if ($mussSeeden) {
            // Bundeseinheitliche Feiertage berechnen und einfügen (idempotent)
            $this->speichereBundesweiteFeiertage($erwartet);
        }

        $this->cacheJahrInit[$jahr] = true;
    }

    /**
     * Diagnose (Smoke-Test): Prüft, ob die bundeseinheitliche Grundmenge für ein Jahr vollständig vorhanden ist.
     *
     * Hinweis:
     * - Dieses Diagnose-API ist rein lesend.
     * - Wie im Livebetrieb werden fehlende Einträge für das Jahr ggf. idempotent ergänzt.
     *
     * @return array{jahr:int,ok:bool|null,hinweis:string,erwartet:int,vorhanden:int,missing:array<int,array{datum:string,name:string}>,extra:array<int,string>}
     */
    public function diagnoseBundesweiteFeiertage(int $jahr): array
    {
        $jahr = (int)$jahr;
        if ($jahr < 1970 || $jahr > 2100) {
            return [
                'jahr' => $jahr,
                'ok' => null,
                'hinweis' => 'Jahr außerhalb des erwarteten Bereichs (1970..2100).',
                'erwartet' => 0,
                'vorhanden' => 0,
                'missing' => [],
                'extra' => [],
            ];
        }

        // Wie im Livebetrieb: seeden, falls nötig (idempotent).
        try {
            $this->generiereFeiertageFuerJahrWennNoetig($jahr);
        } catch (\Throwable $e) {
            return [
                'jahr' => $jahr,
                'ok' => false,
                'hinweis' => 'Feiertage konnten nicht initialisiert werden: ' . $e->getMessage(),
                'erwartet' => 0,
                'vorhanden' => 0,
                'missing' => [],
                'extra' => [],
            ];
        }

        $erwartet = $this->berechneBundesweiteFeiertage($jahr);
        $soll = [];
        foreach ($erwartet as $ft) {
            $d = (string)($ft['datum'] ?? '');
            if ($d !== '') {
                $soll[$d] = (string)($ft['name'] ?? '');
            }
        }

        $vorhanden = [];
        try {
            $rows = $this->feiertagModel->holeFuerJahr($jahr, null);
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }

                $bl = $r['bundesland'] ?? null;
                if ($bl !== null && (string)$bl !== '') {
                    continue;
                }

                $d = (string)($r['datum'] ?? '');
                if ($d !== '') {
                    $vorhanden[$d] = true;
                }
            }
        } catch (\Throwable $e) {
            return [
                'jahr' => $jahr,
                'ok' => false,
                'hinweis' => 'Feiertage konnten nicht geladen werden: ' . $e->getMessage(),
                'erwartet' => count($soll),
                'vorhanden' => 0,
                'missing' => array_map(static fn(array $ft): array => [
                    'datum' => (string)($ft['datum'] ?? ''),
                    'name' => (string)($ft['name'] ?? ''),
                ], $erwartet),
                'extra' => [],
            ];
        }

        $missing = [];
        foreach ($erwartet as $ft) {
            $d = (string)($ft['datum'] ?? '');
            if ($d !== '' && !isset($vorhanden[$d])) {
                $missing[] = [
                    'datum' => $d,
                    'name'  => (string)($ft['name'] ?? ''),
                ];
            }
        }

        $extra = [];
        foreach ($vorhanden as $d => $_) {
            if (!isset($soll[$d])) {
                $extra[] = (string)$d;
            }
        }

        $ok = ($missing === []);
        return [
            'jahr' => $jahr,
            'ok' => $ok,
            'hinweis' => $ok ? 'OK – bundeseinheitliche Feiertage vollständig.' : 'Es fehlen bundeseinheitliche Feiertage (s.u.).',
            'erwartet' => count($soll),
            'vorhanden' => count($vorhanden),
            'missing' => $missing,
            'extra' => $extra,
        ];
    }

    /**
     * Berechnet bundeseinheitliche Feiertage für ein Jahr und speichert sie.
     *
     * Bundeseinheitlich (Grundmenge):
     * - 01.01. Neujahr
     * - Karfreitag
     * - Ostermontag
     * - 01.05. Tag der Arbeit
     * - Christi Himmelfahrt
     * - Pfingstmontag
     * - 03.10. Tag der Deutschen Einheit
     * - 25.12. 1. Weihnachtstag
     * - 26.12. 2. Weihnachtstag
     */
    private function berechneUndSpeichereBundesweiteFeiertage(int $jahr): void
    {
        $this->speichereBundesweiteFeiertage($this->berechneBundesweiteFeiertage($jahr));
    }

    /**
     * Berechnet bundeseinheitliche Feiertage für ein Jahr.
     *
     * @return array<int,array{datum:string,name:string}>
     */
    private function berechneBundesweiteFeiertage(int $jahr): array
    {
        $feiertage = [];

        // Feste Feiertage
        $feiertage[] = [
            'datum' => sprintf('%04d-01-01', $jahr),
            'name'  => 'Neujahr',
        ];

        $feiertage[] = [
            'datum' => sprintf('%04d-05-01', $jahr),
            'name'  => 'Tag der Arbeit',
        ];

        $feiertage[] = [
            'datum' => sprintf('%04d-10-03', $jahr),
            'name'  => 'Tag der Deutschen Einheit',
        ];

        $feiertage[] = [
            'datum' => sprintf('%04d-12-25', $jahr),
            'name'  => '1. Weihnachtstag',
        ];

        $feiertage[] = [
            'datum' => sprintf('%04d-12-26', $jahr),
            'name'  => '2. Weihnachtstag',
        ];

        // Bewegliche Feiertage auf Basis Ostersonntag
        $ostersonntag = $this->berechneOstersonntag($jahr);

        $karfreitag     = $ostersonntag->modify('-2 day');
        $ostermontag    = $ostersonntag->modify('+1 day');
        $himmelfahrt    = $ostersonntag->modify('+39 day');
        $pfingstmontag  = $ostersonntag->modify('+50 day');

        $feiertage[] = [
            'datum' => $karfreitag->format('Y-m-d'),
            'name'  => 'Karfreitag',
        ];
        $feiertage[] = [
            'datum' => $ostermontag->format('Y-m-d'),
            'name'  => 'Ostermontag',
        ];
        $feiertage[] = [
            'datum' => $himmelfahrt->format('Y-m-d'),
            'name'  => 'Christi Himmelfahrt',
        ];
        $feiertage[] = [
            'datum' => $pfingstmontag->format('Y-m-d'),
            'name'  => 'Pfingstmontag',
        ];

        return $feiertage;
    }

    /**
     * Speichert eine Liste bundeseinheitlicher Feiertage (idempotent).
     *
     * @param array<int,array{datum:string,name:string}> $feiertage
     */
    private function speichereBundesweiteFeiertage(array $feiertage): void
    {
        foreach ($feiertage as $ft) {
            try {
                $this->feiertagModel->fuegeEinWennNeu(
                    (string)$ft['datum'],
                    (string)$ft['name'],
                    null,
                    1,
                    1
                );
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Feiertag konnte nicht gespeichert werden', [
                        'datum'     => (string)($ft['datum'] ?? ''),
                        'name'      => (string)($ft['name'] ?? ''),
                        'exception' => $e->getMessage(),
                    ], null, null, 'feiertagservice');
                }
            }
        }
    }

    /**
     * Berechnet das Datum des Ostersonntags nach dem gregorianischen Kalender.
     *
     * Quelle: Gaußsche Osterformel (vereinfachte Standardimplementierung).
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
        $monat = intdiv($h + $l - 7 * $m + 114, 31);          // 3= März, 4= April
        $tag   = (($h + $l - 7 * $m + 114) % 31) + 1;

        $datumString = sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);

        try {
            return new \DateTimeImmutable($datumString);
        } catch (\Throwable $e) {
            // Fallback: Ostersonntag dieses Jahres mit PHP-Funktion berechnen
            $fallback = new \DateTimeImmutable('now');
            return $fallback->setDate($jahr, 3, 31);
        }
    }
}
