# MSC-MVP FHIR Server Implementation Guide

## Overview

The MSC-MVP FHIR Server is a FHIR R4-compliant proxy server that forwards requests to Azure Health Data Services while maintaining PHI compliance and adding MSC-specific extensions for wound care management.

## Architecture

```
Client → MSC FHIR Server → Azure Health Data Services
```

### Key Components

1. **FhirController** - Handles HTTP requests and responses
2. **FhirService** - Business logic and Azure integration
3. **Azure Authentication** - OAuth2 token management
4. **MSC Extensions** - Wound care specific FHIR extensions

## Features

### Supported FHIR Operations

- **Create** (`POST /fhir/Patient`) - Create new patient resources
- **Read** (`GET /fhir/Patient/{id}`) - Retrieve patient by ID
- **Update** (`PUT /fhir/Patient/{id}`) - Update entire patient resource
- **Patch** (`PATCH /fhir/Patient/{id}`) - Partial patient updates
- **Delete** (`DELETE /fhir/Patient/{id}`) - Remove patient resource
- **Search** (`GET /fhir/Patient?param=value`) - Search patients
- **History** (`GET /fhir/Patient/{id}/_history`) - Version history
- **Transaction** (`POST /fhir`) - Batch operations
- **Capability** (`GET /fhir/metadata`) - Server capabilities

### MSC Extensions

The server automatically adds MSC-specific extensions to Patient resources:

```json
{
  "extension": [
    {
      "url": "http://msc-mvp.com/fhir/StructureDefinition/wound-care-consent",
      "valueCode": "active"
    },
    {
      "url": "http://msc-mvp.com/fhir/StructureDefinition/platform-status", 
      "valueCode": "pending"
    },
    {
      "url": "http://msc-mvp.com/fhir/StructureDefinition/preferred-language",
      "valueCode": "en"
    }
  ]
}
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Azure Health Data Services Configuration
AZURE_TENANT_ID=your-azure-tenant-id
AZURE_CLIENT_ID=your-azure-client-id
AZURE_CLIENT_SECRET=your-azure-client-secret
AZURE_FHIR_ENDPOINT=https://your-workspace.fhir.azurehealthcareapis.com
```

### Azure Setup Requirements

1. **Azure Health Data Services Workspace**
2. **FHIR Service** within the workspace
3. **App Registration** with appropriate permissions
4. **Client Secret** for authentication

## API Endpoints

### Base URL
```
/api/fhir
```

### Capability Statement
```http
GET /api/fhir/metadata
Accept: application/fhir+json
```

### Patient Operations

#### Create Patient
```http
POST /api/fhir/Patient
Content-Type: application/fhir+json

{
  "resourceType": "Patient",
  "identifier": [
    {
      "use": "usual",
      "type": {
        "coding": [
          {
            "system": "http://terminology.hl7.org/CodeSystem/v2-0203",
            "code": "MR",
            "display": "Medical record number"
          }
        ]
      },
      "value": "MRN123456"
    }
  ],
  "active": true,
  "name": [
    {
      "use": "official",
      "family": "Doe",
      "given": ["John"]
    }
  ],
  "gender": "male",
  "birthDate": "1980-01-01"
}
```

#### Read Patient
```http
GET /api/fhir/Patient/{id}
Accept: application/fhir+json
```

#### Search Patients
```http
GET /api/fhir/Patient?name=John&gender=male&_count=10
Accept: application/fhir+json
```

#### Transaction Bundle
```http
POST /api/fhir
Content-Type: application/fhir+json

{
  "resourceType": "Bundle",
  "type": "transaction",
  "entry": [
    {
      "request": {
        "method": "POST",
        "url": "Patient"
      },
      "resource": {
        "resourceType": "Patient",
        // ... patient data
      }
    }
  ]
}
```

## Search Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Search by patient name |
| `birthdate` | date | Search by birth date (YYYY-MM-DD) |
| `gender` | token | Search by gender (male, female, other, unknown) |
| `identifier` | token | Search by identifier (MRN, member ID, etc.) |
| `_count` | number | Number of results (1-100, default 20) |
| `_page` | number | Page number for pagination |

## Error Handling

The server returns FHIR-compliant OperationOutcome resources for errors:

```json
{
  "resourceType": "OperationOutcome",
  "issue": [
    {
      "severity": "error",
      "code": "not-found",
      "diagnostics": "Patient with id 'invalid-id' not found"
    }
  ]
}
```

### HTTP Status Codes

- `200 OK` - Successful read/search
- `201 Created` - Successful create
- `204 No Content` - Successful delete
- `400 Bad Request` - Invalid request format
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

## Security

### Authentication

The server uses OAuth2 client credentials flow to authenticate with Azure:

1. Requests access token from Azure AD
2. Caches token for 50 minutes
3. Includes token in all Azure FHIR API calls

### PHI Compliance

- **No PHI Storage**: Patient data is never stored in Supabase
- **Proxy Only**: All data remains in Azure Health Data Services
- **Audit Logging**: All operations are logged for compliance
- **URL Rewriting**: Azure URLs are replaced with local URLs in responses

## Testing

Run the test script to verify functionality:

```bash
php test-fhir-api.php
```

The test covers:
1. Capability statement retrieval
2. Patient creation
3. Patient reading
4. Patient searching
5. Transaction processing

## Monitoring

### Logs

The server logs all operations to Laravel's logging system:

```php
Log::info('FHIR Patient created in Azure', ['patient_id' => $patient['id']]);
Log::error('Failed to create FHIR Patient in Azure', ['error' => $e->getMessage()]);
```

### Health Checks

Monitor these indicators:
- Azure token refresh success
- API response times
- Error rates
- Azure service availability

## Performance

### Caching

- **Access Tokens**: Cached for 50 minutes
- **No Resource Caching**: Ensures data consistency with Azure

### Rate Limiting

Consider implementing rate limiting for production:

```php
Route::middleware(['throttle:60,1'])->group(function () {
    // FHIR routes
});
```

## Development

### Local Setup

1. Configure Azure credentials in `.env`
2. Ensure Laravel app is running
3. Test with `test-fhir-api.php`

### Adding New Resources

To support additional FHIR resources:

1. Add routes in `routes/api.php`
2. Add methods to `FhirController`
3. Extend `FhirService` with new operations
4. Update capability statement

### Custom Extensions

Add MSC-specific extensions in `FhirService::addMscExtensions()`:

```php
$fhirData['extension'][] = [
    'url' => 'http://msc-mvp.com/fhir/StructureDefinition/custom-field',
    'valueString' => $customValue
];
```

## Deployment

### Production Considerations

1. **SSL/TLS**: HTTPS required for PHI
2. **Rate Limiting**: Protect against abuse
3. **Monitoring**: Azure Application Insights
4. **Backup**: Azure handles data backup
5. **Scaling**: Horizontal scaling supported

### Environment Configuration

```env
APP_ENV=production
APP_DEBUG=false
AZURE_TENANT_ID=prod-tenant-id
AZURE_CLIENT_ID=prod-client-id
AZURE_CLIENT_SECRET=prod-client-secret
AZURE_FHIR_ENDPOINT=https://prod-workspace.fhir.azurehealthcareapis.com
```

## Troubleshooting

### Common Issues

1. **401 Unauthorized**
   - Check Azure credentials
   - Verify app registration permissions
   - Ensure client secret is valid

2. **404 Not Found**
   - Verify Azure FHIR endpoint URL
   - Check if resource exists in Azure

3. **Rate Limiting**
   - Azure has built-in rate limits
   - Implement exponential backoff

### Debug Mode

Enable debug logging:

```php
Log::debug('Azure FHIR Request', ['url' => $url, 'data' => $data]);
```

## Compliance

### HIPAA

- All PHI remains in Azure (HIPAA compliant)
- Audit logs maintained
- Access controls enforced

### FHIR R4

- Fully compliant with FHIR R4 specification
- Supports required and recommended operations
- Proper error handling with OperationOutcome

## Support

For issues and questions:

1. Check Azure Health Data Services documentation
2. Review Laravel logs for errors
3. Verify FHIR resource format
4. Test with capability statement first 
