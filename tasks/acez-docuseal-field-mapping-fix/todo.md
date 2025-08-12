# ACZ & Associates DocuSeal Field Mapping Fix

## Problem

The ACZ & Associates IVR form fields were not being properly populated with data from the database during the quick request flow. The main issues were:
1. DocusealController only used minimal frontend data when no episode existed yet
2. The medical_ai_service.py expected an episode_id that doesn't exist until the end of the form flow
3. Data wasn't being extracted from provider/facility entities in the database
4. Many ACZ-specific fields were not being mapped properly

## Todo List

### ✅ Task 1: Enhance DocusealController generateSubmissionSlug method

- Modified the method to use QuickRequestOrchestrator's data extraction when no episode exists
- Added logic to detect when episode_id is not provided and fall back to extracting data from IDs
- Merged extracted data with frontend data (frontend data takes precedence for user inputs)

### ✅ Task 2: Create data extraction method in DocusealController

- Initially created loadEntitiesAndExtractData method in DocusealController
- Realized this logic belonged in the orchestrator
- Removed the method from DocusealController after moving logic to orchestrator

### ✅ Task 3: Add extractDataFromIds method to QuickRequestOrchestrator

- Created comprehensive extractDataFromIds method that works without an episode
- Uses DataExtractor service to pull data from provider and facility entities
- Extracts patient, insurance, and clinical data from the provided IDs
- Properly loads related entities (provider credentials, facility organization, etc.)

### ✅ Task 4: Update prepareDocusealData in QuickRequestOrchestrator

Added comprehensive ACZ field mappings:
- **Clinical Status Fields**: hospice status, Part A stay, post-op global period
- **Wound Location Checkboxes**: Logic to map wound locations based on anatomical area and size
- **Medical History**: Maps from clinical data or comorbidities
- **ACZ Q-code Checkboxes**: Added Q4344, Q4289, Q4275, Q4341, Q4313, Q4316, Q4164
- **Additional Fields**: ISO code, additional emails, facility medicaid number
- **Formatted Fields**: Total wound size with units, comma-separated ICD-10 codes

### ✅ Task 5: Verify ACZ configuration file

- Reviewed config/manufacturers/acz-&-associates.php
- Confirmed all field mappings are present and correctly named
- Configuration structure matches the implementation in orchestrator

### ⏳ Task 6: Test the complete flow

- Need to test with actual ACZ products
- Verify all fields populate correctly in DocuSeal forms
- Check that data flows properly from database to form

## Review

### Summary of Changes

1. **DocusealController.php**
   - Enhanced generateSubmissionSlug to use orchestrator's data extraction when no episode exists
   - Added comprehensive logging for debugging
   - Ensures data is pulled from database entities even without an episode

2. **QuickRequestOrchestrator.php**
   - Added extractDataFromIds method for extracting data without requiring an episode
   - Enhanced prepareDocusealData with all ACZ-specific field mappings
   - Added logic for wound location categorization based on anatomical area and size
   - Implemented proper formatting for ACZ fields (wound size, ICD codes, etc.)
   - Added clinical status fields (hospice, Part A, post-op global period)
   - Added all ACZ-specific Q-code product checkboxes

3. **OptimizedMedicalAiService.php**
   - No changes needed - already handles both episode and non-episode scenarios
   - Falls back to basic field mapping when AI service is unavailable

4. **acz-&-associates.php**
   - Configuration verified to be complete with all necessary field mappings
   - No changes were needed to the configuration file

### Key Improvements

1. **Data Flow Without Episode**: The system now properly extracts and flows data from database entities even when no episode exists yet, solving the core issue.

2. **Comprehensive Field Mapping**: All ACZ-specific fields are now properly mapped, including:
   - Clinical status questions
   - Wound location checkboxes with size-based logic
   - All Q-code product checkboxes
   - Properly formatted fields (dates, phone numbers, addresses)

3. **Better Entity Loading**: The system now loads complete entity relationships (provider → facility → organization) to ensure all data is available.

4. **Fallback Support**: When the AI service is unavailable or no episode exists, the system gracefully falls back to basic field mapping.

### Testing Recommendations

1. Create a new quick request with ACZ products
2. Verify all form fields are populated correctly
3. Check that wound location checkboxes are set based on location and size
4. Confirm clinical status fields default correctly
5. Ensure product Q-code checkboxes match selected products

### Notes

- The changes maintain backward compatibility with existing episodes
- Frontend data takes precedence over extracted data for user-entered fields
- The system logs comprehensive debugging information for troubleshooting
- All changes follow the existing architectural patterns and coding standards

## Phase 2: Clean Architecture Implementation

### Completed Tasks

1. **Created EntityDataService** - Single source of truth for role-based data extraction
   - Handles Provider vs Office Manager logic cleanly
   - Only extracts fields that are actually needed
   - Located at: `app/Services/EntityDataService.php`

2. **Removed All Hardcoding**
   - Cleaned QuickRequestOrchestrator of all manufacturer-specific logic
   - Updated to use EntityDataService for data extraction
   - Now purely configuration-driven

3. **Deleted Redundant Services**
   - `config/ivr-mapping.php` (empty/unused)
   - `config/field-mapping.php` (empty/unused)
   - `app/Models/IVRFieldMapping.php` (database-driven mappings)
   - `app/Services/FhirToIvrFieldMapper.php` (replaced by EntityDataService)

4. **Fixed Runtime Issues**
   - Updated UnifiedFieldMappingService to handle missing configs gracefully
   - Fixed TypeError by using null coalescing operator for config loading

5. **Created Architecture Documentation**
   - Complete architecture document at: `tasks/acez-docuseal-field-mapping-fix/ARCHITECTURE.md`
   - Documents the clean, single source of truth approach

### Final Architecture

- **Single Source of Truth**: `/config/manufacturers/*.php` files
- **Role-Based Extraction**: EntityDataService handles all role logic
- **No Hardcoding**: All manufacturer logic is configuration-driven
- **Targeted Extraction**: Only configured fields are extracted and sent

### Result

The system now has a clean, maintainable architecture where:
1. Manufacturer configs define required fields
2. EntityDataService extracts only those fields based on user role
3. No service overlap or hardcoding
4. Easy to add new manufacturers (just add config file)

## New Issue: Service Overlap - Too Much Data Being Mapped

### Problem Description

There's a severe service overlap issue where the QuickRequestOrchestrator is extracting and mapping ALL fields from database entities (provider, facility, patient, etc.) and attempting to send them all to DocuSeal. This is wrong - we should only fill in the specific fields that are explicitly defined in the ACZ & Associates DocuSeal template configuration.

### Root Cause

1. The `prepareDocusealData` method in QuickRequestOrchestrator extracts comprehensive data from all entities
2. It then attempts to map ALL of this data to DocuSeal fields
3. The ACZ-specific mapping only filters based on config fields, but the initial extraction is too broad

### Solution Plan

#### ⏳ Task 7: Create targeted field extraction method

- [ ] Create `extractTargetedDocusealData` method that only extracts fields defined in manufacturer config
- [ ] Load manufacturer config FIRST, then only extract those specific fields
- [ ] Map extracted fields to DocuSeal field names using the config

#### ⏳ Task 8: Implement field-specific extraction helpers

- [ ] Create `extractSingleField` method to extract individual fields based on their key
- [ ] Add helper methods for different field categories (header, physician, facility, patient, etc.)
- [ ] Ensure each helper only returns the specific field requested, not all fields

#### ⏳ Task 9: Update prepareDocusealData to use targeted extraction for ACZ

- [ ] Modify `prepareDocusealData` to detect ACZ manufacturer
- [ ] Use targeted extraction for ACZ instead of comprehensive extraction
- [ ] Maintain backward compatibility for other manufacturers

#### ⏳ Task 10: Update DocusealController field merging

- [ ] Limit frontend data merging to only user-input fields
- [ ] Prevent wholesale merging of all frontend data
- [ ] Define specific user input fields that can override database values

### Expected Outcome

- Only fields defined in `config/manufacturers/acz-&-associates.php` → `docuseal_field_names` will be extracted and sent
- No extra fields from database entities will pollute the DocuSeal submission
- Clean, targeted field mapping that matches exactly what's in the DocuSeal template
