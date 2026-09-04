<?php
declare(strict_types=1);
/**
 * Template: Mitarbeiterportal – Verbindung zur WERNIG-Homepage
 *
 * Erwartet:
 * - $verbindung (array|null)  – Zeile aus `portal_verbindung`, aktiv
 * - $freigegeben (int)        – wie viele Mitarbeiter freigeschaltet sind
 * - $eingang (array)          – die letzten verarbeiteten Aufträge
 * - $csrfBereich (string)     – Bereichsname für `Csrf`
 * - optional: $flashOk (string|null), $flashErr (string|null)
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? 'portal_admin');
$verbindung  = $verbindung ?? null;
$freigegeben = (int)($freigegeben ?? 0);
$eingang     = $eingang ?? [];
$flashOk     = $flashOk ?? null;
$flashErr    = $flashErr ?? null;

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
        WERNIG-Homepage. <strong>Diese Installation ruft dort an</strong>, holt neue
        Anträge ab und schickt den aktuellen Stand hin. Umgekehrt geht nichts: Die
        Homepage kennt weder die Adresse dieses Servers noch seine Datenbank und
        kann hier nichts abfragen.
    </p>

    <?php if (!empty($flashOk)): ?>
        <div class="hinweis" style="margin:0.5rem 0;"><?php echo $h($flashOk); ?></div>
    <?php endif; ?>
    <?php if (!empty($flashErr)): ?>
        <div class="fehlermeldung" style="margin:0.5rem 0;"><?php echo $h($flashErr); ?></div>
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
                       placeholder="https://wernig.com" spellcheck="false" autocomplete="off">
                <small>Mit <code>https://</code> davor und ohne Pfad dahinter. Der
                       Rest wird angehängt.</small>
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
                            niemand kann sich dort anmelden. Die Freischaltung
                            geschieht je Mitarbeiter in der Mitarbeiterverwaltung.
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
</section>
<?php require __DIR__ . '/../layout/footer.php'; ?>
