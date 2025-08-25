@echo off
echo FIXING REDIS EXTENSION ISSUE
echo Installing packages while ignoring Redis requirement
cd /d "C:\Users\cedri\PrettiOps.Dev\PrettiOps"

echo Step 1: Installing with Redis requirement ignored...
php composer.phar install --optimize-autoloader --ignore-platform-req=ext-redis

echo Step 2: Testing bundles...
php test_bundles.php

echo.
echo ===================================================
echo Installation complete!
echo Redis extension was ignored for local development.
echo ===================================================
pause