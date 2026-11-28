@echo off
setlocal
if exist "%~dp0academy.ps1" (
  powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0academy.ps1" -Action Sync
) else (
  powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0windows-native\academy.ps1" -Action Sync
)
pause
