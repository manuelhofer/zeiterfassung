<?php
declare(strict_types=1);
/**
 * Template: Kurzarbeit – Formular (Anlegen/Bearbeiten)
 *
 * Erwartet:
 * - $plan (array<string,mixed>)
 * - $mitarbeiterListe (array<int,array{id:int,name:string}>)
 * - $mask (int) – Wochentage als Bitmaske, bereits auf 0..127 begrenzt
 * - $id (int) – 0 beim Anlegen
 * - $csrfToken (string)
 * - optional: $flash (array{ok?:string,err?:string})
 */
require __DIR__ . '/../layout/header.php';

/** @var array<string,mixed> $plan */
$plan             = $plan ?? [];
/** @var array<int,array<string,mixed>> $mitarbeiterListe */
$mitarbeiterListe = $mitarbeiterListe ?? [];
$mask             = (int)($mask ?? 31);
$id               = (int)($id ?? 0);
$csrfToken        = (string)($csrfToken ?? '');
$flash            = $flash ?? [];
?>
<section>
    <h2><?php echo $id > 0 ? 'Kurzarbeit-Plan bearbeiten' : 'Kurzarbeit-Plan anlegen'; ?></h2>

    <?php if (!empty($flash['ok'])): ?>
        <div class="erfolgsmeldung"><?php echo htmlspecialchars((string)$flash['ok'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['err'])): ?>
        <div class="fehlermeldung"><?php echo htmlspecialchars((string)$flash['err'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="?seite=kurzarbeit_admin_speichern">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int)($plan['id'] ?? 0); ?>">

        <p>
            <label for="scope">Gültigkeit (Scope)</label><br>
            <select id="scope" name="scope">
                <option value="firma" <?php echo ((string)($plan['scope'] ?? '') === 'firma') ? 'selected' : ''; ?>>Firma (alle)</option>
                <option value="mitarbeiter" <?php echo ((string)($plan['scope'] ?? '') === 'mitarbeiter') ? 'selected' : ''; ?>>Mitarbeiter</option>
            </select>
        </p>

        <p id="mitarbeiter_row">
            <label for="mitarbeiter_id">Mitarbeiter</label><br>
            <select id="mitarbeiter_id" name="mitarbeiter_id">
                <option value="0">-- bitte wählen --</option>
                <?php foreach ($mitarbeiterListe as $m): ?>
                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)($plan['mitarbeiter_id'] ?? 0) === (int)$m['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="von_datum">Von</label><br>
            <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars((string)($plan['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </p>

        <p>
            <label for="bis_datum">Bis</label><br>
            <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars((string)($plan['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </p>

        <fieldset style="max-width:32rem;">
            <legend>Wochentage</legend>
            <?php
            $tage = [
                1 => 'Mo',
                2 => 'Di',
                3 => 'Mi',
                4 => 'Do',
                5 => 'Fr',
                6 => 'Sa',
                7 => 'So',
            ];
            foreach ($tage as $n => $label):
                $bit = 1 << ($n - 1);
                $checked = (($mask & $bit) !== 0);
                ?>
                <label style="display:inline-block;margin-right:0.6rem;">
                    <input type="checkbox" name="wochentage[]" value="<?php echo (int)$n; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </label>
            <?php endforeach; ?>
            <div class="muted" style="margin-top:0.35rem;font-size:0.9em;">Default: Mo-Fr</div>
        </fieldset>

        <p>
            <label for="modus">Modus</label><br>
            <select id="modus" name="modus">
                <option value="stunden" <?php echo ((string)($plan['modus'] ?? '') === 'stunden') ? 'selected' : ''; ?>>Stunden</option>
                <option value="prozent" <?php echo ((string)($plan['modus'] ?? '') === 'prozent') ? 'selected' : ''; ?>>Prozent vom Tages-Soll</option>
            </select>
        </p>

        <p>
            <label for="wert">Wert</label><br>
            <input type="number" step="0.01" min="0" id="wert" name="wert" value="<?php echo htmlspecialchars((string)($plan['wert'] ?? '0.00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </p>

        <p>
            <label for="kommentar">Kommentar (optional)</label><br>
            <input type="text" id="kommentar" name="kommentar" value="<?php echo htmlspecialchars((string)($plan['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" maxlength="255" style="width:100%;max-width:48rem;">
        </p>

        <p>
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($plan['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                Aktiv
            </label>
        </p>

        <p>
            <button type="submit">Speichern</button>
            <a href="?seite=kurzarbeit_admin" style="margin-left:0.75rem;">Zurück zur Liste</a>
        </p>
    </form>

    <script>
        (function () {
            var scope = document.getElementById('scope');
            var row = document.getElementById('mitarbeiter_row');
            function sync() {
                var isFirma = scope && scope.value === 'firma';
                if (row) {
                    row.style.display = isFirma ? 'none' : '';
                }
            }
            if (scope) {
                scope.addEventListener('change', sync);
            }
            sync();
        })();
    </script>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
