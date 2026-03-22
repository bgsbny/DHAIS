@echo off
echo ========================================
echo DH AutoCare - Backup Status Check
echo ========================================
echo.

echo Checking if running as administrator...
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
echo Checking scheduled task status...
schtasks /query /tn "DH AutoCare Database Backup" /fo list >nul 2>&1
if %errorLevel% == 0 (
    echo ✅ Scheduled task exists
    echo.
    echo Task details:
    schtasks /query /tn "DH AutoCare Database Backup" /fo list
) else (
    echo ❌ Scheduled task does not exist
    echo.
    echo To create the task, run setup_30min_backup.bat as administrator
)

echo.
echo Checking backup files...
if exist "C:\xampp\htdocs\dh\backups\dh_autocare_backup_*.sql" (
    echo ✅ Backup files found
    echo.
    echo Recent backups:
    dir "C:\xampp\htdocs\dh\backups\dh_autocare_backup_*.sql" /o-d /t:w
) else (
    echo ❌ No backup files found
)

echo.
echo Testing manual backup...
cd /d C:\xampp\htdocs\dh
php backup.php

echo.
echo ========================================
echo Troubleshooting Tips:
echo ========================================
echo.
echo If auto-backup is not working:
echo 1. Make sure XAMPP is running
echo 2. Check if MySQL service is running
echo 3. Verify the scheduled task exists and is enabled
echo 4. Check Windows Event Viewer for task errors
echo.
echo To manually create the task:
echo 1. Run setup_30min_backup.bat as administrator
echo 2. Or manually create the task in Task Scheduler
echo.
pause 