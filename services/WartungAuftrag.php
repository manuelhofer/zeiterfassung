<?php
declare(strict_types=1);

/** Kleine Schnittstelle zwischen berechtigter Weboberfläche und lokalem CLI-Dienst. */
final class WartungAuftrag
{
    public static function lesen(string $name): ?array
    {
        $konfig = WartungSperre::konfig();
        if ($konfig === null || !in_array($name, ['status', 'angebot', 'version'], true)) { return null; }
        $pfad = $konfig['status_pfad'] . '/' . $name . '.json';
        return is_file($pfad) ? WartungDateien::json($pfad) : null;
    }

    public static function einreichen(string $aktion, string $commit, int $mitarbeiterId): void
    {
        if (!in_array($aktion, ['pruefen', 'backup', 'update'], true)) { throw new RuntimeException('Unbekannte Aktion.'); }
        $konfig = WartungSperre::konfig() ?? throw new RuntimeException('Der Wartungsdienst ist noch nicht eingerichtet.');
        $basis = $konfig['status_pfad'];
        $bereitschaft = WartungAnzeige::bereitschaft($konfig);
        if (!$bereitschaft['bereit']) { throw new RuntimeException($bereitschaft['text']); }
        if (!is_file($basis . '/version.json')) { throw new RuntimeException('Der Wartungsdienst wurde noch nicht initialisiert.'); }
        $lock = fopen($basis . '/auftraege/eingang.lock', 'r+');
        if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('Der Wartungsdienst ist nicht erreichbar.'); }
        try {
            $eingang = $basis . '/auftraege/auftrag.json';
            if (is_file($eingang) && (WartungDateien::json($eingang)['gueltig_bis'] ?? 0) < time()) { unlink($eingang); }
            if (is_file($basis . '/auftraege/auftrag.json') || is_file($basis . '/aktiv.json') || is_file($basis . '/pause.json')) {
                throw new RuntimeException('Ein Auftrag wartet, läuft oder benötigt Fehlerbehebung.');
            }
            if ($aktion === 'update') {
                $angebot = self::lesen('angebot');
                if (!($angebot['verfuegbar'] ?? false) || !preg_match('/^[a-f0-9]{40}$/', $commit) || $commit !== $angebot['commit']) {
                    throw new RuntimeException('Bitte zuerst nach Updates suchen.');
                }
            }
            WartungDateien::schreiben($basis . '/auftraege/auftrag.json', [
                'aktion' => $aktion, 'commit' => $aktion === 'update' ? $commit : '',
                'mitarbeiter_id' => $mitarbeiterId, 'angefordert' => date(DATE_ATOM), 'gueltig_bis' => time() + 60,
            ], 0660);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
