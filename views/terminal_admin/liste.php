<?php
declare(strict_types=1);
/**
 * Template: Terminalverwaltung – Liste
 *
 * Erwartet:
 * - $terminals (array<int,array<string,mixed>>) – Zeilen aus `terminal`, dazu
 *   `abteilung_name` aus dem JOIN
 * - $kopplungService (TerminalKopplungService|null) – für die Frage, ob zu einem
 *   Terminal noch ein Kopplungscode offen ist
 * - $neuerCode (string), $neuerCodeTerminal (string) – frisch erzeugter Code;
 *   er wird genau einmal angezeigt und ist danach nicht mehr abrufbar
 * - optional: $fehlermeldung (string|null), $flashOk (string|null), $flashErr (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? '');
/** @var array<int,array<string,mixed>> $terminals */
$terminals         = $terminals ?? [];
$kopplungService   = $kopplungService ?? null;
$neuerCode         = (string)($neuerCode ?? '');
$neuerCodeTerminal = (string)($neuerCodeTerminal ?? '');
$fehlermeldung     = $fehlermeldung ?? null;
$flashOk           = $flashOk ?? null;
$flashErr          = $flashErr ?? null;
$ladefehler        = (bool)($ladefehler ?? false);
?>
<section>
    <h2>Terminalverwaltung</h2>

    <?php if ($neuerCode !== ''): ?>
        <div class="info-panel" style="margin:0.75rem 0;max-width:560px;">
            <div><strong>Kopplungscode für <?php echo htmlspecialchars($neuerCodeTerminal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
            <div style="font-size:2rem;font-family:monospace;letter-spacing:0.25rem;margin:0.5rem 0;">
                <?php echo htmlspecialchars($neuerCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
            <div><small>
                Am Terminal eingeben. <strong>Der Code wird nur dieses eine Mal angezeigt</strong> –
                er ist danach nicht mehr abrufbar. Er gilt 30 Minuten und lässt sich nur einmal einlösen;
                geht er verloren, einfach einen neuen erzeugen.
            </small></div>
        </div>
    <?php endif; ?>

    <p>
        <a href="?seite=terminal_admin_bearbeiten">Neues Terminal anlegen</a>
    </p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashOk)): ?>
        <div class="hinweis" style="margin: 0.5rem 0;">
            <?php echo htmlspecialchars((string)$flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashErr)): ?>
        <div class="fehlermeldung" style="margin: 0.5rem 0;">
            <?php echo htmlspecialchars((string)$flashErr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann
             steht schon die Fehlermeldung da. */ ?>
    <?php if (count($terminals) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine Terminals hinterlegt.</p>
        <?php endif; ?>
    <?php else: ?>
        <?php /* `.table-wrap` gehört zu `.table-actions`: Die Knöpfe bleiben in
                 ihrer Zeile (`nowrap`), dadurch wird diese elfspaltige Tabelle
                 breiter als ein schmales Fenster. Ohne den Rahmen schiebt sie
                 die ganze Seite zur Seite; mit ihm scrollt nur die Tabelle. */ ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Standort</th>
                    <th>Abteilung</th>
                    <th>Modus</th>
                    <th>Offline K/G</th>
                    <th>Offline Aufträge</th>
                    <th>Auto-Logout</th>
                    <th>Aktiv</th>
                    <th>Kopplung</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($terminals as $t): ?>
                    <?php
                        $id = (int)($t['id'] ?? 0);
                        $name = (string)($t['name'] ?? '');
                        $standort = (string)($t['standort_beschreibung'] ?? '');
                        $abteilung = (string)($t['abteilung_name'] ?? '');
                        $modus = (string)($t['modus'] ?? '');
                        $okg = (int)($t['offline_erlaubt_kommen_gehen'] ?? 0) === 1;
                        $oauf = (int)($t['offline_erlaubt_auftraege'] ?? 0) === 1;
                        $timeout = (int)($t['auto_logout_timeout_sekunden'] ?? 0);
                        $aktiv = (int)($t['aktiv'] ?? 0) === 1;
                        $dbBenutzer = trim((string)($t['db_benutzer'] ?? ''));
                        $gekoppeltAm = trim((string)($t['gekoppelt_am'] ?? ''));
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($standort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($abteilung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($modus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <div class="table-actions">
                                <span><?php echo $okg ? 'Ja' : 'Nein'; ?></span>
                                <form method="post" action="?seite=terminal_admin_toggle">
                                    <?php echo Csrf::feld($csrfBereich); ?>
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="feld" value="offline_erlaubt_kommen_gehen">
                                    <button type="submit">Umschalten</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <div class="table-actions">
                                <span><?php echo $oauf ? 'Ja' : 'Nein'; ?></span>
                                <form method="post" action="?seite=terminal_admin_toggle">
                                    <?php echo Csrf::feld($csrfBereich); ?>
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="feld" value="offline_erlaubt_auftraege">
                                    <button type="submit">Umschalten</button>
                                </form>
                            </div>
                        </td>
                        <td><?php echo $timeout > 0 ? ($timeout . ' s') : '-'; ?></td>
                        <td>
                            <div class="table-actions">
                                <span><?php echo $aktiv ? 'Ja' : 'Nein'; ?></span>
                                <form method="post" action="?seite=terminal_admin_toggle">
                                    <?php echo Csrf::feld($csrfBereich); ?>
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="feld" value="aktiv">
                                    <button type="submit">Umschalten</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <?php if ($dbBenutzer !== ''): ?>
                                <span title="Datenbankbenutzer dieses Geräts"><?php echo htmlspecialchars($dbBenutzer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                <?php if ($gekoppeltAm !== ''): ?>
                                    <br><small class="muted">seit <?php echo htmlspecialchars($gekoppeltAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
                                <?php endif; ?>
                                <?php /* Rückfrage bewusst ohne Gerätenamen: Der steht in derselben Zeile,
                                         und ein Name im JavaScript-Text wäre nur eine weitere Stelle zum
                                         Maskieren. */ ?>
                                <div class="table-actions" style="margin-top:0.35rem;">
                                    <form method="post" action="?seite=terminal_admin_entkoppeln"
                                          onsubmit="return confirm('Dieses Terminal entkoppeln?\n\nDer Datenbankbenutzer wird gelöscht. Das Gerät kann danach nicht mehr buchen, bis es mit einem neuen Kopplungscode erneut gekoppelt wird.');">
                                        <?php echo Csrf::feld($csrfBereich); ?>
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <button type="submit">Entkoppeln</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="muted">nicht gekoppelt</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?seite=terminal_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                                <form method="post" action="?seite=terminal_admin_kopplung">
                                    <?php echo Csrf::feld($csrfBereich); ?>
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit">Kopplungscode</button>
                                </form>
                            </div>
                            <?php
                                $offeneKopplung = $kopplungService !== null ? $kopplungService->holeOffeneKopplung($id) : null;
                                if (is_array($offeneKopplung)):
                            ?>
                                <br><small class="muted">Code offen bis
                                    <?php echo htmlspecialchars((string)($offeneKopplung['gueltig_bis'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
