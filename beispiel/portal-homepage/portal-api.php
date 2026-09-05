<?php
declare(strict_types=1);

/**
 * Mitarbeiterportal – der Endpunkt auf der Website.
 *
 * Das ist die **ganze** Gegenseite. Eine Datei, kein Rahmenwerk, nur PDO.
 * Sie muss unter `/portal-api` erreichbar sein (siehe README, Schritt 3).
 *
 * Vier Aktionen:
 *   koppeln  – einmalig, ohne Unterschrift. Der Kopplungscode ist der Nachweis.
 *   hallo    – Lebenszeichen, prüft die Unterschrift.
 *   abholen  – gibt offene Aufträge heraus.
 *   melden   – nimmt Ergebnisse und den neuen Stand entgegen.
 *
 * DIE RICHTUNG IST EINSEITIG: Diese Datei antwortet nur. Sie ruft die
 * Zeiterfassung nie an und kennt ihre Adresse nicht einmal. Genau darum
 * braucht der Firmenserver keine feste Adresse und keinen offenen Port.
 */

$pdo = require __DIR__ . '/portal-konfig.php';

// --------------------------------------------------------------------------
// Antworten
// --------------------------------------------------------------------------

function antwort(int $code, array $inhalt): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($inhalt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abweisen(int $code, string $text): never
{
    antwort($code, ['ok' => false, 'fehler' => $text]);
}

// --------------------------------------------------------------------------
// Anfrage einlesen
// --------------------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    abweisen(405, 'Nur POST.');
}

$koerper = file_get_contents('php://input');
if ($koerper === false || strlen($koerper) > 8 * 1024 * 1024) {
    abweisen(413, 'Die Anfrage ist zu groß.');
}

$daten = json_decode($koerper, true);
if (!is_array($daten)) {
    abweisen(400, 'Der Anfragekörper ist kein gültiges JSON.');
}

$aktion = (string)($daten['aktion'] ?? '');

// --------------------------------------------------------------------------
// koppeln – die einzige Aktion ohne Unterschrift
// --------------------------------------------------------------------------
// Ein noch nicht gekoppeltes System hat keinen Schlüssel; das ist ja der Zweck
// des Handschlags. Der Kopplungscode tritt an die Stelle der Unterschrift.

if ($aktion === 'koppeln') {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($daten['code'] ?? '')) ?? '');
    if ($code === '') {
        abweisen(401, 'Kein Kopplungscode angegeben.');
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM portal_kopplungscode
          WHERE code_hash = ? AND verbraucht_am IS NULL AND gueltig_bis > NOW()'
    );
    $stmt->execute([hash('sha256', $code)]);
    $zeile = $stmt->fetch();

    if ($zeile === false) {
        abweisen(401, 'Der Kopplungscode ist unbekannt, abgelaufen oder schon verbraucht.');
    }

    // Verbraucht ist er ab hier in jedem Fall – auch wenn danach etwas
    // schiefgeht. Ein Code, der nach einem Fehlversuch noch gilt, ist ein
    // Code, den man erraten kann.
    $pdo->prepare('UPDATE portal_kopplungscode SET verbraucht_am = NOW() WHERE id = ?')
        ->execute([$zeile['id']]);

    $portalId   = 'pt-' . bin2hex(random_bytes(8));
    $schluessel = bin2hex(random_bytes(32));   // 64 Zeichen

    // Eine neue Kopplung löst die alte ab.
    $pdo->exec('UPDATE portal_kopplung SET aktiv = 0 WHERE aktiv = 1');
    $pdo->prepare(
        'INSERT INTO portal_kopplung (portal_id, schluessel_hash, aktiv, letzte_ip)
         VALUES (?, ?, 1, ?)'
    )->execute([$portalId, hash('sha256', $schluessel), $_SERVER['REMOTE_ADDR'] ?? null]);

    // Der Schlüssel geht **einmal** hinaus und wird hier nie gespeichert.
    antwort(200, ['ok' => true, 'portal_id' => $portalId, 'schluessel' => $schluessel]);
}

// --------------------------------------------------------------------------
// Alles Weitere ist unterschrieben
// --------------------------------------------------------------------------

$portalId = (string)($_SERVER['HTTP_X_PORTAL_ID']       ?? '');
$zeit     = (string)($_SERVER['HTTP_X_PORTAL_ZEIT']     ?? '');
$nonce    = (string)($_SERVER['HTTP_X_PORTAL_NONCE']    ?? '');
$signatur = (string)($_SERVER['HTTP_X_PORTAL_SIGNATUR'] ?? '');

if ($portalId === '' || $zeit === '' || $nonce === '' || $signatur === '') {
    abweisen(401, 'Unvollständig unterschriebene Anfrage.');
}
if (!preg_match('/^[0-9a-f]{32}$/', $nonce) || !preg_match('/^[0-9a-f]{64}$/', $signatur)) {
    abweisen(401, 'Nonce oder Signatur haben nicht die erwartete Form.');
}

// Zeitfenster: 300 Sekunden in beide Richtungen. Läuft eine der beiden Uhren
// falsch, ist das der Fehler, den man sucht – deshalb steht er im Klartext.
if (abs(time() - (int)$zeit) > 300) {
    abweisen(401, 'Die Uhren laufen mehr als 300 Sekunden auseinander.');
}

$stmt = $pdo->prepare('SELECT * FROM portal_kopplung WHERE portal_id = ?');
$stmt->execute([$portalId]);
$verbindung = $stmt->fetch();

if ($verbindung === false) {
    abweisen(401, 'Unbekannte Verbindung.');
}
if ((int)$verbindung['aktiv'] !== 1) {
    abweisen(403, 'Diese Verbindung wurde getrennt. Bitte neu koppeln.');
}

// ACHTUNG, hier stolpert jede Nachbildung: Unterschrieben wird mit dem
// **Hash** des Schlüssels, nicht mit dem Schlüssel. Beide Seiten rechnen mit
// sha256(schluessel); im Klartext existiert er nur in der Zeiterfassung.
$erwartet = hash_hmac(
    'sha256',
    $portalId . "\n" . $zeit . "\n" . $nonce . "\n" . $koerper,
    (string)$verbindung['schluessel_hash']
);

if (!hash_equals($erwartet, $signatur)) {
    abweisen(401, 'Die Signatur stimmt nicht.');
}

// Wiederholungsschutz. Der eindeutige Index entscheidet, nicht ein SELECT
// davor – zwei gleichzeitige Anfragen kämen sonst beide durch.
try {
    $pdo->prepare('INSERT INTO portal_nonce (nonce) VALUES (?)')->execute([$nonce]);
} catch (PDOException $e) {
    abweisen(401, 'Diese Anfrage war schon einmal da.');
}
$pdo->exec('DELETE FROM portal_nonce WHERE zeitpunkt < DATE_SUB(NOW(), INTERVAL 1 DAY)');
$pdo->prepare('UPDATE portal_kopplung SET letzte_ip = ? WHERE id = ?')
    ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $verbindung['id']]);

// --------------------------------------------------------------------------
// hallo
// --------------------------------------------------------------------------

if ($aktion === 'hallo') {
    antwort(200, ['ok' => true, 'stand' => date('Y-m-d H:i:s')]);
}

// --------------------------------------------------------------------------
// abholen
// --------------------------------------------------------------------------

if ($aktion === 'abholen') {
    $grenze = (int)($daten['grenze'] ?? 100);
    $grenze = max(1, min(500, $grenze));

    $stmt = $pdo->prepare(
        'SELECT id, mitarbeiter, art, daten FROM portal_auftrag
          WHERE status = \'offen\' ORDER BY id ASC LIMIT ' . $grenze
    );
    $stmt->execute();

    $auftraege = [];
    foreach ($stmt->fetchAll() as $a) {
        $auftraege[] = [
            'id'          => (int)$a['id'],
            'mitarbeiter' => (int)$a['mitarbeiter'],
            'art'         => (string)$a['art'],
            'daten'       => json_decode((string)$a['daten'], true) ?: [],
        ];
    }

    antwort(200, ['ok' => true, 'stand' => date('Y-m-d H:i:s'), 'auftraege' => $auftraege]);
}

// --------------------------------------------------------------------------
// melden
// --------------------------------------------------------------------------

if ($aktion === 'melden') {
    $pdo->beginTransaction();
    try {
        // 1) Ergebnisse zu den Aufträgen
        $abgeschlossen = 0;
        foreach ((array)($daten['ergebnisse'] ?? []) as $e) {
            $id = (int)($e['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $hinweis = mb_substr((string)($e['hinweis'] ?? ''), 0, 255);
            $status  = (string)($e['status'] ?? '') === 'angenommen' ? 'angenommen' : 'abgelehnt';

            // Eine angeforderte Datei kommt als base64 mit. Sie landet neben
            // diesem Skript in `portal-dateien/`; das Verzeichnis muss
            // beschreibbar sein und gehört **nicht** ins Netz.
            if (($e['datei'] ?? '') !== '' && ($e['dateiname'] ?? '') !== '') {
                $ordner = __DIR__ . '/portal-dateien';
                if (!is_dir($ordner)) {
                    @mkdir($ordner, 0750, true);
                }
                $sauber = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$e['dateiname']) ?? 'datei.pdf';
                $roh    = base64_decode((string)$e['datei'], true);
                if ($roh !== false && is_dir($ordner)) {
                    file_put_contents($ordner . '/' . $id . '-' . $sauber, $roh);
                    $hinweis = $hinweis !== '' ? $hinweis : $sauber;
                }
            }

            $pdo->prepare(
                'UPDATE portal_auftrag SET status = ?, hinweis = ?, erledigt_am = NOW()
                  WHERE id = ? AND status = \'offen\''
            )->execute([$status, $hinweis, $id]);
            $abgeschlossen++;
        }

        // 2) Der neue Stand des Schaufensters
        $spiegel = (array)($daten['spiegel'] ?? []);

        // Die Mitarbeiterliste ist immer vollständig, wenn sie kommt. Wer
        // nicht mehr darin steht, ist drüben nicht mehr freigeschaltet und
        // verschwindet hier vollständig – Konto, Zahlen, Anträge.
        if (isset($spiegel['mitarbeiter']) && is_array($spiegel['mitarbeiter'])) {
            $bleiben = [];
            foreach ($spiegel['mitarbeiter'] as $m) {
                $id = (int)($m['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $bleiben[] = $id;

                // Einen bereits eingelösten Code nicht wiederbeleben: Die
                // Zeiterfassung schickt ihn weiter mit, sie weiß nichts von
                // der Anmeldung.
                // VALUES(spalte) im UPDATE-Teil, nicht noch einmal :k, :n, :h.
                // Ein benannter Platzhalter darf bei echten Prepared Statements
                // (EMULATE_PREPARES = false) nur **einmal** vorkommen - sonst
                // gibt es SQLSTATE[HY093] »Invalid parameter number«.
                $pdo->prepare(
                    'INSERT INTO portal_mitarbeiter
                        (fremd_id, kennung, anzeigename, aktiv, aktivierung_hash, aktivierung_bis)
                     VALUES (:f, :k, :n, :a, :h, :b)
                     ON DUPLICATE KEY UPDATE
                        kennung          = VALUES(kennung),
                        anzeigename      = VALUES(anzeigename),
                        aktiv            = VALUES(aktiv),
                        aktivierung_hash = IF(aktivierung_hash_verbraucht IS NOT NULL
                                              AND aktivierung_hash_verbraucht = VALUES(aktivierung_hash),
                                              NULL, VALUES(aktivierung_hash)),
                        aktivierung_bis  = VALUES(aktivierung_bis)'
                )->execute([
                    ':f' => $id,
                    ':k' => (string)($m['kennung'] ?? ''),
                    ':n' => (string)($m['anzeigename'] ?? ''),
                    ':a' => (int)(bool)($m['aktiv'] ?? true),
                    ':h' => ((string)($m['aktivierung_hash'] ?? '')) !== ''
                            ? (string)$m['aktivierung_hash'] : null,
                    ':b' => $m['aktivierung_bis'] ?? null,
                ]);
            }

            if ($bleiben !== []) {
                $platzhalter = implode(',', array_fill(0, count($bleiben), '?'));
                $pdo->prepare("DELETE FROM portal_mitarbeiter WHERE fremd_id NOT IN ($platzhalter)")
                    ->execute($bleiben);
                $pdo->prepare("DELETE FROM portal_spiegel WHERE mitarbeiter IS NOT NULL AND mitarbeiter NOT IN ($platzhalter)")
                    ->execute($bleiben);
                $pdo->prepare("DELETE FROM portal_auftrag WHERE mitarbeiter NOT IN ($platzhalter)")
                    ->execute($bleiben);
            } else {
                $pdo->exec('DELETE FROM portal_mitarbeiter');
                $pdo->exec('DELETE FROM portal_spiegel WHERE mitarbeiter IS NOT NULL');
                $pdo->exec('DELETE FROM portal_auftrag');
            }
        }

        // Alle übrigen Teile landen als JSON, gruppiert nach Mitarbeiter.
        // Was nicht mitkommt, bleibt unverändert stehen – der Spiegel darf
        // teilweise geschickt werden.
        foreach ($spiegel as $teil => $eintraege) {
            if ($teil === 'mitarbeiter' || !is_array($eintraege)) {
                continue;
            }
            $gruppen = [];
            foreach ($eintraege as $eintrag) {
                $wem = isset($eintrag['mitarbeiter']) ? (int)$eintrag['mitarbeiter'] : 0;
                $gruppen[$wem][] = $eintrag;
            }
            // Erst weg, was zu diesem Teil gehört, dann neu – sonst blieben
            // gelöschte Anträge ewig stehen.
            $pdo->prepare('DELETE FROM portal_spiegel WHERE teil = ?')->execute([$teil]);
            foreach ($gruppen as $wem => $liste) {
                $pdo->prepare(
                    'INSERT INTO portal_spiegel (teil, mitarbeiter, schluessel, inhalt, stand)
                     VALUES (?, ?, \'\', ?, NOW())'
                )->execute([
                    $teil,
                    $wem > 0 ? $wem : null,
                    json_encode($liste, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        abweisen(500, 'Der Stand ließ sich nicht ablegen.');
    }

    // Zurück fließt nur, was ausschließlich hier entsteht: ob ein
    // Aktivierungscode je eingelöst wurde und wann jemand zuletzt da war.
    $zustand = [];
    foreach ($pdo->query(
        'SELECT fremd_id, aktiviert_am, letzte_anmeldung_am FROM portal_mitarbeiter'
    ) as $m) {
        $zustand[] = [
            'mitarbeiter'         => (int)$m['fremd_id'],
            'aktiviert_am'        => $m['aktiviert_am'],
            'letzte_anmeldung_am' => $m['letzte_anmeldung_am'],
        ];
    }

    antwort(200, [
        'ok'            => true,
        'stand'         => date('Y-m-d H:i:s'),
        'abgeschlossen' => $abgeschlossen,
        'zustand'       => $zustand,
    ]);
}

abweisen(400, 'Unbekannte Aktion: ' . mb_substr($aktion, 0, 40));
