# Project Structure

## Root Directory
```
/home/rvalen/Projects/msc-woundcare-portal/
├── app/                    # Laravel application code
├── bootstrap/              # Framework bootstrap files
├── config/                 # Configuration files
├── database/               # Migrations, factories, seeders
├── public/                 # Public assets
├── resources/              # Views, React components, assets
├── routes/                 # Route definitions
├── storage/                # Logs, cache, uploads
├── tests/                  # Test files
└── vendor/                 # Composer dependencies
```

## App Directory Structure
```
app/
├── Console/Commands/       # Artisan commands
├── Contracts/              # Interface definitions
├── DTOs/                   # Data Transfer Objects
├── DataTransferObjects/    # Additional DTOs
├── Events/                 # Event classes
├── Exceptions/             # Custom exceptions
├── Helpers/                # Helper functions
├── Http/
│   ├── Controllers/        # HTTP controllers
│   ├── Middleware/         # HTTP middleware
│   └── Requests/           # Form requests
├── Jobs/                   # Queue jobs
├── Listeners/              # Event listeners
├── Mail/                   # Mailable classes
├── Models/                 # Eloquent models
├── Notifications/          # Notification classes
├── Observers/              # Model observers
├── Policies/               # Authorization policies
├── Providers/              # Service providers
├── Repositories/           # Repository classes
├── Rules/                  # Validation rules
├── Services/               # Business logic services
│   ├── AI/                 # AI-related services
│   ├── Azure/              # Azure integrations
│   ├── ClinicalOpportunityEngine/
│   ├── Compliance/
│   ├── Document/
│   ├── DocuSeal/
│   ├── Eligibility/
│   ├── Fhir/
│   ├── FieldMapping/
│   ├── Insurance/
│   ├── Medical/
│   └── QuickRequest/
└── Traits/                 # Reusable traits
```

## Resources Directory
```
resources/
├── css/                    # Stylesheets
├── js/
│   ├── Components/         # React components
│   │   ├── UI/            # UI components
│   │   └── Medical/       # Medical-specific components
│   ├── Pages/             # Inertia page components
│   ├── hooks/             # Custom React hooks
│   ├── services/          # Frontend services
│   ├── stores/            # Zustand stores
│   ├── types/             # TypeScript definitions
│   └── utils/             # Utility functions
└── views/                 # Blade templates
```

## Database Structure
```
database/
├── factories/             # Model factories
├── migrations/            # Database migrations
│   └── *.php             # Individual migration files
└── seeders/              # Database seeders
```

## Key Service Classes
- `FhirService`: FHIR data management
- `DocusealService`: Document generation/signing
- `UnifiedFieldMappingService`: Field transformations
- `ValidationBuilderEngine`: Validation factory
- `ClinicalOpportunityService`: Clinical decisions
- `CommissionRuleFinderService`: Commission logic

## Configuration Files
- `.env.example`: Environment template
- `composer.json`: PHP dependencies
- `package.json`: Node dependencies
- `tsconfig.json`: TypeScript config
- `tailwind.config.js`: Tailwind CSS config
- `vite.config.js`: Vite bundler config
- `phpunit.xml`: PHPUnit test config