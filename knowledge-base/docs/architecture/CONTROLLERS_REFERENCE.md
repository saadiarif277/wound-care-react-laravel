# Controller Reference Guide

**Version:** 1.0  
**Last Updated:** January 2025  
**Audience:** Developers, API Consumers

---

## 📋 Overview

This document provides a comprehensive reference for all controllers in the MSC Wound Care Portal, organized by functionality and purpose. Each controller is documented with its responsibilities, key methods, and API endpoints.

## 🏗️ Controller Architecture

### Organization Pattern
```
Controllers/
├── Core Platform/
│   ├── DashboardController
│   ├── OrderController
│   └── ProductRequestController
├── API Controllers/
│   ├── Api/V1/ (Versioned APIs)
│   ├── Api/Core/ (Core APIs)
│   └── Api/Integration/ (External integrations)
├── Admin Controllers/
│   ├── Admin/UsersController
│   ├── Admin/OrderCenterController
│   └── Admin/ProviderManagementController
└── Specialized Controllers/
    ├── Auth/LoginController
    ├── Commission/CommissionController
    └── Provider/DashboardController
```

## 🎯 Core Platform Controllers

### DashboardController
**Location:** `app/Http/Controllers/DashboardController.php`
**Purpose:** Main dashboard routing and role-based dashboard data

#### Key Methods
```php
public function index(): Response
- Routes users to role-specific dashboards
- Loads role-based dashboard data
- Handles role assignment for new users

private function getDashboardDataForRole(User $user, string $role): array
- Provider dashboard: orders, commissions, opportunities
- Admin dashboard: system metrics, order management
- Sales rep dashboard: territory analytics, commission tracking
```

#### Role-Based Routing
```php
private function getDashboardComponent(string $roleName): string {
    return match($roleName) {
        'provider' => 'Dashboard/Provider/ProviderDashboard',
        'msc-admin' => 'Dashboard/Admin/AdminDashboard',
        'sales-rep' => 'Dashboard/SalesRep/SalesRepDashboard',
        'office-manager' => 'Dashboard/OfficeManager/OfficeManagerDashboard',
        default => 'Dashboard/Provider/ProviderDashboard'
    };
}
```

### QuickRequestController
**Location:** `app/Http/Controllers/QuickRequestController.php`
**Purpose:** 90-second ordering workflow management

#### Architecture
```php
public function __construct(
    protected QuickRequestService $quickRequestService,
    protected QuickRequestOrchestrator $orchestrator,
    protected QuickRequestCalculationService $calculationService,
    protected QuickRequestFileService $fileService,
    protected CurrentOrganization $currentOrganization,
    protected DocusealService $docusealService,
) {}
```

#### Key Methods
```php
public function create(): Response
- Display quick request form
- Load form configuration data
- Initialize workflow state

public function reviewOrder(Request $request): Response
- Order review and confirmation
- Calculate pricing and shipping
- Prepare for submission

public function submitOrder(SubmitOrderRequest $request): JsonResponse
- Process final order submission
- Generate required documents
- Trigger notifications and workflows
```

### ProductRequestController
**Location:** `app/Http/Controllers/ProductRequestController.php`
**Purpose:** Product request lifecycle management

#### Responsibilities
- Product request creation and editing
- Status tracking and updates
- Document management
- Approval workflows

## 🔌 API Controllers (V1)

### Api/V1/QuickRequestController
**Location:** `app/Http/Controllers/Api/V1/QuickRequestController.php`
**Purpose:** API version of quick request functionality

#### API Endpoints
```php
POST /api/v1/quick-requests/submit
- Submit new quick request via API
- JSON payload validation
- Async processing support

GET /api/v1/quick-requests/{id}/status
- Check request processing status
- Return detailed progress information

POST /api/v1/docuseal/generate-builder-token
- Generate DocuSeal form builder tokens
- Support for custom form configurations
```

### Api/V1/ProviderOnboardingController
**Location:** `app/Http/Controllers/Api/V1/ProviderOnboardingController.php`
**Purpose:** Provider onboarding workflow API

#### Features
- Multi-step onboarding process
- Document upload and validation
- Credential verification
- Integration with external systems

### Api/V1/ProviderProfileController
**Location:** `app/Http/Controllers/Api/V1/ProviderProfileController.php`
**Purpose:** Provider profile management API

#### Capabilities
- Profile CRUD operations
- Credential management
- Facility associations
- Specialization tracking

## 🔍 Specialized API Controllers

### Api/MedicareMacValidationController
**Location:** `app/Http/Controllers/Api/MedicareMacValidationController.php`
**Purpose:** Medicare MAC validation and compliance checking

#### Key Features
```php
public function validateOrder(Request $request): JsonResponse
- Comprehensive Medicare validation
- MAC jurisdiction determination
- CPT/HCPCS code validation
- Documentation requirement analysis

public function getMacByZipCode(Request $request): JsonResponse
- ZIP code to MAC mapping
- Jurisdiction boundary checking
- Regional policy application

public function validateSpecialty(Request $request): JsonResponse
- Provider specialty validation
- Specialty-specific requirements
- Credentialing verification
```

#### Validation Categories
```php
private function validateWoundCareSpecialty(array $data): array
- Wound care specific validations
- Treatment protocol compliance
- Documentation requirements

private function validatePulmonologySpecialty(array $data): array
- Pulmonology specific requirements
- Respiratory therapy compliance
- Equipment documentation
```

### Api/EligibilityController
**Location:** `app/Http/Controllers/Api/EligibilityController.php`
**Purpose:** Insurance eligibility verification

#### Methods
```php
public function checkEligibility(Request $request): JsonResponse
- Real-time eligibility verification
- Multi-payer support
- Cached result management

public function getPriorAuthRequirements(Request $request): JsonResponse
- Prior authorization requirement checking
- Payer-specific rules
- Documentation requirements
```

### Api/DocumentIntelligenceController
**Location:** `app/Http/Controllers/Api/DocumentIntelligenceController.php`
**Purpose:** AI-powered document processing

#### Capabilities
- Document OCR and analysis
- Data extraction and validation
- Form pre-filling
- Medical terminology processing

## 👑 Admin Controllers

### Admin/OrderCenterController
**Location:** `app/Http/Controllers/Admin/OrderCenterController.php`
**Purpose:** Administrative order management

#### Features
```php
public function index(): Response
- Order dashboard for administrators
- Bulk order operations
- Status monitoring and updates

public function updateOrderStatus(Request $request): JsonResponse
- Administrative status updates
- Workflow override capabilities
- Audit trail maintenance

public function bulkActions(Request $request): JsonResponse
- Bulk order processing
- Mass status updates
- Batch document generation
```

### Admin/ProviderManagementController
**Location:** `app/Http/Controllers/Admin/ProviderManagementController.php`
**Purpose:** Provider administration and management

#### Responsibilities
- Provider account management
- Credential oversight
- Performance monitoring
- Compliance tracking

### Admin/UsersController
**Location:** `app/Http/Controllers/Admin/UsersController.php`
**Purpose:** User and role management

#### RBAC Operations
```php
public function assignRoles(Request $request, User $user): JsonResponse
- Role assignment to users
- Permission validation
- Audit logging

public function syncRoles(Request $request, User $user): JsonResponse
- Bulk role synchronization
- Permission updates
- Organization-level assignments

public function removeRole(User $user, Role $role): JsonResponse
- Role removal with validation
- Dependent permission cleanup
- Security audit trail
```

## 💰 Commission Controllers

### Commission/CommissionController
**Location:** `app/Http/Controllers/Commission/CommissionController.php`
**Purpose:** Commission calculation and management

#### Features
- Real-time commission calculation
- Territory-based commission rules
- Performance analytics
- Payout management

### Commission/CommissionRuleController
**Location:** `app/Http/Controllers/Commission/CommissionRuleController.php`
**Purpose:** Commission rule configuration

#### Rule Types
- Product-based commission rates
- Territory-specific multipliers
- Performance tier bonuses
- Override commission rules

## 🔧 Integration Controllers

### DocusealWebhookController
**Location:** `app/Http/Controllers/DocusealWebhookController.php`
**Purpose:** DocuSeal webhook handling

#### Webhook Events
```php
public function handle(Request $request): JsonResponse
- Document completion events
- Signature collection updates
- Form submission processing
- Error handling and retries
```

### FhirController
**Location:** `app/Http/Controllers/FhirController.php`
**Purpose:** FHIR resource management

#### FHIR Operations
- Patient resource management
- Provider resource synchronization
- Observation and condition tracking
- PHI-compliant data handling

## 🛡️ Security & Middleware

### Controller Security Patterns
```php
// Example middleware application
class QuickRequestController extends Controller {
    public function __construct() {
        $this->middleware('auth');
        $this->middleware('verified');
        $this->middleware('role:provider,admin');
        $this->middleware('throttle:orders,60,5'); // Rate limiting
    }
}
```

### Rate Limiting by Controller
```php
// Different rate limits for different controllers
'orders' => [60, 5],      // 5 requests per minute
'eligibility' => [60, 20], // 20 requests per minute
'documents' => [60, 10],   // 10 requests per minute
```

## 📊 Controller Performance Metrics

### Response Time Targets
- Dashboard Controllers: < 200ms
- API Controllers: < 100ms
- Document Controllers: < 2s
- Complex Validation: < 5s

### Caching Strategies
```php
// Example controller caching
public function index() {
    $cacheKey = "dashboard_data_{$user->id}_{$user->role}";
    
    return Cache::remember($cacheKey, 300, function() use ($user) {
        return $this->buildDashboardData($user);
    });
}
```

## 🧪 Controller Testing

### Testing Patterns
```php
// Example controller test
class QuickRequestControllerTest extends TestCase {
    use RefreshDatabase, WithFaker;
    
    public function test_provider_can_create_quick_request() {
        $provider = User::factory()->provider()->create();
        
        $response = $this->actingAs($provider)
            ->get('/quick-request/create');
            
        $response->assertOk()
            ->assertInertia(fn($page) => 
                $page->component('QuickRequest/CreateNew')
            );
    }
}
```

### API Testing
```php
public function test_api_eligibility_check() {
    $response = $this->postJson('/api/v1/eligibility/check', [
        'patient_id' => $this->patient->id,
        'insurance_id' => $this->insurance->id,
    ]);
    
    $response->assertOk()
        ->assertJsonStructure([
            'eligible',
            'coverage_details',
            'prior_auth_required'
        ]);
}
```

## 🔄 Controller Lifecycle

### Request Flow
1. **Route Resolution** → Controller method
2. **Middleware Stack** → Authentication, authorization, rate limiting
3. **Request Validation** → Form requests and validation rules
4. **Business Logic** → Service layer interaction
5. **Response Formation** → JSON API or Inertia responses
6. **Logging & Metrics** → Performance and audit logging

### Error Handling
```php
// Standardized error responses
protected function handleApiError(\Exception $e): JsonResponse {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'timestamp' => now()->toISOString(),
    ], $this->getHttpStatusCode($e));
}
```

## 📈 Future Controller Enhancements

### Planned Improvements
1. **API Versioning Strategy** - Comprehensive v2 API development
2. **GraphQL Integration** - Flexible query capabilities
3. **Event-Driven Architecture** - Controller event publishing
4. **Advanced Caching** - Redis-based response caching

### Controller Roadmap
- Q1 2025: Enhanced API validation and error handling
- Q2 2025: Real-time capabilities with WebSocket controllers
- Q3 2025: Advanced analytics and reporting controllers
- Q4 2025: Machine learning integration controllers

---

**Related Documentation:**
- [API Documentation](../development/API_DOCUMENTATION.md)
- [Services Architecture](./SERVICES_ARCHITECTURE.md)
- [Security Architecture](./SECURITY_ARCHITECTURE.md)
