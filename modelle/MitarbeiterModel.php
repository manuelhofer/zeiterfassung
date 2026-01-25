<?php
declare(strict_types=1);

/**
 * MitarbeiterModel
 *
 * Datenzugriff für Tabelle `mitarbeiter`.
 */
class MitarbeiterModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstanz();
    }


    /**
     * Prüft, ob es mindestens einen aktiven, login-berechtigten Mitarbeiter gibt.
     */
    public function existiertLoginberechtigterMitarbeiter(): bool
    {
        $sql = 'SELECT COUNT(*) AS anzahl
                FROM mitarbeiter
                WHERE aktiv = 1
                  AND ist_login_berechtigt = 1';

        $row = $this->db->fetchEine($sql);

        if ($row === null) {
            return false;
        }

        return (int)($row['anzahl'] ?? 0) > 0;
    }

    /**
     * Gibt die IDs aller aktiven, login-berechtigten Mitarbeiter zurück.
     *
     * @return int[]
     */
    public function holeAktiveLoginberechtigteIds(): array
    {
        $sql = 'SELECT id
                FROM mitarbeiter
                WHERE aktiv = 1
                  AND ist_login_berechtigt = 1';

        try {
            $rows = $this->db->fetchAlle($sql);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Lesen der login-berechtigten Mitarbeiter', [
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $ids[] = (int)$row['id'];
            }
        }

        return $ids;
    }




    /**
     * Holt einen Mitarbeiter per ID.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachId(int $id): ?array
    {
        $sql = 'SELECT *
                FROM mitarbeiter
                WHERE id = :id
                LIMIT 1';

        $daten = $this->db->fetchEine($sql, ['id' => $id]);

        return $daten === null ? null : $daten;
    }

    /**
     * Holt einen aktiven, login-berechtigten Mitarbeiter anhand Benutzername oder E-Mail.
     *
     * @return array<string,mixed>|null
     */
        public function holeLoginFaehigenNachKennung(string $kennung): ?array
    {
        $sql = 'SELECT *
                FROM mitarbeiter
                WHERE aktiv = 1
                  AND ist_login_berechtigt = 1
                  AND (benutzername = :kennung_benutzer OR email = :kennung_email)
                LIMIT 1';

        $parameter = [
            'kennung_benutzer' => $kennung,
            'kennung_email'    => $kennung,
        ];

        $daten = $this->db->fetchEine($sql, $parameter);

        return $daten === null ? null : $daten;
    }
    /**
     * Holt einen aktiven Mitarbeiter anhand RFID-Code.
     *
     * @return array<string,mixed>|null
     */
    public function holeNachRfidCode(string $rfidCode): ?array
    {
        $sql = 'SELECT *
                FROM mitarbeiter
                WHERE aktiv = 1
                  AND rfid_code = :rfid
                LIMIT 1';

        $daten = $this->db->fetchEine($sql, ['rfid' => $rfidCode]);

        return $daten === null ? null : $daten;
    }

    /**
     * Holt alle aktiven Mitarbeiter (z. B. für Admin-Listen).
     *
     * @return array<int,array<string,mixed>>
     */
    public function holeAlleAktiven(): array
    {
        $sql = 'SELECT *
                FROM mitarbeiter
                WHERE aktiv = 1
                ORDER BY nachname ASC, vorname ASC';

        return $this->db->fetchAlle($sql);
    }


    /**
     * Holt Mitarbeiter anhand einer Liste von IDs.
     *
     * Standard: liefert auch inaktive Mitarbeiter (wichtig, damit Genehmiger
     * beim Bearbeiten weiterhin im Dropdown sichtbar sind, selbst wenn sie
     * später deaktiviert wurden).
     *
     * @param int[] $ids
     * @param bool $nurAktive Wenn true, werden nur aktive Mitarbeiter geladen.
     * @return array<int,array<string,mixed>>
     */
    public function holeNachIds(array $ids, bool $nurAktive = false): array
    {
        // IDs bereinigen
        $bereinigt = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $bereinigt[$id] = true;
            }
        }

        if (count($bereinigt) === 0) {
            return [];
        }

        $params = [];
        $platzhalter = [];
        $i = 0;
        foreach (array_keys($bereinigt) as $id) {
            $key = ':id' . $i;
            $params[$key] = (int)$id;
            $platzhalter[] = $key;
            $i++;
        }

        $sql = 'SELECT *
                FROM mitarbeiter
                WHERE id IN (' . implode(',', $platzhalter) . ')';

        if ($nurAktive) {
            $sql .= ' AND aktiv = 1';
        }

        $sql .= ' ORDER BY nachname ASC, vorname ASC';

        try {
            return $this->db->fetchAlle($sql, $params);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden mehrerer Mitarbeiter per ID-Liste', [
                    'ids'       => array_keys($bereinigt),
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }

            return [];
        }
    }

    /**
     * Aktualisiert den Passwort-Hash eines Mitarbeiters.
     */


    /**
     * Legt einen neuen Mitarbeiter an.
     *
     * @param array<string,mixed> $daten
     * @return int|null ID des neuen Mitarbeiters oder null bei Fehler.
     */
    public function erstelleMitarbeiter(array $daten): ?int
    {
        $sql = 'INSERT INTO mitarbeiter (
                    vorname,
                    nachname,
                    geburtsdatum,
                    wochenarbeitszeit,
                    urlaub_monatsanspruch,
                    benutzername,
                    email,
                    passwort_hash,
                    rfid_code,
                    aktiv,
                    ist_login_berechtigt
                ) VALUES (
                    :vorname,
                    :nachname,
                    :geburtsdatum,
                    :wochenarbeitszeit,
                    :urlaub_monatsanspruch,
                    :benutzername,
                    :email,
                    :passwort_hash,
                    :rfid_code,
                    :aktiv,
                    :ist_login_berechtigt
                )';

        $params = [
            'vorname'               => (string)$daten['vorname'],
            'nachname'              => (string)$daten['nachname'],
            'geburtsdatum'          => $daten['geburtsdatum'],
            'wochenarbeitszeit'     => $daten['wochenarbeitszeit'],
            'urlaub_monatsanspruch' => $daten['urlaub_monatsanspruch'],
            'benutzername'          => $daten['benutzername'],
            'email'                 => $daten['email'],
            'passwort_hash'         => $daten['passwort_hash'],
            'rfid_code'             => $daten['rfid_code'],
            'aktiv'                 => $daten['aktiv'],
            'ist_login_berechtigt'  => $daten['ist_login_berechtigt'],
        ];

        try {
            $this->db->ausfuehren($sql, $params);
            return (int)$this->db->letzteInsertId();
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Anlegen eines Mitarbeiters', [
                    'daten'     => $daten,
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }

            return null;
        }
    }

    /**
     * Aktualisiert einen vorhandenen Mitarbeiter.
     *
     * @param int $id
     * @param array<string,mixed> $daten
     * @return bool
     */
    public function aktualisiereMitarbeiter(int $id, array $daten): bool
    {
        $sql = 'UPDATE mitarbeiter
                SET vorname               = :vorname,
                    nachname              = :nachname,
                    geburtsdatum          = :geburtsdatum,
                    wochenarbeitszeit     = :wochenarbeitszeit,
                    urlaub_monatsanspruch = :urlaub_monatsanspruch,
                    benutzername          = :benutzername,
                    email                 = :email,
                    passwort_hash         = :passwort_hash,
                    rfid_code             = :rfid_code,
                    aktiv                 = :aktiv,
                    ist_login_berechtigt  = :ist_login_berechtigt
                WHERE id = :id';

        $params = [
            'vorname'               => (string)$daten['vorname'],
            'nachname'              => (string)$daten['nachname'],
            'geburtsdatum'          => $daten['geburtsdatum'],
            'wochenarbeitszeit'     => $daten['wochenarbeitszeit'],
            'urlaub_monatsanspruch' => $daten['urlaub_monatsanspruch'],
            'benutzername'          => $daten['benutzername'],
            'email'                 => $daten['email'],
            'passwort_hash'         => $daten['passwort_hash'],
            'rfid_code'             => $daten['rfid_code'],
            'aktiv'                 => $daten['aktiv'],
            'ist_login_berechtigt'  => $daten['ist_login_berechtigt'],
            'id'                    => $id,
        ];

        try {
            $this->db->ausfuehren($sql, $params);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Aktualisieren eines Mitarbeiters', [
                    'id'        => $id,
                    'daten'     => $daten,
                    'exception' => $e->getMessage(),
                ], $id, null, 'mitarbeiter');
            }

            return false;
        }
    }

function aktualisierePasswortHash(int $id, string $passwortHash): void
    {
        $sql = 'UPDATE mitarbeiter
                SET passwort_hash = :hash
                WHERE id = :id';

        $params = [
            'hash' => $passwortHash,
            'id'   => $id,
        ];

        $this->db->ausfuehren($sql, $params);
    }
}