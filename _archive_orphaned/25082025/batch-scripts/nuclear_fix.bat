@echo off
echo NUCLEAR OPTION: Complete Composer reinstall
echo This will completely regenerate the autoloader from scratch
cd /d "C:\Users\cedri\PrettiOps.Dev\PrettiOps"

echo Step 1: Backing up composer files...
copy composer.json composer.json.backup >nul
copy composer.lock composer.lock.backup >nul

echo Step 2: Removing vendor directory...
if exist vendor rmdir /s /q vendor

echo Step 3: Running fresh composer install...
php composer.phar install --no-dev --optimize-autoloader

echo Step 4: Testing...
php test_bundles.php

echo.
echo ===================================================
echo If this worked, your application should be fixed.
echo If not, you may need to check your composer.json
echo ===================================================
pause