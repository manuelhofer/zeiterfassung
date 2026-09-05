<?php
declare(strict_types=1);

/**
 * Kopplungscode erzeugen – die eine Hälfte des Handschlags, die auf der
 * Website passiert.
 *
 * Ohne diese Seite gibt es nichts einzutragen: Die Zeiterfassung fragt nach
 * einem Code, und den vergibt die Website.
 *
 * ACHTUNG – DIESE SEITE GEHÖRT HINTER IHRE ANMELDUNG. Wer sie aufrufen kann,
 * kann eine fremde Zeiterfassung an Ihre Website koppeln. Bauen Sie die
 * Prüfung ein, die Ihre Website ohnehin hat, zum Beispiel:
 *
 *     session_start();
 *     if (empty($_SESSION['admin'])) { http_response_code(403); exit('Kein Zugriff.'); }
 *
 * Solange die Prüfung fehlt, steht unten eine Sperre, die Sie bewusst
 * entfernen müssen.
 */

// ---- Sperre. Entfernen Sie diese Zeile erst, wenn oben eine Anmeldung steht.
http_response_code(403);
exit("Diese Seite ist noch ungeschuetzt. Bitte erst eine Anmeldepruefung einbauen\n"
   . "und dann die Sperre in kopplungscode.php entfernen.\n");
// ---------------------------------------------------------------------------

$pdo = require __DIR__ . '/portal-konfig.php';

$code = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Ein Alphabet ohne verwechselbare Zeichen: kein O/0, kein I/1/L.
    $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    $pdo->prepare(
        'INSERT INTO portal_kopplungscode (code_hash, gueltig_bis)
         VALUES (?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    )->execute([hash('sha256', $code)]);

    // Alte, längst abgelaufene Codes wegräumen.
    $pdo->exec('DELETE FROM portal_kopplungscode
                 WHERE gueltig_bis < DATE_SUB(NOW(), INTERVAL 7 DAY)');
}

$h = static fn (?string $t): string => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Kopplungscode</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto;
           padding: 0 1rem; line-height: 1.5; }
    code { background: #eee; padding: .1rem .3rem; border-radius: 3px; }
    .code { font-size: 2rem; letter-spacing: .2em; font-family: monospace;
            background: #eef6ee; border: 2px solid #4a4; border-radius: 6px;
            padding: 1rem; text-align: center; margin: 1rem 0; }
    button { font-size: 1rem; padding: .5rem 1rem; cursor: pointer; }
  </style>
</head>
<body>
  <h1>Kopplungscode</h1>

<?php if ($code !== null): ?>
  <p><strong>Dieser Code steht hier nur dieses eine Mal.</strong> Er ist
     nirgends gespeichert, wo man ihn nachlesen könnte – gespeichert ist nur
     sein Hash. Wer ihn verlegt, erzeugt einen neuen.</p>

  <div class="code"><?= $h($code) ?></div>

  <p>Gültig <strong>30 Minuten</strong>, einmal einlösbar. In der Zeiterfassung
     eintragen unter <em>Verwaltung → Mitarbeiterportal</em>, zusammen mit der
     Adresse dieser Website:</p>

  <p><code><?= $h((isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
        . ($_SERVER['HTTP_HOST'] ?? 'ihre-website.de')
        . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/')) ?></code></p>

  <p>Groß- und Kleinschreibung spielt keine Rolle, Bindestriche und Leerzeichen
     ebenso wenig.</p>
<?php else: ?>
  <p>Erzeugt einen Code, mit dem sich eine Zeiterfassung <strong>einmalig</strong>
     an diese Website koppelt. Danach unterschreibt sie jede Anfrage mit einem
     Schlüssel, den nur sie kennt.</p>
<?php endif; ?>

  <form method="post">
    <button type="submit">Kopplungscode erzeugen</button>
  </form>
</body>
</html>
