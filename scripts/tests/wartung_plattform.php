<?php
declare(strict_types=1);
/** Portable Prüfung: keine Anwendungskonfiguration, Datenbank oder Systemdienste. */
require dirname(__DIR__, 2) . '/core/Autoloader.php';
set_error_handler(static function (int $nr, string $text): never { throw new ErrorException($text, 0, $nr); });
$lab = sys_get_temp_dir() . '/zeit-plattform-' . bin2hex(random_bytes(6));
WartungDateien::ordner($lab);
$tests = 0;
function pruefung(string $name, bool $ok): void
{
    global $tests;
    if (!$ok) { throw new RuntimeException('FAIL ' . $name); }
    $tests++;
    echo 'PASS ' . $name . "\n";
}
function abgelehnt(callable $aktion): bool
{
    try { $aktion(); } catch (RuntimeException $e) { return true; }
    return false;
}
try {
    pruefung('absolute Linux-/Windows-Pfade', WartungPlattform::absolut('/tmp/test') && WartungPlattform::absolut('C:/xampp/test')
        && WartungPlattform::absolut('C:\\xampp\\test') && !WartungPlattform::absolut('relativ'));
    pruefung('Pfadgrenzen statt gleicher Namensanfang', WartungPlattform::innerhalb('C:/xampp/app/datei', 'C:/xampp/app')
        && !WartungPlattform::innerhalb('C:/xampp/application', 'C:/xampp/app'));
    pruefung('Traversal im Paket abgewiesen', abgelehnt(fn() => WartungDateien::relativ('../datei.php')));
    $text = 'Leerzeichen & "Zitat" %PATH% ä';
    $kind = $lab . '/kind mit Leerzeichen.php';
    file_put_contents($kind, '<?php echo json_encode([$argv[1],getenv("ZEIT_PLATTFORM"),stream_get_contents(STDIN)]);');
    $eingabe = $lab . '/eingabe';
    file_put_contents($eingabe, 'Testeingabe');
    $resultat = json_decode(WartungDateien::prozess([PHP_BINARY, '-n', $kind, $text], eingabe: $eingabe,
        umgebung: ['ZEIT_PLATTFORM' => $text]), true, 512, JSON_THROW_ON_ERROR);
    pruefung('Argumente und Umgebung ohne Shellauswertung, Dateieingabe', $resultat === [$text,$text,'Testeingabe']);
    pruefung('Plattformspezifische leere Standardeingabe', WartungDateien::prozess([PHP_BINARY,'-n','-r','echo strlen(stream_get_contents(STDIN));']) === '0');
    pruefung('Prozessfehler bleibt erkennbar', abgelehnt(fn() => WartungDateien::prozess([PHP_BINARY,'-n','-r','exit(7);'])));
    WartungDateien::schreiben($lab . '/zustand.json', ['stand'=>1]);
    WartungDateien::schreiben($lab . '/zustand.json', ['stand'=>2]);
    pruefung('Atomarer JSON-Ersatz vorhandener Datei', WartungDateien::json($lab . '/zustand.json')['stand'] === 2);
    $app = $lab . '/app mit Leerzeichen';
    WartungDateien::ordner($app . '/config');
    WartungDateien::ordner($app . '/.git');
    WartungDateien::ordner($app . '/leer');
    file_put_contents($app . '/config/config.local.php', 'synthetische Konfiguration');
    file_put_contents($app . '/.git/HEAD', 'synthetischer Git-Stand');
    $pfade = WartungArchiv::zip($lab . '/dateien.zip', [$app, $lab . '/zustand.json']);
    $zip = new ZipArchive(); $zip->open($lab . '/dateien.zip');
    pruefung('ZIP erhaelt Konfiguration, Git und leere Ordner', $zip->getFromName('pfad-0/config/config.local.php') === 'synthetische Konfiguration'
        && $zip->getFromName('pfad-0/.git/HEAD') === 'synthetischer Git-Stand' && $zip->locateName('pfad-0/leer/') !== false);
    $restore = $lab . '/restore'; WartungDateien::ordner($restore); $zip->extractTo($restore); $zip->close();
    pruefung('ZIP-Restore und Originalpfadzuordnung', $pfade['pfad-0'] === $app
        && file_get_contents($restore . '/pfad-0/config/config.local.php') === 'synthetische Konfiguration');
    file_put_contents($lab . '/kaputt.zip', 'kein Archiv');
    pruefung('Beschaedigtes Archiv wird abgewiesen', abgelehnt(fn() => WartungArchiv::pruefen($lab . '/kaputt.zip')));
    $fixture = $lab . '/paket.zip';
    $zip = new ZipArchive(); $zip->open($fixture, ZipArchive::CREATE);
    foreach (['core/Start.php','services/WartungDienst.php','scripts/wartung.php','public/index.php','public/terminal.php'] as $p) { $zip->addFromString($p, 'fixture'); }
    $zip->close();
    $inventar = WartungPaket::inventar($fixture);
    WartungPaket::auspacken($fixture, $lab . '/bereit', $inventar);
    pruefung('Identisches Paketinventar und Entpacken', hash_file('sha256',$lab . '/bereit/public/index.php') === $inventar['public/index.php']['sha256']);
    if (WartungPlattform::windows()) {
        pruefung('Windows-Pfadvergleich ohne Gross-/Kleinschreibungsfehler', WartungPlattform::innerhalb('C:\\XAMPP\\App\\file', 'c:/xampp/app'));
        pruefung('Lokale Konfiguration bleibt auch mit anderer Schreibweise geschuetzt', WartungDateien::geschuetzt('config/CONFIG.LOCAL.PHP') && WartungDateien::geschuetzt('public/Uploads/lokal.txt'));
        foreach (['datei.php:stream','NUL.txt','ordner/datei.','ordner/datei ','C:/fremd','.GIT/config'] as $p) {
            pruefung('Windows-Sonderpfad abgewiesen: ' . $p, abgelehnt(fn() => WartungDateien::relativ($p)));
        }
        $zip = new ZipArchive(); $zip->open($fixture); $zip->addFromString('public/INDEX.php','kollision'); $zip->close();
        pruefung('Windows-Paketkollision abgewiesen', abgelehnt(fn() => WartungPaket::inventar($fixture)));
    }
    echo "Ergebnis: $tests Pruefungen erfolgreich (" . PHP_OS_FAMILY . ").\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($lab, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $eintrag) { $eintrag->isDir() ? rmdir($eintrag->getPathname()) : unlink($eintrag->getPathname()); }
    rmdir($lab);
}
