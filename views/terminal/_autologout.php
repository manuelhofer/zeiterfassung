<?php
declare(strict_types=1);

/**
 * Gemeinsame Terminal-JS-Logik (Auto-Logout + Uhr + Scanner-Fokus-Helfer).
 *
 * Ziel (T-028): Duplikate in mehreren Terminal-Views reduzieren.
 *
 * Hinweise:
 * - Die eigentliche Logik liegt in `public/js/terminal-autologout.js`.
 * - Das Script wird **immer** eingebunden, damit die Uhr im Kopfbereich auch
 *   auf dem Login-Screen live läuft.
 * - Auto-Logout wird nur aktiv, wenn ein Mitarbeiter angemeldet ist.
 */

// Include-Guard (T-047): Dieses Partial darf pro Request nur einmal geladen werden.
// Hintergrund: Nach Centralisierung in `_layout_bottom.php` können Legacy-Views dieses Partial
// noch direkt inkludieren, ohne dass das Script doppelt eingebunden wird.
if (defined('ZEITERFASSUNG_TERMINAL_AUTOLOGOUT_INCLUDED')) {
    return;
}
define('ZEITERFASSUNG_TERMINAL_AUTOLOGOUT_INCLUDED', true);

$autologoutAktiv = !empty($mitarbeiter);

$timeoutSekunden = 0;
if ($autologoutAktiv) {
    $timeoutSekunden = (int)($terminalTimeoutSekunden ?? 60);
    if ($timeoutSekunden < 10) {
        $timeoutSekunden = 60;
    }
}

$scriptRelPfad = 'js/terminal-autologout.js';
$scriptAttribute = [
    'data-autologout-enabled' => $autologoutAktiv ? '1' : '0',
    'data-timeout-sekunden' => (int)$timeoutSekunden,
    'data-logout-url' => $autologoutAktiv ? 'terminal.php?aktion=logout' : '',
];
require __DIR__ . '/_script.php';

// Sidequest: RFID-Bridge per WebSocket (z. B. RC522 → lokale Bridge → Browser).
// Erstmal bewusst simpel und kiosk-tauglich:
// - Wir binden die Bridge immer am Terminal ein.
// - Default-URL ist localhost:8765.
// - Die Aktivierung und URL sind jetzt in der zentralen config/config.php konfigurierbar.
$scriptRelPfad = 'js/terminal-rfid-ws.js';

// Defensive Defaults (falls Key in einer Alt-Config fehlt)
$rfidWsEnabled = true;
$rfidWsUrl = 'ws://127.0.0.1:8765';

if (isset($konfig) && is_array($konfig)) {
    $t = $konfig['terminal'] ?? null;
    if (is_array($t)) {
        $rw = $t['rfid_ws'] ?? null;
        if (is_array($rw)) {
            if (array_key_exists('enabled', $rw)) {
                $rfidWsEnabled = (bool)$rw['enabled'];
            }
            if (!empty($rw['url']) && is_string($rw['url'])) {
                $rfidWsUrl = trim($rw['url']);
            }
        }
    }
}

if ($rfidWsEnabled) {
    $scriptAttribute = [
        'data-rfid-ws-url' => $rfidWsUrl,
    ];
    require __DIR__ . '/_script.php';
}

// ------------------------------------------------------------
// T-052: Terminal Health Polling (Topbar-Badge + Systemstatus)
// ------------------------------------------------------------
// Ziel:
// - Hauptdatenbank/Queue-Status im Kiosk nicht nur beim Seiten-Reload,
//   sondern zyklisch (alle X Sekunden) aktualisieren.
// - Intervall ist über die DB-Config steuerbar: `terminal_healthcheck_interval`.

$healthIntervalSekunden = 10;

// Offline-Fallback: zuletzt bekannter Wert aus der Session.
if (isset($_SESSION['terminal_healthcheck_interval'])) {
    $v = $_SESSION['terminal_healthcheck_interval'];
    if (is_int($v)) {
        $healthIntervalSekunden = $v;
    } elseif (is_string($v) && ctype_digit(trim($v))) {
        $healthIntervalSekunden = (int)trim($v);
    }
}

// Wenn die Haupt-DB erreichbar ist, Config aus DB laden (darf niemals hard-crashen).
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
            $val = $cfg->get('terminal_healthcheck_interval', $healthIntervalSekunden);

            if (is_int($val)) {
                $healthIntervalSekunden = $val;
            } elseif (is_string($val) && ctype_digit(trim($val))) {
                $healthIntervalSekunden = (int)trim($val);
            }

            // Für Offline-Phasen merken.
            $_SESSION['terminal_healthcheck_interval'] = $healthIntervalSekunden;
        }
    }
} catch (Throwable $e) {
    // Ignorieren – wir bleiben beim Default.
}

// 0 = Polling aus (Status bleibt statisch bis zum nächsten Seiten-Reload).
if ($healthIntervalSekunden < 0) {
    $healthIntervalSekunden = 10;
}

if ($healthIntervalSekunden !== 0) {
    // Defensive Grenzen (sonst DDOS wir uns mit Requests).
    if ($healthIntervalSekunden < 2) {
        $healthIntervalSekunden = 2;
    }
    if ($healthIntervalSekunden > 300) {
        $healthIntervalSekunden = 300;
    }

    $scriptRelPfad = 'js/terminal-health-poll.js';
    $scriptAttribute = [
        'data-health-url' => 'terminal.php?aktion=health',
        'data-interval-sekunden' => (int)$healthIntervalSekunden,
    ];
    require __DIR__ . '/_script.php';
}

