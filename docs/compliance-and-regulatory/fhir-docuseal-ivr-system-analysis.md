# FHIR-to-DocuSeal IVR System Architecture

## Current Implementation

### 1. Simplified Manufacturer Configuration Architecture

**System Design:**
- Products store manufacturer name as a string field
- Manufacturer lookup happens by name when needed
- UnifiedFieldMappingService handles all field conversions

**Current State:**
```
Product Model:
├── manufacturer: string (e.g., "MedLife Solutions", "ACZ & Associates")
├── name: string (e.g., "Amnio AMP", "Biovance")
└── Other product attributes...

Field Mapping Configuration (config/field-mapping.php):
├── Canonical field names
├── Field aliases  
├── Manufacturer-specific DocuSeal field mappings
└── Transformation rules
```

**Implementation Benefits:**
- Simple, direct manufacturer association
- Unified field mapping service
- AI-powered field matching capabilities

### 2. Data Structure Communication Patterns

**Frontend-Backend Contract Violations:**
```javascript
// Expected by Frontend (ProductSelectorQuickRequest.tsx)
interface ProductData {
  available_sizes: string[];     // Expected: populated array
  size_options: SizeOption[];    // Expected: structured options
  size_pricing: SizePricing;     // Expected: pricing map
}

// Actual Backend Response
{
  available_sizes: [],           // Empty array causing iteration errors
  size_options: undefined,       // Missing property
  size_pricing: undefined        // Missing property
}
```

**Performance Bottlenecks:**
- Multiple conditional checks on undefined properties
- Repeated null coalescing operations
- No caching of manufacturer configurations

### 3. FHIR Resource Integration 

**Current FHIR Data Flow:**
```
Patient Creation → FHIR ID Generated → Stored in database
         ↓
Episode Creation → Episode ID Generated → Stored in database
         ↓
DocuSeal IVR → UnifiedFieldMappingService → Mapped to DocuSeal fields
         ↓
Field Conversion → Manufacturer-specific field names applied
```

**Integration Points:**
- FhirService fetches patient data from Azure FHIR
- UnifiedFieldMappingService converts FHIR data to canonical fields
- Manufacturer-specific field mappings applied based on product
- AI-powered field matching for unmapped fields

## Current Architecture

### 1. Unified Field Mapping Service

**Service Architecture:**
```php
class UnifiedFieldMappingService {
    // Maps any data source to canonical field names
    public function mapToCanonicalFields(array $data): array;
    
    // Converts canonical fields to DocuSeal format
    public function convertToDocuSealFields(array $data, array $config): array;
    
    // AI-powered field matching for unknown fields
    public function suggestFieldMappings(array $fields): array;
}
```

**Configuration Structure:**
```php
// config/field-mapping.php
return [
    'canonical_fields' => [
        'patient_name', 'patient_dob', 'patient_gender',
        'physician_name', 'physician_npi', 'facility_name'
    ],
    'field_aliases' => [
        'provider_name' => 'physician_name',
        'practice_name' => 'facility_name'
    ],
    'manufacturers' => [
        'MedLife Solutions' => [
            'docuseal_field_names' => [
                'patient_name' => 'Patient Name',
                'physician_npi' => 'Physician NPI'
            ]
        ]
    ]
];
```

### 2. DocuSeal IVR Generation Flow

**Service Flow:**
```
QuickRequestController::createIVRSubmission()
    ↓
Product lookup → Extract manufacturer name
    ↓
Manufacturer lookup by name
    ↓
FhirService → Fetch patient/provider data
    ↓
UnifiedFieldMappingService → Map to canonical fields
    ↓
DocuSealService → Convert to manufacturer-specific fields
    ↓
DocuSeal API → Create submission
```

**Implementation in DocuSealService:**
```php
public function createIVRSubmission(array $fields, string $templateId): array {
    // Get manufacturer config
    $manufacturerName = $fields['manufacturer'] ?? null;
    $manufacturerConfig = $this->getManufacturerConfig($manufacturerName);
    
    // Use UnifiedFieldMappingService for field conversion
    if (!empty($manufacturerConfig['docuseal_field_names'])) {
        $preparedFields = $this->fieldMappingService->convertToDocuSealFields(
            $fields, 
            $manufacturerConfig
        );
    } else {
        // Fallback to direct field mapping
        $preparedFields = $this->prepareFieldsForDocuSeal($fields, $templateId);
    }
    
    // Create DocuSeal submission
    return $this->createSubmission($templateId, $preparedFields);
}
```

### 3. Key Implementation Details

**Field Mapping Service Features:**
- Canonical field name standardization
- AI-powered field matching using OpenAI GPT-4
- Fuzzy matching with Jaro-Winkler algorithm
- Pattern-based field detection
- Manufacturer-specific field name conversion

**DocuSeal Integration:**
- Direct API integration at https://api.docuseal.com
- Template management by manufacturer folders
- Webhook processing for completed forms
- JWT authentication for secure webhooks

**FHIR Data Integration:**
- Patient data fetched from Azure FHIR
- Provider/Practitioner resource support
- Organization resource support
- PHI data kept separate in Azure, only FHIR IDs stored locally

## Technology Stack

### Backend:
- **Laravel 11**: Core framework
- **Azure FHIR Service**: PHI data storage
- **DocuSeal API**: Document generation
- **OpenAI GPT-4**: Intelligent field mapping
- **Laravel Cache**: Configuration caching

### Frontend:
- **React 18**: UI framework
- **Inertia.js**: Server-side rendering
- **TypeScript**: Type safety
- **DocuSeal React SDK**: Embedded forms

### Integration:
- **UnifiedFieldMappingService**: Central field mapping
- **FhirService**: Azure FHIR client
- **DocuSealService**: DocuSeal API wrapper
- **PhiAuditService**: PHI access auditing

## Error Handling

### Common Issues and Solutions:

1. **Field Name Mismatches**
   - Solution: UnifiedFieldMappingService with AI-powered matching
   - Fallback: Direct field mapping when no configuration exists

2. **Missing Manufacturer Configuration**
   - Solution: Lookup manufacturer by product name
   - Fallback: Use generic field names

3. **Template Not Found**
   - Solution: Verify template ID and folder structure in DocuSeal
   - Fallback: Return error to user with clear message

4. **FHIR Service Unavailable**
   - Solution: Circuit breaker pattern in FhirService
   - Fallback: Use cached data or proceed with available data

## Performance Optimizations

1. **Configuration Caching**
   - Field mapping configurations cached in Laravel Cache
   - Manufacturer lookups cached to reduce database queries

2. **Efficient Field Mapping**
   - Canonical field names reduce mapping complexity
   - AI suggestions cached for repeated use

## Key Configuration Files

1. **config/field-mapping.php**
   - Canonical field definitions
   - Field aliases and transformations
   - Manufacturer-specific DocuSeal field mappings

2. **config/docuseal.php**
   - API credentials and endpoints
   - Template configurations
   - Webhook settings

3. **config/services.php**
   - Azure FHIR configuration
   - OpenAI API settings

## Monitoring and Observability

- Laravel logging for all IVR generation attempts
- PHI audit logging for FHIR data access
- Error tracking for failed field mappings
- Performance monitoring for API response times

## Summary

The current implementation provides a simplified, unified approach to DocuSeal IVR generation:

1. **Unified Field Mapping**: Single service handles all field transformations
2. **Manufacturer by Name**: Products directly reference manufacturer names
3. **AI-Powered Matching**: Intelligent field mapping for unknown fields
4. **FHIR Integration**: Secure PHI data access from Azure
5. **Clean Architecture**: Removed 31 overlapping DocuSeal services

This architecture reduces complexity while maintaining flexibility for manufacturer-specific requirements.
