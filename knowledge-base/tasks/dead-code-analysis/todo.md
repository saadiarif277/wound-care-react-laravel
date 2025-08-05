# Dead Code Analysis - Task Tracking

## Task List

- [x] Search for unused Eloquent models (no references in controllers/services)
- [x] Find controllers without routes pointing to them
- [x] Search for services that are never injected or called
- [x] Identify React components not imported anywhere
- [x] Find PHP traits that are not used
- [x] Look for migrations that might be obsolete
- [x] Check for orphaned test files

## Summary of Findings

### 1. Unused Eloquent Models
Found models with minimal or no references:
- **IVRTemplateField** - Only referenced in import scripts (scripts/import_ivr_fields*.php)
- **ClinicalOpportunityAction** - No references found in controllers or services

### 2. Controllers Without Routes
Found several controllers that appear to have no routes:
- **ContactsController** - No routes found
- **Traits/QuickRequestDocuSealIntegration** - This is a trait, not a controller

### 3. Unused Services
Services with no or minimal usage:
- **TemplateIntelligenceService** - No references found
- **ValidationEngineMonitoring** - No references found

### 4. Unused React Components
Components with no imports:
- **NoAccess.tsx** (resources/js/Pages/Errors/NoAccess.tsx) - No imports found

### 5. Unused PHP Traits
Traits with no usage:
- **InheritsOrganizationFromParent** - No usage found
- **BelongsToPolymorphicOrganization** - No usage found

### 6. Potentially Obsolete Migrations
Tables created by migrations but with no corresponding models:
- activity_logs
- cache_locks
- docuseal_form_flows
- episode_docuseal_submissions
- facility_sales_rep_assignments
- failed_jobs (Laravel framework table)
- field_mapping_analytics
- field_mapping_cache
- field_mapping_logs
- insurance_product_rules

### 7. Orphaned Test Files
No orphaned test files were found - all test files appear to have corresponding classes.

## Review

### Analysis Summary
The dead code analysis revealed several areas where code cleanup could improve maintainability:

1. **Models**: 2 models appear to be unused or only used in scripts
2. **Controllers**: Several controllers lack route definitions
3. **Services**: 2 services have no references
4. **React Components**: 1 component appears unused
5. **PHP Traits**: 2 traits have no usage
6. **Database Tables**: 10 tables have no corresponding models (some are framework tables)

### Recommendations

1. **Immediate Actions**:
   - Remove `TemplateIntelligenceService` and `ValidationEngineMonitoring` if confirmed unused
   - Remove unused traits `InheritsOrganizationFromParent` and `BelongsToPolymorphicOrganization`
   - Remove `NoAccess.tsx` React component if not needed

2. **Requires Investigation**:
   - `IVRTemplateField` model - check if import scripts are still needed
   - `ClinicalOpportunityAction` model - verify if this is planned for future use
   - Migration tables without models - some might be caching/logging tables that don't need models

3. **Framework/System Tables** (Keep):
   - `failed_jobs` - Laravel queue system
   - `cache_locks` - Laravel cache system

### Note
Some items marked as "unused" might be:
- Used dynamically (e.g., through reflection or dynamic class loading)
- Part of future features under development
- Required by third-party packages
- Used in configuration files or database seeders

Always verify with the team before removing any code.