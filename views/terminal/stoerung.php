<?php
declare(strict_types=1);

/**
 * Terminal – Störungsmodus
 *
 * Dieser Screen wird angezeigt, sobald ein Eintrag in der lokalen Offline-Queue
 * (db_injektionsqueue) den Status "fehler" hat.
 *
 * Master-Prompt:
 * - Queue-Abarbeitung wird beim ersten Fehler gestoppt.
 * - Terminal muss dann Aktionen blockieren und den fehlerhaften SQL-Befehl anzeigen.
 * - Erst nachdem ein Admin den Eintrag im Backend löscht/ignoriert, darf die Queue weiterlaufen.
 */

$queueStatus = $_SESSION['terminal_queue_status'] ?? null;

$eintrag = $stoerungEintrag ?? null;
if (!is_array($eintrag)) {
    $eintrag = null;
}

$sqlBefehl = $eintrag['sql_befehl'] ?? null;
if (!is_string($sqlBefehl)) {
    $sqlBefehl = null;
}

$fehlernachricht = $eintrag['fehlernachricht'] ?? null;
if (!is_string($fehlernachricht)) {
    $fehlernachricht = null;
}

$erstelltAm = $eintrag['erstellt_am'] ?? null;
if (!is_string($erstelltAm)) {
    $erstelltAm = null;
}

$id = $eintrag['id'] ?? null;
if (!is_scalar($id) || (string)$id === '') {
    $id = null;
}

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

    if (array_key_exists('offline_queue_verfuegbar', $queueStatus)) {
        $offlineQueueOk = $queueStatus['offline_queue_verfuegbar'];
    }
    if (array_key_exists('offen', $queueStatus) && $queueStatus['offen'] !== null) {
        $queueOffen = (int)$queueStatus['offen'];
    }
    if (array_key_exists('fehler', $queueStatus) && $queueStatus['fehler'] !== null) {
        $queueFehler = (int)$queueStatus['fehler'];
    }
}



$fatalOhneQueue = ($hauptdbOk === false && $offlineQueueOk === false);


$seitenTitel = 'Störung – Terminal';
$seitenUeberschrift = $fatalOhneQueue ? 'Terminal nicht verfügbar' : 'Terminal im Störungsmodus';
$bodyKlasse = 'terminal-wide';
require __DIR__ . '/_layout_top.php';
?>

<?php require __DIR__ . '/_statusbox.php'; ?>

<div class="fehler">
        <?php if ($fatalOhneQueue): ?>
            <strong>Weder Hauptdatenbank noch Offline-Queue verfügbar.</strong><br>
            Bitte <strong>Administrator anfordern</strong>.<br>
            Ohne Offline-Queue kann das Terminal keine Buchungen speichern.
        <?php else: ?>
            <strong>Fehler bei der Übertragung in die Hauptdatenbank.</strong><br>
            Bitte <strong>Administrator anfordern</strong>.<br>
            Solange der fehlerhafte Queue-Eintrag nicht entfernt wurde, sind alle Terminal-Aktionen gesperrt.
        <?php endif; ?>
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
        <?php if ($fatalOhneQueue): ?>
            Offline-Queue ist deaktiviert oder nicht erreichbar. Bitte Offline-DB/Config prüfen.
        <?php else: ?>
            <?php if ($id !== null): ?>
                ID: <strong><?php echo htmlspecialchars((string)$id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong><br>
            <?php endif; ?>
            <?php if ($erstelltAm !== null): ?>
                Erstellt am: <strong><?php echo htmlspecialchars($erstelltAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong><br>
            <?php endif; ?>
            <?php if ($fehlernachricht !== null && $fehlernachricht !== ''): ?>
                Fehlermeldung: <strong><?php echo htmlspecialchars($fehlernachricht, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            <?php else: ?>
                Fehlermeldung: <strong>(nicht verfügbar)</strong>
            <?php endif; ?>

            <div class="status-small mt"><strong>SQL-Befehl</strong></div>
            <?php if ($sqlBefehl !== null && $sqlBefehl !== ''): ?>
                <pre><?php echo htmlspecialchars($sqlBefehl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
            <?php else: ?>
                <pre>(SQL-Befehl nicht verfügbar)</pre>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <p class="hinweis center">
        <?php if ($fatalOhneQueue): ?>
            Hinweis: Bitte Offline-DB/Config reparieren (Offline-Queue). Danach kann das Terminal wieder buchen.
        <?php else: ?>
            Hinweis: Der fehlerhafte Queue-Eintrag muss im Backend (Offline-Queue) gelöscht/ignoriert werden,
            oder manuell korrekt nachgetragen werden. Danach läuft die Queue weiter.
        <?php endif; ?>
    </p>

<div class="button-row">
    <a href="terminal.php?aktion=start" class="button-link">Neu pruefen / Start</a>
</div>

<?php
// Optionales Mitarbeiterpanel (nur wenn noch ein Mitarbeiter eingeloggt ist).
$mitarbeiterId = null;
$mitarbeiterName = '';
if (isset($mitarbeiter) && is_array($mitarbeiter) && isset($mitarbeiter['id'])) {
    $mitarbeiterId = (int)$mitarbeiter['id'];
    $mitarbeiterName = trim((string)($mitarbeiter['vorname'] ?? '') . ' ' . (string)($mitarbeiter['nachname'] ?? ''));
}
?>

<?php if ($mitarbeiterId !== null && $mitarbeiterName !== ''): ?>
    <details class="status-box terminal-mitarbeiterpanel">
        <summary class="status-title">
            <a href="terminal.php?aktion=start&amp;view=arbeitszeit" style="display:flex; justify-content:space-between; width:100%; color:inherit; text-decoration:none;">
                <span>
                    Mitarbeiter:
                    <strong><?php echo htmlspecialchars($mitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    (ID: <?php echo (int)$mitarbeiterId; ?>)
                </span>
                <span class="status-small">Arbeitszeit</span>
            </a>
        </summary>
    </details>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
