<?php
declare(strict_types=1);

/** Alle Einstiegspunkte halten die Lesesperre bis zum Prozessende (auch Shutdown-Sync). */
final class WartungSperre
{
    private static mixed $sperre = null;

    public static function konfig(?string $wurzel = null): ?array
    {
        $wurzel ??= dirname(__DIR__);
        return WartungSystem::konfig($wurzel);
    }

    public static function anfrage(): void
    {
        if (self::$sperre !== null || ($konfig = self::konfig()) === null) { return; }
        $pfad = $konfig['status_pfad'];
        // Die Statusseite bleibt auch ohne DB während des Updates lesbar.
        // Berechtigung wird beim Öffnen der Wartungsmaske in der Sitzung hinterlegt.
        if (is_file($pfad . '/pause.json') || !is_readable($pfad . '/anfragen.lock')) {
            self::pausenAntwort($pfad);
        }
        self::$sperre = fopen($pfad . '/anfragen.lock', 'r');
        if (self::$sperre === false || !flock(self::$sperre, LOCK_SH | LOCK_NB)) {
            self::pausenAntwort($pfad);
        }
        clearstatcache(true, $pfad . '/pause.json');
        if (is_file($pfad . '/pause.json')) { self::pausenAntwort($pfad); }
    }

    private static function pausenAntwort(string $pfad): never
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Wartung läuft; Auftrag wurde nicht ausgeführt.\n");
            exit(75);
        }
        http_response_code(503);
        header('Retry-After: 15');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $status = is_file($pfad . '/status.json') ? WartungDateien::json($pfad . '/status.json') : null;
        $unterbrochen = in_array($status['zustand'] ?? '', ['fehlgeschlagen', 'unterbrochen'], true);
        echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="5"><link rel="stylesheet" href="css/wartung.css"><title>Backup und Updates</title><main class="wartung">';
        echo $unterbrochen
            ? '<h1>Wartung unterbrochen</h1><p>Buchungen bleiben zum Schutz der Daten angehalten. Bitte die Betreuung der Installation verständigen.</p>'
            : '<h1>Wartung läuft</h1><p>Bitte kurz warten. Buchungen sind während der Sicherung und Installation angehalten. Diese Seite lädt sich erneut.</p>';
        if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' && ($_GET['seite'] ?? '') === 'wartung'
            && ($_SESSION['wartung_status_bis'] ?? 0) > time()
            && !empty($_SESSION['auth_mitarbeiter_id'])
            && ($_SESSION['wartung_status_mitarbeiter'] ?? null) === $_SESSION['auth_mitarbeiter_id']
            && $status !== null) {
            require dirname(__DIR__) . '/views/wartung/status.php';
        }
        echo '</main></html>';
        exit;
    }
}
