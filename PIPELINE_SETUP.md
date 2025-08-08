# Azure DevOps Pipeline Setup Guide for UAT MSC Portal

## Overview

This pipeline builds and deploys your Laravel + React TypeScript application to Azure App Service UAT environment.

## Prerequisites

### 1. Azure Resources Required

- **Azure App Service** (Linux with PHP 8.2 runtime)
- **Azure DevOps Project**
- **Service Principal** with Contributor access to your resource group

### 2. Azure DevOps Setup

#### Create Service Connection

1. Go to Azure DevOps Project Settings → Service connections
2. Create a new Azure Resource Manager service connection
3. Name it: `Azure-Service-Connection`
4. Select your subscription and resource group
5. Grant access to all pipelines

#### Environment Setup

1. Go to Pipelines → Environments
2. Create new environment named: `UAT`
3. Add approval checks if needed

### 3. Repository Configuration

#### Required Files

- `azure-pipelines.yml` (already created)
- `.env.example` (should exist in your Laravel project)
- `package.json` with required scripts
- `composer.json` with PHP dependencies

#### Update Pipeline Variables

Edit `azure-pipelines.yml` and replace:

```yaml
# Line 150: Replace with your actual service connection name
azureSubscription: 'YOUR_ACTUAL_SERVICE_CONNECTION_NAME'

# Line 153: Replace with your actual App Service name
appName: 'YOUR_UAT_APP_SERVICE_NAME'
```

### 4. Azure App Service Configuration

#### App Settings Required

Add these application settings in your Azure App Service:

```bash
# Laravel Configuration
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_KEY_HERE

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=your-database-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-database-user
DB_PASSWORD=your-database-password

# Add all other environment variables from your .env file
```

#### Startup Command

Set this as your startup command in App Service:

```bash
php /home/site/wwwroot/artisan migrate --force && php-fpm
```

## Pipeline Workflow

### Build Stage

1. **Setup Environment**: Installs Node.js 18.x and PHP 8.2
2. **Cache Dependencies**: Caches npm and composer packages
3. **Install Dependencies**: Runs `npm ci` and `composer install`
4. **Code Quality**: TypeScript checking and ESLint
5. **Build Frontend**: Compiles React app with Vite
6. **Run Tests**: Executes Jest tests with coverage
7. **Laravel Optimization**: Caches config, routes, and views
8. **Package Creation**: Creates deployment ZIP file
9. **Artifact Publishing**: Uploads build artifacts

### Deploy Stage

1. **Download Artifacts**: Downloads build package
2. **Deploy to App Service**: Deploys to Azure App Service
3. **Post-deployment**: Runs migrations and optimizations

## Commands to Execute

### 1. Commit and Push Pipeline

```bash
git add azure-pipelines.yml
git commit -m "Add Azure DevOps pipeline for UAT deployment"
git push origin UAT
```

### 2. Create Pipeline in Azure DevOps

1. Go to Pipelines → Create Pipeline
2. Select your repository source (GitHub/Azure Repos)
3. Choose "Existing Azure Pipelines YAML file"
4. Select `/azure-pipelines.yml`
5. Save and run

### 3. Configure Branch Policies (Optional)

```bash
# Protect UAT branch
git branch --set-upstream-to=origin/UAT UAT
```

## Troubleshooting

### Common Issues

#### 1. PHP Version Mismatch

If PHP version issues occur, update the setup script:

```yaml
- script: |
    sudo apt-get update
    sudo apt-get install -y php8.2-cli php8.2-mbstring php8.2-xml
    php -v
```

#### 2. Node Dependencies Fail

Ensure your `package.json` has all required scripts:

```json
{
  "scripts": {
    "prod": "vite build",
    "test:coverage": "jest --coverage",
    "type-check": "tsc --noEmit",
    "lint": "eslint resources/js --ext .ts,.tsx"
  }
}
```

#### 3. Laravel Artisan Commands Fail

Make sure your `.env` file has correct database connections and all required environment variables.

#### 4. Deployment Fails

Check that your App Service:

- Has correct runtime stack (PHP 8.2)
- Has all required application settings
- Has sufficient storage space

## Security Best Practices

### 1. Environment Variables

- Never commit `.env` files
- Use Azure Key Vault for sensitive data
- Set environment variables in App Service settings

### 2. Service Principal Permissions

- Grant minimum required permissions (Contributor on resource group)
- Use separate service principals for different environments

### 3. Branch Protection

- Require pull request reviews
- Enable status checks
- Restrict force pushes

## Monitoring and Logging

### Application Insights

Add to your App Service for monitoring:

```bash
APPINSIGHTS_INSTRUMENTATIONKEY=your-key-here
APPLICATIONINSIGHTS_CONNECTION_STRING=your-connection-string
```

### Log Stream

Monitor deployments in real-time:

1. Azure Portal → Your App Service → Log stream
2. Azure DevOps → Pipelines → Your pipeline run → Logs

## Next Steps

1. **Test the Pipeline**: Push a change to UAT branch
2. **Monitor Deployment**: Check Azure DevOps pipeline execution
3. **Verify Application**: Test your UAT environment
4. **Setup Production**: Create similar pipeline for production branch
5. **Add Quality Gates**: Include code quality checks and security scans

## Support

For pipeline issues:

- Check Azure DevOps pipeline logs
- Verify service connection permissions
- Ensure all environment variables are set
- Contact your Azure administrator for resource access issues
