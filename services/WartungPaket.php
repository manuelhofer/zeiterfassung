<?php
declare(strict_types=1);

/** Ein geprüftes main-Commit wird als identisches Paket an alle Geräte verteilt. */
final class WartungPaket
{
    public function __construct(private string $wurzel, private array $konfig) {}

    public function pruefen(): array
    {
        // Das origin der Installation. Öffentliche Quellen brauchen keine Anmeldung.
        $spiegel = $this->wurzel;
        WartungDateien::prozess(['git', '-C', $spiegel, 'fetch', '--no-tags', 'origin', '+refs/heads/main:refs/remotes/origin/main'], frist: 60, umgebung: ['GIT_TERMINAL_PROMPT' => '0', 'GCM_INTERACTIVE' => 'never']);
        $commit = trim(WartungDateien::prozess(['git', '-C', $spiegel, 'rev-parse', 'refs/remotes/origin/main']));
        $alt = WartungDateien::json($this->konfig['status_pfad'] . '/version.json');
        self::bestandPruefen($this->wurzel, $alt['dateien']);
        if ($commit === $alt['commit']) {
            return ['commit' => $commit, 'installiert' => $commit, 'verfuegbar' => false, 'migrationen' => []];
        }
        // Kein Zurücksetzen oder Wechsel auf eine andere Historie durch einen Web-Klick.
        WartungDateien::prozess(['git', '-C', $spiegel, 'merge-base', '--is-ancestor', $alt['commit'], $commit]);
        $zip = $this->konfig['status_pfad'] . '/paket.zip';
        WartungDateien::prozess(['git', '-C', $spiegel, 'archive', '--format=zip', '--output=' . $zip, $commit]);
        $inhalt = self::inventar($zip);
        $archiv = new ZipArchive();
        $archiv->open($zip);
        $manifestText = $archiv->getFromName('updates/migrationen.json');
        $archiv->close();
        if ($manifestText === false) { throw new RuntimeException('Dem Update fehlt der Migrationsvertrag.'); }
        $manifest = json_decode($manifestText, true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['protokoll'] ?? null) !== 1) { throw new RuntimeException('Unbekanntes Updateprotokoll.'); }
        $migrationen = $manifest['migrationen'] ?? [];
        $bekannt = $alt['migrationen'];
        foreach ($bekannt as $i => $migration) {
            if (($migrationen[$i] ?? null) !== $migration || ($inhalt[$migration['datei']]['sha256'] ?? '') !== ($alt['dateien'][$migration['datei']]['sha256'] ?? '')) {
                throw new RuntimeException('Bereits installierte Migration wurde geändert oder entfernt.');
            }
        }
        $neu = array_slice($migrationen, count($bekannt));
        $namen = [];
        foreach ($migrationen as $migration) {
            $datei = $migration['datei'] ?? '';
            WartungDateien::relativ($datei);
            if (!preg_match('~^sql/[0-9]+_migration_[a-z0-9_]+\.sql$~', $datei)
                || !isset($inhalt[$datei]) || isset($namen[$datei])
                || !in_array($migration['ziel'] ?? '', ['haupt', 'offline'], true)
                || !preg_match('/^SELECT\s/i', $migration['pruefung'] ?? '')) {
                throw new RuntimeException('Ungültiger Migrationsvertrag.');
            }
            $namen[$datei] = true;
        }
        $sqlAenderungen = [];
        foreach (array_unique([...array_keys($alt['dateien']), ...array_keys($inhalt)]) as $pfad) {
            if (!str_ends_with($pfad, '.sql') || ($alt['dateien'][$pfad] ?? null) === ($inhalt[$pfad] ?? null)) { continue; }
            $sqlAenderungen[] = $pfad;
            if (in_array($pfad, ['sql/01_initial_schema.sql', 'sql/offline_db_schema.sql'], true)) {
                $ziel = str_contains($pfad, 'offline') ? 'offline' : 'haupt';
                if (!array_filter($neu, static fn($m) => $m['ziel'] === $ziel)) { throw new RuntimeException('Schemaänderung ohne passende Migration: ' . $pfad); }
            } elseif (!in_array($pfad, array_column($neu, 'datei'), true) || isset($alt['dateien'][$pfad])) {
                throw new RuntimeException('SQL-Änderung ist nicht als neue Migration freigegeben: ' . $pfad);
            }
        }
        $plan = ['commit' => $commit, 'installiert' => $alt['commit'], 'verfuegbar' => true,
            'paket_sha256' => hash_file('sha256', $zip), 'dateien' => $inhalt,
            'migrationen' => $neu, 'alle_migrationen' => $migrationen, 'sql_aenderungen' => $sqlAenderungen,
            'geprueft' => date(DATE_ATOM)];
        WartungDateien::schreiben($this->konfig['status_pfad'] . '/plan.json', $plan);
        return $plan;
    }

    public static function inventar(string $pfad): array
    {
        $zip = new ZipArchive();
        if ($zip->open($pfad) !== true) { throw new RuntimeException('Paket ist kein lesbares ZIP.'); }
        $dateien = [];
        $namen = [];
        $bytes = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $info = $zip->statIndex($i);
                $name = $info['name'];
                WartungDateien::relativ(rtrim($name, '/'));
                $zip->getExternalAttributesIndex($i, $system, $attribute);
                $modus = ($attribute >> 16) & 0170000;
                if ($modus !== 0 && !in_array($modus, [0100000, 0040000], true)) { throw new RuntimeException('Links/Sonderdateien sind in Updates nicht erlaubt.'); }
                if (str_ends_with($name, '/')) { continue; }
                $kennung = WartungPlattform::windows() ? strtolower($name) : $name;
                if (isset($namen[$kennung])) { throw new RuntimeException('Mehrdeutiger Paketpfad.'); }
                $namen[$kennung] = true;
                if (WartungDateien::geschuetzt($name) || isset($dateien[$name])) { throw new RuntimeException('Geschützter oder doppelter Paketpfad: ' . $name); }
                $bytes += $info['size'];
                if ($bytes > 1024 * 1024 * 1024 || $info['size'] > 64 * 1024 * 1024) { throw new RuntimeException('Paket überschreitet die Größenbegrenzung.'); }
                $dateien[$name] = ['sha256' => hash('sha256', $zip->getFromIndex($i)), 'ausfuehrbar' => (($attribute >> 16) & 0111) !== 0];
            }
        } finally { $zip->close(); }
        foreach (['core/Start.php', 'services/WartungDienst.php', 'scripts/wartung.php', 'public/index.php', 'public/terminal.php'] as $pflicht) {
            if (!isset($dateien[$pflicht])) { throw new RuntimeException('Unvollständiges Programmpaket: ' . $pflicht); }
        }
        ksort($dateien);
        return $dateien;
    }

    public static function bestandPruefen(string $wurzel, array $dateien): void
    {
        foreach ($dateien as $pfad => $info) {
            self::zielPruefen($wurzel, $pfad);
            if (!is_file($wurzel . '/' . $pfad) || hash_file('sha256', $wurzel . '/' . $pfad) !== $info['sha256']) {
                throw new RuntimeException('Lokale Änderung oder fehlende Programmdatei: ' . $pfad);
            }
        }
    }

    public static function zielPruefen(string $wurzel, string $pfad): void
    {
        WartungDateien::relativ($pfad);
        $ziel = $wurzel;
        foreach (explode('/', $pfad) as $teil) {
            $ziel .= '/' . $teil;
            if (is_link($ziel)) { throw new RuntimeException('Link im Installationspfad: ' . $pfad); }
        }
    }

    public static function auspacken(string $paket, string $ziel, array $erwartet): void
    {
        if (self::inventar($paket) !== $erwartet) { throw new RuntimeException('Paketinhalt stimmt nicht mit dem geprüften Stand überein.'); }
        WartungDateien::ordner($ziel);
        $zip = new ZipArchive();
        $zip->open($paket);
        try {
            foreach ($erwartet as $pfad => $info) {
                self::zielPruefen($ziel, $pfad);
                WartungDateien::ordner(dirname($ziel . '/' . $pfad), 0755);
                if (file_put_contents($ziel . '/' . $pfad, $zip->getFromName($pfad)) === false) { throw new RuntimeException('Paket nicht schreibbar.'); }
                chmod($ziel . '/' . $pfad, $info['ausfuehrbar'] ? 0755 : 0644);
            }
        } finally { $zip->close(); }
        self::bestandPruefen($ziel, $erwartet);
    }
}
