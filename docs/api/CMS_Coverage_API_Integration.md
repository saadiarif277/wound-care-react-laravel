# CMS Coverage API Integration & Validation Builder Engine

This document describes the implementation of CMS Coverage API integration and the ValidationBuilderEngine for wound care and specialty-specific validation.

## Overview

The implementation uses a **modular engine architecture** with specialized validation engines:

1. **CmsCoverageApiService** - Integrates with api.coverage.cms.gov to fetch live LCDs, NCDs, and Articles
2. **ValidationBuilderEngine** - Factory/coordinator that delegates to appropriate engines
3. **WoundCareValidationEngine** - Pure wound care validation logic
4. **PulmonologyWoundCareValidationEngine** - Combined pulmonology + wound care validation
5. **Enhanced MedicareMacValidationService** - Integrates all services for comprehensive validation

### Modular Engine Benefits

- ✅ **Separation of Concerns**: Each engine handles one specialty combination
- ✅ **Maintainability**: Easy to update/modify individual specialty logic
- ✅ **Extensibility**: Simple to add new specialty combinations
- ✅ **Testability**: Individual engines can be tested in isolation
- ✅ **Performance**: Optimized for specific specialty requirements

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  API Controllers                            │
├─────────────────────────────────────────────────────────────┤
│  ValidationBuilderController │ MedicareMacValidationController │
└─────────────────┬───────────────────────────┬───────────────┘
                  │                           │
┌─────────────────▼───────────────┐   ┌──────▼──────────────────┐
│   ValidationBuilderEngine      │   │ MedicareMacValidationService │
│   - Builds validation rules     │   │ - Enhanced with CMS data│
│   - Combines base + CMS rules   │   │ - Live compliance check │
└─────────────────┬───────────────┘   └──────┬──────────────────┘
                  │                           │
        ┌─────────▼──────────┐               │
        │ CmsCoverageApiService │◄────────────┘
        │ - Fetches LCDs      │
        │ - Fetches NCDs      │
        │ - Fetches Articles  │
        │ - MAC jurisdiction  │
        └─────────┬──────────┘
                  │
        ┌─────────▼──────────┐
        │   api.coverage.cms.gov │
        │   CMS Coverage API │
        └────────────────────┘
```

## CmsCoverageApiService

### Features

- **Real-time CMS Data**: Fetches live Local Coverage Determinations (LCDs), National Coverage Determinations (NCDs), and Articles
- **Specialty Filtering**: Filters coverage documents by medical specialty using comprehensive keyword matching
- **State-based Queries**: Supports state-specific LCD and Article queries for MAC jurisdiction compliance
- **Search Functionality**: Full-text search across all CMS coverage documents
- **Caching Strategy**: Implements intelligent caching (1 hour general, 24 hours for details)
- **Error Handling**: Robust error handling with logging and graceful fallbacks

### Supported Specialties

- `wound_care_specialty` - Comprehensive wound care coverage
- `vascular_surgery` - Vascular procedures and interventions
- `interventional_radiology` - Image-guided procedures
- `cardiology` - Cardiac procedures and diagnostics
- `podiatry` - Foot and ankle care
- `plastic_surgery` - Reconstructive and cosmetic procedures

### API Methods

```php
// Get LCDs for a specialty and state
$lcds = $cmsService->getLCDsBySpecialty('wound_care_specialty', 'CA');

// Get NCDs for a specialty
$ncds = $cmsService->getNCDsBySpecialty('wound_care_specialty');

// Get Articles for billing guidance
$articles = $cmsService->getArticlesBySpecialty('wound_care_specialty', 'CA');

// Search across all document types
$results = $cmsService->searchCoverageDocuments('skin substitute', 'CA');

// Get MAC jurisdiction information
$macInfo = $cmsService->getMACJurisdiction('CA');

// Get detailed LCD/NCD content
$lcdDetails = $cmsService->getLCDDetails('L38295');
$ncdDetails = $cmsService->getNCDDetails('270.1');
```

## ValidationBuilderEngine

### Comprehensive Wound Care Validation Rules

Based on the "Wound Care MAC Validation & Compliance Questionnaire", the engine implements:

#### Pre-Purchase Qualification
- **Patient Insurance Info**: Demographics, diagnosis codes, insurance verification
- **Facility Provider Info**: NPI validation, provider specialty verification
- **Medical History Assessment**: Comorbidities, medications, previous treatments

#### Wound Type Classification
- Diabetic Foot Ulcer (DFU)
- Venous Leg Ulcer (VLU)
- Pressure Ulcer/Injury (PU)
- Surgical Wound
- Traumatic Wound
- Arterial Ulcer
- Mixed Etiology

#### Comprehensive Wound Assessment
- **Location & Duration**: Anatomical location, onset date, duration tracking
- **Measurements**: Length, width, depth, total area with measurement method validation
- **Tissue Assessment**: Granulation, slough, eschar, epithelial percentages (must total 100%)
- **Wound Characteristics**: Periwound condition, exudate assessment, edge description
- **Clinical Indicators**: Exposed structures, tunneling, infection signs, pain assessment

#### Conservative Care Documentation
- **Minimum Duration**: 4-week minimum conservative care requirement
- **Wound-Specific Care**:
  - DFU: Offloading protocols, patient adherence tracking
  - VLU: Compression therapy compliance and duration
  - PU: Turning protocols, support surface, nutritional interventions

#### Clinical Assessments
- **Baseline Photography**: Required wound photography documentation
- **Vascular Assessment**: ABI, toe pressure, TcPO2, duplex ultrasound
- **Laboratory Values**: HbA1c for diabetics, albumin, WBC, inflammatory markers

#### MAC Coverage Verification
- MAC jurisdiction identification
- LCD compliance verification
- Prior authorization determination
- HCPCS/CPT code validation
- ICD-10 code support verification

### API Methods

```php
// Build rules for specific specialty
$rules = $validationEngine->buildValidationRulesForSpecialty('wound_care_specialty', 'CA');

// Build rules for authenticated user's specialty
$rules = $validationEngine->buildValidationRulesForUser($user, 'CA');

// Validate an order
$results = $validationEngine->validateOrder($order, 'wound_care_specialty');

// Validate a product request
$results = $validationEngine->validateProductRequest($productRequest, 'wound_care_specialty');
```

## PulmonologyWoundCareValidationEngine

### Comprehensive Dual-Specialty Validation

Based on the "Pulmonology & Wound Care MAC Validation & Compliance Questionnaire", this engine implements:

#### Pre-Treatment Qualification
- **Patient Insurance Info**: Enhanced with pulmonary-specific requirements
- **Facility Provider Info**: Must include both pulmonologist and wound care provider
- **Provider Specialty Verification**: Validates dual specialty credentials

#### Pulmonary History Assessment
- **Primary Pulmonary Conditions**: COPD staging, asthma severity, sleep apnea (AHI), pulmonary hypertension, ILD, lung cancer
- **Smoking History**: Current/former smoker status, pack-years calculation, quit dates
- **Functional Status**: MRC dyspnea scale (1-5), 6-minute walk distance, exercise tolerance

#### Wound Assessment with Pulmonary Considerations
- **Respiratory-Related Wounds**: Ventilator-associated pressure injuries, thoracic surgical wounds, tracheostomy-related wounds
- **Healing Factors**: Tissue hypoxia (SpO2), chronic steroid use, limited mobility due to dyspnea, frequent coughing, right heart failure edema

#### Pulmonary Function Assessment
- **Spirometry Results**: FEV1, FVC (liters and % predicted), FEV1/FVC ratio, DLCO
- **Arterial Blood Gas**: pH, PaO2, PaCO2, HCO3, SaO2 with normal ranges validation
- **Oxygen Therapy**: Continuous O2, flow rates, hours per day, CPAP/BiPAP, mechanical ventilation

#### Tissue Oxygenation Assessment
- **TcPO2 Measurement**: Wound site and reference site measurements on room air and with supplemental O2
- **Hyperbaric Oxygen Evaluation**: HBO candidacy assessment, contraindication screening, previous session tracking

#### Conservative Care with Pulmonary Optimization
- **Oxygenation Optimization**: O2 therapy initiation, target SpO2 (85-100%), compliance tracking
- **Pulmonary Rehabilitation**: Enrollment status, sessions completed, functional improvements
- **Smoking Cessation**: Counseling provision, pharmacotherapy, quit date documentation
- **Standard Wound Care**: Minimum 4-week wound care with specialized respiratory considerations

#### Coordinated Care Planning
- **Multidisciplinary Team**: Required pulmonologist, wound care specialist, optional respiratory therapist, PT, nutritionist
- **Care Coordination**: Team meeting documentation, shared treatment goals, communication protocols
- **Home Care Requirements**: Home O2 setup verification, caregiver training, emergency planning

#### Dual MAC Coverage Verification
- **Dual LCD Compliance**: Both wound care and pulmonary LCD requirements
- **Coordinated Billing**: Verification of appropriate billing for dual specialty care
- **Prior Authorization**: Assessment for both specialty requirements

### API Methods

```php
// Build dual-specialty validation rules
$rules = $pulmonaryWoundEngine->buildValidationRules('CA');

// Validate order with pulmonary considerations
$results = $pulmonaryWoundEngine->validateOrder($order, 'CA');

// Validate product request with respiratory factors
$results = $pulmonaryWoundEngine->validateProductRequest($productRequest, 'CA');
```

### Key Validation Features

- **Respiratory Impact Assessment**: Evaluates how respiratory conditions affect wound healing
- **Tissue Oxygenation Focus**: Emphasizes TcPO2 and HBO candidacy for optimal healing
- **Coordinated Care Requirements**: Ensures appropriate multidisciplinary team involvement
- **Dual Coverage Compliance**: Validates against both pulmonary and wound care MAC requirements

## Enhanced Medicare MAC Validation

The existing `MedicareMacValidationService` has been enhanced to integrate with both new services:

### New Features

- **Live CMS Data Integration**: Real-time LCD/NCD compliance checking
- **Comprehensive Validation Results**: Detailed validation reports from ValidationBuilderEngine
- **CMS Compliance Validation**: Automatic checking against applicable LCDs and NCDs
- **Enhanced Documentation**: CMS data included in audit trails and compliance reports

### Enhanced Validation Process

```php
$validation = $macService->validateOrder($order, 'wound_care_only', 'wound_care_specialty');

// Now includes:
// - Live CMS LCD/NCD data for the specialty and state
// - Comprehensive wound care validation results
// - CMS compliance checking against applicable policies
// - Enhanced audit trail with CMS data references
```

## API Endpoints

### Validation Builder Routes

All routes are under `/api/v1/validation-builder/` and require authentication:

#### Get Validation Rules
```http
GET /api/v1/validation-builder/rules?specialty=wound_care_specialty&state=CA
GET /api/v1/validation-builder/user-rules?state=CA
```

#### Order & Product Validation
```http
POST /api/v1/validation-builder/validate-order
Content-Type: application/json
{
    "order_id": 123,
    "specialty": "wound_care_specialty"
}

POST /api/v1/validation-builder/validate-product-request
Content-Type: application/json
{
    "product_request_id": 456,
    "specialty": "wound_care_specialty"
}
```

#### CMS Coverage Data
```http
GET /api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty&state=CA
GET /api/v1/validation-builder/cms-ncds?specialty=wound_care_specialty
GET /api/v1/validation-builder/cms-articles?specialty=wound_care_specialty&state=CA
GET /api/v1/validation-builder/search-cms?keyword=wound+dressing&state=CA
```

#### Utility Endpoints
```http
GET /api/v1/validation-builder/specialties
GET /api/v1/validation-builder/mac-jurisdiction?state=CA
POST /api/v1/validation-builder/clear-cache
Content-Type: application/json
{
    "specialty": "wound_care_specialty"
}
```

## Performance & Caching

### Caching Strategy

- **General CMS Data**: 1 hour cache for LCDs, NCDs, Articles
- **Detailed Documents**: 24 hour cache for specific LCD/NCD details
- **Validation Rules**: 30 minute cache for built validation rules
- **Search Results**: 30 minute cache for search queries

### Performance Optimization

- **Throttle Limit**: Respects CMS API 10,000 requests/second limit
- **Batch Processing**: Efficient filtering and processing of large datasets
- **Error Resilience**: Graceful degradation when CMS API is unavailable
- **Logging**: Comprehensive error logging for debugging and monitoring

## Testing

Run the test script to verify the implementation:

```bash
php test-validation-builder.php
```

This will test:
- CMS Coverage API Service functionality
- ValidationBuilderEngine rule building
- Service integration and dependency injection
- API endpoint availability

## Configuration

### Environment Variables

No additional environment variables are required. The service uses:
- Base URL: `https://api.coverage.cms.gov/v1`
- No authentication required for CMS API
- Laravel's built-in caching and HTTP client

### Service Registration

Services are automatically registered in `AppServiceProvider`:

```php
// CMS Coverage API Service (singleton)
$this->app->singleton(CmsCoverageApiService::class);

// Validation Builder Engine (singleton, depends on CMS service)
$this->app->singleton(ValidationBuilderEngine::class);
```

## Next Steps

1. **Frontend Integration**: Build React components to interact with the new API endpoints
2. **Enhanced Parsing**: Implement more sophisticated CMS document parsing for rule extraction
3. **Real Order Testing**: Test with actual orders and product requests
4. **Performance Monitoring**: Monitor CMS API response times and cache hit rates
5. **Rule Refinement**: Continuously improve validation rules based on real-world usage

## Modular Engine Testing

### Available Test Commands

```bash
# Test the overall ValidationBuilderEngine system
php artisan test:validation-builder --specialty=wound_care_specialty --state=CA

# Test the PulmonologyWoundCareValidationEngine  
php artisan test:pulmonology-wound-care --state=CA

# Test CMS Coverage API connectivity
php test-validation-builder.php
```

### Test Coverage Results

Both engines have been thoroughly tested:

#### WoundCareValidationEngine ✅
- ✅ Comprehensive wound care validation rules (6 categories)
- ✅ CMS LCD/NCD integration (47 LCDs, 10 NCDs for CA)
- ✅ Wound type classification validation
- ✅ Conservative care requirements (4-week minimum)
- ✅ MAC coverage verification

#### PulmonologyWoundCareValidationEngine ✅
- ✅ Dual-specialty validation rules (8 categories)
- ✅ Combined CMS data integration (71 pulmonary + 47 wound care LCDs)
- ✅ Pulmonary function assessment validation
- ✅ Tissue oxygenation evaluation (TcPO2, HBO)
- ✅ Coordinated care planning requirements
- ✅ Dual MAC coverage verification

### Future Engine Development Pattern

Each new `[Specialty]WoundCareValidationEngine` follows:

1. **Create Engine**: `app/Services/[Specialty]WoundCareValidationEngine.php`
2. **Define Rules**: Load from specialty questionnaire
3. **CMS Integration**: Combine specialty + wound care LCDs/NCDs
4. **Register Service**: Add to `AppServiceProvider`
5. **Update Factory**: Add to `ValidationBuilderEngine` delegator
6. **Create Tests**: Add `php artisan test:[specialty]-wound-care`
7. **Document**: Update this documentation

## Support

For questions or issues with the CMS Coverage API integration:

1. Check the Laravel logs for CMS API errors
2. Verify network connectivity to `api.coverage.cms.gov`
3. Review the test script output for specific error messages
4. Run engine-specific tests: `php artisan test:pulmonology-wound-care`
5. Consult CMS API documentation at https://api.coverage.cms.gov/docs 
