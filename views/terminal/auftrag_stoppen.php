<?php
declare(strict_types=1);

/** @var array|null  $mitarbeiter */
/** @var string|null $nachricht */
/** @var string|null $fehlerText */
/** @var array<string,mixed>|null $letzterAuftrag */
/** @var int|null $terminalTimeoutSekunden */

$terminalTimeoutSekunden = isset($terminalTimeoutSekunden) ? (int)$terminalTimeoutSekunden : null;

$letzterAuftrag = (isset($letzterAuftrag) && is_array($letzterAuftrag)) ? $letzterAuftrag : ($_SESSION['terminal_letzter_auftrag'] ?? null);
if (!is_array($letzterAuftrag)) {
    $letzterAuftrag = null;
}
$letzterAuftragTyp = (is_array($letzterAuftrag) && isset($letzterAuftrag['typ'])) ? trim((string)$letzterAuftrag['typ']) : '';
if ($letzterAuftragTyp !== '' && $letzterAuftragTyp !== 'haupt') {
    // T-026: Auf dieser Seite wird nur der Hauptauftrag berücksichtigt
    $letzterAuftrag = null;
}
$letzterAuftragCode = (is_array($letzterAuftrag) && isset($letzterAuftrag['auftragscode']) && is_string($letzterAuftrag['auftragscode'])) ? trim($letzterAuftrag['auftragscode']) : '';

$csrfToken = (isset($csrfToken) && is_string($csrfToken)) ? $csrfToken : (string)($_SESSION['terminal_csrf_token'] ?? '');
if ($csrfToken === '') {
    // Fallback: falls Controller den Token nicht liefert (sollte normal nicht passieren)
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['terminal_csrf_token'] = $csrfToken;
}

$letzterAuftragStatus = (is_array($letzterAuftrag) && isset($letzterAuftrag['status']) && is_string($letzterAuftrag['status'])) ? (string)$letzterAuftrag['status'] : '';
$letzterIstLaufend = ($letzterAuftragCode !== '' && $letzterAuftragStatus === 'laufend');

$seitenTitel = 'Terminal – Hauptauftrag stoppen';
$seitenUeberschrift = 'Hauptauftrag stoppen';
require __DIR__ . '/_layout_top.php';
?>

    <?php if (!empty($nachricht)): ?>
        <div class="meldung">
            <?php echo htmlspecialchars($nachricht, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlerText)): ?>
        <div class="fehler">
            <?php echo htmlspecialchars($fehlerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <p class="hinweis">
        Hier stoppst du den <strong>Hauptauftrag</strong>. Nebenaufträge stoppst du über "Nebenauftrag stoppen" auf der Startseite.
        Entweder eine konkrete Auftragszeit-ID angeben oder (wenn nur ein Hauptauftrag läuft)
        über den Auftragscode stoppen. Die Buchung erfolgt automatisch für den angemeldeten Mitarbeiter.
    </p>

    <?php if ($letzterAuftragCode !== ''): ?>
        <p class="hinweis">Letzter Hauptauftrag (lokal): <strong><?php echo htmlspecialchars($letzterAuftragCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></p>
    <?php endif; ?>

    <?php if ($letzterIstLaufend): ?>
        <div class="button-row">
            <form method="post" action="terminal.php?aktion=auftrag_stoppen">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="auftragscode" value="<?php echo htmlspecialchars($letzterAuftragCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="status" value="abgeschlossen">
                <button type="submit">Letzten Hauptauftrag abschließen</button>
            </form>

            <form method="post" action="terminal.php?aktion=auftrag_stoppen">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="auftragscode" value="<?php echo htmlspecialchars($letzterAuftragCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="status" value="abgebrochen">
                <button type="submit" class="button-danger">Letzten Hauptauftrag abbrechen</button>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" action="terminal.php?aktion=auftrag_stoppen" class="login-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

        <label for="auftragszeit_id">Auftragszeit-ID (optional)</label>
        <input type="number" id="auftragszeit_id" name="auftragszeit_id" min="1">

        <label for="auftragscode">Auftragscode (optional)</label>
        <input type="text" id="auftragscode" name="auftragscode" autocomplete="off" value="<?php echo htmlspecialchars($letzterAuftragCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

        <label for="status">Status beim Stoppen</label>
        <select id="status" name="status">
            <option value="abgeschlossen">Abschließen</option>
            <option value="abgebrochen">Abbrechen</option>
        </select>

        <div class="button-row">
            <button type="submit">Hauptauftrag stoppen</button>
            <a href="terminal.php?aktion=start" class="button-link secondary">Zurück zum Start</a>
        </div>
    </form>

    <?php
    $logoutFormHidden = true;
    require __DIR__ . '/_logout_form.php';
    ?>



<?php if (!empty($mitarbeiter) && is_array($mitarbeiter)): ?>
    <?php
        // Unten angedockte, platzsparende Mitarbeiter-Info (Touch/Kiosk).
        // Einheitlich zum Startscreen/Urlaub-Seiten.
        $mitarbeiterName = trim((string)($mitarbeiter['vorname'] ?? '') . ' ' . (string)($mitarbeiter['nachname'] ?? ''));
        $mitarbeiterId = (int)($mitarbeiter['id'] ?? 0);
    ?>

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
