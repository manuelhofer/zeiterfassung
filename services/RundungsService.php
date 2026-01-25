<?php
declare(strict_types=1);

/**
 * RundungsService
 *
 * Wendet konfigurierbare Rundungsregeln aus der Tabelle `zeit_rundungsregel`
 * auf Zeitstempel (Kommen/Gehen) an.
 *
 * Wichtig:
 * - Es wird nichts fest "hart" kodiert; alle Regeln kommen aus der Datenbank.
 * - Pro Aufruf wird die erste passende Regel (niedrigste Priorität zuerst) verwendet.
 */
class RundungsService
{
    private static ?RundungsService $instanz = null;

    private ZeitRundungsregelModel $regelModel;

    /** @var array<int,array<string,mixed>> */
    private array $regelnCache = [];

    private function __construct()
    {
        $this->regelModel = new ZeitRundungsregelModel();
        $this->ladeRegeln();
    }

    public static function getInstanz(): RundungsService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Lädt alle aktiven Regeln in den Cache.
     */
    private function ladeRegeln(): void
    {
        try {
            $this->regelnCache = $this->regelModel->holeAktiveRegeln();

            // Wenn keine Regeln vorhanden sind, legen wir einmalig sinnvolle Defaults an.
            // (Idempotent: es wird nur geseedet, wenn die Tabelle leer ist.)
            if (empty($this->regelnCache)) {
                if ($this->seedDefaultRegelnWennLeer()) {
                    $this->regelnCache = $this->regelModel->holeAktiveRegeln();
                }
            }
        } catch (\Throwable $e) {
            $this->regelnCache = [];
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Zeit-Rundungsregeln', [
                    'exception' => $e->getMessage(),
                ], null, null, 'rundung');
            }
        }
    }


    /**
     * Legt Default-Rundungsregeln an, wenn die Tabelle `zeit_rundungsregel` leer ist.
     *
     * Default (gemäß Projektvorgabe):
     * - 00:00–07:00 → 30 Minuten (nächstgelegen)
     * - 07:00–23:59 → 15 Minuten (nächstgelegen)
     *
     * Hinweis:
     * - Für den letzten Bereich wird `bis_uhrzeit = 23:59:59` genutzt.
     *   Das ist im Formular weiterhin als 23:59 editierbar, und wird bei der
     *   Regelprüfung (exklusives Ende) korrekt als Tagesende interpretiert.
     */
    private function seedDefaultRegelnWennLeer(): bool
    {
        try {
            $db = Database::getInstanz();

            $row = $db->fetchEine('SELECT COUNT(*) AS cnt FROM zeit_rundungsregel');
            $cnt = (int)($row['cnt'] ?? 0);

            if ($cnt > 0) {
                return false;
            }

            $sql = 'INSERT INTO zeit_rundungsregel
                        (von_uhrzeit, bis_uhrzeit, einheit_minuten, richtung, gilt_fuer, prioritaet, aktiv, beschreibung)
                    VALUES
                        (:von1, :bis1, :einheit1, :richtung1, :gilt1, :prio1, 1, :besch1),
                        (:von2, :bis2, :einheit2, :richtung2, :gilt2, :prio2, 1, :besch2)';

            $db->ausfuehren($sql, [
                'von1'      => '00:00:00',
                'bis1'      => '07:00:00',
                'einheit1'  => 30,
                'richtung1' => 'naechstgelegen',
                'gilt1'     => 'beide',
                'prio1'     => 1,
                'besch1'    => 'Standard: 00:00–07:00 auf 30 Minuten runden (nächstgelegen)',

                'von2'      => '07:00:00',
                'bis2'      => '23:59:59',
                'einheit2'  => 15,
                'richtung2' => 'naechstgelegen',
                'gilt2'     => 'beide',
                'prio2'     => 2,
                'besch2'    => 'Standard: 07:00–24:00 auf 15 Minuten runden (nächstgelegen)',
            ]);

            if (class_exists('Logger')) {
                Logger::info('Default-Zeit-Rundungsregeln wurden automatisch angelegt (Tabelle war leer).', [], null, null, 'rundung');
            }

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warn('Default-Zeit-Rundungsregeln konnten nicht automatisch angelegt werden.', [
                    'exception' => $e->getMessage(),
                ], null, null, 'rundung');
            }

            return false;
        }
    }

    /**
     * Rundet einen Zeitstempel anhand der konfigurierten Rundungsregeln.
     *
     * @param \DateTimeInterface $zeitstempel
     * @param string              $typ          'kommen' oder 'gehen'
     */
    public function rundeZeitstempel(\DateTimeInterface $zeitstempel, string $typ): \DateTimeImmutable
    {
        $typ = strtolower($typ);
        if ($typ !== 'kommen' && $typ !== 'gehen') {
            // Fallback: keine Rundung
            return \DateTimeImmutable::createFromInterface($zeitstempel);
        }

        $minutenSeitMitternacht = $this->berechneMinutenSeitMitternacht($zeitstempel);

        $regel = $this->findePassendeRegel($minutenSeitMitternacht, $typ);
        if ($regel === null) {
            // Keine passende Regel → keine Rundung
            return \DateTimeImmutable::createFromInterface($zeitstempel);
        }

        $gerundeteMinuten = $this->wendeRegelAn($minutenSeitMitternacht, $regel);

        // Auf gleichen Kalendertag anwenden
        $datum = $zeitstempel->format('Y-m-d');

        $stunden = intdiv($gerundeteMinuten, 60);
        $min     = $gerundeteMinuten % 60;

        $zeitString = sprintf('%s %02d:%02d:00', $datum, $stunden, $min);

        try {
            return new \DateTimeImmutable($zeitString, $zeitstempel->getTimezone());
        } catch (\Throwable $e) {
            // Fallback: ursprünglichen Zeitstempel zurückgeben
            if (class_exists('Logger')) {
                Logger::warn('Fehler bei der Anwendung der Rundungsregel, verwende Originalzeit', [
                    'zeit_original' => $zeitstempel->format(DATE_ATOM),
                    'zeit_string'   => $zeitString,
                    'exception'     => $e->getMessage(),
                ], null, null, 'rundung');
            }

            return \DateTimeImmutable::createFromInterface($zeitstempel);
        }
    }

    /**
     * Findet die erste passende Rundungsregel für eine Tageszeit und Kommen/Gehen-Typ.
     *
     * @param int    $minutenSeitMitternacht
     * @param string $typ                    'kommen' oder 'gehen'
     *
     * @return array<string,mixed>|null
     */
    private function findePassendeRegel(int $minutenSeitMitternacht, string $typ): ?array
    {
        foreach ($this->regelnCache as $regel) {
            $giltFuer = (string)($regel['gilt_fuer'] ?? 'beide');
            if ($giltFuer !== 'beide' && $giltFuer !== $typ) {
                continue;
            }

            $vonZeit = (string)($regel['von_uhrzeit'] ?? '00:00:00');
            $bisZeit = (string)($regel['bis_uhrzeit'] ?? '23:59:59');

            $vonMin = $this->zeitstringZuMinuten($vonZeit);
            $bisMin = $this->zeitstringZuMinuten($bisZeit);

            // Zeitbereich inkl. Start, exklusiv Ende (Standard)
            if ($minutenSeitMitternacht < $vonMin || $minutenSeitMitternacht >= $bisMin) {
                continue;
            }

            return $regel;
        }

        return null;
    }

    /**
     * Wendet eine konkrete Regel auf die Minuten seit Mitternacht an.
     */
    private function wendeRegelAn(int $minutenSeitMitternacht, array $regel): int
    {
        $einheit = (int)($regel['einheit_minuten'] ?? 0);
        if ($einheit <= 0) {
            // Ungültige Konfiguration → keine Rundung
            return $minutenSeitMitternacht;
        }

        $richtung = (string)($regel['richtung'] ?? 'naechstgelegen');

        $wert = $minutenSeitMitternacht / $einheit;

        if ($richtung === 'auf') {
            $faktor = (int)ceil($wert);
        } elseif ($richtung === 'ab') {
            $faktor = (int)floor($wert);
        } else {
            // naechstgelegen
            $faktor = (int)round($wert);
        }

        $gerundet = $faktor * $einheit;

        // Sicherheitshalber in 0..(24*60-1) clampen
        $maxMinuten = 24 * 60 - 1;
        if ($gerundet < 0) {
            $gerundet = 0;
        } elseif ($gerundet > $maxMinuten) {
            $gerundet = $maxMinuten;
        }

        return $gerundet;
    }

    /**
     * Berechnet Minuten seit Mitternacht für einen Zeitstempel.
     */
    private function berechneMinutenSeitMitternacht(\DateTimeInterface $zeit): int
    {
        $stunden = (int)$zeit->format('H');
        $min     = (int)$zeit->format('i');

        return $stunden * 60 + $min;
    }

    /**
     * Konvertiert einen TIME-String (HH:MM:SS) in Minuten seit Mitternacht.
     */
    private function zeitstringZuMinuten(string $time): int
    {
        $teile = explode(':', $time);
        $h = isset($teile[0]) ? (int)$teile[0] : 0;
        $m = isset($teile[1]) ? (int)$teile[1] : 0;
        $s = isset($teile[2]) ? (int)$teile[2] : 0;

        $min = $h * 60 + $m;

        // Wenn Sekunden gesetzt sind (z.B. 23:59:59), interpretieren wir das als
        // "bis Ende dieser Minute" und runden für die Bereichsprüfung auf die nächste Minute.
        if ($s > 0) {
            $min += 1;
        }

        // Clamp in sinnvollen Bereich 0..1440
        if ($min < 0) {
            $min = 0;
        } elseif ($min > 24 * 60) {
            $min = 24 * 60;
        }

        return $min;
    }
}
