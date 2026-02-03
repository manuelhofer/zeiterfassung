# Inhalt für die Git-Wiki

Dieses Dokument ist **der eigentliche Wiki-Inhalt**, den du in die GitHub-Wiki
kopieren kannst. Du wolltest keine Anleitung zur Aktivierung, sondern den
fertigen Text für deine Wiki-Seiten.

## Seite: Home

# Zeiterfassung – Wiki

Willkommen im Projekt-Wiki. Hier findest du die wichtigsten Einstell- und
Admin-Themen zur Zeiterfassung inklusive RFID-Anbindung.

**Schnellzugriff**
- **Administration**: Einstellungen, Rollen, Rechte, Konfiguration
- **RFID-Reader**: Beide Varianten (Tastatur-Scanner & WebSocket-Bridge)
- **FAQ**: Häufige Probleme und Lösungen

**Repo-Teaser (Kurzbeschreibung)**
> „Zeiterfassung und Mitarbeiter-/Auftragsmanagement mit RFID-Integration“

**Empfohlene Topics**
`zeiterfassung`, `rfid`, `php`, `zeitmanagement`, `mitarbeiterverwaltung`

---

## Seite: Administration

# Administration

Diese Seite beschreibt, wo Einstellungen vorgenommen werden und welche
Bereiche wofür zuständig sind.

## Konfigurationsorte

### `config/`
- **`config/config.php`**: Zentrale Konfiguration mit Defaults.
- **`config/config.local.php`** (nicht versioniert): Produktive/ lokale Werte.
- **`config/config.php.example`**: Vorlage für eigene Konfigurationen.

### `services/`
- Fachlogik (z. B. Zeitregeln, Rundung, Urlaub).
- Typische Anlaufpunkte: `ZeitService`, `UrlaubService`, `RundungsService`.

### `controller/`
- Request-Verarbeitung, Übergabe an Views.
- Geeignet für Anpassungen an Abläufen.

### `modelle/`
- Datenbank-Modelle (Mitarbeiter, Zeitbuchungen, Urlaubsanträge).

### `sql/`
- Datenbankschemata/Migrationen.
- Referenz: `sql/01_initial_schema.sql`.

## Typische Einstellungen (Ablauf)

### 1) Benutzer anlegen
1. Admin-Oberfläche öffnen.
2. Mitarbeiter anlegen (Name, Personalnummer, Status).
3. Rollen zuweisen (siehe unten).
4. Speichern und in der Übersicht prüfen.

### 2) Rollen & Rechte
1. Rollenverwaltung öffnen.
2. Rolle erstellen (z. B. „Teamleitung“).
3. Rechte aktivieren (z. B. Freigaben, Auswertungen).
4. Rolle zuweisen.

### 3) Zeitregeln & Rundungen
1. Regeln in `services/` prüfen.
2. Anpassungen in den jeweiligen Services vornehmen.
3. Mit Testbuchungen prüfen.

### 4) Datenbank/SQL
1. Schema aus `sql/01_initial_schema.sql` importieren.
2. Zugangsdaten in `config/config.local.php` setzen.
3. Verbindung über die Admin-Oberfläche prüfen.

---

## Seite: RFID-Reader

# RFID-Reader

Es gibt zwei unterstützte Varianten. Wähle die, die zu deiner Hardware passt.

## Variante 1: Tastatur-Scanner (USB-HID/Keyboard-Wedge)
Der Reader verhält sich wie eine Tastatur und schreibt die UID direkt in das
aktive Eingabefeld.

**Konfiguration**
- `terminal.rfid_ws.enabled = false`
- oder `ZEIT_RFID_WS_ENABLED=0`

**Test**
- Terminal-Seite öffnen.
- Scan durchführen und prüfen, dass die UID im Eingabefeld landet.

## Variante 2: SPI/RC522 mit WebSocket-Bridge
Der Reader liefert UIDs über einen lokalen WebSocket-Dienst.

**Konfiguration**
- `terminal.rfid_ws.enabled = true`
- `terminal.rfid_ws.url = ws://127.0.0.1:8765`
- oder `ZEIT_RFID_WS_ENABLED=1`, `ZEIT_RFID_WS_URL=ws://127.0.0.1:8765`

**Test**
- Dienststatus prüfen (`systemctl status rfid-ws.service --no-pager`).
- Port checken (`ss -lntp | grep 8765`).
- Terminal-Seite öffnen und Scan durchführen.

## Fehlersuche (Kurz)
- Kein Input beim Tastatur-Scanner → Fokus prüfen, HID-Modus aktiv?
- WebSocket getrennt → URL/Port prüfen, `wss://` bei HTTPS.
- RFID unbekannt → RFID-Zuweisung im Admin prüfen.

---

## Seite: FAQ

# FAQ

**F: Wie finde ich die wichtigsten Einstellungen?**  
A: Starte bei `config/` und `services/`. Die Admin-Seite ist der erste Einstieg.

**F: Wo stehen die DB-Tabellen/Struktur?**  
A: In `sql/01_initial_schema.sql`.

**F: Warum kommt beim Scan nichts an?**  
A: Beim Tastatur-Scanner: Fokus prüfen. Bei WebSocket: Dienst/Port/URL prüfen.

**F: RFID wird nicht erkannt?**  
A: Prüfe die RFID-Zuweisung in der Admin-Oberfläche.
