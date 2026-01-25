<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Rundungsregel – Formular (Neu/Bearbeiten)
 *
 * Erwartet:
 * - $regel (array<string,mixed>)
 * - optional: $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$regel        = is_array($regel ?? null) ? $regel : [];
$fehlermeldung = $fehlermeldung ?? null;

$id          = (int)($regel['id'] ?? 0);
$von         = trim((string)($regel['von_uhrzeit'] ?? ''));
$bis         = trim((string)($regel['bis_uhrzeit'] ?? ''));
$einheit     = (int)($regel['einheit_minuten'] ?? 15);
$richtung    = trim((string)($regel['richtung'] ?? 'naechstgelegen'));
$giltFuer    = trim((string)($regel['gilt_fuer'] ?? 'beide'));
$prio        = (int)($regel['prioritaet'] ?? 1);
$aktiv       = (int)($regel['aktiv'] ?? 1) === 1;
$beschreibung= (string)($regel['beschreibung'] ?? '');

// HTML time input: HH:MM
if ($von !== '' && strlen($von) >= 5) {
    $von = substr($von, 0, 5);
}
if ($bis !== '' && strlen($bis) >= 5) {
    $bis = substr($bis, 0, 5);
}

$richtungOptionen = [
    'naechstgelegen' => 'Nächstgelegen',
    'auf'           => 'Aufrunden',
    'ab'            => 'Abrunden',
];

$giltFuerOptionen = [
    'beide' => 'Kommen & Gehen',
    'kommen'=> 'Nur Kommen',
    'gehen' => 'Nur Gehen',
];
?>

<section>
    <h2><?php echo $id > 0 ? 'Rundungsregel bearbeiten' : 'Neue Rundungsregel anlegen'; ?></h2>

    <p style="margin-top:0.25rem;color:#555;">
        Zeitbereich gilt <strong>inklusive Start</strong> und <strong>exklusiv Ende</strong>.
        Regeln werden nach <strong>Priorität</strong> (kleinste zuerst) geprüft; die <strong>erste passende</strong> Regel wird angewendet.
    </p>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <p style="color:red;">
            <?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="post" action="?seite=zeit_rundungsregel_admin_bearbeiten">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

        <div>
            <label for="von_uhrzeit">Von *</label><br>
            <input type="time" id="von_uhrzeit" name="von_uhrzeit" required value="<?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label for="bis_uhrzeit">Bis *</label><br>
            <input type="time" id="bis_uhrzeit" name="bis_uhrzeit" required value="<?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label for="einheit_minuten">Einheit (Minuten) *</label><br>
            <input type="number" id="einheit_minuten" name="einheit_minuten" min="1" max="1440" required
                   value="<?php echo htmlspecialchars((string)$einheit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label for="richtung">Richtung *</label><br>
            <select id="richtung" name="richtung" required>
                <?php foreach ($richtungOptionen as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo ($richtung === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="gilt_fuer">Gilt für *</label><br>
            <select id="gilt_fuer" name="gilt_fuer" required>
                <?php foreach ($giltFuerOptionen as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo ($giltFuer === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="prioritaet">Priorität *</label><br>
            <input type="number" id="prioritaet" name="prioritaet" min="1" max="9999" required
                   value="<?php echo htmlspecialchars((string)$prio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Regel ist aktiv
            </label>
        </div>

        <div>
            <label for="beschreibung">Beschreibung</label><br>
            <input type="text" id="beschreibung" name="beschreibung" maxlength="255"
                   value="<?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div style="margin-top:1em;">
            <button type="submit">Speichern</button>
            <a href="?seite=zeit_rundungsregel_admin">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
