# Field Mapping Duplication Matrix

## Functionality Duplication Analysis

### 1. FHIR Data Extraction

| Functionality | FhirToIvrFieldExtractor | EnhancedDocuSealIVRService | IVRMappingOrchestrator |
|--------------|------------------------|----------------------------|------------------------|
| Extract Patient | ✓ Direct FHIR query | ✓ Via FhirService | ✓ Via FhirToIvrFieldExtractor |
| Extract Coverage | ✓ Direct FHIR query | ✓ Via FhirService | ✓ Via FhirToIvrFieldExtractor |
| Extract Provider | ✓ Direct FHIR query | ✓ Via FhirService | ✓ Via FhirToIvrFieldExtractor |
| Extract Facility | ✓ Database query | ✓ Database query | ✓ Via FhirToIvrFieldExtractor |
| Extract Clinical Data | ✓ Custom logic | ✓ Different approach | ✓ Via FhirToIvrFieldExtractor |

**Duplication Level**: HIGH - Same data extracted 3 different ways

### 2. Field Transformation Functions

| Transformation | Location 1 | Location 2 | Location 3 |
|---------------|-----------|-----------|-----------|
| Date Formatting | FhirToIvrFieldExtractor::formatDate() | UnifiedTemplateMappingEngine::transformDate() | Frontend: formatDateForIVR() |
| Phone Formatting | FhirToIvrFieldExtractor::formatPhone() | UnifiedTemplateMappingEngine::transformPhone() | Frontend: formatPhoneNumber() |
| Address Formatting | FhirToIvrFieldExtractor::inline | UnifiedTemplateMappingEngine::transformAddress() | Frontend: formatAddress() |
| Name Concatenation | Multiple inline implementations | UnifiedTemplateMappingEngine::transformFullName() | Frontend: getFullName() |
| Boolean to Yes/No | Multiple inline implementations | manufacturerFields.ts | DocuSealEmbed.tsx |

**Duplication Level**: VERY HIGH - Same transformations implemented 3+ times

### 3. Manufacturer Configuration

| Config Type | Database | Config File | Frontend | Service Code |
|------------|----------|-------------|----------|--------------|
| ACZ Fields | IVRFieldMappingSeeder | - | manufacturerFields.ts | FhirToIvrFieldExtractor |
| BioWerX Fields | IVRFieldMappingSeeder | - | manufacturerFields.ts | - |
| Template IDs | - | .env | manufacturerFields.ts | DocuSealEmbed |
| Field Requirements | IVRFieldMappingSeeder | ivr-mapping.php | manufacturerFields.ts | ManufacturerTemplateHandler |
| Validation Rules | - | ivr-mapping.php | manufacturerFields.ts | ValidationEngine |

**Duplication Level**: EXTREME - Same config in 4 different places

### 4. Field Mapping Logic

| Mapping Type | Service 1 | Service 2 | Service 3 |
|-------------|-----------|-----------|-----------|
| Exact Match | UnifiedTemplateMappingEngine | EnhancedFuzzyFieldMatcher | Direct assignment in multiple places |
| Fuzzy Match | EnhancedFuzzyFieldMatcher | UnifiedTemplateMappingEngine (calls fuzzy) | - |
| Semantic Match | EnhancedFuzzyFieldMatcher | - | - |
| Pattern Match | EnhancedFuzzyFieldMatcher | UnifiedTemplateMappingEngine | - |
| Fallback Values | FallbackStrategy | Inline in multiple services | Frontend defaults |

**Duplication Level**: MEDIUM - Different implementations of similar logic

### 5. DocuSeal Integration

| Function | EnhancedDocuSealIVRService | DocuSealBuilder | DocuSealEmbed Component |
|---------|---------------------------|-----------------|------------------------|
| Create Submission | ✓ Via DocuSealBuilder | ✓ Direct API | - |
| Field Mapping | ✓ Custom mapping | ✓ Pass-through | ✓ Frontend mapping |
| Token Generation | - | ✓ generateBuilderToken | - |
| Template Management | - | ✓ getTemplate | - |
| FHIR Integration | ✓ Extensive | - | - |

**Duplication Level**: MEDIUM - Overlapping responsibilities

## Code Duplication Examples

### Example 1: Phone Formatting (Found in 5 places)

```php
// FhirToIvrFieldExtractor.php
private function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        return sprintf('(%s) %s-%s', 
            substr($phone, 0, 3),
            substr($phone, 3, 3),
            substr($phone, 6, 4)
        );
    }
    return $phone;
}

// UnifiedTemplateMappingEngine.php
'phone' => function($value) {
    $digits = preg_replace('/\D/', '', $value);
    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6)
        );
    }
    return $value;
}
```

```typescript
// manufacturerFields.ts
export const formatPhoneNumber = (phone: string): string => {
  const cleaned = phone.replace(/\D/g, '');
  if (cleaned.length === 10) {
    return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;
  }
  return phone;
};
```

### Example 2: Date Formatting (Found in 4 places)

```php
// Multiple implementations of the same date formatting logic
// Each slightly different but achieving the same result
```

### Example 3: Manufacturer Config (Found everywhere)

```php
// Database Seeder
'manufacturer' => 'ACZ',
'template_fields' => [
    'patient_name' => ['required' => true],
    'patient_dob' => ['required' => true],
    // ... etc
]

// Frontend
const ACZ_FIELDS = {
  patient_name: { required: true },
  patient_dob: { required: true },
  // ... etc
}

// Service Code
if ($manufacturer === 'ACZ') {
    // Hardcoded ACZ-specific logic
}
```

## Consolidation Priority

### Priority 1: Eliminate Extreme Duplication
1. **Manufacturer Configuration** - Single source of truth
2. **Field Transformations** - Shared utility library

### Priority 2: Merge High Duplication
1. **FHIR Data Extraction** - Single extraction service
2. **Field Mapping Configuration** - Unified config system

### Priority 3: Consolidate Medium Duplication
1. **DocuSeal Integration** - Single service
2. **Mapping Logic** - Strategy pattern in one service

## Impact Analysis

### Current Problems Caused by Duplication:
1. **Inconsistent Behavior**: Phone formatted differently in different flows
2. **Maintenance Nightmare**: Update manufacturer config in 4 places
3. **Bug Multiplication**: Fix date formatting bug in multiple locations
4. **Testing Complexity**: Test same logic multiple times
5. **Performance Issues**: Multiple FHIR queries for same data

### Benefits of Consolidation:
1. **Consistency**: One implementation = consistent behavior
2. **Maintainability**: Update once, works everywhere
3. **Performance**: Cache and reuse extracted data
4. **Testing**: Test once, trust everywhere
5. **Development Speed**: Clear where to add new features