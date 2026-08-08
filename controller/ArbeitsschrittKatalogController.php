<?php
declare(strict_types=1);

/**
 * ArbeitsschrittKatalogController
 *
 * Verwaltung der zentralen, auftragsunabhaengigen Standard-Arbeitsschritte
 * (siehe `docs/spezifikation_auftrag_barcode_laufkarte.md`, Abschnitt 4a).
 *
 * Gedanke dahinter: `fraesen` ist bei jedem Auftrag dasselbe `fraesen`. Die
 * Arbeitsvorbereitung pflegt den Schritt einmal, druckt seinen Strichcode so oft
 * aus wie noetig und haengt ihn an die Maschinen. Gescannt wird dann
 * Auftrag (von der Laufkarte) + Arbeitsschritt (von der Maschine).
 *
 * Der Katalog ist eine **Vorlage**, keine Buchungsquelle: Beim Scannen entsteht
 * wie bisher ein Eintrag in `auftrag_arbeitsschritt`; gezaehlt wird ueber
 * `auftragszeit`. Ein nicht katalogisierter Code wird weiterhin angenommen.
 */
class ArbeitsschrittKatalogController
{
    private const CSRF_KEY = 'arbeitsschritt_katalog_csrf_token';

    private AuthService $authService;
    private Database $db;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->db = Database::getInstanz();
    }

    /**
     * Liste aller Katalogeintraege.
     * Route: ?seite=arbeitsschritt_katalog
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $eintraege = [];
        $fehlermeldung = null;

        try {
            $eintraege = $this->db->fetchAlle(
                'SELECT * FROM arbeitsschritt_katalog ORDER BY aktiv DESC, sort_order ASC, code ASC'
            );

            $codeService = new BarcodeService();
            foreach ($eintraege as $index => $eintrag) {
                $eintraege[$index]['code_url'] = $codeService->baueBildUrl(
                    $codeService->stelleBildBereit(
                        trim((string)($eintrag['code'] ?? '')),
                        $codeService->dateinameKatalog((int)($eintrag['id'] ?? 0)),
                        isset($eintrag['geaendert_am']) ? (string)$eintrag['geaendert_am'] : null
                    )
                );
            }
        } catch (\Throwable $e) {
            $fehlermeldung = 'Der Arbeitsschritt-Katalog konnte nicht geladen werden.';
            $this->protokolliere('Katalog konnte nicht geladen werden', ['exception' => $e->getMessage()]);
        }

        $darfVerwalten = $this->darfVerwalten();
        $csrf = $this->holeOderErzeugeCsrfToken();

        $flashOk = isset($_SESSION['katalog_flash_ok']) ? (string)$_SESSION['katalog_flash_ok'] : '';
        $flashFehler = isset($_SESSION['katalog_flash_fehler']) ? (string)$_SESSION['katalog_flash_fehler'] : '';
        unset($_SESSION['katalog_flash_ok'], $_SESSION['katalog_flash_fehler']);

        $esc = static function ($wert): string {
            return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Arbeitsschritt-Katalog</h2>

            <p>
                Hier stehen die immer wiederkehrenden Arbeitsschritte – einmal gepflegt,
                fuer jeden Auftrag nutzbar. Der Strichcode gehoert an die Maschine:
                Wer mehrere Fraesmaschinen hat, druckt <code>fraesen</code> mehrfach aus
                und haengt den Code an jede davon.
            </p>

            <?php if ($flashOk !== ''): ?>
                <p style="padding:8px;border:1px solid #9ad29a;background:#e9f7e9;"><?php echo $esc($flashOk); ?></p>
            <?php endif; ?>
            <?php if ($flashFehler !== ''): ?>
                <p style="padding:8px;border:1px solid #e0a0a0;background:#fbeaea;"><?php echo $esc($flashFehler); ?></p>
            <?php endif; ?>
            <?php if ($fehlermeldung !== null): ?>
                <div class="fehlermeldung"><?php echo $esc($fehlermeldung); ?></div>
            <?php endif; ?>

            <?php if ($darfVerwalten): ?>
                <p>
                    <a href="?seite=arbeitsschritt_katalog_neu" style="display:inline-block;padding:6px 12px;border:1px solid #2b6cb0;border-radius:4px;background:#2b6cb0;color:#fff;text-decoration:none;">
                        + Arbeitsschritt hinzufuegen
                    </a>
                    <?php if (count($eintraege) > 0): ?>
                        <a href="?seite=arbeitsschritt_katalog_blatt" target="_blank" style="margin-left:1rem;">Alle Strichcodes als Druckblatt (PDF)</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (count($eintraege) === 0): ?>
                <p>Noch keine Arbeitsschritte im Katalog.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Bezeichnung</th>
                            <th>Strichcode</th>
                            <th>Sortierung</th>
                            <th>Aktiv</th>
                            <th>Drucken</th>
                            <?php if ($darfVerwalten): ?><th>Aktion</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eintraege as $eintrag): ?>
                            <?php
                                $id    = (int)($eintrag['id'] ?? 0);
                                $code  = (string)($eintrag['code'] ?? '');
                                $bez   = trim((string)($eintrag['bezeichnung'] ?? ''));
                                $codeUrl = (string)($eintrag['code_url'] ?? '');
                                $aktiv = (int)($eintrag['aktiv'] ?? 0) === 1;
                            ?>
                            <tr<?php echo $aktiv ? '' : ' style="color:#888;"'; ?>>
                                <td><code><?php echo $esc($code); ?></code></td>
                                <td><?php echo $bez !== '' ? $esc($bez) : '-'; ?></td>
                                <td>
                                    <?php if ($codeUrl !== ''): ?>
                                        <img src="<?php echo $esc($codeUrl); ?>" alt="Strichcode <?php echo $esc($code); ?>" style="height:44px;width:auto;image-rendering:pixelated;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int)($eintrag['sort_order'] ?? 0); ?></td>
                                <td><?php echo $aktiv ? 'Ja' : 'Nein'; ?></td>
                                <td>
                                    <form method="get" action="" target="_blank" style="white-space:nowrap;">
                                        <input type="hidden" name="seite" value="arbeitsschritt_katalog_blatt">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <input type="number" name="anzahl" value="1" min="1" max="200" style="width:64px;" title="Wie viele Karten dieses Codes?">
                                        <button type="submit">x drucken</button>
                                    </form>
                                </td>
                                <?php if ($darfVerwalten): ?>
                                    <td><a href="?seite=arbeitsschritt_katalog_bearbeiten&amp;id=<?php echo $id; ?>">Bearbeiten</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><small>
                    Der Katalog schreibt nichts vor: Ein am Terminal gescannter Code, der hier
                    nicht steht, wird weiterhin angenommen und gezaehlt.
                </small></p>
            <?php endif; ?>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Formular fuer einen neuen Katalogeintrag.
     * Route: ?seite=arbeitsschritt_katalog_neu
     */
    public function neu(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        $this->renderFormular([
            'id'          => 0,
            'code'        => '',
            'bezeichnung' => '',
            'sort_order'  => $this->naechsteSortierung(),
            'aktiv'       => 1,
        ], null);
    }

    /**
     * Formular fuer einen vorhandenen Katalogeintrag.
     * Route: ?seite=arbeitsschritt_katalog_bearbeiten&id=...
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ?seite=arbeitsschritt_katalog');
            return;
        }

        $eintrag = null;
        try {
            $eintrag = $this->db->fetchEine('SELECT * FROM arbeitsschritt_katalog WHERE id = :id LIMIT 1', ['id' => $id]);
        } catch (\Throwable $e) {
            $this->protokolliere('Katalogeintrag konnte nicht geladen werden', ['id' => $id, 'exception' => $e->getMessage()]);
        }

        if (!is_array($eintrag)) {
            $_SESSION['katalog_flash_fehler'] = 'Der Arbeitsschritt wurde nicht gefunden.';
            header('Location: ?seite=arbeitsschritt_katalog');
            return;
        }

        $this->renderFormular($eintrag, null);
    }

    /**
     * Speichert einen Katalogeintrag.
     * Route: ?seite=arbeitsschritt_katalog_speichern (POST)
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (!$this->darfVerwalten()) {
            $this->zeigeKeinRecht();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=arbeitsschritt_katalog');
            return;
        }

        $csrf = $this->holeOderErzeugeCsrfToken();
        if ($csrf === '' || !hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
            $_SESSION['katalog_flash_fehler'] = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            header('Location: ?seite=arbeitsschritt_katalog');
            return;
        }

        $id          = (int)($_POST['id'] ?? 0);
        $code        = trim((string)($_POST['code'] ?? ''));
        $bezeichnung = trim((string)($_POST['bezeichnung'] ?? ''));
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $aktiv       = isset($_POST['aktiv']) ? 1 : 0;

        $daten = [
            'id'          => $id,
            'code'        => $code,
            'bezeichnung' => $bezeichnung,
            'sort_order'  => $sortOrder,
            'aktiv'       => $aktiv,
        ];

        if ($code === '') {
            $this->renderFormular($daten, 'Bitte einen Code angeben.');
            return;
        }

        if (mb_strlen($code) > 100) {
            $this->renderFormular($daten, 'Der Code darf hoechstens 100 Zeichen lang sein.');
            return;
        }

        // Codes sind betriebsweit eindeutig - ein an der Maschine haengender
        // Code muss ueberall dasselbe bedeuten.
        try {
            $vorhanden = $this->db->fetchEine(
                'SELECT id FROM arbeitsschritt_katalog WHERE code = :code AND id <> :id LIMIT 1',
                ['code' => $code, 'id' => $id]
            );

            if (is_array($vorhanden)) {
                $this->renderFormular($daten, 'Den Code "' . $code . '" gibt es im Katalog bereits.');
                return;
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Pruefung auf doppelten Katalog-Code fehlgeschlagen', ['exception' => $e->getMessage()]);
        }

        try {
            if ($id > 0) {
                $this->db->ausfuehren(
                    'UPDATE arbeitsschritt_katalog
                        SET code = :code, bezeichnung = :bez, sort_order = :sort, aktiv = :aktiv
                      WHERE id = :id',
                    [
                        'code'  => $code,
                        'bez'   => $bezeichnung !== '' ? $bezeichnung : null,
                        'sort'  => $sortOrder,
                        'aktiv' => $aktiv,
                        'id'    => $id,
                    ]
                );
                $_SESSION['katalog_flash_ok'] = 'Der Arbeitsschritt wurde gespeichert.';
            } else {
                $this->db->ausfuehren(
                    'INSERT INTO arbeitsschritt_katalog (code, bezeichnung, sort_order, aktiv)
                     VALUES (:code, :bez, :sort, :aktiv)',
                    [
                        'code'  => $code,
                        'bez'   => $bezeichnung !== '' ? $bezeichnung : null,
                        'sort'  => $sortOrder,
                        'aktiv' => $aktiv,
                    ]
                );
                $_SESSION['katalog_flash_ok'] = 'Der Arbeitsschritt "' . $code . '" wurde im Katalog angelegt.';
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Katalogeintrag konnte nicht gespeichert werden', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);
            $this->renderFormular($daten, 'Der Arbeitsschritt konnte nicht gespeichert werden.');
            return;
        }

        header('Location: ?seite=arbeitsschritt_katalog');
    }

    /**
     * Druckblatt mit Strichcode-Karten zum Ausschneiden.
     * Route: ?seite=arbeitsschritt_katalog_blatt[&id=…][&anzahl=…]
     *
     * Ohne Parameter: alle aktiven Katalogschritte, eine Karte je Schritt.
     * Mit `id` und `anzahl`: derselbe Schritt mehrfach – der Fall
     * „20-mal fraesen fuer 20 Fraesmaschinen“.
     *
     * Bewusst ohne Verwaltungsrecht: Einen Code nachdrucken, weil die Karte an
     * der Maschine unleserlich geworden ist, muss ohne Aenderungsrecht gehen.
     */
    public function blatt(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $id     = (int)($_GET['id'] ?? 0);
        $anzahl = (int)($_GET['anzahl'] ?? 1);

        // Obergrenze, damit ein vertippter Wert nicht hunderte Seiten erzeugt.
        if ($anzahl < 1) {
            $anzahl = 1;
        }
        if ($anzahl > 200) {
            $anzahl = 200;
        }

        $karten = [];
        $dateiname = 'arbeitsschritte';

        try {
            if ($id > 0) {
                $eintrag = $this->db->fetchEine(
                    'SELECT code, bezeichnung FROM arbeitsschritt_katalog WHERE id = :id LIMIT 1',
                    ['id' => $id]
                );

                if (!is_array($eintrag)) {
                    $_SESSION['katalog_flash_fehler'] = 'Der Arbeitsschritt wurde nicht gefunden.';
                    header('Location: ?seite=arbeitsschritt_katalog');
                    return;
                }

                for ($i = 0; $i < $anzahl; $i++) {
                    $karten[] = $eintrag;
                }

                $dateiname = (string)($eintrag['code'] ?? 'arbeitsschritt');
            } else {
                $karten = $this->db->fetchAlle(
                    'SELECT code, bezeichnung FROM arbeitsschritt_katalog
                      WHERE aktiv = 1
                      ORDER BY sort_order ASC, code ASC'
                );
                $dateiname = 'katalog';
            }
        } catch (\Throwable $e) {
            $this->protokolliere('Druckblatt konnte nicht geladen werden', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);
            $_SESSION['katalog_flash_fehler'] = 'Das Druckblatt konnte nicht erzeugt werden.';
            header('Location: ?seite=arbeitsschritt_katalog');
            return;
        }

        $pdf = PDFService::getInstanz()->erzeugeArbeitsschrittKartenPdf($karten);

        if ($pdf === '') {
            $_SESSION['katalog_flash_fehler'] = 'Das Druckblatt konnte nicht erzeugt werden.';
            header('Location: ?seite=arbeitsschritt_katalog');
            return;
        }

        $dateiname = preg_replace('~[^A-Za-z0-9_.-]+~', '_', $dateiname);
        $dateiname = trim((string)$dateiname, '_');
        if ($dateiname === '') {
            $dateiname = 'arbeitsschritte';
        }

        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: inline; filename="arbeitsschritt_' . $dateiname . '.pdf"');

        echo $pdf;
    }

    // ------------------------------------------------------------------
    // Interna
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $eintrag
     */
    private function renderFormular(array $eintrag, ?string $fehlermeldung): void
    {
        $id          = (int)($eintrag['id'] ?? 0);
        $code        = (string)($eintrag['code'] ?? '');
        $bezeichnung = (string)($eintrag['bezeichnung'] ?? '');
        $sortOrder   = (int)($eintrag['sort_order'] ?? 0);
        $aktiv       = (int)($eintrag['aktiv'] ?? 1) === 1;
        $csrf        = $this->holeOderErzeugeCsrfToken();

        $esc = static function ($wert): string {
            return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2><?php echo $id > 0 ? 'Arbeitsschritt bearbeiten' : 'Arbeitsschritt anlegen'; ?></h2>

            <p><a href="?seite=arbeitsschritt_katalog">&laquo; Zurueck zum Katalog</a></p>

            <?php if (is_string($fehlermeldung) && $fehlermeldung !== ''): ?>
                <div style="margin-bottom:1rem;padding:8px;border:1px solid #e0a0a0;background:#fbeaea;">
                    <?php echo $esc($fehlermeldung); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?seite=arbeitsschritt_katalog_speichern">
                <input type="hidden" name="csrf_token" value="<?php echo $esc($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div style="margin-bottom:0.75rem;">
                    <label for="code"><strong>Code</strong></label><br>
                    <input type="text" id="code" name="code" required maxlength="100"
                           value="<?php echo $esc($code); ?>" style="width:100%;max-width:260px;">
                    <br><small>
                        Steht im Strichcode und wird am Terminal gescannt, z. B. <code>fraesen</code>.
                        Kurz und eindeutig halten – der Code taucht in allen Auswertungen auf.
                        <?php if ($id > 0): ?>
                            <br><strong>Achtung:</strong> Eine Aenderung erzeugt einen neuen Strichcode.
                            Bereits an Maschinen haengende Ausdrucke werden dadurch ungueltig.
                        <?php endif; ?>
                    </small>
                </div>

                <div style="margin-bottom:0.75rem;">
                    <label for="bezeichnung"><strong>Bezeichnung</strong></label><br>
                    <input type="text" id="bezeichnung" name="bezeichnung" maxlength="255"
                           value="<?php echo $esc($bezeichnung); ?>" style="width:100%;max-width:480px;">
                    <br><small>Klartext fuer Ausdruck und Auswertung, z. B. „Fraesen“.</small>
                </div>

                <div style="margin-bottom:0.75rem;">
                    <label for="sort_order"><strong>Sortierung</strong></label><br>
                    <input type="number" id="sort_order" name="sort_order" value="<?php echo $sortOrder; ?>" style="width:100px;">
                    <br><small>Kleinere Zahl steht weiter oben.</small>
                </div>

                <div style="margin-bottom:1rem;">
                    <label>
                        <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
                        Aktiv
                    </label>
                    <br><small>Inaktive Schritte stehen nicht mehr zur Auswahl und nicht auf dem Druckblatt. Bereits erfasste Buchungen bleiben unberuehrt.</small>
                </div>

                <button type="submit">Speichern</button>
                <a href="?seite=arbeitsschritt_katalog" style="margin-left:1rem;">Abbrechen</a>
            </form>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        return true;
    }

    /**
     * Gleiche Pruefung wie im `AuftragController`: Wer Auftraege pflegen darf,
     * pflegt auch die Vorlagen dafuer. Ein eigenes Recht fuer den Katalog waere
     * zusaetzliche Verwaltung ohne erkennbaren Nutzen.
     *
     * Die Legacy-Rollen werden mitgeprueft, weil das im gesamten Projekt so
     * gehandhabt wird (15 Controller) – bestehende Installationen sollen ohne
     * Rechtevergabe weiterarbeiten koennen.
     */
    private function darfVerwalten(): bool
    {
        $legacyAdmin = (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        );

        return $this->authService->hatRecht('AUFTRAEGE_VERWALTEN') || $legacyAdmin;
    }

    private function holeOderErzeugeCsrfToken(): string
    {
        $token = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $token = bin2hex((string)mt_rand());
            }
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return (string)$token;
    }

    private function naechsteSortierung(): int
    {
        try {
            $row = $this->db->fetchEine('SELECT MAX(sort_order) AS maxwert FROM arbeitsschritt_katalog');
            if (is_array($row)) {
                return ((int)($row['maxwert'] ?? 0)) + 10;
            }
        } catch (\Throwable $e) {
            // Sortierung ist Komfort, kein Grund das Formular zu verweigern.
        }

        return 10;
    }

    private function zeigeKeinRecht(): void
    {
        require __DIR__ . '/../views/layout/header.php';
        ?>
        <section>
            <h2>Keine Berechtigung</h2>
            <p>Zum Pflegen des Arbeitsschritt-Katalogs wird das Recht <code>AUFTRAEGE_VERWALTEN</code> benoetigt.</p>
            <p><a href="?seite=arbeitsschritt_katalog">&laquo; Zurueck zum Katalog</a></p>
        </section>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * @param array<string,mixed> $kontext
     */
    private function protokolliere(string $nachricht, array $kontext): void
    {
        if (class_exists('Logger')) {
            Logger::error($nachricht, $kontext, null, null, 'arbeitsschritt_katalog');
        }
    }
}
