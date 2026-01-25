/*
 * Terminal Health Polling
 * - pollt terminal.php?aktion=health
 * - aktualisiert Topbar-Pill (OFFLINE/STÖRUNG) + Systemstatus-Box ohne Reload
 */

(function () {
  "use strict";

  // Script-Tag mit data-Attributen finden
  var currentScript = document.currentScript;
  if (!currentScript) {
    var scripts = document.getElementsByTagName("script");
    currentScript = scripts.length ? scripts[scripts.length - 1] : null;
  }

  var healthUrl = currentScript && currentScript.dataset ? currentScript.dataset.healthUrl : "";
  if (!healthUrl) {
    healthUrl = "terminal.php?aktion=health";
  }

  var intervalSek = 10;
  if (currentScript && currentScript.dataset && currentScript.dataset.intervalSekunden) {
    var n = parseInt(currentScript.dataset.intervalSekunden, 10);
    if (!isNaN(n)) intervalSek = n;
  }

  // 0 oder kleiner = Polling aus
  if (intervalSek <= 0) return;

  // Defensive bounds
  if (intervalSek < 2) intervalSek = 2;
  if (intervalSek > 300) intervalSek = 300;

  function fmtDb(v) {
    if (v === true) return "OK";
    if (v === false) return "offline";
    return "unbekannt";
  }

  function fmtQueue(v) {
    if (v === true) return "verfügbar";
    if (v === false) return "nicht verfügbar";
    return "unbekannt";
  }

  function decideState(h) {
    var haupt = h && Object.prototype.hasOwnProperty.call(h, "hauptdb_verfuegbar") ? h.hauptdb_verfuegbar : null;
    var queue = h && Object.prototype.hasOwnProperty.call(h, "queue_verfuegbar") ? h.queue_verfuegbar : null;

    if (haupt === false && queue === true) {
      return { pillText: "OFFLINE", pillClass: "warn", title: "Systemstatus: Offline-Modus" };
    }
    if (haupt === false && queue === false) {
      return { pillText: "STÖRUNG", pillClass: "error", title: "Systemstatus: Störung" };
    }
    if (haupt !== true) {
      return { pillText: null, pillClass: "warn", title: "Systemstatus: Unklar" };
    }
    return { pillText: null, pillClass: "ok", title: "Systemstatus: Online" };
  }

  function updateTopbarPill(state) {
    var topbar = document.querySelector(".terminal-topbar");
    if (!topbar) return;

    var pill = topbar.querySelector(".terminal-pill");

    if (!state.pillText) {
      if (pill) {
        pill.parentNode.removeChild(pill);
      }
      return;
    }

    if (!pill) {
      pill = document.createElement("div");
      pill.className = "terminal-pill";
      // vor der Uhr einfügen
      var clock = topbar.querySelector("#terminal-uhr");
      if (clock && clock.parentNode === topbar) {
        topbar.insertBefore(pill, clock);
      } else {
        topbar.insertBefore(pill, topbar.firstChild);
      }
    }

    pill.textContent = state.pillText;
    // Klassen setzen
    pill.classList.remove("ok", "warn", "error");
    if (state.pillClass) pill.classList.add(state.pillClass);
  }

  function updateSystemstatusBox(h, state) {
    var box = document.querySelector("details.terminal-systemstatus");
    if (!box) return;

    // Klassen
    box.classList.remove("ok", "warn", "error");
    if (state.pillClass) {
      box.classList.add(state.pillClass);
    }

    // Titel im Summary
    var titleSpan = box.querySelector("summary.status-title span");
    if (titleSpan) {
      titleSpan.textContent = state.title || "Systemstatus";
    }

    // Meta
    var meta = box.querySelector(".terminal-systemstatus-meta");
    if (meta) {
      var teile = [];
      teile.push("Hauptdatenbank: " + fmtDb(h.hauptdb_verfuegbar));
      teile.push("Queue: " + fmtQueue(h.queue_verfuegbar));
      if (typeof h.queue_offen === "number") teile.push("Offen: " + String(h.queue_offen));
      if (typeof h.queue_fehler === "number") teile.push("Fehler: " + String(h.queue_fehler));
      meta.textContent = teile.join(" · ");
    }

    // Erste Zeile im Content (best effort)
    var firstLine = box.querySelector(":scope > .status-small");
    if (firstLine) {
      var qTxt = fmtQueue(h.queue_verfuegbar);
      if (h.queue_verfuegbar === true && h.queue_speicherort) {
        qTxt += " (" + String(h.queue_speicherort) + ")";
      }
      var line = "Hauptdatenbank: " + fmtDb(h.hauptdb_verfuegbar) + " · Queue: " + qTxt;
      if (typeof h.queue_offen === "number") line += " · Offen: " + String(h.queue_offen);
      if (typeof h.queue_fehler === "number") line += " · Fehler: " + String(h.queue_fehler);
      firstLine.textContent = line;
    }
  }

  var inFlight = false;

  async function pollOnce() {
    if (inFlight) return;
    inFlight = true;

    try {
      var res = await fetch(healthUrl, {
        method: "GET",
        cache: "no-store",
        headers: { "Accept": "application/json" }
      });

      // Auch bei 503 noch JSON lesen, falls vorhanden
      var data = null;
      try {
        data = await res.json();
      } catch (e) {
        data = null;
      }

      if (!data || typeof data !== "object") {
        return;
      }

      var state = decideState(data);
      updateTopbarPill(state);
      updateSystemstatusBox(data, state);
    } catch (e) {
      // Keine harten Fehler – Kiosk darf nicht crashen.
      // Bei Netzwerkfehlern zeigen wir: STÖRUNG (best effort)
      var state = { pillText: "STÖRUNG", pillClass: "error", title: "Systemstatus: Störung" };
      updateTopbarPill(state);
    } finally {
      inFlight = false;
    }
  }

  // sofort + Intervall
  pollOnce();
  window.setInterval(pollOnce, intervalSek * 1000);
})();
