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

        $this->renderFormular($maschine, $abteilungen, $fehlermeldung);
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
        if ($name === '') {
            $fehlermeldung = 'Bitte geben Sie einen Namen für die Maschine ein.';
        }

        $maschine = [
            'id'           => $id,
            'name'         => $name,
            'abteilung_id' => $abteilungId,
            'beschreibung' => $beschreibung,
            'aktiv'        => $aktiv,
        ];

        $abteilungen = [];
        try {
            $abteilungen = $this->abteilungModel->holeAlleAktiven();
        } catch (\Throwable $e) {
            $abteilungen = [];
        }

        if ($fehlermeldung !== null) {
            $this->renderFormular($maschine, $abteilungen, $fehlermeldung);
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

            header('Location: ?seite=maschine_admin');
            return;
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Maschine konnte nicht gespeichert werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern einer Maschine', [
                    'id'        => $id,
                    'maschine'  => $maschine,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'maschine');
            }

            $this->renderFormular($maschine, $abteilungen, $fehlermeldung);
        }
    }

    /**
     * Rendert das Formular (Neu/Bearbeiten).
     *
     * @param array<string,mixed> $maschine
     * @param array<int,array<string,mixed>> $abteilungen
     */
    private function renderFormular(array $maschine, array $abteilungen, ?string $fehlermeldung): void
    {
        require __DIR__ . '/../views/layout/header.php';

        $id          = (int)($maschine['id'] ?? 0);
        $name        = (string)($maschine['name'] ?? '');
        $abteilungId = $maschine['abteilung_id'] ?? null;
        $beschreibung = (string)($maschine['beschreibung'] ?? '');
        $aktiv       = (int)($maschine['aktiv'] ?? 0) === 1;

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
                        <div style="margin-top: 0.5rem;">
                            <img src="maschine_code.php?id=<?php echo $id; ?>&amp;format=svg" alt="Barcode Maschine <?php echo $id; ?>" style="max-width: 100%; height: auto;">
                        </div>
                        <div style="margin-top: 0.5rem; display:flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="maschine_code.php?id=<?php echo $id; ?>&amp;format=svg" target="_blank">Download SVG</a>
                            <a href="maschine_code.php?id=<?php echo $id; ?>&amp;format=png" target="_blank">Download PNG</a>
                        </div>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #444;">
                            Scan-Code: <code><?php echo $id; ?></code>
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
}
