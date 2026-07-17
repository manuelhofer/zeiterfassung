# Wartungscheckliste

Diese Checkliste dient als sichere Basis vor und nach Aenderungen. Sie ersetzt
keine fachliche Abnahme, hilft aber dabei, bestehende Funktionalitaet bewusst
zu schuetzen.

## Grundsatz

- Keine Fachlogik aendern, wenn nur Dokumentation oder Einstiegspunkte
  verbessert werden sollen.
- Vor groesseren Aenderungen zuerst den aktuellen Stand sichern.
- Nach jeder Aenderung mindestens Syntaxcheck und die passenden manuellen
  Kernablaeufe pruefen.

## Technischer Schnellcheck

PowerShell aus dem Projektverzeichnis:

```powershell
git status --short
D:\xampp1\php\php.exe -v
```

Alle PHP-Dateien auf Syntaxfehler pruefen:

```powershell
$errors = @()
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    $out = & D:\xampp1\php\php.exe -l $_.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        $errors += [PSCustomObject]@{
            Path = $_.FullName
            Output = ($out -join ' ')
        }
    }
}
if ($errors.Count -eq 0) {
    'OK: all PHP files lint clean'
} else {
    $errors | Format-Table -AutoSize
}
```

## Manuelle Kernablaeufe

Nach Aenderungen an Backend, Auth, Session, Rechten oder Layout:

- Login als Admin
- Dashboard oeffnen
- Mitarbeiterliste oeffnen
- Rollen/Rechte oeffnen
- Monatsreport HTML oeffnen
- Monatsreport PDF erzeugen
- Urlaub beantragen und Liste oeffnen
- Urlaub-Genehmigungsliste oeffnen, sofern Berechtigung vorhanden

Nach Aenderungen am Terminal:

- Terminal-Startseite oeffnen
- RFID/Login testen
- Kommen buchen
- Gehen buchen
- Auftrag starten
- Auftrag stoppen
- Auto-Logout pruefen
- Health-Endpunkt `public/terminal.php?aktion=health` pruefen

Nach Aenderungen an Offline-Queue oder Datenbankverbindung:

- Queue-Admin oeffnen
- Status `offen`, `fehler`, `verarbeitet` ansehen
- Terminal bei erreichbarer Hauptdatenbank testen
- Terminal mit nicht erreichbarer Hauptdatenbank nur kontrolliert testen
- Wiederanlauf der Queue pruefen

## Bereiche mit besonderer Vorsicht

- `public/index.php` und `public/terminal.php`
- `controller/TerminalController.php`
- `core/OfflineQueueManager.php`
- `services/AuthService.php`
- `views/layout/header.php`
- Monatsreport/PDF-Services

Diese Bereiche funktionieren aktuell, sind aber zentral fuer viele Ablaufe.
Hier nur kleine, gut pruefbare Schritte machen.
