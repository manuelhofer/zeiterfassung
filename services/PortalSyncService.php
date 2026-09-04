<?php
declare(strict_types=1);

/**
 * Der Abgleich mit dem Mitarbeiterportal: abholen, anlegen, melden.
 *
 * EIN LAUF IST IMMER DERSELBE DREISCHRITT:
 *
 *   1. `abholen` - was haben die Mitarbeiter im Portal eingetragen?
 *   2. Verarbeiten - Antraege anlegen, Stornos ausfuehren, jeweils mit
 *      **derselben** Pruefung wie am Terminal.
 *   3. `melden` - Ergebnisse und der neue Stand des Schaufensters in einem
 *      Aufruf.
 *
 * WARUM DIE PRUEFUNG DIESELBE SEIN MUSS: Ein Antrag aus dem Portal ist kein
 * Sonderfall, sondern ein Urlaubsantrag. Waeren die Regeln hier andere, gaebe
 * es zwei Wahrheiten darueber, was ein zulaessiger Antrag ist - und die
 * Portalvariante waere die, die niemand pflegt. Deshalb ruft dieser Dienst
 * dieselben Methoden des `UrlaubService` auf wie `TerminalController` und
 * `UrlaubController`.
 *
 * DER DOPPELSCHUTZ IST DAS EIGENTLICHE HANDWERK. Jeder Auftrag traegt eine
 * Nummer, die die Homepage vergeben hat. Bevor ein Urlaubsantrag entsteht,
 * wird diese Nummer in `portal_eingang` geschrieben - in **derselben**
 * Transaktion. Bricht der Lauf davor ab, kommt der Auftrag beim naechsten Mal
 * wieder. Bricht er danach ab, kommt er auch wieder - und wird am eindeutigen
 * Schluessel als Doppel erkannt und nur noch einmal rueckgemeldet
 * (docs/spezifikation_mitarbeiterportal.md, Abschnitt 6).
 */
class PortalSyncService
{
    /** Wie viele Auftraege ein Lauf hoechstens abholt. */
    private const GRENZE = 100;

    /**
     * Wie oft die Monatslisten mitgeschickt werden.
     *
     * Sie sind der mit Abstand groesste Teil des Spiegels - dreizehn
     * Mitarbeiter mal zwei Monate mal dreissig Tage. Alle zwei Minuten waere
     * das viel Verkehr fuer Zahlen, die sich hoechstens beim Stempeln aendern.
     * Zehn Minuten sind ein Kompromiss: Wer im Portal auf die Monatsuebersicht
     * sieht, bekommt sie hoechstens zehn Minuten alt - und das Portal schreibt
     * ohnehin dazu, wie alt sein Stand ist.
     */
    private const MONATE_TAKT_MINUTEN = 10;

    private const SCHLUESSEL_MONATE = 'portal_monate_zuletzt';

    private Database $datenbank;
    private PortalVerbindungService $verbindung;

    public function __construct()
    {
        $this->datenbank  = Database::getInstanz();
        $this->verbindung = PortalVerbindungService::getInstanz();
    }

    /**
     * Ein vollstaendiger Lauf.
     *
     * @return array{ok:bool, zeilen:array<int,string>, fehler:string, dauer_ms:int}
     */
    public function laufen(string $anlass = 'zeitplan'): array
    {
        $start  = microtime(true);
        $zeilen = [];

        if (!$this->verbindung->istGekoppelt()) {
            return ['ok' => false, 'zeilen' => [], 'dauer_ms' => 0,
                    'fehler' => 'Es ist keine Website gekoppelt.'];
        }

        // Schritt 1: abholen
        $abholung = $this->verbindung->sende('abholen', ['grenze' => self::GRENZE]);
        if (!$abholung['ok']) {
            return $this->beenden(false, $zeilen, $abholung['meldung'], $start);
        }

        $auftraege = is_array($abholung['daten']['auftraege'] ?? null) ? $abholung['daten']['auftraege'] : [];
        $zeilen[] = count($auftraege) . ' Auftrag/Aufträge abgeholt.';

        // Schritt 2: verarbeiten
        $ergebnisse = [];
        foreach ($auftraege as $auftrag) {
            $ergebnis = $this->auftragVerarbeiten($auftrag);
            if ($ergebnis !== null) {
                $ergebnisse[] = $ergebnis;
                $zeilen[] = 'Auftrag ' . $ergebnis['id'] . ' (' . (string)($auftrag['art'] ?? '?') . '): '
                          . $ergebnis['status']
                          . ($ergebnis['hinweis'] !== '' ? ' – ' . $ergebnis['hinweis'] : '');
            }
        }

        // Schritt 3: melden
        $mitMonaten = $this->monateFaellig();
        $spiegel = $this->spiegelBauen($mitMonaten);
        $zeilen[] = 'Spiegel: ' . count($spiegel['mitarbeiter']) . ' Mitarbeiter'
                  . ($mitMonaten ? ', mit Monatslisten' : ', ohne Monatslisten');

        $meldung = $this->verbindung->sende('melden', [
            'ergebnisse' => $ergebnisse,
            'spiegel'    => $spiegel,
        ]);
        if (!$meldung['ok']) {
            // Die Antraege sind angelegt, aber die Website weiss es nicht.
            // Das ist kein Datenverlust: Sie gibt dieselben Auftraege beim
            // naechsten Lauf noch einmal heraus, und der Doppelschutz macht
            // daraus keine zweiten Antraege, sondern nur eine zweite Meldung.
            return $this->beenden(false, $zeilen, $meldung['meldung'], $start);
        }

        foreach ((array)($meldung['daten']['aufgenommen'] ?? []) as $bericht) {
            $zeilen[] = (string)$bericht;
        }

        // Was nur die Website weiss, kommt in der Antwort zurueck.
        $this->zustandUebernehmen((array)($meldung['daten']['zustand'] ?? []));

        if ($mitMonaten) {
            KonfigurationService::getInstanz()->set(
                self::SCHLUESSEL_MONATE,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'string',
                'Wann die Monatslisten zuletzt an das Mitarbeiterportal gingen. Vom Programm gepflegt.'
            );
        }

        return $this->beenden(true, $zeilen, '', $start);
    }

    // ------------------------------------------------------- Verarbeitung

    /**
     * Ein einzelner Auftrag - mit Doppelschutz.
     *
     * @param array<string,mixed> $auftrag
     * @return array{id:int,status:string,hinweis:string,antrag?:int,datei?:string,dateiname?:string,bezeichnung?:string}|null
     */
    private function auftragVerarbeiten(array $auftrag): ?array
    {
        $fremdId = (int)($auftrag['id'] ?? 0);
        $art     = (string)($auftrag['art'] ?? '');
        $fremdMa = (int)($auftrag['mitarbeiter'] ?? 0);
        $daten   = is_array($auftrag['daten'] ?? null) ? $auftrag['daten'] : [];

        if ($fremdId <= 0) {
            return null;
        }

        $portalId = (string)($this->verbindung->verbindung()['portal_id'] ?? '');

        // Schon einmal verarbeitet? Dann nur das alte Ergebnis noch einmal
        // melden. Das ist der ganze Zweck der Eingangstabelle.
        $bekannt = $this->datenbank->fetchEine(
            'SELECT ergebnis, hinweis, urlaubsantrag_id FROM portal_eingang
              WHERE portal_id = :p AND fremd_id = :f LIMIT 1',
            [':p' => $portalId, ':f' => $fremdId]
        );
        if ($bekannt !== null) {
            $ergebnis = [
                'id'      => $fremdId,
                'status'  => (string)$bekannt['ergebnis'],
                'hinweis' => (string)($bekannt['hinweis'] ?? ''),
            ];
            if ($bekannt['urlaubsantrag_id'] !== null) {
                $ergebnis['antrag'] = (int)$bekannt['urlaubsantrag_id'];
            }
            return $ergebnis;
        }

        // Der Mitarbeiter muss es geben, aktiv und freigeschaltet sein. Eine
        // entzogene Freischaltung darf keinen Antrag mehr durchlassen, auch
        // wenn er vorher im Briefkasten lag.
        $mitarbeiter = $this->datenbank->fetchEine(
            'SELECT id, vorname, nachname FROM mitarbeiter
              WHERE id = :id AND aktiv = 1 AND portal_aktiv = 1 LIMIT 1',
            [':id' => $fremdMa]
        );
        if ($mitarbeiter === null) {
            return $this->festhalten($portalId, $fremdId, $art, null, 'abgelehnt',
                'Für diesen Mitarbeiter ist das Portal nicht (mehr) freigeschaltet.');
        }

        return match ($art) {
            'urlaub_antrag' => $this->urlaubAntrag($portalId, $fremdId, $art, (int)$mitarbeiter['id'], $daten),
            'urlaub_storno' => $this->urlaubStorno($portalId, $fremdId, $art, (int)$mitarbeiter['id'], $daten),
            default         => $this->festhalten($portalId, $fremdId, $art, (int)$mitarbeiter['id'], 'abgelehnt',
                'Diese Art von Auftrag kennt die Zeiterfassung nicht: ' . mb_substr($art, 0, 40)),
        };
    }

    /**
     * Urlaubsantrag anlegen - mit derselben Pruefkette wie am Terminal.
     *
     * @param array<string,mixed> $daten
     */
    private function urlaubAntrag(string $portalId, int $fremdId, string $art, int $mitarbeiterId, array $daten): array
    {
        $von       = trim((string)($daten['von'] ?? ''));
        $bis       = trim((string)($daten['bis'] ?? ''));
        $kommentar = trim((string)($daten['kommentar'] ?? ''));

        $vonDt = DateTimeImmutable::createFromFormat('Y-m-d', $von);
        $bisDt = DateTimeImmutable::createFromFormat('Y-m-d', $bis);
        if (!$vonDt || $vonDt->format('Y-m-d') !== $von || !$bisDt || $bisDt->format('Y-m-d') !== $bis) {
            return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt',
                'Das Datum ist nicht lesbar. Bitte den Antrag im Portal noch einmal stellen.');
        }
        if ($vonDt > $bisDt) {
            return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt',
                'Das Bis-Datum liegt vor dem Von-Datum.');
        }
        if (mb_strlen($kommentar) > 2000) {
            $kommentar = mb_substr($kommentar, 0, 2000);
        }

        $urlaub = UrlaubService::getInstanz();

        $ueberlappung = $urlaub->findeUeberlappendenAktivenUrlaub($mitarbeiterId, $von, $bis);
        if ($ueberlappung !== null) {
            return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt',
                'Es gibt bereits einen offenen oder genehmigten Antrag im Zeitraum '
                . (string)($ueberlappung['von_datum'] ?? '?') . ' bis '
                . (string)($ueberlappung['bis_datum'] ?? '?') . '.');
        }

        $tage = $urlaub->berechneTageGesamtAlsArbeitstageString($mitarbeiterId, $von, $bis);

        // B-075: Ein Antrag ueber null verrechenbare Tage erzeugt nur einen
        // verwirrenden Eintrag in »Meine Urlaubsanträge«.
        if ((float)$tage <= 0.0) {
            return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt',
                'Der Zeitraum enthält keine verrechenbaren Urlaubstage (Wochenende, Feiertag oder Betriebsferien).');
        }

        if ($urlaub->istNegativerResturlaubGeblockt()) {
            $meldung = $urlaub->pruefeNegativenResturlaubBeiNeuemAntrag($mitarbeiterId, $von, $bis);
            if ($meldung !== null) {
                return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt', $meldung);
            }
        }

        // Ab hier wird geschrieben: Eingangszeile und Antrag in EINER
        // Transaktion, und die Eingangszeile zuerst.
        $eigene = $this->datenbank->transaktionStarten();
        try {
            $this->datenbank->ausfuehren(
                'INSERT INTO portal_eingang (portal_id, fremd_id, art, mitarbeiter_id, ergebnis, hinweis)
                 VALUES (:p, :f, :a, :m, :e, NULL)',
                [':p' => $portalId, ':f' => $fremdId, ':a' => $art, ':m' => $mitarbeiterId, ':e' => 'angenommen']
            );
            $eingangId = (int)$this->datenbank->letzteInsertId();

            $this->datenbank->ausfuehren(
                'INSERT INTO urlaubsantrag (mitarbeiter_id, von_datum, bis_datum, tage_gesamt, kommentar_mitarbeiter)
                 VALUES (:mid, :von, :bis, :tage, :kommentar)',
                [
                    ':mid'       => $mitarbeiterId,
                    ':von'       => $von,
                    ':bis'       => $bis,
                    ':tage'      => $tage,
                    ':kommentar' => $kommentar !== '' ? $kommentar : null,
                ]
            );
            $antragId = (int)$this->datenbank->letzteInsertId();

            $this->datenbank->ausfuehren(
                'UPDATE portal_eingang SET urlaubsantrag_id = :a WHERE id = :id',
                [':a' => $antragId, ':id' => $eingangId]
            );

            $this->datenbank->transaktionAbschliessen($eigene);
        } catch (Throwable $fehler) {
            $this->datenbank->transaktionZuruecknehmen($eigene);
            Logger::error('Portal: Urlaubsantrag konnte nicht angelegt werden', [
                'fremd_id'  => $fremdId,
                'exception' => $fehler->getMessage(),
            ], $mitarbeiterId, null, 'portal');

            // **Kein** Eintrag in `portal_eingang` - die Transaktion ist
            // zurueckgenommen. Der Auftrag kommt beim naechsten Lauf wieder,
            // und das ist richtig so: Vielleicht war es nur ein Aussetzer.
            return ['id' => $fremdId, 'status' => 'abgelehnt', 'hinweis' =>
                'Der Antrag ließ sich gerade nicht speichern. Die Zeiterfassung versucht es erneut.'];
        }

        Logger::info('Portal: Urlaubsantrag angelegt', [
            'urlaubsantrag_id' => $antragId,
            'von'              => $von,
            'bis'              => $bis,
            'tage'             => $tage,
        ], $mitarbeiterId, null, 'portal');

        return ['id' => $fremdId, 'status' => 'angenommen', 'hinweis' => '', 'antrag' => $antragId];
    }

    /**
     * Einen eigenen, offenen Antrag zuruecknehmen.
     *
     * Die Bedingung `mitarbeiter_id = :mid` im UPDATE ist kein Beiwerk: Sie
     * ist die Stelle, an der verhindert wird, dass ein Auftrag von der
     * Homepage einen fremden Antrag storniert. Ohne sie waere eine
     * uebernommene Website ein Werkzeug, um Urlaub anderer Leute zu loeschen.
     *
     * @param array<string,mixed> $daten
     */
    private function urlaubStorno(string $portalId, int $fremdId, string $art, int $mitarbeiterId, array $daten): array
    {
        $antragId = (int)($daten['antrag'] ?? 0);
        if ($antragId <= 0) {
            return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt',
                'Es wurde kein Antrag genannt.');
        }

        $betroffen = $this->datenbank->ausfuehren(
            "UPDATE urlaubsantrag SET status = 'storniert'
              WHERE id = :id AND mitarbeiter_id = :mid AND status = 'offen' LIMIT 1",
            [':id' => $antragId, ':mid' => $mitarbeiterId]
        );

        if ($betroffen < 1) {
            return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'abgelehnt',
                'Dieser Antrag lässt sich nicht mehr zurücknehmen – er ist bereits entschieden '
                . 'oder gehört zu einem anderen Mitarbeiter.');
        }

        Logger::info('Portal: Urlaubsantrag storniert', [
            'urlaubsantrag_id' => $antragId,
        ], $mitarbeiterId, null, 'portal');

        return $this->festhalten($portalId, $fremdId, $art, $mitarbeiterId, 'angenommen', '', $antragId);
    }

    /**
     * Haelt ein Ergebnis in `portal_eingang` fest und gibt es zurueck.
     *
     * @return array{id:int,status:string,hinweis:string,antrag?:int}
     */
    private function festhalten(string $portalId, int $fremdId, string $art, ?int $mitarbeiterId,
                                string $ergebnis, string $hinweis, ?int $antragId = null): array
    {
        try {
            $this->datenbank->ausfuehren(
                'INSERT INTO portal_eingang (portal_id, fremd_id, art, mitarbeiter_id, ergebnis, hinweis, urlaubsantrag_id)
                 VALUES (:p, :f, :a, :m, :e, :h, :u)',
                [':p' => $portalId, ':f' => $fremdId, ':a' => $art, ':m' => $mitarbeiterId,
                 ':e' => $ergebnis, ':h' => $hinweis !== '' ? $hinweis : null, ':u' => $antragId]
            );
        } catch (Throwable) {
            // Der eindeutige Schluessel hat zugeschlagen - dann steht das
            // Ergebnis schon da, und das ist genau der gewuenschte Fall.
        }

        $aus = ['id' => $fremdId, 'status' => $ergebnis, 'hinweis' => $hinweis];
        if ($antragId !== null) {
            $aus['antrag'] = $antragId;
        }
        return $aus;
    }

    // ------------------------------------------------------------ Spiegel

    /**
     * Baut den Stand des Schaufensters.
     *
     * **Nur freigeschaltete Mitarbeiter**, und zu ihnen nur, was das Portal
     * anzeigt. Kein Geburtsdatum, kein Lohn, kein RFID-Code, keine
     * E-Mail-Adresse (docs/spezifikation_mitarbeiterportal.md, Abschnitt 7).
     *
     * @return array<string,mixed>
     */
    private function spiegelBauen(bool $mitMonaten): array
    {
        $jahr  = (int)date('Y');
        $monat = (int)date('n');

        $mitarbeiter = $this->datenbank->fetchAlle(
            'SELECT id, vorname, nachname, portal_kennung,
                    portal_aktivierung_hash, portal_aktivierung_bis
               FROM mitarbeiter
              WHERE aktiv = 1 AND portal_aktiv = 1 AND portal_kennung IS NOT NULL
              ORDER BY nachname, vorname'
        );

        $spiegel = [
            'mitarbeiter'   => [],
            'urlaub'        => [],
            'antraege'      => [],
            'stunden'       => [],
            'abwesenheiten' => $this->abwesenheiten($jahr),
        ];
        if ($mitMonaten) {
            $spiegel['monate'] = [];
        }

        $urlaub      = UrlaubService::getInstanz();
        $stundenkonto = StundenkontoService::getInstanz();
        $report      = ReportService::getInstanz();

        foreach ($mitarbeiter as $m) {
            $id = (int)$m['id'];

            $spiegel['mitarbeiter'][] = [
                'id'               => $id,
                'kennung'          => (string)$m['portal_kennung'],
                'anzeigename'      => trim((string)$m['vorname'] . ' ' . (string)$m['nachname']),
                'aktiv'            => true,
                'aktivierung_hash' => (string)($m['portal_aktivierung_hash'] ?? ''),
                'aktivierung_bis'  => $m['portal_aktivierung_bis'],
            ];

            // Urlaub: laufendes Jahr und Folgejahr - beantragen darf man in
            // beiden (Fachregel Urlaub, Abschnitt 1).
            foreach ([$jahr, $jahr + 1] as $j) {
                $saldo = $urlaub->berechneUrlaubssaldoFuerJahr($id, $j);
                $spiegel['urlaub'][] = [
                    'mitarbeiter' => $id,
                    'jahr'        => $j,
                    'uebertrag'   => (float)$saldo['uebertrag'],
                    'anspruch'    => (float)$saldo['anspruch'],
                    // »Verbraucht« ist, was genommen und was beantragt ist -
                    // dieselbe Summe, die auch die Backend-Masken zeigen.
                    'verbraucht'  => (float)$saldo['genommen'] + (float)$saldo['beantragt'],
                    'uebrig'      => (float)$saldo['verbleibend'],
                ];
            }

            foreach ($urlaub->holeAntraegeFuerMitarbeiter($id) as $antrag) {
                $spiegel['antraege'][] = [
                    'mitarbeiter'           => $id,
                    'id'                    => (int)$antrag['id'],
                    'von'                   => (string)$antrag['von_datum'],
                    'bis'                   => (string)$antrag['bis_datum'],
                    'tage'                  => (float)$antrag['tage_gesamt'],
                    'status'                => (string)$antrag['status'],
                    'kommentar_mitarbeiter' => (string)($antrag['kommentar_mitarbeiter'] ?? ''),
                    'kommentar_genehmiger'  => (string)($antrag['kommentar_genehmiger'] ?? ''),
                    'antrags_datum'         => $antrag['antrags_datum'] ?? null,
                    'entscheidungs_datum'   => $antrag['entscheidungs_datum'] ?? null,
                ];
            }

            $monatsdaten = $report->holeMonatsdatenFuerMitarbeiter($id, $jahr, $monat);
            $werte = $monatsdaten['monatswerte'] ?? null;
            $soll  = (float)($werte['sollstunden'] ?? 0);
            $ist   = (float)($werte['iststunden'] ?? 0);

            $spiegel['stunden'][] = [
                'mitarbeiter' => $id,
                // Dieselbe Zahl, die das Terminal »Stundenkonto« nennt:
                // der Saldo bis einschliesslich Vormonat.
                'saldo'           => round($stundenkonto->holeSaldoMinutenBisVormonat($id, $jahr, $monat) / 60, 2),
                // Was im laufenden Monat noch zu leisten ist. Kann negativ
                // sein - dann ist das Soll schon uebererfuellt.
                'rest_soll_monat' => round($soll - $ist, 2),
            ];

            if ($mitMonaten) {
                // Laufender und vorheriger Monat. Weiter zurueck nicht: Wer
                // aeltere Monate braucht, fordert das PDF an.
                foreach ($this->monatsfenster($jahr, $monat) as [$j, $mo]) {
                    $spiegel['monate'][] = [
                        'mitarbeiter' => $id,
                        'jahr'        => $j,
                        'monat'       => $mo,
                        'tage'        => $this->tagesliste($report, $id, $j, $mo),
                    ];
                }
            }
        }

        return $spiegel;
    }

    /**
     * Die Tagesliste eines Monats, auf das eingedampft, was das Portal zeigt.
     *
     * @return array<int,array<string,mixed>>
     */
    private function tagesliste(ReportService $report, int $mitarbeiterId, int $jahr, int $monat): array
    {
        $daten = $report->holeMonatsdatenFuerMitarbeiter($mitarbeiterId, $jahr, $monat);
        // Gezeigt werden die **korrigierten** Zeiten, nicht die rohen: Das
        // sind dieselben, die im Monats-PDF und in der Backend-Maske stehen.
        // Zwei verschiedene Kommen-Zeiten fuer denselben Tag waeren der
        // sicherste Weg zu einer Nachfrage.
        $uhrzeit = static function ($wert): string {
            $wert = trim((string)$wert);
            if ($wert === '') {
                return '';
            }
            $zeit = strtotime($wert);
            return $zeit === false ? '' : date('H:i', $zeit);
        };

        $aus = [];
        foreach ((array)($daten['tageswerte'] ?? []) as $tag) {
            $aus[] = [
                'datum'    => (string)($tag['datum'] ?? ''),
                'kommen'   => $uhrzeit($tag['kommen_korr'] ?? ''),
                'gehen'    => $uhrzeit($tag['gehen_korr'] ?? ''),
                'pause'    => (string)($tag['pausen_stunden'] ?? ''),
                'ist'      => (string)($tag['arbeitszeit_stunden'] ?? ''),
                'tagestyp' => (string)($tag['tagestyp'] ?? ''),
            ];
        }
        return $aus;
    }

    /** @return array<int,array{0:int,1:int}> laufender und vorheriger Monat */
    private function monatsfenster(int $jahr, int $monat): array
    {
        $vorher = $monat === 1 ? [$jahr - 1, 12] : [$jahr, $monat - 1];
        return [[$jahr, $monat], $vorher];
    }

    /**
     * Betriebsferien und Feiertage - firmenweit, laufendes und Folgejahr.
     *
     * @return array<int,array<string,mixed>>
     */
    private function abwesenheiten(int $jahr): array
    {
        $aus = [];

        foreach ($this->datenbank->fetchAlle(
            'SELECT von_datum, bis_datum, beschreibung FROM betriebsferien
              WHERE bis_datum >= :von AND von_datum <= :bis
              ORDER BY von_datum',
            [':von' => $jahr . '-01-01', ':bis' => ($jahr + 1) . '-12-31']
        ) as $bf) {
            $aus[] = [
                'art'         => 'betriebsferien',
                'von'         => (string)$bf['von_datum'],
                'bis'         => (string)$bf['bis_datum'],
                'bezeichnung' => (string)($bf['beschreibung'] ?? 'Betriebsferien'),
            ];
        }

        foreach ($this->datenbank->fetchAlle(
            'SELECT datum, name FROM feiertag
              WHERE datum BETWEEN :von AND :bis AND ist_betriebsfrei = 1
              ORDER BY datum',
            [':von' => $jahr . '-01-01', ':bis' => ($jahr + 1) . '-12-31']
        ) as $ft) {
            $aus[] = [
                'art'         => 'feiertag',
                'von'         => (string)$ft['datum'],
                'bis'         => (string)$ft['datum'],
                'bezeichnung' => (string)($ft['name'] ?? 'Feiertag'),
            ];
        }

        return $aus;
    }

    // --------------------------------------------------------------- intern

    /**
     * Uebernimmt, was nur die Homepage weiss.
     *
     * @param array<int,array<string,mixed>> $zustand
     */
    private function zustandUebernehmen(array $zustand): void
    {
        foreach ($zustand as $z) {
            if (!is_array($z) || !isset($z['mitarbeiter'])) {
                continue;
            }
            $this->datenbank->ausfuehren(
                'UPDATE mitarbeiter
                    SET portal_aktiviert_am = :a, portal_letzte_anmeldung_am = :l
                  WHERE id = :id',
                [
                    ':a'  => $z['aktiviert_am'] ?? null,
                    ':l'  => $z['letzte_anmeldung_am'] ?? null,
                    ':id' => (int)$z['mitarbeiter'],
                ]
            );
        }
    }

    private function monateFaellig(): bool
    {
        $zuletzt = KonfigurationService::getInstanz()->get(self::SCHLUESSEL_MONATE);
        if ($zuletzt === null || $zuletzt === '') {
            return true;
        }
        $zeit = strtotime($zuletzt);
        if ($zeit === false) {
            return true;
        }
        return (time() - $zeit) >= self::MONATE_TAKT_MINUTEN * 60;
    }

    /**
     * @param array<int,string> $zeilen
     * @return array{ok:bool, zeilen:array<int,string>, fehler:string, dauer_ms:int}
     */
    private function beenden(bool $ok, array $zeilen, string $fehler, float $start): array
    {
        $dauer = (int)round((microtime(true) - $start) * 1000);
        $this->verbindung->laufVermerken($ok, $fehler !== '' ? $fehler : null, $dauer);

        if (!$ok) {
            Logger::warn('Portal-Abgleich gescheitert', ['fehler' => $fehler], null, null, 'portal');
        }

        return ['ok' => $ok, 'zeilen' => $zeilen, 'fehler' => $fehler, 'dauer_ms' => $dauer];
    }
}
