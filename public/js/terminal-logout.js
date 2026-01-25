/*
 * Terminal: Logout Auto-Submit
 *
 * Ziel (T-042): Inline-JS aus `views/terminal/logout.php` entfernen.
 *
 * Konfiguration kommt über das Script-Tag:
 *   <script src="js/terminal-logout.js" data-form-id="logout-form" data-delay-ms="50"></script>
 */

(() => {
    const KEY = '__terminalLogoutAutoSubmit';

    // Defensive: falls die View via Cache zurückkommt, zuerst alte Timer entfernen.
    if (window[KEY] && typeof window[KEY].cleanup === 'function') {
        try { window[KEY].cleanup(); } catch (e) { /* ignore */ }
    }

    const findConfigScript = () => {
        // 1) Standard: currentScript (während Ausführung verfügbar)
        if (document.currentScript) return document.currentScript;

        // 2) Fallback: letztes Script mit passendem Dateinamen
        const scripts = document.getElementsByTagName('script');
        for (let i = scripts.length - 1; i >= 0; i--) {
            const s = scripts[i];
            const src = (s.getAttribute('src') || '').toLowerCase();
            if (src.includes('terminal-logout.js')) return s;
        }
        return null;
    };

    const scriptEl = findConfigScript();
    const ds = (scriptEl && scriptEl.dataset) ? scriptEl.dataset : {};

    const formId = (ds.formId && String(ds.formId)) ? String(ds.formId) : 'logout-form';
    let delayMs = Number(ds.delayMs || 50);
    if (!Number.isFinite(delayMs) || delayMs < 0) delayMs = 0;

    const form = document.getElementById(formId);
    if (!form) return;

    const state = {
        timer: null,
        cleanup: () => {
            if (state.timer) {
                clearTimeout(state.timer);
                state.timer = null;
            }
        },
    };
    window[KEY] = state;

    // Minimaler Delay, damit die Seite kurz rendern kann.
    state.timer = setTimeout(() => {
        try { form.submit(); } catch (e) { /* ignore */ }
    }, delayMs);
})();
