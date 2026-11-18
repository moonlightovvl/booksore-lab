$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot

function Find-LabBinary([string]$Directory, [string]$Name) {
    $match = @(Get-ChildItem -LiteralPath $Directory -Recurse -File -Filter $Name | Where-Object { $_.FullName -notmatch 'ospanel_data|debug' })
    if ($match.Count -ne 1) { throw "Expected one $Name under $Directory, found $($match.Count)." }
    return $match[0].FullName
}
function Write-Utf8([string]$Path, [string]$Text) {
    [System.IO.File]::WriteAllText($Path, $Text, [System.Text.UTF8Encoding]::new($false))
}
function Test-LabPort([int]$Port) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}
function Wait-LabPort([int]$Port) {
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        if (Test-LabPort $Port) { return }
        Start-Sleep -Milliseconds 300
    }
    throw "Port $Port did not start. See runtime/logs."
}
function Load-LabSettings {
    $file = Join-Path $ProjectRoot 'runtime\settings.json'
    if (!(Test-Path -LiteralPath $file)) { throw 'Run SETUP-LAB.cmd first.' }
    return Get-Content -LiteralPath $file -Raw -Encoding UTF8 | ConvertFrom-Json
}
function Start-LabProcess([string]$Name, [string]$Executable, [string[]]$Arguments, [string]$WorkingDirectory) {
    $logs = Join-Path $ProjectRoot 'runtime\logs'
    $process = Start-Process -FilePath $Executable -ArgumentList $Arguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logs "$Name-stdout.log") -RedirectStandardError (Join-Path $logs "$Name-stderr.log")
    $record = @{ id = $process.Id; path = $Executable; started = $process.StartTime.ToUniversalTime().ToString('o') }
    Write-Utf8 (Join-Path $ProjectRoot "runtime\$Name-process.json") ($record | ConvertTo-Json)
    return $process
}

function Stop-LabProcess([string]$Name) {
    $file = Join-Path $ProjectRoot "runtime\$Name-process.json"
    if (!(Test-Path -LiteralPath $file)) { return }
    $record = Get-Content -LiteralPath $file -Raw -Encoding UTF8 | ConvertFrom-Json
    $process = Get-Process -Id $record.id -ErrorAction SilentlyContinue
    if (!$process) { return }
    $recordedStart = ([DateTime]$record.started).ToUniversalTime()
    if ($process.Path -ne $record.path -or $process.StartTime.ToUniversalTime().Ticks -ne $recordedStart.Ticks) {
        throw "Process identity changed for $Name. It was not stopped."
    }
    # Apache on Windows has a child process and is intentionally not a system service.
    $children = @(Get-CimInstance Win32_Process -Filter ("ParentProcessId=" + $process.Id) | Where-Object { $_.ExecutablePath -eq $record.path })
    Stop-Process -Id $process.Id
    foreach ($child in $children) {
        $candidate = Get-Process -Id $child.ProcessId -ErrorAction SilentlyContinue
        if ($candidate -and $candidate.Path -eq $record.path) { Stop-Process -Id $candidate.Id }
    }
}
