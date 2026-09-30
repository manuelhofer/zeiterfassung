<?php
declare(strict_types=1);

/** Dateizugriffe und Prozesse für den CLI-Wartungsdienst. Keine Shellauswertung. */
final class WartungDateien
{
    public static function json(string $pfad): array
    {
        $inhalt = file_get_contents($pfad);
        if ($inhalt === false) {
            throw new RuntimeException('Datei nicht lesbar: ' . $pfad);
        }
        $daten = json_decode($inhalt, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($daten)) {
            throw new RuntimeException('Ungültige JSON-Datei: ' . $pfad);
        }
        return $daten;
    }

    public static function schreiben(string $pfad, array $daten, int $modus = 0640): void
    {
        $tmp = $pfad . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (file_put_contents($tmp, json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false
                || !chmod($tmp, $modus) || !rename($tmp, $pfad)) {
                throw new RuntimeException('Datei nicht schreibbar: ' . $pfad);
            }
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    public static function ordner(string $pfad, int $modus = 0700): void
    {
        if (!is_dir($pfad) && !mkdir($pfad, $modus, true) && !is_dir($pfad)) {
            throw new RuntimeException('Ordner nicht anlegbar: ' . $pfad);
        }
    }

    public static function relativ(string $pfad): void
    {
        if ($pfad === '' || str_contains($pfad, "\0") || str_contains($pfad, '\\') || str_starts_with($pfad, '/')
            || preg_match('~(^|/)(\.{1,2}|\.git)(/|$)~', $pfad)) {
            throw new RuntimeException('Unzulässiger Paketpfad.');
        }
    }

    public static function geschuetzt(string $pfad): bool
    {
        return in_array($pfad, ['config/config.local.php', 'config/geraet.local.php', 'config/wartung.local.php', 'scripts/terminal/terminal.conf'], true)
            || str_starts_with($pfad, 'public/uploads/') && !str_ends_with($pfad, '/.gitkeep');
    }

    /** Stderr geht nicht in die Oberfläche: DB-/SSH-Programme können Geheimnisse ausgeben. */
    public static function prozess(array $argumente, ?string $verzeichnis = null, ?string $eingabe = null, ?string $ausgabe = null, int $frist = 900, bool $jsonFehler = false): string
    {
        $fehler = tmpfile();
        $resultat = tmpfile();
        if ($fehler === false || $resultat === false) {
            throw new RuntimeException('Temporäre Prozessdatei nicht anlegbar.');
        }
        $pipes = [];
        $prozess = proc_open($argumente, [0 => ['file', $eingabe ?? '/dev/null', 'r'],
            1 => $ausgabe === null ? $resultat : ['file', $ausgabe, 'w'], 2 => $fehler], $pipes, $verzeichnis);
        if (!is_resource($prozess)) {
            throw new RuntimeException('Wartungsprogramm konnte nicht gestartet werden.');
        }
        $ende = microtime(true) + $frist;
        do {
            $status = proc_get_status($prozess);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $ende) {
                proc_terminate($prozess, 9);
                proc_close($prozess);
                throw new RuntimeException(basename($argumente[0]) . ': Zeitlimit überschritten. Zustand prüfen.');
            }
            usleep(50000);
        } while (true);
        $code = $status['exitcode'];
        proc_close($prozess);
        rewind($resultat);
        $text = stream_get_contents($resultat);
        fclose($resultat);
        fclose($fehler);
        if ($code !== 0 && !($jsonFehler && $code === 1)) {
            throw new RuntimeException(basename($argumente[0]) . ': fehlgeschlagen (Exit ' . $code . ').');
        }
        return (string)$text;
    }

    public static function pdo(array $db): PDO
    {
        $dsn = $db['dsn'] ?? ('mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['dbname'] ?? '') . ';charset=utf8mb4');
        return new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]);
    }

    /** Passwort nur in einer kurzlebigen Datei mit Modus 0600, niemals in argv. */
    public static function mysql(array $db, callable $aktion): mixed
    {
        $werte = ['user' => $db['user'] ?? '', 'password' => $db['pass'] ?? '', 'host' => $db['host'] ?? 'localhost'];
        $name = $db['dbname'] ?? '';
        if (isset($db['dsn'])) {
            if (!str_starts_with($db['dsn'], 'mysql:')) {
                throw new RuntimeException('Nur MariaDB/MySQL wird unterstützt.');
            }
            foreach (explode(';', substr($db['dsn'], 6)) as $teil) {
                [$key, $wert] = array_pad(explode('=', $teil, 2), 2, '');
                if ($key === 'dbname') { $name = $wert; }
                if (in_array($key, ['host', 'port'], true)) { $werte[$key] = $wert; }
                if ($key === 'unix_socket') { $werte['socket'] = $wert; }
            }
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Datenbankname ist für die Wartung nicht geeignet.');
        }
        $datei = tempnam(sys_get_temp_dir(), 'zeit-db-');
        if ($datei === false) { throw new RuntimeException('DB-Optionsdatei nicht anlegbar.'); }
        chmod($datei, 0600);
        try {
            $inhalt = "[client]\n";
            foreach ($werte as $key => $wert) {
                $inhalt .= $key . '="' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string)$wert) . '"' . "\n";
            }
            if (file_put_contents($datei, $inhalt) === false) { throw new RuntimeException('DB-Optionsdatei nicht schreibbar.'); }
            return $aktion('--defaults-file=' . $datei, $name);
        } finally {
            unlink($datei);
        }
    }
}
