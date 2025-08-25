@echo off
echo RUNNING NUCLEAR OPTION NOW...
echo This will completely reinstall all Composer packages
cd /d "C:\Users\cedri\PrettiOps.Dev\PrettiOps"

echo Step 1: Backing up composer files...
if exist composer.json.backup del composer.json.backup
if exist composer.lock.backup del composer.lock.backup
copy composer.json composer.json.backup >nul
copy composer.lock composer.lock.backup >nul

echo Step 2: Removing vendor directory completely...
if exist vendor (
    echo Removing vendor directory...
    rmdir /s /q vendor
)

echo Step 3: Running fresh composer install...
php composer.phar install --optimize-autoloader

echo Step 4: Testing all bundles...
php test_bundles.php

echo.
echo ===================================================
echo Complete reinstall finished!
echo All packages have been reinstalled from scratch.
echo ===================================================
pause