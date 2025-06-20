# Run the new migrations for FHIR Data Lake

Write-Host "Running FHIR Data Lake migrations..." -ForegroundColor Green

# Navigate to project directory
Set-Location "C:\Users\Richa\wound-care-react-laravel"

# Run the migrations
php artisan migrate

Write-Host "Migrations completed!" -ForegroundColor Green