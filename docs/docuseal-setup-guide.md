# DocuSeal Integration Setup Guide

This guide will help you set up the DocuSeal integration for embedded text field tags in Quick Request IVR forms.

## Prerequisites

1. **DocuSeal Account**: You need an active DocuSeal account
2. **API Key**: Get your DocuSeal API key from your account settings
3. **Environment Configuration**: Add DocuSeal settings to your `.env` file

## Step 1: Get Your DocuSeal API Key

1. Log into your DocuSeal account at <https://app.docuseal.com>
2. Go to **Settings** → **API Keys**
3. Create a new API key or copy your existing one
4. Keep this key secure - you'll need it for the next step

## Step 2: Configure Environment Variables

Add the following to your `.env` file:

```env
# DocuSeal API Configuration
DOCUSEAL_API_KEY=your_api_key_here
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_webhook_secret_here
DOCUSEAL_TIMEOUT=30
DOCUSEAL_MAX_RETRIES=3
DOCUSEAL_RETRY_DELAY=1000

# Azure Document Intelligence (Optional - Enhances PDF analysis)
AZURE_DI_ENDPOINT=https://your-instance.cognitiveservices.azure.com
AZURE_DI_KEY=your_azure_di_key_here
AZURE_DI_API_VERSION=2023-07-31
```

Replace `your_api_key_here` with your actual DocuSeal API key.

**Optional Azure Document Intelligence Setup:**
If you have Azure Document Intelligence configured, the system will automatically enhance embedded tag extraction with:

- Improved field type detection (checkboxes, dates, signatures)
- Better field name matching and suggestions
- Layout analysis for complex forms
- Higher accuracy text extraction

## Step 3: Test the Integration

### Method 1: Using the Templates Page

1. Navigate to **Admin** → **DocuSeal** → **Templates**
2. Click on any template to open the field mapping modal
3. Go to the **"Embedded Tags"** tab
4. Click **"Create Test Submission"** to verify the integration works

### Method 2: Using Artisan Command

Run this command to test your DocuSeal connection:

```bash
php artisan tinker
```

Then run:

```php
$service = app(\App\Services\DocuSealService::class);
$result = $service->getTemplateInfo('test');
// This should return an error about template not found, but confirms API connection
```

## Step 4: Create Your First Embedded Template

### Option A: Upload via Templates Page

1. Create a PDF with embedded `{{field_name}}` tags (see examples below)
2. Go to **Admin** → **DocuSeal** → **Templates**
3. Click on a template and go to **"Embedded Tags"** tab
4. Click **"Upload PDF Template"**
5. Select your PDF file

### Option B: Create Manually in DocuSeal

1. Go to <https://app.docuseal.com/templates>
2. Create a new template
3. Upload your PDF with embedded tags
4. Copy the template ID
5. Update your manufacturer configuration with the template ID

## Example Embedded Tag PDF Content

Create a PDF with content like this:

```
WOUND CARE IVR FORM

Patient Name: {{patient_first_name}} {{patient_last_name}}
Date of Birth: {{patient_dob}}
Member ID: {{patient_member_id}}

Product: {{product_name}} ({{product_code}})
Manufacturer: {{manufacturer}}
Size: {{size}}
Quantity: {{quantity}}

Clinical Attestations:
☐ Conservative treatment failed: {{failed_conservative_treatment;type=checkbox;required=true}}
☐ Information is accurate: {{information_accurate;type=checkbox;required=true}}
☐ Medical necessity established: {{medical_necessity_established;type=checkbox;required=true}}

Provider Signature: {{provider_signature;type=signature;role=Provider;required=true}}
Date: {{signature_date;type=date}}
```

## Step 5: Configure Manufacturer Templates

Update your manufacturer configurations in `/resources/js/Pages/QuickRequest/Components/manufacturerFields.ts`:

```typescript
{
  name: 'ACZ',
  products: ['Membrane Wrap', 'Revoshield'],
  signatureRequired: true,
  docusealTemplateId: 'your_actual_template_id_here', // Replace with real template ID
  fields: [
    // ... existing fields
  ]
}
```

## Testing Quick Request Integration

1. Go to **Quick Requests** → **Create**
2. Fill out all 4 steps
3. On Step 4, if the manufacturer requires a signature, you'll see "Complete IVR Signature"
4. Click the button to test the embedded form with real DocuSeal

## Troubleshooting

### Common Issues

1. **"DocuSeal API key not configured"**
   - Check your `.env` file has `DOCUSEAL_API_KEY` set
   - Restart your Laravel server after adding the key
   - Run `php artisan config:clear` to clear cached config

2. **"Failed to upload template to DocuSeal"**
   - Verify your API key is correct
   - Check that your DocuSeal account has template creation permissions
   - Ensure PDF file is valid and under 10MB

3. **"No signing URL received"**
   - Check that the template ID exists in DocuSeal
   - Verify the template has the correct embedded field tags
   - Review Laravel logs for detailed error messages

4. **"Template fields not pre-filling"**
   - Ensure your PDF uses the exact `{{field_name}}` syntax
   - Check that field names match the Quick Request field mapping
   - Use the field reference guide in the Templates page

### Debug Logging

Check your Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log | grep -i docuseal
```

### Azure Document Intelligence Issues

1. **"Azure Document Intelligence service not available"**
   - Check that `AZURE_DI_ENDPOINT` and `AZURE_DI_KEY` are set in your `.env` file
   - Verify your Azure DI resource is active and accessible
   - Embedded tag extraction will still work without Azure DI, but with less enhancement

2. **"Azure Document Intelligence analysis failed"**
   - Check that your Azure DI endpoint is correct and includes the full URL
   - Verify your API key has Document Intelligence permissions
   - Ensure the PDF file is under 50MB and in a supported format
   - The system will gracefully fall back to basic extraction if Azure DI fails

### API Connection Test

Test your DocuSeal API connection:

```bash
curl -H "X-Auth-Token: YOUR_API_KEY" https://api.docuseal.com/templates
```

## Advanced Configuration

### Webhook Setup (Optional)

To receive notifications when forms are completed:

1. In DocuSeal, go to **Settings** → **Webhooks**
2. Add webhook URL: `https://your-domain.com/webhooks/docuseal`
3. Set webhook secret in your `.env` file

### Custom Field Mapping

You can extend the field mapping in `DocuSealTemplateController::mapEmbeddedFieldsToQuickRequest()` to support additional custom fields specific to your organization.

## Support

- **DocuSeal Documentation**: <https://docs.docuseal.com>
- **API Reference**: <https://docs.docuseal.com/api>
- **Field Tags Guide**: `/docs/docuseal-embedded-fields-guide.md`
- **Example Template**: `/docs/example-ivr-template.md`
