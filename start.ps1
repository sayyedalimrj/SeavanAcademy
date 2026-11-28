param([switch]$NonInteractive, [switch]$NoBrowser, [switch]$PrepareOnly)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function New-AcademySecret {
    $bytes = New-Object byte[] 24
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return 'Aa1!' + ([BitConverter]::ToString($bytes).Replace('-', ''))
}

function New-PrivateSettingsFile([string[]]$Lines) {
    $temporary = Join-Path $PSScriptRoot ('.env.initial-' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType File -Path $temporary | Out-Null
    try {
        if ($env:OS -eq 'Windows_NT') {
            $acl = Get-Acl -LiteralPath $temporary
            $acl.SetAccessRuleProtection($true, $false)
            $identities = @([Security.Principal.WindowsIdentity]::GetCurrent().User,
                (New-Object Security.Principal.SecurityIdentifier('S-1-5-18')),
                (New-Object Security.Principal.SecurityIdentifier('S-1-5-32-544')))
            foreach ($identity in $identities) {
                $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'Allow')
                $acl.SetAccessRule($rule)
            }
            Set-Acl -LiteralPath $temporary -AclObject $acl
        } else {
            & chmod 600 $temporary
            if ($LASTEXITCODE -ne 0) { throw 'Cannot protect the settings file.' }
        }
        [IO.File]::WriteAllLines($temporary, $Lines, (New-Object Text.UTF8Encoding($false)))
        [IO.File]::Move($temporary, (Join-Path $PSScriptRoot '.env'))
    } finally {
        if (Test-Path -LiteralPath $temporary) { Remove-Item -LiteralPath $temporary -Force }
    }
}

if (!(Test-Path .env)) {
    $email = 'admin@example.invalid'
    $folder = ''
    if (!$NonInteractive) {
        $suppliedEmail = Read-Host 'Administrator email (Enter to use a temporary local address)'
        if ($suppliedEmail) { $email = $suppliedEmail.Trim() }
        $folder = Read-Host 'Course folder (Enter to use the included courses folder)'
    }
    if ($email -notmatch '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$') { throw 'Invalid email.' }
    $lines = @('BIND_IP=127.0.0.1', 'SITE_URL=http://127.0.0.1:8080', 'SEAVAN_BOOTSTRAP_LOCAL=1', 'SEAVAN_PROTECTED_MEDIA=1',
        "ADMIN_EMAIL=$email", "ADMIN_PASSWORD=$(New-AcademySecret)", "DB_PASSWORD=$(New-AcademySecret)",
        "DB_ROOT_PASSWORD=$(New-AcademySecret)")
    if ($folder) {
        if (!(Test-Path -LiteralPath $folder -PathType Container)) { throw 'Course folder does not exist.' }
        $folder = (Resolve-Path -LiteralPath $folder).Path.Replace('\','/')
        if ($folder -match "['\r\n]") { throw 'Unsupported path character.' }
        $lines += "COURSES_DIR='$folder'"
    }
    New-PrivateSettingsFile $lines
    Write-Host 'First sign-in is local only. Username: admin  |  Temporary password: admin'
}
New-Item -ItemType Directory -Path courses,courses2,courses3 -Force | Out-Null
if ($PrepareOnly) { exit }

if (!(Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Install Docker Desktop with WSL 2 and Linux containers, then run START.cmd again.'
}
$engine = & docker info --format '{{.OSType}}' 2>$null
if ($LASTEXITCODE -ne 0 -and $env:OS -eq 'Windows_NT') {
    $desktop = Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'
    if (Test-Path -LiteralPath $desktop) {
        Start-Process -FilePath $desktop
        Write-Host 'Waiting for Docker Desktop...'
        for ($attempt = 0; $attempt -lt 45; $attempt++) {
            Start-Sleep -Seconds 2
            $engine = & docker info --format '{{.OSType}}' 2>$null
            if ($LASTEXITCODE -eq 0) { break }
        }
    }
}
if ($LASTEXITCODE -ne 0 -or $engine -ne 'linux') { throw 'Start Docker Desktop and select Linux containers.' }

$extra = @()
if ((Test-Path compose.https.yaml) -and (Get-Content .env | Where-Object {$_ -like 'ACADEMY_HOST=*'})) {
    $extra = @('-f','compose.yaml','-f','compose.https.yaml')
}
& docker compose @extra up -d --build
if ($LASTEXITCODE -ne 0) {
    & docker compose @extra logs --tail 30 init
    throw 'Academy startup failed. See the initialization output above.'
}
$url = (Get-Content .env | Where-Object {$_ -like 'SITE_URL=*'}) -replace '^SITE_URL=', ''
$login = $url.TrimEnd('/') + '/login/index.php'
$ready = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    try {
        $response = Invoke-WebRequest -Uri $login -UseBasicParsing -TimeoutSec 5
        if ($response.Content -match 'name="logintoken"') { $ready = $true; break }
    } catch {
        Start-Sleep -Seconds 2
    }
}
if (!$ready) { throw "Moodle login did not become available at $login. Run DIAGNOSE.cmd to inspect the installation." }
Write-Host "Academy ready: $login"
if (!$NoBrowser) { Start-Process $login }
