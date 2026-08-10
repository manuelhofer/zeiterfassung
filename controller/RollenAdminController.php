<?php
declare(strict_types=1);

/**
 * RollenAdminController
 *
 * Backend-Controller für das Verwalten von Rollen.
 */
class RollenAdminController
{
    /** Bereichsname fuer `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'rollen_admin';

    private AuthService $authService;
    private RolleModel $rolleModel;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->rolleModel  = new RolleModel();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Rollenverwaltung nutzen darf.
     *
     * Erlaubt sind derzeit die Rollen "Chef" oder "Personalbüro".
     * Bei fehlender Anmeldung wird eine Hinweisnachricht ausgegeben,
     * bei fehlender Berechtigung ein HTTP-Status 403 gesetzt.
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            echo '<p>Bitte melden Sie sich zuerst an.</p>';
            return false;
        }

        // Primär: Recht (neues System)
        if (method_exists($this->authService, 'hatRecht')) {
            try {
                if ($this->authService->hatRecht('ROLLEN_RECHTE_VERWALTEN')) {
                    return true;
                }
            } catch (\Throwable) {
                // Fallback unten
            }
        }

        $erlaubteRollen = ['Chef', 'Personalbüro'];

        foreach ($erlaubteRollen as $rollenName) {
            if ($this->authService->hatRolle($rollenName)) {
                return true;
            }
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Rollenverwaltung zu nutzen.</p>';

        return false;
    }


    /**
     * Platzhalter: Liste aller Rollen.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $rollen = $this->rolleModel->holeAlleAktiven();

        require __DIR__ . '/../views/rolle/liste.php';
    }


    /**
     * Zeigt das Formular zum Anlegen oder Bearbeiten einer Rolle.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $rolle = null;
        if ($id > 0) {
            // Für ein einfaches Formular reicht eine Abfrage über alle Rollen
            // und anschließende Auswahl; alternativ könnte eine dedizierte Methode
            // im Modell ergänzt werden.
            $alle = $this->rolleModel->holeAlleAktiven();
            foreach ($alle as $r) {
                if ((int)($r['id'] ?? 0) === $id) {
                    $rolle = $r;
                    break;
                }
            }
            if ($rolle === null) {
                $fehlermeldung = 'Die ausgewählte Rolle wurde nicht gefunden.';
            }
        }

        // Rechte für UI
        // Phase 1b (Rechte-Roadmap): Inaktive (Legacy-)Rechte ausblenden,
        // damit die Rollen-UI keine doppelten/alten Codes mehr anzeigt.
        $rechteAlle = $this->rolleModel->holeAlleRechte(true);
        $rolleRechtIds = $id > 0 ? $this->rolleModel->holeRechtIdsFuerRolle($id) : [];

        $csrfToken = Csrf::token(self::CSRF_BEREICH);

        require __DIR__ . '/../views/rolle/formular.php';
    }

    /**
     * Verarbeitet das Formular zum Anlegen/Bearbeiten einer Rolle.
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $csrfToken = Csrf::token(self::CSRF_BEREICH);
        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            http_response_code(400);

            // Für Neu/Bearbeiten wieder laden
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $rolle = [
                'id'           => $id > 0 ? $id : null,
                'name'         => trim((string)($_POST['name'] ?? '')),
                'beschreibung' => trim((string)($_POST['beschreibung'] ?? '')),
                'aktiv'        => (isset($_POST['aktiv']) && $_POST['aktiv'] === '1') ? 1 : 0,
            ];

            $rechteAlle = $this->rolleModel->holeAlleRechte(true);
            $rolleRechtIds = $this->leseRechtIdsAusPost();

            $fehlermeldung = 'CSRF-Check fehlgeschlagen. Bitte Seite neu laden.';
            require __DIR__ . '/../views/rolle/formular.php';
            return;
        }

        $id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name         = trim((string)($_POST['name'] ?? ''));
        $beschreibung = trim((string)($_POST['beschreibung'] ?? ''));
        $aktiv        = isset($_POST['aktiv']) && $_POST['aktiv'] === '1';

        $rechtIds = $this->leseRechtIdsAusPost();

        $fehlermeldung = null;

        if ($name === '') {
            $fehlermeldung = 'Bitte einen Namen für die Rolle angeben.';
        }

        $datenRolle = [
            'name'         => $name,
            'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
            'aktiv'        => $aktiv,
        ];

        if ($fehlermeldung !== null) {
            $rolle       = $datenRolle;
            $rolle['id'] = $id > 0 ? $id : null;

            $rechteAlle = $this->rolleModel->holeAlleRechte(true);
            $rolleRechtIds = $rechtIds;

            require __DIR__ . '/../views/rolle/formular.php';
            return;
        }

        $rolleIdGespeichert = $this->rolleModel->speichereRolleMitRechten($id > 0 ? $id : null, $datenRolle, $rechtIds);
        if ($rolleIdGespeichert === null) {
            $rolle       = $datenRolle;
            $rolle['id'] = $id > 0 ? $id : null;

            $rechteAlle = $this->rolleModel->holeAlleRechte(true);
            $rolleRechtIds = $rechtIds;

            $fehlermeldung = 'Rolle konnte nicht gespeichert werden. Bitte prüfen Sie die Eingaben.';
            require __DIR__ . '/../views/rolle/formular.php';
            return;
        }

        header('Location: ?seite=rollen_admin');
    }

    /**
     * Liest ausgewählte Rechte aus dem POST (checkbox name="rechte[]").
     *
     * @return int[]
     */
    private function leseRechtIdsAusPost(): array
    {
        $raw = $_POST['rechte'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $val) {
            if (is_numeric($val)) {
                $id = (int)$val;
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

}
