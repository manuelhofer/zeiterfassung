<?php
declare(strict_types=1);

/** Benutzersprache und Verfügbarkeit, auch während die Hauptdatenbank gesperrt ist. */
final class WartungAnzeige
{
    public static function bereitschaft(?array $konfig): array
    {
        if ($konfig === null) { return ['bereit' => false, 'text' => 'Die Installation stellt Backup und Updates noch nicht bereit. Nach der vollständigen Programminstallation stehen die Knöpfe automatisch zur Verfügung.']; }
        $pfad = $konfig['status_pfad'] . '/dienst.json';
        $dienst = is_file($pfad) ? WartungDateien::json($pfad) : [];
        if (($dienst['zustand'] ?? '') === 'fehler') { return ['bereit' => false, 'text' => 'Der Wartungsdienst ist derzeit nicht verfügbar. Es wurde kein neuer Vorgang gestartet.']; }
        $frisch = ($dienst['zeit'] ?? 0) >= time() - 20;
        $aktiv = false;
        if (is_readable($konfig['status_pfad'] . '/dienst.lock')) {
            $lock = fopen($konfig['status_pfad'] . '/dienst.lock', 'r');
            if ($lock) { $aktiv = !flock($lock, LOCK_SH | LOCK_NB); fclose($lock); }
        }
        if (!$frisch && !$aktiv) { return ['bereit' => false, 'text' => 'Der Wartungsdienst antwortet gerade nicht. Bitte kurz warten und erneut versuchen.']; }
        if (!is_file($konfig['status_pfad'] . '/version.json')) { return ['bereit' => false, 'text' => 'Backup und Updates werden automatisch vorbereitet. Bitte kurz warten.']; }
        return ['bereit' => true, 'text' => 'Bereit. Sicherung und Aktualisierung laufen automatisch im Hintergrund.'];
    }

    public static function titel(?array $status): string
    {
        return match ($status['zustand'] ?? '') {
            'erfolgreich' => ($status['aktion'] ?? '') === 'backup' ? 'Sicherung erfolgreich' : (($status['aktion'] ?? '') === 'pruefen' ? 'Prüfung abgeschlossen' : 'Aktualisierung abgeschlossen'),
            'fehlgeschlagen', 'unterbrochen' => 'Vorgang konnte nicht abgeschlossen werden',
            'laeuft' => 'Bitte warten – der Vorgang läuft',
            default => 'Bereit',
        };
    }

    public static function hinweis(array $status): string
    {
        if (($status['zustand'] ?? '') === 'erfolgreich') {
            return match ($status['aktion'] ?? '') {
                'backup' => 'Das Backend und alle aktiven Terminals wurden gesichert.',
                'update' => 'Das Backend und alle aktiven Terminals sind auf demselben Stand. Buchungen sind wieder möglich.',
                default => 'Das Ergebnis der Prüfung steht oben.',
            };
        }
        if (in_array($status['zustand'] ?? '', ['fehlgeschlagen', 'unterbrochen'], true)) {
            if ($status['gesperrt_pruefen'] ?? $status['installation_begonnen'] ?? false) {
                return 'Die Sicherung bleibt erhalten. Buchungen bleiben zum Schutz der Daten angehalten. Bitte die Betreuung der Installation verständigen.';
            }
            return 'Es wurde kein Update installiert. Bitte die Verbindung zu den Geräten und den freien Speicher prüfen und anschließend erneut versuchen.';
        }
        return match ($status['schritt'] ?? 1) {
            2 => 'Dateien und Datenbanken werden gesichert. Du kannst diese Seite schließen.',
            3 => 'Das Backend wird aktualisiert. Die Sicherung ist bereits abgeschlossen.',
            4 => 'Die Terminals erhalten automatisch denselben Programmstand.',
            5 => 'Die abschließende Prüfung läuft. Gleich sind Buchungen wieder möglich.',
            default => 'Die Voraussetzungen und verfügbaren Updates werden geprüft.',
        };
    }
}
