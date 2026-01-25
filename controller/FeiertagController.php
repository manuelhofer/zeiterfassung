<?php
declare(strict_types=1);

/**
 * FeiertagController
 *
 * Backend-Controller für das Verwalten und Anzeigen von Feiertagen.
 *
 * Erste Ausbaustufe:
 * - Liste aller Feiertage eines Jahres
 * - Automatische Generierung der bundeseinheitlichen Feiertage für das gewählte Jahr, falls noch nicht vorhanden
 */
class FeiertagController
{
    private AuthService $authService;
    private FeiertagService $feiertagService;
    private FeiertagModel $feiertagModel;

    public function __construct()
    {
        $this->authService     = AuthService::getInstanz();
        $this->feiertagService = FeiertagService::getInstanz();
        $this->feiertagModel   = new FeiertagModel();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Feiertagsverwaltung nutzen darf.
     *
     * Primär (neues System): Recht `FEIERTAGE_VERWALTEN`.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            echo '<p>Bitte melden Sie sich zuerst im Backend an.</p>';
            return false;
        }

        // Primär: Recht (neues System)
        if (method_exists($this->authService, 'hatRecht')) {
            try {
                if ($this->authService->hatRecht('FEIERTAGE_VERWALTEN')) {
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
        echo '<p>Sie haben keine Berechtigung, die Feiertagsverwaltung zu nutzen.</p>';

        return false;
    }

    /**
     * Übersicht aller Feiertage eines Jahres.
     *
     * - Standardmäßig wird das aktuelle Jahr angezeigt.
     * - Beim ersten Aufruf eines Jahres werden die gesetzlichen Feiertage automatisch erzeugt.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $aktuellesJahr = (int)date('Y');
        $jahr          = isset($_GET['jahr']) ? (int)$_GET['jahr'] : $aktuellesJahr;
        if ($jahr < 1970 || $jahr > 2100) {
            $jahr = $aktuellesJahr;
        }

        // Sicherstellen, dass es für dieses Jahr Einträge gibt
        $this->feiertagService->generiereFeiertageFuerJahrWennNoetig($jahr);

        // Feiertage aus der Datenbank laden
        $feiertage = [];
        try {
            $feiertage = $this->feiertagModel->holeFuerJahr($jahr, null);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Feiertage', [
                    'jahr'      => $jahr,
                    'exception' => $e->getMessage(),
                ], null, null, 'feiertag');
            }
        }

        require __DIR__ . '/../views/feiertag/liste.php';
    }


    /**
     * Feiertag bearbeiten-Formular.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo '<p>Feiertag wurde nicht gefunden.</p>';
            return;
        }

        $feiertag = null;

        try {
            $feiertag = $this->feiertagModel->holeNachId($id);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Feiertags', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'feiertag');
            }
        }

        if ($feiertag === null) {
            echo '<p>Feiertag wurde nicht gefunden.</p>';
            return;
        }

        $datum = (string)($feiertag['datum'] ?? '');
        $jahr  = (int)substr($datum, 0, 4);

        require __DIR__ . '/../views/feiertag/formular.php';
    }

    /**
     * Speichert Änderungen an einem Feiertag (z. B. Betriebsfrei-/Gesetzlich-Flags).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo '<p>Ungültiger Aufruf.</p>';
            return;
        }

        $id         = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $jahr       = isset($_POST['jahr']) ? (int)$_POST['jahr'] : (int)date('Y');
        $name       = isset($_POST['name']) ? (string)$_POST['name'] : '';
        $bundesland = isset($_POST['bundesland']) ? (string)$_POST['bundesland'] : null;

        $istGesetzlich   = isset($_POST['ist_gesetzlich']) && $_POST['ist_gesetzlich'] === '1';
        $istBetriebsfrei = isset($_POST['ist_betriebsfrei']) && $_POST['ist_betriebsfrei'] === '1';

        if ($id <= 0 || trim($name) === '') {
            echo '<p>Die übermittelten Daten sind unvollständig.</p>';
            return;
        }

        try {
            $this->feiertagModel->aktualisiereFeiertag(
                $id,
                $name,
                $bundesland,
                $istGesetzlich,
                $istBetriebsfrei
            );
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern eines Feiertags', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'feiertag');
            }
            echo '<p>Beim Speichern des Feiertags ist ein Fehler aufgetreten.</p>';
            return;
        }

        // Zurück zur Jahresübersicht
        header('Location: ?seite=feiertag_admin&jahr=' . $jahr);
        exit;
    }
}

