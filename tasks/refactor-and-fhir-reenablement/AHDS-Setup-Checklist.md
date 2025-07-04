# Azure Health Data Services Setup Checklist

Based on Azure Health Data Services documentation and FHIR R4 requirements, here's a comprehensive checklist to verify your setup.

## ✅ Azure Portal Setup

### 1. Azure Health Data Services Workspace
- [ ] **Create AHDS Workspace**: Created in Azure Portal under "Azure Health Data Services"
- [ ] **FHIR Service**: Created within the workspace 
- [ ] **Workspace URL**: Format should be `https://{workspace-name}.fhir.azurehealthcareapis.com`
- [ ] **Resource Group**: Properly configured with appropriate permissions
- [ ] **Subscription**: Active Azure subscription with Healthcare APIs enabled

### 2. Azure Active Directory App Registration
- [ ] **App Registration Created**: For OAuth2 authentication
- [ ] **Client ID**: Generated and noted
- [ ] **Client Secret**: Generated and securely stored
- [ ] **Tenant ID**: Identified and noted
- [ ] **API Permissions**: Granted to Azure Healthcare APIs
  - [ ] `https://azurehealthcareapis.com/user_impersonation` (if using user auth)
  - [ ] `https://azurehealthcareapis.com/.default` (for client credentials)
- [ ] **Admin Consent**: Granted for application permissions

### 3. FHIR Service Configuration
- [ ] **FHIR Version**: R4 selected
- [ ] **Authentication**: OAuth2 configured
- [ ] **CORS**: Configured if needed for browser access
- [ ] **Export Settings**: Configured if data export is needed

## ✅ Application Configuration

### 4. Environment Variables (Current Status)
Based on your current setup, verify these environment variables:

#### ✅ COMPLETED - Azure Health Data Services Variables
```bash
# These should be set in your .env file
AZURE_HEALTH_DATA_SERVICES_ENABLED=true  # Set to true when ready
AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL=https://your-workspace.fhir.azurehealthcareapis.com
AZURE_HEALTH_DATA_SERVICES_TENANT_ID=your-tenant-id
AZURE_HEALTH_DATA_SERVICES_CLIENT_ID=your-client-id
AZURE_HEALTH_DATA_SERVICES_CLIENT_SECRET=your-client-secret
AZURE_HEALTH_DATA_SERVICES_SCOPE=https://azurehealthcareapis.com/.default
AZURE_HEALTH_DATA_SERVICES_OAUTH_ENDPOINT=https://login.microsoftonline.com
```

#### ✅ COMPLETED - Legacy Azure FHIR Server Variables (Keep for fallback)
```bash
# Keep these during migration period
AZURE_FHIR_ENDPOINT=your-legacy-endpoint
AZURE_FHIR_TENANT_ID=your-tenant-id
AZURE_FHIR_CLIENT_ID=your-client-id  
AZURE_FHIR_CLIENT_SECRET=your-client-secret
AZURE_FHIR_SCOPE=your-legacy-scope
```

#### ✅ COMPLETED - Feature Flags
```bash
# FHIR Operations (start with false, enable gradually)
FHIR_ENABLED=false
FHIR_SERVICE_ENABLED=false
FHIR_PATIENT_HANDLER_ENABLED=false
FHIR_PROVIDER_HANDLER_ENABLED=false
FHIR_INSURANCE_HANDLER_ENABLED=false
FHIR_CLINICAL_HANDLER_ENABLED=false
FHIR_ORDER_HANDLER_ENABLED=false

# FHIR Configuration
FHIR_CACHE_ENABLED=true
FHIR_AUDIT_ENABLED=true
FHIR_STRICT_VALIDATION=false
```

### 5. Configuration Files Status

#### ✅ COMPLETED - config/services.php
- [x] **Azure AHDS Configuration**: Added comprehensive AHDS config section
- [x] **OAuth2 Settings**: Proper OAuth2 endpoint configuration
- [x] **Scope Configuration**: Correct AHDS scope settings
- [x] **Backward Compatibility**: Legacy FHIR server config maintained

#### ✅ COMPLETED - config/fhir.php  
- [x] **AHDS Section**: Dedicated AHDS configuration block
- [x] **OAuth2 Settings**: Token caching and authentication config
- [x] **FHIR R4 Support**: Version set to R4
- [x] **Resource Validation**: FHIR resource profiles configured
- [x] **Custom Extensions**: MSC wound care extensions defined

#### ✅ COMPLETED - config/features.php
- [x] **Feature Flags**: Comprehensive FHIR operation flags
- [x] **Handler Controls**: Individual handler enable/disable flags
- [x] **Service Controls**: Global FHIR service enable/disable

### 6. Application Services Status

#### ✅ COMPLETED - Connection Test Command
- [x] **Test Command Created**: `php artisan fhir:test-ahds-connection`
- [x] **OAuth2 Testing**: Tests authentication flow
- [x] **Read Access**: Tests CapabilityStatement retrieval
- [x] **Write Access**: Tests Patient resource creation/deletion
- [x] **Bundle Operations**: Tests transaction support
- [x] **Performance Metrics**: Includes timing information

#### 🔄 IN PROGRESS - FhirService Updates
- [x] **Basic Configuration**: Service configured for Azure
- [ ] **AHDS Authentication**: Update OAuth2 flow for AHDS
- [ ] **Token Management**: Implement proper token caching
- [ ] **Error Handling**: AHDS-specific error responses
- [ ] **Endpoint Switching**: Dynamic endpoint selection (legacy vs AHDS)

## 🔄 Testing & Validation

### 7. Connection Testing
- [ ] **Run Connection Test**: Execute `php artisan fhir:test-ahds-connection --details`
- [ ] **OAuth2 Authentication**: Verify token acquisition
- [ ] **Read Operations**: Confirm CapabilityStatement retrieval
- [ ] **Write Operations**: Test Patient resource CRUD
- [ ] **Bundle Transactions**: Verify transaction support
- [ ] **Performance**: Check response times (<2000ms typical)

### 8. FHIR R4 Compliance
- [ ] **Resource Validation**: Test with FHIR R4 resources
- [ ] **Search Parameters**: Verify search functionality
- [ ] **Bundle Operations**: Test transaction and batch bundles
- [ ] **Audit Logging**: Confirm audit events are generated
- [ ] **Error Responses**: Verify OperationOutcome format

## 📋 Migration Steps

### 9. Gradual Rollout Plan
1. [ ] **Phase 1**: Test connection with AHDS workspace
2. [ ] **Phase 2**: Enable `AZURE_HEALTH_DATA_SERVICES_ENABLED=true`
3. [ ] **Phase 3**: Enable `FHIR_ENABLED=true` and `FHIR_SERVICE_ENABLED=true`
4. [ ] **Phase 4**: Enable individual handlers one by one:
   - [ ] Patient Handler (`FHIR_PATIENT_HANDLER_ENABLED=true`)
   - [ ] Provider Handler (`FHIR_PROVIDER_HANDLER_ENABLED=true`)
   - [ ] Insurance Handler (`FHIR_INSURANCE_HANDLER_ENABLED=true`)
   - [ ] Clinical Handler (`FHIR_CLINICAL_HANDLER_ENABLED=true`)
   - [ ] Order Handler (`FHIR_ORDER_HANDLER_ENABLED=true`)
5. [ ] **Phase 5**: Full end-to-end testing
6. [ ] **Phase 6**: Remove legacy configuration

## ⚠️ Known Issues & Considerations

### 10. Current Issues to Address
- [ ] **FhirService OAuth2**: Update authentication for AHDS
- [ ] **Token Caching**: Implement proper token management
- [ ] **Error Handling**: Add AHDS-specific error handling
- [ ] **Endpoint Configuration**: Dynamic endpoint selection
- [ ] **Cache Warming**: Fix provider_fhir_id issues

### 11. Security & Compliance
- [ ] **PHI Handling**: Ensure proper PHI audit logging
- [ ] **Access Controls**: Verify role-based access
- [ ] **Encryption**: Confirm data encryption in transit
- [ ] **Audit Trails**: Complete audit logging implementation
- [ ] **HIPAA Compliance**: Verify compliance requirements

## 📊 Current Status Summary

### ✅ COMPLETED (Excellent Progress!)
- Azure Health Data Services configuration structure
- Environment variable framework
- Feature flag system
- Connection test command
- Configuration files setup
- Documentation and checklists

### 🔄 IN PROGRESS  
- FhirService OAuth2 updates for AHDS
- Connection testing and validation

### ⏳ PENDING
- Gradual handler enablement
- End-to-end testing
- Legacy configuration cleanup

## 🎯 Next Immediate Steps

1. **Test Current Connection**: Run the AHDS connection test
2. **Update FhirService**: Implement AHDS OAuth2 authentication
3. **Enable AHDS**: Set `AZURE_HEALTH_DATA_SERVICES_ENABLED=true`
4. **Gradual Rollout**: Enable handlers one by one

Your setup is very well structured and you've completed most of the foundational work! The configuration framework is excellent and follows Azure Health Data Services best practices. 