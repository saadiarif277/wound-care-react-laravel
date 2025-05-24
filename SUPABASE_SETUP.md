# Supabase Setup Guide - MSC Wound Portal

## Overview

This guide will help you set up Supabase to handle all non-PHI (Protected Health Information) data for the MSC Wound Portal project. The architecture follows HIPAA compliance requirements with strict data separation.

## Architecture Overview

- **Supabase PostgreSQL**: Stores all non-PHI operational data
- **Supabase Storage**: Stores non-PHI documents and files via S3-compatible API
- **Azure Health Data Services**: Stores all PHI data (configured separately)
- **Laravel Authentication**: Uses Sanctum for API authentication (not Supabase Auth)

## Prerequisites

1. Supabase account and project
2. Laravel project (already configured)
3. Redis for caching and sessions

## Step 1: Create Supabase Project

1. Go to [https://supabase.com](https://supabase.com)
2. Create a new project
3. Note down your project details:
   - Project URL
   - API Keys (anon and service_role)
   - Database connection details

## Step 2: Configure Supabase Storage

### Enable S3 Protocol
1. In your Supabase dashboard, go to **Storage** > **Settings**
2. Enable **"Enable connection via S3 protocol"**
3. Note the S3 endpoint URL (e.g., `https://your-project-ref.supabase.co/storage/v1/s3`)
4. Note the region (e.g., `us-east-2`)

### Create S3 Access Keys
1. In the **S3 Access Keys** section, click **"New access key"**
2. Provide a description (e.g., "MSC Wound Portal Laravel App")
3. Copy the **Access Key ID** and **Secret Access Key**
4. **Important**: Save these credentials securely - the secret key won't be shown again

### Create Storage Buckets
Create the following buckets for non-PHI documents:
1. **documents** - For general business documents
2. **reports** - For commission reports and analytics
3. **exports** - For data exports and backups

**Note**: Do NOT store any PHI data in Supabase Storage. All PHI documents must go to Azure Health Data Services.

## Step 3: Environment Configuration

Create a `.env` file in your project root with the following configuration:

```bash
# Application Configuration
APP_NAME="MSC Wound Portal"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost

# Supabase Database Configuration (Non-PHI Data)
DB_CONNECTION=supabase
SUPABASE_DB_HOST=db.your-project-ref.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=your-database-password
SUPABASE_DB_SSL_MODE=require

# Supabase API Configuration
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key

# Supabase Storage (S3-Compatible) - Non-PHI Files Only
FILESYSTEM_DISK=supabase
SUPABASE_S3_ACCESS_KEY_ID=your-s3-access-key-id
SUPABASE_S3_SECRET_ACCESS_KEY=your-s3-secret-access-key
SUPABASE_S3_REGION=us-east-2
SUPABASE_S3_BUCKET=documents
SUPABASE_S3_URL=https://your-project-ref.supabase.co/storage/v1/object/public/documents
SUPABASE_S3_ENDPOINT=https://your-project-ref.supabase.co/storage/v1/s3

# Azure Health Data Services (PHI Data)
AZURE_FHIR_URL=https://your-fhir-service.azurehealthcareapis.com
AZURE_FHIR_CLIENT_ID=your-client-id
AZURE_FHIR_CLIENT_SECRET=your-client-secret
AZURE_FHIR_TENANT_ID=your-tenant-id

# Authentication & Sessions
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:3000,127.0.0.1:3000,::1
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Cache & Queue (Redis recommended for production)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Step 4: Install Dependencies

Your project already has the necessary dependencies. Verify these are in your `composer.json`:

```bash
composer require laravel/sanctum
composer require inertiajs/inertia-laravel
composer require league/flysystem-aws-s3-v3  # For S3-compatible storage
```

## Step 5: Run Database Migrations

Your project already has migrations set up. Run them to create the non-PHI database structure:

```bash
php artisan migrate
```

## Step 6: Configure Supabase Storage Policies

Set up Row Level Security (RLS) policies for your storage buckets:

```sql
-- Enable RLS on storage.buckets
ALTER TABLE storage.buckets ENABLE ROW LEVEL SECURITY;

-- Enable RLS on storage.objects  
ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;

-- Policy for documents bucket (authenticated users can upload/read)
CREATE POLICY "Authenticated users can upload documents" ON storage.objects
    FOR INSERT WITH CHECK (
        bucket_id = 'documents' AND 
        auth.role() = 'authenticated'
    );

CREATE POLICY "Authenticated users can read documents" ON storage.objects
    FOR SELECT USING (
        bucket_id = 'documents' AND 
        auth.role() = 'authenticated'
    );

-- Policy for reports bucket (admin users only)
CREATE POLICY "Admin users can manage reports" ON storage.objects
    FOR ALL USING (
        bucket_id = 'reports' AND 
        auth.jwt() ->> 'role' = 'admin'
    );
```

## Step 7: Configure Supabase Row Level Security (RLS) for Database

Enable RLS on your Supabase database tables for security. Run these SQL commands in the Supabase SQL editor:

```sql
-- Enable RLS on all tables
ALTER TABLE accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE organizations ENABLE ROW LEVEL SECURITY;
ALTER TABLE facilities ENABLE ROW LEVEL SECURITY;
ALTER TABLE contacts ENABLE ROW LEVEL SECURITY;
ALTER TABLE commission_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE commission_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE commission_payouts ENABLE ROW LEVEL SECURITY;

-- Example policy for users table (authenticated users can read their own data)
CREATE POLICY "Users can read own data" ON users
    FOR SELECT USING (auth.uid()::text = id::text);

-- Example policy for organizations (account-based access)
CREATE POLICY "Account members can access organizations" ON organizations
    FOR ALL USING (
        account_id IN (
            SELECT account_id FROM users WHERE id = auth.uid()::text::integer
        )
    );
```

## Step 8: Data Classification Validation

Ensure all data stored in Supabase contains **ONLY** non-PHI data:

### ✅ Non-PHI Data (Safe for Supabase)
**Database:**
- User accounts (business email, names for portal access)
- Organization/facility business information
- Product catalogs and pricing
- Commission rules and calculations
- Order metadata (without clinical details)
- System configuration

**Storage:**
- Business documents and contracts
- Commission reports
- Product catalogs and images
- System documentation
- Data exports and backups

### ❌ PHI Data (Must go to Azure FHIR)
**Database:**
- Patient names, DOB, addresses
- Medical record numbers
- Insurance information
- Clinical documentation references

**Storage:**
- Wound assessments and measurements
- Medical images and photos
- Patient documents
- Clinical reports
- Insurance forms

## Step 9: Test Storage Connection

Test your Supabase Storage connection:

```bash
php artisan tinker
```

```php
// Test storage connection
Storage::disk('supabase')->put('test.txt', 'Hello Supabase Storage!');
$contents = Storage::disk('supabase')->get('test.txt');
echo $contents; // Should output: Hello Supabase Storage!

// Clean up test file
Storage::disk('supabase')->delete('test.txt');
```

## Step 10: Additional Non-PHI Tables Setup

Based on your existing code, you may need to create additional migrations for missing tables:

### MSC Products Table
Create a migration for MSC products (non-PHI product information):

```bash
php artisan make:migration create_msc_products_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('msc_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('national_asp', 10, 2)->nullable();
            $table->decimal('price_per_sq_cm', 10, 4)->nullable();
            $table->string('q_code', 10)->nullable();
            $table->json('available_sizes')->nullable(); // Store as JSON array
            $table->string('graph_type')->nullable();
            $table->string('image_url')->nullable(); // Supabase Storage URL
            $table->json('document_urls')->nullable(); // Array of Supabase Storage URLs
            $table->boolean('is_active')->default(true);
            $table->decimal('commission_rate', 5, 2)->nullable(); // Default commission rate
            $table->timestamps();
            $table->softDeletes();

            $table->index(['manufacturer_id', 'category_id']);
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('msc_products');
    }
};
```

### MSC Sales Reps Table
Create a migration for sales representatives:

```bash
php artisan make:migration create_msc_sales_reps_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('msc_sales_reps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('territory')->nullable();
            $table->decimal('commission_rate_direct', 5, 2)->default(0); // Base commission rate
            $table->decimal('sub_rep_parent_share_percentage', 5, 2)->default(50); // Parent share when sub-rep makes sale
            $table->unsignedBigInteger('parent_rep_id')->nullable(); // For hierarchical structure
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_rep_id')->references('id')->on('msc_sales_reps')->onDelete('set null');
            $table->index('parent_rep_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('msc_sales_reps');
    }
};
```

### Orders Table (Non-PHI metadata only)
Create a migration for order metadata:

```bash
php artisan make:migration create_orders_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('patient_fhir_id'); // Reference to FHIR Patient resource
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('sales_rep_id')->nullable();
            $table->date('date_of_service');
            $table->string('credit_terms')->default('net60');
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'fulfilled', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('expected_reimbursement', 10, 2)->default(0);
            $table->date('expected_collection_date')->nullable();
            $table->string('payment_status')->default('pending');
            $table->decimal('msc_commission_structure', 5, 2)->default(40);
            $table->decimal('msc_commission', 10, 2)->default(0);
            $table->json('document_urls')->nullable(); // Non-PHI document URLs in Supabase Storage
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('sales_rep_id')->references('id')->on('msc_sales_reps');
            $table->index('patient_fhir_id');
            $table->index('status');
            $table->index('date_of_service');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
```

### Order Items Table
Create a migration for order items:

```bash
php artisan make:migration create_order_items_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->string('graph_size')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('msc_products');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};
```

## Step 11: Create Models

Create the corresponding Eloquent models for your new tables:

```bash
php artisan make:model MscProduct
php artisan make:model MscSalesRep
php artisan make:model Order
php artisan make:model OrderItem
```

## Step 12: Seed Sample Data

Create seeders for initial data:

```bash
php artisan make:seeder MscProductSeeder
php artisan make:seeder MscSalesRepSeeder
```

Example seeder for products:
```php
<?php

namespace Database\Seeders;

use App\Models\MscProduct;
use Illuminate\Database\Seeder;

class MscProductSeeder extends Seeder
{
    public function run()
    {
        $products = [
            [
                'sku' => 'MSC-001',
                'name' => 'Advanced Wound Graft Type A',
                'description' => 'Premium wound care product',
                'manufacturer' => 'MedTech Solutions',
                'category' => 'Biological Grafts',
                'national_asp' => 150.00,
                'price_per_sq_cm' => 25.50,
                'q_code' => 'Q4100',
                'available_sizes' => json_encode(['2x2', '4x4', '6x6']),
                'graph_type' => 'biological',
                'is_active' => true,
                'commission_rate' => 5.00,
            ],
            // Add more products as needed
        ];

        foreach ($products as $product) {
            MscProduct::create($product);
        }
    }
}
```

## Step 13: Configure API Routes

Update your `routes/api.php` for the non-PHI endpoints:

```php
use App\Http\Controllers\{
    MscProductController,
    MscSalesRepController,
    OrderController,
    CommissionRuleController
};

Route::middleware('auth:sanctum')->group(function () {
    // Products (Non-PHI)
    Route::apiResource('products', MscProductController::class);
    
    // Sales Reps (Non-PHI)
    Route::apiResource('sales-reps', MscSalesRepController::class);
    
    // Orders (Non-PHI metadata only)
    Route::apiResource('orders', OrderController::class);
    
    // Commission Rules
    Route::apiResource('commission-rules', CommissionRuleController::class);
    
    // File uploads (Non-PHI only)
    Route::post('files/upload', [FileController::class, 'upload']);
    Route::get('files/{path}', [FileController::class, 'serve']);
});
```

## Step 14: Security Best Practices

### RLS Policies
Implement comprehensive Row Level Security policies in Supabase:

```sql
-- Products policy (all authenticated users can read)
CREATE POLICY "Authenticated users can read products" ON msc_products
    FOR SELECT USING (auth.role() = 'authenticated');

-- Sales reps policy (users can only see their own data)
CREATE POLICY "Users can manage own rep data" ON msc_sales_reps
    FOR ALL USING (auth.uid()::text = id::text);

-- Orders policy (account-based access)
CREATE POLICY "Account members can access orders" ON orders
    FOR ALL USING (
        facility_id IN (
            SELECT id FROM facilities 
            WHERE account_id IN (
                SELECT account_id FROM users 
                WHERE id = auth.uid()::text::integer
            )
        )
    );
```

### API Security
Ensure all PHI references use secure identifiers:

```php
// ✅ Good: Use FHIR resource IDs as references
$order->patient_fhir_id = 'Patient/abc-123-def';

// ✅ Good: Store non-PHI documents in Supabase Storage
$order->document_urls = [
    'invoice' => 'https://your-project.supabase.co/storage/v1/object/public/documents/invoices/inv-123.pdf',
    'contract' => 'https://your-project.supabase.co/storage/v1/object/public/documents/contracts/cont-456.pdf'
];

// ❌ Bad: Never store actual PHI in Supabase
$order->patient_name = 'John Doe'; // This would be PHI violation
```

## Step 15: Testing

Create feature tests to verify your setup:

```bash
php artisan make:test SupabaseConnectionTest
php artisan make:test SupabaseStorageTest
php artisan make:test NonPhiDataHandlingTest
```

Example storage test:
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class SupabaseStorageTest extends TestCase
{
    public function test_supabase_storage_connection()
    {
        // Test file upload
        $testContent = 'Test file content';
        $path = 'test/test-file.txt';
        
        Storage::disk('supabase')->put($path, $testContent);
        
        // Test file exists
        $this->assertTrue(Storage::disk('supabase')->exists($path));
        
        // Test file content
        $retrievedContent = Storage::disk('supabase')->get($path);
        $this->assertEquals($testContent, $retrievedContent);
        
        // Clean up
        Storage::disk('supabase')->delete($path);
    }
}
```

## Step 16: File Upload Helper

Create a helper service for managing non-PHI file uploads:

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Upload a non-PHI file to Supabase Storage
     */
    public function uploadDocument(UploadedFile $file, string $category = 'documents'): string
    {
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "{$category}/" . date('Y/m/d') . "/{$fileName}";
        
        Storage::disk('supabase')->putFileAs($category . '/' . date('Y/m/d'), $file, $fileName);
        
        return Storage::disk('supabase')->url($path);
    }
    
    /**
     * Delete a file from Supabase Storage
     */
    public function deleteFile(string $url): bool
    {
        $path = $this->extractPathFromUrl($url);
        return Storage::disk('supabase')->delete($path);
    }
    
    private function extractPathFromUrl(string $url): string
    {
        // Extract path from Supabase Storage URL
        $parts = parse_url($url);
        return ltrim($parts['path'], '/storage/v1/object/public/');
    }
}
```

## Step 17: Monitoring & Maintenance

### Performance Monitoring
- Monitor query performance in Supabase dashboard
- Set up alerts for slow queries
- Monitor storage usage and costs

### Security Auditing
- Regular review of RLS policies
- Monitor access logs for both database and storage
- Audit data classification compliance

### Backup Strategy
- Configure automated backups in Supabase
- Regular storage bucket backups
- Test restoration procedures

## Conclusion

Your MSC Wound Portal is now configured with Supabase for both non-PHI data storage and file management. The setup ensures:

- ✅ HIPAA compliance through strict data separation
- ✅ Secure authentication via Laravel Sanctum
- ✅ Scalable PostgreSQL database
- ✅ S3-compatible file storage for non-PHI documents
- ✅ Row Level Security for data protection
- ✅ Proper referential integrity with FHIR resources

Next steps:
1. Create your S3 access keys in Supabase
2. Set up storage buckets: `documents`, `reports`, `exports`
3. Run the migrations: `php artisan migrate`
4. Seed your database: `php artisan db:seed`
5. Test file upload functionality
6. Configure your Azure FHIR integration for PHI data

For any issues, refer to the [Supabase documentation](https://supabase.com/docs) or the project's technical documentation. 
