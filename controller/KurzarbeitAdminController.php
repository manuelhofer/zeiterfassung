<?php
declare(strict_types=1);

/**
 * KurzarbeitAdminController
 *
 * Backend-UI für die Kurzarbeit-Planung (`kurzarbeit_plan`).
 *
 * Ziel (T-070, Teil 2a):
 * - Plan-CRUD im Backend (Zeitraum/Wochentage/Modus/Wert/Scope).
 * - Noch kein Tages-Override (folgt im nächsten Patch).
 */
class KurzarbeitAdminController
{
    /** Bereichsname für `Csrf` – siehe `core/Csrf.php`. */
    private const CSRF_BEREICH = 'kurzarbeit_admin';
    private const FLASH_OK_KEY = 'kurzarbeit_admin_flash_ok';
    private const FLASH_ERR_KEY = 'kurzarbeit_admin_flash_err';

    private AuthService $authService;
    private Database $datenbank;

    public function __construct()
    {
        $this->authService = AuthService::getInstanz();
        $this->datenbank   = Database::getInstanz();
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer die Kurzarbeit-Planung verwalten darf.
     *
     * Primär (falls vorhanden): Recht `KURZARBEIT_VERWALTEN` oder `KONFIGURATION_VERWALTEN`.
     * Legacy-Fallback: Rollen "Chef" oder "Personalbüro".
     */
    private function pruefeZugriff(): bool
    {
        if (!$this->authService->istAngemeldet()) {
            header('Location: ?seite=login');
            return false;
        }

        try {
            if (
                $this->authService->hatRecht('KURZARBEIT_VERWALTEN')
                || $this->authService->hatRecht('KONFIGURATION_VERWALTEN')
            ) {
                return true;
            }
        } catch (Throwable) {
            // Fallback unten
        }

        if (
            $this->authService->hatRolle('Chef')
            || $this->authService->hatRolle('Personalbüro')
            || $this->authService->hatRolle('Personalbuero')
        ) {
            return true;
        }

        http_response_code(403);
        echo '<p>Sie haben keine Berechtigung, Kurzarbeit zu verwalten.</p>';
        return false;
    }

    /**
     * Liest Flash-Meldungen aus der Session (und entfernt sie).
     *
     * @return array{ok:?string,err:?string}
     */
    private function holeFlash(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ok = null;
        $err = null;

        if (isset($_SESSION[self::FLASH_OK_KEY])) {
            $ok = (string)$_SESSION[self::FLASH_OK_KEY];
            unset($_SESSION[self::FLASH_OK_KEY]);
        }
        if (isset($_SESSION[self::FLASH_ERR_KEY])) {
            $err = (string)$_SESSION[self::FLASH_ERR_KEY];
            unset($_SESSION[self::FLASH_ERR_KEY]);
        }

        return ['ok' => $ok, 'err' => $err];
    }

    private function setzeHinweis(string $msg): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::FLASH_OK_KEY] = $msg;
    }

    private function setzeFehler(string $msg): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::FLASH_ERR_KEY] = $msg;
    }

    private function formatWochentageMask(int $mask): string
    {
        $mask = max(0, min(127, $mask));
        if ($mask === 31) {
            return 'Mo-Fr';
        }
        if ($mask === 127) {
            return 'Mo-So';
        }
        if ($mask === 0) {
            return '-';
        }

        $tage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $bit = 1 << $i;
            if (($mask & $bit) !== 0) {
                $out[] = $tage[$i];
            }
        }
        return implode(',', $out);
    }

    private function parseWochentageMaskAusPost(): int
    {
        $arr = $_POST['wochentage'] ?? [];
        if (!is_array($arr)) {
            $arr = [];
        }

        $mask = 0;
        foreach ($arr as $n) {
            $nn = (int)$n;
            if ($nn < 1 || $nn > 7) {
                continue;
            }
            $mask |= 1 << ($nn - 1);
        }

        if ($mask <= 0) {
            $mask = 31; // Default Mo-Fr
        }

        return max(0, min(127, $mask));
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    private function holeMitarbeiterListe(): array
    {
        try {
            $rows = $this->datenbank->fetchAlle(
                "SELECT id, vorname, nachname FROM mitarbeiter WHERE aktiv = 1 ORDER BY nachname, vorname"
            );
        } catch (Throwable) {
            $rows = [];
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $vn = trim((string)($r['vorname'] ?? ''));
            $nn = trim((string)($r['nachname'] ?? ''));
            $name = trim($nn . ', ' . $vn);
            if ($name === '') {
                $name = 'Mitarbeiter #' . $id;
            }
            $out[] = ['id' => $id, 'name' => $name];
        }
        return $out;
    }

    /**
     * Übersicht aller Kurzarbeit-Pläne.
     */
    public function index(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $flash = $this->holeFlash();
        $csrfToken = Csrf::token(self::CSRF_BEREICH);

        $plaene = [];
        $fehlermeldung = null;

        try {
            $plaene = $this->datenbank->fetchAlle(
                "SELECT p.*, m.vorname AS m_vorname, m.nachname AS m_nachname
                 FROM kurzarbeit_plan p
                 LEFT JOIN mitarbeiter m ON m.id = p.mitarbeiter_id
                 ORDER BY p.aktiv DESC, p.von_datum DESC, p.id DESC"
            );
        } catch (Throwable $e) {
            $fehlermeldung = 'Die Kurzarbeit-Pläne konnten nicht geladen werden.';
            Logger::error('Fehler beim Laden der Kurzarbeit-Pläne (Admin)', [
                'exception' => $e->getMessage(),
            ], null, null, 'kurzarbeit');
        }

        // Die Bitmaske schreibt der Controller aus, weil das seine private
        // Methode tut; die View bekommt fertigen Text.
        foreach ($plaene as $index => $plan) {
            $plaene[$index]['wochentage_text'] = $this->formatWochentageMask(
                (int)($plan['wochentage_mask'] ?? 31)
            );
        }

        require __DIR__ . '/../views/kurzarbeit/liste.php';
    }

    /**
     * Formular: Anlegen/Bearbeiten.
     */
    public function bearbeiten(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        $flash = $this->holeFlash();
        $csrfToken = Csrf::token(self::CSRF_BEREICH);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $plan = [
            'id' => 0,
            'scope' => 'mitarbeiter',
            'mitarbeiter_id' => null,
            'von_datum' => '',
            'bis_datum' => '',
            'wochentage_mask' => 31,
            'modus' => 'stunden',
            'wert' => '0.00',
            'kommentar' => '',
            'aktiv' => 1,
        ];

        if ($id > 0) {
            try {
                $row = $this->datenbank->fetchEine('SELECT * FROM kurzarbeit_plan WHERE id = :id', ['id' => $id]);
                if (is_array($row)) {
                    $plan = array_merge($plan, $row);
                } else {
                    $this->setzeFehler('Eintrag nicht gefunden.');
                    header('Location: ?seite=kurzarbeit_admin');
                    return;
                }
            } catch (Throwable $e) {
                $this->setzeFehler('Eintrag konnte nicht geladen werden.');
                Logger::error('Fehler beim Laden kurzarbeit_plan (Admin bearbeiten)', [
                    'id' => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'kurzarbeit');
                header('Location: ?seite=kurzarbeit_admin');
                return;
            }
        }

        $mitarbeiterListe = $this->holeMitarbeiterListe();

        $mask = (int)($plan['wochentage_mask'] ?? 31);
        $mask = max(0, min(127, $mask));

        require __DIR__ . '/../views/kurzarbeit/formular.php';
    }

    /**
     * POST: Speichert einen Kurzarbeit-Plan (Insert/Update).
     */
    public function speichern(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            $this->setzeFehler('CSRF-Check fehlgeschlagen. Bitte Seite neu laden.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);

        $scope = strtolower(trim((string)($_POST['scope'] ?? 'mitarbeiter')));
        if ($scope !== 'firma' && $scope !== 'mitarbeiter') {
            $scope = 'mitarbeiter';
        }

        $mitarbeiterId = (int)($_POST['mitarbeiter_id'] ?? 0);
        if ($scope === 'firma') {
            $mitarbeiterId = 0;
        }

        $von = trim((string)($_POST['von_datum'] ?? ''));
        $bis = trim((string)($_POST['bis_datum'] ?? ''));

        if ($von === '' || $bis === '') {
            $this->setzeFehler('Bitte Von/Bis-Datum angeben.');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
        if ($von > $bis) {
            $this->setzeFehler('Das Von-Datum darf nicht nach dem Bis-Datum liegen.');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
        if ($scope === 'mitarbeiter' && $mitarbeiterId <= 0) {
            $this->setzeFehler('Bitte einen Mitarbeiter auswählen (Scope = Mitarbeiter).');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }

        $mask = $this->parseWochentageMaskAusPost();

        $modus = strtolower(trim((string)($_POST['modus'] ?? 'stunden')));
        if ($modus !== 'stunden' && $modus !== 'prozent') {
            $modus = 'stunden';
        }

        $wertRaw = trim((string)($_POST['wert'] ?? '0'));
        $wert = (float)str_replace(',', '.', $wertRaw);
        if ($wert < 0) {
            $wert = 0.0;
        }

        // defensives Clamping für UI (Logik im Service clamped nochmal)
        if ($modus === 'prozent' && $wert > 100.0) {
            $wert = 100.0;
        }
        if ($modus === 'stunden' && $wert > 24.0) {
            $wert = 24.0;
        }

        $kommentar = trim((string)($_POST['kommentar'] ?? ''));
        if ($kommentar === '') {
            $kommentar = null;
        } elseif (strlen($kommentar) > 255) {
            $kommentar = substr($kommentar, 0, 255);
        }

        $aktiv = isset($_POST['aktiv']) ? 1 : 0;

        $angelegtVon = null;
        $am = $this->authService->holeAngemeldetenMitarbeiter();
        if (is_array($am)) {
            $angelegtVon = (int)($am['id'] ?? 0);
            if ($angelegtVon <= 0) {
                $angelegtVon = null;
            }
        }

        try {
            if ($id > 0) {
                $this->datenbank->ausfuehren(
                    "UPDATE kurzarbeit_plan
                     SET scope = :scope,
                         mitarbeiter_id = :mid,
                         von_datum = :von,
                         bis_datum = :bis,
                         wochentage_mask = :mask,
                         modus = :modus,
                         wert = :wert,
                         kommentar = :kommentar,
                         aktiv = :aktiv
                     WHERE id = :id",
                    [
                        'scope' => $scope,
                        'mid' => ($mitarbeiterId > 0 ? $mitarbeiterId : null),
                        'von' => $von,
                        'bis' => $bis,
                        'mask' => $mask,
                        'modus' => $modus,
                        'wert' => sprintf('%.2f', $wert),
                        'kommentar' => $kommentar,
                        'aktiv' => $aktiv,
                        'id' => $id,
                    ]
                );
            } else {
                $this->datenbank->ausfuehren(
                    "INSERT INTO kurzarbeit_plan
                        (scope, mitarbeiter_id, von_datum, bis_datum, wochentage_mask, modus, wert, kommentar, aktiv, angelegt_von_mitarbeiter_id)
                     VALUES
                        (:scope, :mid, :von, :bis, :mask, :modus, :wert, :kommentar, :aktiv, :angelegt_von)",
                    [
                        'scope' => $scope,
                        'mid' => ($mitarbeiterId > 0 ? $mitarbeiterId : null),
                        'von' => $von,
                        'bis' => $bis,
                        'mask' => $mask,
                        'modus' => $modus,
                        'wert' => sprintf('%.2f', $wert),
                        'kommentar' => $kommentar,
                        'aktiv' => $aktiv,
                        'angelegt_von' => $angelegtVon,
                    ]
                );
                $id = (int)$this->datenbank->letzteInsertId();
            }

            $this->setzeHinweis('Gespeichert.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        } catch (Throwable $e) {
            $this->setzeFehler('Speichern fehlgeschlagen.');
            Logger::error('Fehler beim Speichern kurzarbeit_plan (Admin)', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ], $angelegtVon, null, 'kurzarbeit');
            header('Location: ?seite=kurzarbeit_admin_bearbeiten' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
    }

    /**
     * POST + CSRF: toggelt Aktiv-Flag.
     */
    public function toggleAktiv(): void
    {
        if (!$this->pruefeZugriff()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        if (!Csrf::istGueltig(self::CSRF_BEREICH)) {
            $this->setzeFehler('CSRF-Check fehlgeschlagen. Bitte Seite neu laden.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $aktiv = (int)($_POST['aktiv'] ?? 0) === 1 ? 1 : 0;

        if ($id <= 0) {
            $this->setzeFehler('Ungültige ID.');
            header('Location: ?seite=kurzarbeit_admin');
            return;
        }

        try {
            $this->datenbank->ausfuehren(
                'UPDATE kurzarbeit_plan SET aktiv = :aktiv WHERE id = :id',
                ['aktiv' => $aktiv, 'id' => $id]
            );
            $this->setzeHinweis('Aktualisiert.');
        } catch (Throwable $e) {
            $this->setzeFehler('Aktualisieren fehlgeschlagen.');
            Logger::error('Fehler beim Toggle kurzarbeit_plan (Admin)', [
                'id' => $id,
                'aktiv' => $aktiv,
                'exception' => $e->getMessage(),
            ], null, null, 'kurzarbeit');
        }

        header('Location: ?seite=kurzarbeit_admin');
    }
}
