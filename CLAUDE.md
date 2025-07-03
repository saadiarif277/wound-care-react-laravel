# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 7 Claude rules
1. First think through the problem, read the codebase for relevant files, and write a plan to tasks/todo.md.
2. The plan should have a list of todo items that you can check off as you complete them
3. Before you begin working, check in with me and I will verify the plan.
4. Then, begin working on the todo items, marking them as complete as you go.
5. Please every step of the way just give me a high level explanation of what changes you made
6. Make every task and code change you do as simple as possible. We want to avoid making any massive or complex changes. Every change should impact as little code as possible. Everything is about simplicity.
7. Finally, add a review section to the [todo.md](http://todo.md/) file with a summary of the changes you made and any other relevant information. 
8. Put that todo.md in a new Folder you make under ./tasks/* and name the folder after the subject of the todo.md was


## Project Overview

MSC Wound Portal - A HIPAA-compliant healthcare platform for wound care management with strict data separation between operational data (Azure SQL) and PHI data (Azure Health Data Services).

## Technology Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: React 18, TypeScript, Inertia.js
- **Database**: Azure SQL (non-PHI), Azure FHIR (PHI)
- **UI Framework**: Tailwind CSS with glassmorphic design system
- **State Management**: Zustand
- **Document Processing**: Docuseal API
- **AI Services**: Azure Document Intelligence, Azure Foundry

## Development Commands

### Setup & Installation
```bash
# Install dependencies
composer install
npm install

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed database
php artisan db:seed
```

### Development
```bash
# Start Laravel development server
php artisan serve

# Start Vite development server (in separate terminal)
npm run dev

# Build for production
npm run prod
```

### Testing
```bash
# Run all tests
npm run test:all

# PHP tests
php artisan test
php artisan test --coverage

# JavaScript tests
npm test
npm run test:coverage

# Specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### Code Quality
```bash
# Lint TypeScript/React code
npm run lint
npm run lint:fix

# Type checking
npm run type-check

# Laravel Pint (PHP linting)
./vendor/bin/pint
```

## Architecture Overview

### Service Layer Pattern
The application uses a comprehensive service layer for business logic:

- **FhirService**: FHIR data management and Azure Health Data Services integration
- **DocusealService**: Document generation, signing, and field mapping
- **UnifiedFieldMappingService**: Data transformation between formats
- **ValidationBuilderEngine**: Factory for creating validation engines per care type
- **ClinicalOpportunityService**: Clinical decision support
- **CommissionRuleFinderService**: Hierarchical commission rule resolution

### Key Architectural Patterns

1. **Inertia.js SPA**: Server-side routing with client-side navigation
2. **Service Container**: Dependency injection via Laravel's container
3. **Repository Pattern**: Implicit through Eloquent models
4. **Middleware Pipeline**: Authentication, authorization, CSRF protection
5. **Event-Driven**: Webhooks for external service updates

### Data Architecture

**Non-PHI (Azure SQL)**:
- Users, organizations, facilities
- Product catalogs, pricing
- Orders, commissions
- System configuration

**PHI (Azure FHIR)**:
- Patient demographics
- Clinical documentation
- Insurance information
- Medical assessments

### Security & Compliance

- **RBAC**: Role-based access control with granular permissions
- **PHI Audit Logging**: All PHI access tracked in audit logs
- **Financial Access Control**: Role-based pricing visibility
- **CSRF Protection**: Token-based request verification
- **API Rate Limiting**: Prevents abuse

## Key Workflows

### Quick Request Flow
1. Provider initiates product request
2. Patient data captured (PHI to FHIR)
3. Insurance eligibility verification
4. Product selection with AI recommendations
5. Docuseal document generation
6. Manufacturer notification
7. Order fulfillment tracking

### Episode-Based Orders
- Orders grouped by patient care episodes
- IVR (Insurance Verification Request) per episode
- Manufacturer-specific document templates
- Real-time status tracking via webhooks

### Commission System
- Hierarchical rule matching (product → manufacturer → category)
- Role-based commission visibility
- Automated payout calculations
- Financial audit trails

## Important Services & Features

### Docuseal Integration
- Template-based document generation
- AI-powered field mapping
- Webhook status updates
- Per-manufacturer custom fields

### FHIR Integration
- Currently local storage, Azure FHIR ready
- Patient, Practitioner, DocumentReference resources
- Compliant data handling with audit trails

### Validation Engines
- WoundCareValidationEngine
- PulmonologyWoundCareValidationEngine
- Extensible for new care types

## Common Tasks

### Adding a New Manufacturer Template
1. Create template in Docuseal
2. Run sync command: `php artisan docuseal:sync`
3. Map fields: `php artisan field-mapping:map {templateId}`
4. Test integration: `php artisan docuseal:test {templateId}`

### Debugging FHIR Issues
```bash
# Check FHIR service status
php artisan fhir:status

# Test FHIR connection
php artisan fhir:test

# View FHIR audit logs
php artisan fhir:audit --recent
```

### Managing Permissions
- Permissions defined in migration files
- Assigned to roles via seeders
- Check user permissions: `php artisan user:permissions {email}`

## Testing Strategy

- **Unit Tests**: Service layer logic
- **Feature Tests**: API endpoints and workflows
- **Integration Tests**: External service connections
- **E2E Tests**: Complete workflows (Quick Request, Orders)

Run specific test files:
```bash
php artisan test tests/Feature/QuickRequestTest.php
php artisan test --filter=Docuseal
```

## Environment Configuration

Key environment variables:
- `FHIR_BASE_URL`: Azure FHIR endpoint
- `DOCUSEAL_API_KEY`: Docuseal authentication
- `AZURE_AI_*`: AI service credentials
- `CMS_API_*`: CMS integration settings

## Performance Considerations

- Episode caching via Redis
- Lazy loading for FHIR resources
- Pagination for large datasets
- Queue workers for async operations

## Deployment

The application supports Azure deployment with:
- Azure App Service for Laravel
- Azure SQL Database
- Azure Health Data Services (FHIR)
- Azure Storage for documents
- Azure Key Vault for secrets