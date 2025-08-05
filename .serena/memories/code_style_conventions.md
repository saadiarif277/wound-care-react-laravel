# Code Style and Conventions

## TypeScript/React Conventions
- **Strict TypeScript**: All strict checks enabled in tsconfig.json
- **File Structure**: 
  - Components in `resources/js/Components/`
  - Pages in `resources/js/Pages/`
  - Types in `resources/js/types/`
  - Hooks in `resources/js/hooks/`
  - Services in `resources/js/services/`
- **Naming**:
  - Components: PascalCase (e.g., `GlassCard.tsx`)
  - Hooks: camelCase with 'use' prefix (e.g., `useAuth.ts`)
  - Types/Interfaces: PascalCase
- **Imports**: Use absolute imports with `@/` prefix
- **Props**: Define interfaces for all component props
- **State Management**: Zustand stores in `resources/js/stores/`

## PHP/Laravel Conventions
- **PSR-4 Autoloading**: `App\\` namespace maps to `app/` directory
- **Service Layer**: Business logic in service classes under `app/Services/`
- **DTOs**: Data Transfer Objects in `app/DTOs/` and `app/DataTransferObjects/`
- **Repository Pattern**: Implicit through Eloquent models
- **Naming**:
  - Classes: PascalCase
  - Methods: camelCase
  - Database: snake_case
- **Type Hints**: Use PHP type declarations for parameters and return types
- **Docblocks**: Use for complex methods and services

## Database Conventions
- **Tables**: Plural, snake_case (e.g., `product_requests`)
- **Columns**: snake_case
- **Foreign Keys**: `{table}_id` format
- **Indexes**: Named with descriptive prefixes
- **UUIDs**: Used for PHI-related tables and audit logs

## Frontend Formatting (Prettier)
- Print Width: 80
- Single Quotes: true
- Tab Width: 2
- Arrow Parens: avoid
- Trailing Comma: none

## Security Conventions
- **PHI Data**: Always reference via FHIR IDs, never store directly
- **Audit Logging**: All PHI access tracked in phi_audit_logs
- **CSRF Protection**: Enabled for all state-changing requests
- **Input Validation**: Use Form Requests for validation
- **Authorization**: Policy-based authorization with Laravel Policies

## Git Conventions
- **Branching**: Feature branches from azure-dev
- **Commits**: Descriptive messages following conventional commits
- **PR Target**: master branch for production changes