@echo off
setlocal
if exist "%~dp0academy.ps1" (
  powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0academy.ps1" -Action Run
) else (
  powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0windows-native\academy.ps1" -Action Run
)
if errorlevel 1 pause
