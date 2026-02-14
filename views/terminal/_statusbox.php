<?php
declare(strict_types=1);

// Terminal-Statusanzeige (Offline-Queue / DB-Health)
// - public/terminal.php aktualisiert pro Request: $_SESSION['terminal_queue_status']

$qs = $_SESSION['terminal_queue_status'] ?? null;

// CSS liegt zentral in `public/css/terminal.css`.
// (Kein Inline-<style> mehr, damit alle Terminal-Views konsistent bleiben.)


$hauptdb = null;
$queue = null;
$offen = null;
$fehler = null;
$speicherort = null;
$letzterFehler = null;

if (is_array($qs)) {
    if (array_key_exists('hauptdb_verfuegbar', $qs)) {
        $hauptdb = $qs['hauptdb_verfuegbar'];
    }
    if (array_key_exists('queue_verfuegbar', $qs)) {
        $queue = $qs['queue_verfuegbar'];
    } elseif (array_key_exists('offline_queue_verfuegbar', $qs)) {
        $queue = $qs['offline_queue_verfuegbar'];
    }

    if (isset($qs['queue_speicherort']) && is_string($qs['queue_speicherort']) && $qs['queue_speicherort'] !== '') {
        $speicherort = $qs['queue_speicherort'];
    }

    if (array_key_exists('offen', $qs) && $qs['offen'] !== null) {
        $offen = (int)$qs['offen'];
    }

    if (array_key_exists('fehler', $qs) && $qs['fehler'] !== null) {
        $fehler = (int)$qs['fehler'];
    }

    if (isset($qs['letzter_fehler']) && is_array($qs['letzter_fehler']) && !empty($qs['letzter_fehler']['id'])) {
        $letzterFehler = $qs['letzter_fehler'];
    }
}

$cls = 'ok';
$title = 'Systemstatus: Online';
$hint = null;

if ($hauptdb === false && $queue === true) {
    $cls = 'warn';
    $title = 'Systemstatus';
    $hint = null;
} elseif ($hauptdb === false && $queue === false) {
    $cls = 'error';
    $title = 'Systemstatus: Störung';
    $hint = 'Hauptdatenbank offline und keine Offline-Queue verfügbar – keine Buchungen möglich.';
} elseif ($hauptdb !== true) {
    $cls = 'warn';
    $title = 'Systemstatus: Unklar';
}

$fmtDb = static function ($v): string {
    if ($v === true) return 'OK';
    if ($v === false) return 'offline';
    return 'unbekannt';
};

$fmtQueue = static function ($v): string {
    if ($v === true) return 'verfügbar';
    if ($v === false) return 'nicht verfügbar';
    return 'unbekannt';
};

$hatLetztenFehler = is_array($letzterFehler) && !empty($letzterFehler['id']);

// Systemstatus am Terminal soll platzsparend sein:
// - immer unten angedockt
// - per Klick aufklappbar (wie "Übersicht heute")
$detailsOpen = ($cls !== 'ok') || $hatLetztenFehler;

$summaryTeile = [];
$summaryTeile[] = 'Hauptdatenbank: ' . $fmtDb($hauptdb);
$summaryTeile[] = 'Queue: ' . $fmtQueue($queue);
if ($offen !== null) {
    $summaryTeile[] = 'Offen: ' . (string)$offen;
}
if ($fehler !== null) {
    $summaryTeile[] = 'Fehler: ' . (string)$fehler;
}
$summaryMeta = implode(' · ', $summaryTeile);

$istOfflineStartansicht = ($hauptdb === false)
    && empty($_SESSION['terminal_mitarbeiter_id'])
    && empty($_SESSION['terminal_last_unknown_rfid']);
?>

<?php if (!$istOfflineStartansicht): ?>
<details class="status-box terminal-systemstatus <?php echo htmlspecialchars($cls, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"<?php echo $detailsOpen ? ' open' : ''; ?>>
    <summary class="status-title">
        <span><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
        <span class="status-small terminal-systemstatus-meta"><?php echo htmlspecialchars($summaryMeta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
    </summary>

    <div class="status-small">
        Hauptdatenbank: <strong><?php echo htmlspecialchars($fmtDb($hauptdb), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        · Queue: <strong><?php
            $qTxt = $fmtQueue($queue);
            if ($queue === true && $speicherort !== null) {
                $qTxt .= ' (' . $speicherort . ')';
            }
            echo htmlspecialchars($qTxt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ?></strong>
        <?php if ($offen !== null): ?>
            · Offen: <strong><?php echo (int)$offen; ?></strong>
        <?php endif; ?>
        <?php if ($fehler !== null): ?>
            · Fehler: <strong><?php echo (int)$fehler; ?></strong>
        <?php endif; ?>
    </div>
    <?php if ($hint !== null): ?>
        <div class="status-small status-hint">
            <?php echo htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($hatLetztenFehler): ?>
        <?php
            $fehlerId = (int)$letzterFehler['id'];
            $fehlerMsg = isset($letzterFehler['fehlernachricht']) ? (string)$letzterFehler['fehlernachricht'] : '';
            $sqlBefehl = isset($letzterFehler['sql_befehl']) ? (string)$letzterFehler['sql_befehl'] : '';

            // Kürzen, damit die Anzeige am Terminal nicht explodiert
            if (strlen($fehlerMsg) > 400) {
                $fehlerMsg = substr($fehlerMsg, 0, 400) . '…';
            }
            if (strlen($sqlBefehl) > 600) {
                $sqlBefehl = substr($sqlBefehl, 0, 600) . '…';
            }
        ?>
        <details class="status-details">
            <summary class="status-small"><strong>Queue-Fehler (ID <?php echo (int)$fehlerId; ?>) – Admin anfordern</strong></summary>
            <div class="status-small status-details-label">Fehlermeldung:</div>
            <pre>
<?php echo htmlspecialchars($fehlerMsg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </pre>

            <?php if ($sqlBefehl !== ''): ?>
                <div class="status-small">SQL-Befehl (gekürzt):</div>
                <pre>
<?php echo htmlspecialchars($sqlBefehl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </pre>
            <?php endif; ?>
        </details>
    <?php endif; ?>
</details>
<?php endif; ?>

<?php
$unknownRfid = $_SESSION['terminal_last_unknown_rfid'] ?? null;
$unknownTs = $_SESSION['terminal_last_unknown_rfid_ts'] ?? null;

if (!is_string($unknownRfid)) {
    $unknownRfid = '';
}
$unknownRfid = trim($unknownRfid);

if (!is_string($unknownTs)) {
    $unknownTs = '';
}
$unknownTs = trim($unknownTs);

$darfRfidZuweisen = (bool)($_SESSION['terminal_darf_rfid_zuweisen'] ?? false);

$assignUrl = '';
if ($unknownRfid !== '') {
    $assignUrl = 'terminal.php?aktion=rfid_zuweisen&prefill_rfid=' . rawurlencode($unknownRfid);
}


$startUrl = 'terminal.php?aktion=start';
if (!empty($_SESSION['terminal_debug_aktiv'])) {
    $startUrl .= '&debug=1';
}

$csrf = '';
if (isset($csrfToken) && is_string($csrfToken) && $csrfToken !== '') {
    $csrf = $csrfToken;
} elseif (isset($_SESSION['terminal_csrf_token']) && is_string($_SESSION['terminal_csrf_token']) && (string)$_SESSION['terminal_csrf_token'] !== '') {
    $csrf = (string)$_SESSION['terminal_csrf_token'];
}

?>

<?php if ($unknownRfid !== ''): ?>
    <div class="status-box error">
        <div class="status-title"><span>RFID unbekannt</span></div>
        <div class="status-small">
            Zuletzt gescannt: <strong><?php echo htmlspecialchars($unknownRfid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        </div>

        <?php if ($unknownTs !== ''): ?>
            <div class="status-hint">Zeit: <?php echo htmlspecialchars($unknownTs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="status-hint">Der RFID-Code ist keinem aktiven Mitarbeiter zugeordnet.</div>

        <?php if ($darfRfidZuweisen && $hauptdb !== true): ?>
            <div class="status-hint">Zuweisung erst möglich, wenn die Hauptdatenbank online ist.</div>
        <?php endif; ?>

        <div class="button-row mt-035">
            <?php if ($darfRfidZuweisen && $hauptdb === true && $assignUrl !== ''): ?>
                <a class="button-link button-danger" href="<?php echo htmlspecialchars($assignUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">RFID jetzt Mitarbeiter zuweisen</a>
            <?php endif; ?>

            <?php if ($csrf !== ''): ?>
                <form method="post" action="<?php echo htmlspecialchars($startUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="clear_unknown_rfid" value="1">
                    <button type="submit" class="secondary">Hinweis löschen</button>
                </form>
            <?php else: ?>
                <span class="button-link disabled">Hinweis löschen</span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
