param([Parameter(Mandatory=$true)][ValidatePattern('^Zeiterfassung-Konfig-[a-f0-9]{16}$')][string]$Aufgabe)
$ErrorActionPreference = 'Stop'
try {
    $Ende = (Get-Date).AddSeconds(15)
    while ((Get-ScheduledTask -TaskName $Aufgabe).State -eq 'Running') {
        if ((Get-Date) -gt $Ende) { throw 'Vorige Konfigurationsauswertung laeuft noch.' }
        Start-Sleep -Milliseconds 100
    }
    Start-ScheduledTask -TaskName $Aufgabe
} catch { Write-Error 'Die Konfigurationsaufgabe konnte nicht gestartet werden.'; exit 1 }
