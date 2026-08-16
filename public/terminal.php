<?php
declare(strict_types=1);

// Einstiegspunkt für das Terminal-Frontend (RFID-/Barcode-Station).
//
// WICHTIG (Offline-Queue, siehe `docs/fachregeln/terminal_und_offline.md`):
// - Bei jedem Request versuchen wir, offene Queue-Einträge abzuarbeiten.
// - Der Status wird in der Session gespeichert, damit die Terminal-Views ihn anzeigen können.

require __DIR__ . '/../core/Autoloader.php';

$konfig = Start::los();

$aktion = isset($_GET['aktion']) ? (string)$_GET['aktion'] : 'start';

// ------------------------------------------------------------
// Stufe 2 der Terminal-Installation: Einrichtung am Gerät
// ------------------------------------------------------------
// Das Installationsskript bringt ein Terminal bis hierher, kennt aber bewusst
// keine Zugangsdaten. Fehlt deshalb `config/config.local.php`, gibt es nichts
// zu bedienen – dann erscheint statt der Oberfläche die Einrichtungsseite
// (Server-Adresse + Kopplungscode). Dieselbe Mechanik wie die Erstinstallation
// im Backend, siehe `docs/spezifikation_terminal_installation.md`, Abschnitt 2.
//
// Bewusst **vor** allem Datenbank-Kram: Ohne Konfiguration gibt es keine
// sinnvolle Verbindung, und der Healthcheck bleibt trotzdem erreichbar, damit
// eine Überwachung auch ein frisches Gerät abfragen kann.
if ($aktion !== 'health' && !TerminalEinrichtungController::istEingerichtet()) {
    (new TerminalEinrichtungController())->bearbeiten();
    exit;
}

// Defaults/Seeds (idempotent, defensive)
try {
    DefaultsSeeder::ensureDefaults();
} catch (Throwable $e) {
    /* niemals hard-crashen lassen */
}

// ------------------------------------------------------------
// T-050 (optional): Health/Ping Endpoint (JSON)
// ------------------------------------------------------------
// Zweck:
// - Sehr einfacher Healthcheck für Monitoring/Debug (ohne Login).
// - Liefert keinen HTML-Screen, sondern JSON.
// - Mutiert keine Fachdaten und verarbeitet **keine** Queue-Einträge.
//
// Aufruf: terminal.php?aktion=health
if ($aktion === 'health') {
    $health = [
        'zeit' => date('c'),
        'terminal_angemeldet' => false,
        'terminal_mitarbeiter_id' => null,
        'hauptdb_verfuegbar' => null,
        'queue_verfuegbar' => null,
        'queue_speicherort' => null,
        'queue_offen' => null,
        'queue_fehler' => null,
        'queue_letzter_fehler_id' => null,
        'queue_letzter_fehler_zeit' => null,
    ];

    // Terminal-Session Status
    if (isset($_SESSION['terminal_mitarbeiter_id'])) {
        $mid = $_SESSION['terminal_mitarbeiter_id'];
        if ((is_int($mid) && $mid > 0) || (is_string($mid) && ctype_digit($mid) && (int)$mid > 0)) {
            $health['terminal_angemeldet'] = true;
            $health['terminal_mitarbeiter_id'] = (int)$mid;
        }
    }

    // Zustand der Queue kommt aus einer Hand – dieselbe Quelle, aus der auch
    // der Bildschirm gespeist wird (siehe unten). Zwei Fassungen driften.
    $zustand = QueueService::getInstanz()->holeZustand();

    $health['hauptdb_verfuegbar'] = $zustand['hauptdb_verfuegbar'];
    $health['queue_verfuegbar']   = $zustand['queue_verfuegbar'];
    $health['queue_speicherort']  = $zustand['queue_speicherort'];
    $health['queue_offen']        = $zustand['offen'];
    $health['queue_fehler']       = $zustand['fehler'];

    // Nur Metadaten des letzten Fehlers – kein SQL-Text im Health-Endpunkt.
    $letzterFehler = $zustand['letzter_fehler'];
    if (is_array($letzterFehler) && isset($letzterFehler['id'])) {
        $health['queue_letzter_fehler_id'] = (int)$letzterFehler['id'];
        if (isset($letzterFehler['letzte_ausfuehrung']) && $letzterFehler['letzte_ausfuehrung'] !== null) {
            $health['queue_letzter_fehler_zeit'] = (string)$letzterFehler['letzte_ausfuehrung'];
        }
    }

    // Wenn weder Haupt-DB noch Offline-Queue verfügbar ist, ist das Terminal faktisch blockiert.
    if ($health['hauptdb_verfuegbar'] === false && $health['queue_verfuegbar'] === false) {
        http_response_code(503);
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode($health, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ------------------------------------------------------------
// T-040: Serverseitiges Inaktivitäts-Timeout (Session-Idle)
// ------------------------------------------------------------
// Hintergrund:
// - Das Terminal hat bereits ein JS-basiertes Auto-Logout (Countdown).
// - Wenn der Browser/JS hängt oder das Terminal per "Zurück/Vor" o.ä. in einen
//   komischen Zustand kommt, braucht es einen serverseitigen Fallback.
// - Wir mutieren dabei **keine** Fachdaten, sondern leiten lediglich auf einen
//   sauberen Logout-Flow um (POST+CSRF wird weiterhin im Controller erzwungen).

// Default: bewusst länger als das JS-Auto-Logout (Fallback, nicht Primär-Logout)
$terminalIdleTimeoutSekunden = 300;

// Wenn möglich: Wert aus der DB-Config laden (idempotent via DefaultsSeeder).
// In Offline-Szenarien darf diese Abfrage niemals das Terminal blockieren.
try {
    /** @var Database $dbTmp */
    $dbTmp = Database::getInstanz();

    $hauptdbOk = null;
    try {
        $hauptdbOk = $dbTmp->istHauptdatenbankVerfuegbar();
    } catch (Throwable $e) {
        $hauptdbOk = null;
    }

    if ($hauptdbOk === true) {
        $terminalIdleTimeoutSekunden = KonfigurationService::getInstanz()
            ->getInt('terminal_session_idle_timeout', $terminalIdleTimeoutSekunden);
    }
} catch (Throwable $e) {
    // Ignorieren – wir bleiben beim Default.
}

// Defensive Grenzen, damit ein falscher Config-Wert nicht das Terminal lahmlegt.
if ($terminalIdleTimeoutSekunden < 30 || $terminalIdleTimeoutSekunden > 86400) {
    $terminalIdleTimeoutSekunden = 300;
}

// Für Offline-Phasen merken (damit wir nicht zwingend die DB brauchen, um den
// zuletzt gültigen Wert zu kennen).
$_SESSION['terminal_session_idle_timeout'] = $terminalIdleTimeoutSekunden;

// Nur wenn ein Mitarbeiter am Terminal angemeldet ist, erzwingen wir den Logout.
$terminalIstAngemeldet = false;
if (isset($_SESSION['terminal_mitarbeiter_id'])) {
    $mid = $_SESSION['terminal_mitarbeiter_id'];
    if (is_int($mid) && $mid > 0) {
        $terminalIstAngemeldet = true;
    } elseif (is_string($mid) && ctype_digit($mid) && (int)$mid > 0) {
        $terminalIstAngemeldet = true;
    }
}

$jetztTs = time();
$letzteAktivitaetTs = $_SESSION['terminal_last_activity_ts'] ?? null;

if ($terminalIstAngemeldet && $aktion !== 'logout' && $letzteAktivitaetTs !== null) {
    $last = null;
    if (is_int($letzteAktivitaetTs)) {
        $last = $letzteAktivitaetTs;
    } elseif (is_string($letzteAktivitaetTs) && ctype_digit($letzteAktivitaetTs)) {
        $last = (int)$letzteAktivitaetTs;
    }

    if ($last !== null) {
        $diff = $jetztTs - $last;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff > $terminalIdleTimeoutSekunden) {
            // Kein Session-Mutieren an Fachlogik-Stellen – nur sauberer Redirect.
            header('Location: terminal.php?aktion=logout');
            exit;
        }
    }
}

// Aktivität immer aktualisieren (auch vor Login), damit eine frische Session
// nicht direkt als "idle" gilt.
$_SESSION['terminal_last_activity_ts'] = $jetztTs;

try {
    // ------------------------------------------------------------
    // Offline-Queue: pro Request versuchen, offene Einträge zu injizieren
    // ------------------------------------------------------------

    // Offene Einträge zuerst abarbeiten, dann den Zustand ermitteln – sonst
    // zeigt der Bildschirm Zähler von vor der Verarbeitung.
    try {
        OfflineQueueManager::getInstanz()->verarbeiteOffeneEintraege();
    } catch (Throwable $e) {
        Logger::error('Terminal: Fehler beim Abarbeiten der Offline-Queue', [
            'exception' => $e->getMessage(),
        ], null, null, 'terminal_offline_queue');
    }

    $zustand = QueueService::getInstanz()->holeZustand();

    $queueStatus = [
        'zeit'               => date('Y-m-d H:i:s'),
        'hauptdb_verfuegbar' => $zustand['hauptdb_verfuegbar'],
        'offen'              => $zustand['offen'],
        'fehler'             => $zustand['fehler'],
        'letzter_fehler'     => $zustand['letzter_fehler'],
        'queue_verfuegbar'   => $zustand['queue_verfuegbar'],
        'queue_speicherort'  => $zustand['queue_speicherort'],
    ];

    $_SESSION['terminal_queue_status'] = $queueStatus;

    // ------------------------------------------------------------
    // Störungsmodus: der eine Fall, in dem nichts mehr geht
    // ------------------------------------------------------------
    // Weder Haupt-DB noch Offline-Queue erreichbar - dann ist keine Buchung
    // speicherbar, und das muss auf dem Bildschirm stehen. Dazu HTTP `503`
    // statt `200`: Vorher meldete der Sperrbildschirm einer Überwachung
    // „alles in Ordnung“, während das Gerät nichts mehr annehmen konnte.
    //
    // Ein gescheiterter Queue-Eintrag sperrt hier **nicht** mehr. Er wird beim
    // Einspielen übersprungen und im Backend gemeldet; Kommen und Gehen laufen
    // weiter (`docs/fachregeln/terminal_und_offline.md`, Abschnitt 5).
    if ($queueStatus['hauptdb_verfuegbar'] === false && $queueStatus['queue_verfuegbar'] === false) {
        http_response_code(503);
        require __DIR__ . '/../views/terminal/stoerung.php';
        exit;
    }

    // ------------------------------------------------------------
    // Terminal-Aktion ausführen
    // ------------------------------------------------------------

    $controller = new TerminalController();

    switch ($aktion) {
        case 'logout':
            // Logout ist eine mutierende Aktion: Verarbeitung erfolgt nur per POST + CSRF.
            // GET zeigt eine kleine Zwischen-Seite, die den POST sauber auslöst (Auto-Logout/Legacy-Links).
            $controller->logout();
            break;

        case 'kommen':
            $controller->kommen();
            break;

        case 'gehen':
            $controller->gehen();
            break;

        case 'auftrag_starten':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->auftragStarten();
            } else {
                $controller->auftragStartenForm(null, null);
            }
            break;

        case 'auftrag_stoppen':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->auftragStoppen();
            } else {
                $controller->auftragStoppenForm(null, null);
            }
            break;

        case 'auftrag_stoppen_quick':
            // Kiosk-Flow: "Auftrag stoppen" vom Startscreen soll ohne Zwischen-Seite funktionieren.
            // Mutierende Aktion nur per POST + CSRF.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->auftragStoppenQuick();
            } else {
                header('Location: terminal.php?aktion=start');
            }
            break;

        case 'nebenauftrag_starten':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->nebenauftragStarten();
            } else {
                $controller->nebenauftragStartenForm(null, null, null);
            }
            break;

        case 'nebenauftrag_stoppen':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->nebenauftragStoppen();
            } else {
                $controller->nebenauftragStoppenForm(null, null);
            }
            break;

        case 'urlaub_beantragen':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->urlaubBeantragen();
            } else {
                $controller->urlaubBeantragenForm(null, null);
            }
            break;

        case 'urlaub_stornieren':
            // Storno ist ein expliziter POST-Intent. GET wird nicht akzeptiert.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->urlaubStornieren();
            } else {
                header('Location: terminal.php?aktion=urlaub_beantragen');
            }
            break;

        case 'rfid_zuweisen':
            // Adminfunktion: RFID-Chip einem Mitarbeiter zuweisen (nur online).
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->rfidZuweisen();
            } else {
                $controller->rfidZuweisenForm(null, null, null);
            }
            break;

        case 'offline_info':
            $controller->offlineInfo();
            break;

        case 'start':
        default:
            $controller->start();
            break;
    }
} catch (Throwable $e) {
    Logger::error('Unbehandelter Fehler im Terminal-Frontend', [
        'aktion'    => $aktion,
        'exception' => $e->getMessage(),
    ], null, null, 'terminal_frontend');

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler – Terminal</title></head><body>';
    echo '<h1>Fehler im Terminal</h1>';
    echo '<p>Es ist ein Fehler aufgetreten. Bitte informieren Sie den Administrator.</p>';
    echo '</body></html>';
}
