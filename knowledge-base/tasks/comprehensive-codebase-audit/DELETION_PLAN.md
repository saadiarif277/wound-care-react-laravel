# Detailed Deletion Plan - File by File Review

## 1. DUPLICATE UI COMPONENTS (Highest Priority)

### DELETE - Entire Duplicate shadcn/ui Directory:
```
/resources/js/Pages/QuickRequest/Orders/ui/
├── accordion.tsx
├── alert-dialog.tsx
├── alert.tsx
├── avatar.tsx
├── badge.tsx
├── button.tsx
├── calendar.tsx
├── card.tsx
├── checkbox.tsx
├── collapsible.tsx
├── command.tsx
├── dialog.tsx
├── drawer.tsx
├── dropdown-menu.tsx
├── form.tsx
├── hover-card.tsx
├── input.tsx
├── label.tsx
├── menubar.tsx
├── navigation-menu.tsx
├── popover.tsx
├── progress.tsx
├── radio-group.tsx
├── scroll-area.tsx
├── select.tsx
├── separator.tsx
├── sheet.tsx
├── skeleton.tsx
├── slider.tsx
├── switch.tsx
├── table.tsx
├── tabs.tsx
├── textarea.tsx
├── toast.tsx
├── toaster.tsx
├── toggle-group.tsx
├── toggle.tsx
├── tooltip.tsx
└── use-toast.ts
```
**Reason**: Exact duplicate of `/resources/js/Components/ui/` directory

### DELETE - Duplicate Button Components:
```
/resources/js/Components/Button.tsx
/resources/js/Components/PrimaryButton.tsx
/resources/js/Components/SecondaryButton.tsx
/resources/js/Components/DangerButton.tsx
```
**Keep**: `/resources/js/Components/ui/button.tsx` (shadcn version)

### DELETE - Duplicate Card Components:
```
/resources/js/Components/Card.tsx
/resources/js/Components/CardContainer.tsx
```
**Keep**: `/resources/js/Components/ui/card.tsx` (shadcn version)

### DELETE - Duplicate Input Components:
```
/resources/js/Components/TextInput.tsx
/resources/js/Components/InputField.tsx
```
**Keep**: `/resources/js/Components/ui/input.tsx` (shadcn version)

### DELETE - Duplicate Address Autocomplete Components:
```
/resources/js/Components/GooglePlacesAutocomplete.tsx
/resources/js/Components/GoogleAddressAutocomplete.tsx
/resources/js/Pages/QuickRequest/components/AddressAutocomplete.tsx
/resources/js/Pages/Provider/Products/components/PlacesAutocomplete.tsx
```
**Keep**: `/resources/js/Components/AddressAutocomplete.tsx` (most feature-complete version)

### DELETE - Duplicate Toast Hooks:
```
/resources/js/Pages/QuickRequest/Orders/hooks/use-toast.ts
/resources/js/Pages/Admin/hooks/use-toast.ts
```
**Keep**: `/resources/js/hooks/use-toast.ts` (root hooks directory)

### DELETE - Duplicate Mobile Detection Hooks:
```
/resources/js/Pages/QuickRequest/Orders/hooks/use-mobile.tsx
/resources/js/Pages/Admin/hooks/use-mobile.tsx
```
**Keep**: `/resources/js/hooks/use-mobile.tsx` (root hooks directory)

## 2. BACKEND DUPLICATES

### DELETE - Duplicate Models:
```
/app/DocusealSubmission.php
```
**Keep**: `/app/Models/Docuseal/DocusealSubmission.php` (properly organized)

### DELETE - Unused Models:
```
/app/Models/ClinicalOpportunityAction.php (no references found)
/app/Models/IVRTemplateField.php (only used in import scripts)
```

### DELETE - Duplicate Controllers:
```
/app/Http/Controllers/EligibilityController.php (if keeping API version)
```
**Keep**: `/app/Http/Controllers/Api/EligibilityController.php`

### DELETE - Unused Controllers:
```
/app/Http/Controllers/ContactsController.php (no routes defined)
```

## 3. UNUSED SERVICES

### DELETE - Services with No References:
```
/app/Services/TemplateIntelligenceService.php
/app/Services/ValidationEngineMonitoring.php
```

## 4. UNUSED TRAITS

### DELETE - PHP Traits with No Usage:
```
/app/Traits/InheritsOrganizationFromParent.php
/app/Traits/BelongsToPolymorphicOrganization.php
```

## 5. UNUSED REACT COMPONENTS

### DELETE - Components Never Imported:
```
/resources/js/Pages/Errors/NoAccess.tsx
```

## 6. UNUSED DEPENDENCIES

### DELETE via composer remove:
```bash
# PHP packages to remove
composer remove aws/aws-sdk-php
composer remove beyondcode/laravel-websockets
composer remove intervention/image
composer remove league/flysystem-aws-s3-v3
composer remove predis/predis
composer remove fruitcake/php-cors
composer remove symfony/css-selector
composer remove symfony/dom-crawler
```

### DELETE via npm uninstall:
```bash
# JavaScript packages to remove
npm uninstall @azure/ai-inference
npm uninstall @azure/core-auth
npm uninstall @azure/core-client
npm uninstall @azure/core-rest-pipeline
npm uninstall @azure/core-sse
npm uninstall @azure/core-util
npm uninstall @azure/logger
npm uninstall openai
npm uninstall react-step-wizard
npm uninstall pdfjs-dist
npm uninstall classnames  # Keep clsx instead
npm uninstall react-hot-toast  # Keep sonner instead
npm uninstall @types/jquery
npm uninstall @types/pdfjs-dist
npm uninstall @types/react-highlight-words
npm uninstall @types/react-router-dom
npm uninstall @types/react-select
```

## 7. TEST/DEBUG ROUTES TO REMOVE

### DELETE from routes/web.php:
- Line 1424-1425: Test OCR route (unprotected)
- Line 1431-1433: Debug route (unprotected)
- Line 1446-1449: Test Availity routes
- Line 1557-1558: Test quick request route

### DELETE from routes/api.php:
- Lines with duplicate manufacturer routes (keep only one set)

## 8. DEAD DATABASE MIGRATIONS (Investigate Before Deleting)

These tables have no corresponding models - verify they're not used before deleting migrations:
```
analytics_events
api_logs
cache_entries
error_logs
field_mapping_suggestions
import_logs
notification_logs
ocr_logs
performance_metrics
template_field_mappings
```

## 9. DUPLICATE ROUTE DEFINITIONS

### DELETE from routes/api.php:
Keep only ONE instance of these route groups:
- Manufacturer routes (currently appears 3 times)
- Remove duplicate at lines ~100-150 and ~200-250

### DELETE from routes/web.php:
- Provider dashboard routes duplicate (lines 409-419, keep 1147-1157)

## 10. MISCELLANEOUS FILES TO REVIEW

### POTENTIALLY DELETE (need your confirmation):
```
/app/Services/AI/AzureAIService.php (if using OpenAI instead)
/app/Services/AI/OpenAIService.php (if using Azure instead)
/resources/js/Pages/Test/ (entire directory if not needed)
/resources/js/Pages/Debug/ (entire directory if not needed)
```

## Summary Statistics

- **Frontend files to delete**: ~75 files
- **Backend files to delete**: ~10 files
- **Dependencies to remove**: 25 packages
- **Routes to clean up**: ~20 duplicate/test routes
- **Estimated space saved**: 150MB+
- **Bundle size reduction**: 200-300KB

## IMPORTANT NOTES

1. **Before deleting models**, verify they're not referenced in:
   - Database seeders
   - Queue jobs
   - Scheduled tasks
   - Configuration files

2. **Before deleting services**, check for:
   - Dynamic instantiation via app() helper
   - References in service providers
   - Usage in queue jobs

3. **Before deleting components**, ensure no lazy imports:
   - Dynamic imports in routes
   - Conditional rendering
   - Code splitting boundaries

Would you like me to:
1. Add more files to the deletion list?
2. Move some files from DELETE to KEEP?
3. Create a backup script before deletion?
4. Generate a verification script to double-check references?