<?php
declare(strict_types=1);
/**
 * Template: Konfiguration – Übersicht aller Einträge der `config`-Tabelle
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>)
 * - optional: $ok (int) – 1 zeigt „Gespeichert."
 * - optional: $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $eintraege */
$eintraege     = $eintraege ?? [];
$ok            = (int)($ok ?? 0);
$fehlermeldung = $fehlermeldung ?? null;
?>
<section>
    <h2>Konfiguration</h2>

    <p style="margin-top:0.25rem;">
        <a href="?seite=konfiguration_admin">Konfiguration</a>
        | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
        | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
        | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
        | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
    </p>

    <p>
        <a href="?seite=konfiguration_admin_bearbeiten">Neuen Eintrag anlegen</a>
    </p>

    <?php if ($ok === 1): ?>
        <div class="erfolgsmeldung">Gespeichert.</div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Einträge vorhanden" wäre daneben
             die falsche Auskunft. */ ?>
    <?php if (count($eintraege) === 0): ?>
        <?php if (empty($fehlermeldung)): ?>
            <p>Es sind derzeit keine Konfigurationseinträge vorhanden.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Schlüssel</th>
                    <th>Wert</th>
                    <th>Typ</th>
                    <th>Beschreibung</th>
                    <th>Geändert</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eintraege as $e): ?>
                    <?php
                        $schluessel    = (string)($e['schluessel'] ?? '');
                        $wert          = $e['wert'] ?? null;
                        $typ           = (string)($e['typ'] ?? '');
                        $beschreibung  = (string)($e['beschreibung'] ?? '');
                        $geaendertAm   = (string)($e['geaendert_am'] ?? '');

                        $wertText = $wert !== null ? (string)$wert : '';
                        $wertKurz = mb_strlen($wertText) > 80 ? (mb_substr($wertText, 0, 80) . '…') : $wertText;
                        $beschreibungKurz = mb_strlen($beschreibung) > 80 ? (mb_substr($beschreibung, 0, 80) . '…') : $beschreibung;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($schluessel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><code><?php echo htmlspecialchars($wertKurz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($beschreibungKurz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($geaendertAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <a href="?seite=konfiguration_admin_bearbeiten&amp;schluessel=<?php echo urlencode($schluessel); ?>">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="muted" style="margin-top:0.75rem;">
        Hinweis: Änderungen wirken sofort. Defaults werden automatisch über <code>DefaultsSeeder</code> angelegt, falls Einträge fehlen.
    </p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
