<?php
declare(strict_types=1);
/**
 * Template: Mitarbeiterportal – Verbindung zur gekoppelten Website
 *
 * Erwartet:
 * - $verbindung (array|null)  – Zeile aus `portal_verbindung`, aktiv
 * - $freigegeben (int)        – wie viele Mitarbeiter freigeschaltet sind
 * - $eingang (array)          – die letzten verarbeiteten Aufträge
 * - $belegschaft (array)      – alle aktiven Mitarbeiter mit ihrem Portal-Zustand
 * - $vorschlaege (array)      – je Mitarbeiter-ID die vorgeschlagene Kennung
 * - $herkunft (array)        – ob diese Installation die Verbindung benutzen darf
 * - $kennung (string)         – wer diese Installation ist
 * - $csrfBereich (string)     – Bereichsname für `Csrf`
 * - optional: $flashOk (string|null), $flashErr (string|null)
 * - optional: $zettel (array|null) – frisch erzeugter Aktivierungscode; er
 *   wird genau einmal angezeigt und ist danach nicht mehr abrufbar
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? 'portal_admin');
$verbindung  = $verbindung ?? null;
$freigegeben = (int)($freigegeben ?? 0);
$eingang     = $eingang ?? [];
$belegschaft = $belegschaft ?? [];
$vorschlaege = $vorschlaege ?? [];
$flashOk     = $flashOk ?? null;
$flashErr    = $flashErr ?? null;
$zettel      = $zettel ?? null;
$herkunft    = $herkunft ?? ['ok' => true, 'meldung' => ''];
$kennung     = (string)($kennung ?? '');

$h = static fn($w): string => htmlspecialchars((string)$w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$lauf      = $verbindung['letzter_lauf_am'] ?? null;
$laufZeit  = $lauf !== null ? strtotime((string)$lauf) : false;
$laufAlter = $laufZeit === false ? null : (int)floor((time() - $laufZeit) / 60);

$artText = [
    'urlaub_antrag' => 'Urlaubsantrag',
    'urlaub_storno' => 'Antrag zurückgezogen',
    'monat_pdf'     => 'Monats-PDF',
];
?>
<section>
    <h2>Mitarbeiterportal</h2>

    <p>
        Mitarbeiter können ihren Urlaub von zu Hause oder vom Handy beantragen und
        ihre Zahlen ansehen – über den Bereich <code>/mitarbeiter</code> auf der
        gekoppelten Website. <strong>Diese Installation ruft dort an</strong>, holt neue
        Anträge ab und schickt den aktuellen Stand hin. Umgekehrt geht nichts: Die
        Website kennt weder die Adresse dieses Servers noch seine Datenbank und
        kann hier nichts abfragen.
    </p>

    <?php if (!empty($flashOk)): ?>
        <div class="hinweis" style="margin:0.5rem 0;"><?php echo $h($flashOk); ?></div>
    <?php endif; ?>
    <?php if (!empty($flashErr)): ?>
        <div class="fehlermeldung" style="margin:0.5rem 0;"><?php echo $h($flashErr); ?></div>
    <?php endif; ?>

    <?php if (!$herkunft['ok']): ?>
        <?php /* Der wichtigste Kasten dieser Maske. Er steht vor allem
                 anderen, weil in diesem Zustand nichts laeuft - und weil der
                 Grund sonst nirgends sichtbar waere. */ ?>
        <div class="fehlermeldung" style="margin:0.75rem 0;max-width:760px;">
            <strong>Diese Datenbank stammt aus einer anderen Installation – es wird nicht abgeglichen.</strong>
            <p style="margin:0.5rem 0 0;"><?php echo $h($herkunft['meldung']); ?></p>
            <p style="margin:0.5rem 0 0;"><small>
                Das ist der Normalfall nach einem eingespielten Server-Dump. Solange dieser
                Hinweis steht, ruft diese Installation die Website <strong>nicht</strong> an –
                und kann dort auch nichts löschen.
            </small></p>
        </div>
    <?php endif; ?>

    <?php if (is_array($zettel)): ?>
        <?php /* Der Zettel zum Ausdrucken oder Abschreiben. Er steht hier
                 genau einmal - nachschlagen kann ihn niemand, auch der Chef
                 nicht. Genau das macht ihn zu einem Nachweis und nicht zu
                 einer Notiz. */ ?>
        <div class="info-panel" id="portal-zettel"
             style="margin:0.75rem 0;max-width:560px;border:2px solid #333;padding:1rem;">
            <div style="font-weight:bold;font-size:1.1rem;">
                Zugang zum Mitarbeiterportal – <?php echo $h($zettel['name']); ?>
            </div>
            <table style="margin:0.75rem 0;">
                <tr>
                    <th style="text-align:left;padding-right:1rem;">Adresse</th>
                    <td><?php echo $h($zettel['adresse']); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left;padding-right:1rem;">Kennung</th>
                    <td style="font-family:monospace;font-size:1.15rem;"><?php echo $h($zettel['kennung']); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left;padding-right:1rem;vertical-align:top;">Code</th>
                    <td style="font-family:monospace;font-size:1.6rem;letter-spacing:0.2rem;">
                        <?php echo $h(PortalFreigabeService::codeLesbar($zettel['code'])); ?>
                    </td>
                </tr>
                <?php if ($zettel['bis'] !== ''): ?>
                <tr>
                    <th style="text-align:left;padding-right:1rem;">Gültig bis</th>
                    <td><?php echo $h(date('d.m.Y', strtotime((string)$zettel['bis']))); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <div><small>
                Adresse aufrufen, »Zugang einrichten« wählen, Kennung und Code eingeben,
                eigenes Passwort vergeben. Danach wird der Code nicht mehr gebraucht.
                Die Leerzeichen im Code sind nur zum Lesen – sie werden nicht mit eingetippt.
            </small></div>
            <div style="margin-top:0.75rem;">
                <button type="button" onclick="window.print();">Drucken</button>
                <small style="margin-left:0.5rem;"><strong>Der Code steht nur dieses eine Mal
                hier.</strong> Wer ihn verliert, bekommt einen neuen – der alte gilt dann nicht mehr.</small>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($verbindung === null): ?>

        <h3>Verbinden</h3>
        <p>
            Der Handschlag geht in zwei Schritten, und der erste passiert
            <strong>nicht hier</strong>: Im Backend der Homepage steht unter
            »Mitarbeiterportal« ein Knopf, der einen Kopplungscode erzeugt. Er gilt
            30 Minuten und lässt sich genau einmal einlösen. Diesen Code und die
            Adresse der Homepage hier eintragen – fertig.
        </p>

        <form method="post" action="?seite=portal_admin" style="max-width:560px;">
            <?php echo Csrf::feld($csrfBereich); ?>
            <input type="hidden" name="aktion" value="koppeln">

            <p>
                <label for="basis_url">Adresse der Homepage</label><br>
                <input type="text" id="basis_url" name="basis_url" style="width:100%;"
                       placeholder="https://example.org" spellcheck="false" autocomplete="off">
                <small>Mit <code>https://</code> davor. Liegt die Website in einem
                       Unterverzeichnis, gehört es dazu – etwa
                       <code>https://example.org/homepage</code>. Sie können auch
                       die vollständige Adresse einsetzen, die im Backend der
                       Website danebensteht; <code>/portal-api</code> am Ende
                       wird abgeschnitten.</small>
            </p>

            <p>
                <label for="code">Kopplungscode</label><br>
                <input type="text" id="code" name="code" style="width:100%;font-family:monospace;
                       letter-spacing:0.15rem;text-transform:uppercase;" maxlength="20"
                       spellcheck="false" autocomplete="off">
                <small>Groß- und Kleinschreibung ist egal, Leerzeichen und Bindestriche auch.</small>
            </p>

            <p><button type="submit">Koppeln</button></p>
        </form>

    <?php else: ?>

        <h3>Verbindung</h3>
        <table style="max-width:760px;">
            <tbody>
                <tr>
                    <th style="text-align:left;">Homepage</th>
                    <td><code><?php echo $h($verbindung['basis_url']); ?></code></td>
                </tr>
                <tr>
                    <th style="text-align:left;">Gekoppelt seit</th>
                    <td><?php echo $h(date('d.m.Y H:i', strtotime((string)$verbindung['gekoppelt_am']))); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left;">Kennung dort</th>
                    <td><code><?php echo $h($verbindung['portal_id']); ?></code></td>
                </tr>
                <tr>
                    <th style="text-align:left;">Gekoppelt von</th>
                    <td>
                        <code><?php echo $h($verbindung['installation'] !== '' ? $verbindung['installation'] : '(vor Migration 13)'); ?></code>
                        <?php if ($herkunft['ok'] && (string)$verbindung['installation'] !== ''): ?>
                            <br><small>Diese Installation: <code><?php echo $h($kennung); ?></code> – passt.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th style="text-align:left;">Letzter Abgleich</th>
                    <td>
                        <?php if ($laufZeit === false): ?>
                            <strong>noch keiner.</strong> Die Kopplung steht, aber
                            der Abgleich ist nie gelaufen – solange kommt auf der
                            Homepage kein einziger Mitarbeiter an.
                        <?php else: ?>
                            <?php echo $h(date('d.m.Y H:i:s', $laufZeit)); ?>
                            (vor <?php echo (int)$laufAlter; ?> Minuten,
                             <?php echo (int)($verbindung['letzte_dauer_ms'] ?? 0); ?> ms)
                            <?php if ((int)($verbindung['letzter_lauf_ok'] ?? 0) !== 1): ?>
                                <br><strong>Der letzte Lauf ist gescheitert:</strong>
                                <?php echo $h($verbindung['letzter_fehler']); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th style="text-align:left;">Freigeschaltet</th>
                    <td>
                        <?php if ($freigegeben === 0): ?>
                            <strong>niemand.</strong> Solange kein Mitarbeiter
                            freigeschaltet ist, steht auf der Homepage nichts und
                            niemand kann sich dort anmelden – die Liste dafür steht
                            weiter unten.
                        <?php else: ?>
                            <?php echo $freigegeben; ?> Mitarbeiter
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:1rem;">
            <form method="post" action="?seite=portal_admin" style="display:inline;">
                <?php echo Csrf::feld($csrfBereich); ?>
                <input type="hidden" name="aktion" value="abgleich">
                <button type="submit">Jetzt abgleichen</button>
            </form>
            <form method="post" action="?seite=portal_admin" style="display:inline;margin-left:0.5rem;">
                <?php echo Csrf::feld($csrfBereich); ?>
                <input type="hidden" name="aktion" value="probe">
                <button type="submit">Verbindung prüfen</button>
            </form>
            <form method="post" action="?seite=portal_admin" style="display:inline;margin-left:0.5rem;"
                  onsubmit="return confirm('Verbindung trennen? Diese Installation ruft danach nicht mehr an. Die gespiegelten Daten auf der Homepage bleiben stehen, bis sie dort gelöscht werden.');">
                <?php echo Csrf::feld($csrfBereich); ?>
                <input type="hidden" name="aktion" value="entkoppeln">
                <button type="submit">Verbindung trennen</button>
            </form>
        </p>

        <h3>Zuletzt angekommen</h3>
        <?php if (count($eingang) === 0): ?>
            <p>Noch nichts. Entweder hat noch niemand etwas im Portal eingetragen,
               oder der Abgleich ist noch nie gelaufen.</p>
        <?php else: ?>
            <p><small>Jeder Auftrag wird <strong>genau einmal</strong> ausgeführt –
               auch wenn ein Anruf mittendrin abbricht und die Homepage ihn ein
               zweites Mal herausgibt. Dafür steht jede Nummer aus dieser Liste
               fest in <code>portal_eingang</code>.</small></p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nr.</th><th>Wer</th><th>Was</th><th>Ergebnis</th><th>Wann</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($eingang as $e): ?>
                    <tr>
                        <td><?php echo (int)$e['fremd_id']; ?></td>
                        <td><?php echo $h($e['name'] ?? '–'); ?></td>
                        <td><?php echo $h($artText[(string)$e['art']] ?? (string)$e['art']); ?></td>
                        <td>
                            <?php if ((string)$e['ergebnis'] === 'angenommen'): ?>
                                angenommen<?php if (!empty($e['urlaubsantrag_id'])): ?>
                                    <small>(Antrag <?php echo (int)$e['urlaubsantrag_id']; ?>)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong>abgelehnt</strong><br>
                                <small><?php echo $h($e['hinweis'] ?? ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $h(date('d.m.Y H:i', strtotime((string)$e['verarbeitet_am']))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($verbindung !== null): ?>
        <h3>Freischaltung</h3>
        <p>
            <strong>Diese Liste ist die eigentliche Entscheidung.</strong> Ein
            freigeschalteter Mitarbeiter steht mit Namen, Urlaubszahlen und
            Stundensaldo auf einem Server im Internet, ein nicht freigeschalteter
            mit keinem Byte. Deshalb gibt es hier bewusst kein »alle freischalten«.
        </p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Kennung</th>
                    <th>Zugang</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($belegschaft as $m): ?>
                <?php
                    $mid       = (int)$m['id'];
                    $frei      = (int)$m['portal_aktiv'] === 1;
                    $kennung   = (string)($vorschlaege[$mid] ?? '');
                    $codeOffen = ($m['portal_aktivierung_hash'] ?? null) !== null
                                 && $m['portal_aktivierung_bis'] !== null
                                 && strtotime((string)$m['portal_aktivierung_bis']) > time();
                    $aktiviert = $m['portal_aktiviert_am'] !== null;
                ?>
                <tr>
                    <td><?php echo $h($m['nachname'] . ', ' . $m['vorname']); ?></td>
                    <td>
                        <?php if ($frei): ?>
                            <code><?php echo $h($m['portal_kennung']); ?></code>
                        <?php else: ?>
                            <small>Vorschlag: <code><?php echo $h($kennung); ?></code></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$frei): ?>
                            <small>nicht freigeschaltet</small>
                        <?php elseif ($aktiviert): ?>
                            eingerichtet am <?php echo $h(date('d.m.Y', strtotime((string)$m['portal_aktiviert_am']))); ?>
                            <?php if ($m['portal_letzte_anmeldung_am'] !== null): ?>
                                <br><small>zuletzt angemeldet
                                <?php echo $h(date('d.m.Y H:i', strtotime((string)$m['portal_letzte_anmeldung_am']))); ?></small>
                            <?php endif; ?>
                        <?php elseif ($codeOffen): ?>
                            <small>wartet auf die erste Anmeldung,<br>Code gilt bis
                            <?php echo $h(date('d.m.Y', strtotime((string)$m['portal_aktivierung_bis']))); ?></small>
                        <?php else: ?>
                            <strong>kein gültiger Code</strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                        <?php if (!$frei): ?>
                            <form method="post" action="?seite=portal_admin" style="display:inline;">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <input type="hidden" name="aktion" value="freischalten">
                                <input type="hidden" name="mitarbeiter_id" value="<?php echo $mid; ?>">
                                <input type="text" name="kennung" value="<?php echo $h($kennung); ?>"
                                       size="14" style="font-family:monospace;" spellcheck="false">
                                <button type="submit">Freischalten</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="?seite=portal_admin" style="display:inline;">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <input type="hidden" name="aktion" value="code">
                                <input type="hidden" name="mitarbeiter_id" value="<?php echo $mid; ?>">
                                <button type="submit"><?php echo $codeOffen || $aktiviert ? 'Neuer Code' : 'Code erzeugen'; ?></button>
                            </form>
                            <form method="post" action="?seite=portal_admin" style="display:inline;"
                                  onsubmit="return confirm('Freischaltung entziehen? Beim nächsten Abgleich verschwindet der Mitarbeiter samt Konto, Zahlen und Anträgen von der Website.');">
                                <?php echo Csrf::feld($csrfBereich); ?>
                                <input type="hidden" name="aktion" value="sperren">
                                <input type="hidden" name="mitarbeiter_id" value="<?php echo $mid; ?>">
                                <button type="submit">Entziehen</button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h3>Was die Homepage von hier bekommt</h3>
    <p>
        Zu jedem <strong>freigeschalteten</strong> Mitarbeiter: Name, Portal-Kennung,
        Urlaubszahlen, Stundensaldo, Tageslisten der Monate – und der
        Aktivierungscode als Hash, solange er noch nicht eingelöst ist. Nicht
        übertragen werden Geburtsdatum, Lohndaten, Krankheitsgründe, RFID-Codes,
        Passwörter dieser Installation und alles zu Mitarbeitern, die nicht
        freigeschaltet sind. Wird eine Freischaltung entzogen, verschwindet der
        Mitarbeiter beim nächsten Abgleich vollständig von der Homepage.
    </p>

    <h3>Eine andere Website anbinden</h3>
    <p>
        Das Portal ist an keine bestimmte Website gebunden. Angekoppelt werden
        kann jede, die den Endpunkt <code>/portal-api</code> anbietet – die
        Zeiterfassung fragt beim Koppeln nur nach einer Adresse.
    </p>
    <p>
        Wer das für eine eigene Website bauen will, muss nicht bei null
        anfangen: Das Paket unten enthält eine <strong>vollständige,
        lauffähige Gegenseite</strong> zum Kopieren – vier PHP-Dateien ohne
        Rahmenwerk, das Datenbankschema und eine Anleitung, die vom leeren
        Verzeichnis bis zum ersten Abgleich führt.
    </p>
    <ul class="klein">
        <li><code>portal-api.php</code> – der Endpunkt, die ganze Gegenseite in einer Datei</li>
        <li><code>portal-konfig.php</code> – das Einzige, was anzupassen ist</li>
        <li><code>kopplungscode.php</code> – erzeugt den Code für den Handschlag</li>
        <li><code>mitarbeiter.php</code> – Beispielseite: anmelden, Zahlen sehen, Urlaub beantragen</li>
        <li><code>schema.sql</code> – sechs Tabellen</li>
        <li><code>README.md</code> – Anleitung, Protokoll, Feldnamen, Fallstricke</li>
    </ul>
    <p>
        <a class="button-link" href="?seite=portal_admin&amp;paket=1"
           download="mitarbeiterportal-beispiel.zip">Beispielpaket herunterladen (ZIP)</a>
    </p>
    <p class="klein">
        Ein Hinweis daraus, der die meiste Zeit spart: Unterschrieben wird mit
        <code>hash('sha256', $schluessel)</code>, <strong>nicht</strong> mit dem
        Schlüssel selbst. Beide Seiten rechnen mit diesem Hash – im Klartext
        liegt der Schlüssel nur hier.
    </p>
</section>
<?php require __DIR__ . '/../layout/footer.php'; ?>
