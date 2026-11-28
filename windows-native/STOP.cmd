@echo off
setlocal
if exist "%~dp0academy.ps1" (
  powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0academy.ps1" -Action Stop
) else (
  powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0windows-native\academy.ps1" -Action Stop
)
if errorlevel 1 pause
