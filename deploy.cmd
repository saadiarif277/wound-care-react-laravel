@if "%SCM_TRACE_LEVEL%" NEQ "4" @echo off

:: Deployment script for Laravel + Inertia.js on Azure

:: 1. Install Composer dependencies
echo Installing Composer dependencies...
call composer install --no-dev --optimize-autoloader --no-interaction

:: 2. Install NPM dependencies
echo Installing NPM dependencies...
call npm ci

:: 3. Build frontend assets with Vite
echo Building frontend assets...
call npm run prod

:: 4. Run Laravel optimizations
echo Running Laravel optimizations...
call php artisan config:cache
call php artisan route:cache
call php artisan view:cache

:: 5. Set proper permissions
echo Setting permissions...
icacls storage /grant "IIS_IUSRS:(OI)(CI)F" /T
icacls bootstrap\cache /grant "IIS_IUSRS:(OI)(CI)F" /T