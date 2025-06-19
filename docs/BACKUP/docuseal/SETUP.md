# DocuSeal Integration Setup Guide

This guide covers the setup and configuration of DocuSeal integration for the MSC Wound Care Portal.

## Prerequisites

1. **DocuSeal Account**: You need a DocuSeal account with API access
2. **API Key**: Obtain your API key from DocuSeal dashboard
3. **Webhook Secret**: Configure webhook secret for secure webhook handling

## Environment Configuration

Add the following variables to your `.env` file:

```bash
# DocuSeal Configuration
DOCUSEAL_API_KEY=your_docuseal_api_key_here
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_webhook_secret_here
DOCUSEAL_TIMEOUT=30
DOCUSEAL_MAX_RETRIES=3
DOCUSEAL_RETRY_DELAY=1000
```

## Installation Steps

### 1. Install Dependencies

The DocuSeal PHP SDK and React components are already installed:

```bash
composer require docusealco/docuseal-php
npm install @docuseal/react
```

### 2. Run Migrations

Create the DocuSeal database tables:

```bash
php artisan migrate
```

### 3. Seed Sample Templates

Populate sample DocuSeal templates:

```bash
php artisan db:seed --class=DocusealTemplateSeeder
```

## API Endpoints

The following API endpoints are available:

### Document Generation

- `POST /api/v1/admin/docuseal/generate-document`
- Generate documents for an approved order

### Submission Management

- `GET /api/v1/admin/docuseal/submissions/{submission_id}/status`
- `GET /api/v1/admin/docuseal/submissions/{submission_id}/download`
- `GET /api/v1/admin/docuseal/orders/{order_id}/submissions`

### Webhooks

- `POST /api/v1/webhooks/docuseal`
- Handle DocuSeal webhook notifications

## Usage Examples

### Generate Documents for an Order

```javascript
const response = await fetch('/api/v1/admin/docuseal/generate-document', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiToken}`,
    },
    body: JSON.stringify({
        order_id: 'order-uuid',
        docuseal_msc_template_id: 'template-uuid'
    })
});

const data = await response.json();
console.log('Document generated:', data);
```

### Check Submission Status

```javascript
const response = await fetch(`/api/v1/admin/docuseal/submissions/${submissionId}/status`, {
    headers: {
        'Authorization': `Bearer ${apiToken}`,
    }
});

const status = await response.json();
console.log('Submission status:', status);
```

## React Components

### DocuSeal Form Component

```tsx
import DocuSealFormComponent from '@/Components/DocuSeal/DocuSealForm';

<DocuSealFormComponent
    submissionId={submission.id}
    signingUrl={submission.signing_url}
    onComplete={(data) => console.log('Form completed:', data)}
    onError={(error) => console.error('Form error:', error)}
/>
```

### Submission Manager Component

```tsx
import SubmissionManager from '@/Components/DocuSeal/SubmissionManager';

<SubmissionManager
    orderId={order.id}
    onDocumentSign={(submissionId) => console.log('Document signed:', submissionId)}
    onDocumentDownload={(submissionId) => console.log('Document downloaded:', submissionId)}
/>
```

## Workflow Integration

### Order Approval Workflow

1. **Order Approved**: Admin approves an order in the system
2. **Document Generation**: System automatically generates required documents:
   - Insurance Verification Form
   - Order Form
   - Onboarding Form (if new provider)
3. **Provider Notification**: Provider receives email with signing links
4. **Document Signing**: Provider signs documents using DocuSeal interface
5. **Completion Notification**: System receives webhook notification when complete
6. **Manufacturer Delivery**: Completed documents are organized by manufacturer folder

### Document Types

- **InsuranceVerification**: Insurance verification forms
- **OrderForm**: Standard order forms with product details
- **OnboardingForm**: Provider onboarding documentation

## Security Considerations

1. **Webhook Verification**: All webhooks are verified using HMAC signatures
2. **Access Control**: API endpoints require proper authentication and permissions
3. **PHI Handling**: PHI data is fetched from Azure FHIR, not stored locally
4. **Audit Logging**: All document generation and access is logged

## Troubleshooting

### Common Issues

1. **API Key Invalid**: Verify your DocuSeal API key is correct
2. **Webhook Failures**: Check webhook secret configuration
3. **Template Not Found**: Ensure default templates are seeded
4. **Permission Denied**: Verify user has `manage-orders` permission

### Debug Mode

Enable debug logging by setting `LOG_LEVEL=debug` in your `.env` file.

### Testing

Use the manual test scripts in `tests/Manual/Api/` to test the integration:

```bash
php artisan test tests/Manual/Api/DocusealIntegrationTest.php
```

## Support

For issues with the DocuSeal integration:

1. Check the Laravel logs: `storage/logs/laravel.log`
2. Review DocuSeal API documentation: <https://www.docuseal.com/docs/api>
3. Contact MSC technical support

## Next Steps

1. Configure actual DocuSeal templates in your DocuSeal account
2. Update template IDs in the seeder
3. Set up webhook endpoints in DocuSeal dashboard
4. Test the complete workflow with real orders
