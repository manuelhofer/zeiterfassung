<?php
declare(strict_types=1);
/**
 * Template: Auftragsdetail – Buchungen, Stammdaten, Arbeitsschritte
 *
 * Erwartet (der Controller rechnet alles vor, siehe `AuftragController::detail()`):
 * - $code (string) – die Auftragsnummer aus der URL
 * - $buchungen (array<int,array<string,mixed>>) – Buchungen, neueste zuerst
 * - $sumStunden (float), $sumProSchrittSorted (array<string,int>),
 *   $countProSchritt (array<string,int>) – Summen der abgeschlossenen Buchungen
 * - $auftragStamm (array<string,mixed>|null) – null, wenn die Nummer nur aus
 *   Buchungen stammt
 * - $arbeitsschritte (array<int,array<string,mixed>>) – je Schritt zusätzlich
 *   `code_url` und `bezeichnung_aus_katalog`
 * - $katalogVerfuegbar (array<int,array<string,mixed>>) – Katalogschritte, die
 *   der Auftrag noch nicht hat
 * - $auftragCodeUrl (string) – Bild-URL des Auftrags-Strichcodes, '' wenn keiner
 * - $darfVerwalten (bool)
 * - optional: $flashOk, $flashFehler, $fehlermeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Buchungen nicht lesbar waren
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich         = (string)($csrfBereich ?? '');
$code                = (string)($code ?? '');
/** @var array<int,array<string,mixed>> $buchungen */
$buchungen           = $buchungen ?? [];
$sumStunden          = (float)($sumStunden ?? 0.0);
/** @var array<string,int> $sumProSchrittSorted */
$sumProSchrittSorted = $sumProSchrittSorted ?? [];
/** @var array<string,int> $countProSchritt */
$countProSchritt     = $countProSchritt ?? [];
$auftragStamm        = $auftragStamm ?? null;
/** @var array<int,array<string,mixed>> $arbeitsschritte */
$arbeitsschritte     = $arbeitsschritte ?? [];
/** @var array<int,array<string,mixed>> $katalogVerfuegbar */
$katalogVerfuegbar   = $katalogVerfuegbar ?? [];
$auftragCodeUrl      = (string)($auftragCodeUrl ?? '');
$darfVerwalten       = (bool)($darfVerwalten ?? false);
$flashOk             = $flashOk ?? null;
$flashFehler         = $flashFehler ?? null;
$fehlermeldung       = $fehlermeldung ?? null;
$ladefehler          = (bool)($ladefehler ?? false);

$escD = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section>
    <h2>Auftrag: <?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>

    <p>
        <a class="button-link quiet" href="?seite=auftrag">&laquo; Zurück zur Liste</a>
    </p>

    <?php if (is_string($flashOk) && $flashOk !== ''): ?>
        <p class="success"><?php echo htmlspecialchars($flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (is_string($flashFehler) && $flashFehler !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($flashFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* „Keine Buchungen" nur, wenn auch wirklich nachgesehen wurde – bei
             einem Ladefehler steht die Fehlermeldung schon darüber (B-096). */ ?>
    <?php if (count($buchungen) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Keine Buchungen gefunden.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mitarbeiter</th>
                    <th>Maschine</th>
                    <th>Typ</th>
                    <th>Arbeitsschritt</th>
                    <th>Start</th>
                    <th>Ende</th>
                    <th>Dauer (h)</th>
                    <th>Status</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($buchungen as $b): ?>
                    <?php
                        $id = (int)($b['id'] ?? 0);
                        $vorname = trim((string)($b['vorname'] ?? ''));
                        $nachname = trim((string)($b['nachname'] ?? ''));
                        $mitarbeiter = trim($vorname . ' ' . $nachname);
                        if ($mitarbeiter === '') {
                            $mitarbeiter = 'Unbekannt';
                        }
                        $maschine = (string)($b['maschine_name'] ?? '');
                        $typ = (string)($b['typ'] ?? '');
                        $schritt = trim((string)($b['arbeitsschritt_code_effektiv'] ?? ''));
                        $start = (string)($b['startzeit'] ?? '');
                        $end = (string)($b['endzeit'] ?? '');
                        $status = (string)($b['status'] ?? '');

                        $dauerH = '';
                        if ($end !== '') {
                            $ts1 = strtotime($start);
                            $ts2 = strtotime($end);
                            if ($ts1 !== false && $ts2 !== false && $ts2 >= $ts1) {
                                $dauerH = number_format(round(($ts2 - $ts1) / 3600, 2), 2, '.', '');
                            }
                        }

                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($mitarbeiter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($maschine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $schritt !== '' ? htmlspecialchars($schritt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '-'; ?></td>
                        <td><?php echo htmlspecialchars($start, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($end, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $dauerH !== '' ? $dauerH : '-'; ?></td>
                        <td><?php echo htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($id > 0): ?>
                                <a class="button-link quiet" href="?seite=auftragszeit_bearbeiten&amp;id=<?php echo $id; ?>">Editieren</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 0.75rem;">
            <strong>Gesamtstunden (abgeschlossen):</strong> <?php echo number_format($sumStunden, 2, '.', ''); ?>
        </p>

        <?php if (is_array($sumProSchrittSorted) && count($sumProSchrittSorted) > 0): ?>
            <h3>Arbeitsschritte (Summe, abgeschlossen)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Arbeitsschritt</th>
                        <th>Buchungen</th>
                        <th>Stunden (Summe)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sumProSchrittSorted as $schrittKey => $sec): ?>
                        <?php
                            $cnt = isset($countProSchritt[$schrittKey]) ? (int)$countProSchritt[$schrittKey] : 0;
                            $h = $sec > 0 ? round(((int)$sec) / 3600, 2) : 0.0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$schrittKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo $cnt; ?></td>
                            <td><?php echo number_format($h, 2, '.', ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><small>Hinweis: Arbeitsschritt-Code wird in den Details angezeigt, sofern beim Auftrag-Start erfasst (Scan/Manuell).</small></p>
    <?php endif; ?>

    <?php /* Ab hier die Stammdaten - bewusst ausserhalb des Buchungs-Zweigs,
             damit sie auch bei einem Auftrag ohne Buchung erscheinen. */ ?>

    <?php if (!is_array($auftragStamm)): ?>
        <h3>Stammdaten</h3>
        <p>
            Zu dieser Auftragsnummer gibt es noch keinen Stammdatensatz – sie stammt
            bisher nur aus Buchungen.
            <?php if ($darfVerwalten): ?>
                <a href="?seite=auftrag_neu">Auftrag jetzt anlegen</a>, um Arbeitsschritte
                und Strichcodes zu pflegen.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <h3>Auftrag</h3>
        <div style="display:flex;gap:2rem;flex-wrap:wrap;align-items:flex-start;margin-bottom:1rem;">
            <div>
                <table>
                    <tbody>
                        <tr><th style="text-align:left;">Kunde</th><td><?php echo $escD(($auftragStamm['kunde'] ?? '') !== '' ? $auftragStamm['kunde'] : '-'); ?></td></tr>
                        <tr><th style="text-align:left;">Zeichnungsnummer</th><td><?php echo $escD(($auftragStamm['zeichnungsnummer'] ?? '') !== '' ? $auftragStamm['zeichnungsnummer'] : '-'); ?></td></tr>
                        <tr><th style="text-align:left;">Kurzbeschreibung</th><td><?php echo $escD(($auftragStamm['kurzbeschreibung'] ?? '') !== '' ? $auftragStamm['kurzbeschreibung'] : '-'); ?></td></tr>
                        <tr><th style="text-align:left;">Status</th><td><?php echo $escD(($auftragStamm['status'] ?? '') !== '' ? $auftragStamm['status'] : '-'); ?></td></tr>
                        <tr><th style="text-align:left;">Aktiv</th><td><?php echo ((int)($auftragStamm['aktiv'] ?? 0) === 1) ? 'Ja' : 'Nein'; ?></td></tr>
                    </tbody>
                </table>
                <div class="table-actions" style="margin-top:0.75rem;">
                    <?php if ($darfVerwalten): ?>
                        <a class="button-link" href="?seite=auftrag_bearbeiten&amp;id=<?php echo (int)$auftragStamm['id']; ?>">Auftrag bearbeiten</a>
                    <?php endif; ?>
                    <a class="button-link quiet" href="?seite=auftrag_laufkarte&amp;code=<?php echo urlencode($code); ?>" target="_blank">Laufkarte als PDF drucken</a>
                </div>
            </div>

            <?php if ($auftragCodeUrl !== ''): ?>
                <div style="text-align:center;">
                    <div><strong>Auftrags-Strichcode</strong></div>
                    <img src="<?php echo $escD($auftragCodeUrl); ?>" alt="Strichcode Auftrag <?php echo $escD($code); ?>" style="height:56px;width:auto;image-rendering:pixelated;">
                    <div><small><?php echo $escD($code); ?></small></div>
                    <div><a href="<?php echo $escD($auftragCodeUrl); ?>" target="_blank">PNG herunterladen</a></div>
                </div>
            <?php endif; ?>
        </div>

        <h3>Arbeitsschritte (Stammdaten)</h3>
        <?php if (count($arbeitsschritte) === 0): ?>
            <p>Noch keine Arbeitsschritte hinterlegt.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Code</th>
                        <th>Bezeichnung</th>
                        <th>Strichcode</th>
                        <th>Aktiv</th>
                        <?php if ($darfVerwalten): ?><th>Aktion</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arbeitsschritte as $nr => $schritt): ?>
                        <?php
                            $schrittId   = (int)($schritt['id'] ?? 0);
                            $schrittCode = (string)($schritt['arbeitsschritt_code'] ?? '');
                            $bezeichnung = trim((string)($schritt['bezeichnung'] ?? ''));
                            $schrittCodeBild   = (string)($schritt['code_url'] ?? '');
                            $schrittAktiv = (int)($schritt['aktiv'] ?? 0) === 1;
                        ?>
                        <tr<?php echo $schrittAktiv ? '' : ' class="muted"'; ?>>
                            <td><?php echo $nr + 1; ?></td>
                            <td><code><?php echo $escD($schrittCode); ?></code></td>
                            <td>
                                <?php echo $bezeichnung !== '' ? $escD($bezeichnung) : '-'; ?>
                                <?php if (!empty($schritt['bezeichnung_aus_katalog'])): ?>
                                    <small class="muted">(aus Katalog)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($schrittCodeBild !== ''): ?>
                                    <img src="<?php echo $escD($schrittCodeBild); ?>" alt="Strichcode <?php echo $escD($schrittCode); ?>" style="height:44px;width:auto;image-rendering:pixelated;">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $schrittAktiv ? 'Ja' : 'Nein'; ?></td>
                            <?php if ($darfVerwalten): ?>
                                <td><a class="button-link quiet" href="?seite=auftrag_schritt_bearbeiten&amp;id=<?php echo $schrittId; ?>">Bearbeiten</a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($darfVerwalten): ?>
            <div class="admin-card" style="margin-top:1rem;max-width:640px;">
                <strong>Arbeitsschritt hinzufügen</strong>
                <form method="post" action="?seite=auftrag_schritt_speichern" style="margin-top:0.5rem;">
                    <?php echo Csrf::feld($csrfBereich); ?>
                    <input type="hidden" name="schritt_id" value="0">
                    <input type="hidden" name="auftrag_id" value="<?php echo (int)$auftragStamm['id']; ?>">

                    <div style="margin-bottom:0.5rem;">
                        <label for="neuer_code"><strong>Code</strong></label><br>
                        <input type="text" id="neuer_code" name="arbeitsschritt_code" required maxlength="100" style="width:100%;max-width:260px;">
                        <br><small>Steht im Strichcode und wird am Terminal gescannt, z. B. <code>drehen</code>, <code>fräsen</code>, <code>sägen</code>.</small>
                    </div>

                    <div style="margin-bottom:0.5rem;">
                        <label for="neue_bezeichnung"><strong>Bezeichnung</strong></label><br>
                        <input type="text" id="neue_bezeichnung" name="bezeichnung" maxlength="255" style="width:100%;max-width:480px;">
                        <br><small>Klartext für die Laufkarte, z. B. „Aussendurchmesser auf 40 mm drehen“.</small>
                    </div>

                    <input type="hidden" name="aktiv" value="1">
                    <button type="submit">Hinzufügen</button>
                </form>
            </div>

            <?php if (count($katalogVerfuegbar) > 0): ?>
                <div class="admin-card" style="margin-top:1rem;max-width:640px;">
                    <strong>Aus dem Arbeitsschritt-Katalog übernehmen</strong>
                    <p style="margin:0.4rem 0;"><small>
                        Standardschritte, die es bei diesem Auftrag noch nicht gibt. Übernommene
                        Schritte erscheinen auf der Laufkarte.
                        <a href="?seite=arbeitsschritt_katalog">Katalog pflegen</a>
                    </small></p>
                    <form method="post" action="?seite=auftrag_schritte_aus_katalog">
                        <?php echo Csrf::feld($csrfBereich); ?>
                        <input type="hidden" name="auftrag_id" value="<?php echo (int)$auftragStamm['id']; ?>">
                        <?php foreach ($katalogVerfuegbar as $kat): ?>
                            <?php
                                $katId  = (int)($kat['id'] ?? 0);
                                $katCode = (string)($kat['code'] ?? '');
                                $katBez  = trim((string)($kat['bezeichnung'] ?? ''));
                            ?>
                            <label style="display:block;margin:0.15rem 0;">
                                <input type="checkbox" name="katalog_ids[]" value="<?php echo $katId; ?>">
                                <code><?php echo $escD($katCode); ?></code>
                                <?php if ($katBez !== ''): ?>
                                    &ndash; <?php echo $escD($katBez); ?>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" style="margin-top:0.5rem;">Ausgewählte übernehmen</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="warning-panel" style="margin-top:1.5rem;max-width:640px;">
                <strong>Auftrag löschen</strong>
                <?php if (count($buchungen) > 0): ?>
                    <p style="margin:0.4rem 0;">
                        Nicht möglich: An diesem Auftrag
                        <?php echo count($buchungen) === 1
                            ? 'hängt eine Buchung'
                            : 'hängen ' . count($buchungen) . ' Buchungen'; ?>.
                        Gebuchte Zeit wird nicht weggeworfen.
                    </p>
                    <p style="margin:0.4rem 0;"><small>
                        Wenn der Auftrag nur aus der Liste verschwinden soll: in der
                        <a href="?seite=auftrag">Auftragsliste</a> auf &bdquo;Inaktiv setzen&ldquo;.
                        Buchungen, Stunden und Laufkarte bleiben dabei erhalten.
                    </small></p>
                <?php else: ?>
                    <p style="margin:0.4rem 0;"><small>
                        Löscht den Auftrag samt seiner Arbeitsschritte. Das ist nur möglich,
                        solange keine Buchung daran hängt &ndash; danach nicht mehr. Gedruckte
                        Laufkarten werden dadurch ungültig.
                    </small></p>
                    <form method="post" action="?seite=auftrag_loeschen"
                          onsubmit="return confirm('Auftrag <?php echo $escD($code); ?> wirklich löschen? Das lässt sich nicht rückgängig machen.');">
                        <?php echo Csrf::feld($csrfBereich); ?>
                        <input type="hidden" name="auftragsnummer" value="<?php echo $escD($code); ?>">
                        <button type="submit" class="danger">Auftrag endgültig löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
