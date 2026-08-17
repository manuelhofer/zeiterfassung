<?php
declare(strict_types=1);

/**
 * Faelle fuer die Zeitrundung (`RundungsService::rundeZeitstempel`).
 *
 * WICHTIG - Reihenfolge: `RundungsService` ist ein Singleton und liest die
 * Regeln **im Konstruktor** in einen Cache. Die Regeln muessen also stehen,
 * bevor `getInstanz()` das erste Mal laeuft. Deshalb wird hier erst geschrieben
 * und danach geholt; ein zweiter Regelsatz im selben Prozess ist nicht moeglich.
 *
 * Statt mehrerer Durchlaeufe deckt ein Regelsatz alle drei Richtungen ab, indem
 * jede Richtung ihren eigenen Zeitbereich bekommt.
 *
 * Erwartungswerte hergeleitet aus `wendeRegelAn()`:
 *   faktor = round|ceil|floor(minutenSeitMitternacht / einheit), Ergebnis
 *   = faktor * einheit, geklemmt auf 0..1439. Sekunden zaehlen nicht mit,
 *   `berechneMinutenSeitMitternacht()` liest nur H und i.
 */

return static function (Pruefgruppe $gruppe): void {
    $db = Database::getInstanz();

    // Eigener Regelsatz, nicht der geseedete Standard - sonst prueft der Fall
    // eine Voraussetzung, die ein anderer Patch aendern kann.
    $db->ausfuehren('DELETE FROM zeit_rundungsregel');

    $regeln = [
        // von,        bis,         einheit, richtung
        ['00:00:00', '07:00:00', 30, 'naechstgelegen'],
        ['07:00:00', '12:00:00', 15, 'naechstgelegen'],
        ['12:00:00', '14:00:00', 15, 'auf'],
        ['14:00:00', '16:00:00', 15, 'ab'],
        ['16:00:00', '23:59:59', 15, 'naechstgelegen'],
    ];

    foreach ($regeln as $i => [$von, $bis, $einheit, $richtung]) {
        $db->ausfuehren(
            'INSERT INTO zeit_rundungsregel
                (von_uhrzeit, bis_uhrzeit, einheit_minuten, richtung, gilt_fuer, prioritaet, aktiv, beschreibung)
             VALUES (:von, :bis, :einheit, :richtung, \'beide\', :prio, 1, :text)',
            [
                'von' => $von,
                'bis' => $bis,
                'einheit' => $einheit,
                'richtung' => $richtung,
                'prio' => $i + 1,
                'text' => 'Fachlogik-Pruefskript (T-140)',
            ]
        );
    }

    $rundung = RundungsService::getInstanz();

    /**
     * @param string $zeit  'HH:MM' am 15.06.2026 (ein Montag, kein Feiertag)
     * @param string $typ   'kommen', 'gehen' oder etwas Ungueltiges
     */
    $runde = static function (string $zeit, string $typ) use ($rundung): string {
        $eingabe = new DateTimeImmutable('2026-06-15 ' . $zeit . ':00');

        return $rundung->rundeZeitstempel($eingabe, $typ)->format('H:i');
    };

    // --- 30-Minuten-Bereich, naechstgelegen (00:00-07:00) --------------------
    $gruppe->pruefe('Kommen 06:40, 30min naechstgelegen', '06:30', $runde('06:40', 'kommen'));
    $gruppe->pruefe('Kommen 06:50, 30min naechstgelegen', '07:00', $runde('06:50', 'kommen'));

    // Genau die halbe Einheit: PHPs round() rundet von der Null weg, also auf.
    $gruppe->pruefe('Kommen 06:45, genau halbe Einheit', '07:00', $runde('06:45', 'kommen'));

    // --- 15-Minuten-Bereich, naechstgelegen (07:00-12:00) -------------------
    $gruppe->pruefe('Kommen 07:03, 15min naechstgelegen', '07:00', $runde('07:03', 'kommen'));
    $gruppe->pruefe('Gehen 07:08, 15min naechstgelegen', '07:15', $runde('07:08', 'gehen'));

    // Sekunden zaehlen nicht mit - 07:03:59 ist wie 07:03.
    $gruppe->pruefe(
        'Kommen 07:03:59, Sekunden ohne Wirkung',
        '07:00',
        $rundung->rundeZeitstempel(new DateTimeImmutable('2026-06-15 07:03:59'), 'kommen')->format('H:i')
    );

    // --- Richtung 'auf' (12:00-14:00) ---------------------------------------
    $gruppe->pruefe('Kommen 12:01, Richtung auf', '12:15', $runde('12:01', 'kommen'));

    // Ein Wert genau auf der Rundungsgrenze bleibt stehen, auch bei 'auf'.
    $gruppe->pruefe('Kommen 12:00, genau auf der Grenze', '12:00', $runde('12:00', 'kommen'));

    // --- Richtung 'ab' (14:00-16:00) ----------------------------------------
    $gruppe->pruefe('Gehen 14:14, Richtung ab', '14:00', $runde('14:14', 'gehen'));

    // --- Raender ------------------------------------------------------------
    $gruppe->pruefe('Kommen 00:00, Tagesanfang', '00:00', $runde('00:00', 'kommen'));

    // 23:59 rundet auf 24:00 und wird auf 23:59 geklemmt - der Tag bleibt.
    $gruppe->pruefe('Gehen 23:59, Klemmung am Tagesende', '23:59', $runde('23:59', 'gehen'));

    // --- Kein gueltiger Typ: keine Rundung ----------------------------------
    $gruppe->pruefe('Typ "mittag" wird nicht gerundet', '07:03', $runde('07:03', 'mittag'));
};
