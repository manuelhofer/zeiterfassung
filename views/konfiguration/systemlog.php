<?php
declare(strict_types=1);
/**
 * Template: Konfiguration – System-Log (Warnung/Fehler)
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>) – Zeilen aus `system_log`
 * - $limit (int) – wie viele Einträge die Abfrage höchstens geholt hat
 * - optional: $ok (int) – 1 zeigt „Aktion abgeschlossen."
 * - optional: $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn das Log nicht gelesen werden konnte
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? '');
/** @var array<int,array<string,mixed>> $eintraege */
$eintraege     = $eintraege ?? [];
$limit         = (int)($limit ?? 0);
$ok            = (int)($ok ?? 0);
$fehlermeldung = $fehlermeldung ?? null;
$ladefehler    = (bool)($ladefehler ?? false);
?>
<section>
    <h2>System-Log</h2>

    <p style="margin-top:0.25rem;">
        <a href="?seite=konfiguration_admin">Konfiguration</a>
        | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
        | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
        | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
        | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
    </p>

    <p class="muted" style="max-width:60rem;">
        Angezeigt werden die letzten <?php echo (int)$limit; ?> Einträge (Warnung, Fehler).
    </p>

    <?php if ($ok === 1): ?>
        <div class="erfolgsmeldung">Aktion abgeschlossen.</div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=konfiguration_admin&amp;tab=systemlog" onsubmit="return confirm('System-Log wirklich vollständig leeren?');">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="log_action" value="leeren">
        <button type="submit" class="button-link danger">System-Log leeren</button>
    </form>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Einträge vorhanden" wäre daneben
             die falsche Auskunft. */ ?>
    <?php if (count($eintraege) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine Log-Einträge vorhanden.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Zeit</th>
                    <th>Level</th>
                    <th>Kategorie</th>
                    <th>Nachricht</th>
                    <th>Daten</th>
                    <th>Mitarbeiter</th>
                    <th>Terminal</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eintraege as $e): ?>
                    <?php
                        $id = (int)($e['id'] ?? 0);
                        $zeit = (string)($e['zeitstempel'] ?? '');
                        $level = strtolower((string)($e['loglevel'] ?? ''));
                        $kategorie = (string)($e['kategorie'] ?? '');
                        $nachricht = (string)($e['nachricht'] ?? '');
                        $daten = (string)($e['daten'] ?? '');
                        $mitarbeiterId = (int)($e['mitarbeiter_id'] ?? 0);
                        $terminalId = (int)($e['terminal_id'] ?? 0);
                        $mVorname = trim((string)($e['m_vorname'] ?? ''));
                        $mNachname = trim((string)($e['m_nachname'] ?? ''));
                        $mitarbeiterName = trim($mNachname . ', ' . $mVorname);
                        if ($mitarbeiterName === '' && $mitarbeiterId > 0) {
                            $mitarbeiterName = 'Mitarbeiter #' . $mitarbeiterId;
                        }
                        $datenKurz = $daten !== '' && mb_strlen($daten) > 120 ? (mb_substr($daten, 0, 120) . '…') : $daten;
                        $datenVoll = $daten !== '' ? $daten : 'Keine Daten vorhanden.';
                        $detailId = 'systemlog_detail_' . $id;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($zeit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($level !== '' ? strtoupper($level) : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($kategorie !== '' ? $kategorie : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($nachricht, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($datenKurz !== ''): ?>
                                <code><?php echo htmlspecialchars($datenKurz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($mitarbeiterName !== '' ? $mitarbeiterName : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $terminalId > 0 ? (int)$terminalId : '-'; ?></td>
                        <td>
                            <button type="button" class="button-link" data-detail-toggle="<?php echo htmlspecialchars($detailId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Details</button>
                            <form method="post" action="?seite=konfiguration_admin&amp;tab=systemlog" style="display:inline;" onsubmit="return confirm('Diesen Log-Eintrag wirklich löschen?');">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <?php /* Wert ohne Umlaut: das ist kein Oberflächentext, sondern der
                                        Vergleichswert aus $aktion === 'loeschen' im Controller. */ ?>
                                <input type="hidden" name="log_action" value="loeschen">
                                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                <button type="submit" class="button-link danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="<?php echo htmlspecialchars($detailId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="display:none;">
                        <td colspan="8">
                            <div style="padding:0.5rem 0;">
                                <strong>Details:</strong>
                                <pre style="white-space:pre-wrap; margin:0.35rem 0 0;"><?php echo htmlspecialchars($datenVoll, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function(){
            var toggles = document.querySelectorAll('[data-detail-toggle]');
            toggles.forEach(function(btn){
                btn.addEventListener('click', function(){
                    var zielId = btn.getAttribute('data-detail-toggle');
                    if (!zielId) return;
                    var zeile = document.getElementById(zielId);
                    if (!zeile) return;
                    var sichtbar = zeile.style.display !== 'none';
                    zeile.style.display = sichtbar ? 'none' : 'table-row';
                });
            });
        })();
        </script>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
