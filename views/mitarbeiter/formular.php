<?php
declare(strict_types=1);

/**
 * Backend-Formular: Mitarbeiter anlegen / bearbeiten
 *
 * Erwartet:
 * - $mitarbeiter (array<string,mixed>|null)
 * - $fehlermeldung (string|null)
 * - $rollen (array<int,array<string,mixed>>) – optional, vollständige Rollenobjekte des Mitarbeiters (nur Anzeige/Zusatzinfos)
 * - $genehmiger (array<int,array<string,mixed>>) – Genehmiger-Datensätze für dieses Formular
 * - $alleRollen (array<int,array<string,mixed>>) – alle aktiven Rollen für die Checkbox-Auswahl
 * - $rollenIdsAusgewaehlt (array<int,int>) – Rollen-IDs, die aktuell für diesen Mitarbeiter gesetzt sind
  * - $alleMitarbeiterGenehmiger (array<int,array<string,mixed>>) – alle aktiven Mitarbeiter für die Genehmiger-Auswahl
*/

require __DIR__ . '/../layout/header.php';

$mitarbeiter   = $mitarbeiter ?? null;
/** @var array<int,array<string,mixed>> $rollen */
$rollen        = $rollen ?? [];
/** @var array<int,array<string,mixed>> $genehmiger */
$genehmiger    = $genehmiger ?? [];
/** @var array<int,array<string,mixed>> $alleRollen */
$alleRollen    = $alleRollen ?? [];
/** @var array<int,array<string,mixed>> $alleMitarbeiterGenehmiger */
$alleMitarbeiterGenehmiger = $alleMitarbeiterGenehmiger ?? [];
/** @var array<int,array<string,mixed>> $alleRechte */
$alleRechte = $alleRechte ?? [];
/** @var array<int,array<string,mixed>> $alleAbteilungen */
$alleAbteilungen = $alleAbteilungen ?? [];
/** @var array<int,array<string,mixed>> $rollenScopesAbteilung */
$rollenScopesAbteilung = $rollenScopesAbteilung ?? [];

/** @var array<int,int> $rechteOverrides */
$rechteOverrides = $rechteOverrides ?? [];
/** @var array<int,int> $rollenIdsAusgewaehlt */
$rollenIdsAusgewaehlt = $rollenIdsAusgewaehlt ?? [];
$fehlermeldung = $fehlermeldung ?? null;
$successmeldung = $successmeldung ?? null;

$rollenIdsAusgewaehlt = array_map('intval', $rollenIdsAusgewaehlt);

$id                 = $mitarbeiter['id'] ?? null;
$vorname            = trim((string)($mitarbeiter['vorname'] ?? ''));
$nachname           = trim((string)($mitarbeiter['nachname'] ?? ''));
$geburtsdatum       = (string)($mitarbeiter['geburtsdatum'] ?? '');
$eintrittsdatum     = (string)($mitarbeiter['eintrittsdatum'] ?? '');
$benutzername       = trim((string)($mitarbeiter['benutzername'] ?? ''));
$email              = trim((string)($mitarbeiter['email'] ?? ''));
$personalnummer     = trim((string)($mitarbeiter['personalnummer'] ?? ''));
$rfidCode           = trim((string)($mitarbeiter['rfid_code'] ?? ''));
$wochenarbeitszeit  = (string)($mitarbeiter['wochenarbeitszeit'] ?? '');
$urlaubMonatsanspr  = (string)($mitarbeiter['urlaub_monatsanspruch'] ?? '');
$stammabteilungId   = (int)($mitarbeiter['stammabteilung_id'] ?? 0);
$abteilungenIds    = $mitarbeiter['abteilungen_ids'] ?? [];
if (!is_array($abteilungenIds)) {
    $abteilungenIds = [];
}
$abteilungenLookup = [];
foreach ($abteilungenIds as $aidRoh) {
    $aid = (int)$aidRoh;
    if ($aid > 0) {
        $abteilungenLookup[$aid] = true;
    }
}
if ($stammabteilungId > 0) {
    $abteilungenLookup[(int)$stammabteilungId] = true;
}
$aktiv              = isset($mitarbeiter['aktiv']) ? (bool)$mitarbeiter['aktiv'] : true;
$loginErlaubt       = $id === null
    ? false
    : (isset($mitarbeiter['ist_login_berechtigt']) ? (bool)$mitarbeiter['ist_login_berechtigt'] : false);

$ueberschrift = $id === null ? 'Neuen Mitarbeiter anlegen' : 'Mitarbeiter bearbeiten';
?>

<section>
    <h2><?php echo htmlspecialchars($ueberschrift, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>

    <?php if ($fehlermeldung !== null): ?>
        <p class="error">
            <?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if ($successmeldung !== null): ?>
        <p class="success">
            <?php echo htmlspecialchars($successmeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="post" action="?seite=mitarbeiter_admin_speichern">
        <input type="hidden" name="id" value="<?php echo $id !== null ? (int)$id : ''; ?>">

        <fieldset>
            <legend>Stammdaten</legend>

            <div>
                <label for="vorname">Vorname *</label><br>
                <input type="text" name="vorname" id="vorname" required
                       value="<?php echo htmlspecialchars($vorname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="nachname">Nachname *</label><br>
                <input type="text" name="nachname" id="nachname" required
                       value="<?php echo htmlspecialchars($nachname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="geburtsdatum">Geburtsdatum</label><br>
                <input type="date" name="geburtsdatum" id="geburtsdatum"
                       value="<?php echo htmlspecialchars($geburtsdatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="eintrittsdatum">Eintrittsdatum</label><br>
                <input type="date" name="eintrittsdatum" id="eintrittsdatum"
                       value="<?php echo htmlspecialchars($eintrittsdatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="wochenarbeitszeit">Wochenarbeitszeit (Stunden)</label><br>
                <input type="number" step="0.25" min="0" name="wochenarbeitszeit" id="wochenarbeitszeit"
                       value="<?php echo htmlspecialchars($wochenarbeitszeit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="urlaub_monatsanspruch">Urlaub (Stunden pro Monat)</label><br>
                <input type="number" step="0.25" min="0" name="urlaub_monatsanspruch" id="urlaub_monatsanspruch"
                       value="<?php echo htmlspecialchars($urlaubMonatsanspr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>
            <div>
                <label>Abteilungen</label><br>

                <?php if (count($alleAbteilungen) === 0): ?>
                    <p>Es sind noch keine Abteilungen definiert.</p>
                    <input type="hidden" name="stammabteilung_id" value="0">
                <?php else: ?>
                    <style>
                        .abt-ui { max-width: 1100px; }
                        .abt-ui__toolbar { display: flex; align-items: center; gap: 0.75rem; margin: 0.25rem 0 0.5rem 0; flex-wrap: wrap; }
                        .abt-ui__filter { width: 320px; max-width: 100%; }
                        .abt-ui__info { opacity: 0.75; }
                        .abt-ui__list { border: 1px solid #ddd; border-radius: 4px; max-height: 240px; overflow: auto; }
                        .abt-ui__table { width: 100%; border-collapse: collapse; }
                        .abt-ui__thead th { position: sticky; top: 0; background: #f3f3f3; z-index: 1; }
                        .abt-ui__cell, .abt-ui__thead th { padding: 6px; border-bottom: 1px solid #eee; }
                        .abt-ui__cell--check { width: 110px; }
                        .abt-ui__cell--stamm { width: 110px; }
                        .abt-ui__row--fixed .abt-ui__cell { background: #fafafa; }
                        .abt-ui__muted { opacity: 0.85; }
                    </style>

                    <div class="abt-ui">
                        <div class="abt-ui__toolbar">
                            <input type="search" id="abt_filter" class="abt-ui__filter" placeholder="Abteilung suchen …" autocomplete="off">
                            <small id="abt_filter_info" class="abt-ui__info"></small>
                        </div>

                        <div class="abt-ui__list" role="group" aria-label="Abteilungen auswählen">
                            <table class="abt-ui__table">
                                <thead class="abt-ui__thead">
                                    <tr>
                                        <th class="abt-ui__cell abt-ui__cell--check" align="left">Mitglied</th>
                                        <th class="abt-ui__cell abt-ui__cell--stamm" align="left">Stamm</th>
                                        <th class="abt-ui__cell" align="left">Abteilung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="abt-ui__row abt-ui__row--fixed" data-fixed="1" data-abt-name="">
                                        <td class="abt-ui__cell abt-ui__cell--check"></td>
                                        <td class="abt-ui__cell abt-ui__cell--stamm">
                                            <label style="display:inline-flex;align-items:center;gap:0.35rem;margin:0;">
                                                <input type="radio" id="stammabteilung_none" name="stammabteilung_id" value="0" <?php echo ((int)$stammabteilungId <= 0) ? 'checked' : ''; ?>>
                                                keine
                                            </label>
                                        </td>
                                        <td class="abt-ui__cell"><span class="abt-ui__muted">keine Stammabteilung</span></td>
                                    </tr>

                                    <?php foreach ($alleAbteilungen as $abt): ?>
                                        <?php $aid = (int)($abt['id'] ?? 0); ?>
                                        <?php if ($aid <= 0) continue; ?>
                                        <?php $aname = (string)($abt['name'] ?? ''); ?>

                                        <?php $istMitglied = isset($abteilungenLookup[$aid]); ?>
                                        <?php $istStamm = ($aid === (int)$stammabteilungId); ?>
                                        <?php
                                            $anameFilter = trim($aname);
                                            if (function_exists('mb_strtolower')) {
                                                $anameFilter = mb_strtolower($anameFilter, 'UTF-8');
                                            } else {
                                                $anameFilter = strtolower($anameFilter);
                                            }
                                        ?>

                                        <tr class="abt-ui__row" data-abt-name="<?php echo htmlspecialchars($anameFilter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                            <td class="abt-ui__cell abt-ui__cell--check">
                                                <label style="display:inline-flex;align-items:center;gap:0.35rem;margin:0;">
                                                    <input type="checkbox"
                                                           id="abt_mitglied_<?php echo $aid; ?>"
                                                           class="abt-check"
                                                           data-aid="<?php echo $aid; ?>"
                                                           name="abteilungen_ids[]"
                                                           value="<?php echo $aid; ?>"
                                                           <?php echo $istMitglied ? 'checked' : ''; ?>>
                                                    <span>ja</span>
                                                </label>
                                            </td>
                                            <td class="abt-ui__cell abt-ui__cell--stamm">
                                                <label style="display:inline-flex;align-items:center;gap:0.35rem;margin:0;opacity:0.9;">
                                                    <input type="radio"
                                                           id="abt_stamm_<?php echo $aid; ?>"
                                                           class="abt-stamm"
                                                           data-aid="<?php echo $aid; ?>"
                                                           name="stammabteilung_id"
                                                           value="<?php echo $aid; ?>"
                                                           <?php echo $istStamm ? 'checked' : ''; ?>
                                                           <?php echo $istMitglied ? '' : 'disabled'; ?>>
                                                    <span>ja</span>
                                                </label>
                                            </td>
                                            <td class="abt-ui__cell">
                                                <label for="abt_mitglied_<?php echo $aid; ?>" style="cursor:pointer;">
                                                    <?php echo htmlspecialchars($aname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <small>Optional: Mitarbeiter kann in mehreren Abteilungen sein oder in keiner.</small>
                    </div>

                    <script>
                    (function () {
                      function bySelectorAll(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }

                      function syncStammDisable(aid) {
                        var chk = document.querySelector('.abt-check[data-aid="' + aid + '"]');
                        var rad = document.querySelector('.abt-stamm[data-aid="' + aid + '"]');
                        if (!chk || !rad) return;

                        if (chk.checked) {
                          rad.disabled = false;
                        } else {
                          // Wenn Stammabteilung abgewählt wird, automatisch auf "keine" zurücksetzen
                          if (rad.checked) {
                            var none = document.getElementById('stammabteilung_none');
                            if (none) none.checked = true;
                          }
                          rad.disabled = true;
                        }
                      }

                      function applyFilter() {
                        var inp = document.getElementById('abt_filter');
                        var info = document.getElementById('abt_filter_info');
                        if (!inp) return;

                        var q = (inp.value || '').toLowerCase().trim();
                        var rows = bySelectorAll('.abt-ui__row');

                        var total = 0;
                        var visible = 0;

                        rows.forEach(function (row) {
                          var fixed = row.getAttribute('data-fixed') === '1';
                          if (fixed) {
                            row.style.display = '';
                            return;
                          }

                          total++;
                          var name = (row.getAttribute('data-abt-name') || '').toLowerCase();
                          var show = (q === '' || name.indexOf(q) !== -1);
                          row.style.display = show ? '' : 'none';
                          if (show) visible++;
                        });

                        if (!info) return;
                        if (q === '') {
                          info.textContent = '';
                        } else {
                          info.textContent = visible + ' von ' + total + ' angezeigt';
                        }
                      }

                      function init() {
                        // Initial: disabled/enabled korrekt setzen
                        bySelectorAll('.abt-check').forEach(function (chk) {
                          var aid = chk.getAttribute('data-aid') || '';
                          syncStammDisable(aid);

                          chk.addEventListener('change', function () {
                            syncStammDisable(aid);
                          });
                        });

                        // Wenn "Stamm" gewählt wird, Mitgliedschaft automatisch aktivieren
                        bySelectorAll('.abt-stamm').forEach(function (rad) {
                          var aid = rad.getAttribute('data-aid') || '';
                          rad.addEventListener('change', function () {
                            if (!rad.checked) return;
                            var chk = document.querySelector('.abt-check[data-aid="' + aid + '"]');
                            if (chk && !chk.checked) {
                              chk.checked = true;
                              syncStammDisable(aid);
                            }
                          });
                        });

                        var inp = document.getElementById('abt_filter');
                        if (inp) {
                          inp.addEventListener('input', applyFilter);
                          applyFilter();
                        }
                      }

                      if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init);
                      } else {
                        init();
                      }
                    })();
                    </script>
                <?php endif; ?>
            </div>

        </fieldset>

        <fieldset>
            <legend>Login / Zugang</legend>

            <div>
                <label for="benutzername">Benutzername</label><br>
                <input type="text" name="benutzername" id="benutzername"
                       value="<?php echo htmlspecialchars($benutzername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="email">E-Mail</label><br>
                <input type="email" name="email" id="email"
                       value="<?php echo htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="personalnummer">Personalnummer</label><br>
                <input type="text" name="personalnummer" id="personalnummer"
                       value="<?php echo htmlspecialchars($personalnummer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <small>Wird z. B. für Terminal-Login (Personalnummer) genutzt.</small>
            </div>

            <div>
                <label for="rfid_code">RFID-Code</label><br>
                <input type="text" name="rfid_code" id="rfid_code"
                       value="<?php echo htmlspecialchars($rfidCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>

            <div>
                <label>
                    <input type="checkbox" name="ist_login_berechtigt" value="1"
                        <?php echo $loginErlaubt ? 'checked' : ''; ?>>
                    Login über Benutzername/E-Mail erlaubt
                </label>
            </div>

            <div>
                <label>
                    <input type="checkbox" name="aktiv" value="1"
                        <?php echo $aktiv ? 'checked' : ''; ?>>
                    Mitarbeiter ist aktiv
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Passwort</legend>
            <p>Nur ausfüllen, wenn das Passwort geändert oder gesetzt werden soll.</p>

            <div>
                <label for="passwort_neu">Neues Passwort</label><br>
                <input type="password" name="passwort_neu" id="passwort_neu" autocomplete="new-password">
            </div>
        </fieldset>


        <fieldset>
            <legend>Rollen (Berechtigungen)</legend>

            <?php if (count($alleRollen) === 0): ?>
                <p>Es sind noch keine Rollen definiert.</p>
            <?php else: ?>
                <?php foreach ($alleRollen as $rolle): ?>
                    <?php
                        $rid   = (int)($rolle['id'] ?? 0);
                        $rName = trim((string)($rolle['name'] ?? ''));
                        $rDesc = (string)($rolle['beschreibung'] ?? '');
                        $checked = in_array($rid, $rollenIdsAusgewaehlt, true) ? 'checked' : '';
                    ?>
                    <div>
                        <label>
                            <input type="checkbox" name="rollen_ids[]" value="<?php echo htmlspecialchars((string)$rid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $checked; ?>>
                            <?php echo htmlspecialchars($rName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            <?php if ($rDesc !== ''): ?>
                                – <?php echo htmlspecialchars($rDesc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
                <p><small>Leere Auswahl bedeutet: Mitarbeiter hat aktuell keine speziellen Rollen/Berechtigungen.</small></p>
                <p><small>Hinweis: Die oben ausgewählten Rollen werden als <b>global</b> gespeichert.</small></p>

                <hr>

                <p><small><b>Abteilungs-Rollen (Phase 1)</b>: Optional kannst du Rollen zusätzlich auf eine Abteilung einschränken (auf Wunsch inkl. Unterbereiche). Änderungen werden beim Speichern übernommen.</small></p>

                <?php
                    $rolleNameById = [];
                    foreach ($alleRollen as $rTmp) {
                        $ridTmp = (int)($rTmp['id'] ?? 0);
                        $rolleNameById[$ridTmp] = (string)($rTmp['name'] ?? ('#' . $ridTmp));
                    }
                    $abtNameById = [];
                    foreach ($alleAbteilungen as $aTmp) {
                        $aidTmp = (int)($aTmp['id'] ?? 0);
                        $abtNameById[$aidTmp] = (string)($aTmp['name'] ?? ('#' . $aidTmp));
                    }

                    $addRolleSelected = (int)($_POST['scope_abteilung_add_rolle_id'] ?? 0);
                    $addAbtSelected   = (int)($_POST['scope_abteilung_add_abteilung_id'] ?? 0);
                    $addUnterChecked  = isset($_POST['scope_abteilung_add_unterbereiche']) ? 'checked' : 'checked';
                ?>

                <?php if (count($rollenScopesAbteilung) === 0): ?>
                    <p><small>Keine Abteilungs-Rollen gesetzt.</small></p>
                <?php else: ?>
                    <table border="0" cellpadding="4" cellspacing="0">
                        <thead>
                            <tr>
                                <th align="left">Rolle</th>
                                <th align="left">Abteilung</th>
                                <th align="left">Unterbereiche</th>
                                <th align="left">Löschen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rollenScopesAbteilung as $entry): ?>
                                <?php
                                    $eid  = (int)($entry['id'] ?? 0);
                                    $rid  = (int)($entry['rolle_id'] ?? 0);
                                    $sid  = (int)($entry['scope_id'] ?? 0);
                                    $gilt = (int)($entry['gilt_unterbereiche'] ?? 0) === 1 ? 'checked' : '';
                                    $rName = $rolleNameById[$rid] ?? ('#' . $rid);
                                    $aName = $abtNameById[$sid] ?? ('#' . $sid);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($aName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td>
                                        <input type="hidden" name="scope_abteilung_row_ids[]" value="<?php echo (int)$eid; ?>">
                                        <label>
                                            <input type="checkbox" name="scope_abteilung_unterbereiche[<?php echo (int)$eid; ?>]" value="1" <?php echo $gilt; ?>>
                                            gilt
                                        </label>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="scope_abteilung_delete[]" value="<?php echo (int)$eid; ?>">
                                            löschen
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <p style="margin-top:10px;"><small><b>Neu hinzufügen</b> (ein Eintrag pro Speichern):</small></p>
                <div style="margin-bottom:6px;">
                    <label>Rolle:
                        <select name="scope_abteilung_add_rolle_id">
                            <option value="0">—</option>
                            <?php foreach ($alleRollen as $rolle): ?>
                                <?php $rid = (int)($rolle['id'] ?? 0); $rName = (string)($rolle['name'] ?? ''); ?>
                                <option value="<?php echo (int)$rid; ?>" <?php echo ($rid === $addRolleSelected ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($rName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div style="margin-bottom:6px;">
                    <label>Abteilung:
                        <select name="scope_abteilung_add_abteilung_id">
                            <option value="0">—</option>
                            <?php foreach ($alleAbteilungen as $abt): ?>
                                <?php $aid = (int)($abt['id'] ?? 0); $aName = (string)($abt['name'] ?? ''); ?>
                                <option value="<?php echo (int)$aid; ?>" <?php echo ($aid === $addAbtSelected ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($aName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div style="margin-bottom:6px;">
                    <label>
                        <input type="checkbox" name="scope_abteilung_add_unterbereiche" value="1" <?php echo $addUnterChecked; ?>>
                        gilt für Unterbereiche
                    </label>
                </div>
                <p><small>Hinweis: Unterbereiche beziehen sich auf die Abteilungs-Hierarchie (parent_id). Die Rechte-Auswertung wird im nächsten Schritt schrittweise umgesetzt.</small></p>
<?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>Rechte-Overrides (pro Mitarbeiter)</legend>
            <p><small>Standard: Rechte kommen über Rollen. Hier kannst du einzelne Rechte zusätzlich <b>erlauben</b> oder explizit <b>entziehen</b>. Leer = vererbt über Rollen.</small></p>

            <?php if (count($alleRechte) === 0): ?>
                <p>Es sind noch keine Rechte definiert.</p>
            <?php else: ?>
				<?php
					$rechteVererbt = (isset($rechteVererbt) && is_array($rechteVererbt)) ? $rechteVererbt : [];
					$rechteEffektiv = (isset($rechteEffektiv) && is_array($rechteEffektiv)) ? $rechteEffektiv : [];
				?>
                <div>
                <?php
                /**
                 * Rechte-Gruppierung (UI-Only): bessere Übersicht in "Mitarbeiter bearbeiten" (Overrides).
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

                foreach ($alleRechte as $recht) {
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
                            <table style="margin:0;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;">Code</th>
                                        <th style="text-align:left;">Name</th>
                                        <th style="text-align:left;">Effektiv</th>
                                        <th style="text-align:left;">Override</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rechteInGruppe as $recht): ?>
                                        <?php
                                            $rid  = (int)($recht['id'] ?? 0);
                                            if ($rid <= 0) {
                                                continue;
                                            }
                                            $code = (string)($recht['code'] ?? '');
                                            $name = (string)($recht['name'] ?? '');
                                            $aktivRecht = (int)($recht['aktiv'] ?? 1) === 1;

                                            $sel = array_key_exists($rid, $rechteOverrides) ? (int)$rechteOverrides[$rid] : null;
                                            $selInherit = $sel === null ? 'selected' : '';
                                            $selAllow   = $sel === 1 ? 'selected' : '';
                                            $selDeny    = $sel === 0 ? 'selected' : '';

                                            $vererbt = isset($rechteVererbt[$rid]);
                                            $effektiv = isset($rechteEffektiv[$rid]);

                                            $effText = 'NEIN';
                                            $effColor = '#b00';
                                            if (!$aktivRecht) {
                                                $effText = 'INAKTIV';
                                                $effColor = '#777';
                                            } elseif ($sel === 1) {
                                                $effText = 'JA (Override)';
                                                $effColor = '#090';
                                            } elseif ($sel === 0) {
                                                $effText = 'NEIN (Override)';
                                                $effColor = '#b00';
                                            } elseif ($effektiv) {
                                                $effText = $vererbt ? 'JA (Rolle)' : 'JA';
                                                $effColor = '#090';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $aktivRecht ? '' : ' <small>(inaktiv)</small>'; ?></td>
                                            <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td><span style="font-weight:bold;color:<?php echo htmlspecialchars($effColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;"><?php echo htmlspecialchars($effText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></td>
                                            <td>
                                                <select name="recht_override[<?php echo htmlspecialchars((string)$rid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>]">
                                                    <option value="" <?php echo $selInherit; ?>>vererbt (Rolle)</option>
                                                    <option value="1" <?php echo $selAllow; ?>>erlauben</option>
                                                    <option value="0" <?php echo $selDeny; ?>>entziehen</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </fieldset>

        

        <fieldset>
            <legend>Genehmiger</legend>
            <p>Optional: Hier können eine oder mehrere Personen hinterlegt werden, die diesen Mitarbeiter z.&nbsp;B. für Urlaube oder Zeiten freigeben.</p>

            <?php
            $anzahlGenehmigerZeilen = max(count($genehmiger) + 2, 3);
            $aktuellerMitarbeiterId = $id !== null ? (int)$id : 0;

            for ($i = 0; $i < $anzahlGenehmigerZeilen; $i++):
                $row   = $genehmiger[$i] ?? null;
                $gid   = $row['genehmiger_mitarbeiter_id'] ?? '';
                $prio  = $row['prioritaet'] ?? '';
                $besch = $row['kommentar'] ?? '';

                $anzeigeName = '';
                if (is_array($row)) {
                    $nameTeile = [];

                    if (!empty($row['genehmiger_vorname'] ?? null)) {
                        $nameTeile[] = (string)$row['genehmiger_vorname'];
                    }
                    if (!empty($row['genehmiger_nachname'] ?? null)) {
                        $nameTeile[] = (string)$row['genehmiger_nachname'];
                    }

                    if (empty($nameTeile) && !empty($row['genehmiger_name'] ?? null)) {
                        $nameTeile[] = (string)$row['genehmiger_name'];
                    }

                    if (!empty($nameTeile)) {
                        $anzeigeName = implode(' ', $nameTeile);
                    }
                }

                $gidInt = $gid === '' ? 0 : (int)$gid;
            ?>
            <?php $zeilenKlasse = $i === 0 ? 'genehmiger-zeile genehmiger-zeile-haupt' : 'genehmiger-zeile'; ?>
            <div class="<?php echo $zeilenKlasse; ?>">
                <label>
                    Genehmiger (Mitarbeiter)
                    <select name="genehmiger_id[]">
                        <option value="">-- bitte wählen --</option>
                        <?php foreach ($alleMitarbeiterGenehmiger as $mgMit):
                            $mgId = (int)($mgMit['id'] ?? 0);
                            if ($mgId <= 0 || $mgId === $aktuellerMitarbeiterId) {
                                continue;
                            }

                            $nameTeile = [];
                            if (!empty($mgMit['nachname'] ?? null)) {
                                $nameTeile[] = (string)$mgMit['nachname'];
                            }
                            if (!empty($mgMit['vorname'] ?? null)) {
                                $nameTeile[] = (string)$mgMit['vorname'];
                            }

                            if (empty($nameTeile) && !empty($mgMit['benutzername'] ?? null)) {
                                $nameTeile[] = (string)$mgMit['benutzername'];
                            }

                            if (empty($nameTeile)) {
                                $nameTeile[] = 'ID ' . $mgId;
                            }

                            $optionLabel = implode(', ', $nameTeile);
                            $selected    = $mgId === $gidInt ? ' selected' : '';
                        ?>
                            <option value="<?php echo $mgId; ?>"<?php echo $selected; ?>>
                                <?php echo htmlspecialchars($optionLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Priorität (1 = Hauptgenehmiger, höhere Zahl = Fallback)
                    <input type="number" name="genehmiger_prio[]" value="<?php echo htmlspecialchars((string)$prio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" min="1">
                </label>

                <label>
                    Beschreibung / Hinweis
                    <input type="text" name="genehmiger_beschreibung[]" value="<?php echo htmlspecialchars((string)$besch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>

                <?php if ($anzeigeName !== ''): ?>
                    <small>(Aktuell: <?php echo htmlspecialchars($anzeigeName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
                <?php endif; ?>
            </div>
            <?php endfor; ?>

            <p><small>Leere Zeilen werden ignoriert. Der Mitarbeiter kann nicht sein eigener Genehmiger sein.</small></p>
        </fieldset>

        <p>
            <button type="submit">Speichern</button>
            <a href="?seite=mitarbeiter_admin">Abbrechen</a>
        </p>
    </form>

    <?php
    // Stundenkonto (Korrekturen/Saldo)
    $stundenkontoDarfVerwalten = $stundenkontoDarfVerwalten ?? false;
    $stundenkontoSaldoAktuellText = $stundenkontoSaldoAktuellText ?? null;
    $stundenkontoLetzteKorrekturen = $stundenkontoLetzteKorrekturen ?? [];
    $stundenkontoLetzteBatches = $stundenkontoLetzteBatches ?? [];
    $stundenkontoStealthMode = $stundenkontoStealthMode ?? false;

    $fmtMin = static function (int $minuten): string {
        $sign = $minuten < 0 ? '-' : '+';
        $abs = abs($minuten);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        return sprintf('%s%d:%02d', $sign, $h, $m);
    };
    ?>

    <?php if ($id !== null): ?>
        <hr>
        <h3>Stundenkonto</h3>

        <p>
            <strong>Saldo Stand heute:</strong>
            <?php echo htmlspecialchars($stundenkontoSaldoAktuellText ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>

        <h4>Letzte Verteilbuchungen / manuelle Korrekturbuchungen</h4>

        <?php if (is_array($stundenkontoLetzteKorrekturen) && count($stundenkontoLetzteKorrekturen) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Wirksam</th>
                        <th>Delta</th>
                        <th>Typ</th>
                        <th>Begründung</th>
                        <th>Erstellt</th>
                        <th>Von</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stundenkontoLetzteKorrekturen as $row):
                        $w = (string)($row['wirksam_datum'] ?? '');
                        $dmin = (int)($row['delta_minuten'] ?? 0);
                        $typ = (string)($row['typ'] ?? '');
                        $begr = (string)($row['begruendung'] ?? '');
                        $erst = (string)($row['erstellt_am'] ?? '');
                        $vonV = trim((string)($row['erstellt_von_vorname'] ?? ''));
                        $vonN = trim((string)($row['erstellt_von_nachname'] ?? ''));
                        $von = trim(($vonV . ' ' . $vonN));
                        if ($von === '') {
                            $von = '—';
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($fmtMin($dmin), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($typ, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($begr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($erst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><small>Noch keine Stundenkonto-Korrekturen vorhanden.</small></p>
        <?php endif; ?>

        <h4>Letzte Verteilbuchungen (Batch)</h4>

        <?php if (is_array($stundenkontoLetzteBatches) && count($stundenkontoLetzteBatches) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zeitraum</th>
                        <th>Modus</th>
                        <th>Tage</th>
                        <th>Summe</th>
                        <th>Pro Tag</th>
                        <th>Arbeitstage</th>
                        <th>Begründung</th>
                        <th>Erstellt</th>
                        <th>Von</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stundenkontoLetzteBatches as $b):
                        $bid = (int)($b['id'] ?? 0);
                        $von = (string)($b['von_datum'] ?? '');
                        $bis = (string)($b['bis_datum'] ?? '');
                        $modus = (string)($b['modus'] ?? '');
                        $anzahlTage = (int)($b['anzahl_tage'] ?? 0);
                        $gesamtMin = isset($b['gesamt_minuten']) ? (int)$b['gesamt_minuten'] : null;
                        $mpt = isset($b['minuten_pro_tag']) ? (int)$b['minuten_pro_tag'] : null;
                        $nur = (int)($b['nur_arbeitstage'] ?? 1) === 1;
                        $begr = (string)($b['begruendung'] ?? '');
                        $erst = (string)($b['erstellt_am'] ?? '');
                        $vonV = trim((string)($b['erstellt_von_vorname'] ?? ''));
                        $vonN = trim((string)($b['erstellt_von_nachname'] ?? ''));
                        $vonName = trim($vonV . ' ' . $vonN);
                        if ($vonName === '') {
                            $vonName = '—';
                        }

                        $modusLabel = $modus;
                        if ($modus === 'gesamt_gleichmaessig') {
                            $modusLabel = 'Gesamt gleichmäßig';
                        } elseif ($modus === 'minuten_pro_tag') {
                            $modusLabel = 'Pro Tag';
                        }

                        $sumMin = $gesamtMin;
                        if ($sumMin === null && $mpt !== null && $anzahlTage > 0) {
                            $sumMin = $mpt * $anzahlTage;
                        }

                        $sumTxt = $sumMin !== null ? $fmtMin((int)$sumMin) : '—';
                        $proTagTxt = $mpt !== null ? $fmtMin((int)$mpt) : '—';
                    ?>
                        <tr>
                            <td><?php echo (int)$bid; ?></td>
                            <td><?php echo htmlspecialchars($von . ' bis ' . $bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($modusLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo (int)$anzahlTage; ?></td>
                            <td><?php echo htmlspecialchars($sumTxt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($proTagTxt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo $nur ? 'Ja' : 'Nein'; ?></td>
                            <td><?php echo htmlspecialchars($begr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($erst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($vonName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><small>Hinweis: Arbeitstage = Montag bis Freitag (Feiertage/Betriebsferien werden aktuell nicht automatisch erkannt).</small></p>
        <?php else: ?>
            <p><small>Noch keine Verteilbuchungen vorhanden.</small></p>
        <?php endif; ?>

        <?php if ($stundenkontoDarfVerwalten): ?>
            <h4>Manuelle Korrektur buchen</h4>
            <form method="post" action="?seite=mitarbeiter_admin_speichern">
                <input type="hidden" name="stundenkonto_only" value="1">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <?php if ($stundenkontoStealthMode): ?>
                    <input type="hidden" name="stundenkonto_stealth" value="1">
                <?php endif; ?>

                <div>
                    <label>
                        Delta (Stunden) – positiv = Gutschrift, negativ = Abzug
                        <input type="number" step="0.25" name="stundenkonto_delta_stunden" required placeholder="z.B. 1.25 oder -0.25">
                    </label>
                </div>

                <div>
                    <label>
                        Wirksam ab
                        <input type="date" name="stundenkonto_wirksam_datum" value="<?php echo htmlspecialchars((new \DateTimeImmutable('today'))->format('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    </label>
                </div>

                <div>
                    <label>
                        Begründung
                        <input type="text" name="stundenkonto_begruendung" required placeholder="z.B. Korrektur wegen ...">
                    </label>
                </div>

                <p>
                    <button type="submit">Korrektur buchen</button>
                </p>
            </form>

            <h4>Verteilbuchung buchen</h4>
            <form method="post" action="?seite=mitarbeiter_admin_speichern">
                <input type="hidden" name="stundenkonto_batch_only" value="1">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <?php if ($stundenkontoStealthMode): ?>
                    <input type="hidden" name="stundenkonto_stealth" value="1">
                <?php endif; ?>

                <div>
                    <label>
                        Modus
                        <select name="stundenkonto_batch_modus" required>
                            <option value="gesamt_gleichmaessig">Gesamtstunden gleichmäßig auf Tage verteilen</option>
                            <option value="minuten_pro_tag">Gutschrift/Abzug pro Tag (fix)</option>
                        </select>
                    </label>
                </div>

                <div>
                    <label>
                        Delta (Stunden)
                        <input type="number" step="0.25" name="stundenkonto_batch_delta_stunden" required placeholder="z.B. 20 oder -20 oder 0.25">
                    </label>
                    <small>Bei "pro Tag" ist das Delta je Tag (z.B. 0.25 = 15 Minuten).</small>
                </div>

                <div>
                    <label>
                        Von
                        <input type="date" name="stundenkonto_batch_von_datum" required>
                    </label>
                    <label>
                        Bis
                        <input type="date" name="stundenkonto_batch_bis_datum" required>
                    </label>
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="stundenkonto_batch_nur_arbeitstage" value="1" checked>
                        Nur Arbeitstage (Mo-Fr)
                    </label>
                </div>

                <div>
                    <label>
                        Begründung
                        <input type="text" name="stundenkonto_batch_begruendung" required placeholder="z.B. Gesetzesänderung / Korrektur ...">
                    </label>
                </div>

                <p>
                    <button type="submit">Verteilbuchung buchen</button>
                </p>
            </form>
        <?php else: ?>
            <p><small>Hinweis: Du hast nicht das Recht <code>STUNDENKONTO_VERWALTEN</code>.</small></p>
        <?php endif; ?>
    <?php endif; ?>

</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
