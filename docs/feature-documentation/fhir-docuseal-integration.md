# FHIR-DocuSeal Integration Guide

## Overview

This guide explains how to integrate FHIR resources with DocuSeal for automatically populating wound care order forms with patient, provider, and clinical data. This integration is designed for providers purchasing wound care products through our platform.

## Architecture

The FHIR-DocuSeal integration consists of:

1. **FhirToIvrFieldExtractor** - Extracts standardized FHIR data
2. **FhirDocuSealIntegrationService** - Maps FHIR data to DocuSeal fields
3. **Enhanced QuickRequestController** - Orchestrates the integration
4. **Enhanced DocuSealEmbed Component** - Frontend interface with FHIR support

## Key Features

### 🩺 Comprehensive FHIR Resource Support
- **Patient** - Demographics, contact info, address
- **Practitioner** - Provider details, NPI, specialty
- **Organization** - Facility information
- **Coverage** - Insurance information
- **Condition** - Diagnosis codes and clinical conditions
- **DeviceRequest** - Product orders and quantities
- **Encounter** - Visit context
- **QuestionnaireResponse** - Clinical assessments

### 🔄 Intelligent Field Mapping
- Multiple field name variations supported
- Automatic data type conversion (dates, phones, etc.)
- Manufacturer-specific field mapping
- Graceful fallback for missing data

### 📋 Pre-populated Forms
- Patient demographics automatically filled
- Provider and facility information populated
- Insurance details extracted from FHIR Coverage
- Clinical data and wound assessments included
- Order information and product details

## Usage Examples

### Backend Integration

#### 1. Using the FHIR-DocuSeal Integration Service

```php
use App\Services\FhirDocuSealIntegrationService;
use App\Models\Episode;

// In your controller
public function createDocuSealWithFhir(Request $request)
{
    $episode = Episode::find($request->episode_id);
    
    $fhirIntegrationService = app(FhirDocuSealIntegrationService::class);
    
    $result = $fhirIntegrationService->createProviderOrderSubmission(
        $episode,
        $request->additional_data ?? []
    );
    
    if ($result['success']) {
        return response()->json([
            'slug' => $result['slug'],
            'embed_url' => $result['embed_url'],
            'fhir_fields_used' => $result['fhir_data_used'],
            'total_fields_mapped' => $result['fields_mapped']
        ]);
    }
    
    return response()->json(['error' => $result['error']], 500);
}
```

#### 2. Enhanced QuickRequest Controller

The enhanced controller automatically detects episodes with FHIR data:

```php
// Request with episode ID for FHIR integration
POST /quick-requests/docuseal/generate-submission-slug
{
    "user_email": "provider@example.com",
    "integration_email": "provider@example.com",
    "prefill_data": {
        "provider_notes": "Additional wound care notes"
    },
    "manufacturerId": 32,
    "productCode": "Q4250",
    "episode_id": 123  // Triggers FHIR integration
}
```

#### 3. FHIR Data Extraction

```php
use App\Services\FhirToIvrFieldExtractor;

$fhirExtractor = app(FhirToIvrFieldExtractor::class);

$fhirContext = [
    'patient_id' => 'Patient/12345',
    'practitioner_id' => 'Practitioner/67890',
    'organization_id' => 'Organization/54321',
    'coverage_id' => 'Coverage/98765'
];

$manufacturerKey = 'Amnio AMP'; // or manufacturer name

$extractedData = $fhirExtractor->extractForManufacturer($fhirContext, $manufacturerKey);

// Results in comprehensive field data:
// [
//     'patient_name' => 'John Doe',
//     'patient_dob' => '1985-03-15',
//     'provider_name' => 'Dr. Jane Smith',
//     'provider_npi' => '1234567890',
//     'primary_insurance_name' => 'Medicare',
//     'wound_type' => 'Diabetic Foot Ulcer',
//     // ... many more fields
// ]
```

### Frontend Integration

#### 1. Enhanced DocuSeal Component

```tsx
import { DocuSealEmbed } from '@/Components/QuickRequest/DocuSealEmbed';

// In your React component
<DocuSealEmbed
    manufacturerId="32"
    productCode="Q4250"
    episodeId={123}  // Enables FHIR integration
    formData={{
        provider_notes: "Additional clinical notes",
        urgency: "routine"
    }}
    onError={(error) => console.error('DocuSeal error:', error)}
    className="w-full"
/>
```

#### 2. Integration Status Display

The component automatically shows integration status:

```tsx
// FHIR-Enhanced Integration displays:
// ✅ FHIR-Enhanced Integration
// Using FHIR patient data • 25 fields from healthcare records • 127 total fields mapped
// Template: Amnio AMP IVR Form (Amnio AMP)

// Standard Integration displays:
// ℹ️ Standard Integration  
// Using form data only • 15 fields mapped
```

## FHIR Field Mapping

### Patient Demographics
- `patient_name` → `PATIENT NAME`, `Patient Name`, `PATIENT_NAME`
- `patient_dob` → `DATE OF BIRTH`, `DOB`, `Patient DOB`
- `patient_phone` → `PATIENT PHONE`, `Phone Number`
- `patient_address` → `PATIENT ADDRESS`, `ADDRESS`
- `patient_gender` → `GENDER`, `Patient Gender`

### Provider Information
- `provider_name` → `PROVIDER NAME`, `PHYSICIAN NAME`, `Doctor Name`
- `provider_npi` → `PROVIDER NPI`, `NPI`, `PHYSICIAN NPI`
- `provider_phone` → `PROVIDER PHONE`, `PHYSICIAN PHONE`
- `provider_specialty` → `SPECIALTY`, `Provider Specialty`

### Insurance Information
- `primary_insurance_name` → `PRIMARY INSURANCE`, `INSURANCE NAME`, `PAYER`
- `primary_policy_number` → `POLICY NUMBER`, `MEMBER ID`
- `primary_subscriber_name` → `SUBSCRIBER NAME`, `POLICYHOLDER NAME`

### Clinical Information
- `primary_diagnosis_code` → `DIAGNOSIS CODE`, `ICD CODE`, `PRIMARY DIAGNOSIS`
- `wound_type` → `WOUND TYPE`, `Wound Type`
- `wound_location` → `WOUND LOCATION`, `Anatomical Location`
- `wound_size_length` → `WOUND LENGTH`, `Length (cm)`
- `wound_size_width` → `WOUND WIDTH`, `Width (cm)`

### Facility Information
- `facility_name` → `FACILITY NAME`, `CLINIC NAME`
- `facility_address` → `FACILITY ADDRESS`, `CLINIC ADDRESS`
- `facility_npi` → `FACILITY NPI`, `CLINIC NPI`

## Configuration

### 1. Environment Variables

```env
# DocuSeal Configuration
DOCUSEAL_API_KEY=your_docuseal_api_key
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_TIMEOUT=30

# FHIR Configuration
AZURE_FHIR_ENDPOINT=https://your-fhir-service.azurehealthcareapis.com
AZURE_CLIENT_ID=your_azure_client_id
AZURE_CLIENT_SECRET=your_azure_client_secret
AZURE_TENANT_ID=your_azure_tenant_id
```

### 2. DocuSeal Template Configuration

Templates should include role "First Party" and field names that match our mapping system:

```json
{
  "template_id": 123456,
  "roles": ["First Party"],
  "fields": [
    {"name": "PATIENT NAME", "type": "text"},
    {"name": "DATE OF BIRTH", "type": "date"},
    {"name": "PROVIDER NAME", "type": "text"},
    {"name": "NPI", "type": "text"},
    {"name": "PRIMARY INSURANCE", "type": "text"},
    {"name": "WOUND TYPE", "type": "text"}
  ]
}
```

### 3. Manufacturer Template Mapping

```php
// In database: docuseal_templates table
[
    'manufacturer_id' => 32, // Amnio AMP
    'docuseal_template_id' => '123456',
    'template_name' => 'Amnio AMP IVR Form',
    'is_active' => true,
    'field_mappings' => [
        'PATIENT NAME' => ['system_field' => 'patient_name', 'data_type' => 'string'],
        'DATE OF BIRTH' => ['system_field' => 'patient_dob', 'data_type' => 'date'],
        'PROVIDER NAME' => ['system_field' => 'provider_name', 'data_type' => 'string'],
        // ... more mappings
    ]
]
```

## Error Handling

### 1. FHIR Integration Failures

```php
// The system gracefully falls back to standard integration
if ($result['success']) {
    // FHIR integration successful
    return $this->returnFhirEnhancedResponse($result);
} else {
    // Log the failure and use standard method
    Log::warning('FHIR integration failed, using standard method', [
        'episode_id' => $episode->id,
        'error' => $result['error']
    ]);
    return $this->useStandardIntegration($data);
}
```

### 2. Missing FHIR Resources

```php
// Service handles missing resources gracefully
if (!$fhirContext['patient_id']) {
    Log::info('No patient FHIR ID, skipping patient data extraction');
    // Continues with available data
}
```

### 3. Field Mapping Errors

```php
// Individual field failures don't stop the process
try {
    $mappedFields = $this->mapFhirToDocuSealFields($fhirData, $template);
} catch (\Exception $e) {
    Log::error('Field mapping failed', ['error' => $e->getMessage()]);
    $mappedFields = []; // Continue with empty fields
}
```

## Testing

### 1. Unit Tests

```php
// Test FHIR data extraction
public function test_fhir_extraction_with_complete_data()
{
    $fhirContext = [
        'patient_id' => 'Patient/test-123',
        'practitioner_id' => 'Practitioner/test-456'
    ];
    
    $result = $this->fhirExtractor->extractForManufacturer($fhirContext, 'Test Manufacturer');
    
    $this->assertArrayHasKey('patient_name', $result);
    $this->assertArrayHasKey('provider_name', $result);
}

// Test DocuSeal integration
public function test_docuseal_submission_with_fhir_data()
{
    $episode = Episode::factory()->withFhirData()->create();
    
    $result = $this->fhirDocuSealService->createProviderOrderSubmission($episode);
    
    $this->assertTrue($result['success']);
    $this->assertNotEmpty($result['slug']);
    $this->assertGreaterThan(0, $result['fhir_data_used']);
}
```

### 2. Integration Tests

```php
// Test end-to-end workflow
public function test_complete_fhir_docuseal_workflow()
{
    // Create episode with FHIR resources
    $episode = $this->createEpisodeWithFhirResources();
    
    // Make API request
    $response = $this->postJson('/quick-requests/docuseal/generate-submission-slug', [
        'user_email' => 'test@example.com',
        'episode_id' => $episode->id,
        'manufacturerId' => 32
    ]);
    
    $response->assertSuccessful();
    $response->assertJsonStructure([
        'success',
        'slug',
        'integration_type',
        'fhir_data_used',
        'fields_mapped'
    ]);
    
    $this->assertEquals('fhir_enhanced', $response->json('integration_type'));
    $this->assertGreaterThan(0, $response->json('fhir_data_used'));
}
```

## Performance Considerations

### 1. FHIR Data Caching

```php
// Cache FHIR resources to avoid repeated API calls
Cache::remember("fhir_patient_{$patientId}", 300, function() use ($patientId) {
    return $this->fhirService->getPatient($patientId);
});
```

### 2. Batch FHIR Requests

```php
// Use FHIR Bundle requests for multiple resources
$bundle = [
    'resourceType' => 'Bundle',
    'type' => 'batch',
    'entry' => [
        ['request' => ['method' => 'GET', 'url' => "Patient/{$patientId}"]],
        ['request' => ['method' => 'GET', 'url' => "Practitioner/{$practitionerId}"]],
        ['request' => ['method' => 'GET', 'url' => "Coverage?patient={$patientId}"]]
    ]
];
```

### 3. Async Processing

```php
// For large episodes, process FHIR data asynchronously
dispatch(new EnrichEpisodeWithFhirData($episode));
```

## Monitoring and Logging

### 1. Key Metrics

```php
// Track integration success rates
Log::info('FHIR integration metrics', [
    'episode_id' => $episode->id,
    'integration_type' => 'fhir_enhanced',
    'fhir_resources_found' => count($fhirContext),
    'fields_extracted' => count($fhirData),
    'fields_mapped' => count($mappedFields),
    'processing_time_ms' => $processingTime
]);
```

### 2. Error Tracking

```php
// Monitor integration failures
if (!$result['success']) {
    Log::error('FHIR-DocuSeal integration failed', [
        'episode_id' => $episode->id,
        'manufacturer_id' => $episode->manufacturer_id,
        'error' => $result['error'],
        'fhir_resources_available' => array_keys($fhirContext)
    ]);
}
```

## Best Practices

### 1. Data Validation
- Always validate FHIR data before mapping
- Use appropriate data types for each field
- Handle missing or malformed FHIR resources gracefully

### 2. Security
- Never log PHI in plain text
- Use secure FHIR endpoints with proper authentication
- Audit all FHIR data access

### 3. Scalability
- Cache frequently accessed FHIR resources
- Use batch requests for multiple resources
- Consider async processing for complex episodes

### 4. User Experience
- Show clear integration status to users
- Provide fallback options when FHIR fails
- Display meaningful error messages

## Troubleshooting

### Common Issues

1. **No FHIR data found**
   - Check episode has valid FHIR IDs
   - Verify FHIR service connectivity
   - Check Azure authentication

2. **Field mapping failures**
   - Review template field names
   - Check manufacturer field mappings
   - Verify data type conversions

3. **DocuSeal role errors**
   - Ensure template has "First Party" role
   - Check role extraction logic
   - Verify template accessibility

### Debug Commands

```php
// Debug FHIR data extraction
php artisan fhir:debug-extraction {episode_id}

// Test DocuSeal template mapping
php artisan docuseal:test-mapping {manufacturer_id}

// Validate FHIR connectivity
php artisan fhir:test-connection
```

This integration provides a seamless way to populate DocuSeal forms with comprehensive FHIR data, improving efficiency and accuracy for wound care providers. 
