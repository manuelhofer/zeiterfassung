<?php
declare(strict_types=1);
/**
 * Template: Auftragsliste (aktive Aufträge und die Ablage der inaktiven)
 *
 * Erwartet (der Controller rechnet alles vor, siehe `AuftragController::index()`):
 * - $auftraege (array<int,array<string,mixed>>) – eine Zeile je Auftragsnummer
 * - $q (string) – Suchbegriff, $nurInaktive, $mitInaktiven (bool)
 * - $anzahlInaktive, $seiteNr (int)
 * - $darfVerwalten (bool)
 * - $blaetterdaten (array<string,mixed>) – für `blaetternavigation.php`
 * - optional: $fehlermeldung, $flashOk, $flashFehler (string|null)
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich    = (string)($csrfBereich ?? '');
/** @var array<int,array<string,mixed>> $auftraege */
$auftraege      = $auftraege ?? [];
$q              = (string)($q ?? '');
$nurInaktive    = (bool)($nurInaktive ?? false);
$mitInaktiven   = (bool)($mitInaktiven ?? true);
$anzahlInaktive = (int)($anzahlInaktive ?? 0);
$seiteNr        = (int)($seiteNr ?? 1);
$darfVerwalten  = (bool)($darfVerwalten ?? false);
/** @var array<string,mixed> $blaetterdaten */
$blaetterdaten  = $blaetterdaten ?? [];
$fehlermeldung  = $fehlermeldung ?? null;
$flashOk        = (string)($flashOk ?? '');
$flashFehler    = (string)($flashFehler ?? '');
?>
<section>
    <h2><?php echo $nurInaktive ? 'Inaktive Aufträge' : 'Aufträge'; ?></h2>

    <?php if ($flashOk !== ''): ?>
        <p class="success"><?php echo htmlspecialchars($flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($flashFehler !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($flashFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <div class="table-actions">
        <?php if (!$nurInaktive): ?>
            <?php if ($darfVerwalten): ?>
                <a class="button-link" href="?seite=auftrag_neu">+ Auftrag hinzufügen</a>
            <?php endif; ?>
            <a class="button-link quiet" href="?seite=auftrag&amp;ansicht=inaktiv">
                Inaktive Aufträge<?php echo $anzahlInaktive > 0 ? ' (' . $anzahlInaktive . ')' : ''; ?>
            </a>
        <?php else: ?>
            <a class="button-link quiet" href="?seite=auftrag">&laquo; Zurück zu den aktiven Aufträgen</a>
        <?php endif; ?>
    </div>

    <?php if ($nurInaktive): ?>
        <p class="muted"><small>
            Inaktive Aufträge erscheinen nicht in der normalen Liste. Sie sind
            nicht gelöscht: Buchungen, Stunden und Laufkarte bleiben erhalten.
        </small></p>
    <?php endif; ?>

    <form method="get" action="">
        <input type="hidden" name="seite" value="auftrag">
        <?php if ($nurInaktive): ?>
            <input type="hidden" name="ansicht" value="inaktiv">
        <?php endif; ?>

        <div class="toolbar">
            <label>
                Suche
                <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="min-width:260px;"
                       placeholder="Auftragsnummer, Kunde, Zeichnung, Beschreibung">
            </label>
            <button type="submit">Suchen</button>
            <?php if ($q !== ''): ?>
                <a class="button-link quiet" href="<?php echo $nurInaktive ? '?seite=auftrag&amp;ansicht=inaktiv' : '?seite=auftrag'; ?>">Zurücksetzen</a>
            <?php endif; ?>

            <?php if (!$nurInaktive): ?>
                <?php /* Verstecktes Feld voran: So kommt der Wert auch dann mit, wenn das Häkchen weg ist. */ ?>
                <input type="hidden" name="mit_inaktiven" value="0">
                <label style="flex-direction:row;align-items:center;gap:0.35rem;font-weight:400;">
                    <input type="checkbox" name="mit_inaktiven" value="1" <?php echo $mitInaktiven ? 'checked' : ''; ?>>
                    Auch inaktive Aufträge durchsuchen
                </label>
            <?php endif; ?>
        </div>

        <p class="muted"><small>
            Durchsucht Auftragsnummer, Kunde, Zeichnungsnummer und Kurzbeschreibung<?php echo $nurInaktive ? ' – nur unter den inaktiven Aufträgen' : ''; ?>.
            <?php if (!$nurInaktive): ?>
                Ohne Suchbegriff zeigt die Liste nur die aktiven Aufträge.
            <?php endif; ?>
        </small></p>
    </form>


    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (count($auftraege) === 0): ?>
        <?php if ($q !== ''): ?>
            <p>Keine Aufträge zu &bdquo;<?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>&ldquo; gefunden.</p>
            <p><small>Gesucht wurde in Auftragsnummer, Kunde, Zeichnungsnummer und Kurzbeschreibung.</small></p>
        <?php elseif ($nurInaktive): ?>
            <p>Kein Auftrag ist auf inaktiv gesetzt.</p>
        <?php else: ?>
            <p>Keine Aufträge vorhanden.</p>
            <p><small>Hier erscheinen angelegte Aufträge und alle Auftragsnummern, zu denen es Buchungen gibt.</small></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Auftragsnummer</th>
                    <th>Kunde</th>
                    <th>Zeichnungsnummer</th>
                    <th>Kurzbeschreibung</th>
                    <th>Buchungen</th>
                    <th>Laufend</th>
                    <th>Status</th>
                    <th>Stunden (Summe)</th>
                    <th>Erste Buchung</th>
                    <th>Letzte Buchung</th>
                    <th>Aktiv</th>
                    <th>Zuletzt bearbeitet</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auftraege as $row): ?>
                    <?php
                        $nr = (string)($row['auftragsnummer'] ?? '');
                        $aktivRaw = $row['auftrag_aktiv'] ?? null;
                        $buchungen = (int)($row['buchungen'] ?? 0);
                        $laufend = (int)($row['laufend'] ?? 0);
                        $status = (string)($row['status'] ?? '');
                        $sekunden = (int)($row['sekunden'] ?? 0);
                        $stunden = $sekunden > 0 ? round($sekunden / 3600, 2) : 0.0;
                        $erste = (string)($row['erste_startzeit'] ?? '');
                        $letzte = (string)($row['letzte_zeit'] ?? '');
                        $zuletztBearbeitet = (string)($row['zuletzt_bearbeitet'] ?? '');

                        $kunde = trim((string)($row['kunde'] ?? ''));
                        $zeichnungsnummer = trim((string)($row['zeichnungsnummer'] ?? ''));
                        $kurzbeschreibung = trim((string)($row['kurzbeschreibung'] ?? ''));

                        $nrEsc = htmlspecialchars($nr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $aktivText = $aktivRaw === null ? '-' : (((int)$aktivRaw === 1) ? 'Ja' : 'Nein');
                        $statusText = $status !== '' ? $status : '-';

                        // Ohne Stammdatensatz gilt der Auftrag als aktiv (siehe Abfrage).
                        // Der Knopf richtet sich nach der Zeile, nicht nach der Ansicht:
                        // In einer Suche über alles stehen beide Sorten nebeneinander.
                        $zeileAktiv = ($aktivRaw === null) || ((int)$aktivRaw === 1);
                    ?>
                    <tr<?php echo $zeileAktiv ? '' : ' class="muted"'; ?>>
                        <td><?php echo $nrEsc; ?></td>
                        <td><?php echo $kunde !== '' ? htmlspecialchars($kunde, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                        <td><?php echo $zeichnungsnummer !== '' ? htmlspecialchars($zeichnungsnummer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                        <td><?php echo $kurzbeschreibung !== '' ? htmlspecialchars($kurzbeschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                        <td><?php echo $buchungen; ?></td>
                        <td><?php echo $laufend; ?></td>
                        <td><?php echo htmlspecialchars($statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo number_format($stunden, 2, '.', ''); ?></td>
                        <td><?php echo htmlspecialchars($erste, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($letzte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $aktivText; ?></td>
                        <td><?php echo htmlspecialchars($zuletztBearbeitet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($nr !== ''): ?>
                                <div class="table-actions">
                                    <a class="button-link" href="?seite=auftrag_detail&amp;code=<?php echo urlencode($nr); ?>">Details</a>
                                    <?php if ($darfVerwalten): ?>
                                        <?php /* Umschalten direkt in der Zeile - dafür erst die Details zu öffnen wäre ein Umweg. */ ?>
                                        <form method="post" action="?seite=auftrag_aktiv_setzen">
                                            <?php echo Csrf::feld($csrfBereich); ?>
                                            <input type="hidden" name="auftragsnummer" value="<?php echo $nrEsc; ?>">
                                            <input type="hidden" name="aktiv" value="<?php echo $zeileAktiv ? '0' : '1'; ?>">
                                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                            <input type="hidden" name="ansicht" value="<?php echo $nurInaktive ? 'inaktiv' : ''; ?>">
                                            <input type="hidden" name="mit_inaktiven" value="<?php echo $mitInaktiven ? '1' : '0'; ?>">
                                            <input type="hidden" name="s" value="<?php echo $seiteNr; ?>">
                                            <button type="submit" class="quiet">
                                                <?php echo $zeileAktiv ? 'Inaktiv setzen' : 'Aktiv setzen'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php require __DIR__ . '/blaetternavigation.php'; ?>

        <p class="muted">
            <small>
                Arbeitsschritt-Code wird in der Detailansicht angezeigt, sofern beim Auftrag-Start erfasst (Scan/Manuell).
            </small>
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
