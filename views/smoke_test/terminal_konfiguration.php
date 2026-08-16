<?php
declare(strict_types=1);
/**
 * Teil-Template: Terminal-Konfiguration (Config-Keys).
 *
 * Erwartet **einen** Wert, `$terminalConfigDaten` – das Bündel von
 * `SmokeTestController::holeTerminalKonfiguration()`:
 * - `zeilen` (array|null) – je Schlüssel gespeicherter Wert, effektiver Wert,
 *   Status und Grenzen; `null` heißt „nicht lesbar"
 * - `hinweis` (string|null) – Fehlertext, wenn das Lesen gescheitert ist
 *
 * Dieser Block hat kein Formular: Er läuft bei jedem Aufruf der Seite.
 */
/** @var array<string,mixed> $terminalConfigDaten */
$zeilen  = $terminalConfigDaten['zeilen'] ?? null;
$hinweis = $terminalConfigDaten['hinweis'] ?? null;
?>
<h3>Terminal-Konfiguration (Config-Keys)</h3>
<p>
    Das Terminal liest bestimmte Einstellungen aus der Tabelle <code>config</code>.
    Wenn ein Key fehlt oder ein ungültiger Wert gespeichert ist, wird im Terminal automatisch auf Default-Werte zurückgefallen.
</p>

<?php if (is_string($hinweis) && $hinweis !== ''): ?>
    <div style="padding:10px; background:#ffebee; border:1px solid #c62828; margin-bottom: 12px;">
        <?php echo htmlspecialchars($hinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (is_array($zeilen) && count($zeilen) > 0): ?>
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
        <?php foreach ($zeilen as $row):
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
