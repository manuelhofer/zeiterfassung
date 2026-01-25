<?php
declare(strict_types=1);

/**
 * Helper
 *
 * Sammlung kleiner, zentraler Hilfsfunktionen für das Projekt.
 *
 * Ziel:
 * - Duplikate bei immer wiederkehrenden Aufgaben vermeiden (z. B. Lesen von Request-Werten).
 * - An einer Stelle definieren, wie Eingaben „bereinigt“ werden.
 * - Nur leichte, gut lesbare Utilities – keine Abhängigkeit auf Fremdbibliotheken.
 */
class Helper
{
    /**
     * Liefert true, wenn die aktuelle Anfrage per POST gesendet wurde.
     */
    public static function istPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /**
     * Liest einen String-Wert aus einem Array (z. B. $_POST, $_GET) und trimmt ihn.
     *
     * @param array<string,mixed> $quelle
     */
    public static function leseString(array $quelle, string $schluessel, string $standardWert = ''): string
    {
        if (!array_key_exists($schluessel, $quelle)) {
            return $standardWert;
        }

        $wert = $quelle[$schluessel];

        if ($wert === null) {
            return $standardWert;
        }

        if (is_string($wert)) {
            return trim($wert);
        }

        if (is_scalar($wert)) {
            return trim((string)$wert);
        }

        return $standardWert;
    }

    /**
     * Liest einen Integer-Wert aus einem Array (z. B. $_POST, $_GET).
     *
     * @param array<string,mixed> $quelle
     */
    public static function leseInt(array $quelle, string $schluessel, ?int $standardWert = null): ?int
    {
        if (!array_key_exists($schluessel, $quelle)) {
            return $standardWert;
        }

        $wert = $quelle[$schluessel];

        if (is_int($wert)) {
            return $wert;
        }

        if (is_numeric($wert)) {
            return (int)$wert;
        }

        return $standardWert;
    }

    /**
     * Liest einen booleschen Wert aus einem Array (z. B. Checkbox).
     *
     * Übliche Konvention:
     * - Existiert der Schlüssel und ist nicht leer → true.
     * - Andernfalls → false.
     *
     * @param array<string,mixed> $quelle
     */
    public static function leseBool(array $quelle, string $schluessel): bool
    {
        if (!array_key_exists($schluessel, $quelle)) {
            return false;
        }

        $wert = $quelle[$schluessel];

        if (is_bool($wert)) {
            return $wert;
        }

        if (is_string($wert)) {
            $wert = trim($wert);
            if ($wert === '') {
                return false;
            }

            $klein = strtolower($wert);
            if (in_array($klein, ['1', 'true', 'ja', 'on', 'yes'], true)) {
                return true;
            }

            return false;
        }

        if (is_int($wert)) {
            return $wert !== 0;
        }

        return false;
    }

    /**
     * Wandelt ein Datum aus einem Formularfeld (Format: YYYY-MM-DD) in ein DateTimeImmutable-Objekt um.
     */
    public static function parseDatum(string $datum): ?\DateTimeImmutable
    {
        $datum = trim($datum);
        if ($datum === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($datum);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Formatiert ein DateTimeInterface zu einem Datum im Format YYYY-MM-DD.
     */
    public static function formatDatum(\DateTimeInterface $datum): string
    {
        return $datum->format('Y-m-d');
    }

    /**
     * Formatiert ein DateTimeInterface zu einem Datum/Zeit-String im Format YYYY-MM-DD HH:MM:SS.
     */
    public static function formatDatumZeit(\DateTimeInterface $datum): string
    {
        return $datum->format('Y-m-d H:i:s');
    }
}
