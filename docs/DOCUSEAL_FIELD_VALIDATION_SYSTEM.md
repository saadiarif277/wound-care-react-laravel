# DocuSeal Field Validation System 🛡️

## Overview

This system provides a **"fix for life"** solution to prevent DocuSeal field mapping errors by automatically validating manufacturer configurations against actual DocuSeal template fields and filtering out invalid mappings.

### The Problem This Solves

Previously, manufacturer configurations could reference fields that don't exist in DocuSeal templates, causing "Unknown field: [field_name]" errors during form submission. This happened when:

- Templates were updated in DocuSeal but configurations weren't updated
- Configurations were manually created with incorrect field names
- Templates were replaced or modified

### The Solution

A comprehensive validation and synchronization system that:

1. **Validates configurations** against actual template fields
2. **Automatically filters** invalid fields during runtime  
3. **Provides commands** for validation and auto-fixing
4. **Schedules automatic** synchronization
5. **Prevents errors** before they reach DocuSeal

---

## Components

### 1. TemplateFieldValidationService

**Location**: `app/Services/DocuSeal/TemplateFieldValidationService.php`

Core service that:
- Fetches actual field names from DocuSeal templates (with caching)
- Validates manufacturer configurations against templates
- Filters configurations to only include valid fields
- Auto-fixes configuration files by commenting out invalid fields

### 2. Enhanced UnifiedFieldMappingService

**Location**: `app/Services/UnifiedFieldMappingService.php` 

Enhanced with:
- Automatic field validation during configuration loading
- Runtime field filtering before DocuSeal submission
- Intelligent error prevention

### 3. Artisan Commands

#### `docuseal:validate-configs`
**Location**: `app/Console/Commands/ValidateDocuSealConfigs.php`

Validate manufacturer configurations against DocuSeal templates.

```bash
# Validate all configurations
php artisan docuseal:validate-configs

# Validate specific manufacturer
php artisan docuseal:validate-configs --manufacturer="ADVANCED SOLUTION"

# Auto-fix invalid configurations
php artisan docuseal:validate-configs --fix

# Clear cache and validate
php artisan docuseal:validate-configs --clear-cache
```

#### `docuseal:sync-templates`
**Location**: `app/Console/Commands/SyncDocuSealTemplates.php`

Automatically sync template changes with configurations.

```bash
# Sync all configurations
php artisan docuseal:sync-templates

# Dry run (show what would be changed)
php artisan docuseal:sync-templates --dry-run

# Force sync even for valid configs
php artisan docuseal:sync-templates --force
```

### 4. Automatic Scheduling

**Location**: `app/Console/Kernel.php`

Automatic daily and weekly synchronization:
- **Daily at 2 AM**: Template synchronization
- **Weekly on Sundays at 3 AM**: Full configuration validation

---

## How It Works

### 1. Configuration Loading (getManufacturerConfig)

When a manufacturer configuration is loaded:

```php
// Before: Returns raw configuration
$config = $this->loadManufacturerFromFile($name);

// After: Validates and filters configuration
if ($this->fieldValidator && isset($config['docuseal_template_id'])) {
    $validatedConfig = $this->fieldValidator->filterValidFields($config);
    return $validatedConfig;
}
```

### 2. Runtime Field Filtering (convertToDocusealFields)

Before sending data to DocuSeal:

```php
// Fetch actual template fields
$templateFields = $this->fieldValidator->getTemplateFields($templateId);

// Filter out invalid field mappings
$fieldNameMapping = array_filter($fieldNameMapping, function($docuSealFieldName) use ($templateFields) {
    return in_array($docuSealFieldName, $templateFields);
});
```

### 3. Template Field Caching

Template fields are cached for 1 hour to minimize API calls:

```php
$cacheKey = 'docuseal_template_fields:' . $templateId;
return Cache::remember($cacheKey, 3600, function () use ($templateId) {
    return $this->docuSealClient->getTemplate($templateId)['fields'];
});
```

---

## Usage Guide

### Initial Setup

1. **Run validation** to see current status:
   ```bash
   php artisan docuseal:validate-configs
   ```

2. **Auto-fix** any issues found:
   ```bash
   php artisan docuseal:validate-configs --fix
   ```

3. **Verify** fixes worked:
   ```bash
   php artisan docuseal:validate-configs
   ```

### Regular Maintenance

The system runs automatically, but you can manually:

1. **Check for template changes**:
   ```bash
   php artisan docuseal:sync-templates --dry-run
   ```

2. **Apply template changes**:
   ```bash
   php artisan docuseal:sync-templates
   ```

3. **Validate after changes**:
   ```bash
   php artisan docuseal:validate-configs
   ```

### Troubleshooting

#### If validation fails:

1. Check DocuSeal API connectivity
2. Verify template IDs are correct
3. Clear cache: `--clear-cache`
4. Check logs for detailed errors

#### If auto-fix doesn't work:

1. Manually review the configuration file
2. Compare with actual template fields in DocuSeal
3. Remove or comment out invalid field mappings

---

## Configuration File Format

### Valid Configuration

```php
<?php
return [
    'id' => 11,
    'name' => 'ADVANCED SOLUTION',
    'docuseal_template_id' => '1199885', // Required for validation
    'docuseal_field_names' => [
        'patient_name' => 'Patient Name',        // ✅ Valid - exists in template
        'facility_name' => 'Facility Name',      // ✅ Valid - exists in template
        // REMOVED: Field doesn't exist in DocuSeal template
        // 'email' => 'Email',                   // ❌ Invalid - commented out by auto-fix
    ],
    'fields' => [
        // Field mapping configuration...
    ]
];
```

### Auto-Fix Changes

When auto-fix runs, it:

1. **Comments out** invalid field mappings
2. **Adds timestamps** for audit trail
3. **Preserves** original configuration
4. **Logs changes** for review

---

## Monitoring & Alerts

### Log Files

- **Sync logs**: `storage/logs/docuseal-sync.log`
- **Validation logs**: `storage/logs/docuseal-validation.log`
- **Application logs**: Standard Laravel logs with validation events

### Email Alerts

Scheduled tasks send email alerts on failure to the configured admin email.

Configure in your `.env`:
```env
MAIL_ADMIN_EMAIL=admin@yourcompany.com
```

### Key Log Events

```php
Log::info('Applied field validation filter to manufacturer config', [
    'manufacturer' => $name,
    'template_id' => $templateId,
    'original_field_count' => 15,
    'filtered_field_count' => 12
]);
```

---

## Benefits

### ✅ Prevents Errors
- No more "Unknown field" errors from DocuSeal
- Automatic filtering of invalid fields
- Graceful handling of template changes

### ✅ Self-Healing
- Configurations automatically adapt to template changes
- Invalid fields are filtered out at runtime
- Auto-fix capabilities for maintenance

### ✅ Maintainable
- Clear validation commands for debugging
- Comprehensive logging and monitoring
- Scheduled automatic maintenance

### ✅ Backward Compatible
- Works with existing configurations
- Graceful fallback if validation fails
- No breaking changes to existing workflows

---

## Advanced Usage

### Custom Validation Rules

Extend `TemplateFieldValidationService` to add custom validation logic:

```php
// Check for critical missing fields
$criticalFields = ['patient_name', 'patient_dob', 'facility_name'];
foreach ($criticalFields as $critical) {
    if (!isset($validFields[$critical])) {
        $errors[] = "Missing critical field: $critical";
    }
}
```

### Integration with CI/CD

Add validation to your deployment pipeline:

```bash
# In your deployment script
php artisan docuseal:validate-configs
if [ $? -ne 0 ]; then
    echo "DocuSeal configuration validation failed!"
    exit 1
fi
```

### API Integration

Use the validation service programmatically:

```php
$validator = app(TemplateFieldValidationService::class);
$validation = $validator->validateManufacturerConfig($config);

if (!$validation['valid']) {
    // Handle validation errors
    foreach ($validation['errors'] as $error) {
        Log::error("Config validation error: $error");
    }
}
```

---

## Future Enhancements

- [ ] Real-time webhook integration with DocuSeal
- [ ] Visual configuration editor with validation
- [ ] Template field discovery and mapping suggestions
- [ ] Configuration versioning and rollback
- [ ] Multi-environment template synchronization

---

## Support

For issues or questions about the DocuSeal Field Validation System:

1. Check the logs for detailed error messages
2. Run validation commands with verbose output
3. Review this documentation for troubleshooting steps
4. Contact the development team with specific error details

**Remember**: This system is designed to be a "fix for life" - it should prevent DocuSeal field mapping issues permanently through automatic validation and synchronization. 