#!/bin/bash

# Deployment script for Laravel + Inertia.js on Azure

# Install Composer dependencies
echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install NPM dependencies
echo "Installing NPM dependencies..."
npm ci

# Build frontend assets with Vite
echo "Building frontend assets..."
npm run prod

# Run Laravel optimizations
echo "Running Laravel optimizations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache