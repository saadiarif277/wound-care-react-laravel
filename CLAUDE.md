<<<<<<< HEAD
Looking at the new workflow documentation and the database change, I'll update the Claude.md to reflect these significant architectural changes:

=======
>>>>>>> origin/provider-side
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

<<<<<<< HEAD
# MSC Wound Portal - Codebase Guide for Claude

## Project Overview
MSC Wound Portal is a HIPAA-compliant healthcare platform for wound care distribution management. Built with Laravel 11, React 18, and TypeScript, it features episode-based order workflows through the Quick Request system, sophisticated DocuSeal IVR integration, and strict PHI/non-PHI data separation between Azure SQL (operational) and Azure Health Data Services (clinical).

## 🚨 CRITICAL: Azure FHIR Migration
**URGENT**: Azure API for FHIR will be retired on September 30, 2026
- New deployments blocked starting April 1, 2025
- Must migrate to Azure Health Data Services FHIR® service
- Current endpoint: `medexchangefhir-fhirserver.fhir.azurehealthcareapis.com`
- Migration impacts authentication, endpoints, and API features

## Important Project Rules
- **Always use permissions**, and never use mock data
- Organizations can have multiple facilities and providers
- Facilities have only one office manager
- Providers can have relationships with multiple facilities and organizations
- Always fully finish a feature including all functionality before moving on
- Always use UUID for primary keys (following existing patterns)
- Patients can only request one product with multiple sizes per order
- Patient display format: `JOSM473` (first 2 letters of first + last name + 3 random digits)
- Orders are grouped into episodes by patient+manufacturer combination
- Follow the Quick Request workflow for all new episodes and orders
- Use episode-based workflows, not individual order management
- NEVER create files unless absolutely necessary
- ALWAYS prefer editing existing files to creating new ones
- Follow existing architectural patterns and conventions

## Build Commands & Setup

### Prerequisites
- PHP 8.3+
- Node.js 22+ (LTS required)
- Composer 2.x
- Azure SQL Database
- Azure Health Data Services workspace
- Redis (for caching and queues)
- AWS S3 (for file storage)

### Development Commands
```bash
# Install dependencies
composer install
npm install

# Development servers
php artisan serve
npm run dev  # Vite dev server

# Build for production
npm run build  # Vite production build

# Database (Azure SQL)
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed  # Reset and seed

# DocuSeal Template Sync
php artisan docuseal:sync-templates
php artisan docuseal:sync-templates --force  # Force update
php artisan docuseal:sync-templates --queue  # Process via queue

# Testing
php artisan test
php artisan test --filter=QuickRequest  # Quick Request tests
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
npx vitest  # Frontend tests

# Code quality
npx tsc --noEmit  # TypeScript type checking
npx eslint resources/js --ext .ts,.tsx  # Linting
php artisan pint  # PHP formatting

# FHIR Connection Test
php artisan fhir:test-connection
```

## Architecture

### Tech Stack
- **Backend**: Laravel 11, PHP 8.3+
- **Frontend**: React 18, TypeScript 5.x, Inertia.js
- **Styling**: Tailwind CSS 3.x with glassmorphic design system
- **State**: Zustand, React Context API
- **Database**: Azure SQL Database (non-PHI), Azure Health Data Services FHIR R4 (PHI)
- **Auth**: Laravel Sanctum with role-based access
- **Queues**: Laravel Horizon with Redis
- **File Storage**: AWS S3
- **Document Signing**: DocuSeal API
- **Caching**: Redis
- **Email**: Laravel Mail with queued notifications

### Data Architecture (HIPAA Compliance)

**Azure SQL Database (Non-PHI)**:
```yaml
Operational Data:
  - User accounts and authentication
  - Organizations, facilities, providers
  - Product catalogs and pricing
  - Episodes (patient references only)
  - Orders (initial and follow-up)
  - Commission structures
  - DocuSeal template configurations
  - Manufacturer relationships
  - Audit logs (non-PHI)
  - Task references for approval workflows
```

**Azure Health Data Services (PHI)**:
```yaml
Clinical Data:
  - FHIR Patient resources
  - FHIR Practitioner resources
  - FHIR Organization resources
  - FHIR Condition (diagnoses)
  - FHIR EpisodeOfCare (care coordination)
  - FHIR Coverage (insurance)
  - FHIR Encounter (visits)
  - FHIR QuestionnaireResponse (assessments)
  - FHIR DeviceRequest (product orders)
  - FHIR Task (approval workflows)
  - FHIR DocumentReference (PDFs)
```

### Quick Request Workflow
The Quick Request system is the primary workflow for initiating episodes and placing orders:

#### UI Flow (5 Steps):
1. **Patient & Insurance** (`Step2PatientInsurance`)
2. **Clinical & Billing** (`Step4ClinicalBilling`)
3. **Product Selection** (`Step5ProductSelection`)
4. **DocuSeal IVR** (`Step7DocuSealIVR`)
5. **Review & Submit** (`Step6ReviewSubmit`)

#### FHIR Orchestration Sequence:
```mermaid
sequenceDiagram
  App->>FHIR: createPatient()
  App->>FHIR: createPractitioner()
  App->>FHIR: createOrganization()
  App->>FHIR: createCondition()
  App->>FHIR: createEpisodeOfCare()
  App->>FHIR: createCoverage()
  App->>FHIR: createEncounter()
  App->>FHIR: createQuestionnaireResponse()
  App->>FHIR: createDeviceRequest()
  App->>FHIR: createTask() // approval
```

#### API Endpoints:
```
POST   /api/v1/quick-request/episodes                 # Create episode + initial order
GET    /api/v1/quick-request/episodes/{episode}       # Get episode details
POST   /api/v1/quick-request/episodes/{episode}/orders # Add follow-up order
GET    /api/v1/quick-request/episodes/{episode}/orders # List episode orders
POST   /api/v1/quick-request/episodes/{episode}/approve # Approve episode
```

### Episode-Based Architecture
```typescript
// Episode structure
interface Episode {
  id: string;  // UUID
  patient_fhir_id: string;  // FHIR Patient reference
  practitioner_fhir_id: string;  // FHIR Practitioner reference
  organization_fhir_id: string;  // FHIR Organization reference
  status: 'draft' | 'pending_review' | 'manufacturer_review' | 'completed';
  orders: Order[];
  created_at: string;
  updated_at: string;
}

// Order structure
interface Order {
  id: string;  // UUID
  episode_id: string;  // FK to Episode
  based_on?: string;  // FK to parent Order (for follow-ups)
  type: 'initial' | 'follow_up';
  details: object;  // JSON payload
  created_at: string;
  updated_at: string;
}
```

### Key Healthcare Integrations
- **Azure FHIR**: Complete FHIR R4 resource management
- **DocuSeal**: IVR forms with dynamic template selection
- **Medicare MAC**: Validation engine for compliance
- **Email Notifications**: Queued manufacturer approvals

## Project Structure
```
msc-wound-portal/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/
│   │   │   │   ├── QuickRequestEpisodeController.php
│   │   │   │   └── QuickRequestOrderController.php
│   │   │   └── Admin/
│   │   │       └── OrderCenterController.php
│   │   └── Requests/Api/V1/QuickRequest/  # Request validation
│   ├── Models/
│   │   ├── Episode.php  # Core episode model
│   │   ├── Order.php    # Order model with relationships
│   │   └── DocuSeal/
│   │       └── Document.php  # Polymorphic attachments
│   ├── Services/
│   │   ├── FhirService.php  # Azure FHIR integration
│   │   ├── DocusealService.php  # PDF generation
│   │   └── QuickRequestService.php  # Orchestration
│   ├── Mail/
│   │   └── ManufacturerApprovalMail.php
│   └── Jobs/
│       └── ProcessQuickRequestJob.php
├── resources/
│   ├── js/
│   │   ├── Components/
│   │   │   ├── QuickRequest/  # Quick Request UI components
│   │   │   │   ├── Step2PatientInsurance.tsx
│   │   │   │   ├── Step4ClinicalBilling.tsx
│   │   │   │   ├── Step5ProductSelection.tsx
│   │   │   │   ├── Step7DocuSealIVR.tsx
│   │   │   │   └── Step6ReviewSubmit.tsx
│   │   │   └── ui/  # Reusable UI components
│   │   ├── Pages/
│   │   └── types/
│   │       └── quickRequest.ts  # TypeScript definitions
│   └── css/
├── database/
│   └── migrations/
│       ├── create_episodes_table.php
│       └── create_orders_table.php
├── docs/
│   ├── ivr-forms/  # DocuSeal templates by manufacturer
│   └── features/
│       └── quick-request-workflow.md
└── storage/
    └── app/public/insurance-verifications/  # Generated PDFs
```

## Common Development Patterns

### Quick Request Implementation
```php
// Episode creation with FHIR orchestration
class QuickRequestService {
    public function createEpisode(array $data): Episode {
        // 1. Create FHIR resources
        $patientId = $this->fhirService->createPatient($data['patient']);
        $practitionerId = $this->fhirService->createPractitioner($data['provider']);
        
        // 2. Create EpisodeOfCare
        $episodeOfCareId = $this->fhirService->createEpisodeOfCare([
            'patient' => $patientId,
            'team' => [$practitionerId],
            'managingOrganization' => $organizationId
        ]);
        
        // 3. Create local episode
        return Episode::create([
            'patient_fhir_id' => $patientId,
            'practitioner_fhir_id' => $practitionerId,
            'organization_fhir_id' => $organizationId,
            'status' => 'draft'
        ]);
    }
}
```

### FHIR Resource Creation with Error Handling
```php
// Retry pattern for FHIR calls
$retry = retry(3, function () use ($data) {
    return $this->fhirClient->create('Patient', $data);
}, 1000); // Exponential backoff
```

### DocuSeal Integration
```php
// Template selection based on manufacturer
$template = "docs/ivr-forms/{$manufacturer->name}/insurance-verification.docx";
$fields = $this->mapFhirToDocuSeal($fhirData, $orderDetails);
$pdf = $this->docuSealService->generatePdf($template, $fields);

// Store PDF reference
Document::create([
    'documentable_type' => Episode::class,
    'documentable_id' => $episode->id,
    'path' => $pdf->path,
    'type' => 'insurance_verification'
]);
```

### State Transitions
```typescript
// Episode status flow
type EpisodeStatus = 'draft' | 'pending_review' | 'manufacturer_review' | 'completed';

// Task-driven transitions
const transitionEpisode = async (episodeId: string, action: string) => {
  const task = await fhirClient.updateTask(taskId, {
    status: 'completed',
    businessStatus: { text: action }
  });
  
  await updateEpisodeStatus(episodeId, getNextStatus(action));
};
```

## Security & Compliance

### HIPAA Compliance Checklist
- ✅ PHI stored only in Azure FHIR
- ✅ Operational data in Azure SQL (non-PHI)
- ✅ Comprehensive audit logging
- ✅ Role-based access control (RBAC)
- ✅ Encrypted data transmission (TLS 1.2+)
- ✅ Token-based authentication with expiry
- ✅ Patient display IDs instead of names
- ✅ Business Associate Agreements (BAA)
- ✅ PDF storage with access controls

### Database Security (Azure SQL)
```php
// Connection configuration
'sqlsrv' => [
    'driver' => 'sqlsrv',
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', '1433'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8',
    'prefix' => '',
    'encrypt' => true,  // Always encrypted
    'trust_server_certificate' => false,
];
```

## Testing Strategy

### Quick Request Testing
```bash
# API integration tests
php artisan test --filter=QuickRequest

# FHIR orchestration tests (mocked)
php artisan test --filter=QuickRequestFhirTest

# PDF generation tests
php artisan test --filter=DocuSealPdfTest
```

### Test Patterns
```php
// Mock FHIR responses
Http::fake([
    '*/Patient' => Http::response(['id' => 'test-patient-id'], 201),
    '*/EpisodeOfCare' => Http::response(['id' => 'test-episode-id'], 201),
]);

// Assert PDF contains fields
$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile($path);
$text = $pdf->getText();
$this->assertStringContainsString('Patient Name:', $text);
```

### Compliance Testing
- Verify FHIR headers: `application/fhir+json`
- Audit log creation for all PHI access
- Task state transitions
- Email notification delivery

## Common Issues & Solutions

### Azure SQL Connection
- Ensure firewall rules allow app access
- Use connection pooling for performance
- Monitor DTU usage for scaling needs

### Quick Request Flow
- If FHIR call fails, log but don't block user
- Store partial progress in episode drafts
- Use queued jobs for long operations

### DocuSeal Templates
- Templates in `docs/ivr-forms/{ManufacturerName}/`
- Ensure field placeholders match mapping keys
- PDF storage path: `storage/app/public/insurance-verifications/`

### Episode Status Management
```
draft → pending_review → manufacturer_review → completed
```
- Status changes trigger FHIR Task updates
- Email notifications sent on key transitions

## Performance Optimization
- FHIR calls use retry with exponential backoff
- Azure SQL queries optimized with proper indexes
- PDF generation queued for async processing
- Redis caching for frequently accessed data
- Batch FHIR resource creation where possible

## Current Architecture Benefits
1. **Azure SQL**: Better integration with Azure ecosystem, improved performance
2. **Quick Request Workflow**: Streamlined episode creation with full FHIR orchestration
3. **Task-Based Approvals**: FHIR-compliant workflow management
4. **DocuSeal Integration**: Dynamic template selection per manufacturer
5. **Comprehensive Testing**: Unit, integration, and E2E test coverage

## MSC Brand Guidelines
- Primary Blue: `#1925c3`
- Primary Red: `#c71719`
- Gradient: `from-[#1925c3] to-[#c71719]`
- Glass effects: 30% opacity with backdrop blur
- Typography: System font stack with medical clarity

## Laravel Development Best Practices

### 1. Follow Laravel's Conventions
- Use PSR-12 coding standards for PHP.
- Name controllers, models, and other classes according to Laravel's naming conventions (e.g., PascalCase for class names, snake_case for database columns).
- Place logic in the appropriate layers (e.g., controllers for handling requests, models for database interactions, services for business logic).

### 2. Use Eloquent ORM Effectively
- Leverage Eloquent relationships (e.g., hasOne, hasMany, belongsTo) to simplify database queries.
- Use query scopes for reusable query logic.
- Avoid N+1 query problems by using eager loading (with()).

### 3. Keep Controllers Thin
- Avoid placing business logic in controllers. Instead, use Service Classes or Action Classes to handle complex logic.
- Controllers should primarily handle request validation and return responses.

### 4. Validate Input Properly
- Use Form Requests for validation instead of writing validation logic directly in controllers.
- Always sanitize and validate user input to prevent security vulnerabilities like SQL injection or XSS.

### 5. Use Migrations and Seeders
- Use migrations to manage database schema changes and ensure consistency across environments.
- Use seeders and factories to populate test data for development and testing.

### 6. Secure Your Application
- Use Laravel's built-in CSRF protection for forms.
- Escape output using {{ }} to prevent XSS attacks.
- Use hashed passwords with Laravel's Hash facade.
- Avoid exposing sensitive data in .env files and use proper environment variable management.

### 7. Optimize Performance
- Cache frequently accessed data using Laravel's caching mechanisms (e.g., Redis, file cache).
- Use queues for time-consuming tasks like sending emails or processing files.
- Optimize database queries by indexing columns and avoiding unnecessary joins.

### 8. Write Tests
- Use PHPUnit or Pest to write unit and feature tests.
- Test controllers, models, and services to ensure your application behaves as expected.
- Use Laravel's testing helpers for mocking and simulating HTTP requests.

### 9. Use Dependency Injection
- Inject dependencies (e.g., services, repositories) into controllers or classes instead of instantiating them directly.
- Use Laravel's Service Container to manage dependencies.

### 10. Keep Code DRY (Don't Repeat Yourself)
- Reuse code by creating helpers, traits, or service classes.
- Use Blade components and layouts to avoid duplicating HTML in views.

### 11. Use Version Control
- Use Git for version control and maintain a clean commit history.
- Follow a branching strategy like Git Flow or Trunk-Based Development.

### 12. Stay Updated
- Regularly update Laravel and its dependencies to the latest stable versions to benefit from security patches and new features.

By adhering to these principles, you'll ensure your Laravel application is robust, maintainable, and scalable.

This guide reflects the current state of the MSC Wound Portal with the Quick Request workflow and Azure SQL implementation. Reference this when implementing new features to ensure consistency with established patterns and compliance requirements.

---
=======
## AI Operating Instructions

VERSION: 2025-06-28-hash-abc123

1. Always load and parse both files:
   - `docs/ai/myrules.md` - Full governance rules (source of truth)
   - `docs/ai/myrules_pipe.md` - Quick reference index

2. Usage:
   - Use pipe format to quickly identify applicable rule categories
   - Reference full rules for implementation details
   - When in doubt, full rules supersede pipe format

3. Validation:
   - Check both files are present before proceeding
   - Verify pipe format matches full rules version

## Important Notes

- **Database Migration**: This project recently migrated from Supabase PostgreSQL to Azure SQL. The README may still reference Supabase, but the actual implementation uses Azure SQL.
- **Laravel Version**: Using Laravel 11 (not 10) - Note that Laravel 11+ does not have Console\Kernel.php
- **No Mock Data**: NEVER use mock data in production or as a fallback - this is a strict requirement for healthcare compliance

## Development Commands

### Initial Setup

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# Run database migrations and seeders
php artisan migrate
php artisan db:seed
```

### Daily Development

```bash
# Start Laravel development server
php artisan serve

# Start Vite development server (in separate terminal)
npm run dev

# Run both frontend and backend tests
npm run test:all

# Run Laravel tests only
php artisan test
php artisan test --filter=SpecificTest

# Run React/Jest tests only
npm test
npm run test:watch

# Lint and type-check frontend code
npm run lint
npm run lint:fix
npm run type-check

# Build for production
npm run prod
```

### Database Commands

```bash
# Refresh database with fresh migrations and seeders
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=DiagnosisCodesFromCsvSeeder

# Create new migration
php artisan make:migration create_table_name
```

### Common Artisan Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# List routes
php artisan route:list
php artisan route:list --path=api

# Create new controller
php artisan make:controller Api/ControllerName --resource

# Create new service
php artisan make:service ServiceName
```

## Architecture Overview

### Technology Stack

- **Backend**: Laravel 11 with PHP 8.2+
- **Frontend**: React 18 with TypeScript, Inertia.js
- **Databases**:
  - Azure mySQL (non-PHI operational data)
  - Azure Health Data Services FHIR (PHI/clinical data)
- **Authentication**: Laravel Sanctum
- **State Management**: React Context API
- **Document Processing**: DocuSeal, Azure Document Intelligence

### Data Architecture (HIPAA Compliance)

**Critical**: This application maintains strict PHI/non-PHI data separation for HIPAA compliance.

**Non-PHI Data (Azure mySQL)**:

- User accounts, organizations, facilities
- Product catalogs, pricing, inventory
- Commission structures and calculations
- Order metadata (without clinical details)
- System configuration and settings

**PHI Data (Azure FHIR)**:

- Patient demographics and identifiers
- Clinical documentation and wound assessments
- Insurance information
- Medical images and clinical notes
- Diagnosis codes and treatment plans

**Data Flow Example**:

Patient Registration → Create FHIR Patient Resource → Store Patient ID in local DB
                    ↓
                    Azure FHIR (PHI)
                    ↓
                    Reference by FHIR ID only in local database

### Service Layer Architecture

The application uses a service-oriented architecture with key services:

1. **FhirService** (`app/Services/FhirService.php`)
   - Manages all Azure FHIR interactions
   - Implements circuit breaker pattern for resilience
   - Handles PHI data operations

2. **DocusealService** (`app/Services/DocusealService.php`)
   - Manages insurance verification form generation
   - Handles webhook processing for completed forms
   - JWT authentication for secure webhooks

3. **QuickRequestOrchestrator** (`app/Services/QuickRequestOrchestrator.php`)
   - Coordinates multi-step episode creation workflow
   - Manages state transitions and validations
   - Integrates multiple services for complex operations

4. **ValidationBuilderEngine** (`app/Services/ValidationBuilderEngine.php`)
   - CMS coverage determination logic
   - Medicare LCD/NCD validation
   - Clinical criteria evaluation

5. **ImprovedOcrFieldDetectionService** (`app/Services/ImprovedOcrFieldDetectionService.php`)
   - Azure Document Intelligence integration
   - Insurance card OCR processing
   - Field extraction and validation

6. **UnifiedFieldMappingService** (`app/Services/UnifiedFieldMappingService.php`)
   - Centralized field mapping for all manufacturers
   - Dynamic form field transformation
   - Manufacturer-specific configuration loading

### API Structure

**API Routes** (`routes/api.php`):

- `/api/v1/` - Versioned API endpoints
- `/api/fhir/` - FHIR server REST API
- `/api/docuseal/webhook` - DocuSeal webhook endpoint

**Key API Groups**:

- Quick Request workflow: `/api/v1/quick-request/*`
- Medicare validation: `/api/v1/medicare-validation/*`
- Eligibility checking: `/api/v1/eligibility/*`
- Clinical opportunities: `/api/v1/clinical-opportunities/*`

### Frontend Architecture

**Inertia.js Pages** (`resources/js/Pages/`):

- Server-side rendered React components
- Automatic prop injection from Laravel controllers
- No separate API layer needed for page navigation

**Component Structure**:

- Glassmorphic design system components
- Reusable form components with react-hook-form
- TypeScript for type safety

**State Management**:

- React Context for theme and global state
- Local component state for forms
- Server state via Inertia props

### Testing Strategy

**Backend Testing**:

- Feature tests for API endpoints
- Unit tests for services and models
- Database tests with transactions
- FHIR integration tests with mocks

**Frontend Testing**:

- Jest with React Testing Library
- Component unit tests
- Integration tests for workflows
- 80% coverage threshold (Note: governance rules specify 85% but jest.config.js uses 80%)

### Security Considerations

1. **PHI Access Auditing**: All PHI access is logged via PhiAuditService
2. **Log Sanitization**: PhiSafeLogger prevents PHI in application logs
3. **Role-Based Access**: Granular permissions system
4. **API Authentication**: Sanctum tokens with CSRF protection
5. **Webhook Security**: JWT validation for external webhooks

### Common Development Patterns

**Creating New Features**:

1. Start with database migration if needed
2. Create service class for business logic
3. Add controller with dependency injection
4. Create Inertia page component
5. Add feature and unit tests
6. Update API documentation

**Working with PHI Data**:

1. Always use FhirService for PHI operations
2. Never store PHI in local database
3. Use FHIR resource IDs as references
4. Implement audit logging for access
5. Test with PHI-safe mock data

**DocuSeal Integration Flow**:

1. Generate template with patient/order data
2. Embed DocuSeal component in React
3. Handle completion webhook
4. Process OCR enhancement if needed
5. Update order status

### Performance Considerations

1. **Caching**: Use Laravel cache for CMS rules and eligibility responses
2. **Queue Jobs**: Long-running tasks should use queued jobs
3. **Database Queries**: Use eager loading to prevent N+1 queries
4. **API Responses**: Implement pagination for large datasets
5. **Circuit Breakers**: External API calls use circuit breaker pattern

### System Memories

- Remember our platform is permissions based
- Never use mock data or even have it as a fall back

## RBAC System Implementation

### Role Structure (Database Slugs)

All roles use hyphenated slugs in the database:
- `provider` - Healthcare Provider
- `office-manager` - Office Manager  
- `msc-rep` - MSC Sales Representative
- `msc-subrep` - MSC Sub-Representative
- `msc-admin` - MSC Administrator
- `super-admin` - Super Administrator

### Permission System

The system uses 19 granular permissions:
- User management: `view-users`, `edit-users`, `delete-users`
- Financial access: `view-financials`, `view-msc-pricing`, `view-discounts`, `view-order-totals`
- Order operations: `create-orders`, `approve-orders`, `process-orders`
- System management: `manage-products`, `manage-commission`, `manage-settings`, `manage-system`
- Viewing permissions: `view-commission`, `view-reports`, `view-phi`
- Admin access: `super-admin-access`, `msc-admin-access`

### Implementation Guidelines

- **Backend**: Always use `$user->hasPermission('permission-name')` for checks
- **Frontend**: Use permission-based visibility, not role checks
- **Middleware**: Apply `permission:permission-name` middleware to routes
- **Legacy**: The old UserRole.php model is removed - use Role.php with permissions

### Commission Access Levels

- Provider/Office Manager: `commission_access_level: 'none'`
- MSC SubRep: `commission_access_level: 'limited'`
- MSC Rep/Admin/Super Admin: `commission_access_level: 'full'`

## IVR Tips

### Exact Field Name Matching

- DocuSeal requires character-perfect field name matches
- Even extra spaces or punctuation differences cause "Unknown field" errors

### String vs Numeric Comparisons

- Form values are stored as strings ('31', '32') not numbers (31, 32)
- Computed field logic must use proper string comparisons: == "31"

### Field Separation Strategy

- Physician fields: Individual provider data (Physician NPI, Physician PTAN)
- Facility fields: Practice/clinic data (Practice NPI, Practice PTAN)
- Clear naming prevents PTAN confusion

### Default Value Handling

- Boolean-style fields should default to explicit "No" unless conditions are met
- Place of Service checkboxes now properly activate based on selected option

## Manufacturer Configuration

The system supports multiple wound care manufacturers with custom field mappings:

### Configuration Files (`config/manufacturers/`)

- Each manufacturer has its own configuration file
- Field mappings define how data transforms between systems
- Supports both IVR forms and standard order forms
- Examples: `advanced-solution.php`, `biowound-solutions.php`, `centurion-therapeutics.php`

### Field Mapping Structure

```php
'field_mappings' => [
    'source_field' => 'destination_field',
    'patient.first_name' => 'Patient First Name',
    // Complex mappings with transformations
]
```

### Dynamic Loading

The UnifiedFieldMappingService automatically loads the correct configuration based on:
- Manufacturer selection in the product
- Form type (IVR vs standard)
- Organization-specific overrides
>>>>>>> origin/provider-side
