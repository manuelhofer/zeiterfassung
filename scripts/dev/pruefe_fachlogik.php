#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  Fachlogik-Pruefskript - rechnet nach, statt nur zu zeigen (T-140)
 * ===========================================================================
 *
 *  Wofuer:
 *      `php -l` ist Syntax. Der Klicktest zeigt eine Zahl im Browser, aber
 *      niemand erkennt an 8:36 statt 8:30, dass sie falsch ist. Die
 *      Pruefumgebung vergleicht zwei Staende und ist zufrieden, wenn beide
 *      gleich falsch rechnen - sie kennt kein Soll. Dieses Skript kennt es.
 *
 *  Aufruf:
 *      php scripts/dev/pruefe_fachlogik.php
 *
 *  Rueckgabewert:
 *      0 = alle Faelle OK, 1 = mindestens ein Fall abweichend oder Abbruch.
 *      Damit passt der Lauf spaeter ohne Aenderung in einen Hook.
 *
 *  Sperre:
 *      Das Skript setzt Rundungsregeln und legt Probe-Daten an. Es laeuft
 *      deshalb NUR gegen eine Datenbank, deren Name mit `zeit_probe` beginnt -
 *      wie `pruefumgebung.sh`. Die Entwicklungsdatenbank enthaelt echte
 *      Personendaten aus einem Serverdump; sie darf es nicht einmal
 *      versehentlich erreichen. Die Pruefung laeuft, BEVOR eine Verbindung
 *      aufgebaut wird.
 *
 *  Umgebung aufbauen:
 *      scripts/dev/pruefumgebung.sh aufbauen
 *      ZEIT_DB_NAME=zeit_probe php scripts/dev/pruefe_fachlogik.php
 *
 *  Zielbild und Akzeptanzkriterien:
 *      docs/spezifikation_fachlogik_pruefskript.md
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Dieses Skript laeuft nur auf der Kommandozeile.\n";
    exit(1);
}

require __DIR__ . '/../../core/Autoloader.php';

// ---------------------------------------------------------------------------
// Sperre: nur gegen die Probe-Datenbank
// ---------------------------------------------------------------------------
// Bewusst vor jedem Datenbankzugriff. `Start::konfig()` liest nur die
// Konfigurationsdatei, es entsteht keine Verbindung.
const DB_PRAEFIX = 'zeit_probe';

$konfig = Start::konfig();
date_default_timezone_set((string)($konfig['timezone'] ?? 'Europe/Berlin'));

$dbKonfig = is_array($konfig['db'] ?? null) ? $konfig['db'] : [];
$dbName = (string)($dbKonfig['dbname'] ?? '');

// Ein kompletter DSN umgeht `dbname` - dann steht der Name dort drin.
if ($dbName === '' && isset($dbKonfig['dsn'])) {
    if (preg_match('/dbname=([^;]+)/', (string)$dbKonfig['dsn'], $treffer) === 1) {
        $dbName = $treffer[1];
    }
}

if ($dbName === '' || strncmp($dbName, DB_PRAEFIX, strlen(DB_PRAEFIX)) !== 0) {
    fwrite(STDERR, sprintf(
        "ABBRUCH: Datenbank '%s' ist keine Probe-Datenbank.\n"
        . "Dieses Skript schreibt Rundungsregeln und Probe-Daten und laeuft nur\n"
        . "gegen einen Namen, der mit '%s' beginnt. Es wurde keine Verbindung\n"
        . "aufgebaut und keine Tabelle gelesen.\n\n"
        . "Umgebung aufbauen:  scripts/dev/pruefumgebung.sh aufbauen\n"
        . "Dann:               ZEIT_DB_NAME=%s php %s\n",
        $dbName === '' ? '(kein Name konfiguriert)' : $dbName,
        DB_PRAEFIX,
        DB_PRAEFIX,
        'scripts/dev/pruefe_fachlogik.php'
    ));
    exit(1);
}

// ---------------------------------------------------------------------------
// Ergebnissammlung
// ---------------------------------------------------------------------------

/**
 * Eine Gruppe von Faellen mit gemeinsamem Thema (Rundung, Pausen, ...).
 */
final class Pruefgruppe
{
    public int $gelaufen = 0;

    /** @var string[] */
    public array $abweichungen = [];

    public function __construct(public readonly string $name)
    {
    }

    /**
     * Vergleicht Ist gegen Soll. `$fall` beschreibt den Fall so, dass man ihn
     * ohne Blick in den Code nachvollziehen kann.
     */
    public function pruefe(string $fall, mixed $erwartet, mixed $bekommen): void
    {
        $this->gelaufen++;

        if ($erwartet === $bekommen) {
            return;
        }

        $this->abweichungen[] = sprintf(
            "  Fall %d (%s):\n    erwartet %s, bekommen %s",
            $this->gelaufen,
            $fall,
            self::zeige($erwartet),
            self::zeige($bekommen)
        );
    }

    /**
     * Merkt einen Fehlschlag, der keinen Vergleich hatte (Ausnahme, fehlende
     * Voraussetzung). Zaehlt wie ein abweichender Fall - stillschweigend
     * uebergehen waere das Gegenteil des Zwecks.
     */
    public function fehler(string $fall, string $grund): void
    {
        $this->gelaufen++;
        $this->abweichungen[] = sprintf("  Fall %d (%s):\n    %s", $this->gelaufen, $fall, $grund);
    }

    public function istGruen(): bool
    {
        return $this->abweichungen === [];
    }

    private static function zeige(mixed $wert): string
    {
        if (is_bool($wert)) {
            return $wert ? 'true' : 'false';
        }

        if (is_array($wert)) {
            $teile = [];
            foreach ($wert as $k => $v) {
                $teile[] = $k . '=' . self::zeige($v);
            }

            return '{' . implode(', ', $teile) . '}';
        }

        if ($wert === null) {
            return 'null';
        }

        return (string)$wert;
    }
}

/** @var Pruefgruppe[] $gruppen */
$gruppen = [];

// ---------------------------------------------------------------------------
// Die Faelle - je Rechenkern eine Datei in scripts/dev/faelle/
// ---------------------------------------------------------------------------
// Getrennte Dateien, damit ein neuer Fall nicht bedeutet, dieses Geruest
// anzufassen: Abschnitt 5 der Arbeitsregeln verlangt, dass wer Rundung, Pausen
// oder Salden aendert, seinen Fall hier hinterlaesst. Das soll billig sein.

// `glob()` liefert je nach Plattform `false` statt einer leeren Liste, wenn das
// Verzeichnis fehlt - deshalb der Fallback.
$fallDateien = glob(__DIR__ . '/faelle/*.php') ?: [];
sort($fallDateien);

// Ein Lauf ohne einen einzigen Fall darf nicht „OK" melden. Ein leeres grünes
// Ergebnis ist genau die Antwort, gegen die dieses Skript gebaut ist.
if ($fallDateien === []) {
    fwrite(STDERR, sprintf(
        "ABBRUCH: keine Fall-Dateien in %s gefunden.\n"
        . "Ein Lauf ohne Faelle ist kein gruener Lauf.\n",
        'scripts/dev/faelle/'
    ));
    exit(1);
}

foreach ($fallDateien as $datei) {
    /** @var callable(Pruefgruppe):void $fall */
    $fall = require $datei;

    if (!is_callable($fall)) {
        fwrite(STDERR, sprintf("ABBRUCH: %s liefert keine aufrufbare Funktion.\n", basename($datei)));
        exit(1);
    }

    $gruppe = new Pruefgruppe(basename($datei, '.php'));

    try {
        $fall($gruppe);
    } catch (Throwable $e) {
        $gruppe->fehler('Gruppe abgebrochen', get_class($e) . ': ' . $e->getMessage());
    }

    $gruppen[] = $gruppe;
}

// ---------------------------------------------------------------------------
// Ausgabe
// ---------------------------------------------------------------------------

$summeGelaufen = 0;
$summeOk = 0;

foreach ($gruppen as $gruppe) {
    $ok = $gruppe->gelaufen - count($gruppe->abweichungen);
    $summeGelaufen += $gruppe->gelaufen;
    $summeOk += $ok;

    printf(
        "%s %d von %d OK\n",
        str_pad($gruppe->name . ' ', 24, '.'),
        $ok,
        $gruppe->gelaufen
    );

    foreach ($gruppe->abweichungen as $zeile) {
        echo $zeile . "\n";
    }
}

printf("\n%d von %d OK\n", $summeOk, $summeGelaufen);

exit($summeOk === $summeGelaufen ? 0 : 1);
