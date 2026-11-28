param([string]$AcademyPath = (Split-Path -Parent $PSScriptRoot))
Set-StrictMode -Version 3.0
$ErrorActionPreference = 'Stop'
$root = (Get-Item -LiteralPath $AcademyPath).FullName
$launcher = Join-Path $root 'windows-native/academy.ps1'
$revisionFile = Join-Path $root 'RELEASE-COMMIT.txt'
if (!(Test-Path -LiteralPath $launcher -PathType Leaf) -or !(Test-Path -LiteralPath $revisionFile -PathType Leaf)) {
    throw 'Extract the patch folder next to START.cmd inside the Seavan Academy installation.'
}
$manifest = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'manifest.json') -Raw | ConvertFrom-Json
$current = (Get-Content -LiteralPath $revisionFile -Raw).Trim()
if ($current -notin @($manifest.base, $manifest.release)) {
    throw "This patch requires the Seavan Academy 1.2 Windows Native package. Found release $current."
}
$changed = @()
foreach ($file in $manifest.files) {
    if ($file.path -cnotmatch '^moodle/(?:course/view[.]php|(?:local/seavan(?:media)?/|theme/seavan/)[a-z0-9_/]+[.](?:php|css|js))$' -and $file.path -cne 'windows-native/academy.ps1') {
        throw "Unexpected patch path: $($file.path)"
    }
    $source = Join-Path (Join-Path $PSScriptRoot 'payload') $file.path
    $target = Join-Path $root $file.path
    if ((Get-FileHash -LiteralPath $source).Hash -ne $file.after) {
        throw "The patch file failed integrity verification: $($file.path)"
    }
    $exists = Test-Path -LiteralPath $target -PathType Leaf
    $hash = if ($exists) { (Get-FileHash -LiteralPath $target).Hash } else { '' }
    if ($hash -eq $file.after) { continue }
    if ($hash -ne $file.before) {
        throw "The installation file has local changes: $($file.path)"
    }
    $changed += $file
}
if (!$changed.Count -and $current -eq $manifest.release) {
    Write-Host 'Seavan Academy 1.3 patch is already installed.'
    exit 0
}
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $launcher -Action Stop
if ($LASTEXITCODE -ne 0) { throw 'Could not stop Seavan Academy. No changes have been copied.' }

$state = Join-Path $root 'local-data'
$guard = $null
if (Test-Path -LiteralPath $state -PathType Container) {
    $guard = [IO.File]::Open((Join-Path $state 'control.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
}
try {
    $processFile = Join-Path $state 'processes.json'
    if (Test-Path -LiteralPath $processFile) {
        $running = Get-Content -LiteralPath $processFile -Raw | ConvertFrom-Json
        if ($running -and @($running.PSObject.Properties | Where-Object { $_.Name -in 'db', 'web', 'php1', 'php2', 'cron' }).Count) {
            throw 'Seavan Academy started again. Stop it before retrying the patch.'
        }
    }
    $backup = Join-Path $root ('patch-backups/1.3-' + [DateTime]::Now.ToString('yyyyMMdd-HHmmssfff'))
    [void][IO.Directory]::CreateDirectory($backup)
    Copy-Item -LiteralPath $revisionFile -Destination (Join-Path $backup 'RELEASE-COMMIT.txt')
    $applied = @()
    try {
        foreach ($file in $changed) {
            $source = Join-Path (Join-Path $PSScriptRoot 'payload') $file.path
            $target = Join-Path $root $file.path
            if ($file.before) {
                $saved = Join-Path $backup $file.path
                [void][IO.Directory]::CreateDirectory((Split-Path -Parent $saved))
                Copy-Item -LiteralPath $target -Destination $saved
            }
            $applied += $file
            Copy-Item -LiteralPath $source -Destination $target -Force
            if ((Get-FileHash -LiteralPath $target).Hash -ne $file.after) { throw "Copy failed: $($file.path)" }
        }
        [IO.File]::WriteAllText($revisionFile, $manifest.release + [Environment]::NewLine)
    } catch {
        $failure = $_
        foreach ($file in $applied) {
            $target = Join-Path $root $file.path
            if ($file.before) {
                Copy-Item -LiteralPath (Join-Path $backup $file.path) -Destination $target -Force
            } else {
                Remove-Item -LiteralPath $target -Force -ErrorAction SilentlyContinue
            }
        }
        Copy-Item -LiteralPath (Join-Path $backup 'RELEASE-COMMIT.txt') -Destination $revisionFile -Force
        throw $failure
    }
} finally {
    if ($guard) { $guard.Dispose() }
}
Write-Host 'Seavan Academy 1.3 patch installed. Open START.cmd.'
