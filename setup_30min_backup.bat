@echo off
echo ========================================
echo DH AutoCare - 30-Minute Auto-Backup Setup
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
echo Setting up 30-minute auto-backup for DH AutoCare...
echo.

REM Delete existing task if it exists
schtasks /delete /tn "DH AutoCare Database Backup" /f >nul 2>&1

REM Create the scheduled task for every 30 minutes
echo Creating scheduled task...
schtasks /create /tn "DH AutoCare Database Backup" /tr "C:\xampp\htdocs\dh\run_backup.bat" /sc minute /mo 30 /ru "SYSTEM" /f

if %errorlevel% equ 0 (
    echo ✅ 30-minute auto-backup scheduled successfully!
    echo.
    echo 📋 Setup Details:
    echo - Task Name: DH AutoCare Database Backup
    echo - Schedule: Every 30 minutes
    echo - Keeps: Last 20 backups (10 hours of data)
    echo - Location: C:\xampp\htdocs\dh\backups\
    echo.
    echo 🔧 To test the backup now, run: php backup.php
    echo.
    echo 📁 You can view backups at: C:\xampp\htdocs\dh\backups\
    echo.
    echo ✅ Task will start running automatically every 30 minutes!
) else (
    echo ❌ Failed to schedule auto-backup
    echo Error code: %errorlevel%
    echo.
    echo Please check:
    echo 1. XAMPP is installed at C:\xampp\
    echo 2. You're running as Administrator
    echo 3. Task Scheduler service is running
    echo.
    echo 🔧 Try running this script as Administrator again
)

echo.
pause 