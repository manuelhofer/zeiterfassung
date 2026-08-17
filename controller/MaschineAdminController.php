<?php
declare(strict_types=1);

/**
 * MaschineAdminController
 *
 * Backend-Controller für das Verwalten von Maschinen.
 */
class MaschineAdminController
{
    /** Bereichsname für `Csrf` - siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'maschine_admin';

    private AuthService $authService;
    private Database $datenbank;
    private MaschineModel $maschineModel;
    private AbteilungModel $abteilungModel;

    public function __construct()
    {
        $this->authService     = AuthService::getInstanz();
        $this->datenbank       = Database::getInstanz();
        $this->maschineModel   = new MaschineModel();
        $this->abteilungModel  = new AbteilungModel();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Maschinenverwaltung nutzen darf.
     *
     * Primär wird das Recht `MASCHINEN_VERWALTEN` geprüft.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('MASCHINEN_VERWALTEN')) {
            return true;
        }

        // Legacy-Fallback: Rollen (für Bestandsinstallationen ohne gepflegte Rechtezuordnung)
        if ($this->authService->istLegacyAdmin()) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Maschinenstammdaten zu verwalten.</p>';
        return false;
    }

    /**
     * Übersicht aller Maschinen.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $fehlermeldung = null;
        if (isset($_SESSION['maschine_admin_flash_error'])) {
            $fehlermeldung = (string)$_SESSION['maschine_admin_flash_error'];
            unset($_SESSION['maschine_admin_flash_error']);
        }

        $maschinen     = [];
        // Nur ein Lesefehler erklärt die leere Liste; eine Flash-Meldung aus
        // einer vorherigen Aktion sagt über den Bestand nichts.
        $ladefehler    = false;

        try {
            $sql = 'SELECT m.*, a.name AS abteilung_name
                    FROM maschine m
                    LEFT JOIN abteilung a ON a.id = m.abteilung_id
                    ORDER BY m.name ASC';

            $maschinen = $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Maschinen konnten nicht geladen werden.';
            $ladefehler    = true;
            Logger::error('Fehler beim Laden der Maschinen im Admin-Bereich', [
                'exception' => $e->getMessage(),
            ], null, null, 'maschine');
        }

        require __DIR__ . '/../views/maschine/liste.php';
    }

    /**
     * Formular zum Anlegen/Bearbeiten einer Maschine.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $idRaw = $_GET['id'] ?? '';
        $id    = is_numeric($idRaw) ? (int)$idRaw : 0;

        $maschine     = [
            'id'           => $id,
            'name'         => '',
            'abteilung_id' => null,
            'beschreibung' => '',
            'code_bild_pfad' => null,
            'aktiv'        => 1,
        ];

        $fehlermeldung = null;

        try {
            if ($id > 0) {
                $geladen = $this->maschineModel->holeNachId($id);
                if ($geladen === null) {
                    $fehlermeldung = 'Die ausgewählte Maschine wurde nicht gefunden.';
                } else {
                    $maschine = $geladen;
                }
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Maschine konnte nicht geladen werden.';
            Logger::error('Fehler beim Laden einer Maschine im Admin-Bereich', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ], $id, null, 'maschine');
        }

        // `holeAlleAktiven()` fängt selbst ab, protokolliert und liefert `[]`;
        // der `catch` hier konnte nie greifen (T-112).
        $abteilungen = $this->abteilungModel->holeAlleAktiven();

        $this->renderFormular($maschine, $abteilungen, $fehlermeldung, null);
    }

    /**
     * Speichert eine Maschine (Neu oder Bearbeiten).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=maschine_admin');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            $_SESSION['maschine_admin_flash_error'] = 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut speichern.';
            header('Location: ?seite=maschine_admin');
            return;
        }

        $idRaw          = $_POST['id'] ?? '';
        $id             = is_numeric($idRaw) ? (int)$idRaw : 0;
        $name           = trim((string)($_POST['name'] ?? ''));
        $beschreibung   = trim((string)($_POST['beschreibung'] ?? ''));
        $abteilungRaw   = (string)($_POST['abteilung_id'] ?? '');
        $aktivRaw       = $_POST['aktiv'] ?? null;

        $abteilungId = null;
        if ($abteilungRaw !== '') {
            $abteilungId = (int)$abteilungRaw;
            if ($abteilungId <= 0) {
                $abteilungId = null;
            }
        }

        $aktiv = $aktivRaw !== null ? 1 : 0;

        $fehlermeldung = null;
        $erfolgsmeldung = null;
        if ($name === '') {
            $fehlermeldung = 'Bitte geben Sie einen Namen für die Maschine ein.';
        }

        $maschine = [
            'id'           => $id,
            'name'         => $name,
            'abteilung_id' => $abteilungId,
            'beschreibung' => $beschreibung,
            'code_bild_pfad' => null,
            'aktiv'        => $aktiv,
        ];

        // Siehe oben (T-112).
        $abteilungen = $this->abteilungModel->holeAlleAktiven();

        if ($fehlermeldung !== null) {
            $this->renderFormular($maschine, $abteilungen, $fehlermeldung, $erfolgsmeldung);
            return;
        }

        try {
            if ($id > 0) {
                $sql = 'UPDATE maschine
                        SET name = :name,
                            abteilung_id = :abteilung_id,
                            beschreibung = :beschreibung,
                            aktiv = :aktiv
                        WHERE id = :id';

                $this->datenbank->ausfuehren($sql, [
                    'id'           => $id,
                    'name'         => $name,
                    'abteilung_id' => $abteilungId,
                    'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
                    'aktiv'        => $aktiv,
                ]);
            } else {
                $sql = 'INSERT INTO maschine (name, abteilung_id, beschreibung, aktiv)
                        VALUES (:name, :abteilung_id, :beschreibung, :aktiv)';

                $this->datenbank->ausfuehren($sql, [
                    'name'         => $name,
                    'abteilung_id' => $abteilungId,
                    'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
                    'aktiv'        => $aktiv,
                ]);
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Maschine konnte nicht gespeichert werden. Bitte prüfen Sie die Datenbankverbindung.';
            Logger::error('Fehler beim Speichern einer Maschine', [
                'id'        => $id,
                'maschine'  => $maschine,
                'exception' => $e->getMessage(),
            ], $id > 0 ? $id : null, null, 'maschine');

            $this->renderFormular($maschine, $abteilungen, $fehlermeldung, $erfolgsmeldung);
            return;
        }

        $maschinenId = $id > 0 ? $id : (int)$this->datenbank->letzteInsertId();
        $codeBildPfad = null;

        try {
            $codeService = new MaschineQrCodeService();
            $codeBildPfad = $codeService->erzeugeMaschinenBarcode($maschinenId, $name);
            if ($codeBildPfad !== null) {
                $codeBildPfad = $this->normalisiereCodeBildPfad($codeBildPfad);
                $sql = 'UPDATE maschine
                        SET code_bild_pfad = :code_bild_pfad
                        WHERE id = :id';
                $this->datenbank->ausfuehren($sql, [
                    'id' => $maschinenId,
                    'code_bild_pfad' => $codeBildPfad,
                ]);
            }
        } catch (\Throwable $e) {
            $codeBildPfad = null;
            Logger::error('Fehler beim Erzeugen des Maschinen-Barcodes', [
                'id'        => $maschinenId,
                'exception' => $e->getMessage(),
            ], $maschinenId, null, 'maschine');
        }

        if ($codeBildPfad === null) {
            $fehlermeldung = 'Die Maschine wurde gespeichert, aber der Barcode konnte nicht erstellt werden. Bitte Schreibrechte im Verzeichnis public/uploads/maschinen_codes prüfen.';
        } else {
            $erfolgsmeldung = 'Die Maschine wurde gespeichert und der Barcode wurde aktualisiert.';
        }

        $aktuelleMaschine = $this->maschineModel->holeNachId($maschinenId);
        if ($aktuelleMaschine === null) {
            $aktuelleMaschine = $maschine;
            $aktuelleMaschine['id'] = $maschinenId;
            $aktuelleMaschine['code_bild_pfad'] = $codeBildPfad;
        }

        $this->renderFormular($aktuelleMaschine, $abteilungen, $fehlermeldung, $erfolgsmeldung);
    }

    /**
     * Barcode für eine Maschine neu generieren.
     */
    public function barcodeNeuGenerieren(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        // Diese Aktion schreibt (`maschine.code_bild_pfad`) – sie gehört
        // deshalb hinter POST und Token. Das Formular in
        // `views/maschine/formular.php` schickt seit jeher per POST; erzwungen
        // hat es hier nur niemand, ein `<img src>` hätte genügt.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=maschine_admin');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            $_SESSION['maschine_admin_flash_error'] = 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut versuchen.';
            header('Location: ?seite=maschine_admin');
            return;
        }

        $idRaw = $_GET['id'] ?? '';
        $id    = is_numeric($idRaw) ? (int)$idRaw : 0;

        $fehlermeldung = null;
        $erfolgsmeldung = null;
        $maschine = null;

        if ($id <= 0) {
            $fehlermeldung = 'Ungültige Maschinen-ID.';
        } else {
            try {
                $maschine = $this->maschineModel->holeNachId($id);
                if ($maschine === null) {
                    $fehlermeldung = 'Die ausgewählte Maschine wurde nicht gefunden.';
                }
            } catch (\Throwable $e) {
                $fehlermeldung = 'Die Maschine konnte nicht geladen werden.';
                Logger::error('Fehler beim Laden einer Maschine für Barcode-Neuerzeugung', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id, null, 'maschine');
            }
        }

        if ($maschine !== null && $fehlermeldung === null) {
            $maschinenName = (string)($maschine['name'] ?? '');
            $codeBildPfad = null;

            try {
                $codeService = new MaschineQrCodeService();
                $codeBildPfad = $codeService->erzeugeMaschinenBarcode($id, $maschinenName);
                if ($codeBildPfad !== null) {
                    $codeBildPfad = $this->normalisiereCodeBildPfad($codeBildPfad);
                    $sql = 'UPDATE maschine
                            SET code_bild_pfad = :code_bild_pfad
                            WHERE id = :id';
                    $this->datenbank->ausfuehren($sql, [
                        'id' => $id,
                        'code_bild_pfad' => $codeBildPfad,
                    ]);
                    $maschine['code_bild_pfad'] = $codeBildPfad;
                }
            } catch (\Throwable $e) {
                $codeBildPfad = null;
                Logger::error('Fehler beim Neuerzeugen des Maschinen-Barcodes', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id, null, 'maschine');
            }

            if ($codeBildPfad === null) {
                $fehlermeldung = 'Der Barcode konnte nicht neu erzeugt werden. Bitte Schreibrechte im Verzeichnis public/uploads/maschinen_codes prüfen.';
            } else {
                $erfolgsmeldung = 'Der Barcode wurde neu generiert.';
            }
        }

        // Siehe oben (T-112).
        $abteilungen = $this->abteilungModel->holeAlleAktiven();

        if ($maschine === null) {
            $maschine = [
                'id'           => $id,
                'name'         => '',
                'abteilung_id' => null,
                'beschreibung' => '',
                'code_bild_pfad' => null,
                'aktiv'        => 1,
            ];
        }

        $this->renderFormular($maschine, $abteilungen, $fehlermeldung, $erfolgsmeldung);
    }

    /**
     * Rendert das Formular (Neu/Bearbeiten).
     *
     * @param array<string,mixed> $maschine
     * @param array<int,array<string,mixed>> $abteilungen
     */
    private function renderFormular(array $maschine, array $abteilungen, ?string $fehlermeldung, ?string $erfolgsmeldung): void
    {
        $codeBildPfad = (string)($maschine['code_bild_pfad'] ?? '');
        $normalisierterCodeBildPfad = $this->normalisiereCodeBildPfad($codeBildPfad) ?? '';
        // Die Bild-URL kommt aus dem Service, damit Erzeugung und Anzeige
        // dieselbe Logik nutzen (Basis konfiguriert oder automatisch abgeleitet).
        // Sie wird hier gebaut und nicht in der View, weil dafür der
        // Normalisierer dieses Controllers gebraucht wird.
        $codeBildUrl = (new MaschineQrCodeService())->baueBildUrl($normalisierterCodeBildPfad);

        $csrfBereich = self::CSRF_BEREICH;
        require __DIR__ . '/../views/maschine/formular.php';
    }

    private function normalisiereCodeBildPfad(?string $codeBildPfad): ?string
    {
        if ($codeBildPfad === null) {
            return null;
        }

        $codeBildPfad = trim($codeBildPfad);
        if ($codeBildPfad === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $codeBildPfad) === 1) {
            return $codeBildPfad;
        }

        return ltrim($codeBildPfad, '/');
    }


}
