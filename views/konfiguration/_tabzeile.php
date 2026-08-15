<?php
declare(strict_types=1);
/**
 * Teil-Template: Tab-Zeile der Konfigurations-Masken
 *
 * Der Unterstrich im Dateinamen sagt: Das hier ist **keine** Maske, sondern ein
 * Stück, das andere Masken einbinden. Sonst steht es zwischen `liste.php` und
 * `systemlog.php` und sieht aus wie eine sechste Seite.
 *
 * Eingebunden wird es mit `require __DIR__ . '/_tabzeile.php';` – es erwartet
 * nichts und gibt nur diesen einen Absatz aus.
 *
 * Die `require`-Zeile steht in den Masken **ohne** Einrückung, obwohl das im
 * Quelltext aus der Reihe fällt: Der Absatz unten bringt seine vier Leerzeichen
 * selbst mit, und eingerückt stünden acht im ausgelieferten HTML.
 *
 * Die aktuelle Maske wird bewusst **nicht** hervorgehoben: Das wäre eine
 * sichtbare Änderung an fünf Masken und gehört in einen eigenen Patch.
 */
?>
    <p style="margin-top:0.25rem;">
        <a href="?seite=konfiguration_admin">Konfiguration</a>
        | <a href="?seite=konfiguration_admin&amp;tab=krankzeitraum">Krank (LFZ/KK)</a>
        | <a href="?seite=konfiguration_admin&amp;tab=pausen">Pausenregeln</a>
        | <a href="?seite=konfiguration_admin&amp;tab=sonstiges">Sonstiges-Gründe</a>
        | <a href="?seite=konfiguration_admin&amp;tab=systemlog">System-Log</a>
    </p>
