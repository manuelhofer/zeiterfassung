<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/Autoloader.php';
umask(0027);
try {
    $wurzel = dirname(__DIR__);
    $konfig = WartungSystem::konfig($wurzel, true) ?? throw new RuntimeException('Die Installation hat den Wartungsdienst noch nicht bereitgestellt.');
    $app = WartungSystem::anwendung($wurzel, $konfig);
    $aktion = $argv[1] ?? '';
    if ($aktion === 'dienst') {
        WartungSystem::lebenszeichen($konfig, 'startet');
        if (($konfig['rolle'] ?? '') === 'terminal' && !is_file($wurzel . '/config/config.local.php')) {
            echo json_encode(['ok' => true, 'wartet_auf_normale_kopplung' => true]) . "\n";
            exit;
        }
        WartungSystem::vorbereiten($wurzel, $konfig, $app);
        $aktion = ($app['app']['installation_typ'] ?? 'backend') === 'backend' ? 'verarbeiten' : 'agent';
    }
    $dienst = new WartungDienst($wurzel, $konfig, $app);
    $antwort = match ($aktion) {
        'initialisieren' => $dienst->initialisieren(),
        'gesundheit' => $dienst->gesundheit(),
        'agent' => (static function () use ($dienst): array { $dienst->agent(); return ['ok' => true]; })(),
        'geraet' => $dienst->geraeteAuftrag(json_decode(stream_get_contents(STDIN, 16384), true, 512, JSON_THROW_ON_ERROR)),
        'verarbeiten' => (static function () use ($dienst): array { $dienst->verarbeiten(); return ['ok' => true]; })(),
        default => throw new RuntimeException('Aufruf: php scripts/wartung.php dienst|gesundheit|initialisieren|verarbeiten|agent|geraet'),
    };
    echo json_encode($antwort, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    if (isset($konfig)) { WartungSystem::lebenszeichen($konfig, 'fehler', 'Der Wartungsdienst konnte nicht starten. Bitte den Betrieb der Installation prüfen.'); }
    // Keine PDO-Fehlertexte mit Datenbank-/Serverinformationen in Geräteantworten.
    $meldung = $e instanceof PDOException ? 'Datenbankzugriff fehlgeschlagen; Konfiguration, Rechte und Schemazustand prüfen.' : $e->getMessage();
    echo json_encode(['ok' => false, 'fehler' => $meldung], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    exit(1);
}
