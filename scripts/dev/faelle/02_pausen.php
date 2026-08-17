<?php
declare(strict_types=1);

/**
 * Faelle fuer den Pausenabzug (`PausenService`).
 *
 * WICHTIG - Reihenfolge: `PausenService` laedt die Pausenfenster beim ersten
 * Aufruf und merkt sie sich. Das Fenster muss also stehen, bevor gerechnet wird.
 *
 * Erwartungswerte hergeleitet aus
 * `berechnePausenMinutenUndEntscheidungFuerBlock()`:
 *   zwang  = Ueberlappung mit aktiven Pausenfenstern
 *   gesetz = Dauer > 9h -> 45, sonst Dauer > 6h -> 30, sonst 0  (strikt groesser)
 *   auto   = max(zwang, gesetz)
 *   entscheidung_noetig = auto > 0 UND Dauer in [6h, 6h+30min] (in Minuten)
 *   pause_minuten = entscheidung_noetig ? 0 : auto
 *
 * Der Grenzfall aus T-081 ist der interessante: Knapp ueber sechs Stunden wird
 * **keine** Pause abgezogen, bis ein Mensch entschieden hat, ob eine gemacht
 * wurde. Ein Skript, das diesen Fall nicht kennt, wuerde ihn fuer einen Fehler
 * halten.
 */

return static function (Pruefgruppe $gruppe): void {
    $db = Database::getInstanz();

    // Konfiguration auf die Standardwerte zurueck: 6h -> 30min, 9h -> 45min,
    // Toleranz 30min. Ohne Zeile in `config` gelten genau diese Defaults.
    $db->ausfuehren(
        'DELETE FROM config WHERE schluessel IN (
            \'pause_gesetz_schwelle1_stunden\',
            \'pause_gesetz_minuten1\',
            \'pause_gesetz_schwelle2_stunden\',
            \'pause_gesetz_minuten2\',
            \'pause_entscheidung_toleranz_minuten\'
        )'
    );

    // Ein betriebliches Fenster: Fruehstueck 09:00-09:15, also 15 Minuten.
    $db->ausfuehren('DELETE FROM pausenfenster');
    $db->ausfuehren(
        'INSERT INTO pausenfenster (von_uhrzeit, bis_uhrzeit, sort_order, kommentar, aktiv)
         VALUES (\'09:00:00\', \'09:15:00\', 10, :text, 1)',
        ['text' => 'Fachlogik-Pruefskript (T-140)']
    );

    $pausen = PausenService::getInstanz();

    /** @return array{pause_minuten:int,auto_pause_minuten:int,entscheidung_noetig:bool} */
    $block = static function (string $von, string $bis) use ($pausen): array {
        return $pausen->berechnePausenMinutenUndEntscheidungFuerBlock(
            new DateTimeImmutable('2026-06-15 ' . $von . ':00'),
            new DateTimeImmutable('2026-06-15 ' . $bis . ':00')
        );
    };

    // 5h ueber das Fenster: nur die Zwangspause, keine gesetzliche.
    $gruppe->pruefe(
        '07:00-12:00 (5h, ueber Fruehstueck)',
        ['pause_minuten' => 15, 'auto_pause_minuten' => 15, 'entscheidung_noetig' => false],
        $block('07:00', '12:00')
    );

    // Genau 6h: die gesetzliche Schwelle ist strikt groesser, greift also nicht.
    // Aber die Dauer liegt im Entscheidungsfenster - also wird nichts abgezogen.
    $gruppe->pruefe(
        '07:00-13:00 (genau 6h, Grenzfall T-081)',
        ['pause_minuten' => 0, 'auto_pause_minuten' => 15, 'entscheidung_noetig' => true],
        $block('07:00', '13:00')
    );

    // 6h01: gesetzliche Pause greift, Entscheidungsfenster ebenfalls.
    $gruppe->pruefe(
        '07:00-13:01 (6h01, noch im Entscheidungsfenster)',
        ['pause_minuten' => 0, 'auto_pause_minuten' => 30, 'entscheidung_noetig' => true],
        $block('07:00', '13:01')
    );

    // 6h31: eine Minute ueber der Toleranz - jetzt wird abgezogen.
    $gruppe->pruefe(
        '07:00-13:31 (6h31, Toleranz ueberschritten)',
        ['pause_minuten' => 30, 'auto_pause_minuten' => 30, 'entscheidung_noetig' => false],
        $block('07:00', '13:31')
    );

    // Genau 9h: zweite Schwelle ist ebenfalls strikt groesser, also 30 Minuten.
    $gruppe->pruefe(
        '07:00-16:00 (genau 9h, zweite Schwelle greift nicht)',
        ['pause_minuten' => 30, 'auto_pause_minuten' => 30, 'entscheidung_noetig' => false],
        $block('07:00', '16:00')
    );

    // 9h30: zweite Schwelle greift.
    $gruppe->pruefe(
        '07:00-16:30 (9h30, zweite Schwelle)',
        ['pause_minuten' => 45, 'auto_pause_minuten' => 45, 'entscheidung_noetig' => false],
        $block('07:00', '16:30')
    );

    // Block ohne Fenster und unter der Schwelle: keine Pause.
    $gruppe->pruefe(
        '13:00-18:00 (5h, ohne Fenster)',
        ['pause_minuten' => 0, 'auto_pause_minuten' => 0, 'entscheidung_noetig' => false],
        $block('13:00', '18:00')
    );

    // Ende gleich Start und Ende vor Start: beides liefert Nullen, keine Ausnahme.
    $gruppe->pruefe(
        '12:00-12:00 (Dauer null)',
        ['pause_minuten' => 0, 'auto_pause_minuten' => 0, 'entscheidung_noetig' => false],
        $block('12:00', '12:00')
    );
    $gruppe->pruefe(
        '18:00-17:00 (Ende vor Start)',
        ['pause_minuten' => 0, 'auto_pause_minuten' => 0, 'entscheidung_noetig' => false],
        $block('18:00', '17:00')
    );

    // Die kurze Fassung muss dasselbe liefern wie das Bündel.
    $gruppe->pruefe(
        'berechnePausenMinutenFuerBlock deckt sich mit dem Buendel',
        45,
        $pausen->berechnePausenMinutenFuerBlock(
            new DateTimeImmutable('2026-06-15 07:00:00'),
            new DateTimeImmutable('2026-06-15 16:30:00')
        )
    );
};
