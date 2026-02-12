<?php
declare(strict_types=1);
/**
 * Basis-Layout: Header
 *
 * Wird von allen Backend-Views eingebunden.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$aktuellerBenutzerName        = 'Gast';
$aktuellerBenutzerRollenText  = '';
$angemeldeterMitarbeiterId = 0;
$hatMitarbeiterAdminRecht     = false;
$hatAbteilungsAdminRecht      = false;
$hatMaschineAdminRecht        = false;
$hatRollenAdminRecht          = false;
$hatFeiertagAdminRecht        = false;
$hatRundungsregelAdminRecht   = false;
$hatBetriebsferienAdminRecht  = false;
$hatQueueAdminRecht           = false;
$hatTerminalAdminRecht         = false;
$hatKonfigurationAdminRecht   = false;
$hatKrankzeitraumAdminRecht   = false;
$hatUrlaubKontingentAdminRecht = false;
$hatUrlaubGenehmigungRecht  = false;

if (class_exists('AuthService')) {
    $auth = AuthService::getInstanz();
    if ($auth->istAngemeldet()) {
        $angemeldeterMitarbeiter = $auth->holeAngemeldetenMitarbeiter();
        if ($angemeldeterMitarbeiter !== null) {
            $angemeldeterMitarbeiterId = (int)($angemeldeterMitarbeiter['id'] ?? 0);
            $vorname  = trim((string)($angemeldeterMitarbeiter['vorname'] ?? ''));
            $nachname = trim((string)($angemeldeterMitarbeiter['nachname'] ?? ''));
            $tmp      = trim($vorname . ' ' . $nachname);
            if ($tmp !== '') {
                $aktuellerBenutzerName = $tmp;
            }
        }

        // Rollen-Text für die Anzeige im Header vorbereiten
        $rollenNamen = [];
        if (method_exists($auth, 'holeAngemeldeteRollenNamen')) {
            $rollenNamen = $auth->holeAngemeldeteRollenNamen();
        }

        if (is_array($rollenNamen) && count($rollenNamen) > 0) {
            $aktuellerBenutzerRollenText = ' (Rollen: ' . implode(', ', $rollenNamen) . ')';
        }

        // Admin-Rechte für Menüpunkte bestimmen (Rechte-basiert, mit Legacy-Fallback auf Rollen)
        $hatLegacyAdminRolle = false;
        if (method_exists($auth, 'hatRolle')) {
            $hatLegacyAdminRolle = (
                $auth->hatRolle('Chef')
                || $auth->hatRolle('Personalbüro')
                || $auth->hatRolle('Personalbuero')
            );
        }

        if (method_exists($auth, 'hatRecht')) {
            $hatMitarbeiterAdminRecht      = $auth->hatRecht('MITARBEITER_VERWALTEN') || $hatLegacyAdminRolle;
            $hatAbteilungsAdminRecht       = $auth->hatRecht('ABTEILUNG_VERWALTEN') || $hatLegacyAdminRolle;
            $hatMaschineAdminRecht         = $auth->hatRecht('MASCHINEN_VERWALTEN') || $hatLegacyAdminRolle;
            $hatRollenAdminRecht           = $auth->hatRecht('ROLLEN_RECHTE_VERWALTEN') || $hatLegacyAdminRolle;
            $hatFeiertagAdminRecht         = $auth->hatRecht('FEIERTAGE_VERWALTEN') || $hatLegacyAdminRolle;
            $hatBetriebsferienAdminRecht   = $auth->hatRecht('BETRIEBSFERIEN_VERWALTEN') || $hatLegacyAdminRolle;
            $hatQueueAdminRecht            = $auth->hatRecht('QUEUE_VERWALTEN') || $hatLegacyAdminRolle;
            $hatTerminalAdminRecht         = $auth->hatRecht('TERMINAL_VERWALTEN') || $hatLegacyAdminRolle;

            // Diese Rechte-Codes sind (noch) nicht überall geseedet – Legacy-Fallback bleibt aktiv.
            $hatRundungsregelAdminRecht    = $auth->hatRecht('ZEIT_RUNDUNGSREGELN_VERWALTEN') || $hatLegacyAdminRolle;
            $hatKonfigurationAdminRecht    = $auth->hatRecht('KONFIGURATION_VERWALTEN') || $hatLegacyAdminRolle;
            $hatUrlaubKontingentAdminRecht = $auth->hatRecht('URLAUB_KONTINGENT_VERWALTEN') || $hatLegacyAdminRolle;
            $hatKrankzeitraumAdminRecht   = $auth->hatRecht('KRANKZEITRAUM_VERWALTEN') || $hatKonfigurationAdminRecht || $hatLegacyAdminRolle;
        } else {
            // Legacy: Rollen-Mapping (vor Rechteverwaltung)
            $hatMitarbeiterAdminRecht      = $hatLegacyAdminRolle;
            $hatAbteilungsAdminRecht       = $hatLegacyAdminRolle;
            $hatMaschineAdminRecht         = $hatLegacyAdminRolle;
            $hatRollenAdminRecht           = $hatLegacyAdminRolle;
            $hatFeiertagAdminRecht         = $hatLegacyAdminRolle;
            $hatRundungsregelAdminRecht    = $hatLegacyAdminRolle;
            $hatBetriebsferienAdminRecht   = $hatLegacyAdminRolle;
            $hatQueueAdminRecht            = $hatLegacyAdminRolle;
            $hatTerminalAdminRecht         = $hatLegacyAdminRolle;
            $hatKonfigurationAdminRecht    = $hatLegacyAdminRolle;
            $hatUrlaubKontingentAdminRecht = $hatLegacyAdminRolle;
            $hatKrankzeitraumAdminRecht   = $hatKonfigurationAdminRecht;
        }
    }

	    // Recht: Urlaub genehmigen (Rechte-basiert; Bereich optional via mitarbeiter_genehmiger)
	    if (method_exists($auth, 'hatRecht')) {
	        $hatUrlaubGenehmigungRecht = (
	            $auth->hatRecht('URLAUB_GENEHMIGEN_ALLE')
	            || $auth->hatRecht('URLAUB_GENEHMIGEN_SELF')
	        );

	        if (
	            !$hatUrlaubGenehmigungRecht
	            && $auth->hatRecht('URLAUB_GENEHMIGEN')
	            && class_exists('Database')
	            && $angemeldeterMitarbeiterId > 0
	        ) {
	            try {
	                $db = Database::getInstanz();
	                $row = $db->fetchEine(
	                    'SELECT 1 FROM mitarbeiter_genehmiger WHERE genehmiger_mitarbeiter_id = :gid LIMIT 1',
	                    ['gid' => $angemeldeterMitarbeiterId]
	                );
	                if ($row !== null) {
	                    $hatUrlaubGenehmigungRecht = true;
	                }
	            } catch (\Throwable $e) {
	                // Ignorieren
	            }
	        }
	    } else {
	        // Fallback (Legacy): Chef/Personalbüro oder als Genehmiger eingetragen
	        if (method_exists($auth, 'hatRolle')) {
	            $hatUrlaubGenehmigungRecht = $auth->hatRolle('Chef') || $auth->hatRolle('Personalbüro') || $auth->hatRolle('Personalbuero');
	        }
	        if (!$hatUrlaubGenehmigungRecht && class_exists('Database') && $angemeldeterMitarbeiterId > 0) {
	            try {
	                $db = Database::getInstanz();
	                $row = $db->fetchEine(
	                    'SELECT 1 FROM mitarbeiter_genehmiger WHERE genehmiger_mitarbeiter_id = :gid LIMIT 1',
	                    ['gid' => $angemeldeterMitarbeiterId]
	                );
	                if ($row !== null) {
	                    $hatUrlaubGenehmigungRecht = true;
	                }
	            } catch (\Throwable $e) {
	                // Ignorieren
	            }
	        }
	    }
}

$seite = isset($_GET['seite']) ? (string)$_GET['seite'] : 'start';
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : '';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Zeiterfassung</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #263238;
            color: #ffffff;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 {
            margin: 0;
            font-size: 1.1rem;
        }
        header .user-info {
            font-size: 0.9rem;
        }
        nav {
            background-color: #37474f;
            color: #ffffff;
            padding: 0.25rem 1.25rem;
        }
        nav a {
            color: #ffffff;
            text-decoration: none;
            margin-right: 1rem;
            font-size: 0.9rem;
        }
        nav a.active {
            font-weight: bold;
            text-decoration: underline;
        }
        main {
            padding: 1.5rem 1.25rem 2rem 1.25rem;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 100%;
            background-color: #ffffff;
        }
        table th, table td {
            padding: 0.4rem 0.5rem;
            border: 1px solid #ddd;
        }
        table th {
            background-color: #eceff1;
            text-align: left;
        }
        section {
            margin-bottom: 1.5rem;
        }

        .genehmiger-zeile {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            margin-bottom: 0.5rem;
        }

        .genehmiger-zeile-haupt {
            border-left: 4px solid #1976d2;
            padding-left: 0.5rem;
            background-color: #e3f2fd;
        }

        .genehmiger-zeile small {
            display: block;
            color: #555555;
        }
    </style>
</head>
<body>
<header>
    <h1>Zeiterfassung &amp; Management</h1>
    <div class="user-info">
        Angemeldet als: <?php echo htmlspecialchars($aktuellerBenutzerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $aktuellerBenutzerRollenText; ?>
    </div>
</header>
<nav>
    <a href="?seite=dashboard" class="<?php echo $seite === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
    <a href="?seite=zeit_heute" class="<?php echo $seite === 'zeit_heute' ? 'active' : ''; ?>">Heutige Zeiten</a>
    <a href="?seite=urlaub_meine" class="<?php echo $seite === 'urlaub_meine' ? 'active' : ''; ?>">Mein Urlaub</a>
    <?php if ($hatUrlaubGenehmigungRecht): ?>
        <a href="?seite=urlaub_genehmigung" class="<?php echo $seite === 'urlaub_genehmigung' ? 'active' : ''; ?>">Urlaub genehmigen</a>
    <?php endif; ?>
    <a href="?seite=report_monat" class="<?php echo $seite === 'report_monat' ? 'active' : ''; ?>">Monatsübersicht</a>
    <a href="?seite=auftrag" class="<?php echo in_array($seite, ['auftrag','auftrag_detail','auftragszeit_bearbeiten'], true) ? 'active' : ''; ?>">Aufträge</a>
    <?php if ($hatMitarbeiterAdminRecht): ?>
        <a href="?seite=mitarbeiter_admin" class="<?php echo $seite === 'mitarbeiter_admin' ? 'active' : ''; ?>">Mitarbeiter</a>
    <?php endif; ?>
    <?php if ($hatAbteilungsAdminRecht): ?>
        <a href="?seite=abteilung_admin" class="<?php echo $seite === 'abteilung_admin' ? 'active' : ''; ?>">Abteilungen</a>
    <?php endif; ?>
    <?php if ($hatMaschineAdminRecht): ?>
        <a href="?seite=maschine_admin" class="<?php echo $seite === 'maschine_admin' ? 'active' : ''; ?>">Maschinen</a>
    <?php endif; ?>
    <?php if ($hatRollenAdminRecht): ?>
        <a href="?seite=rollen_admin" class="<?php echo $seite === 'rollen_admin' ? 'active' : ''; ?>">Rollen</a>
    <?php endif; ?>
    <?php if ($hatFeiertagAdminRecht): ?>
        <a href="?seite=feiertag_admin" class="<?php echo $seite === 'feiertag_admin' ? 'active' : ''; ?>">Feiertage</a>
    <?php endif; ?>
    <?php if ($hatRundungsregelAdminRecht): ?>
        <a href="?seite=zeit_rundungsregel_admin" class="<?php echo $seite === 'zeit_rundungsregel_admin' ? 'active' : ''; ?>">Rundungsregeln</a>
    <?php endif; ?>
    <?php if ($hatKonfigurationAdminRecht): ?>
        <a href="?seite=konfiguration_admin" class="<?php echo in_array($seite, ['konfiguration_admin','konfiguration_admin_bearbeiten'], true) ? 'active' : ''; ?>">Konfiguration</a>
    <?php endif; ?>
    <?php if ($hatKrankzeitraumAdminRecht): ?>
        <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum" class="<?php echo ($seite === 'konfiguration_admin' && $tab === 'krankzeitraum') ? 'active' : ''; ?>">Krank (LF/KK)</a>
    <?php endif; ?>
    <?php if ($hatBetriebsferienAdminRecht): ?>
        <a href="?seite=betriebsferien_admin" class="<?php echo in_array($seite, ['betriebsferien_admin','betriebsferien_admin_bearbeiten'], true) ? 'active' : ''; ?>">Betriebsferien</a>
    <?php endif; ?>
    <?php if ($hatUrlaubKontingentAdminRecht): ?>
        <a href="?seite=urlaub_kontingent_admin" class="<?php echo in_array($seite, ['urlaub_kontingent_admin','urlaub_kontingent_admin_bearbeiten'], true) ? 'active' : ''; ?>">Urlaub-Kontingent</a>
    <?php endif; ?>
    <?php if ($hatQueueAdminRecht): ?>
        <a href="?seite=queue_admin" class="<?php echo $seite === 'queue_admin' ? 'active' : ''; ?>">Offline-Queue</a>
    <?php endif; ?>
    <?php if ($hatTerminalAdminRecht): ?>
        <a href="?seite=terminal_admin" class="<?php echo $seite === 'terminal_admin' ? 'active' : ''; ?>">Terminals</a>
    <?php endif; ?>
    <a href="?seite=logout" style="float:right;">Logout</a>
</nav>
<main>
