<?php
declare(strict_types=1);
/**
 * Template: Rollenverwaltung – Formular (Neu/Bearbeiten)
 *
 * Erwartet:
 * - $rolle (array<string,mixed>|null)
 * - optional: $rechteAlle (array<int,array<string,mixed>>)
 * - optional: $rolleRechtIds (int[])
 * - optional: $csrfToken (string)
 * - optional: $fehlermeldung (string)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<string,mixed>|null $rolle */
$rolle         = $rolle ?? null;
$fehlermeldung = $fehlermeldung ?? null;

/** @var array<int,array<string,mixed>> $rechteAlle */
$rechteAlle = $rechteAlle ?? [];

/** @var int[] $rolleRechtIds */
$rolleRechtIds = $rolleRechtIds ?? [];

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$id           = $rolle['id'] ?? null;
$id           = $id !== null ? (int)$id : 0;
$name         = isset($rolle['name']) ? trim((string)$rolle['name']) : '';
$beschreibung = isset($rolle['beschreibung']) ? (string)$rolle['beschreibung'] : '';
$aktiv        = array_key_exists('aktiv', (array)$rolle) ? ((int)$rolle['aktiv'] === 1) : true;
?>
<section>
    <h2><?php echo $id > 0 ? 'Rolle bearbeiten' : 'Neue Rolle anlegen'; ?></h2>

    <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
        <p style="color:red;"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="?seite=rollen_admin_speichern">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

        <div>
            <label for="name">Name der Rolle *</label><br>
            <input type="text" id="name" name="name" required
                   value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        </div>

        <div>
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                Rolle ist aktiv
            </label>
        </div>

        <div>
            <label for="beschreibung">Beschreibung</label><br>
            <textarea id="beschreibung" name="beschreibung" rows="4" cols="60"><?php
                echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            ?></textarea>
        </div>

        <fieldset style="margin-top:1em;">
            <legend>Rechte dieser Rolle</legend>
            <p style="margin-top:0;">Diese Rechte werden an Mitarbeiter vererbt, die diese Rolle besitzen.</p>

            <?php if (count($rechteAlle) === 0): ?>
                <p><em>Keine Rechte gefunden (Tabelle <code>recht</code> fehlt oder ist leer).</em></p>
            <?php else: ?>
                <?php
                /**
                 * Rechte-Gruppierung (UI-Only): bessere Übersicht in "Rolle bearbeiten".
                 *
                 * Regel: Wir gruppieren nur nach Code-Präfixen / bekannten Codes.
                 * Das ändert keinerlei Berechtigungslogik, sondern nur die Darstellung.
                 */
                $gruppen = [
                    'Stammdaten & Benutzer'     => [],
                    'Zeit & Pausen'             => [],
                    'Urlaub'                    => [],
                    'Reports'                   => [],
                    'Terminal & Offline-Queue'  => [],
                    'System & Konfiguration'    => [],
                    'Sonstiges'                 => [],
                ];

                $bestimmeGruppe = static function (string $code): string {
                    $c = strtoupper(trim($code));
                    if ($c === '') {
                        return 'Sonstiges';
                    }

                    // Stammdaten / Benutzer / Rollen
                    if (in_array($c, ['MITARBEITER_VERWALTEN', 'ABTEILUNG_VERWALTEN', 'MASCHINEN_VERWALTEN', 'ROLLEN_RECHTE_VERWALTEN'], true)) {
                        return 'Stammdaten & Benutzer';
                    }

                    // Urlaub
                    if (str_starts_with($c, 'URLAUB_')) {
                        return 'Urlaub';
                    }

                    // Reports
                    if (str_starts_with($c, 'REPORT_') || str_starts_with($c, 'REPORTS_')) {
                        return 'Reports';
                    }

                    // Terminal / Offline / Dashboard-Warnblock
                    if (str_starts_with($c, 'TERMINAL_') || str_starts_with($c, 'QUEUE_') || $c === 'DASHBOARD_ZEITWARNUNGEN_SEHEN') {
                        return 'Terminal & Offline-Queue';
                    }

                    // Zeit & Pausen
                    if (str_starts_with($c, 'ZEITBUCHUNG_') || str_starts_with($c, 'ZEIT_') || str_starts_with($c, 'PAUSEN')) {
                        return 'Zeit & Pausen';
                    }

                    // System / Konfiguration / Kalender
                    if (str_starts_with($c, 'KONFIGURATION_')
                        || str_starts_with($c, 'FEIERTAGE_')
                        || str_starts_with($c, 'BETRIEBSFERIEN_')
                        || str_starts_with($c, 'KRANK')
                        || str_starts_with($c, 'KURZARBEIT_')
                        || str_starts_with($c, 'ZEIT_RUNDUNGSREGELN_')
                    ) {
                        return 'System & Konfiguration';
                    }

                    return 'Sonstiges';
                };

                foreach ($rechteAlle as $recht) {
                    $code = (string)($recht['code'] ?? '');
                    $gruppe = $bestimmeGruppe($code);
                    if (!array_key_exists($gruppe, $gruppen)) {
                        $gruppe = 'Sonstiges';
                    }
                    $gruppen[$gruppe][] = $recht;
                }
                ?>

                <?php foreach ($gruppen as $gruppenName => $rechteInGruppe): ?>
                    <?php if (count($rechteInGruppe) === 0): continue; endif; ?>
                    <details open style="border:1px solid #ddd; padding:0.5rem; margin:0.6rem 0;">
                        <summary style="cursor:pointer;">
                            <strong><?php echo htmlspecialchars($gruppenName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            <small>(<?php echo (int)count($rechteInGruppe); ?>)</small>
                        </summary>
                        <div style="margin-top:0.35rem;">
                            <?php foreach ($rechteInGruppe as $recht): ?>
                                <?php
                                    $rechtId = (int)($recht['id'] ?? 0);
                                    $rechtCode = trim((string)($recht['code'] ?? ''));
                                    $rechtName = trim((string)($recht['name'] ?? ''));
                                    $rechtBeschr = (string)($recht['beschreibung'] ?? '');
                                    $rechtAktiv = ((int)($recht['aktiv'] ?? 0) === 1);
                                    $checked = $rechtId > 0 && in_array($rechtId, $rolleRechtIds, true);
                                ?>
                                <label style="display:block; margin:0.35rem 0;">
                                    <input type="checkbox" name="rechte[]" value="<?php echo htmlspecialchars((string)$rechtId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                    <strong><?php echo htmlspecialchars($rechtName !== '' ? $rechtName : $rechtCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                                    <small>
                                        (<?php echo htmlspecialchars($rechtCode !== '' ? $rechtCode : ('ID ' . (string)$rechtId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $rechtAktiv ? '' : ', inaktiv'; ?>)
                                    </small>
                                    <?php if (trim($rechtBeschr) !== ''): ?>
                                        <br><small><?php echo htmlspecialchars($rechtBeschr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>

        <div style="margin-top:1em;">
            <button type="submit">Speichern</button>
            <a href="?seite=rollen_admin">Abbrechen</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
