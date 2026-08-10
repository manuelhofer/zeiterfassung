<?php
declare(strict_types=1);

/**
 * MaschineQrCodeService
 *
 * Erzeugt Strichcodes (Code 128) fuer Maschinen und kann sie direkt ausgeben.
 *
 * **Der Name ist Historie.** Frueher waren die Maschinen-Codes QR-Codes; seit
 * der Umstellung auf Code 128 (P-2026-08-08-24) ist alles im Projekt derselbe
 * Codetyp, weil in der Halle 1D-Handscanner im Einsatz sind – Begruendung im
 * Kopf von `services/BarcodeService.php`. Der QR-Zweig wurde in
 * P-2026-08-10-05 entfernt: Er lief nur noch als Rueckfall und lieferte dann
 * ein Bild, das an diesen Scannern gar kein Code ist.
 *
 * Umbenannt wird die Klasse bewusst spaeter – der Name steht auch in
 * Konfigurationsschluesseln (`maschinen_qr_url`, `maschinen_qr_rel_pfad`), und
 * die gehoeren in einen eigenen Patch mit Migration.
 */
class MaschineQrCodeService
{
    private string $basisVerzeichnis;
    private string $relativerSpeicherPfad;
    private string $relativerUrlPfad;
    private string $maschinenQrUrl;

    public function __construct(?string $basisVerzeichnis = null)
    {
        $this->basisVerzeichnis = $basisVerzeichnis ?? __DIR__ . '/../public';
        $konfiguration = $this->ladeKonfiguration();
        $this->relativerSpeicherPfad = $this->ermittleRelativenSpeicherPfad($konfiguration);
        $this->relativerUrlPfad = $this->ermittleRelativenUrlPfad($konfiguration, $this->relativerSpeicherPfad);
        $this->maschinenQrUrl = $this->ermittleMaschinenQrUrl($konfiguration);
        $this->ladeBarcodeBibliothek();
    }

    public function erzeugeMaschinenBarcode(int $maschinenId, string $maschinenName): ?string
    {
        if ($maschinenId <= 0) {
            return null;
        }

        $maschinenName = trim($maschinenName);
        $barcodeInhalt = $maschinenId . '_' . $maschinenName;
        $dateiname = 'maschine_' . $maschinenId . '_barcode.png';
        $relativerPfad = $this->relativerSpeicherPfad . '/' . $dateiname;
        $zielPfad = $this->basisVerzeichnis . '/' . $relativerPfad;

        $zielOrdner = dirname($zielPfad);
        if (!is_dir($zielOrdner)) {
            if (!mkdir($zielOrdner, 0755, true) && !is_dir($zielOrdner)) {
                return null;
            }
        }

        if (!$this->erzeugeBarcodePng($barcodeInhalt, $zielPfad)) {
            return null;
        }

        if (!is_file($zielPfad)) {
            return null;
        }

        return $relativerPfad;
    }

    public function gebeBarcodePngAus(string $daten, int $breiteFaktor = 2, int $hoehe = 60): void
    {
        $pngDaten = $this->erzeugeBarcodePngDaten($daten, $breiteFaktor, $hoehe);
        if ($pngDaten !== null) {
            echo $pngDaten;
        }
    }

    private function ladeBarcodeBibliothek(): void
    {
        $basisPfad = __DIR__ . '/barcode/Picqer/Barcode';
        $dateien = [
            $basisPfad . '/Barcode.php',
            $basisPfad . '/BarcodeBar.php',
            $basisPfad . '/BarcodeGenerator.php',
            $basisPfad . '/BarcodeGeneratorPNG.php',
            $basisPfad . '/Exceptions/BarcodeException.php',
            $basisPfad . '/Exceptions/InvalidCharacterException.php',
            $basisPfad . '/Exceptions/InvalidLengthException.php',
            $basisPfad . '/Exceptions/InvalidCheckDigitException.php',
            $basisPfad . '/Exceptions/UnknownTypeException.php',
            $basisPfad . '/Renderers/RendererInterface.php',
            $basisPfad . '/Renderers/PngRenderer.php',
            $basisPfad . '/Types/TypeInterface.php',
            $basisPfad . '/Types/TypeCode128.php',
        ];

        foreach ($dateien as $datei) {
            if (is_file($datei)) {
                require_once $datei;
            }
        }
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function ermittleRelativenSpeicherPfad(array $konfiguration): string
    {
        $standardPfad = 'uploads/maschinen_codes';
        $konfigPfad = $this->waehleRelativenPfad($konfiguration);
        return $this->normalisiereRelativenPfad($konfigPfad, $standardPfad);
    }

    /**
     * @return array<string,mixed>
     */
    private function ladeKonfiguration(): array
    {
        if (!class_exists('KonfigurationService')) {
            $pfad = __DIR__ . '/KonfigurationService.php';
            if (is_file($pfad)) {
                require_once $pfad;
            }
        }

        if (!class_exists('KonfigurationService')) {
            return [];
        }

        $service = KonfigurationService::getInstanz();

        return [
            'maschinen_qr_rel_pfad' => $service->get('maschinen_qr_rel_pfad', null),
            'maschinen_qr_url' => $service->get('maschinen_qr_url', null),
            'maschinen_qr_base_url' => $service->get('maschinen_qr_base_url', null),
        ];
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function ermittleRelativenUrlPfad(array $konfiguration, string $fallback): string
    {
        $konfigPfad = $this->waehleRelativenPfad($konfiguration);
        return $this->normalisiereRelativenPfad($konfigPfad, $fallback);
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function ermittleMaschinenQrUrl(array $konfiguration): string
    {
        $maschinenQrUrl = $konfiguration['maschinen_qr_url'] ?? '';
        if (!$this->istNichtLeererString($maschinenQrUrl)) {
            $maschinenQrUrl = $konfiguration['maschinen_qr_base_url'] ?? '';
        }

        if (!is_string($maschinenQrUrl)) {
            return $this->ermittleWebBasis();
        }

        $maschinenQrUrl = trim($maschinenQrUrl);

        // Nicht gesetzt -> Basis automatisch aus der Installation ableiten.
        if ($maschinenQrUrl === '') {
            return $this->ermittleWebBasis();
        }

        // Ausdruecklich '/' -> Domain-Root, also bewusst keine Automatik.
        if ($maschinenQrUrl === '/') {
            return '';
        }

        if (preg_match('~^https?://~i', $maschinenQrUrl) === 1) {
            return $maschinenQrUrl;
        }

        return $this->normalisiereRelativenPfad($maschinenQrUrl, '');
    }

    /**
     * Ermittelt den Web-Basispfad der laufenden Installation.
     *
     * Hintergrund: Die Bilder liegen immer unter `public/<maschinen_qr_rel_pfad>`.
     * Damit ist die Browser-URL vollstaendig bestimmt, sobald bekannt ist, unter
     * welchem Pfad `public/` im Web haengt. Genau das wird ermittelt, damit
     * niemand denselben Pfad ein zweites Mal von Hand pflegen muss.
     *
     * Die eigentliche Ableitung steht in `Helper::ermittleWebBasis()`, weil sie
     * auch vom `QrCodeService` (Auftraege/Arbeitsschritte) gebraucht wird – zwei
     * Kopien derselben Logik waren schon einmal die Ursache eines Fehlers
     * (siehe B-089).
     */
    private function ermittleWebBasis(): string
    {
        return Helper::ermittleWebBasis();
    }

    private function normalisiereRelativenPfad($konfigPfad, string $fallback): string
    {
        if (!is_string($konfigPfad)) {
            return $fallback;
        }

        $konfigPfad = str_replace('\\', '/', $konfigPfad);
        $konfigPfad = trim($konfigPfad);
        if ($konfigPfad === '') {
            return $fallback;
        }

        $konfigPfad = ltrim($konfigPfad, '/');
        $konfigPfad = preg_replace('~^(?:zeiterfassung/)?public(?:/|$)~i', '', $konfigPfad);
        $konfigPfad = trim((string)$konfigPfad, '/');

        if ($konfigPfad === '') {
            return $fallback;
        }

        return $konfigPfad;
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function waehleRelativenPfad(array $konfiguration): ?string
    {
        $neuerPfad = $konfiguration['maschinen_qr_rel_pfad'] ?? null;
        if ($this->istNichtLeererString($neuerPfad)) {
            return $neuerPfad;
        }

        $alterPfad = $konfiguration['qr_maschinen_rel_pfad'] ?? null;
        if ($this->istNichtLeererString($alterPfad)) {
            return $alterPfad;
        }

        return null;
    }

    private function istNichtLeererString($wert): bool
    {
        return is_string($wert) && trim($wert) !== '';
    }

    /**
     * Baut aus einem gespeicherten `code_bild_pfad` die Browser-URL.
     *
     * Der gespeicherte Pfad ist relativ zu `public/` (z. B.
     * `uploads/maschinen_codes/maschine_5_barcode.png`). Davor kommt die Basis
     * aus `ermittleMaschinenQrUrl()` - entweder ausdruecklich konfiguriert oder
     * automatisch aus der Installation abgeleitet.
     *
     * Rueckgabe: leerer String, wenn kein Bild hinterlegt ist.
     */
    public function baueBildUrl(string $gespeicherterPfad): string
    {
        $gespeicherterPfad = trim($gespeicherterPfad);
        if ($gespeicherterPfad === '') {
            return '';
        }

        // Bereits eine vollstaendige URL (z. B. Bilder auf einem anderen Host).
        if (preg_match('~^https?://~i', $gespeicherterPfad) === 1) {
            return $gespeicherterPfad;
        }

        $relativerPfad = $this->normalisiereRelativenPfad($gespeicherterPfad, '');
        if ($relativerPfad === '') {
            return '';
        }

        return $this->baueUrlPfad($relativerPfad);
    }

    private function baueUrlPfad(string $relativerPfad): string
    {
        $relativerPfad = ltrim($relativerPfad, '/');

        if ($this->maschinenQrUrl === '') {
            return '/' . $relativerPfad;
        }

        if (preg_match('~^https?://~i', $this->maschinenQrUrl) === 1) {
            return rtrim($this->maschinenQrUrl, '/') . '/' . $relativerPfad;
        }

        return '/' . trim($this->maschinenQrUrl, '/') . '/' . $relativerPfad;
    }

    private function erzeugeBarcodePng(string $daten, string $zielPfad, int $breiteFaktor = 2, int $hoehe = 60): bool
    {
        $pngDaten = $this->erzeugeBarcodePngDaten($daten, $breiteFaktor, $hoehe);
        if ($pngDaten === null) {
            return false;
        }

        return file_put_contents($zielPfad, $pngDaten) !== false;
    }

    private function erzeugeBarcodePngDaten(string $daten, int $breiteFaktor = 2, int $hoehe = 60): ?string
    {
        if (!class_exists('Picqer\\Barcode\\BarcodeGeneratorPNG')) {
            return null;
        }

        if (!function_exists('imagecreate') && !extension_loaded('imagick')) {
            return null;
        }

        try {
            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            return $generator->getBarcode(
                $daten,
                Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128,
                $breiteFaktor,
                $hoehe
            );
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Erzeugen des Maschinen-Barcodes', [
                    'daten' => $daten,
                    'exception' => $e->getMessage(),
                ], null, null, 'maschine');
            }
        }

        return null;
    }
}
