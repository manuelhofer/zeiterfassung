<?php
declare(strict_types=1);

/**
 * Terminal – Störung: weder Hauptdatenbank noch Offline-Queue erreichbar
 *
 * Regel (`docs/fachregeln/terminal_und_offline.md`, Abschnitt 5):
 * Das ist der **einzige** Fall, für den es diesen sperrenden Bildschirm noch
 * gibt – in ihm lässt sich keine Buchung speichern, weder in der Hauptdatenbank
 * noch lokal. Den Status `503` setzt der Aufrufer (`public/terminal.php`).
 *
 * Ein gescheiterter Queue-Eintrag führt hierher **nicht** mehr: Er wird beim
 * Einspielen übersprungen, das Terminal bleibt bedienbar, und gemeldet wird er
 * dort, wo jemand entscheiden darf – in der Queue-Verwaltung des Backends.
 */

$queueStatus = $_SESSION['terminal_queue_status'] ?? null;

$queueOffen  = null;
$queueFehler = null;
$queueZeit   = null;
$hauptdbOk   = null;
$offlineQueueOk = null;

if (is_array($queueStatus)) {
    if (isset($queueStatus['zeit']) && is_string($queueStatus['zeit'])) {
        $queueZeit = $queueStatus['zeit'];
    }
    if (array_key_exists('hauptdb_verfuegbar', $queueStatus)) {
        $hauptdbOk = $queueStatus['hauptdb_verfuegbar'];
    }
    if (array_key_exists('queue_verfuegbar', $queueStatus)) {
        $offlineQueueOk = $queueStatus['queue_verfuegbar'];
    }
    if (array_key_exists('offen', $queueStatus) && $queueStatus['offen'] !== null) {
        $queueOffen = (int)$queueStatus['offen'];
    }
    if (array_key_exists('fehler', $queueStatus) && $queueStatus['fehler'] !== null) {
        $queueFehler = (int)$queueStatus['fehler'];
    }
}

$seitenTitel = 'Störung – Terminal';
$seitenUeberschrift = 'Terminal nicht verfügbar';
$bodyKlasse = 'terminal-wide';
require __DIR__ . '/_layout_top.php';
?>

<div class="fehler">
        <strong>Weder Hauptdatenbank noch Offline-Queue verfügbar.</strong><br>
        Bitte <strong>Administrator anfordern</strong>.<br>
        Ohne Offline-Queue kann das Terminal keine Buchungen speichern.
    </div>

    <div class="status-box warn">
        <div class="status-small">
            Zeitpunkt: <strong><?php echo htmlspecialchars((string)($queueZeit ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            &nbsp;|&nbsp;
            Hauptdatenbank: <strong><?php
                if ($hauptdbOk === true) {
                    echo 'OK';
                } elseif ($hauptdbOk === false) {
                    echo 'NICHT ERREICHBAR';
                } else {
                    echo 'unbekannt';
                }
            ?></strong>
            &nbsp;|&nbsp;
            Offline-Queue: <strong><?php
                if ($offlineQueueOk === true) {
                    echo 'OK';
                } elseif ($offlineQueueOk === false) {
                    echo 'NICHT VERFÜGBAR';
                } else {
                    echo 'unbekannt';
                }
            ?></strong>
            &nbsp;|&nbsp;
            Queue offen: <strong><?php echo $queueOffen === null ? '-' : (int)$queueOffen; ?></strong>
            &nbsp;|&nbsp;
            Queue Fehler: <strong><?php echo $queueFehler === null ? '-' : (int)$queueFehler; ?></strong>
        </div>
    </div>

    <div class="status-box error">
        <div class="status-title"><span>Fehlerdetails</span></div>
        Offline-Queue ist deaktiviert oder nicht erreichbar. Bitte Offline-DB/Config prüfen.
    </div>

    <p class="hinweis center">
        Hinweis: Bitte Offline-DB/Config reparieren (Offline-Queue). Danach kann das Terminal wieder buchen.
    </p>

<div class="button-row">
    <a href="terminal.php?aktion=start" class="button-link">Neu prüfen / Start</a>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
