<?php
declare(strict_types=1);

/**
 * AuftragModel
 *
 * Datenzugriff für die optionale Tabelle `auftrag`.
 * Je nach Einbindung kann diese Tabelle nur als lokaler Cache dienen.
 */
class AuftragModel
{
    private Database $datenbank;

    public function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    /**
     * Sucht einen Auftrag anhand der Auftragsnummer.
     *
     * Hinweis:
     * Im aktuellen DB-Schema (Source of Truth) ist die Kernspalte `auftrag.auftragsnummer`.
     * Die historische Bezeichnung "auftragscode" existiert in der Tabelle `auftrag` nicht
     * (sie wird nur in `auftragszeit.auftragscode` als frei eingegebener Code gespeichert).
     *
     * @return array<string,mixed>|null
     */
    public function findeNachCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        try {
            $sql = 'SELECT *
                    FROM auftrag
                    WHERE auftragsnummer = :code
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['code' => $code]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Suchen eines Auftrags nach Auftragsnummer', [
                    'auftragsnummer' => $code,
                    'exception'      => $e->getMessage(),
                ], null, null, 'auftrag');
            }

            return null;
        }
    }

    /**
     * Lädt einen Auftrag nach ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $sql = 'SELECT *
                    FROM auftrag
                    WHERE id = :id
                    LIMIT 1';

            return $this->datenbank->fetchEine($sql, ['id' => $id]);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Auftrags nach ID', [
                    'id'        => $id,
                    'exception' => $e->getMessage(),
                ], null, null, 'auftrag');
            }

            return null;
        }
    }
}
