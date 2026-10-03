<?php
declare(strict_types=1);

/** Betriebssystemgrenzen; fachlicher Updateablauf und Transport bleiben gemeinsam. */
final class WartungPlattform
{
    private static array $programme = [];

    public static function windows(): bool { return PHP_OS_FAMILY === 'Windows'; }
    public static function pfad(string $pfad): string { return str_replace('\\', '/', $pfad); }
    public static function absolut(string $pfad): bool
    {
        return str_starts_with($pfad, '/') || preg_match('~^[a-zA-Z]:[/\\\\]~', $pfad) === 1;
    }
    public static function vergleich(string $pfad): string
    {
        $pfad = rtrim(self::pfad($pfad), '/');
        return self::windows() ? strtolower($pfad) : $pfad;
    }
    public static function innerhalb(string $pfad, string $basis): bool
    {
        return self::vergleich($pfad) === self::vergleich($basis)
            || str_starts_with(self::vergleich($pfad), self::vergleich($basis) . '/');
    }
    public static function programme(array $programme): void { self::$programme = $programme; }
    public static function programm(string $name): string
    {
        return self::$programme[$name] ?? $name;
    }
    public static function phpSkript(string $datei, array $argumente = []): array
    {
        // Windows hält das CLI-Hauptskript offen. require schließt die Quelldatei
        // nach dem Laden, damit sich der laufende Updater selbst ersetzen kann.
        return self::windows()
            ? [PHP_BINARY, '-r', "require base64_decode('" . base64_encode($datei) . "');", ...$argumente]
            : [PHP_BINARY, $datei, ...$argumente];
    }
    public static function bytes(string $pfad): int
    {
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pfad, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $datei) {
            if ($datei->isFile() && !$datei->isLink()) { $bytes += $datei->getSize(); }
        }
        return $bytes;
    }

    /** SYSTEM startet nur einen fest installierten LocalService-Auftrag, niemals config.php. */
    public static function anwendung(string $wurzel, array $konfig): array
    {
        $basis = $konfig['status_pfad'];
        $sperre = fopen($basis . '/anwendung.lock', 'c');
        if (!$sperre || !flock($sperre, LOCK_EX)) { throw new RuntimeException('Konfigurationssperre fehlt.'); }
        try {
            $datei = $basis . '/anwendung/config.json';
            if (is_file($datei) && !unlink($datei)) { throw new RuntimeException('Konfigurationsantwort nicht löschbar.'); }
            WartungDateien::prozess(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
                '-File', $wurzel . '/scripts/windows/konfig_start.ps1', '-Aufgabe', $konfig['konfig_aufgabe']], frist: 25);
            $ende = microtime(true) + 20;
            do {
                clearstatcache(true, $datei);
                if (is_file($datei)) {
                    $app = WartungDateien::json($datei);
                    if (($app['app']['installation_typ'] ?? '') !== 'backend' || !isset($app['db'])) {
                        throw new RuntimeException('Windows-Wartung benötigt eine Backendkonfiguration.');
                    }
                    return $app;
                }
                usleep(100000);
            } while (microtime(true) < $ende);
            throw new RuntimeException('Windows-Konfigurationsaufgabe antwortet nicht. Installer erneut ausführen.');
        } finally { flock($sperre, LOCK_UN); fclose($sperre); }
    }

    public static function dateisperrenPruefen(string $wurzel, array $dateien): void
    {
        if (!self::windows()) { return; }
        // Prüfung vor SQL; ein späterer Zugriffskonflikt wird weiterhin als Fehler behandelt.
        $liste = tempnam(sys_get_temp_dir(), 'zeit-lock-');
        if ($liste === false) { throw new RuntimeException('Dateiprüfung nicht vorbereitbar.'); }
        $problem = '';
        try {
            $pfade = [];
            foreach ($dateien as $pfad) {
                WartungPaket::zielPruefen($wurzel, $pfad);
                if (is_file($wurzel . '/' . $pfad)) { $pfade[] = $wurzel . '/' . $pfad; }
            }
            WartungDateien::schreiben($liste, $pfade, 0600);
            $antwort = json_decode(WartungDateien::prozess(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
                '-File', $wurzel . '/scripts/windows/dateisperren.ps1', '-Liste', $liste], frist: 60, jsonFehler: true), true, 512, JSON_THROW_ON_ERROR);
            if (($antwort['ok'] ?? false) !== true) {
                $problem = ($antwort['datei'] ?? '') === '' ? '' : ' (' . basename(WartungPlattform::pfad($antwort['datei'])) . ')';
                throw new RuntimeException('Dateiaustausch nicht freigegeben.');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Programmdateien sind geöffnet oder nicht ersetzbar' . $problem . '. Bitte Editoren und andere Dateizugriffe schließen.', 0, $e);
        } finally { unlink($liste); }
    }
}
