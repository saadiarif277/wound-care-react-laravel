# Manufacturer Field Mapping Architecture - Single Source of Truth

## Overview
This document outlines the clean, unified architecture for handling manufacturer-specific field mappings in the MSC Wound Portal system. Our goal is to eliminate all hardcoding and service overlap by establishing clear responsibilities and data flow.

## Core Principles

1. **Single Source of Truth**: `/config/manufacturers/*.php` files are the ONLY source of truth for manufacturer field requirements
2. **Role-Based Data Extraction**: EntityDataService handles all role-based logic for facility/provider data
3. **No Hardcoding**: All manufacturer-specific logic is configuration-driven
4. **Targeted Field Extraction**: Only fields defined in manufacturer configs are extracted and mapped

## Architecture Components

### 1. Manufacturer Configuration Files (`/config/manufacturers/*.php`)
- **Purpose**: Define which fields each manufacturer requires for their DocuSeal forms
- **Structure**:
  ```php
  return [
      'docuseal_field_names' => [
          'internal_key' => 'docuseal_field_name',
          // Only fields listed here will be extracted
      ],
      'validation_rules' => [...],
      'business_rules' => [...]
  ];
  ```
- **Examples**: `acz-&-associates.php`, `biowound-solutions.php`, etc.

### 2. EntityDataService (New)
- **Purpose**: Extract facility/provider/organization data based on user role
- **Responsibilities**:
  - Determine data sources based on user role (Provider vs Office Manager)
  - Extract only requested fields from database entities
  - Handle role-based logic cleanly
- **Key Methods**:
  - `extractDataByRole($userId, $facilityId, $providerId, $requiredFields)`
  - `extractProviderData($providerId, $fields)`
  - `extractFacilityData($facilityId, $fields)`
  - `extractOrganizationData($organizationId, $fields)`

### 3. QuickRequestOrchestrator
- **Current State**: Cleaned of all hardcoded manufacturer logic
- **Responsibilities**:
  - Orchestrate the quick request flow
  - Use EntityDataService for data extraction
  - Pass data to appropriate services
- **Changes Made**:
  - Removed all ACZ-specific hardcoding
  - Removed manufacturer name checks
  - Now uses dynamic configuration loading

### 4. UnifiedFieldMappingService
- **Purpose**: Map extracted data to manufacturer field names
- **Required Changes**:
  - Remove all hardcoded manufacturer logic
  - Use manufacturer configs as the only source
  - Call EntityDataService for data extraction
- **Key Methods**:
  - `mapFieldsForManufacturer($manufacturerName, $extractedData)`
  - `loadManufacturerConfig($manufacturerName)`

### 5. Medical AI Services
- **OptimizedMedicalAiService.php**: PHP wrapper for AI field enhancement
- **medical_ai_service.py**: Python AI service for intelligent field mapping
- **Required Changes**:
  - Remove any hardcoded manufacturer logic
  - Use manufacturer configs for field requirements
  - Focus only on intelligent field enhancement

## Data Flow

```
1. User fills Quick Request form
   └─> Role detected (Provider or Office Manager)
   
2. QuickRequestOrchestrator receives request
   └─> Loads manufacturer config from /config/manufacturers/
   └─> Gets list of required fields from config
   
3. EntityDataService.extractDataByRole() called
   └─> Provider: Uses their profile + selected facility
   └─> Office Manager: Uses their facility + selected provider
   └─> Returns ONLY the requested fields
   
4. UnifiedFieldMappingService maps data
   └─> Uses manufacturer config for field name mapping
   └─> No hardcoding, pure configuration
   
5. (Optional) AI Enhancement
   └─> OptimizedMedicalAiService enhances mappings
   └─> Uses manufacturer config for context
   
6. DocuSeal receives final mapped data
   └─> Only contains fields defined in manufacturer config
```

## Services to Remove/Refactor

### To Remove Completely:
1. **IVRFieldMapping.php** (Model) - Database-driven mappings conflict with file configs
2. **ivr-mapping.php** (Config) - Empty/unused
3. **field-mapping.php** (Config) - Empty/unused
4. **FhirToIvrFieldMapper.php** - Overlaps with EntityDataService responsibilities

### To Refactor:
1. **MappingRulesEngine.php** - Keep for transformation rules but remove manufacturer logic
2. **UnifiedFieldMappingService.php** - Remove hardcoding, use configs only
3. **OptimizedMedicalAiService.php** - Remove hardcoding, focus on enhancement

## Implementation Checklist

- [x] Remove hardcoded logic from QuickRequestOrchestrator
- [x] Create EntityDataService with role-based extraction
- [x] Update UnifiedFieldMappingService to use configs only
- [x] Clean up OptimizedMedicalAiService (no hardcoding found)
- [x] Remove redundant services (IVRFieldMapping, FhirToIvrFieldMapper, empty configs)
- [x] Update medical_ai_service.py to align with new architecture (no hardcoding found)
- [ ] Test complete flow with multiple manufacturers

## Benefits

1. **No Service Overlap**: Each service has a clear, single responsibility
2. **Easy Manufacturer Addition**: Just add a new config file
3. **Maintainable**: All manufacturer logic in one place
4. **Testable**: Clear data flow and responsibilities
5. **Performant**: Only extract needed fields, no waste

## Example: ACZ & Associates Flow

1. Config defines fields: `name`, `email`, `physician_npi`, etc.
2. EntityDataService extracts ONLY those fields from database
3. UnifiedFieldMappingService maps to DocuSeal names
4. No extra fields, no service overlap, clean data flow 

## Summary of Changes

### What We've Accomplished

1. **Created EntityDataService** - A clean, role-based service that extracts only the fields requested from the database entities
   - Handles Provider vs Office Manager logic cleanly
   - Extracts facility, provider, and organization data based on user role
   - Only extracts fields that are actually needed (no waste)

2. **Cleaned QuickRequestOrchestrator**
   - Removed all hardcoded manufacturer logic (ACZ, BioWound, etc.)
   - Updated to use EntityDataService for data extraction
   - Uses extractTargetedDocusealData for manufacturer-specific field extraction
   - Now purely configuration-driven

3. **Removed Redundant Services**
   - Deleted IVRFieldMapping.php (database-driven mapping model)
   - Deleted FhirToIvrFieldMapper.php (replaced by EntityDataService)
   - Deleted empty config files (ivr-mapping.php, field-mapping.php)
   - Fixed UnifiedFieldMappingService to handle missing configs gracefully

4. **Verified Clean Services**
   - UnifiedFieldMappingService already uses manufacturer configs
   - OptimizedMedicalAiService has no hardcoded manufacturer logic
   - medical_ai_service.py has no hardcoded manufacturer logic
   - MappingRulesEngine kept for transformation rules (no manufacturer hardcoding)

### Current Clean Architecture

```
User Request → QuickRequestOrchestrator
    ↓
    ├─→ Load Manufacturer Config (/config/manufacturers/*.php)
    ├─→ Extract Required Fields List
    ├─→ EntityDataService.extractDataByRole()
    │     ├─→ Provider: Own data + Selected facility
    │     └─→ Office Manager: Own facility + Selected provider
    ├─→ Extract metadata fields (patient, insurance, clinical)
    ├─→ Map to DocuSeal field names (using config)
    └─→ Send to DocuSeal (only configured fields)
```

### Key Benefits Achieved

1. **Single Source of Truth**: Manufacturer configs in /config/manufacturers/
2. **No Service Overlap**: Each service has one clear responsibility
3. **No Hardcoding**: All manufacturer logic is configuration-driven
4. **Efficient**: Only extracts fields that are actually needed
5. **Maintainable**: Easy to add new manufacturers (just add config file)
6. **Role-Based**: Clean separation of Provider vs Office Manager logic

### Next Steps

1. Test the complete flow with ACZ & Associates
2. Test with other manufacturers (BioWound, Advanced Solution, etc.)
3. Monitor for any edge cases or missing fields
4. Document any new manufacturer additions 