<?php
declare(strict_types=1);
/**
 * Template: Auftrag – Arbeitsschritt bearbeiten
 *
 * Erwartet (der Controller rechnet die Werte vor, siehe
 * `AuftragController::renderSchrittFormular()`):
 * - $id, $auftragId (int)
 * - $auftragsnummer, $code, $bezeichnung (string)
 * - $aktiv (bool)
 * - optional: $fehlermeldung (string|null)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich    = (string)($csrfBereich ?? '');
$id             = (int)($id ?? 0);
$auftragId      = (int)($auftragId ?? 0);
$auftragsnummer = (string)($auftragsnummer ?? '');
$code           = (string)($code ?? '');
$bezeichnung    = (string)($bezeichnung ?? '');
$aktiv          = (bool)($aktiv ?? true);
$fehlermeldung  = $fehlermeldung ?? null;

$esc = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section>
    <h2>Arbeitsschritt bearbeiten</h2>

    <p><a class="button-link quiet" href="?seite=auftrag_detail&amp;code=<?php echo urlencode($auftragsnummer); ?>">&laquo; Zurück zum Auftrag <?php echo $esc($auftragsnummer); ?></a></p>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <div class="error"><?php echo $esc($fehlermeldung); ?></div>
    <?php endif; ?>

    <form method="post" action="?seite=auftrag_schritt_speichern">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="schritt_id" value="<?php echo $id; ?>">
        <input type="hidden" name="auftrag_id" value="<?php echo $auftragId; ?>">

        <div style="margin-bottom:0.75rem;">
            <label for="arbeitsschritt_code"><strong>Code</strong></label><br>
            <input type="text" id="arbeitsschritt_code" name="arbeitsschritt_code" required maxlength="100"
                   value="<?php echo $esc($code); ?>" style="width:100%;max-width:260px;">
            <br><small>Änderungen erzeugen automatisch einen neuen Strichcode. Bereits gedruckte Laufkarten werden dadurch ungültig.</small>
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="bezeichnung"><strong>Bezeichnung</strong></label><br>
            <input type="text" id="bezeichnung" name="bezeichnung" maxlength="255"
                   value="<?php echo $esc($bezeichnung); ?>" style="width:100%;max-width:480px;">
        </div>

        <div style="margin-bottom:1rem;">
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Aktiv
            </label>
            <br><small>Inaktive Schritte erscheinen nicht auf der Laufkarte. Gelöscht wird nicht, damit vorhandene Buchungen zuordenbar bleiben.</small>
        </div>

        <div class="form-actions">
            <button type="submit">Speichern</button>
            <a class="button-link quiet" href="?seite=auftrag_detail&amp;code=<?php echo urlencode($auftragsnummer); ?>">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
