# Zeiterfassung & Mitarbeiter-/Auftragsmanagement

Webbasierte Zeiterfassung für Betriebe: Kommen/Gehen mit Rundungs- und
Pausenregeln, Urlaubsverwaltung mit Genehmigungsworkflow, Auftragszeiten per
Scan, Auswertungen als Monats-PDF – dazu eine Terminal-Oberfläche (Kiosk) für
die Halle inklusive RFID-Login und Offline-Queue.

**Technik:** reines PHP (kein Framework), MariaDB/MySQL, Apache. Läuft auf
Debian; das Terminal und eine Backup-Datenbank laufen auch auf einem
Raspberry Pi.

**Status:** fertig, im Praxis-Test. Weiterentwicklung nur bei Bugs oder auf
ausdrücklichen Auftrag.

---

## Wenn du dieses Repository frisch geklont hast

**Zuerst lesen – in dieser Reihenfolge:**

1. **[`docs/master_prompt_zeiterfassung_v13.md`](docs/master_prompt_zeiterfassung_v13.md)**
   – das verbindliche Regelwerk: Arbeitsweise, Architektur, komplette
   Fachlogik. Der Abschnitt „0. Einstieg nach dem Klonen“ führt durch den Rest.
2. [`docs/STATUS_SNAPSHOT.md`](docs/STATUS_SNAPSHOT.md) – aktueller Stand in
   Kurzform.
3. [`docs/rechte_prompt.md`](docs/rechte_prompt.md) – Source of Truth für alle
   Rechte-Codes.
4. [`docs/archiv/DEV_PROMPT_HISTORY.md`](docs/archiv/DEV_PROMPT_HISTORY.md) –
   Snapshot oben, darunter der vollständige Projektverlauf.

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
| `docs/` | Dokumentation, Master-Prompt, Handbücher, Archiv |

Einstiegspunkte: Backend `public/index.php` (`?seite=…`), Terminal
`public/terminal.php` (`?aktion=…`).

## Konfiguration

`config/config.php` enthält nur Defaults. Echte Zugangsdaten gehören in
`config/config.local.php` (nicht versioniert) – Vorlage:
`config/config.php.example`.

## Mitarbeiten

Kurzfassung der Regeln aus dem Master-Prompt:

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
- [Prompt-Übersicht](docs/prompt_uebersicht.md)
- [Git-Wiki & Repo-Beschreibung](docs/git_wiki_und_beschreibung.md)

## Lizenz

Siehe [LICENSE](LICENSE).
