#!/usr/bin/env bash
# APP ist der aufgeloeste Projektpfad; nur ein unzugaenglicher Elternpfad
# erfordert einen Umzug. /root bleibt geschlossen fuer den Webserver.
backend_installationspfad() {
    local ZIEL="${1:-/var/www/zeiterfassung}"
    if runuser -u www-data -- test -x "$(dirname "$APP")"; then
        return 0
    fi
    if [ -e "$ZIEL" ] || [ -L "$ZIEL" ]; then
        echo "Installationspfad $APP ist fuer den Webserver gesperrt; $ZIEL ist bereits belegt. Es wurde nichts verschoben oder ueberschrieben." >&2
        return 1
    fi
    mkdir -p -m 0755 "$(dirname "$ZIEL")"
    if ! runuser -u www-data -- test -x "$(dirname "$ZIEL")"; then
        echo "Auch der Zielpfad $ZIEL ist fuer den Webserver gesperrt." >&2
        return 1
    fi
    mv -T -- "$APP" "$ZIEL"
    APP="$ZIEL"
    cd "$APP"
    echo "Anwendung nach $APP verschoben; vorhandene Konfiguration bleibt erhalten."
}
