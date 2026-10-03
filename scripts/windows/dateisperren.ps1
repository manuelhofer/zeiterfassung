param([Parameter(Mandatory=$true)][string]$Liste)
$ErrorActionPreference = 'Stop'
$Pfad = ''
try {
    # Ersetzen benoetigt DELETE-Freigabe, keine exklusive Lesesperre. Ein
    # lesender PHP-Prozess darf den Austausch erlauben und weiterarbeiten.
    Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;
public static class ZeitDateifreigabe {
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
    public static extern SafeFileHandle CreateFile(string name, uint access, uint share,
        IntPtr security, uint creation, uint flags, IntPtr template);
}
'@
    foreach ($Pfad in (Get-Content -LiteralPath $Liste -Raw -Encoding UTF8 | ConvertFrom-Json)) {
        $Datei = [ZeitDateifreigabe]::CreateFile($Pfad, 0x10000, 7, [IntPtr]::Zero, 3, 0x80, [IntPtr]::Zero)
        try {
            if ($Datei.IsInvalid) { throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error()) }
        } finally { $Datei.Dispose() }
    }
    @{ok=$true} | ConvertTo-Json -Compress
} catch {
    @{ok=$false;datei=$Pfad} | ConvertTo-Json -Compress
    exit 1
}
