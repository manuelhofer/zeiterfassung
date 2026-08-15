<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Mitarbeiterliste
 *
 * Erwartet:
 * - $mitarbeiterListe (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 * - $listenStatus (string)
 * - optional: $ladefehler (bool) – true, wenn die Liste nicht gelesen werden konnte
 */
require __DIR__ . '/../layout/header.php';

$mitarbeiterListe = $mitarbeiterListe ?? [];
$fehlermeldung    = $fehlermeldung ?? null;
$ladefehler       = (bool)($ladefehler ?? false);
$listenStatus     = ($listenStatus ?? 'aktiv') === 'inaktiv' ? 'inaktiv' : 'aktiv';
$zeigtInaktive    = $listenStatus === 'inaktiv';
?>

<section>
    <div class="page-header">
        <div>
            <h2><?php echo $zeigtInaktive ? 'Inaktive Mitarbeiter' : 'Mitarbeiterverwaltung'; ?></h2>
            <p>Mitarbeiter suchen, bearbeiten und die zugeh&ouml;rigen Verwaltungsbereiche &ouml;ffnen.</p>
        </div>
        <div class="action-row">
            <?php if ($zeigtInaktive): ?>
                <a class="button-link secondary" href="?seite=mitarbeiter_admin">Zu den aktiven</a>
            <?php else: ?>
                <a class="button-link secondary" href="?seite=mitarbeiter_admin&amp;status=inaktiv">Zu den inaktiven</a>
                <a class="button-link" href="?seite=mitarbeiter_admin_bearbeiten">Neuen Mitarbeiter anlegen</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($fehlermeldung !== null): ?>
        <p class="error"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php /* Bei einem Lesefehler ist die Liste ebenfalls leer – dann steht schon
             die Fehlermeldung da, und „keine Mitarbeiter erfasst" wäre daneben
             die falsche Auskunft. */ ?>
    <?php if (count($mitarbeiterListe) === 0): ?>
        <?php if (!$ladefehler): ?>
            <p>Es sind derzeit keine <?php echo $zeigtInaktive ? 'inaktiven' : 'aktiven'; ?> Mitarbeiter erfasst.</p>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>RFID-Code</th>
                    <th>Login erlaubt</th>
                    <th>Aktiv</th>
                    <th title="Resturlaub im laufenden Jahr; Betriebsferien und offene Anträge sind abgezogen">Urlaub übrig</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mitarbeiterListe as $mit): ?>
                    <?php
                        $id        = (int)($mit['id'] ?? 0);
                        $vorname   = trim((string)($mit['vorname'] ?? ''));
                        $nachname  = trim((string)($mit['nachname'] ?? ''));
                        $benutzer  = trim((string)($mit['benutzername'] ?? ''));
                        $email     = trim((string)($mit['email'] ?? ''));
                        $rfid      = trim((string)($mit['rfid_code'] ?? ''));
                        $loginOk   = (int)($mit['ist_login_berechtigt'] ?? 0) === 1;
                        $aktiv     = (int)($mit['aktiv'] ?? 0) === 1;

                        $nameVoll  = trim($vorname . ' ' . $nachname);

                        $urlaubUebrig = $mit['urlaub_uebrig'] ?? null;
                        $urlaubText = '';
                        if ($urlaubUebrig !== null) {
                            $urlaubZahl = (float)$urlaubUebrig;
                            $urlaubText = rtrim(rtrim(number_format($urlaubZahl, 2, ',', '.'), '0'), ',');
                        }
                    ?>
                    <tr>
                        <td class="numeric"><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($nameVoll, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($benutzer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($rfid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><span class="status-pill <?php echo $loginOk ? 'ok' : 'error'; ?>"><?php echo $loginOk ? 'Ja' : 'Nein'; ?></span></td>
                        <td><span class="status-pill <?php echo $aktiv ? 'ok' : 'error'; ?>"><?php echo $aktiv ? 'Ja' : 'Nein'; ?></span></td>
                        <td style="white-space:nowrap;">
                            <?php if ($urlaubText === ''): ?>
                                <small>–</small>
                            <?php else: ?>
                                <strong<?php echo ((float)$urlaubUebrig < 0) ? ' class="error"' : ''; ?>><?php echo htmlspecialchars($urlaubText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> Tage
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?seite=mitarbeiter_admin_bearbeiten&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                                <a href="?seite=mitarbeiter_rechte&amp;id=<?php echo (int)$id; ?>">Rollen &amp; Rechte</a>
                                <a href="?seite=mitarbeiter_stundenkonto&amp;mitarbeiter_id=<?php echo (int)$id; ?>">Stundenkonto</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
