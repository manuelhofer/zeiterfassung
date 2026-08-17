<?php
declare(strict_types=1);

/**
 * MaschinenSpiegel
 *
 * Eine kleine, lokale Maschinenliste auf dem Terminal (T-138, P3).
 *
 * **Wozu:** Die Maschine gehört beim Auftragsstart zur Buchung, und ohne
 * Hauptdatenbank kann das Gerät sie nirgends nachschlagen. Der Spiegel hält
 * die Liste daneben, damit die Auswahl auch dann steht, wenn die Verbindung
 * weg ist.
 *
 * **Was drinsteht:** `maschine_id`, `name`, `aktiv` – und sonst nichts. Der
 * Name steht hier, anders als beim `MitarbeiterSpiegel`, weil eine
 * Maschinenliste ohne Namen unbedienbar ist; ein Maschinenname ist kein
 * Personendatum. `beschreibung` und `code_bild_pfad` bleiben draußen.
 *
 * **Was er ausdrücklich nicht ist: eine Türsteherin.** Ist der Spiegel leer
 * oder fehlt er, bleibt die Auswahl leer und der Auftrag startet **trotzdem**
 * – dann eben ohne Maschine. Eine verlorene Auftragszeit wäre schlimmer als
 * eine ohne Maschinenangabe. Dieselbe Haltung wie in T-125.
 *
 * **Wo er liegt:** ausschließlich in der lokalen Ausweichdatenbank des
 * Terminals. Nicht in der Hauptdatenbank – dort steht das Original, ein
 * Spiegel daneben wäre eine zweite Wahrheit über dieselbe Frage.
 *
 * Regeln dazu: `docs/fachregeln/terminal_und_offline.md`, Abschnitt 5.
 */
class MaschinenSpiegel
{
    /**
     * Wie alt der Spiegel werden darf, bevor er aufgefrischt wird.
     *
     * Dieselben fünf Minuten wie beim Mitarbeiterspiegel. Maschinen ändern
     * sich noch seltener als Chips – kürzer wäre reine Last, länger spart
     * nichts, was auffiele.
     */
    private const AUFFRISCHUNG_SEKUNDEN = 300;

    private static ?MaschinenSpiegel $instanz = null;

    private Database $datenbank;

    /** Einmal je Prozess: Steht die Tabelle? `null` = noch nicht geprüft. */
    private ?bool $schemaBereit = null;

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    public static function getInstanz(): MaschinenSpiegel
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Frischt den Spiegel auf, wenn er alt genug ist.
     *
     * Wird bei jedem Terminal-Aufruf angestoßen und tut die meiste Zeit
     * nichts. Fehler sind nie fatal: Ein Terminal, dessen Spiegel nicht
     * aktualisiert werden kann, arbeitet mit dem alten weiter – und wenn es
     * gar keinen gibt, ohne.
     */
    public function aktualisiereWennFaellig(): void
    {
        $pdo = $this->verbindungOderNull();
        if ($pdo === null) {
            return;
        }

        // Ohne Hauptdatenbank gibt es nichts zu spiegeln.
        try {
            if (!$this->datenbank->istHauptdatenbankVerfuegbar()) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        try {
            if (!$this->istFaellig($pdo)) {
                return;
            }

            // Spaltenweise lesen, nicht `SELECT *`: Am Terminal ist das
            // Leserecht spaltenweise vergeben (T-101).
            $zeilen = $this->datenbank->fetchAlle(
                'SELECT id, name, aktiv FROM maschine'
            );

            $this->schreibeSpiegel($pdo, $zeilen);
        } catch (\Throwable $e) {
            Logger::warn(
                'Maschinen-Spiegel konnte nicht aufgefrischt werden',
                ['exception' => $e->getMessage()],
                null,
                Helper::terminalId(),
                'maschinen_spiegel'
            );
        }
    }

    /**
     * Die aktiven Maschinen, wie das Gerät sie kennt.
     *
     * Liefert eine **leere Liste**, solange kein brauchbarer Spiegel da ist.
     * Das ist kein Fehler, sondern die Antwort „ich weiß nichts": Der Aufrufer
     * zeigt dann keine Auswahl, und die Maschinen-ID lässt sich weiterhin
     * scannen oder tippen.
     *
     * @return array<int,array{id:int, name:string}> nach Namen sortiert
     */
    public function holeAktiveMaschinen(): array
    {
        $pdo = $this->verbindungOderNull();
        if ($pdo === null) {
            return [];
        }

        try {
            if (!$this->ensureSchema($pdo)) {
                return [];
            }

            $statement = $pdo->query(
                'SELECT maschine_id, name FROM maschine_spiegel
                  WHERE aktiv = 1
                  ORDER BY name ASC, maschine_id ASC'
            );

            if ($statement === false) {
                return [];
            }

            $liste = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $zeile) {
                $id = (int)($zeile['maschine_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $liste[] = ['id' => $id, 'name' => (string)($zeile['name'] ?? '')];
            }

            return $liste;
        } catch (\Throwable $e) {
            Logger::warn(
                'Maschinen-Spiegel konnte nicht gelesen werden',
                ['exception' => $e->getMessage()],
                null,
                Helper::terminalId(),
                'maschinen_spiegel'
            );

            return [];
        }
    }

    /**
     * Die lokale Ausweichdatenbank – und nur die.
     *
     * Kein Rückfall auf die Hauptdatenbank, anders als bei der Queue: Dort
     * steht die Tabelle `maschine` selbst. Ein Spiegel daneben wäre eine
     * zweite Antwort auf dieselbe Frage, und die driftet.
     */
    private function verbindungOderNull(): ?\PDO
    {
        if (!Helper::istTerminalInstallation()) {
            return null;
        }

        try {
            $offline = $this->datenbank->getOfflineVerbindung();
        } catch (\Throwable $e) {
            return null;
        }

        return $offline instanceof \PDO ? $offline : null;
    }

    /**
     * Ist der Spiegel älter als `AUFFRISCHUNG_SEKUNDEN` (oder gar nicht da)?
     */
    private function istFaellig(\PDO $pdo): bool
    {
        if (!$this->ensureSchema($pdo)) {
            return false;
        }

        $statement = $pdo->query('SELECT MAX(aktualisiert_am) FROM maschine_spiegel');
        if ($statement === false) {
            return true;
        }

        $letzte = $statement->fetchColumn();
        if (!is_string($letzte) || trim($letzte) === '') {
            return true;
        }

        try {
            $stand = new \DateTimeImmutable($letzte);
        } catch (\Throwable $e) {
            return true;
        }

        return (time() - $stand->getTimestamp()) >= self::AUFFRISCHUNG_SEKUNDEN;
    }

    /**
     * Schreibt den Spiegel neu.
     *
     * Ganz oder gar nicht: Ein halb geschriebener Spiegel zeigt eine
     * Maschinenliste, in der die gesuchte Maschine fehlt – und wer sie nicht
     * findet, bucht ohne. Deshalb Löschen und Füllen in **einer** Transaktion,
     * und erst danach zählt er als aufgefrischt.
     *
     * @param array<int,array<string,mixed>> $zeilen
     */
    private function schreibeSpiegel(\PDO $pdo, array $zeilen): void
    {
        $jetzt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM maschine_spiegel');

            $einfuegen = $pdo->prepare(
                'INSERT INTO maschine_spiegel
                    (maschine_id, name, aktiv, aktualisiert_am)
                 VALUES (:id, :name, :aktiv, :aktualisiert_am)'
            );

            foreach ($zeilen as $zeile) {
                $id = (int)($zeile['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $einfuegen->bindValue(':id', $id, \PDO::PARAM_INT);
                $einfuegen->bindValue(':name', (string)($zeile['name'] ?? ''), \PDO::PARAM_STR);
                $einfuegen->bindValue(':aktiv', ((int)($zeile['aktiv'] ?? 0) === 1) ? 1 : 0, \PDO::PARAM_INT);
                $einfuegen->bindValue(':aktualisiert_am', $jetzt, \PDO::PARAM_STR);

                $einfuegen->execute();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Legt die Tabelle an, falls sie fehlt.
     *
     * Dieselbe Bauart wie in `MitarbeiterSpiegel::ensureSchema()`: Die lokale
     * Datenbank eines Terminals kann aus einer älteren Installation stammen,
     * und `sql/offline_db_schema.sql` läuft dort nicht noch einmal.
     */
    private function ensureSchema(\PDO $pdo): bool
    {
        if ($this->schemaBereit !== null) {
            return $this->schemaBereit;
        }

        try {
            $probe = $pdo->query('SELECT 1 FROM maschine_spiegel LIMIT 1');
            if ($probe !== false) {
                $this->schemaBereit = true;

                return true;
            }
        } catch (\Throwable $e) {
            // Tabelle fehlt vermutlich – wird gleich angelegt.
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS maschine_spiegel (\n" .
                "  maschine_id BIGINT UNSIGNED NOT NULL,\n" .
                "  name VARCHAR(150) NOT NULL,\n" .
                "  aktiv TINYINT(1) NOT NULL DEFAULT 1,\n" .
                "  aktualisiert_am DATETIME NOT NULL,\n" .
                "  PRIMARY KEY (maschine_id)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );

            $this->schemaBereit = true;
        } catch (\Throwable $e) {
            Logger::warn(
                'Tabelle maschine_spiegel konnte nicht angelegt werden',
                ['exception' => $e->getMessage()],
                null,
                Helper::terminalId(),
                'maschinen_spiegel'
            );

            $this->schemaBereit = false;
        }

        return $this->schemaBereit;
    }
}
