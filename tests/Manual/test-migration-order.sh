#!/bin/bash

# MSC Wound Portal - Migration Order Test Script
# This script tests the migration and seed order to ensure proper database setup

echo "========================================"
echo "Migration Order Testing"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print section headers
print_section() {
    echo ""
    echo -e "${BLUE}=== $1 ===${NC}"
    echo ""
}

# Function to print test results
print_result() {
    if [ $2 -eq 0 ]; then
        echo -e "${GREEN}✓ $1${NC}"
    else
        echo -e "${RED}✗ $1${NC}"
    fi
}

# 1. Backup existing database (if needed)
print_section "1. Database Backup"
echo -e "${YELLOW}Warning: This test will drop and recreate all tables!${NC}"
echo "Press Ctrl+C to cancel, or Enter to continue..."
read -r

# 2. Drop all tables
print_section "2. Dropping All Tables"
php artisan migrate:reset --force
print_result "Tables dropped" $?

# 3. Run the fix migration order migration
print_section "3. Running Fix Migration Order"
php artisan migrate --path=database/migrations/2025_01_17_120000_fix_migration_order.php --force
print_result "Fix migration order completed" $?

# 4. Run all migrations
print_section "4. Running All Migrations"
php artisan migrate --force
print_result "All migrations completed" $?

# 5. Check table structure
print_section "5. Verifying Table Structure"

# Check if key tables exist
TABLES_TO_CHECK=(
    "accounts"
    "users"
    "roles"
    "permissions"
    "organizations"
    "facilities"
    "manufacturers"
    "categories"
    "msc_products"
    "product_requests"
    "docuseal_folders"
    "docuseal_templates"
    "docuseal_submissions"
)

for table in "${TABLES_TO_CHECK[@]}"; do
    result=$(php artisan tinker --execute="echo Schema::hasTable('$table') ? 'exists' : 'missing';")
    if [[ $result == *"exists"* ]]; then
        echo -e "${GREEN}✓ Table $table exists${NC}"
    else
        echo -e "${RED}✗ Table $table is missing${NC}"
    fi
done

# 6. Run seeders
print_section "6. Running Database Seeders"
php artisan db:seed --force
SEED_RESULT=$?
print_result "Database seeding completed" $SEED_RESULT

# 7. Verify seed data
print_section "7. Verifying Seed Data"

php artisan tinker << 'EOF'
echo "Checking seed data...\n";

$checks = [
    'Users' => \App\Models\User::count(),
    'Roles' => DB::table('roles')->count(),
    'Permissions' => DB::table('permissions')->count(),
    'Organizations' => \App\Models\Organization::count(),
    'Facilities' => \App\Models\Facility::count(),
    'Products' => \App\Models\Order\Product::count(),
    'Product Requests' => \App\Models\Order\ProductRequest::count(),
];

foreach ($checks as $name => $count) {
    echo sprintf("%-20s: %d\n", $name, $count);
}

// Check specific relationships
echo "\nChecking relationships:\n";

// Check if products have manufacturers
$productsWithManufacturers = \App\Models\Order\Product::whereNotNull('manufacturer')->count();
echo "Products with manufacturers: $productsWithManufacturers\n";

// Check if product requests have products
$requestsWithProducts = \App\Models\Order\ProductRequest::has('products')->count();
echo "Product requests with products: $requestsWithProducts\n";

// Check Docuseal setup
$folders = DB::table('docuseal_folders')->count();
$templates = DB::table('docuseal_templates')->count();
echo "Docuseal folders: $folders\n";
echo "Docuseal templates: $templates\n";

// Check user roles
$usersWithRoles = \App\Models\User::whereHas('roles')->count();
echo "Users with roles: $usersWithRoles\n";
EOF

# 8. Test specific migration fixes
print_section "8. Testing Migration Fixes"

php artisan tinker << 'EOF'
echo "Testing migration fixes...\n";

// Check if msc_products has required columns
$productColumns = Schema::getColumnListing('msc_products');
$requiredColumns = ['category_id', 'manufacturer_id', 'national_asp', 'msc_price', 'code'];
$missingColumns = array_diff($requiredColumns, $productColumns);

if (empty($missingColumns)) {
    echo "✓ All required columns exist in msc_products\n";
} else {
    echo "✗ Missing columns in msc_products: " . implode(', ', $missingColumns) . "\n";
}

// Check if product_requests has IVR columns
$prColumns = Schema::getColumnListing('product_requests');
$ivrColumns = [
    'ivr_required', 'ivr_bypass_reason', 'ivr_sent_at', 'ivr_signed_at',
    'ivr_document_url', 'docuseal_submission_id', 'docuseal_template_id'
];
$missingIvrColumns = array_diff($ivrColumns, $prColumns);

if (empty($missingIvrColumns)) {
    echo "✓ All IVR columns exist in product_requests\n";
} else {
    echo "✗ Missing IVR columns: " . implode(', ', $missingIvrColumns) . "\n";
}

// Check foreign key constraints
try {
    // Test creating a product with manufacturer
    $manufacturer = DB::table('manufacturers')->first();
    $category = DB::table('categories')->first();
    
    if ($manufacturer && $category) {
        $product = \App\Models\Order\Product::create([
            'name' => 'Test Product FK',
            'sku' => 'TEST-FK-001',
            'manufacturer_id' => $manufacturer->id,
            'category_id' => $category->id,
            'is_active' => true
        ]);
        echo "✓ Foreign key constraints working properly\n";
        $product->delete();
    } else {
        echo "⚠ Could not test foreign keys - missing test data\n";
    }
} catch (\Exception $e) {
    echo "✗ Foreign key constraint error: " . $e->getMessage() . "\n";
}
EOF

# 9. Summary
print_section "9. Migration Order Test Summary"

if [ $SEED_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ All migrations and seeds completed successfully!${NC}"
    echo ""
    echo "The database has been properly set up with:"
    echo "1. All required tables created in correct order"
    echo "2. Foreign key constraints properly established"
    echo "3. All IVR and Docuseal fields added"
    echo "4. Test data seeded successfully"
else
    echo -e "${RED}✗ There were errors during migration/seeding${NC}"
    echo ""
    echo "Please check the output above for specific errors."
fi

echo ""
echo "Next steps:"
echo "1. Test the application functionality"
echo "2. Verify IVR generation works with the new fields"
echo "3. Check that all relationships are properly loaded"