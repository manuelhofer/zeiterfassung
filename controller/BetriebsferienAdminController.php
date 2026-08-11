<?php
declare(strict_types=1);

/**
 * BetriebsferienAdminController
 *
 * Backend-Controller für das Verwalten von Betriebsferien.
 *
 * Erste Ausbaustufe:
 * - Liste aller Betriebsferien (global + abteilungsspezifisch)
 * - Anlegen/Bearbeiten inkl. Aktiv-Flag
 *
 * WICHTIG: Die Berücksichtigung in Sollstunden/Auswertungen folgt in einem
 * separaten Schritt, damit die Changes klein bleiben (max. 3 Dateien pro Patch).
 */
class BetriebsferienAdminController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'betriebsferien_admin';

    private AuthService $authService;
    private Database $datenbank;
    private AbteilungModel $abteilungModel;

    public function __construct()
    {
        $this->authService    = AuthService::getInstanz();
        $this->datenbank      = Database::getInstanz();
        $this->abteilungModel = new AbteilungModel();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Betriebsferienverwaltung nutzen darf.
     *
     * Primär wird das Recht `BETRIEBSFERIEN_VERWALTEN` geprüft.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('BETRIEBSFERIEN_VERWALTEN')) {
            return true;
        }

        // Legacy-Fallback: Rollen (für Bestandsinstallationen ohne gepflegte Rechtezuordnung)
        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, die Betriebsferien zu verwalten.</p>';
        return false;
    }


    /**
     * Übersicht aller Betriebsferien.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $fehlermeldung = null;
        $eintraege     = [];

        // Rückmeldungen aus toggleAktiv() – der leitet hierher zurück, damit
        // ein Neuladen die Aktion nicht wiederholt.
        $meldung = isset($_GET['meldung']) ? (string)$_GET['meldung'] : '';
        if ($meldung === 'csrf_ungueltig') {
            $fehlermeldung = 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.';
        } elseif ($meldung === 'ungueltige_id') {
            $fehlermeldung = 'Der Eintrag wurde nicht gefunden.';
        } elseif ($meldung === 'speichern_fehlgeschlagen') {
            $fehlermeldung = 'Der Eintrag konnte nicht umgeschaltet werden.';
        }

        try {
            $sql = 'SELECT bf.*, a.name AS abteilung_name
                    FROM betriebsferien bf
                    LEFT JOIN abteilung a ON a.id = bf.abteilung_id
                    ORDER BY bf.von_datum ASC, bf.bis_datum ASC, bf.id ASC';

            $eintraege = $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Betriebsferien konnten nicht geladen werden.';
            Logger::error('Fehler beim Laden der Betriebsferien im Admin-Bereich', [
                'exception' => $e->getMessage(),
            ], null, null, 'betriebsferien');
        }

        // Die View baut ihr CSRF-Feld selbst; sie bekommt dafür den
        // Bereichsnamen, damit er nicht ein zweites Mal irgendwo steht.
        $csrfBereich = self::CSRF_BEREICH;

        require __DIR__ . '/../views/betriebsferien/liste.php';
    }

    /**
     * Formular zum Anlegen/Bearbeiten.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $idRaw = $_GET['id'] ?? '';
        $id    = is_numeric($idRaw) ? (int)$idRaw : 0;

        $eintrag = [
            'id'           => $id,
            'von_datum'    => '',
            'bis_datum'    => '',
            'beschreibung' => '',
            'abteilung_id' => null,
            'aktiv'        => 1,
        ];

        $fehlermeldung = null;

        try {
            if ($id > 0) {
                $sql = 'SELECT * FROM betriebsferien WHERE id = :id LIMIT 1';
                $geladen = $this->datenbank->fetchEine($sql, ['id' => $id]);
                if ($geladen === null) {
                    $fehlermeldung = 'Der Eintrag wurde nicht gefunden.';
                } else {
                    $eintrag = $geladen;
                }
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Der Eintrag konnte nicht geladen werden.';
            Logger::error('Fehler beim Laden einer Betriebsferien-Zeile', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ], $id > 0 ? $id : null, null, 'betriebsferien');
        }

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
        }

        $this->renderFormular($eintrag, $abteilungen, $fehlermeldung);
    }

    /**
     * Speichert einen Eintrag (Neu oder Bearbeiten).
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

        $idRaw          = $_POST['id'] ?? '';
        $id             = is_numeric($idRaw) ? (int)$idRaw : 0;
        $von            = trim((string)($_POST['von_datum'] ?? ''));
        $bis            = trim((string)($_POST['bis_datum'] ?? ''));
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
        if (!$this->istValidesDatum($von) || !$this->istValidesDatum($bis)) {
            $fehlermeldung = 'Bitte geben Sie ein gültiges Datum im Format JJJJ-MM-TT an.';
        } elseif ($von > $bis) {
            $fehlermeldung = 'Das "Von"-Datum darf nicht nach dem "Bis"-Datum liegen.';
        }

        $eintrag = [
            'id'           => $id,
            'von_datum'    => $von,
            'bis_datum'    => $bis,
            'beschreibung' => $beschreibung,
            'abteilung_id' => $abteilungId,
            'aktiv'        => $aktiv,
        ];

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
        }

        if ($fehlermeldung !== null) {
            $this->renderFormular($eintrag, $abteilungen, $fehlermeldung);
            return;
        }

        try {
            if ($id > 0) {
                $sql = 'UPDATE betriebsferien
                        SET von_datum = :von_datum,
                            bis_datum = :bis_datum,
                            beschreibung = :beschreibung,
                            abteilung_id = :abteilung_id,
                            aktiv = :aktiv
                        WHERE id = :id';

                $this->datenbank->ausfuehren($sql, [
                    'id'           => $id,
                    'von_datum'    => $von,
                    'bis_datum'    => $bis,
                    'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
                    'abteilung_id' => $abteilungId,
                    'aktiv'        => $aktiv,
                ]);
            } else {
                $sql = 'INSERT INTO betriebsferien (von_datum, bis_datum, beschreibung, abteilung_id, aktiv)
                        VALUES (:von_datum, :bis_datum, :beschreibung, :abteilung_id, :aktiv)';

                $this->datenbank->ausfuehren($sql, [
                    'von_datum'    => $von,
                    'bis_datum'    => $bis,
                    'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
                    'abteilung_id' => $abteilungId,
                    'aktiv'        => $aktiv,
                ]);
            }

            header('Location: ?seite=betriebsferien_admin');
            return;
        } catch (\Throwable $e) {
            $fehlermeldung = 'Der Eintrag konnte nicht gespeichert werden.';
            Logger::error('Fehler beim Speichern von Betriebsferien', [
                'id'        => $id,
                'eintrag'   => $eintrag,
                'exception' => $e->getMessage(),
            ], $id > 0 ? $id : null, null, 'betriebsferien');

            $this->renderFormular($eintrag, $abteilungen, $fehlermeldung);
        }
    }

    /**
     * @param array<string,mixed> $eintrag
     * @param array<int,array<string,mixed>> $abteilungen
     */
    private function renderFormular(array $eintrag, array $abteilungen, ?string $fehlermeldung): void
    {
        require __DIR__ . '/../views/betriebsferien/formular.php';
    }

    /**
     * Schaltet einen Betriebsferien-Eintrag aktiv/inaktiv.
     *
     * Warum es das braucht: Alle Leser werten `betriebsferien.aktiv` aus
     * (`BetriebsferienModel::holeAktive()`, `UrlaubJahresübersichtController`,
     * `ReportService`), gesetzt wurde die Spalte aber nur beim Anlegen. Ein
     * Eintrag liess sich also nur löschen, nicht stilllegen – und Löschen
     * nimmt die Historie mit. Aufbau bewusst wie
     * `KurzarbeitAdminController::toggleAktiv()`, damit beide Masken sich
     * gleich verhalten.
     */
    public function toggleAktiv(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        // Mutierende Aktion: nur per POST, und nur mit gültigem Token.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=betriebsferien_admin');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            header('Location: ?seite=betriebsferien_admin&meldung=csrf_ungueltig');
            return;
        }

        $id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $aktiv = (isset($_POST['aktiv']) && (int)$_POST['aktiv'] === 1) ? 1 : 0;

        if ($id <= 0) {
            header('Location: ?seite=betriebsferien_admin&meldung=ungueltige_id');
            return;
        }

        try {
            $this->datenbank->ausfuehren(
                'UPDATE betriebsferien SET aktiv = :aktiv WHERE id = :id',
                ['aktiv' => $aktiv, 'id' => $id]
            );
        } catch (\Throwable $e) {
            Logger::error('Fehler beim Umschalten von betriebsferien.aktiv', [
                'id'        => $id,
                'aktiv'     => $aktiv,
                'exception' => $e->getMessage(),
            ], null, null, 'betriebsferien');

            header('Location: ?seite=betriebsferien_admin&meldung=speichern_fehlgeschlagen');
            return;
        }

        header('Location: ?seite=betriebsferien_admin');
    }

    private function istValidesDatum(string $datum): bool
    {
        if ($datum === '') {
            return false;
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $datum);
        return $dt !== false && $dt->format('Y-m-d') === $datum;
    }
}
