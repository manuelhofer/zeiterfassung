<?php
declare(strict_types=1);

/**
 * MaschineAdminController
 *
 * Backend-Controller für das Verwalten von Maschinen.
 */
class MaschineAdminController
{
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
        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
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
        $maschinen     = [];

        try {
            $sql = 'SELECT m.*, a.name AS abteilung_name
                    FROM maschine m
                    LEFT JOIN abteilung a ON a.id = m.abteilung_id
                    ORDER BY m.name ASC';

            $maschinen = $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Maschinen konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Maschinen im Admin-Bereich', [
                    'exception' => $e->getMessage(),
                ], null, null, 'maschine');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Maschinen</h2>
            <p>
                <a href="?seite=maschine_admin_bearbeiten">Neue Maschine anlegen</a>
            </p>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($maschinen) === 0): ?>
                <p>Es sind derzeit keine Maschinen hinterlegt.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Abteilung</th>
                            <th>Aktiv</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maschinen as $m): ?>
                            <?php
                                $id          = (int)($m['id'] ?? 0);
                                $name        = (string)($m['name'] ?? '');
                                $abteilung   = (string)($m['abteilung_name'] ?? '');
                                $aktiv       = (int)($m['aktiv'] ?? 0) === 1;
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($abteilung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                                <td>
                                    <a href="?seite=maschine_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden einer Maschine im Admin-Bereich', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id, null, 'maschine');
            }
        }

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Abteilungen für Maschinen-Formular', [
                    'exception' => $e->getMessage(),
                ], null, null, 'maschine');
            }
        }

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

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
        }

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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern einer Maschine', [
                    'id'        => $id,
                    'maschine'  => $maschine,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'maschine');
            }

            $this->renderFormular($maschine, $abteilungen, $fehlermeldung, $erfolgsmeldung);
            return;
        }

        $maschinenId = $id > 0 ? $id : (int)$this->datenbank->letzteInsertId();
        $codeBildPfad = null;
        $barcodeFallback = false;

        try {
            $qrService = new MaschineQrCodeService();
            $codeBildPfad = $qrService->erzeugeMaschinenBarcode($maschinenId, $name);
            if ($codeBildPfad === null) {
                $barcodeFallback = true;
                $codeBildPfad = $qrService->erzeugeMaschinenQrCode($maschinenId);
            }
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Erzeugen des Maschinen-Barcodes', [
                    'id'        => $maschinenId,
                    'exception' => $e->getMessage(),
                ], $maschinenId, null, 'maschine');
            }
        }

        if ($codeBildPfad === null) {
            $fehlermeldung = 'Die Maschine wurde gespeichert, aber der Barcode konnte nicht erstellt werden. Bitte Schreibrechte im Verzeichnis public/uploads/maschinen_codes prüfen.';
        } elseif ($barcodeFallback) {
            $erfolgsmeldung = 'Die Maschine wurde gespeichert. Der Barcode konnte nicht erstellt werden, daher wurde ein QR-Code hinterlegt.';
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
     * Rendert das Formular (Neu/Bearbeiten).
     *
     * @param array<string,mixed> $maschine
     * @param array<int,array<string,mixed>> $abteilungen
     */
    private function renderFormular(array $maschine, array $abteilungen, ?string $fehlermeldung, ?string $erfolgsmeldung): void
    {
        require __DIR__ . '/../views/layout/header.php';

        $id          = (int)($maschine['id'] ?? 0);
        $name        = (string)($maschine['name'] ?? '');
        $abteilungId = $maschine['abteilung_id'] ?? null;
        $beschreibung = (string)($maschine['beschreibung'] ?? '');
        $codeBildPfad = (string)($maschine['code_bild_pfad'] ?? '');
        $normalisierterCodeBildPfad = $this->normalisiereCodeBildPfad($codeBildPfad) ?? '';
        $maschinenQrUrlKonfiguriert = $this->holeMaschinenQrUrl() !== '';
        $codeBildUrl = $this->baueQrCodeUrlPfad($normalisierterCodeBildPfad);
        $aktiv       = (int)($maschine['aktiv'] ?? 0) === 1;
        $scanDaten = $id > 0 ? $id . '_' . $name : '';

        if ($abteilungId !== null) {
            $abteilungId = (int)$abteilungId;
            if ($abteilungId <= 0) {
                $abteilungId = null;
            }
        }
        ?>
        <section>
            <h2><?php echo $id > 0 ? 'Maschine bearbeiten' : 'Maschine anlegen'; ?></h2>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($erfolgsmeldung)): ?>
                <div class="erfolgsmeldung">
                    <?php echo htmlspecialchars((string)$erfolgsmeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=maschine_admin_speichern">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div style="margin-bottom: 0.75rem;">
                    <label for="name"><strong>Name</strong></label><br>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 100%; max-width: 520px;">
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label for="abteilung_id"><strong>Abteilung</strong></label><br>
                    <select id="abteilung_id" name="abteilung_id" style="width: 100%; max-width: 520px;">
                        <option value="">(keine)</option>
                        <?php foreach ($abteilungen as $abt): ?>
                            <?php
                                $aid = (int)($abt['id'] ?? 0);
                                $aname = (string)($abt['name'] ?? '');
                                $selected = ($abteilungId !== null && $aid === (int)$abteilungId) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $aid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($aname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label for="beschreibung"><strong>Beschreibung</strong></label><br>
                    <textarea id="beschreibung" name="beschreibung" rows="4" style="width: 100%; max-width: 820px;"><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                        Aktiv
                    </label>
                </div>

                <?php if ($id > 0): ?>
                    <div style="margin: 1rem 0; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; max-width: 520px;">
                        <div><strong>Maschinen-Barcode</strong></div>
                        <?php if (!$maschinenQrUrlKonfiguriert): ?>
                            <div style="margin-top: 0.5rem; color: #a00;">
                                Bitte die Maschinen-QR-URL in der Konfiguration hinterlegen, damit der Barcode korrekt geladen werden kann.
                            </div>
                        <?php elseif ($codeBildUrl !== ''): ?>
                            <div style="margin-top: 0.5rem;">
                                <img src="<?php echo htmlspecialchars($codeBildUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Barcode Maschine <?php echo $id; ?>" style="max-width: 100%; height: auto;">
                            </div>
                            <div style="margin-top: 0.5rem; display:flex; gap: 1rem; flex-wrap: wrap;">
                                <a href="<?php echo htmlspecialchars($codeBildUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" target="_blank">Download PNG</a>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 0.5rem; color: #444;">
                                Noch kein Barcode gespeichert. Bitte die Maschine speichern.
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #444;">
                            Scan-Code: <code><?php echo htmlspecialchars($scanDaten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display:flex; gap: 1rem; align-items:center;">
                    <button type="submit">Speichern</button>
                    <a href="?seite=maschine_admin">Abbrechen</a>
                </div>
            </form>
        </section>
        <?php

        require __DIR__ . '/../views/layout/footer.php';
    }

    private function baueQrCodeUrlPfad(string $codeBildPfad): string
    {
        $codeBildPfad = trim($codeBildPfad);
        if ($codeBildPfad === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $codeBildPfad) === 1) {
            return $codeBildPfad;
        }

        $maschinenQrUrl = $this->holeMaschinenQrUrl();
        $dateiname = basename($codeBildPfad);
        if ($dateiname === '') {
            return '';
        }

        if ($maschinenQrUrl === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $maschinenQrUrl) === 1) {
            return rtrim($maschinenQrUrl, '/') . '/' . ltrim($dateiname, '/');
        }

        return '/' . trim($maschinenQrUrl, '/') . '/' . ltrim($dateiname, '/');
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

    private function holeMaschinenQrUrl(): string
    {
        $konfigService = $this->holeKonfigurationService();
        if ($konfigService !== null) {
            $maschinenQrUrl = $konfigService->get('maschinen_qr_url', null);
            if (is_string($maschinenQrUrl) && trim($maschinenQrUrl) !== '') {
                return trim($maschinenQrUrl);
            }

            $alterUrl = $konfigService->get('maschinen_qr_base_url', null);
            if (is_string($alterUrl)) {
                return trim($alterUrl);
            }
        }

        return '';
    }

    /**
     * @return object|null
     */
    private function holeKonfigurationService(): ?object
    {
        if (!class_exists('KonfigurationService')) {
            $pfad = __DIR__ . '/../services/KonfigurationService.php';
            if (is_file($pfad)) {
                require_once $pfad;
            }
        }

        if (class_exists('KonfigurationService')) {
            return KonfigurationService::getInstanz();
        }

        return null;
    }

}
