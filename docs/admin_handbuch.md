# Admin-Handbuch

## Zweck und Zielgruppe
Dieses Handbuch richtet sich an Administrator:innen und beschreibt die
Konfigurationsorte sowie typische Einstellungen im Projekt.

## Konfigurationsorte und Zuständigkeiten

### `config/`
- **`config/config.php`**: Zentrale Konfigurationsdatei. Enthält die Struktur
  der Einstellungen und Default-Werte.
- **`config/config.php.example`**: Vorlage für eigene Konfigurationen.
- **`config/config.local.php`** (nicht versioniert): Lokale/produktive Werte,
  z. B. Zugangsdaten. Wird bevorzugt geladen.

### `services/`
- **Geschäftslogik** (z. B. Zeit- und Urlaubsregeln). Hier wird festgelegt, wie
  Buchungen, Rundungen oder Berechnungen durchgeführt werden.
- Typische Anlaufpunkte: `ZeitService`, `UrlaubService`, `RundungsService`.

### `controller/`
- **Request-Verarbeitung**: Nimmt Parameter an, orchestriert Services und
  übergibt Daten an Views. Geeignet für Anpassungen an Abläufen und
  Admin-Oberflächen.

### `modelle/`
- **Datenbank-Modelle**: Reine DB-Zugriffe und Datenobjekte für Mitarbeiter,
  Zeitbuchungen, Urlaubsanträge usw.

### `sql/`
- **Datenbankschemata** und Migrationen, z. B. `sql/01_initial_schema.sql` als
  Referenz der DB-Struktur.

## Schritt-für-Schritt: Typische Einstellungen

### 1) Benutzer anlegen und pflegen
1. Öffne die Admin-Oberfläche (Web).
2. Lege einen neuen Mitarbeiter an (Name, Personalnummer, Status).
3. Weisen die Benutzerrolle(n) zu (siehe Abschnitt Rollen/Rechte).
4. Speichere und prüfe, ob der Benutzer in der Übersicht erscheint.

**Hinweis:** Die zugrundeliegenden DB-Tabellen und Felder findest du im
Schema unter `sql/01_initial_schema.sql`.

### 2) Rollen und Rechte
1. Öffne die Rollen-/Rechteverwaltung in der Admin-Oberfläche.
2. Erstelle eine neue Rolle (z. B. „Teamleitung“).
3. Aktiviere die benötigten Rechte (z. B. Freigaben, Auswertungen, Admin).
4. Weisen die Rolle einzelnen Benutzer:innen zu.

**Technischer Bezug:** Rollenlogik wird typischerweise in `services/` umgesetzt
und von `controller/` verarbeitet.

### 3) Zeitregeln und Rundungen
1. Prüfe vorhandene Rundungs- oder Zeitregeln in den Services.
2. Passe die Regeln in den jeweiligen Services an (z. B. Rundungsintervalle).
3. Teste die Änderung mit Beispielbuchungen (z. B. Start/Ende).

**Tipp:** Halte die Regeln konsistent und dokumentiere Änderungen im
Status-Snapshot.

### 4) Datenbank/SQL (z. B. Initialisierung)
1. Richte die Datenbank ein und importiere das Schema aus `sql/01_initial_schema.sql`.
2. Pflege Zugangsdaten in `config/config.local.php` oder per Umgebungsvariablen.
3. Prüfe die Verbindung über die Admin-Oberfläche.

### 5) RFID-Reader (Terminal)
1. Wähle die passende Reader-Variante (Tastatur-Scanner oder WebSocket-Bridge).
2. Konfiguriere die Bridge-Einstellungen in `config/config.php` bzw. `config/config.local.php`.
3. Teste den Scan am Terminal und prüfe die Logs bei Problemen.

## Verlinkte Referenzen
- Projektübersicht und Konfiguration: [`docs/README.md`](README.md)
- Status/Letzte Änderungen: [`docs/STATUS_SNAPSHOT.md`](STATUS_SNAPSHOT.md)
- RFID-Reader-Setup: [`docs/rfid_reader_setup.md`](rfid_reader_setup.md)
