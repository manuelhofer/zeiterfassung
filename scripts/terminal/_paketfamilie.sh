# ===========================================================================
#  Zeiterfassung - Hallenterminal: Paketfamilie erkennen und Pakete holen
# ===========================================================================
#
#  KEIN eigenstaendiges Skript. Wird von den Installationsskripten der Stufen
#  3, 4 und 5 eingelesen:
#
#      . "$SKRIPTDIR/_paketfamilie.sh"
#
#  Zweck: **Eine** Stelle fuer alles, was je Paketmanager anders ist. Vorher
#  stand die Erkennung in jedem der drei Skripte noch einmal - wer eine
#  Distribution ergaenzen wollte, musste an drei Stellen daran denken, und die
#  vierte vergass er (T-104).
#
#  Was hier NICHT hingehoert: welche Pakete ein Skript braucht. Das ist je
#  Stufe verschieden (Webserver, Browser, Python) und steht dort, wo es
#  gebraucht wird. Hier steht nur, **wie** man auf dieser Familie installiert.
#
#  Erwartet vom aufrufenden Skript: eine Funktion `warnung`. Fehlt sie, wird
#  eine schlichte Ersatzfunktion angelegt - die Datei soll sich auch einzeln
#  einlesen lassen, etwa zum Ausprobieren.
# ===========================================================================

if ! declare -F warnung >/dev/null 2>&1; then
    warnung() { echo "WARNUNG: $*"; }
fi

# ---------------------------------------------------------------------------
# Setzt FAMILIE (apt | pacman | dnf | zypper) und BETRIEBSSYSTEM.
#
# Rueckgabe 0, wenn die Familie erkannt wurde, sonst 1. Der Aufrufer
# entscheidet, ob das ein Abbruch ist - fuer Stufe 3 ja, sonst vielleicht nicht.
#
# Nur die vier grossen Familien; Exoten muessen von Hand nacharbeiten
# (Spezifikation, Abschnitt 9).
# ---------------------------------------------------------------------------
erkenne_paketfamilie() {
    FAMILIE=""
    BETRIEBSSYSTEM="unbekannt"

    if [ -r /etc/os-release ]; then
        # In einer Subshell lesen: /etc/os-release setzt unter anderem NAME und
        # VERSION - Namen, die ein aufrufendes Skript ebenfalls benutzen
        # koennte. Vorher wurde die Datei direkt eingelesen; das ging gut, war
        # aber Glueck.
        local id id_like pretty name
        id="$(. /etc/os-release 2>/dev/null && printf '%s' "${ID:-}")"
        id_like="$(. /etc/os-release 2>/dev/null && printf '%s' "${ID_LIKE:-}")"
        pretty="$(. /etc/os-release 2>/dev/null && printf '%s' "${PRETTY_NAME:-}")"
        name="$(. /etc/os-release 2>/dev/null && printf '%s' "${NAME:-}")"

        BETRIEBSSYSTEM="${pretty:-${name:-unbekannt}}"

        case " $id $id_like " in
            *debian*|*ubuntu*|*raspbian*|*mint*)         FAMILIE="apt" ;;
            *arch*|*cachyos*|*manjaro*|*endeavouros*)    FAMILIE="pacman" ;;
            *fedora*|*rhel*|*centos*|*rocky*|*alma*)     FAMILIE="dnf" ;;
            *suse*|*sles*)                               FAMILIE="zypper" ;;
        esac
    fi

    [ -n "$FAMILIE" ]
}

# ---------------------------------------------------------------------------
# Paketquellen auffrischen. Einmal je Skriptlauf, vor dem ersten Installieren.
#
# Auf Arch ist das bewusst `-Syu` und nicht `-Sy`: Ein blosses `-Sy` mit
# nachfolgendem `-S` ist der bekannte Weg in eine halb aktualisierte
# Installation (partial upgrade). Auf einem Geraet, das danach jahrelang in
# einer Halle steht, ist das keine theoretische Sorge.
#
# dnf und zypper loesen beim Installieren ohnehin gegen die aktuellen Quellen
# auf - dort ist nichts zu tun.
# ---------------------------------------------------------------------------
paketquellen_auffrischen() {
    case "$FAMILIE" in
        apt)    DEBIAN_FRONTEND=noninteractive apt-get update || warnung "apt-get update fehlgeschlagen." ;;
        pacman) pacman -Syu --noconfirm || warnung "pacman -Syu fehlgeschlagen." ;;
    esac
    return 0
}

# ---------------------------------------------------------------------------
# Ein oder mehrere Pakete installieren.
#
#   paket_installieren cage
#   paket_installieren $PAKETE        # bewusst ohne Anfuehrungszeichen
#
# Rueckgabe ist die des Paketmanagers - der Aufrufer entscheidet, ob ein
# fehlendes Paket eine Warnung oder ein Abbruch ist. Auf einem Kiosk ist ein
# fehlender Browser ein Abbruch, ein fehlendes 'unclutter' nicht.
# ---------------------------------------------------------------------------
paket_installieren() {
    [ "$#" -gt 0 ] || return 0
    echo "  installiere: $*"
    case "$FAMILIE" in
        apt)    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@" ;;
        pacman) pacman -S --needed --noconfirm "$@" ;;
        dnf)    dnf install -y "$@" ;;
        zypper) zypper --non-interactive install "$@" ;;
        *)      warnung "Paketfamilie unbekannt - '$*' nicht installiert."; return 1 ;;
    esac
}
