<?php
declare(strict_types=1);
/**
 * Template: Konfiguration – Formular (Anlegen/Bearbeiten) eines `config`-Eintrags
 *
 * Erwartet:
 * - $datensatz (array<string,mixed>) – schluessel, wert, typ, beschreibung,
 *   erstellt_am, geaendert_am
 * - $istBearbeiten (bool) – true, wenn ein bestehender Schlüssel geöffnet wurde
 * - $schluesselGet (string) – der Schlüssel aus der URL, für das Ziel des Formulars
 * - optional: $fehlermeldung (string|null)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich   = (string)($csrfBereich ?? '');
/** @var array<string,mixed> $datensatz */
$datensatz     = $datensatz ?? [];
$istBearbeiten = (bool)($istBearbeiten ?? false);
$schluesselGet = (string)($schluesselGet ?? '');
$fehlermeldung = $fehlermeldung ?? null;

$schluessel   = (string)($datensatz['schluessel'] ?? '');
$wert         = (string)($datensatz['wert'] ?? '');
$typ          = (string)($datensatz['typ'] ?? '');
$beschreibung = (string)($datensatz['beschreibung'] ?? '');
$erstelltAm   = (string)($datensatz['erstellt_am'] ?? '');
$geaendertAm  = (string)($datensatz['geaendert_am'] ?? '');
?>
<section>
    <h2><?php echo $istBearbeiten ? 'Konfiguration bearbeiten' : 'Konfiguration anlegen'; ?></h2>

    <p>
        <a href="?seite=konfiguration_admin">&laquo; Zurück zur Übersicht</a>
    </p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=konfiguration_admin_bearbeiten<?php echo $istBearbeiten ? '&amp;schluessel=' . urlencode($schluesselGet) : ''; ?>">
        <?php echo Csrf::feld($csrfBereich); ?>
        <div style="display:flex; flex-direction:column; gap:0.6rem; max-width:900px;">
            <label>
                Schlüssel
                <input
                    type="text"
                    name="schluessel"
                    value="<?php echo htmlspecialchars($schluessel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    <?php echo $istBearbeiten ? 'readonly' : ''; ?>
                    style="width:100%; padding:0.45rem;"
                    maxlength="190"
                    required
                >
                <?php if ($istBearbeiten): ?>
                    <small>Der Schlüssel ist bei bestehenden Einträgen gesperrt.</small>
                <?php else: ?>
                    <small>Beispiel: <code>terminal_timeout_standard</code></small>
                <?php endif; ?>
            </label>

            <label>
                Typ (optional)
                <input
                    type="text"
                    name="typ"
                    value="<?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    style="width:100%; padding:0.45rem;"
                    maxlength="50"
                    placeholder="z.B. int / bool / string"
                >
            </label>

            <label>
                Wert
                <textarea
                    name="wert"
                    rows="5"
                    style="width:100%; padding:0.45rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"
                ><?php echo htmlspecialchars($wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
            </label>

            <label>
                Beschreibung (optional)
                <textarea
                    name="beschreibung"
                    rows="3"
                    style="width:100%; padding:0.45rem;"
                ><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
            </label>

            <?php if ($erstelltAm !== '' || $geaendertAm !== ''): ?>
                <div class="muted" style="font-size:0.9rem;">
                    <?php if ($erstelltAm !== ''): ?>
                        Erstellt: <?php echo htmlspecialchars($erstelltAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    <?php endif; ?>
                    <?php if ($geaendertAm !== ''): ?>
                        &nbsp;|&nbsp; Geändert: <?php echo htmlspecialchars($geaendertAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div>
                <button type="submit">Speichern</button>
            </div>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
