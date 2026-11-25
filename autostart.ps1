$ErrorActionPreference='Stop'
Set-Location $PSScriptRoot
for($i=0;$i -lt 60;$i++){
    & docker info --format '{{.ServerVersion}}' 2>$null | Out-Null
    if($LASTEXITCODE -eq 0){break}
    Start-Sleep -Seconds 5
}
if($LASTEXITCODE -ne 0){throw 'Docker Desktop not available'}
if(!(Test-Path .env)){throw 'Run START.cmd once before autostart'}
& docker compose start
if($LASTEXITCODE -ne 0){throw 'Existing containers could not start; run START.cmd'}
