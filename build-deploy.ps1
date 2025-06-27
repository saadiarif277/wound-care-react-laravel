# Build and Deploy Script for Windows PowerShell
# Run this from your project directory

Write-Host "Building MSC-MVP for Azure Deployment..." -ForegroundColor Green

# 1. Install PHP dependencies
Write-Host "Installing PHP dependencies..." -ForegroundColor Yellow
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Install Node dependencies
Write-Host "Installing Node dependencies..." -ForegroundColor Yellow
npm ci

# 3. Build frontend
Write-Host "Building frontend assets..." -ForegroundColor Yellow
npm run prod

# 4. Copy .env
Write-Host "Creating production .env..." -ForegroundColor Yellow
Copy-Item .env.example -Destination .env -Force

# 5. Generate key
php artisan key:generate

# 6. Optimize Laravel
Write-Host "Optimizing Laravel..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Create deployment package
Write-Host "Creating deployment package..." -ForegroundColor Yellow
Remove-Item deploy.zip -ErrorAction SilentlyContinue

# Create a list of files to exclude
$exclude = @("node_modules", ".git", "tests", "*.log", ".env.example", ".github", "storage/logs/*")

# Create the zip file
Compress-Archive -Path * -DestinationPath deploy.zip -Force -CompressionLevel Optimal

Write-Host "Deployment package created: deploy.zip" -ForegroundColor Green
Write-Host "Size: $((Get-Item deploy.zip).Length / 1MB) MB" -ForegroundColor Cyan

Write-Host "`nTo deploy to Azure, run:" -ForegroundColor Yellow
Write-Host "az webapp deployment source config-zip --resource-group YOUR-RG --name msc-dev-ap --src deploy.zip" -ForegroundColor Cyan