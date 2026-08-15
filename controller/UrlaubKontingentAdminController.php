<?php
declare(strict_types=1);

/**
 * UrlaubKontingentAdminController
 *
 * Admin-UI für das Pflegen von Urlaubskontingenten pro Jahr (T-021 Teil 3).
 *
 * Zweck:
 * - Korrektur pro Mitarbeiter/Jahr pflegen.
 * - Optionaler Anspruch-Override (anstatt `mitarbeiter.urlaub_monatsanspruch * 12`).
 * - Übertrag wird ab v8 automatisch aus dem Resturlaub des Vorjahres berechnet (Master v8, 12.3). Das Feld `uebertrag_tage` ist Legacy und wird hier nicht mehr editiert.
 */
class UrlaubKontingentAdminController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'urlaub_kontingent_admin';

    private AuthService $authService;
    private Database $datenbank;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank   = Database::getInstanz();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Kontingentverwaltung nutzen darf.
     *
     * Aktuell sind hierfür die Rollen "Chef" oder "Personalbüro" ausreichend.
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        // Primär: Rechteprüfung (rollenbasierte Rechteverwaltung)
        if ($this->authService->hatRecht('URLAUB_KONTINGENT_VERWALTEN')) {
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
        echo '<p>Sie haben keine Berechtigung, das Urlaubskontingent zu verwalten.</p>';
        return false;
    }


    /**
     * Übersicht: Mitarbeiterliste + Werte für ein Jahr.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
        if (!$this->istValidesJahr($jahr)) {
            $jahr = (int)date('Y');
        }
        $aktuellesJahr = (int)date('Y');
        $vorjahr = $aktuellesJahr - 1;

        $flashOk = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_ok'])) {
            $flashOk = (string)$_SESSION['urlaub_kontingent_admin_flash_ok'];
            unset($_SESSION['urlaub_kontingent_admin_flash_ok']);
        }

        $flashFehler = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_error'])) {
            $flashFehler = (string)$_SESSION['urlaub_kontingent_admin_flash_error'];
            unset($_SESSION['urlaub_kontingent_admin_flash_error']);
        }

        $fehlermeldung = null;
        $zeilen = [];

        try {
            $sql = 'SELECT
                        m.id,
                        m.vorname,
                        m.nachname,
                        m.aktiv,
                        m.urlaub_monatsanspruch,
                        ukj.anspruch_override_tage,
                        ukj.korrektur_tage,
                        ukj.notiz
                    FROM mitarbeiter m
                    LEFT JOIN urlaub_kontingent_jahr ukj
                        ON ukj.mitarbeiter_id = m.id
                       AND ukj.jahr = :jahr
                    ORDER BY m.aktiv DESC, m.nachname ASC, m.vorname ASC, m.id ASC';

            $zeilen = $this->datenbank->fetchAlle($sql, ['jahr' => $jahr]);

            // Resturlaub je Mitarbeiter dazuholen. Ohne diese Zahl ist die
            // Liste nur eine Stammdatenpflege – gebraucht wird sie aber vor
            // allem, um zu sehen, wer noch wie viel Urlaub hat.
            $urlaubService = UrlaubService::getInstanz();
            foreach ($zeilen as &$zeile) {
                $zeile['saldo'] = null;
                try {
                    $zeile['saldo'] = $urlaubService->berechneUrlaubssaldoFuerJahr(
                        (int)($zeile['id'] ?? 0),
                        $jahr
                    );
                } catch (Throwable $e) {
                    // Eine Zeile ohne Saldo ist kein Grund, die Liste zu verlieren.
                }
            }
            unset($zeile);
        } catch (Throwable $e) {
            $fehlermeldung = 'Die Urlaubskontingente konnten nicht geladen werden.';
            Logger::error('Fehler beim Laden der Urlaubskontingente (Admin)', [
                'jahr'      => $jahr,
                'exception' => $e->getMessage(),
            ], null, null, 'urlaub');
        }

        // Dieselbe Regel wie im Formular: formatiert wird hier, angezeigt dort.
        foreach ($zeilen as $index => $row) {
            $monats = (string)($row['urlaub_monatsanspruch'] ?? '0.00');
            $override = $row['anspruch_override_tage'] ?? null;

            $zeilen[$index]['standard_anspruch_text'] = $this->formatDecimal(((float)$monats) * 12.0);
            $zeilen[$index]['override_text'] = $override === null
                ? ''
                : $this->formatDecimal((float)$override);
            $zeilen[$index]['korrektur_text'] = $this->formatDecimal((float)($row['korrektur_tage'] ?? 0));
        }

        require __DIR__ . '/../views/urlaub/kontingent_liste.php';
    }

    /**
     * Formular für einen Mitarbeiter/Jahr.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $mitarbeiterId = isset($_GET['mitarbeiter_id']) ? (int)$_GET['mitarbeiter_id'] : 0;
        $jahr          = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');

        if ($mitarbeiterId <= 0 || !$this->istValidesJahr($jahr)) {
            http_response_code(400);
            echo '<p>Ungültige Parameter.</p>';
            return;
        }

        $flashOk = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_ok'])) {
            $flashOk = (string)$_SESSION['urlaub_kontingent_admin_flash_ok'];
            unset($_SESSION['urlaub_kontingent_admin_flash_ok']);
        }

        $flashFehler = null;
        if (isset($_SESSION['urlaub_kontingent_admin_flash_error'])) {
            $flashFehler = (string)$_SESSION['urlaub_kontingent_admin_flash_error'];
            unset($_SESSION['urlaub_kontingent_admin_flash_error']);
        }

        $fehlermeldung = null;
        if ($flashFehler !== null && $flashFehler !== '') {
            $fehlermeldung = $flashFehler;
        }
        $mitarbeiter = null;
        $kontingent = null;

        try {
            $mitarbeiter = $this->datenbank->fetchEine(
                'SELECT id, vorname, nachname, aktiv, urlaub_monatsanspruch FROM mitarbeiter WHERE id = :id LIMIT 1',
                ['id' => $mitarbeiterId]
            );
            if ($mitarbeiter === null) {
                $fehlermeldung = 'Mitarbeiter nicht gefunden.';
            }
        } catch (Throwable $e) {
            $fehlermeldung = 'Mitarbeiter konnte nicht geladen werden.';
        }

        if ($fehlermeldung === null) {
            try {
                $kontingent = $this->datenbank->fetchEine(
                    'SELECT * FROM urlaub_kontingent_jahr WHERE mitarbeiter_id = :mid AND jahr = :jahr LIMIT 1',
                    ['mid' => $mitarbeiterId, 'jahr' => $jahr]
                );
            } catch (Throwable $e) {
                $kontingent = null;
            }
        }

        $vorname  = $mitarbeiter !== null ? trim((string)($mitarbeiter['vorname'] ?? '')) : '';
        $nachname = $mitarbeiter !== null ? trim((string)($mitarbeiter['nachname'] ?? '')) : '';
        $aktiv    = $mitarbeiter !== null ? ((int)($mitarbeiter['aktiv'] ?? 0) === 1) : false;
        $monats   = $mitarbeiter !== null ? (float)($mitarbeiter['urlaub_monatsanspruch'] ?? 0.0) : 0.0;
        $standardAnspruch = $this->formatDecimal($monats * 12.0);

        $anspruchOverride = $kontingent['anspruch_override_tage'] ?? null;
        $hatAnspruchOverride = $anspruchOverride !== null;
        $korrektur = $kontingent['korrektur_tage'] ?? '0.00';
        $notiz     = $kontingent['notiz'] ?? '';

        // Festgeschriebener Übertrag (B-080): Ist er gesetzt, gewinnt er gegen
        // die Neuberechnung. Deshalb gehört er sichtbar in die Maske – sonst
        // steht dort eine Zahl, deren Herkunft niemand nachvollziehen kann.
        $uebertragTage = $kontingent['uebertrag_tage'] ?? '0.00';
        $uebertragFestAm = $kontingent['uebertrag_festgeschrieben_am'] ?? null;
        $uebertragIstFest = is_string($uebertragFestAm) && $uebertragFestAm !== '';

        $csrfToken = Csrf::token(self::CSRF_BEREICH);
        $aktuellesJahr = (int)date('Y');
        $vorjahr = $aktuellesJahr - 1;

        // Auto-Übertrag (Vorjahr -> Jahr) anzeigen, damit "Manuell" korrekt verstanden wird.
        $autoUebertragTage = null;
        $autoUebertragErmittelt = false;
        try {
            $saldoTmp = UrlaubService::getInstanz()->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr);
            if (is_array($saldoTmp) && array_key_exists("uebertrag", $saldoTmp)) {
                $autoUebertragTage = (float)str_replace(",", ".", (string)$saldoTmp["uebertrag"]);
                $autoUebertragErmittelt = true;
            }
        } catch (Throwable $e) {
            $autoUebertragTage = null;
            $autoUebertragErmittelt = false;
        }
        if ($autoUebertragTage === null) {
            $autoUebertragTage = 0.0;
        }
        $autoUebertragText = $this->formatDecimal((float)$autoUebertragTage);
        $autoUebertragHinweis = $autoUebertragErmittelt ? "" : "Auto-Wert nicht ermittelbar";
        $beispielSollTage = 5.0;
        $beispielSollText = $this->formatDecimal($beispielSollTage);
        $beispielDeltaText = $this->formatDecimal($beispielSollTage - (float)$autoUebertragTage);

        // `formatDecimal()` ist privat; die View bekommt die Zahlen deshalb
        // fertig formatiert und greift nicht auf den Controller zu.
        $anspruchOverrideText = $anspruchOverride === null
            ? ''
            : $this->formatDecimal((float)$anspruchOverride);
        $uebertragTageText = $this->formatDecimal((float)$uebertragTage);
        $korrekturText     = $this->formatDecimal((float)$korrektur);

        require __DIR__ . '/../views/urlaub/kontingent_formular.php';
    }

    /**
     * Speichern (INSERT/UPDATE via ON DUPLICATE KEY).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '<p>Ungültiger Aufruf.</p>';
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            http_response_code(400);
            echo '<p>CSRF-Token ungültig. Bitte Seite neu laden.</p>';
            return;
        }

        $mitarbeiterId = isset($_POST['mitarbeiter_id']) ? (int)$_POST['mitarbeiter_id'] : 0;
        $jahr          = isset($_POST['jahr']) ? (int)$_POST['jahr'] : 0;
        $formularAktion = isset($_POST['formular_aktion']) ? (string)$_POST['formular_aktion'] : 'speichern';

        $anspruchOverrideRaw = (string)($_POST['anspruch_override_tage'] ?? '');
        $uebertragRaw        = (string)($_POST['uebertrag_tage'] ?? '0');
        $uebertragFreigeben  = isset($_POST['uebertrag_neu_berechnen']);
        $korrekturRaw        = (string)($_POST['korrektur_tage'] ?? '0');
        $notizRaw            = trim((string)($_POST['notiz'] ?? ''));

        if ($mitarbeiterId <= 0 || !$this->istValidesJahr($jahr)) {
            $_SESSION['urlaub_kontingent_admin_flash_ok'] = '';
            header('Location: ?seite=urlaub_kontingent_admin&jahr=' . (int)date('Y'));
            return;
        }

        if ($formularAktion === 'anspruch_override_loeschen') {
            try {
                $this->datenbank->ausfuehren(
                    'UPDATE urlaub_kontingent_jahr
                        SET anspruch_override_tage = NULL
                      WHERE mitarbeiter_id = :mid
                        AND jahr = :jahr',
                    [
                        'mid'  => $mitarbeiterId,
                        'jahr' => $jahr,
                    ]
                );

                Logger::info('Urlaubsanspruch-Override gelöscht', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                ], $mitarbeiterId, null, 'urlaub');

                $_SESSION['urlaub_kontingent_admin_flash_ok'] = 'Anspruch-Override gelöscht. Es gilt wieder der Standardanspruch.';
            } catch (Throwable $e) {
                Logger::error('Fehler beim Löschen des Urlaubsanspruch-Overrides', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'jahr'           => $jahr,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'urlaub');
                $_SESSION['urlaub_kontingent_admin_flash_error'] = 'Anspruch-Override konnte nicht gelöscht werden.';
            }

            header('Location: ?seite=urlaub_kontingent_admin_bearbeiten&mitarbeiter_id=' . $mitarbeiterId . '&jahr=' . $jahr);
            exit;
        }

        $fehlermeldung = null;

        $anspruchOverride = $this->parseDecimalNullable($anspruchOverrideRaw, $fehlermeldung);
        // Beim Freigeben ist die Eingabe egal - sie wird ohnehin verworfen.
        $uebertrag        = $uebertragFreigeben ? '0.00' : $this->parseDecimalRequired($uebertragRaw, $fehlermeldung);
        $korrektur        = $this->parseDecimalRequired($korrekturRaw, $fehlermeldung);
        $notiz            = $notizRaw !== '' ? $notizRaw : null;

        if ($fehlermeldung !== null) {
            $_SESSION['urlaub_kontingent_admin_flash_ok'] = '';
            // Fehler direkt im Formular zeigen
            $_GET['mitarbeiter_id'] = (string)$mitarbeiterId;
            $_GET['jahr'] = (string)$jahr;
            // "bearbeiten" nochmals rendern, aber mit Fehlerausgabe
            // (wir speichern die Fehlermeldung kurz in Session, um keinen 4. File für Flash zu brauchen)
            $_SESSION['urlaub_kontingent_admin_flash_error'] = $fehlermeldung;
            header('Location: ?seite=urlaub_kontingent_admin_bearbeiten&mitarbeiter_id=' . $mitarbeiterId . '&jahr=' . $jahr);
            return;
        }

        try {
            // „Neu berechnen lassen" gibt den Übertrag wieder frei: Zeitstempel
            // auf NULL, Wert auf 0. Beim nächsten Aufruf rechnet
            // `UrlaubService` ihn aus dem Vorjahr und schreibt ihn erneut fest.
            $uebertragWert  = $uebertragFreigeben ? '0.00' : $uebertrag;
            $uebertragFestAm = $uebertragFreigeben ? null : date('Y-m-d H:i:s');

            $sql = 'INSERT INTO urlaub_kontingent_jahr
                        (mitarbeiter_id, jahr, anspruch_override_tage, uebertrag_tage,
                         uebertrag_festgeschrieben_am, korrektur_tage, notiz)
                    VALUES
                        (:mid, :jahr, :aot, :ueb, :ufa, :kor, :notiz)
                    ON DUPLICATE KEY UPDATE
                        anspruch_override_tage = VALUES(anspruch_override_tage),
                        uebertrag_tage = VALUES(uebertrag_tage),
                        uebertrag_festgeschrieben_am = VALUES(uebertrag_festgeschrieben_am),
                        korrektur_tage = VALUES(korrektur_tage),
                        notiz = VALUES(notiz)';

            $this->datenbank->ausfuehren($sql, [
                'mid'  => $mitarbeiterId,
                'jahr' => $jahr,
                'aot'  => $anspruchOverride,
                'ueb'  => $uebertragWert,
                'ufa'  => $uebertragFestAm,
                'kor'  => $korrektur,
                'notiz'=> $notiz,
            ]);

            Logger::info('Urlaubskontingent gespeichert', [
                'mitarbeiter_id' => $mitarbeiterId,
                'jahr'           => $jahr,
            ], $mitarbeiterId, null, 'urlaub');

            $_SESSION['urlaub_kontingent_admin_flash_ok'] = 'Gespeichert.';
        } catch (Throwable $e) {
            Logger::error('Fehler beim Speichern des Urlaubskontingents', [
                'mitarbeiter_id' => $mitarbeiterId,
                'jahr'           => $jahr,
                'exception'      => $e->getMessage(),
            ], $mitarbeiterId, null, 'urlaub');
            $_SESSION['urlaub_kontingent_admin_flash_ok'] = 'Speichern fehlgeschlagen (DB-Fehler).';
        }

        header('Location: ?seite=urlaub_kontingent_admin_bearbeiten&mitarbeiter_id=' . $mitarbeiterId . '&jahr=' . $jahr);
        exit;
    }

    private function istValidesJahr(int $jahr): bool
    {
        return $jahr >= 2000 && $jahr <= 2100;
    }

    /**
     * Formatiert für Anzeige (immer 2 Nachkommastellen, Punkt).
     */
    private function formatDecimal(float $wert): string
    {
        return number_format($wert, 2, '.', '');
    }

    /**
     * Parst Dezimal (Pflichtfeld). Akzeptiert Komma oder Punkt.
     *
     * @return string|null
     */
    private function parseDecimalRequired(string $raw, ?string &$fehlermeldung): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '0.00';
        }

        $norm = str_replace(',', '.', $raw);
        if (!is_numeric($norm)) {
            $fehlermeldung = 'Bitte nur Zahlen für Korrektur eingeben (z. B. 2.5 oder 2,5).';
            return null;
        }

        return $this->formatDecimal((float)$norm);
    }

    /**
     * Parst Dezimal (nullable). Leer => NULL.
     *
     * @return string|null
     */
    private function parseDecimalNullable(string $raw, ?string &$fehlermeldung): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $norm = str_replace(',', '.', $raw);
        if (!is_numeric($norm)) {
            $fehlermeldung = 'Bitte nur Zahlen für Anspruch-Override eingeben (z. B. 30 oder 30,0) oder Feld leer lassen.';
            return null;
        }

        return $this->formatDecimal((float)$norm);
    }
}
