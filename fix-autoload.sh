#!/bin/bash

echo "Clearing Laravel caches and regenerating autoload files..."

# Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Clear compiled files
php artisan clear-compiled

# Regenerate composer autoload
composer dump-autoload -o

# Cache routes and config for better performance
php artisan config:cache
php artisan route:cache

echo "All caches cleared and autoload files regenerated!"
echo "Try accessing Step 7 again."