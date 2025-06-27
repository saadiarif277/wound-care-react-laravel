# Product Request Patient Flow E2E Tests

This directory contains comprehensive end-to-end tests for the product request patient flow feature.

## Test Files Created

### 1. ProductRequestPatientE2ETest.php

Comprehensive E2E tests covering the complete patient flow from start to finish:

- **test_complete_product_request_patient_flow**: Tests the entire workflow from patient info to order submission
- **test_patient_search_integration**: Tests FHIR patient search functionality
- **test_medicare_mac_validation**: Tests Medicare MAC regional validation
- **test_validation_errors_for_incomplete_submission**: Tests form validation
- **test_concurrent_user_access_handling**: Tests access control and permissions
- **test_order_action_history_tracking**: Tests audit trail functionality
- **test_auto_save_draft_functionality**: Tests draft auto-save feature
- **test_order_summary_pdf_generation**: Tests PDF generation for completed orders
- **test_error_recovery_mechanisms**: Tests error handling and retry logic

### 2. ProductRequestPatientApiTest.php

API integration tests focusing on individual endpoints:

- **test_api_requires_authentication**: Ensures all endpoints require authentication
- **test_create_product_request_api**: Tests order creation endpoint
- **test_update_insurance_api**: Tests insurance information update
- **test_verify_eligibility_api**: Tests eligibility verification with mocked external services
- **test_update_wound_info_api**: Tests wound information updates
- **test_get_product_recommendations_api**: Tests AI-powered product recommendations
- **test_add_products_api**: Tests product selection and pricing
- **test_attestations_api**: Tests provider attestations
- **test_submit_order_api**: Tests order submission workflow
- **test_auto_save_api**: Tests draft auto-save functionality
- **test_order_history_api**: Tests audit trail API
- **test_mac_validation_api**: Tests Medicare MAC validation API
- **test_api_rate_limiting**: Tests API rate limiting
- **test_concurrent_request_handling**: Tests concurrent request handling
- **test_api_error_handling**: Tests comprehensive error scenarios

## Running the Tests

To run all E2E tests:

```bash
php artisan test --filter=ProductRequestPatient
```

To run a specific test:

```bash
php artisan test --filter=ProductRequestPatientE2ETest::test_complete_product_request_patient_flow
```

## Test Setup Requirements

1. **Database**: Tests use RefreshDatabase trait, so ensure test database is configured
2. **Mocking**: Tests mock external services (FHIR, Availity, etc.)
3. **Factories**: Ensure all model factories are properly configured

## Key Testing Patterns

### 1. Authentication

All tests start by creating and authenticating a provider user:

```php
$this->actingAs($this->providerUser);
```

### 2. External Service Mocking

External APIs are mocked to ensure consistent test results:

```php
Http::fake([
    'availity.com/api/*' => Http::response([...], 200)
]);
```

### 3. Database Assertions

Tests verify both API responses and database state:

```php
$response->assertStatus(200);
$this->assertDatabaseHas('product_requests', [...]);
```

### 4. Comprehensive Coverage

Tests cover:

- Happy path scenarios
- Error conditions
- Edge cases
- Security/permissions
- Performance considerations

## Integration Points Tested

1. **FHIR Integration**: Patient and provider data retrieval
2. **Eligibility Services**: Insurance verification via Availity
3. **MAC Validation**: Medicare regional requirements
4. **Product Recommendations**: AI-powered product matching
5. **PDF Generation**: Order summary documents
6. **Audit Trail**: Complete action history tracking

## Best Practices

1. Each test is isolated and doesn't depend on others
2. Mock external services to avoid dependencies
3. Test both success and failure scenarios
4. Verify both API responses and database state
5. Use descriptive test names that explain what's being tested
