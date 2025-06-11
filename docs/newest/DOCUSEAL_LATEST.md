# DocuSeal Integration - Current Implementation

## Overview

DocuSeal is integrated into the MSC Wound Portal to handle electronic document generation and signature workflows, specifically for Insurance Verification Requests (IVRs) in the order fulfillment process. The integration enables automated document creation, electronic signatures from providers, and tracking of document status throughout the order lifecycle.

## Architecture

### Core Components

1. **IvrDocusealService** (`/app/Services/IvrDocusealService.php`)
   - Primary service for IVR document generation
   - Handles provider signature workflows
   - Manages manufacturer approval tracking
   - Integrates with FHIR for patient data

2. **DocusealService** (`/app/Services/DocusealService.php`)
   - Base service for DocuSeal API interactions
   - Handles document generation for orders
   - Manages submission creation and tracking

3. **Webhook Controller** (`/app/Http/Controllers/DocuSealWebhookController.php`)
   - Processes DocuSeal webhook events
   - Updates order status when documents are signed
   - Verifies webhook signatures for security

### Data Models

1. **DocusealFolder** (`/app/Models/Docuseal/DocusealFolder.php`)
   - Maps manufacturers to specific DocuSeal folders
   - Stores folder IDs and delivery endpoints
   - Enables manufacturer-specific template selection

2. **DocusealTemplate** (`/app/Models/Docuseal/DocusealTemplate.php`)
   - Stores template information by type (IVR, Order Form, etc.)
   - Links templates to manufacturer folders
   - Tracks active/inactive status

3. **DocusealSubmission** (`/app/Models/Docuseal/DocusealSubmission.php`)
   - Records all document submissions
   - Tracks signing URLs and document status
   - Links to ProductRequest via order_id

## IVR Workflow Integration

### 1. IVR Generation Process

When an admin clicks "Generate IVR" for a ProductRequest with status `pending_ivr`:

```php
// IvrDocusealService::generateIvr()
1. Validates order status is 'pending_ivr'
2. Identifies manufacturer from product
3. Retrieves manufacturer's DocuSeal folder
4. Selects IVR template from folder
5. Fetches patient data from FHIR
6. Pre-populates template fields
7. Creates DocuSeal submission (no signature required)
8. Generates document for admin review
9. Updates ProductRequest status to 'ivr_sent'
10. Stores document URL for download
```

### 2. Field Mapping

The IVR template is populated with:

**Patient Information:**
- Patient name (from FHIR)
- Date of birth
- Patient identifier (de-identified)
- Gender
- Address
- Phone number

**Provider Information:**
- Provider name
- NPI number
- Facility name
- Facility address

**Order Information:**
- Order/Request number
- Order date
- Expected service date
- Product details (name, SKU, quantity, size)

**Clinical Information:**
- Wound type
- Wound location
- Wound duration
- Wound size

**Insurance Information:**
- Payer name
- Insurance ID
- Place of service

### 3. Admin Review & Send Workflow

1. **IVR Generation**
   - Admin generates IVR document
   - Document is pre-populated with all required data
   - No signature required - document is ready for review

2. **Admin Review**
   - Admin downloads generated IVR document
   - Reviews for accuracy and completeness
   - Makes any necessary corrections

3. **Send to Manufacturer**
   - Admin manually emails IVR to manufacturer
   - Clicks "Mark as Sent to Manufacturer" button
   - System updates `manufacturer_sent_at` timestamp

4. **Manufacturer Approval**
   - Manufacturer reviews IVR via email
   - Sends approval confirmation back to admin
   - Admin records approval in system with reference
   - Updates status to `ivr_confirmed`

## Database Schema

### Product Request IVR Fields

```sql
-- Added to product_requests table
ivr_required BOOLEAN DEFAULT true
ivr_bypass_reason VARCHAR(255) NULL
ivr_bypassed_at TIMESTAMP NULL
ivr_bypassed_by BIGINT UNSIGNED NULL
docuseal_submission_id VARCHAR(255) NULL
docuseal_template_id VARCHAR(255) NULL
ivr_sent_at TIMESTAMP NULL
ivr_signed_at TIMESTAMP NULL
ivr_document_url VARCHAR(255) NULL
manufacturer_sent_at TIMESTAMP NULL
manufacturer_sent_by BIGINT UNSIGNED NULL
manufacturer_approved BOOLEAN DEFAULT false
manufacturer_approved_at TIMESTAMP NULL
manufacturer_approval_reference VARCHAR(255) NULL
manufacturer_notes TEXT NULL
```

### DocuSeal Tables

```sql
-- docuseal_folders
id UUID PRIMARY KEY
manufacturer_id VARCHAR(255)
docuseal_folder_id VARCHAR(255)
folder_name VARCHAR(255)
is_active BOOLEAN DEFAULT true

-- docuseal_templates
id UUID PRIMARY KEY
folder_id UUID
template_name VARCHAR(255)
template_type VARCHAR(255) -- 'IVR', 'OrderForm', etc.
docuseal_template_id VARCHAR(255)
is_active BOOLEAN DEFAULT true
is_default BOOLEAN DEFAULT false

-- docuseal_submissions
id UUID PRIMARY KEY
order_id BIGINT UNSIGNED -- References product_requests.id
docuseal_submission_id VARCHAR(255)
docuseal_template_id VARCHAR(255)
document_type VARCHAR(255)
status VARCHAR(255) -- 'pending', 'completed'
folder_id UUID
document_url VARCHAR(255)
signing_url VARCHAR(255)
metadata JSON
completed_at TIMESTAMP
```

## Configuration

### Environment Variables

```env
# DocuSeal API Configuration
DOCUSEAL_API_KEY=your_api_key_here
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_webhook_secret_here
DOCUSEAL_TIMEOUT=30
DOCUSEAL_MAX_RETRIES=3
DOCUSEAL_RETRY_DELAY=1000
```

### Service Configuration

Located in `/config/services.php`:

```php
'docuseal' => [
    'api_key' => env('DOCUSEAL_API_KEY'),
    'api_url' => env('DOCUSEAL_API_URL', 'https://api.docuseal.com'),
    'webhook_secret' => env('DOCUSEAL_WEBHOOK_SECRET'),
    'timeout' => env('DOCUSEAL_TIMEOUT', 30),
    'max_retries' => env('DOCUSEAL_MAX_RETRIES', 3),
    'retry_delay' => env('DOCUSEAL_RETRY_DELAY', 1000),
],
```

## Admin UI Integration

### Order Center Actions

The Admin Order Center (`/admin/orders`) provides IVR management through:

1. **Generate IVR Button**
   - Visible when order status is `pending_ivr`
   - Opens modal with IVR requirement options

2. **IVR Generation Modal**
   - Option to generate IVR (default)
   - Option to skip IVR with justification
   - Justification required if skipping

3. **Status Display**
   - Shows IVR status in order details
   - Displays timestamps for sent/signed
   - Links to signed documents

4. **Manufacturer Approval**
   - Button to confirm manufacturer approval
   - Requires approval reference
   - Optional notes field

## API Endpoints

### Admin Routes

```php
// Generate IVR for an order
POST /admin/orders/{productRequest}/generate-ivr
{
    "ivr_required": true|false,
    "justification": "string (required if ivr_required is false)"
}

// Confirm manufacturer approval
POST /admin/orders/{productRequest}/manufacturer-approval
{
    "approval_reference": "string (required)",
    "notes": "string (optional)"
}
```

### Webhook Route

```php
// Mark IVR as sent to manufacturer
POST /admin/orders/{productRequest}/send-ivr-to-manufacturer

// DocuSeal webhook endpoint (optional - not used for IVR workflow)
POST /webhooks/docuseal
{
    "event": "submission.completed",
    "data": {
        "id": "submission_id",
        "status": "completed",
        "documents": [...]
    }
}
```

## Security Considerations

1. **Webhook Verification**
   - HMAC-SHA256 signature verification
   - Webhook secret stored in environment
   - Signature validated before processing

2. **PHI Protection**
   - Patient data fetched from FHIR only when needed
   - No PHI stored in DocuSeal metadata
   - Document URLs stored but documents remain in DocuSeal

3. **Access Control**
   - IVR generation requires `manage-orders` permission
   - Webhook endpoint excluded from auth middleware
   - Audit trail for all IVR actions

## Business Rules

1. **One Product Rule**
   - Each ProductRequest can only have one product type
   - Multiple sizes/quantities of same product allowed

2. **IVR Requirement**
   - IVR required by default for all orders
   - Can be skipped with admin justification
   - Justification logged for audit

3. **No Signature Required**
   - IVRs are generated documents for manufacturer review
   - No provider signature needed
   - Admin reviews before sending to manufacturer

4. **Manufacturer Approval**
   - Manufacturer must approve IVR before order proceeds
   - Approval tracked manually by admin
   - Reference number required for tracking

5. **Status Progression**
   - `pending_ivr` → `ivr_sent` → `ivr_confirmed` → `approved`
   - Cannot skip stages without proper justification
   - All status changes logged

## Implementation Status

### Completed Features
- ✅ IVR generation via DocuSeal API (no signature required)
- ✅ Document generation for admin review
- ✅ Admin UI for IVR management
- ✅ Manufacturer approval tracking
- ✅ IVR skip functionality with justification
- ✅ FHIR integration for patient data
- ✅ Manufacturer-specific folder/template selection
- ✅ Manual send to manufacturer workflow

### Pending Features
- ⏳ Automated manufacturer notification
- ⏳ Bulk IVR generation
- ⏳ Direct manufacturer API integration
- ⏳ Real-time status updates via webhooks
- ⏳ Document archival system

## Troubleshooting

### Common Issues

1. **IVR Generation Fails**
   - Check DocuSeal API key configuration
   - Verify manufacturer has folder configured
   - Ensure IVR template exists in folder

2. **Webhook Not Received**
   - Verify webhook URL is publicly accessible
   - Check webhook secret configuration
   - Review DocuSeal webhook logs

3. **Signature Not Updating**
   - Ensure webhook processing is working
   - Check submission ID matches
   - Verify database connections

### Logging

All DocuSeal interactions are logged:
- IVR generation: `[info] IVR generated successfully`
- Webhook events: `[info] DocuSeal webhook received`
- Errors: `[error] Failed to generate IVR`

## Future Enhancements

1. **Automated Workflows**
   - Direct submission to manufacturers via API
   - Automatic status updates based on manufacturer responses
   - Batch processing for multiple orders

2. **Enhanced Integration**
   - Real-time document status tracking
   - Embedded signing within application
   - Multi-language template support

3. **Analytics**
   - IVR completion time metrics
   - Manufacturer response time tracking
   - Success rate reporting