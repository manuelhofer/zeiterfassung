param([Parameter(Mandatory=$true)][ValidatePattern('^[A-Za-z0-9_.-]+$')][string]$Dienst)
$ErrorActionPreference = 'Stop'
try {
    Restart-Service -Name $Dienst -Force
    (Get-Service -Name $Dienst).WaitForStatus('Running', [TimeSpan]::FromSeconds(30))
} catch { Write-Error 'Apache konnte nicht neu gestartet werden.'; exit 1 }
