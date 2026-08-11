<?php
declare(strict_types=1);

/**
 * ArbeitsschrittKatalogController
 *
 * Verwaltung der zentralen, auftragsunabhängigen Standard-Arbeitsschritte
 * (siehe `docs/spezifikation_auftrag_barcode_laufkarte.md`, Abschnitt 4a).
 *
 * Gedanke dahinter: `fraesen` ist bei jedem Auftrag dasselbe `fraesen`. Die
 * Arbeitsvorbereitung pflegt den Schritt einmal, druckt seinen Strichcode so oft
 * aus wie nötig und hängt ihn an die Maschinen. Gescannt wird dann
 * Auftrag (von der Laufkarte) + Arbeitsschritt (von der Maschine).
 *
 * Der Katalog ist eine **Vorlage**, keine Buchungsquelle: Beim Scannen entsteht
 * wie bisher ein Eintrag in `auftrag_arbeitsschritt`; gezählt wird über
 * `auftragszeit`. Ein nicht katalogisierter Code wird weiterhin angenommen.
 */
class ArbeitsschrittKatalogController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'arbeitsschritt_katalog';

    private AuthService $authService;
    private Database $db;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->db = Database::getInstanz();
    }

    /**
     * Liste aller Katalogeinträge.
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
        $csrf = Csrf::token(self::CSRF_BEREICH);

        $flashOk = isset($_SESSION['katalog_flash_ok']) ? (string)$_SESSION['katalog_flash_ok'] : '';
        $flashFehler = isset($_SESSION['katalog_flash_fehler']) ? (string)$_SESSION['katalog_flash_fehler'] : '';
        unset($_SESSION['katalog_flash_ok'], $_SESSION['katalog_flash_fehler']);

        require __DIR__ . '/../views/arbeitsschritt_katalog/liste.php';
    }

    /**
     * Formular für einen neuen Katalogeintrag.
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
     * Formular für einen vorhandenen Katalogeintrag.
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

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
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

        // Codes sind betriebsweit eindeutig - ein an der Maschine hängender
        // Code muss überall dasselbe bedeuten.
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
     * „20-mal fraesen für 20 Fräsmaschinen“.
     *
     * Bewusst ohne Verwaltungsrecht: Einen Code nachdrucken, weil die Karte an
     * der Maschine unleserlich geworden ist, muss ohne Änderungsrecht gehen.
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
        // Die Einzelwerte leitet die View selbst aus `$eintrag` ab; das Token
        // kommt von hier, weil der Bereichsname dem Controller gehört.
        $csrf = Csrf::token(self::CSRF_BEREICH);

        require __DIR__ . '/../views/arbeitsschritt_katalog/formular.php';
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
     * Gleiche Prüfung wie im `AuftragController`: Wer Aufträge pflegen darf,
     * pflegt auch die Vorlagen dafür. Ein eigenes Recht für den Katalog wäre
     * zusätzliche Verwaltung ohne erkennbaren Nutzen.
     *
     * Die Legacy-Rollen werden mitgeprüft, weil das im gesamten Projekt so
     * gehandhabt wird (15 Controller) – bestehende Installationen sollen ohne
     * Rechtevergabe weiterarbeiten können.
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
        require __DIR__ . '/../views/arbeitsschritt_katalog/kein_recht.php';
    }

    /**
     * @param array<string,mixed> $kontext
     */
    private function protokolliere(string $nachricht, array $kontext): void
    {
        Logger::error($nachricht, $kontext, null, null, 'arbeitsschritt_katalog');
    }
}
