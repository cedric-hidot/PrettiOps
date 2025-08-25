@echo off
echo FINAL ATTEMPT: Regenerate autoloader properly
cd /d "C:\Users\cedri\PrettiOps.Dev\PrettiOps"

echo Step 1: Remove corrupted static file...
del vendor\composer\autoload_static.php 2>nul

echo Step 2: Regenerate autoloader...
php composer.phar dump-autoload --optimize

echo Step 3: Test...
php test_bundles.php

echo.
echo If this still fails, run nuclear_fix.bat for complete reinstall
pause