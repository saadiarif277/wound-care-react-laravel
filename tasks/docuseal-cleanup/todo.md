# DocuSeal Cleanup Task

## Objective
Search the entire codebase for any remaining references to DocuSeal/Docuseal/docuseal (case-insensitive) and identify any broken imports or dependencies.

## Todo Items
- [x] Search for direct text references to DocuSeal/Docuseal/docuseal in all files
- [x] Check PHP files for DocuSeal references
- [x] Check TypeScript/JavaScript files for DocuSeal references
- [x] Check configuration files
- [x] Check environment files and examples
- [x] Check composer.json and package.json for DocuSeal dependencies
- [x] Check database migrations for DocuSeal columns
- [x] Check routes for DocuSeal endpoints
- [x] Check views and email templates
- [x] Search for broken imports or missing services
- [x] Check for any remaining DocuSeal-related classes or interfaces

## Progress Completed

### Phase 1: Remove Service Dependencies ✅
- [x] Removed DocusealService import from AppServiceProvider.php
- [x] Fixed HandleQuickRequestSubmitted.php to remove ProcessQuickRequestToDocusealAndFhir job
- [x] Removed all commented DocusealService imports
- [x] Fixed OrderController show method to remove DocusealService parameter

### Phase 2: Update Models and Database ✅
- [x] Removed DocusealTemplate imports from multiple files
- [x] Removed DocusealSubmission import from PatientManufacturerIVREpisode
- [x] Updated model relationships to use placeholders for PDF system
- [x] Removed DocusealFolder imports from Manufacturer model
- [x] Created migration to remove DocuSeal columns

### Phase 3: Update Configuration ✅
- [x] Removed DocuSeal settings from .env and .env.example
- [x] Updated config/features.php to use PDF instead of DocuSeal
- [x] Updated FeatureFlagService with isPdfEnabled() method
- [x] Removed DocuSeal environment variables from GitHub workflow
- [x] Removed DocuSeal packages from composer.json

### Phase 4: Update Frontend References ✅
- Frontend already uses Step7PDFIIVR component
- TypeScript interfaces still have docuseal properties but these are used for backward compatibility

### Phase 5: Clean Up Tests ✅
- [x] Updated test scripts to remove DocusealService usage
- [x] No DocuSeal references found in views

## Review Summary

The DocuSeal cleanup has been successfully completed. All direct references to DocuSeal services, models, and configurations have been removed or replaced with placeholders for the PDF system.

### Remaining References (Intentional)
1. **Database columns**: Still exist in database but migration created to remove them
2. **TypeScript interfaces**: Properties like `docuseal_submission_id` remain for backward compatibility
3. **Configuration files**: Manufacturer configs still have `docuseal_template_id` fields that need to be updated with PDF template IDs
4. **Comments**: Various TODO comments reference DocuSeal as context for what was replaced

### Next Steps
1. Run `composer update` to remove DocuSeal packages
2. Run `php artisan migrate` to remove DocuSeal columns from database
3. Implement PDF service to replace DocuSeal functionality
4. Update manufacturer configurations with PDF template IDs
5. Update TypeScript interfaces once PDF system is fully implemented

### Side Issue Found
- CSRF 419 error on create-draft-episode endpoint - this appears to be unrelated to DocuSeal cleanup and may be a session/authentication issue