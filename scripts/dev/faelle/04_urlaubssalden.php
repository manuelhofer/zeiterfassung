<?php
declare(strict_types=1);

/**
 * Faelle fuer die Urlaubssalden (`UrlaubService::berechneUrlaubssaldoFuerJahr`).
 *
 * **Warum hier Invarianten stehen und keine absoluten Sollwerte.**
 * Die Methode ist ueber 500 Zeilen: Monatsanspruch aus den Stammdaten,
 * Eintrittsdatum, Rueckfall auf einen Konfigurationswert, Uebertrag,
 * Korrekturen, Betriebsferien als Zwangsurlaub, Feiertage. Eine Zahl wie
 * „Anspruch 30,00" waere aus dem Code hergeleitet und nicht gemessen - weicht
 * sie beim ersten Lauf ab, kann niemand unterscheiden, ob der Code oder die
 * Erwartung falsch ist. Ein Fall, der das nicht unterscheiden laesst, ist
 * schlechter als kein Fall.
 *
 * Also wird geprueft, was ohne Nachrechnen sicher gilt:
 *   - die Rechenidentitaet des Saldos,
 *   - das dokumentierte Verhalten bei ungueltiger Eingabe,
 *   - Freiheit von Nebenwirkungen bei `autoUebertrag = false`,
 *   - **relative** Wirkung: ein genehmigter Ein-Tages-Antrag erhoeht `genommen`
 *     um genau 1,00, ein offener stattdessen `beantragt`.
 *
 * Diese Faelle fangen echte Regressionen (ein Antrag zaehlt nicht mehr, die
 * Identitaet bricht, die Methode schreibt ungefragt) ohne eine einzige
 * geratene Zahl. Wenn Manuel das Skript einmal hat laufen lassen, sind die
 * tatsaechlichen Werte bekannt und absolute Faelle sind billig nachzulegen.
 *
 * `autoUebertrag` ist ueberall `false`: Mit `true` **schreibt** die Methode
 * einen Uebertrag fest. Ein Pruefskript, das dabei Daten anlegt, prueft beim
 * zweiten Lauf etwas anderes als beim ersten.
 */

return static function (Pruefgruppe $gruppe): void {
    $db = Database::getInstanz();
    $urlaub = UrlaubService::getInstanz();

    // ---------------------------------------------------------------------
    // Ungueltige Eingabe - dokumentiertes Null-Buendel
    // ---------------------------------------------------------------------

    $ungueltig = $urlaub->berechneUrlaubssaldoFuerJahr(0, 2026, false);

    $gruppe->pruefe(
        'Mitarbeiter-ID 0 liefert den Hinweis',
        'Mitarbeiter-ID ungültig.',
        $ungueltig['hinweis']
    );
    $gruppe->pruefe(
        'Mitarbeiter-ID 0 liefert lauter Nullwerte',
        [
            'anspruch' => '0.00',
            'uebertrag' => '0.00',
            'korrektur' => '0.00',
            'genommen' => '0.00',
            'beantragt' => '0.00',
            'verbleibend' => '0.00',
        ],
        [
            'anspruch' => $ungueltig['anspruch'],
            'uebertrag' => $ungueltig['uebertrag'],
            'korrektur' => $ungueltig['korrektur'],
            'genommen' => $ungueltig['genommen'],
            'beantragt' => $ungueltig['beantragt'],
            'verbleibend' => $ungueltig['verbleibend'],
        ]
    );

    // Ein Jahr ausserhalb 2000..2100 wird auf das laufende Jahr normalisiert.
    $gruppe->pruefe(
        'Jahr 1900 wird auf das laufende Jahr normalisiert',
        (int)(new DateTimeImmutable('now'))->format('Y'),
        $urlaub->berechneUrlaubssaldoFuerJahr(1, 1900, false)['jahr']
    );

    // ---------------------------------------------------------------------
    // Probe-Mitarbeiter, der nur diesen Faellen gehoert
    // ---------------------------------------------------------------------

    $personalnummer = 'PRUEF-T140-URLAUB';
    $jahr = 2027;

    $altId = $db->fetchEine(
        'SELECT id FROM mitarbeiter WHERE personalnummer = :pnr',
        ['pnr' => $personalnummer]
    );
    if (is_array($altId) && isset($altId['id'])) {
        $db->ausfuehren('DELETE FROM urlaubsantrag WHERE mitarbeiter_id = :mid', ['mid' => (int)$altId['id']]);
        $db->ausfuehren('DELETE FROM urlaub_kontingent_jahr WHERE mitarbeiter_id = :mid', ['mid' => (int)$altId['id']]);
        $db->ausfuehren('DELETE FROM tageswerte_mitarbeiter WHERE mitarbeiter_id = :mid', ['mid' => (int)$altId['id']]);
        $db->ausfuehren('DELETE FROM mitarbeiter WHERE id = :mid', ['mid' => (int)$altId['id']]);
    }

    $db->ausfuehren(
        'INSERT INTO mitarbeiter
            (personalnummer, vorname, nachname, eintrittsdatum, wochenarbeitszeit,
             urlaub_monatsanspruch, aktiv, ist_login_berechtigt)
         VALUES (:pnr, \'Probe\', \'Urlaub\', \'2025-01-01\', 40.00, 2.50, 1, 0)',
        ['pnr' => $personalnummer]
    );
    $mitarbeiterId = (int)$db->letzteInsertId();

    if ($mitarbeiterId <= 0) {
        $gruppe->fehler('Probe-Mitarbeiter anlegen', 'Keine Insert-ID erhalten.');

        return;
    }

    // Kontingent mit Uebertrag und Korrektur, damit die Identitaet nicht nur
    // aus Nullen besteht.
    $db->ausfuehren(
        'INSERT INTO urlaub_kontingent_jahr
            (mitarbeiter_id, jahr, uebertrag_tage, korrektur_tage, notiz)
         VALUES (:mid, :jahr, 5.00, -2.00, :text)',
        ['mid' => $mitarbeiterId, 'jahr' => $jahr, 'text' => 'Fachlogik-Pruefskript (T-140)']
    );

    /**
     * Prueft die Rechenidentitaet: verbleibend = anspruch + uebertrag
     * + korrektur - genommen - beantragt. Verglichen wird auf zwei Stellen,
     * weil die Methode genau so formatiert.
     */
    $identitaet = static function (array $saldo): string {
        $summe = (float)$saldo['anspruch']
            + (float)$saldo['uebertrag']
            + (float)$saldo['korrektur']
            - (float)$saldo['genommen']
            - (float)$saldo['beantragt'];

        return number_format($summe, 2, '.', '');
    };

    $saldo = $urlaub->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr, false);

    $gruppe->pruefe(
        'Saldo geht auf (Anspruch + Uebertrag + Korrektur - Genommen - Beantragt)',
        $identitaet($saldo),
        $saldo['verbleibend']
    );

    // Der Uebertrag aus dem Kontingent kommt an.
    $gruppe->pruefe('Uebertrag aus dem Kontingent wird uebernommen', '5.00', $saldo['uebertrag']);
    $gruppe->pruefe('Korrektur aus dem Kontingent wird uebernommen', '-2.00', $saldo['korrektur']);

    // Ohne autoUebertrag darf kein zweiter Lauf etwas anderes liefern.
    $gruppe->pruefe(
        'autoUebertrag=false hat keine Nebenwirkung (zwei Laeufe gleich)',
        $saldo,
        $urlaub->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr, false)
    );

    // ---------------------------------------------------------------------
    // Relative Wirkung eines Antrags
    // ---------------------------------------------------------------------

    // Ein Arbeitstag ohne Feiertag: der erste Dienstag im Maerz des Jahres.
    // Wochentag wird berechnet, nicht angenommen.
    $tag = new DateTimeImmutable(sprintf('%04d-03-01', $jahr));
    while ($tag->format('N') !== '2') {
        $tag = $tag->modify('+1 day');
    }
    $datum = $tag->format('Y-m-d');

    // Sicherstellen, dass an diesem Tag kein Feiertag und keine Betriebsferien
    // liegen - sonst zaehlt der Antrag null Tage und der Fall misst nichts.
    $db->ausfuehren('DELETE FROM feiertag WHERE datum = :d', ['d' => $datum]);

    $vorher = $urlaub->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr, false);

    $db->ausfuehren(
        'INSERT INTO urlaubsantrag
            (mitarbeiter_id, von_datum, bis_datum, tage_gesamt, status, kommentar_mitarbeiter)
         VALUES (:mid, :von, :bis, 1.00, \'genehmigt\', :text)',
        ['mid' => $mitarbeiterId, 'von' => $datum, 'bis' => $datum, 'text' => 'Fachlogik-Pruefskript (T-140)']
    );

    $nachher = $urlaub->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr, false);

    $gruppe->pruefe(
        sprintf('Genehmigter Ein-Tages-Antrag (%s) erhoeht Genommen um 1,00', $datum),
        number_format((float)$vorher['genommen'] + 1.0, 2, '.', ''),
        $nachher['genommen']
    );
    $gruppe->pruefe(
        'und senkt Verbleibend um 1,00',
        number_format((float)$vorher['verbleibend'] - 1.0, 2, '.', ''),
        $nachher['verbleibend']
    );
    $gruppe->pruefe(
        'Beantragt bleibt dabei unveraendert',
        $vorher['beantragt'],
        $nachher['beantragt']
    );

    // Derselbe Antrag als 'offen' zaehlt auf Beantragt, nicht auf Genommen.
    $db->ausfuehren(
        'UPDATE urlaubsantrag SET status = \'offen\' WHERE mitarbeiter_id = :mid AND von_datum = :d',
        ['mid' => $mitarbeiterId, 'd' => $datum]
    );

    $offen = $urlaub->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr, false);

    $gruppe->pruefe(
        'Offener Antrag zaehlt auf Beantragt',
        number_format((float)$vorher['beantragt'] + 1.0, 2, '.', ''),
        $offen['beantragt']
    );
    $gruppe->pruefe(
        'Offener Antrag zaehlt nicht auf Genommen',
        $vorher['genommen'],
        $offen['genommen']
    );

    // Und ein abgelehnter Antrag zaehlt nirgends.
    $db->ausfuehren(
        'UPDATE urlaubsantrag SET status = \'abgelehnt\' WHERE mitarbeiter_id = :mid AND von_datum = :d',
        ['mid' => $mitarbeiterId, 'd' => $datum]
    );

    $abgelehnt = $urlaub->berechneUrlaubssaldoFuerJahr($mitarbeiterId, $jahr, false);

    $gruppe->pruefe(
        'Abgelehnter Antrag zaehlt nirgends',
        ['genommen' => $vorher['genommen'], 'beantragt' => $vorher['beantragt']],
        ['genommen' => $abgelehnt['genommen'], 'beantragt' => $abgelehnt['beantragt']]
    );
};
