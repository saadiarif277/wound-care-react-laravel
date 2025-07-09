# Manufacturer Configuration Analysis

## Summary
Analysis of 11 manufacturer configurations to identify common field patterns, inconsistencies, and optimization opportunities for FHIR-compliant IVR form filling.

## Manufacturer Status Overview

### Complete Configurations (5)
1. **ACZ Associates** - Complete docuseal_field_names and fields mappings
2. **BioWound Solutions** - Complete configuration with template ID 
3. **Centurion Therapeutics** - Complete configuration with template ID
4. **MedLife Solutions** - Complete configuration with template ID (most comprehensive)
5. **Advanced Solution** - Complete configuration but missing template ID

### Partial Configurations (2)
1. **Advanced Health** - Basic structure but missing template ID and incomplete fields
2. **Advanced Solution Order Form** - Order form only, has template ID

### Empty/Placeholder Configurations (4)
1. **BioWerX** - TODO placeholders only
2. **Extremity Care LLC** - TODO placeholders only
3. **SKYE Biologics** - TODO placeholders only
4. **Total Ancillary** - TODO placeholders only

## Common Field Patterns

### 1. Patient Information (Universal)
All complete configurations include:
- `patient_name` (computed from first + last name)
- `patient_dob` (date of birth)
- `patient_gender` (M/F checkboxes)
- `patient_phone` (phone number)
- `patient_address` (full address)
- `patient_city`, `patient_state`, `patient_zip` (address components)

### 2. Provider Information (Universal)
All complete configurations include:
- `provider_name` / `physician_name` (provider full name)
- `provider_npi` / `physician_npi` (NPI number)
- `provider_specialty` / `physician_specialty` (medical specialty)
- `provider_ptan` / `physician_ptan` (PTAN number)

### 3. Facility Information (Universal)
All complete configurations include:
- `facility_name` (facility name)
- `facility_npi` (facility NPI)
- `facility_address` (facility address)
- `facility_phone` (facility phone)
- `facility_fax` (facility fax)

### 4. Insurance Information (Universal)
All complete configurations include:
- `primary_insurance_name` (primary insurance name)
- `primary_member_id` (member ID)
- `secondary_insurance_name` (secondary insurance - optional)
- `secondary_member_id` (secondary member ID - optional)

### 5. Clinical Information (Universal)
All complete configurations include:
- `wound_type` (type of wound)
- `wound_location` (wound location)
- `wound_size` or `wound_size_cm2` (wound dimensions)
- `wound_duration` (how long wound has existed)
- `diagnosis_code` (primary diagnosis ICD-10)
- `diagnosis_description` (diagnosis description)

### 6. Product Information (Universal)
All complete configurations include:
- `product_name` (product name)
- `product_hcpcs` (HCPCS/Q-code)
- `product_size` (product size)
- `product_quantity` (quantity requested)

### 7. Administrative Information (Universal)
All complete configurations include:
- `date_of_service` (expected service date)
- `place_of_service` (place of service code)
- `shipping_address` (shipping information)
- `special_instructions` (special instructions)

## Manufacturer-Specific Field Requirements

### BioWound Solutions (Most Complex)
- Request type checkboxes: `new_request`, `re_verification`, `additional_applications`, `new_insurance`
- Place of service checkboxes: `pos_11`, `pos_21`, `pos_24`, `pos_22`, `pos_32`, `pos_13`, `pos_12`
- Wound type checkboxes: `wound_dfu`, `wound_vlu`, `wound_chronic_ulcer`, `wound_dehisced_surgical`
- Product code checkboxes: `q4161`, `q4205`, `q4290`, `q4238`, `q4239`, `q4266`, `q4267`, `q4265`
- SNF status: `patient_snf_yes`, `patient_snf_no`, `snf_days`
- Prior authorization: `prior_auth_yes`, `prior_auth_no`

### MedLife Solutions (Most Comprehensive)
- Contact information: `name`, `email`, `phone`, `distributor_company`
- Tax ID information: `tax_id`, `practice_ptan`, `practice_npi`
- Clinical fields: `icd10_code_1`, `cpt_code_1`, `hcpcs_code_1`
- Global period: `patient_global_yes`, `patient_global_no`
- Insurance attachments: `insurance_card_attached`

### Centurion Therapeutics (Checkboxes)
- Wound status checkboxes: `check_new_wound`, `check_additional_application`, `check_reverification`
- Contact information: `name`, `email`, `phone`
- Patient mobile: `patient_mobile`
- Provider medicare: `provider_medicare_number`

### Advanced Solution (Standard)
- Standard fields similar to BioWound but simpler structure
- No checkbox mappings for complex fields
- Missing template ID (needs to be added)

### ACZ Associates (Basic)
- Standard field mappings
- Authorization number: `authorization_number`
- Urgency level: `urgency_level`
- Shipping details: separate city/state/zip fields

## Field Mapping Inconsistencies

### 1. Patient Name Fields
- **Issue**: Some configs reference `patient_first_name` and `patient_last_name` in computations but don't define these fields
- **Affected**: BioWound Solutions, Centurion Therapeutics, Advanced Solution
- **Solution**: Add missing field definitions for name components

### 2. Date Format Transformations
- **Inconsistency**: Different date formats across manufacturers
- **MedLife**: `date:m/d/Y`
- **BioWound**: `date:m/d/Y`
- **Solution**: Standardize on `m/d/Y` format

### 3. Phone Number Transformations
- **Inconsistency**: Some use `phone:US` transform, others don't
- **Solution**: Standardize on `phone:US` transformation

### 4. Address Field Structures
- **Issue**: Different address field naming conventions
- **Some use**: `address_line1`, `address_line2`
- **Others use**: `address`
- **Solution**: Support both formats with fallbacks

### 5. Insurance Field Naming
- **Issue**: Different field names for same data
- **Primary**: `primary_insurance_name` vs `insurance_name`
- **Member ID**: `primary_member_id` vs `insurance_member_id`
- **Solution**: Create field aliases in orchestrator

## Missing Template IDs

### Manufacturers Missing Template IDs
1. **Advanced Health** - `docuseal_template_id` is empty
2. **Advanced Solution** - `docuseal_template_id` is empty
3. **BioWerX** - `docuseal_template_id` is empty
4. **Extremity Care LLC** - `docuseal_template_id` is empty
5. **SKYE Biologics** - `docuseal_template_id` is empty
6. **Total Ancillary** - `docuseal_template_id` is empty

### Impact
- Without template IDs, these manufacturers cannot generate IVR forms
- Need to obtain template IDs from DocuSeal or create templates

## FHIR Resource Mapping Opportunities

### Patient Data (FHIR Patient Resource)
- **Demographics**: name, gender, birthDate, telecom, address
- **Identifier**: patient_display_id, medical record number
- **Current Usage**: Limited - mostly form data
- **Optimization**: Extract comprehensive patient data from FHIR Patient resource

### Provider Data (FHIR Practitioner Resource)
- **Demographics**: name, telecom, address
- **Qualifications**: specialty, license, credentials
- **Identifiers**: NPI, PTAN, DEA, license numbers
- **Current Usage**: Limited - mostly form data
- **Optimization**: Extract comprehensive provider data from FHIR Practitioner resource

### Facility Data (FHIR Organization Resource)
- **Demographics**: name, telecom, address
- **Identifiers**: NPI, PTAN, tax ID
- **Type**: facility type, place of service
- **Current Usage**: Limited - mostly form data
- **Optimization**: Extract comprehensive facility data from FHIR Organization resource

### Clinical Data (FHIR Condition/Encounter Resources)
- **Diagnosis**: ICD-10 codes, descriptions
- **Clinical Notes**: wound assessment, treatment history
- **Procedures**: CPT codes, HCPCS codes
- **Current Usage**: Minimal - mostly form data
- **Optimization**: Extract clinical data from FHIR clinical resources

### Insurance Data (FHIR Coverage Resource)
- **Payer**: insurance company name, phone
- **Coverage**: member ID, group number, policy number
- **Subscriber**: subscriber name, relationship
- **Current Usage**: Limited - mostly form data
- **Optimization**: Extract comprehensive insurance data from FHIR Coverage resource

## Field Completion Rate Analysis

### Current Issues
Based on previous field mapping analysis:
- **BioWound Solutions**: 40.52% completion rate (5 required fields missing)
- **Missing fields**: Often computed fields that are actually present
- **Common gaps**: Sales rep info, contact details, territory information

### Optimization Opportunities
1. **Sales Rep/Contact Information**: Extract from current user profile
2. **Territory Information**: Extract from user's organization
3. **Clinical Assessment**: Extract from FHIR clinical resources
4. **Insurance Details**: Extract from FHIR coverage resources
5. **Provider Credentials**: Extract from FHIR practitioner resource

## Validation Requirements

### FHIR Compliance
- Ensure all FHIR resources are properly structured
- Validate FHIR resource references and relationships
- Check for required FHIR fields and data types

### IVR Form Completeness
- Validate all required fields are populated
- Check field format compliance (dates, phones, etc.)
- Ensure manufacturer-specific requirements are met

### Data Consistency
- Verify patient demographics match across resources
- Ensure provider information is consistent
- Check facility/organization data alignment

## Recommendations

### Immediate Actions
1. **Fix Missing Field Definitions**: Add patient_first_name and patient_last_name fields to affected configs
2. **Standardize Transformations**: Apply consistent date and phone formatting
3. **Complete Template IDs**: Obtain missing DocuSeal template IDs
4. **Test Field Mapping**: Verify all manufacturers can generate IVR forms

### Optimization Priorities
1. **Enhance QuickRequestOrchestrator**: Better FHIR resource utilization
2. **Improve Step7DocusealIVR**: Manufacturer-specific handling
3. **Add Validation Layer**: Comprehensive validation for FHIR and IVR compliance
4. **Create Field Aliases**: Handle different field naming conventions

### Long-term Improvements
1. **Dynamic Field Mapping**: AI-powered field mapping based on template analysis
2. **Template Synchronization**: Automated template field discovery
3. **Manufacturer API Integration**: Direct integration with manufacturer systems
4. **Advanced Validation**: Real-time field validation during form completion

## Success Metrics
- **Field Completion Rate**: Target 95%+ for all manufacturers
- **IVR Generation Success**: Target 98%+ success rate
- **FHIR Compliance**: 100% compliant resource creation
- **Data Quality**: Minimize manual data entry through FHIR utilization 