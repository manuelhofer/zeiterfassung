<?php
declare(strict_types=1);

/** @var array|null  $mitarbeiter */
/** @var string|null $nachricht */
/** @var string|null $fehlerText */
/** @var int|null    $terminalTimeoutSekunden */
/** @var array<int,array<string,mixed>> $urlaubsantraege */
/** @var string|null $csrfToken */
/** @var array<string,string|int>|null $urlaubSaldo */
/** @var string|null $urlaubSaldoFehler */
/** @var int|null $urlaubJahr */
/** @var array<string,string>|null $urlaubVorschau */
/** @var array<string,string>|null $urlaubFormular */
/** @var array<string,mixed>|null $monatsStatus */

$terminalTimeoutSekunden = isset($terminalTimeoutSekunden) ? (int)$terminalTimeoutSekunden : 180;
if ($terminalTimeoutSekunden < 10) {
    $terminalTimeoutSekunden = 180;
}


$urlaubsantraege = $urlaubsantraege ?? [];
if (!is_array($urlaubsantraege)) {
    $urlaubsantraege = [];
}

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$urlaubFormular = is_array($urlaubFormular ?? null) ? (array)$urlaubFormular : [
    'von_datum'             => '',
    'bis_datum'             => '',
    'kommentar_mitarbeiter' => '',
];

$urlaubSaldo = (isset($urlaubSaldo) && is_array($urlaubSaldo)) ? $urlaubSaldo : null;
$urlaubSaldoFehler = (isset($urlaubSaldoFehler) && is_string($urlaubSaldoFehler) && $urlaubSaldoFehler !== '') ? $urlaubSaldoFehler : null;
$urlaubJahr = isset($urlaubJahr) ? (int)$urlaubJahr : (int)(new DateTimeImmutable('now'))->format('Y');
$urlaubVorschau = (isset($urlaubVorschau) && is_array($urlaubVorschau)) ? $urlaubVorschau : null;

// Mitarbeiter-Name/ID für das Bottom-Panel (defensiv; verhindert undefined vars)
$mitarbeiterName = '';
$mitarbeiterId = 0;
if (is_array($mitarbeiter ?? null)) {
    $mitarbeiterName = trim((string)($mitarbeiter['vorname'] ?? '') . ' ' . (string)($mitarbeiter['nachname'] ?? ''));
    $mitarbeiterId = (int)($mitarbeiter['id'] ?? 0);
}

$fmtTage = static function ($val): string {
    $f = 0.0;
    if (is_numeric($val)) {
        $f = (float)$val;
    }
    return number_format($f, 2, ',', '.');
};

// Haupt-DB Status (für "nur online" Bereiche)
$queueStatus = $_SESSION['terminal_queue_status'] ?? null;
$hauptdbOk = null;
if (is_array($queueStatus) && array_key_exists('hauptdb_verfuegbar', $queueStatus)) {
    $hauptdbOk = $queueStatus['hauptdb_verfuegbar'];
}
$seitenTitel = 'Terminal – Urlaub beantragen';
$seitenUeberschrift = 'Urlaub beantragen';
require __DIR__ . '/_layout_top.php';
?>
<?php require __DIR__ . '/_statusbox.php'; ?>

    <?php
    $logoutFormHidden = true;
    require __DIR__ . '/_logout_form.php';
    ?>

    <div class="top-actions">
        <a href="terminal.php?aktion=start" class="button-link secondary">Zurück</a>
        <button type="submit" form="logout-form" class="button-link secondary">Abmelden</button>
    </div>

    <?php if (!empty($nachricht)): ?>
        <div class="meldung">
            <?php echo htmlspecialchars((string)$nachricht, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mitarbeiter)): ?>
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

    <?php if (!empty($fehlerText)): ?>
        <div class="fehler">
            <?php echo htmlspecialchars((string)$fehlerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($mitarbeiter)): ?>
        <p class="hinweis">
            Sie sind nicht am Terminal angemeldet. Bitte zurück zum Startscreen.
        </p>
        <div class="button-row">
            <a href="terminal.php?aktion=start" class="button-link">Zum Start</a>
        </div>
    <?php else: ?>

        <div class="status-box">
            <div class="status-title">
                <span>Urlaubssaldo <?php echo (int)$urlaubJahr; ?></span>
            </div>

            <?php if ($hauptdbOk !== true): ?>
                <div class="status-small"><strong>Nur online verfügbar.</strong></div>
            <?php elseif (!empty($urlaubSaldoFehler)): ?>
                <div class="status-small"><strong><?php echo htmlspecialchars((string)$urlaubSaldoFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
            <?php elseif (is_array($urlaubSaldo)): ?>
                <div class="status-small">
    Verfügbar gesamt: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['verbleibend'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
    &nbsp;|&nbsp; Genehmigt: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['genommen'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
    &nbsp;|&nbsp; Offen: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['beantragt'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
</div>
<div class="status-small mt-025">
    <?php
        $anspruchBasis = (float)($urlaubSaldo['anspruch'] ?? 0);
        $korrekturTage = (float)($urlaubSaldo['korrektur'] ?? 0);
        $anspruchEffektiv = $anspruchBasis + $korrekturTage;
    ?>
    Jahresanspruch (<?php echo (int)$urlaubJahr; ?>): <strong><?php echo htmlspecialchars($fmtTage($anspruchBasis), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
    <?php if (abs($korrekturTage) > 0.0001): ?>
        &nbsp;|&nbsp; Korrektur: <strong><?php echo htmlspecialchars($fmtTage($korrekturTage), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        &nbsp;|&nbsp; Effektiv: <strong><?php echo htmlspecialchars($fmtTage($anspruchEffektiv), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
    <?php endif; ?>
    &nbsp;|&nbsp; Übertrag (<?php echo (int)($urlaubJahr - 1); ?>): <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['uebertrag'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
</div>


                <?php if (!empty($urlaubSaldo['hinweis'])): ?>
                    <div class="status-small mt-025">
                        <?php echo htmlspecialchars((string)$urlaubSaldo['hinweis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="status-small">Keine Urlaubsdaten verfügbar.</div>
            <?php endif; ?>
        </div>

        <?php if ($hauptdbOk === true && is_array($urlaubSaldo)): ?>
            <div class="status-box">
                <div class="status-title"><span>Vorschau</span></div>
                <div class="status-small">
                    Verfügbar: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['verbleibend'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    <?php if (is_array($urlaubVorschau) && isset($urlaubVorschau['tage_antrag'])): ?>
                        &nbsp;|&nbsp; Dieser Antrag: <strong><?php echo htmlspecialchars($fmtTage($urlaubVorschau['tage_antrag'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        &nbsp;|&nbsp; Nach diesem Antrag: <strong><?php echo htmlspecialchars($fmtTage($urlaubVorschau['nach_antrag'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    <?php else: ?>
                        &nbsp;|&nbsp; Zeitraum wählen für Vorschau.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
            $urlaubListeOpen = true;
            require __DIR__ . '/_urlaub_antraege_liste.php';
        ?>

        <p class="hinweis mt-1">
            <strong>Neuen Urlaubsantrag erstellen</strong><br>
            Zeitraum auswählen und optional einen Kommentar hinzufügen.
        </p>

        <form method="post" action="terminal.php?aktion=urlaub_beantragen" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

            <label for="von_datum">Von</label>
            <input type="date" id="von_datum" name="von_datum"
                   value="<?php echo htmlspecialchars((string)($urlaubFormular['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                   required autofocus>

            <label for="bis_datum">Bis</label>
            <input type="date" id="bis_datum" name="bis_datum"
                   value="<?php echo htmlspecialchars((string)($urlaubFormular['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                   required>

            <label for="kommentar_mitarbeiter">Kommentar (optional)</label>
            <textarea id="kommentar_mitarbeiter" name="kommentar_mitarbeiter"><?php
                echo htmlspecialchars((string)($urlaubFormular['kommentar_mitarbeiter'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            ?></textarea>

            <div class="button-row">
                <button type="submit">Antrag speichern</button>
                <a href="terminal.php?aktion=start" class="button-link secondary">Abbrechen</a>
            </div>
        </form>

        <div class="logout-link">
            <button type="submit" form="logout-form" class="logout-button">Abmelden</button>
        </div>
    <?php endif; ?>


	<?php require __DIR__ . '/_layout_bottom.php'; ?>
