/*
 * Terminal: Gemeinsame JS-Logik
 * - Live-Uhr im Kopfbereich (#terminal-uhr)
 * - Auto-Logout + Countdown (nur wenn ein Mitarbeiter angemeldet ist)
 * - Scanner-Fokus-Helfer (Keyboard-Wedge Reader)
 *
 * Konfiguration kommt über das Script-Tag:
 *   <script
 *     src="js/terminal-autologout.js"
 *     data-autologout-enabled="1"
 *     data-timeout-sekunden="60"
 *     data-logout-url="terminal.php?aktion=logout">
 *   </script>
 */

(() => {
    // Defensive: falls die View via Cache zurückkommt, zuerst alte Timer entfernen.
    const KEY = '__terminalAutoLogout';
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
            if (src.includes('terminal-autologout.js')) return s;
        }
        return null;
    };

    const scriptEl = findConfigScript();
    const ds = (scriptEl && scriptEl.dataset) ? scriptEl.dataset : {};

    const autologoutEnabled = String(ds.autologoutEnabled || '') === '1';

    let timeoutSek = 0;
    if (autologoutEnabled) {
        timeoutSek = Number(ds.timeoutSekunden || 60);
        if (!Number.isFinite(timeoutSek) || timeoutSek < 10) timeoutSek = 60;
    }

    const logoutUrl = autologoutEnabled
        ? ((ds.logoutUrl && String(ds.logoutUrl)) ? String(ds.logoutUrl) : 'terminal.php?aktion=logout')
        : '';

    // ------------------------------------------------------------
    // Live-Uhr (T-077) – laeuft auch auf dem Login-Screen
    // ------------------------------------------------------------

    const uhrEl = document.getElementById('terminal-uhr');

    const pad2 = (n) => String(n).padStart(2, '0');
    const formatZeit = (d) => {
        // Deutschland: HH:MM:SS DD-MM-YYYY
        return (
            pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds()) +
            ' ' +
            pad2(d.getDate()) + '-' + pad2(d.getMonth() + 1) + '-' + d.getFullYear()
        );
    };

    const updateUhr = () => {
        if (!uhrEl) return;
        uhrEl.textContent = formatZeit(new Date());
    };

    let clockTimer = null;
    let clockInterval = null;

    const startClock = () => {
        updateUhr();
        // Auf Sekundenkante ausrichten, damit die Anzeige sauber "tickt".
        const msToNextSecond = 1000 - (Date.now() % 1000);
        clockTimer = setTimeout(() => {
            updateUhr();
            clockInterval = setInterval(updateUhr, 1000);
        }, msToNextSecond);
    };

    // ------------------------------------------------------------
    // Auto-Logout (nur wenn enabled)
    // ------------------------------------------------------------

    let timer = null;
    let countdownInterval = null;
    let deadlineMs = Date.now() + (timeoutSek * 1000);

    const logoutForm = document.getElementById('logout-form');

    const ensureBadge = () => {
        if (!autologoutEnabled) return null;

        let badge = document.getElementById('terminal-countdown');
        if (badge) return badge;

        badge = document.createElement('div');
        badge.setAttribute('id', 'terminal-countdown');
        badge.setAttribute('aria-live', 'polite');
        // Styling kommt über `public/css/terminal.css` (siehe #terminal-countdown).
        badge.classList.add('terminal-countdown');

        document.body.appendChild(badge);
        return badge;
    };

    const badge = ensureBadge();

    const tick = () => {
        if (!autologoutEnabled || !badge) return;
        const rest = Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000));
        badge.textContent = 'Auto-Logout in ' + rest + 's';
    };

    const doLogout = () => {
        if (!autologoutEnabled) return;

        if (logoutForm) {
            logoutForm.submit();
            return;
        }
        // Fallback (sollte eigentlich nie passieren)
        if (logoutUrl) {
            window.location.href = logoutUrl;
        }
    };

    const schedule = () => {
        if (!autologoutEnabled) return;
        if (timer) clearTimeout(timer);
        timer = setTimeout(doLogout, timeoutSek * 1000);
    };

    const reset = () => {
        if (!autologoutEnabled) return;
        deadlineMs = Date.now() + (timeoutSek * 1000);
        tick();
        schedule();
    };

    // ------------------------------------------------------------
    // Scanner-/RFID-UX: Fokus-Helfer für Keyboard-Wedge-Reader
    // ------------------------------------------------------------
    // Viele Reader "tippen" die ID wie eine Tastatur. Wenn der Fokus
    // (z.B. durch einen Klick) auf einem Button landet, gehen Scans verloren.
    // Diese Logik hält den Fokus möglichst auf einem passenden Eingabefeld.

    const isFocusableInput = (el) => {
        if (!el) return false;
        const tag = (el.tagName || '').toUpperCase();
        if (tag !== 'INPUT' && tag !== 'TEXTAREA') return false;

        if (el.disabled || el.readOnly) return false;
        const type = (el.getAttribute('type') || '').toLowerCase();
        if (type === 'hidden') return false;

        // Sichtbarkeit (einfache Heuristik)
        const rect = el.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return false;

        return true;
    };

    const pickFocusTarget = () => {
        // 0) bevorzugte IDs (kontextabhängig: Login/Scan/Workflow)
        const preferredIds = ['rfid_code', 'auftragscode', 'neben_auftragscode', 'auftragszeit_id'];
        for (const id of preferredIds) {
            const el = document.getElementById(id);
            if (isFocusableInput(el)) return el;
        }

        // 1) explizit markiert
        const marked = document.querySelector('[data-terminal-scan="1"]');
        if (isFocusableInput(marked)) return marked;

        // 2) Autofocus (präferiert text)
        const autoText = document.querySelector('input[autofocus][type="text"]');
        if (isFocusableInput(autoText)) return autoText;

        const autoAny = document.querySelector('input[autofocus], textarea[autofocus]');
        if (isFocusableInput(autoAny)) return autoAny;

        // 3) Scan-Placeholder (falls vorhanden)
        const scanHint = document.querySelector('input[placeholder*="Scan"], input[placeholder*="scan"]');
        if (isFocusableInput(scanHint)) return scanHint;

        // 4) erster sichtbarer Text-Input
        const firstText = document.querySelector('input[type="text"]');
        if (isFocusableInput(firstText)) return firstText;

        // 5) Fallback: irgendein sichtbares Input/textarea
        const any = document.querySelector('input:not([type="hidden"]), textarea');
        if (isFocusableInput(any)) return any;

        return null;
    };

    const focusScanInput = () => {
        const active = document.activeElement;
        const activeTag = active ? (active.tagName || '').toUpperCase() : '';

        // Wenn der Fokus bereits auf einem Eingabefeld liegt, nichts anfassen.
        if (activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT') {
            return;
        }

        const target = pickFocusTarget();
        if (!target) return;

        try {
            target.focus({ preventScroll: true });
        } catch (e) {
            try { target.focus(); } catch (e2) { /* ignore */ }
        }

        if (typeof target.select === 'function') {
            try { target.select(); } catch (e) { /* ignore */ }
        }
    };

    // Pointer-Events: Timer resetten + danach Fokus zurück auf Scannerfeld.
    const istRfidZuweisungInteraktion = (ereignisZiel) => {
        if (!ereignisZiel || typeof ereignisZiel.closest !== 'function') {
            return false;
        }

        // Touch-Bedienung: Bei der RFID-Zuweisung darf ein offenes Select
        // nicht durch automatischen Fokuswechsel auf #rfid_code geschlossen werden.
        if (ereignisZiel.closest('#ziel_mitarbeiter_id')) {
            return true;
        }

        if (ereignisZiel.closest('form[action*="aktion=rfid_zuweisen"]')) {
            return true;
        }

        return false;
    };

    const onPointerEvent = (ereignis) => {
        reset();

        if (istRfidZuweisungInteraktion(ereignis ? ereignis.target : null)) {
            return;
        }

        setTimeout(focusScanInput, 0);
    };

    // Keydown: reset + falls kein Input fokussiert ist, sofort umschalten.
    const onKeydown = (e) => {
        reset();

        const active = document.activeElement;
        const activeTag = active ? (active.tagName || '').toUpperCase() : '';
        if (activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT') {
            return;
        }

        const target = pickFocusTarget();
        if (!target) return;

        try {
            target.focus({ preventScroll: true });
        } catch (err) {
            try { target.focus(); } catch (e2) { /* ignore */ }
        }

        // Best effort: erstes Zeichen eines Scans nicht verlieren.
        if (
            e &&
            typeof e.key === 'string' &&
            e.key.length === 1 &&
            !e.ctrlKey && !e.altKey && !e.metaKey &&
            (target.tagName || '').toUpperCase() !== 'SELECT'
        ) {
            try {
                target.value = String(target.value || '') + e.key;
                const len = String(target.value).length;
                if (typeof target.setSelectionRange === 'function') {
                    target.setSelectionRange(len, len);
                }
                e.preventDefault();
            } catch (err) {
                // ignore
            }
        }
    };

    const onWindowFocus = () => setTimeout(focusScanInput, 0);
    const onVisibility = () => {
        if (!document.hidden) {
            setTimeout(focusScanInput, 0);
        }
    };

    const initRfidZuweisungFokus = () => {
        const mitarbeiterAuswahl = document.getElementById('ziel_mitarbeiter_id');
        const rfidEingabe = document.getElementById('rfid_code');

        if (!(mitarbeiterAuswahl instanceof HTMLSelectElement)) return;
        if (!(rfidEingabe instanceof HTMLInputElement)) return;

        mitarbeiterAuswahl.addEventListener('change', () => {
            const wert = String(mitarbeiterAuswahl.value || '').trim();
            if (!wert) return;

            try {
                rfidEingabe.focus({ preventScroll: true });
            } catch (err) {
                try { rfidEingabe.focus(); } catch (e2) { /* ignore */ }
            }
        });
    };

    // ------------------------------------------------------------
    // Zentrale Confirm-Logik (T-052)
    // ------------------------------------------------------------
    // Ziel: Kein Inline-JS mehr in den Views. Destruktive Aktionen
    // werden per data-confirm="..." zentral abgefragt.
    // Unterstützt sowohl am <form> als auch am submit-Button.

    const onSubmitConfirm = (e) => {
        try {
            const form = e && e.target;
            if (!(form instanceof HTMLFormElement)) return;

            // Priorität: Button, der submitted (falls Browser submitter unterstützt)
            let msg = '';
            const submitter = e && e.submitter;
            if (submitter && submitter.dataset && typeof submitter.dataset.confirm === 'string') {
                msg = String(submitter.dataset.confirm || '');
            }

            // Fallback: Attribut am Form
            if (!msg && form.dataset && typeof form.dataset.confirm === 'string') {
                msg = String(form.dataset.confirm || '');
            }

            msg = String(msg || '').trim();
            if (!msg) return;

            // Native Confirm – im Terminal völlig ausreichend.
            if (!window.confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
                // UX: Fokus zurück auf Scan-Input, damit der Reader direkt wieder funktioniert.
                setTimeout(focusScanInput, 0);
            }
        } catch (err) {
            // Im Zweifel lieber nicht blockieren.
        }
    };


    // ------------------------------------------------------------
    // Debug Helper (T-069): Debug-Dump kopieren
    // ------------------------------------------------------------
    const debugCopyBtn = document.getElementById('terminal-debug-copy');
    const debugDumpEl = document.getElementById('terminal-debug-dump');
    const debugCopyStatus = document.getElementById('terminal-debug-copy-status');

    const setCopyStatus = (msg) => {
        if (!debugCopyStatus) return;
        debugCopyStatus.textContent = String(msg || '');
        if (msg) {
            setTimeout(() => {
                if (debugCopyStatus.textContent === String(msg)) {
                    debugCopyStatus.textContent = '';
                }
            }, 3500);
        }
    };

    const fallbackSelectForCopy = () => {
        if (!debugDumpEl) return false;
        try {
            const range = document.createRange();
            range.selectNodeContents(debugDumpEl);
            const sel = window.getSelection();
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            return true;
        } catch (e) {
            return false;
        }
    };

    const fallbackExecCommandCopy = (text) => {
        try {
            const ta = document.createElement('textarea');
            ta.value = String(text || '');
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-1000px';
            ta.style.left = '-1000px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            const ok = !!(document.execCommand && document.execCommand('copy'));
            document.body.removeChild(ta);
            return ok;
        } catch (e) {
            return false;
        }
    };

    if (debugCopyBtn && debugDumpEl) {
        debugCopyBtn.addEventListener('click', async () => {
            const text = String(debugDumpEl.innerText || '').trim();
            if (!text) {
                setCopyStatus('Kein Debug-Text gefunden.');
                return;
            }

            // 1) moderne Clipboard API (nur in Secure Context zuverlässig)
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    setCopyStatus('Kopiert.');
                    return;
                }
            } catch (e) {
                // ignore, fallback
            }

            // 2) Legacy-Fallback (execCommand)
            if (fallbackExecCommandCopy(text)) {
                setCopyStatus('Kopiert.');
                return;
            }

            // 3) Letzter Fallback: Text markieren
            if (fallbackSelectForCopy()) {
                setCopyStatus('Clipboard nicht verfügbar – Text ist markiert. Bitte STRG+C.');
            } else {
                setCopyStatus('Clipboard nicht verfügbar – bitte Debug-Text manuell kopieren.');
            }
        });
    }

    ['click', 'touchstart'].forEach((evt) => {
        document.addEventListener(evt, onPointerEvent, { passive: true });
    });
    document.addEventListener('keydown', onKeydown, true);
    document.addEventListener('submit', onSubmitConfirm, true);
    window.addEventListener('focus', onWindowFocus);
    document.addEventListener('visibilitychange', onVisibility);

    startClock();
    initRfidZuweisungFokus();

    if (autologoutEnabled) {
        countdownInterval = setInterval(tick, 1000);
        reset();
    }
    setTimeout(focusScanInput, 0);

    window[KEY] = {
        cleanup: () => {
            if (timer) clearTimeout(timer);
            if (countdownInterval) clearInterval(countdownInterval);
            if (clockTimer) clearTimeout(clockTimer);
            if (clockInterval) clearInterval(clockInterval);
            ['click', 'touchstart'].forEach((evt) => {
                document.removeEventListener(evt, onPointerEvent);
            });
            document.removeEventListener('keydown', onKeydown, true);
            document.removeEventListener('submit', onSubmitConfirm, true);
            window.removeEventListener('focus', onWindowFocus);
            document.removeEventListener('visibilitychange', onVisibility);
        }
    };
})();
