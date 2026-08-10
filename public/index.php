<?php
declare(strict_types=1);

// Front-Controller / Einstiegspunkt.

require __DIR__ . '/../core/Autoloader.php';

$konfig = Start::los();

// ---------------------------------------------------------------------------
// Auf einem Terminal gibt es hier nichts zu holen (T-103)
// ---------------------------------------------------------------------------
// Der Webserver eines Terminals zeigt auf `public/` - damit liegt neben
// `terminal.php` auch dieser Front-Controller im Zugriff, und ein Hallengerät
// lieferte bis P-2026-08-09-19 die Anmeldemaske des Backends aus.
//
// Was das schuetzt, und was ausdrücklich nicht: Der Datenbankbenutzer des
// Geräts darf Mitarbeiterstammdaten, Zeitbuchungen und Stundenkonten **lesen**
// (siehe Rechteliste in docs/spezifikation_terminal_installation.md). Wer das
// Gerät aufschraubt, liest die Zugangsdaten aus `config.local.php` und kommt
// an diese Daten - daran ändert diese Sperre nichts. Sie nimmt lediglich die
// Backend-Oberflaeche aus dem Weg, die auf einem Hallengerät nichts zu suchen
// hat. Der Schutz der Daten liegt bei der Rechteliste, nicht hier.
//
// Bewusst **ohne Ausnahme für den Kopplungs-Endpunkt**: Der läuft auf dem
// Backend. Ein Terminal, das ihn selbst anboete, würde Datenbankbenutzer
// verteilen - genau das, was die Kopplung verhindern soll.
//
// Rückweg für die Wartung: `installation_typ` in `config/config.local.php`
// auf 'backend' setzen. Das braucht Zugriff auf die Datei, und das ist die
// richtige Huerde.
$istTerminal = ($konfig['app']['installation_typ'] ?? 'backend') === 'terminal';

// Zweiter Fall, sonst bliebe eine Luecke von Tagen: Zwischen dem Aufsetzen und
// dem Koppeln gibt es noch keine `config.local.php`, also gilt der Standard
// 'backend' - und ein Gerät, das schon in der Halle hängt, zeigte bis dahin
// die Anmeldemaske. Woran ein solches Gerät zu erkennen ist:
// `config/geraet.local.php` legt ausschließlich install_terminal.sh an.
//
// Nur wenn `config.local.php` fehlt. Ist sie da und nennt 'backend', ist das
// eine ausdrückliche Entscheidung und gewinnt.
if (!$istTerminal
    && !is_file(__DIR__ . '/../config/config.local.php')
    && is_file(__DIR__ . '/../config/geraet.local.php')
) {
    $istTerminal = true;
}

if ($istTerminal) {
    // Weiterleiten statt 404: Auf einem Kiosk ist die Terminal-Oberflaeche das,
    // was der Aufrufer gemeint hat - bei einem noch nicht gekoppelten Gerät
    // ist das die Einrichtungsseite. Ein Fehlerbild wäre hier nur im Weg.
    header('Location: terminal.php', true, 302);
    header('Cache-Control: no-store');
    exit;
}

/**
 * Liest Jahr und Monat aus dem Request, wendet die Stepper an und begrenzt sie.
 *
 * Fasst zusammen, was für Monatsübersicht, Monats-PDF und Sammelexport
 * dreimal zeichengleich dastand.
 *
 * @return array{0:int,1:int}
 */
function holeJahrMonatAusRequest(): array
{
    $jahr  = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
    $monat = isset($_GET['monat']) ? (int)$_GET['monat'] : (int)date('n');

    [$jahr, $monat] = verarbeite_jahr_monat_aktion($jahr, $monat);

    return normalize_jahr_monat($jahr, $monat);
}

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

/**
 * Begrenzt Jahr und Monat auf sinnvolle Werte.
 *
 * Hintergrund (T-069 Teil 2a/2b): Durch Browser-Back/Forward, Copy&Paste oder
 * Tippfehler entstehen Werte wie `monat=0` oder `monat=13`. Die führen in
 * Reports und PDFs zu DateTime-Fehlern und je nach PHP-Einstellung zu
 * Warnungen im Log. Deshalb wird defensiv begrenzt statt hart abgebrochen.
 */
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

    // Zugang: **alles ist geschützt**, ausser den wenigen ausdrücklich offenen
    // Routen. Vorher stand hier eine Liste aller geschützten Seiten – also eine
    // zweite Fassung des `switch` weiter unten, von Hand gepflegt. Beide waren
    // zuletzt deckungsgleich, aber die nächste neue Route wäre in einer der
    // beiden vergessen worden. Und der Fehler geht in die gefährliche Richtung:
    // Eine vergessene Zeile in der Liste macht die Seite **offen**.
    //
    // Umgekehrt kann nichts passieren: Wer eine Route ergänzt, bekommt sie
    // geschützt, ohne daran zu denken. Nur wer sie ausdrücklich öffnen will,
    // muss hier eine Zeile schreiben – und dann denkt er auch darüber nach.
    $offeneSeiten = [
        'login',
        'logout',
        // Kopplungs-Endpunkt für Terminals: Ein frisch installiertes Gerät hat
        // noch keinen Benutzer – der Kopplungscode ist der Nachweis
        // (siehe TerminalKopplungController).
        'terminal_kopplung',
    ];

    if (!in_array($seite, $offeneSeiten, true) && !$auth->istAngemeldet()) {
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

        // Kopplungs-Endpunkt für Terminals. Bewusst **ohne** Anmeldung: Ein
        // frisch installiertes Gerät hat noch keinen Benutzer - der
        // Kopplungscode ist der Nachweis (siehe TerminalKopplungController).
        case 'terminal_kopplung':
            $controller = new TerminalKopplungController();
            $controller->koppeln();
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

        case 'urlaub_verwaltung':
            $controller = new UrlaubController();
            $controller->verwaltung();
            break;

        case 'urlaub_jahresuebersicht':
        // Alt-Link: nirgends mehr verlinkt, bleibt für Lesezeichen aus dem Betrieb.
        case 'urlaubsplanung':
            $controller = new UrlaubJahresuebersichtController();
            $controller->index();
            break;

        case 'report_monat':
            [$jahr, $monat] = holeJahrMonatAusRequest();

            $controller = new ReportController();
            $controller->monatsuebersicht($jahr, $monat);
            break;

        case 'report_monat_pdf':
            [$jahr, $monat] = holeJahrMonatAusRequest();

            $controller = new ReportController();
            $controller->monatsPdf($jahr, $monat);
            break;

        case 'report_monat_export_all':
            [$jahr, $monat] = holeJahrMonatAusRequest();

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

        case 'auftrag_neu':
            $controller = new AuftragController();
            $controller->neu();
            break;

        case 'auftrag_bearbeiten':
            $controller = new AuftragController();
            $controller->bearbeiten();
            break;

        case 'auftrag_speichern':
            $controller = new AuftragController();
            $controller->speichern();
            break;

        case 'auftrag_aktiv_setzen':
            $controller = new AuftragController();
            $controller->aktivSetzen();
            break;

        case 'arbeitsschritt_katalog':
            $controller = new ArbeitsschrittKatalogController();
            $controller->index();
            break;

        case 'arbeitsschritt_katalog_neu':
            $controller = new ArbeitsschrittKatalogController();
            $controller->neu();
            break;

        case 'arbeitsschritt_katalog_bearbeiten':
            $controller = new ArbeitsschrittKatalogController();
            $controller->bearbeiten();
            break;

        case 'arbeitsschritt_katalog_speichern':
            $controller = new ArbeitsschrittKatalogController();
            $controller->speichern();
            break;

        case 'arbeitsschritt_katalog_blatt':
            $controller = new ArbeitsschrittKatalogController();
            $controller->blatt();
            break;

        case 'auftrag_laufkarte':
            $controller = new AuftragController();
            $controller->laufkarte();
            break;

        case 'auftrag_schritt_bearbeiten':
            $controller = new AuftragController();
            $controller->schrittBearbeiten();
            break;

        case 'auftrag_schritte_aus_katalog':
            $controller = new AuftragController();
            $controller->schritteAusKatalog();
            break;

        case 'auftrag_schritt_speichern':
            $controller = new AuftragController();
            $controller->schrittSpeichern();
            break;

        case 'auftragszeit_bearbeiten':
            $controller = new AuftragController();
            $controller->auftragszeitBearbeiten();
            break;

        case 'mitarbeiter_admin':
            $controller = new MitarbeiterAdminController();
            $controller->index();
            break;

        case 'mitarbeiter_stundenkonto':
            $controller = new MitarbeiterAdminController();
            $controller->stundenkonto();
            break;

        case 'mitarbeiter_rechte':
            $_GET['rechte_modus'] = '1';
            $controller = new MitarbeiterAdminController();
            $controller->bearbeiten();
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

        case 'audit_logs':
            $controller = new AuditLogController();
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

        case 'terminal_admin_kopplung':
            $controller = new TerminalAdminController();
            $controller->kopplung();
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

        case 'terminal_admin_entkoppeln':
            $controller = new TerminalAdminController();
            $controller->entkoppeln();
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
    Logger::error('Unbehandelter Fehler im Front-Controller', [
        'seite'     => isset($seite) ? $seite : null,
        'exception' => $e->getMessage(),
    ], null, null, 'frontend');

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler</title></head><body>';
    echo '<h1>Interner Fehler</h1>';
    echo '<p>Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Administrator.</p>';
    echo '</body></html>';
}
