<?php
declare(strict_types=1);

/** ZIP-Sicherung mit expliziter Pfadzuordnung statt Windows-Laufwerksnamen im Archiv. */
final class WartungArchiv
{
    public static function zip(string $datei, array $pfade): array
    {
        $zip = new ZipArchive();
        if ($zip->open($datei, ZipArchive::CREATE | ZipArchive::EXCL) !== true) { throw new RuntimeException('Sicherungsarchiv nicht anlegbar.'); }
        $zuordnung = [];
        try {
            foreach ($pfade as $i => $pfad) {
                $prefix = 'pfad-' . $i;
                $zuordnung[$prefix] = $pfad;
                if (is_link($pfad)) { throw new RuntimeException('Windows-Sicherung unterstützt keine Dateiverknüpfungen.'); }
                if (is_file($pfad)) {
                    if (!$zip->addFile($pfad, $prefix . '/' . basename($pfad))) { throw new RuntimeException('Sicherungsdatei nicht lesbar.'); }
                    continue;
                }
                if (!$zip->addEmptyDir($prefix)) { throw new RuntimeException('Sicherungsordner nicht anlegbar.'); }
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pfad, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $eintrag) {
                    if ($eintrag->isLink()) { throw new RuntimeException('Windows-Sicherung unterstützt keine Dateiverknüpfungen.'); }
                    $relativ = substr(WartungPlattform::pfad($eintrag->getPathname()), strlen(rtrim(WartungPlattform::pfad($pfad), '/')) + 1);
                    $ok = $eintrag->isDir() ? $zip->addEmptyDir($prefix . '/' . $relativ) : $zip->addFile($eintrag->getPathname(), $prefix . '/' . $relativ);
                    if (!$ok) { throw new RuntimeException('Sicherungsdatei nicht lesbar: ' . $relativ); }
                }
            }
        } finally { if (!$zip->close()) { throw new RuntimeException('Sicherungsarchiv nicht vollständig schreibbar.'); } }
        self::pruefen($datei);
        return $zuordnung;
    }

    public static function pruefen(string $datei): void
    {
        $zip = new ZipArchive();
        if ($zip->open($datei, ZipArchive::CHECKCONS) !== true) { throw new RuntimeException('Sicherungsarchiv ist nicht lesbar.'); }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $info = $zip->statIndex($i);
                if (str_ends_with($info['name'], '/')) { continue; }
                $stream = $zip->getStream($info['name']);
                if ($stream === false) { throw new RuntimeException('Archivdatei ist nicht lesbar.'); }
                try {
                    $hash = hash_init('crc32b');
                    $bytes = hash_update_stream($hash, $stream);
                    $crc = hash_final($hash);
                } finally { fclose($stream); }
                if ($bytes !== $info['size'] || $crc !== sprintf('%08x', $info['crc'])) { throw new RuntimeException('Archivdatei ist unvollständig.'); }
            }
        } finally { $zip->close(); }
    }
}
