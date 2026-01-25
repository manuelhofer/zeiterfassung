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

        try {
            $sql = 'SELECT bf.*, a.name AS abteilung_name
                    FROM betriebsferien bf
                    LEFT JOIN abteilung a ON a.id = bf.abteilung_id
                    ORDER BY bf.von_datum ASC, bf.bis_datum ASC, bf.id ASC';

            $eintraege = $this->datenbank->fetchAlle($sql);
        } catch (\Throwable $e) {
            $fehlermeldung = 'Die Betriebsferien konnten nicht geladen werden.';
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden der Betriebsferien im Admin-Bereich', [
                    'exception' => $e->getMessage(),
                ], null, null, 'betriebsferien');
            }
        }

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Betriebsferien</h2>
            <p>
                <a href="?seite=betriebsferien_admin_bearbeiten">Neue Betriebsferien anlegen</a>
            </p>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($eintraege) === 0): ?>
                <p>Es sind derzeit keine Betriebsferien hinterlegt.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Von</th>
                            <th>Bis</th>
                            <th>Abteilung</th>
                            <th>Beschreibung</th>
                            <th>Aktiv</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eintraege as $bf): ?>
                            <?php
                                $id = (int)($bf['id'] ?? 0);
                                $von = (string)($bf['von_datum'] ?? '');
                                $bis = (string)($bf['bis_datum'] ?? '');
                                $abteilung = (string)($bf['abteilung_name'] ?? '');
                                $beschreibung = (string)($bf['beschreibung'] ?? '');
                                $aktiv = (int)($bf['aktiv'] ?? 0) === 1;
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $abteilung !== '' ? htmlspecialchars($abteilung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '(global)'; ?></td>
                                <td><?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                                <td>
                                    <a href="?seite=betriebsferien_admin_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p style="margin-top: 1rem;">
                <small>
                    Hinweis: Global bedeutet <code>abteilung_id = NULL</code> (gilt für alle).
                </small>
            </p>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden einer Betriebsferien-Zeile', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'betriebsferien');
            }
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
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Speichern von Betriebsferien', [
                    'id'        => $id,
                    'eintrag'   => $eintrag,
                    'exception' => $e->getMessage(),
                ], $id > 0 ? $id : null, null, 'betriebsferien');
            }

            $this->renderFormular($eintrag, $abteilungen, $fehlermeldung);
        }
    }

    /**
     * @param array<string,mixed> $eintrag
     * @param array<int,array<string,mixed>> $abteilungen
     */
    private function renderFormular(array $eintrag, array $abteilungen, ?string $fehlermeldung): void
    {
        require __DIR__ . '/../views/layout/header.php';

        $id          = (int)($eintrag['id'] ?? 0);
        $von         = (string)($eintrag['von_datum'] ?? '');
        $bis         = (string)($eintrag['bis_datum'] ?? '');
        $beschreibung = (string)($eintrag['beschreibung'] ?? '');
        $aktiv       = (int)($eintrag['aktiv'] ?? 0) === 1;

        $abteilungId = $eintrag['abteilung_id'] ?? null;
        if ($abteilungId !== null) {
            $abteilungId = (int)$abteilungId;
            if ($abteilungId <= 0) {
                $abteilungId = null;
            }
        }
        ?>
        <section>
            <h2><?php echo $id > 0 ? 'Betriebsferien bearbeiten' : 'Betriebsferien anlegen'; ?></h2>

            <?php if (!empty($fehlermeldung)): ?>
                <div class="fehlermeldung">
                    <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=betriebsferien_admin_speichern">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div style="margin-bottom: 0.75rem;">
                    <label for="von_datum"><strong>Von</strong></label><br>
                    <input type="date" id="von_datum" name="von_datum" value="<?php echo htmlspecialchars($von, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label for="bis_datum"><strong>Bis</strong></label><br>
                    <input type="date" id="bis_datum" name="bis_datum" value="<?php echo htmlspecialchars($bis, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label for="abteilung_id"><strong>Abteilung</strong></label><br>
                    <select id="abteilung_id" name="abteilung_id" style="width: 100%; max-width: 520px;">
                        <option value="">(global)</option>
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
                    <input type="text" id="beschreibung" name="beschreibung" value="<?php echo htmlspecialchars($beschreibung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width: 100%; max-width: 820px;">
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                        Aktiv
                    </label>
                </div>

                <div style="display:flex; gap: 1rem; align-items:center;">
                    <button type="submit">Speichern</button>
                    <a href="?seite=betriebsferien_admin">Abbrechen</a>
                </div>
            </form>
        </section>
        <?php

        require __DIR__ . '/../views/layout/footer.php';
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
