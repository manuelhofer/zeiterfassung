<?php
declare(strict_types=1);

/** Automatische lokale Einrichtung durch den normalen Systeminstaller. Keine Web-Systemrechte. */
final class WartungSystem
{
    public static function pfad(string $wurzel): string
    {
        $wurzel = WartungPlattform::pfad(realpath($wurzel) ?: $wurzel);
        if (WartungPlattform::windows()) { $wurzel = strtolower($wurzel); }
        return dirname($wurzel) . '/.zeit-wartung-' . substr(hash('sha256', $wurzel), 0, 16);
    }

    public static function konfig(string $wurzel, bool $privat = false): ?array
    {
        $basis = self::pfad($wurzel);
        if (!is_file($basis . '/system.json')) { return null; }
        $konfig = WartungDateien::json($basis . '/system.json');
        if ($privat) {
            $konfig = array_replace($konfig, WartungDateien::json($basis . '/privat/datenbank.json'));
            WartungPlattform::programme($konfig['programme'] ?? []);
        }
        return $konfig;
    }

    public static function anwendung(string $wurzel, array $konfig): array
    {
        if (WartungPlattform::windows()) { return WartungPlattform::anwendung($wurzel, $konfig); }
        // config/ ist während der normalen Terminal-Kopplung webschreibbar.
        // Deshalb PHP-Konfiguration NIE als privilegierter Wartungsprozess auswerten.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $json = WartungDateien::prozess(['runuser', '-u', $konfig['web_benutzer'], '--', PHP_BINARY,
                $wurzel . '/scripts/wartung_konfig.php'], $wurzel);
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
        return json_decode(WartungDateien::prozess([PHP_BINARY, $wurzel . '/scripts/wartung_konfig.php'], $wurzel), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function lokaleAdminDb(array $db): array
    {
        $teile = [];
        foreach (explode(';', substr($db['dsn'] ?? '', 6)) as $teil) {
            if (str_contains($teil, '=')) { [$key, $wert] = explode('=', $teil, 2); $teile[$key] = $wert; }
        }
        $host = $teile['host'] ?? $db['host'] ?? 'localhost';
        if (!isset($teile['unix_socket']) && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            // Externe DB: vorhandener Zugang. Keine erfundenen Adminrechte.
            return $db;
        }
        $name = $teile['dbname'] ?? $db['dbname'] ?? '';
        if (WartungPlattform::windows()) {
            return ['host' => '127.0.0.1', 'port' => $teile['port'] ?? $db['port'] ?? 3306,
                'dbname' => $name, 'user' => 'root', 'pass' => ''];
        }
        $socket = $teile['unix_socket'] ?? ini_get('pdo_mysql.default_socket');
        return ['dsn' => 'mysql:unix_socket=' . $socket . ';dbname=' . $name . ';charset=utf8mb4', 'user' => 'root', 'pass' => ''];
    }

    public static function einrichten(string $wurzel, string $webBenutzer, array $neustartDienste): array
    {
        $basis = self::pfad($wurzel);
        if (is_file($basis . '/system.json')) {
            // Wiederholte Installation ändert keine Schlüssel und keinen installierten Versionsbeleg.
            return self::konfig($wurzel, true);
        }
        foreach ([$basis => 0750, $basis . '/privat' => 0700, $basis . '/auftraege' => 0770,
            $basis . '/empfang' => 0700, $basis . '-sicherungen' => 0700] as $pfad => $modus) {
            WartungDateien::ordner($pfad, $modus);
            chmod($pfad, $modus);
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                $benutzer = posix_getpwnam($webBenutzer);
                if ($benutzer === false || !chgrp($pfad, $benutzer['gid'])) { throw new RuntimeException('Webbenutzer konnte nicht zugeordnet werden.'); }
            }
        }
        $konfig = ['status_pfad' => $basis, 'backup_pfad' => $basis . '-sicherungen',
            'web_benutzer' => $webBenutzer, 'transport' => 'datenbank', 'reserve_bytes' => 536870912,
            'neustart_befehl' => ['systemctl', 'restart', ...$neustartDienste],
            'zusatz_pfade' => [$basis . '/system.json', $basis . '/privat']];
        if (WartungPlattform::windows()) {
            $windows = WartungDateien::json($basis . '/privat/windows.json');
            if (isset($windows['openssl_conf'])) { putenv('OPENSSL_CONF=' . $windows['openssl_conf']); }
            $konfig['programme'] = $windows['programme'];
            $konfig['archiv_format'] = 'zip';
            $konfig['konfig_aufgabe'] = $windows['konfig_aufgabe'];
            $konfig['neustart_befehl'] = [$windows['programme']['powershell.exe'], '-NoProfile', '-NonInteractive',
                '-ExecutionPolicy', 'Bypass', '-File', $wurzel . '/scripts/windows/apache_neustart.ps1', '-Dienst', $windows['apache_dienst']];
            WartungPlattform::programme($konfig['programme']);
        }
        $app = self::anwendung($wurzel, $konfig);
        $terminal = ($app['app']['installation_typ'] ?? '') === 'terminal'
            || (!is_file($wurzel . '/config/config.local.php') && is_file($wurzel . '/config/geraet.local.php'));
        $konfig['rolle'] = $terminal ? 'terminal' : 'backend';
        $admin = [];
        if (!$terminal) { $admin['haupt'] = self::lokaleAdminDb($app['db']); }
        if ($app['offline_db']['enabled'] ?? false) { $admin['offline'] = self::lokaleAdminDb($app['offline_db']); }
        $privat = ['db_admin' => $admin];
        foreach ($admin as $db) { WartungDateien::pdo($db)->query('SELECT 1'); }
        if (!$terminal && !is_file($basis . '/privat/signatur.key')) {
            $paar = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072]);
            if ($paar === false || !openssl_pkey_export($paar, $key)) { throw new RuntimeException('Updateschlüssel konnte nicht erzeugt werden.'); }
            if (file_put_contents($basis . '/privat/signatur.key', $key) !== strlen($key)) { throw new RuntimeException('Updateschlüssel nicht speicherbar.'); }
            chmod($basis . '/privat/signatur.key', 0600);
        }
        foreach (['anfragen.lock' => 0640, 'auftraege/eingang.lock' => 0660] as $datei => $modus) {
            touch($basis . '/' . $datei); chmod($basis . '/' . $datei, $modus);
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) { chgrp($basis . '/' . $datei, posix_getpwnam($webBenutzer)['gid']); }
        }
        WartungDateien::schreiben($basis . '/privat/datenbank.json', $privat, 0600);
        WartungDateien::schreiben($basis . '/system.json', $konfig);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) { chgrp($basis . '/system.json', posix_getpwnam($webBenutzer)['gid']); }
        return array_replace($konfig, $privat);
    }

    public static function vorbereiten(string $wurzel, array $konfig, array $app): void
    {
        $basis = $konfig['status_pfad'];
        if (is_file($basis . '/version.json')) { return; }
        if ($konfig['rolle'] === 'terminal' && !is_file($wurzel . '/config/config.local.php')) { return; }
        if ($konfig['rolle'] === 'backend') {
            WartungDateien::schreiben($basis . '/pause.json', ['id' => 'ersteinrichtung', 'seit' => date(DATE_ATOM)]);
            $sperre = fopen($basis . '/anfragen.lock', 'r+');
            if (!$sperre || !flock($sperre, LOCK_EX)) { throw new RuntimeException('Die Erstinstallation kann noch nicht abgeschlossen werden.'); }
            // Vor der erstmaligen Übernahme werden die vorhandenen Daten gesichert.
            $id = 'ersteinrichtung-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            (new WartungBackup($wurzel, $konfig, $app))->erstellen($konfig['backup_pfad'] . '/' . $id);
            foreach (['14_migration_wartungsrechte.sql', '15_migration_wartung_kopplung.sql'] as $sql) {
                WartungDateien::mysql($konfig['db_admin']['haupt'], static function ($option, $name) use ($wurzel, $sql): void {
                    WartungDateien::prozess(['mariadb', $option, $name], null, $wurzel . '/sql/' . $sql);
                });
            }
            $pdo = WartungDateien::pdo($konfig['db_admin']['haupt']);
            $key = openssl_pkey_get_private(file_get_contents($basis . '/privat/signatur.key'));
            if ($key === false) { throw new RuntimeException('Updateschlüssel ist nicht lesbar.'); }
            $public = openssl_pkey_get_details($key)['key'];
            $stmt = $pdo->prepare('INSERT INTO wartung_system (id,oeffentlicher_schluessel) VALUES (1,?) ON DUPLICATE KEY UPDATE oeffentlicher_schluessel=VALUES(oeffentlicher_schluessel)');
            $stmt->execute([$public]);
            foreach ($pdo->query("SELECT id,db_benutzer,db_benutzer_host FROM terminal WHERE db_benutzer IS NOT NULL AND db_benutzer <> ''") as $terminal) {
                WartungKanal::rechte($pdo, $terminal['db_benutzer'], $terminal['db_benutzer_host'] ?? '%', (int)$terminal['id']);
            }
            fclose($sperre);
        }
        (new WartungDienst($wurzel, $konfig, $app))->initialisieren();
        if (is_file($basis . '/pause.json') && WartungDateien::json($basis . '/pause.json')['id'] === 'ersteinrichtung') { unlink($basis . '/pause.json'); }
    }

    public static function lebenszeichen(array $konfig, string $zustand = 'bereit', string $hinweis = ''): void
    {
        WartungDateien::schreiben($konfig['status_pfad'] . '/dienst.json', ['zeit' => time(), 'zustand' => $zustand, 'hinweis' => $hinweis]);
    }
}
