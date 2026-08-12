<?php
declare(strict_types=1);
/**
 * Template: Urlaubskontingent – Formular (Mitarbeiter/Jahr)
 *
 * Erwartet:
 * - $mitarbeiter (array<string,mixed>|null) – null heißt: nur Meldung zeigen
 * - $mitarbeiterId, $jahr, $vorjahr, $aktuellesJahr (int)
 * - $csrfToken (string)
 * - die bereits formatierten Zahlen aus dem Controller: $standardAnspruch,
 *   $anspruchOverrideText, $uebertragTageText, $korrekturText,
 *   $autoUebertragText, $beispielSollText, $beispielDeltaText (string)
 * - $hatAnspruchOverride, $uebertragIstFest (bool), $uebertragFestAm (?string)
 * - $autoUebertragHinweis (string), $notiz (string)
 * - optional: $flashOk (string|null), $fehlermeldung (string|null)
 */
require __DIR__ . '/../layout/header.php';

$mitarbeiter   = $mitarbeiter ?? null;
$mitarbeiterId = (int)($mitarbeiterId ?? 0);
$jahr          = (int)($jahr ?? date('Y'));
$vorjahr       = (int)($vorjahr ?? ((int)date('Y') - 1));
$aktuellesJahr = (int)($aktuellesJahr ?? date('Y'));
$csrfToken     = (string)($csrfToken ?? '');
$flashOk       = $flashOk ?? null;
$fehlermeldung = $fehlermeldung ?? null;

$vorname  = $mitarbeiter !== null ? trim((string)($mitarbeiter['vorname'] ?? '')) : '';
$nachname = $mitarbeiter !== null ? trim((string)($mitarbeiter['nachname'] ?? '')) : '';
$aktiv    = $mitarbeiter !== null ? ((int)($mitarbeiter['aktiv'] ?? 0) === 1) : false;
?>
<section>
    <h2>Urlaubskontingent bearbeiten</h2>

    <p>
        <a href="?seite=urlaub_kontingent_admin&amp;jahr=<?php echo (int)$jahr; ?>">&laquo; Zurück zur Übersicht</a>
    </p>

    <?php if (!empty($flashOk)): ?>
        <div class="success">
            <?php echo htmlspecialchars((string)$flashOk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="fehlermeldung">
            <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($mitarbeiter !== null): ?>
        <p>
            Mitarbeiter: <strong><?php echo htmlspecialchars(trim($vorname . ' ' . $nachname), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            (ID: <?php echo (int)$mitarbeiterId; ?>, <?php echo $aktiv ? 'aktiv' : 'inaktiv'; ?>)
            <br>
            Jahr: <strong><?php echo (int)$jahr; ?></strong>
            <br>
            Jahr wechseln:
            <a href="?seite=urlaub_kontingent_admin_bearbeiten&amp;mitarbeiter_id=<?php echo (int)$mitarbeiterId; ?>&amp;jahr=<?php echo (int)$vorjahr; ?>">Vorjahr <?php echo (int)$vorjahr; ?></a>
            |
            <a href="?seite=urlaub_kontingent_admin_bearbeiten&amp;mitarbeiter_id=<?php echo (int)$mitarbeiterId; ?>&amp;jahr=<?php echo (int)$aktuellesJahr; ?>">Aktuelles Jahr <?php echo (int)$aktuellesJahr; ?></a>
        </p>

        <form method="post" action="?seite=urlaub_kontingent_admin_speichern">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="mitarbeiter_id" value="<?php echo (int)$mitarbeiterId; ?>">
            <input type="hidden" name="jahr" value="<?php echo (int)$jahr; ?>">

            <table style="max-width: 48rem;">
                <tbody>
                    <tr>
                        <th style="width: 18rem;">Anspruch (Standard)</th>
                        <td>
                            <?php echo htmlspecialchars((string)$standardAnspruch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage
                            <br>
                            <small>Berechnung: <code>urlaub_monatsanspruch * 12</code></small>
                        </td>
                    </tr>
                    <tr>
                        <th>Anspruch (Override)</th>
                        <td>
                            <input type="text" name="anspruch_override_tage" value="<?php echo htmlspecialchars((string)$anspruchOverrideText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="leer = Standard" style="width: 10rem;">
                            <?php if ($hatAnspruchOverride): ?>
                                <button type="submit" name="formular_aktion" value="anspruch_override_loeschen" style="margin-left:0.5rem;" onclick="return confirm('Anspruch-Override wirklich löschen? Danach gilt wieder der Standardanspruch.');">Override löschen</button>
                            <?php endif; ?>
                            <br>
                            <small>Optional. Leer lassen oder Override löschen, wenn Standard gelten soll. <code>0.00</code> ist ein gültiger Override auf null Tage.</small>
                        </td>
                    </tr>
                    <tr>
                        <th>Übertrag (auto)</th>
                        <td>
                            <strong><?php echo htmlspecialchars((string)$autoUebertragText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> Tage</strong>
                            <?php if ($autoUebertragHinweis !== ''): ?>
                                <small class="wert-fehlt">(<?php echo htmlspecialchars((string)$autoUebertragHinweis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</small>
                            <?php endif; ?>
                            <br>
                            <small>
                                Resturlaub aus dem Vorjahr (<?php echo (int)($jahr - 1); ?>) wird automatisch übernommen (Master v8, 12.3).<br>
                                Wenn du den Übertrag abweichend festlegen willst: <code>Manuell = gewünschter Übertrag - Auto-Übertrag</code>.<br>
                                Beispiel: Auto <?php echo htmlspecialchars((string)$autoUebertragText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> → Soll <?php echo htmlspecialchars((string)$beispielSollText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> ⇒ Manuell <?php echo htmlspecialchars((string)$beispielDeltaText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>.
                            </small>
                        </td>
                    </tr>

                    <tr>
                        <th>Übertrag (festgeschrieben)</th>
                        <td>
                            <input type="text" name="uebertrag_tage" value="<?php echo htmlspecialchars((string)$uebertragTageText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 10rem;"> Tage
                            <label style="margin-left:1rem;">
                                <input type="checkbox" name="uebertrag_neu_berechnen" value="1">
                                neu berechnen lassen
                            </label>
                            <br>
                            <small>
                                <?php if ($uebertragIstFest): ?>
                                    Festgeschrieben am
                                    <strong><?php echo htmlspecialchars((string)$uebertragFestAm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                                    – dieser Wert gilt und wird <strong>nicht</strong> überschrieben.
                                <?php else: ?>
                                    Noch nicht festgeschrieben – der Übertrag wird beim nächsten Aufruf aus dem
                                    Vorjahr berechnet und dann hier eingetragen.
                                <?php endif; ?>
                                Wer den Wert ändert, schreibt ihn damit fest. „Neu berechnen lassen" gibt ihn
                                wieder frei; die Eingabe wird dann verworfen.
                            </small>
                        </td>
                    </tr>

                    <tr>
                        <th>Manuell (+/- Tage)</th>
                        <td>
                            <input type="text" name="korrektur_tage" value="<?php echo htmlspecialchars((string)$korrekturText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 10rem;"> Tage
                            <br>
                            <small>Hier kannst du Urlaubstage manuell gut-/abbuchen (z. B. <code>+2.00</code> oder <code>-1.50</code>). Dieser Wert wird zusätzlich zum Auto-Übertrag addiert. Für Sonderfälle kannst du alternativ den Anspruch-Override setzen.</small>
                        </td>
                    </tr>
                    <tr>
                        <th>Notiz</th>
                        <td>
                            <input type="text" name="notiz" value="<?php echo htmlspecialchars((string)$notiz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" maxlength="255" style="width: 100%;">
                        </td>
                    </tr>
                </tbody>
            </table>

            <p style="margin-top: 1rem;">
                <button type="submit">Speichern</button>
            </p>

            <p>
                <small>Hinweis: Die Werte wirken sich direkt auf den Urlaubssaldo in "Mein Urlaub" aus (Anspruch + Übertrag(auto) + Manuell - genehmigt - offen). Wenn du den Auto-Übertrag reduzieren willst, nutze Manuell mit negativem Wert (Formel oben).</small>
            </p>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
