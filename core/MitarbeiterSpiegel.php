<?php
declare(strict_types=1);

/**
 * MitarbeiterSpiegel
 *
 * Eine kleine, lokale Liste der Berechtigten auf dem Terminal (T-125).
 *
 * **Wozu:** Ohne sie merkt das Gerät erst beim Einspielen – oft Stunden
 * später –, dass ein Chip niemandem gehört. Der Mensch, der ihn drangehalten
 * hat, ist dann längst weg, und im Backend liegt ein Eintrag, den jemand von
 * Hand nachtragen muss. Mit ihr steht die Auskunft in dem Moment auf dem
 * Bildschirm, in dem sie jemandem nützt.
 *
 * **Was drinsteht:** `mitarbeiter_id`, `personalnummer`, `rfid_code`, `aktiv` –
 * und sonst nichts. Keine Namen, keine Passwörter, keine Kontostände. Wird das
 * Gerät gestohlen, sind es Nummern ohne Zuordnung. (Dass die Zugangsdaten zur
 * Hauptdatenbank ohnehin auf dem Gerät liegen, macht der Spiegel weder besser
 * noch schlimmer.)
 *
 * **Was er ausdrücklich nicht ist: eine Türsteherin.** Ein Chip, der nicht in
 * der Liste steht, wird offline **trotzdem** angenommen. Sonst verlöre jemand
 * mit frisch ausgegebenem Chip seine Ankunftszeit, weil der Spiegel zwei
 * Stunden alt ist. Der Eintrag geht wie gehabt mit RFID-Auflösung in die Queue
 * und taucht im Zweifel im Backend auf.
 *
 * **Wo er liegt:** ausschließlich in der lokalen Ausweichdatenbank des
 * Terminals. Nicht in der Hauptdatenbank – dort steht das Original, ein
 * Spiegel daneben wäre eine zweite Wahrheit über dieselbe Frage.
 *
 * Regeln dazu: `docs/fachregeln/terminal_und_offline.md`, Abschnitt 5.
 */
class MitarbeiterSpiegel
{
    /** Antworten von `pruefeChip()`. */
    public const CHIP_BEKANNT      = 'bekannt';
    public const CHIP_UNBEKANNT    = 'unbekannt';
    public const CHIP_INAKTIV      = 'inaktiv';

    /**
     * Es gibt keinen brauchbaren Spiegel (keine Ausweichdatenbank, Tabelle
     * fehlt oder noch nie gefüllt). Das ist **keine** Aussage über den Chip –
     * wer diesen Wert bekommt, sagt nichts, statt etwas Falsches zu sagen.
     */
    public const CHIP_OHNE_AUSKUNFT = 'ohne_auskunft';

    /**
     * Wie alt der Spiegel werden darf, bevor er aufgefrischt wird.
     *
     * Fünf Minuten sind ein Kompromiss: Ein frisch ausgegebener Chip ist
     * spätestens dann bekannt, und ein Terminal, das alle paar Sekunden eine
     * Seite baut, liest die Mitarbeitertabelle trotzdem nur alle 300 Sekunden.
     */
    private const AUFFRISCHUNG_SEKUNDEN = 300;

    private static ?MitarbeiterSpiegel $instanz = null;

    private Database $datenbank;

    /** Einmal je Prozess: Steht die Tabelle? `null` = noch nicht geprüft. */
    private ?bool $schemaBereit = null;

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    public static function getInstanz(): MitarbeiterSpiegel
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

            $zeilen = $this->datenbank->fetchAlle(
                'SELECT id, personalnummer, rfid_code, aktiv FROM mitarbeiter'
            );

            $this->schreibeSpiegel($pdo, $zeilen);
        } catch (\Throwable $e) {
            Logger::warn(
                'Mitarbeiter-Spiegel konnte nicht aufgefrischt werden',
                ['exception' => $e->getMessage()],
                null,
                Helper::terminalId(),
                'mitarbeiter_spiegel'
            );
        }
    }

    /**
     * Was weiß das Gerät über diesen Chip?
     *
     * Liefert `CHIP_OHNE_AUSKUNFT`, solange kein gefüllter Spiegel da ist –
     * ein leerer Spiegel würde sonst jeden Chip als unbekannt melden, und das
     * wäre schlimmer als zu schweigen.
     */
    public function pruefeChip(string $rfidCode): string
    {
        $rfidCode = trim($rfidCode);
        if ($rfidCode === '') {
            return self::CHIP_OHNE_AUSKUNFT;
        }

        $pdo = $this->verbindungOderNull();
        if ($pdo === null) {
            return self::CHIP_OHNE_AUSKUNFT;
        }

        try {
            if (!$this->ensureSchema($pdo) || !$this->istGefuellt($pdo)) {
                return self::CHIP_OHNE_AUSKUNFT;
            }

            $statement = $pdo->prepare(
                'SELECT aktiv FROM mitarbeiter_spiegel WHERE rfid_code = :code LIMIT 1'
            );
            $statement->bindValue(':code', $rfidCode, \PDO::PARAM_STR);
            $statement->execute();

            $aktiv = $statement->fetchColumn();
            if ($aktiv === false) {
                return self::CHIP_UNBEKANNT;
            }

            return ((int)$aktiv === 1) ? self::CHIP_BEKANNT : self::CHIP_INAKTIV;
        } catch (\Throwable $e) {
            Logger::warn(
                'Mitarbeiter-Spiegel konnte nicht gelesen werden',
                ['exception' => $e->getMessage()],
                null,
                Helper::terminalId(),
                'mitarbeiter_spiegel'
            );

            return self::CHIP_OHNE_AUSKUNFT;
        }
    }

    /**
     * Die lokale Ausweichdatenbank – und nur die.
     *
     * Kein Rückfall auf die Hauptdatenbank, anders als bei der Queue: Dort
     * steht die Tabelle `mitarbeiter` selbst. Ein Spiegel daneben wäre eine
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

        $statement = $pdo->query('SELECT MAX(aktualisiert_am) FROM mitarbeiter_spiegel');
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

    private function istGefuellt(\PDO $pdo): bool
    {
        $statement = $pdo->query('SELECT COUNT(*) FROM mitarbeiter_spiegel');

        return $statement !== false && (int)$statement->fetchColumn() > 0;
    }

    /**
     * Schreibt den Spiegel neu.
     *
     * Ganz oder gar nicht: Ein halb geschriebener Spiegel meldet Chips als
     * unbekannt, die es gibt. Deshalb Löschen und Füllen in **einer**
     * Transaktion – und erst danach zählt er als aufgefrischt.
     *
     * @param array<int,array<string,mixed>> $zeilen
     */
    private function schreibeSpiegel(\PDO $pdo, array $zeilen): void
    {
        $jetzt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM mitarbeiter_spiegel');

            $einfuegen = $pdo->prepare(
                'INSERT INTO mitarbeiter_spiegel
                    (mitarbeiter_id, personalnummer, rfid_code, aktiv, aktualisiert_am)
                 VALUES (:id, :personalnummer, :rfid_code, :aktiv, :aktualisiert_am)'
            );

            foreach ($zeilen as $zeile) {
                $id = (int)($zeile['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $einfuegen->bindValue(':id', $id, \PDO::PARAM_INT);

                $personalnummer = $zeile['personalnummer'] ?? null;
                if ($personalnummer !== null && trim((string)$personalnummer) !== '') {
                    $einfuegen->bindValue(':personalnummer', (string)$personalnummer, \PDO::PARAM_STR);
                } else {
                    $einfuegen->bindValue(':personalnummer', null, \PDO::PARAM_NULL);
                }

                $rfid = $zeile['rfid_code'] ?? null;
                if ($rfid !== null && trim((string)$rfid) !== '') {
                    $einfuegen->bindValue(':rfid_code', (string)$rfid, \PDO::PARAM_STR);
                } else {
                    $einfuegen->bindValue(':rfid_code', null, \PDO::PARAM_NULL);
                }

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
     * Dieselbe Bauart wie in `OfflineQueueManager::ensureQueueSchema()`: Die
     * lokale Datenbank eines Terminals kann aus einer älteren Installation
     * stammen, und `sql/offline_db_schema.sql` läuft dort nicht noch einmal.
     */
    private function ensureSchema(\PDO $pdo): bool
    {
        if ($this->schemaBereit !== null) {
            return $this->schemaBereit;
        }

        try {
            $probe = $pdo->query('SELECT 1 FROM mitarbeiter_spiegel LIMIT 1');
            if ($probe !== false) {
                $this->schemaBereit = true;

                return true;
            }
        } catch (\Throwable $e) {
            // Tabelle fehlt vermutlich – wird gleich angelegt.
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS mitarbeiter_spiegel (\n" .
                "  mitarbeiter_id BIGINT UNSIGNED NOT NULL,\n" .
                "  personalnummer VARCHAR(50) NULL,\n" .
                "  rfid_code VARCHAR(100) NULL,\n" .
                "  aktiv TINYINT(1) NOT NULL DEFAULT 1,\n" .
                "  aktualisiert_am DATETIME NOT NULL,\n" .
                "  PRIMARY KEY (mitarbeiter_id),\n" .
                "  KEY idx_mitarbeiter_spiegel_rfid (rfid_code)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );

            $this->schemaBereit = true;
        } catch (\Throwable $e) {
            Logger::warn(
                'Tabelle mitarbeiter_spiegel konnte nicht angelegt werden',
                ['exception' => $e->getMessage()],
                null,
                Helper::terminalId(),
                'mitarbeiter_spiegel'
            );

            $this->schemaBereit = false;
        }

        return $this->schemaBereit;
    }
}
