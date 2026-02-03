# Git-Wiki und Repository-Beschreibung

## Ziel
Diese Anleitung beschreibt, wie eine **GitHub-Wiki** für das Projekt gepflegt
wird und wie die **Repository-Beschreibung** (Kurzbeschreibung + Topics) sinnvoll
gesetzt wird.

## GitHub-Wiki anlegen und pflegen

### 1) Wiki aktivieren
1. Öffne das Repository auf GitHub.
2. Klicke auf **Settings** → **Features**.
3. Aktiviere die Option **Wiki**.

> Hinweis: Die Wiki ist ein **separates Git-Repository**. Du kannst sie sowohl
> über die GitHub-Weboberfläche als auch per Git-Clone verwalten.

### 2) Wiki-Repository klonen
```bash
git clone https://github.com/<ORG>/<REPO>.wiki.git
```

### 3) Startstruktur für die Wiki (Empfehlung)
Lege folgende Seiten an (Dateinamen sind die Seitentitel):

- **Home.md**
  - Kurzüberblick, Links zu den wichtigsten Bereichen.
- **Administration.md**
  - Einstellungen, Rollen, Rechte, Konfigurationsorte.
- **RFID-Reader.md**
  - Anleitung für beide RFID-Varianten (Keyboard-Wedge & WebSocket-Bridge).
- **FAQ.md**
  - Häufige Probleme und Lösungen.
- **Changelog.md** (optional)
  - Änderungen an Prozessen und Konfigurationen.

### 4) Inhalt aus der Projekt-Doku übernehmen
Die Dateien im Verzeichnis `docs/` eignen sich als Grundlage:
- `docs/admin_handbuch.md` → **Administration.md**
- `docs/rfid_reader_setup.md` → **RFID-Reader.md**
- `docs/STATUS_SNAPSHOT.md` → **Changelog.md** (ggf. gekürzt)

### 5) Änderungen veröffentlichen
```bash
git add .
git commit -m "Wiki: Basisdokumentation" 
git push
```

## Repository-Beschreibung (Kurztext + Topics)

### 1) Kurzbeschreibung setzen
1. Öffne die Repository-Seite auf GitHub.
2. Klicke auf das **Zahnrad** neben der Beschreibung.
3. Beispieltext:
   - **Kurzbeschreibung:** „Zeiterfassung und Mitarbeiter-/Auftragsmanagement mit RFID-Integration“

### 2) Topics pflegen
Empfohlene Topics (anpassen je nach Projektumfang):
- `zeiterfassung`
- `rfid`
- `php`
- `zeitmanagement`
- `mitarbeiterverwaltung`

### 3) README-Teaser (optional)
Optional kann im Haupt-README ein kurzer Absatz ergänzt werden, der auf die
Wiki verweist und den Einstieg erleichtert.

## Empfohlener Wiki-Starttext (Beispiel)
```markdown
# Zeiterfassung – Wiki

Willkommen im Projekt-Wiki. Hier findest du:
- **Administration** (Einstellungen, Rechte, Konfiguration)
- **RFID-Reader** (beide Varianten inkl. Fehlerdiagnose)
- **FAQ**

Für technische Details im Repository siehe das Verzeichnis `docs/`.
```
