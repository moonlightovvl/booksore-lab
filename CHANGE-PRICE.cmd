@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\run-sql.ps1" -SqlFile "database/defense-change.sql"
pause
