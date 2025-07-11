# Analyze Form Fill Gaps - Todo List

## Objective
Investigate why forms aren't filling at 80%+ by analyzing sample forms and current mapping services to identify gaps in the mapping logic.

## Plan

### 1. Analyze Sample Forms Structure
- [x] Read several JSON forms from docs/data-and-reference/json-forms/
- [x] Identify common field patterns and naming conventions
- [x] Document the most frequently occurring fields
- [x] Note any complex or nested field structures

### 2. Review Current Mapping Services
- [x] Examine UnifiedFieldMappingService.php
- [x] Review CanonicalFieldMappingService.php
- [x] Check FieldMappingService.php
- [x] Analyze manufacturer-specific configurations

### 3. Identify Mapping Gaps
- [x] Compare form fields with mapping logic
- [x] Find fields that aren't being mapped properly
- [x] Check for field name variations not handled
- [x] Identify missing transformation logic

### 4. Analyze Field Filling Logic
- [x] Review how data is retrieved from FHIR
- [x] Check how fields are populated in DocuSeal
- [x] Identify data availability issues
- [x] Find logic gaps in field population

### 5. Document Findings
- [x] List unmapped or poorly mapped fields
- [x] Identify data source issues
- [x] Propose solutions for improving fill rate
- [x] Create prioritized action items

## Notes
- Focus on simplicity - avoid complex changes
- Look for quick wins that can improve fill rate
- Consider field name variations and aliases

## Key Findings

### 1. Mapping Coverage is Good (97.3%)
- CSV-based canonical field mapping shows 97.3% of fields are mapped
- Only 13 fields are intentionally ignored (headers/instructions)
- The mapping infrastructure is comprehensive

### 2. Primary Issues Preventing 80%+ Fill Rate

#### A. Data Source Gaps
1. **Provider Credentials Missing**: Many provider fields (DEA, state licenses) are stored in `providerCredentials` relationship but may not always be populated
2. **Facility Missing Fields**: Some facilities lack complete data (PTAN, group NPI, Medicare contractor)
3. **FHIR Data Incomplete**: Not all patient/provider data may be available in FHIR
4. **Insurance Details**: Secondary/tertiary insurance often missing

#### B. Field Name Mismatches
1. **CSV Mapping vs Config Mismatch**: The CSV mapping file uses different field keys than manufacturer configs
   - CSV: `physician_name` → Config: `physician_name` ✓
   - CSV: `provider_npi` → Config: `physician_npi` ✗
2. **Canonical Key Confusion**: Some canonical keys in CSV don't match the actual data keys:
   - `contact_phone` used for multiple different phone fields
   - `patient_name` incorrectly mapped to some non-name fields

#### C. Data Extraction Issues
1. **Nested Data Not Fully Utilized**: Provider profile and credentials are loaded but may not be fully extracted
2. **Missing Computed Fields**: Some fields like full addresses need to be computed from parts
3. **Boolean/Checkbox Fields**: Place of service checkboxes need special handling

#### D. Dynamic Mapping Not Always Used
- The system has AI-enhanced dynamic mapping but may fall back to static mapping
- Static mapping may not handle all field variations

### 3. Quick Win Solutions

#### Solution 1: Fix Field Key Mismatches
- Update manufacturer configs to use the same field keys as CSV mappings
- Ensure canonical keys in CSV accurately reflect the data structure

#### Solution 2: Enhance Data Extraction
- Ensure all provider credentials are properly extracted
- Add fallback logic for missing facility fields
- Compute full addresses from parts when needed

#### Solution 3: Improve Boolean Field Handling
- Convert place of service to individual boolean fields
- Handle Yes/No checkboxes properly

#### Solution 4: Add More Data Sources
- Pull more data from QuickRequest/ProductRequest
- Use session data for additional context
- Add manufacturer-specific fields from `manufacturer_fields` JSON

#### Solution 5: Enable Dynamic Mapping by Default
- Ensure AI-enhanced mapping is used when available
- Add better fallback handling when AI service fails