$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
function Check-Exit { if ($LASTEXITCODE -ne 0) { throw "Command failed: $LASTEXITCODE" } }
function New-Secret {
  $bytes = New-Object byte[] 24
  $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
  try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
  return 'Aa1!' + ([BitConverter]::ToString($bytes).Replace('-', ''))
}
docker info --format '{{.ServerVersion}}'
Check-Exit
docker compose version
Check-Exit
if (!(Test-Path .env)) {
  $ip = Read-Host 'Server IPv4 address (Enter = local test 127.0.0.1)'
  if (!$ip) { $ip = '127.0.0.1' }
  $parsed = $null
  if (![Net.IPAddress]::TryParse($ip, [ref]$parsed) -or $parsed.AddressFamily -ne 'InterNetwork' -or $ip -eq '0.0.0.0') { throw 'Enter a valid specific IPv4 address.' }
  $email = Read-Host 'Administrator email (Enter = admin@example.invalid)'
  if (!$email) { $email = 'admin@example.invalid' }
  if ($email -notmatch '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$') { throw 'Invalid email.' }
  $admin = New-Secret
  @("BIND_IP=$ip","SITE_URL=http://${ip}:8080","ADMIN_EMAIL=$email","ADMIN_PASSWORD=$admin","DB_PASSWORD=$(New-Secret)","DB_ROOT_PASSWORD=$(New-Secret)") | Set-Content -Encoding ASCII .env
  Write-Host "Initial administrator: admin / $admin"
  Write-Host 'Keep .env private. Change admin password after login.'
}
docker compose up -d --build
Check-Exit
docker compose ps -a
Check-Exit
$url = (Get-Content .env | Where-Object { $_ -like 'SITE_URL=*' }) -replace '^SITE_URL=', ''
Write-Host "Academy URL: $url"
Start-Process ($url + '/local/seavan/index.php')
