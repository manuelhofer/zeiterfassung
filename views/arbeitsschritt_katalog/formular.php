<?php
declare(strict_types=1);
/**
 * Template: Arbeitsschritt-Katalog – Formular (Neu/Bearbeiten)
 *
 * Erwartet:
 * - $eintrag (array<string,mixed>)
 * - $csrf (string) – Token des Bereichs `arbeitsschritt_katalog`
 * - optional: $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<string,mixed> $eintrag */
$eintrag       = $eintrag ?? [];
$csrf          = (string)($csrf ?? '');
$fehlermeldung = $fehlermeldung ?? null;

$id          = (int)($eintrag['id'] ?? 0);
$code        = (string)($eintrag['code'] ?? '');
$bezeichnung = (string)($eintrag['bezeichnung'] ?? '');
$sortOrder   = (int)($eintrag['sort_order'] ?? 0);
$aktiv       = (int)($eintrag['aktiv'] ?? 1) === 1;

$esc = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section>
    <h2><?php echo $id > 0 ? 'Arbeitsschritt bearbeiten' : 'Arbeitsschritt anlegen'; ?></h2>

    <p><a class="button-link quiet" href="?seite=arbeitsschritt_katalog">&laquo; Zurueck zum Katalog</a></p>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <div class="error">
            <?php echo $esc($fehlermeldung); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=arbeitsschritt_katalog_speichern">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrf); ?>">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div style="margin-bottom:0.75rem;">
            <label for="code"><strong>Code</strong></label><br>
            <input type="text" id="code" name="code" required maxlength="100"
                   value="<?php echo $esc($code); ?>" style="width:100%;max-width:260px;">
            <br><small>
                Steht im Strichcode und wird am Terminal gescannt, z. B. <code>fraesen</code>.
                Kurz und eindeutig halten – der Code taucht in allen Auswertungen auf.
                <?php if ($id > 0): ?>
                    <br><strong>Achtung:</strong> Eine Aenderung erzeugt einen neuen Strichcode.
                    Bereits an Maschinen haengende Ausdrucke werden dadurch ungueltig.
                <?php endif; ?>
            </small>
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="bezeichnung"><strong>Bezeichnung</strong></label><br>
            <input type="text" id="bezeichnung" name="bezeichnung" maxlength="255"
                   value="<?php echo $esc($bezeichnung); ?>" style="width:100%;max-width:480px;">
            <br><small>Klartext fuer Ausdruck und Auswertung, z. B. „Fraesen“.</small>
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="sort_order"><strong>Sortierung</strong></label><br>
            <input type="number" id="sort_order" name="sort_order" value="<?php echo $sortOrder; ?>" style="width:100px;">
            <br><small>Kleinere Zahl steht weiter oben.</small>
        </div>

        <div style="margin-bottom:1rem;">
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Aktiv
            </label>
            <br><small>Inaktive Schritte stehen nicht mehr zur Auswahl und nicht auf dem Druckblatt. Bereits erfasste Buchungen bleiben unberuehrt.</small>
        </div>

        <div class="form-actions">
            <button type="submit">Speichern</button>
            <a class="button-link quiet" href="?seite=arbeitsschritt_katalog">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
