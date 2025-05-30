# MSC Wound Portal

The MSC Wound Portal is a healthcare platform for wound care management with strict HIPAA compliance through data separation between operational data (Supabase) and PHI data (Azure Health Data Services).

## Quick Start

### Prerequisites
- PHP 8.1+
- Composer
- Node.js 22 (LTS recommended for production)
- Supabase account
- Azure Health Data Services (for PHI)

### Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd wound-care-stage
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

   **Note**: This project requires Node.js 22 (LTS). See [`docs/NODE_VERSION_STRATEGY.md`](./docs/NODE_VERSION_STRATEGY.md) for detailed version requirements and setup instructions.

3. **Configure Supabase for Non-PHI Data**
   
   **For detailed setup instructions, see [`SUPABASE_SETUP.md`](./SUPABASE_SETUP.md)**

   Quick setup using our automated script:
   ```bash
   # On Windows (PowerShell)
   .\setup-supabase.ps1
   
   # On Linux/Mac
   ./setup-supabase.sh
   ```

4. **Environment Configuration**
   - Copy the environment template from `SUPABASE_SETUP.md`
   - Create your `.env` file with Supabase and Azure FHIR credentials
   - Generate app key: `php artisan key:generate`

5. **Database Setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Test Your Setup**
   ```bash
   # Test Supabase connection
   php test-supabase-connection.php
   
   # Run Laravel tests
   php artisan test
   ```

7. **Start Development Server**
   ```bash
   php artisan serve
   npm run dev
   ```

## Architecture

### Data Separation (HIPAA Compliance)
- **Supabase PostgreSQL**: Non-PHI operational data
  - User accounts and authentication
  - Organizations, facilities, providers (business info only)
  - Product catalogs and pricing
  - Commission structures and calculations
  - Order metadata (without clinical details)

- **Azure Health Data Services**: PHI data only
  - Patient demographics
  - Clinical documentation
  - Insurance information
  - Medical images and assessments

### Technology Stack
- **Backend**: Laravel 10, PHP 8.1+
- **Frontend**: React 18, TypeScript, Inertia.js
- **Database**: Supabase PostgreSQL (non-PHI), Azure FHIR (PHI)
- **Authentication**: Laravel Sanctum
- **UI**: Tailwind CSS, Shadcn UI
- **State Management**: Zustand
- **File Storage**: AWS S3

## Key Features

- 🏥 **Wound Care Management**: Comprehensive wound assessment and tracking
- 💰 **Commission Management**: Automated commission calculation and tracking
- 📊 **Reporting & Analytics**: Sales reports and commission analytics  
- 🔒 **HIPAA Compliance**: Strict PHI/non-PHI data separation
- 👥 **Multi-Role Access**: Sales reps, facilities, administrators
- 📱 **Responsive Design**: Works on desktop and mobile devices

## Development

### File Structure
```
wound-care-stage/
├── app/                    # Laravel application code
├── resources/js/           # React frontend code
├── database/migrations/    # Database migrations
├── SUPABASE_SETUP.md      # Detailed Supabase setup guide
├── setup-supabase.ps1     # Windows setup script
├── setup-supabase.sh      # Linux/Mac setup script
└── test-supabase-connection.php  # Connection test utility
```

### Available Scripts

- `composer install` - Install PHP dependencies
- `npm install` - Install Node.js dependencies
- `php artisan migrate` - Run database migrations
- `php artisan serve` - Start Laravel development server
- `npm run dev` - Start Vite development server
- `php artisan test` - Run PHPUnit tests
- `npm run build` - Build for production

### Testing

- **Unit Tests**: `php artisan test --testsuite=Unit`
- **Feature Tests**: `php artisan test --testsuite=Feature`
- **Database Tests**: `php artisan test --filter=Database`
- **Supabase Connection**: `php test-supabase-connection.php`

## Security & Compliance

This application follows strict HIPAA compliance requirements:

- **Data Classification**: All data is classified as PHI or non-PHI
- **Encryption**: Data encrypted at rest and in transit
- **Access Control**: Role-based access with audit logging
- **Data Minimization**: Only necessary data is collected and stored
- **Secure References**: PHI referenced via secure FHIR resource IDs

## Contributing

1. Follow the existing code style and patterns
2. Ensure all tests pass before submitting PR
3. Verify HIPAA compliance for any data-related changes
4. Update documentation for significant changes

## Support

For setup issues:
1. Check `SUPABASE_SETUP.md` for detailed instructions
2. Run `php test-supabase-connection.php` to diagnose connection issues
3. Verify environment configuration matches the template

## License

This project is proprietary software. See LICENSE file for details.
