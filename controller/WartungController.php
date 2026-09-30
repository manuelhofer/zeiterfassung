<?php
declare(strict_types=1);

final class WartungController
{
    public function index(): void
    {
        $auth = AuthService::getInstanz();
        $darfBackup = $auth->istAngemeldet() && $auth->hatRecht('BACKUP_VERWALTEN');
        $darfUpdate = $auth->istAngemeldet() && $auth->hatRecht('UPDATE_VERWALTEN');
        if (!$darfBackup && !$darfUpdate) { http_response_code(403); echo 'Keine Berechtigung zur Wartung.'; return; }
        $_SESSION['wartung_status_bis'] = time() + 7200;
        $_SESSION['wartung_status_mitarbeiter'] = $auth->holeAngemeldeteMitarbeiterId();
        if (Helper::istPost()) {
            try {
                if (!Csrf::istGueltig('wartung')) { throw new RuntimeException('Die Formularsitzung ist abgelaufen. Bitte neu laden.'); }
                $aktion = Helper::leseString($_POST, 'aktion');
                if (($aktion === 'backup' && !$darfBackup) || (in_array($aktion, ['pruefen', 'update'], true) && !$darfUpdate)) {
                    http_response_code(403); echo 'Keine Berechtigung für diese Aktion.'; return;
                }
                WartungAuftrag::einreichen($aktion, Helper::leseString($_POST, 'commit'), (int)$auth->holeAngemeldeteMitarbeiterId());
                $_SESSION['wartung_flash'] = 'Auftrag vorgemerkt. Der Wartungsdienst übernimmt ihn automatisch; du kannst diese Seite schließen.';
            } catch (Throwable $e) { $_SESSION['wartung_flash'] = $e->getMessage(); }
            header('Location: ?seite=wartung', true, 303);
            return;
        }
        $flash = $_SESSION['wartung_flash'] ?? '';
        unset($_SESSION['wartung_flash']);
        $konfig = WartungSperre::konfig();
        $version = WartungAuftrag::lesen('version');
        $angebot = WartungAuftrag::lesen('angebot');
        $status = WartungAuftrag::lesen('status');
        $wartet = $konfig !== null && is_file($konfig['status_pfad'] . '/auftraege/auftrag.json');
        $belegt = $wartet || ($konfig !== null && is_file($konfig['status_pfad'] . '/aktiv.json'));
        $abgelaufen = $wartet && (WartungDateien::json($konfig['status_pfad'] . '/auftraege/auftrag.json')['gueltig_bis'] ?? 0) < time();
        if ($abgelaufen) { $wartet = false; $belegt = is_file($konfig['status_pfad'] . '/aktiv.json'); }
        $bereitschaft = WartungAnzeige::bereitschaft($konfig);
        $bereit = $bereitschaft['bereit'] && !$belegt;
        $geraete = [];
        try {
            $pdo = Database::getInstanz()->getVerbindung();
            $geraete = $pdo->query("SELECT t.name,UNIX_TIMESTAMP(g.gesehen)>=UNIX_TIMESTAMP()-60 AS bereit FROM terminal t LEFT JOIN wartung_geraet g ON g.terminal_id=t.id AND g.db_benutzer=t.db_benutzer WHERE t.aktiv=1 AND t.modus='terminal' ORDER BY t.name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { /* Die Bereitschaftsmeldung erklärt eine noch unvollständige Installation. */ }

        require __DIR__ . '/../views/wartung/index.php';
    }
}
