# Duplicate Code Analysis - TODO

## Tasks

- [x] Explore project structure and get overview of Models, Services, Controllers
- [x] Analyze Models directory for duplicate/similar models  
- [x] Examine Services directory for overlapping functionality
- [x] Check Controllers for similar methods and duplicate logic
- [x] Review Form Requests, Resources, and validation patterns
- [x] Identify duplicate database operations and business logic patterns
- [x] Create comprehensive report of findings

## Findings Report

### 1. Duplicate Models

#### DocusealSubmission Duplication
- **Files**: 
  - `app/Models/DocusealSubmission.php`
  - `app/Models/Docuseal/DocusealSubmission.php`
- **Issue**: Two models with the same name but in different namespaces
- **Differences**: The namespaced version in `Docuseal/` folder is more complete with relationships, scopes, and methods
- **Recommendation**: Remove the root-level model and use only the namespaced version

#### Authorization Models Overlap
- **Files**:
  - `app/Models/Insurance/PreAuthorization.php`
  - `app/Models/Insurance/PriorAuthorization.php`
- **Issue**: Very similar models for authorization tracking with overlapping fields
- **Differences**: PreAuthorization is linked to ProductRequest, PriorAuthorization is linked to Order
- **Recommendation**: Consolidate into a single model with a polymorphic relationship

### 2. Duplicate Services

#### Eligibility Services Duplication
- **Files**:
  - `app/Services/Eligibility/UnifiedEligibilityService.php`
  - `app/Services/EligibilityEngine/EligibilityService.php`
  - `app/Services/EligibilityEngine/AvailityEligibilityService.php`
  - `app/Services/EligibilityEngine/OptumEligibilityService.php`
- **Issue**: Multiple services handling eligibility checks with overlapping functionality
- **Pattern**: UnifiedEligibilityService appears to be a newer abstraction layer, while EligibilityEngine services are provider-specific
- **Recommendation**: Ensure UnifiedEligibilityService is the single entry point and remove direct usage of provider-specific services

#### Field Mapping Services Proliferation
- **Files**:
  - `app/Services/UnifiedFieldMappingService.php`
  - `app/Services/DocuSeal/DynamicFieldMappingService.php`
  - `app/Services/AI/IntelligentFieldMappingService.php`
  - `app/Services/FieldMappingSuggestionService.php`
  - `app/Services/FieldMapping/` (directory with multiple helpers)
- **Issue**: Multiple services handling field mapping with unclear separation of concerns
- **Recommendation**: Consolidate into a single service with clear responsibilities

#### Commission Calculation Services
- **Files**:
  - `app/Services/OrderCommissionProcessorService.php`
  - `app/Services/OrderItemCommissionCalculatorService.php`
  - `app/Services/PayoutCalculatorService.php`
  - `app/Services/CommissionRuleFinderService.php`
- **Issue**: Multiple services handling different aspects of commission calculation
- **Pattern**: Services appear to be properly separated by responsibility but could benefit from a facade pattern

### 3. Duplicate Controllers

#### Eligibility Controllers
- **Files**:
  - `app/Http/Controllers/EligibilityController.php`
  - `app/Http/Controllers/Api/EligibilityController.php`
- **Issue**: Two controllers handling eligibility with unclear separation
- **Recommendation**: Use API controller for all eligibility checks, remove or refactor the non-API version

#### DocuSeal Controllers
- **Files**:
  - `app/Http/Controllers/DocusealController.php`
  - `app/Http/Controllers/QuickRequest/DocusealController.php`
- **Issue**: Two controllers handling DocuSeal integration
- **Pattern**: QuickRequest version appears to be specialized for quick request workflow
- **Recommendation**: Consider using trait for shared functionality

#### QuickRequest Controllers
- **Files**:
  - `app/Http/Controllers/QuickRequestController.php`
  - `app/Http/Controllers/Api/V1/QuickRequestController.php`
- **Issue**: Multiple controllers handling quick requests
- **Pattern**: API version follows versioning pattern, but functionality overlap needs review

#### Product Request Controllers
- **Files**:
  - `app/Http/Controllers/ProductRequestController.php`
  - `app/Http/Controllers/ProductController.php`
  - `app/Http/Controllers/Api/ProductRequestClinicalAssessmentController.php`
  - `app/Http/Controllers/Api/ProductRequestPatientController.php`
- **Issue**: Multiple controllers handling product-related functionality
- **Pattern**: API controllers appear to be specialized by function, but base controllers have overlap

### 4. Multiple Audit Log Models
- **Files**:
  - `app/Models/FhirAuditLog.php`
  - `app/Models/MappingAuditLog.php`
  - `app/Models/OrderAuditLog.php`
  - `app/Models/ProfileAuditLog.php`
  - `app/Models/RbacAuditLog.php`
- **Issue**: Multiple audit log models with likely similar structure
- **Recommendation**: Consider a single polymorphic audit log model or a trait for shared functionality

### 5. Validation Engine Pattern
- **Files**:
  - `app/Services/WoundCareValidationEngine.php`
  - `app/Services/PulmonologyWoundCareValidationEngine.php`
  - `app/Services/ValidationBuilderEngine.php`
- **Pattern**: Properly uses builder/factory pattern for different validation types
- **Note**: This appears to be intentional design, not duplication

### 6. Dashboard Controllers
- **Files**:
  - `app/Http/Controllers/DashboardController.php`
  - `app/Http/Controllers/Provider/DashboardController.php`
- **Pattern**: Role-based separation of dashboard controllers
- **Note**: This appears to be intentional separation by user role

## Recommendations Summary

1. **High Priority**:
   - Remove duplicate DocusealSubmission model
   - Consolidate PreAuthorization/PriorAuthorization models
   - Unify eligibility services under UnifiedEligibilityService
   - Consolidate field mapping services

2. **Medium Priority**:
   - Review and consolidate duplicate controllers
   - Implement single audit log system with polymorphic relationships
   - Create shared traits for common controller functionality

3. **Low Priority**:
   - Document the intended usage of each service/controller
   - Add deprecation notices to services being phased out
   - Consider implementing a facade pattern for complex service groups

## Architecture Improvements

1. **Service Layer**: Implement clear service boundaries with single responsibility
2. **Controller Organization**: Use API versioning consistently and avoid duplication between web/API controllers
3. **Model Organization**: Use namespacing consistently (e.g., all insurance models in Insurance namespace)
4. **Audit System**: Implement a unified audit system instead of multiple audit log models

## Review Summary

The codebase shows signs of organic growth with multiple implementations of similar functionality. The main areas of concern are:

1. Duplicate models (DocusealSubmission)
2. Multiple eligibility service implementations
3. Proliferation of field mapping services
4. Duplicate controllers for same resources
5. Multiple audit log implementations

Most duplications appear to be the result of different development phases or attempts to refactor without removing old code. A systematic cleanup would significantly improve maintainability.