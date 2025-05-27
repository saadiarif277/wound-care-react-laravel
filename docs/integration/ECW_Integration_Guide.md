# eClinicalWorks FHIR Integration Guide

## Overview

This integration provides secure access to patient data from eClinicalWorks EHR system through their FHIR R4 API. The integration follows OAuth2 authentication, HIPAA compliance standards, and eCW's developer terms and conditions.

## Features

### Supported Operations
- **OAuth2 Authentication** - Secure user authorization with eClinicalWorks
- **Patient Search** - Search patients by name, birthdate, gender, identifier
- **Patient Details** - Retrieve complete patient demographics
- **Observations** - Access vital signs, lab results, and clinical observations
- **Documents** - Retrieve clinical notes and documentation
- **Audit Logging** - HIPAA-compliant access tracking

### Security & Compliance
- ✅ OAuth2 with PKCE support
- ✅ Encrypted token storage
- ✅ HIPAA audit logging
- ✅ State parameter validation
- ✅ Input sanitization and validation
- ✅ Rate limiting and error handling

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# eClinicalWorks Configuration
ECW_CLIENT_ID=your-ecw-client-id
ECW_CLIENT_SECRET=your-ecw-client-secret
ECW_ENVIRONMENT=sandbox
ECW_SANDBOX_ENDPOINT=https://fhir.eclinicalworks.com/ecwopendev/fhir
ECW_PRODUCTION_ENDPOINT=https://fhir.eclinicalworks.com/production/fhir
ECW_APPLICATION_ID=your-application-id
ECW_REDIRECT_URI=https://your-domain.com/api/ecw/callback
ECW_SCOPE=patient/Patient.read patient/Observation.read patient/DocumentReference.read
ECW_API_VERSION=R4
```

### eClinicalWorks Developer Portal Setup

1. **Register at eCW Developer Portal**
   - Visit: https://fhir.eclinicalworks.com/ecwopendev/global/signup
   - Complete the registration form
   - Accept the Terms & Conditions

2. **Create Your Application**
   - Log in to the developer portal
   - Click "App Registration"
   - Fill in your application details:
     - **App Name**: Your application name
     - **Description**: Brief description of your wound care solution
     - **Redirect URI**: `https://your-domain.com/api/ecw/callback`
     - **Scope**: `patient/Patient.read patient/Observation.read patient/DocumentReference.read`

3. **Get Your Credentials**
   - Note your **Client ID** and **Client Secret**
   - These will be used in your environment configuration

### Database Setup

Run the migrations to create required tables:

```bash
php artisan migrate
```

This creates:
- `ecw_user_tokens` - Secure token storage
- `ecw_audit_log` - HIPAA audit trail

## Usage

### 1. Frontend Integration

Add the eCW connection component to your dashboard:

```tsx
import EcwConnection from '@/Components/EcwIntegration/EcwConnection';

function Dashboard() {
  const [ecwConnected, setEcwConnected] = useState(false);

  return (
    <div>
      <EcwConnection onConnectionChange={setEcwConnected} />
      
      {ecwConnected && (
        <div>
          {/* Your eCW-enabled features */}
        </div>
      )}
    </div>
  );
}
```

### 2. Authentication Flow

```typescript
// 1. Initiate authentication
// User clicks "Connect to eClinicalWorks" button
// Redirects to: /api/ecw/auth

// 2. User authorizes on eCW
// eCW redirects back to: /api/ecw/callback

// 3. Check connection status
const response = await fetch('/api/ecw/status');
const { connected } = await response.json();
```

### 3. API Usage Examples

#### Search Patients
```typescript
const searchPatients = async (searchTerm: string) => {
  const response = await fetch(`/api/ecw/patients/search?name=${searchTerm}`, {
    headers: {
      'Accept': 'application/fhir+json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });
  
  if (response.status === 401) {
    // User needs to authenticate with eCW
    window.location.href = '/api/ecw/auth';
    return;
  }
  
  const bundle = await response.json();
  return bundle.entry || [];
};
```

#### Get Patient Details
```typescript
const getPatient = async (patientId: string) => {
  const response = await fetch(`/api/ecw/patients/${patientId}`, {
    headers: {
      'Accept': 'application/fhir+json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });
  
  if (!response.ok) {
    throw new Error('Failed to fetch patient');
  }
  
  return await response.json();
};
```

#### Get Patient Observations
```typescript
const getObservations = async (patientId: string, category?: string) => {
  const params = new URLSearchParams();
  if (category) params.append('category', category);
  
  const response = await fetch(
    `/api/ecw/patients/${patientId}/observations?${params}`,
    {
      headers: {
        'Accept': 'application/fhir+json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }
  );
  
  const bundle = await response.json();
  return bundle.entry || [];
};
```

### 4. Server-Side Integration

```php
use App\Services\EcwFhirService;

class YourController extends Controller
{
    public function __construct(private EcwFhirService $ecwService) {}
    
    public function getPatientData(string $patientId)
    {
        // Check if user has valid eCW token
        if (!$this->ecwService->hasValidToken(auth()->id())) {
            return response()->json(['requires_auth' => true], 401);
        }
        
        // Get patient data
        $accessToken = $this->ecwService->getUserToken(auth()->id());
        $patient = $this->ecwService->getPatient($patientId, $accessToken);
        
        return response()->json($patient);
    }
}
```

## API Endpoints

### Authentication
- `GET /api/ecw/auth` - Initiate OAuth2 flow
- `GET /api/ecw/callback` - OAuth2 callback handler

### Connection Management  
- `GET /api/ecw/status` - Check connection status
- `POST /api/ecw/disconnect` - Disconnect from eCW
- `GET /api/ecw/test` - Test connection validity

### FHIR Data Access
- `GET /api/ecw/patients/search` - Search patients
- `GET /api/ecw/patients/{id}` - Get patient details
- `GET /api/ecw/patients/{id}/observations` - Get observations
- `GET /api/ecw/patients/{id}/documents` - Get documents

## Error Handling

### Common Error Responses

```json
// Not authenticated with eCW
{
  "error": "Not connected to eClinicalWorks",
  "requires_auth": true
}

// Patient not found
{
  "error": "Patient not found"
}

// Server error
{
  "error": "Patient search failed"
}
```

### Handle Authentication Expiry

```typescript
const handleApiCall = async (apiCall: () => Promise<Response>) => {
  const response = await apiCall();
  
  if (response.status === 401) {
    const data = await response.json();
    if (data.requires_auth) {
      // Redirect to eCW authentication
      window.location.href = '/api/ecw/auth';
      return;
    }
  }
  
  return response;
};
```

## Security Considerations

### Token Management
- Tokens are encrypted at rest using Laravel's encryption
- Tokens are automatically refreshed when needed
- Expired tokens are cleaned up automatically

### Audit Logging
All patient data access is logged with:
- Patient ID
- User ID
- Action performed
- IP address
- User agent
- Timestamp

### Input Validation
- All search parameters are sanitized
- Parameter limits are enforced
- Invalid characters are stripped

## Testing

### Sandbox Environment
eCW provides a sandbox environment for testing:
- URL: `https://fhir.eclinicalworks.com/ecwopendev/fhir`
- Test patients and data available
- Safe for development and testing

### Connection Testing
```bash
# Test the connection programmatically
curl -X GET "https://your-domain.com/api/ecw/test" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer your-app-token"
```

## Compliance

### HIPAA Requirements Met
- ✅ Access logging and audit trails
- ✅ Secure authentication (OAuth2)
- ✅ Encrypted data transmission (HTTPS)
- ✅ Encrypted data storage
- ✅ User access controls
- ✅ Data minimization (only requested scopes)

### eCW Terms Compliance
- ✅ Regular security testing
- ✅ Anti-virus scanning
- ✅ No malicious code
- ✅ Performance impact monitoring
- ✅ Proper data usage agreements
- ✅ No unauthorized data selling
- ✅ Consent-based data access

## Troubleshooting

### Common Issues

**Connection Failed**
- Verify credentials in `.env`
- Check redirect URI matches exactly
- Ensure HTTPS in production

**Token Expired**
- Tokens automatically refresh
- Manual refresh: delete from `ecw_user_tokens` table
- Re-authenticate via `/api/ecw/auth`

**Permission Denied**
- Check scope in application registration
- Verify user has proper permissions in eCW
- Contact eCW support for scope issues

**Sandbox vs Production**
- Ensure `ECW_ENVIRONMENT` is set correctly
- Sandbox: `sandbox`, Production: `production`
- Different endpoints and credentials

### Support

- **eCW Developer Support**: Support through developer portal
- **Documentation**: https://fhir.eclinicalworks.com/ecwopendev
- **FHIR Specification**: http://hl7.org/fhir/R4/

## Changelog

### Version 1.0.0
- Initial eClinicalWorks FHIR integration
- OAuth2 authentication flow
- Patient, Observation, DocumentReference support
- HIPAA audit logging
- React components for UI integration 
