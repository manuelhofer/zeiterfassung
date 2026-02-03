<?php
declare(strict_types=1);

/**
 * MaschineQrCodeService
 *
 * Erzeugt QR-Codes und Barcodes fuer Maschinen und kann diese direkt ausgeben.
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
        $this->ladeBibliothek();
        $this->ladeBarcodeBibliothek();
    }

    public function erzeugeMaschinenQrCode(int $maschinenId): ?string
    {
        if ($maschinenId <= 0) {
            return null;
        }

        $dateiname = 'maschine_' . $maschinenId . '.png';
        $relativerPfad = $this->relativerSpeicherPfad . '/' . $dateiname;
        $zielPfad = $this->basisVerzeichnis . '/' . $relativerPfad;

        $zielOrdner = dirname($zielPfad);
        if (!is_dir($zielOrdner)) {
            if (!mkdir($zielOrdner, 0755, true) && !is_dir($zielOrdner)) {
                return null;
            }
        }

        $this->erzeugePng((string)$maschinenId, $zielPfad);

        if (!is_file($zielPfad)) {
            return null;
        }

        return $relativerPfad;
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

    public function gebeQrPngAus(string $daten, int $groesse = 6, int $rand = 2): void
    {
        $this->erzeugePng($daten, null, $groesse, $rand);
    }

    public function gebeBarcodePngAus(string $daten, int $breiteFaktor = 2, int $hoehe = 60): void
    {
        $pngDaten = $this->erzeugeBarcodePngDaten($daten, $breiteFaktor, $hoehe);
        if ($pngDaten !== null) {
            echo $pngDaten;
        }
    }

    private function ladeBibliothek(): void
    {
        require_once __DIR__ . '/phpqrcode/qrlib.php';
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
            return '';
        }

        $maschinenQrUrl = trim($maschinenQrUrl);
        if ($maschinenQrUrl === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $maschinenQrUrl) === 1) {
            return $maschinenQrUrl;
        }

        return $this->normalisiereRelativenPfad($maschinenQrUrl, '');
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

    private function baueUrlPfad(string $dateiname): string
    {
        $relativerPfad = $this->relativerUrlPfad . '/' . $dateiname;

        if ($this->maschinenQrUrl === '') {
            return '/' . ltrim($relativerPfad, '/');
        }

        if (preg_match('~^https?://~i', $this->maschinenQrUrl) === 1) {
            return rtrim($this->maschinenQrUrl, '/') . '/' . ltrim($relativerPfad, '/');
        }

        return '/' . trim($this->maschinenQrUrl, '/') . '/' . ltrim($relativerPfad, '/');
    }

    private function erzeugePng(string $daten, ?string $zielPfad, int $groesse = 6, int $rand = 2): void
    {
        $level = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 'M';
        $ziel = $zielPfad ?? false;

        QRcode::png($daten, $ziel, $level, $groesse, $rand);
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
