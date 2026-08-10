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

    /**
     * Maskiert einen Wert für die Verwendung in einem SQL-Stringliteral –
     * **ohne** die umschließenden Anführungszeichen.
     *
     * Warum es diese Funktion überhaupt gibt: Die Offline-Queue speichert
     * fertigen SQL-Text, der später ausgeführt wird. Dort sind Prepared
     * Statements nicht möglich, also muss von Hand maskiert werden – und dann
     * bitte an genau einer Stelle.
     *
     * Warum der Backslash zuerst kommt: MySQL und MariaDB behandeln `\` in der
     * Standardeinstellung als Fluchtzeichen (`NO_BACKSLASH_ESCAPES` ist aus).
     * Ein Wert, der auf `\` endet, würde sonst das schließende
     * Anführungszeichen maskieren, das Literal offen lassen und alles
     * Nachfolgende zu SQL machen. Die Reihenfolge ist wesentlich: erst
     * Backslashes verdoppeln, dann Anführungszeichen – umgekehrt würden die
     * frisch erzeugten Backslashes gleich wieder verdoppelt.
     */
    public static function sqlEscape(string $wert): string
    {
        return str_replace(['\\', "'"], ['\\\\', "''"], $wert);
    }

    /**
     * Liefert einen Wert als vollständiges SQL-Stringliteral, inklusive
     * Anführungszeichen. Siehe `sqlEscape()` für die Begründung.
     */
    public static function sqlLiteral(string $wert): string
    {
        return "'" . self::sqlEscape($wert) . "'";
    }

    /**
     * Ermittelt den Web-Basispfad der laufenden Installation.
     *
     * Wird gebraucht, um aus einem Pfad unterhalb von `public/` eine URL zu
     * bauen, die im Browser funktioniert – egal ob die Anwendung direkt auf der
     * Domain-Wurzel oder in einem Unterordner (`/zeiterfassung`) hängt.
     *
     * Reihenfolge:
     * 1. `app.base_url` aus der Konfigurationsdatei, falls gesetzt,
     * 2. sonst das Verzeichnis des laufenden Skripts (`SCRIPT_NAME`),
     * 3. sonst leer = Domain-Wurzel.
     *
     * Rückgabe ohne führenden/abschließenden Schraegstrich (z. B.
     * `zeiterfassung`) oder eine vollständige URL, wenn `base_url` eine solche
     * ist. Leerer String bedeutet Domain-Wurzel.
     */
    public static function ermittleWebBasis(): string
    {
        $basisAusKonfig = self::holeBaseUrlAusKonfigdatei();
        if ($basisAusKonfig !== '') {
            if (preg_match('~^https?://~i', $basisAusKonfig) === 1) {
                return rtrim($basisAusKonfig, '/');
            }

            return trim(str_replace('\\', '/', $basisAusKonfig), '/');
        }

        $skriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (is_string($skriptName) && $skriptName !== '') {
            $verzeichnis = str_replace('\\', '/', dirname($skriptName));
            $verzeichnis = trim($verzeichnis, '/');

            if ($verzeichnis !== '' && $verzeichnis !== '.') {
                return $verzeichnis;
            }
        }

        return '';
    }

    /**
     * Liest `app.base_url` aus der Konfigurationsdatei.
     */
    private static function holeBaseUrlAusKonfigdatei(): string
    {
        $pfad = __DIR__ . '/../config/config.php';
        if (!is_file($pfad)) {
            return '';
        }

        /** @var array<string,mixed> $konfig */
        $konfig = require $pfad;
        $baseUrl = $konfig['app']['base_url'] ?? '';

        return is_string($baseUrl) ? trim($baseUrl) : '';
    }
}
