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
        $bereit = $version !== null && !$belegt;
        require __DIR__ . '/../views/wartung/index.php';
    }
}
