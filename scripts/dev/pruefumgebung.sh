#!/usr/bin/env bash
#
# ===========================================================================
#  Pruefumgebung: zwei Staende des Projekts gegeneinander rendern
# ===========================================================================
#
#  Wofuer:
#      Wer Markup verschiebt (Controller -> Teil-Template), muss belegen, dass
#      dieselbe Seite danach dasselbe HTML liefert. Dafuer braucht es zwei
#      lauffaehige Staende auf denselben Daten - einen alten (ein Commit) und
#      den aktuellen Arbeitsstand - und einen Vergleich, der Leerraum ignoriert.
#
#  Aufruf:
#      scripts/dev/pruefumgebung.sh aufbauen [<commit>]     # Standard: HEAD
#      scripts/dev/pruefumgebung.sh spiegeln                # Arbeitsstand neu einspielen
#      scripts/dev/pruefumgebung.sh daten <datei.sql>       # Probe-Daten einspielen
#      scripts/dev/pruefumgebung.sh pruefen                 # Fachlogik nachrechnen (T-140)
#      scripts/dev/pruefumgebung.sh terminal [alt|neu|beide] [--offline]
#      scripts/dev/pruefumgebung.sh backend  [alt|neu|beide]
#      scripts/dev/pruefumgebung.sh holen alt|neu [--post 'a=b'] [--token] <pfad>
#      scripts/dev/pruefumgebung.sh vergleichen [--post 'a=b'] [--token] <pfad>
#      scripts/dev/pruefumgebung.sh sql [--offline] <statement>
#      scripts/dev/pruefumgebung.sh meldungen               # PHP-Meldungen der Serverlogs
#      scripts/dev/pruefumgebung.sh status
#      scripts/dev/pruefumgebung.sh abraeumen
#
#  Terminal-Modus:
#      `terminal` schreibt die Konfiguration eines gekoppelten Geraets
#      (`installation_typ = terminal`, `terminal.id`) - dieselben Ports,
#      dieselben Probe-Datenbanken, kein zweites Verzeichnis. `--offline` zeigt
#      die Hauptdatenbank zusaetzlich auf eine Datenbank, die es nicht gibt;
#      damit ist das Geraet offline und schreibt in die Ausweichdatenbank.
#      Der Modus haengt am Stand, nicht am Aufruf: `spiegeln` behaelt ihn bei,
#      und der Wechsel wirkt sofort - ein Server muss nicht neu starten. Genau
#      so wird aus einem Ausfall die Rueckkehr: erst `terminal --offline`
#      buchen, dann `terminal`, ein Seitenaufruf, und die Queue laeuft an.
#      Beide Staende koennen verschiedene Modi haben - `terminal neu --offline`
#      neben `backend alt` ist ein Terminal ohne Verbindung samt Backend, das
#      seine Fehlermeldung zeigt.
#
#      Der Pfad ist hier `terminal.php?aktion=…`, nicht `?seite=…`:
#          scripts/dev/pruefumgebung.sh vergleichen 'terminal.php?aktion=start'
#      Ein `?seite=…` beantwortet ein Terminal mit einer Weiterleitung auf
#      `terminal.php` - dann vergleicht man zwei leere Antworten.
#
#  Was die Umgebung ist:
#      - zwei Kopien des Repos ausserhalb der Arbeitskopie
#        ("alt" = <commit> per `git archive`, "neu" = Arbeitsstand per rsync)
#      - EIN Paar Probe-Datenbanken (`zeit_probe`, `zeit_probe_off`) aus
#        `sql/01_initial_schema.sql` bzw. `sql/offline_db_schema.sql`,
#        an denen beide Staende haengen - nur so vergleicht man Markup und
#        nicht Daten
#      - ein erfundener Pruefbenutzer (Probe Pruefer / probe), angelegt ueber
#        die Erstinstallations-Maske der App selbst
#      - zwei `php -S` mit abgeschaltetem OPcache (sonst misst man den Stand
#        von vorhin, siehe docs/lokale_entwicklungsumgebung.md, Abschnitt 5)
#
#  Was die Umgebung NICHT ist:
#      Kein Ersatz fuer die Entwicklungsumgebung. Sie fasst `zeiterfassung`
#      und `zeiterfassung_offline` nicht an; alle Datenbanknamen muessen mit
#      `zeit_probe` beginnen, sonst bricht das Skript ab.
#
#  Fachliche Probe-Daten bringt jeder Patch selbst mit (`daten <datei.sql>`) -
#  welche Kanten eine Maske hat, weiss nur der Patch, der sie anfasst.
#
#  `pruefen` rechnet die Fachlogik nach (Rundung, Pausen, Salden) statt sie nur
#  anzuzeigen. Es spiegelt vorher den Arbeitsstand und startet das Skript in der
#  Kopie - nur dort steht eine Konfiguration, die auf `zeit_probe` zeigt. Aus
#  der Arbeitskopie heraus laesst es sich nicht starten, und das ist Absicht:
#  dort gilt die Entwicklungsdatenbank mit echten Personendaten.
#
#  Abraeumen ist Pflicht und wird nachgeprueft, nicht geglaubt
#  (docs/wartungscheckliste.md).
# ===========================================================================

set -uo pipefail

SKRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJEKT="$(cd "$SKRIPTDIR/../.." && pwd)"

# Ablageort bewusst NICHT in /tmp: Ein Neustart hat die Umgebung schon einmal
# mitten im Lauf geloescht (P-2026-08-16-01).
BASIS="${ZEIT_PRUEFDIR:-${XDG_CACHE_HOME:-$HOME/.cache}/zeiterfassung-pruefumgebung}"

PORT_ALT="${ZEIT_PORT_ALT:-8801}"
PORT_NEU="${ZEIT_PORT_NEU:-8802}"

DB_HOST="${ZEIT_DB_HOST:-127.0.0.1}"
DB_USER="${ZEIT_DB_USER:-zeiterfassung}"
DB_PASS="${ZEIT_DB_PASS:-zeiterfassung}"
DB_PROBE="${ZEIT_PROBE_DB:-zeit_probe}"
DB_PROBE_OFF="${DB_PROBE}_off"

# Der Ausfall: ein Datenbankname, den es nicht gibt. Bewusst auf demselben,
# erreichbaren Host - dann scheitert die Verbindung sofort statt in einen
# Zeitablauf zu laufen, und der Name faellt unter dieselbe zeit_probe-Regel.
DB_PROBE_TOT="${DB_PROBE}_tot"

# Erfundener Pruefbenutzer - reine Testdaten, nie ein echter Mensch.
PRUEF_BENUTZER="probe"
PRUEF_PASSWORT="probe1234"

# Erfundenes Terminal, wie es nach einer Kopplung in der Datenbank steht.
TERMINAL_ID=1
TERMINAL_NAME="Probe-Terminal"

rot()  { printf '\033[31m%s\033[0m\n' "$*"; }
gruen(){ printf '\033[32m%s\033[0m\n' "$*"; }
schritt() { echo; echo "---- $* ----"; }
fehler() { rot "FEHLER: $*"; exit 1; }

# Schutz vor dem einen Fehler, der wehtut: eine Probe-Datenbank, die keine ist.
case "$DB_PROBE" in
    zeit_probe*) ;;
    *) fehler "Datenbankname '$DB_PROBE' beginnt nicht mit 'zeit_probe'." ;;
esac

db() { mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$@" 2>/dev/null; }

port_belegt() {
    local port="$1"
    ss -ltn | awk -v muster=":${port}\$" 'NR>1 && $4 ~ muster { gefunden=1 } END { exit gefunden ? 0 : 1 }'
}

url_von() {
    case "$1" in
        alt) echo "http://127.0.0.1:$PORT_ALT" ;;
        neu) echo "http://127.0.0.1:$PORT_NEU" ;;
        *)   fehler "Unbekannter Stand '$1' (erlaubt: alt, neu)" ;;
    esac
}

# HTML vergleichbar machen: ein Strom, Umbruch nach jedem '>', Token maskiert,
# Einrueckung weg. Damit faellt der fehlende Zeilenumbruch von Csrf::feld()
# ebenso weg wie die Ebene, die ein Block verliert, wenn er aus der Seite in
# ein Teil-Template wandert - beides ist Leerraum und kein Unterschied.
#
# Ebenso der Cache-Buster '?v=<mtime>' der Terminal-Seiten: 'git archive' setzt
# allen Dateien die Zeit des Commits, rsync behaelt die der Arbeitskopie - der
# Wert ist also **immer** verschieden, und zwar in jeder Zeile mit CSS oder JS.
# Maskiert werden nur die Ziffern; '?v=' bleibt stehen, damit ein Wechsel des
# Verfahrens (Hash statt Zeitstempel) weiterhin als Unterschied auffaellt.
normalisieren() {
    tr -d '\r\n\t' \
        | sed -e 's/[[:space:]]\{1,\}/ /g' -e 's/>/>\n/g' \
        | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
        | sed -e 's/\(name="csrf_token" value="\)[^"]*"/\1TOKEN"/g' \
        | sed -e 's/\(?v=\)[0-9]\{1,\}/\1ZEITSTEMPEL/g'
}

# Der Modus eines Standes - 'backend', 'terminal' oder 'terminal offline'.
# Er liegt neben dem Stand und nicht im Aufruf, damit 'spiegeln' ihn nicht
# stillschweigend auf Backend zuruecksetzt: Wer mitten in einem Offline-Test
# den Arbeitsstand nachzieht, will danach dasselbe Geraet vorfinden.
modus_lesen() {
    local name="$1"
    if [ -f "$BASIS/$name.modus" ]; then
        cat "$BASIS/$name.modus"
    else
        echo "backend"
    fi
}

konfiguration_schreiben() {
    local name="$1"
    local ziel="$BASIS/$name"

    local modus typ hauptdb terminal_block
    modus="$(modus_lesen "$name")"

    typ='backend'
    hauptdb="$DB_PROBE"
    case "$modus" in
        terminal*) typ='terminal' ;;
    esac
    case "$modus" in
        *offline) hauptdb="$DB_PROBE_TOT" ;;
    esac

    # Ein Terminal kennt sich selbst - ohne 'terminal.id' traegt keine Buchung
    # eine Geraetekennung, und dann prueft der Test etwas anderes als gemeint.
    if [ "$typ" = 'terminal' ]; then
        terminal_block="    // Wer dieses Geraet ist - wie nach einer Kopplung.
    'terminal' => [
        'id'                           => $TERMINAL_ID,
        'name'                         => '$TERMINAL_NAME',
        'standort_beschreibung'        => 'Pruefumgebung',
        'abteilung_id'                 => null,
        'auto_logout_timeout_sekunden' => 60,
        'offline_erlaubt_kommen_gehen' => true,
        'offline_erlaubt_auftraege'    => true,
"
    else
        terminal_block="    'terminal' => [
"
    fi

    terminal_block="$terminal_block
        // Bridge aus: sie wuerde nur ins Leere verbinden und Meldungen erzeugen.
        'rfid_ws' => [
            'enabled' => false,
            'url'     => 'ws://127.0.0.1:8765',
        ],
    ],"

    cat > "$ziel/config/config.local.php" <<PHPKONFIG
<?php
declare(strict_types=1);

/**
 * Konfiguration der Pruefumgebung - erzeugt von scripts/dev/pruefumgebung.sh.
 * Modus dieses Standes: $modus
 *
 * Haengt bewusst an den Probe-Datenbanken. Die Entwicklungsdatenbank
 * 'zeiterfassung' wird von dieser Umgebung nie angefasst.
 */

return [
    'app' => [
        'name' => 'Zeiterfassung',
        // 'php -S' liefert public/ direkt an der Wurzel aus, deshalb leer.
        'base_url' => '',
        'debug' => true,
        'installation_typ' => '$typ',
    ],

    'timezone' => 'Europe/Berlin',

    'db' => [
        'host'    => '$DB_HOST',
        'dbname'  => '$hauptdb',
        'charset' => 'utf8mb4',
        'user'    => '$DB_USER',
        'pass'    => '$DB_PASS',
    ],

    'offline_db' => [
        'enabled' => true,
        'host'    => '$DB_HOST',
        'dbname'  => '$DB_PROBE_OFF',
        'charset' => 'utf8mb4',
        'user'    => '$DB_USER',
        'pass'    => '$DB_PASS',
    ],

$terminal_block
];
PHPKONFIG

    # Gegenprobe: die Kopie darf niemals auf die Entwicklungsdatenbank zeigen.
    if ! grep -q "'dbname'  => '$hauptdb'," "$ziel/config/config.local.php"; then
        fehler "Konfiguration in $ziel zeigt nicht auf $hauptdb."
    fi
}

arbeitsstand_spiegeln() {
    # config.local.php der Arbeitskopie bleibt draussen: sie zeigt auf die
    # Entwicklungsdatenbank, und genau die soll ein Testlauf nicht sehen.
    rsync -a --delete \
        --exclude '.git' \
        --exclude 'config/config.local.php' \
        "$PROJEKT/" "$BASIS/neu/" || fehler "rsync des Arbeitsstands fehlgeschlagen."
    konfiguration_schreiben neu
}

server_starten() {
    local name="$1" port="$2" verzeichnis="$3"

    php -S "127.0.0.1:$port" -t "$verzeichnis/public" \
        -d opcache.enable=0 -d opcache.enable_cli=0 \
        -d error_reporting=E_ALL -d display_errors=0 -d log_errors=1 \
        > "$BASIS/$name.log" 2>&1 &
    echo $! > "$BASIS/$name.pid"

    local versuch=0
    while [ $versuch -lt 50 ]; do
        if curl -s -o /dev/null "http://127.0.0.1:$port/"; then
            echo "Stand '$name' laeuft auf Port $port (PID $(cat "$BASIS/$name.pid"))."
            return 0
        fi
        sleep 0.2
        versuch=$((versuch + 1))
    done

    fehler "Stand '$name' antwortet auf Port $port nicht (siehe $BASIS/$name.log)."
}

# ---------------------------------------------------------------------------
#  aufbauen
# ---------------------------------------------------------------------------
befehl_aufbauen() {
    local commit="${1:-HEAD}"

    [ -f "$PROJEKT/sql/01_initial_schema.sql" ] || fehler "$PROJEKT sieht nicht nach dem Projekt aus."
    git -C "$PROJEKT" rev-parse --verify "$commit^{commit}" >/dev/null 2>&1 \
        || fehler "'$commit' ist kein Commit."
    db -e 'SELECT 1;' >/dev/null || fehler "Keine Verbindung zur Datenbank ($DB_USER@$DB_HOST)."

    for port in "$PORT_ALT" "$PORT_NEU"; do
        port_belegt "$port" && fehler "Port $port ist belegt - erst 'abraeumen'."
    done

    local kurz
    kurz="$(git -C "$PROJEKT" rev-parse --short "$commit")"

    schritt "Verzeichnisse ($BASIS)"
    rm -rf "$BASIS/alt" "$BASIS/neu"
    # Auch die Modusmerker weg: Eine frische Umgebung ist ein Backend, nicht
    # das Terminal von gestern.
    rm -f "$BASIS/alt.modus" "$BASIS/neu.modus"
    mkdir -p "$BASIS/alt" "$BASIS/neu" || fehler "Konnte $BASIS nicht anlegen."

    schritt "Stand 'alt' aus $commit ($kurz)"
    git -C "$PROJEKT" archive "$commit" | tar -x -C "$BASIS/alt" || fehler "git archive fehlgeschlagen."
    konfiguration_schreiben alt
    echo "$kurz" > "$BASIS/alt.commit"

    schritt "Stand 'neu' aus dem Arbeitsstand"
    arbeitsstand_spiegeln

    schritt "Probe-Datenbanken ($DB_PROBE, $DB_PROBE_OFF)"
    db -e "DROP DATABASE IF EXISTS \`$DB_PROBE\`;
           DROP DATABASE IF EXISTS \`$DB_PROBE_OFF\`;
           CREATE DATABASE \`$DB_PROBE\`     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
           CREATE DATABASE \`$DB_PROBE_OFF\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || fehler "Konnte die Probe-Datenbanken nicht anlegen."
    db "$DB_PROBE"     < "$PROJEKT/sql/01_initial_schema.sql" || fehler "Schema-Import fehlgeschlagen."
    db "$DB_PROBE_OFF" < "$PROJEKT/sql/offline_db_schema.sql" || fehler "Offline-Schema-Import fehlgeschlagen."
    echo "Tabellen: $(db -N -B "$DB_PROBE" -e 'SHOW TABLES;' | wc -l) (Haupt) / $(db -N -B "$DB_PROBE_OFF" -e 'SHOW TABLES;' | wc -l) (Offline)"

    schritt "Server"
    server_starten alt "$PORT_ALT" "$BASIS/alt"
    server_starten neu "$PORT_NEU" "$BASIS/neu"

    schritt "Pruefbenutzer und Anmeldung"
    benutzer_anlegen
    anmelden alt "$PORT_ALT"
    anmelden neu "$PORT_NEU"

    schritt "Bereit"
    echo "alt = $kurz  ->  http://127.0.0.1:$PORT_ALT"
    echo "neu = Arbeitsstand  ->  http://127.0.0.1:$PORT_NEU"
    echo "Vergleich: $0 vergleichen '?seite=smoke_test'"
    echo "Am Ende:   $0 abraeumen"
}

benutzer_anlegen() {
    # Ueber die Erstinstallations-Maske der App, nicht per Hand-INSERT: so
    # entstehen Passwort-Hash und Rollen genauso wie im echten Ablauf.
    local antwort
    antwort="$(curl -s -X POST \
        -d "aktion=initial_admin" \
        -d "vorname=Probe" \
        -d "nachname=Pruefer" \
        -d "benutzername=$PRUEF_BENUTZER" \
        -d "email=probe@example.invalid" \
        -d "passwort=$PRUEF_PASSWORT" \
        -d "passwort_bestaetigung=$PRUEF_PASSWORT" \
        "http://127.0.0.1:$PORT_ALT/?seite=login")"

    if ! echo "$antwort" | grep -q 'Der erste Admin-Benutzer wurde angelegt'; then
        fehler "Pruefbenutzer wurde nicht angelegt (Antwort der Erstinstallations-Maske passt nicht)."
    fi
    echo "Pruefbenutzer '$PRUEF_BENUTZER' angelegt (Passwort: $PRUEF_PASSWORT)."
}

anmelden() {
    local name="$1" port="$2"
    rm -f "$BASIS/$name.cookies"

    curl -s -o /dev/null -c "$BASIS/$name.cookies" -b "$BASIS/$name.cookies" \
        -d "benutzername=$PRUEF_BENUTZER" -d "passwort=$PRUEF_PASSWORT" \
        "http://127.0.0.1:$port/?seite=login"

    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' -b "$BASIS/$name.cookies" \
        "http://127.0.0.1:$port/?seite=dashboard")"
    [ "$code" = "200" ] || fehler "Anmeldung auf Stand '$name' fehlgeschlagen (Dashboard: HTTP $code)."
    echo "Stand '$name': angemeldet (Dashboard HTTP 200)."
}

# ---------------------------------------------------------------------------
#  spiegeln / daten
# ---------------------------------------------------------------------------
befehl_spiegeln() {
    [ -d "$BASIS/neu" ] || fehler "Keine Umgebung vorhanden - erst 'aufbauen'."
    arbeitsstand_spiegeln
    echo "Arbeitsstand nach $BASIS/neu gespiegelt."
}

befehl_daten() {
    local datei="${1:-}"
    [ -f "$datei" ] || fehler "SQL-Datei '$datei' nicht gefunden."
    db "$DB_PROBE" < "$datei" || fehler "Einspielen von '$datei' fehlgeschlagen."
    echo "'$datei' in $DB_PROBE eingespielt."
}

# ---------------------------------------------------------------------------
#  pruefen
# ---------------------------------------------------------------------------
# Warum das hier steht und nicht in der Anleitung: Das Fachlogik-Pruefskript
# laeuft nur gegen eine `zeit_probe`-Datenbank, und welche Datenbank gilt,
# entscheidet die `config.local.php` NEBEN dem Skript - nicht eine
# Umgebungsvariable. `config/config.php` liefert eine vorhandene
# `config.local.php` zurueck, bevor je ein `getenv()` faellt, und `Database`
# liest dieselbe Datei noch einmal selbst. Der frueher dokumentierte Aufruf
# `ZEIT_DB_NAME=zeit_probe php scripts/dev/pruefe_fachlogik.php` konnte deshalb
# nie funktionieren: aus der Arbeitskopie heraus gilt immer deren eigene
# Konfiguration - also die Entwicklungsdatenbank mit echten Personendaten.
# Gerettet hat das nur die Sperre im Skript selbst.
#
# Die einzige Stelle, an der die richtige Konfiguration steht, ist die Kopie
# `neu` dieser Umgebung. Also laeuft das Skript dort - und wird vorher
# gespiegelt, damit niemand den Stand von vorhin prueft.
befehl_pruefen() {
    [ -d "$BASIS/neu" ] || fehler "Keine Umgebung vorhanden - erst 'aufbauen'."

    local skript="$BASIS/neu/scripts/dev/pruefe_fachlogik.php"

    schritt "Arbeitsstand spiegeln"
    arbeitsstand_spiegeln

    [ -f "$skript" ] || fehler "Pruefskript nicht gefunden: $skript"

    schritt "Fachlogik-Pruefskript"
    # OPcache aus: sonst misst der Lauf den Stand von vorhin
    # (docs/lokale_entwicklungsumgebung.md, Abschnitt 5).
    php -d opcache.enable=0 -d opcache.enable_cli=0 "$skript" "$@"
    local ergebnis=$?

    echo
    if [ "$ergebnis" -eq 0 ]; then
        gruen "Alle Faelle OK."
    else
        rot "Mindestens ein Fall weicht ab - siehe oben."
    fi

    return "$ergebnis"
}

# ---------------------------------------------------------------------------
#  terminal / backend
# ---------------------------------------------------------------------------
# Ein gekoppeltes Terminal steht auch in der Datenbank. Ohne diese Zeile zeigt
# jede Buchung mit `terminal_id` ins Leere und die Terminal-Verwaltung im
# Backend bleibt leer - der Test prueft dann eine Lage, die es nicht gibt.
terminal_eintragen() {
    db "$DB_PROBE" -e "INSERT INTO \`terminal\`
                           (id, name, standort_beschreibung, modus, aktiv,
                            offline_erlaubt_kommen_gehen, offline_erlaubt_auftraege)
                       VALUES ($TERMINAL_ID, '$TERMINAL_NAME', 'Pruefumgebung', 'terminal', 1, 1, 1)
                       ON DUPLICATE KEY UPDATE name = '$TERMINAL_NAME', aktiv = 1;" \
        || fehler "Konnte das Probe-Terminal nicht eintragen."
}

modus_setzen() {
    local stand="$1" modus="$2"
    [ -d "$BASIS/neu" ] || fehler "Keine Umgebung vorhanden - erst 'aufbauen'."

    local namen
    case "$stand" in
        beide) namen="alt neu" ;;
        *)     namen="$stand" ;;
    esac

    case "$modus" in
        terminal*) terminal_eintragen ;;
    esac

    local name
    for name in $namen; do
        [ -d "$BASIS/$name" ] || fehler "Stand '$name' gibt es nicht - erst 'aufbauen'."
        echo "$modus" > "$BASIS/$name.modus"
        konfiguration_schreiben "$name"
        echo "Stand '$name' ($(url_von "$name")): $modus"
    done

    # Kein Neustart der Server: 'config/config.php' liest die lokale Datei je
    # Anfrage neu (clearstatcache), der naechste Seitenaufruf sieht den Modus.
    case "$modus" in
        terminal*) echo "Terminal-Startbildschirm: $(url_von "${namen%% *}")/terminal.php" ;;
    esac
}

befehl_terminal() {
    local stand="beide" modus="terminal"
    while [ $# -gt 0 ]; do
        case "$1" in
            --offline)     modus="terminal offline"; shift ;;
            alt|neu|beide) stand="$1"; shift ;;
            *) fehler "Unbekannte Angabe '$1' (erlaubt: alt, neu, beide, --offline)" ;;
        esac
    done
    modus_setzen "$stand" "$modus"
}

befehl_backend() {
    local stand="${1:-beide}"
    case "$stand" in
        alt|neu|beide) ;;
        *) fehler "Unbekannter Stand '$stand' (erlaubt: alt, neu, beide)" ;;
    esac
    modus_setzen "$stand" "backend"
}

# ---------------------------------------------------------------------------
#  holen / vergleichen
# ---------------------------------------------------------------------------
#  Gemeinsame Optionen:
#    --post 'a=b&c=d'  POST statt GET
#    --token           vorher die Seite holen und das erste csrf_token aus dem
#                      Markup an den POST-Rumpf haengen (ein Bereich je Seite -
#                      wer zwei braucht, baut den Rumpf selbst)
POST_RUMPF=""
MIT_TOKEN=0

optionen_lesen() {
    POST_RUMPF=""
    MIT_TOKEN=0
    while [ $# -gt 0 ]; do
        case "$1" in
            --post)  POST_RUMPF="${2:-}"; shift 2 ;;
            --token) MIT_TOKEN=1; shift ;;
            *) break ;;
        esac
    done
    RESTLICHE=("$@")
}

seite_holen() {
    local name="$1" pfad="$2" ziel="$3"
    local basis rumpf
    basis="$(url_von "$name")"

    if [ "$MIT_TOKEN" = "1" ]; then
        local token
        token="$(curl -s -b "$BASIS/$name.cookies" -c "$BASIS/$name.cookies" "$basis/$pfad" \
            | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//')"
        [ -n "$token" ] || fehler "Kein csrf_token auf '$pfad' (Stand $name) gefunden."
        rumpf="$POST_RUMPF&csrf_token=$token"
    else
        rumpf="$POST_RUMPF"
    fi

    if [ -n "$rumpf" ]; then
        curl -s -o "$ziel" -w '%{http_code}' -b "$BASIS/$name.cookies" -c "$BASIS/$name.cookies" \
            --data-raw "$rumpf" "$basis/$pfad"
    else
        curl -s -o "$ziel" -w '%{http_code}' -b "$BASIS/$name.cookies" -c "$BASIS/$name.cookies" \
            "$basis/$pfad"
    fi
}

befehl_holen() {
    local name="${1:-}"; shift
    optionen_lesen "$@"
    local pfad="${RESTLICHE[0]:-}"
    [ -n "$pfad" ] || fehler "Pfad fehlt, z. B. '?seite=smoke_test'."

    local code
    code="$(seite_holen "$name" "$pfad" "$BASIS/$name.html")"
    echo "HTTP $code  ->  $BASIS/$name.html ($(wc -c < "$BASIS/$name.html") Bytes)"
}

befehl_vergleichen() {
    optionen_lesen "$@"
    local pfad="${RESTLICHE[0]:-}"
    [ -n "$pfad" ] || fehler "Pfad fehlt, z. B. '?seite=smoke_test'."

    local code_alt code_neu
    code_alt="$(seite_holen alt "$pfad" "$BASIS/alt.html")"
    code_neu="$(seite_holen neu "$pfad" "$BASIS/neu.html")"

    normalisieren < "$BASIS/alt.html" > "$BASIS/alt.norm"
    normalisieren < "$BASIS/neu.html" > "$BASIS/neu.norm"

    diff -u "$BASIS/alt.norm" "$BASIS/neu.norm" > "$BASIS/diff.txt"
    local abweichungen
    abweichungen="$(grep -c '^[+-][^+-]' "$BASIS/diff.txt")"

    echo "Pfad:   $pfad"
    echo "HTTP:   alt $code_alt / neu $code_neu"
    echo "Bytes:  alt $(wc -c < "$BASIS/alt.html") / neu $(wc -c < "$BASIS/neu.html")"
    echo "Zeilen: $abweichungen abweichend (Diff: $BASIS/diff.txt)"

    if [ "$code_alt" != "$code_neu" ]; then
        rot "Unterschiedlicher HTTP-Status."
        return 1
    fi
    if [ "$abweichungen" != "0" ]; then
        # Letzte Instanz: Ein Block, der in ein Teil-Template wandert, verliert
        # eine Einrueckungsebene - das steht danach als Leerzeichen mitten im
        # Strom und ist trotzdem kein Unterschied. Wer beide Dokumente ohne
        # jedes Leerzeichen vergleicht, sieht es.
        tr -d '[:space:]' < "$BASIS/alt.norm" > "$BASIS/alt.eng"
        tr -d '[:space:]' < "$BASIS/neu.norm" > "$BASIS/neu.eng"
        if cmp -s "$BASIS/alt.eng" "$BASIS/neu.eng"; then
            gruen "Nur Leerraum: ohne jedes Leerzeichen sind beide Dokumente identisch."
            return 0
        fi
        head -40 "$BASIS/diff.txt"
        return 1
    fi
    gruen "Kein Unterschied."
    return 0
}

# ---------------------------------------------------------------------------
#  sql / meldungen / status
# ---------------------------------------------------------------------------
befehl_sql() {
    local ziel="$DB_PROBE"
    if [ "${1:-}" = "--offline" ]; then
        ziel="$DB_PROBE_OFF"
        shift
    fi
    [ -n "${1:-}" ] || fehler "SQL-Statement fehlt."
    mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$ziel" -e "$1"
}

befehl_meldungen() {
    local gefunden=0
    for name in alt neu; do
        [ -f "$BASIS/$name.log" ] || continue
        local treffer
        treffer="$(grep -E 'PHP (Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable)|Uncaught' "$BASIS/$name.log")"
        if [ -n "$treffer" ]; then
            gefunden=1
            rot "Stand '$name':"
            echo "$treffer"
        fi
    done
    [ "$gefunden" = "0" ] && gruen "Beide Serverlogs ohne PHP-Meldung."
    return $gefunden
}

befehl_status() {
    echo "Ablage:  $BASIS"
    [ -f "$BASIS/alt.commit" ] && echo "alt:     $(cat "$BASIS/alt.commit")"
    echo
    echo "Modus:"
    local name
    for name in alt neu; do
        [ -d "$BASIS/$name" ] && echo "  $name: $(modus_lesen "$name")"
    done
    echo "Ports:"
    ss -ltn | awk 'NR>1 && $4 ~ /:88[0-9][0-9]$/ { print "  belegt: " $4 }'
    echo "Prozesse:"
    pgrep -af 'php -S' | grep -v pgrep | sed 's/^/  /'
    echo "Datenbanken:"
    db -N -B -e 'SHOW DATABASES;' | grep '^zeit_probe' | sed 's/^/  /'
}

# ---------------------------------------------------------------------------
#  abraeumen
# ---------------------------------------------------------------------------
#  'kill' und 'pkill' melden Erfolg, auch wenn der Prozess weiterlaeuft -
#  zweimal hing danach noch ein Server auf seinem Port, einmal ueber eine
#  Sitzungsgrenze hinweg. Deshalb wird hier nachgesehen, nicht geglaubt.
befehl_abraeumen() {
    schritt "Server beenden"
    for name in alt neu; do
        if [ -f "$BASIS/$name.pid" ]; then
            kill "$(cat "$BASIS/$name.pid")" 2>/dev/null
            rm -f "$BASIS/$name.pid"
        fi
    done
    pkill -f "php -S 127.0.0.1:$PORT_ALT" 2>/dev/null
    pkill -f "php -S 127.0.0.1:$PORT_NEU" 2>/dev/null
    sleep 1

    schritt "Datenbanken loeschen"
    db -e "DROP DATABASE IF EXISTS \`$DB_PROBE\`; DROP DATABASE IF EXISTS \`$DB_PROBE_OFF\`;" \
        || rot "Konnte die Probe-Datenbanken nicht loeschen."

    schritt "Verzeichnis loeschen"
    rm -rf "$BASIS"

    schritt "Nachpruefung (erwartet: dreimal nichts)"
    local rest=0
    local ports prozesse datenbanken
    ports="$(ss -ltn | awk 'NR>1 && $4 ~ /:88[0-9][0-9]$/ { print "Port belegt: " $4 }')"
    prozesse="$(pgrep -af 'php -S' | grep -v pgrep)"
    datenbanken="$(db -N -B -e 'SHOW DATABASES;' | grep '^zeit_probe')"

    for zeile in "$ports" "$prozesse" "$datenbanken"; do
        if [ -n "$zeile" ]; then
            rest=1
            rot "$zeile"
        fi
    done

    if [ "$rest" = "0" ]; then
        gruen "Aufgeraeumt: kein Port, kein Server, keine zeit_probe-Datenbank."
    else
        rot "Es sind Reste geblieben - siehe oben."
    fi

    echo
    echo "Entwicklungsdatenbanken (muessen unberuehrt sein):"
    db -N -B -e 'SHOW DATABASES;' | grep -E '^zeiterfassung' | sed 's/^/  /'
    return $rest
}

# ---------------------------------------------------------------------------
befehl="${1:-}"
[ $# -gt 0 ] && shift

case "$befehl" in
    aufbauen)    befehl_aufbauen "$@" ;;
    spiegeln)    befehl_spiegeln "$@" ;;
    daten)       befehl_daten "$@" ;;
    pruefen)     befehl_pruefen "$@" ;;
    terminal)    befehl_terminal "$@" ;;
    backend)     befehl_backend "$@" ;;
    holen)       befehl_holen "$@" ;;
    vergleichen) befehl_vergleichen "$@" ;;
    sql)         befehl_sql "$@" ;;
    meldungen)   befehl_meldungen "$@" ;;
    status)      befehl_status "$@" ;;
    abraeumen)   befehl_abraeumen "$@" ;;
    *)
        sed -n '2,74p' "$0" | sed 's/^#//; s/^ //'
        exit 1
        ;;
esac
