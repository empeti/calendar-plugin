@echo off
REM Batch script to generate test appointments for mpeti-booking-calendar
REM Usage: generate-test-appointments.bat 10

setlocal enabledelayedexpansion

if "%1"=="" (
    echo Error: Please provide the number of appointments to generate.
    echo Usage: generate-test-appointments.bat ^<number^>
    exit /b 1
)

set COUNT=%1
set SCRIPT_DIR=%~dp0
set WP_PATH=%SCRIPT_DIR%app\public

REM Check if WP-CLI is available
where wp >nul 2>&1
if errorlevel 1 (
    echo Error: WP-CLI not found. Please install WP-CLI or ensure it's in your PATH.
    exit /b 1
)

cd /d "%WP_PATH%"

echo Generating %COUNT% test appointments...
echo.

REM Create a temporary PHP file with the count embedded
set TEMP_FILE=%TEMP%\mbc_generate_%RANDOM%.php
(
echo ^<?php
echo define^('MBC_APPOINTMENT_COUNT', %COUNT%^);
echo require '%SCRIPT_DIR%generate-test-appointments.php';
echo ?^>
) > "%TEMP_FILE%"

REM Run the temporary PHP file via WP-CLI
wp eval-file "%TEMP_FILE%"

REM Clean up
del "%TEMP_FILE%" 2>nul

if errorlevel 1 (
    echo.
    echo Error: Failed to generate appointments.
    exit /b 1
)

echo.
echo Done!

