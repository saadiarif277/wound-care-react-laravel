#!/bin/bash

# Deployment script for Laravel + Inertia.js on Azure
set -e  # Exit on any error

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "Error: Not in Laravel root directory"
    exit 1
fi

# Install Composer dependencies if composer is available
echo "Installing Composer dependencies..."
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "Composer not found, skipping PHP dependencies"
fi

# Install NPM dependencies
echo "Installing NPM dependencies..."
npm ci --no-bin-links --prefer-offline --no-audit

# Build frontend assets with Vite (only once!)
echo "Building frontend assets..."
npx vite build

# Run Laravel optimizations if PHP is available
if command -v php &> /dev/null; then
    echo "Running Laravel optimizations..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "PHP not found, skipping Laravel optimizations"
fi

# Set proper permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "Deployment completed!"