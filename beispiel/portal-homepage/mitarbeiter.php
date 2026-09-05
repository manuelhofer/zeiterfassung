<?php
declare(strict_types=1);

/**
 * Der Mitarbeiterbereich – das, was ein Mitarbeiter zu sehen bekommt.
 *
 * Bewusst schmucklos: Sie sollen das Gerüst sehen, nicht mein Aussehen.
 * Setzen Sie Ihr eigenes Layout darum herum; alles Fachliche steht oben im
 * PHP-Teil und ist vom HTML getrennt.
 *
 * WAS HIER PASSIERT
 *   1. Erstanmeldung: Kennung + Aktivierungscode vom Zettel + eigenes Passwort.
 *   2. Danach: Kennung + Passwort.
 *   3. Anzeige der gespiegelten Zahlen, alle aus `portal_spiegel`.
 *   4. Ein Urlaubsantrag legt einen Auftrag in den Briefkasten. Mehr nicht –
 *      **entschieden wird in der Zeiterfassung.** Diese Seite rechnet nichts
 *      und genehmigt nichts.
 */

session_start();
$pdo = require __DIR__ . '/portal-konfig.php';

$h = static fn ($t): string => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

/** Kennungen werden genauso zurechtgestutzt wie in der Zeiterfassung. */
function kennungNormalisieren(string $eingabe): string
{
    $wert = mb_strtolower(trim($eingabe));
    return mb_substr(preg_replace('/[^a-z0-9.\-]/', '', $wert) ?? '', 0, 60);
}

/** Leerzeichen und Bindestriche im Aktivierungscode sind egal. */
function codeNormalisieren(string $eingabe): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $eingabe) ?? '');
}

function spiegelTeil(PDO $pdo, string $teil, ?int $mitarbeiter): array
{
    $stmt = $pdo->prepare(
        'SELECT inhalt FROM portal_spiegel
          WHERE teil = ? AND ' . ($mitarbeiter === null ? 'mitarbeiter IS NULL' : 'mitarbeiter = ?')
    );
    $stmt->execute($mitarbeiter === null ? [$teil] : [$teil, $mitarbeiter]);
    $roh = $stmt->fetchColumn();
    return $roh === false ? [] : (json_decode((string)$roh, true) ?: []);
}

$fehler  = '';
$hinweis = '';
$ich     = null;

// --------------------------------------------------------------------------
// Abmelden
// --------------------------------------------------------------------------
if (($_GET['abmelden'] ?? '') !== '') {
    unset($_SESSION['portal_mitarbeiter']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
    exit;
}

// --------------------------------------------------------------------------
// Formulare
// --------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $was = (string)($_POST['was'] ?? '');

    // ---- Erstanmeldung mit Aktivierungscode ------------------------------
    if ($was === 'einrichten') {
        $kennung   = kennungNormalisieren((string)($_POST['kennung'] ?? ''));
        $code      = codeNormalisieren((string)($_POST['code'] ?? ''));
        $passwort  = (string)($_POST['passwort'] ?? '');
        $passwort2 = (string)($_POST['passwort2'] ?? '');

        if (mb_strlen($passwort) < 10) {
            $fehler = 'Das Passwort braucht mindestens 10 Zeichen.';
        } elseif ($passwort !== $passwort2) {
            $fehler = 'Die beiden Passwörter sind nicht gleich.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM portal_mitarbeiter WHERE kennung = ? AND aktiv = 1');
            $stmt->execute([$kennung]);
            $m = $stmt->fetch();

            $codeHash = hash('sha256', $code);
            $passt = $m !== false
                && (string)($m['aktivierung_hash'] ?? '') !== ''
                && hash_equals((string)$m['aktivierung_hash'], $codeHash)
                && $m['aktivierung_bis'] !== null
                && strtotime((string)$m['aktivierung_bis']) >= time()
                && !hash_equals((string)($m['aktivierung_hash_verbraucht'] ?? ''), $codeHash);

            if (!$passt) {
                // Eine Meldung für alle Fälle: Sie soll nicht verraten, welche
                // Kennung es gibt.
                $fehler = 'Kennung oder Aktivierungscode stimmt nicht, oder der Code ist '
                        . 'abgelaufen beziehungsweise schon benutzt. Im Betrieb einen neuen anfordern.';
            } else {
                $pdo->prepare(
                    'UPDATE portal_mitarbeiter
                        SET passwort_hash = ?, aktiviert_am = NOW(),
                            aktivierung_hash = NULL, aktivierung_hash_verbraucht = ?
                      WHERE fremd_id = ?'
                )->execute([password_hash($passwort, PASSWORD_DEFAULT), $codeHash, $m['fremd_id']]);

                $_SESSION['portal_mitarbeiter'] = (int)$m['fremd_id'];
                $hinweis = 'Zugang eingerichtet. Ab jetzt genügen Kennung und Passwort.';
            }
        }
    }

    // ---- Anmelden --------------------------------------------------------
    if ($was === 'anmelden') {
        $kennung  = kennungNormalisieren((string)($_POST['kennung'] ?? ''));
        $passwort = (string)($_POST['passwort'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM portal_mitarbeiter WHERE kennung = ? AND aktiv = 1');
        $stmt->execute([$kennung]);
        $m = $stmt->fetch();

        if ($m !== false && (string)($m['passwort_hash'] ?? '') !== ''
            && password_verify($passwort, (string)$m['passwort_hash'])) {
            $pdo->prepare('UPDATE portal_mitarbeiter SET letzte_anmeldung_am = NOW() WHERE fremd_id = ?')
                ->execute([$m['fremd_id']]);
            $_SESSION['portal_mitarbeiter'] = (int)$m['fremd_id'];
        } else {
            $fehler = 'Kennung oder Passwort stimmt nicht.';
        }
    }

    // ---- Urlaub beantragen ----------------------------------------------
    if ($was === 'urlaub' && isset($_SESSION['portal_mitarbeiter'])) {
        $von  = (string)($_POST['von'] ?? '');
        $bis  = (string)($_POST['bis'] ?? '');
        $komm = mb_substr((string)($_POST['kommentar'] ?? ''), 0, 500);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $von) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bis)) {
            $fehler = 'Bitte beide Daten angeben.';
        } elseif ($bis < $von) {
            $fehler = 'Das Ende liegt vor dem Anfang.';
        } else {
            $pdo->prepare(
                'INSERT INTO portal_auftrag (mitarbeiter, art, daten) VALUES (?, \'urlaub_antrag\', ?)'
            )->execute([
                (int)$_SESSION['portal_mitarbeiter'],
                json_encode(['von' => $von, 'bis' => $bis, 'kommentar' => $komm], JSON_UNESCAPED_UNICODE),
            ]);
            $hinweis = 'Der Antrag liegt im Briefkasten. Die Zeiterfassung holt ihn beim '
                     . 'nächsten Abgleich ab und entscheidet dort.';
        }
    }
}

// --------------------------------------------------------------------------
// Angemeldet? Dann Daten holen
// --------------------------------------------------------------------------
if (isset($_SESSION['portal_mitarbeiter'])) {
    $stmt = $pdo->prepare('SELECT * FROM portal_mitarbeiter WHERE fremd_id = ? AND aktiv = 1');
    $stmt->execute([(int)$_SESSION['portal_mitarbeiter']]);
    $ich = $stmt->fetch() ?: null;

    // Verschwunden heißt ausgesperrt: Wird die Freischaltung drüben
    // zurückgenommen, ist die Zeile beim nächsten Abgleich weg.
    if ($ich === null) {
        unset($_SESSION['portal_mitarbeiter']);
    }
}

$urlaub   = $ich ? spiegelTeil($pdo, 'urlaub',   (int)$ich['fremd_id']) : [];
$antraege = $ich ? spiegelTeil($pdo, 'antraege', (int)$ich['fremd_id']) : [];
$stunden  = $ich ? spiegelTeil($pdo, 'stunden',  (int)$ich['fremd_id']) : [];

$offene = [];
if ($ich) {
    $stmt = $pdo->prepare(
        'SELECT art, daten, status, hinweis, erstellt_am FROM portal_auftrag
          WHERE mitarbeiter = ? ORDER BY id DESC LIMIT 10'
    );
    $stmt->execute([(int)$ich['fremd_id']]);
    $offene = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Keine Seite dieses Bereichs gehört in eine Suchmaschine. -->
  <meta name="robots" content="noindex,nofollow">
  <title>Mitarbeiterbereich</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 44rem; margin: 2rem auto;
           padding: 0 1rem; line-height: 1.5; }
    label { display: block; margin-top: .6rem; font-weight: 600; }
    input, textarea { width: 100%; padding: .5rem; font-size: 1rem; box-sizing: border-box; }
    button { margin-top: 1rem; font-size: 1rem; padding: .6rem 1.2rem; cursor: pointer; }
    table { border-collapse: collapse; width: 100%; margin: .5rem 0 1.5rem; }
    th, td { border: 1px solid #ccc; padding: .4rem .6rem; text-align: left; }
    th { background: #f2f2f2; }
    .fehler  { background: #fdd; border-left: 4px solid #c33; padding: .8rem; margin: 1rem 0; }
    .hinweis { background: #dfd; border-left: 4px solid #3a3; padding: .8rem; margin: 1rem 0; }
    fieldset { border: 1px solid #ccc; margin: 1.5rem 0; padding: 1rem; }
  </style>
</head>
<body>

<?php if ($fehler !== ''): ?><p class="fehler"><?= $h($fehler) ?></p><?php endif; ?>
<?php if ($hinweis !== ''): ?><p class="hinweis"><?= $h($hinweis) ?></p><?php endif; ?>

<?php if ($ich === null): ?>

  <h1>Mitarbeiterbereich</h1>

  <fieldset>
    <legend>Anmelden</legend>
    <form method="post">
      <input type="hidden" name="was" value="anmelden">
      <label for="k1">Kennung</label>
      <input id="k1" name="kennung" autocomplete="username" required>
      <label for="p1">Passwort</label>
      <input id="p1" name="passwort" type="password" autocomplete="current-password" required>
      <button type="submit">Anmelden</button>
    </form>
  </fieldset>

  <fieldset>
    <legend>Zugang einrichten</legend>
    <p>Einmal nötig – danach genügen Kennung und Passwort. Beides steht auf dem
       Zettel, den Sie im Betrieb bekommen haben.</p>
    <form method="post">
      <input type="hidden" name="was" value="einrichten">
      <label for="k2">Kennung</label>
      <input id="k2" name="kennung" required>
      <label for="c2">Aktivierungscode</label>
      <input id="c2" name="code" placeholder="ABCD 2345 EFGH" required>
      <label for="pw1">Neues Passwort</label>
      <input id="pw1" name="passwort" type="password" autocomplete="new-password" required>
      <label for="pw2">Noch einmal</label>
      <input id="pw2" name="passwort2" type="password" autocomplete="new-password" required>
      <button type="submit">Zugang einrichten</button>
    </form>
  </fieldset>

<?php else: ?>

  <h1>Hallo <?= $h($ich['anzeigename']) ?></h1>
  <p><a href="?abmelden=1">Abmelden</a></p>

  <h2>Urlaub</h2>
  <?php if ($urlaub === []): ?>
    <p>Noch keine Zahlen da. Sie erscheinen nach dem nächsten Abgleich.</p>
  <?php else: ?>
    <table>
      <tr><th>Jahr</th><th>Übertrag</th><th>Anspruch</th><th>Verbraucht</th><th>Übrig</th></tr>
      <?php foreach ($urlaub as $u): ?>
        <tr>
          <td><?= $h($u['jahr'] ?? '') ?></td>
          <td><?= $h($u['uebertrag'] ?? '') ?></td>
          <td><?= $h($u['anspruch'] ?? '') ?></td>
          <td><?= $h($u['verbraucht'] ?? '') ?></td>
          <td><strong><?= $h($u['uebrig'] ?? '') ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if ($stunden !== []): ?>
    <h2>Stunden</h2>
    <table>
      <tr><th>Saldo</th><th>Rest-Soll im Monat</th></tr>
      <?php foreach ($stunden as $s): ?>
        <tr><td><?= $h($s['saldo'] ?? '') ?></td><td><?= $h($s['rest_soll_monat'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>Meine Anträge</h2>
  <?php if ($antraege === []): ?>
    <p>Noch keine.</p>
  <?php else: ?>
    <table>
      <tr><th>Von</th><th>Bis</th><th>Tage</th><th>Status</th><th>Kommentar</th></tr>
      <?php foreach ($antraege as $a): ?>
        <tr>
          <td><?= $h($a['von'] ?? '') ?></td>
          <td><?= $h($a['bis'] ?? '') ?></td>
          <td><?= $h($a['tage'] ?? '') ?></td>
          <td><?= $h($a['status'] ?? '') ?></td>
          <td><?= $h($a['kommentar_genehmiger'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if ($offene !== []): ?>
    <h2>Zuletzt eingereicht</h2>
    <table>
      <tr><th>Art</th><th>Eingereicht</th><th>Stand</th><th>Anmerkung</th></tr>
      <?php foreach ($offene as $o): ?>
        <tr>
          <td><?= $h($o['art']) ?></td>
          <td><?= $h($o['erstellt_am']) ?></td>
          <td><?= $h($o['status'] === 'offen' ? 'wartet auf den Abgleich' : $o['status']) ?></td>
          <td><?= $h($o['hinweis']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <fieldset>
    <legend>Urlaub beantragen</legend>
    <form method="post">
      <input type="hidden" name="was" value="urlaub">
      <label for="von">Von</label>
      <input id="von" name="von" type="date" required>
      <label for="bis">Bis</label>
      <input id="bis" name="bis" type="date" required>
      <label for="kom">Anmerkung (freiwillig)</label>
      <textarea id="kom" name="kommentar" rows="2"></textarea>
      <button type="submit">Antrag abschicken</button>
    </form>
    <p><small>Der Antrag wird hier nur eingesammelt. Geprüft und entschieden
       wird er in der Zeiterfassung im Betrieb.</small></p>
  </fieldset>

<?php endif; ?>

</body>
</html>
