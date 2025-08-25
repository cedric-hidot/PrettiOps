@echo off
echo FINAL AUTOLOADER FIX - This will properly regenerate everything
cd /d "C:\Users\cedri\PrettiOps.Dev\PrettiOps"

echo Step 1: Clearing all caches...
if exist var\cache rmdir /s /q var\cache
if exist var\log rmdir /s /q var\log

echo Step 2: Removing corrupted autoloader files...
del /q vendor\composer\autoload_*.php 2>nul

echo Step 3: Running proper composer regeneration...
php composer.phar dump-autoload --optimize --no-dev

echo Step 4: Verifying critical directories exist...
mkdir var 2>nul
mkdir var\cache 2>nul
mkdir var\log 2>nul

echo Step 5: Testing autoloader...
php -r "require 'vendor/autoload.php'; echo class_exists('Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle') ? 'DoctrineFixturesBundle: OK' . PHP_EOL : 'DoctrineFixturesBundle: MISSING' . PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('Liip\TestFixturesBundle\LiipTestFixturesBundle') ? 'LiipTestFixturesBundle: OK' . PHP_EOL : 'LiipTestFixturesBundle: MISSING' . PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('App\Kernel') ? 'App\Kernel: OK' . PHP_EOL : 'App\Kernel: MISSING' . PHP_EOL;"

echo.
echo ===================================================
echo COMPLETE! Try your application now.
echo If it still doesn't work, you may need to run:
echo     composer install --optimize-autoloader
echo ===================================================
pause