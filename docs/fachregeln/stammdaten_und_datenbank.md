# Fachregeln: Stammdaten, Abteilungen, Maschinen, Datenbank-Design

*Source of Truth für die Struktur:* `sql/01_initial_schema.sql`. Dieses
Dokument beschreibt nur, **warum** etwas so ist – die aktuellen Spalten stehen
im Schema und müssen hier nicht doppelt gepflegt werden.
*Herkunft:* Master-Prompt v13, Abschnitte 4, 5, 6.1 und 17.

---

## 1. Mitarbeiter

Mindestens gespeichert werden: Vorname, Nachname, Geburtsdatum,
Wochenarbeitszeit (Dezimalzahl, z. B. 35,00), Urlaubsanspruch pro Monat
(Dezimalzahl, z. B. 2,08 Tage/Monat), Login-Daten (Benutzername oder E-Mail
optional, Passwort als **Hash**), RFID-Chip-Code (eindeutiger String),
Aktiv-Status, Rollen, Abteilungszuordnungen, Timestamps.

Dazu gepflegt: `personalnummer` (wird in Übersichten statt der Datenbank-ID
angezeigt; Rückfall auf die ID, wenn sie fehlt) und `eintrittsdatum` (Basis
für den anteiligen Urlaubsanspruch im Eintrittsjahr).

**Ausgeschiedene Mitarbeiter bleiben erreichbar** über
`?seite=mitarbeiter_admin&status=inaktiv` – ihre Daten werden für Auswertungen
weiter gebraucht.

Die Bearbeitung ist auf mehrere Seiten verteilt, damit das Stammdatenformular
bedienbar bleibt:

- `?seite=mitarbeiter_admin` – Stammdaten
- `?seite=mitarbeiter_rechte` – Rollen, Rechte-Overrides, Genehmiger
- `?seite=mitarbeiter_stundenkonto` – Stundenkonto mit Mitarbeiter-Auswahl

**Wichtig:** Das Speichern der Stammdaten lässt bestehende Rechte-Zuordnungen
unangetastet.

## 2. Abteilungen und Mehrfachzugehörigkeit

Tabelle `abteilung`: `name`, optional `beschreibung`, `parent_id` (Hierarchie,
z. B. „Management" → „Oberes Management" → „Arbeitsvorbereitung"), `aktiv`,
Timestamps.

Mitarbeiter können in **mehreren** Abteilungen geführt werden
(`mitarbeiter_hat_abteilung` mit `ist_stammabteilung` für Berichte). Wer
überwiegend in der Fraeserei arbeitet, aber auch drehen kann, gehört zu
beiden.

**Beim Stempeln oder Auftragsstart wird kein harter Abteilungsabgleich
erzwungen** – der Mitarbeiter darf dort stempeln, wo er faktisch arbeitet.

Abteilungen dienen zur Filterung in Auswertungen, zur Zuordnung von Maschinen
und als organisatorische Struktur – und als Bereich (Scope) für Rechte, siehe
[rollen_rechte_genehmiger.md](rollen_rechte_genehmiger.md).

## 3. Maschinen

Tabelle `maschine`: `name`, optional `abteilung_id`, optional `beschreibung`,
`aktiv`, Timestamps. Später denkbar: Stundensatz, Maschinentyp, Seriennummer,
Standort.

Je Maschine wird ein Barcode erzeugt (Code 128, wie bei Auftraegen – siehe
[auftraege_und_codes.md](auftraege_und_codes.md)).

## 4. Konfiguration

**In PHP** liegt nur **eine** zentrale Config-Datei (`config/config.php`, echte
Werte in `config/config.local.php`) mit:

- Verbindungsdaten der Hauptdatenbank,
- optional Verbindungsdaten der lokalen Offline-Datenbank,
- Basis-Einstellungen wie `base_url`,
- **Kennzeichnung, ob die Installation als `terminal` oder als `backend`
  läuft** (`app.installation_typ`),
- optional dem Eingabemodus für Reader/Scanner (USB-Keyboard-Wedge oder lokale
  Bridge, z. B. RC522).

**Alles Anwendungsbezogene** gehört in die Tabelle `config` (Key/Value):
Standard-Wochenstunden, Anzeigeoptionen, Rundungs- und Korrekturregeln,
Verhalten bei Feiertagen und Betriebsferien, Timeouts. Der `DefaultsSeeder`
legt fehlende Schluessel idempotent an – er überschreibt nie vorhandene Werte.

## 5. Allgemeine Datenbank-Regeln

- Engine **InnoDB**, Zeichensatz **utf8mb4**.
- Primaerschluessel `id INT UNSIGNED AUTO_INCREMENT`.
- Fremdschluessel: `ON UPDATE CASCADE`; `ON DELETE` je nach Sinn (`SET NULL`,
  `RESTRICT`, in Ausnahmefaellen `CASCADE`).
- **Aktiv-Flags statt hartem Löschen**, wo sinnvoll.
- Zugriff **immer** über PDO mit **Prepared Statements**.

**Strukturaenderungen** gehören als Migrationsdatei nach `sql/` **und** müssen
in `sql/01_initial_schema.sql` nachgezogen werden, damit Neuinstallationen
konsistent bleiben. Migrationen müssen **idempotent** sein – sie laufen in der
Praxis mehr als einmal.

## 6. Backend-Funktionen im Überblick

Nur zur Orientierung; die tatsaechlichen Routen stehen in `public/index.php`.

1. **Login/Logout** mit rollenbasiertem Menue. Existiert noch kein
   login-berechtigter Mitarbeiter, erscheint statt des Logins die
   **Erstinstallation** (erster Admin-Benutzer).
2. **Stammdaten:** Mitarbeiter, Rollen, Abteilungen, Maschinen, Terminals,
   Genehmiger, Konfiguration und Rundungsregeln.
3. **Urlaubsverwaltung:** Antragslisten, Detailansicht, Genehmigung, Filter,
   Jahresübersicht (`?seite=urlaub_jahresuebersicht`) mit U/O/BF/FT-Zellen.
4. **Zeit und Auftraege:** Tages- und Monatsdaten, Korrekturmasken,
   Auswertungen nach Abteilung, Auftrag und Maschine.
5. **PDF und Exporte:** Einzel-PDF, Sammel-Export als ZIP, CSV/Excel.
6. **Offline-Queue-Verwaltung:** Liste aller Injektionseintraege, fehlerhafte
   Eintraege inklusive SQL-Befehl einsehen, „Ignorieren/Löschen", optional
   „Erneut versuchen", Logging von Störungen.

Die Top-Navigation ist in Aufklappgruppen geordnet: `Urlaub`, `Uebersichten`,
`Mitarbeiter`, `Rechte`, `Verwaltung`, `Auftraege`.

## 7. Erweiterbarkeit

Das System soll so gebaut sein, dass neue Terminals einfach ergaenzt werden
können, zusätzliche Rollen und Rechte möglich sind, kuenftige
ERP-Schnittstellen für Auftraege einfach anzubinden sind und neue Auswertungen
ohne grosse Umbauten entstehen.
