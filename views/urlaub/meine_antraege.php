<?php
declare(strict_types=1);
/**
 * Template: Eigene Urlaubsanträge
 *
 * Erwartet:
 * - $antraege (array<int,array<string,mixed>>)
 * Optional:
 * - $zeigeFormular (bool)
 * - $formular (array<string,string>)
 * - $meldung (string|null)
 * - $fehlermeldung (string|null)
 * - $csrfToken (string)
 */
require __DIR__ . '/../layout/header.php';

/** @var array<int,array<string,mixed>> $antraege */
$antraege = $antraege ?? [];
$zeigeFormular = (bool)($zeigeFormular ?? false);
$formular = $formular ?? ['von_datum' => '', 'bis_datum' => '', 'kommentar_mitarbeiter' => ''];
$meldung = $meldung ?? null;
$fehlermeldung = $fehlermeldung ?? null;
$csrfToken = (string)($csrfToken ?? '');

/** @var array<string,mixed>|null $urlaubSaldo */
$urlaubSaldo = (isset($urlaubSaldo) && is_array($urlaubSaldo)) ? $urlaubSaldo : null;

/** @var array<string,mixed>|null $urlaubVorschau */
$urlaubVorschau = (isset($urlaubVorschau) && is_array($urlaubVorschau)) ? $urlaubVorschau : null;
?>

<section>
    <h2>Meine Urlaubsanträge</h2>

    <p style="margin-top:0.25rem;">
        <a href="?seite=urlaub_meine&amp;aktion=neu">Neuen Urlaubsantrag stellen</a>
    </p>

    <?php if ($urlaubSaldo !== null): ?>
        <?php
        $saldoJahr = (int)($urlaubSaldo['jahr'] ?? (int)(new DateTimeImmutable('now'))->format('Y'));
        $vorjahr = $saldoJahr - 1;


        // Anzeige: Standard = nur Anträge im aktuellen Saldo-Jahr, optional alle Jahre via ?alle=1
        $zeigeAlleJahre = (isset($_GET['alle']) && (string)$_GET['alle'] === '1');
        $anzeigeJahr = $saldoJahr;

        $jahrStart = new DateTimeImmutable(sprintf('%04d-01-01', $anzeigeJahr));
        $jahrEnde  = new DateTimeImmutable(sprintf('%04d-12-31', $anzeigeJahr));

        $parseYmd = static function (string $ymd): ?DateTimeImmutable {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
            return ($dt instanceof DateTimeImmutable) ? $dt : null;
        };

        $hatAndereJahre = false;
        foreach ($antraege as $tmpA) {
            $v = $parseYmd((string)($tmpA['von_datum'] ?? ''));
            $b = $parseYmd((string)($tmpA['bis_datum'] ?? ''));
            if (!$v || !$b) {
                continue;
            }
            $inJahr = !($b < $jahrStart || $v > $jahrEnde);
            if (!$inJahr) {
                $hatAndereJahre = true;
                break;
            }
        }

        // v8: Verbrauchsreihenfolge: zuerst Übertrag (Vorjahr), dann Jahr YYYY.
        $toFloat = static function ($v): float {
            if (is_numeric($v)) {
                return (float)$v;
            }
            if (is_string($v)) {
                $v = preg_replace('/\s+/', '', $v);
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
                return is_numeric($v) ? (float)$v : 0.0;
            }
            return 0.0;
        };

        $fmtTage = static function ($val): string {
            $f = 0.0;
            if (is_numeric($val)) {
                $f = (float)$val;
            }
            return number_format($f, 2, ',', '.');
        };

        $uebertragAuto = $toFloat($urlaubSaldo['uebertrag'] ?? 0);
        $korrektur = $toFloat($urlaubSaldo['korrektur'] ?? 0);
        $anspruch = $toFloat($urlaubSaldo['anspruch'] ?? 0);

        // v8: Manuell (+/- Tage) wirkt zuerst auf den Übertrag (auto). Wenn der Übertrag dadurch < 0 fällt,
        // wird der Rest (negativ) vom Jahres-Anspruch abgezogen, damit die Summe exakt bleibt.
        $uebertragEff = $uebertragAuto + $korrektur;
        $jahrKorrektur = 0.0;
        if ($uebertragEff < 0.0) {
            $jahrKorrektur = $uebertragEff;
            $uebertragEff = 0.0;
        }

        $jahrKontingent = $anspruch + $jahrKorrektur;
        $verbraucht = $toFloat($urlaubSaldo['genommen'] ?? 0) + $toFloat($urlaubSaldo['beantragt'] ?? 0);

        // Darstellung: Wie viel wurde aus Übertrag vs. Jahr verbraucht?
        $uebertragVerbraucht = min($verbraucht, $uebertragEff);

        $uebertragVerfuegbar = max($uebertragEff - $verbraucht, 0.0);
        $restVerbrauchNachUebertrag = max($verbraucht - $uebertragEff, 0.0);
        $jahrVerbraucht = $restVerbrauchNachUebertrag;
        $jahrVerfuegbar = max($jahrKontingent - $restVerbrauchNachUebertrag, 0.0);

        $jahrVerbraucht = $restVerbrauchNachUebertrag;

        $saldoVerbleibend = $toFloat($urlaubSaldo['verbleibend'] ?? 0);
        $saldoGenommen = $toFloat($urlaubSaldo['genommen'] ?? 0);
        $saldoBeantragt = $toFloat($urlaubSaldo['beantragt'] ?? 0);
        $saldoHinweis = trim((string)($urlaubSaldo['hinweis'] ?? ''));

        // Info: Betriebsferien (Rest Jahr) - nur zur Anzeige/Transparenz
        $bfRestArbeitstage = null;
        try {
            $auth = AuthService::getInstanz();
            $mid = (int)($auth->holeAngemeldeteMitarbeiterId() ?? 0);

            if ($mid > 0) {
                $heute = new DateTimeImmutable('today');
                $startRest = ($heute < $jahrStart) ? $jahrStart : $heute;

                if ($startRest <= $jahrEnde) {
                    $bfRestArbeitstage = UrlaubService::getInstanz()->zaehleBetriebsferienArbeitstageFuerMitarbeiter(
                        $mid,
                        $startRest->format('Y-m-d'),
                        $jahrEnde->format('Y-m-d')
                    );
                } else {
                    $bfRestArbeitstage = 0.0;
                }
            }
        } catch (\Throwable $e) {
            $bfRestArbeitstage = null;
        }

        $bfRestText = ($bfRestArbeitstage === null) ? '' : $fmtTage((float)$bfRestArbeitstage);
        ?>

        <div style="border:1px solid #ddd;padding:0.5rem 0.75rem;margin-bottom:0.75rem;background:#f7f7f7;">
            <strong>Urlaub verfügbar</strong><br>

            <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-top:0.35rem;">
                <div style="min-width:320px;">
                    <div><strong>Übertrag (<?php echo (int)$vorjahr; ?>)</strong></div>
                    <div style="margin-top:0.15rem;line-height:1.35;">
                        War (Auto): <strong><?php echo htmlspecialchars($fmtTage($uebertragAuto), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        Korrektur (Manuell): <strong><?php echo htmlspecialchars($fmtTage($korrektur), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        Effektiv: <strong><?php echo htmlspecialchars($fmtTage($uebertragEff), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        Verbraucht: <strong><?php echo htmlspecialchars($fmtTage($uebertragVerbraucht), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        Übrig: <strong><?php echo htmlspecialchars($fmtTage($uebertragVerfuegbar), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage
                    </div>
                </div>

                <div style="min-width:320px;">
                    <div><strong><?php echo (int)$saldoJahr; ?></strong></div>
                    <div style="margin-top:0.15rem;line-height:1.35;">
                        Anspruch (Auto): <strong><?php echo htmlspecialchars($fmtTage($anspruch), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        <?php if ($jahrKorrektur !== 0.0): ?>
                            Rest Korrektur (aus Manuell): <strong><?php echo htmlspecialchars($fmtTage($jahrKorrektur), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        <?php endif; ?>
                        Effektiv: <strong><?php echo htmlspecialchars($fmtTage($jahrKontingent), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        Verbraucht: <strong><?php echo htmlspecialchars($fmtTage($jahrVerbraucht), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage<br>
                        Übrig: <strong><?php echo htmlspecialchars($fmtTage($jahrVerfuegbar), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage
                    </div>
                </div>
            </div>
            <div style="margin-top:0.35rem;">
                Gesamt übrig (abzgl. BF):
                <strong><?php echo htmlspecialchars($fmtTage($saldoVerbleibend), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                &nbsp;|&nbsp; Genehmigt (inkl. BF):
                <strong><?php echo htmlspecialchars($fmtTage($saldoGenommen), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                &nbsp;|&nbsp; Offen:
                <strong><?php echo htmlspecialchars($fmtTage($saldoBeantragt), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                <?php if ($bfRestText !== ''): ?>
                    &nbsp;|&nbsp; BF (Rest Jahr):
                    <strong><?php echo htmlspecialchars($bfRestText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                <?php endif; ?>
            </div>

            <?php if ($saldoHinweis !== ''): ?>
                <div style="margin-top:0.35rem;color:#555;"><small><?php echo htmlspecialchars($saldoHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small></div>
            <?php else: ?>
                <div style="margin-top:0.35rem;color:#555;"><small>Berechnung: Verbrauchsreihenfolge Übertrag (Vorjahr) → Jahr <?php echo (int)$saldoJahr; ?>. Wochenenden und Feiertage zählen nicht als Urlaubstage. Betriebsferien werden automatisch als Urlaub berücksichtigt (und sind in „Genehmigt (inkl. BF)“ enthalten).</small></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($meldung)): ?>
        <div style="background:#e8f5e9;border:1px solid #c8e6c9;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$meldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div style="background:#ffebee;border:1px solid #ffcdd2;padding:0.5rem 0.75rem;margin-bottom:0.75rem;">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($zeigeFormular): ?>
        <div style="border:1px solid #ddd;padding:0.75rem;margin-bottom:1rem;background:#fafafa;">
            <h3 style="margin-top:0;">Urlaubsantrag stellen</h3>

            <?php if ($urlaubSaldo !== null): ?>
                <?php
                $pvVerfuegbar = (string)($urlaubSaldo['verbleibend'] ?? '0.00');
                $pvHat = ($urlaubVorschau !== null && isset($urlaubVorschau['tage_antrag']));
                $pvTage = (string)($urlaubVorschau['tage_antrag'] ?? '0.00');
                $pvNach = (string)($urlaubVorschau['nach_antrag'] ?? '0.00');
                $pvWarn = trim((string)($urlaubVorschau['warnung'] ?? ''));
                $pvNachNum = (float)$pvNach;
                ?>

                <div style="border:1px solid #ddd;padding:0.5rem 0.75rem;margin-bottom:0.75rem;background:#f7f7f7;">
                    <strong>Vorschau</strong><br>
                    Verfügbar: <strong><?php echo htmlspecialchars($pvVerfuegbar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    <?php if ($pvHat): ?>
                        &nbsp;| Dieser Antrag: <strong><?php echo htmlspecialchars($pvTage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        &nbsp;| Nach diesem Antrag:
                        <strong<?php echo ($pvNachNum < 0 ? ' style="color:#b71c1c;"' : ''); ?>><?php echo htmlspecialchars($pvNach, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    <?php else: ?>
                        &nbsp;| Zeitraum wählen für Vorschau.
                    <?php endif; ?>

                    <?php if ($pvWarn !== ''): ?>
                        <div style="margin-top:0.35rem;color:#b71c1c;"><small><?php echo htmlspecialchars($pvWarn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=urlaub_meine&amp;aktion=neu">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
                    <div>
                        <label for="von_datum"><strong>Von</strong></label><br>
                        <input id="von_datum" name="von_datum" type="date" value="<?php echo htmlspecialchars((string)($formular['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="bis_datum"><strong>Bis</strong></label><br>
                        <input id="bis_datum" name="bis_datum" type="date" value="<?php echo htmlspecialchars((string)($formular['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                    </div>
                </div>

                <div style="margin-top:0.75rem;">
                    <label for="kommentar_mitarbeiter"><strong>Kommentar</strong> (optional)</label><br>
                    <textarea id="kommentar_mitarbeiter" name="kommentar_mitarbeiter" rows="3" style="width:100%;max-width:900px;"><?php echo htmlspecialchars((string)($formular['kommentar_mitarbeiter'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                </div>

                <p style="margin:0.5rem 0 0.75rem 0;color:#555;">
                    Hinweis: <strong>Tage gesamt</strong> wird als <strong>Arbeitstage</strong> berechnet (Mo–Fr).
                    Wochenenden und betriebsfreie Feiertage werden nicht gezählt. Betriebsferien werden im Antrag nicht gezählt (werden automatisch als Urlaub berücksichtigt).
                </p>

                <button type="submit">Antrag speichern</button>
                <a href="?seite=urlaub_meine" style="margin-left:0.75rem;">Abbrechen</a>
            </form>
        </div>
    <?php endif; ?>

    
    <?php if ($hatAndereJahre): ?>
        <div style="margin:0.25rem 0 0.75rem 0;">
            <?php if (!$zeigeAlleJahre): ?>
                <a href="?seite=urlaub_meine&amp;alle=1">Anträge aus allen Jahren anzeigen</a>
            <?php else: ?>
                <a href="?seite=urlaub_meine">Nur Anträge für <?php echo (int)$anzeigeJahr; ?> anzeigen</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php if ($antraege === []): ?>
        <p>Es liegen derzeit keine Urlaubsanträge vor.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Von</th>
                <th>Bis</th>
                <th>Tage gesamt</th>
                <th>Status</th>
                <th>Entscheidung</th>
                <th>Kommentar</th>
                <th>Aktion</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($antraege as $a):
                $vonStr = (string)($a['von_datum'] ?? '');
                $bisStr = (string)($a['bis_datum'] ?? '');
                $vonDt = $parseYmd($vonStr);
                $bisDt = $parseYmd($bisStr);
                $inJahr = true;
                if ($vonDt && $bisDt) {
                    $inJahr = !($bisDt < $jahrStart || $vonDt > $jahrEnde);
                }
                if (!$zeigeAlleJahre && !$inJahr) {
                    continue;
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($a['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($a['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($a['tage_gesamt'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($a['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td>
                        <?php
                        $status = (string)($a['status'] ?? '');
                        $entscheidungsDatum = (string)($a['entscheidungs_datum'] ?? '');
                        $entscheidungsName  = (string)($a['entscheidungs_mitarbeiter_name'] ?? '');

                        if ($status === 'genehmigt' || $status === 'abgelehnt') {
                            $parts = [];
                            if ($entscheidungsDatum !== '') {
                                $parts[] = htmlspecialchars($entscheidungsDatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            }
                            if ($entscheidungsName !== '') {
                                $parts[] = 'durch ' . htmlspecialchars($entscheidungsName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            }
                            echo $parts !== [] ? implode(' ', $parts) : '—';
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $kommentarM = trim((string)($a['kommentar_mitarbeiter'] ?? ''));
                        $kommentarG = trim((string)($a['kommentar_genehmiger'] ?? ''));
                        if ($kommentarM === '' && $kommentarG === '') {
                            echo '—';
                        } else {
                            if ($kommentarM !== '') {
                                echo nl2br(htmlspecialchars($kommentarM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                            }

                            if ($kommentarG !== '') {
                                if ($kommentarM !== '') {
                                    echo '<hr style="border:none;border-top:1px solid #eee;margin:0.5rem 0;">';
                                }
                                echo '<small><strong>Genehmiger:</strong> ' . nl2br(htmlspecialchars($kommentarG, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</small>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $status = (string)($a['status'] ?? '');
                        $antragId = (int)($a['id'] ?? 0);

                        if ($status === 'offen' && $antragId > 0):
                            ?>
                            <form method="post" action="?seite=urlaub_meine" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <input type="hidden" name="aktion" value="stornieren">
                                <input type="hidden" name="antrag_id" value="<?php echo (int)$antragId; ?>">
                                <button type="submit" onclick="return confirm('Diesen Urlaubsantrag wirklich stornieren?');">Stornieren</button>
                            </form>
                            <?php
                        else:
                            echo '—';
                        endif;
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
