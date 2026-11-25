param([ValidateSet('schedule','diagnose','https','rollback')][string]$Action='diagnose')
$ErrorActionPreference='Stop'
Set-Location $PSScriptRoot
function D { & docker compose @args; if($LASTEXITCODE -ne 0){throw "Compose error: $LASTEXITCODE"} }
if($Action -eq 'schedule'){
    $account=[Security.Principal.WindowsIdentity]::GetCurrent().Name
    $principal=New-ScheduledTaskPrincipal -UserId $account -LogonType Interactive -RunLevel Limited
    $start=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "'+$PSScriptRoot+'\autostart.ps1"')
    $backup=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "'+$PSScriptRoot+'\manage.ps1" -Action backup')
    $settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 3)
    Register-ScheduledTask -TaskName 'SeavanAcademy-Start' -Action $start -Trigger (New-ScheduledTaskTrigger -AtLogOn -User $account) -Principal $principal -Settings $settings -Force | Out-Null
    Register-ScheduledTask -TaskName 'SeavanAcademy-Backup' -Action $backup -Trigger (New-ScheduledTaskTrigger -Daily -At 3am) -Principal $principal -Settings $settings -Force | Out-Null
    Write-Host 'Registered logon startup and daily backup. Docker Desktop must run in this Windows account. Backups do not run while logged off or PC is powered off.'
    exit
}
if($Action -eq 'diagnose'){
    $dir=Join-Path $PSScriptRoot 'diagnostics'
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
    $file=Join-Path $dir ('health-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'.txt')
    & docker compose ps -a 2>&1 | Out-File $file
    & docker stats --no-stream --format '{{.Name}} {{.CPUPerc}} {{.MemUsage}}' 2>&1 | Out-File $file -Append
    & docker compose exec -T web df -h /var/moodledata /srv/seavan-courses /srv/seavan-courses2 /srv/seavan-courses3 2>&1 | Out-File $file -Append
    & docker compose exec -T web php -v 2>&1 | Out-File $file -Append
    & docker compose exec -T db healthcheck.sh --connect --innodb_initialized 2>&1 | Out-File $file -Append
    Get-Content $file
    Write-Host "Diagnostic report: $file. Review before sharing."
    exit
}
if($Action -eq 'https'){
    if(!(Test-Path .env)){throw 'Configure .env and internal DNS first; see docs/company-fa.md.'}
    $site=(Get-Content .env | Where-Object {$_ -like 'SITE_URL=*'}) -replace '^SITE_URL=',''
    if($site -notmatch '^https://[A-Za-z0-9.-]+$'){throw 'Set SITE_URL to the internal HTTPS hostname first.'}
    if(!(Get-Content .env | Where-Object {$_ -eq 'SSL_PROXY=1'})){throw 'Set SSL_PROXY=1 before starting HTTPS.'}
    D -f compose.yaml -f compose.https.yaml up -d --build
    Write-Host 'HTTPS started. Install only this server root CA on authorized clients after verifying its fingerprint.'
    exit
}
& (Join-Path $PSScriptRoot 'manage.ps1') -Action rollback
