# MSC Wound Care - Database Seeding Fix Script
# This script helps resolve database transaction errors during seeding

Write-Host "MSC Wound Care - Database Seeding Fix" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan

# Function to run command and check for errors
function Run-Artisan {
    param(
        [string]$Command,
        [string]$Description
    )
    
    Write-Host "`n$Description" -ForegroundColor Yellow
    Write-Host "Running: php artisan $Command" -ForegroundColor Gray
    
    $process = Start-Process -FilePath "php" -ArgumentList "artisan", $Command -NoNewWindow -Wait -PassThru -RedirectStandardOutput "temp_output.txt" -RedirectStandardError "temp_error.txt"
    $output = Get-Content "temp_output.txt" -ErrorAction SilentlyContinue
    $errorOutput = Get-Content "temp_error.txt" -ErrorAction SilentlyContinue
    
    Remove-Item "temp_output.txt" -ErrorAction SilentlyContinue
    Remove-Item "temp_error.txt" -ErrorAction SilentlyContinue
    
    if ($process.ExitCode -eq 0) {
        Write-Host "[SUCCESS]" -ForegroundColor Green
        if ($output) { Write-Host $output }
    } else {
        Write-Host "[FAILED]" -ForegroundColor Red
        if ($output) { Write-Host $output }
        if ($errorOutput) { Write-Host $errorOutput -ForegroundColor Red }
        return $false
    }
    return $true
}

# Step 1: Clear cache and config
Write-Host "`nStep 1: Clearing Laravel cache and config..." -ForegroundColor Cyan
php artisan config:clear
php artisan cache:clear

# Step 2: Test database connection
Write-Host "`nStep 2: Testing database connection..." -ForegroundColor Cyan
$testScript = @"
try {
    DB::connection()->getPdo();
    echo 'CONNECTION_OK';
} catch (Exception `$e) {
    echo 'CONNECTION_FAILED: ' . `$e->getMessage();
}
"@

$testScript | Out-File -FilePath "test_connection.php" -Encoding UTF8
$testResult = php artisan tinker  test_connection.php 2>&1 | Out-String
Remove-Item "test_connection.php" -ErrorAction SilentlyContinue

if ($testResult -match "CONNECTION_OK") {
    Write-Host "[SUCCESS] Database connection successful" -ForegroundColor Green
} else {
    Write-Host "[FAILED] Database connection failed" -ForegroundColor Red
    Write-Host $testResult
    Write-Host "`nPlease check your .env file and database configuration" -ForegroundColor Yellow
    exit 1
}

# Step 3: Fresh migration
Write-Host "`nStep 3: Running fresh migration..." -ForegroundColor Cyan
$migrate = Run-Artisan "migrate:fresh" "Dropping all tables and re-running migrations"
if (-not $migrate) {
    Write-Host "`nMigration failed. Please fix migration errors before proceeding." -ForegroundColor Red
    exit 1
}

# Step 4: Try complete seeding first
Write-Host "`nStep 4: Attempting complete database seeding..." -ForegroundColor Cyan
$fullSeed = Run-Artisan "db:seed" "Running all seeders"

if ($fullSeed) {
    Write-Host "`n[SUCCESS] Database seeding completed successfully!" -ForegroundColor Green
    exit 0
}

# Step 5: If full seeding failed, run seeders individually
Write-Host "`nFull seeding failed. Running seeders individually..." -ForegroundColor Yellow

$seeders = @(
    @{Class="OrganizationSeeder"; Desc="Creating organizations"},
    @{Class="CategoriesAndManufacturersSeeder"; Desc="Creating categories and manufacturers"},
    @{Class="ProductSeeder"; Desc="Creating products"},
    @{Class="ManufacturerDocuSealTemplateSeeder"; Desc="Creating manufacturer DocuSeal templates and folders"},
    @{Class="IVRFieldMappingSeeder"; Desc="Creating IVR field mappings"},
    @{Class="RemoveHardcodedDataSeeder"; Desc="Creating reference data"},
    @{Class="DiagnosisCodesFromCsvSeeder"; Desc="Importing diagnosis codes from CSV"},
    @{Class="PatientManufacturerIVREpisodeSeeder"; Desc="Creating patient episodes"}
)

$failedSeeders = @()
$successCount = 0

foreach ($seeder in $seeders) {
    $success = Run-Artisan "db:seed --class=$($seeder.Class)" $seeder.Desc
    if (-not $success) {
        $failedSeeders += $seeder.Class
    } else {
        $successCount++
    }
}

# Summary
Write-Host "`n===== SUMMARY =====" -ForegroundColor Cyan
Write-Host "Successful seeders: $successCount / $($seeders.Count)" -ForegroundColor White

if ($failedSeeders.Count -eq 0) {
    Write-Host "[SUCCESS] All seeders completed successfully!" -ForegroundColor Green
} else {
    Write-Host "[FAILED] The following seeders failed:" -ForegroundColor Red
    foreach ($failed in $failedSeeders) {
        Write-Host "  - $failed" -ForegroundColor Red
    }
    
    Write-Host "`nTroubleshooting steps:" -ForegroundColor Yellow
    Write-Host "1. Check the specific seeder file for issues"
    Write-Host "2. Ensure all required CSV files exist in docs/data/"
    Write-Host "3. Check for foreign key constraint violations"
    Write-Host "4. Review the error messages above for specific issues"
}

Write-Host "`nAdditional debugging commands:" -ForegroundColor Cyan
Write-Host "- Check Laravel logs: Get-Content storage/logs/laravel.log -Tail 50"
Write-Host "- Test specific seeder: php artisan db:seed --class=SeederName -v"
Write-Host "- Run migrations only: php artisan migrate:fresh"
Write-Host "- Check database tables: php artisan tinker"