# Nur fuer einen wegwerfbaren GitHub-Windows-Runner, niemals auf Benutzergeraeten.
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
if ($env:GITHUB_ACTIONS -ne 'true' -or -not $env:RUNNER_TEMP) { throw 'Nur in der isolierten GitHub-Actions-Testumgebung ausfuehren.' }
$Repo = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$Origin = Join-Path $env:RUNNER_TEMP 'zeit-origin'
$App = 'C:/xampp/zeiterfassung'
$Php = 'C:/xampp/php/php.exe'
$Tests = 0
function Gut([string]$Name, [bool]$Ok) {
    if (-not $Ok) { throw "FAIL $Name" }
    $script:Tests++
    Write-Host "PASS $Name"
}
function Git([string[]]$Argumente) {
    & git.exe @Argumente
    if ($LASTEXITCODE) { throw 'Git-Testbefehl fehlgeschlagen.' }
}
function PHP([string]$Code) {
    # PowerShell 5.1 veraendert innere Anfuehrungszeichen bei nativen -r-Argumenten.
    $Datei = Join-Path $env:RUNNER_TEMP ('zeit-php-' + [guid]::NewGuid().ToString('N') + '.php')
    try {
        [IO.File]::WriteAllText($Datei,('<?php require $argv[1]."/core/Autoloader.php";' + $Code),[Text.UTF8Encoding]::new($false))
        $Text = & $script:Php $Datei $script:App
        if ($LASTEXITCODE) { throw 'PHP-Testbefehl fehlgeschlagen.' }
        return ($Text -join "`n")
    } finally { Remove-Item -LiteralPath $Datei }
}
function Lesen([string]$Name) { Get-Content -LiteralPath "$script:Basis/$Name" -Raw | ConvertFrom-Json }
function Auftrag([string]$Aktion, [string]$Commit = '') {
    $Vorher = if (Test-Path "$script:Basis/status.json") { (Lesen 'status.json').id } else { '' }
    PHP ('WartungAuftrag::einreichen("' + $Aktion + '","' + $Commit + '",1);') | Out-Null
    $Ende = (Get-Date).AddMinutes(5)
    do {
        Start-Sleep -Seconds 1
        if (Test-Path "$script:Basis/status.json") {
            $Status = Lesen 'status.json'
            if ($Status.id -ne $Vorher -and $Status.zustand -in @('erfolgreich','fehlgeschlagen','unterbrochen')) { return $Status }
        }
    } while ((Get-Date) -lt $Ende)
    throw 'Wartungsauftrag antwortet nicht.'
}
try {
    if (Test-Path $App) { throw 'Testziel existiert bereits; keine fremden Daten ueberschreiben.' }
    Git @('clone','--no-hardlinks',$Repo,$Origin)
    Git @('-C',$Origin,'switch','-C','main')
    Git @('-C',$Origin,'config','user.name','WindowsFixture')
    Git @('-C',$Origin,'config','user.email','fixture@localhost')
    Git @('-c','core.autocrlf=false','clone',$Origin,$App)
    & "$App/scripts/windows/installieren.ps1"
    if ($LASTEXITCODE) { throw 'Windows-Installer fehlgeschlagen.' }
    $Basis = PHP 'echo WartungSystem::pfad($argv[1]);'
    $System = Lesen 'system.json'
    $Schluessel = (Get-FileHash "$Basis/privat/signatur.key").Hash
    $KonfigHash = (Get-FileHash "$App/config/config.local.php").Hash
    Gut 'Dienst wird von Windows automatisch gestartet' ((Get-ScheduledTask -TaskName ($System.konfig_aufgabe -replace 'Konfig','Wartung')).State -eq 'Running')
    $PrivatAcl = Get-Acl "$Basis/privat"
    Gut 'Private Daten sind fuer LocalService gesperrt' (-not @($PrivatAcl.Access | Where-Object { $_.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value -eq 'S-1-5-19' }).Count)
    $Apache = Get-CimInstance Win32_Service | Where-Object { $_.Name -like 'ZeitApache-*' }
    Gut 'Apache laeuft mit eingeschraenkter Identitaet' ($Apache.StartName -eq 'NT AUTHORITY\LocalService')
    & $Php "$App/scripts/tests/wartung_plattform.php"
    if ($LASTEXITCODE) { throw 'Portable Windows-Pruefung fehlgeschlagen.' }
    $Antwort = Invoke-WebRequest 'http://localhost/' -UseBasicParsing
    Gut 'Backend ist ueber Apache erreichbar' ($Antwort.StatusCode -eq 200)
    $Backup = Auftrag 'backup'
    Gut 'Backupknopf-Auftrag wird ausgefuehrt' ($Backup.zustand -eq 'erfolgreich')
    $Manifest = Get-Content "$($Backup.backup)/manifest.json" -Raw | ConvertFrom-Json
    Gut 'Windows sichert Dateien und SQL' ($Manifest.archiv_format -eq 'zip' -and (Test-Path "$($Backup.backup)/haupt.sql"))
    $Restore = Join-Path $env:RUNNER_TEMP 'zeit-restore'
    Expand-Archive "$($Backup.backup)/dateien.zip" -DestinationPath $Restore
    Gut 'Datei-Restore erhaelt lokale Konfiguration' ((Get-FileHash "$Restore/pfad-0/config/config.local.php").Hash -eq $KonfigHash)
    PHP ('$c=WartungSystem::konfig($argv[1],true);$db=$c["db_admin"]["haupt"];WartungDateien::pdo($db)->exec("CREATE DATABASE zeit_ci_restore");$db["dbname"]="zeit_ci_restore";WartungDateien::mysql($db,function($o,$n){WartungDateien::prozess(["mariadb",$o,$n],eingabe:"' + ($Backup.backup -replace '\\','/') + '/haupt.sql");});echo WartungDateien::pdo($db)->query("SELECT COUNT(*) FROM wartung_system")->fetchColumn();') | ForEach-Object { Gut 'SQL-Restore ist lesbar' ($_ -eq '1') }
    $Json = Get-Content "$Origin/updates/migrationen.json" -Raw | ConvertFrom-Json
    $Json.migrationen += [pscustomobject]@{datei='sql/16_migration_windows_probe.sql';ziel='haupt';pruefung='SELECT COUNT(*)=1 FROM wartung_windows_probe'}
    [IO.File]::WriteAllText("$Origin/updates/migrationen.json",($Json | ConvertTo-Json -Depth 10),[Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText("$Origin/sql/16_migration_windows_probe.sql",'CREATE TABLE wartung_windows_probe(id INT PRIMARY KEY);INSERT INTO wartung_windows_probe VALUES(1);',[Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText("$Origin/updates/windows-test.txt",'Windows-Update',[Text.UTF8Encoding]::new($false))
    Git @('-C',$Origin,'add','.')
    Git @('-C',$Origin,'commit','-m','Windows Testupdate')
    $Commit = (& git.exe '-C' $Origin 'rev-parse' 'HEAD').Trim()
    $Probe = Auftrag 'pruefen'
    Gut 'Windows erkennt Update und DB-Migration' ($Probe.zustand -eq 'erfolgreich' -and (Lesen 'angebot.json').migrationen.Count -eq 1)
    $Update = Auftrag 'update' $Commit
    Gut 'Windows installiert nach Sicherung und startet Apache neu' ($Update.zustand -eq 'erfolgreich' -and (Lesen 'version.json').commit -eq $Commit)
    Gut 'Konfiguration und Signaturschluessel bleiben erhalten' ((Get-FileHash "$App/config/config.local.php").Hash -eq $KonfigHash -and (Get-FileHash "$Basis/privat/signatur.key").Hash -eq $Schluessel)
    $Gesund = PHP '$d=new WartungDienst($argv[1],WartungSystem::konfig($argv[1],true));echo json_encode($d->gesundheit());' | ConvertFrom-Json
    Gut 'Neuer Code und Datenbank bestehen Gesundheitspruefung' $Gesund.ok
    $Datei = [IO.File]::Open("$App/updates/windows-test.txt",'Open','ReadWrite','None')
    try {
        $Abgewiesen = PHP 'try{WartungPlattform::dateisperrenPruefen($argv[1],["updates/windows-test.txt"]);echo "nein";}catch(RuntimeException $e){echo "ja";}'
        Gut 'Exklusiv geoeffnete Datei wird vor SQL erkannt' ($Abgewiesen -eq 'ja')
    } finally { $Datei.Dispose() }
    & "$App/scripts/windows/installieren.ps1"
    if ($LASTEXITCODE) { throw 'Installer-Wiederholung fehlgeschlagen.' }
    Gut 'Wiederinstallation erhaelt Versionsstand und Schluessel' ((Lesen 'version.json').commit -eq $Commit -and (Get-FileHash "$Basis/privat/signatur.key").Hash -eq $Schluessel)
    Write-Host "Ergebnis: $Tests native Windows-Pruefungen erfolgreich."
} catch {
    Write-Host $_.Exception.Message
    if (Test-Path variable:script:Basis) {
        if (Test-Path "$Basis/status.json") { Get-Content -LiteralPath "$Basis/status.json" -Raw }
    }
    if (Test-Path 'C:/xampp/apache/logs/error.log') { Get-Content 'C:/xampp/apache/logs/error.log' -Tail 30 }
    exit 1
} finally {
    # Ausschliesslich selbst angelegte Aufgaben/Dienste dieser wegwerfbaren VM.
    Get-ScheduledTask -TaskName 'Zeiterfassung-*' -ErrorAction SilentlyContinue | Stop-ScheduledTask
    Get-Service -Name 'ZeitApache-*','ZeitMariaDb-*' -ErrorAction SilentlyContinue | Stop-Service -Force
}
