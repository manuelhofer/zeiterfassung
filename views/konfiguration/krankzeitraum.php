<?php
declare(strict_types=1);
/**
 * Template: Konfiguration – Krankzeiten (LFZ/KK)
 *
 * Liste und Formular auf einer Seite, dazu der Vorschlag „Wechsel nach
 * 6 Wochen" (Anzeige im PHP, Nachrechnen im JavaScript beim Tippen).
 *
 * Erwartet:
 * - $eintraege (array<int,array<string,mixed>>) – Zeilen aus `krankzeitraum`
 * - $mitarbeiterListe (array<int,array{id:int,name:string,aktiv:int}>)
 * - $form (array<string,mixed>) – Werte des Formulars
 * - $editId (int) – >0, wenn ein bestehender Zeitraum bearbeitet wird
 * - $formatKrankDatumAnzeige (callable(?string): string) – Datum für die
 *   Anzeige; der Controller braucht dieselbe Funktion für seine Meldungen und
 *   reicht sie deshalb weiter, statt sie hier ein zweites Mal zu schreiben
 * - optional: $ok (int) – 1 zeigt „Gespeichert."
 * - optional: $fehlermeldung (string|null), $hinweismeldung (string|null)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 * - $csrfBereich (string) – Bereichsname für `Csrf`, kommt aus dem Controller
 */
require __DIR__ . '/../layout/header.php';

$csrfBereich = (string)($csrfBereich ?? '');
/** @var array<int,array<string,mixed>> $eintraege */
$eintraege        = $eintraege ?? [];
$mitarbeiterListe = $mitarbeiterListe ?? [];
/** @var array<string,mixed> $form */
$form             = $form ?? [];
$editId           = (int)($editId ?? 0);
$ok               = (int)($ok ?? 0);
$fehlermeldung    = $fehlermeldung ?? null;
$hinweismeldung   = $hinweismeldung ?? null;
$ladefehler       = (bool)($ladefehler ?? false);
?>
<section>
    <h2>Krankzeiten (LFZ/KK)</h2>

<?php require __DIR__ . '/_tabzeile.php'; ?>

    <p class="muted" style="max-width:60rem;">
        Hier werden Krankzeiträume gepflegt, damit später im Report/PDF automatisch in die Spalten
        <strong>Krank LF</strong> (Lohnfortzahlung) und <strong>Krank KK</strong> (Krankenkasse) verteilt werden kann.
        Wechsel LFZ → KK wird als zweiter Zeitraum gepflegt.
    </p>

    <?php /* Nur beim Bearbeiten: Der Link leert das Formular. Auf der
             leeren Maske zeigt er auf die eigene Seite und tut nichts. */ ?>
    <?php if ($editId > 0): ?>
        <p>
            <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Neu anlegen</a>
        </p>
    <?php endif; ?>

    <?php if ($ok === 1): ?>
        <div class="erfolgsmeldung">Gespeichert.</div>
    <?php endif; ?>

    <?php if (!empty($hinweismeldung)): ?>
        <div class="notice"><?php echo htmlspecialchars((string)$hinweismeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="error"><?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="?seite=konfiguration_admin&amp;tab=krankzeitraum">
        <?php echo Csrf::feld($csrfBereich); ?>
        <input type="hidden" name="krank_action" value="speichern">
        <input type="hidden" name="id" value="<?php echo (int)($form['id'] ?? 0); ?>">

        <p>
            <label for="mitarbeiter_id">Mitarbeiter</label><br>
            <select id="mitarbeiter_id" name="mitarbeiter_id" required>
                <option value="0">-- bitte wählen --</option>
                <?php foreach ($mitarbeiterListe as $m): ?>
                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)($form['mitarbeiter_id'] ?? 0) === (int)$m['id']) ? 'selected' : ''; ?>>
                        <?php
                            $label = (string)$m['name'];
                            if ((int)($m['aktiv'] ?? 1) !== 1) {
                                $label .= ' (inaktiv)';
                            }
                            echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="typ">Typ</label><br>
            <select id="typ" name="typ" required>
                <option value="lfz" <?php echo ((string)($form['typ'] ?? 'lfz') === 'lfz') ? 'selected' : ''; ?>>Krank LF (Lohnfortzahlung)</option>
                <option value="kk" <?php echo ((string)($form['typ'] ?? '') === 'kk') ? 'selected' : ''; ?>>Krank KK (Krankenkasse)</option>
            </select>
        </p>

        <p>
            <label for="von_datum">Von</label><br>
            <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars((string)($form['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
        </p>

        <p>
            <label for="bis_datum">Bis (optional)</label><br>
            <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars((string)($form['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <br><small class="muted">Leer lassen = laufender Zeitraum</small>
        </p>

        <?php
            // Optionaler UX-Helper (T-071): Vorschlag für den Wechsel von Krank LF (Lohnfortzahlung) zu Krank KK nach 6 Wochen.
            // Faustregel: 6 Wochen = 42 Kalendertage ab Start (inkl. Starttag).
            // -> LFZ bis = Start + 41 Tage, KK ab = Start + 42 Tage.
            $v6wVon = trim((string)($form['von_datum'] ?? ''));
            $v6wBis = '';
            $v6wKkVon = '';
            if (((string)($form['typ'] ?? '')) === 'lfz' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v6wVon)) {
                try {
                    $dt = new DateTimeImmutable($v6wVon, new DateTimeZone('Europe/Berlin'));
                    $v6wBis = $dt->modify('+41 days')->format('Y-m-d');
                    $v6wKkVon = $dt->modify('+42 days')->format('Y-m-d');
                } catch (Throwable) {
                    // defensiv: keine Vorschläge anzeigen
                }
            }
            $v6wVonAnzeige = $formatKrankDatumAnzeige($v6wVon);
            $v6wBisAnzeige = $formatKrankDatumAnzeige($v6wBis);
            $v6wKkVonAnzeige = $formatKrankDatumAnzeige($v6wKkVon);
        ?>

        <div id="lfz6w_hinweis" class="notice" style="margin:-0.5rem 0 1rem 0; max-width:48rem;">
            <strong>Vorschlag „Wechsel nach 6 Wochen“</strong><br>
            <span class="muted">
                Start am <span id="lfz6w_von"><?php echo htmlspecialchars($v6wVonAnzeige, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span> →
                Krank LF bis <span id="lfz6w_bis"><?php echo htmlspecialchars($v6wBisAnzeige, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>,
                Krank KK ab <span id="lfz6w_kk"><?php echo htmlspecialchars($v6wKkVonAnzeige, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>.
            </span>
            <div style="margin-top:0.45rem;">
                <button type="button" id="lfz6w_apply">Bis automatisch setzen</button>
                <small class="muted" style="margin-left:0.5rem;">(setzt das Feld „Bis“ auf LFZ-Ende)</small>
            </div>
        </div>

        <script>
        (function(){
            var elTyp = document.getElementById('typ');
            var elVon = document.getElementById('von_datum');
            var elBis = document.getElementById('bis_datum');
            var box = document.getElementById('lfz6w_hinweis');
            var spVon = document.getElementById('lfz6w_von');
            var spBis = document.getElementById('lfz6w_bis');
            var spKk = document.getElementById('lfz6w_kk');
            var btn = document.getElementById('lfz6w_apply');

            function dateToIso(s){
                s = String(s || '').trim();
                if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
                    return s;
                }
                var m = s.match(/^(\d{2})[-.](\d{2})[-.](\d{4})$/);
                if (m) {
                    return m[3] + '-' + m[2] + '-' + m[1];
                }
                return '';
            }

            function isoToDisplay(s){
                s = String(s || '').trim();
                var m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                return m ? (m[3] + '.' + m[2] + '.' + m[1]) : s;
            }

            function isValidDateStr(s){
                return dateToIso(s) !== '';
            }

            function addDays(dateStr, days){
                var iso = dateToIso(dateStr);
                if (iso === '') return null;
                var p = iso.split('-');
                var y = parseInt(p[0], 10);
                var m = parseInt(p[1], 10) - 1;
                var d = parseInt(p[2], 10);
                if (!isFinite(y) || !isFinite(m) || !isFinite(d)) return null;
                var dt = new Date(Date.UTC(y, m, d));
                dt.setUTCDate(dt.getUTCDate() + days);
                return dt.toISOString().slice(0, 10);
            }

            function update(){
                if (!box || !elTyp || !elVon) return;
                var typ = String(elTyp.value || '').toLowerCase();
                var von = String(elVon.value || '').trim();

                if (typ !== 'lfz' || !isValidDateStr(von)) {
                    box.style.display = 'none';
                    return;
                }

                var bis = addDays(von, 41);
                var kk = addDays(von, 42);
                if (!bis || !kk) {
                    box.style.display = 'none';
                    return;
                }

                box.style.display = 'block';
                if (spVon) spVon.textContent = isoToDisplay(dateToIso(von));
                if (spBis) spBis.textContent = isoToDisplay(bis);
                if (spKk) spKk.textContent = isoToDisplay(kk);
                if (btn && btn.dataset) btn.dataset.lfzBis = bis;
            }

            if (btn) {
                btn.addEventListener('click', function(){
                    var v = (btn.dataset && btn.dataset.lfzBis) ? String(btn.dataset.lfzBis) : '';
                    if (elBis && isValidDateStr(v)) {
                        elBis.value = v;
                    }
                });
            }

            if (elTyp) elTyp.addEventListener('change', update);
            if (elVon) elVon.addEventListener('change', update);

            update();
        })();
        </script>

        <p>
            <label for="kommentar">Kommentar (optional)</label><br>
            <input type="text" id="kommentar" name="kommentar" value="<?php echo htmlspecialchars((string)($form['kommentar'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" maxlength="255" style="width:100%;max-width:48rem;">
        </p>

        <p>
            <label>
                <input type="checkbox" name="aktiv" value="1" <?php echo ((int)($form['aktiv'] ?? 1) === 1) ? 'checked' : ''; ?>>
                Aktiv
            </label>
        </p>

        <p>
            <button type="submit">Speichern</button>
            <?php if ((int)($form['id'] ?? 0) > 0): ?>
                <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum" style="margin-left:0.75rem;">Abbrechen</a>
            <?php endif; ?>
        </p>
    </form>

    <h3 style="margin-top:1.25rem;">Übersicht</h3>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Mitarbeiter</th>
            <th>Typ</th>
            <th>Zeitraum</th>
            <th>Kommentar</th>
            <th>Aktiv</th>
            <th>Aktionen</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($eintraege === []): ?>
            <?php /* Nach einem Lesefehler steht die Fehlermeldung schon
                     oben – „Keine Einträge." wäre daneben die falsche
                     Auskunft. */ ?>
            <?php if (!$ladefehler): ?>
                <tr><td colspan="7">Keine Einträge.</td></tr>
            <?php endif; ?>
        <?php else: ?>
            <?php foreach ($eintraege as $k): ?>
                <?php
                $id = (int)($k['id'] ?? 0);
                $mid = (int)($k['mitarbeiter_id'] ?? 0);
                $vn = trim((string)($k['m_vorname'] ?? ''));
                $nn = trim((string)($k['m_nachname'] ?? ''));
                $mName = trim($nn . ', ' . $vn);
                if ($mName === '') {
                    $mName = 'Mitarbeiter #' . $mid;
                }
                $typ = (string)($k['typ'] ?? '');
                $typText = $typ === 'kk' ? 'Krank KK' : 'Krank LF';
                $von = (string)($k['von_datum'] ?? '');
                $bis = (string)($k['bis_datum'] ?? '');
                $zeitraum = $formatKrankDatumAnzeige($von) . ' bis ' . ($bis !== '' ? $formatKrankDatumAnzeige($bis) : 'offen');
                $kommentar = (string)($k['kommentar'] ?? '');
                $aktiv = (int)($k['aktiv'] ?? 0) === 1;
                ?>
                <tr>
                    <td><?php echo (int)$id; ?></td>
                    <td><?php echo htmlspecialchars($mName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($typText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($zeitraum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($kommentar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                    <td>
                        <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                        <?php if ($typ === 'lfz' && $aktiv && $bis !== ''): ?>
                            <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum&amp;wechsel_kk_von=<?php echo (int)$id; ?>" style="margin-left:0.75rem;">Wechsel zu KK</a>
                        <?php endif; ?>
                        <form method="post" action="?seite=konfiguration_admin&amp;tab=krankzeitraum" style="display:inline;">
                            <?php echo Csrf::feld($csrfBereich); ?>
                            <input type="hidden" name="krank_action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                            <input type="hidden" name="aktiv" value="<?php echo $aktiv ? '0' : '1'; ?>">
                            <button type="submit"><?php echo $aktiv ? 'Deaktivieren' : 'Aktivieren'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
