# Environment Setup Documentation

**Version:** 1.0  
**Last Updated:** January 2025  
**Status:** Production Guide

---

## 🚀 Environment Overview

The MSC Wound Care Portal supports multiple deployment environments with specific configurations for development, staging, and production scenarios.

## 🏗️ Environment Types

### 1. Local Development
**Purpose**: Developer workstations and local testing

### 2. Development Environment
**Purpose**: Shared development environment for integration testing

### 3. Staging Environment
**Purpose**: Production-like testing environment

### 4. Production Environment
**Purpose**: Live system serving end users

## 📋 Prerequisites

### System Requirements
```yaml
Backend Requirements:
  - PHP 8.2 or higher
  - Composer 2.x
  - MySQL 8.0+
  - Redis 6.x+
  - Node.js 18.x+
  - NPM/Yarn latest

Frontend Requirements:
  - Node.js 18.x or higher
  - NPM 9.x+ or Yarn 1.22+
  - Modern browser support
  - TypeScript 5.0+

Development Tools:
  - Git 2.x+
  - Docker & Docker Compose
  - VS Code (recommended)
  - Azure CLI (for cloud deployments)
```

### Azure Services Required
```yaml
Core Services:
  - Azure Database for MySQL
  - Azure Cache for Redis
  - Azure Blob Storage
  - Azure Health Data Services (FHIR)
  - Azure AI Services
  - Azure Document Intelligence

Optional Services:
  - Azure Application Insights
  - Azure Key Vault
  - Azure CDN
  - Azure DNS
```

## 🔧 Environment Configuration

### Environment Variables
Based on your `.env` file structure:

```bash
# Application Configuration
APP_DEBUG=false  # true for dev environments
APP_ENV=production  # local, development, staging, production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_NAME="MSC Platform"
APP_URL=https://your-domain.com

# Azure FHIR Configuration
AZURE_FHIR_CLIENT_ID=your-client-id
AZURE_FHIR_CLIENT_SECRET=your-client-secret
AZURE_FHIR_TENANT_ID=your-tenant-id
AZURE_FHIR_BASE_URL=https://your-fhir-endpoint.fhir.azurehealthcareapis.com
AZURE_FHIR_SCOPE=https://your-fhir-endpoint.fhir.azurehealthcareapis.com/.default
AZURE_FHIR_AUTHORITY=https://login.microsoftonline.com/your-tenant-id

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=your-mysql-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-username
DB_PASSWORD=your-password
DB_SSL_REQUIRED=ON  # OFF for local development

# Cache & Queue Configuration
CACHE_DRIVER=redis  # file for local development
QUEUE_CONNECTION=redis  # sync for local development
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6380
REDIS_DATABASE=0

# Azure Storage
AZURE_STORAGE_BLOB_URL=https://your-storage-account.blob.core.windows.net/container-name

# External API Configuration
AVAILITY_API_BASE_URL=https://api.availity.com/availity/development-partner/v1
AVAILITY_CLIENT_ID=your-client-id
AVAILITY_CLIENT_SECRET=your-client-secret

CMS_API_BASE_URL=https://api.coverage.cms.gov/v1

DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_API_KEY=your-api-key
DOCUSEAL_WEBHOOK_SECRET=your-webhook-secret

# Azure AI Services
AZURE_OPENAI_ENDPOINT=https://your-ai-services.cognitiveservices.azure.com
AZURE_OPENAI_API_KEY=your-api-key
AZURE_OPENAI_DEPLOYMENT_NAME=gpt-4o

AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT=https://your-di-resource.cognitiveservices.azure.com/
AZURE_DOCUMENT_INTELLIGENCE_KEY=your-di-key
```

## 🐳 Docker Development Setup

### Docker Compose Configuration
```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: msc_platform
      MYSQL_USER: msc_user
      MYSQL_PASSWORD: password
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:6-alpine
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data

  frontend:
    image: node:18-alpine
    working_dir: /app
    ports:
      - "5173:5173"
    volumes:
      - .:/app
    command: npm run dev

volumes:
  mysql_data:
  redis_data:
```

### Dockerfile
```dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader
RUN npm install
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage
RUN chown -R www-data:www-data /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

## 🚀 Deployment Steps

### 1. Local Development Setup
```bash
# Clone repository
git clone https://github.com/your-org/msc-woundcare-portal.git
cd msc-woundcare-portal

# Install backend dependencies
composer install

# Install frontend dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Build frontend assets
npm run build

# Start development servers
php artisan serve  # Backend (http://localhost:8000)
npm run dev       # Frontend (http://localhost:5173)
```

### 2. Docker Development Setup
```bash
# Start all services
docker-compose up -d

# Run migrations in container
docker-compose exec app php artisan migrate

# View logs
docker-compose logs -f app
```

### 3. Azure Deployment

#### Prerequisites
```bash
# Install Azure CLI
curl -sL https://aka.ms/InstallAzureCLIDeb | sudo bash

# Login to Azure
az login

# Set subscription
az account set --subscription "your-subscription-id"
```

#### Deploy to Azure App Service
```bash
# Create resource group
az group create --name msc-platform-rg --location eastus2

# Create App Service plan
az appservice plan create \
  --name msc-platform-plan \
  --resource-group msc-platform-rg \
  --sku P1V2 \
  --is-linux

# Create web app
az webapp create \
  --resource-group msc-platform-rg \
  --plan msc-platform-plan \
  --name msc-platform-app \
  --runtime "PHP|8.2"

# Configure app settings
az webapp config appsettings set \
  --resource-group msc-platform-rg \
  --name msc-platform-app \
  --settings @appsettings.json

# Deploy code
az webapp deployment source config-zip \
  --resource-group msc-platform-rg \
  --name msc-platform-app \
  --src deployment.zip
```

## 🔒 Security Configuration

### SSL/TLS Setup
```yaml
Production Requirements:
  - TLS 1.2 minimum
  - Valid SSL certificate
  - HSTS enabled
  - Secure cookie flags
  - CSRF protection enabled

Azure App Service:
  - Managed certificates available
  - Custom domain support
  - Automatic renewal
  - SNI SSL support
```

### Environment Security
```bash
# Secure environment variables
az webapp config appsettings set \
  --resource-group msc-platform-rg \
  --name msc-platform-app \
  --settings \
    "APP_KEY=@Microsoft.KeyVault(SecretUri=https://vault.vault.azure.net/secrets/app-key/)" \
    "DB_PASSWORD=@Microsoft.KeyVault(SecretUri=https://vault.vault.azure.net/secrets/db-password/)"

# Key Vault integration
az keyvault create \
  --name msc-platform-vault \
  --resource-group msc-platform-rg \
  --location eastus2
```

## 📊 Monitoring Setup

### Application Insights
```bash
# Create Application Insights
az monitor app-insights component create \
  --app msc-platform-insights \
  --location eastus2 \
  --resource-group msc-platform-rg \
  --application-type web

# Get instrumentation key
az monitor app-insights component show \
  --app msc-platform-insights \
  --resource-group msc-platform-rg \
  --query instrumentationKey
```

### Health Checks
```php
// routes/web.php
Route::get('/health', function () {
    $checks = [
        'database' => DB::connection()->getPdo() ? 'ok' : 'fail',
        'redis' => Redis::ping() ? 'ok' : 'fail',
        'storage' => Storage::disk('azure')->exists('health-check.txt') ? 'ok' : 'fail'
    ];
    
    $status = in_array('fail', $checks) ? 500 : 200;
    
    return response()->json([
        'status' => $status === 200 ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toISOString()
    ], $status);
});
```

## 🔧 Troubleshooting

### Common Issues

#### Database Connection
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### Redis Connection
```bash
# Test Redis connection
php artisan tinker
>>> Redis::ping();
```

#### Azure Services
```bash
# Test FHIR connection
curl -H "Authorization: Bearer $TOKEN" \
  "$AZURE_FHIR_BASE_URL/metadata"

# Test Blob Storage
az storage blob list \
  --account-name your-storage-account \
  --container-name your-container
```

### Log Locations
```yaml
Local Development:
  - storage/logs/laravel.log
  - Browser console (frontend)
  
Azure App Service:
  - Log Stream (portal)
  - /home/LogFiles/
  - Application Insights

Docker:
  - docker-compose logs app
  - docker logs container-name
```

## 📈 Performance Optimization

### Production Optimizations
```bash
# Optimize autoloader
composer install --no-dev --optimize-autoloader

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize images
npm run build:production
```

### Database Optimizations
```sql
-- Add indexes for performance
CREATE INDEX idx_product_requests_status ON product_requests(status);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_facilities_organization ON facilities(organization_id);
```

---

**Related Documentation:**
- [Azure Infrastructure](./AZURE_INFRASTRUCTURE.md)
- [CI/CD Pipeline](./CICD_PIPELINE.md)
- [Monitoring Setup](./MONITORING.md)
