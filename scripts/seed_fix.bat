@echo off
echo MSC Wound Care - Database Seeding Fix
echo =====================================

echo.
echo Step 1: Clearing Laravel cache and config...
php artisan config:clear
php artisan cache:clear

echo.
echo Step 2: Running fresh migration...
php artisan migrate:fresh
if %errorlevel% neq 0 (
    echo [FAILED] Migration failed. Please fix migration errors before proceeding.
    pause
    exit /b 1
)

echo.
echo Step 3: Attempting complete database seeding...
php artisan db:seed
if %errorlevel% equ 0 (
    echo.
    echo [SUCCESS] Database seeding completed successfully!
    pause
    exit /b 0
)

echo.
echo Full seeding failed. Running seeders individually...
echo.

echo Running DatabaseSeeder...
php artisan db:seed --class=DatabaseSeeder

echo.
echo Running OrganizationSeeder...
php artisan db:seed --class=OrganizationSeeder

echo.
echo Running CategoriesAndManufacturersSeeder...
php artisan db:seed --class=CategoriesAndManufacturersSeeder

echo.
echo Running ProductSeeder...
php artisan db:seed --class=ProductSeeder

echo.
echo Running ManufacturerDocuSealTemplateSeeder...
php artisan db:seed --class=ManufacturerDocuSealTemplateSeeder

echo.
echo Running IVRFieldMappingSeeder...
php artisan db:seed --class=IVRFieldMappingSeeder

echo.
echo Running RemoveHardcodedDataSeeder...
php artisan db:seed --class=RemoveHardcodedDataSeeder

echo.
echo Running DiagnosisCodesFromCsvSeeder...
php artisan db:seed --class=DiagnosisCodesFromCsvSeeder

echo.
echo Running PatientManufacturerIVREpisodeSeeder...
php artisan db:seed --class=PatientManufacturerIVREpisodeSeeder

echo.
echo ===== SEEDING COMPLETE =====
echo Check above for any errors.
echo.
pause