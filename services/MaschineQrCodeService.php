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

    public function __construct(?string $basisVerzeichnis = null)
    {
        $this->basisVerzeichnis = $basisVerzeichnis ?? __DIR__ . '/../public';
        $this->ladeBibliothek();
    }

    public function erzeugeMaschinenQrCode(int $maschinenId): ?string
    {
        if ($maschinenId <= 0) {
            return null;
        }

        $relativerPfad = 'uploads/maschinen_codes/maschine_' . $maschinenId . '.png';
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

    public function gebeQrPngAus(string $daten, int $groesse = 6, int $rand = 2): void
    {
        $this->erzeugePng($daten, null, $groesse, $rand);
    }

    private function ladeBibliothek(): void
    {
        require_once __DIR__ . '/phpqrcode/qrlib.php';
    }

    private function erzeugePng(string $daten, ?string $zielPfad, int $groesse = 6, int $rand = 2): void
    {
        $level = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 'M';
        $ziel = $zielPfad ?? false;

        QRcode::png($daten, $ziel, $level, $groesse, $rand);
    }
}
