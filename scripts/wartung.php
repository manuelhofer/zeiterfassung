<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/Autoloader.php';
umask(0027);
try {
    $konfig = WartungSperre::konfig() ?? throw new RuntimeException('config/wartung.local.php fehlt.');
    $dienst = new WartungDienst(dirname(__DIR__), $konfig);
    $aktion = $argv[1] ?? '';
    $antwort = match ($aktion) {
        'initialisieren' => $dienst->initialisieren(),
        'gesundheit' => $dienst->gesundheit(),
        'geraet' => $dienst->geraeteAuftrag(json_decode(stream_get_contents(STDIN, 16384), true, 512, JSON_THROW_ON_ERROR)),
        'verarbeiten' => (static function () use ($dienst): array { $dienst->verarbeiten(); return ['ok' => true]; })(),
        default => throw new RuntimeException('Aufruf: php scripts/wartung.php initialisieren|verarbeiten|gesundheit|geraet'),
    };
    echo json_encode($antwort, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    // Keine PDO-Fehlertexte mit Datenbank-/Serverinformationen in Geräteantworten.
    $meldung = $e instanceof PDOException ? 'Datenbankzugriff fehlgeschlagen; Konfiguration, Rechte und Schemazustand prüfen.' : $e->getMessage();
    echo json_encode(['ok' => false, 'fehler' => $meldung], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    exit(1);
}
