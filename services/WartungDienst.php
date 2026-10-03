<?php
declare(strict_types=1);

/** CLI-Orchestrierung. Webaufträge enthalten ausschließlich Aktion und bereits geprüften Commit. */
final class WartungDienst
{
    private array $app;
    private string $statusPfad;
    private array $status = [];
    private ?WartungKanal $kanal = null;
    private mixed $dienstSperre = null;

    public function __construct(private string $wurzel, private array $konfig, ?array $app = null)
    {
        $this->wurzel = WartungPlattform::pfad(realpath($wurzel) ?: throw new RuntimeException('Anwendungspfad fehlt.'));
        $this->app = $app ?? WartungSystem::anwendung($this->wurzel, $konfig);
        foreach (['status_pfad', 'backup_pfad'] as $schluessel) {
            $pfad = $konfig[$schluessel] ?? '';
            $real = realpath($pfad);
            if ($real === false || WartungPlattform::vergleich($pfad) !== WartungPlattform::vergleich($real)
                || WartungPlattform::innerhalb($real, $this->wurzel) || preg_match('~^([a-zA-Z]:)?[/\\\\]$~', $real)) {
                throw new RuntimeException($schluessel . ' muss als echter, separater Ordner außerhalb der Anwendung existieren.');
            }
        }
        if (WartungPlattform::innerhalb($konfig['backup_pfad'], $konfig['status_pfad'])
            || WartungPlattform::innerhalb($konfig['status_pfad'], $konfig['backup_pfad'])) {
            throw new RuntimeException('Status- und Sicherungsordner müssen getrennt sein.');
        }
        foreach ($konfig['zusatz_pfade'] ?? [] as $pfad) {
            if (realpath($pfad) === false || WartungPlattform::vergleich(realpath($pfad)) !== WartungPlattform::vergleich($pfad)
                || preg_match('~^([a-zA-Z]:)?[/\\\\]$~', $pfad) || WartungPlattform::innerhalb($konfig['backup_pfad'], $pfad)) {
                throw new RuntimeException('Zusatzpfad fehlt oder enthält das Sicherungsziel.');
            }
        }
        $this->statusPfad = $konfig['status_pfad'];
    }

    private function exklusiv(): void
    {
        if (is_resource($this->dienstSperre)) { return; }
        $this->dienstSperre = fopen($this->statusPfad . '/dienst.lock', 'c');
        if (!$this->dienstSperre || !flock($this->dienstSperre, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Ein Wartungsdienst läuft bereits.'); }
    }

    public function initialisieren(): array
    {
        $this->exklusiv();
        if (is_file($this->statusPfad . '/version.json')) { throw new RuntimeException('Bereits eingerichtet; Versionsstand wird nicht überschrieben.'); }
        $commit = trim(WartungDateien::prozess(['git', '-C', $this->wurzel, 'rev-parse', 'HEAD']));
        $zip = $this->statusPfad . '/initial.zip';
        WartungDateien::prozess(['git', '-C', $this->wurzel, 'archive', '--format=zip', '--output=' . $zip, $commit]);
        $dateien = WartungPaket::inventar($zip);
        WartungPaket::bestandPruefen($this->wurzel, $dateien);
        $manifest = WartungDateien::json($this->wurzel . '/updates/migrationen.json');
        $this->datenbankPruefen($manifest['migrationen']);
        WartungDateien::ordner($this->statusPfad . '/auftraege', 0770);
        WartungDateien::ordner($this->statusPfad . '/empfang');
        foreach (['anfragen.lock', 'auftraege/eingang.lock'] as $datei) {
            if (!touch($this->statusPfad . '/' . $datei)) { throw new RuntimeException('Sperrdatei nicht anlegbar.'); }
            chmod($this->statusPfad . '/' . $datei, $datei === 'anfragen.lock' ? 0640 : 0660);
        }
        $version = ['commit' => $commit, 'dateien' => $dateien, 'migrationen' => $manifest['migrationen']];
        WartungDateien::schreiben($this->statusPfad . '/version.json', $version);
        unlink($zip);
        return ['ok' => true, 'commit' => $commit];
    }

    public function verarbeiten(): void
    {
        $this->exklusiv();
        if (!$this->istBackend()) { throw new RuntimeException('Nur das Backend verarbeitet Webaufträge.'); }
        WartungSystem::lebenszeichen($this->konfig);
        $eingang = fopen($this->statusPfad . '/auftraege/eingang.lock', 'r+');
        if (!$eingang || !flock($eingang, LOCK_EX)) { throw new RuntimeException('Auftragssperre fehlt.'); }
        try {
            // Nach Stromausfall niemals einen angefangenen Auftrag erneut ausführen.
            if (is_file($this->statusPfad . '/aktiv.json')) {
                if (is_file($this->statusPfad . '/status.json') && in_array(WartungDateien::json($this->statusPfad . '/status.json')['zustand'] ?? '', ['fehlgeschlagen', 'unterbrochen'], true)) { return; }
                $this->status = WartungDateien::json($this->statusPfad . '/aktiv.json');
                $this->melden('unterbrochen', 'Dienst wurde unterbrochen. Sicherungen und Gerätezustände prüfen; CLI-Wiederanlauf laut Wartungsanleitung erforderlich.');
                return;
            }
            $pfad = $this->statusPfad . '/auftraege/auftrag.json';
            if (!is_file($pfad)) { return; }
            $auftrag = WartungDateien::json($pfad);
            if (($auftrag['gueltig_bis'] ?? 0) < time()) { unlink($pfad); return; }
            if (!in_array($auftrag['aktion'] ?? '', ['pruefen', 'backup', 'update'], true)) { throw new RuntimeException('Unbekannter Auftrag.'); }
            $id = bin2hex(random_bytes(12));
            $this->status = ['id' => $id, 'aktion' => $auftrag['aktion'], 'mitarbeiter_id' => (int)($auftrag['mitarbeiter_id'] ?? 0), 'gestartet' => date(DATE_ATOM), 'protokoll' => []];
            WartungDateien::schreiben($this->statusPfad . '/aktiv.json', $this->status);
            unlink($pfad);
        } finally { flock($eingang, LOCK_UN); fclose($eingang); }
        $geraete = [];
        $pausiert = [];
        $veraendert = false;
        try {
            if (is_file($this->statusPfad . '/pause.json')) { throw new RuntimeException('Eine bestehende Wartungssperre muss zuerst vor Ort geklärt werden.'); }
            if ($auftrag['aktion'] === 'pruefen') {
                $this->melden('laeuft', 'Prüfe main und Datenbankänderungen.');
                foreach (['angebot.json', 'plan.json'] as $datei) {
                    if (is_file($this->statusPfad . '/' . $datei)) { unlink($this->statusPfad . '/' . $datei); }
                }
                $plan = (new WartungPaket($this->wurzel, $this->konfig))->pruefen();
                WartungDateien::schreiben($this->statusPfad . '/angebot.json', array_diff_key($plan, array_flip(['dateien', 'alle_migrationen'])));
                $this->melden('erfolgreich', $plan['verfuegbar'] ? 'Update gefunden. Datenbankänderungen sind unten aufgeführt.' : 'Der installierte Stand entspricht main.');
                return;
            }
            $plan = null;
            if ($auftrag['aktion'] === 'update') {
                $plan = WartungDateien::json($this->statusPfad . '/plan.json');
                if (empty($auftrag['commit']) || $auftrag['commit'] !== $plan['commit'] || !$plan['verfuegbar']) { throw new RuntimeException('Update zuerst prüfen und den angebotenen Stand auswählen.'); }
                // Installiert exakt das angezeigte Paket, auch wenn main inzwischen weiterläuft.
                if (hash_file('sha256', $this->statusPfad . '/paket.zip') !== $plan['paket_sha256']) { throw new RuntimeException('Geprüftes Paket wurde verändert.'); }
                $aktuell = WartungDateien::json($this->statusPfad . '/version.json');
                if ($aktuell['commit'] !== $plan['installiert']) { throw new RuntimeException('Installierter Stand hat sich seit der Prüfung geändert.'); }
                WartungPaket::bestandPruefen($this->wurzel, $aktuell['dateien']);
            }
            $this->melden('laeuft', 'Prüfe Backend und alle aktiven Terminals.');
            $geraete = $this->geraete();
            $lokal = $this->vorpruefung($plan !== null);
            foreach ($geraete as $terminal) {
                $probe = $this->remote($terminal, ['aktion' => 'probe', 'update' => $plan !== null]);
                if ($probe['rolle'] !== 'terminal' || (int)$probe['terminal_id'] !== (int)$terminal['id'] || ($plan !== null && $probe['commit'] !== $lokal['commit'])) {
                    throw new RuntimeException('Terminal ' . $terminal['id'] . ': Identität oder Programmstand passt nicht.');
                }
            }
            // Vor jedem Aufruf vormerken: Bei verlorener Antwort kann die Sperre schon gesetzt sein.
            foreach ($geraete as $terminal) {
                $pausiert[] = $terminal;
                $this->remote($terminal, ['aktion' => 'pause', 'id' => $id]);
            }
            $this->pause($id);
            // Gerätemenge unter der Sperre erneut prüfen (Kopplung kann vorher noch aktiv gewesen sein).
            if ($geraete !== $this->geraete()) { throw new RuntimeException('Die aktiven Terminals haben sich während der Vorbereitung geändert.'); }
            $this->melden('laeuft', 'Sichere Backenddateien, Datenbanken und Datenbankzugänge.');
            $this->backup($id);
            $this->status['backup'] = $this->konfig['backup_pfad'] . '/' . $id;
            foreach ($geraete as $terminal) {
                $this->melden('laeuft', 'Sichere Terminal ' . $terminal['id'] . ' einschließlich Offline-Buchungen.');
                if ($plan !== null) {
                    $this->kanal()->senden($terminal['db_benutzer'], $id, 'paket.zip', $this->statusPfad . '/paket.zip');
                    $planDatei = $this->statusPfad . '/plan.json';
                    $this->kanal()->senden($terminal['db_benutzer'], $id, 'plan.json', $planDatei);
                    $this->remote($terminal, ['aktion' => 'vorbereiten', 'id' => $id, 'sha256' => $plan['paket_sha256'], 'plan_sha256' => hash_file('sha256', $planDatei)]);
                }
                $beleg = $this->remote($terminal, ['aktion' => 'backup', 'id' => $id]);
                $ziel = $this->konfig['backup_pfad'] . '/' . $id . '/terminals/' . $terminal['id'];
                WartungDateien::ordner($ziel);
                foreach (['manifest.json', ...array_keys($beleg['dateien'])] as $datei) {
                    WartungDateien::relativ($datei);
                    $this->kanal()->empfangen($terminal['db_benutzer'], $id, $datei, $ziel . '/' . $datei);
                }
                WartungBackup::pruefen($ziel);
            }
            if ($plan !== null) {
                $this->vorbereiten($id, $plan['paket_sha256'], false);
                $this->melden('laeuft', 'Sicherungen geprüft. Migriere Datenbank und installiere Backend.');
                // Dauerhafter Riegel VOR dem ersten verändernden Befehl; DDL ist nicht transaktional.
                $veraendert = true;
                $this->status['installation_begonnen'] = true;
                WartungDateien::schreiben($this->statusPfad . '/aktiv.json', $this->status);
                $this->installieren($id, $plan, $this->statusPfad . '/bereit-' . $id);
                foreach ($geraete as $terminal) {
                    $this->melden('laeuft', 'Aktualisiere Terminal ' . $terminal['id'] . '.');
                    $this->remote($terminal, ['aktion' => 'installieren', 'id' => $id]);
                }
                $this->gesundheit();
                foreach ($geraete as $terminal) {
                    $probe = $this->remote($terminal, ['aktion' => 'gesundheit']);
                    if ($probe['commit'] !== $plan['commit']) { throw new RuntimeException('Terminalversion stimmt nach dem Update nicht.'); }
                }
            }
            $this->melden('laeuft', 'Alle Prüfungen erfolgreich. Gebe Buchungen wieder frei.');
            foreach ($geraete as $terminal) { $this->remote($terminal, ['aktion' => 'freigeben', 'id' => $id]); }
            $this->freigeben($id);
            $this->kanal()->aufraeumen();
            $this->melden('erfolgreich', $plan === null ? 'Backend und alle aktiven Terminals vollständig gesichert.' : 'Backend und alle aktiven Terminals sind aktualisiert.');
        } catch (Throwable $e) {
            WartungDateien::schreiben($this->statusPfad . '/privat/fehler-' . $id . '.json', ['klasse' => get_class($e), 'meldung' => $e->getMessage(), 'ort' => $e->getFile() . ':' . $e->getLine()], 0600);
            $offen = [];
            if (!$veraendert) {
                foreach ($pausiert as $terminal) {
                    try { $this->remote($terminal, ['aktion' => 'freigeben', 'id' => $id]); }
                    catch (Throwable) { $offen[] = (string)$terminal['id']; }
                }
                try { $this->freigeben($id); } catch (Throwable) { $offen[] = 'Backend'; }
            }
            $this->status['gesperrt_pruefen'] = $veraendert || $offen !== [];
            if ($veraendert) {
                // Erreichbare Terminals zeigen ebenfalls den Abbruch statt endloses Warten.
                foreach ($pausiert as $terminal) {
                    try { $this->remote($terminal, ['aktion' => 'abbruch', 'id' => $id]); }
                    catch (Throwable) { /* Die vorhandene Sperre bleibt auch ohne Antwort bestehen. */ }
                }
            }
            $fehler = $e instanceof PDOException ? 'Datenbankzugriff fehlgeschlagen; Rechte und Schemazustand prüfen.' : $e->getMessage();
            $this->melden('fehlgeschlagen', $fehler . ($veraendert ? ' Installation hat begonnen; Wartungssperren bleiben zur Fehlerbehebung bestehen.' : ($offen ? ' Freigabe unbestätigt: ' . implode(', ', $offen) : ' Keine Installation ausgeführt.')));
        } finally {
            // Fehler nach Beginn der Installation benötigen bewusst einen Administrator vor Ort.
            if (!($this->status['gesperrt_pruefen'] ?? false)) { unlink($this->statusPfad . '/aktiv.json'); }
        }
    }

    private function melden(string $zustand, string $text): void
    {
        WartungSystem::lebenszeichen($this->konfig);
        $schritt = $this->status['schritt'] ?? 1;
        if (str_starts_with($text, 'Sichere ')) { $schritt = 2; }
        if (str_starts_with($text, 'Sicherungen geprüft.')) { $schritt = 3; }
        if (str_starts_with($text, 'Aktualisiere Terminal')) { $schritt = 4; }
        if (str_starts_with($text, 'Alle Prüfungen') || $zustand === 'erfolgreich') { $schritt = 5; }
        $this->status['schritt'] = $schritt;
        $this->status['zustand'] = $zustand;
        $this->status['aktualisiert'] = date(DATE_ATOM);
        $this->status['protokoll'][] = ['zeit' => date(DATE_ATOM), 'text' => $text];
        WartungDateien::schreiben($this->statusPfad . '/status.json', $this->status);
        WartungDateien::schreiben($this->statusPfad . '/lauf-' . $this->status['id'] . '.json', $this->status);
    }

    private function istBackend(): bool { return ($this->app['app']['installation_typ'] ?? 'backend') === 'backend'; }

    private function kanal(): WartungKanal
    {
        return $this->kanal ??= new WartungKanal(WartungDateien::pdo($this->app['db']), $this->konfig, $this->istBackend());
    }

    private function geraete(): array
    {
        $pdo = WartungDateien::pdo($this->app['db']);
        $geraete = [];
        foreach ($pdo->query("SELECT t.id,t.name,t.db_benutzer, g.gesehen, UNIX_TIMESTAMP(g.gesehen) >= UNIX_TIMESTAMP()-60 AS bereit FROM terminal t LEFT JOIN wartung_geraet g ON g.terminal_id=t.id AND g.db_benutzer=t.db_benutzer WHERE t.aktiv=1 AND t.modus='terminal' ORDER BY t.id") as $terminal) {
            if (!(int)$terminal['bereit']) {
                throw new RuntimeException($terminal['name'] . ' ist noch nicht für Updates erreichbar. Das Gerät bitte einschalten und die Netzwerkverbindung prüfen.');
            }
            $geraete[] = ['id' => (int)$terminal['id'], 'name' => $terminal['name'], 'db_benutzer' => $terminal['db_benutzer']];
        }
        return $geraete;
    }

    private function remote(array $terminal, array $anfrage): array
    {
        return $this->kanal()->aufruf($terminal, $anfrage);
    }

    public function agent(): void
    {
        $this->exklusiv();
        if ($this->istBackend()) { throw new RuntimeException('Geräteagent ist nur für Terminals vorgesehen.'); }
        WartungSystem::lebenszeichen($this->konfig);
        $version = WartungDateien::json($this->statusPfad . '/version.json');
        $this->kanal()->agent((int)$this->app['terminal']['id'], $version['commit'], fn(array $anfrage): array => $this->geraeteAuftrag($anfrage));
    }

    public function geraeteAuftrag(array $anfrage): array
    {
        $this->exklusiv();
        if ($this->istBackend()) { throw new RuntimeException('Geräteaufträge sind nur auf Terminals erlaubt.'); }
        $id = $anfrage['id'] ?? '';
        if (!in_array($anfrage['aktion'] ?? '', ['probe', 'gesundheit'], true)) { $this->idPruefen($id); }
        return match ($anfrage['aktion'] ?? '') {
            'probe' => $this->vorpruefung(($anfrage['update'] ?? false) === true),
            'pause' => $this->pause($id),
            'backup' => $this->backup($id),
            'vorbereiten' => $this->vorbereiten($id, $anfrage['sha256'] ?? ''),
            'installieren' => $this->installieren($id, WartungDateien::json($this->statusPfad . '/empfang/' . $id . '.json'), $this->statusPfad . '/bereit-' . $id),
            'gesundheit' => $this->gesundheit(),
            'freigeben' => $this->freigeben($id),
            'abbruch' => $this->abbruch($id),
            default => throw new RuntimeException('Unbekannter Geräteauftrag.'),
        };
    }

    private function idPruefen(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) { throw new RuntimeException('Ungültige Auftragskennung.'); }
    }

    private function pause(string $id): array
    {
        $this->idPruefen($id);
        $datei = $this->statusPfad . '/pause.json';
        if (is_file($datei) && WartungDateien::json($datei)['id'] !== $id) { throw new RuntimeException('Anderer Wartungsauftrag hält das Gerät an.'); }
        WartungDateien::schreiben($datei, ['id' => $id, 'seit' => date(DATE_ATOM)]);
        if (!$this->istBackend()) {
            WartungDateien::schreiben($this->statusPfad . '/status.json', ['id' => $id, 'zustand' => 'laeuft']);
        }
        $sperre = fopen($this->statusPfad . '/anfragen.lock', 'r+');
        if (!$sperre) { throw new RuntimeException('Anfragensperre fehlt.'); }
        $ende = time() + 120;
        while (!flock($sperre, LOCK_EX | LOCK_NB)) {
            if (time() >= $ende) { fclose($sperre); throw new RuntimeException('Laufende Buchungen werden nicht rechtzeitig beendet.'); }
            usleep(100000);
        }
        fclose($sperre);
        return ['ok' => true];
    }

    private function eigenePause(string $id): void
    {
        $this->idPruefen($id);
        if (!is_file($this->statusPfad . '/pause.json') || WartungDateien::json($this->statusPfad . '/pause.json')['id'] !== $id) { throw new RuntimeException('Passende Wartungssperre fehlt.'); }
    }

    private function freigeben(string $id): array
    {
        if (!is_file($this->statusPfad . '/pause.json')) { return ['ok' => true]; }
        $this->eigenePause($id);
        if (!unlink($this->statusPfad . '/pause.json')) { throw new RuntimeException('Wartungssperre nicht lösbar.'); }
        return ['ok' => true];
    }

    private function backup(string $id): array
    {
        $this->eigenePause($id);
        return ['ok' => true] + (new WartungBackup($this->wurzel, $this->konfig, $this->app))->erstellen($this->konfig['backup_pfad'] . '/' . $id);
    }

    private function abbruch(string $id): array
    {
        $this->eigenePause($id);
        WartungDateien::schreiben($this->statusPfad . '/status.json', ['id' => $id, 'zustand' => 'fehlgeschlagen', 'gesperrt_pruefen' => true]);
        return ['ok' => true];
    }

    private function vorpruefung(bool $update = true): array
    {
        $version = WartungDateien::json($this->statusPfad . '/version.json');
        if ($update) {
            WartungPaket::bestandPruefen($this->wurzel, $version['dateien']);
            $this->datenbankPruefen($version['migrationen']);
            if (empty($this->konfig['neustart_befehl']) || !is_array($this->konfig['neustart_befehl'])) { throw new RuntimeException('Befehl zum Neuladen des Webservers fehlt.'); }
        }
        $bedarf = WartungPlattform::bytes($this->wurzel) * 3;
        foreach ((new WartungBackup($this->wurzel, $this->konfig, $this->app))->datenbanken() as $db) {
            $pdo = WartungDateien::pdo($db);
            $bedarf += (int)$pdo->query('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() * 3;
        }
        $bedarf += (int)($this->konfig['reserve_bytes'] ?? 536870912);
        foreach ([$this->wurzel, $this->statusPfad, $this->konfig['backup_pfad']] as $pfad) {
            $frei = disk_free_space($pfad);
            if ($frei === false || $frei < $bedarf || !is_writable($pfad)) { throw new RuntimeException('Zu wenig freier Speicher oder Schreibrechte: ' . $pfad); }
        }
        return ['ok' => true, 'rolle' => $this->istBackend() ? 'backend' : 'terminal', 'terminal_id' => $this->app['terminal']['id'] ?? null, 'commit' => $version['commit']];
    }

    private function vorbereiten(string $id, string $sha, bool $empfangen = true): array
    {
        $this->eigenePause($id);
        $paket = $this->statusPfad . ($empfangen ? '/empfang/' . $id . '.zip' : '/paket.zip');
        $plan = WartungDateien::json($this->statusPfad . ($empfangen ? '/empfang/' . $id . '.json' : '/plan.json'));
        if ($sha !== $plan['paket_sha256'] || hash_file('sha256', $paket) !== $sha) { throw new RuntimeException('Prüfsumme des empfangenen Pakets stimmt nicht.'); }
        $alt = WartungDateien::json($this->statusPfad . '/version.json');
        if ($plan['installiert'] !== $alt['commit']) { throw new RuntimeException('Ausgangsversion passt nicht zum Paket.'); }
        WartungPaket::bestandPruefen($this->wurzel, $alt['dateien']);
        foreach ($plan['dateien'] as $pfad => $info) {
            WartungPaket::zielPruefen($this->wurzel, $pfad);
            if (!isset($alt['dateien'][$pfad]) && file_exists($this->wurzel . '/' . $pfad)) { throw new RuntimeException('Neue Paketdatei kollidiert mit lokaler Datei: ' . $pfad); }
        }
        $bereit = $this->statusPfad . '/bereit-' . $id;
        WartungPaket::auspacken($paket, $bereit, $plan['dateien']);
        foreach (array_keys($plan['dateien']) as $pfad) {
            if (str_ends_with($pfad, '.php')) { WartungDateien::prozess([PHP_BINARY, '-l', $bereit . '/' . $pfad]); }
        }
        return ['ok' => true];
    }

    private function installieren(string $id, array $plan, string $bereit): array
    {
        $this->eigenePause($id);
        WartungBackup::pruefen($this->konfig['backup_pfad'] . '/' . $id);
        if (is_file($this->statusPfad . '/installation-' . $id . '.json')) { throw new RuntimeException('Installation wurde bereits begonnen; keine automatische Wiederholung von SQL.'); }
        WartungDateien::schreiben($this->statusPfad . '/installation-' . $id . '.json', ['commit' => $plan['commit'], 'begonnen' => date(DATE_ATOM)]);
        WartungPaket::bestandPruefen($bereit, $plan['dateien']);
        $alt = WartungDateien::json($this->statusPfad . '/version.json');
        WartungPlattform::dateisperrenPruefen($this->wurzel, array_keys($plan['dateien'] + $alt['dateien']));
        $dbs = (new WartungBackup($this->wurzel, $this->konfig, $this->app))->datenbanken();
        foreach ($plan['migrationen'] as $migration) {
            if (!isset($dbs[$migration['ziel']])) { continue; }
            try {
                WartungDateien::mysql($dbs[$migration['ziel']], static function ($option, $name) use ($bereit, $migration): void {
                    WartungDateien::prozess(['mariadb', $option, '--default-character-set=utf8mb4', $name], null, $bereit . '/' . $migration['datei']);
                });
            } catch (Throwable $e) { throw new RuntimeException('Migration ' . $migration['datei'] . ' fehlgeschlagen: ' . $e->getMessage(), 0, $e); }
            $this->datenbankPruefen([$migration]);
        }
        $alt = WartungDateien::json($this->statusPfad . '/version.json');
        foreach ($plan['dateien'] as $pfad => $info) {
            WartungPaket::zielPruefen($this->wurzel, $pfad);
            WartungDateien::ordner(dirname($this->wurzel . '/' . $pfad), 0755);
            $tmp = $this->wurzel . '/' . $pfad . '.wartung-' . $id;
            if (file_exists($tmp) || is_link($tmp)) { throw new RuntimeException('Temporärer Installationspfad ist belegt.'); }
            if (!copy($bereit . '/' . $pfad, $tmp) || !chmod($tmp, $info['ausfuehrbar'] ? 0755 : 0644)
                || !rename($tmp, $this->wurzel . '/' . $pfad)) { throw new RuntimeException('Installation der Datei fehlgeschlagen: ' . $pfad); }
        }
        foreach (array_diff_key($alt['dateien'], $plan['dateien']) as $pfad => $info) {
            WartungPaket::zielPruefen($this->wurzel, $pfad);
            if (!unlink($this->wurzel . '/' . $pfad)) { throw new RuntimeException('Entfernte Programmdatei nicht löschbar: ' . $pfad); }
        }
        WartungPaket::bestandPruefen($this->wurzel, $plan['dateien']);
        $version = ['commit' => $plan['commit'], 'dateien' => $plan['dateien'], 'migrationen' => $plan['alle_migrationen']];
        WartungDateien::schreiben($this->statusPfad . '/version.json', $version);
        // Neuer Prozess lädt den neuen Code. FPM/Apache anschließend neu laden, damit kein alter Opcode weiterläuft.
        WartungDateien::prozess([PHP_BINARY, $this->wurzel . '/scripts/wartung.php', 'gesundheit']);
        $neustart = $this->konfig['neustart_befehl'] ?? [];
        if (!is_array($neustart) || $neustart === []) { throw new RuntimeException('Befehl zum Neuladen des Webservers fehlt.'); }
        WartungDateien::prozess($neustart);
        return ['ok' => true, 'commit' => $plan['commit']];
    }

    private function datenbankPruefen(array $migrationen): void
    {
        $dbs = (new WartungBackup($this->wurzel, $this->konfig, $this->app))->datenbanken();
        foreach ($dbs as $rolle => $db) {
            $pdo = WartungDateien::pdo($db);
            if ($rolle === 'haupt') {
                $pdo->query('SELECT installation FROM portal_verbindung LIMIT 0');
                $pdo->query('SELECT meta_quell_id FROM db_injektionsqueue LIMIT 0');
                $fk = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND constraint_name IN ('fk_zeitbuchung_mitarbeiter', 'fk_auftragszeit_mitarbeiter')")->fetchColumn();
                if ($fk !== 2) { throw new RuntimeException('Bestandsmigrationen 09/11 sind nicht vollständig abgeschlossen.'); }
            } else {
                $pdo->query('SELECT meta_mitarbeiter_id, meta_terminal_id, meta_quell_id FROM db_injektionsqueue LIMIT 0');
                $pdo->query('SELECT mitarbeiter_id, rfid_code FROM mitarbeiter_spiegel LIMIT 0');
            }
            foreach ($migrationen as $migration) {
                if ($migration['ziel'] === $rolle && (int)$pdo->query($migration['pruefung'])->fetchColumn() !== 1) { throw new RuntimeException('Datenbankprüfung fehlgeschlagen: ' . $migration['datei']); }
            }
        }
    }

    public function gesundheit(): array
    {
        $version = WartungDateien::json($this->statusPfad . '/version.json');
        WartungPaket::bestandPruefen($this->wurzel, $version['dateien']);
        $this->datenbankPruefen($version['migrationen']);
        // Terminal muss nach zentralen Migrationen weiterhin mit seinen eingeschränkten Rechten lesen können.
        $pdo = WartungDateien::pdo($this->app['db']);
        $pdo->query('SELECT id, personalnummer, rfid_code FROM mitarbeiter LIMIT 0');
        $pdo->query('SELECT id FROM zeitbuchung LIMIT 0');
        return ['ok' => true, 'commit' => $version['commit']];
    }
}
