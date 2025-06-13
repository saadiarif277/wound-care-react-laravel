# MSC Wound Portal - Codebase Guide for Claude

## Project Overview
MSC Wound Portal is a HIPAA-compliant healthcare platform for wound care management built with Laravel 11, React 18, and TypeScript. It features strict data separation between operational data (Supabase) and PHI data (Azure FHIR).

## Important Project Rules
- **Always use roles not permissions**, and never use mock data for the remainder of the project
- Organizations can have multiple facilities and providers
- Facilities have only one office manager
- Providers can have relationships with multiple facilities and organizations
- Always fully finish a page or feature before moving on, including all buttons, functions and other feature functionality
- Always use UUID when appropriate (as used in other tables and migrations)
- Patients can only request one product with multiple sizes, not multiple products
- Patient info should be shown as: first two letters of first name, first two letters of last name and random # sequence known as PATIENT_DISPLAY_ID
- Only use documentation from @docs/newest/
- Do what has been asked; nothing more, nothing less
- NEVER create files unless absolutely necessary
- ALWAYS prefer editing existing files to creating new ones
- NEVER proactively create documentation files (*.md) or README files unless explicitly requested

## Build Commands & Setup

### Prerequisites
- PHP 8.2+
- Node.js 22+ (LTS required)
- Composer
- Supabase account
- Azure Health Data Services (for PHI)

### Development Commands
```bash
# Install dependencies
composer install
npm install

# Development servers
php artisan serve
npm run dev

# Build for production
npm run prod  # or vite build

# Database
php artisan migrate
php artisan db:seed

# Testing
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Code quality (if available)
npm run lint
npm run typecheck
```

## Architecture

### Tech Stack
- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: React 18, TypeScript, Inertia.js
- **Styling**: Tailwind CSS with glassmorphic design system
- **State**: Zustand, React Context API
- **Database**: Supabase PostgreSQL (non-PHI), Azure FHIR (PHI)
- **Auth**: Laravel Sanctum
- **File Storage**: AWS S3

### Data Architecture (HIPAA Compliance)
**Supabase (Non-PHI)**:
- User accounts and authentication
- Organizations, facilities, providers (business info only)
- Product catalogs and pricing
- Commission structures and calculations
- Order metadata (without clinical details)

**Azure FHIR (PHI)**:
- Patient demographics
- Clinical documentation
- Insurance information
- Medical images and assessments

### Key Healthcare Packages
- `dcarbone/php-fhir`: FHIR resource handling
- `dcarbone/php-fhir-generated`: FHIR R4 models
- `docusealco/docuseal-php`: Document signing integration
- `axlon/laravel-postal-code-validation`: Address validation

## Theme System Architecture

### Glass Morphism Design System
The platform uses a modern glassmorphic design with full dark/light theme support:

```typescript
// Theme structure in /resources/js/theme/glass-theme.ts
export const themes = {
  dark: {
    glass: { /* Glass effects for dark mode */ },
    text: { /* Text color variants */ },
    button: { /* Button styles */ },
    // ... other component styles
  },
  light: {
    // Light theme equivalents
  }
}
```

### Theme Context Pattern
```typescript
// Usage in components
import { useTheme } from '@/contexts/ThemeContext';

// With fallback for components outside ThemeProvider
let theme: 'dark' | 'light' = 'dark';
let t = themes.dark;

try {
  const themeContext = useTheme();
  theme = themeContext.theme;
  t = themes[theme];
} catch (e) {
  // Fallback to dark theme
}
```

### Key UI Components
- **GlassCard**: Base glassmorphic container with variants
- **StatCard**: Metric display widgets
- **Button**: Medical-grade buttons with loading states
- **Input**: Theme-aware form inputs with error states
- **ThemeToggle**: Light/dark mode switcher
- **Heading**: Typography component with gradient support

## Project Structure
```
msc-wound-portal/
├── app/                    # Laravel application
│   ├── Http/Controllers/   # API & web controllers
│   ├── Models/            # Eloquent models
│   └── Services/          # Business logic
├── resources/
│   ├── js/                # React frontend
│   │   ├── Components/    # Reusable components
│   │   ├── Pages/        # Inertia page components
│   │   ├── Layouts/      # Layout components
│   │   ├── contexts/     # React contexts
│   │   └── theme/        # Theme configuration
│   └── css/              # Global styles
├── database/
│   └── migrations/       # Database migrations
└── routes/              # API and web routes
```

## Common Development Patterns

### Creating Components
1. Check existing components for patterns
2. Use theme-aware styling with fallbacks
3. Follow established naming conventions
4. Implement proper TypeScript interfaces

### Working with Routes
- Use Laravel's route() helper: `route('product-requests.show', { id })`
- Common routes:
  - `product-requests.index/create/show`
  - `providers`
  - `facilities.index`
  - `admin.order-center`

### Database Patterns
- Always use UUIDs for primary keys
- Follow existing migration patterns
- Maintain PHI/non-PHI data separation

### Form Handling
- Use Inertia's useForm hook
- Implement proper validation
- Show loading states during submission
- Handle errors gracefully

## Security Considerations
- Never store PHI in Supabase
- Use FHIR resource IDs for PHI references
- Implement proper RBAC checks
- Sanitize all user inputs
- Follow HIPAA compliance guidelines

## Testing Approach
1. Check README or package.json for test commands
2. Never assume specific test frameworks
3. Ask user for lint/typecheck commands if needed
4. Suggest adding commands to CLAUDE.md for future reference

## MSC Brand Colors
- MSC Blue: #1925c3
- MSC Red: #c71719
- Applied via gradients: `from-[#1925c3] to-[#c71719]`

## Common Issues & Solutions

### Theme Context Errors
If "useTheme must be used within ThemeProvider":
- Wrap component in MainLayout
- Or use try/catch pattern with fallback

### Route Errors
If "route 'X' is not in the route list":
- Check routes/web.php for correct route names
- Common replacements:
  - 'profile.edit' → 'providers'
  - 'documents' → 'product-requests.index'

### CSS Build Errors
- Remove invalid @apply directives
- Check for undefined Tailwind classes
- Ensure proper theme variable usage

## Performance Optimization
- Use React.memo for expensive components
- Implement virtualization for long lists
- Lazy load heavy components
- Optimize images with proper sizing
- Use intersection observer for animations

This guide should be referenced when working on the MSC Wound Portal to ensure consistency and compliance with established patterns.