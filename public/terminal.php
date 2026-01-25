<?php
declare(strict_types=1);

// Einstiegspunkt für das Terminal-Frontend (RFID-/Barcode-Station).
//
// WICHTIG (Master-Prompt / Offline-Queue):
// - Bei jedem Request versuchen wir, offene Queue-Einträge abzuarbeiten.
// - Der Status wird in der Session gespeichert, damit die Terminal-Views ihn anzeigen können.

require __DIR__ . '/../core/Autoloader.php';

$konfig = require __DIR__ . '/../config/config.php';

// Zeitzone setzen
if (isset($konfig['timezone']) && is_string($konfig['timezone']) && $konfig['timezone'] !== '') {
    date_default_timezone_set($konfig['timezone']);
} else {
    date_default_timezone_set('Europe/Berlin');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Defaults/Seeds (idempotent, defensive)
try {
    DefaultsSeeder::ensureDefaults();
} catch (Throwable $e) {
    /* niemals hard-crashen lassen */
}

$aktion = isset($_GET['aktion']) ? (string)$_GET['aktion'] : 'start';

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

    $db = null;
    try {
        if (class_exists('Database')) {
            /** @var Database $db */
            $db = Database::getInstanz();
        }
    } catch (Throwable $e) {
        $db = null;
    }

    // Haupt-DB Status
    if ($db !== null && method_exists($db, 'istHauptdatenbankVerfuegbar')) {
        try {
            $health['hauptdb_verfuegbar'] = $db->istHauptdatenbankVerfuegbar();
        } catch (Throwable $e) {
            $health['hauptdb_verfuegbar'] = null;
        }
    }

    // Queue-Verfügbarkeit bestimmen
    $queueOfflinePdo = null;
    if ($db !== null && method_exists($db, 'getOfflineVerbindung')) {
        try {
            $queueOfflinePdo = $db->getOfflineVerbindung();
        } catch (Throwable $e) {
            $queueOfflinePdo = null;
        }
    }

    // Konsistente Logik mit OfflineQueueManager:
    // Wenn eine Offline-DB verfügbar ist, ist sie der primäre Queue-Speicherort.
    if ($queueOfflinePdo instanceof PDO) {
        $health['queue_verfuegbar'] = true;
        $health['queue_speicherort'] = 'offline';
    } elseif ($health['hauptdb_verfuegbar'] === true) {
        $health['queue_verfuegbar'] = true;
        $health['queue_speicherort'] = 'haupt';
    } elseif ($health['hauptdb_verfuegbar'] === false) {
        $health['queue_verfuegbar'] = false;
        $health['queue_speicherort'] = null;
    } else {
        $health['queue_verfuegbar'] = null;
        $health['queue_speicherort'] = null;
    }

    // Queue-Zähler (best effort)
    $queuePdo = null;
    if ($queueOfflinePdo instanceof PDO) {
        $queuePdo = $queueOfflinePdo;
    } elseif ($db !== null) {
        try {
            if (method_exists($db, 'getVerbindung')) {
                $queuePdo = $db->getVerbindung();
            } elseif (method_exists($db, 'getPdo')) {
                $queuePdo = $db->getPdo();
            }
        } catch (Throwable $e) {
            $queuePdo = null;
        }
    }

    if ($queuePdo instanceof PDO) {
        try {
            $health['queue_offen'] = (int)$queuePdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status = 'offen'")->fetchColumn();
            $health['queue_fehler'] = (int)$queuePdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status = 'fehler'")->fetchColumn();
        } catch (Throwable $e) {
            // optional
        }
    }


    // Letzten Fehler-Eintrag (nur Metadaten; kein SQL-Text im Health-Endpoint)
    if ($queuePdo instanceof PDO) {
        try {
            $stmt = $queuePdo->query("SELECT id, letzte_ausfuehrung FROM db_injektionsqueue WHERE status = 'fehler' ORDER BY letzte_ausfuehrung DESC, id DESC LIMIT 1");
            if ($stmt !== false) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row) && isset($row['id'])) {
                    $health['queue_letzter_fehler_id'] = (int)$row['id'];
                    if (array_key_exists('letzte_ausfuehrung', $row) && $row['letzte_ausfuehrung'] !== null) {
                        $health['queue_letzter_fehler_zeit'] = (string)$row['letzte_ausfuehrung'];
                    }
                }
            }
        } catch (Throwable $e) {
            // optional
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
    if (class_exists('Database')) {
        /** @var Database $dbTmp */
        $dbTmp = Database::getInstanz();

        $hauptdbOk = null;
        if (method_exists($dbTmp, 'istHauptdatenbankVerfuegbar')) {
            try {
                $hauptdbOk = $dbTmp->istHauptdatenbankVerfuegbar();
            } catch (Throwable $e) {
                $hauptdbOk = null;
            }
        }

        if ($hauptdbOk === true && class_exists('ConfigService')) {
            /** @var ConfigService $cfg */
            $cfg = ConfigService::getInstanz();
            $val = $cfg->get('terminal_session_idle_timeout', $terminalIdleTimeoutSekunden);

            if (is_int($val)) {
                $terminalIdleTimeoutSekunden = $val;
            } elseif (is_string($val) && ctype_digit(trim($val))) {
                $terminalIdleTimeoutSekunden = (int)trim($val);
            }
        }
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

    $queueStatus = [
        'zeit'               => date('Y-m-d H:i:s'),
        'hauptdb_verfuegbar' => null,
        'offen'              => null,
        'fehler'             => null,
        'letzter_fehler'     => null,
        'queue_verfuegbar'   => null,
        // Legacy-/View-Key: in einigen Views wird explizit dieser Key erwartet.
        // Wir halten beide Keys synchron, damit das Terminal nicht „stumm“ bleibt,
        // wenn eine View noch den alten Namen verwendet.
        'offline_queue_verfuegbar' => null,
        'queue_speicherort'   => null,
    ];

    $db = null;
    if (class_exists('Database')) {
        /** @var Database $db */
        $db = Database::getInstanz();

        // Haupt-DB Healthcheck (falls verfügbar)
        if (method_exists($db, 'istHauptdatenbankVerfuegbar')) {
            try {
                $queueStatus['hauptdb_verfuegbar'] = $db->istHauptdatenbankVerfuegbar();
            } catch (Throwable $e) {
                $queueStatus['hauptdb_verfuegbar'] = null;
            }
        }
    }

    // Queue-Verfuegbarkeit bestimmen (wichtig, wenn die Haupt-DB offline ist)
    $queueOfflinePdo = null;
    if ($db !== null && method_exists($db, 'getOfflineVerbindung')) {
        try {
            $queueOfflinePdo = $db->getOfflineVerbindung();
        } catch (Throwable $e) {
            $queueOfflinePdo = null;
        }
    }

    // Konsistente Logik mit OfflineQueueManager:
    // Wenn eine Offline-DB verfügbar ist, ist sie der primäre Queue-Speicherort.
    if ($queueOfflinePdo instanceof PDO) {
        $queueStatus['queue_verfuegbar'] = true;
        $queueStatus['queue_speicherort'] = 'offline';
    } elseif ($queueStatus['hauptdb_verfuegbar'] === true) {
        $queueStatus['queue_verfuegbar'] = true;
        $queueStatus['queue_speicherort'] = 'haupt';
    } elseif ($queueStatus['hauptdb_verfuegbar'] === false) {
        $queueStatus['queue_verfuegbar'] = false;
        $queueStatus['queue_speicherort'] = null;
    } else {
        // unbekannter Status (weder Haupt-DB noch Offline-DB sicher ermittelbar)
        $queueStatus['queue_verfuegbar'] = null;
        $queueStatus['queue_speicherort'] = null;
    }

    // Legacy-/View-Key immer spiegeln.
    $queueStatus['offline_queue_verfuegbar'] = $queueStatus['queue_verfuegbar'];

    $queueManager = null;
    if (class_exists('OfflineQueueManager')) {
        /** @var OfflineQueueManager $queueManager */
        $queueManager = OfflineQueueManager::getInstanz();

        // Abarbeitung nur versuchen – Fehler dürfen das Terminal nicht hard-crashen.
        try {
            $queueManager->verarbeiteOffeneEintraege();
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Terminal: Fehler beim Abarbeiten der Offline-Queue', [
                    'exception' => $e->getMessage(),
                ], null, null, 'terminal_offline_queue');
            }
        }

        // Letzten Fehler-Eintrag laden (für Statusanzeige)
        try {
            $queueStatus['letzter_fehler'] = $queueManager->holeLetztenFehlerEintrag();
        } catch (Throwable $e) {
            $queueStatus['letzter_fehler'] = null;
        }
    }

    // Queue-Zähler bestimmen (auf Queue-DB bzw. Fallback Haupt-DB)
    $queuePdo = null;
    if ($db !== null) {
        try {
            if (method_exists($db, 'getOfflineVerbindung')) {
                $queuePdo = $db->getOfflineVerbindung();
            }
        } catch (Throwable $e) {
            $queuePdo = null;
        }

        if (!($queuePdo instanceof PDO)) {
            try {
                if (method_exists($db, 'getVerbindung')) {
                    $queuePdo = $db->getVerbindung();
                } elseif (method_exists($db, 'getPdo')) {
                    $queuePdo = $db->getPdo();
                }
            } catch (Throwable $e) {
                $queuePdo = null;
            }
        }
    }

    if ($queuePdo instanceof PDO) {
        try {
            $queueStatus['offen'] = (int)$queuePdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status = 'offen'")->fetchColumn();
            $queueStatus['fehler'] = (int)$queuePdo->query("SELECT COUNT(*) FROM db_injektionsqueue WHERE status = 'fehler'")->fetchColumn();
        } catch (Throwable $e) {
            // Statusanzeige ist optional, Terminal darf trotzdem laufen.
        }
    }

    $_SESSION['terminal_queue_status'] = $queueStatus;

    // ------------------------------------------------------------
    // Fatal: Haupt-DB down UND keine Offline-Queue verfügbar
    // ------------------------------------------------------------
    // In diesem Zustand kann das Terminal keine Buchungen speichern.
    // Wir wechseln in einen blockierenden Screen (Master-Prompt: „Admin anfordern“).
    if ($queueStatus['hauptdb_verfuegbar'] === false && $queueStatus['queue_verfuegbar'] === false) {
        $stoerungEintrag = null;
        require __DIR__ . '/../views/terminal/stoerung.php';
        exit;
    }

    // ------------------------------------------------------------
    // Störungsmodus (Master-Prompt):
    // Wenn ein Fehler-Eintrag in der Queue existiert, muss das Terminal
    // in einen blockierenden Störungsmodus wechseln, bis ein Admin den
    // problematischen Queue-Eintrag löscht/ignoriert.
    // ------------------------------------------------------------

    $stoerungAktiv = false;
    $stoerungEintrag = null;

    if (array_key_exists('fehler', $queueStatus) && $queueStatus['fehler'] !== null) {
        $stoerungAktiv = ((int)$queueStatus['fehler'] > 0);
    }

    // Fallback: wenn der Zähler nicht ermittelt werden konnte, aber ein letzter Fehler-Eintrag existiert.
    if (!$stoerungAktiv && isset($queueStatus['letzter_fehler']) && is_array($queueStatus['letzter_fehler']) && !empty($queueStatus['letzter_fehler']['id'])) {
        $stoerungAktiv = true;
    }

    if ($stoerungAktiv) {
        if (isset($queueStatus['letzter_fehler']) && is_array($queueStatus['letzter_fehler'])) {
            $stoerungEintrag = $queueStatus['letzter_fehler'];
        }

        // In diesem Modus werden alle normalen Aktionen blockiert.
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

        case 'start':
        default:
            $controller->start();
            break;
    }
} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::error('Unbehandelter Fehler im Terminal-Frontend', [
            'aktion'    => $aktion,
            'exception' => $e->getMessage(),
        ], null, null, 'terminal_frontend');
    }

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler – Terminal</title></head><body>';
    echo '<h1>Fehler im Terminal</h1>';
    echo '<p>Es ist ein Fehler aufgetreten. Bitte informieren Sie den Administrator.</p>';
    echo '</body></html>';
}
