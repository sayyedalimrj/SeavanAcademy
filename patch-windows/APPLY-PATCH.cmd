@echo off
setlocal
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0apply-patch.ps1" %*
set "patch_exit=%errorlevel%"
if "%~1"=="" pause
exit /b %patch_exit%
