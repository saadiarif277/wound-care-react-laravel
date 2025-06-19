# Migration Order Documentation

## Overview
This document explains the migration ordering and dependencies in the MSC Wound Portal database schema.

## Migration Order Fix (2025_01_17_120000_fix_migration_order.php)

This migration ensures all tables are created in the correct order with proper dependencies and adds missing columns that were identified during testing.

### What It Does

1. **Creates Missing Tables** (in dependency order):
   - `manufacturers` - Must exist before products reference it
   - `categories` - Must exist before products reference it
   - `provider_profiles` - For provider-specific data
   - `provider_products` - Junction table for provider-product relationships
   - `docuseal_folders` - Must exist before templates reference it
   - `docuseal_templates` - For IVR templates
   - `docuseal_submissions` - For tracking IVR submissions

2. **Adds Missing Columns**:
   - **msc_products table**:
     - `category_id` (foreign key to categories)
     - `manufacturer_id` (foreign key to manufacturers)
     - `national_asp` (national average selling price)
     - `msc_price` (MSC pricing)
     - `code` (product code)
   
   - **product_requests table** (IVR/DocuSeal fields):
     - `ivr_required` (boolean)
     - `ivr_bypass_reason` (text)
     - `ivr_sent_at` (timestamp)
     - `ivr_signed_at` (timestamp)
     - `ivr_document_url` (string)
     - `docuseal_submission_id` (string)
     - `docuseal_template_id` (string)
     - Manufacturer approval fields
     - Clinical attestation fields
     - Additional order fields

3. **Adds Foreign Key Constraints**:
   - Only after ensuring all referenced tables exist

### Dependency Order

```
1. Core Tables (no dependencies):
   - accounts
   - manufacturers
   - categories

2. User/Auth Tables:
   - users (depends on: accounts)
   - roles
   - permissions

3. Organization Tables:
   - organizations (depends on: accounts)
   - facilities (depends on: organizations)

4. Product Tables:
   - msc_products (depends on: manufacturers, categories)

5. DocuSeal Tables:
   - docuseal_folders
   - docuseal_templates (depends on: docuseal_folders)
   - docuseal_submissions (depends on: docuseal_folders)

6. Order Tables:
   - product_requests (depends on: users, facilities)
   - orders (depends on: users)
   - order_items (depends on: orders, msc_products)

7. Junction Tables:
   - role_permission (depends on: roles, permissions)
   - user_role (depends on: users, roles)
   - facility_user (depends on: facilities, users)
   - product_request_products (depends on: product_requests, msc_products)
   - provider_products (depends on: users, msc_products)
```

## Database Seeder Order

The DatabaseSeeder has been updated to:

1. **Disable foreign key checks** before truncating
2. **Truncate tables in reverse dependency order** (children before parents)
3. **Handle missing tables gracefully** with try-catch
4. **Call seeders in correct order**:
   - CategoriesAndManufacturersSeeder (creates reference data first)
   - DocusealFolderSeeder (creates folders before templates)
   - DocusealTemplateSeeder (creates templates that reference folders)

## Testing

Run the migration order test script:
```bash
./tests/Manual/test-migration-order.sh
```

This will:
1. Drop all tables
2. Run the fix migration
3. Run all migrations
4. Verify table structure
5. Run seeders
6. Verify relationships

## Common Issues and Solutions

### Issue: Foreign key constraint errors
**Solution**: The fix migration creates all referenced tables before adding constraints

### Issue: Seeder fails with "table not found"
**Solution**: DatabaseSeeder now checks if tables exist before truncating

### Issue: Missing columns in existing tables
**Solution**: The fix migration checks for column existence before adding

## Best Practices

1. **Always check dependencies** when creating new migrations
2. **Use timestamps in migration names** to control order
3. **Check for table/column existence** in migrations that might run multiple times
4. **Disable foreign key checks** when truncating related tables
5. **Test migrations** on a fresh database regularly