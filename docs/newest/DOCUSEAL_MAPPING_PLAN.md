# DocuSeal IVR Mapping Plan

## Overview

This document outlines the mapping strategy between the MSC Wound Portal's Product Request workflow and the Universal IVR form fields for DocuSeal integration.

## Current State Analysis

### Product Request Workflow Steps

1. **Patient Information Step**
   - Facility selection
   - Patient identifier (non-PHI)
   - Place of service
   - Medicare Part B authorization (for SNF)
   - Expected service date
   - Payer information
   - Wound type

2. **Clinical Assessment Step**
   - Dynamic forms based on wound type
   - Clinical summary generation
   - Assessment data storage

3. **Product Selection Step**
   - Product selection with sizes and quantities
   - AI-powered recommendations

4. **Validation & Eligibility Step**
   - MAC validation
   - Insurance eligibility checks

5. **Clinical Opportunities Step**
   - Optional revenue optimization

6. **Review & Submit Step**
   - Final review and submission

### DocuSeal Integration Architecture

- **IVR Status Flow**: `pending_ivr` → `ivr_sent` → `ivr_confirmed` → `approved`
- **Data Sources**: Local DB + Azure FHIR + External Services
- **Templates**: Universal base + 8 manufacturer-specific variations

## Field Mapping Tables

### Direct Mappings (Data Available in Product Request)

| IVR Field | Source | Data Path | Notes |
|-----------|--------|-----------|-------|
| **Order Information** |
| orderNumber | ProductRequest | `$productRequest->order->order_number` | Generated on order creation |
| requestDate | ProductRequest | `$productRequest->created_at` | Submission timestamp |
| accountType | Hardcoded | `'MSC'` | Always MSC |
| **Facility Information** |
| facilityName | Facility | `$productRequest->facility->name` | From relationship |
| facilityAddressLine1 | Facility | `$productRequest->facility->address->street` | Needs parsing |
| facilityCity | Facility | `$productRequest->facility->address->city` | Needs parsing |
| facilityState | Facility | `$productRequest->facility->address->state` | Needs parsing |
| facilityZip | Facility | `$productRequest->facility->address->zip` | Needs parsing |
| facilityNPI | Facility | `$productRequest->facility->npi` | **GAP: Add to facility model** |
| facilityTaxID | Facility | `$productRequest->facility->tax_id` | **GAP: Add to facility model** |
| **Provider Information** |
| physicianName | Provider | `$productRequest->provider->user->name` | Via relationships |
| physicianNPI | Provider | `$productRequest->provider->npi_number` | From provider profile |
| physicianPTAN | Provider | `$productRequest->provider->ptan` | **GAP: Add to provider profile** |
| physicianMedicaidNumber | Provider | `$productRequest->provider->medicaid_number` | **GAP: Add to provider profile** |
| physicianTaxID | Provider | `$productRequest->provider->tax_id` | **GAP: Add to provider profile** |
| **Treatment Information** |
| placeOfService | ProductRequest | `$productRequest->place_of_service` | Direct mapping |
| anticipatedTreatmentDate | ProductRequest | `$productRequest->expected_service_date` | Direct mapping |
| **Product Information** |
| selectedProducts | ProductRequest | `$productRequest->products` | JSON array |
| productQuantity | Products | `$productRequest->products[].quantity` | Per product |
| productSizes | Products | `$productRequest->products[].size` | Per product |

### PHI Mappings (From Azure FHIR)

| IVR Field | Source | FHIR Resource | Notes |
|-----------|--------|---------------|-------|
| patientName | FHIR | `Patient.name` | First + Last |
| patientDOB | FHIR | `Patient.birthDate` | Format: MM/DD/YYYY |
| patientGender | FHIR | `Patient.gender` | M/F mapping |
| patientAddressLine1 | FHIR | `Patient.address[0].line[0]` | Primary address |
| patientCity | FHIR | `Patient.address[0].city` | |
| patientState | FHIR | `Patient.address[0].state` | |
| patientZip | FHIR | `Patient.address[0].postalCode` | |
| patientPhoneNumber | FHIR | `Patient.telecom[type=phone].value` | Primary phone |

### Insurance Mappings

| IVR Field | Source | Data Path | Notes |
|-----------|--------|-----------|-------|
| primaryInsuranceName | ProductRequest | `$productRequest->payer_name_submitted` | From step 1 |
| primaryPolicyNumber | ProductRequest | `$productRequest->patient_api_input['member_id']` | From step 1 |
| primarySubscriberName | FHIR | `Coverage.subscriber.display` | Or use patient name |
| primaryPayerPhoneNumber | External | Payer lookup service | **GAP: Need payer DB** |
| secondaryInsuranceName | N/A | Not collected | **GAP: Needed Field** |
| secondaryPolicyNumber | N/A | Not collected | **GAP: Needed Field** |

### Clinical Mappings

| IVR Field | Source | Transformation | Notes |
|-----------|--------|---------------|-------|
| woundType | ProductRequest | `mapWoundType($productRequest->wound_type)` | Needs mapping function |
| woundLocation | Clinical Summary | Extract from `clinical_summary.woundDetails` | Parse JSON |
| woundDuration | Clinical Summary | Extract from `clinical_summary.woundDetails` | Parse JSON |
| woundSize | Clinical Summary | Extract from `clinical_summary.woundDetails` | Parse JSON |
| icd10Codes | Derived | `getICD10FromWoundType($productRequest->wound_type)` | Lookup table |
| cptCodes | Derived | `getCPTFromProduct($productRequest->products)` | Product-based |

### Sales & MSC Information

| IVR Field | Source | Data Path | Notes |
|-----------|--------|-----------|-------|
| mscRepName | Sales Assignment | Via facility sales assignments | Relationship lookup |
| mscRepPhoneNumber | MscSalesRep | `$salesRep->phone` | From profile |
| medicareAdminContractor | Facility | `getMACByZip($facility->address->zip)` | ZIP-based lookup |

## Data Transformation Functions

### Required Transformations

1. **Wound Type Mapping**
   ```php
   function mapWoundType($internalType) {
       $mapping = [
           'diabetic_foot_ulcer' => 'DFU',
           'venous_leg_ulcer' => 'VLU',
           'pressure_ulcer' => 'PU',
           'surgical_wound' => 'SW',
           'other' => 'OTHER'
       ];
       return $mapping[$internalType] ?? 'OTHER';
   }
   ```

2. **ICD-10 Code Derivation**
   ```php
   function getICD10FromWoundType($woundType) {
       $icdMapping = [
           'diabetic_foot_ulcer' => ['E11.621', 'E11.622'],
           'venous_leg_ulcer' => ['I83.0', 'I83.2'],
           'pressure_ulcer' => ['L89.90', 'L89.91'],
           'surgical_wound' => ['T81.89XA']
       ];
       return $icdMapping[$woundType] ?? [];
   }
   ```

3. **CPT Code Calculation**
   ```php
   function getCPTFromProduct($products) {
       // Based on product type and wound size
       // Implementation depends on business rules
   }
   ```

4. **Address Parsing**
   ```php
   function parseAddress($addressString) {
       // Parse single address field into components
       // street, city, state, zip
   }
   ```

## Implementation Strategy

### Phase 1: Data Model Enhancements
1. Add missing fields to Facility model:
   - `npi` (string)
   - `tax_id` (string)
   - `medicare_admin_contractor` (string)

2. Add missing fields to ProviderProfile model:
   - `ptan` (string)
   - `medicaid_number` (string)
   - `tax_id` (string)

3. Create Payer lookup table:
   - Payer name → Phone number mapping

### Phase 2: FHIR Integration Enhancement
1. Implement patient address fetching
2. Implement phone number retrieval
3. Add Coverage resource queries for insurance details

### Phase 3: IVR Field Mapper Service
1. Create `IVRFieldMapper` service class
2. Implement all transformation functions
3. Add manufacturer-specific field mappings
4. Handle optional/conditional fields

### Phase 4: Workflow Integration
1. Trigger IVR generation on `pending_ivr` status
2. Populate all available fields automatically
3. Flag missing required fields for manual entry
4. Generate and attach to DocuSeal submission

## Manufacturer-Specific Variations

Each manufacturer may have unique requirements:

### ACZ Distribution
- Additional fields for lot tracking
- Specific product codes

### Advanced Solution
- Requires physician signature page
- Additional clinical documentation

### MedLife Solutions
- Simplified form with fewer fields
- Direct submission capability

### BioWound Solutions
- Enhanced clinical data requirements
- Tissue type specifications

### Imbed Biosciences
- Antimicrobial product tracking
- Special handling instructions

### Extremity Care
- Location-specific fields
- Extremity mapping requirements

### StimLabs
- Tissue processing information
- Donor screening data

### Centurion Therapeutics
- Standard universal form
- No special requirements

## Security Considerations

1. **PHI Protection**
   - Fetch PHI only during IVR generation
   - No local storage of patient names/DOB
   - Secure transmission to DocuSeal

2. **Access Control**
   - Admin-only IVR generation
   - Audit trail for all IVR actions
   - Role-based field visibility

3. **Data Validation**
   - Validate all fields before submission
   - Sanitize inputs
   - Verify required fields

## Validation Requirements

### Required Fields (Must Have)
- Order number
- Facility information (name, address, NPI)
- Provider information (name, NPI)
- Patient identifier
- Primary insurance
- Product selection
- Treatment date

### Conditional Fields
- Medicare Part B authorization (if SNF)
- Secondary insurance (if available)
- Clinical documentation (based on payer)

### Optional Fields
- Additional clinical notes
- Special instructions
- Rush order indicators

## Success Metrics

1. **Automation Rate**: % of fields auto-populated
2. **Error Rate**: % of IVRs requiring correction
3. **Processing Time**: Time from request to IVR generation
4. **Approval Rate**: % of IVRs approved by manufacturers

## Next Steps

1. Review and approve data model changes
2. Implement provider profile enhancements
3. Create IVRFieldMapper service
4. Test with each manufacturer template
5. Deploy phased rollout

## Conclusion

The current product request workflow contains approximately 70% of the data needed for IVR generation. With the proposed enhancements:
- 90% of fields can be auto-populated
- Manual entry reduced to optional/edge cases
- Streamlined approval process
- Improved accuracy and compliance

The mapping is highly feasible with minimal system changes required.