[CmdletBinding()]
param(
    [ValidateSet('Run', 'Start', 'Stop', 'Status', 'Sync')]
    [string]$Action = 'Run',
    [switch]$NoBrowser
)

Set-StrictMode -Version 3.0
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
if ($env:OS -ne 'Windows_NT' -or $env:PROCESSOR_ARCHITECTURE -ne 'AMD64') {
    throw 'Seavan Academy requires 64-bit Windows on an x64 processor.'
}

$Root = Split-Path -Parent $PSScriptRoot
$Site = Join-Path $Root 'moodle'
$State = Join-Path $Root 'local-data'
$Tools = Join-Path $State 'runtime'
$SettingsFile = Join-Path $State 'settings.json'
$ProcessFile = Join-Path $State 'processes.json'
$Installed = Join-Path $State 'installed'
$PhpIni = Join-Path $State 'php.ini'
$WebAddress = 'http://127.0.0.1:8080'
$env:SEAVAN_NATIVE_STATE = $State
$env:SEAVAN_BIND_IP = '127.0.0.1'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Utf8 = New-Object System.Text.UTF8Encoding($false)
$Services = [ordered]@{}
$Ports = @{ db = 9336; php1 = 9081; php2 = 9082; web = 8080 }

function Write-LocalText([string]$Path, [string]$Content) {
    [IO.File]::WriteAllText($Path, $Content, $Utf8)
}

function Protect-LocalState {
    [void][IO.Directory]::CreateDirectory($State)
    $attributes = [IO.File]::GetAttributes($State)
    if ($attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'The local-data directory must be a regular directory on an NTFS drive.'
    }
    $acl = Get-Acl -LiteralPath $State
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($rule in @($acl.Access)) { $acl.RemoveAccessRuleAll($rule) }
    $current = [Security.Principal.WindowsIdentity]::GetCurrent().User
    foreach ($id in @($current.Value, 'S-1-5-18', 'S-1-5-32-544')) {
        $sid = New-Object Security.Principal.SecurityIdentifier($id)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule(
            $sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'
        )
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $State -AclObject $acl
}

function New-Secret {
    $bytes = New-Object byte[] 24
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return 'Aa9!' + [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
}

function Get-Executable([string]$Name) {
    $item = $Dependencies.$Name
    return Join-Path $Tools "$Name-$($item.version)/$($item.executable)"
}

function Get-Runtimes {
    [void][IO.Directory]::CreateDirectory($Tools)
    foreach ($name in @('php', 'mariadb', 'caddy')) {
        if (Test-Path -LiteralPath (Get-Executable $name) -PathType Leaf) { continue }
        $item = $Dependencies.$name
        $dest = Join-Path $Tools "$name-$($item.version)"
        $archive = "$dest.zip"
        $stage = "$dest.stage"
        if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
        [void][IO.Directory]::CreateDirectory($stage)
        try {
            Write-Host "Preparing $name $($item.version)..."
            $bundled = Join-Path $PSScriptRoot "offline/$name.zip"
            if (Test-Path -LiteralPath $bundled -PathType Leaf) {
                $inputArchive = $bundled
            } else {
                $inputArchive = $archive
                if (!(Test-Path -LiteralPath $archive) -or (Get-FileHash -LiteralPath $archive).Hash -ne $item.sha256) {
                    Invoke-WebRequest -Uri $item.url -UseBasicParsing -OutFile $archive
                }
            }
            if ((Get-FileHash -LiteralPath $inputArchive).Hash -ne $item.sha256) {
                throw "SHA-256 verification failed for $name."
            }
            & "$env:SystemRoot\System32\tar.exe" -xf $inputArchive -C $stage
            if ($LASTEXITCODE -ne 0) { throw "Could not unpack $name." }
            $source = $stage
            if ($name -eq 'mariadb') { $source = Join-Path $stage "mariadb-$($item.version)-winx64" }
            if (!(Test-Path -LiteralPath (Join-Path $source $item.executable))) {
                throw "Unexpected $name archive layout."
            }
            if (Test-Path -LiteralPath $dest) { throw "Incomplete runtime directory: $dest" }
            Move-Item -LiteralPath $source -Destination $dest
        } finally {
            if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
            if (Test-Path -LiteralPath $archive) { Remove-Item -LiteralPath $archive -Force }
        }
    }
}

function Invoke-Checked([string]$Executable, [string[]]$Arguments, [string]$Label) {
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) { throw "$Label failed (exit $LASTEXITCODE)." }
}

function Invoke-MoodlePhp([string[]]$Arguments, [string]$Label) {
    Invoke-Checked (Get-Executable 'php') (@('-c', $PhpIni) + $Arguments) $Label
}

function Quote-Arguments([string[]]$Arguments) {
    $quoted = foreach ($arg in $Arguments) {
        '"' + ($arg -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1') + '"'
    }
    return $quoted -join ' '
}

function Write-ProcessList {
    Write-LocalText "$ProcessFile.tmp" ($Services | ConvertTo-Json -Depth 4)
    if (Test-Path -LiteralPath $ProcessFile) {
        [IO.File]::Replace("$ProcessFile.tmp", $ProcessFile, "$ProcessFile.previous")
        Remove-Item -LiteralPath "$ProcessFile.previous" -Force
    } else {
        [IO.File]::Move("$ProcessFile.tmp", $ProcessFile)
    }
}

function Read-ProcessList {
    if (!(Test-Path -LiteralPath $ProcessFile)) { return }
    $saved = Get-Content -LiteralPath $ProcessFile -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($property in $saved.PSObject.Properties) {
        $Services[$property.Name] = $property.Value
    }
}

function Get-OwnedProcess([string]$Name) {
    if (!$Services.Contains($Name)) { return }
    $item = $Services[$Name]
    $process = Get-Process -Id $item.id -ErrorAction SilentlyContinue
    if ($process -and $process.Path -eq $item.path -and [string]$process.StartTime.ToFileTimeUtc() -eq $item.started) {
        return $process
    }
}

function Start-OwnedProcess([string]$Name, [string]$Exe, [string[]]$Arguments) {
    $stdout = Join-Path $State "$Name.out.log"
    $stderr = Join-Path $State "$Name.err.log"
    $process = Start-Process -FilePath $Exe -ArgumentList (Quote-Arguments $Arguments) -PassThru -WindowStyle Hidden -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    $Services[$Name] = @{
        id = $process.Id
        path = $Exe
        started = [string]$process.StartTime.ToFileTimeUtc()
    }
    Write-ProcessList
}

function Test-LocalPort([int]$Port) {
    $client = New-Object Net.Sockets.TcpClient
    try {
        $attempt = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (!$attempt.AsyncWaitHandle.WaitOne(250)) { return $false }
        $client.EndConnect($attempt)
        return $true
    } catch [Net.Sockets.SocketException] {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Wait-LocalPort([string]$Name) {
    $deadline = [DateTime]::UtcNow.AddSeconds(50)
    do {
        if (!(Get-OwnedProcess $Name)) { throw "$Name stopped. See local-data/$Name.err.log." }
        if (Test-LocalPort $Ports[$Name]) { return }
        Start-Sleep -Milliseconds 350
    } while ([DateTime]::UtcNow -lt $deadline)
    throw "$Name did not open its local port. See local-data/$Name.err.log."
}

function Stop-Academy {
    foreach ($name in @('web', 'cron', 'php1', 'php2', 'db')) {
        $process = Get-OwnedProcess $name
        if (!$process) { continue }
        if ($name -eq 'db' -and (Test-Path -LiteralPath (Join-Path $State 'db-client.ini'))) {
            $admin = Join-Path (Split-Path -Parent (Get-Executable 'mariadb')) 'mariadb-admin.exe'
            & $admin "--defaults-file=$State/db-client.ini" shutdown
            if ($LASTEXITCODE -eq 0 -and $process.WaitForExit(12000)) { continue }
        }
        Stop-Process -InputObject $process -Force -ErrorAction SilentlyContinue
        [void]$process.WaitForExit(5000)
    }
    $Services.Clear()
    Write-ProcessList
}

function Write-Configuration {
    $dbdir = (Split-Path -Parent (Split-Path -Parent (Get-Executable 'mariadb'))).Replace('\', '/')
    $phpdir = (Split-Path -Parent (Get-Executable 'php')).Replace('\', '/')
    $private = $State.Replace('\', '/')
    $webroot = $Site.Replace('\', '/')
    @('data', 'data/lang', 'data/seavan-uploads', 'tmp') | ForEach-Object {
        [void][IO.Directory]::CreateDirectory((Join-Path $State $_))
    }
    $languageSource = Join-Path $PSScriptRoot 'lang/fa'
    $languageDest = Join-Path $State 'data/lang/fa'
    if (!(Test-Path -LiteralPath $languageDest) -and (Test-Path -LiteralPath $languageSource)) {
        Copy-Item -LiteralPath $languageSource -Destination $languageDest -Recurse
    }

    Write-LocalText "$State/my.ini" @"
[mysqld]
basedir="$dbdir"
datadir="$private/db"
bind-address=127.0.0.1
port=9336
skip-name-resolve
performance_schema=OFF
innodb_buffer_pool_size=64M
aria_pagecache_buffer_size=16M
key_buffer_size=8M
max_connections=12
max_allowed_packet=64M
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
"@
    Write-LocalText "$State/db-client.ini" @"
[client]
host=127.0.0.1
port=9336
protocol=tcp
user=root
password=$($Config.rootPassword)
"@
    Write-LocalText $PhpIni @"
extension_dir="$phpdir/ext"
extension=curl
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=soap
extension=sodium
extension=zip
extension=fileinfo
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=64
opcache.max_accelerated_files=12000
opcache.enable_file_override=0
cgi.force_redirect=0
cgi.fix_pathinfo=1
expose_php=Off
display_errors=Off
log_errors=On
error_log="$private/php.log"
memory_limit=256M
max_input_vars=5000
post_max_size=64M
upload_max_filesize=60M
upload_tmp_dir="$private/tmp"
sys_temp_dir="$private/tmp"
date.timezone=Asia/Tehran
"@
    Write-LocalText "$State/Caddyfile" @"
{
    admin off
    auto_https off
    persist_config off
}
http://127.0.0.1:8080 {
    bind 127.0.0.1
    root * "$webroot"
    @private path_regexp private (?i)(^|/)([.][^/]*|(?:cli|tests|vendor|fixtures|node_modules)(?:/|$)|(?:config[.]php|composer[.](?:json|lock)|phpunit[.]xml[.]dist)(?:/|$))
    respond @private 404
    php_fastcgi 127.0.0.1:9081 127.0.0.1:9082 {
        lb_policy least_conn
        lb_try_duration 2s
        read_timeout 5m
    }
    file_server
}
"@
    $entry = Join-Path $Site 'config.php'
    $template = Join-Path $PSScriptRoot 'moodle-entry.php'
    if ((Test-Path -LiteralPath $entry) -and
        ([IO.File]::ReadAllText($entry) -ne [IO.File]::ReadAllText($template))) {
        throw 'An existing Moodle config.php belongs to another installation. Extract the ZIP into a separate folder.'
    }
    Copy-Item -LiteralPath $template -Destination $entry -Force
}

function Test-Web {
    $result = Invoke-WebRequest -Uri "$WebAddress/login/index.php" -UseBasicParsing -TimeoutSec 90
    if ($result.StatusCode -ne 200 -or $result.Content -notmatch 'name="logintoken"') {
        throw 'Moodle did not show the login form. See local-data/php.log.'
    }
}

function Start-Academy {
    $running = @('db', 'php1', 'php2', 'web') | Where-Object { Get-OwnedProcess $_ }
    if (@($running).Count -eq 4 -and (Test-Path -LiteralPath $Installed)) {
        Test-Web
        return
    }
    Stop-Academy
    foreach ($port in $Ports.Values) {
        if (Test-LocalPort $port) { throw "Local port $port is already in use. Close the other service and start again." }
    }
    Get-Runtimes
    $php = Get-Executable 'php'
    try { Invoke-Checked $php @('-n', '-v') 'Windows PHP' | Out-Null } catch {
        throw 'PHP could not run. Install the Microsoft x64 Visual C++ Redistributable at https://aka.ms/vc14/vc_redist.x64.exe and retry.'
    }
    if (!(Test-Path -LiteralPath $SettingsFile)) {
        $courses = Join-Path $Root 'courses'
        [void][IO.Directory]::CreateDirectory($courses)
        Write-LocalText $SettingsFile (@{
            rootPassword = New-Secret
            databasePassword = New-Secret
            bootstrapPassword = New-Secret
            courseFolder = 'courses'
        } | ConvertTo-Json)
    }
    $Config = Get-Content -LiteralPath $SettingsFile -Raw -Encoding UTF8 | ConvertFrom-Json
    Write-Configuration
    $database = Get-Executable 'mariadb'
    $dbdir = Join-Path $State 'db'
    if (!(Test-Path -LiteralPath (Join-Path $dbdir 'mysql') -PathType Container)) {
        Write-Host 'Initializing the local database...'
        $init = Join-Path (Split-Path -Parent $database) 'mariadb-install-db.exe'
        Invoke-Checked $init @("--datadir=$dbdir", "--port=$($Ports.db)", "--password=$($Config.rootPassword)") 'MariaDB initialization'
    }

    try {
        Start-OwnedProcess 'db' $database @("--defaults-file=$State/my.ini", '--console')
        Wait-LocalPort 'db'
        if (!(Test-Path -LiteralPath $Installed)) {
            Write-Host 'Installing Moodle. The first run can take a few minutes...'
            $provision = Join-Path $PSScriptRoot 'database.php'
            Invoke-MoodlePhp @($provision, 'provision') 'Moodle database provisioning'
            $stateOfDb = Invoke-MoodlePhp @($provision, 'status') 'Moodle database check'
            if ($stateOfDb -eq 'empty') {
                $persianName = [string]::Concat([char[]](1570,1705,1575,1583,1605,1740,32,1587,1740,1608,1575,1606))
                Invoke-MoodlePhp @("$Site/admin/cli/install_database.php", '--agree-license', '--lang=en', "--fullname=$persianName", "--shortname=$persianName", '--adminuser=admin', "--adminpass=$($Config.bootstrapPassword)", '--adminemail=admin@example.invalid') 'Moodle install'
            }
            Invoke-MoodlePhp @("$PSScriptRoot/bootstrap-locale.php") 'Persian locale'
            Invoke-MoodlePhp @("$Site/local/seavan/cli/setup_categories.php") 'Course categories'
            foreach ($option in @('theme=seavan', 'guestloginbutton=0', 'defaulthomepage=/my/index.php')) {
                $name, $value = $option.Split('=', 2)
                Invoke-MoodlePhp @("$Site/admin/cli/cfg.php", "--name=$name", "--set=$value") 'Academy configuration'
            }
            $env:SEAVAN_BOOTSTRAP_LOCAL = '1'
            $env:ADMIN_PASSWORD = $Config.bootstrapPassword
            try {
                Invoke-MoodlePhp @("$Site/local/seavan/cli/first_login.php", '--initialize', '--if-needed') 'First administrator login'
            } finally {
                Remove-Item Env:ADMIN_PASSWORD, Env:SEAVAN_BOOTSTRAP_LOCAL -ErrorAction SilentlyContinue
            }
            Write-LocalText $Installed '1'
        }
        if ($Config.PSObject.Properties.Name -contains 'bootstrapPassword') {
            $Config.PSObject.Properties.Remove('bootstrapPassword')
            Write-LocalText $SettingsFile ($Config | ConvertTo-Json)
        }

        $phpCgi = Join-Path (Split-Path -Parent $php) 'php-cgi.exe'
        $env:PHP_FCGI_MAX_REQUESTS = '0'
        foreach ($name in @('php1', 'php2')) {
            Start-OwnedProcess $name $phpCgi @('-c', $PhpIni, '-b', "127.0.0.1:$($Ports[$name])")
            Wait-LocalPort $name
        }
        Start-OwnedProcess 'web' (Get-Executable 'caddy') @('run', '--config', "$State/Caddyfile", '--adapter', 'caddyfile')
        Wait-LocalPort 'web'
        Test-Web
        $synced = Join-Path $State 'windows-1.3-library-synced'
        if (!(Test-Path -LiteralPath $synced)) {
            Write-Host 'Synchronizing existing course folders with Moodle...'
            $lines = @(Invoke-MoodlePhp @("$PSScriptRoot/import-courses.php") 'Course import')
            $lines | Write-Host
            $errorPrefix = -join ([char[]](1582,1591,1575))
            if ($lines -match "^$errorPrefix ") {
                Write-Warning 'One or more folders could not be synchronized. Review the import output and use IMPORT-COURSES.cmd to retry.'
            } else {
                Write-LocalText $synced '1'
            }
        }
    } catch {
        Stop-Academy
        throw
    }
}

$Dependencies = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'dependencies.json') -Raw | ConvertFrom-Json
if ($Action -in @('Status', 'Stop') -and !(Test-Path -LiteralPath $State)) {
    Write-Host 'Seavan Academy is stopped.'
    exit 0
}
if ($Action -in @('Start', 'Run')) { Protect-LocalState }
$guard = [IO.File]::Open((Join-Path $State 'control.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
try {
    Read-ProcessList
    switch ($Action) {
        'Status' {
            if (Get-OwnedProcess 'web') { Write-Host $WebAddress } else { Write-Host 'Seavan Academy is stopped.' }
        }
        'Stop' { Stop-Academy; Write-Host 'Seavan Academy is stopped.' }
        'Sync' {
            if (!(Get-OwnedProcess 'db')) { throw 'Start Seavan Academy before importing the course folders.' }
            Invoke-MoodlePhp @("$PSScriptRoot/import-courses.php") 'Course import'
        }
        default {
            Start-Academy
            Write-Host "Seavan Academy is ready at $WebAddress"
            if (!$NoBrowser) { Start-Process $WebAddress }
        }
    }
} finally {
    $guard.Dispose()
}

if ($Action -eq 'Run') {
    $nextCron = [DateTime]::UtcNow
    Write-Host 'Keep this window open. Use STOP.cmd to shut down.'
    while ((Get-OwnedProcess 'web') -and (Get-OwnedProcess 'db')) {
        if ([DateTime]::UtcNow -ge $nextCron -and !(Get-OwnedProcess 'cron')) {
            try {
                $guard = [IO.File]::Open((Join-Path $State 'control.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
            } catch [IO.IOException] {
                Start-Sleep -Seconds 2
                continue
            }
            try {
                $Services.Clear()
                Read-ProcessList
                if ((Get-OwnedProcess 'web') -and !(Get-OwnedProcess 'cron')) {
                    Start-OwnedProcess 'cron' (Get-Executable 'php') @('-c', $PhpIni, "$Site/admin/cli/cron.php", '--keep-alive=0')
                }
            } finally {
                $guard.Dispose()
            }
            $nextCron = [DateTime]::UtcNow.AddMinutes(5)
        }
        Start-Sleep -Seconds 3
    }
}
