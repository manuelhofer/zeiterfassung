<?php
declare(strict_types=1);
/**
 * Template: Auftrag anlegen/bearbeiten
 *
 * Erwartet (der Controller rechnet die Werte vor, siehe
 * `AuftragController::renderAuftragFormular()`):
 * - $id (int) – 0 beim Anlegen
 * - $auftragsnummer, $kurzbeschreibung, $kunde, $zeichnungsnummer,
 *   $status (string)
 * - $aktiv (bool)
 * - $statusAuswahl (array<string,string>) – Code => Beschriftung
 * - $katalogAuswahl (array<int,array<string,mixed>>) – nur beim Anlegen gefüllt
 * - $katalogAngehakt (array<int,mixed>) – angehakte Katalog-IDs als Schlüssel
 * - optional: $fehlermeldung (string|null)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich      = (string)($csrfBereich ?? '');
$id               = (int)($id ?? 0);
$auftragsnummer   = (string)($auftragsnummer ?? '');
$kurzbeschreibung = (string)($kurzbeschreibung ?? '');
$kunde            = (string)($kunde ?? '');
$zeichnungsnummer = (string)($zeichnungsnummer ?? '');
$status           = (string)($status ?? '');
$aktiv            = (bool)($aktiv ?? true);
/** @var array<string,string> $statusAuswahl */
$statusAuswahl    = $statusAuswahl ?? [];
/** @var array<int,array<string,mixed>> $katalogAuswahl */
$katalogAuswahl   = $katalogAuswahl ?? [];
/** @var array<int,mixed> $katalogAngehakt */
$katalogAngehakt  = $katalogAngehakt ?? [];
$fehlermeldung    = $fehlermeldung ?? null;

$esc = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section>
    <h2><?php echo $id > 0 ? 'Auftrag bearbeiten' : 'Auftrag anlegen'; ?></h2>

    <p><a class="button-link quiet" href="?seite=auftrag">&laquo; Zurück zur Liste</a></p>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <div class="error"><?php echo $esc($fehlermeldung); ?></div>
    <?php endif; ?>

    <form method="post" action="?seite=auftrag_speichern">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div style="margin-bottom:0.75rem;">
            <label for="auftragsnummer"><strong>Auftragsnummer</strong></label><br>
            <input type="text" id="auftragsnummer" name="auftragsnummer" required maxlength="100"
                   value="<?php echo $esc($auftragsnummer); ?>" style="width:100%;max-width:420px;">
            <br><small>Dieser Wert steht später im Strichcode und wird am Terminal gescannt.</small>
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="kunde"><strong>Kunde</strong></label><br>
            <input type="text" id="kunde" name="kunde" maxlength="255"
                   value="<?php echo $esc($kunde); ?>" style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="zeichnungsnummer"><strong>Zeichnungsnummer</strong></label><br>
            <input type="text" id="zeichnungsnummer" name="zeichnungsnummer" maxlength="100"
                   value="<?php echo $esc($zeichnungsnummer); ?>" style="width:100%;max-width:420px;">
            <br><small>Freiwillig. Wird von der Suche mitgefunden und steht auf der Laufkarte.</small>
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="kurzbeschreibung"><strong>Kurzbeschreibung</strong></label><br>
            <input type="text" id="kurzbeschreibung" name="kurzbeschreibung" maxlength="255"
                   value="<?php echo $esc($kurzbeschreibung); ?>" style="width:100%;max-width:620px;">
        </div>

        <div style="margin-bottom:0.75rem;">
            <label for="status"><strong>Status</strong></label><br>
            <select id="status" name="status" style="width:100%;max-width:260px;">
                <option value=""<?php echo $status === '' ? ' selected' : ''; ?>>(kein Status)</option>
                <?php foreach ($statusAuswahl as $wert => $beschriftung): ?>
                    <option value="<?php echo $esc($wert); ?>"<?php echo $status === $wert ? ' selected' : ''; ?>>
                        <?php echo $esc($beschriftung); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($status !== '' && !isset($statusAuswahl[$status])): ?>
                    <?php /* Altbestand: frei eingetippte Werte bleiben wählbar, statt still zu verschwinden. */ ?>
                    <option value="<?php echo $esc($status); ?>" selected><?php echo $esc($status); ?> (Altwert)</option>
                <?php endif; ?>
            </select>
            <br><small>Freiwillige Angabe. Der Status in der Auftragsliste wird ohnehin aus den Buchungen berechnet.</small>
        </div>

        <div style="margin-bottom:1rem;">
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Aktiv
            </label>
        </div>

        <?php if ($id === 0 && count($katalogAuswahl) > 0): ?>
            <div class="admin-card" style="margin:0.75rem 0;max-width:640px;">
                <strong>Arbeitsschritte aus dem Katalog</strong>
                <p style="margin:0.4rem 0;"><small>
                    Was hier angehakt ist, hängt beim Speichern gleich am Auftrag und
                    steht auf der Laufkarte. Später ändern geht in der Auftragsansicht.
                    <a href="?seite=arbeitsschritt_katalog">Katalog pflegen</a>
                </small></p>
                <?php foreach ($katalogAuswahl as $kat): ?>
                    <?php
                        $katId   = (int)($kat['id'] ?? 0);
                        $katCode = (string)($kat['code'] ?? '');
                        $katBez  = trim((string)($kat['bezeichnung'] ?? ''));
                    ?>
                    <label style="display:block;margin:0.15rem 0;">
                        <input type="checkbox" name="katalog_ids[]" value="<?php echo $katId; ?>"<?php echo isset($katalogAngehakt[$katId]) ? ' checked' : ''; ?>>
                        <code><?php echo $esc($katCode); ?></code>
                        <?php if ($katBez !== ''): ?>
                            &ndash; <?php echo $esc($katBez); ?>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit">Speichern</button>
            <a class="button-link quiet" href="?seite=auftrag">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
