<?php
declare(strict_types=1);
/**
 * Backend-Ansicht: Mitarbeiterliste
 *
 * Erwartet:
 * - $mitarbeiterListe (array<int,array<string,mixed>>)
 * - $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$mitarbeiterListe = $mitarbeiterListe ?? [];
$fehlermeldung    = $fehlermeldung ?? null;
?>

<section>
    <h2>Mitarbeiterverwaltung</h2>

    <p>
        <a href="?seite=mitarbeiter_admin_bearbeiten">Neuen Mitarbeiter anlegen</a>
    </p>

    <?php if ($fehlermeldung !== null): ?>
        <p class="error"><?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (count($mitarbeiterListe) === 0): ?>
        <p>Es sind derzeit keine aktiven Mitarbeiter erfasst.</p>
    <?php else: ?>
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
                    ?>
                    <tr>
                        <td><?php echo $id; ?></td>
                        <td><?php echo htmlspecialchars($nameVoll, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($benutzer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($rfid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td><?php echo $loginOk ? 'Ja' : 'Nein'; ?></td>
                        <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <a href="?seite=mitarbeiter_admin_bearbeiten&amp;id=<?php echo (int)$id; ?>">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
