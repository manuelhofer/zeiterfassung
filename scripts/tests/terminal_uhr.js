/* Browseruhr/Zeitzone simulieren; keine echten Timer oder Netzaufrufe. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const quelle = fs.readFileSync(path.join(__dirname, '../../public/js/terminal-autologout.js'), 'utf8');
let tests = 0;
function gut(name, ok) { assert.ok(ok, name); tests++; process.stdout.write(`PASS ${name}\n`); }

async function main() {
    let monoton = 0;
    let falsch = Date.UTC(2001, 0, 1);
    let antwort = { epoche: Date.UTC(2026, 0, 1, 10) / 1000, zeitzone: 'Europe/Berlin' };
    let netzfehler = false;
    let anzahl = 0;
    let nummer = 0;
    const intervalle = new Map();
    const fristen = new Map();
    const uhr = { textContent: '' };
    const badge = { textContent: '' };
    const ereignisse = new Map();
    class FalscheDate extends Date {
        constructor(...args) { super(...(args.length ? args : [falsch])); }
        static now() { return falsch; }
    }
    const dataset = { autologoutEnabled: '1', timeoutSekunden: '60',
        zeitEpoche: String(Date.UTC(2026, 6, 1, 10) / 1000), zeitzone: 'Europe/Berlin',
        zeitUrl: 'terminal.php?aktion=zeit' };
    const document = {
        currentScript: { dataset }, hidden: false, activeElement: null,
        getElementById: id => id === 'terminal-uhr' ? uhr : id === 'terminal-countdown' ? badge : null,
        querySelector: () => null,
        addEventListener: (name, fn) => ereignisse.set(name, fn), removeEventListener: () => {}
    };
    const window = { location: { href: '' }, addEventListener: () => {}, removeEventListener: () => {} };
    const sandbox = { document, window, navigator: {}, console, Date: FalscheDate, Intl,
        HTMLSelectElement: class {}, HTMLInputElement: class {}, AbortController,
        performance: { now: () => monoton },
        setTimeout: (fn, ms) => { const id = ++nummer; fristen.set(id, { fn, ms }); return id; },
        clearTimeout: id => fristen.delete(id),
        setInterval: (fn, ms) => { const id = ++nummer; intervalle.set(id, { fn, ms }); return id; },
        clearInterval: id => intervalle.delete(id),
        fetch: async (url, optionen) => {
            anzahl++;
            assert.equal(url, 'terminal.php?aktion=zeit');
            assert.equal(optionen.cache, 'no-store');
            if (netzfehler) throw new Error('offline fixture');
            return { ok: true, json: async () => antwort };
        }
    };
    vm.runInNewContext(quelle, sandbox);
    gut('Uhr startet mit Backendzeit statt falscher Browseruhr', uhr.textContent === '12:00:00 01-07-2026');
    [...fristen.values()].find(x => x.ms === 1000).fn();
    monoton = 5000;
    falsch += 172800000;
    for (const t of intervalle.values()) if (t.ms === 1000) t.fn();
    gut('Uhr läuft trotz lokalem Uhrsprung monoton weiter', uhr.textContent === '12:00:05 01-07-2026');
    gut('Uhrsprung verändert Auto-Logout-Frist nicht', badge.textContent === 'Auto-Logout in 55s');
    const abgleich = [...intervalle.values()].find(x => x.ms === 15000).fn;
    await abgleich();
    gut('Periodischer Abgleich übernimmt Winterzeit und Datum des Servers', uhr.textContent === '11:00:00 01-01-2026');
    const vorher = uhr.textContent;
    antwort = { epoche: 'nicht-eine-zeit', zeitzone: 'Europe/Berlin' };
    await abgleich();
    gut('Ungültige Zeitantwort ersetzt keinen gültigen Stand', uhr.textContent === vorher);
    antwort = { epoche: Date.UTC(2026, 5, 1) / 1000, zeitzone: 'ungueltige-zeitzone' };
    await abgleich();
    gut('Ungültige Zeitzone stoppt die Uhr nicht', uhr.textContent === vorher);
    netzfehler = true;
    await abgleich();
    monoton += 10000;
    for (const t of intervalle.values()) if (t.ms === 1000) t.fn();
    gut('Bei Netzausfall läuft letzter Serverstand weiter', uhr.textContent === '11:00:10 01-01-2026');
    netzfehler = false;
    antwort = { epoche: Date.UTC(2026, 9, 9, 8) / 1000, zeitzone: 'Europe/Berlin' };
    ereignisse.get('visibilitychange')();
    await new Promise(resolve => setImmediate(resolve));
    gut('Nach Sichtbarwerden wird die Uhr erneut abgeglichen', uhr.textContent === '10:00:00 09-10-2026');
    const gelesen = anzahl;
    window.__terminalAutoLogout.cleanup();
    await abgleich();
    gut('Cleanup entfernt Intervalle und verhindert weitere Uhrabfragen', intervalle.size === 0 && anzahl === gelesen);
    process.stdout.write(`Ergebnis: ${tests} Browser-Uhr-Prüfungen erfolgreich.\n`);
}
main().catch(e => { console.error(e); process.exitCode = 1; });
