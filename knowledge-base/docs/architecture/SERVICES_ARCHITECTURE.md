# Service Layer Architecture

**Version:** 1.0  
**Last Updated:** January 2025  
**Audience:** Developers, Technical Staff

---

## 📋 Overview

The MSC Wound Care Portal implements a comprehensive service layer architecture that handles complex business logic, integrations, and data processing. This document provides detailed documentation of all service classes, their responsibilities, and interactions.

## 🏗️ Service Architecture Pattern

### Service Categories
```
Services/
├── Core Business Logic/
│   ├── QuickRequestService
│   ├── OrderService
│   ├── PatientService
│   └── ProviderService
├── Integration Services/
│   ├── FhirService
│   ├── DocusealService
│   ├── AvailityService
│   └── CmsCoverageApiService
├── Validation & Compliance/
│   ├── MacValidationService
│   ├── EligibilityService
│   └── ValidationBuilderEngine
├── AI & Machine Learning/
│   ├── OptimizedMedicalAiService
│   ├── ContinuousLearningService
│   └── FormFillingOptimizer
└── Data Processing/
    ├── UnifiedFieldMappingService
    ├── DocumentIntelligenceService
    └── EntityDataService
```

## 🔧 Core Services Documentation

### QuickRequestService
**Location:** `app/Services/QuickRequestService.php`
**Purpose:** Manages the 90-second ordering workflow

#### Responsibilities
- Form data preparation and validation
- Workflow state management
- Integration coordination
- Error handling and recovery

#### Key Methods
```php
public function getFormData(User $user): array
public function validateRequest(array $data): bool
public function processSubmission(array $data): QuickRequestResult
```

#### Dependencies
- `CurrentOrganization`
- `DocusealService`
- `UnifiedFieldMappingService`

### MacValidationService
**Location:** `app/Services/MacValidationService.php`
**Purpose:** Medicare Administrative Contractor validation and compliance

#### Responsibilities
- ZIP code to MAC jurisdiction mapping
- CPT/HCPCS code validation
- Coverage determination
- Documentation requirements analysis

#### Key Features
```php
// Wound care CPT codes validation
private array $woundCareCptCodes = [
    '97597' => ['description' => 'Debridement, open wound'],
    '97598' => ['description' => 'Debridement, additional 20 sq cm'],
    // ... more codes
];

// Validation methods
public function validateOrder(Order $order): ValidationResult
public function getMacByZipCode(string $zipCode): string
public function getCoverageRequirements(array $cptCodes): array
```

#### Integration Points
- CMS Coverage API
- ValidationBuilderEngine
- Order models

### UnifiedFieldMappingService
**Location:** `app/Services/UnifiedFieldMappingService.php`
**Purpose:** Unified field mapping across manufacturers and forms

#### Responsibilities
- FHIR to IVR field mapping
- Manufacturer-specific transformations
- Data validation and normalization
- AI-enhanced field matching

#### Architecture
```php
public function __construct(
    private DataExtractor $dataExtractor,
    private FieldTransformer $fieldTransformer,
    private FieldMatcher $fieldMatcher,
    FhirService $fhirService,
    MedicalTerminologyService $medicalTerminologyService
)
```

#### Key Methods
```php
public function mapPatientData(array $patientData, string $manufacturer): array
public function mapProviderData(array $providerData, string $manufacturer): array
public function validateMappedData(array $mappedData): ValidationResult
```

## 🤖 AI & Machine Learning Services

### OptimizedMedicalAiService
**Location:** `app/Services/Medical/OptimizedMedicalAiService.php`
**Purpose:** Primary AI service for medical data processing

#### Capabilities
- Medical terminology processing
- Clinical decision support
- Data extraction and normalization
- Fallback mechanisms

### ContinuousLearningService
**Location:** `app/Services/Learning/ContinuousLearningService.php`
**Purpose:** Machine learning pipeline for behavioral analysis

#### Model Types
```php
private const MODEL_TYPES = [
    'behavioral_prediction' => 'Behavioral Prediction Model',
    'product_recommendation' => 'Product Recommendation Model',
    'workflow_optimization' => 'Workflow Optimization Model',
    'form_optimization' => 'Form Optimization Model',
    'personalization' => 'Personalization Model',
    'clinical_decision_support' => 'Clinical Decision Support Model'
];
```

#### Features
- Behavioral event tracking
- Model training orchestration
- Prediction generation
- Continuous improvement

## 🔗 Integration Services

### FhirService
**Location:** `app/Services/FhirService.php`
**Purpose:** FHIR resource management and Azure Health Data Services integration

#### Capabilities
- Patient resource management
- Provider resource synchronization
- Observation and condition tracking
- PHI-compliant data handling

#### Circuit Breaker Pattern
```php
// Implements circuit breaker for resilience
protected FhirCircuitBreaker $circuitBreaker;
protected FhirErrorHandler $errorHandler;
```

### DocusealService
**Location:** `app/Services/DocusealService.php`
**Purpose:** Document generation and e-signature workflow management

#### Features
- Template management
- Form pre-filling
- Submission tracking
- Webhook handling

#### Integration with Field Mapping
```php
public function __construct(
    UnifiedFieldMappingService $fieldMappingService
) {
    $this->fieldMappingService = $fieldMappingService;
}
```

## 📊 Validation & Compliance Services

### ValidationBuilderEngine
**Location:** `app/Services/ValidationBuilderEngine.php`
**Purpose:** Dynamic validation rule engine

#### Capabilities
- Rule-based validation
- Medicare compliance checking
- Clinical decision support
- Documentation requirement analysis

### EligibilityService
**Location:** `app/Services/EligibilityEngine/EligibilityService.php`
**Purpose:** Insurance eligibility verification

#### Features
- Real-time eligibility checking
- Multi-payer support
- Caching and optimization
- Fallback mechanisms

## 🔄 Service Provider Architecture

### Service Registration
```php
// AppServiceProvider.php
$this->app->singleton(MacValidationService::class);
$this->app->singleton(UnifiedFieldMappingService::class);
$this->app->singleton(OptimizedMedicalAiService::class);
```

### Specialized Providers
- `FHIRServiceProvider` - FHIR service registration
- `QuickRequestServiceProvider` - Quick request workflow services
- `SecurityServiceProvider` - Security and rate limiting

## 📈 Performance Considerations

### Caching Strategy
```php
// Example: MacValidationService caching
private const CACHE_TTL_MINUTES = 60 * 24; // one day

public function getCachedMacData(string $zipCode): ?array {
    return Cache::remember("mac_data_{$zipCode}", 
        self::CACHE_TTL_MINUTES, 
        fn() => $this->fetchMacData($zipCode)
    );
}
```

### Service Optimization
- Singleton pattern for heavy services
- Lazy loading for optional dependencies
- Circuit breaker for external integrations
- Background job processing for heavy operations

## 🔒 Security Patterns

### PHI Handling
```php
// All services implement PHI-safe logging
protected PhiSafeLogger $logger;

// Example usage
$this->logger->logInfo('Patient data processed', [
    'patient_id' => $patient->fhir_id, // Safe to log
    // Never log PHI directly
]);
```

### Service Isolation
- Each service has defined boundaries
- Cross-service communication through interfaces
- Dependency injection for testability
- No direct model access outside service layer

## 🧪 Testing Services

### Service Testing Pattern
```php
// Example service test
class MacValidationServiceTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->service = app(MacValidationService::class);
    }
    
    public function test_validates_wound_care_cpt_codes(): void {
        $result = $this->service->validateCptCode('97597');
        $this->assertTrue($result->isValid());
    }
}
```

### Mock Services for Testing
```php
// Test service registration
$this->app->singleton(FhirService::class, function () {
    return Mockery::mock(FhirService::class);
});
```

## 🔧 Configuration Management

### Service Configuration
```php
// config/services.php
'medical_ai' => [
    'base_url' => env('MEDICAL_AI_SERVICE_URL'),
    'timeout' => env('MEDICAL_AI_TIMEOUT', 30),
    'retries' => env('MEDICAL_AI_RETRIES', 3),
],

'mac_validation' => [
    'cache_ttl' => env('MAC_CACHE_TTL', 1440), // minutes
    'api_timeout' => env('MAC_API_TIMEOUT', 10),
],
```

### Environment-Specific Services
- Development: Mock services for external APIs
- Staging: Limited external API calls
- Production: Full service stack with monitoring

## 📊 Service Metrics & Monitoring

### Performance Metrics
- Service response times
- Cache hit rates
- Error rates and types
- API call volumes

### Health Checks
```php
// Service health check implementation
public function healthCheck(): HealthStatus {
    return new HealthStatus([
        'service' => 'MacValidationService',
        'status' => 'healthy',
        'dependencies' => $this->checkDependencies(),
        'metrics' => $this->getMetrics(),
    ]);
}
```

## 🚀 Future Service Enhancements

### Planned Improvements
1. **Microservice Migration**: Gradual extraction of services
2. **Event-Driven Architecture**: Service communication via events
3. **Service Mesh**: Advanced service-to-service communication
4. **Auto-scaling**: Dynamic service scaling based on load

### Service Roadmap
- Q1 2025: Enhanced AI service capabilities
- Q2 2025: Real-time analytics services
- Q3 2025: Advanced compliance automation
- Q4 2025: Predictive health analytics

---

**Next Steps:**
- Review [API Documentation](../development/API_DOCUMENTATION.md)
- See [Development Setup](../development/DEVELOPMENT_SETUP.md)
- Check [Testing Guide](../development/TESTING_GUIDE.md)
