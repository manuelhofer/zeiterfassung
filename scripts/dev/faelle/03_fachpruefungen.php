<?php
declare(strict_types=1);

/**
 * Faelle fuer die drei Fachpruefungen aus `FachpruefungService`
 * (Monatsraster, Doppelzaehlung, Feiertag+Arbeitszeit).
 *
 * Zwei Arten von Faellen, und sie sind unterschiedlich sicher:
 *
 * 1. **Eingabepruefung.** Was bei ungueltiger Mitarbeiter-ID, ungueltigem Monat
 *    und ungueltigem Jahr herauskommt, steht als Zeichenkette im Code der
 *    Pruefungen. Diese Faelle sind aus dem gelesenen Code hergeleitet und
 *    haengen an keiner Report-Interna.
 *
 * 2. **Erkennung eines echten Konflikts.** Ein Tageswert mit
 *    `kennzeichen_feiertag = 1`, Arbeitszeit **und** Feiertagsstunden muss von
 *    `pruefeFeiertagUndArbeitszeit()` als `ok = false` gemeldet werden. Das ist
 *    der Fall, der belegt, dass die Pruefung erkennt und nicht nur laeuft.
 *    Er haengt daran, wie `ReportService::holeMonatsdatenFuerMitarbeiter()` die
 *    Spalte `ist_stunden` auf das Feld `arbeitszeit_stunden` abbildet - das ist
 *    beim Schreiben dieser Datei gelesen, aber nie ausgefuehrt worden. Sollte
 *    der Fall beim ersten Lauf abweichen, ist die Abbildung der erste Ort zum
 *    Nachsehen, nicht die Pruefung.
 *
 * Semantik von `ok` bei Feiertag+Arbeitszeit, aus dem Code:
 *   null  = keine Feiertage im Monat, Aussage nicht moeglich
 *   true  = Feiertage vorhanden, kein Konflikt
 *   false = mindestens ein Tag mit Arbeitszeit > 0,01 UND Feiertagsstunden > 0,01
 */

return static function (Pruefgruppe $gruppe): void {
    $db = Database::getInstanz();
    $pruefung = FachpruefungService::getInstanz();

    // ---------------------------------------------------------------------
    // 1. Eingabepruefung - unabhaengig von Daten
    // ---------------------------------------------------------------------

    $gruppe->pruefe(
        'Monatsraster, Mitarbeiter-ID 0',
        'Keine gültige Mitarbeiter-ID für den Monatsreport-Raster-Check.',
        $pruefung->pruefeMonatsraster(0, 2026, 6)['hinweis']
    );
    $gruppe->pruefe(
        'Monatsraster, Monat 13',
        'Monat muss 1..12 sein.',
        $pruefung->pruefeMonatsraster(1, 2026, 13)['hinweis']
    );
    $gruppe->pruefe(
        'Monatsraster, Jahr 1900',
        'Jahr außerhalb des erwarteten Bereichs (1970..2100).',
        $pruefung->pruefeMonatsraster(1, 1900, 6)['hinweis']
    );
    $gruppe->pruefe(
        'Monatsraster, ungueltige Eingabe liefert kein Ergebnis',
        null,
        $pruefung->pruefeMonatsraster(0, 2026, 6)['ergebnis']
    );

    $gruppe->pruefe(
        'Doppelzaehlung, Mitarbeiter-ID 0',
        'Keine gültige Mitarbeiter-ID für den Doppelzählung-Check.',
        $pruefung->pruefeDoppelzaehlung(0, 2026, 6)['hinweis']
    );
    $gruppe->pruefe(
        'Doppelzaehlung, Monat 0',
        'Monat muss 1..12 sein.',
        $pruefung->pruefeDoppelzaehlung(1, 2026, 0)['hinweis']
    );

    $gruppe->pruefe(
        'Feiertag+Arbeitszeit, Mitarbeiter-ID 0',
        'Keine gültige Mitarbeiter-ID für den Feiertag+Arbeitszeit-Check.',
        $pruefung->pruefeFeiertagUndArbeitszeit(0, 2026, 6)['hinweis']
    );
    $gruppe->pruefe(
        'Feiertag+Arbeitszeit, Jahr 2200',
        'Jahr außerhalb des erwarteten Bereichs (1970..2100).',
        $pruefung->pruefeFeiertagUndArbeitszeit(1, 2200, 6)['hinweis']
    );

    // Die drei Eingabewerte kommen unveraendert im Buendel zurueck - darauf
    // verlassen sich die Views.
    $raster = $pruefung->pruefeMonatsraster(7, 2026, 5);
    $gruppe->pruefe(
        'Buendel gibt die Eingabewerte zurueck',
        ['mitarbeiter_id' => 7, 'jahr' => 2026, 'monat' => 5],
        [
            'mitarbeiter_id' => $raster['mitarbeiter_id'],
            'jahr' => $raster['jahr'],
            'monat' => $raster['monat'],
        ]
    );

    // ---------------------------------------------------------------------
    // 2. Erkennung: ein Feiertag mit Arbeitszeit UND Feiertagsstunden
    // ---------------------------------------------------------------------

    // Probe-Mitarbeiter, der nur diesem Fall gehoert. Loeschen vor dem Anlegen,
    // damit zwei Laeufe dasselbe Ergebnis liefern.
    $personalnummer = 'PRUEF-T140';
    $db->ausfuehren(
        'DELETE FROM tageswerte_mitarbeiter
          WHERE mitarbeiter_id IN (SELECT id FROM mitarbeiter WHERE personalnummer = :pnr)',
        ['pnr' => $personalnummer]
    );
    $db->ausfuehren('DELETE FROM mitarbeiter WHERE personalnummer = :pnr', ['pnr' => $personalnummer]);
    $db->ausfuehren(
        'INSERT INTO mitarbeiter
            (personalnummer, vorname, nachname, eintrittsdatum, wochenarbeitszeit,
             urlaub_monatsanspruch, aktiv, ist_login_berechtigt)
         VALUES (:pnr, \'Probe\', \'Fachlogik\', \'2025-01-01\', 40.00, 2.50, 1, 0)',
        ['pnr' => $personalnummer]
    );
    $mitarbeiterId = (int)$db->letzteInsertId();

    if ($mitarbeiterId <= 0) {
        $gruppe->fehler('Probe-Mitarbeiter anlegen', 'Keine Insert-ID erhalten.');

        return;
    }

    // Ein Feiertag, der sicher da ist: 2026-06-15 ist ein Montag und in keinem
    // Bundesland gesetzlich frei - also erfinden wir einen eigenen.
    $feiertag = '2026-06-15';
    $db->ausfuehren('DELETE FROM feiertag WHERE datum = :d', ['d' => $feiertag]);
    $db->ausfuehren(
        'INSERT INTO feiertag (datum, name, bundesland, ist_gesetzlich, ist_betriebsfrei)
         VALUES (:d, :name, NULL, 1, 1)',
        ['d' => $feiertag, 'name' => 'Pruefskript-Feiertag (T-140)']
    );

    // Der Konflikt: acht Stunden Arbeitszeit und acht Stunden Feiertag am
    // selben Tag, Feiertagskennzeichen gesetzt.
    $db->ausfuehren(
        'INSERT INTO tageswerte_mitarbeiter
            (mitarbeiter_id, datum, ist_stunden, feiertag_stunden, kennzeichen_feiertag, kommentar)
         VALUES (:mid, :d, 8.00, 8.00, 1, :text)',
        ['mid' => $mitarbeiterId, 'd' => $feiertag, 'text' => 'Fachlogik-Pruefskript (T-140)']
    );

    $ergebnis = $pruefung->pruefeFeiertagUndArbeitszeit($mitarbeiterId, 2026, 6)['ergebnis'];

    if (!is_array($ergebnis)) {
        $gruppe->fehler(
            'Feiertag+Arbeitszeit erkennt den Konflikt',
            'Kein Ergebnis erhalten - Hinweis war: '
            . (string)($pruefung->pruefeFeiertagUndArbeitszeit($mitarbeiterId, 2026, 6)['hinweis'] ?? '(keiner)')
        );

        return;
    }

    $gruppe->pruefe(
        'Feiertag mit Arbeitszeit UND Feiertagsstunden wird gemeldet',
        false,
        $ergebnis['ok']
    );

    // Und ohne den Konflikt ist derselbe Monat sauber.
    $db->ausfuehren(
        'UPDATE tageswerte_mitarbeiter SET ist_stunden = 0.00 WHERE mitarbeiter_id = :mid AND datum = :d',
        ['mid' => $mitarbeiterId, 'd' => $feiertag]
    );

    $ergebnisSauber = $pruefung->pruefeFeiertagUndArbeitszeit($mitarbeiterId, 2026, 6)['ergebnis'];
    $gruppe->pruefe(
        'Feiertag ohne Arbeitszeit ist kein Konflikt',
        true,
        is_array($ergebnisSauber) ? $ergebnisSauber['ok'] : null
    );
};
