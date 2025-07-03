@echo off
echo Clearing Laravel caches and regenerating autoload files...

REM Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

REM Clear compiled files
php artisan clear-compiled

REM Regenerate composer autoload
composer dump-autoload -o

REM Cache routes and config for better performance
php artisan config:cache
php artisan route:cache

echo.
echo All caches cleared and autoload files regenerated!
echo Try accessing Step 7 again.
pause