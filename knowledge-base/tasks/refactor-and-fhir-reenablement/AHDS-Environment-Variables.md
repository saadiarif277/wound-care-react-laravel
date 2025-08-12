# Azure Health Data Services Environment Variables

This document outlines the environment variables required for migrating from Azure FHIR Server to Azure Health Data Services (AHDS).

## Required Environment Variables

### Azure Health Data Services Configuration

```bash
# Enable Azure Health Data Services (set to true to use AHDS instead of legacy Azure FHIR Server)
AZURE_HEALTH_DATA_SERVICES_ENABLED=false

# Azure Health Data Services Workspace URL
# Format: https://{workspace-name}.fhir.azurehealthcareapis.com
AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL=https://your-workspace-name.fhir.azurehealthcareapis.com

# Azure AD Authentication for AHDS
AZURE_HEALTH_DATA_SERVICES_TENANT_ID=your-tenant-id-here
AZURE_HEALTH_DATA_SERVICES_CLIENT_ID=your-client-id-here
AZURE_HEALTH_DATA_SERVICES_CLIENT_SECRET=your-client-secret-here

# OAuth2 Configuration (usually don't need to change these)
AZURE_HEALTH_DATA_SERVICES_SCOPE=https://azurehealthcareapis.com/.default
AZURE_HEALTH_DATA_SERVICES_OAUTH_ENDPOINT=https://login.microsoftonline.com
```

### Legacy Azure FHIR Server Configuration (Keep for Migration Period)

```bash
# Legacy Azure FHIR Server Configuration (will be deprecated)
# Keep these for fallback during migration
AZURE_FHIR_URL=https://your-legacy-fhir-server.azurehealthcareapis.com
AZURE_FHIR_TENANT_ID=your-tenant-id-here
AZURE_FHIR_CLIENT_ID=your-client-id-here
AZURE_FHIR_CLIENT_SECRET=your-client-secret-here
```

### Feature Flags for FHIR Operations

```bash
# Feature Flags for FHIR Operations
FHIR_PATIENT_HANDLER_ENABLED=false
FHIR_PROVIDER_HANDLER_ENABLED=false
FHIR_INSURANCE_HANDLER_ENABLED=false
FHIR_CLINICAL_HANDLER_ENABLED=false
FHIR_ORDER_HANDLER_ENABLED=false
```

### FHIR Configuration

```bash
# FHIR Caching and Audit
FHIR_CACHE_ENABLED=true
FHIR_AUDIT_ENABLED=true
FHIR_STRICT_VALIDATION=false
```

## Setup Instructions

1. **Create Azure Health Data Services Workspace**:
   - Go to Azure Portal
   - Create a new "Azure Health Data Services" resource
   - Create a FHIR service within the workspace
   - Note the workspace URL

2. **Configure Azure AD App Registration**:
   - Create or use existing App Registration
   - Grant permissions to Azure Healthcare APIs
   - Generate client secret
   - Note tenant ID, client ID, and client secret

3. **Update Environment Variables**:
   - Add the AHDS variables to your `.env` file
   - Initially keep `AZURE_HEALTH_DATA_SERVICES_ENABLED=false`
   - Test connection before enabling

4. **Test Connection**:
   - Run `php artisan fhir:test-ahds-connection` (once created)
   - Verify authentication and basic operations work

5. **Enable Gradually**:
   - Set `AZURE_HEALTH_DATA_SERVICES_ENABLED=true`
   - Enable individual handler feature flags one by one
   - Test each step thoroughly

## Key Differences from Azure FHIR Server

- **Authentication**: Uses OAuth2 Client Credentials flow
- **Endpoints**: Workspace-based URLs instead of direct FHIR server URLs
- **Scopes**: Uses `https://azurehealthcareapis.com/.default`
- **Performance**: Better throughput and lower latency
- **Features**: Enhanced audit logging, better Bundle support

## Migration Timeline

1. **Phase 1**: Configure AHDS alongside existing FHIR server
2. **Phase 2**: Test connection and basic operations
3. **Phase 3**: Enable handlers one by one with feature flags
4. **Phase 4**: Full migration and cleanup of legacy configuration 