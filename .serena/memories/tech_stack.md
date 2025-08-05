# Technology Stack

## Backend
- **Framework**: Laravel 11
- **Language**: PHP 8.2+
- **Database**: 
  - Azure SQL (non-PHI operational data)
  - Azure Health Data Services/FHIR (PHI data)
- **Authentication**: Laravel Sanctum
- **Queue/Cache**: Redis (recommended for production)
- **File Storage**: AWS S3 / Supabase Storage (S3-compatible)

## Frontend
- **Framework**: React 18 with TypeScript
- **SPA Bridge**: Inertia.js
- **UI Framework**: Tailwind CSS with glassmorphic design system
- **State Management**: Zustand
- **Component Library**: 
  - Radix UI
  - Headless UI
  - Custom glass-theme components
- **Form Handling**: React Hook Form with Zod validation
- **Charts**: Chart.js, Recharts
- **Icons**: React Icons, Lucide React, Heroicons

## Build Tools
- **Bundler**: Vite
- **CSS Processing**: PostCSS, Tailwind CSS
- **TypeScript**: Strict mode enabled with comprehensive type checking
- **Testing**: 
  - PHP: PHPUnit
  - JavaScript: Jest with React Testing Library

## External Services
- **Document Processing**: Docuseal API
- **AI Services**: 
  - Azure Document Intelligence (form extraction)
  - Azure OpenAI / Azure AI Foundry
- **Email**: Mailgun
- **Maps**: Google Maps API (optional)
- **Error Tracking**: Sentry

## Development Tools
- **Code Quality**:
  - PHP: Laravel Pint
  - JavaScript/TypeScript: ESLint
  - Prettier for formatting
- **Version Requirements**:
  - Node.js: 18+ (22 LTS recommended)
  - npm: 8.0.0+
  - PHP: 8.2+