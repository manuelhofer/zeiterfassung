<?php
declare(strict_types=1);

/**
 * Terminal – Einrichtungsseite (Kopplung am Gerät).
 *
 * Wird von `TerminalEinrichtungController` gerendert, solange auf diesem Gerät
 * keine `config/config.local.php` existiert. Entspricht der Erstinstallation im
 * Backend (`views/login/initial_admin.php`), ist aber für den Touchscreen
 * gebaut: große Felder, Bildschirmtastatur, keine Systemtastatur nötig.
 *
 * Erwartete Variablen:
 * - $csrfToken (string)
 * - $fehlermeldung (string|null)
 * - $formularwerte (array<string,string>)
 * - $abtippInhalt (string|null)   Inhalt der Konfiguration, falls das Schreiben scheiterte
 * - $konfigPfad (string)
 * - $verzeichnisOk (bool)         Ist config/ beschreibbar?
 * - $offlineFehlt (bool)          Keine lokale Ausweichdatenbank hinterlegt?
 * - $erfolg (array|null)          Terminal-Daten nach erfolgreicher Kopplung
 * - $warnung (string|null)        Warnung aus der Kopplungsantwort (z. B. kein HTTPS)
 *
 * Diese Seite bindet bewusst **nicht** `_layout_top.php` ein: Dort hängen
 * Statusanzeige (ONLINE/OFFLINE), Uhr und Auto-Logout an einer Datenbank, die
 * es hier noch gar nicht gibt. Ein Auto-Logout wäre hier außerdem schädlich –
 * er würde die Seite während des Tippens neu laden.
 */

$csrfToken     = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$fehlermeldung = isset($fehlermeldung) && is_string($fehlermeldung) && $fehlermeldung !== '' ? $fehlermeldung : null;
$formularwerte = isset($formularwerte) && is_array($formularwerte) ? $formularwerte : [];
$abtippInhalt  = isset($abtippInhalt) && is_string($abtippInhalt) && $abtippInhalt !== '' ? $abtippInhalt : null;
$konfigPfad    = isset($konfigPfad) && is_string($konfigPfad) ? $konfigPfad : '';
$verzeichnisOk = isset($verzeichnisOk) ? (bool)$verzeichnisOk : true;
$offlineFehlt  = isset($offlineFehlt) ? (bool)$offlineFehlt : false;
$erfolg        = isset($erfolg) && is_array($erfolg) ? $erfolg : null;
$warnung       = isset($warnung) && is_string($warnung) && $warnung !== '' ? $warnung : null;

$e = static function ($wert): string {
    return htmlspecialchars((string)$wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$cssRelPfad = 'css/terminal.css';
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Terminal einrichten – Zeiterfassung</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php require __DIR__ . '/_style.php'; ?>
    <style>
        /*
         * Nur das Nötigste zusätzlich zum Terminal-Stylesheet: Diese Seite
         * gibt es genau einmal im Leben eines Geräts, deshalb bleiben ihre
         * Sonderfälle hier und nicht im gemeinsamen CSS.
         */
        body.terminal-einrichtung main {
            padding-bottom: 1.5rem;
        }

        body.terminal-einrichtung main > h1 {
            display: block;
            font-size: 1.5rem;
            margin: 0 0 0.35rem 0;
        }

        .einrichtung-feld {
            margin-bottom: 1rem;
        }

        .einrichtung-feld label {
            display: block;
            font-size: 1.05rem;
            margin-bottom: 0.35rem;
        }

        .einrichtung-feld input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            padding: 0.85rem;
            font-size: 1.5rem;
            border-radius: 6px;
            border: 1px solid #555;
            background-color: #444;
            color: #f5f5f5;
        }

        .einrichtung-feld input[type="text"]:focus {
            outline: 3px solid #2d7cd6;
        }

        .einrichtung-feld input#kopplungscode {
            font-family: monospace;
            letter-spacing: 0.25rem;
            text-transform: uppercase;
        }

        .einrichtung-feldhinweis {
            font-size: 0.85rem;
            color: #cccccc;
            margin-top: 0.3rem;
        }

        .einrichtung-tastaturtitel {
            font-size: 0.9rem;
            color: #cccccc;
            margin-bottom: 0.35rem;
        }

        .einrichtung-abtippen {
            max-height: 40vh;
            overflow: auto;
            background-color: #1b1b1b;
            border: 1px solid #555;
            border-radius: 4px;
            padding: 0.6rem;
            font-size: 0.8rem;
            white-space: pre;
        }

        .einrichtung-daten {
            font-size: 1.05rem;
            line-height: 1.6;
        }
    </style>
</head>
<body class="terminal-einrichtung">
<main>

<?php if ($erfolg !== null): ?>

    <h1>Terminal ist gekoppelt</h1>

    <div class="meldung">
        Die Konfiguration wurde gespeichert. Das Terminal ist einsatzbereit.
    </div>

    <?php if ($warnung !== null): ?>
        <div class="fehler">
            <strong>Hinweis zur Sicherheit:</strong><br>
            <?php echo $e($warnung); ?>
        </div>
    <?php endif; ?>

    <div class="status-box ok">
        <div class="status-title"><span>Dieses Gerät</span></div>
        <div class="einrichtung-daten">
            Name: <strong><?php echo $e($erfolg['name'] ?? ''); ?></strong><br>
            Terminal-Nummer: <strong><?php echo (int)($erfolg['id'] ?? 0); ?></strong>
            <?php if (trim((string)($erfolg['standort_beschreibung'] ?? '')) !== ''): ?>
                <br>Standort: <strong><?php echo $e($erfolg['standort_beschreibung']); ?></strong>
            <?php endif; ?>
            <br>Automatische Abmeldung: <strong><?php echo (int)($erfolg['auto_logout_timeout_sekunden'] ?? 60); ?> Sekunden</strong>
        </div>
    </div>

    <?php if ($offlineFehlt): ?>
        <div class="status-box warn">
            <div class="status-title"><span>Ohne lokale Ausweichdatenbank</span></div>
            <div class="status-small">
                Auf diesem Gerät ist keine lokale Ausweichdatenbank hinterlegt
                (<code>config/gerät.local.php</code> fehlt). Das Terminal arbeitet, solange der
                Server erreichbar ist – bei einem Netzausfall kann es aber <strong>nichts
                zwischenspeichern</strong>. Das Installationsskript richtet sie ein.
            </div>
        </div>
    <?php endif; ?>

    <div class="button-row primary-action">
        <a href="terminal.php?aktion=start" class="button-link">Terminal starten</a>
    </div>

<?php else: ?>

    <h1>Terminal einrichten</h1>
    <p class="hinweis">
        Dieses Gerät ist noch nicht mit einem Server verbunden. Im Backend unter
        <em>Verwaltung &rarr; Terminals</em> zum passenden Terminal einen Kopplungscode erzeugen
        und hier eintragen. Der Code gilt 30 Minuten und nur ein einziges Mal.
    </p>

    <?php if ($fehlermeldung !== null): ?>
        <div class="fehler"><?php echo $e($fehlermeldung); ?></div>
    <?php endif; ?>

    <?php if ($abtippInhalt !== null): ?>
        <div class="status-box error">
            <div class="status-title"><span>Bitte von Hand anlegen: <?php echo $e($konfigPfad); ?></span></div>
            <div class="status-small">
                Der Kopplungscode ist verbraucht. Wird die Datei jetzt nicht mit diesem Inhalt
                angelegt, muss im Backend ein neuer Code erzeugt werden. Die Zugangsdaten unten
                gelten nur für dieses Terminal.
            </div>
            <pre class="einrichtung-abtippen"><?php echo $e($abtippInhalt); ?></pre>
        </div>
    <?php endif; ?>

    <?php if (!$verzeichnisOk): ?>
        <div class="status-box warn">
            <div class="status-title"><span>Verzeichnis nicht beschreibbar</span></div>
            <div class="status-small">
                Der Webserver darf in <code><?php echo $e(dirname($konfigPfad)); ?></code> nicht schreiben.
                Die Kopplung lässt sich trotzdem durchführen – die Konfiguration wird dann zum
                Abtippen angezeigt. Besser: vorher die Schreibrechte setzen.
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="terminal.php?aktion=einrichtung" class="login-form" id="einrichtung-formular" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">

        <div class="einrichtung-feld">
            <label for="serveradresse">Adresse des Servers</label>
            <input type="text" name="serveradresse" id="serveradresse"
                   value="<?php echo $e($formularwerte['serveradresse'] ?? ''); ?>"
                   inputmode="url" autocapitalize="off" autocorrect="off" spellcheck="false"
                   data-tastatur="text">
            <div class="einrichtung-feldhinweis">
                Beispiel: <code>192.168.10.5</code> oder <code>server/zeiterfassung</code>
            </div>
        </div>

        <div class="einrichtung-feld">
            <label for="kopplungscode">Kopplungscode</label>
            <input type="text" name="kopplungscode" id="kopplungscode"
                   value="<?php echo $e($formularwerte['kopplungscode'] ?? ''); ?>"
                   maxlength="16" autocapitalize="characters" autocorrect="off" spellcheck="false"
                   data-tastatur="code">
            <div class="einrichtung-feldhinweis">
                8 Zeichen aus dem Backend. Groß- und Kleinschreibung sowie Bindestriche sind egal.
            </div>
        </div>

        <div id="einrichtung-tastaturbereich" hidden>
            <div class="einrichtung-tastaturtitel" id="einrichtung-tastaturtitel">Bildschirmtastatur</div>
            <div class="terminal-osk" id="einrichtung-tastatur" aria-label="Bildschirmtastatur"></div>
            <button type="button" class="secondary terminal-osk-umschalter" id="einrichtung-tastatur-umschalter"
                    aria-expanded="true" aria-controls="einrichtung-tastatur">Tastatur schließen</button>
        </div>

        <div class="button-row primary-action">
            <button type="submit">Koppeln</button>
        </div>
    </form>

    <p class="hinweis center">
        Nach der Kopplung startet dieses Gerät von selbst in die Bedienoberfläche.
    </p>

<?php endif; ?>

</main>

<?php if ($erfolg === null): ?>
<script>
(function () {
    'use strict';

    // Bildschirmtastatur: Ein Hallenterminal hat keine Tastatur. Die Belegung
    // hängt am Feld – für den Kopplungscode gibt es bewusst nur die Zeichen,
    // die im Code vorkommen können (kein O/0, kein I/1/L). Damit sind Zahlen-
    // dreher und Verwechslungen beim Abtippen praktisch ausgeschlossen.
    var CODE_ZEICHEN = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'.split('');

    var textLayout = {
        klein: [
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            ['q', 'w', 'e', 'r', 't', 'z', 'u', 'i', 'o', 'p'],
            ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', '-'],
            ['y', 'x', 'c', 'v', 'b', 'n', 'm', '.', ':', '/']
        ],
        groß: [
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            ['Q', 'W', 'E', 'R', 'T', 'Z', 'U', 'I', 'O', 'P'],
            ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', '_'],
            ['Y', 'X', 'C', 'V', 'B', 'N', 'M', '.', ':', '/']
        ]
    };

    var behaelter = document.getElementById('einrichtung-tastatur');
    var bereich = document.getElementById('einrichtung-tastaturbereich');
    var titel = document.getElementById('einrichtung-tastaturtitel');
    var umschalter = document.getElementById('einrichtung-tastatur-umschalter');
    var felder = Array.prototype.slice.call(document.querySelectorAll('[data-tastatur]'));

    if (!behaelter || !bereich || felder.length === 0) {
        return;
    }

    var aktivesFeld = null;
    var grossModus = false;
    var sichtbar = true;

    function erstelleTaste(beschriftung, wert, zusatzklasse) {
        var taste = document.createElement('button');
        taste.type = 'button';
        taste.className = 'terminal-osk-taste' + (zusatzklasse ? ' ' + zusatzklasse : '');
        taste.setAttribute('data-wert', wert);
        taste.textContent = beschriftung;
        return taste;
    }

    function zeileAus(zeichen) {
        var zeile = document.createElement('div');
        zeile.className = 'terminal-osk-zeile';
        zeichen.forEach(function (z) {
            zeile.appendChild(erstelleTaste(z, z));
        });
        return zeile;
    }

    function zeichnen() {
        behaelter.innerHTML = '';

        if (!aktivesFeld) {
            return;
        }

        if (aktivesFeld.getAttribute('data-tastatur') === 'code') {
            var reihe = [];
            CODE_ZEICHEN.forEach(function (z, index) {
                reihe.push(z);
                if (reihe.length === 10 || index === CODE_ZEICHEN.length - 1) {
                    behaelter.appendChild(zeileAus(reihe));
                    reihe = [];
                }
            });
        } else {
            var layout = grossModus ? textLayout.groß : textLayout.klein;
            layout.forEach(function (zeichen) {
                behaelter.appendChild(zeileAus(zeichen));
            });
        }

        var steuerZeile = document.createElement('div');
        steuerZeile.className = 'terminal-osk-zeile';

        if (aktivesFeld.getAttribute('data-tastatur') !== 'code') {
            steuerZeile.appendChild(
                erstelleTaste(grossModus ? 'abc' : 'ABC', '#groß', 'terminal-osk-taste-breit')
            );
        }

        steuerZeile.appendChild(
            erstelleTaste('Löschen', '#löschen', 'terminal-osk-taste-breit terminal-osk-taste-extra-breit')
        );
        behaelter.appendChild(steuerZeile);
    }

    function aktualisiereUmschalter() {
        behaelter.hidden = !sichtbar;
        if (umschalter) {
            umschalter.textContent = sichtbar ? 'Tastatur schließen' : 'Tastatur öffnen';
            umschalter.setAttribute('aria-expanded', sichtbar ? 'true' : 'false');
        }
    }

    function setzeAktivesFeld(feld) {
        aktivesFeld = feld;
        bereich.hidden = false;
        if (titel) {
            var label = document.querySelector('label[for="' + feld.id + '"]');
            titel.textContent = 'Bildschirmtastatur: ' + (label ? label.textContent : '');
        }
        zeichnen();
        aktualisiereUmschalter();
    }

    function fuegeEin(text) {
        if (!aktivesFeld) {
            return;
        }
        var start = aktivesFeld.selectionStart;
        var ende = aktivesFeld.selectionEnd;
        if (typeof start !== 'number' || typeof ende !== 'number') {
            aktivesFeld.value += text;
        } else {
            aktivesFeld.value = aktivesFeld.value.slice(0, start) + text + aktivesFeld.value.slice(ende);
            var neu = start + text.length;
            aktivesFeld.setSelectionRange(neu, neu);
        }
        aktivesFeld.focus();
    }

    function lösche() {
        if (!aktivesFeld) {
            return;
        }
        var start = aktivesFeld.selectionStart;
        var ende = aktivesFeld.selectionEnd;
        if (typeof start !== 'number' || typeof ende !== 'number') {
            aktivesFeld.value = aktivesFeld.value.slice(0, -1);
        } else if (start !== ende) {
            aktivesFeld.value = aktivesFeld.value.slice(0, start) + aktivesFeld.value.slice(ende);
            aktivesFeld.setSelectionRange(start, start);
        } else if (start > 0) {
            aktivesFeld.value = aktivesFeld.value.slice(0, start - 1) + aktivesFeld.value.slice(ende);
            aktivesFeld.setSelectionRange(start - 1, start - 1);
        }
        aktivesFeld.focus();
    }

    behaelter.addEventListener('click', function (ereignis) {
        var ziel = ereignis.target;
        if (!(ziel instanceof HTMLElement) || !ziel.hasAttribute('data-wert')) {
            return;
        }

        var wert = ziel.getAttribute('data-wert');
        if (wert === '#löschen') {
            lösche();
        } else if (wert === '#groß') {
            grossModus = !grossModus;
            zeichnen();
        } else {
            fuegeEin(wert);
        }
    });

    if (umschalter) {
        umschalter.addEventListener('click', function () {
            sichtbar = !sichtbar;
            aktualisiereUmschalter();
        });
    }

    felder.forEach(function (feld) {
        feld.addEventListener('focus', function () {
            setzeAktivesFeld(feld);
        });
        feld.addEventListener('click', function () {
            setzeAktivesFeld(feld);
        });
    });

    // Startpunkt: das erste noch leere Feld – meist die Serveradresse.
    var start = felder.filter(function (feld) {
        return feld.value.trim() === '';
    })[0] || felder[0];

    setzeAktivesFeld(start);
    start.focus();
})();
</script>
<?php endif; ?>

</body>
</html>
