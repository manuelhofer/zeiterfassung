<?php
declare(strict_types=1);

/**
 * AbteilungAdminController
 *
 * Backend-Controller für das Verwalten von Abteilungen.
 * Stellt zunächst eine einfache Liste aller aktiven Abteilungen bereit.
 */
class AbteilungAdminController
{
    private AuthService $authService;
    private AbteilungModel $abteilungModel;

    public function __construct()
    {
        $this->authService    = AuthService::getInstanz();
        $this->abteilungModel = new AbteilungModel();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Abteilungsverwaltung nutzen darf.
     *
     * Aktuell sind hierfür die Rollen "Chef" oder "Personalbüro" ausreichend.
     * Ist niemand angemeldet, wird auf die Login-Seite umgeleitet.
     * Bei fehlender Berechtigung wird ein HTTP-Status 403 zurückgegeben.
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Recht (neues System)
        if (method_exists($this->authService, 'hatRecht')) {
            try {
                if ($this->authService->hatRecht('ABTEILUNG_VERWALTEN')) {
                    return true;
                }
            } catch (\Throwable) {
                // Fallback unten
            }
        }

        $erlaubteRollen = ['Chef', 'Personalbüro', 'Personalbuero'];

        foreach ($erlaubteRollen as $rollenName) {
            if ($this->authService->hatRolle($rollenName)) {
                return true;
            }
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Abteilungsstammdaten zu verwalten.</p>';

        return false;
    }

    /**
     * Übersicht aller aktiven Abteilungen.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $fehlermeldung = null;
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen  = [];
            $fehlermeldung = 'Die Abteilungen konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Abteilungen im Admin-Bereich', [
                    'exception' => $e->getMessage(),
                ], null, null, 'abteilung');
            }
        }

        require __DIR__ . '/../views/abteilung/liste.php';
    }


    /**
     * Formular zum Anlegen/Bearbeiten einer Abteilung.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $idRaw = $_GET['id'] ?? '';
        $id    = is_numeric($idRaw) ? (int)$idRaw : 0;

        $abteilung        = null;
        $fehlermeldung    = null;
        $alleAbteilungen  = [];

        try {
            // Für die Auswahl der übergeordneten Abteilung alle aktiven Abteilungen laden.
            $alleAbteilungen = $this->abteilungModel->holeAlleAktiven();

            if ($id > 0) {
                $abteilung = $this->abteilungModel->holeNachId($id);
                if ($abteilung === null) {
                    $fehlermeldung = 'Die ausgewählte Abteilung wurde nicht gefunden.';
                }
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Abteilung konnte nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden einer Abteilung im Admin-Bereich', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id, null, 'abteilung');
            }
        }

        require __DIR__ . '/../views/abteilung/formular.php';
    }

    /**
     * Speichert eine Abteilung (Neu oder Bearbeiten).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $idRaw        = $_POST['id'] ?? '';
        $id           = is_numeric($idRaw) ? (int)$idRaw : 0;
        $name         = trim((string)($_POST['name'] ?? ''));
        $beschreibung = trim((string)($_POST['beschreibung'] ?? ''));
        $parentIdRaw  = $_POST['parent_id'] ?? '';
        $aktivRaw     = $_POST['aktiv'] ?? null;

        $parentId = null;
        if ($parentIdRaw !== '') {
            $parentId = (int)$parentIdRaw;
            if ($parentId <= 0) {
                $parentId = null;
            }
        }

        $aktiv = $aktivRaw !== null ? 1 : 0;

        // Validierung
        $fehlermeldung = null;
        if ($name === '') {
            $fehlermeldung = 'Bitte geben Sie einen Namen für die Abteilung ein.';
        }

        if ($fehlermeldung === null && $id > 0 && $parentId !== null && $parentId === $id) {
            $fehlermeldung = 'Eine Abteilung kann nicht sich selbst als übergeordnete Abteilung haben.';
        }

        if ($fehlermeldung !== null) {
            $abteilung = [
                'id'           => $id,
                'name'         => $name,
                'beschreibung' => $beschreibung,
                'parent_id'    => $parentId,
                'aktiv'        => $aktiv,
            ];

            try {
                $alleAbteilungen = $this->abteilungModel->holeAlleAktiven();
            } catch (\Throwable $e) {
                $alleAbteilungen = [];
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Nachladen der Abteilungen für das Formular', [
                        'exception' => $e->getMessage(),
                    ], null, null, 'abteilung');
                }
            }

            $fehlermeldungLok = $fehlermeldung;
            $fehlermeldung    = $fehlermeldungLok;

            require __DIR__ . '/../views/abteilung/formular.php';
            return;
        }

        $daten = [
            'name'         => $name,
            'beschreibung' => $beschreibung,
            'parent_id'    => $parentId,
            'aktiv'        => $aktiv,
        ];

        try {
            if ($id > 0) {
                $erfolg = $this->abteilungModel->aktualisiereAbteilung($id, $daten);
                if (!$erfolg) {
                    throw new \RuntimeException('Aktualisieren der Abteilung fehlgeschlagen.');
                }
            } else {
                $neueId = $this->abteilungModel->erstelleAbteilung($daten);
                if ($neueId === null) {
                    throw new \RuntimeException('Anlegen der Abteilung fehlgeschlagen.');
                }
            }

            header('Location: ?seite=abteilung_admin');
            return;
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Abteilung konnte nicht gespeichert werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern einer Abteilung', [
                    'id'        => $id,
                    'daten'     => $daten,
                    'exception' => $e->getMessage(),
                ], $id, null, 'abteilung');
            }

            $abteilung = [
                'id'           => $id,
                'name'         => $name,
                'beschreibung' => $beschreibung,
                'parent_id'    => $parentId,
                'aktiv'        => $aktiv,
            ];

            try {
                $alleAbteilungen = $this->abteilungModel->holeAlleAktiven();
            } catch (\Throwable $e2) {
                $alleAbteilungen = [];
                if (class_exists('Logger')) {
                    Logger::error('Fehler beim Nachladen der Abteilungen nach einem Speicherfehler', [
                        'exception' => $e2->getMessage(),
                    ], null, null, 'abteilung');
                }
            }

            require __DIR__ . '/../views/abteilung/formular.php';
        }
    }

}
