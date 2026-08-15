<?php
declare(strict_types=1);
/**
 * Teil-Template: Blätternavigation unter der Auftragsliste.
 *
 * Die Seitenzahlen stehen immer da. Die Sprungpfeile (Anfang, zurück, vor,
 * Ende) kommen erst ab `$mitPfeilen` dazu – bei vier Seiten sind alle Zahlen
 * ohnehin sichtbar.
 *
 * Erwartet **einen** Wert, `$blaetterdaten` – gerechnet von
 * `AuftragController::baueBlaetterdaten()`. Als Bündel statt als acht einzelne
 * Variablen, damit dieses Teil-Template nichts aus der Umgebung erbt, in die es
 * eingebunden wird:
 * - `seiteNr`, `seitenGesamt`, `treffer`, `von`, `bis` (int)
 * - `ersteZahl`, `letzteZahl` (int) – Fenster der gezeigten Seitenzahlen
 * - `mitPfeilen` (bool)
 * - `seitenUrls` (array<int,string>) – Seitenzahl => URL, unescaped
 */
/** @var array<string,mixed> $blaetterdaten */
$blaetterdaten = $blaetterdaten ?? [];

$seiteNr      = (int)($blaetterdaten['seiteNr'] ?? 1);
$seitenGesamt = (int)($blaetterdaten['seitenGesamt'] ?? 1);
$treffer      = (int)($blaetterdaten['treffer'] ?? 0);
$von          = (int)($blaetterdaten['von'] ?? 0);
$bis          = (int)($blaetterdaten['bis'] ?? 0);
$ersteZahl    = (int)($blaetterdaten['ersteZahl'] ?? 1);
$letzteZahl   = (int)($blaetterdaten['letzteZahl'] ?? 1);
$mitPfeilen   = (bool)($blaetterdaten['mitPfeilen'] ?? false);
/** @var array<int,string> $seitenUrls */
$seitenUrls   = $blaetterdaten['seitenUrls'] ?? [];

$url = static function (int $ziel) use ($seitenUrls): string {
    return htmlspecialchars((string)($seitenUrls[$ziel] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<nav class="pager">
    <span class="pager-info">
        <?php echo $treffer === 1
            ? '1 Auftrag'
            : $von . '&ndash;' . $bis . ' von ' . $treffer . ' Aufträgen'; ?>
    </span>

    <?php if ($seitenGesamt > 1): ?>
        <?php if ($mitPfeilen): ?>
            <?php if ($seiteNr > 1): ?>
                <a class="button-link quiet" href="<?php echo $url(1); ?>" title="Erste Seite">&laquo;</a>
                <a class="button-link quiet" href="<?php echo $url($seiteNr - 1); ?>" title="Eine Seite zurück">&lsaquo;</a>
            <?php else: ?>
                <span class="button-link disabled">&laquo;</span>
                <span class="button-link disabled">&lsaquo;</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($ersteZahl > 1): ?>
            <a class="button-link quiet" href="<?php echo $url(1); ?>">1</a>
            <?php if ($ersteZahl > 2): ?><span class="muted">&hellip;</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $ersteZahl; $i <= $letzteZahl; $i++): ?>
            <?php if ($i === $seiteNr): ?>
                <span class="button-link aktuell" aria-current="page"><?php echo $i; ?></span>
            <?php else: ?>
                <a class="button-link quiet" href="<?php echo $url($i); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($letzteZahl < $seitenGesamt): ?>
            <?php if ($letzteZahl < $seitenGesamt - 1): ?><span class="muted">&hellip;</span><?php endif; ?>
            <a class="button-link quiet" href="<?php echo $url($seitenGesamt); ?>"><?php echo $seitenGesamt; ?></a>
        <?php endif; ?>

        <?php if ($mitPfeilen): ?>
            <?php if ($seiteNr < $seitenGesamt): ?>
                <a class="button-link quiet" href="<?php echo $url($seiteNr + 1); ?>" title="Eine Seite vor">&rsaquo;</a>
                <a class="button-link quiet" href="<?php echo $url($seitenGesamt); ?>" title="Letzte Seite">&raquo;</a>
            <?php else: ?>
                <span class="button-link disabled">&rsaquo;</span>
                <span class="button-link disabled">&raquo;</span>
            <?php endif; ?>
        <?php endif; ?>

        <span class="muted"><small>Seite <?php echo $seiteNr; ?> von <?php echo $seitenGesamt; ?></small></span>
    <?php endif; ?>
</nav>
