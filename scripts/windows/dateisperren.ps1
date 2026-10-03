param([Parameter(Mandatory=$true)][string]$Liste)
$ErrorActionPreference = 'Stop'
try {
    foreach ($Pfad in (Get-Content -LiteralPath $Liste -Raw | ConvertFrom-Json)) {
        $Datei = [IO.File]::Open($Pfad, [IO.FileMode]::Open, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
        $Datei.Dispose()
    }
} catch { Write-Error 'Eine Programmdatei ist gesperrt oder nicht ersetzbar.'; exit 1 }
