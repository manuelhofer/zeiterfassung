ROADMAP-PROMPT (zum 1:1 Kopieren)



Titel: Terminal: Klick auf Mitarbeitername → Stunden-Übersicht (Monat / bis heute)



Input liegt bei:



2342354\_gesammt.zip (Projekt)



master\_prompt\_zeiterfassung\_v10.md



DEV\_PROMPT\_HISTORY.md



rechte\_prompt.md



zeiterfassung\_aktuell.sql



Ziel



Im Terminal unten auf „Mitarbeiter: <Name> (ID: x)” klicken → Popup/Overlay zeigt:



Arbeitsstunden in diesem Monat (Soll-Monat)



Arbeitsstunden in diesem Monat bis heute (Soll-bis-heute)



geleistete Arbeitsstunden bis heute (Ist-bis-heute)



Minimal, schnell, kiosk-tauglich.



Harte Regeln (wie in euren Prompts)



Micro-Patches nach Master/Dev-Regeln.



Pro Patch max. so klein wie gefordert (typisch: 1–3 Dateien).



Ausgabe pro Patch: nur ZIP (plus ultrakurzer 1-Zeiler “Next step” wenn erlaubt).



Nach jedem Patch stoppen und auf User-“weiter” warten.



SQL nur, wenn wirklich nötig (Index/Views), als eigener Micro-Patch.



Patch-Plan (Micro-Patches)

PATCH 01 — API-Endpoint: Ist-Stunden bis heute (Monat)



Zweck: Backend liefert ist\_bis\_heute für eingeloggten Mitarbeiter (Session).



Umsetzung:



Neuen API-Endpoint anlegen (z. B. public/api/terminal\_stats.php oder bestehendes API-Schema nutzen).



Auth wie im Terminal/anderen APIs: nur eingeloggter User, keine Fremd-ID Parameter.



Berechnung ist\_bis\_heute:



Zeitraum: 1. des Monats 00:00 → jetzt.



Zeiten aus Stempelungen/Zeiterfassung summieren.



Wenn heute ein offener “Kommen ohne Gehen” existiert: bis jetzt mitzählen.



Wenn Daten fehlen → 0.0 statt Error.



Output JSON (vorerst minimal):



{ "ok": true, "ist\_bis\_heute\_stunden": 12.5 }





Akzeptanztest:



Terminal-Login vorhanden → API liefert OK.



Neuer Mitarbeiter ohne Zeiten → 0.



ZIP ausgeben, warten auf “weiter”.



PATCH 02 — Soll-Stunden (Monat / bis heute) im API ergänzen



Zweck: API liefert zusätzlich soll\_monat und soll\_bis\_heute.



Umsetzung:



In dem selben Endpoint (oder Helper, je nach Master-Regeln) Soll-Stunden berechnen:



Basis: vorhandenes Arbeitszeitmodell / Wochenstunden / Tagesstunden aus DB (nehmen was existiert).



Fallback (nur wenn nix vorhanden): konfigurierte Default-Tagesstunden.



Arbeitstage: Mo–Fr (Wochenenden raus).



soll\_monat: alle Arbeitstage im Monat.



soll\_bis\_heute: Arbeitstage vom Monatsanfang bis inkl. heute.



JSON danach:



{

&nbsp; "ok": true,

&nbsp; "soll\_monat\_stunden": 168,

&nbsp; "soll\_bis\_heute\_stunden": 72,

&nbsp; "ist\_bis\_heute\_stunden": 64.5

}





Akzeptanztest:



API liefert alle 3 Werte.



Keine Fatal-Errors bei leeren Stammdaten.



ZIP ausgeben, warten auf “weiter”.



PATCH 03 — Soll-Berechnung korrekt machen: Feiertage/Betriebsferien/Urlaub berücksichtigen



Zweck: Soll wird “realistisch” (d. h. freie Tage reduzieren Soll).



Umsetzung (in dieser Reihenfolge, damit’s klein bleibt):



Betriebsferien/Feiertage (was im Projekt existiert) → Arbeitstage rausrechnen.



Genehmigter Urlaub des Mitarbeiters → Arbeitstage rausrechnen.



Optional: halbe Tage, falls System das kennt.



Wichtig: Wenn im Projekt bereits eine Funktion existiert (Monatsreport/Urlaubssaldo), die wiederverwenden, nicht doppelt implementieren.



Akzeptanztest:



Monat mit eingetragenen Betriebsferien: soll\_monat sinkt.



Genehmigter Urlaub in diesem Monat: soll\_\* sinkt entsprechend.



ZIP ausgeben, warten auf “weiter”.



PATCH 04 — Terminal UI: Name klickbar + Modal/Overlay + API Anzeige



Zweck: Klick auf Mitarbeiterzeile öffnet Overlay mit den 3 Werten.



Umsetzung:



In der Terminal-Seite (die das Screenshot-Layout rendert) den Bereich

Mitarbeiter: <Name> (ID: x) klickbar machen:



CSS: cursor:pointer



onClick: fetch('/api/terminal\_stats.php')



Overlay/Modal:



Headline: „Arbeitszeit-Übersicht“



3 Zeilen (genau wie gewünscht)



Button „Schließen“ + ESC/Click outside schließen (wenn klein möglich)



Offline/Fehlerfall: „Nicht verfügbar (offline)“ statt Crash.



Darstellung: Stunden als hh:mm oder xx,xx h (projektweit konsistent).



Akzeptanztest:



Klick → Overlay erscheint innerhalb ~1s.



Werte werden angezeigt.



Abbrechen/Schließen funktioniert.



Terminal-Flow (Kommen/Urlaub/etc.) bleibt unverändert.



ZIP ausgeben, warten auf “weiter”.



PATCH 05 — Feinschliff: Format + Mini-Check “hinterher/vorne”



Nur wenn du es willst, sonst weglassen.

Zweck: Auf einen Blick sehen ob der Monat “passt”.



Umsetzung:



Zusatzzeile (optional): Differenz bis heute = ist\_bis\_heute - soll\_bis\_heute



Farbe: grün wenn ≥0, rot wenn <0 (nur wenn im Master erlaubt).



Keine neuen Rechte.



ZIP ausgeben, warten auf “weiter”.



PATCH 06 — Doku/History sauberziehen



Zweck: Alles dokumentiert, Prompt danach löschbar.



Umsetzung:



DEV\_PROMPT\_HISTORY.md: Eintrag(e) je Patch oder zusammenfassend (wie eure Regel sagt).



Master/Terminal-Doku: kurzer Hinweis “Klick auf Mitarbeitername → Stundenübersicht”.



Keine Codeänderungen mehr, nur Doku.



ZIP ausgeben, fertig.



Definitionen (fix, damit’s nicht ausufert)



“bis heute” = vom 1. des Monats bis jetzt (Ist) / bis inkl. heute (Soll).



“Arbeitsstunden” in dieser Ansicht = Soll-Stunden (nicht “Ist”).



API zeigt nur eigenen User (Session), kein Parameter.



Wenn du willst, kann ich den Prompt noch kürzer machen (wirklich nur Patch-Titel + Acceptance), aber so ist er “abarbeitbar”, ohne dass man wieder diskutieren muss, was “Soll” bedeutet.

