<?php
declare(strict_types=1);
// Kein Zugriff auf Projektkonfiguration, Datenbanken oder die echte Systemuhr.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Berlin');

final class ZeitProbeStatement extends PDOStatement
{
    public function __construct(private mixed $wert) {}
    public function fetchColumn(int $column = 0): mixed { return $this->wert; }
}
final class ZeitProbePdo extends PDO
{
    public int $abfragen = 0;
    public function __construct(public mixed $wert) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($query !== 'SELECT UNIX_TIMESTAMP()') { throw new RuntimeException('Unerwartete Abfrage.'); }
        $this->abfragen++;
        return new ZeitProbeStatement($this->wert);
    }
}
final class Start
{
    public static array $daten = ['db' => ['host' => 'fixture', 'dbname' => 'probe', 'user' => 'fixture'], 'terminal' => ['id' => 1]];
    public static function konfig(): array { return self::$daten; }
}
final class Helper
{
    public static bool $terminal = true;
    public static function istTerminalInstallation(): bool { return self::$terminal; }
}
final class Database
{
    public static ZeitProbePdo $pdo;
    public static bool $online = true;
    public static function getInstanz(): self { return new self(); }
    public function istHauptdatenbankVerfuegbar(): bool { return self::$online; }
    public function getVerbindung(): PDO { return self::$pdo; }
}
function neu(): void
{
    foreach (['epoche', 'monoton'] as $name) {
        (new ReflectionProperty(TerminalZeit::class, $name))->setValue(null, null);
    }
}
$tests = 0;
function gut(string $name, bool $ok): void
{
    global $tests;
    if (!$ok) { throw new RuntimeException('FAIL ' . $name); }
    $tests++;
    echo 'PASS ' . $name . "\n";
}
$basis = sys_get_temp_dir() . '/zeit-uhr-' . bin2hex(random_bytes(6));
mkdir($basis . '/core', 0700, true);
mkdir($basis . '/config', 0700);
$root = dirname(__DIR__, 2);
copy($root . '/core/TerminalZeit.php', $basis . '/core/TerminalZeit.php');
require $basis . '/core/TerminalZeit.php';
$cache = $basis . '/config/terminalzeit.local.json';
try {
    $server = time() + 172800;
    Database::$pdo = new ZeitProbePdo((string)$server);
    $jetzt = TerminalZeit::jetzt();
    gut('Backendzeit gewinnt bei zwei Tagen Abweichung zur lokalen Uhr', abs($jetzt->getTimestamp() - $server) < 2);
    gut('Heute verwendet denselben Backend-Kalendertag', TerminalZeit::heute()->format('Y-m-d') === $jetzt->format('Y-m-d'));
    TerminalZeit::anzeige();
    gut('Zeitquelle benötigt nur eine Abfrage je Request', Database::$pdo->abfragen === 1);
    if (is_file($cache)) {
        $daten = json_decode((string)file_get_contents($cache), true, 16, JSON_THROW_ON_ERROR);
        gut('Cache enthält keine DB-Zugangsdaten', array_keys($daten) === ['epoche', 'monoton', 'systemstart', 'bindung']);
        $daten['monoton'] = hrtime(true) / 1e9 - 60;
        file_put_contents($cache, json_encode($daten, JSON_THROW_ON_ERROR));
        Database::$online = false;
        neu();
        gut('Offline läuft letzter Abgleich trotz falscher lokaler Uhr weiter', abs(TerminalZeit::jetzt()->getTimestamp() - ($server + 60)) < 2);
        gut('Offline-Abgleich führt keine weitere DB-Abfrage aus', Database::$pdo->abfragen === 1);
        Start::$daten['terminal']['id'] = 2;
        neu();
        gut('Fremde Kopplung übernimmt keinen alten Zeitstand', abs(TerminalZeit::jetzt()->getTimestamp() - time()) < 2);
        Start::$daten['terminal']['id'] = 1;
        $daten['systemstart'] = 'anderer-systemstart';
        file_put_contents($cache, json_encode($daten, JSON_THROW_ON_ERROR));
        neu();
        gut('Monotoner Wert eines anderen Systemstarts wird verworfen', abs(TerminalZeit::jetzt()->getTimestamp() - time()) < 2);
        file_put_contents($cache, '{kaputt');
        neu();
        gut('Defekter Cache verhindert keine lokale Offlinezeit', abs(TerminalZeit::jetzt()->getTimestamp() - time()) < 2);
        unlink($cache . '.lock');
        mkdir($cache . '.lock', 0700);
        Database::$online = true;
        neu();
        $warnungen = [];
        set_error_handler(static function (int $grad, string $text) use (&$warnungen): bool {
            if (error_reporting() & $grad) { $warnungen[] = $text; }
            return true;
        });
        try { $ohneCache = TerminalZeit::jetzt()->getTimestamp(); }
        finally { restore_error_handler(); }
        gut('Cache-Schreibfehler lässt Onlinezeit ohne sichtbare Warnungen nutzbar', abs($ohneCache - $server) < 2 && $warnungen === []);
        rmdir($cache . '.lock');
    }
    Database::$online = true;
    foreach ([['2026-07-01 10:00:00', '12:00', 'Sommerzeit'], ['2026-01-01 10:00:00', '11:00', 'Winterzeit']] as [$utc, $erwartet, $name]) {
        Database::$pdo->wert = (string)(new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
        neu();
        gut($name . ' der Backendzeit bleibt Europe/Berlin', TerminalZeit::jetzt()->format('H:i') === $erwartet);
    }
    foreach ([false, '0', '1700000000;id', '9999999999'] as $wert) {
        $abgewiesen = false;
        try { TerminalZeit::serverEpoche(new ZeitProbePdo($wert)); }
        catch (RuntimeException) { $abgewiesen = true; }
        gut('Ungültige Backendzeit wird abgewiesen: ' . json_encode($wert), $abgewiesen);
    }
    Helper::$terminal = false;
    neu();
    gut('Backend verwendet weiterhin seinen eigenen Systemzeitpunkt', abs(TerminalZeit::jetzt()->getTimestamp() - time()) < 2);
    echo "Ergebnis: $tests Zeit-Prüfungen erfolgreich.\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basis, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $datei) {
        $datei->isDir() ? rmdir($datei->getPathname()) : unlink($datei->getPathname());
    }
    rmdir($basis);
}
