# Suggested Commands for Development

## Setup Commands
```bash
# Install dependencies
composer install
npm install

# Generate application key
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed

# Clear various caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Development Server
```bash
# Start Laravel server
php artisan serve

# Start Vite dev server (separate terminal)
npm run dev

# Build for production
npm run prod
```

## Testing Commands
```bash
# Run all tests (PHP + JS)
npm run test:all

# PHP tests only
php artisan test
php artisan test --coverage
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# JavaScript tests only
npm test
npm run test:watch
npm run test:coverage

# Specific test workflows
php artisan test:order-workflow
php artisan test:mac-validation-api
```

## Code Quality Commands
```bash
# Linting and type checking
npm run lint
npm run lint:fix
npm run type-check

# PHP linting (Laravel Pint)
./vendor/bin/pint
```

## Artisan Commands (Custom)
```bash
# Sync Docuseal templates
php artisan docuseal:sync

# Test various services
php artisan test:azure-ai
php artisan test:docuseal-api
php artisan test:fhir-connection

# Debug commands
php artisan debug:product-sizes
php artisan debug:acez-ivr-mapping

# User/Permission management
php artisan user:permissions {email}
php artisan check:user-roles
php artisan check:user-permissions

# Data management
php artisan clear:payers-cache
php artisan cleanup:mock-orders
```

## Database Commands
```bash
# Create new migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate
php artisan migrate:fresh --seed
php artisan migrate:rollback

# Create models/controllers/services
php artisan make:model ModelName -m
php artisan make:controller ControllerName
php artisan make:service ServiceName
```

## Git Commands (Linux)
```bash
git status
git add .
git commit -m "feat: description"
git push origin azure-dev
git pull origin master
git checkout -b feature/branch-name
```

## System Commands
```bash
# File operations
ls -la
cd directory
mkdir new-directory
rm -rf directory

# Search commands
grep -r "pattern" .
find . -name "*.php"

# Process management
ps aux | grep php
kill -9 PID

# Log viewing
tail -f storage/logs/laravel.log
```

## Debugging Commands
```bash
# View routes
php artisan route:list

# Tinker (REPL)
php artisan tinker

# View config
php artisan config:show

# Queue workers (if using queues)
php artisan queue:work
php artisan queue:listen
```