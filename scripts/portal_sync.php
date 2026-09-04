<?php
declare(strict_types=1);

/**
 * Der Abgleich mit dem Mitarbeiterportal, fuer den Zeitplandienst.
 *
 * Aufruf auf dem Firmenserver, ueblicherweise alle zwei Minuten:
 *
 *   * / 2 * * * *  php /pfad/zur/zeiterfassung/scripts/portal_sync.php >/dev/null
 *
 * (Ohne Leerzeichen im ersten Feld: `*​/2 * * * *`.)
 *
 * WARUM EIN SKRIPT UND KEINE ADRESSE: Weil diese Installation die anrufende
 * Seite ist. Eine Adresse wie der Zeitplandienst der Homepage waere hier
 * sinnlos - es gibt niemanden von aussen, der sie aufrufen koennte, und das
 * ist ja der ganze Punkt (docs/spezifikation_mitarbeiterportal.md,
 * Abschnitt 2).
 *
 * AUSGABE: einfacher Text, eine Zeile je Schritt. Der Rueckgabewert ist 0,
 * wenn der Lauf durchging, sonst 1 - damit ein Zeitplandienst mit
 * Fehlerbenachrichtigung eine schicken kann.
 *
 * **Der Lauf ist gegen Ueberschneidung gesichert.** Ohne Sperre koennten sich
 * zwei Laeufe ueberholen, wenn einer laenger braucht als der Takt - und der
 * zweite holte dieselben Auftraege noch einmal ab. Der Doppelschutz in
 * `portal_eingang` faenge das zwar ab, aber es waere unnoetige Arbeit und ein
 * unnoetig verwirrendes Protokoll.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dieses Skript laeuft nur auf der Kommandozeile.\n");
}

require __DIR__ . '/../core/Autoloader.php';
Start::los();

$sperrdatei = sys_get_temp_dir() . '/zeiterfassung_portal_sync.lock';
$sperre = @fopen($sperrdatei, 'c');
if ($sperre === false) {
    fwrite(STDERR, "Sperrdatei liess sich nicht anlegen: $sperrdatei\n");
    exit(1);
}
if (!flock($sperre, LOCK_EX | LOCK_NB)) {
    echo "Ein Abgleich laeuft bereits - dieser Aufruf tut nichts.\n";
    exit(0);
}

$start = date('d.m.Y H:i:s');
$dienst = new PortalSyncService();
$ergebnis = $dienst->laufen('zeitplan');

echo "Mitarbeiterportal - Abgleich, $start\n";
echo str_repeat('-', 60) . "\n";
foreach ($ergebnis['zeilen'] as $zeile) {
    echo $zeile . "\n";
}
if ($ergebnis['fehler'] !== '') {
    echo "FEHLER: " . $ergebnis['fehler'] . "\n";
}
echo str_repeat('-', 60) . "\n";
echo ($ergebnis['ok'] ? 'Durchgelaufen' : 'Abgebrochen')
    . ' in ' . $ergebnis['dauer_ms'] . " ms.\n";

flock($sperre, LOCK_UN);
fclose($sperre);

exit($ergebnis['ok'] ? 0 : 1);
