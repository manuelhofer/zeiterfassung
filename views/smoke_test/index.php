<?php
declare(strict_types=1);
/**
 * Template: Smoke-Test (Diagnose)
 *
 * Eine Seite, fünfzehn voneinander unabhängige Checks. Der Controller führt
 * jeden Check aus und legt sein Ergebnis in einem eigenen Satz Variablen ab –
 * hier wird nur angezeigt (siehe `SmokeTestController::index()`).
 *
 * Jeder Check bringt dieselben drei Sorten Werte mit:
 * - `…Ergebnis` (array|null) – `null` heisst „noch nicht gelaufen", die
 *   Ergebnisanzeige bleibt dann weg,
 * - `…Hinweis` (string|null) – Fehlertext **statt** eines Ergebnisses,
 * - die Formularwerte, mit denen der Check zuletzt lief.
 *
 * Alle Checks mit Formular bis auf den Terminal-Login stehen in eigenen
 * Teil-Templates und bekommen je **ein** Bündel (T-105); die Blöcke ohne POST
 * folgen noch.
 *
 * Erwartet:
 * - $terminalLoginCode, $terminalLoginErgebnis, $terminalLoginHinweis
 * - $pdfQuickDaten, $pdfSynthDaten, $pdfDbMultiDaten, $pdfDbListeDaten,
 *   $feiertagQuickDaten, $monatsrasterDaten, $monatsfallbackDaten,
 *   $doppelzaehlungDaten, $feiertagArbeitszeitDaten, $buchungssequenzDaten,
 *   $feiertagSeedDaten – je ein Bündel für sein Teil-Template, Inhalt dort
 *   dokumentiert
 * - $terminalConfig (array|null), $terminalConfigHinweis (string|null)
 * - $queueUebersicht (array|null), $queueHinweis, $smokeFlash (string|null)
 * - $checks (array) – die Tabelle der Abhängigkeits-Checks
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 *
 * **Warum hier keine `?? null`-Vorbelegung steht wie in den kleinen Masken:**
 * Der Controller setzt alle diese Variablen ausnahmslos, bevor er die View
 * einbindet. Eine Vorbelegung wären knapp sechzig Zeilen, die bei jeder
 * Änderung am Controller mitgepflegt werden müssten – und sie würde einen
 * Controller, der eine davon vergisst, in eine still leere Kachel verwandeln
 * statt in eine Meldung im Log.
 */
require __DIR__ . '/../layout/header.php';
?>
<section>
    <h2>Smoke-Test (Diagnose)</h2>
    <p>
        Diese Seite führt <strong>nur lesende</strong> Checks aus. Sie ändert keine Zeiten und startet keine Queue.
        Optional kann sie PDFs <strong>im Speicher</strong> erzeugen, um die PDF-Erzeugung auf Validität (Header/EOF) zu prüfen.
    </p>

    <p>
        <a href="?seite=dashboard">&laquo; Zurück zum Dashboard</a>
    </p>

    <h3>Terminal-Login-Check (RFID / Personalnummer / ID)</h3>
    <p>
        Dieser Check ist <strong>rein lesend</strong> und emuliert die Login-Reihenfolge des Terminals.
        Damit lässt sich schnell prüfen, ob ein Code in der Datenbank zu einem <strong>aktiven</strong> Mitarbeiter auflöst.
    </p>

    <form method="post" action="?seite=smoke_test" style="margin: 0 0 12px 0;">
        <label for="terminal_login_code"><strong>Code</strong>:</label>
        <input type="text" id="terminal_login_code" name="terminal_login_code"
               value="<?php echo htmlspecialchars($terminalLoginCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
               style="min-width: 260px;">
        <button type="submit">Prüfen</button>
    </form>

    <?php if ($terminalLoginHinweis !== null && $terminalLoginHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($terminalLoginHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/pdf_quick.php'; ?>

    <?php require __DIR__ . '/pdf_synth.php'; ?>

    <?php require __DIR__ . '/pdf_db_multipage.php'; ?>

    <?php require __DIR__ . '/feiertag_quick.php'; ?>

    <?php require __DIR__ . '/monatsraster.php'; ?>

    <?php require __DIR__ . '/monatsfallback.php'; ?>

    <?php require __DIR__ . '/doppelzaehlung.php'; ?>

    <?php require __DIR__ . '/feiertag_arbeitszeit.php'; ?>

    <?php require __DIR__ . '/buchungssequenz.php'; ?>

    <?php if (is_array($terminalLoginErgebnis)):
        $typ = $terminalLoginErgebnis['typ'] ?? null;
        $m = $terminalLoginErgebnis['mitarbeiter'] ?? null;
        $warn = $terminalLoginErgebnis['warnungen'] ?? [];
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
                $a = $terminalLoginErgebnis['anwesenheit'] ?? null;
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
                $fc = (string)($terminalLoginErgebnis['fehler_code'] ?? '');
                if ($fc === 'MEHRDEUTIG'):
                    ?>
                    <p style="margin:0;"><strong>BLOCK:</strong> Mehrdeutiger numerischer Code – Terminal würde den Login abbrechen.</p>
                <?php else: ?>
                    <p style="margin:0;"><strong>FAIL:</strong> Kein aktiver Mitarbeiter für diesen Code gefunden.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            $alts = $terminalLoginErgebnis['alternativen'] ?? [];
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


    
    <h3>Terminal-Konfiguration (Config-Keys)</h3>
    <p>
        Das Terminal liest bestimmte Einstellungen aus der Tabelle <code>config</code>.
        Wenn ein Key fehlt oder ein ungültiger Wert gespeichert ist, wird im Terminal automatisch auf Default-Werte zurückgefallen.
    </p>

    <?php if (is_string($terminalConfigHinweis) && $terminalConfigHinweis !== ''): ?>
        <div style="padding:10px; background:#ffebee; border:1px solid #c62828; margin-bottom: 12px;">
            <?php echo htmlspecialchars($terminalConfigHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($terminalConfig) && count($terminalConfig) > 0): ?>
        <table style="margin-bottom: 16px;">
            <thead>
            <tr>
                <th>Key</th>
                <th>Beschreibung</th>
                <th>DB-Wert</th>
                <th>Effektiv</th>
                <th>Status</th>
                <th>Range</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($terminalConfig as $row):
                if (!is_array($row)) { continue; }
                $st = (string)($row['status'] ?? 'default');
                $statusText = ($st === 'ok') ? 'OK' : (($st === 'invalid') ? 'INVALID → Default' : 'Nicht gesetzt → Default');
                $style = ($st === 'ok') ? 'background:#e8f5e9;' : (($st === 'invalid') ? 'background:#ffebee;' : 'background:#fffde7;');
                $raw = $row['raw'] ?? null;
                $rawText = ($raw === null || trim((string)$raw) === '') ? '—' : (string)$raw;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($row['key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['titel'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><strong><?php echo (int)($row['effective'] ?? 0); ?></strong> s</td>
                    <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo (int)($row['min'] ?? 0); ?>–<?php echo (int)($row['max'] ?? 0); ?> s (Default: <?php echo (int)($row['default'] ?? 0); ?> s)</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:-6px;">
            <a href="?seite=konfiguration_admin">Konfiguration öffnen</a>
        </p>
    <?php else: ?>
        <p><em>Keine Terminal-Konfigdaten verfügbar (DB nicht erreichbar oder Tabelle <code>config</code> fehlt).</em></p>
    <?php endif; ?>

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

    <?php if (is_string($queueHinweis) && $queueHinweis !== ''): ?>
        <div style="padding:10px; background:#fffde7; border:1px solid #fbc02d; margin-bottom: 12px;">
            <?php echo htmlspecialchars($queueHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($queueUebersicht) && is_array($queueUebersicht['counts'] ?? null)):
        $qQuelle = (string)($queueUebersicht['quelle'] ?? '');
        $qQuelleLabel = ($qQuelle === 'offline') ? 'Offline-DB' : 'Haupt-DB';
        $qc = (array)$queueUebersicht['counts'];
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

            <?php $latest = $queueUebersicht['latest'] ?? []; ?>
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


    <table>
        <thead>
        <tr>
            <th>Gruppe</th>
            <th>Check</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $c):
            $ok = $c['ok'];
            $status = ($ok === true) ? 'OK' : (($ok === false) ? 'FEHLT/FAIL' : 'n/a');
            $style = ($ok === true) ? 'background:#e8f5e9;' : (($ok === false) ? 'background:#ffebee;' : 'background:#fffde7;');
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$c['gruppe'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$c['titel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$c['details'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php require __DIR__ . '/feiertag_seed.php'; ?>

    <h3>Manuelle Klick-Checkliste (danach Bugfixes ableiten)</h3>
    <ul>
        <li><strong>Backend Login</strong>: Einloggen, Dashboard öffnen.</li>
        <li><strong>Terminal Health</strong>: <code>terminal.php?aktion=health</code> (JSON) aufrufen.</li>
        <li><strong>Terminal Kommen/Gehen</strong>: einmal stempeln (online), dann DB kurz weg und erneut (Offline-Queue), danach Queue im Backend verarbeiten.</li>
        <li><strong>Auftrag Start/Stop</strong>: Hauptauftrag starten/stoppen (online + offline).</li>
        <li><strong>Monatsreport</strong>: Monatsübersicht öffnen, PDF erzeugen, ggf. Sammel-Export.</li>
        <li><strong>PDF-Quick-Check</strong>: Hier im Smoke-Test Jahr/Monat wählen und prüfen (Header/EOF).</li>
    </ul>
</section>
<?php
require __DIR__ . '/../layout/footer.php';
