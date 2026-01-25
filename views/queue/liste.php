<?php
declare(strict_types=1);

/**
 * View: Liste der Offline-Queue-Einträge
 *
 * Erwartet:
 * - array $eintraege
 * - string $aktuellerStatusFilter
 * - string $meldung
 * - string $csrfToken
 * - array|null $queueReport (Flash-Report nach "Queue verarbeiten")
 */
?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<section>
    <h2>Offline-Queue</h2>

    <form method="get" style="margin-bottom: 1rem;">
        <input type="hidden" name="seite" value="queue_admin">
        <label for="status">Status:</label>
        <select name="status" id="status">
            <option value="offen" <?php echo $aktuellerStatusFilter === 'offen' ? 'selected' : ''; ?>>Offen</option>
            <option value="verarbeitet" <?php echo $aktuellerStatusFilter === 'verarbeitet' ? 'selected' : ''; ?>>Verarbeitet</option>
            <option value="fehler" <?php echo $aktuellerStatusFilter === 'fehler' ? 'selected' : ''; ?>>Fehler</option>
        </select>
        <button type="submit">Filtern</button>
    </form>

    <?php if (!empty($meldung)): ?>
        <div style="padding: 0.6rem 0.8rem; background: #eef6ff; border: 1px solid #cfe6ff; border-radius: 6px; margin-bottom: 1rem;">
            <?php if ($meldung === 'eintrag_geloescht'): ?>
                <strong>OK:</strong> Queue-Eintrag wurde ignoriert/gelöscht.
            <?php elseif ($meldung === 'queue_verarbeitet'): ?>
                <?php if (isset($queueReport) && is_array($queueReport)): ?>
                    <?php
                    $versucht = (int)($queueReport['versucht'] ?? 0);
                    $ok = (int)($queueReport['ok'] ?? 0);
                    $neuFehler = (int)($queueReport['neu_fehler'] ?? 0);
                    $offenNachher = (int)($queueReport['offen_nachher'] ?? 0);
                    $dauerMs = (int)($queueReport['dauer_ms'] ?? 0);

                    $fehlerId = (int)($queueReport['fehler_id'] ?? 0);
                    $fehlerAktion = (string)($queueReport['fehler_aktion'] ?? '');
                    $fehlerNachricht = (string)($queueReport['fehler_nachricht'] ?? '');
                    if (mb_strlen($fehlerNachricht) > 180) {
                        $fehlerNachricht = mb_substr($fehlerNachricht, 0, 177) . '...';
                    }
                    ?>
                    <?php if ($versucht === 0): ?>
                        <strong>OK:</strong> Keine offenen Queue-Einträge vorhanden.
                    <?php else: ?>
                        <strong>OK:</strong>
                        Verarbeitung abgeschlossen:
                        versucht <?php echo $versucht; ?>,
                        OK <?php echo $ok; ?>,
                        Fehler <?php echo $neuFehler; ?>,
                        offen verbleibend <?php echo $offenNachher; ?>.
                        <?php if ($dauerMs > 0): ?>
                            <span style="color:#666;">(<?php echo $dauerMs; ?> ms)</span>
                        <?php endif; ?>

                        <?php if ($neuFehler > 0 && $fehlerId > 0): ?>
                            <div style="margin-top: 0.35rem;">
                                <strong>Fehler-Eintrag:</strong>
                                #<?php echo $fehlerId; ?>
                                <?php if ($fehlerAktion !== ''): ?>
                                    (<?php echo htmlspecialchars($fehlerAktion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                                <?php endif; ?>
                                – <?php echo htmlspecialchars($fehlerNachricht !== '' ? $fehlerNachricht : 'Details stehen beim Eintrag.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <strong>OK:</strong> Verarbeitung der offenen Queue-Einträge wurde angestoßen.
                <?php endif; ?>
            <?php elseif ($meldung === 'retry_ok'): ?>
                <strong>OK:</strong> Queue-Eintrag wurde erneut ausgeführt und als verarbeitet markiert.
            <?php elseif ($meldung === 'retry_fehler'): ?>
                <strong>Fehler:</strong> Retry fehlgeschlagen. Details stehen beim Eintrag.
            <?php elseif ($meldung === 'eintrag_nicht_gefunden'): ?>
                <strong>Fehler:</strong> Queue-Eintrag wurde nicht gefunden.
            <?php elseif ($meldung === 'hauptdb_offline'): ?>
                <strong>Fehler:</strong> Hauptdatenbank ist nicht erreichbar – Verarbeitung/Retry nicht möglich.
            <?php elseif ($meldung === 'csrf_ungueltig'): ?>
                <strong>Fehler:</strong> Sicherheits-Token (CSRF) ist ungültig. Bitte Seite neu laden und erneut versuchen.
            <?php else: ?>
                <?php echo htmlspecialchars($meldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=queue_admin&amp;status=<?php echo urlencode($aktuellerStatusFilter); ?>" style="margin-bottom: 1rem;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <input type="hidden" name="aktion" value="queue_verarbeiten">
        <button type="submit">Queue verarbeiten</button>
        <span style="margin-left: 0.6rem; font-size: 0.9rem; color: #555;">(offene Einträge jetzt gegen die Hauptdatenbank ausführen)</span>
    </form>

    <p style="font-size: 0.9rem; color: #555;">
        Hinweis: Einträge mit Status <strong>fehler</strong> blockieren das Terminal im Störungsmodus.
        Nach manueller Prüfung kann ein Admin den fehlerhaften Eintrag <strong>ignorieren/löschen</strong>, damit die Queue weiterlaufen kann.
    </p>

    <?php if (empty($eintraege)): ?>
        <p>Es wurden keine Einträge für den gewählten Status gefunden.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Erstellt am</th>
                <th>Letzte Ausführung</th>
                <th>Versuche</th>
                <th>SQL-Befehl (gekürzt)</th>
                <th>Fehlernachricht (gekürzt)</th>
                <th>Details</th>
                <?php if ($aktuellerStatusFilter === 'fehler'): ?>
                    <th>Aktion</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($eintraege as $eintrag): ?>
                <tr>
                    <td><?php echo (int)($eintrag['id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string)($eintrag['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($eintrag['erstellt_am'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($eintrag['letzte_ausfuehrung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo (int)($eintrag['versuche'] ?? 0); ?></td>
                    <td>
                        <?php
                        $sqlBefehl = (string)($eintrag['sql_befehl'] ?? '');
                        if (mb_strlen($sqlBefehl) > 120) {
                            $sqlBefehl = mb_substr($sqlBefehl, 0, 117) . '...';
                        }
                        echo htmlspecialchars($sqlBefehl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        ?>
                    </td>
                    <td>
                        <?php
                        $fehler = (string)($eintrag['fehlernachricht'] ?? '');
                        if (mb_strlen($fehler) > 120) {
                            $fehler = mb_substr($fehler, 0, 117) . '...';
                        }
                        echo htmlspecialchars($fehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        ?>
                    </td>

                    <td>
                        <?php
                        $vollSql = (string)($eintrag['sql_befehl'] ?? '');
                        $vollFehler = (string)($eintrag['fehlernachricht'] ?? '');
                        $metaMitarbeiterId = $eintrag['meta_mitarbeiter_id'] ?? null;
                        $metaTerminalId = $eintrag['meta_terminal_id'] ?? null;
                        $metaAktion = (string)($eintrag['meta_aktion'] ?? '');
                        ?>
                        <details>
                            <summary>anzeigen</summary>
                            <div style="margin-top: 0.4rem; font-size: 0.9rem;">
                                <div><strong>Meta Aktion:</strong> <?php echo htmlspecialchars($metaAktion !== '' ? $metaAktion : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                                <div><strong>Meta Mitarbeiter-ID:</strong> <?php echo $metaMitarbeiterId !== null ? (int)$metaMitarbeiterId : '-'; ?></div>
                                <div><strong>Meta Terminal-ID:</strong> <?php echo $metaTerminalId !== null ? (int)$metaTerminalId : '-'; ?></div>
                            </div>
                            <div style="margin-top: 0.6rem;">
                                <div style="font-weight: bold; margin-bottom: 0.2rem;">SQL (voll)</div>
                                <pre style="white-space: pre-wrap; max-height: 14rem; overflow: auto; background: #f7f7f7; padding: 0.5rem; border-radius: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($vollSql, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                            </div>
                            <?php if ($vollFehler !== ''): ?>
                                <div style="margin-top: 0.6rem;">
                                    <div style="font-weight: bold; margin-bottom: 0.2rem;">Fehler (voll)</div>
                                    <pre style="white-space: pre-wrap; max-height: 10rem; overflow: auto; background: #fff4f4; padding: 0.5rem; border-radius: 6px; border: 1px solid #f0caca;"><?php echo htmlspecialchars($vollFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                                </div>
                            <?php endif; ?>
                        </details>
                    </td>

                    <?php if ($aktuellerStatusFilter === 'fehler'): ?>
                        <td>
                            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                <form method="post" action="?seite=queue_admin&amp;status=fehler" style="margin:0;" onsubmit="return confirm('Diesen Queue-Eintrag jetzt erneut gegen die Hauptdatenbank ausführen?\n\nHinweis: Bei Erfolg wird der Eintrag als verarbeitet markiert.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <input type="hidden" name="aktion" value="retry">
                                    <input type="hidden" name="id" value="<?php echo (int)($eintrag['id'] ?? 0); ?>">
                                    <button type="submit">Retry</button>
                                </form>

                                <form method="post" action="?seite=queue_admin&amp;status=fehler" style="margin:0;" onsubmit="return confirm('Diesen Queue-Eintrag wirklich ignorieren/löschen?\n\nHinweis: Der Sachverhalt muss ggf. vorher manuell in der Hauptdatenbank nachgepflegt werden.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <input type="hidden" name="aktion" value="loeschen">
                                    <input type="hidden" name="id" value="<?php echo (int)($eintrag['id'] ?? 0); ?>">
                                    <button type="submit">Ignorieren/Löschen</button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
