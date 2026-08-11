<?php
declare(strict_types=1);

/**
 * UrlaubGenehmigungService
 *
 * Beantwortet genau eine Frage: **Für welche Mitarbeiter darf dieser
 * Genehmiger Urlaub entscheiden?**
 *
 * Vorher beantworteten vier Stellen sie mit je eigener SQL – die
 * Genehmigungsliste gleich zweimal. Das ging so lange gut, wie es nur einen
 * Weg gab (`mitarbeiter_genehmiger`). Mit dem Abteilungsbezug aus B-093 gibt
 * es zwei, und vier Kopien wären vier Gelegenheiten, einen davon zu vergessen
 * – im Zweifel die Anzeige, nicht die Prüfung. Dann sähe jemand einen Antrag,
 * den er nicht entscheiden kann, oder schlimmer: umgekehrt.
 *
 * **Der Zuschnitt ist Absicht und im Namen sichtbar.** Dies ist keine
 * allgemeine Bereichsauflösung für das Rechtesystem, sondern nur die
 * Urlaubsgenehmigung – siehe `docs/spezifikation_abteilungsrechte.md`.
 * Wer hier etwas anderes anbaut, baut am falschen Ort.
 */
class UrlaubGenehmigungService
{
    /**
     * Das einzige Recht, das einen Abteilungsbezug auswertet.
     */
    private const RECHT_CODE = 'URLAUB_GENEHMIGEN';

    private static ?UrlaubGenehmigungService $instanz = null;

    private Database $datenbank;

    private function __construct()
    {
        $this->datenbank = Database::getInstanz();
    }

    public static function getInstanz(): UrlaubGenehmigungService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Alle Mitarbeiter, für die dieser Genehmiger zuständig ist – aus beiden
     * Wegen zusammen: namentlich eingetragen **oder** über eine Abteilung.
     *
     * Der Genehmiger selbst ist nie enthalten. Eigene Anträge hängen
     * ausschliesslich an `URLAUB_GENEHMIGEN_SELF`.
     *
     * @return array<int,int> Mitarbeiter-IDs, aufsteigend
     */
    public function holeZustaendigeMitarbeiterIds(int $genehmigerId): array
    {
        $genehmigerId = (int)$genehmigerId;
        if ($genehmigerId <= 0) {
            return [];
        }

        $ids = [];

        foreach ($this->holeNamentlichZugeordnete($genehmigerId) as $id) {
            $ids[$id] = true;
        }

        foreach ($this->holeUeberAbteilungZugeordnete($genehmigerId) as $id) {
            $ids[$id] = true;
        }

        unset($ids[$genehmigerId]);

        $liste = array_keys($ids);
        sort($liste);

        return $liste;
    }

    /**
     * Darf dieser Genehmiger über den Antrag dieses Mitarbeiters entscheiden?
     *
     * Deckt **nicht** die Sonderfälle ab: eigener Antrag
     * (`URLAUB_GENEHMIGEN_SELF`) und `URLAUB_GENEHMIGEN_ALLE`. Die entscheidet
     * der Aufrufer vorher – hier geht es nur um die Zuständigkeit.
     */
    public function istZustaendigFuer(int $genehmigerId, int $mitarbeiterId): bool
    {
        $mitarbeiterId = (int)$mitarbeiterId;
        if ($mitarbeiterId <= 0) {
            return false;
        }

        return in_array($mitarbeiterId, $this->holeZustaendigeMitarbeiterIds($genehmigerId), true);
    }

    /**
     * Hat dieser Mitarbeiter überhaupt einen Genehmigungsauftrag – über einen
     * der beiden Wege?
     *
     * Für die Zugangsprüfung der Genehmigungsliste: Wer für niemanden
     * zuständig ist, bekommt die Maske gar nicht erst zu sehen.
     */
    public function istGenehmigerFuerIrgendwen(int $genehmigerId): bool
    {
        return $this->holeZustaendigeMitarbeiterIds($genehmigerId) !== [];
    }

    /**
     * Hat dieser Mitarbeiter `URLAUB_GENEHMIGEN` über eine abteilungsbezogen
     * zugewiesene Rolle?
     *
     * Nötig, weil `AuthService::hatRecht()` nur betriebsweite Zuweisungen
     * kennt und das absichtlich so bleibt: Eine abteilungsbezogene Rolle
     * gewährt **nur** dieses eine Recht, nicht ihren ganzen Inhalt.
     */
    public function hatGenehmigungsrechtUeberAbteilung(int $genehmigerId): bool
    {
        return $this->holeAbteilungsZuweisungen($genehmigerId) !== [];
    }

    /**
     * Namentliche Zuordnung – der bisherige Weg (`mitarbeiter_genehmiger`).
     *
     * @return array<int,int>
     */
    private function holeNamentlichZugeordnete(int $genehmigerId): array
    {
        try {
            $zeilen = $this->datenbank->fetchAlle(
                'SELECT mitarbeiter_id
                 FROM mitarbeiter_genehmiger
                 WHERE genehmiger_mitarbeiter_id = :gid',
                ['gid' => $genehmigerId]
            );
        } catch (\Throwable $e) {
            Logger::error('Urlaubsgenehmigung: namentliche Zuordnung nicht lesbar', [
                'genehmiger_id' => $genehmigerId,
                'exception'     => $e->getMessage(),
            ], $genehmigerId, null, 'urlaub_genehmigung');

            return [];
        }

        $ids = [];
        foreach ($zeilen as $zeile) {
            $id = (int)($zeile['mitarbeiter_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Zuordnung über die Abteilung – der neue Weg aus B-093.
     *
     * @return array<int,int>
     */
    private function holeUeberAbteilungZugeordnete(int $genehmigerId): array
    {
        $abteilungIds = $this->holeZustaendigeAbteilungIds($genehmigerId);
        if ($abteilungIds === []) {
            return [];
        }

        // Feste Platzhalter statt eingesetzter Zahlen: Die IDs sind zwar
        // bereits nach `int` gecastet, aber eine IN-Liste von Hand
        // zusammenzubauen ist genau die Gewohnheit, die irgendwann bei einem
        // Wert ohne Cast endet.
        $platzhalter = [];
        $parameter   = [];
        foreach (array_values($abteilungIds) as $i => $id) {
            $platzhalter[]        = ':a' . $i;
            $parameter['a' . $i]  = $id;
        }

        try {
            $zeilen = $this->datenbank->fetchAlle(
                'SELECT DISTINCT mha.mitarbeiter_id
                 FROM mitarbeiter_hat_abteilung mha
                 WHERE mha.abteilung_id IN (' . implode(', ', $platzhalter) . ')',
                $parameter
            );
        } catch (\Throwable $e) {
            Logger::error('Urlaubsgenehmigung: Abteilungszuordnung nicht lesbar', [
                'genehmiger_id' => $genehmigerId,
                'exception'     => $e->getMessage(),
            ], $genehmigerId, null, 'urlaub_genehmigung');

            return [];
        }

        $ids = [];
        foreach ($zeilen as $zeile) {
            $id = (int)($zeile['mitarbeiter_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Alle Abteilungen, für die dieser Genehmiger zuständig ist – die direkt
     * zugewiesenen und, wo `gilt_unterbereiche` gesetzt ist, deren Unterbaum.
     *
     * @return array<int,int>
     */
    private function holeZustaendigeAbteilungIds(int $genehmigerId): array
    {
        $zuweisungen = $this->holeAbteilungsZuweisungen($genehmigerId);
        if ($zuweisungen === []) {
            return [];
        }

        $ids = [];
        foreach ($zuweisungen as $zuweisung) {
            $ids[$zuweisung['abteilung_id']] = true;

            if ($zuweisung['gilt_unterbereiche']) {
                foreach ($this->holeUnterbaum($zuweisung['abteilung_id']) as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Die abteilungsbezogenen Rollenzuweisungen, die `URLAUB_GENEHMIGEN`
     * enthalten.
     *
     * @return array<int,array{abteilung_id:int,gilt_unterbereiche:bool}>
     */
    private function holeAbteilungsZuweisungen(int $genehmigerId): array
    {
        $genehmigerId = (int)$genehmigerId;
        if ($genehmigerId <= 0) {
            return [];
        }

        try {
            // `UPPER` auf beiden Seiten: Die Rechtecodes werden im ganzen
            // Projekt ohne Rücksicht auf Gross-/Kleinschreibung verglichen
            // (`AuthService::hatRecht()`), das muss hier genauso sein.
            $zeilen = $this->datenbank->fetchAlle(
                "SELECT s.scope_id, s.gilt_unterbereiche
                 FROM mitarbeiter_hat_rolle_scope s
                 INNER JOIN rolle_hat_recht rhr ON rhr.rolle_id = s.rolle_id
                 INNER JOIN recht r ON r.id = rhr.recht_id
                 WHERE s.mitarbeiter_id = :mid
                   AND s.scope_typ = 'abteilung'
                   AND s.scope_id > 0
                   AND r.aktiv = 1
                   AND UPPER(r.code) = :code",
                ['mid' => $genehmigerId, 'code' => self::RECHT_CODE]
            );
        } catch (\Throwable $e) {
            // Fehlt die Tabelle (Altbestand), gibt es schlicht keinen
            // Abteilungsbezug - das ist kein Grund, die Genehmigung zu
            // blockieren.
            return [];
        }

        $out = [];
        foreach ($zeilen as $zeile) {
            $abteilungId = (int)($zeile['scope_id'] ?? 0);
            if ($abteilungId <= 0) {
                continue;
            }

            $out[] = [
                'abteilung_id'       => $abteilungId,
                'gilt_unterbereiche' => (int)($zeile['gilt_unterbereiche'] ?? 0) === 1,
            ];
        }

        return $out;
    }

    /**
     * Alle Abteilungen unterhalb der angegebenen, beliebig tief.
     *
     * Iterativ mit Besuchsliste statt Rekursion: `abteilung.parent_id` zeigt
     * auf dieselbe Tabelle, und eine Schleife darin ist zwar Unsinn, aber
     * eintragbar (siehe Spezifikation, Abschnitt 7). Rekursion liefe darin
     * bis zum Speicherende, das hier hört nach dem ersten Wiedersehen auf.
     *
     * @return array<int,int>
     */
    private function holeUnterbaum(int $wurzelId): array
    {
        $gefunden = [];
        $offen    = [$wurzelId];
        $besucht  = [$wurzelId => true];

        while ($offen !== []) {
            $aktuell = array_shift($offen);

            try {
                $kinder = $this->datenbank->fetchAlle(
                    'SELECT id FROM abteilung WHERE parent_id = :pid',
                    ['pid' => $aktuell]
                );
            } catch (\Throwable $e) {
                Logger::error('Urlaubsgenehmigung: Abteilungsbaum nicht lesbar', [
                    'abteilung_id' => $aktuell,
                    'exception'    => $e->getMessage(),
                ], null, null, 'urlaub_genehmigung');

                break;
            }

            foreach ($kinder as $kind) {
                $id = (int)($kind['id'] ?? 0);
                if ($id <= 0 || isset($besucht[$id])) {
                    continue;
                }

                $besucht[$id]  = true;
                $gefunden[$id] = true;
                $offen[]       = $id;
            }
        }

        return array_keys($gefunden);
    }
}
