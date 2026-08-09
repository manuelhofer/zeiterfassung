# Zeiterfassung & Mitarbeiter-/Auftragsmanagement

Webbasierte Zeiterfassung für Betriebe: Kommen/Gehen mit Rundungs- und
Pausenregeln, Urlaubsverwaltung mit Genehmigungsworkflow, Auftragszeiten per
Scan, Auswertungen als Monats-PDF – dazu eine Terminal-Oberfläche (Kiosk) für
die Halle inklusive RFID-Login und Offline-Queue.

Aufträge lassen sich im Backend anlegen und mit Arbeitsschritten versehen; für
wiederkehrende Tätigkeiten gibt es einen betriebsweiten Arbeitsschritt-Katalog.
Alle Codes sind Strichcodes (Code 128) und lassen sich als **Laufkarte** je
Auftrag oder als **Kartenblatt** für die Maschinen ausdrucken – Details in
[docs/spezifikation_auftrag_barcode_laufkarte.md](docs/spezifikation_auftrag_barcode_laufkarte.md).

**Technik:** reines PHP (kein Framework), MariaDB/MySQL, Apache. Läuft auf
Debian; das Terminal und eine Backup-Datenbank laufen auch auf einem
Raspberry Pi.

**Status:** fertig, im Praxis-Test. Weiterentwicklung nur bei Bugs oder auf
ausdrücklichen Auftrag.

---

## Wenn du dieses Repository frisch geklont hast

**Zuerst lesen – in dieser Reihenfolge:**

1. **[`CHATSTART.md`](CHATSTART.md)** – der Einstieg: Was ist das Projekt, wie
   wird gearbeitet, und die **Lesekarte**, welche Datei zu welcher Aufgabe
   gehört. Gilt für Menschen wie für KI-Assistenten.
2. [`docs/arbeitsregeln.md`](docs/arbeitsregeln.md) – die verbindlichen Regeln
   für jede Änderung.
3. [`docs/STATUS_SNAPSHOT.md`](docs/STATUS_SNAPSHOT.md) – aktueller Stand und
   nächster Schritt.
4. Die passende Datei aus [`docs/fachregeln/`](docs/fachregeln/) – **nur die
   zum Thema**, nicht alle.

Die Fachlogik lag früher komplett im Master-Prompt v13. Der liegt jetzt im
Archiv und wird nur noch für historische Fragen gebraucht; sein Inhalt steckt
in `docs/arbeitsregeln.md` und `docs/fachregeln/`.

Wer wissen will, warum im Archiv so viele alte Prompts liegen und was davon
noch gilt: [`docs/archiv/ALTE_PROMPTS.md`](docs/archiv/ALTE_PROMPTS.md).

## Lokal zum Laufen bringen

Vollständige Anleitung: **[`docs/lokale_entwicklungsumgebung.md`](docs/lokale_entwicklungsumgebung.md)**

Auf Arch Linux / CachyOS genügt:

```bash
sudo bash scripts/dev/setup_lokale_umgebung_arch.sh
```

Danach:

| | |
| --- | --- |
| Backend | `http://localhost/zeiterfassung` |
| Terminal | `http://localhost/zeiterfassung/terminal.php` |
| phpMyAdmin | `http://localhost/phpmyadmin` |

Beim ersten Aufruf erscheint die Maske **Erstinstallation**, in der der erste
Administrator angelegt wird.

Für den **Produktivserver** (Debian/Apache) gilt stattdessen
[`docs/installationsanleitung.md`](docs/installationsanleitung.md).

## Projektstruktur

| Verzeichnis | Inhalt |
| --- | --- |
| `public/` | Einstiegspunkte (`index.php`, `terminal.php`), CSS, JS, Bilder |
| `core/` | Basis: Datenbank, Autoloader, Session, Logging, Offline-Queue, Feiertage |
| `modelle/` | reine DB-Modelle (Mitarbeiter, Zeitbuchungen, Urlaubsanträge, …) |
| `services/` | Fachlogik (ZeitService, UrlaubService, RundungsService, PDFService, …) |
| `controller/` | Request-Verarbeitung, Übergabe an Views |
| `views/` | PHP-Views / HTML-Templates |
| `config/` | Konfiguration (`config.php`, lokale Werte in `config.local.php`) |
| `sql/` | Initialschema und SQL-Hilfen |
| `scripts/dev/` | Helfer für die lokale Entwicklungsumgebung |
| `scripts/terminal/` | Installationsskript für ein Hallenterminal |
| `docs/` | Arbeitsregeln, Fachregeln, Handbücher, Archiv |

Einstiegspunkte: Backend `public/index.php` (`?seite=…`), Terminal
`public/terminal.php` (`?aktion=…`).

## Konfiguration

`config/config.php` enthält nur Defaults. Echte Zugangsdaten gehören in
`config/config.local.php` (nicht versioniert) – Vorlage:
`config/config.php.example`.

**Terminals konfigurieren sich selbst.** Fehlt `config/config.local.php`, zeigt
`public/terminal.php` statt der Bedienoberfläche eine Einrichtungsseite: dort
werden Server-Adresse und ein im Backend erzeugter Kopplungscode eingegeben, und
das Terminal schreibt seine Konfiguration daraus selbst. Das Grundsystem eines
Geräts richtet `scripts/terminal/install_terminal.sh` ein – Pakete, Code,
Webserver und lokale Ausweichdatenbank, aber bewusst **keine** Zugangsdaten;
`scripts/terminal/install_kiosk.sh` macht daraus den Vollbild-Kiosk.
Einzelheiten: [Terminal-Installation](docs/spezifikation_terminal_installation.md).

## Mitarbeiten

Kurzfassung – vollständig in [`docs/arbeitsregeln.md`](docs/arbeitsregeln.md):

- **Ein Patch = ein Thema** mit einem Akzeptanzkriterium in einem Satz.
- Patch-ID `P-YYYY-MM-DD-XX` in den Commit-Betreff, z. B.
  `P-2026-08-08-01 report-kommen-gehen`.
- [`docs/archiv/DEV_PROMPT_HISTORY.md`](docs/archiv/DEV_PROMPT_HISTORY.md) im
  **selben Commit** pflegen.
- Vor dem Start prüfen, ob die Sache nicht längst erledigt ist (History +
  `git log`).
- Nach der Änderung: `php -l` über die geänderten Dateien und die betroffenen
  Abläufe aus [`docs/wartungscheckliste.md`](docs/wartungscheckliste.md)
  durchklicken.
- Deutsch: Oberfläche, Variablennamen, Kommentare.
- PHP-Baseline: mindestens 8.2 (Raspberry Pi OS / Debian 12), muss auf
  aktuellem PHP warnungsfrei laufen.

## Weitere Dokumentation

- [Admin-Handbuch](docs/admin_handbuch.md)
- [Wartungscheckliste](docs/wartungscheckliste.md)
- [RFID-Reader-Setup](docs/rfid_reader_setup.md)
- [Git-Wiki & Repo-Beschreibung](docs/git_wiki_und_beschreibung.md)
- [Verzeichnis aller Dokumente](docs/README.md)

## Lizenz

Siehe [LICENSE](LICENSE).
