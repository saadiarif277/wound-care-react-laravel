# MSC Wound Care React Laravel - Codebase Index

Generated on: 2025-07-04

## 🏥 Project Overview

**MSC Wound Portal** is a healthcare platform for wound care management with strict HIPAA compliance through data separation between operational data (Azure SQL) and PHI data (Azure Health Data Services).

### Technology Stack
- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: React 18, TypeScript, Inertia.js
- **Database**: Azure SQL (non-PHI), Azure FHIR (PHI)
- **Authentication**: Laravel Sanctum
- **UI**: Tailwind CSS, headlessui, glassmorphic design
- **State Management**: Zustand
- **File Storage**: AWS S3

## 📁 Directory Structure

### Backend (Laravel)

#### 🔧 Core Application (`/app`)
```
app/
├── Console/Commands/          # Artisan commands
├── Contracts/                 # Interfaces & contracts
├── Controllers/               # HTTP controllers
├── DTOs/                      # Data Transfer Objects
├── Events/                    # Event classes
├── Exceptions/                # Custom exceptions
├── Helpers/                   # Helper utilities
├── Http/                      # HTTP layer (controllers, middleware, requests)
├── Jobs/                      # Queue jobs
├── Listeners/                 # Event listeners
├── Logging/                   # Custom logging
├── Mail/                      # Email classes
├── Models/                    # Eloquent models
├── Notifications/             # Notification classes
├── Observers/                 # Model observers
├── Policies/                  # Authorization policies
├── Providers/                 # Service providers
├── Repositories/              # Repository pattern
├── Services/                  # Business logic services
└── Traits/                    # Reusable traits
```

#### 🎯 Key Service Directories (`/app/Services`)
- **AI/**: Azure AI integration
- **ClinicalOpportunityEngine/**: Clinical decision support
- **Compliance/**: HIPAA compliance & auditing
- **EligibilityEngine/**: Insurance eligibility checking
- **Fhir/**: FHIR data handling
- **FieldMapping/**: Form field mapping
- **FuzzyMapping/**: Intelligent field matching
- **HealthData/**: Health data services
- **Insurance/**: Insurance verification & processing
- **ProductRecommendationEngine/**: Product recommendation logic
- **QuickRequest/**: Quick order processing
- **Templates/**: Document template handling

#### 🗃️ Models Organization (`/app/Models`)
- **Commissions/**: Commission calculation models
- **Docuseal/**: Document sealing integration
- **Fhir/**: FHIR resource models
- **Insurance/**: Insurance-related models
- **Medical/**: Medical coding (ICD-10, CPT)
- **Order/**: Order management models
- **Users/**: User and organization models

### Frontend (React + TypeScript)

#### ⚛️ React Application (`/resources/js`)
```
resources/js/
├── Components/                # Reusable UI components
│   ├── Admin/                # Admin-specific components
│   ├── ClinicalOpportunities/ # Clinical decision support UI
│   ├── DiagnosisCode/        # Diagnosis code selectors
│   ├── Episodes/             # Episode management UI
│   ├── Form/                 # Form components
│   ├── GhostAiUi/           # AI assistant interface
│   ├── Header/              # Header components
│   ├── IVR/                 # IVR form components
│   ├── Menu/                # Navigation menus
│   ├── Onboarding/          # User onboarding flow
│   ├── Order/               # Order management UI
│   ├── ProductCatalog/      # Product selection UI
│   ├── QuickRequest/        # Quick order UI
│   └── ui/                  # Base UI components
├── Hooks/                   # Custom React hooks
├── Layouts/                 # Page layouts
├── Pages/                   # Page components
│   ├── Admin/              # Admin pages
│   ├── Auth/               # Authentication pages
│   ├── Dashboard/          # Dashboard views
│   ├── Provider/           # Provider portal
│   ├── QuickRequest/       # Quick request workflow
│   └── ...
├── contexts/               # React contexts
├── lib/                   # Utility libraries
├── services/              # API services
├── stores/                # Zustand stores
├── types/                 # TypeScript definitions
└── utils/                 # Utility functions
```

#### 🎨 UI Components & Design System
- **Glassmorphic Design**: Modern glass-effect UI with light/dark mode
- **GlassCard**: Base glassmorphic container
- **StatCard**: Metric display widgets
- **Button**: Medical-grade buttons with loading states
- **ThemeToggle**: Light/dark mode switcher
- **GhostAiUi**: AI assistant interface components

### Configuration & Setup

#### 📋 Config Files (`/config`)
- **azure.php**: Azure services configuration
- **cms.php**: CMS API settings
- **docuseal.php**: DocuSeal integration
- **eligibility.php**: Insurance eligibility providers
- **fhir.php**: FHIR server configuration
- **fuzzy_mapping.php**: Field mapping configuration
- **manufacturers/**: Manufacturer-specific configs

#### 🗄️ Database (`/database`)
```
database/
├── factories/              # Model factories for testing
├── migrations/             # Database migrations
└── seeders/               # Database seeders
```

#### 📊 Recent Migrations (July 2025)
- Order status history tracking
- Enhanced order polling system
- Updated PHI audit logs
- Order status workflow improvements

### Documentation (`/docs`)

#### 📚 Key Documentation Areas
- **api-documentation/**: API integration guides
- **compliance-and-regulatory/**: HIPAA compliance docs
- **data-and-reference/**: ICD-10 codes, diagnosis references
- **feature-documentation/**: Feature implementation guides
- **hipaa-compliance/**: Comprehensive HIPAA documentation
- **ivr-forms/**: Manufacturer IVR form templates
- **project-summaries/**: Project completion summaries

### Testing (`/tests`)

#### 🧪 Test Structure
```
tests/
├── Feature/                # Feature tests
├── Integration/            # Integration tests
├── Manual/                 # Manual testing scripts
└── Unit/                   # Unit tests
```

## 🔑 Key Features & Modules

### 1. 🏥 Wound Care Management
- Comprehensive wound assessment and tracking
- Clinical decision support
- Patient episode management
- Multi-provider collaboration

### 2. 💰 Commission Management
- Automated commission calculation
- Sales rep commission tracking
- Payout management system
- Commission rules engine

### 3. 📊 Order Management
- Quick request workflow
- Order tracking and status management
- Manufacturer integration
- Document automation (DocuSeal)

### 4. 🔒 HIPAA Compliance
- **Data Separation**: PHI (Azure FHIR) vs Non-PHI (Azure SQL)
- **Audit Logging**: Comprehensive audit trails
- **Access Control**: Role-based permissions
- **Encryption**: Data encrypted at rest and in transit

### 5. 🏢 Multi-Tenant Architecture
- Organization management
- Facility management
- Provider onboarding
- Role-based access control

### 6. 🤖 AI Integration
- Azure AI Document Intelligence
- Intelligent field mapping
- Clinical opportunity detection
- AI-powered form processing

## 🚀 Recent Development Activity

### Current Tasks (July 2025)
1. **Order Polling Implementation**: Real-time order status updates
2. **Clinical Validation Enhancements**: Improved validation workflows
3. **CSRF Token Fixes**: Authentication improvements
4. **Product Selection Improvements**: Enhanced product catalog
5. **UI Fixes**: Mobile responsiveness and user experience

### Recently Added Files
- `app/Http/Controllers/Api/OrderPollingController.php`
- `app/Observers/OrderObserver.php`
- `resources/js/hooks/useOrderPolling.ts`
- `resources/js/services/orderPollingService.ts`
- Migration: `2025_07_03_235548_create_order_status_history_table.php`

## 🛠️ Development Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+ (LTS recommended)
- Azure SQL Database
- Azure Health Data Services

### Quick Start Commands
```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed

# Development servers
php artisan serve
npm run dev

# Testing
php artisan test
npm run test
```

### Available Scripts
- `composer install`: PHP dependencies
- `npm install`: Node.js dependencies
- `php artisan migrate`: Run migrations
- `php artisan serve`: Laravel dev server
- `npm run dev`: Vite dev server
- `npm run build`: Production build
- `php artisan test`: Run PHPUnit tests

## 🔐 Security & Compliance

### HIPAA Compliance Features
- **Data Classification**: All data classified as PHI or non-PHI
- **Secure References**: PHI referenced via FHIR resource IDs
- **Audit Logging**: Comprehensive access logging
- **Role-Based Access**: Granular permission system
- **Encryption**: End-to-end encryption

### Security Middleware
- CSRF protection
- API rate limiting
- PHI access control
- Security headers
- Session management

## 📈 Architecture Patterns

### Backend Patterns
- **Repository Pattern**: Data access abstraction
- **Service Layer**: Business logic separation
- **Observer Pattern**: Model event handling
- **Provider Pattern**: Service registration
- **Policy Pattern**: Authorization logic

### Frontend Patterns
- **Component Composition**: Reusable UI components
- **Custom Hooks**: Reusable stateful logic
- **Context API**: Global state management
- **Error Boundaries**: Error handling
- **Lazy Loading**: Performance optimization

## 🎯 Business Logic Overview

### Core Workflows
1. **Provider Onboarding**: Multi-step registration process
2. **Quick Request**: Streamlined order creation
3. **Clinical Assessment**: Wound care evaluation
4. **Insurance Verification**: Eligibility checking
5. **Order Processing**: From request to fulfillment
6. **Commission Calculation**: Automated commission processing

### Integration Points
- **Azure FHIR**: PHI data storage
- **DocuSeal**: Document automation
- **Insurance APIs**: Eligibility verification
- **Manufacturer APIs**: Product data and ordering
- **Azure AI**: Document intelligence

This index provides a comprehensive overview of the MSC Wound Care React Laravel codebase, covering architecture, features, recent developments, and development workflows.
