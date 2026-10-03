<?php
declare(strict_types=1);

/** Gerätekanal über vorhandene, individuell eingeschränkte DB-Anmeldungen. */
final class WartungKanal
{
    private const TEIL_BYTES = 262144;
    public function __construct(private PDO $pdo, private array $konfig, private bool $backend) {}

    public static function rechte(PDO $pdo, string $benutzer, string $host, int $terminalId): void
    {
        $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db) || !preg_match('/^[a-z0-9_]{1,80}$/', $benutzer) || $terminalId < 1) {
            throw new RuntimeException('Ungültige Gerätezuordnung.');
        }
        // Ein importierter Dump kann einen fehlenden Definer oder die alte DB
        // referenzieren. Vor den Geräte-Grants auf den aktuellen Backendzugang
        // und diese Datenbank binden; bestehende View-Grants bleiben erhalten.
        foreach ([
            'wartung_mein_geraet' => ['wartung_geraet', ''],
            'wartung_mein_befehl' => ['wartung_befehl', ''],
            'wartung_mein_download' => ['wartung_dateiteil', " AND richtung = 'hin'"],
            'wartung_mein_upload' => ['wartung_dateiteil', " AND richtung = 'zurueck'"],
        ] as $view => [$tabelle, $richtung]) {
            // USER() bleibt der Terminalclient; CURRENT_USER im Filter würde
            // stattdessen den Definer verwenden und die Gerätezuordnung brechen.
            $pdo->exec("CREATE OR REPLACE ALGORITHM=MERGE DEFINER=CURRENT_USER SQL SECURITY DEFINER VIEW `$db`.`$view` AS "
                . "SELECT * FROM `$db`.`$tabelle` WHERE db_benutzer = SUBSTRING_INDEX(USER(), '@', 1)$richtung WITH CASCADED CHECK OPTION");
        }
        $ziel = $pdo->quote($benutzer) . '@' . $pdo->quote($host);
        foreach (['wartung_system', 'wartung_mein_geraet', 'wartung_mein_befehl', 'wartung_mein_download', 'wartung_mein_upload'] as $view) {
            $pdo->exec("GRANT SELECT ON `$db`.`$view` TO $ziel");
        }
        $pdo->exec("GRANT UPDATE (gesehen, version, meldung) ON `$db`.wartung_mein_geraet TO $ziel");
        $pdo->exec("GRANT UPDATE (zustand, antwort) ON `$db`.wartung_mein_befehl TO $ziel");
        $pdo->exec("GRANT INSERT ON `$db`.wartung_mein_upload TO $ziel");
        $stmt = $pdo->prepare("INSERT INTO wartung_geraet (terminal_id, db_benutzer) VALUES (?, ?) ON DUPLICATE KEY UPDATE db_benutzer=VALUES(db_benutzer)");
        $stmt->execute([$terminalId, $benutzer]);
    }

    public function aufruf(array $terminal, array $anfrage): array
    {
        $id = bin2hex(random_bytes(16));
        $text = json_encode(['rpc' => $id, 'terminal_id' => (int)$terminal['id'], 'bis' => time() + 3600, 'anfrage' => $anfrage], JSON_THROW_ON_ERROR);
        $secret = file_get_contents($this->konfig['status_pfad'] . '/privat/signatur.key');
        if ($secret === false) { throw new RuntimeException('Der Update-Dienst ist nicht vollständig bereit.'); }
        if (!openssl_sign($text, $signatur, $secret, OPENSSL_ALGO_SHA256)) { throw new RuntimeException('Updateauftrag konnte nicht signiert werden.'); }
        $signatur = base64_encode($signatur);
        $stmt = $this->pdo->prepare('INSERT INTO wartung_befehl (id,db_benutzer,anfrage,signatur) VALUES (?,?,?,?)');
        $stmt->execute([$id, $terminal['db_benutzer'], $text, $signatur]);
        $frist = match ($anfrage['aktion'] ?? '') {
            'backup', 'installieren', 'vorbereiten' => 900,
            'pause' => 150,
            default => 15,
        };
        $ende = microtime(true) + ($this->konfig['antwort_timeout'] ?? $frist);
        $holen = $this->pdo->prepare('SELECT zustand,antwort FROM wartung_befehl WHERE id=?');
        while (microtime(true) < $ende) {
            $holen->execute([$id]);
            $antwort = $holen->fetch();
            if ($antwort && $antwort['zustand'] === 'erledigt') {
                $daten = json_decode($antwort['antwort'], true, 512, JSON_THROW_ON_ERROR);
                if (!($daten['ok'] ?? false)) { throw new RuntimeException(($terminal['name'] ?? 'Terminal') . ': ' . ($daten['fehler'] ?? 'Aktion fehlgeschlagen.')); }
                return $daten;
            }
            usleep(250000);
        }
        throw new RuntimeException(($terminal['name'] ?? 'Terminal') . ' antwortet nicht. Die Verbindung zum Gerät bitte prüfen.');
    }

    public function senden(string $benutzer, string $auftrag, string $datei, string $quelle): void
    {
        self::dateiPruefen($auftrag, $datei);
        $tabelle = $this->backend ? 'wartung_dateiteil' : 'wartung_mein_upload';
        $richtung = $this->backend ? 'hin' : 'zurueck';
        $stmt = $this->pdo->prepare("INSERT INTO $tabelle (db_benutzer,auftrag,richtung,datei,nummer,inhalt) VALUES (?,?,?,?,?,?)");
        $handle = fopen($quelle, 'rb');
        if (!$handle) { throw new RuntimeException('Übertragungsdatei nicht lesbar.'); }
        $nummer = 0;
        try {
            while (!feof($handle)) {
                $teil = fread($handle, self::TEIL_BYTES);
                if ($teil === false) { throw new RuntimeException('Dateiübertragung wurde unterbrochen.'); }
                if ($teil !== '' || $nummer === 0) { $stmt->execute([$benutzer, $auftrag, $richtung, $datei, $nummer++, $teil]); }
            }
        } finally { fclose($handle); }
    }

    public function empfangen(string $benutzer, string $auftrag, string $datei, string $ziel): void
    {
        self::dateiPruefen($auftrag, $datei);
        $tabelle = $this->backend ? 'wartung_dateiteil' : 'wartung_mein_download';
        $richtung = $this->backend ? 'zurueck' : 'hin';
        $stmt = $this->pdo->prepare("SELECT nummer,inhalt FROM $tabelle WHERE db_benutzer=? AND auftrag=? AND richtung=? AND datei=? AND nummer=?");
        $handle = fopen($ziel, 'xb');
        if (!$handle) { throw new RuntimeException('Empfangsdatei bereits vorhanden oder nicht schreibbar.'); }
        chmod($ziel, 0600);
        try {
            for ($nummer = 0; $nummer <= 65536; $nummer++) {
                $stmt->execute([$benutzer, $auftrag, $richtung, $datei, $nummer]);
                $zeile = $stmt->fetch();
                if (!$zeile) {
                    if ($nummer === 0) { throw new RuntimeException('Die Datei wurde nicht vollständig übertragen.'); }
                    return;
                }
                if (strlen($zeile['inhalt']) > self::TEIL_BYTES || fwrite($handle, $zeile['inhalt']) !== strlen($zeile['inhalt'])) { throw new RuntimeException('Empfangsdatei nicht vollständig schreibbar.'); }
            }
            throw new RuntimeException('Übertragungsdatei ist zu groß.');
        } finally { fclose($handle); }
    }

    private static function dateiPruefen(string $auftrag, string $datei): void
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $auftrag) || !preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $datei) || str_contains($datei, '..')) { throw new RuntimeException('Unzulässige Übertragungskennung.'); }
    }

    public function aufraeumen(): void
    {
        // Nur Transportdaten, niemals Sicherungsdateien oder Buchungsdaten.
        $this->pdo->exec('DELETE FROM wartung_dateiteil');
        $this->pdo->exec('DELETE FROM wartung_befehl');
    }

    public function agent(int $terminalId, string $version, callable $ausfuehren): void
    {
        $eigene = $this->pdo->query('SELECT terminal_id, db_benutzer FROM wartung_mein_geraet')->fetch();
        if (!$eigene || (int)$eigene['terminal_id'] !== $terminalId) { throw new RuntimeException('Geräteanmeldung passt nicht zur Kopplung.'); }
        $pfad = $this->konfig['status_pfad'];
        $public = (string)$this->pdo->query('SELECT oeffentlicher_schluessel FROM wartung_system WHERE id=1')->fetchColumn();
        if (openssl_pkey_get_public($public) === false) { throw new RuntimeException('Der Server ist für Updates noch nicht bereit.'); }
        $vertrauen = $pfad . '/privat/server.pub';
        if (!is_file($vertrauen)) {
            if (file_put_contents($vertrauen, $public) !== strlen($public)) { throw new RuntimeException('Serverzuordnung konnte nicht gespeichert werden.'); }
            chmod($vertrauen, 0600);
        }
        if (!hash_equals((string)file_get_contents($vertrauen), $public)) { throw new RuntimeException('Die Identität des Update-Servers hat sich geändert.'); }
        $stmt = $this->pdo->prepare('UPDATE wartung_mein_geraet SET gesehen=NOW(),version=?,meldung=?');
        $stmt->execute([$version, 'bereit']);
        $befehl = $this->pdo->query("SELECT * FROM wartung_mein_befehl WHERE zustand IN ('wartend','laeuft') ORDER BY erstellt,id LIMIT 1")->fetch();
        if (!$befehl) { return; }
        $id = $befehl['id'];
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) { throw new RuntimeException('Unzulässige Geräteanfrage.'); }
        $beleg = $pfad . '/privat/rpc-' . $id . '.json';
        try {
            $signatur = base64_decode($befehl['signatur'], true);
            if ($signatur === false || openssl_verify($befehl['anfrage'], $signatur, $public, OPENSSL_ALGO_SHA256) !== 1) { throw new RuntimeException('Die Geräteanfrage ist nicht vom Update-Dienst signiert.'); }
            $nachricht = json_decode($befehl['anfrage'], true, 512, JSON_THROW_ON_ERROR);
            if ($nachricht['rpc'] !== $id || $nachricht['terminal_id'] !== $terminalId || $nachricht['bis'] < time()) { throw new RuntimeException('Abgelaufene oder falsch zugeordnete Geräteanfrage.'); }
            if (is_file($beleg)) {
                $antwort = WartungDateien::json($beleg)['antwort'] ?? ['ok' => false, 'fehler' => 'Geräteauftrag wurde unterbrochen. Keine automatische Wiederholung.'];
            } else {
                WartungDateien::schreiben($beleg, ['begonnen' => date(DATE_ATOM)], 0600);
                $stmt = $this->pdo->prepare("UPDATE wartung_mein_befehl SET zustand='laeuft' WHERE id=?");
                $stmt->execute([$id]);
                $anfrage = $nachricht['anfrage'];
                $auftrag = $anfrage['id'] ?? '';
                if (($anfrage['aktion'] ?? '') === 'vorbereiten') {
                    $this->empfangen($eigene['db_benutzer'], $auftrag, 'paket.zip', $pfad . '/empfang/' . $auftrag . '.zip');
                    $plan = $pfad . '/empfang/' . $auftrag . '.json';
                    $this->empfangen($eigene['db_benutzer'], $auftrag, 'plan.json', $plan);
                    if (!hash_equals($anfrage['plan_sha256'] ?? '', hash_file('sha256', $plan))) { throw new RuntimeException('Der Updateplan stimmt nicht mit der Serversignatur überein.'); }
                }
                $antwort = $ausfuehren($anfrage);
                if (($anfrage['aktion'] ?? '') === 'backup') {
                    foreach (['manifest.json', ...array_keys($antwort['dateien'])] as $datei) {
                        $this->senden($eigene['db_benutzer'], $auftrag, $datei, $this->konfig['backup_pfad'] . '/' . $auftrag . '/' . $datei);
                    }
                }
                WartungDateien::schreiben($beleg, ['antwort' => $antwort], 0600);
            }
        } catch (Throwable $e) {
            $antwort = ['ok' => false, 'fehler' => $e instanceof PDOException ? 'Die Geräteverbindung ist unterbrochen.' : $e->getMessage()];
            WartungDateien::schreiben($beleg, ['antwort' => $antwort], 0600);
        }
        $stmt = $this->pdo->prepare("UPDATE wartung_mein_befehl SET zustand='erledigt',antwort=? WHERE id=?");
        $stmt->execute([json_encode($antwort, JSON_THROW_ON_ERROR), $id]);
    }
}
