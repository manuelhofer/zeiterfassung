<?php
declare(strict_types=1);
/**
 * Dashboard-Übersicht nach dem Login.
 *
 * Erwartet:
 * - $mitarbeiterName (string)
 */
require __DIR__ . '/../layout/header.php';

$mitarbeiterName = $mitarbeiterName ?? 'Unbekannt';
?>

<section>
    <h2>Willkommen, <?php echo htmlspecialchars($mitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    <p>Dies ist Ihre Startseite für die Zeiterfassung.</p>

    <?php if (!empty($pausenentscheidungFlash) && is_array($pausenentscheidungFlash)): ?>
        <?php
        $typ = (string)($pausenentscheidungFlash['typ'] ?? '');
        $txt = (string)($pausenentscheidungFlash['text'] ?? '');
        $bg  = ($typ === 'ok') ? '#f0fff4' : '#fff5f5';
        $bd  = ($typ === 'ok') ? '#2f855a' : '#b00020';
        ?>
        <div style="margin-top: 0.75rem; border: 1px solid <?php echo $bd; ?>; background: <?php echo $bg; ?>; border-radius: 10px; padding: 0.75rem 1rem;">
            <strong style="color:<?php echo $bd; ?>;"><?php echo htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        </div>
    <?php endif; ?>


    <ul>
        <li><a href="?seite=zeit_heute">Heutige Kommen/Gehen-Buchungen anzeigen</a></li>
        <li><a href="?seite=urlaub_meine">Eigene Urlaubsanträge einsehen</a></li>
        <li><a href="?seite=report_monat">Monatsübersicht Ihrer Arbeitszeiten</a></li>
    </ul>
</section>


<?php if (!empty($zeitUnstimmigkeitenFehler)): ?>
    <section>
        <div style="border: 2px solid #b00020; border-radius: 10px; padding: 0.9rem 1rem; background: #fff5f5;">
            <strong style="color:#b00020;">Zeitwarnungen konnten nicht geladen werden.</strong>
            <div style="margin-top: 0.45rem; color:#333;">Bitte error_log prüfen (DashboardController).</div>
        </div>
    </section>
<?php endif; ?>


<?php if (isset($zeitUnstimmigkeiten) && is_array($zeitUnstimmigkeiten) && count($zeitUnstimmigkeiten) > 0): ?>
    <section>
        <div style="border: 2px solid #b00020; border-radius: 10px; padding: 0.9rem 1rem; background: #fff5f5;">
            <div style="display:flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap;">
                <strong style="color:#b00020;">Achtung: Unvollständige Zeitbuchungen</strong>
                <span style="color:#666; font-size: 0.95rem;">(letzte <?php echo (int)($zeitUnstimmigkeitenTage ?? 14); ?> Tage)</span>
            </div>
            <div style="margin-top: 0.45rem; color:#333;">
                Mindestens ein Mitarbeiter hat eine <strong>ungleiche Anzahl</strong> von Kommen/Gehen-Stempeln (z. B. „Kommen“ ohne „Gehen“, „Gehen“ ohne „Kommen“ oder doppelte Stempel).
                Bitte prüfen und ggf. korrigieren.
            </div>

            <div style="margin-top: 0.65rem; overflow-x:auto;">
                <table style="border-collapse: collapse; width: 100%; min-width: 560px;">
                    <thead>
                    <tr>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Mitarbeiter</th>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Datum</th>
                        <th style="text-align:right; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Öffnen</th>
                        <th style="text-align:right; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Kommen</th>
                        <th style="text-align:right; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Gehen</th>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Aktion</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($zeitUnstimmigkeiten as $w):
                        $mid = (int)($w['mitarbeiter_id'] ?? 0);
                        $name = trim((string)($w['name'] ?? ''));
                        $datum = (string)($w['datum'] ?? '');

                        $jahr = 0;
                        $monat = 0;
                        try {
                            $dt = new \DateTimeImmutable($datum);
                            $jahr = (int)$dt->format('Y');
                            $monat = (int)$dt->format('n');
                        } catch (\Throwable $e) {
                            $jahr = 0;
                            $monat = 0;
                        }

                        $kannReportAndereFlag = (bool)($kannReportAndere ?? false);
                        $kannZeitEditAndereFlag = (bool)($kannZeitEditAndere ?? false);

                        $linkMonat = '';
                        if ($kannReportAndereFlag && $mid > 0 && $jahr > 0 && $monat > 0) {
                            $linkMonat = '?seite=report_monat&jahr=' . $jahr . '&monat=' . $monat . '&mitarbeiter_id=' . $mid;
                        }

                        $linkTag = '';
                        if ($kannZeitEditAndereFlag && $mid > 0 && $datum !== '') {
                            $linkTag = '?seite=zeit_heute&datum=' . urlencode($datum) . '&mitarbeiter_id=' . $mid;
                        }
                        ?>
                        <tr>
                            <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; white-space: nowrap;">
                                <?php echo htmlspecialchars($name !== '' ? $name : ('ID ' . (string)$mid), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; white-space: nowrap;">
                                <?php echo htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; text-align:right; white-space: nowrap;">
                                <?php echo (int)($w['anzahl_buchungen'] ?? 0); ?>
                            </td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; text-align:right; white-space: nowrap;">
                                <?php echo (int)($w['anzahl_kommen'] ?? 0); ?>
                            </td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; text-align:right; white-space: nowrap;">
                                <?php echo (int)($w['anzahl_gehen'] ?? 0); ?>
                            </td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; white-space: nowrap;">
                                <?php
                                    $hasAction = false;
                                    if ($linkMonat !== '') {
                                        $hasAction = true;
                                        echo '<a href="' . htmlspecialchars($linkMonat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Monat öffnen</a>';
                                    }
                                    if ($linkTag !== '') {
                                        if ($hasAction) {
                                            echo ' | ';
                                        }
                                        $hasAction = true;
                                        echo '<a href="' . htmlspecialchars($linkTag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Tag öffnen</a>';
                                    }
                                    if (!$hasAction) {
                                        echo '<span style="color:#999;">-</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($pausenEntscheidungenOffen) && is_array($pausenEntscheidungenOffen)): ?>
    <section>
        <div style="border: 2px solid #b00020; border-radius: 10px; padding: 0.9rem 1rem; background: #fff5f5;">
            <div style="display:flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap;">
                <strong style="color:#b00020;">Achtung: Pausen-Entscheidung nötig</strong>
                <span style="color:#666; font-size: 0.95rem;">(<?php echo (int)($pausenEntscheidungenOffenAnzahl ?? count($pausenEntscheidungenOffen)); ?> Fälle)</span>
            </div>
            <div style="margin-top: 0.45rem; color:#333;">
                In diesen Fällen wird aktuell <strong>keine Pause</strong> abgezogen, bis entschieden wurde.
            </div>

            <div style="margin-top: 0.65rem; overflow-x:auto;">
                <table style="border-collapse: collapse; width: 100%; min-width: 820px;">
                    <thead>
                    <tr>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Mitarbeiter</th>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Datum</th>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Kommen</th>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Gehen</th>
                        <th style="text-align:right; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Auto-Pause</th>
                        <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Entscheidung</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pausenEntscheidungenOffen as $r): ?>
                        <?php
                        $mid = (int)($r['mitarbeiter_id'] ?? 0);
                        $dn = (string)($r['name'] ?? '');
                        $dt = (string)($r['datum'] ?? '');
                        $ko = (string)($r['kommen'] ?? '');
                        $ge = (string)($r['gehen'] ?? '');
                        $am = (int)($r['auto_minuten'] ?? 0);
                        ?>
                        <tr>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem;"><?php echo htmlspecialchars($dn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem;"><?php echo htmlspecialchars($dt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem;">
                                <?php
                                $tagUrl = '?seite=zeit_heute&datum=' . urlencode($dt) . '&mitarbeiter_id=' . urlencode((string)$mid);
                                ?>
                                <a href="<?php echo htmlspecialchars($tagUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                   style="display:inline-block; padding: 0.25rem 0.55rem; border: 1px solid #666; border-radius: 6px; background:#fff; text-decoration:none; color:#111;">
                                    Tag öffnen
                                </a>
                            </td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem;"><?php echo htmlspecialchars($ko, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem;"><?php echo htmlspecialchars($ge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem; text-align:right;"><?php echo htmlspecialchars(sprintf('%.2f', ($am / 60.0)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</td>
                            <td style="border-bottom: 1px solid #eee; padding: 0.45rem 0.5rem;">
                                <form method="post" style="display:flex; gap: 0.4rem; flex-wrap: wrap;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($csrfToken ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <input type="hidden" name="pausenentscheidung_submit" value="1">
                                    <input type="hidden" name="mitarbeiter_id" value="<?php echo (int)$mid; ?>">
                                    <input type="hidden" name="datum" value="<?php echo htmlspecialchars($dt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                                    <button type="submit" name="entscheidung" value="ABZIEHEN"
                                            style="padding: 0.35rem 0.6rem; border: 1px solid #666; border-radius: 6px; background: #fff; cursor: pointer;">
                                        Pause abziehen
                                    </button>

                                    <button type="submit" name="entscheidung" value="NICHT_ABZIEHEN"
                                            style="padding: 0.35rem 0.6rem; border: 1px solid #666; border-radius: 6px; background: #fff; cursor: pointer;">
                                        Keine Pause
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>




<?php
// Admin-Schnellzugriff (nur anzeigen, wenn mindestens ein Admin-Menüpunkt sichtbar ist).
$hatAdminSchnellzugriff = (
    ($hatMitarbeiterAdminRecht ?? false)
    || ($hatAbteilungsAdminRecht ?? false)
    || ($hatMaschineAdminRecht ?? false)
    || ($hatRollenAdminRecht ?? false)
    || ($hatFeiertagAdminRecht ?? false)
    || ($hatRundungsregelAdminRecht ?? false)
    || ($hatKonfigurationAdminRecht ?? false)
    || ($hatBetriebsferienAdminRecht ?? false)
    || ($hatUrlaubKontingentAdminRecht ?? false)
    || ($hatQueueAdminRecht ?? false)
    || ($hatTerminalAdminRecht ?? false)
);
?>

<?php if ($hatAdminSchnellzugriff): ?>
    <section>
        <h2>Admin Schnellzugriff</h2>

        <div style="display:flex; gap: 1rem; flex-wrap: wrap; align-items: stretch;">
            <?php if (($hatMitarbeiterAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Mitarbeiter</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=mitarbeiter_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatAbteilungsAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Abteilungen</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=abteilung_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatRollenAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Rollen &amp; Rechte</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=rollen_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatMaschineAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Maschinen</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=maschine_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatFeiertagAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Feiertage</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=feiertag_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatBetriebsferienAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Betriebsferien</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=betriebsferien_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatUrlaubKontingentAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Urlaub-Kontingent</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=urlaub_kontingent_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatRundungsregelAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Rundungsregeln</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=zeit_rundungsregel_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatKonfigurationAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Konfiguration</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=konfiguration_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatTerminalAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Terminals</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=terminal_admin">öffnen</a></div>
                </div>
            <?php endif; ?>

            <?php if (($hatQueueAdminRecht ?? false)): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #ffffff; min-width: 240px; flex: 1 1 240px; max-width: 420px;">
                    <strong>Offline-Queue</strong>
                    <div style="margin-top: 0.45rem;"><a href="?seite=queue_admin">öffnen</a></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>


<?php
$hatSystemStatus = (isset($systemStatus) && is_array($systemStatus)) || (isset($queueKachel) && is_array($queueKachel));
?>

<?php if ($hatSystemStatus): ?>
    <section>
        <h2>Systemstatus</h2>

        <div style="margin: 0.5rem 0 1rem 0; border: 1px dashed #bbb; border-radius: 8px; padding: 0.75rem 1rem; background: #fcfcfc;">
            <div style="display:flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap;">
                <strong>Smoke-Test (Diagnose)</strong>
                <?php if (!(isset($smokeTest) && is_array($smokeTest))): ?>
                    <div style="display:flex; gap: 0.6rem; flex-wrap: wrap; align-items: baseline;">
                        <a href="?seite=dashboard&amp;smoke=1" style="font-size: 0.95rem;" onclick="return confirm('Smoke-Test ausführen? (read-only Checks; kann je nach System einige Sekunden dauern)');">ausführen</a>
                        <span style="color:#999;">|</span>
                        <a href="?seite=dashboard&amp;smoke=2" style="font-size: 0.95rem;" onclick="return confirm('FULL Smoke-Test ausführen? (enthält Schreibtests in DB-Transaktion + Rollback)');">voll (Rollback)</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($smokeTest) && is_array($smokeTest) && isset($smokeTest['checks']) && is_array($smokeTest['checks'])): ?>
                <div style="margin-top: 0.6rem; font-size: 0.95rem; color: #444;">
                    Gesamt: <strong><?php echo number_format((float)($smokeTest['total_ms'] ?? 0), 1, ',', '.'); ?> ms</strong>
                </div>

                <div style="overflow-x:auto; margin-top: 0.6rem;">
                    <table style="border-collapse: collapse; width: 100%; min-width: 520px;">
                        <thead>
                        <tr>
                            <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Check</th>
                            <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Status</th>
                            <th style="text-align:left; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">Details</th>
                            <th style="text-align:right; border-bottom: 1px solid #ddd; padding: 0.4rem 0.5rem;">ms</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($smokeTest['checks'] as $c):
                            $ok = (bool)($c['ok'] ?? false);
                            $statusTxt = $ok ? 'OK' : 'FEHLER';
                            $statusColor = $ok ? '#1b5e20' : '#b00020';
                            ?>
                            <tr>
                                <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; white-space: nowrap;">
                                    <?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </td>
                                <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; color: <?php echo $statusColor; ?>; font-weight: 700;">
                                    <?php echo $statusTxt; ?>
                                </td>
                                <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem;">
                                    <?php echo htmlspecialchars((string)($c['details'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </td>
                                <td style="border-bottom: 1px solid #eee; padding: 0.4rem 0.5rem; text-align: right; white-space: nowrap;">
                                    <?php echo number_format((float)($c['ms'] ?? 0), 1, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="margin-top: 0.45rem; font-size: 0.95rem; color: #666;">
                    Führt einen kleinen Testlauf über DB/ZIP/Report/PDF aus. Nur auf Klick – nicht bei jedem Dashboard-Load.
                </div>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap: 1rem; flex-wrap: wrap; align-items: stretch;">

            <?php if (isset($systemStatus) && is_array($systemStatus)): ?>
                <?php
                $hauptDbOk = (bool)($systemStatus['hauptdb_ok'] ?? false);
                $offlineStatus = (string)($systemStatus['offline_db_status'] ?? 'deaktiviert');
                $term = $systemStatus['terminals'] ?? null;

                $dbFarbe = $hauptDbOk ? '#1b5e20' : '#b00020';
                $dbBg = $hauptDbOk ? '#f3fff3' : '#fff4f4';

                $offlineText = 'Deaktiviert';
                if ($offlineStatus === 'ok') {
                    $offlineText = 'OK';
                } elseif ($offlineStatus === 'nicht_erreichbar') {
                    $offlineText = 'Nicht erreichbar';
                }
                ?>

                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: <?php echo $dbBg; ?>; min-width: 260px; flex: 1 1 260px; max-width: 520px;">
                    <strong>Hauptdatenbank</strong>
                    <div style="margin-top: 0.35rem;">
                        Status: <strong style="color: <?php echo $dbFarbe; ?>;"><?php echo $hauptDbOk ? 'OK' : 'NICHT ERREICHBAR'; ?></strong>
                    </div>
                    <div style="margin-top: 0.35rem; font-size: 0.9rem; color: #555;">
                        Offline-DB: <strong><?php echo htmlspecialchars($offlineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    </div>
                </div>


                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: #fafafa; min-width: 260px; flex: 1 1 260px; max-width: 520px;">
                    <div style="display:flex; justify-content: space-between; align-items: baseline; gap: 1rem;">
                        <strong>Terminals</strong>
                        <a href="?seite=terminal_admin" style="font-size: 0.95rem;">öffnen</a>
                    </div>

                    <?php if (!is_array($term)):
                        // Wenn die Haupt-DB down ist, gibt es keine Terminal-Summary.
                        ?>
                        <div style="margin-top: 0.5rem; font-size: 0.95rem; color: #666;">
                            Status konnte nicht geladen werden.
                        </div>
                    <?php else: ?>
                        <?php
                        $gesamt = (int)($term['gesamt'] ?? 0);
                        $aktiv = (int)($term['aktiv'] ?? 0);
                        $aktivTerminal = (int)($term['aktiv_terminal'] ?? 0);
                        $aktivBackend = (int)($term['aktiv_backend'] ?? 0);

                        $kgJa = (int)($term['aktiv_offline_kg_ja'] ?? 0);
                        $kgNein = (int)($term['aktiv_offline_kg_nein'] ?? 0);
                        $aufJa = (int)($term['aktiv_offline_auf_ja'] ?? 0);
                        $aufNein = (int)($term['aktiv_offline_auf_nein'] ?? 0);
                        ?>

                        <div style="margin-top: 0.55rem; display:flex; gap: 1.2rem; flex-wrap: wrap;">
                            <div>Aktiv: <strong><?php echo $aktiv; ?></strong> / <?php echo $gesamt; ?></div>
                            <div>Modus: <strong><?php echo $aktivTerminal; ?></strong> Terminal, <strong><?php echo $aktivBackend; ?></strong> Backend</div>
                        </div>

                        <div style="margin-top: 0.55rem; font-size: 0.95rem; color: #444;">
                            Offline Kommen/Gehen: <strong><?php echo $kgJa; ?></strong> ja, <strong><?php echo $kgNein; ?></strong> nein<br>
                            Offline Aufträge: <strong><?php echo $aufJa; ?></strong> ja, <strong><?php echo $aufNein; ?></strong> nein
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


            <?php if (isset($queueKachel) && is_array($queueKachel)): ?>
                <?php
                $verfuegbar = (bool)($queueKachel['verfuegbar'] ?? false);
                $offen = (int)($queueKachel['offen'] ?? 0);
                $fehler = (int)($queueKachel['fehler'] ?? 0);
                $quelle = (string)($queueKachel['quelle'] ?? '');
                $letzteAusf = (string)($queueKachel['letzte_ausfuehrung'] ?? '');

                $fehlerFarbe = $fehler > 0 ? '#b00020' : '#1b5e20';
                $boxBg = $fehler > 0 ? '#fff4f4' : '#f3fff3';
                ?>

                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 0.9rem 1rem; background: <?php echo $boxBg; ?>; min-width: 260px; flex: 1 1 260px; max-width: 520px;">
                    <div style="display:flex; justify-content: space-between; align-items: baseline; gap: 1rem;">
                        <strong>Offline-Queue</strong>
                        <a href="?seite=queue_admin" style="font-size: 0.95rem;">öffnen</a>
                    </div>

                    <?php if (!$verfuegbar): ?>
                        <div style="margin-top: 0.5rem; font-size: 0.95rem; color: #666;">Status konnte nicht geladen werden.</div>
                    <?php else: ?>
                        <div style="margin-top: 0.5rem; display:flex; gap: 1.2rem; flex-wrap: wrap;">
                            <div>Offen: <strong><?php echo $offen; ?></strong></div>
                            <div>Fehler: <strong style="color: <?php echo $fehlerFarbe; ?>;"><?php echo $fehler; ?></strong></div>
                        </div>

                        <?php if ($letzteAusf !== ''): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #555;">
                                Letzte Ausführung: <?php echo htmlspecialchars($letzteAusf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($quelle !== ''): ?>
                            <div style="margin-top: 0.25rem; font-size: 0.85rem; color: #777;">
                                Quelle: <?php echo $quelle === 'offline' ? 'Offline-DB' : ($quelle === 'haupt' ? 'Haupt-DB' : htmlspecialchars($quelle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>


    </section>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
