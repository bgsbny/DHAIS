@echo off
echo ========================================
echo DH AutoCare - Create Backup Task
echo ========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo ✅ Running as Administrator
) else (
    echo ❌ This script needs Administrator privileges
    echo.
    echo Please:
    echo 1. Right-click on this file
    echo 2. Select "Run as administrator"
    echo 3. Click "Yes" when prompted
    echo.
    pause
    exit /b 1
)

echo.
echo Creating scheduled task for DH AutoCare backup...

REM Delete existing task if it exists
echo Deleting existing task (if any)...
schtasks /delete /tn "DH AutoCare Database Backup" /f >nul 2>&1

REM Create the scheduled task
echo Creating new scheduled task...
schtasks /create /tn "DH AutoCare Database Backup" /tr "C:\xampp\htdocs\dh\run_backup.bat" /sc minute /mo 30 /ru "SYSTEM" /f

if %errorlevel% equ 0 (
    echo.
    echo ✅ Scheduled task created successfully!
    echo.
    echo Task Details:
    echo - Name: DH AutoCare Database Backup
    echo - Schedule: Every 30 minutes
    echo - Command: C:\xampp\htdocs\dh\run_backup.bat
    echo - User: SYSTEM
    echo.
    echo The task will start running automatically.
    echo You can check the task in Windows Task Scheduler.
    echo.
    echo To test immediately, run: php backup.php
) else (
    echo.
    echo ❌ Failed to create scheduled task
    echo Error code: %errorlevel%
    echo.
    echo Please check:
    echo 1. You're running as Administrator
    echo 2. Task Scheduler service is running
    echo 3. The path C:\xampp\htdocs\dh\ exists
)

echo.
pause
