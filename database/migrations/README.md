# Database Migration - Consolidated Schema

## Overview

This directory contains a single consolidated migration file that encompasses the entire database schema for the MSC Wound Portal application. All previous individual migration files have been moved to the `backup/` directory.

## Consolidated Migration File

### `2025_01_01_000000_create_all_tables.php`

This comprehensive migration file creates all database tables in the correct dependency order. It includes:

1. **Core/Base Tables** - No dependencies
   - accounts, categories, manufacturers

2. **User/Auth Tables**
   - users, roles, permissions, role_permission, user_role

3. **Organization/Facility Tables**
   - organizations, facilities, facility_user, organization_users

4. **Product Tables**
   - msc_products, product_sizes, product_pricing_history

5. **Sales & Commission Tables**
   - msc_sales_reps, commission_rules, sales_rep_organizations
   - provider_sales_rep_assignments, facility_sales_rep_assignments

6. **DocuSeal/IVR Tables**
   - docuseal_folders, docuseal_templates, docuseal_submissions
   - patient_manufacturer_ivr_episodes, quick_request_submissions

7. **Order/Request Tables**
   - orders, order_items, product_requests, product_request_products
   - order_notes, order_documents, order_status_history, order_audit_logs

8. **Clinical/Validation Tables**
   - pre_authorizations, icd10_codes, cpt_codes
   - medicare_mac_validations, clinical_opportunities
   - msc_product_recommendation_rules, insurance_product_rules

9. **Provider/Patient Tables**
   - provider_profiles, provider_credentials, provider_products
   - provider_invitations, patient_associations, patient_ivr_status

10. **Field Mapping Tables**
    - ivr_field_mappings, ivr_template_fields, ivr_mapping_audit
    - canonical_fields, template_field_mappings, field_mapping_logs

11. **Payment & Commission Tables**
    - payments, commission_payouts, commission_records

12. **Onboarding Tables**
    - organization_onboarding, onboarding_checklists, onboarding_documents

13. **Audit & Logging Tables**
    - profile_audit_log, rbac_audit_logs, fhir_audit_logs, phi_audit_logs
    - activity_logs, manufacturer_contacts

14. **System Tables**
    - failed_jobs, password_resets, personal_access_tokens
    - sessions, cache, cache_locks, order_action_history

## Key Features

### Idempotency
The migration uses `if (!Schema::hasTable())` checks to ensure tables are only created if they don't already exist.

### Performance Optimization
- Comprehensive indexes on all foreign keys and commonly queried fields
- Composite indexes for multi-column queries
- Full-text indexes on searchable fields (for non-SQLite databases)

### Data Types
- UUIDs for certain primary keys (episodes, folders, templates, etc.)
- Proper enum types for status fields
- JSON columns for flexible data storage
- Decimal precision for financial fields

### Soft Deletes
Implemented on key business entities to maintain data integrity and audit trails.

## Running the Migration

```bash
# Run the migration
php artisan migrate

# Rollback if needed
php artisan migrate:rollback

# Fresh migration (drops all tables first)
php artisan migrate:fresh
```

## Backup Directory

The `backup/` directory contains all the original individual migration files for historical reference. These files show the evolution of the database schema over time but are no longer actively used.

## Benefits of Consolidation

1. **Single Source of Truth**: One file contains the entire database schema
2. **Faster Deployments**: No need to run 100+ individual migrations
3. **Easier Maintenance**: Schema changes are made in one place
4. **Clear Dependencies**: Tables are created in proper order
5. **Better Performance**: Single transaction for all table creation

## Important Notes

- Always test migrations on a development database first
- The consolidated migration includes all columns and modifications from the incremental migrations
- Foreign key constraints are properly ordered to avoid dependency issues
- The migration is designed to be run on a fresh database

## Future Changes

When making schema changes:
1. Update the consolidated migration file
2. Create a new incremental migration for existing databases
3. Document the changes in this README