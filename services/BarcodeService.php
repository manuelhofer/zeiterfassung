<?php
declare(strict_types=1);

/**
 * BarcodeService
 *
 * Erzeugt Strichcodes (Code 128) fuer Auftragsnummern, Arbeitsschritt-Codes und
 * Katalogeintraege – siehe `docs/spezifikation_auftrag_barcode_laufkarte.md`.
 *
 * **Warum Code 128 und nicht QR:** Im Betrieb sind 1D-Handscanner im Einsatz,
 * und die Maschinen-Codes des Projekts sind ebenfalls Code 128
 * (`MaschineQrCodeService::erzeugeBarcodePng`). Ein einziger Codetyp bedeutet:
 * ein Scannertyp, keine Sonderfaelle. Code 128 kann Buchstaben, Ziffern und
 * Sonderzeichen, deshalb steht im Strichcode weiterhin **der Code selbst**
 * (z. B. `fraesen`) – es braucht keine kuenstliche Nummer.
 *
 * Zwei Ausgabewege, weil zwei Ziele bedient werden:
 * - `stelleBildBereit()` schreibt eine PNG-Datei unterhalb von `public/` fuer
 *   die Anzeige im Backend.
 * - `holeBalken()` liefert die reine Balkenfolge, damit die PDFs den Code als
 *   Vektor zeichnen koennen. Der PDF-Writer des Projekts kann keine Bilder
 *   einbetten, und gezeichnete Balken drucken ohnehin schaerfer.
 *
 * Der Inhalt ist immer **nur der nackte Code** – kein Praefix, keine URL. Das
 * Terminal liest seine Scan-Felder als reinen Text, deshalb funktionieren
 * gedruckte Codes ohne jede Aenderung am Terminal.
 */
class BarcodeService
{
    /** Standard-Speicherort unterhalb von `public/`, per Konfiguration aenderbar. */
    private const STANDARD_REL_PFAD = 'uploads/auftrag_codes';

    /** Modulbreite und Hoehe der erzeugten PNG-Dateien (Bildschirmanzeige). */
    private const PNG_MODULBREITE = 2;
    private const PNG_HOEHE = 60;

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
     * @param string      $nutzdaten   Inhalt des Codes (z. B. `fraesen`)
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
                $this->protokolliereFehler('Zielordner fuer Strichcodes konnte nicht angelegt werden', ['ordner' => $zielOrdner]);
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
     * Liefert die Balkenfolge eines Code-128-Strichcodes.
     *
     * Rueckgabe:
     * - `breite`  Gesamtbreite in Modulen (Grundlage fuer die Skalierung)
     * - `balken`  Liste dunkler Balken als `['start' => Modul, 'breite' => Module]`
     *
     * Gedacht fuer die PDFs: jeder Balken wird dort ein gefuelltes Rechteck.
     * Bei einem Fehler kommt eine leere Liste zurueck, damit ein einzelner
     * kaputter Code nie das ganze Dokument verhindert.
     *
     * @return array{breite:int,balken:array<int,array{start:int,breite:int}>}
     */
    public function holeBalken(string $nutzdaten): array
    {
        $leer = ['breite' => 0, 'balken' => []];

        $nutzdaten = trim($nutzdaten);
        if ($nutzdaten === '' || !class_exists('Picqer\\Barcode\\Types\\TypeCode128')) {
            return $leer;
        }

        try {
            $typ = new Picqer\Barcode\Types\TypeCode128();
            $barcode = $typ->getBarcode($nutzdaten);
        } catch (\Throwable $e) {
            $this->protokolliereFehler('Strichcode konnte nicht berechnet werden', [
                'exception' => $e->getMessage(),
            ]);
            return $leer;
        }

        $balken = [];
        $position = 0;

        foreach ($barcode->getBars() as $bar) {
            $breite = $bar->getWidth();
            if ($bar->isBar() && $breite > 0) {
                $balken[] = ['start' => $position, 'breite' => $breite];
            }
            $position += $breite;
        }

        return ['breite' => max(1, $barcode->getWidth()), 'balken' => $balken];
    }

    /**
     * Dateiname fuer den Strichcode eines Auftrags.
     */
    public function dateinameAuftrag(int $auftragId): string
    {
        return 'auftrag_' . $auftragId . '.png';
    }

    /**
     * Dateiname fuer den Strichcode eines Arbeitsschritts am Auftrag.
     */
    public function dateinameArbeitsschritt(int $arbeitsschrittId): string
    {
        return 'schritt_' . $arbeitsschrittId . '.png';
    }

    /**
     * Dateiname fuer den Strichcode eines Katalogeintrags.
     */
    public function dateinameKatalog(int $katalogId): string
    {
        return 'katalog_' . $katalogId . '.png';
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
        if (!class_exists('Picqer\\Barcode\\BarcodeGeneratorPNG')) {
            $this->protokolliereFehler('Strichcode-Bibliothek nicht verfuegbar', ['ziel' => $zielPfad]);
            return false;
        }

        if (!function_exists('imagecreate') && !extension_loaded('imagick')) {
            $this->protokolliereFehler('Keine Bildunterstuetzung (GD/Imagick) vorhanden', ['ziel' => $zielPfad]);
            return false;
        }

        try {
            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            $pngDaten = $generator->getBarcode(
                $nutzdaten,
                Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128,
                self::PNG_MODULBREITE,
                self::PNG_HOEHE
            );
        } catch (\Throwable $e) {
            $this->protokolliereFehler('Strichcode-PNG konnte nicht erzeugt werden', [
                'ziel'      => $zielPfad,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }

        if (!is_string($pngDaten) || $pngDaten === '') {
            $this->protokolliereFehler('Strichcode-PNG war leer', ['ziel' => $zielPfad]);
            return false;
        }

        if (file_put_contents($zielPfad, $pngDaten) === false) {
            $this->protokolliereFehler('Strichcode-PNG konnte nicht geschrieben werden', ['ziel' => $zielPfad]);
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
                $konfig = KonfigurationService::getInstanz();
                $wert = $konfig->get('auftrag_code_rel_pfad', null);

                // Rueckfall auf den alten Schluessel: Der Wert hiess bis
                // P-2026-08-08-24 `auftrag_qr_rel_pfad`. Installationen, die die
                // Migration noch nicht eingespielt haben, sollen trotzdem ihren
                // eingestellten Pfad behalten statt auf den Standard zu fallen.
                if (!is_string($wert) || trim($wert) === '') {
                    $wert = $konfig->get('auftrag_qr_rel_pfad', null);
                }
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
        if (class_exists('Picqer\\Barcode\\BarcodeGeneratorPNG')) {
            return;
        }

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
     * @param array<string,mixed> $kontext
     */
    private function protokolliereFehler(string $nachricht, array $kontext): void
    {
        if (class_exists('Logger')) {
            Logger::error($nachricht, $kontext, null, null, 'barcode');
        }
    }
}
