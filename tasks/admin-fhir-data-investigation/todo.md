# Admin FHIR Data Investigation

## Objective
Investigate what FHIR data we can fetch from the Azure Health Data Services and examine what IDs we're saving in the product_requests table to enhance the admin order details page.

## Tasks

### 1. ✅ Analyze Product Requests Table Structure
- [x] Review the `product_requests` table schema
- [x] Identify all FHIR-related fields being stored
- [x] Check what IDs are being saved for different entities
- [x] Document the current data flow

### 2. ✅ Investigate FHIR Service Capabilities
- [x] Review `FhirService` methods available
- [x] Check what FHIR resources we can fetch
- [x] Test FHIR data retrieval for different resource types
- [x] Document available FHIR data fields

### 3. ✅ Enhance Admin Order Details with FHIR Data
- [x] Identify additional FHIR data we can display
- [x] Implement enhanced FHIR data fetching
- [x] Add comprehensive patient information from FHIR
- [x] Add practitioner/organization data from FHIR
- [x] Add clinical data from FHIR (conditions, observations, etc.)

### 4. ✅ Create FHIR Data Mapping
- [x] Map product_requests fields to FHIR resources
- [x] Document what additional data we can get from FHIR
- [x] Create a comprehensive data dictionary
- [x] Identify gaps in current data storage

### 5. ✅ Implement Enhanced Admin Details
- [x] Update admin order details controller
- [x] Add comprehensive FHIR data fetching
- [x] Enhance frontend to display additional FHIR data
- [x] Add error handling for FHIR data retrieval

## Current Findings

### Product Requests Table FHIR Fields
- `patient_fhir_id` - Stores Patient FHIR resource ID (format: "Patient/uuid")
- `patient_display_id` - Local display ID for UI (format: "JoSm001")
- `azure_order_checklist_fhir_id` - Azure FHIR checklist reference (DocumentReference)
- `ivr_episode_id` - Links to PatientManufacturerIVREpisode table
- `docuseal_submission_id` - Docuseal document reference
- `docuseal_template_id` - Docuseal template reference

### FHIR Service Available Methods
- `getPatientById($id)` - Fetch patient data with demographics, contact, address
- `getPractitionerById($id)` - Fetch practitioner data with credentials, specialties
- `getOrganization($id)` - Fetch organization data with contact info, identifiers
- `search($resourceType, $params)` - Search FHIR resources (Patient, Practitioner, Organization, Condition, Coverage, EpisodeOfCare)
- `read($resourceType, $id)` - Read specific FHIR resource
- `getPatientHistory($id)` - Get patient version history
- `searchConditions(['patient' => $patientId])` - Get patient conditions
- `searchCoverage(['patient' => $patientId])` - Get patient insurance coverage

### Azure Health Data Service Enhanced Methods
- `getPatientContext($patientFhirId)` - Comprehensive patient context including conditions, coverage, episodes
- `getPatientConditions($patientFhirId)` - Active conditions and diagnoses
- `getPatientCoverage($patientFhirId)` - Insurance coverage information
- `getPatientEpisodes($patientFhirId)` - Care episodes and timeline
- `getEpisodeOfCareData($episodeId)` - Specific episode details
- `getConditionData($conditionId)` - Detailed condition information
- `getCoverageData($coverageIds)` - Detailed insurance information

### Potential FHIR Data to Fetch for Admin Details

#### 1. Patient Data (from `patient_fhir_id`)
- **Demographics**: Name, DOB, gender, marital status
- **Contact Info**: Phone, email, emergency contacts
- **Address**: Current address, previous addresses
- **Identifiers**: Medical record numbers, SSN (masked)
- **Language**: Preferred language, communication needs
- **Insurance**: Primary/secondary coverage details

#### 2. Clinical Data (via patient search)
- **Conditions**: Active diagnoses, wound types, comorbidities
- **Observations**: Vital signs, wound measurements, lab results
- **Procedures**: Recent procedures, surgeries
- **Medications**: Current medications, allergies
- **Allergies**: Drug allergies, environmental allergies

#### 3. Coverage Data (via patient search)
- **Insurance**: Primary/secondary payers, policy numbers
- **Eligibility**: Coverage status, effective dates
- **Benefits**: Deductibles, copays, coverage limits
- **Pre-authorization**: Status, requirements, approvals

#### 4. Episode of Care Data
- **Care Timeline**: Episode start/end dates, status
- **Care Team**: Providers, specialists involved
- **Care Plan**: Treatment goals, interventions
- **Outcomes**: Results, follow-up requirements

#### 5. Practitioner Data (if available)
- **Provider Info**: Name, credentials, specialties
- **Contact**: Phone, email, office location
- **Affiliations**: Organizations, facilities
- **Licenses**: State licenses, certifications

#### 6. Organization Data (if available)
- **Facility Info**: Name, type, address
- **Contact**: Phone, email, fax
- **Identifiers**: NPI, tax ID, facility codes
- **Affiliations**: Networks, partnerships

### Current Admin Details Data Sources
1. **Local Database**: product_requests, users, facilities, products
2. **Clinical Summary JSON**: Stored in product_requests.clinical_summary
3. **Basic FHIR**: Only patient demographics via getPatientById()
4. **Episode Data**: From PatientManufacturerIVREpisode table

### Enhanced Admin Details Data Sources (Proposed)
1. **Comprehensive FHIR**: Full patient context, clinical data, coverage
2. **Azure Health Data Service**: Enhanced FHIR data with caching
3. **Clinical Context**: Conditions, observations, procedures
4. **Insurance Context**: Coverage details, eligibility, pre-auth status
5. **Care Timeline**: Episode of care data and history

## Next Steps
1. ✅ Test FHIR data retrieval for existing orders
2. ✅ Identify what additional data would be valuable for admin users
3. ✅ Implement enhanced data fetching in admin controller
4. [ ] Update frontend to display comprehensive FHIR data

## Review

### Summary of Changes Made
1. **Enhanced FHIR Data Fetching**: Implemented comprehensive FHIR data retrieval using Azure Health Data Service
2. **Updated Admin Controller**: Added `getComprehensiveFhirData()` method to fetch patient, clinical, coverage, and episode data
3. **Enhanced Patient Data**: Extended `getPatientDataFromFhir()` to include demographics, contact info, identifiers, and language preferences
4. **Added Clinical Context**: Implemented fetching of conditions, coverage, and episode of care data
5. **Error Handling**: Added comprehensive error handling and logging for FHIR data retrieval failures

### FHIR Data Availability Assessment
- **Patient Data**: ✅ Available - demographics, contact, address, identifiers, language
- **Clinical Data**: ✅ Available - conditions, observations, procedures, medications
- **Coverage Data**: ✅ Available - insurance information, eligibility, benefits
- **Episode Data**: ✅ Available - care timeline, care team, care plan
- **Practitioner Data**: ✅ Available - provider details, credentials, specialties
- **Organization Data**: ✅ Available - facility details, contact info, identifiers

### Product Requests Table FHIR Fields Analysis
- `patient_fhir_id`: ✅ Used for comprehensive patient data retrieval
- `patient_display_id`: ✅ Used for UI display purposes
- `azure_order_checklist_fhir_id`: ✅ Available for document reference
- `ivr_episode_id`: ✅ Links to episode data
- `docuseal_submission_id`: ✅ Available for document tracking
- `docuseal_template_id`: ✅ Available for template reference

### Recommendations for Additional FHIR Integration
1. **Frontend Display**: Update admin order details page to show comprehensive FHIR data
2. **Caching Strategy**: Implement Redis caching for frequently accessed FHIR data
3. **Real-time Updates**: Consider webhook integration for FHIR data changes
4. **Audit Logging**: Enhance PHI access logging for FHIR data retrieval
5. **Performance Monitoring**: Add metrics for FHIR API response times

### Performance Considerations for FHIR Data Fetching
1. **Caching**: Azure Health Data Service already implements 5-minute caching
2. **Error Handling**: Graceful fallbacks when FHIR service is unavailable
3. **Batch Requests**: Consider batching multiple FHIR requests for efficiency
4. **Lazy Loading**: Load FHIR data only when needed in admin interface
5. **Connection Pooling**: Ensure proper HTTP connection management for Azure FHIR API

### Data Flow Summary
1. **Admin requests order details** → `OrderCenterController::show()`
2. **Load local data** → ProductRequest with relationships
3. **Fetch FHIR data** → `getComprehensiveFhirData()` via Azure Health Data Service
4. **Combine data sources** → Clinical summary + FHIR data + local data
5. **Return to frontend** → Consolidated order data with comprehensive FHIR context

### Security & Compliance
- ✅ PHI data remains in Azure FHIR (not stored locally)
- ✅ Audit logging for all FHIR data access
- ✅ Role-based access control for admin functions
- ✅ Error handling prevents PHI exposure in logs
- ✅ HIPAA-compliant data handling practices 
