# Fachregeln

Die Fachlogik des Projekts, nach Thema getrennt. **Lies nur die Datei, die zu
deiner Aufgabe gehoert** – nicht alle.

| Datei | Thema |
| --- | --- |
| [zeit_rundung_pausen.md](zeit_rundung_pausen.md) | Kommen/Gehen, Rohdaten, Rundungsregeln, Arbeitsbloecke, Pausen, Tageswerte, Korrekturmaske, Audit-Trail |
| [urlaub_abwesenheit_feiertage.md](urlaub_abwesenheit_feiertage.md) | Urlaubsantrag, Saldo, Uebertrag, Genehmigung, Betriebsferien, Feiertage |
| [rollen_rechte_genehmiger.md](rollen_rechte_genehmiger.md) | Rollen, Rechte, Bereiche (Scope), Superuser, Genehmiger, `hatRecht()` |
| [terminal_und_offline.md](terminal_und_offline.md) | Terminal-UI, RFID, Auto-Logout, Offline-Queue, Stoerungsmodus, Kopplung |
| [auftraege_und_codes.md](auftraege_und_codes.md) | Auftraege, Auftragszeiten, Haupt-/Nebenauftrag, Strichcodes, Laufkarte |
| [auswertung_und_pdf.md](auswertung_und_pdf.md) | Monatsuebersicht, Monatsreport, PDF-Technik, Stundenkonto, Dashboard-Warnungen |
| [stammdaten_und_datenbank.md](stammdaten_und_datenbank.md) | Mitarbeiter, Abteilungen, Maschinen, Konfiguration, DB-Regeln |

**Source of Truth fuer die Datenbankstruktur** ist immer
`sql/01_initial_schema.sql`, nicht diese Dateien. Hier steht das **Warum**,
dort stehen die aktuellen Spalten.

**Source of Truth fuer Rechte-Codes** ist `docs/rechte_prompt.md`.

## Herkunft

Diese Dateien entstanden aus dem Master-Prompt v13
(`docs/archiv/master_prompt_zeiterfassung_v13.md`), der als ein einziges
Dokument zu gross geworden war. Jede Datei nennt oben, aus welchen Abschnitten
sie stammt. Inhaltlich wurde nichts weggelassen; ergaenzt wurden Regeln, die
sich aus der Fehlerhistorie ergeben hatten und bisher nur dort standen.

**Eine Regel gehoert an genau eine Stelle.** Steht dieselbe Aussage in zwei
Dateien, driftet sie frueher oder spaeter auseinander – dann weiss niemand
mehr, welche Fassung gilt. Verweise statt kopieren.
