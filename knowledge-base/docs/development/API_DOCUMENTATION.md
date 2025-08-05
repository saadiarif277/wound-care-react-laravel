# API Documentation

## 🌐 MSC Wound Care Portal API

### Overview
The MSC Wound Care Portal provides a comprehensive REST API for integrating with external systems, mobile applications, and third-party healthcare tools.

## 🔐 Authentication

### API Token Authentication
```bash
# Request API token
curl -X POST https://api.mscwoundcare.com/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Response
{
  "token": "1|abc123...",
  "type": "Bearer",
  "expires_in": 3600
}
```

### Using API Tokens
```bash
# Include token in requests
curl -H "Authorization: Bearer 1|abc123..." \
     -H "Accept: application/json" \
     https://api.mscwoundcare.com/api/v1/patients
```

### OAuth 2.0 (For third-party integrations)
```bash
# Authorization URL
https://api.mscwoundcare.com/oauth/authorize?
  client_id=YOUR_CLIENT_ID&
  redirect_uri=YOUR_REDIRECT_URI&
  response_type=code&
  scope=read:patients write:orders
```

## 📊 API Endpoints

### Patients API

#### List Patients
```http
GET /api/v1/patients
```

**Parameters:**
- `page` (integer): Page number for pagination
- `per_page` (integer): Items per page (max 100)
- `search` (string): Search by name or MRN
- `organization_id` (integer): Filter by organization

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "date_of_birth": "1980-01-15",
      "mrn": "MRN123456",
      "organization_id": 1,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "links": {
    "first": "https://api.example.com/api/v1/patients?page=1",
    "last": "https://api.example.com/api/v1/patients?page=10",
    "prev": null,
    "next": "https://api.example.com/api/v1/patients?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 20,
    "to": 20,
    "total": 200
  }
}
```

#### Get Patient Details
```http
GET /api/v1/patients/{id}
```

**Response:**
```json
{
  "id": 1,
  "first_name": "John",
  "last_name": "Doe",
  "date_of_birth": "1980-01-15",
  "mrn": "MRN123456",
  "phone": "+1234567890",
  "email": "john.doe@email.com",
  "address": {
    "street": "123 Main St",
    "city": "Anytown",
    "state": "CA",
    "zip": "12345"
  },
  "insurance": {
    "primary": {
      "company": "Blue Cross",
      "policy_number": "ABC123",
      "group_number": "GRP456"
    }
  },
  "episodes": [
    {
      "id": 1,
      "start_date": "2024-01-15",
      "status": "active",
      "diagnosis": "Diabetic foot ulcer"
    }
  ]
}
```

#### Create Patient
```http
POST /api/v1/patients
```

**Request Body:**
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "date_of_birth": "1975-05-20",
  "phone": "+1987654321",
  "email": "jane.smith@email.com",
  "address": {
    "street": "456 Oak Ave",
    "city": "Somewhere",
    "state": "NY",
    "zip": "54321"
  },
  "insurance": {
    "primary": {
      "company": "Aetna",
      "policy_number": "XYZ789",
      "group_number": "GRP123"
    }
  }
}
```

### Orders API

#### List Orders
```http
GET /api/v1/orders
```

**Parameters:**
- `status` (string): Filter by order status
- `provider_id` (integer): Filter by provider
- `facility_id` (integer): Filter by facility
- `date_from` (date): Start date filter
- `date_to` (date): End date filter

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "order_number": "ORD-2024-001",
      "patient_id": 1,
      "provider_id": 5,
      "facility_id": 2,
      "status": "pending",
      "total_amount": 299.99,
      "items": [
        {
          "product_id": 10,
          "product_name": "Advanced Wound Dressing",
          "quantity": 5,
          "unit_price": 59.99
        }
      ],
      "created_at": "2024-01-15T14:30:00Z"
    }
  ]
}
```

#### Create Order
```http
POST /api/v1/orders
```

**Request Body:**
```json
{
  "patient_id": 1,
  "facility_id": 2,
  "items": [
    {
      "product_id": 10,
      "quantity": 5,
      "clinical_notes": "For diabetic foot ulcer treatment"
    }
  ],
  "clinical_assessment": {
    "wound_type": "diabetic_ulcer",
    "wound_size": "2.5x1.8cm",
    "wound_depth": "partial_thickness",
    "drainage": "moderate"
  },
  "insurance_verification": {
    "prior_auth_required": false,
    "coverage_verified": true
  }
}
```

### Products API

#### List Products
```http
GET /api/v1/products
```

**Parameters:**
- `category` (string): Product category filter
- `manufacturer` (string): Manufacturer filter
- `active_only` (boolean): Show only active products

**Response:**
```json
{
  "data": [
    {
      "id": 10,
      "name": "Advanced Wound Dressing",
      "sku": "AWD-001",
      "manufacturer": "MedCorp",
      "category": "wound_dressings",
      "description": "Advanced hydrocolloid dressing for chronic wounds",
      "unit_price": 59.99,
      "is_active": true,
      "clinical_indications": [
        "diabetic_ulcers",
        "pressure_ulcers",
        "venous_ulcers"
      ]
    }
  ]
}
```

### Organizations API

#### List Organizations
```http
GET /api/v1/organizations
```

**Permissions Required:** `view-organizations`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Metro Health System",
      "type": "hospital_system",
      "status": "active",
      "facilities_count": 5,
      "providers_count": 25,
      "contact": {
        "phone": "+1555123456",
        "email": "contact@metrohealth.com"
      }
    }
  ]
}
```

### Facilities API

#### List Facilities
```http
GET /api/v1/facilities
```

**Parameters:**
- `organization_id` (integer): Filter by organization

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Metro General Hospital",
      "organization_id": 1,
      "facility_type": "hospital_outpatient",
      "npi": "1234567890",
      "address": {
        "street": "100 Hospital Dr",
        "city": "Metro City",
        "state": "CA",
        "zip": "90210"
      },
      "providers_count": 12
    }
  ]
}
```

### Clinical Opportunities API

#### Get Recommendations
```http
POST /api/v1/clinical/recommendations
```

**Request Body:**
```json
{
  "patient_id": 1,
  "wound_assessment": {
    "wound_type": "diabetic_ulcer",
    "size_length": 2.5,
    "size_width": 1.8,
    "depth": "partial_thickness",
    "drainage_amount": "moderate",
    "drainage_type": "serous",
    "surrounding_skin": "intact",
    "pain_level": 3
  },
  "current_treatment": {
    "products": ["basic_gauze"],
    "frequency": "daily",
    "duration_weeks": 2
  }
}
```

**Response:**
```json
{
  "recommendations": [
    {
      "product_id": 15,
      "product_name": "Hydrocolloid Advanced Dressing",
      "confidence_score": 0.92,
      "rationale": "Optimal for moderate drainage diabetic ulcers",
      "clinical_evidence": {
        "studies": 12,
        "healing_rate_improvement": "35%",
        "cost_effectiveness": "high"
      },
      "usage_instructions": {
        "frequency": "change every 3-5 days",
        "duration": "until healed or significant improvement"
      }
    }
  ],
  "alternative_options": [
    {
      "product_id": 18,
      "product_name": "Foam Dressing with Border",
      "confidence_score": 0.87,
      "rationale": "Good absorption for moderate drainage"
    }
  ]
}
```

## 🔍 FHIR Integration

### FHIR Patient Resource
```http
GET /api/fhir/Patient/{id}
```

**Response:**
```json
{
  "resourceType": "Patient",
  "id": "1",
  "identifier": [
    {
      "type": {
        "coding": [
          {
            "system": "http://terminology.hl7.org/CodeSystem/v2-0203",
            "code": "MR"
          }
        ]
      },
      "value": "MRN123456"
    }
  ],
  "name": [
    {
      "family": "Doe",
      "given": ["John"]
    }
  ],
  "birthDate": "1980-01-15",
  "address": [
    {
      "line": ["123 Main St"],
      "city": "Anytown",
      "state": "CA",
      "postalCode": "12345"
    }
  ]
}
```

### FHIR Observation (Wound Assessment)
```http
GET /api/fhir/Observation/{id}
```

**Response:**
```json
{
  "resourceType": "Observation",
  "id": "wound-assessment-1",
  "status": "final",
  "category": [
    {
      "coding": [
        {
          "system": "http://terminology.hl7.org/CodeSystem/observation-category",
          "code": "assessment"
        }
      ]
    }
  ],
  "code": {
    "coding": [
      {
        "system": "http://snomed.info/sct",
        "code": "225552003",
        "display": "Wound assessment"
      }
    ]
  },
  "subject": {
    "reference": "Patient/1"
  },
  "component": [
    {
      "code": {
        "coding": [
          {
            "system": "http://snomed.info/sct",
            "code": "401238003",
            "display": "Length of wound"
          }
        ]
      },
      "valueQuantity": {
        "value": 2.5,
        "unit": "cm"
      }
    }
  ]
}
```

## 📋 Insurance & Eligibility API

### Check Eligibility
```http
POST /api/v1/insurance/eligibility
```

**Request Body:**
```json
{
  "patient_id": 1,
  "insurance": {
    "payer_id": "12345",
    "member_id": "ABC123456",
    "group_number": "GRP789"
  },
  "provider": {
    "npi": "1234567890",
    "taxonomy": "208100000X"
  },
  "service_type": "durable_medical_equipment"
}
```

**Response:**
```json
{
  "eligible": true,
  "coverage": {
    "active": true,
    "benefit_year": "2024",
    "deductible": {
      "individual": 1000.00,
      "met": 250.00,
      "remaining": 750.00
    },
    "copayment": 25.00,
    "coinsurance": 20,
    "out_of_pocket_max": 5000.00,
    "out_of_pocket_met": 400.00
  },
  "prior_authorization": {
    "required": false,
    "applicable_codes": []
  }
}
```

## 📈 Analytics API

### Order Analytics
```http
GET /api/v1/analytics/orders
```

**Parameters:**
- `date_from` (date): Start date
- `date_to` (date): End date
- `group_by` (string): Group by provider, facility, product
- `organization_id` (integer): Filter by organization

**Response:**
```json
{
  "summary": {
    "total_orders": 1250,
    "total_amount": 125000.50,
    "average_order_value": 100.00
  },
  "trends": [
    {
      "date": "2024-01-15",
      "orders": 42,
      "amount": 4200.00
    }
  ],
  "top_products": [
    {
      "product_id": 10,
      "product_name": "Advanced Wound Dressing",
      "order_count": 156,
      "total_amount": 9204.00
    }
  ]
}
```

## 🔨 Webhooks

### Setting Up Webhooks
Register webhook endpoints to receive real-time updates:

```http
POST /api/v1/webhooks
```

**Request Body:**
```json
{
  "url": "https://your-app.com/webhooks/msc",
  "events": ["order.created", "order.updated", "patient.updated"],
  "secret": "your-webhook-secret"
}
```

### Webhook Events

#### Order Created
```json
{
  "event": "order.created",
  "timestamp": "2024-01-15T14:30:00Z",
  "data": {
    "order_id": 123,
    "order_number": "ORD-2024-001",
    "patient_id": 456,
    "status": "pending",
    "total_amount": 299.99
  }
}
```

#### Order Status Updated
```json
{
  "event": "order.status_updated",
  "timestamp": "2024-01-15T16:45:00Z",
  "data": {
    "order_id": 123,
    "old_status": "pending",
    "new_status": "approved",
    "updated_by": "msc_admin_user"
  }
}
```

## 🚫 Error Handling

### Error Response Format
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "phone": ["The phone format is invalid."]
    }
  },
  "request_id": "req_123456789"
}
```

### Common Error Codes
- `400 BAD_REQUEST`: Invalid request format
- `401 UNAUTHORIZED`: Authentication required
- `403 FORBIDDEN`: Insufficient permissions
- `404 NOT_FOUND`: Resource not found
- `422 VALIDATION_ERROR`: Request validation failed
- `429 RATE_LIMITED`: Too many requests
- `500 INTERNAL_ERROR`: Server error

## 📊 Rate Limiting

### Rate Limits
- **General API**: 1000 requests per hour
- **Authentication**: 5 requests per minute
- **File Uploads**: 10 requests per minute
- **Webhooks**: 100 requests per minute

### Rate Limit Headers
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1642694400
```

## 🔧 SDKs and Libraries

### PHP SDK
```php
composer require msc/wound-care-api

use MSC\WoundCare\Client;

$client = new Client([
    'token' => 'your-api-token',
    'base_url' => 'https://api.mscwoundcare.com'
]);

$patients = $client->patients()->list();
```

### JavaScript SDK
```javascript
npm install @msc/wound-care-api

import { MSCWoundCareAPI } from '@msc/wound-care-api';

const api = new MSCWoundCareAPI({
  token: 'your-api-token',
  baseURL: 'https://api.mscwoundcare.com'
});

const patients = await api.patients.list();
```

## 📚 Additional Resources

- [Authentication Guide](./API_AUTHENTICATION.md)
- [FHIR Implementation](../integrations/FHIR_INTEGRATION.md)
- [Webhook Setup Guide](./WEBHOOK_GUIDE.md)
- [SDK Documentation](./SDK_DOCUMENTATION.md)
