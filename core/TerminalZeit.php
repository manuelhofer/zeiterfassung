<?php
declare(strict_types=1);

/** Gemeinsame Backendzeit; offline läuft der letzte Abgleich monoton weiter. */
final class TerminalZeit
{
    private static ?int $epoche = null;
    private static ?float $monoton = null;

    public static function serverEpoche(PDO $pdo): int
    {
        // Ohne Argument liefert UNIX_TIMESTAMP UTC, unabhängig von der SQL-Zeitzone.
        $wert = $pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
        if (!is_scalar($wert) || !preg_match('/^[0-9]{10}$/', (string)$wert)) {
            throw new RuntimeException('Die Backendzeit ist ungültig.');
        }
        $epoche = (int)$wert;
        if ($epoche < 1577836800 || $epoche >= 4102444800) {
            throw new RuntimeException('Die Backendzeit liegt außerhalb des unterstützten Bereichs.');
        }
        return $epoche;
    }

    public static function jetzt(): DateTimeImmutable
    {
        if (!Helper::istTerminalInstallation()) {
            return new DateTimeImmutable('now');
        }
        if (self::$epoche === null) {
            $monoton = hrtime(true) / 1e9;
            try {
                $db = Database::getInstanz();
                if ($db->istHauptdatenbankVerfuegbar()) {
                    self::$epoche = self::serverEpoche($db->getVerbindung());
                    self::$monoton = hrtime(true) / 1e9;
                    self::merkeAbgleich();
                }
            } catch (Throwable) {
                // Ein fehlender Zeitabgleich darf die Offline-Buchung nicht verhindern.
            }
            if (self::$epoche === null) {
                self::ladeAbgleich($monoton);
            }
            if (self::$epoche === null) {
                self::$epoche = time();
                self::$monoton = hrtime(true) / 1e9;
            }
        }
        $vergangen = max(0.0, hrtime(true) / 1e9 - (float)self::$monoton);
        return (new DateTimeImmutable('@' . (string)(self::$epoche + (int)$vergangen)))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    public static function heute(): DateTimeImmutable
    {
        return self::jetzt()->setTime(0, 0, 0);
    }

    public static function anzeige(): array
    {
        return ['epoche' => self::jetzt()->getTimestamp(), 'zeitzone' => date_default_timezone_get()];
    }

    private static function pfad(): string
    {
        return dirname(__DIR__) . '/config/terminalzeit.local.json';
    }

    private static function bindung(): string
    {
        $konfig = Start::konfig();
        $db = $konfig['db'] ?? [];
        return hash('sha256', json_encode([
            $db['dsn'] ?? '', $db['host'] ?? '', $db['dbname'] ?? '', $db['user'] ?? '',
            $konfig['terminal']['id'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    private static function systemstart(): string
    {
        $pfad = '/proc/sys/kernel/random/boot_id';
        return is_readable($pfad) ? trim((string)@file_get_contents($pfad)) : '';
    }

    private static function merkeAbgleich(): void
    {
        $systemstart = self::systemstart();
        $pfad = self::pfad();
        if ($systemstart === '' || !is_writable(dirname($pfad))) { return; }
        $temp = null;
        $anfragen = null;
        try {
            if (class_exists(WartungSystem::class)) {
                $status = WartungSystem::pfad(dirname(__DIR__));
                if (is_file($status . '/anfragen.lock')) {
                    $anfragen = @fopen($status . '/anfragen.lock', 'r');
                    if ($anfragen === false || !flock($anfragen, LOCK_SH | LOCK_NB)) { return; }
                }
                // Auch reine Uhrabfragen müssen ein konsistentes Datei-Backup ermöglichen.
                if (is_file($status . '/pause.json')) { return; }
            }
            $daten = json_encode(['epoche' => self::$epoche, 'monoton' => self::$monoton,
                'systemstart' => $systemstart, 'bindung' => self::bindung()], JSON_THROW_ON_ERROR);
            // Keine PHP-Konfiguration erzeugen. Die Datei gehört zur Geräteinstallation.
            // Der Cache ist optional: ein Speicher-/Rechtefehler darf keine
            // Warnung in die Buchungsantwort oder den JSON-Uhrkanal schreiben.
            $temp = @tempnam(dirname($pfad), '.terminalzeit-');
            if ($temp === false) { return; }
            if (!@chmod($temp, 0600)) { return; }
            if (@file_put_contents($temp, $daten, LOCK_EX) !== strlen($daten)) { return; }
            // Ein paralleler GET darf keinen älteren Stand über einen neueren schreiben.
            $sperre = @fopen($pfad . '.lock', 'c');
            if ($sperre === false) { return; }
            try {
                if (!flock($sperre, LOCK_EX)) { return; }
                $vorher = is_file($pfad) ? json_decode((string)@file_get_contents($pfad), true) : null;
                if (is_array($vorher) && ($vorher['systemstart'] ?? '') === $systemstart
                    && ($vorher['bindung'] ?? '') === self::bindung()
                    && (float)($vorher['monoton'] ?? 0) > (float)self::$monoton) { return; }
                if (!@rename($temp, $pfad)) { return; }
            } finally { flock($sperre, LOCK_UN); fclose($sperre); }
        } catch (Throwable) {
            // Die aktuelle Onlinezeit bleibt auch ohne schreibbaren Cache nutzbar.
        } finally {
            if (is_string($temp) && is_file($temp)) { @unlink($temp); }
            if (is_resource($anfragen)) { flock($anfragen, LOCK_UN); fclose($anfragen); }
        }
    }

    private static function ladeAbgleich(float $monoton): void
    {
        $pfad = self::pfad();
        if (!is_file($pfad) || !is_readable($pfad)) { return; }
        try {
            $daten = json_decode((string)@file_get_contents($pfad), true, 16, JSON_THROW_ON_ERROR);
            $systemstart = self::systemstart();
            if (!is_array($daten) || $systemstart === '' || ($daten['systemstart'] ?? '') !== $systemstart
                || ($daten['bindung'] ?? '') !== self::bindung() || !is_int($daten['epoche'] ?? null)
                || $daten['epoche'] < 1577836800 || $daten['epoche'] >= 4102444800
                || !is_numeric($daten['monoton'] ?? null) || $daten['monoton'] < 0
                || $daten['monoton'] > $monoton) { return; }
            self::$epoche = $daten['epoche'];
            self::$monoton = (float)$daten['monoton'];
        } catch (Throwable) {
            // Beschädigter oder fremder Cache wird nicht als Zeitquelle übernommen.
        }
    }
}
