# QuickRequest: Removing Hardcoded Data

## Overview
This document outlines the changes made to remove hardcoded data from the QuickRequest implementation and replace it with dynamic, database-driven configuration.

## Changes Made

### 1. Database Schema Additions

Created migration: `2025_01_21_000001_create_insurance_product_rules_table.php`

#### New Tables:
- **insurance_product_rules**: Stores insurance-specific product coverage rules
- **diagnosis_codes**: ICD-10 diagnosis codes for wound care
- **wound_types**: Types of wounds that can be treated
- **msc_contacts**: MSC contact information for different departments

#### Modified Tables:
- **products**: Added `mue_limit` field for Maximum Units of Eligibility

### 2. Configuration API Endpoints

Created `ConfigurationController.php` with the following endpoints:
- `GET /api/v1/configuration/insurance-product-rules`
- `GET /api/v1/configuration/diagnosis-codes`
- `GET /api/v1/configuration/wound-types`
- `GET /api/v1/configuration/product-mue-limits`
- `GET /api/v1/configuration/msc-contacts`
- `GET /api/v1/configuration/quick-request` (combined config)

### 3. React Hooks for Dynamic Data

Created `useQuickRequestConfig.ts` with hooks:
- `useQuickRequestConfig()`: Fetches all configuration data
- `useInsuranceProductRules()`: Fetches insurance-specific rules
- `useDiagnosisCodes()`: Fetches diagnosis codes
- `useMscContacts()`: Fetches MSC contact information

### 4. Code Changes

#### ProductSelectorQuickRequest.tsx
- Added TODO comments for transitioning to API-based configuration
- Updated hardcoded email from `ashley@mscadmin.com` to `admin@mscwoundcare.com`
- Added comments indicating data should come from database

#### manufacturerFields.ts
- Updated DocuSeal template IDs to use environment variables as fallback
- Added TODO comments for getting actual template IDs

#### QuickRequestController.php
- Updated provider credentials to fetch from actual provider profile
- Added TODO comments for moving diagnosis codes and wound types to database
- Updated DocuSeal email to use configuration
- Enhanced manufacturer template mapping with proper fallback

### 5. Configuration Updates

#### config/docuseal.php
- Added `account_email` configuration option

### 6. Data Seeder

Created `RemoveHardcodedDataSeeder.php` to populate:
- All current insurance product rules
- Diagnosis codes (yellow and orange categories)
- Wound types
- MUE limits for products
- MSC contact information

## Migration Steps

1. **Run the migration**:
   ```bash
   php artisan migrate
   ```

2. **Seed the configuration data**:
   ```bash
   php artisan db:seed --class=RemoveHardcodedDataSeeder
   ```

3. **Update environment variables**:
   ```env
   DOCUSEAL_ACCOUNT_EMAIL=limitless@mscwoundcare.com
   DOCUSEAL_TEMPLATE_ACZ=852440
   DOCUSEAL_TEMPLATE_ADVANCED=your_template_id
   DOCUSEAL_TEMPLATE_MEDLIFE=your_template_id
   DOCUSEAL_TEMPLATE_BIOWOUND=your_template_id
   DOCUSEAL_TEMPLATE_SKYE=your_template_id
   ```

## Future Implementation

To fully transition from hardcoded to dynamic data:

1. **Update React Components**:
   - Import and use the configuration hooks
   - Replace hardcoded values with API responses
   - Remove the fallback hardcoded data once database is stable

2. **Admin Interface**:
   - Create admin pages to manage:
     - Insurance product rules
     - Diagnosis codes
     - Wound types
     - MUE limits
     - MSC contacts

3. **DocuSeal Templates**:
   - Get actual template IDs from DocuSeal
   - Update environment variables
   - Remove fallback template IDs

4. **Provider Credentials**:
   - Ensure all providers have credentials stored in their profiles
   - Update the provider profile management to include credentials

## Benefits

1. **Flexibility**: Admin staff can update rules without code changes
2. **Accuracy**: Real-time updates to insurance rules and product coverage
3. **Maintainability**: Centralized configuration management
4. **Scalability**: Easy to add new insurance types, states, or products
5. **Auditability**: Database changes can be tracked and audited

## Backward Compatibility

The implementation includes fallback mechanisms:
- React hooks have fallback functions that return the original hardcoded values
- This ensures the application continues to work during the transition
- Once the database is populated and stable, fallback code can be removed

## Testing

1. Verify all API endpoints return expected data
2. Test React hooks with various parameters
3. Ensure fallback mechanisms work when API is unavailable
4. Validate that seeded data matches original hardcoded values