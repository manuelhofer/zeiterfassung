<?php
declare(strict_types=1);

/** Vollständige Anwendung und installationslokale Datenbanken, ohne automatische Löschung. */
final class WartungBackup
{
    public function __construct(private string $wurzel, private array $konfig, private array $app) {}

    public function erstellen(string $ziel): array
    {
        if (file_exists($ziel)) { throw new RuntimeException('Sicherungsziel existiert bereits.'); }
        WartungDateien::ordner($ziel);
        $pfade = [$this->wurzel, ...($this->konfig['zusatz_pfade'] ?? []), $this->konfig['status_pfad'] . '/version.json'];
        $pfade = array_values(array_filter($pfade, static fn($p) => file_exists($p)));
        foreach ($pfade as $pfad) {
            if (!str_starts_with($pfad, '/') || str_contains($pfad, "\n")) { throw new RuntimeException('Ungültiger Sicherungspfad.'); }
            $iterator = is_dir($pfad) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pfad, FilesystemIterator::SKIP_DOTS)) : [$pfad];
            foreach ($iterator as $datei) {
                $name = (string)$datei;
                if (!is_link($name)) { continue; }
                $real = realpath($name);
                $erfasst = false;
                foreach ($pfade as $basis) {
                    if ($real !== false && ($real === $basis || str_starts_with($real, rtrim($basis, '/') . '/'))) { $erfasst = true; }
                }
                if (!$erfasst) { throw new RuntimeException('Nicht mitgesichertes Linkziel: ' . $name . '. Zusatzpfad konfigurieren.'); }
            }
        }
        WartungDateien::prozess(['tar', '--create', '--gzip', '--file=' . $ziel . '/dateien.tar.gz', '--', ...$pfade]);
        WartungDateien::prozess(['tar', '--list', '--gzip', '--file=' . $ziel . '/dateien.tar.gz']);
        $dbs = $this->datenbanken();
        foreach ($dbs as $rolle => $db) {
            $pdo = WartungDateien::pdo($db);
            WartungDateien::mysql($db, static function ($option, $name) use ($ziel, $rolle): void {
                WartungDateien::prozess(['mariadb-dump', $option, '--lock-all-tables', '--routines', '--events', '--triggers', '--hex-blob', '--default-character-set=utf8mb4', $name], null, null, $ziel . '/' . $rolle . '.sql');
            });
            if (filesize($ziel . '/' . $rolle . '.sql') < 100) { throw new RuntimeException('Unvollständiger Datenbankdump.'); }
            // Zugehörige Benutzer einschließlich Terminalzugängen; keine fremden DB-Konten.
            $benutzer = [$this->app[$rolle === 'haupt' ? 'db' : 'offline_db']['user']];
            if ($rolle === 'haupt') {
                foreach ($pdo->query("SELECT db_benutzer FROM terminal WHERE db_benutzer IS NOT NULL AND db_benutzer <> ''") as $terminal) { $benutzer[] = $terminal['db_benutzer']; }
            }
            $sql = "-- Zugehörige Benutzer und Berechtigungen. Nur bei Wiederherstellung auf separatem Server ausführen.\n";
            $abfrage = $pdo->prepare('SELECT User, Host FROM mysql.user WHERE User = ?');
            foreach (array_unique($benutzer) as $name) {
                $abfrage->execute([$name]);
                $konten = $abfrage->fetchAll();
                if ($konten === []) { throw new RuntimeException('DB-Benutzersicherung unvollständig.'); }
                foreach ($konten as $konto) {
                    $kennung = $pdo->quote($konto['User']) . '@' . $pdo->quote($konto['Host']);
                    $create = $pdo->query('SHOW CREATE USER ' . $kennung)->fetch(PDO::FETCH_NUM);
                    $sql .= $create[0] . ";\n";
                    foreach ($pdo->query('SHOW GRANTS FOR ' . $kennung)->fetchAll(PDO::FETCH_COLUMN) as $grant) { $sql .= $grant . ";\n"; }
                }
            }
            if (file_put_contents($ziel . '/' . $rolle . '-benutzer.sql', $sql) === false) { throw new RuntimeException('Benutzersicherung nicht schreibbar.'); }
        }
        $dateien = [];
        foreach (glob($ziel . '/*') ?: [] as $datei) {
            chmod($datei, 0600);
            $dateien[basename($datei)] = ['bytes' => filesize($datei), 'sha256' => hash_file('sha256', $datei)];
        }
        $manifest = ['erstellt' => date(DATE_ATOM), 'rolle' => $this->app['app']['installation_typ'] ?? 'backend',
            'terminal_id' => $this->app['terminal']['id'] ?? null, 'pfade' => $pfade, 'dateien' => $dateien];
        WartungDateien::schreiben($ziel . '/manifest.json', $manifest, 0600);
        self::pruefen($ziel);
        return $manifest;
    }

    public static function pruefen(string $ziel): void
    {
        $manifest = WartungDateien::json($ziel . '/manifest.json');
        foreach ($manifest['dateien'] as $name => $info) {
            WartungDateien::relativ($name);
            if (!is_file($ziel . '/' . $name) || hash_file('sha256', $ziel . '/' . $name) !== $info['sha256']
                || filesize($ziel . '/' . $name) !== $info['bytes']) {
                throw new RuntimeException('Sicherungsprüfung fehlgeschlagen: ' . $name);
            }
        }
    }

    public function datenbanken(): array
    {
        $dbs = [];
        if (($this->app['app']['installation_typ'] ?? 'backend') === 'backend') {
            $dbs['haupt'] = array_replace($this->app['db'], $this->konfig['db_admin']['haupt'] ?? []);
        }
        if (($this->app['offline_db']['enabled'] ?? false) === true) {
            $dbs['offline'] = array_replace($this->app['offline_db'], $this->konfig['db_admin']['offline'] ?? []);
        }
        return $dbs;
    }
}
