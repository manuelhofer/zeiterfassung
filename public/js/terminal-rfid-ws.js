/*
 * Terminal RFID WebSocket Bridge
 *
 * Zweck:
 * - Ein lokaler WebSocket (z. B. ws://127.0.0.1:8765) liefert UIDs/Strings.
 * - Dieses Script setzt die UID in relevante Eingabefelder (RFID Login, RFID-Zuweisung)
 *   und kann – je nach Kontext – automatisch submitten.
 *
 * WICHTIG:
 * - Dieses Script ist bewusst "best effort" und darf nie das Terminal blockieren.
 * - Keyboard-Wedge-Reader bleiben kompatibel (dieses Script ergänzt nur).
 */

(function () {
  "use strict";

  const scriptEl = document.currentScript;
  const wsUrlAttr = scriptEl ? (scriptEl.getAttribute("data-rfid-ws-url") || "") : "";
  const WS_URL = wsUrlAttr.trim();

  if (!WS_URL) {
    return;
  }

  let ws = null;
  let reconnectTimer = null;
  let reconnectAttempt = 0;
  const reconnectDelays = [1000, 2000, 5000, 10000];
  let connecting = false;

  const statusEl = document.getElementById("terminal-debug-ws-status");
  let lastStatus = "";

  // Für "Doppel-Scans" gibt es serverseitig bereits De-Bounce.
  // Clientseitig verhindern wir zusätzlich, dass wir dieselbe UID direkt mehrfach in Folge verarbeiten.
  let lastUid = "";
  let lastUidAt = 0;

  // Guard gegen zu viele DOM-Updates (z. B. wenn Error/Close sehr schnell feuert)
  let lastStatusAt = 0;

  function pad2(n) {
    const s = String(n || 0);
    return s.length >= 2 ? s : "0" + s;
  }

  function fmtDt(ts) {
    const d = ts ? new Date(ts) : new Date();
    const hh = pad2(d.getHours());
    const mm = pad2(d.getMinutes());
    const ss = pad2(d.getSeconds());
    const dd = pad2(d.getDate());
    const mo = pad2(d.getMonth() + 1);
    const yy = d.getFullYear();
    return `${hh}:${mm}:${ss} ${dd}-${mo}-${yy}`;
  }

  function setStatus(text) {
    if (!statusEl) return;
    if (text === lastStatus) return;

    // UID-Status darf immer sofort aktualisieren, alles andere wird leicht gedrosselt.
    const isUidLine = text.indexOf("UID empfangen") !== -1;
    const now = Date.now();
    if (!isUidLine && (now - lastStatusAt) < 150) {
      return;
    }
    lastStatusAt = now;

    lastStatus = text;
    statusEl.textContent = text;
  }

  function log(...args) {
    // Konsole nur nutzen, wenn vorhanden. In Kiosk-Browsern ist das nicht immer zugänglich.
    try {
      // eslint-disable-next-line no-console
      console.log(...args);
    } catch (e) {
      /* ignore */
    }
  }

  function isVisible(el) {
    if (!el) return false;
    const style = window.getComputedStyle(el);
    if (style.display === "none" || style.visibility === "hidden" || style.opacity === "0") return false;
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function normalizeUid(raw) {
    const uid = (raw || "").toString().trim();
    if (!uid) return "";
    if (uid === "CONNECTED") return "";
    return uid;
  }

  function findRfidInput() {
    // Priorität 1: Fokus-Element, wenn es plausibel ist.
    const active = document.activeElement;
    if (active && active.tagName === "INPUT") {
      const type = (active.getAttribute("type") || "text").toLowerCase();
      if ((type === "text" || type === "password" || type === "search" || type === "tel" || type === "number") && isVisible(active)) {
        const id = (active.getAttribute("id") || "").toLowerCase();
        const name = (active.getAttribute("name") || "").toLowerCase();
        if (id === "rfid_code" || name === "rfid_code" || active.hasAttribute("data-accept-rfid")) {
          return active;
        }
      }
    }

    // Priorität 2: explizit RFID-Feld
    const rfid = document.querySelector("input#rfid_code, input[name='rfid_code']");
    if (rfid && isVisible(rfid)) {
      return rfid;
    }

    return null;
  }

  function tryAutoSubmit(inputEl) {
    if (!inputEl) return;
    const form = inputEl.closest("form");
    if (!form) return;

    const action = (form.getAttribute("action") || "").toString();

    // Login/Offline-RFID-Eingabe: direkt submitten
    if (action.includes("aktion=start")) {
      try {
        form.requestSubmit ? form.requestSubmit() : form.submit();
      } catch (e) {
        try { form.submit(); } catch (e2) { /* ignore */ }
      }
      return;
    }

    // RFID-Zuweisen: nur auto-submitten, wenn ein Mitarbeiter ausgewählt ist.
    if (action.includes("aktion=rfid_zuweisen")) {
      const sel = form.querySelector("select#ziel_mitarbeiter_id");
      const selVal = sel ? (sel.value || "") : "";
      if (selVal.toString().trim() !== "") {
        // Mini-Delay, damit UI die Eingabe sichtbar aktualisieren kann.
        setTimeout(() => {
          try {
            form.requestSubmit ? form.requestSubmit() : form.submit();
          } catch (e) {
            try { form.submit(); } catch (e2) { /* ignore */ }
          }
        }, 50);
      }
    }
  }

  function handleUid(uid) {
    const now = Date.now();

    // Simple client-side duplicate guard
    if (uid === lastUid && (now - lastUidAt) < 300) {
      return;
    }
    lastUid = uid;
    lastUidAt = now;

    const input = findRfidInput();
    if (!input) {
      return;
    }

    try {
      input.focus({ preventScroll: true });
    } catch (e) {
      try { input.focus(); } catch (e2) { /* ignore */ }
    }

    input.value = uid;

    // Events, damit ggf. UI/Validatoren reagieren.
    try {
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
    } catch (e) {
      /* ignore */
    }

    tryAutoSubmit(input);
  }

  function cleanupTimers() {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  }

  function scheduleReconnect(reasonText) {
    cleanupTimers();

    // Backoff: 1s → 2s → 5s → 10s (gedeckelt)
    const idx = Math.min(reconnectAttempt, reconnectDelays.length - 1);
    const delay = reconnectDelays[idx];
    const attemptHuman = reconnectAttempt + 1;
    reconnectAttempt++;

    const reason = reasonText ? ` (${reasonText})` : "";
    setStatus(`RFID WS: Reconnect in ${Math.round(delay / 1000)}s – Versuch ${attemptHuman}${reason} (${WS_URL})`);

    reconnectTimer = setTimeout(() => {
      connect();
    }, delay);
  }

  function connect() {
    cleanupTimers();

    if (connecting) {
      return;
    }
    connecting = true;

    setStatus(`RFID WS: verbinde… (${WS_URL})`);

    try {
      ws = new WebSocket(WS_URL);
    } catch (e) {
      ws = null;
      connecting = false;
      scheduleReconnect("WebSocket ctor");
      return;
    }

    ws.onopen = () => {
      log("RFID WS connected");
      reconnectAttempt = 0;
      connecting = false;
      setStatus(`RFID WS: verbunden (${WS_URL})`);
    };

    ws.onclose = () => {
      log("RFID WS closed, retrying...");
      ws = null;
      connecting = false;
      scheduleReconnect("close");
    };

    ws.onerror = (e) => {
      log("RFID WS error", e);
      // onclose feuert meist danach. Falls nicht (Browser-/Netz-Kante),
      // sorgen wir defensiv für genau einen Reconnect.
      setStatus(`RFID WS: Fehler (${WS_URL})`);
      if (!reconnectTimer) {
        reconnectTimer = setTimeout(() => {
          reconnectTimer = null;
          if (!ws || ws.readyState === WebSocket.CLOSED) {
            scheduleReconnect("error");
          }
        }, 600);
      }
    };

    ws.onmessage = (ev) => {
      const uid = normalizeUid(ev && ev.data ? ev.data : "");
      if (!uid) return;
      setStatus(`RFID WS: UID empfangen ${uid} (${fmtDt(Date.now())})`);
      handleUid(uid);
    };
  }

  // Reconnect nur, wenn das Tab sichtbar ist (Kiosk meistens immer sichtbar).
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      if (!ws || ws.readyState === WebSocket.CLOSED) {
        connect();
      }
    }
  });

  connect();
})();
