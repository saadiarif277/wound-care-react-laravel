#!/bin/bash

# MSC Wound Portal - Supabase Setup Script
# This script automates the setup of Supabase for non-PHI data

echo "🏥 MSC Wound Portal - Supabase Setup"
echo "===================================="

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ .env file not found. Please create one first using the template in SUPABASE_SETUP.md"
    exit 1
fi

# Check for required environment variables
echo "🔍 Checking environment configuration..."
if ! grep -q "SUPABASE_DB_HOST" .env; then
    echo "❌ SUPABASE_DB_HOST not found in .env file"
    echo "📖 Please refer to SUPABASE_SETUP.md for configuration details"
    exit 1
fi

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install
npm install

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate
fi

# Create missing migrations for MSC-specific tables
echo "🗄️  Creating additional migrations..."

# Check if migrations already exist before creating
if [ ! -f "database/migrations/*_create_msc_products_table.php" ]; then
    php artisan make:migration create_msc_products_table
fi

if [ ! -f "database/migrations/*_create_msc_sales_reps_table.php" ]; then
    php artisan make:migration create_msc_sales_reps_table
fi

if [ ! -f "database/migrations/*_create_orders_table.php" ]; then
    php artisan make:migration create_orders_table
fi

if [ ! -f "database/migrations/*_create_order_items_table.php" ]; then
    php artisan make:migration create_order_items_table
fi

# Create models if they don't exist
echo "📋 Creating models..."
if [ ! -f "app/Models/MscProduct.php" ]; then
    php artisan make:model MscProduct
fi

if [ ! -f "app/Models/MscSalesRep.php" ]; then
    php artisan make:model MscSalesRep
fi

if [ ! -f "app/Models/Order.php" ]; then
    php artisan make:model Order
fi

if [ ! -f "app/Models/OrderItem.php" ]; then
    php artisan make:model OrderItem
fi

# Create controllers if they don't exist
echo "🎮 Creating controllers..."
if [ ! -f "app/Http/Controllers/MscProductController.php" ]; then
    php artisan make:controller MscProductController --api
fi

if [ ! -f "app/Http/Controllers/MscSalesRepController.php" ]; then
    php artisan make:controller MscSalesRepController --api
fi

if [ ! -f "app/Http/Controllers/OrderController.php" ]; then
    php artisan make:controller OrderController --api
fi

# Create seeders
echo "🌱 Creating seeders..."
if [ ! -f "database/seeders/MscProductSeeder.php" ]; then
    php artisan make:seeder MscProductSeeder
fi

if [ ! -f "database/seeders/MscSalesRepSeeder.php" ]; then
    php artisan make:seeder MscSalesRepSeeder
fi

# Test database connection
echo "🔗 Testing Supabase connection..."
php artisan tinker --execute="echo 'Connection test: '; var_dump(DB::connection('supabase')->select('SELECT version()')[0]->version ?? 'Failed');"

if [ $? -eq 0 ]; then
    echo "✅ Supabase connection successful!"
else
    echo "❌ Supabase connection failed. Please check your configuration."
    echo "📖 Refer to SUPABASE_SETUP.md for troubleshooting"
    exit 1
fi

# Run migrations
echo "🚀 Running migrations..."
read -p "Do you want to run database migrations now? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate
    echo "✅ Migrations completed!"
else
    echo "⏸️  Skipped migrations. Run 'php artisan migrate' when ready."
fi

# Ask about seeding
echo "🌱 Database seeding..."
read -p "Do you want to run database seeders now? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan db:seed
    echo "✅ Database seeding completed!"
else
    echo "⏸️  Skipped seeding. Run 'php artisan db:seed' when ready."
fi

# Create tests
echo "🧪 Creating tests..."
if [ ! -f "tests/Feature/SupabaseConnectionTest.php" ]; then
    php artisan make:test SupabaseConnectionTest
fi

if [ ! -f "tests/Feature/NonPhiDataHandlingTest.php" ]; then
    php artisan make:test NonPhiDataHandlingTest
fi

echo ""
echo "🎉 Supabase setup completed!"
echo ""
echo "Next steps:"
echo "1. Review and update the migration files with the schemas from SUPABASE_SETUP.md"
echo "2. Configure Row Level Security policies in your Supabase dashboard"
echo "3. Update your model relationships and fillable attributes"
echo "4. Implement your API controllers"
echo "5. Set up your Azure FHIR integration for PHI data"
echo ""
echo "📖 For detailed instructions, see SUPABASE_SETUP.md"
echo "🔧 To test: php artisan test --filter=SupabaseConnection"
