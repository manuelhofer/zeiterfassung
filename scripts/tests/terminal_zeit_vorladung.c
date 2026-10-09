/* Nur im eigenen PHP-Testprozess vorladen; die Kerneluhr bleibt unverändert. */
#define _GNU_SOURCE
#include <dlfcn.h>
#include <stdlib.h>
#include <sys/time.h>
#include <time.h>

static time_t versatz(void) {
    const char *wert = getenv("ZEIT_TEST_UHRVERSATZ");
    return wert ? (time_t)strtoll(wert, NULL, 10) : 0;
}

time_t time(time_t *ziel) {
    static time_t (*echt)(time_t *);
    if (!echt) echt = dlsym(RTLD_NEXT, "time");
    time_t wert = echt(NULL) + versatz();
    if (ziel) *ziel = wert;
    return wert;
}

int gettimeofday(struct timeval *wert, void *zone) {
    static int (*echt)(struct timeval *, void *);
    if (!echt) echt = dlsym(RTLD_NEXT, "gettimeofday");
    int ergebnis = echt(wert, zone);
    if (ergebnis == 0) wert->tv_sec += versatz();
    return ergebnis;
}

int clock_gettime(clockid_t art, struct timespec *wert) {
    static int (*echt)(clockid_t, struct timespec *);
    if (!echt) echt = dlsym(RTLD_NEXT, "clock_gettime");
    int ergebnis = echt(art, wert);
    if (ergebnis == 0 && (art == CLOCK_REALTIME || art == CLOCK_REALTIME_COARSE)) wert->tv_sec += versatz();
    return ergebnis;
}
