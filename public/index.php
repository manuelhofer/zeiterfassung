<?php
declare(strict_types=1);

// Front-Controller / Einstiegspunkt.

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

/**
 * Normalisiert Jahr/Monat aus GET-Parametern.
 *
 * Hintergrund (T-069 Teil 2a/2b):
 * Manchmal entstehen (z. B. durch Browser-Back/Forward, Copy&Paste oder Tippfehler)
 * ungueltige Werte wie monat=0 oder monat=13. Das fuehrt in den Reports/PDFs zu
 * DateTime-Fehlern und kann je nach PHP-Einstellungen Warnungen/Notices ausloesen.
 *
 * Wir clampen defensiv, statt einen Hard-Fehler zu riskieren.
 */


/**
 * Verarbeitet optionale Stepper-Aktionen für Jahr/Monat.
 *
 * Unterstützte Query-Parameter:
 * - jahr_aktion=plus|minus (alternativ jahr_plus/jahr_minus)
 * - monat_aktion=plus|minus (alternativ monat_plus/monat_minus)
 */
function verarbeite_jahr_monat_aktion(int $jahr, int $monat): array
{
    $jahrAktion = isset($_GET['jahr_aktion']) ? (string)$_GET['jahr_aktion'] : '';
    $monatAktion = isset($_GET['monat_aktion']) ? (string)$_GET['monat_aktion'] : '';

    if ($jahrAktion === '' && isset($_GET['jahr_plus'])) {
        $jahrAktion = 'plus';
    } elseif ($jahrAktion === '' && isset($_GET['jahr_minus'])) {
        $jahrAktion = 'minus';
    }

    if ($monatAktion === '' && isset($_GET['monat_plus'])) {
        $monatAktion = 'plus';
    } elseif ($monatAktion === '' && isset($_GET['monat_minus'])) {
        $monatAktion = 'minus';
    }

    if ($monatAktion === 'plus') {
        $monat++;
        if ($monat > 12) {
            $monat = 1;
            $jahr++;
        }
    } elseif ($monatAktion === 'minus') {
        $monat--;
        if ($monat < 1) {
            $monat = 12;
            $jahr--;
        }
    }

    if ($jahrAktion === 'plus') {
        $jahr++;
    } elseif ($jahrAktion === 'minus') {
        $jahr--;
    }

    return [$jahr, $monat];
}
function normalize_jahr_monat(int $jahr, int $monat): array
{
    $jetztJahr = (int)date('Y');

    // Jahr: sehr defensiv clampen (damit niemand 0/9999 ins System schiebt).
    if ($jahr < 2000 || $jahr > 2100) {
        $jahr = $jetztJahr;
    }

    // Monat: 1..12
    if ($monat < 1) {
        $monat = 1;
    }
    if ($monat > 12) {
        $monat = 12;
    }

    return [$jahr, $monat];
}

// Defaults/Seeds (idempotent, defensive)
try {
    DefaultsSeeder::ensureDefaults();
} catch (Throwable $e) {
    /* niemals hard-crashen lassen */
}

try {
    $seite = isset($_GET['seite']) ? (string)$_GET['seite'] : 'login';

    /** @var AuthService $auth */
    $auth = AuthService::getInstanz();

    // Geschützte Seiten: nur mit Login zugänglich
    $geschuetzteSeiten = [
        'dashboard',
        'smoke_test',
        'zeit_heute',
        'urlaub_meine',
        'urlaub_genehmigung',
        'report_monat',
        'report_monat_pdf',
        'report_monat_export_all',
        'auftrag',
        'auftrag_detail',
        'auftragszeit_bearbeiten',
        'mitarbeiter_admin',
        'mitarbeiter_admin_bearbeiten',
        'mitarbeiter_admin_speichern',
        'maschine_admin',
        'maschine_admin_bearbeiten',
        'maschine_admin_speichern',
        'maschine_admin_barcode_neu',
        'abteilung_admin',
        'abteilung_admin_bearbeiten',
        'abteilung_admin_speichern',
        'rollen_admin',
        'rollen_admin_bearbeiten',
        'rollen_admin_speichern',
        'feiertag_admin',
        'feiertag_admin_bearbeiten',
        'feiertag_admin_speichern',
        'betriebsferien_admin',
        'betriebsferien_admin_bearbeiten',
        'betriebsferien_admin_speichern',
        'betriebsferien_admin_toggle',
        'queue_admin',
        'zeit_rundungsregel_admin',
        'zeit_rundungsregel_admin_bearbeiten',
        'konfiguration_admin',
        'konfiguration_admin_bearbeiten',
        'urlaub_kontingent_admin',
        'urlaub_kontingent_admin_bearbeiten',
        'urlaub_kontingent_admin_speichern',
        'terminal_admin',
        'terminal_admin_bearbeiten',
        'terminal_admin_speichern',
        'kurzarbeit_admin',
        'kurzarbeit_admin_bearbeiten',
        'kurzarbeit_admin_speichern',
        'kurzarbeit_admin_toggle',
        'terminal_admin_toggle',
    ];

    if (in_array($seite, $geschuetzteSeiten, true) && !$auth->istAngemeldet()) {
        header('Location: ?seite=login');
        exit;
    }

    switch ($seite) {
        case 'login':
            $controller = new LoginController();
            $controller->index();
            break;

        case 'logout':
            $controller = new LoginController();
            $controller->logout();
            break;

        case 'dashboard':
            $controller = new DashboardController();
            $controller->index();
            break;

        case 'smoke_test':
            $controller = new SmokeTestController();
            $controller->index();
            break;

        case 'zeit_heute':
            $controller = new ZeitController();
            $controller->tagesansicht(null);
            break;

        case 'urlaub_meine':
            $controller = new UrlaubController();
            $controller->meineAntraege();
            break;

        case 'urlaub_genehmigung':
            $controller = new UrlaubController();
            $controller->genehmigungListe();
            break;

        case 'report_monat':
            $jahr  = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
            $monat = isset($_GET['monat']) ? (int)$_GET['monat'] : (int)date('n');

            [$jahr, $monat] = verarbeite_jahr_monat_aktion($jahr, $monat);
            [$jahr, $monat] = normalize_jahr_monat($jahr, $monat);

            $controller = new ReportController();
            $controller->monatsuebersicht($jahr, $monat);
            break;

        case 'report_monat_pdf':
            $jahr  = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
            $monat = isset($_GET['monat']) ? (int)$_GET['monat'] : (int)date('n');

            [$jahr, $monat] = verarbeite_jahr_monat_aktion($jahr, $monat);
            [$jahr, $monat] = normalize_jahr_monat($jahr, $monat);

            $controller = new ReportController();
            $controller->monatsPdf($jahr, $monat);
            break;

        case 'report_monat_export_all':
            $jahr  = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
            $monat = isset($_GET['monat']) ? (int)$_GET['monat'] : (int)date('n');

            [$jahr, $monat] = verarbeite_jahr_monat_aktion($jahr, $monat);
            [$jahr, $monat] = normalize_jahr_monat($jahr, $monat);

            $controller = new ReportController();
            $controller->monatsPdfExportAll($jahr, $monat);
            break;


        case 'auftrag':
            $controller = new AuftragController();
            $controller->index();
            break;

        case 'auftrag_detail':
            $controller = new AuftragController();
            $controller->detail();
            break;

        case 'auftragszeit_bearbeiten':
            $controller = new AuftragController();
            $controller->auftragszeitBearbeiten();
            break;

        case 'mitarbeiter_admin':
            $controller = new MitarbeiterAdminController();
            $controller->index();
            break;

        case 'maschine_admin':
            $controller = new MaschineAdminController();
            $controller->index();
            break;

        case 'maschine_admin_bearbeiten':
            $controller = new MaschineAdminController();
            $controller->bearbeiten();
            break;

        case 'maschine_admin_speichern':
            $controller = new MaschineAdminController();
            $controller->speichern();
            break;

        case 'maschine_admin_barcode_neu':
            $controller = new MaschineAdminController();
            $controller->barcodeNeuGenerieren();
            break;

        case 'abteilung_admin':
            $controller = new AbteilungAdminController();
            $controller->index();
            break;

        case 'abteilung_admin_bearbeiten':
            $controller = new AbteilungAdminController();
            $controller->bearbeiten();
            break;

        case 'abteilung_admin_speichern':
            $controller = new AbteilungAdminController();
            $controller->speichern();
            break;


        case 'mitarbeiter_admin_bearbeiten':
            $controller = new MitarbeiterAdminController();
            $controller->bearbeiten();
            break;

        case 'mitarbeiter_admin_speichern':
            $controller = new MitarbeiterAdminController();
            $controller->speichern();
            break;

        case 'rollen_admin':
            $controller = new RollenAdminController();
            $controller->index();
            break;

        case 'rollen_admin_bearbeiten':
            $controller = new RollenAdminController();
            $controller->bearbeiten();
            break;

        case 'rollen_admin_speichern':
            $controller = new RollenAdminController();
            $controller->speichern();
            break;

        case 'feiertag_admin':
            $controller = new FeiertagController();
            $controller->index();
            break;

        case 'feiertag_admin_bearbeiten':
            $controller = new FeiertagController();
            $controller->bearbeiten();
            break;

        case 'feiertag_admin_speichern':
            $controller = new FeiertagController();
            $controller->speichern();
            break;


        case 'betriebsferien_admin':
            $controller = new BetriebsferienAdminController();
            $controller->index();
            break;

        case 'betriebsferien_admin_bearbeiten':
            $controller = new BetriebsferienAdminController();
            $controller->bearbeiten();
            break;

        case 'betriebsferien_admin_speichern':
            $controller = new BetriebsferienAdminController();
            $controller->speichern();
            break;

        case 'betriebsferien_admin_toggle':
            $controller = new BetriebsferienAdminController();
            $controller->toggleAktiv();
            break;

        case 'kurzarbeit_admin':
            $controller = new KurzarbeitAdminController();
            $controller->index();
            break;

        case 'kurzarbeit_admin_bearbeiten':
            $controller = new KurzarbeitAdminController();
            $controller->bearbeiten();
            break;

        case 'kurzarbeit_admin_speichern':
            $controller = new KurzarbeitAdminController();
            $controller->speichern();
            break;

        case 'kurzarbeit_admin_toggle':
            $controller = new KurzarbeitAdminController();
            $controller->toggleAktiv();
            break;

        case 'queue_admin':
            $controller = new QueueController();
            $controller->index();
            break;

        case 'zeit_rundungsregel_admin':
            $controller = new ZeitRundungsregelAdminController();
            $controller->index();
            break;

        case 'zeit_rundungsregel_admin_bearbeiten':
            $controller = new ZeitRundungsregelAdminController();
            $controller->bearbeiten();
            break;

        case 'konfiguration_admin':
            $controller = new KonfigurationController();
            $controller->index();
            break;

        case 'konfiguration_admin_bearbeiten':
            $controller = new KonfigurationController();
            $controller->bearbeiten();
            break;

        case 'urlaub_kontingent_admin':
            $controller = new UrlaubKontingentAdminController();
            $controller->index();
            break;

        case 'urlaub_kontingent_admin_bearbeiten':
            $controller = new UrlaubKontingentAdminController();
            $controller->bearbeiten();
            break;

        case 'urlaub_kontingent_admin_speichern':
            $controller = new UrlaubKontingentAdminController();
            $controller->speichern();
            break;

        case 'terminal_admin':
            $controller = new TerminalAdminController();
            $controller->index();
            break;

        case 'terminal_admin_bearbeiten':
            $controller = new TerminalAdminController();
            $controller->bearbeiten();
            break;

        case 'terminal_admin_speichern':
            $controller = new TerminalAdminController();
            $controller->speichern();
            break;

        case 'terminal_admin_toggle':
            $controller = new TerminalAdminController();
            $controller->toggleFlag();
            break;

        default:
            // Fallback: Wenn angemeldet, aufs Dashboard – sonst Login anzeigen.
            if ($auth->istAngemeldet()) {
                header('Location: ?seite=dashboard');
            } else {
                header('Location: ?seite=login');
            }
            exit;
    }
} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::error('Unbehandelter Fehler im Front-Controller', [
            'seite'     => isset($seite) ? $seite : null,
            'exception' => $e->getMessage(),
        ], null, null, 'frontend');
    }

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler</title></head><body>';
    echo '<h1>Interner Fehler</h1>';
    echo '<p>Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Administrator.</p>';
    echo '</body></html>';
}
