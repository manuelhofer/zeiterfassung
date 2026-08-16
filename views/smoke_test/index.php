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
 * Jeder Block mit eigenen Daten steht in einem eigenen Teil-Template und
 * bekommt **ein** Bündel (T-105). Ohne Bündel bleiben nur die beiden Blöcke,
 * die keine eigenen Daten haben: die Abhängigkeitstabelle und die
 * Klick-Checkliste.
 *
 * Erwartet:
 * - $terminalLoginDaten, $pdfQuickDaten, $pdfSynthDaten, $pdfDbMultiDaten,
 *   $pdfDbListeDaten, $feiertagQuickDaten, $monatsrasterDaten,
 *   $monatsfallbackDaten, $doppelzaehlungDaten, $feiertagArbeitszeitDaten,
 *   $buchungssequenzDaten, $feiertagSeedDaten – je ein Bündel für sein
 *   Teil-Template, Inhalt dort dokumentiert
 * - $terminalConfigDaten, $queueDaten – dasselbe für die beiden Übersichten,
 *   die ohne POST laufen; dazu $smokeFlash (string|null) für die Meldung des
 *   letzten Queue-Roundtrips
 * - $checks (array) – die Tabelle der Abhängigkeits-Checks
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 *
 * **Warum hier keine `?? null`-Vorbelegung steht wie in den kleinen Masken:**
 * Der Controller setzt alle diese Werte ausnahmslos, bevor er die View
 * einbindet. Eine Vorbelegung würde einen Controller, der eine davon vergisst,
 * in eine still leere Kachel verwandeln statt in eine Meldung im Log. Vor
 * T-105 waren es rund sechzig lose Variablen, jetzt vierzehn Bündel – die
 * Begründung ist dieselbe geblieben.
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

    <?php require __DIR__ . '/terminal_login.php'; ?>

    <?php require __DIR__ . '/pdf_quick.php'; ?>

    <?php require __DIR__ . '/pdf_synth.php'; ?>

    <?php require __DIR__ . '/pdf_db_multipage.php'; ?>

    <?php require __DIR__ . '/feiertag_quick.php'; ?>

    <?php require __DIR__ . '/monatsraster.php'; ?>

    <?php require __DIR__ . '/monatsfallback.php'; ?>

    <?php require __DIR__ . '/doppelzaehlung.php'; ?>

    <?php require __DIR__ . '/feiertag_arbeitszeit.php'; ?>

    <?php require __DIR__ . '/buchungssequenz.php'; ?>

    <?php require __DIR__ . '/terminal_konfiguration.php'; ?>

    <?php require __DIR__ . '/offline_queue.php'; ?>


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
