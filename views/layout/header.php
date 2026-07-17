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
$hatAuditLogAdminRecht        = false;
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
            $hatAuditLogAdminRecht         = $auth->hatRecht('KONFIGURATION_VERWALTEN') || $auth->hatRecht('ROLLEN_RECHTE_VERWALTEN') || $hatLegacyAdminRolle;

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
            $hatAuditLogAdminRecht         = $hatLegacyAdminRolle;
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

$navUrlaubAktiv = in_array($seite, [
    'urlaub_meine',
    'urlaub_genehmigung',
    'urlaub_verwaltung',
    'urlaub_kontingent_admin',
    'urlaub_kontingent_admin_bearbeiten',
    'betriebsferien_admin',
    'betriebsferien_admin_bearbeiten',
], true);

$navUebersichtenAktiv = in_array($seite, [
    'report_monat',
    'report_monat_pdf',
    'report_monat_export_all',
    'urlaub_jahresuebersicht',
    'urlaubsplanung',
], true);

$navMitarbeiterAktiv = in_array($seite, [
    'mitarbeiter_admin',
    'mitarbeiter_admin_bearbeiten',
    'mitarbeiter_stundenkonto',
], true);

$navRechteAktiv = in_array($seite, [
    'mitarbeiter_rechte',
    'rollen_admin',
    'rollen_admin_bearbeiten',
    'abteilung_admin',
    'abteilung_admin_bearbeiten',
], true);

$navVerwaltungAktiv = in_array($seite, [
    'maschine_admin',
    'maschine_admin_bearbeiten',
    'feiertag_admin',
    'feiertag_admin_bearbeiten',
    'zeit_rundungsregel_admin',
    'konfiguration_admin',
    'konfiguration_admin_bearbeiten',
    'queue_admin',
    'terminal_admin',
    'terminal_admin_bearbeiten',
    'audit_logs',
], true);

$hatRechteMenue = $hatMitarbeiterAdminRecht || $hatRollenAdminRecht || $hatAbteilungsAdminRecht;
$hatVerwaltungMenue = $hatMaschineAdminRecht
    || $hatFeiertagAdminRecht
    || $hatRundungsregelAdminRecht
    || $hatKonfigurationAdminRecht
    || $hatKrankzeitraumAdminRecht
    || $hatQueueAdminRecht
    || $hatTerminalAdminRecht
    || $hatAuditLogAdminRecht;

$navVerwaltungStartUrl = '?seite=dashboard';
if ($hatKonfigurationAdminRecht) {
    $navVerwaltungStartUrl = '?seite=konfiguration_admin';
} elseif ($hatMaschineAdminRecht) {
    $navVerwaltungStartUrl = '?seite=maschine_admin';
} elseif ($hatFeiertagAdminRecht) {
    $navVerwaltungStartUrl = '?seite=feiertag_admin';
} elseif ($hatRundungsregelAdminRecht) {
    $navVerwaltungStartUrl = '?seite=zeit_rundungsregel_admin';
} elseif ($hatKrankzeitraumAdminRecht) {
    $navVerwaltungStartUrl = '?seite=konfiguration_admin&tab=krankzeitraum';
} elseif ($hatQueueAdminRecht) {
    $navVerwaltungStartUrl = '?seite=queue_admin';
} elseif ($hatTerminalAdminRecht) {
    $navVerwaltungStartUrl = '?seite=terminal_admin';
} elseif ($hatAuditLogAdminRecht) {
    $navVerwaltungStartUrl = '?seite=audit_logs';
}
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
        .nav-menu {
            position: relative;
            display: inline-block;
            margin-right: 1rem;
        }
        .nav-menu > a {
            margin-right: 0;
        }
        .nav-menu.active > a {
            font-weight: bold;
            text-decoration: underline;
        }
        .nav-menu-items {
            display: none;
            position: absolute;
            left: 0;
            top: 100%;
            min-width: 14rem;
            background-color: #455a64;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
            z-index: 20;
        }
        .nav-menu:hover .nav-menu-items,
        .nav-menu:focus-within .nav-menu-items {
            display: block;
        }
        .nav-menu-items a {
            display: block;
            margin: 0;
            padding: 0.45rem 0.75rem;
            white-space: nowrap;
        }
        .nav-menu-items a:hover,
        .nav-menu-items a:focus {
            background-color: #546e7a;
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

        :root {
            --backend-bg: #f4f6f7;
            --backend-surface: #ffffff;
            --backend-line: #d9e0e4;
            --backend-line-soft: #edf1f3;
            --backend-text: #172126;
            --backend-muted: #5d6b73;
            --backend-primary: #255f85;
            --backend-primary-dark: #1d4f70;
            --backend-danger: #a82222;
            --backend-danger-bg: #fff1f1;
            --backend-success: #1f6f43;
            --backend-success-bg: #eef9f1;
            --backend-info-bg: #f3f8fb;
        }

        body {
            background-color: var(--backend-bg);
            color: var(--backend-text);
            font-size: 15px;
            line-height: 1.4;
        }

        main {
            padding: 1.35rem 1.35rem 2rem 1.35rem;
        }

        h2 {
            margin: 0 0 0.85rem 0;
            font-size: 1.35rem;
        }

        h3 {
            margin: 1rem 0 0.6rem 0;
            font-size: 1.08rem;
        }

        h4 {
            margin: 1.15rem 0 0.55rem 0;
            font-size: 1rem;
        }

        p {
            margin: 0.55rem 0;
        }

        section {
            margin-bottom: 1.35rem;
        }

        a {
            color: #1f5f85;
        }

        a:hover,
        a:focus {
            color: #143f59;
        }

        label {
            font-weight: 600;
        }

        input,
        select,
        textarea,
        button {
            font: inherit;
        }

        input[type="text"],
        input[type="search"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="date"],
        input[type="time"],
        select,
        textarea {
            border: 1px solid #b9c5cb;
            border-radius: 4px;
            padding: 0.34rem 0.45rem;
            background: #ffffff;
            color: var(--backend-text);
            box-sizing: border-box;
        }

        input[type="checkbox"],
        input[type="radio"] {
            transform: translateY(1px);
        }

        textarea {
            max-width: 100%;
        }

        button,
        .button-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            border: 1px solid #1f5f85;
            border-radius: 4px;
            background: var(--backend-primary);
            color: #ffffff;
            padding: 0.38rem 0.68rem;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.2;
            min-height: 2rem;
        }

        button:hover,
        button:focus,
        .button-link:hover,
        .button-link:focus {
            background: var(--backend-primary-dark);
            color: #ffffff;
            text-decoration: none;
        }

        button[name="aktion"][value="ablehnen"],
        button.danger,
        .button-link.danger {
            border-color: #8e1d1d;
            background: var(--backend-danger);
        }

        button[name="aktion"][value="ablehnen"]:hover,
        button[name="aktion"][value="ablehnen"]:focus,
        button.danger:hover,
        button.danger:focus,
        .button-link.danger:hover,
        .button-link.danger:focus {
            background: #821818;
        }

        table {
            border: 1px solid var(--backend-line);
            font-size: 0.93rem;
        }

        table th,
        table td {
            border: 1px solid var(--backend-line);
            padding: 0.42rem 0.5rem;
            vertical-align: top;
        }

        table th {
            background-color: #e8eef1;
            color: #111b20;
            font-weight: 700;
        }

        tbody tr:nth-child(even) td {
            background-color: #fbfcfd;
        }

        tbody tr:hover td {
            background-color: #f2f7fa;
        }

        fieldset {
            border: 1px solid var(--backend-line);
            border-radius: 6px;
            padding: 0.95rem 1rem 1rem 1rem;
            margin: 0 0 0.85rem 0;
            background: #fbfcfd;
        }

        legend {
            font-weight: 700;
            padding: 0 0.35rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .page-header h2 {
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: var(--backend-muted);
            margin: 0;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.65rem;
            margin: 0.65rem 0 1rem 0;
        }

        .toolbar label {
            display: inline-flex;
            flex-direction: column;
            gap: 0.22rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.7rem;
            margin: 0.5rem 0;
        }

        .form-row label {
            display: inline-flex;
            flex-direction: column;
            gap: 0.22rem;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            align-items: center;
            margin-top: 0.8rem;
        }

        .table-wrap {
            overflow-x: auto;
            max-width: 100%;
            margin: 0.65rem 0 1rem 0;
        }

        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px;
        }

        .table-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }

        .inline-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: flex-start;
        }

        .link-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            list-style: none;
            padding: 0;
            margin: 0.75rem 0 0 0;
        }

        .link-list a {
            display: inline-flex;
            align-items: center;
            min-height: 1.9rem;
            border: 1px solid var(--backend-line);
            border-radius: 4px;
            padding: 0.25rem 0.55rem;
            background: #ffffff;
            text-decoration: none;
        }

        .link-list a:hover,
        .link-list a:focus {
            border-color: #9eb8c6;
            background: #f7fbfd;
            text-decoration: none;
        }

        .muted {
            color: var(--backend-muted);
        }

        .numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        p.error,
        div.error,
        p.success,
        div.success,
        p.notice,
        div.notice {
            border-radius: 6px;
            padding: 0.65rem 0.8rem;
            border: 1px solid var(--backend-line);
            margin: 0.75rem 0;
        }

        p.error,
        div.error {
            background: var(--backend-danger-bg);
            border-color: #e2a5a5;
            color: var(--backend-danger);
        }

        p.success,
        div.success {
            background: var(--backend-success-bg);
            border-color: #9ac8aa;
            color: var(--backend-success);
        }

        p.notice,
        div.notice {
            background: var(--backend-info-bg);
            color: #314852;
        }

        .admin-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.8rem;
            margin: 0.75rem 0 1rem 0;
        }

        .admin-card {
            border: 1px solid var(--backend-line);
            border-radius: 6px;
            background: var(--backend-surface);
            padding: 0.85rem 0.95rem;
        }

        .admin-card strong {
            display: block;
            margin-bottom: 0.35rem;
        }

        .admin-card p {
            color: var(--backend-muted);
            margin: 0.25rem 0 0 0;
        }

        .admin-card .card-link {
            display: inline-block;
            margin-top: 0.45rem;
            font-weight: 700;
        }

        .permission-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            align-items: start;
            margin: 0.75rem 0 1rem 0;
        }

        .permission-card {
            border: 1px solid var(--backend-line);
            border-radius: 8px;
            background: var(--backend-surface);
            padding: 0.95rem 1rem;
        }

        .permission-card h3 {
            margin-top: 0;
        }

        .role-option-list {
            display: grid;
            gap: 0.45rem;
            margin: 0.6rem 0;
        }

        .role-option {
            display: block;
            border: 1px solid var(--backend-line-soft);
            border-radius: 6px;
            background: #fbfcfd;
            padding: 0.55rem 0.65rem;
        }

        .role-option small {
            display: block;
            color: var(--backend-muted);
            margin-left: 1.45rem;
        }

        .compact-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, max-content));
            gap: 0.65rem;
            align-items: end;
            margin-top: 0.65rem;
        }

        .warning-panel {
            border: 2px solid #d64a4a;
            border-radius: 6px;
            padding: 0.9rem 1rem;
            background: #fff7f7;
        }

        .warning-panel > strong {
            color: var(--backend-danger);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.12rem 0.48rem;
            font-size: 0.82rem;
            font-weight: 700;
            border: 1px solid var(--backend-line);
            background: #ffffff;
            white-space: nowrap;
        }

        .status-pill.ok {
            color: var(--backend-success);
            border-color: #9ac8aa;
            background: var(--backend-success-bg);
        }

        .status-pill.error {
            color: var(--backend-danger);
            border-color: #e2a5a5;
            background: var(--backend-danger-bg);
        }

        @media (max-width: 760px) {
            header {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.35rem;
            }

            nav {
                padding: 0.45rem 0.85rem;
            }

            nav a,
            .nav-menu {
                margin-right: 0.7rem;
                margin-bottom: 0.2rem;
            }

            main {
                padding: 1rem 0.75rem 1.5rem 0.75rem;
            }

            .toolbar,
            .form-row {
                align-items: stretch;
            }
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
    <span class="nav-menu <?php echo $navUrlaubAktiv ? 'active' : ''; ?>">
        <a href="?seite=urlaub_meine">Urlaub</a>
        <span class="nav-menu-items">
            <a href="?seite=urlaub_meine">Mein Urlaub</a>
            <?php if ($hatUrlaubGenehmigungRecht): ?>
                <a href="?seite=urlaub_genehmigung">Urlaub genehmigen</a>
                <a href="?seite=urlaub_verwaltung">Urlaubsverwaltung</a>
            <?php endif; ?>
            <?php if ($hatUrlaubKontingentAdminRecht): ?>
                <a href="?seite=urlaub_kontingent_admin">Urlaub-Kontingent</a>
            <?php endif; ?>
            <?php if ($hatBetriebsferienAdminRecht): ?>
                <a href="?seite=betriebsferien_admin">Betriebsferien</a>
            <?php endif; ?>
        </span>
    </span>
    <span class="nav-menu <?php echo $navUebersichtenAktiv ? 'active' : ''; ?>">
        <a href="?seite=report_monat">&Uuml;bersichten</a>
        <span class="nav-menu-items">
            <a href="?seite=report_monat">Monats&uuml;bersicht</a>
            <?php if ($hatUrlaubGenehmigungRecht): ?>
                <a href="?seite=urlaub_jahresuebersicht">Urlaub Jahres&uuml;bersicht</a>
            <?php endif; ?>
        </span>
    </span>
    <a href="?seite=auftrag" class="<?php echo in_array($seite, ['auftrag','auftrag_detail','auftragszeit_bearbeiten'], true) ? 'active' : ''; ?>">Auftr&auml;ge</a>
    <?php if ($hatMitarbeiterAdminRecht): ?>
        <span class="nav-menu <?php echo $navMitarbeiterAktiv ? 'active' : ''; ?>">
            <a href="?seite=mitarbeiter_admin">Mitarbeiter</a>
            <span class="nav-menu-items">
                <a href="?seite=mitarbeiter_admin">Mitarbeiter&uuml;bersicht</a>
                <a href="?seite=mitarbeiter_admin_bearbeiten">Mitarbeiter anlegen</a>
                <a href="?seite=mitarbeiter_stundenkonto">Stundenkonto</a>
            </span>
        </span>
    <?php endif; ?>
    <?php if ($hatRechteMenue): ?>
        <span class="nav-menu <?php echo $navRechteAktiv ? 'active' : ''; ?>">
            <a href="<?php echo $hatMitarbeiterAdminRecht ? '?seite=mitarbeiter_rechte' : ($hatRollenAdminRecht ? '?seite=rollen_admin' : '?seite=abteilung_admin'); ?>">Rechte</a>
            <span class="nav-menu-items">
                <?php if ($hatMitarbeiterAdminRecht): ?>
                    <a href="?seite=mitarbeiter_rechte">Mitarbeiter-Rollen &amp; Rechte</a>
                <?php endif; ?>
                <?php if ($hatRollenAdminRecht): ?>
                    <a href="?seite=rollen_admin">Rollen verwalten</a>
                <?php endif; ?>
                <?php if ($hatAbteilungsAdminRecht): ?>
                    <a href="?seite=abteilung_admin">Abteilungen</a>
                <?php endif; ?>
            </span>
        </span>
    <?php endif; ?>
    <?php if ($hatVerwaltungMenue): ?>
        <span class="nav-menu <?php echo $navVerwaltungAktiv ? 'active' : ''; ?>">
            <a href="<?php echo htmlspecialchars($navVerwaltungStartUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Verwaltung</a>
            <span class="nav-menu-items">
                <?php if ($hatMaschineAdminRecht): ?>
                    <a href="?seite=maschine_admin">Maschinen</a>
                <?php endif; ?>
                <?php if ($hatFeiertagAdminRecht): ?>
                    <a href="?seite=feiertag_admin">Feiertage</a>
                <?php endif; ?>
                <?php if ($hatRundungsregelAdminRecht): ?>
                    <a href="?seite=zeit_rundungsregel_admin">Rundungsregeln</a>
                <?php endif; ?>
                <?php if ($hatKonfigurationAdminRecht): ?>
                    <a href="?seite=konfiguration_admin">Konfiguration</a>
                <?php endif; ?>
                <?php if ($hatKrankzeitraumAdminRecht): ?>
                    <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LF/KK)</a>
                <?php endif; ?>
                <?php if ($hatQueueAdminRecht): ?>
                    <a href="?seite=queue_admin">Offline-Queue</a>
                <?php endif; ?>
                <?php if ($hatTerminalAdminRecht): ?>
                    <a href="?seite=terminal_admin">Terminals</a>
                <?php endif; ?>
                <?php if ($hatAuditLogAdminRecht): ?>
                    <a href="?seite=audit_logs">Logs</a>
                <?php endif; ?>
            </span>
        </span>
    <?php endif; ?>
    <?php if (false): ?>
    <a href="?seite=urlaub_meine" class="<?php echo $seite === 'urlaub_meine' ? 'active' : ''; ?>">Mein Urlaub</a>
    <?php if ($hatUrlaubGenehmigungRecht): ?>
        <a href="?seite=urlaub_genehmigung" class="<?php echo $seite === 'urlaub_genehmigung' ? 'active' : ''; ?>">Urlaub genehmigen</a>
        <a href="?seite=urlaub_jahresuebersicht" class="<?php echo in_array($seite, ['urlaub_jahresuebersicht', 'urlaubsplanung'], true) ? 'active' : ''; ?>">Urlaub Jahresübersicht</a>
    <?php endif; ?>
    <a href="?seite=report_monat" class="<?php echo $seite === 'report_monat' ? 'active' : ''; ?>">Monatsübersicht</a>
    <a href="?seite=auftrag" class="<?php echo in_array($seite, ['auftrag','auftrag_detail','auftragszeit_bearbeiten'], true) ? 'active' : ''; ?>">Aufträge</a>
    <?php if ($hatMitarbeiterAdminRecht): ?>
        <span class="nav-menu <?php echo in_array($seite, ['mitarbeiter_admin','mitarbeiter_admin_bearbeiten','mitarbeiter_stundenkonto','mitarbeiter_rechte'], true) ? 'active' : ''; ?>">
            <a href="?seite=mitarbeiter_admin">Mitarbeiter</a>
            <span class="nav-menu-items">
                <a href="?seite=mitarbeiter_admin">Mitarbeiteruebersicht</a>
                <a href="?seite=mitarbeiter_admin_bearbeiten">Mitarbeiter anlegen</a>
                <a href="?seite=mitarbeiter_rechte">Rollen &amp; Rechte</a>
                <a href="?seite=mitarbeiter_stundenkonto">Stundenkonto</a>
            </span>
        </span>
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
    <?php endif; ?>
    <a href="?seite=logout" style="float:right;">Logout</a>
</nav>
<main>
