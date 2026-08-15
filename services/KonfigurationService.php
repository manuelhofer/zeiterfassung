<?php
declare(strict_types=1);

/**
 * KonfigurationService
 *
 * Stellt eine zentrale Schnittstelle zur `config`-Tabelle bereit.
 *
 * Aufgabe:
 * - Key-Value-Konfiguration lesen/schreiben.
 * - Komfort-Methoden für typische Datentypen (String, int, bool).
 *
 * Wichtiger Hinweis:
 * - Anwendungslogik soll Konfigurationswerte nicht direkt aus der Datenbank lesen,
 *   sondern immer über diesen Service gehen.
 */
class KonfigurationService
{
    /** Singleton-Instanz. */
    private static ?KonfigurationService $instanz = null;

    private Database $datenbank;

    /** Einfache Laufzeit-Cache für Konfigurationswerte. @var array<string,?string> */
    private array $cache = [];

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    public static function getInstanz(): KonfigurationService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Liefert den Rohwert eines Konfigurationsschlüssels oder den Standardwert.
     */
    public function get(string $schluessel, ?string $standardWert = null): ?string
    {
        $schluessel = trim($schluessel);
        if ($schluessel === '') {
            return $standardWert;
        }

        if (array_key_exists($schluessel, $this->cache)) {
            $wert = $this->cache[$schluessel];
            return $wert !== null ? $wert : $standardWert;
        }

        try {
            $sql = 'SELECT wert
                    FROM config
                    WHERE schluessel = :schluessel
                    LIMIT 1';

            $datensatz = $this->datenbank->fetchEine($sql, ['schluessel' => $schluessel]);

            if ($datensatz === null) {
                $this->cache[$schluessel] = null;
                return $standardWert;
            }

            $wert = $datensatz['wert'];
            $wert = $wert !== null ? (string)$wert : null;

            $this->cache[$schluessel] = $wert;

            return $wert !== null ? $wert : $standardWert;
        } catch (\Throwable $e) {
            Logger::error('Fehler beim Lesen eines Konfigurationswertes', [
                'schluessel' => $schluessel,
                'exception'  => $e->getMessage(),
            ], null, null, 'config');

            return $standardWert;
        }
    }

    /**
     * Liefert einen Konfigurationswert als Integer.
     */
    public function getInt(string $schluessel, ?int $standardWert = null): ?int
    {
        $roh = $this->get($schluessel, null);
        if ($roh === null) {
            return $standardWert;
        }

        if (is_numeric($roh)) {
            return (int)$roh;
        }

        return $standardWert;
    }

    /**
     * Liefert einen Konfigurationswert als booleschen Wert.
     *
     * Typische Belegungen in der DB:
     * - '1', 'true', 'ja', 'on' → true
     * - '0', 'false', 'nein', '' → false
     */
    public function getBool(string $schluessel, bool $standardWert = false): bool
    {
        $roh = $this->get($schluessel, null);
        if ($roh === null) {
            return $standardWert;
        }

        $roh = strtolower(trim($roh));

        if (in_array($roh, ['1', 'true', 'ja', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($roh, ['0', 'false', 'nein', 'off', 'no'], true)) {
            return false;
        }

        return $standardWert;
    }

    /**
     * Schreibt oder aktualisiert einen Konfigurationswert.
     *
     * Wirft weiter, wenn das Schreiben scheitert – wie `getAlle()` und anders
     * als `get()`. Beim Lesen gibt es einen Standardwert, auf den
     * zurückgefallen werden kann; beim Schreiben gibt es keinen Ersatz für
     * „ist nicht angekommen". Ein verschluckter Schreibfehler meldet dem
     * Benutzer „Gespeichert." und lässt ihn mit dem alten Wert weiterarbeiten,
     * ohne es zu wissen – der teuerste Fehler von allen, weil ihn niemand
     * bemerkt.
     *
     * @param string      $schluessel   Eindeutiger Schlüssel
     * @param string|null $wert         Rohwert (TEXT)
     * @param string|null $typ          Optionaler Typ-Hinweis (z. B. 'string', 'int', 'bool')
     * @param string|null $beschreibung Optionale Beschreibung für das Backend
     */
    public function set(string $schluessel, ?string $wert, ?string $typ = null, ?string $beschreibung = null): void
    {
        $schluessel = trim($schluessel);
        if ($schluessel === '') {
            return;
        }

        $sql = 'INSERT INTO config (schluessel, wert, typ, beschreibung)
                VALUES (:schluessel, :wert, :typ, :beschreibung)
                ON DUPLICATE KEY UPDATE
                    wert = VALUES(wert),
                    typ = VALUES(typ),
                    beschreibung = VALUES(beschreibung)';

        $this->datenbank->ausfuehren($sql, [
            'schluessel'   => $schluessel,
            'wert'         => $wert,
            'typ'          => $typ,
            'beschreibung' => $beschreibung,
        ]);

        // Cache erst aktualisieren, wenn das Schreiben durch ist.
        $this->cache[$schluessel] = $wert;
    }

    /**
     * Liefert alle Konfigurationseinträge als Array (für Backend-Übersichten).
     *
     * Wirft weiter, wenn die Tabelle nicht lesbar ist – anders als `get()`, das
     * bewusst auf den Standardwert zurückfällt. Für einen einzelnen Schlüssel
     * gibt es diesen Rückfallwert, für die Übersicht nicht: Eine leere Liste
     * hiesse „es gibt keine Einträge", und das ist eine andere Aussage als
     * „die Einträge konnten nicht geladen werden". Der Aufrufer entscheidet,
     * wie er den Fehler zeigt.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAlle(): array
    {
        $sql = 'SELECT *
                FROM config
                ORDER BY schluessel ASC';

        return $this->datenbank->fetchAlle($sql);
    }
}
