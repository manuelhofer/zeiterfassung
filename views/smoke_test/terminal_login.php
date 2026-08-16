<?php
declare(strict_types=1);
/**
 * Teil-Template: Terminal-Login-Check (RFID / Personalnummer / ID).
 *
 * Erwartet **einen** Wert, `$terminalLoginDaten` – das Bündel von
 * `SmokeTestController::pruefeTerminalLogin()`:
 * - `code` (string) – der Code, mit dem der Check zuletzt lief
 * - `ergebnis` (array|null) – `null` heißt „noch nicht gelaufen"
 * - `hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses
 */
/** @var array<string,mixed> $terminalLoginDaten */
$code     = (string)($terminalLoginDaten['code'] ?? '');
$ergebnis = $terminalLoginDaten['ergebnis'] ?? null;
$hinweis  = $terminalLoginDaten['hinweis'] ?? null;
?>
<h3>Terminal-Login-Check (RFID / Personalnummer / ID)</h3>
<p>
    Dieser Check ist <strong>rein lesend</strong> und emuliert die Login-Reihenfolge des Terminals.
    Damit lässt sich schnell prüfen, ob ein Code in der Datenbank zu einem <strong>aktiven</strong> Mitarbeiter auflöst.
</p>

<form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
    <label for="terminal_login_code"><strong>Code</strong>:</label>
    <input type="text" id="terminal_login_code" name="terminal_login_code"
           value="<?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
           style="min-width: 260px;">
    <button type="submit">Prüfen</button>
</form>

<?php if ($hinweis !== null && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($ergebnis)):
    $typ = $ergebnis['typ'] ?? null;
    $m = $ergebnis['mitarbeiter'] ?? null;
    $warn = $ergebnis['warnungen'] ?? [];
    ?>
    <div style="padding:10px; background:<?php echo $typ ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $typ ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
        <?php if ($typ && is_array($m)): ?>
            <p style="margin:0 0 6px 0;"><strong>OK:</strong> Terminal würde den Mitarbeiter per <strong><?php echo htmlspecialchars((string)$typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> einloggen.</p>
            <ul style="margin:0;">
                <li>ID: <?php echo (int)($m['id'] ?? 0); ?></li>
                <li>Name: <?php echo htmlspecialchars(trim((string)($m['vorname'] ?? '') . ' ' . (string)($m['nachname'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <li>Personalnummer: <?php echo htmlspecialchars((string)($m['personalnummer'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <li>RFID: <?php echo htmlspecialchars((string)($m['rfid_code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            </ul>

            <?php
            $a = $ergebnis['anwesenheit'] ?? null;
            if (is_array($a) && isset($a['kommen']) && isset($a['gehen'])):
                $isA = (bool)($a['ist_anwesend'] ?? false);
                $k = (int)($a['kommen'] ?? 0);
                $g = (int)($a['gehen'] ?? 0);
                ?>
                <hr>
                <p style="margin:0 0 6px 0;"><strong>Anwesenheit heute (<?php echo htmlspecialchars((string)($a['datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>):</strong>
                    Kommen <?php echo $k; ?> / Gehen <?php echo $g; ?>
                    &nbsp;→&nbsp;
                    <strong><?php echo $isA ? 'ANWESEND' : 'NICHT anwesend'; ?></strong>
                </p>
                <p style="margin:0 0 6px 0;">
                    Terminal-Menü sollte entsprechend
                    <strong><?php echo $isA ? 'Gehen + Aufträge (+ Urlaub)' : 'nur Kommen (+ Urlaub)'; ?></strong>
                    anzeigen.
                </p>


                <?php
                $kommenErlaubt = (bool)($a['kommen_erlaubt'] ?? (!$isA));
                $gehenErlaubt = (bool)($a['gehen_erlaubt'] ?? $isA);
                $auftragErlaubt = (bool)($a['auftrag_erlaubt'] ?? $isA);
                ?>
                <p style="margin:0 0 6px 0;">
                    <strong>Erlaubt (online-Check):</strong>
                    Kommen <?php echo $kommenErlaubt ? 'JA' : 'NEIN'; ?>,
                    Gehen <?php echo $gehenErlaubt ? 'JA' : 'NEIN'; ?>,
                    Auftrag-Start <?php echo $auftragErlaubt ? 'JA' : 'NEIN'; ?>
                </p>
                <?php if (is_array($a['letzte_buchung'] ?? null) && isset($a['letzte_buchung']['typ'])):
                    $lb = $a['letzte_buchung'];
                    ?>
                    <p style="margin:0;">
                        Letzte Buchung: <strong><?php echo htmlspecialchars((string)($lb['typ'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        um <strong><?php echo htmlspecialchars((string)($lb['zeitstempel'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        (Quelle: <?php echo htmlspecialchars((string)($lb['quelle'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                    </p>
                <?php endif; ?>

            <?php elseif (is_array($a) && isset($a['fehler'])): ?>
                <hr>
                <p style="margin:0;"><strong>Anwesenheit-Check:</strong> Fehler: <?php echo htmlspecialchars((string)$a['fehler'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            <?php endif; ?>

        <?php else:
            $fc = (string)($ergebnis['fehler_code'] ?? '');
            if ($fc === 'MEHRDEUTIG'):
                ?>
                <p style="margin:0;"><strong>BLOCK:</strong> Mehrdeutiger numerischer Code – Terminal würde den Login abbrechen.</p>
            <?php else: ?>
                <p style="margin:0;"><strong>FAIL:</strong> Kein aktiver Mitarbeiter für diesen Code gefunden.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        $alts = $ergebnis['alternativen'] ?? [];
        if (is_array($alts) && count($alts) > 0):
            ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Alternative Treffer (Mehrdeutigkeits-Check):</strong></p>
            <ul style="margin:0;">
                <?php foreach ($alts as $t => $rowAlt):
                    $altId = (int)($rowAlt['id'] ?? 0);
                    $altName = trim((string)($rowAlt['vorname'] ?? '') . ' ' . (string)($rowAlt['nachname'] ?? ''));
                    $altLine = (string)$t . ': ID ' . $altId . ($altName !== '' ? ' (' . $altName . ')' : '');
                    ?>
                    <li><?php echo htmlspecialchars($altLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_array($warn) && count($warn) > 0): ?>
            <hr>
            <p style="margin:0 0 6px 0;"><strong>Hinweise:</strong></p>
            <ul style="margin:0;">
                <?php foreach ($warn as $w): ?>
                    <li><?php echo htmlspecialchars((string)$w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>


<?php endif; ?>
