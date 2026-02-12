<?php
declare(strict_types=1);

/** @var array|null  $mitarbeiter */
/** @var string|null $nachricht */
/** @var string|null $fehlerText */
/** @var string|null $heuteDatum */
/** @var array<int,array<string,mixed>> $heuteBuchungen */
/** @var string|null $heuteFehler */
/** @var array<int,array<string,mixed>> $laufendeAuftraege */
/** @var array<string,mixed>|null $letzterAuftrag */
/** @var int|null $terminalTimeoutSekunden */
/** @var array<int,array<string,mixed>> $urlaubsantraege */
/** @var string|null $csrfToken */
/** @var array<int,array<string,mixed>>|null $zeitWarnungen */
/** @var array<string,string|int>|null $urlaubSaldo */
/** @var string|null $urlaubSaldoFehler */
/** @var int|null $urlaubJahr */
/** @var array<string,string>|null $urlaubVorschau */
/** @var array<string,mixed>|null $stundenkontoSaldo */
/** @var string|null $stundenkontoSaldoFehler */
/** @var int|null $urlaubWizardSchritt */

$heuteDatum = $heuteDatum ?? null;
$heuteBuchungen = $heuteBuchungen ?? [];
$heuteFehler = $heuteFehler ?? null;
$laufendeAuftraege = $laufendeAuftraege ?? [];
$terminalTimeoutSekunden = $terminalTimeoutSekunden ?? null;
$zeitWarnungen = (isset($zeitWarnungen) && is_array($zeitWarnungen)) ? $zeitWarnungen : null;

$letzterAuftrag = (isset($letzterAuftrag) && is_array($letzterAuftrag)) ? $letzterAuftrag : ($_SESSION['terminal_letzter_auftrag'] ?? null);
if (!is_array($letzterAuftrag)) {
    $letzterAuftrag = null;
}


$urlaubsantraege = $urlaubsantraege ?? [];
if (!is_array($urlaubsantraege)) {
    $urlaubsantraege = [];
}

$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
// Defensive: einige Controller-Pfade rendern den Startscreen ohne explizit
// $csrfToken zu setzen. Wenn der Token bereits in der Session existiert,
// nutzen wir ihn, damit POST-Formulare weiterhin funktionieren.
if ($csrfToken === '' && isset($_SESSION['terminal_csrf_token']) && is_string($_SESSION['terminal_csrf_token'])) {
    $csrfToken = (string)$_SESSION['terminal_csrf_token'];
}

$zeigeUrlaubFormular = (bool)($zeigeUrlaubFormular ?? false);
$zeigeUrlaubUebersicht = (bool)($zeigeUrlaubUebersicht ?? false);
$zeigeArbeitszeitUebersichtSeite = (bool)($zeigeArbeitszeitUebersichtSeite ?? false);
$urlaubModus = isset($urlaubModus) ? (string)$urlaubModus : '';
$betriebsferienListe = $betriebsferienListe ?? [];
if (!is_array($betriebsferienListe)) {
    $betriebsferienListe = [];
}
$zeigeNebenauftragStartFormular = (bool)($zeigeNebenauftragStartFormular ?? false);
$zeigeNebenauftragStopFormular  = (bool)($zeigeNebenauftragStopFormular ?? false);
$nebenauftragStopOfflineModus   = (bool)($nebenauftragStopOfflineModus ?? false);

$zeigeRfidZuweisenFormular = (bool)($zeigeRfidZuweisenFormular ?? false);
$rfidZuweisenFormular = is_array($rfidZuweisenFormular ?? null) ? (array)$rfidZuweisenFormular : [];
$rfidZuweisenMitarbeiterListe = $rfidZuweisenMitarbeiterListe ?? [];
if (!is_array($rfidZuweisenMitarbeiterListe)) {
    $rfidZuweisenMitarbeiterListe = [];
}

$darfRfidZuweisen = false;
if (is_array($mitarbeiter) && array_key_exists('darf_rfid_zuweisen', $mitarbeiter)) {
    $darfRfidZuweisen = (bool)$mitarbeiter['darf_rfid_zuweisen'];
} elseif (isset($_SESSION['terminal_darf_rfid_zuweisen'])) {
    $darfRfidZuweisen = (bool)$_SESSION['terminal_darf_rfid_zuweisen'];
}

$nebenauftragFormular = is_array($nebenauftragFormular ?? null) ? (array)$nebenauftragFormular : [];
$laufendeNebenauftraege = $laufendeNebenauftraege ?? [];
if (!is_array($laufendeNebenauftraege)) {
    $laufendeNebenauftraege = [];
}

$urlaubFormular = is_array($urlaubFormular ?? null) ? (array)$urlaubFormular : [];
$urlaubWizardSchritt = isset($urlaubWizardSchritt) ? (int)$urlaubWizardSchritt : (int)($urlaubFormular['wizard_schritt'] ?? 1);
if ($urlaubWizardSchritt < 1 || $urlaubWizardSchritt > 3) {
    $urlaubWizardSchritt = 1;
}

$datumAusFormular = static function (string $datumWert): array {
    if ($datumWert !== '') {
        $datumObjekt = DateTimeImmutable::createFromFormat('Y-m-d', $datumWert);
        if ($datumObjekt instanceof DateTimeImmutable) {
            return [
                'tag'   => (int)$datumObjekt->format('d'),
                'monat' => (int)$datumObjekt->format('m'),
                'jahr'  => (int)$datumObjekt->format('Y'),
            ];
        }
    }

    $heute = new DateTimeImmutable('today');
    return [
        'tag'   => (int)$heute->format('d'),
        'monat' => (int)$heute->format('m'),
        'jahr'  => (int)$heute->format('Y'),
    ];
};

$vonDatumTeile = $datumAusFormular((string)($urlaubFormular['von_datum'] ?? ''));
$bisDatumTeile = $datumAusFormular((string)($urlaubFormular['bis_datum'] ?? ''));

$urlaubSaldo = (isset($urlaubSaldo) && is_array($urlaubSaldo)) ? $urlaubSaldo : null;
$urlaubSaldoFehler = (isset($urlaubSaldoFehler) && is_string($urlaubSaldoFehler) && $urlaubSaldoFehler !== '') ? $urlaubSaldoFehler : null;
$urlaubJahr = isset($urlaubJahr) ? (int)$urlaubJahr : (int)(new DateTimeImmutable('now'))->format('Y');
$urlaubVorschau = (isset($urlaubVorschau) && is_array($urlaubVorschau)) ? $urlaubVorschau : null;

$stundenkontoSaldo = (isset($stundenkontoSaldo) && is_array($stundenkontoSaldo)) ? $stundenkontoSaldo : null;
$stundenkontoSaldoFehler = (isset($stundenkontoSaldoFehler) && is_string($stundenkontoSaldoFehler) && $stundenkontoSaldoFehler !== "") ? (string)$stundenkontoSaldoFehler : null;


// Debug (T-069 Helper):
// Aktivieren über URL: terminal.php?aktion=start&debug=1
//
// v8.1: Debug-Status kann in der Session persistieren (Controller setzt $_SESSION['terminal_debug_aktiv']).
$debugAktiv = isset($debugAktiv) ? (bool)$debugAktiv : false;
if (!$debugAktiv && isset($_SESSION['terminal_debug_aktiv'])) {
    $debugAktiv = (bool)$_SESSION['terminal_debug_aktiv'];
}

$fmtTage = static function ($val): string {
    $f = 0.0;
    if (is_numeric($val)) {
        $f = (float)$val;
    }
    return number_format($f, 2, ',', '.');
};

$fmtDatumDE = static function (string $ymd): string {
    $ymd = trim($ymd);
    if ($ymd === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($ymd))->format('d-m-Y');
    } catch (Throwable $e) {
        return $ymd;
    }
};

$fmtDatum = static function (string $ymd): string {
    $ymd = trim($ymd);
    if ($ymd === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($ymd))->format('d-m-Y');
    } catch (Throwable $e) {
        return $ymd;
    }
};


// Haupt-DB Status (für "nur online" Bereiche)
// Queue-Details/Anzeige erfolgt über views/terminal/_statusbox.php
$queueStatus = $_SESSION['terminal_queue_status'] ?? null;
$hauptdbOk = null;
if (is_array($queueStatus) && array_key_exists('hauptdb_verfuegbar', $queueStatus)) {
    $hauptdbOk = $queueStatus['hauptdb_verfuegbar'];
}


// Anwesenheit (Kommen/Gehen-Status):
// - Online: heute mehr "kommen" als "gehen" => anwesend
// - Offline-Fallback: Session-Merker (wird in TerminalController bei Kommen/Gehen gesetzt)
//
// Zusätzlich für T-069 Debug: Wir merken uns die Kommen/Gehen-Zähler, um Anomalien leichter zu sehen.
$kommenAnzahl = null;
$gehenAnzahl  = null;

$istAnwesend = false;
if (!empty($mitarbeiter) && isset($mitarbeiter['id'])) {
    if ($hauptdbOk === true) {
        $kommen = 0;
        $gehen  = 0;

        if (isset($heuteBuchungen) && is_array($heuteBuchungen)) {
            foreach ($heuteBuchungen as $b) {
                $typ = $b['typ'] ?? null;
                if ($typ === 'kommen') {
                    $kommen++;
                } elseif ($typ === 'gehen') {
                    $gehen++;
                }
            }
        }

        $kommenAnzahl = $kommen;
        $gehenAnzahl  = $gehen;
        $istAnwesend = ($kommen > $gehen);
    } else {
        $istAnwesend = isset($_SESSION['terminal_anwesend']) ? (bool)$_SESSION['terminal_anwesend'] : false;
    }
}

// Laufende Auftraege (nur fuer Button-Logik am Startscreen):
// - Online: aus DB via $laufendeAuftraege
// - Offline: Hauptauftrag-Fallback via Session-Merker (terminal_letzter_auftrag)
$hatLaufenderHauptauftrag = false;
$hatLaufenderNebenauftrag = false;

if ($hauptdbOk === true && is_array($laufendeAuftraege) && count($laufendeAuftraege) > 0) {
    foreach ($laufendeAuftraege as $az) {
        if (!is_array($az)) {
            continue;
        }
        $typ = (string)($az['typ'] ?? '');
        if ($typ === 'haupt') {
            $hatLaufenderHauptauftrag = true;
        } elseif ($typ === 'neben') {
            $hatLaufenderNebenauftrag = true;
        }
    }
} elseif (is_array($letzterAuftrag)) {
    $typ = (string)($letzterAuftrag['typ'] ?? '');
    $status = (string)($letzterAuftrag['status'] ?? '');
    if ($typ === 'haupt' && $status === 'laufend' && !empty($letzterAuftrag['auftragscode'])) {
        $hatLaufenderHauptauftrag = true;
    }
}

// Offline-Fallback fuer Nebenauftraege: Terminal merkt lokal, ob mindestens ein Nebenauftrag gestartet wurde.
if ($hauptdbOk !== true) {
    $cnt = $_SESSION['terminal_nebenauftrag_laufend_count'] ?? 0;
    if (is_numeric($cnt) && (int)$cnt > 0) {
        $hatLaufenderNebenauftrag = true;
    }
}

	// Layout-Variante: Beim Login soll der Anmelde-Button den restlichen Platz nach unten fuellen.
	$bodyKlasse = 'terminal-wide';
	if (empty($mitarbeiter)) {
		$bodyKlasse .= ' terminal-login';
	}

if ($zeigeArbeitszeitUebersichtSeite) {
	$seitenTitel = 'Arbeitszeit-Übersicht – Terminal';
	$seitenUeberschrift = 'Arbeitszeit-Übersicht';
} else {
	$seitenTitel = 'Terminal – Zeiterfassung';
	$seitenUeberschrift = 'Terminal – Zeiterfassung';
}
require __DIR__ . '/_layout_top.php';
?>

<?php require __DIR__ . '/_statusbox.php'; ?>

<?php if ($debugAktiv): ?>
<?php
    $debugZeilen = [];
    $debugZeilen[] = 'Zeit: ' . (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $debugZeilen[] = 'Aktion: ' . (string)($_GET['aktion'] ?? 'start');

    $debugZeilen[] = 'Haupt-DB: ' . ($hauptdbOk === true ? 'OK' : ($hauptdbOk === false ? 'OFFLINE' : 'unbekannt'));

    $qs = is_array($queueStatus) ? $queueStatus : [];
    $debugZeilen[] = 'Queue-Speicherort: ' . (string)($qs['queue_speicherort'] ?? '');
    $debugZeilen[] = 'Queue offen: ' . (string)($qs['offen'] ?? '');
    $debugZeilen[] = 'Queue fehler: ' . (string)($qs['fehler'] ?? '');
    if (isset($qs['letzter_fehler']) && is_array($qs['letzter_fehler']) && !empty($qs['letzter_fehler']['id'])) {
        $debugZeilen[] = 'Letzter Queue-Fehler-ID: ' . (int)$qs['letzter_fehler']['id'];
        if (!empty($qs['letzter_fehler']['letzte_ausfuehrung'])) {
            $debugZeilen[] = 'Letzter Queue-Fehler-Zeit: ' . (string)$qs['letzter_fehler']['letzte_ausfuehrung'];
        }
    }

    $debugZeilen[] = 'Session terminal_mitarbeiter_id: ' . (string)($_SESSION['terminal_mitarbeiter_id'] ?? '');
    $debugZeilen[] = 'Session terminal_anwesend: ' . (string)($_SESSION['terminal_anwesend'] ?? '') . ' (seit: ' . (string)($_SESSION['terminal_anwesend_zeit'] ?? '') . ')';
    $debugZeilen[] = 'Berechnet anwesend: ' . ($istAnwesend ? '1' : '0');
    if ($kommenAnzahl !== null || $gehenAnzahl !== null) {
        $debugZeilen[] = 'Zähler (heute): kommen=' . (string)($kommenAnzahl ?? '') . ', gehen=' . (string)($gehenAnzahl ?? '');
    }
    $debugZeilen[] = 'Heutige Buchungen: ' . (string)(is_array($heuteBuchungen) ? count($heuteBuchungen) : 0);
    $debugZeilen[] = 'CSRF Token Länge: ' . (string)(is_string($csrfToken) ? strlen($csrfToken) : 0);
    // Serverseitiges Idle-Timeout (Fallback aus public/terminal.php)
    $idleTimeout = null;
    if (isset($_SESSION['terminal_session_idle_timeout'])) {
        $idleTimeout = (int)$_SESSION['terminal_session_idle_timeout'];
    }
    $lastActivity = $_SESSION['terminal_last_activity_ts'] ?? null;
    $lastActivityTs = null;
    if (is_int($lastActivity)) {
        $lastActivityTs = $lastActivity;
    } elseif (is_string($lastActivity) && ctype_digit($lastActivity)) {
        $lastActivityTs = (int)$lastActivity;
    }

    if ($idleTimeout !== null && $idleTimeout > 0) {
        $debugZeilen[] = 'Server Idle-Timeout: ' . (int)$idleTimeout . 's';
    }
    if ($lastActivityTs !== null) {
        $debugZeilen[] = 'Letzte Aktivität (server): ' . date('Y-m-d H:i:s', $lastActivityTs);
        if ($idleTimeout !== null && $idleTimeout > 0) {
            $seit = max(time() - $lastActivityTs, 0);
            $rest = max($idleTimeout - $seit, 0);
            $debugZeilen[] = 'Idle: seit ' . (int)$seit . 's, verbleibend ' . (int)$rest . 's';
        }
    }
?>
<details class="status-box mt-05">
    <summary class="status-title"><span>Debug (T-069)</span></summary>
    <div class="status-small">
        <pre id="terminal-debug-dump"><?php echo htmlspecialchars(implode("
", $debugZeilen), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>

        <div class="button-row mt-035">
            <button type="button" id="terminal-debug-copy" class="secondary">Debug-Info kopieren</button>
        </div>
        <div class="status-small" id="terminal-debug-copy-status" aria-live="polite"></div>

        <div class="status-details-label mt-035">RFID WebSocket</div>
        <div class="status-small" id="terminal-debug-ws-status" aria-live="polite">RFID WS: (warte auf Verbindung)</div>

        <div class="status-details-label mt-035">T-069 Mini-Checkliste</div>
        <div class="status-small">
            <ul class="status-small">
                <li>Login via RFID (nicht anwesend): nur „Kommen“ + „Urlaub Übersicht“ sichtbar.</li>
                <li>Kommen: danach „Gehen“ + Auftrag/Nebenauftrag sichtbar; Übersicht (heute) aufklappbar.</li>
                <li>Gehen: danach Logout/Startscreen; Auto-Logout/Idle prüfen.</li>
            </ul>
        </div>

        <form method="post" action="terminal.php?aktion=start&amp;debug=1" class="login-form">
            <label for="bugreport_id">Bug-ID (optional)</label>
            <input type="text" id="bugreport_id" name="bugreport_id" autocomplete="off" placeholder="B-069x">

            <label for="bugreport_text">Bug-Notiz (ins LOG)</label>
            <textarea id="bugreport_text" name="bugreport_text" rows="4" placeholder="Kurze Beschreibung + Schritte (welcher Screen/Aktion)"></textarea>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

            <div class="button-row">
                <button type="submit" name="bugreport_submit" value="1">Bug-Notiz speichern</button>
            </div>
        </form>


        <?php
            $qr = $_SESSION['terminal_debug_queue_report'] ?? null;
            if (!is_array($qr)) {
                $qr = null;
            }
        ?>
        <div class="status-details-label mt-035">Offline-Queue (manuell)</div>

        <?php
            // Kurzstatus (für Feldtest / Debug)
            $qs = $_SESSION['terminal_queue_status'] ?? null;
            $dbTxt = 'unbekannt';
            $qTxt = 'unbekannt';
            $qOffenTxt = '';
            $qFehlerTxt = '';
            $qOrtTxt = '';

            if (is_array($qs)) {
                if (array_key_exists('hauptdb_verfuegbar', $qs)) {
                    $dbTxt = ($qs['hauptdb_verfuegbar'] === true) ? 'OK' : (($qs['hauptdb_verfuegbar'] === false) ? 'offline' : 'unbekannt');
                }

                $qv = null;
                if (array_key_exists('queue_verfuegbar', $qs)) {
                    $qv = $qs['queue_verfuegbar'];
                } elseif (array_key_exists('offline_queue_verfuegbar', $qs)) {
                    $qv = $qs['offline_queue_verfuegbar'];
                }

                if ($qv === true) {
                    $qTxt = 'verfügbar';
                } elseif ($qv === false) {
                    $qTxt = 'nicht verfügbar';
                }

                if (isset($qs['queue_speicherort']) && is_string($qs['queue_speicherort']) && trim((string)$qs['queue_speicherort']) !== '') {
                    $qOrtTxt = ' (' . trim((string)$qs['queue_speicherort']) . ')';
                }

                if (array_key_exists('offen', $qs) && $qs['offen'] !== null) {
                    $qOffenTxt = ' · Offen: ' . (int)$qs['offen'];
                }
                if (array_key_exists('fehler', $qs) && $qs['fehler'] !== null) {
                    $qFehlerTxt = ' · Fehler: ' . (int)$qs['fehler'];
                }
            }

            $debugQueueStatusLine = 'Haupt-DB: ' . $dbTxt . ' · Queue: ' . $qTxt . $qOrtTxt . $qOffenTxt . $qFehlerTxt;
        ?>

        <div class="status-small"><?php echo htmlspecialchars($debugQueueStatusLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>

        <details class="status-details mt-035">
            <summary class="status-small"><strong>Feldtest-Checkliste (Offline-Queue)</strong></summary>
            <div class="status-small">
                <ol class="status-small">
                    <li>Online stempeln (Kommen/Gehen).</li>
                    <li>Haupt-DB offline machen (DB stoppen / Netzwerk blocken).</li>
                    <li>Offline mehrfach stempeln (mind. 2 RFIDs, auch kurz hintereinander).</li>
                    <li>Haupt-DB wieder online.</li>
                    <li>Hier: „Queue jetzt verarbeiten“ drücken und Report prüfen.</li>
                </ol>
                <div class="status-small">Health JSON: <code>terminal.php?aktion=health</code> · Backend: <code>index.php?seite=queue_admin</code></div>
            </div>
        </details>
        <?php if ($qr !== null): ?>
            <?php
                $zeit = isset($qr['zeit']) ? (string)$qr['zeit'] : '';
                $bo = array_key_exists('before_offen', $qr) ? $qr['before_offen'] : null;
                $bf = array_key_exists('before_fehler', $qr) ? $qr['before_fehler'] : null;
                $ao = array_key_exists('after_offen', $qr) ? $qr['after_offen'] : null;
                $af = array_key_exists('after_fehler', $qr) ? $qr['after_fehler'] : null;
                $ms = array_key_exists('dauer_ms', $qr) ? $qr['dauer_ms'] : null;
                $nf = array_key_exists('new_fehler', $qr) ? (int)$qr['new_fehler'] : 0;

                $line = 'Letzter Replay: ' . $zeit;
                if ($bo !== null && $ao !== null) {
                    $line .= ' | Offen ' . (int)$bo . ' → ' . (int)$ao;
                }
                if ($bf !== null && $af !== null) {
                    $line .= ' | Fehler ' . (int)$bf . ' → ' . (int)$af;
                }
                if ($nf > 0) {
                    $line .= ' | neue Fehler ' . (int)$nf;
                }
                if ($ms !== null) {
                    $line .= ' | ' . (int)$ms . 'ms';
                }
            ?>
            <div class="status-small"><?php echo htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <?php else: ?>
            <div class="status-small">Noch kein manueller Replay ausgefuehrt.</div>
        <?php endif; ?>

        <form method="post" action="terminal.php?aktion=start&amp;debug=1" class="login-form mt-035">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <div class="button-row">
                <button type="submit" name="debug_queue_verarbeiten" value="1" class="secondary">Queue jetzt verarbeiten</button>
            </div>
            <div class="status-small">Hinweis: Nur mit Recht <code>QUEUE_VERWALTEN</code> (sonst Fehlermeldung).</div>
        </form>
        <details class="status-details mt-035">
            <summary class="status-small"><strong>Letzte Queue-Einträge (Top 10)</strong></summary>
            <?php if (isset($debugQueueEintraege) && is_array($debugQueueEintraege) && count($debugQueueEintraege) > 0): ?>
                <?php
                    $qlines = [];
                    foreach ($debugQueueEintraege as $qe) {
                        $qlines[] = sprintf(
                            '#%s %s v=%s erstellt=%s letzte=%s aktion=%s mid=%s tid=%s fehler=%s',
                            (string)($qe['id'] ?? ''),
                            (string)($qe['status'] ?? ''),
                            (string)($qe['versuche'] ?? ''),
                            (string)($qe['erstellt_am'] ?? ''),
                            (string)($qe['letzte_ausfuehrung'] ?? ''),
                            (string)($qe['meta_aktion'] ?? ''),
                            (string)($qe['meta_mitarbeiter_id'] ?? ''),
                            (string)($qe['meta_terminal_id'] ?? ''),
                            (string)($qe['fehler_kurz'] ?? '')
                        );
                    }
                ?>
                <pre><?php echo htmlspecialchars(implode("\n", $qlines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
            <?php else: ?>
                <div class="status-small">Keine Einträge gefunden (oder DB nicht erreichbar).</div>
            <?php endif; ?>
        </details>
    </div>
</details>
<?php endif; ?>





    <?php if (!empty($nachricht)):
        // B-078: "Angemeldet als ..." soll nicht doppelt erscheinen.
        // Login-Hinweis wird über die Mitarbeiter-Box abgedeckt.
        $n = trim((string)$nachricht);
        $unterdruecken = (!empty($mitarbeiter) && stripos($n, 'Angemeldet als') === 0);
    ?>
        <?php if (!$unterdruecken): ?>
            <div class="meldung">
                <?php echo htmlspecialchars($n, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($fehlerText)): ?>
        <div class="fehler">
            <?php echo htmlspecialchars($fehlerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

	<?php
		$qsOffen  = isset($queueStatus) && is_array($queueStatus) ? (int)($queueStatus['offen'] ?? 0) : 0;
		$qsFehler = isset($queueStatus) && is_array($queueStatus) ? (int)($queueStatus['fehler'] ?? 0) : 0;
		$qsKurz   = isset($queueStatus) && is_array($queueStatus) ? (string)($queueStatus['letzter_fehler_kurz'] ?? '') : '';
	?>
	<?php if ($qsFehler > 0 || $qsOffen > 0): ?>
		<div class="status-box <?php echo ($qsFehler > 0) ? 'error' : 'warn'; ?>">
			<div class="status-title">
				<span>Offline-Queue: <?php echo ($qsFehler > 0) ? 'FEHLER' : 'Offen'; ?></span>
			</div>
			<div class="status-small">
				<?php if ($qsFehler > 0): ?>
					Es gibt <strong><?php echo (int)$qsFehler; ?></strong> fehlerhafte Queue-Eintraege. Bitte Debug oeffnen / Admin informieren.
					<?php if (trim($qsKurz) !== ''): ?>
						<div class="status-small mt">Letzter Fehler: <?php echo htmlspecialchars(trim($qsKurz), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
					<?php endif; ?>
				<?php else: ?>
					Es gibt <strong><?php echo (int)$qsOffen; ?></strong> offene Queue-Eintraege. Sobald die Haupt-DB wieder erreichbar ist, werden diese nachgezogen.
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if (!empty($mitarbeiter) && $hauptdbOk === true && is_array($zeitWarnungen) && count($zeitWarnungen) > 0): ?>
		<div class="status-box error">
			<div class="status-title"><span>Achtung: Unvollständige Zeitstempel</span></div>
			<div class="status-small">
				Es gibt offene/unklare Kommen/Gehen-Buchungen. Bitte Personalbüro/Vorgesetzten informieren.
				<div class="status-small mt">
					<?php foreach ($zeitWarnungen as $w):
						$wDatum = $fmtDatumDE((string)($w['datum'] ?? ''));
						$wK = (int)($w['anzahl_kommen'] ?? 0);
						$wG = (int)($w['anzahl_gehen'] ?? 0);
					?>
						<div><strong><?php echo htmlspecialchars($wDatum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>: kommen=<?php echo (int)$wK; ?>, gehen=<?php echo (int)$wG; ?></div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

    <?php if (empty($mitarbeiter)): ?>
        <?php if ($hauptdbOk === false): ?>
            <?php
                // Offline-Mode (Master-Prompt v9):
                // Ohne Hauptdatenbank kein Login, aber Kommen/Gehen per RFID darf weiterhin angenommen werden.
                $offlineRfid = '';
                if (isset($_SESSION['terminal_offline_rfid_code']) && is_string($_SESSION['terminal_offline_rfid_code'])) {
                    $offlineRfid = trim((string)$_SESSION['terminal_offline_rfid_code']);
                }

                // UX (Offline): Wenn vorhanden, zeigen wir einen Vorschlag an,
                // basierend auf der letzten lokalen Offline-Buchung fuer diesen RFID.
                $offlineHint = null;
                if ($offlineRfid !== '' && isset($_SESSION['terminal_offline_rfid_hint']) && is_array($_SESSION['terminal_offline_rfid_hint'])) {
                    $h = $_SESSION['terminal_offline_rfid_hint'];
                    if (isset($h['rfid_code']) && is_string($h['rfid_code']) && trim((string)$h['rfid_code']) === $offlineRfid) {
                        $offlineHint = $h;
                    }
                }
            ?>

            <div class="fehler">
                Hauptdatenbank offline – Offline-Modus aktiv.
                <div class="status-small" style="margin-top:6px;">
                    Kommen/Gehen wird lokal gespeichert und später synchronisiert.
                </div>
            </div>

            <p class="hinweis">
                Bitte RFID-Chip an das Lesegerät halten.<br>
                Danach „Kommen“ oder „Gehen“ auswählen.
            </p>

            <form method="post" action="terminal.php?aktion=start" class="login-form terminal-login-form">
                <label for="rfid_code">RFID</label>
                <input type="text" id="rfid_code" name="rfid_code" autocomplete="off" autofocus>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                <div class="button-row terminal-login-buttonrow">
                    <button type="submit">Weiter</button>
                </div>
            </form>

            <?php if ($offlineRfid !== ''): ?>
                <div class="status-box" style="margin-top:16px;">
                    <div class="status-title"><span>RFID erfasst</span></div>
                    <div class="status-small">Code: <strong><?php echo htmlspecialchars($offlineRfid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>

                    <?php
                        $offlineVorschlag = '';
                        $offlineLetzteTyp = '';
                        $offlineLetzteZeit = '';

                        if (is_array($offlineHint)) {
                            if (isset($offlineHint['vorschlag_typ']) && is_string($offlineHint['vorschlag_typ'])) {
                                $offlineVorschlag = trim((string)$offlineHint['vorschlag_typ']);
                            }
                            if (isset($offlineHint['letzte_typ']) && is_string($offlineHint['letzte_typ'])) {
                                $offlineLetzteTyp = trim((string)$offlineHint['letzte_typ']);
                            }
                            if (isset($offlineHint['letzte_zeit']) && is_string($offlineHint['letzte_zeit'])) {
                                $offlineLetzteZeit = trim((string)$offlineHint['letzte_zeit']);
                            }
                        }

                        $labelTyp = static function (string $t): string {
                            if ($t === 'kommen') return 'Kommen';
                            if ($t === 'gehen') return 'Gehen';
                            return $t;
                        };

                        $fmtZeitpunktDE = static function (string $dt): string {
                            $dt = trim($dt);
                            if ($dt === '') return '';
                            try {
                                return (new DateTimeImmutable($dt))->format('H:i:s d-m-Y');
                            } catch (Throwable $e) {
                                return $dt;
                            }
                        };

                        $offlineKommenCls = '';
                        $offlineGehenCls = '';
                        if ($offlineVorschlag === 'kommen') {
                            $offlineGehenCls = 'secondary';
                        } elseif ($offlineVorschlag === 'gehen') {
                            $offlineKommenCls = 'secondary';
                        }
                    ?>

                    <?php if ($offlineVorschlag !== ''): ?>
                        <div class="status-small" style="margin-top:6px;">
                            Vorschlag: <strong><?php echo htmlspecialchars($labelTyp($offlineVorschlag), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($offlineLetzteTyp !== '' || $offlineLetzteZeit !== ''): ?>
                        <div class="status-small" style="margin-top:4px;">
                            Letzte Offline-Buchung:
                            <strong><?php echo htmlspecialchars($labelTyp($offlineLetzteTyp), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            <?php if ($offlineLetzteZeit !== ''): ?>
                                um <?php echo htmlspecialchars($fmtZeitpunktDE($offlineLetzteZeit), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" action="terminal.php?aktion=kommen" class="terminal-button-form" style="margin-top:18px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="rfid_code" value="<?php echo htmlspecialchars($offlineRfid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <?php
                        $nachtshiftOption = false;
                        try {
                            $nowTime = (new DateTimeImmutable('now'))->format('H:i:s');
                            $nachtshiftOption = ($nowTime >= '18:00:00');
                        } catch (Throwable $e) {
                            $nachtshiftOption = false;
                        }
                    ?>
                    <?php if ($nachtshiftOption): ?>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="nachtshift" value="1">
                            Nachtschicht (Kommen nach 18:00)
                        </label>
                    <?php endif; ?>
                    <button type="submit" class="primary <?php echo htmlspecialchars($offlineKommenCls, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Kommen</button>
                </form>

                <form method="post" action="terminal.php?aktion=gehen" class="terminal-button-form" style="margin-top:12px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="rfid_code" value="<?php echo htmlspecialchars($offlineRfid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <button type="submit" class="primary <?php echo htmlspecialchars($offlineGehenCls, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Gehen</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p class="hinweis">
				Bitte RFID-Chip an das Lesegerät halten.<br>
                Der RFID-Leser „tippt“ den Code meist automatisch in das Feld und bestätigt mit Enter.
            </p>

				<form method="post" action="terminal.php?aktion=start" class="login-form terminal-login-form">
				<label for="rfid_code">RFID</label>
                <input type="text" id="rfid_code" name="rfid_code" autocomplete="off" autofocus>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

					<div class="button-row terminal-login-buttonrow">
                    <button type="submit">Anmelden</button>
                </div>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <?php
            $mitarbeiterName = '';
            $mitarbeiterId = 0;
            if (is_array($mitarbeiter)) {
                $mitarbeiterName = trim((string)($mitarbeiter['vorname'] ?? '') . ' ' . (string)($mitarbeiter['nachname'] ?? ''));
                $mitarbeiterId = (int)($mitarbeiter['id'] ?? 0);
            }
        ?>

        <?php if ($zeigeArbeitszeitUebersichtSeite): ?>
            <div class="status-box">
                <div class="status-title"><span>Arbeitszeit-Übersicht</span></div>
                <?php
                    $jetzt = new DateTimeImmutable('now');
                    $aktuellesJahr = (int)$jetzt->format('Y');
                    $auswahlJahr = (int)($monatsStatus['jahr'] ?? $aktuellesJahr);
                    $auswahlMonat = (int)($monatsStatus['monat'] ?? (int)$jetzt->format('n'));
                    $jahre = range($aktuellesJahr - 2, $aktuellesJahr + 1);
                ?>
                <form method="get" action="terminal.php" class="terminal-button-form" style="margin-top:8px;">
                    <input type="hidden" name="aktion" value="start">
                    <input type="hidden" name="view" value="arbeitszeit">
                    <label style="display:inline-block; margin-right:8px;">
                        Jahr:
                        <select name="jahr">
                            <?php foreach ($jahre as $jahrOption): ?>
                                <option value="<?php echo (int)$jahrOption; ?>"<?php echo ($jahrOption === $auswahlJahr) ? ' selected' : ''; ?>>
                                    <?php echo (int)$jahrOption; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:inline-block; margin-right:8px;">
                        Monat:
                        <select name="monat">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo (int)$m; ?>"<?php echo ($m === $auswahlMonat) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars(sprintf('%02d', $m), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <button type="submit" class="secondary">Anzeigen</button>
                </form>
                <div class="status-small mt-025">Hinweis: „Ist bis heute“ bezieht sich auf das gewählte Monatsdatum.</div>
                <div class="status-small">
                    Mitarbeiter:
                    <strong><?php echo htmlspecialchars($mitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    (ID: <?php echo (int)$mitarbeiterId; ?>)
                </div>

                <?php if ($hauptdbOk !== true): ?>
                    <div class="status-small mt-05"><strong>Nur im Online-Modus verfügbar.</strong></div>
                <?php elseif (isset($monatsStatus) && is_array($monatsStatus) && isset($monatsStatus['ist_bisher'])): ?>
                    <div class="status-small mt-05">Soll-Stunden (Monat gesamt): <strong><?php echo htmlspecialchars((string)($monatsStatus['soll_monat_gesamt'] ?? '0.00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                    <div class="status-small">Soll-Stunden (bis heute): <strong><?php echo htmlspecialchars((string)($monatsStatus['soll_bis_heute'] ?? '0.00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                    <div class="status-small">Ist-Stunden (bis heute): <strong><?php echo htmlspecialchars((string)($monatsStatus['ist_bisher'] ?? '0.00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>

                    <?php if (isset($monatsStatus['zusammenfassung']) && is_array($monatsStatus['zusammenfassung'])): ?>
                        <?php $zusammenfassung = $monatsStatus['zusammenfassung']; ?>
                        <div class="status-small mt-05"><strong>Monatsübersicht (PDF)</strong></div>
                        <div class="status-small">IST: <strong><?php echo htmlspecialchars((string)($zusammenfassung['ist'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Arzt: <strong><?php echo htmlspecialchars((string)($zusammenfassung['arzt'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Krank LFZ: <strong><?php echo htmlspecialchars((string)($zusammenfassung['krank_lfz'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Krank KK: <strong><?php echo htmlspecialchars((string)($zusammenfassung['krank_kk'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Urlaub: <strong><?php echo htmlspecialchars((string)($zusammenfassung['urlaub'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Feiertag: <strong><?php echo htmlspecialchars((string)($zusammenfassung['feiertag'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Kurzarbeit: <strong><?php echo htmlspecialchars((string)($zusammenfassung['kurzarbeit'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Sonst: <strong><?php echo htmlspecialchars((string)($zusammenfassung['sonst'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Summen: <strong><?php echo htmlspecialchars((string)($zusammenfassung['summen'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <div class="status-small">Differenz: <strong><?php echo htmlspecialchars((string)($zusammenfassung['differenz'] ?? '0,00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <?php if (!empty($zusammenfassung['stundenkonto'])): ?>
                            <div class="status-small">Stundenkonto (bis Vormonat): <strong><?php echo htmlspecialchars((string)$zusammenfassung['stundenkonto'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($stundenkontoSaldoFehler)): ?>
                        <div class="status-small mt-025"><strong><?php echo htmlspecialchars((string)$stundenkontoSaldoFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                    <?php elseif (is_array($stundenkontoSaldo) && isset($stundenkontoSaldo['saldo_stunden_bis_vormonat'])): ?>
                        <div class="status-small mt-025">Gutstunden/Minusstunden (Stand bis Vormonat): <strong><?php echo htmlspecialchars((string)($stundenkontoSaldo['saldo_stunden_bis_vormonat'] ?? '0.00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                    <?php endif; ?>
                    <?php if ((($monatsStatus['daten_ok'] ?? true) !== true)): ?>
                        <?php
                            $monatsStatusFehler = null;
                            if (!empty($monatsStatus['fehler_text'])) {
                                $monatsStatusFehler = (string)$monatsStatus['fehler_text'];
                            }
                        ?>
                        <div class="status-small mt-05"><strong><?php echo htmlspecialchars($monatsStatusFehler ?? 'Monatsübersicht konnte nicht geladen werden.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                        $monatsStatusFehler = null;
                        if (isset($monatsStatus) && is_array($monatsStatus) && !empty($monatsStatus['fehler_text'])) {
                            $monatsStatusFehler = (string)$monatsStatus['fehler_text'];
                        }
                    ?>
                    <div class="status-small mt-05"><strong><?php echo htmlspecialchars($monatsStatusFehler ?? 'Monatsübersicht konnte nicht geladen werden.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                <?php endif; ?>
            </div>

            <div class="button-row">
                <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Zurück</a>
            </div>

            <?php require __DIR__ . '/_layout_bottom.php'; return; ?>
        <?php endif; ?>

        <details class="status-box terminal-mitarbeiterpanel">
            <summary class="status-title">
                <a href="terminal.php?aktion=start&amp;view=arbeitszeit" style="display:flex; justify-content:space-between; width:100%; color:inherit; text-decoration:none;">
                    <span>
                        Mitarbeiter:
                        <strong><?php echo htmlspecialchars($mitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        (ID: <?php echo (int)$mitarbeiterId; ?>)
                    </span>
                    <span class="status-small">Arbeitszeit</span>
                </a>
            </summary>
        </details>


        <?php if ($zeigeUrlaubUebersicht): ?>
            <p class="hinweis">
                <strong>Urlaub Übersicht</strong><br>
                Überblick über Urlaubssaldo, Anträge und Betriebsferien.
            </p>

            <?php if ($hauptdbOk !== true): ?>
                <div class="status-box">
                    <div class="status-title"><span>Hinweis</span></div>
                    <div class="status-small"><strong>Nur im Online-Modus verfügbar.</strong></div>
                </div>
            <?php elseif (!empty($urlaubSaldoFehler)): ?>
                <div class="status-box">
                    <div class="status-title"><span>Urlaub</span></div>
                    <div class="status-small"><strong><?php echo htmlspecialchars((string)$urlaubSaldoFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                </div>
            <?php elseif (is_array($urlaubSaldo)): ?>
                <div class="status-box">
                    <div class="status-title"><span>Urlaubssaldo</span></div>
                    <div class="status-small">
                        Verfügbar gesamt: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['verbleibend'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        &nbsp;|&nbsp; Genehmigt: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['genommen'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        &nbsp;|&nbsp; Offen: <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['beantragt'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    </div>
                    <div class="status-small mt-025">
						<?php
							$anspruchBasis = (float)($urlaubSaldo['anspruch'] ?? 0);
							$korrekturTage = (float)($urlaubSaldo['korrektur'] ?? 0);
							$anspruchEffektiv = $anspruchBasis + $korrekturTage;
						?>
						Jahresanspruch (<?php echo (int)$urlaubJahr; ?>): <strong><?php echo htmlspecialchars($fmtTage($anspruchBasis), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
						&nbsp;|&nbsp; Übertrag (<?php echo (int)($urlaubJahr - 1); ?>): <strong><?php echo htmlspecialchars($fmtTage($urlaubSaldo['uebertrag'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
						<?php if (abs($korrekturTage) > 0.0001): ?>
							&nbsp;|&nbsp; Korrektur: <strong><?php echo htmlspecialchars($fmtTage($korrekturTage), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
							&nbsp;|&nbsp; Effektiv: <strong><?php echo htmlspecialchars($fmtTage($anspruchEffektiv), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
						<?php endif; ?>
                    </div>
                    <?php if (!empty($urlaubSaldo['hinweis'])): ?>
                        <div class="status-small mt-025">
                            <?php echo htmlspecialchars((string)$urlaubSaldo['hinweis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="status-box">
                    <div class="status-title"><span>Urlaub</span></div>
                    <div class="status-small">Urlaub: Daten aktuell nicht verfügbar. Bitte Seite neu laden.</div>
                </div>
            <?php endif; ?>

            <?php if ($hauptdbOk === true && !empty($betriebsferienListe)): ?>
                <div class="status-box">
                    <div class="status-title"><span>Betriebsferien</span></div>
                    <?php foreach ($betriebsferienListe as $bf): ?>
                        <?php
                            $bfVon = isset($bf['von_datum']) ? (string)$bf['von_datum'] : '';
                            $bfBis = isset($bf['bis_datum']) ? (string)$bf['bis_datum'] : '';
                            $bfText = isset($bf['beschreibung']) ? trim((string)$bf['beschreibung']) : '';
                            $bfTage = null;
                            if (isset($bf['benoetigte_tage']) && is_numeric($bf['benoetigte_tage'])) {
                                $bfTage = (float)$bf['benoetigte_tage'];
                            }

                        ?>
                        <div class="status-small mt-025">
                            <strong><?php echo htmlspecialchars($fmtDatum($bfVon), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            bis
                            <strong><?php echo htmlspecialchars($fmtDatum($bfBis), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            <?php if ($bfText !== ''): ?>
                                &nbsp;–&nbsp;<?php echo htmlspecialchars($bfText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            <?php endif; ?>
                            <?php if ($bfTage !== null && $bfTage > 0.0001): ?>
                                &nbsp;|&nbsp;benötigte Urlaubstage: <strong><?php echo htmlspecialchars($fmtTage($bfTage), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
                $urlaubListeOpen = true;
                $urlaubListeTitel = 'Meine Urlaubsanträge (letzte 12)';
                require __DIR__ . '/_urlaub_antraege_liste.php';
            ?>

            <div class="button-row">
                <a href="terminal.php?aktion=urlaub_beantragen&amp;modus=antrag" class="button-link terminal-primary-action">Urlaub beantragen</a>
                <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Zurück</a>
            </div>
        <?php elseif ($zeigeUrlaubFormular): ?>
            <p class="hinweis">
                <strong>Urlaub beantragen</strong><br>
                Bitte in drei Schritten ausfüllen.
            </p>

            <form method="post" action="terminal.php?aktion=logout" id="urlaub_wizard_exit_form" class="is-hidden">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </form>

            <form method="post" action="terminal.php?aktion=urlaub_beantragen" class="login-form" id="urlaub_wizard_formular" data-initialer-schritt="<?php echo $urlaubWizardSchritt; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" id="wizard_aktion" name="wizard_aktion" value="speichern">
                <input type="hidden" id="wizard_schritt" name="wizard_schritt" value="<?php echo $urlaubWizardSchritt; ?>">
                <input type="hidden" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars((string)($urlaubFormular['von_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                <input type="hidden" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars((string)($urlaubFormular['bis_datum'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                <input type="hidden" id="kommentar_mitarbeiter_wizard" name="kommentar_mitarbeiter" value="<?php echo htmlspecialchars((string)($urlaubFormular['kommentar_mitarbeiter'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <?php // Hinweis: Im Wizard keine weiteren <form>-Includes verwenden, da der HTML-Parser sonst Formulare implizit schließt. ?>

                <div class="terminal-wizard-schritt" data-schritt="ab_wann"<?php echo ($urlaubWizardSchritt === 1 ? '' : ' hidden'); ?>>
                    <p class="status-small"><strong>Schritt 1 von 3: ab_wann</strong></p>
                    <label>Startdatum</label>
                    <div class="terminal-datum-eingabe" data-datum-block="von">
                        <div class="terminal-datum-grid" aria-label="Startdatum auswählen">
                            <div class="terminal-datum-zeile" data-segment="tag">
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="runter" aria-label="Tag verringern">−</button>
                                <input type="text" class="terminal-datum-wert" value="<?php echo (int)$vonDatumTeile['tag']; ?>" aria-label="Tag" readonly>
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="hoch" aria-label="Tag erhöhen">+</button>
                                <span class="terminal-datum-zeile-bezeichnung">Tag</span>
                            </div>
                            <div class="terminal-datum-zeile" data-segment="monat">
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="runter" aria-label="Monat verringern">−</button>
                                <input type="text" class="terminal-datum-wert" value="<?php echo (int)$vonDatumTeile['monat']; ?>" aria-label="Monat" readonly>
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="hoch" aria-label="Monat erhöhen">+</button>
                                <span class="terminal-datum-zeile-bezeichnung">Monat</span>
                            </div>
                            <div class="terminal-datum-zeile" data-segment="jahr">
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="runter" aria-label="Jahr verringern">−</button>
                                <input type="text" class="terminal-datum-wert" value="<?php echo (int)$vonDatumTeile['jahr']; ?>" aria-label="Jahr" readonly>
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="hoch" aria-label="Jahr erhöhen">+</button>
                                <span class="terminal-datum-zeile-bezeichnung">Jahr</span>
                            </div>
                        </div>
                    </div>
                    <p class="terminal-wizard-fehlermeldung" data-fehler-fuer="ab_wann" hidden></p>
                </div>

                <div class="terminal-wizard-schritt" data-schritt="bis_wann"<?php echo ($urlaubWizardSchritt === 2 ? '' : ' hidden'); ?>>
                    <p class="status-small"><strong>Schritt 2 von 3: bis_wann</strong></p>
                    <label>Enddatum</label>
                    <div class="terminal-datum-eingabe" data-datum-block="bis">
                        <div class="terminal-datum-grid" aria-label="Enddatum auswählen">
                            <div class="terminal-datum-zeile" data-segment="tag">
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="runter" aria-label="Tag verringern">−</button>
                                <input type="text" class="terminal-datum-wert" value="<?php echo (int)$bisDatumTeile['tag']; ?>" aria-label="Tag" readonly>
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="hoch" aria-label="Tag erhöhen">+</button>
                                <span class="terminal-datum-zeile-bezeichnung">Tag</span>
                            </div>
                            <div class="terminal-datum-zeile" data-segment="monat">
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="runter" aria-label="Monat verringern">−</button>
                                <input type="text" class="terminal-datum-wert" value="<?php echo (int)$bisDatumTeile['monat']; ?>" aria-label="Monat" readonly>
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="hoch" aria-label="Monat erhöhen">+</button>
                                <span class="terminal-datum-zeile-bezeichnung">Monat</span>
                            </div>
                            <div class="terminal-datum-zeile" data-segment="jahr">
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="runter" aria-label="Jahr verringern">−</button>
                                <input type="text" class="terminal-datum-wert" value="<?php echo (int)$bisDatumTeile['jahr']; ?>" aria-label="Jahr" readonly>
                                <button type="button" class="terminal-zahlenfeld-knopf" data-richtung="hoch" aria-label="Jahr erhöhen">+</button>
                                <span class="terminal-datum-zeile-bezeichnung">Jahr</span>
                            </div>
                        </div>
                    </div>
                    <p class="terminal-wizard-fehlermeldung" data-fehler-fuer="bis_wann" hidden></p>
                    <p class="terminal-wizard-hinweis" data-hinweis-fuer="bis_wann" hidden></p>
                </div>

                <div class="terminal-wizard-schritt" data-schritt="kommentar"<?php echo ($urlaubWizardSchritt === 3 ? '' : ' hidden'); ?>>
                    <p class="status-small"><strong>Schritt 3 von 3: kommentar</strong></p>
                    <label for="kommentar_mitarbeiter">Kommentar (optional)</label>
                    <textarea id="kommentar_mitarbeiter" rows="8" aria-describedby="kommentar_hinweis kommentar_zeichenzahl"><?php echo htmlspecialchars((string)($urlaubFormular['kommentar_mitarbeiter'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                    <button type="button" class="secondary terminal-osk-umschalter" id="kommentar_tastatur_umschalter" aria-expanded="true" aria-controls="terminal_kommentar_tastatur">Tastatur schließen</button>
                    <div class="terminal-osk" id="terminal_kommentar_tastatur" aria-label="Bildschirmtastatur für Kommentar"></div>
                    <p id="kommentar_hinweis" class="status-small">Kommentar (optional)</p>
                    <p id="kommentar_zeichenzahl" class="status-small" aria-live="polite">0 Zeichen</p>
                </div>

                <p class="terminal-wizard-validierungsmeldung" data-wizard-validierung role="status" aria-live="polite"></p>

                <div class="button-row terminal-wizard-aktionsleiste">
                    <div class="terminal-wizard-sekundaeraktionen">
                        <button type="submit" form="urlaub_wizard_exit_form" class="secondary terminal-primary-action"<?php echo ($urlaubWizardSchritt === 1 ? "" : " hidden"); ?>>Exit</button>
                        <button type="button" class="secondary terminal-primary-action" data-nav="zurueck"<?php echo ($urlaubWizardSchritt > 1 ? '' : ' hidden'); ?>>Zurück</button>
                    </div>
                    <button type="submit" class="terminal-primary-action" data-nav="weiter" name="wizard_aktion" value="weiter"<?php echo ($urlaubWizardSchritt < 3 ? '' : ' hidden'); ?>>Weiter</button>
                    <button type="submit" class="terminal-primary-action" data-nav="speichern"<?php echo ($urlaubWizardSchritt >= 3 ? '' : ' hidden'); ?>>Speichern</button>
                </div>
            </form>

            <script>
                (function () {
                    'use strict';

                    const formular = document.getElementById('urlaub_wizard_formular');
                    if (!(formular instanceof HTMLFormElement)) {
                        return;
                    }

                    const verstecktesVonDatum = document.getElementById('von_datum');
                    const verstecktesBisDatum = document.getElementById('bis_datum');
                    const versteckterKommentar = document.getElementById('kommentar_mitarbeiter_wizard');
                    const versteckteWizardAktion = document.getElementById('wizard_aktion');
                    const versteckterWizardSchritt = document.getElementById('wizard_schritt');
                    const kommentarTextfeld = document.getElementById('kommentar_mitarbeiter');

                    if (!(verstecktesVonDatum instanceof HTMLInputElement) || !(verstecktesBisDatum instanceof HTMLInputElement) || !(versteckterKommentar instanceof HTMLInputElement)) {
                        return;
                    }

                    const wizardSchritte = ['ab_wann', 'bis_wann', 'kommentar'];
                    const initialerSchritt = parseInt(formular.getAttribute('data-initialer-schritt') || '1', 10);
                    const wizardZustand = {
                        schrittIndex: Number.isInteger(initialerSchritt) ? Math.max(0, Math.min(wizardSchritte.length - 1, initialerSchritt - 1)) : 0,
                        von_datum: verstecktesVonDatum.value,
                        bis_datum: verstecktesBisDatum.value,
                        kommentar_mitarbeiter: versteckterKommentar.value
                    };

                    const schrittElemente = Array.from(formular.querySelectorAll('[data-schritt]'));
                    const knopfZurueck = formular.querySelector('[data-nav="zurueck"]');
                    const knopfExit = formular.querySelector('[form="urlaub_wizard_exit_form"]');
                    const knopfWeiter = formular.querySelector('[data-nav="weiter"]');
                    const knopfSpeichern = formular.querySelector('[data-nav="speichern"]');
                    const kommentarZeichenzahl = document.getElementById('kommentar_zeichenzahl');
                    const tastaturUmschalter = document.getElementById('kommentar_tastatur_umschalter');
                    const tastaturContainer = document.getElementById('terminal_kommentar_tastatur');
                    const fehlermeldungAbWann = formular.querySelector('[data-fehler-fuer="ab_wann"]');
                    const fehlermeldungBisWann = formular.querySelector('[data-fehler-fuer="bis_wann"]');
                    const hinweisBisWann = formular.querySelector('[data-hinweis-fuer="bis_wann"]');
                    const wizardValidierungsmeldung = formular.querySelector('[data-wizard-validierung]');
                    let tastaturSichtbar = true;
                    let sonderzeichenModus = false;

                    const tastaturLayouts = {
                        standard: [
                            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
                            ['q', 'w', 'e', 'r', 't', 'z', 'u', 'i', 'o', 'p'],
                            ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
                            ['y', 'x', 'c', 'v', 'b', 'n', 'm', ',', '.']
                        ],
                        sonderzeichen: [
                            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
                            ['!', '"', '§', '$', '%', '&', '/', '(', ')', '='],
                            ['?', '+', '-', '*', '#', ';', ':', '_', '@', '€', 'ä', 'ö', 'ü'],
                            ['[', ']', '{', '}', '<', '>', '\\', '|', '^']
                        ]
                    };

                    function tageImMonat(jahr, monat) {
                        return new Date(jahr, monat, 0).getDate();
                    }

                    function eingrenzen(wert, min, max) {
                        if (Number.isNaN(wert)) {
                            return min;
                        }
                        return Math.min(Math.max(wert, min), max);
                    }

                    function parseDatumStreng(datumText) {
                        if (typeof datumText !== 'string') {
                            return null;
                        }

                        const treffer = datumText.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!treffer) {
                            return null;
                        }

                        const jahr = parseInt(treffer[1], 10);
                        const monat = parseInt(treffer[2], 10);
                        const tag = parseInt(treffer[3], 10);
                        const datumObjekt = new Date(jahr, monat - 1, tag);
                        if (
                            Number.isNaN(datumObjekt.getTime())
                            || datumObjekt.getFullYear() !== jahr
                            || datumObjekt.getMonth() !== monat - 1
                            || datumObjekt.getDate() !== tag
                        ) {
                            return null;
                        }

                        return datumObjekt;
                    }

                    function istNurWochenende(vonDatumObjekt, bisDatumObjekt) {
                        if (!(vonDatumObjekt instanceof Date) || !(bisDatumObjekt instanceof Date)) {
                            return false;
                        }

                        const laufendesDatum = new Date(vonDatumObjekt.getTime());
                        while (laufendesDatum <= bisDatumObjekt) {
                            const wochentag = laufendesDatum.getDay();
                            if (wochentag !== 0 && wochentag !== 6) {
                                return false;
                            }
                            laufendesDatum.setDate(laufendesDatum.getDate() + 1);
                        }

                        return true;
                    }

                    function enthaeltWochenende(vonDatumObjekt, bisDatumObjekt) {
                        if (!(vonDatumObjekt instanceof Date) || !(bisDatumObjekt instanceof Date)) {
                            return false;
                        }

                        const laufendesDatum = new Date(vonDatumObjekt.getTime());
                        while (laufendesDatum <= bisDatumObjekt) {
                            const wochentag = laufendesDatum.getDay();
                            if (wochentag === 0 || wochentag === 6) {
                                return true;
                            }
                            laufendesDatum.setDate(laufendesDatum.getDate() + 1);
                        }

                        return false;
                    }

                    function setzeTextNachricht(element, nachricht) {
                        if (!(element instanceof HTMLElement)) {
                            return;
                        }
                        const text = (typeof nachricht === 'string') ? nachricht.trim() : '';
                        element.textContent = text;
                        element.hidden = text === '';
                    }

                    function setzeWizardValidierungsmeldung(nachricht) {
                        if (!(wizardValidierungsmeldung instanceof HTMLElement)) {
                            return;
                        }
                        const text = (typeof nachricht === 'string') ? nachricht.trim() : '';
                        wizardValidierungsmeldung.textContent = text;
                    }

                    function pruefeAktivenSchritt() {
                        const aktuellerSchritt = wizardSchritte[wizardZustand.schrittIndex];
                        const vonDatumObjekt = parseDatumStreng(wizardZustand.von_datum);
                        const bisDatumObjekt = parseDatumStreng(wizardZustand.bis_datum);

                        setzeTextNachricht(fehlermeldungAbWann, '');
                        setzeTextNachricht(fehlermeldungBisWann, '');
                        setzeTextNachricht(hinweisBisWann, '');
                        setzeWizardValidierungsmeldung('');

                        if (aktuellerSchritt === 'ab_wann') {
                            if (vonDatumObjekt === null) {
                                setzeTextNachricht(fehlermeldungAbWann, 'Bitte ein technisch gültiges Startdatum auswählen.');
                                setzeWizardValidierungsmeldung('Schritt 1 ist ungültig: Bitte ein gültiges Startdatum auswählen.');
                                return false;
                            }
                        }

                        if (aktuellerSchritt === 'bis_wann') {
                            if (bisDatumObjekt === null) {
                                setzeTextNachricht(fehlermeldungBisWann, 'Bitte ein technisch gültiges Enddatum auswählen.');
                                setzeWizardValidierungsmeldung('Schritt 2 ist ungültig: Bitte ein gültiges Enddatum auswählen.');
                                return false;
                            }

                            if (vonDatumObjekt === null) {
                                setzeTextNachricht(fehlermeldungBisWann, 'Das Startdatum ist ungültig. Bitte Schritt „ab_wann“ prüfen.');
                                setzeWizardValidierungsmeldung('Startdatum ungültig: Bitte zuerst Schritt 1 korrigieren.');
                                return false;
                            }

                            if (bisDatumObjekt < vonDatumObjekt) {
                                setzeTextNachricht(fehlermeldungBisWann, 'Das Enddatum darf nicht vor dem Startdatum liegen.');
                                setzeWizardValidierungsmeldung('Schritt 2 ist ungültig: Enddatum darf nicht vor dem Startdatum liegen.');
                                return false;
                            }

                            if (istNurWochenende(vonDatumObjekt, bisDatumObjekt)) {
                                setzeTextNachricht(hinweisBisWann, 'Hinweis: Der gewählte Zeitraum enthält nur Wochenenden. Dadurch können 0 Urlaubstage entstehen.');
                            } else if (enthaeltWochenende(vonDatumObjekt, bisDatumObjekt)) {
                                setzeTextNachricht(hinweisBisWann, 'Hinweis: Der gewählte Zeitraum enthält Wochenendtage. Diese werden bei der Urlaubstage-Berechnung nicht als Arbeitstage gezählt.');
                            }
                        }

                        return true;
                    }

                    function aktualisiereDatumsblock(datumsblock) {
                        const datumsBlockName = datumsblock.getAttribute('data-datum-block');
                        const verstecktesDatum = (datumsBlockName === 'von') ? verstecktesVonDatum : verstecktesBisDatum;
                        const tagAnzeige = datumsblock.querySelector('[data-segment="tag"] .terminal-datum-wert');
                        const monatAnzeige = datumsblock.querySelector('[data-segment="monat"] .terminal-datum-wert');
                        const jahrAnzeige = datumsblock.querySelector('[data-segment="jahr"] .terminal-datum-wert');

                        if (!verstecktesDatum || !tagAnzeige || !monatAnzeige || !jahrAnzeige) {
                            return;
                        }

                        const jahr = eingrenzen(parseInt(jahrAnzeige.value, 10), 2020, 2100);
                        const monat = eingrenzen(parseInt(monatAnzeige.value, 10), 1, 12);
                        const maxTag = tageImMonat(jahr, monat);
                        const tag = eingrenzen(parseInt(tagAnzeige.value, 10), 1, maxTag);

                        jahrAnzeige.value = String(jahr);
                        monatAnzeige.value = String(monat);
                        tagAnzeige.value = String(tag);

                        const tagMitNull = String(tag).padStart(2, '0');
                        const monatMitNull = String(monat).padStart(2, '0');
                        verstecktesDatum.value = jahr + '-' + monatMitNull + '-' + tagMitNull;

                        if (datumsBlockName === 'von') {
                            wizardZustand.von_datum = verstecktesDatum.value;
                        } else {
                            wizardZustand.bis_datum = verstecktesDatum.value;
                        }
                    }

                    function aktualisiereKommentarZeichenzahl() {
                        if (!(kommentarTextfeld instanceof HTMLTextAreaElement) || !(kommentarZeichenzahl instanceof HTMLElement)) {
                            return;
                        }
                        kommentarZeichenzahl.textContent = kommentarTextfeld.value.length + ' Zeichen';
                    }

                    function synchronisiereKommentarZustand() {
                        if (!(kommentarTextfeld instanceof HTMLTextAreaElement)) {
                            return;
                        }
                        wizardZustand.kommentar_mitarbeiter = kommentarTextfeld.value;
                        versteckterKommentar.value = kommentarTextfeld.value;
                        aktualisiereKommentarZeichenzahl();
                    }

                    function fokussiereKommentarTextfeld(ansEndeSetzen) {
                        if (!(kommentarTextfeld instanceof HTMLTextAreaElement)) {
                            return;
                        }
                        kommentarTextfeld.focus({ preventScroll: true });
                        if (ansEndeSetzen) {
                            const cursorPosition = kommentarTextfeld.value.length;
                            kommentarTextfeld.setSelectionRange(cursorPosition, cursorPosition);
                        }
                    }

                    function fuegeKommentarTextEin(text) {
                        if (!(kommentarTextfeld instanceof HTMLTextAreaElement) || typeof text !== 'string') {
                            return;
                        }
                        const start = kommentarTextfeld.selectionStart ?? kommentarTextfeld.value.length;
                        const ende = kommentarTextfeld.selectionEnd ?? kommentarTextfeld.value.length;
                        const davor = kommentarTextfeld.value.slice(0, start);
                        const danach = kommentarTextfeld.value.slice(ende);
                        kommentarTextfeld.value = davor + text + danach;
                        const neuePosition = start + text.length;
                        kommentarTextfeld.setSelectionRange(neuePosition, neuePosition);
                        synchronisiereKommentarZustand();
                        fokussiereKommentarTextfeld(false);
                    }

                    function loescheKommentarZeichen() {
                        if (!(kommentarTextfeld instanceof HTMLTextAreaElement)) {
                            return;
                        }
                        const start = kommentarTextfeld.selectionStart ?? kommentarTextfeld.value.length;
                        const ende = kommentarTextfeld.selectionEnd ?? kommentarTextfeld.value.length;
                        if (start !== ende) {
                            kommentarTextfeld.value = kommentarTextfeld.value.slice(0, start) + kommentarTextfeld.value.slice(ende);
                            kommentarTextfeld.setSelectionRange(start, start);
                        } else if (start > 0) {
                            kommentarTextfeld.value = kommentarTextfeld.value.slice(0, start - 1) + kommentarTextfeld.value.slice(ende);
                            kommentarTextfeld.setSelectionRange(start - 1, start - 1);
                        }
                        synchronisiereKommentarZustand();
                        fokussiereKommentarTextfeld(false);
                    }

                    function aktualisiereTastaturUmschalter() {
                        if (!(tastaturUmschalter instanceof HTMLButtonElement) || !(tastaturContainer instanceof HTMLElement)) {
                            return;
                        }
                        tastaturContainer.hidden = !tastaturSichtbar;
                        tastaturUmschalter.textContent = tastaturSichtbar ? 'Tastatur schließen' : 'Tastatur öffnen';
                        tastaturUmschalter.setAttribute('aria-expanded', tastaturSichtbar ? 'true' : 'false');
                    }

                    function erstelleTaste(label, wert, zusatzklasse) {
                        const taste = document.createElement('button');
                        taste.type = 'button';
                        taste.className = 'terminal-osk-taste' + (zusatzklasse ? ' ' + zusatzklasse : '');
                        taste.setAttribute('data-taste-wert', wert);
                        taste.textContent = label;
                        return taste;
                    }

                    function renderTastatur() {
                        if (!(tastaturContainer instanceof HTMLElement)) {
                            return;
                        }

                        tastaturContainer.innerHTML = '';
                        const layout = sonderzeichenModus ? tastaturLayouts.sonderzeichen : tastaturLayouts.standard;
                        layout.forEach(function (zeile) {
                            const zeilenElement = document.createElement('div');
                            zeilenElement.className = 'terminal-osk-zeile';
                            zeile.forEach(function (zeichen) {
                                zeilenElement.appendChild(erstelleTaste(zeichen, zeichen));
                            });
                            tastaturContainer.appendChild(zeilenElement);
                        });

                        const steuerZeile = document.createElement('div');
                        steuerZeile.className = 'terminal-osk-zeile';
                        steuerZeile.appendChild(erstelleTaste(sonderzeichenModus ? 'ABC' : '#+=', 'modus', 'terminal-osk-taste-breit'));
                        steuerZeile.appendChild(erstelleTaste('Leerzeichen', 'leerzeichen', 'terminal-osk-taste-breit terminal-osk-taste-extra-breit'));
                        steuerZeile.appendChild(erstelleTaste('Löschen', 'loeschen', 'terminal-osk-taste-breit'));
                        tastaturContainer.appendChild(steuerZeile);
                    }

                    function aktualisiereWizardAnsicht() {
                        const istLetzterSchritt = wizardZustand.schrittIndex >= wizardSchritte.length - 1;

                        if (versteckterWizardSchritt instanceof HTMLInputElement) {
                            versteckterWizardSchritt.value = String(wizardZustand.schrittIndex + 1);
                        }

                        schrittElemente.forEach(function (element) {
                            const schrittName = element.getAttribute('data-schritt');
                            const istAktiv = schrittName === wizardSchritte[wizardZustand.schrittIndex];
                            element.hidden = !istAktiv;
                        });

                        if (knopfZurueck instanceof HTMLButtonElement) {
                            knopfZurueck.hidden = wizardZustand.schrittIndex === 0;
                            knopfZurueck.disabled = wizardZustand.schrittIndex === 0;
                        }
                        if (knopfExit instanceof HTMLButtonElement) {
                            knopfExit.hidden = wizardZustand.schrittIndex !== 0;
                        }
                        if (knopfWeiter instanceof HTMLButtonElement) {
                            knopfWeiter.hidden = istLetzterSchritt;
                        }
                        if (knopfSpeichern instanceof HTMLButtonElement) {
                            knopfSpeichern.hidden = !istLetzterSchritt;
                        }

                        if (wizardSchritte[wizardZustand.schrittIndex] === 'kommentar' && kommentarTextfeld instanceof HTMLTextAreaElement) {
                            kommentarTextfeld.value = wizardZustand.kommentar_mitarbeiter;
                            fokussiereKommentarTextfeld(true);
                            tastaturSichtbar = true;
                            aktualisiereTastaturUmschalter();
                            aktualisiereKommentarZeichenzahl();
                        } else if (tastaturContainer instanceof HTMLElement) {
                            tastaturContainer.hidden = true;
                        }
                    }

                    document.querySelectorAll('[data-datum-block]').forEach(function (datumsblock) {
                        aktualisiereDatumsblock(datumsblock);

                        datumsblock.addEventListener('click', function (ereignis) {
                            const ziel = ereignis.target;
                            if (!(ziel instanceof HTMLElement) || !ziel.matches('[data-richtung]')) {
                                return;
                            }

                            const zahlenfeld = ziel.closest('[data-segment]');
                            if (!zahlenfeld) {
                                return;
                            }

                            const eingabe = zahlenfeld.querySelector('.terminal-datum-wert');
                            if (!(eingabe instanceof HTMLInputElement)) {
                                return;
                            }

                            const schritt = ziel.getAttribute('data-richtung') === 'hoch' ? 1 : -1;
                            const segment = zahlenfeld.getAttribute('data-segment');
                            const aktuellerWert = parseInt(eingabe.value, 10) || 0;

                            if (segment === 'monat') {
                                const neuerWert = ((aktuellerWert - 1 + schritt + 12) % 12) + 1;
                                eingabe.value = String(neuerWert);
                            } else if (segment === 'tag') {
                                const jahrAnzeige = datumsblock.querySelector('[data-segment="jahr"] .terminal-datum-wert');
                                const monatAnzeige = datumsblock.querySelector('[data-segment="monat"] .terminal-datum-wert');
                                const maxTag = tageImMonat(parseInt(jahrAnzeige.value, 10) || 2026, parseInt(monatAnzeige.value, 10) || 1);
                                const neuerWert = ((aktuellerWert - 1 + schritt + maxTag) % maxTag) + 1;
                                eingabe.value = String(neuerWert);
                            } else {
                                eingabe.value = String(eingrenzen(aktuellerWert + schritt, 2020, 2100));
                            }

                            aktualisiereDatumsblock(datumsblock);
                            pruefeAktivenSchritt();
                        });
                    });

                    if (kommentarTextfeld instanceof HTMLTextAreaElement) {
                        kommentarTextfeld.addEventListener('input', function () {
                            synchronisiereKommentarZustand();
                        });

                        kommentarTextfeld.addEventListener('keydown', function (ereignis) {
                            if (ereignis.key === 'Enter' && ereignis.ctrlKey) {
                                ereignis.preventDefault();
                            }
                        });
                    }

                    if (tastaturUmschalter instanceof HTMLButtonElement) {
                        tastaturUmschalter.addEventListener('click', function () {
                            tastaturSichtbar = !tastaturSichtbar;
                            aktualisiereTastaturUmschalter();
                            if (tastaturSichtbar) {
                                fokussiereKommentarTextfeld(false);
                            }
                        });
                    }

                    if (tastaturContainer instanceof HTMLElement) {
                        tastaturContainer.addEventListener('click', function (ereignis) {
                            const ziel = ereignis.target;
                            if (!(ziel instanceof HTMLElement)) {
                                return;
                            }
                            const taste = ziel.closest('[data-taste-wert]');
                            if (!(taste instanceof HTMLElement)) {
                                return;
                            }

                            const tastaturWert = taste.getAttribute('data-taste-wert');
                            if (tastaturWert === 'modus') {
                                sonderzeichenModus = !sonderzeichenModus;
                                renderTastatur();
                                fokussiereKommentarTextfeld(false);
                                return;
                            }
                            if (tastaturWert === 'leerzeichen') {
                                fuegeKommentarTextEin(' ');
                                return;
                            }
                            if (tastaturWert === 'loeschen') {
                                loescheKommentarZeichen();
                                return;
                            }
                            if (typeof tastaturWert === 'string' && tastaturWert !== '') {
                                fuegeKommentarTextEin(tastaturWert);
                            }
                        });
                    }

                    if (knopfZurueck instanceof HTMLButtonElement) {
                        knopfZurueck.addEventListener('click', function () {
                            wizardZustand.schrittIndex = Math.max(0, wizardZustand.schrittIndex - 1);
                            aktualisiereWizardAnsicht();
                        });
                    }

                    function geheZumNaechstenSchrittNachValidierung() {
                        if (!pruefeAktivenSchritt()) {
                            return;
                        }

                        wizardZustand.schrittIndex = Math.min(wizardSchritte.length - 1, wizardZustand.schrittIndex + 1);
                        aktualisiereWizardAnsicht();
                    }

                    if (knopfWeiter instanceof HTMLButtonElement) {
                        knopfWeiter.addEventListener('click', function (ereignis) {
                            if (versteckteWizardAktion instanceof HTMLInputElement) {
                                versteckteWizardAktion.value = 'weiter';
                            }
                            if (versteckterWizardSchritt instanceof HTMLInputElement) {
                                versteckterWizardSchritt.value = String(wizardZustand.schrittIndex + 1);
                            }

                            // Wenn JavaScript läuft, bleibt der Wechsel clientseitig.
                            // Ohne JavaScript greift der Submit-Fallback auf dem Server.
                            ereignis.preventDefault();
                            geheZumNaechstenSchrittNachValidierung();
                        });
                    }

                    formular.addEventListener('submit', function (ereignis) {
                        if (versteckteWizardAktion instanceof HTMLInputElement) {
                            versteckteWizardAktion.value = 'speichern';
                        }
                        if (versteckterWizardSchritt instanceof HTMLInputElement) {
                            versteckterWizardSchritt.value = String(wizardZustand.schrittIndex + 1);
                        }

                        const istLetzterSchritt = wizardZustand.schrittIndex >= wizardSchritte.length - 1;

                        if (!istLetzterSchritt) {
                            ereignis.preventDefault();
                            geheZumNaechstenSchrittNachValidierung();
                            return;
                        }

                        if (!pruefeAktivenSchritt()) {
                            ereignis.preventDefault();
                            return;
                        }

                        versteckterKommentar.value = (kommentarTextfeld instanceof HTMLTextAreaElement) ? kommentarTextfeld.value : wizardZustand.kommentar_mitarbeiter;
                    });

                    renderTastatur();
                    aktualisiereTastaturUmschalter();
                    aktualisiereKommentarZeichenzahl();
                    aktualisiereWizardAnsicht();
                    formular.setAttribute('data-wizard-initialisiert', '1');
                })();
            </script>

            <script>
                (function () {
                    'use strict';

                    var formular = document.getElementById('urlaub_wizard_formular');
                    if (!(formular instanceof HTMLFormElement)) {
                        return;
                    }

                    // Notfall-Fallback: Wenn das Hauptskript wegen Browser-Inkompatibilität
                    // oder eines Laufzeitfehlers nicht korrekt initialisiert, bleibt der
                    // „Weiter“-Knopf trotzdem benutzbar.
                    if (formular.getAttribute('data-wizard-initialisiert') === '1') {
                        return;
                    }

                    var schrittElemente = Array.prototype.slice.call(formular.querySelectorAll('[data-schritt]'));
                    if (schrittElemente.length === 0) {
                        return;
                    }

                    var knopfZurueck = formular.querySelector('[data-nav="zurueck"]');
                    var knopfExit = formular.querySelector('[form="urlaub_wizard_exit_form"]');
                    var knopfWeiter = formular.querySelector('[data-nav="weiter"]');
                    var knopfSpeichern = formular.querySelector('[data-nav="speichern"]');
                    var schrittIndex = 0;

                    function aktualisiereAnsicht() {
                        var istLetzterSchritt = schrittIndex >= (schrittElemente.length - 1);

                        for (var i = 0; i < schrittElemente.length; i++) {
                            schrittElemente[i].hidden = (i !== schrittIndex);
                        }

                        if (knopfZurueck instanceof HTMLButtonElement) {
                            knopfZurueck.hidden = schrittIndex === 0;
                            knopfZurueck.disabled = schrittIndex === 0;
                        }
                        if (knopfExit instanceof HTMLButtonElement) {
                            knopfExit.hidden = schrittIndex !== 0;
                        }
                        if (knopfWeiter instanceof HTMLButtonElement) {
                            knopfWeiter.hidden = istLetzterSchritt;
                        }
                        if (knopfSpeichern instanceof HTMLButtonElement) {
                            knopfSpeichern.hidden = !istLetzterSchritt;
                        }
                    }

                    if (knopfZurueck instanceof HTMLButtonElement) {
                        knopfZurueck.addEventListener('click', function () {
                            schrittIndex = Math.max(0, schrittIndex - 1);
                            aktualisiereAnsicht();
                        });
                    }

                    if (knopfWeiter instanceof HTMLButtonElement) {
                        knopfWeiter.addEventListener('click', function () {
                            schrittIndex = Math.min(schrittElemente.length - 1, schrittIndex + 1);
                            aktualisiereAnsicht();
                        });
                    }

                    formular.addEventListener('submit', function (ereignis) {
                        if (schrittIndex < (schrittElemente.length - 1)) {
                            ereignis.preventDefault();
                            schrittIndex = Math.min(schrittElemente.length - 1, schrittIndex + 1);
                            aktualisiereAnsicht();
                        }
                    });

                    aktualisiereAnsicht();
                })();
            </script>
        <?php elseif ($zeigeRfidZuweisenFormular): ?>
            <p class="hinweis">
                <strong>RFID-Chip zu Mitarbeiter zuweisen</strong><br>
                Mitarbeiter auswählen und RFID-Chip an das Lesegerät halten.
            </p>

            <form method="post" action="terminal.php?aktion=rfid_zuweisen" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                <label for="ziel_mitarbeiter_id">Mitarbeiter</label>
                <select id="ziel_mitarbeiter_id" name="ziel_mitarbeiter_id" required>
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($rfidZuweisenMitarbeiterListe as $m): ?>
                        <?php
                            $mid = (int)($m['id'] ?? 0);
                            $pn = (string)($m['personalnummer'] ?? '');
                            $label = trim((string)($m['nachname'] ?? '')) . ', ' . trim((string)($m['vorname'] ?? ''));
                            if ($pn !== '') {
                                $label .= ' (PN: ' . $pn . ')';
                            }
                            $selected = ((string)$mid !== '' && (string)$mid === (string)($rfidZuweisenFormular['ziel_mitarbeiter_id'] ?? '')) ? 'selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars((string)$mid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="rfid_code">RFID-Code</label>
                <input type="text" id="rfid_code" name="rfid_code" value="<?php echo htmlspecialchars((string)($rfidZuweisenFormular['rfid_code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required autofocus>

                <div class="button-row">
                    <button type="submit" class="terminal-primary-action">Speichern</button>
                    <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Abbrechen</a>
                </div>

                <p class="status-small mt-05">
                    Hinweis: Diese Funktion ist nur im Online-Modus verfügbar und wird im System-Log protokolliert.
                </p>
            </form>
        <?php elseif ($zeigeNebenauftragStartFormular): ?>
            <p class="hinweis">
                <strong>Nebenauftrag starten</strong><br>
                Bitte erst den Auftragscode scannen oder eingeben und danach den Arbeitsschritt-Code.
                (Maschine ist optional.) Ein Nebenauftrag läuft parallel zum Hauptauftrag.
            </p>

            <form method="post" action="terminal.php?aktion=nebenauftrag_starten" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                <label for="neben_auftragscode">Auftragscode</label>
                <input type="text" id="neben_auftragscode" name="auftragscode" value="<?php echo htmlspecialchars((string)($nebenauftragFormular['auftragscode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required autofocus>

                <label for="neben_arbeitsschritt_code">Arbeitsschritt-Code</label>
                <input type="text" id="neben_arbeitsschritt_code" name="arbeitsschritt_code" value="<?php echo htmlspecialchars((string)($nebenauftragFormular['arbeitsschritt_code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off" required>

                <label for="neben_maschine_id">Maschine (optional, Barcode: id_name möglich)</label>
                <input type="text" id="neben_maschine_id" name="maschine_id" value="<?php echo htmlspecialchars((string)($nebenauftragFormular['maschine_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" inputmode="numeric" autocomplete="off" placeholder="z.B. 12">

                <div class="button-row">
                    <button type="submit" class="terminal-primary-action">Nebenauftrag starten</button>
                    <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Abbrechen</a>
                </div>
            </form>

            <script>
            (function () {
              const auftrag = document.getElementById('neben_auftragscode');
              const schritt = document.getElementById('neben_arbeitsschritt_code');
              const maschine = document.getElementById('neben_maschine_id');

              // Barcode-Scanner senden meistens ein "Enter" nach dem Scan.
              // Wir springen dann bequem ins naechste Feld.
              if (auftrag && schritt) {
                auftrag.addEventListener('keydown', (ev) => {
                  if (ev.key === 'Enter') {
                    ev.preventDefault();
                    schritt.focus();
                    schritt.select?.();
                  }
                });
              }

              if (schritt && maschine) {
                schritt.addEventListener('keydown', (ev) => {
                  if (ev.key === 'Enter') {
                    ev.preventDefault();
                    maschine.focus();
                    maschine.select?.();
                  }
                });
              }

              if (maschine) {
                maschine.addEventListener('keydown', (ev) => {
                  if (ev.key === 'Enter') {
                    // Maschine ist optional: Enter startet/submit.
                    ev.preventDefault();
                    const form = maschine.closest('form');
                    if (form) {
                      form.submit();
                    }
                  }
                });
              }
            })();
            </script>

        <?php elseif ($zeigeNebenauftragStopFormular): ?>
            <p class="hinweis">
                <strong>Nebenauftrag stoppen</strong><br>
                Nebenauftrag auswählen oder Auftragscode scannen.
            </p>

            <?php if ($nebenauftragStopOfflineModus): ?>
                <div class="status-small mt-05">
                    Hauptdatenbank offline – Nebenauftrag kann nur per Auftragscode gestoppt werden.
                </div>

                <form method="post" action="terminal.php?aktion=nebenauftrag_stoppen" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                    <label for="auftragscode">Auftragscode</label>
                    <input type="text" id="auftragscode" name="auftragscode" value="" placeholder="Scan" autocomplete="off" required autofocus>

                    <div class="button-row">
                        <button type="submit" class="terminal-primary-action">Nebenauftrag stoppen</button>
                        <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Abbrechen</a>
                    </div>
                </form>

            <?php elseif (empty($laufendeNebenauftraege)): ?>
                <div class="status-small mt-05">
                    Es läuft aktuell kein Nebenauftrag.
                </div>
                <div class="button-row">
                    <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Zurück</a>
                </div>
            <?php else: ?>
                <form method="post" action="terminal.php?aktion=nebenauftrag_stoppen" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                    <label for="auftragszeit_id">Laufender Nebenauftrag</label>
                    <select id="auftragszeit_id" name="auftragszeit_id" autofocus>
                        <?php foreach ($laufendeNebenauftraege as $na): ?>
                            <?php
                                $naId = isset($na['id']) ? (int)$na['id'] : 0;
                                $naCode = isset($na['auftragscode']) ? (string)$na['auftragscode'] : '';
                                $naStart = isset($na['startzeit']) ? (string)$na['startzeit'] : '';
                                $naStartFmt = $naStart;
                                try {
                                    if ($naStart !== '') { $naStartFmt = (new DateTimeImmutable($naStart))->format('d.m.Y H:i'); }
                                } catch (Throwable $e) {
                                    $naStartFmt = $naStart;
                                }
                                $naLabel = '#' . $naId . ' – ' . $naCode . ' (Start: ' . $naStartFmt . ')';
                            ?>
                            <option value="<?php echo (int)$naId; ?>"><?php echo htmlspecialchars($naLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="auftragscode">Auftragscode (optional)</label>
                    <input type="text" id="auftragscode" name="auftragscode" value="" placeholder="Scan" autocomplete="off">

                    <div class="button-row">
                        <button type="submit" class="terminal-primary-action">Nebenauftrag stoppen</button>
                        <a href="terminal.php?aktion=start" class="button-link secondary terminal-primary-action">Abbrechen</a>
                    </div>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <?php if (!$istAnwesend): ?>
                <p class="hinweis">
                    Du bist aktuell <strong>nicht als anwesend</strong> erfasst. Bitte zuerst <strong>"Kommen"</strong> buchen.
                    <br>(Urlaub kann auch ohne Kommen beantragt werden.)
                </p>

				<div class="button-row primary-action">
                    <form method="post" action="terminal.php?aktion=kommen">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <?php
                            $nachtshiftOption = false;
                            try {
                                $nowTime = (new DateTimeImmutable('now'))->format('H:i:s');
                                $nachtshiftOption = ($nowTime >= '18:00:00');
                            } catch (Throwable $e) {
                                $nachtshiftOption = false;
                            }
                        ?>
                        <?php if ($nachtshiftOption): ?>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="checkbox" name="nachtshift" value="1">
                                Nachtschicht (Kommen nach 18:00)
                            </label>
                        <?php endif; ?>
						<button type="submit" class="terminal-primary-action">Kommen</button>
                    </form>
				</div>

				<div class="button-row">
                    <?php if ($hauptdbOk === true): ?>
                        <a href="terminal.php?aktion=urlaub_beantragen" class="button-link">Urlaub Übersicht</a>
                    <?php else: ?>
                        <span class="button-link disabled">Urlaub Übersicht</span>
                    <?php endif; ?>
                </div>

                <?php if ($darfRfidZuweisen): ?>
				<div class="button-row">
                    <?php if ($hauptdbOk === true): ?>
                        <a href="terminal.php?aktion=rfid_zuweisen" class="button-link secondary">RFID-Chip zu Mitarbeiter zuweisen</a>
                    <?php else: ?>
                        <span class="button-link disabled">RFID-Chip zu Mitarbeiter zuweisen</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <p class="hinweis">
                    Bitte gewünschte Aktion wählen. Kommen/Gehen bucht unmittelbar eine Zeit, die Auftragsfunktionen arbeiten mit laufenden Aufträgen.
                </p>

				<div class="button-row primary-action">
                    <form method="post" action="terminal.php?aktion=gehen">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
						<button type="submit" class="secondary terminal-primary-action">Gehen</button>
                    </form>
                </div>

	                <?php if (!$hatLaufenderHauptauftrag): ?>
	                    <div class="button-row primary-action">
	                        <a href="terminal.php?aktion=auftrag_starten" class="button-link terminal-primary-action">Auftrag starten</a>
	                    </div>
	                <?php else: ?>
	                    <div class="button-row primary-action">
                        <form method="post" action="terminal.php?aktion=auftrag_stoppen_quick">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button type="submit" class="secondary terminal-primary-action">Auftrag stoppen</button>
                        </form>
	                    </div>
	                <?php endif; ?>

                <?php if ($hatLaufenderHauptauftrag || $hatLaufenderNebenauftrag): ?>
                    <div class="button-row">
                        <?php if ($hatLaufenderHauptauftrag): ?>
                            <a href="terminal.php?aktion=nebenauftrag_starten" class="button-link">Nebenauftrag starten</a>
                        <?php endif; ?>

                        <?php if ($hatLaufenderNebenauftrag): ?>
                            <a href="terminal.php?aktion=nebenauftrag_stoppen" class="button-link secondary">Nebenauftrag stoppen</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($darfRfidZuweisen): ?>
                    <div class="button-row">
                        <?php if ($hauptdbOk === true): ?>
                            <a href="terminal.php?aktion=urlaub_beantragen" class="button-link">Urlaub Übersicht</a>
                            <a href="terminal.php?aktion=rfid_zuweisen" class="button-link secondary">RFID-Chip zu Mitarbeiter zuweisen</a>
                        <?php else: ?>
                            <a href="#" class="button-link disabled">Urlaub Übersicht</a>
                            <a href="#" class="button-link secondary disabled">RFID-Chip zu Mitarbeiter zuweisen</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="button-row">
                        <?php if ($hauptdbOk === true): ?>
                            <a href="terminal.php?aktion=urlaub_beantragen" class="button-link">Urlaub Übersicht</a>
                        <?php else: ?>
                            <a href="#" class="button-link disabled">Urlaub Übersicht</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // Logout-Form als hidden einbinden, Button bleibt im Layout wie bisher (T-046).
            $logoutFormHidden = true;
            require __DIR__ . '/_logout_form.php';
        ?>
        <div class="button-row primary-action">
            <button type="submit" form="logout-form" class="secondary terminal-primary-action">Abbrechen</button>
        </div>

<?php if ($istAnwesend): ?>

            <details id="uebersicht_details" class="status-box terminal-uebersichtheute mt-1">
            <summary class="status-title"><span>Übersicht (heute)</span></summary>
            <?php if ($hauptdbOk !== true): ?>
                <div class="status-small mt-05">
                    <strong>Hauptdatenbank offline – Übersicht ist nur online verfügbar.</strong>
                </div>

                <div class="status-small mt-05">
                    Datum: <strong><?php echo htmlspecialchars((string)($heuteDatum ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                </div>

                <?php if (is_array($letzterAuftrag) && (($letzterAuftrag['status'] ?? '') === 'laufend') && !empty($letzterAuftrag['auftragscode'])): ?>
                    <div class="status-small mt-035">Letzter Auftrag (lokal): <strong><?php echo htmlspecialchars((string)$letzterAuftrag['auftragscode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                <?php endif; ?>
                <?php if (is_array($letzterAuftrag) && (($letzterAuftrag['status'] ?? '') === 'pausiert') && !empty($letzterAuftrag['auftragscode'])): ?>
                    <div class="status-small mt-035">Letzter Auftrag (pausiert, lokal): <strong><?php echo htmlspecialchars((string)$letzterAuftrag['auftragscode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                <?php endif; ?>
            <?php else: ?>

            <?php if (!empty($heuteFehler)): ?>
                <div class="status-small mt-05">
                    <strong><?php echo htmlspecialchars((string)$heuteFehler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                </div>
            <?php endif; ?>

            <?php if (isset($monatsStatus) && is_array($monatsStatus) && (($monatsStatus['daten_ok'] ?? true) === true) && isset($monatsStatus['ist_bisher'])): ?>
                <div class="status-small mt-05"><strong>Monatsstatus (<?php echo htmlspecialchars(sprintf('%02d/%04d', (int)$monatsStatus['monat'], (int)$monatsStatus['jahr']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</strong></div>
		        <?php if (isset($monatsStatus['rest_bis_monatsende'])): ?>
		            <div class="status-small">Noch zu arbeiten bis Monatsende: <strong><?php echo htmlspecialchars((string)$monatsStatus['rest_bis_monatsende'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
		        <?php endif; ?>
                <?php if (isset($monatsStatus['soll_bis_heute'])): ?>
                    <div class="status-small">Arbeitsstunden in diesem Monat bis heute: <strong><?php echo htmlspecialchars((string)$monatsStatus['soll_bis_heute'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
                <?php endif; ?>
                <div class="status-small">Geleistete Arbeitsstunden bis heute: <strong><?php echo htmlspecialchars((string)$monatsStatus['ist_bisher'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong></div>
            <?php if (isset($monatsStatus['saldo_bis_heute'], $monatsStatus['saldo_label'], $monatsStatus['saldo_ampel'])): ?>
                    <?php
                        $saldoAmpel = ((string)($monatsStatus['saldo_ampel'] ?? '')) === 'ok' ? 'ok' : 'error';
                        $saldoFarbe = $saldoAmpel === 'ok' ? '#2e7d32' : '#c62828';
                    ?>
                    <div class="status-small">Saldo (bis heute): <strong style="color: <?php echo $saldoFarbe; ?>;"><?php echo htmlspecialchars((string)$monatsStatus['saldo_bis_heute'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> h</strong> <span style="opacity:0.9">(<?php echo htmlspecialchars((string)$monatsStatus['saldo_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</span></div>
                <?php endif; ?>

            <?php endif; ?>
            <?php if (isset($monatsStatus) && is_array($monatsStatus) && isset($monatsStatus['ist_bisher']) && (($monatsStatus['daten_ok'] ?? true) !== true)): ?>
                <?php
                    $monatsStatusFehler = null;
                    if (!empty($monatsStatus['fehler_text'])) {
                        $monatsStatusFehler = (string)$monatsStatus['fehler_text'];
                    }
                ?>
                <div class="status-small mt-05"><strong><?php echo htmlspecialchars($monatsStatusFehler ?? 'Monatsübersicht konnte nicht geladen werden.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
            <?php endif; ?>
            <?php if (!isset($monatsStatus) || !is_array($monatsStatus) || !isset($monatsStatus['ist_bisher'])): ?>
                <?php
                    $monatsStatusFehler = null;
                    if (isset($monatsStatus) && is_array($monatsStatus) && !empty($monatsStatus['fehler_text'])) {
                        $monatsStatusFehler = (string)$monatsStatus['fehler_text'];
                    }
                ?>
                <div class="status-small mt-05"><strong><?php echo htmlspecialchars($monatsStatusFehler ?? 'Monatsübersicht konnte nicht geladen werden.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
            <?php endif; ?>

            <div class="status-small mt-05">
                Datum: <strong><?php echo htmlspecialchars((string)($heuteDatum ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            </div>

            <div class="status-small mt-05"><strong>Heutige Buchungen</strong></div>
            <?php if (empty($heuteBuchungen)): ?>
                <div class="status-small">Keine Buchungen vorhanden.</div>
            <?php else: ?>
                <pre><?php
                    foreach ($heuteBuchungen as $row) {
                        $typ = isset($row['typ']) ? (string)$row['typ'] : '';
                        $ts  = isset($row['zeitstempel']) ? (string)$row['zeitstempel'] : '';
                        $zeit = '';
                        if ($ts !== '') {
                            try {
                                $zeit = (new DateTimeImmutable($ts))->format('H:i:s');
                            } catch (Throwable $e) {
                                $zeit = $ts;
                            }
                        }

                        $quelle = isset($row['quelle']) ? (string)$row['quelle'] : '';
                        $terminalBez = isset($row['terminal_bezeichnung']) ? (string)$row['terminal_bezeichnung'] : '';

                        $line = sprintf('%s  %-6s  (%s%s)', $zeit, $typ, $quelle, $terminalBez !== '' ? ', ' . $terminalBez : '');
                        echo htmlspecialchars(trim($line), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
                    }
                ?></pre>
            <?php endif; ?>

            <div class="status-small mt-075"><strong>Laufende Aufträge</strong></div>
            <?php if (empty($laufendeAuftraege)): ?>
                <div class="status-small">Keine laufenden Aufträge.</div>
                <?php if (is_array($letzterAuftrag) && (($letzterAuftrag['status'] ?? '') === 'laufend') && !empty($letzterAuftrag['auftragscode'])): ?>
                    <div class="status-small mt-035">Letzter Auftrag (lokal): <strong><?php echo htmlspecialchars((string)$letzterAuftrag['auftragscode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                <?php endif; ?>
                <?php if (is_array($letzterAuftrag) && (($letzterAuftrag['status'] ?? '') === 'pausiert') && !empty($letzterAuftrag['auftragscode'])): ?>
                    <div class="status-small mt-035">Letzter Auftrag (pausiert, lokal): <strong><?php echo htmlspecialchars((string)$letzterAuftrag['auftragscode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></div>
                <?php endif; ?>
            <?php else: ?>
                <pre><?php
                    foreach ($laufendeAuftraege as $row) {
                        $start = isset($row['startzeit']) ? (string)$row['startzeit'] : '';
                        $startZeit = $start;
                        if ($start !== '') {
                            try {
                                $startZeit = (new DateTimeImmutable($start))->format('H:i');
                            } catch (Throwable $e) {
                                $startZeit = $start;
                            }
                        }

                        $code = isset($row['auftragscode']) ? (string)$row['auftragscode'] : '';
                        $typ  = isset($row['typ']) ? (string)$row['typ'] : '';
                        $maschine = isset($row['maschine_id']) && $row['maschine_id'] !== null ? 'M' . (int)$row['maschine_id'] : '';

                        $line = trim(sprintf('%s  %s  %s %s', $startZeit, $code, $typ, $maschine));
                        echo htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
                    }
                ?></pre>
            <?php endif; ?>
        <?php endif; ?>
            </details>
            <?php endif; ?>


        


    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
