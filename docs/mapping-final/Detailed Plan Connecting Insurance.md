Detailed Plan: Connecting Insurance Verification & Order Forms with Dynamic Field Mapping



Based on my comprehensive analysis, here's a detailed plan to fully connect all components:



Phase 1: Create Missing Core Services


1.1 FhirDocuSealIntegrationService
Purpose: Bridge FHIR data with DocuSeal field mapping
Key Methods:
`prepareSubmissionData()` - Extract FHIR resources and prepare for DocuSeal
`syncFhirToDocuSeal()` - Keep FHIR and DocuSeal data synchronized
`handleCompletedForm()` - Update FHIR resources when DocuSeal forms complete
Integration Points:
FhirService for FHIR data access
UnifiedFieldMappingService for field transformations
DocuSealService for submission management


1.2 EnhancedDocuSealIVRService
Purpose: Manage the entire IVR submission lifecycle
Key Methods:
`createDynamicSubmission()` - Create submissions based on product/manufacturer
`trackSubmissionStatus()` - Real-time status tracking
`retryFailedSubmissions()` - Automatic retry with exponential backoff
`generateManufacturerNotification()` - Notify manufacturers of completed forms


Phase 2: Enhance Dynamic Field Mapping


2.1 Extend UnifiedFieldMappingService

```php

// Add new methods:

public function getRequiredFieldsByProduct(Product $product): array

public function validateInsuranceRequirements(Coverage $coverage, string $manufacturer): array

public function generateFieldMappingPreview(Episode $episode, Product $product): array

```



2.2 Create Field Mapping Middleware
Intercept form submissions
Apply manufacturer-specific validations
Transform fields based on product selection
Cache commonly used mappings


Phase 3: Implement Robust Order Flow Integration


3.1 Enhanced Quick Request Flow

```

Patient Info → Insurance Verification → Product Selection

↓

Manufacturer Detection

↓

Dynamic Field Mapping

↓

DocuSeal Form Generation

↓

Form Completion → Order Creation

```



3.2 Key Integration Points
After Product Selection:
Auto-detect manufacturer from product
Load manufacturer-specific field requirements
Pre-validate available data


Before DocuSeal Generation:
Run UnifiedFieldMappingService
Apply manufacturer business rules
Generate field mapping preview


During Form Display:
Show only relevant fields for manufacturer
Apply conditional logic based on insurance type
Pre-fill all mapped fields


Phase 4: Implement Error Handling & Recovery


4.1 Circuit Breaker Pattern

```php

// Add to DocuSealService

private CircuitBreaker $circuitBreaker;




public function createSubmissionWithCircuitBreaker($data) {

return $this->circuitBreaker->call(function() use ($data) {

return $this->createSubmission($data);

});

}

```



4.2 Retry Queue System
Create `failed_docuseal_submissions` table
Implement retry job with exponential backoff
Max 3 retries before manual intervention
Send alerts on repeated failures


Phase 5: Status Tracking & Webhooks


5.1 Enhanced Status Model

```php

// Add to Episode model

const STATUS_PENDING_IVR = 'pending_ivr';

const STATUS_IVR_IN_PROGRESS = 'ivr_in_progress';

const STATUS_IVR_COMPLETED = 'ivr_completed';

const STATUS_IVR_FAILED = 'ivr_failed';

const STATUS_ORDER_CREATED = 'order_created';

```



5.2 Webhook Enhancement
Consolidate webhook handling
Add webhook deduplication
Implement webhook replay functionality
Add webhook event logging


Phase 6: Testing & Validation


6.1 Create Comprehensive Tests
Unit tests for new services
Integration tests for complete flow
Manufacturer-specific test cases
Error scenario testing


6.2 Test Data Setup

```php

// Create test fixtures for each manufacturer

$manufacturers = ['ACZ', 'Advanced Health', 'MedLife', ...];

foreach ($manufacturers as $manufacturer) {

createTestEpisodeForManufacturer($manufacturer);

}

```



Implementation Steps


Week 1: Create missing services (FhirDocuSealIntegrationService, EnhancedDocuSealIVRService)
Week 2: Enhance field mapping with product/manufacturer awareness
Week 3: Implement error handling and retry mechanisms
Week 4: Add comprehensive status tracking and webhook improvements
Week 5: Testing and bug fixes
Week 6: Documentation and deployment


Key Files to Create/Modify



New Files:

`/app/Services/FhirDocuSealIntegrationService.php`
`/app/Services/EnhancedDocuSealIVRService.php`
`/app/Jobs/RetryFailedDocuSealSubmission.php`
`/database/migrations/add_enhanced_status_to_episodes.php`



Files to Modify:

`/app/Http/Controllers/QuickRequestController.php` - Add missing webhook handler
`/app/Services/UnifiedFieldMappingService.php` - Add product-aware methods
`/app/Services/DocuSealService.php` - Add circuit breaker and retry logic
`/resources/js/Pages/QuickRequest/Step7DocuSealIVR.tsx` - Enhance error handling



This plan ensures seamless integration between insurance verification and order forms with dynamic field mapping based on product/manufacturer selection while maintaining form consistency.