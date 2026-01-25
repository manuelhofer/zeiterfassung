<?php
declare(strict_types=1);

/**
 * Terminal Partial: Liste der letzten Urlaubsanträge (inkl. Storno-Button).
 *
 * Erwartet:
 * - $urlaubsantraege (array<int,array<string,mixed>>)
 * - $csrfToken (string)  (wird defensiv aus Session ergänzt)
 *
 * Optional:
 * - $urlaubListeOpen  (bool)   → <details> standardmäßig offen
 * - $urlaubListeTitel (string) → Überschrift in <summary>
 */

$urlaubsantraege = $urlaubsantraege ?? [];
if (!is_array($urlaubsantraege)) {
    $urlaubsantraege = [];
}

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

// Defensive: falls eine View den Token nicht durchreicht, versuchen wir, ihn aus der Session zu laden.
if (($csrfToken === '' || strlen($csrfToken) < 20) && session_status() === PHP_SESSION_ACTIVE) {
    $sessToken = $_SESSION['terminal_csrf_token'] ?? '';
    if (is_string($sessToken) && strlen($sessToken) >= 20) {
        $csrfToken = $sessToken;
    }
}

$urlaubListeOpen = isset($urlaubListeOpen) ? (bool)$urlaubListeOpen : true;
$urlaubListeTitel = (isset($urlaubListeTitel) && is_string($urlaubListeTitel) && $urlaubListeTitel !== '')
    ? $urlaubListeTitel
    : 'Meine Urlaubsanträge (letzte 12)';

// Terminal-UX: Vorjahres-Anträge mit Status "genehmigt" sind in der Regel nicht mehr relevant.
// Diese werden daher aus der Terminal-Liste ausgeblendet.
$tz = new DateTimeZone('Europe/Berlin');
$jetzt = new DateTimeImmutable('now', $tz);
$jahrStart = $jetzt->setDate((int)$jetzt->format('Y'), 1, 1)->setTime(0, 0, 0);

?>

<details class="urlaub-liste"<?php echo $urlaubListeOpen ? ' open' : ''; ?>>
    <summary class="status-small"><strong><?php echo htmlspecialchars($urlaubListeTitel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></summary>

    <?php if (empty($urlaubsantraege)): ?>
        <div class="status-small mt-05">Noch keine Urlaubsanträge vorhanden.</div>
    <?php else: ?>
        <?php foreach ($urlaubsantraege as $ua): ?>
            <?php
                $id = isset($ua['id']) ? (int)$ua['id'] : 0;
                $status = isset($ua['status']) ? (string)$ua['status'] : '';

                $von = isset($ua['von_datum']) ? (string)$ua['von_datum'] : '';
                $bis = isset($ua['bis_datum']) ? (string)$ua['bis_datum'] : '';
                $vonFmt = $von;
                $bisFmt = $bis;
                try {
                    if ($von !== '') {
                        $vonFmt = (new DateTimeImmutable($von))->format('d.m.Y');
                    }
                } catch (Throwable $e) {
                    $vonFmt = $von;
                }
                try {
                    if ($bis !== '') {
                        $bisFmt = (new DateTimeImmutable($bis))->format('d.m.Y');
                    }
                } catch (Throwable $e) {
                    $bisFmt = $bis;
                }

                $tage = isset($ua['tage_gesamt']) ? (float)$ua['tage_gesamt'] : 0.0;
                $tageFmt = number_format($tage, 2, ',', '.');

                $antragsDatum = isset($ua['antrags_datum']) ? (string)$ua['antrags_datum'] : '';
                $antragsFmt = $antragsDatum;
                try {
                    if ($antragsDatum !== '') {
                        $antragsFmt = (new DateTimeImmutable($antragsDatum))->format('d.m.Y H:i');
                    }
                } catch (Throwable $e) {
                    $antragsFmt = $antragsDatum;
                }

                $statusCss = in_array($status, ['offen', 'genehmigt', 'abgelehnt', 'storniert'], true) ? $status : '';

                // Vorjahres-Anträge mit Status "genehmigt" ausblenden (Terminal-Übersicht soll fokussiert bleiben).
                $bisDt = null;
                try {
                    if ($bis !== '') {
                        $bisDt = new DateTimeImmutable($bis, $tz);
                    }
                } catch (Throwable $e) {
                    $bisDt = null;
                }
                if ($status === 'genehmigt' && $bisDt instanceof DateTimeImmutable && $bisDt < $jahrStart) {
                    continue;
                }
            ?>

            <?php if ($status === 'genehmigt'): ?>
                <div class="urlaub-row">
                    <details class="urlaub-row-details">
                        <summary class="status-small">
                            <strong><?php echo htmlspecialchars($vonFmt . ' – ' . $bisFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            <span class="urlaub-meta">(<?php echo htmlspecialchars((string)$tageFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage)</span>
                            <span class="status-label genehmigt">genehmigt</span>
                        </summary>
                        <div class="status-small mt-015">
                            Antrag: <?php echo htmlspecialchars((string)$antragsFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            &nbsp;|&nbsp; ID: <?php echo (int)$id; ?>
                        </div>
                    </details>
                </div>
            <?php else: ?>
                <div class="urlaub-row">
                    <div>
                        <div>
                            <strong><?php echo htmlspecialchars($vonFmt . ' – ' . $bisFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            <span class="urlaub-meta">(<?php echo htmlspecialchars((string)$tageFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage)</span>
                        </div>
                        <div class="status-small mt-015">
                            Status:
                            <span class="status-label <?php echo htmlspecialchars($statusCss, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars($status !== '' ? $status : 'unbekannt', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            &nbsp;|&nbsp; Antrag: <?php echo htmlspecialchars((string)$antragsFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            &nbsp;|&nbsp; ID: <?php echo (int)$id; ?>
                        </div>
                    </div>

                    <?php if ($status === 'offen' && $id > 0): ?>
                        <form method="post" action="terminal.php?aktion=urlaub_stornieren" data-confirm="Urlaubsantrag wirklich stornieren?">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <input type="hidden" name="urlaubsantrag_id" value="<?php echo (int)$id; ?>">
                            <button type="submit" class="small-button danger">Stornieren</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</details>
