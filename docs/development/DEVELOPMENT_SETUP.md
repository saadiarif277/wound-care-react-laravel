# Development Setup Guide

## 🛠️ Development Environment Setup

This guide will help you set up a local development environment for the MSC Wound Care Portal.

## 📋 Prerequisites

### Required Software
- **PHP 8.2+** with extensions:
  - BCMath
  - Ctype
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PDO
  - Tokenizer
  - XML
  - GD
  - Imagick (recommended)
- **Composer 2.0+**
- **Node.js 18.0+**
- **npm 8.0+** or **Yarn 1.22+**
- **MySQL 8.0+** or **PostgreSQL 13+**
- **Redis 6.0+**
- **Git**

### Development Tools (Recommended)
- **Docker Desktop** (for containerized development)
- **VS Code** with extensions:
  - PHP Intelephense
  - Laravel Extension Pack
  - Vetur (for Vue.js)
  - ESLint
  - Prettier
- **TablePlus** or similar database client
- **Postman** or **Insomnia** for API testing

## 🚀 Quick Start

### 1. Clone Repository
```bash
git clone https://github.com/msc/wound-care-portal.git
cd wound-care-portal
```

### 2. Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure environment variables
# Edit .env file with your database and service credentials
```

### 3. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Build frontend assets
npm run dev
```

### 4. Database Setup
```bash
# Run database migrations
php artisan migrate

# Seed database with sample data
php artisan db:seed

# Create symbolic link for storage
php artisan storage:link
```

### 5. Start Development Server
```bash
# Start Laravel development server
php artisan serve

# In another terminal, start asset compilation
npm run watch

# The application will be available at http://localhost:8000
```

## 🐳 Docker Development Setup

### Using Docker Compose
```bash
# Clone repository
git clone https://github.com/msc/wound-care-portal.git
cd wound-care-portal

# Copy environment file
cp .env.docker .env

# Start development environment
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Run migrations
docker-compose exec app php artisan migrate --seed

# Build assets
docker-compose exec app npm run dev
```

### Docker Services
```yaml
# docker-compose.yml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - database
      - redis
      
  database:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: wound_care_portal
    ports:
      - "3306:3306"
      
  redis:
    image: redis:6-alpine
    ports:
      - "6379:6379"
      
  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"
      - "8025:8025"
```

## ⚙️ Configuration

### Environment Variables
```bash
# Application
APP_NAME="MSC Wound Care Portal"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wound_care_portal
DB_USERNAME=root
DB_PASSWORD=

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (for local development)
MAIL_MAILER=log
# For testing with MailHog
# MAIL_MAILER=smtp
# MAIL_HOST=localhost
# MAIL_PORT=1025

# Azure Services (for development)
AZURE_STORAGE_ACCOUNT=your_dev_account
AZURE_STORAGE_KEY=your_dev_key
AZURE_STORAGE_CONTAINER=dev-documents

# FHIR Service
FHIR_BASE_URL=https://your-dev-fhir-server.com
FHIR_CLIENT_ID=your_dev_client_id
FHIR_CLIENT_SECRET=your_dev_secret

# DocuSeal Integration
DOCUSEAL_API_URL=https://api.docuseal.co
DOCUSEAL_API_KEY=your_dev_api_key

# External APIs
AVAILITY_API_URL=https://api.availity.com
AVAILITY_CLIENT_ID=your_dev_client_id
AVAILITY_CLIENT_SECRET=your_dev_secret
```

### Database Configuration
```php
// config/database.php - Development settings
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'wound_care_portal'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false, // More lenient for development
    'engine' => null,
],
```

## 🏗️ Project Structure

### Backend Structure
```
app/
├── Console/           # Artisan commands
├── Http/
│   ├── Controllers/   # Request handlers
│   ├── Middleware/    # HTTP middleware
│   ├── Requests/      # Form request validation
│   └── Resources/     # API response transformers
├── Models/            # Eloquent models
├── Services/          # Business logic services
├── Jobs/              # Queue jobs
├── Events/            # Event classes
├── Listeners/         # Event listeners
├── Mail/              # Mail classes
├── Notifications/     # Notification classes
├── Policies/          # Authorization policies
├── Providers/         # Service providers
└── Rules/             # Custom validation rules
```

### Frontend Structure
```
resources/
├── js/
│   ├── Components/    # Vue.js components
│   ├── Pages/         # Inertia.js pages
│   ├── Stores/        # Pinia stores
│   ├── Utils/         # Utility functions
│   └── app.js         # Main application file
├── css/
│   ├── app.css        # Main stylesheet
│   └── components/    # Component styles
└── views/             # Blade templates
```

### Database Structure
```
database/
├── factories/         # Model factories
├── migrations/        # Database migrations
├── seeders/          # Database seeders
└── schema/           # Database schema documentation
```

## 🔧 Development Tools

### Laravel Artisan Commands
```bash
# Generate components
php artisan make:controller PatientController
php artisan make:model Patient -mfsr  # Model with migration, factory, seeder, resource
php artisan make:service PatientService
php artisan make:job ProcessOrderJob
php artisan make:request CreatePatientRequest

# Database operations
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh --seed
php artisan db:seed --class=UserSeeder

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue operations
php artisan queue:work
php artisan queue:restart
php artisan queue:failed
```

### Frontend Development
```bash
# Start development server with hot reload
npm run hot

# Watch for changes and recompile
npm run watch

# Build for production
npm run production

# Run ESLint
npm run lint

# Run tests
npm run test
```

### Code Quality Tools
```bash
# PHP CS Fixer
./vendor/bin/php-cs-fixer fix

# PHP Stan (Static Analysis)
./vendor/bin/phpstan analyse

# Run PHPUnit tests
php artisan test

# Run specific test
php artisan test --filter=PatientServiceTest

# Generate test coverage
php artisan test --coverage
```

## 🧪 Testing Setup

### PHPUnit Configuration
```xml
<!-- phpunit.xml -->
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

### Test Database Setup
```bash
# Create test database
php artisan config:cache
php artisan migrate --env=testing

# Run tests with database
php artisan test --env=testing
```

### Writing Tests
```php
// tests/Feature/PatientTest.php
class PatientTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_create_patient()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post('/api/v1/patients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1980-01-15',
            ]);
            
        $response->assertStatus(201);
        $this->assertDatabaseHas('patients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }
}
```

## 🔍 Debugging

### Debug Tools
```bash
# Enable query logging
DB::enableQueryLog();
dd(DB::getQueryLog());

# Debug with Laravel Telescope
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

# Debug with Laravel Debugbar
composer require barryvdh/laravel-debugbar --dev
```

### Logging Configuration
```php
// config/logging.php - Development channels
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'slack'],
    ],
    
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Common Debug Techniques
```php
// In controllers or services
Log::debug('Processing order', ['order_id' => $order->id]);

// Dump and die
dd($variable);

// Dump without dying
dump($variable);

// Ray debugging (if installed)
ray($variable);
```

## 🚀 Performance Optimization

### Development Performance
```bash
# Optimize for development
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear optimization (when making config changes)
php artisan optimize:clear
```

### Database Query Optimization
```php
// Enable query logging
DB::listen(function ($query) {
    Log::info($query->sql, $query->bindings);
});

// Use Laravel Debugbar to monitor queries
# Check for N+1 queries in the debugbar
```

## 📝 Code Standards

### PHP Standards
- Follow PSR-12 coding standards
- Use type hints and return types
- Write meaningful method and variable names
- Add PHPDoc comments for complex methods

```php
/**
 * Create a new patient record with validation
 *
 * @param array $patientData
 * @return Patient
 * @throws ValidationException
 */
public function createPatient(array $patientData): Patient
{
    // Implementation
}
```

### JavaScript/Vue Standards
- Use ES6+ syntax
- Follow Vue.js style guide
- Use meaningful component names
- Add JSDoc comments for complex functions

```javascript
/**
 * Format currency value for display
 * @param {number} amount - The amount to format
 * @param {string} currency - Currency code (default: USD)
 * @returns {string} Formatted currency string
 */
export function formatCurrency(amount, currency = 'USD') {
    // Implementation
}
```

## 🔄 Git Workflow

### Branch Strategy
```bash
# Main branches
main              # Production-ready code
develop          # Integration branch for features

# Feature branches
feature/patient-management
feature/order-processing
bugfix/login-issue
hotfix/critical-security-fix
```

### Commit Messages
```bash
# Format: type(scope): description
feat(patients): add patient search functionality
fix(orders): resolve order calculation bug
docs(api): update API documentation
refactor(services): simplify order processing logic
test(patients): add unit tests for patient service
```

### Pull Request Process
1. Create feature branch from `develop`
2. Implement changes with tests
3. Run code quality checks
4. Create pull request with description
5. Code review and approval
6. Merge to `develop`

## 📚 Additional Resources

### Documentation
- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)

### Development Tools
- [Laravel Telescope](https://laravel.com/docs/telescope)
- [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar)
- [PHP CS Fixer](https://cs.symfony.com/)
- [PHPStan](https://phpstan.org/)

### Learning Resources
- [Laracasts](https://laracasts.com/)
- [Vue Mastery](https://www.vuemastery.com/)
- [Laravel Daily](https://laraveldaily.com/)

## 🆘 Getting Help

### Internal Support
- **Technical Lead**: tech-lead@mscwoundcare.com
- **Development Team**: dev-team@mscwoundcare.com
- **DevOps Support**: devops@mscwoundcare.com

### Community Resources
- [Laravel Community](https://laravel.io/)
- [Vue.js Discord](https://discord.com/invite/vue)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/laravel)
