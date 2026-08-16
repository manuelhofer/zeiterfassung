<?php
declare(strict_types=1);

/**
 * OfflineQueueManager
 *
 * Verwaltet die Tabelle `db_injektionsqueue` für den Offline-Betrieb.
 *
 * Aufgaben:
 * - SQL-Befehle in die Queue schreiben, wenn die Hauptdatenbank nicht erreichbar ist.
 * - Abarbeitung der Queue, sobald die Hauptdatenbank wieder verfügbar ist.
 * - Überspringen eines gescheiterten Eintrags, ohne die Abarbeitung zu stoppen.
 * - Melden eines gescheiterten Eintrags in der Hauptdatenbank, damit das
 *   Backend ihn zeigt.
 * - Bereitstellung von Hilfsfunktionen für das Backend/Terminal-UI (z. B. aktueller Fehler-Eintrag).
 */
class OfflineQueueManager
{
    /** Singleton-Instanz. */
    private static ?OfflineQueueManager $instanz = null;

    private Database $datenbank;

    /**
     * Wird genutzt, um die Queue-Schema-Initialisierung nur einmal pro Prozess
     * auszuführen (idempotent).
     */
    private bool $schemaInitialisiert = false;

    /**
     * Ob die Queue-Tabelle der Hauptdatenbank die Spalte `meta_quell_id` hat
     * (Migration 10). Einmal je Prozess ermittelt; `null` heißt „noch nicht
     * gefragt".
     */
    private ?bool $herkunftsSpalteVorhanden = null;

    /**
     * Privater Konstruktor.
     */
    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Liefert die Singleton-Instanz.
     */
    public static function getInstanz(): OfflineQueueManager
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Legt einen neuen Eintrag in der Offline-Queue an.
     *
     * Wird typischerweise vom Terminal aufgerufen, wenn die Hauptdatenbank
     * nicht erreichbar ist und eine Aktion später nachgeholt werden soll.
     *
     * @param string      $sqlBefehl    Vollständiger SQL-Befehl, der später 1:1 gegen die Haupt-DB ausgeführt wird.
     * @param int|null    $mitarbeiterId Optionale Mitarbeiter-ID für Metadaten.
     * @param int|null    $terminalId    Terminal-ID für Metadaten; `null` heißt
     *                                   „dieses Gerät", nicht „unbekannt".
     * @param string|null $aktion        Optionale Beschreibung der Aktion (z. B. 'zeit_stempeln', 'auftrag_start').
     */
    public function speichereInQueue(
        string $sqlBefehl,
        ?int $mitarbeiterId = null,
        ?int $terminalId = null,
        ?string $aktion = null
    ): bool {
        $sqlBefehl = trim($sqlBefehl);
        if ($sqlBefehl === '') {
            return false;
        }

        // Die Queue existiert nur auf einem Terminal – ein Eintrag ohne
        // Terminal-ID ist deshalb nie „von irgendwo", sondern immer von hier.
        // Die meisten Aufrufer reichen die ID nicht durch; statt sie an
        // neunzehn Stellen nachzutragen, füllt sie die Queue selbst.
        if ($terminalId === null) {
            $terminalId = Helper::terminalId();
        }

        try {
            $pdo = $this->holeQueueVerbindung();
            $this->ensureQueueSchema($pdo);
        } catch (\Throwable $e) {
            Logger::error(
                'OfflineQueueManager: Keine DB-Verbindung zum Speichern in db_injektionsqueue',
                ['exception' => $e->getMessage()],
                $mitarbeiterId,
                $terminalId,
                'offline_queue'
            );

            return false;
        }

        $sql = 'INSERT INTO db_injektionsqueue (status, sql_befehl, meta_mitarbeiter_id, meta_terminal_id, meta_aktion)
                VALUES (\'offen\', :sql_befehl, :meta_mitarbeiter_id, :meta_terminal_id, :meta_aktion)';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':sql_befehl', $sqlBefehl, \PDO::PARAM_STR);

        if ($mitarbeiterId !== null) {
            $statement->bindValue(':meta_mitarbeiter_id', $mitarbeiterId, \PDO::PARAM_INT);
        } else {
            $statement->bindValue(':meta_mitarbeiter_id', null, \PDO::PARAM_NULL);
        }

        if ($terminalId !== null) {
            $statement->bindValue(':meta_terminal_id', $terminalId, \PDO::PARAM_INT);
        } else {
            $statement->bindValue(':meta_terminal_id', null, \PDO::PARAM_NULL);
        }

        if ($aktion !== null && $aktion !== '') {
            $statement->bindValue(':meta_aktion', $aktion, \PDO::PARAM_STR);
        } else {
            $statement->bindValue(':meta_aktion', null, \PDO::PARAM_NULL);
        }

        return $statement->execute();
    }

    /**
     * Abarbeitung aller offenen Queue-Einträge in zeitlicher Reihenfolge.
     *
     * Regeln (siehe `docs/fachregeln/terminal_und_offline.md`, Abschnitt 5):
     * - Es werden nur Einträge mit Status 'offen' verarbeitet.
     * - Abarbeitung in aufsteigender Reihenfolge von `erstellt_am`, dann `id`.
     * - Scheitert ein Eintrag, geht er mit seiner Fehlermeldung auf Status
     *   'fehler' und wird **übersprungen**; die folgenden Einträge werden
     *   weiter abgearbeitet.
     * - Gemeldet wird er in der Hauptdatenbank
     *   (`meldeFehlerAnHauptdatenbank()`), damit ihn die Queue-Verwaltung im
     *   Backend zeigt.
     *
     * Warum überspringen und nicht abbrechen: Ein einziger unauflösbarer
     * Eintrag – ein unbekannter Chip genügt – hielt sonst alle späteren
     * Buchungen fest, obwohl mit ihnen nichts ist, und sperrte das Terminal.
     * Ein zweiter Versuch entsteht daraus nicht: 'fehler' ist nicht 'offen',
     * der nächste Lauf sieht den Eintrag nicht mehr. Gemeldet wird er im
     * Backend, wo jemand entscheiden darf.
     *
     * **Ein Abschluss, nicht zwei** (T-128): Buchung und Herkunftsvermerk
     * gehen gemeinsam in **einer** Transaktion auf die Hauptdatenbank. Das
     * Abhaken in der Queue des Terminals danach ist nur noch eine Notiz –
     * geht sie verloren, weil in genau diesem Moment der Strom ausfällt,
     * findet der nächste Lauf den Vermerk und holt sie nach, statt die Buchung
     * ein zweites Mal einzuspielen.
     */
    public function verarbeiteOffeneEintraege(): void
    {
        // Ohne Hauptdatenbank macht das Abarbeiten keinen Sinn.
        if (!$this->datenbank->istHauptdatenbankVerfuegbar()) {
            return;
        }

        $queuePdo = $this->holeQueueVerbindung();
        $this->ensureQueueSchema($queuePdo);
        $hauptPdo = $this->datenbank->getVerbindung();

        // Liegt die Queue in der Hauptdatenbank – ein Terminal ohne erreichbare
        // Ausweichdatenbank –, gibt es nur eine Datenbank und damit von selbst
        // nur einen Abschluss: Das `UPDATE` gehört dann einfach mit in die
        // Transaktion. Ein Vermerk wäre hier eine Kopie der Zeile daneben.
        $eineDatenbank = ($queuePdo === $hauptPdo);

        // Einmal vorab fragen, nicht je Eintrag – und bewusst **vor** der
        // Schleife, damit auf der Verbindung, über die gleich der Cursor läuft,
        // keine Nebenabfrage mehr nötig ist.
        $vermerkMoeglich = !$eineDatenbank && $this->hatHerkunftsSpalte($hauptPdo);

        $sqlSelect = 'SELECT *
                      FROM db_injektionsqueue
                      WHERE status = \'offen\'
                      ORDER BY erstellt_am ASC, id ASC';

        $statement = $queuePdo->query($sqlSelect);
        if ($statement === false) {
            return;
        }

        while (($eintrag = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $id        = (int)$eintrag['id'];
            $sqlBefehl = (string)$eintrag['sql_befehl'];

            // Sicherstellen, dass der SQL-Befehl nicht leer ist.
            if (trim($sqlBefehl) === '') {
                $meldung = 'Leerer SQL-Befehl in db_injektionsqueue.';
                $this->markiereAlsFehler($queuePdo, $id, $meldung);
                $this->meldeFehlerAnHauptdatenbank($hauptPdo, $queuePdo, $eintrag, $meldung);
                continue;
            }

            // Der Eintrag steht auf 'offen' – aber steht er das zu Recht? Nach
            // einem Stromausfall zwischen Commit und Abhaken ist die Buchung
            // längst drin. Dann nur die Notiz nachholen.
            if ($vermerkMoeglich && $this->istBereitsEingespielt($hauptPdo, $eintrag)) {
                $this->markiereAlsVerarbeitet($queuePdo, $id);

                Logger::warn(
                    'Queue-Eintrag war bereits eingespielt und wurde nur nachgetragen',
                    ['id' => $id, 'meta_aktion' => $eintrag['meta_aktion'] ?? null],
                    null,
                    isset($eintrag['meta_terminal_id']) ? (int)$eintrag['meta_terminal_id'] : null,
                    'offline_queue'
                );

                continue;
            }

            try {
                // Ausführung auf der Hauptdatenbank.
                $hauptPdo->beginTransaction();
                $hauptPdo->exec($sqlBefehl);

                if ($eineDatenbank) {
                    $this->markiereAlsVerarbeitet($queuePdo, $id);
                } elseif ($vermerkMoeglich) {
                    // Kein `try` darum: Lässt sich der Vermerk nicht schreiben,
                    // darf die Buchung nicht stehen bleiben – sonst wäre sie
                    // genau die Buchung, die beim nächsten Lauf ein zweites Mal
                    // entsteht. Die Ausnahme fällt in den `catch` unten und
                    // nimmt die Transaktion mit.
                    $this->schreibeHerkunftsvermerk($hauptPdo, $eintrag);
                }

                $hauptPdo->commit();

                if (!$eineDatenbank) {
                    $this->markiereAlsVerarbeitet($queuePdo, $id);
                }
            } catch (\Throwable $e) {
                if ($hauptPdo->inTransaction()) {
                    $hauptPdo->rollBack();
                }

                $this->markiereAlsFehler($queuePdo, $id, $e->getMessage());
                $this->meldeFehlerAnHauptdatenbank($hauptPdo, $queuePdo, $eintrag, $e->getMessage());

                Logger::error(
                    'Fehler bei Abarbeitung von db_injektionsqueue',
                    ['id' => $id, 'sql_befehl' => $sqlBefehl, 'exception' => $e->getMessage()],
                    null,
                    null,
                    'offline_queue'
                );

                // Kein Abbruch: Der nächste Eintrag ist an diesem Fehler
                // unschuldig und gehört genauso in die Hauptdatenbank.
            }
        }
    }

    /**
     * Legt einen gescheiterten Eintrag dort ab, wo jemand über ihn entscheiden
     * darf: in der `db_injektionsqueue` der **Hauptdatenbank**. Die vorhandene
     * Queue-Verwaltung im Backend (`QUEUE_VERWALTEN`) zeigt ihn damit ohne neue
     * Maske – mit Zeitpunkt, Chipnummer (im SQL-Befehl), Terminal und
     * Fehlermeldung, und mit „Retry" und „Ignorieren/Löschen" daneben.
     *
     * Warum das nötig ist: Ohne diesen Schritt hieße „überspringen" für jeden
     * Betrachter „Fehler verschwindet". Die Queue liegt auf dem Terminal, und
     * das Backend liest nach derselben Regel seine eigene – gesehen hätte den
     * Eintrag nur, wer mit Tastatur oder SSH an das Gerät geht.
     *
     * Dass die Hauptdatenbank gerade erreichbar ist, steht fest: Sonst hätte es
     * den Versuch nicht gegeben, der eben gescheitert ist.
     *
     * Der lokale Eintrag bleibt liegen und wird **nicht** gelöscht – er ist der
     * Beleg auf dem Gerät. Doppelt eingespielt wird deshalb nichts: Er steht
     * auf `fehler`, und abgearbeitet wird nur `offen`.
     *
     * @param array<string,mixed> $eintrag Der lokale Queue-Eintrag, wie er aus
     *                                     `db_injektionsqueue` gelesen wurde.
     */
    private function meldeFehlerAnHauptdatenbank(
        \PDO $hauptPdo,
        \PDO $queuePdo,
        array $eintrag,
        string $fehlerNachricht
    ): void {
        // Liegt die Queue ohnehin in der Hauptdatenbank – ein Terminal ohne
        // erreichbare Ausweichdatenbank –, steht der Eintrag dort schon. Eine
        // Kopie wäre derselbe Fehler zweimal in derselben Liste.
        if ($queuePdo === $hauptPdo) {
            return;
        }

        $lokaleId = (int)($eintrag['id'] ?? 0);

        // Woher der Eintrag stammt, gehört in die Meldung: Die ID im Backend
        // ist eine neue, die lokale findet man sonst auf dem Gerät nicht wieder.
        $meldung = 'Terminal-Queue Eintrag ' . $lokaleId . ': ' . $fehlerNachricht;
        if (strlen($meldung) > 1000) {
            $meldung = substr($meldung, 0, 1000);
        }

        // Der Zeitpunkt der Buchung, nicht der des Einspielversuchs - danach
        // sortiert die Liste im Backend, und danach sucht der Mensch, der die
        // Zeit von Hand nachträgt.
        $erstelltAm = $eintrag['erstellt_am'] ?? null;
        if (!is_string($erstelltAm) || trim($erstelltAm) === '') {
            $erstelltAm = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        }

        $terminalId = $eintrag['meta_terminal_id'] ?? null;
        if ($terminalId === null) {
            $terminalId = Helper::terminalId();
        }

        // Die Herkunft gehört auch an die Fehlermeldung – bisher stand die
        // lokale ID nur im Fließtext der Meldung. Verwechseln lässt sich
        // beides nicht: Als „schon eingespielt" zählt nur ein Vermerk mit
        // Status `verarbeitet` (T-128).
        $mitHerkunft = $this->hatHerkunftsSpalte($hauptPdo);

        $sql = 'INSERT INTO db_injektionsqueue
                    (erstellt_am, status, sql_befehl, fehlernachricht, letzte_ausfuehrung,
                     versuche, meta_mitarbeiter_id, meta_terminal_id, meta_aktion'
                . ($mitHerkunft ? ', meta_quell_id' : '') . ')
                VALUES (:erstellt_am, \'fehler\', :sql_befehl, :fehlernachricht, :letzte_ausfuehrung,
                        :versuche, :meta_mitarbeiter_id, :meta_terminal_id, :meta_aktion'
                . ($mitHerkunft ? ', :meta_quell_id' : '') . ')';

        try {
            $statement = $hauptPdo->prepare($sql);

            if ($mitHerkunft) {
                $statement->bindValue(':meta_quell_id', $lokaleId > 0 ? $lokaleId : null, $lokaleId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
            }

            $statement->bindValue(':erstellt_am', $erstelltAm, \PDO::PARAM_STR);
            $statement->bindValue(':sql_befehl', (string)($eintrag['sql_befehl'] ?? ''), \PDO::PARAM_STR);
            $statement->bindValue(':fehlernachricht', $meldung, \PDO::PARAM_STR);
            $statement->bindValue(
                ':letzte_ausfuehrung',
                (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                \PDO::PARAM_STR
            );
            $statement->bindValue(':versuche', (int)($eintrag['versuche'] ?? 0) + 1, \PDO::PARAM_INT);

            $mitarbeiterId = $eintrag['meta_mitarbeiter_id'] ?? null;
            if ($mitarbeiterId !== null) {
                $statement->bindValue(':meta_mitarbeiter_id', (int)$mitarbeiterId, \PDO::PARAM_INT);
            } else {
                $statement->bindValue(':meta_mitarbeiter_id', null, \PDO::PARAM_NULL);
            }

            if ($terminalId !== null) {
                $statement->bindValue(':meta_terminal_id', (int)$terminalId, \PDO::PARAM_INT);
            } else {
                $statement->bindValue(':meta_terminal_id', null, \PDO::PARAM_NULL);
            }

            $aktion = $eintrag['meta_aktion'] ?? null;
            if (is_string($aktion) && $aktion !== '') {
                $statement->bindValue(':meta_aktion', $aktion, \PDO::PARAM_STR);
            } else {
                $statement->bindValue(':meta_aktion', null, \PDO::PARAM_NULL);
            }

            $statement->execute();
        } catch (\Throwable $e) {
            // Scheitert auch das, bleibt nur das Protokoll. Der lokale Eintrag
            // steht weiterhin auf 'fehler'; die Abarbeitung darf daran nicht
            // hängen bleiben - sonst hielte ein defektes Melden genau das auf,
            // was dieser Patch freimacht.
            Logger::error(
                'Gescheiterter Queue-Eintrag konnte nicht in der Hauptdatenbank gemeldet werden',
                ['lokale_id' => $lokaleId, 'exception' => $e->getMessage()],
                null,
                is_int($terminalId) ? $terminalId : null,
                'offline_queue'
            );
        }
    }

    /**
     * Woher ein Queue-Eintrag stammt: `[terminal_id, lokale_id, erstellt_am]`,
     * oder `null`, wenn sich das nicht eindeutig sagen lässt.
     *
     * Warum alle drei zusammen und nicht nur die lokale ID: Die ID ist nur
     * innerhalb **einer** Queue eindeutig. Zwei Terminals haben beide einen
     * Eintrag 7, und wird die Ausweichdatenbank eines Terminals neu angelegt,
     * fängt sie selbst bei demselben Gerät wieder bei 1 an. Der Zeitpunkt des
     * Eintrags trennt auch diesen Fall – zwei Buchungen auf dieselbe Sekunde
     * mit derselben wiederverwendeten ID gibt es praktisch nicht.
     *
     * @param array<string,mixed> $eintrag
     * @return array{0:int,1:int,2:string}|null
     */
    private function herkunftDesEintrags(array $eintrag): ?array
    {
        $quellId = (int)($eintrag['id'] ?? 0);
        if ($quellId <= 0) {
            return null;
        }

        $terminalId = $eintrag['meta_terminal_id'] ?? null;
        if ($terminalId === null) {
            $terminalId = Helper::terminalId();
        }

        if ($terminalId === null) {
            return null;
        }

        $erstelltAm = $eintrag['erstellt_am'] ?? null;
        if (!is_string($erstelltAm) || trim($erstelltAm) === '') {
            return null;
        }

        return [(int)$terminalId, $quellId, trim($erstelltAm)];
    }

    /**
     * Steht in der Hauptdatenbank schon der Vermerk, dass dieser Eintrag
     * eingespielt wurde?
     *
     * Nur `verarbeitet` zählt. Eine Fehlermeldung zu demselben Eintrag
     * (`meldeFehlerAnHauptdatenbank()`) trägt dieselbe Herkunft, bedeutet aber
     * das Gegenteil – sie als „schon drin" zu lesen, würde eine Buchung
     * verlieren, die nie stattgefunden hat.
     *
     * @param array<string,mixed> $eintrag
     */
    private function istBereitsEingespielt(\PDO $hauptPdo, array $eintrag): bool
    {
        $herkunft = $this->herkunftDesEintrags($eintrag);
        if ($herkunft === null) {
            return false;
        }

        try {
            $statement = $hauptPdo->prepare(
                'SELECT id
                   FROM db_injektionsqueue
                  WHERE meta_terminal_id = :terminal_id
                    AND meta_quell_id    = :quell_id
                    AND erstellt_am      = :erstellt_am
                    AND status           = \'verarbeitet\'
                  LIMIT 1'
            );
            $statement->bindValue(':terminal_id', $herkunft[0], \PDO::PARAM_INT);
            $statement->bindValue(':quell_id', $herkunft[1], \PDO::PARAM_INT);
            $statement->bindValue(':erstellt_am', $herkunft[2], \PDO::PARAM_STR);
            $statement->execute();

            return $statement->fetchColumn() !== false;
        } catch (\Throwable $e) {
            // Im Zweifel einspielen. Eine Dublette steht in der Hauptdatenbank
            // und lässt sich im Backend erkennen und korrigieren; eine Buchung,
            // die niemand gemacht hat, weil eine kaputte Abfrage „schon drin"
            // sagte, ist weg.
            Logger::warn(
                'Herkunftsvermerk konnte nicht geprüft werden – Eintrag wird eingespielt',
                ['id' => $herkunft[1], 'exception' => $e->getMessage()],
                null,
                $herkunft[0],
                'offline_queue'
            );

            return false;
        }
    }

    /**
     * Schreibt den Vermerk „Eintrag N von Terminal X ist eingespielt" in die
     * `db_injektionsqueue` der Hauptdatenbank.
     *
     * Aufgerufen **innerhalb** der Transaktion, in der auch die Buchung steckt.
     * Das ist der ganze Trick an T-128: Zwei Datenbanken lassen sich ohne XA
     * nicht gemeinsam abschließen, eine Datenbank mit sich selbst schon. Der
     * Vermerk und die Buchung stehen danach entweder beide da oder keiner von
     * beiden.
     *
     * Der Vermerk trägt den vollständigen SQL-Befehl. Das lässt die Tabelle
     * mitwachsen und ist es wert: In der Queue-Verwaltung steht damit, was ein
     * Terminal wann nachgetragen hat – bisher war das nur am Gerät zu sehen.
     *
     * @param array<string,mixed> $eintrag
     */
    private function schreibeHerkunftsvermerk(\PDO $hauptPdo, array $eintrag): void
    {
        $herkunft = $this->herkunftDesEintrags($eintrag);
        if ($herkunft === null) {
            // Ohne Herkunft kein Vermerk – und damit für diesen einen Eintrag
            // auch kein Schutz. Das gehört ins Protokoll und nicht in eine
            // Ausnahme: Die Buchung selbst ist in Ordnung.
            Logger::warn(
                'Queue-Eintrag ohne erkennbare Herkunft eingespielt – ohne Vermerk (T-128)',
                ['id' => $eintrag['id'] ?? null, 'meta_aktion' => $eintrag['meta_aktion'] ?? null],
                null,
                null,
                'offline_queue'
            );

            return;
        }

        $sql = 'INSERT INTO db_injektionsqueue
                    (erstellt_am, status, sql_befehl, letzte_ausfuehrung, versuche,
                     meta_mitarbeiter_id, meta_terminal_id, meta_aktion, meta_quell_id)
                VALUES (:erstellt_am, \'verarbeitet\', :sql_befehl, :letzte_ausfuehrung, :versuche,
                        :meta_mitarbeiter_id, :meta_terminal_id, :meta_aktion, :meta_quell_id)';

        $statement = $hauptPdo->prepare($sql);
        $statement->bindValue(':erstellt_am', $herkunft[2], \PDO::PARAM_STR);
        $statement->bindValue(':sql_befehl', (string)($eintrag['sql_befehl'] ?? ''), \PDO::PARAM_STR);
        $statement->bindValue(
            ':letzte_ausfuehrung',
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':versuche', (int)($eintrag['versuche'] ?? 0) + 1, \PDO::PARAM_INT);

        $mitarbeiterId = $eintrag['meta_mitarbeiter_id'] ?? null;
        if ($mitarbeiterId !== null) {
            $statement->bindValue(':meta_mitarbeiter_id', (int)$mitarbeiterId, \PDO::PARAM_INT);
        } else {
            $statement->bindValue(':meta_mitarbeiter_id', null, \PDO::PARAM_NULL);
        }

        $statement->bindValue(':meta_terminal_id', $herkunft[0], \PDO::PARAM_INT);

        $aktion = $eintrag['meta_aktion'] ?? null;
        if (is_string($aktion) && $aktion !== '') {
            $statement->bindValue(':meta_aktion', $aktion, \PDO::PARAM_STR);
        } else {
            $statement->bindValue(':meta_aktion', null, \PDO::PARAM_NULL);
        }

        $statement->bindValue(':meta_quell_id', $herkunft[1], \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * Hat die Queue-Tabelle die Spalte `meta_quell_id` (Migration 10)?
     *
     * Warum das überhaupt geprüft wird, statt sich auf die Migration zu
     * verlassen: Ohne die Prüfung würde auf einer Installation, die den Code
     * eingespielt hat und die Migration nicht, **jede** Buchung beim
     * Wiederanlauf scheitern – der Vermerk ist Teil der Transaktion. Aus einer
     * vergessenen Migration würde damit ein Terminal, das keine Zeiten mehr
     * nachträgt. Der Rückfall auf den alten Weg ist das kleinere Übel, aber
     * nicht stillschweigend: Er steht als Fehler im Protokoll.
     */
    private function hatHerkunftsSpalte(\PDO $pdo): bool
    {
        if ($this->herkunftsSpalteVorhanden !== null) {
            return $this->herkunftsSpalteVorhanden;
        }

        $vorhanden = false;
        try {
            // Dieselbe Form wie in `ensureQueueSchema()`: Eine Abfrage, die
            // nichts liest, aber die Spalte benennen muss.
            $probe = $pdo->query('SELECT meta_quell_id FROM db_injektionsqueue LIMIT 0');
            $vorhanden = ($probe !== false);
        } catch (\Throwable $e) {
            $vorhanden = false;
        }

        if (!$vorhanden) {
            Logger::error(
                'db_injektionsqueue ohne Spalte meta_quell_id – sql/10_migration_queue_herkunftsvermerk.sql '
                . 'fehlt. Die Queue arbeitet weiter, aber ein Ausfall zwischen Buchung und Abhaken kann '
                . 'einen Eintrag ein zweites Mal einspielen (T-128).',
                [],
                null,
                Helper::terminalId(),
                'offline_queue'
            );
        }

        $this->herkunftsSpalteVorhanden = $vorhanden;

        return $vorhanden;
    }

    /**
     * Gibt den aktuell letzten Fehler-Eintrag aus der Queue zurück (oder null).
     *
     * @return array<string,mixed>|null
     */
    public function holeLetztenFehlerEintrag(): ?array
    {
        try {
            $pdo = $this->holeQueueVerbindung();
            $this->ensureQueueSchema($pdo);
        } catch (\Throwable $e) {
            return null;
        }

        $sql = 'SELECT *
                FROM db_injektionsqueue
                WHERE status = \'fehler\'
                ORDER BY letzte_ausfuehrung DESC, id DESC
                LIMIT 1';

        $statement = $pdo->query($sql);
        if ($statement === false) {
            return null;
        }

        $datensatz = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($datensatz === false) {
            return null;
        }

        return $datensatz;
    }

    /**
     * Löscht einen Eintrag dauerhaft aus der Queue.
     *
     * Kann vom Backend/Terminal-Admin verwendet werden, um einen
     * problematischen Eintrag nach manueller Nachpflege zu entfernen
     * und die Queue danach weiterlaufen zu lassen.
     */
    public function loescheEintrag(int $id): void
    {
        try {
            $pdo = $this->holeQueueVerbindung();
            $this->ensureQueueSchema($pdo);
        } catch (\Throwable $e) {
            return;
        }

        $sql = 'DELETE FROM db_injektionsqueue WHERE id = :id';
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Markiert einen Eintrag als verarbeitet.
     */
    private function markiereAlsVerarbeitet(\PDO $pdo, int $id): void
    {
        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'UPDATE db_injektionsqueue
                SET status = \'verarbeitet\',
                    fehlernachricht = NULL,
                    letzte_ausfuehrung = :ts,
                    versuche = versuche + 1
                WHERE id = :id';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':ts', $ts, \PDO::PARAM_STR);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Markiert einen Eintrag als Fehler und speichert die Fehlermeldung.
     */
    private function markiereAlsFehler(\PDO $pdo, int $id, string $fehlerNachricht): void
    {
        // Fehlermeldung auf eine sinnvolle Länge begrenzen.
        if (strlen($fehlerNachricht) > 1000) {
            $fehlerNachricht = substr($fehlerNachricht, 0, 1000);
        }

        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'UPDATE db_injektionsqueue
                SET status = \'fehler\',
                    fehlernachricht = :fehlernachricht,
                    letzte_ausfuehrung = :ts,
                    versuche = versuche + 1
                WHERE id = :id';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':fehlernachricht', $fehlerNachricht, \PDO::PARAM_STR);
        $statement->bindValue(':ts', $ts, \PDO::PARAM_STR);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Stellt sicher, dass die Queue-Tabelle in der verwendeten Queue-DB existiert.
     *
     * Hintergrund:
     * - Für Terminals wird optional eine separate Offline-DB genutzt.
     * - Diese kann leer sein (z. B. SQLite-Datei frisch angelegt).
     * - Dann müssen wir das Minimal-Schema für `db_injektionsqueue` automatisch erstellen.
     */
    private function ensureQueueSchema(\PDO $pdo): void
    {
        if ($this->schemaInitialisiert) {
            return;
        }

        try {
            $probe = $pdo->query('SELECT 1 FROM db_injektionsqueue LIMIT 1');
            if ($probe !== false) {
                $this->schemaInitialisiert = true;
                return;
            }
        } catch (\Throwable $e) {
            // Tabelle existiert vermutlich nicht → wir erstellen sie.
        }

        $driver = '';
        try {
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = '';
        }

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS db_injektionsqueue (\n" .
                "  id INTEGER PRIMARY KEY AUTOINCREMENT,\n" .
                "  status TEXT NOT NULL DEFAULT 'offen',\n" .
                "  sql_befehl TEXT NOT NULL,\n" .
                "  fehlernachricht TEXT NULL,\n" .
                "  versuche INTEGER NOT NULL DEFAULT 0,\n" .
                "  letzte_ausfuehrung TEXT NULL,\n" .
                "  meta_mitarbeiter_id INTEGER NULL,\n" .
                "  meta_terminal_id INTEGER NULL,\n" .
                "  meta_aktion TEXT NULL,\n" .
                "  meta_quell_id INTEGER NULL,\n" .
                "  erstellt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP\n" .
                ");"
            );
        } else {
            // Default: MySQL/MariaDB
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS db_injektionsqueue (\n" .
                "  id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
                "  status VARCHAR(20) NOT NULL DEFAULT 'offen',\n" .
                "  sql_befehl MEDIUMTEXT NOT NULL,\n" .
                "  fehlernachricht TEXT NULL,\n" .
                "  versuche INT UNSIGNED NOT NULL DEFAULT 0,\n" .
                "  letzte_ausfuehrung DATETIME NULL,\n" .
                "  meta_mitarbeiter_id INT UNSIGNED NULL,\n" .
                "  meta_terminal_id INT UNSIGNED NULL,\n" .
                "  meta_aktion VARCHAR(100) NULL,\n" .
                // Nur in der Hauptdatenbank gefüllt; steht hier, damit beide
                // Queue-Tabellen dieselbe Form haben (T-128).
                "  meta_quell_id INT UNSIGNED NULL,\n" .
                "  erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
                "  PRIMARY KEY (id)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );
        }

        $this->schemaInitialisiert = true;
    }

    /**
     * Ermittelt die passende Verbindung für die Queue-Tabelle.
     *
     * Logik:
     * - Wenn eine Offline-Datenbank konfiguriert und erreichbar ist, wird diese verwendet.
     * - Ansonsten wird die Hauptdatenbank verwendet.
     */
    private function holeQueueVerbindung(): \PDO
    {
        $pdo = $this->holeQueueVerbindungOderNull();
        if ($pdo === null) {
            throw new \RuntimeException('Keine Queue-DB verfügbar (Offline-DB nicht aktiv/erreichbar und Haupt-DB offline).');
        }

        return $pdo;
    }

    /**
     * Dieselbe Regel wie `holeQueueVerbindung()`, nur ohne Ausnahme: `null`
     * heißt „im Moment liegt die Queue nirgends".
     *
     * Öffentlich, weil `QueueService` und `QueueController` dieselbe Frage
     * beantworten müssen – wer anzeigt, muss in dieselbe Tabelle sehen, in die
     * geschrieben wird. Diese Regel stand vorher mehrfach im Projekt, in leicht
     * verschiedenen Fassungen; driften sie auseinander, liest die Oberfläche
     * eine andere Datenbank, als das Terminal befüllt.
     */
    public function holeQueueVerbindungOderNull(): ?\PDO
    {
        $ausweich = $this->holeAusweichVerbindungOderNull();
        if ($ausweich instanceof \PDO) {
            return $ausweich;
        }

        // Rückfall auf die Hauptdatenbank nur dann, wenn sie erreichbar ist.
        try {
            if ($this->datenbank->istHauptdatenbankVerfuegbar()) {
                return $this->datenbank->getVerbindung();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Die lokale Ausweichdatenbank – aber **nur auf einem Terminal**.
     *
     * Sie gehört der Maschine in der Halle: Dort ist sie die Queue, solange die
     * Hauptdatenbank fehlt. Auf einem Backend beantwortet dieselbe Datenbank
     * eine andere Frage. Die Queue eines Backends liegt in der Hauptdatenbank,
     * und nur dort – dorthin melden die Terminals ihre gescheiterten Einträge
     * (P-2026-08-16-11), und dort erwartet sie die Queue-Verwaltung.
     *
     * Warum die Bindung nötig ist (T-130): `offline_db.enabled` steht in
     * `config/config.php` standardmäßig auf `1`. Auf einem Rechner, auf dem
     * beides liegt – Backend und eine erreichbare `zeiterfassung_offline` –,
     * las die Queue-Verwaltung die Ausweichdatenbank und zeigte die Meldungen
     * der Terminals nicht. Nicht sichtbar heißt hier: nicht vorhanden, für
     * jeden, der hinsieht.
     *
     * Maßgeblich ist deshalb `app.installation_typ`, genau wie bei
     * `Helper::terminalId()` – nicht die Erreichbarkeit der Datenbank und nicht
     * ein `terminal`-Block, der aus einer früheren Kopplung stehen geblieben
     * ist.
     */
    private function holeAusweichVerbindungOderNull(): ?\PDO
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
     * Wo die Queue gerade liegt: `'offline'`, `'haupt'` oder `null`, wenn
     * keine der beiden Datenbanken erreichbar ist. Für Anzeigen gedacht.
     */
    public function holeQueueSpeicherort(): ?string
    {
        if ($this->holeAusweichVerbindungOderNull() instanceof \PDO) {
            return 'offline';
        }

        try {
            return $this->datenbank->istHauptdatenbankVerfuegbar() ? 'haupt' : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
