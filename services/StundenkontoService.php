<?php
declare(strict_types=1);

/**
 * StundenkontoService
 *
 * Read-only Zugriff auf das Stundenkonto (Gutstunden/Minusstunden).
 *
 * Ziel (Zusatz2):
 * - Terminal/Report/PDF sollen den **aktuellen Saldo** anzeigen koennen,
 *   ohne dafuer "25 Jahre" Zeitbuchungen neu zu aggregieren.
 * - In der Monatsuebersicht soll z. B. der Saldo **bis Ende Vormonat**
 *   angezeigt werden (exklusiv der Stunden des aktuellen Monatsreports).
 *
 * Hinweis:
 * - Dieses Service ist bewusst minimal (Micro-Patch): nur Summen-Funktionen.
 * - Buchungen/Verteilungen/Backend-UI folgen in weiteren Patches.
 */
class StundenkontoService
{
    private static ?StundenkontoService $instanz = null;
    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstanz();
    }

    public static function getInstanz(): StundenkontoService
    {
        if (self::$instanz === null) {
            self::$instanz = new StundenkontoService();
        }
        return self::$instanz;
    }

    /**
     * Liefert den Saldo (Summe delta_minuten) bis zu einem Stichtag (exklusiv).
     *
     * Beispiel:
     * - bisDatumExklusiv = 2026-02-01 -> summiert alle Korrekturen mit wirksam_datum < 2026-02-01
     */
    public function holeSaldoMinutenBisDatumExklusiv(int $mitarbeiterId, string $bisDatumExklusivIso): int
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        $bisDatumExklusivIso = trim($bisDatumExklusivIso);

        if ($mitarbeiterId <= 0 || $bisDatumExklusivIso === '') {
            return 0;
        }

        $sql = 'SELECT COALESCE(SUM(delta_minuten), 0) AS sum_min
                FROM stundenkonto_korrektur
                WHERE mitarbeiter_id = :mid
                  AND wirksam_datum < :bis';

        try {
            $row = $this->db->fetchEine($sql, [
                'mid' => $mitarbeiterId,
                'bis' => $bisDatumExklusivIso,
            ]);
        } catch (\Throwable $e) {
            // Wenn Migration noch nicht eingespielt ist, existiert die Tabelle nicht.
            if (class_exists('Logger')) {
                Logger::warn('StundenkontoService: Summe nicht lesbar (Tabelle fehlt?)', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'bis'            => $bisDatumExklusivIso,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'stundenkonto');
            }
            return 0;
        }

        return (int)($row['sum_min'] ?? 0);
    }

    /**
     * Liefert den Stundenkonto-Saldo (Minuten) bis Ende Vormonat.
     *
     * Technisch: Summe aller Korrekturen mit wirksam_datum < 1. Tag des Monats.
     */
    public function holeSaldoMinutenBisVormonat(int $mitarbeiterId, int $jahr, int $monat): int
    {
        // Monat plausibilisieren
        if ($monat < 1) {
            $monat = 1;
        } elseif ($monat > 12) {
            $monat = 12;
        }

        try {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $jahr, $monat), new \DateTimeZone('Europe/Berlin'));
        } catch (\Throwable $e) {
            // Fallback: aktueller Monatsanfang
            $start = (new \DateTimeImmutable('first day of this month', new \DateTimeZone('Europe/Berlin')));
        }

        $bisIso = $start->format('Y-m-d');
        return $this->holeSaldoMinutenBisDatumExklusiv($mitarbeiterId, $bisIso);
    }

    /**
     * Helper: Minuten als Stunden-String (z. B. +12.50 / -3.25) formatieren.
     */
    public function formatMinutenAlsStundenString(int $minuten, bool $mitVorzeichen = true): string
    {
        $stunden = $minuten / 60.0;
        if ($mitVorzeichen) {
            return sprintf('%+.2f', $stunden);
        }
        return sprintf('%.2f', $stunden);
    }
}
