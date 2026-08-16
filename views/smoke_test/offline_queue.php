<?php
declare(strict_types=1);
/**
 * Teil-Template: Offline-Queue (db_injektionsqueue).
 *
 * Erwartet:
 * - `$queueDaten` – das Bündel von `SmokeTestController::holeQueueUebersicht()`:
 *   `uebersicht` (array|null) mit `quelle`, `counts` und `latest`, dazu
 *   `hinweis` (string|null), wenn das Lesen gescheitert ist oder offene bzw.
 *   fehlerhafte Einträge liegen.
 * - `$smokeFlash` (string|null) – die Meldung des letzten Queue-Roundtrips.
 *   Sie gehört nicht zur Übersicht, sondern zur Aktion darunter, und kommt
 *   deshalb getrennt.
 * - `$csrfBereich` (string) – Bereichsname für `Csrf::feld()`; der Roundtrip
 *   schreibt in die Queue und ist deshalb geschützt.
 *
 * Dieser Block hat kein Prüfformular: Die Übersicht läuft bei jedem Aufruf.
 */
/** @var array<string,mixed> $queueDaten */
/** @var string|null $smokeFlash */
/** @var string $csrfBereich */
$uebersicht = $queueDaten['uebersicht'] ?? null;
$hinweis    = $queueDaten['hinweis'] ?? null;
?>
<h3>Offline-Queue (db_injektionsqueue)</h3>
<p>
    Zeigt den Status der Offline-Queue (rein lesend). Wenn „offen“ oder „fehler“ vorhanden sind,
    muss die Queue im Backend verarbeitet bzw. geprüft werden.
</p>

<?php if (is_string($smokeFlash) && $smokeFlash !== ''): ?>
    <div style="padding:10px; background:#e3f2fd; border:1px solid #1565c0; margin-bottom: 12px;">
        <?php echo htmlspecialchars($smokeFlash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_string($hinweis) && $hinweis !== ''): ?>
    <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($uebersicht) && is_array($uebersicht['counts'] ?? null)):
    $qQuelle = (string)($uebersicht['quelle'] ?? '');
    $qQuelleLabel = ($qQuelle === 'offline') ? 'Offline-DB' : 'Haupt-DB';
    $qc = (array)$uebersicht['counts'];
    $qFehler = (int)($qc['fehler'] ?? 0);
    $qOffen = (int)($qc['offen'] ?? 0);
    $qVerarb = (int)($qc['verarbeitet'] ?? 0);
    $qGes = (int)($qc['gesamt'] ?? ($qFehler + $qOffen + $qVerarb));
    $qOk = ($qFehler === 0);
    ?>
    <div style="padding:10px; background:<?php echo $qOk ? '#e8f5e9' : '#ffebee'; ?>; border:1px solid <?php echo $qOk ? '#2e7d32' : '#c62828'; ?>; margin-bottom: 12px;">
        <p style="margin:0 0 6px 0;">
            <strong><?php echo $qOk ? 'OK' : 'HINWEIS'; ?>:</strong>
            Queue-DB: <?php echo htmlspecialchars($qQuelleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> – Queue-Einträge: Gesamt <?php echo $qGes; ?> – Offen <?php echo $qOffen; ?> – Fehler <?php echo $qFehler; ?> – Verarbeitet <?php echo $qVerarb; ?>
        </p>

        <form method="post" action="?seite=smoke_test" style="margin:0 0 8px 0;">
            <?php echo Csrf::feld($csrfBereich); ?>
            <input type="hidden" name="action" value="queue_roundtrip">
            <button type="submit" style="padding:6px 10px; border:1px solid #1565c0; background:#e3f2fd; cursor:pointer;">
                Queue-Roundtrip testen (DO 1)
            </button>
            <span class="status-small" style="margin-left:8px;">Erzeugt einen harmlosen Testeintrag in der Queue und stößt die Verarbeitung an.</span>
        </form>

        <?php $latest = $uebersicht['latest'] ?? []; ?>
        <?php if (is_array($latest) && count($latest) > 0): ?>
            <details>
                <summary class="status-small"><strong>Letzte 10 Queue-Einträge anzeigen</strong></summary>
                <table style="margin-top:8px;">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Aktion</th>
                        <th>Mitarbeiter</th>
                        <th>Terminal</th>
                        <th>Erstellt</th>
                        <th>Letzte Ausführung</th>
                        <th>Versuche</th>
                        <th>Fehler</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($latest as $r):
                        if (!is_array($r)) { continue; }
                        ?>
                        <tr>
                            <td><?php echo (int)($r['id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['meta_aktion'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['meta_mitarbeiter_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['meta_terminal_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['erstellt_am'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['letzte_ausfuehrung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo (int)($r['versuche'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['fehlernachricht_kurz'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php else: ?>
            <p style="margin:0;"><em>Keine Queue-Einträge vorhanden.</em></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <p><em>Keine Queue-Daten verfügbar (DB nicht erreichbar oder Tabelle fehlt).</em></p>
<?php endif; ?>
