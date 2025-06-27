# IVR DocuSeal Integration - Complete Testing Guide

## Overview
This guide provides comprehensive testing procedures for the IVR field mapping and DocuSeal integration, ensuring 90% auto-population of fields from existing data.

## Pre-requisites

### 1. Environment Setup
```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set DocuSeal credentials in .env
DOCUSEAL_API_KEY=your_api_key_here
DOCUSEAL_API_URL=https://api.docuseal.com

# Set Azure FHIR credentials
AZURE_FHIR_BASE_URL=https://your-fhir-server.azurehealthcareapis.com
AZURE_FHIR_TENANT_ID=your_tenant_id
AZURE_FHIR_CLIENT_ID=your_client_id
AZURE_FHIR_CLIENT_SECRET=your_client_secret
```

### 2. Database Setup
```bash
# Run migrations
php artisan migrate

# Seed test data
php artisan db:seed
php artisan db:seed --class=TestOrderSeeder
```

## Test Suite Components

### 1. Unit Tests - Field Mapping Service
Tests the core field mapping functionality in isolation.

**File:** `tests/Feature/IvrFieldMappingTest.php`

**Run:**
```bash
php artisan test tests/Feature/IvrFieldMappingTest.php
```

**Tests:**
- Standard DocuSeal field mapping
- Manufacturer-specific fields
- Field type definitions
- Required field validation
- Date formatting
- Clinical summary extraction

### 2. Integration Tests - E2E Flow
Tests the complete order flow from creation to IVR generation.

**File:** `tests/Feature/IvrDocuSealE2ETest.php`

**Run:**
```bash
php artisan test tests/Feature/IvrDocuSealE2ETest.php
```

**Tests:**
- Complete order workflow
- IVR generation with mocked APIs
- Permission verification
- Status transitions
- Manufacturer approval flow

### 3. Manual Test Scripts

#### A. Direct IVR Generation Test
**File:** `tests/Manual/test-ivr-generation.php`

**Run:**
```bash
php tests/Manual/test-ivr-generation.php
```

**Purpose:** Tests IVR generation with real services and provides detailed diagnostics.

#### B. E2E Test Runner
**File:** `tests/Manual/test-ivr-e2e.sh`

**Run:**
```bash
chmod +x tests/Manual/test-ivr-e2e.sh
./tests/Manual/test-ivr-e2e.sh
```

**Purpose:** Automated test runner that executes all tests and checks configuration.

## Testing Scenarios

### Scenario 1: Standard Order with IVR
```php
// 1. Create product request
$request = ProductRequest::create([
    'provider_id' => $provider->id,
    'patient_display_id' => 'JODO123',
    'payer_name_submitted' => 'Medicare',
    'failed_conservative_treatment' => true,
    // ... other fields
]);

// 2. Generate IVR
$ivrService->generateIvr($request);

// 3. Verify fields are mapped
Assert: 90% of fields auto-populated
```

### Scenario 2: Manufacturer-Specific Fields
Test each manufacturer's unique requirements:

#### ACZ Distribution
- `physician_attestation` → Yes/No
- `not_used_previously` → Yes/No

#### Advanced Health
- `multiple_products` → Yes/No
- `previous_use` → Yes/No
- `additional_products` → Text list

#### MedLife (Amnio AMP)
- `amnio_amp_size` → Select from sizes

#### Centurion
- `previous_amnion_use` → Yes/No
- `stat_order` → Yes/No

### Scenario 3: Field Validation
```bash
# Test with missing required fields
php artisan tinker
>>> $order = ProductRequest::find(1);
>>> $order->payer_name_submitted = null;
>>> $order->save();
>>> $errors = app(IvrFieldMappingService::class)->validateMapping('ACZ_Distribution', []);
>>> print_r($errors); // Should show missing payer_name
```

### Scenario 4: FHIR Integration
```php
// Mock patient data structure
$patientData = [
    'given' => ['John'],
    'family' => 'Doe',
    'birthDate' => '1970-01-01',
    'address' => [[
        'line' => ['123 Main St'],
        'city' => 'Anytown',
        'state' => 'CA',
        'postalCode' => '12345'
    ]]
];

// Verify mapping
$mapped = $service->mapProductRequestToIvrFields($order, 'ACZ_Distribution', $patientData);
Assert: patient_first_name = 'John'
Assert: patient_city = 'Anytown'
```

## Field Mapping Verification

### Standard Fields (All Manufacturers)
| Field Name | Source | Format | Example |
|------------|--------|--------|---------|
| patient_first_name | FHIR | text | John |
| patient_last_name | FHIR | text | Doe |
| patient_dob | FHIR | Y-m-d | 1970-01-01 |
| patient_display_id | ProductRequest | text | JODO123 |
| payer_name | ProductRequest | text | Medicare |
| product_name | Product | text | ACELL Cytal |
| provider_name | Provider | text | Dr. Smith |
| facility_name | Facility | text | Wound Center |
| failed_conservative_treatment | ProductRequest | Yes/No | Yes |
| todays_date | Auto | m/d/Y | 12/17/2024 |

### Date Format Standards
- Patient DOB: `Y-m-d` (1970-01-01)
- Service dates: `Y-m-d` (2024-12-25)
- Display dates: `m/d/Y` (12/25/2024)
- Time: `h:i:s A` (02:30:45 PM)

## API Testing

### 1. Generate IVR
```bash
curl -X POST http://localhost:8000/admin/orders/{order_id}/generate-ivr \
  -H "X-CSRF-TOKEN: {token}" \
  -H "Content-Type: application/json" \
  -d '{"ivr_required": true}'
```

### 2. Skip IVR
```bash
curl -X POST http://localhost:8000/admin/orders/{order_id}/generate-ivr \
  -H "X-CSRF-TOKEN: {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "ivr_required": false,
    "justification": "Emergency order - IVR to follow"
  }'
```

### 3. Send to Manufacturer
```bash
curl -X POST http://localhost:8000/admin/orders/{order_id}/send-ivr-to-manufacturer \
  -H "X-CSRF-TOKEN: {token}"
```

## Troubleshooting

### Issue: Fields Not Mapping
```php
// Debug field mapping
Log::info('Field mapping debug', [
    'manufacturer' => $manufacturerKey,
    'mapped_fields' => $mappedFields,
    'patient_data' => $patientData
]);
```

### Issue: DocuSeal API Error
```bash
# Check configuration
php artisan tinker
>>> config('services.docuseal')
```

### Issue: Missing Required Fields
```php
// Check which fields are required
$required = $service->getRequiredFieldsForManufacturer('ACZ_Distribution');
print_r($required);
```

### Issue: FHIR Connection Failed
```bash
# Test FHIR connection
php artisan tinker
>>> app(FhirService::class)->testConnection()
```

## Success Criteria

✓ All unit tests pass
✓ E2E tests complete without errors
✓ 90% of fields auto-populate correctly
✓ All manufacturer-specific fields map properly
✓ Date formats are consistent
✓ Required field validation works
✓ FHIR patient data integrates correctly
✓ DocuSeal API creates submissions
✓ Status transitions occur properly
✓ Permissions are enforced

## Performance Benchmarks

- Field mapping: < 100ms
- IVR generation: < 2 seconds
- FHIR data retrieval: < 500ms
- Total E2E flow: < 5 seconds

## Next Steps

1. **Configure Production Templates**
   - Upload manufacturer IVR templates to DocuSeal
   - Map template IDs in configuration
   - Test with real templates

2. **Train Users**
   - Demonstrate auto-population
   - Show manual override options
   - Explain validation messages

3. **Monitor Performance**
   - Track field mapping accuracy
   - Monitor API response times
   - Log validation failures

4. **Continuous Improvement**
   - Add new manufacturer mappings
   - Update field definitions
   - Enhance validation rules