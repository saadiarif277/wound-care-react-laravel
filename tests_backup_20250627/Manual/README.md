# Manual Testing Suite

This directory contains manual test scripts for the MSC Wound Care Portal. These tests are designed to verify functionality that requires manual execution or integration testing outside of the standard PHPUnit test suite.

## Directory Structure

```
tests/Manual/
├── Api/                    # API endpoint testing
├── Integration/            # Integration and connection tests
├── Security/              # Role-based access and security tests
├── Services/              # Service layer testing
└── README.md              # This file
```

## Test Categories

### API Tests (`Api/`)
Tests for REST API endpoints and external service integrations.

- **FhirApiTest.php** - Tests FHIR API endpoints and Azure Health Data Services integration
- **ProductRecommendationsApiTest.php** - Tests the product recommendation API endpoints

### Integration Tests (`Integration/`)
Tests for database connections and external service integrations.

- **SupabaseConnectionTest.php** - Tests Supabase database connectivity and operations

### Security Tests (`Security/`)
Tests for role-based access control and financial data restrictions.

- **OfficeManagerPricingRestrictionsTest.php** - Tests Office Manager role financial restrictions
- **ProductCatalogRestrictionsTest.php** - Tests product catalog role-based pricing visibility

### Service Tests (`Services/`)
Tests for business logic services and internal components.

- **ValidationBuilderServiceTest.php** - Tests the validation builder service functionality
- **PatientServiceTest.php** - Tests patient service operations and FHIR integration

## Running Tests

### Prerequisites

1. Ensure your `.env` file is properly configured
2. Database connections are established
3. Required services are running (if testing integrations)

### Running Individual Tests

```bash
# Run from the project root directory
php tests/Manual/Api/FhirApiTest.php
php tests/Manual/Security/ProductCatalogRestrictionsTest.php
php tests/Manual/Integration/SupabaseConnectionTest.php
```

### Running All Manual Tests

Use the test runner script:

```bash
php tests/Manual/run-all-tests.php
```

## Test Configuration

### Environment Variables Required

Different tests may require specific environment variables:

#### FHIR API Tests
```env
AZURE_TENANT_ID=your-tenant-id
AZURE_CLIENT_ID=your-client-id
AZURE_CLIENT_SECRET=your-client-secret
AZURE_FHIR_ENDPOINT=https://your-workspace.fhir.azurehealthcareapis.com
```

#### Supabase Tests
```env
SUPABASE_URL=your-supabase-url
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
```

#### eCW Integration Tests
```env
ECW_FHIR_SANDBOX_ENDPOINT=your-ecw-sandbox-endpoint
ECW_FHIR_PRODUCTION_ENDPOINT=your-ecw-production-endpoint
ECW_CLIENT_ID=your-ecw-client-id
ECW_CLIENT_SECRET=your-ecw-client-secret
```

## Test Results

Tests will output results to the console with:
- ✅ Success indicators
- ❌ Failure indicators
- Detailed error messages when applicable
- Performance metrics where relevant

## Adding New Tests

When adding new manual tests:

1. Place them in the appropriate category directory
2. Use descriptive filenames ending with `Test.php`
3. Include proper error handling and output formatting
4. Update this README with test descriptions
5. Add any required environment variables to the configuration section

## Integration with CI/CD

These manual tests are designed for:
- Local development verification
- Manual QA testing
- Integration testing with external services
- Security validation

They are not automatically run in CI/CD pipelines due to their manual nature and external dependencies.

## Troubleshooting

### Common Issues

1. **Database Connection Errors**: Ensure your `.env` database configuration is correct
2. **API Endpoint Errors**: Verify external service endpoints and credentials
3. **Permission Errors**: Check that your user has appropriate database and file permissions
4. **Missing Dependencies**: Run `composer install` to ensure all packages are available

### Getting Help

If tests fail:
1. Check the error output for specific issues
2. Verify environment configuration
3. Ensure all required services are running
4. Check network connectivity for external service tests

## Security Considerations

- Never commit real credentials to version control
- Use test/sandbox environments when possible
- Be cautious with PHI data in test scenarios
- Ensure test data is properly cleaned up after execution 
