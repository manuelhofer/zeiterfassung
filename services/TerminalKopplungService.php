<?php
declare(strict_types=1);

/**
 * TerminalKopplungService
 *
 * Vergibt und prüft die Kopplungscodes, mit denen sich ein Terminal am
 * Backend anmeldet (siehe `docs/spezifikation_terminal_installation.md`,
 * Abschnitt 2a).
 *
 * Grundgedanke: Das Installationsskript eines Terminals kennt **keine**
 * Zugangsdaten. Stattdessen erzeugt die Verwaltung im Backend einen kurzen
 * Code, der am Geraet eingetippt wird; damit holt sich das Terminal alles
 * Weitere selbst.
 *
 * Sicherheitsentscheidungen und ihr Warum:
 *
 * - **Nur der Hash wird gespeichert.** Der Code ist ein Geheimnis auf Zeit und
 *   wird wie ein Passwort behandelt: einmal anzeigen, danach nicht mehr
 *   rekonstruierbar. Wer ihn verliert, erzeugt einen neuen.
 * - **Einmalig verwendbar.** Nach dem Einlösen ist er verbraucht; ein
 *   abgehörter Code nuetzt niemandem mehr.
 * - **Zeitlich begrenzt** (Standard 30 Minuten) - ein vergessener Zettel am
 *   Terminal wird damit von selbst wertlos.
 * - **Alphabet ohne Verwechslungen:** kein O/0, kein I/1/l. Der Code wird an
 *   einem Touchscreen in der Halle abgetippt, oft von einem Zettel.
 * - **Laenge 8 aus 32 Zeichen** ergibt rund 10^12 Möglichkeiten. Ein
 *   Durchprobieren über das Netz ist damit aussichtslos; zusätzlich wird
 *   jeder Fehlversuch protokolliert, damit ein Versuch überhaupt auffällt.
 */
class TerminalKopplungService
{
    /** Zeichen ohne Verwechslungsgefahr (kein O/0, kein I/1/L). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LAENGE = 8;
    private const STANDARD_GUELTIG_MINUTEN = 30;

    private static ?TerminalKopplungService $instanz = null;

    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstanz();
    }

    public static function getInstanz(): TerminalKopplungService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Erzeugt einen Kopplungscode für ein Terminal.
     *
     * Vorhandene, noch offene Codes desselben Terminals werden dabei entwertet:
     * Es soll immer nur ein gültiger Code je Geraet unterwegs sein, sonst
     * weiß niemand mehr, welcher Zettel noch zaehlt.
     *
     * @return string|null Der Code im Klartext - **einmalig**, danach nur noch
     *                     als Hash vorhanden. Null bei Fehler.
     */
    public function erzeugeCode(int $terminalId, ?int $erstelltVonMitarbeiterId = null, int $gueltigMinuten = self::STANDARD_GUELTIG_MINUTEN): ?string
    {
        if ($terminalId <= 0) {
            return null;
        }

        if ($gueltigMinuten < 1) {
            $gueltigMinuten = self::STANDARD_GUELTIG_MINUTEN;
        }

        try {
            $this->entwerteOffeneCodes($terminalId);

            // Bei einer Kollision des Hashes (praktisch ausgeschlossen, aber
            // die Spalte ist UNIQUE) einfach neu wuerfeln.
            for ($versuch = 0; $versuch < 5; $versuch++) {
                $code = $this->wuerfleCode();

                try {
                    $this->db->ausfuehren(
                        'INSERT INTO terminal_kopplung
                            (terminal_id, code_hash, gueltig_bis, erstellt_von_mitarbeiter_id)
                         VALUES
                            (:tid, :hash, DATE_ADD(NOW(), INTERVAL :min MINUTE), :mid)',
                        [
                            'tid'  => $terminalId,
                            'hash' => $this->hashe($code),
                            'min'  => $gueltigMinuten,
                            'mid'  => $erstelltVonMitarbeiterId,
                        ]
                    );
                } catch (\Throwable $e) {
                    continue;
                }

                $this->protokolliere('info', 'Kopplungscode fuer Terminal erzeugt', [
                    'terminal_id'    => $terminalId,
                    'gueltig_minuten' => $gueltigMinuten,
                ], $erstelltVonMitarbeiterId);

                return $code;
            }
        } catch (\Throwable $e) {
            $this->protokolliere('error', 'Kopplungscode konnte nicht erzeugt werden', [
                'terminal_id' => $terminalId,
                'exception'   => $e->getMessage(),
            ], $erstelltVonMitarbeiterId);
        }

        return null;
    }

    /**
     * Prüft einen Code und verbraucht ihn bei Erfolg.
     *
     * Die Prüfung ist bewusst **eine** Abfrage samt Gültigkeitsbedingung, und
     * das Verbrauchen läuft als bedingtes UPDATE. So kann derselbe Code auch
     * bei zwei gleichzeitigen Anfragen nur einmal gewinnen.
     *
     * @param string      $code Klartext-Code, wie am Terminal eingetippt
     * @param string|null $host Kennung des Geraets (Hostname/MAC), nur zur Nachvollziehbarkeit
     *
     * @return array<string,mixed>|null Terminal-Datensatz, oder null wenn der Code nicht gilt
     */
    public function loeseCodeEin(string $code, ?string $host = null): ?array
    {
        $code = $this->normalisiere($code);
        if ($code === '') {
            return null;
        }

        try {
            $zeile = $this->db->fetchEine(
                'SELECT id, terminal_id
                   FROM terminal_kopplung
                  WHERE code_hash = :hash
                    AND verbraucht_am IS NULL
                    AND gueltig_bis >= NOW()
                  LIMIT 1',
                ['hash' => $this->hashe($code)]
            );

            if (!is_array($zeile)) {
                // Bewusst ohne Angabe, ob der Code unbekannt, abgelaufen oder
                // schon verbraucht war - das hilft nur beim Durchprobieren.
                $this->protokolliere('warn', 'Kopplung fehlgeschlagen: Code ungueltig', [
                    'host' => $host,
                ]);
                return null;
            }

            $kopplungId = (int)$zeile['id'];
            $terminalId = (int)$zeile['terminal_id'];

            // Bedingtes UPDATE: gewinnt nur, wer den Code als Erster einloest.
            $betroffen = $this->db->ausfuehren(
                'UPDATE terminal_kopplung
                    SET verbraucht_am = NOW(),
                        verbraucht_von_host = :host
                  WHERE id = :id
                    AND verbraucht_am IS NULL',
                ['id' => $kopplungId, 'host' => $host !== null ? mb_substr($host, 0, 190) : null]
            );

            if ($betroffen < 1) {
                $this->protokolliere('warn', 'Kopplung fehlgeschlagen: Code war bereits verbraucht', [
                    'terminal_id' => $terminalId,
                    'host'        => $host,
                ]);
                return null;
            }

            $terminal = $this->db->fetchEine(
                'SELECT * FROM terminal WHERE id = :id LIMIT 1',
                ['id' => $terminalId]
            );

            if (!is_array($terminal)) {
                $this->protokolliere('error', 'Kopplung: Terminal zum Code nicht gefunden', [
                    'terminal_id' => $terminalId,
                ]);
                return null;
            }

            $this->protokolliere('info', 'Terminal erfolgreich gekoppelt', [
                'terminal_id' => $terminalId,
                'host'        => $host,
            ]);

            return $terminal;
        } catch (\Throwable $e) {
            $this->protokolliere('error', 'Kopplung konnte nicht geprueft werden', [
                'exception' => $e->getMessage(),
                'host'      => $host,
            ]);
            return null;
        }
    }

    /**
     * Offener, noch gültiger Code eines Terminals - für die Anzeige
     * „Kopplung läuft noch bis …“. Der Code selbst ist nicht enthalten.
     *
     * @return array<string,mixed>|null
     */
    public function holeOffeneKopplung(int $terminalId): ?array
    {
        if ($terminalId <= 0) {
            return null;
        }

        try {
            return $this->db->fetchEine(
                'SELECT id, gueltig_bis, erstellt_am
                   FROM terminal_kopplung
                  WHERE terminal_id = :tid
                    AND verbraucht_am IS NULL
                    AND gueltig_bis >= NOW()
                  ORDER BY id DESC
                  LIMIT 1',
                ['tid' => $terminalId]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Entwertet noch offene Codes eines Terminals, indem ihre Gültigkeit
     * zurückgesetzt wird. Gelöscht wird nicht - der Verlauf bleibt
     * nachvollziehbar.
     *
     * Oeffentlich, weil nicht nur ein neuer Code die alten ersetzt: Auch das
     * Entkoppeln muss sie entwerten, sonst holt sich ein gerade abgemeldetes
     * Geraet mit einem noch offenen Code sofort einen neuen Zugang.
     */
    public function entwerteOffeneCodes(int $terminalId): void
    {
        // Eine Sekunde in die Vergangenheit, nicht auf NOW(): Die Prüfung beim
        // Einlösen lässt `gültig_bis >= NOW()` gelten - auf NOW() gesetzt
        // wäre der alte Code also noch eine Sekunde lang gültig.
        $this->db->ausfuehren(
            'UPDATE terminal_kopplung
                SET gueltig_bis = DATE_SUB(NOW(), INTERVAL 1 SECOND)
              WHERE terminal_id = :tid
                AND verbraucht_am IS NULL
                AND gueltig_bis > NOW()',
            ['tid' => $terminalId]
        );
    }

    // ------------------------------------------------------------------
    // Interna
    // ------------------------------------------------------------------

    private function wuerfleCode(): string
    {
        $laenge = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < self::CODE_LAENGE; $i++) {
            try {
                $index = random_int(0, $laenge - 1);
            } catch (\Throwable $e) {
                $index = mt_rand(0, $laenge - 1);
            }
            $code .= self::ALPHABET[$index];
        }

        return $code;
    }

    /**
     * Vereinheitlicht die Eingabe: Grossbuchstaben, keine Leer- oder
     * Trennzeichen. Wer den Code am Touchscreen mit Bindestrich eintippt, soll
     * nicht daran scheitern.
     */
    private function normalisiere(string $code): string
    {
        $code = strtoupper(trim($code));
        return (string)preg_replace('~[^A-Z0-9]~', '', $code);
    }

    private function hashe(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * @param array<string,mixed> $kontext
     */
    private function protokolliere(string $stufe, string $nachricht, array $kontext, ?int $mitarbeiterId = null): void
    {
        switch ($stufe) {
            case 'error':
                Logger::error($nachricht, $kontext, $mitarbeiterId, null, 'terminal_kopplung');
                break;
            case 'warn':
                Logger::warn($nachricht, $kontext, $mitarbeiterId, null, 'terminal_kopplung');
                break;
            default:
                Logger::info($nachricht, $kontext, $mitarbeiterId, null, 'terminal_kopplung');
        }
    }
}
