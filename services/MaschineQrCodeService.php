<?php
declare(strict_types=1);

/**
 * MaschineQrCodeService
 *
 * Erzeugt QR-Codes fuer Maschinen und kann diese direkt ausgeben.
 */
class MaschineQrCodeService
{
    private string $basisVerzeichnis;
    private string $relativerSpeicherPfad;
    private string $relativerUrlPfad;
    private string $basisUrl;

    public function __construct(?string $basisVerzeichnis = null)
    {
        $this->basisVerzeichnis = $basisVerzeichnis ?? __DIR__ . '/../public';
        $konfiguration = $this->ladeKonfiguration();
        $this->relativerSpeicherPfad = $this->ermittleRelativenSpeicherPfad($konfiguration);
        $this->relativerUrlPfad = $this->ermittleRelativenUrlPfad($konfiguration, $this->relativerSpeicherPfad);
        $this->basisUrl = $this->ermittleBasisUrl($konfiguration);
        $this->ladeBibliothek();
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

        return $this->baueUrlPfad($dateiname);
    }

    public function gebeQrPngAus(string $daten, int $groesse = 6, int $rand = 2): void
    {
        $this->erzeugePng($daten, null, $groesse, $rand);
    }

    private function ladeBibliothek(): void
    {
        require_once __DIR__ . '/phpqrcode/qrlib.php';
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function ermittleRelativenSpeicherPfad(array $konfiguration): string
    {
        $standardPfad = 'uploads/maschinen_codes';
        $konfigPfad = $this->waehleRelativenPfad($konfiguration);
        return $this->bereinigeRelativenPfad($konfigPfad, $standardPfad);
    }

    /**
     * @return array<string,mixed>
     */
    private function ladeKonfiguration(): array
    {
        if (!class_exists('KonfigurationService')) {
            return [];
        }

        $service = KonfigurationService::getInstanz();

        return [
            'maschinen_qr_rel_pfad' => $service->get('maschinen_qr_rel_pfad', null),
            'qr_maschinen_rel_pfad' => $service->get('qr_maschinen_rel_pfad', null),
            'maschinen_qr_base_url' => $service->get('maschinen_qr_base_url', null),
        ];
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function ermittleRelativenUrlPfad(array $konfiguration, string $fallback): string
    {
        $konfigPfad = $this->waehleRelativenPfad($konfiguration);
        return $this->bereinigeRelativenPfad($konfigPfad, $fallback);
    }

    /**
     * @param array<string,mixed> $konfiguration
     */
    private function ermittleBasisUrl(array $konfiguration): string
    {
        $basisUrl = $konfiguration['maschinen_qr_base_url'] ?? '';
        return is_string($basisUrl) ? trim($basisUrl) : '';
    }

    private function bereinigeRelativenPfad($konfigPfad, string $fallback): string
    {
        if (!is_string($konfigPfad)) {
            return $fallback;
        }

        $konfigPfad = trim($konfigPfad);
        if ($konfigPfad === '') {
            return $fallback;
        }

        $konfigPfad = trim($konfigPfad, '/');
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

        if ($this->basisUrl === '') {
            return '/' . ltrim($relativerPfad, '/');
        }

        if (preg_match('~^https?://~i', $this->basisUrl) === 1) {
            return rtrim($this->basisUrl, '/') . '/' . ltrim($relativerPfad, '/');
        }

        return '/' . trim($this->basisUrl, '/') . '/' . ltrim($relativerPfad, '/');
    }

    private function erzeugePng(string $daten, ?string $zielPfad, int $groesse = 6, int $rand = 2): void
    {
        $level = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 'M';
        $ziel = $zielPfad ?? false;

        QRcode::png($daten, $ziel, $level, $groesse, $rand);
    }
}
