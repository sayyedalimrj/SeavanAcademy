param([ValidateSet('backup','restore','update','whisper','rollback')][string]$Action='backup',[string]$Snapshot='')
$ErrorActionPreference='Stop'
Set-Location $PSScriptRoot
function D { $extra=@(); if((Test-Path .env) -and (Test-Path compose.https.yaml) -and (Get-Content .env | Where-Object {$_ -like 'ACADEMY_HOST=*'})){ $extra=@('-f','compose.yaml','-f','compose.https.yaml') }; & docker compose @extra @args; if($LASTEXITCODE -ne 0){throw "Docker Compose failed ($LASTEXITCODE)"} }
function Set-AcademyMaintenance([bool]$Enabled) {
    $argument = if ($Enabled) { '--enable' } else { '--disable' }
    D exec -T -u www-data web php /var/www/html/admin/cli/maintenance.php $argument
}
function StopWorkers {
    $script:previousWorkers=@(D --profile whisper ps --services --status running | Where-Object {$_ -in @('cron','processor','whisper')})
    D --profile whisper stop cron processor whisper
}
function StartWorkers { D up -d --no-build --no-deps cron processor; if($script:previousWorkers -contains 'whisper'){D --profile whisper up -d --no-build --no-deps whisper} }
function Backup {
    $dest=Join-Path $PSScriptRoot (Join-Path "backups" ("snapshot-"+(Get-Date -Format 'yyyyMMdd-HHmmss-fff')))
    New-Item -ItemType Directory -Path $dest -Force | Out-Null
    Set-AcademyMaintenance $true
    try {
        StopWorkers
        D exec -T db sh -c 'set -e; mariadb-dump --single-transaction --quick -uroot -p"$MARIADB_ROOT_PASSWORD" moodle > /tmp/seavan-snapshot.sql; gzip -f /tmp/seavan-snapshot.sql'
        D cp 'db:/tmp/seavan-snapshot.sql.gz' (Join-Path $dest 'database.sql.gz')
        D exec -T db rm -f /tmp/seavan-snapshot.sql.gz
        D run --rm --no-deps -v "$($dest):/backup" --entrypoint sh web -c 'tar -C /var/moodledata --exclude=./cache --exclude=./localcache --exclude=./temp --exclude=./sessions --exclude=./seavan-whisper-model -czf /backup/moodledata.tar.gz .'
        D run --rm --no-deps -v "$($dest):/backup:ro" --entrypoint sh web -c 'gzip -t /backup/database.sql.gz && tar -tzf /backup/moodledata.tar.gz >/dev/null'
        Copy-Item .env (Join-Path $dest 'private.env')
        $revision=(& git rev-parse HEAD).Trim()
        if($LASTEXITCODE -ne 0){throw 'Cannot determine source revision'}
        $revision | Set-Content -Encoding ASCII (Join-Path $dest 'source-commit.txt')
        $hashes=@{}
        foreach($name in @('database.sql.gz','moodledata.tar.gz','private.env','source-commit.txt')){
            $hashes[$name]=(Get-FileHash (Join-Path $dest $name) -Algorithm SHA256).Hash
        }
        $hashes | ConvertTo-Json | Set-Content -Encoding ASCII (Join-Path $dest 'checksums.json')
    } finally {
        try { Set-AcademyMaintenance $false } finally { StartWorkers }
    }
    Write-Host "Private backup: $dest"
}
if(!(Test-Path .env)){throw 'First run START.cmd'}
if($Action -eq 'backup'){Backup;exit}
if($Action -eq 'whisper'){D --profile whisper up -d --build whisper;exit}
if($Action -eq 'update'){
    Backup
    & git pull --ff-only
    if($LASTEXITCODE -ne 0){throw 'Git update failed. Backup remains available.'}
    D build web
    D up -d --build
    exit
}
$source=$Snapshot
if(!$source){$source=Read-Host 'Path to snapshot directory (created by BACKUP.cmd)'}
$source=(Resolve-Path $source).Path
$manifest=Get-Content (Join-Path $source 'checksums.json') -Raw | ConvertFrom-Json
foreach($name in @('database.sql.gz','moodledata.tar.gz','private.env','source-commit.txt')){
    $actual=(Get-FileHash (Join-Path $source $name) -Algorithm SHA256).Hash
    if($actual -ne $manifest.$name){throw "Checksum mismatch: $name"}
}
if((Get-FileHash .env).Hash -ne (Get-FileHash (Join-Path $source 'private.env')).Hash){throw 'Restore requires the original matching .env and server configuration.'}
$revision=(Get-Content (Join-Path $source 'source-commit.txt') -Raw).Trim()
if($revision -notmatch '^[0-9a-f]{40}$'){throw 'Invalid snapshot source commit'}
$current=(& git rev-parse HEAD).Trim()
if($Action -eq 'restore' -and $current -ne $revision){throw 'Snapshot source differs. Use ROLLBACK.cmd.'}
if($Action -eq 'rollback'){
    & git cat-file -e "$revision^{commit}"
    if($LASTEXITCODE -ne 0){throw 'Snapshot source not found in local Git history'}
    if((& git status --porcelain --untracked-files=no)){throw 'Tracked source has local modifications'}
}
if((Read-Host 'Restore replaces current database and Moodledata. Type RESTORE to confirm') -ne 'RESTORE'){exit}
Backup
Set-AcademyMaintenance $true
StopWorkers
D stop web
if($Action -eq 'rollback' -and $current -ne $revision){
    & git switch --detach $revision
    if($LASTEXITCODE -ne 0){throw 'Source switch failed. Current data has not been replaced.'}
    D build web
}
D run --rm --no-deps -v "$($source):/backup:ro" --entrypoint sh web -c 'gzip -t /backup/database.sql.gz && tar -tzf /backup/moodledata.tar.gz >/dev/null'
D cp (Join-Path $source 'database.sql.gz') 'db:/tmp/seavan-restore.sql.gz'
D exec -T db sh -c 'set -e; gzip -dc /tmp/seavan-restore.sql.gz > /tmp/seavan-restore.sql; mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE moodle; CREATE DATABASE moodle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" moodle < /tmp/seavan-restore.sql; rm /tmp/seavan-restore.sql /tmp/seavan-restore.sql.gz'
D run --rm --no-deps -v "$($source):/backup:ro" --entrypoint sh web -c 'set -e; find /var/moodledata -mindepth 1 -maxdepth 1 ! -name seavan-whisper-model -exec rm -rf {} +; tar -C /var/moodledata -xzf /backup/moodledata.tar.gz; chown -R www-data:www-data /var/moodledata'
D run --rm --no-deps init
D up -d --no-build --no-deps web
D exec -T -u www-data web php /var/www/html/admin/cli/purge_caches.php
Set-AcademyMaintenance $false
StartWorkers
Write-Host 'Restore completed. Original media must still be present in the configured course folder.'
