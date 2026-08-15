# Fachregeln

Die Fachlogik des Projekts, nach Thema getrennt. **Lies nur die Datei, die zu
deiner Aufgabe gehört** – nicht alle.

| Datei | Thema |
| --- | --- |
| [zeit_rundung_pausen.md](zeit_rundung_pausen.md) | Kommen/Gehen, Rohdaten, Rundungsregeln, Arbeitsblöcke, Pausen, Tageswerte, Korrekturmaske, Audit-Trail |
| [urlaub_abwesenheit_feiertage.md](urlaub_abwesenheit_feiertage.md) | Urlaubsantrag, Saldo, Übertrag, Genehmigung, Betriebsferien, Feiertage |
| [rollen_rechte_genehmiger.md](rollen_rechte_genehmiger.md) | Rollen, Rechte, Bereiche (Scope), Superuser, Genehmiger, `hatRecht()` |
| [terminal_und_offline.md](terminal_und_offline.md) | Terminal-UI, RFID, Auto-Logout, Offline-Queue, Störungsmodus, Kopplung |
| [aufträge_und_codes.md](auftraege_und_codes.md) | Aufträge, Auftragszeiten, Haupt-/Nebenauftrag, Strichcodes, Laufkarte |
| [auswertung_und_pdf.md](auswertung_und_pdf.md) | Monatsübersicht, Monatsreport, PDF-Technik, Stundenkonto, Dashboard-Warnungen |
| [stammdaten_und_datenbank.md](stammdaten_und_datenbank.md) | Mitarbeiter, Abteilungen, Maschinen, Konfiguration, DB-Regeln |

**Source of Truth für die Datenbankstruktur** ist immer
`sql/01_initial_schema.sql`, nicht diese Dateien. Hier steht das **Warum**,
dort stehen die aktuellen Spalten.

**Source of Truth für Rechte-Codes** ist `docs/rechte_prompt.md`.

## Herkunft

Diese Dateien entstanden aus dem Master-Prompt v13
(`docs/archiv/master_prompt_zeiterfassung_v13.md`), der als ein einziges
Dokument zu groß geworden war. Jede Datei nennt oben, aus welchen Abschnitten
sie stammt. Inhaltlich wurde nichts weggelassen; ergänzt wurden Regeln, die
sich aus der Fehlerhistorie ergeben hatten und bisher nur dort standen.

**Eine Regel gehört an genau eine Stelle.** Steht dieselbe Aussage in zwei
Dateien, driftet sie früher oder später auseinander – dann weiß niemand
mehr, welche Fassung gilt. Verweise statt kopieren.
