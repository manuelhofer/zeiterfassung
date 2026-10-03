# Auf einem eigenen XAMPP-Backend als Administrator ausfuehren. Keine Zusatzdienste.
param([string]$XamppPfad = '')
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Programm([string]$Datei, [string[]]$Argumente) {
    & $Datei @Argumente
    if ($LASTEXITCODE -ne 0) { throw "Programm fehlgeschlagen: $([IO.Path]::GetFileName($Datei)) (Exit $LASTEXITCODE)." }
}
function TextSchreiben([string]$Pfad, [string]$Text) {
    [IO.File]::WriteAllText($Pfad, $Text, [Text.UTF8Encoding]::new($false))
}
function Rechte([string]$Pfad, [string]$Webrecht = '') {
    $Ordner = (Get-Item -LiteralPath $Pfad -Force).PSIsContainer
    if ($Ordner) { $Acl = [Security.AccessControl.DirectorySecurity]::new() }
    else { $Acl = [Security.AccessControl.FileSecurity]::new() }
    $Acl.SetAccessRuleProtection($true, $false)
    $Acl.SetOwner([Security.Principal.SecurityIdentifier]::new('S-1-5-32-544'))
    $Erbe = [Security.AccessControl.InheritanceFlags]::None
    if ($Ordner) { $Erbe = [Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit' }
    foreach ($Sid in @('S-1-5-18', 'S-1-5-32-544')) {
        $Acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
            [Security.Principal.SecurityIdentifier]::new($Sid), 'FullControl', $Erbe, 'None', 'Allow'))
    }
    if ($Webrecht) {
        $Acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
            [Security.Principal.SecurityIdentifier]::new('S-1-5-19'), $Webrecht, $Erbe, 'None', 'Allow'))
    }
    Set-Acl -LiteralPath $Pfad -AclObject $Acl
}
function BaumRechte([string]$Pfad, [string]$Webrecht = '') {
    Rechte $Pfad $Webrecht
    # Vorhandene explizite Schreibrechte verschwinden, neue Dateien erben die ACL.
    Get-ChildItem -LiteralPath $Pfad -Force -Recurse | ForEach-Object { Rechte $_.FullName $Webrecht }
}
function Aufgabe([string]$Name, [string]$Skript, [string]$Zusatz, [string]$Identitaet, [switch]$Dauerhaft) {
    $Aktion = New-ScheduledTaskAction -Execute $script:Php -Argument ('"' + $Skript + '"' + $Zusatz) -WorkingDirectory $script:App
    $Principal = New-ScheduledTaskPrincipal -UserId $Identitaet -LogonType ServiceAccount
    $Settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit ([TimeSpan]::Zero) `
        -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
    if ($Dauerhaft) {
        $Trigger = @(New-ScheduledTaskTrigger -AtStartup; New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1))
        Register-ScheduledTask -TaskName $Name -Action $Aktion -Principal $Principal -Settings $Settings -Trigger $Trigger -Force | Out-Null
    } else {
        Register-ScheduledTask -TaskName $Name -Action $Aktion -Principal $Principal -Settings $Settings -Force | Out-Null
    }
}

try {
    if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Bitte PowerShell als Administrator starten.' }
    $App = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..')).TrimEnd('\')
    if (-not $XamppPfad) {
        $Kandidaten = @('C:\xampp') + @(Get-PSDrive -PSProvider FileSystem | ForEach-Object { Join-Path $_.Root 'xampp' })
        $Kandidaten += @('HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\xampp',
            'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\xampp') | ForEach-Object {
            if (Test-Path $_) { (Get-ItemProperty $_).InstallLocation }
        }
        $XamppPfad = @($Kandidaten | Where-Object { $_ -and (Test-Path (Join-Path $_ 'php/php.exe')) } | Select-Object -Unique | Select-Object -First 1) -join ''
    }
    if (-not $XamppPfad) { throw 'XAMPP nicht gefunden. XAMPP mit PHP mindestens 8.2 installieren.' }
    $Xampp = (Get-Item -LiteralPath $XamppPfad).FullName.TrimEnd('\')
    if ((Split-Path $Xampp -Parent) -notmatch '^[A-Za-z]:\\$') { throw 'XAMPP direkt unter einem Laufwerk installieren, zum Beispiel C:\xampp.' }
    foreach ($Pfad in @($App, $Xampp)) {
        if ($Pfad -notmatch '^[A-Za-z]:\\' -or $Pfad -match '["\r\n]') { throw 'Lokaler Windows-Pfad ohne Anfuehrungszeichen erforderlich.' }
        if ((Get-Volume -DriveLetter $Pfad.Substring(0,1)).FileSystem -ne 'NTFS') { throw 'Der Installer benoetigt NTFS fuer geschuetzte Sicherungen.' }
        if ((Get-Item -LiteralPath $Pfad).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Verknuepfungen im Installationspfad sind nicht unterstuetzt.' }
    }
    foreach ($Pfad in @($App,$Xampp)) {
        if (Get-ChildItem -LiteralPath $Pfad -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
            throw 'Verknuepfungen im Projekt/XAMPP entfernen; der Installer folgt keinen fremden Pfaden.'
        }
    }
    $Php = Join-Path $Xampp 'php/php.exe'
    $Apache = Join-Path $Xampp 'apache/bin/httpd.exe'
    $Mysql = Join-Path $Xampp 'mysql/bin/mysql.exe'
    $Dump = Join-Path $Xampp 'mysql/bin/mysqldump.exe'
    $Server = Join-Path $Xampp 'mysql/bin/mysqld.exe'
    $GitBefehl = Get-Command git.exe -ErrorAction SilentlyContinue
    $Git = if ($GitBefehl) { $GitBefehl.Source } else { Join-Path $env:ProgramFiles 'Git/cmd/git.exe' }
    foreach ($Datei in @($Php,$Apache,$Mysql,$Dump,$Server,$Git)) {
        if (-not (Test-Path -LiteralPath $Datei -PathType Leaf)) { throw "Voraussetzung fehlt: $Datei. XAMPP und Git fuer Windows installieren." }
    }
    # SYSTEM darf spaeter keinen benutzerbeschreibbaren PHP-/Git-Code starten.
    if (-not $Git.StartsWith($env:ProgramFiles + '\', [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Git fuer alle Benutzer unter Program Files installieren; ein benutzerbeschreibbares Git ist ungeeignet.'
    }
    $PhpIni = Join-Path $Xampp 'php/php.ini'
    # Portable XAMPP-Pakete enthalten laufwerksrelative /xampp-Pfade. Diese
    # duerfen weder vom Startordner noch vom Laufwerk des Windows-Diensts abhaengen.
    $XamppUnix = $Xampp -replace '\\','/'
    $Konfigurationen = @($PhpIni,(Join-Path $Xampp 'mysql/bin/my.ini'))
    $Konfigurationen += @(Get-ChildItem -LiteralPath (Join-Path $Xampp 'apache/conf') -Filter '*.conf' -Recurse | ForEach-Object { $_.FullName })
    foreach ($Datei in $Konfigurationen) {
        $Inhalt = Get-Content -LiteralPath $Datei -Raw
        $Inhalt = [regex]::Replace($Inhalt, '(?i)(?:[A-Z]:)?[\\/]+xampp[\\/]', { param($Treffer) $XamppUnix + '/' })
        if (-not (Test-Path "$Datei.vor-zeiterfassung")) { Copy-Item -LiteralPath $Datei -Destination "$Datei.vor-zeiterfassung" }
        TextSchreiben $Datei $Inhalt
    }
    $Ini = Get-Content -LiteralPath $PhpIni -Raw
    foreach ($Erweiterung in @('zip','pdo_mysql','mbstring','gd','curl','openssl')) {
        $Aktiv = '(?im)^[ \t]*extension[ \t]*=[ \t]*(php_)?' + $Erweiterung + '(\.dll)?[ \t]*\r?$'
        if ($Ini -notmatch $Aktiv) {
            $Kommentar = [regex]::new('(?im)^[ \t]*;[ \t]*(extension[ \t]*=[ \t]*(php_)?' + $Erweiterung + '(\.dll)?[ \t]*\r?)$')
            $Ini = $Kommentar.Replace($Ini, '$1', 1)
        }
    }
    TextSchreiben $PhpIni $Ini
    Programm $Php @("$App/scripts/windows/installation_pruefen.php")
    Programm $Git @('-C',$App,'rev-parse','HEAD')
    $Ziel = Join-Path $Xampp 'zeiterfassung'
    if (-not $App.Equals($Ziel, [StringComparison]::OrdinalIgnoreCase)) {
        if (Test-Path -LiteralPath $Ziel) { throw "Ziel $Ziel ist bereits belegt. Nichts wurde ueberschrieben." }
        Move-Item -LiteralPath $App -Destination $Ziel
        $App = $Ziel
        Set-Location -LiteralPath $App
        Write-Host "Projekt nach $App verschoben; nur public/ wird ausgeliefert."
    }
    $Basis = (& $Php "$App/scripts/windows/installation_pruefen.php" 'status-pfad' $App | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $Basis -notmatch '^[a-zA-Z]:/[^\r\n]+/\.zeit-wartung-[a-f0-9]{16}$') { throw 'Wartungsordner konnte nicht bestimmt werden. PHP muss ohne Startwarnungen laufen.' }
    if ((Test-Path "$Basis/aktiv.json") -or (Test-Path "$Basis/pause.json")) { throw 'Wartung laeuft oder ist unterbrochen. Installer erst nach abgeschlossener Wartung ausfuehren.' }
    if (-not (Test-Path "$Basis/version.json")) {
        # Git-Archive und Terminalpakete muessen dieselben Bytes enthalten (kein CRLF-Umbau).
        Programm $Git @('-C',$App,'diff','--exit-code')
        Programm $Git @('-C',$App,'diff','--cached','--exit-code')
        Programm $Git @('-C',$App,'config','core.autocrlf','false')
        Programm $Git @('-C',$App,'checkout-index','--force','--all')
    }
    $Kennung = [IO.Path]::GetFileName($Basis).Substring(14)
    $Takt = 'Zeiterfassung-Wartung-' + $Kennung
    $KonfigTask = 'Zeiterfassung-Konfig-' + $Kennung
    if (Get-ScheduledTask -TaskName $Takt -ErrorAction SilentlyContinue) { Stop-ScheduledTask -TaskName $Takt }
    foreach ($Pfad in @($Basis, "$Basis/privat", "$Basis/auftraege", "$Basis/empfang", "$Basis/anwendung", "$Basis-sicherungen")) {
        New-Item -ItemType Directory -Path $Pfad -Force | Out-Null
    }
    # Schutz zuerst, bevor Zugangsdaten oder Schluessel geschrieben werden.
    BaumRechte $Basis 'ReadAndExecute'
    BaumRechte "$Basis/privat"
    BaumRechte "$Basis/empfang"
    BaumRechte "$Basis-sicherungen"
    BaumRechte "$Basis/auftraege" 'Modify'
    BaumRechte "$Basis/anwendung" 'Modify'
    BaumRechte $App 'ReadAndExecute'
    BaumRechte "$App/config" 'Modify'
    BaumRechte "$App/public/uploads" 'Modify'
    # Nur Ausfuehrungsdateien des eigenen XAMPP sperren, nicht fremde Programme.
    foreach ($Ordner in @('php','apache')) { BaumRechte (Join-Path $Xampp $Ordner) 'ReadAndExecute' }
    foreach ($Ordner in @('apache/logs','php/logs','tmp')) {
        New-Item -ItemType Directory -Path (Join-Path $Xampp $Ordner) -Force | Out-Null
        BaumRechte (Join-Path $Xampp $Ordner) 'Modify'
    }
    # Schutz des Elternordners verhindert Austausch ganzer Codeverzeichnisse.
    Rechte $Xampp 'ReadAndExecute'
    Rechte (Join-Path $Xampp 'mysql')
    BaumRechte (Join-Path $Xampp 'mysql/bin')
    BaumRechte (Join-Path $Xampp 'mysql/data')

    $ApacheServices = @(Get-CimInstance Win32_Service | Where-Object { $_.PathName -like "*$Apache*" })
    $DbServices = @(Get-CimInstance Win32_Service | Where-Object { $_.PathName -like "*$Server*" })
    if ($ApacheServices.Count -gt 1 -or $DbServices.Count -gt 1) { throw 'Mehrere XAMPP-Dienste gefunden; bitte Installation pruefen.' }
    $ApacheDienst = if ($ApacheServices.Count) { $ApacheServices[0].Name } else { 'ZeitApache-' + $Kennung }
    $DbDienst = if ($DbServices.Count) { $DbServices[0].Name } else { 'ZeitMariaDb-' + $Kennung }
    # Standalone-Prozesse desselben XAMPP sauber beenden, bevor Windows-Dienste uebernehmen.
    foreach ($Prozess in @(Get-CimInstance Win32_Process | Where-Object { $_.ExecutablePath -eq $Apache })) {
        if (-not $ApacheServices.Count) { throw 'Apache laeuft noch im XAMPP-Control-Panel. Einmal dort stoppen, dann Installer erneut starten.' }
    }
    foreach ($Prozess in @(Get-CimInstance Win32_Process | Where-Object { $_.ExecutablePath -eq $Server })) {
        if (-not $DbServices.Count) { throw 'MySQL laeuft noch im XAMPP-Control-Panel. Einmal dort stoppen, dann Installer erneut starten.' }
    }
    if ($ApacheServices.Count) { Stop-Service -Name $ApacheDienst -Force }
    if ($DbServices.Count) { Stop-Service -Name $DbDienst -Force }
    $HttpdKonfig = Join-Path $Xampp 'apache/conf/httpd.conf'
    $Vorher = Get-Content -LiteralPath $HttpdKonfig -Raw
    if (-not (Test-Path "$HttpdKonfig.vor-zeiterfassung")) { Copy-Item -LiteralPath $HttpdKonfig -Destination "$HttpdKonfig.vor-zeiterfassung" }
    $Public = ($App -replace '\\','/') + '/public'
    $Dokument = [regex]::Match($Vorher, '(?m)^\s*DocumentRoot\s+"([^"]+)"')
    if (-not $Dokument.Success) { throw 'Apache-DocumentRoot nicht erkannt.' }
    $Neu = $Vorher.Replace($Dokument.Value, 'DocumentRoot "' + $Public + '"')
    $Neu = $Neu.Replace('<Directory "' + $Dokument.Groups[1].Value + '">', '<Directory "' + $Public + '">')
    TextSchreiben $HttpdKonfig $Neu
    Programm $Apache @('-t','-f',$HttpdKonfig)
    if (-not $ApacheServices.Count) { Programm $Apache @('-k','install','-n',$ApacheDienst,'-f',$HttpdKonfig) }
    $ApacheService = Get-CimInstance Win32_Service -Filter "Name='$ApacheDienst'"
    $Resultat = Invoke-CimMethod -InputObject $ApacheService -MethodName Change -Arguments @{ StartName='NT AUTHORITY\LocalService'; StartPassword='' }
    if ($Resultat.ReturnValue -ne 0) { throw 'Apache konnte nicht auf LocalService umgestellt werden.' }
    if (-not $DbServices.Count) { Programm $Server @('--install',$DbDienst,('--defaults-file=' + (Join-Path $Xampp 'mysql/bin/my.ini'))) }
    Set-Service -Name $DbDienst -StartupType Automatic
    Set-Service -Name $ApacheDienst -StartupType Automatic
    Start-Service -Name $DbDienst
    (Get-Service $DbDienst).WaitForStatus('Running',[TimeSpan]::FromSeconds(30))
    $Windows = @{ programme=@{ 'git'=$Git; 'mariadb'=$Mysql; 'mariadb-dump'=$Dump;
        'schtasks.exe'=(Join-Path $env:SystemRoot 'System32/schtasks.exe');
        'powershell.exe'=(Join-Path $env:SystemRoot 'System32/WindowsPowerShell/v1.0/powershell.exe') };
        konfig_aufgabe=$KonfigTask; apache_dienst=$ApacheDienst; openssl_conf=(Join-Path $Xampp 'apache/conf/openssl.cnf') }
    TextSchreiben "$Basis/privat/windows.json" ($Windows | ConvertTo-Json -Depth 8)
    Programm $Php @("$App/scripts/backend_einrichten.php")
    BaumRechte "$App/config" 'Modify'
    Aufgabe $KonfigTask "$App/scripts/wartung_konfig.php" ' --windows-json' 'S-1-5-19'
    Programm $Php @("$App/scripts/wartung_einrichten.php",'LocalService')
    # Sperrdatei nur oeffnen/locken, nicht ersetzen; Auftragseingang separat beschreibbar.
    Rechte "$Basis/anfragen.lock" 'Read'
    BaumRechte "$Basis/auftraege" 'Modify'
    Aufgabe $Takt "$App/scripts/wartung_windows.php" '' 'S-1-5-18' -Dauerhaft
    foreach ($Regel in @(@{Name="Zeiterfassung-Web-$Kennung";Programm=$Apache;Port=80},
        @{Name="Zeiterfassung-DB-$Kennung";Programm=$Server;Port=3306})) {
        Get-NetFirewallRule -Name $Regel.Name -ErrorAction SilentlyContinue | Remove-NetFirewallRule
        New-NetFirewallRule -Name $Regel.Name -DisplayName $Regel.Name -Direction Inbound -Action Allow `
            -Profile Domain,Private -RemoteAddress LocalSubnet -Program $Regel.Programm -Protocol TCP -LocalPort $Regel.Port | Out-Null
    }
    Start-Service -Name $ApacheDienst
    Start-ScheduledTask -TaskName $Takt
    Write-Host "Installation abgeschlossen. Im Browser http://localhost/ oeffnen. Projekt: $App"
} catch {
    Write-Error $_.Exception.Message
    exit 1
}
