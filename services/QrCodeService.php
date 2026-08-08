<?php
declare(strict_types=1);

/**
 * QrCodeService
 *
 * Erzeugt QR-Codes fuer beliebige Nutzdaten – aktuell fuer Auftragsnummern und
 * Arbeitsschritt-Codes (siehe `docs/spezifikation_auftrag_qr_laufkarte.md`).
 *
 * Zwei Ausgabewege, weil zwei Ziele bedient werden:
 * - `stelleBildBereit()` schreibt eine PNG-Datei unterhalb von `public/` fuer
 *   die Anzeige im Backend.
 * - `holeModulMatrix()` liefert die reine Modulmatrix, damit das
 *   Laufkarten-PDF die Codes als Vektor zeichnen kann. Der PDF-Writer des
 *   Projekts kann keine Bilder einbetten, und gezeichnete Rechtecke drucken
 *   ohnehin schaerfer als ein hochskaliertes Pixelbild.
 *
 * Wichtig: Der QR-Code enthaelt **nur den nackten Code** (Auftragsnummer bzw.
 * Arbeitsschritt-Code), kein Praefix und keine URL. Das Terminal liest die
 * Scan-Felder als reinen Text weiter – deshalb funktionieren gedruckte Codes
 * ohne jede Aenderung am Terminal.
 */
class QrCodeService
{
    /** Standard-Speicherort unterhalb von `public/`, per Konfiguration aenderbar. */
    private const STANDARD_REL_PFAD = 'uploads/auftrag_codes';

    private string $basisVerzeichnis;
    private string $relativerPfad;

    public function __construct(?string $basisVerzeichnis = null)
    {
        $this->basisVerzeichnis = $basisVerzeichnis ?? __DIR__ . '/../public';
        $this->relativerPfad = $this->ermittleRelativenPfad();
        $this->ladeBibliothek();
    }

    /**
     * Stellt sicher, dass fuer die Nutzdaten eine PNG-Datei existiert, und
     * liefert deren Pfad relativ zu `public/`.
     *
     * Neu erzeugt wird nur, wenn die Datei fehlt oder aelter ist als der
     * uebergebene Aenderungszeitpunkt des Datensatzes. So bekommt ein
     * umbenannter Arbeitsschritt automatisch einen neuen Code, ohne dass bei
     * jedem Seitenaufruf gerechnet wird.
     *
     * @param string      $nutzdaten   Inhalt des QR-Codes (z. B. `drehen`)
     * @param string      $dateiname   Dateiname ohne Pfad (z. B. `schritt_12.png`)
     * @param string|null $geaendertAm Aenderungszeitpunkt des Datensatzes (Y-m-d H:i:s)
     *
     * @return string|null Pfad relativ zu `public/`, oder null bei Fehler
     */
    public function stelleBildBereit(string $nutzdaten, string $dateiname, ?string $geaendertAm = null): ?string
    {
        $nutzdaten = trim($nutzdaten);
        $dateiname = basename(trim($dateiname));
        if ($nutzdaten === '' || $dateiname === '') {
            return null;
        }

        $relativerDateipfad = $this->relativerPfad . '/' . $dateiname;
        $zielPfad = $this->basisVerzeichnis . '/' . $relativerDateipfad;

        if (!$this->mussNeuErzeugtWerden($zielPfad, $geaendertAm)) {
            return $relativerDateipfad;
        }

        $zielOrdner = dirname($zielPfad);
        if (!is_dir($zielOrdner)) {
            if (!mkdir($zielOrdner, 0755, true) && !is_dir($zielOrdner)) {
                $this->protokolliereFehler('QR-Zielordner konnte nicht angelegt werden', ['ordner' => $zielOrdner]);
                return null;
            }
        }

        if (!$this->erzeugePngDatei($nutzdaten, $zielPfad)) {
            return null;
        }

        return $relativerDateipfad;
    }

    /**
     * Baut aus einem Pfad relativ zu `public/` die Browser-URL.
     */
    public function baueBildUrl(?string $relativerDateipfad): string
    {
        $relativerDateipfad = trim((string)$relativerDateipfad);
        if ($relativerDateipfad === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $relativerDateipfad) === 1) {
            return $relativerDateipfad;
        }

        $relativerDateipfad = ltrim(str_replace('\\', '/', $relativerDateipfad), '/');
        $basis = Helper::ermittleWebBasis();

        if ($basis === '') {
            return '/' . $relativerDateipfad;
        }

        if (preg_match('~^https?://~i', $basis) === 1) {
            return rtrim($basis, '/') . '/' . $relativerDateipfad;
        }

        return '/' . trim($basis, '/') . '/' . $relativerDateipfad;
    }

    /**
     * Liefert die QR-Modulmatrix als Array von Zeilen aus '0'/'1'.
     *
     * Gedacht fuer das Laufkarten-PDF: jedes '1' wird dort ein gefuelltes
     * Rechteck. Bei einem Fehler kommt ein leeres Array zurueck, damit ein
     * kaputter Code nie das ganze PDF verhindert.
     *
     * @return array<int,string>
     */
    public function holeModulMatrix(string $nutzdaten): array
    {
        $nutzdaten = trim($nutzdaten);
        if ($nutzdaten === '' || !class_exists('QRcode')) {
            return [];
        }

        try {
            $matrix = QRcode::text($nutzdaten);
        } catch (\Throwable $e) {
            $this->protokolliereFehler('QR-Matrix konnte nicht erzeugt werden', [
                'exception' => $e->getMessage(),
            ]);
            return [];
        }

        if (!is_array($matrix)) {
            return [];
        }

        $zeilen = [];
        foreach ($matrix as $zeile) {
            if (is_string($zeile) && $zeile !== '') {
                $zeilen[] = $zeile;
            }
        }

        return $zeilen;
    }

    /**
     * Dateiname fuer den QR-Code eines Auftrags.
     */
    public function dateinameAuftrag(int $auftragId): string
    {
        return 'auftrag_' . $auftragId . '.png';
    }

    /**
     * Dateiname fuer den QR-Code eines Arbeitsschritts.
     */
    public function dateinameArbeitsschritt(int $arbeitsschrittId): string
    {
        return 'schritt_' . $arbeitsschrittId . '.png';
    }

    // ------------------------------------------------------------------
    // Interna
    // ------------------------------------------------------------------

    private function mussNeuErzeugtWerden(string $zielPfad, ?string $geaendertAm): bool
    {
        if (!is_file($zielPfad)) {
            return true;
        }

        if ($geaendertAm === null || trim($geaendertAm) === '') {
            return false;
        }

        $zeitDatensatz = strtotime($geaendertAm);
        if ($zeitDatensatz === false) {
            return false;
        }

        $zeitDatei = @filemtime($zielPfad);
        if ($zeitDatei === false) {
            return true;
        }

        return $zeitDatei < $zeitDatensatz;
    }

    private function erzeugePngDatei(string $nutzdaten, string $zielPfad): bool
    {
        if (!class_exists('QRcode')) {
            $this->protokolliereFehler('QR-Bibliothek nicht verfuegbar', ['ziel' => $zielPfad]);
            return false;
        }

        try {
            // Groesse 6 / Rand 2 ergibt gut scanbare Bilder in Bildschirmgroesse.
            QRcode::png($nutzdaten, $zielPfad, QR_ECLEVEL_M, 6, 2);
        } catch (\Throwable $e) {
            $this->protokolliereFehler('QR-PNG konnte nicht erzeugt werden', [
                'ziel'      => $zielPfad,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }

        if (!is_file($zielPfad)) {
            $this->protokolliereFehler('QR-PNG wurde nicht geschrieben', ['ziel' => $zielPfad]);
            return false;
        }

        return true;
    }

    /**
     * Speicherpfad aus der Konfiguration, mit Standard als Rueckfallebene.
     */
    private function ermittleRelativenPfad(): string
    {
        $wert = null;

        if (!class_exists('KonfigurationService')) {
            $pfad = __DIR__ . '/KonfigurationService.php';
            if (is_file($pfad)) {
                require_once $pfad;
            }
        }

        if (class_exists('KonfigurationService')) {
            try {
                $wert = KonfigurationService::getInstanz()->get('auftrag_qr_rel_pfad', null);
            } catch (\Throwable $e) {
                $wert = null;
            }
        }

        if (!is_string($wert) || trim($wert) === '') {
            return self::STANDARD_REL_PFAD;
        }

        $wert = str_replace('\\', '/', trim($wert));
        $wert = ltrim($wert, '/');
        // Ein versehentlich mitgegebenes `public/` waere doppelt.
        $wert = (string)preg_replace('~^public(?:/|$)~i', '', $wert);
        $wert = trim($wert, '/');

        return $wert !== '' ? $wert : self::STANDARD_REL_PFAD;
    }

    private function ladeBibliothek(): void
    {
        if (!class_exists('QRcode')) {
            $pfad = __DIR__ . '/phpqrcode/qrlib.php';
            if (is_file($pfad)) {
                require_once $pfad;
            }
        }
    }

    /**
     * @param array<string,mixed> $kontext
     */
    private function protokolliereFehler(string $nachricht, array $kontext): void
    {
        if (class_exists('Logger')) {
            Logger::error($nachricht, $kontext, null, null, 'auftrag_qr');
        }
    }
}
